const puppeteer = require('puppeteer-extra');
const StealthPlugin = require('puppeteer-extra-plugin-stealth');
puppeteer.use(StealthPlugin());
const dns = require('dns').promises;

const url = process.argv[2];
if (!url) {
    console.error("Please provide a URL");
    process.exit(1);
}

function isPrivateIp(ip) {
    ip = ip.trim();
    if (/^(?:[0-9]{1,3}\.){3}[0-9]{1,3}$/.test(ip)) {
        const parts = ip.split('.').map(Number);
        if (parts.some(p => p < 0 || p > 255)) return true;
        if (parts[0] === 127) return true;
        if (parts[0] === 10) return true;
        if (parts[0] === 172 && parts[1] >= 16 && parts[1] <= 31) return true;
        if (parts[0] === 192 && parts[1] === 168) return true;
        if (parts[0] === 169 && parts[1] === 254) return true;
        if (parts[0] === 0) return true;
        return false;
    }
    if (ip.includes(':')) {
        const lowerIp = ip.toLowerCase();
        if (lowerIp === '::1' || lowerIp === '0:0:0:0:0:0:0:1') return true;
        if (lowerIp === '::' || lowerIp === '0:0:0:0:0:0:0:0') return true;
        if (lowerIp.startsWith('fe80:') || lowerIp.startsWith('fe8') || lowerIp.startsWith('fe9') || lowerIp.startsWith('fea') || lowerIp.startsWith('feb')) return true;
        if (lowerIp.startsWith('fc') || lowerIp.startsWith('fd')) return true;
        if (lowerIp.startsWith('::ffff:')) {
            const mapped = ip.slice(7);
            if (mapped.includes('.')) {
                return isPrivateIp(mapped);
            }
        }
        return false;
    }
    return false;
}

async function isSafeUrl(urlString) {
    try {
        const parsed = new URL(urlString);
        if (parsed.protocol !== 'http:' && parsed.protocol !== 'https:') {
            return false;
        }
        const hostname = parsed.hostname;
        const lowerHost = hostname.toLowerCase();
        if (lowerHost === 'localhost' || lowerHost.endsWith('.local') || lowerHost.endsWith('.internal')) {
            return false;
        }
        const cleanHost = hostname.startsWith('[') && hostname.endsWith(']') 
            ? hostname.slice(1, -1) 
            : hostname;
            
        if (isPrivateIp(cleanHost)) {
            return false;
        }
        try {
            const lookupResult = await dns.lookup(cleanHost, { all: true });
            for (const item of lookupResult) {
                if (isPrivateIp(item.address)) {
                    return false;
                }
            }
        } catch (e) {
            // DNS lookup failed
        }
        return true;
    } catch (err) {
        return false;
    }
}

(async () => {
    // 1. Pre-flight check
    if (!await isSafeUrl(url)) {
        console.error("SSRF Prevention: The target URL is not allowed (blocked private/local resource or invalid protocol).");
        process.exit(1);
    }

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
        
        // 2. Intercept and block local/private IP requests (covers redirects and subresources)
        await page.setRequestInterception(true);
        page.on('request', async (request) => {
            const targetUrl = request.url();
            const safe = await isSafeUrl(targetUrl);
            if (!safe) {
                await request.abort('blockedbyclient');
            } else {
                await request.continue();
            }
        });

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
