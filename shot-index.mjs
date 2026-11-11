import { chromium } from 'playwright';

const out = process.argv[2] ?? 'C:/Users/fandi/AppData/Local/Temp/opencode/savings-index.png';
const width = Number(process.argv[3] ?? 1440);
const height = Number(process.argv[4] ?? 1000);

const browser = await chromium.launch();
const context = await browser.newContext({ viewport: { width, height }, deviceScaleFactor: 1 });
await context.addInitScript(() => { try { localStorage.setItem('siupk-locale', 'id'); } catch (error) {} });
const page = await context.newPage();
page.on('pageerror', (err) => console.log('PAGE ERROR:', err.message));

await page.goto('http://demo.siupknext.test/login', { waitUntil: 'networkidle' });
await page.locator('input[autocomplete="username"]').fill('admin');
await page.locator('input[type="password"]').fill('password');
await page.locator('button[type="submit"]').click();
await page.waitForURL('**/dashboard', { timeout: 30000 });
await page.waitForLoadState('networkidle');

await page.goto('http://demo.siupknext.test/simpanan', { waitUntil: 'networkidle' });
await page.waitForTimeout(1000);
await page.screenshot({ path: out, fullPage: true });
console.log('TITLE:', await page.title());
console.log('URL:', page.url());
console.log('SCREENSHOT:', out);
await browser.close();
