import { test, expect } from '@playwright/test';

const ADMIN_USERNAME = process.env.ADMIN_USERNAME || 'admin';
const ADMIN_PASSWORD = process.env.ADMIN_PASSWORD || 'Admin1234!';

test.describe('OEE PDF download', () => {
  test('button on /admin/oee triggers PDF download with correct mime', async ({ page }) => {
    await page.goto('/login');
    await page.fill('input[name="username"]', ADMIN_USERNAME);
    await page.fill('input[name="password"]', ADMIN_PASSWORD);
    await Promise.all([
      page.waitForURL((url) => !url.pathname.startsWith('/login')),
      page.click('button[type="submit"]'),
    ]);

    await page.goto('/admin/oee');

    const [download] = await Promise.all([
      page.waitForEvent('download'),
      page.getByRole('link', { name: /Download PDF/i }).first().click(),
    ]);

    const filename = download.suggestedFilename();
    expect(filename).toMatch(/^oee-report-.*\.pdf$/);

    const path = await download.path();
    expect(path).toBeTruthy();
    await download.saveAs(`test-results/${filename}`);

    // Sanity: file starts with %PDF
    const fs = await import('fs');
    const head = fs.readFileSync(`test-results/${filename}`).subarray(0, 5).toString();
    expect(head).toBe('%PDF-');
  });
});
