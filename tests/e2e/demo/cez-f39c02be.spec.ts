import { test, expect, Page } from '@playwright/test';

const ADMIN = process.env.ADMIN_USERNAME || 'admin';
const PASS = process.env.ADMIN_PASSWORD || 'Admin1234!';

const EN_WORKER = 'Unique identifier of this worker across the whole system, e.g. W-201. No two workers can share a code.';
const PL_WORKER = 'Unikalny identyfikator pracownika w całym systemie, np. W-201. Dwóch pracowników nie może mieć tego samego kodu.';
const EN_ACCOUNT = 'Unique identifier of the worker profile linked to this account, e.g. W-201.';
const PL_ACCOUNT = 'Unikalny identyfikator profilu pracownika powiązanego z tym kontem, np. W-201.';

async function walk(page: Page, testInfo: any, n: string, hr: string, workers: string, newWorker: string, admin: string, users: string, newAccount: string, profile: string, hint: { w: string; a: string }) {
  await page.getByRole('button', { name: hr, exact: true }).click();
  await page.getByRole('link', { name: workers, exact: true }).first().click();
  await page.getByRole('link', { name: newWorker }).first().click();
  await expect(page.getByText(hint.w)).toBeVisible();
  await page.waitForTimeout(800);
  await page.screenshot({ path: testInfo.outputPath(`${n}a-worker-form.png`), fullPage: true });

  await page.getByRole('button', { name: admin, exact: true }).click();
  await page.getByRole('link', { name: users, exact: true }).first().click();
  await page.getByRole('button', { name: newAccount }).first().click();
  await page.getByRole('button', { name: profile }).click();
  await expect(page.getByText(hint.a)).toBeVisible();
  await page.waitForTimeout(800);
  await page.screenshot({ path: testInfo.outputPath(`${n}b-account-form.png`), fullPage: true });
}

test('worker code hint in worker and account forms, EN and PL', async ({ page }, testInfo) => {
  await page.goto('/login');
  await page.getByLabel(/username|email/i).fill(ADMIN);
  await page.getByLabel(/password/i).fill(PASS);
  await page.getByRole('button', { name: /sign in|log in/i }).click();
  await page.getByRole('button', { name: 'Start with an empty system' }).click();
  await expect(page.getByRole('button', { name: 'HR', exact: true })).toBeVisible();
  await page.screenshot({ path: testInfo.outputPath('00-dashboard.png'), fullPage: true });

  await walk(page, testInfo, '01', 'HR', 'Workers', 'New Worker', 'Admin', 'Users & Accounts', 'New Account', 'Worker profile', { w: EN_WORKER, a: EN_ACCOUNT });

  await page.getByRole('button', { name: 'Cancel' }).click();
  await page.getByRole('link', { name: 'Settings', exact: true }).last().click();
  await page.getByRole('link', { name: /System Settings/ }).click();
  await expect(page.getByRole('heading', { name: 'Language' })).toBeVisible();
  await page.getByLabel('Select language').click();
  await page.screenshot({ path: testInfo.outputPath('02-language-dropdown.png'), fullPage: true });
  await page.getByRole('option', { name: 'Polski' }).click();
  await page.getByRole('button', { name: 'Save', exact: true }).click();
  await expect(page.getByRole('button', { name: 'Kadry', exact: true })).toBeVisible({ timeout: 30000 });
  await page.waitForTimeout(800);
  await page.screenshot({ path: testInfo.outputPath('03-polish-ui.png'), fullPage: true });

  await walk(page, testInfo, '04', 'Kadry', 'Pracownicy', 'Nowy pracownik', 'Administracja', 'Użytkownicy i konta', 'Nowe konto', 'Profil pracownika', { w: PL_WORKER, a: PL_ACCOUNT });
});
