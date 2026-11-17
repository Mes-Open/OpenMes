import { test } from '@playwright/test';
import { login as signIn } from './support/app';

const ADMIN_USERNAME = process.env.ADMIN_USERNAME || 'admin';
const ADMIN_PASSWORD = process.env.ADMIN_PASSWORD || 'Admin1234!';

test('capture maintenance screens', async ({ page }) => {
  await signIn(page, ADMIN_USERNAME, ADMIN_PASSWORD);

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
