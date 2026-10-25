import { test, expect } from '@playwright/test';

test('shift KPI tiles: backlog count matches the planner, scrap target change shows on the dashboard', async ({ page }, testInfo) => {
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
  const tiles = page.getByRole('region', { name: 'Shift at a glance' });
  await expect(tiles).toBeVisible();
  const backlog = page.getByTestId('kpi-backlog');
  await expect(backlog).toContainText('Orders not scheduled');
  await expect(page.getByTestId('kpi-lines')).toContainText('Lines producing');
  await expect(page.getByTestId('kpi-scrap')).toContainText('Scrap rate');
  await expect(page.getByTestId('kpi-fpy')).toContainText('First-pass yield');
  await page.waitForTimeout(1500);
  await page.screenshot({ path: testInfo.outputPath('01-kpi-tiles.png'), fullPage: true });

  const n = (await backlog.locator('span').nth(1).innerText()).trim();
  expect(n).toMatch(/^\d+$/);

  // Click the backlog tile → Production Planner
  await backlog.click();
  await expect(page).toHaveURL(/\/admin\/schedule/, { timeout: 30000 });
  await expect(page.getByText(`${n} unscheduled in backlog`)).toBeVisible({ timeout: 30000 });
  await expect(page.getByText(new RegExp(`Backlog\\s*·?\\s*${n}\\b`)).first()).toBeVisible();
  await page.waitForTimeout(1000);
  await page.screenshot({ path: testInfo.outputPath('02-planner-backlog.png'), fullPage: true });

  // Settings → System → Production → change the scrap target
  await page.getByText('Settings', { exact: true }).first().click();
  await page.getByRole('link', { name: /System Settings/ }).first().click();
  await expect(page).toHaveURL(/\/settings\/system/, { timeout: 30000 });
  await page.getByRole('tab', { name: 'Production' }).click();
  const scrap = page.getByLabel('Max scrap rate (%)');
  await scrap.scrollIntoViewIfNeeded();
  await expect(scrap).toHaveValue('2.0');
  await page.screenshot({ path: testInfo.outputPath('03-targets-before.png'), fullPage: true });
  await scrap.fill('7.5');
  await page.getByRole('button', { name: 'Save targets' }).click();
  await expect(page.getByText('Production targets updated.')).toBeVisible();
  await page.waitForTimeout(800);
  await page.screenshot({ path: testInfo.outputPath('04-targets-saved.png'), fullPage: true });

  // Back on the dashboard
  await page.getByRole('link', { name: 'Dashboard' }).first().click();
  await expect(page.getByRole('heading', { name: 'Plant overview' })).toBeVisible();
  const scrapTile = page.getByTestId('kpi-scrap');
  await expect(scrapTile).toContainText(/Target ≤ 7\.5%|No completed output this shift/);
  await page.waitForTimeout(1500);
  await page.getByRole('heading', { name: 'Plant overview' }).scrollIntoViewIfNeeded();
  await page.waitForTimeout(800);
  await page.screenshot({ path: testInfo.outputPath('05-dashboard-new-target.png'), fullPage: true });

  // Dark mode, narrow screens
  await page.getByRole('button', { name: 'Dark Mode' }).click();
  await expect(page.getByRole('button', { name: 'Light Mode' })).toBeVisible();
  await page.waitForTimeout(800);
  await page.getByRole('heading', { name: 'Plant overview' }).scrollIntoViewIfNeeded();
  await page.screenshot({ path: testInfo.outputPath('06-dark.png'), fullPage: true });
  await page.setViewportSize({ width: 1024, height: 800 });
  await expect(page.getByTestId('kpi-backlog')).toBeVisible();
  await page.getByRole('heading', { name: 'Plant overview' }).scrollIntoViewIfNeeded();
  await page.screenshot({ path: testInfo.outputPath('07-dark-1024.png'), fullPage: true });
  await page.setViewportSize({ width: 390, height: 800 });
  await expect(page.getByTestId('kpi-backlog')).toBeVisible();
  await page.waitForTimeout(800);
  await page.getByRole('heading', { name: 'Plant overview' }).scrollIntoViewIfNeeded();
  await page.screenshot({ path: testInfo.outputPath('08-dark-390.png'), fullPage: true });
});
