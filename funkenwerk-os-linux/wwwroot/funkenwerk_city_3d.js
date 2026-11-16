import * as THREE from './vendor/three.module.min.js';
import { RoundedBoxGeometry } from './vendor/RoundedBoxGeometry.js';

// ============================================================================
// FUNKENWERK 3D WATER METROPOLIS ENGINE (60-FPS INTEL UHD 605 EDITION)
// Inspired by Stargate Atlantis x Burj Khalifa x Avengers Stark Tower:
// - Phase 1: Deep Funken-Universum Star Cosmos + Photorealistic Ocean Planet
//   with Fresnel Rim-Glow, Rotating Cloud Deck & Seamless Orbital Dive
// - Phase 2: Floating Water City "Funkenwerk" on Multi-Wave GLSL Ocean,
//   7 Star-Shaped Department Piers + Ring Bridges over Inner Harbor Lagoons,
//   90% 24K Gold (#C5A059 / #D4AF37) & Matte Obsidian + 10% Department Accents,
//   Colossal "MEIN SEELENFUNKE" Supertower + Living Golden Sky-Gliders
// - Phase 3: Penthouse Throne Room at the Supertower Crown (Y = 258m) with
//   360° Panoramic Glass, Royal Crimson Velvet Carpet, Alina's High Throne
//   ("ALINA / Herrscherin über das Seelenfunken-Universum") & Falcon's
//   Right-Hand Throne + Detailed 3D Figure of Falcon (Suit-Armor, Schnauzer,
//   24K-Gold Monocle with Chain)
// - Strict Zero-Load Discipline: 100% paused rAF (0% CPU/GPU) when closed!
// ============================================================================

const TOWER_BASE_Y = 0.0;
const THRONE_FLOOR_Y = 258.0;
const TOWER_APEX_Y = 328.0;
const PLANET_RADIUS = 1800.0;

// Exact 7 Seelenfunke Portal Department Colors (~10% subtle facade/pier accents)
const DEPARTMENTS = [
  { key: 'support',     name: 'Support',      icon: '🎧', hex: '#06B6D4', color: 0x06B6D4 },
  { key: 'products',    name: 'Produkte',     icon: '🎨', hex: '#3B82F6', color: 0x3B82F6 },
  { key: 'marketing',   name: 'Marketing',    icon: '📣', hex: '#A855F7', color: 0xA855F7 },
  { key: 'orders',      name: 'Bestellungen', icon: '📦', hex: '#F59E0B', color: 0xF59E0B },
  { key: 'accounting',  name: 'Buchhaltung',  icon: '💶', hex: '#10B981', color: 0x10B981 },
  { key: 'agents',      name: 'Agenten',      icon: '🤖', hex: '#6366F1', color: 0x6366F1 },
  { key: 'system',      name: 'System',       icon: '⚙️', hex: '#EF4444', color: 0xEF4444 }
];

function clamp(v, lo, hi) {
  return Math.max(lo, Math.min(hi, v));
}

function lerp(a, b, t) {
  return a + (b - a) * t;
}

function easeInOutQuint(t) {
  const x = clamp(t, 0, 1);
  return x < 0.5 ? 16 * x * x * x * x * x : 1 - Math.pow(-2 * x + 2, 5) / 2;
}

function easeInOutCubic(t) {
  const x = clamp(t, 0, 1);
  return x < 0.5 ? 4 * x * x * x : 1 - Math.pow(-2 * x + 2, 3) / 2;
}

function hexToRgb(hex) {
  const n = typeof hex === 'number' ? hex : parseInt(String(hex).replace('#', ''), 16);
  return [(n >> 16) & 255, (n >> 8) & 255, n & 255];
}

// Deterministic pseudo-random generator for reproducible city architecture
function mulberry32(seed) {
  let a = seed >>> 0;
  return function () {
    a |= 0;
    a = (a + 0x6D2B79F5) | 0;
    let t = Math.imul(a ^ (a >>> 15), 1 | a);
    t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
    return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
  };
}

let MAX_ANISO = 4;

function configureHdTex(tex, aniso = 4) {
  tex.generateMipmaps = true;
  tex.minFilter = THREE.LinearMipmapLinearFilter;
  tex.magFilter = THREE.LinearFilter;
  tex.anisotropy = Math.min(MAX_ANISO, aniso);
  tex.colorSpace = THREE.SRGBColorSpace;
  return tex;
}

// Zero-Light-Cost Luxury Studio MatCap Generator (Polished 24K Gold, Matte Obsidian, Skin, Velvet)
function makeLuxuryMatcap(baseHex, specHex, rimHex, specPower = 28, rimStrength = 0.36) {
  const size = 256;
  const c = document.createElement('canvas');
  c.width = size;
  c.height = size;
  const ctx = c.getContext('2d');
  const img = ctx.createImageData(size, size);
  const d = img.data;

  const base = hexToRgb(baseHex);
  const spec = hexToRgb(specHex);
  const rim = hexToRgb(rimHex);

  const lx1 = -0.36, ly1 = 0.64, lz1 = 0.677;
  const hx1 = -0.20, hy1 = 0.39, hz1 = 0.899;
  const lx2 = 0.52,  ly2 = 0.32, lz2 = 0.791;
  const hx2 = 0.32,  hy2 = 0.21, hz2 = 0.924;

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
        const skyBounce = 0.32 + 0.30 * (ny * 0.5 + 0.5);
        const horizonBand = Math.exp(-Math.pow((ny + 0.12) * 5.4, 2)) * (specPower >= 22 ? 0.14 : 0.03);
        const hemi = skyBounce + 0.48 * ndl1 + 0.22 * ndl2 + horizonBand;

        const ndh1 = Math.max(0, nx * hx1 + ny * hy1 + nz * hz1);
        const ndh2 = Math.max(0, nx * hx2 + ny * hy2 + nz * hz2);
        const sp = Math.pow(ndh1, Math.max(5, specPower * 0.42)) * 0.24 +
                   Math.pow(ndh1, specPower * 1.35) * 0.82 +
                   Math.pow(ndh2, specPower * 0.88) * 0.18;
        const fresnel = Math.pow(1.0 - nz, 2.7) * rimStrength;

        r = base[0] * hemi + spec[0] * sp + rim[0] * fresnel;
        g = base[1] * hemi + spec[1] * sp + rim[1] * fresnel;
        b = base[2] * hemi + spec[2] * sp + rim[2] * fresnel;
      } else {
        const len = Math.sqrt(r2);
        const uy = ny / len;
        const hemi = 0.34 + 0.24 * (uy * 0.5 + 0.5);
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
  return configureHdTex(new THREE.CanvasTexture(c), 2);
}

// 1024x1024 Interior-Lit Skyscraper Curtain-Wall Atlas + Emissive Window Map + PBR Roughness Map (4x Anisotropy)
function makeSkyscraperFacadeAtlas() {
  const W = 1024, H = 1024;
  const cCol = document.createElement('canvas');
  cCol.width = W;
  cCol.height = H;
  const ctx = cCol.getContext('2d');

  const cEmis = document.createElement('canvas');
  cEmis.width = W;
  cEmis.height = H;
  const ectx = cEmis.getContext('2d');

  const cRough = document.createElement('canvas');
  cRough.width = W;
  cRough.height = H;
  const rctx = cRough.getContext('2d');

  const rng = mulberry32(20261116);

  // Base obsidian architectural stone & steel cladding
  const bg = ctx.createLinearGradient(0, 0, 0, H);
  bg.addColorStop(0, '#0d131d');
  bg.addColorStop(0.55, '#090d15');
  bg.addColorStop(1, '#06090f');
  ctx.fillStyle = bg;
  ctx.fillRect(0, 0, W, H);

  ectx.fillStyle = '#000000';
  ectx.fillRect(0, 0, W, H);

  // Default matte dark stone spandrel roughness (~0.78 -> rgb 199)
  rctx.fillStyle = 'rgb(199, 199, 199)';
  rctx.fillRect(0, 0, W, H);

  // Subtle dark granite/steel mineral micro-grain on spandrels
  for (let g = 0; g < 4200; g++) {
    const gx = rng() * W;
    const gy = rng() * H;
    const shade = rng() > 0.5 ? 'rgba(255,250,235,0.035)' : 'rgba(0,0,0,0.22)';
    ctx.fillStyle = shade;
    ctx.fillRect(gx, gy, 3, 2);
  }

  const floors = 24;
  const cols = 12;
  const fh = H / floors;
  const cw = W / cols;

  for (let f = 0; f < floors; f++) {
    const y = f * fh;
    const bandHighlight = (f % 8 === 0);
    let suiteLit = rng() > 0.46;
    let suiteWarmth = rng();

    for (let col = 0; col < cols; col++) {
      if (col % 3 === 0 && rng() > 0.35) {
        suiteLit = rng() > 0.44;
        suiteWarmth = rng();
      }
      const x = col * cw;
      const padX = 4.5;
      const padTop = 5.5;
      const padBot = 4.5;
      const wx = x + padX;
      const wy = y + padTop;
      const ww = cw - padX * 2;
      const wh = fh - padTop - padBot;

      // Mirror-smooth architectural glass pane in roughnessMap (~0.14 -> rgb 36)
      rctx.fillStyle = 'rgb(36, 36, 36)';
      rctx.fillRect(wx, wy, ww, wh);

      if (bandHighlight) {
        const lg = ctx.createLinearGradient(0, wy, 0, wy + wh);
        lg.addColorStop(0, '#FFEAB3');
        lg.addColorStop(0.5, '#C5A059');
        lg.addColorStop(1, '#5E4720');
        ctx.fillStyle = lg;
        ctx.fillRect(wx, wy, ww, wh);

        ectx.fillStyle = 'rgba(225, 182, 98, 0.72)';
        ectx.fillRect(wx, wy + 2, ww, wh - 4);
      } else if (suiteLit && rng() > 0.14) {
        // Warm champagne-gold interior atelier/office lighting with ceiling downlight gradient
        const topCol = suiteWarmth > 0.62 ? '#FFF6D6' : '#FDE68A';
        const midCol = suiteWarmth > 0.62 ? '#F3C878' : '#D4AF37';
        const botCol = '#58411E';
        const roomGrad = ctx.createLinearGradient(0, wy, 0, wy + wh);
        roomGrad.addColorStop(0.0, topCol);
        roomGrad.addColorStop(0.48, midCol);
        roomGrad.addColorStop(1.0, botCol);
        ctx.fillStyle = roomGrad;
        ctx.fillRect(wx, wy, ww, wh);

        // Interior architectural silhouette (mezzanine desk bar & subtle vertical partition/blinds)
        ctx.fillStyle = 'rgba(14, 18, 26, 0.42)';
        ctx.fillRect(wx, wy + wh * 0.66, ww, 2.2);
        if (rng() > 0.45) {
          const partX = wx + ww * (0.35 + rng() * 0.35);
          ctx.fillRect(partX, wy, 2.0, wh);
        }
        if (rng() > 0.68) {
          ctx.fillStyle = 'rgba(18, 22, 30, 0.28)';
          ctx.fillRect(wx, wy, ww, wh * 0.32);
        }

        const emisGrad = ectx.createLinearGradient(0, wy, 0, wy + wh);
        emisGrad.addColorStop(0.0, suiteWarmth > 0.62 ? 'rgba(255, 243, 201, 0.94)' : 'rgba(245, 208, 125, 0.78)');
        emisGrad.addColorStop(0.75, 'rgba(197, 160, 89, 0.52)');
        emisGrad.addColorStop(1.0, 'rgba(92, 68, 30, 0.25)');
        ectx.fillStyle = emisGrad;
        ectx.fillRect(wx + 1, wy + 1, ww - 2, wh - 2);
      } else {
        // Unlit deep sapphire-obsidian curtain glass (reflects 2K sky via PMREM envMap!)
        const dg = ctx.createLinearGradient(0, wy, 0, wy + wh);
        dg.addColorStop(0, '#131e30');
        dg.addColorStop(0.6, '#0c1422');
        dg.addColorStop(1, '#080d17');
        ctx.fillStyle = dg;
        ctx.fillRect(wx, wy, ww, wh);
      }
    }

    // Horizontal brushed stone & 24K-gold spandrel cornice line
    ctx.fillStyle = '#141923';
    ctx.fillRect(0, y, W, 4.5);
    ctx.fillStyle = bandHighlight ? '#E5C158' : 'rgba(197, 160, 89, 0.52)';
    ctx.fillRect(0, y + (bandHighlight ? 0.5 : 1.5), W, bandHighlight ? 3.4 : 1.8);

    rctx.fillStyle = bandHighlight ? 'rgb(76, 76, 76)' : 'rgb(186, 186, 186)';
    rctx.fillRect(0, y, W, 5.0);
  }

  // Crisp vertical 24K-gold structural mullions with satin metallic roughness (~0.32 -> rgb 82)
  for (let col = 0; col <= cols; col++) {
    const x = col * cw;
    const major = (col % 4 === 0);
    const mw = major ? 4.4 : 2.0;
    ctx.fillStyle = major ? '#D4AF37' : 'rgba(197, 160, 89, 0.44)';
    ctx.fillRect(x - mw * 0.5, 0, mw, H);
    if (major) {
      ctx.fillStyle = 'rgba(255, 248, 214, 0.45)';
      ctx.fillRect(x - 0.8, 0, 1.2, H);
    }
    rctx.fillStyle = 'rgb(82, 82, 82)';
    rctx.fillRect(x - mw * 0.5, 0, mw, H);
  }

  const colorTex = configureHdTex(new THREE.CanvasTexture(cCol), 4);
  colorTex.wrapS = THREE.RepeatWrapping;
  colorTex.wrapT = THREE.RepeatWrapping;

  const emissiveTex = configureHdTex(new THREE.CanvasTexture(cEmis), 4);
  emissiveTex.wrapS = THREE.RepeatWrapping;
  emissiveTex.wrapT = THREE.RepeatWrapping;

  const roughnessTex = new THREE.CanvasTexture(cRough);
  roughnessTex.generateMipmaps = true;
  roughnessTex.minFilter = THREE.LinearMipmapLinearFilter;
  roughnessTex.magFilter = THREE.LinearFilter;
  roughnessTex.anisotropy = Math.min(MAX_ANISO, 4);
  if (typeof THREE.NoColorSpace !== 'undefined') {
    roughnessTex.colorSpace = THREE.NoColorSpace;
  }
  roughnessTex.wrapS = THREE.RepeatWrapping;
  roughnessTex.wrapT = THREE.RepeatWrapping;

  return { colorTex, emissiveTex, roughnessTex };
}

function makeSkyscraperFacadeTexture() {
  return makeSkyscraperFacadeAtlas().colorTex;
}

// Seamless 2K (2048x1024) Equirectangular Golden-Hour Sky & Volumetric Cloud Panorama for SkyDome & PMREM IBL
function makePhotorealSkyPanoramaTexture(sunDirVec) {
  const W = 2048, H = 1024;
  const c = document.createElement('canvas');
  c.width = W;
  c.height = H;
  const ctx = c.getContext('2d');

  const sx = sunDirVec ? sunDirVec.x : -0.38;
  const sy = sunDirVec ? sunDirVec.y : 0.24;
  const sz = sunDirVec ? sunDirVec.z : -0.89;
  const sLen = Math.hypot(sx, sy, sz) || 1.0;
  const ux = sx / sLen, uy = sy / sLen, uz = sz / sLen;

  // 1. Seamless 3D-Spherical Volumetric Cloud & Atmospheric Scattering Pass (512x256 -> upscaled smoothly to 2048x1024)
  const cw = 512, ch = 256;
  const cloudCanvas = document.createElement('canvas');
  cloudCanvas.width = cw;
  cloudCanvas.height = ch;
  const cctx = cloudCanvas.getContext('2d');
  const img = cctx.createImageData(cw, ch);
  const data = img.data;

  const hash3 = (ix, iy, iz) => {
    let n = (Math.imul(ix, 127) + Math.imul(iy, 311) + Math.imul(iz, 74)) | 0;
    n = (n << 13) ^ n;
    return ((Math.imul(n, (Math.imul(n, Math.imul(n, 15731)) + 789221) | 0) + 1376312589) >>> 0) / 4294967296;
  };

  const noise3 = (px, py, pz) => {
    const ix = Math.floor(px), iy = Math.floor(py), iz = Math.floor(pz);
    let fx = px - ix, fy = py - iy, fz = pz - iz;
    fx = fx * fx * (3 - 2 * fx);
    fy = fy * fy * (3 - 2 * fy);
    fz = fz * fz * (3 - 2 * fz);
    const n000 = hash3(ix, iy, iz),       n100 = hash3(ix + 1, iy, iz);
    const n010 = hash3(ix, iy + 1, iz),   n110 = hash3(ix + 1, iy + 1, iz);
    const n001 = hash3(ix, iy, iz + 1),   n101 = hash3(ix + 1, iy, iz + 1);
    const n011 = hash3(ix, iy + 1, iz + 1), n111 = hash3(ix + 1, iy + 1, iz + 1);
    const nx00 = n000 + (n100 - n000) * fx;
    const nx10 = n010 + (n110 - n010) * fx;
    const nx01 = n001 + (n101 - n001) * fx;
    const nx11 = n011 + (n111 - n011) * fx;
    const nxy0 = nx00 + (nx10 - nx00) * fy;
    const nxy1 = nx01 + (nx11 - nx01) * fy;
    return nxy0 + (nxy1 - nxy0) * fz;
  };

  const cloudFbm = (px, py, pz) => {
    let v = 0.0;
    v += 0.52 * noise3(px, py, pz);
    v += 0.27 * noise3(px * 2.07 + 3.1, py * 2.07 + 1.7, pz * 2.07 + 4.3);
    v += 0.14 * noise3(px * 4.23 + 7.4, py * 4.23 + 5.2, pz * 4.23 + 2.9);
    v += 0.07 * noise3(px * 8.51 + 1.9, py * 8.51 + 8.6, pz * 8.51 + 6.1);
    return v;
  };

  for (let y = 0; y < ch; y++) {
    const lat = (0.5 - (y + 0.5) / ch) * Math.PI;
    const sinLat = Math.sin(lat);
    const cosLat = Math.cos(lat);

    for (let x = 0; x < cw; x++) {
      const u = (x + 0.5) / cw;
      const lon = (u - 0.5) * Math.PI * 2.0;
      const dx = cosLat * Math.cos(lon);
      const dy = sinLat;
      const dz = cosLat * Math.sin(lon);

      const sunDot = Math.max(0, dx * ux + dy * uy + dz * uz);
      let r = 0, g = 0, b = 0;

      if (dy >= -0.02) {
        const h = clamp(dy, 0.0, 1.0);
        // Atmospheric Rayleigh zenith -> golden-sapphire mid-sky -> warm champagne horizon
        const tMid = Math.pow(1.0 - h, 1.85);
        const tHor = Math.pow(1.0 - h, 5.2);
        const azGlow = Math.pow(sunDot, 2.4);

        // Zenith (#081B3A) -> Mid (#1C4776)
        r = lerp(8, 34, tMid);
        g = lerp(27, 78, tMid);
        b = lerp(58, 126, tMid);

        // Horizon atmospheric scattering (warm gold near sun, twilight rose-bronze opposite)
        const horR = lerp(86, 238, azGlow);
        const horG = lerp(96, 186, azGlow);
        const horB = lerp(124, 112, azGlow);
        r = lerp(r, horR, tHor * 0.92);
        g = lerp(g, horG, tHor * 0.92);
        b = lerp(b, horB, tHor * 0.92);

        // Volumetric cloud deck projected onto elevated atmospheric shell
        const cloudBand = clamp((h - 0.015) / 0.14, 0.0, 1.0) * clamp((0.78 - h) / 0.52, 0.0, 1.0);
        if (cloudBand > 0.004) {
          const projScale = 3.8 / (h + 0.24);
          const cxp = dx * projScale;
          const CYP = dy * 2.4;
          const czp = dz * projScale;

          const dens = cloudFbm(cxp, CYP, czp);
          const cov = clamp((dens - 0.47) / 0.30, 0.0, 1.0);
          const cloudAlpha = cov * cov * (3 - 2 * cov) * cloudBand * 0.88;

          if (cloudAlpha > 0.005) {
            // Directional sun self-shadowing sample toward sunDirVec
            const litSample = cloudFbm(cxp + ux * 0.42, CYP + uy * 0.35, czp + uz * 0.42);
            const shadow = clamp((litSample - dens) * 3.8, -0.45, 0.65);
            const sunLit = clamp(0.56 - shadow + Math.pow(sunDot, 3.0) * 0.42, 0.08, 1.0);
            const silverLining = Math.pow(sunDot, 6.0) * clamp(1.0 - cov * 0.75, 0.15, 1.0);

            // Dark indigo-slate cloud base vs sunlit 24K champagne-gold cloud ridges
            let cr = lerp(32, 246, sunLit) + silverLining * 55;
            let cg = lerp(44, 208, sunLit) + silverLining * 38;
            let cb = lerp(68, 154, sunLit) + silverLining * 14;

            // Aerial perspective horizon mist blending
            const mist = Math.pow(1.0 - h, 4.2) * 0.62;
            cr = lerp(cr, horR, mist);
            cg = lerp(cg, horG, mist);
            cb = lerp(cb, horB, mist);

            r = lerp(r, cr, cloudAlpha);
            g = lerp(g, cg, cloudAlpha);
            b = lerp(b, cb, cloudAlpha);
          }
        }

        // Solar Mie aureole & crisp sun disc
        const mie = Math.pow(sunDot, 9.0) * 0.46 + Math.pow(sunDot, 64.0) * 0.85 + Math.pow(sunDot, 380.0) * 1.45;
        r += 255 * mie * 0.92;
        g += 224 * mie * 0.82;
        b += 156 * mie * 0.58;
      } else {
        // Lower hemisphere (-Y ocean bounce for physically grounded PMREM metallic undersides!)
        const depth = clamp(-dy, 0.0, 1.0);
        const reflSun = Math.max(0, dx * ux + (-dy) * uy + dz * uz);
        const waterGlint = Math.pow(reflSun, 14.0) * 0.55;
        r = lerp(48, 8, Math.pow(depth, 0.55)) + 212 * waterGlint;
        g = lerp(68, 26, Math.pow(depth, 0.55)) + 175 * waterGlint;
        b = lerp(94, 52, Math.pow(depth, 0.55)) + 92 * waterGlint;
      }

      const p = (y * cw + x) * 4;
      data[p]     = clamp(Math.round(r), 0, 255);
      data[p + 1] = clamp(Math.round(g), 0, 255);
      data[p + 2] = clamp(Math.round(b), 0, 255);
      data[p + 3] = 255;
    }
  }
  cctx.putImageData(img, 0, 0);

  ctx.imageSmoothingEnabled = true;
  if (typeof ctx.imageSmoothingQuality !== 'undefined') {
    ctx.imageSmoothingQuality = 'high';
  }
  ctx.drawImage(cloudCanvas, 0, 0, W, H);

  // 2. High-Frequency 2K Horizon Haze Veil & Fine Golden Cirrus Filaments directly at 2048x1024
  const hzGrad = ctx.createLinearGradient(0, H * 0.43, 0, H * 0.54);
  hzGrad.addColorStop(0.0, 'rgba(245, 206, 134, 0.0)');
  hzGrad.addColorStop(0.58, 'rgba(236, 192, 118, 0.14)');
  hzGrad.addColorStop(0.75, 'rgba(176, 152, 128, 0.18)');
  hzGrad.addColorStop(1.0, 'rgba(18, 38, 66, 0.0)');
  ctx.fillStyle = hzGrad;
  ctx.fillRect(0, H * 0.43, W, H * 0.11);

  const rng = mulberry32(20261118);
  for (let i = 0; i < 64; i++) {
    const cx = rng() * W;
    const cy = H * (0.20 + rng() * 0.27);
    const rx = 70 + rng() * 190;
    const ry = 2.2 + rng() * 6.5;
    const wg = ctx.createRadialGradient(cx, cy, 2, cx, cy, rx);
    wg.addColorStop(0.0, 'rgba(255, 238, 196, 0.09)');
    wg.addColorStop(0.55, 'rgba(212, 175, 55, 0.04)');
    wg.addColorStop(1.0, 'rgba(212, 175, 55, 0.0)');
    ctx.save();
    ctx.translate(cx, cy);
    ctx.scale(1.0, ry / rx);
    ctx.fillStyle = wg;
    ctx.beginPath();
    ctx.arc(0, 0, rx, 0, Math.PI * 2);
    ctx.fill();
    ctx.restore();
  }

  const tex = configureHdTex(new THREE.CanvasTexture(c), 4);
  tex.mapping = THREE.EquirectangularReflectionMapping;
  tex.wrapS = THREE.RepeatWrapping;
  tex.wrapT = THREE.ClampToEdgeWrapping;
  return tex;
}

// Procedural Soft Squircle Contact-Shadow Map for Baked Ambient Occlusion (0% Real-Time Shadow Cost!)
function makeBakedAoShadowTexture() {
  const S = 256;
  const c = document.createElement('canvas');
  c.width = S;
  c.height = S;
  const ctx = c.getContext('2d');
  const img = ctx.createImageData(S, S);
  const d = img.data;
  const half = S * 0.5;

  for (let y = 0; y < S; y++) {
    const ny = Math.abs((y + 0.5 - half) / half);
    for (let x = 0; x < S; x++) {
      const nx = Math.abs((x + 0.5 - half) / half);
      // Rounded-box squircle norm fits both rectangular skyscrapers and octagonal/circular towers naturally
      const dist = Math.pow(Math.pow(nx, 2.8) + Math.pow(ny, 2.8), 1.0 / 2.8);
      const t = clamp(1.0 - dist / 0.95, 0.0, 1.0);
      const alpha = Math.pow(t, 1.85) * 0.72;
      const p = (y * S + x) * 4;
      d[p]     = 4;
      d[p + 1] = 6;
      d[p + 2] = 11;
      d[p + 3] = clamp(Math.round(alpha * 255), 0, 255);
    }
  }
  ctx.putImageData(img, 0, 0);
  const tex = new THREE.CanvasTexture(c);
  tex.generateMipmaps = true;
  tex.minFilter = THREE.LinearMipmapLinearFilter;
  tex.magFilter = THREE.LinearFilter;
  tex.needsUpdate = true;
  return tex;
}

// Monumental Stark-Tower Style "MEIN SEELENFUNKE" 24K-Gold Facade Plaque Texture
function makeMeinSeelenfunkePlaqueTexture() {
  const W = 2048, H = 384;
  const c = document.createElement('canvas');
  c.width = W;
  c.height = H;
  const ctx = c.getContext('2d');

  // Matte obsidian architectural backing plate
  const bg = ctx.createLinearGradient(0, 0, 0, H);
  bg.addColorStop(0, '#0c0e14');
  bg.addColorStop(0.5, '#07080c');
  bg.addColorStop(1, '#0c0e14');
  ctx.fillStyle = bg;
  ctx.fillRect(0, 0, W, H);

  // Double 24K Gold Architectural Bezel Frame
  ctx.strokeStyle = '#C5A059';
  ctx.lineWidth = 10;
  ctx.strokeRect(14, 14, W - 28, H - 28);
  ctx.strokeStyle = 'rgba(253, 230, 138, 0.55)';
  ctx.lineWidth = 3;
  ctx.strokeRect(30, 30, W - 60, H - 60);

  // Subtle vertical brushed-obsidian ribs behind lettering
  ctx.fillStyle = 'rgba(197, 160, 89, 0.06)';
  for (let x = 48; x < W - 48; x += 32) {
    ctx.fillRect(x, 36, 2, H - 72);
  }

  // Sculpted 3D-extruded shadow under letters
  ctx.textAlign = 'center';
  ctx.textBaseline = 'middle';
  ctx.font = '900 132px "Liberation Sans", "Segoe UI", system-ui, sans-serif';
  if (typeof ctx.letterSpacing !== 'undefined') {
    ctx.letterSpacing = '14px';
  }

  ctx.fillStyle = 'rgba(0, 0, 0, 0.92)';
  ctx.fillText('MEIN SEELENFUNKE', W * 0.5 + 5, H * 0.52 + 6);

  // Bright 24K Gold metallic gradient lettering
  const tg = ctx.createLinearGradient(0, H * 0.22, 0, H * 0.80);
  tg.addColorStop(0.00, '#FFFDF2');
  tg.addColorStop(0.28, '#FDE68A');
  tg.addColorStop(0.55, '#D4AF37');
  tg.addColorStop(0.82, '#FFF3C4');
  tg.addColorStop(1.00, '#B78E3E');
  ctx.fillStyle = tg;
  ctx.fillText('MEIN SEELENFUNKE', W * 0.5, H * 0.52);

  ctx.lineWidth = 2.2;
  ctx.strokeStyle = '#FFFBEB';
  ctx.strokeText('MEIN SEELENFUNKE', W * 0.5, H * 0.52);

  return configureHdTex(new THREE.CanvasTexture(c), 4);
}


// High-Resolution Engraved 24K-Gold Throne Backrest Plaque Generator
function makeThroneEngravingTexture(mainTitle, subTitle, isPrimary = true) {
  const W = 1536, H = 768;
  const c = document.createElement('canvas');
  c.width = W;
  c.height = H;
  const ctx = c.getContext('2d');

  // Deep brushed obsidian-onyx inlay tablet
  const bg = ctx.createRadialGradient(W * 0.5, H * 0.45, 40, W * 0.5, H * 0.5, W * 0.62);
  bg.addColorStop(0, '#14161e');
  bg.addColorStop(0.65, '#0b0c11');
  bg.addColorStop(1, '#06070a');
  ctx.fillStyle = bg;
  ctx.fillRect(0, 0, W, H);

  // Ornate 24K Gold Royal Border
  ctx.strokeStyle = '#C5A059';
  ctx.lineWidth = 14;
  ctx.strokeRect(24, 24, W - 48, H - 48);
  ctx.strokeStyle = '#FDE68A';
  ctx.lineWidth = 3.5;
  ctx.strokeRect(46, 46, W - 92, H - 92);

  // Corner diamonds
  const drawStar = (cx, cy, r) => {
    ctx.fillStyle = '#FDE68A';
    ctx.beginPath();
    ctx.moveTo(cx, cy - r);
    ctx.quadraticCurveTo(cx, cy, cx + r, cy);
    ctx.quadraticCurveTo(cx, cy, cx, cy + r);
    ctx.quadraticCurveTo(cx, cy, cx - r, cy);
    ctx.quadraticCurveTo(cx, cy, cx, cy - r);
    ctx.fill();
  };
  drawStar(84, 84, 20);
  drawStar(W - 84, 84, 20);
  drawStar(84, H - 84, 20);
  drawStar(W - 84, H - 84, 20);
  drawStar(W * 0.5, 132, isPrimary ? 34 : 24);

  const goldGrad = ctx.createLinearGradient(0, H * 0.22, 0, H * 0.82);
  goldGrad.addColorStop(0.0, '#FFFDF5');
  goldGrad.addColorStop(0.3, '#FDE68A');
  goldGrad.addColorStop(0.58, '#C5A059');
  goldGrad.addColorStop(0.84, '#FFF2C2');
  goldGrad.addColorStop(1.0, '#A88034');

  ctx.textAlign = 'center';
  ctx.textBaseline = 'middle';

  // Primary Sovereign Name ("ALINA" or "FALCON")
  ctx.font = isPrimary
    ? '900 168px "Liberation Serif", "Georgia", "Liberation Sans", serif'
    : '900 124px "Liberation Sans", "Segoe UI", sans-serif';
  ctx.fillStyle = 'rgba(0,0,0,0.9)';
  ctx.fillText(mainTitle, W * 0.5 + 4, H * 0.43 + 5);
  ctx.fillStyle = goldGrad;
  ctx.fillText(mainTitle, W * 0.5, H * 0.43);

  // Fine gold divider rule
  ctx.fillStyle = '#C5A059';
  ctx.fillRect(W * 0.20, H * 0.60, W * 0.60, 3.5);

  // Royal Title ("Herrscherin über das Seelenfunken-Universum" / "FALCON · Rechte Hand & Mitgründer")
  ctx.font = isPrimary
    ? '700 54px "Liberation Sans", "Segoe UI", sans-serif'
    : '700 48px "Liberation Sans", "Segoe UI", sans-serif';
  ctx.fillStyle = 'rgba(0,0,0,0.9)';
  ctx.fillText(subTitle, W * 0.5 + 3, H * 0.74 + 4);
  ctx.fillStyle = '#FDE68A';
  ctx.fillText(subTitle, W * 0.5, H * 0.74);

  return configureHdTex(new THREE.CanvasTexture(c), 4);
}


// Falcone's Armored Suit Chest Emblem ("FALCONE · Mitgründer")
function makeFalconArmorChestTexture() {
  const W = 512, H = 256;
  const c = document.createElement('canvas');
  c.width = W;
  c.height = H;
  const ctx = c.getContext('2d');

  ctx.fillStyle = '#0c0d12';
  ctx.fillRect(0, 0, W, H);

  ctx.strokeStyle = '#D4AF37';
  ctx.lineWidth = 8;
  ctx.strokeRect(10, 10, W - 20, H - 20);

  ctx.textAlign = 'center';
  ctx.textBaseline = 'middle';
  ctx.fillStyle = '#FDE68A';
  ctx.font = '900 64px "Liberation Sans", "Segoe UI", sans-serif';
  ctx.fillText('FALCONE', W * 0.5, H * 0.40, W - 44);

  ctx.fillStyle = '#C5A059';
  ctx.fillRect(W * 0.20, H * 0.60, W * 0.60, 3);

  ctx.fillStyle = '#FFF6D6';
  ctx.font = '700 35px "Liberation Sans", "Segoe UI", sans-serif';
  ctx.fillText('FALCONE · Mitgründer', W * 0.5, H * 0.77, W - 44);

  return configureHdTex(new THREE.CanvasTexture(c), 4);
}


// Penthouse Nero-Marquina Black Marble & 24K-Gold Compass Floor Texture
function makePenthouseMarbleTexture() {
  const S = 1024;
  const c = document.createElement('canvas');
  c.width = S;
  c.height = S;
  const ctx = c.getContext('2d');
  const cx = S * 0.5, cy = S * 0.5;

  const rg = ctx.createRadialGradient(cx, cy, 30, cx, cy, S * 0.5);
  rg.addColorStop(0, '#161922');
  rg.addColorStop(0.65, '#0c0e14');
  rg.addColorStop(1, '#07080c');
  ctx.fillStyle = rg;
  ctx.fillRect(0, 0, S, S);

  // Subtle organic marble veins
  const rng = mulberry32(7711);
  ctx.strokeStyle = 'rgba(255, 248, 225, 0.055)';
  ctx.lineWidth = 1.6;
  for (let i = 0; i < 38; i++) {
    ctx.beginPath();
    let x = rng() * S, y = rng() * S;
    ctx.moveTo(x, y);
    for (let s = 0; s < 5; s++) {
      x += (rng() - 0.5) * 220;
      y += (rng() - 0.5) * 220;
      ctx.lineTo(x, y);
    }
    ctx.stroke();
  }

  // Concentric 24K Gold Atlantis / Seelenfunke floor inlay rings
  const radii = [110, 220, 345, 465, 492];
  radii.forEach((r, idx) => {
    ctx.strokeStyle = idx % 2 === 0 ? '#C5A059' : 'rgba(253, 230, 138, 0.45)';
    ctx.lineWidth = idx === 4 ? 7 : 3.2;
    ctx.beginPath();
    ctx.arc(cx, cy, r, 0, Math.PI * 2);
    ctx.stroke();
  });

  // 7-fold star rays matching the 7 piers below!
  for (let k = 0; k < 14; k++) {
    const ang = (k / 14) * Math.PI * 2;
    const r0 = k % 2 === 0 ? 42 : 110;
    const r1 = k % 2 === 0 ? 465 : 345;
    ctx.strokeStyle = k % 2 === 0 ? 'rgba(212, 175, 55, 0.65)' : 'rgba(197, 160, 89, 0.28)';
    ctx.lineWidth = k % 2 === 0 ? 3.0 : 1.6;
    ctx.beginPath();
    ctx.moveTo(cx + Math.cos(ang) * r0, cy + Math.sin(ang) * r0);
    ctx.lineTo(cx + Math.cos(ang) * r1, cy + Math.sin(ang) * r1);
    ctx.stroke();
  }

  return configureHdTex(new THREE.CanvasTexture(c), 4);
}


// Royal Crimson Velvet Carpet Runner Texture ("Samt-Karmesinrot mit feiner Goldbordüre")
function makeRoyalCarpetTexture() {
  const W = 512, H = 1024;
  const c = document.createElement('canvas');
  c.width = W;
  c.height = H;
  const ctx = c.getContext('2d');

  // Rich brushed crimson velvet base (#6E0B18 -> #8B1122 -> #6E0B18)
  const cg = ctx.createLinearGradient(0, 0, W, 0);
  cg.addColorStop(0.00, '#540812');
  cg.addColorStop(0.14, '#7A0E1D');
  cg.addColorStop(0.50, '#8E1324');
  cg.addColorStop(0.86, '#7A0E1D');
  cg.addColorStop(1.00, '#540812');
  ctx.fillStyle = cg;
  ctx.fillRect(0, 0, W, H);

  // Subtle woven velvet pile micro-grain
  const rng = mulberry32(9901);
  ctx.fillStyle = 'rgba(255, 215, 160, 0.025)';
  for (let i = 0; i < 1800; i++) {
    ctx.fillRect(rng() * W, rng() * H, 3, 2);
  }

  // Fine double 24K-Gold brocade borders on left & right edges
  ctx.fillStyle = '#D4AF37';
  ctx.fillRect(14, 0, 14, H);
  ctx.fillRect(W - 28, 0, 14, H);

  ctx.fillStyle = '#FDE68A';
  ctx.fillRect(36, 0, 4, H);
  ctx.fillRect(W - 40, 0, 4, H);

  // Subtle repeating gold diamond filigree inside border
  ctx.strokeStyle = 'rgba(212, 175, 55, 0.52)';
  ctx.lineWidth = 2;
  for (let y = 24; y < H; y += 48) {
    ctx.strokeRect(18, y, 6, 16);
    ctx.strokeRect(W - 24, y, 6, 16);
  }

  const tex = configureHdTex(new THREE.CanvasTexture(c), 4);
  tex.wrapS = THREE.ClampToEdgeWrapping;
  tex.wrapT = THREE.RepeatWrapping;
  tex.repeat.set(1, 3);
  return tex;
}

class FunkenwerkCityEngine {
  constructor() {
    this.initialized = false;
    this.running = false;
    this.rafId = 0;
    this.canvas = null;
    this.renderer = null;
    this.scene = null;
    this.camera = null;

    this.clockTime = 0;
    this.lastFrameMs = 0;
    this.renderScale = 1.0;
    this.smoothDt = 0.0166;

    // Groups & Shader Uniforms
    this.spaceGroup = null;
    this.planetGroup = null;
    this.cityGroup = null;
    this.supertowerGroup = null;
    this.throneRoomGroup = null;
    this.falconGroup = null;
    this.falconChestGroup = null;
    this.falconHeadGroup = null;
    this.throneClickTargets = [];
    this.departmentClickTargets = [];
    this.departmentBeacons = {};
    this.departmentLandmarks = [];
    this.hoveredDeptKey = null;
    this.activeDepartment = null;
    this.departments = DEPARTMENTS;
    this.hoverChipEl = null;
    this.hoverDotEl = null;
    this.hoverLabelEl = null;

    this.skyPanoramaTex = null;
    this.pmremTarget = null;
    this._throneRefVec = new THREE.Vector3(0, THRONE_FLOOR_Y + 2, 0);

    this.skyUniforms = null;
    this.planetSurfaceUniforms = null;
    this.planetCloudUniforms = null;
    this.planetAtmoUniforms = null;
    this.oceanUniforms = null;
    this.cloudVeilEl = null;

    // Sky-Gliders Instanced Traffic
    this.gliderInst = null;
    this.gliderData = [];
    this.dummyObj = new THREE.Object3D();

    // Camera Flight & Damped Spherical OrbitControls
    this.viewMode = 'orbit'; // 'orbit' | 'city' | 'throne' | 'department'
    this.flight = {
      active: false,
      type: 'none',
      elapsed: 0,
      duration: 6.0,
      fromPos: new THREE.Vector3(),
      fromTarget: new THREE.Vector3(),
      midPos: new THREE.Vector3(),
      toPos: new THREE.Vector3(),
      toTarget: new THREE.Vector3()
    };

    this.orbit = {
      target: new THREE.Vector3(0, 96, 0),
      desiredTarget: new THREE.Vector3(0, 96, 0),
      radius: 560,
      desiredRadius: 560,
      theta: 0.34,
      desiredTheta: 0.34,
      phi: 1.24,
      desiredPhi: 1.24,
      minRadius: 1.6,
      maxRadius: 2400,
      minY: 3.0,
      isDragging: false,
      dragButton: 0,
      downX: 0,
      downY: 0,
      lastX: 0,
      lastY: 0,
      movedDist: 0,
      pinchDist: 0
    };

    this.raycaster = new THREE.Raycaster();
    this.pointerNdc = new THREE.Vector2();
    this._boundLoop = (ts) => this._tick(ts);
    this._boundResize = () => this.resize();
  }

  init() {
    if (this.initialized) return;
    this.canvas = document.getElementById('funkenwerk-city-canvas');
    if (!this.canvas) return;

    this.renderer = new THREE.WebGLRenderer({
      canvas: this.canvas,
      antialias: true,
      alpha: false,
      powerPreference: 'high-performance',
      stencil: false
    });
    this.renderer.outputColorSpace = THREE.SRGBColorSpace;
    this.renderer.toneMapping = THREE.ACESFilmicToneMapping;
    this.renderer.toneMappingExposure = 1.10;
    this.renderer.setClearColor(0x02040a, 1);

    MAX_ANISO = Math.min(8, this.renderer.capabilities.getMaxAnisotropy() || 4);

    this.scene = new THREE.Scene();
    this.camera = new THREE.PerspectiveCamera(46, window.innerWidth / Math.max(1, window.innerHeight), 0.25, 26000);

    // Build Luxury Material Palette (Zero expensive dynamic lights!)
    this._buildMaterials();

    // Build All 3 Seamless World Layers
    this._buildCosmosAndSky();
    this._buildOrbitalPlanet();
    this._buildOcean();
    this._buildAtlantisMetropolis();
    this._buildSupertower();
    this._buildThroneRoomAndFalcon();

    // Bind Smooth Mouse & Touch OrbitControls + Spire Raycast Click
    this._bindControls();
    window.addEventListener('resize', this._boundResize);
    this.resize();

    this.initialized = true;
  }

  _buildMaterials() {
    const sunDir = new THREE.Vector3(-0.38, 0.24, -0.89).normalize();
    this.skyPanoramaTex = makePhotorealSkyPanoramaTexture(sunDir);

    // Precompute PMREM IBL Reflection Environment once at startup (0% per-frame shadow/probe cost!)
    if (this.renderer && typeof THREE.PMREMGenerator === 'function') {
      const pmremGen = new THREE.PMREMGenerator(this.renderer);
      if (typeof pmremGen.compileEquirectangularShader === 'function') {
        pmremGen.compileEquirectangularShader();
      }
      this.pmremTarget = pmremGen.fromEquirectangular(this.skyPanoramaTex);
      this.scene.environment = this.pmremTarget.texture;
      pmremGen.dispose();
    }

    const mcGoldBright = makeLuxuryMatcap('#D4AF37', '#FFFBEB', '#FDE68A', 38, 0.46);
    const mcGoldBrushed = makeLuxuryMatcap('#B89146', '#FFF3C4', '#D4AF37', 22, 0.32);
    const mcObsidian = makeLuxuryMatcap('#11141C', '#687387', '#C5A059', 30, 0.22);
    const mcAnthracite = makeLuxuryMatcap('#1B202B', '#7C879B', '#9CA3AF', 18, 0.18);
    const mcCrimsonVelvet = makeLuxuryMatcap('#7A0D1B', '#C94A5A', '#D4AF37', 10, 0.24);
    const mcSkin = makeLuxuryMatcap('#DDA988', '#FFF2E6', '#F4C6A8', 14, 0.20);
    const mcHair = makeLuxuryMatcap('#2A1E17', '#7A6252', '#C5A059', 20, 0.18);
    const mcWhiteShirt = makeLuxuryMatcap('#ECEFF4', '#FFFFFF', '#D4AF37', 16, 0.15);

    const facadeAtlas = makeSkyscraperFacadeAtlas();
    const stColorTex = facadeAtlas.colorTex.clone();
    stColorTex.repeat.set(2, 3);
    stColorTex.needsUpdate = true;
    const stEmisTex = facadeAtlas.emissiveTex.clone();
    stEmisTex.repeat.set(2, 3);
    stEmisTex.needsUpdate = true;
    const stRoughTex = facadeAtlas.roughnessTex.clone();
    stRoughTex.repeat.set(2, 3);
    stRoughTex.needsUpdate = true;

    const aoTex = makeBakedAoShadowTexture();

    this.mats = {
      goldBright: new THREE.MeshMatcapMaterial({
        matcap: mcGoldBright,
        polygonOffset: true,
        polygonOffsetFactor: -1,
        polygonOffsetUnits: -2
      }),
      goldBrushed: new THREE.MeshMatcapMaterial({
        matcap: mcGoldBrushed,
        polygonOffset: true,
        polygonOffsetFactor: -1,
        polygonOffsetUnits: -2
      }),
      goldPbrBright: new THREE.MeshStandardMaterial({
        color: 0xE2BA4A,
        metalness: 0.88,
        roughness: 0.20,
        envMapIntensity: 1.35,
        polygonOffset: true,
        polygonOffsetFactor: -1,
        polygonOffsetUnits: -2
      }),
      goldPbrBrushed: new THREE.MeshStandardMaterial({
        color: 0xC69C42,
        metalness: 0.80,
        roughness: 0.34,
        envMapIntensity: 1.15,
        polygonOffset: true,
        polygonOffsetFactor: -1,
        polygonOffsetUnits: -2
      }),
      obsidianPbr: new THREE.MeshStandardMaterial({
        color: 0x101520,
        metalness: 0.42,
        roughness: 0.24,
        envMapIntensity: 1.18
      }),
      obsidian: new THREE.MeshMatcapMaterial({ matcap: mcObsidian }),
      anthracite: new THREE.MeshMatcapMaterial({ matcap: mcAnthracite }),
      velvet: new THREE.MeshMatcapMaterial({ matcap: mcCrimsonVelvet }),
      skin: new THREE.MeshMatcapMaterial({ matcap: mcSkin }),
      hair: new THREE.MeshMatcapMaterial({ matcap: mcHair }),
      shirt: new THREE.MeshMatcapMaterial({ matcap: mcWhiteShirt }),
      skyscraperFacade: new THREE.MeshStandardMaterial({
        map: facadeAtlas.colorTex,
        roughnessMap: facadeAtlas.roughnessTex,
        roughness: 1.0,
        metalness: 0.26,
        emissive: new THREE.Color(0xFFE4A3),
        emissiveMap: facadeAtlas.emissiveTex,
        emissiveIntensity: 0.78,
        envMapIntensity: 1.18
      }),
      supertowerFacade: new THREE.MeshStandardMaterial({
        map: stColorTex,
        roughnessMap: stRoughTex,
        roughness: 1.0,
        metalness: 0.30,
        emissive: new THREE.Color(0xFFE8B0),
        emissiveMap: stEmisTex,
        emissiveIntensity: 0.84,
        envMapIntensity: 1.28
      }),
      bakedAo: new THREE.MeshBasicMaterial({
        map: aoTex,
        transparent: true,
        opacity: 0.82,
        depthWrite: false,
        polygonOffset: true,
        polygonOffsetFactor: -3,
        polygonOffsetUnits: -6
      }),
      beaconGold: new THREE.MeshBasicMaterial({
        color: 0xFFE89E,
        polygonOffset: true,
        polygonOffsetFactor: -1,
        polygonOffsetUnits: -2
      }),
      deptVeins: new THREE.MeshBasicMaterial({
        color: 0xFFFFFF,
        polygonOffset: true,
        polygonOffsetFactor: -2,
        polygonOffsetUnits: -4
      }),
      panoramicGlass: new THREE.MeshBasicMaterial({
        color: 0x9BD5F5,
        transparent: true,
        opacity: 0.11,
        depthWrite: false,
        side: THREE.DoubleSide
      })
    };
  }

  // ==========================================================================
  // PHASE 1A: DEEP FUNKEN-UNIVERSUM STAR COSMOS & 2K ATMOSPHERIC SKY DOME
  // ==========================================================================
  _buildCosmosAndSky() {
    this.spaceGroup = new THREE.Group();
    this.scene.add(this.spaceGroup);

    // Sky Dome blending seamlessly from Deep Space (#010206) to 2K Volumetric Golden-Hour Panorama
    this.skyUniforms = {
      uSpaceBlend: { value: 1.0 },
      uSunDir: { value: new THREE.Vector3(-0.38, 0.24, -0.89).normalize() },
      uSkyPano: { value: this.skyPanoramaTex }
    };

    const skyMat = new THREE.ShaderMaterial({
      side: THREE.BackSide,
      depthWrite: false,
      uniforms: this.skyUniforms,
      vertexShader: `
        varying vec3 vWorldDir;
        void main() {
          vec4 wp = modelMatrix * vec4(position, 1.0);
          vWorldDir = normalize(position);
          gl_Position = projectionMatrix * viewMatrix * wp;
        }
      `,
      fragmentShader: `
        uniform float uSpaceBlend;
        uniform vec3 uSunDir;
        uniform sampler2D uSkyPano;
        varying vec3 vWorldDir;
        void main() {
          vec3 d = normalize(vWorldDir);
          float h = clamp(d.y, -0.2, 1.0);

          // Deep Space Cosmos background
          vec3 spaceCol = mix(vec3(0.012, 0.018, 0.038), vec3(0.003, 0.005, 0.012), clamp(h * 1.4 + 0.2, 0.0, 1.0));
          float nebula = pow(max(0.0, dot(d, normalize(vec3(0.45, 0.35, -0.82)))), 3.0);
          spaceCol += vec3(0.075, 0.055, 0.022) * nebula;

          // Sample seamless 2K Equirectangular Golden-Hour Cloud Panorama
          vec2 equirectUv = vec2(
            atan(d.z, d.x) * 0.15915494 + 0.5,
            asin(clamp(d.y, -1.0, 1.0)) * 0.31830989 + 0.5
          );
          vec3 citySky = texture2D(uSkyPano, equirectUv).rgb;

          vec3 col = mix(citySky, spaceCol, uSpaceBlend);
          gl_FragColor = vec4(col, 1.0);
        }
      `
    });
    const skyDome = new THREE.Mesh(new THREE.SphereGeometry(18500, 48, 32), skyMat);
    this.spaceGroup.add(skyDome);

    // 3,600 Crisp Multi-Hue Stars in the Funken-Universum
    const rng = mulberry32(424242);
    const starCount = 3600;
    const posArr = new Float32Array(starCount * 3);
    const colArr = new Float32Array(starCount * 3);
    const palette = [
      [1.00, 0.93, 0.75], // Warm 24K Gold Spark
      [0.96, 0.98, 1.00], // Pure Starlight
      [0.72, 0.86, 1.00], // Sapphire Ice
      [0.86, 0.70, 0.38]  // Deep Seelenfunke Gold
    ];

    for (let i = 0; i < starCount; i++) {
      const u = rng();
      const v = rng();
      const theta = u * Math.PI * 2;
      const phi = Math.acos(2 * v - 1);
      const rad = 11500 + rng() * 5200;
      posArr[i * 3]     = rad * Math.sin(phi) * Math.cos(theta);
      posArr[i * 3 + 1] = rad * Math.cos(phi);
      posArr[i * 3 + 2] = rad * Math.sin(phi) * Math.sin(theta);

      const c = palette[Math.floor(rng() * palette.length)];
      const br = 0.55 + rng() * 0.45;
      colArr[i * 3]     = c[0] * br;
      colArr[i * 3 + 1] = c[1] * br;
      colArr[i * 3 + 2] = c[2] * br;
    }

    const starGeo = new THREE.BufferGeometry();
    starGeo.setAttribute('position', new THREE.BufferAttribute(posArr, 3));
    starGeo.setAttribute('color', new THREE.BufferAttribute(colArr, 3));

    const starSprite = document.createElement('canvas');
    starSprite.width = 32;
    starSprite.height = 32;
    const sctx = starSprite.getContext('2d');
    const sg = sctx.createRadialGradient(16, 16, 0, 16, 16, 15);
    sg.addColorStop(0, 'rgba(255,255,255,1)');
    sg.addColorStop(0.35, 'rgba(255,244,214,0.85)');
    sg.addColorStop(1, 'rgba(255,244,214,0)');
    sctx.fillStyle = sg;
    sctx.fillRect(0, 0, 32, 32);

    this.starMat = new THREE.PointsMaterial({
      size: 46,
      map: new THREE.CanvasTexture(starSprite),
      vertexColors: true,
      transparent: true,
      opacity: 0.95,
      blending: THREE.AdditiveBlending,
      depthWrite: false
    });
    const stars = new THREE.Points(starGeo, this.starMat);
    this.spaceGroup.add(stars);
  }

  // ==========================================================================
  // PHASE 1B: PHOTOREALISTIC OCEAN PLANET (TANGENT AT (0,0,0) FOR SEAMLESS ZOOM)
  // ==========================================================================
  _buildOrbitalPlanet() {
    // Centered at (0, -PLANET_RADIUS, 0) so North Zenith sits at exact (0, 0, 0) = Funkenwerk City!
    this.planetGroup = new THREE.Group();
    this.planetGroup.position.set(0, -PLANET_RADIUS, 0);
    this.scene.add(this.planetGroup);

    const orbitalLightDir = new THREE.Vector3(0.28, 0.86, 0.42).normalize();

    // 1. Ocean Planet Surface Shader (Deep Sapphire Ocean + Turquoise Shoals + Golden Funkenwerk Star Beacon)
    this.planetSurfaceUniforms = {
      uTime: { value: 0 },
      uAlpha: { value: 1.0 },
      uLightDir: { value: orbitalLightDir }
    };

    const planetSurfMat = new THREE.ShaderMaterial({
      transparent: true,
      uniforms: this.planetSurfaceUniforms,
      vertexShader: `
        varying vec3 vNormalObj;
        varying vec3 vWorldPos;
        varying vec3 vWorldNormal;
        void main() {
          vNormalObj = normalize(position);
          vec4 wp = modelMatrix * vec4(position, 1.0);
          vWorldPos = wp.xyz;
          vWorldNormal = normalize(mat3(modelMatrix) * normal);
          gl_Position = projectionMatrix * viewMatrix * wp;
        }
      `,
      fragmentShader: `
        uniform float uTime;
        uniform float uAlpha;
        uniform vec3 uLightDir;
        varying vec3 vNormalObj;
        varying vec3 vWorldPos;
        varying vec3 vWorldNormal;

        float hash(vec3 p) {
          p = fract(p * 0.3183099 + 0.1);
          p *= 17.0;
          return fract(p.x * p.y * p.z * (p.x + p.y + p.z));
        }
        float noise(vec3 x) {
          vec3 i = floor(x);
          vec3 f = fract(x);
          f = f * f * (3.0 - 2.0 * f);
          return mix(
            mix(mix(hash(i + vec3(0,0,0)), hash(i + vec3(1,0,0)), f.x),
                mix(hash(i + vec3(0,1,0)), hash(i + vec3(1,1,0)), f.x), f.y),
            mix(mix(hash(i + vec3(0,0,1)), hash(i + vec3(1,0,1)), f.x),
                mix(hash(i + vec3(0,1,1)), hash(i + vec3(1,1,1)), f.x), f.y), f.z);
        }
        float fbm(vec3 p) {
          float v = 0.0;
          float a = 0.5;
          for (int i = 0; i < 4; i++) {
            v += a * noise(p);
            p *= 2.05;
            a *= 0.5;
          }
          return v;
        }

        void main() {
          vec3 N = normalize(vWorldNormal);
          vec3 V = normalize(cameraPosition - vWorldPos);
          vec3 L = normalize(uLightDir);

          float n1 = fbm(vNormalObj * 4.5);
          float n2 = fbm(vNormalObj * 14.0 + vec3(2.1));
          float shoal = smoothstep(0.54, 0.76, n1 * 0.75 + n2 * 0.35);

          vec3 deepOcean = vec3(0.018, 0.075, 0.165);
          vec3 midOcean  = vec3(0.035, 0.175, 0.320);
          vec3 reefTeal  = vec3(0.065, 0.340, 0.440);
          vec3 baseCol = mix(deepOcean, midOcean, n1);
          baseCol = mix(baseCol, reefTeal, shoal * 0.65);

          float ndl = max(0.0, dot(N, L));
          float terminator = smoothstep(-0.12, 0.25, dot(N, L));

          // Sunlit golden specular ocean sheen from orbit
          vec3 H = normalize(L + V);
          float spec = pow(max(0.0, dot(N, H)), 42.0) * 0.72 + pow(max(0.0, dot(N, H)), 8.0) * 0.16;
          vec3 specCol = vec3(1.00, 0.88, 0.58) * spec * terminator;

          // Atmospheric Fresnel Rim on Planet Surface (Ocean Cyan + 24K Gold)
          float fres = pow(1.0 - max(0.0, dot(N, V)), 3.4);
          vec3 rimCol = mix(vec3(0.08, 0.52, 0.82), vec3(0.86, 0.68, 0.34), ndl) * fres * 0.85;

          // Radiant Golden Star Point of "Funkenwerk" right at North Zenith (0, 1, 0)!
          float distToCity = length(vNormalObj - vec3(0.0, 1.0, 0.0));
          float pulse = 0.88 + 0.12 * sin(uTime * 3.2);
          float cityCore = exp(-distToCity * 58.0) * 2.2 * pulse;
          float cityHalo = exp(-distToCity * 14.5) * 0.65 * pulse;
          vec3 cityGlow = vec3(1.00, 0.86, 0.45) * (cityCore + cityHalo);

          vec3 col = baseCol * (0.07 + 0.95 * ndl) + specCol + rimCol + cityGlow;
          gl_FragColor = vec4(col, uAlpha);
        }
      `
    });
    const planetSphere = new THREE.Mesh(new THREE.SphereGeometry(PLANET_RADIUS, 96, 72), planetSurfMat);
    this.planetGroup.add(planetSphere);

    // 2. Gently Rotating Sunlit Cloud Layer (with clear eye opening above Funkenwerk!)
    this.planetCloudUniforms = {
      uTime: { value: 0 },
      uAlpha: { value: 1.0 },
      uLightDir: { value: orbitalLightDir }
    };

    const cloudMat = new THREE.ShaderMaterial({
      transparent: true,
      depthWrite: false,
      uniforms: this.planetCloudUniforms,
      vertexShader: `
        varying vec3 vObjDir;
        varying vec3 vWorldNormal;
        varying vec3 vWorldPos;
        void main() {
          vObjDir = normalize(position);
          vec4 wp = modelMatrix * vec4(position, 1.0);
          vWorldPos = wp.xyz;
          vWorldNormal = normalize(mat3(modelMatrix) * normal);
          gl_Position = projectionMatrix * viewMatrix * wp;
        }
      `,
      fragmentShader: `
        uniform float uTime;
        uniform float uAlpha;
        uniform vec3 uLightDir;
        varying vec3 vObjDir;
        varying vec3 vWorldNormal;
        varying vec3 vWorldPos;

        float hash(vec3 p) {
          p = fract(p * 0.3183099 + 0.1);
          p *= 17.0;
          return fract(p.x * p.y * p.z * (p.x + p.y + p.z));
        }
        float noise(vec3 x) {
          vec3 i = floor(x);
          vec3 f = fract(x);
          f = f * f * (3.0 - 2.0 * f);
          return mix(
            mix(mix(hash(i + vec3(0,0,0)), hash(i + vec3(1,0,0)), f.x),
                mix(hash(i + vec3(0,1,0)), hash(i + vec3(1,1,0)), f.x), f.y),
            mix(mix(hash(i + vec3(0,0,1)), hash(i + vec3(1,0,1)), f.x),
                mix(hash(i + vec3(0,1,1)), hash(i + vec3(1,1,1)), f.x), f.z), f.z);
        }
        float fbm(vec3 p) {
          float v = 0.0;
          float a = 0.52;
          for (int i = 0; i < 4; i++) {
            v += a * noise(p);
            p *= 2.12;
            a *= 0.5;
          }
          return v;
        }

        void main() {
          float ang = uTime * 0.018;
          mat2 rot = mat2(cos(ang), -sin(ang), sin(ang), cos(ang));
          vec3 p = vObjDir;
          p.xz = rot * p.xz;

          float swirl = fbm(p * 5.2 + vec3(0.0, p.y * 1.8, 0.0));
          float detail = fbm(p * 13.5 + swirl * 1.4);
          float cloud = smoothstep(0.46, 0.76, swirl * 0.65 + detail * 0.42);

          // Part the clouds gently right over Funkenwerk zenith so the golden beacon shines through!
          float zenithDist = length(vObjDir - vec3(0.0, 1.0, 0.0));
          float eyeClear = smoothstep(0.025, 0.13, zenithDist);
          cloud *= (0.22 + 0.78 * eyeClear);

          float ndl = max(0.05, dot(normalize(vWorldNormal), normalize(uLightDir)));
          vec3 cloudCol = mix(vec3(0.76, 0.86, 0.96), vec3(1.00, 0.96, 0.86), ndl) * (0.20 + 0.92 * ndl);
          gl_FragColor = vec4(cloudCol, cloud * 0.68 * uAlpha);
        }
      `
    });
    this.planetCloudsMesh = new THREE.Mesh(new THREE.SphereGeometry(PLANET_RADIUS + 22, 80, 60), cloudMat);
    this.planetGroup.add(this.planetCloudsMesh);

    // 3. Outer Atmospheric Fresnel Rim-Glow Shell (Gold & Ocean-Blue)
    this.planetAtmoUniforms = {
      uAlpha: { value: 1.0 },
      uLightDir: { value: orbitalLightDir }
    };
    const atmoMat = new THREE.ShaderMaterial({
      side: THREE.BackSide,
      transparent: true,
      blending: THREE.AdditiveBlending,
      depthWrite: false,
      uniforms: this.planetAtmoUniforms,
      vertexShader: `
        varying vec3 vWorldNormal;
        varying vec3 vWorldPos;
        void main() {
          vec4 wp = modelMatrix * vec4(position, 1.0);
          vWorldPos = wp.xyz;
          vWorldNormal = normalize(mat3(modelMatrix) * normal);
          gl_Position = projectionMatrix * viewMatrix * wp;
        }
      `,
      fragmentShader: `
        uniform float uAlpha;
        uniform vec3 uLightDir;
        varying vec3 vWorldNormal;
        varying vec3 vWorldPos;
        void main() {
          vec3 N = normalize(vWorldNormal);
          vec3 V = normalize(cameraPosition - vWorldPos);
          float rim = pow(clamp(0.72 - dot(-N, V), 0.0, 1.0), 2.8);
          float sun = clamp(dot(N, normalize(uLightDir)) * 0.5 + 0.5, 0.1, 1.0);
          vec3 atmoCol = mix(vec3(0.08, 0.55, 0.92), vec3(0.92, 0.74, 0.36), pow(sun, 2.2));
          gl_FragColor = vec4(atmoCol * rim * 1.15 * sun, rim * uAlpha);
        }
      `
    });
    const atmoShell = new THREE.Mesh(new THREE.SphereGeometry(PLANET_RADIUS + 96, 64, 48), atmoMat);
    this.planetGroup.add(atmoShell);
  }

  // ==========================================================================
  // PHASE 2A: REALISTIC MULTI-WAVE GLSL 3D OCEAN WITH GOLDEN TOWER REFLECTIONS
  // ==========================================================================
  _buildOcean() {
    this.oceanUniforms = {
      uTime: { value: 0 },
      uAlpha: { value: 1.0 },
      uSunDir: { value: new THREE.Vector3(-0.38, 0.24, -0.89).normalize() },
      uSkyPano: { value: this.skyPanoramaTex }
    };

    const oceanMat = new THREE.ShaderMaterial({
      transparent: true,
      uniforms: this.oceanUniforms,
      vertexShader: `
        uniform float uTime;
        varying vec3 vWorldPos;
        void main() {
          vec3 pos = position;
          float r = length(pos.xz);
          // Subtle physical swell near the city piers
          float swell = (sin(pos.x * 0.045 + uTime * 1.25) + cos(pos.z * 0.038 - uTime * 1.05)) * 0.28;
          pos.y += swell * smoothstep(2600.0, 180.0, r);
          vec4 wp = modelMatrix * vec4(pos, 1.0);
          vWorldPos = wp.xyz;
          gl_Position = projectionMatrix * viewMatrix * wp;
        }
      `,
      fragmentShader: `
        uniform float uTime;
        uniform float uAlpha;
        uniform vec3 uSunDir;
        uniform sampler2D uSkyPano;
        varying vec3 vWorldPos;

        // 6-Component Multi-Scale Analytical Ocean Wave Normal (60 FPS on UHD 605!)
        vec3 getOceanNormal(vec2 p, float dist) {
          float detailFade = smoothstep(3800.0, 120.0, dist);
          float dx = 0.0;
          float dz = 0.0;

          float w1 = sin(p.x * 0.085 + p.y * 0.042 + uTime * 1.45);
          dx += 0.085 * w1 * 0.42;
          dz += 0.042 * w1 * 0.42;

          float w2 = cos(p.x * -0.052 + p.y * 0.095 - uTime * 1.22);
          dx += -0.052 * w2 * 0.38;
          dz += 0.095 * w2 * 0.38;

          float w3 = sin(p.x * 0.23 + p.y * -0.19 + uTime * 2.15);
          dx += 0.23 * w3 * 0.16 * detailFade;
          dz += -0.19 * w3 * 0.16 * detailFade;

          float w4 = cos(p.x * 0.47 + p.y * 0.41 - uTime * 2.85);
          dx += 0.47 * w4 * 0.075 * detailFade;
          dz += 0.41 * w4 * 0.075 * detailFade;

          float w5 = sin(p.x * -0.82 + p.y * 0.68 + uTime * 3.40);
          dx += -0.82 * w5 * 0.038 * detailFade;
          dz += 0.68 * w5 * 0.038 * detailFade;

          return normalize(vec3(-dx, 1.0, -dz));
        }

        vec3 getDeptColor(float angle) {
          // Maps pier angle [0..2PI] smoothly to the 7 Seelenfunke Department Accents
          float a = mod(angle - 0.36 + 6.2831853, 6.2831853);
          float idx = floor((a / 6.2831853) * 7.0 + 0.5);
          idx = mod(idx, 7.0);
          if (idx < 0.5) return vec3(0.024, 0.714, 0.831); // Support Cyan #06B6D4
          if (idx < 1.5) return vec3(0.231, 0.510, 0.965); // Produkte Blue #3B82F6
          if (idx < 2.5) return vec3(0.659, 0.333, 0.969); // Marketing Violet #A855F7
          if (idx < 3.5) return vec3(0.961, 0.620, 0.043); // Bestellungen Amber #F59E0B
          if (idx < 4.5) return vec3(0.063, 0.725, 0.506); // Buchhaltung Emerald #10B981
          if (idx < 5.5) return vec3(0.388, 0.400, 0.945); // Agenten Indigo #6366F1
          return vec3(0.937, 0.267, 0.267);                // System Ruby #EF4444
        }

        void main() {
          vec2 xz = vWorldPos.xz;
          float dist = length(xz);
          vec3 V = normalize(cameraPosition - vWorldPos);
          vec3 N = getOceanNormal(xz, dist);
          vec3 L = normalize(uSunDir);

          // Lagoon shallow teal vs deep open sapphire ocean
          float lagoonMask = smoothstep(640.0, 160.0, dist);
          vec3 deepWater = vec3(0.014, 0.058, 0.120);
          vec3 lagoonWater = vec3(0.030, 0.142, 0.232);
          vec3 waterBase = mix(deepWater, lagoonWater, lagoonMask * 0.72);

          // True Fresnel Reflection sampling the 2K Golden-Hour Sky & Volumetric Clouds!
          float fresnel = pow(1.0 - max(0.0, dot(N, V)), 4.0);
          vec3 rawRefl = reflect(-V, N);
          vec3 reflDir = normalize(vec3(rawRefl.x, abs(rawRefl.y) + 0.015, rawRefl.z));
          vec2 reflUv = vec2(
            atan(reflDir.z, reflDir.x) * 0.15915494 + 0.5,
            asin(clamp(reflDir.y, -1.0, 1.0)) * 0.31830989 + 0.5
          );
          vec3 skyRefl = texture2D(uSkyPano, reflUv).rgb * 0.90;
          vec3 col = mix(waterBase, skyRefl, clamp(0.18 + fresnel * 0.78, 0.0, 0.92));

          // Sun Specular Glitter Path across Waves
          vec3 H = normalize(L + V);
          float sunSpecTight = pow(max(0.0, dot(N, H)), 180.0) * 1.65;
          float sunSpecBroad = pow(max(0.0, dot(N, H)), 24.0) * 0.28;
          col += vec3(1.00, 0.91, 0.65) * (sunSpecTight + sunSpecBroad);

          // Wave-Distorted Golden Reflections of Supertower & 7 Atlantis Piers!
          vec2 warpedXz = xz + N.xz * 26.0;
          float wDist = length(warpedXz);
          float wAng = atan(warpedXz.y, warpedXz.x);

          // Central Supertower & Core Golden Shimmer Reflection
          float coreRefl = exp(-max(0.0, wDist - 125.0) * 0.014) * smoothstep(90.0, 155.0, wDist);
          float waveSparkle = 0.55 + 0.45 * sin(N.x * 22.0 + N.z * 18.0 + uTime * 2.5);
          col += vec3(0.86, 0.68, 0.34) * coreRefl * waveSparkle * 0.52;

          // 7 Radial Pier & Ring-Bridge Water Reflections (90% Gold + ~10% Department Tint)
          float pierStar = pow(max(0.0, cos((wAng - 0.36) * 7.0)), 6.0);
          float ring1 = exp(-pow((wDist - 252.0) * 0.055, 2.0));
          float ring2 = exp(-pow((wDist - 412.0) * 0.045, 2.0));
          float pierRange = smoothstep(620.0, 155.0, wDist) * smoothstep(120.0, 165.0, wDist);

          vec3 deptCol = getDeptColor(wAng);
          vec3 pierLightCol = mix(vec3(0.84, 0.66, 0.33), deptCol, 0.22);
          col += pierLightCol * (pierStar * pierRange * 0.34 + (ring1 + ring2) * 0.24) * waveSparkle;

          // Distant Atmospheric Horizon Blend
          float horizonFog = smoothstep(3200.0, 9200.0, dist);
          vec3 horUvCol = texture2D(uSkyPano, vec2(atan(vWorldPos.z, vWorldPos.x) * 0.15915494 + 0.5, 0.505)).rgb;
          col = mix(col, horUvCol * 0.88, horizonFog);

          gl_FragColor = vec4(col, uAlpha);
        }
      `
    });

    const oceanGeo = new THREE.RingGeometry(0.1, 9600, 96, 24);
    oceanGeo.rotateX(-Math.PI / 2);
    this.oceanMesh = new THREE.Mesh(oceanGeo, oceanMat);
    this.oceanMesh.position.y = 0.0;
    this.scene.add(this.oceanMesh);
  }

  // ==========================================================================
  // 7 INDIVIDUAL 3D DEPARTMENT LANDMARKS ON THE PIER CITADEL ROOFS
  // ==========================================================================
  _buildDepartmentLandmark(dept, deptIndex) {
    const landmarkRoot = new THREE.Group();
    landmarkRoot.position.set(0, 8.20, 362);

    // Subtle matte satin department accent material (~10% architectural accent, zero artificial neon glow)
    const accentMat = new THREE.MeshStandardMaterial({
      color: dept.color,
      metalness: 0.32,
      roughness: 0.38,
      envMapIntensity: 0.95,
      polygonOffset: true,
      polygonOffsetFactor: -1,
      polygonOffsetUnits: -2
    });

    // Stepped Schwarz-Gold Citadel Altar Tower (Y_local = 0 .. 10.4m -> Y_world = 8.2 .. 18.6m)
    const altarBase = new THREE.Mesh(new THREE.CylinderGeometry(11.6, 13.8, 3.6, 8), this.mats.obsidianPbr);
    altarBase.position.y = 1.8;
    landmarkRoot.add(altarBase);

    const altarCollar1 = new THREE.Mesh(new THREE.CylinderGeometry(12.2, 12.6, 0.62, 8), this.mats.goldPbrBright);
    altarCollar1.position.y = 3.95;
    landmarkRoot.add(altarCollar1);

    const altarMid = new THREE.Mesh(new THREE.CylinderGeometry(8.8, 10.8, 4.8, 8), this.mats.obsidianPbr);
    altarMid.position.y = 6.7;
    landmarkRoot.add(altarMid);

    const altarCrown = new THREE.Mesh(new THREE.CylinderGeometry(9.6, 9.2, 0.72, 8), this.mats.goldPbrBright);
    altarCrown.position.y = 9.5;
    landmarkRoot.add(altarCrown);

    const altarAccentRing = new THREE.Mesh(new THREE.TorusGeometry(8.2, 0.28, 8, 32), accentMat);
    altarAccentRing.rotation.x = Math.PI * 0.5;
    altarAccentRing.position.y = 10.05;
    landmarkRoot.add(altarAccentRing);

    // Levitating Rooftop Landmark Assembly (centered at Y_local = 17.8m -> Y_world = 26.0m)
    const floatGroup = new THREE.Group();
    floatGroup.position.set(0, 17.8, 0);
    landmarkRoot.add(floatGroup);

    const primaryRotor = new THREE.Group();
    floatGroup.add(primaryRotor);

    const secondaryRotor = new THREE.Group();
    floatGroup.add(secondaryRotor);

    let landmarkType = 'monolith';

    if (dept.key === 'support') {
      // 1. SUPPORT: Kommunikations-Parabolring (parabolic_comm_ring)
      landmarkType = 'parabolic_comm_ring';
      const outerWaveRing = new THREE.Mesh(new THREE.TorusGeometry(6.8, 0.46, 12, 48), this.mats.goldPbrBright);
      outerWaveRing.rotation.x = 0.42;
      primaryRotor.add(outerWaveRing);

      const dishGeo = new THREE.SphereGeometry(5.2, 28, 16, 0, Math.PI * 2, 0, 0.92);
      const dish = new THREE.Mesh(dishGeo, this.mats.obsidianPbr);
      dish.rotation.x = Math.PI * 0.74;
      dish.position.set(0, -0.8, 0);
      primaryRotor.add(dish);

      const dishRim = new THREE.Mesh(new THREE.TorusGeometry(4.15, 0.28, 10, 36), this.mats.goldPbrBright);
      dishRim.rotation.x = Math.PI * 0.24;
      dishRim.position.set(0, 1.42, -1.35);
      primaryRotor.add(dishRim);

      const feedSpire = new THREE.Mesh(new THREE.CylinderGeometry(0.24, 0.58, 6.2, 12), this.mats.goldPbrBrushed);
      feedSpire.rotation.x = -Math.PI * 0.26;
      feedSpire.position.set(0, 1.35, -1.3);
      primaryRotor.add(feedSpire);

      const relayOrb = new THREE.Mesh(new THREE.OctahedronGeometry(1.15, 1), accentMat);
      relayOrb.position.set(0, 3.4, -3.4);
      primaryRotor.add(relayOrb);

      const innerSignalRing = new THREE.Mesh(new THREE.TorusGeometry(4.9, 0.22, 10, 40), accentMat);
      innerSignalRing.rotation.y = Math.PI * 0.35;
      secondaryRotor.add(innerSignalRing);
    } else if (dept.key === 'products') {
      // 2. PRODUKTE: Facettiertes K9-Kristall-Prisma (k9_crystal_prism)
      landmarkType = 'k9_crystal_prism';
      const k9Mat = new THREE.MeshStandardMaterial({
        color: 0xCFE8FF,
        metalness: 0.18,
        roughness: 0.08,
        envMapIntensity: 1.65
      });
      const crystalCore = new THREE.Mesh(new THREE.OctahedronGeometry(5.2, 0), k9Mat);
      crystalCore.scale.set(0.86, 1.34, 0.86);
      primaryRotor.add(crystalCore);

      const crystalGirdle = new THREE.Mesh(new THREE.CylinderGeometry(4.65, 4.65, 0.42, 6), this.mats.goldPbrBright);
      primaryRotor.add(crystalGirdle);

      const caliperOuter = new THREE.Mesh(new THREE.TorusGeometry(6.9, 0.36, 8, 6), this.mats.goldPbrBright);
      secondaryRotor.add(caliperOuter);

      const caliperAccent = new THREE.Mesh(new THREE.TorusGeometry(5.8, 0.20, 8, 36), accentMat);
      caliperAccent.rotation.x = Math.PI * 0.5;
      secondaryRotor.add(caliperAccent);
    } else if (dept.key === 'marketing') {
      // 3. MARKETING: Goldene Licht-Krone (golden_light_crown)
      landmarkType = 'golden_light_crown';
      const crownBand = new THREE.Mesh(new THREE.CylinderGeometry(5.4, 4.6, 2.2, 8), this.mats.obsidianPbr);
      crownBand.position.y = -1.2;
      primaryRotor.add(crownBand);

      const crownRimBot = new THREE.Mesh(new THREE.TorusGeometry(4.8, 0.36, 10, 32), this.mats.goldPbrBright);
      crownRimBot.rotation.x = Math.PI * 0.5;
      crownRimBot.position.y = -2.2;
      primaryRotor.add(crownRimBot);

      const crownRimTop = new THREE.Mesh(new THREE.TorusGeometry(5.5, 0.38, 10, 32), this.mats.goldPbrBright);
      crownRimTop.rotation.x = Math.PI * 0.5;
      crownRimTop.position.y = -0.1;
      primaryRotor.add(crownRimTop);

      for (let s = 0; s < 8; s++) {
        const ang = (s / 8) * Math.PI * 2;
        const spikeH = (s % 2 === 0) ? 5.6 : 3.8;
        const spike = new THREE.Mesh(new THREE.ConeGeometry(0.78, spikeH, 4), this.mats.goldPbrBright);
        spike.position.set(Math.cos(ang) * 5.3, spikeH * 0.5 - 0.1, Math.sin(ang) * 5.3);
        spike.rotation.y = -ang;
        spike.rotation.z = -Math.cos(ang) * 0.16;
        spike.rotation.x = Math.sin(ang) * 0.16;
        primaryRotor.add(spike);

        const jewel = new THREE.Mesh(new THREE.OctahedronGeometry(0.58, 0), accentMat);
        jewel.position.set(Math.cos(ang) * 5.15, -1.15, Math.sin(ang) * 5.15);
        primaryRotor.add(jewel);
      }

      const centerStar = new THREE.Mesh(new THREE.OctahedronGeometry(2.3, 0), this.mats.goldPbrBright);
      centerStar.position.y = 1.6;
      secondaryRotor.add(centerStar);
    } else if (dept.key === 'orders') {
      // 4. BESTELLUNGEN: Praezisions-Laser-Fokusring (precision_laser_focus_ring)
      landmarkType = 'precision_laser_focus_ring';
      const collimatorRing = new THREE.Mesh(new THREE.CylinderGeometry(6.8, 6.8, 1.5, 24, 1, true), this.mats.obsidianPbr);
      collimatorRing.rotation.x = 0.36;
      primaryRotor.add(collimatorRing);

      for (const gy of [-0.82, 0.82]) {
        const rim = new THREE.Mesh(new THREE.TorusGeometry(6.85, 0.34, 10, 48), this.mats.goldPbrBright);
        rim.rotation.x = Math.PI * 0.5 + 0.36;
        rim.position.set(0, gy * Math.cos(0.36), gy * Math.sin(0.36));
        primaryRotor.add(rim);
      }

      // 4 inward-pointing micrometric xTool UV focus calipers
      for (let c = 0; c < 4; c++) {
        const cAng = (c / 4) * Math.PI * 2;
        const emitter = new THREE.Mesh(new THREE.ConeGeometry(0.62, 3.2, 8), this.mats.goldPbrBright);
        emitter.position.set(Math.cos(cAng) * 4.8, 0, Math.sin(cAng) * 4.8);
        emitter.rotation.z = Math.cos(cAng) * Math.PI * 0.5;
        emitter.rotation.x = -Math.sin(cAng) * Math.PI * 0.5;
        secondaryRotor.add(emitter);
      }

      const focusTargetRing = new THREE.Mesh(new THREE.TorusGeometry(2.75, 0.26, 10, 36), accentMat);
      focusTargetRing.rotation.x = Math.PI * 0.5;
      secondaryRotor.add(focusTargetRing);

      const blankCore = new THREE.Mesh(new THREE.CylinderGeometry(1.85, 1.85, 0.9, 8), this.mats.obsidianPbr);
      secondaryRotor.add(blankCore);
    } else if (dept.key === 'accounting') {
      // 5. BUCHHALTUNG: Oktogonaler Tresor-Monolith (octagonal_vault_monolith)
      landmarkType = 'octagonal_vault_monolith';
      const vaultBody = new THREE.Mesh(new THREE.CylinderGeometry(5.2, 5.2, 3.6, 8), this.mats.obsidianPbr);
      vaultBody.rotation.x = Math.PI * 0.5;
      primaryRotor.add(vaultBody);

      const vaultOuterRim = new THREE.Mesh(new THREE.TorusGeometry(5.45, 0.52, 8, 8), this.mats.goldPbrBright);
      primaryRotor.add(vaultOuterRim);

      const sealRing = new THREE.Mesh(new THREE.TorusGeometry(3.85, 0.24, 8, 32), accentMat);
      sealRing.position.z = 1.92;
      primaryRotor.add(sealRing);

      const sealRingBack = sealRing.clone();
      sealRingBack.position.z = -1.92;
      primaryRotor.add(sealRingBack);

      // Twin 8-spoke 24K-gold vault locking wheels
      for (const vz of [-2.05, 2.05]) {
        const wheel = new THREE.Group();
        wheel.position.z = vz;
        wheel.add(new THREE.Mesh(new THREE.TorusGeometry(2.55, 0.26, 8, 24), this.mats.goldPbrBright));
        for (let sp = 0; sp < 4; sp++) {
          const bar = new THREE.Mesh(new THREE.BoxGeometry(4.9, 0.28, 0.28), this.mats.goldPbrBright);
          bar.rotation.z = (sp / 4) * Math.PI;
          wheel.add(bar);
        }
        const hub = new THREE.Mesh(new THREE.CylinderGeometry(0.78, 0.78, 0.5, 12), this.mats.goldPbrBright);
        hub.rotation.x = Math.PI * 0.5;
        wheel.add(hub);
        secondaryRotor.add(wheel);
      }
    } else if (dept.key === 'agents') {
      // 6. AGENTEN: Mehrachsige Neuronale Gyro-Sphaere (neural_gyro_sphere)
      landmarkType = 'neural_gyro_sphere';
      const gyroOuter = new THREE.Mesh(new THREE.TorusGeometry(7.0, 0.38, 10, 48), this.mats.goldPbrBright);
      primaryRotor.add(gyroOuter);

      const gyroMid = new THREE.Mesh(new THREE.TorusGeometry(5.5, 0.32, 10, 44), this.mats.goldPbrBrushed);
      gyroMid.rotation.x = Math.PI * 0.38;
      secondaryRotor.add(gyroMid);

      const gyroInner = new THREE.Mesh(new THREE.TorusGeometry(4.1, 0.24, 10, 36), accentMat);
      gyroInner.rotation.y = Math.PI * 0.42;
      secondaryRotor.add(gyroInner);

      const neuralCore = new THREE.Mesh(new THREE.IcosahedronGeometry(2.45, 1), this.mats.obsidianPbr);
      primaryRotor.add(neuralCore);

      const synapticCage = new THREE.Mesh(
        new THREE.IcosahedronGeometry(2.62, 1),
        new THREE.MeshBasicMaterial({ color: 0xD4AF37, wireframe: true })
      );
      primaryRotor.add(synapticCage);
    } else {
      // 7. SYSTEM: Gepanzerter Reaktor-Schildkern (armored_reactor_shield_core)
      landmarkType = 'armored_reactor_shield_core';
      const gearRing = new THREE.Mesh(new THREE.TorusGeometry(5.8, 0.48, 10, 36), this.mats.goldPbrBright);
      gearRing.rotation.x = Math.PI * 0.5;
      primaryRotor.add(gearRing);

      for (let t = 0; t < 12; t++) {
        const tang = (t / 12) * Math.PI * 2;
        const tooth = new THREE.Mesh(new THREE.BoxGeometry(1.15, 0.95, 1.35), this.mats.goldPbrBright);
        tooth.position.set(Math.cos(tang) * 6.35, 0, Math.sin(tang) * 6.35);
        tooth.rotation.y = -tang;
        primaryRotor.add(tooth);
      }

      // 6 Armored Obsidian-Gold Aegis Shield Plates
      for (let p = 0; p < 6; p++) {
        const pang = (p / 6) * Math.PI * 2;
        const shield = new THREE.Mesh(new THREE.BoxGeometry(2.6, 5.4, 0.65), this.mats.obsidianPbr);
        shield.position.set(Math.cos(pang) * 4.1, 0, Math.sin(pang) * 4.1);
        shield.rotation.y = -pang + Math.PI * 0.5;
        secondaryRotor.add(shield);

        const shieldTrim = new THREE.Mesh(new THREE.BoxGeometry(2.8, 0.42, 0.78), this.mats.goldPbrBright);
        shieldTrim.position.set(Math.cos(pang) * 4.12, 2.55, Math.sin(pang) * 4.12);
        shieldTrim.rotation.y = -pang + Math.PI * 0.5;
        secondaryRotor.add(shieldTrim);
      }

      const reactorCore = new THREE.Mesh(new THREE.OctahedronGeometry(2.35, 1), accentMat);
      primaryRotor.add(reactorCore);
    }

    this.departmentLandmarks.push({
      key: dept.key,
      landmarkType,
      floatGroup,
      primaryRotor,
      secondaryRotor,
      baseY: 17.8,
      phase: deptIndex * 0.92
    });

    return landmarkRoot;
  }

  // ==========================================================================
  // PHASE 2B: STARGATE-ATLANTIS WATER METROPOLIS (7 PIERS + RING BRIDGES + INSTANCED TOWERS)
  // ==========================================================================
  _buildAtlantisMetropolis() {
    this.cityGroup = new THREE.Group();
    this.scene.add(this.cityGroup);

    const rng = mulberry32(20260707);

    // 1. Central Metropolis Core Island (Cleanly Stepped >= 0.70m Vertical Separation -> Zero Coplanar Overlap!)
    const coreRadii = [
      { r: 148.0, h: 2.80, y: 1.40, segs: 14, mat: this.mats.anthracite },
      { r: 149.2, h: 0.68, y: 3.16, segs: 14, mat: this.mats.goldBrushed },
      { r: 132.0, h: 2.90, y: 4.98, segs: 14, mat: this.mats.obsidian },
      { r: 133.2, h: 0.68, y: 6.80, segs: 14, mat: this.mats.goldBright },
      { r: 96.0,  h: 2.30, y: 8.32, segs: 14, mat: this.mats.obsidian }
    ];
    coreRadii.forEach((tier) => {
      const m = new THREE.Mesh(new THREE.CylinderGeometry(tier.r * 0.97, tier.r, tier.h, tier.segs), tier.mat);
      m.position.y = tier.y;
      this.cityGroup.add(m);
    });

    // Chamfered Stargate-Atlantis Outer Pier Wing Shape (Extruded once, reused for all 7 Piers!)
    const wingShape = new THREE.Shape();
    wingShape.moveTo(-22, 275);
    wingShape.lineTo(-68, 325);
    wingShape.lineTo(-74, 428);
    wingShape.lineTo(-46, 488);
    wingShape.lineTo(-19, 502);
    wingShape.lineTo(-19, 456); // Recessed Atlantis boat/glider slip!
    wingShape.lineTo( -7, 456);
    wingShape.lineTo( -7, 504);
    wingShape.lineTo(  7, 504);
    wingShape.lineTo(  7, 456);
    wingShape.lineTo( 19, 456);
    wingShape.lineTo( 19, 502);
    wingShape.lineTo( 46, 488);
    wingShape.lineTo( 74, 428);
    wingShape.lineTo( 68, 325);
    wingShape.lineTo( 22, 275);
    wingShape.closePath();

    // wingDeck top at Y = 4.80 + 0.70 = 5.50m; wingGoldTrim wider seawall promenade stepped cleanly at Y = 4.70 + 0.32 = 5.02m (0.48m step!)
    const wingDeckGeo = new THREE.ExtrudeGeometry(wingShape, {
      depth: 4.1,
      bevelEnabled: true,
      bevelThickness: 0.70,
      bevelSize: 0.90,
      bevelSegments: 1
    });
    wingDeckGeo.rotateX(Math.PI / 2);

    const wingGoldTrimGeo = new THREE.ExtrudeGeometry(wingShape, {
      depth: 0.72,
      bevelEnabled: true,
      bevelThickness: 0.32,
      bevelSize: 2.35,
      bevelSegments: 1
    });
    wingGoldTrimGeo.rotateX(Math.PI / 2);

    // Collect Instance Transforms for Skyscrapers, Crowns, Pylons, Department Veins & Baked AO Shadows!
    const towerTransforms = [];
    const goldFrameTransforms = [];
    const spireTransforms = [];
    const aoTransforms = [];
    const deptVeinItems = []; // { matrix, color }

    const pushMat = (arr, x, y, z, sx, sy, sz, rotY = 0) => {
      this.dummyObj.position.set(x, y, z);
      this.dummyObj.rotation.set(0, rotY, 0);
      this.dummyObj.scale.set(sx, sy, sz);
      this.dummyObj.updateMatrix();
      arr.push(this.dummyObj.matrix.clone());
    };

    const pushAo = (x, y, z, sx, sz, rotY = 0) => {
      this.dummyObj.position.set(x, y, z);
      this.dummyObj.rotation.set(0, rotY, 0);
      this.dummyObj.scale.set(sx, 1, sz);
      this.dummyObj.updateMatrix();
      aoTransforms.push(this.dummyObj.matrix.clone());
    };

    const pushDeptVein = (x, y, z, sx, sy, sz, rotY, hexCol) => {
      this.dummyObj.position.set(x, y, z);
      this.dummyObj.rotation.set(0, rotY, 0);
      this.dummyObj.scale.set(sx, sy, sz);
      this.dummyObj.updateMatrix();
      deptVeinItems.push({ matrix: this.dummyObj.matrix.clone(), color: new THREE.Color(hexCol) });
    };

    // Soft Baked AO under Supertower Base Podium & Core Terrace Step
    pushAo(0, 9.55, 0, 94, 94, 0);
    pushAo(0, 6.51, 0, 212, 212, 0);

    // Helper to add a multi-tier stepped Atlantis skyscraper + Baked AO Contact Shadow at world (wx, baseY, wz)
    const addSteppedSkyscraper = (wx, baseY, wz, baseW, baseD, totalH, rotY, deptHex = null) => {
      // Grounding Baked AO Contact Shadow directly on Pier/Core Deck (0% real-time shadow cost!)
      pushAo(wx, baseY + 0.08, wz, baseW * 1.66, baseD * 1.66, rotY);

      // Main lower shaft
      const h1 = totalH * 0.64;
      pushMat(towerTransforms, wx, baseY + h1 * 0.5, wz, baseW, h1, baseD, rotY);
      pushMat(goldFrameTransforms, wx, baseY + h1 + 0.62, wz, baseW * 1.07, 1.2, baseD * 1.07, rotY);

      // Stepped mid setback
      const h2 = totalH * 0.26;
      const w2 = baseW * 0.74;
      const d2 = baseD * 0.74;
      pushMat(towerTransforms, wx, baseY + h1 + h2 * 0.5, wz, w2, h2, d2, rotY);
      pushMat(goldFrameTransforms, wx, baseY + h1 + h2 + 0.54, wz, w2 * 1.09, 1.0, d2 * 1.09, rotY);

      // Upper penthouse crown + 24K-Gold Spire Needle
      const h3 = totalH * 0.12;
      const w3 = baseW * 0.48;
      pushMat(goldFrameTransforms, wx, baseY + h1 + h2 + h3 * 0.5, wz, w3, h3, w3, rotY);
      const spireH = Math.max(8, totalH * 0.20);
      pushMat(spireTransforms, wx, baseY + totalH + spireH * 0.48, wz, Math.max(0.7, baseW * 0.08), spireH, Math.max(0.7, baseW * 0.08), rotY);

      // ~10% Subtle Department Vertical Optical Light Vein with clear facade offset + polygonOffset (-2/-4)
      if (deptHex) {
        const vx = wx + Math.sin(rotY) * (baseD * 0.535);
        const vz = wz + Math.cos(rotY) * (baseD * 0.535);
        pushDeptVein(vx, baseY + (h1 + h2) * 0.5, vz, 0.58, (h1 + h2) * 0.90, 0.58, rotY, deptHex);
      }
    };

    // 2. Populate Dense Central Core Skyscraper Cluster (~94 stepped towers rising toward Supertower!)
    const coreRings = [
      { count: 14, rad: 46,  minH: 132, maxH: 196, w: 12.5 },
      { count: 22, rad: 74,  minH: 92,  maxH: 152, w: 11.8 },
      { count: 28, rad: 102, minH: 62,  maxH: 116, w: 11.0 },
      { count: 30, rad: 126, minH: 40,  maxH: 82,  w: 10.2 }
    ];
    coreRings.forEach((ring, rIdx) => {
      for (let i = 0; i < ring.count; i++) {
        const ang = (i / ring.count) * Math.PI * 2 + rIdx * 0.21 + (rng() - 0.5) * 0.08;
        const frontAlign = Math.cos(ang - Math.PI * 0.5);
        const rad = ring.rad + (rng() - 0.5) * 6.5;
        const wx = Math.cos(ang) * rad;
        const wz = Math.sin(ang) * rad;
        let h = lerp(ring.minH, ring.maxH, rng());
        if (frontAlign > 0.92 && rIdx >= 1) h *= 0.68;
        const w = ring.w * (0.84 + rng() * 0.34);
        const d = ring.w * (0.84 + rng() * 0.34);
        const deptAccent = (i % 5 === 0) ? DEPARTMENTS[i % 7].hex : null;
        const coreBaseY = rad < 95.0 ? 9.47 : 6.43;
        addSteppedSkyscraper(wx, coreBaseY, wz, w, d, h, -ang + Math.PI * 0.5, deptAccent);
      }
    });

    // 3. Build the 7 Radial Atlantis Department Piers + 7 Landmarks + Water-Lagoon Ring Bridges!
    // Shortened end-faces (-0.30m per end: 133.4m vs 134.0m) + >= 0.38m vertical step separation!
    const causewayGeo = new THREE.BoxGeometry(30.0, 3.20, 134.0);
    const causewayGoldTrimGeo = new THREE.BoxGeometry(32.6, 0.68, 133.4);
    const causewayParapetGeo = new THREE.BoxGeometry(1.10, 0.42, 133.4);
    const landingPadOctGeo = new THREE.CylinderGeometry(34, 37, 4.2, 8);
    const landingPadRingGeo = new THREE.TorusGeometry(24, 0.62, 8, 8);
    landingPadRingGeo.rotateX(Math.PI / 2);

    this.pierAngles = [];
    this.departmentLandmarks = [];

    for (let k = 0; k < 7; k++) {
      const dept = DEPARTMENTS[k];
      const theta = (k / 7) * Math.PI * 2 + 0.36;
      this.pierAngles.push(theta);

      const pierGrp = new THREE.Group();
      const rotY = Math.atan2(Math.cos(theta), Math.sin(theta));
      pierGrp.rotation.y = rotY;
      this.cityGroup.add(pierGrp);

      // 3a. Inner Radial Causeway over Water (cwDeck top = 5.08m, cwTrim top = 4.70m, Parapets top = 5.50m)
      const cwDeck = new THREE.Mesh(causewayGeo, this.mats.obsidian);
      cwDeck.position.set(0, 3.48, 211.5);
      pierGrp.add(cwDeck);

      const cwTrim = new THREE.Mesh(causewayGoldTrimGeo, this.mats.goldBrushed);
      cwTrim.position.set(0, 4.36, 211.5);
      pierGrp.add(cwTrim);

      for (const pxSide of [-14.45, 14.45]) {
        const parapet = new THREE.Mesh(causewayParapetGeo, this.mats.goldBright);
        parapet.position.set(pxSide, 5.29, 211.5);
        pierGrp.add(parapet);
      }

      // Sea Pylons + Water Contact AO Shadows under Causeway
      for (let pz = 168; pz <= 264; pz += 32) {
        const wx = Math.sin(rotY) * pz;
        const wz = Math.cos(rotY) * pz;
        pushMat(goldFrameTransforms, wx, 1.85, wz, 26, 3.8, 5.5, rotY);
        pushAo(wx, 0.16, wz, 34, 13.5, rotY);
      }

      // Subtle ~10% Department Optical Veins cleanly stepped above Parapets (y = 5.88m)
      for (const side of [-12.5, 12.5]) {
        const lx = side * Math.cos(rotY) + 211.5 * Math.sin(rotY);
        const lz = -side * Math.sin(rotY) + 211.5 * Math.cos(rotY);
        pushDeptVein(lx, 5.88, lz, 0.82, 0.36, 131.0, rotY, dept.hex);
      }

      // 3b. Broad Chamfered Atlantis Outer Pier Wing (wingDeck top = 5.50m, wingGold seawall top = 5.02m -> 0.48m step!)
      const wingDeck = new THREE.Mesh(wingDeckGeo, this.mats.anthracite);
      wingDeck.position.set(0, 4.80, 0);
      pierGrp.add(wingDeck);

      const wingGold = new THREE.Mesh(wingGoldTrimGeo, this.mats.goldBright);
      wingGold.position.set(0, 4.70, 0);
      pierGrp.add(wingGold);

      // Raised Central Pier Citadel Podium (top = 8.20m) + Stepped Gold Cornice (top = 7.80m -> 0.40m step!)
      const pod = new THREE.Mesh(new THREE.BoxGeometry(58, 3.10, 128.0), this.mats.obsidian);
      pod.position.set(0, 6.65, 382);
      pierGrp.add(pod);

      const podGoldTrim = new THREE.Mesh(new THREE.BoxGeometry(59.8, 0.56, 127.4), this.mats.goldBright);
      podGoldTrim.position.set(0, 7.52, 382);
      pierGrp.add(podGoldTrim);

      // Soft Baked AO under Citadel Podium on Wing Deck & under Landmark Altar on Citadel Podium
      pushAo(Math.sin(rotY) * 382, 5.58, Math.cos(rotY) * 382, 74, 146, rotY);
      pushAo(Math.sin(rotY) * 362, 8.28, Math.cos(rotY) * 362, 29, 29, rotY);

      // 3b-landmark. Individual 3D Department Landmark crowning the Pier Citadel!
      const landmarkNode = this._buildDepartmentLandmark(dept, k);
      pierGrp.add(landmarkNode);

      // Twin Sleek Black-Gold Hydro-Cruisers berthed in the Atlantis Pier Slip
      for (const slipX of [-13, 13]) {
        const hull = new THREE.Mesh(new THREE.ConeGeometry(4.2, 34, 6), this.mats.obsidian);
        hull.rotation.x = Math.PI * 0.5;
        hull.scale.set(1.0, 1.0, 0.42);
        hull.position.set(slipX, 1.6, 478);
        pierGrp.add(hull);

        const trim = new THREE.Mesh(new THREE.BoxGeometry(2.2, 1.1, 23.4), this.mats.goldBright);
        trim.position.set(slipX, 2.75, 476);
        pierGrp.add(trim);
      }

      // 3c. Iconic Pier-Tip Octagonal Atlantis Starport / Landing Pad (pad top = 4.50m, linkBridge top = 4.95m -> 0.45m step!)
      const linkBridge = new THREE.Mesh(new THREE.BoxGeometry(18, 2.50, 26.4), this.mats.obsidian);
      linkBridge.position.set(0, 3.70, 506);
      pierGrp.add(linkBridge);

      const pad = new THREE.Mesh(landingPadOctGeo, this.mats.obsidian);
      pad.position.set(0, 2.40, 540);
      pierGrp.add(pad);

      const padGoldRing = new THREE.Mesh(landingPadRingGeo, this.mats.goldBright);
      padGoldRing.position.set(0, 4.92, 540);
      pierGrp.add(padGoldRing);

      const wxPad = Math.sin(rotY) * 540;
      const wzPad = Math.cos(rotY) * 540;
      pushAo(wxPad, 0.16, wzPad, 88, 88, rotY);
      pushDeptVein(wxPad, 4.96, wzPad, 15.6, 0.38, 15.6, rotY, dept.hex);

      // 3d-click. Invisible Department Pier Raycast Proxy (covers Pier + Citadel + 3D Rooftop Landmark!)
      const deptClickProxy = new THREE.Mesh(
        new THREE.BoxGeometry(72, 64, 290),
        new THREE.MeshBasicMaterial({ visible: false })
      );
      deptClickProxy.position.set(0, 32, 360);
      deptClickProxy.userData = {
        isDepartment: true,
        deptKey: dept.key,
        deptIndex: k,
        name: dept.name,
        icon: dept.icon,
        hex: dept.hex
      };
      pierGrp.add(deptClickProxy);
      this.departmentClickTargets.push(deptClickProxy);

      const beaconMat = new THREE.MeshBasicMaterial({
        color: dept.color,
        transparent: true,
        opacity: 0.28,
        side: THREE.DoubleSide,
        depthWrite: false,
        polygonOffset: true,
        polygonOffsetFactor: -2,
        polygonOffsetUnits: -4
      });
      const podiumBeaconGeo = new THREE.RingGeometry(24.2, 26.8, 36);
      podiumBeaconGeo.rotateX(-Math.PI * 0.5);
      const podiumBeacon = new THREE.Mesh(podiumBeaconGeo, beaconMat);
      podiumBeacon.position.set(0, 8.32, 362);
      pierGrp.add(podiumBeacon);

      const padBeaconGeo = new THREE.RingGeometry(17.2, 20.4, 8);
      padBeaconGeo.rotateX(-Math.PI * 0.5);
      const padBeacon = new THREE.Mesh(padBeaconGeo, beaconMat);
      padBeacon.position.set(0, 4.62, 540);
      pierGrp.add(padBeacon);

      this.departmentBeacons[dept.key] = {
        mat: beaconMat,
        podiumBeacon,
        padBeacon
      };

      // 3d. 22 Stepped Skyscrapers Framing the Open Department Landmark Citadel Plaza at (0, 362)!
      const pierTowerSpots = [
        // Twin Gateway Towers in front & Tall Citadel Keep behind the 3D Landmark at (0, 362)
        [-19, 330, 88, 11.0], [19, 330, 92, 11.0],
        [-21, 356, 82, 10.6], [21, 356, 86, 10.6],
        [0,   398, 108, 13.2], [-22, 392, 78, 10.5], [22, 392, 82, 10.5],
        [0,   426, 74, 11.0],
        // Left & Right Stepped Atlantis Wing Towers
        [-44, 342, 66, 10.2], [44, 342, 68, 10.2],
        [-52, 374, 58, 9.6],  [52, 374, 62, 9.6],
        [-48, 408, 52, 9.4],  [48, 408, 54, 9.4],
        [-38, 438, 44, 8.8],  [38, 438, 46, 8.8],
        [-58, 426, 36, 8.4],  [58, 426, 38, 8.4],
        [-32, 314, 54, 9.2],  [32, 314, 56, 9.2],
        [-26, 462, 34, 8.2],  [26, 462, 36, 8.2]
      ];

      pierTowerSpots.forEach(([lx, lz, hBase, wBase], idx) => {
        const wx = lx * Math.cos(rotY) + lz * Math.sin(rotY);
        const wz = -lx * Math.sin(rotY) + lz * Math.cos(rotY);
        const h = hBase * (0.90 + rng() * 0.22);
        const w = wBase * (0.92 + rng() * 0.18);
        const baseY = Math.abs(lx) < 26 && lz > 318 && lz < 444 ? 8.20 : 5.50;
        addSteppedSkyscraper(wx, baseY, wz, w, w * 0.94, h, rotY, idx % 2 === 0 ? dept.hex : null);
      });
    }

    // 4. Outer & Mid Ring Bridges Over Shimmering Water Lagoons (Shortened Stirnflaechen & >= 0.40m Step!)
    const ringRadii = [
      { r: 252, width: 14.5, y: 3.55 },
      { r: 412, width: 16.5, y: 3.95 }
    ];

    ringRadii.forEach((rb, rIdx) => {
      const deckTop = rb.y + 1.0;
      for (let k = 0; k < 7; k++) {
        const a1 = this.pierAngles[k];
        const a2 = this.pierAngles[(k + 1) % 7];
        const p1 = new THREE.Vector2(Math.cos(a1) * rb.r, Math.sin(a1) * rb.r);
        const p2 = new THREE.Vector2(Math.cos(a2) * rb.r, Math.sin(a2) * rb.r);
        const dx = p2.x - p1.x;
        const dz = p2.y - p1.y;
        const spanLen = Math.hypot(dx, dz);
        const deckLen = Math.max(12, spanLen - 2.4);
        const railLen = Math.max(10, deckLen - 0.60); // -0.30m shortened at each end!
        const midX = (p1.x + p2.x) * 0.5;
        const midZ = (p1.y + p2.y) * 0.5;
        const bridgeRotY = Math.atan2(dx, dz);

        // Obsidian Bridge Deck (top = deckTop)
        const deck = new THREE.Mesh(new THREE.BoxGeometry(rb.width, 2.0, deckLen), this.mats.obsidian);
        deck.position.set(midX, rb.y, midZ);
        deck.rotation.y = bridgeRotY;
        this.cityGroup.add(deck);

        // Stepped Lower 24K-Gold Arch-Cornice (top = deckTop - 0.40m)
        const goldRail = new THREE.Mesh(new THREE.BoxGeometry(rb.width + 2.2, 0.56, railLen), this.mats.goldBrushed);
        goldRail.position.set(midX, deckTop - 0.68, midZ);
        goldRail.rotation.y = bridgeRotY;
        this.cityGroup.add(goldRail);

        // Twin Raised 24K-Gold Side Balustrades (top = deckTop + 0.42m)
        for (const sideSign of [-1, 1]) {
          const sideOff = sideSign * (rb.width * 0.5 - 0.48);
          const rx = midX + sideOff * Math.cos(bridgeRotY);
          const rz = midZ - sideOff * Math.sin(bridgeRotY);
          const sideBar = new THREE.Mesh(new THREE.BoxGeometry(0.95, 0.42, railLen), this.mats.goldBright);
          sideBar.position.set(rx, deckTop + 0.21, rz);
          sideBar.rotation.y = bridgeRotY;
          this.cityGroup.add(sideBar);
        }

        // Bridge Support Pylons + Water Contact AO Shadows in the Lagoon
        for (let s = 0.25; s <= 0.76; s += 0.25) {
          const px = lerp(p1.x, p2.x, s);
          const pz = lerp(p1.y, p2.y, s);
          pushMat(goldFrameTransforms, px, 1.85, pz, rb.width * 0.85, 3.8, 4.2, bridgeRotY);
          pushAo(px, 0.16, pz, rb.width * 1.4, 11.5, bridgeRotY);
        }

        // Subtle Department Light Vein on Ring Bridge (+0.38m above deckTop + polygonOffset -2/-4)
        pushDeptVein(midX, deckTop + 0.38, midZ, 0.75, 0.36, railLen * 0.90, bridgeRotY, DEPARTMENTS[(k + rIdx) % 7].hex);
      }
    });

    // 5. Commit All Batched InstancedMeshes (Ultra-Low Draw Calls = Locked 60 FPS!)
    const unitBox = new THREE.BoxGeometry(1, 1, 1);
    const unitSpire = new THREE.ConeGeometry(0.5, 1, 6);
    const unitAoPlane = new THREE.PlaneGeometry(1, 1);
    unitAoPlane.rotateX(-Math.PI * 0.5);

    const buildInst = (geo, mat, matrices) => {
      const inst = new THREE.InstancedMesh(geo, mat, matrices.length);
      for (let i = 0; i < matrices.length; i++) {
        inst.setMatrixAt(i, matrices[i]);
      }
      inst.instanceMatrix.needsUpdate = true;
      inst.frustumCulled = false;
      this.cityGroup.add(inst);
      return inst;
    };

    buildInst(unitBox, this.mats.skyscraperFacade, towerTransforms);
    buildInst(unitBox, this.mats.goldBrushed, goldFrameTransforms);
    buildInst(unitSpire, this.mats.goldBright, spireTransforms);

    const aoInst = buildInst(unitAoPlane, this.mats.bakedAo, aoTransforms);
    aoInst.renderOrder = 1;

    const veinInst = new THREE.InstancedMesh(unitBox, this.mats.deptVeins, deptVeinItems.length);
    for (let i = 0; i < deptVeinItems.length; i++) {
      veinInst.setMatrixAt(i, deptVeinItems[i].matrix);
      veinInst.setColorAt(i, deptVeinItems[i].color);
    }
    veinInst.instanceMatrix.needsUpdate = true;
    if (veinInst.instanceColor) veinInst.instanceColor.needsUpdate = true;
    veinInst.frustumCulled = false;
    this.cityGroup.add(veinInst);

    // 6. Living Golden Sky-Gliders Along Ring Bridges & Radial Piers (84 Gliders in 1 Draw Call!)
    const gliderCount = 84;
    const gliderGeo = new THREE.ConeGeometry(1.15, 5.4, 4);
    gliderGeo.rotateX(Math.PI * 0.5);
    gliderGeo.scale(1.6, 0.42, 1.0);
    this.gliderInst = new THREE.InstancedMesh(gliderGeo, this.mats.beaconGold, gliderCount);
    this.gliderInst.frustumCulled = false;
    this.cityGroup.add(this.gliderInst);

    this.gliderData = [];
    for (let i = 0; i < gliderCount; i++) {
      const mode = i < 42 ? 'pier' : (i < 70 ? 'ring' : 'tower_orbit');
      this.gliderData.push({
        mode,
        pierIdx: i % 7,
        ringR: (i % 2 === 0) ? 252 : 412,
        phase: rng(),
        speed: (0.032 + rng() * 0.035) * (i % 2 === 0 ? 1 : -1),
        alt: mode === 'tower_orbit' ? (115 + rng() * 115) : (10.5 + (i % 3) * 3.5),
        laneOffset: (i % 2 === 0 ? 5.2 : -5.2)
      });
    }
  }

  // ==========================================================================
  // PHASE 2C: COLOSSAL GOLDEN SUPERTOWER ("MEIN SEELENFUNKE" · BURJ KHALIFA x STARK TOWER)
  // ==========================================================================
  _buildSupertower() {
    this.supertowerGroup = new THREE.Group();
    this.cityGroup.add(this.supertowerGroup);

    // 1. Grand Octagonal Base Podium (Y = 9.47 .. 26m, PBR Obsidian + 24K Gold PMREM Reflections!)
    const basePodium = new THREE.Mesh(new THREE.CylinderGeometry(30, 36, 17, 8), this.mats.obsidianPbr);
    basePodium.position.y = 17.5;
    this.supertowerGroup.add(basePodium);

    const baseGoldCollar = new THREE.Mesh(new THREE.CylinderGeometry(31.2, 31.2, 2.2, 8), this.mats.goldPbrBright);
    baseGoldCollar.position.y = 26.8;
    this.supertowerGroup.add(baseGoldCollar);

    // 2. Spiraling Burj-Khalifa Setback Tiers (Y = 26 .. 218m) in 24K Gold & Interior-Lit Curtain-Wall
    const coreShaft = new THREE.Mesh(new THREE.CylinderGeometry(13.2, 20.5, 194, 8), this.mats.supertowerFacade);
    coreShaft.position.y = 123;
    this.supertowerGroup.add(coreShaft);

    // 8 Soaring 24K-Gold Vertical Exoskeleton Ribs up the Entire Supertower
    for (let i = 0; i < 8; i++) {
      const ang = (i / 8) * Math.PI * 2 + Math.PI / 8;
      const rib = new THREE.Mesh(new THREE.BoxGeometry(1.75, 232, 2.8), this.mats.goldPbrBright);
      const rMid = 16.95;
      rib.position.set(Math.cos(ang) * rMid, 138, Math.sin(ang) * rMid);
      rib.rotation.y = -ang + Math.PI * 0.5;
      this.supertowerGroup.add(rib);
    }

    // 6 Spiraling Y-Buttress Wing Towers Stepping Back Like the Burj Khalifa!
    const wingHeights = [66, 98, 128, 158, 186, 212];
    for (let w = 0; w < 6; w++) {
      const ang = (w / 6) * Math.PI * 2 + 0.25;
      const h = wingHeights[w];
      const dist = 20.5 - w * 1.15;
      const rad = 8.8 - w * 0.55;

      const wingShaft = new THREE.Mesh(new THREE.CylinderGeometry(rad * 0.84, rad, h - 24, 6), this.mats.supertowerFacade);
      wingShaft.position.set(Math.cos(ang) * dist, 24 + (h - 24) * 0.5, Math.sin(ang) * dist);
      this.supertowerGroup.add(wingShaft);

      // Gleaming 24K-Gold Setback Terrace Crown + Mini Spire on Each Buttress
      const cap = new THREE.Mesh(new THREE.CylinderGeometry(rad * 0.94, rad * 0.98, 2.4, 6), this.mats.goldPbrBright);
      cap.position.set(Math.cos(ang) * dist, h + 1.2, Math.sin(ang) * dist);
      this.supertowerGroup.add(cap);

      const fin = new THREE.Mesh(new THREE.ConeGeometry(1.1, 16, 6), this.mats.goldPbrBright);
      fin.position.set(Math.cos(ang) * dist, h + 9.7, Math.sin(ang) * dist);
      this.supertowerGroup.add(fin);
    }

    // Horizontal Golden Ring Bands along Main Shaft
    [54, 88, 122, 156, 190, 218].forEach((ry, idx) => {
      const rr = lerp(20.8, 14.6, idx / 5);
      const band = new THREE.Mesh(new THREE.CylinderGeometry(rr, rr, 1.6, 8), this.mats.goldPbrBright);
      band.position.y = ry;
      this.supertowerGroup.add(band);
    });

    // 3. Avengers Stark-Tower Cantilevered Upper Sky-Citadel & "MEIN SEELENFUNKE" Monumental Plaque (Y = 218 .. 258m)
    const starkLower = new THREE.Mesh(new THREE.CylinderGeometry(18.6, 14.2, 18, 8), this.mats.obsidianPbr);
    starkLower.position.y = 227;
    this.supertowerGroup.add(starkLower);

    const starkDeck = new THREE.Mesh(new THREE.BoxGeometry(44, 10.2, 32), this.mats.obsidianPbr);
    starkDeck.position.set(0, 238.5, 2.2);
    this.supertowerGroup.add(starkDeck);

    const starkGoldFrameTop = new THREE.Mesh(new THREE.BoxGeometry(45.6, 1.5, 33.6), this.mats.goldPbrBright);
    starkGoldFrameTop.position.set(0, 244.45, 2.2);
    this.supertowerGroup.add(starkGoldFrameTop);

    const starkGoldFrameBot = new THREE.Mesh(new THREE.BoxGeometry(45.6, 1.5, 33.6), this.mats.goldPbrBright);
    starkGoldFrameBot.position.set(0, 232.55, 2.2);
    this.supertowerGroup.add(starkGoldFrameBot);

    for (const sx of [-18.5, 18.5]) {
      const strut = new THREE.Mesh(new THREE.BoxGeometry(2.0, 26, 2.4), this.mats.goldPbrBright);
      strut.position.set(sx, 224, 11.5);
      strut.rotation.z = sx > 0 ? 0.24 : -0.24;
      strut.rotation.x = -0.22;
      this.supertowerGroup.add(strut);
    }

    // High-Resolution Monumental "MEIN SEELENFUNKE" Gold Facade Signage (Front +Z & Back -Z with polygonOffset!)
    const signTex = makeMeinSeelenfunkePlaqueTexture();
    const signMat = new THREE.MeshBasicMaterial({
      map: signTex,
      polygonOffset: true,
      polygonOffsetFactor: -2,
      polygonOffsetUnits: -4
    });
    const signGeo = new THREE.PlaneGeometry(42.5, 8.0);

    const frontSign = new THREE.Mesh(signGeo, signMat);
    frontSign.position.set(0, 238.5, 18.55);
    this.supertowerGroup.add(frontSign);

    const backSign = new THREE.Mesh(signGeo, signMat);
    backSign.position.set(0, 238.5, -14.15);
    backSign.rotation.y = Math.PI;
    this.supertowerGroup.add(backSign);

    const neckPedestal = new THREE.Mesh(new THREE.CylinderGeometry(14.2, 16.8, 12.6, 16), this.mats.obsidianPbr);
    neckPedestal.position.y = 251.0;
    this.supertowerGroup.add(neckPedestal);

    const penthouseRimBase = new THREE.Mesh(new THREE.CylinderGeometry(14.6, 14.2, 1.2, 32), this.mats.goldPbrBright);
    penthouseRimBase.position.y = THRONE_FLOOR_Y - 0.55;
    this.supertowerGroup.add(penthouseRimBase);

    // 4. Clickable Crown Spire & Golden Filigree Cupola Above Penthouse (Y = 266.2 .. 328.0m)
    const cupolaRing = new THREE.Mesh(new THREE.CylinderGeometry(11.8, 13.8, 1.4, 32), this.mats.goldPbrBright);
    cupolaRing.position.y = THRONE_FLOOR_Y + 8.4;
    this.supertowerGroup.add(cupolaRing);
    this.throneClickTargets.push(cupolaRing);

    for (let i = 0; i < 8; i++) {
      const ang = (i / 8) * Math.PI;
      const arch = new THREE.Mesh(new THREE.TorusGeometry(11.6, 0.36, 8, 32, Math.PI), this.mats.goldPbrBright);
      arch.position.y = THRONE_FLOOR_Y + 8.5;
      arch.rotation.y = ang;
      this.supertowerGroup.add(arch);
    }

    const spireBase = new THREE.Mesh(new THREE.CylinderGeometry(3.2, 6.4, 14, 16), this.mats.goldPbrBright);
    spireBase.position.y = THRONE_FLOOR_Y + 24.5;
    this.supertowerGroup.add(spireBase);
    this.throneClickTargets.push(spireBase);

    const spireNeedle = new THREE.Mesh(new THREE.ConeGeometry(2.8, 42, 16), this.mats.goldPbrBright);
    spireNeedle.position.y = THRONE_FLOOR_Y + 51.0;
    this.supertowerGroup.add(spireNeedle);
    this.throneClickTargets.push(spireNeedle);

    const apexStar = new THREE.Mesh(new THREE.OctahedronGeometry(2.2, 0), this.mats.beaconGold);
    apexStar.position.y = TOWER_APEX_Y;
    this.supertowerGroup.add(apexStar);
    this.throneClickTargets.push(apexStar);

    const clickProxy = new THREE.Mesh(
      new THREE.CylinderGeometry(16, 16, 72, 12),
      new THREE.MeshBasicMaterial({ visible: false })
    );
    clickProxy.position.y = THRONE_FLOOR_Y + 32;
    this.supertowerGroup.add(clickProxy);
    this.throneClickTargets.push(clickProxy);
  }

  // ==========================================================================
  // PHASE 3: ALINA'S & FALCON'S PENTHOUSE THRONE ROOM AT THE SUPERTOWER CROWN
  // ==========================================================================
  _buildThroneRoomAndFalcon() {
    this.throneRoomGroup = new THREE.Group();
    this.throneRoomGroup.position.set(0, THRONE_FLOOR_Y, 0);
    this.supertowerGroup.add(this.throneRoomGroup);

    // 1. Polished Nero-Marquina Marble & 24K-Gold Compass Floor (Radius 13.6m)
    const floorTex = makePenthouseMarbleTexture();
    const floorMat = new THREE.MeshBasicMaterial({ map: floorTex });
    const floorMesh = new THREE.Mesh(new THREE.CircleGeometry(13.6, 48), floorMat);
    floorMesh.rotation.x = -Math.PI * 0.5;
    floorMesh.position.y = 0.02;
    this.throneRoomGroup.add(floorMesh);

    // 2. 360° Floor-to-Ceiling Panoramic Glass Rotunda + 16 Slim 24K-Gold Structural Columns
    const glassWall = new THREE.Mesh(
      new THREE.CylinderGeometry(13.35, 13.35, 8.2, 48, 1, true),
      this.mats.panoramicGlass
    );
    glassWall.position.y = 4.1;
    this.throneRoomGroup.add(glassWall);

    // Ultra-low golden panoramic curb rail so the view out over all 7 piers & ocean is completely unobstructed!
    const lowGoldCurb = new THREE.Mesh(new THREE.TorusGeometry(13.25, 0.14, 8, 64), this.mats.goldBright);
    lowGoldCurb.rotation.x = Math.PI * 0.5;
    lowGoldCurb.position.y = 0.16;
    this.throneRoomGroup.add(lowGoldCurb);

    const glassHandrail = new THREE.Mesh(new THREE.TorusGeometry(12.95, 0.055, 8, 64), this.mats.goldBright);
    glassHandrail.rotation.x = Math.PI * 0.5;
    glassHandrail.position.y = 0.92;
    this.throneRoomGroup.add(glassHandrail);

    for (let i = 0; i < 16; i++) {
      // Keep dead-center behind thrones (-Z = -PI/2) free of a column so ocean horizon shines cleanly behind Alina's Throne!
      const ang = (i / 16) * Math.PI * 2 + (Math.PI / 16);
      const col = new THREE.Mesh(new THREE.CylinderGeometry(0.18, 0.22, 8.3, 12), this.mats.goldBright);
      col.position.set(Math.cos(ang) * 13.3, 4.15, Math.sin(ang) * 13.3);
      this.throneRoomGroup.add(col);
    }

    // Suspended 24K-Gold Halo Chandelier Ring overhead
    const chandelierRing = new THREE.Mesh(new THREE.TorusGeometry(5.2, 0.11, 10, 48), this.mats.goldBright);
    chandelierRing.rotation.x = Math.PI * 0.5;
    chandelierRing.position.y = 6.85;
    this.throneRoomGroup.add(chandelierRing);

    const chandelierGlow = new THREE.Mesh(new THREE.TorusGeometry(5.2, 0.05, 8, 48), this.mats.beaconGold);
    chandelierGlow.rotation.x = Math.PI * 0.5;
    chandelierGlow.position.y = 6.74;
    this.throneRoomGroup.add(chandelierGlow);

    // 3. Royal Crimson Velvet Carpet Runner ("Samt-Karmesinrot mit feiner Goldbordüre")
    const carpetTex = makeRoyalCarpetTexture();
    const carpetMat = new THREE.MeshBasicMaterial({ map: carpetTex });
    const mainRunner = new THREE.Mesh(new THREE.BoxGeometry(2.65, 0.035, 13.2), carpetMat);
    mainRunner.position.set(0, 0.035, 4.3);
    this.throneRoomGroup.add(mainRunner);

    // 4. Elevated 3-Step Black-Gold Sovereign Dais / Pedestal (z = -3.25)
    const daisSteps = [
      { w: 7.4, d: 4.8, h: 0.18, y: 0.09, z: -3.10 },
      { w: 6.7, d: 4.2, h: 0.18, y: 0.27, z: -3.28 },
      { w: 6.0, d: 3.6, h: 0.18, y: 0.45, z: -3.45 }
    ];
    daisSteps.forEach((st) => {
      const stepObs = new THREE.Mesh(new RoundedBoxGeometry(st.w, st.h, st.d, 2, 0.04), this.mats.obsidian);
      stepObs.position.set(0.35, st.y, st.z);
      this.throneRoomGroup.add(stepObs);

      const stepGold = new THREE.Mesh(new THREE.BoxGeometry(st.w + 0.06, 0.045, st.d + 0.06), this.mats.goldBright);
      stepGold.position.set(0.35, st.y + st.h * 0.5, st.z);
      this.throneRoomGroup.add(stepGold);

      // Crimson carpet continuing right up the center steps to Alina's High Throne!
      const stepCarpet = new THREE.Mesh(new THREE.BoxGeometry(2.35, 0.03, st.d + 0.04), carpetMat);
      stepCarpet.position.set(0, st.y + st.h * 0.5 + 0.025, st.z + 0.02);
      this.throneRoomGroup.add(stepCarpet);
    });

    const daisTopY = 0.54;

    // Open Filigree 24K-Gold Halo Arch Framing the Panoramic Ocean Horizon Behind the Thrones
    const haloArch = new THREE.Mesh(new THREE.TorusGeometry(3.55, 0.09, 10, 48, Math.PI), this.mats.goldBright);
    haloArch.position.set(0.35, daisTopY + 0.2, -4.75);
    this.throneRoomGroup.add(haloArch);

    // ========================================================================
    // 5. ALINA'S MAJESTIC CENTRAL HIGH THRONE (x = 0.0, z = -3.55)
    // ========================================================================
    const alinaThrone = new THREE.Group();
    alinaThrone.position.set(0.0, daisTopY, -3.55);
    this.throneRoomGroup.add(alinaThrone);

    // Sculpted Throne Base & Crimson Velvet Seat Cushion
    const atBase = new THREE.Mesh(new RoundedBoxGeometry(1.58, 0.50, 1.24, 3, 0.06), this.mats.obsidian);
    atBase.position.set(0, 0.25, 0.06);
    alinaThrone.add(atBase);

    const atBaseGold = new THREE.Mesh(new THREE.BoxGeometry(1.64, 0.08, 1.30), this.mats.goldBright);
    atBaseGold.position.set(0, 0.05, 0.06);
    alinaThrone.add(atBaseGold);

    const atSeat = new THREE.Mesh(new RoundedBoxGeometry(1.34, 0.18, 1.08, 3, 0.07), this.mats.velvet);
    atSeat.position.set(0, 0.54, 0.10);
    alinaThrone.add(atSeat);

    // Sculpted Obsidian & 24K-Gold Winged Armrests
    for (const sx of [-0.72, 0.72]) {
      const armSupport = new THREE.Mesh(new RoundedBoxGeometry(0.20, 0.54, 1.12, 3, 0.05), this.mats.obsidian);
      armSupport.position.set(sx, 0.68, 0.08);
      alinaThrone.add(armSupport);

      const armCap = new THREE.Mesh(new RoundedBoxGeometry(0.24, 0.10, 1.18, 3, 0.04), this.mats.goldBright);
      armCap.position.set(sx, 0.96, 0.10);
      alinaThrone.add(armCap);

      const finial = new THREE.Mesh(new THREE.SphereGeometry(0.11, 14, 12), this.mats.goldBright);
      finial.position.set(sx, 1.04, 0.66);
      alinaThrone.add(finial);
    }

    // Tall Royal High Backrest (Height 2.92m) + Velvet Lower Lumbar Cushion
    const atBackCore = new THREE.Mesh(new RoundedBoxGeometry(1.62, 2.86, 0.22, 3, 0.06), this.mats.obsidian);
    atBackCore.position.set(0, 1.56, -0.44);
    alinaThrone.add(atBackCore);

    // Sculpted 24K-Gold Side Pillars & Crown Arch on Alina's Throne
    for (const sx of [-0.84, 0.84]) {
      const pillar = new THREE.Mesh(new CylinderOrBoxPillar(0.11, 2.98), this.mats.goldBright);
      pillar.position.set(sx, 1.58, -0.42);
      alinaThrone.add(pillar);

      const spireFin = new THREE.Mesh(new THREE.ConeGeometry(0.12, 0.44, 8), this.mats.goldBright);
      spireFin.position.set(sx, 3.26, -0.42);
      alinaThrone.add(spireFin);
    }

    const atTopCrestBar = new THREE.Mesh(new RoundedBoxGeometry(1.86, 0.18, 0.28, 2, 0.05), this.mats.goldBright);
    atTopCrestBar.position.set(0, 3.02, -0.42);
    alinaThrone.add(atTopCrestBar);

    // 3D Sculpted Royal Crown & Seelenfunke Star atop Alina's Throne!
    const crownRing = new THREE.Mesh(new THREE.CylinderGeometry(0.32, 0.26, 0.22, 8), this.mats.goldBright);
    crownRing.position.set(0, 3.22, -0.42);
    alinaThrone.add(crownRing);

    const crownStar = new THREE.Mesh(new THREE.OctahedronGeometry(0.18, 0), this.mats.beaconGold);
    crownStar.position.set(0, 3.48, -0.42);
    alinaThrone.add(crownStar);

    const atLumbar = new THREE.Mesh(new RoundedBoxGeometry(1.26, 0.92, 0.12, 3, 0.05), this.mats.velvet);
    atLumbar.position.set(0, 1.08, -0.31);
    alinaThrone.add(atLumbar);

    // RAZOR-SHARP GOLD ENGRAVING DIRECTLY ON ALINA'S HIGH BACKREST:
    // "ALINA" / "Herrscherin über das Seelenfunken-Universum"
    const alinaPlaqueTex = makeThroneEngravingTexture(
      'ALINA',
      'Herrscherin über das Seelenfunken-Universum',
      true
    );
    const alinaPlaqueFrame = new THREE.Mesh(new RoundedBoxGeometry(1.50, 0.82, 0.06, 2, 0.02), this.mats.goldBright);
    alinaPlaqueFrame.position.set(0, 2.28, -0.31);
    alinaThrone.add(alinaPlaqueFrame);

    const alinaPlaqueFace = new THREE.Mesh(
      new THREE.PlaneGeometry(1.44, 0.76),
      new THREE.MeshBasicMaterial({ map: alinaPlaqueTex })
    );
    alinaPlaqueFace.position.set(0, 2.28, -0.275);
    alinaThrone.add(alinaPlaqueFace);

    // ========================================================================
    // 6. FALCON'S RIGHT-HAND THRONE ("FALCON · Rechte Hand & Mitgründer")
    //    Positioned directly to the right next to Alina's High Throne!
    // ========================================================================
    const falconThrone = new THREE.Group();
    falconThrone.position.set(1.88, daisTopY, -3.38);
    falconThrone.rotation.y = -0.16;
    this.throneRoomGroup.add(falconThrone);

    const ftBase = new THREE.Mesh(new RoundedBoxGeometry(1.18, 0.44, 1.02, 3, 0.05), this.mats.obsidian);
    ftBase.position.set(0, 0.22, 0.05);
    falconThrone.add(ftBase);

    const ftSeat = new THREE.Mesh(new RoundedBoxGeometry(0.98, 0.15, 0.90, 3, 0.05), this.mats.velvet);
    ftSeat.position.set(0, 0.48, 0.08);
    falconThrone.add(ftSeat);

    for (const sx of [-0.54, 0.54]) {
      const arm = new THREE.Mesh(new RoundedBoxGeometry(0.16, 0.46, 0.92, 2, 0.04), this.mats.obsidian);
      arm.position.set(sx, 0.60, 0.06);
      falconThrone.add(arm);

      const armGold = new THREE.Mesh(new RoundedBoxGeometry(0.19, 0.08, 0.96, 2, 0.03), this.mats.goldBright);
      armGold.position.set(sx, 0.84, 0.08);
      falconThrone.add(armGold);

      const pil = new THREE.Mesh(new CylinderOrBoxPillar(0.08, 2.24), this.mats.goldBright);
      pil.position.set(sx * 1.12, 1.24, -0.36);
      falconThrone.add(pil);
    }

    const ftBack = new THREE.Mesh(new RoundedBoxGeometry(1.22, 2.18, 0.18, 3, 0.05), this.mats.obsidian);
    ftBack.position.set(0, 1.24, -0.36);
    falconThrone.add(ftBack);

    const ftTopBar = new THREE.Mesh(new RoundedBoxGeometry(1.34, 0.14, 0.22, 2, 0.04), this.mats.goldBright);
    ftTopBar.position.set(0, 2.36, -0.36);
    falconThrone.add(ftTopBar);

    const ftLumbar = new THREE.Mesh(new RoundedBoxGeometry(0.94, 0.74, 0.10, 2, 0.04), this.mats.velvet);
    ftLumbar.position.set(0, 0.92, -0.25);
    falconThrone.add(ftLumbar);

    const falconPlaqueTex = makeThroneEngravingTexture(
      'FALCONE',
      'FALCONE · Rechte Hand & Mitgründer',
      false
    );
    const ftPlaqueFrame = new THREE.Mesh(new RoundedBoxGeometry(1.12, 0.60, 0.05, 2, 0.02), this.mats.goldBright);
    ftPlaqueFrame.position.set(0, 1.84, -0.25);
    falconThrone.add(ftPlaqueFrame);

    const ftPlaqueFace = new THREE.Mesh(
      new THREE.PlaneGeometry(1.06, 0.54),
      new THREE.MeshBasicMaterial({ map: falconPlaqueTex })
    );
    ftPlaqueFace.position.set(0, 1.84, -0.22);
    falconThrone.add(ftPlaqueFace);

    // ========================================================================
    // 7. DETAILED 3D FIGURE OF FALCON (STANDING VIGILANTLY BY HIS THRONE)
    //    Tailored Black-Gold Suit-Armor ("FALCON · Mitgründer"), Groomed
    //    Schnauzer, 24K-Gold Monocle with Chain & Gentleman Hairstyle!
    // ========================================================================
    this.falconGroup = new THREE.Group();
    // Positioned cleanly beside his Right-Hand Throne on the dais so BOTH his full figure AND throne plaque shine unobstructed!
    this.falconGroup.position.set(2.68, daisTopY, -2.86);
    this.falconGroup.rotation.y = -0.34;
    this.throneRoomGroup.add(this.falconGroup);

    // Armored Dress Boots & Tailored Obsidian Trousers with 24K-Gold Side Stripes
    for (const lx of [-0.13, 0.13]) {
      const boot = new THREE.Mesh(new RoundedBoxGeometry(0.14, 0.12, 0.28, 2, 0.04), this.mats.obsidian);
      boot.position.set(lx, 0.06, 0.05);
      this.falconGroup.add(boot);

      const bootGoldToe = new THREE.Mesh(new RoundedBoxGeometry(0.145, 0.04, 0.10, 2, 0.02), this.mats.goldBright);
      bootGoldToe.position.set(lx, 0.03, 0.15);
      this.falconGroup.add(bootGoldToe);

      const leg = new THREE.Mesh(new THREE.CylinderGeometry(0.088, 0.072, 0.84, 14), this.mats.obsidian);
      leg.position.set(lx, 0.52, 0);
      this.falconGroup.add(leg);

      const goldStripe = new THREE.Mesh(new THREE.BoxGeometry(0.018, 0.80, 0.025), this.mats.goldBright);
      goldStripe.position.set(lx + (lx > 0 ? 0.084 : -0.084), 0.52, 0);
      this.falconGroup.add(goldStripe);
    }

    // Upper Torso Group (Animated with Subtle Sovereign Breathing!)
    this.falconChestGroup = new THREE.Group();
    this.falconChestGroup.position.set(0, 0.94, 0);
    this.falconGroup.add(this.falconChestGroup);

    // Tailored Black-Obsidian Armored Jacket Torso
    const torso = new THREE.Mesh(new RoundedBoxGeometry(0.46, 0.58, 0.26, 3, 0.06), this.mats.obsidian);
    torso.position.set(0, 0.28, 0);
    this.falconChestGroup.add(torso);

    // 24K-Gold Belt & Buckle
    const belt = new THREE.Mesh(new RoundedBoxGeometry(0.47, 0.055, 0.27, 2, 0.02), this.mats.goldBright);
    belt.position.set(0, 0.04, 0);
    this.falconChestGroup.add(belt);

    // Crisp White Gentleman Wing-Collar Shirt V-Neck & Golden Cravat/Tie
    const shirtV = new THREE.Mesh(new THREE.ConeGeometry(0.11, 0.24, 4), this.mats.shirt);
    shirtV.rotation.z = Math.PI;
    shirtV.scale.set(1.0, 1.0, 0.35);
    shirtV.position.set(0, 0.49, 0.115);
    this.falconChestGroup.add(shirtV);

    const goldTie = new THREE.Mesh(new THREE.BoxGeometry(0.045, 0.20, 0.025), this.mats.goldBright);
    goldTie.position.set(0, 0.46, 0.136);
    this.falconChestGroup.add(goldTie);

    // Sculpted 24K-Gold Suit-Armor Pauldrons & Lapels + Inscribed Chest Breastplate ("FALCON · Mitgründer")
    for (const sx of [-0.27, 0.27]) {
      const pauldron = new THREE.Mesh(new THREE.SphereGeometry(0.115, 16, 12), this.mats.goldBright);
      pauldron.scale.set(1.15, 0.72, 1.05);
      pauldron.position.set(sx, 0.53, 0);
      this.falconChestGroup.add(pauldron);

      const lapel = new THREE.Mesh(new THREE.BoxGeometry(0.055, 0.28, 0.025), this.mats.goldBright);
      lapel.position.set(sx * 0.42, 0.44, 0.132);
      lapel.rotation.z = sx > 0 ? 0.26 : -0.26;
      this.falconChestGroup.add(lapel);
    }

    const armorChestTex = makeFalconArmorChestTexture();
    const chestPlate = new THREE.Mesh(
      new THREE.PlaneGeometry(0.32, 0.16),
      new THREE.MeshBasicMaterial({ map: armorChestTex })
    );
    chestPlate.position.set(0, 0.28, 0.136);
    this.falconChestGroup.add(chestPlate);

    // Arms: Left Arm Poised Behind Back / At Side, Right Hand Resting on a 24K-Gold Gentleman Cane!
    const leftArm = new THREE.Mesh(new THREE.CylinderGeometry(0.064, 0.054, 0.54, 12), this.mats.obsidian);
    leftArm.position.set(-0.29, 0.26, -0.02);
    leftArm.rotation.z = 0.08;
    this.falconChestGroup.add(leftArm);

    const leftHand = new THREE.Mesh(new THREE.SphereGeometry(0.052, 12, 10), this.mats.skin);
    leftHand.position.set(-0.31, -0.02, 0.0);
    this.falconChestGroup.add(leftHand);

    const rightUpperArm = new THREE.Mesh(new THREE.CylinderGeometry(0.064, 0.056, 0.30, 12), this.mats.obsidian);
    rightUpperArm.position.set(0.29, 0.38, 0.04);
    rightUpperArm.rotation.x = -0.32;
    rightUpperArm.rotation.z = -0.10;
    this.falconChestGroup.add(rightUpperArm);

    const rightForeArm = new THREE.Mesh(new THREE.CylinderGeometry(0.056, 0.048, 0.28, 12), this.mats.obsidian);
    rightForeArm.position.set(0.30, 0.16, 0.18);
    rightForeArm.rotation.x = -0.68;
    this.falconChestGroup.add(rightForeArm);

    const rightCuff = new THREE.Mesh(new THREE.CylinderGeometry(0.053, 0.053, 0.04, 12), this.mats.goldBright);
    rightCuff.position.set(0.30, 0.07, 0.26);
    rightCuff.rotation.x = -0.68;
    this.falconChestGroup.add(rightCuff);

    const rightHand = new THREE.Mesh(new THREE.SphereGeometry(0.054, 12, 10), this.mats.skin);
    rightHand.position.set(0.30, 0.03, 0.30);
    this.falconChestGroup.add(rightHand);

    // 24K-Gold Topped Obsidian Gentleman's Cane planted on the Dais
    const caneShaft = new THREE.Mesh(new THREE.CylinderGeometry(0.016, 0.012, 0.96, 10), this.mats.obsidian);
    caneShaft.position.set(0.30, 0.48, 0.30);
    this.falconGroup.add(caneShaft);

    const canePommel = new THREE.Mesh(new THREE.SphereGeometry(0.042, 14, 12), this.mats.goldBright);
    canePommel.position.set(0.30, 0.97, 0.30);
    this.falconGroup.add(canePommel);

    // Falcon's Head, Dapper Gentleman Hairstyle, Groomed Schnauzer & 24K-Gold Monocle with Chain!
    this.falconHeadGroup = new THREE.Group();
    this.falconHeadGroup.position.set(0, 0.68, 0.01);
    this.falconChestGroup.add(this.falconHeadGroup);

    const neck = new THREE.Mesh(new THREE.CylinderGeometry(0.068, 0.078, 0.10, 12), this.mats.skin);
    neck.position.set(0, -0.04, 0);
    this.falconHeadGroup.add(neck);

    const head = new THREE.Mesh(new THREE.SphereGeometry(0.135, 20, 16), this.mats.skin);
    head.scale.set(0.92, 1.08, 0.96);
    head.position.set(0, 0.08, 0);
    this.falconHeadGroup.add(head);

    // Swept-Back Gentleman Hairstyle (Side-parted sculpted pompadour & neat temples)
    const hairTop = new THREE.Mesh(new THREE.SphereGeometry(0.140, 18, 14), this.mats.hair);
    hairTop.scale.set(0.95, 0.72, 1.02);
    hairTop.position.set(0, 0.145, -0.015);
    this.falconHeadGroup.add(hairTop);

    const hairWave = new THREE.Mesh(new RoundedBoxGeometry(0.21, 0.065, 0.16, 3, 0.03), this.mats.hair);
    hairWave.position.set(-0.015, 0.205, 0.03);
    hairWave.rotation.z = 0.10;
    this.falconHeadGroup.add(hairWave);

    // Eyes & Confident Eyebrows
    for (const ex of [-0.046, 0.046]) {
      const eye = new THREE.Mesh(new THREE.SphereGeometry(0.015, 10, 8), this.mats.obsidian);
      eye.position.set(ex, 0.095, 0.118);
      this.falconHeadGroup.add(eye);

      const brow = new THREE.Mesh(new THREE.BoxGeometry(0.042, 0.011, 0.015), this.mats.hair);
      brow.position.set(ex, 0.122, 0.120);
      brow.rotation.z = ex > 0 ? 0.08 : -0.06;
      this.falconHeadGroup.add(brow);
    }

    // Nose
    const nose = new THREE.Mesh(new THREE.ConeGeometry(0.018, 0.052, 8), this.mats.skin);
    nose.rotation.x = Math.PI * 0.42;
    nose.position.set(0, 0.072, 0.136);
    this.falconHeadGroup.add(nose);

    // Iconic Groomed Gentleman Schnauzer (Twin Curved Handlebar Mustache Wings!)
    for (const mx of [-1, 1]) {
      const stacheWing = new THREE.Mesh(new RoundedBoxGeometry(0.068, 0.024, 0.028, 2, 0.01), this.mats.hair);
      stacheWing.position.set(mx * 0.032, 0.040, 0.128);
      stacheWing.rotation.z = mx * -0.18;
      stacheWing.rotation.y = mx * -0.16;
      this.falconHeadGroup.add(stacheWing);

      const stacheTip = new THREE.Mesh(new THREE.SphereGeometry(0.013, 8, 8), this.mats.hair);
      stacheTip.position.set(mx * 0.068, 0.046, 0.120);
      this.falconHeadGroup.add(stacheTip);
    }

    // Iconic 24K-Gold Monocle over Right Eye + Delicate Draped Golden Chain to Lapel!
    const monocleRim = new THREE.Mesh(new THREE.TorusGeometry(0.030, 0.0045, 8, 24), this.mats.goldBright);
    monocleRim.position.set(-0.048, 0.095, 0.130);
    this.falconHeadGroup.add(monocleRim);

    const monocleLens = new THREE.Mesh(
      new THREE.CircleGeometry(0.028, 20),
      new THREE.MeshBasicMaterial({ color: 0xFDE68A, transparent: true, opacity: 0.24, side: THREE.DoubleSide })
    );
    monocleLens.position.set(-0.048, 0.095, 0.131);
    this.falconHeadGroup.add(monocleLens);

    // Golden Monocle Chain Curve (8 fine golden links draping down to left lapel!)
    const chainPts = [
      [-0.076, 0.088, 0.128],
      [-0.092, 0.052, 0.126],
      [-0.104, 0.012, 0.124],
      [-0.110, -0.028, 0.124],
      [-0.112, -0.068, 0.126],
      [-0.108, -0.108, 0.130],
      [-0.102, -0.145, 0.134]
    ];
    chainPts.forEach(([cx, cy, cz]) => {
      const link = new THREE.Mesh(new THREE.SphereGeometry(0.0055, 8, 6), this.mats.goldBright);
      link.position.set(cx, cy, cz);
      this.falconHeadGroup.add(link);
    });
  }

  // ==========================================================================
  // CAMERA FLIGHTS & INTERACTIVE DAMPED ORBITCONTROLS (INSTANT CANCEL!)
  // ==========================================================================
  _syncPlanetVsCityBlend(camPos) {
    // Seamless altitude-based crossfade between Orbital Planet (alt > 780m) and Floating Ocean City (alt < 420m)
    const distFromOrigin = camPos.length();
    const planetVis = clamp((distFromOrigin - 820) / 760, 0, 1);
    const cityVis = clamp(1.0 - (distFromOrigin - 680) / 860, 0, 1);

    if (this.planetGroup) {
      this.planetGroup.visible = planetVis > 0.005;
      this.planetSurfaceUniforms.uAlpha.value = planetVis;
      this.planetCloudUniforms.uAlpha.value = planetVis;
      this.planetAtmoUniforms.uAlpha.value = planetVis;
    }

    if (this.cityGroup) {
      this.cityGroup.visible = cityVis > 0.01;
    }
    if (this.oceanMesh) {
      this.oceanMesh.visible = cityVis > 0.01;
      this.oceanUniforms.uAlpha.value = cityVis;
    }
    if (this.skyUniforms) {
      this.skyUniforms.uSpaceBlend.value = planetVis;
    }
    if (this.starMat) {
      this.starMat.opacity = lerp(0.28, 0.98, planetVis);
    }

    // Subtle sunlit cloud-pierce veil right around cloud deck altitude (dist 760..1250)
    if (!this.cloudVeilEl) {
      this.cloudVeilEl = document.getElementById('fw-city-cloud-veil');
    }
    if (this.cloudVeilEl) {
      const cloudBand = Math.exp(-Math.pow((distFromOrigin - 960) / 210, 2));
      const op = this.flight.active ? (cloudBand * 0.34) : 0;
      this.cloudVeilEl.style.opacity = op > 0.005 ? op.toFixed(3) : '0';
    }

    // Dynamic 3-Stage Camera Clipping Planes (0% GPU cost, 35x-74x higher depth buffer precision, zero Z-fighting!)
    const distTarget = camPos.distanceTo(this.orbit.target);
    const distThrone = camPos.distanceTo(this._throneRefVec);
    const camDist = distFromOrigin > 1400
      ? distFromOrigin
      : Math.min(distFromOrigin, distTarget, distThrone);

    let newNear;
    let newFar;
    if (camDist < 48.0) {
      newNear = 0.15;
      newFar = 4200;
    } else if (camDist <= 1400.0) {
      newNear = clamp(camDist * 0.018, 0.8, 22.0);
      newFar = 9600;
    } else {
      newNear = clamp(camDist * 0.04, 22.0, 140.0);
      newFar = 28000;
    }

    if (Math.abs(newNear - this.camera.near) > 0.02 || Math.abs(newFar - this.camera.far) > 5) {
      this.camera.near = newNear;
      this.camera.far = newFar;
      this.camera.updateProjectionMatrix();
    }
  }

  _resolveDepartment(deptKeyOrIndex) {
    if (typeof deptKeyOrIndex === 'number' && Number.isInteger(deptKeyOrIndex)) {
      return DEPARTMENTS[deptKeyOrIndex] || null;
    }
    const norm = String(deptKeyOrIndex ?? '').trim().toLowerCase();
    if (!norm) return null;
    const byKey = DEPARTMENTS.find((d) => d.key === norm);
    if (byKey) return byKey;
    if (/^[0-6]$/.test(norm)) {
      return DEPARTMENTS[Number(norm)] || null;
    }
    return DEPARTMENTS.find((d) => d.name.toLowerCase() === norm) || null;
  }

  _emitDepartmentChange(deptOrNull) {
    const nextKey = deptOrNull ? deptOrNull.key : null;
    this.activeDepartment = nextKey;
    window.dispatchEvent(new CustomEvent('fw-city-department-change', {
      detail: {
        deptKey: nextKey,
        dept: deptOrNull || null
      }
    }));
  }

  _showHoverChip(clientX, clientY, label, hexColor) {
    if (!this.hoverChipEl) {
      this.hoverChipEl = document.getElementById('fw-city-hover-chip');
      this.hoverDotEl = document.getElementById('fw-city-hover-dot');
      this.hoverLabelEl = document.getElementById('fw-city-hover-label');
    }
    if (!this.hoverChipEl) return;
    if (this.hoverDotEl) {
      this.hoverDotEl.style.background = hexColor || '#C5A059';
    }
    if (this.hoverLabelEl) {
      this.hoverLabelEl.textContent = label || '';
    }
    const maxLeft = Math.max(12, window.innerWidth - 210);
    const maxTop = Math.max(12, window.innerHeight - 46);
    const x = clamp(clientX + 16, 12, maxLeft);
    const y = clamp(clientY - 18, 12, maxTop);
    this.hoverChipEl.style.transform = `translate3d(${Math.round(x)}px, ${Math.round(y)}px, 0)`;
    this.hoverChipEl.classList.remove('hidden');
  }

  _hideHoverChip() {
    if (!this.hoverChipEl) {
      this.hoverChipEl = document.getElementById('fw-city-hover-chip');
    }
    this.hoverChipEl?.classList.add('hidden');
  }

  _adoptCameraIntoOrbitControls(targetVec, modeHint = null) {
    if (modeHint) this.viewMode = modeHint;
    this.orbit.target.copy(targetVec);
    this.orbit.desiredTarget.copy(targetVec);

    const offset = new THREE.Vector3().subVectors(this.camera.position, targetVec);
    const r = Math.max(1.8, offset.length());
    const phi = clamp(Math.acos(clamp(offset.y / r, -0.995, 0.995)), 0.08, Math.PI * 0.495);
    const theta = Math.atan2(offset.x, offset.z);

    this.orbit.radius = this.orbit.desiredRadius = r;
    this.orbit.phi = this.orbit.desiredPhi = phi;
    this.orbit.theta = this.orbit.desiredTheta = theta;

    if (this.viewMode === 'throne') {
      this.orbit.minRadius = 1.65;
      this.orbit.maxRadius = 42.0;
      this.orbit.minY = THRONE_FLOOR_Y + 0.45;
    } else if (this.viewMode === 'department') {
      this.orbit.minRadius = 42.0;
      this.orbit.maxRadius = 920.0;
      this.orbit.minY = 6.2;
    } else {
      this.orbit.minRadius = 28.0;
      this.orbit.maxRadius = 6200.0;
      this.orbit.minY = 4.5;
    }
    this._updateHudActiveButtons();
  }

  cancelFlightToFreeOrbit() {
    if (!this.flight.active) return;
    const prevFlightType = this.flight.type;
    this.flight.active = false;
    if (this.cloudVeilEl) this.cloudVeilEl.style.opacity = '0';

    // If cancelled mid-space-dive, ensure the city is cleanly visible if close enough, or keep current look target
    const curLook = new THREE.Vector3();
    this.camera.getWorldDirection(curLook);
    const dist = clamp(this.camera.position.length() * 0.45, 4.0, 900.0);
    const target = this.camera.position.clone().addScaledVector(curLook, dist);
    if (this.viewMode === 'department' && prevFlightType === 'glide_department') {
      target.copy(this.flight.toTarget);
    } else if (this.camera.position.length() < 1200 && this.viewMode !== 'department') {
      target.set(0, clamp(target.y, 8, 265), 0);
    }
    const keepMode = (this.viewMode === 'throne' || this.viewMode === 'department')
      ? this.viewMode
      : 'city';
    this._adoptCameraIntoOrbitControls(target, keepMode);
  }

  // Phase 1 -> Phase 2: Epic 6.0s Orbital Planet Fly-In to the Floating City of Funkenwerk!
  startOrbitalFlyIn() {
    this.init();
    this.viewMode = 'orbit';
    this._emitDepartmentChange(null);
    this._updateHudActiveButtons();

    const startPos = new THREE.Vector3(980, 3550, 3850);
    const startTarget = new THREE.Vector3(0, -1350, 0);
    const cityOverviewPos = new THREE.Vector3(175, 178, 520);
    const cityOverviewTarget = new THREE.Vector3(0, 112, 0);

    this.camera.position.copy(startPos);
    this.camera.lookAt(startTarget);
    this._syncPlanetVsCityBlend(startPos);

    this.flight.active = true;
    this.flight.type = 'orbital_dive';
    this.flight.elapsed = 0;
    this.flight.duration = 6.0;
    this.flight.fromPos.copy(startPos);
    this.flight.fromTarget.copy(startTarget);
    this.flight.midPos.set(340, 940, 1180);
    this.flight.toPos.copy(cityOverviewPos);
    this.flight.toTarget.copy(cityOverviewTarget);
  }

  // Phase 2 Direct Smooth Glide to Atlantis Water City Overview
  flyToCityOverview(instant = false) {
    this.init();
    this.viewMode = 'city';
    this._emitDepartmentChange(null);
    this._updateHudActiveButtons();

    const destPos = new THREE.Vector3(175, 178, 520);
    const destTarget = new THREE.Vector3(0, 112, 0);

    if (instant) {
      this.flight.active = false;
      this.camera.position.copy(destPos);
      this.camera.lookAt(destTarget);
      this._syncPlanetVsCityBlend(destPos);
      this._adoptCameraIntoOrbitControls(destTarget, 'city');
      return;
    }

    this.flight.active = true;
    this.flight.type = 'glide_city';
    this.flight.elapsed = 0;
    this.flight.duration = 2.5;
    this.flight.fromPos.copy(this.camera.position);
    this.flight.fromTarget.copy(this.orbit.target);
    this.flight.midPos.set(
      lerp(this.camera.position.x, destPos.x, 0.5) + 45,
      Math.max(this.camera.position.y, destPos.y) + 48,
      lerp(this.camera.position.z, destPos.z, 0.5) + 65
    );
    this.flight.toPos.copy(destPos);
    this.flight.toTarget.copy(destTarget);
  }

  // Phase 2D Smooth Over-Lagoon Glide to any of the 7 Department Piers!
  flyToDepartment(deptKeyOrIndex, instant = false) {
    this.init();
    const dept = this._resolveDepartment(deptKeyOrIndex);
    if (!dept) return false;
    const k = DEPARTMENTS.indexOf(dept);
    const theta = this.pierAngles[k];
    const rotY = Math.atan2(Math.cos(theta), Math.sin(theta));

    this.viewMode = 'department';
    this._emitDepartmentChange(dept);
    this._updateHudActiveButtons();

    // Pier-Citadel Landmark target (lx = 0, y = 23.5, lz = 362) & elevated 3/4 lagoon hero viewpoint (lx = 52, y = 39.5, lz = 250)
    const destTarget = new THREE.Vector3(
      362 * Math.sin(rotY),
      23.5,
      362 * Math.cos(rotY)
    );
    const destPos = new THREE.Vector3(
      52 * Math.cos(rotY) + 250 * Math.sin(rotY),
      39.5,
      -52 * Math.sin(rotY) + 250 * Math.cos(rotY)
    );

    if (instant) {
      this.flight.active = false;
      this.camera.position.copy(destPos);
      this.camera.lookAt(destTarget);
      this._syncPlanetVsCityBlend(destPos);
      this._adoptCameraIntoOrbitControls(destTarget, 'department');
      return true;
    }

    // Atmospheric approach entry point if starting from deep space or fresh origin
    if (this.camera.position.length() > 1400 || this.camera.position.lengthSq() < 10) {
      const seaEntry = new THREE.Vector3(
        115 * Math.cos(rotY) + 185 * Math.sin(rotY),
        135.0,
        -115 * Math.sin(rotY) + 185 * Math.cos(rotY)
      );
      this.camera.position.copy(seaEntry);
      this.orbit.target.copy(destTarget);
    }

    const mid = new THREE.Vector3().lerpVectors(this.camera.position, destPos, 0.5);
    const midHoriz = Math.hypot(mid.x, mid.z);
    if (midHoriz < 215) {
      const outAngle = midHoriz > 5 ? Math.atan2(mid.z, mid.x) : theta;
      mid.x = Math.cos(outAngle) * 240;
      mid.z = Math.sin(outAngle) * 240;
    }
    mid.y = Math.max(this.camera.position.y, destPos.y) + 28;

    this.flight.active = true;
    this.flight.type = 'glide_department';
    this.flight.elapsed = 0;
    this.flight.duration = 2.2;
    this.flight.fromPos.copy(this.camera.position);
    this.flight.fromTarget.copy(this.orbit.target);
    this.flight.midPos.copy(mid);
    this.flight.toPos.copy(destPos);
    this.flight.toTarget.copy(destTarget);
    return true;
  }

  // Phase 3 Smooth Panoramic Ascent into Alina & Falcon's Penthouse Throne Room!
  flyToThroneRoom(instant = false) {
    this.init();
    this.viewMode = 'throne';
    this._emitDepartmentChange(null);
    this._updateHudActiveButtons();

    // Framed right on the Royal Crimson Velvet Carpet so BOTH Alina's High Throne ("ALINA / Herrscherin...")
    // AND Falcon's Right-Hand Throne + 3D Falcon + Panoramic Ocean Horizon shine in 1 breathtaking frame!
    const destPos = new THREE.Vector3(0.55, THRONE_FLOOR_Y + 2.58, 3.95);
    const destTarget = new THREE.Vector3(0.78, THRONE_FLOOR_Y + 1.88, -3.45);

    if (instant) {
      this.flight.active = false;
      this.camera.position.copy(destPos);
      this.camera.lookAt(destTarget);
      this._syncPlanetVsCityBlend(destPos);
      this._adoptCameraIntoOrbitControls(destTarget, 'throne');
      return;
    }

    // If starting from Deep Space, snap inside atmospheric approach first so the ascent into the tower crown is crisp
    if (this.camera.position.length() > 1400) {
      this.camera.position.set(95, 290, 260);
      this.orbit.target.set(0, THRONE_FLOOR_Y, 0);
    }

    this.flight.active = true;
    this.flight.type = 'glide_throne';
    this.flight.elapsed = 0;
    this.flight.duration = 2.75;
    this.flight.fromPos.copy(this.camera.position);
    this.flight.fromTarget.copy(this.orbit.target);
    this.flight.midPos.set(
      this.camera.position.x * 0.25,
      Math.max(this.camera.position.y * 0.5 + 135, THRONE_FLOOR_Y + 4.2),
      Math.max(11.5, this.camera.position.z * 0.18)
    );
    this.flight.toPos.copy(destPos);
    this.flight.toTarget.copy(destTarget);
  }

  _updateHudActiveButtons() {
    const bOrbit = document.getElementById('btn-fw-city-orbit');
    const bCity = document.getElementById('btn-fw-city-overview');
    const bDepts = document.getElementById('btn-fw-city-departments');
    const deptMenu = document.getElementById('fw-city-dept-menu');
    const bThrone = document.getElementById('btn-fw-city-throne');
    bOrbit?.classList.toggle('active', this.viewMode === 'orbit');
    bCity?.classList.toggle('active', this.viewMode === 'city');
    bThrone?.classList.toggle('active', this.viewMode === 'throne');
    if (bDepts) {
      const activeDept = this.viewMode === 'department' && this.activeDepartment
        ? DEPARTMENTS.find((d) => d.key === this.activeDepartment)
        : null;
      bDepts.classList.toggle('active', !!activeDept);
      bDepts.textContent = activeDept ? `${activeDept.icon} ${activeDept.name} ▾` : '🏢 Abteilungen ▾';
    }
    if (deptMenu) {
      deptMenu.querySelectorAll('[data-dept-key]').forEach((item) => {
        const isAct = this.viewMode === 'department' && item.getAttribute('data-dept-key') === this.activeDepartment;
        item.classList.toggle('active', isAct);
      });
    }
  }

  _bindControls() {
    const el = this.canvas;
    if (!el) return;

    el.addEventListener('contextmenu', (e) => e.preventDefault());

    el.addEventListener('pointerdown', (e) => {
      if (!this.running) return;
      document.getElementById('fw-city-dept-menu')?.classList.add('hidden');
      document.getElementById('btn-fw-city-departments')?.setAttribute('aria-expanded', 'false');
      this._hideHoverChip();
      if (this.flight.active) {
        this.cancelFlightToFreeOrbit();
      }
      this.orbit.isDragging = true;
      this.orbit.dragButton = e.button;
      this.orbit.downX = this.orbit.lastX = e.clientX;
      this.orbit.downY = this.orbit.lastY = e.clientY;
      this.orbit.movedDist = 0;
      try { el.setPointerCapture(e.pointerId); } catch { }
    });

    el.addEventListener('pointermove', (e) => {
      if (!this.running) return;

      if (!this.orbit.isDragging) {
        if (!this.cityGroup?.visible) {
          this.hoveredDeptKey = null;
          el.style.cursor = 'grab';
          this._hideHoverChip();
          return;
        }

        const rect = el.getBoundingClientRect();
        this.pointerNdc.x = ((e.clientX - rect.left) / Math.max(1, rect.width)) * 2 - 1;
        this.pointerNdc.y = -((e.clientY - rect.top) / Math.max(1, rect.height)) * 2 + 1;
        this.raycaster.setFromCamera(this.pointerNdc, this.camera);

        let hitLabel = '';
        let hitHex = '#C5A059';
        let hitDeptKey = null;

        if (this.viewMode !== 'throne' && this.raycaster.intersectObjects(this.throneClickTargets, false).length > 0) {
          hitLabel = '👑 Thronsaal';
          hitHex = '#C5A059';
        } else {
          const deptHits = this.raycaster.intersectObjects(this.departmentClickTargets, false);
          if (deptHits.length > 0 && deptHits[0].object?.userData?.deptKey) {
            const ud = deptHits[0].object.userData;
            hitDeptKey = ud.deptKey;
            hitLabel = `${ud.icon || '🏢'} ${ud.name}`;
            hitHex = ud.hex || '#C5A059';
          }
        }

        this.hoveredDeptKey = hitDeptKey;
        if (hitLabel) {
          el.style.cursor = 'pointer';
          this._showHoverChip(e.clientX, e.clientY, hitLabel, hitHex);
        } else {
          el.style.cursor = 'grab';
          this._hideHoverChip();
        }
        return;
      }

      const dx = e.clientX - this.orbit.lastX;
      const dy = e.clientY - this.orbit.lastY;
      this.orbit.lastX = e.clientX;
      this.orbit.lastY = e.clientY;
      this.orbit.movedDist += Math.hypot(dx, dy);

      if (this.orbit.movedDist > 2) {
        this.hoveredDeptKey = null;
        this._hideHoverChip();
        el.style.cursor = 'grabbing';
      }

      if (this.orbit.dragButton === 2 || e.shiftKey) {
        // Right-drag / Shift-drag: Smooth horizontal/vertical pan
        const panScale = this.orbit.radius * 0.0014;
        const right = new THREE.Vector3();
        const up = new THREE.Vector3(0, 1, 0);
        this.camera.getWorldDirection(right);
        right.cross(up).normalize();
        this.orbit.desiredTarget.addScaledVector(right, -dx * panScale);
        this.orbit.desiredTarget.y = clamp(this.orbit.desiredTarget.y + dy * panScale, 2.0, 320.0);
      } else {
        // Left-drag: Silky 360° spherical orbit
        this.orbit.desiredTheta -= dx * 0.0052;
        const maxPhi = this.viewMode === 'throne' ? Math.PI * 0.56 : Math.PI * 0.488;
        this.orbit.desiredPhi = clamp(this.orbit.desiredPhi - dy * 0.0046, 0.08, maxPhi);
      }
    });

    el.addEventListener('pointerleave', () => {
      this.hoveredDeptKey = null;
      this._hideHoverChip();
      if (!this.orbit.isDragging) {
        el.style.cursor = 'grab';
      }
    });

    const endPointer = (e) => {
      if (!this.orbit.isDragging) return;
      this.orbit.isDragging = false;
      el.style.cursor = 'grab';
      try { el.releasePointerCapture(e.pointerId); } catch { }

      // Click without drag: 1. Throne Room -> 2. Department Pier -> 3. Open Water return
      if (this.orbit.movedDist < 6 && e.button === 0 && this.cityGroup?.visible) {
        const rect = el.getBoundingClientRect();
        this.pointerNdc.x = ((e.clientX - rect.left) / Math.max(1, rect.width)) * 2 - 1;
        this.pointerNdc.y = -((e.clientY - rect.top) / Math.max(1, rect.height)) * 2 + 1;
        this.raycaster.setFromCamera(this.pointerNdc, this.camera);

        if (this.viewMode !== 'throne' && this.raycaster.intersectObjects(this.throneClickTargets, false).length > 0) {
          this.flyToThroneRoom(false);
        } else {
          const deptHits = this.raycaster.intersectObjects(this.departmentClickTargets, false);
          if (deptHits.length > 0 && deptHits[0].object?.userData?.deptKey) {
            this.flyToDepartment(deptHits[0].object.userData.deptKey, false);
          } else if (this.viewMode === 'department') {
            this.flyToCityOverview(false);
          }
        }
      }
    };

    el.addEventListener('pointerup', endPointer);
    el.addEventListener('pointercancel', endPointer);

    el.addEventListener('wheel', (e) => {
      if (!this.running) return;
      e.preventDefault();
      if (this.flight.active) {
        this.cancelFlightToFreeOrbit();
      }
      const zoomFactor = e.deltaY > 0 ? 1.11 : 0.90;
      this.orbit.desiredRadius = clamp(
        this.orbit.desiredRadius * zoomFactor,
        this.orbit.minRadius,
        this.orbit.maxRadius
      );
    }, { passive: false });

    // Multi-touch Pinch-Zoom support
    el.addEventListener('touchstart', (e) => {
      if (e.touches.length === 2) {
        if (this.flight.active) this.cancelFlightToFreeOrbit();
        const dx = e.touches[0].clientX - e.touches[1].clientX;
        const dy = e.touches[0].clientY - e.touches[1].clientY;
        this.orbit.pinchDist = Math.hypot(dx, dy);
      }
    }, { passive: true });

    el.addEventListener('touchmove', (e) => {
      if (e.touches.length === 2 && this.orbit.pinchDist > 8) {
        const dx = e.touches[0].clientX - e.touches[1].clientX;
        const dy = e.touches[0].clientY - e.touches[1].clientY;
        const dist = Math.hypot(dx, dy);
        const scale = this.orbit.pinchDist / Math.max(8, dist);
        this.orbit.pinchDist = dist;
        this.orbit.desiredRadius = clamp(
          this.orbit.desiredRadius * scale,
          this.orbit.minRadius,
          this.orbit.maxRadius
        );
      }
    }, { passive: true });
  }

  // ==========================================================================
  // LIFECYCLE: OPEN, CLOSE (100% ZERO CPU/GPU WHEN CLOSED!) & 60-FPS RENDER LOOP
  // ==========================================================================
  open(initialMode = 'orbit') {
    this.init();
    this.resize();

    const modeStr = String(initialMode || 'orbit').trim();
    if (modeStr.startsWith('department:')) {
      const deptKey = modeStr.slice('department:'.length).trim() || 'orders';
      if (this.camera.position.lengthSq() < 10) {
        this.camera.position.set(175, 178, 520);
        this.orbit.target.set(0, 112, 0);
      }
      this.flyToDepartment(deptKey, false);
    } else if (modeStr === 'throne') {
      this.flyToThroneRoom(false);
    } else if (modeStr === 'city' || modeStr === 'heroes') {
      this.flyToCityOverview(true);
    } else {
      this.startOrbitalFlyIn();
    }

    if (!this.running) {
      this.running = true;
      this.lastFrameMs = performance.now();
      this.rafId = requestAnimationFrame(this._boundLoop);
    }
  }

  close() {
    this.running = false;
    this.flight.active = false;
    this.hoveredDeptKey = null;
    this._hideHoverChip();
    this._emitDepartmentChange(null);
    if (this.rafId) {
      cancelAnimationFrame(this.rafId);
      this.rafId = 0;
    }
    if (this.cloudVeilEl) {
      this.cloudVeilEl.style.opacity = '0';
    }
  }

  resize() {
    if (!this.renderer || !this.camera) return;
    const w = Math.max(1, window.innerWidth);
    const h = Math.max(1, window.innerHeight);
    const baseDpr = Math.min(window.devicePixelRatio || 1, 1.35);
    this.renderer.setPixelRatio(baseDpr * this.renderScale);
    this.renderer.setSize(w, h, false);
    this.camera.aspect = w / h;
    this.camera.updateProjectionMatrix();
  }

  _updateLivingTrafficAndFalcon(t) {
    // 1. Update 84 Golden Sky-Gliders cruising along piers, ring bridges & supertower
    if (this.gliderInst && this.cityGroup?.visible) {
      for (let i = 0; i < this.gliderData.length; i++) {
        const g = this.gliderData[i];
        const u = ((g.phase + t * g.speed) % 1 + 1) % 1;
        let wx = 0, wy = g.alt, wz = 0, yaw = 0;

        if (g.mode === 'pier') {
          const ang = this.pierAngles[g.pierIdx];
          const rotY = Math.atan2(Math.cos(ang), Math.sin(ang));
          const rDist = lerp(150, 528, u);
          wx = g.laneOffset * Math.cos(rotY) + rDist * Math.sin(rotY);
          wz = -g.laneOffset * Math.sin(rotY) + rDist * Math.cos(rotY);
          yaw = g.speed >= 0 ? rotY : (rotY + Math.PI);
        } else if (g.mode === 'ring') {
          const ang = u * Math.PI * 2;
          wx = Math.cos(ang) * g.ringR;
          wz = Math.sin(ang) * g.ringR;
          const dirSign = g.speed >= 0 ? 1 : -1;
          yaw = Math.atan2(-Math.sin(ang) * dirSign, Math.cos(ang) * dirSign);
        } else {
          const ang = u * Math.PI * 2;
          const rOrb = 52 + (i % 3) * 18;
          wx = Math.cos(ang) * rOrb;
          wz = Math.sin(ang) * rOrb;
          wy = g.alt + Math.sin(t * 1.4 + i) * 3.0;
          const dirSign = g.speed >= 0 ? 1 : -1;
          yaw = Math.atan2(-Math.sin(ang) * dirSign, Math.cos(ang) * dirSign);
        }

        this.dummyObj.position.set(wx, wy, wz);
        this.dummyObj.rotation.set(0, yaw, 0);
        this.dummyObj.scale.set(1, 1, 1);
        this.dummyObj.updateMatrix();
        this.gliderInst.setMatrixAt(i, this.dummyObj.matrix);
      }
      this.gliderInst.instanceMatrix.needsUpdate = true;
    }

    // 1b. Gentle Matte Beacon Ring Pulse on Hovered or Active Department Pier
    if (this.cityGroup?.visible) {
      for (let k = 0; k < DEPARTMENTS.length; k++) {
        const dk = DEPARTMENTS[k].key;
        const b = this.departmentBeacons[dk];
        if (!b) continue;
        const isHighlighted = (this.hoveredDeptKey === dk) || (this.viewMode === 'department' && this.activeDepartment === dk);
        if (isHighlighted) {
          const pulse = 0.5 + 0.5 * Math.sin(t * 3.4);
          b.mat.opacity = 0.52 + 0.30 * pulse;
          const scPod = 1.0 + 0.026 * pulse;
          const scPad = 1.0 + 0.048 * pulse;
          b.podiumBeacon.scale.set(scPod, 1, scPod);
          b.padBeacon.scale.set(scPad, 1, scPad);
        } else {
          b.mat.opacity = 0.26;
          b.podiumBeacon.scale.set(1, 1, 1);
          b.padBeacon.scale.set(1, 1, 1);
        }
      }

      // 1c. Smooth 60-FPS Levitation & Multi-Axis Rotation of the 7 Pier Citadel 3D Landmarks
      for (let li = 0; li < this.departmentLandmarks.length; li++) {
        const lm = this.departmentLandmarks[li];
        const isActiveLm = (this.hoveredDeptKey === lm.key) || (this.viewMode === 'department' && this.activeDepartment === lm.key);
        const speedMul = isActiveLm ? 1.45 : 1.0;
        const liftY = isActiveLm ? 0.38 : 0.0;
        lm.floatGroup.position.y = lm.baseY + liftY + Math.sin(t * 1.35 + lm.phase) * 0.42;
        lm.primaryRotor.rotation.y = (t * 0.36 * speedMul) + lm.phase;
        lm.secondaryRotor.rotation.y = -(t * 0.54 * speedMul) - lm.phase;
        if (lm.key === 'agents' || lm.key === 'orders' || lm.key === 'support') {
          lm.secondaryRotor.rotation.x = Math.sin(t * 0.68 + lm.phase) * 0.36;
          lm.secondaryRotor.rotation.z = Math.cos(t * 0.52 + lm.phase) * 0.28;
        }
      }
    }

    // 2. Subtle Poised Vigilance Animation for 3D Falcon in the Penthouse Throne Room
    if (this.falconChestGroup && this.falconHeadGroup) {
      this.falconChestGroup.position.y = 0.94 + Math.sin(t * 1.85) * 0.008;
      this.falconHeadGroup.rotation.y = Math.sin(t * 0.65) * 0.09;
      this.falconHeadGroup.rotation.z = Math.cos(t * 0.85) * 0.022;
    }
  }

  _tick(nowMs) {
    if (!this.running) return;
    this.rafId = requestAnimationFrame(this._boundLoop);

    const rawDt = (nowMs - this.lastFrameMs) / 1000;
    this.lastFrameMs = nowMs;
    const dt = clamp(rawDt, 0.001, 0.05);
    this.clockTime += dt;

    // Adaptive Resolution Guard for Locked 60 FPS on Intel UHD 605
    this.smoothDt = lerp(this.smoothDt, dt, 0.08);
    if (this.smoothDt > 0.022 && this.renderScale > 0.80) {
      this.renderScale = Math.max(0.80, this.renderScale - 0.06);
      this.resize();
    } else if (this.smoothDt < 0.0155 && this.renderScale < 1.0) {
      this.renderScale = Math.min(1.0, this.renderScale + 0.04);
      this.resize();
    }

    // Update Shader Clocks
    this.planetSurfaceUniforms.uTime.value = this.clockTime;
    this.planetCloudUniforms.uTime.value = this.clockTime;
    this.oceanUniforms.uTime.value = this.clockTime;

    // Update Camera (Automated Quadratic-Bezier Spline Flight OR Damped Spherical OrbitControls)
    if (this.flight.active) {
      this.flight.elapsed += dt;
      const rawT = clamp(this.flight.elapsed / this.flight.duration, 0, 1);
      const e = this.flight.type === 'orbital_dive' ? easeInOutQuint(rawT) : easeInOutCubic(rawT);

      // Quadratic Bezier Camera Position for Sweeping Cinema Arc!
      const omt = 1.0 - e;
      const px = omt * omt * this.flight.fromPos.x + 2 * omt * e * this.flight.midPos.x + e * e * this.flight.toPos.x;
      const py = omt * omt * this.flight.fromPos.y + 2 * omt * e * this.flight.midPos.y + e * e * this.flight.toPos.y;
      const pz = omt * omt * this.flight.fromPos.z + 2 * omt * e * this.flight.midPos.z + e * e * this.flight.toPos.z;
      this.camera.position.set(px, py, pz);

      const targetE = this.flight.type === 'orbital_dive' ? Math.pow(e, 1.35) : e;
      const curTarget = new THREE.Vector3().lerpVectors(this.flight.fromTarget, this.flight.toTarget, targetE);
      this.camera.lookAt(curTarget);
      this.orbit.target.copy(curTarget);

      if (rawT >= 1.0) {
        this.flight.active = false;
        const settledMode = this.flight.type === 'glide_throne'
          ? 'throne'
          : (this.flight.type === 'glide_department' ? 'department' : 'city');
        this._adoptCameraIntoOrbitControls(this.flight.toTarget, settledMode);
      }
    } else {
      const damp = 1.0 - Math.exp(-10.5 * dt);
      this.orbit.target.lerp(this.orbit.desiredTarget, damp);
      this.orbit.radius = lerp(this.orbit.radius, this.orbit.desiredRadius, damp);
      this.orbit.theta = lerp(this.orbit.theta, this.orbit.desiredTheta, damp);
      this.orbit.phi = lerp(this.orbit.phi, this.orbit.desiredPhi, damp);

      const sinPhi = Math.sin(this.orbit.phi);
      const cx = this.orbit.target.x + this.orbit.radius * sinPhi * Math.sin(this.orbit.theta);
      let cy = this.orbit.target.y + this.orbit.radius * Math.cos(this.orbit.phi);
      const cz = this.orbit.target.z + this.orbit.radius * sinPhi * Math.cos(this.orbit.theta);
      if (cy < this.orbit.minY) cy = this.orbit.minY;

      this.camera.position.set(cx, cy, cz);
      this.camera.lookAt(this.orbit.target);
    }

    this._syncPlanetVsCityBlend(this.camera.position);
    this._updateLivingTrafficAndFalcon(this.clockTime);

    this.renderer.render(this.scene, this.camera);
  }
}

function CylinderOrBoxPillar(r, h) {
  return new THREE.CylinderGeometry(r, r * 1.08, h, 14);
}

const funkenwerkCityInstance = new FunkenwerkCityEngine();
window.FunkenwerkCity3D = funkenwerkCityInstance;
