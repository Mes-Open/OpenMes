import { test, expect } from '@playwright/test';

test('create an ERP API key with the masterdata scope, then revoke it', async ({ page }, testInfo) => {
  await page.goto('/login');
  await page.getByLabel(/username/i).fill(process.env.ADMIN_USERNAME || 'admin');
  await page.getByLabel(/password/i).fill(process.env.ADMIN_PASSWORD || 'Admin1234!');
  await page.getByRole('button', { name: /sign in|log in|login/i }).click();

  await page.getByRole('link', { name: 'Start with an empty system' }).or(page.getByRole('button', { name: 'Start with an empty system' })).click();
  await expect(page.getByRole('button', { name: /Settings/ }).or(page.getByRole('link', { name: /Settings/ })).first()).toBeVisible();
  await page.screenshot({ path: testInfo.outputPath('01-dashboard.png'), fullPage: true });

  // Settings group -> API Keys
  await page.getByRole('button', { name: /Settings/ }).or(page.getByRole('link', { name: /Settings/ })).first().click();
  await expect(page.getByRole('link', { name: 'API Keys' }).first()).toBeVisible();
  await page.getByRole('link', { name: 'API Keys' }).first().click();
  await expect(page.getByText('Personal access tokens').first()).toBeVisible();
  await expect(page.getByRole('heading', { name: 'ERP API keys' })).toBeVisible();
  await page.screenshot({ path: testInfo.outputPath('02-api-keys-page.png'), fullPage: true });

  // New key
  await page.getByLabel('Key name').fill('Odoo');
  const scope = page.locator('[data-testid="erp-api-keys"]');
  await expect(scope.getByText('erp:masterdata:read').first()).toBeVisible();
  await expect(scope.getByText('Read lines, warehouses & product types')).toBeVisible();
  await scope.getByRole('checkbox', { name: /erp:masterdata:read/ }).check();
  await scope.getByRole('checkbox', { name: /erp:production:read/ }).check();
  await scope.getByLabel('Key name').scrollIntoViewIfNeeded();
  await page.waitForTimeout(800);
  await page.screenshot({ path: testInfo.outputPath('03-scopes-ticked.png'), fullPage: true });

  await scope.getByRole('button', { name: 'Create key' }).click();
  await expect(page.getByText('API key created.').first()).toBeVisible();
  await expect(scope.getByTestId('erp-api-key-reveal')).toContainText('omk_');
  const row = scope.getByTestId('erp-api-key-row').filter({ hasText: 'Odoo' });
  await expect(row).toBeVisible();
  await expect(row).toContainText('erp:masterdata:read');
  await expect(row).toContainText('erp:production:read');
  await expect(row).toContainText('Active');
  await scope.getByTestId('erp-api-key-reveal').scrollIntoViewIfNeeded();
  await page.waitForTimeout(800);
  await page.screenshot({ path: testInfo.outputPath('04-key-created.png'), fullPage: true });

  // Revoke
  await row.getByRole('button', { name: 'Revoke key' }).click();
  const dlg = page.getByRole('alertdialog').or(page.getByRole('dialog')).first();
  await expect(dlg).toBeVisible();
  await page.screenshot({ path: testInfo.outputPath('05-revoke-confirm.png'), fullPage: true });
  await dlg.getByRole('button', { name: 'Revoke key' }).click();
  await expect(page.getByText('API key revoked.').first()).toBeVisible();
  await expect(scope.getByTestId('erp-api-key-row')).toHaveCount(0);
  await page.waitForTimeout(800);
  await page.screenshot({ path: testInfo.outputPath('06-key-revoked.png'), fullPage: true });
});
