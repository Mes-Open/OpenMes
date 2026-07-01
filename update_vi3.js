const fs = require('fs');
let code = fs.readFileSync('tests/e2e/car-production-buildout-vi.spec.ts', 'utf8');

const newLogin = `async function login(page: Page, user: string, pass: string) {
  await page.goto('/login');
  
  await stepSubtitle(page, 'Đổi ngôn ngữ sang Tiếng Việt ngay tại màn hình đăng nhập');
  
  // Wait for the language dropdown to be visible
  const langDropdown = page.locator('button[aria-haspopup="listbox"]').first();
  await expect(langDropdown).toBeVisible({ timeout: 5000 });
  
  // Choose Tiếng Việt
  await langDropdown.click();
  await page.getByRole('option', { name: /Tiếng Việt/i }).first().click();
  
  // Wait for reload window.location.href
  await page.waitForTimeout(2000); 
  
  await page.fill('input[name="username"]', user);
  await page.fill('input[name="password"]', pass);
  await Promise.all([page.waitForURL((u) => !u.pathname.startsWith('/login')), page.click('button[type="submit"]')]);
}`;

code = code.replace(/async function login\(page: Page, user: string, pass: string\) \{[\s\S]*?^\}/m, newLogin);

fs.writeFileSync('tests/e2e/car-production-buildout-vi.spec.ts', code);
console.log('VI Test Updated with subtitle on login page.');
