import { test } from '@playwright/test';
import { login as signIn } from './support/app';

const ADMIN = process.env.ADMIN_USERNAME || 'admin';
const PASS = process.env.ADMIN_PASSWORD || 'Admin1234!';

async function login(page: any, ctx: any) {
  await ctx.clearCookies();
  await signIn(page, ADMIN, PASS);
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
