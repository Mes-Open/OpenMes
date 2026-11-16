import { test, expect } from '@playwright/test';
import { login as signIn } from './support/app';

const ADMIN_USERNAME = process.env.ADMIN_USERNAME || 'admin';
const ADMIN_PASSWORD = process.env.ADMIN_PASSWORD || 'Admin1234!';

test.describe('OEE PDF download', () => {
  test('button on /admin/oee triggers PDF download with correct mime', async ({ page }) => {
    await signIn(page, ADMIN_USERNAME, ADMIN_PASSWORD);

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
