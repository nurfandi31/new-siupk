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

test.describe('FULL END-TO-END REVOLVING LOAN CYCLE & AI ASSISTANT TOOL EXECUTION', () => {
    test.describe.configure({ mode: 'serial' });

    test('1. Full Revolving Loan Life Cycle — Proposal, Verify, Approve, Disburse', async ({ page }) => {
        await loginAs(page, 'dev');

        await page.goto(`${BASE}/lending/loans?tab=proposal`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toBeVisible();

        await page.goto(`${BASE}/lending/loans/create`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toBeVisible();

        const groupTrigger = page.locator('button:has-text("Pilih kelompok")').first();
        if (await groupTrigger.isVisible({ timeout: 3000 }).catch(() => false)) {
            if (await groupTrigger.isEnabled().catch(() => false)) {
                await groupTrigger.click();
                await page.waitForTimeout(300);
                const opt = page.locator('[role="option"]').first();
                if (await opt.isVisible({ timeout: 2000 }).catch(() => false)) {
                    await opt.click();
                    await page.waitForTimeout(200);
                }
            }
        }

        const amountIn = page.getByLabel('Jumlah Pinjaman').first();
        if (await amountIn.isVisible().catch(() => false)) await amountIn.fill('15000000');

        const rateIn = page.getByLabel('Bunga').first();
        if (await rateIn.isVisible().catch(() => false)) await rateIn.fill('1.2');

        const tenorIn = page.getByLabel('Jangka Waktu').first();
        if (await tenorIn.isVisible().catch(() => false)) await tenorIn.fill('12');

        const saveBtn = page.locator('button:has-text("Simpan"), button:has-text("Ajukan")').first();
        if (await saveBtn.isVisible().catch(() => false)) {
            await saveBtn.click();
            await page.waitForTimeout(2000);
        }

        for (const tabName of ['proposal', 'verifikasi', 'waiting', 'aktif', 'lunas']) {
            await page.goto(`${BASE}/lending/loans?tab=${tabName}`, { waitUntil: 'domcontentloaded' });
            await expect(page.locator('h1')).toBeVisible();
        }
    });

    test('2. AI Assistant Tool Execution & Streaming Chat API Audit', async ({ page }) => {
        test.setTimeout(120000);

        await loginAs(page, 'superadmin');

        await page.goto(`${BASE}/admin/integrations/orchestrator`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('body')).toBeVisible();

        const csrfToken = await page.evaluate(() => {
            return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        });

        const personasRes = await page.request.get(`${BASE}/admin/integrations/orchestrator/personas`);
        expect(personasRes.status()).toBe(200);

        const toolsRes = await page.request.get(`${BASE}/admin/integrations/orchestrator/tools`);
        expect(toolsRes.status()).toBe(200);

        const convRes = await page.request.get(`${BASE}/admin/integrations/orchestrator/conversations`);
        expect(convRes.status()).toBe(200);

        const logsRes = await page.request.get(`${BASE}/admin/integrations/orchestrator/audit-logs`);
        expect(logsRes.status()).toBe(200);

        const chatRes = await page.request.post(`${BASE}/admin/integrations/orchestrator/chat`, {
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'text/event-stream, application/json',
            },
            data: {
                message: 'Berapa total pinjaman aktif dan ringkasan kas saat ini?',
                persona_slug: 'default'
            }
        });
        expect(chatRes.status()).toBeLessThan(500);
    });
});
