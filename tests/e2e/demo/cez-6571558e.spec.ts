import { test, expect } from '@playwright/test';

test('dashboard shows live "OEE · this shift", compares with Plant Board, follows line target change', async ({ page }, testInfo) => {
  await page.setViewportSize({ width: 1440, height: 900 });
  await page.goto('/login');
  await page.getByLabel(/username/i).fill(process.env.ADMIN_USERNAME || 'admin');
  await page.getByLabel(/password/i).fill(process.env.ADMIN_PASSWORD || 'Admin1234!');
  await page.getByRole('button', { name: /sign in|log in|login/i }).click();

  await expect(page.getByRole('heading', { name: 'Welcome to OpenMES' })).toBeVisible({ timeout: 60000 });
  await page.getByRole('button', { name: 'Install this example company' }).click();
  await expect(page.getByRole('heading', { name: 'Welcome to OpenMES' })).toBeHidden({ timeout: 240000 });
  await expect(page.getByText('Settings', { exact: true }).first()).toBeVisible({ timeout: 60000 });

  // Dashboard
  await page.getByRole('link', { name: 'Dashboard', exact: true }).first().click();
  await expect(page).toHaveURL(/\/admin\/dashboard/, { timeout: 30000 });
  const section = page.getByTestId('oee-section');
  await expect(page.getByRole('heading', { name: 'OEE · this shift' })).toBeVisible({ timeout: 30000 });
  await expect(page.getByTestId('oee-plant-target')).toContainText(/Target \d+(\.\d)?%/);
  await expect(page.getByText('By line')).toBeVisible();
  await expect(page.getByTestId('oee-line-row').first()).toBeVisible();
  await section.scrollIntoViewIfNeeded();
  await page.waitForTimeout(800);
  await page.screenshot({ path: testInfo.outputPath('01-oee-section.png'), fullPage: true });
  const plantValue = await page.getByTestId('oee-plant-value').innerText();
  const lineRows = await page.getByTestId('oee-line-row').allInnerTexts();
  console.log('PLANT', plantValue, 'LINES', JSON.stringify(lineRows));

  // Tooltip
  await page.getByRole('button', { name: 'What is OEE?' }).focus();
  await expect(page.getByRole('tooltip')).toBeVisible();
  await expect(page.getByRole('tooltip')).toContainText('Availability × Performance × Quality');
  await expect(page.getByRole('tooltip')).toContainText('planning efficiency');
  await page.waitForTimeout(800);
  await page.screenshot({ path: testInfo.outputPath('02-tooltip.png'), fullPage: true });

  // Plant board
  await page.getByRole('link', { name: 'Plant Board' }).first().click();
  await expect(page).toHaveURL(/\/admin\/shift-board/, { timeout: 30000 });
  await page.waitForTimeout(2500);
  await page.screenshot({ path: testInfo.outputPath('03-plant-board.png'), fullPage: true });

  // Line settings: planning efficiency 80
  await page.getByText('Settings', { exact: true }).first().click();
  await page.getByRole('link', { name: /System Settings/ }).first().click();
  await expect(page).toHaveURL(/\/settings\/system/, { timeout: 30000 });
  await page.getByRole('tab', { name: 'Production' }).click();
  await page.getByRole('link', { name: 'Line settings' }).click();
  await expect(page).toHaveURL(/\/admin\/lines/, { timeout: 30000 });
  await page.getByRole('row', { name: /Cooling & Packing/ }).getByRole('link', { name: /Edit/ })
    .or(page.getByRole('row', { name: /Cooling & Packing/ }).getByRole('button', { name: /Edit/ })).first().click();
  const eff = page.getByLabel(/Planning efficiency/);
  await expect(eff).toBeVisible({ timeout: 30000 });
  await eff.fill('80');
  await page.screenshot({ path: testInfo.outputPath('04-line-efficiency-80.png'), fullPage: true });
  await page.getByRole('button', { name: /Save/ }).first().click();
  await expect(eff).toBeHidden({ timeout: 30000 });
  await page.waitForTimeout(800);
  await page.screenshot({ path: testInfo.outputPath('05-line-saved.png'), fullPage: true });

  // Back to dashboard
  await page.getByRole('link', { name: 'Dashboard', exact: true }).first().click();
  await expect(page).toHaveURL(/\/admin\/dashboard/, { timeout: 30000 });
  await expect(page.getByRole('heading', { name: 'OEE · this shift' })).toBeVisible({ timeout: 30000 });
  await expect(page.getByRole('img', { name: /Cooling & Packing .*target 80%/ })).toBeVisible();
  await page.getByTestId('oee-section').scrollIntoViewIfNeeded();
  await page.waitForTimeout(800);
  await page.screenshot({ path: testInfo.outputPath('06-dashboard-target-80.png'), fullPage: true });
});
