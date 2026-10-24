import { test, expect } from '@playwright/test';

test('production targets card loads lazily and the OEE target survives finishing an order', async ({ page }, testInfo) => {
  await page.setViewportSize({ width: 1440, height: 900 });
  await page.goto('/login');
  await page.getByLabel(/username/i).fill(process.env.ADMIN_USERNAME || 'admin');
  await page.getByLabel(/password/i).fill(process.env.ADMIN_PASSWORD || 'Admin1234!');
  await page.getByRole('button', { name: /sign in|log in|login/i }).click();

  await expect(page.getByRole('heading', { name: 'Welcome to OpenMES' })).toBeVisible({ timeout: 60000 });
  await page.getByRole('button', { name: 'Install this example company' }).click();
  await expect(page.getByRole('heading', { name: 'Welcome to OpenMES' })).toBeHidden({ timeout: 240000 });
  await expect(page.getByText('Settings', { exact: true }).first()).toBeVisible({ timeout: 60000 });
  await page.screenshot({ path: testInfo.outputPath('01-dashboard.png'), fullPage: true });

  // Settings → System opens on General: no targets card at all
  await page.getByText('Settings', { exact: true }).first().click();
  await page.getByRole('link', { name: /System Settings/ }).first().click();
  await expect(page).toHaveURL(/\/settings\/system/, { timeout: 30000 });
  await expect(page.getByRole('tab', { name: 'Production' })).toBeVisible();
  await expect(page.getByText('Production targets')).toHaveCount(0);
  await page.waitForTimeout(800);
  await page.screenshot({ path: testInfo.outputPath('02-system-general.png'), fullPage: true });

  // Another tab, then Production: the card loads on demand
  await page.getByRole('tab', { name: 'Security' }).click();
  await page.waitForTimeout(500);
  await page.getByRole('tab', { name: 'Production' }).click();
  await expect(page.getByTestId('current-shift-fpy')).toContainText(/Current shift: \d+(\.\d)?%|No quality checks this shift/, { timeout: 30000 });
  const oee = page.getByTestId('plant-oee-target');
  await expect(oee).toContainText(/Plant OEE target this shift: \d+(\.\d)?%/);
  const before = (await oee.innerText()).trim();
  await page.getByTestId('plant-oee-target').scrollIntoViewIfNeeded();
  await page.waitForTimeout(800);
  await page.screenshot({ path: testInfo.outputPath('03-targets-loaded.png'), fullPage: true });

  // Work orders: cancel one order (leaves the plan) via the list
  await page.getByRole('button', { name: 'Orders', exact: true }).click();
  await page.getByRole('link', { name: 'All Orders' }).first().click();
  await expect(page).toHaveURL(/work-orders/, { timeout: 30000 });
  await page.waitForTimeout(1500);
  await page.screenshot({ path: testInfo.outputPath('04-work-orders.png'), fullPage: true });
  await page.getByRole('checkbox').nth(1).check();
  await page.getByRole('button', { name: 'Cancel', exact: true }).first().click();
  await page.getByRole('button', { name: 'Cancel orders' }).click();
  await page.waitForTimeout(1500);
  await page.screenshot({ path: testInfo.outputPath('05-order-cancelled.png'), fullPage: true });

  // Back to the targets card
  const sys = page.getByRole('link', { name: /System Settings/ }).or(page.getByRole('button', { name: /System Settings/ })).first();
  const open = await sys.waitFor({ state: 'visible', timeout: 3000 }).then(() => true, () => false);
  if (!open) await page.getByText('Settings', { exact: true }).first().click();
  await sys.click();
  await page.screenshot({ path: testInfo.outputPath('05b-settings-menu.png'), fullPage: true });
  await page.getByRole('link', { name: 'Production', exact: true }).click();
  await expect(page).toHaveURL(/tab=production/, { timeout: 30000 });
  await expect(page.getByTestId('plant-oee-target')).toContainText(/Plant OEE target this shift: \d+(\.\d)?%/, { timeout: 30000 });
  const after = (await page.getByTestId('plant-oee-target').innerText()).trim();
  expect(after).toBe(before);
  await page.getByTestId('plant-oee-target').scrollIntoViewIfNeeded();
  await page.waitForTimeout(800);
  await page.screenshot({ path: testInfo.outputPath('06-targets-unchanged.png'), fullPage: true });
});
