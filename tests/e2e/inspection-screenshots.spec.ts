import { test } from '@playwright/test';
import { login as signIn } from './support/app';

const ADMIN_USERNAME = process.env.ADMIN_USERNAME || 'admin';
const ADMIN_PASSWORD = process.env.ADMIN_PASSWORD || 'Admin1234!';

test('capture inspection screenshots', async ({ page }) => {
  await signIn(page, ADMIN_USERNAME, ADMIN_PASSWORD);

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
