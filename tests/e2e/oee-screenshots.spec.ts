import { test } from '@playwright/test';
import { login as signIn } from './support/app';

const ADMIN_USERNAME = process.env.ADMIN_USERNAME || 'admin';
const ADMIN_PASSWORD = process.env.ADMIN_PASSWORD || 'Admin1234!';

test('capture OEE gauge screenshots', async ({ page }) => {
  await signIn(page, ADMIN_USERNAME, ADMIN_PASSWORD);

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
