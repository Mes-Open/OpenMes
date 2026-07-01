const { chromium } = require('playwright');

(async () => {
    const browser = await chromium.launch();
    const page = await browser.newPage();
    
    page.on('console', msg => {
        if (msg.type() === 'error') {
            console.log(`PAGE ERROR: "${msg.text()}"`);
        }
    });
    
    page.on('pageerror', err => {
        console.log(`PAGE EXCEPTION: ${err.message}`);
    });
    
    await page.goto('http://localhost:8080/login');
    await page.waitForTimeout(2000);
    await browser.close();
})();
