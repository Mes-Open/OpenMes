import { test, expect } from '@playwright/test';
import { readFileSync } from 'fs';
import { resolve } from 'path';

const envPath = resolve(__dirname, '../../.env');
const envContent = readFileSync(envPath, 'utf-8');
const adminPassMatch = envContent.match(/^ADMIN_PASSWORD=(.*)$/m);
const ADMIN_PASS = adminPassMatch ? adminPassMatch[1].trim() : 'MFRz9GZBkM9UEYTfsaHPLG1P';
const ADMIN_USER = 'admin';

const OP_USER = 'testop99';
const OP_PASS = 'Operator1234!';

async function pickFormSelect(page, index, optionName) {
  await page.locator('form button[aria-haspopup="listbox"]').nth(index).click();
  await page.getByRole('option', { name: optionName }).first().click();
}

test('create user test', async ({ page }) => {
  await page.goto('/login');
  await page.fill('input[name="username"]', ADMIN_USER);
  await page.fill('input[name="password"]', ADMIN_PASS);
  await Promise.all([page.waitForURL((u) => !u.pathname.startsWith('/login')), page.click('button[type="submit"]')]);
  console.log('Login successful');

  await page.goto('/admin/users/create');
  await page.locator('input[type="text"]').nth(0).fill('Test Operator 99');
  await page.locator('input[type="text"]').nth(1).fill(OP_USER);
  await page.locator('input[type="email"]').fill(`${OP_USER}@example.test`);
  await page.locator('input[type="password"]').nth(0).fill(OP_PASS);
  await page.locator('input[type="password"]').nth(1).fill(OP_PASS);
  await pickFormSelect(page, 0, /Operator/i);
  
  await page.locator('form').getByRole('button', { name: /(Create|Save|Lưu|Tạo)/i }).first().click();
  
  await page.waitForTimeout(2000);
  console.log('CURRENT_URL:', page.url());
  const err = await page.locator('.text-om-blocked, .bg-om-blocked-bg, .text-om-error, .text-red-500, .text-red-600').allTextContents();
  console.log('ERRORS:', err);
});
