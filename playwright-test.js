const { chromium } = require('playwright');
(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage();
  await page.setContent(`
    <button aria-label="2026-07-01">1</button>
  `);
  const btn = page.getByRole('button', { name: '2026-07-01', exact: true });
  console.log('Button found?', await btn.count());
  await browser.close();
})();
