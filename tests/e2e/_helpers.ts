import { test, expect, type Page, type APIResponse, type Locator } from '@playwright/test';
import fs from 'fs';
import path from 'path';

const BASE = process.env.E2E_BASE_URL ?? 'http://localhost:56586';
const AUDIT_DIR = path.resolve(process.cwd(), 'docs/audit/2026-08-14');

export const ACCOUNTS = {
    superadmin: { username: 'superadmin', password: 'password' },
    dev: { username: 'dev', password: 'password' },
    province: { username: 'province_user', password: 'password' },
    regency: { username: 'regency_user', password: 'password' },
} as const;

export type Role = keyof typeof ACCOUNTS;

function ensureDomainFile(domain: string): string {
    if (!fs.existsSync(AUDIT_DIR)) fs.mkdirSync(AUDIT_DIR, { recursive: true });
    const file = path.join(AUDIT_DIR, `${domain}.md`);
    if (!fs.existsSync(file)) {
        const header = `# Audit Domain: ${domain}\n\n| # | Test | Input | Expected | Actual | Status |\n|---|---|---|---|---|---|\n`;
        fs.writeFileSync(file, header, 'utf8');
    }
    return file;
}

export function recordTest(domain: string, name: string, ctx: { input: string; expected: string; actual: string; status: 'PASS' | 'FAIL' | 'SKIP' | 'ERR' }): void {
    const file = ensureDomainFile(domain);
    const idx = fs.readFileSync(file, 'utf8').split('\n').filter((l) => l.startsWith('|') && !l.startsWith('| #') && !l.startsWith('|---')).length + 1;
    const row = `| ${idx} | ${name.replace(/\|/g, '\\|')} | ${ctx.input.replace(/\|/g, '\\|')} | ${ctx.expected.replace(/\|/g, '\\|')} | ${ctx.actual.replace(/\|/g, '\\|').slice(0, 300)} | ${ctx.status} |\n`;
    fs.appendFileSync(file, row, 'utf8');
}

export async function loginAs(page: Page, role: Role): Promise<void> {
    const acc = ACCOUNTS[role];
    await page.context().clearCookies();
    await page.request.post(`${BASE}/logout`).catch(() => {});
    await page.goto(`${BASE}/login`, { waitUntil: 'domcontentloaded' });
    const userIn = page.locator('input[autocomplete="username"]').first();
    await userIn.waitFor({ state: 'visible', timeout: 15000 });
    await userIn.fill(acc.username);
    const passIn = page.locator('input[autocomplete="current-password"]').first();
    await passIn.fill(acc.password);
    await page.getByRole('button', { name: /Masuk/i }).first().click();
    await page.waitForURL((url) => !url.pathname.includes('/login'), { timeout: 25000 }).catch(() => {});
    await page.waitForTimeout(500);
}

export async function logout(page: Page): Promise<void> {
    await page.request.post(`${BASE}/logout`).catch(() => {});
    await page.context().clearCookies();
}

export async function gotoNoErr(page: Page, url: string): Promise<APIResponse | null> {
    const resp = await page.goto(url, { waitUntil: 'domcontentloaded' });
    await noErr(page);
    return resp;
}

export async function noErr(page: Page): Promise<void> {
    const body = await page.locator('body').innerText().catch(() => '');
    const bad = /(500\s*Server\s*Error|SQLSTATE\[|Whoops|Exception\s+in|Tidak\s+dapat\s+menampilkan)/i;
    expect(body, `Error marker detected on ${page.url()}: ${body.slice(0, 200)}`).not.toMatch(bad);
}

export function uniqueNIK(): string {
    // 16-digit NIK with timestamp suffix uniqueness
    const ts = Date.now().toString().padStart(13, '0').slice(-13);
    return ('3204' + ts).padStart(16, '0').slice(-16);
}

export function uniqueCode(prefix: string): string {
    // alpha_dash friendly (no dashes)
    return `${prefix}_${Date.now().toString().slice(-8)}`;
}

export async function clickSmartSelect(page: Page, labelText: string, optionText: string): Promise<boolean> {
    // SmartSelect renders label as a <label> with text; trigger is a sibling button.
    const label = page.locator(`label:has-text("${labelText}")`).first();
    if (!(await label.isVisible({ timeout: 3000 }).catch(() => false))) return false;
    const trigger = label.locator('xpath=following::button[1]');
    if (!(await trigger.isVisible({ timeout: 3000 }).catch(() => false))) return false;
    await trigger.click();
    await page.waitForTimeout(250);
    const opt = page.locator(`[role="option"]:has-text("${optionText}")`).first();
    if (!(await opt.isVisible({ timeout: 3000 }).catch(() => false))) return false;
    await opt.click();
    await page.waitForTimeout(200);
    return true;
}

export async function pickFirstSmartOption(page: Page, labelText: string): Promise<boolean> {
    const label = page.locator(`label:has-text("${labelText}")`).first();
    if (!(await label.isVisible({ timeout: 3000 }).catch(() => false))) return false;
    const trigger = label.locator('xpath=following::button[1]');
    await trigger.click();
    await page.waitForTimeout(250);
    const opt = page.locator(`[role="option"]`).first();
    if (!(await opt.isVisible({ timeout: 2000 }).catch(() => false))) return false;
    await opt.click();
    await page.waitForTimeout(200);
    return true;
}

export async function humanType(page: Page, locator: Locator | string, text: string): Promise<void> {
    const el = typeof locator === 'string' ? page.locator(locator).first() : locator.first();
    await el.waitFor({ state: 'visible', timeout: 15000 });
    await el.click();
    await el.fill('');
    await el.pressSequentially(text, { delay: 20 });
}

export async function submitAndExpectNo5xx(page: Page, opts?: { expectedStatus?: number | number[]; expectedPath?: RegExp }): Promise<APIResponse | null> {
    const submitBtn = page.getByRole('button', { name: /(Simpan|Submit|Jalankan|Kirim|Save)/i }).first();
    await submitBtn.waitFor({ state: 'visible', timeout: 10000 });
    const navWait = page.waitForResponse((r) => r.request().method() === 'POST' && r.status() < 500, { timeout: 20000 }).catch(() => null);
    await submitBtn.click();
    const resp = await navWait;
    await page.waitForTimeout(800);
    await noErr(page);
    if (opts?.expectedPath) {
        await page.waitForURL(opts.expectedPath, { timeout: 10000 }).catch(() => {});
    }
    return resp;
}

export async function fillByLabel(page: Page, label: string, value: string): Promise<boolean> {
    const lbl = page.locator(`label:has-text("${label}")`).first();
    if (!(await lbl.isVisible({ timeout: 3000 }).catch(() => false))) return false;
    const inputId = await lbl.getAttribute('for').catch(() => null);
    let input: Locator | null = null;
    if (inputId) {
        const escaped = inputId.replace(/([!"#$%&'()*+,./:;<=>?@[\\\]^`{|}~])/g, '\\$1');
        input = page.locator(`#${escaped}`).first();
    }
    if (!input || !(await input.isVisible({ timeout: 2000 }).catch(() => false))) {
        input = lbl.locator('xpath=following::input[1]');
    }
    await input.fill('');
    await input.pressSequentially(value, { delay: 15 });
    return true;
}

export { BASE, AUDIT_DIR };