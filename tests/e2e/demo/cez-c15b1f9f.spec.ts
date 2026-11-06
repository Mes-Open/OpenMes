import { test, expect } from '@playwright/test';

test('admin adds a unit alias Stück → pcs and sees duplicate rejected', async ({ page }, testInfo) => {
  await page.goto('/login');
  await page.getByLabel(/username/i).fill(process.env.ADMIN_USERNAME || 'admin');
  await page.getByLabel(/password/i).fill(process.env.ADMIN_PASSWORD || 'Admin1234!');
  await page.getByRole('button', { name: /sign in|log in|login/i }).click();

  await page.getByRole('link', { name: 'Start with an empty system' }).or(page.getByRole('button', { name: 'Start with an empty system' })).click();
  const settings = page.getByRole('link', { name: 'Settings', exact: true }).or(page.getByRole('button', { name: 'Settings', exact: true })).first();
  await expect(settings).toBeVisible();
  await page.screenshot({ path: testInfo.outputPath('01-dashboard.png'), fullPage: true });

  await settings.click();
  const units = page.getByRole('link', { name: 'Units of Measure' }).first();
  await expect(units).toBeVisible();
  await page.screenshot({ path: testInfo.outputPath('02-settings-menu.png'), fullPage: true });
  await units.click();

  await expect(page.getByText('Unit Aliases').last()).toBeVisible();
  await expect(page.getByText('ERP imports translate these names')).toBeVisible();
  await expect(page.getByRole('row', { name: /C62\s+pcs/ }).first()).toBeVisible({ timeout: 20000 });
  await page.screenshot({ path: testInfo.outputPath('03-default-aliases.png'), fullPage: true });
  await page.getByPlaceholder(/Filter/).first().fill('units');
  await expect(page.getByRole('row', { name: /units\s+pcs/i }).first()).toBeVisible({ timeout: 20000 });
  await page.waitForTimeout(800);
  await page.screenshot({ path: testInfo.outputPath('03a-units-filtered.png'), fullPage: true });

  // Clean demo: remove the pre-filled Stück alias (the owner's row) first.
  await page.getByPlaceholder(/Filter/).first().fill('stück');
  await expect(page.getByRole('row', { name: /stück\s+pcs/i }).first()).toBeVisible();
  await page.screenshot({ path: testInfo.outputPath('03b-filtered-stueck.png'), fullPage: true });
  while (await page.getByRole('row', { name: /stück\s+pcs/i }).count() > 0) {
    await page.getByRole('row', { name: /stück\s+pcs/i }).first().getByRole('button', { name: /Delete/ }).click();
    await page.getByRole('button', { name: 'Delete', exact: true }).last().click();
    await page.waitForTimeout(1500);
    await page.reload();
    await page.getByPlaceholder(/Filter/).first().fill('stück');
    await page.waitForTimeout(800);
  }
  await page.screenshot({ path: testInfo.outputPath('03c-stueck-deleted.png'), fullPage: true });

  // Create Stück -> pcs
  await page.getByRole('button', { name: /New Alias/ }).or(page.getByRole('link', { name: /New Alias/ })).first().click();
  const dlg = page.getByRole('dialog');
  await dlg.getByLabel('Alias').fill('Stück');
  await dlg.getByLabel('Canonical unit').fill('pcs');
  await page.screenshot({ path: testInfo.outputPath('04-form-filled.png'), fullPage: true });
  await dlg.getByRole('button', { name: /Save|Create/ }).click();
  await expect(page.getByText('Unit alias created successfully.')).toBeVisible();
  await expect(dlg).toBeHidden();
  // No Reverb in the demo instance: refresh the live list by hand.
  await page.reload();
  await expect(page.getByRole('row', { name: /C62/ }).first()).toBeVisible({ timeout: 20000 });
  await page.getByPlaceholder(/Filter/).first().fill('Stück');
  await expect(page.getByRole('row', { name: /Stück\s+pcs/ })).toHaveCount(1, { timeout: 20000 });
  await page.waitForTimeout(800);
  await page.screenshot({ path: testInfo.outputPath('05-alias-listed.png'), fullPage: true });

  // Duplicate (case-insensitive)
  await page.getByRole('button', { name: /New Alias/ }).or(page.getByRole('link', { name: /New Alias/ })).first().click();
  const dlg2 = page.getByRole('dialog');
  await dlg2.getByLabel('Alias').fill('stück');
  await dlg2.getByLabel('Canonical unit').fill('pcs');
  await dlg2.getByRole('button', { name: /Save|Create/ }).click();
  await expect(dlg2.getByText('This alias is already defined.', { exact: true })).toBeVisible();
  await page.waitForTimeout(800);
  await page.screenshot({ path: testInfo.outputPath('06-duplicate-rejected.png'), fullPage: true });
});
