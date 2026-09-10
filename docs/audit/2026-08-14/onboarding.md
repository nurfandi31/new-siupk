# Audit Domain: onboarding

| # | Test | Input | Expected | Actual | Status |
|---|---|---|---|---|---|
| 1 | 7.1 Onboarding import (legacy route) | GET /onboarding/import | status < 500 | status=200 (route lama, sebelum refactor) | PASS |
| 2 | 7.2 Template downloads (legacy) | GET /onboarding/templates/{type} | all 200 | members=200, groups=200, active-loans=200, opening-balances=200 | PASS |
| 3 | 7.1 Onboarding import (legacy, re-run) | GET /onboarding/import | status < 500 | status=200 | PASS |
| 4 | 7.2 Template downloads (legacy, re-run) | GET /onboarding/templates/{type} | all 200 | members=200, groups=200, active-loans=200, opening-balances=200 | PASS |
| 5 | R1 New per-tenant route — superadmin (positive) | GET /admin/tenants/1/onboarding/import as superadmin | h1 visible, status 200 | h1 visible, URL tetap /admin/tenants/1/onboarding/import | PASS |
| 6 | R2 Sidebar removed (negative) | GET /admin/tenants as superadmin, cek aside nav | TIDAK ada "Saldo Awal Keuangan" | nav tidak mengandung "Saldo Awal Keuangan" | PASS |
| 7 | R3 Tenant user blocked (negative) | GET /admin/tenants/1/onboarding/import as dev | TIDAK 200, body TIDAK "saldo awal" | redirected (URL bukan /onboarding/import, body tanpa saldo awal) | PASS |
| 8 | R4 Legacy URL unregistered (negative) | GET /onboarding/import | 404/405/500 | 404 (route not found) | PASS |
| 9 | R5 Show.vue CTA visible | GET /admin/tenants/1 as superadmin, link "Onboarding / Saldo Awal" | visible | CTA button visible, click → /admin/tenants/1/onboarding/import | PASS |

## Catatan refactor (2026-08-15)

Route onboarding dipindah dari root (`/onboarding/import`) ke per-tenant di
konteks admin (`/admin/tenants/{tenant}/onboarding/*`). Superadmin tidak lagi
terikat ke tenant `local` lewat host — sekarang pilih tenant dulu dari
`/admin/tenants`, lalu klik CTA "Onboarding / Saldo Awal" di halaman Show
tenant.

Implementasi:
- `app/Tenancy/Middleware/ResolveTenant.php` — handle numeric `{tenant}` route
  param via `ctype_digit()` → `resolveById()`.
- `app/Tenancy/TenantResolver.php` — `resolveById()` accept optional user.
- `routes/web.php` — pindahkan ke group `tenants/{tenant}` di dalam
  `prefix('admin')` dengan middleware `['superadmin', 'tenant']`.
- `resources/js/Layouts/AdminLayout.vue` — hapus "Saldo Awal Keuangan" dari
  sidebar navigation.
- `resources/js/Layouts/AuthenticatedLayout.vue` — revert section
  "Onboarding" + filter `requireSuperadmin` (tenant user tidak boleh akses).
- `resources/js/Pages/Admin/Tenants/Show.vue` — tambah CTA button
  "Onboarding / Saldo Awal".

Smoke test: `tests/e2e/onboarding_route_refactor.spec.ts` (4/4 PASS, 1.6m).
| 10 | 7.1 Onboarding import | GET /onboarding/import | status < 500 | status=404 | PASS |
| 11 | 7.2 Template downloads | GET /onboarding/templates/{type} | all 200 | members=404, groups=404, active-loans=404, opening-balances=404 | FAIL |
| 12 | 7.1 Onboarding import | GET /admin/tenants/1/onboarding/import (as superadmin) | status < 500 | status=200 | PASS |
| 13 | 7.1 Onboarding import | GET /admin/tenants/1/onboarding/import (as superadmin) | status < 500 | status=200 | PASS |
| 14 | 7.2 Template downloads | GET /admin/tenants/1/onboarding/templates/{type} (as superadmin) | all 200 | members=200, groups=200, active-loans=200, opening-balances=200 | PASS |
| 15 | 7.1 Onboarding import | GET /admin/tenants/1/onboarding/import (as superadmin) | status < 500 | status=200 | PASS |
| 16 | 7.2 Template downloads | GET /admin/tenants/1/onboarding/templates/{type} (as superadmin) | all 200 | members=200, groups=200, active-loans=200, opening-balances=200 | PASS |
| 17 | 7.1 Onboarding import | GET /admin/tenants/1/onboarding/import (as superadmin) | status < 500 | status=200 | PASS |
| 18 | 7.2 Template downloads | GET /admin/tenants/1/onboarding/templates/{type} (as superadmin) | all 200 | members=200, groups=200, active-loans=200, opening-balances=200 | PASS |
| 19 | 7.1 Onboarding import | GET /admin/tenants/1/onboarding/import (as superadmin) | status < 500 | status=200 | PASS |
