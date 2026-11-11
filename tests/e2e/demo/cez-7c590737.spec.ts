import { test, expect } from '@playwright/test';

test('a draft goes stale when an operator is unassigned and Apply refuses', async ({ page }, testInfo) => {
  test.setTimeout(290_000);
  page.setDefaultTimeout(30_000);
  await page.setViewportSize({ width: 1440, height: 900 });

  await page.goto('/login');
  await page.getByLabel(/username/i).fill(process.env.ADMIN_USERNAME!);
  await page.getByLabel(/password/i).fill(process.env.ADMIN_PASSWORD!);
  await page.getByRole('button', { name: /sign in|log in|login/i }).click();
  await expect(page.getByText('Welcome to OpenMES').first()).toBeVisible({ timeout: 60_000 });
  const shop = page.getByText('Precision machine shop').locator('xpath=ancestor::*[.//input[@type="radio"]][1]').getByRole('radio');
  await shop.click();
  await page.getByRole('button', { name: 'Install this example company' }).click();
  await expect(page.getByText('Welcome to OpenMES')).toHaveCount(0, { timeout: 200_000 });
  await page.waitForLoadState('networkidle', { timeout: 60_000 }).catch(() => {});
  await expect(page.getByRole('button', { name: /^Admin$/i })).toBeVisible({ timeout: 60_000 });
  await page.screenshot({ path: testInfo.outputPath('01-installed.png'), fullPage: true });

  await page.getByRole('button', { name: /^Orders$/i }).click();
  await page.getByRole('link', { name: 'All Orders' }).click();
  await expect(page).toHaveURL(/work-orders/);
  await page.waitForTimeout(2500);
  await page.getByText('New Work Order', { exact: true }).click();
  const wo = page.getByRole('dialog').first();
  await expect(wo.getByLabel(/^Order No/i)).toBeVisible();
  await page.waitForTimeout(1500);
  await wo.getByLabel(/^Order No/i).fill('WO-STALE-01');
  await wo.getByLabel(/^Line/i).click();
  await page.getByRole('option', { name: 'CNC Turning' }).click();
  await wo.getByLabel(/^Product Type/i).click();
  await page.getByRole('option', { name: /Bronze Bushing/ }).click();
  await wo.getByLabel(/^Planned Qty/i).fill('40');
  await wo.getByRole('button', { name: /^(Create|Save)/ }).click();
  await page.waitForTimeout(2000);
  await page.screenshot({ path: testInfo.outputPath('02-wo-created.png'), fullPage: true });
  await page.getByRole('link', { name: 'Scheduler' }).first().click();
  await page.getByRole('link', { name: /Automatic scheduling/ }).click();
  await expect(page.getByText('New simulation')).toBeVisible();
  await page.getByRole('button', { name: /^Simulate/ }).click();
  await expect(page.getByRole('button', { name: 'Apply', exact: true })).toBeVisible({ timeout: 90000 });
  await expect(page.getByText(/Staffing changed since this draft/)).toHaveCount(0);
  await page.waitForTimeout(1000);
  await page.screenshot({ path: testInfo.outputPath('03-draft-fresh.png'), fullPage: true });

  // Staffing change: unassign an operator from CNC Turning
  await page.getByRole('button', { name: /^Production$/i }).click();
  await page.getByRole('button', { name: /Production Lines/i }).click();
  await page.getByRole('link', { name: 'All Production Lines' }).click();
  await page.getByRole('link', { name: /CNC Turning/ }).first().click();
  await expect(page.getByText('Assigned Operators').first()).toBeVisible();
  await page.screenshot({ path: testInfo.outputPath('04-line.png'), fullPage: true });
  await page.getByRole('button', { name: 'Remove operator' }).first().click();
  await page.getByRole('alertdialog').getByRole('button', { name: 'Confirm' }).click();
  await page.waitForTimeout(1500);
  await page.screenshot({ path: testInfo.outputPath('05-unassigned.png'), fullPage: true });

  await page.getByRole('link', { name: 'Scheduler' }).first().click();
  await page.getByRole('link', { name: /Automatic scheduling/ }).click();
  const msg = 'Staffing changed since this draft was generated — generate it again.';
  await expect(page.getByText(msg)).toBeVisible({ timeout: 30000 });
  await page.waitForTimeout(1000);
  await page.screenshot({ path: testInfo.outputPath('06-stale-banner.png'), fullPage: true });

  await page.getByRole('button', { name: 'Apply', exact: true }).click();
  await page.getByRole('alertdialog').getByRole('button', { name: 'Apply', exact: true }).click();
  await page.waitForTimeout(1500);
  await expect(page.getByText(msg).first()).toBeVisible();
  await expect(page.getByRole('button', { name: 'Apply', exact: true })).toBeVisible();
  await page.waitForTimeout(800);
  await page.screenshot({ path: testInfo.outputPath('07-apply-refused.png'), fullPage: true });
});
