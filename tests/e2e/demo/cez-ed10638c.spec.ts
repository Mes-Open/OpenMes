import { test, expect } from '@playwright/test';

test('plant overview: time left in h/min and silent collector lines turn to "No data"', async ({ page }, testInfo) => {
  test.setTimeout(1_200_000);

  await page.goto('/login');
  await page.getByLabel(/username/i).fill(process.env.ADMIN_USERNAME!);
  await page.getByLabel(/password/i).fill(process.env.ADMIN_PASSWORD!);
  await page.getByRole('button', { name: /sign in|log in/i }).click();

  await expect(page.getByText('Welcome to OpenMES').first()).toBeVisible();
  await page.getByRole('button', { name: 'Install this example company' }).click();
  await expect(page.getByRole('link', { name: 'Settings' }).first()).toBeVisible({ timeout: 120_000 });

  const dash = page.getByRole('link', { name: 'Dashboard' }).first();
  if (await dash.isVisible()) await dash.click();
  await expect(page.getByRole('heading', { name: 'Plant overview' })).toBeVisible();
  await expect(page.getByTestId('overview-live')).toHaveAttribute('data-live', 'ok');

  // Header: time left is "N h left" or "H h MM min left" / "N min left", never rounded down
  const shift = page.getByTestId('overview-shift');
  await expect(shift).toBeVisible();
  await expect(shift).toContainText(/(\d+ h( \d+ min)?|\d+ min) left|No shift running/);
  await page.waitForTimeout(800);
  await page.screenshot({ path: testInfo.outputPath('01-header-time-left.png'), fullPage: true });

  // Lines card with legend incl. "No data"
  const legend = page.getByTestId('line-state-legend');
  await legend.scrollIntoViewIfNeeded();
  await expect(legend).toContainText('No data');
  await expect(page.getByTestId('overview-line').first()).toBeVisible();
  await page.waitForTimeout(800);
  await page.screenshot({ path: testInfo.outputPath('02-lines-fresh.png'), fullPage: true });

  // Nobody feeds the demo collectors: after >10 min without a signal a machine line
  // must switch to "No data". The page refreshes itself every 60 s.
  const silent = page.locator('[data-testid="overview-line"][data-state="unknown"]').first();
  await expect(silent).toBeVisible({ timeout: 14 * 60_000 });
  await expect(silent).toContainText('No data');
  await silent.scrollIntoViewIfNeeded();
  await page.waitForTimeout(1500);
  await page.screenshot({ path: testInfo.outputPath('03-line-no-data.png'), fullPage: true });

  // Dark mode
  await page.getByRole('button', { name: 'Dark Mode' }).click();
  await expect(page.getByRole('button', { name: 'Light Mode' })).toBeVisible();
  await expect(silent).toContainText('No data');
  await page.waitForTimeout(800);
  await page.screenshot({ path: testInfo.outputPath('04-dark-no-data.png'), fullPage: true });

  // Narrow viewports
  await page.setViewportSize({ width: 1024, height: 800 });
  await expect(silent).toBeVisible();
  await page.screenshot({ path: testInfo.outputPath('05-dark-1024.png'), fullPage: true });
  await page.setViewportSize({ width: 390, height: 800 });
  await expect(silent).toBeVisible();
  await page.waitForTimeout(800);
  await page.screenshot({ path: testInfo.outputPath('06-dark-390.png'), fullPage: true });
});
