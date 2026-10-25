import { test, expect } from '@playwright/test';

test('plant overview dashboard header, new work order shortcut and Last 30 days widgets', async ({ page }, testInfo) => {
  test.setTimeout(290_000);

  await page.goto('/login');
  await page.getByLabel(/username/i).fill(process.env.ADMIN_USERNAME!);
  await page.getByLabel(/password/i).fill(process.env.ADMIN_PASSWORD!);
  await page.getByRole('button', { name: /sign in|log in/i }).click();

  await expect(page.getByText('Welcome to OpenMES').first()).toBeVisible();
  await page.getByRole('button', { name: 'Install this example company' }).click();
  await expect(page.getByRole('link', { name: 'Settings' }).first()).toBeVisible({ timeout: 120_000 });

  // Dashboard via the sidebar
  const dash = page.getByRole('link', { name: 'Dashboard' }).first();
  if (await dash.isVisible()) await dash.click();
  await expect(page.getByRole('heading', { name: 'Plant overview' })).toBeVisible();
  await expect(page.getByTestId('overview-live')).toHaveAttribute('data-live', 'ok');
  await expect(page.getByTestId('overview-shift')).toBeVisible();
  await page.waitForTimeout(800);
  await page.screenshot({ path: testInfo.outputPath('01-header.png'), fullPage: true });

  // New work order shortcut
  await page.getByRole('link', { name: /New work order/ }).click();
  await expect(page.getByRole('heading', { name: /New Work Order/i }).first()).toBeVisible();
  await page.waitForTimeout(800);
  await page.screenshot({ path: testInfo.outputPath('02-new-work-order.png'), fullPage: true });

  // Back to the dashboard
  await page.getByRole('link', { name: 'Dashboard' }).first().click();
  await expect(page.getByRole('heading', { name: 'Plant overview' })).toBeVisible();

  // Last 30 days section
  const last30 = page.getByRole('heading', { name: 'Last 30 days' });
  await expect(last30).toBeVisible();
  await page.waitForTimeout(1500);
  await last30.scrollIntoViewIfNeeded();
  await page.waitForTimeout(800);
  await page.screenshot({ path: testInfo.outputPath('03-last-30-days.png'), fullPage: true });

  // Dark theme
  await page.getByRole('button', { name: 'Dark Mode' }).click();
  await expect(page.getByRole('button', { name: 'Light Mode' })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Plant overview' })).toBeVisible();
  await page.waitForTimeout(800);
  await page.screenshot({ path: testInfo.outputPath('04-dark-header.png'), fullPage: true });

  // Narrow viewports
  await page.setViewportSize({ width: 1024, height: 800 });
  await expect(page.getByRole('link', { name: /New work order/ })).toBeVisible();
  await page.screenshot({ path: testInfo.outputPath('05-dark-1024.png'), fullPage: true });
  await page.setViewportSize({ width: 390, height: 800 });
  await expect(page.getByRole('link', { name: /New work order/ })).toBeVisible();
  await page.waitForTimeout(800);
  await page.screenshot({ path: testInfo.outputPath('06-dark-390.png'), fullPage: true });
});
