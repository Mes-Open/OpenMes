const fs = require('fs');
let code = fs.readFileSync('tests/e2e/car-production-buildout-en.spec.ts', 'utf8');

// 1. For login
code = code.replace(
  /await stepSubtitle\(page, 'Login as Administrator'\);\s+await login\(page, ADMIN, PASS\);/,
  `await login(page, ADMIN, PASS);\n  await stepSubtitle(page, 'Login as Administrator');`
);

// 2. For standard test.steps
code = code.replace(
  /await stepSubtitle\((page|op), '(.*?)'\);\s+await test\.step\('(.*?)', async \(\) => \{\s+await (page|op)\.goto\('(.*?)'\);\s+await (page|op)\.waitForTimeout\(NAV_DELAY\);/g,
  `await test.step('$3', async () => {\n    await $4.goto('$5');\n    await stepSubtitle($1, '$2');`
);

// 3. For the material lots and materials (loops inside)
code = code.replace(
  /await stepSubtitle\(page, 'Register Raw Materials \\(6 Components\\)'\);\s+await test\.step\('create materials', async \(\) => \{\s+for \(const mat of bomDefs\) \{\s+await page\.goto\('\/admin\/materials\/create'\);/g,
  `await test.step('create materials', async () => {\n    for (const mat of bomDefs) {\n      await page.goto('/admin/materials/create');\n      if (mat === bomDefs[0]) await stepSubtitle(page, 'Register Raw Materials (6 Components)');`
);

code = code.replace(
  /await stepSubtitle\(page, 'Create Initial Material Lots \\(Inventory Setup\\)'\);\s+await test\.step\('create material lots for stock', async \(\) => \{\s+for \(const mat of bomDefs\) \{\s+await page\.goto\('\/admin\/material-lots\/create'\);/g,
  `await test.step('create material lots for stock', async () => {\n    for (const mat of bomDefs) {\n      await page.goto('/admin/material-lots/create');\n      if (mat === bomDefs[0]) await stepSubtitle(page, 'Create Initial Material Lots (Inventory Setup)');`
);

// 4. For operator queue
code = code.replace(
  /await stepSubtitle\(op, 'Operator Picks Work Order & Starts a Batch'\);\s+await test\.step\('operator starts a batch step', async \(\) => \{\s+await op\.goto\('\/operator\/queue'\);\s+await op\.waitForTimeout\(NAV_DELAY\);/g,
  `await test.step('operator starts a batch step', async () => {\n    await op.goto('/operator/queue');\n    await stepSubtitle(op, 'Operator Picks Work Order & Starts a Batch');`
);

code = code.replace(
  /await stepSubtitle\(op, 'Pack a Car onto a Pallet & Scan Barcode'\);\s+await test\.step\('package a car onto a pallet', async \(\) => \{\s+console\.log\('Going to packaging station\.\.\.'\);\s+await op\.goto\('\/packaging\/station'\);\s+await op\.waitForTimeout\(NAV_DELAY\);/g,
  `await test.step('package a car onto a pallet', async () => {\n    console.log('Going to packaging station...');\n    await op.goto('/packaging/station');\n    await stepSubtitle(op, 'Pack a Car onto a Pallet & Scan Barcode');`
);

// 5. Dashboard overviews
code = code.replace(
  /await stepSubtitle\(page, 'Dashboard Overview'\);\s+await page\.goto\('\/admin\/dashboard'\);\s+await page\.waitForTimeout\(NAV_DELAY\);/g,
  `await page.goto('/admin/dashboard');\n  await stepSubtitle(page, 'Dashboard Overview');`
);

code = code.replace(
  /await stepSubtitle\(page, 'Work Orders Progress Overview'\);\s+await page\.goto\('\/admin\/work-orders'\);\s+await page\.waitForTimeout\(NAV_DELAY\);/g,
  `await page.goto('/admin/work-orders');\n  await stepSubtitle(page, 'Work Orders Progress Overview');`
);

// Fix remaining occurrences of stepSubtitle before test.step where goto doesn't have NAV_DELAY
code = code.replace(
  /await stepSubtitle\((page|op), '(.*?)'\);\s+await test\.step\('(.*?)', async \(\) => \{\s+await (page|op)\.goto\('(.*?)'\);/g,
  `await test.step('$3', async () => {\n    await $4.goto('$5');\n    await stepSubtitle($1, '$2');`
);

fs.writeFileSync('tests/e2e/car-production-buildout-en.spec.ts', code);
console.log('Done replacement');
