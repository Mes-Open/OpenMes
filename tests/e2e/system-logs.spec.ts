import { test } from '@playwright/test';

async function login(page: any, ctx: any) {
  await ctx.clearCookies();
  await page.goto('/login');
  await page.fill('input[name="username"]', 'admin');
  await page.fill('input[name="password"]', 'Admin1234!');
  await Promise.all([
    page.waitForURL((url: URL) => !url.pathname.startsWith('/login')),
    page.click('button[type="submit"]'),
  ]);
}

test('system logs — 3 tabs', async ({ page, context }) => {
  await login(page, context);
  await page.setViewportSize({ width: 1400, height: 1100 });

  for (const tab of ['app', 'failed_jobs', 'deployments']) {
    await page.goto(`/admin/logs/system?tab=${tab}`);
    await page.waitForLoadState('networkidle');
    await page.screenshot({ path: `test-results/system-logs-${tab}.png`, fullPage: true });
  }
});
