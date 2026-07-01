const fs = require('fs');
let code = fs.readFileSync('tests/e2e/car-production-buildout-vi.spec.ts', 'utf8');

// Change locale to en-US
code = code.replace(/locale:\s*'vi-VN'/, "locale: 'en-US'");

// Insert language switch step
const languageSwitchStep = `
  await test.step('switch language to vietnamese', async () => {
    await page.goto('/settings');
    await stepSubtitle(page, 'Đổi ngôn ngữ sang Tiếng Việt (Tiếng Việt)');
    
    // Pick Tiếng Việt from the first dropdown (Language)
    const dropdown = page.locator('button[aria-haspopup="listbox"]').first();
    await dropdown.click();
    await page.getByRole('option', { name: /Tiếng Việt/i }).first().click();
    
    // Save
    await page.getByRole('button', { name: /Save|Lưu/i }).first().click();
    await page.waitForTimeout(1500);
  });
`;

code = code.replace(
  /await login\(page, ADMIN, PASS\);/,
  `await login(page, ADMIN, PASS);\n\n` + languageSwitchStep
);

fs.writeFileSync('tests/e2e/car-production-buildout-vi.spec.ts', code);
console.log('VI Test Updated.');
