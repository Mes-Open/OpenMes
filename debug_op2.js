const { chromium } = require('@playwright/test');


(async () => {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext();
  const page = await context.newPage();

  console.log('Logging in...');
  await page.goto('http://localhost:8080/login');
  await page.fill('input[name="username"]', 'admin');
  await page.fill('input[name="password"]', 'Admin1234!');
  await page.click('button[type="submit"]');
  await page.waitForURL('http://localhost:8080/admin/dashboard');

  console.log('Going to create user...');
  await page.goto('http://localhost:8080/admin/users/create');
  
  await page.locator('input[type="text"]').nth(0).fill('Test Operator 99');
  await page.locator('input[type="text"]').nth(1).fill('testop99');
  await page.locator('input[type="email"]').fill('testop99@example.com');
  await page.locator('input[type="password"]').nth(0).fill('Operator1234!');
  await page.locator('input[type="password"]').nth(1).fill('Operator1234!');
  
  await page.locator('form button[aria-haspopup="listbox"]').nth(0).click();
  await page.getByRole('option', { name: /Operator/i }).first().click();

  await page.locator('form').getByRole('button', { name: /(Create|Save|Lưu|Tạo)/i }).first().click();
  
  console.log('Wait 1s...');
  await page.waitForTimeout(1000);
  
  console.log('CURRENT URL:', page.url());
  const err = await page.locator('.text-om-blocked, .bg-om-blocked-bg').allTextContents();
  console.log('ERRORS:', err);

  const html = await page.locator('form').nth(1).innerHTML();
  console.log('FORM_HTML length:', html.length);
  const isValid = await page.evaluate(() => document.querySelectorAll('form')[1].checkValidity());
  console.log('IS VALID:', isValid);

  await browser.close();
})();
