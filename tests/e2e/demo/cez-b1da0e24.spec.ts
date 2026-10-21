import { test, expect } from '@playwright/test';

test('Quality inspector permission: grant to an operator, operator inspects, cannot disposition', async ({ page }, testInfo) => {
  test.setTimeout(290_000);
  page.setDefaultTimeout(20_000);

  // Admin login
  await page.goto('/login');
  await page.getByLabel(/username/i).fill(process.env.ADMIN_USERNAME!);
  await page.getByLabel(/password/i).fill(process.env.ADMIN_PASSWORD!);
  await page.getByRole('button', { name: /sign in|log in|login/i }).click();

  // Fresh instance: install an example company (has operators + materials)
  await expect(page.getByText('Welcome to OpenMES').first()).toBeVisible();
  await page.getByText('Precision machine shop').click();
  await page.getByRole('button', { name: 'Install this example company' }).click();
  await expect(page.getByText('Welcome to OpenMES')).toHaveCount(0, { timeout: 120_000 });
  await page.screenshot({ path: testInfo.outputPath('01-installed.png'), fullPage: true });

  // Admin → Users → operator
  await page.getByRole('button', { name: /^Admin$/i }).click();
  await page.getByRole('link', { name: 'Users & Accounts' }).first().click();
  await expect(page.getByText('Ewa Baran').first()).toBeVisible();
  await page.screenshot({ path: testInfo.outputPath('02-users.png'), fullPage: true });
  await page.getByRole('row', { name: /Ewa Baran/ }).getByRole('link', { name: 'Edit' }).click();
  await expect(page.getByText('Quality permissions')).toBeVisible();
  await page.getByRole('checkbox', { name: 'Quality inspector' }).click();
  await page.screenshot({ path: testInfo.outputPath('03-grant-inspector.png'), fullPage: true });
  await page.getByRole('button', { name: 'Save Changes' }).click();
  await expect(page.getByText('Account updated successfully.')).toBeVisible();
  await page.screenshot({ path: testInfo.outputPath('04-saved.png'), fullPage: true });

  // Operator logs in on the tablet
  await page.getByRole('button', { name: /log ?out|sign out/i }).or(page.getByRole('link', { name: /log ?out|sign out/i })).first().click();
  await page.getByLabel(/username/i).fill('ewa.baran');
  await page.getByLabel(/password/i).fill('Operator1!');
  await page.getByRole('button', { name: /sign in|log in|login/i }).click();
  await expect(page.getByText(/Inspection & Packing/).first()).toBeVisible();
  await page.screenshot({ path: testInfo.outputPath('05-select-line.png'), fullPage: true });
  await page.getByText(/Inspection & Packing/).first().click();
  await expect(page.getByRole('link', { name: 'Inspections' })).toBeVisible();
  await page.screenshot({ path: testInfo.outputPath('06-inspections-tab.png'), fullPage: true });

  await page.getByRole('link', { name: 'Inspections' }).click();
  await expect(page.getByRole('heading', { name: 'Start inspection' })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Pending inspections' })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Awaiting disposition' })).toBeVisible();
  await page.screenshot({ path: testInfo.outputPath('07-inspections-page.png'), fullPage: true });
  await page.getByLabel('Material').click();
  await page.getByRole('option', { name: /MAT-BAR-42CRMO4/ }).click();
  await page.getByLabel('Lot number').fill('LOT-QA-001');
  await page.getByLabel('Quantity received').fill('50');
  await page.screenshot({ path: testInfo.outputPath('08-start-form.png'), fullPage: true });
  await page.getByRole('button', { name: 'Start inspection' }).click();
  await expect(page.getByText('Inspection started.')).toBeVisible();
  await page.screenshot({ path: testInfo.outputPath('09-inspection-started.png'), fullPage: true });

  await page.getByRole('button', { name: 'Complete inspection' }).click();
  await page.screenshot({ path: testInfo.outputPath('10-confirm.png'), fullPage: true });
  await page.getByRole('button', { name: 'Complete inspection' }).last().click();
  await expect(page.getByText('Awaiting disposition.').first()).toBeVisible();
  await expect(page.getByRole('button', { name: 'Accept', exact: true })).toHaveCount(0);
  await expect(page.getByRole('button', { name: 'Quarantine' })).toHaveCount(0);
  await page.waitForTimeout(800);
  await page.screenshot({ path: testInfo.outputPath('11-completed-no-disposition.png'), fullPage: true });
});
