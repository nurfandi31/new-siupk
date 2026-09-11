# Audit Komprehensif siupk Next — README

Tanggal sesi: 2026-08-14 s/d 2026-08-15 (UTC+7)
Sesi: `tests/TEST_AUDIT_LOG.md` → komprehensif via Playwright + manual fix.

## Tujuan

1. Audit keseluruhan codebase (`F:\Workspace\laragon\www\new_siupk`),
   backend + frontend, dengan **uji coba fitur seperti manusia** (bukan
   hanya inventory route).
2. Perbaiki flow / alur yang acak-acakan selama audit.
3. Setiap pengujian dicatat per-fitur di `docs/audit/2026-08-14/<domain>.md`
   dengan format `{input, expected, actual, status}`.
4. Pengujian migrasi legacy `kecamatan_id=76` melalui halaman admin,
   mode live, data riil.

## Ringkasan Per-Domain

| #   | Domain                                | File log                                | Pass/Total | Status |
| --- | ------------------------------------- | --------------------------------------- | ---------- | ------ |
| D1  | Public & Auth                         | `auth.md`                               | 10/10      |   ✅   |
| D2  | Admin Platform (superadmin)           | `admin-platform.md`                     | 24/24      |   ✅   |
| D3  | Master Data (dev)                     | `master-data.md`                        | 10/10      |   ✅   |
| D4  | Lending (dev)                         | `lending.md`                            | 12/12      |   ✅   |
| D5  | Accounting (dev)                      | `accounting.md`                         | 14/14      |   ✅   |
| D6  | Budgeting (dev)                       | `budgeting.md`                          | 2/2        |   ✅   |
| D7  | Tenant Onboarding (superadmin)        | `onboarding.md`                         | 9/9        |   ✅   |
| D8  | Profile & Settings (dev)              | `profile-settings.md`                   | 4/4        |   ✅   |
| D9  | Tenant Billing (dev)                  | `billing.md`                            | 2/2        |   ✅   |
| D10 | Notifications & WA                    | `notifications-wa.md`                   | 6/6        |   ✅   |
| D11 | Search & Misc                         | `search-regional.md`                    | 4/4        |   ✅   |
| D12 | Province/Regency + Webhooks           | `webhooks-province-regency.md`          | 6/6        |   ✅   |
| D13 | AI Assistant Widget                   | `assistant-widget.md`                   | 2/2        |   ✅   |

Hasil `full-audit.spec.ts`: **49/49 PASS** dalam ~15.3 menit (setelah perbaikan F000-F007).
Lihat `fixes.md` untuk daftar bug + patch.

## Re-eval pasca refactor Saldo Awal (2026-08-15)

| Suite                              | Hasil                    | Durasi    |
| ---------------------------------- | ------------------------ | --------- |
| PHPUnit Unit                       | **40/40 PASS** (143 asr.)  | 38.4s     |
| PHPUnit Feature                    | **174/174 PASS** (1406 asr.) | 929.9s    |
| Playwright `full-audit.spec.ts`    | **49/49 PASS** | 19.4m      |

Catatan teknis (3 bug ditemukan & diperbaiki selama re-eval):

- **Bug F011**: SQLite test DB (`database/platform_test.sqlite`,
  `tenant_test.sqlite`) corrupt "database disk image is malformed" setelah
  parallel run. **Fix**: hapus file sebelum run; Laravel akan recreate via
  `migrate:fresh` di trait `BuildsTenantTestDatabase`.
- **Bug F012**: `tests/e2e/full-audit.spec.ts` D7.1 + D7.2 masih pakai URL
  lama `/onboarding/import` dan `/onboarding/templates/{type}` (sebelum
  refactor Saldo Awal ke `/admin/tenants/{tenant}/onboarding/*`). **Fix**:
  update URL + tambah `loginAs('superadmin')` di D7.2 (route butuh auth +
  superadmin).
- **Bug F013**: Controller `TenantOnboardingImportController::downloadTemplate`
  signature `downloadTemplate(string $type, ...)` → Laravel inject nilai
  `{tenant}` ke `$type` (positional, param pertama). **Fix**: tambah explicit
  `Tenant $tenant` parameter + import `App\Models\Platform\Tenant`.

Lihat `fixes.md` untuk detail tiap patch.

## Tahap 4 — Migrasi Live kecamatan_id 76

Lihat `migration-76.md`. Ringkas:

- Cutover end-to-end via GUI admin (`/admin/migration`) **BERHASIL** (run #5).
- 22.541 jurnal + 1.409 anggota + 1.420 kelompok + 1.721 pinjaman + 41.418
  angsuran berpindah dari legacy `103.177.95.91/siupk` (suffix=76) ke
  tenant lokal `siupk_shard_local` (MySQL container).
- 3 bug baru ditemukan & diperbaiki selama eksekusi (F008-F010).

## Environment

- Stack: Docker Compose (`nginx:56586`, `app`, `queue`, `mysql:3307`,
  `redis`, `postgres+pgvector`, `ollama`).
- Legacy DB: `103.177.95.91:3306/siupk` (cPanel, SELECT only).
- Platform DB: `siupk_platform` (root/root).
- Tenant DB: `siupk_shard_local` (root/root).
- Users seeded: `superadmin`/`password`, `dev`/`password` (via `UserSeeder`).
- Queue worker: `php artisan queue:work redis --tries=3 --timeout=90`.

## Cara Reproduksi Audit

```bash
# Pre-flight
docker exec new_siupk-app-1 php artisan optimize:clear

# Audit penuh
npx playwright test tests/e2e/full-audit.spec.ts --reporter=list

# Migrasi live (akhiri)
npx playwright test tests/e2e/full-audit-migration-76.spec.ts --reporter=list
```