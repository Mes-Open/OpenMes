import { test } from '@playwright/test';

const ADMIN = process.env.ADMIN_USERNAME || 'admin';
const PASS = process.env.ADMIN_PASSWORD || 'Admin1234!';
test('audit logs page', async ({ page, context }) => {
  await context.clearCookies();
  await page.goto('/login');
  await page.fill('input[name="username"]', ADMIN);
  await page.fill('input[name="password"]', PASS);
  await Promise.all([
    page.waitForURL((url) => !url.pathname.startsWith('/login')),
    page.click('button[type="submit"]'),
  ]);
  await page.setViewportSize({ width: 1400, height: 1200 });
  await page.goto('/admin/audit-logs');
  await page.waitForLoadState('networkidle');
  await page.screenshot({ path: 'test-results/audit-logs.png', fullPage: true });
});
