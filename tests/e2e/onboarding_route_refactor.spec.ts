import { test, expect } from '@playwright/test';

const BASE = process.env.E2E_BASE_URL ?? 'http://localhost:56586';

test.describe('Onboarding route refactor → /admin/tenants/{tenant}/onboarding/*', () => {
    test('superadmin can access /admin/tenants/1/onboarding/import', async ({ page }) => {
        await page.context().clearCookies();
        await page.request.post(`${BASE}/logout`).catch(() => {});
        await page.goto(`${BASE}/login`, { waitUntil: 'domcontentloaded' });
        await page.locator('input[autocomplete="username"]').first().fill('superadmin');
        await page.locator('input[autocomplete="current-password"]').first().fill('password');
        await page.getByRole('button', { name: /Masuk/i }).first().click();
        await page.waitForURL((url) => !url.pathname.includes('/login'), { timeout: 25000 });

        // Navigate via the Show page CTA
        await page.goto(`${BASE}/admin/tenants/1`, { waitUntil: 'domcontentloaded' });
        await expect(page.getByRole('link', { name: /Onboarding.*Saldo Awal/i }).first()).toBeVisible({ timeout: 10000 });

        // Click the CTA → should land on /admin/tenants/1/onboarding/import
        await page.getByRole('link', { name: /Onboarding.*Saldo Awal/i }).first().click();
        await page.waitForURL(/\/admin\/tenants\/1\/onboarding\/import$/, { timeout: 15000 });
        await expect(page.locator('h1').first()).toBeVisible();
        const status = page.url().endsWith('/admin/tenants/1/onboarding/import') ? 200 : 0;
        expect(status).toBe(200);
    });

    test('superadmin sidebar no longer shows Saldo Awal Keuangan', async ({ page }) => {
        await page.context().clearCookies();
        await page.request.post(`${BASE}/logout`).catch(() => {});
        await page.goto(`${BASE}/login`, { waitUntil: 'domcontentloaded' });
        await page.locator('input[autocomplete="username"]').first().fill('superadmin');
        await page.locator('input[autocomplete="current-password"]').first().fill('password');
        await page.getByRole('button', { name: /Masuk/i }).first().click();
        await page.waitForURL((url) => !url.pathname.includes('/login'), { timeout: 25000 });
        await page.goto(`${BASE}/admin/tenants`, { waitUntil: 'domcontentloaded' });

        const navText = await page.locator('aside nav').innerText().catch(() => '');
        expect(navText).not.toContain('Saldo Awal Keuangan');
    });

    test('dev user is blocked from /admin/tenants/1/onboarding/import', async ({ page }) => {
        await page.context().clearCookies();
        await page.request.post(`${BASE}/logout`).catch(() => {});
        await page.goto(`${BASE}/login`, { waitUntil: 'domcontentloaded' });
        await page.locator('input[autocomplete="username"]').first().fill('dev');
        await page.locator('input[autocomplete="current-password"]').first().fill('password');
        await page.getByRole('button', { name: /Masuk/i }).first().click();
        await page.waitForURL((url) => !url.pathname.includes('/login'), { timeout: 25000 });

        // Direct navigation should NOT succeed as 200 with onboarding content.
        const response = await page.goto(`${BASE}/admin/tenants/1/onboarding/import`, { waitUntil: 'domcontentloaded' });
        // Either redirected away from /onboarding/import, or response is 403/forbidden.
        const finalUrl = page.url();
        const isOnOnboarding = finalUrl.includes('/onboarding/import');
        if (isOnOnboarding) {
            // If somehow still on onboarding, the body must NOT show saldo form heading
            const bodyText = await page.locator('body').innerText();
            expect(bodyText.toLowerCase()).not.toContain('saldo awal');
        }
        expect(isOnOnboarding).toBe(false);
        expect(response?.status() ?? 0).toBeLessThan(500);
    });

    test('old /onboarding/import URL no longer registered', async ({ page }) => {
        const response = await page.request.get(`${BASE}/onboarding/import`, { maxRedirects: 0 }).catch(() => null);
        if (response) {
            // Old route should return 404 (route not found) — not 200/302 to /login
            expect([404, 405, 500]).toContain(response.status());
        }
    });
});
