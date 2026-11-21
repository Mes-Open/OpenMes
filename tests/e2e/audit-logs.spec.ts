import { test } from '@playwright/test';
import { login as signIn } from './support/app';

const ADMIN = process.env.ADMIN_USERNAME || 'admin';
const PASS = process.env.ADMIN_PASSWORD || 'Admin1234!';
test('audit logs page', async ({ page, context }) => {
  await context.clearCookies();
  await signIn(page, ADMIN, PASS);
  await page.setViewportSize({ width: 1400, height: 1200 });
  await page.goto('/admin/audit-logs');
  await page.waitForLoadState('networkidle');
  await page.screenshot({ path: 'test-results/audit-logs.png', fullPage: true });
});
