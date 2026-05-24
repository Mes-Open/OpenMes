import { test } from '@playwright/test';

const ADMIN = process.env.ADMIN_USERNAME || 'admin';
const PASS = process.env.ADMIN_PASSWORD || 'Admin1234!';

test('smoke: all new pages render after rebuild', async ({ page, context }) => {
  await context.clearCookies();
  await page.goto('/login');
  await page.fill('input[name="username"]', ADMIN);
  await page.fill('input[name="password"]', PASS);
  await Promise.all([
    page.waitForURL((url) => !url.pathname.startsWith('/login')),
    page.click('button[type="submit"]'),
  ]);
  await page.setViewportSize({ width: 1500, height: 1100 });

  // Generate traffic for activity logs
  for (const p of ['/admin/dashboard', '/admin/work-orders', '/admin/maintenance-events', '/admin/issues']) {
    await page.goto(p);
    await page.waitForLoadState('networkidle');
  }

  // Screenshot all 4 new pages
  await page.goto('/admin/logs/activity');
  await page.waitForLoadState('networkidle');
  await page.screenshot({ path: 'test-results/final-activity-logs.png', fullPage: true });

  await page.goto('/admin/logs/system');
  await page.waitForLoadState('networkidle');
  await page.screenshot({ path: 'test-results/final-system-logs.png', fullPage: true });

  await page.goto('/admin/logs/system?tab=deployments');
  await page.waitForLoadState('networkidle');
  await page.screenshot({ path: 'test-results/final-deployments.png', fullPage: true });

  await page.goto('/admin/maintenance-events');
  await page.waitForLoadState('networkidle');
  await page.screenshot({ path: 'test-results/final-maintenance.png', fullPage: true });

  await page.goto('/admin/maintenance-schedules');
  await page.waitForLoadState('networkidle');
  await page.screenshot({ path: 'test-results/final-schedules.png', fullPage: true });
});
