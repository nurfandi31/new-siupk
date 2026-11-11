# AUDIT DETAIL — SIUPK Next (`C:\laragon\www\siupknext`)

**Tanggal audit:** 11 November 2026
**Target:** `C:\laragon\www\siupknext` (working tree, termasuk perubahan yang belum di-commit)
**Pembanding:** `C:\laragon\www\siupk` (aplikasi legacy SIUPK)
**Sifat audit:** Read-only (tidak ada kode aplikasi yang diubah)
**Metode:** Pembacaan kode menyeluruh, penelusuran route, eksekusi test suite penuh, verifikasi manual setiap temuan berisiko tinggi, dan perbandingan fitur dengan legacy.

> Catatan: repo sedang memiliki banyak perubahan belum di-commit (`.env.example`, `routes/web.php`, `LoanService.php`, halaman Vue, dll). Semua temuan di bawah mengacu pada kondisi **working tree saat audit**.

---

## 1. Ringkasan Eksekutif

Aplikasi SIUPK Next sudah jauh lebih modern dan terstruktur dibanding legacy: Laravel 13, Inertia 2 + Vue 3, domain-driven folder, 648 route, 114 file test, dan test suite **hijau** (642 passed, 13 skipped, 9.508 assertion). Cakupan fitur juga sudah sangat luas (lending kelompok & individu, akuntansi, aset, budgeting, regulatory, website, portal anggota, mobile/desktop API, billing, savings).

Namun audit detail menemukan **masalah keamanan dan integritas data yang serius** yang tidak tertangkap test suite karena test banyak menguji service secara langsung, bukan lewat route/UI.

### Skor ringkas per area

| Area | Skor | Catatan utama |
|---|---:|---|
| Master Data & Membership | 82 | CRUD lengkap, validasi baik; PII di public disk |
| Lending (kelompok & individu) | 55 | Alur lengkap, tetapi tabel pembayaran tidak konsisten → laporan keuangan salah |
| Savings (modul baru) | 45 | Fitur banyak, **0 test**, tidak terhubung ke jurnal akuntansi, tidak ikut sync desktop |
| Accounting & Reporting | 50 | Banyak laporan bagus, tetapi CALK error dan 9 export Excel mati |
| Auth / RBAC / Tenancy | 40 | Beberapa jalur eskalasi hak akses dan kebocoran lintas tenant |
| API Mobile / Desktop / Holding | 35 | Fail-open dan bypass scope tenant |
| Frontend / UX | 60 | UI rapi, tetapi 10 fitur kena 419 CSRF dan Portal crash |
| Paritas dengan legacy SIUPK | 70 | Mayoritas fitur ada; beberapa modul legacy belum ada |
| Testing | 65 | 642 test hijau, tetapi blind spot besar (Savings, route, UI props) |
| **Keseluruhan** | **≈ 57/100** | **Belum layak produksi tanpa perbaikan P0** |

### Statistik aplikasi

| Metrik | Nilai |
|---|---:|
| File PHP di `app/`, `config/`, `routes/`, `database/` | 613 |
| LOC PHP di `app/` | ± 77.604 |
| Halaman Vue | 169 |
| File test | 114 |
| Migration | 70 |
| Route terdaftar | 648 (643 bernama) |
| **Route rusak (method/class tidak ada)** | **18** |
| TODO/FIXME/HACK di `app/` | 1 |

### Hasil test suite (log: `storage/logs/audit-test-run.txt`)

```
Tests: 13 skipped, 642 passed (9508 assertions)
Duration: 715.88s
```

Test suite **hijau**, tetapi hijau ≠ bebas bug. Contoh nyata:
- `tests/Feature/Portal/PortalMemberTest.php` memakai nama prop `loan_summary` / `active_groups` (snake_case), sedangkan `Portal/Index.vue` membaca `loanSummary` / `activeGroups` (camelCase) → test lolos, halaman crash.
- `tests/Feature/Accounting/Reports/ExcelExportTest.php` hanya menguji `XlsxWriter`, bukan route export → 9 route export Excel rusak tetap lolos.
- Tidak ada satu pun test untuk modul `app/Domain/Savings/**`.

---

## 2. Temuan KRITIS (P0)

### K-01 — Holding API fail-open total bila `HOLDING_API_KEY` kosong
**Lokasi:** `app/Http/Middleware/VerifyHoldingApiToken.php:22-27`
```php
$configuredKey = (string) config('services.holding.api_key', '');
if ($configuredKey === '') {
    return $next($request);   // <-- semua request lolos, termasuk production
}
```
**Dampak:** Seluruh endpoint `/api/v1/holding/**` (laporan konsolidasi, daftar tenant, neraca, laba rugi, CALK, cash flow) dapat diakses **tanpa autentikasi** jika env tidak diisi. Tidak ada guard `app()->isProduction()` seperti pada Desktop API.
**Bukti:** Tidak ada pengecekan environment sebelum `return $next($request)`.
**Rekomendasi:** Tolak semua request (503/401) bila key kosong di production; fail-closed secara default.

---

### K-02 — Desktop Sync API: user tenant biasa bisa membaca & mem-push seluruh data tenant
**Lokasi:** `app/Http/Middleware/VerifyDesktopApiToken.php:27-48`, `routes/api.php:96-113`
```php
if ($providedKey !== '' && hash_equals($configuredKey, $providedKey)) return $next($request);

$user = $request->user();
if ($user !== null) {
    return $next($request);   // <-- siapa pun yang login, tanpa cek role/permission
}
```
**Dampak:**
1. Di non-production, key kosong = semua request lolos.
2. Di production dengan key terisi, **setiap user yang punya session login** (termasuk kasir/petugas desa) dapat memanggil `/api/v1/desktop/sync/snapshot`, `/delta`, dan `/push`.
3. `push` menerima mutasi untuk tabel `user_roles`, `roles`, `loans`, `journal_entries`, dll (`DesktopPushApplyService::DESKTOP_TABLES`) **tanpa permission check dan tanpa CSRF** (API group tidak memakai CSRF). Ini jalur eskalasi hak akses langsung: user biasa dapat mengangkat dirinya menjadi admin atau mengubah jurnal/pinjaman.
**Bukti:** `DesktopPushApplyService::applyWithPolicy()` hanya memvalidasi `tenant_id` + `sync_mutations`, tidak memanggil `PermissionChecker`.
**Rekomendasi:** Wajibkan API key untuk semua request desktop (kecuali di test), dan untuk fallback session user wajibkan role `superadmin`/`admin` + audit. Pertimbangkan memisahkan endpoint read-only vs write-only.

---

### K-03 — Reset password & hapus user tanpa permission check (takeover akun admin)
**Lokasi:** `routes/web.php:963-981`, `app/Http/Controllers/Access/TenantUserManagementController.php:223-241`
```php
public function resetPassword(Request $request, User $user): RedirectResponse
{
    $this->assertBelongs($user);   // hanya cek user milik tenant
    ...
    $user->forceFill(['password' => Hash::make($data['password'])])->save();
}
public function destroy(Request $request, User $user): RedirectResponse
{
    $this->assertBelongs($user);
    ...
}
```
Grup route `access/*` hanya memakai middleware `['auth','tenant','subscription.active']`. `StoreTenantUserRequest`/`UpdateTenantUserRequest` memang dipetakan ke `users.manage`, tetapi **`resetPassword` dan `destroy` memakai `Request` biasa sehingga tidak ada pengecekan `users.manage`**. `index` dan `edit` juga tidak mengecek `users.view`.
**Dampak:** User tenant mana pun (mis. kasir, petugas desa, bahkan anggota yang punya akun) dapat mereset password administrator tenant dan mengambil alih akun, atau menghapus user lain.
**Catatan tambahan:** `PermissionChecker` fail-open untuk user tanpa role (`PermissionChecker.php:40-43`), sehingga menghapus role user = memberi akses penuh.
**Rekomendasi:** Tambahkan `$this->permissions->denyUnless($request->user(), 'users.manage')` di `resetPassword`/`destroy`, dan `users.view` di `index`/`edit`; ubah default `PermissionChecker` menjadi fail-closed.

---

### K-04 — Pembayaran angsuran dari web tidak menulis `loan_payments` / `loan_payment_allocations` → laporan keuangan salah
**Lokasi:** `app/Domain/Lending/Services/LoanService.php:1112-1258`
Alur `recordInstallmentPayment()` hanya:
1. membuat `journal_entries` + `journal_lines` (`source_type = loan_installment`),
2. meng-update `loan_installments`.

Tidak ada insert ke `loan_payments` maupun `loan_payment_allocations`. Yang menulis `loan_payments` hanya:
- `SeedSampleLoans` (seeder),
- `LegacyLoanLoader` (migrasi data lama),
- `DesktopPushApplyService::applyMobileLoanPayment()` (pembayaran via mobile sync).

Sementara banyak laporan dan dashboard membaca `loan_payments`:
| File | Baris | Dampak |
|---|---|---|
| `app/Domain/Dashboard/Services/DashboardService.php` | 515-522 | grafik "collected" selalu 0 untuk pembayaran web |
| `app/Domain/Lending/Services/Reports/ActiveGroupsReportService.php` | 91-113 | outstanding kelompok = principal penuh selamanya |
| `ActiveMembersReportService.php` | 100 | idem |
| `CollectibilityReportService.php` | 118, 378, 627, 869 | kolektabilitas salah |
| `LppReportService.php` | 122, 414, 725 | LPP salah |
| `NearSettlementReportService.php` | 141 | salah |
| `PaidLoansReportService.php` | 117 | pinjaman lunas tidak terdeteksi |
| `WriteOffsReportService.php` / `WriteOffsIndividuReportService.php` | 129/130 | salah |
| `app/Domain/Regulatory/Services/AllowanceService.php` | 245 | cadangan piutang salah |
| `CollectibilityOjkService.php` / `CollectibilityOjkV2Service.php` | 241/287 | OJK salah |
| `PaidGroupLoansService.php` / `PaidIndividuLoansService.php` | 83/84 | salah |
| `app/Domain/Accounting/Services/Reports/RegencyConsolidatedReportService.php` | 97 | konsolidasi kabupaten salah |

**Bukti pendukung:** `DesktopPushApplyService::applyMobileLoanPayment()` (baris 473-497) memanggil `recordInstallmentPayment()` lalu membuat `LoanPayment`, tetapi **tidak membuat `LoanPaymentAllocation`**. Jadi bahkan pembayaran mobile pun tidak memperbaiki laporan berbasis alokasi.
**Dampak:** Data keuangan tidak konsisten antar-laporan; kolektibilitas, OJK, LPP, dan dashboard bisa menampilkan angka yang sangat berbeda dari jurnal.
**Rekomendasi:** Jadikan `recordInstallmentPayment()` sebagai satu-satunya sumber kebenaran: tulis `loan_payments` + `loan_payment_allocations` (principal/interest/penalty) di dalam transaksi yang sama, atau ubah semua laporan untuk membaca `loan_installments`/jurnal. Tambahkan test integrasi yang membandingkan total jurnal vs total laporan.

---

### K-05 — `CalkService::saveNotes()` dipanggil dengan 3 argumen → error 500
**Lokasi:** `app/Http/Controllers/Accounting/ReportController.php:285-294`, `app/Domain/Accounting/Services/Reports/CalkService.php:93-96`
```php
// Controller
$this->calk->saveNotes((int) $validated['year'], $month, [ ... ]);   // 3 argumen

// Service
public function saveNotes(string $notes): void                            // 1 argumen
```
**Dampak:** Menyimpan catatan CALK selalu gagal (`ArgumentCountError`/`TypeError`) → halaman CALK tidak bisa dipakai. Tidak ada test yang menutupi route ini.
**Rekomendasi:** Samakan signature (mis. `saveNotes(int $year, ?int $month, array $notes)`) dan simpan per periode.

---

### K-06 — Foto KTP & tanda tangan disimpan di disk publik (PII terbuka tanpa login)
**Lokasi:**
- `app/Http/Controllers/MasterData/MemberController.php:164-168` → `identity-photos/{person_id}/ktp.{ext}` di disk `public`
- `app/Domain/Documents/Services/SignatureImageService.php:16, 86-87` → `tenants/{id}/signatures/{report}.png` di disk `public`
- `public/storage` sudah ter-link (`Test-Path public\storage` = true)

**Dampak:** Siapa pun yang mengetahui/ menebak URL `https://domain/storage/identity-photos/{id}/ktp.jpg` dapat mengunduh KTP, tanda tangan pejabat, dan logo tenant tanpa autentikasi. ID person bersifat berurutan sehingga mudah dienumerasi.
**Rekomendasi:** Pindahkan ke disk privat (`local`/`s3` private) dan sajikan lewat route ber-autentikasi + otorisasi (contoh `StorageServeController` yang sudah ada), atau gunakan signed URL berumur pendek. Tambahkan `X-Robots-Tag: noindex`.

---

### K-07 — API Mobile: kebocoran data lintas tenant karena `orWhere` tanpa grouping
**Lokasi:**
- `app/Http/Controllers/Api/Mobile/MobileVerificationController.php:122-124` dan `225`
- `app/Http/Controllers/Api/Mobile/MobileExecutiveController.php:200-202`, `254-256`, `312-314`

```php
Loan::query()
    ->where('row_id', $loanId)
    ->orWhere('id', $loanId)
    ->first();
```
`TenantScope` hanya menambahkan `where tenant_id = X` di level atas (`app/Tenancy/TenantScope.php:19-22`). Tanpa grouping, SQL menjadi:

```sql
WHERE tenant_id = X AND row_id = ? OR id = ?
```

Karena `id` (ID legacy) berulang di setiap tenant (1, 2, 3, ...), **user tenant A bisa membaca pinjaman tenant B hanya dengan menebak angka `id`**.
**Dampak:** Kebocoran data pinjaman, nama anggota/kelompok, nominal, dan status lintas tenant. Route mobile hanya memakai `auth:sanctum` + `tenant`, tanpa permission (`routes/api.php:120-157`).
**Rekomendasi:** Bungkus dengan closure: `->where(fn ($q) => $q->where('row_id',$id)->orWhere('id',$id))`, atau lebih baik gunakan route model binding (`Loan $loan`) yang otomatis terkena global scope. Audit semua `orWhere` serupa.

---

### K-08 — API Mobile: approval/verifikasi pinjaman tanpa permission
**Lokasi:** `routes/api.php:143-156`, `app/Http/Controllers/Api/Mobile/MobileExecutiveController.php`, `MobileVerificationController.php`
Semua endpoint `verification/*` dan `executive/*` hanya memakai `auth:sanctum` + `tenant`. Tidak ada `PermissionChecker`/`denyUnless` di controller.
**Dampak:** Setiap user tenant yang bisa login mobile (termasuk kasir/petugas) dapat menyetujui (`approve`), menolak (`reject`), dan memverifikasi pinjaman.
**Rekomendasi:** Tambahkan permission `loans.verify` / `loans.approve` / `loans.view` pada route atau controller; batasi token mobile berdasarkan role.

---

### K-09 — Quick Login aktif secara default di luar production (login tanpa password + direktori user)
**Lokasi:** `config/quick_login.php:32-35`
```php
'enabled' => env('QUICK_LOGIN_ENABLED', env('APP_ENV', 'production') !== 'production'),
```
**Dampak:** Pada deployment dengan `APP_ENV=local/staging` (yang sering dipakai di server demo), halaman `/user`, `/login/user`, dan `/t/{tenant}/user` menampilkan daftar user dan **login tanpa password hanya dengan klik**. Ini adalah pintu masuk penuh ke aplikasi.
**Rekomendasi:** Default `false`; aktifkan hanya lewat env eksplisit. Tambahkan guard tambahan (IP allowlist / hanya di CLI).

---

## 3. Temuan TINGGI (P1)

### T-01 — CSRF token tidak pernah dirender, 10 fitur frontend selalu 419
**Lokasi:** `resources/views/app.blade.php` (tidak ada `<meta name="csrf-token">`), dan 10 file Vue yang memakai `document.querySelector('meta[name="csrf-token"]')`:
1. `resources/js/Components/AssistantWidget.vue`
2. `resources/js/Components/NotificationDropdown.vue`
3. `resources/js/Pages/Admin/AiAssistant/Index.vue`
4. `resources/js/Pages/Admin/PaymentGateways/Index.vue`
5. `resources/js/Pages/Admin/PlatformWhatsapp/Index.vue`
6. `resources/js/Pages/Admin/Tenants/Users/Index.vue`
7. `resources/js/Pages/Admin/Tenants/Index.vue`
8. `resources/js/Pages/Admin/Tenants/Show.vue`
9. `resources/js/Pages/Settings/Whatsapp/Hub.vue`
10. `resources/js/Pages/Settings/Index.vue`

Contoh: `app/Http/Controllers/...` tidak ada; `Settings/Index.vue:205-214` memanggil `fetch('/settings/lending-system/sync-rounding', { headers: { 'X-CSRF-TOKEN': '' } })`. CSRF middleware aktif (`bootstrap/app.php:47-50`), `axios` tidak dipakai di jalur ini, dan browser tidak mengirim `X-XSRF-TOKEN` untuk `fetch` manual.
**Dampak:** Sinkronisasi pembulatan, impersonate tenant, pengaturan payment gateway, WhatsApp pairing, AI assistant, notifikasi, dan reset password dari UI akan gagal 419.
**Rekomendasi:** Tambahkan `<meta name="csrf-token" content="{{ csrf_token() }}">` di `app.blade.php` (atau gunakan `X-XSRF-TOKEN` dari cookie), lalu tambahkan test browser/feature.

---

### T-02 — 18 route rusak (method/class tidak ada)
Diverifikasi dengan skrip resolusi route:

| # | Route | Method | Aksi | Masalah |
|---|---|---|---|---|
| 1 | `billing/invoices/{invoice}/checkout/tripay` | POST | `InvoiceController@checkoutTripay` | method tidak ada |
| 2 | `master-data/groups/lookup` | GET | `GroupController@lookup` | method tidak ada |
| 3 | `lending/proposals/{proposal}/edit` | GET | `LoanController@edit` | method tidak ada |
| 4 | `lending/loans/{loan}/edit` | GET | `LoanController@edit` | method tidak ada |
| 5 | `accounting/accounts/create` | GET | `ChartOfAccountsController@create` | method tidak ada |
| 6 | `accounting/accounts` | POST | `ChartOfAccountsController@store` | method tidak ada |
| 7 | `accounting/accounts/{account}/edit` | GET | `ChartOfAccountsController@edit` | method tidak ada |
| 8 | `accounting/accounts/{account}` | PUT | `ChartOfAccountsController@update` | method tidak ada |
| 9 | `accounting/reports/journals/excel` | GET | `ReportController@journalsExcel` | method tidak ada |
| 10 | `accounting/reports/trial-balance/excel` | GET | `ReportController@trialBalanceExcel` | method tidak ada |
| 11 | `accounting/reports/balance-sheet/excel` | GET | `ReportController@balanceSheetExcel` | method tidak ada |
| 12 | `accounting/reports/income-statement/excel` | GET | `ReportController@incomeStatementExcel` | method tidak ada |
| 13 | `accounting/reports/cash-flow/excel` | GET | `ReportController@cashFlowExcel` | method tidak ada |
| 14 | `accounting/reports/equity-change/excel` | GET | `ReportController@equityChangeExcel` | method tidak ada |
| 15 | `accounting/reports/general-ledger/excel` | GET | `ReportController@generalLedgerExcel` | method tidak ada |
| 16 | `accounting/reports/assets/fixed/excel` | GET | `ReportController@fixedAssetsExcel` | method tidak ada |
| 17 | `accounting/reports/assets/intangible/excel` | GET | `ReportController@intangibleAssetsExcel` | method tidak ada |
| 18 | `settings/whatsapp/pair` | POST | `SettingsController@pairWhatsapp` | method tidak ada |

**Dampak:** Menu/halaman yang memanggil route ini akan 500 (`BadMethodCallException`) atau 404. Yang paling terasa: seluruh export Excel akuntansi, CRUD COA, dan tombol edit pinjaman.
**Catatan:** `ChartOfAccounts` hanya punya `index` (read-only) — berarti fitur tambah/ubah COA memang belum diimplementasikan, bukan sekadar salah nama.
**Rekomendasi:** Implementasikan atau hapus route + tombol UI. Tambahkan test yang memanggil route (bukan hanya service) agar regresi terdeteksi.

---

### T-03 — Halaman Portal Anggota crash karena nama prop tidak cocok
**Lokasi:** `app/Http/Controllers/Portal/PortalMemberController.php:51-58` mengirim `loan_summary` dan `active_groups`; `resources/js/Pages/Portal/Index.vue:16-19` mendeklarasikan `loanSummary` dan `activeGroups` (wajib).
**Dampak:** Vue menerima `undefined` untuk prop wajib; `props.loanSummary.total` (baris 32) melempar `TypeError` → halaman portal anggota blank/error.
**Bukti bahwa test tidak menangkap:** `tests/Feature/Portal/PortalMemberTest.php:83-84, 199` justru meng-assert nama snake_case, sehingga test hijau.
**Rekomendasi:** Samakan nama (camelCase di controller atau snake_case di Vue), tambahkan assertion pada props yang benar, dan smoke test render Vue.

---

### T-04 — Modul Savings tidak ikut Desktop Sync
**Lokasi:** `app/Domain/Sync/Services/TenantSnapshotService.php:21-67` (whitelist tabel tidak memuat `simp_*`), `app/Domain/Sync/Services/DesktopPushApplyService.php:40` (`DESKTOP_TABLES = TenantSnapshotService::TABLES_IN_ORDER`).
Model savings juga tidak mengimplementasikan penanda pengecualian sync.
**Dampak:** Snapshot/delta ke desktop tidak berisi produk, rekening, mutasi, atau run bunga simpanan. Desktop akan menampilkan data savings kosong; push dari desktop tidak bisa mengubah savings. Jika savings dipakai, data desktop dan server akan berbeda.
**Rekomendasi:** Tambahkan tabel savings dinamis per tenant ke mekanisme sync (atau dokumentasikan bahwa savings online-only dan sembunyikan di desktop).

---

### T-05 — Modul Savings tidak terhubung ke jurnal akuntansi
**Lokasi:** `app/Domain/Savings/Services/SavingsService.php` — tidak ada referensi `JournalEntry`/`journal_entries` sama sekali (diverifikasi dengan grep).
Setoran, penarikan, bunga, pajak, dan biaya admin hanya membuat baris di `simp_transactions_t{id}`.
**Dampak:** Saldo simpanan tidak tercermin di neraca/GL; laporan `savings_receivables`, laba rugi, dan CALK tidak akan rekonsiliasi dengan mutasi simpanan. Beban bunga/pajak tidak pernah masuk jurnal.
**Rekomendasi:** Buat posting jurnal otomatis (Kas/Bank ↔ Simpanan; Beban Bunga ↔ Utang Bunga; Utang Pajak; Pendapatan Admin) dengan COA yang dapat dikonfigurasi per produk. Jika belum siap, beri label jelas "modul belum terintegrasi akuntansi".

---

### T-06 — Modul Savings tidak memiliki test sama sekali
**Lokasi:** `tests/**` — tidak ada file/kelas yang menyebut `Savings`.
Padahal modul ini punya aturan bisnis kompleks: saldo minimum, backdate, bunga (`interestPeriod`), pajak, biaya admin, tutup rekening, dan tabel dinamis per tenant.
**Dampak:** Regresi pada modul keuangan baru tidak akan terdeteksi. Sudah ditemukan beberapa kelemahan yang tidak tertangkap (lihat M-01, M-02, T-05).
**Rekomendasi:** Tambahkan minimal: unit test perhitungan bunga (termasuk `interest_day` negatif/0), feature test CRUD rekening + transaksi + tutup rekening, dan test isolasi tenant (`simp_*_t1` vs `t2`).

---

### T-07 — PermissionChecker fail-open untuk user tanpa role
**Lokasi:** `app/Domain/Access/Services/PermissionChecker.php:40-43, 105-107`
```php
$effective = $this->effectivePermissions($user);
if ($effective === null) {
    return true; // unrestricted (no roles assigned)
}
```
**Dampak:** Setiap akun baru yang belum diberi role otomatis punya **semua permission**. Ini bertentangan dengan prinsip least privilege dan memperbesar dampak K-03/T-08. Komentar kode menyebut ini "legacy full access", tetapi untuk aplikasi multi-tenant modern ini berbahaya.
**Rekomendasi:** Fail-closed: user tanpa role hanya boleh akses `portal.self`/dashboard dasar. Sediakan migrasi untuk memberi role default saat provisioning.

---

### T-08 — Beberapa aksi mutasi pinjaman tidak dicek permission
Sebagian besar aksi memakai `FormRequest` + trait `AuthorizesPermission` dan `config/permissions.php:334-380`. Namun aksi berikut memakai `Request`/route-model-binding langsung tanpa permission:
| Aksi | Lokasi | Permission yang seharusnya |
|---|---|---|
| `revert()` | `LoanController.php:574` | `loans.approve`/`loans.manage` |
| `individualRevert()` | `LoanController.php:1442` | idem |
| `setCommittee()` | `LoanController.php:695` | `loans.manage` |
| `removeBeneficiary()` | `LoanController.php:426` | `loans.manage` |
| `index()`, `show()` | `LoanController.php:46, 441` | `loans.view` |

**Dampak:** User tanpa izin dapat membatalkan approval, mengubah komite, menghapus pemanfaat, dan melihat daftar/detail pinjaman.
**Rekomendasi:** Tambahkan `PermissionChecker::denyUnless` atau ubah ke FormRequest dengan mapping.

---

### T-09 — XSS tersimpan pada konten website publik (`v-html` tanpa sanitasi)
**Lokasi:** `resources/js/Pages/PublicSite/BlogPost.vue:58-61`, `resources/js/Pages/PublicSite/StaticPage.vue:38-41` (`v-html="post.content"` / `v-html="page.content"`). Tidak ditemukan sanitizer HTML di alur `Website` (satu-satunya sanitizer ada di `SignatureTemplateService`).
**Dampak:** Admin tenant yang menyisipkan `<script>`/`onerror` di artikel/halaman akan mengeksekusi JavaScript di browser pengunjung publik (stored XSS). Bisa dipakai mencuri session pengunjung yang juga login, atau deface.
**Rekomendasi:** Sanitasi HTML di server (mis. HTMLPurifier/`mews/purifier`) dengan allowlist tag, atau render sebagai Markdown dan escape. Jangan hanya mengandalkan `v-html` mentah.

---

### T-10 — Filter status pada API koleksi mobile bisa dilewati (`orWhere` tidak digroup)
**Lokasi:** `app/Http/Controllers/Api/Mobile/MobileCollectionController.php:44-48`
```php
$query->whereHas('borrower.group', fn ($q) => $q->where('organization_unit_row_id', $user->village_row_id))
      ->orWhereHas('borrower.member', fn ($q) => $q->where('organization_unit_row_id', $user->village_row_id));
```
Query sebelumnya sudah `whereIn('status', ['active','disbursed'])`. Tanpa closure, SQL menjadi `status IN (...) AND group=village OR member=village` → cabang OR mengabaikan filter status.
**Dampak:** Petugas desa dapat melihat pinjaman berstatus `completed`/`written_off`/`rescheduled` (dan status lain) yang tidak seharusnya muncul di daftar koleksi.
**Bukti inkonsistensi:** Filter `village_id` tepat di bawahnya (baris 50-55) sudah dibungkus closure dengan benar.
**Rekomendasi:** Bungkus kedua `whereHas` dalam satu closure.

---

### T-11 — `trustProxies(at: '*')` + `TENANCY_ALLOW_HEADER=true` di `.env.example`
**Lokasi:** `bootstrap/app.php:39`, `.env.example:66`
**Dampak:**
- `trustProxies('*')` membuat aplikasi memercayai header `X-Forwarded-*` dari siapa pun. Jika server tidak berada di belakang reverse proxy tepercaya, penyerang dapat memalsukan IP, host, dan skema (berdampak pada rate limit, URL generation, dan log audit).
- `TENANCY_ALLOW_HEADER=true` mengizinkan penentuan tenant lewat header `X-Tenant-Code`. Resolver masih memvalidasi keanggotaan user (`TenantResolver::validateTenantAccess`), jadi bukan kebocoran langsung, tetapi menambah permukaan serangan dan membingungkan audit.
**Rekomendasi:** Batasi `trustProxies` ke IP proxy nyata; default `TENANCY_ALLOW_HEADER=false`.

---

## 4. Temuan SEDANG (P2)

### M-01 — Setelan Savings tidak semuanya dipakai
`SavingsModuleManager` menyimpan `default_product_code`, `account_number_prefix`, `minimum_withdrawal_balance`, tetapi implementasi di `SavingsService` hanya memakai sebagian. `postInterest(..., ?string $postingDate = null, ...)` (baris 823-829) menerima `$postingDate` yang tidak pernah dipakai (selalu memakai `$period['posting']`).
**Dampak:** Admin mengubah setelan tetapi perilaku tidak berubah → kebingungan operasional.
**Rekomendasi:** Implementasikan atau hapus dari UI/skema setelan.

### M-02 — Pembayaran mobile membuat `loan_payments` tanpa `loan_payment_allocations`
`DesktopPushApplyService.php:484-497` membuat `LoanPayment` tetapi tidak membuat alokasi. Laporan yang menjumlahkan alokasi (mis. outstanding kelompok) tetap salah meski pembayaran mobile tercatat.
**Rekomendasi:** Tulis alokasi principal/interest/penalty pada saat yang sama.

### M-03 — Jaminan (collateral) hanya untuk pinjaman individu
Legacy memiliki `perguliran_i/{id}/jaminan` dan `form-jaminan`; di Next collateral hanya tersimpan sebagai JSON di `loans.collateral` untuk member loan (`MemberLoanRequest.php:45-51`). Tidak ada modul jaminan untuk pinjaman kelompok, tidak ada register agunan, dan tidak ada dokumen pengembalian jaminan berbasis data.
**Dampak:** Paritas fitur legacy belum tercapai; risiko operasional agunan.
**Rekomendasi:** Tambahkan tabel/relasi jaminan (jenis, nilai, dokumen, status) untuk kedua jenis pinjaman, plus cetak bukti pengembalian jaminan.

### M-04 — Modul Asuransi belum ada
Legacy: `pengaturan/asuransi/{kec}`, `peserta_asuransi`, `_asuransi.blade.php`. Next hanya memiliki tipe dokumen `peserta_asuransi` di `Loans/Show.vue:555` dan template PDF; tidak ada CRUD/pengaturan premi asuransi. Bahkan `Settings/Sop/Index.vue:354` menulis "Asuransi, redaksi SPK, dan CALK menyusul", dan `routes/web.php:1006` mengonfirmasi.
**Rekomendasi:** Implementasikan modul asuransi atau hapus menu yang mengarah ke fitur belum ada.

### M-05 — Modul Saham dan Supplier belum ada
Legacy memiliki model `Saham`, `Supplier`, `SebutanSaham`, `SebutanSupplier`. Di Next hanya ada akun COA bernama "Saham" (`DefaultChartOfAccountsProvisioner.php:93`) dan tidak ada tabel/route supplier (`supplier=0` hasil grep).
**Dampak:** Fitur simpanan saham anggota dan manajemen supplier tidak tersedia.

### M-06 — Beberapa laporan legacy belum setara
Meskipun folder `resources/views/reports/pdf` sangat kaya (150+ template), beberapa laporan legacy tidak ditemukan padanannya, antara lain: laporan asuransi, laporan saham, rekap supplier, dan beberapa formulir jaminan. Perlu pemetaan formal 1:1.

### M-07 — Test coverage tidak merata
| Area | Test | Catatan |
|---|---|---|
| Savings | **0** | modul keuangan baru tanpa test |
| Budgeting | 0 (hanya request map) | CRUD budget tidak diuji |
| Documents | hanya unit signature | cetak dokumen kritis tidak diuji end-to-end |
| Regulatory | 2 (OJK health) | kolektibilitas OJK, allowance, laporan lain tidak diuji |
| Route/UI | rendah | 18 route rusak & Portal crash lolos |
| Excel export | service-only | route export tidak diuji |

**Rekomendasi:** Tambahkan "route smoke test" yang meng-iterasi `route:list` dan memastikan controller method ada; ini akan langsung menangkap 18 route rusak.

### M-08 — Header keamanan & konfigurasi produksi belum eksplisit
Tidak ditemukan middleware CSP/HSTS/X-Frame-Options. `.env.example` memakai `APP_ENV=local`, `TENANCY_ALLOW_HEADER=true`, dan `TENANCY_SEED_USERS=true`; ini berisiko jika disalin apa adanya ke server.
**Rekomendasi:** Dokumentasikan checklist produksi (APP_ENV=production, APP_DEBUG=false, key API wajib, seed users off, quick login off).

### M-09 — Mobile auth tidak membatasi role pada token
`MobileAuthController::login` menerbitkan token untuk semua user `active` tanpa memeriksa role/permission (`MobileAuthController.php:44-64`). Semua endpoint mobile kemudian terbuka (lihat K-07/K-08).
**Rekomendasi:** Terbitkan ability/token scope sesuai role dan validasi di route (`abilities:`).

### M-10 — Duplikasi definisi route desktop & holding
`routes/api.php` mendaftarkan prefix ganda (`v1/...` dan tanpa versi). Ini memperluas permukaan serangan dan membingungkan dokumentasi. Pertimbangkan hanya satu versi publik.

---

## 5. Temuan RENDAH (P3)

1. **Kunci API di query string** — `VerifyHoldingApiToken::extractToken()` menerima `?api_key=`/`?holding_key=` (baris 59-61). Kunci akan tercatat di access log/referrer. Utamakan header.
2. **`ResolveAssistantActor` mengizinkan user platform tanpa `tenant_id` bertindak di tenant mana pun** (`ResolveAssistantActor.php:56-60`). Perketat dengan validasi membership.
3. **Komentar/encoding rusak** — beberapa string mengandung karakter mojibake (`�?"`, `A��,��??` di `routes/web.php:375`, `LoanApproveRequest.php`). Tidak memengaruhi fungsi, tetapi mengurangi kualitas dan bisa merusak teks PDF.
4. **`GetContent` count TODO hanya 1** — disiplin komentar baik.
5. **Tidak ada `declare(strict_types=1)` di beberapa file test/console** — konsistensi.
6. **`app/Http/Controllers/Controller.php` tanpa authorize helper** — setiap controller menulis ulang pola; rawan lupa (lihat K-03).

---

## 6. Status per Modul

### 6.1 Auth, RBAC, Tenancy
- Login web, OTP forgot-password, single-device session, impersonation, province/regency/superadmin guard: **ada**.
- RBAC: permission catalog + role packs + `AuthorizesPermission` trait: **ada dan cukup rapi**.
- Masalah: fail-open user tanpa role, reset password/hapus user tanpa permission, quick login default aktif, mobile token tanpa scope.
- Tenancy: `TenantScope` + `TenantResolver` + shard connection: **konsep baik**, tetapi satu `orWhere` tanpa grouping membocorkan data (K-07).

### 6.2 Master Data & Membership
- Anggota, kelompok, desa, institusi lain, foto KTP, import CSV: **ada**.
- Validasi dan permission via FormRequest: **baik**.
- Masalah: PII di public disk (K-06); lookup grup route rusak (T-02).

### 6.3 Lending (Kelompok & Individu)
- Siklus proposal → verifikasi → approve → cair → angsuran → pelunasan → write-off → reschedule: **ada**, dengan banyak dokumen cetak dan laporan.
- Perhitungan bunga, simulasi, pembulatan, komite, pemanfaat: **ada**.
- Masalah utama: integritas `loan_payments` (K-04), permission bolong pada beberapa aksi (T-08), route edit rusak (T-02), jaminan kelompok belum ada (M-03), asuransi belum ada (M-04).

### 6.4 Savings (Modul Baru)
- Produk, rekening, transaksi, bunga, pajak, admin, tutup rekening, cetak: **ada**.
- Tabel dinamis per tenant `simp_*_t{tenantId}` + `tenant_modules`: **desain baik**.
- Masalah: **0 test**, tidak ada jurnal/GL (T-05), tidak ikut sync desktop (T-04), setelan tidak terpakai (M-01), belum ada integrasi ke laporan keuangan.

### 6.5 Accounting & Reporting
- COA read-only, jurnal, posting, tutup periode, saldo awal, laporan (neraca, laba rugi, arus kas, perubahan ekuitas, CALK, trial balance, buku besar), aset tetap/tak berwujud, konsolidasi kabupaten/provinsi: **sebagian besar ada**.
- Masalah: CALK error 500 (K-05), 9 export Excel + 4 CRUD COA rusak (T-02), laporan yang bergantung `loan_payments` salah (K-04).
- Catatan: klaim subagent bahwa "total konsolidasi provinsi selalu 0" **tidak terbukti** — implementasi `ProvinceConsolidatedReportService::dashboardMetrics()` sudah menghitung total dari `cashBalances`, `loans`, dan `incomeStatement` (baris 55-105). Klaim "BalanceSheet menimpa akun 3.2.02.01" juga **tidak terbukti** — kode hanya menambahkan laba berjalan bila akun belum ada (baris 125-147).

### 6.6 Assets & Budgeting
- Aset tetap/tak berwujud + kategori + status history + penyusutan: **ada**.
- Budgeting per bulan, copy previous, approve/reopen: **ada**, tetapi **tanpa test**.

### 6.7 Regulatory (OJK)
- Kolektibilitas, allowance, laporan kesehatan, rekap pinjaman lunas: **ada**.
- Masalah: bergantung pada `loan_payments` (K-04), test sangat minim (2).

### 6.8 Website & Public Site
- Blog, halaman statis, pesan kontak, pengaturan situs, sitemap/robots: **ada**.
- Masalah: stored XSS karena `v-html` tanpa sanitasi (T-09).

### 6.9 Portal Anggota
- Ringkasan pinjaman, daftar pinjaman, kelompok aktif, pemanfaat: **ada di controller**.
- Masalah: nama prop tidak cocok → crash (T-03).

### 6.10 Mobile & Desktop API
- Mobile: auth token, koleksi, sync offline, verifikasi, eksekutif: **ada**.
- Desktop: snapshot/delta/push: **ada**.
- Holding: laporan konsolidasi: **ada**.
- Masalah: fail-open (K-01, K-02), cross-tenant (K-07), RBAC (K-08, M-09), savings tidak ikut sync (T-04).

### 6.11 Billing & Subscription
- Invoice tenant, Tripay/Duitku/Xendit, subscription gate, blocking: **ada**.
- Route checkout Tripay rusak (T-02).
- Webhook mengikuti pola aman (signature verification) — tidak ditemukan masalah besar.

### 6.12 Settings, SOP, Documents
- Profil, logo, SOP, template tanda tangan, pengaturan lending system, WhatsApp: **ada**.
- Masalah: CSRF (T-01), route WhatsApp pair rusak (T-02), signature/logo di public disk (K-06), modul asuransi/SPK/CALK "menyusul".

---

## 7. Matriks Paritas Legacy SIUPK vs SIUPK Next

| Fitur legacy | Legacy | Next | Status |
|---|---|---|---|
| Anggota & kelompok | ✅ | ✅ | Setara+ |
| Penduduk / desa / kecamatan | ✅ | ✅ | Setara |
| Pinjaman kelompok (perguliran) | ✅ | ✅ | Setara, kecuali jaminan |
| Pinjaman individu | ✅ | ✅ | Setara |
| Pemanfaat & komite | ✅ | ✅ | Setara |
| Angsuran & pencairan | ✅ | ✅ | Ada, tetapi data `loan_payments` tidak konsisten |
| Simpanan | ✅ | ✅ (baru) | Fitur ada, belum terhubung GL & sync |
| Bunga simpanan | ✅ | ✅ | Ada, tanpa test |
| Saham | ✅ | ❌ | **Belum ada** |
| Asuransi | ✅ | ⚠️ | Hanya template dokumen |
| Jaminan/agunan (kelompok) | ✅ | ⚠️ | Hanya individu (JSON) |
| Supplier | ✅ | ❌ | **Belum ada** |
| Inventaris/aset | ✅ | ✅ | Setara (modul aset) |
| Akuntansi & COA | ✅ | ⚠️ | COA read-only + 9 export rusak |
| CALK | ✅ | ⚠️ | Simpan error 500 |
| Tutup buku & alokasi laba | ✅ | ✅ | Setara |
| Laporan OJK / kesehatan | ✅ | ✅ | Ada, minim test |
| E-budgeting | ✅ | ✅ | Ada, tanpa test |
| Pengawas | ✅ | ✅ | Modul supervisor |
| WhatsApp | ✅ | ✅ | Ada, pairing route rusak |
| Cetak dokumen (SPK, kuitansi, kartu, dll) | ✅ | ✅ | Sangat lengkap |
| Portal anggota | ❌ | ✅ | Baru, tetapi crash |
| Mobile & desktop sync | ❌ | ✅ | Baru, ada isu keamanan |
| Billing SaaS multi-tenant | ❌ | ✅ | Baru |
| Modul simpanan multi-tenant | ❌ | ✅ | Baru, belum matang |

---

## 8. Rekomendasi Berprioritas

### P0 — sebelum produksi (target 1–2 sprint)
1. Tutup fail-open API: `VerifyHoldingApiToken`, `VerifyDesktopApiToken`; wajibkan key di production dan batasi user session ke admin.
2. Perbaiki RBAC: `resetPassword`/`destroy`/`index`/`edit` user + `PermissionChecker` fail-closed.
3. Perbaiki integritas `loan_payments`/`loan_payment_allocations` (satu sumber kebenaran) + test rekonsiliasi jurnal vs laporan.
4. Perbaiki cross-tenant `orWhere` di Mobile API (K-07) dan tambahkan permission di endpoint mobile.
5. Perbaiki CALK `saveNotes` dan 18 route rusak (minimal implementasi/hapus).
6. Pindahkan PII (KTP, tanda tangan) ke disk privat + route ber-otorisasi.
7. Tambahkan `<meta name="csrf-token">` dan perbaiki 10 file fetch.
8. Matikan quick login secara default.

### P1 — kualitas & paritas (2–4 sprint)
9. Perbaiki prop Portal (`loanSummary`/`activeGroups`) + smoke test render.
10. Tambahkan test modul Savings (bunga, backdate, pajak, admin, tutup, isolasi tenant).
11. Integrasikan Savings ke jurnal/GL dan Desktop Sync.
12. Sanitasi HTML website (stored XSS).
13. Tambahkan route smoke test otomatis untuk mendeteksi route rusak.
14. Batasi `trustProxies`, matikan `TENANCY_ALLOW_HEADER` secara default, tambahkan security headers.
15. Lengkapi permission pada `revert`, `setCommittee`, `removeBeneficiary`, dan endpoint baca pinjaman.

### P2 — pengembangan fitur (4+ sprint)
16. Modul jaminan/agunan lengkap (kelompok & individu) + dokumen pengembalian.
17. Modul asuransi (peserta, premi, klaim) dan modul saham/supplier jika masih dibutuhkan.
18. Perluas test: budgeting, dokumen cetak, regulatory, UI props.
19. Rapikan encoding/mojibake dan konsistensi `strict_types`.
20. Audit performa query laporan (banyak join `loan_payments` berulang).

---

## 9. Klaim yang Tidak Terbukti / Ditolak

Agar tidak menyesatkan, beberapa klaim dari audit sebelumnya/analisis awal **tidak terbukti** saat diverifikasi ulang:

| Klaim | Status | Bukti |
|---|---|---|
| Total konsolidasi provinsi selalu 0 | **Tidak terbukti** | `ProvinceConsolidatedReportService.php:55-105` menghitung total dengan benar; ada test `ProvinceConsolidatedReportTest` |
| BalanceSheet menimpa akun `3.2.02.01` | **Tidak terbukti** | `BalanceSheetService.php:125-147` hanya menambahkan bila akun belum ada |
| Endpoint onboarding/import tidak ada | **Tidak terbukti** | `routes/web.php:381-386` mendefinisikan 6 route onboarding; `TenantOnboardingImportController` ada |
| Modul savings 0% (audit lama) | **Usang** | Modul savings sekarang lengkap (domain, controller, 5 halaman Vue, 4 tabel dinamis/tenant) |
| Semua aksi lending tanpa permission | **Sebagian salah** | Mayoritas memakai `AuthorizesPermission` + `request_map`; hanya 4 aksi yang bolong (T-08) |
| `loan_payments` tidak pernah ditulis | **Perlu koreksi** | Ditulis oleh seeder, legacy loader, dan mobile sync; **tidak** ditulis oleh pembayaran web (K-04) |

---

## 10. Lampiran — Cara Verifikasi Cepat

```powershell
# 1. Test suite penuh
php artisan test

# 2. Route rusak (class/method tidak ada)
php C:\Users\fandi\AppData\Local\Temp\opencode\route_check.php

# 3. Statistik route
php artisan route:list --json > routes.json
# total 648, named 643

# 4. Cek modul savings punya test atau tidak
Get-ChildItem -Recurse -Include *.php -Path tests | Select-String -Pattern "Savings"

# 5. Cek CSRF meta
Select-String -Path resources\views\app.blade.php -Pattern "csrf"

# 6. Cek pemakaian loan_payments vs loan_payment_allocations
Get-ChildItem -Recurse -Include *.php -Path app | Select-String -Pattern "loan_payments"
```

---

**Kesimpulan:** SIUPK Next adalah aplikasi yang ambisius dan sudah sangat kaya fitur, dengan arsitektur modern dan test suite yang luas. Namun ada **9 temuan kritis** yang menyangkut keamanan akses, kebocoran data lintas tenant, dan integritas laporan keuangan. Test suite yang hijau memberi rasa aman semu karena blind spot pada route, UI props, dan modul Savings. Perbaikan P0 di atas harus diselesaikan sebelum aplikasi dipakai di lingkungan produksi dengan data nyata.
