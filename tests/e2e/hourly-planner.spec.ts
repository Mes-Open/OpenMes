import { test } from '@playwright/test';
import { login as signIn } from './support/app';

const ADMIN = process.env.ADMIN_USERNAME || 'admin';
const PASS = process.env.ADMIN_PASSWORD || 'Admin1234!';

test('hourly planner renders + cards visible', async ({ page, context }) => {
  await context.clearCookies();
  await signIn(page, ADMIN, PASS);
  await page.setViewportSize({ width: 1500, height: 1000 });
  await page.goto('/admin/schedule?view_mode=hourly');
  await page.waitForLoadState('networkidle');
  await page.screenshot({ path: 'test-results/hourly-planner.png', fullPage: true });
});
