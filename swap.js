const fs = require('fs');
const files = [
  'tests/e2e/car-production-buildout-vi.spec.ts',
  'tests/e2e/car-production-buildout-en.spec.ts'
];

for (const file of files) {
  let content = fs.readFileSync(file, 'utf8');

  if (!content.includes('switchToOperator')) {
    content = content.replace('test.use({', `let adminCookies: any[] = [];
let opCookies: any[] = [];

async function switchToOperator(page: import('@playwright/test').Page) {
  adminCookies = await page.context().cookies();
  await page.context().clearCookies();
  if (opCookies.length > 0) {
    await page.context().addCookies(opCookies);
  }
}

async function switchToAdmin(page: import('@playwright/test').Page) {
  opCookies = await page.context().cookies();
  await page.context().clearCookies();
  if (adminCookies.length > 0) {
    await page.context().addCookies(adminCookies);
  }
}

test.use({`);
  }

  // Replace operator login context creation
  content = content.replace(
    /const opCtx = await browser\.newContext\(\{ ignoreHTTPSErrors: true \}\);\n\s*const op = await opCtx\.newPage\(\);\n\s*await addClickHighlighter\(op\);\n\s*await login\(op, OP_USER, OP_PASS\);/g,
    "await switchToOperator(page);\n  await login(page, OP_USER, OP_PASS);"
  );

  // Replace `op.` with `page.`
  content = content.replace(/\bop\./g, 'page.');
  content = content.replace(/\(op\)/g, '(page)');
  content = content.replace(/\(op,/g, '(page,');

  // Replace `opCtx.close();` with nothing
  content = content.replace(/await opCtx\.close\(\);\n/g, '');

  // Add switchToAdmin before QC step
  content = content.replace(
    /await test\.step\('perform quality control linked to the pallet', async \(\) => \{/g,
    "await switchToAdmin(page);\n  await test.step('perform quality control linked to the pallet', async () => {"
  );

  fs.writeFileSync(file, content);
  console.log('Processed', file);
}

// Now also process the main file because it already has the functions but needs `opCtx` replaced
let mainContent = fs.readFileSync('tests/e2e/car-production-buildout.spec.ts', 'utf8');
mainContent = mainContent.replace(
  /const opCtx = await browser\.newContext\(\{ ignoreHTTPSErrors: true \}\);\n\s*const op = await opCtx\.newPage\(\);\n\s*await addClickHighlighter\(op\);\n\s*await login\(op, OP_USER, OP_PASS\);/g,
  "await switchToOperator(page);\n  await login(page, OP_USER, OP_PASS);"
);
mainContent = mainContent.replace(/\bop\./g, 'page.');
mainContent = mainContent.replace(/\(op\)/g, '(page)');
mainContent = mainContent.replace(/\(op,/g, '(page,');
mainContent = mainContent.replace(/await opCtx\.close\(\);\n/g, '');
mainContent = mainContent.replace(
  /await test\.step\('perform quality control linked to the pallet', async \(\) => \{/g,
  "await switchToAdmin(page);\n  await test.step('perform quality control linked to the pallet', async () => {"
);
fs.writeFileSync('tests/e2e/car-production-buildout.spec.ts', mainContent);
console.log('Processed main file');
