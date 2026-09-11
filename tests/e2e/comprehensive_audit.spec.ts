import { test, expect, type Page } from '@playwright/test';

const BASE = process.env.E2E_BASE_URL ?? 'http://localhost:56586';
const TIMEOUT = 30000;

async function loginAs(page: Page, username: string, password: string = 'password'): Promise<void> {
    await page.goto(BASE + '/login', { waitUntil: 'domcontentloaded', timeout: TIMEOUT });
    if (!page.url().includes('/login')) return;
    const userIn = page.locator('input[autocomplete="username"]').first();
    await userIn.waitFor({ state: 'visible', timeout: 15000 });
    await userIn.fill('');
    await userIn.pressSequentially(username, { delay: 30 });
    const passIn = page.locator('input[autocomplete="current-password"]').first();
    await passIn.fill('');
    await passIn.pressSequentially(password, { delay: 30 });
    await page.getByRole('button', { name: /Masuk/i }).first().click();
    await page.waitForURL((url) => !url.pathname.includes('/login'), { timeout: TIMEOUT });
    await page.waitForTimeout(500);
}

async function ensureLoggedOut(page: Page): Promise<void> {
    await page.context().clearCookies();
}

async function go(page: Page, path: string): Promise<void> {
    await page.goto(BASE + path, { waitUntil: 'domcontentloaded', timeout: TIMEOUT });
    await page.waitForTimeout(300);
}

async function noErr(page: Page): Promise<void> {
    const body = await page.locator('body').textContent();
    expect(body).not.toContain('500 | Server Error');
    expect(body).not.toContain('Whoops, looks like');
    expect(body).not.toContain('ErrorException');
    expect(body).not.toContain('SQLSTATE');
}

async function h1(page: Page, text?: string | RegExp): Promise<void> {
    const el = page.locator('h1').first();
    await expect(el).toBeVisible({ timeout: 10000 });
    if (text) await expect(el).toContainText(text);
}

/* ---- SECTION 1: PUBLIC PAGES ---- */
test.describe('1. Public Pages', () => {
    test('1.1 Landing page hero, FAQ, CTA', async ({ page }) => {
        await go(page, '/');
        await noErr(page);
        await expect(page.locator('body')).toContainText('Dana Bergulir');
        const faq = page.locator('button:has-text("Apa itu"), button:has-text("Apakah"), button:has-text("Bagaimana")');
        const c = await faq.count();
        for (let i = 0; i < Math.min(c, 3); i++) { await faq.nth(i).click(); await page.waitForTimeout(300); }
        await expect(page.locator('a[href*="/login"]').first()).toBeVisible();
    });

    test('1.2 Login page elements', async ({ page }) => {
        await go(page, '/login');
        await noErr(page);
        await expect(page.getByRole('heading', { name: 'Masuk ke Akun Anda' })).toBeVisible();
        await expect(page.locator('input[autocomplete="username"]')).toBeVisible();
        await expect(page.locator('input[autocomplete="current-password"]')).toBeVisible();
        await expect(page.getByRole('button', { name: /Masuk/i })).toBeVisible();
    });

    test('1.3 Wrong credentials', async ({ page }) => {
        await ensureLoggedOut(page);
        await go(page, '/login');
        await page.locator('input[autocomplete="username"]').first().fill('wronguser');
        await page.locator('input[autocomplete="current-password"]').first().fill('wrongpassword');
        await page.getByRole('button', { name: /Masuk/i }).first().click();
        await page.waitForTimeout(2000);
        expect(page.url()).toContain('/login');
    });

    test('1.4 Superadmin -> /admin', async ({ page }) => {
        await ensureLoggedOut(page);
        await loginAs(page, 'superadmin');
        expect(page.url()).toContain('/admin');
        await noErr(page);
    });

    test('1.5 Tenant user -> /dashboard', async ({ page }) => {
        await ensureLoggedOut(page);
        await loginAs(page, 'dev');
        expect(page.url()).toContain('/dashboard');
        await noErr(page);
    });
});

/* ---- SECTION 2: ADMIN SUITE ---- */
test.describe('2. Admin Suite', () => {
    test.describe.configure({ mode: 'serial' });
    test.beforeEach(async ({ page }) => { await loginAs(page, 'superadmin'); });

    test('2.1 Admin Dashboard', async ({ page }) => {
        await go(page, '/admin'); await noErr(page); await h1(page);
    });

    test('2.2 Admin Tenants list+search', async ({ page }) => {
        await go(page, '/admin/tenants'); await noErr(page); await h1(page);
        await expect(page.locator('table').first()).toBeVisible({ timeout: 10000 });
        const s = page.locator('input[placeholder*="Cari"], input[type="search"]').first();
        if (await s.isVisible({ timeout: 3000 }).catch(() => false)) {
            await s.fill('local'); await page.waitForTimeout(1000);
        }
    });

    test('2.3 Admin Tenants create form', async ({ page }) => {
        await go(page, '/admin/tenants/create'); await noErr(page);
        await h1(page, /Tambah Tenant/);
        await expect(page.getByLabel('Nama Tenant')).toBeVisible();
    });

    test('2.4 Admin Plans list', async ({ page }) => {
        await go(page, '/admin/plans'); await noErr(page); await h1(page);
    });

    test('2.5 Admin Plans create CRUD', async ({ page }) => {
        await go(page, '/admin/plans/create'); await noErr(page);
        await h1(page, /Tambah Plan/);
        const code = 'test_' + Date.now();
        await page.getByLabel('Kode').fill(code);
        await page.getByLabel('Nama').fill('Plan E2E ' + code);
        await page.getByLabel('Harga').fill('100000');
        await page.getByRole('button', { name: /Simpan/i }).first().click();
        await page.waitForTimeout(2000);
        await noErr(page);
    });

    test('2.6 Admin Invoices', async ({ page }) => {
        await go(page, '/admin/invoices'); await noErr(page); await h1(page);
    });

    test('2.7 Admin Integrations', async ({ page }) => {
        await go(page, '/admin/integrations'); await noErr(page); await h1(page);
    });

    test('2.8 Admin Migration Hub', async ({ page }) => {
        await go(page, '/admin/migration'); await noErr(page);
        await h1(page, /Migrasi Data Legacy/);
        await expect(page.locator('form').first()).toBeVisible();
    });
});

/* ---- SECTION 3: TENANT DASHBOARD ---- */
test.describe('3. Tenant Dashboard', () => {
    test.beforeEach(async ({ page }) => { await loginAs(page, 'dev'); });

    test('3.1 Dashboard stats', async ({ page }) => {
        await go(page, '/dashboard'); await noErr(page); await h1(page);
    });

    test('3.2 Pipeline modal proposal', async ({ page }) => {
        await go(page, '/dashboard?pipeline=proposal'); await noErr(page);
    });

    test('3.3 Pipeline modal aktif', async ({ page }) => {
        await go(page, '/dashboard?pipeline=aktif'); await noErr(page);
    });
});

/* ---- SECTION 4: MASTER DATA MEMBERS ---- */
test.describe('4. Members', () => {
    test.describe.configure({ mode: 'serial' });
    test.beforeEach(async ({ page }) => { await loginAs(page, 'dev'); });

    test('4.1 Members list', async ({ page }) => {
        await go(page, '/master-data/members'); await noErr(page); await h1(page);
        await expect(page.locator('table').first()).toBeVisible({ timeout: 10000 });
    });

    test('4.2 Members search', async ({ page }) => {
        await go(page, '/master-data/members');
        const s = page.locator('input[placeholder*="Cari"], input[type="search"]').first();
        if (await s.isVisible({ timeout: 3000 }).catch(() => false)) {
            await s.fill('test'); await page.waitForTimeout(1000); await noErr(page);
        }
    });

    test('4.3 Members create form', async ({ page }) => {
        await go(page, '/master-data/members/create'); await noErr(page); await h1(page);
    });

    test('4.4 Members create real data', async ({ page }) => {
        await go(page, '/master-data/members/create'); await noErr(page);
        const nik = page.getByLabel(/NIK/);
        await nik.fill('9999888877776666');
        await page.waitForTimeout(1500);
        const name = page.getByLabel(/Nama/i).first();
        if (await name.isVisible().catch(() => false)) await name.fill('Test Member E2E Audit');
        const g = page.locator('button:has-text("Laki-laki"), label:has-text("Laki-laki")').first();
        if (await g.isVisible({ timeout: 2000 }).catch(() => false)) await g.click();
        const ph = page.getByLabel(/Telepon|HP|Phone/i).first();
        if (await ph.isVisible({ timeout: 2000 }).catch(() => false)) await ph.fill('081234567890');
        await page.getByRole('button', { name: /Simpan/i }).first().click();
        await page.waitForTimeout(3000);
        await noErr(page);
    });

    test('4.5 Members detail view', async ({ page }) => {
        await go(page, '/master-data/members');
        const link = page.locator('table tbody tr a, table tbody tr td a').first();
        if (await link.isVisible({ timeout: 5000 }).catch(() => false)) {
            await link.click(); await page.waitForTimeout(1000); await noErr(page);
        }
    });
});

/* ---- SECTION 5: GROUPS ---- */
test.describe('5. Groups', () => {
    test.describe.configure({ mode: 'serial' });
    test.beforeEach(async ({ page }) => { await loginAs(page, 'dev'); });

    test('5.1 Groups list', async ({ page }) => {
        await go(page, '/master-data/groups'); await noErr(page); await h1(page);
    });

    test('5.2 Groups create form', async ({ page }) => {
        await go(page, '/master-data/groups/create'); await noErr(page); await h1(page);
    });

    test('5.3 Groups detail', async ({ page }) => {
        await go(page, '/master-data/groups');
        const link = page.locator('table tbody tr a, table tbody tr td a').first();
        if (await link.isVisible({ timeout: 5000 }).catch(() => false)) {
            await link.click(); await page.waitForTimeout(1000); await noErr(page);
        }
    });
});

/* ---- SECTION 6: VILLAGES ---- */
test.describe('6. Villages', () => {
    test.beforeEach(async ({ page }) => { await loginAs(page, 'dev'); });

    test('6.1 Villages list', async ({ page }) => {
        await go(page, '/master-data/villages'); await noErr(page); await h1(page);
    });

    test('6.2 Villages create form', async ({ page }) => {
        await go(page, '/master-data/villages/create'); await noErr(page);
    });
});

/* ---- SECTION 7: INSTITUTIONS ---- */
test.describe('7. Institutions', () => {
    test.beforeEach(async ({ page }) => { await loginAs(page, 'dev'); });

    test('7.1 Institutions list', async ({ page }) => {
        await go(page, '/master-data/institutions'); await noErr(page); await h1(page);
    });

    test('7.2 Institutions create form', async ({ page }) => {
        await go(page, '/master-data/institutions/create'); await noErr(page);
    });
});

/* ---- SECTION 8: LOANS ---- */
test.describe('8. Loans', () => {
    test.describe.configure({ mode: 'serial' });
    test.beforeEach(async ({ page }) => { await loginAs(page, 'dev'); });

    test('8.1 Proposal tab', async ({ page }) => {
        await go(page, '/lending/loans?tab=proposal'); await noErr(page); await h1(page);
    });
    test('8.2 Verifikasi tab', async ({ page }) => {
        await go(page, '/lending/loans?tab=verifikasi'); await noErr(page);
    });
    test('8.3 Waiting tab', async ({ page }) => {
        await go(page, '/lending/loans?tab=waiting'); await noErr(page);
    });
    test('8.4 Aktif tab', async ({ page }) => {
        await go(page, '/lending/loans?tab=aktif'); await noErr(page);
    });
    test('8.5 Lunas tab', async ({ page }) => {
        await go(page, '/lending/loans?tab=lunas'); await noErr(page);
    });
    test('8.6 Create form', async ({ page }) => {
        await go(page, '/lending/loans/create'); await noErr(page); await h1(page);
    });
    test('8.7 Search aktif tab', async ({ page }) => {
        await go(page, '/lending/loans?tab=aktif');
        const s = page.locator('input[placeholder*="Cari"], input[type="search"]').first();
        if (await s.isVisible({ timeout: 3000 }).catch(() => false)) {
            await s.fill('test'); await page.waitForTimeout(1000); await noErr(page);
        }
    });
    test('8.8 Detail view', async ({ page }) => {
        await go(page, '/lending/loans?tab=aktif');
        const link = page.locator('a[href*="/lending/loans/"]').first();
        if (await link.isVisible({ timeout: 5000 }).catch(() => false)) {
            await link.click(); await page.waitForTimeout(1000); await noErr(page);
        }
    });
});

/* ---- SECTION 9: LENDING REPORTS ---- */
test.describe('9. Lending Reports', () => {
    test.beforeEach(async ({ page }) => { await loginAs(page, 'dev'); });

    test('9.1 Portfolio', async ({ page }) => {
        await go(page, '/lending/reports/portfolio'); await noErr(page); await h1(page);
    });
    test('9.2 Schedule vs Actual', async ({ page }) => {
        await go(page, '/lending/reports/schedule-vs-actual'); await noErr(page);
    });
    test('9.3 LPP Desa', async ({ page }) => {
        await go(page, '/lending/reports/lpp-desa'); await noErr(page);
    });
    test('9.4 LPP Kelompok', async ({ page }) => {
        await go(page, '/lending/reports/lpp-kelompok'); await noErr(page);
    });
    test('9.5 Kolektibilitas', async ({ page }) => {
        await go(page, '/lending/reports/kolek-desa'); await noErr(page);
    });
    test('9.6 CKPN', async ({ page }) => {
        await go(page, '/lending/reports/cadangan-penghapusan'); await noErr(page);
    });
});

/* ---- SECTION 10-14: ACCOUNTING ---- */
test.describe('10. Accounting COA', () => {
    test.beforeEach(async ({ page }) => { await loginAs(page, 'dev'); });
    test('10.1 COA list', async ({ page }) => {
        await go(page, '/accounting/coa'); await noErr(page); await h1(page);
    });
});

test.describe('11. Journal Entries', () => {
    test.beforeEach(async ({ page }) => { await loginAs(page, 'dev'); });
    test('11.1 Create form', async ({ page }) => {
        await go(page, '/accounting/journal-entries/create'); await noErr(page); await h1(page);
    });
    test('11.2 Select transaction type', async ({ page }) => {
        await go(page, '/accounting/journal-entries/create'); await noErr(page);
        const sel = page.locator('button:has-text("Pilih"), button:has-text("Jenis Transaksi")').first();
        if (await sel.isVisible({ timeout: 3000 }).catch(() => false)) {
            await sel.click(); await page.waitForTimeout(300);
            const opt = page.locator('[role="option"]').first();
            if (await opt.isVisible({ timeout: 2000 }).catch(() => false)) {
                await opt.click(); await page.waitForTimeout(500);
            }
        }
    });
});

test.describe('12. Journals Browse', () => {
    test.beforeEach(async ({ page }) => { await loginAs(page, 'dev'); });
    test('12.1 Journal list', async ({ page }) => {
        await go(page, '/accounting/journals'); await noErr(page); await h1(page);
    });
});

test.describe('13. Period Close', () => {
    test.beforeEach(async ({ page }) => { await loginAs(page, 'dev'); });
    test('13.1 Period close', async ({ page }) => {
        await go(page, '/accounting/period-close'); await noErr(page); await h1(page);
    });
});

test.describe('14. Tax Estimate', () => {
    test.beforeEach(async ({ page }) => { await loginAs(page, 'dev'); });
    test('14.1 Tax estimate', async ({ page }) => {
        await go(page, '/accounting/tax-estimate'); await noErr(page); await h1(page);
    });
});

/* ---- SECTION 15: ACCOUNTING REPORTS ---- */
test.describe('15. Accounting Reports', () => {
    test.beforeEach(async ({ page }) => { await loginAs(page, 'dev'); });

    test('15.1 Reports hub', async ({ page }) => { await go(page, '/accounting/reports'); await noErr(page); });
    test('15.2 Trial Balance', async ({ page }) => { await go(page, '/accounting/reports/trial-balance'); await noErr(page); });
    test('15.3 Balance Sheet', async ({ page }) => { await go(page, '/accounting/reports/balance-sheet'); await noErr(page); });
    test('15.4 Income Statement', async ({ page }) => { await go(page, '/accounting/reports/income-statement'); await noErr(page); });
    test('15.5 General Ledger', async ({ page }) => { await go(page, '/accounting/reports/general-ledger'); await noErr(page); });
    test('15.6 Cash Flow', async ({ page }) => { await go(page, '/accounting/reports/cash-flow'); await noErr(page); });
    test('15.7 Equity Change', async ({ page }) => { await go(page, '/accounting/reports/equity-change'); await noErr(page); });
    test('15.8 CALK', async ({ page }) => { await go(page, '/accounting/reports/calk'); await noErr(page); });
    test('15.9 Journal Report', async ({ page }) => { await go(page, '/accounting/reports/journal'); await noErr(page); });
    test('15.10 Financial Health', async ({ page }) => { await go(page, '/accounting/reports/financial-health'); await noErr(page); });
    test('15.11 Annual Pack', async ({ page }) => { await go(page, '/accounting/reports/annual-pack'); await noErr(page); });
});

/* ---- SECTION 16-17: ASSETS & BUDGETING ---- */
test.describe('16. Assets', () => {
    test.beforeEach(async ({ page }) => { await loginAs(page, 'dev'); });
    test('16.1 Assets list', async ({ page }) => { await go(page, '/assets'); await noErr(page); await h1(page); });
});

test.describe('17. Budgeting', () => {
    test.beforeEach(async ({ page }) => { await loginAs(page, 'dev'); });
    test('17.1 Budgeting page', async ({ page }) => { await go(page, '/budgeting'); await noErr(page); await h1(page); });
});

/* ---- SECTION 18: SETTINGS ---- */
test.describe('18. Settings', () => {
    test.describe.configure({ mode: 'serial' });
    test.beforeEach(async ({ page }) => { await loginAs(page, 'dev'); });

    test('18.1 Settings page', async ({ page }) => {
        await go(page, '/settings'); await noErr(page); await h1(page, /Pengaturan/);
    });
    test('18.2 Switch tabs', async ({ page }) => {
        await go(page, '/settings'); await noErr(page);
        for (const t of ['Sistem Pinjaman', 'Logo Lembaga', 'WhatsApp Gateway', 'Tanda Tangan']) {
            const btn = page.locator('button:has-text("' + t + '")').first();
            if (await btn.isVisible({ timeout: 2000 }).catch(() => false)) {
                await btn.click(); await page.waitForTimeout(300); await noErr(page);
            }
        }
    });
});

/* ---- SECTION 19: PROFILE ---- */
test.describe('19. Profile', () => {
    test.beforeEach(async ({ page }) => { await loginAs(page, 'dev'); });

    test('19.1 Profile page', async ({ page }) => {
        await go(page, '/profile'); await noErr(page); await h1(page, /Profil/);
    });
    test('19.2 Profile tabs', async ({ page }) => {
        await go(page, '/profile');
        for (const t of ['Akun', 'Foto']) {
            const btn = page.locator('button:has-text("' + t + '")').first();
            if (await btn.isVisible({ timeout: 2000 }).catch(() => false)) {
                await btn.click(); await page.waitForTimeout(300); await noErr(page);
            }
        }
    });
});

/* ---- SECTION 20: BILLING & NOTIFICATIONS ---- */
test.describe('20. Billing & Notifications', () => {
    test.beforeEach(async ({ page }) => { await loginAs(page, 'dev'); });
    test('20.1 Billing invoices', async ({ page }) => { await go(page, '/billing/invoices'); await noErr(page); });
    test('20.2 WhatsApp Hub billing tab', async ({ page }) => { await go(page, '/settings/whatsapp?tab=billing'); await noErr(page); });
});

/* ---- SECTION 21: ONBOARDING ---- */
test.describe('21. Onboarding', () => {
    test.beforeEach(async ({ page }) => { await loginAs(page, 'dev'); });
    test('21.1 Import wizard', async ({ page }) => { await go(page, '/onboarding/import'); await noErr(page); });
});

/* ---- SECTION 22: SEARCH ---- */
test.describe('22. Search', () => {
    test.beforeEach(async ({ page }) => { await loginAs(page, 'dev'); });
    test('22.1 Search API', async ({ page }) => {
        const r = await page.request.get(BASE + '/search?q=test');
        expect(r.status()).toBeLessThan(500);
    });
});

/* ---- SECTION 23: LOGOUT ---- */
test.describe('23. Logout', () => {
    test('23.1 Logout flow', async ({ page }) => {
        await loginAs(page, 'dev');
        const btn = page.locator('button:has-text("Keluar"), a:has-text("Keluar"), button:has-text("Logout")').first();
        if (await btn.isVisible({ timeout: 5000 }).catch(() => false)) {
            await btn.click(); await page.waitForTimeout(2000);
            expect(page.url()).toContain('/login');
        }
    });
});

/* ---- SECTION 24: LEGACY MIGRATION kecamatan_id 76 ---- */
test.describe('24. Legacy Migration kecamatan_id 76', () => {
    test.describe.configure({ mode: 'serial' });
    test.setTimeout(300000);

    test('24.1 Trigger migration for suffix 76', async ({ page }) => {
        await loginAs(page, 'superadmin');
        await go(page, '/admin/migration');
        await noErr(page);
        await h1(page, /Migrasi Data Legacy/);

        const form = page.locator('form').first();
        await expect(form).toBeVisible({ timeout: 10000 });

        // Find suffix input or SmartSelect and set to 76
        const suffixInput = page.locator('input[name="suffix"]').first();
        if (await suffixInput.isVisible({ timeout: 3000 }).catch(() => false)) {
            await suffixInput.fill('76');
        } else {
            const suffixBtn = page.locator('button:has-text("Suffix")').first();
            if (await suffixBtn.isVisible({ timeout: 3000 }).catch(() => false)) {
                await suffixBtn.click();
                await page.waitForTimeout(300);
                const opt76 = page.locator('[role="option"]:has-text("76")').first();
                if (await opt76.isVisible({ timeout: 3000 }).catch(() => false)) {
                    await opt76.click();
                } else {
                    const search = page.locator('[role="listbox"] input, input[placeholder*="Cari"]').first();
                    if (await search.isVisible({ timeout: 2000 }).catch(() => false)) {
                        await search.fill('76');
                        await page.waitForTimeout(500);
                        const filtered = page.locator('[role="option"]').first();
                        if (await filtered.isVisible({ timeout: 2000 }).catch(() => false)) await filtered.click();
                    }
                }
            }
        }
        await page.waitForTimeout(300);
        await page.screenshot({ path: 'test-results/migration-76-before.png', fullPage: true });

        // Submit
        const submitBtn = page.locator('button:has-text("Jalankan"), button:has-text("Mulai"), button:has-text("Proses"), button[type="submit"]').first();
        if (await submitBtn.isVisible({ timeout: 5000 }).catch(() => false)) {
            await submitBtn.click();
            await page.waitForTimeout(3000);
        }
        await noErr(page);

        // Monitor migration progress
        await page.waitForTimeout(5000);
        await page.screenshot({ path: 'test-results/migration-76-submitted.png', fullPage: true });

        const logOutput = page.locator('pre').first();
        if (await logOutput.isVisible({ timeout: 10000 }).catch(() => false)) {
            let maxAttempts = 48;
            for (let i = 0; i < maxAttempts; i++) {
                await page.waitForTimeout(5000);
                const bodyText = await page.locator('body').textContent() || '';
                if (bodyText.includes('completed') || bodyText.includes('failed')) break;
            }
        }
        await page.screenshot({ path: 'test-results/migration-76-final.png', fullPage: true });
    });
});
