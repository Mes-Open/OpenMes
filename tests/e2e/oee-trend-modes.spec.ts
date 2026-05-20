import { test } from '@playwright/test';

const ADMIN_USERNAME = process.env.ADMIN_USERNAME || 'admin';
const ADMIN_PASSWORD = process.env.ADMIN_PASSWORD || 'Admin1234!';

test('screenshot Combined and Per-line trend modes', async ({ page }) => {
  await page.goto('/login');
  await page.fill('input[name="username"]', ADMIN_USERNAME);
  await page.fill('input[name="password"]', ADMIN_PASSWORD);
  await Promise.all([
    page.waitForURL((url) => !url.pathname.startsWith('/login')),
    page.click('button[type="submit"]'),
  ]);

  await page.setViewportSize({ width: 1400, height: 900 });

  await page.goto('/admin/oee');
  await page.waitForLoadState('networkidle');
  await page.screenshot({ path: 'test-results/oee-combined.png', fullPage: true });

  await page.getByRole('button', { name: 'Per line' }).click();
  await page.waitForTimeout(300);
  await page.screenshot({ path: 'test-results/oee-per-line.png', fullPage: true });

  // Show "All" shift breakdown by visiting per-line detail
  await page.goto('/admin/oee/1');
  await page.waitForLoadState('networkidle');
  await page.screenshot({ path: 'test-results/oee-line-detail-shifts.png', fullPage: true });
});
