const fs = require('fs');
const path = require('path');
const https = require('https');

const assets = [
  { url: 'https://cdn.jsdelivr.net/npm/chart.js', dest: 'chartjs/chart.umd.js' },
  { url: 'https://cdn.jsdelivr.net/npm/apexcharts/dist/apexcharts.min.js', dest: 'apexcharts/apexcharts.min.js' },
  { url: 'https://cdn.jsdelivr.net/npm/marked/marked.min.js', dest: 'marked/marked.min.js' },
  { url: 'https://cdnjs.cloudflare.com/ajax/libs/dompurify/3.0.6/purify.min.js', dest: 'dompurify/purify.min.js' },
  { url: 'https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js', dest: 'highlight/highlight.min.js' },
  { url: 'https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github-dark.min.css', dest: 'highlight/github-dark.min.css' },
  { url: 'https://cdnjs.cloudflare.com/ajax/libs/animejs/3.2.1/anime.min.js', dest: 'animejs/anime.min.js' },
  { url: 'https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js', dest: 'html2canvas/html2canvas.min.js' },
  { url: 'https://cdn.jsdelivr.net/npm/mobile-drag-drop@2.3.0-rc.2/default.min.css', dest: 'mobile-drag-drop/default.min.css' },
  { url: 'https://cdn.jsdelivr.net/npm/mobile-drag-drop@2.3.0-rc.2/index.min.js', dest: 'mobile-drag-drop/index.min.js' },
  { url: 'https://api.mapbox.com/mapbox-gl-js/v3.2.0/mapbox-gl.css', dest: 'mapbox-gl/mapbox-gl.css' },
  { url: 'https://api.mapbox.com/mapbox-gl-js/v3.2.0/mapbox-gl.js', dest: 'mapbox-gl/mapbox-gl.js' },
  { url: 'https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js', dest: 'sortablejs/Sortable.min.js' },
  { url: 'https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js', dest: 'jszip/jszip.min.js' },
  { url: 'https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js', dest: 'three/three.min.js' },
  { url: 'https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/controls/OrbitControls.js', dest: 'three/OrbitControls.js' },
  { url: 'https://cdn.jsdelivr.net/npm/emoji-picker-element@1/index.js', dest: 'emoji-picker-element/index.js' },
  { url: 'https://cdn.jsdelivr.net/npm/emoji-picker-element@1/picker.js', dest: 'emoji-picker-element/picker.js' },
  { url: 'https://cdn.jsdelivr.net/npm/emoji-picker-element@1/database.js', dest: 'emoji-picker-element/database.js' }
];

const vendorBaseDir = path.join(__dirname, '../public/vendor');

function download(url, destRelPath) {
  const destPath = path.join(vendorBaseDir, destRelPath);
  return new Promise((resolve, reject) => {
    const dir = path.dirname(destPath);
    fs.mkdirSync(dir, { recursive: true });
    
    https.get(url, (res) => {
      if (res.statusCode === 301 || res.statusCode === 302) {
        // Follow redirect
        download(res.headers.location, destRelPath).then(resolve).catch(reject);
        return;
      }
      
      if (res.statusCode !== 200) {
        reject(new Error(`Failed to download ${url}: Status code ${res.statusCode}`));
        return;
      }
      
      const file = fs.createWriteStream(destPath);
      res.pipe(file);
      file.on('finish', () => {
        file.close();
        console.log(`✓ Downloaded: ${url} -> ${destRelPath}`);
        resolve();
      });
    }).on('error', (err) => {
      fs.unlink(destPath, () => {});
      reject(err);
    });
  });
}

async function run() {
  console.log('Starting download of vendor assets...');
  for (const asset of assets) {
    try {
      await download(asset.url, asset.dest);
    } catch (err) {
      console.error(`✗ Error downloading ${asset.url}:`, err.message);
    }
  }
  console.log('All downloads completed!');
}

run();
