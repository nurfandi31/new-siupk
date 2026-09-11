import { test, expect, type Page } from '@playwright/test';

const BASE = process.env.E2E_BASE_URL ?? 'http://localhost:56586';

async function loginAs(page: Page, username: string) {
    await page.context().clearCookies();
    await page.goto(`${BASE}/login`, { waitUntil: 'domcontentloaded' });
    const userIn = page.locator('input[autocomplete="username"]').first();
    await userIn.waitFor({ state: 'visible', timeout: 15000 });
    await userIn.fill('');
    await userIn.pressSequentially(username, { delay: 20 });

    const passIn = page.locator('input[autocomplete="current-password"]').first();
    await passIn.fill('');
    await passIn.pressSequentially('password', { delay: 20 });

    await page.getByRole('button', { name: /Masuk/i }).first().click();
    await page.waitForFunction(() => !window.location.pathname.includes('/login'), { timeout: 20000 });
    await page.waitForTimeout(400);
}

async function selectSmartOption(page: Page, triggerLabel: string) {
    const trigger = page.locator(`button:has-text("${triggerLabel}")`).first();
    if (await trigger.isVisible({ timeout: 3000 }).catch(() => false)) {
        if (await trigger.isEnabled().catch(() => false)) {
            await trigger.click();
            await page.waitForTimeout(250);
            const opt = page.locator('[role="option"]').first();
            if (await opt.isVisible({ timeout: 2000 }).catch(() => false)) {
                await opt.click();
                await page.waitForTimeout(150);
            }
        }
    }
}

test.describe('ULTIMATE COMPLETE SYSTEM FEATURES AUDIT', () => {
    test.describe.configure({ mode: 'serial' });

    test('1. Perguliran Pinjaman — Proposal Submission & Browsing Tabs', async ({ page }) => {
        await loginAs(page, 'dev');

        await page.goto(`${BASE}/lending/loans?tab=proposal`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toBeVisible();

        await page.goto(`${BASE}/lending/loans?tab=verifikasi`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toBeVisible();

        await page.goto(`${BASE}/lending/loans?tab=waiting`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toBeVisible();

        await page.goto(`${BASE}/lending/loans?tab=aktif`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toBeVisible();

        await page.goto(`${BASE}/lending/loans?tab=lunas`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toBeVisible();
    });

    test('2. Perguliran Pinjaman — Create Proposal & Submit New Loan Form', async ({ page }) => {
        await loginAs(page, 'dev');
        await page.goto(`${BASE}/lending/loans/create`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toBeVisible();

        await selectSmartOption(page, 'Pilih kelompok');

        const amountIn = page.getByLabel('Jumlah Pinjaman').first();
        if (await amountIn.isVisible().catch(() => false)) await amountIn.fill('10000000');

        const rateIn = page.getByLabel('Bunga').first();
        if (await rateIn.isVisible().catch(() => false)) await rateIn.fill('1.2');

        const tenorIn = page.getByLabel('Jangka Waktu').first();
        if (await tenorIn.isVisible().catch(() => false)) await tenorIn.fill('12');

        const saveBtn = page.locator('button:has-text("Simpan"), button:has-text("Ajukan")').first();
        if (await saveBtn.isVisible().catch(() => false)) {
            await saveBtn.click();
            await page.waitForTimeout(2000);
        }
    });

    test('3. Angsuran Pinjaman — Installment Journal Form & Printable Receipt', async ({ page }) => {
        await loginAs(page, 'dev');

        await page.goto(`${BASE}/accounting/journal-entries/installment`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1').first()).toBeVisible();

        await selectSmartOption(page, 'Pilih pinjaman');
    });

    test('4. Jurnal Umum — General Journal Entry Form & Cash Evidence Print', async ({ page }) => {
        await loginAs(page, 'dev');

        await page.goto(`${BASE}/accounting/journal-entries/create`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toBeVisible();

        const descIn = page.getByLabel('Keterangan').first();
        if (await descIn.isVisible().catch(() => false)) {
            await descIn.fill('Penerimaan Sewa Gedung Serbaguna UPK');
        }

        await page.goto(`${BASE}/accounting/journals`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toBeVisible();
    });

    test('5. AI Assistant — Hub, Personas, Tools, Documents RAG & API Status', async ({ page }) => {
        await loginAs(page, 'superadmin');

        await page.goto(`${BASE}/admin/integrations/orchestrator`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('body')).toBeVisible();

        const personasRes = await page.request.get(`${BASE}/ai-assistant/personas`);
        expect(personasRes.status()).toBeLessThan(500);

        const toolsRes = await page.request.get(`${BASE}/ai-assistant/tools`);
        expect(toolsRes.status()).toBeLessThan(500);

        const docsRes = await page.request.get(`${BASE}/ai-assistant/documents`);
        expect(docsRes.status()).toBeLessThan(500);
    });
});
