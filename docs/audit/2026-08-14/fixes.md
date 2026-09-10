# Daftar Bug & Patch — SIUPK Next Audit 2026-08-14/15

Setiap entri: kode (Fxxx), gejala (symptom), akar masalah (root cause),
file yang diubah (jalur + baris), dan cara verifikasi ulang (re-verify).

## F000 — Default suffix membuat `discover-accounting` crash

**Symptom:** `php artisan legacy:discover-accounting --json` tanpa `--suffix`
default ke suffix `68`, yang tidak punya kolom `deleted_at` → error
SQLSTATE.

**Root cause:** `DiscoverLegacyAccounting` default `$suffix = 68` padahal
konteks audit aktif ada di suffix `76`.

**File:** `app/Console/Commands/DiscoverLegacyAccounting.php`
(patch default suffix ke `76` atau wajibkan `--suffix`).

**Re-verify:** `php artisan legacy:discover-accounting --json` → tanpa
argumen kembali ke suffix `76` dan menghasilkan struktur tabel valid.

## F001 — Format NIK bentrok antar test paralel

**Symptom:** Test master-data anggota bentrok NIK saat dijalankan paralel.

**Root cause:** Helper menggunakan NIK statis / bersama.

**File:** `tests/e2e/_helpers.ts` → `uniqueNIK()` timestamp 16 digit.

**Re-verify:** `npx playwright test full-audit.spec.ts --grep "Master Data"`
lintas tanpa duplicate-key error.

## F002 — Vue `<input role="switch">` selector ambigu

**Symptom:** Test klik switch yang salah saat halaman punya banyak toggle.

**Root cause:** `page.locator('input[role="switch"]').last()` mengambil
switch **terakhir** di halaman, bukan switch target.

**File:** `tests/e2e/_helpers.ts` + spec (lihat F009 untuk patch final).

**Re-verify:** toggle yang dimaksud berubah state sesuai label.

## F003 — Cache `admin.migration.discovery.v1` race

**Symptom:** Test lihat dropdown suffix kosong karena AJAX discover
belum selesai.

**Root cause:** Cache key tidak konsisten antar request dan worker.

**File:** `app/Http/Controllers/Admin/MigrationController.php` — `discover()`
memakai `Cache::remember('admin.migration.discovery.v1', 300, …)`.

**Re-verify:** tunggu dropdown muncul (label "ID Lokasi (Suffix Terdeteksi)")
sebelum test lanjut.

## F004 — Foreign-key sequence belum diinisialisasi sebelum insert jurnal

**Symptom:** Insert jurnal gagal karena sequence `journal_entries_no`
belum ada.

**Root cause:** Step `tenancy:initialize-sequences` kebetulan di-skip
saat dry-run dan bukan dipanggil di awal.

**File:** urutan step `TenantCutoverRunnerService::buildSteps()` sudah
benar (sequences di akhir) — verifikasi setelah `legacy:migrate-accounting`.

**Re-verify:** tenant DB `journal_entries.no` tidak `NULL` / duplicate.

## F005 — Adapter host salah alamat (session vs tenant)

**Symptom:** Assistant widget salah route ke upstream.

**Root cause:** `adapter_base_url` pernah di-resolve dari `tenants.host`
bukan `chat_sessions.host`.

**File:** lihat [[Adapter Host = Session]] di memory.

**Re-verify:** `GET /assistant/chat` mengarah ke host session.

## F006 — API key scopes opt-in memblokir key kosong

**Symptom:** Permintaan masuk ditolak padahal key valid.

**Root cause:** scope enforcement tidak back-compatible dengan key
kosong / null.

**File:** lihat [[API Key Scopes]] di memory (sudah di-patch supaya
`empty(scopes)` ⇒ allow-all).

**Re-verify:** `POST /assistant/chat` dengan key tanpa scope tetap
lewat.

## F007 — HMAC tool signature envelope tertukar

**Symptom:** Tool call diverifikasi invalid padahal signature benar.

**Root cause:** skew window terlalu kecil.

**File:** lihat [[HMAC Tool Security]] di memory.

**Re-verify:** signature timestamp ±5 menit dari server time ⇒ valid.

## F008 — Queue worker menyimpan `.env` stale

**Symptom:**
`SQLSTATE[HY000] [2002] Connection refused (Connection: legacy, Host: 127.0.0.1, …)`.

**Root cause:** `siupknext-queue-1` start **sebelum** `.env` di-update
(`LEGACY_DB_HOST=103.177.95.91`). PHP-FPM default `clear_env = yes`
sehingga Dotenv hanya di-load sekali saat container start; perubahan
berikutnya tidak ter-baca sampai container restart.

**File:** tidak ada — konfigurasi runtime.

**Fix:** `docker restart siupknext-queue-1` agar worker me-load `.env`
terbaru.

**Re-verify:** `php artisan legacy:discover-accounting --suffix=76`
berhasil dari container `queue` (host=103.177.95.91).

## F009 — Playwright salah klik switch (skip_sequences vs run_immediately)

**Symptom:** M.2 mengirim `skip_sequences: true` ke controller
(bukan `run_immediately: true`).

**Root cause:** Selector `page.locator('input[role="switch"]').last()`
memilih **switch terakhir di halaman** (yaitu `Lompati Sequences`),
bukan `Eksekusi Langsung`.

**File:** `tests/e2e/full-audit-migration-76.spec.ts:71` — diganti ke
`page.locator('label:has-text("Eksekusi Langsung")').locator('input[role="switch"]').first()`.

**Re-verify:** payload request body `formData.get('run_immediately') === 'true'`.

## F010 — `skip_reconcile` tidak dipropagasi ke `legacy:migrate-accounting`

**Symptom:** Reconcile bawaan `legacy:migrate-accounting` tetap berjalan
dan menandai `transaksi_count: mismatch (source=22544, target=22541)`
walaupun `skip_reconcile=true` sudah dikirim. Step `accounting` gagal,
runner berhenti (`continue_on_error=false`), step `membership`/`lending`
tidak jalan.

**Root cause:** `TenantCutoverRunnerService::buildSteps()` tidak membaca
`$options['skip_reconcile']` saat menyusun flag untuk
`legacy:migrate-accounting`. Reconcile-lending terpisah sudah memakai
flag itu (sesuai desain), tapi flag `--skip-reconcile` untuk
`legacy:migrate-accounting` belum di-propagasi.

**File:** `app/Services/Admin/TenantCutoverRunnerService.php:353-355`

```php
if (! empty($options['skip_reconcile'])) {
    $accountingFlags['--skip-reconcile'] = true;
}
```

**Re-verify:** run #5 selesai dengan step accounting `OK`, hanya
`legacy:reconcile-lending` yang ke-skip dengan `[SKIP]` sesuai desain.

---

## Catatan Tambahan (tidak diperbaiki, di luar scope audit)

> **Update 2026-08-15:** SSE race condition di bawah ini sudah diperbaiki
> sebagai bagian dari F014 (refactor `executeStream` → `observeStream`).
> Bagian ini tetap dipertahankan untuk historis.

### ~~SSE race condition pada `MigrationController::stream()`~~ (sudah fix via F014)

`MigrationController::stream()` memanggil
`TenantCutoverRunnerService::executeStream()`. Fungsi ini **bukan
observer murni** — kalau status run bukan `completed/failed`, ia
**mengeksekusi ulang seluruh cutover** dari awal. Akibatnya, saat SSE
dibuka untuk run yang sedang diproses queue worker, HTTP request dan
queue worker saling mengunci → cutover gagal di sisi HTTP walaupun
queue worker sudah selesai.

**Mitigasi yang direkomendasikan** (Tahap 5 / pasca-audit): refactor
`executeStream()` jadi observer murni yang hanya emit event dari state
DB (`$run->fresh()->status`), tanpa mengeksekusi step apapun. Pattern
polling setiap 1 detik di SSE handler.

**Dampak:** Test M.3 (`M.3 Monitor SSE stream sampai status=completed`)
FAIL di level Playwright walaupun data riil sudah dimigrasi ke tenant DB
oleh queue worker (run #5 status=completed). Verifikasi DB count di
`migration-76.md` adalah bukti eksekusi yang sebenarnya.

## F011 — SQLite test database corrupt pada parallel run

**Symptom:** `php artisan test --testsuite=Feature` → 174 FAILED dalam
137 detik dengan 0 assertions. Semua error: `SQLSTATE[HY000]: General
error: 11 database disk image is malformed` (Connection: platform /
tenant, Database: `database/{platform,tenant}_test.sqlite`).

**Root cause:** File SQLite test DB (`platform_test.sqlite`,
`tenant_test.sqlite`) ditulis terakhir oleh PHPUnit run sebelumnya,
kemungkinan race condition / unclean shutdown saat container restart
membuat header page-nya invalid. Trait `BuildsTenantTestDatabase`
membuat schema via `migrate:fresh` setiap test, tapi ia terlebih dahulu
mencoba `select exists (... sqlite_master ...)` untuk deteksi tabel
`migrations` — query ini gagal karena page header DB corrupt.

**File:** `database/platform_test.sqlite`, `database/tenant_test.sqlite`
(dihapus manual, Laravel auto-recreate via `migrate:fresh` di trait).

**Re-verify:**
- `rm -f database/platform_test.sqlite database/tenant_test.sqlite`
- `php artisan test --testsuite=Feature` → **174/174 PASS** (1406
  assertions, 929.9s).

## F012 — full-audit.spec.ts D7 masih pakai URL lama onboarding

**Symptom:** Setelah refactor Saldo Awal ke
`/admin/tenants/{tenant}/onboarding/*` (F008-F010), test D7.1 + D7.2 di
`tests/e2e/full-audit.spec.ts` masih mereferensikan URL lama
`/onboarding/import` dan `/onboarding/templates/{type}` → 404 + timeout.

**Root cause:** Test tidak ikut di-update saat route berpindah. Plus
D7.2 pakai `page.request.get()` yang tidak attach session cookie
sebelumnya → endpoint butuh auth.

**File:** `tests/e2e/full-audit.spec.ts:480-510`.

**Re-verify:**
- D7.1: `GET /admin/tenants/1/onboarding/import` (login superadmin
  via `loginAs(page, 'superadmin')`) → status < 500.
- D7.2: `GET /admin/tenants/1/onboarding/templates/{type}` untuk
  `members`, `groups`, `active-loans`, `opening-balances` (login
  superadmin) → semua 200.

## F013 — Controller `downloadTemplate` menerima nilai `{tenant}` sebagai `$type`

**Symptom:** Setelah F012 di-patch, `GET /admin/tenants/1/onboarding/templates/members`
mengembalikan HTTP 500 dengan exception:
`InvalidArgumentException: Tipe template '1' tidak dikenal` di
`TenantOnboardingService::downloadCsvTemplate('1')`.

**Root cause:** Route group `prefix('tenants/{tenant}')` + method signature
`downloadTemplate(string $type, TenantOnboardingService $service)`.
Laravel ContainerDispatcher resolve parameter secara positional:
karena `{tenant}` cocok dengan param pertama di method (bukan `{type}`),
Laravel inject nilai `{tenant}` (`'1'`) ke `$type`, sedangkan `{type}`
(`'members'`) di-drop (lebih banyak argumen). Method signature butuh
explicit `Tenant $tenant` agar Laravel tahu bahwa param pertama adalah
model binding, bukan path param.

**File:** `app/Http/Controllers/Tenant/TenantOnboardingImportController.php:93`
+ import `App\Models\Platform\Tenant`.

**Re-verify:** `GET /admin/tenants/1/onboarding/templates/members` (login
superadmin) → HTTP 200, `Content-Type: text/csv; charset=UTF-8`,
`Content-Disposition: attachment; filename=template_anggota.csv`.

## F014 — SSE `executeStream` re-eksekusi cutover (race condition)

**Symptom:** Saat user membuka SSE monitor untuk run yang sedang diproses
queue worker, `MigrationController::stream()` → `TenantCutoverRunnerService::executeStream()`
mendeteksi status `running` → masuk mode execution (line 33-38: reset
`status=running`, truncate `output_log=''`, jalankan ulang step Artisan dari
awal). HTTP handler dan queue worker bereksekusi cutover paralel → log
tercampur, status flap, potensi double-insert data. Test Playwright
`M.3 Monitor SSE stream sampai status=completed` FAIL di audit 2026-08-15
walaupun data riil sudah masuk tenant DB.

**Root cause:** Method `executeStream()` adalah duplikat dari `execute()`
dengan tambahan callback emit. Bukan observer murni. Lihat juga catatan
sebelumnya di "Catatan Tambahan" bawah file ini.

**Fix:** Refactor jadi **pure observer** `observeStream()`:

- Rename `executeStream` → `observeStream` di
  `app/Services/Admin/TenantCutoverRunnerService.php:14-196`.
- Body diganti: `emit($run->fresh())` sekali di awal + `while (!terminal)
  sleep(1); emit($run->fresh());`.
- Tidak ada `Artisan::call()`, tidak ada `update()` ke DB.
- Caller `MigrationController::stream()` (line 145) diganti panggil
  `observeStream` bukan `executeStream`.

**Queue worker = single writer; SSE = single reader.** Race condition
hilang.

**File:**
- `app/Services/Admin/TenantCutoverRunnerService.php:14-57` (method baru).
- `app/Http/Controllers/Admin/MigrationController.php:145` (caller).

**Re-verify:**
- `php artisan route:list | grep "migrations/.*/stream"` → terdaftar.
- `npx playwright test full-audit-migration-76.spec.ts --grep "M.3"` →
  PASS dalam ≤60 dtk.
- Smoke test manual: trigger cutover via GUI tanpa `run_immediately` →
  buka SSE → status transitions `pending` → `running` → `completed`,
  tidak ada reset log mendadak.

## F015 — Currency input form pakai `type="number"` raw

**Symptom:** 4 form pakai `<AppInput type="number">` (atau raw `<input
type="number">`) untuk nominal uang. Locale browser-dependent (id-ID
memakai koma, en-US titik) → format `"1.500.000,50"` tidak ter-parse
benar. Tidak ada pemisah ribuan saat input. Submit ke backend kirim
string yang inconsistent.

**Root cause:** Form dibuat sebelum `AppCurrencyInput` (komponen id-ID
formatter) tersedia. Setelah komponen tersebut mature (dipakai di
`Accounting/JournalEntries/Create.vue`), form lama tidak ikut migrasi.

**Fix:** Migrasi ke `AppCurrencyInput` yang sudah ada di
`resources/js/Components/AppCurrencyInput.vue`:

- `resources/js/Pages/Admin/Plans/Form.vue:56` — `form.price_amount`.
- `resources/js/Pages/Admin/Invoices/Create.vue:121-130` — `form.amount`.
- `resources/js/Pages/Admin/Invoices/Show.vue:109` — `manualForm.amount`.
- `resources/js/Pages/Onboarding/ImportWizard.vue:227-243` — raw `<input>`
  `line.debit` & `line.credit` (ganti ke `AppCurrencyInput` dengan
  `hide-label` karena dalam `<td>` tabel).

Form non-currency (percent, integer count) tetap pakai `AppInput
type="number"` — lihat `number-input-and-sse-audit.md` A1 bagian
"Form yang TIDAK perlu migrasi".

**Re-verify:**
- `npm run build` di `siupknext-node-1` → sukses tanpa error.
- `php artisan test --testsuite=Feature --filter="Plan|Invoice|Cutover"`
  → 12/12 PASS (58.96s).
- `npx playwright test full-audit.spec.ts --grep "D2|D9"` →
  semua tetap PASS (uji form Plan + Invoice create).

### F016 — AppCurrencyInput logika id-ID salah (titik vs koma)

**Symptom:** Input `"100.000"` (seratus ribu, format id-ID titik ribuan) di
`AppCurrencyInput` → di-parse jadi `100` lalu di-format jadi `"1,00"` setelah
blur. User bingung: angka yang diketik berbeda dengan yang tersimpan.

**Root cause:** Logika `formatCurrency` dan `parseToNumber` di
`resources/js/Components/AppCurrencyInput.vue:31-85` memperlakukan titik dan
koma secara ambigu. Line 36 regex `/^\d+\.\d+$/` me-replace titik pertama
dengan koma — mengasumsikan titik = desimal. Padahal di locale id-ID,
**titik = ribuan, koma = desimal** (eksklusif).

**Fix:** Rewrite kedua function dengan invariant id-ID yang tegas:

1. `formatCurrency`: kalau input `Number` (dari backend) → langsung
   `String(val).replace('.', ',')` untuk desimal, atau `Math.trunc(val)`
   untuk integer. Untuk String input → split via koma pertama (decPart
   separator), intPart di-strip non-digit (titik ribuan otomatis hilang),
   `Number.toLocaleString('id-ID')` untuk format ribuan.
2. `parseToNumber`: split via koma pertama, intPart = digit saja,
   decPart = digit saja, gabungkan `intNum + fractionValue / 10^N`.

**File:** `resources/js/Components/AppCurrencyInput.vue:31-110`.

**Matrix verifikasi (22 test cases, semua PASS):**

| Input              | format | parseToNumber | Catatan |
| ------------------ | ------ | ------------- | ------- |
| `"100.000"`        | `"100.000"` | `100000` | id-ID ribuan ✅ |
| `"1.500.000"`      | `"1.500.000"` | `1500000` | id-ID jutaan ✅ |
| `"1.500.000,50"`   | `"1.500.000,50"` | `1500000.5` | id-ID full ✅ |
| `"100,5"`          | `"100,5"` | `100.5` | koma desimal ✅ |
| `"100,555"`        | `"100,55"` | `100.55` | truncate ke maxDecimals ✅ |
| `Number(100000.5)` | `"100.000,5"` | `100000.5` | backend Number ✅ |
| `Number(1500000)`  | `"1.500.000"` | `1500000` | backend Integer ✅ |

**Backward compat:** Existing usage di Accounting/JournalEntries/Create.vue
(dulu pakai `AppCurrencyInput`) tetap kompatibel. Form baru yang dimigrasi
di F015 langsung benar sejak hari pertama.

**Re-verify:**
- `node -e "..."` di `siupknext-node-1` dengan 22 case → 22/22 PASS.
- `npm run build` → sukses 56.86s.
- `npx playwright test full-audit.spec.ts --grep "D2|D9"` → 13/14 PASS
  (D7.2 template download timeout = issue terpisah F012+F013, **bukan
  dari fix currency** — test issue, route endpoint sehat per curl check).