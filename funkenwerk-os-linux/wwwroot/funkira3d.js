import * as THREE from './vendor/three.module.min.js';
import { RoundedBoxGeometry } from './vendor/RoundedBoxGeometry.js';

// ============================================================================
// FUNKIRA 3D ATELIER & FULL-BODY CHIBI RIG ENGINE (LUXURY 60-FPS EDITION)
// Tuned for Intel GeminiLake UHD Graphics 605 @ 2560x1440 QHD:
// - Zero dynamic lights & zero PBR GGX loops (Custom Studio MatCaps + Baked AO Lightmaps)
// - Smooth bevelled RoundedBoxGeometry + sculpted LatheGeometry (rich specular roll-off)
// - Framed 12-Agent Easter-Egg Group Portrait ('DAS GRÜNDER-TEAM · MEIN SEELENFUNKE')
// - Static world matrix freezing (0.00 ms room matrix overhead per frame)
// - Pre-baked zero-GC, zero-clip Visor sprites + dirty-checked 30 Hz texture upload
// - Unified single-RAF frame loop + automatic 60-FPS QHD resolution governor
// ============================================================================

const EMOTION_PRESETS = {
    neutral:  { lidTop: 0.03, lidBot: 0.0,  browTilt: 0.0,   browY: 0.0,  mouthCurve: 0.25,  mouthOpen: 0.04, pupilScale: 1.0,  blush: 0.36, tear: 0, headRoll: 0.0,   headPitch: 0.0  },
    happy:    { lidTop: 0.06, lidBot: 0.18, browTilt: -0.10, browY: -2.8, mouthCurve: 0.65,  mouthOpen: 0.24, pupilScale: 1.06, blush: 0.65, tear: 0, headRoll: 0.05,  headPitch: -0.03 },
    laughing: { lidTop: 0.76, lidBot: 0.42, browTilt: -0.16, browY: -4.2, mouthCurve: 0.92,  mouthOpen: 0.66, pupilScale: 1.08, blush: 0.82, tear: 0, headRoll: -0.07, headPitch: -0.06 },
    sweet:    { lidTop: 0.10, lidBot: 0.26, browTilt: -0.22, browY: -2.4, mouthCurve: 0.56,  mouthOpen: 0.12, pupilScale: 1.15, blush: 0.96, tear: 0, headRoll: 0.11,  headPitch: 0.03  },
    sad:      { lidTop: 0.34, lidBot: 0.05, browTilt: -0.38, browY: 2.0,  mouthCurve: -0.58, mouthOpen: 0.10, pupilScale: 0.94, blush: 0.22, tear: 1, headRoll: -0.08, headPitch: 0.12  },
    angry:    { lidTop: 0.30, lidBot: 0.14, browTilt: 0.44,  browY: 4.0,  mouthCurve: -0.45, mouthOpen: 0.16, pupilScale: 0.86, blush: 0.48, tear: 0, headRoll: 0.03,  headPitch: 0.06  },
    proud:    { lidTop: 0.14, lidBot: 0.22, browTilt: 0.12,  browY: -2.0, mouthCurve: 0.60,  mouthOpen: 0.16, pupilScale: 1.03, blush: 0.54, tear: 0, headRoll: -0.05, headPitch: -0.07 },
    curious:  { lidTop: 0.0,  lidBot: 0.0,  browTilt: -0.16, browY: -4.2, mouthCurve: 0.18,  mouthOpen: 0.22, pupilScale: 1.12, blush: 0.42, tear: 0, headRoll: 0.13,  headPitch: 0.02  },
    sleepy:   { lidTop: 0.66, lidBot: 0.10, browTilt: -0.14, browY: 1.6,  mouthCurve: 0.10,  mouthOpen: 0.18, pupilScale: 0.92, blush: 0.35, tear: 0, headRoll: 0.09,  headPitch: 0.14  },
    excited:  { lidTop: 0.0,  lidBot: 0.14, browTilt: -0.18, browY: -5.0, mouthCurve: 0.86,  mouthOpen: 0.54, pupilScale: 1.18, blush: 0.86, tear: 0, headRoll: -0.06, headPitch: -0.05 }
};

function lerp(a, b, t) { return a + (b - a) * t; }
function clamp(v, lo, hi) { return Math.max(lo, Math.min(hi, v)); }

function hexToRgb(hex) {
    const n = typeof hex === 'number' ? hex : parseInt(String(hex).replace('#', ''), 16);
    return [(n >> 16) & 255, (n >> 8) & 255, n & 255];
}

function configureFastTexture(tex) {
    tex.generateMipmaps = false;
    tex.minFilter = THREE.LinearFilter;
    tex.magFilter = THREE.LinearFilter;
    tex.colorSpace = THREE.SRGBColorSpace;
    return tex;
}

// Procedural 128x128 Dual-Light Studio MatCap Generator (Zero per-pixel light math on GPU!)
function makeStudioMatcapTexture(baseHex, specHex, rimHex, specPower = 26, rimStrength = 0.38) {
    const size = 128;
    const c = document.createElement('canvas');
    c.width = size;
    c.height = size;
    const ctx = c.getContext('2d');
    const img = ctx.createImageData(size, size);
    const d = img.data;

    const base = hexToRgb(baseHex);
    const spec = hexToRgb(specHex);
    const rim = hexToRgb(rimHex);

    // Warm sunlit window key (top-left-front) + warm studio fill (top-right)
    const lx1 = -0.34, ly1 = 0.60, lz1 = 0.72;
    const hx1 = -0.18, hy1 = 0.34, hz1 = 0.923;
    const lx2 = 0.48,  ly2 = 0.35, lz2 = 0.80;

    const half = size * 0.5;
    for (let y = 0; y < size; y++) {
        const ny = (half - y - 0.5) / half;
        for (let x = 0; x < size; x++) {
            const nx = (x + 0.5 - half) / half;
            const r2 = nx * nx + ny * ny;
            let r, g, b;
            if (r2 <= 1.0) {
                const nz = Math.sqrt(Math.max(0, 1.0 - r2));
                const ndl1 = Math.max(0, nx * lx1 + ny * ly1 + nz * lz1);
                const ndl2 = Math.max(0, nx * lx2 + ny * ly2 + nz * lz2);
                const hemi = 0.36 + 0.28 * (ny * 0.5 + 0.5) + 0.46 * ndl1 + 0.20 * ndl2;
                const ndh = Math.max(0, nx * hx1 + ny * hy1 + nz * hz1);
                const sp = Math.pow(ndh, specPower) * 0.88;
                const fresnel = Math.pow(1.0 - nz, 2.5) * rimStrength;

                r = base[0] * hemi + spec[0] * sp + rim[0] * fresnel;
                g = base[1] * hemi + spec[1] * sp + rim[1] * fresnel;
                b = base[2] * hemi + spec[2] * sp + rim[2] * fresnel;
            } else {
                const len = Math.sqrt(r2);
                const uy = ny / len;
                const hemi = 0.36 + 0.24 * (uy * 0.5 + 0.5);
                r = base[0] * hemi + rim[0] * rimStrength * 0.9;
                g = base[1] * hemi + rim[1] * rimStrength * 0.9;
                b = base[2] * hemi + rim[2] * rimStrength * 0.9;
            }
            const p = (y * size + x) * 4;
            d[p]     = clamp(Math.round(r), 0, 255);
            d[p + 1] = clamp(Math.round(g), 0, 255);
            d[p + 2] = clamp(Math.round(b), 0, 255);
            d[p + 3] = 255;
        }
    }
    ctx.putImageData(img, 0, 0);
    return configureFastTexture(new THREE.CanvasTexture(c));
}

function createRoundedRectShape(w, h, r) {
    const s = new THREE.Shape();
    const hw = w * 0.5;
    const hh = h * 0.5;
    const rad = Math.min(r, hw * 0.9, hh * 0.9);
    s.moveTo(-hw + rad, -hh);
    s.lineTo(hw - rad, -hh);
    s.quadraticCurveTo(hw, -hh, hw, -hh + rad);
    s.lineTo(hw, hh - rad);
    s.quadraticCurveTo(hw, hh, hw - rad, hh);
    s.lineTo(-hw + rad, hh);
    s.quadraticCurveTo(-hw, hh, -hw, hh - rad);
    s.lineTo(-hw, -hh + rad);
    s.quadraticCurveTo(-hw, -hh, -hw + rad, -hh);
    return s;
}

function createArchedWindowShape(w, h) {
    const s = new THREE.Shape();
    const hw = w * 0.5;
    const rectH = h - hw;
    const bottomY = -h * 0.5;
    s.moveTo(-hw, bottomY);
    s.lineTo(hw, bottomY);
    s.lineTo(hw, bottomY + rectH);
    s.absarc(0, bottomY + rectH, hw, 0, Math.PI, false);
    s.lineTo(-hw, bottomY);
    return s;
}

// Herringbone Walnut Parquet + Pre-Baked Furniture Ambient-Occlusion Shadows (0 GPU shadow cost!)
function makeBakedFloorTexture() {
    const S = 512;
    const c = document.createElement('canvas');
    c.width = S;
    c.height = S;
    const ctx = c.getContext('2d');
    ctx.fillStyle = '#221710';
    ctx.fillRect(0, 0, S, S);

    // Rich dark walnut plank parquet with subtle warm sheen
    const rows = 26;
    const rowH = S / rows;
    for (let r = 0; r < rows; r++) {
        const y = r * rowH;
        const offset = (r % 3) * 32;
        for (let col = -1; col < 10; col++) {
            const x = col * 64 + offset;
            const v = ((r * 11 + col * 17) % 15) - 7;
            const cr = 54 + v;
            const cg = 36 + Math.round(v * 0.65);
            const cb = 24 + Math.round(v * 0.45);
            ctx.fillStyle = `rgb(${cr}, ${cg}, ${cb})`;
            ctx.fillRect(x + 0.8, y + 0.8, 62.4, rowH - 1.3);

            // Subtle wood grain highlight line
            ctx.fillStyle = 'rgba(220, 175, 115, 0.045)';
            ctx.fillRect(x + 4, y + rowH * 0.35, 54, 1.2);
        }
    }

    // Helper mapping world (wx, wz) on 10.5 x 8.5 floor centered at (0, 0) -> canvas UV
    const worldToCanvas = (wx, wz) => [
        ((wx + 5.25) / 10.5) * S,
        ((wz + 4.25) / 8.5) * S
    ];
    const paintAO = (wx, wz, rx, rz, alpha = 0.55) => {
        const [cx, cy] = worldToCanvas(wx, wz);
        const prx = (rx / 10.5) * S;
        const pry = (rz / 8.5) * S;
        ctx.save();
        ctx.translate(cx, cy);
        ctx.scale(1, pry / Math.max(1, prx));
        const g = ctx.createRadialGradient(0, 0, prx * 0.15, 0, 0, prx);
        g.addColorStop(0, `rgba(6, 4, 8, ${alpha})`);
        g.addColorStop(0.65, `rgba(8, 5, 10, ${alpha * 0.45})`);
        g.addColorStop(1, 'rgba(0, 0, 0, 0)');
        ctx.fillStyle = g;
        ctx.beginPath();
        ctx.arc(0, 0, prx, 0, Math.PI * 2);
        ctx.fill();
        ctx.restore();
    };

    // Soft baked Ambient Occlusion under static Atelier furniture
    paintAO(1.82, -1.85, 1.35, 0.68, 0.62);  // Manufaktur Workbench
    paintAO(-2.22, -1.82, 0.78, 0.62, 0.62); // xTool F2 Ultra UV Station
    paintAO(-0.88, -2.20, 0.56, 0.44, 0.56); // Luxury Vitrine Pedestal
    paintAO(2.95, -0.88, 0.42, 0.42, 0.52);  // Boutique Plant
    paintAO(-3.15, -1.62, 0.62, 0.62, 0.48); // Seasonal Tree Corner

    // Warm Golden Sunlit Floor Pool (from left arched window + center chandelier)
    const sunPool = ctx.createRadialGradient(210, 245, 18, 248, 256, 265);
    sunPool.addColorStop(0, 'rgba(255, 222, 158, 0.24)');
    sunPool.addColorStop(0.45, 'rgba(197, 160, 89, 0.08)');
    sunPool.addColorStop(1, 'rgba(9, 7, 13, 0.72)');
    ctx.fillStyle = sunPool;
    ctx.fillRect(0, 0, S, S);

    return configureFastTexture(new THREE.CanvasTexture(c));
}

// Obsidian & 24K Gold Paneled Boutique Wainscoting Wall Texture
function makeBakedWallTexture() {
    const S = 512;
    const c = document.createElement('canvas');
    c.width = S;
    c.height = S;
    const ctx = c.getContext('2d');

    // Upper matte obsidian-anthracite wall
    const vg = ctx.createLinearGradient(0, 0, 0, S);
    vg.addColorStop(0, '#111017');
    vg.addColorStop(0.68, '#1b1823');
    vg.addColorStop(0.70, '#2a1d14');
    vg.addColorStop(1, '#1e140e');
    ctx.fillStyle = vg;
    ctx.fillRect(0, 0, S, S);

    // Subtle upper gold gallery frame panels
    ctx.strokeStyle = 'rgba(197, 160, 89, 0.28)';
    ctx.lineWidth = 2;
    const panelCount = 5;
    const pw = S / panelCount;
    for (let i = 0; i < panelCount; i++) {
        ctx.strokeRect(i * pw + 12, 26, pw - 24, S * 0.60);
        // Lower walnut wainscoting panels
        ctx.fillStyle = 'rgba(66, 44, 28, 0.42)';
        ctx.fillRect(i * pw + 10, S * 0.73, pw - 20, S * 0.22);
        ctx.strokeStyle = 'rgba(197, 160, 89, 0.34)';
        ctx.strokeRect(i * pw + 12, S * 0.74, pw - 24, S * 0.20);
        ctx.strokeStyle = 'rgba(197, 160, 89, 0.28)';
    }

    // Dado Gold Molding Bar at y = 70%
    const dado = ctx.createLinearGradient(0, S * 0.69, 0, S * 0.715);
    dado.addColorStop(0, '#8f6d32');
    dado.addColorStop(0.5, '#ebd08e');
    dado.addColorStop(1, '#6e5222');
    ctx.fillStyle = dado;
    ctx.fillRect(0, S * 0.695, S, 7);

    // Warm Sconce & Gallery Lamp Halo in Center-Right + Soft Studio Vignette
    const glow = ctx.createRadialGradient(S * 0.53, S * 0.38, 18, S * 0.50, S * 0.44, S * 0.56);
    glow.addColorStop(0, 'rgba(255, 214, 138, 0.20)');
    glow.addColorStop(0.55, 'rgba(197, 160, 89, 0.06)');
    glow.addColorStop(1, 'rgba(8, 7, 12, 0.52)');
    ctx.fillStyle = glow;
    ctx.fillRect(0, 0, S, S);

    return configureFastTexture(new THREE.CanvasTexture(c));
}

// Ornate Schwarz-Gold Boutique Medallion Carpet Texture
function makeBakedRugTexture() {
    const S = 512;
    const c = document.createElement('canvas');
    c.width = S;
    c.height = S;
    const ctx = c.getContext('2d');
    ctx.clearRect(0, 0, S, S);

    const cx = S * 0.5, cy = S * 0.5;
    // Soft floor drop shadow under carpet edge
    const sh = ctx.createRadialGradient(cx, cy, S * 0.42, cx, cy, S * 0.495);
    sh.addColorStop(0, 'rgba(0,0,0,0.5)');
    sh.addColorStop(1, 'rgba(0,0,0,0)');
    ctx.fillStyle = sh;
    ctx.beginPath();
    ctx.arc(cx, cy, S * 0.495, 0, Math.PI * 2);
    ctx.fill();

    // Velvet obsidian-plum weave + brushed 24K gold borders
    const rg = ctx.createRadialGradient(cx, cy, 16, cx, cy, S * 0.465);
    rg.addColorStop(0, '#241e2c');
    rg.addColorStop(0.68, '#18141f');
    rg.addColorStop(0.86, '#14111a');
    rg.addColorStop(0.89, '#9f7c3a');
    rg.addColorStop(0.93, '#f2d48f');
    rg.addColorStop(0.96, '#b58e46');
    rg.addColorStop(0.99, '#6e5322');
    rg.addColorStop(1, 'rgba(0,0,0,0)');
    ctx.fillStyle = rg;
    ctx.beginPath();
    ctx.arc(cx, cy, S * 0.465, 0, Math.PI * 2);
    ctx.fill();

    // Inner filigree ring & 8-point Seelenfunke compass star
    ctx.strokeStyle = 'rgba(235, 202, 130, 0.42)';
    ctx.lineWidth = 2.5;
    ctx.beginPath();
    ctx.arc(cx, cy, S * 0.37, 0, Math.PI * 2);
    ctx.stroke();

    ctx.strokeStyle = 'rgba(197, 160, 89, 0.26)';
    ctx.lineWidth = 2;
    ctx.beginPath();
    for (let i = 0; i < 16; i++) {
        const a = (i / 16) * Math.PI * 2;
        const rad = (i % 2 === 0) ? S * 0.16 : S * 0.065;
        const px = cx + Math.cos(a) * rad;
        const py = cy + Math.sin(a) * rad;
        if (i === 0) ctx.moveTo(px, py); else ctx.lineTo(px, py);
    }
    ctx.closePath();
    ctx.stroke();

    return configureFastTexture(new THREE.CanvasTexture(c));
}

// Scenic Seasonal Gifhorn Window Canvas (Updated only when season/night changes!)
function paintScenicWindowCanvas(canvas, season, isLateNight) {
    const W = canvas.width, H = canvas.height;
    const ctx = canvas.getContext('2d');
    const g = ctx.createLinearGradient(0, 0, 0, H);

    if (isLateNight) {
        g.addColorStop(0, '#0b1022');
        g.addColorStop(0.6, '#16213e');
        g.addColorStop(1, '#232946');
    } else if (season === 'winter') {
        g.addColorStop(0, '#2a4060');
        g.addColorStop(0.65, '#6d8cb0');
        g.addColorStop(1, '#dce8f5');
    } else if (season === 'spring') {
        g.addColorStop(0, '#2d5a88');
        g.addColorStop(0.65, '#7cb2d6');
        g.addColorStop(1, '#f7d9c4');
    } else if (season === 'summer') {
        g.addColorStop(0, '#24588a');
        g.addColorStop(0.68, '#6bb5e0');
        g.addColorStop(1, '#fceabb');
    } else {
        // Warm Golden Gifhorn Autumn Sunset
        g.addColorStop(0, '#2c223e');
        g.addColorStop(0.48, '#7a4557');
        g.addColorStop(0.82, '#d98a4e');
        g.addColorStop(1, '#ffdca2');
    }
    ctx.fillStyle = g;
    ctx.fillRect(0, 0, W, H);

    if (isLateNight) {
        // Crisp stars & golden crescent moon
        ctx.fillStyle = 'rgba(255, 245, 210, 0.85)';
        for (let i = 0; i < 28; i++) {
            const sx = ((i * 73) % (W - 20)) + 10;
            const sy = ((i * 41) % (H * 0.65)) + 12;
            ctx.fillRect(sx, sy, (i % 3 === 0) ? 2.5 : 1.5, (i % 3 === 0) ? 2.5 : 1.5);
        }
        ctx.fillStyle = '#ffe9ad';
        ctx.beginPath();
        ctx.arc(W * 0.68, H * 0.28, 22, 0, Math.PI * 2);
        ctx.fill();
        ctx.fillStyle = '#111933';
        ctx.beginPath();
        ctx.arc(W * 0.68 + 8, H * 0.28 - 4, 19, 0, Math.PI * 2);
        ctx.fill();
    } else {
        // Soft sunlit glow
        const sg = ctx.createRadialGradient(W * 0.62, H * 0.66, 8, W * 0.62, H * 0.66, 95);
        sg.addColorStop(0, 'rgba(255, 244, 204, 0.85)');
        sg.addColorStop(1, 'rgba(255, 220, 150, 0)');
        ctx.fillStyle = sg;
        ctx.fillRect(0, 0, W, H);
    }

    // Soft distant treeline / Gifhorn roofs silhouette at bottom
    ctx.fillStyle = isLateNight ? '#090c18' : '#231924';
    ctx.beginPath();
    ctx.moveTo(0, H);
    ctx.lineTo(0, H * 0.83);
    for (let x = 0; x <= W; x += 24) {
        const th = H * 0.83 - Math.abs(Math.sin(x * 0.055)) * 18 - ((x % 48 === 0) ? 10 : 0);
        ctx.lineTo(x, th);
    }
    ctx.lineTo(W, H);
    ctx.closePath();
    ctx.fill();
}

function makeSunbeamTexture() {
    const c = document.createElement('canvas');
    c.width = 128;
    c.height = 256;
    const ctx = c.getContext('2d');
    const g = ctx.createLinearGradient(0, 0, 128, 256);
    g.addColorStop(0, 'rgba(255, 226, 158, 0.20)');
    g.addColorStop(0.55, 'rgba(255, 214, 135, 0.07)');
    g.addColorStop(1, 'rgba(255, 214, 135, 0)');
    ctx.fillStyle = g;
    ctx.fillRect(0, 0, 128, 256);
    return configureFastTexture(new THREE.CanvasTexture(c));
}

function makeWallGlowTexture() {
    const c = document.createElement('canvas');
    c.width = 128;
    c.height = 128;
    const ctx = c.getContext('2d');
    const g = ctx.createRadialGradient(64, 64, 4, 64, 64, 62);
    g.addColorStop(0, 'rgba(255, 224, 150, 0.46)');
    g.addColorStop(0.5, 'rgba(218, 172, 90, 0.14)');
    g.addColorStop(1, 'rgba(0, 0, 0, 0)');
    ctx.fillStyle = g;
    ctx.fillRect(0, 0, 128, 128);
    return configureFastTexture(new THREE.CanvasTexture(c));
}

function makeChestEmblemTexture() {
    const c = document.createElement('canvas');
    c.width = 384;
    c.height = 192;
    const ctx = c.getContext('2d');
    ctx.clearRect(0, 0, 384, 192);

    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillStyle = '#ffd5e2';
    ctx.font = '900 50px "Orbitron", "Segoe UI", sans-serif';
    ctx.fillText('FUNKIRA', 192, 76);

    ctx.fillStyle = '#f29ab4';
    ctx.font = '700 26px Georgia, serif';
    ctx.fillText('Mein-Seelenfunke', 192, 126);

    return configureFastTexture(new THREE.CanvasTexture(c));
}

function makeBossBadgeTexture() {
    const c = document.createElement('canvas');
    c.width = 192;
    c.height = 192;
    const ctx = c.getContext('2d');
    ctx.clearRect(0, 0, 192, 192);

    ctx.beginPath();
    ctx.moveTo(27, 21);
    ctx.lineTo(165, 21);
    ctx.lineTo(159, 114);
    ctx.quadraticCurveTo(154, 154, 96, 177);
    ctx.quadraticCurveTo(38, 154, 33, 114);
    ctx.closePath();
    ctx.fillStyle = '#171419';
    ctx.fill();
    ctx.lineWidth = 7.5;
    ctx.strokeStyle = '#e7a1b4';
    ctx.stroke();

    ctx.textAlign = 'center';
    ctx.fillStyle = '#fff5f8';
    ctx.font = '700 26px sans-serif';
    ctx.fillText('★', 96, 57);
    ctx.font = '900 34px "Orbitron", sans-serif';
    ctx.fillText('BOSS', 96, 98);

    ctx.strokeStyle = '#e7a1b4';
    ctx.lineWidth = 6;
    ctx.beginPath();
    ctx.moveTo(58, 122);
    ctx.lineTo(96, 143);
    ctx.lineTo(134, 122);
    ctx.stroke();

    return configureFastTexture(new THREE.CanvasTexture(c));
}

function makeWallCrestTexture(textTop, textSub) {
    const c = document.createElement('canvas');
    c.width = 512;
    c.height = 128;
    const ctx = c.getContext('2d');
    ctx.fillStyle = '#141118';
    ctx.fillRect(0, 0, 512, 128);
    ctx.strokeStyle = '#C5A059';
    ctx.lineWidth = 4;
    ctx.strokeRect(6, 6, 500, 116);

    ctx.textAlign = 'center';
    ctx.fillStyle = '#F2D28B';
    ctx.font = '800 27px Georgia, serif';
    ctx.fillText(textTop, 256, 55);
    ctx.fillStyle = '#C5A059';
    ctx.font = '700 14px "Segoe UI", sans-serif';
    ctx.fillText(textSub, 256, 87);

    return configureFastTexture(new THREE.CanvasTexture(c));
}

function makeShadowDiscTexture() {
    const c = document.createElement('canvas');
    c.width = 96;
    c.height = 96;
    const ctx = c.getContext('2d');
    const g = ctx.createRadialGradient(48, 48, 5, 48, 48, 46);
    g.addColorStop(0, 'rgba(0,0,0,0.62)');
    g.addColorStop(0.65, 'rgba(0,0,0,0.24)');
    g.addColorStop(1, 'rgba(0,0,0,0)');
    ctx.fillStyle = g;
    ctx.fillRect(0, 0, 96, 96);
    return configureFastTexture(new THREE.CanvasTexture(c));
}

function freezeStaticTree(rootObj) {
    rootObj.updateMatrixWorld(true);
    rootObj.traverse((node) => {
        node.matrixAutoUpdate = false;
    });
}

class FunkiraAtelierEngine {
    constructor() {
        this.canvas = null;
        this.renderer = null;
        this.scene = null;
        this.camera = null;
        this.running = false;
        this.lastMs = 0;

        // Dynamic QHD Render Scale Governor (Targets Locked 60 FPS on Intel UHD 605)
        this.renderScale = 0.60; // 1536x864 on 2560x1440 QHD
        this.fps = 60;
        this._fpsFrames = 0;
        this._fpsWindowStartMs = 0;

        // State synced from app.js
        this.mode = 'front_active'; // 'front_active' | 'atelier_autonomous'
        this.emotion = 'happy';
        this.gesture = 'wave';
        this.gestureUntilMs = 0;
        this.lookTarget = { x: 0, y: 0 };
        this.lookCur = { x: 0, y: 0 };
        this.personDetected = false;
        this.voiceEnergy = 0;
        this.isSpeaking = false;

        this.worldState = {
            shopLevel: 2,
            season: 'autumn',
            occasion: 'none',
            rosesCount: 0,
            isLateNight: false
        };

        // Pre-baked Zero-GC Visor Canvas (384 x 240) & Offscreen Eye/Blush Sprites
        this.visorCanvas = document.createElement('canvas');
        this.visorCanvas.width = 384;
        this.visorCanvas.height = 240;
        this.visorCtx = this.visorCanvas.getContext('2d', { alpha: false });
        this.visorTexture = null;
        this.visorBgCanvas = null;
        this.irisSpriteCanvas = null;
        this.blushSpriteCanvas = null;
        this._lastVisorDrawMs = 0;
        this._lastDrawnSig = '';

        // Scenic Window Canvas
        this.windowCanvas = document.createElement('canvas');
        this.windowCanvas.width = 256;
        this.windowCanvas.height = 256;
        this.windowTexture = null;
        this.sunbeamMesh = null;

        this.faceCur = { ...EMOTION_PRESETS.happy };
        this.blinkVal = 0;
        this.nextBlinkMs = performance.now() + 2400;

        // Shared Materials & Rig references
        this.mats = {};
        this.rig = {};
        this.outfits = {};
        this.levelGroups = {};
        this.occasionGroups = {};
        this.roseMeshes = [];
        this.fallingPetals = [];
        this.weatherParticles = [];
        this.sparkParticles = [];

        // Locomotion & Autonomous Atelier AI
        this.pos = new THREE.Vector3(0, 0, 1.52);
        this.targetPos = new THREE.Vector3(0, 0, 1.52);
        this.facingYaw = 0;
        this.walkPhase = 0;
        this.moveSpeed = 1.65;
        this.currentTask = 'front';
        this.nextTaskSwitchMs = 0;
        this.taskIndex = 0;
    }

    init(canvasEl) {
        if (!canvasEl) return false;
        if (this.renderer && this.canvas === canvasEl) return true;
        this.canvas = canvasEl;

        this.renderer = new THREE.WebGLRenderer({
            canvas: canvasEl,
            antialias: false,
            alpha: false,
            stencil: false,
            depth: true,
            powerPreference: 'high-performance'
        });
        this.renderer.outputColorSpace = THREE.SRGBColorSpace;
        this.renderer.toneMapping = THREE.NoToneMapping;
        this.renderer.shadowMap.enabled = false;

        this.scene = new THREE.Scene();
        this.scene.background = new THREE.Color(0x121017);

        this.camera = new THREE.PerspectiveCamera(40, 16 / 9, 0.15, 18);
        this.camera.position.set(0, 1.38, 3.66);
        this.camera.lookAt(0, 1.02, 0.2);

        this._bakeVisorSprites();
        this._initSharedMatcaps();
        this._buildAtelierRoom();
        this._buildFunkiraRig();
        this._applyWorldStateVisuals();
        this._renderVisorFace(performance.now(), 0.016, true);
        this.resize();

        window.addEventListener('resize', () => this.resize());
        return true;
    }

    _bakeVisorSprites() {
        const bg = document.createElement('canvas');
        bg.width = 384;
        bg.height = 240;
        const bctx = bg.getContext('2d', { alpha: false });
        bctx.fillStyle = '#151218';
        bctx.fillRect(0, 0, 384, 240);

        const topGrad = bctx.createLinearGradient(0, 0, 384, 0);
        topGrad.addColorStop(0, '#c46f84');
        topGrad.addColorStop(0.5, '#f5b2c4');
        topGrad.addColorStop(1, '#b86277');
        bctx.fillStyle = topGrad;
        bctx.fillRect(8, 9, 368, 8);

        bctx.beginPath();
        bctx.moveTo(0, 18);
        bctx.lineTo(188, 18);
        bctx.lineTo(118, 240);
        bctx.lineTo(0, 240);
        bctx.closePath();
        const sheen = bctx.createLinearGradient(0, 18, 160, 240);
        sheen.addColorStop(0, 'rgba(255, 228, 238, 0.10)');
        sheen.addColorStop(1, 'rgba(255, 228, 238, 0.015)');
        bctx.fillStyle = sheen;
        bctx.fill();

        bctx.strokeStyle = '#0b090d';
        bctx.lineWidth = 10;
        bctx.strokeRect(3, 3, 378, 234);
        this.visorBgCanvas = bg;

        const ic = document.createElement('canvas');
        ic.width = 96;
        ic.height = 96;
        const ictx = ic.getContext('2d');
        const ig = ictx.createRadialGradient(48, 58, 6, 48, 48, 42);
        ig.addColorStop(0, '#ff8fb1');
        ig.addColorStop(0.35, '#c93863');
        ig.addColorStop(0.72, '#69142c');
        ig.addColorStop(1, '#260711');
        ictx.fillStyle = ig;
        ictx.beginPath();
        ictx.ellipse(48, 48, 39, 41, 0, 0, Math.PI * 2);
        ictx.fill();

        ictx.fillStyle = '#1f050d';
        ictx.beginPath();
        ictx.ellipse(48, 46, 20, 22, 0, 0, Math.PI * 2);
        ictx.fill();

        ictx.strokeStyle = 'rgba(255, 168, 195, 0.68)';
        ictx.lineWidth = 3.5;
        ictx.beginPath();
        ictx.arc(48, 51, 26, 0.22 * Math.PI, 0.78 * Math.PI);
        ictx.stroke();

        ictx.fillStyle = '#ffffff';
        ictx.beginPath();
        ictx.ellipse(36, 34, 10.4, 12.0, -0.22, 0, Math.PI * 2);
        ictx.fill();

        ictx.fillStyle = 'rgba(255, 240, 246, 0.92)';
        ictx.beginPath();
        ictx.arc(61, 59, 4.8, 0, Math.PI * 2);
        ictx.fill();
        this.irisSpriteCanvas = ic;

        const bc = document.createElement('canvas');
        bc.width = 64;
        bc.height = 40;
        const blctx = bc.getContext('2d');
        const bgGrad = blctx.createRadialGradient(32, 20, 3, 32, 20, 26);
        bgGrad.addColorStop(0, 'rgba(235, 84, 122, 0.44)');
        bgGrad.addColorStop(1, 'rgba(235, 84, 122, 0)');
        blctx.fillStyle = bgGrad;
        blctx.fillRect(0, 0, 64, 40);

        blctx.strokeStyle = 'rgba(235, 95, 130, 0.85)';
        blctx.lineWidth = 2.4;
        for (let s = -2; s <= 2; s++) {
            blctx.beginPath();
            blctx.moveTo(32 + s * 7 - 3, 25);
            blctx.lineTo(32 + s * 7 + 3, 15);
            blctx.stroke();
        }
        this.blushSpriteCanvas = bc;
    }

    _initSharedMatcaps() {
        this.mats.roseGold   = new THREE.MeshMatcapMaterial({ matcap: makeStudioMatcapTexture('#e2869d', '#fff8fb', '#ffbdd0', 28, 0.44) });
        this.mats.darkArmor  = new THREE.MeshMatcapMaterial({ matcap: makeStudioMatcapTexture('#1d1721', '#8e6c7c', '#e490a6', 22, 0.35) });
        this.mats.jointDark  = new THREE.MeshMatcapMaterial({ matcap: makeStudioMatcapTexture('#110d14', '#4e3e49', '#7a5c6b', 16, 0.20) });
        this.mats.gold24k    = new THREE.MeshMatcapMaterial({ matcap: makeStudioMatcapTexture('#c89f4d', '#fffbe6', '#f7d88b', 32, 0.48) });
        this.mats.woodDark   = new THREE.MeshMatcapMaterial({ matcap: makeStudioMatcapTexture('#452f20', '#876449', '#6b4c35', 14, 0.16) });
        this.mats.obsidian   = new THREE.MeshMatcapMaterial({ matcap: makeStudioMatcapTexture('#16141c', '#544e63', '#C5A059', 18, 0.18) });
        this.mats.silverSteel = new THREE.MeshMatcapMaterial({ matcap: makeStudioMatcapTexture('#7a808c', '#f5f7fa', '#c5a059', 26, 0.28) });
        this.mats.leafGreen  = new THREE.MeshMatcapMaterial({ matcap: makeStudioMatcapTexture('#28593c', '#6ba882', '#92cfa8', 12, 0.20) });
        this.mats.scarfRed   = new THREE.MeshMatcapMaterial({ matcap: makeStudioMatcapTexture('#b52648', '#ed6888', '#ffa1b6', 10, 0.22) });
        this.mats.snowWhite  = new THREE.MeshMatcapMaterial({ matcap: makeStudioMatcapTexture('#ede7e6', '#ffffff', '#ffdce6', 9, 0.16) });
        this.mats.roseBloom  = new THREE.MeshMatcapMaterial({ matcap: makeStudioMatcapTexture('#cf234e', '#ff7a9c', '#ffb5c8', 18, 0.30) });
        this.mats.broomStraw = new THREE.MeshMatcapMaterial({ matcap: makeStudioMatcapTexture('#cca460', '#f7dfad', '#e6c27a', 8, 0.14) });
        this.mats.broomWood  = new THREE.MeshMatcapMaterial({ matcap: makeStudioMatcapTexture('#875d34', '#bd8c5e', '#9e7044', 10, 0.12) });
        this.mats.warmBulb   = new THREE.MeshBasicMaterial({ color: 0xFFE9B5 });
        this.mats.petalRose  = new THREE.MeshBasicMaterial({ color: 0xD92B5A, side: THREE.DoubleSide, transparent: true, opacity: 0.92 });
        this.mats.petalGold  = new THREE.MeshBasicMaterial({ color: 0xE6C275, side: THREE.DoubleSide, transparent: true, opacity: 0.92 });
        this.mats.petalGeo   = new THREE.PlaneGeometry(0.065, 0.085);
    }

    resize() {
        if (!this.renderer || !this.canvas || !this.camera) return;
        const rect = this.canvas.parentElement
            ? this.canvas.parentElement.getBoundingClientRect()
            : { width: window.innerWidth, height: window.innerHeight };
        const cssW = Math.max(320, Math.floor(rect.width || window.innerWidth));
        const cssH = Math.max(240, Math.floor(rect.height || window.innerHeight));

        const baseScale = cssW > 1920 ? this.renderScale : Math.min(1.0, this.renderScale * 1.25);
        this.renderer.setPixelRatio(baseScale);
        this.renderer.setSize(cssW, cssH, false);
        this.camera.aspect = cssW / cssH;
        this.camera.updateProjectionMatrix();
    }

    start() {
        if (!this.renderer || this.running) return;
        this.running = true;
        this.resize();
        const now = performance.now();
        this.lastMs = now;
        this._fpsFrames = 0;
        this._fpsWindowStartMs = now;
    }

    stop() {
        this.running = false;
    }

    setMode(mode) {
        const prev = this.mode;
        this.mode = mode === 'atelier_autonomous' ? 'atelier_autonomous' : 'front_active';
        if (this.mode === 'front_active') {
            this.targetPos.set(0, 0, 1.52);
            this.moveSpeed = prev === 'atelier_autonomous' ? 3.55 : 2.4;
            this.currentTask = 'front';
            if (this.rig.broom) this.rig.broom.visible = false;
            if (this.rig.cloth) this.rig.cloth.visible = false;
        } else {
            this.moveSpeed = 1.48;
            this._pickNextAutonomousTask(performance.now(), true);
        }
    }

    runToCamera() {
        this.setMode('front_active');
        this.setExpression('excited', 'wave', 5500);
    }

    setExpression(emotion, gesture, durationMs = 6000) {
        if (emotion && EMOTION_PRESETS[emotion]) {
            this.emotion = emotion;
        }
        if (gesture) {
            this.gesture = gesture;
            this.gestureUntilMs = durationMs > 0 ? (performance.now() + durationMs) : 0;
        }
    }

    setLookTarget(nx, ny, detected = true) {
        this.lookTarget.x = clamp(nx || 0, -1, 1);
        this.lookTarget.y = clamp(ny || 0, -1, 1);
        this.personDetected = !!detected;
    }

    setVoiceState(energy, speaking) {
        this.voiceEnergy = clamp(energy || 0, 0, 1);
        this.isSpeaking = !!speaking;
    }

    setWorldState(ws = {}) {
        let changed = false;
        if (typeof ws.shopLevel === 'number' && ws.shopLevel >= 1) {
            const lvl = clamp(Math.round(ws.shopLevel), 1, 5);
            if (lvl !== this.worldState.shopLevel) { this.worldState.shopLevel = lvl; changed = true; }
        }
        if (ws.season && ws.season !== this.worldState.season) {
            this.worldState.season = ws.season;
            changed = true;
        }
        if (ws.occasion && ws.occasion !== this.worldState.occasion) {
            this.worldState.occasion = ws.occasion;
            changed = true;
        }
        if (typeof ws.rosesCount === 'number' && ws.rosesCount !== this.worldState.rosesCount) {
            this.worldState.rosesCount = Math.max(0, ws.rosesCount);
            this._syncVaseRoses(this.worldState.rosesCount);
        }
        const hr = new Date().getHours();
        const lateNow = (hr >= 22 || hr < 7);
        if (lateNow !== this.worldState.isLateNight) {
            this.worldState.isLateNight = lateNow;
            changed = true;
        }
        if (changed) this._applyWorldStateVisuals();
    }

    triggerTikTokGift(count = 1) {
        const add = clamp(count || 1, 1, 20);
        this.worldState.rosesCount = (this.worldState.rosesCount || 0) + add;
        this._syncVaseRoses(this.worldState.rosesCount);
        this.setExpression('sweet', 'cheer', 6500);

        const spawnCount = Math.min(24, 12 + add * 4);
        for (let i = 0; i < spawnCount; i++) {
            const m = new THREE.Mesh(this.mats.petalGeo, (i % 4 === 0) ? this.mats.petalGold : this.mats.petalRose);
            m.position.set(
                this.pos.x + (Math.random() - 0.5) * 2.1,
                2.35 + Math.random() * 1.0,
                this.pos.z + (Math.random() - 0.5) * 1.1
            );
            m.rotation.set(Math.random() * 3, Math.random() * 3, Math.random() * 3);
            this.scene.add(m);
            this.fallingPetals.push({
                mesh: m,
                vy: 0.58 + Math.random() * 0.42,
                vx: (Math.random() - 0.5) * 0.38,
                spin: (Math.random() - 0.5) * 3.8,
                life: 3.4 + Math.random() * 1.1
            });
        }
    }

    // ========================================================================
    // STATIC LUXURY MANUFAKTUR ATELIER (ZERO LIGHT SHADERS + FROZEN MATRICES)
    // ========================================================================
    _buildAtelierRoom() {
        const goldMat = this.mats.gold24k;
        const woodDarkMat = this.mats.woodDark;
        const obsidianMat = this.mats.obsidian;
        const wallBasicMat = new THREE.MeshBasicMaterial({ map: makeBakedWallTexture() });
        const floorBasicMat = new THREE.MeshBasicMaterial({ map: makeBakedFloorTexture() });
        const glowWallTex = makeWallGlowTexture();

        const roomStatic = new THREE.Group();

        // 1. Herringbone Walnut Floor with Baked Furniture AO
        const floor = new THREE.Mesh(new THREE.PlaneGeometry(10.5, 8.5), floorBasicMat);
        floor.rotation.x = -Math.PI / 2;
        roomStatic.add(floor);

        // 2. Paneled Obsidian & Gold Wainscoting Studio Walls + Crown Cornice
        const backWall = new THREE.Mesh(new THREE.PlaneGeometry(10.5, 5.0), wallBasicMat);
        backWall.position.set(0, 2.5, -2.65);
        roomStatic.add(backWall);

        const leftWall = new THREE.Mesh(new THREE.PlaneGeometry(7.6, 5.0), wallBasicMat);
        leftWall.position.set(-4.15, 2.5, 0.5);
        leftWall.rotation.y = Math.PI / 2;
        roomStatic.add(leftWall);

        const rightWall = new THREE.Mesh(new THREE.PlaneGeometry(7.6, 5.0), wallBasicMat);
        rightWall.position.set(4.15, 2.5, 0.5);
        rightWall.rotation.y = -Math.PI / 2;
        roomStatic.add(rightWall);

        const baseboardBack = new THREE.Mesh(new RoundedBoxGeometry(10.4, 0.11, 0.06, 2, 0.02), goldMat);
        baseboardBack.position.set(0, 0.055, -2.62);
        const crownBack = new THREE.Mesh(new RoundedBoxGeometry(10.4, 0.14, 0.10, 2, 0.03), woodDarkMat);
        crownBack.position.set(0, 3.62, -2.60);
        const crownGoldStrip = new THREE.Mesh(new RoundedBoxGeometry(10.4, 0.035, 0.11, 1, 0.01), goldMat);
        crownGoldStrip.position.set(0, 3.54, -2.59);
        roomStatic.add(baseboardBack, crownBack, crownGoldStrip);

        // 3. Arched Scenic Gifhorn Window (Back-Left) + Volumetric Sunbeam
        const winGroup = new THREE.Group();
        winGroup.position.set(-2.22, 1.92, -2.61);

        paintScenicWindowCanvas(this.windowCanvas, this.worldState.season, this.worldState.isLateNight);
        this.windowTexture = configureFastTexture(new THREE.CanvasTexture(this.windowCanvas));

        const archPaneGeo = new THREE.ShapeGeometry(createArchedWindowShape(1.56, 1.72), 18);
        // Normalize ShapeGeometry UVs to [0..1]
        archPaneGeo.computeBoundingBox();
        const bb = archPaneGeo.boundingBox;
        const posAttr = archPaneGeo.attributes.position;
        const uvAttr = archPaneGeo.attributes.uv;
        for (let i = 0; i < posAttr.count; i++) {
            const u = (posAttr.getX(i) - bb.min.x) / (bb.max.x - bb.min.x);
            const v = (posAttr.getY(i) - bb.min.y) / (bb.max.y - bb.min.y);
            uvAttr.setXY(i, u, v);
        }
        const skyPane = new THREE.Mesh(archPaneGeo, new THREE.MeshBasicMaterial({ map: this.windowTexture }));
        winGroup.add(skyPane);

        // Sculpted Arched 24K Gold Frame + Mullions + Sill
        const archOuter = createArchedWindowShape(1.72, 1.88);
        const archHole = createArchedWindowShape(1.52, 1.68);
        archOuter.holes.push(archHole);
        const archFrameGeo = new THREE.ExtrudeGeometry(archOuter, {
            depth: 0.055, curveSegments: 16, bevelEnabled: true, bevelThickness: 0.015, bevelSize: 0.012, bevelSegments: 2
        });
        const archFrame = new THREE.Mesh(archFrameGeo, goldMat);
        archFrame.position.z = -0.01;
        const mullV = new THREE.Mesh(new RoundedBoxGeometry(0.042, 1.68, 0.045, 1, 0.01), goldMat);
        mullV.position.z = 0.025;
        const mullH = new THREE.Mesh(new RoundedBoxGeometry(1.54, 0.042, 0.045, 1, 0.01), goldMat);
        mullH.position.set(0, -0.14, 0.025);
        const winSill = new THREE.Mesh(new RoundedBoxGeometry(1.88, 0.075, 0.16, 2, 0.02), woodDarkMat);
        winSill.position.set(0, -0.96, 0.04);
        winGroup.add(archFrame, mullV, mullH, winSill);
        roomStatic.add(winGroup);

        // Delicate Volumetric Golden Sunbeam Plane
        const sunbeam = new THREE.Mesh(
            new THREE.PlaneGeometry(1.75, 3.10),
            new THREE.MeshBasicMaterial({
                map: makeSunbeamTexture(),
                transparent: true,
                depthWrite: false,
                blending: THREE.AdditiveBlending
            })
        );
        sunbeam.position.set(-1.55, 1.48, -1.95);
        sunbeam.rotation.z = 0.42;
        sunbeam.rotation.y = 0.22;
        this.scene.add(sunbeam);
        freezeStaticTree(sunbeam);
        this.sunbeamMesh = sunbeam;

        // 4. EASTER EGG: 24K Gold Museum Picture Frame ('DAS GRÜNDER-TEAM · MEIN SEELENFUNKE')
        // Prominently hung on the main Atelier back wall right behind Funkira!
        const photoGroup = new THREE.Group();
        photoGroup.position.set(0.32, 1.88, -2.59);

        // Soft warm picture-light halo on the wall behind the frame
        const photoWallHalo = new THREE.Mesh(
            new THREE.PlaneGeometry(2.35, 1.55),
            new THREE.MeshBasicMaterial({ map: glowWallTex, transparent: true, depthWrite: false })
        );
        photoWallHalo.position.set(0, 0.06, -0.03);
        photoGroup.add(photoWallHalo);

        // Outer Chamfered 24K Gold Gallery Frame + Velvet Obsidian Passepartout
        const frameOuterMesh = new THREE.Mesh(new RoundedBoxGeometry(1.66, 1.02, 0.065, 2, 0.024), goldMat);
        const frameInnerMatte = new THREE.Mesh(new RoundedBoxGeometry(1.52, 0.88, 0.070, 1, 0.01), obsidianMat);
        photoGroup.add(frameOuterMesh, frameInnerMatte);

        // Load high-res 12-Agent Founders Group Photo asynchronously from local SSD
        const placeholderCanvas = document.createElement('canvas');
        placeholderCanvas.width = 64;
        placeholderCanvas.height = 36;
        const pctx = placeholderCanvas.getContext('2d');
        pctx.fillStyle = '#241811';
        pctx.fillRect(0, 0, 64, 36);
        const portraitMat = new THREE.MeshBasicMaterial({
            map: configureFastTexture(new THREE.CanvasTexture(placeholderCanvas))
        });

        const loader = new THREE.TextureLoader();
        loader.load('./assets/colleagues_group_photo.jpg?v=20261113v3', (loadedTex) => {
            configureFastTexture(loadedTex);
            portraitMat.map = loadedTex;
            portraitMat.needsUpdate = true;
        });

        const portraitPlane = new THREE.Mesh(new THREE.PlaneGeometry(1.46, 0.815), portraitMat);
        portraitPlane.position.z = 0.038;
        photoGroup.add(portraitPlane);

        // Subtle Diagonal Museum Glass Specular Sheen
        const glassSheen = new THREE.Mesh(
            new THREE.PlaneGeometry(1.46, 0.815),
            new THREE.MeshBasicMaterial({ color: 0xfff5dc, transparent: true, opacity: 0.055, depthWrite: false })
        );
        glassSheen.position.z = 0.040;
        photoGroup.add(glassSheen);

        // Brass Museum Picture-Gallery Lamp Mounted Above Frame
        const lampArm = new THREE.Mesh(new CylinderGeometrySafe(0.016, 0.016, 0.16, 8), goldMat);
        lampArm.rotation.x = Math.PI / 2;
        lampArm.position.set(0, 0.56, 0.07);
        const lampHood = new THREE.Mesh(new RoundedBoxGeometry(0.62, 0.048, 0.075, 2, 0.018), goldMat);
        lampHood.position.set(0, 0.56, 0.15);
        const lampGlowStrip = new THREE.Mesh(new THREE.PlaneGeometry(0.56, 0.045), this.mats.warmBulb);
        lampGlowStrip.rotation.x = Math.PI / 2;
        lampGlowStrip.position.set(0, 0.534, 0.15);
        photoGroup.add(lampArm, lampHood, lampGlowStrip);

        roomStatic.add(photoGroup);

        // 5. Flanking Warm Brass Boutique Wall Sconces
        for (const sx of [-0.88, 1.52]) {
            const sconce = new THREE.Group();
            sconce.position.set(sx, 1.94, -2.60);
            const halo = new THREE.Mesh(
                new THREE.PlaneGeometry(0.85, 0.85),
                new THREE.MeshBasicMaterial({ map: glowWallTex, transparent: true, depthWrite: false })
            );
            halo.position.z = -0.02;
            const backplate = new THREE.Mesh(new RoundedBoxGeometry(0.11, 0.24, 0.03, 2, 0.015), goldMat);
            const shade = new THREE.Mesh(new THREE.CylinderGeometry(0.065, 0.085, 0.16, 12), this.mats.warmBulb);
            shade.position.set(0, 0.04, 0.075);
            const stem = new THREE.Mesh(new THREE.CylinderGeometry(0.014, 0.014, 0.14, 8), goldMat);
            stem.position.set(0, -0.06, 0.055);
            sconce.add(halo, backplate, shade, stem);
            roomStatic.add(sconce);
        }

        this.scene.add(roomStatic);
        freezeStaticTree(roomStatic);

        // Shared Weather Particle Material & Low-Count Pool (14 crisp particles outside arched window)
        this.weatherMat = new THREE.MeshBasicMaterial({ color: 0xE6A145, side: THREE.DoubleSide });
        const wpGeo = new THREE.PlaneGeometry(0.042, 0.042);
        for (let i = 0; i < 14; i++) {
            const wp = new THREE.Mesh(wpGeo, this.weatherMat);
            wp.position.set(-2.22 + (Math.random() - 0.5) * 1.38, 1.15 + Math.random() * 1.4, -2.58);
            this.scene.add(wp);
            this.weatherParticles.push({
                mesh: wp,
                speedY: 0.18 + Math.random() * 0.20,
                phase: Math.random() * 6.28
            });
        }

        // ---- LEVEL 1: Bevelled Walnut Manufaktur-Werkbank + 14K Körper-Kasse + Amphora Rosen-Vase ----
        const lvl1 = new THREE.Group();
        const benchTop = new THREE.Mesh(new RoundedBoxGeometry(2.10, 0.095, 0.86, 2, 0.026), woodDarkMat);
        benchTop.position.set(1.82, 0.87, -1.85);
        const benchTrim = new THREE.Mesh(new RoundedBoxGeometry(2.12, 0.028, 0.88, 1, 0.01), goldMat);
        benchTrim.position.set(1.82, 0.845, -1.85);
        const drawerApron = new THREE.Mesh(new RoundedBoxGeometry(1.92, 0.15, 0.74, 2, 0.02), obsidianMat);
        drawerApron.position.set(1.82, 0.74, -1.85);
        const pullL = new THREE.Mesh(new RoundedBoxGeometry(0.16, 0.022, 0.03, 1, 0.008), goldMat);
        pullL.position.set(1.36, 0.74, -1.47);
        const pullR = new THREE.Mesh(new RoundedBoxGeometry(0.16, 0.022, 0.03, 1, 0.008), goldMat);
        pullR.position.set(2.28, 0.74, -1.47);
        lvl1.add(benchTop, benchTrim, drawerApron, pullL, pullR);

        // Tapered Turned 24K Gold Legs
        const legGeo = new THREE.CylinderGeometry(0.042, 0.026, 0.84, 12);
        for (const [lx, lz] of [[0.88, -1.50], [2.76, -1.50], [0.88, -2.20], [2.76, -2.20]]) {
            const leg = new THREE.Mesh(legGeo, goldMat);
            leg.position.set(lx, 0.42, lz);
            lvl1.add(leg);
        }

        // Sculpted 14K Körper-Kasse Savings Jar
        const jarPoints = [
            new THREE.Vector2(0.00, 0.00),
            new THREE.Vector2(0.095, 0.00),
            new THREE.Vector2(0.125, 0.07),
            new THREE.Vector2(0.120, 0.18),
            new THREE.Vector2(0.082, 0.23),
            new THREE.Vector2(0.096, 0.26),
            new THREE.Vector2(0.00, 0.26)
        ];
        const jarMesh = new THREE.Mesh(new THREE.LatheGeometry(jarPoints, 16), goldMat);
        jarMesh.position.set(2.42, 0.915, -1.92);
        lvl1.add(jarMesh);

        // Sculpted Amphora Vase for TikTok Roses
        this.vaseGroup = new THREE.Group();
        this.vaseGroup.position.set(1.18, 0.915, -1.68);
        const vasePoints = [
            new THREE.Vector2(0.00, 0.00),
            new THREE.Vector2(0.075, 0.00),
            new THREE.Vector2(0.112, 0.10),
            new THREE.Vector2(0.068, 0.22),
            new THREE.Vector2(0.092, 0.29)
        ];
        const vaseBody = new THREE.Mesh(new THREE.LatheGeometry(vasePoints, 16), goldMat);
        this.vaseGroup.add(vaseBody);
        lvl1.add(this.vaseGroup);

        this.scene.add(lvl1);
        freezeStaticTree(lvl1);
        this.vaseGroup.matrixAutoUpdate = true;
        this.levelGroups[1] = lvl1;

        // ---- LEVEL 2: Ornate Schwarz-Gold Medallion Rug, Bevelled Westpack Boxes & Boutique Monstera ----
        const lvl2 = new THREE.Group();
        const rug = new THREE.Mesh(
            new THREE.PlaneGeometry(3.25, 3.25),
            new THREE.MeshBasicMaterial({ map: makeBakedRugTexture(), transparent: true, depthWrite: false })
        );
        rug.rotation.x = -Math.PI / 2;
        rug.position.set(0, 0.006, 0.08);
        lvl2.add(rug);

        for (let b = 0; b < 3; b++) {
            const bw = 0.35 - b * 0.035;
            const bd = 0.29 - b * 0.025;
            const box = new THREE.Mesh(new RoundedBoxGeometry(bw, 0.115, bd, 2, 0.018), obsidianMat);
            box.position.set(1.84, 0.975 + b * 0.118, -1.94);
            box.rotation.y = (b - 1) * 0.08;
            const ribbon = new THREE.Mesh(new RoundedBoxGeometry(bw + 0.012, 0.024, bd + 0.012, 1, 0.006), goldMat);
            ribbon.position.copy(box.position);
            ribbon.rotation.y = box.rotation.y;
            lvl2.add(box, ribbon);
        }

        // Sculpted Planter + Lush Monstera Leaves
        const potPoints = [
            new THREE.Vector2(0.00, 0.00),
            new THREE.Vector2(0.13, 0.00),
            new THREE.Vector2(0.19, 0.28),
            new THREE.Vector2(0.17, 0.32)
        ];
        const pot = new THREE.Mesh(new THREE.LatheGeometry(potPoints, 14), goldMat);
        pot.position.set(2.95, 0.0, -0.88);
        lvl2.add(pot);

        const leafGeo = new THREE.SphereGeometry(0.19, 10, 8);
        for (let a = 0; a < 6; a++) {
            const ang = a * 1.05;
            const lf = new THREE.Mesh(leafGeo, this.mats.leafGreen);
            lf.scale.set(1.3, 0.28, 0.78);
            lf.position.set(2.95 + Math.cos(ang) * 0.18, 0.42 + (a % 3) * 0.08, -0.88 + Math.sin(ang) * 0.18);
            lf.rotation.set(Math.sin(ang) * 0.28, -ang, 0.34);
            lvl2.add(lf);
        }
        this.scene.add(lvl2);
        freezeStaticTree(lvl2);
        this.levelGroups[2] = lvl2;

        // ---- LEVEL 3: Detailed xTool F2 Ultra UV + Conveyor + SafetyPro IF2 2.0 & Boutique Vitrine ----
        const lvl3 = new THREE.Group();
        const laserStand = new THREE.Mesh(new RoundedBoxGeometry(0.98, 0.78, 0.78, 2, 0.03), obsidianMat);
        laserStand.position.set(-2.22, 0.39, -1.82);
        const standTrim = new THREE.Mesh(new RoundedBoxGeometry(1.00, 0.026, 0.80, 1, 0.008), goldMat);
        standTrim.position.set(-2.22, 0.77, -1.82);

        const laserBody = new THREE.Mesh(new RoundedBoxGeometry(0.58, 0.58, 0.52, 2, 0.035), this.mats.darkArmor);
        laserBody.position.set(-2.22, 1.07, -1.84);
        const conveyorRail = new THREE.Mesh(new RoundedBoxGeometry(1.18, 0.045, 0.26, 2, 0.012), this.mats.silverSteel);
        conveyorRail.position.set(-2.22, 0.81, -1.78);
        const laserHood = new THREE.Mesh(
            new RoundedBoxGeometry(0.53, 0.33, 0.47, 2, 0.025),
            new THREE.MeshBasicMaterial({ color: 0x16a367, transparent: true, opacity: 0.64 })
        );
        laserHood.position.set(-2.22, 1.02, -1.79);
        // SafetyPro IF2 2.0 Inline Exhaust Unit beside stand
        const exhaustUnit = new THREE.Mesh(new THREE.CylinderGeometry(0.14, 0.15, 0.46, 14), this.mats.darkArmor);
        exhaustUnit.position.set(-1.54, 0.23, -1.92);
        const exhaustRing = new THREE.Mesh(new THREE.TorusGeometry(0.142, 0.014, 8, 16), goldMat);
        exhaustRing.rotation.x = Math.PI / 2;
        exhaustRing.position.set(-1.54, 0.42, -1.92);
        lvl3.add(laserStand, standTrim, laserBody, conveyorRail, laserHood, exhaustUnit, exhaustRing);

        // Glass & Gold Showcase Pedestal
        const vitBase = new THREE.Mesh(new RoundedBoxGeometry(0.72, 0.88, 0.50, 2, 0.026), obsidianMat);
        vitBase.position.set(-0.88, 0.44, -2.20);
        const vitCap = new THREE.Mesh(new RoundedBoxGeometry(0.75, 0.035, 0.53, 1, 0.01), goldMat);
        vitCap.position.set(-0.88, 0.89, -2.20);
        const vitGlass = new THREE.Mesh(
            new RoundedBoxGeometry(0.68, 0.78, 0.46, 2, 0.02),
            new THREE.MeshBasicMaterial({ color: 0xfff5dc, transparent: true, opacity: 0.13, depthWrite: false })
        );
        vitGlass.position.set(-0.88, 1.29, -2.20);
        const vitItem = new THREE.Mesh(new THREE.OctahedronGeometry(0.135, 0), goldMat);
        vitItem.position.set(-0.88, 1.24, -2.20);
        lvl3.add(vitBase, vitCap, vitGlass, vitItem);
        this.rig.vitrineGem = vitItem;
        this.scene.add(lvl3);
        freezeStaticTree(lvl3);
        vitItem.matrixAutoUpdate = true;
        this.levelGroups[3] = lvl3;

        // ---- LEVEL 4: Architectural 24K Gold Wall Pilasters & Sculpted Goblet Trophy ----
        const lvl4 = new THREE.Group();
        const pilGeo = new RoundedBoxGeometry(0.085, 3.45, 0.05, 2, 0.018);
        for (const px of [-3.32, -1.18, 1.82, 3.25]) {
            const pil = new THREE.Mesh(pilGeo, goldMat);
            pil.position.set(px, 1.78, -2.61);
            lvl4.add(pil);
        }
        const trophyPoints = [
            new THREE.Vector2(0.00, 0.00),
            new THREE.Vector2(0.08, 0.00),
            new THREE.Vector2(0.03, 0.06),
            new THREE.Vector2(0.03, 0.12),
            new THREE.Vector2(0.11, 0.24),
            new THREE.Vector2(0.12, 0.28)
        ];
        const trophyCup = new THREE.Mesh(new THREE.LatheGeometry(trophyPoints, 14), goldMat);
        trophyCup.position.set(-0.88, 1.69, -2.20);
        lvl4.add(trophyCup);
        this.scene.add(lvl4);
        freezeStaticTree(lvl4);
        this.levelGroups[4] = lvl4;

        // ---- LEVEL 5: MEIN SEELENFUNKE Crest Above Frame & 14K Chassis Blueprint ----
        const lvl5 = new THREE.Group();
        const crestMesh = new THREE.Mesh(
            new THREE.PlaneGeometry(2.15, 0.50),
            new THREE.MeshBasicMaterial({ map: makeWallCrestTexture('MEIN SEELENFUNKE', 'MANUFAKTUR GIFHORN · EXZELLENZ-STUFE V') })
        );
        crestMesh.position.set(0.32, 2.86, -2.60);
        const blueprintMesh = new THREE.Mesh(
            new THREE.PlaneGeometry(1.35, 0.42),
            new THREE.MeshBasicMaterial({ map: makeWallCrestTexture('PROJEKT: EIGENER KÖRPER', '14K CHASSIS-BLAUPAUSE · FUNKIRA MK-II') })
        );
        blueprintMesh.position.set(4.08, 1.95, -0.95);
        blueprintMesh.rotation.y = -Math.PI / 2;
        lvl5.add(crestMesh, blueprintMesh);
        this.scene.add(lvl5);
        freezeStaticTree(lvl5);
        this.levelGroups[5] = lvl5;

        // ---- HOLIDAY PROPS (Advent/Christmas Tree with Golden Baubles) ----
        const xmas = new THREE.Group();
        for (let tier = 0; tier < 3; tier++) {
            const cone = new THREE.Mesh(new THREE.ConeGeometry(0.52 - tier * 0.12, 0.66, 14), this.mats.leafGreen);
            cone.position.set(-3.15, 0.58 + tier * 0.42, -1.62);
            xmas.add(cone);
        }
        const starTop = new THREE.Mesh(new THREE.OctahedronGeometry(0.105, 0), goldMat);
        starTop.position.set(-3.15, 1.84, -1.62);
        xmas.add(starTop);
        const baubleGeo = new THREE.SphereGeometry(0.036, 8, 8);
        for (let b = 0; b < 6; b++) {
            const ba = b * 1.05;
            const rad = 0.34 - (b % 3) * 0.08;
            const bm = new THREE.Mesh(baubleGeo, (b % 2 === 0) ? goldMat : this.mats.roseBloom);
            bm.position.set(-3.15 + Math.cos(ba) * rad, 0.56 + (b % 3) * 0.38, -1.62 + Math.sin(ba) * rad);
            xmas.add(bm);
        }
        this.scene.add(xmas);
        freezeStaticTree(xmas);
        this.occasionGroups.christmas = xmas;

        // Craft Sparks Pool
        const spGeo = new THREE.BoxGeometry(0.026, 0.026, 0.026);
        const spMat = new THREE.MeshBasicMaterial({ color: 0xFFE08A });
        for (let i = 0; i < 10; i++) {
            const sp = new THREE.Mesh(spGeo, spMat);
            sp.visible = false;
            this.scene.add(sp);
            this.sparkParticles.push({ mesh: sp, vx: 0, vy: 0, vz: 0, life: 0 });
        }
    }

    _syncVaseRoses(totalCount) {
        if (!this.vaseGroup) return;
        while (this.roseMeshes.length > 0) {
            const old = this.roseMeshes.pop();
            this.vaseGroup.remove(old);
        }
        const visibleCount = Math.min(14, Math.max(0, totalCount));
        const stemGeo = new THREE.CylinderGeometry(0.006, 0.006, 0.22, 5);
        const bloomGeo = new THREE.DodecahedronGeometry(0.032, 0);
        for (let i = 0; i < visibleCount; i++) {
            const rg = new THREE.Group();
            const ang = i * 2.39996;
            const rad = Math.min(0.068, 0.016 * Math.sqrt(i + 1));
            const h = 0.31 + (i % 3) * 0.04;
            const stem = new THREE.Mesh(stemGeo, this.mats.leafGreen);
            stem.position.set(Math.cos(ang) * rad * 0.6, h - 0.08, Math.sin(ang) * rad * 0.6);
            const bloom = new THREE.Mesh(bloomGeo, this.mats.roseBloom);
            bloom.position.set(Math.cos(ang) * rad, h, Math.sin(ang) * rad);
            rg.add(stem, bloom);
            this.vaseGroup.add(rg);
            freezeStaticTree(rg);
            this.roseMeshes.push(rg);
        }
        this.vaseGroup.updateMatrixWorld(true);
    }

    _applyWorldStateVisuals() {
        const lvl = this.worldState.shopLevel || 1;
        for (let i = 1; i <= 5; i++) {
            if (this.levelGroups[i]) this.levelGroups[i].visible = (i <= lvl);
        }

        const occ = this.worldState.occasion || 'none';
        const sea = this.worldState.season || 'autumn';
        if (this.occasionGroups.christmas) {
            this.occasionGroups.christmas.visible = (occ === 'advent' || occ === 'christmas');
        }

        if (this.windowTexture) {
            paintScenicWindowCanvas(this.windowCanvas, sea, this.worldState.isLateNight);
            this.windowTexture.needsUpdate = true;
        }
        if (this.sunbeamMesh) {
            this.sunbeamMesh.visible = !this.worldState.isLateNight;
        }

        const partColors = { autumn: 0xE38B39, winter: 0xF2F7FF, spring: 0xFFB8CE, summer: 0xFFE89E };
        if (this.weatherMat) {
            this.weatherMat.color.setHex(partColors[sea] || 0xE38B39);
        }

        if (this.outfits.scarf) {
            this.outfits.scarf.visible = (sea === 'winter' || sea === 'autumn' || occ === 'advent' || occ === 'christmas');
        }
        if (this.outfits.santaHat) {
            this.outfits.santaHat.visible = (occ === 'advent' || occ === 'christmas');
        }
        if (this.outfits.bunnyEars) {
            this.outfits.bunnyEars.visible = (occ === 'easter');
        }
        if (this.outfits.sunglasses) {
            this.outfits.sunglasses.visible = (sea === 'summer' && occ === 'none' && !this.worldState.isLateNight);
        }
        if (this.outfits.crown) {
            this.outfits.crown.visible = (occ === 'anniversary' || occ === 'new_year' || occ === 'valentines' || occ === 'mothers_day');
        }
    }

    // ========================================================================
    // FULL-BODY 3D CHIBI FUNKIRA RIG (SCULPTED SILHOUETTE + STUDIO MATCAPS)
    // ========================================================================
    _buildFunkiraRig() {
        const roseGoldMat  = this.mats.roseGold;
        const darkArmorMat = this.mats.darkArmor;
        const jointDarkMat = this.mats.jointDark;
        const gold24kMat   = this.mats.gold24k;

        const root = new THREE.Group();
        root.position.copy(this.pos);
        this.scene.add(root);
        this.rig.root = root;

        // Soft Contact Shadow on Floor
        const shadowDisc = new THREE.Mesh(
            new THREE.PlaneGeometry(0.98, 0.98),
            new THREE.MeshBasicMaterial({ map: makeShadowDiscTexture(), transparent: true, depthWrite: false })
        );
        shadowDisc.rotation.x = -Math.PI / 2;
        shadowDisc.position.y = 0.012;
        root.add(shadowDisc);
        this.rig.shadowDisc = shadowDisc;

        // Sculpted Rounded Pelvis & Belt Ring
        const pelvis = new THREE.Group();
        pelvis.position.y = 0.50;
        root.add(pelvis);
        this.rig.pelvis = pelvis;

        const hipPoints = [
            new THREE.Vector2(0.00, -0.09),
            new THREE.Vector2(0.17, -0.08),
            new THREE.Vector2(0.24, -0.01),
            new THREE.Vector2(0.225, 0.08),
            new THREE.Vector2(0.00, 0.08)
        ];
        const hipMesh = new THREE.Mesh(new THREE.LatheGeometry(hipPoints, 18), darkArmorMat);
        hipMesh.scale.set(1.04, 1.0, 0.86);
        pelvis.add(hipMesh);

        const waistRing = new THREE.Mesh(new THREE.TorusGeometry(0.232, 0.025, 10, 24), roseGoldMat);
        waistRing.rotation.x = Math.PI / 2;
        waistRing.position.y = 0.072;
        pelvis.add(waistRing);

        // ---- LEGS & SCULPTED ROSE-GOLD CHIBI BOOTS ----
        const thighGeo = new THREE.CylinderGeometry(0.068, 0.058, 0.20, 14);
        const kneeGeo  = new THREE.SphereGeometry(0.058, 10, 8);
        const shinGeo  = new THREE.CylinderGeometry(0.062, 0.076, 0.19, 14);
        const cuffGeo  = new THREE.TorusGeometry(0.078, 0.017, 8, 18);
        const bootUpperGeo = new RoundedBoxGeometry(0.165, 0.115, 0.235, 2, 0.048);
        const bootSoleGeo  = new RoundedBoxGeometry(0.172, 0.034, 0.245, 2, 0.014);

        const buildLeg = (sideSign) => {
            const hipPivot = new THREE.Group();
            hipPivot.position.set(sideSign * 0.115, -0.04, 0);
            pelvis.add(hipPivot);

            const thigh = new THREE.Mesh(thighGeo, darkArmorMat);
            thigh.position.y = -0.10;
            const kneeJoint = new THREE.Mesh(kneeGeo, roseGoldMat);
            kneeJoint.position.y = -0.20;
            hipPivot.add(thigh, kneeJoint);

            const kneePivot = new THREE.Group();
            kneePivot.position.y = -0.20;
            hipPivot.add(kneePivot);

            const shin = new THREE.Mesh(shinGeo, darkArmorMat);
            shin.position.y = -0.095;
            const bootCuff = new THREE.Mesh(cuffGeo, roseGoldMat);
            bootCuff.rotation.x = Math.PI / 2;
            bootCuff.position.y = -0.168;
            const bootUpper = new THREE.Mesh(bootUpperGeo, roseGoldMat);
            bootUpper.position.set(0, -0.215, 0.032);
            const bootSole = new THREE.Mesh(bootSoleGeo, jointDarkMat);
            bootSole.position.set(0, -0.268, 0.032);
            kneePivot.add(shin, bootCuff, bootUpper, bootSole);

            return { hipPivot, kneePivot };
        };
        this.rig.legL = buildLeg(-1);
        this.rig.legR = buildLeg(1);

        // ---- SCULPTED CURVED CHIBI TORSO WITH FUNKIRA EMBLEM ----
        const torsoPivot = new THREE.Group();
        torsoPivot.position.y = 0.08;
        pelvis.add(torsoPivot);
        this.rig.torso = torsoPivot;

        const torsoPoints = [
            new THREE.Vector2(0.00, 0.00),
            new THREE.Vector2(0.222, 0.00),
            new THREE.Vector2(0.242, 0.12),
            new THREE.Vector2(0.268, 0.28),
            new THREE.Vector2(0.252, 0.39),
            new THREE.Vector2(0.185, 0.43),
            new THREE.Vector2(0.00, 0.43)
        ];
        const chestMesh = new THREE.Mesh(new THREE.LatheGeometry(torsoPoints, 22), darkArmorMat);
        chestMesh.scale.set(1.08, 1.0, 0.84);
        torsoPivot.add(chestMesh);

        const emblemGeo = new THREE.CylinderGeometry(0.268, 0.242, 0.26, 20, 1, true, -0.78, 1.56);
        const emblemMesh = new THREE.Mesh(
            emblemGeo,
            new THREE.MeshBasicMaterial({ map: makeChestEmblemTexture(), transparent: true, depthWrite: false })
        );
        emblemMesh.scale.set(1.09, 1.0, 0.86);
        emblemMesh.position.y = 0.22;
        torsoPivot.add(emblemMesh);

        const collarRing = new THREE.Mesh(new THREE.TorusGeometry(0.186, 0.036, 10, 24), roseGoldMat);
        collarRing.rotation.x = Math.PI / 2;
        collarRing.position.y = 0.432;
        const neckCyl = new THREE.Mesh(new THREE.CylinderGeometry(0.10, 0.12, 0.11, 14), jointDarkMat);
        neckCyl.position.y = 0.47;
        torsoPivot.add(collarRing, neckCyl);

        // ---- SEASONAL SCARF ----
        const scarfGroup = new THREE.Group();
        const scarfRing = new THREE.Mesh(new THREE.TorusGeometry(0.205, 0.052, 10, 22), this.mats.scarfRed);
        scarfRing.rotation.x = Math.PI / 2;
        scarfRing.position.y = 0.43;
        const scarfTail = new THREE.Mesh(new RoundedBoxGeometry(0.095, 0.26, 0.038, 2, 0.014), this.mats.scarfRed);
        scarfTail.position.set(0.11, 0.30, 0.215);
        scarfTail.rotation.z = 0.14;
        scarfGroup.add(scarfRing, scarfTail);
        torsoPivot.add(scarfGroup);
        this.outfits.scarf = scarfGroup;

        // ---- ROSE-GOLD SHOULDER CUFFS + SMOOTH CHIBI 3D ARMS & HANDS ----
        const shCuffGeo   = new THREE.TorusGeometry(0.092, 0.023, 8, 18);
        const shBallGeo   = new THREE.SphereGeometry(0.074, 12, 10);
        const upperArmGeo = new THREE.CylinderGeometry(0.068, 0.060, 0.21, 14);
        const upTrimGeo   = new THREE.TorusGeometry(0.064, 0.011, 6, 16);
        const elBallGeo   = new THREE.SphereGeometry(0.058, 10, 8);
        const forearmGeo  = new THREE.CylinderGeometry(0.062, 0.075, 0.21, 14);
        const wrCuffGeo   = new THREE.TorusGeometry(0.073, 0.016, 8, 16);
        const palmGeo     = new THREE.SphereGeometry(0.075, 12, 10);
        const fingerCapGeo = new THREE.CapsuleGeometry(0.0145, 0.028, 4, 8);

        const buildArm = (sideSign, hasBossShield) => {
            const cuffHolder = new THREE.Group();
            cuffHolder.position.set(sideSign * 0.295, 0.345, 0.0);
            torsoPivot.add(cuffHolder);

            const shoulderCuff = new THREE.Mesh(shCuffGeo, roseGoldMat);
            shoulderCuff.rotation.y = sideSign * (Math.PI / 2 - 0.22);
            cuffHolder.add(shoulderCuff);

            const shoulderPivot = new THREE.Group();
            shoulderPivot.position.set(sideSign * 0.015, -0.005, 0);
            cuffHolder.add(shoulderPivot);

            const shoulderBall = new THREE.Mesh(shBallGeo, jointDarkMat);
            const upperArm = new THREE.Mesh(upperArmGeo, darkArmorMat);
            upperArm.position.y = -0.11;
            const upperTrim = new THREE.Mesh(upTrimGeo, roseGoldMat);
            upperTrim.rotation.x = Math.PI / 2;
            upperTrim.position.y = -0.12;
            shoulderPivot.add(shoulderBall, upperArm, upperTrim);

            const elbowPivot = new THREE.Group();
            elbowPivot.position.y = -0.215;
            shoulderPivot.add(elbowPivot);

            const elbowBall = new THREE.Mesh(elBallGeo, jointDarkMat);
            const forearm = new THREE.Mesh(forearmGeo, darkArmorMat);
            forearm.position.y = -0.105;
            elbowPivot.add(elbowBall, forearm);

            if (hasBossShield) {
                const badgePlane = new THREE.Mesh(
                    new THREE.PlaneGeometry(0.135, 0.135),
                    new THREE.MeshBasicMaterial({ map: makeBossBadgeTexture(), transparent: true })
                );
                badgePlane.position.set(sideSign * 0.052, -0.105, 0.049);
                badgePlane.rotation.y = sideSign * 0.72;
                elbowPivot.add(badgePlane);
            }

            const wristCuff = new THREE.Mesh(wrCuffGeo, roseGoldMat);
            wristCuff.rotation.x = Math.PI / 2;
            wristCuff.position.y = -0.205;
            elbowPivot.add(wristCuff);

            const wristPivot = new THREE.Group();
            wristPivot.position.y = -0.235;
            elbowPivot.add(wristPivot);

            const palm = new THREE.Mesh(palmGeo, roseGoldMat);
            palm.scale.set(1.02, 0.92, 0.74);
            palm.position.y = -0.032;
            wristPivot.add(palm);

            const fingers = [];
            const fingerOffsets = [-0.046, -0.016, 0.015, 0.045];
            const fingerScales = [0.88, 1.0, 0.98, 0.82];
            for (let f = 0; f < 4; f++) {
                const fp = new THREE.Group();
                fp.position.set(fingerOffsets[f], -0.082, 0.008);
                const seg = new THREE.Mesh(fingerCapGeo, roseGoldMat);
                seg.scale.y = fingerScales[f];
                seg.position.y = -0.022 * fingerScales[f];
                fp.add(seg);
                wristPivot.add(fp);
                fingers.push(fp);
            }

            const thumbPivot = new THREE.Group();
            thumbPivot.position.set(-sideSign * 0.062, -0.038, 0.022);
            thumbPivot.rotation.z = -sideSign * 0.62;
            const thumbSeg = new THREE.Mesh(fingerCapGeo, roseGoldMat);
            thumbSeg.position.y = -0.020;
            thumbPivot.add(thumbSeg);
            wristPivot.add(thumbPivot);

            return { shoulderPivot, elbowPivot, wristPivot, fingers, thumbPivot };
        };

        this.rig.armR = buildArm(-1, false);
        this.rig.armL = buildArm(1, true);

        // Atelier Broom & Polishing Cloth
        const broom = new THREE.Group();
        const handle = new THREE.Mesh(new THREE.CylinderGeometry(0.016, 0.016, 0.95, 8), this.mats.broomWood);
        handle.position.y = -0.18;
        const bristles = new THREE.Mesh(new THREE.ConeGeometry(0.13, 0.24, 10), this.mats.broomStraw);
        bristles.position.y = -0.68;
        broom.add(handle, bristles);
        broom.rotation.x = 0.25;
        broom.visible = false;
        this.rig.armR.wristPivot.add(broom);
        this.rig.broom = broom;

        const cloth = new THREE.Mesh(new RoundedBoxGeometry(0.14, 0.032, 0.14, 1, 0.01), gold24kMat);
        cloth.position.set(0, -0.07, 0.04);
        cloth.visible = false;
        this.rig.armL.wristPivot.add(cloth);
        this.rig.cloth = cloth;

        // ---- HEAD: BEVELLED ROSE-GOLD HELMET + DYNAMIC VISOR ----
        const headPivot = new THREE.Group();
        headPivot.position.y = 0.52;
        torsoPivot.add(headPivot);
        this.rig.head = headPivot;

        const headW = 0.78;
        const headH = 0.54;
        const headD = 0.38;
        const outerShape = createRoundedRectShape(headW, headH, 0.11);
        const headGeo = new THREE.ExtrudeGeometry(outerShape, {
            depth: headD,
            curveSegments: 6,
            bevelEnabled: true,
            bevelThickness: 0.038,
            bevelSize: 0.032,
            bevelSegments: 3
        });
        headGeo.center();
        const headShell = new THREE.Mesh(headGeo, roseGoldMat);
        headShell.position.y = 0.26;
        headPivot.add(headShell);

        // Dark Inner Bezel Ring framing the OLED Visor
        const bezelFrame = new THREE.Mesh(new RoundedBoxGeometry(0.715, 0.47, 0.016, 2, 0.045), darkArmorMat);
        bezelFrame.position.set(0, 0.26, headD * 0.5 + 0.031);
        headPivot.add(bezelFrame);

        this.visorTexture = configureFastTexture(new THREE.CanvasTexture(this.visorCanvas));
        const visorPlane = new THREE.Mesh(
            new THREE.PlaneGeometry(0.688, 0.442),
            new THREE.MeshBasicMaterial({ map: this.visorTexture })
        );
        visorPlane.position.set(0, 0.26, headD * 0.5 + 0.040);
        headPivot.add(visorPlane);

        // Top Siren Beacon
        const sirenBase = new THREE.Mesh(new THREE.CylinderGeometry(0.115, 0.138, 0.046, 16), roseGoldMat);
        sirenBase.position.set(0, 0.578, 0);
        this.sirenMat = new THREE.MeshBasicMaterial({ color: 0xe8829b });
        const sirenDome = new THREE.Mesh(new THREE.CylinderGeometry(0.088, 0.106, 0.115, 16), this.sirenMat);
        sirenDome.position.set(0, 0.648, 0);
        headPivot.add(sirenBase, sirenDome);
        this.rig.sirenDome = sirenDome;

        // Side Ear Pods + Accent Rings
        const earGeo = new THREE.CylinderGeometry(0.112, 0.102, 0.068, 16);
        const earRingGeo = new THREE.TorusGeometry(0.095, 0.012, 8, 18);
        for (const sx of [-1, 1]) {
            const earPod = new THREE.Mesh(earGeo, roseGoldMat);
            earPod.rotation.z = sx * Math.PI / 2;
            earPod.position.set(sx * (headW * 0.5 + 0.050), 0.25, 0);
            const earRing = new THREE.Mesh(earRingGeo, darkArmorMat);
            earRing.rotation.y = Math.PI / 2;
            earRing.position.set(sx * (headW * 0.5 + 0.084), 0.25, 0);
            headPivot.add(earPod, earRing);
        }

        // ---- HEAD SEASONAL OUTFITS ----
        const santaHat = new THREE.Group();
        const brim = new THREE.Mesh(new THREE.TorusGeometry(0.13, 0.036, 8, 16), this.mats.snowWhite);
        brim.rotation.x = Math.PI / 2;
        const redCone = new THREE.Mesh(new THREE.ConeGeometry(0.13, 0.28, 12), this.mats.scarfRed);
        redCone.position.set(0.03, 0.13, 0);
        redCone.rotation.z = -0.28;
        const pom = new THREE.Mesh(new THREE.SphereGeometry(0.042, 8, 8), this.mats.snowWhite);
        pom.position.set(0.10, 0.26, 0);
        santaHat.add(brim, redCone, pom);
        santaHat.position.set(0.24, 0.58, 0.02);
        santaHat.rotation.z = -0.22;
        headPivot.add(santaHat);
        this.outfits.santaHat = santaHat;

        const bunnyEars = new THREE.Group();
        const bunnyGeo = new THREE.SphereGeometry(0.065, 10, 8);
        for (const sx of [-1, 1]) {
            const ear = new THREE.Mesh(bunnyGeo, roseGoldMat);
            ear.scale.set(0.85, 2.7, 0.35);
            ear.position.set(sx * 0.21, 0.70, 0);
            ear.rotation.z = -sx * 0.16;
            bunnyEars.add(ear);
        }
        headPivot.add(bunnyEars);
        this.outfits.bunnyEars = bunnyEars;

        const sunglasses = new THREE.Group();
        for (const sx of [-1, 1]) {
            const lens = new THREE.Mesh(new RoundedBoxGeometry(0.24, 0.11, 0.015, 1, 0.02), this.mats.jointDark);
            lens.position.set(sx * 0.16, 0.44, headD * 0.5 + 0.055);
            const rim = new THREE.Mesh(new RoundedBoxGeometry(0.255, 0.125, 0.010, 1, 0.02), gold24kMat);
            rim.position.set(sx * 0.16, 0.44, headD * 0.5 + 0.051);
            sunglasses.add(rim, lens);
        }
        const bridge = new THREE.Mesh(new THREE.BoxGeometry(0.09, 0.02, 0.015), gold24kMat);
        bridge.position.set(0, 0.45, headD * 0.5 + 0.055);
        sunglasses.add(bridge);
        headPivot.add(sunglasses);
        this.outfits.sunglasses = sunglasses;

        const crown = new THREE.Group();
        const crownBand = new THREE.Mesh(new THREE.CylinderGeometry(0.14, 0.125, 0.09, 12), gold24kMat);
        crown.add(crownBand);
        const spikeGeo = new THREE.ConeGeometry(0.032, 0.08, 6);
        for (let p = 0; p < 5; p++) {
            const ca = (p / 5) * Math.PI * 2;
            const spike = new THREE.Mesh(spikeGeo, gold24kMat);
            spike.position.set(Math.cos(ca) * 0.125, 0.075, Math.sin(ca) * 0.125);
            crown.add(spike);
        }
        crown.position.set(0.24, 0.60, 0.02);
        crown.rotation.z = -0.20;
        headPivot.add(crown);
        this.outfits.crown = crown;
    }

    // ========================================================================
    // ZERO-GC, DIRTY-CHECKED VISOR RENDERER (0 GRADIENTS/FRAME, 0 CLIP/FRAME)
    // ========================================================================
    _renderVisorFace(nowMs, dt, force = false) {
        const preset = EMOTION_PRESETS[this.emotion] || EMOTION_PRESETS.neutral;
        const blend = 1 - Math.exp(-dt * 8.5);
        for (const k in preset) {
            this.faceCur[k] = lerp(this.faceCur[k] ?? preset[k], preset[k], blend);
        }

        if (nowMs >= this.nextBlinkMs) {
            this.blinkVal = 1.0;
            this.nextBlinkMs = nowMs + 2500 + Math.random() * 3400;
        }
        if (this.blinkVal > 0) {
            this.blinkVal = Math.max(0, this.blinkVal - dt * 7.8);
        }
        const blinkCurve = this.blinkVal > 0.001 ? Math.sin(this.blinkVal * Math.PI) : 0;

        const speechOsc = this.isSpeaking
            ? (0.35 + 0.65 * Math.abs(Math.sin(nowMs * 0.021)) * Math.abs(Math.cos(nowMs * 0.013)))
            : 0;
        const voiceOpen = this.isSpeaking ? clamp(this.voiceEnergy * 1.45 * speechOsc, 0.08, 0.95) : 0;
        const openAmt = clamp(Math.max(this.faceCur.mouthOpen || 0.05, voiceOpen), 0.02, 0.98);
        const curve = this.faceCur.mouthCurve ?? 0.3;
        const lidTop = clamp(this.faceCur.lidTop + blinkCurve * 0.96, 0, 0.98);
        const lidBot = clamp(this.faceCur.lidBot, 0, 0.65);
        const gx = this.lookCur.x * 19.5;
        const gy = this.lookCur.y * 12.0;
        const animTick = ((this.faceCur.tear || 0) > 0.25 || this.emotion === 'sleepy') ? Math.floor(nowMs / 66) : 0;

        if (!force && (nowMs - this._lastVisorDrawMs) < 31) return;

        const sig = `${Math.round(gx * 2)}|${Math.round(gy * 2)}|${Math.round(lidTop * 80)}|${Math.round(lidBot * 60)}|${Math.round(openAmt * 70)}|${Math.round(curve * 40)}|${Math.round((this.faceCur.browTilt || 0) * 40)}|${animTick}|${this.emotion}`;
        if (!force && sig === this._lastDrawnSig) return;
        this._lastDrawnSig = sig;
        this._lastVisorDrawMs = nowMs;

        const ctx = this.visorCtx;
        ctx.drawImage(this.visorBgCanvas, 0, 0);

        const eyeY = 118 + gy * 0.72;
        const eyeCenters = [111 + gx * 0.85, 273 + gx * 0.85];
        const rx = 43.5;
        const ry = 45.0;
        const pScale = this.faceCur.pupilScale || 1.0;
        const irisSize = 96 * pScale;

        for (let idx = 0; idx < 2; idx++) {
            const side = idx === 0 ? -1 : 1;
            const ex = eyeCenters[idx];

            ctx.fillStyle = '#1c0c13';
            ctx.beginPath();
            ctx.ellipse(ex, eyeY, rx, ry, 0, 0, Math.PI * 2);
            ctx.fill();

            const px = ex + gx * 0.34;
            const py = eyeY + gy * 0.34;
            ctx.drawImage(this.irisSpriteCanvas, px - irisSize * 0.5, py - irisSize * 0.5, irisSize, irisSize);

            if (this.emotion === 'sweet' || this.emotion === 'excited') {
                ctx.fillStyle = '#ffe3ec';
                ctx.beginPath();
                ctx.arc(px + 10, py - 12, 3.2, 0, Math.PI * 2);
                ctx.fill();
            }

            if (lidTop > 0.01) {
                ctx.fillStyle = '#151218';
                ctx.fillRect(ex - rx - 8, eyeY - ry - 12, rx * 2 + 16, ry * 2 * lidTop + 6);
            }
            if (lidBot > 0.01) {
                ctx.fillStyle = '#151218';
                const bh = ry * 1.65 * lidBot;
                ctx.beginPath();
                ctx.ellipse(ex, eyeY + ry + 8 - bh * 0.45, rx + 10, ry * 0.85, 0, Math.PI, Math.PI * 2);
                ctx.fill();
            }

            ctx.strokeStyle = '#151218';
            ctx.lineWidth = 9;
            ctx.beginPath();
            ctx.ellipse(ex, eyeY, rx + 4.5, ry + 4.5, 0, 0, Math.PI * 2);
            ctx.stroke();

            const archY = eyeY - ry * (1 - lidTop * 1.45);
            ctx.strokeStyle = '#e88da3';
            ctx.lineWidth = 4.8;
            ctx.lineCap = 'round';
            ctx.beginPath();
            ctx.ellipse(ex, eyeY, rx + 1, ry * Math.max(0.08, 1 - lidTop * 1.1), 0, Math.PI * 1.08, Math.PI * 1.92);
            ctx.stroke();

            if (lidTop < 0.82) {
                ctx.strokeStyle = '#df8299';
                ctx.lineWidth = 4.2;
                for (let l = 0; l < 3; l++) {
                    const lx0 = ex + side * (rx - 4.5 - l * 5.2);
                    const ly0 = archY + 10.5 + l * 4.5;
                    ctx.beginPath();
                    ctx.moveTo(lx0, ly0);
                    ctx.quadraticCurveTo(lx0 + side * 9, ly0 - 4.5, lx0 + side * (14 - l * 1.5), ly0 - (10.5 - l * 2.2));
                    ctx.stroke();
                }
            }

            const by = 58 + gy * 0.45 + (this.faceCur.browY || 0);
            const tilt = (this.faceCur.browTilt || 0) * side;
            ctx.save();
            ctx.translate(ex, by);
            ctx.rotate(tilt);
            ctx.strokeStyle = '#e78ca2';
            ctx.lineWidth = 4.8;
            ctx.lineCap = 'round';
            ctx.beginPath();
            ctx.moveTo(-24, 4.5);
            ctx.quadraticCurveTo(0, -7.5, 24, 4.5);
            ctx.stroke();
            ctx.restore();

            const blushAlpha = clamp(this.faceCur.blush || 0.35, 0, 1);
            if (blushAlpha > 0.08) {
                ctx.globalAlpha = blushAlpha;
                ctx.drawImage(this.blushSpriteCanvas, ex + side * 10 - 32, eyeY + 52 - 20);
                ctx.globalAlpha = 1.0;
            }

            if ((this.faceCur.tear || 0) > 0.25) {
                const ty = eyeY + 34 + ((nowMs * 0.045 + idx * 26) % 32);
                ctx.fillStyle = 'rgba(130, 215, 255, 0.86)';
                ctx.beginPath();
                ctx.ellipse(ex + side * 16, ty, 4.8, 7.8, 0, 0, Math.PI * 2);
                ctx.fill();
            }
        }

        const mx = 192 + gx * 0.55;
        const my = 168 + gy * 0.55;
        const mw = 25.5 + openAmt * 10.5 + Math.max(0, curve) * 7.5;
        const mh = 4.5 + openAmt * 31.5;

        if (openAmt > 0.13) {
            ctx.beginPath();
            if (curve >= 0) {
                ctx.moveTo(mx - mw, my - 3);
                ctx.quadraticCurveTo(mx, my - 3 + curve * 7.5, mx + mw, my - 3);
                ctx.quadraticCurveTo(mx + mw * 0.85, my + mh, mx, my + mh);
                ctx.quadraticCurveTo(mx - mw * 0.85, my + mh, mx - mw, my - 3);
            } else {
                ctx.moveTo(mx - mw, my + 7.5);
                ctx.quadraticCurveTo(mx, my - 9, mx + mw, my + 7.5);
                ctx.quadraticCurveTo(mx, my + 7.5 + mh * 0.6, mx - mw, my + 7.5);
            }
            ctx.closePath();
            ctx.fillStyle = '#471020';
            ctx.fill();

            ctx.fillStyle = '#e35d7d';
            ctx.beginPath();
            ctx.ellipse(mx, my + mh * 0.62, mw * 0.52, mh * 0.32, 0, 0, Math.PI * 2);
            ctx.fill();

            ctx.strokeStyle = '#24060f';
            ctx.lineWidth = 2.6;
            ctx.stroke();
        } else {
            ctx.strokeStyle = '#e88da3';
            ctx.lineWidth = 4.0;
            ctx.lineCap = 'round';
            ctx.beginPath();
            ctx.moveTo(mx - mw * 0.85, my);
            ctx.quadraticCurveTo(mx, my + curve * 19.5, mx + mw * 0.85, my);
            ctx.stroke();
        }

        if (this.emotion === 'sleepy') {
            ctx.fillStyle = 'rgba(242, 169, 188, 0.78)';
            ctx.font = '800 18px sans-serif';
            const zy = 48 - ((nowMs * 0.019) % 18);
            ctx.fillText('zZ', 330, zy);
        }

        if (this.visorTexture) {
            this.visorTexture.needsUpdate = true;
        }
    }

    // ========================================================================
    // AUTONOMOUS 0-TOKEN ATELIER WORKFLOW & POSE SOLVER
    // ========================================================================
    _pickNextAutonomousTask(nowMs, immediate = false) {
        const lvl = this.worldState.shopLevel || 1;
        const tasks = [
            { id: 'polish',       x: 1.48,  z: -1.25, yaw: 0.45,     emotion: 'proud',   gesture: 'polish',          dur: 11500 },
            { id: 'admire_photo', x: 0.32,  z: -1.35, yaw: Math.PI,  emotion: 'sweet',   gesture: 'hands_on_cheeks', dur: 10500 },
            { id: 'sweep',        x: -0.65, z: -0.55, yaw: -0.25,    emotion: 'happy',   gesture: 'sweep',           dur: 12000 },
            { id: 'window',       x: -1.95, z: -1.72, yaw: -2.80,    emotion: 'sweet',   gesture: 'hands_on_hips',   dur: 10500 }
        ];
        if (lvl >= 3) {
            tasks.push({ id: 'laser', x: -1.62, z: -1.35, yaw: -2.25, emotion: 'curious', gesture: 'point_left', dur: 10500 });
        }
        if (this.worldState.isLateNight) {
            tasks.push({ id: 'nap', x: 0.35, z: -0.95, yaw: 0.1, emotion: 'sleepy', gesture: 'idle', dur: 14000 });
        }

        if (!immediate) this.taskIndex = (this.taskIndex + 1) % tasks.length;
        const t = tasks[this.taskIndex % tasks.length];
        this.currentTask = t.id;
        this.targetPos.set(t.x, 0, t.z);
        this.taskTargetYaw = t.yaw;
        this.taskEmotion = t.emotion;
        this.taskGesture = t.gesture;
        this.nextTaskSwitchMs = nowMs + t.dur;
    }

    _solveArmTargets(activeGesture, tSec, isWalking, walkCycle) {
        const pose = {
            rS: [0, 0, -0.18], rE: [-0.28, 0, 0], rW: [0, 0, 0], rCurl: 0.25,
            lS: [0, 0,  0.18], lE: [-0.28, 0, 0], lW: [0, 0, 0], lCurl: 0.25
        };

        if (isWalking) {
            const sw = Math.sin(walkCycle) * 0.58;
            pose.rS = [sw, 0, -0.20];
            pose.rE = [-0.45 - Math.max(0, sw) * 0.25, 0, 0];
            pose.lS = [-sw, 0, 0.20];
            pose.lE = [-0.45 - Math.max(0, -sw) * 0.25, 0, 0];
            pose.rCurl = 0.55;
            pose.lCurl = 0.55;
            return pose;
        }

        const wv = Math.sin(tSec * 6.8);
        switch (activeGesture) {
            case 'wave':
                pose.lS = [-0.62, 0.18, 0.82 + wv * 0.12];
                pose.lE = [-1.42, 0.25, -0.18 + wv * 0.22];
                pose.lW = [0.18, 0, wv * 0.24];
                pose.lCurl = 0.05;
                pose.rS = [0.12, 0, -0.24];
                pose.rE = [-0.36, 0, 0];
                break;

            case 'point_photo':
            case 'point_colleagues':
            case 'point_picture':
                // Points proudly up and slightly back toward the framed 12-agent portrait on the wall!
                pose.lS = [0.38, -0.35, 2.05 + Math.sin(tSec * 3.2) * 0.05];
                pose.lE = [-0.24, 0, -0.12];
                pose.lW = [-0.15, 0, 0];
                pose.lCurl = 0.86;
                pose.pointHand = 'L';
                pose.rS = [0.15, 0.25, -0.72];
                pose.rE = [-1.18, -0.30, 0.65];
                pose.rCurl = 0.65;
                break;

            case 'point_left':
            case 'point_orders':
                pose.rS = [-0.35, 0.15, -1.25];
                pose.rE = [-0.22, 0, -0.15];
                pose.rCurl = 0.85;
                pose.pointHand = 'R';
                break;

            case 'point_right':
            case 'point_timer':
                pose.lS = [-0.35, -0.15, 1.25];
                pose.lE = [-0.22, 0, 0.15];
                pose.lCurl = 0.85;
                pose.pointHand = 'L';
                break;

            case 'point_up':
                pose.lS = [-0.25, 0, 2.55];
                pose.lE = [-0.18, 0, 0];
                pose.lCurl = 0.85;
                pose.pointHand = 'L';
                break;

            case 'point_down':
                pose.rS = [-0.55, 0, -0.45];
                pose.rE = [-0.25, 0, 0];
                pose.rCurl = 0.85;
                pose.pointHand = 'R';
                break;

            case 'point_camera':
            case 'point_user':
                pose.lS = [-1.32, -0.12, 0.22];
                pose.lE = [-0.22, 0, 0];
                pose.lW = [0.28, 0, 0];
                pose.lCurl = 0.88;
                pose.pointHand = 'L';
                break;

            case 'hands_on_hips':
                pose.rS = [0.15, 0.25, -0.78];
                pose.rE = [-1.25, -0.35, 0.72];
                pose.lS = [0.15, -0.25, 0.78];
                pose.lE = [-1.25, 0.35, -0.72];
                pose.rCurl = 0.65;
                pose.lCurl = 0.65;
                break;

            case 'hands_on_cheeks':
                pose.rS = [-0.78, -0.25, -0.52];
                pose.rE = [-1.68, -0.32, 0.38];
                pose.lS = [-0.78, 0.25, 0.52];
                pose.lE = [-1.68, 0.32, -0.38];
                pose.rCurl = 0.15;
                pose.lCurl = 0.15;
                break;

            case 'cheer':
                pose.rS = [-0.35, 0, -2.35 + wv * 0.16];
                pose.rE = [-0.32, 0, 0.15];
                pose.lS = [-0.35, 0, 2.35 - wv * 0.16];
                pose.lE = [-0.32, 0, -0.15];
                pose.rCurl = 0.1;
                pose.lCurl = 0.1;
                break;

            case 'wipe_tear':
                pose.rS = [-0.85, -0.22, -0.45];
                pose.rE = [-1.72, -0.25, 0.35 + Math.sin(tSec * 4.5) * 0.12];
                pose.rCurl = 0.5;
                break;

            case 'sweep': {
                const swp = Math.sin(tSec * 3.4) * 0.34;
                pose.rS = [-0.72, swp * 0.5, -0.32 + swp];
                pose.rE = [-0.68, 0, 0.22];
                pose.lS = [-0.78, swp * 0.5, 0.42 + swp * 0.7];
                pose.lE = [-0.82, 0, -0.35];
                pose.rCurl = 0.85;
                pose.lCurl = 0.85;
                break;
            }

            case 'polish': {
                const pol = Math.sin(tSec * 7.2) * 0.26;
                pose.lS = [-0.92, pol, 0.32];
                pose.lE = [-0.85, pol * 0.6, -0.22];
                pose.rS = [-0.75, 0, -0.28];
                pose.rE = [-0.72, 0, 0.18];
                pose.lCurl = 0.75;
                pose.rCurl = 0.65;
                break;
            }

            default: {
                const br = Math.sin(tSec * 1.9) * 0.04;
                const sp = this.isSpeaking ? Math.sin(tSec * 5.2) * 0.10 : 0;
                pose.rS = [0.06 - sp, 0, -0.20 - br];
                pose.rE = [-0.32 - Math.abs(sp) * 1.4, 0, 0.04];
                pose.lS = [0.06 + sp, 0, 0.20 + br];
                pose.lE = [-0.32 - Math.abs(sp) * 1.4, 0, -0.04];
                break;
            }
        }
        return pose;
    }

    _applyArmSmoothing(armRig, sTarget, eTarget, wTarget, curl, isPointing, blend) {
        armRig.shoulderPivot.rotation.x = lerp(armRig.shoulderPivot.rotation.x, sTarget[0], blend);
        armRig.shoulderPivot.rotation.y = lerp(armRig.shoulderPivot.rotation.y, sTarget[1], blend);
        armRig.shoulderPivot.rotation.z = lerp(armRig.shoulderPivot.rotation.z, sTarget[2], blend);

        armRig.elbowPivot.rotation.x = lerp(armRig.elbowPivot.rotation.x, eTarget[0], blend);
        armRig.elbowPivot.rotation.y = lerp(armRig.elbowPivot.rotation.y, eTarget[1], blend);
        armRig.elbowPivot.rotation.z = lerp(armRig.elbowPivot.rotation.z, eTarget[2], blend);

        armRig.wristPivot.rotation.x = lerp(armRig.wristPivot.rotation.x, wTarget[0], blend);
        armRig.wristPivot.rotation.y = lerp(armRig.wristPivot.rotation.y, wTarget[1], blend);
        armRig.wristPivot.rotation.z = lerp(armRig.wristPivot.rotation.z, wTarget[2], blend);

        for (let i = 0; i < armRig.fingers.length; i++) {
            const targetCurl = (isPointing && i === 1) ? 0.04 : curl;
            armRig.fingers[i].rotation.x = lerp(armRig.fingers[i].rotation.x, -targetCurl * 1.15, blend);
        }
    }

    // Driven directly from app.js unified single-RAF renderLoop(now)
    renderFrame(nowMs) {
        if (!this.running || !this.renderer) return;
        const dt = Math.min(0.05, Math.max(0.001, (nowMs - this.lastMs) / 1000));
        this.lastMs = nowMs;
        const tSec = nowMs * 0.001;

        // Automatic 60-FPS QHD Resolution Governor (protects Intel UHD 605 from frame drops)
        this._fpsFrames++;
        const winElapsed = nowMs - this._fpsWindowStartMs;
        if (winElapsed >= 1200) {
            this.fps = Math.round((this._fpsFrames * 1000) / winElapsed);
            this._fpsFrames = 0;
            this._fpsWindowStartMs = nowMs;
            if (this.fps < 50 && this.renderScale > 0.46) {
                this.renderScale = Math.max(0.46, +(this.renderScale - 0.07).toFixed(2));
                this.resize();
            } else if (this.fps >= 59 && this.renderScale < 0.66) {
                this.renderScale = Math.min(0.66, +(this.renderScale + 0.03).toFixed(2));
                this.resize();
            }
        }

        if (this.mode === 'atelier_autonomous' && nowMs >= this.nextTaskSwitchMs) {
            this._pickNextAutonomousTask(nowMs, false);
        }

        if (this.mode === 'front_active' && this.gestureUntilMs > 0 && nowMs > this.gestureUntilMs) {
            this.gesture = 'idle';
            this.gestureUntilMs = 0;
        }

        const isPointingPhoto = (
            this.mode === 'front_active' &&
            (this.gesture === 'point_photo' || this.gesture === 'point_colleagues' || this.gesture === 'point_picture')
        );
        // Step slightly left when showing off the framed wall portrait so the entire frame is unobstructed!
        if (this.mode === 'front_active') {
            this.targetPos.set(isPointingPhoto ? -0.48 : 0, 0, isPointingPhoto ? 1.38 : 1.52);
        }

        // Smooth 3D Locomotion
        const dx = this.targetPos.x - this.pos.x;
        const dz = this.targetPos.z - this.pos.z;
        const dist = Math.hypot(dx, dz);
        const isWalking = dist > 0.045;

        if (isWalking) {
            const step = Math.min(dist, this.moveSpeed * dt);
            this.pos.x += (dx / dist) * step;
            this.pos.z += (dz / dist) * step;
            this.walkPhase += dt * (this.moveSpeed > 2.6 ? 14.5 : 9.2);

            const moveYaw = Math.atan2(dx, dz);
            let diff = moveYaw - this.facingYaw;
            while (diff > Math.PI) diff -= Math.PI * 2;
            while (diff < -Math.PI) diff += Math.PI * 2;
            this.facingYaw += diff * (1 - Math.exp(-dt * 10));

            if (this.rig.broom) this.rig.broom.visible = false;
            if (this.rig.cloth) this.rig.cloth.visible = false;
        } else {
            const desiredYaw = this.mode === 'front_active'
                ? (isPointingPhoto ? 0.36 : (this.lookCur.x * 0.24))
                : (this.taskTargetYaw || 0);
            let diff = desiredYaw - this.facingYaw;
            while (diff > Math.PI) diff -= Math.PI * 2;
            while (diff < -Math.PI) diff += Math.PI * 2;
            this.facingYaw += diff * (1 - Math.exp(-dt * 7.5));

            if (this.mode === 'atelier_autonomous') {
                this.emotion = this.taskEmotion || 'happy';
                if (this.rig.broom) this.rig.broom.visible = (this.currentTask === 'sweep');
                if (this.rig.cloth) this.rig.cloth.visible = (this.currentTask === 'polish');

                if (this.currentTask === 'polish' && Math.random() < 0.28) {
                    const freeSp = this.sparkParticles.find(s => s.life <= 0);
                    if (freeSp) {
                        freeSp.mesh.visible = true;
                        freeSp.mesh.position.set(1.68 + (Math.random() - 0.5) * 0.16, 0.96, -1.65 + (Math.random() - 0.5) * 0.14);
                        freeSp.vx = (Math.random() - 0.5) * 0.7;
                        freeSp.vy = 0.45 + Math.random() * 0.55;
                        freeSp.vz = (Math.random() - 0.5) * 0.7;
                        freeSp.life = 0.42 + Math.random() * 0.25;
                    }
                }
            } else {
                if (this.rig.broom) this.rig.broom.visible = false;
                if (this.rig.cloth) this.rig.cloth.visible = false;
            }
        }

        this.rig.root.position.set(this.pos.x, 0, this.pos.z);
        this.rig.root.rotation.y = this.facingYaw;

        // Legs & Bounce
        const legBlend = 1 - Math.exp(-dt * 11);
        if (isWalking) {
            const sL = Math.sin(this.walkPhase) * 0.62;
            const sR = -sL;
            this.rig.legL.hipPivot.rotation.x = lerp(this.rig.legL.hipPivot.rotation.x, sL, legBlend);
            this.rig.legR.hipPivot.rotation.x = lerp(this.rig.legR.hipPivot.rotation.x, sR, legBlend);
            this.rig.legL.kneePivot.rotation.x = lerp(this.rig.legL.kneePivot.rotation.x, Math.max(0, -sL) * 0.85, legBlend);
            this.rig.legR.kneePivot.rotation.x = lerp(this.rig.legR.kneePivot.rotation.x, Math.max(0, -sR) * 0.85, legBlend);
            this.rig.pelvis.position.y = 0.50 + Math.abs(Math.cos(this.walkPhase)) * 0.045;
        } else {
            const idleBounce = Math.sin(tSec * 2.2) * 0.012 + (this.emotion === 'excited' || this.emotion === 'laughing' ? Math.abs(Math.sin(tSec * 7.5)) * 0.035 : 0);
            this.rig.pelvis.position.y = 0.50 + idleBounce;
            this.rig.legL.hipPivot.rotation.x = lerp(this.rig.legL.hipPivot.rotation.x, 0, legBlend);
            this.rig.legR.hipPivot.rotation.x = lerp(this.rig.legR.hipPivot.rotation.x, 0, legBlend);
            this.rig.legL.kneePivot.rotation.x = lerp(this.rig.legL.kneePivot.rotation.x, 0, legBlend);
            this.rig.legR.kneePivot.rotation.x = lerp(this.rig.legR.kneePivot.rotation.x, 0, legBlend);
        }

        // Smooth Person Gaze Tracking
        const desiredLookX = (this.mode === 'front_active' && this.personDetected)
            ? this.lookTarget.x
            : Math.sin(tSec * 0.55) * 0.12;
        const desiredLookY = (this.mode === 'front_active' && this.personDetected)
            ? this.lookTarget.y
            : Math.cos(tSec * 0.42) * 0.06;

        const lookBlend = 1 - Math.exp(-dt * 5.8);
        this.lookCur.x = lerp(this.lookCur.x, desiredLookX, lookBlend);
        this.lookCur.y = lerp(this.lookCur.y, desiredLookY, lookBlend);

        const preset = EMOTION_PRESETS[this.emotion] || EMOTION_PRESETS.neutral;
        const headBlend = 1 - Math.exp(-dt * 7.5);
        this.rig.torso.rotation.y = lerp(this.rig.torso.rotation.y, this.lookCur.x * 0.16, headBlend);
        this.rig.torso.rotation.z = lerp(this.rig.torso.rotation.z, Math.sin(tSec * 1.6) * 0.02, headBlend);

        const speakNod = this.isSpeaking ? Math.sin(tSec * 9.5) * 0.035 * (0.3 + this.voiceEnergy) : 0;
        const headYawTarget = isPointingPhoto ? -0.18 : (this.lookCur.x * 0.34);
        this.rig.head.rotation.y = lerp(this.rig.head.rotation.y, headYawTarget, headBlend);
        this.rig.head.rotation.x = lerp(this.rig.head.rotation.x, this.lookCur.y * 0.22 + (preset.headPitch || 0) + speakNod, headBlend);
        this.rig.head.rotation.z = lerp(this.rig.head.rotation.z, (preset.headRoll || 0) - this.lookCur.x * 0.07, headBlend);

        if (this.sirenMat) {
            const boost = this.isSpeaking ? (this.voiceEnergy * 0.25) : (this.emotion === 'excited' ? 0.16 : 0);
            this.sirenMat.color.setRGB(Math.min(1, 0.91 + boost), Math.min(1, 0.51 + boost * 0.5), Math.min(1, 0.61 + boost * 0.5));
        }

        // Smooth 3D Chibi Arms
        const activeGest = (this.mode === 'atelier_autonomous' && !isWalking) ? (this.taskGesture || 'idle') : this.gesture;
        const armPose = this._solveArmTargets(activeGest, tSec, isWalking, this.walkPhase);
        const armBlend = 1 - Math.exp(-dt * 8.8);
        this._applyArmSmoothing(this.rig.armR, armPose.rS, armPose.rE, armPose.rW, armPose.rCurl, armPose.pointHand === 'R', armBlend);
        this._applyArmSmoothing(this.rig.armL, armPose.lS, armPose.lE, armPose.lW, armPose.lCurl, armPose.pointHand === 'L', armBlend);

        // Camera Subtle Framing Follow (frames both Funkira & the Golden Group Portrait when pointing at photo!)
        const camTargetX = isPointingPhoto ? 0.08 : (this.mode === 'front_active' ? (this.lookCur.x * 0.08) : (this.pos.x * 0.22));
        const camTargetY = isPointingPhoto ? 1.46 : 1.38;
        const camTargetZ = isPointingPhoto ? 3.82 : (this.mode === 'front_active' ? 3.64 : 3.92);
        this.camera.position.x = lerp(this.camera.position.x, camTargetX, 1 - Math.exp(-dt * 3.2));
        this.camera.position.y = lerp(this.camera.position.y, camTargetY, 1 - Math.exp(-dt * 3.2));
        this.camera.position.z = lerp(this.camera.position.z, camTargetZ, 1 - Math.exp(-dt * 3.2));
        if (isPointingPhoto) {
            this.camera.lookAt(0.08, 1.32, -0.85);
        } else {
            this.camera.lookAt(this.pos.x * 0.32, 1.00, this.pos.z * 0.35);
        }

        // Ambient Animations
        if (this.rig.vitrineGem && (this.worldState.shopLevel || 1) >= 3) {
            this.rig.vitrineGem.rotation.y = tSec * 0.85;
        }
        for (let i = 0; i < this.weatherParticles.length; i++) {
            const wp = this.weatherParticles[i];
            wp.mesh.position.y -= wp.speedY * dt;
            wp.mesh.position.x += Math.sin(tSec * 1.8 + wp.phase) * 0.08 * dt;
            wp.mesh.rotation.z += dt * 1.2;
            if (wp.mesh.position.y < 1.15) {
                wp.mesh.position.y = 2.55;
                wp.mesh.position.x = -2.22 + (Math.random() - 0.5) * 1.38;
            }
        }
        for (let i = 0; i < this.sparkParticles.length; i++) {
            const sp = this.sparkParticles[i];
            if (sp.life > 0) {
                sp.life -= dt;
                sp.mesh.position.x += sp.vx * dt;
                sp.mesh.position.y += sp.vy * dt;
                sp.mesh.position.z += sp.vz * dt;
                sp.vy -= 2.4 * dt;
                if (sp.life <= 0) sp.mesh.visible = false;
            }
        }
        for (let i = this.fallingPetals.length - 1; i >= 0; i--) {
            const p = this.fallingPetals[i];
            p.life -= dt;
            p.mesh.position.y -= p.vy * dt;
            p.mesh.position.x += (p.vx + Math.sin(tSec * 3 + i) * 0.22) * dt;
            p.mesh.rotation.x += p.spin * dt;
            p.mesh.rotation.y += p.spin * 0.7 * dt;
            if (p.life <= 0 || p.mesh.position.y < 0.03) {
                this.scene.remove(p.mesh);
                this.fallingPetals.splice(i, 1);
            }
        }

        this._renderVisorFace(nowMs, dt, false);
        this.renderer.render(this.scene, this.camera);
    }
}

function CylinderGeometrySafe(rt, rb, h, seg) {
    return new THREE.CylinderGeometry(rt, rb, h, seg);
}

window.Funkira3D = new FunkiraAtelierEngine();
