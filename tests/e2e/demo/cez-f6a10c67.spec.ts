import { test, expect } from '@playwright/test';

test('admin sets production targets, sees validation errors and the OEE target source', async ({ page }, testInfo) => {
  await page.setViewportSize({ width: 1440, height: 900 });
  await page.goto('/login');
  await page.getByLabel(/username/i).fill(process.env.ADMIN_USERNAME || 'admin');
  await page.getByLabel(/password/i).fill(process.env.ADMIN_PASSWORD || 'Admin1234!');
  await page.getByRole('button', { name: /sign in|log in|login/i }).click();

  await expect(page.getByRole('heading', { name: 'Welcome to OpenMES' })).toBeVisible({ timeout: 60000 });
  await page.screenshot({ path: testInfo.outputPath('00-welcome.png'), fullPage: true });
  await page.getByRole('button', { name: 'Install this example company' }).click();
  await expect(page.getByRole('heading', { name: 'Welcome to OpenMES' })).toBeHidden({ timeout: 240000 });
  await expect(page.getByText('Settings', { exact: true }).first()).toBeVisible({ timeout: 60000 });
  await page.screenshot({ path: testInfo.outputPath('01-dashboard.png'), fullPage: true });

  // Sidebar → Settings → System
  await page.getByText('Settings', { exact: true }).first().click();
  await page.getByRole('link', { name: /System Settings/ }).first().click();
  await expect(page).toHaveURL(/\/settings\/system/, { timeout: 30000 });
  await page.getByRole('tab', { name: 'Production' }).click();

  const card = page.getByRole('form', { name: 'Production targets' }).or(page.locator('form', { hasText: 'Production targets' }));
  const scrap = page.getByLabel('Max scrap rate (%)');
  const fpy = page.getByLabel('Min first-pass yield (%)');
  await scrap.scrollIntoViewIfNeeded();
  await expect(scrap).toHaveValue('2.0');
  await expect(fpy).toHaveValue('95.0');
  await expect(page.getByTestId('current-shift-fpy')).toContainText(/Current shift: \d+(\.\d)?%|No quality checks this shift|Quality module is disabled/);
  await expect(page.getByTestId('plant-oee-target')).toContainText('Plant OEE target this shift');
  await expect(page.getByRole('link', { name: 'Line settings' })).toBeVisible();
  await page.waitForTimeout(800);
  await page.screenshot({ path: testInfo.outputPath('02-targets-defaults.png'), fullPage: true });

  // Save valid values
  await scrap.fill('1.5');
  await fpy.fill('97');
  await page.getByRole('button', { name: 'Save targets' }).click();
  await expect(page.getByText('Production targets updated.')).toBeVisible();
  await page.waitForTimeout(800);
  await page.screenshot({ path: testInfo.outputPath('03-saved.png'), fullPage: true });

  // Reload → values persist
  await page.reload();
  await page.getByRole('tab', { name: 'Production' }).click();
  await expect(page.getByLabel('Max scrap rate (%)')).toHaveValue('1.5');
  await expect(page.getByLabel('Min first-pass yield (%)')).toHaveValue(/^97(\.0)?$/);
  await page.getByLabel('Max scrap rate (%)').scrollIntoViewIfNeeded();
  await page.waitForTimeout(800);
  await page.screenshot({ path: testInfo.outputPath('04-after-refresh.png'), fullPage: true });

  // Invalid value
  await page.getByLabel('Max scrap rate (%)').fill('150');
  await page.getByRole('button', { name: 'Save targets' }).click();
  await expect(page.getByText(/must not be greater than 100/)).toBeVisible();
  await page.waitForTimeout(800);
  await page.screenshot({ path: testInfo.outputPath('05-validation-error-en.png'), fullPage: true });

  // Polish
  await page.getByRole('tab', { name: 'General' }).click();
  await page.getByRole('button', { name: 'Select language' }).or(page.getByLabel('Select language')).first().click();
  await page.getByRole('option', { name: /Polski|Polish/ }).first().click();
  await page.getByRole('button', { name: 'Save', exact: true }).click();
  await expect(page.getByRole('tab', { name: 'Produkcja' })).toBeVisible({ timeout: 30000 });
  await page.getByRole('tab', { name: 'Produkcja' }).click();
  await page.getByLabel(/Maks\. wskaźnik braków/).fill('150');
  await page.getByRole('button', { name: /Zapisz cele/ }).click();
  await expect(page.getByText(/nie może być większe niż 100/)).toBeVisible();
  await page.waitForTimeout(800);
  await page.screenshot({ path: testInfo.outputPath('06-validation-error-pl.png'), fullPage: true });

  // Line settings link
  await page.getByRole('link', { name: /Ustawienia linii|Line settings/ }).click();
  await expect(page).toHaveURL(/\/admin\/lines/, { timeout: 30000 });
  await page.waitForTimeout(800);
  await page.screenshot({ path: testInfo.outputPath('07-lines.png'), fullPage: true });

  // Edit a line: planning efficiency is the source of the OEE target
  await page.getByRole('row', { name: /Cooling & Packing/ }).getByRole('link', { name: /Edytuj|Edit/ }).or(page.getByRole('row', { name: /Cooling & Packing/ }).getByRole('button', { name: /Edytuj|Edit/ })).first().click();
  const eff = page.getByLabel(/Sprawność planistyczna|Planning efficiency/);
  await expect(eff).toBeVisible({ timeout: 30000 });
  await eff.scrollIntoViewIfNeeded();
  await page.screenshot({ path: testInfo.outputPath('08-line-edit.png'), fullPage: true });
  await eff.fill('80');
  await page.waitForTimeout(800);
  await page.screenshot({ path: testInfo.outputPath('09-line-efficiency-80.png'), fullPage: true });
  await page.getByRole('button', { name: 'Zapisz zmiany' }).click();
  await expect(eff).toBeHidden({ timeout: 30000 });
  await page.waitForTimeout(800);
  await page.screenshot({ path: testInfo.outputPath('10-line-saved.png'), fullPage: true });

  // Back to the targets card: the plant OEE target reflects the 80% line if it has a plan this shift
  await page.getByRole('link', { name: 'Ustawienia', exact: true }).last().click();
  await page.getByRole('link', { name: /Ustawienia systemowe/ }).first().click();
  await page.getByRole('tab', { name: 'Produkcja' }).click();
  await expect(page.getByTestId('plant-oee-target')).toContainText('Cel OEE zakładu na tej zmianie');
  await page.getByTestId('plant-oee-target').scrollIntoViewIfNeeded();
  await page.waitForTimeout(800);
  await page.screenshot({ path: testInfo.outputPath('11-targets-after-line-change.png'), fullPage: true });
});
