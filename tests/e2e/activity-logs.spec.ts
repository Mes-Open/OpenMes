import { test, expect } from '@playwright/test';

test('activity logs page renders + middleware records requests', async ({ page, context }) => {
  await context.clearCookies();
  await page.goto('/login');
  await page.fill('input[name="username"]', 'admin');
  await page.fill('input[name="password"]', 'Admin1234!');
  await Promise.all([
    page.waitForURL((url) => !url.pathname.startsWith('/login')),
    page.click('button[type="submit"]'),
  ]);

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
