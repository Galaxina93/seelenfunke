const { chromium } = require('playwright');

const url = process.argv[2];
if (!url) {
    console.error("Please provide a URL");
    process.exit(1);
}

(async () => {
    let browser;
    try {
        browser = await chromium.launch({
            headless: true,
            args: [
                '--disable-blink-features=AutomationControlled',
                '--no-sandbox',
                '--disable-setuid-sandbox',
                '--disable-dev-shm-usage',
                '--disable-accelerated-2d-canvas',
                '--no-first-run',
                '--no-zygote',
                '--disable-gpu'
            ]
        });

        const context = await browser.newContext({
            viewport: { width: 1920, height: 1080 },
            userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            locale: 'de-DE',
            timezoneId: 'Europe/Berlin',
            permissions: ['geolocation'],
            colorScheme: 'dark',
            javaScriptEnabled: true
        });

        const page = await context.newPage();
        
        // Hide webdriver
        await page.addInitScript(() => {
            Object.defineProperty(navigator, 'webdriver', {
                get: () => undefined
            });
        });

        await page.goto(url, { waitUntil: 'load', timeout: 45000 });
        
        try {
            await page.waitForSelector('.v2-listing-card', { timeout: 15000 });
        } catch (e) {
            // Might be anti-bot page, return what we have
        }

        const html = await page.content();
        console.log(html);

    } catch (e) {
        console.error("Playwright Error: " + e.message);
    } finally {
        if (browser) {
            await browser.close();
        }
    }
})();
