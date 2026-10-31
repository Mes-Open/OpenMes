import { test, expect } from '@playwright/test';

test('admin works the dashboard "Needs action" list', async ({ page }, testInfo) => {
  await page.setViewportSize({ width: 1440, height: 900 });
  await page.goto('/login');
  await page.getByLabel(/username/i).fill(process.env.ADMIN_USERNAME || 'admin');
  await page.getByLabel(/password/i).fill(process.env.ADMIN_PASSWORD || 'Admin1234!');
  await page.getByRole('button', { name: /sign in|log in|login/i }).click();

  await expect(page.getByRole('heading', { name: 'Welcome to OpenMES' })).toBeVisible({ timeout: 60000 });
  await page.getByRole('button', { name: 'Install this example company' }).click();
  await expect(page.getByRole('heading', { name: 'Welcome to OpenMES' })).toBeHidden({ timeout: 240000 });
  await expect(page.getByText('Settings', { exact: true }).first()).toBeVisible({ timeout: 60000 });

  // Setup through the UI: a failed inbound inspection (demo data has none)
  await page.getByRole('link', { name: 'Dashboard', exact: true }).first().click();
  await page.getByRole('link', { name: /View all/ }).first().click();
  await expect(page.getByRole('heading', { name: 'Inspections', exact: true })).toBeVisible({ timeout: 30000 });
  await page.getByRole('combobox', { name: 'Material' }).click();
  await page.getByRole('option').filter({ hasNotText: 'no inspection plan' }).first().click();
  await page.getByLabel('Lot number').fill('LOT-NEEDS-ACTION-1');
  await page.getByLabel('Quantity received').fill('100');
  await page.screenshot({ path: testInfo.outputPath('00a-start-inspection.png'), fullPage: true });
  await page.getByRole('button', { name: 'Start inspection' }).click();
  await expect(page.getByRole('heading', { name: 'Criteria' })).toBeVisible({ timeout: 30000 });
  const failBtns = page.getByRole('button', { name: 'Fail', exact: true });
  const nFail = await failBtns.count();
  for (let i = 0; i < nFail; i++) { await failBtns.nth(i).click(); await page.waitForTimeout(500); }
  const nums = page.getByRole('spinbutton');
  const nNum = await nums.count();
  for (let i = 0; i < nNum; i++) {
    await nums.nth(i).fill('99999');
    await page.getByRole('button', { name: 'Record result' }).nth(i).click();
    await page.waitForTimeout(500);
  }
  await page.screenshot({ path: testInfo.outputPath('00b-failing-results.png'), fullPage: true });
  await page.getByRole('button', { name: 'Complete inspection' }).click();
  await page.getByRole('button', { name: 'Complete inspection' }).last().click();
  await expect(page.getByRole('heading', { name: 'Disposition' })).toBeVisible({ timeout: 30000 });
  await page.screenshot({ path: testInfo.outputPath('00c-completed-fail.png'), fullPage: true });

  // Back to the admin dashboard (the operator layout has no admin menu)
  await page.goBack();
  await page.goBack();
  await expect(page.getByRole('link', { name: 'Dashboard', exact: true }).first()).toBeVisible({ timeout: 30000 });
  const section = page.getByTestId('needs-action');
  await section.scrollIntoViewIfNeeded();
  await expect(section.getByRole('heading', { name: /Needs action/ })).toBeVisible({ timeout: 60000 });
  await page.waitForTimeout(800);
  await page.screenshot({ path: testInfo.outputPath('01-needs-action.png'), fullPage: true });

  const items = section.getByTestId('needs-action-item');
  await expect(items.first()).toBeVisible();
  await expect(section.getByRole('link', { name: /All alerts/ })).toBeVisible();

  // Open ticket on a down line
  const openTicket = section.getByRole('button', { name: 'Open ticket' }).first();
  await expect(openTicket).toBeVisible();
  await openTicket.click();
  await expect(page.getByText(/Escalated to maintenance/)).toBeVisible({ timeout: 30000 });
  await page.waitForTimeout(800);
  await page.screenshot({ path: testInfo.outputPath('02-ticket-opened.png'), fullPage: true });
  const viewTicket = section.getByRole('link', { name: 'View ticket' }).first();
  await expect(viewTicket).toBeVisible({ timeout: 30000 });
  await viewTicket.click();
  await expect(page.getByText(/Showing issue #\d+ only/)).toBeVisible({ timeout: 30000 });
  await expect(page.getByText('Stop escalated from shift monitor').first()).toBeVisible();
  await page.waitForTimeout(800);
  await page.screenshot({ path: testInfo.outputPath('03-issue-filtered.png'), fullPage: true });

  // Back to dashboard, Review
  await page.getByRole('link', { name: 'Dashboard', exact: true }).first().click();
  await expect(section.getByRole('heading', { name: /Needs action/ })).toBeVisible({ timeout: 60000 });
  const review = section.getByRole('link', { name: 'Review' }).first();
  await expect(review).toBeVisible({ timeout: 60000 });
  await review.click();
  await expect(page.getByText(/Inspection/i).first()).toBeVisible({ timeout: 30000 });
  await page.waitForTimeout(800);
  await page.screenshot({ path: testInfo.outputPath('04-review.png'), fullPage: true });

  // Show all (only when the list is longer than six rows — the demo data has fewer)
  await page.goBack();
  await expect(section.getByRole('heading', { name: /Needs action/ })).toBeVisible({ timeout: 60000 });
  await page.waitForTimeout(800);
  if (await section.getByRole('button', { name: /Show all/ }).count()) {
    await section.getByRole('button', { name: /Show all/ }).click();
    await expect(section.getByRole('button', { name: 'Show less' })).toBeVisible();
    await page.screenshot({ path: testInfo.outputPath('05-show-all.png'), fullPage: true });
    await section.getByRole('button', { name: 'Show less' }).click();
  }
  await expect(section.getByRole('link', { name: 'View ticket' })).toBeVisible();
  await page.screenshot({ path: testInfo.outputPath('05-dashboard-after.png'), fullPage: true });

  // All alerts
  await section.getByRole('link', { name: /All alerts/ }).click();
  await expect(page).toHaveURL(/\/admin\/alerts/, { timeout: 30000 });
  await page.waitForTimeout(800);
  await page.screenshot({ path: testInfo.outputPath('06-alerts.png'), fullPage: true });
});
