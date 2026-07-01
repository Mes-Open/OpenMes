const { chromium } = require('playwright');

(async () => {
    const browser = await chromium.launch();
    const page = await browser.newPage();
    
    page.on('console', msg => {
        console.log(`[CONSOLE ${msg.type()}] ${msg.text()}`);
    });
    
    page.on('pageerror', err => {
        console.log(`[PAGE EXCEPTION] ${err.message}`);
    });
    
    page.on('requestfailed', request => {
        console.log(`[REQUEST FAILED] ${request.url()} - ${request.failure().errorText}`);
    });

    page.on('response', response => {
        if (!response.ok()) {
            console.log(`[RESPONSE ERROR] ${response.status()} ${response.url()}`);
        }
    });

    await page.goto('http://localhost:8080/login');
    await page.waitForTimeout(3000);
    
    const content = await page.content();
    console.log('[HTML CONTENT LENGTH]', content.length);
    if (content.length < 1000) {
        console.log('[HTML CONTENT]', content);
    } else {
        console.log('[HTML CONTENT PREVIEW]', content.substring(0, 500));
        console.log('[HTML CONTENT END]', content.substring(content.length - 500));
    }

    await browser.close();
})();
