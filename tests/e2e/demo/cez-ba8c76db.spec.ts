import { test, expect } from '@playwright/test';

test('link an HR worker to a user account, then unlink', async ({ page }, testInfo) => {

  await page.goto('/login');
  await page.getByLabel(/username/i).fill(process.env.ADMIN_USERNAME || 'admin');
  await page.getByLabel(/password/i).fill(process.env.ADMIN_PASSWORD || 'Admin1234!');
  await page.getByRole('button', { name: /sign in|log in|login/i }).click();

  await page.getByRole('link', { name: 'Start with an empty system' }).or(page.getByRole('button', { name: 'Start with an empty system' })).click();
  await expect(page.getByRole('link', { name: 'HR' }).or(page.getByRole('button', { name: 'HR' })).first()).toBeVisible();
  await page.screenshot({ path: testInfo.outputPath('01-dashboard.png'), fullPage: true });

  // 1. Create the worker in HR
  await page.getByRole('link', { name: 'HR' }).or(page.getByRole('button', { name: 'HR' })).first().click();
  await page.getByRole('link', { name: 'Workers' }).first().click();
  await page.getByRole('link', { name: /New Worker/ }).first().click();
  await page.getByLabel('Code').fill('W-1001');
  await page.getByLabel('Name').fill('Jan Nowak');
  await page.screenshot({ path: testInfo.outputPath('02-new-worker.png'), fullPage: true });
  await page.getByRole('button', { name: /^(Create|Save)/ }).first().click();
  await expect(page.getByText('Jan Nowak').first()).toBeVisible();
  await page.screenshot({ path: testInfo.outputPath('03-worker-listed.png'), fullPage: true });

  // 2. New account
  await page.getByRole('link', { name: 'Admin', exact: true }).or(page.getByRole('button', { name: 'Admin', exact: true })).first().click();
  await page.getByRole('link', { name: 'Users & Accounts' }).first().click();
  await page.getByRole('button', { name: /New Account/ }).or(page.getByRole('link', { name: /New Account/ })).first().click();
  const dlg = page.getByRole('dialog');
  await dlg.getByLabel('Name', { exact: true }).fill('Jan Nowak Account');
  await dlg.getByLabel('Username').fill('jnowak');
  await dlg.getByLabel('Email').fill('jnowak@example.com');
  await dlg.getByLabel('Password', { exact: true }).fill('Secret1234!x');
  await dlg.getByLabel('Confirm Password').fill('Secret1234!x');
  await dlg.getByRole('combobox', { name: /Role/ }).or(dlg.getByRole('button', { name: /Select role/ })).first().click();
  await page.getByRole('option', { name: 'Operator' }).click();

  await dlg.getByRole('button', { name: /Worker profile/ }).click();
  await dlg.getByText('Link existing worker').click();
  await dlg.getByRole('button', { name: /Select worker/ }).or(dlg.getByRole('combobox', { name: /Worker/ })).first().click();
  await page.getByRole('combobox', { name: 'Search…' }).fill('nowak');
  await page.getByRole('option', { name: /W-1001 — Jan Nowak/ }).click();
  await expect(dlg.getByText('W-1001 — Jan Nowak').first()).toBeVisible();
  await page.screenshot({ path: testInfo.outputPath('04-linking.png'), fullPage: true });
  await dlg.getByRole('button', { name: 'Create Account' }).click();
  await expect(page.getByText('Account created successfully.')).toBeVisible();
  // No Reverb in the demo instance, so the live list is refreshed by hand (F5).
  await page.reload();
  await expect(page.getByRole('row', { name: /jnowak/ })).toContainText('W-1001', { timeout: 20000 });
  await page.waitForTimeout(800);
  await page.screenshot({ path: testInfo.outputPath('05-account-created.png'), fullPage: true });

  // 3. Edit shows linkage
  await page.getByRole('row', { name: /jnowak/ }).getByRole('link', { name: /Edit/ }).click();
  await expect(page.getByText('W-1001 — Jan Nowak').first()).toBeVisible();
  await expect(page.getByRole('link', { name: 'Open in HR' })).toBeVisible();
  await page.waitForTimeout(800);
  await page.screenshot({ path: testInfo.outputPath('06-edit-linked.png'), fullPage: true });

  // 4. Unlink
  await page.getByRole('button', { name: 'Unlink worker profile' }).click();
  await page.screenshot({ path: testInfo.outputPath('07-confirm.png'), fullPage: true });
  await page.getByRole('button', { name: 'Unlink worker profile' }).last().click();
  await expect(page.getByText('Worker profile unlinked. The worker record was kept.')).toBeVisible();
  await expect(page.getByText('Create new profile').first()).toBeVisible();
  await page.waitForTimeout(800);
  await page.screenshot({ path: testInfo.outputPath('08-unlinked.png'), fullPage: true });

  // 5. Worker still in HR
  await page.getByRole('button', { name: 'HR' }).click();
  await page.getByRole('link', { name: 'Workers' }).first().click();
  await expect(page.getByText('Jan Nowak').first()).toBeVisible();
  await page.waitForTimeout(800);
  await page.screenshot({ path: testInfo.outputPath('09-worker-kept.png'), fullPage: true });
});
