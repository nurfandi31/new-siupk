import { test, expect } from '@playwright/test';

test('Test Chat Assistant Live', async ({ page }) => {
    // 1. Login
    await page.goto('http://localhost:8080/login');
    await page.fill('input[autocomplete="username"]', 'superadmin');
    await page.fill('input[autocomplete="current-password"]', 'password');
    await page.click('button:has-text("Masuk")');
    await page.waitForURL(url => !url.pathname.includes('/login'), { timeout: 15000 });

    // 2. Navigate to orchestrator or dashboard where session is active
    await page.goto('http://localhost:8080/admin/ai-assistant');
    await page.waitForTimeout(1000);

    // 3. Post chat message via in-browser fetch with SSE streaming
    const chatResult = await page.evaluate(async () => {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const res = await fetch('/admin/ai-assistant/chat', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'text/event-stream, application/json',
            },
            body: JSON.stringify({
                message: 'Halo Ariel! Siapa namamu dan apa tugas utamamu di siupk?',
                persona_slug: 'default'
            })
        });

        const status = res.status;
        const reader = res.body.getReader();
        const decoder = new TextDecoder();
        let fullText = '';

        while (true) {
            const { done, value } = await reader.read();
            if (done) break;
            fullText += decoder.decode(value, { stream: true });
        }

        return { status, fullText };
    });

    console.log('\n=============================================');
    console.log('HTTP Status:', chatResult.status);
    console.log('--- RESPON STREAMING DARI ASSISTANT (SSE) ---');
    console.log(chatResult.fullText);
    console.log('=============================================\n');

    expect(chatResult.status).toBe(200);
});