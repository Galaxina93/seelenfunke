import * as THREE from './vendor/three.module.min.js';
import { RoundedBoxGeometry } from './vendor/RoundedBoxGeometry.js';

// ============================================================================
// FUNKIRA 3D 2-STORY HOUSE & SEELENFUNKEN ATELIER ENGINE (LUXURY 60-FPS EDITION)
// Exact 1:1 Architectural Implementation of Alina's 8.50m x 14.00m Floorplan:
// - Erdgeschoss (y = 0.00m): Schlafzimmer (16.57m²), Büro (10.25m²), Wohn-/Esszimmer (25.88m²),
//   Flur (1.26m), Trainingsraum (9.86m² mit Hantelbank), Großes Bad (Fliesen), Heizungsraum (5.68m²),
//   Diele (13.96m² mit Eingang & 3D-Treppe), Gäste-WC (1.19m) & Küche (14.97m² mit Insel)
// - Obergeschoss (y = 3.10m mit 1.25m Dachschrägen links & rechts, 6.00m Stehhöhe):
//   Nord-Studio (2 Schreibtische + Schranksystem), OG-Bad (4.11m, Fliesen + Dachfenster),
//   West-Galerie (2 Heizkörper), Treppe + schwarzes Geländer & Seelenfunken Atelier Werkstatt
// - Zero dynamic lights (Studio MatCaps + Pre-baked Floor/Wall Lightmaps = Locked 60 FPS on UHD 605)
// - Cross-floor Dijkstra Pathfinding (EG <-> Treppe <-> OG) + Hard Circle-vs-AABB Collision
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

const OG_Y = 3.10;
const HOUSE_W = 8.50;
const HOUSE_L = 14.00;

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

// Pentagonal Gable Wall Shape matching Obergeschoss Dachschrägen (width 8.50m, eaves +0.28m, Kniestock ±3.00m @ +1.32m, peak 0m @ +3.36m)
function createGableWallShape() {
    const s = new THREE.Shape();
    s.moveTo(-4.24, 0.0);
    s.lineTo( 4.24, 0.0);
    s.lineTo( 4.24, 0.28);
    s.lineTo( 3.00, 1.32);
    s.lineTo( 0.00, 3.36);
    s.lineTo(-3.00, 1.32);
    s.lineTo(-4.24, 0.28);
    s.closePath();
    return s;
}

// ============================================================================
// ARCHITECTURAL FLOOR TEXTURES (1:1 VINYLBODEN + BAD-FLIESEN + EAVES + AO)
// ============================================================================
function makeEgFloorTexture() {
    const W = 512, H = 768;
    const c = document.createElement('canvas');
    c.width = W;
    c.height = H;
    const ctx = c.getContext('2d');

    // Base warm honey-oak Vinylboden ("Alle Räume außer großes Badezimmer")
    ctx.fillStyle = '#c8af8e';
    ctx.fillRect(0, 0, W, H);

    const plankRows = 48;
    const rowH = H / plankRows;
    for (let r = 0; r < plankRows; r++) {
        const y = r * rowH;
        const off = (r % 3) * 42;
        for (let col = -1; col < 9; col++) {
            const x = col * 72 + off;
            const v = ((r * 13 + col * 19) % 14) - 7;
            ctx.fillStyle = `rgb(${196 + v}, ${170 + Math.round(v * 0.8)}, ${138 + Math.round(v * 0.6)})`;
            ctx.fillRect(x + 0.7, y + 0.7, 70.6, rowH - 1.2);
            ctx.fillStyle = 'rgba(255, 245, 228, 0.10)';
            ctx.fillRect(x + 4, y + rowH * 0.35, 62, 1.0);
        }
    }

    const xToPx = (wx) => ((wx + 4.25) / 8.50) * W;
    const zToPy = (wz) => ((wz + 7.00) / 14.00) * H;

    // Großes Badezimmer (x = +1.19 .. +4.16, z = -4.48 .. -2.05) -> Light Grey Fliesen!
    const bx0 = xToPx(1.19), bx1 = xToPx(4.20);
    const bz0 = zToPy(-4.48), bz1 = zToPy(-2.05);
    ctx.fillStyle = '#cfd3d8';
    ctx.fillRect(bx0, bz0, bx1 - bx0, bz1 - bz0);
    ctx.strokeStyle = 'rgba(130, 138, 148, 0.52)';
    ctx.lineWidth = 1.2;
    for (let tx = bx0; tx <= bx1; tx += 18) {
        ctx.beginPath(); ctx.moveTo(tx, bz0); ctx.lineTo(tx, bz1); ctx.stroke();
    }
    for (let ty = bz0; ty <= bz1; ty += 18) {
        ctx.beginPath(); ctx.moveTo(bx0, ty); ctx.lineTo(bx1, ty); ctx.stroke();
    }

    // Soft Ambient Occlusion shadows under EG furniture
    const paintAO = (wx, wz, rx, rz, alpha = 0.30) => {
        const cx = xToPx(wx), cy = zToPy(wz);
        const prx = (rx / 8.50) * W;
        const pry = (rz / 14.00) * H;
        ctx.save();
        ctx.translate(cx, cy);
        ctx.scale(1, pry / Math.max(1, prx));
        const g = ctx.createRadialGradient(0, 0, prx * 0.2, 0, 0, prx);
        g.addColorStop(0, `rgba(28, 20, 14, ${alpha})`);
        g.addColorStop(1, 'rgba(28, 20, 14, 0)');
        ctx.fillStyle = g;
        ctx.beginPath(); ctx.arc(0, 0, prx, 0, Math.PI * 2); ctx.fill();
        ctx.restore();
    };
    paintAO(-3.00, -4.75, 1.25, 1.15, 0.34); // Schlafzimmer Bett
    paintAO( 2.48, -5.72, 1.15, 0.65, 0.28); // Hantelbank
    paintAO(-2.25, -1.30, 1.05, 1.05, 0.28); // Büro Schreibtische
    paintAO(-2.55,  2.55, 1.55, 1.45, 0.32); // Wohnzimmer Ecksofa
    paintAO(-2.42,  5.35, 1.35, 0.85, 0.30); // Esstisch
    paintAO( 0.82,  4.45, 0.72, 1.20, 0.30); // Kücheninsel

    return configureFastTexture(new THREE.CanvasTexture(c));
}

function makeOgFloorTexture() {
    const W = 512, H = 768;
    const c = document.createElement('canvas');
    c.width = W;
    c.height = H;
    const ctx = c.getContext('2d');

    const xToPx = (wx) => ((wx + 4.25) / 8.50) * W;
    const zToPy = (wz) => ((wz + 7.00) / 14.00) * H;

    // Center 6.00m warm Vinylboden (North Studio & Gallery)
    ctx.fillStyle = '#d3bc9d';
    ctx.fillRect(0, 0, W, H);
    const plankRows = 48;
    const rowH = H / plankRows;
    for (let r = 0; r < plankRows; r++) {
        const y = r * rowH;
        const off = (r % 3) * 40;
        for (let col = -1; col < 9; col++) {
            const x = col * 72 + off;
            const v = ((r * 11 + col * 17) % 12) - 6;
            ctx.fillStyle = `rgb(${205 + v}, ${180 + Math.round(v * 0.75)}, ${148 + Math.round(v * 0.55)})`;
            ctx.fillRect(x + 0.7, y + 0.7, 70.6, rowH - 1.2);
        }
    }

    // South Seelenfunken Atelier Zone (z = +2.28 .. +7.00, x = -3.00 .. +3.00): Luxury Dark Walnut Boutique Parquet!
    const ax0 = xToPx(-3.00), ax1 = xToPx(3.00);
    const az0 = zToPy(2.28),  az1 = H;
    ctx.fillStyle = '#2a1c13';
    ctx.fillRect(ax0, az0, ax1 - ax0, az1 - az0);
    for (let r = Math.floor(az0 / rowH); r < plankRows; r++) {
        const y = r * rowH;
        const off = (r % 3) * 34;
        for (let col = 0; col < 8; col++) {
            const x = ax0 + col * 58 + off;
            if (x > ax1) continue;
            const v = ((r * 13 + col * 17) % 14) - 7;
            ctx.fillStyle = `rgb(${58 + v}, ${39 + Math.round(v * 0.65)}, ${26 + Math.round(v * 0.45)})`;
            ctx.fillRect(x, y + 0.6, Math.min(56.8, ax1 - x), rowH - 1.2);
        }
    }
    // Gold transition threshold strip between OG Gallery & Seelenfunken Atelier
    ctx.fillStyle = '#C5A059';
    ctx.fillRect(ax0, az0 - 2, ax1 - ax0, 4);

    // OG-Bad (x = -0.06 .. +3.00, z = -4.21 .. -0.10) -> Light Grey Fliesen!
    const bx0 = xToPx(-0.06), bx1 = xToPx(3.00);
    const bz0 = zToPy(-4.21), bz1 = zToPy(-0.10);
    ctx.fillStyle = '#d2d6db';
    ctx.fillRect(bx0, bz0, bx1 - bx0, bz1 - bz0);
    ctx.strokeStyle = 'rgba(130, 138, 148, 0.50)';
    ctx.lineWidth = 1.2;
    for (let tx = bx0; tx <= bx1; tx += 18) {
        ctx.beginPath(); ctx.moveTo(tx, bz0); ctx.lineTo(tx, bz1); ctx.stroke();
    }
    for (let ty = bz0; ty <= bz1; ty += 18) {
        ctx.beginPath(); ctx.moveTo(bx0, ty); ctx.lineTo(bx1, ty); ctx.stroke();
    }

    // 1.25m Left & Right Dachschrägen Eaves Strips (matching Screenshot 2 shaded 1.25m strips!)
    const eaveLeftW = xToPx(-3.00);
    const eaveRightX = xToPx(3.00);
    ctx.fillStyle = '#9e886d';
    ctx.fillRect(0, 0, eaveLeftW, H);
    ctx.fillRect(eaveRightX, 0, W - eaveRightX, H);
    ctx.strokeStyle = 'rgba(65, 48, 32, 0.25)';
    for (let y = 0; y < H; y += 10) {
        ctx.beginPath();
        ctx.moveTo(0, y); ctx.lineTo(eaveLeftW, y + 6);
        ctx.moveTo(eaveRightX, y); ctx.lineTo(W, y + 6);
        ctx.stroke();
    }

    return configureFastTexture(new THREE.CanvasTexture(c));
}

// Obsidian & 24K Gold Paneled Boutique Atelier Gable Wall Texture
function makeBakedWallTexture() {
    const S = 512;
    const c = document.createElement('canvas');
    c.width = S;
    c.height = S;
    const ctx = c.getContext('2d');

    const vg = ctx.createLinearGradient(0, 0, 0, S);
    vg.addColorStop(0, '#121018');
    vg.addColorStop(0.68, '#1c1824');
    vg.addColorStop(0.70, '#2b1e15');
    vg.addColorStop(1, '#1e140e');
    ctx.fillStyle = vg;
    ctx.fillRect(0, 0, S, S);

    ctx.strokeStyle = 'rgba(197, 160, 89, 0.28)';
    ctx.lineWidth = 2;
    const panelCount = 5;
    const pw = S / panelCount;
    for (let i = 0; i < panelCount; i++) {
        ctx.strokeRect(i * pw + 12, 26, pw - 24, S * 0.60);
        ctx.fillStyle = 'rgba(66, 44, 28, 0.42)';
        ctx.fillRect(i * pw + 10, S * 0.73, pw - 20, S * 0.22);
        ctx.strokeStyle = 'rgba(197, 160, 89, 0.34)';
        ctx.strokeRect(i * pw + 12, S * 0.74, pw - 24, S * 0.20);
        ctx.strokeStyle = 'rgba(197, 160, 89, 0.28)';
    }

    const dado = ctx.createLinearGradient(0, S * 0.69, 0, S * 0.715);
    dado.addColorStop(0, '#8f6d32');
    dado.addColorStop(0.5, '#ebd08e');
    dado.addColorStop(1, '#6e5222');
    ctx.fillStyle = dado;
    ctx.fillRect(0, S * 0.695, S, 7);

    const tex = configureFastTexture(new THREE.CanvasTexture(c));
    tex.wrapS = THREE.RepeatWrapping;
    tex.wrapT = THREE.ClampToEdgeWrapping;
    return tex;
}

function makeBakedRugTexture() {
    const S = 512;
    const c = document.createElement('canvas');
    c.width = S;
    c.height = S;
    const ctx = c.getContext('2d');
    ctx.clearRect(0, 0, S, S);

    const cx = S * 0.5, cy = S * 0.5;
    const sh = ctx.createRadialGradient(cx, cy, S * 0.42, cx, cy, S * 0.495);
    sh.addColorStop(0, 'rgba(0,0,0,0.5)');
    sh.addColorStop(1, 'rgba(0,0,0,0)');
    ctx.fillStyle = sh;
    ctx.beginPath(); ctx.arc(cx, cy, S * 0.495, 0, Math.PI * 2); ctx.fill();

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
    ctx.beginPath(); ctx.arc(cx, cy, S * 0.465, 0, Math.PI * 2); ctx.fill();

    ctx.strokeStyle = 'rgba(235, 202, 130, 0.42)';
    ctx.lineWidth = 2.5;
    ctx.beginPath(); ctx.arc(cx, cy, S * 0.37, 0, Math.PI * 2); ctx.stroke();

    ctx.strokeStyle = 'rgba(197, 160, 89, 0.28)';
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

function paintScenicWindowCanvas(canvas, season, isLateNight, occasion = 'none') {
    const W = canvas.width, H = canvas.height;
    const ctx = canvas.getContext('2d');
    const g = ctx.createLinearGradient(0, 0, 0, H);
    const isFireworkOccasion = (occasion === 'silvester' || occasion === 'new_year');
    const nightSky = isLateNight || isFireworkOccasion;

    if (nightSky) {
        g.addColorStop(0, '#090d1d');
        g.addColorStop(0.6, '#151f3c');
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
        g.addColorStop(0, '#1f5c94');
        g.addColorStop(0.68, '#65b6e6');
        g.addColorStop(1, '#fdf0c2');
    } else {
        g.addColorStop(0, '#2c223e');
        g.addColorStop(0.48, '#7a4557');
        g.addColorStop(0.82, '#d98a4e');
        g.addColorStop(1, '#ffdca2');
    }
    ctx.fillStyle = g;
    ctx.fillRect(0, 0, W, H);

    if (nightSky) {
        ctx.fillStyle = 'rgba(255, 245, 210, 0.85)';
        for (let i = 0; i < 28; i++) {
            const sx = ((i * 73) % (W - 20)) + 10;
            const sy = ((i * 41) % (H * 0.65)) + 12;
            ctx.fillRect(sx, sy, (i % 3 === 0) ? 2.5 : 1.5, (i % 3 === 0) ? 2.5 : 1.5);
        }
        ctx.fillStyle = occasion === 'halloween' ? '#ff9d3b' : '#ffe9ad';
        ctx.beginPath(); ctx.arc(W * 0.68, H * 0.28, 22, 0, Math.PI * 2); ctx.fill();
        if (occasion !== 'halloween') {
            ctx.fillStyle = '#111933';
            ctx.beginPath(); ctx.arc(W * 0.68 + 8, H * 0.28 - 4, 19, 0, Math.PI * 2); ctx.fill();
        }
        if (isFireworkOccasion) {
            const bursts = [
                { cx: W * 0.28, cy: H * 0.34, r: 34, col: '#ffd978' },
                { cx: W * 0.72, cy: H * 0.44, r: 28, col: '#ff7aa8' },
                { cx: W * 0.48, cy: H * 0.22, r: 24, col: '#7ce8ff' }
            ];
            for (const b of bursts) {
                ctx.strokeStyle = b.col;
                ctx.lineWidth = 2.0;
                for (let k = 0; k < 14; k++) {
                    const a = (k / 14) * Math.PI * 2;
                    ctx.beginPath();
                    ctx.moveTo(b.cx + Math.cos(a) * 4, b.cy + Math.sin(a) * 4);
                    ctx.lineTo(b.cx + Math.cos(a) * b.r, b.cy + Math.sin(a) * b.r);
                    ctx.stroke();
                }
            }
        }
    } else {
        const sunR = season === 'summer' ? 115 : 95;
        const sg = ctx.createRadialGradient(W * 0.62, H * 0.56, 8, W * 0.62, H * 0.56, sunR);
        sg.addColorStop(0, season === 'summer' ? 'rgba(255, 249, 214, 0.95)' : 'rgba(255, 244, 204, 0.85)');
        sg.addColorStop(1, 'rgba(255, 220, 150, 0)');
        ctx.fillStyle = sg;
        ctx.fillRect(0, 0, W, H);
    }

    if (occasion === 'advent' || occasion === 'christmas' || occasion === 'nikolaus') {
        ctx.fillStyle = '#fff0b8';
        ctx.beginPath();
        ctx.arc(W * 0.26, H * 0.24, 5.5, 0, Math.PI * 2);
        ctx.fill();
    }

    ctx.fillStyle = nightSky ? '#090c18' : (season === 'winter' ? '#dbe6f2' : '#231924');
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

function paintGardenGroundCanvas(canvas, season, occasion = 'none') {
    const W = canvas.width, H = canvas.height;
    const ctx = canvas.getContext('2d');

    if (season === 'winter') {
        const g = ctx.createLinearGradient(0, 0, 0, H);
        g.addColorStop(0, '#e8eef5');
        g.addColorStop(0.5, '#f2f6fb');
        g.addColorStop(1, '#dde6f0');
        ctx.fillStyle = g;
        ctx.fillRect(0, 0, W, H);

        // Soft snowdrifts & subtle blue-silver winter contours
        for (let i = 0; i < 36; i++) {
            const sx = ((i * 97) % W);
            const sy = ((i * 61) % H);
            const rg = ctx.createRadialGradient(sx, sy, 4, sx, sy, 44 + (i % 3) * 18);
            rg.addColorStop(0, 'rgba(255, 255, 255, 0.75)');
            rg.addColorStop(1, 'rgba(205, 220, 238, 0)');
            ctx.fillStyle = rg;
            ctx.beginPath(); ctx.arc(sx, sy, 62, 0, Math.PI * 2); ctx.fill();
        }
    } else {
        const basePalette = season === 'summer'
            ? ['#3b7d3c', '#478f44', '#336e35']
            : (season === 'spring'
                ? ['#3f8646', '#4e9c52', '#37763d']
                : ['#4d6e38', '#637a3a', '#425e31']);
        const g = ctx.createLinearGradient(0, 0, W, H);
        g.addColorStop(0, basePalette[0]);
        g.addColorStop(0.52, basePalette[1]);
        g.addColorStop(1, basePalette[2]);
        ctx.fillStyle = g;
        ctx.fillRect(0, 0, W, H);

        // Subtle mowed lawn stripes for manicured Seelenfunke garden look
        const stripes = 14;
        const stripeW = W / stripes;
        for (let s = 0; s < stripes; s++) {
            if (s % 2 === 0) {
                ctx.fillStyle = 'rgba(255, 255, 235, 0.045)';
                ctx.fillRect(s * stripeW, 0, stripeW, H);
            }
        }

        // Seasonal lawn accents: spring daisies, summer clover/buttercups, autumn fallen leaves
        for (let i = 0; i < 110; i++) {
            const px = ((i * 79 + 17) % (W - 16)) + 8;
            const py = ((i * 53 + 29) % (H - 16)) + 8;
            if (season === 'spring' || (season === 'summer' && i % 2 === 0)) {
                ctx.fillStyle = (i % 5 === 0) ? '#ffe47a' : '#fff9f2';
                ctx.beginPath(); ctx.arc(px, py, (i % 4 === 0) ? 2.6 : 1.9, 0, Math.PI * 2); ctx.fill();
            } else if (season === 'autumn') {
                const leafCols = ['#d97828', '#c94a29', '#e3a636', '#9e3b24'];
                ctx.fillStyle = leafCols[i % leafCols.length];
                ctx.beginPath();
                ctx.ellipse(px, py, 3.6, 2.1, (i * 0.7) % 3.14, 0, Math.PI * 2);
                ctx.fill();
            }
        }
    }

    // Perimeter soil border shadow along fence
    ctx.strokeStyle = season === 'winter' ? 'rgba(175, 192, 212, 0.55)' : 'rgba(34, 46, 24, 0.42)';
    ctx.lineWidth = 14;
    ctx.strokeRect(7, 7, W - 14, H - 14);
}

function paintTerraceDeckCanvas(canvas, season) {
    const W = canvas.width, H = canvas.height;
    const ctx = canvas.getContext('2d');

    ctx.fillStyle = season === 'winter' ? '#7a6858' : (season === 'summer' ? '#9c6e46' : '#8a603d');
    ctx.fillRect(0, 0, W, H);

    const planks = 22;
    const ph = H / planks;
    for (let r = 0; r < planks; r++) {
        const y = r * ph;
        const v = ((r * 17) % 14) - 7;
        const baseR = (season === 'winter' ? 132 : 158) + v;
        const baseG = (season === 'winter' ? 114 : 112) + Math.round(v * 0.8);
        const baseB = (season === 'winter' ? 98 : 72) + Math.round(v * 0.6);
        ctx.fillStyle = `rgb(${baseR}, ${baseG}, ${baseB})`;
        ctx.fillRect(2, y + 1.5, W - 4, ph - 2.8);

        ctx.fillStyle = 'rgba(28, 18, 11, 0.52)';
        ctx.fillRect(0, y, W, 1.5);

        if (season === 'winter') {
            ctx.fillStyle = 'rgba(238, 245, 255, 0.34)';
            ctx.fillRect(4, y + 1.5, W - 8, 2.2);
        } else if (season === 'summer') {
            ctx.fillStyle = 'rgba(255, 236, 196, 0.12)';
            ctx.fillRect(4, y + 2.0, W - 8, 2.0);
        }
    }

    if (season === 'winter') {
        // Frosted snow corners on terrace deck
        const sg = ctx.createRadialGradient(W * 0.12, H * 0.86, 8, W * 0.12, H * 0.86, W * 0.36);
        sg.addColorStop(0, 'rgba(244, 248, 255, 0.82)');
        sg.addColorStop(1, 'rgba(244, 248, 255, 0)');
        ctx.fillStyle = sg;
        ctx.fillRect(0, 0, W, H);
    }

    ctx.strokeStyle = '#C5A059';
    ctx.lineWidth = 5;
    ctx.strokeRect(3, 3, W - 6, H - 6);
}

function makeSunbeamTexture() {
    const c = document.createElement('canvas');
    c.width = 128;
    c.height = 256;
    const ctx = c.getContext('2d');
    const g = ctx.createLinearGradient(0, 0, 128, 256);
    g.addColorStop(0, 'rgba(255, 226, 158, 0.18)');
    g.addColorStop(0.55, 'rgba(255, 214, 135, 0.06)');
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

function makeIlluminatedBrandSignTexture() {
    const W = 512, H = 224;
    const c = document.createElement('canvas');
    c.width = W;
    c.height = H;
    const ctx = c.getContext('2d');

    const bg = ctx.createLinearGradient(0, 0, W, H);
    bg.addColorStop(0, '#121015');
    bg.addColorStop(0.5, '#1b171f');
    bg.addColorStop(1, '#110f14');
    ctx.fillStyle = bg;
    ctx.fillRect(0, 0, W, H);

    const innerGlow = ctx.createRadialGradient(W * 0.5, H * 0.50, 14, W * 0.5, H * 0.50, W * 0.48);
    innerGlow.addColorStop(0, 'rgba(255, 222, 145, 0.24)');
    innerGlow.addColorStop(0.6, 'rgba(197, 160, 89, 0.08)');
    innerGlow.addColorStop(1, 'rgba(0, 0, 0, 0)');
    ctx.fillStyle = innerGlow;
    ctx.fillRect(0, 0, W, H);

    ctx.strokeStyle = '#F5DC9B';
    ctx.lineWidth = 5;
    ctx.strokeRect(8, 8, W - 16, H - 16);
    ctx.strokeStyle = 'rgba(197, 160, 89, 0.55)';
    ctx.lineWidth = 2;
    ctx.strokeRect(18, 18, W - 36, H - 36);

    ctx.save();
    ctx.translate(W * 0.5, 48);
    ctx.shadowColor = 'rgba(255, 218, 130, 0.95)';
    ctx.shadowBlur = 16;
    ctx.fillStyle = '#FFF5D6';
    ctx.beginPath();
    for (let i = 0; i < 8; i++) {
        const rad = (i % 2 === 0) ? 18 : 5.5;
        const ang = (i * Math.PI) / 4;
        const px = Math.cos(ang) * rad;
        const py = Math.sin(ang) * rad;
        if (i === 0) ctx.moveTo(px, py); else ctx.lineTo(px, py);
    }
    ctx.closePath();
    ctx.fill();
    ctx.restore();

    ctx.save();
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.shadowColor = 'rgba(255, 212, 118, 0.95)';
    ctx.shadowBlur = 20;
    ctx.fillStyle = '#FFF6DC';
    ctx.font = '800 47px Georgia, "Times New Roman", serif';
    ctx.fillText('Mein-Seelenfunke', W * 0.5, 118);
    ctx.shadowBlur = 8;
    ctx.fillStyle = '#FCE29C';
    ctx.fillText('Mein-Seelenfunke', W * 0.5, 118);
    ctx.restore();

    ctx.strokeStyle = 'rgba(242, 210, 139, 0.65)';
    ctx.lineWidth = 2;
    ctx.beginPath();
    ctx.moveTo(118, 154);
    ctx.lineTo(W - 118, 154);
    ctx.stroke();

    ctx.textAlign = 'center';
    ctx.fillStyle = '#DFBC74';
    ctx.font = '700 16px "Segoe UI", sans-serif';
    ctx.fillText('MANUFAKTUR GIFHORN · UNIKATE', W * 0.5, 180);

    return configureFastTexture(new THREE.CanvasTexture(c));
}

function makeCozyLivingRugTexture() {
    const S = 256;
    const c = document.createElement('canvas');
    c.width = S;
    c.height = S;
    const ctx = c.getContext('2d');
    ctx.fillStyle = '#ded3c3';
    ctx.fillRect(0, 0, S, S);
    for (let y = 0; y < S; y += 6) {
        ctx.fillStyle = (y % 12 === 0) ? 'rgba(255,250,242,0.32)' : 'rgba(165,142,112,0.16)';
        ctx.fillRect(0, y, S, 3);
    }
    ctx.strokeStyle = '#C5A059';
    ctx.lineWidth = 6;
    ctx.strokeRect(12, 12, S - 24, S - 24);
    return configureFastTexture(new THREE.CanvasTexture(c));
}

function makeSmartphoneScreenTexture(callerName = 'Papa') {
    const W = 128, H = 256;
    const c = document.createElement('canvas');
    c.width = W;
    c.height = H;
    const ctx = c.getContext('2d');

    const bg = ctx.createLinearGradient(0, 0, 0, H);
    bg.addColorStop(0, '#14111b');
    bg.addColorStop(0.55, '#1d1726');
    bg.addColorStop(1, '#100d15');
    ctx.fillStyle = bg;
    ctx.fillRect(0, 0, W, H);

    ctx.strokeStyle = 'rgba(197, 160, 89, 0.55)';
    ctx.lineWidth = 3;
    ctx.strokeRect(5, 5, W - 10, H - 10);

    // Golden Seelenfunke Avatar Circle
    ctx.fillStyle = 'rgba(197, 160, 89, 0.18)';
    ctx.beginPath();
    ctx.arc(W * 0.5, 68, 26, 0, Math.PI * 2);
    ctx.fill();
    ctx.strokeStyle = '#C5A059';
    ctx.lineWidth = 2.2;
    ctx.stroke();

    ctx.fillStyle = '#FCE29C';
    ctx.font = '700 24px Georgia, serif';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText('✦', W * 0.5, 69);

    const safeLabel = String(callerName || 'Anruf').slice(0, 14);
    ctx.fillStyle = '#FFF8EB';
    ctx.font = '700 16px "Segoe UI", sans-serif';
    ctx.fillText(safeLabel, W * 0.5, 118);

    ctx.fillStyle = '#C5A059';
    ctx.font = '600 11px "Segoe UI", sans-serif';
    ctx.fillText('TELEFONAT AKTIV', W * 0.5, 140);

    // Hangup red circle icon at bottom
    ctx.fillStyle = '#d93848';
    ctx.beginPath();
    ctx.arc(W * 0.5, 206, 17, 0, Math.PI * 2);
    ctx.fill();
    ctx.fillStyle = '#ffffff';
    ctx.font = '700 14px sans-serif';
    ctx.fillText('✆', W * 0.5, 207);

    return configureFastTexture(new THREE.CanvasTexture(c));
}

function makeSeelenfunkePlaqueTexture(mainText = 'MEIN SEELENFUNKE', subText = '', theme = 'obsidian_gold') {
    const W = 256, H = 88;
    const c = document.createElement('canvas');
    c.width = W;
    c.height = H;
    const ctx = c.getContext('2d');

    if (theme === 'gold_plate') {
        const g = ctx.createLinearGradient(0, 0, W, H);
        g.addColorStop(0, '#ebd08e');
        g.addColorStop(0.5, '#c89f4d');
        g.addColorStop(1, '#f5df9f');
        ctx.fillStyle = g;
        ctx.fillRect(0, 0, W, H);
        ctx.strokeStyle = '#18131d';
        ctx.lineWidth = 4;
        ctx.strokeRect(4, 4, W - 8, H - 8);
        ctx.lineWidth = 1.5;
        ctx.strokeRect(10, 10, W - 20, H - 20);
        ctx.fillStyle = '#16121b';
    } else {
        const g = ctx.createLinearGradient(0, 0, 0, H);
        g.addColorStop(0, '#1c1722');
        g.addColorStop(0.5, '#120f17');
        g.addColorStop(1, '#1a1520');
        ctx.fillStyle = g;
        ctx.fillRect(0, 0, W, H);
        ctx.strokeStyle = '#C5A059';
        ctx.lineWidth = 4;
        ctx.strokeRect(4, 4, W - 8, H - 8);
        ctx.strokeStyle = 'rgba(247, 216, 139, 0.55)';
        ctx.lineWidth = 1.5;
        ctx.strokeRect(10, 10, W - 20, H - 20);
        ctx.fillStyle = '#F7DF9C';
    }

    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    if (subText) {
        ctx.font = '800 20px Georgia, "Times New Roman", serif';
        ctx.fillText(`✦ ${mainText} ✦`, W * 0.5, 34);
        ctx.font = '700 13px "Segoe UI", sans-serif';
        ctx.fillStyle = theme === 'gold_plate' ? '#2c2218' : '#C5A059';
        ctx.fillText(subText, W * 0.5, 62);
    } else {
        ctx.font = '800 22px Georgia, "Times New Roman", serif';
        ctx.fillText(`✦ ${mainText} ✦`, W * 0.5, H * 0.5 + 1);
    }

    return configureFastTexture(new THREE.CanvasTexture(c));
}

function makeSeelenfunkeArtCanvasTexture(motif = 'spark_mandala', subLabel = 'MANUFAKTUR GIFHORN') {
    const W = 256, H = 192;
    const c = document.createElement('canvas');
    c.width = W;
    c.height = H;
    const ctx = c.getContext('2d');

    const bg = ctx.createRadialGradient(W * 0.5, H * 0.44, 10, W * 0.5, H * 0.5, 150);
    bg.addColorStop(0, '#261e29');
    bg.addColorStop(0.65, '#17131c');
    bg.addColorStop(1, '#0f0c13');
    ctx.fillStyle = bg;
    ctx.fillRect(0, 0, W, H);

    // Double 24K Gold Passepartout Frame
    ctx.strokeStyle = '#C5A059';
    ctx.lineWidth = 3.5;
    ctx.strokeRect(10, 10, W - 20, H - 20);
    ctx.strokeStyle = 'rgba(247, 222, 156, 0.45)';
    ctx.lineWidth = 1.2;
    ctx.strokeRect(16, 16, W - 32, H - 32);

    const cx = W * 0.5;
    const cy = 78;
    ctx.save();
    ctx.strokeStyle = '#E5C170';
    ctx.fillStyle = '#F7DF9C';

    if (motif === 'spark_mandala') {
        for (let r = 0; r < 12; r++) {
            const a = (r / 12) * Math.PI * 2;
            ctx.lineWidth = (r % 3 === 0) ? 2.4 : 1.2;
            ctx.beginPath();
            ctx.moveTo(cx + Math.cos(a) * 12, cy + Math.sin(a) * 12);
            ctx.lineTo(cx + Math.cos(a) * ((r % 2 === 0) ? 42 : 28), cy + Math.sin(a) * ((r % 2 === 0) ? 42 : 28));
            ctx.stroke();
        }
        ctx.lineWidth = 2.0;
        ctx.beginPath();
        ctx.arc(cx, cy, 30, 0, Math.PI * 2);
        ctx.stroke();
        ctx.font = '700 26px Georgia, serif';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText('✦', cx, cy + 1);
    } else if (motif === 'botanical_gold') {
        ctx.lineWidth = 2.4;
        ctx.beginPath();
        ctx.moveTo(cx, cy + 36);
        ctx.quadraticCurveTo(cx - 8, cy, cx + 6, cy - 36);
        ctx.stroke();
        for (let l = -2; l <= 2; l++) {
            const ly = cy + l * 13;
            ctx.beginPath();
            ctx.ellipse(cx - 14, ly, 12, 5, -0.45, 0, Math.PI * 2);
            ctx.fill();
            ctx.beginPath();
            ctx.ellipse(cx + 15, ly - 5, 12, 5, 0.45, 0, Math.PI * 2);
            ctx.fill();
        }
    } else if (motif === 'moon_sparks') {
        ctx.beginPath();
        ctx.arc(cx - 4, cy, 32, 0.45, Math.PI * 2 - 0.45);
        ctx.arc(cx + 10, cy - 6, 26, Math.PI * 2 - 0.55, 0.55, true);
        ctx.closePath();
        ctx.fill();
        ctx.font = '700 18px Georgia, serif';
        ctx.fillText('✦', cx + 22, cy - 12);
        ctx.font = '700 13px Georgia, serif';
        ctx.fillText('✦', cx + 34, cy + 8);
    } else if (motif === 'golden_heart') {
        ctx.lineWidth = 3.0;
        ctx.beginPath();
        ctx.moveTo(cx, cy + 28);
        ctx.bezierCurveTo(cx - 42, cy + 2, cx - 36, cy - 32, cx, cy - 12);
        ctx.bezierCurveTo(cx + 36, cy - 32, cx + 42, cy + 2, cx, cy + 28);
        ctx.stroke();
        ctx.font = '700 22px Georgia, serif';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText('✦', cx, cy + 2);
    } else {
        // crest_manufaktur
        ctx.lineWidth = 2.4;
        ctx.beginPath();
        ctx.arc(cx, cy, 34, 0, Math.PI * 2);
        ctx.stroke();
        ctx.font = '800 26px Georgia, serif';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText('SF', cx, cy + 2);
    }
    ctx.restore();

    // Crisp Golden Signature on Artwork
    ctx.textAlign = 'center';
    ctx.fillStyle = '#F7DF9C';
    ctx.font = '800 17px Georgia, "Times New Roman", serif';
    ctx.fillText('MEIN SEELENFUNKE', cx, 146);
    ctx.fillStyle = '#C5A059';
    ctx.font = '700 11px "Segoe UI", sans-serif';
    ctx.fillText(subLabel, cx, 165);

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
        this.renderScale = 0.60;
        this.fps = 60;
        this._fpsFrames = 0;
        this._fpsWindowStartMs = 0;

        // State synced from app.js
        this.mode = 'front_active'; // 'front_active' | 'atelier_autonomous'
        this.emotion = 'happy';
        this.emotionStartedMs = 0;
        this.emotionUntilMs = 0;
        this.gesture = 'wave';
        this.gestureUntilMs = 0;
        this.lookTarget = { x: 0, y: 0 };
        this.lookCur = { x: 0, y: 0 };
        this.personDetected = false;
        this.voiceEnergy = 0;
        this.isSpeaking = false;
        this.phoneCallActive = false;
        this.phoneCallerLabel = '';
        this.phoneScreenMat = null;

        this.worldState = {
            shopLevel: 2,
            season: 'autumn',
            occasion: 'none',
            rosesCount: 0,
            isLateNight: false
        };

        // Pre-baked Zero-GC Visor Canvas (384 x 240)
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

        // Scenic Window Canvas shared across all 12 EG + OG windows + Outdoor Garden & Terrace Canvases
        this.windowCanvas = document.createElement('canvas');
        this.windowCanvas.width = 256;
        this.windowCanvas.height = 256;
        this.windowTexture = null;
        this.gardenGroundCanvas = document.createElement('canvas');
        this.gardenGroundCanvas.width = 512;
        this.gardenGroundCanvas.height = 384;
        this.gardenGroundTexture = null;
        this.terraceDeckCanvas = document.createElement('canvas');
        this.terraceDeckCanvas.width = 384;
        this.terraceDeckCanvas.height = 256;
        this.terraceDeckTexture = null;
        this.sunbeamMesh = null;
        this.sunbeamMeshes = [];

        this.faceCur = { ...EMOTION_PRESETS.happy };
        this.blinkVal = 0;
        this.nextBlinkMs = performance.now() + 2400;

        // Groups & Materials
        this.mats = {};
        this.rig = {};
        this.outfits = {};
        this.egGroup = null;
        this.gardenGroup = null;
        this.gardenSeasonGroups = {};
        this.gardenOccasionGroups = {};
        this.stairsGroup = null;
        this.ogGroup = null;
        this.ogRoofClosedGroup = null;
        this.egItemsGroup = null;
        this.ogItemsGroup = null;
        this.egWeatherGroup = null;
        this.ogWeatherGroup = null;
        this.ogSouthWeatherGroup = null;
        this.levelGroups = {};
        this.occasionGroups = {};
        this.roseMeshes = [];
        this.fallingPetals = [];
        this.weatherParticles = [];
        this.outdoorWeatherParticles = [];
        this.waterDropParticles = [];
        this.grassParticles = [];
        this.fireworkBursts = [];
        this.sparkParticles = [];
        this.worldItems = {};
        this.dropSpots = {};
        this.carriedItemId = null;
        this.itemJob = null;
        this.savedItemPlacements = {};
        this.lawnMowerGroup = null;
        this.lawnMowerDeck = null;
        this.lawnMowerWheels = [];
        this._mowerWaypoints = [
            { x:  1.05, z:  9.86 },
            { x: -1.78, z:  9.86 },
            { x: -1.78, z: 10.22 },
            { x:  1.05, z: 10.22 }
        ];
        this._mowerLaneIdx = 0;
        this._mowerParked = true;
        this.indoorBgColor = new THREE.Color(0x121017);
        this.outdoorSkyColor = new THREE.Color(0x47364d);
        this._tmpSpoutWorld = new THREE.Vector3();

        // 2-Floor Locomotion & Staircase State (Default home: Obergeschoss Seelenfunken Atelier)
        this.pos = new THREE.Vector3(0.0, OG_Y, 4.48);
        this.targetPos = new THREE.Vector3(0.0, OG_Y, 4.48);
        this.targetFloor = 1; // 0 | 'stairs' | 1
        this.waypoints = [];
        this.currentRoom = 'Seelenfunken Atelier';
        this.cameraLook = new THREE.Vector3(0, OG_Y + 1.02, 5.45);
        this.isSprintingHome = false;
        this.facingYaw = Math.PI;
        this.walkPhase = 0;
        this.moveSpeed = 1.65;
        this.currentTask = 'front';
        this.nextTaskSwitchMs = 0;
        this.taskIndex = 0;

        // Logical Active Spatial Navigation & Full-Body Posture Action State
        this.customNavActive = false;
        this.cameraFocusMode = 'auto'; // 'auto' | 'closeup_photo' | 'closeup_sign' | 'closeup_workbench' | 'closeup_laser' | 'closeup_garden'
        this.customFacingYaw = null;
        this.activeBodyAction = 'none'; // 'none' | 'duck' | 'jump' | 'spin' | 'turn_around' | 'bow' | 'stretch' | 'lean_closer' | 'dance' | 'water_plants' | 'garden_work' | 'sweep_terrace' | 'mow_lawn'
        this.bodyActionStartMs = 0;
        this.bodyActionUntilMs = 0;
        this.pendingBodyAction = null;

        this._initItemRegistryData();
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

        this.camera = new THREE.PerspectiveCamera(42, 16 / 9, 0.15, 64);
        this.camera.position.set(0, OG_Y + 1.38, 2.26);
        this.camera.lookAt(0, OG_Y + 1.02, 5.45);

        this._bakeVisorSprites();
        this._initSharedMatcaps();
        this._buildTwoStoryHouse();
        this._buildFunkiraRig();
        this._buildMovableSeelenfunkeItems();
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
        this.mats.roseGold     = new THREE.MeshMatcapMaterial({ matcap: makeStudioMatcapTexture('#e2869d', '#fff8fb', '#ffbdd0', 28, 0.44) });
        this.mats.darkArmor    = new THREE.MeshMatcapMaterial({ matcap: makeStudioMatcapTexture('#1d1721', '#8e6c7c', '#e490a6', 22, 0.35) });
        this.mats.jointDark    = new THREE.MeshMatcapMaterial({ matcap: makeStudioMatcapTexture('#110d14', '#4e3e49', '#7a5c6b', 16, 0.20) });
        this.mats.gold24k      = new THREE.MeshMatcapMaterial({ matcap: makeStudioMatcapTexture('#c89f4d', '#fffbe6', '#f7d88b', 32, 0.48) });
        this.mats.woodDark     = new THREE.MeshMatcapMaterial({ matcap: makeStudioMatcapTexture('#452f20', '#876449', '#6b4c35', 14, 0.16) });
        this.mats.oakWarm      = new THREE.MeshMatcapMaterial({ matcap: makeStudioMatcapTexture('#b89264', '#f2dec2', '#cfa97b', 12, 0.16) });
        this.mats.cognacLeather = new THREE.MeshMatcapMaterial({ matcap: makeStudioMatcapTexture('#a97447', '#ebd0b3', '#c89162', 14, 0.20) });
        this.mats.wallPlaster  = new THREE.MeshMatcapMaterial({ matcap: makeStudioMatcapTexture('#dcd8d2', '#ffffff', '#b8b2a8', 8, 0.12) });
        this.mats.wallExterior = new THREE.MeshMatcapMaterial({ matcap: makeStudioMatcapTexture('#2a2831', '#696477', '#C5A059', 12, 0.15) });
        this.mats.obsidian     = new THREE.MeshMatcapMaterial({ matcap: makeStudioMatcapTexture('#16141c', '#544e63', '#C5A059', 18, 0.18) });
        this.mats.silverSteel  = new THREE.MeshMatcapMaterial({ matcap: makeStudioMatcapTexture('#7a808c', '#f5f7fa', '#c5a059', 26, 0.28) });
        this.mats.ceramicWhite = new THREE.MeshMatcapMaterial({ matcap: makeStudioMatcapTexture('#f0f2f5', '#ffffff', '#cfd8e3', 28, 0.24) });
        this.mats.leafGreen    = new THREE.MeshMatcapMaterial({ matcap: makeStudioMatcapTexture('#28593c', '#6ba882', '#92cfa8', 12, 0.20) });
        this.mats.scarfRed     = new THREE.MeshMatcapMaterial({ matcap: makeStudioMatcapTexture('#b52648', '#ed6888', '#ffa1b6', 10, 0.22) });
        this.mats.snowWhite    = new THREE.MeshMatcapMaterial({ matcap: makeStudioMatcapTexture('#ede7e6', '#ffffff', '#ffdce6', 9, 0.16) });
        this.mats.roseBloom    = new THREE.MeshMatcapMaterial({ matcap: makeStudioMatcapTexture('#cf234e', '#ff7a9c', '#ffb5c8', 18, 0.30) });
        this.mats.broomStraw   = new THREE.MeshMatcapMaterial({ matcap: makeStudioMatcapTexture('#cca460', '#f7dfad', '#e6c27a', 8, 0.14) });
        this.mats.broomWood    = new THREE.MeshMatcapMaterial({ matcap: makeStudioMatcapTexture('#875d34', '#bd8c5e', '#9e7044', 10, 0.12) });
        this.mats.velvetCream  = new THREE.MeshMatcapMaterial({ matcap: makeStudioMatcapTexture('#cbbba8', '#fffaf2', '#e8d7c3', 10, 0.18) });
        this.mats.marbleCream  = new THREE.MeshMatcapMaterial({ matcap: makeStudioMatcapTexture('#e6dfd5', '#ffffff', '#d8c39e', 22, 0.22) });
        this.mats.terracotta   = new THREE.MeshMatcapMaterial({ matcap: makeStudioMatcapTexture('#b55c3c', '#f2a583', '#d97854', 12, 0.18) });
        this.mats.soilRich     = new THREE.MeshMatcapMaterial({ matcap: makeStudioMatcapTexture('#3b281c', '#694a36', '#4f3728', 8, 0.10) });
        this.mats.sunflowerYellow = new THREE.MeshMatcapMaterial({ matcap: makeStudioMatcapTexture('#e3aa1e', '#fff29c', '#ffd04d', 18, 0.28) });
        this.mats.pumpkinOrange = new THREE.MeshMatcapMaterial({ matcap: makeStudioMatcapTexture('#d6631b', '#ffb870', '#f08532', 16, 0.24) });
        this.mats.tulipPink    = new THREE.MeshMatcapMaterial({ matcap: makeStudioMatcapTexture('#df4f84', '#ffb8d2', '#f77aa8', 18, 0.26) });
        this.mats.blossomPink  = new THREE.MeshMatcapMaterial({ matcap: makeStudioMatcapTexture('#eb8fb0', '#fff0f6', '#ffc4da', 14, 0.22) });
        this.mats.autumnAmber  = new THREE.MeshMatcapMaterial({ matcap: makeStudioMatcapTexture('#c75a24', '#ffb66e', '#e38234', 14, 0.22) });
        this.mats.stoneWarm    = new THREE.MeshMatcapMaterial({ matcap: makeStudioMatcapTexture('#969189', '#ded9d1', '#b5afa6', 12, 0.14) });
        this.mats.waterBlue    = new THREE.MeshBasicMaterial({ color: 0x8ad8ff, transparent: true, opacity: 0.86 });
        this.mats.crystalGlass = new THREE.MeshBasicMaterial({ color: 0xeef7ff, transparent: true, opacity: 0.44, depthWrite: false });
        this.mats.crystalRim   = new THREE.MeshBasicMaterial({ color: 0xfffef7, transparent: true, opacity: 0.72, depthWrite: false });
        this.mats.showerGlass  = new THREE.MeshBasicMaterial({ color: 0xdff2ff, transparent: true, opacity: 0.26, depthWrite: false });
        this.mats.warmBulb     = new THREE.MeshBasicMaterial({ color: 0xFFE9B5 });
        this.mats.petalRose    = new THREE.MeshBasicMaterial({ color: 0xD92B5A, side: THREE.DoubleSide, transparent: true, opacity: 0.92 });
        this.mats.petalGold    = new THREE.MeshBasicMaterial({ color: 0xE6C275, side: THREE.DoubleSide, transparent: true, opacity: 0.92 });
        this.mats.petalGeo     = new THREE.PlaneGeometry(0.065, 0.085);
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

    // ========================================================================
    // ROOM NAMING, PER-FLOOR SOLID COLLIDERS & CROSS-FLOOR DIJKSTRA ROUTING
    // ========================================================================
    _isOnStairs() {
        return this.targetFloor === 'stairs' || (this.pos.y > 0.08 && this.pos.y < (OG_Y - 0.08));
    }

    _currentFloorIndex() {
        return this.pos.y >= (OG_Y * 0.5) ? 1 : 0;
    }

    _roomNameAt(x, y, z) {
        if (y > 0.12 && y < OG_Y - 0.12) return 'Treppe';
        if (y >= OG_Y * 0.5) {
            if (z >= 2.25) return 'Seelenfunken Atelier';
            if (z < -4.21) return 'OG-Studio';
            if (x > -0.06 && z < -0.10) return 'OG-Bad';
            return 'OG-Galerie';
        }
        if (z > 6.98) {
            return (z <= 9.58 && x >= -3.90 && x <= 2.50) ? 'Terrasse' : 'Garten';
        }
        if (x < -0.07) {
            if (z < -2.61) return 'Schlafzimmer';
            if (z < 0.01) return 'Büro';
            return 'Wohn-/Esszimmer';
        }
        if (z < -4.48 && x > 0.12) return 'Trainingsraum';
        if (z < -0.06 && x <= 1.19) return 'Flur';
        if (z < -2.05 && x > 1.19) return 'Bad';
        if (z < -0.06 && x > 1.19) return 'Heizungsraum';
        if (z >= 1.68 && z <= 2.87 && x >= 2.82) return 'Gäste-WC';
        if (z >= 2.55) return 'Küche';
        return 'Diele';
    }

    _getFloorBounds(floorIdx) {
        if (floorIdx === 1) {
            // Respect 1.25m Dachschrägen eaves left & right (usable standing width x = -3.00 .. +3.00)
            return { minX: -2.62, maxX: 2.62, minZ: -6.48, maxZ: 6.38 };
        }
        // Floor 0 encompasses EG House (z = -6.48 .. +6.84) + Outdoor Terrasse & Garten (z = +7.04 .. +12.82)
        return { minX: -5.22, maxX: 5.22, minZ: -6.48, maxZ: 12.82 };
    }

    _getFloorColliders(floorIdx) {
        const sea = this.worldState.season || 'autumn';
        const occ = this.worldState.occasion || 'none';
        if (floorIdx === 0) {
            const egBoxes = [
                // --- EG Exterior House Envelope & Terrassentür Gap (x = -1.24 .. -0.20 at z = +6.94) ---
                { minX: -5.60, maxX:  5.60, minZ: -7.10, maxZ: -6.72 }, // North house wall
                { minX: -5.60, maxX: -3.96, minZ: -7.10, maxZ:  7.02 }, // West house wall & West garden fence wing
                { minX:  3.96, maxX:  5.60, minZ: -7.10, maxZ:  7.02 }, // East house wall & East garden fence wing
                { minX: -5.60, maxX: -1.24, minZ:  6.84, maxZ:  7.04 }, // South wall West of Terrassentür
                { minX: -0.20, maxX:  5.60, minZ:  6.84, maxZ:  7.04 }, // South wall East of Terrassentür

                // --- EG Interior Partition Walls (exact doorway gaps preserved!) ---
                // Schlafzimmer South wall (z = -2.61) & Büro South wall (z = +0.01)
                { minX: -4.16, maxX: -0.07, minZ: -2.69, maxZ: -2.53 },
                { minX: -4.16, maxX: -0.07, minZ: -0.07, maxZ:  0.09 },

                // West-Column / Flur & Diele wall (x = -0.07) with 3 doorways:
                // Schlafzimmer door (-3.88..-2.86), Büro door (-1.52..-0.50), Wohnzimmer door (+0.42..+1.46)
                { minX:  0.04, maxX:  0.20, minZ: -6.92, maxZ: -4.48 }, // Trainingsraum West wall (3.96m width!)
                { minX: -0.15, maxX:  0.20, minZ: -4.56, maxZ: -3.88 },
                { minX: -0.15, maxX:  0.01, minZ: -2.86, maxZ: -1.52 },
                { minX: -0.15, maxX:  0.01, minZ: -0.50, maxZ:  0.42 },
                { minX: -0.15, maxX:  0.01, minZ:  1.46, maxZ:  6.92 },

                // Trainingsraum South wall (z = -4.48, doorway gap x = +0.18 .. +1.14)
                { minX:  1.14, maxX:  4.16, minZ: -4.56, maxZ: -4.40 },

                // Flur / Bad & Heizungsraum wall (x = +1.19, Bad doorway gap z = -3.66 .. -2.64)
                { minX:  1.11, maxX:  1.27, minZ: -4.48, maxZ: -3.66 },
                { minX:  1.11, maxX:  1.27, minZ: -2.64, maxZ: -0.06 },

                // Bad / Heizungsraum wall (z = -2.05)
                { minX:  1.19, maxX:  4.16, minZ: -2.13, maxZ: -1.97 },

                // Flur & Heizungsraum South wall at Diele (z = -0.06):
                // Flur doorway gap (+0.02..+1.06), Heizungsraum doorway gap (+1.38..+2.36)
                { minX:  1.06, maxX:  1.38, minZ: -0.14, maxZ:  0.02 },
                { minX:  2.36, maxX:  4.16, minZ: -0.14, maxZ:  0.02 },

                // Gäste-WC walls (x = +2.82 .. +4.16, z = +1.68 .. +2.87)
                { minX:  2.76, maxX:  4.16, minZ:  1.62, maxZ:  2.93 },

                // Küche North wall (z = +2.55, doorway gap x = +1.50 .. +2.58)
                { minX: -0.07, maxX:  1.50, minZ:  2.47, maxZ:  2.63 },
                { minX:  2.58, maxX:  2.84, minZ:  2.47, maxZ:  2.63 },

                // Staircase low-headroom footprint in Diele (entry at East x = +3.16, z = +1.06; West half is >1.65m overhead!)
                { minX:  1.88, maxX:  2.54, minZ:  0.66, maxZ:  1.48 },

                // --- EG Solid Furniture Colliders ---
                // Schlafzimmer: Doppelbett + Kleiderschrank
                { minX: -4.08, maxX: -1.98, minZ: -5.72, maxZ: -3.82 },
                { minX: -3.55, maxX: -1.18, minZ: -3.22, maxZ: -2.61 },
                // Trainingsraum: Hantelbank + Langhantel
                { minX:  1.62, maxX:  3.48, minZ: -6.22, maxZ: -5.22 },
                // Großes Bad: Dusche + Badewanne + Waschbecken + WC
                { minX:  1.22, maxX:  3.98, minZ: -4.46, maxZ: -3.66 },
                { minX:  1.85, maxX:  3.82, minZ: -2.56, maxZ: -2.06 },
                // Büro: Doppel-Schreibtische + Rollcontainer + Büro-Regal
                { minX: -3.35, maxX: -1.52, minZ: -2.12, maxZ: -0.48 },
                { minX: -4.08, maxX: -3.48, minZ: -2.55, maxZ: -1.85 },
                { minX: -1.25, maxX: -0.35, minZ: -0.42, maxZ:  0.00 },
                // Heizungsraum: Waschmaschine + Trockner + Heizung + Netzwerk-Schrank
                { minX:  1.25, maxX:  3.98, minZ: -2.02, maxZ: -1.32 },
                { minX:  3.52, maxX:  4.08, minZ: -1.28, maxZ: -0.58 },
                // Diele: Garderobe
                { minX:  0.04, maxX:  1.16, minZ:  2.06, maxZ:  2.50 },
                // Wohn-/Esszimmer: L-Ecksofa + TV-Lowboard + Esstisch mit 6 Stühlen
                { minX: -4.08, maxX: -3.02, minZ:  1.05, maxZ:  3.82 },
                { minX: -4.08, maxX: -1.32, minZ:  3.05, maxZ:  3.88 },
                { minX: -3.10, maxX: -1.35, minZ:  0.05, maxZ:  0.42 },
                { minX: -3.52, maxX: -1.35, minZ:  4.48, maxZ:  6.22 },
                // Küche: L-Zeile (Ost + Süd) + Kühlschrank + Freistehende Kücheninsel
                { minX:  3.28, maxX:  4.12, minZ:  2.92, maxZ:  6.88 },
                { minX:  0.12, maxX:  4.12, minZ:  6.02, maxZ:  6.88 },
                { minX:  0.34, maxX:  1.56, minZ:  3.48, maxZ:  5.42 },

                // --- Outdoor Terrasse & Garten Solid Colliders ---
                // Terrasse Rattan Lounge-Sofa & Tisch
                { minX: -3.62, maxX: -1.68, minZ:  7.42, maxZ:  8.95 },
                // Holz-Hochbeet (Kräuter & Gemüse)
                { minX:  2.62, maxX:  4.18, minZ:  7.82, maxZ:  9.38 },
                // Blumenbeet im Garten
                { minX: -3.58, maxX: -1.42, minZ: 10.62, maxZ: 11.78 },
                // Großer Zier-Gartenbaum
                { minX:  3.35, maxX:  4.35, minZ: 10.95, maxZ: 11.95 }
            ];

            // Goldener SEELENFUNKE Rasenmäher (parked on stone pad when not actively mowing)
            const isMowingNow = (
                this.activeBodyAction === 'mow_lawn' ||
                (this.mode === 'atelier_autonomous' && this.currentTask === 'mow_lawn')
            );
            if (!isMowingNow && this._mowerParked !== false) {
                egBoxes.push({ minX: 2.84, maxX: 3.52, minZ: 9.64, maxZ: 10.26 });
            }

            if (sea === 'winter' || occ === 'advent' || occ === 'christmas' || occ === 'nikolaus') {
                egBoxes.push({ minX: -4.18, maxX: -3.32, minZ: 9.32, maxZ: 10.18 }); // 3D Schneemann
            }
            if (occ === 'advent' || occ === 'christmas' || occ === 'nikolaus') {
                egBoxes.push({ minX: 1.48, maxX: 2.42, minZ: 11.08, maxZ: 12.02 }); // Outdoor Weihnachtsbaum
            }
            if (occ === 'silvester' || occ === 'new_year') {
                egBoxes.push({ minX: 0.85, maxX: 1.55, minZ: 7.38, maxZ: 8.08 }); // Silvester Sekt-Tisch
            }
            return egBoxes;
        }

        // --- OG (Floor 1, y = 3.10m) Walls, Geländer, Posts & Seelenfunken Atelier Furniture ---
        const lvl = this.worldState.shopLevel || 1;
        const ogBoxes = [
            // OG-Bad Walls (x = -0.06 .. +3.00, z = -4.21 .. -0.10, West doorway gap z = -1.88 .. -0.86)
            { minX: -0.06, maxX:  3.05, minZ: -4.29, maxZ: -4.13 },
            { minX: -0.06, maxX:  3.05, minZ: -0.18, maxZ: -0.02 },
            { minX: -0.14, maxX:  0.02, minZ: -4.25, maxZ: -1.88 },
            { minX: -0.14, maxX:  0.02, minZ: -0.86, maxZ: -0.04 },

            // OG-Bad Fixtures (Badewanne, Waschbecken, WC, Heizkörper)
            { minX:  0.05, maxX:  2.95, minZ: -4.18, maxZ: -3.32 },
            { minX:  0.02, maxX:  0.58, minZ: -2.82, maxZ: -1.94 },
            { minX:  2.08, maxX:  2.98, minZ: -2.75, maxZ: -1.98 },
            { minX:  0.95, maxX:  2.45, minZ: -0.38, maxZ: -0.12 },

            // Stairwell opening + Black Geländer ("Das ist ein Geländer", z = +1.92, x = +0.46 .. +3.00)
            { minX:  0.68, maxX:  3.02, minZ:  0.58, maxZ:  1.54 },
            { minX:  0.46, maxX:  3.02, minZ:  1.82, maxZ:  2.02 },

            // North Studio: 2 White Desks + Chairs + Right Schranksystem
            { minX: -2.38, maxX: -0.52, minZ: -6.85, maxZ: -5.42 },
            { minX:  0.32, maxX:  2.12, minZ: -6.85, maxZ: -5.42 },
            { minX:  2.38, maxX:  3.02, minZ: -6.85, maxZ: -4.25 },

            // West Knee-Wall Heizkörper (x = -3.00 .. -2.76)
            { minX: -3.02, maxX: -2.74, minZ: -3.78, maxZ: -1.92 },
            { minX: -3.02, maxX: -2.74, minZ:  0.12, maxZ:  1.98 }
        ];

        // Seelenfunken Atelier Furniture (z = +2.35 .. +6.84)
        if (lvl >= 1) {
            ogBoxes.push({ minX: -2.68, maxX: -0.68, minZ: 5.24, maxZ: 6.20 }); // Walnut Manufaktur-Werkbank + Crystal Trophies
        }
        if (lvl >= 2) {
            ogBoxes.push({ minX: -2.78, maxX: -2.18, minZ: 4.15, maxZ: 4.75 }); // Monstera Planter
        }
        if (lvl >= 3) {
            ogBoxes.push({ minX:  1.36, maxX:  2.52, minZ: 5.26, maxZ: 6.18 }); // xTool F2 Ultra UV + Exhaust
            ogBoxes.push({ minX:  0.68, maxX:  1.38, minZ: 5.65, maxZ: 6.28 }); // Boutique Vitrine
        }
        if (occ === 'advent' || occ === 'christmas' || occ === 'nikolaus') {
            ogBoxes.push({ minX:  2.05, maxX:  2.78, minZ: 4.05, maxZ: 4.82 }); // Christmas Tree
        }

        return ogBoxes;
    }

    _getFloorNavNodes(floorIdx) {
        if (floorIdx === 0) {
            return [
                // Schlafzimmer & doorway (x = -0.07, z = -3.37)
                { x: -1.45, z: -4.65 },
                { x: -0.65, z: -3.37 },
                { x:  0.54, z: -3.37 },
                // Trainingsraum & doorway (z = -4.48, x = +0.67)
                { x:  0.67, z: -4.02 },
                { x:  0.67, z: -4.92 },
                { x:  1.25, z: -4.92 },
                { x:  1.25, z: -5.25 },
                // Großes Bad & doorway (x = +1.19, z = -3.15)
                { x:  0.54, z: -3.15 },
                { x:  1.68, z: -3.15 },
                // Büro & doorway (x = -0.07, z = -1.01)
                { x: -0.65, z: -1.01 },
                { x: -1.08, z: -1.30 },
                { x:  0.54, z: -1.01 },
                // Flur <-> Diele doorway (z = -0.06, x = +0.54)
                { x:  0.54, z: -0.48 },
                { x:  0.54, z:  0.34 },
                // Heizungsraum & doorway (z = -0.06, x = +1.87)
                { x:  1.56, z:  0.34 },
                { x:  1.87, z:  0.34 },
                { x:  1.87, z: -0.68 },
                // Diele East & Staircase bottom entry (STAIR_BOT = 3.16, 1.06)
                { x:  3.16, z:  0.34 },
                { x:  3.16, z:  1.06 },
                // Diele South corridor & Küche entry
                { x:  0.54, z:  0.94 },
                { x:  0.54, z:  1.65 },
                { x:  1.56, z:  1.65 },
                { x:  1.56, z:  1.88 },
                { x:  2.05, z:  1.88 },
                // Wohn-/Esszimmer doorway (x = -0.07, z = +0.94) & aisles
                { x: -0.62, z:  0.94 },
                { x: -1.85, z:  1.95 },
                { x: -0.68, z:  2.55 },
                { x: -0.68, z:  4.25 },
                { x: -0.78, z:  5.35 },
                { x: -0.72, z:  6.25 },
                // Küche doorway (z = +2.55, x = +2.05) & wrap-around island aisles
                { x:  2.05, z:  3.05 },
                { x:  2.35, z:  4.45 },
                { x:  2.35, z:  5.55 },
                // Terrassentür (x = -0.72, z = +6.94) -> Terrasse & Garten Waypoints
                { x: -0.72, z:  7.48 },
                { x: -0.72, z:  8.32 },
                { x:  0.45, z:  8.48 },
                { x:  2.02, z:  8.60 },
                { x: -0.72, z:  9.88 },
                { x: -2.45, z:  9.98 },
                { x: -2.55, z:  9.45 },
                { x:  1.15, z: 10.35 },
                { x: -0.35, z: 11.35 },
                { x:  2.22, z:  9.95 },
                { x:  2.75, z: 10.75 }
            ];
        }

        // Floor 1 (Obergeschoss) safe aisle & doorway nodes
        return [
            // Staircase top landing (STAIR_TOP = 0.22, 1.06)
            { x:  0.22, z:  1.06 },
            { x: -0.65, z:  1.06 },
            { x: -0.65, z:  1.95 },
            { x: -1.45, z:  1.95 },
            // OG-Bad doorway & interior
            { x: -0.65, z: -1.36 },
            { x:  0.65, z: -1.36 },
            { x:  1.32, z: -2.35 },
            // North Studio gallery & twin desks
            { x: -1.25, z: -2.85 },
            { x: -1.25, z: -4.65 },
            { x: -1.45, z: -5.00 },
            { x:  1.15, z: -4.85 },
            // Seelenfunken Atelier open floor & stations
            { x: -0.75, z:  2.95 },
            { x:  0.35, z:  3.05 },
            { x:  0.00, z:  4.48 },
            { x:  0.52, z:  4.62 },
            { x: -1.65, z:  4.68 },
            { x:  1.72, z:  4.68 }
        ];
    }

    _segmentHitsBox(ax, az, bx, bz, box, pad) {
        const minX = box.minX - pad;
        const maxX = box.maxX + pad;
        const minZ = box.minZ - pad;
        const maxZ = box.maxZ + pad;

        let t0 = 0.0;
        let t1 = 1.0;
        const dx = bx - ax;
        const dz = bz - az;

        const clip = (p, q) => {
            if (Math.abs(p) < 1e-8) return q >= 0;
            const r = q / p;
            if (p < 0) {
                if (r > t1) return false;
                if (r > t0) t0 = r;
            } else {
                if (r < t0) return false;
                if (r < t1) t1 = r;
            }
            return true;
        };

        if (!clip(-dx, ax - minX)) return false;
        if (!clip( dx, maxX - ax)) return false;
        if (!clip(-dz, az - minZ)) return false;
        if (!clip( dz, maxZ - az)) return false;
        return t0 <= t1;
    }

    _segmentHitsAnyCollider(ax, az, bx, bz, boxes, pad = 0.28) {
        for (let i = 0; i < boxes.length; i++) {
            if (this._segmentHitsBox(ax, az, bx, bz, boxes[i], pad)) return true;
        }
        return false;
    }

    _pushOutOfColliders(x, z, floorIdx = 1, radius = 0.29, boxes = null) {
        const bounds = this._getFloorBounds(floorIdx);
        const list = boxes || this._getFloorColliders(floorIdx);
        let px = clamp(x, bounds.minX, bounds.maxX);
        let pz = clamp(z, bounds.minZ, bounds.maxZ);

        for (let pass = 0; pass < 3; pass++) {
            for (let i = 0; i < list.length; i++) {
                const b = list[i];
                const cx = clamp(px, b.minX, b.maxX);
                const cz = clamp(pz, b.minZ, b.maxZ);
                const dx = px - cx;
                const dz = pz - cz;
                const d2 = dx * dx + dz * dz;

                if (d2 < radius * radius) {
                    if (d2 > 1e-7) {
                        const d = Math.sqrt(d2);
                        const push = (radius - d) + 0.004;
                        px += (dx / d) * push;
                        pz += (dz / d) * push;
                    } else {
                        const toLeft  = Math.abs(px - b.minX);
                        const toRight = Math.abs(b.maxX - px);
                        const toBack  = Math.abs(pz - b.minZ);
                        const toFront = Math.abs(b.maxZ - pz);
                        const m = Math.min(toLeft, toRight, toBack, toFront);
                        if (m === toFront) pz = b.maxZ + radius + 0.01;
                        else if (m === toBack) pz = b.minZ - radius - 0.01;
                        else if (m === toLeft) px = b.minX - radius - 0.01;
                        else px = b.maxX + radius + 0.01;
                    }
                }
            }
            px = clamp(px, bounds.minX, bounds.maxX);
            pz = clamp(pz, bounds.minZ, bounds.maxZ);
        }
        return { x: px, z: pz };
    }

    _enforceCharacterCollision() {
        if (this._isOnStairs()) {
            this.pos.x = clamp(this.pos.x, 0.20, 3.20);
            this.pos.z = clamp(this.pos.z, 0.86, 1.26);
            const t = clamp((2.78 - this.pos.x) / (2.78 - 0.76), 0, 1);
            this.pos.y = t * OG_Y;
            return;
        }
        const fIdx = this._currentFloorIndex();
        const safe = this._pushOutOfColliders(this.pos.x, this.pos.z, fIdx, 0.24);
        this.pos.x = safe.x;
        this.pos.z = safe.z;
        this.pos.y = fIdx === 1 ? OG_Y : 0.0;
    }

    _solveSingleFloorPath(sx, sz, gx, gz, floorIdx) {
        const boxes = this._getFloorColliders(floorIdx);
        const start = this._pushOutOfColliders(sx, sz, floorIdx, 0.25, boxes);
        const goal = this._pushOutOfColliders(gx, gz, floorIdx, 0.25, boxes);
        const fy = floorIdx === 1 ? OG_Y : 0.0;

        if (!this._segmentHitsAnyCollider(start.x, start.z, goal.x, goal.z, boxes, 0.24)) {
            return [{ x: goal.x, y: fy, z: goal.z, floor: floorIdx }];
        }

        const baseNodes = this._getFloorNavNodes(floorIdx);
        const navNodes = [start, ...baseNodes, goal];
        const n = navNodes.length;
        const distArr = new Float64Array(n).fill(1e9);
        const prevArr = new Int16Array(n).fill(-1);
        const usedArr = new Uint8Array(n);
        distArr[0] = 0;

        for (let step = 0; step < n; step++) {
            let u = -1;
            let best = 1e9;
            for (let i = 0; i < n; i++) {
                if (!usedArr[i] && distArr[i] < best) {
                    best = distArr[i];
                    u = i;
                }
            }
            if (u === -1 || u === n - 1) break;
            usedArr[u] = 1;

            const ux = navNodes[u].x;
            const uz = navNodes[u].z;
            for (let v = 0; v < n; v++) {
                if (usedArr[v] || v === u) continue;
                const vx = navNodes[v].x;
                const vz = navNodes[v].z;
                if (!this._segmentHitsAnyCollider(ux, uz, vx, vz, boxes, 0.235)) {
                    const nd = best + Math.hypot(vx - ux, vz - uz);
                    if (nd < distArr[v]) {
                        distArr[v] = nd;
                        prevArr[v] = u;
                    }
                }
            }
        }

        const pts = [];
        if (prevArr[n - 1] !== -1) {
            let cur = n - 1;
            while (cur > 0 && cur !== -1) {
                pts.unshift({ x: navNodes[cur].x, y: fy, z: navNodes[cur].z, floor: floorIdx });
                cur = prevArr[cur];
            }
        } else {
            pts.push({ x: goal.x, y: fy, z: goal.z, floor: floorIdx });
        }
        return pts;
    }

    _routeTo(destX, destZ, destFloor = 1) {
        const goalFloor = destFloor === 0 ? 0 : 1;
        const STAIR_BOT = { x: 3.16, z: 1.06 };
        const STAIR_TOP = { x: 0.22, z: 1.06 };

        let allPts = [];

        if (this._isOnStairs()) {
            // Finish stairs directly toward goalFloor landing, then route on goalFloor
            if (goalFloor === 1) {
                allPts.push(
                    { x: 0.76, y: OG_Y, z: 1.06, floor: 'stairs' },
                    { x: STAIR_TOP.x, y: OG_Y, z: STAIR_TOP.z, floor: 1 },
                    ...this._solveSingleFloorPath(STAIR_TOP.x, STAIR_TOP.z, destX, destZ, 1)
                );
            } else {
                allPts.push(
                    { x: 2.78, y: 0.0, z: 1.06, floor: 'stairs' },
                    { x: STAIR_BOT.x, y: 0.0, z: STAIR_BOT.z, floor: 0 },
                    ...this._solveSingleFloorPath(STAIR_BOT.x, STAIR_BOT.z, destX, destZ, 0)
                );
            }
        } else {
            const curFloor = this._currentFloorIndex();
            const safeNow = this._pushOutOfColliders(this.pos.x, this.pos.z, curFloor, 0.24);
            this.pos.x = safeNow.x;
            this.pos.z = safeNow.z;
            this.pos.y = curFloor === 1 ? OG_Y : 0.0;

            if (curFloor === goalFloor) {
                allPts = this._solveSingleFloorPath(this.pos.x, this.pos.z, destX, destZ, goalFloor);
            } else if (curFloor === 0 && goalFloor === 1) {
                allPts = [
                    ...this._solveSingleFloorPath(this.pos.x, this.pos.z, STAIR_BOT.x, STAIR_BOT.z, 0),
                    { x: 2.78, y: 0.0,  z: 1.06, floor: 'stairs' },
                    { x: 0.76, y: OG_Y, z: 1.06, floor: 'stairs' },
                    { x: STAIR_TOP.x, y: OG_Y, z: STAIR_TOP.z, floor: 1 },
                    ...this._solveSingleFloorPath(STAIR_TOP.x, STAIR_TOP.z, destX, destZ, 1)
                ];
            } else {
                allPts = [
                    ...this._solveSingleFloorPath(this.pos.x, this.pos.z, STAIR_TOP.x, STAIR_TOP.z, 1),
                    { x: 0.76, y: OG_Y, z: 1.06, floor: 'stairs' },
                    { x: 2.78, y: 0.0,  z: 1.06, floor: 'stairs' },
                    { x: STAIR_BOT.x, y: 0.0, z: STAIR_BOT.z, floor: 0 },
                    ...this._solveSingleFloorPath(STAIR_BOT.x, STAIR_BOT.z, destX, destZ, 0)
                ];
            }
        }

        this.waypoints = allPts;
        const first = this.waypoints.shift();
        if (first) {
            this.targetPos.set(first.x, first.y, first.z);
            this.targetFloor = first.floor;
        }
    }

    // ========================================================================
    // PERSISTENT MOVABLE 3D SEELENFUNKE ITEMS & GOLDEN LAWN MOWER STATE ENGINE
    // ========================================================================
    _initItemRegistryData() {
        this.dropSpots = {
            workbench:      { label: 'Atelier-Werkbank',         floor: 1, y: OG_Y + 0.918, approachX: -1.45, approachZ:  4.68, faceYaw:  0.0,            rotY: Math.PI,      slots: [[-0.92, 5.42], [-1.68, 5.36], [-2.22, 5.38], [-1.30, 5.38]] },
            atelier_center: { label: 'Seelenfunken Atelier',     floor: 1, y: OG_Y + 0.008, approachX:  0.22, approachZ:  4.48, faceYaw:  Math.PI * 0.5,  rotY: Math.PI,      slots: [[ 0.82, 4.48], [ 0.82, 4.86], [ 0.82, 4.12]] },
            laser:          { label: 'Atelier-Vitrine',          floor: 1, y: OG_Y + 0.008, approachX:  1.04, approachZ:  4.68, faceYaw:  0.0,            rotY: Math.PI,      slots: [[ 1.04, 5.26], [ 0.76, 5.26]] },
            og_studio:      { label: 'OG-Studio Schreibtisch',   floor: 1, y: OG_Y + 0.778, approachX: -1.45, approachZ: -5.00, faceYaw:  Math.PI,        rotY: 0.0,          slots: [[-1.48, -6.08], [-1.05, -6.08], [-1.92, -6.08]] },
            og_gallery:     { label: 'OG-Galerie',               floor: 1, y: OG_Y + 0.008, approachX: -0.85, approachZ:  1.95, faceYaw: -Math.PI * 0.5,  rotY: 0.0,          slots: [[-1.55, 1.95], [-1.55, 1.55], [-1.55, 2.35]] },
            og_bathroom:    { label: 'OG-Bad Waschtisch',        floor: 1, y: OG_Y + 0.712, approachX:  1.05, approachZ: -2.35, faceYaw: -Math.PI * 0.5,  rotY: Math.PI * 0.5, slots: [[ 0.32, -2.68], [ 0.32, -2.02]] },
            kitchen:        { label: 'Kücheninsel',              floor: 0, y: 0.923,        approachX:  2.02, approachZ:  4.45, faceYaw: -Math.PI * 0.5,  rotY: 0.0,          slots: [[ 1.18, 4.02], [ 1.18, 4.88], [ 0.58, 4.02], [ 0.58, 4.88]] },
            dining_room:    { label: 'Esstisch',                 floor: 0, y: 0.793,        approachX: -0.88, approachZ:  5.35, faceYaw: -Math.PI * 0.5,  rotY: 0.0,          slots: [[-1.72, 5.35], [-1.72, 5.05], [-1.72, 5.65], [-3.12, 5.35]] },
            living_room:    { label: 'Wohnzimmer-Lowboard',      floor: 0, y: 0.442,        approachX: -1.85, approachZ:  0.92, faceYaw:  Math.PI,        rotY: 0.0,          slots: [[-1.62, 0.31], [-2.82, 0.31], [-2.22, 0.33]] },
            office:         { label: 'Büro-Schreibtisch',        floor: 0, y: 0.766,        approachX: -1.08, approachZ: -1.30, faceYaw: -Math.PI * 0.5,  rotY: 0.0,          slots: [[-1.84, -1.02], [-1.84, -1.58], [-2.35, -1.02]] },
            bedroom:        { label: 'Schlafzimmer-Betttruhe',   floor: 0, y: 0.582,        approachX: -1.45, approachZ: -4.65, faceYaw: -Math.PI * 0.5,  rotY: 0.0,          slots: [[-2.10, -4.52], [-2.10, -5.04], [-2.10, -4.78]] },
            hallway:        { label: 'Diele-Garderobe',          floor: 0, y: 1.152,        approachX:  0.65, approachZ:  1.65, faceYaw:  0.0,            rotY: 0.0,          slots: [[ 0.42, 2.24], [ 0.88, 2.24], [ 0.65, 2.24]] },
            gym:            { label: 'Trainingsraum',            floor: 0, y: 0.018,        approachX:  1.25, approachZ: -5.25, faceYaw:  Math.PI * 0.5,  rotY: 0.0,          slots: [[ 1.52, -4.92], [ 1.52, -5.48]] },
            bathroom_eg:    { label: 'Bad-Waschtisch',           floor: 0, y: 0.682,        approachX:  1.68, approachZ: -3.15, faceYaw:  0.35,           rotY: Math.PI,      slots: [[ 1.88, -2.32], [ 2.48, -2.32]] },
            terrace:        { label: 'Terrassentisch',           floor: 0, y: 0.378,        approachX: -1.24, approachZ:  8.52, faceYaw: -Math.PI * 0.5,  rotY: 0.0,          slots: [[-2.06, 8.52], [-2.56, 8.52], [-2.32, 8.34]] },
            hochbeet:       { label: 'Hochbeet-Rand',            floor: 0, y: 0.793,        approachX:  2.02, approachZ:  8.60, faceYaw:  Math.PI * 0.5,  rotY: 0.0,          slots: [[ 2.70, 8.32], [ 2.70, 8.88], [ 2.70, 8.60]] },
            flowerbed:      { label: 'Blumenbeet-Rand',          floor: 0, y: 0.162,        approachX: -2.45, approachZ:  9.98, faceYaw:  0.0,            rotY: 0.0,          slots: [[-2.05, 10.68], [-2.95, 10.68], [-2.50, 10.68]] },
            garden:         { label: 'Garten-Trittstein',        floor: 0, y: 0.018,        approachX: -0.38, approachZ: 10.42, faceYaw:  0.0,            rotY: 0.0,          slots: [[-0.38, 11.05], [ 0.18, 11.05], [-0.92, 11.05]] }
        };

        this._defaultItemSpecs = [
            { id: 'trophy',    label: 'Goldener Seelenfunke-Pokal',         defaultSpot: 'workbench'   },
            { id: 'flowerpot', label: 'Goldener Seelenfunke-Blumentopf',    defaultSpot: 'dining_room' },
            { id: 'vase',      label: 'Goldene Seelenfunke-Rosenvase',      defaultSpot: 'living_room' },
            { id: 'piggybank', label: 'Goldene Seelenfunke-Spardose',       defaultSpot: 'office'      },
            { id: 'mug',       label: 'Schwarz-Goldene Seelenfunke-Tasse',  defaultSpot: 'kitchen'     },
            { id: 'lantern',   label: 'Goldene Seelenfunke-Gartenlaterne',  defaultSpot: 'terrace'     },
            { id: 'giftbox',   label: 'Westpack-Geschenkbox Seelenfunke',   defaultSpot: 'bedroom'     },
            { id: 'frame',     label: 'Goldener Seelenfunke-Bilderrahmen',  defaultSpot: 'og_studio'   }
        ];

        for (const spec of this._defaultItemSpecs) {
            const sp = this.dropSpots[spec.defaultSpot];
            const sl = sp.slots[0];
            this.worldItems[spec.id] = {
                id: spec.id,
                label: spec.label,
                defaultSpot: spec.defaultSpot,
                spotKey: spec.defaultSpot,
                floor: sp.floor,
                x: sl[0],
                y: sp.y,
                z: sl[1],
                rotY: sp.rotY,
                approachX: sp.approachX,
                approachZ: sp.approachZ,
                faceYaw: sp.faceYaw,
                group: null
            };
        }

        if (typeof localStorage !== 'undefined') {
            try {
                const raw = localStorage.getItem('fw_funkira_world_items_v1');
                if (raw) {
                    const parsed = JSON.parse(raw);
                    if (parsed && typeof parsed === 'object') {
                        this.applySavedItemPlacements(parsed, false);
                    }
                }
            } catch (_) {}
        }
    }

    _normalizeItemId(raw) {
        const k = String(raw || '').trim().toLowerCase();
        if (!k || k === 'none') return '';
        const itemMap = {
            trophy: 'trophy', pokal: 'trophy', 'trophäe': 'trophy', trophaee: 'trophy', wanderpokal: 'trophy', siegerpokal: 'trophy', kristallpokal: 'trophy', award: 'trophy',
            flowerpot: 'flowerpot', blumentopf: 'flowerpot', blume: 'flowerpot', blumen: 'flowerpot', pflanze: 'flowerpot', topfpflanze: 'flowerpot', orchidee: 'flowerpot', flower: 'flowerpot', flowers: 'flowerpot', plant: 'flowerpot',
            vase: 'vase', blumenvase: 'vase', rosenvase: 'vase', 'rosenstrauß': 'vase', rosenstrauss: 'vase', rosen: 'vase', bouquet: 'vase',
            piggybank: 'piggybank', spardose: 'piggybank', sparschatz: 'piggybank', schatzkasse: 'piggybank', kasse: 'piggybank', 'körperkasse': 'piggybank', koerperkasse: 'piggybank', jar: 'piggybank',
            mug: 'mug', tasse: 'mug', kaffeetasse: 'mug', becher: 'mug', cup: 'mug', coffee: 'mug', kaffee: 'mug',
            lantern: 'lantern', laterne: 'lantern', gartenlaterne: 'lantern', windlicht: 'lantern', kerze: 'lantern', licht: 'lantern',
            giftbox: 'giftbox', geschenkbox: 'giftbox', geschenk: 'giftbox', schmuckbox: 'giftbox', westpack: 'giftbox', box: 'giftbox', paket: 'giftbox',
            frame: 'frame', bilderrahmen: 'frame', rahmen: 'frame', tischbild: 'frame', 'gemälde': 'frame', gemaelde: 'frame', art: 'frame'
        };
        return itemMap[k] || (this.worldItems[k] ? k : '');
    }

    _nearestDropSpotKey() {
        const curFloor = this._currentFloorIndex();
        let bestKey = curFloor === 1 ? 'workbench' : 'kitchen';
        let bestDist = Infinity;
        for (const [key, sp] of Object.entries(this.dropSpots)) {
            if (sp.floor !== curFloor) continue;
            const d = Math.hypot(sp.approachX - this.pos.x, sp.approachZ - this.pos.z);
            if (d < bestDist) {
                bestDist = d;
                bestKey = key;
            }
        }
        return bestKey;
    }

    _allocateDropSlot(spotKey, itemId) {
        const spot = this.dropSpots[spotKey] || this.dropSpots.workbench;
        for (let i = 0; i < spot.slots.length; i++) {
            const [sx, sz] = spot.slots[i];
            let occupied = false;
            for (const other of Object.values(this.worldItems)) {
                if (other.id === itemId || other.spotKey === 'carried') continue;
                if (other.floor === spot.floor && Math.hypot(other.x - sx, other.z - sz) < 0.17) {
                    occupied = true;
                    break;
                }
            }
            if (!occupied) return [sx, sz];
        }
        const base = spot.slots[0];
        const idx = Object.keys(this.worldItems).indexOf(itemId);
        return [base[0] + ((idx % 3) - 1) * 0.14, base[1]];
    }

    _placeItemImmediate(itemId, spotKey, exactXZ = null, persist = true) {
        const itemObj = this.worldItems[itemId];
        if (!itemObj) return false;
        const validKey = this.dropSpots[spotKey] ? spotKey : itemObj.defaultSpot;
        const spot = this.dropSpots[validKey];

        let sx, sz;
        if (Array.isArray(exactXZ) && exactXZ.length === 2 && Number.isFinite(exactXZ[0]) && Number.isFinite(exactXZ[1])) {
            const ref = spot.slots[0];
            if (Math.hypot(exactXZ[0] - ref[0], exactXZ[1] - ref[1]) <= 1.25) {
                sx = exactXZ[0];
                sz = exactXZ[1];
            } else {
                [sx, sz] = this._allocateDropSlot(validKey, itemId);
            }
        } else {
            [sx, sz] = this._allocateDropSlot(validKey, itemId);
        }

        if (this.carriedItemId === itemId) {
            this.carriedItemId = null;
        }

        itemObj.spotKey = validKey;
        itemObj.floor = spot.floor;
        itemObj.x = sx;
        itemObj.y = spot.y;
        itemObj.z = sz;
        itemObj.rotY = spot.rotY;
        itemObj.approachX = spot.approachX;
        itemObj.approachZ = spot.approachZ;
        itemObj.faceYaw = spot.faceYaw;

        if (itemObj.group) {
            const targetParent = (spot.floor === 1) ? this.ogItemsGroup : this.egItemsGroup;
            if (targetParent && itemObj.group.parent !== targetParent) {
                targetParent.add(itemObj.group);
            }
            itemObj.group.position.set(sx, spot.y, sz);
            itemObj.group.rotation.set(0, spot.rotY, 0);
            itemObj.group.visible = true;
        }

        this.savedItemPlacements[itemId] = {
            spotKey: validKey,
            x: +sx.toFixed(3),
            y: +spot.y.toFixed(3),
            z: +sz.toFixed(3),
            floor: spot.floor
        };

        if (persist) {
            this._persistWorldItems();
        }
        return true;
    }

    _attachItemToHands(itemId) {
        const itemObj = this.worldItems[itemId];
        if (!itemObj) return false;
        if (this.carriedItemId && this.carriedItemId !== itemId) {
            this._placeItemImmediate(this.carriedItemId, this._nearestDropSpotKey(), null, true);
        }
        this.carriedItemId = itemId;
        itemObj.spotKey = 'carried';
        if (itemObj.group && this.rig.carryAnchor) {
            this.rig.carryAnchor.add(itemObj.group);
            itemObj.group.position.set(0, 0, 0);
            itemObj.group.rotation.set(0, 0, 0);
            itemObj.group.visible = true;
        }
        return true;
    }

    _persistWorldItems() {
        const snapshot = {};
        for (const [id, itemObj] of Object.entries(this.worldItems)) {
            if (itemObj.spotKey === 'carried') {
                if (this.savedItemPlacements[id]) snapshot[id] = this.savedItemPlacements[id];
                continue;
            }
            snapshot[id] = {
                spotKey: itemObj.spotKey,
                x: +itemObj.x.toFixed(3),
                y: +itemObj.y.toFixed(3),
                z: +itemObj.z.toFixed(3),
                floor: itemObj.floor
            };
        }
        this.savedItemPlacements = snapshot;
        if (typeof localStorage !== 'undefined') {
            try {
                localStorage.setItem('fw_funkira_world_items_v1', JSON.stringify(snapshot));
            } catch (_) {}
        }
        if (typeof window !== 'undefined' && typeof window.onFunkiraWorldItemsChanged === 'function') {
            try {
                window.onFunkiraWorldItemsChanged(snapshot);
            } catch (_) {}
        }
    }

    applySavedItemPlacements(mapObj, persistLocal = true) {
        if (!mapObj || typeof mapObj !== 'object') return false;
        let applied = 0;
        for (const [id, entry] of Object.entries(mapObj)) {
            if (!this.worldItems[id] || !entry || typeof entry !== 'object') continue;
            const sk = String(entry.spotKey || '');
            if (!this.dropSpots[sk]) continue;
            const xz = (Number.isFinite(entry.x) && Number.isFinite(entry.z)) ? [Number(entry.x), Number(entry.z)] : null;
            if (this._placeItemImmediate(id, sk, xz, false)) {
                applied++;
            }
        }
        if (persistLocal && applied > 0 && typeof localStorage !== 'undefined') {
            try {
                localStorage.setItem('fw_funkira_world_items_v1', JSON.stringify(this.savedItemPlacements));
            } catch (_) {}
        }
        return applied > 0;
    }

    resetAllWorldItems(persist = true) {
        this.itemJob = null;
        this.carriedItemId = null;
        for (const spec of this._defaultItemSpecs) {
            const sp = this.dropSpots[spec.defaultSpot];
            this._placeItemImmediate(spec.id, spec.defaultSpot, sp.slots[0], false);
        }
        if (persist) {
            this._persistWorldItems();
        }
        return true;
    }

    getWorldItemsSummary() {
        const out = {};
        for (const [id, it] of Object.entries(this.worldItems)) {
            out[id] = {
                label: it.label,
                spotKey: it.spotKey,
                locationLabel: it.spotKey === 'carried' ? 'In Funkiras Händen' : (this.dropSpots[it.spotKey]?.label || it.spotKey),
                floor: it.floor,
                x: +it.x.toFixed(2),
                z: +it.z.toFixed(2)
            };
        }
        return out;
    }

    _parkLawnMowerHome() {
        this._mowerParked = true;
        if (this.lawnMowerGroup) {
            this.lawnMowerGroup.position.set(3.18, 0, 9.95);
            this.lawnMowerGroup.rotation.set(0, -Math.PI * 0.42, 0);
        }
        if (this.lawnMowerDeck) {
            this.lawnMowerDeck.position.y = 0;
        }
    }

    setMode(mode) {
        const prev = this.mode;
        if (this.activeBodyAction === 'mow_lawn' || this.currentTask === 'mow_lawn') {
            this._parkLawnMowerHome();
        }
        this.mode = mode === 'atelier_autonomous' ? 'atelier_autonomous' : 'front_active';
        this.customNavActive = false;
        this.cameraFocusMode = 'auto';
        this.customFacingYaw = null;
        this.activeBodyAction = 'none';
        this.bodyActionUntilMs = 0;
        this.pendingBodyAction = null;
        this.itemJob = null;

        if (this.mode === 'front_active') {
            const awayFromAtelier = (prev === 'atelier_autonomous' || this.pos.y < 2.8 || this.pos.z < 2.8 || Math.abs(this.pos.x) > 1.3);
            this.isSprintingHome = awayFromAtelier;
            this.moveSpeed = this.isSprintingHome ? 4.85 : 2.6;
            this.currentTask = 'front';
            this._routeTo(0.0, 4.48, 1);
            if (this.rig.broom) this.rig.broom.visible = false;
            if (this.rig.cloth) this.rig.cloth.visible = false;
            if (this.rig.wateringCan) this.rig.wateringCan.visible = false;
            if (this.rig.gardenTrowel) this.rig.gardenTrowel.visible = false;
        } else {
            if (this.carriedItemId) {
                this._placeItemImmediate(this.carriedItemId, this._nearestDropSpotKey(), null, true);
            }
            this.isSprintingHome = false;
            this.moveSpeed = 1.48;
            this._pickNextAutonomousTask(performance.now(), true);
        }
    }

    // ========================================================================
    // GENERAL LOGICAL SPATIAL MOVEMENT, ITEM CARRY/PLACE & POSTURE ENGINE
    // Supports relative moves, Atelier landmarks, 3D Staircase, all 2-story house rooms,
    // Terrasse & Garten, Golden SEELENFUNKE Lawn Mower (lawn_mower / mow_lawn),
    // and persistent 3D item pickup, carrying & placement (pick_up, move_item, place_down, reset_items).
    // ========================================================================
    executeMovement(opts = {}) {
        const rawTarget     = String(opts.target || opts.destination || '').trim().toLowerCase();
        const rawAction     = String(opts.bodyAction || opts.body_action || opts.posture || '').trim().toLowerCase();
        const rawItemAction = String(opts.itemAction || opts.item_action || '').trim().toLowerCase();
        const rawItem       = String(opts.item || opts.object || opts.itemId || '').trim().toLowerCase();
        const rawSpeed      = String(opts.speed || 'walk').trim().toLowerCase();
        const stepDist      = clamp(Number(opts.distance || opts.distanceMeters || 1.28) || 1.28, 0.45, 3.00);
        const durSec        = clamp(Number(opts.durationSeconds || opts.duration_seconds || 0) || 0, 0, 30);

        if (this.mode !== 'front_active') {
            if (this.currentTask === 'mow_lawn') this._parkLawnMowerHome();
            this.mode = 'front_active';
            if (this.rig.broom) this.rig.broom.visible = false;
            if (this.rig.cloth) this.rig.cloth.visible = false;
            if (this.rig.wateringCan) this.rig.wateringCan.visible = false;
            if (this.rig.gardenTrowel) this.rig.gardenTrowel.visible = false;
        }

        // 1. Normalize target alias
        const targetMap = {
            '': 'stay', 'none': 'stay', 'stay': 'stay', 'here': 'stay', 'hier': 'stay',
            'aside': 'step_aside', 'side': 'step_aside', 'step_aside': 'step_aside', 'zur_seite': 'step_aside',
            'left': 'step_left', 'step_left': 'step_left', 'links': 'step_left',
            'right': 'step_right', 'step_right': 'step_right', 'rechts': 'step_right',
            'forward': 'closer_to_camera', 'closer': 'closer_to_camera', 'closer_to_camera': 'closer_to_camera', 'step_forward': 'closer_to_camera', 'camera': 'closer_to_camera', 'front': 'closer_to_camera',
            'back': 'step_back', 'backward': 'step_back', 'step_back': 'step_back', 'further_back': 'step_back',
            'home': 'atelier_center', 'center': 'atelier_center', 'atelier': 'atelier_center', 'atelier_center': 'atelier_center', 'come_back': 'atelier_center',
            'photo': 'colleagues_photo', 'picture': 'colleagues_photo', 'bild': 'colleagues_photo', 'colleagues': 'colleagues_photo', 'colleagues_photo': 'colleagues_photo',
            'sign': 'brand_sign', 'brand_sign': 'brand_sign', 'schild': 'brand_sign', 'leuchtschild': 'brand_sign',
            'workbench': 'workbench', 'table': 'workbench', 'trophies': 'workbench', 'werkbank': 'workbench', 'tisch': 'workbench',
            'laser': 'laser', 'xtool': 'laser', 'vitrine': 'laser',
            'window': 'atelier_window', 'atelier_window': 'atelier_window', 'fenster': 'atelier_window',
            'stairs_down': 'stairs_down', 'downstairs': 'stairs_down', 'treppe_runter': 'stairs_down',
            'stairs_mid': 'stairs_mid', 'stairs': 'stairs_mid', 'treppe': 'stairs_mid',
            'stairs_up': 'stairs_up', 'upstairs': 'stairs_up', 'treppe_hoch': 'stairs_up',
            'kitchen': 'kitchen', 'kueche': 'kitchen', 'küche': 'kitchen', 'kuecheninsel': 'kitchen', 'kücheninsel': 'kitchen', 'kuechentisch': 'kitchen', 'küchentisch': 'kitchen',
            'living_room': 'living_room', 'sofa': 'living_room', 'wohnzimmer': 'living_room', 'lowboard': 'living_room',
            'dining_room': 'dining_room', 'dining_table': 'dining_room', 'esszimmer': 'dining_room', 'esstisch': 'dining_room',
            'office': 'office', 'buero': 'office', 'büro': 'office', 'schreibtisch': 'office',
            'bedroom': 'bedroom', 'schlafzimmer': 'bedroom', 'bett': 'bedroom',
            'gym': 'gym', 'workout': 'gym', 'trainingsraum': 'gym',
            'bathroom': 'bathroom_eg', 'bathroom_eg': 'bathroom_eg', 'bad': 'bathroom_eg',
            'utility_room': 'utility_room', 'heating_room': 'utility_room', 'heizungsraum': 'utility_room',
            'hallway': 'hallway', 'entrance': 'hallway', 'diele': 'hallway', 'flur': 'hallway', 'garderobe': 'hallway',
            'og_studio': 'og_studio', 'studio': 'og_studio',
            'og_bathroom': 'og_bathroom', 'bad_oben': 'og_bathroom',
            'og_gallery': 'og_gallery', 'railing': 'og_gallery', 'gelaender': 'og_gallery', 'geländer': 'og_gallery',
            'terrace': 'terrace', 'terrasse': 'terrace', 'veranda': 'terrace', 'patio': 'terrace', 'balkon': 'terrace', 'terrassentisch': 'terrace',
            'garden': 'garden', 'garten': 'garden', 'outside': 'garden', 'outdoors': 'garden', 'draussen': 'garden', 'draußen': 'garden', 'raus': 'garden', 'rausgehen': 'garden', 'rasen': 'garden',
            'hochbeet': 'hochbeet', 'raised_bed': 'hochbeet', 'herb_bed': 'hochbeet', 'kräuterbeet': 'hochbeet', 'kraeuterbeet': 'hochbeet', 'gemüsebeet': 'hochbeet', 'gemuesebeet': 'hochbeet',
            'flowerbed': 'flowerbed', 'blumenbeet': 'flowerbed', 'beet': 'flowerbed', 'flowers': 'flowerbed', 'blumen': 'flowerbed',
            'snowman': 'snowman', 'schneemann': 'snowman',
            'lawn_mower': 'lawn_mower', 'mower': 'lawn_mower', 'rasenmaeher': 'lawn_mower', 'rasenmäher': 'lawn_mower', 'maeher': 'lawn_mower', 'mäher': 'lawn_mower'
        };
        let target = targetMap[rawTarget] || rawTarget || 'stay';

        // 2. Normalize body action alias
        const actionMap = {
            '': 'none', 'none': 'none',
            'stand': 'stand_up', 'stand_up': 'stand_up', 'reset': 'stand_up', 'aufstehen': 'stand_up',
            'duck': 'duck', 'crouch': 'duck', 'squat': 'duck', 'ducken': 'duck', 'hocke': 'duck',
            'jump': 'jump', 'hop': 'jump', 'springen': 'jump',
            'spin': 'spin', 'pirouette': 'spin', 'twirl': 'spin', 'drehen': 'spin',
            'turn_around': 'turn_around', 'turn_back': 'turn_around', 'show_back': 'turn_around', 'umdrehen': 'turn_around',
            'bow': 'bow', 'verbeugen': 'bow',
            'stretch': 'stretch', 'strecken': 'stretch',
            'lean_closer': 'lean_closer', 'lean': 'lean_closer', 'vorbeugen': 'lean_closer',
            'dance': 'dance', 'tanzen': 'dance',
            'water_plants': 'water_plants', 'water': 'water_plants', 'giessen': 'water_plants', 'gießen': 'water_plants', 'blumen_giessen': 'water_plants', 'pflanzen_giessen': 'water_plants',
            'garden_work': 'garden_work', 'gardening': 'garden_work', 'gartenarbeit': 'garden_work', 'plant': 'garden_work', 'pflanzen': 'garden_work', 'weed': 'garden_work', 'unkraut': 'garden_work', 'dig': 'garden_work', 'buddeln': 'garden_work',
            'sweep_terrace': 'sweep_terrace', 'sweep': 'sweep_terrace', 'fegen': 'sweep_terrace', 'laub_fegen': 'sweep_terrace', 'schnee_fegen': 'sweep_terrace', 'schnee_schippen': 'sweep_terrace',
            'mow_lawn': 'mow_lawn', 'mow': 'mow_lawn', 'rasen_maehen': 'mow_lawn', 'rasen_mähen': 'mow_lawn', 'maehen': 'mow_lawn', 'mähen': 'mow_lawn'
        };
        const bodyAction = actionMap[rawAction] || 'none';

        // 3. Normalize 3D Item Manipulation Action (pick_up | move_item | place_down | reset_items)
        const itemActionMap = {
            '': 'none', 'none': 'none',
            'pick_up': 'pick_up', 'pickup': 'pick_up', 'take': 'pick_up', 'grab': 'pick_up', 'hold': 'pick_up', 'aufheben': 'pick_up', 'nehmen': 'pick_up', 'holen': 'pick_up',
            'move_item': 'move_item', 'move': 'move_item', 'carry_to': 'move_item', 'bring': 'move_item', 'umstellen': 'move_item', 'tragen': 'move_item', 'bringen': 'move_item', 'hinstellen': 'move_item',
            'place_down': 'place_down', 'put_down': 'place_down', 'drop': 'place_down', 'place': 'place_down', 'abstellen': 'place_down', 'ablegen': 'place_down', 'stellen': 'place_down',
            'reset_items': 'reset_items', 'tidy_up': 'reset_items', 'aufräumen': 'reset_items', 'aufraeumen': 'reset_items', 'reset_world_items': 'reset_items'
        };
        let itemAction = itemActionMap[rawItemAction] || 'none';
        let itemId = this._normalizeItemId(rawItem);

        if (itemId && itemAction === 'none') {
            itemAction = (target !== 'stay' && this.dropSpots[target]) ? 'move_item' : 'pick_up';
        }
        if (itemAction === 'pick_up' && target !== 'stay' && this.dropSpots[target]) {
            itemAction = 'move_item';
        }

        if (itemAction === 'reset_items') {
            if (this.activeBodyAction === 'mow_lawn') this._parkLawnMowerHome();
            this.activeBodyAction = 'none';
            this.bodyActionUntilMs = 0;
            this.resetAllWorldItems(true);
            this.setExpression(opts.emotion || 'happy', 'cheer', 5500);
            return { ok: true, itemAction: 'reset_items', room: this.currentRoom };
        }

        if (itemAction === 'pick_up' || itemAction === 'move_item' || itemAction === 'place_down') {
            if (this.activeBodyAction === 'mow_lawn') this._parkLawnMowerHome();
            this.activeBodyAction = 'none';
            this.bodyActionUntilMs = 0;
            this.pendingBodyAction = null;
            if (this.rig.wateringCan) this.rig.wateringCan.visible = false;
            if (this.rig.gardenTrowel) this.rig.gardenTrowel.visible = false;
            if (this.rig.broom) this.rig.broom.visible = false;

            if (!itemId) {
                itemId = this.carriedItemId || 'trophy';
            }
            const itemObj = this.worldItems[itemId];
            if (!itemObj) {
                return { ok: false, error: 'unknown_item', item: rawItem };
            }

            let destSpotKey = null;
            if (itemAction === 'move_item' || itemAction === 'place_down') {
                if (target !== 'stay' && this.dropSpots[target]) {
                    destSpotKey = target;
                } else if (target === 'stairs_up') {
                    destSpotKey = 'workbench';
                } else if (target === 'stairs_down') {
                    destSpotKey = 'kitchen';
                } else {
                    destSpotKey = this._nearestDropSpotKey();
                }
            }

            this.customNavActive = true;
            this.cameraFocusMode = (destSpotKey === 'terrace' || destSpotKey === 'hochbeet' || destSpotKey === 'flowerbed' || destSpotKey === 'garden')
                ? 'closeup_garden'
                : 'auto';
            this.customFacingYaw = null;
            this.isSprintingHome = (rawSpeed === 'sprint');
            this.moveSpeed = rawSpeed === 'sprint' ? 4.45 : (rawSpeed === 'fast' ? 3.10 : 2.15);

            if (itemAction === 'place_down' || this.carriedItemId === itemId) {
                if (!this.carriedItemId) {
                    this._attachItemToHands(itemId);
                }
                const destSpot = this.dropSpots[destSpotKey] || this.dropSpots.workbench;
                this.itemJob = {
                    phase: 'walking_to_drop',
                    itemId,
                    destSpotKey,
                    phaseTimer: 0
                };
                this._routeTo(destSpot.approachX, destSpot.approachZ, destSpot.floor);
                this.setExpression(opts.emotion || 'happy', 'carry_hold', 12000);
                return { ok: true, itemAction, item: itemId, destSpotKey, floor: destSpot.floor };
            }

            // Need to walk to the item's CURRENT persisted location first to pick it up!
            this.itemJob = {
                phase: 'walking_to_pickup',
                itemId,
                destSpotKey, // null for pick_up only; spotKey for move_item!
                phaseTimer: 0
            };
            this._routeTo(itemObj.approachX, itemObj.approachZ, itemObj.floor);
            this.setExpression(opts.emotion || 'happy', 'idle', 9000);
            return { ok: true, itemAction, item: itemId, fromSpot: itemObj.spotKey, destSpotKey };
        }

        // Cancel any unfinished itemJob when an ordinary movement command arrives
        this.itemJob = null;

        // Smart outdoor auto-routing: if gardening/watering/sweeping/mowing is requested, route to the proper spot first!
        const isOutdoorsNow = (this.pos.y < 0.5 && this.pos.z > 6.98);
        if (bodyAction === 'mow_lawn' && target === 'stay') {
            const nearMower = (this.pos.y < 0.5 && Math.hypot(this.pos.x - 2.15, this.pos.z - 9.95) < 1.15);
            if (!nearMower) {
                target = 'lawn_mower';
            }
        } else if (target === 'stay' && !isOutdoorsNow) {
            if (bodyAction === 'water_plants') {
                target = 'hochbeet';
            } else if (bodyAction === 'garden_work') {
                target = (this.worldState.season === 'winter') ? 'hochbeet' : 'flowerbed';
            } else if (bodyAction === 'sweep_terrace') {
                target = 'terrace';
            }
        }

        const applyBodyActionNow = (act) => {
            if (!act || act === 'none') return;
            if (act === 'stand_up') {
                if (this.activeBodyAction === 'mow_lawn') this._parkLawnMowerHome();
                this.activeBodyAction = 'none';
                this.bodyActionUntilMs = 0;
                this.pendingBodyAction = null;
                if (this.rig.wateringCan) this.rig.wateringCan.visible = false;
                if (this.rig.gardenTrowel) this.rig.gardenTrowel.visible = false;
                if (this.rig.broom) this.rig.broom.visible = false;
                this.resetToCalm();
                return;
            }
            if (this.activeBodyAction === 'mow_lawn' && act !== 'mow_lawn') {
                this._parkLawnMowerHome();
            }
            if (this.carriedItemId && (act === 'mow_lawn' || act === 'water_plants' || act === 'garden_work' || act === 'sweep_terrace')) {
                this._placeItemImmediate(this.carriedItemId, this._nearestDropSpotKey(), null, true);
            }
            this.activeBodyAction = act;
            this.bodyActionStartMs = performance.now();
            if (act === 'duck') {
                const d = (durSec > 0 ? clamp(durSec, 2.5, 25) : 7.5) * 1000;
                this.bodyActionUntilMs = this.bodyActionStartMs + d;
                this.setExpression(opts.emotion || 'curious', 'duck', d);
            } else if (act === 'jump') {
                this.bodyActionUntilMs = this.bodyActionStartMs + 1550;
                this.setExpression(opts.emotion || 'excited', 'cheer', 2300);
            } else if (act === 'spin') {
                this.bodyActionUntilMs = this.bodyActionStartMs + 1400;
                this.setExpression(opts.emotion || 'excited', 'spin_arms', 2100);
            } else if (act === 'turn_around') {
                const d = (durSec > 0 ? clamp(durSec, 2.5, 22) : 6.5) * 1000;
                this.bodyActionUntilMs = this.bodyActionStartMs + d;
                this.setExpression(opts.emotion || 'happy', 'hands_on_hips', d);
            } else if (act === 'bow') {
                const d = (durSec > 0 ? clamp(durSec, 2.0, 8) : 3.6) * 1000;
                this.bodyActionUntilMs = this.bodyActionStartMs + d;
                this.setExpression(opts.emotion || 'sweet', 'bow', d);
            } else if (act === 'stretch') {
                const d = (durSec > 0 ? clamp(durSec, 2.0, 8) : 4.0) * 1000;
                this.bodyActionUntilMs = this.bodyActionStartMs + d;
                this.setExpression(opts.emotion || 'happy', 'stretch', d);
            } else if (act === 'lean_closer') {
                const d = (durSec > 0 ? clamp(durSec, 2.0, 15) : 5.5) * 1000;
                this.bodyActionUntilMs = this.bodyActionStartMs + d;
                this.setExpression(opts.emotion || 'curious', 'hands_on_hips', d);
            } else if (act === 'dance') {
                const d = (durSec > 0 ? clamp(durSec, 3.0, 22) : 7.5) * 1000;
                this.bodyActionUntilMs = this.bodyActionStartMs + d;
                this.setExpression(opts.emotion || 'excited', 'dance', d);
            } else if (act === 'water_plants') {
                const d = (durSec > 0 ? clamp(durSec, 4.0, 28) : 11.5) * 1000;
                this.bodyActionUntilMs = this.bodyActionStartMs + d;
                this.setExpression(opts.emotion || 'sweet', 'water_plants', d);
            } else if (act === 'garden_work') {
                const d = (durSec > 0 ? clamp(durSec, 4.0, 28) : 12.0) * 1000;
                this.bodyActionUntilMs = this.bodyActionStartMs + d;
                this.setExpression(opts.emotion || 'happy', 'garden_work', d);
            } else if (act === 'sweep_terrace') {
                const d = (durSec > 0 ? clamp(durSec, 4.0, 28) : 11.0) * 1000;
                this.bodyActionUntilMs = this.bodyActionStartMs + d;
                this.setExpression(opts.emotion || 'happy', 'sweep', d);
            } else if (act === 'mow_lawn') {
                const d = (durSec > 0 ? clamp(durSec, 6.0, 30) : 15.0) * 1000;
                this.bodyActionUntilMs = this.bodyActionStartMs + d;
                this._mowerParked = false;
                this._mowerLaneIdx = 0;
                this.moveSpeed = 1.25;
                this.cameraFocusMode = 'closeup_garden';
                const wp0 = this._mowerWaypoints[0];
                this.waypoints = [];
                this.targetFloor = 0;
                this.targetPos.set(wp0.x, 0.0, wp0.z);
                this.setExpression(opts.emotion || 'proud', 'push_mower', d);
            }
        };

        if (target === 'stay') {
            applyBodyActionNow(bodyAction);
            return { ok: true, target, bodyAction, room: this.currentRoom };
        }

        // Moving to a new spot: stand up & stow hand tools during walk, then trigger requested action upon arrival!
        if (this.activeBodyAction === 'mow_lawn') {
            this._parkLawnMowerHome();
        }
        if (this.activeBodyAction === 'duck' || this.activeBodyAction === 'bow' || this.activeBodyAction === 'lean_closer' ||
            this.activeBodyAction === 'water_plants' || this.activeBodyAction === 'garden_work' ||
            this.activeBodyAction === 'sweep_terrace' || this.activeBodyAction === 'mow_lawn') {
            this.activeBodyAction = 'none';
            this.bodyActionUntilMs = 0;
        }
        if (this.rig.wateringCan) this.rig.wateringCan.visible = false;
        if (this.rig.gardenTrowel) this.rig.gardenTrowel.visible = false;
        if (this.rig.broom) this.rig.broom.visible = false;
        this.pendingBodyAction = (bodyAction && bodyAction !== 'none' && bodyAction !== 'stand_up') ? bodyAction : null;
        this.pendingBodyDurationSec = this.pendingBodyAction ? durSec : 0;
        this.isSprintingHome = (rawSpeed === 'sprint');
        this.moveSpeed = rawSpeed === 'sprint' ? 4.85 : (rawSpeed === 'fast' ? 3.25 : 2.15);

        // Camera screen-relative basis vectors on XZ plane (works identically in Atelier & Dollhouse View!)
        let fx = (this.cameraLook ? this.cameraLook.x : 0) - (this.camera ? this.camera.position.x : 0);
        let fz = (this.cameraLook ? this.cameraLook.z : 5.45) - (this.camera ? this.camera.position.z : 2.26);
        const fl = Math.hypot(fx, fz) || 1;
        fx /= fl;
        fz /= fl;
        const rx = -fz; // Screen-Right X
        const rz = fx;  // Screen-Right Z

        const curFloor = this._currentFloorIndex();
        const inAtelier = (curFloor === 1 && this.pos.z >= 2.35 && !this._isOnStairs());

        if (target === 'step_left' || target === 'step_right' || target === 'step_aside' || target === 'closer_to_camera' || target === 'step_back') {
            this.customNavActive = true;
            this.cameraFocusMode = 'auto';
            this.customFacingYaw = null;

            let gx = this.pos.x;
            let gz = this.pos.z;
            let gFloor = curFloor;

            if (this._isOnStairs()) {
                // Step safely off stairs to nearest landing first
                gFloor = this.pos.y >= OG_Y * 0.5 ? 1 : 0;
                gx = gFloor === 1 ? -0.45 : 3.16;
                gz = gFloor === 1 ? 1.95 : 0.85;
            } else if (target === 'step_left') {
                gx = this.pos.x - rx * stepDist;
                gz = this.pos.z - rz * stepDist;
            } else if (target === 'step_right') {
                gx = this.pos.x + rx * stepDist;
                gz = this.pos.z + rz * stepDist;
            } else if (target === 'closer_to_camera') {
                if (inAtelier) {
                    gx = clamp(this.pos.x * 0.6, -1.2, 1.2);
                    gz = clamp(this.pos.z - stepDist, 3.06, 5.65);
                } else {
                    gx = this.pos.x - fx * stepDist;
                    gz = this.pos.z - fz * stepDist;
                }
            } else if (target === 'step_back') {
                if (inAtelier) {
                    gz = clamp(this.pos.z + stepDist, 3.20, 5.85);
                } else {
                    gx = this.pos.x + fx * stepDist;
                    gz = this.pos.z + fz * stepDist;
                }
            } else if (target === 'step_aside') {
                if (inAtelier) {
                    // Clear the entire center, Colleagues Photo (+0.52) & Golden Sign (-0.98) sightline!
                    gx = (this.pos.x > 0.95) ? -1.45 : 1.48;
                    gz = clamp(this.pos.z, 3.95, 4.95);
                } else {
                    const boxes = this._getFloorColliders(curFloor);
                    const candA = this._pushOutOfColliders(this.pos.x - rx * stepDist, this.pos.z - rz * stepDist, curFloor, 0.28, boxes);
                    const candB = this._pushOutOfColliders(this.pos.x + rx * stepDist, this.pos.z + rz * stepDist, curFloor, 0.28, boxes);
                    const distA = Math.hypot(candA.x - this.pos.x, candA.z - this.pos.z);
                    const distB = Math.hypot(candB.x - this.pos.x, candB.z - this.pos.z);
                    if (distA >= distB) { gx = candA.x; gz = candA.z; }
                    else { gx = candB.x; gz = candB.z; }
                }
            }

            this._routeTo(gx, gz, gFloor);
            return { ok: true, target, bodyAction, room: this.currentRoom };
        }

        if (target === 'stairs_mid') {
            this.customNavActive = true;
            this.cameraFocusMode = 'auto';
            this.customFacingYaw = null;
            const STAIR_BOT = { x: 3.16, z: 1.06 };
            const STAIR_TOP = { x: 0.22, z: 1.06 };
            let pts = [];
            if (this._isOnStairs()) {
                pts = [{ x: 1.77, y: OG_Y * 0.5, z: 1.06, floor: 'stairs' }];
            } else if (curFloor === 1) {
                pts = [
                    ...this._solveSingleFloorPath(this.pos.x, this.pos.z, STAIR_TOP.x, STAIR_TOP.z, 1),
                    { x: 0.76, y: OG_Y, z: 1.06, floor: 'stairs' },
                    { x: 1.77, y: OG_Y * 0.5, z: 1.06, floor: 'stairs' }
                ];
            } else {
                pts = [
                    ...this._solveSingleFloorPath(this.pos.x, this.pos.z, STAIR_BOT.x, STAIR_BOT.z, 0),
                    { x: 2.78, y: 0.0, z: 1.06, floor: 'stairs' },
                    { x: 1.77, y: OG_Y * 0.5, z: 1.06, floor: 'stairs' }
                ];
            }
            this.waypoints = pts;
            const first = this.waypoints.shift();
            if (first) {
                this.targetPos.set(first.x, first.y, first.z);
                this.targetFloor = first.floor;
            }
            return { ok: true, target, bodyAction, room: 'Treppe' };
        }

        // Named Landmarks & All Rooms across Floor 0 (EG + Terrasse + Garten) & Floor 1 (OG)
        const landmarkSpecs = {
            atelier_center:   { x:  0.00, z:  4.48, floor: 1, focus: 'auto',              yaw: null, resetCustom: true },
            stairs_up:        { x:  0.00, z:  4.48, floor: 1, focus: 'auto',              yaw: null, resetCustom: true },
            colleagues_photo: { x: -0.18, z:  5.58, floor: 1, focus: 'closeup_photo',     yaw: Math.PI - 0.28, emo: 'proud',   gest: 'point_photo', durMs: 10500 },
            brand_sign:       { x: -0.22, z:  5.52, floor: 1, focus: 'closeup_sign',      yaw: Math.PI + 0.28, emo: 'proud',   gest: 'point_right', durMs: 8500 },
            workbench:        { x: -1.62, z:  4.68, floor: 1, focus: 'closeup_workbench', yaw: Math.PI * 0.86, emo: 'happy',   gest: 'point_right', durMs: 7500 },
            laser:            { x:  1.72, z:  4.68, floor: 1, focus: 'closeup_laser',     yaw: -Math.PI * 0.86, emo: 'curious', gest: 'point_left', durMs: 7500 },
            atelier_window:   { x:  2.08, z:  4.92, floor: 1, focus: 'auto',              yaw: 0.0,            emo: 'sweet',   gest: 'hands_on_hips', durMs: 7500 },
            stairs_down:      { x:  3.16, z:  0.96, floor: 0, focus: 'auto',              yaw: null },
            kitchen:          { x:  2.35, z:  4.45, floor: 0, focus: 'auto',              yaw: null },
            living_room:      { x: -1.85, z:  1.95, floor: 0, focus: 'auto',              yaw: null },
            dining_room:      { x: -0.78, z:  5.35, floor: 0, focus: 'auto',              yaw: null },
            office:           { x: -1.08, z: -1.30, floor: 0, focus: 'auto',              yaw: null },
            bedroom:          { x: -1.45, z: -4.65, floor: 0, focus: 'auto',              yaw: null },
            gym:              { x:  1.25, z: -5.25, floor: 0, focus: 'auto',              yaw: null },
            bathroom_eg:      { x:  1.68, z: -3.15, floor: 0, focus: 'auto',              yaw: null },
            utility_room:     { x:  1.87, z: -0.68, floor: 0, focus: 'auto',              yaw: null },
            hallway:          { x:  3.16, z:  0.34, floor: 0, focus: 'auto',              yaw: null },
            og_studio:        { x: -1.45, z: -5.00, floor: 1, focus: 'auto',              yaw: null },
            og_bathroom:      { x:  1.32, z: -2.35, floor: 1, focus: 'auto',              yaw: null },
            og_gallery:       { x: -0.65, z:  1.95, floor: 1, focus: 'auto',              yaw: null },
            terrace:          { x: -0.72, z:  8.32, floor: 0, focus: 'closeup_garden',    yaw: null,            emo: 'happy',   gest: 'hands_on_hips', durMs: 8000 },
            garden:           { x: -0.35, z: 10.65, floor: 0, focus: 'closeup_garden',    yaw: null,            emo: 'happy',   gest: 'wave',          durMs: 8500 },
            hochbeet:         { x:  2.02, z:  8.60, floor: 0, focus: 'closeup_garden',    yaw: Math.PI * 0.5,   emo: 'sweet',   gest: 'hands_on_hips', durMs: 9000 },
            flowerbed:        { x: -2.45, z:  9.98, floor: 0, focus: 'closeup_garden',    yaw: 0.0,             emo: 'happy',   gest: 'hands_on_hips', durMs: 9000 },
            snowman:          { x: -2.62, z:  9.55, floor: 0, focus: 'closeup_garden',    yaw: -Math.PI * 0.42, emo: 'excited', gest: 'wave',          durMs: 8500 },
            lawn_mower:       { x:  2.18, z:  9.95, floor: 0, focus: 'closeup_garden',    yaw: Math.PI * 0.5,   emo: 'proud',   gest: 'point_left',    durMs: 8500 }
        };

        const spec = landmarkSpecs[target] || landmarkSpecs.atelier_center;
        this.customNavActive = !spec.resetCustom;
        this.cameraFocusMode = spec.focus || 'auto';
        this.customFacingYaw = spec.yaw;
        this._routeTo(spec.x, spec.z, spec.floor);

        if (this.carriedItemId) {
            this.setExpression(opts.emotion || 'happy', 'carry_hold', 8500);
        } else if (spec.emo || spec.gest) {
            this.setExpression(opts.emotion || spec.emo || 'happy', opts.gesture || spec.gest || 'idle', spec.durMs || 7000);
        } else if (opts.emotion || opts.gesture) {
            this.setExpression(opts.emotion || 'happy', opts.gesture || 'idle', 5500);
        }

        return { ok: true, target, bodyAction, floor: spec.floor };
    }

    runToCamera(spawnInHouseIfCentered = false) {
        if (spawnInHouseIfCentered && this.pos.y > 2.8 && this.pos.z > 3.2 && Math.abs(this.pos.x) < 1.0) {
            // Spawn downstairs in Wohn-/Esszimmer, Küche, or sunny Terrasse so Alina sees Funkira bound up the 3D Treppe!
            const spawnSpots = [
                { x: -1.85, z: 1.95, floor: 0 },
                { x:  2.35, z: 4.45, floor: 0 },
                { x: -0.72, z: 8.32, floor: 0 }
            ];
            const sp = spawnSpots[Math.floor(Math.random() * spawnSpots.length)];
            this.pos.set(sp.x, 0.0, sp.z);
            this.targetFloor = 0;
            if (this.rig.root) this.rig.root.position.copy(this.pos);
            if (this.camera) {
                this.camera.position.set(sp.x * 0.65, 7.6, sp.z + 6.8);
                this.cameraLook.set(sp.x, 0.85, sp.z - 0.5);
            }
        }
        this.mode = 'atelier_autonomous';
        this.setMode('front_active');
        this.setExpression('excited', 'wave', 6200);
    }

    setExpression(emotion, gesture, durationMs = 4800) {
        const now = performance.now();
        const normEmo = emotion === 'laugh' ? 'laughing' : (emotion === 'cry' ? 'sad' : emotion);
        if (normEmo && EMOTION_PRESETS[normEmo]) {
            this.emotion = normEmo;
            this.emotionStartedMs = now;
            if (normEmo === 'neutral' || (this.worldState.isLateNight && normEmo === 'sleepy' && durationMs <= 0)) {
                this.emotionUntilMs = 0;
            } else {
                const baseDur = durationMs > 0 ? durationMs : 4600;
                const maxEmoDur = (normEmo === 'laughing' || normEmo === 'excited' || normEmo === 'angry' || normEmo === 'sad')
                    ? Math.min(baseDur, 5200)
                    : Math.min(baseDur, 6500);
                this.emotionUntilMs = now + Math.max(1500, maxEmoDur);
            }
        }
        if (this.phoneCallActive) {
            this.gesture = 'talk_phone';
            this.gestureUntilMs = 0;
        } else if (gesture) {
            this.gesture = gesture;
            this.gestureUntilMs = (gesture !== 'idle' && durationMs > 0) ? (now + durationMs) : 0;
        }
    }

    settleAfterSpeech(graceMs = 1100) {
        if (this.mode !== 'front_active') return;
        const now = performance.now();
        const calmEmo = this.worldState.isLateNight ? 'sleepy' : 'neutral';
        if (this.emotion && this.emotion !== calmEmo && this.activeBodyAction === 'none' && !this.itemJob) {
            const targetEnd = now + Math.max(400, graceMs);
            this.emotionUntilMs = this.emotionUntilMs > 0 ? Math.min(this.emotionUntilMs, targetEnd) : targetEnd;
        }
        if (this.phoneCallActive) {
            this.gesture = 'talk_phone';
            this.gestureUntilMs = 0;
            return;
        }
        if (this.activeBodyAction !== 'none' || this.carriedItemId || this.itemJob) {
            return;
        }
        if (this.gesture && this.gesture !== 'idle' && this.gesture !== 'wave' && this.gesture !== 'point_photo' && this.gesture !== 'point_colleagues') {
            const gEnd = now + Math.max(500, graceMs + 250);
            this.gestureUntilMs = this.gestureUntilMs > 0 ? Math.min(this.gestureUntilMs, gEnd) : gEnd;
        }
    }

    resetToCalm() {
        this.emotion = this.worldState.isLateNight ? 'sleepy' : 'neutral';
        this.emotionUntilMs = 0;
        if (this.phoneCallActive) {
            this.gesture = 'talk_phone';
            this.gestureUntilMs = 0;
        } else if (this.carriedItemId) {
            this.gesture = 'carry_hold';
            this.gestureUntilMs = 0;
        } else {
            this.gesture = 'idle';
            this.gestureUntilMs = 0;
        }
    }

    setPhoneCallState(active, callerLabel = '') {
        this.phoneCallActive = !!active;
        this.phoneCallerLabel = active ? String(callerLabel || 'Anruf') : '';

        if (active) {
            if (this.carriedItemId) {
                this._placeItemImmediate(this.carriedItemId, this._nearestDropSpotKey(), null, true);
            }
            if (this.phoneScreenMat) {
                const prevTex = this.phoneScreenMat.map;
                this.phoneScreenMat.map = makeSmartphoneScreenTexture(this.phoneCallerLabel);
                this.phoneScreenMat.needsUpdate = true;
                if (prevTex && typeof prevTex.dispose === 'function') prevTex.dispose();
            }
            if (this.rig.smartphone) this.rig.smartphone.visible = true;
            if (this.rig.broom) this.rig.broom.visible = false;
            if (this.rig.cloth) this.rig.cloth.visible = false;
            if (this.rig.wateringCan) this.rig.wateringCan.visible = false;
            if (this.rig.gardenTrowel) this.rig.gardenTrowel.visible = false;
            this.setMode('front_active');
            this.emotion = 'happy';
            this.emotionStartedMs = performance.now();
            this.emotionUntilMs = 0;
            this.gesture = 'talk_phone';
            this.gestureUntilMs = 0;
        } else {
            if (this.rig.smartphone) this.rig.smartphone.visible = false;
            this.resetToCalm();
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
                this.pos.y + 2.35 + Math.random() * 1.0,
                this.pos.z + (Math.random() - 0.5) * 1.1
            );
            m.rotation.set(Math.random() * 3, Math.random() * 3, Math.random() * 3);
            this.scene.add(m);
            this.fallingPetals.push({
                mesh: m,
                minY: this.pos.y + 0.03,
                vy: 0.58 + Math.random() * 0.42,
                vx: (Math.random() - 0.5) * 0.38,
                spin: (Math.random() - 0.5) * 3.8,
                life: 3.4 + Math.random() * 1.1
            });
        }
    }

    // ========================================================================
    // 2-STORY HOUSE BUILDER: ERDGESCHOSS + 3D TREPPE + OBERGESCHOSS (DACHSCHRÄGEN)
    // ========================================================================
    _buildTwoStoryHouse() {
        const goldMat = this.mats.gold24k;
        const woodDarkMat = this.mats.woodDark;
        const oakMat = this.mats.oakWarm;
        const cognacMat = this.mats.cognacLeather;
        const plasterMat = this.mats.wallPlaster;
        const extWallMat = this.mats.wallExterior;
        const obsidianMat = this.mats.obsidian;
        const whiteMat = this.mats.ceramicWhite;
        const glowWallTex = makeWallGlowTexture();

        paintScenicWindowCanvas(this.windowCanvas, this.worldState.season, this.worldState.isLateNight, this.worldState.occasion);
        this.windowTexture = configureFastTexture(new THREE.CanvasTexture(this.windowCanvas));
        const skyMat = new THREE.MeshBasicMaterial({ map: this.windowTexture });

        this.weatherMat = new THREE.MeshBasicMaterial({ color: 0xE6A145, side: THREE.DoubleSide });
        const wpGeo = new THREE.PlaneGeometry(0.038, 0.038);

        this.egWeatherGroup = new THREE.Group();
        this.ogWeatherGroup = new THREE.Group();
        this.ogSouthWeatherGroup = new THREE.Group();
        this.scene.add(this.egWeatherGroup, this.ogWeatherGroup, this.ogSouthWeatherGroup);

        // Helper: Build framed architectural window + synchronized weather particles
        const addFramedWindow = (parentGrp, weatherGrp, x, y, z, w, h, rotY) => {
            const wg = new THREE.Group();
            wg.position.set(x, y, z);
            wg.rotation.y = rotY;

            const frame = new THREE.Mesh(new RoundedBoxGeometry(w + 0.12, h + 0.12, 0.08, 2, 0.015), goldMat);
            const pane = new THREE.Mesh(new THREE.PlaneGeometry(w, h), skyMat);
            pane.position.z = 0.045;
            const mullV = new THREE.Mesh(new RoundedBoxGeometry(0.032, h, 0.03, 1, 0.006), goldMat);
            mullV.position.z = 0.055;
            const mullH = new THREE.Mesh(new RoundedBoxGeometry(w, 0.032, 0.03, 1, 0.006), goldMat);
            mullH.position.set(0, -h * 0.08, 0.055);
            const sill = new THREE.Mesh(new RoundedBoxGeometry(w + 0.20, 0.055, 0.16, 1, 0.01), woodDarkMat);
            sill.position.set(0, -h * 0.5 - 0.04, 0.05);
            wg.add(frame, pane, mullV, mullH, sill);
            parentGrp.add(wg);

            // Spawn 6 synchronized weather particles in front of glass pane
            const nrmX = Math.sin(rotY);
            const nrmZ = Math.cos(rotY);
            const tanX = Math.cos(rotY);
            const tanZ = -Math.sin(rotY);
            const frontOff = 0.062;

            for (let i = 0; i < 6; i++) {
                const wp = new THREE.Mesh(wpGeo, this.weatherMat);
                wp.rotation.y = rotY;
                const uBase = (Math.random() - 0.5) * (w * 0.80);
                const relY = (Math.random() - 0.5) * (h * 0.80);
                wp.position.set(
                    x + nrmX * frontOff + tanX * uBase,
                    y + relY,
                    z + nrmZ * frontOff + tanZ * uBase
                );
                weatherGrp.add(wp);
                this.weatherParticles.push({
                    mesh: wp,
                    cx: x + nrmX * frontOff,
                    cy: y,
                    cz: z + nrmZ * frontOff,
                    tanX,
                    tanZ,
                    halfW: w * 0.42,
                    minY: y - h * 0.43,
                    maxY: y + h * 0.43,
                    uBase,
                    y: y + relY,
                    slopeDxPerY: 0,
                    speedY: 0.22 + Math.random() * 0.20,
                    phase: Math.random() * 6.28
                });
            }
        };

        // Helper: Build interior architectural cutaway wall segment with warm walnut/gold cap
        const addWallBox = (grp, x1, z1, x2, z2, yBase, height, thickness = 0.12, mat = plasterMat, capMat = woodDarkMat) => {
            const cx = (x1 + x2) * 0.5;
            const cz = (z1 + z2) * 0.5;
            const sx = Math.max(thickness, Math.abs(x2 - x1));
            const sz = Math.max(thickness, Math.abs(z2 - z1));
            const wall = new THREE.Mesh(new RoundedBoxGeometry(sx, height, sz, 1, 0.01), mat);
            wall.position.set(cx, yBase + height * 0.5, cz);
            const cap = new THREE.Mesh(new RoundedBoxGeometry(sx + 0.02, 0.035, sz + 0.02, 1, 0.006), capMat);
            cap.position.set(cx, yBase + height + 0.015, cz);
            grp.add(wall, cap);
        };

        // Helper: White ribbed Heizkörper radiator (Screenshot 2)
        const addRadiator = (grp, x, yBase, z, length, alongZ = true) => {
            const sx = alongZ ? 0.11 : length;
            const sz = alongZ ? length : 0.11;
            const body = new THREE.Mesh(new RoundedBoxGeometry(sx, 0.58, sz, 2, 0.02), whiteMat);
            body.position.set(x, yBase + 0.36, z);
            const topGrill = new THREE.Mesh(new RoundedBoxGeometry(sx + 0.01, 0.03, sz + 0.01, 1, 0.005), this.mats.silverSteel);
            topGrill.position.set(x, yBase + 0.66, z);
            grp.add(body, topGrill);
        };

        // Shared High-DPI Schwarz-Gold MEIN SEELENFUNKE Plaque Materials (Zero duplicate Canvas allocations!)
        this.plaqueMats = {
            obsidianMain: new THREE.MeshBasicMaterial({ map: makeSeelenfunkePlaqueTexture('MEIN SEELENFUNKE', 'MANUFAKTUR GIFHORN', 'obsidian') }),
            goldMain:     new THREE.MeshBasicMaterial({ map: makeSeelenfunkePlaqueTexture('MEIN SEELENFUNKE', 'HANDVEREDELT · 24K', 'gold') }),
            team:         new THREE.MeshBasicMaterial({ map: makeSeelenfunkePlaqueTexture('MEIN SEELENFUNKE', 'DAS GRÜNDER-TEAM', 'obsidian') }),
            trophy:       new THREE.MeshBasicMaterial({ map: makeSeelenfunkePlaqueTexture('MEIN SEELENFUNKE', 'EXZELLENZ-POKAL', 'obsidian') }),
            flowers:      new THREE.MeshBasicMaterial({ map: makeSeelenfunkePlaqueTexture('MEIN SEELENFUNKE', 'BLÜTENZAUBER · 24K', 'obsidian') }),
            garden:       new THREE.MeshBasicMaterial({ map: makeSeelenfunkePlaqueTexture('MEIN SEELENFUNKE', 'GOLDENER GARTEN', 'obsidian') }),
            mowerObs:     new THREE.MeshBasicMaterial({ map: makeSeelenfunkePlaqueTexture('SEELENFUNKE', 'GOLD EDITION · 24K', 'obsidian') }),
            mowerGold:    new THREE.MeshBasicMaterial({ map: makeSeelenfunkePlaqueTexture('SEELENFUNKE', 'MANUFAKTUR GARTEN', 'gold') })
        };

        // Helper: Framed Schwarz-Gold Gallery Wall Artwork signed "MEIN SEELENFUNKE"
        const addGoldenSignedWallArt = (parentGrp, x, y, z, w, h, rotY, motif, subLabel) => {
            const ag = new THREE.Group();
            ag.position.set(x, y, z);
            ag.rotation.y = rotY;
            const outer = new THREE.Mesh(new RoundedBoxGeometry(w + 0.09, h + 0.09, 0.042, 2, 0.014), goldMat);
            const matte = new THREE.Mesh(new RoundedBoxGeometry(w + 0.02, h + 0.02, 0.046, 1, 0.008), obsidianMat);
            const canvasMesh = new THREE.Mesh(
                new THREE.PlaneGeometry(w, h),
                new THREE.MeshBasicMaterial({ map: makeSeelenfunkeArtCanvasTexture(motif, subLabel) })
            );
            canvasMesh.position.z = 0.026;
            const namePlate = new THREE.Mesh(new THREE.PlaneGeometry(Math.min(w * 0.56, 0.44), 0.075), this.plaqueMats.obsidianMain);
            namePlate.position.set(0, -h * 0.5 - 0.022, 0.028);
            ag.add(outer, matte, canvasMesh, namePlate);
            parentGrp.add(ag);
        };

        // ====================================================================
        // 1. ERDGESCHOSS (y = 0.00m, 8.50m x 14.00m — Screenshot 1)
        // ====================================================================
        const eg = new THREE.Group();
        this.egGroup = eg;

        const egSlab = new THREE.Mesh(new THREE.BoxGeometry(8.62, 0.16, 14.12), obsidianMat);
        egSlab.position.set(0, -0.085, 0);
        const egFloor = new THREE.Mesh(
            new THREE.PlaneGeometry(HOUSE_W, HOUSE_L),
            new THREE.MeshBasicMaterial({ map: makeEgFloorTexture() })
        );
        egFloor.rotation.x = -Math.PI / 2;
        eg.add(egSlab, egFloor);

        // EG Exterior Walls (North, West, East full 2.75m height; South low 0.36m cutaway plinth)
        const egExtH = 2.75;
        addWallBox(eg, -4.24, -6.94,  4.24, -6.94, 0, egExtH, 0.14, extWallMat, goldMat); // North
        addWallBox(eg, -4.18, -6.94, -4.18,  6.94, 0, egExtH, 0.14, extWallMat, goldMat); // West
        addWallBox(eg,  4.18, -6.94,  4.18,  0.22, 0, egExtH, 0.14, extWallMat, goldMat); // East upper
        addWallBox(eg,  4.18,  1.42,  4.18,  6.94, 0, egExtH, 0.14, extWallMat, goldMat); // East lower
        addWallBox(eg, -4.24,  6.94, -1.24,  6.94, 0, 0.34,   0.14, extWallMat, goldMat); // South cutaway sill West of Terrassentür
        addWallBox(eg, -0.20,  6.94,  4.24,  6.94, 0, 0.34,   0.14, extWallMat, goldMat); // South cutaway sill East of Terrassentür

        // Offene Terrassentür (x = -1.24 .. -0.20, z = +6.94) vom Wohn-/Esszimmer auf die Terrasse
        const patioThreshold = new THREE.Mesh(new RoundedBoxGeometry(1.06, 0.024, 0.22, 1, 0.006), goldMat);
        patioThreshold.position.set(-0.72, 0.012, 6.94);
        const patioDoorFrame = new THREE.Mesh(new RoundedBoxGeometry(0.06, 1.32, 0.92, 2, 0.015), goldMat);
        patioDoorFrame.position.set(-1.30, 0.68, 7.35);
        patioDoorFrame.rotation.y = 0.22;
        const patioDoorGlass = new THREE.Mesh(new THREE.PlaneGeometry(0.78, 1.18), this.mats.showerGlass);
        patioDoorGlass.position.set(-1.29, 0.68, 7.35);
        patioDoorGlass.rotation.y = Math.PI * 0.5 + 0.22;
        eg.add(patioThreshold, patioDoorFrame, patioDoorGlass);

        // Eingang (1.95m zone on East wall x = +4.18, z = +0.82)
        const frontDoor = new THREE.Mesh(new RoundedBoxGeometry(0.10, 2.22, 1.12, 2, 0.02), woodDarkMat);
        frontDoor.position.set(4.15, 1.11, 0.82);
        const doorGlass = new THREE.Mesh(new THREE.PlaneGeometry(0.24, 1.62), this.mats.showerGlass);
        doorGlass.rotation.y = -Math.PI / 2;
        doorGlass.position.set(4.09, 1.18, 0.82);
        const doorHandle = new THREE.Mesh(new RoundedBoxGeometry(0.04, 0.90, 0.03, 1, 0.01), goldMat);
        doorHandle.position.set(4.08, 1.08, 0.44);
        const doorMatMesh = new THREE.Mesh(new RoundedBoxGeometry(0.58, 0.015, 1.05, 1, 0.005), obsidianMat);
        doorMatMesh.position.set(3.72, 0.008, 0.82);
        eg.add(frontDoor, doorGlass, doorHandle, doorMatMesh);

        // EG Exterior Windows (7 windows with sky + synchronized snow!)
        addFramedWindow(eg, this.egWeatherGroup, -2.35, 1.62, -6.85, 1.58, 1.28, 0);            // Schlafzimmer North
        addFramedWindow(eg, this.egWeatherGroup, -4.09, 1.62, -1.30, 1.32, 1.24, Math.PI / 2);  // Büro West
        addFramedWindow(eg, this.egWeatherGroup,  4.09, 1.62, -5.65, 1.36, 1.24, -Math.PI / 2); // Trainingsraum East
        addFramedWindow(eg, this.egWeatherGroup,  4.09, 1.62, -3.25, 1.22, 1.18, -Math.PI / 2); // Großes Bad East
        addFramedWindow(eg, this.egWeatherGroup,  4.09, 1.68,  2.28, 0.72, 0.88, -Math.PI / 2); // Gäste-WC East
        addFramedWindow(eg, this.egWeatherGroup,  4.09, 1.62,  3.88, 1.18, 1.22, -Math.PI / 2); // Küche East 1
        addFramedWindow(eg, this.egWeatherGroup,  4.09, 1.62,  5.58, 1.18, 1.22, -Math.PI / 2); // Küche East 2

        // EG Interior Cutaway Partition Walls (1.26m height -> razor-sharp floorplan + 100% furniture visibility!)
        const egIntH = 1.26;
        addWallBox(eg, -4.12, -2.61, -0.07, -2.61, 0, egIntH); // Schlafzimmer / Büro
        addWallBox(eg, -4.12,  0.01, -0.07,  0.01, 0, egIntH); // Büro / Wohnzimmer
        addWallBox(eg,  0.12, -6.88,  0.12, -4.48, 0, egIntH); // Schlafzimmer / Trainingsraum (3.96m!)
        addWallBox(eg, -0.07, -4.48,  0.14, -4.48, 0, egIntH); // Step corner
        addWallBox(eg, -0.07, -4.48, -0.07, -3.88, 0, egIntH); // Schlafzimmer door jamb N
        addWallBox(eg, -0.07, -2.86, -0.07, -1.52, 0, egIntH); // Between Schlafzimmer & Büro doors
        addWallBox(eg, -0.07, -0.50, -0.07,  0.42, 0, egIntH); // Between Büro & Wohnzimmer doors
        addWallBox(eg, -0.07,  1.46, -0.07,  6.88, 0, egIntH); // Wohnzimmer / Diele & Küche
        addWallBox(eg,  1.14, -4.48,  4.12, -4.48, 0, egIntH); // Trainingsraum South (doorway 0.18..1.14)
        addWallBox(eg,  1.19, -4.48,  1.19, -3.66, 0, egIntH); // Flur / Bad N
        addWallBox(eg,  1.19, -2.64,  1.19, -0.06, 0, egIntH); // Flur / Bad S & Heizungsraum
        addWallBox(eg,  1.19, -2.05,  4.12, -2.05, 0, egIntH); // Bad / Heizungsraum
        addWallBox(eg,  1.06, -0.06,  1.38, -0.06, 0, egIntH); // Pillar between Flur & Heizungsraum doors
        addWallBox(eg,  2.36, -0.06,  4.12, -0.06, 0, egIntH); // Heizungsraum South
        addWallBox(eg,  2.82,  1.68,  4.12,  1.68, 0, egIntH); // Gäste-WC North
        addWallBox(eg,  2.82,  2.87,  4.12,  2.87, 0, egIntH); // Gäste-WC South
        addWallBox(eg,  2.82,  1.68,  2.82,  1.95, 0, egIntH); // Gäste-WC West N
        addWallBox(eg,  2.82,  2.52,  2.82,  2.87, 0, egIntH); // Gäste-WC West S
        addWallBox(eg, -0.07,  2.55,  1.50,  2.55, 0, egIntH); // Diele / Küche W
        addWallBox(eg,  2.58,  2.55,  2.84,  2.55, 0, egIntH); // Diele / Küche E

        // --- EG Room 1: Schlafzimmer (16.57 m²) ---
        const bedFrame = new THREE.Mesh(new RoundedBoxGeometry(2.04, 0.34, 1.84, 2, 0.03), oakMat);
        bedFrame.position.set(-3.04, 0.17, -4.78);
        const bedHead = new THREE.Mesh(new RoundedBoxGeometry(0.14, 1.05, 1.92, 2, 0.03), woodDarkMat);
        bedHead.position.set(-4.02, 0.525, -4.78);
        const mattress = new THREE.Mesh(new RoundedBoxGeometry(1.94, 0.20, 1.74, 3, 0.05), this.mats.snowWhite);
        mattress.position.set(-3.00, 0.42, -4.78);
        const duvet = new THREE.Mesh(new RoundedBoxGeometry(1.38, 0.23, 1.76, 3, 0.05), this.mats.velvetCream);
        duvet.position.set(-2.70, 0.44, -4.78);
        eg.add(bedFrame, bedHead, mattress, duvet);
        for (const pz of [-5.22, -4.34]) {
            const pillow = new THREE.Mesh(new RoundedBoxGeometry(0.38, 0.14, 0.62, 2, 0.04), goldMat);
            pillow.position.set(-3.68, 0.56, pz);
            pillow.rotation.z = -0.18;
            eg.add(pillow);
        }
        for (const nz of [-5.96, -3.60]) {
            const ns = new THREE.Mesh(new RoundedBoxGeometry(0.42, 0.46, 0.42, 2, 0.02), oakMat);
            ns.position.set(-3.84, 0.23, nz);
            eg.add(ns);
        }
        const wardrobe = new THREE.Mesh(new RoundedBoxGeometry(2.32, 1.95, 0.56, 2, 0.025), oakMat);
        wardrobe.position.set(-2.36, 0.975, -2.94);
        const wardTrim = new THREE.Mesh(new RoundedBoxGeometry(2.34, 0.04, 0.58, 1, 0.008), goldMat);
        wardTrim.position.set(-2.36, 1.96, -2.94);
        // Luxury Schwarz-Gold Betttruhe / Footboard Bench (dropSpots.bedroom surface y = 0.582!)
        const bedTrunk = new THREE.Mesh(new RoundedBoxGeometry(0.26, 0.54, 1.32, 2, 0.02), obsidianMat);
        bedTrunk.position.set(-2.10, 0.27, -4.78);
        const bedTrunkTop = new THREE.Mesh(new RoundedBoxGeometry(0.28, 0.044, 1.34, 2, 0.015), goldMat);
        bedTrunkTop.position.set(-2.10, 0.56, -4.78);
        const bedTrunkPlaque = new THREE.Mesh(new THREE.PlaneGeometry(0.32, 0.085), this.plaqueMats.obsidianMain);
        bedTrunkPlaque.position.set(-1.966, 0.34, -4.78);
        bedTrunkPlaque.rotation.y = Math.PI / 2;
        eg.add(wardrobe, wardTrim, bedTrunk, bedTrunkTop, bedTrunkPlaque);

        // --- EG Room 2: Trainingsraum (9.86 m² mit Hantelbank & Langhantel) ---
        const gymMat = new THREE.Mesh(new RoundedBoxGeometry(2.25, 0.016, 1.32, 1, 0.005), obsidianMat);
        gymMat.position.set(2.52, 0.008, -5.72);
        const hBenchPad = new THREE.Mesh(new RoundedBoxGeometry(1.26, 0.09, 0.34, 2, 0.02), obsidianMat);
        hBenchPad.position.set(2.38, 0.44, -5.72);
        const hBenchLeg1 = new THREE.Mesh(new RoundedBoxGeometry(0.08, 0.42, 0.42, 1, 0.01), this.mats.silverSteel);
        hBenchLeg1.position.set(1.88, 0.21, -5.72);
        const hBenchLeg2 = hBenchLeg1.clone();
        hBenchLeg2.position.set(2.88, 0.21, -5.72);
        const rackPostL = new THREE.Mesh(new RoundedBoxGeometry(0.06, 1.12, 0.06, 1, 0.01), obsidianMat);
        rackPostL.position.set(3.02, 0.56, -6.02);
        const rackPostR = rackPostL.clone();
        rackPostR.position.set(3.02, 0.56, -5.42);
        const barbellBar = new THREE.Mesh(new THREE.CylinderGeometry(0.018, 0.018, 1.56, 10), this.mats.silverSteel);
        barbellBar.rotation.x = Math.PI / 2;
        barbellBar.position.set(3.02, 0.96, -5.72);
        eg.add(gymMat, hBenchPad, hBenchLeg1, hBenchLeg2, rackPostL, rackPostR, barbellBar);
        for (const wz of [-6.36, -6.28, -5.16, -5.08]) {
            const plate = new THREE.Mesh(new THREE.CylinderGeometry(0.19, 0.19, 0.045, 16), obsidianMat);
            plate.rotation.x = Math.PI / 2;
            plate.position.set(3.02, 0.96, wz);
            eg.add(plate);
        }

        // --- EG Room 3: Großes Bad (Fliesen, Dusche, Badewanne, Waschbecken, WC) ---
        const showerTray = new THREE.Mesh(new RoundedBoxGeometry(0.88, 0.05, 0.86, 1, 0.01), whiteMat);
        showerTray.position.set(1.68, 0.025, -4.02);
        const showerPane = new THREE.Mesh(new THREE.BoxGeometry(0.03, 1.75, 0.84), this.mats.showerGlass);
        showerPane.position.set(2.12, 0.90, -4.02);
        const egTubOuter = new THREE.Mesh(new RoundedBoxGeometry(1.74, 0.58, 0.80, 3, 0.06), whiteMat);
        egTubOuter.position.set(3.12, 0.29, -4.02);
        const egTubInner = new THREE.Mesh(new RoundedBoxGeometry(1.48, 0.06, 0.58, 2, 0.04), this.mats.showerGlass);
        egTubInner.position.set(3.12, 0.56, -4.02);
        const egVanity = new THREE.Mesh(new RoundedBoxGeometry(0.86, 0.52, 0.46, 2, 0.02), woodDarkMat);
        egVanity.position.set(2.18, 0.42, -2.32);
        const egBasin = new THREE.Mesh(new THREE.CylinderGeometry(0.24, 0.18, 0.12, 16), whiteMat);
        egBasin.scale.set(1.3, 1, 0.85);
        egBasin.position.set(2.18, 0.74, -2.32);
        const egWcBowl = new THREE.Mesh(new RoundedBoxGeometry(0.38, 0.42, 0.52, 3, 0.08), whiteMat);
        egWcBowl.position.set(3.48, 0.21, -2.36);
        eg.add(showerTray, showerPane, egTubOuter, egTubInner, egVanity, egBasin, egWcBowl);

        // --- EG Room 4: Büro (10.25 m² — Doppel-Schreibtische, 2 Stühle, Rollcontainer, Regal) ---
        for (const dx of [-2.74, -2.06]) {
            const dTop = new THREE.Mesh(new RoundedBoxGeometry(0.66, 0.05, 1.56, 2, 0.015), whiteMat);
            dTop.position.set(dx, 0.74, -1.30);
            const legA = new THREE.Mesh(new RoundedBoxGeometry(0.60, 0.72, 0.05, 1, 0.01), this.mats.silverSteel);
            legA.position.set(dx, 0.36, -2.02);
            const legB = legA.clone();
            legB.position.set(dx, 0.36, -0.58);
            eg.add(dTop, legA, legB);
        }
        for (const [cx, rotY] of [[-3.32, Math.PI / 2], [-1.48, -Math.PI / 2]]) {
            const seat = new THREE.Mesh(new RoundedBoxGeometry(0.46, 0.08, 0.46, 2, 0.02), cognacMat);
            seat.position.set(cx, 0.48, -1.30);
            const back = new THREE.Mesh(new RoundedBoxGeometry(0.08, 0.44, 0.44, 2, 0.02), cognacMat);
            back.position.set(cx + (rotY > 0 ? -0.20 : 0.20), 0.72, -1.30);
            const stem = new THREE.Mesh(new THREE.CylinderGeometry(0.03, 0.03, 0.46, 8), obsidianMat);
            stem.position.set(cx, 0.23, -1.30);
            eg.add(seat, back, stem);
        }
        const roll1 = new THREE.Mesh(new RoundedBoxGeometry(0.44, 0.58, 0.48, 2, 0.02), whiteMat);
        roll1.position.set(-3.80, 0.29, -2.22);
        const officeShelf = new THREE.Mesh(new RoundedBoxGeometry(0.84, 1.22, 0.34, 2, 0.02), whiteMat);
        officeShelf.position.set(-0.80, 0.61, -0.20);
        eg.add(roll1, officeShelf);

        // --- EG Room 5: Heizungsraum (5.68 m² — Waschmaschine, Trockner, Heizung, Netzwerk-Schrank) ---
        for (const wx of [1.62, 2.30]) {
            const wm = new THREE.Mesh(new RoundedBoxGeometry(0.60, 0.85, 0.60, 2, 0.03), whiteMat);
            wm.position.set(wx, 0.425, -1.68);
            const porthole = new THREE.Mesh(new THREE.TorusGeometry(0.16, 0.025, 8, 16), obsidianMat);
            porthole.position.set(wx, 0.46, -1.37);
            eg.add(wm, porthole);
        }
        const boiler = new THREE.Mesh(new RoundedBoxGeometry(0.56, 1.65, 0.52, 2, 0.03), whiteMat);
        boiler.position.set(3.72, 0.825, -1.68);
        const netRack = new THREE.Mesh(new RoundedBoxGeometry(0.44, 0.62, 0.52, 2, 0.02), obsidianMat);
        netRack.position.set(3.82, 1.32, -0.92);
        const netLed = new THREE.Mesh(new RoundedBoxGeometry(0.46, 0.03, 0.54, 1, 0.005), this.mats.scarfRed);
        netLed.position.set(3.82, 1.63, -0.92);
        eg.add(boiler, netRack, netLed);

        // --- EG Room 6: Diele (13.96 m²) & Gäste-WC (1.19 m) ---
        const garderobe = new THREE.Mesh(new RoundedBoxGeometry(1.18, 1.15, 0.42, 2, 0.02), oakMat);
        garderobe.position.set(0.65, 0.575, 2.28);
        const guestWc = new THREE.Mesh(new RoundedBoxGeometry(0.36, 0.40, 0.48, 3, 0.07), whiteMat);
        guestWc.position.set(3.65, 0.20, 1.98);
        const guestSink = new THREE.Mesh(new RoundedBoxGeometry(0.42, 0.16, 0.32, 2, 0.03), whiteMat);
        guestSink.position.set(3.25, 0.82, 2.64);
        eg.add(garderobe, guestWc, guestSink);

        // --- EG Room 7: Wohn-/Esszimmer (25.88 m² — L-Ecksofa, TV-Board & 6er Esstisch) ---
        const lvRug = new THREE.Mesh(
            new THREE.PlaneGeometry(2.75, 2.55),
            new THREE.MeshBasicMaterial({ map: makeCozyLivingRugTexture() })
        );
        lvRug.rotation.x = -Math.PI / 2;
        lvRug.position.set(-2.35, 0.006, 2.15);
        eg.add(lvRug);

        // Large L-shaped Cognac Ecksofa (Screenshot 1)
        const sofaWestSeat = new THREE.Mesh(new RoundedBoxGeometry(0.92, 0.40, 2.58, 3, 0.05), cognacMat);
        sofaWestSeat.position.set(-3.54, 0.22, 2.40);
        const sofaWestBack = new THREE.Mesh(new RoundedBoxGeometry(0.22, 0.76, 2.62, 3, 0.05), cognacMat);
        sofaWestBack.position.set(-3.92, 0.42, 2.40);
        const sofaSouthSeat = new THREE.Mesh(new RoundedBoxGeometry(2.18, 0.40, 0.90, 3, 0.05), cognacMat);
        sofaSouthSeat.position.set(-2.48, 0.22, 3.45);
        const sofaSouthBack = new THREE.Mesh(new RoundedBoxGeometry(2.65, 0.76, 0.22, 3, 0.05), cognacMat);
        sofaSouthBack.position.set(-2.72, 0.42, 3.80);
        const mediaLowboard = new THREE.Mesh(new RoundedBoxGeometry(1.68, 0.44, 0.36, 2, 0.02), woodDarkMat);
        mediaLowboard.position.set(-2.22, 0.22, 0.24);
        const tvScreen = new THREE.Mesh(new RoundedBoxGeometry(1.42, 0.82, 0.04, 1, 0.01), obsidianMat);
        tvScreen.position.set(-2.22, 1.02, 0.14);
        eg.add(sofaWestSeat, sofaWestBack, sofaSouthSeat, sofaSouthBack, mediaLowboard, tvScreen);

        // Large Esstisch + 6 Stühle (Screenshot 1 bottom-left)
        const dineTop = new THREE.Mesh(new RoundedBoxGeometry(2.08, 0.065, 0.96, 2, 0.02), whiteMat);
        dineTop.position.set(-2.42, 0.76, 5.35);
        const dineRunner = new THREE.Mesh(new RoundedBoxGeometry(0.88, 0.015, 0.30, 1, 0.005), goldMat);
        dineRunner.position.set(-2.42, 0.80, 5.35);
        eg.add(dineTop, dineRunner);
        const dLegGeo = new THREE.CylinderGeometry(0.03, 0.022, 0.74, 10);
        for (const [lx, lz] of [[-3.34, 4.96], [-1.50, 4.96], [-3.34, 5.74], [-1.50, 5.74]]) {
            const dl = new THREE.Mesh(dLegGeo, oakMat);
            dl.position.set(lx, 0.37, lz);
            eg.add(dl);
        }
        for (const cx of [-3.05, -2.42, -1.79]) {
            for (const [cz, bOff] of [[4.68, -0.18], [6.02, 0.18]]) {
                const cSeat = new THREE.Mesh(new RoundedBoxGeometry(0.44, 0.06, 0.42, 2, 0.02), cognacMat);
                cSeat.position.set(cx, 0.46, cz);
                const cBack = new THREE.Mesh(new RoundedBoxGeometry(0.42, 0.42, 0.06, 2, 0.02), cognacMat);
                cBack.position.set(cx, 0.68, cz + bOff);
                const cStem = new THREE.Mesh(new THREE.CylinderGeometry(0.024, 0.024, 0.45, 8), woodDarkMat);
                cStem.position.set(cx, 0.225, cz);
                eg.add(cSeat, cBack, cStem);
            }
        }

        // --- EG Room 8: Küche (14.97 m² — L-Zeile Ost/Süd, Kühlschrank & Nord-Süd-Kücheninsel) ---
        const kEastBase = new THREE.Mesh(new RoundedBoxGeometry(0.70, 0.88, 3.82, 2, 0.025), oakMat);
        kEastBase.position.set(3.72, 0.44, 4.92);
        const kEastTop = new THREE.Mesh(new RoundedBoxGeometry(0.74, 0.055, 3.86, 2, 0.02), this.mats.marbleCream);
        kEastTop.position.set(3.72, 0.90, 4.92);
        const kSouthBase = new THREE.Mesh(new RoundedBoxGeometry(2.15, 0.88, 0.70, 2, 0.025), oakMat);
        kSouthBase.position.set(2.32, 0.44, 6.48);
        const kSouthTop = new THREE.Mesh(new RoundedBoxGeometry(2.19, 0.055, 0.74, 2, 0.02), this.mats.marbleCream);
        kSouthTop.position.set(2.32, 0.90, 6.48);
        const kFridge = new THREE.Mesh(new RoundedBoxGeometry(0.96, 2.02, 0.74, 2, 0.035), obsidianMat);
        kFridge.position.set(0.68, 1.01, 6.46);
        const kFridgeHandle = new THREE.Mesh(new RoundedBoxGeometry(0.03, 0.68, 0.04, 1, 0.008), goldMat);
        kFridgeHandle.position.set(1.06, 1.15, 6.07);
        const kSink = new THREE.Mesh(new RoundedBoxGeometry(0.44, 0.025, 0.66, 1, 0.01), this.mats.silverSteel);
        kSink.position.set(3.70, 0.93, 4.65);
        const kFaucet = new THREE.Mesh(new THREE.CylinderGeometry(0.02, 0.024, 0.28, 10), goldMat);
        kFaucet.position.set(3.92, 1.07, 4.65);
        const espBody = new THREE.Mesh(new RoundedBoxGeometry(0.36, 0.38, 0.40, 2, 0.03), this.mats.darkArmor);
        espBody.position.set(3.68, 1.11, 3.45);
        const espTop = new THREE.Mesh(new RoundedBoxGeometry(0.38, 0.03, 0.42, 1, 0.008), goldMat);
        espTop.position.set(3.68, 1.31, 3.45);
        eg.add(kEastBase, kEastTop, kSouthBase, kSouthTop, kFridge, kFridgeHandle, kSink, kFaucet, espBody, espTop);

        // Freestanding Vertical (North-South) Kücheninsel + Stools (Screenshot 1)
        const islBase = new THREE.Mesh(new RoundedBoxGeometry(0.88, 0.86, 1.86, 2, 0.03), woodDarkMat);
        islBase.position.set(0.86, 0.43, 4.45);
        const islTop = new THREE.Mesh(new RoundedBoxGeometry(0.98, 0.065, 1.96, 2, 0.025), whiteMat);
        islTop.position.set(0.86, 0.89, 4.45);
        const islBowl = new THREE.Mesh(new THREE.CylinderGeometry(0.18, 0.09, 0.08, 16), goldMat);
        islBowl.position.set(0.86, 0.96, 4.45);
        eg.add(islBase, islTop, islBowl);
        for (const [sx, sz] of [[0.86, 3.28], [0.86, 5.62], [1.54, 4.45]]) {
            const stSeat = new THREE.Mesh(new RoundedBoxGeometry(0.38, 0.06, 0.38, 2, 0.02), cognacMat);
            stSeat.position.set(sx, 0.62, sz);
            const stStem = new THREE.Mesh(new THREE.CylinderGeometry(0.024, 0.024, 0.60, 8), goldMat);
            stStem.position.set(sx, 0.30, sz);
            eg.add(stSeat, stStem);
        }

        // --- EG Signed 24K Gold "MEIN SEELENFUNKE" Gallery Wall Paintings across All Rooms ---
        addGoldenSignedWallArt(eg, -4.08, 1.70, -4.78, 1.26, 0.84,  Math.PI / 2, 'moon_sparks',      'STERNENFUNKE · MANUFAKTUR'); // Schlafzimmer
        addGoldenSignedWallArt(eg, -4.08, 1.64, -2.28, 0.78, 0.60,  Math.PI / 2, 'crest_manufaktur', 'ATELIER GIFHORN');           // Büro
        addGoldenSignedWallArt(eg, -4.08, 1.70,  2.40, 1.54, 0.94,  Math.PI / 2, 'spark_mandala',    'MEIN SEELENFUNKE EDITION');  // Wohnzimmer
        addGoldenSignedWallArt(eg, -4.08, 1.68,  5.35, 1.22, 0.82,  Math.PI / 2, 'botanical_gold',   'GOLDENE BLÜTE · 24K');       // Esszimmer
        addGoldenSignedWallArt(eg,  2.52, 1.70, -6.84, 1.28, 0.84,  0.0,         'crest_manufaktur', 'FUNKIRA POWER · 24K');       // Trainingsraum
        addGoldenSignedWallArt(eg,  4.08, 1.66,  4.73, 0.68, 0.76, -Math.PI / 2, 'golden_heart',     'HERZENSFUNKE');              // Küche

        this.scene.add(eg);
        freezeStaticTree(eg);

        // Dynamic EG & Outdoor Movable Items Container (never frozen so Funkira can carry & place!)
        this.egItemsGroup = new THREE.Group();
        this.egItemsGroup.matrixAutoUpdate = true;
        this.scene.add(this.egItemsGroup);

        // ====================================================================
        // 2. 3D TREPPE NACH OBEN (Always Visible: EG Diele <-> OG Galerie)
        // ====================================================================
        const stairs = new THREE.Group();
        this.stairsGroup = stairs;
        const stepCount = 14;
        for (let s = 0; s < stepCount; s++) {
            const frac = (s + 0.5) / stepCount;
            const sx = lerp(2.78, 0.76, frac);
            const sy = (s + 1) * (OG_Y / stepCount);
            const tread = new THREE.Mesh(new RoundedBoxGeometry(0.22, 0.055, 0.88, 1, 0.01), woodDarkMat);
            tread.position.set(sx, sy - 0.028, 1.06);
            const nose = new THREE.Mesh(new RoundedBoxGeometry(0.025, 0.058, 0.89, 1, 0.005), goldMat);
            nose.position.set(sx + 0.10, sy - 0.026, 1.06);
            stairs.add(tread, nose);
        }
        const stairLen = Math.hypot(2.02, OG_Y);
        const stairPitch = Math.atan2(OG_Y, 2.02);
        for (const sz of [0.60, 1.52]) {
            const stringer = new THREE.Mesh(new RoundedBoxGeometry(stairLen, 0.16, 0.06, 1, 0.01), obsidianMat);
            stringer.position.set(1.77, OG_Y * 0.5 - 0.10, sz);
            stringer.rotation.z = -stairPitch;
            stairs.add(stringer);
        }
        this.scene.add(stairs);
        freezeStaticTree(stairs);

        // ====================================================================
        // 3. OBERGESCHOSS (y = 3.10m, 1.25m Dachschrägen + Seelenfunken Atelier)
        // ====================================================================
        const og = new THREE.Group();
        this.ogGroup = og;

        // Split OG Floor around the Stairwell Opening (x = +0.74 .. +2.88, z = +0.58 .. +1.54)
        // so you can genuinely look down the 3D Treppe into the Erdgeschoss!
        const ogFloorTex = makeOgFloorTexture();
        const addOgFloorPatch = (x0, z0, x1, z1) => {
            const pw = x1 - x0;
            const pd = z1 - z0;
            const geo = new THREE.PlaneGeometry(pw, pd);
            const uv = geo.attributes.uv;
            const u0 = (x0 + 4.25) / HOUSE_W;
            const u1 = (x1 + 4.25) / HOUSE_W;
            // PlaneGeometry rotated -PI/2 around X maps v=1 -> -Z (top) and v=0 -> +Z (bottom)
            const vTop = 1.0 - (z0 + 7.00) / HOUSE_L;
            const vBot = 1.0 - (z1 + 7.00) / HOUSE_L;
            uv.setXY(0, u0, vTop);
            uv.setXY(1, u1, vTop);
            uv.setXY(2, u0, vBot);
            uv.setXY(3, u1, vBot);
            const m = new THREE.Mesh(geo, new THREE.MeshBasicMaterial({ map: ogFloorTex }));
            m.rotation.x = -Math.PI / 2;
            m.position.set((x0 + x1) * 0.5, OG_Y, (z0 + z1) * 0.5);
            const under = new THREE.Mesh(new THREE.BoxGeometry(pw, 0.10, pd), obsidianMat);
            under.position.set((x0 + x1) * 0.5, OG_Y - 0.055, (z0 + z1) * 0.5);
            og.add(m, under);
        };
        addOgFloorPatch(-4.25, -7.00,  4.25,  0.58); // North half above stairwell
        addOgFloorPatch(-4.25,  0.58,  0.74,  1.54); // West gallery beside stairwell
        addOgFloorPatch( 2.88,  0.58,  4.25,  1.54); // East eaves beside stairwell
        addOgFloorPatch(-4.25,  1.54,  4.25,  7.00); // South half (Geländer + Seelenfunken Atelier!)

        // --- DACHSCHRÄGEN ARCHITECTURE ("Denk an die Dachschrägen" — 1.25m left & right, 6.00m center) ---
        // Left & Right Kniestock (Knee-Walls at x = -3.00 and x = +3.00, height = 1.32m)
        const knieH = 1.32;
        addWallBox(og, -3.00, -6.92, -3.00,  6.90, OG_Y, knieH, 0.10, obsidianMat, goldMat);
        addWallBox(og,  3.00, -6.92,  3.00,  6.90, OG_Y, knieH, 0.10, obsidianMat, goldMat);

        // Sloped Eaves Roof Aprons (x = ±4.25 @ OG_Y+0.28 -> x = ±3.00 @ OG_Y+1.32) + Exposed Walnut Dachsparren (/ \)
        const roofAngle = Math.atan2(3.36 - 1.32, 3.00); // ~34.2° true pitched roof slope!
        const eaveLen = Math.hypot(1.25, 1.04);
        for (const sx of [-1, 1]) {
            const eaveApron = new THREE.Mesh(new RoundedBoxGeometry(eaveLen, 0.08, 13.88, 1, 0.01), woodDarkMat);
            eaveApron.position.set(sx * 3.625, OG_Y + 0.80, 0);
            eaveApron.rotation.z = -sx * roofAngle;
            og.add(eaveApron);
        }

        // Exposed Walnut & Gold Dachsparren Rafters (/ \) + Central Firstbalken Ridge Beam
        const rafterLen = Math.hypot(3.02, 2.04);
        const ridgeBeam = new THREE.Mesh(new RoundedBoxGeometry(0.16, 0.18, 13.88, 2, 0.02), woodDarkMat);
        ridgeBeam.position.set(0, OG_Y + 3.34, 0);
        const ridgeGold = new THREE.Mesh(new RoundedBoxGeometry(0.17, 0.03, 13.88, 1, 0.005), goldMat);
        ridgeGold.position.set(0, OG_Y + 3.24, 0);
        og.add(ridgeBeam, ridgeGold);

        for (const rz of [-6.80, -4.21, -2.05, -0.10, 2.28, 4.55, 6.88]) {
            for (const sx of [-1, 1]) {
                const rafter = new THREE.Mesh(new RoundedBoxGeometry(rafterLen, 0.11, 0.11, 1, 0.015), woodDarkMat);
                rafter.position.set(sx * 1.50, OG_Y + 2.34, rz);
                rafter.rotation.z = -sx * roofAngle;
                og.add(rafter);
            }
        }

        // North Structural Wooden Center Posts (kept behind Atelier camera so Atelier & Group Photo sightlines stay 100% unobstructed!)
        for (const pz of [-6.78, -0.08]) {
            const post = new THREE.Mesh(new RoundedBoxGeometry(0.15, 3.30, 0.15, 1, 0.015), woodDarkMat);
            post.position.set(-0.08, OG_Y + 1.65, pz);
            og.add(post);
        }

        // North Gable Wall (z = -6.90) + 2 North Windows + 2 White Desks + Cognac Chairs + Schranksystem
        const northGable = new THREE.Mesh(
            new THREE.ShapeGeometry(createGableWallShape()),
            extWallMat
        );
        northGable.position.set(0, OG_Y, -6.90);
        og.add(northGable);
        addFramedWindow(og, this.ogWeatherGroup, -1.48, OG_Y + 1.36, -6.82, 1.24, 1.08, 0);
        addFramedWindow(og, this.ogWeatherGroup,  1.22, OG_Y + 1.36, -6.82, 1.24, 1.08, 0);

        for (const dx of [-1.48, 1.22]) {
            const ndTop = new THREE.Mesh(new RoundedBoxGeometry(1.68, 0.055, 0.76, 2, 0.015), whiteMat);
            ndTop.position.set(dx, OG_Y + 0.75, -6.34);
            const ndLegL = new THREE.Mesh(new RoundedBoxGeometry(0.05, 0.73, 0.70, 1, 0.01), this.mats.silverSteel);
            ndLegL.position.set(dx - 0.78, OG_Y + 0.365, -6.34);
            const ndLegR = ndLegL.clone();
            ndLegR.position.set(dx + 0.78, OG_Y + 0.365, -6.34);
            const chSeat = new THREE.Mesh(new RoundedBoxGeometry(0.46, 0.08, 0.46, 2, 0.02), cognacMat);
            chSeat.position.set(dx, OG_Y + 0.48, -5.68);
            chSeat.rotation.y = 0.35;
            const chBack = new THREE.Mesh(new RoundedBoxGeometry(0.44, 0.42, 0.08, 2, 0.02), cognacMat);
            chBack.position.set(dx + 0.06, OG_Y + 0.72, -5.48);
            chBack.rotation.y = 0.35;
            const chStem = new THREE.Mesh(new THREE.CylinderGeometry(0.03, 0.03, 0.46, 8), obsidianMat);
            chStem.position.set(dx, OG_Y + 0.23, -5.68);
            og.add(ndTop, ndLegL, ndLegR, chSeat, chBack, chStem);
        }
        const schrankSys = new THREE.Mesh(new RoundedBoxGeometry(0.52, 1.28, 2.48, 2, 0.02), woodDarkMat);
        schrankSys.position.set(2.70, OG_Y + 0.64, -5.52);
        og.add(schrankSys);

        // --- OG-Bad (4.11m long, z = -4.21 .. -0.10, Fliesen + Badewanne + Waschbecken + WC + Heizkörper + Dachfenster) ---
        addWallBox(og, -0.06, -4.21,  3.00, -4.21, OG_Y, knieH);
        addWallBox(og, -0.06, -0.10,  3.00, -0.10, OG_Y, knieH);
        addWallBox(og, -0.06, -4.21, -0.06, -1.88, OG_Y, knieH);
        addWallBox(og, -0.06, -0.86, -0.06, -0.10, OG_Y, knieH);

        const ogTubDeck = new THREE.Mesh(new RoundedBoxGeometry(2.78, 0.58, 0.84, 3, 0.05), whiteMat);
        ogTubDeck.position.set(1.48, OG_Y + 0.29, -3.72);
        const ogTubWater = new THREE.Mesh(new RoundedBoxGeometry(2.15, 0.05, 0.58, 2, 0.04), this.mats.showerGlass);
        ogTubWater.position.set(1.48, OG_Y + 0.56, -3.72);
        const ogVanity = new THREE.Mesh(new RoundedBoxGeometry(0.48, 0.54, 0.92, 2, 0.02), whiteMat);
        ogVanity.position.set(0.26, OG_Y + 0.44, -2.35);
        const ogBasin = new THREE.Mesh(new THREE.CylinderGeometry(0.22, 0.17, 0.12, 16), whiteMat);
        ogBasin.position.set(0.28, OG_Y + 0.76, -2.35);
        const ogWc = new THREE.Mesh(new RoundedBoxGeometry(0.54, 0.42, 0.38, 3, 0.07), whiteMat);
        ogWc.position.set(2.58, OG_Y + 0.21, -2.35);
        og.add(ogTubDeck, ogTubWater, ogVanity, ogBasin, ogWc);
        addRadiator(og, 1.68, OG_Y, -0.22, 1.45, false); // OG-Bad Heizkörper

        // Sloped Dachfenster (Velux Skylight in East Dachschräge above OG-Bad WC!)
        const veluxGroup = new THREE.Group();
        veluxGroup.position.set(3.48, OG_Y + 0.96, -2.35);
        veluxGroup.rotation.z = -roofAngle;
        const veluxFrame = new THREE.Mesh(new RoundedBoxGeometry(1.08, 0.07, 1.02, 2, 0.015), goldMat);
        const veluxPane = new THREE.Mesh(new THREE.PlaneGeometry(0.94, 0.88), skyMat);
        veluxPane.rotation.x = -Math.PI / 2;
        veluxPane.position.y = 0.042;
        veluxGroup.add(veluxFrame, veluxPane);
        og.add(veluxGroup);
        for (let i = 0; i < 6; i++) {
            const wp = new THREE.Mesh(wpGeo, this.weatherMat);
            wp.rotation.x = -Math.PI / 2;
            wp.rotation.y = 0;
            wp.rotation.z = -roofAngle;
            const uBase = (Math.random() - 0.5) * 0.72;
            const yRel = (Math.random() - 0.5) * 0.44;
            const slopeDx = 1.0 / Math.tan(roofAngle);
            wp.position.set(3.48 - yRel * slopeDx, OG_Y + 1.03 + yRel, -2.35 + uBase);
            this.ogWeatherGroup.add(wp);
            this.weatherParticles.push({
                mesh: wp,
                cx: 3.48,
                cy: OG_Y + 1.03,
                cz: -2.35,
                tanX: 0,
                tanZ: 1,
                halfW: 0.38,
                minY: OG_Y + 0.78,
                maxY: OG_Y + 1.28,
                uBase,
                y: OG_Y + 1.03 + yRel,
                slopeDxPerY: -slopeDx,
                speedY: 0.16 + Math.random() * 0.14,
                phase: Math.random() * 6.28
            });
        }

        // 2 West Knee-Wall Heizkörper (Screenshot 2 left side)
        addRadiator(og, -2.88, OG_Y, -2.85, 1.82, true);
        addRadiator(og, -2.88, OG_Y,  1.05, 1.82, true);

        // Matte Black Geländer ("Das ist ein Geländer" at z = +1.92, x = +0.48 .. +2.98)
        const gelTop = new THREE.Mesh(new RoundedBoxGeometry(2.52, 0.055, 0.065, 2, 0.015), obsidianMat);
        gelTop.position.set(1.74, OG_Y + 1.00, 1.92);
        const gelBot = new THREE.Mesh(new RoundedBoxGeometry(2.52, 0.04, 0.05, 1, 0.01), obsidianMat);
        gelBot.position.set(1.74, OG_Y + 0.08, 1.92);
        og.add(gelTop, gelBot);
        for (let b = 0; b <= 10; b++) {
            const bx = 0.50 + b * 0.246;
            const bar = new THREE.Mesh(new RoundedBoxGeometry(0.032, 0.94, 0.032, 1, 0.005), obsidianMat);
            bar.position.set(bx, OG_Y + 0.53, 1.92);
            og.add(bar);
        }
        // Safety side-rail on North edge of stairwell opening
        const stairBackGuard = new THREE.Mesh(new RoundedBoxGeometry(2.22, 0.96, 0.05, 1, 0.01), obsidianMat);
        stairBackGuard.position.set(1.85, OG_Y + 0.48, 0.56);
        og.add(stairBackGuard);

        // ====================================================================
        // 4. SEELENFUNKEN ATELIER ENCLOSED GABLE & DACHSCHRÄGEN CEILING
        // (Visible in front_active Atelier view; cutaway in Dollhouse Pause Mode!)
        // ====================================================================
        const atelierClosed = new THREE.Group();
        this.ogRoofClosedGroup = atelierClosed;

        // Sloped Dachschrägen Ceiling Panels over Seelenfunken Atelier (z = +1.85 .. +6.90)
        const slopeCeilingMat = new THREE.MeshMatcapMaterial({
            matcap: makeStudioMatcapTexture('#231b17', '#6e5645', '#C5A059', 12, 0.16)
        });
        for (const sx of [-1, 1]) {
            const ceilSlope = new THREE.Mesh(new RoundedBoxGeometry(rafterLen, 0.06, 5.10, 1, 0.01), slopeCeilingMat);
            ceilSlope.position.set(sx * 1.50, OG_Y + 2.38, 4.38);
            ceilSlope.rotation.z = -sx * roofAngle;
            atelierClosed.add(ceilSlope);
        }

        // Pentagonal South Atelier Gable Wall (z = +6.84, facing -Z toward camera!)
        const gableGeo = new THREE.ShapeGeometry(createGableWallShape());
        gableGeo.computeBoundingBox();
        const gbb = gableGeo.boundingBox;
        const gp = gableGeo.attributes.position;
        const guv = gableGeo.attributes.uv;
        for (let i = 0; i < gp.count; i++) {
            guv.setXY(
                i,
                ((gp.getX(i) - gbb.min.x) / (gbb.max.x - gbb.min.x)) * 1.8,
                (gp.getY(i) - gbb.min.y) / (gbb.max.y - gbb.min.y)
            );
        }
        const southGableWall = new THREE.Mesh(
            gableGeo,
            new THREE.MeshBasicMaterial({ map: makeBakedWallTexture(), side: THREE.DoubleSide })
        );
        southGableWall.position.set(0, OG_Y, 6.84);
        southGableWall.rotation.y = Math.PI;
        const southBaseboard = new THREE.Mesh(new RoundedBoxGeometry(5.96, 0.10, 0.06, 2, 0.015), goldMat);
        southBaseboard.position.set(0, OG_Y + 0.05, 6.80);
        atelierClosed.add(southGableWall, southBaseboard);

        // 2 South Atelier Gable Windows (Screenshot 2 bottom edge: left & right of center!)
        addFramedWindow(atelierClosed, this.ogSouthWeatherGroup,  2.12, OG_Y + 1.18, 6.79, 1.10, 1.16, Math.PI);
        addFramedWindow(atelierClosed, this.ogSouthWeatherGroup, -2.12, OG_Y + 1.18, 6.79, 1.10, 1.16, Math.PI);

        // Volumetric Golden Sunbeams from South Atelier Windows
        const sunbeamMat = new THREE.MeshBasicMaterial({
            map: makeSunbeamTexture(),
            transparent: true,
            depthWrite: false,
            blending: THREE.AdditiveBlending,
            side: THREE.DoubleSide
        });
        const sunbeamGeo = new THREE.PlaneGeometry(1.45, 2.65);
        this.sunbeamMeshes = [];
        for (const [sbX, sbRotZ] of [[1.78, -0.34], [-1.78, 0.34]]) {
            const sb = new THREE.Mesh(sunbeamGeo, sunbeamMat);
            sb.position.set(sbX, OG_Y + 1.18, 6.18);
            sb.rotation.y = Math.PI;
            sb.rotation.z = sbRotZ;
            this.ogSouthWeatherGroup.add(sb);
            this.sunbeamMeshes.push(sb);
        }
        this.sunbeamMesh = this.sunbeamMeshes[0];

        // EASTER EGG: 24K Gold Framed 12-Colleagues Portrait ('DAS GRÜNDER-TEAM · MEIN SEELENFUNKE')
        // Positioned at x = +0.52 (Screen-Center-Left when facing +Z into Atelier)
        const photoGroup = new THREE.Group();
        photoGroup.position.set(0.52, OG_Y + 1.74, 6.78);
        photoGroup.rotation.y = Math.PI;

        const photoWallHalo = new THREE.Mesh(
            new THREE.PlaneGeometry(2.15, 1.42),
            new THREE.MeshBasicMaterial({ map: glowWallTex, transparent: true, depthWrite: false })
        );
        photoWallHalo.position.set(0, 0.05, -0.02);
        const frameOuterMesh = new THREE.Mesh(new RoundedBoxGeometry(1.48, 0.92, 0.06, 2, 0.022), goldMat);
        const frameInnerMatte = new THREE.Mesh(new RoundedBoxGeometry(1.35, 0.79, 0.065, 1, 0.01), obsidianMat);
        photoGroup.add(photoWallHalo, frameOuterMesh, frameInnerMatte);

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

        const portraitPlane = new THREE.Mesh(new THREE.PlaneGeometry(1.30, 0.74), portraitMat);
        portraitPlane.position.z = 0.036;
        const lampHood = new THREE.Mesh(new RoundedBoxGeometry(0.56, 0.045, 0.07, 2, 0.016), goldMat);
        lampHood.position.set(0, 0.50, 0.13);
        const lampGlowStrip = new THREE.Mesh(new THREE.PlaneGeometry(0.50, 0.04), this.mats.warmBulb);
        lampGlowStrip.rotation.x = Math.PI / 2;
        lampGlowStrip.position.set(0, 0.476, 0.13);
        const photoPlaqueBacking = new THREE.Mesh(new RoundedBoxGeometry(0.56, 0.11, 0.025, 1, 0.008), goldMat);
        photoPlaqueBacking.position.set(0, -0.45, 0.038);
        const photoPlaqueFace = new THREE.Mesh(new THREE.PlaneGeometry(0.52, 0.092), this.plaqueMats.team);
        photoPlaqueFace.position.set(0, -0.45, 0.052);
        photoGroup.add(portraitPlane, lampHood, lampGlowStrip, photoPlaqueBacking, photoPlaqueFace);
        atelierClosed.add(photoGroup);

        // OG Signed 24K Gold "MEIN SEELENFUNKE" Gallery Frames on West Knee-Wall
        addGoldenSignedWallArt(og, -2.92, OG_Y + 0.84, -0.90, 0.96, 0.64, Math.PI / 2, 'golden_heart',   'GALERIE SEELENFUNKE');
        addGoldenSignedWallArt(og, -2.92, OG_Y + 0.84, -5.15, 0.96, 0.64, Math.PI / 2, 'botanical_gold', 'STUDIO GIFHORN');

        // RIGHT NEXT TO GROUP PHOTO (Screen-Right = -X!): Illuminated Golden "Mein-Seelenfunke" Sign!
        const brandSignGroup = new THREE.Group();
        brandSignGroup.position.set(-0.98, OG_Y + 1.74, 6.77);
        brandSignGroup.rotation.y = Math.PI;

        const signWallHalo = new THREE.Mesh(
            new THREE.PlaneGeometry(2.10, 1.22),
            new THREE.MeshBasicMaterial({ map: glowWallTex, transparent: true, depthWrite: false })
        );
        signWallHalo.position.set(0, 0, -0.02);
        const signBacklitRim = new THREE.Mesh(new RoundedBoxGeometry(1.48, 0.66, 0.024, 2, 0.018), this.mats.warmBulb);
        signBacklitRim.position.z = 0.004;
        const signGoldCasing = new THREE.Mesh(new RoundedBoxGeometry(1.43, 0.61, 0.045, 2, 0.02), goldMat);
        signGoldCasing.position.z = 0.020;
        const signFacePlane = new THREE.Mesh(
            new THREE.PlaneGeometry(1.36, 0.54),
            new THREE.MeshBasicMaterial({ map: makeIlluminatedBrandSignTexture() })
        );
        signFacePlane.position.z = 0.045;
        brandSignGroup.add(signWallHalo, signBacklitRim, signGoldCasing, signFacePlane);
        atelierClosed.add(brandSignGroup);

        og.add(atelierClosed);

        // ---- LEVEL 1: Bevelled Walnut Manufaktur-Werkbank + 3D GLAS-TROPHÄEN + 14K Jar + Rosen-Vase ----
        // Placed at Screen-Right (x = -1.68, z = +5.72) facing -Z toward camera!
        const lvl1 = new THREE.Group();
        lvl1.position.set(-1.68, OG_Y, 5.72);
        lvl1.rotation.y = Math.PI;

        const benchTop = new THREE.Mesh(new RoundedBoxGeometry(1.94, 0.095, 0.84, 2, 0.026), woodDarkMat);
        benchTop.position.set(0, 0.87, 0);
        const benchTrim = new THREE.Mesh(new RoundedBoxGeometry(1.96, 0.028, 0.86, 1, 0.01), goldMat);
        benchTrim.position.set(0, 0.845, 0);
        const drawerApron = new THREE.Mesh(new RoundedBoxGeometry(1.76, 0.15, 0.72, 2, 0.02), obsidianMat);
        drawerApron.position.set(0, 0.74, 0);
        const pullL = new THREE.Mesh(new RoundedBoxGeometry(0.16, 0.022, 0.03, 1, 0.008), goldMat);
        pullL.position.set(-0.42, 0.74, 0.37);
        const pullR = new THREE.Mesh(new RoundedBoxGeometry(0.16, 0.022, 0.03, 1, 0.008), goldMat);
        pullR.position.set(0.42, 0.74, 0.37);
        const benchCrest = new THREE.Mesh(new THREE.PlaneGeometry(0.36, 0.088), this.plaqueMats.obsidianMain);
        benchCrest.position.set(0, 0.74, 0.364);
        lvl1.add(benchTop, benchTrim, drawerApron, pullL, pullR, benchCrest);

        const legGeo = new THREE.CylinderGeometry(0.040, 0.025, 0.84, 12);
        for (const [lx, lz] of [[-0.86, 0.32], [0.86, 0.32], [-0.86, -0.32], [0.86, -0.32]]) {
            const leg = new THREE.Mesh(legGeo, goldMat);
            leg.position.set(lx, 0.42, lz);
            lvl1.add(leg);
        }

        // 3D GLAS-TROPHÄEN (Signed "MEIN SEELENFUNKE" Optical K9 Seelen-Kristall Awards!)
        const trophiesGroup = new THREE.Group();
        const t1Base = new THREE.Mesh(new RoundedBoxGeometry(0.20, 0.048, 0.20, 2, 0.012), obsidianMat);
        t1Base.position.set(0.0, 0.94, 0.18);
        const t1GoldCollar = new THREE.Mesh(new RoundedBoxGeometry(0.16, 0.024, 0.16, 1, 0.008), goldMat);
        t1GoldCollar.position.set(0.0, 0.972, 0.18);
        const t1Plaque = new THREE.Mesh(new THREE.PlaneGeometry(0.168, 0.036), this.plaqueMats.trophy);
        t1Plaque.position.set(0.0, 0.942, 0.282);
        const t1CoreStar = new THREE.Mesh(new THREE.OctahedronGeometry(0.042, 0), this.mats.warmBulb);
        t1CoreStar.position.set(0.0, 1.15, 0.18);
        const t1Obelisk = new THREE.Mesh(new THREE.CylinderGeometry(0.042, 0.082, 0.34, 6), this.mats.crystalGlass);
        t1Obelisk.position.set(0.0, 1.15, 0.18);
        const t1Apex = new THREE.Mesh(new THREE.ConeGeometry(0.042, 0.08, 6), this.mats.crystalRim);
        t1Apex.position.set(0.0, 1.36, 0.18);
        trophiesGroup.add(t1Base, t1GoldCollar, t1Plaque, t1CoreStar, t1Obelisk, t1Apex);

        const t2Base = new THREE.Mesh(new THREE.CylinderGeometry(0.075, 0.088, 0.048, 12), goldMat);
        t2Base.position.set(-0.32, 0.94, 0.15);
        const t2Plinth = new THREE.Mesh(new RoundedBoxGeometry(0.138, 0.034, 0.024, 1, 0.005), obsidianMat);
        t2Plinth.position.set(-0.32, 0.942, 0.232);
        const t2Plaque = new THREE.Mesh(new THREE.PlaneGeometry(0.128, 0.030), this.plaqueMats.trophy);
        t2Plaque.position.set(-0.32, 0.942, 0.246);
        const t2Core = new THREE.Mesh(new THREE.OctahedronGeometry(0.032, 0), goldMat);
        t2Core.position.set(-0.32, 1.10, 0.15);
        const t2Diamond = new THREE.Mesh(new THREE.OctahedronGeometry(0.105, 0), this.mats.crystalGlass);
        t2Diamond.scale.set(1.0, 1.35, 0.58);
        t2Diamond.position.set(-0.32, 1.10, 0.15);
        const t2Sheen = new THREE.Mesh(new THREE.OctahedronGeometry(0.108, 0), this.mats.crystalRim);
        t2Sheen.scale.set(0.32, 1.36, 0.60);
        t2Sheen.position.set(-0.32, 1.10, 0.15);
        trophiesGroup.add(t2Base, t2Plinth, t2Plaque, t2Core, t2Diamond, t2Sheen);

        const t3Base = new THREE.Mesh(new RoundedBoxGeometry(0.18, 0.044, 0.12, 2, 0.01), obsidianMat);
        t3Base.position.set(0.32, 0.94, 0.15);
        const t3GoldTrim = new THREE.Mesh(new RoundedBoxGeometry(0.15, 0.020, 0.095, 1, 0.006), goldMat);
        t3GoldTrim.position.set(0.32, 0.968, 0.15);
        const t3Plaque = new THREE.Mesh(new THREE.PlaneGeometry(0.152, 0.034), this.plaqueMats.trophy);
        t3Plaque.position.set(0.32, 0.942, 0.212);
        const t3InnerCrest = new THREE.Mesh(new RoundedBoxGeometry(0.065, 0.12, 0.012, 1, 0.004), this.mats.warmBulb);
        t3InnerCrest.position.set(0.32, 1.09, 0.15);
        const t3Block = new THREE.Mesh(new RoundedBoxGeometry(0.138, 0.25, 0.062, 2, 0.016), this.mats.crystalGlass);
        t3Block.position.set(0.32, 1.10, 0.15);
        trophiesGroup.add(t3Base, t3GoldTrim, t3Plaque, t3InnerCrest, t3Block);
        lvl1.add(trophiesGroup);

        // Signed 14K Körper-Kasse Savings Jar & Signed TikTok Rose Amphora Vase
        const jarPoints = [
            new THREE.Vector2(0.00, 0.00), new THREE.Vector2(0.090, 0.00),
            new THREE.Vector2(0.118, 0.07), new THREE.Vector2(0.112, 0.18),
            new THREE.Vector2(0.078, 0.23), new THREE.Vector2(0.092, 0.26), new THREE.Vector2(0.00, 0.26)
        ];
        const jarMesh = new THREE.Mesh(new THREE.LatheGeometry(jarPoints, 16), goldMat);
        jarMesh.position.set(-0.68, 0.915, -0.10);
        const jarBadge = new THREE.Mesh(new THREE.PlaneGeometry(0.135, 0.045), this.plaqueMats.obsidianMain);
        jarBadge.position.set(-0.68, 1.03, 0.022);
        lvl1.add(jarMesh, jarBadge);

        this.vaseGroup = new THREE.Group();
        this.vaseGroup.position.set(0.68, 0.915, 0.12);
        const vasePoints = [
            new THREE.Vector2(0.00, 0.00), new THREE.Vector2(0.075, 0.00),
            new THREE.Vector2(0.112, 0.10), new THREE.Vector2(0.068, 0.22), new THREE.Vector2(0.092, 0.29)
        ];
        const vaseBody = new THREE.Mesh(new THREE.LatheGeometry(vasePoints, 16), goldMat);
        const vaseBadge = new THREE.Mesh(new THREE.PlaneGeometry(0.132, 0.044), this.plaqueMats.flowers);
        vaseBadge.position.set(0, 0.11, 0.115);
        this.vaseGroup.add(vaseBody, vaseBadge);
        lvl1.add(this.vaseGroup);

        og.add(lvl1);
        this.levelGroups[1] = lvl1;

        // ---- LEVEL 2: Ornate Schwarz-Gold Medallion Rug, Signed Westpack Boxes & Signed Boutique Monstera ----
        const lvl2 = new THREE.Group();
        const rug = new THREE.Mesh(
            new THREE.PlaneGeometry(3.05, 3.05),
            new THREE.MeshBasicMaterial({ map: makeBakedRugTexture(), transparent: true, depthWrite: false })
        );
        rug.rotation.x = -Math.PI / 2;
        rug.position.set(0, OG_Y + 0.006, 4.55);
        lvl2.add(rug);

        for (let b = 0; b < 3; b++) {
            const bw = 0.29 - b * 0.030;
            const bd = 0.23 - b * 0.020;
            const box = new THREE.Mesh(new RoundedBoxGeometry(bw, 0.10, bd, 2, 0.018), obsidianMat);
            box.position.set(-1.38, OG_Y + 0.97 + b * 0.102, 5.92);
            const ribbon = new THREE.Mesh(new RoundedBoxGeometry(bw + 0.012, 0.020, bd + 0.012, 1, 0.006), goldMat);
            ribbon.position.copy(box.position);
            const boxSeal = new THREE.Mesh(new THREE.PlaneGeometry(bw * 0.62, 0.044), this.plaqueMats.goldMain);
            boxSeal.position.set(-1.38, OG_Y + 0.97 + b * 0.102, 5.92 - bd * 0.5 - 0.008);
            boxSeal.rotation.y = Math.PI;
            lvl2.add(box, ribbon, boxSeal);
        }

        const potPoints = [
            new THREE.Vector2(0.00, 0.00), new THREE.Vector2(0.13, 0.00),
            new THREE.Vector2(0.19, 0.28), new THREE.Vector2(0.17, 0.32)
        ];
        const pot = new THREE.Mesh(new THREE.LatheGeometry(potPoints, 14), goldMat);
        pot.position.set(-2.48, OG_Y, 4.45);
        const potPlaque = new THREE.Mesh(new THREE.PlaneGeometry(0.19, 0.062), this.plaqueMats.flowers);
        potPlaque.position.set(-2.31, OG_Y + 0.19, 4.35);
        potPlaque.rotation.y = Math.PI * 0.72;
        lvl2.add(pot, potPlaque);
        const leafGeo = new THREE.SphereGeometry(0.18, 10, 8);
        for (let a = 0; a < 6; a++) {
            const ang = a * 1.05;
            const lf = new THREE.Mesh(leafGeo, this.mats.leafGreen);
            lf.scale.set(1.3, 0.28, 0.78);
            lf.position.set(-2.48 + Math.cos(ang) * 0.17, OG_Y + 0.42 + (a % 3) * 0.08, 4.45 + Math.sin(ang) * 0.17);
            lf.rotation.set(Math.sin(ang) * 0.28, -ang, 0.34);
            lvl2.add(lf);
            if (a % 2 === 0) {
                const goldBloom = new THREE.Mesh(new THREE.OctahedronGeometry(0.042, 0), goldMat);
                goldBloom.position.set(-2.48 + Math.cos(ang) * 0.12, OG_Y + 0.52 + (a % 3) * 0.05, 4.45 + Math.sin(ang) * 0.12);
                lvl2.add(goldBloom);
            }
        }
        og.add(lvl2);
        this.levelGroups[2] = lvl2;

        // ---- LEVEL 3: xTool F2 Ultra UV + Conveyor + SafetyPro IF2 2.0 & Signed Boutique Vitrine ----
        // Placed at Screen-Left (x = +1.92, z = +5.72) facing -Z toward camera!
        const lvl3 = new THREE.Group();
        const laserGrp = new THREE.Group();
        laserGrp.position.set(1.96, OG_Y, 5.72);
        laserGrp.rotation.y = Math.PI;

        const laserStand = new THREE.Mesh(new RoundedBoxGeometry(0.94, 0.78, 0.74, 2, 0.03), obsidianMat);
        laserStand.position.y = 0.39;
        const standTrim = new THREE.Mesh(new RoundedBoxGeometry(0.96, 0.026, 0.76, 1, 0.008), goldMat);
        standTrim.position.y = 0.77;
        const laserPlaque = new THREE.Mesh(new THREE.PlaneGeometry(0.46, 0.12), this.plaqueMats.obsidianMain);
        laserPlaque.position.set(0, 0.48, 0.375);
        const laserBody = new THREE.Mesh(new RoundedBoxGeometry(0.56, 0.56, 0.50, 2, 0.035), this.mats.darkArmor);
        laserBody.position.set(0, 1.06, -0.02);
        const conveyorRail = new THREE.Mesh(new RoundedBoxGeometry(1.12, 0.045, 0.25, 2, 0.012), this.mats.silverSteel);
        conveyorRail.position.set(0, 0.81, 0.04);
        const laserHood = new THREE.Mesh(
            new RoundedBoxGeometry(0.51, 0.32, 0.45, 2, 0.025),
            new THREE.MeshBasicMaterial({ color: 0x16a367, transparent: true, opacity: 0.64 })
        );
        laserHood.position.set(0, 1.01, 0.03);
        const exhaustUnit = new THREE.Mesh(new THREE.CylinderGeometry(0.13, 0.14, 0.45, 14), this.mats.darkArmor);
        exhaustUnit.position.set(0.58, 0.225, -0.08);
        laserGrp.add(laserStand, standTrim, laserPlaque, laserBody, conveyorRail, laserHood, exhaustUnit);
        lvl3.add(laserGrp);

        const vitBase = new THREE.Mesh(new RoundedBoxGeometry(0.62, 0.86, 0.46, 2, 0.026), obsidianMat);
        vitBase.position.set(1.04, OG_Y + 0.43, 5.96);
        const vitCap = new THREE.Mesh(new RoundedBoxGeometry(0.65, 0.032, 0.49, 1, 0.01), goldMat);
        vitCap.position.set(1.04, OG_Y + 0.87, 5.96);
        const vitPlaque = new THREE.Mesh(new THREE.PlaneGeometry(0.42, 0.115), this.plaqueMats.trophy);
        vitPlaque.position.set(1.04, OG_Y + 0.56, 5.726);
        vitPlaque.rotation.y = Math.PI;
        const vitGlass = new THREE.Mesh(
            new RoundedBoxGeometry(0.58, 0.74, 0.42, 2, 0.02),
            new THREE.MeshBasicMaterial({ color: 0xfff5dc, transparent: true, opacity: 0.13, depthWrite: false })
        );
        vitGlass.position.set(1.04, OG_Y + 1.25, 5.96);
        const vitItem = new THREE.Mesh(new THREE.OctahedronGeometry(0.125, 0), goldMat);
        vitItem.position.set(1.04, OG_Y + 1.22, 5.96);
        lvl3.add(vitBase, vitCap, vitPlaque, vitGlass, vitItem);
        this.rig.vitrineGem = vitItem;
        og.add(lvl3);
        this.levelGroups[3] = lvl3;

        // ---- LEVEL 4: Signed Sculpted Goblet Trophy ----
        const lvl4 = new THREE.Group();
        const gobBase = new THREE.Mesh(new RoundedBoxGeometry(0.22, 0.048, 0.22, 2, 0.012), obsidianMat);
        gobBase.position.set(1.04, OG_Y + 1.644, 5.96);
        const gobPlaque = new THREE.Mesh(new THREE.PlaneGeometry(0.18, 0.038), this.plaqueMats.trophy);
        gobPlaque.position.set(1.04, OG_Y + 1.646, 5.847);
        gobPlaque.rotation.y = Math.PI;
        const trophyPoints = [
            new THREE.Vector2(0.00, 0.00), new THREE.Vector2(0.08, 0.00),
            new THREE.Vector2(0.03, 0.06), new THREE.Vector2(0.03, 0.12),
            new THREE.Vector2(0.11, 0.24), new THREE.Vector2(0.12, 0.28)
        ];
        const trophyCup = new THREE.Mesh(new THREE.LatheGeometry(trophyPoints, 14), goldMat);
        trophyCup.position.set(1.04, OG_Y + 1.668, 5.96);
        lvl4.add(gobBase, gobPlaque, trophyCup);
        og.add(lvl4);
        this.levelGroups[4] = lvl4;

        // ---- LEVEL 5: MEIN SEELENFUNKE Crest on Gable Peak & 14K Chassis Blueprint ----
        const lvl5 = new THREE.Group();
        const crestMesh = new THREE.Mesh(
            new THREE.PlaneGeometry(1.78, 0.42),
            new THREE.MeshBasicMaterial({ map: makeWallCrestTexture('MEIN SEELENFUNKE', 'MANUFAKTUR GIFHORN · EXZELLENZ-STUFE V') })
        );
        crestMesh.position.set(-0.22, OG_Y + 2.52, 6.77);
        crestMesh.rotation.y = Math.PI;
        const blueprintMesh = new THREE.Mesh(
            new THREE.PlaneGeometry(1.15, 0.36),
            new THREE.MeshBasicMaterial({ map: makeWallCrestTexture('PROJEKT: EIGENER KÖRPER', '14K CHASSIS-BLAUPAUSE · FUNKIRA MK-II') })
        );
        blueprintMesh.position.set(-2.92, OG_Y + 0.92, 3.55);
        blueprintMesh.rotation.y = Math.PI / 2;
        lvl5.add(blueprintMesh);
        atelierClosed.add(crestMesh);
        og.add(lvl5);
        this.levelGroups[5] = lvl5;

        // ---- HOLIDAY PROPS (Advent/Christmas Tree in Seelenfunken Atelier) ----
        const xmas = new THREE.Group();
        for (let tier = 0; tier < 3; tier++) {
            const cone = new THREE.Mesh(new THREE.ConeGeometry(0.46 - tier * 0.10, 0.58, 14), this.mats.leafGreen);
            cone.position.set(2.42, OG_Y + 0.48 + tier * 0.36, 4.42);
            xmas.add(cone);
        }
        const starTop = new THREE.Mesh(new THREE.OctahedronGeometry(0.095, 0), goldMat);
        starTop.position.set(2.42, OG_Y + 1.56, 4.42);
        xmas.add(starTop);
        og.add(xmas);
        this.occasionGroups.christmas = xmas;

        this.scene.add(og);
        freezeStaticTree(og);
        og.matrixAutoUpdate = true;
        atelierClosed.matrixAutoUpdate = true;
        this.vaseGroup.matrixAutoUpdate = true;
        vitItem.matrixAutoUpdate = true;

        // Dynamic OG Movable Items Container (child of og so cutaway hides OG items automatically!)
        this.ogItemsGroup = new THREE.Group();
        this.ogItemsGroup.matrixAutoUpdate = true;
        og.add(this.ogItemsGroup);

        // Build Outdoor Terrasse, Garten, Seasonal Vegetation & Holiday Worlds
        this._buildTerraceAndGarden();

        // Craft Sparks Pool
        const spGeo = new THREE.BoxGeometry(0.026, 0.026, 0.026);
        const spMat = new THREE.MeshBasicMaterial({ color: 0xFFE08A });
        for (let i = 0; i < 12; i++) {
            const sp = new THREE.Mesh(spGeo, spMat);
            sp.visible = false;
            this.scene.add(sp);
            this.sparkParticles.push({ mesh: sp, vx: 0, vy: 0, vz: 0, life: 0 });
        }
    }

    _buildTerraceAndGarden() {
        const goldMat = this.mats.gold24k;
        const woodDarkMat = this.mats.woodDark;
        const oakMat = this.mats.oakWarm;
        const obsidianMat = this.mats.obsidian;
        const leafMat = this.mats.leafGreen;
        const snowMat = this.mats.snowWhite;
        const stoneMat = this.mats.stoneWarm;
        const soilMat = this.mats.soilRich;
        const terraMat = this.mats.terracotta;

        const garden = new THREE.Group();
        this.gardenGroup = garden;

        // 1. Dynamic Seasonal Garden Ground & WPC/Teak Terrace Deck
        paintGardenGroundCanvas(this.gardenGroundCanvas, this.worldState.season, this.worldState.occasion);
        this.gardenGroundTexture = configureFastTexture(new THREE.CanvasTexture(this.gardenGroundCanvas));
        const earthSlab = new THREE.Mesh(new THREE.BoxGeometry(11.42, 0.14, 6.54), obsidianMat);
        earthSlab.position.set(0, -0.088, 10.18);
        const lawnMesh = new THREE.Mesh(
            new THREE.PlaneGeometry(11.24, 6.42),
            new THREE.MeshBasicMaterial({ map: this.gardenGroundTexture })
        );
        lawnMesh.rotation.x = -Math.PI / 2;
        lawnMesh.position.set(0, -0.012, 10.18);
        garden.add(earthSlab, lawnMesh);

        paintTerraceDeckCanvas(this.terraceDeckCanvas, this.worldState.season);
        this.terraceDeckTexture = configureFastTexture(new THREE.CanvasTexture(this.terraceDeckCanvas));
        const deckBase = new THREE.Mesh(new RoundedBoxGeometry(6.34, 0.034, 2.58, 1, 0.008), woodDarkMat);
        deckBase.position.set(-0.70, 0.002, 8.26);
        const deckTop = new THREE.Mesh(
            new THREE.PlaneGeometry(6.28, 2.52),
            new THREE.MeshBasicMaterial({ map: this.terraceDeckTexture })
        );
        deckTop.rotation.x = -Math.PI / 2;
        deckTop.position.set(-0.70, 0.021, 8.26);
        garden.add(deckBase, deckTop);

        // Natural Stepping-Stone Garden Path (Terrasse -> Gartenmitte, Blumenbeet & Hochbeet)
        const stepStoneGeo = new THREE.CylinderGeometry(0.28, 0.30, 0.026, 10);
        const stoneCoords = [
            [-0.72, 9.82], [-0.58, 10.42], [-0.38, 11.05], [-0.18, 11.65],
            [-1.38, 9.95], [-2.05, 10.05],
            [ 1.78, 8.60], [ 2.28, 8.60]
        ];
        for (let i = 0; i < stoneCoords.length; i++) {
            const st = new THREE.Mesh(stepStoneGeo, stoneMat);
            st.scale.set(1.12, 1.0, 0.86);
            st.rotation.y = (i * 0.65);
            st.position.set(stoneCoords[i][0], 0.004, stoneCoords[i][1]);
            garden.add(st);
        }

        // 2. Perimeter Fence, Evergreen Hedges & Warm Post Lanterns
        const addFenceSpan = (x1, z1, x2, z2, h = 1.08) => {
            const cx = (x1 + x2) * 0.5;
            const cz = (z1 + z2) * 0.5;
            const sx = Math.max(0.08, Math.abs(x2 - x1));
            const sz = Math.max(0.08, Math.abs(z2 - z1));
            const panel = new THREE.Mesh(new RoundedBoxGeometry(sx, h, sz, 1, 0.01), woodDarkMat);
            panel.position.set(cx, h * 0.5, cz);
            const cap = new THREE.Mesh(new RoundedBoxGeometry(sx + 0.02, 0.032, sz + 0.02, 1, 0.006), goldMat);
            cap.position.set(cx, h + 0.016, cz);
            garden.add(panel, cap);
        };
        addFenceSpan(-5.56, 6.96, -4.18, 6.96, 1.12); // North-West wing
        addFenceSpan( 4.18, 6.96,  5.56, 6.96, 1.12); // North-East wing
        addFenceSpan(-5.56, 6.96, -5.56, 13.34, 1.12); // West garden fence
        addFenceSpan( 5.56, 6.96,  5.56, 13.34, 1.12); // East garden fence
        addFenceSpan(-5.56, 13.34, 5.56, 13.34, 0.42); // Low South cutaway fence for unobstructed view

        const hedgeWest = new THREE.Mesh(new RoundedBoxGeometry(0.42, 0.82, 5.90, 2, 0.14), leafMat);
        hedgeWest.position.set(-5.24, 0.41, 10.15);
        const hedgeEast = new THREE.Mesh(new RoundedBoxGeometry(0.42, 0.82, 5.90, 2, 0.14), leafMat);
        hedgeEast.position.set(5.24, 0.41, 10.15);
        garden.add(hedgeWest, hedgeEast);

        const lanternPostGeo = new THREE.CylinderGeometry(0.035, 0.042, 0.92, 8);
        const lanternBulbGeo = new THREE.SphereGeometry(0.075, 10, 8);
        const lanternCoords = [
            [-3.82, 9.48], [2.42, 9.48],
            [-5.48, 7.05], [5.48, 7.05],
            [-5.48, 13.26], [5.48, 13.26]
        ];
        for (const [lx, lz] of lanternCoords) {
            const post = new THREE.Mesh(lanternPostGeo, obsidianMat);
            post.position.set(lx, 0.46, lz);
            const bulb = new THREE.Mesh(lanternBulbGeo, this.mats.warmBulb);
            bulb.position.set(lx, 0.96, lz);
            const hat = new THREE.Mesh(new THREE.ConeGeometry(0.10, 0.06, 8), goldMat);
            hat.position.set(lx, 1.03, lz);
            garden.add(post, bulb, hat);
        }

        // 3. Terrasse Rattan Lounge-Sofa, Coffee Table & Planter Pots
        const loungeRug = new THREE.Mesh(
            new THREE.PlaneGeometry(2.10, 1.65),
            new THREE.MeshBasicMaterial({ map: makeCozyLivingRugTexture() })
        );
        loungeRug.rotation.x = -Math.PI / 2;
        loungeRug.position.set(-2.62, 0.025, 8.22);
        garden.add(loungeRug);

        const sofaBaseA = new THREE.Mesh(new RoundedBoxGeometry(1.68, 0.34, 0.68, 2, 0.03), woodDarkMat);
        sofaBaseA.position.set(-2.66, 0.19, 7.78);
        const sofaCushA = new THREE.Mesh(new RoundedBoxGeometry(1.58, 0.11, 0.60, 2, 0.03), this.mats.velvetCream);
        sofaCushA.position.set(-2.66, 0.40, 7.80);
        const sofaBackA = new THREE.Mesh(new RoundedBoxGeometry(1.68, 0.42, 0.14, 2, 0.03), woodDarkMat);
        sofaBackA.position.set(-2.66, 0.54, 7.50);
        const sofaBaseB = new THREE.Mesh(new RoundedBoxGeometry(0.68, 0.34, 0.92, 2, 0.03), woodDarkMat);
        sofaBaseB.position.set(-3.16, 0.19, 8.48);
        const sofaCushB = new THREE.Mesh(new RoundedBoxGeometry(0.60, 0.11, 0.84, 2, 0.03), this.mats.velvetCream);
        sofaCushB.position.set(-3.14, 0.40, 8.48);
        const loungeTable = new THREE.Mesh(new RoundedBoxGeometry(0.82, 0.34, 0.52, 2, 0.025), oakMat);
        loungeTable.position.set(-2.32, 0.19, 8.52);
        const tableTrim = new THREE.Mesh(new RoundedBoxGeometry(0.84, 0.024, 0.54, 1, 0.008), goldMat);
        tableTrim.position.set(-2.32, 0.365, 8.52);
        garden.add(sofaBaseA, sofaCushA, sofaBackA, sofaBaseB, sofaCushB, loungeTable, tableTrim);

        // Signed 24K Gold & Obsidian Patio Planters with Golden Blooms
        const potGeo = new THREE.CylinderGeometry(0.20, 0.14, 0.34, 12);
        const potRimGeo = new THREE.TorusGeometry(0.20, 0.016, 8, 16);
        for (const [px, pz] of [[-1.56, 7.26], [0.26, 7.26]]) {
            const pot = new THREE.Mesh(potGeo, obsidianMat);
            pot.position.set(px, 0.19, pz);
            const pRim = new THREE.Mesh(potRimGeo, goldMat);
            pRim.rotation.x = Math.PI / 2;
            pRim.position.set(px, 0.355, pz);
            const pBadge = new THREE.Mesh(new THREE.PlaneGeometry(0.20, 0.064), this.plaqueMats.flowers);
            pBadge.position.set(px, 0.21, pz + 0.188);
            const bush = new THREE.Mesh(new THREE.SphereGeometry(0.21, 10, 8), leafMat);
            bush.position.set(px, 0.42, pz);
            const gFlower1 = new THREE.Mesh(new THREE.OctahedronGeometry(0.045, 0), goldMat);
            gFlower1.position.set(px - 0.07, 0.58, pz + 0.06);
            const gFlower2 = new THREE.Mesh(new THREE.DodecahedronGeometry(0.042, 0), this.mats.roseBloom);
            gFlower2.position.set(px + 0.08, 0.56, pz + 0.05);
            garden.add(pot, pRim, pBadge, bush, gFlower1, gFlower2);
        }

        // 4. Holz-Hochbeet (Signed "MEIN SEELENFUNKE · GOLDENER GARTEN" at x = +3.40, z = +8.60)
        const hbGroup = new THREE.Group();
        hbGroup.position.set(3.40, 0, 8.60);
        const hbBody = new THREE.Mesh(new RoundedBoxGeometry(1.46, 0.76, 1.46, 2, 0.025), oakMat);
        hbBody.position.y = 0.38;
        const hbRim = new THREE.Mesh(new RoundedBoxGeometry(1.54, 0.045, 1.54, 1, 0.01), woodDarkMat);
        hbRim.position.y = 0.77;
        const hbGoldBand = new THREE.Mesh(new RoundedBoxGeometry(1.49, 0.026, 1.49, 1, 0.006), goldMat);
        hbGoldBand.position.y = 0.62;
        const hbPlaqueSouth = new THREE.Mesh(new THREE.PlaneGeometry(0.54, 0.15), this.plaqueMats.garden);
        hbPlaqueSouth.position.set(0, 0.42, 0.736);
        const hbPlaqueWest = new THREE.Mesh(new THREE.PlaneGeometry(0.54, 0.15), this.plaqueMats.garden);
        hbPlaqueWest.position.set(-0.736, 0.42, 0);
        hbPlaqueWest.rotation.y = -Math.PI / 2;
        const hbSoil = new THREE.Mesh(new RoundedBoxGeometry(1.34, 0.08, 1.34, 1, 0.01), soilMat);
        hbSoil.position.y = 0.73;
        hbGroup.add(hbBody, hbRim, hbGoldBand, hbPlaqueSouth, hbPlaqueWest, hbSoil);
        for (let r = -1; r <= 1; r++) {
            for (let c = -1; c <= 1; c++) {
                const herb = new THREE.Mesh(new THREE.DodecahedronGeometry(0.11, 0), leafMat);
                herb.position.set(c * 0.36, 0.82, r * 0.36);
                hbGroup.add(herb);
            }
        }
        garden.add(hbGroup);

        // 5. Garten-Blumenbeet (Signed with 24K Gold Steckschild at x = -2.50, z = +11.20)
        const bbGroup = new THREE.Group();
        bbGroup.position.set(-2.50, 0, 11.20);
        const bbRim = new THREE.Mesh(new RoundedBoxGeometry(2.12, 0.16, 1.12, 2, 0.04), obsidianMat);
        bbRim.position.y = 0.08;
        const bbGoldTrim = new THREE.Mesh(new RoundedBoxGeometry(2.15, 0.026, 1.15, 1, 0.01), goldMat);
        bbGoldTrim.position.y = 0.155;
        const bbSoil = new THREE.Mesh(new RoundedBoxGeometry(1.94, 0.14, 0.94, 2, 0.03), soilMat);
        bbSoil.position.y = 0.11;
        const bbStakeRod = new THREE.Mesh(new THREE.CylinderGeometry(0.014, 0.014, 0.34, 8), goldMat);
        bbStakeRod.position.set(0.68, 0.24, 0.28);
        const bbStakeBack = new THREE.Mesh(new RoundedBoxGeometry(0.42, 0.13, 0.022, 1, 0.006), goldMat);
        bbStakeBack.position.set(0.68, 0.40, 0.29);
        bbStakeBack.rotation.x = -0.26;
        const bbStakeFace = new THREE.Mesh(new THREE.PlaneGeometry(0.39, 0.112), this.plaqueMats.garden);
        bbStakeFace.position.set(0.68, 0.402, 0.304);
        bbStakeFace.rotation.x = -0.26;
        bbGroup.add(bbRim, bbGoldTrim, bbSoil, bbStakeRod, bbStakeBack, bbStakeFace);
        for (let i = 0; i < 7; i++) {
            const leafClump = new THREE.Mesh(new THREE.SphereGeometry(0.13, 8, 6), leafMat);
            leafClump.scale.set(1.2, 0.65, 1.1);
            leafClump.position.set(-0.72 + i * 0.24, 0.20, (i % 2 === 0 ? -0.18 : 0.18));
            bbGroup.add(leafClump);
        }
        garden.add(bbGroup);

        // Schwarz-Goldener Stellplatz für Funkiras Goldenen SEELENFUNKE-Rasenmäher (x = +3.18, z = +9.95)
        const mowerPadBase = new THREE.Mesh(new RoundedBoxGeometry(0.88, 0.022, 0.76, 2, 0.01), stoneMat);
        mowerPadBase.position.set(3.18, 0.005, 9.95);
        const mowerPadTrim = new THREE.Mesh(new RoundedBoxGeometry(0.91, 0.016, 0.79, 1, 0.006), goldMat);
        mowerPadTrim.position.set(3.18, 0.009, 9.95);
        garden.add(mowerPadBase, mowerPadTrim);

        // 6. Zier-Gartenbaum (x = +3.85, z = +11.45)
        const treeBase = new THREE.Group();
        treeBase.position.set(3.85, 0, 11.45);
        const treeRing = new THREE.Mesh(new THREE.TorusGeometry(0.44, 0.06, 8, 16), stoneMat);
        treeRing.rotation.x = Math.PI / 2;
        treeRing.position.y = 0.04;
        const trunk = new THREE.Mesh(new THREE.CylinderGeometry(0.13, 0.21, 1.55, 10), woodDarkMat);
        trunk.position.y = 0.77;
        const branchL = new THREE.Mesh(new THREE.CylinderGeometry(0.06, 0.09, 0.68, 8), woodDarkMat);
        branchL.position.set(-0.20, 1.56, 0.05);
        branchL.rotation.z = 0.58;
        const branchR = new THREE.Mesh(new THREE.CylinderGeometry(0.06, 0.09, 0.64, 8), woodDarkMat);
        branchR.position.set(0.22, 1.58, -0.06);
        branchR.rotation.z = -0.54;
        treeBase.add(treeRing, trunk, branchL, branchR);
        garden.add(treeBase);

        const addTreeCanopy = (targetGrp, canopyMat) => {
            const blobs = [
                [0.0, 2.15, 0.0, 0.62],
                [-0.42, 1.92, 0.18, 0.46],
                [0.44, 1.94, -0.14, 0.46],
                [0.12, 2.02, 0.38, 0.42],
                [-0.16, 2.00, -0.36, 0.42]
            ];
            for (const [bx, by, bz, br] of blobs) {
                const sp = new THREE.Mesh(new THREE.SphereGeometry(br, 10, 8), canopyMat);
                sp.position.set(3.85 + bx, by, 11.45 + bz);
                targetGrp.add(sp);
            }
        };

        // ====================================================================
        // 7. SEASONAL 3D LAYERS (SPRING, SUMMER, AUTUMN, WINTER)
        // ====================================================================
        const springGrp = new THREE.Group();
        addTreeCanopy(springGrp, this.mats.blossomPink);
        const flowerStemGeo = new THREE.CylinderGeometry(0.012, 0.012, 0.26, 6);
        const tulipHeadGeo = new THREE.SphereGeometry(0.055, 8, 8);
        for (let i = 0; i < 12; i++) {
            const fx = -3.28 + (i % 6) * 0.31;
            const fz = 11.00 + Math.floor(i / 6) * 0.38;
            const st = new THREE.Mesh(flowerStemGeo, leafMat);
            st.position.set(fx, 0.28, fz);
            const hd = new THREE.Mesh(tulipHeadGeo, (i % 3 === 0) ? this.mats.sunflowerYellow : this.mats.tulipPink);
            hd.scale.set(0.85, 1.25, 0.85);
            hd.position.set(fx, 0.43, fz);
            springGrp.add(st, hd);
        }
        for (let i = 0; i < 6; i++) {
            const hd = new THREE.Mesh(tulipHeadGeo, (i % 2 === 0) ? this.mats.tulipPink : this.mats.sunflowerYellow);
            hd.position.set(3.04 + (i % 3) * 0.36, 0.94, 8.38 + Math.floor(i / 3) * 0.44);
            springGrp.add(hd);
        }
        garden.add(springGrp);
        this.gardenSeasonGroups.spring = springGrp;

        const summerGrp = new THREE.Group();
        addTreeCanopy(summerGrp, leafMat);
        // Opened 3D Sonnenschirm over Terrace Lounge!
        const paraPole = new THREE.Mesh(new THREE.CylinderGeometry(0.032, 0.036, 2.22, 10), woodDarkMat);
        paraPole.position.set(-2.95, 1.12, 7.98);
        const paraCanopy = new THREE.Mesh(new THREE.ConeGeometry(1.32, 0.38, 10), this.mats.velvetCream);
        paraCanopy.position.set(-2.95, 2.18, 7.98);
        const paraFinial = new THREE.Mesh(new THREE.SphereGeometry(0.055, 8, 8), goldMat);
        paraFinial.position.set(-2.95, 2.40, 7.98);
        summerGrp.add(paraPole, paraCanopy, paraFinial);

        // Tall Sunflowers & Blooming Roses in Blumenbeet + Ripe Tomatoes in Hochbeet
        const sunStemGeo = new THREE.CylinderGeometry(0.018, 0.022, 0.62, 6);
        const sunDiscGeo = new THREE.CylinderGeometry(0.11, 0.11, 0.03, 10);
        for (let i = 0; i < 5; i++) {
            const sx = -3.22 + i * 0.36;
            const sz = 11.34;
            const sst = new THREE.Mesh(sunStemGeo, leafMat);
            sst.position.set(sx, 0.46, sz);
            const sHead = new THREE.Mesh(sunDiscGeo, this.mats.sunflowerYellow);
            sHead.rotation.x = Math.PI * 0.38;
            sHead.position.set(sx, 0.78, sz + 0.03);
            const sCore = new THREE.Mesh(new THREE.SphereGeometry(0.052, 8, 6), soilMat);
            sCore.position.set(sx, 0.79, sz + 0.05);
            summerGrp.add(sst, sHead, sCore);
        }
        for (let i = 0; i < 6; i++) {
            const rb = new THREE.Mesh(new THREE.DodecahedronGeometry(0.055, 0), this.mats.roseBloom);
            rb.position.set(-3.15 + i * 0.28, 0.32, 10.96);
            summerGrp.add(rb);
            const tom = new THREE.Mesh(new THREE.SphereGeometry(0.042, 8, 6), this.mats.scarfRed);
            tom.position.set(3.04 + (i % 3) * 0.36, 0.90, 8.32 + Math.floor(i / 3) * 0.48);
            summerGrp.add(tom);
        }
        garden.add(summerGrp);
        this.gardenSeasonGroups.summer = summerGrp;

        const autumnGrp = new THREE.Group();
        addTreeCanopy(autumnGrp, this.mats.autumnAmber);
        // Harvest Pumpkins & Marigolds
        const pumpCoords = [[2.42, 0.15, 9.22, 0.16], [2.62, 0.11, 9.42, 0.11], [-1.52, 0.16, 7.62, 0.14]];
        for (const [px, py, pz, pr] of pumpCoords) {
            const pk = new THREE.Mesh(new THREE.SphereGeometry(pr, 10, 8), this.mats.pumpkinOrange);
            pk.scale.set(1.18, 0.82, 1.18);
            pk.position.set(px, py, pz);
            const pst = new THREE.Mesh(new THREE.CylinderGeometry(0.015, 0.02, 0.07, 6), leafMat);
            pst.position.set(px, py + pr * 0.85, pz);
            autumnGrp.add(pk, pst);
        }
        for (let i = 0; i < 8; i++) {
            const mg = new THREE.Mesh(new THREE.DodecahedronGeometry(0.052, 0), (i % 2 === 0) ? this.mats.pumpkinOrange : this.mats.sunflowerYellow);
            mg.position.set(-3.24 + i * 0.22, 0.30, 11.18 + (i % 2 === 0 ? -0.14 : 0.14));
            autumnGrp.add(mg);
        }
        garden.add(autumnGrp);
        this.gardenSeasonGroups.autumn = autumnGrp;

        const winterGrp = new THREE.Group();
        addTreeCanopy(winterGrp, snowMat);
        // Snow blankets on Hedges, Hochbeet & Blumenbeet
        const snowHedgeW = new THREE.Mesh(new RoundedBoxGeometry(0.44, 0.12, 5.92, 2, 0.05), snowMat);
        snowHedgeW.position.set(-5.24, 0.84, 10.15);
        const snowHedgeE = new THREE.Mesh(new RoundedBoxGeometry(0.44, 0.12, 5.92, 2, 0.05), snowMat);
        snowHedgeE.position.set(5.24, 0.84, 10.15);
        const snowHb = new THREE.Mesh(new RoundedBoxGeometry(1.42, 0.07, 1.42, 2, 0.03), snowMat);
        snowHb.position.set(3.40, 0.81, 8.60);
        const snowBb = new THREE.Mesh(new RoundedBoxGeometry(1.98, 0.08, 0.98, 2, 0.03), snowMat);
        snowBb.position.set(-2.50, 0.20, 11.20);
        winterGrp.add(snowHedgeW, snowHedgeE, snowHb, snowBb);

        // 3D Schneemann (x = -3.75, z = +9.75)
        const sm = new THREE.Group();
        sm.position.set(-3.75, 0, 9.75);
        sm.rotation.y = 0.45;
        const smBot = new THREE.Mesh(new THREE.SphereGeometry(0.36, 12, 10), snowMat);
        smBot.scale.set(1.05, 0.92, 1.05);
        smBot.position.y = 0.31;
        const smMid = new THREE.Mesh(new THREE.SphereGeometry(0.26, 12, 10), snowMat);
        smMid.position.y = 0.78;
        const smHead = new THREE.Mesh(new THREE.SphereGeometry(0.185, 12, 10), snowMat);
        smHead.position.y = 1.15;
        const smScarf = new THREE.Mesh(new THREE.TorusGeometry(0.16, 0.042, 8, 16), this.mats.scarfRed);
        smScarf.rotation.x = Math.PI / 2;
        smScarf.position.y = 0.99;
        const smNose = new THREE.Mesh(new THREE.ConeGeometry(0.032, 0.16, 8), this.mats.pumpkinOrange);
        smNose.rotation.x = Math.PI / 2;
        smNose.position.set(0, 1.15, 0.22);
        const smHatBrim = new THREE.Mesh(new THREE.CylinderGeometry(0.21, 0.21, 0.03, 12), obsidianMat);
        smHatBrim.position.y = 1.31;
        const smHatTop = new THREE.Mesh(new THREE.CylinderGeometry(0.14, 0.13, 0.22, 12), obsidianMat);
        smHatTop.position.y = 1.43;
        const smHatBand = new THREE.Mesh(new THREE.CylinderGeometry(0.143, 0.143, 0.035, 12), goldMat);
        smHatBand.position.y = 1.34;
        sm.add(smBot, smMid, smHead, smScarf, smNose, smHatBrim, smHatTop, smHatBand);
        for (const ex of [-0.065, 0.065]) {
            const eye = new THREE.Mesh(new THREE.SphereGeometry(0.022, 6, 6), obsidianMat);
            eye.position.set(ex, 1.19, 0.168);
            sm.add(eye);
        }
        winterGrp.add(sm);
        garden.add(winterGrp);
        this.gardenSeasonGroups.winter = winterGrp;

        // ====================================================================
        // 8. HOLIDAY & SPECIAL OCCASION 3D LAYERS IN THE GARDEN
        // ====================================================================
        // A) Advent / Nikolaus / Christmas: Outdoor Illuminated Fir Tree, Gifts & Fairy Lights
        const xmasOut = new THREE.Group();
        for (let tier = 0; tier < 3; tier++) {
            const cone = new THREE.Mesh(new THREE.ConeGeometry(0.58 - tier * 0.12, 0.72, 14), leafMat);
            cone.position.set(1.95, 0.52 + tier * 0.46, 11.55);
            xmasOut.add(cone);
        }
        const outStar = new THREE.Mesh(new THREE.OctahedronGeometry(0.12, 0), goldMat);
        outStar.position.set(1.95, 1.92, 11.55);
        xmasOut.add(outStar);
        for (let b = 0; b < 14; b++) {
            const ang = (b / 14) * Math.PI * 4;
            const rad = 0.44 - (b / 14) * 0.26;
            const orn = new THREE.Mesh(
                new THREE.SphereGeometry(0.042, 8, 6),
                (b % 3 === 0) ? this.mats.warmBulb : ((b % 2 === 0) ? this.mats.scarfRed : goldMat)
            );
            orn.position.set(1.95 + Math.cos(ang) * rad, 0.48 + b * 0.09, 11.55 + Math.sin(ang) * rad);
            xmasOut.add(orn);
        }
        // Wrapped Gift Boxes under outdoor tree + Warm Terrace Fairy Lights
        const gift1 = new THREE.Mesh(new RoundedBoxGeometry(0.26, 0.22, 0.26, 1, 0.015), this.mats.scarfRed);
        gift1.position.set(1.58, 0.11, 11.35);
        const gift2 = new THREE.Mesh(new RoundedBoxGeometry(0.22, 0.18, 0.22, 1, 0.015), goldMat);
        gift2.position.set(2.26, 0.09, 11.28);
        xmasOut.add(gift1, gift2);
        const fairyGeo = new THREE.SphereGeometry(0.036, 6, 6);
        for (let f = 0; f < 16; f++) {
            const fb = new THREE.Mesh(fairyGeo, this.mats.warmBulb);
            fb.position.set(-3.70 + f * 0.40, 0.14 + Math.sin(f * 0.9) * 0.03, 9.50);
            xmasOut.add(fb);
        }
        garden.add(xmasOut);
        this.gardenOccasionGroups.christmas = xmasOut;

        // B) Silvester & Neujahr: Festive Terrace Champagne Bistro Table + Live 3D Fireworks Pool!
        const silvGrp = new THREE.Group();
        const bistroPed = new THREE.Mesh(new THREE.CylinderGeometry(0.045, 0.22, 0.76, 10), goldMat);
        bistroPed.position.set(1.20, 0.38, 7.72);
        const bistroTop = new THREE.Mesh(new THREE.CylinderGeometry(0.34, 0.34, 0.035, 16), this.mats.marbleCream);
        bistroTop.position.set(1.20, 0.77, 7.72);
        const bottleBody = new THREE.Mesh(new THREE.CylinderGeometry(0.042, 0.045, 0.22, 10), leafMat);
        bottleBody.position.set(1.14, 0.89, 7.68);
        const bottleNeck = new THREE.Mesh(new THREE.CylinderGeometry(0.018, 0.032, 0.10, 8), goldMat);
        bottleNeck.position.set(1.14, 1.03, 7.68);
        const fluteGeo = new THREE.CylinderGeometry(0.026, 0.014, 0.13, 8);
        const flute1 = new THREE.Mesh(fluteGeo, goldMat);
        flute1.position.set(1.28, 0.85, 7.66);
        const flute2 = new THREE.Mesh(fluteGeo, goldMat);
        flute2.position.set(1.25, 0.85, 7.80);
        silvGrp.add(bistroPed, bistroTop, bottleBody, bottleNeck, flute1, flute2);
        garden.add(silvGrp);
        this.gardenOccasionGroups.silvester = silvGrp;

        // Live 60-FPS 3D Silvester Fireworks Pool (4 rockets x 22 sparks = 88 tiny unlit cubes, zero GC!)
        const fwSparkGeo = new THREE.BoxGeometry(0.068, 0.068, 0.068);
        const fwRocketGeo = new THREE.ConeGeometry(0.045, 0.16, 6);
        const fwPalette = [0xFFE07D, 0xFF7AA8, 0x7CE8FF, 0x82FFB4];
        for (let r = 0; r < 4; r++) {
            const mat = new THREE.MeshBasicMaterial({ color: fwPalette[r % fwPalette.length] });
            const rocketMesh = new THREE.Mesh(fwRocketGeo, this.mats.warmBulb);
            rocketMesh.visible = false;
            this.scene.add(rocketMesh);
            const sparks = [];
            for (let s = 0; s < 22; s++) {
                const smesh = new THREE.Mesh(fwSparkGeo, mat);
                smesh.visible = false;
                this.scene.add(smesh);
                sparks.push({ mesh: smesh, vx: 0, vy: 0, vz: 0 });
            }
            this.fireworkBursts.push({
                state: 'cooldown',
                timer: r * 0.75,
                x: 0, y: 0, z: 0,
                vy: 0, burstY: 3.6,
                rocketMesh,
                sparks,
                mat
            });
        }

        // C) Easter: Colorful 3D Painted Ostereier in Garden & Easter Basket on Lounge Table
        const easterGrp = new THREE.Group();
        const eggGeo = new THREE.SphereGeometry(0.065, 8, 8);
        const eggMats = [this.mats.tulipPink, this.mats.sunflowerYellow, this.mats.blossomPink, goldMat];
        const eggSpots = [
            [-1.15, 10.25], [0.45, 10.65], [1.35, 9.95], [-2.85, 10.45],
            [-0.15, 11.85], [2.45, 10.35], [-3.75, 11.35], [0.95, 11.45]
        ];
        for (let i = 0; i < eggSpots.length; i++) {
            const egg = new THREE.Mesh(eggGeo, eggMats[i % eggMats.length]);
            egg.scale.set(0.86, 1.24, 0.86);
            egg.position.set(eggSpots[i][0], 0.075, eggSpots[i][1]);
            easterGrp.add(egg);
        }
        const basket = new THREE.Mesh(new THREE.CylinderGeometry(0.16, 0.12, 0.11, 10), this.mats.broomStraw);
        basket.position.set(-2.32, 0.43, 8.52);
        easterGrp.add(basket);
        garden.add(easterGrp);
        this.gardenOccasionGroups.easter = easterGrp;

        // D) Halloween: Carved Glowing 3D Jack-o'-Lanterns
        const halloGrp = new THREE.Group();
        const jackSpots = [[-1.52, 9.42], [0.18, 9.42], [-1.25, 10.55], [2.45, 9.52]];
        for (const [jx, jz] of jackSpots) {
            const jBody = new THREE.Mesh(new THREE.SphereGeometry(0.17, 10, 8), this.mats.pumpkinOrange);
            jBody.scale.set(1.15, 0.88, 1.15);
            jBody.position.set(jx, 0.15, jz);
            const jGlow = new THREE.Mesh(new THREE.PlaneGeometry(0.14, 0.08), this.mats.warmBulb);
            jGlow.position.set(jx, 0.16, jz + 0.185);
            halloGrp.add(jBody, jGlow);
        }
        garden.add(halloGrp);
        this.gardenOccasionGroups.halloween = halloGrp;

        // E) Valentines / Mother's Day / Seelenfunke Anniversary: Blooming Rose Bouquets
        const celebGrp = new THREE.Group();
        for (const [rx, rz] of [[-2.32, 8.52], [-1.56, 7.26], [0.26, 7.26]]) {
            const bouquet = new THREE.Mesh(new THREE.DodecahedronGeometry(0.12, 0), this.mats.roseBloom);
            bouquet.position.set(rx, rx === -2.32 ? 0.45 : 0.56, rz);
            celebGrp.add(bouquet);
        }
        garden.add(celebGrp);
        this.gardenOccasionGroups.celebration = celebGrp;

        this.scene.add(garden);
        freezeStaticTree(garden);
        garden.matrixAutoUpdate = true;

        // 9. GOLDENER "SEELENFUNKE" 3D-RASENMÄHER (24K Gold & Matte Obsidian + Spinning Wheels + Grass Particles!)
        // Local orientation: +Z = Front Mower Nose, -Z = Rear Grass Catcher & Twin Golden Push-Handlebars
        const mower = new THREE.Group();
        mower.matrixAutoUpdate = true;
        this.lawnMowerGroup = mower;

        const mowerDeck = new THREE.Group();
        mowerDeck.matrixAutoUpdate = true;
        this.lawnMowerDeck = mowerDeck;

        const chassisGold = new THREE.Mesh(new RoundedBoxGeometry(0.46, 0.125, 0.62, 2, 0.032), goldMat);
        chassisGold.position.set(0, 0.135, 0.05);
        const frontBumper = new THREE.Mesh(new RoundedBoxGeometry(0.49, 0.048, 0.085, 2, 0.016), goldMat);
        frontBumper.position.set(0, 0.095, 0.36);
        const frontObsidianGrille = new THREE.Mesh(new RoundedBoxGeometry(0.36, 0.034, 0.03, 1, 0.008), obsidianMat);
        frontObsidianGrille.position.set(0, 0.098, 0.395);

        const motorTurbine = new THREE.Mesh(new THREE.CylinderGeometry(0.128, 0.162, 0.135, 16), obsidianMat);
        motorTurbine.position.set(0, 0.255, 0.06);
        const motorGoldCrown = new THREE.Mesh(new THREE.CylinderGeometry(0.115, 0.126, 0.032, 16), goldMat);
        motorGoldCrown.position.set(0, 0.33, 0.06);
        const motorSparkStar = new THREE.Mesh(new THREE.OctahedronGeometry(0.038, 0), goldMat);
        motorSparkStar.position.set(0, 0.375, 0.06);

        // Prominent "SEELENFUNKE" Hood, Side-Skirt & Rear Catcher Plaques!
        const hoodPlaque = new THREE.Mesh(new THREE.PlaneGeometry(0.36, 0.125), this.plaqueMats.mowerObs);
        hoodPlaque.position.set(0, 0.212, 0.255);
        hoodPlaque.rotation.x = -0.96;
        const sidePlaqueL = new THREE.Mesh(new THREE.PlaneGeometry(0.33, 0.082), this.plaqueMats.mowerObs);
        sidePlaqueL.position.set(0.234, 0.142, 0.05);
        sidePlaqueL.rotation.y = Math.PI / 2;
        const sidePlaqueR = new THREE.Mesh(new THREE.PlaneGeometry(0.33, 0.082), this.plaqueMats.mowerObs);
        sidePlaqueR.position.set(-0.234, 0.142, 0.05);
        sidePlaqueR.rotation.y = -Math.PI / 2;

        // Rear Grass-Catcher Box (Fangkorb)
        const catcherBody = new THREE.Mesh(new RoundedBoxGeometry(0.38, 0.21, 0.27, 2, 0.025), obsidianMat);
        catcherBody.position.set(0, 0.195, -0.22);
        const catcherGoldLid = new THREE.Mesh(new RoundedBoxGeometry(0.39, 0.032, 0.28, 1, 0.01), goldMat);
        catcherGoldLid.position.set(0, 0.308, -0.22);
        const catcherRearPlaque = new THREE.Mesh(new THREE.PlaneGeometry(0.28, 0.088), this.plaqueMats.mowerGold);
        catcherRearPlaque.position.set(0, 0.20, -0.358);
        catcherRearPlaque.rotation.y = Math.PI;

        // Twin Ergonomic 24K Gold Push-Handlebars (exact match for Funkira's 2-handed push_mower pose!)
        const barLen = 0.58;
        const barGeo = new THREE.CylinderGeometry(0.015, 0.017, barLen, 10);
        for (const sx of [-1, 1]) {
            const strut = new THREE.Mesh(barGeo, goldMat);
            strut.position.set(sx * 0.195, 0.45, -0.21);
            strut.rotation.x = 0.30;
            mowerDeck.add(strut);
        }
        const gripCrossbar = new THREE.Mesh(new THREE.CylinderGeometry(0.017, 0.017, 0.44, 10), goldMat);
        gripCrossbar.rotation.z = Math.PI / 2;
        gripCrossbar.position.set(0, 0.72, -0.295);
        const safetyBail = new THREE.Mesh(new THREE.CylinderGeometry(0.010, 0.010, 0.36, 8), obsidianMat);
        safetyBail.rotation.z = Math.PI / 2;
        safetyBail.position.set(0, 0.745, -0.285);
        const handleBadge = new THREE.Mesh(new THREE.PlaneGeometry(0.22, 0.064), this.plaqueMats.mowerObs);
        handleBadge.position.set(0, 0.56, -0.245);
        handleBadge.rotation.x = -1.26;
        handleBadge.rotation.y = Math.PI;

        mowerDeck.add(
            chassisGold, frontBumper, frontObsidianGrille,
            motorTurbine, motorGoldCrown, motorSparkStar,
            hoodPlaque, sidePlaqueL, sidePlaqueR,
            catcherBody, catcherGoldLid, catcherRearPlaque,
            gripCrossbar, safetyBail, handleBadge
        );
        mower.add(mowerDeck);

        // 4 Spinning Wheels with 24K Gold Rims
        this.lawnMowerWheels = [];
        const wheelSpecs = [
            [-0.245, 0.085,  0.23, 0.085],
            [ 0.245, 0.085,  0.23, 0.085],
            [-0.250, 0.105, -0.13, 0.105],
            [ 0.250, 0.105, -0.13, 0.105]
        ];
        for (const [wx, wy, wz, wr] of wheelSpecs) {
            const wPivot = new THREE.Group();
            wPivot.matrixAutoUpdate = true;
            wPivot.position.set(wx, wy, wz);
            const tire = new THREE.Mesh(new THREE.CylinderGeometry(wr, wr, 0.052, 14), obsidianMat);
            tire.rotation.z = Math.PI / 2;
            const rim = new THREE.Mesh(new THREE.CylinderGeometry(wr * 0.62, wr * 0.62, 0.056, 8), goldMat);
            rim.rotation.z = Math.PI / 2;
            const spoke = new THREE.Mesh(new THREE.BoxGeometry(0.060, wr * 1.15, 0.022), goldMat);
            wPivot.add(tire, rim, spoke);
            mower.add(wPivot);
            this.lawnMowerWheels.push(wPivot);
        }

        this.scene.add(mower);
        this._parkLawnMowerHome();

        // Pooled Grass-Clipping Particles for Live Lawn Mowing
        const grassGeo = new THREE.BoxGeometry(0.022, 0.048, 0.015);
        const grassMatGreen = new THREE.MeshBasicMaterial({ color: 0x56B85E });
        const grassMatGold  = new THREE.MeshBasicMaterial({ color: 0xE6C265 });
        for (let i = 0; i < 14; i++) {
            const gm = new THREE.Mesh(grassGeo, (i % 4 === 0) ? grassMatGold : grassMatGreen);
            gm.visible = false;
            this.scene.add(gm);
            this.grassParticles.push({ mesh: gm, vx: 0, vy: 0, vz: 0, life: 0 });
        }

        // 10. Pooled 3D Outdoor Weather Particles (Snowflakes / Blossom Petals / Sun Motes / Autumn Leaves)
        this.outdoorWeatherMat = new THREE.MeshBasicMaterial({ color: 0xE38B39, side: THREE.DoubleSide });
        const owGeo = new THREE.PlaneGeometry(0.052, 0.052);
        for (let i = 0; i < 34; i++) {
            const m = new THREE.Mesh(owGeo, this.outdoorWeatherMat);
            const px = (Math.random() - 0.5) * 9.6;
            const py = 0.15 + Math.random() * 3.3;
            const pz = 7.15 + Math.random() * 5.8;
            m.position.set(px, py, pz);
            m.rotation.set(Math.random() * 3, Math.random() * 3, 0);
            this.scene.add(m);
            this.outdoorWeatherParticles.push({
                mesh: m,
                baseX: px,
                y: py,
                baseZ: pz,
                speedY: 0.24 + Math.random() * 0.26,
                phase: Math.random() * 6.28
            });
        }
    }

    // ========================================================================
    // BUILDER FOR 8 MOVABLE, PERSISTENT, SIGNED "MEIN SEELENFUNKE" 3D ITEMS
    // ========================================================================
    _buildMovableSeelenfunkeItems() {
        const goldMat     = this.mats.gold24k;
        const roseGoldMat = this.mats.roseGold;
        const obsidianMat = this.mats.obsidian;
        const leafMat     = this.mats.leafGreen;

        const makeItemGroup = () => {
            const g = new THREE.Group();
            g.matrixAutoUpdate = true;
            return g;
        };

        // 1. TROPHY: Goldener Seelenfunke-Wanderpokal
        {
            const g = makeItemGroup();
            const base = new THREE.Mesh(new RoundedBoxGeometry(0.17, 0.045, 0.17, 2, 0.01), obsidianMat);
            base.position.y = 0.0225;
            const collar = new THREE.Mesh(new RoundedBoxGeometry(0.135, 0.022, 0.135, 1, 0.006), goldMat);
            collar.position.y = 0.054;
            const plaque = new THREE.Mesh(new THREE.PlaneGeometry(0.145, 0.036), this.plaqueMats.trophy);
            plaque.position.set(0, 0.024, 0.088);
            const cupPts = [
                new THREE.Vector2(0.00, 0.00), new THREE.Vector2(0.055, 0.00),
                new THREE.Vector2(0.024, 0.045), new THREE.Vector2(0.024, 0.095),
                new THREE.Vector2(0.088, 0.195), new THREE.Vector2(0.096, 0.235)
            ];
            const cup = new THREE.Mesh(new THREE.LatheGeometry(cupPts, 14), goldMat);
            cup.position.y = 0.065;
            for (const sx of [-1, 1]) {
                const handle = new THREE.Mesh(new THREE.TorusGeometry(0.048, 0.009, 6, 12, Math.PI * 1.15), goldMat);
                handle.position.set(sx * 0.075, 0.22, 0);
                handle.rotation.z = sx * (-Math.PI * 0.5);
                g.add(handle);
            }
            const starFinial = new THREE.Mesh(new THREE.OctahedronGeometry(0.040, 0), this.mats.warmBulb);
            starFinial.position.y = 0.34;
            g.add(base, collar, plaque, cup, starFinial);
            this.worldItems.trophy.group = g;
        }

        // 2. FLOWERPOT: Goldener Seelenfunke-Blumentopf mit Goldblüten
        {
            const g = makeItemGroup();
            const pot = new THREE.Mesh(new THREE.CylinderGeometry(0.105, 0.076, 0.155, 14), goldMat);
            pot.position.y = 0.078;
            const rim = new THREE.Mesh(new THREE.TorusGeometry(0.105, 0.011, 8, 16), obsidianMat);
            rim.rotation.x = Math.PI / 2;
            rim.position.y = 0.155;
            const soil = new THREE.Mesh(new THREE.CylinderGeometry(0.098, 0.098, 0.02, 12), this.mats.soilRich);
            soil.position.y = 0.148;
            const plaque = new THREE.Mesh(new THREE.PlaneGeometry(0.135, 0.044), this.plaqueMats.flowers);
            plaque.position.set(0, 0.085, 0.098);
            g.add(pot, rim, soil, plaque);
            for (let i = 0; i < 5; i++) {
                const ang = (i / 5) * Math.PI * 2;
                const lf = new THREE.Mesh(new THREE.SphereGeometry(0.055, 8, 6), leafMat);
                lf.scale.set(1.25, 0.35, 0.8);
                lf.position.set(Math.cos(ang) * 0.065, 0.18, Math.sin(ang) * 0.065);
                lf.rotation.y = -ang;
                g.add(lf);
            }
            const flowerSpecs = [[0, 0.29, 0, goldMat], [-0.045, 0.25, 0.03, this.mats.tulipPink], [0.045, 0.26, -0.02, this.mats.sunflowerYellow]];
            for (const [fx, fy, fz, fmat] of flowerSpecs) {
                const st = new THREE.Mesh(new THREE.CylinderGeometry(0.006, 0.006, 0.12, 6), leafMat);
                st.position.set(fx * 0.6, fy - 0.06, fz * 0.6);
                const bl = new THREE.Mesh(new THREE.DodecahedronGeometry(0.036, 0), fmat);
                bl.position.set(fx, fy, fz);
                g.add(st, bl);
            }
            this.worldItems.flowerpot.group = g;
        }

        // 3. VASE: Goldene Seelenfunke-Rosenvase
        {
            const g = makeItemGroup();
            const vPts = [
                new THREE.Vector2(0.00, 0.00), new THREE.Vector2(0.062, 0.00),
                new THREE.Vector2(0.092, 0.08), new THREE.Vector2(0.052, 0.17), new THREE.Vector2(0.072, 0.22)
            ];
            const body = new THREE.Mesh(new THREE.LatheGeometry(vPts, 14), obsidianMat);
            const goldBand = new THREE.Mesh(new THREE.TorusGeometry(0.088, 0.009, 8, 16), goldMat);
            goldBand.rotation.x = Math.PI / 2;
            goldBand.position.y = 0.095;
            const plaque = new THREE.Mesh(new THREE.PlaneGeometry(0.125, 0.040), this.plaqueMats.goldMain);
            plaque.position.set(0, 0.095, 0.096);
            g.add(body, goldBand, plaque);
            for (let r = 0; r < 5; r++) {
                const ang = r * 1.256;
                const rad = (r === 0) ? 0 : 0.036;
                const st = new THREE.Mesh(new THREE.CylinderGeometry(0.005, 0.005, 0.14, 5), leafMat);
                st.position.set(Math.cos(ang) * rad * 0.7, 0.24, Math.sin(ang) * rad * 0.7);
                const bl = new THREE.Mesh(new THREE.DodecahedronGeometry(0.030, 0), (r === 0) ? goldMat : this.mats.roseBloom);
                bl.position.set(Math.cos(ang) * rad, 0.29 + (r === 0 ? 0.025 : 0), Math.sin(ang) * rad);
                g.add(st, bl);
            }
            this.worldItems.vase.group = g;
        }

        // 4. PIGGYBANK: Goldene Seelenfunke-Schatzkasse / Spardose
        {
            const g = makeItemGroup();
            const body = new THREE.Mesh(new THREE.CylinderGeometry(0.092, 0.092, 0.175, 16), goldMat);
            body.position.y = 0.092;
            const base = new THREE.Mesh(new THREE.CylinderGeometry(0.098, 0.098, 0.022, 16), obsidianMat);
            base.position.y = 0.011;
            const lid = new THREE.Mesh(new THREE.CylinderGeometry(0.096, 0.096, 0.022, 16), roseGoldMat);
            lid.position.y = 0.182;
            const slot = new THREE.Mesh(new RoundedBoxGeometry(0.085, 0.012, 0.016, 1, 0.003), obsidianMat);
            slot.position.y = 0.194;
            const coin = new THREE.Mesh(new THREE.CylinderGeometry(0.028, 0.028, 0.007, 12), goldMat);
            coin.rotation.x = Math.PI / 2;
            coin.position.set(0, 0.212, 0);
            const plaque = new THREE.Mesh(new THREE.PlaneGeometry(0.138, 0.046), this.plaqueMats.obsidianMain);
            plaque.position.set(0, 0.096, 0.096);
            g.add(body, base, lid, slot, coin, plaque);
            this.worldItems.piggybank.group = g;
        }

        // 5. MUG: Schwarz-Goldene Seelenfunke-Lieblingstasse
        {
            const g = makeItemGroup();
            const saucer = new THREE.Mesh(new THREE.CylinderGeometry(0.095, 0.072, 0.016, 14), goldMat);
            saucer.position.y = 0.008;
            const cup = new THREE.Mesh(new THREE.CylinderGeometry(0.062, 0.054, 0.128, 14), obsidianMat);
            cup.position.y = 0.076;
            const cupRim = new THREE.Mesh(new THREE.TorusGeometry(0.062, 0.007, 8, 16), goldMat);
            cupRim.rotation.x = Math.PI / 2;
            cupRim.position.y = 0.14;
            const coffeeTop = new THREE.Mesh(new THREE.CylinderGeometry(0.056, 0.056, 0.006, 12), this.mats.cognacLeather);
            coffeeTop.position.y = 0.132;
            const handle = new THREE.Mesh(new THREE.TorusGeometry(0.036, 0.008, 6, 12, Math.PI * 1.15), goldMat);
            handle.position.set(0.064, 0.082, 0);
            handle.rotation.z = -Math.PI * 0.5;
            const plaque = new THREE.Mesh(new THREE.PlaneGeometry(0.098, 0.036), this.plaqueMats.goldMain);
            plaque.position.set(0, 0.080, 0.063);
            g.add(saucer, cup, cupRim, coffeeTop, handle, plaque);
            this.worldItems.mug.group = g;
        }

        // 6. LANTERN: Goldene Seelenfunke-Gartenlaterne
        {
            const g = makeItemGroup();
            const base = new THREE.Mesh(new RoundedBoxGeometry(0.16, 0.032, 0.16, 2, 0.008), obsidianMat);
            base.position.y = 0.016;
            const chamber = new THREE.Mesh(new RoundedBoxGeometry(0.115, 0.16, 0.115, 1, 0.01), this.mats.crystalGlass);
            chamber.position.y = 0.112;
            const candle = new THREE.Mesh(new THREE.CylinderGeometry(0.026, 0.028, 0.085, 10), this.mats.warmBulb);
            candle.position.y = 0.078;
            for (const [px, pz] of [[-0.062, -0.062], [0.062, -0.062], [-0.062, 0.062], [0.062, 0.062]]) {
                const post = new THREE.Mesh(new THREE.CylinderGeometry(0.007, 0.007, 0.165, 6), goldMat);
                post.position.set(px, 0.114, pz);
                g.add(post);
            }
            const roof = new THREE.Mesh(new THREE.ConeGeometry(0.125, 0.075, 4), goldMat);
            roof.rotation.y = Math.PI / 4;
            roof.position.y = 0.23;
            const ring = new THREE.Mesh(new THREE.TorusGeometry(0.028, 0.006, 6, 12), goldMat);
            ring.position.y = 0.285;
            const plaque = new THREE.Mesh(new THREE.PlaneGeometry(0.132, 0.034), this.plaqueMats.obsidianMain);
            plaque.position.set(0, 0.018, 0.083);
            g.add(base, chamber, candle, roof, ring, plaque);
            this.worldItems.lantern.group = g;
        }

        // 7. GIFTBOX: Schwarze Westpack-Geschenkbox Mein Seelenfunke
        {
            const g = makeItemGroup();
            const box = new THREE.Mesh(new RoundedBoxGeometry(0.22, 0.115, 0.18, 2, 0.015), obsidianMat);
            box.position.y = 0.058;
            const lid = new THREE.Mesh(new RoundedBoxGeometry(0.23, 0.032, 0.19, 2, 0.01), obsidianMat);
            lid.position.y = 0.106;
            const ribX = new THREE.Mesh(new RoundedBoxGeometry(0.234, 0.122, 0.026, 1, 0.004), goldMat);
            ribX.position.y = 0.062;
            const ribZ = new THREE.Mesh(new RoundedBoxGeometry(0.026, 0.122, 0.194, 1, 0.004), goldMat);
            ribZ.position.y = 0.062;
            for (const sx of [-1, 1]) {
                const loop = new THREE.Mesh(new THREE.TorusGeometry(0.028, 0.008, 6, 12), goldMat);
                loop.position.set(sx * 0.026, 0.136, 0);
                loop.rotation.z = sx * 0.45;
                g.add(loop);
            }
            const seal = new THREE.Mesh(new THREE.PlaneGeometry(0.155, 0.046), this.plaqueMats.goldMain);
            seal.position.set(0, 0.058, 0.098);
            g.add(box, lid, ribX, ribZ, seal);
            this.worldItems.giftbox.group = g;
        }

        // 8. FRAME: Goldener Seelenfunke-Tischbilderrahmen
        {
            const g = makeItemGroup();
            const easelGrp = new THREE.Group();
            easelGrp.position.set(0, 0.115, 0.01);
            easelGrp.rotation.x = -0.16;
            const outer = new THREE.Mesh(new RoundedBoxGeometry(0.23, 0.21, 0.024, 2, 0.01), goldMat);
            const matte = new THREE.Mesh(new RoundedBoxGeometry(0.19, 0.17, 0.026, 1, 0.006), obsidianMat);
            const art = new THREE.Mesh(
                new THREE.PlaneGeometry(0.175, 0.155),
                new THREE.MeshBasicMaterial({ map: makeSeelenfunkeArtCanvasTexture('spark_mandala', 'MEIN SEELENFUNKE') })
            );
            art.position.z = 0.015;
            const plaque = new THREE.Mesh(new THREE.PlaneGeometry(0.15, 0.036), this.plaqueMats.obsidianMain);
            plaque.position.set(0, -0.088, 0.016);
            easelGrp.add(outer, matte, art, plaque);
            const strut = new THREE.Mesh(new RoundedBoxGeometry(0.035, 0.16, 0.014, 1, 0.004), obsidianMat);
            strut.position.set(0, 0.075, -0.042);
            strut.rotation.x = 0.38;
            g.add(easelGrp, strut);
            this.worldItems.frame.group = g;
        }

        // Position every 3D item at its persisted location (or default home spot if never moved yet)
        for (const spec of this._defaultItemSpecs) {
            const saved = this.savedItemPlacements[spec.id];
            if (saved && this.dropSpots[saved.spotKey]) {
                const xz = (Number.isFinite(saved.x) && Number.isFinite(saved.z)) ? [saved.x, saved.z] : null;
                this._placeItemImmediate(spec.id, saved.spotKey, xz, false);
            } else {
                const sp = this.dropSpots[spec.defaultSpot];
                this._placeItemImmediate(spec.id, spec.defaultSpot, sp.slots[0], false);
            }
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
        const isXmasSeason = (occ === 'advent' || occ === 'christmas' || occ === 'nikolaus');
        const isSilvester = (occ === 'silvester' || occ === 'new_year');

        if (this.occasionGroups.christmas) {
            this.occasionGroups.christmas.visible = isXmasSeason;
        }

        // Sync Outdoor 3D Garden Seasonal & Holiday Groups
        if (this.gardenSeasonGroups.spring) this.gardenSeasonGroups.spring.visible = (sea === 'spring');
        if (this.gardenSeasonGroups.summer) this.gardenSeasonGroups.summer.visible = (sea === 'summer');
        if (this.gardenSeasonGroups.autumn) this.gardenSeasonGroups.autumn.visible = (sea === 'autumn');
        if (this.gardenSeasonGroups.winter) this.gardenSeasonGroups.winter.visible = (sea === 'winter');

        if (this.gardenOccasionGroups.christmas) this.gardenOccasionGroups.christmas.visible = isXmasSeason;
        if (this.gardenOccasionGroups.silvester) this.gardenOccasionGroups.silvester.visible = isSilvester;
        if (this.gardenOccasionGroups.easter) this.gardenOccasionGroups.easter.visible = (occ === 'easter');
        if (this.gardenOccasionGroups.halloween) this.gardenOccasionGroups.halloween.visible = (occ === 'halloween');
        if (this.gardenOccasionGroups.celebration) {
            this.gardenOccasionGroups.celebration.visible = (
                occ === 'valentines' || occ === 'mothers_day' || occ === 'anniversary' || occ === 'pentecost'
            );
        }

        if (!isSilvester && this.fireworkBursts) {
            for (const fb of this.fireworkBursts) {
                fb.rocketMesh.visible = false;
                for (const sp of fb.sparks) sp.mesh.visible = false;
            }
        }

        if (this.windowTexture) {
            paintScenicWindowCanvas(this.windowCanvas, sea, this.worldState.isLateNight, occ);
            this.windowTexture.needsUpdate = true;
        }
        if (this.gardenGroundTexture) {
            paintGardenGroundCanvas(this.gardenGroundCanvas, sea, occ);
            this.gardenGroundTexture.needsUpdate = true;
        }
        if (this.terraceDeckTexture) {
            paintTerraceDeckCanvas(this.terraceDeckCanvas, sea);
            this.terraceDeckTexture.needsUpdate = true;
        }

        if (this.sunbeamMeshes && this.sunbeamMeshes.length > 0) {
            for (let i = 0; i < this.sunbeamMeshes.length; i++) {
                this.sunbeamMeshes[i].visible = (!this.worldState.isLateNight && !isSilvester);
            }
        }

        const partColors = { autumn: 0xE38B39, winter: 0xF2F7FF, spring: 0xFFB8CE, summer: 0xFFE89E };
        if (this.weatherMat) {
            this.weatherMat.color.setHex(partColors[sea] || 0xE38B39);
        }
        if (this.outdoorWeatherMat) {
            this.outdoorWeatherMat.color.setHex(partColors[sea] || 0xE38B39);
        }

        // Outdoor Sky Backdrop Tint when Funkira steps onto Terrasse / Garten
        if (this.worldState.isLateNight || isSilvester) {
            this.outdoorSkyColor.setHex(0x0d1224);
        } else if (sea === 'winter') {
            this.outdoorSkyColor.setHex(0x3a506b);
        } else if (sea === 'spring') {
            this.outdoorSkyColor.setHex(0x3a6488);
        } else if (sea === 'summer') {
            this.outdoorSkyColor.setHex(0x2f6899);
        } else {
            this.outdoorSkyColor.setHex(0x3d2e44);
        }

        if (this.outfits.scarf) {
            this.outfits.scarf.visible = (sea === 'winter' || sea === 'autumn' || isXmasSeason);
        }
        if (this.outfits.santaHat) {
            this.outfits.santaHat.visible = isXmasSeason;
        }
        if (this.outfits.bunnyEars) {
            this.outfits.bunnyEars.visible = (occ === 'easter');
        }
        if (this.outfits.sunglasses) {
            this.outfits.sunglasses.visible = (sea === 'summer' && (occ === 'none' || occ === 'standard') && !this.worldState.isLateNight);
        }
        if (this.outfits.crown) {
            this.outfits.crown.visible = (
                occ === 'anniversary' || isSilvester || occ === 'valentines' || occ === 'mothers_day'
            );
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
        root.rotation.y = this.facingYaw;
        this.scene.add(root);
        this.rig.root = root;

        const shadowDisc = new THREE.Mesh(
            new THREE.PlaneGeometry(0.98, 0.98),
            new THREE.MeshBasicMaterial({ map: makeShadowDiscTexture(), transparent: true, depthWrite: false })
        );
        shadowDisc.rotation.x = -Math.PI / 2;
        shadowDisc.position.y = 0.012;
        root.add(shadowDisc);
        this.rig.shadowDisc = shadowDisc;

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

        const torsoPivot = new THREE.Group();
        torsoPivot.position.y = 0.08;
        pelvis.add(torsoPivot);
        this.rig.torso = torsoPivot;

        const carryAnchor = new THREE.Group();
        carryAnchor.matrixAutoUpdate = true;
        carryAnchor.position.set(0, 0.13, 0.33);
        torsoPivot.add(carryAnchor);
        this.rig.carryAnchor = carryAnchor;

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

        const shCuffGeo    = new THREE.TorusGeometry(0.092, 0.023, 8, 18);
        const shBallGeo    = new THREE.SphereGeometry(0.074, 12, 10);
        const upperArmGeo  = new THREE.CylinderGeometry(0.068, 0.060, 0.21, 14);
        const upTrimGeo    = new THREE.TorusGeometry(0.064, 0.011, 6, 16);
        const elBallGeo    = new THREE.SphereGeometry(0.058, 10, 8);
        const forearmGeo   = new THREE.CylinderGeometry(0.062, 0.075, 0.21, 14);
        const wrCuffGeo    = new THREE.TorusGeometry(0.073, 0.016, 8, 16);
        const palmGeo      = new THREE.SphereGeometry(0.075, 12, 10);
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

        // Rose-Gold & 24K Gold Gießkanne (Watering Can) in Right Hand for 3D Gartenarbeit!
        const wateringCan = new THREE.Group();
        const wcBody = new THREE.Mesh(new THREE.CylinderGeometry(0.082, 0.098, 0.19, 14), roseGoldMat);
        wcBody.position.set(0, -0.15, 0.08);
        const wcRim = new THREE.Mesh(new THREE.TorusGeometry(0.082, 0.012, 8, 16), gold24kMat);
        wcRim.rotation.x = Math.PI / 2;
        wcRim.position.set(0, -0.055, 0.08);
        const wcHandle = new THREE.Mesh(new THREE.TorusGeometry(0.088, 0.014, 8, 16, Math.PI * 1.28), gold24kMat);
        wcHandle.rotation.y = Math.PI / 2;
        wcHandle.position.set(0, -0.055, 0.04);
        const wcSpout = new THREE.Mesh(new THREE.CylinderGeometry(0.014, 0.024, 0.22, 10), gold24kMat);
        wcSpout.position.set(0, -0.10, 0.22);
        wcSpout.rotation.x = 0.88;
        const wcRoseHead = new THREE.Mesh(new THREE.ConeGeometry(0.038, 0.042, 10), gold24kMat);
        wcRoseHead.position.set(0, -0.03, 0.31);
        wcRoseHead.rotation.x = 0.88 + Math.PI;
        const spoutTip = new THREE.Object3D();
        spoutTip.position.set(0, -0.02, 0.33);
        wateringCan.add(wcBody, wcRim, wcHandle, wcSpout, wcRoseHead, spoutTip);
        if (this.plaqueMats && this.plaqueMats.mowerObs) {
            for (const side of [-1, 1]) {
                const wcBadge = new THREE.Mesh(new THREE.PlaneGeometry(0.11, 0.042), this.plaqueMats.mowerObs);
                wcBadge.position.set(side * 0.091, -0.15, 0.08);
                wcBadge.rotation.y = side * Math.PI * 0.5;
                wateringCan.add(wcBadge);
            }
        }
        wateringCan.visible = false;
        this.rig.armR.wristPivot.add(wateringCan);
        this.rig.wateringCan = wateringCan;
        this.rig.wateringSpoutTip = spoutTip;

        const wdGeo = new THREE.BoxGeometry(0.022, 0.044, 0.022);
        for (let i = 0; i < 16; i++) {
            const wd = new THREE.Mesh(wdGeo, this.mats.waterBlue);
            wd.visible = false;
            this.scene.add(wd);
            this.waterDropParticles.push({ mesh: wd, vx: 0, vy: 0, vz: 0, life: 0 });
        }

        // 24K Gold & Walnut Gartenschaufel / Pflanzkelle (Garden Trowel) in Right Hand!
        const gardenTrowel = new THREE.Group();
        const trHandle = new THREE.Mesh(new THREE.CylinderGeometry(0.017, 0.019, 0.14, 8), this.mats.broomWood);
        trHandle.position.set(0, -0.06, 0.02);
        trHandle.rotation.x = Math.PI * 0.38;
        const trFerrule = new THREE.Mesh(new THREE.CylinderGeometry(0.020, 0.020, 0.025, 8), gold24kMat);
        trFerrule.position.set(0, -0.09, 0.09);
        trFerrule.rotation.x = Math.PI * 0.38;
        const trBlade = new THREE.Mesh(new THREE.ConeGeometry(0.052, 0.16, 6), gold24kMat);
        trBlade.scale.set(1.0, 1.0, 0.32);
        trBlade.position.set(0, -0.14, 0.19);
        trBlade.rotation.x = Math.PI * 0.42;
        gardenTrowel.add(trHandle, trFerrule, trBlade);
        gardenTrowel.visible = false;
        this.rig.armR.wristPivot.add(gardenTrowel);
        this.rig.gardenTrowel = gardenTrowel;

        // Luxury Seelenfunke Obsidian & 24K Gold Smartphone in Right Hand (for Whitelist Phone-Concierge!)
        const smartphone = new THREE.Group();
        const phoneRim = new THREE.Mesh(new RoundedBoxGeometry(0.098, 0.196, 0.014, 2, 0.016), gold24kMat);
        const phoneBody = new THREE.Mesh(new RoundedBoxGeometry(0.093, 0.191, 0.016, 2, 0.015), this.mats.obsidian);
        this.phoneScreenMat = new THREE.MeshBasicMaterial({ map: makeSmartphoneScreenTexture('Papa') });
        const phoneScreen = new THREE.Mesh(new THREE.PlaneGeometry(0.082, 0.174), this.phoneScreenMat);
        phoneScreen.position.set(0, 0, -0.0086);
        phoneScreen.rotation.y = Math.PI;

        // Pro Triple-Lens Gold Camera Island + Seelenfunke Gold Emblem on Back (visible to viewer!)
        const camIsland = new THREE.Mesh(new RoundedBoxGeometry(0.038, 0.046, 0.004, 1, 0.007), darkArmorMat);
        camIsland.position.set(-0.020, 0.062, 0.009);
        smartphone.add(phoneRim, phoneBody, phoneScreen, camIsland);
        const lensRingGeo = new THREE.CylinderGeometry(0.0075, 0.0075, 0.004, 10);
        lensRingGeo.rotateX(Math.PI * 0.5);
        const lensGlassGeo = new THREE.CylinderGeometry(0.0052, 0.0052, 0.0045, 10);
        lensGlassGeo.rotateX(Math.PI * 0.5);
        for (const [lx, ly] of [[-0.028, 0.072], [-0.028, 0.052], [-0.012, 0.062]]) {
            const lRing = new THREE.Mesh(lensRingGeo, gold24kMat);
            lRing.position.set(lx, ly, 0.0108);
            const lGlass = new THREE.Mesh(lensGlassGeo, jointDarkMat);
            lGlass.position.set(lx, ly, 0.0112);
            smartphone.add(lRing, lGlass);
        }
        const backStar = new THREE.Mesh(new THREE.OctahedronGeometry(0.010, 0), gold24kMat);
        backStar.scale.set(1.0, 1.35, 0.25);
        backStar.position.set(0.0, 0.004, 0.0092);
        smartphone.add(backStar);

        smartphone.position.set(-0.012, -0.050, 0.002);
        smartphone.rotation.set(2.724, 0.108, -0.037);
        smartphone.visible = this.phoneCallActive;
        this.rig.armR.wristPivot.add(smartphone);
        this.rig.smartphone = smartphone;

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

        const sirenBase = new THREE.Mesh(new THREE.CylinderGeometry(0.115, 0.138, 0.046, 16), roseGoldMat);
        sirenBase.position.set(0, 0.578, 0);
        this.sirenMat = new THREE.MeshBasicMaterial({ color: 0xe8829b });
        const sirenDome = new THREE.Mesh(new THREE.CylinderGeometry(0.088, 0.106, 0.115, 16), this.sirenMat);
        sirenDome.position.set(0, 0.648, 0);
        headPivot.add(sirenBase, sirenDome);
        this.rig.sirenDome = sirenDome;

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
    // ZERO-GC, DIRTY-CHECKED VISOR RENDERER
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
    // AUTONOMOUS 2-STORY HOUSE + TERRASSE & GARTEN PAUSE WORKFLOW & POSES
    // ========================================================================
    _pickNextAutonomousTask(nowMs, immediate = false) {
        if (this.currentTask === 'mow_lawn') {
            this._parkLawnMowerHome();
            this.moveSpeed = 1.48;
        }
        const lvl = this.worldState.shopLevel || 1;
        const sea = this.worldState.season || 'autumn';
        const occ = this.worldState.occasion || 'none';
        const isSilvester = (occ === 'silvester' || occ === 'new_year');
        const isXmasSeason = (occ === 'advent' || occ === 'christmas' || occ === 'nikolaus');

        const tasks = [
            { id: 'living_sofa',    floor: 0, x: -1.85, z:  1.95, yaw: -Math.PI * 0.5, emotion: 'sweet',   gesture: 'hands_on_cheeks', dur: 16000 },
            { id: 'kitchen_island', floor: 0, x:  2.35, z:  4.45, yaw: -Math.PI * 0.5, emotion: 'happy',   gesture: 'polish',          dur: 15000 }
        ];

        // Seasonal & Holiday Outdoor Terrace & Garden Tasks (incl. Golden SEELENFUNKE Lawn Mower)!
        if (isSilvester) {
            tasks.push(
                { id: 'silvester_fireworks', floor: 0, x: -0.35, z: 8.35, yaw: 0.0, emotion: 'excited', gesture: 'cheer', dur: 18500 },
                { id: 'silvester_toast',     floor: 0, x:  0.55, z: 7.85, yaw: Math.PI * 0.4, emotion: 'happy', gesture: 'wave', dur: 15500 }
            );
        } else if (!this.worldState.isLateNight) {
            if (sea === 'spring' || sea === 'summer') {
                tasks.push(
                    { id: 'hochbeet_water', floor: 0, x:  2.02, z:  8.60, yaw:  Math.PI * 0.5, emotion: 'sweet', gesture: 'water_plants',    dur: 16500 },
                    { id: 'mow_lawn',       floor: 0, x:  2.18, z:  9.95, yaw: -Math.PI * 0.5, emotion: 'proud', gesture: 'push_mower',      dur: 19500 },
                    { id: 'flowerbed_tend', floor: 0, x: -2.45, z:  9.98, yaw:  0.0,           emotion: 'happy', gesture: 'garden_work',     dur: 16500 },
                    { id: 'terrace_sun',    floor: 0, x: -0.72, z:  8.32, yaw:  0.0,           emotion: 'happy', gesture: 'hands_on_cheeks', dur: 15000 }
                );
            } else if (sea === 'autumn') {
                tasks.push(
                    { id: 'hochbeet_water', floor: 0, x:  2.02, z:  8.60, yaw:  Math.PI * 0.5, emotion: 'sweet', gesture: 'water_plants', dur: 16000 },
                    { id: 'mow_lawn',       floor: 0, x:  2.18, z:  9.95, yaw: -Math.PI * 0.5, emotion: 'proud', gesture: 'push_mower',   dur: 18500 },
                    { id: 'terrace_sweep',  floor: 0, x: -0.72, z:  8.32, yaw:  0.25,          emotion: 'happy', gesture: 'sweep',        dur: 15500 },
                    { id: 'flowerbed_tend', floor: 0, x: -2.45, z:  9.98, yaw:  0.0,           emotion: 'happy', gesture: 'garden_work',  dur: 16000 }
                );
            } else if (sea === 'winter') {
                tasks.push(
                    { id: 'snowman_visit',  floor: 0, x: -2.62, z:  9.55, yaw: -Math.PI * 0.42, emotion: 'excited', gesture: 'wave', dur: 15500 },
                    { id: 'terrace_sweep',  floor: 0, x: -0.72, z:  8.32, yaw: 0.20,            emotion: 'happy',   gesture: 'sweep', dur: 15000 }
                );
            }
            if (occ === 'easter') {
                tasks.push({ id: 'easter_garden', floor: 0, x: -0.35, z: 10.65, yaw: 0.0, emotion: 'excited', gesture: 'cheer', dur: 16000 });
            } else if (isXmasSeason) {
                tasks.push({ id: 'xmas_garden_tree', floor: 0, x: 0.85, z: 10.75, yaw: Math.PI * 0.32, emotion: 'sweet', gesture: 'hands_on_cheeks', dur: 16000 });
            }
        }

        tasks.push(
            { id: 'office_desk', floor: 0, x: -1.08, z: -1.30, yaw: -Math.PI * 0.5, emotion: 'curious', gesture: 'point_left',    dur: 14500 },
            { id: 'gym_workout', floor: 0, x:  1.25, z: -5.25, yaw:  Math.PI * 0.5, emotion: 'excited', gesture: 'cheer',         dur: 14500 },
            { id: 'og_studio',   floor: 1, x: -1.45, z: -5.00, yaw:  Math.PI,       emotion: 'curious', gesture: 'hands_on_hips', dur: 14500 },
            { id: 'polish',      floor: 1, x: -1.65, z:  4.68, yaw:  0.0,           emotion: 'proud',   gesture: 'polish',        dur: 14000 }
        );

        if (lvl >= 3) {
            tasks.push({ id: 'laser', floor: 1, x: 1.72, z: 4.68, yaw: 0.0, emotion: 'curious', gesture: 'point_left', dur: 13500 });
        }
        if (this.worldState.isLateNight && !isSilvester) {
            tasks.push({ id: 'bedroom_rest', floor: 0, x: -1.45, z: -4.65, yaw: -Math.PI * 0.5, emotion: 'sleepy', gesture: 'idle', dur: 17500 });
        }

        if (immediate) {
            // On Pause start: head outside on Silvester / daytime pleasant weather, or into Wohnzimmer/Küche!
            this.taskIndex = (!this.worldState.isLateNight || isSilvester) && tasks.length > 2 ? 2 : (Math.random() < 0.55 ? 0 : 1);
        } else {
            this.taskIndex = (this.taskIndex + 1) % tasks.length;
        }
        const t = tasks[this.taskIndex % tasks.length];
        this.currentTask = t.id;
        this._routeTo(t.x, t.z, t.floor);
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
            if (activeGesture === 'push_mower' && !this.phoneCallActive) {
                const vib = Math.sin(tSec * 28.0) * 0.012;
                pose.rS = [-0.92 + vib, -0.10, -0.08];
                pose.rE = [-0.36, 0, 0.08];
                pose.rW = [0.10, 0, 0];
                pose.rCurl = 0.88;
                pose.lS = [-0.92 + vib, 0.10, 0.08];
                pose.lE = [-0.36, 0, -0.08];
                pose.lW = [0.10, 0, 0];
                pose.lCurl = 0.88;
                return pose;
            }
            if (this.carriedItemId && !this.phoneCallActive) {
                const bob = Math.sin(walkCycle * 2.0) * 0.035;
                pose.rS = [-0.82 + bob, -0.26, -0.14];
                pose.rE = [-0.92, -0.18, 0.22];
                pose.rW = [0.16, 0.18, 0];
                pose.rCurl = 0.65;
                pose.lS = [-0.82 + bob, 0.26, 0.14];
                pose.lE = [-0.92, 0.18, -0.22];
                pose.lW = [0.16, -0.18, 0];
                pose.lCurl = 0.65;
                return pose;
            }
            const sw = Math.sin(walkCycle) * 0.58;
            if (this.phoneCallActive) {
                pose.rS = [-2.12, 0.36, -0.84];
                pose.rE = [-0.84, 0.0, 0.18];
                pose.rW = [0.15, 0.25, -0.10];
                pose.rCurl = 0.74;
            } else {
                pose.rS = [sw, 0, -0.20];
                pose.rE = [-0.45 - Math.max(0, sw) * 0.25, 0, 0];
                pose.rCurl = 0.55;
            }
            pose.lS = [-sw, 0, 0.20];
            pose.lE = [-0.45 - Math.max(0, -sw) * 0.25, 0, 0];
            pose.lCurl = 0.55;
            return pose;
        }

        const wv = Math.sin(tSec * 6.8);
        switch (activeGesture) {
            case 'talk_phone': {
                const sp = this.isSpeaking ? Math.sin(tSec * 5.6) * 0.12 : 0;
                const nod = this.isSpeaking ? Math.sin(tSec * 8.2) * 0.025 : 0;
                // Right hand holds Seelenfunke Smartphone snugly beside Funkira's right earPod!
                pose.rS = [-2.25 + nod, 0.40, -0.90];
                pose.rE = [-0.80, 0.0, 0.20];
                pose.rW = [0.15, 0.25, -0.10];
                pose.rCurl = 0.76;
                // Left hand gestures naturally while talking on the phone
                pose.lS = [-0.26 + sp, -0.10, 0.30 + Math.abs(sp) * 0.35];
                pose.lE = [-0.62 - Math.abs(sp) * 0.95, 0.12, -0.14];
                pose.lW = [0.10, 0, sp * 0.35];
                pose.lCurl = this.isSpeaking ? 0.22 : 0.45;
                break;
            }

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

            case 'duck': {
                const sh = Math.sin(tSec * 3.8) * 0.05;
                pose.rS = [-0.98 + sh, -0.22, -0.62];
                pose.rE = [-1.52, -0.28, 0.42];
                pose.lS = [-0.98 - sh,  0.22,  0.62];
                pose.lE = [-1.52,  0.28, -0.42];
                pose.rCurl = 0.35;
                pose.lCurl = 0.35;
                break;
            }

            case 'bow': {
                pose.rS = [-0.62, -0.38, -0.42];
                pose.rE = [-1.42, -0.25, 0.52];
                pose.lS = [0.18, -0.10, 0.78];
                pose.lE = [-0.28, 0, -0.12];
                pose.rCurl = 0.22;
                pose.lCurl = 0.15;
                break;
            }

            case 'stretch': {
                const st = Math.sin(tSec * 2.8) * 0.07;
                pose.rS = [-0.22, 0, -2.58 + st];
                pose.rE = [-0.18, 0, 0.08];
                pose.lS = [-0.22, 0,  2.58 - st];
                pose.lE = [-0.18, 0, -0.08];
                pose.rCurl = 0.08;
                pose.lCurl = 0.08;
                break;
            }

            case 'spin_arms': {
                pose.rS = [-0.18, 0.10, -1.42];
                pose.rE = [-0.25, 0, 0.12];
                pose.lS = [-0.18, -0.10, 1.42];
                pose.lE = [-0.25, 0, -0.12];
                pose.rCurl = 0.12;
                pose.lCurl = 0.12;
                break;
            }

            case 'dance': {
                const db = Math.sin(tSec * 6.2);
                pose.rS = [-0.55 + db * 0.45, 0.18, -1.35 - db * 0.55];
                pose.rE = [-0.95 + db * 0.35, 0, 0.22];
                pose.lS = [-0.55 - db * 0.45, -0.18, 1.35 - db * 0.55];
                pose.lE = [-0.95 - db * 0.35, 0, -0.22];
                pose.rCurl = 0.20;
                pose.lCurl = 0.20;
                break;
            }

            case 'water_plants': {
                const sway = Math.sin(tSec * 2.3) * 0.16;
                const tilt = Math.sin(tSec * 3.1) * 0.06;
                pose.rS = [-0.96, sway, -0.22];
                pose.rE = [-0.62, 0, 0.12];
                pose.rW = [0.44 + tilt, 0, 0];
                pose.rCurl = 0.84;
                pose.lS = [0.15, -0.25, 0.78];
                pose.lE = [-1.25, 0.35, -0.72];
                pose.lCurl = 0.65;
                break;
            }

            case 'garden_work': {
                const dig = Math.sin(tSec * 4.4);
                pose.rS = [-1.06 + dig * 0.28, -0.14, -0.24];
                pose.rE = [-0.78 - dig * 0.24, 0, 0.18];
                pose.rW = [0.26 + dig * 0.22, 0, 0];
                pose.rCurl = 0.86;
                pose.lS = [-0.88 - dig * 0.16, 0.18, 0.28];
                pose.lE = [-0.82, 0, -0.20];
                pose.lCurl = 0.45;
                break;
            }

            case 'carry_hold': {
                const cr = Math.sin(tSec * 2.4) * 0.025;
                pose.rS = [-0.82 + cr, -0.26, -0.14];
                pose.rE = [-0.92, -0.18, 0.22];
                pose.rW = [0.16, 0.18, 0];
                pose.rCurl = 0.65;
                pose.lS = [-0.82 + cr, 0.26, 0.14];
                pose.lE = [-0.92, 0.18, -0.22];
                pose.lW = [0.16, -0.18, 0];
                pose.lCurl = 0.65;
                break;
            }

            case 'carry_reach': {
                pose.rS = [-1.18, -0.16, -0.10];
                pose.rE = [-0.42, -0.10, 0.14];
                pose.rW = [0.22, 0.10, 0];
                pose.rCurl = 0.42;
                pose.lS = [-1.18, 0.16, 0.10];
                pose.lE = [-0.42, 0.10, -0.14];
                pose.lW = [0.22, -0.10, 0];
                pose.lCurl = 0.42;
                break;
            }

            case 'push_mower': {
                const vib = Math.sin(tSec * 26.0) * 0.010;
                pose.rS = [-0.92 + vib, -0.10, -0.08];
                pose.rE = [-0.36, 0, 0.08];
                pose.rW = [0.10, 0, 0];
                pose.rCurl = 0.88;
                pose.lS = [-0.92 + vib, 0.10, 0.08];
                pose.lE = [-0.36, 0, -0.08];
                pose.lW = [0.10, 0, 0];
                pose.lCurl = 0.88;
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

    // ========================================================================
    // MAIN 60-FPS RENDER FRAME LOOP (DRIVEN BY app.js UNIFIED RAF)
    // ========================================================================
    renderFrame(nowMs) {
        if (!this.running || !this.renderer) return;
        const dt = Math.min(0.05, Math.max(0.001, (nowMs - this.lastMs) / 1000));
        this.lastMs = nowMs;
        const tSec = nowMs * 0.001;

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

        if (this.mode === 'front_active') {
            if (this.phoneCallActive) {
                this.gesture = 'talk_phone';
                this.gestureUntilMs = 0;
            } else if (this.gestureUntilMs > 0 && nowMs > this.gestureUntilMs) {
                this.gesture = 'idle';
                this.gestureUntilMs = 0;
            }
            if (this.emotionUntilMs > 0 && nowMs > this.emotionUntilMs) {
                this.emotion = this.phoneCallActive ? 'happy' : (this.worldState.isLateNight ? 'sleepy' : 'neutral');
                this.emotionUntilMs = 0;
            }
        }
        if (this.rig.smartphone) {
            this.rig.smartphone.visible = this.phoneCallActive;
        }

        if (this.activeBodyAction !== 'none' && this.bodyActionUntilMs > 0 && nowMs >= this.bodyActionUntilMs) {
            if (this.activeBodyAction === 'mow_lawn') {
                this._parkLawnMowerHome();
                this.moveSpeed = 2.15;
                this.waypoints = [];
                this.targetPos.copy(this.pos);
            }
            this.activeBodyAction = 'none';
            this.bodyActionUntilMs = 0;
        }

        const isPointingPhoto = (
            this.mode === 'front_active' &&
            (this.gesture === 'point_photo' || this.gesture === 'point_colleagues' || this.gesture === 'point_picture')
        );
        const arrivedInAtelier = (this.pos.y >= OG_Y - 0.08 && this.pos.z >= 2.85 && (!this.waypoints || this.waypoints.length === 0));
        if (this.mode === 'front_active' && arrivedInAtelier && !this.customNavActive && !this.itemJob) {
            this.targetFloor = 1;
            this.targetPos.set(isPointingPhoto ? -0.18 : 0.0, OG_Y, isPointingPhoto ? 5.58 : 4.48);
        }

        // Autonomous or Commanded Golden SEELENFUNKE Lawn-Mowing Waypoint Loop
        const isMowMode = (this.activeBodyAction === 'mow_lawn') || (this.mode === 'atelier_autonomous' && this.currentTask === 'mow_lawn');
        if (isMowMode && !this._mowerParked && (!this.waypoints || this.waypoints.length === 0)) {
            if (Math.hypot(this.targetPos.x - this.pos.x, this.targetPos.z - this.pos.z) <= 0.085) {
                this._mowerLaneIdx = (this._mowerLaneIdx + 1) % this._mowerWaypoints.length;
                const nxtWp = this._mowerWaypoints[this._mowerLaneIdx];
                this.targetFloor = 0;
                this.targetPos.set(nxtWp.x, 0.0, nxtWp.z);
            }
        }

        // Smooth 2-Floor & Staircase Waypoint Locomotion
        let dx = this.targetPos.x - this.pos.x;
        let dz = this.targetPos.z - this.pos.z;
        let dist = Math.hypot(dx, dz);
        if (dist <= 0.065 && this.waypoints && this.waypoints.length > 0) {
            const nxt = this.waypoints.shift();
            this.targetPos.set(nxt.x, nxt.y, nxt.z);
            this.targetFloor = nxt.floor;
            dx = this.targetPos.x - this.pos.x;
            dz = this.targetPos.z - this.pos.z;
            dist = Math.hypot(dx, dz);
        }
        const isWalking = dist > 0.045;

        if (isWalking) {
            const step = Math.min(dist, this.moveSpeed * dt);
            this.pos.x += (dx / dist) * step;
            this.pos.z += (dz / dist) * step;
            this._enforceCharacterCollision();
            this.walkPhase += dt * (this.moveSpeed > 3.2 ? 18.2 : (this.moveSpeed > 2.2 ? 13.5 : 8.8));

            const moveYaw = Math.atan2(dx, dz);
            let diff = moveYaw - this.facingYaw;
            while (diff > Math.PI) diff -= Math.PI * 2;
            while (diff < -Math.PI) diff += Math.PI * 2;
            this.facingYaw += diff * (1 - Math.exp(-dt * 11));

            if (this.rig.broom) this.rig.broom.visible = false;
            if (this.rig.cloth) this.rig.cloth.visible = false;
            if (this.rig.wateringCan) this.rig.wateringCan.visible = false;
            if (this.rig.gardenTrowel) this.rig.gardenTrowel.visible = false;
        } else {
            this._enforceCharacterCollision();
            if (this.isSprintingHome) {
                this.isSprintingHome = false;
                this.moveSpeed = 2.4;
            }
            // Start autonomous lawn mowing as soon as Funkira reaches the parked Golden SEELENFUNKE Mower!
            if (this.mode === 'atelier_autonomous' && this.currentTask === 'mow_lawn' && this._mowerParked && (!this.waypoints || this.waypoints.length === 0)) {
                this._mowerParked = false;
                this._mowerLaneIdx = 0;
                this.moveSpeed = 1.22;
                const wp0 = this._mowerWaypoints[0];
                this.targetFloor = 0;
                this.targetPos.set(wp0.x, 0.0, wp0.z);
            }
            if (this.pendingBodyAction) {
                const queuedAct = this.pendingBodyAction;
                const queuedDur = this.pendingBodyDurationSec || 0;
                this.pendingBodyAction = null;
                this.pendingBodyDurationSec = 0;
                this.executeMovement({ target: 'stay', bodyAction: queuedAct, durationSeconds: queuedDur });
            }

            // 3D Item Pickup, Carry & Persistent Surface Placement State Machine!
            if (this.itemJob && (!this.waypoints || this.waypoints.length === 0)) {
                if (this.itemJob.phase === 'walking_to_pickup') {
                    const it = this.worldItems[this.itemJob.itemId];
                    if (!it) {
                        this.itemJob = null;
                    } else {
                        this.itemJob.phase = 'reaching_pickup';
                        this.itemJob.phaseTimer = 0.56;
                        this.customFacingYaw = it.faceYaw;
                        this.setExpression('curious', 'carry_reach', 1500);
                    }
                } else if (this.itemJob.phase === 'reaching_pickup') {
                    const it = this.worldItems[this.itemJob.itemId];
                    if (it) this.customFacingYaw = it.faceYaw;
                    this.itemJob.phaseTimer -= dt;
                    if (this.itemJob.phaseTimer <= 0) {
                        this._attachItemToHands(this.itemJob.itemId);
                        if (this.itemJob.destSpotKey && this.dropSpots[this.itemJob.destSpotKey]) {
                            const ds = this.dropSpots[this.itemJob.destSpotKey];
                            this.itemJob.phase = 'walking_to_drop';
                            this.customFacingYaw = null;
                            this._routeTo(ds.approachX, ds.approachZ, ds.floor);
                            this.setExpression('happy', 'carry_hold', 14000);
                        } else {
                            this.itemJob = null;
                            this.customFacingYaw = null;
                            this.setExpression('proud', 'carry_hold', 12000);
                        }
                    }
                } else if (this.itemJob.phase === 'walking_to_drop') {
                    const ds = this.dropSpots[this.itemJob.destSpotKey] || this.dropSpots[this._nearestDropSpotKey()];
                    this.itemJob.phase = 'reaching_place';
                    this.itemJob.phaseTimer = 0.56;
                    this.customFacingYaw = ds ? ds.faceYaw : this.facingYaw;
                    this.setExpression('sweet', 'carry_reach', 1500);
                } else if (this.itemJob.phase === 'reaching_place') {
                    const ds = this.dropSpots[this.itemJob.destSpotKey] || this.dropSpots[this._nearestDropSpotKey()];
                    if (ds) this.customFacingYaw = ds.faceYaw;
                    this.itemJob.phaseTimer -= dt;
                    if (this.itemJob.phaseTimer <= 0) {
                        this._placeItemImmediate(this.itemJob.itemId, this.itemJob.destSpotKey, null, true);
                        this.itemJob = null;
                        this.customFacingYaw = null;
                        this.setExpression('proud', 'hands_on_hips', 5500);
                    }
                }
            }

            // Compute natural camera-facing yaw anywhere in the 2-story house, Terrasse & Garten
            let desiredYaw;
            if (this.mode === 'front_active') {
                if (typeof this.customFacingYaw === 'number') {
                    desiredYaw = this.customFacingYaw;
                } else if (isPointingPhoto) {
                    desiredYaw = Math.PI - 0.28;
                } else {
                    const camToCharYaw = this.camera
                        ? Math.atan2(this.camera.position.x - this.pos.x, this.camera.position.z - this.pos.z)
                        : Math.PI;
                    desiredYaw = camToCharYaw + this.lookCur.x * 0.22;
                }
            } else {
                desiredYaw = this.taskTargetYaw ?? 0;
            }

            if (this.activeBodyAction === 'turn_around') {
                desiredYaw += Math.PI;
            } else if (this.activeBodyAction === 'dance') {
                desiredYaw += Math.sin(tSec * 3.1) * 0.28;
            }

            let diff = desiredYaw - this.facingYaw;
            while (diff > Math.PI) diff -= Math.PI * 2;
            while (diff < -Math.PI) diff += Math.PI * 2;
            this.facingYaw += diff * (1 - Math.exp(-dt * 7.5));

            if (this.mode === 'atelier_autonomous') {
                this.emotion = this.taskEmotion || 'happy';
                const tGest = this.taskGesture || 'idle';
                if (this.rig.broom) this.rig.broom.visible = (tGest === 'sweep' || this.currentTask === 'sweep');
                if (this.rig.cloth) this.rig.cloth.visible = (this.currentTask === 'polish');
                if (this.rig.wateringCan) this.rig.wateringCan.visible = (!this.phoneCallActive && tGest === 'water_plants');
                if (this.rig.gardenTrowel) this.rig.gardenTrowel.visible = (!this.phoneCallActive && tGest === 'garden_work');

                if (this.currentTask === 'polish' && Math.random() < 0.28) {
                    const freeSp = this.sparkParticles.find(s => s.life <= 0);
                    if (freeSp) {
                        freeSp.mesh.visible = true;
                        freeSp.mesh.position.set(-1.68 + (Math.random() - 0.5) * 0.22, OG_Y + 1.06, 5.54 + (Math.random() - 0.5) * 0.14);
                        freeSp.vx = (Math.random() - 0.5) * 0.7;
                        freeSp.vy = 0.45 + Math.random() * 0.55;
                        freeSp.vz = (Math.random() - 0.5) * 0.7;
                        freeSp.life = 0.42 + Math.random() * 0.25;
                    }
                }
            } else {
                const showSweep = !this.phoneCallActive && (this.activeBodyAction === 'sweep_terrace' || this.gesture === 'sweep');
                const showWater = !this.phoneCallActive && (this.activeBodyAction === 'water_plants' || this.gesture === 'water_plants');
                const showTrowel = !this.phoneCallActive && (this.activeBodyAction === 'garden_work' || this.gesture === 'garden_work');
                if (this.rig.broom) this.rig.broom.visible = showSweep;
                if (this.rig.cloth) this.rig.cloth.visible = false;
                if (this.rig.wateringCan) this.rig.wateringCan.visible = showWater;
                if (this.rig.gardenTrowel) this.rig.gardenTrowel.visible = showTrowel;
            }
        }

        // Full-Body 3D Posture Kinematics (Duck/Crouch, Jump, Spin, Bow, Stretch, Lean, Dance, Gardening & Watering)
        let jumpAirY = 0.0;
        let spinYawAdd = 0.0;
        let torsoPitchTarget = 0.0;
        let torsoRollAdd = 0.0;
        let headPitchAdd = 0.0;

        this.currentRoom = this._roomNameAt(this.pos.x, this.pos.y, this.pos.z);
        const isOutdoors = (this.pos.y < 0.5 && this.pos.z > 6.98);
        const isActivelyMowing = isMowMode && !this._mowerParked;
        const isReachingItemNow = !isWalking && Boolean(
            this.itemJob && (this.itemJob.phase === 'reaching_pickup' || this.itemJob.phase === 'reaching_place')
        );
        const isGardeningNow = !isWalking && !this.phoneCallActive && (
            this.activeBodyAction === 'garden_work' ||
            (this.mode === 'atelier_autonomous' && this.taskGesture === 'garden_work')
        );
        const isWateringNow = !isWalking && !this.phoneCallActive && (
            this.activeBodyAction === 'water_plants' ||
            (this.mode === 'atelier_autonomous' && this.taskGesture === 'water_plants')
        );

        const legBlend = 1 - Math.exp(-dt * 12.5);
        if (isWalking) {
            const sL = Math.sin(this.walkPhase) * 0.62;
            const sR = -sL;
            this.rig.legL.hipPivot.rotation.x = lerp(this.rig.legL.hipPivot.rotation.x, sL, legBlend);
            this.rig.legR.hipPivot.rotation.x = lerp(this.rig.legR.hipPivot.rotation.x, sR, legBlend);
            this.rig.legL.kneePivot.rotation.x = lerp(this.rig.legL.kneePivot.rotation.x, Math.max(0, -sL) * 0.85, legBlend);
            this.rig.legR.kneePivot.rotation.x = lerp(this.rig.legR.kneePivot.rotation.x, Math.max(0, -sR) * 0.85, legBlend);
            this.rig.pelvis.position.y = lerp(this.rig.pelvis.position.y, 0.50 + Math.abs(Math.cos(this.walkPhase)) * 0.048, legBlend);
            if (isActivelyMowing) {
                torsoPitchTarget = 0.14;
                headPitchAdd = 0.10;
            }
        } else if (isReachingItemNow) {
            let surfRelY = 0.78;
            if (this.itemJob.phase === 'reaching_pickup') {
                const it = this.worldItems[this.itemJob.itemId];
                if (it) surfRelY = it.y - this.pos.y;
            } else {
                const ds = this.dropSpots[this.itemJob.destSpotKey];
                if (ds) surfRelY = ds.y - this.pos.y;
            }
            const lowReach = surfRelY < 0.58;
            this.rig.pelvis.position.y = lerp(this.rig.pelvis.position.y, lowReach ? 0.365 : 0.475, legBlend);
            this.rig.legL.hipPivot.rotation.x = lerp(this.rig.legL.hipPivot.rotation.x, lowReach ? -0.82 : -0.18, legBlend);
            this.rig.legR.hipPivot.rotation.x = lerp(this.rig.legR.hipPivot.rotation.x, lowReach ? -0.82 : -0.18, legBlend);
            this.rig.legL.kneePivot.rotation.x = lerp(this.rig.legL.kneePivot.rotation.x, lowReach ? 1.42 : 0.32, legBlend);
            this.rig.legR.kneePivot.rotation.x = lerp(this.rig.legR.kneePivot.rotation.x, lowReach ? 1.42 : 0.32, legBlend);
            torsoPitchTarget = lowReach ? 0.38 : 0.28;
            headPitchAdd = 0.14;
        } else if (this.activeBodyAction === 'duck') {
            // Exact grounded 3D crouch: thigh 0.20m (-1.18 rad) + shin/boot 0.268m (+2.02 rad) -> boots stay planted at y = 0.00m!
            const breathe = Math.sin(tSec * 2.6) * 0.007;
            this.rig.pelvis.position.y = lerp(this.rig.pelvis.position.y, 0.31 + breathe, legBlend);
            this.rig.legL.hipPivot.rotation.x = lerp(this.rig.legL.hipPivot.rotation.x, -1.18, legBlend);
            this.rig.legR.hipPivot.rotation.x = lerp(this.rig.legR.hipPivot.rotation.x, -1.18, legBlend);
            this.rig.legL.kneePivot.rotation.x = lerp(this.rig.legL.kneePivot.rotation.x, 2.02, legBlend);
            this.rig.legR.kneePivot.rotation.x = lerp(this.rig.legR.kneePivot.rotation.x, 2.02, legBlend);
            torsoPitchTarget = 0.38;
            headPitchAdd = -0.34;
        } else if (isGardeningNow) {
            const atHochbeet = (this.pos.x > 1.55 && this.pos.z > 7.60 && this.pos.z < 9.55);
            if (atHochbeet) {
                this.rig.pelvis.position.y = lerp(this.rig.pelvis.position.y, 0.475, legBlend);
                this.rig.legL.hipPivot.rotation.x = lerp(this.rig.legL.hipPivot.rotation.x, -0.18, legBlend);
                this.rig.legR.hipPivot.rotation.x = lerp(this.rig.legR.hipPivot.rotation.x, -0.18, legBlend);
                this.rig.legL.kneePivot.rotation.x = lerp(this.rig.legL.kneePivot.rotation.x, 0.32, legBlend);
                this.rig.legR.kneePivot.rotation.x = lerp(this.rig.legR.kneePivot.rotation.x, 0.32, legBlend);
                torsoPitchTarget = 0.28;
                headPitchAdd = 0.16;
            } else {
                const bob = Math.sin(tSec * 4.4) * 0.012;
                this.rig.pelvis.position.y = lerp(this.rig.pelvis.position.y, 0.325 + bob, legBlend);
                this.rig.legL.hipPivot.rotation.x = lerp(this.rig.legL.hipPivot.rotation.x, -1.12, legBlend);
                this.rig.legR.hipPivot.rotation.x = lerp(this.rig.legR.hipPivot.rotation.x, -1.12, legBlend);
                this.rig.legL.kneePivot.rotation.x = lerp(this.rig.legL.kneePivot.rotation.x, 1.94, legBlend);
                this.rig.legR.kneePivot.rotation.x = lerp(this.rig.legR.kneePivot.rotation.x, 1.94, legBlend);
                torsoPitchTarget = 0.44;
                headPitchAdd = 0.14;
            }
            if (Math.random() < 0.24) {
                const freeSp = this.sparkParticles.find(s => s.life <= 0);
                if (freeSp) {
                    const fwdX = Math.sin(this.facingYaw) * 0.42;
                    const fwdZ = Math.cos(this.facingYaw) * 0.42;
                    freeSp.mesh.visible = true;
                    freeSp.mesh.position.set(
                        this.pos.x + fwdX + (Math.random() - 0.5) * 0.18,
                        this.pos.y + (atHochbeet ? 0.82 : 0.22),
                        this.pos.z + fwdZ + (Math.random() - 0.5) * 0.18
                    );
                    freeSp.vx = (Math.random() - 0.5) * 0.55;
                    freeSp.vy = 0.35 + Math.random() * 0.45;
                    freeSp.vz = (Math.random() - 0.5) * 0.55;
                    freeSp.life = 0.38 + Math.random() * 0.22;
                }
            }
        } else if (isWateringNow) {
            this.rig.pelvis.position.y = lerp(this.rig.pelvis.position.y, 0.485, legBlend);
            this.rig.legL.hipPivot.rotation.x = lerp(this.rig.legL.hipPivot.rotation.x, -0.10, legBlend);
            this.rig.legR.hipPivot.rotation.x = lerp(this.rig.legR.hipPivot.rotation.x, -0.10, legBlend);
            this.rig.legL.kneePivot.rotation.x = lerp(this.rig.legL.kneePivot.rotation.x, 0.18, legBlend);
            this.rig.legR.kneePivot.rotation.x = lerp(this.rig.legR.kneePivot.rotation.x, 0.18, legBlend);
            torsoPitchTarget = 0.18;
            headPitchAdd = 0.14;
        } else if (this.activeBodyAction === 'jump') {
            const el = Math.max(0, (nowMs - this.bodyActionStartMs) * 0.001);
            if (el < 0.24) {
                const u = el / 0.24;
                this.rig.pelvis.position.y = lerp(0.50, 0.36, u);
                this.rig.legL.hipPivot.rotation.x = -0.88 * u;
                this.rig.legR.hipPivot.rotation.x = -0.88 * u;
                this.rig.legL.kneePivot.rotation.x = 1.48 * u;
                this.rig.legR.kneePivot.rotation.x = 1.48 * u;
                torsoPitchTarget = 0.22 * u;
            } else if (el < 1.14) {
                const p = (el - 0.24) / 0.90;
                const arc = 4 * p * (1 - p);
                jumpAirY = arc * 0.46;
                this.rig.pelvis.position.y = 0.50;
                this.rig.legL.hipPivot.rotation.x = -0.36 * arc;
                this.rig.legR.hipPivot.rotation.x = -0.36 * arc;
                this.rig.legL.kneePivot.rotation.x = 0.74 * arc;
                this.rig.legR.kneePivot.rotation.x = 0.74 * arc;
                torsoPitchTarget = -0.10 * arc;
            } else {
                const w = clamp((el - 1.14) / 0.41, 0, 1);
                const absorb = Math.sin(w * Math.PI);
                this.rig.pelvis.position.y = 0.50 - 0.11 * absorb;
                this.rig.legL.hipPivot.rotation.x = -0.68 * absorb;
                this.rig.legR.hipPivot.rotation.x = -0.68 * absorb;
                this.rig.legL.kneePivot.rotation.x = 1.16 * absorb;
                this.rig.legR.kneePivot.rotation.x = 1.16 * absorb;
                torsoPitchTarget = 0.16 * absorb;
            }
        } else if (this.activeBodyAction === 'spin') {
            const p = clamp((nowMs - this.bodyActionStartMs) / 1350, 0, 1);
            const ease = p < 0.5 ? 4 * p * p * p : 1 - Math.pow(-2 * p + 2, 3) / 2;
            spinYawAdd = ease * Math.PI * 2;
            jumpAirY = Math.sin(p * Math.PI) * 0.08;
            this.rig.pelvis.position.y = lerp(this.rig.pelvis.position.y, 0.50, legBlend);
            this.rig.legL.hipPivot.rotation.x = lerp(this.rig.legL.hipPivot.rotation.x, -0.12 * Math.sin(p * Math.PI), legBlend);
            this.rig.legR.hipPivot.rotation.x = lerp(this.rig.legR.hipPivot.rotation.x, 0.18 * Math.sin(p * Math.PI), legBlend);
            this.rig.legL.kneePivot.rotation.x = lerp(this.rig.legL.kneePivot.rotation.x, 0.25 * Math.sin(p * Math.PI), legBlend);
            this.rig.legR.kneePivot.rotation.x = lerp(this.rig.legR.kneePivot.rotation.x, 0.35 * Math.sin(p * Math.PI), legBlend);
        } else if (this.activeBodyAction === 'bow') {
            this.rig.pelvis.position.y = lerp(this.rig.pelvis.position.y, 0.48, legBlend);
            this.rig.legL.hipPivot.rotation.x = lerp(this.rig.legL.hipPivot.rotation.x, -0.10, legBlend);
            this.rig.legR.hipPivot.rotation.x = lerp(this.rig.legR.hipPivot.rotation.x, -0.10, legBlend);
            this.rig.legL.kneePivot.rotation.x = lerp(this.rig.legL.kneePivot.rotation.x, 0.16, legBlend);
            this.rig.legR.kneePivot.rotation.x = lerp(this.rig.legR.kneePivot.rotation.x, 0.16, legBlend);
            torsoPitchTarget = 0.56;
            headPitchAdd = 0.16;
        } else if (this.activeBodyAction === 'stretch') {
            this.rig.pelvis.position.y = lerp(this.rig.pelvis.position.y, 0.535, legBlend);
            this.rig.legL.hipPivot.rotation.x = lerp(this.rig.legL.hipPivot.rotation.x, 0.06, legBlend);
            this.rig.legR.hipPivot.rotation.x = lerp(this.rig.legR.hipPivot.rotation.x, 0.06, legBlend);
            this.rig.legL.kneePivot.rotation.x = lerp(this.rig.legL.kneePivot.rotation.x, 0, legBlend);
            this.rig.legR.kneePivot.rotation.x = lerp(this.rig.legR.kneePivot.rotation.x, 0, legBlend);
            torsoPitchTarget = -0.15;
            headPitchAdd = -0.12;
        } else if (this.activeBodyAction === 'lean_closer') {
            this.rig.pelvis.position.y = lerp(this.rig.pelvis.position.y, 0.46, legBlend);
            this.rig.legL.hipPivot.rotation.x = lerp(this.rig.legL.hipPivot.rotation.x, -0.24, legBlend);
            this.rig.legR.hipPivot.rotation.x = lerp(this.rig.legR.hipPivot.rotation.x, -0.24, legBlend);
            this.rig.legL.kneePivot.rotation.x = lerp(this.rig.legL.kneePivot.rotation.x, 0.42, legBlend);
            this.rig.legR.kneePivot.rotation.x = lerp(this.rig.legR.kneePivot.rotation.x, 0.42, legBlend);
            torsoPitchTarget = 0.34;
            headPitchAdd = -0.26;
        } else if (this.activeBodyAction === 'dance') {
            const beat = tSec * 6.2;
            const sw = Math.sin(beat);
            this.rig.pelvis.position.y = lerp(this.rig.pelvis.position.y, 0.465 + Math.abs(Math.cos(beat)) * 0.055, legBlend);
            this.rig.legL.hipPivot.rotation.x = lerp(this.rig.legL.hipPivot.rotation.x, -0.24 + sw * 0.22, legBlend);
            this.rig.legR.hipPivot.rotation.x = lerp(this.rig.legR.hipPivot.rotation.x, -0.24 - sw * 0.22, legBlend);
            this.rig.legL.kneePivot.rotation.x = lerp(this.rig.legL.kneePivot.rotation.x, 0.42 - sw * 0.24, legBlend);
            this.rig.legR.kneePivot.rotation.x = lerp(this.rig.legR.kneePivot.rotation.x, 0.42 + sw * 0.24, legBlend);
            torsoRollAdd = Math.sin(beat * 0.5) * 0.14;
        } else {
            const emoAgeSec = Math.max(0, (nowMs - (this.emotionStartedMs || 0)) / 1000);
            const burstFade = (this.emotion === 'excited' || this.emotion === 'laughing')
                ? Math.max(0, 1 - (emoAgeSec / 2.8))
                : 0;
            const idleBounce = Math.sin(tSec * 2.2) * 0.012 + (burstFade > 0 ? Math.abs(Math.sin(tSec * 7.5)) * 0.032 * burstFade : 0);
            this.rig.pelvis.position.y = lerp(this.rig.pelvis.position.y, 0.50 + idleBounce, legBlend);
            this.rig.legL.hipPivot.rotation.x = lerp(this.rig.legL.hipPivot.rotation.x, 0, legBlend);
            this.rig.legR.hipPivot.rotation.x = lerp(this.rig.legR.hipPivot.rotation.x, 0, legBlend);
            this.rig.legL.kneePivot.rotation.x = lerp(this.rig.legL.kneePivot.rotation.x, 0, legBlend);
            this.rig.legR.kneePivot.rotation.x = lerp(this.rig.legR.kneePivot.rotation.x, 0, legBlend);
        }

        this.rig.root.position.set(this.pos.x, this.pos.y + jumpAirY, this.pos.z);
        this.rig.root.rotation.y = this.facingYaw + spinYawAdd;

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
        const headBlend = 1 - Math.exp(-dt * 8.5);
        this.rig.torso.rotation.x = lerp(this.rig.torso.rotation.x, torsoPitchTarget, headBlend);
        this.rig.torso.rotation.y = lerp(this.rig.torso.rotation.y, this.lookCur.x * 0.16, headBlend);
        this.rig.torso.rotation.z = lerp(this.rig.torso.rotation.z, Math.sin(tSec * 1.6) * 0.02 + torsoRollAdd, headBlend);

        const speakNod = this.isSpeaking ? Math.sin(tSec * 9.5) * 0.035 * (0.3 + this.voiceEnergy) : 0;
        const headYawTarget = isPointingPhoto ? 0.18 : (this.lookCur.x * 0.34);
        this.rig.head.rotation.y = lerp(this.rig.head.rotation.y, headYawTarget, headBlend);
        this.rig.head.rotation.x = lerp(this.rig.head.rotation.x, this.lookCur.y * 0.22 + (preset.headPitch || 0) + speakNod + headPitchAdd, headBlend);
        this.rig.head.rotation.z = lerp(this.rig.head.rotation.z, (preset.headRoll || 0) - this.lookCur.x * 0.07, headBlend);

        if (this.sirenMat) {
            const boost = this.isSpeaking ? (this.voiceEnergy * 0.25) : (this.emotion === 'excited' ? 0.16 : 0);
            this.sirenMat.color.setRGB(Math.min(1, 0.91 + boost), Math.min(1, 0.51 + boost * 0.5), Math.min(1, 0.61 + boost * 0.5));
        }

        let activeGest = (this.mode === 'atelier_autonomous' && !isWalking) ? (this.taskGesture || 'idle') : this.gesture;
        if (isActivelyMowing && !this.phoneCallActive) {
            activeGest = 'push_mower';
        } else if (isReachingItemNow && !this.phoneCallActive) {
            activeGest = 'carry_reach';
        } else if (this.carriedItemId && !this.phoneCallActive) {
            activeGest = 'carry_hold';
        } else if (!isWalking && !this.phoneCallActive) {
            if (this.activeBodyAction === 'duck') activeGest = 'duck';
            else if (this.activeBodyAction === 'bow') activeGest = 'bow';
            else if (this.activeBodyAction === 'stretch') activeGest = 'stretch';
            else if (this.activeBodyAction === 'spin') activeGest = 'spin_arms';
            else if (this.activeBodyAction === 'dance') activeGest = 'dance';
            else if (this.activeBodyAction === 'water_plants') activeGest = 'water_plants';
            else if (this.activeBodyAction === 'garden_work') activeGest = 'garden_work';
            else if (this.activeBodyAction === 'sweep_terrace') activeGest = 'sweep';
        }

        // Live Golden SEELENFUNKE 3D Lawn Mower Motion, Spinning Wheels & Grass Clippings!
        if (isActivelyMowing && this.lawnMowerGroup) {
            const fwdX = Math.sin(this.facingYaw);
            const fwdZ = Math.cos(this.facingYaw);
            this.lawnMowerGroup.position.set(this.pos.x + fwdX * 0.68, 0.0, this.pos.z + fwdZ * 0.68);
            this.lawnMowerGroup.rotation.set(0, this.facingYaw, 0);
            if (this.lawnMowerDeck) {
                this.lawnMowerDeck.position.y = Math.sin(tSec * 42.0) * 0.0045;
            }
            for (let w = 0; w < this.lawnMowerWheels.length; w++) {
                this.lawnMowerWheels[w].rotation.x += dt * 7.8;
            }
            if (isWalking && Math.random() < 0.58) {
                const freeGr = this.grassParticles.find(g => g.life <= 0);
                if (freeGr) {
                    freeGr.mesh.visible = true;
                    freeGr.mesh.position.set(
                        this.pos.x + fwdX * 0.52 + (Math.random() - 0.5) * 0.24,
                        0.09,
                        this.pos.z + fwdZ * 0.52 + (Math.random() - 0.5) * 0.24
                    );
                    freeGr.vx = -fwdX * (0.45 + Math.random() * 0.55) + (Math.random() - 0.5) * 0.75;
                    freeGr.vy = 0.55 + Math.random() * 0.65;
                    freeGr.vz = -fwdZ * (0.45 + Math.random() * 0.55) + (Math.random() - 0.5) * 0.75;
                    freeGr.life = 0.42 + Math.random() * 0.22;
                }
            }
        }
        const armPose = this._solveArmTargets(activeGest, tSec, isWalking, this.walkPhase);
        const armBlend = 1 - Math.exp(-dt * 8.8);
        this._applyArmSmoothing(this.rig.armR, armPose.rS, armPose.rE, armPose.rW, armPose.rCurl, armPose.pointHand === 'R', armBlend);
        this._applyArmSmoothing(this.rig.armL, armPose.lS, armPose.lE, armPose.lW, armPose.lCurl, armPose.pointHand === 'L', armBlend);

        // Smooth Indoor/Outdoor Sky Background Blend
        const bgTarget = (isOutdoors || this.worldState.occasion === 'silvester' || this.worldState.occasion === 'new_year')
            ? this.outdoorSkyColor
            : this.indoorBgColor;
        this.scene.background.lerp(bgTarget, 1 - Math.exp(-dt * 2.6));

        // ====================================================================
        // SMART 2-FLOOR DOLLHOUSE, TERRACE/GARDEN & LANDMARK CAMERA GLIDING
        // ====================================================================
        const useDollhouseView = (this.mode === 'atelier_autonomous') || (this.pos.y < OG_Y - 0.15) || (this.pos.z < 2.35);

        let camTargetX, camTargetY, camTargetZ, lookX, lookY, lookZ;
        if (!useDollhouseView) {
            if (this.cameraFocusMode === 'closeup_photo' || isPointingPhoto) {
                // Crisp, unobstructed close-up framing of the 12-Colleagues Portrait + Funkira beside it!
                camTargetX = 0.26;
                camTargetY = OG_Y + 1.54;
                camTargetZ = 3.45;
                lookX = 0.34;
                lookY = OG_Y + 1.60;
                lookZ = 6.76;
            } else if (this.cameraFocusMode === 'closeup_sign') {
                camTargetX = -0.56;
                camTargetY = OG_Y + 1.52;
                camTargetZ = 3.45;
                lookX = -0.82;
                lookY = OG_Y + 1.58;
                lookZ = 6.77;
            } else if (this.cameraFocusMode === 'closeup_workbench') {
                camTargetX = -0.98;
                camTargetY = OG_Y + 1.42;
                camTargetZ = 3.15;
                lookX = -1.62;
                lookY = OG_Y + 1.06;
                lookZ = 5.68;
            } else if (this.cameraFocusMode === 'closeup_laser') {
                camTargetX = 0.98;
                camTargetY = OG_Y + 1.42;
                camTargetZ = 3.15;
                lookX = 1.78;
                lookY = OG_Y + 1.06;
                lookZ = 5.68;
            } else {
                camTargetX = clamp(this.pos.x * 0.18 - this.lookCur.x * 0.06, -0.45, 0.45);
                camTargetY = OG_Y + 1.38;
                camTargetZ = 2.26;
                lookX = clamp(this.pos.x * 0.42, -0.85, 0.85);
                lookY = OG_Y + (this.activeBodyAction === 'duck' ? 0.84 : 1.02);
                lookZ = 5.45;
            }
        } else if (isOutdoors && this.mode === 'front_active') {
            // Scenic Outdoor Terrasse & Garten Framing (shows Funkira, Hochbeet/Blumenbeet & Sky/Fireworks!)
            camTargetX = clamp(this.pos.x * 0.65, -3.10, 3.10);
            camTargetY = 2.75;
            camTargetZ = clamp(this.pos.z + 4.55, 12.10, 16.40);
            lookX = clamp(this.pos.x * 0.85, -3.40, 3.40);
            lookY = (isGardeningNow && this.pos.x <= 1.55) ? 0.86 : 1.16;
            lookZ = clamp(this.pos.z - 1.10, 6.80, 10.60);
        } else {
            // Sims-Style Architectural 3D Floorplan + Garden Overview Camera
            const baseFloorY = this.pos.y;
            camTargetX = clamp(this.pos.x * 0.58, -3.10, 3.10);
            camTargetY = baseFloorY + (isOutdoors ? 4.85 : 6.85);
            camTargetZ = clamp(this.pos.z + (isOutdoors ? 5.95 : 6.95), 2.20, 18.40);
            lookX = clamp(this.pos.x * 0.78, -3.40, 3.40);
            lookY = baseFloorY + (isOutdoors ? 0.98 : 0.78);
            lookZ = clamp(this.pos.z - 0.65, -5.80, 11.40);
        }

        const camBlend = 1 - Math.exp(-dt * (this.isSprintingHome ? 3.8 : 2.5));
        this.camera.position.x = lerp(this.camera.position.x, camTargetX, camBlend);
        this.camera.position.y = lerp(this.camera.position.y, camTargetY, camBlend);
        this.camera.position.z = lerp(this.camera.position.z, camTargetZ, camBlend);
        this.cameraLook.x = lerp(this.cameraLook.x, lookX, camBlend);
        this.cameraLook.y = lerp(this.cameraLook.y, lookY, camBlend);
        this.cameraLook.z = lerp(this.cameraLook.z, lookZ, camBlend);
        this.camera.lookAt(this.cameraLook);

        // Dynamic Floor & Roof Cutaway Visibility
        const showOg = !(useDollhouseView && this.pos.y < 1.95);
        const showClosedAtelierGable = showOg && !useDollhouseView && (this.camera.position.z < 6.75);
        if (this.ogGroup && this.ogGroup.visible !== showOg) {
            this.ogGroup.visible = showOg;
        }
        if (this.ogRoofClosedGroup && this.ogRoofClosedGroup.visible !== showClosedAtelierGable) {
            this.ogRoofClosedGroup.visible = showClosedAtelierGable;
        }
        if (this.ogWeatherGroup) {
            this.ogWeatherGroup.visible = showOg;
        }
        if (this.ogSouthWeatherGroup) {
            this.ogSouthWeatherGroup.visible = showClosedAtelierGable;
        }

        // Ambient Animations (Vitrine Gem, Window & Outdoor Weather, Watering Stream, Fireworks, Sparks & Petals)
        if (this.rig.vitrineGem && (this.worldState.shopLevel || 1) >= 3) {
            this.rig.vitrineGem.rotation.y = tSec * 0.85;
        }
        for (let i = 0; i < this.weatherParticles.length; i++) {
            const wp = this.weatherParticles[i];
            wp.y -= wp.speedY * dt;
            if (wp.y < wp.minY) {
                wp.uBase = (Math.random() - 0.5) * (wp.halfW * 1.9);
                wp.y = wp.maxY;
            }
            const u = clamp(wp.uBase + Math.sin(tSec * 1.8 + wp.phase) * 0.055, -wp.halfW, wp.halfW);
            const dyFromCenter = wp.y - wp.cy;
            const slopeShiftX = wp.slopeDxPerY ? (dyFromCenter * wp.slopeDxPerY) : 0;
            wp.mesh.position.set(
                wp.cx + wp.tanX * u + slopeShiftX,
                wp.y,
                wp.cz + wp.tanZ * u
            );
        }

        // Outdoor 3D Garden Weather Particles (Snow / Blossoms / Sun Motes / Autumn Leaves)
        const seaNow = this.worldState.season || 'autumn';
        const owSpeedMul = seaNow === 'summer' ? 0.35 : 1.0;
        for (let i = 0; i < this.outdoorWeatherParticles.length; i++) {
            const ow = this.outdoorWeatherParticles[i];
            ow.y -= ow.speedY * owSpeedMul * dt;
            if (ow.y < 0.06) {
                ow.baseX = (Math.random() - 0.5) * 9.6;
                ow.baseZ = 7.15 + Math.random() * 5.8;
                ow.y = 3.45;
            }
            const driftX = Math.sin(tSec * 1.3 + ow.phase) * 0.22;
            const driftZ = Math.cos(tSec * 1.1 + ow.phase) * 0.14;
            ow.mesh.position.set(ow.baseX + driftX, ow.y, ow.baseZ + driftZ);
            ow.mesh.rotation.y += dt * 1.4;
        }

        // Gießkanne Water Droplets Stream
        if (isWateringNow && this.rig.wateringCan && this.rig.wateringCan.visible && this.rig.wateringSpoutTip) {
            this.rig.wateringSpoutTip.getWorldPosition(this._tmpSpoutWorld);
            if (Math.random() < 0.72) {
                const freeDrop = this.waterDropParticles.find(d => d.life <= 0);
                if (freeDrop) {
                    const fwdX = Math.sin(this.facingYaw);
                    const fwdZ = Math.cos(this.facingYaw);
                    freeDrop.mesh.visible = true;
                    freeDrop.mesh.position.copy(this._tmpSpoutWorld);
                    freeDrop.vx = fwdX * (0.75 + Math.random() * 0.35) + (Math.random() - 0.5) * 0.18;
                    freeDrop.vy = -0.25 - Math.random() * 0.25;
                    freeDrop.vz = fwdZ * (0.75 + Math.random() * 0.35) + (Math.random() - 0.5) * 0.18;
                    freeDrop.life = 0.52;
                }
            }
        }
        for (let i = 0; i < this.waterDropParticles.length; i++) {
            const wd = this.waterDropParticles[i];
            if (wd.life > 0) {
                wd.life -= dt;
                wd.mesh.position.x += wd.vx * dt;
                wd.mesh.position.y += wd.vy * dt;
                wd.mesh.position.z += wd.vz * dt;
                wd.vy -= 3.8 * dt;
                if (wd.life <= 0 || wd.mesh.position.y <= 0.04) {
                    wd.life = 0;
                    wd.mesh.visible = false;
                }
            }
        }

        // Live 60-FPS 3D Silvester & New Year Fireworks above Garden & House!
        if (this.worldState.occasion === 'silvester' || this.worldState.occasion === 'new_year') {
            for (let r = 0; r < this.fireworkBursts.length; r++) {
                const fb = this.fireworkBursts[r];
                if (fb.state === 'cooldown') {
                    fb.timer -= dt;
                    if (fb.timer <= 0) {
                        fb.state = 'launch';
                        fb.x = (Math.random() - 0.5) * 7.8;
                        fb.y = 0.35;
                        fb.z = 6.2 + Math.random() * 4.6;
                        fb.vy = 3.6 + Math.random() * 1.1;
                        fb.burstY = 2.65 + Math.random() * 1.75;
                        fb.rocketMesh.visible = true;
                        fb.rocketMesh.position.set(fb.x, fb.y, fb.z);
                    }
                } else if (fb.state === 'launch') {
                    fb.y += fb.vy * dt;
                    fb.rocketMesh.position.set(fb.x, fb.y, fb.z);
                    if (fb.y >= fb.burstY) {
                        fb.rocketMesh.visible = false;
                        fb.state = 'burst';
                        fb.timer = 1.25;
                        for (let s = 0; s < fb.sparks.length; s++) {
                            const spk = fb.sparks[s];
                            const phi = Math.acos(1 - (2 * (s + 0.5)) / fb.sparks.length);
                            const theta = Math.PI * (1 + Math.sqrt(5)) * s + r;
                            const spd = 1.45 + (s % 3) * 0.38;
                            spk.vx = Math.sin(phi) * Math.cos(theta) * spd;
                            spk.vy = Math.cos(phi) * spd + 0.35;
                            spk.vz = Math.sin(phi) * Math.sin(theta) * spd;
                            spk.mesh.visible = true;
                            spk.mesh.scale.setScalar(1.0);
                            spk.mesh.position.set(fb.x, fb.y, fb.z);
                        }
                    }
                } else if (fb.state === 'burst') {
                    fb.timer -= dt;
                    const lifeScale = clamp(fb.timer / 1.25, 0.05, 1.0);
                    for (let s = 0; s < fb.sparks.length; s++) {
                        const spk = fb.sparks[s];
                        spk.mesh.position.x += spk.vx * dt;
                        spk.mesh.position.y += spk.vy * dt;
                        spk.mesh.position.z += spk.vz * dt;
                        spk.vy -= 1.55 * dt;
                        spk.vx *= (1 - dt * 0.9);
                        spk.vz *= (1 - dt * 0.9);
                        spk.mesh.scale.setScalar(lifeScale);
                    }
                    if (fb.timer <= 0) {
                        for (let s = 0; s < fb.sparks.length; s++) {
                            fb.sparks[s].mesh.visible = false;
                        }
                        fb.state = 'cooldown';
                        fb.timer = 0.25 + Math.random() * 1.15;
                    }
                }
            }
        }

        for (let i = 0; i < this.grassParticles.length; i++) {
            const gp = this.grassParticles[i];
            if (gp.life > 0) {
                gp.life -= dt;
                gp.mesh.position.x += gp.vx * dt;
                gp.mesh.position.y += gp.vy * dt;
                gp.mesh.position.z += gp.vz * dt;
                gp.mesh.rotation.x += dt * 7.5;
                gp.mesh.rotation.z += dt * 6.2;
                gp.vy -= 3.2 * dt;
                if (gp.life <= 0 || gp.mesh.position.y <= 0.02) {
                    gp.life = 0;
                    gp.mesh.visible = false;
                }
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
            if (p.life <= 0 || p.mesh.position.y < (p.minY ?? 0.03)) {
                this.scene.remove(p.mesh);
                this.fallingPetals.splice(i, 1);
            }
        }

        this._renderVisorFace(nowMs, dt, false);
        this.renderer.render(this.scene, this.camera);
    }
}

window.Funkira3D = new FunkiraAtelierEngine();
