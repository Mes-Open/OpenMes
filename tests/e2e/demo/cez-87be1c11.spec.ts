import { test, expect } from '@playwright/test';

test('dashboard line activity timeline: segments, tooltip, shift overview link, plant board, dark mode, phone', async ({ page }, testInfo) => {
  test.setTimeout(290_000);
  await page.setViewportSize({ width: 1440, height: 900 });

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

  const card = page.getByTestId('line-activity');
  await expect(card.getByText('Line activity · this shift')).toBeVisible();
  await card.scrollIntoViewIfNeeded();
  for (const l of ['Running', 'Idle', 'Blocked', 'Down', 'Now']) {
    await expect(card.getByText(l, { exact: true }).first()).toBeVisible();
  }
  await expect(card.getByText('Uptime', { exact: true })).toBeVisible();
  await expect(card.getByTestId('line-activity-now').first()).toBeVisible();
  await page.waitForTimeout(800);
  await page.screenshot({ path: testInfo.outputPath('01-line-activity.png'), fullPage: true });

  // Tooltip on a segment
  const segs = card.getByTestId('line-activity-segment');
  const count = await segs.count();
  if (count > 0) {
    const seg = segs.first();
    await seg.scrollIntoViewIfNeeded();
    await seg.hover();
    await expect(page.getByRole('tooltip').or(page.locator('[role=tooltip]')).first()).toBeVisible({ timeout: 5000 });
    await page.waitForTimeout(800);
    await page.screenshot({ path: testInfo.outputPath('02-tooltip.png'), fullPage: true });
    const down = card.locator('[data-testid="line-activity-segment"][data-state="down"]').first();
    if (await down.count()) {
      await down.hover();
      await page.waitForTimeout(800);
      await page.screenshot({ path: testInfo.outputPath('03-tooltip-down.png'), fullPage: true });
    }
  }

  // Line name → Shift Overview
  const firstRow = card.locator('[data-testid^="line-activity-row-"]').first();
  const lineLink = firstRow.getByRole('link').first();
  await expect(lineLink).toBeVisible();
  const lineName = (await lineLink.textContent())!.trim();
  await lineLink.click();
  await expect(page).toHaveURL(/\/admin\/shift-overview\?line=/, { timeout: 30000 });
  await expect(page.getByText(lineName).first()).toBeVisible();
  await page.waitForTimeout(800);
  await page.screenshot({ path: testInfo.outputPath('04-shift-overview.png'), fullPage: true });

  await page.goBack();
  await expect(page.getByRole('heading', { name: 'Plant overview' })).toBeVisible();
  await expect(page.getByTestId('line-activity')).toBeVisible();

  // Compare with the Plant Board
  await page.getByRole('link', { name: 'Plant Board' }).first().click();
  await expect(page).toHaveURL(/\/admin\/shift-board/, { timeout: 30000 });
  await expect(page.getByText(lineName).first()).toBeVisible();
  await page.waitForTimeout(1200);
  await page.screenshot({ path: testInfo.outputPath('05-plant-board.png'), fullPage: true });

  await page.getByRole('link', { name: 'Dashboard' }).first().click();
  await expect(page.getByRole('heading', { name: 'Plant overview' })).toBeVisible();

  // Dark mode
  const theme = page.getByRole('button', { name: /dark|theme|mode/i }).first();
  if (await theme.isVisible()) {
    await theme.click();
    await page.waitForTimeout(800);
  }
  await page.getByTestId('line-activity').scrollIntoViewIfNeeded();
  await expect(page.getByTestId('line-activity')).toBeVisible();
  await page.screenshot({ path: testInfo.outputPath('06-dark.png'), fullPage: true });

  // Phone
  await page.setViewportSize({ width: 390, height: 844 });
  await page.waitForTimeout(800);
  await expect(page.getByTestId('line-activity')).toBeVisible();
  await page.getByTestId('line-activity').scrollIntoViewIfNeeded();
  await page.waitForTimeout(800);
  await page.screenshot({ path: testInfo.outputPath('07-phone.png'), fullPage: true });
});
