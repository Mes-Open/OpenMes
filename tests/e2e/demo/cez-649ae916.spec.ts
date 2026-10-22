import { test, expect } from '@playwright/test';

test('installation currency flows into worker pay and cost reports', async ({ page }, testInfo) => {
  test.setTimeout(290_000);

  await page.goto('/login');
  await page.getByLabel(/username/i).fill(process.env.ADMIN_USERNAME!);
  await page.getByLabel(/password/i).fill(process.env.ADMIN_PASSWORD!);
  await page.getByRole('button', { name: /sign in|log in/i }).click();

  // Fresh system → install the example company so the cost report has orders.
  await expect(page.getByText('Welcome to OpenMES').first()).toBeVisible();
  await page.getByRole('button', { name: 'Install this example company' }).click();
  await expect(page.getByRole('link', { name: 'Settings' }).first()).toBeVisible({ timeout: 120_000 });
  await page.screenshot({ path: testInfo.outputPath('01-dashboard.png'), fullPage: true });

  // Settings → System Settings → Currency
  await page.getByRole('link', { name: 'Settings' }).first().click();
  await page.getByRole('link', { name: /System Settings/ }).first().click();
  await expect(page.getByRole('heading', { name: 'Currency' })).toBeVisible();
  const currency = page.getByRole('combobox', { name: /PLN|EUR/ });
  await currency.scrollIntoViewIfNeeded();
  await expect(currency).toContainText('PLN - Polish');
  await page.screenshot({ path: testInfo.outputPath('02-settings-pln.png'), fullPage: true });
  await currency.click();
  await page.getByRole('option', { name: /EUR - Euro/ }).click();
  await page.getByRole('button', { name: 'Save', exact: true }).click();
  await expect(page.getByText('System settings updated.')).toBeVisible();
  await expect(page.getByRole('combobox', { name: /EUR/ })).toContainText('EUR - Euro');
  await page.waitForTimeout(800);
  await page.screenshot({ path: testInfo.outputPath('03-settings-eur-saved.png'), fullPage: true });

  // HR → Workers → new worker with an hourly rate of 40
  await page.getByRole('button', { name: 'HR' }).first().click();
  await page.getByRole('link', { name: 'Workers' }).first().click();
  await page.getByRole('link', { name: /New Worker/ }).first().click();
  await page.locator('input[type="text"]').nth(0).fill('QA-001');
  await page.locator('input[type="text"]').nth(1).fill('Quincy Tester');
  await page.getByText('Use system default', { exact: true }).click();
  await page.getByRole('option', { name: 'Hourly' }).click();
  await page.locator('input[type="number"]').first().fill('40');
  await expect(page.getByTestId('pay-rate-currency')).toHaveText('EUR');
  await page.screenshot({ path: testInfo.outputPath('04-new-worker-form-eur.png'), fullPage: true });
  await page.getByRole('button', { name: 'Create Worker' }).click();

  await page.getByRole('link', { name: 'Quincy Tester' }).first().click();
  await expect(page.getByTestId('worker-compensation')).toContainText('Hourly');
  await expect(page.getByTestId('worker-pay-rate')).toHaveText('40.00 EUR');
  await page.waitForTimeout(800);
  await page.screenshot({ path: testInfo.outputPath('05-worker-detail-eur.png'), fullPage: true });

  await page.getByRole('link', { name: 'Edit' }).first().click();
  await expect(page.getByTestId('pay-rate-currency')).toHaveText('EUR');
  await expect(page.getByText(/Rate in the system currency \(EUR\)/)).toBeVisible();
  await page.waitForTimeout(800);
  await page.screenshot({ path: testInfo.outputPath('06-worker-edit-eur.png'), fullPage: true });
  await page.getByRole('link', { name: 'Cancel' }).first().click();

  // Analytics → Production Cost Report
  await page.getByRole('button', { name: 'Analytics' }).first().click();
  await page.getByRole('link', { name: 'Production Cost Report' }).first().click();
  await expect(page.getByRole('heading', { name: 'Production Cost Report' })).toBeVisible();
  await expect(page.getByText(/EUR/).first()).toBeVisible();
  await page.waitForTimeout(800);
  await page.screenshot({ path: testInfo.outputPath('07-cost-report-eur.png'), fullPage: true });

  // Order detail
  await page.getByRole('link', { name: /^WO-BK-\d+/ }).nth(1).click();
  await expect(page.getByText('Total cost').first()).toBeVisible();
  await expect(page.getByText(/\d[\d,]*\.\d{2} EUR/).first()).toBeVisible();
  await page.waitForTimeout(800);
  await page.screenshot({ path: testInfo.outputPath('08-cost-detail-eur.png'), fullPage: true });

  // Back to the report and export CSV
  await page.getByRole('link', { name: /Production Cost Report/ }).first().click();
  const downloadPromise = page.waitForEvent('download');
  await page.getByRole('link', { name: 'Export CSV' }).click();
  const download = await downloadPromise;
  expect(download.suggestedFilename()).toMatch(/\.csv$/);
  await expect(page.getByText('Total cost').first()).toBeVisible();

  // Switch back to PLN
  await page.getByRole('button', { name: 'Settings' }).first().click();
  await page.getByRole('link', { name: /System Settings/ }).first().click();
  await expect(page.getByRole('heading', { name: 'Currency' })).toBeVisible();
  await page.getByRole('combobox', { name: /EUR/ }).click();
  await page.getByRole('option', { name: /PLN - Polish/ }).click();
  await page.getByRole('button', { name: 'Save', exact: true }).click();
  await expect(page.getByText('System settings updated.')).toBeVisible();
  await expect(page.getByRole('combobox', { name: /PLN/ })).toContainText('PLN - Polish');
  await page.screenshot({ path: testInfo.outputPath('09-settings-back-to-pln.png'), fullPage: true });

  await page.getByRole('link', { name: 'Production Cost Report' }).first().click();
  await expect(page.getByText(/PLN/).first()).toBeVisible();
  await expect(page.getByText(/EUR/)).toHaveCount(0);
  await page.waitForTimeout(800);
  await page.screenshot({ path: testInfo.outputPath('10-cost-report-pln.png'), fullPage: true });
});
