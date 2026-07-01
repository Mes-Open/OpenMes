const { chromium } = require('playwright');
(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage();
  await page.goto('http://localhost:8080/login');
  await page.fill('input[name="username"]', 'admin');
  await page.fill('input[name="password"]', 'Admin1234!');
  await page.click('button[type="submit"]');
  await page.waitForURL('**/admin**');
  await page.goto('http://localhost:8080/admin/material-lots/create');
  await page.waitForSelector('text=Select date');
  const html = await page.$eval('div:has-text("RECEIVED")', el => el.innerHTML);
  console.log(html);
  await browser.close();
})();
