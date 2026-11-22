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

const UG_Y = -3.20;
const OG_Y = 3.10;
const HOUSE_W = 8.50;
const HOUSE_L = 14.00;

function lerp(a, b, t) { return a + (b - a) * t; }
function clamp(v, lo, hi) { return Math.max(lo, Math.min(hi, v)); }

function hexToRgb(hex) {
    const n = typeof hex === 'number' ? hex : parseInt(String(hex).replace('#', ''), 16);
    return [(n >> 16) & 255, (n >> 8) & 255, n & 255];
}

let MAX_GPU_ANISOTROPY = 4;

function configureFastTexture(tex) {
    tex.generateMipmaps = false;
    tex.minFilter = THREE.LinearFilter;
    tex.magFilter = THREE.LinearFilter;
    tex.colorSpace = THREE.SRGBColorSpace;
    return tex;
}

// High-Definition Pre-Baked Texture Helper (Trilinear Mipmaps + 4x Anisotropy = Razor-Sharp Angles at 0 CPU Cost!)
function configureHdTexture(tex, anisotropy = 4) {
    tex.generateMipmaps = true;
    tex.minFilter = THREE.LinearMipmapLinearFilter;
    tex.magFilter = THREE.LinearFilter;
    tex.anisotropy = Math.min(MAX_GPU_ANISOTROPY, anisotropy);
    tex.colorSpace = THREE.SRGBColorSpace;
    return tex;
}

// Procedural 384x384 Multi-Zone Luxury Studio MatCap Generator (Zero per-pixel light math on GPU!)
function makeStudioMatcapTexture(baseHex, specHex, rimHex, specPower = 26, rimStrength = 0.38) {
    const size = 384;
    const c = document.createElement('canvas');
    c.width = size;
    c.height = size;
    const ctx = c.getContext('2d');
    const img = ctx.createImageData(size, size);
    const d = img.data;

    const base = hexToRgb(baseHex);
    const spec = hexToRgb(specHex);
    const rim = hexToRgb(rimHex);

    // Key studio softbox + secondary champagne fill + subtle warm floor bounce
    const lx1 = -0.34, ly1 = 0.62, lz1 = 0.706;
    const hx1 = -0.19, hy1 = 0.38, hz1 = 0.905;
    const lx2 = 0.50,  ly2 = 0.34, lz2 = 0.796;
    const hx2 = 0.31,  hy2 = 0.22, hz2 = 0.925;

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
                const skyBounce = 0.34 + 0.30 * (ny * 0.5 + 0.5);
                const horizonBand = Math.exp(-Math.pow((ny + 0.14) * 5.2, 2)) * (specPower >= 20 ? 0.11 : 0.03);
                const hemi = skyBounce + 0.47 * ndl1 + 0.21 * ndl2 + horizonBand;

                const ndh1 = Math.max(0, nx * hx1 + ny * hy1 + nz * hz1);
                const ndh2 = Math.max(0, nx * hx2 + ny * hy2 + nz * hz2);
                const spBroad = Math.pow(ndh1, Math.max(6, specPower * 0.45)) * 0.26;
                const spCrisp = Math.pow(ndh1, specPower * 1.35) * 0.78 + Math.pow(ndh2, specPower * 0.9) * 0.18;
                const sp = spBroad + spCrisp;
                const fresnel = Math.pow(1.0 - nz, 2.65) * rimStrength;

                r = base[0] * hemi + spec[0] * sp + rim[0] * fresnel;
                g = base[1] * hemi + spec[1] * sp + rim[1] * fresnel;
                b = base[2] * hemi + spec[2] * sp + rim[2] * fresnel;
            } else {
                const len = Math.sqrt(r2);
                const uy = ny / len;
                const hemi = 0.35 + 0.25 * (uy * 0.5 + 0.5);
                r = base[0] * hemi + rim[0] * rimStrength * 0.92;
                g = base[1] * hemi + rim[1] * rimStrength * 0.92;
                b = base[2] * hemi + rim[2] * rimStrength * 0.92;
            }
            const p = (y * size + x) * 4;
            d[p]     = clamp(Math.round(r), 0, 255);
            d[p + 1] = clamp(Math.round(g), 0, 255);
            d[p + 2] = clamp(Math.round(b), 0, 255);
            d[p + 3] = 255;
        }
    }
    ctx.putImageData(img, 0, 0);
    return configureHdTexture(new THREE.CanvasTexture(c), 2);
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

// Pentagonal Gable Wall Shape matching raised Obergeschoss Dachschrägen (width 8.50m, eaves +1.30m, Kniestock ±3.00m @ +2.12m, peak 0m @ +4.10m)
function createGableWallShape() {
    const s = new THREE.Shape();
    s.moveTo(-4.24, 0.0);
    s.lineTo( 4.24, 0.0);
    s.lineTo( 4.24, 1.30);
    s.lineTo( 3.00, 2.12);
    s.lineTo( 0.00, 4.10);
    s.lineTo(-3.00, 2.12);
    s.lineTo(-4.24, 1.30);
    s.closePath();
    return s;
}

// Shared helper to paint fine organic wood-grain fibers & plank micro-bevels onto HD floor canvases
function paintWoodPlankDetail(ctx, px, py, pw, ph, grainRgbDark, grainRgbLight, seed) {
    ctx.strokeStyle = grainRgbDark;
    ctx.lineWidth = 1.0;
    const lines = 4;
    for (let g = 0; g < lines; g++) {
        const gy = py + ph * (0.18 + g * 0.21) + ((seed + g * 7) % 3) - 1;
        const wave = (((seed * 5 + g * 11) % 7) - 3) * 0.8;
        ctx.beginPath();
        ctx.moveTo(px + 3, gy);
        ctx.bezierCurveTo(px + pw * 0.35, gy + wave, px + pw * 0.68, gy - wave, px + pw - 3, gy);
        ctx.stroke();
    }
    if (seed % 7 === 0 && pw > 60) {
        const kx = px + pw * (0.28 + ((seed % 5) * 0.11));
        const ky = py + ph * 0.52;
        ctx.strokeStyle = grainRgbDark;
        ctx.beginPath();
        ctx.ellipse(kx, ky, 5.5, 2.4, 0.08, 0, Math.PI * 2);
        ctx.stroke();
    }
    ctx.fillStyle = grainRgbLight;
    ctx.fillRect(px + 2, py + 1.4, Math.max(0, pw - 4), 1.4);
}

// ============================================================================
// PHOTOREALISTIC ATELIER PBR ARCHITECTURE HELPERS (60-FPS INTEL UHD 605 EDITION)
// Real 2K/1K Architectural Assets (/assets/atelier_*.jpg) + Instant Zero-Flicker
// Fallbacks + UV1 Baked Ambient Occlusion + Zero Dynamic Lights (PMREM IBL Only)
// ============================================================================
const ATELIER_ASSET_VER = '20261116v25';
const PARQUET_TILE_M = 1.70;
const BOISERIE_TILE_M = 3.00;

function makeFallbackEquirectEnvTexture() {
    const c = document.createElement('canvas');
    c.width = 128;
    c.height = 64;
    const ctx = c.getContext('2d');
    const g = ctx.createLinearGradient(0, 0, 0, 64);
    g.addColorStop(0.00, '#fffaf0');
    g.addColorStop(0.32, '#f6e7c8');
    g.addColorStop(0.54, '#dfc496');
    g.addColorStop(0.76, '#b89668');
    g.addColorStop(1.00, '#7d6242');
    ctx.fillStyle = g;
    ctx.fillRect(0, 0, 128, 64);
    ctx.fillStyle = 'rgba(255, 252, 240, 0.55)';
    for (let x = 8; x < 128; x += 24) {
        ctx.fillRect(x, 4, 14, 18);
    }
    const tex = new THREE.CanvasTexture(c);
    tex.colorSpace = THREE.SRGBColorSpace;
    tex.mapping = THREE.EquirectangularReflectionMapping;
    return tex;
}

function makeFallbackParquetTexture() {
    const S = 128;
    const c = document.createElement('canvas');
    c.width = S;
    c.height = S;
    const ctx = c.getContext('2d');
    ctx.fillStyle = '#c89f6d';
    ctx.fillRect(0, 0, S, S);
    ctx.strokeStyle = 'rgba(116, 82, 46, 0.28)';
    ctx.lineWidth = 1.2;
    for (let i = -S; i < S * 2; i += 16) {
        ctx.beginPath();
        ctx.moveTo(i, 0);
        ctx.lineTo(i + S * 0.5, S * 0.5);
        ctx.lineTo(i, S);
        ctx.stroke();
    }
    const tex = configureHdTexture(new THREE.CanvasTexture(c), MAX_GPU_ANISOTROPY);
    tex.wrapS = THREE.RepeatWrapping;
    tex.wrapT = THREE.RepeatWrapping;
    return tex;
}

function makeFallbackBoiserieTexture() {
    const S = 128;
    const c = document.createElement('canvas');
    c.width = S;
    c.height = S;
    const ctx = c.getContext('2d');
    const vg = ctx.createLinearGradient(0, 0, 0, S);
    vg.addColorStop(0.00, '#d8cebe');
    vg.addColorStop(0.05, '#e5dccf');
    vg.addColorStop(0.95, '#dcd2c2');
    vg.addColorStop(0.955, '#1b1817');
    vg.addColorStop(1.00, '#121010');
    ctx.fillStyle = vg;
    ctx.fillRect(0, 0, S, S);
    ctx.strokeStyle = 'rgba(148, 134, 114, 0.42)';
    ctx.lineWidth = 1.5;
    for (let i = 0; i < 3; i++) {
        const px = i * (S / 3);
        ctx.strokeRect(px + 6, 20, S / 3 - 12, 72);
        ctx.strokeRect(px + 6, 102, S / 3 - 12, 16);
    }
    const tex = configureHdTexture(new THREE.CanvasTexture(c), MAX_GPU_ANISOTROPY);
    tex.wrapS = THREE.RepeatWrapping;
    tex.wrapT = THREE.ClampToEdgeWrapping;
    return tex;
}

function makeFallbackNeroTexture() {
    const S = 64;
    const c = document.createElement('canvas');
    c.width = S;
    c.height = S;
    const ctx = c.getContext('2d');
    ctx.fillStyle = '#19181b';
    ctx.fillRect(0, 0, S, S);
    ctx.strokeStyle = 'rgba(197, 160, 89, 0.34)';
    ctx.lineWidth = 1.1;
    ctx.beginPath();
    ctx.moveTo(4, 10);
    ctx.quadraticCurveTo(30, 28, 58, 54);
    ctx.moveTo(14, 58);
    ctx.quadraticCurveTo(36, 34, 56, 12);
    ctx.stroke();
    const tex = configureHdTexture(new THREE.CanvasTexture(c), MAX_GPU_ANISOTROPY);
    tex.wrapS = THREE.RepeatWrapping;
    tex.wrapT = THREE.RepeatWrapping;
    return tex;
}

function makeFloorAoTexture(isOg = false) {
    const W = 512, H = 768;
    const c = document.createElement('canvas');
    c.width = W;
    c.height = H;
    const ctx = c.getContext('2d');

    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, W, H);

    const xToPx = (wx) => ((wx + 4.25) / HOUSE_W) * W;
    const zToPy = (wz) => ((wz + 7.00) / HOUSE_L) * H;

    // Subtle perimeter wall contact AO
    ctx.strokeStyle = 'rgba(56, 42, 28, 0.34)';
    ctx.lineWidth = 12;
    ctx.strokeRect(6, 6, W - 12, H - 12);

    const paintAO = (wx, wz, rx, rz, alpha = 0.42) => {
        const cx = xToPx(wx), cy = zToPy(wz);
        const prx = (rx / HOUSE_W) * W;
        const pry = (rz / HOUSE_L) * H;
        ctx.save();
        ctx.translate(cx, cy);
        ctx.scale(1, pry / Math.max(1, prx));
        const g = ctx.createRadialGradient(0, 0, prx * 0.16, 0, 0, prx);
        g.addColorStop(0, `rgba(42, 30, 18, ${alpha})`);
        g.addColorStop(0.58, `rgba(42, 30, 18, ${alpha * 0.44})`);
        g.addColorStop(1, 'rgba(42, 30, 18, 0)');
        ctx.fillStyle = g;
        ctx.beginPath();
        ctx.arc(0, 0, prx, 0, Math.PI * 2);
        ctx.fill();
        ctx.restore();
    };

    if (!isOg) {
        paintAO(-3.00, -4.75, 1.28, 1.18, 0.44); // Schlafzimmer Bett
        paintAO(-2.36, -2.94, 1.24, 0.42, 0.36); // Kleiderschrank
        paintAO( 2.48, -5.72, 1.18, 0.68, 0.38); // Hantelbank
        paintAO(-2.25, -1.30, 1.08, 1.08, 0.38); // Buero Schreibtische
        paintAO(-2.55,  2.55, 1.58, 1.48, 0.42); // Wohnzimmer Ecksofa
        paintAO(-2.42,  5.35, 1.38, 0.88, 0.40); // Esstisch
        paintAO( 0.86,  4.45, 0.76, 1.24, 0.44); // Kuecheninsel
        paintAO( 3.70,  4.92, 0.52, 2.02, 0.36); // Kuechenzeile
    } else {
        // Knee-wall contact strips along x = -3.00 and x = +3.00
        const klX = xToPx(-3.00), krX = xToPx(3.00);
        for (const kx of [klX, krX]) {
            const kg = ctx.createLinearGradient(kx - 14, 0, kx + 14, 0);
            kg.addColorStop(0, 'rgba(42, 30, 18, 0)');
            kg.addColorStop(0.5, 'rgba(42, 30, 18, 0.34)');
            kg.addColorStop(1, 'rgba(42, 30, 18, 0)');
            ctx.fillStyle = kg;
            ctx.fillRect(kx - 14, 0, 28, H);
        }
        // South gable wall contact strip
        const sg = ctx.createLinearGradient(0, H - 22, 0, H);
        sg.addColorStop(0, 'rgba(42, 30, 18, 0)');
        sg.addColorStop(1, 'rgba(42, 30, 18, 0.38)');
        ctx.fillStyle = sg;
        ctx.fillRect(klX, H - 22, krX - klX, 22);

        paintAO(-1.68,  5.72, 1.18, 0.64, 0.50); // Atelier Werkbank
        paintAO( 1.96,  5.72, 0.76, 0.60, 0.50); // xTool Laser
        paintAO( 1.04,  5.96, 0.50, 0.44, 0.46); // Boutique Vitrine
        paintAO(-2.48,  4.45, 0.38, 0.38, 0.42); // Monstera Goldtopf
        paintAO(-1.48, -6.34, 0.96, 0.52, 0.38); // Nord-Studio Desk W
        paintAO( 1.22, -6.34, 0.96, 0.52, 0.38); // Nord-Studio Desk E
        paintAO( 2.70, -5.52, 0.38, 1.32, 0.36); // Schranksystem
    }

    const tex = new THREE.CanvasTexture(c);
    tex.generateMipmaps = true;
    tex.minFilter = THREE.LinearMipmapLinearFilter;
    tex.magFilter = THREE.LinearFilter;
    tex.channel = 1;
    return tex;
}

function makeMarbleBathTileTexture() {
    const S = 512;
    const c = document.createElement('canvas');
    c.width = S;
    c.height = S;
    const ctx = c.getContext('2d');
    ctx.fillStyle = '#cfc9c0';
    ctx.fillRect(0, 0, S, S);
    const step = S / 4;
    for (let r = 0; r < 4; r++) {
        for (let col = 0; col < 4; col++) {
            const tx = col * step, ty = r * step;
            ctx.fillStyle = ((r + col) % 2 === 0) ? '#ece8e1' : '#e5e0d7';
            ctx.fillRect(tx + 1.5, ty + 1.5, step - 3, step - 3);
            ctx.strokeStyle = 'rgba(176, 162, 138, 0.28)';
            ctx.lineWidth = 1.3;
            ctx.beginPath();
            ctx.moveTo(tx + 8, ty + step * 0.24 + (r * 7) % 14);
            ctx.bezierCurveTo(tx + step * 0.42, ty + step * 0.52, tx + step * 0.68, ty + step * 0.44, tx + step - 8, ty + step * 0.78);
            ctx.stroke();
        }
    }
    const tex = configureHdTexture(new THREE.CanvasTexture(c), MAX_GPU_ANISOTROPY);
    tex.wrapS = THREE.RepeatWrapping;
    tex.wrapT = THREE.RepeatWrapping;
    return tex;
}

function makeGalleryFrameShadowTexture() {
    const W = 256, H = 192;
    const c = document.createElement('canvas');
    c.width = W;
    c.height = H;
    const ctx = c.getContext('2d');
    ctx.clearRect(0, 0, W, H);
    ctx.save();
    ctx.shadowColor = 'rgba(26, 20, 14, 0.46)';
    ctx.shadowBlur = 22;
    ctx.shadowOffsetY = 7;
    ctx.fillStyle = 'rgba(26, 20, 14, 0.42)';
    ctx.fillRect(36, 30, W - 72, H - 64);
    ctx.restore();
    return configureHdTexture(new THREE.CanvasTexture(c), 2);
}

function createBoiserieWallBoxGeo(sx, height, sz) {
    const geo = new THREE.BoxGeometry(sx, height, sz);
    const pos = geo.attributes.position;
    const nrm = geo.attributes.normal;
    const uv = geo.attributes.uv;
    const vMax = height >= 1.95 ? 1.0 : clamp(height / 2.25, 0.24, 0.82);
    for (let i = 0; i < pos.count; i++) {
        const nx = Math.abs(nrm.getX(i));
        const ny = Math.abs(nrm.getY(i));
        if (ny > 0.5) {
            uv.setXY(i, (pos.getX(i) + sx * 0.5) / BOISERIE_TILE_M, 0.52);
        } else {
            const horiz = nx > 0.5 ? (pos.getZ(i) + sz * 0.5) : (pos.getX(i) + sx * 0.5);
            const vNorm = (pos.getY(i) + height * 0.5) / Math.max(0.05, height);
            uv.setXY(i, horiz / BOISERIE_TILE_M, clamp(vNorm * vMax, 0.0, 1.0));
        }
    }
    return geo;
}

function paintScenicWindowCanvas(canvas, season, isLateNight, occasion = 'none') {
    const W = canvas.width, H = canvas.height;
    const S = W / 256;
    const ctx = canvas.getContext('2d');
    const g = ctx.createLinearGradient(0, 0, 0, H);
    const isFireworkOccasion = (occasion === 'silvester' || occasion === 'new_year');
    const nightSky = isLateNight || isFireworkOccasion;

    if (nightSky) {
        g.addColorStop(0, '#070b18');
        g.addColorStop(0.55, '#131d3a');
        g.addColorStop(1, '#242b4a');
    } else if (season === 'winter') {
        g.addColorStop(0, '#263c5c');
        g.addColorStop(0.62, '#6b8bb0');
        g.addColorStop(1, '#e2edf8');
    } else if (season === 'spring') {
        g.addColorStop(0, '#295684');
        g.addColorStop(0.62, '#7ab1d6');
        g.addColorStop(1, '#f9dec9');
    } else if (season === 'summer') {
        g.addColorStop(0, '#1b5790');
        g.addColorStop(0.66, '#62b5e6');
        g.addColorStop(1, '#fef2c6');
    } else {
        g.addColorStop(0, '#2a203c');
        g.addColorStop(0.46, '#7b4457');
        g.addColorStop(0.80, '#dc8c4e');
        g.addColorStop(1, '#ffe0a8');
    }
    ctx.fillStyle = g;
    ctx.fillRect(0, 0, W, H);

    if (nightSky) {
        for (let i = 0; i < 44; i++) {
            const sx = ((i * 137) % Math.max(1, W - 28)) + 14;
            const sy = ((i * 83) % Math.max(1, Math.floor(H * 0.68))) + 14;
            const sr = ((i % 4 === 0) ? 2.1 : 1.2) * S;
            ctx.fillStyle = (i % 5 === 0) ? 'rgba(255, 232, 175, 0.95)' : 'rgba(242, 247, 255, 0.84)';
            ctx.beginPath();
            ctx.arc(sx, sy, sr, 0, Math.PI * 2);
            ctx.fill();
        }
        const moonHalo = ctx.createRadialGradient(W * 0.68, H * 0.28, 6 * S, W * 0.68, H * 0.28, 48 * S);
        moonHalo.addColorStop(0, 'rgba(255, 236, 182, 0.34)');
        moonHalo.addColorStop(1, 'rgba(255, 236, 182, 0)');
        ctx.fillStyle = moonHalo;
        ctx.fillRect(0, 0, W, H);

        ctx.fillStyle = occasion === 'halloween' ? '#ff9d3b' : '#ffecba';
        ctx.beginPath(); ctx.arc(W * 0.68, H * 0.28, 22 * S, 0, Math.PI * 2); ctx.fill();
        if (occasion !== 'halloween') {
            ctx.fillStyle = '#101833';
            ctx.beginPath(); ctx.arc(W * 0.68 + 8 * S, H * 0.28 - 4 * S, 19 * S, 0, Math.PI * 2); ctx.fill();
        }
        if (isFireworkOccasion) {
            const bursts = [
                { cx: W * 0.28, cy: H * 0.34, r: 34 * S, col: '#ffd978' },
                { cx: W * 0.72, cy: H * 0.44, r: 28 * S, col: '#ff7aa8' },
                { cx: W * 0.48, cy: H * 0.22, r: 24 * S, col: '#7ce8ff' }
            ];
            for (const b of bursts) {
                ctx.strokeStyle = b.col;
                ctx.lineWidth = 2.0 * S;
                for (let k = 0; k < 16; k++) {
                    const a = (k / 16) * Math.PI * 2;
                    ctx.beginPath();
                    ctx.moveTo(b.cx + Math.cos(a) * 4 * S, b.cy + Math.sin(a) * 4 * S);
                    ctx.lineTo(b.cx + Math.cos(a) * b.r, b.cy + Math.sin(a) * b.r);
                    ctx.stroke();
                }
            }
        }
    } else {
        const sunR = (season === 'summer' ? 118 : 98) * S;
        const sg = ctx.createRadialGradient(W * 0.62, H * 0.56, 8 * S, W * 0.62, H * 0.56, sunR);
        sg.addColorStop(0, season === 'summer' ? 'rgba(255, 250, 220, 0.96)' : 'rgba(255, 245, 208, 0.88)');
        sg.addColorStop(0.45, 'rgba(255, 230, 172, 0.38)');
        sg.addColorStop(1, 'rgba(255, 220, 150, 0)');
        ctx.fillStyle = sg;
        ctx.fillRect(0, 0, W, H);

        // Soft sunlit cirrus clouds
        ctx.fillStyle = 'rgba(255, 250, 240, 0.16)';
        for (let cl = 0; cl < 4; cl++) {
            ctx.beginPath();
            ctx.ellipse(W * (0.20 + cl * 0.22), H * (0.24 + (cl % 2) * 0.11), 46 * S, 9 * S, -0.05, 0, Math.PI * 2);
            ctx.fill();
        }
    }

    if (occasion === 'advent' || occasion === 'christmas' || occasion === 'nikolaus') {
        ctx.fillStyle = '#fff2be';
        ctx.beginPath();
        ctx.arc(W * 0.26, H * 0.24, 5.5 * S, 0, Math.PI * 2);
        ctx.fill();
    }

    // Distant softly hazed treeline + foreground Gifhorn forest silhouette
    ctx.fillStyle = nightSky ? 'rgba(18, 24, 46, 0.72)' : (season === 'winter' ? 'rgba(196, 212, 230, 0.75)' : 'rgba(64, 46, 62, 0.45)');
    ctx.beginPath();
    ctx.moveTo(0, H);
    ctx.lineTo(0, H * 0.79);
    for (let x = 0; x <= W; x += 16 * S) {
        const th = H * 0.79 - Math.abs(Math.sin(x * 0.032 / S)) * 14 * S;
        ctx.lineTo(x, th);
    }
    ctx.lineTo(W, H);
    ctx.closePath();
    ctx.fill();

    ctx.fillStyle = nightSky ? '#080b16' : (season === 'winter' ? '#dae6f2' : '#221823');
    ctx.beginPath();
    ctx.moveTo(0, H);
    ctx.lineTo(0, H * 0.84);
    for (let x = 0; x <= W; x += 14 * S) {
        const th = H * 0.84 - Math.abs(Math.sin(x * 0.055 / S)) * 18 * S - ((Math.round(x / (14 * S)) % 2 === 0) ? 9 * S : 0);
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
        g.addColorStop(0.5, '#f4f8fc');
        g.addColorStop(1, '#dce5f0');
        ctx.fillStyle = g;
        ctx.fillRect(0, 0, W, H);

        // Soft snowdrifts & subtle blue-silver winter contours
        for (let i = 0; i < 52; i++) {
            const sx = ((i * 173) % W);
            const sy = ((i * 109) % H);
            const rad = (W * 0.08) + (i % 4) * (W * 0.032);
            const rg = ctx.createRadialGradient(sx, sy, 6, sx, sy, rad);
            rg.addColorStop(0, 'rgba(255, 255, 255, 0.80)');
            rg.addColorStop(1, 'rgba(198, 214, 234, 0)');
            ctx.fillStyle = rg;
            ctx.beginPath(); ctx.arc(sx, sy, rad, 0, Math.PI * 2); ctx.fill();
        }
        // Sparkling ice crystal micro-glints
        ctx.fillStyle = 'rgba(255, 255, 255, 0.92)';
        for (let s = 0; s < 220; s++) {
            const gx = ((s * 97 + 31) % (W - 24)) + 12;
            const gy = ((s * 67 + 19) % (H - 24)) + 12;
            ctx.fillRect(gx, gy, (s % 3 === 0) ? 2.6 : 1.6, (s % 3 === 0) ? 2.6 : 1.6);
        }
    } else {
        const basePalette = season === 'summer'
            ? ['#397a3a', '#468f43', '#316b33']
            : (season === 'spring'
                ? ['#3d8544', '#4d9c51', '#35743b']
                : ['#4b6c36', '#617939', '#405c30']);
        const g = ctx.createLinearGradient(0, 0, W, H);
        g.addColorStop(0, basePalette[0]);
        g.addColorStop(0.52, basePalette[1]);
        g.addColorStop(1, basePalette[2]);
        ctx.fillStyle = g;
        ctx.fillRect(0, 0, W, H);

        // Manicured Seelenfunke mower stripes
        const stripes = 16;
        const stripeW = W / stripes;
        for (let s = 0; s < stripes; s++) {
            ctx.fillStyle = (s % 2 === 0) ? 'rgba(255, 255, 232, 0.052)' : 'rgba(18, 34, 14, 0.045)';
            ctx.fillRect(s * stripeW, 0, stripeW, H);
        }

        // Fine procedural grass-blade micro-texture (baked once on season change = 0 runtime cost!)
        ctx.lineWidth = 1.3;
        for (let b = 0; b < 1100; b++) {
            const bx = ((b * 131 + 23) % (W - 12)) + 6;
            const by = ((b * 89 + 41) % (H - 12)) + 6;
            const tilt = (((b * 17) % 11) - 5) * 0.65;
            ctx.strokeStyle = (b % 3 === 0) ? 'rgba(148, 206, 118, 0.24)' : 'rgba(22, 48, 19, 0.22)';
            ctx.beginPath();
            ctx.moveTo(bx, by);
            ctx.lineTo(bx + tilt, by - 6.5);
            ctx.stroke();
        }

        // Crisp seasonal lawn accents: multi-petal daisies, buttercups, or veined autumn leaves
        for (let i = 0; i < 180; i++) {
            const px = ((i * 149 + 29) % (W - 32)) + 16;
            const py = ((i * 97 + 43) % (H - 32)) + 16;
            if (season === 'spring' || (season === 'summer' && i % 2 === 0)) {
                const isGold = (i % 5 === 0);
                ctx.fillStyle = isGold ? '#ffdf6b' : '#fffaf4';
                for (let p = 0; p < 5; p++) {
                    const a = (p / 5) * Math.PI * 2;
                    ctx.beginPath();
                    ctx.arc(px + Math.cos(a) * 3.0, py + Math.sin(a) * 3.0, 2.0, 0, Math.PI * 2);
                    ctx.fill();
                }
                ctx.fillStyle = isGold ? '#d98a1e' : '#f5c242';
                ctx.beginPath(); ctx.arc(px, py, 1.9, 0, Math.PI * 2); ctx.fill();
            } else if (season === 'autumn') {
                const leafCols = ['#dc7a28', '#cb4b29', '#e5a836', '#9e3b24', '#c58f2b'];
                ctx.fillStyle = leafCols[i % leafCols.length];
                ctx.beginPath();
                ctx.ellipse(px, py, 6.2, 3.5, (i * 0.7) % 3.14, 0, Math.PI * 2);
                ctx.fill();
            }
        }
    }

    // Soft Ambient Occlusion under garden tree, flowerbed & Hochbeet
    const paintGardenAO = (nx, ny, rx, ry, alpha = 0.28) => {
        const cx = nx * W, cy = ny * H;
        ctx.save();
        ctx.translate(cx, cy);
        ctx.scale(1, ry / Math.max(1, rx));
        const g = ctx.createRadialGradient(0, 0, rx * 0.2, 0, 0, rx);
        g.addColorStop(0, `rgba(18, 26, 14, ${alpha})`);
        g.addColorStop(1, 'rgba(18, 26, 14, 0)');
        ctx.fillStyle = g;
        ctx.beginPath(); ctx.arc(0, 0, rx, 0, Math.PI * 2); ctx.fill();
        ctx.restore();
    };
    paintGardenAO(0.84, 0.70, W * 0.085, H * 0.11, 0.30); // Gartenbaum
    paintGardenAO(0.28, 0.66, W * 0.110, H * 0.095, 0.28); // Blumenbeet
    paintGardenAO(0.80, 0.25, W * 0.082, H * 0.120, 0.28); // Hochbeet

    // Perimeter soil border shadow along fence
    ctx.strokeStyle = season === 'winter' ? 'rgba(168, 186, 208, 0.58)' : 'rgba(28, 40, 19, 0.46)';
    ctx.lineWidth = Math.round(W * 0.024);
    const pad = Math.round(W * 0.012);
    ctx.strokeRect(pad, pad, W - pad * 2, H - pad * 2);
}

function paintTerraceDeckCanvas(canvas, season) {
    const W = canvas.width, H = canvas.height;
    const ctx = canvas.getContext('2d');

    ctx.fillStyle = season === 'winter' ? '#6e5c4d' : (season === 'summer' ? '#8f623c' : '#7d5535');
    ctx.fillRect(0, 0, W, H);

    const planks = 24;
    const ph = H / planks;
    for (let r = 0; r < planks; r++) {
        const y = r * ph;
        const v = ((r * 17) % 16) - 8;
        const baseR = (season === 'winter' ? 134 : 162) + v;
        const baseG = (season === 'winter' ? 116 : 114) + Math.round(v * 0.8);
        const baseB = (season === 'winter' ? 99 : 74) + Math.round(v * 0.6);
        ctx.fillStyle = `rgb(${baseR}, ${baseG}, ${baseB})`;
        ctx.fillRect(6, y + 2.8, W - 12, ph - 5.2);

        // Fine teak wood-grain streaks + plank butt-joint + brass deck screws
        paintWoodPlankDetail(
            ctx,
            6,
            y + 2.8,
            W - 12,
            ph - 5.2,
            'rgba(48, 28, 14, 0.24)',
            season === 'winter' ? 'rgba(240, 246, 255, 0.28)' : 'rgba(255, 238, 204, 0.14)',
            r * 19 + 5
        );
        const jointX = ((r * 263 + 140) % (W - 220)) + 110;
        ctx.fillStyle = 'rgba(26, 16, 9, 0.52)';
        ctx.fillRect(jointX, y + 2.8, 2.2, ph - 5.2);
        ctx.fillStyle = 'rgba(214, 178, 102, 0.65)';
        for (const sx of [jointX - 8, jointX + 9, 18, W - 20]) {
            ctx.beginPath(); ctx.arc(sx, y + ph * 0.34, 1.8, 0, Math.PI * 2); ctx.fill();
            ctx.beginPath(); ctx.arc(sx, y + ph * 0.68, 1.8, 0, Math.PI * 2); ctx.fill();
        }

        // Deep recessed shadow gap between terrace planks
        ctx.fillStyle = 'rgba(22, 14, 8, 0.64)';
        ctx.fillRect(0, y, W, 2.8);
    }

    if (season === 'winter') {
        const sg = ctx.createRadialGradient(W * 0.12, H * 0.86, 14, W * 0.12, H * 0.86, W * 0.38);
        sg.addColorStop(0, 'rgba(244, 248, 255, 0.84)');
        sg.addColorStop(1, 'rgba(244, 248, 255, 0)');
        ctx.fillStyle = sg;
        ctx.fillRect(0, 0, W, H);
    }

    ctx.strokeStyle = '#C5A059';
    ctx.lineWidth = 10;
    ctx.strokeRect(5, 5, W - 10, H - 10);
    ctx.strokeStyle = 'rgba(255, 238, 186, 0.55)';
    ctx.lineWidth = 2.5;
    ctx.strokeRect(11, 11, W - 22, H - 22);
}

function makeSunbeamTexture() {
    const c = document.createElement('canvas');
    c.width = 256;
    c.height = 512;
    const ctx = c.getContext('2d');
    const g = ctx.createLinearGradient(0, 0, 256, 512);
    g.addColorStop(0, 'rgba(255, 228, 164, 0.20)');
    g.addColorStop(0.55, 'rgba(255, 214, 135, 0.07)');
    g.addColorStop(1, 'rgba(255, 214, 135, 0)');
    ctx.fillStyle = g;
    ctx.fillRect(0, 0, 256, 512);
    return configureHdTexture(new THREE.CanvasTexture(c), 2);
}

function makeWallGlowTexture() {
    const c = document.createElement('canvas');
    c.width = 256;
    c.height = 256;
    const ctx = c.getContext('2d');
    const g = ctx.createRadialGradient(128, 128, 8, 128, 128, 124);
    g.addColorStop(0, 'rgba(255, 226, 154, 0.48)');
    g.addColorStop(0.5, 'rgba(218, 172, 90, 0.15)');
    g.addColorStop(1, 'rgba(0, 0, 0, 0)');
    ctx.fillStyle = g;
    ctx.fillRect(0, 0, 256, 256);
    return configureHdTexture(new THREE.CanvasTexture(c), 2);
}

function makeChestEmblemTexture() {
    const c = document.createElement('canvas');
    c.width = 768;
    c.height = 384;
    const ctx = c.getContext('2d');
    ctx.clearRect(0, 0, 768, 384);
    ctx.save();
    ctx.scale(2, 2);

    // Subtle 24K Gold Chest Filigree Line & Seelenfunke Star Crest
    ctx.strokeStyle = 'rgba(245, 214, 150, 0.55)';
    ctx.lineWidth = 2.0;
    ctx.beginPath();
    ctx.moveTo(74, 34); ctx.lineTo(170, 34);
    ctx.moveTo(214, 34); ctx.lineTo(310, 34);
    ctx.stroke();

    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillStyle = '#FCE29C';
    ctx.font = '700 20px Georgia, serif';
    ctx.fillText('✦', 192, 34);

    const txtGrad = ctx.createLinearGradient(0, 52, 0, 102);
    txtGrad.addColorStop(0, '#fff4f8');
    txtGrad.addColorStop(0.55, '#ffd2e1');
    txtGrad.addColorStop(1, '#e88ca6');
    ctx.fillStyle = txtGrad;
    ctx.font = '900 50px "Orbitron", "Segoe UI", sans-serif';
    ctx.fillText('FUNKIRA', 192, 78);

    ctx.fillStyle = '#f5b87a';
    ctx.font = '700 25px Georgia, serif';
    ctx.fillText('Mein-Seelenfunke', 192, 126);

    ctx.strokeStyle = 'rgba(232, 150, 176, 0.50)';
    ctx.lineWidth = 1.6;
    ctx.beginPath();
    ctx.moveTo(108, 152); ctx.lineTo(192, 162); ctx.lineTo(276, 152);
    ctx.stroke();
    ctx.restore();
    return configureHdTexture(new THREE.CanvasTexture(c), 4);
}

function makeBossBadgeTexture() {
    const c = document.createElement('canvas');
    c.width = 384;
    c.height = 384;
    const ctx = c.getContext('2d');
    ctx.clearRect(0, 0, 384, 384);
    ctx.save();
    ctx.scale(2, 2);
    ctx.beginPath();
    ctx.moveTo(27, 21);
    ctx.lineTo(165, 21);
    ctx.lineTo(159, 114);
    ctx.quadraticCurveTo(154, 154, 96, 177);
    ctx.quadraticCurveTo(38, 154, 33, 114);
    ctx.closePath();
    const shGrad = ctx.createLinearGradient(0, 20, 0, 178);
    shGrad.addColorStop(0, '#231d29');
    shGrad.addColorStop(1, '#120f16');
    ctx.fillStyle = shGrad;
    ctx.fill();
    ctx.lineWidth = 7.5;
    ctx.strokeStyle = '#e7a1b4';
    ctx.stroke();
    ctx.lineWidth = 2.0;
    ctx.strokeStyle = 'rgba(247, 220, 152, 0.68)';
    ctx.stroke();

    ctx.textAlign = 'center';
    ctx.fillStyle = '#FCE29C';
    ctx.font = '700 26px sans-serif';
    ctx.fillText('★', 96, 56);
    ctx.fillStyle = '#fff5f8';
    ctx.font = '900 34px "Orbitron", sans-serif';
    ctx.fillText('BOSS', 96, 98);

    ctx.strokeStyle = '#e7a1b4';
    ctx.lineWidth = 5.5;
    ctx.beginPath();
    ctx.moveTo(58, 122);
    ctx.lineTo(96, 143);
    ctx.lineTo(134, 122);
    ctx.stroke();
    ctx.restore();

    return configureHdTexture(new THREE.CanvasTexture(c), 4);
}

function makeWallCrestTexture(textTop, textSub) {
    const c = document.createElement('canvas');
    c.width = 1024;
    c.height = 256;
    const ctx = c.getContext('2d');
    ctx.save();
    ctx.scale(2, 2);
    const bg = ctx.createLinearGradient(0, 0, 512, 128);
    bg.addColorStop(0, '#120f17');
    bg.addColorStop(0.5, '#1c1724');
    bg.addColorStop(1, '#120f17');
    ctx.fillStyle = bg;
    ctx.fillRect(0, 0, 512, 128);
    ctx.strokeStyle = '#C5A059';
    ctx.lineWidth = 4.5;
    ctx.strokeRect(6, 6, 500, 116);
    ctx.strokeStyle = 'rgba(243, 213, 135, 0.48)';
    ctx.lineWidth = 3.0;
    ctx.strokeRect(13, 13, 486, 102);
    ctx.textAlign = 'center';
    ctx.fillStyle = '#F7DF9C';
    ctx.font = '800 27px Georgia, serif';
    ctx.fillText(textTop, 256, 54);
    ctx.fillStyle = '#D8B365';
    ctx.font = '700 14px "Segoe UI", sans-serif';
    ctx.fillText(textSub, 256, 88);
    ctx.restore();
    return configureHdTexture(new THREE.CanvasTexture(c), 8);
}

function makeIlluminatedBrandSignTexture() {
    const W = 512, H = 224;
    const c = document.createElement('canvas');
    c.width = 1024;
    c.height = 512;
    const ctx = c.getContext('2d');
    ctx.save();
    ctx.scale(1024 / W, 512 / H);

    const bg = ctx.createLinearGradient(0, 0, W, H);
    bg.addColorStop(0, '#110f15');
    bg.addColorStop(0.5, '#1d1822');
    bg.addColorStop(1, '#100e14');
    ctx.fillStyle = bg;
    ctx.fillRect(0, 0, W, H);

    const innerGlow = ctx.createRadialGradient(W * 0.5, H * 0.50, 14, W * 0.5, H * 0.50, W * 0.48);
    innerGlow.addColorStop(0, 'rgba(255, 224, 148, 0.24)');
    innerGlow.addColorStop(0.6, 'rgba(197, 160, 89, 0.08)');
    innerGlow.addColorStop(1, 'rgba(0, 0, 0, 0)');
    ctx.fillStyle = innerGlow;
    ctx.fillRect(0, 0, W, H);

    ctx.strokeStyle = '#E8CA82';
    ctx.lineWidth = 5.5;
    ctx.strokeRect(8, 8, W - 16, H - 16);
    ctx.strokeStyle = 'rgba(218, 180, 104, 0.56)';
    ctx.lineWidth = 3.2;
    ctx.strokeRect(18, 18, W - 36, H - 36);

    ctx.save();
    ctx.translate(W * 0.5, 48);
    ctx.shadowColor = 'rgba(255, 218, 130, 0.85)';
    ctx.shadowBlur = 14;
    ctx.fillStyle = '#FFF7DE';
    ctx.beginPath();
    for (let i = 0; i < 8; i++) {
        const rad = (i % 2 === 0) ? 18.5 : 5.5;
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
    ctx.shadowColor = 'rgba(255, 212, 118, 0.85)';
    ctx.shadowBlur = 16;
    ctx.fillStyle = '#FFF8E3';
    ctx.font = '800 47px Georgia, "Times New Roman", serif';
    ctx.fillText('Mein-Seelenfunke', W * 0.5, 116);
    ctx.shadowBlur = 5;
    ctx.fillStyle = '#FCE29C';
    ctx.fillText('Mein-Seelenfunke', W * 0.5, 116);
    ctx.restore();

    // Vertically feathered matte-gold divider ribbon (eliminates subpixel shimmer on UHD 605)
    const ruleY = 154;
    const ruleV = ctx.createLinearGradient(0, ruleY - 4.0, 0, ruleY + 4.0);
    ruleV.addColorStop(0.0, 'rgba(197, 160, 89, 0.0)');
    ruleV.addColorStop(0.28, 'rgba(197, 160, 89, 0.36)');
    ruleV.addColorStop(0.50, 'rgba(242, 210, 139, 0.84)');
    ruleV.addColorStop(0.72, 'rgba(197, 160, 89, 0.36)');
    ruleV.addColorStop(1.0, 'rgba(197, 160, 89, 0.0)');
    ctx.fillStyle = ruleV;
    ctx.fillRect(112, ruleY - 4.0, W - 224, 8.0);

    ctx.textAlign = 'center';
    ctx.fillStyle = '#E5C37A';
    ctx.font = '700 16px "Segoe UI", sans-serif';
    ctx.fillText('MANUFAKTUR GIFHORN · UNIKATE', W * 0.5, 182);
    ctx.restore();

    return configureHdTexture(new THREE.CanvasTexture(c), 8);
}

function makeCozyLivingRugTexture() {
    const S = 768;
    const c = document.createElement('canvas');
    c.width = S;
    c.height = S;
    const ctx = c.getContext('2d');
    ctx.fillStyle = '#e0d5c5';
    ctx.fillRect(0, 0, S, S);
    for (let y = 0; y < S; y += 6) {
        ctx.fillStyle = (y % 12 === 0) ? 'rgba(255,250,242,0.38)' : 'rgba(155,132,102,0.16)';
        ctx.fillRect(0, y, S, 3);
    }
    for (let x = 0; x < S; x += 8) {
        ctx.fillStyle = (x % 16 === 0) ? 'rgba(255,252,246,0.16)' : 'rgba(140,118,90,0.08)';
        ctx.fillRect(x, 0, 2, S);
    }
    ctx.strokeStyle = '#C5A059';
    ctx.lineWidth = 14;
    ctx.strokeRect(28, 28, S - 56, S - 56);
    ctx.strokeStyle = 'rgba(197, 160, 89, 0.45)';
    ctx.lineWidth = 4;
    ctx.strokeRect(48, 48, S - 96, S - 96);
    return configureHdTexture(new THREE.CanvasTexture(c), 4);
}

function makeDeskMonitorScreenTexture(titleText = 'MEIN SEELENFUNKE') {
    const W = 512, H = 288;
    const c = document.createElement('canvas');
    c.width = W;
    c.height = H;
    const ctx = c.getContext('2d');
    const bg = ctx.createLinearGradient(0, 0, W, H);
    bg.addColorStop(0, '#100e16');
    bg.addColorStop(1, '#1b1724');
    ctx.fillStyle = bg;
    ctx.fillRect(0, 0, W, H);

    // Sidebar + Golden KPI cards
    ctx.fillStyle = '#15121d';
    ctx.fillRect(0, 0, 96, H);
    ctx.strokeStyle = 'rgba(197, 160, 89, 0.42)';
    ctx.lineWidth = 2;
    ctx.strokeRect(3, 3, W - 6, H - 6);

    ctx.fillStyle = '#FCE29C';
    ctx.font = '800 18px Georgia, serif';
    ctx.fillText(`✦ ${titleText}`, 116, 36);

    for (let k = 0; k < 3; k++) {
        const kx = 116 + k * 124;
        ctx.fillStyle = 'rgba(197, 160, 89, 0.12)';
        ctx.fillRect(kx, 56, 112, 64);
        ctx.strokeStyle = 'rgba(197, 160, 89, 0.48)';
        ctx.strokeRect(kx, 56, 112, 64);
        ctx.fillStyle = '#F7DF9C';
        ctx.fillRect(kx + 12, 74, 58, 8);
        ctx.fillStyle = 'rgba(255, 255, 255, 0.45)';
        ctx.fillRect(kx + 12, 92, 82, 6);
    }

    // Golden Revenue Sparkline Chart
    ctx.fillStyle = 'rgba(255, 255, 255, 0.04)';
    ctx.fillRect(116, 138, 372, 124);
    ctx.strokeStyle = '#E5C170';
    ctx.lineWidth = 3.5;
    ctx.beginPath();
    const pts = [[132, 232], [186, 208], [242, 216], [298, 178], [356, 186], [412, 158], [468, 148]];
    pts.forEach(([px, py], idx) => { if (idx === 0) ctx.moveTo(px, py); else ctx.lineTo(px, py); });
    ctx.stroke();

    return configureHdTexture(new THREE.CanvasTexture(c), 4);
}

function makeSmartphoneScreenTexture(callerName = 'Papa') {
    const W = 128, H = 256;
    const c = document.createElement('canvas');
    c.width = W * 2;
    c.height = H * 2;
    const ctx = c.getContext('2d');
    ctx.save();
    ctx.scale(2, 2);

    const bg = ctx.createLinearGradient(0, 0, 0, H);
    bg.addColorStop(0, '#14111b');
    bg.addColorStop(0.55, '#1d1726');
    bg.addColorStop(1, '#100d15');
    ctx.fillStyle = bg;
    ctx.fillRect(0, 0, W, H);

    ctx.strokeStyle = 'rgba(197, 160, 89, 0.58)';
    ctx.lineWidth = 3;
    ctx.strokeRect(5, 5, W - 10, H - 10);

    // Golden Seelenfunke Avatar Circle
    ctx.fillStyle = 'rgba(197, 160, 89, 0.20)';
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
    ctx.restore();

    return configureHdTexture(new THREE.CanvasTexture(c), 4);
}

function makeSeelenfunkePlaqueTexture(mainText = 'MEIN SEELENFUNKE', subText = '', theme = 'obsidian_gold') {
    const W = 256, H = 88;
    const c = document.createElement('canvas');
    c.width = 512;
    c.height = 256;
    const ctx = c.getContext('2d');
    ctx.save();
    ctx.scale(512 / W, 256 / H);

    if (theme === 'gold_plate') {
        const g = ctx.createLinearGradient(0, 0, W, H);
        g.addColorStop(0, '#f0d796');
        g.addColorStop(0.5, '#c89f4d');
        g.addColorStop(1, '#f7e3a6');
        ctx.fillStyle = g;
        ctx.fillRect(0, 0, W, H);
        ctx.strokeStyle = '#18131d';
        ctx.lineWidth = 4.2;
        ctx.strokeRect(4, 4, W - 8, H - 8);
        ctx.lineWidth = 2.6;
        ctx.strokeRect(10, 10, W - 20, H - 20);
        ctx.fillStyle = '#16121b';
    } else {
        const g = ctx.createLinearGradient(0, 0, 0, H);
        g.addColorStop(0, '#1e1825');
        g.addColorStop(0.5, '#110e16');
        g.addColorStop(1, '#1b1622');
        ctx.fillStyle = g;
        ctx.fillRect(0, 0, W, H);
        ctx.strokeStyle = '#C5A059';
        ctx.lineWidth = 4.2;
        ctx.strokeRect(4, 4, W - 8, H - 8);
        ctx.strokeStyle = 'rgba(243, 213, 135, 0.56)';
        ctx.lineWidth = 2.6;
        ctx.strokeRect(10, 10, W - 20, H - 20);
        ctx.fillStyle = '#F9E3A4';
    }

    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    if (subText) {
        ctx.font = '800 20px Georgia, "Times New Roman", serif';
        ctx.fillText(`✦ ${mainText} ✦`, W * 0.5, 34);
        ctx.font = '700 13px "Segoe UI", sans-serif';
        ctx.fillStyle = theme === 'gold_plate' ? '#2c2218' : '#D5B062';
        ctx.fillText(subText, W * 0.5, 62);
    } else {
        ctx.font = '800 22px Georgia, "Times New Roman", serif';
        ctx.fillText(`✦ ${mainText} ✦`, W * 0.5, H * 0.5 + 1);
    }
    ctx.restore();

    return configureHdTexture(new THREE.CanvasTexture(c), 8);
}

function makeSeelenfunkeArtCanvasTexture(motif = 'spark_mandala', subLabel = 'MANUFAKTUR GIFHORN') {
    const W = 256, H = 192;
    const c = document.createElement('canvas');
    c.width = 512;
    c.height = 512;
    const ctx = c.getContext('2d');
    ctx.save();
    ctx.scale(512 / W, 512 / H);

    const bg = ctx.createRadialGradient(W * 0.5, H * 0.44, 10, W * 0.5, H * 0.5, 150);
    bg.addColorStop(0, '#29202d');
    bg.addColorStop(0.65, '#17131c');
    bg.addColorStop(1, '#0e0b12');
    ctx.fillStyle = bg;
    ctx.fillRect(0, 0, W, H);

    // Double 24K Gold Passepartout Frame + Corner Rosettes
    ctx.strokeStyle = '#C5A059';
    ctx.lineWidth = 4.0;
    ctx.strokeRect(10, 10, W - 20, H - 20);
    ctx.strokeStyle = 'rgba(243, 213, 135, 0.52)';
    ctx.lineWidth = 2.6;
    ctx.strokeRect(16, 16, W - 32, H - 32);

    const cx = W * 0.5;
    const cy = 78;
    ctx.save();
    ctx.strokeStyle = '#EACA7B';
    ctx.fillStyle = '#F9E3A4';

    if (motif === 'spark_mandala') {
        for (let r = 0; r < 16; r++) {
            const a = (r / 16) * Math.PI * 2;
            ctx.lineWidth = (r % 4 === 0) ? 2.4 : 1.2;
            ctx.beginPath();
            ctx.moveTo(cx + Math.cos(a) * 11, cy + Math.sin(a) * 11);
            ctx.lineTo(cx + Math.cos(a) * ((r % 2 === 0) ? 43 : 27), cy + Math.sin(a) * ((r % 2 === 0) ? 43 : 27));
            ctx.stroke();
        }
        ctx.lineWidth = 2.0;
        ctx.beginPath();
        ctx.arc(cx, cy, 31, 0, Math.PI * 2);
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

    ctx.textAlign = 'center';
    ctx.fillStyle = '#F9E3A4';
    ctx.font = '800 17px Georgia, "Times New Roman", serif';
    ctx.fillText('MEIN SEELENFUNKE', cx, 146);
    ctx.fillStyle = '#D5B062';
    ctx.font = '700 11px "Segoe UI", sans-serif';
    ctx.fillText(subLabel, cx, 165);
    ctx.restore();

    return configureHdTexture(new THREE.CanvasTexture(c), 4);
}

function makeShadowDiscTexture() {
    const c = document.createElement('canvas');
    c.width = 256;
    c.height = 256;
    const ctx = c.getContext('2d');
    const g = ctx.createRadialGradient(128, 128, 14, 128, 128, 122);
    g.addColorStop(0, 'rgba(0,0,0,0.66)');
    g.addColorStop(0.58, 'rgba(0,0,0,0.26)');
    g.addColorStop(1, 'rgba(0,0,0,0)');
    ctx.fillStyle = g;
    ctx.fillRect(0, 0, 256, 256);
    return configureHdTexture(new THREE.CanvasTexture(c), 2);
}

function freezeStaticTree(rootObj) {
    rootObj.updateMatrixWorld(true);
    rootObj.traverse((node) => {
        node.matrixAutoUpdate = false;
    });
}

// Static Mesh Batcher: merges opaque static leaf meshes sharing identical Material + UV layout
// into single draw calls per visibility group (reduces ~1,600 draw calls down to < 60 on Intel UHD Mini-PC!)
function batchStaticSubtree(rootObj, protectedSet = null, northCullGroup = null, northSplitZ = 2.12) {
    if (!rootObj) return 0;
    rootObj.updateMatrixWorld(true);
    const invRoot = rootObj.matrixWorld.clone().invert();
    const invNorth = northCullGroup ? northCullGroup.matrixWorld.clone().invert() : null;
    const buckets = new Map();
    const worldBox = northCullGroup ? new THREE.Box3() : null;

    const walk = (node) => {
        if (node !== rootObj && protectedSet && protectedSet.has(node)) return;
        if (node.isMesh && node.children.length === 0 && node.visible && node.geometry && node.material && !Array.isArray(node.material)) {
            const mat = node.material;
            const geo = node.geometry;
            if (!mat.transparent && geo.attributes && geo.attributes.position && node.matrixWorld.determinant() > 0) {
                let zone = 'main';
                if (northCullGroup && worldBox) {
                    if (!geo.boundingBox) geo.computeBoundingBox();
                    worldBox.copy(geo.boundingBox).applyMatrix4(node.matrixWorld);
                    if (worldBox.max.z < northSplitZ) {
                        zone = 'north';
                    }
                }
                const hasNorm = !!geo.attributes.normal;
                const hasUv = !!geo.attributes.uv;
                const hasUv1 = !!geo.attributes.uv1;
                const key = `${zone}|${mat.uuid}|${hasNorm ? 1 : 0}|${hasUv ? 1 : 0}|${hasUv1 ? 1 : 0}|${node.renderOrder || 0}`;
                let arr = buckets.get(key);
                if (!arr) {
                    arr = [];
                    buckets.set(key, arr);
                }
                arr.push(node);
            }
        }
        for (let i = 0; i < node.children.length; i++) {
            walk(node.children[i]);
        }
    };
    walk(rootObj);

    let savedCalls = 0;
    const relMat = new THREE.Matrix4();

    for (const [key, meshes] of buckets.entries()) {
        const isNorth = key.startsWith('north|');
        if (meshes.length < 2) {
            if (isNorth && northCullGroup && invNorth && meshes.length === 1) {
                const single = meshes[0];
                relMat.multiplyMatrices(invNorth, single.matrixWorld);
                if (single.parent) single.parent.remove(single);
                single.matrixAutoUpdate = false;
                single.matrix.copy(relMat);
                single.matrix.decompose(single.position, single.quaternion, single.scale);
                northCullGroup.add(single);
            }
            continue;
        }

        const sample = meshes[0];
        const hasNorm = !!sample.geometry.attributes.normal;
        const hasUv = !!sample.geometry.attributes.uv;
        const hasUv1 = !!sample.geometry.attributes.uv1;
        const targetParent = (isNorth && northCullGroup) ? northCullGroup : rootObj;
        const targetInv = (isNorth && invNorth) ? invNorth : invRoot;

        const bakedGeos = [];
        let totalVerts = 0;
        for (let i = 0; i < meshes.length; i++) {
            const m = meshes[i];
            const g = m.geometry.index ? m.geometry.toNonIndexed() : m.geometry.clone();
            relMat.multiplyMatrices(targetInv, m.matrixWorld);
            g.applyMatrix4(relMat);
            totalVerts += g.attributes.position.count;
            bakedGeos.push(g);
        }

        const posArr = new Float32Array(totalVerts * 3);
        const normArr = hasNorm ? new Float32Array(totalVerts * 3) : null;
        const uvArr = hasUv ? new Float32Array(totalVerts * 2) : null;
        const uv1Arr = hasUv1 ? new Float32Array(totalVerts * 2) : null;

        let vOff = 0;
        for (let i = 0; i < bakedGeos.length; i++) {
            const g = bakedGeos[i];
            const cnt = g.attributes.position.count;
            posArr.set(g.attributes.position.array.subarray(0, cnt * 3), vOff * 3);
            if (normArr) normArr.set(g.attributes.normal.array.subarray(0, cnt * 3), vOff * 3);
            if (uvArr) uvArr.set(g.attributes.uv.array.subarray(0, cnt * 2), vOff * 2);
            if (uv1Arr) uv1Arr.set(g.attributes.uv1.array.subarray(0, cnt * 2), vOff * 2);
            vOff += cnt;
            g.dispose();
        }

        const mergedGeo = new THREE.BufferGeometry();
        mergedGeo.setAttribute('position', new THREE.BufferAttribute(posArr, 3));
        if (normArr) mergedGeo.setAttribute('normal', new THREE.BufferAttribute(normArr, 3));
        if (uvArr) mergedGeo.setAttribute('uv', new THREE.BufferAttribute(uvArr, 2));
        if (uv1Arr) mergedGeo.setAttribute('uv1', new THREE.BufferAttribute(uv1Arr, 2));
        mergedGeo.computeBoundingSphere();

        const batchedMesh = new THREE.Mesh(mergedGeo, sample.material);
        batchedMesh.renderOrder = sample.renderOrder || 0;
        batchedMesh.matrixAutoUpdate = false;
        targetParent.add(batchedMesh);

        for (let i = 0; i < meshes.length; i++) {
            const m = meshes[i];
            if (m.parent) m.parent.remove(m);
        }
        savedCalls += (meshes.length - 1);
    }

    const pruneEmpty = (node) => {
        for (let i = node.children.length - 1; i >= 0; i--) {
            pruneEmpty(node.children[i]);
        }
        if (node !== rootObj && node !== northCullGroup && !node.isMesh && node.children.length === 0 && !(protectedSet && protectedSet.has(node))) {
            if (node.parent) node.parent.remove(node);
        }
    };
    pruneEmpty(rootObj);
    freezeStaticTree(rootObj);
    if (northCullGroup) freezeStaticTree(northCullGroup);
    return savedCalls;
}

class FunkiraAtelierEngine {
    constructor() {
        this.canvas = null;
        this.renderer = null;
        this.scene = null;
        this.camera = null;
        this.running = false;
        this.lastMs = 0;
        this.isLowPowerGpu = false;
        this.ogNorthStaticGroup = null;
        this.cellarDomeStaticGroup = null;

        // Hysteresis Render Scale Governor (Zero ping-pong framebuffer reallocation!)
        this.renderScale = 0.88;
        this.fps = 60;
        this._fpsFrames = 0;
        this._fpsWindowStartMs = 0;
        this._lowFpsStreaks = 0;
        this._highFpsStreaks = 0;
        this._scaleLockedDown = false;
        this._lastPixelW = 0;
        this._lastPixelH = 0;
        this._lastAppliedScale = 0;

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

        // Pre-baked Zero-GC 2x Retina Visor Canvas (768 x 480)
        this.visorCanvas = document.createElement('canvas');
        this.visorCanvas.width = 768;
        this.visorCanvas.height = 480;
        this.visorCtx = this.visorCanvas.getContext('2d', { alpha: false });
        this.visorTexture = null;
        this.visorBgCanvas = null;
        this.irisSpriteCanvas = null;
        this.blushSpriteCanvas = null;
        this._lastVisorDrawMs = 0;
        this._lastDrawnSig = '';

        // HD Scenic Window Canvas shared across all 12 EG + OG windows + HD Outdoor Garden & Terrace Canvases
        this.windowCanvas = document.createElement('canvas');
        this.windowCanvas.width = 512;
        this.windowCanvas.height = 512;
        this.windowTexture = null;
        this.gardenGroundCanvas = document.createElement('canvas');
        this.gardenGroundCanvas.width = 1280;
        this.gardenGroundCanvas.height = 960;
        this.gardenGroundTexture = null;
        this.terraceDeckCanvas = document.createElement('canvas');
        this.terraceDeckCanvas.width = 1024;
        this.terraceDeckCanvas.height = 768;
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
        this.cellarBgColor = new THREE.Color(0x0b0a10);
        this.outdoorSkyColor = new THREE.Color(0x47364d);
        this._tmpSpoutWorld = new THREE.Vector3();

        // Secret 3D Kuppelhalle, Hydraulic Lift & Project-Brain State (0% GPU cost when upstairs!)
        this.upstairsFxGroup = null;
        this.cellarGroup = null;
        this.cellarShaftGroup = null;
        this.cellarLiftPlatform = null;
        this.cellarHoloGroup = null;
        this.cellarHatchLedMat = null;
        this.cellarBrainInnerMesh = null;
        this.cellarBrainOrbitC = null;
        this.cellarHealWaveMesh = null;
        this.cellarHealWaveMat = null;
        this.cellarHealAnimTimer = 0;
        this.cellarNodeSlots = [];
        this.cellarBeamSlots = [];
        this.cellarPickMeshes = [];
        this.cellarSnapshot = null;
        this.selectedCellarKey = '';
        this._raycaster = new THREE.Raycaster();
        this._pointerNdc = new THREE.Vector2();
        this._beamUpVec = new THREE.Vector3(0, 1, 0);
        this._beamDirVec = new THREE.Vector3();

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

        try {
            const gl = this.renderer.getContext();
            const dbg = gl ? gl.getExtension('WEBGL_debug_renderer_info') : null;
            const gpuName = (gl && dbg) ? String(gl.getParameter(dbg.UNMASKED_RENDERER_WEBGL) || '') : '';
            this.isLowPowerGpu = /Intel.*(UHD|HD Graphics)|Mesa|llvmpipe|JasperLake|GeminiLake|AlderLake-N/i.test(gpuName)
                || ((navigator.hardwareConcurrency || 8) <= 4);
        } catch {
            this.isLowPowerGpu = ((navigator.hardwareConcurrency || 8) <= 4);
        }

        this.renderScale = this.isLowPowerGpu ? 0.80 : 0.92;
        MAX_GPU_ANISOTROPY = this.isLowPowerGpu
            ? 2
            : Math.min(8, Math.max(1, this.renderer.capabilities.getMaxAnisotropy() || 1));

        this.scene = new THREE.Scene();
        this.scene.background = new THREE.Color(0x121017);

        this.camera = new THREE.PerspectiveCamera(42, 16 / 9, 0.15, 64);
        this.camera.position.set(0, OG_Y + 1.38, 2.26);
        this.camera.lookAt(0, OG_Y + 1.02, 5.45);

        this._bakeVisorSprites();
        this._initSharedMatcaps();
        this._initAtelierPhotorealPbr();
        this._buildTwoStoryHouse();
        this._buildSecretCellar();
        this._buildFunkiraRig();
        this._buildMovableSeelenfunkeItems();
        this._applyWorldStateVisuals();
        this._optimizeStaticWorldFor60Fps();
        this._renderVisorFace(performance.now(), 0.016, true);
        this.resize(true);

        window.addEventListener('resize', () => this.resize(true));
        return true;
    }

    _bakeVisorSprites() {
        const bg = document.createElement('canvas');
        bg.width = 768;
        bg.height = 480;
        const bctx = bg.getContext('2d', { alpha: false });
        bctx.save();
        bctx.scale(2, 2);

        const bgGrad = bctx.createRadialGradient(192, 118, 20, 192, 120, 235);
        bgGrad.addColorStop(0, '#1a1520');
        bgGrad.addColorStop(0.68, '#130f17');
        bgGrad.addColorStop(1, '#0b080e');
        bctx.fillStyle = bgGrad;
        bctx.fillRect(0, 0, 384, 240);

        // Subtle optical micro-grid lines (baked once -> 0% runtime cost)
        bctx.fillStyle = 'rgba(255, 210, 228, 0.014)';
        for (let y = 18; y < 232; y += 4) {
            bctx.fillRect(8, y, 368, 1);
        }

        const topGrad = bctx.createLinearGradient(0, 0, 384, 0);
        topGrad.addColorStop(0, '#c46f84');
        topGrad.addColorStop(0.35, '#f6b4c6');
        topGrad.addColorStop(0.5, '#ffe4ec');
        topGrad.addColorStop(0.65, '#f5b2c4');
        topGrad.addColorStop(1, '#b86277');
        bctx.fillStyle = topGrad;
        bctx.fillRect(8, 8.5, 368, 7.5);

        // Delicate 24K Gold couture hairline under top lightbar
        bctx.fillStyle = 'rgba(218, 178, 102, 0.52)';
        bctx.fillRect(14, 17.5, 356, 1.2);

        bctx.beginPath();
        bctx.moveTo(0, 18);
        bctx.lineTo(192, 18);
        bctx.lineTo(116, 240);
        bctx.lineTo(0, 240);
        bctx.closePath();
        const sheen = bctx.createLinearGradient(0, 18, 165, 240);
        sheen.addColorStop(0, 'rgba(255, 232, 242, 0.11)');
        sheen.addColorStop(0.55, 'rgba(255, 224, 236, 0.045)');
        sheen.addColorStop(1, 'rgba(255, 224, 236, 0.01)');
        bctx.fillStyle = sheen;
        bctx.fill();

        bctx.strokeStyle = '#09070b';
        bctx.lineWidth = 9.5;
        bctx.strokeRect(3, 3, 378, 234);
        bctx.strokeStyle = 'rgba(197, 160, 89, 0.28)';
        bctx.lineWidth = 1.2;
        bctx.strokeRect(8.5, 8.5, 367, 223);
        bctx.restore();
        this.visorBgCanvas = bg;

        // 2x Retina 192x192 Crystal-Fiber Iris Sprite
        const ic = document.createElement('canvas');
        ic.width = 192;
        ic.height = 192;
        const ictx = ic.getContext('2d');
        ictx.save();
        ictx.scale(2, 2);

        // Deep outer limbal ring
        ictx.fillStyle = '#1d050c';
        ictx.beginPath();
        ictx.ellipse(48, 48, 40.2, 42.2, 0, 0, Math.PI * 2);
        ictx.fill();

        const ig = ictx.createRadialGradient(48, 59, 5, 48, 48, 42);
        ig.addColorStop(0, '#ff9ebc');
        ig.addColorStop(0.28, '#df4b78');
        ig.addColorStop(0.62, '#8c1d3e');
        ig.addColorStop(0.88, '#3d0919');
        ig.addColorStop(1, '#1e040c');
        ictx.fillStyle = ig;
        ictx.beginPath();
        ictx.ellipse(48, 48, 38.6, 40.6, 0, 0, Math.PI * 2);
        ictx.fill();

        // 28 radial crystal-rose & champagne-gold iris fibers
        for (let f = 0; f < 28; f++) {
            const ang = (f / 28) * Math.PI * 2;
            const rIn = 19.5 + (f % 2) * 2.0;
            const rOut = 36.2 - (f % 3) * 1.8;
            ictx.strokeStyle = (f % 4 === 0)
                ? 'rgba(255, 222, 168, 0.30)'
                : 'rgba(255, 162, 192, 0.26)';
            ictx.lineWidth = (f % 2 === 0) ? 1.35 : 0.85;
            ictx.beginPath();
            ictx.moveTo(48 + Math.cos(ang) * rIn, 47 + Math.sin(ang) * rIn);
            ictx.lineTo(48 + Math.cos(ang) * rOut, 48 + Math.sin(ang) * rOut);
            ictx.stroke();
        }

        // Upper-lid soft ambient shadow across top of iris
        const topShad = ictx.createLinearGradient(0, 7, 0, 42);
        topShad.addColorStop(0, 'rgba(18, 3, 9, 0.62)');
        topShad.addColorStop(1, 'rgba(18, 3, 9, 0)');
        ictx.fillStyle = topShad;
        ictx.beginPath();
        ictx.ellipse(48, 48, 38.6, 40.6, 0, 0, Math.PI * 2);
        ictx.fill();

        // Deep velvet pupil + thin rose-gold pupil collar
        ictx.strokeStyle = 'rgba(255, 196, 212, 0.44)';
        ictx.lineWidth = 1.4;
        ictx.fillStyle = '#18030a';
        ictx.beginPath();
        ictx.ellipse(48, 46, 19.6, 21.6, 0, 0, Math.PI * 2);
        ictx.fill();
        ictx.stroke();

        // Lower golden-rose caustic crescent
        const caustGrad = ictx.createLinearGradient(22, 68, 74, 68);
        caustGrad.addColorStop(0, 'rgba(255, 168, 195, 0.45)');
        caustGrad.addColorStop(0.5, 'rgba(255, 226, 190, 0.88)');
        caustGrad.addColorStop(1, 'rgba(255, 168, 195, 0.45)');
        ictx.strokeStyle = caustGrad;
        ictx.lineWidth = 3.4;
        ictx.lineCap = 'round';
        ictx.beginPath();
        ictx.arc(48, 50.5, 26.2, 0.20 * Math.PI, 0.80 * Math.PI);
        ictx.stroke();

        // Primary crystal studio catchlight + secondary starlight gem highlight
        ictx.fillStyle = '#ffffff';
        ictx.beginPath();
        ictx.ellipse(35.5, 33.5, 10.6, 12.2, -0.22, 0, Math.PI * 2);
        ictx.fill();

        ictx.fillStyle = 'rgba(255, 244, 248, 0.96)';
        ictx.beginPath();
        ictx.arc(61.5, 59.5, 4.9, 0, Math.PI * 2);
        ictx.fill();

        // Tiny 4-point Seelenfunke starlight glint on upper-right iris rim
        ictx.fillStyle = 'rgba(255, 246, 218, 0.92)';
        ictx.beginPath();
        for (let k = 0; k < 8; k++) {
            const ka = (k / 8) * Math.PI * 2;
            const kr = (k % 2 === 0) ? 4.2 : 1.3;
            const kx = 63.0 + Math.cos(ka) * kr;
            const ky = 32.0 + Math.sin(ka) * kr;
            if (k === 0) ictx.moveTo(kx, ky); else ictx.lineTo(kx, ky);
        }
        ictx.closePath();
        ictx.fill();
        ictx.restore();
        this.irisSpriteCanvas = ic;

        // 2x Retina 128x80 Blush Sprite
        const bc = document.createElement('canvas');
        bc.width = 128;
        bc.height = 80;
        const blctx = bc.getContext('2d');
        blctx.save();
        blctx.scale(2, 2);
        const blushGrad = blctx.createRadialGradient(32, 20, 2, 32, 20, 27);
        blushGrad.addColorStop(0, 'rgba(242, 92, 130, 0.48)');
        blushGrad.addColorStop(0.6, 'rgba(235, 84, 122, 0.20)');
        blushGrad.addColorStop(1, 'rgba(235, 84, 122, 0)');
        blctx.fillStyle = blushGrad;
        blctx.fillRect(0, 0, 64, 40);

        blctx.strokeStyle = 'rgba(245, 110, 144, 0.88)';
        blctx.lineWidth = 2.1;
        blctx.lineCap = 'round';
        for (let s = -2; s <= 2; s++) {
            blctx.beginPath();
            blctx.moveTo(32 + s * 6.8 - 2.8, 24.5);
            blctx.lineTo(32 + s * 6.8 + 2.8, 15.5);
            blctx.stroke();
        }
        blctx.restore();
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

    _initAtelierPhotorealPbr() {
        // 1. Instant synchronous Studio-Atrium PMREM environment (0ms flicker-free start, 0 dynamic lights!)
        this.pmremGenerator = new THREE.PMREMGenerator(this.renderer);
        this.pmremGenerator.compileEquirectangularShader();
        const fallbackEnvTex = makeFallbackEquirectEnvTexture();
        this.atelierPmremTarget = this.pmremGenerator.fromEquirectangular(fallbackEnvTex);
        fallbackEnvTex.dispose();
        this.scene.environment = this.atelierPmremTarget.texture;

        // 2. Instant synchronous fallback maps (identical shader defines = 0 runtime shader recompiles on Intel UHD 605!)
        const fbParquet  = makeFallbackParquetTexture();
        const fbBoiserie = makeFallbackBoiserieTexture();
        const fbNero     = makeFallbackNeroTexture();
        const egAoTex    = makeFloorAoTexture(false);
        const ogAoTex    = makeFloorAoTexture(true);
        const bathTex    = makeMarbleBathTileTexture();

        this.mats.atelierFloorEgPbr = new THREE.MeshStandardMaterial({
            map: fbParquet,
            bumpMap: fbParquet,
            bumpScale: 0.008,
            roughness: 0.36,
            metalness: 0.02,
            envMapIntensity: 0.65,
            aoMap: egAoTex,
            aoMapIntensity: 0.68,
            emissive: new THREE.Color(0xfffaf2),
            emissiveMap: fbParquet,
            emissiveIntensity: 0.28
        });

        this.mats.atelierFloorOgPbr = new THREE.MeshStandardMaterial({
            map: fbParquet,
            bumpMap: fbParquet,
            bumpScale: 0.008,
            roughness: 0.36,
            metalness: 0.02,
            envMapIntensity: 0.65,
            aoMap: ogAoTex,
            aoMapIntensity: 0.72,
            emissive: new THREE.Color(0xfffaf2),
            emissiveMap: fbParquet,
            emissiveIntensity: 0.28
        });

        this.mats.atelierBoiseriePbr = new THREE.MeshStandardMaterial({
            map: fbBoiserie,
            bumpMap: fbBoiserie,
            bumpScale: 0.012,
            roughness: 0.68,
            metalness: 0.02,
            envMapIntensity: 0.58,
            emissive: new THREE.Color(0xfffcf6),
            emissiveMap: fbBoiserie,
            emissiveIntensity: 0.34,
            side: THREE.FrontSide
        });

        this.mats.atelierNeroStonePbr = new THREE.MeshStandardMaterial({
            map: fbNero,
            bumpMap: fbNero,
            bumpScale: 0.006,
            roughness: 0.24,
            metalness: 0.06,
            envMapIntensity: 0.90,
            emissive: new THREE.Color(0xffffff),
            emissiveMap: fbNero,
            emissiveIntensity: 0.14
        });

        this.mats.atelierGoldPbr = new THREE.MeshStandardMaterial({
            color: 0xC5A059,
            metalness: 0.86,
            roughness: 0.22,
            envMapIntensity: 1.25,
            emissive: new THREE.Color(0x261b08),
            emissiveIntensity: 0.20
        });

        this.mats.atelierCeilingPbr = new THREE.MeshStandardMaterial({
            color: 0xebe3d5,
            roughness: 0.64,
            metalness: 0.01,
            envMapIntensity: 0.55,
            emissive: new THREE.Color(0xf5efe4),
            emissiveIntensity: 0.36
        });

        this.mats.atelierBathStonePbr = new THREE.MeshStandardMaterial({
            map: bathTex,
            bumpMap: bathTex,
            bumpScale: 0.004,
            roughness: 0.22,
            metalness: 0.03,
            envMapIntensity: 0.72,
            emissive: new THREE.Color(0xffffff),
            emissiveMap: bathTex,
            emissiveIntensity: 0.30,
            polygonOffset: true,
            polygonOffsetFactor: -1,
            polygonOffsetUnits: -2
        });

        this.mats.atelierGlassPbr = new THREE.MeshStandardMaterial({
            color: 0xf6fbff,
            metalness: 0.12,
            roughness: 0.06,
            envMapIntensity: 1.35,
            transparent: true,
            opacity: 0.26,
            depthWrite: false
        });

        // Intel UHD / Mesa Mini-PC Fast Fillrate Path: replace full-screen 14-sample PMREM+Bump PBR shaders
        // on large matte architectural walls/floors/ceilings with zero-light Baked-AO shaders (10x faster fragment fillrate!)
        if (this.isLowPowerGpu) {
            this.mats.atelierFloorEgPbr.dispose();
            this.mats.atelierFloorOgPbr.dispose();
            this.mats.atelierBoiseriePbr.dispose();
            this.mats.atelierCeilingPbr.dispose();
            this.mats.atelierBathStonePbr.dispose();

            this.mats.atelierFloorEgPbr = new THREE.MeshBasicMaterial({
                map: fbParquet,
                aoMap: egAoTex,
                aoMapIntensity: 0.72,
                color: 0xf8f3eb
            });
            this.mats.atelierFloorOgPbr = new THREE.MeshBasicMaterial({
                map: fbParquet,
                aoMap: ogAoTex,
                aoMapIntensity: 0.76,
                color: 0xf8f3eb
            });
            this.mats.atelierBoiseriePbr = new THREE.MeshBasicMaterial({
                map: fbBoiserie,
                color: 0xfaf6ee,
                side: THREE.FrontSide
            });
            this.mats.atelierCeilingPbr = new THREE.MeshBasicMaterial({
                color: 0xeae2d4
            });
            this.mats.atelierBathStonePbr = new THREE.MeshBasicMaterial({
                map: bathTex,
                color: 0xf7f6f2,
                polygonOffset: true,
                polygonOffsetFactor: -1,
                polygonOffsetUnits: -2
            });
            this.mats.atelierNeroStonePbr.bumpMap = null;
        }

        // 3. Asynchronous HD Photorealistic Asset Streaming (< 80ms local loopback HTTP)
        const texLoader = new THREE.TextureLoader();

        texLoader.load(`/assets/atelier_golden_env.jpg?v=${ATELIER_ASSET_VER}`, (envTex) => {
            envTex.colorSpace = THREE.SRGBColorSpace;
            envTex.mapping = THREE.EquirectangularReflectionMapping;
            if (this.pmremGenerator && this.scene) {
                const nextPmrem = this.pmremGenerator.fromEquirectangular(envTex);
                const prevPmrem = this.atelierPmremTarget;
                this.atelierPmremTarget = nextPmrem;
                this.scene.environment = nextPmrem.texture;
                if (prevPmrem) prevPmrem.dispose();
            }
            envTex.dispose();
        });

        texLoader.load(`/assets/atelier_herringbone_oak.jpg?v=${ATELIER_ASSET_VER}`, (oakTex) => {
            oakTex.colorSpace = THREE.SRGBColorSpace;
            oakTex.wrapS = THREE.RepeatWrapping;
            oakTex.wrapT = THREE.RepeatWrapping;
            configureHdTexture(oakTex, MAX_GPU_ANISOTROPY);
            for (const fMat of [this.mats.atelierFloorEgPbr, this.mats.atelierFloorOgPbr]) {
                fMat.map = oakTex;
                if ('bumpMap' in fMat && !this.isLowPowerGpu) fMat.bumpMap = oakTex;
                if ('emissiveMap' in fMat) fMat.emissiveMap = oakTex;
                fMat.needsUpdate = true;
            }
            fbParquet.dispose();
        });

        texLoader.load(`/assets/atelier_boiserie_wall.jpg?v=${ATELIER_ASSET_VER}`, (wallTex) => {
            wallTex.colorSpace = THREE.SRGBColorSpace;
            wallTex.wrapS = THREE.RepeatWrapping;
            wallTex.wrapT = THREE.ClampToEdgeWrapping;
            configureHdTexture(wallTex, MAX_GPU_ANISOTROPY);
            this.mats.atelierBoiseriePbr.map = wallTex;
            if ('bumpMap' in this.mats.atelierBoiseriePbr && !this.isLowPowerGpu) {
                this.mats.atelierBoiseriePbr.bumpMap = wallTex;
            }
            if ('emissiveMap' in this.mats.atelierBoiseriePbr) {
                this.mats.atelierBoiseriePbr.emissiveMap = wallTex;
            }
            this.mats.atelierBoiseriePbr.needsUpdate = true;
            fbBoiserie.dispose();
        });

        texLoader.load(`/assets/atelier_nero_stone.jpg?v=${ATELIER_ASSET_VER}`, (stoneTex) => {
            stoneTex.colorSpace = THREE.SRGBColorSpace;
            stoneTex.wrapS = THREE.RepeatWrapping;
            stoneTex.wrapT = THREE.RepeatWrapping;
            configureHdTexture(stoneTex, MAX_GPU_ANISOTROPY);
            this.mats.atelierNeroStonePbr.map = stoneTex;
            if (!this.isLowPowerGpu) this.mats.atelierNeroStonePbr.bumpMap = stoneTex;
            this.mats.atelierNeroStonePbr.emissiveMap = stoneTex;
            this.mats.atelierNeroStonePbr.needsUpdate = true;
            fbNero.dispose();
        });
    }

    _optimizeStaticWorldFor60Fps() {
        if (!this.scene) return;
        this.scene.updateMatrixWorld(true);

        const protectedSet = new Set();
        const protect = (obj) => { if (obj) protectedSet.add(obj); };

        protect(this.rig?.root);
        protect(this.rig?.vitrineGem);
        protect(this.vaseGroup);
        protect(this.egItemsGroup);
        protect(this.ogItemsGroup);
        protect(this.ogRoofClosedGroup);
        protect(this.egWeatherGroup);
        protect(this.ogWeatherGroup);
        protect(this.ogSouthWeatherGroup);
        protect(this.upstairsFxGroup);
        protect(this.lawnMowerGroup);
        protect(this.cellarLiftPlatform);
        protect(this.cellarGroup);
        protect(this.cellarDomeShellGroup);
        protect(this.cellarHoloGroup);
        protect(this.cellarDomeMesh);
        protect(this.cellarBridgeGroup);

        for (const g of Object.values(this.levelGroups || {})) protect(g);
        for (const g of Object.values(this.occasionGroups || {})) protect(g);
        for (const g of Object.values(this.gardenSeasonGroups || {})) protect(g);
        for (const g of Object.values(this.gardenOccasionGroups || {})) protect(g);
        for (const m of (this.sunbeamMeshes || [])) protect(m);
        for (const m of (this.cellarPickMeshes || [])) protect(m);

        // Split Obergeschoss into North Rooms (z < 2.12, culled when inside Atelier!) + South Atelier
        if (this.ogGroup && !this.ogNorthStaticGroup) {
            this.ogNorthStaticGroup = new THREE.Group();
            this.ogNorthStaticGroup.matrixAutoUpdate = false;
            this.ogGroup.add(this.ogNorthStaticGroup);
            protect(this.ogNorthStaticGroup);
        }

        batchStaticSubtree(this.egGroup, protectedSet);
        batchStaticSubtree(this.stairsGroup, protectedSet);
        batchStaticSubtree(this.ogGroup, protectedSet, this.ogNorthStaticGroup, 2.12);
        batchStaticSubtree(this.ogRoofClosedGroup, protectedSet);

        for (const g of Object.values(this.levelGroups || {})) batchStaticSubtree(g, protectedSet);
        for (const g of Object.values(this.occasionGroups || {})) batchStaticSubtree(g, protectedSet);

        batchStaticSubtree(this.gardenGroup, protectedSet);
        for (const g of Object.values(this.gardenSeasonGroups || {})) batchStaticSubtree(g, protectedSet);
        for (const g of Object.values(this.gardenOccasionGroups || {})) batchStaticSubtree(g, protectedSet);

        batchStaticSubtree(this.cellarShaftGroup, protectedSet);
        batchStaticSubtree(this.cellarBridgeGroup, protectedSet);
        batchStaticSubtree(this.cellarDomeStaticGroup, protectedSet);

        this.scene.updateMatrixWorld(true);
    }

    resize(force = false) {
        if (!this.renderer || !this.canvas || !this.camera) return;
        const rect = this.canvas.parentElement
            ? this.canvas.parentElement.getBoundingClientRect()
            : { width: window.innerWidth, height: window.innerHeight };
        const cssW = Math.max(320, Math.floor(rect.width || window.innerWidth));
        const cssH = Math.max(240, Math.floor(rect.height || window.innerHeight));

        const dpr = Math.min(window.devicePixelRatio || 1, 1.5);
        const maxGpuW = this.isLowPowerGpu ? 1472 : 2048;
        const resolutionCapScale = maxGpuW / cssW;
        const baseScale = this.isLowPowerGpu
            ? Math.min(1.0, this.renderScale, resolutionCapScale * (this.renderScale / 0.80))
            : (cssW > 1920 ? Math.min(1.0, this.renderScale, resolutionCapScale) : Math.min(dpr, this.renderScale * 1.09));

        if (!force && cssW === this._lastPixelW && cssH === this._lastPixelH && Math.abs(baseScale - this._lastAppliedScale) < 0.005) {
            return;
        }
        this._lastPixelW = cssW;
        this._lastPixelH = cssH;
        this._lastAppliedScale = baseScale;

        this.renderer.setPixelRatio(baseScale);
        this.renderer.setSize(cssW, cssH, false);
        this.camera.aspect = cssW / cssH;
        this.camera.updateProjectionMatrix();
    }

    start() {
        if (!this.renderer || this.running) return;
        this.running = true;
        this.resize(false);
        const now = performance.now();
        this.lastMs = now;
        this._fpsFrames = 0;
        this._fpsWindowStartMs = now;
        this._lowFpsStreaks = 0;
        this._highFpsStreaks = 0;
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

    _isOnCellarStairs() {
        return this.targetFloor === 'cellar_stairs' || (this.pos.y < -0.08 && this.pos.y > (UG_Y + 0.08));
    }

    _currentFloorIndex() {
        if (this.pos.y <= (UG_Y * 0.5)) return -1;
        return this.pos.y >= (OG_Y * 0.5) ? 1 : 0;
    }

    _roomNameAt(x, y, z) {
        if (y < -0.12 && y > UG_Y + 0.12) return 'Kuppel-Lift';
        if (y <= UG_Y * 0.5) {
            if (z < 2.08) return 'Kuppel-Vorhalle';
            if (z < 7.36) return 'Kuppel-Brücke';
            return 'Projekt-Gehirn Kuppelhalle';
        }
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
        if (floorIdx === -1) {
            return { minX: -1.95, maxX: 3.25, minZ: -1.00, maxZ: 11.15 };
        }
        if (floorIdx === 1) {
            // Respect 1.25m Dachschrägen eaves left & right (usable standing width x = -3.00 .. +3.00)
            return { minX: -2.62, maxX: 2.62, minZ: -6.48, maxZ: 6.38 };
        }
        // Floor 0 encompasses EG House (z = -6.48 .. +6.84) + Outdoor Terrasse & Garten (z = +7.04 .. +12.82)
        return { minX: -5.22, maxX: 5.22, minZ: -6.48, maxZ: 12.82 };
    }

    _getFloorColliders(floorIdx) {
        if (floorIdx === -1) {
            return [
                // Arrival Balcony West & North Chasm Guards (Lift shaft sits at x = 2.86, z = -0.73)
                { minX: -2.60, maxX: -0.72, minZ: -1.25, maxZ:  2.08 },
                { minX: -0.75, maxX:  2.39, minZ: -1.25, maxZ:  0.18 },
                { minX:  3.33, maxX:  3.80, minZ: -1.25, maxZ:  2.08 },
                // Abyss & Railing Guard Left of Suspended Walkway Bridge (z = 2.08 .. 7.36)
                { minX: -2.60, maxX: -0.58, minZ:  2.08, maxZ:  7.36 },
                // Abyss & Railing Guard Right of Suspended Walkway Bridge (z = 2.08 .. 7.36)
                { minX:  0.58, maxX:  3.80, minZ:  2.08, maxZ:  7.36 },
                // Central 24K-Gold & Nero-Marquina Neural Projector Console on Circular Kuppel Island (z = 9.40)
                { minX: -0.68, maxX:  0.68, minZ:  8.72, maxZ: 10.08 },
                // Circular Island Curved Perimeter Guards (keeps Funkira safely inside R = 2.35m platform)
                { minX: -2.60, maxX: -1.52, minZ:  7.36, maxZ:  8.00 },
                { minX:  1.52, maxX:  3.80, minZ:  7.36, maxZ:  8.00 },
                { minX:  2.05, maxX:  3.80, minZ:  8.00, maxZ: 10.45 },
                { minX: -2.60, maxX: -1.48, minZ: 10.45, maxZ: 11.40 },
                { minX:  1.48, maxX:  3.80, minZ: 10.45, maxZ: 11.40 }
            ];
        }
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
        if (floorIdx === -1) {
            return [
                { x:  2.86, z: -0.73 },
                { x:  2.86, z:  0.92 },
                { x:  0.00, z:  1.35 },
                { x:  0.00, z:  4.75 },
                { x:  0.00, z:  7.62 },
                { x: -1.18, z:  8.48 },
                { x:  1.18, z:  8.48 },
                { x: -1.20, z:  9.85 },
                { x:  1.20, z:  9.85 }
            ];
        }
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
                // Heizungsraum, Hydraulic Vault Lift & doorway (z = -0.06, x = +1.87)
                { x:  1.56, z:  0.34 },
                { x:  1.87, z:  0.34 },
                { x:  1.87, z: -0.73 },
                { x:  2.86, z: -0.73 },
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
        if (this._isOnCellarStairs()) {
            this.pos.x = clamp(this.pos.x, 2.66, 3.06);
            this.pos.z = clamp(this.pos.z, -0.92, -0.54);
            this.pos.y = clamp(this.pos.y, UG_Y, 0.0);
            return;
        }
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
        this.pos.y = fIdx === 1 ? OG_Y : (fIdx === -1 ? UG_Y : 0.0);
    }

    _solveSingleFloorPath(sx, sz, gx, gz, floorIdx) {
        const boxes = this._getFloorColliders(floorIdx);
        const start = this._pushOutOfColliders(sx, sz, floorIdx, 0.25, boxes);
        const goal = this._pushOutOfColliders(gx, gz, floorIdx, 0.25, boxes);
        const fy = floorIdx === 1 ? OG_Y : (floorIdx === -1 ? UG_Y : 0.0);

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
        const goalFloor = destFloor === -1 ? -1 : (destFloor === 0 ? 0 : 1);
        const STAIR_BOT  = { x: 3.16, z:  1.06 };
        const STAIR_TOP  = { x: 0.22, z:  1.06 };
        const CELLAR_LIFT = { x: 2.86, z: -0.73 };
        const CELLAR_EXIT = { x: 2.86, z:  0.92 };
        const EG_LIFT_APPROACH = { x: 1.87, z: -0.73 };

        let allPts = [];

        if (this._isOnCellarStairs()) {
            if (goalFloor === -1) {
                allPts.push(
                    { x: CELLAR_LIFT.x, y: UG_Y, z: CELLAR_LIFT.z, floor: 'cellar_stairs' },
                    { x: CELLAR_EXIT.x, y: UG_Y, z: CELLAR_EXIT.z, floor: -1 },
                    ...this._solveSingleFloorPath(CELLAR_EXIT.x, CELLAR_EXIT.z, destX, destZ, -1)
                );
            } else if (goalFloor === 0) {
                allPts.push(
                    { x: CELLAR_LIFT.x, y: 0.0, z: CELLAR_LIFT.z, floor: 'cellar_stairs' },
                    { x: EG_LIFT_APPROACH.x, y: 0.0, z: EG_LIFT_APPROACH.z, floor: 0 },
                    ...this._solveSingleFloorPath(EG_LIFT_APPROACH.x, EG_LIFT_APPROACH.z, destX, destZ, 0)
                );
            } else {
                allPts.push(
                    { x: CELLAR_LIFT.x, y: 0.0, z: CELLAR_LIFT.z, floor: 'cellar_stairs' },
                    { x: EG_LIFT_APPROACH.x, y: 0.0, z: EG_LIFT_APPROACH.z, floor: 0 },
                    ...this._solveSingleFloorPath(EG_LIFT_APPROACH.x, EG_LIFT_APPROACH.z, STAIR_BOT.x, STAIR_BOT.z, 0),
                    { x: 2.78, y: 0.0,  z: 1.06, floor: 'stairs' },
                    { x: 0.76, y: OG_Y, z: 1.06, floor: 'stairs' },
                    { x: STAIR_TOP.x, y: OG_Y, z: STAIR_TOP.z, floor: 1 },
                    ...this._solveSingleFloorPath(STAIR_TOP.x, STAIR_TOP.z, destX, destZ, 1)
                );
            }
        } else if (this._isOnStairs()) {
            // Finish stairs directly toward goalFloor landing, then route on goalFloor
            if (goalFloor === 1) {
                allPts.push(
                    { x: 0.76, y: OG_Y, z: 1.06, floor: 'stairs' },
                    { x: STAIR_TOP.x, y: OG_Y, z: STAIR_TOP.z, floor: 1 },
                    ...this._solveSingleFloorPath(STAIR_TOP.x, STAIR_TOP.z, destX, destZ, 1)
                );
            } else if (goalFloor === 0) {
                allPts.push(
                    { x: 2.78, y: 0.0, z: 1.06, floor: 'stairs' },
                    { x: STAIR_BOT.x, y: 0.0, z: STAIR_BOT.z, floor: 0 },
                    ...this._solveSingleFloorPath(STAIR_BOT.x, STAIR_BOT.z, destX, destZ, 0)
                );
            } else {
                allPts.push(
                    { x: 2.78, y: 0.0, z: 1.06, floor: 'stairs' },
                    { x: STAIR_BOT.x, y: 0.0, z: STAIR_BOT.z, floor: 0 },
                    ...this._solveSingleFloorPath(STAIR_BOT.x, STAIR_BOT.z, CELLAR_LIFT.x, CELLAR_LIFT.z, 0),
                    { x: CELLAR_LIFT.x, y: UG_Y, z: CELLAR_LIFT.z, floor: 'cellar_stairs' },
                    { x: CELLAR_EXIT.x, y: UG_Y, z: CELLAR_EXIT.z, floor: -1 },
                    ...this._solveSingleFloorPath(CELLAR_EXIT.x, CELLAR_EXIT.z, destX, destZ, -1)
                );
            }
        } else {
            const curFloor = this._currentFloorIndex();
            const safeNow = this._pushOutOfColliders(this.pos.x, this.pos.z, curFloor, 0.24);
            this.pos.x = safeNow.x;
            this.pos.z = safeNow.z;
            this.pos.y = curFloor === 1 ? OG_Y : (curFloor === -1 ? UG_Y : 0.0);

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
            } else if (curFloor === 1 && goalFloor === 0) {
                allPts = [
                    ...this._solveSingleFloorPath(this.pos.x, this.pos.z, STAIR_TOP.x, STAIR_TOP.z, 1),
                    { x: 0.76, y: OG_Y, z: 1.06, floor: 'stairs' },
                    { x: 2.78, y: 0.0,  z: 1.06, floor: 'stairs' },
                    { x: STAIR_BOT.x, y: 0.0, z: STAIR_BOT.z, floor: 0 },
                    ...this._solveSingleFloorPath(STAIR_BOT.x, STAIR_BOT.z, destX, destZ, 0)
                ];
            } else if (curFloor === 0 && goalFloor === -1) {
                allPts = [
                    ...this._solveSingleFloorPath(this.pos.x, this.pos.z, CELLAR_LIFT.x, CELLAR_LIFT.z, 0),
                    { x: CELLAR_LIFT.x, y: UG_Y, z: CELLAR_LIFT.z, floor: 'cellar_stairs' },
                    { x: CELLAR_EXIT.x, y: UG_Y, z: CELLAR_EXIT.z, floor: -1 },
                    ...this._solveSingleFloorPath(CELLAR_EXIT.x, CELLAR_EXIT.z, destX, destZ, -1)
                ];
            } else if (curFloor === 1 && goalFloor === -1) {
                allPts = [
                    ...this._solveSingleFloorPath(this.pos.x, this.pos.z, STAIR_TOP.x, STAIR_TOP.z, 1),
                    { x: 0.76, y: OG_Y, z: 1.06, floor: 'stairs' },
                    { x: 2.78, y: 0.0,  z: 1.06, floor: 'stairs' },
                    { x: STAIR_BOT.x, y: 0.0, z: STAIR_BOT.z, floor: 0 },
                    ...this._solveSingleFloorPath(STAIR_BOT.x, STAIR_BOT.z, CELLAR_LIFT.x, CELLAR_LIFT.z, 0),
                    { x: CELLAR_LIFT.x, y: UG_Y, z: CELLAR_LIFT.z, floor: 'cellar_stairs' },
                    { x: CELLAR_EXIT.x, y: UG_Y, z: CELLAR_EXIT.z, floor: -1 },
                    ...this._solveSingleFloorPath(CELLAR_EXIT.x, CELLAR_EXIT.z, destX, destZ, -1)
                ];
            } else if (curFloor === -1 && goalFloor === 0) {
                allPts = [
                    ...this._solveSingleFloorPath(this.pos.x, this.pos.z, CELLAR_EXIT.x, CELLAR_EXIT.z, -1),
                    { x: CELLAR_LIFT.x, y: UG_Y, z: CELLAR_LIFT.z, floor: -1 },
                    { x: CELLAR_LIFT.x, y: 0.0,  z: CELLAR_LIFT.z, floor: 'cellar_stairs' },
                    { x: EG_LIFT_APPROACH.x, y: 0.0, z: EG_LIFT_APPROACH.z, floor: 0 },
                    ...this._solveSingleFloorPath(EG_LIFT_APPROACH.x, EG_LIFT_APPROACH.z, destX, destZ, 0)
                ];
            } else {
                allPts = [
                    ...this._solveSingleFloorPath(this.pos.x, this.pos.z, CELLAR_EXIT.x, CELLAR_EXIT.z, -1),
                    { x: CELLAR_LIFT.x, y: UG_Y, z: CELLAR_LIFT.z, floor: -1 },
                    { x: CELLAR_LIFT.x, y: 0.0,  z: CELLAR_LIFT.z, floor: 'cellar_stairs' },
                    { x: EG_LIFT_APPROACH.x, y: 0.0, z: EG_LIFT_APPROACH.z, floor: 0 },
                    ...this._solveSingleFloorPath(EG_LIFT_APPROACH.x, EG_LIFT_APPROACH.z, STAIR_BOT.x, STAIR_BOT.z, 0),
                    { x: 2.78, y: 0.0,  z: 1.06, floor: 'stairs' },
                    { x: 0.76, y: OG_Y, z: 1.06, floor: 'stairs' },
                    { x: STAIR_TOP.x, y: OG_Y, z: STAIR_TOP.z, floor: 1 },
                    ...this._solveSingleFloorPath(STAIR_TOP.x, STAIR_TOP.z, destX, destZ, 1)
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
            'lawn_mower': 'lawn_mower', 'mower': 'lawn_mower', 'rasenmaeher': 'lawn_mower', 'rasenmäher': 'lawn_mower', 'maeher': 'lawn_mower', 'mäher': 'lawn_mower',
            'secret_cellar': 'secret_cellar', 'cellar': 'secret_cellar', 'geheimkeller': 'secret_cellar', 'keller': 'secret_cellar', 'error_room': 'secret_cellar', 'fehlerraum': 'secret_cellar', 'analyse_keller': 'secret_cellar', 'analysekeller': 'secret_cellar', 'projekt_gehirn': 'secret_cellar', 'fehlerlabor': 'secret_cellar'
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

            if (this._isOnCellarStairs()) {
                gFloor = this.pos.y <= UG_Y * 0.5 ? -1 : 0;
                gx = gFloor === -1 ? 2.15 : 1.96;
                gz = gFloor === -1 ? 2.00 : -0.68;
            } else if (this._isOnStairs()) {
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
            const CELLAR_LIFT = { x: 2.86, z: -0.73 };
            const CELLAR_EXIT = { x: 2.86, z:  0.92 };
            const EG_LIFT_APPROACH = { x: 1.87, z: -0.73 };
            let pts = [];
            if (this._isOnCellarStairs() || curFloor === -1) {
                const startOnCellar = !this._isOnCellarStairs();
                pts = [
                    ...(startOnCellar ? [
                        ...this._solveSingleFloorPath(this.pos.x, this.pos.z, CELLAR_EXIT.x, CELLAR_EXIT.z, -1),
                        { x: CELLAR_LIFT.x, y: UG_Y, z: CELLAR_LIFT.z, floor: -1 }
                    ] : []),
                    { x: CELLAR_LIFT.x, y: 0.0, z: CELLAR_LIFT.z, floor: 'cellar_stairs' },
                    { x: EG_LIFT_APPROACH.x, y: 0.0, z: EG_LIFT_APPROACH.z, floor: 0 },
                    ...this._solveSingleFloorPath(EG_LIFT_APPROACH.x, EG_LIFT_APPROACH.z, STAIR_BOT.x, STAIR_BOT.z, 0),
                    { x: 2.78, y: 0.0, z: 1.06, floor: 'stairs' },
                    { x: 1.77, y: OG_Y * 0.5, z: 1.06, floor: 'stairs' }
                ];
            } else if (this._isOnStairs()) {
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
            utility_room:     { x:  1.87, z: -0.73, floor: 0, focus: 'auto',              yaw: null },
            hallway:          { x:  3.16, z:  0.34, floor: 0, focus: 'auto',              yaw: null },
            og_studio:        { x: -1.45, z: -5.00, floor: 1, focus: 'auto',              yaw: null },
            og_bathroom:      { x:  1.32, z: -2.35, floor: 1, focus: 'auto',              yaw: null },
            og_gallery:       { x: -0.65, z:  1.95, floor: 1, focus: 'auto',              yaw: null },
            terrace:          { x: -0.72, z:  8.32, floor: 0, focus: 'closeup_garden',    yaw: null,            emo: 'happy',   gest: 'hands_on_hips', durMs: 8000 },
            garden:           { x: -0.35, z: 10.65, floor: 0, focus: 'closeup_garden',    yaw: null,            emo: 'happy',   gest: 'wave',          durMs: 8500 },
            hochbeet:         { x:  2.02, z:  8.60, floor: 0, focus: 'closeup_garden',    yaw: Math.PI * 0.5,   emo: 'sweet',   gest: 'hands_on_hips', durMs: 9000 },
            flowerbed:        { x: -2.45, z:  9.98, floor: 0, focus: 'closeup_garden',    yaw: 0.0,             emo: 'happy',   gest: 'hands_on_hips', durMs: 9000 },
            snowman:          { x: -2.62, z:  9.55, floor: 0, focus: 'closeup_garden',    yaw: -Math.PI * 0.42, emo: 'excited', gest: 'wave',          durMs: 8500 },
            lawn_mower:       { x:  2.18, z:  9.95, floor: 0, focus: 'closeup_garden',    yaw: Math.PI * 0.5,   emo: 'proud',   gest: 'point_left',    durMs: 8500 },
            secret_cellar:    { x: -1.18, z:  8.48, floor: -1, focus: 'closeup_cellar',   yaw: null,            emo: 'curious', gest: 'point_left',    durMs: 9500 }
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
            (this.upstairsFxGroup || this.scene).add(m);
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
        this.upstairsFxGroup = new THREE.Group();
        this.upstairsFxGroup.matrixAutoUpdate = true;
        this.scene.add(this.upstairsFxGroup);

        const goldMat = this.mats.atelierGoldPbr || this.mats.gold24k;
        const neroStoneMat = this.mats.atelierNeroStonePbr || this.mats.obsidian;
        const boiserieMat = this.mats.atelierBoiseriePbr || this.mats.wallPlaster;
        const bathStoneMat = this.mats.atelierBathStonePbr || this.mats.marbleCream;
        const glassPbrMat = this.mats.atelierGlassPbr || this.mats.crystalGlass;
        const woodDarkMat = this.mats.woodDark;
        const oakMat = this.mats.oakWarm;
        const cognacMat = this.mats.cognacLeather;
        const obsidianMat = this.mats.obsidian;
        const whiteMat = this.mats.ceramicWhite;
        const frameShadowMat = new THREE.MeshBasicMaterial({
            map: makeGalleryFrameShadowTexture(),
            transparent: true,
            depthWrite: false,
            polygonOffset: true,
            polygonOffsetFactor: -1,
            polygonOffsetUnits: -1
        });

        paintScenicWindowCanvas(this.windowCanvas, this.worldState.season, this.worldState.isLateNight, this.worldState.occasion);
        this.windowTexture = configureHdTexture(new THREE.CanvasTexture(this.windowCanvas), 4);
        const skyMat = new THREE.MeshBasicMaterial({ map: this.windowTexture });
        const deskScreenMat = new THREE.MeshBasicMaterial({
            map: makeDeskMonitorScreenTexture(),
            polygonOffset: true,
            polygonOffsetFactor: -2,
            polygonOffsetUnits: -2
        });

        const addDeskMonitor = (parentGrp, x, surfY, z, rotY) => {
            const mg = new THREE.Group();
            mg.position.set(x, surfY, z);
            mg.rotation.y = rotY;
            const base = new THREE.Mesh(new RoundedBoxGeometry(0.24, 0.014, 0.14, 1, 0.004), goldMat);
            base.position.set(0, 0.007, 0);
            const neck = new THREE.Mesh(new RoundedBoxGeometry(0.036, 0.15, 0.026, 1, 0.005), goldMat);
            neck.position.set(0, 0.08, -0.025);
            const bezel = new THREE.Mesh(new RoundedBoxGeometry(0.62, 0.34, 0.022, 2, 0.01), obsidianMat);
            bezel.position.set(0, 0.24, 0);
            const goldTrim = new THREE.Mesh(new RoundedBoxGeometry(0.628, 0.014, 0.024, 1, 0.004), goldMat);
            goldTrim.position.set(0, 0.068, 0.001);
            const screen = new THREE.Mesh(new THREE.PlaneGeometry(0.588, 0.308), deskScreenMat);
            screen.position.set(0, 0.242, 0.022);
            const kbd = new THREE.Mesh(new RoundedBoxGeometry(0.32, 0.010, 0.11, 1, 0.003), obsidianMat);
            kbd.position.set(0, 0.006, 0.16);
            mg.add(base, neck, bezel, goldTrim, screen, kbd);
            parentGrp.add(mg);
        };

        this.weatherMat = new THREE.MeshBasicMaterial({ color: 0xE6A145, side: THREE.DoubleSide });
        const wpGeo = new THREE.PlaneGeometry(0.038, 0.038);

        this.egWeatherGroup = new THREE.Group();
        this.ogWeatherGroup = new THREE.Group();
        this.ogSouthWeatherGroup = new THREE.Group();
        this.scene.add(this.egWeatherGroup, this.ogWeatherGroup, this.ogSouthWeatherGroup);

        // Helper: Build framed architectural window (24K Gold PBR mullions + Nero-Marquina sill + PBR glass reflection)
        const addFramedWindow = (parentGrp, weatherGrp, x, y, z, w, h, rotY) => {
            const wg = new THREE.Group();
            wg.position.set(x, y, z);
            wg.rotation.y = rotY;

            const frame = new THREE.Mesh(new RoundedBoxGeometry(w + 0.12, h + 0.12, 0.08, 2, 0.015), goldMat);
            const pane = new THREE.Mesh(new THREE.PlaneGeometry(w, h), skyMat);
            pane.position.z = 0.044;
            const glassSheen = new THREE.Mesh(new THREE.PlaneGeometry(w, h), glassPbrMat);
            glassSheen.position.z = 0.048;
            const mullV = new THREE.Mesh(new RoundedBoxGeometry(0.030, h, 0.03, 1, 0.006), goldMat);
            mullV.position.z = 0.056;
            const mullH = new THREE.Mesh(new RoundedBoxGeometry(w, 0.030, 0.03, 1, 0.006), goldMat);
            mullH.position.set(0, -h * 0.08, 0.056);
            const sill = new THREE.Mesh(new RoundedBoxGeometry(w + 0.22, 0.048, 0.17, 1, 0.008), neroStoneMat);
            sill.position.set(0, -h * 0.5 - 0.038, 0.052);
            const sillGoldLip = new THREE.Mesh(new RoundedBoxGeometry(w + 0.23, 0.014, 0.176, 1, 0.004), goldMat);
            sillGoldLip.position.set(0, -h * 0.5 - 0.064, 0.052);
            wg.add(frame, pane, glassSheen, mullV, mullH, sill, sillGoldLip);
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

        // Helper: Build architectural Parisian Boiserie wall segment with Nero-Marquina & 24K Gold crown
        const addWallBox = (grp, x1, z1, x2, z2, yBase, height, thickness = 0.12, mat = boiserieMat, capMat = neroStoneMat) => {
            const cx = (x1 + x2) * 0.5;
            const cz = (z1 + z2) * 0.5;
            const sx = Math.max(thickness, Math.abs(x2 - x1));
            const sz = Math.max(thickness, Math.abs(z2 - z1));
            const wallGeo = (mat === boiserieMat)
                ? createBoiserieWallBoxGeo(sx, height, sz)
                : new RoundedBoxGeometry(sx, height, sz, 1, 0.01);
            const wall = new THREE.Mesh(wallGeo, mat);
            wall.position.set(cx, yBase + height * 0.5, cz);
            const cap = new THREE.Mesh(new RoundedBoxGeometry(sx + 0.022, 0.028, sz + 0.022, 1, 0.005), capMat);
            cap.position.set(cx, yBase + height + 0.014, cz);
            const goldBead = new THREE.Mesh(new THREE.BoxGeometry(sx + 0.026, 0.010, sz + 0.026), goldMat);
            goldBead.position.set(cx, yBase + height + 0.002, cz);
            grp.add(wall, cap, goldBead);
        };

        // Helper: White ribbed Heizkoerper radiator
        const addRadiator = (grp, x, yBase, z, length, alongZ = true) => {
            const sx = alongZ ? 0.11 : length;
            const sz = alongZ ? length : 0.11;
            const body = new THREE.Mesh(new RoundedBoxGeometry(sx, 0.58, sz, 2, 0.02), whiteMat);
            body.position.set(x, yBase + 0.36, z);
            const topGrill = new THREE.Mesh(new RoundedBoxGeometry(sx + 0.01, 0.03, sz + 0.01, 1, 0.005), this.mats.silverSteel);
            topGrill.position.set(x, yBase + 0.66, z);
            grp.add(body, topGrill);
        };

        // Shared High-DPI Schwarz-Gold MEIN SEELENFUNKE Plaque Materials (Zero duplicate Canvas allocations + anti-Z-fighting offset!)
        const makePlaqueMat = (mainText, subText, theme) => new THREE.MeshBasicMaterial({
            map: makeSeelenfunkePlaqueTexture(mainText, subText, theme),
            polygonOffset: true,
            polygonOffsetFactor: -3,
            polygonOffsetUnits: -3
        });
        this.plaqueMats = {
            obsidianMain: makePlaqueMat('MEIN SEELENFUNKE', 'MANUFAKTUR GIFHORN', 'obsidian'),
            goldMain:     makePlaqueMat('MEIN SEELENFUNKE', 'HANDVEREDELT · 24K', 'gold'),
            team:         makePlaqueMat('MEIN SEELENFUNKE', 'DAS GRÜNDER-TEAM', 'obsidian'),
            trophy:       makePlaqueMat('MEIN SEELENFUNKE', 'EXZELLENZ-POKAL', 'obsidian'),
            flowers:      makePlaqueMat('MEIN SEELENFUNKE', 'BLÜTENZAUBER · 24K', 'obsidian'),
            garden:       makePlaqueMat('MEIN SEELENFUNKE', 'GOLDENER GARTEN', 'obsidian'),
            mowerObs:     makePlaqueMat('SEELENFUNKE', 'GOLD EDITION · 24K', 'obsidian'),
            mowerGold:    makePlaqueMat('SEELENFUNKE', 'MANUFAKTUR GARTEN', 'gold')
        };

        // Helper: Framed Schwarz-Gold Gallery Wall Artwork signed "MEIN SEELENFUNKE" (stepped >= 12mm Z-layers + polygonOffset)
        const addGoldenSignedWallArt = (parentGrp, x, y, z, w, h, rotY, motif, subLabel) => {
            const ag = new THREE.Group();
            ag.position.set(x, y, z);
            ag.rotation.y = rotY;
            const shad = new THREE.Mesh(new THREE.PlaneGeometry(w + 0.28, h + 0.28), frameShadowMat);
            shad.position.set(0, -0.02, -0.014);
            const outer = new THREE.Mesh(new RoundedBoxGeometry(w + 0.09, h + 0.09, 0.038, 2, 0.014), goldMat);
            const matte = new THREE.Mesh(new RoundedBoxGeometry(w + 0.02, h + 0.02, 0.048, 1, 0.008), neroStoneMat);
            const canvasMesh = new THREE.Mesh(
                new THREE.PlaneGeometry(w, h),
                new THREE.MeshBasicMaterial({
                    map: makeSeelenfunkeArtCanvasTexture(motif, subLabel),
                    polygonOffset: true,
                    polygonOffsetFactor: -2,
                    polygonOffsetUnits: -2
                })
            );
            canvasMesh.position.z = 0.036;
            const namePlate = new THREE.Mesh(new THREE.PlaneGeometry(Math.min(w * 0.56, 0.44), 0.075), this.plaqueMats.obsidianMain);
            namePlate.position.set(0, -h * 0.5 - 0.022, 0.048);
            ag.add(shad, outer, matte, canvasMesh, namePlate);
            parentGrp.add(ag);
        };

        // ====================================================================
        // 1. ERDGESCHOSS (y = 0.00m, 8.50m x 14.00m)
        // ====================================================================
        const eg = new THREE.Group();
        this.egGroup = eg;

        // Split EG Floor & Slab around the Hydraulic Vault Lift Shaft Opening (x = 2.39 .. 3.33, z = -1.20 .. -0.26)
        // Seamless world-space French Herringbone Oak Parquet (/assets/atelier_herringbone_oak.jpg) + UV1 Baked AO!
        const egFloorMat = this.mats.atelierFloorEgPbr;
        const addEgFloorPatch = (x0, z0, x1, z1) => {
            const pw = x1 - x0;
            const pd = z1 - z0;
            const geo = new THREE.PlaneGeometry(pw, pd);
            const uv = geo.attributes.uv;
            const u0 = (x0 + 4.25) / PARQUET_TILE_M;
            const u1 = (x1 + 4.25) / PARQUET_TILE_M;
            const vTop = (7.00 - z0) / PARQUET_TILE_M;
            const vBot = (7.00 - z1) / PARQUET_TILE_M;
            uv.setXY(0, u0, vTop);
            uv.setXY(1, u1, vTop);
            uv.setXY(2, u0, vBot);
            uv.setXY(3, u1, vBot);

            const uv1Arr = new Float32Array(8);
            const au0 = (x0 + 4.25) / HOUSE_W;
            const au1 = (x1 + 4.25) / HOUSE_W;
            const avTop = 1.0 - (z0 + 7.00) / HOUSE_L;
            const avBot = 1.0 - (z1 + 7.00) / HOUSE_L;
            uv1Arr[0] = au0; uv1Arr[1] = avTop;
            uv1Arr[2] = au1; uv1Arr[3] = avTop;
            uv1Arr[4] = au0; uv1Arr[5] = avBot;
            uv1Arr[6] = au1; uv1Arr[7] = avBot;
            geo.setAttribute('uv1', new THREE.BufferAttribute(uv1Arr, 2));

            const m = new THREE.Mesh(geo, egFloorMat);
            m.rotation.x = -Math.PI / 2;
            m.position.set((x0 + x1) * 0.5, 0.0, (z0 + z1) * 0.5);
            const under = new THREE.Mesh(new THREE.BoxGeometry(pw, 0.16, pd), neroStoneMat);
            under.position.set((x0 + x1) * 0.5, -0.085, (z0 + z1) * 0.5);
            eg.add(m, under);
        };
        addEgFloorPatch(-4.25, -7.00,  4.25, -1.20); // North half above Hydraulic Vault Lift
        addEgFloorPatch(-4.25, -1.20,  2.39, -0.26); // West section beside Hydraulic Vault Lift
        addEgFloorPatch( 3.33, -1.20,  4.25, -0.26); // East section beside Hydraulic Vault Lift
        addEgFloorPatch(-4.25, -0.26,  4.25,  7.00); // South half below Hydraulic Vault Lift

        // EG Grosses Badezimmer Calacatta Marble Floor Inlay + 24K Gold Threshold
        const egBathGeo = new THREE.PlaneGeometry(2.97, 2.43);
        egBathGeo.attributes.uv.setXY(0, 0, 1.52);
        egBathGeo.attributes.uv.setXY(1, 1.86, 1.52);
        egBathGeo.attributes.uv.setXY(2, 0, 0);
        egBathGeo.attributes.uv.setXY(3, 1.86, 0);
        const egBathFloor = new THREE.Mesh(egBathGeo, bathStoneMat);
        egBathFloor.rotation.x = -Math.PI / 2;
        egBathFloor.position.set(2.675, 0.003, -3.265);
        const egBathThresh = new THREE.Mesh(new THREE.BoxGeometry(0.04, 0.008, 1.02), goldMat);
        egBathThresh.position.set(1.19, 0.004, -3.15);
        eg.add(egBathFloor, egBathThresh);

        // EG Exterior Walls (North, West, East full 2.75m Parisian Boiserie; South low 0.34m cutaway plinth)
        const egExtH = 2.75;
        addWallBox(eg, -4.24, -6.94,  4.24, -6.94, 0, egExtH, 0.14, boiserieMat, neroStoneMat); // North
        addWallBox(eg, -4.18, -6.94, -4.18,  6.94, 0, egExtH, 0.14, boiserieMat, neroStoneMat); // West
        addWallBox(eg,  4.18, -6.94,  4.18,  0.22, 0, egExtH, 0.14, boiserieMat, neroStoneMat); // East upper
        addWallBox(eg,  4.18,  1.42,  4.18,  6.94, 0, egExtH, 0.14, boiserieMat, neroStoneMat); // East lower
        addWallBox(eg, -4.24,  6.94, -1.24,  6.94, 0, 0.34,   0.14, boiserieMat, neroStoneMat); // South cutaway sill West
        addWallBox(eg, -0.20,  6.94,  4.24,  6.94, 0, 0.34,   0.14, boiserieMat, neroStoneMat); // South cutaway sill East

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
        const bedRunner = new THREE.Mesh(new RoundedBoxGeometry(0.36, 0.242, 1.78, 2, 0.03), obsidianMat);
        bedRunner.position.set(-2.32, 0.442, -4.78);
        const bedRunnerPiping = new THREE.Mesh(new RoundedBoxGeometry(0.38, 0.244, 1.785, 2, 0.03), goldMat);
        bedRunnerPiping.position.set(-2.32, 0.440, -4.78);
        eg.add(bedFrame, bedHead, mattress, duvet, bedRunnerPiping, bedRunner);
        for (const pz of [-5.22, -4.34]) {
            const pillow = new THREE.Mesh(new RoundedBoxGeometry(0.38, 0.14, 0.62, 2, 0.04), goldMat);
            pillow.position.set(-3.68, 0.56, pz);
            pillow.rotation.z = -0.18;
            const accentPillow = new THREE.Mesh(new RoundedBoxGeometry(0.24, 0.12, 0.40, 2, 0.04), obsidianMat);
            accentPillow.position.set(-3.46, 0.57, pz);
            accentPillow.rotation.z = -0.24;
            eg.add(pillow, accentPillow);
        }
        for (const nz of [-5.96, -3.60]) {
            const ns = new THREE.Mesh(new RoundedBoxGeometry(0.42, 0.46, 0.42, 2, 0.02), oakMat);
            ns.position.set(-3.84, 0.23, nz);
            const nsLampBase = new THREE.Mesh(new THREE.CylinderGeometry(0.055, 0.07, 0.14, 10), goldMat);
            nsLampBase.position.set(-3.84, 0.53, nz);
            const nsLampShade = new THREE.Mesh(new THREE.CylinderGeometry(0.075, 0.11, 0.15, 12), this.mats.velvetCream);
            nsLampShade.position.set(-3.84, 0.67, nz);
            const nsLampBulb = new THREE.Mesh(new THREE.SphereGeometry(0.045, 8, 6), this.mats.warmBulb);
            nsLampBulb.position.set(-3.84, 0.62, nz);
            eg.add(ns, nsLampBase, nsLampShade, nsLampBulb);
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

        // --- EG Room 4: Büro (10.25 m² — Doppel-Schreibtische, Ultrawide-Monitore, 2 Stühle, Rollcontainer, Regal) ---
        for (const dx of [-2.74, -2.06]) {
            const dTop = new THREE.Mesh(new RoundedBoxGeometry(0.66, 0.05, 1.56, 2, 0.015), whiteMat);
            dTop.position.set(dx, 0.74, -1.30);
            const legA = new THREE.Mesh(new RoundedBoxGeometry(0.60, 0.72, 0.05, 1, 0.01), this.mats.silverSteel);
            legA.position.set(dx, 0.36, -2.02);
            const legB = legA.clone();
            legB.position.set(dx, 0.36, -0.58);
            eg.add(dTop, legA, legB);
        }
        addDeskMonitor(eg, -2.56, 0.765, -1.30, -Math.PI / 2);
        addDeskMonitor(eg, -2.24, 0.765, -1.30,  Math.PI / 2);
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

        // Open 24K-Gold Shaft Rim Bezel around the 0.94m x 0.94m Hydraulic Vault Lift (x = 2.86, z = -0.73)
        const rimBarH = new THREE.BoxGeometry(1.02, 0.022, 0.042);
        const rimBarV = new THREE.BoxGeometry(0.042, 0.022, 0.94);
        const rimN = new THREE.Mesh(rimBarH, goldMat); rimN.position.set(2.86, 0.011, -1.22);
        const rimS = new THREE.Mesh(rimBarH, goldMat); rimS.position.set(2.86, 0.011, -0.24);
        const rimW = new THREE.Mesh(rimBarV, goldMat); rimW.position.set(2.37, 0.011, -0.73);
        const rimE = new THREE.Mesh(rimBarV, goldMat); rimE.position.set(3.35, 0.011, -0.73);
        this.cellarHatchLedMat = new THREE.MeshBasicMaterial({ color: 0x10B981 });
        const hatchLed = new THREE.Mesh(new THREE.BoxGeometry(0.74, 0.026, 0.036), this.cellarHatchLedMat);
        hatchLed.position.set(2.86, 0.014, -0.205);
        eg.add(boiler, netRack, netLed, rimN, rimS, rimW, rimE, hatchLed);

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
        const tvFrame = new THREE.Mesh(new RoundedBoxGeometry(1.45, 0.85, 0.032, 1, 0.01), goldMat);
        tvFrame.position.set(-2.22, 1.02, 0.132);
        const tvScreen = new THREE.Mesh(new RoundedBoxGeometry(1.42, 0.82, 0.04, 1, 0.01), obsidianMat);
        tvScreen.position.set(-2.22, 1.02, 0.14);
        const tvUi = new THREE.Mesh(new THREE.PlaneGeometry(1.36, 0.76), deskScreenMat);
        tvUi.position.set(-2.22, 1.02, 0.163);
        eg.add(sofaWestSeat, sofaWestBack, sofaSouthSeat, sofaSouthBack, mediaLowboard, tvFrame, tvScreen, tvUi);

        // Plump Boutique Throw Cushions on Cognac L-Ecksofa (Zero walk-collider overlap!)
        const cushionGeo = new RoundedBoxGeometry(0.32, 0.30, 0.12, 2, 0.05);
        for (const [px, py, pz, ry, rz, cMat] of [
            [-3.70, 0.54, 1.48,  Math.PI * 0.48, -0.20, this.mats.velvetCream],
            [-3.68, 0.54, 2.38,  Math.PI * 0.52, -0.18, goldMat],
            [-3.64, 0.54, 3.36,  Math.PI * 0.26, -0.16, this.mats.velvetCream],
            [-2.62, 0.54, 3.58,  Math.PI + 0.08,  0.18, goldMat],
            [-1.78, 0.54, 3.56,  Math.PI - 0.12,  0.18, this.mats.velvetCream]
        ]) {
            const cush = new THREE.Mesh(cushionGeo, cMat);
            cush.position.set(px, py, pz);
            cush.rotation.set(0.16, ry, rz);
            eg.add(cush);
        }

        // Large Esstisch (Nero-Marquina Stone Top + 24K Gold Bezel) + 6 Stuehle
        const dineTop = new THREE.Mesh(new RoundedBoxGeometry(2.08, 0.065, 0.96, 2, 0.02), neroStoneMat);
        dineTop.position.set(-2.42, 0.76, 5.35);
        const dineTrim = new THREE.Mesh(new RoundedBoxGeometry(2.10, 0.018, 0.98, 1, 0.006), goldMat);
        dineTrim.position.set(-2.42, 0.742, 5.35);
        const dineRunner = new THREE.Mesh(new RoundedBoxGeometry(0.88, 0.015, 0.30, 1, 0.005), goldMat);
        dineRunner.position.set(-2.42, 0.80, 5.35);
        eg.add(dineTop, dineTrim, dineRunner);
        const dLegGeo = new THREE.CylinderGeometry(0.03, 0.022, 0.74, 10);
        for (const [lx, lz] of [[-3.34, 4.96], [-1.50, 4.96], [-3.34, 5.74], [-1.50, 5.74]]) {
            const dl = new THREE.Mesh(dLegGeo, goldMat);
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

        // --- EG Room 8: Kueche (14.97 m2: L-Zeile Ost/Sued, Kuehlschrank & Nord-Sued-Kuecheninsel mit Nero-Marquina Steinplatten) ---
        const kEastBase = new THREE.Mesh(new RoundedBoxGeometry(0.70, 0.88, 3.82, 2, 0.025), oakMat);
        kEastBase.position.set(3.72, 0.44, 4.92);
        const kEastTop = new THREE.Mesh(new RoundedBoxGeometry(0.74, 0.055, 3.86, 2, 0.02), neroStoneMat);
        kEastTop.position.set(3.72, 0.90, 4.92);
        const kEastTrim = new THREE.Mesh(new RoundedBoxGeometry(0.75, 0.016, 3.87, 1, 0.005), goldMat);
        kEastTrim.position.set(3.72, 0.875, 4.92);
        const kSouthBase = new THREE.Mesh(new RoundedBoxGeometry(2.15, 0.88, 0.70, 2, 0.025), oakMat);
        kSouthBase.position.set(2.32, 0.44, 6.48);
        const kSouthTop = new THREE.Mesh(new RoundedBoxGeometry(2.19, 0.055, 0.74, 2, 0.02), neroStoneMat);
        kSouthTop.position.set(2.32, 0.90, 6.48);
        const kSouthTrim = new THREE.Mesh(new RoundedBoxGeometry(2.20, 0.016, 0.75, 1, 0.005), goldMat);
        kSouthTrim.position.set(2.32, 0.875, 6.48);
        const kFridge = new THREE.Mesh(new RoundedBoxGeometry(0.96, 2.02, 0.74, 2, 0.035), neroStoneMat);
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
        eg.add(kEastBase, kEastTop, kEastTrim, kSouthBase, kSouthTop, kSouthTrim, kFridge, kFridgeHandle, kSink, kFaucet, espBody, espTop);
        for (const ez of [3.82, 3.96]) {
            const cup = new THREE.Mesh(new THREE.CylinderGeometry(0.028, 0.022, 0.052, 10), obsidianMat);
            cup.position.set(3.56, 0.955, ez);
            const saucer = new THREE.Mesh(new THREE.CylinderGeometry(0.046, 0.038, 0.010, 10), goldMat);
            saucer.position.set(3.56, 0.932, ez);
            eg.add(cup, saucer);
        }

        // Freestanding Vertical (North-South) Kuecheninsel (Nero-Marquina Stone Slab + 24K Gold Trim) + Stools
        const islBase = new THREE.Mesh(new RoundedBoxGeometry(0.88, 0.86, 1.86, 2, 0.03), neroStoneMat);
        islBase.position.set(0.86, 0.43, 4.45);
        const islTop = new THREE.Mesh(new RoundedBoxGeometry(0.98, 0.065, 1.96, 2, 0.025), neroStoneMat);
        islTop.position.set(0.86, 0.89, 4.45);
        const islTrim = new THREE.Mesh(new RoundedBoxGeometry(0.995, 0.020, 1.975, 1, 0.006), goldMat);
        islTrim.position.set(0.86, 0.862, 4.45);
        const islBowl = new THREE.Mesh(new THREE.CylinderGeometry(0.18, 0.09, 0.08, 16), goldMat);
        islBowl.position.set(0.86, 0.96, 4.45);
        eg.add(islBase, islTop, islTrim, islBowl);
        for (const [fx, fy, fz, fMat] of [
            [0.82, 1.015, 4.42, this.mats.scarfRed],
            [0.91, 1.015, 4.44, this.mats.sunflowerYellow],
            [0.85, 1.020, 4.50, this.mats.leafGreen]
        ]) {
            const fruit = new THREE.Mesh(new THREE.SphereGeometry(0.042, 8, 8), fMat);
            fruit.position.set(fx, fy, fz);
            eg.add(fruit);
        }
        for (const [sx, sz] of [[0.86, 3.28], [0.86, 5.62], [1.54, 4.45]]) {
            const stSeat = new THREE.Mesh(new RoundedBoxGeometry(0.38, 0.06, 0.38, 2, 0.02), cognacMat);
            stSeat.position.set(sx, 0.62, sz);
            const stStem = new THREE.Mesh(new THREE.CylinderGeometry(0.024, 0.024, 0.60, 8), goldMat);
            stStem.position.set(sx, 0.30, sz);
            eg.add(stSeat, stStem);
        }

        // --- EG Signed 24K Gold "MEIN SEELENFUNKE" Gallery Wall Paintings across All Rooms ---
        addGoldenSignedWallArt(eg, -4.08, 1.70, -4.78, 1.26, 0.84,  Math.PI / 2, 'moon_sparks',      'STERNENFUNKE · MANUFAKTUR'); // Schlafzimmer
        addGoldenSignedWallArt(eg, -4.08, 1.64, -2.28, 0.78, 0.60,  Math.PI / 2, 'crest_manufaktur', 'ATELIER GIFHORN');           // Buero
        addGoldenSignedWallArt(eg, -4.08, 1.70,  2.40, 1.54, 0.94,  Math.PI / 2, 'spark_mandala',    'MEIN SEELENFUNKE EDITION');  // Wohnzimmer
        addGoldenSignedWallArt(eg, -4.08, 1.68,  5.35, 1.22, 0.82,  Math.PI / 2, 'botanical_gold',   'GOLDENE BLÜTE · 24K');       // Esszimmer
        addGoldenSignedWallArt(eg,  2.52, 1.70, -6.84, 1.28, 0.84,  0.0,         'crest_manufaktur', 'FUNKIRA POWER · 24K');       // Trainingsraum
        addGoldenSignedWallArt(eg,  4.08, 1.66,  4.73, 0.68, 0.76, -Math.PI / 2, 'golden_heart',     'HERZENSFUNKE');              // Kueche

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
            const tread = new THREE.Mesh(new RoundedBoxGeometry(0.22, 0.055, 0.88, 1, 0.01), neroStoneMat);
            tread.position.set(sx, sy - 0.028, 1.06);
            const nose = new THREE.Mesh(new RoundedBoxGeometry(0.025, 0.058, 0.89, 1, 0.005), goldMat);
            nose.position.set(sx + 0.10, sy - 0.026, 1.06);
            stairs.add(tread, nose);
        }
        const stairLen = Math.hypot(2.02, OG_Y);
        const stairPitch = Math.atan2(OG_Y, 2.02);
        for (const sz of [0.60, 1.52]) {
            const stringer = new THREE.Mesh(new RoundedBoxGeometry(stairLen, 0.16, 0.06, 1, 0.01), neroStoneMat);
            stringer.position.set(1.77, OG_Y * 0.5 - 0.10, sz);
            stringer.rotation.z = -stairPitch;
            stairs.add(stringer);
        }
        this.scene.add(stairs);
        freezeStaticTree(stairs);

        // ====================================================================
        // 3. OBERGESCHOSS (y = 3.10m, 1.25m Dachschraegen + Seelenfunken Atelier)
        // ====================================================================
        const og = new THREE.Group();
        this.ogGroup = og;

        // Split OG Floor around the Stairwell Opening (x = +0.74 .. +2.88, z = +0.58 .. +1.54)
        // Seamless French Herringbone Oak Parquet (/assets/atelier_herringbone_oak.jpg) + Satin PBR + UV1 AO!
        const ogFloorMat = this.mats.atelierFloorOgPbr;
        const addOgFloorPatch = (x0, z0, x1, z1) => {
            const pw = x1 - x0;
            const pd = z1 - z0;
            const geo = new THREE.PlaneGeometry(pw, pd);
            const uv = geo.attributes.uv;
            const u0 = (x0 + 4.25) / PARQUET_TILE_M;
            const u1 = (x1 + 4.25) / PARQUET_TILE_M;
            const vTop = (7.00 - z0) / PARQUET_TILE_M;
            const vBot = (7.00 - z1) / PARQUET_TILE_M;
            uv.setXY(0, u0, vTop);
            uv.setXY(1, u1, vTop);
            uv.setXY(2, u0, vBot);
            uv.setXY(3, u1, vBot);

            const uv1Arr = new Float32Array(8);
            const au0 = (x0 + 4.25) / HOUSE_W;
            const au1 = (x1 + 4.25) / HOUSE_W;
            const avTop = 1.0 - (z0 + 7.00) / HOUSE_L;
            const avBot = 1.0 - (z1 + 7.00) / HOUSE_L;
            uv1Arr[0] = au0; uv1Arr[1] = avTop;
            uv1Arr[2] = au1; uv1Arr[3] = avTop;
            uv1Arr[4] = au0; uv1Arr[5] = avBot;
            uv1Arr[6] = au1; uv1Arr[7] = avBot;
            geo.setAttribute('uv1', new THREE.BufferAttribute(uv1Arr, 2));

            const m = new THREE.Mesh(geo, ogFloorMat);
            m.rotation.x = -Math.PI / 2;
            m.position.set((x0 + x1) * 0.5, OG_Y, (z0 + z1) * 0.5);
            const under = new THREE.Mesh(new THREE.BoxGeometry(pw, 0.10, pd), neroStoneMat);
            under.position.set((x0 + x1) * 0.5, OG_Y - 0.055, (z0 + z1) * 0.5);
            og.add(m, under);
        };
        addOgFloorPatch(-4.25, -7.00,  4.25,  0.58); // North half above stairwell
        addOgFloorPatch(-4.25,  0.58,  0.74,  1.54); // West gallery beside stairwell
        addOgFloorPatch( 2.88,  0.58,  4.25,  1.54); // East eaves beside stairwell
        addOgFloorPatch(-4.25,  1.54,  4.25,  7.00); // South half (Gelaender + Seelenfunken Atelier!)

        // OG-Bad Calacatta Marble Floor Inlay + 24K Gold Doorway Threshold
        const ogBathGeo = new THREE.PlaneGeometry(3.06, 4.11);
        ogBathGeo.attributes.uv.setXY(0, 0, 2.56);
        ogBathGeo.attributes.uv.setXY(1, 1.91, 2.56);
        ogBathGeo.attributes.uv.setXY(2, 0, 0);
        ogBathGeo.attributes.uv.setXY(3, 1.91, 0);
        const ogBathFloor = new THREE.Mesh(ogBathGeo, bathStoneMat);
        ogBathFloor.rotation.x = -Math.PI / 2;
        ogBathFloor.position.set(1.47, OG_Y + 0.003, -2.155);
        const ogBathThresh = new THREE.Mesh(new THREE.BoxGeometry(0.04, 0.008, 1.02), goldMat);
        ogBathThresh.position.set(-0.06, OG_Y + 0.004, -1.37);
        og.add(ogBathFloor, ogBathThresh);

        // Flush Nero-Marquina & 24K Gold Threshold Strip between OG Gallery and Seelenfunken Atelier (z = 2.28)
        const atelierThreshStone = new THREE.Mesh(new THREE.BoxGeometry(5.88, 0.006, 0.14), neroStoneMat);
        atelierThreshStone.position.set(0, OG_Y + 0.003, 2.28);
        const atelierThreshGoldN = new THREE.Mesh(new THREE.BoxGeometry(5.90, 0.008, 0.018), goldMat);
        atelierThreshGoldN.position.set(0, OG_Y + 0.004, 2.205);
        const atelierThreshGoldS = new THREE.Mesh(new THREE.BoxGeometry(5.90, 0.008, 0.018), goldMat);
        atelierThreshGoldS.position.set(0, OG_Y + 0.004, 2.355);
        og.add(atelierThreshStone, atelierThreshGoldN, atelierThreshGoldS);

        // --- DACHSCHRAEGEN ARCHITECTURE (Parisian Boiserie Knee-Walls 2.08m + Peak 4.10m!) ---
        const knieWallH = 2.08;
        const knieH = 1.68;
        addWallBox(og, -3.00, -6.92, -3.00,  6.90, OG_Y, knieWallH, 0.10, boiserieMat, neroStoneMat);
        addWallBox(og,  3.00, -6.92,  3.00,  6.90, OG_Y, knieWallH, 0.10, boiserieMat, neroStoneMat);

        // Sloped Eaves Roof Aprons (x = +-4.25 @ OG_Y+1.295 -> x = +-3.00 @ OG_Y+2.12) + Exposed Walnut & Gold Dachsparren
        const roofAngle = Math.atan2(4.10 - 2.12, 3.00);
        const eaveLen = Math.hypot(1.25, 0.825);
        for (const sx of [-1, 1]) {
            const eaveApron = new THREE.Mesh(new RoundedBoxGeometry(eaveLen, 0.08, 13.88, 1, 0.01), woodDarkMat);
            eaveApron.position.set(sx * 3.625, OG_Y + 1.708, 0);
            eaveApron.rotation.z = -sx * roofAngle;
            og.add(eaveApron);
        }

        // Exposed Walnut & 24K Gold Dachsparren Rafters (/ \) + Central Firstbalken Ridge Beam
        const rafterLen = Math.hypot(3.02, 1.993);
        const ridgeBeam = new THREE.Mesh(new RoundedBoxGeometry(0.16, 0.18, 13.88, 2, 0.02), woodDarkMat);
        ridgeBeam.position.set(0, OG_Y + 4.08, 0);
        const ridgeGold = new THREE.Mesh(new RoundedBoxGeometry(0.17, 0.03, 13.88, 1, 0.005), goldMat);
        ridgeGold.position.set(0, OG_Y + 3.98, 0);
        og.add(ridgeBeam, ridgeGold);

        for (const rz of [-6.80, -4.21, -2.05, -0.10, 2.28, 4.55, 6.88]) {
            for (const sx of [-1, 1]) {
                const rafter = new THREE.Mesh(new RoundedBoxGeometry(rafterLen, 0.11, 0.11, 1, 0.015), woodDarkMat);
                rafter.position.set(sx * 1.50, OG_Y + 3.09, rz);
                rafter.rotation.z = -sx * roofAngle;
                const rafterGold = new THREE.Mesh(new THREE.BoxGeometry(rafterLen, 0.016, 0.116), goldMat);
                rafterGold.position.set(sx * 1.50, OG_Y + 3.035, rz);
                rafterGold.rotation.z = -sx * roofAngle;
                og.add(rafter, rafterGold);
            }
        }

        // North Structural Wooden Center Posts (kept behind Atelier camera so Atelier & Group Photo sightlines stay 100% unobstructed!)
        for (const pz of [-6.78, -0.08]) {
            const post = new THREE.Mesh(new RoundedBoxGeometry(0.15, 4.02, 0.15, 1, 0.015), woodDarkMat);
            post.position.set(-0.08, OG_Y + 2.01, pz);
            og.add(post);
        }

        // Shared UV-calibrated Parisian Boiserie Gable Geometry (wrapS = Repeat, wrapT = ClampToEdge so black marble baseboard sits flush at y = 0!)
        const buildBoiserieGableGeo = () => {
            const gGeo = new THREE.ShapeGeometry(createGableWallShape());
            gGeo.computeBoundingBox();
            const gbb = gGeo.boundingBox;
            const gp = gGeo.attributes.position;
            const guv = gGeo.attributes.uv;
            const spanY = Math.max(0.1, gbb.max.y - gbb.min.y);
            for (let i = 0; i < gp.count; i++) {
                guv.setXY(
                    i,
                    (gp.getX(i) + 3.00) / BOISERIE_TILE_M,
                    clamp((gp.getY(i) - gbb.min.y) / spanY, 0.0, 1.0)
                );
            }
            return gGeo;
        };

        // North Gable Wall (z = -6.90) + 2 North Windows + 2 Nero-Marquina Desks + Cognac Chairs + Schranksystem
        const northGable = new THREE.Mesh(buildBoiserieGableGeo(), boiserieMat);
        northGable.position.set(0, OG_Y, -6.90);
        og.add(northGable);
        addFramedWindow(og, this.ogWeatherGroup, -1.48, OG_Y + 1.36, -6.82, 1.24, 1.08, 0);
        addFramedWindow(og, this.ogWeatherGroup,  1.22, OG_Y + 1.36, -6.82, 1.24, 1.08, 0);

        for (const dx of [-1.48, 1.22]) {
            const ndTop = new THREE.Mesh(new RoundedBoxGeometry(1.68, 0.055, 0.76, 2, 0.015), neroStoneMat);
            ndTop.position.set(dx, OG_Y + 0.75, -6.34);
            const ndTrim = new THREE.Mesh(new RoundedBoxGeometry(1.70, 0.016, 0.78, 1, 0.005), goldMat);
            ndTrim.position.set(dx, OG_Y + 0.728, -6.34);
            const ndLegL = new THREE.Mesh(new RoundedBoxGeometry(0.05, 0.73, 0.70, 1, 0.01), goldMat);
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
            og.add(ndTop, ndTrim, ndLegL, ndLegR, chSeat, chBack, chStem);
            addDeskMonitor(og, dx + (dx < 0 ? 0.36 : 0.0), OG_Y + 0.778, -6.54, 0.0);
        }
        const schrankSys = new THREE.Mesh(new RoundedBoxGeometry(0.52, 1.28, 2.48, 2, 0.02), woodDarkMat);
        schrankSys.position.set(2.70, OG_Y + 0.64, -5.52);
        og.add(schrankSys);

        // --- OG-Bad (4.11m long, z = -4.21 .. -0.10, Fliesen + Badewanne + Waschbecken + WC + Heizkoerper + Dachfenster) ---
        addWallBox(og, -0.06, -4.21,  3.00, -4.21, OG_Y, knieH);
        addWallBox(og, -0.06, -0.10,  3.00, -0.10, OG_Y, knieH);
        addWallBox(og, -0.06, -4.21, -0.06, -1.88, OG_Y, knieH);
        addWallBox(og, -0.06, -0.86, -0.06, -0.10, OG_Y, knieH);

        const ogTubDeck = new THREE.Mesh(new RoundedBoxGeometry(2.78, 0.58, 0.84, 3, 0.05), whiteMat);
        ogTubDeck.position.set(1.48, OG_Y + 0.29, -3.72);
        const ogTubWater = new THREE.Mesh(new RoundedBoxGeometry(2.15, 0.05, 0.58, 2, 0.04), this.mats.showerGlass);
        ogTubWater.position.set(1.48, OG_Y + 0.56, -3.72);
        const ogVanity = new THREE.Mesh(new RoundedBoxGeometry(0.48, 0.54, 0.92, 2, 0.02), neroStoneMat);
        ogVanity.position.set(0.26, OG_Y + 0.44, -2.35);
        const ogBasin = new THREE.Mesh(new THREE.CylinderGeometry(0.22, 0.17, 0.12, 16), whiteMat);
        ogBasin.position.set(0.28, OG_Y + 0.76, -2.35);
        const ogWc = new THREE.Mesh(new RoundedBoxGeometry(0.54, 0.42, 0.38, 3, 0.07), whiteMat);
        ogWc.position.set(2.58, OG_Y + 0.21, -2.35);
        og.add(ogTubDeck, ogTubWater, ogVanity, ogBasin, ogWc);
        addRadiator(og, 1.68, OG_Y, -0.22, 1.45, false); // OG-Bad Heizkoerper

        // Sloped Dachfenster (Velux Skylight in East Dachschraege above OG-Bad WC!)
        const veluxGroup = new THREE.Group();
        veluxGroup.position.set(3.48, OG_Y + 1.82, -2.35);
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
            wp.position.set(3.48 - yRel * slopeDx, OG_Y + 1.89 + yRel, -2.35 + uBase);
            this.ogWeatherGroup.add(wp);
            this.weatherParticles.push({
                mesh: wp,
                cx: 3.48,
                cy: OG_Y + 1.89,
                cz: -2.35,
                tanX: 0,
                tanZ: 1,
                halfW: 0.38,
                minY: OG_Y + 1.64,
                maxY: OG_Y + 2.14,
                uBase,
                y: OG_Y + 1.89 + yRel,
                slopeDxPerY: -slopeDx,
                speedY: 0.16 + Math.random() * 0.14,
                phase: Math.random() * 6.28
            });
        }

        // 2 West Knee-Wall Heizkoerper
        addRadiator(og, -2.88, OG_Y, -2.85, 1.82, true);
        addRadiator(og, -2.88, OG_Y,  1.05, 1.82, true);

        // Nero-Marquina & 24K Gold Boutique Balustrade ("Das ist ein Gelaender" at z = +1.92, x = +0.48 .. +2.98)
        const gelTop = new THREE.Mesh(new RoundedBoxGeometry(2.52, 0.055, 0.068, 2, 0.015), neroStoneMat);
        gelTop.position.set(1.74, OG_Y + 1.00, 1.92);
        const gelGoldCap = new THREE.Mesh(new THREE.BoxGeometry(2.53, 0.014, 0.072), goldMat);
        gelGoldCap.position.set(1.74, OG_Y + 1.032, 1.92);
        const gelBot = new THREE.Mesh(new RoundedBoxGeometry(2.52, 0.04, 0.05, 1, 0.01), neroStoneMat);
        gelBot.position.set(1.74, OG_Y + 0.08, 1.92);
        og.add(gelTop, gelGoldCap, gelBot);
        for (let b = 0; b <= 10; b++) {
            const bx = 0.50 + b * 0.246;
            const bar = new THREE.Mesh(
                new RoundedBoxGeometry(0.030, 0.94, 0.030, 1, 0.005),
                (b % 5 === 0) ? goldMat : neroStoneMat
            );
            bar.position.set(bx, OG_Y + 0.53, 1.92);
            og.add(bar);
        }
        // Safety side-rail on North edge of stairwell opening
        const stairBackGuard = new THREE.Mesh(new RoundedBoxGeometry(2.22, 0.96, 0.05, 1, 0.01), neroStoneMat);
        stairBackGuard.position.set(1.85, OG_Y + 0.48, 0.56);
        const stairBackGold = new THREE.Mesh(new THREE.BoxGeometry(2.23, 0.016, 0.055), goldMat);
        stairBackGold.position.set(1.85, OG_Y + 0.965, 0.56);
        og.add(stairBackGuard, stairBackGold);

        // ====================================================================
        // 4. SEELENFUNKEN ATELIER ENCLOSED GABLE & DACHSCHRAEGEN CEILING
        // (Visible in front_active Atelier view; cutaway in Dollhouse Pause Mode!)
        // ====================================================================
        const atelierClosed = new THREE.Group();
        this.ogRoofClosedGroup = atelierClosed;

        // Sloped Parisian Plaster Ceiling Panels over Seelenfunken Atelier (z = +1.85 .. +6.90)
        const slopeCeilingMat = this.mats.atelierCeilingPbr;
        for (const sx of [-1, 1]) {
            const ceilSlope = new THREE.Mesh(new RoundedBoxGeometry(rafterLen, 0.06, 5.10, 1, 0.01), slopeCeilingMat);
            ceilSlope.position.set(sx * 1.50, OG_Y + 3.13, 4.38);
            ceilSlope.rotation.z = -sx * roofAngle;
            atelierClosed.add(ceilSlope);
        }

        // Pentagonal South Atelier Gable Wall (z = +6.84, facing -Z toward camera!)
        // Uses /assets/atelier_boiserie_wall.jpg with ClampToEdge Y so cornice sits on top & black marble baseboard sits on floor!
        const southGableWall = new THREE.Mesh(buildBoiserieGableGeo(), boiserieMat);
        southGableWall.position.set(0, OG_Y, 6.84);
        southGableWall.rotation.y = Math.PI;
        // Delicate 24K Gold bead crowning the black marble baseboard (keeps the photorealistic marble plinth 100% visible!)
        const southBaseboardBead = new THREE.Mesh(new THREE.BoxGeometry(5.98, 0.016, 0.024), goldMat);
        southBaseboardBead.position.set(0, OG_Y + 0.148, 6.826);
        atelierClosed.add(southGableWall, southBaseboardBead);

        // 2 South Atelier Gable Windows (generous +35cm roof clearance at both outer upper corners!)
        addFramedWindow(atelierClosed, this.ogSouthWeatherGroup,  2.24, OG_Y + 1.18, 6.79, 1.06, 1.14, Math.PI);
        addFramedWindow(atelierClosed, this.ogSouthWeatherGroup, -2.24, OG_Y + 1.18, 6.79, 1.06, 1.14, Math.PI);

        // Subtle Volumetric Golden Sunbeams from South Atelier Windows
        const sunbeamMat = new THREE.MeshBasicMaterial({
            map: makeSunbeamTexture(),
            transparent: true,
            opacity: 0.52,
            depthWrite: false,
            blending: THREE.AdditiveBlending,
            side: THREE.DoubleSide
        });
        const sunbeamGeo = new THREE.PlaneGeometry(1.45, 2.65);
        this.sunbeamMeshes = [];
        for (const [sbX, sbRotZ] of [[1.88, -0.34], [-1.88, 0.34]]) {
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
            new THREE.PlaneGeometry(1.76, 1.18),
            frameShadowMat
        );
        photoWallHalo.position.set(0, -0.02, -0.022);
        const frameOuterMesh = new THREE.Mesh(new RoundedBoxGeometry(1.48, 0.92, 0.054, 2, 0.022), goldMat);
        const frameInnerMatte = new THREE.Mesh(new RoundedBoxGeometry(1.35, 0.79, 0.066, 1, 0.01), neroStoneMat);
        photoGroup.add(photoWallHalo, frameOuterMesh, frameInnerMatte);

        const placeholderCanvas = document.createElement('canvas');
        placeholderCanvas.width = 64;
        placeholderCanvas.height = 36;
        const pctx = placeholderCanvas.getContext('2d');
        pctx.fillStyle = '#241811';
        pctx.fillRect(0, 0, 64, 36);
        const portraitMat = new THREE.MeshBasicMaterial({
            map: configureFastTexture(new THREE.CanvasTexture(placeholderCanvas)),
            polygonOffset: true,
            polygonOffsetFactor: -2,
            polygonOffsetUnits: -2
        });

        const loader = new THREE.TextureLoader();
        loader.load('./assets/colleagues_group_photo.jpg?v=20261113v3', (loadedTex) => {
            configureHdTexture(loadedTex, 8);
            portraitMat.map = loadedTex;
            portraitMat.needsUpdate = true;
        });

        const portraitPlane = new THREE.Mesh(new THREE.PlaneGeometry(1.30, 0.74), portraitMat);
        portraitPlane.position.z = 0.045;
        const lampHood = new THREE.Mesh(new RoundedBoxGeometry(0.56, 0.045, 0.07, 2, 0.016), goldMat);
        lampHood.position.set(0, 0.50, 0.13);
        const lampGlowStrip = new THREE.Mesh(new THREE.PlaneGeometry(0.50, 0.04), this.mats.warmBulb);
        lampGlowStrip.rotation.x = Math.PI / 2;
        lampGlowStrip.position.set(0, 0.476, 0.13);
        const photoPlaqueBacking = new THREE.Mesh(new RoundedBoxGeometry(0.56, 0.11, 0.024, 1, 0.008), goldMat);
        photoPlaqueBacking.position.set(0, -0.45, 0.044);
        const photoPlaqueFace = new THREE.Mesh(new THREE.PlaneGeometry(0.52, 0.092), this.plaqueMats.team);
        photoPlaqueFace.position.set(0, -0.45, 0.068);
        photoGroup.add(portraitPlane, lampHood, lampGlowStrip, photoPlaqueBacking, photoPlaqueFace);
        atelierClosed.add(photoGroup);

        // High-Mounted 24K Gold Double-Ring Halo Chandelier under Ridge Beam (100% above Photo & Crest sightlines!)
        const chandelierGrp = new THREE.Group();
        chandelierGrp.position.set(0, OG_Y + 3.16, 5.15);
        const chanCanopy = new THREE.Mesh(new THREE.CylinderGeometry(0.11, 0.14, 0.045, 16), goldMat);
        chanCanopy.position.y = 0.78;
        const chanOuterGold = new THREE.Mesh(new THREE.TorusGeometry(0.54, 0.020, 12, 36), goldMat);
        chanOuterGold.rotation.x = Math.PI / 2;
        const chanOuterGlow = new THREE.Mesh(new THREE.TorusGeometry(0.515, 0.012, 8, 36), this.mats.warmBulb);
        chanOuterGlow.rotation.x = Math.PI / 2;
        chanOuterGlow.position.y = -0.012;
        const chanInnerGold = new THREE.Mesh(new THREE.TorusGeometry(0.30, 0.016, 10, 28), goldMat);
        chanInnerGold.rotation.x = Math.PI / 2;
        chanInnerGold.position.set(0, -0.11, 0);
        chanInnerGold.rotation.z = 0.10;
        const chanInnerGlow = new THREE.Mesh(new THREE.TorusGeometry(0.28, 0.010, 8, 28), this.mats.warmBulb);
        chanInnerGlow.rotation.x = Math.PI / 2;
        chanInnerGlow.position.set(0, -0.12, 0);
        chanInnerGlow.rotation.z = 0.10;
        const chanCoreGem = new THREE.Mesh(new THREE.OctahedronGeometry(0.055, 0), glassPbrMat);
        chanCoreGem.position.set(0, -0.12, 0);
        chandelierGrp.add(chanCanopy, chanOuterGold, chanOuterGlow, chanInnerGold, chanInnerGlow, chanCoreGem);
        for (let w = 0; w < 3; w++) {
            const wa = (w / 3) * Math.PI * 2;
            const wire = new THREE.Mesh(new THREE.CylinderGeometry(0.004, 0.004, 0.80, 6), goldMat);
            wire.position.set(Math.cos(wa) * 0.32, 0.39, Math.sin(wa) * 0.32);
            chandelierGrp.add(wire);
        }
        atelierClosed.add(chandelierGrp);

        // Warm 24K Gold & Nero-Marquina Kniestock Sconce Uplights on Left & Right Atelier Knee-Walls
        for (const [sx, sz, sRotY] of [[-2.922, 4.95, Math.PI / 2], [2.922, 4.95, -Math.PI / 2]]) {
            const inwardStep = sx < 0 ? 0.010 : -0.010;
            const sconceTrim = new THREE.Mesh(new RoundedBoxGeometry(0.134, 0.254, 0.014, 1, 0.004), goldMat);
            sconceTrim.position.set(sx, OG_Y + 1.18, sz);
            sconceTrim.rotation.y = sRotY;
            const sconcePlate = new THREE.Mesh(new RoundedBoxGeometry(0.12, 0.24, 0.022, 1, 0.006), neroStoneMat);
            sconcePlate.position.set(sx + inwardStep, OG_Y + 1.18, sz);
            sconcePlate.rotation.y = sRotY;
            const sconceBar = new THREE.Mesh(new THREE.CylinderGeometry(0.018, 0.018, 0.20, 10), goldMat);
            sconceBar.position.set(sx + (sx < 0 ? 0.032 : -0.032), OG_Y + 1.18, sz);
            const sconceBulb = new THREE.Mesh(new THREE.CylinderGeometry(0.022, 0.022, 0.16, 10), this.mats.warmBulb);
            sconceBulb.position.set(sx + (sx < 0 ? 0.048 : -0.048), OG_Y + 1.18, sz);
            atelierClosed.add(sconceTrim, sconcePlate, sconceBar, sconceBulb);
        }

        // OG Signed 24K Gold "MEIN SEELENFUNKE" Gallery Frames on West Knee-Wall (stepped cleanly off -2.950 inner face)
        addGoldenSignedWallArt(og, -2.91, OG_Y + 0.84, -0.90, 0.96, 0.64, Math.PI / 2, 'golden_heart',   'GALERIE SEELENFUNKE');
        addGoldenSignedWallArt(og, -2.91, OG_Y + 0.84, -5.15, 0.96, 0.64, Math.PI / 2, 'botanical_gold', 'STUDIO GIFHORN');

        // RIGHT NEXT TO GROUP PHOTO (Screen-Right = -X!): Illuminated Golden "Mein-Seelenfunke" Sign (clean gap to Right Window!)
        const brandSignGroup = new THREE.Group();
        brandSignGroup.position.set(-0.91, OG_Y + 1.74, 6.77);
        brandSignGroup.rotation.y = Math.PI;

        const signWallHalo = new THREE.Mesh(
            new THREE.PlaneGeometry(1.56, 0.84),
            frameShadowMat
        );
        signWallHalo.position.set(0, -0.02, -0.016);
        const signBacklitRim = new THREE.Mesh(new RoundedBoxGeometry(1.34, 0.62, 0.022, 2, 0.018), this.mats.warmBulb);
        signBacklitRim.position.z = 0.004;
        const signGoldCasing = new THREE.Mesh(new RoundedBoxGeometry(1.29, 0.57, 0.044, 2, 0.02), goldMat);
        signGoldCasing.position.z = 0.026;
        const signFacePlane = new THREE.Mesh(
            new THREE.PlaneGeometry(1.22, 0.50),
            new THREE.MeshBasicMaterial({
                map: makeIlluminatedBrandSignTexture(),
                polygonOffset: true,
                polygonOffsetFactor: -3,
                polygonOffsetUnits: -3
            })
        );
        signFacePlane.position.z = 0.060;
        brandSignGroup.add(signWallHalo, signBacklitRim, signGoldCasing, signFacePlane);
        atelierClosed.add(brandSignGroup);

        og.add(atelierClosed);

        // ---- LEVEL 1: Polished Nero-Marquina Manufaktur-Werkbank + 24K Gold Bezel + 3D GLAS-TROPHAEEN ----
        // Placed at Screen-Right (x = -1.68, z = +5.72) facing -Z toward camera!
        const lvl1 = new THREE.Group();
        lvl1.position.set(-1.68, OG_Y, 5.72);
        lvl1.rotation.y = Math.PI;

        const benchTop = new THREE.Mesh(new RoundedBoxGeometry(1.94, 0.095, 0.84, 2, 0.026), neroStoneMat);
        benchTop.position.set(0, 0.87, 0);
        const benchTrim = new THREE.Mesh(new RoundedBoxGeometry(1.965, 0.028, 0.865, 1, 0.01), goldMat);
        benchTrim.position.set(0, 0.845, 0);
        const drawerApron = new THREE.Mesh(new RoundedBoxGeometry(1.76, 0.15, 0.72, 2, 0.02), neroStoneMat);
        drawerApron.position.set(0, 0.74, 0);
        const drawerGoldBase = new THREE.Mesh(new RoundedBoxGeometry(1.77, 0.016, 0.73, 1, 0.005), goldMat);
        drawerGoldBase.position.set(0, 0.665, 0);
        const pullL = new THREE.Mesh(new RoundedBoxGeometry(0.16, 0.022, 0.03, 1, 0.008), goldMat);
        pullL.position.set(-0.42, 0.74, 0.37);
        const pullR = new THREE.Mesh(new RoundedBoxGeometry(0.16, 0.022, 0.03, 1, 0.008), goldMat);
        pullR.position.set(0.42, 0.74, 0.37);
        const benchCrest = new THREE.Mesh(new THREE.PlaneGeometry(0.36, 0.088), this.plaqueMats.obsidianMain);
        benchCrest.position.set(0, 0.74, 0.372);
        lvl1.add(benchTop, benchTrim, drawerApron, drawerGoldBase, pullL, pullR, benchCrest);

        const legGeo = new THREE.CylinderGeometry(0.040, 0.025, 0.84, 12);
        for (const [lx, lz] of [[-0.86, 0.32], [0.86, 0.32], [-0.86, -0.32], [0.86, -0.32]]) {
            const leg = new THREE.Mesh(legGeo, goldMat);
            leg.position.set(lx, 0.42, lz);
            lvl1.add(leg);
        }

        // 3D GLAS-TROPHAEEN (Signed "MEIN SEELENFUNKE" Optical K9 Seelen-Kristall Awards on Nero-Marquina Plinths!)
        const trophiesGroup = new THREE.Group();
        const t1Base = new THREE.Mesh(new RoundedBoxGeometry(0.20, 0.048, 0.20, 2, 0.012), neroStoneMat);
        t1Base.position.set(0.0, 0.94, 0.18);
        const t1GoldCollar = new THREE.Mesh(new RoundedBoxGeometry(0.16, 0.024, 0.16, 1, 0.008), goldMat);
        t1GoldCollar.position.set(0.0, 0.972, 0.18);
        const t1Plaque = new THREE.Mesh(new THREE.PlaneGeometry(0.168, 0.036), this.plaqueMats.trophy);
        t1Plaque.position.set(0.0, 0.942, 0.282);
        const t1CoreStar = new THREE.Mesh(new THREE.OctahedronGeometry(0.042, 0), this.mats.warmBulb);
        t1CoreStar.position.set(0.0, 1.15, 0.18);
        const t1Obelisk = new THREE.Mesh(new THREE.CylinderGeometry(0.042, 0.082, 0.34, 6), glassPbrMat);
        t1Obelisk.position.set(0.0, 1.15, 0.18);
        const t1Apex = new THREE.Mesh(new THREE.ConeGeometry(0.042, 0.08, 6), this.mats.crystalRim);
        t1Apex.position.set(0.0, 1.36, 0.18);
        trophiesGroup.add(t1Base, t1GoldCollar, t1Plaque, t1CoreStar, t1Obelisk, t1Apex);

        const t2Base = new THREE.Mesh(new THREE.CylinderGeometry(0.075, 0.088, 0.048, 12), goldMat);
        t2Base.position.set(-0.32, 0.94, 0.15);
        const t2Plinth = new THREE.Mesh(new RoundedBoxGeometry(0.138, 0.034, 0.024, 1, 0.005), neroStoneMat);
        t2Plinth.position.set(-0.32, 0.942, 0.232);
        const t2Plaque = new THREE.Mesh(new THREE.PlaneGeometry(0.128, 0.030), this.plaqueMats.trophy);
        t2Plaque.position.set(-0.32, 0.942, 0.246);
        const t2Core = new THREE.Mesh(new THREE.OctahedronGeometry(0.032, 0), goldMat);
        t2Core.position.set(-0.32, 1.10, 0.15);
        const t2Diamond = new THREE.Mesh(new THREE.OctahedronGeometry(0.105, 0), glassPbrMat);
        t2Diamond.scale.set(1.0, 1.35, 0.58);
        t2Diamond.position.set(-0.32, 1.10, 0.15);
        const t2Sheen = new THREE.Mesh(new THREE.OctahedronGeometry(0.108, 0), this.mats.crystalRim);
        t2Sheen.scale.set(0.32, 1.36, 0.60);
        t2Sheen.position.set(-0.32, 1.10, 0.15);
        trophiesGroup.add(t2Base, t2Plinth, t2Plaque, t2Core, t2Diamond, t2Sheen);

        const t3Base = new THREE.Mesh(new RoundedBoxGeometry(0.18, 0.044, 0.12, 2, 0.01), neroStoneMat);
        t3Base.position.set(0.32, 0.94, 0.15);
        const t3GoldTrim = new THREE.Mesh(new RoundedBoxGeometry(0.15, 0.020, 0.095, 1, 0.006), goldMat);
        t3GoldTrim.position.set(0.32, 0.968, 0.15);
        const t3Plaque = new THREE.Mesh(new THREE.PlaneGeometry(0.152, 0.034), this.plaqueMats.trophy);
        t3Plaque.position.set(0.32, 0.942, 0.212);
        const t3InnerCrest = new THREE.Mesh(new RoundedBoxGeometry(0.065, 0.12, 0.012, 1, 0.004), this.mats.warmBulb);
        t3InnerCrest.position.set(0.32, 1.09, 0.15);
        const t3Block = new THREE.Mesh(new RoundedBoxGeometry(0.138, 0.25, 0.062, 2, 0.016), glassPbrMat);
        t3Block.position.set(0.32, 1.10, 0.15);
        trophiesGroup.add(t3Base, t3GoldTrim, t3Plaque, t3InnerCrest, t3Block);
        lvl1.add(trophiesGroup);

        // Signed 14K Koerper-Kasse Savings Jar & Signed TikTok Rose Amphora Vase
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

        // Artisan Manufaktur Jeweler Pad, Engraved Ring Blank, Golden Caliper & Loupe on Workbench
        const jPadFrame = new THREE.Mesh(new RoundedBoxGeometry(0.36, 0.018, 0.24, 1, 0.005), goldMat);
        jPadFrame.position.set(0.06, 0.924, -0.18);
        const jPadVelvet = new THREE.Mesh(new RoundedBoxGeometry(0.33, 0.022, 0.21, 1, 0.005), obsidianMat);
        jPadVelvet.position.set(0.06, 0.927, -0.18);
        const jRingBlank = new THREE.Mesh(new THREE.TorusGeometry(0.032, 0.006, 10, 22), goldMat);
        jRingBlank.rotation.x = Math.PI * 0.36;
        jRingBlank.position.set(0.00, 0.952, -0.18);
        const jLoupeBody = new THREE.Mesh(new THREE.CylinderGeometry(0.026, 0.028, 0.028, 14), goldMat);
        jLoupeBody.position.set(0.14, 0.952, -0.16);
        const jLoupeLens = new THREE.Mesh(new THREE.CylinderGeometry(0.021, 0.021, 0.030, 14), this.mats.crystalRim);
        jLoupeLens.position.set(0.14, 0.953, -0.16);
        const jCaliperBar = new THREE.Mesh(new RoundedBoxGeometry(0.19, 0.008, 0.022, 1, 0.002), goldMat);
        jCaliperBar.position.set(-0.28, 0.922, -0.20);
        jCaliperBar.rotation.y = 0.24;
        lvl1.add(jPadFrame, jPadVelvet, jRingBlank, jLoupeBody, jLoupeLens, jCaliperBar);

        og.add(lvl1);
        this.levelGroups[1] = lvl1;

        // ---- LEVEL 2: Flush Parisian 24K Gold & Nero-Marquina Parquet Inlay Border, Westpack Boxes & Monstera ----
        // Keeps 95% of the photorealistic Herringbone Oak Parquet completely open and gleaming!
        const lvl2 = new THREE.Group();
        const inlayGroup = new THREE.Group();
        inlayGroup.position.set(0, OG_Y + 0.003, 4.55);
        const inlayNeroBand = new THREE.Mesh(new THREE.RingGeometry(1.36, 1.48, 64), neroStoneMat);
        inlayNeroBand.rotation.x = -Math.PI / 2;
        const inlayOuterBrass = new THREE.Mesh(new THREE.RingGeometry(1.48, 1.502, 64), goldMat);
        inlayOuterBrass.rotation.x = -Math.PI / 2;
        inlayOuterBrass.position.y = 0.001;
        const inlayInnerBrass = new THREE.Mesh(new THREE.RingGeometry(1.338, 1.36, 64), goldMat);
        inlayInnerBrass.rotation.x = -Math.PI / 2;
        inlayInnerBrass.position.y = 0.001;
        const inlayCenterDisc = new THREE.Mesh(new THREE.CircleGeometry(0.18, 32), neroStoneMat);
        inlayCenterDisc.rotation.x = -Math.PI / 2;
        const inlayCenterRing = new THREE.Mesh(new THREE.RingGeometry(0.175, 0.195, 32), goldMat);
        inlayCenterRing.rotation.x = -Math.PI / 2;
        inlayCenterRing.position.y = 0.001;
        inlayGroup.add(inlayNeroBand, inlayOuterBrass, inlayInnerBrass, inlayCenterDisc, inlayCenterRing);
        lvl2.add(inlayGroup);

        for (let b = 0; b < 3; b++) {
            const bw = 0.29 - b * 0.030;
            const bd = 0.23 - b * 0.020;
            const box = new THREE.Mesh(new RoundedBoxGeometry(bw, 0.10, bd, 2, 0.018), neroStoneMat);
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

        // ---- LEVEL 3: xTool F2 Ultra UV on Nero-Marquina Plinth + Signed Boutique Vitrine ----
        // Placed at Screen-Left (x = +1.96, z = +5.72) facing -Z toward camera!
        const lvl3 = new THREE.Group();
        const laserGrp = new THREE.Group();
        laserGrp.position.set(1.96, OG_Y, 5.72);
        laserGrp.rotation.y = Math.PI;

        const laserStand = new THREE.Mesh(new RoundedBoxGeometry(0.94, 0.78, 0.74, 2, 0.03), neroStoneMat);
        laserStand.position.y = 0.39;
        const standTrim = new THREE.Mesh(new RoundedBoxGeometry(0.965, 0.026, 0.765, 1, 0.008), goldMat);
        standTrim.position.y = 0.77;
        const standBaseGold = new THREE.Mesh(new RoundedBoxGeometry(0.96, 0.022, 0.76, 1, 0.006), goldMat);
        standBaseGold.position.y = 0.012;
        const laserPlaque = new THREE.Mesh(new THREE.PlaneGeometry(0.46, 0.12), this.plaqueMats.obsidianMain);
        laserPlaque.position.set(0, 0.48, 0.382);
        const laserBody = new THREE.Mesh(new RoundedBoxGeometry(0.56, 0.56, 0.50, 2, 0.035), this.mats.darkArmor);
        laserBody.position.set(0, 1.06, -0.02);
        const conveyorRail = new THREE.Mesh(new RoundedBoxGeometry(1.12, 0.045, 0.25, 2, 0.012), this.mats.silverSteel);
        conveyorRail.position.set(0, 0.81, 0.04);
        const laserHood = new THREE.Mesh(
            new RoundedBoxGeometry(0.51, 0.32, 0.45, 2, 0.025),
            new THREE.MeshBasicMaterial({ color: 0x16a367, transparent: true, opacity: 0.64 })
        );
        laserHood.position.set(0, 1.01, 0.03);
        const laserTopCrown = new THREE.Mesh(new RoundedBoxGeometry(0.57, 0.022, 0.51, 1, 0.008), goldMat);
        laserTopCrown.position.set(0, 1.345, -0.02);
        const laserDial = new THREE.Mesh(new THREE.CylinderGeometry(0.034, 0.038, 0.022, 16), goldMat);
        laserDial.rotation.x = Math.PI / 2;
        laserDial.position.set(0.18, 0.845, 0.24);
        const laserStatusRing = new THREE.Mesh(new THREE.TorusGeometry(0.036, 0.004, 8, 18), this.mats.warmBulb);
        laserStatusRing.position.set(0.18, 0.845, 0.242);
        const ra3Rotary = new THREE.Mesh(new THREE.CylinderGeometry(0.038, 0.038, 0.16, 12), this.mats.silverSteel);
        ra3Rotary.rotation.z = Math.PI / 2;
        ra3Rotary.position.set(-0.04, 0.855, 0.04);
        const exhaustUnit = new THREE.Mesh(new THREE.CylinderGeometry(0.13, 0.14, 0.45, 16), neroStoneMat);
        exhaustUnit.position.set(0.58, 0.225, -0.08);
        const exhaustCap = new THREE.Mesh(new THREE.CylinderGeometry(0.134, 0.134, 0.028, 16), goldMat);
        exhaustCap.position.set(0.58, 0.455, -0.08);
        laserGrp.add(
            laserStand, standTrim, standBaseGold, laserPlaque, laserBody, conveyorRail,
            laserHood, laserTopCrown, laserDial, laserStatusRing, ra3Rotary, exhaustUnit, exhaustCap
        );
        lvl3.add(laserGrp);

        const vitBase = new THREE.Mesh(new RoundedBoxGeometry(0.62, 0.86, 0.46, 2, 0.026), neroStoneMat);
        vitBase.position.set(1.04, OG_Y + 0.43, 5.96);
        const vitBaseGold = new THREE.Mesh(new RoundedBoxGeometry(0.64, 0.022, 0.48, 1, 0.006), goldMat);
        vitBaseGold.position.set(1.04, OG_Y + 0.012, 5.96);
        const vitCap = new THREE.Mesh(new RoundedBoxGeometry(0.65, 0.032, 0.49, 1, 0.01), goldMat);
        vitCap.position.set(1.04, OG_Y + 0.87, 5.96);
        const vitPlaque = new THREE.Mesh(new THREE.PlaneGeometry(0.42, 0.115), this.plaqueMats.trophy);
        vitPlaque.position.set(1.04, OG_Y + 0.56, 5.718);
        vitPlaque.rotation.y = Math.PI;
        const vitGlass = new THREE.Mesh(
            new RoundedBoxGeometry(0.58, 0.74, 0.42, 2, 0.02),
            glassPbrMat
        );
        vitGlass.position.set(1.04, OG_Y + 1.25, 5.96);
        const vitTopRim = new THREE.Mesh(new RoundedBoxGeometry(0.60, 0.020, 0.44, 1, 0.006), goldMat);
        vitTopRim.position.set(1.04, OG_Y + 1.622, 5.96);
        const vitItem = new THREE.Mesh(new THREE.OctahedronGeometry(0.125, 0), goldMat);
        vitItem.position.set(1.04, OG_Y + 1.22, 5.96);
        lvl3.add(vitBase, vitBaseGold, vitCap, vitPlaque, vitGlass, vitTopRim, vitItem);
        this.rig.vitrineGem = vitItem;
        og.add(lvl3);
        this.levelGroups[3] = lvl3;

        // ---- LEVEL 4: Signed Sculpted Goblet Trophy on Nero-Marquina Plinth ----
        const lvl4 = new THREE.Group();
        const gobBase = new THREE.Mesh(new RoundedBoxGeometry(0.22, 0.048, 0.22, 2, 0.012), neroStoneMat);
        gobBase.position.set(1.04, OG_Y + 1.644, 5.96);
        const gobPlaque = new THREE.Mesh(new THREE.PlaneGeometry(0.18, 0.038), this.plaqueMats.trophy);
        gobPlaque.position.set(1.04, OG_Y + 1.646, 5.838);
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
        const crestShad = new THREE.Mesh(new THREE.PlaneGeometry(1.98, 0.62), frameShadowMat);
        crestShad.position.set(-0.22, OG_Y + 2.50, 6.824);
        crestShad.rotation.y = Math.PI;
        const crestBacking = new THREE.Mesh(new RoundedBoxGeometry(1.83, 0.47, 0.026, 2, 0.012), goldMat);
        crestBacking.position.set(-0.22, OG_Y + 2.52, 6.800);
        crestBacking.rotation.y = Math.PI;
        const crestMesh = new THREE.Mesh(
            new THREE.PlaneGeometry(1.78, 0.42),
            new THREE.MeshBasicMaterial({
                map: makeWallCrestTexture('MEIN SEELENFUNKE', 'MANUFAKTUR GIFHORN · EXZELLENZ-STUFE V'),
                polygonOffset: true,
                polygonOffsetFactor: -3,
                polygonOffsetUnits: -3
            })
        );
        crestMesh.position.set(-0.22, OG_Y + 2.52, 6.774);
        crestMesh.rotation.y = Math.PI;
        const blueprintBacking = new THREE.Mesh(new RoundedBoxGeometry(1.19, 0.40, 0.024, 1, 0.008), goldMat);
        blueprintBacking.position.set(-2.926, OG_Y + 0.92, 3.55);
        blueprintBacking.rotation.y = Math.PI / 2;
        const blueprintMesh = new THREE.Mesh(
            new THREE.PlaneGeometry(1.15, 0.36),
            new THREE.MeshBasicMaterial({
                map: makeWallCrestTexture('PROJEKT: EIGENER KÖRPER', '14K CHASSIS-BLAUPAUSE · FUNKIRA MK-II'),
                polygonOffset: true,
                polygonOffsetFactor: -3,
                polygonOffsetUnits: -3
            })
        );
        blueprintMesh.position.set(-2.901, OG_Y + 0.92, 3.55);
        blueprintMesh.rotation.y = Math.PI / 2;
        lvl5.add(blueprintBacking, blueprintMesh);
        atelierClosed.add(crestShad, crestBacking, crestMesh);
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
            (this.upstairsFxGroup || this.scene).add(sp);
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

        // 1. Dynamic HD Seasonal Garden Ground & WPC/Teak Terrace Deck
        paintGardenGroundCanvas(this.gardenGroundCanvas, this.worldState.season, this.worldState.occasion);
        this.gardenGroundTexture = configureHdTexture(new THREE.CanvasTexture(this.gardenGroundCanvas), 4);
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
        this.terraceDeckTexture = configureHdTexture(new THREE.CanvasTexture(this.terraceDeckCanvas), 4);
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

        const lanternPostGeo = new THREE.CylinderGeometry(0.035, 0.044, 0.92, 12);
        const lanternCollarGeo = new THREE.CylinderGeometry(0.068, 0.046, 0.028, 12);
        const lanternGlassGeo = new THREE.CylinderGeometry(0.078, 0.068, 0.14, 12);
        const lanternBulbGeo = new THREE.SphereGeometry(0.048, 12, 10);
        const lanternCoords = [
            [-3.82, 9.48], [2.42, 9.48],
            [-5.48, 7.05], [5.48, 7.05],
            [-5.48, 13.26], [5.48, 13.26]
        ];
        for (const [lx, lz] of lanternCoords) {
            const post = new THREE.Mesh(lanternPostGeo, obsidianMat);
            post.position.set(lx, 0.46, lz);
            const collar = new THREE.Mesh(lanternCollarGeo, goldMat);
            collar.position.set(lx, 0.91, lz);
            const glass = new THREE.Mesh(lanternGlassGeo, this.mats.crystalGlass);
            glass.position.set(lx, 0.985, lz);
            const bulb = new THREE.Mesh(lanternBulbGeo, this.mats.warmBulb);
            bulb.position.set(lx, 0.975, lz);
            const hat = new THREE.Mesh(new THREE.ConeGeometry(0.105, 0.065, 12), goldMat);
            hat.position.set(lx, 1.075, lz);
            garden.add(post, collar, glass, bulb, hat);
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

        // Outdoor Boutique Accent Cushions & Schwarz-Gold Tea Duo on Lounge Table (clear of dropSpots.terrace!)
        const outPillowGeo = new RoundedBoxGeometry(0.26, 0.24, 0.10, 2, 0.04);
        for (const [ox, oz, ory, oMat] of [
            [-3.22, 7.64,  0.22, goldMat],
            [-2.58, 7.62, -0.06, obsidianMat],
            [-1.98, 7.65, -0.20, goldMat]
        ]) {
            const op = new THREE.Mesh(outPillowGeo, oMat);
            op.position.set(ox, 0.55, oz);
            op.rotation.set(-0.18, ory, 0);
            garden.add(op);
        }
        const teaTray = new THREE.Mesh(new RoundedBoxGeometry(0.24, 0.014, 0.15, 1, 0.004), goldMat);
        teaTray.position.set(-2.32, 0.382, 8.68);
        const teaCupL = new THREE.Mesh(new THREE.CylinderGeometry(0.026, 0.020, 0.048, 10), obsidianMat);
        teaCupL.position.set(-2.38, 0.412, 8.68);
        const teaCupR = new THREE.Mesh(new THREE.CylinderGeometry(0.026, 0.020, 0.048, 10), obsidianMat);
        teaCupR.position.set(-2.26, 0.412, 8.68);
        garden.add(teaTray, teaCupL, teaCupR);

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
                [ 0.00, 2.22,  0.00, 0.64],
                [-0.44, 1.94,  0.18, 0.48],
                [ 0.46, 1.96, -0.15, 0.48],
                [ 0.14, 2.04,  0.40, 0.44],
                [-0.18, 2.02, -0.38, 0.44],
                [-0.32, 2.28, -0.18, 0.38],
                [ 0.30, 2.26,  0.22, 0.38],
                [ 0.00, 2.52,  0.04, 0.36]
            ];
            for (const [bx, by, bz, br] of blobs) {
                const sp = new THREE.Mesh(new THREE.SphereGeometry(br, 16, 12), canopyMat);
                sp.position.set(3.85 + bx, by, 11.45 + bz);
                targetGrp.add(sp);
            }
        };

        // ====================================================================
        // 7. SEASONAL 3D LAYERS (SPRING, SUMMER, AUTUMN, WINTER)
        // ====================================================================
        const springGrp = new THREE.Group();
        addTreeCanopy(springGrp, this.mats.blossomPink);
        const flowerStemGeo = new THREE.CylinderGeometry(0.012, 0.014, 0.26, 8);
        const tulipHeadGeo = new THREE.SphereGeometry(0.052, 12, 10);
        const petalCrownGeo = new THREE.TorusGeometry(0.038, 0.016, 8, 12);
        for (let i = 0; i < 12; i++) {
            const fx = -3.28 + (i % 6) * 0.31;
            const fz = 11.00 + Math.floor(i / 6) * 0.38;
            const pMat = (i % 3 === 0) ? this.mats.sunflowerYellow : this.mats.tulipPink;
            const st = new THREE.Mesh(flowerStemGeo, leafMat);
            st.position.set(fx, 0.28, fz);
            const hd = new THREE.Mesh(tulipHeadGeo, pMat);
            hd.scale.set(0.85, 1.25, 0.85);
            hd.position.set(fx, 0.43, fz);
            const corolla = new THREE.Mesh(petalCrownGeo, pMat);
            corolla.rotation.x = Math.PI / 2;
            corolla.position.set(fx, 0.41, fz);
            const coreGem = new THREE.Mesh(new THREE.SphereGeometry(0.018, 8, 6), goldMat);
            coreGem.position.set(fx, 0.48, fz);
            springGrp.add(st, hd, corolla, coreGem);
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
            (this.upstairsFxGroup || this.scene).add(rocketMesh);
            const sparks = [];
            for (let s = 0; s < 22; s++) {
                const smesh = new THREE.Mesh(fwSparkGeo, mat);
                smesh.visible = false;
                (this.upstairsFxGroup || this.scene).add(smesh);
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

        (this.upstairsFxGroup || this.scene).add(mower);
        this._parkLawnMowerHome();

        // Pooled Grass-Clipping Particles for Live Lawn Mowing
        const grassGeo = new THREE.BoxGeometry(0.022, 0.048, 0.015);
        const grassMatGreen = new THREE.MeshBasicMaterial({ color: 0x56B85E });
        const grassMatGold  = new THREE.MeshBasicMaterial({ color: 0xE6C265 });
        for (let i = 0; i < 14; i++) {
            const gm = new THREE.Mesh(grassGeo, (i % 4 === 0) ? grassMatGold : grassMatGreen);
            gm.visible = false;
            (this.upstairsFxGroup || this.scene).add(gm);
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
            (this.upstairsFxGroup || this.scene).add(m);
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
    // SECRET 3D KUPPELHALLE, HYDRAULIC VAULT LIFT & AUTONOMOUS PROJECT-BRAIN (UG_Y = -3.20m)
    // Photorealistic Schwarz-Gold Subterranean Dome Hall with:
    // - Real Geometric EG Shaft Cutout + Vertical Hydraulic Lift (0% ceiling/floor clipping!)
    // - High-Resolution Photorealistic Architectural Assets (cellar_dome_panorama / marble / bridge)
    // - Zero GPU overhead when upstairs (cellarGroup.visible = false) & 100% Upstairs Culling downstairs
    // - Zero Control Windows: 100% Autonomous Self-Healing & Antigravity Handoff Wave
    // ========================================================================
    _buildSecretCellar() {
        const goldMat = this.mats.gold24k;
        const obsidianMat = this.mats.obsidian;
        const woodDarkMat = this.mats.woodDark;
        const glassMat = this.mats.crystalGlass;

        const texLoader = new THREE.TextureLoader();
        const domeShellMat = new THREE.MeshBasicMaterial({ color: 0x131119, side: THREE.BackSide });
        const islandMarbleMat = new THREE.MeshBasicMaterial({ color: 0x15131c });
        const bridgeDeckMat = new THREE.MeshBasicMaterial({ color: 0x16141e });

        texLoader.load('./assets/cellar_dome_panorama.jpg?v=20261115v23', (tex) => {
            tex.colorSpace = THREE.SRGBColorSpace;
            tex.wrapS = THREE.RepeatWrapping;
            tex.wrapT = THREE.ClampToEdgeWrapping;
            configureHdTexture(tex, 4);
            domeShellMat.color.setHex(0xFFFFFF);
            domeShellMat.map = tex;
            domeShellMat.needsUpdate = true;
        });
        texLoader.load('./assets/cellar_island_marble.jpg?v=20261115v23', (tex) => {
            tex.colorSpace = THREE.SRGBColorSpace;
            configureHdTexture(tex, 4);
            islandMarbleMat.color.setHex(0xFFFFFF);
            islandMarbleMat.map = tex;
            islandMarbleMat.needsUpdate = true;
        });
        texLoader.load('./assets/cellar_bridge_deck.jpg?v=20261115v23', (tex) => {
            tex.colorSpace = THREE.SRGBColorSpace;
            tex.wrapS = THREE.ClampToEdgeWrapping;
            tex.wrapT = THREE.MirroredRepeatWrapping;
            tex.repeat.set(1, 3);
            configureHdTexture(tex, 4);
            bridgeDeckMat.color.setHex(0xFFFFFF);
            bridgeDeckMat.map = tex;
            bridgeDeckMat.needsUpdate = true;
        });

        // A1. Flush Nero-Marquina & 24K-Gold Hydraulic Vault Lift Platform at (x = 2.86, z = -0.73)
        // Sits completely flush inside the real geometric EG floor cutout when upstairs, and glides vertically with Funkira!
        const liftPlat = new THREE.Group();
        liftPlat.position.set(2.86, 0.0, -0.73);
        liftPlat.matrixAutoUpdate = true;
        const liftTop = new THREE.Mesh(new THREE.PlaneGeometry(0.91, 0.91), islandMarbleMat);
        liftTop.rotation.x = -Math.PI / 2;
        liftTop.position.y = 0.002;
        const liftSlab = new THREE.Mesh(new RoundedBoxGeometry(0.92, 0.14, 0.92, 2, 0.015), obsidianMat);
        liftSlab.position.y = -0.072;
        const liftGoldRim = new THREE.Mesh(new THREE.BoxGeometry(0.932, 0.028, 0.932), goldMat);
        liftGoldRim.position.y = -0.014;
        const liftPiston = new THREE.Mesh(new THREE.CylinderGeometry(0.11, 0.14, 0.44, 16), goldMat);
        liftPiston.position.y = -0.35;
        liftPlat.add(liftTop, liftSlab, liftGoldRim, liftPiston);
        this.cellarLiftPlatform = liftPlat;
        this.scene.add(liftPlat);

        const cellar = new THREE.Group();
        cellar.visible = false;
        cellar.matrixAutoUpdate = true;
        this.cellarGroup = cellar;

        // A2. Illuminated Obsidian & 24K-Gold Vertical Lift Shaft (x = 2.86, z = -0.73, y = 0.00 -> UG_Y)
        const shaftGrp = new THREE.Group();
        const shaftH = Math.abs(UG_Y);
        const shaftWallW = new THREE.Mesh(new THREE.BoxGeometry(0.06, shaftH, 1.02), obsidianMat);
        shaftWallW.position.set(2.36, UG_Y * 0.5, -0.73);
        const shaftWallE = new THREE.Mesh(new THREE.BoxGeometry(0.06, shaftH, 1.02), obsidianMat);
        shaftWallE.position.set(3.36, UG_Y * 0.5, -0.73);
        const shaftWallN = new THREE.Mesh(new THREE.BoxGeometry(1.06, shaftH, 0.06), obsidianMat);
        shaftWallN.position.set(2.86, UG_Y * 0.5, -1.23);
        // South lintel above subterranean doorway (leaves y = UG_Y .. UG_Y + 2.30 wide open to walk out!)
        const lintelH = Math.max(0.40, shaftH - 2.30);
        const shaftLintelS = new THREE.Mesh(new THREE.BoxGeometry(1.06, lintelH, 0.06), obsidianMat);
        shaftLintelS.position.set(2.86, -lintelH * 0.5, -0.23);
        const shaftDoorHeaderGold = new THREE.Mesh(new THREE.BoxGeometry(1.04, 0.038, 0.075), goldMat);
        shaftDoorHeaderGold.position.set(2.86, UG_Y + 2.30, -0.23);
        shaftGrp.add(shaftWallW, shaftWallE, shaftWallN, shaftLintelS, shaftDoorHeaderGold);

        const ringStripMat = new THREE.MeshBasicMaterial({ color: 0xE5C158 });
        for (let r = 0; r < 6; r++) {
            const ry = -0.42 - r * 0.48;
            const stripW = new THREE.Mesh(new THREE.BoxGeometry(0.018, 0.022, 0.90), ringStripMat);
            stripW.position.set(2.395, ry, -0.73);
            const stripE = new THREE.Mesh(new THREE.BoxGeometry(0.018, 0.022, 0.90), ringStripMat);
            stripE.position.set(3.325, ry, -0.73);
            const stripN = new THREE.Mesh(new THREE.BoxGeometry(0.90, 0.022, 0.018), ringStripMat);
            stripN.position.set(2.86, ry, -1.195);
            shaftGrp.add(stripW, stripE, stripN);
        }
        freezeStaticTree(shaftGrp);
        this.cellarShaftGroup = shaftGrp;
        cellar.add(shaftGrp);

        // B. Monumental 25.2-Meter Photorealistic Spherical Kuppelhalle + Cantilevered Bridge (visible once deepInCellar)
        const domeHall = new THREE.Group();
        domeHall.visible = false;
        domeHall.matrixAutoUpdate = true;
        this.cellarDomeShellGroup = domeHall;
        cellar.add(domeHall);

        const domeStatic = new THREE.Group();
        const DOME_R = 12.60;
        const DOME_Y = UG_Y + 1.35;
        const DOME_Z = 9.40;

        // 1. Photorealistic 2048x1024 Architectural Luxury Dome Panorama (aligned so u = 0.5 faces camera dead-center!)
        const domeMesh = new THREE.Mesh(new THREE.SphereGeometry(DOME_R, 64, 44), domeShellMat);
        domeMesh.position.set(0, DOME_Y, DOME_Z);
        domeMesh.rotation.y = -Math.PI * 0.5;
        this.cellarDomeMesh = domeMesh;
        domeStatic.add(domeMesh);

        // 2. Sculpted 24K-Gold & Obsidian Great-Circle Meridian Ribs, Latitude Rings, Zenith Oculus & Abyss Core
        const eqRing = new THREE.Mesh(new THREE.TorusGeometry(DOME_R - 0.12, 0.085, 10, 72), goldMat);
        eqRing.rotation.x = Math.PI / 2;
        eqRing.position.set(0, DOME_Y, DOME_Z);
        domeStatic.add(eqRing);

        for (const latOffY of [4.85, -4.65]) {
            const latR = Math.sqrt(Math.max(1, (DOME_R - 0.12) * (DOME_R - 0.12) - latOffY * latOffY));
            const latRing = new THREE.Mesh(new THREE.TorusGeometry(latR, 0.056, 8, 64), goldMat);
            latRing.rotation.x = Math.PI / 2;
            latRing.position.set(0, DOME_Y + latOffY, DOME_Z);
            domeStatic.add(latRing);
        }

        const meridianGeo = new THREE.TorusGeometry(DOME_R - 0.14, 0.050, 8, 64);
        for (let m = 0; m < 8; m++) {
            const mer = new THREE.Mesh(meridianGeo, (m % 2 === 0) ? goldMat : obsidianMat);
            mer.position.set(0, DOME_Y, DOME_Z);
            mer.rotation.y = (m / 8) * Math.PI;
            domeStatic.add(mer);
        }

        // Zenith Oculus Crown at top of Sphere
        const oculusOuter = new THREE.Mesh(new THREE.TorusGeometry(2.15, 0.11, 10, 44), goldMat);
        oculusOuter.rotation.x = Math.PI / 2;
        oculusOuter.position.set(0, DOME_Y + DOME_R - 0.22, DOME_Z);
        const oculusDisc = new THREE.Mesh(
            new THREE.CircleGeometry(2.06, 36),
            new THREE.MeshBasicMaterial({ color: 0x261f18 })
        );
        oculusDisc.rotation.x = Math.PI / 2;
        oculusDisc.position.set(0, DOME_Y + DOME_R - 0.26, DOME_Z);
        domeStatic.add(oculusOuter, oculusDisc);

        // Deep Subterranean Abyss Foundation Rings (7.35m beneath the suspended bridge!)
        const abyssOuter = new THREE.Mesh(new THREE.TorusGeometry(3.85, 0.14, 10, 48), goldMat);
        abyssOuter.rotation.x = Math.PI / 2;
        abyssOuter.position.set(0, UG_Y - 7.35, DOME_Z);
        const abyssInner = new THREE.Mesh(new THREE.TorusGeometry(1.95, 0.085, 8, 40), goldMat);
        abyssInner.rotation.x = Math.PI / 2;
        abyssInner.position.set(0, UG_Y - 7.48, DOME_Z);
        const abyssCoreDisc = new THREE.Mesh(
            new THREE.CylinderGeometry(1.88, 2.20, 0.42, 32),
            obsidianMat
        );
        abyssCoreDisc.position.set(0, UG_Y - 7.68, DOME_Z);
        domeStatic.add(abyssOuter, abyssInner, abyssCoreDisc);

        // 3. Arrival Balcony + Monumental Round Vault Portal + 5.35m Photorealistic Cantilevered Bridge
        const bridgeGrp = new THREE.Group();
        this.cellarBridgeGroup = bridgeGrp;

        // Arrival Balcony connecting Lift Landing (x = 2.86, z = -0.73 .. 0.92) to Bridge Axis (x = 0.00, z = 1.35 .. 2.08)
        const liftLandingSlab = new THREE.Mesh(new RoundedBoxGeometry(1.18, 0.22, 2.26, 2, 0.02), obsidianMat);
        liftLandingSlab.position.set(2.86, UG_Y - 0.11, -0.18);
        const liftLandingTrim = new THREE.Mesh(new THREE.BoxGeometry(1.20, 0.028, 2.28), goldMat);
        liftLandingTrim.position.set(2.86, UG_Y - 0.014, -0.18);

        const vestDeck = new THREE.Mesh(new RoundedBoxGeometry(4.36, 0.22, 1.56, 2, 0.02), obsidianMat);
        vestDeck.position.set(1.28, UG_Y - 0.11, 1.32);
        const vestTop = new THREE.Mesh(new THREE.PlaneGeometry(4.28, 1.48), bridgeDeckMat);
        vestTop.rotation.x = -Math.PI / 2;
        vestTop.position.set(1.28, UG_Y + 0.003, 1.32);
        const vestTrim = new THREE.Mesh(new THREE.BoxGeometry(4.40, 0.030, 1.58), goldMat);
        vestTrim.position.set(1.28, UG_Y - 0.014, 1.32);
        bridgeGrp.add(liftLandingSlab, liftLandingTrim, vestDeck, vestTop, vestTrim);

        // Monumental Circular Vault Portal Ring framing the Bridge Entrance (z = 2.15)
        const portalOuter = new THREE.Mesh(new THREE.TorusGeometry(1.92, 0.155, 12, 52), obsidianMat);
        portalOuter.position.set(0, UG_Y + 1.56, 2.15);
        const portalGold = new THREE.Mesh(new THREE.TorusGeometry(1.75, 0.052, 10, 52), goldMat);
        portalGold.position.set(0, UG_Y + 1.56, 2.15);
        const portalHalo = new THREE.Mesh(new THREE.TorusGeometry(1.68, 0.018, 8, 52), ringStripMat);
        portalHalo.position.set(0, UG_Y + 1.56, 2.15);
        const portalCrest = new THREE.Mesh(new THREE.PlaneGeometry(1.04, 0.27), this.plaqueMats.obsidianMain);
        portalCrest.position.set(0, UG_Y + 3.32, 1.98);
        portalCrest.rotation.y = Math.PI;
        bridgeGrp.add(portalOuter, portalGold, portalHalo, portalCrest);

        // Photorealistic 5.35m Suspended Bridge Deck (z = 2.08 -> 7.43)
        const bridgeDeckTop = new THREE.Mesh(
            new THREE.PlaneGeometry(1.58, 5.35),
            bridgeDeckMat
        );
        bridgeDeckTop.rotation.x = -Math.PI / 2;
        bridgeDeckTop.position.set(0, UG_Y + 0.004, 4.755);

        const bridgeSlab = new THREE.Mesh(new RoundedBoxGeometry(1.64, 0.18, 5.36, 2, 0.02), obsidianMat);
        bridgeSlab.position.set(0, UG_Y - 0.092, 4.755);
        const bridgeSpine = new THREE.Mesh(new THREE.BoxGeometry(0.48, 0.36, 5.30), woodDarkMat);
        bridgeSpine.position.set(0, UG_Y - 0.33, 4.755);
        bridgeGrp.add(bridgeDeckTop, bridgeSlab, bridgeSpine);

        // Recessed Warm-Gold Runway Edge Light Strips + Under-Bridge Structural Ribs + Glass-Gold Balustrades
        const postGeo = new THREE.CylinderGeometry(0.020, 0.022, 0.94, 8);
        const strutGeo = new THREE.BoxGeometry(1.56, 0.065, 0.095);
        for (let s = 0; s < 8; s++) {
            const rz = lerp(2.45, 7.08, s / 7);
            const uRib = new THREE.Mesh(strutGeo, goldMat);
            uRib.position.set(0, UG_Y - 0.21, rz);
            bridgeGrp.add(uRib);
            for (const sx of [-0.76, 0.76]) {
                const post = new THREE.Mesh(postGeo, goldMat);
                post.position.set(sx, UG_Y + 0.47, rz);
                bridgeGrp.add(post);
            }
        }
        for (const sx of [-0.76, 0.76]) {
            const edgeGlow = new THREE.Mesh(new THREE.BoxGeometry(0.022, 0.016, 5.18), ringStripMat);
            edgeGlow.position.set(sx * 0.92, UG_Y + 0.010, 4.755);
            const topRail = new THREE.Mesh(new RoundedBoxGeometry(0.044, 0.038, 5.06, 1, 0.01), goldMat);
            topRail.position.set(sx, UG_Y + 0.94, 4.755);
            const midRail = new THREE.Mesh(new THREE.BoxGeometry(0.024, 0.022, 5.02), goldMat);
            midRail.position.set(sx, UG_Y + 0.49, 4.755);
            const glassPane = new THREE.Mesh(new THREE.BoxGeometry(0.016, 0.82, 4.92), glassMat);
            glassPane.position.set(sx, UG_Y + 0.47, 4.755);
            bridgeGrp.add(edgeGlow, topRail, midRail, glassPane);
        }
        domeStatic.add(bridgeGrp);

        // 4. Photorealistic Nero-Marquina & 24K-Gold Central Command Island (x = 0.00, y = UG_Y, z = 9.40, Radius = 2.35m)
        const islandTop = new THREE.Mesh(
            new THREE.CircleGeometry(2.34, 56),
            islandMarbleMat
        );
        islandTop.rotation.x = -Math.PI / 2;
        islandTop.position.set(0, UG_Y + 0.005, DOME_Z);

        const islandDrum = new THREE.Mesh(new THREE.CylinderGeometry(2.36, 2.08, 0.32, 48), obsidianMat);
        islandDrum.position.set(0, UG_Y - 0.16, DOME_Z);
        const islandGoldRim = new THREE.Mesh(new THREE.TorusGeometry(2.35, 0.035, 10, 56), goldMat);
        islandGoldRim.rotation.x = Math.PI / 2;
        islandGoldRim.position.set(0, UG_Y - 0.01, DOME_Z);
        const islandUnderCone = new THREE.Mesh(new THREE.CylinderGeometry(1.64, 0.48, 2.85, 28), woodDarkMat);
        islandUnderCone.position.set(0, UG_Y - 1.72, DOME_Z);
        const islandUnderGold = new THREE.Mesh(new THREE.TorusGeometry(1.04, 0.045, 8, 36), goldMat);
        islandUnderGold.rotation.x = Math.PI / 2;
        islandUnderGold.position.set(0, UG_Y - 1.88, DOME_Z);
        domeStatic.add(islandTop, islandDrum, islandGoldRim, islandUnderCone, islandUnderGold);

        // Curved Perimeter Guard Rail around Circular Island (leaving North Bridge Entry wide open!)
        const islandArcSpan = Math.PI * 1.76;
        const islandRail = new THREE.Mesh(new THREE.TorusGeometry(2.26, 0.028, 8, 52, islandArcSpan), goldMat);
        islandRail.rotation.x = Math.PI / 2;
        islandRail.rotation.z = -Math.PI * 0.38;
        islandRail.position.set(0, UG_Y + 0.92, DOME_Z);
        domeStatic.add(islandRail);
        for (let p = 0; p <= 10; p++) {
            const ang = -Math.PI * 0.38 + (p / 10) * islandArcSpan;
            const px = Math.cos(ang) * 2.26;
            const pz = DOME_Z + Math.sin(ang) * 2.26;
            if (pz < DOME_Z - 1.92 && Math.abs(px) < 0.84) continue;
            const rPost = new THREE.Mesh(postGeo, goldMat);
            rPost.position.set(px, UG_Y + 0.46, pz);
            domeStatic.add(rPost);
        }

        // 5. Central 24K-Gold & Obsidian Neural Projector Pedestal
        const holoBase = new THREE.Mesh(new THREE.CylinderGeometry(0.68, 0.78, 0.14, 28), obsidianMat);
        holoBase.position.set(0, UG_Y + 0.07, DOME_Z);
        const holoCol = new THREE.Mesh(new THREE.CylinderGeometry(0.48, 0.60, 0.48, 28), woodDarkMat);
        holoCol.position.set(0, UG_Y + 0.38, DOME_Z);
        const holoRim = new THREE.Mesh(new THREE.CylinderGeometry(0.72, 0.64, 0.09, 32), goldMat);
        holoRim.position.set(0, UG_Y + 0.66, DOME_Z);
        const holoLens = new THREE.Mesh(
            new THREE.CylinderGeometry(0.60, 0.60, 0.025, 32),
            new THREE.MeshBasicMaterial({ color: 0x16202e })
        );
        holoLens.position.set(0, UG_Y + 0.71, DOME_Z);
        const holoEmitterRing = new THREE.Mesh(
            new THREE.TorusGeometry(0.48, 0.018, 8, 36),
            ringStripMat
        );
        holoEmitterRing.rotation.x = Math.PI / 2;
        holoEmitterRing.position.set(0, UG_Y + 0.73, DOME_Z);
        domeStatic.add(holoBase, holoCol, holoRim, holoLens, holoEmitterRing);

        this.cellarDomeStaticGroup = domeStatic;
        freezeStaticTree(domeStatic);
        domeHall.add(domeStatic);

        // 6. Colossal 3.8-Meter 3D Spherical "Projekt-Gehirn" Constellation + Autonomous Golden Healing Wave!
        const holoGrp = new THREE.Group();
        holoGrp.position.set(0, UG_Y + 2.56, DOME_Z);
        holoGrp.matrixAutoUpdate = true;
        this.cellarHoloGroup = holoGrp;
        domeHall.add(holoGrp);

        this.cellarBrainWireMat = new THREE.MeshBasicMaterial({
            color: 0xC5A059,
            wireframe: true,
            transparent: true,
            opacity: 0.18
        });
        this.cellarBrainShellMesh = new THREE.Mesh(new THREE.IcosahedronGeometry(1.46, 2), this.cellarBrainWireMat);
        this.cellarBrainShellMesh.matrixAutoUpdate = true;
        this.cellarBrainInnerMesh = new THREE.Mesh(
            new THREE.IcosahedronGeometry(0.72, 1),
            new THREE.MeshBasicMaterial({ color: 0xE5C158, wireframe: true, transparent: true, opacity: 0.22 })
        );
        this.cellarBrainInnerMesh.matrixAutoUpdate = true;
        holoGrp.add(this.cellarBrainShellMesh, this.cellarBrainInnerMesh);

        const orbitMat = new THREE.MeshBasicMaterial({ color: 0xD9B15F, transparent: true, opacity: 0.46 });
        this.cellarBrainOrbitA = new THREE.Mesh(new THREE.TorusGeometry(1.88, 0.013, 8, 60), orbitMat);
        this.cellarBrainOrbitA.rotation.x = 0.48;
        this.cellarBrainOrbitA.matrixAutoUpdate = true;
        this.cellarBrainOrbitB = new THREE.Mesh(new THREE.TorusGeometry(1.66, 0.011, 8, 56), orbitMat);
        this.cellarBrainOrbitB.rotation.x = -0.58;
        this.cellarBrainOrbitB.rotation.y = 0.42;
        this.cellarBrainOrbitB.matrixAutoUpdate = true;
        this.cellarBrainOrbitC = new THREE.Mesh(new THREE.TorusGeometry(2.06, 0.009, 8, 64), orbitMat);
        this.cellarBrainOrbitC.rotation.x = Math.PI * 0.5;
        this.cellarBrainOrbitC.matrixAutoUpdate = true;
        holoGrp.add(this.cellarBrainOrbitA, this.cellarBrainOrbitB, this.cellarBrainOrbitC);

        // Expanding Emerald-Gold Autonomous Healing Shockwave Ring (0% idle cost, triggers on auto-heal!)
        this.cellarHealWaveMat = new THREE.MeshBasicMaterial({
            color: 0x10B981,
            transparent: true,
            opacity: 0.0,
            depthWrite: false
        });
        this.cellarHealWaveMesh = new THREE.Mesh(new THREE.TorusGeometry(0.85, 0.026, 10, 56), this.cellarHealWaveMat);
        this.cellarHealWaveMesh.rotation.x = Math.PI * 0.5;
        this.cellarHealWaveMesh.visible = false;
        this.cellarHealWaveMesh.matrixAutoUpdate = true;
        holoGrp.add(this.cellarHealWaveMesh);

        const coreGeo = new THREE.OctahedronGeometry(0.145, 1);
        const ringGeo = new THREE.TorusGeometry(0.21, 0.015, 6, 24);
        const labelGeo = new THREE.PlaneGeometry(0.94, 0.27);
        const beamGeo = new THREE.CylinderGeometry(0.013, 0.013, 1.0, 6);

        for (let b = 0; b < 16; b++) {
            const bMat = new THREE.MeshBasicMaterial({ color: 0xC5A059, transparent: true, opacity: 0.76 });
            const bMesh = new THREE.Mesh(beamGeo, bMat);
            bMesh.visible = false;
            holoGrp.add(bMesh);
            this.cellarBeamSlots.push({ mesh: bMesh, mat: bMat });
        }

        for (let n = 0; n < 14; n++) {
            const nGrp = new THREE.Group();
            nGrp.visible = false;
            nGrp.matrixAutoUpdate = true;

            const cMat = new THREE.MeshBasicMaterial({ color: 0xC5A059 });
            const cMesh = new THREE.Mesh(coreGeo, cMat);
            cMesh.userData.cellarSlotIndex = n;

            const rMat = new THREE.MeshBasicMaterial({ color: 0xFFE59E });
            const rMesh = new THREE.Mesh(ringGeo, rMat);
            rMesh.visible = false;

            const lCanvas = document.createElement('canvas');
            lCanvas.width = 320;
            lCanvas.height = 92;
            const lTex = configureFastTexture(new THREE.CanvasTexture(lCanvas));
            const lMat = new THREE.MeshBasicMaterial({ map: lTex, transparent: true, depthWrite: false });
            const lMesh = new THREE.Mesh(labelGeo, lMat);
            lMesh.position.set(0, 0.27, 0);
            lMesh.userData.cellarSlotIndex = n;

            nGrp.add(cMesh, rMesh, lMesh);
            holoGrp.add(nGrp);

            this.cellarPickMeshes.push(cMesh, lMesh);
            this.cellarNodeSlots.push({
                group: nGrp,
                coreMesh: cMesh,
                coreMat: cMat,
                ringMesh: rMesh,
                ringMat: rMat,
                labelCanvas: lCanvas,
                labelTex: lTex,
                labelMesh: lMesh,
                basePos: new THREE.Vector3(),
                baseScale: 1.0,
                pulseKind: 'calm',
                payload: null
            });
        }

        this.scene.add(cellar);

        // Build default calm constellation immediately so the dome hall looks rich even before first fetch completes
        this.setCellarSnapshot({
            status: 'healthy',
            summary: { open_issues_count: 0, project_files_count: 891, directed_edges_count: 3856 },
            error_cores: [],
            department_clusters: [
                { key: 'product', label: 'Manufaktur & Produkte', file_count: 142, open_errors: 0 },
                { key: 'order', label: 'Bestellungen & Checkout', file_count: 98, open_errors: 0 },
                { key: 'ai', label: 'KI-Agenten & Funkira', file_count: 134, open_errors: 0 },
                { key: 'accounting', label: 'Buchhaltung & Steuern', file_count: 92, open_errors: 0 },
                { key: 'management', label: 'Leitung & Funkenwerk', file_count: 108, open_errors: 0 },
                { key: 'marketing', label: 'Marketing & Influencer', file_count: 94, open_errors: 0 },
                { key: 'system', label: 'System & Projekt-Gehirn', file_count: 88, open_errors: 0 },
                { key: 'customer', label: 'Kunden & Gamification', file_count: 68, open_errors: 0 },
                { key: 'support', label: 'Support & Telefonie', file_count: 42, open_errors: 0 },
                { key: 'minipc', label: 'Mini-PC & Desktop', file_count: 25, open_errors: 0 }
            ]
        });
    }

    triggerCellarHealWave() {
        this.cellarHealAnimTimer = 2.4;
        if (this.cellarHealWaveMesh && this.cellarHealWaveMat) {
            this.cellarHealWaveMesh.visible = true;
            this.cellarHealWaveMesh.scale.setScalar(0.35);
            this.cellarHealWaveMat.opacity = 0.92;
        }
        if (this.pos.y <= -1.2) {
            this.setExpression('proud', 'cheer', 5200);
        }
    }

    _paintCellarNodeLabel(slot, title, subText, accentHex, isSelected) {
        const c = slot.labelCanvas;
        const ctx = c.getContext('2d');
        ctx.clearRect(0, 0, c.width, c.height);

        ctx.fillStyle = isSelected ? 'rgba(26, 22, 34, 0.96)' : 'rgba(14, 12, 20, 0.92)';
        ctx.strokeStyle = isSelected ? '#FFE59E' : accentHex;
        ctx.lineWidth = isSelected ? 3.5 : 2.2;
        ctx.beginPath();
        ctx.roundRect(4, 4, c.width - 8, c.height - 8, 14);
        ctx.fill();
        ctx.stroke();

        ctx.fillStyle = '#F9FAFB';
        ctx.font = '700 24px system-ui, -apple-system, sans-serif';
        ctx.textAlign = 'center';
        const cleanTitle = String(title || '').replace(/\s[\u2013\u2014]\s/g, ' · ').slice(0, 24);
        ctx.fillText(cleanTitle, c.width * 0.5, 40);

        ctx.fillStyle = isSelected ? '#FDE68A' : accentHex;
        ctx.font = '600 18px system-ui, -apple-system, sans-serif';
        const cleanSub = String(subText || '').replace(/\s[\u2013\u2014]\s/g, ' · ').slice(0, 30);
        ctx.fillText(cleanSub, c.width * 0.5, 69);

        slot.labelTex.needsUpdate = true;
    }

    _orientCellarBeam(beamSlot, pA, pB, hexColor, opacity = 0.72) {
        this._beamDirVec.subVectors(pB, pA);
        const len = this._beamDirVec.length();
        if (len < 0.02) {
            beamSlot.mesh.visible = false;
            return;
        }
        beamSlot.mesh.visible = true;
        beamSlot.mesh.position.copy(pA).addScaledVector(this._beamDirVec, 0.5);
        beamSlot.mesh.scale.set(1, len, 1);
        this._beamDirVec.normalize();
        beamSlot.mesh.quaternion.setFromUnitVectors(this._beamUpVec, this._beamDirVec);
        beamSlot.mat.color.setHex(hexColor);
        beamSlot.mat.opacity = opacity;
    }

    setCellarSnapshot(snapshot, selectedKey = '') {
        if (!snapshot || typeof snapshot !== 'object') return;

        const rawItems = Array.isArray(snapshot.items)
            ? snapshot.items
            : (Array.isArray(snapshot.error_cores) ? snapshot.error_cores : []);
        const normPathList = (arr) => (Array.isArray(arr) ? arr : [])
            .map(entry => typeof entry === 'string' ? entry : String(entry?.file_path || entry?.path || entry?.name || ''))
            .filter(Boolean);

        const normCores = rawItems.map((it, idx) => ({
            id: String(it.item_key || it.id || `issue_${idx}`),
            severity: String(it.severity || 'error'),
            platform: String(it.platform_label || it.platform || 'Portal'),
            department_label: String(it.department_label || it.component || 'System'),
            title: String(it.title || 'Systembefund'),
            message: String(it.message || ''),
            culprit_file: String(it.culprit_file || it.component || ''),
            culprit_line: it.line ?? it.culprit_line ?? null,
            occurrences: Number(it.occurrences_count ?? it.occurrences ?? 1) || 1,
            plain_german_cause: String(it.shop_impact_de || it.probable_cause || it.plain_german_cause || it.message || ''),
            incoming_files: normPathList(it.incoming_files),
            outgoing_files: normPathList(it.outgoing_files)
        }));

        const rawClusters = Array.isArray(snapshot.department_clusters) ? snapshot.department_clusters : [];
        const normClusters = rawClusters.map(cl => ({
            key: String(cl.key || 'system'),
            label: String(cl.label || cl.key || 'System'),
            file_count: Number(cl.files_count ?? cl.file_count ?? 0) || 0,
            open_errors: Number(cl.open_errors_count ?? cl.open_errors ?? 0) || 0,
            sample_files: Array.isArray(cl.sample_files) ? cl.sample_files : []
        }));

        const bs = snapshot.brain_stats || snapshot.summary || {};
        const openCount = Number(
            bs.open_issues_count ??
            ((Number(bs.open_errors_count || 0) + Number(bs.open_warnings_count || 0)) || normCores.length)
        ) || 0;

        this.cellarSnapshot = {
            ...snapshot,
            summary: {
                open_issues_count: openCount,
                project_files_count: Number(bs.total_files ?? bs.project_files_count ?? 891) || 891,
                directed_edges_count: Number(bs.total_links ?? bs.directed_edges_count ?? 3856) || 3856
            },
            error_cores: normCores,
            department_clusters: normClusters
        };

        if (this.cellarHatchLedMat) {
            this.cellarHatchLedMat.color.setHex(openCount > 0 ? 0xE11D48 : 0x10B981);
        }

        if (selectedKey) {
            this.selectedCellarKey = selectedKey;
        } else if (!this.selectedCellarKey) {
            this.selectedCellarKey = normCores.length > 0 ? String(normCores[0].id || '') : (normClusters[0] ? `cluster:${normClusters[0].key}` : 'cluster:system_security');
        } else {
            const stillExists = normCores.some(c => String(c.id) === this.selectedCellarKey)
                || this.selectedCellarKey.startsWith('cluster:')
                || this.selectedCellarKey.startsWith('file:');
            if (!stillExists) {
                this.selectedCellarKey = normCores.length > 0 ? String(normCores[0].id || '') : (normClusters[0] ? `cluster:${normClusters[0].key}` : 'cluster:system_security');
            }
        }

        this._rebuildCellarHologramLayout();
        this._redrawCellarWallBoard();
    }

    focusCellarNode(itemKey) {
        if (!itemKey) return;
        this.selectedCellarKey = String(itemKey);
        this._rebuildCellarHologramLayout();
        this._redrawCellarWallBoard();
        if (this.pos.y <= -1.0) {
            this.setExpression('curious', 'point_left', 4200);
        }
    }

    _rebuildCellarHologramLayout() {
        if (!this.cellarHoloGroup || !this.cellarSnapshot) return;
        for (const s of this.cellarNodeSlots) { s.group.visible = false; s.payload = null; s.ringMesh.visible = false; }
        for (const b of this.cellarBeamSlots) { b.mesh.visible = false; }

        const cores = Array.isArray(this.cellarSnapshot.error_cores) ? this.cellarSnapshot.error_cores : [];
        const clusters = Array.isArray(this.cellarSnapshot.department_clusters) ? this.cellarSnapshot.department_clusters : [];

        // Check if an error core is selected (or a neighbor file while an error core exists)
        let activeCore = cores.find(c => String(c.id) === this.selectedCellarKey) || null;
        if (!activeCore && this.selectedCellarKey.startsWith('file:') && cores.length > 0) {
            const fPath = this.selectedCellarKey.slice(5);
            activeCore = cores.find(c =>
                c.culprit_file === fPath ||
                (Array.isArray(c.incoming_files) && c.incoming_files.includes(fPath)) ||
                (Array.isArray(c.outgoing_files) && c.outgoing_files.includes(fPath))
            ) || cores[0];
        }
        if (!activeCore && !this.selectedCellarKey.startsWith('cluster:') && cores.length > 0) {
            activeCore = cores[0];
            this.selectedCellarKey = String(activeCore.id || '');
        }

        let slotIdx = 0;
        let beamIdx = 0;
        const baseShort = (p) => {
            const s = String(p || '').replace(/\\/g, '/');
            const idx = s.lastIndexOf('/');
            return idx >= 0 ? s.slice(idx + 1) : s;
        };

        if (activeCore) {
            // MODE A: Monumental 3D Spherical Kuppel Layout -> Central Error Core + <- Callers (Left Arc) + -> Dependencies (Right Arc) + Satellites
            const isCrit = String(activeCore.severity || 'error') !== 'warning';
            const coreHex = isCrit ? 0xE11D48 : 0xD97706;
            const coreAccent = isCrit ? '#FDA4AF' : '#FCD34D';
            if (this.cellarBrainWireMat) {
                this.cellarBrainWireMat.color.setHex(isCrit ? 0xE11D48 : 0xD97706);
                this.cellarBrainWireMat.opacity = 0.18;
            }
            const culpritBase = baseShort(activeCore.culprit_file || activeCore.component || 'System');
            const lineSuffix = activeCore.culprit_line ? `:${activeCore.culprit_line}` : '';

            const centerSlot = this.cellarNodeSlots[slotIdx++];
            centerSlot.basePos.set(0, 0.04, 0);
            centerSlot.group.position.copy(centerSlot.basePos);
            centerSlot.baseScale = 1.52;
            centerSlot.coreMesh.scale.setScalar(1.52);
            centerSlot.coreMat.color.setHex(coreHex);
            centerSlot.pulseKind = 'error';
            centerSlot.ringMesh.visible = (this.selectedCellarKey === String(activeCore.id));
            centerSlot.group.visible = true;
            centerSlot.payload = { kind: 'error', key: String(activeCore.id), core: activeCore };
            this._paintCellarNodeLabel(
                centerSlot,
                `${culpritBase}${lineSuffix}`,
                `${activeCore.platform || 'portal'} · ${activeCore.occurrences || 1}x`,
                coreAccent,
                this.selectedCellarKey === String(activeCore.id)
            );

            const incoming = (Array.isArray(activeCore.incoming_files) ? activeCore.incoming_files : []).slice(0, 4);
            for (let i = 0; i < incoming.length && slotIdx < this.cellarNodeSlots.length; i++) {
                const fPath = String(incoming[i]);
                const fKey = `file:${fPath}`;
                const sel = (this.selectedCellarKey === fKey);
                const yOff = (incoming.length === 1) ? 0.14 : lerp(0.68, -0.52, i / (incoming.length - 1));
                const xOff = -1.42 - Math.sin((i + 1) * 0.85) * 0.18;
                const zOff = (i % 2 === 0) ? -0.32 : 0.28;
                const sl = this.cellarNodeSlots[slotIdx++];
                sl.basePos.set(xOff, yOff, zOff);
                sl.group.position.copy(sl.basePos);
                sl.baseScale = sel ? 1.18 : 0.96;
                sl.coreMesh.scale.setScalar(sl.baseScale);
                sl.coreMat.color.setHex(0xE5C158);
                sl.pulseKind = 'calm';
                sl.ringMesh.visible = sel;
                sl.group.visible = true;
                sl.payload = { kind: 'file', key: fKey, filePath: fPath, direction: 'incoming', parentErrorId: String(activeCore.id) };
                this._paintCellarNodeLabel(sl, baseShort(fPath), '← Aufrufer', '#E5C158', sel);
                if (beamIdx < this.cellarBeamSlots.length) {
                    this._orientCellarBeam(this.cellarBeamSlots[beamIdx++], sl.basePos, centerSlot.basePos, 0xE5C158, sel ? 0.96 : 0.72);
                }
            }

            const outgoing = (Array.isArray(activeCore.outgoing_files) ? activeCore.outgoing_files : []).slice(0, 4);
            for (let i = 0; i < outgoing.length && slotIdx < this.cellarNodeSlots.length; i++) {
                const fPath = String(outgoing[i]);
                const fKey = `file:${fPath}`;
                const sel = (this.selectedCellarKey === fKey);
                const yOff = (outgoing.length === 1) ? 0.14 : lerp(0.68, -0.52, i / (outgoing.length - 1));
                const xOff = 1.42 + Math.sin((i + 1) * 0.85) * 0.18;
                const zOff = (i % 2 === 0) ? 0.28 : -0.32;
                const sl = this.cellarNodeSlots[slotIdx++];
                sl.basePos.set(xOff, yOff, zOff);
                sl.group.position.copy(sl.basePos);
                sl.baseScale = sel ? 1.18 : 0.96;
                sl.coreMesh.scale.setScalar(sl.baseScale);
                sl.coreMat.color.setHex(0x38BDF8);
                sl.pulseKind = 'calm';
                sl.ringMesh.visible = sel;
                sl.group.visible = true;
                sl.payload = { kind: 'file', key: fKey, filePath: fPath, direction: 'outgoing', parentErrorId: String(activeCore.id) };
                this._paintCellarNodeLabel(sl, baseShort(fPath), '→ Referenz', '#7DD3FC', sel);
                if (beamIdx < this.cellarBeamSlots.length) {
                    this._orientCellarBeam(this.cellarBeamSlots[beamIdx++], centerSlot.basePos, sl.basePos, 0x38BDF8, sel ? 0.96 : 0.72);
                }
            }

            // Satellite nodes in upper dome crown for additional open errors so Alina can click directly between them in 3D
            const otherCores = cores.filter(c => String(c.id) !== String(activeCore.id)).slice(0, 4);
            for (let i = 0; i < otherCores.length && slotIdx < this.cellarNodeSlots.length; i++) {
                const oc = otherCores[i];
                const sl = this.cellarNodeSlots[slotIdx++];
                const ang = lerp(-1.05, 1.05, otherCores.length === 1 ? 0.5 : (i / (otherCores.length - 1)));
                sl.basePos.set(Math.sin(ang) * 1.18, 1.02, -0.42 - Math.cos(ang) * 0.22);
                sl.group.position.copy(sl.basePos);
                sl.baseScale = 0.98;
                sl.coreMesh.scale.setScalar(0.98);
                sl.coreMat.color.setHex(String(oc.severity) === 'warning' ? 0xD97706 : 0xE11D48);
                sl.pulseKind = 'error';
                sl.group.visible = true;
                sl.payload = { kind: 'error', key: String(oc.id), core: oc };
                this._paintCellarNodeLabel(sl, baseShort(oc.culprit_file || oc.component || 'Befund'), `${oc.platform || 'portal'} · Wechsel`, '#FDA4AF', false);
            }
            return;
        }

        // MODE B: Calm 360° Spherical 10-Department Seelenfunke Project-Brain Constellation inside the Kuppel
        if (this.cellarBrainWireMat) {
            this.cellarBrainWireMat.color.setHex(cores.length > 0 ? 0xE11D48 : 0xC5A059);
            this.cellarBrainWireMat.opacity = 0.16;
        }
        const hubSlot = this.cellarNodeSlots[slotIdx++];
        const filesTotal = Number(this.cellarSnapshot.summary?.project_files_count || 891);
        const edgesTotal = Number(this.cellarSnapshot.summary?.directed_edges_count || 3856);
        hubSlot.basePos.set(0, 0.06, 0);
        hubSlot.group.position.copy(hubSlot.basePos);
        hubSlot.baseScale = 1.42;
        hubSlot.coreMesh.scale.setScalar(1.42);
        hubSlot.coreMat.color.setHex(cores.length > 0 ? 0xE11D48 : 0x10B981);
        hubSlot.pulseKind = cores.length > 0 ? 'error' : 'calm';
        hubSlot.group.visible = true;
        hubSlot.payload = { kind: 'hub', key: cores.length > 0 ? String(cores[0].id) : 'cluster:system' };
        this._paintCellarNodeLabel(hubSlot, 'Projekt-Gehirn', `${filesTotal} Dateien · ${edgesTotal} Kanten`, '#6EE7B7', false);

        const list = clusters.slice(0, 10);
        for (let i = 0; i < list.length && slotIdx < this.cellarNodeSlots.length; i++) {
            const cl = list[i];
            const cKey = `cluster:${cl.key}`;
            const sel = (this.selectedCellarKey === cKey);
            const ang = (i / Math.max(1, list.length)) * Math.PI * 2;
            const tier = (i % 3) - 1; // -1, 0, +1 spherical elevation bands
            const rx = Math.cos(ang) * 1.56;
            const rz = Math.sin(ang) * 0.78;
            const ry = tier * 0.56 + ((i % 2 === 0) ? 0.10 : -0.08);
            const hasErr = Number(cl.open_errors || 0) > 0;

            const sl = this.cellarNodeSlots[slotIdx++];
            sl.basePos.set(rx, ry, rz);
            sl.group.position.copy(sl.basePos);
            sl.baseScale = sel ? 1.18 : 0.94;
            sl.coreMesh.scale.setScalar(sl.baseScale);
            sl.coreMat.color.setHex(hasErr ? 0xE11D48 : 0xC5A059);
            sl.pulseKind = hasErr ? 'error' : 'calm';
            sl.ringMesh.visible = sel;
            sl.group.visible = true;
            sl.payload = { kind: 'cluster', key: cKey, cluster: cl };
            this._paintCellarNodeLabel(
                sl,
                cl.label || cl.key,
                hasErr ? `${cl.open_errors} Befund · ${cl.file_count || 0} Dateien` : `${cl.file_count || 0} Dateien`,
                hasErr ? '#FDA4AF' : '#E5C158',
                sel
            );
            if (beamIdx < this.cellarBeamSlots.length) {
                this._orientCellarBeam(this.cellarBeamSlots[beamIdx++], hubSlot.basePos, sl.basePos, hasErr ? 0xE11D48 : 0xC5A059, sel ? 0.92 : 0.56);
            }
        }
    }

    _redrawCellarWallBoard() {
        // Pure Full-Screen Cinema Kuppelhalle: zero clutter wall-boards or control windows!
    }

    tryPickCellarNodeAtClientXY(clientX, clientY) {
        if (!this.canvas || !this.camera || !this.cellarGroup || !this.cellarGroup.visible) return null;
        if (this.pos.y > -0.85) return null;
        const rect = this.canvas.getBoundingClientRect();
        if (!rect.width || !rect.height) return null;
        this._pointerNdc.x = ((clientX - rect.left) / rect.width) * 2 - 1;
        this._pointerNdc.y = -((clientY - rect.top) / rect.height) * 2 + 1;
        this._raycaster.setFromCamera(this._pointerNdc, this.camera);

        const visibleTargets = this.cellarPickMeshes.filter(m => m.parent && m.parent.visible);
        if (visibleTargets.length === 0) return null;
        const hits = this._raycaster.intersectObjects(visibleTargets, false);
        if (!hits || hits.length === 0) return null;

        const slotIdx = hits[0].object?.userData?.cellarSlotIndex;
        const slot = (typeof slotIdx === 'number') ? this.cellarNodeSlots[slotIdx] : null;
        if (!slot || !slot.payload) return null;

        this.focusCellarNode(slot.payload.key);
        if (typeof window.onFunkiraCellarNodePicked === 'function') {
            window.onFunkiraCellarNodePicked(slot.payload);
        }
        return slot.payload;
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
        const hipMesh = new THREE.Mesh(new THREE.LatheGeometry(hipPoints, 28), darkArmorMat);
        hipMesh.scale.set(1.04, 1.0, 0.86);
        pelvis.add(hipMesh);

        const waistRing = new THREE.Mesh(new THREE.TorusGeometry(0.232, 0.025, 14, 32), roseGoldMat);
        waistRing.rotation.x = Math.PI / 2;
        waistRing.position.y = 0.072;
        const beltBuckleBack = new THREE.Mesh(new RoundedBoxGeometry(0.088, 0.052, 0.024, 2, 0.010), this.mats.obsidian);
        beltBuckleBack.position.set(0, 0.072, 0.206);
        const beltBuckleRim = new THREE.Mesh(new RoundedBoxGeometry(0.096, 0.058, 0.018, 2, 0.010), gold24kMat);
        beltBuckleRim.position.set(0, 0.072, 0.203);
        const beltStarGem = new THREE.Mesh(new THREE.OctahedronGeometry(0.020, 0), gold24kMat);
        beltStarGem.scale.set(1.0, 1.25, 0.45);
        beltStarGem.position.set(0, 0.072, 0.218);
        pelvis.add(waistRing, beltBuckleRim, beltBuckleBack, beltStarGem);

        const thighGeo = new THREE.CylinderGeometry(0.068, 0.058, 0.20, 22);
        const kneeGeo  = new THREE.SphereGeometry(0.058, 16, 12);
        const kneeRingGeo = new THREE.TorusGeometry(0.048, 0.007, 8, 20);
        const shinGeo  = new THREE.CylinderGeometry(0.062, 0.076, 0.19, 22);
        const cuffGeo  = new THREE.TorusGeometry(0.078, 0.017, 12, 28);
        const bootUpperGeo = new RoundedBoxGeometry(0.165, 0.115, 0.235, 3, 0.048);
        const bootWeltGeo  = new RoundedBoxGeometry(0.170, 0.013, 0.242, 2, 0.014);
        const bootSoleGeo  = new RoundedBoxGeometry(0.172, 0.034, 0.245, 2, 0.014);

        const buildLeg = (sideSign) => {
            const hipPivot = new THREE.Group();
            hipPivot.position.set(sideSign * 0.115, -0.04, 0);
            pelvis.add(hipPivot);

            const thigh = new THREE.Mesh(thighGeo, darkArmorMat);
            thigh.position.y = -0.10;
            const kneeJoint = new THREE.Mesh(kneeGeo, roseGoldMat);
            kneeJoint.position.y = -0.20;
            const kneeGoldRing = new THREE.Mesh(kneeRingGeo, gold24kMat);
            kneeGoldRing.position.set(0, -0.20, 0.022);
            hipPivot.add(thigh, kneeJoint, kneeGoldRing);

            const kneePivot = new THREE.Group();
            kneePivot.position.y = -0.20;
            hipPivot.add(kneePivot);

            const shin = new THREE.Mesh(shinGeo, darkArmorMat);
            shin.position.y = -0.095;
            const bootCuff = new THREE.Mesh(cuffGeo, roseGoldMat);
            bootCuff.rotation.x = Math.PI / 2;
            bootCuff.position.y = -0.168;
            const bootBuckle = new THREE.Mesh(new RoundedBoxGeometry(0.066, 0.020, 0.016, 1, 0.005), gold24kMat);
            bootBuckle.position.set(0, -0.170, 0.096);
            const bootUpper = new THREE.Mesh(bootUpperGeo, roseGoldMat);
            bootUpper.position.set(0, -0.215, 0.032);
            const bootWelt = new THREE.Mesh(bootWeltGeo, gold24kMat);
            bootWelt.position.set(0, -0.252, 0.032);
            const bootSole = new THREE.Mesh(bootSoleGeo, jointDarkMat);
            bootSole.position.set(0, -0.268, 0.032);
            kneePivot.add(shin, bootCuff, bootBuckle, bootUpper, bootWelt, bootSole);

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
        const chestMesh = new THREE.Mesh(new THREE.LatheGeometry(torsoPoints, 32), darkArmorMat);
        chestMesh.scale.set(1.08, 1.0, 0.84);
        torsoPivot.add(chestMesh);

        const emblemGeo = new THREE.CylinderGeometry(0.268, 0.242, 0.26, 32, 1, true, -0.78, 1.56);
        const emblemMesh = new THREE.Mesh(
            emblemGeo,
            new THREE.MeshBasicMaterial({ map: makeChestEmblemTexture(), transparent: true, depthWrite: false })
        );
        emblemMesh.scale.set(1.115, 1.0, 0.885);
        emblemMesh.position.y = 0.22;
        torsoPivot.add(emblemMesh);

        // Rear Seelenfunke Power-Core Medallion (visible when Funkira turns around!)
        const backCoreRing = new THREE.Mesh(new THREE.TorusGeometry(0.068, 0.011, 10, 24), gold24kMat);
        backCoreRing.position.set(0, 0.25, -0.218);
        const backCorePlate = new THREE.Mesh(new THREE.CylinderGeometry(0.058, 0.058, 0.014, 20), this.mats.obsidian);
        backCorePlate.rotation.x = Math.PI / 2;
        backCorePlate.position.set(0, 0.25, -0.216);
        const backCoreStar = new THREE.Mesh(new THREE.OctahedronGeometry(0.026, 0), this.mats.warmBulb);
        backCoreStar.scale.set(1.0, 1.25, 0.35);
        backCoreStar.position.set(0, 0.25, -0.224);
        torsoPivot.add(backCoreRing, backCorePlate, backCoreStar);

        const collarRing = new THREE.Mesh(new THREE.TorusGeometry(0.186, 0.036, 14, 32), roseGoldMat);
        collarRing.rotation.x = Math.PI / 2;
        collarRing.position.y = 0.432;
        const collarGoldTrim = new THREE.Mesh(new THREE.TorusGeometry(0.194, 0.009, 10, 32), gold24kMat);
        collarGoldTrim.rotation.x = Math.PI / 2;
        collarGoldTrim.position.y = 0.408;
        const collarBrooch = new THREE.Mesh(new THREE.OctahedronGeometry(0.024, 0), gold24kMat);
        collarBrooch.scale.set(1.0, 1.3, 0.45);
        collarBrooch.position.set(0, 0.422, 0.206);
        const neckCyl = new THREE.Mesh(new THREE.CylinderGeometry(0.10, 0.12, 0.11, 20), jointDarkMat);
        neckCyl.position.y = 0.47;
        torsoPivot.add(collarRing, collarGoldTrim, collarBrooch, neckCyl);

        const scarfGroup = new THREE.Group();
        const scarfRing = new THREE.Mesh(new THREE.TorusGeometry(0.205, 0.052, 14, 30), this.mats.scarfRed);
        scarfRing.rotation.x = Math.PI / 2;
        scarfRing.position.y = 0.43;
        const scarfTail = new THREE.Mesh(new RoundedBoxGeometry(0.095, 0.26, 0.038, 2, 0.014), this.mats.scarfRed);
        scarfTail.position.set(0.11, 0.30, 0.215);
        scarfTail.rotation.z = 0.14;
        const scarfGoldTip = new THREE.Mesh(new RoundedBoxGeometry(0.098, 0.022, 0.040, 1, 0.006), gold24kMat);
        scarfGoldTip.position.set(0.093, 0.175, 0.216);
        scarfGoldTip.rotation.z = 0.14;
        scarfGroup.add(scarfRing, scarfTail, scarfGoldTip);
        torsoPivot.add(scarfGroup);
        this.outfits.scarf = scarfGroup;

        const shCuffGeo    = new THREE.TorusGeometry(0.092, 0.023, 12, 26);
        const shGoldRimGeo = new THREE.TorusGeometry(0.098, 0.008, 10, 26);
        const shPauldronGeo = new THREE.SphereGeometry(0.096, 18, 12, 0, Math.PI * 2, 0, Math.PI * 0.48);
        const shBallGeo    = new THREE.SphereGeometry(0.074, 16, 14);
        const upperArmGeo  = new THREE.CylinderGeometry(0.068, 0.060, 0.21, 22);
        const upTrimGeo    = new THREE.TorusGeometry(0.064, 0.011, 10, 24);
        const elBallGeo    = new THREE.SphereGeometry(0.058, 14, 12);
        const forearmGeo   = new THREE.CylinderGeometry(0.062, 0.075, 0.21, 22);
        const wrCuffGeo    = new THREE.TorusGeometry(0.073, 0.016, 12, 24);
        const wrGoldGeo    = new THREE.TorusGeometry(0.077, 0.006, 8, 24);
        const palmGeo      = new THREE.SphereGeometry(0.075, 16, 14);
        const fingerProxGeo = new THREE.CapsuleGeometry(0.0145, 0.016, 6, 10);
        const fingerDistGeo = new THREE.CapsuleGeometry(0.0125, 0.014, 6, 10);
        const knuckleRingGeo = new THREE.TorusGeometry(0.0148, 0.0032, 6, 14);

        const buildArm = (sideSign, hasBossShield) => {
            const cuffHolder = new THREE.Group();
            cuffHolder.position.set(sideSign * 0.295, 0.345, 0.0);
            torsoPivot.add(cuffHolder);

            const shoulderCuff = new THREE.Mesh(shCuffGeo, roseGoldMat);
            shoulderCuff.rotation.y = sideSign * (Math.PI / 2 - 0.22);
            const shoulderGoldRim = new THREE.Mesh(shGoldRimGeo, gold24kMat);
            shoulderGoldRim.rotation.y = sideSign * (Math.PI / 2 - 0.22);
            shoulderGoldRim.position.x = sideSign * 0.012;
            const pauldron = new THREE.Mesh(shPauldronGeo, roseGoldMat);
            pauldron.scale.set(1.05, 0.68, 0.96);
            pauldron.position.set(sideSign * 0.018, 0.022, 0);
            pauldron.rotation.z = -sideSign * 0.42;
            cuffHolder.add(shoulderCuff, shoulderGoldRim, pauldron);

            const shoulderPivot = new THREE.Group();
            shoulderPivot.position.set(sideSign * 0.015, -0.005, 0);
            cuffHolder.add(shoulderPivot);

            const shoulderBall = new THREE.Mesh(shBallGeo, jointDarkMat);
            const upperArm = new THREE.Mesh(upperArmGeo, darkArmorMat);
            upperArm.position.y = -0.11;
            const upperTrim = new THREE.Mesh(upTrimGeo, gold24kMat);
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
            const wristGold = new THREE.Mesh(wrGoldGeo, gold24kMat);
            wristGold.rotation.x = Math.PI / 2;
            wristGold.position.y = -0.192;
            elbowPivot.add(wristCuff, wristGold);

            const wristPivot = new THREE.Group();
            wristPivot.position.y = -0.235;
            elbowPivot.add(wristPivot);

            const palm = new THREE.Mesh(palmGeo, roseGoldMat);
            palm.scale.set(1.02, 0.92, 0.74);
            palm.position.y = -0.032;
            const palmEmblem = new THREE.Mesh(new THREE.OctahedronGeometry(0.015, 0), gold24kMat);
            palmEmblem.scale.set(1.0, 1.2, 0.35);
            palmEmblem.position.set(0, -0.030, -0.054);
            wristPivot.add(palm, palmEmblem);

            const fingers = [];
            const fingerOffsets = [-0.046, -0.016, 0.015, 0.045];
            const fingerScales = [0.88, 1.0, 0.98, 0.82];
            for (let f = 0; f < 4; f++) {
                const fp = new THREE.Group();
                fp.position.set(fingerOffsets[f], -0.080, 0.008);
                const sc = fingerScales[f];
                const prox = new THREE.Mesh(fingerProxGeo, roseGoldMat);
                prox.scale.y = sc;
                prox.position.y = -0.012 * sc;
                const kRing = new THREE.Mesh(knuckleRingGeo, gold24kMat);
                kRing.rotation.x = Math.PI / 2;
                kRing.position.y = -0.023 * sc;
                const dist = new THREE.Mesh(fingerDistGeo, roseGoldMat);
                dist.scale.y = sc;
                dist.position.set(0, -0.034 * sc, 0.002);
                dist.rotation.x = -0.10;
                fp.add(prox, kRing, dist);
                wristPivot.add(fp);
                fingers.push(fp);
            }

            const thumbPivot = new THREE.Group();
            thumbPivot.position.set(-sideSign * 0.062, -0.038, 0.022);
            thumbPivot.rotation.z = -sideSign * 0.62;
            const thumbProx = new THREE.Mesh(fingerProxGeo, roseGoldMat);
            thumbProx.position.y = -0.012;
            const thumbRing = new THREE.Mesh(knuckleRingGeo, gold24kMat);
            thumbRing.rotation.x = Math.PI / 2;
            thumbRing.position.y = -0.022;
            const thumbDist = new THREE.Mesh(fingerDistGeo, roseGoldMat);
            thumbDist.position.y = -0.032;
            thumbPivot.add(thumbProx, thumbRing, thumbDist);
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
            (this.upstairsFxGroup || this.scene).add(wd);
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
            curveSegments: 12,
            bevelEnabled: true,
            bevelThickness: 0.038,
            bevelSize: 0.032,
            bevelSegments: 5
        });
        headGeo.center();
        const headShell = new THREE.Mesh(headGeo, roseGoldMat);
        headShell.position.y = 0.26;
        headPivot.add(headShell);

        const bezelFrame = new THREE.Mesh(new RoundedBoxGeometry(0.718, 0.472, 0.016, 3, 0.046), darkArmorMat);
        bezelFrame.position.set(0, 0.26, headD * 0.5 + 0.032);
        const goldInnerBezel = new THREE.Mesh(new RoundedBoxGeometry(0.698, 0.452, 0.012, 3, 0.042), gold24kMat);
        goldInnerBezel.position.set(0, 0.26, headD * 0.5 + 0.037);
        headPivot.add(bezelFrame, goldInnerBezel);

        this.visorTexture = configureFastTexture(new THREE.CanvasTexture(this.visorCanvas));
        const visorPlane = new THREE.Mesh(
            new THREE.PlaneGeometry(0.688, 0.442),
            new THREE.MeshBasicMaterial({ map: this.visorTexture })
        );
        visorPlane.position.set(0, 0.26, headD * 0.5 + 0.047);
        headPivot.add(visorPlane);

        const sirenBase = new THREE.Mesh(new THREE.CylinderGeometry(0.116, 0.140, 0.046, 24), roseGoldMat);
        sirenBase.position.set(0, 0.578, 0);
        const sirenGoldCollar = new THREE.Mesh(new THREE.TorusGeometry(0.118, 0.010, 10, 24), gold24kMat);
        sirenGoldCollar.rotation.x = Math.PI / 2;
        sirenGoldCollar.position.set(0, 0.600, 0);
        this.sirenMat = new THREE.MeshBasicMaterial({ color: 0xe8829b });
        const sirenDome = new THREE.Mesh(new THREE.CylinderGeometry(0.084, 0.102, 0.112, 24), this.sirenMat);
        sirenDome.position.set(0, 0.648, 0);
        const sirenCrystalBell = new THREE.Mesh(new THREE.CylinderGeometry(0.098, 0.116, 0.120, 24), this.mats.crystalGlass);
        sirenCrystalBell.position.set(0, 0.650, 0);
        const sirenCap = new THREE.Mesh(new THREE.CylinderGeometry(0.052, 0.094, 0.020, 24), gold24kMat);
        sirenCap.position.set(0, 0.714, 0);
        const sirenStar = new THREE.Mesh(new THREE.OctahedronGeometry(0.018, 0), gold24kMat);
        sirenStar.position.set(0, 0.734, 0);
        headPivot.add(sirenBase, sirenGoldCollar, sirenDome, sirenCrystalBell, sirenCap, sirenStar);
        this.rig.sirenDome = sirenDome;

        const earGeo = new THREE.CylinderGeometry(0.114, 0.102, 0.068, 24);
        const earRingGeo = new THREE.TorusGeometry(0.096, 0.012, 10, 24);
        const earGoldRingGeo = new THREE.TorusGeometry(0.068, 0.008, 10, 24);
        const earCapGeo = new THREE.CylinderGeometry(0.046, 0.046, 0.012, 20);
        for (const sx of [-1, 1]) {
            const earPod = new THREE.Mesh(earGeo, roseGoldMat);
            earPod.rotation.z = sx * Math.PI / 2;
            earPod.position.set(sx * (headW * 0.5 + 0.050), 0.25, 0);
            const earRing = new THREE.Mesh(earRingGeo, darkArmorMat);
            earRing.rotation.y = Math.PI / 2;
            earRing.position.set(sx * (headW * 0.5 + 0.084), 0.25, 0);
            const earGoldRing = new THREE.Mesh(earGoldRingGeo, gold24kMat);
            earGoldRing.rotation.y = Math.PI / 2;
            earGoldRing.position.set(sx * (headW * 0.5 + 0.088), 0.25, 0);
            const earCap = new THREE.Mesh(earCapGeo, roseGoldMat);
            earCap.rotation.z = sx * Math.PI / 2;
            earCap.position.set(sx * (headW * 0.5 + 0.087), 0.25, 0);
            headPivot.add(earPod, earRing, earGoldRing, earCap);
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
            lens.position.set(sx * 0.16, 0.44, headD * 0.5 + 0.060);
            const rim = new THREE.Mesh(new RoundedBoxGeometry(0.255, 0.125, 0.010, 1, 0.02), gold24kMat);
            rim.position.set(sx * 0.16, 0.44, headD * 0.5 + 0.056);
            sunglasses.add(rim, lens);
        }
        const bridge = new THREE.Mesh(new THREE.BoxGeometry(0.09, 0.02, 0.015), gold24kMat);
        bridge.position.set(0, 0.45, headD * 0.5 + 0.060);
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

        const visorActiveAnim = this.isSpeaking || this.blinkVal > 0.002 || animTick !== 0;
        const minVisorIntervalMs = visorActiveAnim ? 33 : 62;
        if (!force && (nowMs - this._lastVisorDrawMs) < minVisorIntervalMs) return;

        const gazeQuant = visorActiveAnim ? 2.0 : 1.0;
        const sig = `${Math.round(gx * gazeQuant)}|${Math.round(gy * gazeQuant)}|${Math.round(lidTop * 80)}|${Math.round(lidBot * 60)}|${Math.round(openAmt * 70)}|${Math.round(curve * 40)}|${Math.round((this.faceCur.browTilt || 0) * 40)}|${animTick}|${this.emotion}`;
        if (!force && sig === this._lastDrawnSig) return;
        this._lastDrawnSig = sig;
        this._lastVisorDrawMs = nowMs;

        const ctx = this.visorCtx;
        ctx.drawImage(this.visorBgCanvas, 0, 0);
        ctx.save();
        ctx.scale(2, 2);

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
                ctx.drawImage(this.blushSpriteCanvas, ex + side * 10 - 32, eyeY + 52 - 20, 64, 40);
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

        ctx.restore();

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
        if (winElapsed >= 2000) {
            this.fps = Math.round((this._fpsFrames * 1000) / winElapsed);
            this._fpsFrames = 0;
            this._fpsWindowStartMs = nowMs;
            if (this.fps < 48 && this.renderScale > 0.62) {
                this._lowFpsStreaks++;
                this._highFpsStreaks = 0;
                if (this._lowFpsStreaks >= 2) {
                    this._lowFpsStreaks = 0;
                    this._scaleLockedDown = true;
                    this.renderScale = Math.max(0.62, +(this.renderScale - 0.08).toFixed(2));
                    this.resize(false);
                }
            } else if (!this._scaleLockedDown && !this.isLowPowerGpu && this.fps >= 59 && this.renderScale < 1.00) {
                this._highFpsStreaks++;
                this._lowFpsStreaks = 0;
                if (this._highFpsStreaks >= 3) {
                    this._highFpsStreaks = 0;
                    this.renderScale = Math.min(1.00, +(this.renderScale + 0.04).toFixed(2));
                    this.resize(false);
                }
            } else {
                this._lowFpsStreaks = 0;
                this._highFpsStreaks = 0;
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

        // Smooth 2-Floor, Staircase & Vertical Hydraulic Vault Lift Locomotion
        let isRidingLift = false;
        let isWalking = false;

        if (this.targetFloor === 'cellar_stairs') {
            this.pos.x = lerp(this.pos.x, 2.86, 1 - Math.exp(-dt * 14));
            this.pos.z = lerp(this.pos.z, -0.73, 1 - Math.exp(-dt * 14));
            const dy = this.targetPos.y - this.pos.y;
            const yDist = Math.abs(dy);
            if (yDist <= 0.035) {
                this.pos.y = this.targetPos.y;
                if (this.waypoints && this.waypoints.length > 0) {
                    const nxt = this.waypoints.shift();
                    this.targetPos.set(nxt.x, nxt.y, nxt.z);
                    this.targetFloor = nxt.floor;
                }
            } else {
                isRidingLift = true;
                const liftSpeed = 1.92;
                this.pos.y = clamp(this.pos.y + Math.sign(dy) * Math.min(yDist, liftSpeed * dt), UG_Y, 0.0);
                let diff = 0.0 - this.facingYaw;
                while (diff > Math.PI) diff -= Math.PI * 2;
                while (diff < -Math.PI) diff += Math.PI * 2;
                this.facingYaw += diff * (1 - Math.exp(-dt * 9.5));
                if (this.rig.broom) this.rig.broom.visible = false;
                if (this.rig.cloth) this.rig.cloth.visible = false;
                if (this.rig.wateringCan) this.rig.wateringCan.visible = false;
                if (this.rig.gardenTrowel) this.rig.gardenTrowel.visible = false;
            }
        }

        if (!isRidingLift && this.targetFloor !== 'cellar_stairs') {
            let dx = this.targetPos.x - this.pos.x;
            let dz = this.targetPos.z - this.pos.z;
            let dist = Math.hypot(dx, dz);
            if (dist <= 0.065 && this.waypoints && this.waypoints.length > 0) {
                const nxt = this.waypoints.shift();
                this.targetPos.set(nxt.x, nxt.y, nxt.z);
                this.targetFloor = nxt.floor;
                if (this.targetFloor !== 'cellar_stairs') {
                    dx = this.targetPos.x - this.pos.x;
                    dz = this.targetPos.z - this.pos.z;
                    dist = Math.hypot(dx, dz);
                } else {
                    dist = 0;
                    isRidingLift = true;
                }
            }
            isWalking = !isRidingLift && dist > 0.045;

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
            }
        }

        if (!isWalking && !isRidingLift) {
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

        // Smooth Indoor/Outdoor/Cellar Sky Background Blend & Hysteresis Hydraulic Lift Cut
        const showCellar = (this.pos.y < -0.02) || (this.targetFloor === 'cellar_stairs') || (this.targetFloor === -1);
        const deepThreshold = this._wasDeepInCellar ? -1.56 : -1.76;
        const deepInCellar = (this.pos.y <= deepThreshold);
        if (this.cellarLiftPlatform) {
            this.cellarLiftPlatform.position.y = (isRidingLift || (this.pos.y < -0.02 && this.pos.y > UG_Y + 0.02))
                ? this.pos.y
                : (deepInCellar ? UG_Y : 0.0);
        }
        if (deepInCellar !== Boolean(this._wasDeepInCellar)) {
            this._wasDeepInCellar = deepInCellar;
            if (deepInCellar) {
                // Clean Cinema Cut into Subterranean Arrival Balcony looking through the Round 24K-Gold Vault Portal across the 5.35m Bridge!
                this.camera.position.set(1.18, UG_Y + 2.28, -1.72);
                this.cameraLook.set(0.08, UG_Y + 1.92, 9.40);
                this.camera.lookAt(this.cameraLook);
            } else {
                // Clean Cinema Cut back to Heizungsraum Hydraulic Vault Lift when ascending above ceiling slab
                this.camera.position.set(2.26, 2.92, 2.65);
                this.cameraLook.set(2.86, this.pos.y + 0.85, -0.73);
                this.camera.lookAt(this.cameraLook);
            }
        }
        const bgTarget = deepInCellar
            ? this.cellarBgColor
            : ((isOutdoors || this.worldState.occasion === 'silvester' || this.worldState.occasion === 'new_year')
                ? this.outdoorSkyColor
                : this.indoorBgColor);
        this.scene.background.lerp(bgTarget, 1 - Math.exp(-dt * 2.6));

        // ====================================================================
        // SMART 2-FLOOR DOLLHOUSE, KUPPELHALLE BRIDGE, TERRACE/GARDEN & LANDMARK CAMERA GLIDING
        // ====================================================================
        const useDollhouseView = (this.mode === 'atelier_autonomous') || (this.pos.y < OG_Y - 0.15) || (this.pos.z < 2.35);

        let camTargetX, camTargetY, camTargetZ, lookX, lookY, lookZ;
        if (deepInCellar) {
            if (this.pos.z < 7.15) {
                // Cinematic Bridge Walk Tracking: Camera glides from Arrival Balcony through the Round Vault Portal down the 5.35m bridge!
                const bridgeT = clamp((this.pos.z - 0.92) / (7.15 - 0.92), 0, 1);
                camTargetX = lerp(1.05, 0.00, bridgeT);
                camTargetY = lerp(UG_Y + 2.28, UG_Y + 2.38, bridgeT);
                camTargetZ = clamp(this.pos.z - 3.05, -1.72, 2.82);
                lookX = lerp(0.12, 0.00, bridgeT);
                lookY = UG_Y + 1.95;
                lookZ = 9.40;
            } else {
                // Pure Full-Screen Symmetrical Kuppelhalle Panorama: Zero control windows, dead-center 25.2m Dome + 3.8m 3D Project-Brain + Nero-Marquina Island!
                camTargetX = 0.00;
                camTargetY = UG_Y + 2.38;
                camTargetZ = 2.82;
                lookX = 0.00;
                lookY = UG_Y + 1.96;
                lookZ = 9.40;
            }
        } else if (this.pos.y < -0.02 && !deepInCellar) {
            // Descending / Ascending Hydraulic Vault Lift in Heizungsraum (0% ceiling clipping!)
            camTargetX = 2.26;
            camTargetY = 2.92;
            camTargetZ = 2.65;
            lookX = 2.86;
            lookY = this.pos.y + 0.85;
            lookZ = -0.73;
        } else if (!useDollhouseView) {
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

        // Strict Floor, Roof, Atelier-Interior & Secret Cellar Visibility Culling
        // (0% Cellar Load Upstairs; 0% Downstairs/Garden/North-OG Load when Camera is inside Seelenfunken Atelier!)
        if (this.cellarGroup && this.cellarGroup.visible !== showCellar) {
            this.cellarGroup.visible = showCellar;
        }
        if (this.cellarDomeShellGroup && this.cellarDomeShellGroup.visible !== deepInCellar) {
            this.cellarDomeShellGroup.visible = deepInCellar;
        }
        const showUpstairsHouse = !deepInCellar;
        const cameraInsideClosedAtelier = showUpstairsHouse
            && !useDollhouseView
            && !showCellar
            && (this.camera.position.z < 6.45)
            && (this.camera.position.y < OG_Y + 2.25);
        const showLowerAndGarden = showUpstairsHouse && !cameraInsideClosedAtelier;

        if (this.egGroup && this.egGroup.visible !== showLowerAndGarden) this.egGroup.visible = showLowerAndGarden;
        if (this.egItemsGroup && this.egItemsGroup.visible !== showLowerAndGarden) this.egItemsGroup.visible = showLowerAndGarden;
        if (this.egWeatherGroup && this.egWeatherGroup.visible !== showLowerAndGarden) this.egWeatherGroup.visible = showLowerAndGarden;
        if (this.stairsGroup && this.stairsGroup.visible !== showLowerAndGarden) this.stairsGroup.visible = showLowerAndGarden;
        if (this.gardenGroup && this.gardenGroup.visible !== showLowerAndGarden) this.gardenGroup.visible = showLowerAndGarden;
        if (this.lawnMowerGroup && this.lawnMowerGroup.visible !== showLowerAndGarden) this.lawnMowerGroup.visible = showLowerAndGarden;
        if (this.cellarLiftPlatform && this.cellarLiftPlatform.visible !== (showLowerAndGarden || showCellar)) {
            this.cellarLiftPlatform.visible = (showLowerAndGarden || showCellar);
        }
        if (this.upstairsFxGroup && this.upstairsFxGroup.visible !== showUpstairsHouse) this.upstairsFxGroup.visible = showUpstairsHouse;

        const showOg = showUpstairsHouse && !(useDollhouseView && this.pos.y < 1.95);
        const showOgNorth = showOg && !cameraInsideClosedAtelier;
        const showClosedAtelierGable = showOg && !useDollhouseView && (this.camera.position.z < 6.75);
        if (this.ogGroup && this.ogGroup.visible !== showOg) {
            this.ogGroup.visible = showOg;
        }
        if (this.ogNorthStaticGroup && this.ogNorthStaticGroup.visible !== showOgNorth) {
            this.ogNorthStaticGroup.visible = showOgNorth;
        }
        if (this.ogRoofClosedGroup && this.ogRoofClosedGroup.visible !== showClosedAtelierGable) {
            this.ogRoofClosedGroup.visible = showClosedAtelierGable;
        }
        if (this.ogWeatherGroup && this.ogWeatherGroup.visible !== showOgNorth) {
            this.ogWeatherGroup.visible = showOgNorth;
        }
        if (this.ogSouthWeatherGroup && this.ogSouthWeatherGroup.visible !== showClosedAtelierGable) {
            this.ogSouthWeatherGroup.visible = showClosedAtelierGable;
        }

        // 60-FPS Zero-GC Spherical Project-Brain Pulse, Gyroscope Orbits, Healing Wave & Camera-Facing Labels (Runs ONLY when deepInCellar === true!)
        if (deepInCellar && this.cellarHoloGroup) {
            this.cellarHoloGroup.position.y = UG_Y + 2.56 + Math.sin(tSec * 1.65) * 0.035;
            if (this.cellarBrainShellMesh) {
                this.cellarBrainShellMesh.rotation.y = tSec * 0.085;
                this.cellarBrainShellMesh.rotation.x = Math.sin(tSec * 0.32) * 0.06;
            }
            if (this.cellarBrainInnerMesh) {
                this.cellarBrainInnerMesh.rotation.y = -tSec * 0.19;
                this.cellarBrainInnerMesh.rotation.z = tSec * 0.11;
            }
            if (this.cellarBrainOrbitA) {
                this.cellarBrainOrbitA.rotation.z = tSec * 0.18;
            }
            if (this.cellarBrainOrbitB) {
                this.cellarBrainOrbitB.rotation.z = -tSec * 0.23;
            }
            if (this.cellarBrainOrbitC) {
                this.cellarBrainOrbitC.rotation.z = tSec * 0.14;
                this.cellarBrainOrbitC.rotation.y = Math.sin(tSec * 0.45) * 0.18;
            }
            if (this.cellarHealAnimTimer > 0 && this.cellarHealWaveMesh && this.cellarHealWaveMat) {
                this.cellarHealAnimTimer = Math.max(0, this.cellarHealAnimTimer - dt);
                const prog = 1.0 - (this.cellarHealAnimTimer / 2.4);
                this.cellarHealWaveMesh.visible = this.cellarHealAnimTimer > 0.02;
                this.cellarHealWaveMesh.scale.setScalar(0.35 + prog * 2.55);
                this.cellarHealWaveMat.opacity = Math.max(0, (1.0 - prog) * 0.88);
            }
            for (let i = 0; i < this.cellarNodeSlots.length; i++) {
                const sl = this.cellarNodeSlots[i];
                if (!sl.group.visible) continue;
                sl.coreMesh.rotation.y = tSec * 1.25 + i * 0.4;
                if (sl.pulseKind === 'error') {
                    const pulse = sl.baseScale * (1.0 + Math.sin(tSec * 4.8 + i) * 0.095);
                    sl.coreMesh.scale.setScalar(pulse);
                } else {
                    const calmBreath = sl.baseScale * (1.0 + Math.sin(tSec * 1.9 + i * 0.7) * 0.032);
                    sl.coreMesh.scale.setScalar(calmBreath);
                }
                if (sl.ringMesh.visible) {
                    sl.ringMesh.quaternion.copy(this.camera.quaternion);
                }
                sl.labelMesh.quaternion.copy(this.camera.quaternion);
            }
        }

        // Ambient Animations (Vitrine Gem, Window & Outdoor Weather, Watering Stream, Fireworks, Sparks & Petals)
        if (this.rig.vitrineGem && (this.worldState.shopLevel || 1) >= 3) {
            this.rig.vitrineGem.rotation.y = tSec * 0.85;
        }
        for (let i = 0; i < this.weatherParticles.length; i++) {
            const wp = this.weatherParticles[i];
            if (wp.mesh.parent && !wp.mesh.parent.visible) continue;
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

        // Outdoor 3D Garden Weather Particles (Snow / Blossoms / Sun Motes / Autumn Leaves - skipped when Garden is culled!)
        if (showLowerAndGarden) {
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
                (p.mesh.parent || this.scene).remove(p.mesh);
                this.fallingPetals.splice(i, 1);
            }
        }

        this._renderVisorFace(nowMs, dt, false);
        this.renderer.render(this.scene, this.camera);
    }
}

window.Funkira3D = new FunkiraAtelierEngine();
