import { test } from '@playwright/test';

const ADMIN_USERNAME = process.env.ADMIN_USERNAME || 'admin';
const ADMIN_PASSWORD = process.env.ADMIN_PASSWORD || 'Admin1234!';

test('capture maintenance screens', async ({ page }) => {
  await page.goto('/login');
  await page.fill('input[name="username"]', ADMIN_USERNAME);
  await page.fill('input[name="password"]', ADMIN_PASSWORD);
  await Promise.all([
    page.waitForURL((url) => !url.pathname.startsWith('/login')),
    page.click('button[type="submit"]'),
  ]);

  await page.setViewportSize({ width: 1400, height: 1100 });

  await page.goto('/admin/maintenance-events');
  await page.waitForLoadState('networkidle');
  await page.screenshot({ path: 'test-results/maintenance-01-index.png', fullPage: true });

  await page.goto('/admin/maintenance-events/create');
  await page.waitForLoadState('networkidle');
  await page.screenshot({ path: 'test-results/maintenance-02-create.png', fullPage: true });

  // Try /1/edit directly
  await page.goto('/admin/maintenance-events/1/edit').catch(() => {});
  await page.waitForLoadState('networkidle').catch(() => {});
  await page.screenshot({ path: 'test-results/maintenance-03-edit.png', fullPage: true });
});
