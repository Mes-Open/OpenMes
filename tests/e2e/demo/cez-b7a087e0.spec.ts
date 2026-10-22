import { test, expect } from '@playwright/test';

test('HR form checkboxes line up with their labels on desktop and mobile', async ({ page }, testInfo) => {
  await page.goto('/login');
  await page.getByLabel(/username/i).fill(process.env.ADMIN_USERNAME || 'admin');
  await page.getByLabel(/password/i).fill(process.env.ADMIN_PASSWORD || 'Admin1234!');
  await page.getByRole('button', { name: /sign in|log in|login/i }).click();

  const empty = page.getByText('Start with an empty system');
  await expect(empty.or(page.getByText('HR', { exact: true }).first())).toBeVisible({ timeout: 60000 });
  if (await empty.isVisible()) {
    await empty.click();
  }
  await page.screenshot({ path: testInfo.outputPath('01-after-login.png'), fullPage: true });

  await page.setViewportSize({ width: 1440, height: 900 });
  const openWorkers = async () => {
    const workers = page.getByRole('link', { name: 'Workers', exact: true }).first();
    if (!(await workers.isVisible())) {
      await page.getByText('HR', { exact: true }).first().click();
    }
    await workers.click();
  };
  const newWorker = page.getByRole('link', { name: /New Worker/i }).or(page.getByRole('button', { name: /New Worker/i })).first();
  await openWorkers();
  await expect(newWorker).toBeVisible();
  await page.screenshot({ path: testInfo.outputPath('02-workers-list.png'), fullPage: true });

  await newWorker.click();
  const logistics = page.getByRole('checkbox', { name: /Logistics operator/ });
  const active = page.getByRole('checkbox', { name: 'Active', exact: true });
  await expect(logistics).toBeVisible();
  await expect(active).toBeChecked();
  await expect(logistics).not.toBeChecked();
  await logistics.scrollIntoViewIfNeeded();
  await page.waitForTimeout(800);
  await page.screenshot({ path: testInfo.outputPath('03-desktop-new-worker-unchecked.png'), fullPage: true });
  await logistics.click();
  await expect(logistics).toBeChecked();
  await page.waitForTimeout(800);
  await page.screenshot({ path: testInfo.outputPath('04-desktop-new-worker-toggled.png'), fullPage: true });

  // Create the worker so the edit form can be shown too
  await page.getByLabel(/^Code/).fill('W-QA1');
  await page.getByLabel(/^Name/).fill('Anna Kowalska');
  await page.getByRole('button', { name: 'Create Worker' }).click();
  await expect(page.getByText('Anna Kowalska').first()).toBeVisible();
  await page.waitForTimeout(800);
  await page.screenshot({ path: testInfo.outputPath('05-worker-created.png'), fullPage: true });

  // Edit from the list row
  await openWorkers();
  await expect(page.getByText('Anna Kowalska').first()).toBeVisible();
  const row = page.getByRole('row', { name: /Anna Kowalska/ });
  await row.getByRole('link', { name: 'Edit' }).click();
  await expect(page.getByRole('checkbox', { name: /Logistics operator/ })).toBeChecked();
  await expect(page.getByRole('checkbox', { name: 'Active', exact: true })).toBeChecked();
  await page.getByRole('checkbox', { name: /Logistics operator/ }).scrollIntoViewIfNeeded();
  await page.waitForTimeout(800);
  await page.screenshot({ path: testInfo.outputPath('06-desktop-edit-worker.png'), fullPage: true });

  // Users & Accounts drawer
  await page.getByRole('button', { name: /^Admin$/i }).first().click();
  await page.getByRole('link', { name: /Users & Accounts/ }).click();
  await page.getByRole('button', { name: /New Account/ }).or(page.getByRole('link', { name: /New Account/ })).first().click();
  const pw = page.getByRole('checkbox', { name: /Require password change/ });
  await expect(pw).toBeVisible();
  await page.waitForTimeout(800);
  await page.screenshot({ path: testInfo.outputPath('07-desktop-new-account.png'), fullPage: true });
  await page.getByRole('button', { name: /^Worker profile/ }).click();
  await page.waitForTimeout(800);
  await page.screenshot({ path: testInfo.outputPath('08-desktop-worker-profile.png'), fullPage: true });

  // Mobile
  await page.setViewportSize({ width: 390, height: 844 });
  await pw.scrollIntoViewIfNeeded();
  await page.waitForTimeout(800);
  await page.screenshot({ path: testInfo.outputPath('09-mobile-new-account.png'), fullPage: true });
  await expect(pw).toBeVisible();

  // Mobile: back to the worker form
  await page.getByRole('button', { name: 'Close' }).click();
  await openWorkers();
  await newWorker.click();
  const mLogistics = page.getByRole('checkbox', { name: /Logistics operator/ });
  await expect(mLogistics).toBeVisible();
  await mLogistics.scrollIntoViewIfNeeded();
  await page.waitForTimeout(800);
  await page.screenshot({ path: testInfo.outputPath('10-mobile-new-worker.png'), fullPage: true });
  await mLogistics.click();
  await expect(mLogistics).toBeChecked();
  await page.waitForTimeout(800);
  await page.screenshot({ path: testInfo.outputPath('11-mobile-new-worker-toggled.png'), fullPage: true });
});
