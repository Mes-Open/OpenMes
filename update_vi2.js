const fs = require('fs');
let code = fs.readFileSync('tests/e2e/car-production-buildout-vi.spec.ts', 'utf8');

// Remove the old /settings step I inserted previously
code = code.replace(/await test\.step\('switch language to vietnamese'[\s\S]*?\}\);\s*/, '');

// Redefine login function to click the language dropdown on the login page BEFORE logging in
const newLogin = `async function login(page: Page, user: string, pass: string) {
  await page.goto('/login');
  
  // Wait for the language dropdown to be visible
  const langDropdown = page.locator('button[aria-haspopup="listbox"], button[aria-label="Language"]').first();
  await expect(langDropdown).toBeVisible({ timeout: 5000 });
  
  // Choose Tiếng Việt
  await pickDropdown(langDropdown, /Tiếng Việt/i);
  await page.waitForTimeout(1500); // Give it time to reload window.location.href
  
  await page.fill('input[name="username"]', user);
  await page.fill('input[name="password"]', pass);
  await Promise.all([page.waitForURL((u) => !u.pathname.startsWith('/login')), page.click('button[type="submit"]')]);
}`;

code = code.replace(/async function login\(page: Page, user: string, pass: string\) \{[\s\S]*?^\}/m, newLogin);

fs.writeFileSync('tests/e2e/car-production-buildout-vi.spec.ts', code);
console.log('VI Test Updated to switch language on /login page.');
