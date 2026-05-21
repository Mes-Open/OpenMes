import { test, expect } from '@playwright/test';

const ADMIN = process.env.ADMIN_USERNAME || 'admin';
const PASS = process.env.ADMIN_PASSWORD || 'Admin1234!';

test('updater banner: shows update available when remote is newer', async ({ page, context }) => {
  await context.clearCookies();
  await page.goto('/login');
  await page.fill('input[name="username"]', ADMIN);
  await page.fill('input[name="password"]', PASS);
  await Promise.all([
    page.waitForURL((url) => !url.pathname.startsWith('/login')),
    page.click('button[type="submit"]'),
  ]);
  await page.evaluate(() => sessionStorage.clear());
  await page.setViewportSize({ width: 1400, height: 900 });
  await page.goto('/admin/dashboard');
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(2000);

  await expect(page.locator('text=/Update available/i')).toBeVisible({ timeout: 5000 });
  await expect(page.locator('text=/OpenMES v999.0.0/')).toBeVisible();
  await expect(page.getByRole('button', { name: /Update now/i })).toBeVisible();
  await page.screenshot({ path: 'test-results/updater-banner-available.png', clip: { x: 0, y: 0, width: 1400, height: 80 } });
});
