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
  
  try {
    await page.waitForURL('http://localhost:8080/admin/dashboard', { timeout: 10000 });
    console.log('Login successful');
  } catch (e) {
    console.log('Login failed!');
    await page.screenshot({ path: 'login_fail.png' });
    const content = await page.content();
    require('fs').writeFileSync('login_fail.html', content);
  }

  await browser.close();
})();
