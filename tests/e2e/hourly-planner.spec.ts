import { test } from '@playwright/test';

const ADMIN = process.env.ADMIN_USERNAME || 'admin';
const PASS = process.env.ADMIN_PASSWORD || 'Admin1234!';

test('hourly planner renders + cards visible', async ({ page, context }) => {
  await context.clearCookies();
  await page.goto('/login');
  await page.fill('input[name="username"]', ADMIN);
  await page.fill('input[name="password"]', PASS);
  await Promise.all([
    page.waitForURL((url) => !url.pathname.startsWith('/login')),
    page.click('button[type="submit"]'),
  ]);
  await page.setViewportSize({ width: 1500, height: 1000 });
  await page.goto('/admin/schedule?view_mode=hourly');
  await page.waitForLoadState('networkidle');
  await page.screenshot({ path: 'test-results/hourly-planner.png', fullPage: true });
});
