const fs = require('fs');
const path = require('path');
const https = require('https');

// DEFINIERE DEINE ZIELE (Suchbegriffe für die Iconify API)
const TARGETS = [
    { category: 'Floral', query: 'flower' },
    { category: 'Love', query: 'heart' },
    { category: 'Feiertage', query: 'christmas' },
    { category: 'Feiertage', query: 'easter' },
    { category: 'Tiere', query: 'animal' },
    { category: 'Symbole', query: 'symbol' },
    { category: 'Hochzeit', query: 'wedding' }
];

const MAX_PER_CATEGORY = 30;
const BASE_DIR = path.join(__dirname, '../public/shop/product/configurator/vectors');

// Helper Funktion für HTTPS GET Requests
function fetchJson(url) {
    return new Promise((resolve, reject) => {
        https.get(url, { headers: { 'User-Agent': 'NodeJS Downloader' } }, (res) => {
            let body = '';
            res.on('data', chunk => body += chunk);
            res.on('end', () => resolve(JSON.parse(body)));
        }).on('error', reject);
    });
}

function downloadFile(url, dest) {
    return new Promise((resolve, reject) => {
        const file = fs.createWriteStream(dest);
        https.get(url, (response) => {
            response.pipe(file);
            file.on('finish', () => file.close(resolve));
        }).on('error', (err) => {
            fs.unlink(dest, () => {});
            reject(err);
        });
    });
}

(async () => {
    console.log('====== Iconify Mass SVG Downloader ======');
    console.log('Lade ohne Browser und ohne Puppeteer direkt native Vektoren herunter...\n');

    for (const target of TARGETS) {
        const targetDir = path.join(BASE_DIR, target.category);
        if (!fs.existsSync(targetDir)) {
            fs.mkdirSync(targetDir, { recursive: true });
        }

        console.log(`-> Suche nach "${target.query}" für Kategorie: ${target.category}`);

        try {
            // Iconify Search API
            const searchUrl = `https://api.iconify.design/search?query=${target.query}&limit=${MAX_PER_CATEGORY}`;
            const data = await fetchJson(searchUrl);

            if (!data.icons || data.icons.length === 0) {
                console.log('   Keine Icons gefunden.');
                continue;
            }

            console.log(`   Gefunden: ${data.icons.length} Icons. Lade herunter...`);

            let count = 1;
            for (const iconId of data.icons) {
                try {
                    // Download API: https://api.iconify.design/PREFIX/NAME.svg
                    const downloadUrl = `https://api.iconify.design/${iconId}.svg`;
                    const safeName = iconId.replace(':', '-');
                    let destPath = path.join(targetDir, `${safeName}.svg`);

                    if (fs.existsSync(destPath)) {
                        destPath = path.join(targetDir, `${safeName}-${count}.svg`);
                    }

                    await downloadFile(downloadUrl, destPath);
                    console.log(`   [OK] Gespeichert: ${target.category}/${path.basename(destPath)}`);
                    count++;

                    // Millisekunden Pause um die API nicht zu spammen
                    await new Promise(r => setTimeout(r, 100));
                } catch (err) {
                    console.log(`   [ERR] Fehler bei Datei: ${iconId}`);
                }
            }
        } catch (err) {
             console.log(`   [ERR] Fehler bei der Suche nach ${target.query}:`, err.message);
        }
        console.log('');
    }

    console.log('====== FERTIG! ======');
})();
