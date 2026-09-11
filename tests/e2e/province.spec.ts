import { test, expect } from '@playwright/test';

const BASE = process.env.E2E_BASE_URL ?? 'http://localhost:56586';

test.describe('Province Level Suite Tests (/province)', () => {
    test('Unauthenticated user is redirected from /province/dashboard to login', async ({ page }) => {
        await page.goto(`${BASE}/province/dashboard`, { waitUntil: 'domcontentloaded' });
        await page.waitForTimeout(500);
        expect(page.url()).toContain('/login');
    });

    test('Unauthenticated user is redirected from /province/reports/pack to login', async ({ page }) => {
        await page.goto(`${BASE}/province/reports/pack`, { waitUntil: 'domcontentloaded' });
        await page.waitForTimeout(500);
        expect(page.url()).toContain('/login');
    });
});
