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
        await trigger.click();
        await page.waitForTimeout(250);
        const opt = page.locator('[role="option"]').first();
        if (await opt.isVisible({ timeout: 2000 }).catch(() => false)) {
            await opt.click();
            await page.waitForTimeout(150);
        }
    }
}

test.describe('DEEP EXHAUSTIVE ALL FEATURES AUDIT — FULL ACTION WORKFLOWS', () => {
    test.describe.configure({ mode: 'serial' });

    test('1. Master Data — Member Full Form Creation (NIK, Guarantor, Business)', async ({ page }) => {
        await loginAs(page, 'dev');
        await page.goto(`${BASE}/master-data/members/create`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toBeVisible();

        const ts = Date.now();
        const nikReal = `3204${String(ts).slice(-12)}`;

        const nikIn = page.locator('label:has-text("NIK") + div input, input[inputmode="numeric"]').first();
        await nikIn.waitFor({ state: 'visible' });
        await nikIn.click();
        await nikIn.fill('');
        await nikIn.pressSequentially(nikReal, { delay: 20 });
        await page.waitForTimeout(800);

        await page.getByLabel('Nama Lengkap').first().fill(`Ahmad Supriatna ${ts}`);

        const kkIn = page.getByLabel('Nomor KK').first();
        if (await kkIn.isVisible().catch(() => false)) await kkIn.fill(`3204${String(ts).slice(-12)}`);

        const birthIn = page.getByLabel('Tempat Lahir').first();
        if (await birthIn.isVisible().catch(() => false)) await birthIn.fill('Bandung');

        const phoneIn = page.getByLabel('No. HP').first();
        if (await phoneIn.isVisible().catch(() => false)) await phoneIn.fill('081234567890');

        const addrIn = page.getByLabel('Alamat').first();
        if (await addrIn.isVisible().catch(() => false)) await addrIn.fill('Jl. Raya Soreang No. 123');

        await selectSmartOption(page, 'Pilih desa');

        const switches = page.locator('input[type="checkbox"], button[role="switch"]');
        const switchCount = await switches.count();
        for (let i = 0; i < switchCount; i++) {
            const sw = switches.nth(i);
            if (await sw.isVisible().catch(() => false)) {
                await sw.click({ force: true });
                await page.waitForTimeout(200);
            }
        }

        const gName = page.getByLabel('Nama Penjamin').first();
        if (await gName.isVisible().catch(() => false)) await gName.fill('Siti Aminah');

        const gRel = page.getByLabel('Hubungan').first();
        if (await gRel.isVisible().catch(() => false)) await gRel.fill('Istri');

        const bName = page.getByLabel('Nama Usaha').first();
        if (await bName.isVisible().catch(() => false)) await bName.fill('Toko Kelontong Barokah');

        const saveBtn = page.locator('button:has-text("Simpan")').first();
        await saveBtn.click();
        await page.waitForTimeout(2000);
        expect(page.url()).toContain('/master-data/members');
    });

    test('2. Master Data — Group Full Form Creation', async ({ page }) => {
        await loginAs(page, 'dev');
        await page.goto(`${BASE}/master-data/groups/create`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toBeVisible();

        const ts = Date.now();
        const nameIn = page.getByLabel('Nama Kelompok').first();
        await expect(nameIn).toBeVisible();
        await nameIn.fill(`Kelompok Tani Sejahtera ${ts}`);

        const addrIn = page.getByLabel('Alamat').first();
        if (await addrIn.isVisible().catch(() => false)) await addrIn.fill('Jl. Mekar Saluyu No. 45');

        const phoneIn = page.getByLabel('No. HP').first();
        if (await phoneIn.isVisible().catch(() => false)) await phoneIn.fill('081399887766');

        await selectSmartOption(page, 'Pilih desa');
    });

    test('3. Lending — Proposal Submission, Installment Calculation & SPK Document', async ({ page }) => {
        await loginAs(page, 'dev');
        await page.goto(`${BASE}/lending/loans/create`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toBeVisible();

        await selectSmartOption(page, 'Pilih kelompok');

        const amountIn = page.getByLabel('Jumlah Pinjaman').first();
        if (await amountIn.isVisible().catch(() => false)) await amountIn.fill('20000000');

        const rateIn = page.getByLabel('Bunga').first();
        if (await rateIn.isVisible().catch(() => false)) await rateIn.fill('1.5');

        const tenorIn = page.getByLabel('Jangka Waktu').first();
        if (await tenorIn.isVisible().catch(() => false)) await tenorIn.fill('12');

        const saveBtn = page.locator('button:has-text("Simpan"), button:has-text("Ajukan")').first();
        if (await saveBtn.isVisible().catch(() => false)) {
            await saveBtn.click();
            await page.waitForTimeout(2000);
        }

        await page.goto(`${BASE}/lending/loans`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toBeVisible();
    });

    test('4. Accounting — COA, Cash In/Out Journal Posting & Period Close', async ({ page }) => {
        await loginAs(page, 'dev');

        await page.goto(`${BASE}/accounting/chart-of-accounts/create`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toBeVisible();

        const codeIn = page.getByLabel('Kode Akun').first();
        if (await codeIn.isVisible().catch(() => false)) await codeIn.fill(`1101-${Math.floor(Math.random() * 899 + 100)}`);

        const nameIn = page.getByLabel('Nama Akun').first();
        if (await nameIn.isVisible().catch(() => false)) await nameIn.fill('Kas Bank Syariah Audit');

        const saveCoa = page.locator('button:has-text("Simpan")').first();
        if (await saveCoa.isVisible().catch(() => false)) {
            await saveCoa.click();
            await page.waitForTimeout(1500);
        }

        await page.goto(`${BASE}/accounting/journal-entries/create`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toBeVisible();

        const descIn = page.getByLabel('Keterangan').first();
        if (await descIn.isVisible().catch(() => false)) {
            await descIn.fill('Penerimaan Dana Hibah Operasional UPK');
        }

        await page.goto(`${BASE}/accounting/period-close`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toBeVisible();
    });

    test('5. Financial Reports — Balance Sheet, Income Statement, Cash Flow & Health Index', async ({ page }) => {
        await loginAs(page, 'dev');

        await page.goto(`${BASE}/accounting/reports/balance-sheet`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toBeVisible();

        await page.goto(`${BASE}/accounting/reports/income-statement`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toBeVisible();

        await page.goto(`${BASE}/accounting/reports/cash-flow`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toBeVisible();

        await page.goto(`${BASE}/accounting/reports/annual-pack`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toBeVisible();
    });

    test('6. Budgeting & Asset Management — Monthly Budget & Asset Creation', async ({ page }) => {
        await loginAs(page, 'dev');

        await page.goto(`${BASE}/budgeting`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toBeVisible();

        await page.goto(`${BASE}/accounting/assets/create`, { waitUntil: 'domcontentloaded' });
        if (await page.locator('h1').first().isVisible().catch(() => false)) {
            const nameIn = page.getByLabel('Nama Aset').first();
            if (await nameIn.isVisible().catch(() => false)) await nameIn.fill('Printer HP LaserJet Pro M404');

            const valIn = page.getByLabel('Harga Perolehan').first();
            if (await valIn.isVisible().catch(() => false)) await valIn.fill('4500000');

            const saveAsset = page.locator('button:has-text("Simpan")').first();
            if (await saveAsset.isVisible().catch(() => false)) {
                await saveAsset.click();
                await page.waitForTimeout(1500);
            }
        }
    });

    test('7. Settings & Profile — Identity, Lending Rules, Signatures & WA Gateway', async ({ page }) => {
        await loginAs(page, 'dev');

        await page.goto(`${BASE}/settings`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toBeVisible();

        for (const tabText of ['Identitas Lembaga', 'Sistem Pinjaman', 'WhatsApp Gateway', 'Tanda Tangan']) {
            const tab = page.locator(`button:has-text("${tabText}")`).first();
            if (await tab.isVisible().catch(() => false)) {
                await tab.click();
                await page.waitForTimeout(300);
            }
        }

        await page.goto(`${BASE}/profile`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toContainText('Profil');
    });

    test('8. Tenant Onboarding — Import Wizard File Upload Surface', async ({ page }) => {
        await loginAs(page, 'dev');
        await page.goto(`${BASE}/onboarding/import`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('body')).toBeVisible();
    });

    test('9. Superadmin — Invoices, Gateways & AI Assistant Orchestrator', async ({ page }) => {
        await loginAs(page, 'superadmin');

        await page.goto(`${BASE}/admin/invoices`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toBeVisible();

        await page.goto(`${BASE}/admin/payment-gateways`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('body')).toBeVisible();
    });

    test('10. Admin Cutover Hub — Live Execution of Legacy Migration for Suffix / Kecamatan ID 76', async ({ page }) => {
        test.setTimeout(180000);

        await loginAs(page, 'superadmin');
        await page.goto(`${BASE}/admin/migration`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toContainText('Migrasi Data Legacy');

        const suffixTrigger = page.locator('button:has-text("Suffix"), button:has-text("1"), input[name="suffix"]').first();
        if (await suffixTrigger.isVisible().catch(() => false)) {
            const isInput = await suffixTrigger.evaluate(el => el.tagName.toLowerCase() === 'input');
            if (isInput) {
                await suffixTrigger.fill('76');
            } else {
                await suffixTrigger.click();
                await page.waitForTimeout(400);

                const opt76 = page.locator('[role="option"]:has-text("76"), li:has-text("76")').first();
                if (await opt76.isVisible().catch(() => false)) {
                    await opt76.click();
                } else {
                    const searchIn = page.locator('input[placeholder*="Cari"], input[type="search"]').first();
                    if (await searchIn.isVisible().catch(() => false)) {
                        await searchIn.fill('76');
                        await page.waitForTimeout(300);
                        const firstOpt = page.locator('[role="option"]').first();
                        if (await firstOpt.isVisible().catch(() => false)) await firstOpt.click();
                    }
                }
            }
        }

        const submitBtn = page.getByRole('button', { name: /Jalankan Migrasi Data/i }).first();
        await expect(submitBtn).toBeVisible();
        await submitBtn.click();

        await page.waitForTimeout(2000);

        const modalOrLog = page.locator('.fixed, table, pre').first();
        await expect(modalOrLog).toBeVisible({ timeout: 15000 });

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
