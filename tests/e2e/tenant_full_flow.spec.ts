import { test, expect, type Browser, type BrowserContext, type Locator, type Page } from '@playwright/test';
import fs from 'fs';
import path from 'path';

/**
 * LIVE end-to-end: CRUD anggota lengkap, alur pinjaman individu sampai
 * angsuran, jelajah laporan, cetak PDF dan unduh Excel.
 *
 * Jalankan headed:
 *   npx playwright test tests/e2e/tenant_full_flow.spec.ts --headed --workers=1 --reporter=line
 */

const CENTRAL = process.env.E2E_CENTRAL_URL ?? 'http://siupknext.test';
const TENANT = process.env.E2E_TENANT_URL ?? 'http://asta.siupknext.test';
const TENANT_CODE = process.env.E2E_TENANT_CODE ?? 'asta';
const TENANT_PASSWORD = process.env.E2E_TENANT_PASSWORD ?? 'Asta#2026!';

const OUT = path.resolve(process.cwd(), 'docs/audit/live-tenancy');
const EXPORT_DIR = path.join(OUT, 'exports');
fs.mkdirSync(EXPORT_DIR, { recursive: true });

type Status = 'PASS' | 'FAIL' | 'SKIP' | 'INFO';
type Result = { area: string; step: string; status: Status; detail: string };

const results: Result[] = [];
const runtime = {
    context: null as BrowserContext | null,
    page: null as Page | null,
    memberId: 0,
    memberName: '',
    memberNik: '',
    loanId: 0,
    paidInstallment: { principal: 0, interest: 0, dueDate: '' },
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
        const message = error instanceof Error ? error.message.split('\n')[0].slice(0, 500) : String(error).slice(0, 500);
        record(area, name, 'FAIL', message);
        if (opts.critical) throw error;
    }
}

async function shot(page: Page, label: string, fullPage = true) {
    shotIndex += 1;
    const safe = label.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '').slice(0, 70);
    const file = path.join(OUT, `crud-${String(shotIndex).padStart(3, '0')}-${safe}.png`);
    await page.screenshot({ path: file, fullPage }).catch(() => undefined);
    console.log(`   📸 ${path.basename(file)}`);
}

const ERROR_MARKERS = /(Whoops, looks like something went wrong|Internal Server Error\s*Copy as Markdown|Illuminate\\Database\\QueryException)/i;

async function bodyText(page: Page): Promise<string> {
    return page.locator('body').innerText().catch(() => '');
}

async function assertHealthy(page: Page, label: string) {
    const body = await bodyText(page);
    if (ERROR_MARKERS.test(body)) {
        throw new Error(`${label}: error marker — ${body.replace(/\s+/g, ' ').slice(0, 300)}`);
    }
}

async function gotoHealthy(page: Page, url: string, label: string): Promise<number> {
    const response = await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 45000 });
    await page.waitForTimeout(400);
    await assertHealthy(page, label);
    return response?.status() ?? 0;
}

async function domClick(locator: Locator, timeout = 20000) {
    await locator.waitFor({ state: 'visible', timeout });
    await locator.evaluate((el) => (el as HTMLElement).click());
}

function escapeRegex(text: string): string {
    return text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

async function pickSmart(page: Page, label: string, optionText: string) {
    const trigger = page.getByLabel(label, { exact: false }).first();
    await trigger.scrollIntoViewIfNeeded();
    await trigger.click();
    await page.waitForTimeout(300);

    // SmartSelect yang searchable menampilkan input pencarian di dalam listbox.
    const listbox = page.locator('[role="listbox"]').last();
    const search = listbox.locator('input[type="search"]').first();
    if (await search.isVisible().catch(() => false)) {
        await search.fill(optionText);
        await page.waitForTimeout(500);
    }

    const option = page.getByRole('option', { name: new RegExp(escapeRegex(optionText), 'i') }).first();
    await option.waitFor({ state: 'visible', timeout: 20000 });
    await option.click();
    await page.waitForTimeout(350);
}

async function pickFirstSmart(page: Page, label: string) {
    const trigger = page.getByLabel(label, { exact: false }).first();
    await trigger.scrollIntoViewIfNeeded();
    await trigger.click();
    const option = page.getByRole('option').first();
    await option.waitFor({ state: 'visible', timeout: 20000 });
    await option.click();
    await page.waitForTimeout(300);
}

async function pickDate(page: Page, label: string, iso: string) {
    const trigger = page.getByLabel(label, { exact: false }).first();
    await trigger.scrollIntoViewIfNeeded();
    await trigger.click();
    const dialog = page.getByRole('dialog').last();
    await dialog.waitFor({ state: 'visible', timeout: 15000 });
    for (let i = 0; i < 24; i += 1) {
        const cell = dialog.locator(`[data-date="${iso}"]`).first();
        if (await cell.count()) {
            await cell.click();
            await page.waitForTimeout(400);
            return;
        }
        const next = dialog.getByRole('button', { name: /bulan berikutnya|next month/i }).first();
        if (!(await next.count())) break;
        await next.click();
        await page.waitForTimeout(250);
    }
    throw new Error(`Tanggal ${iso} tidak ditemukan di date picker`);
}

async function openActionsTab(page: Page) {
    // Material Symbols ikut terbaca di accessible name (mis. "bolt Aksi"),
    // dan regex /aksi/ juga cocok dengan menu sidebar "Transaksi".
    // Jadi cari tab di nav detail pinjaman saja.
    const tab = page.locator('nav[aria-label*="Bagian detail"] button').filter({ hasText: 'Aksi' }).first();
    await tab.waitFor({ state: 'visible', timeout: 15000 });
    await tab.click();
    await page.waitForTimeout(500);
}

async function loginTenant(page: Page) {
    await page.goto(`${TENANT}/login`, { waitUntil: 'networkidle', timeout: 45000 });
    if (page.url().includes('/dashboard')) return;

    const username = page.locator('input[autocomplete="username"]').first();
    const password = page.locator('input[autocomplete="current-password"]').first();
    await username.waitFor({ state: 'visible', timeout: 20000 });
    await username.click();
    await username.fill(`${TENANT_CODE}.admin`);
    await password.click();
    await password.fill(TENANT_PASSWORD);
    await page.getByRole('button', { name: /masuk ke dashboard/i }).first().click();
    await page.waitForURL((u) => u.pathname.includes('/dashboard'), { timeout: 45000 });
    await page.waitForTimeout(800);
    await assertHealthy(page, 'dashboard tenant');
}

function uniqueNik(prefix = '3204'): string {
    const stamp = `${Date.now()}`.slice(-10);
    const rand = `${Math.floor(Math.random() * 9000) + 1000}`;
    return `${prefix}${stamp}${rand}`.slice(0, 16);
}

function parseIdNumber(text: string): number {
    const cleaned = text.replace(/[^\d,.-]/g, '').trim();
    if (!cleaned) return 0;
    // Format id-ID: titik = ribuan, koma = desimal.
    const normalized = cleaned.replace(/\./g, '').replace(',', '.');
    const value = Number(normalized);
    return Number.isFinite(value) ? value : 0;
}

/** Tanggal ISO `months` bulan lalu, dengan hari di-clamp ke akhir bulan. */
function isoMonthsAgo(months: number): string {
    const now = new Date();
    const first = new Date(now.getFullYear(), now.getMonth() - months, 1);
    const daysInMonth = new Date(first.getFullYear(), first.getMonth() + 1, 0).getDate();
    const day = Math.min(now.getDate(), daysInMonth);
    const target = new Date(first.getFullYear(), first.getMonth(), day);
    const mm = String(target.getMonth() + 1).padStart(2, '0');
    const dd = String(target.getDate()).padStart(2, '0');
    return `${target.getFullYear()}-${mm}-${dd}`;
}

async function createMember(page: Page, name: string, nik: string): Promise<number> {
    await gotoHealthy(page, `${TENANT}/master-data/members/create`, 'tambah anggota');
    // Form melakukan lookup NIK async. Respons lookup menimpa seluruh form
    // dengan fields(null) bila NIK tidak ditemukan, jadi tunggu lookup selesai
    // SEBELUM mengisi nama/alamat — jika tidak, isian bisa terhapus.
    const lookup = page
        .waitForResponse((r) => r.url().includes('/master-data/members/lookup'), { timeout: 30000 })
        .catch(() => null);
    await page.getByLabel('NIK').first().fill(nik);
    await lookup;
    await page.waitForTimeout(700);

    await page.getByLabel('Nama Lengkap').first().fill(name);
    const birthPlace = page.getByLabel('Tempat Lahir').first();
    if (await birthPlace.count()) await birthPlace.fill('Citeureup');
    await page.getByLabel('Alamat').first().fill('Jl. Uji Alur Lengkap No. 7');
    await pickFirstSmart(page, 'Desa');
    const nameValue = await page.getByLabel('Nama Lengkap').first().inputValue();
    if (nameValue !== name) throw new Error(`Nama terhapus setelah lookup NIK (nilai="${nameValue}")`);
    await shot(page, 'form anggota alur penuh');
    await domClick(page.getByRole('button', { name: /^simpan/i }).first());
    await page.waitForURL(/\/master-data\/members(\?.*)?$/, { timeout: 45000 });
    await page.waitForTimeout(900);
    await assertHealthy(page, 'daftar anggota setelah simpan');

    await gotoHealthy(page, `${TENANT}/master-data/members?search=${encodeURIComponent(nik)}`, 'cari anggota baru');
    const link = page.getByRole('link', { name }).first();
    await link.waitFor({ state: 'visible', timeout: 20000 });
    const href = await link.getAttribute('href');
    const id = Number((href ?? '').split('/').filter(Boolean).pop());
    if (!id) throw new Error('Tidak bisa membaca row_id anggota dari tautan daftar');
    return id;
}

test.setTimeout(35 * 60 * 1000);
test.describe.configure({ mode: 'serial' });

test.beforeAll(async ({ browser }: { browser: Browser }) => {
    const context = await browser.newContext({
        viewport: { width: 1440, height: 960 },
        ignoreHTTPSErrors: true,
        locale: 'id-ID',
        timezoneId: 'Asia/Jakarta',
        acceptDownloads: true,
    });
    runtime.context = context;
    context.setDefaultTimeout(20000);
    context.setDefaultNavigationTimeout(45000);
    runtime.page = await context.newPage();
    runtime.page.on('pageerror', (err) => runtime.consoleErrors.push(`pageerror: ${err.message}`));
    runtime.page.on('console', (msg) => {
        if (msg.type() === 'error') {
            const text = msg.text();
            if (!/favicon|net::ERR_ABORTED|status of 403/i.test(text)) {
                runtime.consoleErrors.push(`console: ${text.slice(0, 240)}`);
            }
        }
    });
});

// Saat suite dijalankan sebagian (mis. --grep "03"), sesi login dari test 01
// belum tentu ada. Pastikan setiap test memulai dengan sesi tenant yang valid.
test.beforeEach(async () => {
    if (runtime.page) await loginTenant(runtime.page);
});

test.afterAll(async () => {
    const failures = results.filter((r) => r.status === 'FAIL');
    const passes = results.filter((r) => r.status === 'PASS');
    const skipped = results.filter((r) => r.status === 'SKIP');

    const lines = [
        `# Live Audit — CRUD Anggota, Pinjaman/Angsuran, Laporan PDF & Excel`,
        ``,
        `- Tanggal: ${new Date().toISOString()}`,
        `- Lokasi: ${TENANT}`,
        `- Font: ${CENTRAL}`,
        `- Anggota uji: ${runtime.memberName || '-'} (row_id ${runtime.memberId || '-'})`,
        `- Pinjaman uji: row_id ${runtime.loanId || '-'}`,
        `- Screenshot: ${OUT}`,
        `- Berkas ekspor: ${EXPORT_DIR}`,
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
        ...results.map((r, i) => `| ${i + 1} | ${r.area} | ${r.step.replace(/\|/g, '\\|')} | ${r.status} | ${r.detail.replace(/\|/g, '\\|').replace(/\n/g, ' ').slice(0, 400)} |`),
        ``,
        `## Console / Page Errors`,
        ``,
        runtime.consoleErrors.length
            ? runtime.consoleErrors.map((e) => `- ${e.replace(/\|/g, '\\|')}`).join('\n')
            : '- Tidak ada error console yang tercatat.',
        ``,
    ];

    fs.writeFileSync(path.join(OUT, 'report-crud-reports.md'), lines.join('\n'), 'utf8');
    fs.writeFileSync(path.join(OUT, 'report-crud-reports.json'), JSON.stringify({ results, consoleErrors: runtime.consoleErrors }, null, 2), 'utf8');

    await runtime.page?.waitForTimeout(1500).catch(() => undefined);
    await runtime.context?.close();

    console.log(`\n📄 Laporan: ${path.join(OUT, 'report-crud-reports.md')}`);
    console.log(`📊 PASS=${passes.length} FAIL=${failures.length} SKIP=${skipped.length}`);
});

test('01 — Login tenant sebagai admin', async () => {
    await step('Auth', 'Login manual admin tenant', async () => {
        await loginTenant(runtime.page!);
        await shot(runtime.page!, 'dashboard login alur penuh');
        return runtime.page!.url();
    }, { critical: true });
});

test('02 — CRUD anggota lengkap: tambah, simpan, detail, edit, hapus', async () => {
    const page = runtime.page!;
    runtime.memberName = `Anggota Alur ${Date.now().toString().slice(-6)}`;
    runtime.memberNik = uniqueNik();

    await step('Anggota', 'Tambah & simpan anggota baru', async () => {
        runtime.memberId = await createMember(page, runtime.memberName, runtime.memberNik);
        await shot(page, 'anggota baru tersimpan');
        return `${runtime.memberName} / ${runtime.memberNik} (row_id ${runtime.memberId})`;
    }, { critical: true });

    await step('Anggota', 'Buka detail & verifikasi NIK', async () => {
        await gotoHealthy(page, `${TENANT}/master-data/members/${runtime.memberId}`, 'detail anggota');
        const body = await bodyText(page);
        if (!body.includes(runtime.memberNik)) throw new Error('NIK tidak tampil di detail anggota');
        if (!body.includes(runtime.memberName)) throw new Error('Nama tidak tampil di detail anggota');
        await shot(page, 'detail anggota baru');
        return page.url();
    });

    await step('Anggota', 'Edit nomor HP & simpan', async () => {
        await page.locator(`a[href="/master-data/members/${runtime.memberId}/edit"]`).first().click();
        await page.waitForURL(/\/master-data\/members\/\d+\/edit/, { timeout: 30000 });
        await page.waitForTimeout(500);
        const phone = page.getByLabel('No. HP').first();
        await phone.fill('081298765432');
        await shot(page, 'form edit anggota');
        await domClick(page.getByRole('button', { name: /^simpan/i }).first());
        await page.waitForURL(/\/master-data\/members(\?.*)?$/, { timeout: 40000 });
        await page.waitForTimeout(800);
        await assertHealthy(page, 'setelah edit anggota');
        return 'HP diperbarui menjadi 081298765432';
    });

    await step('Anggota', 'Hapus anggota (konfirmasi dialog)', async () => {
        await gotoHealthy(page, `${TENANT}/master-data/members/${runtime.memberId}`, 'detail sebelum hapus');
        await domClick(page.getByRole('button', { name: /hapus anggota/i }).first());
        const dialog = page.getByRole('dialog').first();
        await dialog.waitFor({ state: 'visible', timeout: 15000 });
        await shot(page, 'dialog konfirmasi hapus anggota');
        await domClick(dialog.getByRole('button', { name: /ya, hapus anggota/i }).first());
        await page.waitForURL(/\/master-data\/members(\?.*)?$/, { timeout: 40000 });
        await page.waitForTimeout(800);
        await assertHealthy(page, 'setelah hapus anggota');

        await gotoHealthy(page, `${TENANT}/master-data/members?search=${encodeURIComponent(runtime.memberNik)}`, 'cari anggota terhapus');
        const body = await bodyText(page);
        if (body.includes(runtime.memberName)) throw new Error('Anggota masih muncul di daftar setelah dihapus');
        await shot(page, 'anggota terhapus dari daftar');
        return 'anggota hilang dari daftar';
    });
});

test('03 — Alur pinjaman individu: proposal → verifikasi → alokasi → cair → angsuran', async () => {
    const page = runtime.page!;
    runtime.memberName = `Anggota Angsuran ${Date.now().toString().slice(-6)}`;
    runtime.memberNik = uniqueNik('3205');

    await step('Pinjaman', 'Siapkan anggota peminjam', async () => {
        // Anggota sebelumnya dihapus pada test 02; buat anggota khusus pinjaman.
        runtime.memberId = await createMember(page, runtime.memberName, runtime.memberNik);
        return `${runtime.memberName} (row_id ${runtime.memberId})`;
    }, { critical: true });

    await step('Pinjaman', 'Buat proposal pinjaman individu', async () => {
        await gotoHealthy(page, `${TENANT}/lending/member-loans/create`, 'form proposal pinjaman');
        await pickSmart(page, 'Produk', 'Pinjaman Individu');
        await pickSmart(page, 'Anggota', runtime.memberName);
        await page.getByLabel('Plafon').first().fill('1200000');
        await page.getByLabel('Tenor (bln)').first().fill('3');
        const rate = page.getByLabel('Total Jasa (%)').first();
        if (!(await rate.inputValue()).trim()) await rate.fill('3');
        await shot(page, 'form proposal pinjaman terisi');
        await domClick(page.getByRole('button', { name: /simpan proposal/i }).first());
        await page.waitForURL(/\/lending\/member-loans\/\d+/, { timeout: 45000 });
        await page.waitForTimeout(1200);
        await assertHealthy(page, 'detail proposal pinjaman');
        runtime.loanId = Number(page.url().split('/').filter(Boolean).pop());
        const body = await bodyText(page);
        if (!/draft|proposal/i.test(body)) throw new Error('Status proposal tidak terbaca di detail');
        await shot(page, 'detail proposal dibuat');
        return `loan row_id ${runtime.loanId}`;
    }, { critical: true });

    await step('Pinjaman', 'Verifikasi proposal', async () => {
        await gotoHealthy(page, `${TENANT}/lending/member-loans/${runtime.loanId}`, 'detail pinjaman');
        await openActionsTab(page);
        await page.getByText('Form Verifikasi', { exact: false }).first().waitFor({ state: 'visible', timeout: 15000 });
        // Backdate satu bulan agar angsuran pertama jatuh tempo hari ini dan
        // bisa diposting (aturan: tanggal transaksi tidak boleh di masa depan).
        await page.getByLabel('Tanggal Verifikasi').first().fill(isoMonthsAgo(1));
        const responsePromise = page.waitForResponse(
            (r) => r.url().includes(`/lending/member-loans/${runtime.loanId}/verify`) && r.request().method() === 'PATCH',
            { timeout: 40000 },
        );
        await domClick(page.getByRole('button', { name: /simpan & verifikasi/i }).first());
        const response = await responsePromise;
        await assertHealthy(page, 'setelah verifikasi');
        if (response.status() >= 500) throw new Error(`HTTP ${response.status()} saat verifikasi`);
        // Re-render Inertia bisa lebih lambat dari response; tunggu form tahap berikutnya.
        const nextForm = page.getByText('Penetapan Alokasi', { exact: false }).first();
        if (!(await nextForm.waitFor({ state: 'visible', timeout: 20000 }).then(() => true).catch(() => false))) {
            const body = await bodyText(page);
            throw new Error(`Form Penetapan Alokasi tidak muncul. Cuplikan: ${body.replace(/\s+/g, ' ').slice(0, 300)}`);
        }
        await shot(page, 'setelah verifikasi');
        return `HTTP ${response.status()}`;
    });

    await step('Pinjaman', 'Tetapkan alokasi', async () => {
        const approvedAt = isoMonthsAgo(1);
        await page.getByLabel('Tanggal Persetujuan').first().fill(approvedAt);
        await page.getByLabel('Rencana Tanggal Cair').first().fill(approvedAt);
        const responsePromise = page.waitForResponse(
            (r) => r.url().includes(`/lending/member-loans/${runtime.loanId}/approve`) && r.request().method() === 'PATCH',
            { timeout: 40000 },
        );
        await domClick(page.getByRole('button', { name: /simpan & alokasikan/i }).first());
        const response = await responsePromise;
        await assertHealthy(page, 'setelah alokasi');
        if (response.status() >= 500) throw new Error(`HTTP ${response.status()} saat alokasi`);
        const nextForm = page.getByText('Form Pencairan', { exact: false }).first();
        if (!(await nextForm.waitFor({ state: 'visible', timeout: 20000 }).then(() => true).catch(() => false))) {
            const body = await bodyText(page);
            throw new Error(`Form Pencairan tidak muncul. Cuplikan: ${body.replace(/\s+/g, ' ').slice(0, 300)}`);
        }
        await shot(page, 'setelah alokasi');
        return `HTTP ${response.status()}`;
    });

    await step('Pinjaman', 'Catat pencairan & bentuk jadwal angsuran', async () => {
        await page.getByLabel('Tanggal Cair').first().fill(isoMonthsAgo(1));
        const account = page.getByLabel('Rekening Sumber Dana').first();
        await account.waitFor({ state: 'visible', timeout: 15000 });
        await account.selectOption({ index: 1 });
        await shot(page, 'form pencairan terisi');
        const responsePromise = page.waitForResponse(
            (r) => r.url().includes(`/lending/member-loans/${runtime.loanId}/disburse`) && r.request().method() === 'PATCH',
            { timeout: 45000 },
        );
        await domClick(page.getByRole('button', { name: /catat pencairan/i }).first());
        const response = await responsePromise;
        await page.waitForTimeout(1800);
        await assertHealthy(page, 'setelah pencairan');
        if (response.status() >= 500) throw new Error(`HTTP ${response.status()} saat pencairan`);
        return `HTTP ${response.status()}`;
    });

    await step('Pinjaman', 'Verifikasi status aktif & jadwal angsuran', async () => {
        await gotoHealthy(page, `${TENANT}/lending/member-loans/${runtime.loanId}`, 'detail pinjaman aktif');
        let body = await bodyText(page);
        if (!/aktif|disbursed|angsuran/i.test(body)) throw new Error('Status aktif tidak terlihat');
        await domClick(page.getByRole('button', { name: /jadwal angsuran/i }).first());
        await page.waitForTimeout(500);
        const rows = page.locator('table tbody tr').filter({ hasText: /Angsuran #/i });
        const count = await rows.count();
        if (count < 1) throw new Error('Jadwal angsuran kosong setelah pencairan');
        let found = false;
        let firstPrincipalText = '';
        let firstInterestText = '';
        for (let i = 0; i < count; i += 1) {
            const cells = rows.nth(i).locator('td');
            const principalText = (await cells.nth(2).innerText()).trim();
            const interestText = (await cells.nth(3).innerText()).trim();
            const principal = parseIdNumber(principalText);
            if (principal > 0) {
                runtime.paidInstallment.principal = principal;
                runtime.paidInstallment.interest = parseIdNumber(interestText);
                firstPrincipalText = principalText;
                firstInterestText = interestText;
                found = true;
                break;
            }
        }
        if (!found) throw new Error('Tidak ada baris angsuran dengan pokok > 0');

        // Ambil due date ISO dari props Inertia untuk dipakai saat memilih
        // tanggal pada form angsuran (baris #0 placeholder bukan angsuran riil).
        const data = await page.evaluate(() => {
            const raw = document.getElementById('app')?.getAttribute('data-page');
            try { return raw ? JSON.parse(raw) : null; } catch { return null; }
        });
        const rawRows = (data?.props?.loan?.installments ?? []) as Array<Record<string, unknown>>;
        const grouped = new Map<number, { n: number; due: string; principal: number; paid: number }>();
        for (const row of rawRows) {
            const n = Number(row.installment_number);
            const current = grouped.get(n) ?? { n, due: String(row.due_date ?? ''), principal: 0, paid: 0 };
            current.principal += Number(row.principal_due ?? 0);
            current.paid += Number(row.principal_paid ?? 0);
            grouped.set(n, current);
        }
        const firstReal = [...grouped.values()]
            .filter((row) => row.principal - row.paid > 0)
            .sort((a, b) => a.n - b.n)[0];
        if (!firstReal?.due) throw new Error('Due date angsuran pertama tidak ditemukan di props');
        runtime.paidInstallment.dueDate = firstReal.due;

        await shot(page, 'jadwal angsuran terbentuk');
        return `${count} baris jadwal; angsuran pertama pokok ${firstPrincipalText}, jasa ${firstInterestText}, jatuh tempo ${firstReal.due}`;
    }, { critical: true });

    await step('Angsuran', 'Posting pembayaran angsuran pertama', async () => {
        await gotoHealthy(page, `${TENANT}/accounting/journal-entries/installment-individual`, 'form angsuran individu');
        await pickSmart(page, 'Pinjaman Perorangan', runtime.memberName);
        // Tanggal harus >= jatuh tempo angsuran riil pertama; jika memakai hari ini,
        // form memilih baris placeholder #0 yang tidak lolos validasi min:1.
        await pickDate(page, 'Tanggal Angsuran', runtime.paidInstallment.dueDate);
        await page.waitForTimeout(600);
        const memberSearch = page.getByPlaceholder(/cari anggota/i).first();
        if (await memberSearch.count()) {
            await memberSearch.fill(runtime.memberName);
            await page.waitForTimeout(400);
        }
        await page.getByText('Kecocokan terkonfirmasi', { exact: false }).waitFor({ state: 'visible', timeout: 15000 }).catch(() => undefined);
        await page.getByLabel('Pokok').first().fill(String(runtime.paidInstallment.principal));
        await page.getByLabel('Jasa').first().fill(String(runtime.paidInstallment.interest));
        await pickFirstSmart(page, 'Tujuan (Akun Debit)');
        await shot(page, 'form angsuran terisi');
        const responsePromise = page.waitForResponse(
            (r) => r.url().includes('/accounting/journal-entries/installment-individual') && r.request().method() === 'POST',
            { timeout: 45000 },
        );
        await domClick(page.getByRole('button', { name: /catat jurnal angsuran/i }).first());
        const response = await responsePromise;
        await page.waitForTimeout(1800);
        await assertHealthy(page, 'setelah posting angsuran');
        if (response.status() >= 500) throw new Error(`HTTP ${response.status()} saat posting angsuran`);
        const body = await bodyText(page);
        if (!/berhasil|angsuran|jurnal/i.test(body)) throw new Error('Tidak ada konfirmasi setelah posting angsuran');
        await shot(page, 'angsuran berhasil diposting');
        return `HTTP ${response.status()}`;
    });

    await step('Angsuran', 'Verifikasi angsuran tercatat di detail pinjaman', async () => {
        await gotoHealthy(page, `${TENANT}/lending/member-loans/${runtime.loanId}`, 'detail pinjaman setelah angsuran');
        const body = await bodyText(page);
        const match = body.match(/(\d+)\s*\/\s*(\d+)\s*angsuran/i);
        if (!match) throw new Error('Ringkasan angsuran lunas tidak ditemukan');
        const paid = Number(match[1]);
        if (paid < 1) throw new Error(`Angsuran lunas masih ${paid}`);
        await shot(page, 'angsuran tercatat');
        return `${match[0]}`;
    });
});

// ------------------------------------------------------------------
// LAPORAN: halaman + ekspor PDF/Excel
// ------------------------------------------------------------------

const REPORT_PAGES: Array<[string, string]> = [
    ['Portfolio', '/lending/reports/portfolio'],
    ['Jatuh Tempo', '/lending/reports/due-today'],
    ['Tunggakan', '/lending/reports/overdue'],
    ['Pinjaman Lunas', '/lending/reports/paid'],
    ['Proposal', '/lending/reports/proposals'],
    ['Verifikasi', '/lending/reports/verifications'],
    ['Waiting List', '/lending/reports/waiting-list'],
    ['Penghapusan', '/lending/reports/write-offs'],
    ['Penghapusan Individu', '/lending/reports/write-offs-individu'],
    ['Near Settlement', '/lending/reports/near-settlement'],
    ['Jadwal vs Aktual', '/lending/reports/schedule-vs-actual'],
    ['Jadwal vs Aktual Individu', '/lending/reports/schedule-vs-actual-individu'],
    ['Kolek Desa', '/lending/reports/kolek-desa'],
    ['Kolek Individu', '/lending/reports/kolek-individu'],
    ['Kolek Kelompok', '/lending/reports/kolek-kelompok'],
    ['Kolek Desa Individu', '/lending/reports/kolek-desa-individu'],
    ['LPP Desa', '/lending/reports/lpp-desa'],
    ['LPP Individu', '/lending/reports/lpp-individu'],
    ['LPP Kelompok', '/lending/reports/lpp-kelompok'],
    ['LPP Desa Individu', '/lending/reports/lpp-desa-individu'],
    ['Cadangan Penghapusan', '/lending/reports/cadangan-penghapusan'],
    ['Cadangan Penghapusan Individu', '/lending/reports/cadangan-penghapusan-individu'],
    ['Anggota Aktif', '/lending/reports/members/active'],
    ['Kelompok Aktif', '/lending/reports/groups/active'],
    ['Pemanfaat Aktif', '/lending/reports/beneficiaries/active'],
    ['Neraca', '/accounting/reports/balance-sheet'],
    ['Laba Rugi', '/accounting/reports/income-statement'],
    ['Arus Kas', '/accounting/reports/cash-flow'],
    ['Buku Besar', '/accounting/reports/general-ledger'],
    ['Neraca Saldo', '/accounting/reports/trial-balance'],
    ['Perubahan Ekuitas', '/accounting/reports/equity-change'],
    ['Jurnal', '/accounting/reports/journals'],
    ['Anggaran', '/accounting/reports/budgeting'],
    ['Kesehatan Keuangan', '/accounting/reports/financial-health'],
    ['CALK', '/accounting/reports/calk'],
    ['Simpanan', '/accounting/reports/simpanan'],
    ['OJK Kesehatan', '/regulatory/ojk/health'],
    ['OJK Kolektibilitas', '/regulatory/ojk/collectibility'],
    ['OJK Kolektibilitas v2', '/regulatory/ojk/collectibility-v2'],
    ['OJK Profil', '/regulatory/ojk/profile'],
    ['OJK Simpanan', '/regulatory/ojk/savings'],
    ['OJK Pinjaman Aktif', '/regulatory/ojk/active-loans'],
    ['OJK Penyisihan', '/regulatory/ojk/allowance'],
    ['OJK Neraca', '/regulatory/ojk/balance-sheet'],
    ['OJK Cover', '/regulatory/ojk/cover'],
    ['OJK Laba Rugi', '/regulatory/ojk/income-statement'],
    ['OJK Jasa Simpanan', '/regulatory/ojk/interest'],
    ['OJK Pinjaman Diterima', '/regulatory/ojk/loans-received'],
    ['OJK Pinjaman Lunas Kelompok', '/regulatory/ojk/paid-group'],
    ['OJK Pinjaman Lunas Individu', '/regulatory/ojk/paid-individu'],
    ['OJK Piutang Simpanan', '/regulatory/ojk/savings-receivables'],
];

const GL_EXPORT = '/accounting/reports/general-ledger';
const GL_ACCOUNT_ROW_ID = '478'; // Kas Tunai (tenant ASTA)

const EXPORTS: Array<{ name: string; url: string; kind: 'pdf' | 'excel' }> = [
    { name: 'lending-portfolio', url: '/lending/reports/portfolio/pdf', kind: 'pdf' },
    { name: 'lending-due-today', url: '/lending/reports/due-today/pdf', kind: 'pdf' },
    { name: 'lending-due-today', url: '/lending/reports/due-today/excel', kind: 'excel' },
    { name: 'lending-overdue', url: '/lending/reports/overdue/pdf', kind: 'pdf' },
    { name: 'lending-overdue', url: '/lending/reports/overdue/excel', kind: 'excel' },
    { name: 'lending-paid', url: '/lending/reports/paid/pdf', kind: 'pdf' },
    { name: 'lending-proposals', url: '/lending/reports/proposals/pdf', kind: 'pdf' },
    { name: 'lending-proposals', url: '/lending/reports/proposals/excel', kind: 'excel' },
    { name: 'lending-verifications', url: '/lending/reports/verifications/pdf', kind: 'pdf' },
    { name: 'lending-verifications', url: '/lending/reports/verifications/excel', kind: 'excel' },
    { name: 'lending-waiting-list', url: '/lending/reports/waiting-list/pdf', kind: 'pdf' },
    { name: 'lending-waiting-list', url: '/lending/reports/waiting-list/excel', kind: 'excel' },
    { name: 'lending-write-offs', url: '/lending/reports/write-offs/pdf', kind: 'pdf' },
    { name: 'lending-write-offs', url: '/lending/reports/write-offs/excel', kind: 'excel' },
    { name: 'lending-write-offs-individu', url: '/lending/reports/write-offs-individu/pdf', kind: 'pdf' },
    { name: 'lending-write-offs-individu', url: '/lending/reports/write-offs-individu/excel', kind: 'excel' },
    { name: 'lending-near-settlement', url: '/lending/reports/near-settlement/pdf', kind: 'pdf' },
    { name: 'lending-schedule-vs-actual', url: '/lending/reports/schedule-vs-actual/pdf', kind: 'pdf' },
    { name: 'lending-schedule-vs-actual-individu', url: '/lending/reports/schedule-vs-actual-individu/pdf', kind: 'pdf' },
    { name: 'lending-schedule-vs-actual-individu', url: '/lending/reports/schedule-vs-actual-individu/excel', kind: 'excel' },
    { name: 'lending-kolek-desa', url: '/lending/reports/kolek-desa/pdf', kind: 'pdf' },
    { name: 'lending-kolek-individu', url: '/lending/reports/kolek-individu/pdf', kind: 'pdf' },
    { name: 'lending-kolek-kelompok', url: '/lending/reports/kolek-kelompok/pdf', kind: 'pdf' },
    { name: 'lending-kolek-desa-individu', url: '/lending/reports/kolek-desa-individu/pdf', kind: 'pdf' },
    { name: 'lending-lpp-desa', url: '/lending/reports/lpp-desa/pdf', kind: 'pdf' },
    { name: 'lending-lpp-individu', url: '/lending/reports/lpp-individu/pdf', kind: 'pdf' },
    { name: 'lending-lpp-kelompok', url: '/lending/reports/lpp-kelompok/pdf', kind: 'pdf' },
    { name: 'lending-lpp-desa-individu', url: '/lending/reports/lpp-desa-individu/pdf', kind: 'pdf' },
    { name: 'lending-cadangan', url: '/lending/reports/cadangan-penghapusan/pdf', kind: 'pdf' },
    { name: 'lending-cadangan-individu', url: '/lending/reports/cadangan-penghapusan-individu/pdf', kind: 'pdf' },
    { name: 'lending-members-active', url: '/lending/reports/members/active/pdf', kind: 'pdf' },
    { name: 'lending-groups-active', url: '/lending/reports/groups/active/pdf', kind: 'pdf' },
    { name: 'lending-beneficiaries-active', url: '/lending/reports/beneficiaries/active/pdf', kind: 'pdf' },
    { name: 'accounting-balance-sheet', url: '/accounting/reports/balance-sheet/pdf', kind: 'pdf' },
    { name: 'accounting-balance-sheet', url: '/accounting/reports/balance-sheet/excel', kind: 'excel' },
    { name: 'accounting-income-statement', url: '/accounting/reports/income-statement/pdf', kind: 'pdf' },
    { name: 'accounting-income-statement', url: '/accounting/reports/income-statement/excel', kind: 'excel' },
    { name: 'accounting-cash-flow', url: '/accounting/reports/cash-flow/pdf', kind: 'pdf' },
    { name: 'accounting-cash-flow', url: '/accounting/reports/cash-flow/excel', kind: 'excel' },
    { name: 'accounting-general-ledger', url: `${GL_EXPORT}/pdf?account=${GL_ACCOUNT_ROW_ID}`, kind: 'pdf' },
    { name: 'accounting-general-ledger', url: `${GL_EXPORT}/excel?account=${GL_ACCOUNT_ROW_ID}`, kind: 'excel' },
    { name: 'accounting-trial-balance', url: '/accounting/reports/trial-balance/pdf', kind: 'pdf' },
    { name: 'accounting-trial-balance', url: '/accounting/reports/trial-balance/excel', kind: 'excel' },
    { name: 'accounting-equity-change', url: '/accounting/reports/equity-change/pdf', kind: 'pdf' },
    { name: 'accounting-equity-change', url: '/accounting/reports/equity-change/excel', kind: 'excel' },
    { name: 'accounting-journals', url: '/accounting/reports/journals/pdf', kind: 'pdf' },
    { name: 'accounting-journals', url: '/accounting/reports/journals/excel', kind: 'excel' },
    { name: 'accounting-budgeting', url: '/accounting/reports/budgeting/pdf', kind: 'pdf' },
    { name: 'accounting-budgeting', url: '/accounting/reports/budgeting/excel', kind: 'excel' },
    { name: 'accounting-financial-health', url: '/accounting/reports/financial-health/pdf', kind: 'pdf' },
    { name: 'accounting-calk', url: '/accounting/reports/calk/pdf', kind: 'pdf' },
    { name: 'accounting-simpanan', url: '/accounting/reports/simpanan/pdf', kind: 'pdf' },
    { name: 'accounting-simpanan', url: '/accounting/reports/simpanan/excel', kind: 'excel' },
    { name: 'accounting-assets-fixed', url: '/accounting/reports/assets/fixed/pdf', kind: 'pdf' },
    { name: 'accounting-assets-fixed', url: '/accounting/reports/assets/fixed/excel', kind: 'excel' },
    { name: 'accounting-assets-intangible', url: '/accounting/reports/assets/intangible/pdf', kind: 'pdf' },
    { name: 'accounting-assets-intangible', url: '/accounting/reports/assets/intangible/excel', kind: 'excel' },
    { name: 'ojk-health', url: '/regulatory/ojk/health/pdf', kind: 'pdf' },
    { name: 'ojk-collectibility', url: '/regulatory/ojk/collectibility/pdf', kind: 'pdf' },
    { name: 'ojk-collectibility-v2', url: '/regulatory/ojk/collectibility-v2/pdf', kind: 'pdf' },
    { name: 'ojk-profile', url: '/regulatory/ojk/profile/pdf', kind: 'pdf' },
    { name: 'ojk-savings', url: '/regulatory/ojk/savings/pdf', kind: 'pdf' },
    { name: 'ojk-active-loans', url: '/regulatory/ojk/active-loans/pdf', kind: 'pdf' },
    { name: 'ojk-allowance', url: '/regulatory/ojk/allowance/pdf', kind: 'pdf' },
    { name: 'ojk-balance-sheet', url: '/regulatory/ojk/balance-sheet/pdf', kind: 'pdf' },
    { name: 'ojk-cover', url: '/regulatory/ojk/cover/pdf', kind: 'pdf' },
    { name: 'ojk-income-statement', url: '/regulatory/ojk/income-statement/pdf', kind: 'pdf' },
    { name: 'ojk-interest', url: '/regulatory/ojk/interest/pdf', kind: 'pdf' },
    { name: 'ojk-loans-received', url: '/regulatory/ojk/loans-received/pdf', kind: 'pdf' },
    { name: 'ojk-paid-group', url: '/regulatory/ojk/paid-group/pdf', kind: 'pdf' },
    { name: 'ojk-paid-individu', url: '/regulatory/ojk/paid-individu/pdf', kind: 'pdf' },
    { name: 'ojk-savings-receivables', url: '/regulatory/ojk/savings-receivables/pdf', kind: 'pdf' },
];

test('04 — Jelajah semua halaman laporan', async () => {
    const page = runtime.page!;

    for (const [label, route] of REPORT_PAGES) {
        await step('Laporan', `${label} (${route})`, async () => {
            const status = await gotoHealthy(page, `${TENANT}${route}`, label);
            if (status >= 500) throw new Error(`HTTP ${status}`);
            if (status === 404) throw new Error('HTTP 404 — rute laporan tidak ditemukan');
            if (status === 403) return 'HTTP 403 — dibatasi permission/role';
            const text = await bodyText(page);
            return `HTTP ${status}, ${text.length} char`;
        });
    }

    record('Laporan', 'Aset Tetap & Aset Tidak Berwujud', 'INFO', 'Tidak ada halaman UI; hanya tersedia ekspor PDF/Excel (divalidasi di matriks ekspor)');

    await shot(page, 'daftar halaman laporan selesai');
});

test('05 — Ekspor PDF & Excel dari UI (unduhan nyata)', async () => {
    const page = runtime.page!;
    const context = runtime.context!;

    const uiReports: Array<[string, string]> = [
        ['Jatuh Tempo', '/lending/reports/due-today'],
        ['Tunggakan', '/lending/reports/overdue'],
        ['Neraca', '/accounting/reports/balance-sheet'],
        ['Laba Rugi', '/accounting/reports/income-statement'],
    ];

    for (const [label, route] of uiReports) {
        await step('Ekspor UI', `Tombol PDF & Excel — ${label}`, async () => {
            await gotoHealthy(page, `${TENANT}${route}`, label);
            const pdfAnchor = page.locator('a[href*="/pdf"]').first();
            const excelAnchor = page.locator('a[href*="/excel"]').first();
            await pdfAnchor.waitFor({ state: 'visible', timeout: 15000 });
            await excelAnchor.waitFor({ state: 'visible', timeout: 15000 });
            const pdfHref = await pdfAnchor.getAttribute('href');
            const excelHref = await excelAnchor.getAttribute('href');
            await shot(page, `tombol ekspor ${label}`);

            const pdfRes = await context.request.get(`${TENANT}${pdfHref}`, { timeout: 90000 });
            const pdfBody = await pdfRes.body();
            if (pdfRes.status() !== 200) throw new Error(`PDF HTTP ${pdfRes.status()}`);
            if (!pdfBody.subarray(0, 5).toString('latin1').startsWith('%PDF')) {
                throw new Error(`PDF bukan berkas PDF (magic ${pdfBody.subarray(0, 12).toString('latin1')})`);
            }

            // Klik Excel: harus memicu event download browser.
            const [download] = await Promise.all([
                page.waitForEvent('download', { timeout: 45000 }),
                excelAnchor.click(),
            ]);
            const saved = path.join(EXPORT_DIR, `${label.toLowerCase().replace(/[^a-z0-9]+/g, '-')}-ui.xlsx`);
            await download.saveAs(saved);
            const size = fs.statSync(saved).size;
            const head = fs.readFileSync(saved).subarray(0, 4);
            if (!(head[0] === 0x50 && head[1] === 0x4b)) {
                throw new Error(`Unduhan Excel bukan XLSX (magic ${head.toString('latin1')})`);
            }

            // Klik PDF: biasanya membuka tab baru (target=_blank). Tidak wajib popup,
            // karena validasi isi sudah dilakukan lewat request di atas.
            const popupPromise = page.waitForEvent('popup', { timeout: 8000 }).catch(() => null);
            await pdfAnchor.click();
            const popup = await popupPromise;
            if (popup) {
                await popup.waitForLoadState('domcontentloaded').catch(() => undefined);
                await popup.close().catch(() => undefined);
            }

            return `PDF ${pdfBody.length} byte (${pdfHref}); Excel ${size} byte (${excelHref})`;
        });
    }
});

test('06 — Matriks ekspor semua laporan (PDF & Excel)', async () => {
    const context = runtime.context!;

    for (const item of EXPORTS) {
        await step('Matriks Ekspor', `${item.name} .${item.kind}`, async () => {
            const response = await context.request.get(`${TENANT}${item.url}`, { timeout: 90000 });
            const status = response.status();
            if (status !== 200) {
                const body = (await response.text()).replace(/\s+/g, ' ').slice(0, 220);
                throw new Error(`HTTP ${status} — ${body}`);
            }
            const body = await response.body();
            const contentType = response.headers()['content-type'] ?? '';
            if (item.kind === 'pdf') {
                if (!body.subarray(0, 5).toString('latin1').startsWith('%PDF')) {
                    throw new Error(`Bukan PDF (content-type ${contentType}, magic ${body.subarray(0, 16).toString('latin1')})`);
                }
            } else {
                const isZip = body[0] === 0x50 && body[1] === 0x4b;
                const isCsv = /csv|text\/plain/i.test(contentType);
                if (!isZip && !isCsv) {
                    throw new Error(`Bukan Excel/CSV (content-type ${contentType}, magic ${body.subarray(0, 16).toString('latin1')})`);
                }
            }
            if (body.length < 400) {
                throw new Error(`Berkas terlalu kecil (${body.length} byte), kemungkinan halaman error`);
            }
            const ext = item.kind === 'pdf' ? 'pdf' : 'xlsx';
            fs.writeFileSync(path.join(EXPORT_DIR, `${item.name}.${ext}`), body);
            return `HTTP 200, ${body.length} byte, ${contentType}`;
        });
    }
});
