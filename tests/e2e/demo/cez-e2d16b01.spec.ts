import { test, expect, type Page } from '@playwright/test';

const ADMIN_USER = process.env.ADMIN_USERNAME || 'admin';
const ADMIN_PASS = process.env.ADMIN_PASSWORD || 'Admin1234!';
const OP_USER = 'qa.orders.op';
const OP_PASS = 'QaOrders#2026pass';

async function login(page: Page, user: string, pass: string) {
  await page.getByLabel(/username/i).fill(user);
  await page.getByLabel(/password/i).fill(pass);
  await page.getByRole('button', { name: /sign in|log in|login/i }).click();
}

async function logout(page: Page) {
  await page.getByRole('button', { name: 'Logout' }).first().click();
  await expect(page.getByRole('button', { name: /sign in|log in|login/i })).toBeVisible({ timeout: 30000 });
}

test('work orders: tab access alone no longer allows creating orders; Admin still can', async ({ page }, testInfo) => {
  page.setDefaultTimeout(15000);
  await page.goto('/login');
  await login(page, ADMIN_USER, ADMIN_PASS);

  const empty = page.getByText('Start with an empty system');
  await expect(empty.or(page.getByRole('link', { name: 'Settings' }).first())).toBeVisible({ timeout: 60000 });
  if (await empty.isVisible()) await empty.click();
  await expect(page.getByRole('link', { name: 'Settings' }).first()).toBeVisible({ timeout: 60000 });
  await page.screenshot({ path: testInfo.outputPath('01-admin-dashboard.png'), fullPage: true });

  // Grant the Operator role access to the Orders tab only
  await page.getByRole('link', { name: 'Settings' }).first().click();
  await page.getByRole('link', { name: 'Tab Access', exact: true }).click();
  await expect(page.getByRole('heading', { name: 'Tab Access', level: 1 })).toBeVisible();
  await expect(page.getByRole('columnheader').nth(1)).toBeVisible();
  const ordersRow = page.getByRole('row', { name: /^Orders/ }).first();
  const headers = await page.getByRole('columnheader').allTextContents();
  const opIdx = headers.findIndex((h) => h.trim().toLowerCase().startsWith("operator"));
  expect(opIdx).toBeGreaterThan(0);
  const opOrders = ordersRow.getByRole('checkbox').nth(opIdx - 1);
  if ((await opOrders.getAttribute('aria-checked')) !== 'true') await opOrders.click();
  await expect(ordersRow.getByRole('checkbox').nth(opIdx - 1)).toBeChecked();
  await page.screenshot({ path: testInfo.outputPath('02-operator-orders-ticked.png'), fullPage: true });
  const [saveResp] = await Promise.all([
    page.waitForResponse((r) => r.url().includes('/settings/access') && r.request().method() === 'POST'),
    page.getByRole('button', { name: 'Save' }).click(),
  ]);
  expect(saveResp.status()).toBeLessThan(400);
  await page.waitForTimeout(2500);
  await page.getByRole('heading', { name: 'Tab Access', level: 1 }).scrollIntoViewIfNeeded();
  await page.screenshot({ path: testInfo.outputPath('02b-after-save.png'), fullPage: true });
  await expect(ordersRow.getByRole('checkbox').nth(opIdx - 1)).toBeChecked();
  await page.waitForTimeout(800);
  await page.screenshot({ path: testInfo.outputPath('03-tab-access-saved.png'), fullPage: true });

  // Create an Operator user
  const usersLink = page.getByRole('link', { name: /^Users/ }).first();
  if (!(await usersLink.isVisible())) await page.getByText('Admin', { exact: true }).first().click();
  await usersLink.click();
  await page.getByRole('button', { name: /New Account/ }).first().click();
  const form = page.locator('form').filter({ hasText: 'Account Type' });
  await form.getByLabel(/^Name/).fill('QA Orders Operator');
  await form.getByLabel(/^Username/).fill(OP_USER);
  await form.getByLabel(/^Email/).fill('qa.orders.op@example.com');
  await form.getByLabel(/^Password/).first().fill(OP_PASS);
  await form.getByLabel(/^Confirm Password/).fill(OP_PASS);
  await form.getByText('— Select role —').click();
  await page.getByText('Operator', { exact: true }).last().click();
  await page.screenshot({ path: testInfo.outputPath('04-new-operator-form.png'), fullPage: true });
  await page.getByRole('button', { name: 'Create Account' }).click();
  await expect(page.getByText('Account created successfully.')).toBeVisible({ timeout: 30000 });
  await page.waitForTimeout(800);
  await page.screenshot({ path: testInfo.outputPath('05-operator-created.png'), fullPage: true });
  await logout(page);

  // Operator: Orders visible, but no "New Work Order"
  await login(page, OP_USER, OP_PASS);
  const ordersLink = page.getByRole('link', { name: /Orders/ }).first();
  await expect(ordersLink).toBeVisible({ timeout: 30000 });
  await page.screenshot({ path: testInfo.outputPath('06-operator-home.png'), fullPage: true });
  await ordersLink.click();
  await expect(page.getByText(/Work Orders/).first()).toBeVisible();
  const woLink = page.getByRole('link', { name: /Work Orders/ }).first();
  if (await woLink.isVisible().catch(() => false)) await woLink.click();
  await page.waitForTimeout(1500);
  await expect(page.getByRole('link', { name: /New Work Order/i })).toHaveCount(0);
  await expect(page.getByRole('button', { name: /New Work Order/i })).toHaveCount(0);
  await page.waitForTimeout(800);
  await page.screenshot({ path: testInfo.outputPath('07-operator-orders-no-button.png'), fullPage: true });
  await logout(page);

  // Admin: button is there and creating works
  await login(page, ADMIN_USER, ADMIN_PASS);
  await expect(page.getByRole('link', { name: 'Settings' }).first()).toBeVisible({ timeout: 60000 });
  await page.getByRole('link', { name: /Orders/ }).first().click();
  const wo = page.getByRole('link', { name: /Work Orders/ }).first();
  if (await wo.isVisible().catch(() => false)) await wo.click();
  const newBtn = page.getByRole('link', { name: /New Work Order/i }).or(page.getByRole('button', { name: /New Work Order/i })).first();
  await expect(newBtn).toBeVisible({ timeout: 30000 });
  await page.waitForTimeout(800);
  await page.screenshot({ path: testInfo.outputPath('08-admin-orders-with-button.png'), fullPage: true });
  await newBtn.click();
  await page.screenshot({ path: testInfo.outputPath('09-admin-new-order-form.png'), fullPage: true });
});
