import { test, expect } from '@playwright/test';

test('schedule → dashboard planned vs actual and output per hour', async ({ page }, testInfo) => {
  test.setTimeout(290_000);
  page.setDefaultTimeout(45_000);
  await page.setViewportSize({ width: 1440, height: 900 });
  await page.goto('/login');
  await page.getByLabel(/username/i).fill(process.env.ADMIN_USERNAME!);
  await page.getByLabel(/password/i).fill(process.env.ADMIN_PASSWORD!);
  await page.getByRole('button', { name: /sign in|log in/i }).click();

  await expect(page.getByText('Welcome to OpenMES').first()).toBeVisible();
  await page.getByRole('button', { name: 'Install this example company' }).click();
  await expect(page.getByRole('link', { name: 'Settings' }).first()).toBeVisible({ timeout: 120_000 });

  // Admin: a new order on a line that has a bench operator
  await page.getByRole('link', { name: 'Dashboard' }).first().click();
  await page.getByRole('link', { name: /New work order/ }).click();
  await expect(page.getByRole('heading', { name: /New Work Order/i }).first()).toBeVisible();
  await page.locator('input[name="order_no"]').fill('WO-QA-0001');
  await page.locator('form button[aria-haspopup="listbox"]').nth(1).click();
  await page.getByText('Cooling & Packing', { exact: true }).last().click();
  await page.locator('form button[aria-haspopup="listbox"]').nth(2).click();
  await page.getByText('Baked Cheesecake', { exact: true }).last().click();
  await page.locator('input[name="planned_qty"]').fill('200');
  await page.waitForTimeout(500);
  await page.screenshot({ path: testInfo.outputPath('00-new-wo.png'), fullPage: true });
  await page.locator('form').getByRole('button', { name: /Create|Save/ }).first().click();
  await page.waitForURL(/\/admin\/work-orders$/);
  await page.getByRole('link', { name: 'Scheduler' }).first().click();
  await expect(page).toHaveURL(/\/admin\/schedule/);
  await page.waitForTimeout(2000);
  await page.screenshot({ path: testInfo.outputPath('01-schedule.png'), fullPage: true });

  await page.getByRole('link', { name: 'Automatic scheduling' }).click();
  await page.getByText('Material availability').click();
  await page.getByText('Operator availability').click();
  await page.getByRole('button', { name: 'Simulate' }).click();
  await page.waitForTimeout(4000);
  await page.screenshot({ path: testInfo.outputPath('01b-sim.png'), fullPage: true });
  await page.getByRole('button', { name: 'Apply', exact: true }).first().click();
  await page.getByRole('button', { name: 'Apply', exact: true }).last().click();
  await page.waitForTimeout(3000);
  await page.screenshot({ path: testInfo.outputPath('01c-applied.png'), fullPage: true });
  await page.getByRole('link', { name: 'Dashboard' }).first().click();
  await expect(page.getByRole('heading', { name: 'Plant overview' })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Production · planned vs actual' })).toBeVisible();
  await page.waitForTimeout(2000);
  await page.screenshot({ path: testInfo.outputPath('02-dashboard.png'), fullPage: true });

  await expect(page.getByRole('heading', { name: 'Output per hour' })).toBeVisible();
  await expect(page.getByTestId('pva-figure')).toContainText('/');
  await page.getByRole('heading', { name: 'Output per hour' }).scrollIntoViewIfNeeded();
  await page.waitForTimeout(800);
  await page.screenshot({ path: testInfo.outputPath('03-output-per-hour.png'), fullPage: true });

  // Dark theme
  await page.getByRole('button', { name: 'Dark Mode' }).click();
  await expect(page.getByRole('button', { name: 'Light Mode' })).toBeVisible();
  await page.waitForTimeout(800);
  await page.screenshot({ path: testInfo.outputPath('04-dark.png'), fullPage: true });

  // Narrow viewports
  await page.setViewportSize({ width: 1024, height: 800 });
  await expect(page.getByRole('heading', { name: 'Output per hour' })).toBeVisible();
  await page.screenshot({ path: testInfo.outputPath('05-dark-1024.png'), fullPage: true });
  await page.setViewportSize({ width: 390, height: 800 });
  await expect(page.getByRole('heading', { name: 'Production · planned vs actual' })).toBeVisible();
  await page.waitForTimeout(800);
  await page.screenshot({ path: testInfo.outputPath('06-dark-390.png'), fullPage: true });
});
