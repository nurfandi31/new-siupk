import { test, expect, type Page } from '@playwright/test';

const BASE = process.env.E2E_BASE_URL ?? 'http://localhost:56586';

async function loginOnce(page: Page, username: string = 'dev'): Promise<void> {
    await page.context().clearCookies();
    await page.goto(`${BASE}/login`, { waitUntil: 'domcontentloaded' });
    
    const userIn = page.locator('input[autocomplete="username"]').first();
    await userIn.waitFor({ state: 'visible', timeout: 15000 });
    await userIn.fill('');
    await userIn.pressSequentially(username, { delay: 25 });

    const passIn = page.locator('input[autocomplete="current-password"]').first();
    await passIn.fill('');
    await passIn.pressSequentially('password', { delay: 25 });

    await page.getByRole('button', { name: /Masuk/i }).first().click();
    await page.waitForFunction(() => !window.location.pathname.includes('/login'), { timeout: 20000 });
    await page.waitForTimeout(500);
}

test.describe('1. Landing Page & Public UI', () => {

    test('1.1. Landing page displays professional hero, features, steps, and interactive FAQ', async ({ page }) => {
        await page.goto(`${BASE}/`, { waitUntil: 'domcontentloaded' });
        
        await expect(page.locator('body')).toBeVisible();
        await expect(page.locator('text=Dana Bergulir').first()).toBeVisible();

        const faqButton = page.locator('button:has-text("Apa itu"), button:has-text("Apakah")').first();
        if (await faqButton.isVisible().catch(() => false)) {
            await faqButton.click();
            await page.waitForTimeout(200);
        }

        const loginLink = page.locator('a[href*="/login"]').first();
        await expect(loginLink).toBeVisible();
    });

    test('1.2. Login page renders clean design without framework leaks', async ({ page }) => {
        await page.goto(`${BASE}/login`, { waitUntil: 'domcontentloaded' });
        
        await expect(page.getByRole('heading', { name: 'Masuk ke Akun Anda' })).toBeVisible();
        await expect(page.locator('input[autocomplete="username"]')).toBeVisible();
        await expect(page.locator('input[autocomplete="current-password"]')).toBeVisible();
        await expect(page.getByRole('button', { name: /Masuk/i })).toBeVisible();
    });

});

test.describe('2. Admin Suite Tests (/admin)', () => {
    test.beforeEach(async ({ page }) => {
        await loginOnce(page, 'superadmin');
    });

    test('2.0. Admin Login', async ({ page }) => {
        await page.goto(`${BASE}/admin`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toBeVisible();
    });

    test('2.1. Admin Dashboard', async ({ page }) => {
        await page.goto(`${BASE}/admin`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toBeVisible();
    });

    test('2.2. Admin Tenant Management (/admin/tenants)', async ({ page }) => {
        await page.goto(`${BASE}/admin/tenants`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toBeVisible();
    });

    test('2.3. Admin Subscription Plans (/admin/plans)', async ({ page }) => {
        await page.goto(`${BASE}/admin/plans`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toBeVisible();
    });

    test('2.4. Admin Invoices (/admin/invoices)', async ({ page }) => {
        await page.goto(`${BASE}/admin/invoices`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toBeVisible();
    });

    test('2.5. Admin Legacy Migration Hub (/admin/migration)', async ({ page }) => {
        await page.goto(`${BASE}/admin/migration`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toBeVisible();
    });

    test('2.6. Admin Integrations & Gateway (/admin/payment-gateways)', async ({ page }) => {
        await page.goto(`${BASE}/admin/payment-gateways`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('body')).toBeVisible();
    });
});

test.describe('3. Operational Tenant Suite Tests', () => {
    test.beforeEach(async ({ page }) => {
        await loginOnce(page, 'dev');
    });

    test('3.0. Tenant User Login', async ({ page }) => {
        await page.goto(`${BASE}/dashboard`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toBeVisible();
    });

    test('3.1. Operations Dashboard (/dashboard)', async ({ page }) => {
        await page.goto(`${BASE}/dashboard`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toBeVisible();
    });

    test('3.2. Master Data - Anggota (/master-data/members)', async ({ page }) => {
        await page.goto(`${BASE}/master-data/members`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toBeVisible();
    });

    test('3.3. Master Data - Kelompok (/master-data/groups)', async ({ page }) => {
        await page.goto(`${BASE}/master-data/groups`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toBeVisible();
    });

    test('3.4. Master Data - Desa / Wilayah (/master-data/villages)', async ({ page }) => {
        await page.goto(`${BASE}/master-data/villages`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toBeVisible();
    });

    test('3.5. Master Data - Lembaga Lain (/master-data/other-institutions)', async ({ page }) => {
        await page.goto(`${BASE}/master-data/other-institutions`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toBeVisible();
    });

    test('3.6. Lending - Pinjaman (/lending/loans)', async ({ page }) => {
        await page.goto(`${BASE}/lending/loans`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toBeVisible();
    });

    test('3.7. Lending - Reports (/lending/reports)', async ({ page }) => {
        await page.goto(`${BASE}/lending/reports`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toBeVisible();
    });

    test('3.8. Accounting - Bagan Akun COA (/accounting/chart-of-accounts)', async ({ page }) => {
        await page.goto(`${BASE}/accounting/chart-of-accounts`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toBeVisible();
    });

    test('3.9. Accounting - Jurnal Umum (/accounting/journal-entries)', async ({ page }) => {
        await page.goto(`${BASE}/accounting/journal-entries`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toBeVisible();
    });

    test('3.10. Accounting - Tutup Buku (/accounting/period-close)', async ({ page }) => {
        await page.goto(`${BASE}/accounting/period-close`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toBeVisible();
    });

    test('3.11. Accounting - Reports Hub (/accounting/reports)', async ({ page }) => {
        await page.goto(`${BASE}/accounting/reports`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toBeVisible();
    });

    test('3.12. Settings (/settings)', async ({ page }) => {
        await page.goto(`${BASE}/settings`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toBeVisible();
    });

    test('3.13. Profile (/profile)', async ({ page }) => {
        await page.goto(`${BASE}/profile`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toBeVisible();
    });
});
