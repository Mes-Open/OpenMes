import { test } from '@playwright/test';

const ADMIN_USERNAME = process.env.ADMIN_USERNAME || 'admin';
const ADMIN_PASSWORD = process.env.ADMIN_PASSWORD || 'Admin1234!';

test('capture inspection screenshots', async ({ page }) => {
  await page.goto('/login');
  await page.fill('input[name="username"]', ADMIN_USERNAME);
  await page.fill('input[name="password"]', ADMIN_PASSWORD);
  await Promise.all([
    page.waitForURL((url) => !url.pathname.startsWith('/login')),
    page.click('button[type="submit"]'),
  ]);

  await page.setViewportSize({ width: 1400, height: 1400 });

  await page.goto('/admin/dashboard');
  await page.waitForLoadState('networkidle');
  await page.screenshot({ path: 'test-results/dashboard-with-inbound-widget.png', fullPage: true });

  await page.goto('/inspections');
  await page.waitForLoadState('networkidle');
  await page.screenshot({ path: 'test-results/inspections-list.png', fullPage: true });

  await page.goto('/admin/inspection-plans');
  await page.waitForLoadState('networkidle');
  await page.screenshot({ path: 'test-results/inspection-plans.png', fullPage: true });
});
