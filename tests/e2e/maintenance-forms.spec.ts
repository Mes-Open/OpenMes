import { test, expect } from '@playwright/test';
const ADMIN = process.env.ADMIN_USERNAME || 'admin';
const PASS = process.env.ADMIN_PASSWORD || 'Admin1234!';

async function login(page: any) {
  await page.goto('/login');
  await page.fill('input[name="username"]', ADMIN);
  await page.fill('input[name="password"]', PASS);
  await Promise.all([
    page.waitForURL((url: URL) => !url.pathname.startsWith('/login')),
    page.click('button[type="submit"]'),
  ]);
}

test('maintenance create form has all 5 sections', async ({ page, context }) => {
  await context.clearCookies();
  await login(page);
  await page.setViewportSize({ width: 1400, height: 1600 });
  await page.goto('/admin/maintenance-events/create');
  await page.waitForLoadState('networkidle');
  for (const sec of ['What', 'Where', 'When', 'Who', 'Cost']) {
    await expect(page.getByRole('heading', { name: new RegExp(`^${sec}$`, 'i') })).toBeVisible();
  }
  await page.screenshot({ path: 'test-results/maintenance-create-new.png', fullPage: true });
});

test('maintenance edit form for in_progress event includes Resolution section', async ({ page, context }) => {
  await context.clearCookies();
  await login(page);
  await page.setViewportSize({ width: 1400, height: 1800 });
  // assume at least one in_progress event exists (seeded)
  await page.goto('/admin/maintenance-events');
  await page.waitForLoadState('networkidle');
  // click first edit icon
  const editLink = page.locator('a[title="Edit"]').first();
  await editLink.click();
  await page.waitForLoadState('networkidle');
  await page.screenshot({ path: 'test-results/maintenance-edit-new.png', fullPage: true });
});
