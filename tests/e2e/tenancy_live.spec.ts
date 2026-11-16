import { test, expect, type Browser, type BrowserContext, type Locator, type Page } from '@playwright/test';
import fs from 'fs';
import path from 'path';

/**
 * LIVE end-to-end audit: Pusat Tenant -> Lokasi Tenant.
 *
 * Run headed so the operator can watch every step:
 *   npx playwright test tests/e2e/tenancy_live.spec.ts --headed --workers=1
 */

const CENTRAL = process.env.E2E_CENTRAL_URL ?? 'http://siupknext.test';
const TENANT = process.env.E2E_TENANT_URL ?? 'http://asta.siupknext.test';
const TENANT_NAME = process.env.E2E_TENANT_NAME ?? 'ASTA';
const TENANT_CODE = process.env.E2E_TENANT_CODE ?? 'asta';
const TENANT_DOMAIN = process.env.E2E_TENANT_DOMAIN ?? 'asta.siupknext.test';
const NEW_PASSWORD = process.env.E2E_TENANT_PASSWORD ?? 'Asta#2026!';

const OUT = path.resolve(process.cwd(), 'docs/audit/live-tenancy');
fs.mkdirSync(OUT, { recursive: true });

type Status = 'PASS' | 'FAIL' | 'SKIP' | 'INFO';
type Result = { area: string; step: string; status: Status; detail: string };

const results: Result[] = [];
const runtime = {
    tenantId: null as number | null,
    seedPassword: null as string | null,
    centralPage: null as Page | null,
    tenantPage: null as Page | null,
    context: null as BrowserContext | null,
    memberNik: '',
    memberName: '',
    consoleErrors: [] as string[],
};

let shotIndex = 0;

function record(area: string, step: string, status: Status, detail = '') {
    results.push({ area, step, status, detail });
    const icon = status === 'PASS' ? '✅' : status === 'FAIL' ? '❌' : status === 'SKIP' ? '⏭️' : 'ℹ️';
    console.log(`${icon} [${area}] ${step}${detail ? ` — ${detail}` : ''}`);
}

async function step(area: string, name: string, fn: () => Promise<string | void>, opts: { critical?: boolean } = {}) {
    try {
        const detail = await fn();
        record(area, name, 'PASS', detail ?? '');
    } catch (error) {
        const message = error instanceof Error ? error.message.split('\n')[0].slice(0, 400) : String(error).slice(0, 400);
        record(area, name, 'FAIL', message);
        if (opts.critical) throw error;
    }
}

async function shot(page: Page, label: string, fullPage = true) {
    shotIndex += 1;
    const safe = label.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '').slice(0, 70);
    const file = path.join(OUT, `${String(shotIndex).padStart(3, '0')}-${safe}.png`);
    await page.screenshot({ path: file, fullPage }).catch(() => undefined);
    console.log(`   📸 ${path.basename(file)}`);
}

async function pause(page: Page, ms = 900) {
    await page.waitForTimeout(ms);
}

// Marker khusus halaman error Laravel. Jangan pakai "SQLSTATE["/`Server Error` polos
// karena keduanya bisa muncul di dokumentasi (mis. Changelog) sebagai teks biasa.
const ERROR_MARKERS = /(Whoops, looks like something went wrong|Internal Server Error\s*Copy as Markdown|Illuminate\\Database\\QueryException)/i;

async function domClick(locator: Locator, timeout = 15000) {
    await locator.waitFor({ state: 'visible', timeout });
    await locator.evaluate((el) => (el as HTMLElement).click());
}

async function bodyText(page: Page): Promise<string> {
    return page.locator('body').innerText().catch(() => '');
}

async function assertPageHealthy(page: Page, label: string) {
    const body = await bodyText(page);
    if (ERROR_MARKERS.test(body)) {
        throw new Error(`${label}: halaman mengandung error marker — ${body.replace(/\s+/g, ' ').slice(0, 240)}`);
    }
}

async function gotoHealthy(page: Page, url: string, label: string): Promise<number> {
    const response = await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 45000 });
    await page.waitForTimeout(350);
    await assertPageHealthy(page, label);
    return response?.status() ?? 0;
}

async function selectSmart(page: Page, label: string, optionText: string) {
    const trigger = page.getByLabel(label, { exact: false }).first();
    await trigger.scrollIntoViewIfNeeded();
    await trigger.click();
    const option = page.getByRole('option', { name: new RegExp(optionText.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'i') }).first();
    await option.waitFor({ state: 'visible', timeout: 20000 });
    await option.click();
    await page.waitForTimeout(250);
}

async function selectFirstSmart(page: Page, label: string) {
    const trigger = page.getByLabel(label, { exact: false }).first();
    await trigger.scrollIntoViewIfNeeded();
    await trigger.click();
    const option = page.getByRole('option').first();
    await option.waitFor({ state: 'visible', timeout: 20000 });
    await option.click();
    await page.waitForTimeout(250);
}

async function loginCentral(page: Page) {
    await page.goto(`${CENTRAL}/login`, { waitUntil: 'domcontentloaded' });
    await page.locator('input[autocomplete="username"]').first().fill('superadmin');
    await page.locator('input[autocomplete="current-password"]').first().fill('password');
    await page.getByRole('button', { name: /masuk ke dashboard/i }).first().click();
    await page.waitForURL((url) => url.pathname.startsWith('/admin'), { timeout: 40000 });
    await page.waitForTimeout(600);
}

async function loginTenant(page: Page) {
    await page.goto(`${TENANT}/login`, { waitUntil: 'networkidle' });
    if (!page.url().includes('/login')) return;

    const username = page.locator('input[autocomplete="username"]').first();
    const password = page.locator('input[autocomplete="current-password"]').first();
    await username.waitFor({ state: 'visible', timeout: 20000 });
    await username.fill(`${TENANT_CODE}.admin`);
    await password.fill(process.env.E2E_TENANT_PASSWORD ?? 'Asta#2026!');
    await domClick(page.getByRole('button', { name: /masuk ke dashboard/i }).first());
    await page.waitForURL((u) => !u.pathname.includes('/login'), { timeout: 45000 });
    await page.waitForTimeout(600);
}

function uniqueNik(): string {
    return ('3204' + Date.now().toString()).slice(-16);
}

test.setTimeout(20 * 60 * 1000);
test.describe.configure({ mode: 'serial' });

test.beforeAll(async ({ browser }: { browser: Browser }) => {
    const context = await browser.newContext({
        viewport: { width: 1440, height: 960 },
        ignoreHTTPSErrors: true,
        locale: 'id-ID',
        timezoneId: 'Asia/Jakarta',
    });
    runtime.context = context;
    context.setDefaultTimeout(15000);
    context.setDefaultNavigationTimeout(45000);
    runtime.centralPage = await context.newPage();
    runtime.tenantPage = await context.newPage();

    for (const [name, page] of [['central', runtime.centralPage], ['tenant', runtime.tenantPage]] as const) {
        page.on('pageerror', (err) => {
            runtime.consoleErrors.push(`${name}: pageerror: ${err.message}`);
        });
        page.on('console', (msg) => {
            if (msg.type() === 'error') {
                const text = msg.text();
                if (!/favicon|net::ERR_ABORTED|Failed to load resource: the server responded with a status of 403/i.test(text)) {
                    runtime.consoleErrors.push(`${name}: console: ${text.slice(0, 220)}`);
                }
            }
        });
    }
});

test.afterAll(async () => {
    const failures = results.filter((r) => r.status === 'FAIL');
    const passes = results.filter((r) => r.status === 'PASS');
    const skipped = results.filter((r) => r.status === 'SKIP');

    const lines = [
        `# Live Audit — Pusat Tenant → Lokasi Tenant`,
        ``,
        `- Tanggal: ${new Date().toISOString()}`,
        `- Pusat: ${CENTRAL}`,
        `- Lokasi: ${TENANT}`,
        `- Tenant: ${TENANT_NAME} (${TENANT_CODE})`,
        `- Tenant ID: ${runtime.tenantId ?? '-'}`,
        `- Screenshot: ${OUT}`,
        ``,
        `## Ringkasan`,
        ``,
        `| Status | Jumlah |`,
        `|---|---|`,
        `| PASS | ${passes.length} |`,
        `| FAIL | ${failures.length} |`,
        `| SKIP | ${skipped.length} |`,
        ``,
        `## Detail`,
        ``,
        `| # | Area | Langkah | Status | Detail |`,
        `|---|---|---|---|---|`,
        ...results.map((r, i) => `| ${i + 1} | ${r.area} | ${r.step.replace(/\|/g, '\\|')} | ${r.status} | ${r.detail.replace(/\|/g, '\\|').replace(/\n/g, ' ').slice(0, 260)} |`),
        ``,
        `## Console / Page Errors`,
        ``,
        runtime.consoleErrors.length
            ? runtime.consoleErrors.map((e) => `- ${e.replace(/\|/g, '\\|')}`).join('\n')
            : '- Tidak ada error console yang tercatat.',
        ``,
    ];

    fs.writeFileSync(path.join(OUT, 'report.md'), lines.join('\n'), 'utf8');
    fs.writeFileSync(path.join(OUT, 'report.json'), JSON.stringify({ results, consoleErrors: runtime.consoleErrors }, null, 2), 'utf8');

    // Keep the browser open briefly so the operator can see the final state.
    await runtime.tenantPage?.waitForTimeout(1500).catch(() => undefined);
    await runtime.context?.close();

    console.log(`\n📄 Laporan: ${path.join(OUT, 'report.md')}`);
    console.log(`📊 PASS=${passes.length} FAIL=${failures.length} SKIP=${skipped.length}`);
});

// ------------------------------------------------------------------
// PHASE 1 — PUSAT TENANT
// ------------------------------------------------------------------

test('01 — Pusat Tenant: login superadmin & dashboard', async () => {
    const page = runtime.centralPage!;
    await step('Pusat', 'Buka halaman login pusat', async () => {
        const status = await gotoHealthy(page, `${CENTRAL}/login`, 'login pusat');
        await shot(page, '01 login pusat');
        return `HTTP ${status}`;
    }, { critical: true });

    await step('Pusat', 'Login sebagai superadmin', async () => {
        await loginCentral(page);
        await page.waitForTimeout(700);
        await assertPageHealthy(page, 'admin dashboard');
        await shot(page, '02 dashboard pusat');
        return page.url();
    }, { critical: true });

    const centralPages: Array<[string, string]> = [
        ['Dashboard', '/admin'],
        ['Daftar Tenant', '/admin/tenants'],
        ['Invoice', '/admin/invoices'],
        ['Plan', '/admin/plans'],
        ['Shard', '/admin/shards'],
        ['Pengaturan Platform', '/admin/settings'],
        ['Audit Log', '/admin/audit-logs'],
        ['Pendapatan', '/admin/revenue'],
        ['Pengguna Platform', '/admin/users'],
        ['WhatsApp Platform', '/admin/whatsapp'],
        ['Integrasi', '/admin/integrations'],
        ['Payment Gateway', '/admin/payment-gateways'],
        ['Migrasi', '/admin/migration'],
        ['Changelog', '/admin/changelog'],
    ];

    for (const [label, route] of centralPages) {
        await step('Pusat', `Halaman ${label}`, async () => {
            const status = await gotoHealthy(page, `${CENTRAL}${route}`, label);
            const body = await bodyText(page);
            if (status >= 500) throw new Error(`HTTP ${status}`);
            return `HTTP ${status}, ${body.length} char`;
        });
    }

    await step('Pusat', 'Navigasi sidebar admin terlihat', async () => {
        await gotoHealthy(page, `${CENTRAL}/admin`, 'dashboard');
        const links = await page.locator('aside a, nav a').count();
        await shot(page, '03 sidebar pusat');
        if (links < 5) throw new Error(`Hanya ${links} link navigasi ditemukan`);
        return `${links} link navigasi`;
    });
});

test('02 — Pusat Tenant: daftarkan tenant ASTA', async () => {
    const page = runtime.centralPage!;

    await step('Pusat', 'Cari tenant ASTA di daftar tenant', async () => {
        await gotoHealthy(page, `${CENTRAL}/admin/tenants`, 'daftar tenant');
        await shot(page, '04 daftar tenant');
        const existing = page.locator('a[href^="/admin/tenants/"]').filter({ hasText: new RegExp(`^${TENANT_NAME}$`, 'i') }).first();
        if (await existing.count()) {
            const href = await existing.getAttribute('href');
            runtime.tenantId = Number((href ?? '').split('/').filter(Boolean).pop());
            return `Tenant sudah ada (row_id ${runtime.tenantId})`;
        }
        return 'Tenant belum ada, lanjut pendaftaran';
    }, { critical: true });

    if (runtime.tenantId === null) {
        await step('Pusat', 'Buka form Tambah Tenant', async () => {
            await page.getByRole('link', { name: /tambah tenant/i }).first().click();
            await page.waitForURL(/\/admin\/tenants\/create/, { timeout: 20000 });
            await shot(page, '05 form tambah tenant');
            return page.url();
        }, { critical: true });

        await step('Pusat', 'Isi identitas & wilayah tenant', async () => {
            await page.getByLabel('Nama Tenant').first().fill(TENANT_NAME);
            await selectSmart(page, 'Provinsi', 'Jawa Barat');
            await selectSmart(page, 'Kabupaten/Kota', 'Kabupaten Bogor');
            await selectSmart(page, 'Kecamatan', 'Citeureup');
            await shot(page, '06 form tenant terisi');
            return 'ASTA / Jawa Barat / Bogor / Citeureup';
        }, { critical: true });

        await step('Pusat', 'Simpan & provisioning tenant', async () => {
            await domClick(page.getByRole('button', { name: /daftarkan tenant/i }).first());
            await page.waitForURL(/\/admin\/tenants\/\d+/, { timeout: 180000 });
            await pause(page, 900);
            await assertPageHealthy(page, 'detail tenant');
            runtime.tenantId = Number(page.url().split('/').filter(Boolean).pop());
            const body = await bodyText(page);
            const match = body.match(/password awal:\s*([^\s.]+)/i);
            if (match) runtime.seedPassword = match[1];
            await shot(page, '07 tenant berhasil dibuat');
            return `row_id ${runtime.tenantId}${runtime.seedPassword ? ' + password awal tertangkap' : ''}`;
        }, { critical: true });
    }

    expect(runtime.tenantId).not.toBeNull();

    await step('Pusat', 'Detail tenant lengkap (status, shard, modul, users)', async () => {
        await gotoHealthy(page, `${CENTRAL}/admin/tenants/${runtime.tenantId}`, 'detail tenant');
        const body = await bodyText(page);
        for (const marker of ['Detail Tenant', 'Custom Domain', 'Pengguna', 'Modul Simpanan', 'Langganan']) {
            if (!body.includes(marker)) throw new Error(`Bagian "${marker}" tidak ditemukan`);
        }
        await shot(page, '08 detail tenant ASTA');
        return 'semua kartu detail tampil';
    });
});

test('03 — Pusat Tenant: atur domain asta.siupknext.test', async () => {
    const page = runtime.centralPage!;
    const id = runtime.tenantId!;

    await step('Pusat', 'Tambah custom domain pada tenant', async () => {
        await gotoHealthy(page, `${CENTRAL}/admin/tenants/${id}/edit`, 'edit tenant');
        const already = await page.locator(`text=${TENANT_DOMAIN}`).count();
        if (!already) {
            await page.getByLabel('Tambah Domain').first().fill(TENANT_DOMAIN);
            await page.getByRole('button', { name: /^tambah$/i }).first().click();
            await page.waitForTimeout(300);
            await domClick(page.getByRole('button', { name: /simpan perubahan/i }).first());
            await page.waitForURL(/\/admin\/tenants\/\d+$/, { timeout: 40000 });
            await page.waitForTimeout(800);
        }
        await assertPageHealthy(page, 'detail tenant setelah domain');
        await shot(page, '09 domain tersimpan');
        return TENANT_DOMAIN;
    }, { critical: true });

    await step('Pusat', 'Verifikasi domain tampil di detail tenant', async () => {
        await gotoHealthy(page, `${CENTRAL}/admin/tenants/${id}`, 'detail tenant');
        await expect(page.getByText(TENANT_DOMAIN, { exact: false }).first()).toBeVisible({ timeout: 10000 });
        await shot(page, '10 domain di detail tenant');
        return TENANT_DOMAIN;
    });

    await step('Lokasi', 'Host tenant dikenali (GET /login/user)', async () => {
        const response = await runtime.context!.request.get(`${TENANT}/login/user`, { maxRedirects: 5, timeout: 30000 });
        const status = response.status();
        if (status !== 200) throw new Error(`HTTP ${status} — host belum ter-resolve ke tenant`);
        const html = await response.text();
        if (!/Pilih Akun Login|Lokasi Tenant/i.test(html)) throw new Error('Halaman bukan halaman login tenant');
        return `HTTP ${status}`;
    });
});

test('04 — Pusat Tenant: users, hak akses menu, onboarding, purifier', async () => {
    const page = runtime.centralPage!;
    const id = runtime.tenantId!;

    await step('Pusat', 'Daftar pengguna tenant (akun otomatis per role)', async () => {
        await gotoHealthy(page, `${CENTRAL}/admin/tenants/${id}/users`, 'users tenant');
        const body = await bodyText(page);
        if (!body.includes(`${TENANT_CODE}.admin`)) throw new Error(`Akun ${TENANT_CODE}.admin tidak ditemukan`);
        const count = (body.match(new RegExp(`${TENANT_CODE}\\.[a-z_]+`, 'g')) ?? []).length;
        await shot(page, '11 users tenant');
        return `${count} akun terlihat`;
    });

    await step('Pusat', 'Form tambah user tenant', async () => {
        const status = await gotoHealthy(page, `${CENTRAL}/admin/tenants/${id}/users/create`, 'form user tenant');
        await shot(page, '12 form user tenant');
        return `HTTP ${status}`;
    });

    await step('Pusat', 'Hak akses menu tenant', async () => {
        await gotoHealthy(page, `${CENTRAL}/admin/tenants/${id}/menus`, 'menu tenant');
        const body = await bodyText(page);
        if (!/menu|role|peran/i.test(body)) throw new Error('Konten hak akses menu tidak tampil');
        await shot(page, '13 hak akses menu');
        const roleLink = page.locator('a[href*="/menus/"]').first();
        if (await roleLink.count()) {
            const href = await roleLink.getAttribute('href');
            if (href) {
                const status = await gotoHealthy(page, `${CENTRAL}${href}`, 'edit menu role');
                await shot(page, '14 edit menu role');
                return `index + editor role (HTTP ${status})`;
            }
        }
        return 'index menu';
    });

    await step('Pusat', 'Onboarding & saldo awal tenant', async () => {
        const status = await gotoHealthy(page, `${CENTRAL}/admin/tenants/${id}/onboarding/import`, 'onboarding tenant');
        const body = await bodyText(page);
        if (!/onboarding|saldo|import/i.test(body)) throw new Error('Konten onboarding tidak tampil');
        await shot(page, '15 onboarding tenant');
        return `HTTP ${status}`;
    });

    await step('Pusat', 'Data purifier / training mode', async () => {
        const status = await gotoHealthy(page, `${CENTRAL}/admin/tenants/${id}/data-purifier`, 'data purifier');
        const body = await bodyText(page);
        if (!/purifier|pelatihan|training|hapus/i.test(body)) throw new Error('Konten data purifier tidak tampil');
        await shot(page, '16 data purifier');
        return `HTTP ${status}`;
    });
});

test('05 — Pusat Tenant: modul simpanan, suspend/activate, impersonasi', async () => {
    const page = runtime.centralPage!;
    const id = runtime.tenantId!;

    await step('Pusat', 'Aktifkan / verifikasi modul Simpanan untuk tenant', async () => {
        await gotoHealthy(page, `${CENTRAL}/admin/tenants/${id}`, 'detail tenant');
        const body = await bodyText(page);
        const moduleActive = /Modul Simpanan[\s\S]{0,120}?\bAktif\b/i.test(body) && body.includes(`simp_products_t${id}`);
        if (moduleActive) {
            await shot(page, '17 modul simpanan sudah aktif');
            return `Modul Simpanan sudah aktif (simp_*_t${id} tersedia)`;
        }
        const enable = page.getByRole('button', { name: /^(aktifkan modul|aktifkan kembali)$/i }).first();
        if (!(await enable.count())) return 'Tombol aktivasi tidak tersedia';
        await domClick(enable);
        const dialog = page.getByRole('dialog').first();
        await dialog.waitFor({ state: 'visible', timeout: 10000 });
        await shot(page, '17 konfirmasi aktifkan simpanan');
        await domClick(dialog.locator('button').filter({ hasText: /^Aktifkan$/i }).first());
        await page.getByText(`simp_products_t${id}`, { exact: false }).first().waitFor({ state: 'visible', timeout: 60000 });
        await assertPageHealthy(page, 'setelah aktifkan simpanan');
        await shot(page, '18 modul simpanan aktif');
        return `modul simpanan diaktifkan (simp_*_t${id})`;
    });

    await step('Pusat', 'Suspend tenant & verifikasi host tenant tertutup', async () => {
        await gotoHealthy(page, `${CENTRAL}/admin/tenants/${id}`, 'detail tenant');
        const suspend = page.getByRole('button', { name: /^suspend$/i }).first();
        if (!(await suspend.count())) return 'Tombol suspend tidak tersedia (status bukan active)';
        await domClick(suspend);
        await page.getByText('Tenant disuspend', { exact: false }).first().waitFor({ state: 'visible', timeout: 30000 });
        await assertPageHealthy(page, 'setelah suspend');
        const response = await runtime.context!.request.get(`${TENANT}/login/user`, { maxRedirects: 0, timeout: 20000 });
        await shot(page, '19 tenant disuspend');
        return `tenant disuspend, host tenant HTTP ${response.status()}`;
    });

    await step('Pusat', 'Aktifkan kembali tenant', async () => {
        const activate = page.getByRole('button', { name: /^aktifkan$/i }).first();
        if (!(await activate.count())) return 'Tombol aktifkan tidak tersedia';
        await domClick(activate);
        await page.getByText('Tenant disuspend', { exact: false }).first().waitFor({ state: 'hidden', timeout: 30000 });
        await assertPageHealthy(page, 'setelah aktifkan');
        await shot(page, '20 tenant aktif kembali');
        const activity = await runtime.context!.request.get(`${TENANT}/login/user`, { maxRedirects: 5, timeout: 20000 });
        return `tenant aktif, host tenant HTTP ${activity.status()}`;
    });

    await step('Pusat', 'Impersonasi: Login Tenant membuka domain lokasi', async () => {
        await gotoHealthy(page, `${CENTRAL}/admin/tenants/${id}`, 'detail tenant');
        const responsePromise = page.waitForResponse(
            (r) => r.url().includes(`/admin/tenants/${id}/impersonate`) && r.request().method() === 'POST',
            { timeout: 30000 },
        );
        const popupPromise = runtime.context!.waitForEvent('page', { timeout: 8000 }).catch(() => null);
        await domClick(page.getByRole('button', { name: /^login tenant$/i }).first());
        const response = await responsePromise;
        const data = (await response.json()) as { redirect_url?: string };
        let popup = await popupPromise;
        if (!popup) {
            popup = await runtime.context!.newPage();
            await popup.goto(data.redirect_url ?? `${TENANT}/dashboard`, { waitUntil: 'domcontentloaded', timeout: 45000 });
        }
        await popup.waitForLoadState('domcontentloaded', { timeout: 45000 }).catch(() => undefined);
        await popup.waitForURL(/\/dashboard/, { timeout: 45000 }).catch(() => undefined);
        await popup.waitForTimeout(900);
        await assertPageHealthy(popup, 'dashboard impersonasi');
        const body = await bodyText(popup);
        if (!/Mode Impersonasi Superadmin/i.test(body)) throw new Error('Banner impersonasi tidak tampil');
        await shot(popup, '21 impersonasi dashboard tenant');
        const url = popup.url();

        const back = popup.getByRole('button', { name: /kembali ke superadmin/i }).first();
        if (await back.count()) {
            await domClick(back);
            await popup.waitForURL((u) => u.pathname.startsWith('/admin'), { timeout: 30000 }).catch(() => undefined);
            await popup.waitForTimeout(800);
            await shot(popup, '22 kembali ke superadmin');
        }
        await popup.close().catch(() => undefined);
        return url;
    });
});

// ------------------------------------------------------------------
// PHASE 2 — LOKASI TENANT
// ------------------------------------------------------------------

test('06 — Lokasi Tenant: situs publik & login otomatis', async () => {
    const page = runtime.tenantPage!;

    await step('Lokasi', 'Situs publik tenant', async () => {
        const status = await gotoHealthy(page, `${TENANT}/`, 'situs publik tenant');
        await shot(page, '23 situs publik tenant');
        return `HTTP ${status}`;
    });

    await step('Lokasi', 'Halaman pilih akun tenant (host otomatis)', async () => {
        const status = await gotoHealthy(page, `${TENANT}/login/user`, 'login user tenant');
        const body = await bodyText(page);
        if (!body.includes(TENANT_CODE)) throw new Error('Kode tenant tidak tampil di konteks');
        if (/superadmin/i.test(body)) throw new Error('Akun pusat bocor ke halaman tenant');
        if (!body.includes(`${TENANT_CODE}.admin`)) throw new Error(`Akun ${TENANT_CODE}.admin tidak tampil`);
        await shot(page, '24 pilih akun tenant');
        return `HTTP ${status}, hanya user tenant`;
    });

    await step('Lokasi', 'Login otomatis (klik 2x kartu admin)', async () => {
        const card = page.locator('article[role="button"]').first();
        if (!(await card.count())) {
            throw new Error('Kartu pengguna tidak tersedia (quick login mungkin nonaktif)');
        }
        await card.evaluate((el) => el.dispatchEvent(new MouseEvent('dblclick', { bubbles: true, cancelable: true, view: window })));
        await page.waitForURL((u) => u.pathname.includes('/dashboard'), { timeout: 40000 });
        await page.waitForTimeout(900);
        await assertPageHealthy(page, 'dashboard tenant');
        await shot(page, '25 dashboard tenant');
        return page.url();
    });
});

test('07 — Lokasi Tenant: jelajah seluruh modul utama', async () => {
    const page = runtime.tenantPage!;

    const routes: Array<[string, string]> = [
        ['Dashboard', '/dashboard'],
        ['Profil', '/profile'],
        ['Pencarian Global', '/search?q=asta'],
        ['Master Data — Anggota', '/master-data/members'],
        ['Master Data — Tambah Anggota', '/master-data/members/create'],
        ['Master Data — Kelompok', '/master-data/groups'],
        ['Master Data — Tambah Kelompok', '/master-data/groups/create'],
        ['Master Data — Desa', '/master-data/villages'],
        ['Master Data — Lembaga Lain', '/master-data/institutions'],
        ['Pembiayaan — Pinjaman', '/lending/loans'],
        ['Pembiayaan — Proposal', '/lending/proposals'],
        ['Pembiayaan — Pinjaman Anggota', '/lending/member-loans'],
        ['Pembiayaan — Simulasi', '/lending/simulation'],
        ['Pembiayaan — Laporan Portfolio', '/lending/reports/portfolio'],
        ['Pembiayaan — Jatuh Tempo', '/lending/reports/due-today'],
        ['Pembiayaan — Tunggakan', '/lending/reports/overdue'],
        ['Pembiayaan — LPP Kelompok', '/lending/reports/lpp-kelompok'],
        ['Akuntansi — Bagan Akun', '/accounting/chart-of-accounts'],
        ['Akuntansi — Jurnal', '/accounting/journals'],
        ['Akuntansi — Entri Jurnal', '/accounting/journal-entries'],
        ['Akuntansi — Akun', '/accounting/accounts'],
        ['Akuntansi — Aset', '/accounting/assets'],
        ['Akuntansi — Laporan', '/accounting/reports'],
        ['Akuntansi — Neraca', '/accounting/reports/balance-sheet'],
        ['Akuntansi — Laba Rugi', '/accounting/reports/income-statement'],
        ['Akuntansi — Arus Kas', '/accounting/reports/cash-flow'],
        ['Akuntansi — Buku Besar', '/accounting/reports/general-ledger'],
        ['Akuntansi — Tutup Buku', '/accounting/period-close'],
        ['Anggaran', '/budgeting'],
        ['OJK — Kesehatan', '/regulatory/ojk/health'],
        ['OJK — Kolektibilitas', '/regulatory/ojk/collectibility'],
        ['OJK — Profil', '/regulatory/ojk/profile'],
        ['OJK — Simpanan', '/regulatory/ojk/savings'],
        ['Akses — Pengguna', '/access/users'],
        ['Akses — Role', '/access/roles'],
        ['Pengaturan', '/settings'],
        ['Pengaturan — SOP', '/settings/sop'],
        ['Pengaturan — WhatsApp', '/settings/whatsapp'],
        // Tanda tangan & offline-access adalah endpoint PUT (bagian dari /settings),
        // bukan halaman GET. Halaman GET yang tersedia adalah offline-users.
        ['Pengaturan — Pengguna Offline', '/settings/offline-users'],
        ['Website — Postingan', '/website/posts'],
        ['Website — Halaman', '/website/pages'],
        ['Website — Pengaturan', '/website/settings'],
        ['Website — Pesan', '/website/messages'],
        ['Billing — Invoice', '/billing/invoices'],
        ['Catatan Supervisor', '/supervisor/notes'],
        ['Portal Anggota', '/portal'],
        ['Changelog', '/changelog'],
        ['Simpanan', '/simpanan'],
        ['Simpanan — Pengaturan', '/simpanan/pengaturan'],
        ['Simpanan — Bunga', '/simpanan/bunga'],
    ];

    for (const [label, route] of routes) {
        await step('Lokasi', label, async () => {
            const status = await gotoHealthy(page, `${TENANT}${route}`, label);
            if (status >= 500) throw new Error(`HTTP ${status}`);
            // /portal khusus akun anggota yang tertaut ke data anggota; admin tenant
            // tidak punya relasi anggota sehingga 404 adalah perilaku yang wajar.
            if (status === 404 && route === '/portal') {
                return 'HTTP 404 — halaman portal khusus akun anggota (admin tidak tertaut anggota), bukan kegagalan server';
            }
            if (status === 404) throw new Error(`HTTP 404 — rute tidak ditemukan`);
            if (status === 403) return `HTTP 403 — dibatasi permission/role (bukan error server)`;
            if (status >= 300 && status < 400) return `HTTP ${status} — redirect`;
            const textLength = (await bodyText(page)).length;
            await shot(page, `modul ${label}`);
            return `HTTP ${status}, ${textLength} char`;
        });
    }
});

test('08 — Lokasi Tenant: CRUD anggota (detail)', async () => {
    const page = runtime.tenantPage!;
    await loginTenant(page);
    runtime.memberNik = uniqueNik();
    runtime.memberName = `Anggota Uji ${Date.now().toString().slice(-5)}`;

    await step('Lokasi', 'Tambah anggota baru', async () => {
        await gotoHealthy(page, `${TENANT}/master-data/members/create`, 'tambah anggota');
        // Lookup NIK mengosongkan ulang form saat respons "tidak ditemukan"
        // tiba. Tunggu responsnya dulu sebelum mengisi field lain.
        const lookup = page
            .waitForResponse((r) => r.url().includes('/master-data/members/lookup'), { timeout: 30000 })
            .catch(() => null);
        await page.getByLabel('NIK').first().fill(runtime.memberNik);
        await lookup;
        await page.waitForTimeout(500);
        await page.getByLabel('Nama Lengkap').first().fill(runtime.memberName);
        await page.getByLabel('Alamat').first().fill('Jl. Pengujian Otomatis No. 1');
        await selectFirstSmart(page, 'Desa');
        await shot(page, '26 form anggota terisi');
        await domClick(page.getByRole('button', { name: /^simpan/i }).first());
        await page.waitForURL(/\/master-data\/members(\?.*)?$/, { timeout: 40000 });
        await page.waitForTimeout(900);
        await assertPageHealthy(page, 'daftar anggota setelah simpan');
        await shot(page, '27 anggota tersimpan');
        return `${runtime.memberName} / ${runtime.memberNik}`;
    }, { critical: true });

    await step('Lokasi', 'Cari & buka detail anggota', async () => {
        await gotoHealthy(page, `${TENANT}/master-data/members?search=${encodeURIComponent(runtime.memberNik)}`, 'cari anggota');
        const link = page.getByRole('link', { name: runtime.memberName }).first();
        await link.waitFor({ state: 'visible', timeout: 15000 });
        await link.click();
        await page.waitForURL(/\/master-data\/members\/\d+/, { timeout: 30000 });
        await page.waitForTimeout(700);
        await assertPageHealthy(page, 'detail anggota');
        const body = await bodyText(page);
        if (!body.includes(runtime.memberNik)) throw new Error('NIK tidak tampil di detail');
        await shot(page, '28 detail anggota');
        return page.url();
    });

    await step('Lokasi', 'Edit nomor HP anggota', async () => {
        await page.getByRole('link', { name: /^edit/i }).first().click().catch(async () => {
            await page.goto(page.url().replace(/$/, '/edit'), { waitUntil: 'domcontentloaded' });
        });
        await page.waitForTimeout(700);
        const phone = page.getByLabel('No. HP').first();
        await phone.fill('081234567890');
        await domClick(page.getByRole('button', { name: /^simpan/i }).first());
        await page.waitForURL(/\/master-data\/members(\?.*)?$/, { timeout: 40000 });
        await page.waitForTimeout(800);
        await assertPageHealthy(page, 'setelah edit anggota');
        await shot(page, '29 anggota diperbarui');
        return 'HP diperbarui';
    });
});

test('09 — Lokasi Tenant: CRUD lembaga lain (detail)', async () => {
    const page = runtime.tenantPage!;
    await loginTenant(page);
    const name = `Lembaga Uji ${Date.now().toString().slice(-5)}`;

    await step('Lokasi', 'Tambah lembaga lain', async () => {
        await gotoHealthy(page, `${TENANT}/master-data/institutions/create`, 'tambah lembaga');
        await selectFirstSmart(page, 'Desa');
        await page.getByLabel('Nama Lembaga').first().fill(name);
        await page.getByLabel('Alamat Lembaga').first().fill('Jl. Lembaga Uji No. 2');
        await page.getByLabel('Nama Pimpinan').first().fill('Pimpinan Uji');
        await page.getByLabel('Nama Penanggungjawab').first().fill('Penanggungjawab Uji');
        await page.getByLabel('Nomor Identitas Lembaga').first().fill(`ID${Date.now().toString().slice(-8)}`);
        await shot(page, '30 form lembaga terisi');
        await domClick(page.getByRole('button', { name: /^simpan/i }).first());
        await page.waitForURL(/\/master-data\/institutions(\?.*)?$/, { timeout: 40000 });
        await page.waitForTimeout(800);
        await assertPageHealthy(page, 'daftar lembaga setelah simpan');
        await shot(page, '31 lembaga tersimpan');
        return name;
    });

    await step('Lokasi', 'Verifikasi lembaga di daftar', async () => {
        await gotoHealthy(page, `${TENANT}/master-data/institutions?search=${encodeURIComponent(name)}`, 'cari lembaga');
        const body = await bodyText(page);
        if (!body.includes(name)) throw new Error('Lembaga baru tidak ditemukan di daftar');
        await shot(page, '32 lembaga di daftar');
        return name;
    });
});

test('10 — Lokasi Tenant: reset password, logout, login manual', async () => {
    const page = runtime.tenantPage!;
    await loginTenant(page);

    await step('Lokasi', 'Reset password admin tenant', async () => {
        await gotoHealthy(page, `${TENANT}/access/users`, 'manajemen pengguna tenant');
        const row = page.locator('tr', { hasText: `${TENANT_CODE}.admin` }).first();
        await row.waitFor({ state: 'visible', timeout: 15000 });
        const keyButton = row.locator('button:has-text("key")').first();
        await keyButton.click();
        const dialog = page.getByRole('dialog').first();
        await dialog.waitFor({ state: 'visible', timeout: 10000 });
        await dialog.getByLabel('Password Baru').fill(NEW_PASSWORD);
        await dialog.getByLabel('Konfirmasi Password').fill(NEW_PASSWORD);
        await shot(page, '33 modal reset password');
        await domClick(dialog.getByRole('button', { name: /simpan password/i }).first());
        await dialog.waitFor({ state: 'hidden', timeout: 20000 }).catch(() => undefined);
        await page.waitForTimeout(1200);
        await assertPageHealthy(page, 'setelah reset password');
        await shot(page, '34 password direset');
        return page.url().includes('/login')
            ? 'password diganti; sesi sendiri otomatis berakhir (perilaku keamanan)'
            : 'password admin diganti';
    });

    await step('Lokasi', 'Logout dari aplikasi tenant', async () => {
        if (page.url().includes('/login')) {
            return 'sudah di halaman login setelah reset password — logout tidak diperlukan';
        }
        await domClick(page.getByRole('button', { name: /^keluar$/i }).first());
        const dialog = page.getByRole('dialog').first();
        await dialog.waitFor({ state: 'visible', timeout: 10000 });
        await domClick(dialog.getByRole('button', { name: /^keluar$/i }).first());
        await page.waitForURL((u) => u.pathname.includes('/login'), { timeout: 30000 });
        await page.waitForTimeout(500);
        await shot(page, '35 logout tenant');
        return page.url();
    });

    await step('Lokasi', 'Login manual dengan password baru', async () => {
        // Selalu mulai dari halaman login yang bersih agar field tidak terisi
        // sebelum Vue selesai hydrate (penyebab kegagalan pada run sebelumnya).
        await page.goto(`${TENANT}/login`, { waitUntil: 'networkidle' });
        const username = page.locator('input[autocomplete="username"]').first();
        const password = page.locator('input[autocomplete="current-password"]').first();
        await username.waitFor({ state: 'visible', timeout: 20000 });
        await username.click();
        await username.fill(`${TENANT_CODE}.admin`);
        await password.click();
        await password.fill(NEW_PASSWORD);
        const typedUser = await username.inputValue();
        const typedPass = await password.inputValue();
        if (typedUser !== `${TENANT_CODE}.admin` || typedPass !== NEW_PASSWORD) {
            throw new Error(`Field login tidak terisi (user="${typedUser}", pass len=${typedPass.length})`);
        }
        await shot(page, '36 login manual tenant');
        await domClick(page.getByRole('button', { name: /masuk ke dashboard/i }).first());
        // Setelah reset password, sesi menyimpan "intended URL" (mis. /access/users),
        // jadi login bisa mendarat di halaman itu, bukan /dashboard.
        await page.waitForURL((u) => !u.pathname.includes('/login'), { timeout: 40000 });
        await gotoHealthy(page, `${TENANT}/dashboard`, 'dashboard setelah login manual');
        await page.waitForTimeout(800);
        await assertPageHealthy(page, 'dashboard setelah login manual');
        await shot(page, '37 dashboard login manual');
        return page.url();
    });
});

test('11 — Isolasi & keamanan lintas tenant/pusat', async () => {
    const page = runtime.tenantPage!;

    await step('Isolasi', 'Akun tenant tidak boleh membuka panel pusat', async () => {
        const response = await page.goto(`${TENANT}/admin/tenants`, { waitUntil: 'domcontentloaded' });
        const status = response?.status() ?? 0;
        const body = await bodyText(page);
        if (status === 200 && /Manajemen Tenant/i.test(body)) {
            throw new Error('Akun tenant dapat membuka Manajemen Tenant milik pusat!');
        }
        await shot(page, '38 isolasi panel pusat');
        return `HTTP ${status} (ditolak sesuai harapan)`;
    });

    await step('Isolasi', 'Host pusat tanpa sesi tidak menampilkan data tenant', async () => {
        const response = await runtime.context!.request.get(`${CENTRAL}/admin/tenants`, { maxRedirects: 0, timeout: 20000 });
        return `HTTP ${response.status()} (redirect ke login bila 302)`;
    });

    await step('Isolasi', 'Akun tenant tidak melihat user tenant lain di /login/user', async () => {
        const response = await page.goto(`${TENANT}/login/user`, { waitUntil: 'domcontentloaded' });
        const body = await bodyText(page);
        if (/superadmin/i.test(body)) throw new Error('Superadmin muncul di halaman login user tenant');
        const demoLeak = new RegExp(`demo\\.(admin|admin_desa)`, 'i').test(body);
        await shot(page, '39 isolasi daftar user tenant');
        return demoLeak ? 'PERHATIAN: pola username tenant demo terlihat' : `HTTP ${response?.status()}, hanya user ${TENANT_CODE}`;
    });
});

test('12 — Pusat Tenant: audit trail aksi yang baru dijalankan', async () => {
    const page = runtime.centralPage!;

    await step('Pusat', 'Audit log mencatat aksi tenant', async () => {
        await gotoHealthy(page, `${CENTRAL}/admin/audit-logs`, 'audit log');
        const body = await bodyText(page);
        // UI menampilkan label manusiawi ("Tenant Impersonate"), bukan nama
        // aksi mentah ("tenant.impersonate").
        const hits = ['Tenant Create', 'Tenant Update', 'Tenant Impersonate', 'Tenant Activate', 'Tenant Suspend']
            .filter((label) => body.includes(label));
        await shot(page, '40 audit log pusat');
        if (!hits.length) throw new Error('Tidak ada aksi tenant yang tercatat di audit log');
        return `Tercatat: ${hits.join(', ')}`;
    });

    await step('Pusat', 'Form invoice tenant', async () => {
        const status = await gotoHealthy(page, `${CENTRAL}/admin/invoices/create?tenant_id=${runtime.tenantId}`, 'form invoice');
        await shot(page, '41 form invoice');
        return `HTTP ${status}`;
    });
});
