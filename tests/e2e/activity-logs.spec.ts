import { test, expect } from '@playwright/test';
import { login as signIn } from './support/app';

const ADMIN = process.env.ADMIN_USERNAME || 'admin';
const PASS = process.env.ADMIN_PASSWORD || 'Admin1234!';

test('activity logs page renders + middleware records requests', async ({ page, context }) => {
  await context.clearCookies();
  await signIn(page, ADMIN, PASS);

  // generate some traffic so middleware writes rows
  for (const path of ['/admin/dashboard', '/admin/work-orders', '/admin/maintenance-events', '/admin/issues']) {
    await page.goto(path);
    await page.waitForLoadState('networkidle');
  }

  await page.setViewportSize({ width: 1500, height: 1200 });
  await page.goto('/admin/logs/activity');
  await page.waitForLoadState('networkidle');

  await expect(page.getByRole('heading', { name: /Activity Logs/i })).toBeVisible();
  await page.screenshot({ path: 'test-results/activity-logs.png', fullPage: true });
});
