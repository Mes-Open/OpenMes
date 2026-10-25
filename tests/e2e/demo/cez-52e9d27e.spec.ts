import { test, expect } from '@playwright/test';

test('admin reads line cards and the summary bar on the plant overview and drills down', async ({ page }, testInfo) => {
  await page.setViewportSize({ width: 1440, height: 1000 });
  await page.goto('/login');
  await page.getByLabel(/username/i).fill(process.env.ADMIN_USERNAME || 'admin');
  await page.getByLabel(/password/i).fill(process.env.ADMIN_PASSWORD || 'Admin1234!');
  await page.getByRole('button', { name: /sign in|log in|login/i }).click();

  await expect(page.getByRole('heading', { name: 'Welcome to OpenMES' })).toBeVisible({ timeout: 60000 });
  await page.getByRole('button', { name: 'Install this example company' }).click();
  await expect(page.getByRole('heading', { name: 'Welcome to OpenMES' })).toBeHidden({ timeout: 240000 });
  await expect(page.getByText('Settings', { exact: true }).first()).toBeVisible({ timeout: 60000 });

  // Dashboard → Lines section
  await page.getByRole('link', { name: 'Dashboard' }).first().click();
  const lines = page.getByRole('region', { name: 'Lines' });
  await expect(lines).toBeVisible({ timeout: 30000 });
  await expect(page.getByRole('link', { name: 'Plant board →' })).toBeVisible();
  const cards = page.getByTestId('line-card');
  await expect(cards.first()).toBeVisible({ timeout: 30000 });
  expect(await cards.count()).toBeGreaterThan(0);
  await expect(cards.first()).toContainText(/Running|Blocked|Idle|Down/);
  await expect(cards.first()).not.toContainText('NaN');
  await cards.first().scrollIntoViewIfNeeded();
  await page.waitForTimeout(800);
  await page.screenshot({ path: testInfo.outputPath('01-lines-section.png'), fullPage: true });

  // Summary bar
  const summary = page.getByRole('region', { name: 'Plant summary' });
  await summary.scrollIntoViewIfNeeded();
  await expect(summary).toContainText('Work orders today');
  await expect(summary).toContainText(/\d+ running · \d+ pending/);
  await expect(summary).toContainText('Next order');
  await expect(summary).not.toContainText('NaN');
  await page.waitForTimeout(800);
  await page.screenshot({ path: testInfo.outputPath('02-summary-bar.png'), fullPage: true });

  // Click a line card → Shift Overview
  await cards.first().scrollIntoViewIfNeeded();
  await cards.first().click();
  await expect(page).toHaveURL(/\/admin\/shift-overview\?line=\d+/, { timeout: 30000 });
  await page.waitForTimeout(1000);
  await page.screenshot({ path: testInfo.outputPath('03-shift-overview.png'), fullPage: true });
  await page.goBack();
  await expect(page.getByTestId('line-card').first()).toBeVisible({ timeout: 30000 });

  // Next order → work order
  const next = page.getByTestId('summary-next_order');
  await next.scrollIntoViewIfNeeded();
  await expect(next).toBeVisible();
  await page.screenshot({ path: testInfo.outputPath('04-back-on-dashboard.png'), fullPage: true });
  await next.click();
  await expect(page).toHaveURL(/\/admin\/work-orders\/\d+/, { timeout: 30000 });
  await page.waitForTimeout(1000);
  await page.screenshot({ path: testInfo.outputPath('05-next-order.png'), fullPage: true });
  await page.goBack();

  // Materials → list
  const mat = page.getByTestId('summary-materials');
  await mat.scrollIntoViewIfNeeded();
  await expect(mat).toContainText(/\d+ below minimum/);
  await mat.click();
  await expect(page).toHaveURL(/\/admin\/materials/, { timeout: 30000 });
  await page.waitForTimeout(1000);
  await page.screenshot({ path: testInfo.outputPath('06-materials.png'), fullPage: true });
});
