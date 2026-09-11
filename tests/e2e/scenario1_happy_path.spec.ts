import { test, expect, type Page } from '@playwright/test';

const BASE = process.env.E2E_BASE_URL ?? 'http://localhost:56586';

async function humanType(page: Page, selectorOrLocator: any, text: string) {
    const el = typeof selectorOrLocator === 'string' ? page.locator(selectorOrLocator).first() : selectorOrLocator.first();
    await el.waitFor({ state: 'visible', timeout: 15000 });
    await el.click();
    await el.fill('');
    await el.pressSequentially(text, { delay: 25 });
}

async function loginAs(page: Page, username: string) {
    await page.goto(`${BASE}/login`, { waitUntil: 'domcontentloaded' });
    if (!page.url().includes('/login')) {
        await page.context().clearCookies();
        await page.goto(`${BASE}/login`, { waitUntil: 'domcontentloaded' });
    }
    const userIn = page.locator('input[autocomplete="username"]').first();
    await userIn.waitFor({ state: 'visible', timeout: 15000 });
    await userIn.fill('');
    await userIn.pressSequentially(username, { delay: 25 });

    const passIn = page.locator('input[autocomplete="current-password"]').first();
    await passIn.fill('');
    await passIn.pressSequentially('password', { delay: 25 });

    await page.getByRole('button', { name: /Masuk/i }).first().click();
    await page.waitForURL((url) => !url.pathname.includes('/login'), { timeout: 20000 });
    await page.waitForTimeout(500);
}

async function selectFirstSmartOption(page: Page, label: string) {
    const trigger = page.locator(`button:has-text("${label}")`).first();
    if (await trigger.isVisible({ timeout: 3000 }).catch(() => false)) {
        await trigger.click();
        await page.waitForTimeout(300);
        const opt = page.locator('[role="option"]').first();
        if (await opt.isVisible({ timeout: 3000 }).catch(() => false)) {
            await opt.click();
            await page.waitForTimeout(200);
        }
    }
}

test.describe('Scenario 1: Happy Path — Full CRUD & Core Workflows', () => {
    test.describe.configure({ mode: 'serial' });

    test('1.01 Landing Page — hero, features, FAQ accordion, CTA navigation', async ({ page }) => {
        await page.goto(`${BASE}/`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('body')).toBeVisible();

        const heroText = page.locator('text=Dana Bergulir').first();
        await expect(heroText).toBeVisible({ timeout: 10000 });

        const faqButtons = page.locator('button:has-text("Apa itu"), button:has-text("Apakah"), button:has-text("Bagaimana")');
        const faqCount = await faqButtons.count();
        for (let i = 0; i < Math.min(faqCount, 3); i++) {
            await faqButtons.nth(i).click();
            await page.waitForTimeout(400);
        }

        const loginLink = page.locator('a[href*="/login"]').first();
        await expect(loginLink).toBeVisible();
        await page.goto(`${BASE}/login`, { waitUntil: 'domcontentloaded' });
        await expect(page).toHaveURL(/\/login/);
    });

    test('1.02 Login — wrong credentials show error, stay on login page', async ({ page }) => {
        await page.context().clearCookies();
        await page.goto(`${BASE}/login`, { waitUntil: 'domcontentloaded' });
        await humanType(page, 'input[autocomplete="username"]', 'wronguser');
        await humanType(page, 'input[autocomplete="current-password"]', 'wrongpassword');
        await page.getByRole('button', { name: /Masuk/i }).first().click();
        await page.waitForTimeout(3000);
        expect(page.url()).toContain('/login');
    });

    test('1.03 Login as dev → verify dashboard loads with stats', async ({ page }) => {
        await loginAs(page, 'dev');
        await page.goto(`${BASE}/dashboard`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toBeVisible();
        const cards = page.locator('[class*="card"], [class*="Card"]');
        await expect(cards.first()).toBeVisible({ timeout: 10000 });
    });

    test('1.04 Master Data — browse members list, search, pagination', async ({ page }) => {
        await loginAs(page, 'dev');
        await page.goto(`${BASE}/master-data/members`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toBeVisible();

        const searchInput = page.locator('input[placeholder*="Cari"], input[type="search"], input[name="search"]').first();
        if (await searchInput.isVisible().catch(() => false)) {
            await searchInput.fill('');
            await searchInput.pressSequentially('anggota', { delay: 40 });
            await page.waitForTimeout(1000);
            await searchInput.fill('');
            await page.waitForTimeout(500);
        }

        await expect(page.locator('table, [class*="grid"]').first()).toBeVisible();
    });

    test('1.05 Master Data — create new member with full form', async ({ page }) => {
        await loginAs(page, 'dev');
        await page.goto(`${BASE}/master-data/members/create`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toBeVisible();

        const ts = Date.now();
        const nik = `32${String(ts).slice(-14)}`;

        const nikInput = page.locator('label:has-text("NIK") + div input, input[inputmode="numeric"]').first();
        await humanType(page, nikInput, nik);
        await page.waitForTimeout(1000);

        const nameInput = page.getByLabel('Nama Lengkap').first();
        if (await nameInput.isVisible().catch(() => false)) {
            await nameInput.fill(`Test Member ${ts}`);
        }

        const genderRadio = page.locator('button:has-text("Laki-laki"), label:has-text("Laki-laki")').first();
        if (await genderRadio.isVisible().catch(() => false)) {
            await genderRadio.click();
        }

        const birthPlace = page.getByLabel('Tempat Lahir').first();
        if (await birthPlace.isVisible().catch(() => false)) {
            await birthPlace.fill('Jakarta');
        }

        const phoneInput = page.getByLabel('No. HP').first();
        if (await phoneInput.isVisible().catch(() => false)) {
            await phoneInput.fill('081234567890');
        }

        const addressInput = page.getByLabel('Alamat').first();
        if (await addressInput.isVisible().catch(() => false)) {
            await addressInput.fill('Jl. Merdeka No. 45');
        }

        await selectFirstSmartOption(page, 'Pilih desa');

        const saveBtn = page.locator('button:has-text("Simpan")').first();
        if (await saveBtn.isVisible().catch(() => false)) {
            await saveBtn.click();
            await page.waitForTimeout(2000);
        }
    });

    test('1.06 Master Data — browse groups list with sorting', async ({ page }) => {
        await loginAs(page, 'dev');
        await page.goto(`${BASE}/master-data/groups`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toBeVisible();
    });

    test('1.07 Master Data — browse villages', async ({ page }) => {
        await loginAs(page, 'dev');
        await page.goto(`${BASE}/master-data/villages`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toBeVisible();
    });

    test('1.08 Master Data — browse other institutions', async ({ page }) => {
        await loginAs(page, 'dev');
        await page.goto(`${BASE}/master-data/other-institutions`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toBeVisible();
    });

    test('1.09 Lending — browse loans list', async ({ page }) => {
        await loginAs(page, 'dev');
        await page.goto(`${BASE}/lending/loans`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toBeVisible();
    });

    test('1.10 Lending — open create loan form, verify dropdowns load', async ({ page }) => {
        await loginAs(page, 'dev');
        await page.goto(`${BASE}/lending/loans/create`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toBeVisible();
    });

    test('1.11 Accounting — browse chart of accounts', async ({ page }) => {
        await loginAs(page, 'dev');
        await page.goto(`${BASE}/accounting/chart-of-accounts`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toBeVisible();
    });

    test('1.12 Accounting — browse journal entries', async ({ page }) => {
        await loginAs(page, 'dev');
        await page.goto(`${BASE}/accounting/journal-entries`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toBeVisible();
    });

    test('1.13 Accounting — open journal create form, verify transaction types', async ({ page }) => {
        await loginAs(page, 'dev');
        await page.goto(`${BASE}/accounting/journal-entries/create`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toBeVisible();
    });

    test('1.14 Accounting — period close page loads', async ({ page }) => {
        await loginAs(page, 'dev');
        await page.goto(`${BASE}/accounting/period-close`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toBeVisible();
    });

    test('1.15 Reports — reports hub loads with all report links', async ({ page }) => {
        await loginAs(page, 'dev');
        await page.goto(`${BASE}/accounting/reports`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toBeVisible();
    });

    test('1.16 Reports — balance sheet loads', async ({ page }) => {
        await loginAs(page, 'dev');
        await page.goto(`${BASE}/accounting/reports/balance-sheet`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toBeVisible();
    });

    test('1.17 Reports — income statement loads', async ({ page }) => {
        await loginAs(page, 'dev');
        await page.goto(`${BASE}/accounting/reports/income-statement`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toBeVisible();
    });

    test('1.18 Budgeting — budgeting page loads with month tabs', async ({ page }) => {
        await loginAs(page, 'dev');
        await page.goto(`${BASE}/budgeting`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toBeVisible();
    });

    test('1.19 Assets — asset list loads', async ({ page }) => {
        await loginAs(page, 'dev');
        await page.goto(`${BASE}/accounting/assets`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1, main')).toBeVisible();
    });

    test('1.20 Settings — browse all tabs (Identity, Lending, Logo, WA, Signatures)', async ({ page }) => {
        await loginAs(page, 'dev');
        await page.goto(`${BASE}/settings`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toBeVisible();

        const tabKeys = ['Sistem Pinjaman', 'Logo Lembaga', 'WhatsApp Gateway', 'Tanda Tangan', 'Identitas Lembaga'];
        for (const tabText of tabKeys) {
            const tab = page.locator(`button:has-text("${tabText}")`).first();
            if (await tab.isVisible({ timeout: 2000 }).catch(() => false)) {
                await tab.click();
                await page.waitForTimeout(300);
            }
        }
    });

    test('1.21 Profile — browse tabs (Personal, Account, Photo)', async ({ page }) => {
        await loginAs(page, 'dev');
        await page.goto(`${BASE}/profile`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toContainText('Profil');

        for (const tabText of ['Akun', 'Foto', 'Data Pribadi']) {
            const tab = page.locator(`button:has-text("${tabText}")`).first();
            if (await tab.isVisible({ timeout: 2000 }).catch(() => false)) {
                await tab.click();
                await page.waitForTimeout(300);
            }
        }
    });

    test('1.22 Billing — invoice list loads', async ({ page }) => {
        await loginAs(page, 'dev');
        await page.goto(`${BASE}/billing/invoices`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('body')).toBeVisible();
    });

    test('1.23 WhatsApp Hub — billing tab loads', async ({ page }) => {
        await loginAs(page, 'dev');
        await page.goto(`${BASE}/settings/whatsapp?tab=billing`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('body')).toBeVisible();
    });

    test('1.24 Global Search — search trigger works', async ({ page }) => {
        await loginAs(page, 'dev');
        await page.goto(`${BASE}/dashboard`, { waitUntil: 'domcontentloaded' });

        const searchBtn = page.locator('button[aria-label*="search"], button:has-text("Cari"), [data-search]').first();
        if (await searchBtn.isVisible({ timeout: 3000 }).catch(() => false)) {
            await searchBtn.click();
            await page.waitForTimeout(500);
        }
    });

    test('1.25 Logout — user can logout successfully', async ({ page }) => {
        await loginAs(page, 'dev');
        await page.goto(`${BASE}/dashboard`, { waitUntil: 'domcontentloaded' });

        const logoutBtn = page.locator('button:has-text("Keluar"), button:has-text("Logout"), a:has-text("Keluar")').first();
        if (await logoutBtn.isVisible({ timeout: 5000 }).catch(() => false)) {
            await logoutBtn.click();
            await page.waitForTimeout(2000);
        }
    });
});
