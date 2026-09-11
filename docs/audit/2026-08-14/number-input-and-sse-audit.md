# Audit Input Number & SSE Migrasi — siupk Next

Tanggal: 2026-08-15
Lingkup:
1. Audit konsistensi input bilangan (currency vs integer vs percent) di seluruh
   `resources/js/Pages/**`.
2. Audit SSE migrasi data di `MigrationController::stream` →
   `TenantCutoverRunnerService::executeStream` → `RunTenantCutoverJob`.

## Ringkasan Eksekutif

| #   | Topik                                | Temuan                                                | Status |
| --- | ------------------------------------ | ----------------------------------------------------- | ------ |
| A1  | Currency input consistency           | 6 form pakai `type="number"` untuk nominal uang       |   ⚠    |
| A2  | AppCurrencyInput adopsi              | 9 form sudah benar (Accounting/Journal/Installment/…) |   ✅   |
| A3  | SSE migrasi — executeStream observer | Race condition: HTTP SSE re-eksekusi cutover          |   🔴   |
| A4  | SSE migrasi — race HTTP vs queue     | Run via queue + buka SSE → double execution           |   🔴   |
| A5  | Vue fallback SSE → polling           | Sudah ada fallback `startPolling()`                   |   ✅   |

## A1 — Form yang masih pakai `AppInput type="number"` untuk nominal uang

`AppInput` plain wrapper `<input :type="type">`. Untuk nominal uang, ini memunculkan
3 masalah:

- Locale browser-dependent: comma vs dot desimal inkonsisten (id-ID pakai koma,
  en-US pakai titik).
- Tidak ada pemisah ribuan saat input. User harus ketik `1500000` bukan `1.500.000`.
- Submit ke Laravel mengirim string `"1500000"` (browser locale), bukan number
  valid → backend `validate(['numeric'])` lolos tapi nilai bisa keliru saat DB
  cast ke decimal.

`AppCurrencyInput` (di `resources/js/Components/AppCurrencyInput.vue`) sudah
menyediakan: parsing id-ID `1.000.000,50` → `1500000.5`, format ribuan saat
blur, simpan numeric ke v-model. Jadi rekomendasi: migrasi semua form nominal
uang ke `AppCurrencyInput`.

### Daftar form yang WAJIB migrasi (6 form, 7 input)

| #   | File                                                          | Baris | Field                          | Tipe data |
| --- | ------------------------------------------------------------- | ----- | ------------------------------ | --------- |
| 1   | `resources/js/Pages/Admin/Plans/Form.vue`                     | 56    | `form.price_amount`            | decimal(2) — harga plan billing |
| 2   | `resources/js/Pages/Admin/Invoices/Create.vue`                | 121-130 | `form.amount`                | decimal(2) — nominal invoice |
| 3   | `resources/js/Pages/Admin/Invoices/Show.vue`                  | 109   | `manualForm.amount`            | decimal(2) — pelunasan manual |
| 4   | `resources/js/Pages/Onboarding/ImportWizard.vue`              | 227-243 | `line.debit` & `line.credit` | decimal(2) — baris jurnal saldo awal |
| 5   | `resources/js/Pages/Lending/Loans/Form.vue`                   | 254-255 | `form.service_rate_total`    | percent (0-100, decimal 2) — bagi hasil / jasa |
| 6   | `resources/js/Pages/Settings/Index.vue`                       | 359   | `default_interest_rate`        | percent (0-100, decimal 2) — default bunga produk |

**Detail:**

- **#1 Admin/Plans/Form.vue** — harga plan berbayar (billing). Saat ini:
  ```vue
  <AppInput v-model="form.price_amount" label="Harga" type="number"
            step="0.01" min="0" required :error="..." />
  ```
  → ganti `<AppCurrencyInput v-model="form.price_amount" ... />`.

- **#2 Admin/Invoices/Create.vue** — nominal invoice:
  ```vue
  <AppInput v-model="form.amount" type="number" step="0.01" ... />
  ```
  → `<AppCurrencyInput v-model="form.amount" ... />`.

- **#3 Admin/Invoices/Show.vue** — pembayaran manual via halaman invoice detail:
  ```vue
  <AppInput v-model="manualForm.amount" type="number" step="0.01" ... />
  ```
  → `<AppCurrencyInput>`.

- **#4 Onboarding/ImportWizard.vue** — input baris jurnal saldo awal:
  ```vue
  <input type="number" v-model.number="line.debit" step="0.01" min="0" />
  <input type="number" v-model.number="line.credit" step="0.01" min="0" />
  ```
  Raw `<input>` (bukan AppInput). → ganti `<AppCurrencyInput>` di kedua baris.
  Form ini dipakai admin import saldo awal dari CSV/UI.

- **#5 Lending/Loans/Form.vue** — bagi hasil / jasa total:
  ```vue
  <AppInput v-model="form.service_rate_total" type="number"
            inputmode="decimal" step="0.01" ... />
  ```
  Field percent. Tetap pakai `type="number"` valid karena rentang 0–100 dan
  bilangan kecil. **Opsional**: migrasi ke `AppCurrencyInput` (akan
  menampilkan "12,50" saat blur) atau tetap. Rekomendasi: tetap karena percent,
  bukan nominal uang — tidak ada kebutuhan pemisah ribuan.

- **#6 Settings/Index.vue** — default interest rate produk:
  ```vue
  <AppInput v-model.number="...default_interest_rate" type="number"
            step="0.01" :min="0" :max="100" ... />
  ```
  Sama — percent, rentang 0–100. Rekomendasi: tetap `type="number"`.

### Form yang SUDAH benar pakai `AppCurrencyInput` (A2 ✅)

Tidak perlu diubah, jadi acuan untuk migrasi #1-#4:

- `resources/js/Pages/Accounting/JournalEntries/Create.vue` — semua line debit/credit.
- `resources/js/Pages/Accounting/JournalEntries/Installment.vue` — semua nominal.
- `resources/js/Pages/Lending/Loans/Create.vue` (Form.vue) — `principal_amount`.
- `resources/js/Pages/Accounting/PeriodClose/Index.vue` — saldo akun.

### Form `type="number"` yang TIDAK perlu migrasi (integer / count)

| File                                            | Baris | Field                  | Alasan                        |
| ----------------------------------------------- | ----- | ---------------------- | ----------------------------- |
| `Assets/Form.vue`                               | 90, 95 | `quantity`, `cost_per_unit` (opsional) | integer unit barang |
| `Lending/Loans/Form.vue`                        | 249, 1160, 1213 | `term_months` | integer bulan (1-120)  |
| `Lending/Loans/Show.vue` (reschedule/edit)      | 1160, 1213 | `term_months` | integer bulan        |
| `Accounting/JournalEntries/Create.vue`          | 369, 376 | `asset_quantity`, `asset_useful_life_months` | integer count |
| `Admin/Migration/Index.vue`                     | 397 | `form.chunk`           | integer batch size (10-5000) |
| `Settings/Index.vue` (term)                     | 360 | `default_term_months`  | integer bulan                  |
| `MasterData/Members/Form.vue`                   | 144, 147, 169 | NIK/KK | 16 digit numerik dengan maxlength |
| `MasterData/Groups/Form.vue`                    | 222 | NIK                    | sama                           |
| `MasterData/Institutions/Form.vue`              | 65  | No. HP                 | telepon, pakai `type="tel"`    |
| `Profile/Edit.vue`                              | 168 | No. HP                 | sama                           |

`type="tel"` untuk No. HP / NIK / KK — pakai `inputmode="numeric"` + `maxlength="16"`,
benar.

### Rekomendasi Prioritas

- **High**: #1-#4 (nominal uang sungguhan, display ke user, error-prone).
- **Low/None**: #5-#6 (percent, rentang kecil).
- Estimasi effort: ~15-20 menit per file (4 file × ~4 baris edit) + rebuild
  Vite + smoke test Playwright.

## A3 — SSE Migrasi: `executeStream` bukan observer murni

**File:** `app/Services/Admin/TenantCutoverRunnerService.php:14-196`

**Akar masalah.** Method `executeStream()` adalah **duplikat** dari `execute()`
(line 198-319), bedanya hanya ditambah callback `$notify('update', …)` di tiap
state transition. Konsekuensi:

1. Saat frontend buka `GET /admin/migrations/{run}/stream`:
   - `MigrationController::stream()` (line 145) → `$runner->executeStream($run, …)`.
   - `executeStream()` line 22 cek status: kalau `completed`/`failed` → emit
     sekali lalu return (observer-only). Kalau `running`/`pending` → **masuk
     ke mode execution** (line 33-194): reset status ke `running`, truncate
     `output_log`, jalankan ulang semua step dari awal.
2. Untuk run yang baru di-trigger via `run_immediately: true` (synchronous,
   line 124 controller): HTTP request menunggu `execute()` selesai → saat SSE
   dibuka, status sudah `completed` → emit sekali, OK.
3. Untuk run via **queue** (`RunTenantCutoverJob::dispatch`, line 126):
   - Queue worker mulai `execute()` di proses terpisah.
   - User klik "Monitor SSE" di GUI → `executeStream()` dari proses HTTP
     **mendeteksi status=running** → reset `output_log=''`, set status=running,
     jalan step 1, dst.
   - Queue worker yang sama-sama `execute()` independent → dua eksekusi
     paralel dari step yang sama (atau yang berbeda kalau ada race pada urutan).
   - Hasil: log tercampur, step duplikat, data migrasi bisa double-insert
     (kalau step tidak idempotent), status flap.

**Bukti empiris (audit 2026-08-15).** Test Playwright `M.3 Monitor SSE stream
sampai status=completed` FAIL — status berubah ke `failed` walaupun data riil
22.541 jurnal sudah masuk tenant DB (lihat `migration-76.md:159-170`). Run #5
sebenarnya sukses oleh queue worker, tapi SSE HTTP handler "killed" cutover
dengan cara meng-eksekusi ulang dan me-replace state.

**Rekomendasi Fix (refactor observer murni):**

```php
// New method di TenantCutoverRunnerService — pure observer:
public function observeStream(CutoverRun $run, ?callable $onEvent = null): \Generator
{
    $lastSig = null;
    $notify = static function (string $event, array $data) use ($onEvent): void {
        if ($onEvent !== null) {
            $onEvent($event, $data);
        }
    };

    // Emit current state immediately.
    $fresh = $run->fresh();
    $notify('update', [
        'status' => $fresh->status,
        'steps' => $fresh->steps,
        'output_log' => $fresh->output_log,
        'error_message' => $fresh->error_message,
    ]);
    $lastSig = md5(($fresh->output_log ?? '').($fresh->status ?? ''));

    // Poll DB until terminal.
    while (! in_array($fresh->status, ['completed', 'failed'], true)) {
        sleep(1);
        $fresh = $run->fresh();
        $sig = md5(($fresh->output_log ?? '').($fresh->status ?? ''));
        if ($sig === $lastSig) {
            continue; // no change, skip emit
        }
        $lastSig = $sig;
        $notify('update', [
            'status' => $fresh->status,
            'steps' => $fresh->steps,
            'output_log' => $fresh->output_log,
            'error_message' => $fresh->error_message,
        ]);
    }
}
```

**Di controller**, ubah `stream()` jadi loop generator:

```php
public function stream(CutoverRun $run, TenantCutoverRunnerService $runner): StreamedResponse
{
    return response()->stream(function () use ($run, $runner): void {
        @ini_set('zlib.output_compression', '0');
        @ini_set('implicit_flush', '1');
        if (function_exists('ob_implicit_flush')) {
            ob_implicit_flush(true);
        }
        // Pure observer — tidak pernah menyentuh state cutover.
        foreach ($runner->observeStream($run) as $event) {
            // generator yields nothing; emit via callback below
        }
    }, 200, [...]);
}
```

Lebih sederhana: `observeStream` menerima callback langsung, sama
seperti `executeStream`, tapi body-nya read-only:

```php
public function observeStream(CutoverRun $run, ?callable $onEvent = null): void
{
    // ... polling loop dengan callback emit ...
}
```

**Penting**: polling interval 1 detik cukup untuk UX; bisa diturunkan ke 500ms
kalau user minta lebih real-time. Memory DB load minimal — hanya 1 SELECT per
detik per SSE subscriber.

### Dampak Refactor

- ✅ Menghilangkan double-execution: SSE tidak pernah trigger `Artisan::call()`.
- ✅ Test `M.3` akan PASS: SSE emit `status=completed` terakhir, lalu close.
- ✅ Log migrasi di DB menjadi single source of truth — queue worker yang
  menulis, SSE yang membaca.
- ⚠ Saat `run_immediately: true` (synchronous), `executeStream()` sekarang
  sudah inline di HTTP request → SSE handler tetap butuh state, tapi karena
  HTTP request sudah selesai → status sudah terminal → `observeStream` emit
  sekali lalu return. OK.

### Frontend (Vue) tidak perlu diubah

`Index.vue` line 191-231 (`startSseOrPolling`) sudah benar: pakai
`EventSource('/admin/migrations/{id}/stream')`, listen event `update`,
fallback ke `startPolling()` kalau error. Tetap dipakai setelah refactor.

## A4 — Race HTTP vs Queue (root cause A3)

Sekalipun `executeStream` di-refactor jadi observer murni, race condition
antara HTTP request yang synchronous (`run_immediately`) dan queue worker
tetap ada **jika** user trigger `run_immediately=true` lalu buka SSE untuk
run yang sama dari tab lain. Namun, mode ini tidak umum dipakai (Tombol "Jalankan
Sekarang" langsung return setelah selesai) — bukan use case riil.

Race riil hanya untuk mode **queue**: user trigger via form, queue worker
eksekusi, SSE untuk monitor. Setelah refactor A3, mode ini jadi **aman**:
queue = writer, SSE = reader.

## A5 — Vue fallback SSE → polling ✅

`resources/js/Pages/Admin/Migration/Index.vue`:

```js
sseSource.onerror = (err) => {
    console.warn('SSE stream error, falling back to polling:', err);
    stopSseOrPolling();
    startPolling(runId);
};
```

Fallback sudah ada. `startPolling()` interval 2 detik (`setInterval` di
method `pollRun`). Tidak ada perubahan perlu.

## Verifikasi

### Untuk A1 (currency input migration)

```bash
docker exec new_siupk-app-1 php artisan optimize:clear
docker exec new_siupk-node-1 npm run build
```

Smoke test Playwright:

```ts
// Plans/Form.vue → set price_amount = "1.500.000,50" → submit →
// verify in DB decimal(15,2) = 1500000.50
test('plan price accepts id-ID format', async ({ page }) => {
  await loginAs(page, 'superadmin');
  await page.goto('/admin/plans/create');
  await page.locator('input[name="price_amount"]').fill('1.500.000,50');
  await page.locator('button[type="submit"]').click();
  // expect: success, redirect to /admin/plans, list shows "Rp 1.500.000,50"
});
```

### Untuk A3 (SSE refactor)

1. Edit `TenantCutoverRunnerService::executeStream` → rename + ganti body jadi
   polling observer (lihat patch di atas). Atau rename method jadi
   `observeStream` dan tambah baru (preserve backward-compat kalau ada caller
   lain). Cek dulu usage:
   ```bash
   grep -rn "executeStream" app/ tests/
   ```
2. Edit `MigrationController::stream()` panggil `observeStream` bukan
   `executeStream`.
3. Build:
   ```bash
   docker exec new_siupk-app-1 php artisan optimize:clear
   ```
4. Test Playwright `M.3 Monitor SSE stream sampai status=completed`:
   ```bash
   npx playwright test full-audit-migration-76.spec.ts \
     --grep "M.3 Monitor SSE" --reporter=list
   ```
   Expected: PASS, status berubah ke `completed` dalam ≤60 detik.

## Catatan

- Effort total A1: ~1 jam (4 file, test). Effort A3: ~30 menit (1 method +
  controller patch + retest M.3).
- Prioritas: **A3 dulu** (race condition aktif, bisa data corruption saat
  produksi), baru **A1** (UX consistency).
- Pertimbangkan input percent (`#5`, `#6`): saat ini `type="number"` sudah
  cukup, tapi kalau ada permintaan UX "tampilan 12,50% dengan koma", baru
  migrasi ke `AppCurrencyInput`.
