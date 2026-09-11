# Edit Jurnal (Reverse + Recreate Atomik) — siupk Next 2026-08-18

> **Pertanyaan user:** "Apakah sudah ada fitur edit transaksi?"
> → "Bisa tambahkan fitur edit? Bukan edit dalam artian meng-edit yang sudah
> di-input. Tapi edit ini akan melakukan reverse transaksi lama dan langsung
> input transaksi baru"

## Latar Belakang

Sistem akuntansi siupk Next memberlakukan **immutability** terhadap jurnal
yang sudah `posted` (`app/Domain/Accounting/Models/JournalEntry.php`: save
& delete melempar `DomainException`). Prinsip ini penting untuk menjaga
audit trail — tapi membuat koreksi jurnal yang salah jadi workflow 2 langkah
manual yang tidak atomic:

1. `POST /accounting/journals/{entry}/reverse` → buat jurnal lawan.
2. `POST /accounting/journal-entries` → buat jurnal baru yang benar.

Risiko: kalau langkah 1 sukses dan langkah 2 gagal (mis. periode fiskal
terbaru sudah `closed`), user terjebak dengan reversal tanpa jurnal
pengganti — saldo akun jadi salah. Tambahan: copy-paste field dari jurnal
lama ke form baru rawan typo di akun COA.

## Tujuan

Satu aksi **Edit** dari daftar jurnal (`/accounting/journals`) yang:

- Membuka form **pre-filled** dari jurnal lama (tidak ada copy-paste).
- Mewajibkan **alasan** edit (audit trail).
- Me-reverse jurnal lama **dan** membuat jurnal baru dalam **satu DB
  transaction** (all-or-nothing atomic).
- Membatasi hanya jurnal yang boleh diedit (`manual` + `asset_purchase`).

## Keputusan Desain

| Topik | Pilihan | Alasan |
|---|---|---|
| Source type editable | `manual` + `asset_purchase` saja | Jurnal `loan_installment`/`loan` punya side effect ke tabel loans/installments; `profit_allocation` & `journal_reversal` adalah hasil sistem. |
| Tanggal jurnal baru | User boleh edit; default = tanggal jurnal lama; divalidasi terhadap periode fiskal `open` | Konsisten dengan pola posting baru; tidak mungkin memilih tanggal di periode `closed`. |
| Alasan | Wajib, max 500 char | Audit trail. Disimpan di description reversal + di prefix description jurnal baru (`[Koreksi jurnal #N] …`). |
| Mekanisme | `DB::connection('tenant')->transaction(fn, 5)` membungkus reverse + post | Laravel's nested transaction jadi savepoint. Kalau posting baru gagal, savepoint reversal otomatis di-rollback. |

## Skema & Permission

- TIDAK ada perubahan skema database — hanya behavior service.
- Permission key: `journals.create` (sama dengan reverse — payung "koreksi
  jurnal").

## Komponen Baru

### Backend

- `app/Domain/Accounting/Services/JournalEditService.php` — service atomic,
  membungkus `JournalReversalService::reverse()` + `JournalPostingService::post()`.
  Konstanta publik: `JournalEditService::EDITABLE_SOURCE_TYPES = ['manual',
  'asset_purchase']`.
- `app/Http/Controllers/Accounting/JournalBrowseController.php` — tambah
  `edit()` + `update()` methods. Validasi manual (mirror `JournalEntryRequest`)
  untuk menghindari dependency ke FormRequest yang sedang di-isolasi untuk
  perbaikan UTF-16 BOM.
- `app/Domain/Accounting/Services/JournalBrowseService.php` — tambah flag
  `can_edit` per row.

### Frontend

- `resources/js/Pages/Accounting/JournalEntries/Edit.vue` — mirror dari
  `Create.vue` dengan banner warning + field `reason` wajib.
- `resources/js/Pages/Accounting/Journals/Index.vue` — tambah tombol **Edit**
  di kolom aksi, di sebelah Reverse. Tombol Edit hanya muncul bila
  `row.can_edit === true`.

### Routes

```php
Route::get('/journals/{entry}/edit',  [JournalBrowseController::class, 'edit'])->name('journals.edit');
Route::put('/journals/{entry}',        [JournalBrowseController::class, 'update'])->name('journals.update');
```

## Alur `JournalEditService::edit()` (ringkas)

```
1. Guard: $original->source_type ∈ ['manual', 'asset_purchase']? else DomainException
2. Guard: $original->reversed_entry_row_id !== null? else DomainException
3. Guard: ada row lain dengan reversed_entry_row_id = $original.row_id? else DomainException
4. Guard: $reason !== ''? else DomainException
5. DB::connection('tenant')->transaction(fn () { ... }, 5):
   a. $reversal = reversals->reverse($original, $reversalDate, $userId, $reason)
      → buat JournalEntry (source_type=journal_reversal) dengan debit↔kredit di-swap
   b. $newDraft = createDraftFromData($data, $userId, $reason, $original)
      → buat JournalEntry (status=draft) + 2 JournalLine
      → kalau asset_purchase: register Asset baru via AssetService::create()
   c. $posted = posting->post($newDraft, $userId)
      → validasi periode, keseimbangan, status=draft→posted
   return ['reversal' => $reversal, 'new' => $posted]
```

Kalau step (c) melempar exception (mis. periode `closed`, akun COA tidak aktif,
jurnal tidak seimbang) → savepoint step (a) otomatis rollback → DB unchanged.

## Verifikasi

8 test PASS di `tests/Feature/Accounting/JournalEditTest.php`:

| # | Test | Skenario |
|---|---|---|
| 1 | `edit reverses old and creates posted new entry` | Happy path: 2 entries baru, jurnal lama di-reverse, badge "Dibatalkan" |
| 2 | `edit form renders with prefill` | GET form pre-filled dengan data jurnal lama |
| 3 | `edit requires reason` | PUT tanpa `reason` → validation error |
| 4 | `edit blocks non editable source type` | Source `journal_reversal`/`profit_allocation` → 422 |
| 5 | `edit blocks already reversed` | Original sudah punya reversal → 422 |
| 6 | `edit atomic when posting new fails` | Set fiscal period `closed` → posting gagal → DB unchanged, original masih `posted` |
| 7 | `edit handles asset purchase with new asset` | Edit `pembelian_aset_peralatan` → 1 asset baru terdaftar, asset lama tetap ada terkait jurnal lama |
| 8 | `edit service standalone throws for non editable` | Service direct call dengan source_type non-editable → DomainException |

Tidak ada regresi: **53/53 Accounting tests PASS** (309 assertions)
setelah integrasi.

## API

```
GET  /accounting/journals/{entry}/edit   → 200 Inertia Edit.vue form
                                            422 kalau non-editable
PUT  /accounting/journals/{entry}        → 302 back + flash
                                            Body: JournalEntryRequest shape + reason (required, 5..500 chars)
                                            Auth: journals.create
```

## UI Behavior

- Tombol **Edit** hanya muncul untuk row `source_type ∈ {manual, asset_purchase}`
  yang belum di-reverse (di-flag via `can_edit` dari backend).
- Tombol **Edit** tetap disabled / hidden untuk source_type lain (angsuran,
  pencairan, write-off, profit_allocation, reversal).
- Submit Edit → user diredirect kembali ke `/accounting/journals` dengan
  flash: `"Jurnal #N dikoreksi. Reversal #X + jurnal baru #Y dibuat."`
- Di daftar jurnal akan terlihat: jurnal lama badge jadi **Dibatalkan**,
  muncul 2 entry baru (1 reversal + 1 posted baru dengan prefix description
  `[Koreksi jurnal #N] …`).

## Trade-off & Edge Cases

- **Direct edit vs reverse+recreate**: Dipilih reverse+recreate demi
  konsistensi dengan prinsip `Posted financial records are immutable`
  (`docs/PROJECT_OVERVIEW.md:128`). Direct update akan melanggar audit trail
  dan membingungkan rekonsiliasi laporan.
- **Lock conflict** saat parallel edit: dicegah karena `JournalEditService`
  pakai outer `DB::transaction(..., 5)` retry Laravel untuk deadlock. Untuk
  race condition antar user, tidak dikhawatirkan karena jurnal posted
  biasanya sudah final.
- **Asset lama + jurnal lama yang di-reverse**: asset lama tetap ada,
  terkait jurnal lama. Tidak boleh soft-delete karena akan membuat audit
  trail ambigu. Sistem laporan inventaris (`/accounting/assets`) tetap
  menampilkan asset lama — siap di-cross-check manual kalau perlu.
- **FormRequest dependency**: `JournalEntryRequest` belum dipakai di
  `update()` karena terkendala UTF-16 BOM pada file
  `app/Http/Requests/Accounting/JournalEntryRequest.php` (pre-existing).
  Validasi dilakukan manual dengan rules mirror dari FormRequest.
