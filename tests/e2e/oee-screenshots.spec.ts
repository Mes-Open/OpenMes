import { test } from '@playwright/test';

const ADMIN_USERNAME = process.env.ADMIN_USERNAME || 'admin';
const ADMIN_PASSWORD = process.env.ADMIN_PASSWORD || 'Admin1234!';

test('capture OEE gauge screenshots', async ({ page }) => {
  await page.goto('/login');
  await page.fill('input[name="username"]', ADMIN_USERNAME);
  await page.fill('input[name="password"]', ADMIN_PASSWORD);
  await Promise.all([
    page.waitForURL((url) => !url.pathname.startsWith('/login')),
    page.click('button[type="submit"]'),
  ]);

  await page.setViewportSize({ width: 1400, height: 900 });

  await page.goto('/admin/dashboard');
  await page.waitForLoadState('networkidle');
  await page.screenshot({ path: 'test-results/dashboard.png', fullPage: true });

  await page.goto('/admin/oee');
  await page.waitForLoadState('networkidle');
  await page.screenshot({ path: 'test-results/oee-index.png', fullPage: true });

  await page.goto('/admin/oee?granularity=week');
  await page.waitForLoadState('networkidle');
  await page.screenshot({ path: 'test-results/oee-weekly.png', fullPage: true });
});
