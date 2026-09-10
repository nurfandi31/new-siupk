# Audit Fungsi Penghapusan Pinjaman — SIUPK Next 2026-08-15

> **Pertanyaan user:** "cek fungsi penghapusan pinjaman untu pinjaman
> anggota/pemanfaat"

Investigasi read-only terhadap seluruh jalur kode yang menyentuh entitas
`Loan` dan `LoanBeneficiary`. Tidak ada perubahan kode yang dilakukan —
dokumen ini hanya memetakan state quo dan mengusulkan opsi perbaikan
sebagai persiapan diskusi.

---

## TL;DR

| Pertanyaan | Jawaban |
|---|---|
| Apakah ada "Hapus Pinjaman" yang hard-delete baris `loans`? | **TIDAK.** Tidak ada route, controller method, service method, command, job, atau endpoint admin yang menghapus baris `loans`. |
| Apakah "Penghapusan Pinjaman" di UI = hapus data? | **TIDAK.** Itu = write-off: ubah `loans.status` ke `written_off`, buat jurnal `source_type='loan_write_off'`, catat di `loan_write_offs` dan `loan_status_histories`. **Record pinjaman tetap ada.** |
| Apakah ada "Hapus Pemanfaat" yang hard-delete? | **YA, tapi 1 baris `loan_beneficiaries` saja**, bukan loan. Hanya jalan saat status `draft`/`verified`. |
| Apakah write-off reversible? | **Tidak melalui flow write-off.** Jurnal bisa di-reverse via `accounting.journals.reverse` (pembalik jurnal umum), tapi `loans.status` tidak dikembalikan ke `active`. |
| Apakah `Loan` pakai `SoftDeletes`? | **TIDAK.** Bandingkan: `Member`, `Group`, `Person`, `Asset` pakai SoftDeletes. |
| Apakah ada audit trail khusus pinjaman? | **Sebagian** — `loan_status_histories` mencatat SEMUA transisi status. Tapi tidak ada `AuditLog` terpusat (hanya dipakai modul AI Assistant). |

---

## 1. Peta Kode Saat Ini

### 1.1 Routes (`routes/web.php` lines 299–320)

```php
Route::post('/lending/loans', ...);                                 // store
Route::get('/lending/loans/{loan}', ...);                           // show
Route::put('/lending/loans/{loan}', ...);                           // update
Route::delete('/lending/loans/{loan}/beneficiaries/{member}', ...); // ← satu-satunya DELETE
Route::patch('/lending/loans/{loan}/verify', ...);
Route::patch('/lending/loans/{loan}/approve', ...);
Route::patch('/lending/loans/{loan}/disburse', ...);
Route::post('/lending/loans/{loan}/disburse', ...);
Route::patch('/lending/loans/{loan}/revert', ...);
Route::patch('/lending/loans/{loan}/committee', ...);
Route::post('/lending/loans/{loan}/reschedule', ...);
Route::post('/lending/loans/{loan}/write-off', ...);
Route::patch('/lending/loans/{loan}/complete', ...);
```

**Tidak ada `Route::delete('/lending/loans/{loan}', ...)`** — tidak ada
jalur hard-delete untuk loan.

### 1.2 LoanController (`app/Http/Controllers/Lending/LoanController.php`)

Method yang relevan dengan "hapus":

| Line | Method | Fungsi |
|---|---|---|
| 316 | `removeBeneficiary(Request, Loan, int $member, LoanService)` | Hapus 1 baris beneficiary. Status guard: hanya `draft`/`verified`. **Tidak ada Form Request AuthorizesPermission** — hanya check status di controller body. |
| 425 | `writeOff(LoanWriteOffRequest, Loan, LoanService)` | Write-off. Pakai `LoanWriteOffRequest` (Form Request dengan permission gate). |

**Tidak ada `destroy()` method.**

### 1.3 LoanService (`app/Domain/Lending/Services/LoanService.php`)

| Line | Method | Tipe |
|---|---|---|
| 217–235 | `removeBeneficiary(Loan, int $memberRowId, int $userId)` | Hard-delete 1 baris `LoanBeneficiary` |
| 660–748 | `writeOff(Loan, array $data, int $userId)` | Status change + jurnal + LoanWriteOff row |

Method lain yang menyentuh `->delete()`:

- Line 208 (`updateProposal`): `$loan->beneficiaries()->whereNotIn('member_row_id', $keep)->delete();` — hapus beneficiary yang tidak ada di keep-list saat update proposal.
- Line 1140 (`regenerateInstallmentSchedule`): `$loan->installments()->delete();` — reset jadwal angsuran saat approve.

**Tidak ada `destroy()`, `deleteLoan()`, `purge()`, `forceDelete()` di LoanService.**

### 1.4 Model `Loan` (`app/Domain/Lending/Models/Loan.php`)

- **Tidak ada `use SoftDeletes;`** (bandingkan: `Member`, `Group`, `Person`, `Asset` pakai SoftDeletes).
- Foreign key constraint di migrasi `2026_07_18_100004_create_lending_tables.php`:
  - `loan_status_histories` → `loans` (`cascadeOnDelete`)
  - `loan_payments` → `loans` (`restrictOnDelete`)
  - `loan_write_offs` → `loans` (`restrictOnDelete`)

Artinya: kalau pun ada yang mencoba `DELETE FROM loans WHERE id=?`, FK
restrict dari `loan_payments` & `loan_write_offs` akan **menolak**. Loan
tidak bisa di-`DELETE` di level SQL tanpa memutus chain.

### 1.5 Frontend (`resources/js/Pages/Lending/Loans/Show.vue`)

```vue
<!-- line 547 -->
<AppButton v-if="canWriteOff" variant="danger" icon="delete_forever"
           @click="openWriteOffModal">
    Penghapusan Pinjaman
</AppButton>
```

- **Label**: "Penghapusan Pinjaman"
- **Icon**: `delete_forever` (Material icon for permanent delete)
- **Variant**: `danger`
- **Visibility** (line 273): `can('loans.manage') && isActiveLoan.value && Number(props.loan.principal_remaining) > 0`
- **Click handler**: `openWriteOffModal()` → buka modal (bukan submit langsung)

**Modal (`Show.vue:1175-1189`):**

```vue
<AppModal v-model="writeOffModalOpen" title="Penghapusan Pinjaman" size="md">
    <p class="mb-4 text-sm text-on-surface-variant">
        Piutang sisa pokok <strong>{{ currency(loan.principal_remaining) }}</strong>
        akan dihapusbukukan. Status pinjaman menjadi <strong>Dihapus</strong>.
        Tindakan ini tidak dapat dibatalkan.
    </p>
    ...
    <AppButton variant="danger" :loading="writeOffForm.processing"
               @click="submitWriteOff">Hapus Pinjaman</AppButton>
</AppModal>
```

**Submit handler** (`Show.vue:473-478`):
```js
function submitWriteOff() {
    writeOffForm.post(`/lending/loans/${props.loan.row_id}/write-off`, {
        preserveScroll: true,
        onSuccess: () => { writeOffModalOpen.value = false; },
    });
}
```

Modal body **sudah eksplisit** menyatakan "Sisa pokok akan dihapusbukukan"
dan "Tindakan ini tidak dapat dibatalkan". Tombol footer menggunakan label
"Hapus Pinjaman" (line 1187) yang bisa diperjelas.

**Tombol removeBeneficiary** (line 532 area) memakai modal konfirmasi
standar `useConfirm()`.

### 1.6 Commands, Jobs, Admin, E2E

Semua jalur lain yang bisa menghapus data juga dipindai:

| Lokasi | Hasil scan |
|---|---|
| `app/Console/Commands/**` (20 file) | Tidak ada yang hapus loan. Semua command bersifat migrasi/discovery (baca legacy, tulis ke tabel baru). |
| `app/Jobs/**` (3 file: RecalculateMonthlyBalances, RunTenantCutoverJob, Middleware/InitializeTenant) | Tidak ada yang hapus loan. |
| `app/Http/Controllers/Admin/**` (9 controller) | Tidak ada yang reference `Loan`/`loan`. Hanya `AiAssistantController` yang punya `->delete()` (untuk persona/doc, bukan loan). |
| `routes/api.php` | Hanya prefix `assistant` (HMAC-signed). Tidak ada loan endpoint. |
| `tests/e2e/**` (14 spec) | Pencarian `delete loan`/`deleteLoan`/`hapus.pinjaman` = 0 hasil. E2E hanya menyebut path laporan `cadangan-penghapusan`. |

**Verdict**: Tidak ada jalur hard-delete loan yang tersembunyi.

---

## 2. Mekanisme Write-Off — Apa yang Sebenarnya Terjadi

`LoanService::writeOff()` (line 660-748):

```php
public function writeOff(Loan $loan, array $data, int $userId): Loan
{
    if (! in_array($loan->status, ['active', 'disbursed'], true)) {
        throw new RuntimeException('Penghapusan hanya untuk pinjaman aktif.');
    }
    ...
    $entry = JournalEntry::query()->create([
        'journal_number' => null,
        'transaction_date' => $writtenOffAt->toDateString(),
        'source_type' => 'loan_write_off',
        'source_row_id' => (int) $loan->row_id,
        'description' => sprintf('Penghapusan piutang pinjaman #%s ...', ...),
        'status' => 'draft',
        'created_by_user_id' => $userId,
    ]);
    // Debit cadangan piutang (1.1.04), Kredit piutang pokok (1.1.03)
    $entry->lines()->create([
        'line_number' => 1, 'account_row_id' => $allowance,
        'debit' => $principalRemaining, ...
    ]);
    $entry->lines()->create([
        'line_number' => 2, 'account_row_id' => $receivable,
        'credit' => $principalRemaining, ...
    ]);
    $posted = $this->journalPosting->post($entry, $userId); // → 'posted'

    LoanWriteOff::query()->create([...]);

    $loan->update([
        'status' => 'written_off',
        'completed_at' => $writtenOffAt->toDateString(),
        'guidance_notes' => $reason !== '' ? $reason : $loan->guidance_notes,
    ]);

    $loan->statusHistories()->create([
        'from_status' => $fromStatus,
        'to_status' => 'written_off',
        'principal_amount' => $principalRemaining,
        'notes' => $reason !== '' ? $reason : 'Penghapusan piutang.',
        'changed_by_user_id' => $userId,
        'changed_at' => now(),
    ]);
}
```

**3 efek samping write-off (semua di 1 transaksi):**
1. `JournalEntry` baru dengan `source_type='loan_write_off'` (status: `posted`).
2. `LoanWriteOff` row baru.
3. `Loan.status` → `written_off`, `completed_at` terisi, history tercatat.

**Record pinjaman TIDAK dihapus** — hanya di-flag `written_off`. Status
ini permanen (lihat §3).

---

## 3. Apakah Write-Off Reversible?

**Tidak melalui flow write-off.**

| Jalur | Efek |
|---|---|
| `Route::post('/lending/loans/{loan}/write-off', ...)` | Hanya forward (active → written_off). |
| Generic journal reversal (`Route::post('/accounting/journals/{entry}/reverse')`) | Membalik debit/kredit jurnal jadi jurnal baru. **TIDAK mengembalikan `loans.status` dari `written_off` ke `active`.** |
| `LoanController` lain | Tidak ada method reverse/un-write-off. |

Grep `unwriteOff|reverseWriteOff|revertWriteOff|un.write` di seluruh
project = **No matches found**.

**Artinya**: Sekalipun user me-reverse jurnalnya lewat modul accounting,
status loan tetap `written_off` dan `LoanWriteOff` row tetap ada. Tiga
artefak (journal entry + LoanWriteOff row + loan.status) tidak coupled
untuk reversal.

---

## 4. Audit Trail

### 4.1 `loan_status_histories` — Mencatat SEMUA transisi

Model `LoanStatusHistory` (line 113-126 di migrasi) mencatat setiap
transisi status:

| Line LoanService | Transisi yang dicatat |
|---|---|
| 152 | `null → draft` (createProposal) |
| 310 | `* → verified` (verify) |
| 354 | `* → waiting` (approve) |
| 382 | `* → active` (disburse) |
| 644 | `{verified/waiting/approved} → draft` (revertToDraft) |
| **735** | **`{active/disbursed} → written_off` (writeOff)** |
| 824 | `{active/disbursed} → rescheduled` (reschedule) |
| 901–919 | 4 baris sintetis untuk loan baru hasil reschedule |
| 1107 | `{active/disbursed} → completed` (complete) |

Test konfirmasi di `tests/Feature/Lending/LoanLifecycleTest.php:433-435`:
```php
$fresh->statusHistories()
    ->where('to_status', 'written_off')
    ->get()
```

### 4.2 `AuditLog` terpusat — TIDAK digunakan modul Lending

Pencarian `AuditLog::record` di `app/Domain/Lending/**` = **No matches
found**. `AuditLog` hanya dipakai di modul AI Assistant (lihat memory
`[[Audit Logging Pattern]]`). Artinya: write-off tidak tercatat di
`audit_logs` — hanya tercatat di `loan_status_histories` (yang tidak
menyimpan IP, user-agent, atau signature request).

### 4.3 `loan_activities` — TIDAK ADA

Berbeda dengan modul lain (membership, accounting) yang punya tabel
activity log, lending tidak punya tabel sendiri. Audit hanya bergantung pada
activity log yang tersimpan di `loan_status_histories`.

---

## 5. Permission Matrix untuk Aksi "Hapus"

Modul lending hanya punya 1 permission relevan: **`loans.manage`**
(di `config/permissions.php`).

| Aksi | Permission yang dipakai | Backend gate | Frontend gate |
|---|---|---|---|
| **Write-off** | `loans.manage` | `LoanWriteOffRequest` (Form Request dengan `AuthorizesPermission`) | `canWriteOff` computed di `Show.vue:273` |
| **Remove beneficiary** | `loans.manage` (atau tidak ada?) | **TIDAK ADA Form Request** — hanya status check di controller body | `canRemoveBeneficiary` computed di Show.vue |
| **Reschedule** | `loans.manage` | `LoanRescheduleRequest` | `canReschedule` |
| **Edit proposal** | `loans.manage` | `LoanUpdateRequest` | `canEdit` |
| **Revert** | `loans.manage` | `LoanRevertRequest` (?) | — |
| **Complete** | `loans.manage` | (in LoanController) | `canCompleteAction` |

### 5.1 Temuan C-1: `removeBeneficiary` tidak punya permission gate di backend

`LoanController::removeBeneficiary()` (line 316) signature:
```php
public function removeBeneficiary(Request $request, Loan $loan, int $member, LoanService $loans): RedirectResponse
```

- Pakai raw `Request`, bukan Form Request.
- Hanya cek `in_array($loan->status, ['draft', 'verified'], true)`.
- **Tidak instantiate Form Request yang extend `AuthorizesPermission`.**

Perbandingan dengan `writeOff()` (line 425):
```php
public function writeOff(LoanWriteOffRequest $request, Loan $loan, LoanService $loans): RedirectResponse
```

→ Pakai `LoanWriteOffRequest` (Form Request dengan permission check).
**Ini konsisten untuk write-off, tapi tidak untuk removeBeneficiary.**

**Implikasi**: Frontend `canRemoveBeneficiary` (computed) mungkin
menyembunyikan tombol, tapi siapa pun yang tau URL `/lending/loans/{loan}/beneficiaries/{member}`
dengan method DELETE + auth session valid **bisa langsung execute**.
Mitigasi: middleware `auth` + role-based route protection di level group
mungkin sudah cukup (lihat `routes/web.php` group prefix), tapi **tidak
ada defense-in-depth permission check khusus di action**.

**Severity**: Medium. Di tenant DB kecil dengan 1-3 admin, risiko rendah.
Tapi best practice Laravel = selalu pakai Form Request dengan
`AuthorizesPermission` trait untuk konsistensi.

---

## 6. Edge Cases & Safety Nets

| Skenario | Status saat ini |
|---|---|
| Admin mau hapus loan salah ketik | Tidak bisa — tidak ada endpoint. |
| Admin mau "rollback" write-off | Tidak bisa — tidak ada reverse flow. Hanya bisa reverse jurnal umum, dan loan tetap `written_off`. |
| Loan salah status (mis. di-`active` padahal salah input) | Bisa di-`revert` ke draft (line 644), lalu diedit. Tapi `revertToDraft` hanya jalan dari `verified`/`waiting`/`approved`, bukan dari `active`. Jadi tidak ada rollback dari `active`/`disbursed`. |
| Salah klik "Hapus Pemanfaat" | Modal `useConfirm()` standar, ada tombol Cancel. Tapi tidak ada undo setelah eksekusi. |
| Bulk corruption (mis. migration gagal tengah jalan) | Bisa di-truncate via `migrate:fresh` (testing only) atau operasi SQL manual. Tidak ada admin UI. |
| Force-delete loan via SQL | FK restrict dari `loan_payments` & `loan_write_offs` memblokir. Harus hapus dependent rows dulu (cascade order: payments → write_offs → loan). Tidak ada tool internal. |

---

## 7. Rekomendasi (TIDAK dilakukan — hanya opsi)

### 7.1 Perbaiki UX label (Rendah, ~10 menit)

Jika perlu akurasi kosmetik:

1. **Show.vue:547** — Ganti `icon="delete_forever"` → `icon="delete_sweep"` atau `cancel_presentation`. Label tetap "Penghapusan Pinjaman" (sesuai copy akuntansi "penghapusan piutang").
2. **Show.vue:1187** — Ganti label tombol modal footer "Hapus Pinjaman" → "Hapus Bukukan Piutang".

Modal body sudah benar — tidak perlu diubah.

### 7.2 Tambah `AuditLog` untuk write-off (Medium, ~30 menit)

Tambahkan 1 baris di `LoanService::writeOff()` setelah `$loan->update(...)`:
```php
AuditLog::record('lending.loan.write_off', $loan, [
    'amount' => $principalRemaining,
    'reason' => $reason,
]);
```
Supaya masuk ke audit trail terpusat (dengan IP, user-agent, dll).

### 7.3 Tambah `AuthorizesPermission` ke `removeBeneficiary` (Medium, ~15 menit)

Buat `LoanRemoveBeneficiaryRequest` yang extend FormRequest + `AuthorizesPermission`:
```php
public function authorize(): bool {
    return $this->user()->can('loans.manage');
}
```
Ganti signature `removeBeneficiary()` controller agar pakai Form Request
baru. Defense-in-depth.

### 7.4 Tambah SoftDeletes ke Loan (Tinggi, ~2-4 jam + diskusi)

Implikasi besar:
- **Foreign key**: `loan_status_histories` sudah `cascadeOnDelete` — OK.
  `loan_payments` & `loan_write_offs` `restrictOnDelete` — perlu migrasi
  ke `nullOnDelete` atau model logic.
- **Restore**: perlu UI atau Artisan command.
- **Index**: butuh `deleted_at` di composite indexes.
- **Existing loans**: perlu strategi backfill (default `NULL`).
- **Reporting**: query `Loan::query()` sekarang perlu eksplisit
  `->withTrashed()` untuk beberapa report (e.g. laporan cadangan
  penghapusan perlu lihat yang sudah di-soft-delete).

**Tidak disarankan tanpa diskusi** — dampak ke reporting & migration besar.

### 7.5 Endpoint admin hard-delete (Sangat tinggi, JANGAN tanpa diskusi)

Kalau perlu "force delete loan" untuk koreksi data:

1. Tambah `Route::delete('/admin/tenants/{tenant}/lending/loans/{loan}', ...)` — khusus superadmin, di-prefix tenant.
2. Method di `TenantAdminController` (atau baru `LendingAdminController`):
   - Validasi: loan tidak punya payments (atau null-kan payments dulu).
   - Hapus dependent: `loan_payments.allocations`, `loan_payments`, `loan_write_offs`, `loan_beneficiaries`, `loan_installments`, `loan_status_histories` (cascade aman).
   - Hard-delete loan.
   - Audit log wajib.
3. Konfirmasi modal dengan double-confirmation (ketik ID loan).
4. Test eksplisit di `tests/Feature/Admin/ForceDeleteLoanTest.php`.

**Tidak direkomendasikan untuk umum** — fitur ini harus opt-in untuk
superadmin tenant dan diberi watermark "TIDAK UNTUK PRODUKSI".

---

## 8. Ringkasan Final

| Item | Verdict |
|---|---|
| Ada "hapus pinjaman" hard-delete? | **TIDAK** — tidak ada di routes, controller, services, commands, jobs, api, admin, atau tests. |
| Tombol "Penghapusan Pinjaman" = hapus data? | **TIDAK** — itu = write-off (status change + jurnal). Record tetap ada. |
| Tombol "Hapus Pemanfaat" = hapus data? | **YA, 1 baris beneficiary** — hanya saat `draft`/`verified`. Tidak ada soft-delete. |
| Write-off reversible? | **Tidak** — tidak ada reverse flow. Jurnal bisa di-reverse generic tapi status loan tidak dikembalikan. |
| Audit trail? | **Sebagian** — `loan_status_histories` lengkap untuk SEMUA transisi (verified by test). Tidak ada `AuditLog` terpusat. |
| Permission gate konsisten? | **Tidak** — `removeBeneficiary` tidak pakai Form Request dengan `AuthorizesPermission`. Write-off sudah benar. |
| Loan pakai `SoftDeletes`? | **TIDAK** — beda dengan `Member`/`Group`/`Person`/`Asset`. FK constraint sudah `restrictOnDelete` di payments & write_offs. |

**Kesimpulan untuk user**: Sistem saat ini **sudah aman dari hard-delete
tidak sengaja** — tidak ada jalur yang bisa menghapus loan record tanpa
sengaja (tidak ada endpoint, FK restrict). Yang ada hanya **write-off**
(yang sebenarnya adalah "dihapusbukukan" dalam terminologi akuntansi,
bukan "dihapus dari database") dan **hapus pemanfaat** (hapus 1 baris
beneficiary, hanya di tahap awal). UX label "Penghapusan Pinjaman" +
icon `delete_forever` memang misleading — itu bisa diperbaiki di Show.vue
tanpa mengubah backend.
