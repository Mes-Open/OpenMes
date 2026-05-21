import { test } from '@playwright/test';
test('audit logs page', async ({ page, context }) => {
  await context.clearCookies();
  await page.goto('/login');
  await page.fill('input[name="username"]', 'admin');
  await page.fill('input[name="password"]', 'Admin1234!');
  await Promise.all([
    page.waitForURL((url) => !url.pathname.startsWith('/login')),
    page.click('button[type="submit"]'),
  ]);
  await page.setViewportSize({ width: 1400, height: 1200 });
  await page.goto('/admin/audit-logs');
  await page.waitForLoadState('networkidle');
  await page.screenshot({ path: 'test-results/audit-logs.png', fullPage: true });
});
