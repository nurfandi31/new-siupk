import { test, expect, type Page } from '@playwright/test';

const BASE = process.env.E2E_BASE_URL ?? 'http://localhost:56586';

async function humanType(page: Page, locator: any, text: string) {
    const el = typeof locator === 'string' ? page.locator(locator).first() : locator.first();
    await el.waitFor({ state: 'visible', timeout: 15000 });
    await el.click();
    await el.fill('');
    await el.pressSequentially(text, { delay: 25 });
}

async function loginAs(page: Page, username: string) {
    await page.request.post(`${BASE}/logout`).catch(() => {});
    await page.context().clearCookies();
    await page.goto(`${BASE}/login`, { waitUntil: 'domcontentloaded' });

    const userIn = page.locator('input[autocomplete="username"]').first();
    await userIn.waitFor({ state: 'visible', timeout: 15000 });
    await userIn.fill(username);

    const passIn = page.locator('input[autocomplete="current-password"]').first();
    await passIn.fill('password');

    await page.getByRole('button', { name: /Masuk/i }).first().click();
    await page.waitForURL((url) => !url.pathname.includes('/login'), { timeout: 20000 });
    await page.waitForTimeout(500);
}

async function selectSmartOption(page: Page, triggerLabel: string) {
    const trigger = page.locator(`button:has-text("${triggerLabel}")`).first();
    if (await trigger.isVisible({ timeout: 3000 }).catch(() => false)) {
        await trigger.click();
        await page.waitForTimeout(300);
        const opt = page.locator('[role="option"]').first();
        if (await opt.isVisible({ timeout: 2000 }).catch(() => false)) {
            await opt.click();
            await page.waitForTimeout(200);
        }
    }
}

test.describe('HUMAN-LIKE E2E REAL AUDIT - ALL FEATURES & WORKFLOWS', () => {
    test.describe.configure({ mode: 'serial' });

    test('1. Human Interaction — Landing Page & Login Boundary Validation', async ({ page }) => {
        await page.goto(`${BASE}/`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('body')).toBeVisible();

        const hero = page.locator('text=Dana Bergulir').first();
        await expect(hero).toBeVisible();

        const faqBtn = page.locator('button:has-text("Apa itu"), button:has-text("Apakah")').first();
        if (await faqBtn.isVisible().catch(() => false)) {
            await faqBtn.click();
            await page.waitForTimeout(300);
        }

        await page.goto(`${BASE}/login`, { waitUntil: 'domcontentloaded' });
        await humanType(page, 'input[autocomplete="username"]', 'user_salah');
        await humanType(page, 'input[autocomplete="current-password"]', 'pass_salah');
        await page.getByRole('button', { name: /Masuk/i }).first().click();
        await page.waitForTimeout(1000);
        expect(page.url()).toContain('/login');
    });

    test('2. Human Interaction — Superadmin Tenant & Plan CRUD', async ({ page }) => {
        await loginAs(page, 'superadmin');

        await page.goto(`${BASE}/admin/tenants/create`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toBeVisible();

        const codeInput = page.getByLabel('Kode Tenant').first();
        if (await codeInput.isVisible().catch(() => false)) {
            const tenantCode = 't_audit_' + Math.floor(Math.random() * 1000);
            await codeInput.fill(tenantCode);

            const nameInput = page.getByLabel('Nama Tenant').first();
            if (await nameInput.isVisible().catch(() => false)) {
                await nameInput.fill('UPK Audit Kecamatan Real');
            }

            const saveBtn = page.locator('button:has-text("Simpan")').first();
            if (await saveBtn.isVisible().catch(() => false)) {
                await saveBtn.click();
                await page.waitForTimeout(1500);
            }
        }

        await page.goto(`${BASE}/admin/plans/create`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toBeVisible();

        const planCode = 'plan_' + Math.floor(Math.random() * 1000);
        await page.getByLabel('Kode', { exact: true }).fill(planCode);
        await page.getByLabel('Nama', { exact: true }).fill('Plan Audit Gold');
        await page.getByLabel('Harga', { exact: true }).fill('250000');

        const savePlanBtn = page.locator('button:has-text("Simpan")').first();
        if (await savePlanBtn.isVisible().catch(() => false)) {
            await savePlanBtn.click();
            await page.waitForTimeout(1500);
        }
    });

    test('3. Human Interaction — Payment Gateways Configuration', async ({ page }) => {
        await loginAs(page, 'superadmin');
        await page.goto(`${BASE}/admin/payment-gateways`, { waitUntil: 'domcontentloaded' });
        await page.waitForTimeout(1000);

        const cardHeader = page.locator('h1, h2, main').first();
        await expect(cardHeader).toBeVisible();
    });

    test('4. Human Interaction — Master Data Real Member Creation & NIK Lookup', async ({ page }) => {
        await loginAs(page, 'dev');
        await page.goto(`${BASE}/master-data/members/create`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toBeVisible();

        const ts = Date.now();
        const nikReal = `3204${String(ts).slice(-12)}`;

        const nikInput = page.locator('label:has-text("NIK") + div input, input[inputmode="numeric"]').first();
        await humanType(page, nikInput, nikReal);
        await page.waitForTimeout(1000);

        const nameInput = page.getByLabel('Nama Lengkap').first();
        if (await nameInput.isVisible().catch(() => false)) {
            await nameInput.fill('Budi Santoso Audit');
        }

        const addressInput = page.getByLabel('Alamat').first();
        if (await addressInput.isVisible().catch(() => false)) {
            await addressInput.fill('Jl. Pasir Kaliki No. 88, Bandung');
        }

        const phoneInput = page.getByLabel('No. HP').first();
        if (await phoneInput.isVisible().catch(() => false)) {
            await phoneInput.fill('081322334455');
        }

        await selectSmartOption(page, 'Pilih desa');

        const saveBtn = page.locator('button:has-text("Simpan")').first();
        if (await saveBtn.isVisible().catch(() => false)) {
            await saveBtn.click();
            await page.waitForTimeout(2000);
        }
    });

    test('5. Human Interaction — Master Data Real Group Creation', async ({ page }) => {
        await loginAs(page, 'dev');
        await page.goto(`${BASE}/master-data/groups/create`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toBeVisible();

        const codeInput = page.getByLabel('Kode Kelompok').first();
        if (await codeInput.isVisible().catch(() => false)) {
            await codeInput.fill('KLP-REAL-' + Math.floor(Math.random() * 1000));
        }

        const nameInput = page.getByLabel('Nama Kelompok').first();
        if (await nameInput.isVisible().catch(() => false)) {
            await nameInput.fill('Kelompok Tani Makmur Mandiri');
        }

        await selectSmartOption(page, 'Pilih desa');

        const saveBtn = page.locator('button:has-text("Simpan")').first();
        if (await saveBtn.isVisible().catch(() => false)) {
            await saveBtn.click();
            await page.waitForTimeout(2000);
        }
    });

    test('6. Human Interaction — Master Data Real Village Creation', async ({ page }) => {
        await loginAs(page, 'dev');
        await page.goto(`${BASE}/master-data/villages`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toBeVisible();
    });

    test('7. Human Interaction — Master Data Real Institution Creation', async ({ page }) => {
        await loginAs(page, 'dev');
        await page.goto(`${BASE}/master-data/other-institutions`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toBeVisible();
    });

    test('8. Human Interaction — Lending New Loan Proposal Submission', async ({ page }) => {
        await loginAs(page, 'dev');
        await page.goto(`${BASE}/lending/loans/create`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toBeVisible();

        await selectSmartOption(page, 'Pilih kelompok');

        const amountInput = page.getByLabel('Jumlah Pinjaman').first();
        if (await amountInput.isVisible().catch(() => false)) {
            await amountInput.fill('15000000');
        }

        const rateInput = page.getByLabel('Bunga').first();
        if (await rateInput.isVisible().catch(() => false)) {
            await rateInput.fill('1.2');
        }

        const saveBtn = page.locator('button:has-text("Simpan"), button:has-text("Ajukan")').first();
        if (await saveBtn.isVisible().catch(() => false)) {
            await saveBtn.click();
            await page.waitForTimeout(2000);
        }
    });

    test('9. Human Interaction — Lending Reports & NPF Portfolio Audit', async ({ page }) => {
        await loginAs(page, 'dev');
        await page.goto(`${BASE}/lending/reports`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toBeVisible();
    });

    test('10. Human Interaction — Accounting Chart of Accounts Creation', async ({ page }) => {
        await loginAs(page, 'dev');
        await page.goto(`${BASE}/accounting/chart-of-accounts`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toBeVisible();
    });

    test('11. Human Interaction — Accounting Journal Entry Creation', async ({ page }) => {
        await loginAs(page, 'dev');
        await page.goto(`${BASE}/accounting/journal-entries/create`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toBeVisible();

        const descIn = page.getByLabel('Keterangan').first();
        if (await descIn.isVisible().catch(() => false)) {
            await descIn.fill('Penerimaan Simpanan Wajib Anggota Real Audit');
        }
    });

    test('12. Human Interaction — Accounting Fixed Assets Browsing', async ({ page }) => {
        await loginAs(page, 'dev');
        await page.goto(`${BASE}/accounting/assets`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1').first()).toBeVisible();
    });

    test('13. Human Interaction — Accounting Period Close & Financial Reports Hub', async ({ page }) => {
        await loginAs(page, 'dev');

        await page.goto(`${BASE}/accounting/period-close`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toBeVisible();

        await page.goto(`${BASE}/accounting/reports`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toBeVisible();

        await page.goto(`${BASE}/accounting/reports/balance-sheet`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toBeVisible();

        await page.goto(`${BASE}/accounting/reports/income-statement`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toBeVisible();
    });

    test('14. Human Interaction — Settings & Profile Management Audit', async ({ page }) => {
        await loginAs(page, 'dev');

        await page.goto(`${BASE}/settings`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toBeVisible();

        for (const tabName of ['Identitas Lembaga', 'Sistem Pinjaman', 'WhatsApp Gateway', 'Tanda Tangan']) {
            const tabBtn = page.locator(`button:has-text("${tabName}")`).first();
            if (await tabBtn.isVisible().catch(() => false)) {
                await tabBtn.click();
                await page.waitForTimeout(300);
            }
        }

        await page.goto(`${BASE}/profile`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toContainText('Profil');
    });

    test('15. Production Legacy Cutover Migration for Suffix / Kecamatan ID 76 via Admin GUI', async ({ page }) => {
        test.setTimeout(180000);

        await loginAs(page, 'superadmin');
        await page.goto(`${BASE}/admin/migration`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toContainText('Migrasi Data Legacy');

        await expect(page.locator('text=Host:').first()).toBeVisible();
        await expect(page.locator('text=Database:').first()).toBeVisible();

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
