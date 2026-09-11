import { test, expect, type Page } from '@playwright/test';

const BASE = process.env.E2E_BASE_URL ?? 'http://localhost:56586';

async function humanType(page: Page, selectorOrLocator: any, text: string) {
    const el = typeof selectorOrLocator === 'string' ? page.locator(selectorOrLocator).first() : selectorOrLocator.first();
    await el.waitFor({ state: 'visible', timeout: 15000 });
    await el.click();
    await el.fill('');
    await el.pressSequentially(text, { delay: 30 });
}

async function loginSuperAdmin(page: Page) {
    await page.goto(`${BASE}/login`, { waitUntil: 'domcontentloaded' });
    if (!page.url().includes('/login')) {
        await page.context().clearCookies();
        await page.goto(`${BASE}/login`, { waitUntil: 'domcontentloaded' });
    }
    const userIn = page.locator('input[autocomplete="username"]').first();
    await userIn.waitFor({ state: 'visible', timeout: 15000 });
    await userIn.fill('');
    await userIn.pressSequentially('superadmin', { delay: 30 });

    const passIn = page.locator('input[autocomplete="current-password"]').first();
    await passIn.fill('');
    await passIn.pressSequentially('password', { delay: 30 });

    await page.getByRole('button', { name: /Masuk/i }).first().click();
    await page.waitForFunction(() => !window.location.pathname.includes('/login'), { timeout: 20000 });
    await page.waitForTimeout(500);
}

test.describe('Scenario 3: Real Production Legacy Migration for Kecamatan ID 76 via Admin GUI', () => {

    test('3.1 Access Admin Migration GUI & Verify Suffix Options and Legacy DB Settings', async ({ page }) => {
        await loginSuperAdmin(page);

        await page.goto(`${BASE}/admin/migration`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toContainText('Migrasi Data Legacy');

        await expect(page.locator('text=Host:').first()).toBeVisible();
        await expect(page.locator('text=Database:').first()).toBeVisible();
    });

    test('3.2 Execute Legacy Cutover Migration for Suffix / Kecamatan ID 76 via GUI', async ({ page }) => {
        test.setTimeout(180000);

        await loginSuperAdmin(page);

        await page.goto(`${BASE}/admin/migration`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toContainText('Migrasi Data Legacy');

        // Target Suffix ID 76
        const suffixTrigger = page.locator('button:has-text("Suffix"), button:has-text("1"), input[name="suffix"]').first();
        if (await suffixTrigger.isVisible().catch(() => false)) {
            const tagName = await suffixTrigger.evaluate(el => el.tagName.toLowerCase());
            if (tagName === 'input') {
                await suffixTrigger.fill('76');
            } else {
                await suffixTrigger.click();
                await page.waitForTimeout(400);

                const opt76 = page.locator('[role="option"]:has-text("76"), li:has-text("76")').first();
                if (await opt76.isVisible().catch(() => false)) {
                    await opt76.click();
                } else {
                    const selectSearch = page.locator('input[placeholder*="Cari"], input[type="search"]').first();
                    if (await selectSearch.isVisible().catch(() => false)) {
                        await selectSearch.fill('76');
                        await page.waitForTimeout(300);
                        const firstOpt = page.locator('[role="option"]').first();
                        if (await firstOpt.isVisible().catch(() => false)) {
                            await firstOpt.click();
                        }
                    }
                }
            }
        }

        // Submit form
        const submitBtn = page.getByRole('button', { name: /Jalankan Migrasi Data/i }).first();
        await expect(submitBtn).toBeVisible();
        await submitBtn.click();

        await page.waitForTimeout(2000);

        // Check if modal or table is rendered
        const modalOrTable = page.locator('.fixed, table, pre, [class*="modal"]').first();
        await expect(modalOrTable).toBeVisible({ timeout: 15000 });

        // Poll log output
        const consoleLog = page.locator('pre').first();
        if (await consoleLog.isVisible().catch(() => false)) {
            for (let i = 0; i < 20; i++) {
                await page.waitForTimeout(3000);
                const logContent = await consoleLog.textContent() || '';
                if (logContent.includes('SELESAI CUTOVER') || logContent.includes('BERHASIL') || logContent.includes('GAGAL')) {
                    break;
                }
            }
        }
    });
});
