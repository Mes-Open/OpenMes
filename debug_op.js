const { test, expect, chromium } = require('@playwright/test');
(async () => {
  const browser = await chromium.launch({ headless: false });
  const page = await browser.newPage(); page.on('pageerror', e => console.error('PAGE_ERROR:', e)); page.on('console', m => console.log('CONSOLE:', m.text()));
  
  await page.goto('http://localhost:8080/login');
  await page.fill('input[name="username"]', 'admin');
  await page.fill('input[name="password"]', 'Admin1234!');
  await Promise.all([page.waitForURL((u) => !u.pathname.startsWith('/login')), page.getByRole('button', { name: /(Sign in|Log in|Dang nh?p)/i }).click()]);

  await page.goto('http://localhost:8080/admin/users/create');
  
  const OP_USER = `op_${Date.now()}`;
  const OP_PASS = 'Op1234!';
  
  await page.locator('input[type="text"]').nth(0).fill(`Car Operator`);
  await page.locator('input[type="text"]').nth(1).fill(OP_USER);
  await page.locator('input[type="email"]').fill(`${OP_USER}@example.test`);
  await page.locator('input[type="password"]').nth(0).fill(OP_PASS);
  await page.locator('input[type="password"]').nth(1).fill(OP_PASS);
  
  // Pick role
  await page.locator('form button[aria-haspopup="listbox"]').nth(0).click();
  await page.getByRole('option', { name: /Operator|Vận hành/i }).first().click();
  
  await page.screenshot({ path: 'before-submit.png', fullPage: true }); const html = await page.locator('form').first().innerHTML(); console.log('FORM_HTML:', html); const [res] = await Promise.all([
    page.waitForResponse(r => r.url().includes('/admin/users') && r.request().method() === 'POST').catch(() => null),
    page.locator('form').getByRole('button', { name: /(Create|Save|Lưu|Tạo)/i }).first().click()
  ]);
  
  if (res) {
    console.log('POST status:', res.status());
    const text = await res.text();
    console.log('Response body:', text);
  } else {
    console.log('No POST request sent! Form might be invalid.');
    console.log('Valid:', await page.evaluate(() => document.querySelector('form').checkValidity()));
  }
  
  await browser.close();
})();
