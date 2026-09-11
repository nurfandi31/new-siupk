import { test, expect, type Page } from '@playwright/test';

const BASE = process.env.E2E_BASE_URL ?? 'http://localhost:56586';

async function loginUser(page: Page, username: string = 'dev'): Promise<void> {
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

test.describe('1. Interactive Admin Suite (CRUD & Operations)', () => {
    test.beforeEach(async ({ page }) => {
        await loginUser(page, 'superadmin');
    });

    test('1.1. Admin - Plans: Create New Plan', async ({ page }) => {
        await page.goto(`${BASE}/admin/plans/create`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toContainText('Tambah Plan');

        const uniqueCode = 'plan_' + Math.floor(Math.random() * 10000);
        await page.getByLabel('Kode', { exact: true }).fill(uniqueCode);
        await page.getByLabel('Nama', { exact: true }).fill('Plan Test E2E');
        await page.getByLabel('Harga', { exact: true }).fill('150000');

        await page.getByRole('button', { name: /Simpan/i }).first().click();
        await page.waitForTimeout(1000);
        expect(page.url()).toContain('/admin/plans');
    });

    test('1.2. Admin - Integrations: View Payment Gateways', async ({ page }) => {
        await page.goto(`${BASE}/admin/payment-gateways`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('body')).toBeVisible();
    });
});

test.describe('2. Interactive Master Data & Operational Suite', () => {
    test.beforeEach(async ({ page }) => {
        await loginUser(page, 'dev');
    });

    test('2.1. Master Data - Members List', async ({ page }) => {
        await page.goto(`${BASE}/master-data/members`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toBeVisible();
    });

    test('2.2. Master Data - Groups List', async ({ page }) => {
        await page.goto(`${BASE}/master-data/groups`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toBeVisible();
    });

    test('2.3. Lending - Loans List', async ({ page }) => {
        await page.goto(`${BASE}/lending/loans`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toBeVisible();
    });

    test('2.4. Accounting - Chart of Accounts List', async ({ page }) => {
        await page.goto(`${BASE}/accounting/chart-of-accounts`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toBeVisible();
    });
});
