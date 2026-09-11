# E2E tests (Playwright + Chromium)

Headless Chromium end-to-end smoke tests against the running dev stack.

## Stack
- `@playwright/test` is installed under `new_siupk-node-1` (sandbox path `/usr/bin/chromium`).
- Tests target the dev stack over `http://new_siupk-nginx-1` (docker internal network).
- Logged in as `dev` / `password` against the `local` tenant.

## One-time setup
```bash
# System Chromium (Alpine package, since the headless_shell shipped with Playwright
# has dynamic-library dependencies that the minimal node image does not provide).
docker exec new_siupk-node-1 apk add --no-cache chromium
```

Every dev rebuild sets `public/hot`, which makes `@vite` resolve scripts to the
vite dev server (`localhost:5173`) — unreachable from inside Playwright. Make
sure `public/hot` is removed before running tests so the built manifest is used:

```bash
docker exec new_siupk-app-1 rm public/hot
```

The local tenant must accept requests via the docker hostname. Add it to the
tenant's `metadata.domains` once:

```bash
docker exec new_siupk-app-1 php -r '
require "/var/www/html/vendor/autoload.php";
$app = require "/var/www/html/bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
DB::connection("platform")->table("tenants")->where("code","local")->update([
    "metadata" => json_encode(["domains" => ["new_siupk-nginx-1","localhost"]]),
]);
'
```

## Running

```bash
# from project root (host) — runs against new_siupk-node-1's Playwright
docker exec new_siupk-node-1 npm run e2e
```

Tests skip gracefully when prerequisite data (members, etc.) is missing —
seed a few via the running app first if you want full coverage.

## Coverage
- `tests/e2e/smoke.spec.ts`
  - login flow lands an authenticated user
  - loan proposal form enables officer dropdowns after picking a group
  - `AppCurrencyInput` formats `4500000` → `4.500.000` on blur
  - group form search excludes already-selected members (skips when no members exist)

## Adding new tests
- Use role-based selectors (`getByRole`) where possible.
- Avoid coupling to icon classes or backend names that ship in `name=` attributes (Inertia useForm does not forward `name` to DOM).
- Most Vue forms use lowercase placeholders built from labels (e.g. `Masukkan ${label.toLowerCase()}`); prefer `getByPlaceholder` with `label.toLowerCase()` content.
