import { test, expect, type Page } from '@playwright/test';

const ADMIN_USER = process.env.ADMIN_USERNAME || 'admin';
const ADMIN_PASS = process.env.ADMIN_PASSWORD || 'Admin1234!';

async function login(page: Page, user: string, pass: string) {
  await page.getByLabel(/username/i).fill(user);
  await page.getByLabel(/password/i).fill(pass);
  await page.getByRole('button', { name: /sign in|log in|login/i }).click();
}

async function logout(page: Page) {
  await page.getByRole('button', { name: 'Logout' }).first().click();
  await expect(page.getByRole('button', { name: /sign in|log in|login/i })).toBeVisible({ timeout: 30000 });
}

test('dashboard downtime card: empty state, report link, new stop shows up', async ({ page }, testInfo) => {
  page.setDefaultTimeout(60000);
  await page.setViewportSize({ width: 1440, height: 900 });
  await page.goto('/login');
  await login(page, ADMIN_USER, ADMIN_PASS);

  await expect(page.getByRole('heading', { name: 'Welcome to OpenMES' })).toBeVisible({ timeout: 60000 });
  await page.getByRole('button', { name: 'Install this example company' }).click();
  await expect(page.getByRole('heading', { name: 'Welcome to OpenMES' })).toBeHidden({ timeout: 240000 });
  await expect(page.getByText('Settings', { exact: true }).first()).toBeVisible({ timeout: 60000 });
  await page.waitForTimeout(1500);

  // Dashboard: Downtime card, no stops yet
  const card = page.getByTestId('overview-downtime');
  await card.scrollIntoViewIfNeeded();
  // Seed state depends on the time of day: either no stops yet, or unclassified AUTO stops.
  await expect(card.getByText('No downtime this shift').or(card.getByTestId('downtime-total'))).toBeVisible();
  await expect(card.getByRole('link', { name: /Downtime report/ })).toBeVisible();
  await page.waitForTimeout(800);
  await page.screenshot({ path: testInfo.outputPath('01-downtime-empty.png'), fullPage: true });

  // "Downtime report →" goes to Plant Board, not a 404
  await card.getByRole('link', { name: /Downtime report/ }).click();
  await expect(page).toHaveURL(/\/admin\/shift-board/, { timeout: 30000 });
  await expect(page.getByText('Not Found')).toHaveCount(0);
  await page.waitForTimeout(1500);
  await page.screenshot({ path: testInfo.outputPath('02-plant-board.png'), fullPage: true });

  // The admin UI has no link into the operator screens: create an Operator account
  const usersLink = page.getByRole('link', { name: /^Users/ }).first();
  if (!(await usersLink.isVisible())) await page.getByText('Admin', { exact: true }).first().click();
  await usersLink.click();
  await expect(page.getByText('Administrator').first()).toBeVisible();
  await page.waitForTimeout(2500);
  await page.getByRole('button', { name: /New Account/ }).first().click();
  const form = page.locator('form').filter({ hasText: 'Account Type' });
  await expect(form).toBeVisible({ timeout: 30000 });
  await form.getByText('Workstation', { exact: true }).first().click();
  await form.locator('input[type=text]').nth(0).fill('Line Operator');
  await form.locator('input[type=text]').nth(1).fill('qa.downtime.op');
  await form.locator('input[type=email]').fill('qa.downtime.op@example.com');
  await form.locator('input[type=password]').nth(0).fill('QaDowntime#2026pass');
  await form.locator('input[type=password]').nth(1).fill('QaDowntime#2026pass');
  await form.getByText('— Select workstation —').click();
  await page.getByRole('option').first().click();
  await page.screenshot({ path: testInfo.outputPath('03-new-account.png'), fullPage: true });
  await page.getByRole('button', { name: 'Create Account' }).click();
  await expect(page.getByText('Account created successfully.')).toBeVisible({ timeout: 30000 });
  await page.waitForTimeout(800);
  await page.screenshot({ path: testInfo.outputPath('04-account-created.png'), fullPage: true });

  // Operator terminal: report downtime
  await logout(page);
  await login(page, 'qa.downtime.op', 'QaDowntime#2026pass');
  await expect(page).toHaveURL(/\/operator\/queue/, { timeout: 60000 });
  await page.getByRole('button', { name: 'Report Downtime' }).click();
  await page.getByRole('button', { name: 'Reason' }).or(page.getByText('— Select reason —')).first().click();
  await page.getByRole('option').first().click();
  await page.waitForTimeout(800);
  await page.screenshot({ path: testInfo.outputPath('05-report-downtime.png'), fullPage: true });
  await page.getByRole('button', { name: 'Start Downtime' }).click();
  await expect(page.getByRole('button', { name: 'Stop Downtime' })).toBeVisible({ timeout: 30000 });
  await page.waitForTimeout(800);
  await page.screenshot({ path: testInfo.outputPath('06-downtime-running.png'), fullPage: true });

  // Back as admin: the Downtime card shows the new stop
  await logout(page);
  await login(page, ADMIN_USER, ADMIN_PASS);
  const card2 = page.getByTestId('overview-downtime');
  await expect(card2.getByTestId('downtime-total')).toBeVisible({ timeout: 70000 });
  await expect(card2.locator('[data-testid=downtime-reason]:not([data-kind=unclassified])').first()).toBeVisible();
  await card2.scrollIntoViewIfNeeded();
  await page.waitForTimeout(1500);
  await page.screenshot({ path: testInfo.outputPath('07-downtime-with-stop.png'), fullPage: true });
});
