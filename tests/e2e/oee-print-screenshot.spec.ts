import { test } from '@playwright/test';

const ADMIN_USERNAME = process.env.ADMIN_USERNAME || 'admin';
const ADMIN_PASSWORD = process.env.ADMIN_PASSWORD || 'Admin1234!';

test('capture print report screenshots', async ({ page }) => {
  await page.goto('/login');
  await page.fill('input[name="username"]', ADMIN_USERNAME);
  await page.fill('input[name="password"]', ADMIN_PASSWORD);
  await Promise.all([
    page.waitForURL((url) => !url.pathname.startsWith('/login')),
    page.click('button[type="submit"]'),
  ]);

  await page.setViewportSize({ width: 1200, height: 1600 });

  // Full report (both lines)
  await page.goto('/admin/oee/print');
  await page.waitForLoadState('networkidle');
  await page.screenshot({ path: 'test-results/print-all-lines.png', fullPage: true });

  // Per-line report
  await page.goto('/admin/oee/print?line_id=1');
  await page.waitForLoadState('networkidle');
  await page.screenshot({ path: 'test-results/print-line-1.png', fullPage: true });

  // Emulate print media to verify @media print rules
  await page.emulateMedia({ media: 'print' });
  await page.screenshot({ path: 'test-results/print-line-1-media-print.png', fullPage: true });
});
