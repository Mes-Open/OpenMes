import { test, expect } from '@playwright/test';

test('Quality inspector permission: grant to an operator, operator inspects, cannot disposition', async ({ page }, testInfo) => {
  test.setTimeout(290_000);
  page.setDefaultTimeout(30_000);

  // Login/logout helpers: on repeated login/logout cycles in the same SPA
  // session the submit click can occasionally race the page's hydration and
  // leave the form filled but un-submitted — resubmit once if that happens.
  const logout = () => page.getByRole('button', { name: /log ?out|sign out/i }).or(page.getByRole('link', { name: /log ?out|sign out/i })).first().click();
  const login = async (u: string, pw: string) => {
    await page.getByLabel(/username/i).fill(u);
    await page.getByLabel(/password/i).fill(pw);
    await page.getByRole('button', { name: /sign in|log in|login/i }).click();
    try {
      await expect(page.getByRole('heading', { name: 'Sign in' })).toHaveCount(0, { timeout: 20_000 });
    } catch {
      await page.getByRole('button', { name: /sign in|log in|login/i }).click();
      await expect(page.getByRole('heading', { name: 'Sign in' })).toHaveCount(0, { timeout: 20_000 });
    }
  };

  // Admin login
  await page.goto('/login');
  await login(process.env.ADMIN_USERNAME!, process.env.ADMIN_PASSWORD!);

  // Fresh instance: install an example company (has operators + materials)
  await expect(page.getByText('Welcome to OpenMES').first()).toBeVisible({ timeout: 20_000 });
  const machineShopOption = page
    .getByText('Precision machine shop')
    .locator('xpath=ancestor::*[.//input[@type="radio"]][1]')
    .getByRole('radio');
  await machineShopOption.click();
  await expect(machineShopOption).toBeChecked();
  await page.getByRole('button', { name: 'Install this example company' }).click();
  await expect(page.getByText('Welcome to OpenMES')).toHaveCount(0, { timeout: 180_000 });
  // The redirect lands on the dashboard before its JS/data has painted;
  // give it room to finish loading before the next nav click.
  await page.waitForLoadState('networkidle', { timeout: 60_000 }).catch(() => {});
  await expect(page.getByRole('button', { name: /^Settings$/i })).toBeVisible({ timeout: 60_000 });
  await page.screenshot({ path: testInfo.outputPath('01-installed.png'), fullPage: true });

  // Enable lot tracking so a released inspection creates a material lot
  await page.getByRole('button', { name: /^Settings$/i }).click();
  await page.getByRole('button', { name: 'System Settings' }).click();
  const productionTab = page.getByRole('link', { name: 'Production', exact: true }).last();
  await expect(productionTab).toBeVisible({ timeout: 20_000 });
  await productionTab.click();
  const lotTrackingSwitch = page.getByText('Lot tracking of materials').locator('xpath=ancestor::div[2]').getByRole('switch');
  await expect(lotTrackingSwitch).toBeVisible({ timeout: 20_000 });
  await lotTrackingSwitch.click();
  await page.screenshot({ path: testInfo.outputPath('01b-lot-tracking.png'), fullPage: true });
  await page.getByRole('button', { name: 'Save', exact: true }).click();
  await page.waitForTimeout(1500);

  // Admin → Users → operator
  await page.getByRole('button', { name: /^Admin$/i }).click();
  const usersLink = page.getByRole('link', { name: 'Users & Accounts' }).first();
  await expect(usersLink).toBeVisible({ timeout: 20_000 });
  await usersLink.click();
  await expect(page.getByText('Ewa Baran').first()).toBeVisible({ timeout: 20_000 });
  await page.screenshot({ path: testInfo.outputPath('02-users.png'), fullPage: true });
  await page.getByRole('row', { name: /Ewa Baran/ }).getByRole('link', { name: 'Edit' }).click();
  await expect(page.getByText('Quality permissions')).toBeVisible({ timeout: 20_000 });
  await page.getByRole('checkbox', { name: 'Quality inspector' }).click();
  await page.screenshot({ path: testInfo.outputPath('03-grant-inspector.png'), fullPage: true });
  await page.getByRole('button', { name: 'Save Changes' }).click();
  await expect(page.getByText('Account updated successfully.')).toBeVisible({ timeout: 20_000 });
  await page.screenshot({ path: testInfo.outputPath('04-saved.png'), fullPage: true });

  // Operator logs in on the tablet
  await logout();
  await login('ewa.baran', 'Operator1!');
  await expect(page.getByText(/Inspection & Packing/).first()).toBeVisible({ timeout: 20_000 });
  await page.screenshot({ path: testInfo.outputPath('05-select-line.png'), fullPage: true });
  await page.getByText(/Inspection & Packing/).first().click();
  await expect(page.getByRole('link', { name: 'Inspections' })).toBeVisible({ timeout: 20_000 });
  await page.screenshot({ path: testInfo.outputPath('06-inspections-tab.png'), fullPage: true });

  await page.getByRole('link', { name: 'Inspections' }).click();
  await expect(page.getByRole('heading', { name: 'Start inspection' })).toBeVisible({ timeout: 20_000 });
  await expect(page.getByRole('heading', { name: 'Pending inspections' })).toBeVisible({ timeout: 20_000 });
  await expect(page.getByRole('heading', { name: 'Awaiting disposition' })).toBeVisible({ timeout: 20_000 });
  await page.screenshot({ path: testInfo.outputPath('07-inspections-page.png'), fullPage: true });
  await page.getByLabel('Material').click();
  await page.getByRole('option', { name: /MAT-BAR-42CRMO4/ }).click();
  await page.getByLabel('Lot number').fill('LOT-QA-001');
  await page.getByLabel('Quantity received').fill('50');
  await page.screenshot({ path: testInfo.outputPath('08-start-form.png'), fullPage: true });
  await page.getByRole('button', { name: 'Start inspection' }).click();
  await expect(page.getByText('Inspection started.')).toBeVisible({ timeout: 20_000 });
  await page.screenshot({ path: testInfo.outputPath('09-inspection-started.png'), fullPage: true });

  for (const c of ['Mill certificate', 'Diameter tolerance', 'Straightness', 'Surface defects']) {
    const row = page.getByText(c).locator('xpath=ancestor::*[.//button[normalize-space()="Pass"]][1]');
    await row.getByRole('button', { name: 'Pass', exact: true }).click();
    await expect(row.locator('span', { hasText: 'Pass' })).toBeVisible({ timeout: 20_000 });
  }
  await page.screenshot({ path: testInfo.outputPath('09b-results-recorded.png'), fullPage: true });
  await page.getByRole('button', { name: 'Complete inspection' }).click();
  await page.screenshot({ path: testInfo.outputPath('10-confirm.png'), fullPage: true });
  await page.getByRole('button', { name: 'Complete inspection' }).last().click();
  await expect(page.getByText('Awaiting disposition.').first()).toBeVisible({ timeout: 20_000 });
  await expect(page.getByRole('button', { name: 'Accept', exact: true })).toHaveCount(0);
  await expect(page.getByRole('button', { name: 'Quarantine' })).toHaveCount(0);
  await page.waitForTimeout(800);
  await page.screenshot({ path: testInfo.outputPath('11-completed-no-disposition.png'), fullPage: true });

  // Disposition side: admin also grants disposition, the operator accepts the lot
  await logout();
  await login(process.env.ADMIN_USERNAME!, process.env.ADMIN_PASSWORD!);
  await page.getByRole('button', { name: /^Warehouses$/i }).click();
  await page.getByRole('link', { name: 'Material Lots' }).first().click();
  await expect(page.getByText('LOT-QA-001')).toHaveCount(0);
  await page.screenshot({ path: testInfo.outputPath('12-no-lot-yet.png'), fullPage: true });

  await page.getByRole('button', { name: /^Admin$/i }).click();
  await page.getByRole('link', { name: 'Users & Accounts' }).first().click();
  await page.getByRole('row', { name: /Ewa Baran/ }).getByRole('link', { name: 'Edit' }).click();
  await page.getByRole('checkbox', { name: 'Quality disposition' }).click();
  await page.getByRole('button', { name: 'Save Changes' }).click();
  await expect(page.getByText('Account updated successfully.')).toBeVisible({ timeout: 20_000 });
  await page.screenshot({ path: testInfo.outputPath('13-disposition-granted.png'), fullPage: true });
  await logout();
  await login('ewa.baran', 'Operator1!');
  await page.getByText(/Inspection & Packing/).first().click();
  await page.getByRole('link', { name: 'Inspections' }).click();
  await expect(page.getByRole('heading', { name: 'Awaiting disposition' })).toBeVisible({ timeout: 20_000 });
  await page.screenshot({ path: testInfo.outputPath('14-awaiting-list.png'), fullPage: true });
  await page.getByText('LOT-QA-001').first().click();
  await page.getByRole('button', { name: 'Accept', exact: true }).click();
  const confirm = page.getByRole('button', { name: 'Accept', exact: true }).last();
  if (await confirm.count() > 1) await confirm.click();
  await expect(page.getByText('Disposition applied.')).toBeVisible({ timeout: 20_000 });
  await page.waitForTimeout(800);
  await page.screenshot({ path: testInfo.outputPath('15-disposition-applied.png'), fullPage: true });

  await logout();
  await login(process.env.ADMIN_USERNAME!, process.env.ADMIN_PASSWORD!);
  await page.getByRole('button', { name: /^Warehouses$/i }).click();
  await page.getByRole('link', { name: 'Material Lots' }).first().click();
  await expect(page.getByRole('row', { name: /LOT-QA-001.*Released/ })).toBeVisible({ timeout: 20_000 });
  await page.waitForTimeout(800);
  await page.screenshot({ path: testInfo.outputPath('16-lot-released.png'), fullPage: true });
});
