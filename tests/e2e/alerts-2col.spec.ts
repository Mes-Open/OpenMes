import { test } from '@playwright/test';

const ADMIN_USERNAME = process.env.ADMIN_USERNAME || 'admin';
const ADMIN_PASSWORD = process.env.ADMIN_PASSWORD || 'Admin1234!';

test('alerts page — 2-column desktop + stacked mobile', async ({ page }) => {
  await page.goto('/login');
  await page.fill('input[name="username"]', ADMIN_USERNAME);
  await page.fill('input[name="password"]', ADMIN_PASSWORD);
  await Promise.all([
    page.waitForURL((url) => !url.pathname.startsWith('/login')),
    page.click('button[type="submit"]'),
  ]);

  await page.setViewportSize({ width: 1400, height: 900 });
  await page.goto('/admin/alerts');
  await page.waitForLoadState('networkidle');
  await page.screenshot({ path: 'test-results/alerts-desktop-2col.png', fullPage: true });

  await page.setViewportSize({ width: 390, height: 900 });
  await page.goto('/admin/alerts');
  await page.waitForLoadState('networkidle');
  await page.screenshot({ path: 'test-results/alerts-mobile-stacked.png', fullPage: true });
});
