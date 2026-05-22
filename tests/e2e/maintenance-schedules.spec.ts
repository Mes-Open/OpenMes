import { test, expect } from '@playwright/test';

const ADMIN = process.env.ADMIN_USERNAME || 'admin';
const PASS = process.env.ADMIN_PASSWORD || 'Admin1234!';

test('schedules index renders + create form has sections', async ({ page, context }) => {
  await context.clearCookies();
  await page.goto('/login');
  await page.fill('input[name="username"]', ADMIN);
  await page.fill('input[name="password"]', PASS);
  await Promise.all([
    page.waitForURL((url) => !url.pathname.startsWith('/login')),
    page.click('button[type="submit"]'),
  ]);
  await page.setViewportSize({ width: 1400, height: 1200 });

  await page.goto('/admin/maintenance-schedules');
  await page.waitForLoadState('networkidle');
  await page.screenshot({ path: 'test-results/maintenance-schedules-index.png', fullPage: true });

  await page.goto('/admin/maintenance-schedules/create');
  await page.waitForLoadState('networkidle');
  for (const sec of ['What', 'Where', 'When', 'Who']) {
    await expect(page.getByRole('heading', { name: new RegExp(`^${sec}$`, 'i') })).toBeVisible();
  }
  await page.screenshot({ path: 'test-results/maintenance-schedules-create.png', fullPage: true });
});
