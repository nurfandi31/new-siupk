import { test, expect, type Page } from '@playwright/test';

const BASE = process.env.E2E_BASE_URL ?? 'http://localhost:56586';

async function performLogin(page: Page, username: string = 'superadmin'): Promise<boolean> {
    await page.goto(`${BASE}/login`, { waitUntil: 'domcontentloaded' });
    
    const userIn = page.locator('input[autocomplete="username"]').first();
    await userIn.waitFor({ state: 'visible', timeout: 15000 });
    await userIn.focus();
    await userIn.pressSequentially(username, { delay: 15 });

    const passIn = page.locator('input[autocomplete="current-password"]').first();
    await passIn.focus();
    await passIn.pressSequentially('password', { delay: 15 });
    
    await page.getByRole('button', { name: /Masuk/i }).first().click();
    await page.waitForTimeout(2000);
    return true;
}

test.describe('siupk UI Consistency & E2E Tests', () => {

    test('1. Login Page UI component consistency', async ({ page }) => {
        await page.goto(`${BASE}/login`, { waitUntil: 'domcontentloaded' });
        
        // Headings
        await expect(page.getByRole('heading', { name: 'Masuk ke Akun Anda' })).toBeVisible();
        await expect(page.getByRole('heading', { name: 'Transformasi Digital' })).toBeVisible();

        // Standardized Input Components
        const usernameInput = page.locator('input[autocomplete="username"]').first();
        const passwordInput = page.locator('input[autocomplete="current-password"]').first();
        await expect(usernameInput).toBeVisible();
        await expect(passwordInput).toBeVisible();

        // Standardized Button Component
        const submitBtn = page.getByRole('button', { name: /Masuk/i }).first();
        await expect(submitBtn).toBeVisible();
    });

    test('2. Admin Migration Cutover GUI component consistency (/admin/migration)', async ({ page }) => {
        await performLogin(page, 'superadmin');
        await page.goto(`${BASE}/admin/migration`, { waitUntil: 'domcontentloaded' });

        // Verify title or page structure
        const heading = page.locator('h1, h2').first();
        await expect(heading).toBeVisible();
    });

    test('3. Admin Integrations & Tripay Credentials GUI component consistency (/admin/integrations)', async ({ page }) => {
        await performLogin(page, 'superadmin');
        await page.goto(`${BASE}/admin/integrations`, { waitUntil: 'domcontentloaded' });

        // Verify title or page structure
        const heading = page.locator('h1, h2').first();
        await expect(heading).toBeVisible();
    });

    test('4. Tenant Onboarding & Import Wizard GUI component consistency (/onboarding/import)', async ({ page }) => {
        await performLogin(page, 'dev');
        await page.goto(`${BASE}/onboarding/import`, { waitUntil: 'domcontentloaded' });

        // Verify title or page structure
        const heading = page.locator('h1, h2').first();
        await expect(heading).toBeVisible();
    });

});
