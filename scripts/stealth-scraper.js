const puppeteer = require('puppeteer-extra');
const StealthPlugin = require('puppeteer-extra-plugin-stealth');
puppeteer.use(StealthPlugin());

const url = process.argv[2];
if (!url) {
    console.error("Please provide a URL");
    process.exit(1);
}

(async () => {
    let browser;
    try {
        browser = await puppeteer.launch({
            headless: "new",
            args: [
                '--no-sandbox',
                '--disable-setuid-sandbox',
                '--disable-blink-features=AutomationControlled',
                '--disable-infobars',
                '--window-size=1920,1080',
            ]
        });
        const page = await browser.newPage();
        
        await page.setViewport({ width: 1920, height: 1080 });
        await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
        
        await page.goto(url, { waitUntil: 'networkidle2', timeout: 45000 });
        
        // Try to wait for products
        try {
            await page.waitForSelector('.v2-listing-card', { timeout: 10000 });
        } catch (e) {
            // Might be a captcha or no results, we just ignore and return HTML
        }

        const html = await page.content();
        console.log(html);
        
    } catch (e) {
        console.error("Puppeteer Script Error: " + e.message);
        process.exit(1);
    } finally {
        if (browser) {
            await browser.close();
        }
    }
})();
