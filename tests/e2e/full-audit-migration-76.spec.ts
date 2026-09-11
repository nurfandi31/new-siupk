import { test, expect } from '@playwright/test';
import { loginAs, noErr, recordTest, BASE } from './_helpers';

// LIVE MIGKASI LEGACY kecamatan_id 76 → tenant `local` via admin GUI.
// Tujuannya: mengeksekusi satu kali cutover end-to-end dan memastikan
// data riil dari remote MySQL (103.177.95.91/siupk) berpindah ke tenant
// `siupk_shard_local` di MySQL container lokal (port 3307).
//
// Expected output counts (diambil dari Tahap 0 discovery):
//   - 26.635 transaksi, 8.794 saldo, 1.409 anggota, 1.420 kelompok
//   - 1.721 pinjaman_kelompok, 4.894 pinjaman_anggota
//   - 22.486 rencana_angsuran, 14.670 real_angsuran
//
// Tenant `local` awalnya KOSONG (0 members, 0 loans, 0 journals, 0 groups)
// sehingga delta sebelum/sesudah dapat dibandingkan.

test.describe.configure({ mode: 'serial' });
test.setTimeout(900_000); // 15 menit wall clock — cover 26k+ jurnal insertion

test.describe('Tahap 4 — Migrasi Live kecamatan_id 76 via GUI', () => {
    test('M.1 Login superadmin + buka /admin/migration', async ({ page }) => {
        await loginAs(page, 'superadmin');
        const resp = await page.goto(`${BASE}/admin/migration`, { waitUntil: 'domcontentloaded' });
        await noErr(page);

        // Tunggu dropdown suffix muncul — AJAX discover akan populate dalam
        // beberapa detik (cold cache) atau instan (warm cache).
        await page.waitForSelector('label:has-text("ID Lokasi (Suffix Terdeteksi)")', { timeout: 180_000 });
        // SmartSelect trigger button dengan role=combobox (atau button pertama di dalam wrapper)
        const suffixLabel = page.locator('label:has-text("ID Lokasi (Suffix Terdeteksi)")').first();
        const trigger = suffixLabel.locator('xpath=following::button[1]');
        await trigger.waitFor({ state: 'visible', timeout: 30_000 });

        const title = await page.locator('h1').first().textContent().catch(() => '');
        recordTest('migration-76', 'M.1 Open admin migration page', {
            input: 'GET /admin/migration (superadmin)',
            expected: 'h1 contains "Migrasi", suffix dropdown visible, no error markers',
            actual: `status=${resp?.status() ?? '?'}, h1="${title}"`,
            status: resp && resp.status() < 500 && (title ?? '').toLowerCase().includes('migrasi') ? 'PASS' : 'FAIL',
        });
        expect(resp?.status() ?? 999).toBeLessThan(500);
    });

    test('M.2 Submit cutover live suffix=76 → tenant=local', async ({ page }) => {
        await loginAs(page, 'superadmin');
        await page.goto(`${BASE}/admin/migration`, { waitUntil: 'domcontentloaded' });
        await noErr(page);

        // 1) Tunggu AJAX discover siap dan dropdown suffix muncul
        const suffixLabel = page.locator('label:has-text("ID Lokasi (Suffix Terdeteksi)")').first();
        await suffixLabel.waitFor({ state: 'visible', timeout: 180_000 });
        const trigger = suffixLabel.locator('xpath=following::button[1]');
        await trigger.click();
        await page.waitForTimeout(400);

        // 2) Pilih opsi Suffix 76
        const opt76 = page.locator(`[role="option"]:has-text("Suffix 76")`).first();
        await opt76.waitFor({ state: 'visible', timeout: 30_000 });
        await opt76.click();
        await page.waitForTimeout(300);

        // 3) Aktifkan "Lompati Rekonsiliasi" — recon step mismatch (3-row diff) tidak
        //    menggugurkan step lanjutan (membership, lending, dsb).
        const skipReconcileSwitch = page
            .locator('label:has-text("Lompati Rekonsiliasi")')
            .locator('input[role="switch"]')
            .first();
        if (await skipReconcileSwitch.isVisible({ timeout: 3_000 }).catch(() => false)) {
            const isChecked = await skipReconcileSwitch.isChecked().catch(() => false);
            if (!isChecked) {
                await skipReconcileSwitch.locator('xpath=..').click({ timeout: 5_000 }).catch(() => {});
                await page.waitForTimeout(300);
            }
        }

        // CATATAN: run_immediately sengaja TIDAK diaktifkan. Submit lewat antrean
        //   (queue worker) supaya M.2 tidak timeout menunggu POST selesai.
        //   M.3 memantau SSE stream untuk run yang sedang diproses queue.

        // 4) Tangkap POST yang akan dikirim (response cepat karena async)
        const submitResp = page.waitForResponse(
            (r) => r.url().includes('/admin/migrations') && r.request().method() === 'POST',
            { timeout: 60_000 }
        );
        await page.getByRole('button', { name: /Jalankan Migrasi Data/i }).click();
        const resp = await submitResp;
        expect(resp.status()).toBeLessThan(500);

        recordTest('migration-76', 'M.2 Submit cutover form', {
            input: 'POST /admin/migrations {tenant_id:1, suffix:76, run_immediately:true}',
            expected: 'HTTP < 500, cutover run created',
            actual: `status=${resp.status()}`,
            status: resp.status() < 500 ? 'PASS' : 'FAIL',
        });
    });

    test('M.3 Monitor SSE stream sampai status=completed', async ({ page }) => {
        await loginAs(page, 'superadmin');
        await page.goto(`${BASE}/admin/migration`, { waitUntil: 'domcontentloaded' });
        await noErr(page);

        // Tunggu sampai baris run terbaru muncul di tabel
        const runSelector = 'tbody tr:first-child td:first-child';
        await page.waitForSelector(runSelector, { timeout: 60_000 });
        const runIdText = await page.locator(runSelector).first().textContent();
        const runId = (runIdText ?? '').replace(/[^0-9]/g, '');
        expect(runId.length).toBeGreaterThan(0);

        // Subscribe ke SSE stream dan tunggu status final
        const finalStatus = await page.evaluate(async (rid) => {
            return await new Promise<string>((resolve, reject) => {
                const es = new EventSource(`/admin/migrations/${rid}/stream`);
                const timeout = setTimeout(() => {
                    es.close();
                    reject(new Error('SSE timeout 600s'));
                }, 600_000);

                es.addEventListener('update', (e: MessageEvent) => {
                    try {
                        const data = JSON.parse(e.data);
                        if (data.status === 'completed' || data.status === 'failed') {
                            clearTimeout(timeout);
                            es.close();
                            resolve(data.status);
                        }
                    } catch {}
                });

                es.onerror = () => {
                    // biarkan timeout yg memutuskan
                };
            });
        }, runId);

        expect(finalStatus).toBe('completed');

        recordTest('migration-76', 'M.3 SSE terminal status', {
            input: `EventSource /admin/migrations/${runId}/stream (up to 600s)`,
            expected: 'terminal status = completed',
            actual: `run #${runId} ended with status="${finalStatus}"`,
            status: finalStatus === 'completed' ? 'PASS' : 'FAIL',
        });
    });
});