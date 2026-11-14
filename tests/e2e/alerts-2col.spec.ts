import { test } from '@playwright/test';
import { login as signIn } from './support/app';

const ADMIN_USERNAME = process.env.ADMIN_USERNAME || 'admin';
const ADMIN_PASSWORD = process.env.ADMIN_PASSWORD || 'Admin1234!';

test('alerts page — 2-column desktop + stacked mobile', async ({ page }) => {
  await signIn(page, ADMIN_USERNAME, ADMIN_PASSWORD);

  await page.setViewportSize({ width: 1400, height: 900 });
  await page.goto('/admin/alerts');
  await page.waitForLoadState('networkidle');
  await page.screenshot({ path: 'test-results/alerts-desktop-2col.png', fullPage: true });

  await page.setViewportSize({ width: 390, height: 900 });
  await page.goto('/admin/alerts');
  await page.waitForLoadState('networkidle');
  await page.screenshot({ path: 'test-results/alerts-mobile-stacked.png', fullPage: true });
});
