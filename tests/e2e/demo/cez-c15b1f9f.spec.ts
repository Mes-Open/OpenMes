import { test, expect } from '@playwright/test';

test('admin adds a unit alias Stück → pcs in Settings → Units', async ({ page }, testInfo) => {
  await page.setViewportSize({ width: 1440, height: 900 });
  await page.goto('/login');
  await page.getByLabel(/username/i).fill(process.env.ADMIN_USERNAME || 'admin');
  await page.getByLabel(/password/i).fill(process.env.ADMIN_PASSWORD || 'Admin1234!');
  await page.getByRole('button', { name: /sign in|log in|login/i }).click();

  await expect(page.getByRole('heading', { name: 'Welcome to OpenMES' })).toBeVisible({ timeout: 60000 });
  await page.getByRole('button', { name: 'Start with an empty system' }).click();
  await expect(page.getByRole('heading', { name: 'Welcome to OpenMES' })).toBeHidden({ timeout: 120000 });
  await page.screenshot({ path: testInfo.outputPath('01-dashboard.png'), fullPage: true });

  await page.getByRole('link', { name: 'Settings', exact: true }).first().click();
  await page.getByRole('link', { name: /Units of Measure/ }).first().click();
  await expect(page.getByText('ERP imports translate these names')).toBeVisible();
  await expect(page.getByRole('cell', { name: 'C62', exact: true })).toBeVisible({ timeout: 30000 });
  await page.waitForTimeout(800);
  await page.screenshot({ path: testInfo.outputPath('02-alias-list.png'), fullPage: true });

  // Filter by Stück (the seeded default may already exist)
  await page.getByPlaceholder('Filter…').first().fill('Stück');
  await page.waitForTimeout(800);
  await page.screenshot({ path: testInfo.outputPath('03-filter-stuck.png'), fullPage: true });
  await expect(page.getByText('Unit alias deleted successfully.')).toHaveCount(0);
  const row = page.getByRole('row').filter({ hasText: /stück/i });
  if (await row.count()) {
    await row.first().getByRole('button').last().click();
    await page.getByRole('button', { name: 'Delete', exact: true }).last().click();
    await expect(page.getByText('Unit alias deleted successfully.')).toBeVisible({ timeout: 15000 });
    await page.waitForTimeout(3000);
    await page.screenshot({ path: testInfo.outputPath('03b-after-delete.png'), fullPage: true });
    // reopen the page so the list is reloaded without the deleted default
    await page.getByRole('link', { name: 'Dashboard', exact: true }).first().click();
    await page.getByRole('link', { name: 'Settings', exact: true }).first().click();
    await page.getByRole('link', { name: /Units of Measure/ }).first().click();
    await expect(page.getByRole('cell', { name: 'C62', exact: true })).toBeVisible({ timeout: 30000 });
    await page.getByPlaceholder('Filter…').first().fill('Stück');
    await page.waitForTimeout(1500);
    await expect(row).toHaveCount(0);
    await page.screenshot({ path: testInfo.outputPath('03c-default-gone.png'), fullPage: true });
  }

  await page.getByRole('button', { name: 'New Alias' }).first().click();
  const dlg = page.getByRole('dialog');
  await expect(dlg).toBeVisible();
  await dlg.getByLabel('Alias').fill('Stück');
  await dlg.getByLabel('Canonical unit').fill('pcs');
  await page.screenshot({ path: testInfo.outputPath('04-form-filled.png'), fullPage: true });
  await dlg.getByRole('button', { name: 'Create' }).click();

  await expect(page.getByText('Unit alias created successfully.')).toBeVisible({ timeout: 30000 });
  // The demo instance has no live-sync socket: reopen the page like a user would so the list loads fresh.
  await page.getByRole('link', { name: 'Dashboard', exact: true }).first().click();
  await page.getByRole('link', { name: 'Settings', exact: true }).first().click();
  await page.getByRole('link', { name: /Units of Measure/ }).first().click();
  await expect(page.getByRole('cell', { name: 'C62', exact: true })).toBeVisible({ timeout: 30000 });
  await page.getByPlaceholder('Filter…').first().fill('Stück');
  await expect(page.getByRole('cell', { name: 'Stück', exact: true })).toBeVisible({ timeout: 30000 });
  await expect(page.getByRole('row').filter({ hasText: 'Stück' }).getByText('pcs')).toBeVisible();
  await page.waitForTimeout(800);
  await page.screenshot({ path: testInfo.outputPath('05-alias-created.png'), fullPage: true });

  // Negative: duplicate
  await page.getByRole('button', { name: 'New Alias' }).first().click();
  const dlg2 = page.getByRole('dialog');
  await dlg2.getByLabel('Alias').fill('stück');
  await dlg2.getByLabel('Canonical unit').fill('pcs');
  await dlg2.getByRole('button', { name: 'Create' }).click();
  await expect(dlg2.getByRole('alert').getByText('This alias is already defined.', { exact: true })).toBeVisible({ timeout: 15000 });
  await page.waitForTimeout(800);
  await page.screenshot({ path: testInfo.outputPath('06-duplicate-rejected.png'), fullPage: true });
});
