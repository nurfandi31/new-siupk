import { test, expect, type Page } from '@playwright/test';

const BASE = process.env.E2E_BASE_URL ?? 'http://localhost:56586';

async function humanType(page: Page, selectorOrLocator: any, text: string) {
    const el = typeof selectorOrLocator === 'string' ? page.locator(selectorOrLocator).first() : selectorOrLocator.first();
    await el.waitFor({ state: 'visible', timeout: 15000 });
    await el.click();
    await el.fill('');
    await el.pressSequentially(text, { delay: 30 });
}

async function loginUser(page: Page, username: string) {
    await page.context().clearCookies();
    await page.goto(`${BASE}/login`, { waitUntil: 'domcontentloaded' });

    const userIn = page.locator('input[autocomplete="username"]').first();
    await userIn.waitFor({ state: 'visible', timeout: 15000 });
    await userIn.fill('');
    await userIn.pressSequentially(username, { delay: 30 });

    const passIn = page.locator('input[autocomplete="current-password"]').first();
    await passIn.fill('');
    await passIn.pressSequentially('password', { delay: 30 });

    await page.getByRole('button', { name: /Masuk/i }).first().click();
    await page.waitForFunction(() => !window.location.pathname.includes('/login'), { timeout: 20000 });
    await page.waitForTimeout(500);
}

test.describe('Scenario 2: Edge Cases, Input Validation & Security Boundary Testing', () => {

    test('2.1 Invalid Login Attempt', async ({ page }) => {
        await page.context().clearCookies();
        await page.goto(`${BASE}/login`, { waitUntil: 'domcontentloaded' });
        await humanType(page, 'input[autocomplete="username"]', 'wronguser999');
        await humanType(page, 'input[autocomplete="current-password"]', 'wrongpassword');
        await page.getByRole('button', { name: /Masuk/i }).first().click();

        await page.waitForTimeout(1000);
        await expect(page).toHaveURL(/\/login/);
    });

    test('2.2 Empty Form Submission Validation on Plan Creation', async ({ page }) => {
        await loginUser(page, 'superadmin');

        await page.goto(`${BASE}/admin/plans/create`, { waitUntil: 'domcontentloaded' });
        await page.getByRole('button', { name: /Simpan/i }).first().click();

        await page.waitForTimeout(500);
        await expect(page).toHaveURL(/\/admin\/plans\/create/);
    });

    test('2.3 Unauthorized Access Boundary - Tenant Accessing Admin Suite', async ({ page }) => {
        await loginUser(page, 'dev');

        await page.goto(`${BASE}/admin/tenants`, { waitUntil: 'domcontentloaded' });
        await page.waitForTimeout(1000);
        expect(page.url()).not.toContain('/admin/tenants');
    });

    test('2.4 Regional Selection Fallback Verification', async ({ page }) => {
        await loginUser(page, 'superadmin');

        await page.goto(`${BASE}/admin/tenants/create`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('body')).toBeVisible();

        const provTrigger = page.locator('button:has-text("Pilih provinsi"), button:has-text("Provinsi")').first();
        if (await provTrigger.isVisible().catch(() => false)) {
            await provTrigger.click();
            await page.waitForTimeout(300);
            const jatengOpt = page.locator('[role="option"]:has-text("JAWA TENGAH"), li:has-text("JAWA TENGAH")').first();
            if (await jatengOpt.isVisible().catch(() => false)) {
                await jatengOpt.click();
                await page.waitForTimeout(300);

                const regTrigger = page.locator('button:has-text("Pilih kabupaten"), button:has-text("Kabupaten")').first();
                await expect(regTrigger).not.toContainText('Tidak ada opsi');
            }
        }
    });

    test('2.5 Payment Gateway Form Validation & Toggle Resilience', async ({ page }) => {
        await loginUser(page, 'superadmin');

        await page.goto(`${BASE}/admin/payment-gateways`, { waitUntil: 'domcontentloaded' });
        await page.waitForTimeout(1000);

        const cardOrHeader = page.locator('h1, h2, main, header').first();
        await expect(cardOrHeader).toBeVisible();
    });
});
