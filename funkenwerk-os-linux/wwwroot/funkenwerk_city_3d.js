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

// Procedural Skyscraper Curtain-Wall Facade Texture (90% Obsidian & 24K Gold Mullions + Warm Atelier Windows)
function makeSkyscraperFacadeTexture() {
  const W = 512, H = 1024;
  const c = document.createElement('canvas');
  c.width = W;
  c.height = H;
  const ctx = c.getContext('2d');
  const rng = mulberry32(20261116);

  // Deep obsidian architectural glass base
  const bg = ctx.createLinearGradient(0, 0, 0, H);
  bg.addColorStop(0, '#0c1018');
  bg.addColorStop(0.55, '#080b11');
  bg.addColorStop(1, '#05070b');
  ctx.fillStyle = bg;
  ctx.fillRect(0, 0, W, H);

  const floors = 48;
  const cols = 16;
  const fh = H / floors;
  const cw = W / cols;

  for (let f = 0; f < floors; f++) {
    const y = f * fh;
    const bandHighlight = (f % 8 === 0);
    for (let col = 0; col < cols; col++) {
      const x = col * cw;
      const litRoll = rng();
      if (bandHighlight) {
        ctx.fillStyle = 'rgba(197, 160, 89, 0.42)';
        ctx.fillRect(x + 2, y + 3, cw - 4, fh - 6);
      } else if (litRoll > 0.56) {
        const warm = litRoll > 0.86 ? 'rgba(255, 236, 179, 0.82)' : 'rgba(197, 160, 89, 0.38)';
        ctx.fillStyle = warm;
        ctx.fillRect(x + 3.5, y + 3.5, cw - 7, fh - 7);
      } else {
        ctx.fillStyle = 'rgba(18, 26, 38, 0.68)';
        ctx.fillRect(x + 3, y + 3, cw - 6, fh - 6);
      }
    }
    // Horizontal brushed gold spandrel line
    ctx.fillStyle = bandHighlight ? '#D4AF37' : 'rgba(197, 160, 89, 0.34)';
    ctx.fillRect(0, y, W, bandHighlight ? 2.4 : 1.2);
  }

  // Crisp vertical 24K gold structural mullions
  for (let col = 0; col <= cols; col++) {
    const x = col * cw;
    const major = (col % 4 === 0);
    ctx.fillStyle = major ? '#C5A059' : 'rgba(197, 160, 89, 0.26)';
    ctx.fillRect(x - (major ? 1.5 : 0.6), 0, major ? 3.0 : 1.2, H);
  }

  const tex = configureHdTex(new THREE.CanvasTexture(c), 4);
  tex.wrapS = THREE.RepeatWrapping;
  tex.wrapT = THREE.RepeatWrapping;
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

// Monumental 24K-Gold Pantheon Entablature Frieze ("HALLE DER HELDEN")
function makeHallOfHeroesFriezeTexture() {
  const W = 2048, H = 320;
  const c = document.createElement('canvas');
  c.width = W;
  c.height = H;
  const ctx = c.getContext('2d');

  const bg = ctx.createLinearGradient(0, 0, 0, H);
  bg.addColorStop(0, '#0d1017');
  bg.addColorStop(0.5, '#07090e');
  bg.addColorStop(1, '#0d1017');
  ctx.fillStyle = bg;
  ctx.fillRect(0, 0, W, H);

  ctx.strokeStyle = '#C5A059';
  ctx.lineWidth = 10;
  ctx.strokeRect(12, 12, W - 24, H - 24);
  ctx.strokeStyle = 'rgba(253, 230, 138, 0.55)';
  ctx.lineWidth = 3;
  ctx.strokeRect(28, 28, W - 56, H - 56);

  ctx.textAlign = 'center';
  ctx.textBaseline = 'middle';
  ctx.font = '900 116px "Liberation Serif", "Georgia", "Liberation Sans", serif';
  if (typeof ctx.letterSpacing !== 'undefined') {
    ctx.letterSpacing = '14px';
  }

  ctx.fillStyle = 'rgba(0, 0, 0, 0.92)';
  ctx.fillText('HALLE DER HELDEN', W * 0.5 + 4, H * 0.53 + 5);

  const tg = ctx.createLinearGradient(0, H * 0.20, 0, H * 0.82);
  tg.addColorStop(0.0, '#FFFDF4');
  tg.addColorStop(0.3, '#FDE68A');
  tg.addColorStop(0.58, '#D4AF37');
  tg.addColorStop(0.85, '#FFF2C2');
  tg.addColorStop(1.0, '#AD8333');
  ctx.fillStyle = tg;
  ctx.fillText('HALLE DER HELDEN', W * 0.5, H * 0.53);

  return configureHdTex(new THREE.CanvasTexture(c), 4);
}

// High-Contrast Silk-Matte Carrara-Alabaster & Deep Atlantis-Sapphire Pantheon Floor Texture
function makeHallOfHeroesMarbleTexture() {
  const S = 1024;
  const c = document.createElement('canvas');
  c.width = S;
  c.height = S;
  const ctx = c.getContext('2d');
  const cx = S * 0.5, cy = S * 0.5;

  // 1. Noble Carrara-Alabaster & Pearl Marble Base (#ECEAE4 -> #DFDCD3, zero glare)
  const rg = ctx.createRadialGradient(cx, cy, 40, cx, cy, S * 0.68);
  rg.addColorStop(0.0, '#ECEAE4');
  rg.addColorStop(0.65, '#E4E1D9');
  rg.addColorStop(1.0, '#D8D4CA');
  ctx.fillStyle = rg;
  ctx.fillRect(0, 0, S, S);

  // 2. Subtle Cut-Marble Ashlar Slab Grid with Slight Tone Variation per Slab
  const slabs = 8;
  const step = S / slabs;
  const rng = mulberry32(20261445);
  for (let r = 0; r < slabs; r++) {
    for (let col = 0; col < slabs; col++) {
      const shade = Math.round((rng() - 0.5) * 10);
      ctx.fillStyle = `rgba(${232 + shade}, ${229 + shade}, ${222 + shade}, 0.42)`;
      ctx.fillRect(col * step + 1.5, r * step + 1.5, step - 3, step - 3);
    }
  }

  // 3. Fine Cool Silver-Slate Carrara Marble Veins (#94A3B8)
  for (let i = 0; i < 54; i++) {
    ctx.strokeStyle = i % 3 === 0 ? 'rgba(100, 116, 139, 0.24)' : 'rgba(148, 163, 184, 0.20)';
    ctx.lineWidth = i % 4 === 0 ? 2.1 : 1.2;
    ctx.beginPath();
    let x = rng() * S;
    let y = rng() * S;
    ctx.moveTo(x, y);
    for (let s = 0; s < 6; s++) {
      x += (rng() - 0.42) * 185;
      y += (rng() - 0.48) * 145;
      ctx.lineTo(x, y);
    }
    ctx.stroke();
  }

  // Ashlar Grout Lines
  ctx.strokeStyle = 'rgba(148, 163, 184, 0.34)';
  ctx.lineWidth = 1.6;
  for (let i = 1; i < slabs; i++) {
    const p = i * step;
    ctx.beginPath(); ctx.moveTo(p, 0); ctx.lineTo(p, S); ctx.stroke();
    ctx.beginPath(); ctx.moveTo(0, p); ctx.lineTo(S, p); ctx.stroke();
  }

  // 4. Deep Atlantis-Sapphire Outer Frame (#133B5C) Setting Off the 24K-Gold Colonnades & Steles
  ctx.strokeStyle = '#133B5C';
  ctx.lineWidth = 44;
  ctx.strokeRect(42, 42, S - 84, S - 84);

  ctx.strokeStyle = '#0E2B45';
  ctx.lineWidth = 6;
  ctx.strokeRect(68, 68, S - 136, S - 136);

  // Subtle Sapphire Ehrenpromenade Center Inlay Guide
  ctx.fillStyle = 'rgba(19, 59, 92, 0.14)';
  ctx.fillRect(cx - 88, 68, 176, S - 136);
  ctx.strokeStyle = '#133B5C';
  ctx.lineWidth = 4;
  ctx.strokeRect(cx - 88, 68, 176, S - 136);

  // 5. Central Atlantis-Sapphire & Alabaster Compass Medallion Under the Court of Honor
  ctx.fillStyle = '#133B5C';
  ctx.beginPath();
  ctx.arc(cx, cy - 48, 196, 0, Math.PI * 2);
  ctx.fill();

  ctx.fillStyle = '#EAE7E0';
  ctx.beginPath();
  ctx.arc(cx, cy - 48, 164, 0, Math.PI * 2);
  ctx.fill();

  ctx.strokeStyle = '#133B5C';
  ctx.lineWidth = 5;
  ctx.beginPath();
  ctx.arc(cx, cy - 48, 112, 0, Math.PI * 2);
  ctx.stroke();

  // 8-Pointed Atlantis Sapphire Star Rose in Center Medallion
  for (let k = 0; k < 8; k++) {
    const ang = (k / 8) * Math.PI * 2;
    ctx.strokeStyle = k % 2 === 0 ? '#133B5C' : 'rgba(19, 59, 92, 0.48)';
    ctx.lineWidth = k % 2 === 0 ? 4.5 : 2.2;
    ctx.beginPath();
    ctx.moveTo(cx + Math.cos(ang) * 26, (cy - 48) + Math.sin(ang) * 26);
    ctx.lineTo(cx + Math.cos(ang) * 158, (cy - 48) + Math.sin(ang) * 158);
    ctx.stroke();
  }

  return configureHdTex(new THREE.CanvasTexture(c), 4);
}

// Verified Tripo 3D local front axis (+X) -> -Math.PI / 2 rotates TV face & "Funki" chest text straight to +Z camera!
const FUNKI_GLB_FRONT_ROTATION_Y = -Math.PI * 0.5;

const LOCAL_FUNKI_GLB_URLS = {
  1:  '/assets/funki_models/funki_lvl_1_rags.glb',
  2:  '/assets/funki_models/funki_lvl_2_basic.glb',
  3:  '/assets/funki_models/funki_lvl_3_helper.glb',
  4:  '/assets/funki_models/funki_lvl_4_novice.glb',
  5:  '/assets/funki_models/funki_lvl_5_apprentice.glb',
  6:  '/assets/funki_models/funki_lvl_6_craftsman.glb',
  7:  '/assets/funki_models/funki_lvl_7_artisan.glb',
  8:  '/assets/funki_models/funki_lvl_8_master.glb',
  9:  '/assets/funki_models/funki_lvl_9_lightbringer.glb',
  10: '/assets/funki_models/funki_lvl_10_god_of_soul.glb'
};

function makeSparkParticleTexture() {
  const S = 64;
  const c = document.createElement('canvas');
  c.width = S;
  c.height = S;
  const ctx = c.getContext('2d');
  const g = ctx.createRadialGradient(S * 0.5, S * 0.5, 1, S * 0.5, S * 0.5, S * 0.48);
  g.addColorStop(0.0, 'rgba(255, 255, 255, 1.0)');
  g.addColorStop(0.28, 'rgba(255, 238, 170, 0.92)');
  g.addColorStop(0.65, 'rgba(212, 175, 55, 0.35)');
  g.addColorStop(1.0, 'rgba(197, 160, 89, 0.0)');
  ctx.fillStyle = g;
  ctx.fillRect(0, 0, S, S);
  const tex = new THREE.CanvasTexture(c);
  tex.needsUpdate = true;
  return tex;
}

// Zero-Dependency Native Binary GLB 2.0 Parser (0 external CDN requests, 0ms local Mini-PC load!)
function parseGlbBinary(arrayBuffer, maxAniso, disposablesList) {
  if (!arrayBuffer || arrayBuffer.byteLength < 20) {
    return Promise.reject(new Error('Ungueltiger GLB-Datenstrom.'));
  }

  const dv = new DataView(arrayBuffer);
  const magic = dv.getUint32(0, true);
  const version = dv.getUint32(4, true);
  if (magic !== 0x46546C67 || version !== 2) {
    return Promise.reject(new Error('Nicht unterstuetztes GLB-2.0-Format.'));
  }

  let jsonObj = null;
  let binOffset = 0;
  let binLength = 0;
  let offset = 12;

  while (offset + 8 <= arrayBuffer.byteLength) {
    const chunkLen = dv.getUint32(offset, true);
    const chunkType = dv.getUint32(offset + 4, true);
    const dataStart = offset + 8;
    if (dataStart + chunkLen > arrayBuffer.byteLength) break;

    if (chunkType === 0x4E4F534A) {
      const jsonBytes = new Uint8Array(arrayBuffer, dataStart, chunkLen);
      const jsonStr = new TextDecoder('utf-8').decode(jsonBytes);
      jsonObj = JSON.parse(jsonStr);
    } else if (chunkType === 0x004E4942) {
      binOffset = dataStart;
      binLength = chunkLen;
    }
    offset = dataStart + chunkLen;
  }

  if (!jsonObj || !binLength) {
    return Promise.reject(new Error('Unvollstaendige GLB-Chunks.'));
  }

  const COMP_SPECS = {
    5120: { Ctor: Int8Array, bytes: 1, read: (v, o) => v.getInt8(o) },
    5121: { Ctor: Uint8Array, bytes: 1, read: (v, o) => v.getUint8(o) },
    5122: { Ctor: Int16Array, bytes: 2, read: (v, o) => v.getInt16(o, true) },
    5123: { Ctor: Uint16Array, bytes: 2, read: (v, o) => v.getUint16(o, true) },
    5125: { Ctor: Uint32Array, bytes: 4, read: (v, o) => v.getUint32(o, true) },
    5126: { Ctor: Float32Array, bytes: 4, read: (v, o) => v.getFloat32(o, true) }
  };
  const TYPE_COUNTS = { SCALAR: 1, VEC2: 2, VEC3: 3, VEC4: 4, MAT4: 16 };

  function readAccessorArray(accIdx) {
    if (accIdx === undefined || accIdx === null || !jsonObj.accessors || !jsonObj.accessors[accIdx]) {
      return null;
    }
    const acc = jsonObj.accessors[accIdx];
    const bv = (jsonObj.bufferViews && jsonObj.bufferViews[acc.bufferView]) ? jsonObj.bufferViews[acc.bufferView] : null;
    const spec = COMP_SPECS[acc.componentType];
    const itemSize = TYPE_COUNTS[acc.type] || 1;
    if (!bv || !spec) return null;

    const count = acc.count || 0;
    const byteStart = binOffset + (bv.byteOffset || 0) + (acc.byteOffset || 0);
    const packedStride = itemSize * spec.bytes;
    const stride = bv.byteStride || packedStride;

    if (stride === packedStride && (byteStart % spec.bytes) === 0) {
      return {
        array: new spec.Ctor(arrayBuffer, byteStart, count * itemSize).slice(),
        itemSize
      };
    }

    const out = new spec.Ctor(count * itemSize);
    for (let i = 0; i < count; i++) {
      const baseOff = byteStart + i * stride;
      for (let c = 0; c < itemSize; c++) {
        out[i * itemSize + c] = spec.read(dv, baseOff + c * spec.bytes);
      }
    }
    return { array: out, itemSize };
  }

  const localTexPromises = {};
  const localMatPromises = {};

  function loadEmbeddedTexture(texIndex, isSrgb) {
    if (texIndex === undefined || texIndex === null || !jsonObj.textures || !jsonObj.textures[texIndex]) {
      return Promise.resolve(null);
    }
    const key = texIndex + (isSrgb ? ':srgb' : ':lin');
    if (localTexPromises[key]) return localTexPromises[key];

    const texDef = jsonObj.textures[texIndex];
    const imgDef = (jsonObj.images && texDef.source !== undefined) ? jsonObj.images[texDef.source] : null;
    if (!imgDef || imgDef.bufferView === undefined || !jsonObj.bufferViews || !jsonObj.bufferViews[imgDef.bufferView]) {
      return Promise.resolve(null);
    }

    const bv = jsonObj.bufferViews[imgDef.bufferView];
    const imgStart = binOffset + (bv.byteOffset || 0);
    const imgBytes = new Uint8Array(arrayBuffer, imgStart, bv.byteLength);
    const blob = new Blob([imgBytes], { type: imgDef.mimeType || 'image/jpeg' });
    const blobUrl = URL.createObjectURL(blob);

    localTexPromises[key] = new Promise((resolve) => {
      const imgEl = new Image();
      imgEl.onload = () => {
        URL.revokeObjectURL(blobUrl);
        const tex = new THREE.Texture(imgEl);
        tex.flipY = false;
        tex.wrapS = THREE.RepeatWrapping;
        tex.wrapT = THREE.RepeatWrapping;
        tex.generateMipmaps = true;
        tex.minFilter = THREE.LinearMipmapLinearFilter;
        tex.magFilter = THREE.LinearFilter;
        tex.anisotropy = Math.min(maxAniso || 4, 4);
        if (isSrgb) {
          tex.colorSpace = THREE.SRGBColorSpace;
        } else if (typeof THREE.NoColorSpace !== 'undefined') {
          tex.colorSpace = THREE.NoColorSpace;
        }
        tex.needsUpdate = true;
        if (disposablesList) disposablesList.push(tex);
        resolve(tex);
      };
      imgEl.onerror = () => {
        URL.revokeObjectURL(blobUrl);
        resolve(null);
      };
      imgEl.src = blobUrl;
    });

    return localTexPromises[key];
  }

  function buildMaterial(matIndex) {
    const key = (matIndex === undefined || matIndex === null) ? 'default' : String(matIndex);
    if (localMatPromises[key]) return localMatPromises[key];

    const matDef = (jsonObj.materials && jsonObj.materials[matIndex]) ? jsonObj.materials[matIndex] : {};
    const pbr = matDef.pbrMetallicRoughness || {};
    const baseIdx = (pbr.baseColorTexture && pbr.baseColorTexture.index !== undefined) ? pbr.baseColorTexture.index : null;
    const mrIdx = (pbr.metallicRoughnessTexture && pbr.metallicRoughnessTexture.index !== undefined) ? pbr.metallicRoughnessTexture.index : null;
    const normIdx = (matDef.normalTexture && matDef.normalTexture.index !== undefined) ? matDef.normalTexture.index : null;
    const emisIdx = (matDef.emissiveTexture && matDef.emissiveTexture.index !== undefined) ? matDef.emissiveTexture.index : null;

    localMatPromises[key] = Promise.all([
      loadEmbeddedTexture(baseIdx, true),
      loadEmbeddedTexture(mrIdx, false),
      loadEmbeddedTexture(normIdx, false),
      loadEmbeddedTexture(emisIdx, true)
    ]).then(([baseTex, mrTex, normTex, emisTex]) => {
      const bcf = Array.isArray(pbr.baseColorFactor) ? pbr.baseColorFactor : [1, 1, 1, 1];
      const rawMetal = (typeof pbr.metallicFactor === 'number') ? pbr.metallicFactor : (mrTex ? 1.0 : 0.16);
      const safeMetalness = mrTex ? clamp(rawMetal * 0.34, 0.14, 0.36) : clamp(rawMetal, 0.12, 0.28);
      const rawRough = (typeof pbr.roughnessFactor === 'number') ? pbr.roughnessFactor : 0.78;
      const safeRoughness = mrTex ? clamp(rawRough * 0.74, 0.32, 0.86) : clamp(rawRough * 0.84, 0.38, 0.88);

      const opts = {
        color: new THREE.Color(bcf[0], bcf[1], bcf[2]),
        metalness: safeMetalness,
        roughness: safeRoughness,
        side: matDef.doubleSided ? THREE.DoubleSide : THREE.FrontSide
      };
      if (baseTex) opts.map = baseTex;
      if (mrTex) {
        opts.roughnessMap = mrTex;
        opts.metalnessMap = mrTex;
      }
      if (normTex) {
        opts.normalMap = normTex;
        const ns = (matDef.normalTexture && typeof matDef.normalTexture.scale === 'number') ? matDef.normalTexture.scale : 0.85;
        opts.normalScale = new THREE.Vector2(ns, ns);
      }
      if (emisTex) {
        opts.emissiveMap = emisTex;
        const ef = Array.isArray(matDef.emissiveFactor) ? matDef.emissiveFactor : [1, 1, 1];
        opts.emissive = new THREE.Color(ef[0], ef[1], ef[2]);
      } else if (Array.isArray(matDef.emissiveFactor)) {
        opts.emissive = new THREE.Color(matDef.emissiveFactor[0], matDef.emissiveFactor[1], matDef.emissiveFactor[2]);
      }
      if (matDef.alphaMode === 'MASK') {
        opts.alphaTest = (typeof matDef.alphaCutoff === 'number') ? matDef.alphaCutoff : 0.5;
      } else if (matDef.alphaMode === 'BLEND') {
        opts.transparent = true;
        opts.opacity = (typeof bcf[3] === 'number') ? bcf[3] : 1.0;
      }

      const mat = new THREE.MeshStandardMaterial(opts);
      if (disposablesList) disposablesList.push(mat);
      return mat;
    });

    return localMatPromises[key];
  }

  function buildNodeAsync(nodeIdx) {
    const nDef = (jsonObj.nodes && jsonObj.nodes[nodeIdx]) ? jsonObj.nodes[nodeIdx] : null;
    if (!nDef) return Promise.resolve(new THREE.Group());

    const nodeObj = new THREE.Group();
    if (Array.isArray(nDef.matrix) && nDef.matrix.length === 16) {
      const m4 = new THREE.Matrix4();
      m4.fromArray(nDef.matrix);
      m4.decompose(nodeObj.position, nodeObj.quaternion, nodeObj.scale);
    } else {
      if (Array.isArray(nDef.translation)) nodeObj.position.fromArray(nDef.translation);
      if (Array.isArray(nDef.rotation)) nodeObj.quaternion.fromArray(nDef.rotation);
      if (Array.isArray(nDef.scale)) nodeObj.scale.fromArray(nDef.scale);
    }

    const primTasks = [];
    if (nDef.mesh !== undefined && jsonObj.meshes && jsonObj.meshes[nDef.mesh]) {
      const prims = jsonObj.meshes[nDef.mesh].primitives || [];
      for (let pi = 0; pi < prims.length; pi++) {
        const prim = prims[pi];
        primTasks.push(buildMaterial(prim.material).then((mat) => {
          const geo = new THREE.BufferGeometry();
          const attrs = prim.attributes || {};
          const posData = readAccessorArray(attrs.POSITION);
          if (!posData) return;
          geo.setAttribute('position', new THREE.BufferAttribute(posData.array, 3));

          const normData = readAccessorArray(attrs.NORMAL);
          if (normData) {
            geo.setAttribute('normal', new THREE.BufferAttribute(normData.array, 3));
          }

          const uvData = readAccessorArray(attrs.TEXCOORD_0);
          if (uvData) {
            geo.setAttribute('uv', new THREE.BufferAttribute(uvData.array, 2));
          }

          const idxData = readAccessorArray(prim.indices);
          if (idxData) {
            geo.setIndex(new THREE.BufferAttribute(idxData.array, 1));
          }

          if (!normData) {
            geo.computeVertexNormals();
          }
          geo.computeBoundingBox();
          geo.computeBoundingSphere();
          if (disposablesList) disposablesList.push(geo);

          nodeObj.add(new THREE.Mesh(geo, mat));
        }));
      }
    }

    const childIndices = Array.isArray(nDef.children) ? nDef.children : [];
    const childTasks = childIndices.map((ci) =>
      buildNodeAsync(ci).then((childGrp) => {
        nodeObj.add(childGrp);
      })
    );

    return Promise.all(primTasks.concat(childTasks)).then(() => nodeObj);
  }

  const sceneIdx = (typeof jsonObj.scene === 'number') ? jsonObj.scene : 0;
  const rootNodes = (jsonObj.scenes && jsonObj.scenes[sceneIdx] && Array.isArray(jsonObj.scenes[sceneIdx].nodes))
    ? jsonObj.scenes[sceneIdx].nodes
    : [0];

  const rootGroup = new THREE.Group();
  return Promise.all(rootNodes.map((rn) =>
    buildNodeAsync(rn).then((child) => {
      rootGroup.add(child);
    })
  )).then(() => ({
    scene: rootGroup,
    animations: Array.isArray(jsonObj.animations) ? jsonObj.animations : []
  }));
}


// 10 Authentic Funki Evolution Levels (1:1 to funki_lvl_1_rags.glb .. funki_lvl_10_god_of_soul.glb)
const FUNKI_LEVEL_SPECS = {
  1:  { level: 1,  title: 'Ursprung',               sitting: true,  eyeMode: 'sad',      mouth: 'sad',      auraHex: 0xC5A059 },
  2:  { level: 2,  title: 'Lehrling',               sitting: false, eyeMode: 'solemn',   mouth: 'solemn',   auraHex: 0xD97706 },
  3:  { level: 3,  title: 'Helfer',                 sitting: false, eyeMode: 'solemn',   mouth: 'solemn',   auraHex: 0xEAB308 },
  4:  { level: 4,  title: 'Kristall-Novize',        sitting: false, eyeMode: 'happy',    mouth: 'laugh',    auraHex: 0x38BDF8 },
  5:  { level: 5,  title: 'Funken-Geselle',         sitting: false, eyeMode: 'confident',mouth: 'grin',     auraHex: 0x10B981 },
  6:  { level: 6,  title: 'Gravur-Handwerker',      sitting: false, eyeMode: 'confident',mouth: 'grin',     auraHex: 0xF43F5E },
  7:  { level: 7,  title: 'Kunsthandwerker',        sitting: false, eyeMode: 'confident',mouth: 'grin',     auraHex: 0xD4AF37 },
  8:  { level: 8,  title: 'Meister',                sitting: false, eyeMode: 'confident',mouth: 'grin',     auraHex: 0xFDE68A },
  9:  { level: 9,  title: 'Lichtbringer',           sitting: false, eyeMode: 'confident',mouth: 'grin',     auraHex: 0x38BDF8 },
  10: { level: 10, title: 'Gott des Seelenfunkens', sitting: false, eyeMode: 'amethyst', mouth: 'grin',     auraHex: 0xA855F7 }
};

const s_funkiFaceTexCache = new Map();
const s_funkiChestTexCache = new Map();

// 512x320 Authentic Funki TV-Screen Face Texture (Big Eyes + Signature Bottom-Right Golden Gears!)
function makeFunkiTvFaceTexture(level) {
  const lvl = clamp(Math.round(Number(level) || 1), 1, 10);
  if (s_funkiFaceTexCache.has(lvl)) return s_funkiFaceTexCache.get(lvl);

  const spec = FUNKI_LEVEL_SPECS[lvl];
  const W = 512, H = 320;
  const c = document.createElement('canvas');
  c.width = W;
  c.height = H;
  const ctx = c.getContext('2d');

  // Matte charcoal CRT screen background
  const bg = ctx.createRadialGradient(W * 0.48, H * 0.46, 20, W * 0.5, H * 0.5, W * 0.62);
  bg.addColorStop(0, '#272930');
  bg.addColorStop(0.68, '#17181D');
  bg.addColorStop(1, '#0D0E12');
  ctx.fillStyle = bg;
  ctx.fillRect(0, 0, W, H);

  // Subtle screen inner shadow border
  ctx.strokeStyle = 'rgba(0,0,0,0.65)';
  ctx.lineWidth = 12;
  ctx.strokeRect(6, 6, W - 12, H - 12);

  // Signature Bottom-Right Exposed Golden Clockwork Gears Recess (present on ALL 10 Funki artworks!)
  ctx.save();
  ctx.beginPath();
  ctx.moveTo(W - 155, H - 10);
  ctx.lineTo(W - 96, H - 56);
  ctx.lineTo(W - 44, H - 96);
  ctx.lineTo(W - 10, H - 108);
  ctx.lineTo(W - 10, H - 10);
  ctx.closePath();
  ctx.fillStyle = '#09090C';
  ctx.fill();
  ctx.strokeStyle = '#9E7B36';
  ctx.lineWidth = 4;
  ctx.stroke();

  const drawGear = (gx, gy, gr, teeth) => {
    ctx.save();
    ctx.translate(gx, gy);
    ctx.fillStyle = '#D4AF37';
    for (let t = 0; t < teeth; t++) {
      ctx.rotate((Math.PI * 2) / teeth);
      ctx.fillRect(-3.5, -gr - 5, 7, 7);
    }
    ctx.beginPath();
    ctx.arc(0, 0, gr, 0, Math.PI * 2);
    ctx.fill();
    ctx.fillStyle = '#111216';
    ctx.beginPath();
    ctx.arc(0, 0, gr * 0.44, 0, Math.PI * 2);
    ctx.fill();
    ctx.strokeStyle = '#FDE68A';
    ctx.lineWidth = 2;
    ctx.stroke();
    ctx.restore();
  };
  drawGear(W - 44, H - 42, 20, 10);
  drawGear(W - 94, H - 34, 16, 8);
  ctx.restore();

  // Draw Big Expressive Funki Eyes
  const drawEye = (ex, ey, isLeft) => {
    ctx.save();
    ctx.translate(ex, ey);

    // Outer recessed eye socket
    ctx.fillStyle = '#090A0D';
    ctx.beginPath();
    ctx.ellipse(0, 0, 68, 58, 0, 0, Math.PI * 2);
    ctx.fill();

    ctx.beginPath();
    ctx.ellipse(0, 0, 62, 52, 0, 0, Math.PI * 2);
    ctx.clip();

    if (spec.eyeMode === 'amethyst') {
      // Level 10: Radiant Glowing Amethyst-Violet God Eyes!
      const eg = ctx.createRadialGradient(0, 4, 4, 0, 4, 56);
      eg.addColorStop(0.0, '#FFFFFF');
      eg.addColorStop(0.28, '#F3E8FF');
      eg.addColorStop(0.62, '#A855F7');
      eg.addColorStop(1.0, '#4C1D95');
      ctx.fillStyle = eg;
      ctx.fillRect(-68, -58, 136, 116);
    } else {
      // Sclera
      ctx.fillStyle = '#E5E7EB';
      ctx.fillRect(-68, -58, 136, 116);

      // Rich Warm Amber-Gold Iris + Deep Pupil
      const ix = isLeft ? 6 : -6;
      const iy = 6;
      const ig = ctx.createRadialGradient(ix, iy + 8, 6, ix, iy, 40);
      ig.addColorStop(0.0, '#FEF08A');
      ig.addColorStop(0.45, '#F59E0B');
      ig.addColorStop(0.85, '#92400E');
      ig.addColorStop(1.0, '#451A03');
      ctx.fillStyle = ig;
      ctx.beginPath();
      ctx.arc(ix, iy, 38, 0, Math.PI * 2);
      ctx.fill();

      ctx.fillStyle = '#09090B';
      ctx.beginPath();
      ctx.arc(ix, iy - 1, 21, 0, Math.PI * 2);
      ctx.fill();

      // Crisp Starlight Catchlights
      ctx.fillStyle = '#FFFFFF';
      ctx.beginPath();
      ctx.arc(ix - 10, iy - 10, 8.5, 0, Math.PI * 2);
      ctx.fill();
      ctx.beginPath();
      ctx.arc(ix + 11, iy + 9, 4.0, 0, Math.PI * 2);
      ctx.fill();
    }

    // Metallic Upper Eyelid Expression
    ctx.fillStyle = lvl <= 3 ? '#3A3228' : '#262830';
    ctx.strokeStyle = '#C5A059';
    ctx.lineWidth = 3;
    ctx.beginPath();
    if (spec.eyeMode === 'sad') {
      // Heavy droopy sad tilt outward
      const tiltY = isLeft ? 16 : -12;
      ctx.moveTo(-70, -60);
      ctx.lineTo(70, -60);
      ctx.lineTo(70, -2 - tiltY);
      ctx.lineTo(-70, -2 + tiltY);
    } else if (spec.eyeMode === 'solemn') {
      ctx.moveTo(-70, -60);
      ctx.lineTo(70, -60);
      ctx.lineTo(70, -14);
      ctx.lineTo(-70, -14);
    } else {
      ctx.moveTo(-70, -60);
      ctx.lineTo(70, -60);
      ctx.lineTo(70, -28);
      ctx.quadraticCurveTo(0, -36, -70, -28);
    }
    ctx.closePath();
    ctx.fill();
    ctx.stroke();

    ctx.restore();
  };

  drawEye(148, 134, true);
  drawEye(358, 134, false);

  // Subtle weathered tear scratch on Level 1
  if (lvl === 1) {
    ctx.strokeStyle = 'rgba(200, 210, 220, 0.25)';
    ctx.lineWidth = 3;
    ctx.beginPath();
    ctx.moveTo(118, 192);
    ctx.lineTo(115, 238);
    ctx.stroke();
  }

  // Mouth Expression
  ctx.save();
  if (spec.mouth === 'sad' || spec.mouth === 'solemn') {
    ctx.strokeStyle = '#090A0D';
    ctx.lineWidth = 9;
    ctx.lineCap = 'round';
    ctx.beginPath();
    const drop = spec.mouth === 'sad' ? 22 : 11;
    ctx.moveTo(208, 246 + drop * 0.4);
    ctx.quadraticCurveTo(256, 232 - drop * 0.5, 304, 246 + drop * 0.3);
    ctx.stroke();
  } else if (spec.mouth === 'laugh') {
    ctx.fillStyle = '#180D0B';
    ctx.beginPath();
    ctx.moveTo(206, 224);
    ctx.quadraticCurveTo(256, 228, 306, 224);
    ctx.quadraticCurveTo(296, 274, 256, 274);
    ctx.quadraticCurveTo(216, 274, 206, 224);
    ctx.fill();

    ctx.fillStyle = '#B4533A';
    ctx.beginPath();
    ctx.ellipse(256, 256, 26, 13, 0, 0, Math.PI * 2);
    ctx.fill();
  } else {
    // Heroic Confident White-Toothed Grin (Lvl 5..10)
    ctx.fillStyle = '#0B0C10';
    ctx.beginPath();
    ctx.moveTo(204, 226);
    ctx.quadraticCurveTo(256, 232, 308, 222);
    ctx.quadraticCurveTo(296, 268, 256, 268);
    ctx.quadraticCurveTo(216, 268, 204, 226);
    ctx.fill();

    ctx.fillStyle = '#F8FAFC';
    ctx.beginPath();
    ctx.moveTo(214, 231);
    ctx.quadraticCurveTo(256, 236, 298, 228);
    ctx.quadraticCurveTo(288, 256, 256, 256);
    ctx.quadraticCurveTo(224, 256, 214, 231);
    ctx.fill();
  }
  ctx.restore();

  const tex = configureHdTex(new THREE.CanvasTexture(c), 4);
  s_funkiFaceTexCache.set(lvl, tex);
  return tex;
}

// 512x256 Authentic "Funki" Chest-Plate Texture per Level
function makeFunkiChestTexture(level) {
  const lvl = clamp(Math.round(Number(level) || 1), 1, 10);
  if (s_funkiChestTexCache.has(lvl)) return s_funkiChestTexCache.get(lvl);

  const W = 512, H = 256;
  const c = document.createElement('canvas');
  c.width = W;
  c.height = H;
  const ctx = c.getContext('2d');

  const plateColors = {
    1: ['#2A2520', '#1B1815'],
    2: ['#3A2D1E', '#221A12'],
    3: ['#433320', '#261D12'],
    4: ['#5D6470', '#383E47'],
    5: ['#6E7582', '#3F4550'],
    6: ['#373D47', '#1E2229'],
    7: ['#7A5D29', '#423114'],
    8: ['#9E782E', '#573F14'],
    9: ['#475569', '#1E293B'],
    10:['#6B4E2E', '#2E1A47']
  };
  const [cTop, cBot] = plateColors[lvl] || plateColors[1];
  const bg = ctx.createLinearGradient(0, 0, 0, H);
  bg.addColorStop(0, cTop);
  bg.addColorStop(1, cBot);
  ctx.fillStyle = bg;
  ctx.fillRect(0, 0, W, H);

  ctx.strokeStyle = lvl === 9 ? '#38BDF8' : (lvl === 10 ? '#C084FC' : '#D4AF37');
  ctx.lineWidth = 10;
  ctx.strokeRect(10, 10, W - 20, H - 20);

  ctx.textAlign = 'center';
  ctx.textBaseline = 'middle';
  ctx.font = '900 104px "Liberation Sans", "Segoe UI", sans-serif';
  ctx.fillStyle = 'rgba(0,0,0,0.82)';
  ctx.fillText('Funki', W * 0.5 + 4, H * 0.54 + 5);

  const tg = ctx.createLinearGradient(0, H * 0.25, 0, H * 0.80);
  tg.addColorStop(0, '#FFFBEB');
  tg.addColorStop(0.45, '#FACC15');
  tg.addColorStop(1, '#CA8A04');
  ctx.fillStyle = tg;
  ctx.fillText('Funki', W * 0.5, H * 0.54);

  const tex = configureHdTex(new THREE.CanvasTexture(c), 4);
  s_funkiChestTexCache.set(lvl, tex);
  return tex;
}

function resolveHeroAuraHex(hero) {
  if (!hero) return 0xC5A059;
  const a = String(hero.aura || '').trim().toLowerCase();
  const r = String(hero.rank_class || '').trim().toUpperCase();
  if (a.includes('cyan') || a.includes('ice')) return 0x06B6D4;
  if (a.includes('emerald') || a.includes('green') || a.includes('smaragd')) return 0x10B981;
  if (a.includes('violet') || a.includes('purple') || a.includes('amethyst')) return 0xA855F7;
  if (a.includes('blue') || a.includes('sapphire')) return 0x3B82F6;
  if (a.includes('ruby') || a.includes('red') || a.includes('crimson')) return 0xEF4444;
  if (a.includes('gold') || a.includes('solar') || r.includes('KLASSE S')) return 0xFDE68A;
  if (r.includes('KLASSE A')) return 0x06B6D4;
  if (r.includes('KLASSE B')) return 0x3B82F6;
  return 0xC5A059;
}

// Crisp Dynamic 24K-Gold Pedestal Name Plaque (Real Hero vs Level Preview vs "Freier Ehrenplatz" Founding Fallback)
function paintHeroPedestalCanvas(c, slotNumber, hero, previewLevel = 0) {
  const W = c.width, H = c.height;
  const ctx = c.getContext('2d');

  const bg = ctx.createRadialGradient(W * 0.5, H * 0.5, 20, W * 0.5, H * 0.5, W * 0.62);
  bg.addColorStop(0, '#141720');
  bg.addColorStop(0.7, '#0a0c12');
  bg.addColorStop(1, '#06070b');
  ctx.fillStyle = bg;
  ctx.fillRect(0, 0, W, H);

  ctx.strokeStyle = '#C5A059';
  ctx.lineWidth = 10;
  ctx.strokeRect(14, 14, W - 28, H - 28);
  ctx.strokeStyle = 'rgba(253, 230, 138, 0.5)';
  ctx.lineWidth = 2.5;
  ctx.strokeRect(30, 30, W - 60, H - 60);

  ctx.textAlign = 'center';
  ctx.textBaseline = 'middle';

  if (previewLevel >= 1 && previewLevel <= 10) {
    const sp = FUNKI_LEVEL_SPECS[previewLevel];
    ctx.fillStyle = '#C5A059';
    ctx.font = '800 38px "Liberation Sans", "Segoe UI", sans-serif';
    ctx.fillText(`✦ FUNKI STUFE ${previewLevel} / 10 ✦`, W * 0.5, H * 0.28, W - 90);

    ctx.fillStyle = 'rgba(197, 160, 89, 0.42)';
    ctx.fillRect(W * 0.20, H * 0.43, W * 0.60, 2.5);

    ctx.fillStyle = '#FDE68A';
    ctx.font = '900 72px "Liberation Sans", "Segoe UI", sans-serif';
    ctx.fillText(sp.title, W * 0.5, H * 0.68, W - 88);
    return;
  }

  const rawName = hero && !hero.is_placeholder && hero.display_name
    ? String(hero.display_name).replace(/\s[\u2013\u2014]\s/g, ' · ').trim()
    : '';
  const isRealHero = rawName.length > 0 && rawName.toLowerCase() !== 'freier ehrenplatz';

  if (isRealHero) {
    const lvl = clamp(Math.round(Number(hero.level) || 1), 1, 999);
    const rkRaw = String(hero.rank_class || 'KLASSE C').replace(/\s[\u2013\u2014]\s/g, ' · ').trim().toUpperCase();
    const rk = rkRaw.startsWith('KLASSE') ? rkRaw : `KLASSE ${rkRaw}`;

    ctx.fillStyle = '#C5A059';
    ctx.font = '800 38px "Liberation Sans", "Segoe UI", sans-serif';
    ctx.fillText(`#${slotNumber} · ${rk} · LVL ${lvl}`, W * 0.5, H * 0.27, W - 90);

    ctx.fillStyle = 'rgba(197, 160, 89, 0.45)';
    ctx.fillRect(W * 0.18, H * 0.42, W * 0.64, 2.5);

    const ng = ctx.createLinearGradient(0, H * 0.46, 0, H * 0.86);
    ng.addColorStop(0, '#FFFDF5');
    ng.addColorStop(0.35, '#FDE68A');
    ng.addColorStop(0.7, '#D4AF37');
    ng.addColorStop(1, '#B88D3B');
    ctx.fillStyle = ng;
    ctx.font = '900 84px "Liberation Sans", "Segoe UI", sans-serif';
    ctx.fillText(rawName, W * 0.5, H * 0.67, W - 88);
  } else {
    ctx.fillStyle = 'rgba(197, 160, 89, 0.82)';
    ctx.font = '800 36px "Liberation Sans", "Segoe UI", sans-serif';
    ctx.fillText(`✦ EHRENPLATZ #${slotNumber} ✦`, W * 0.5, H * 0.28, W - 90);

    ctx.fillStyle = 'rgba(197, 160, 89, 0.32)';
    ctx.fillRect(W * 0.22, H * 0.44, W * 0.56, 2);

    ctx.fillStyle = '#FDE68A';
    ctx.font = '800 68px "Liberation Sans", "Segoe UI", sans-serif';
    ctx.fillText('Freier Ehrenplatz', W * 0.5, H * 0.67, W - 88);
  }
}

// Crisp Dynamic Colonnade Honor Stele Tablet Canvas (for Heroes #6..#29)
function paintHeroSteleCanvas(c, slotNumber, hero) {
  const W = c.width, H = c.height;
  const ctx = c.getContext('2d');

  ctx.fillStyle = '#090b10';
  ctx.fillRect(0, 0, W, H);
  ctx.strokeStyle = '#C5A059';
  ctx.lineWidth = 7;
  ctx.strokeRect(10, 10, W - 20, H - 20);

  ctx.textAlign = 'center';
  ctx.textBaseline = 'middle';

  ctx.fillStyle = '#D4AF37';
  ctx.font = '900 38px "Liberation Sans", sans-serif';
  ctx.fillText('✦', W * 0.5, H * 0.16);

  const cleanName = hero && hero.display_name
    ? String(hero.display_name).replace(/\s[\u2013\u2014]\s/g, ' · ').trim()
    : '';

  if (cleanName) {
    const lvl = Math.max(1, Number(hero.level) || 1);
    const rkRaw = String(hero.rank_class || 'KLASSE C').trim().toUpperCase();
    const rk = rkRaw.startsWith('KLASSE') ? rkRaw : `KLASSE ${rkRaw}`;

    ctx.fillStyle = '#FDE68A';
    ctx.font = '800 42px "Liberation Sans", sans-serif';
    ctx.fillText(cleanName, W * 0.5, H * 0.43, W - 44);

    ctx.fillStyle = '#C5A059';
    ctx.font = '700 28px "Liberation Sans", sans-serif';
    ctx.fillText(`#${slotNumber} · ${rk}`, W * 0.5, H * 0.64, W - 44);
    ctx.fillText(`LVL ${lvl}`, W * 0.5, H * 0.79, W - 44);
  } else {
    ctx.fillStyle = 'rgba(197, 160, 89, 0.85)';
    ctx.font = '700 28px "Liberation Sans", sans-serif';
    ctx.fillText(`#${slotNumber}`, W * 0.5, H * 0.36);

    ctx.fillStyle = '#FDE68A';
    ctx.font = '800 34px "Liberation Sans", sans-serif';
    ctx.fillText('Freier', W * 0.5, H * 0.56, W - 40);
    ctx.fillText('Ehrenplatz', W * 0.5, H * 0.70, W - 40);
  }
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
    this.hallOfHeroesGroup = null;
    this.falconGroup = null;
    this.falconChestGroup = null;
    this.falconHeadGroup = null;
    this.throneClickTargets = [];
    this.heroesClickTargets = [];
    this.departmentClickTargets = [];
    this.departmentBeacons = {};
    this.hoveredDeptKey = null;
    this.activeDepartment = null;
    this.departments = DEPARTMENTS;
    this.hoverChipEl = null;
    this.hoverDotEl = null;
    this.hoverLabelEl = null;
    this.heroPedestalEntries = [];
    this.heroSteleEntries = [];
    this.lastHeroesFetchMs = 0;
    this.heroesFetchInFlight = false;
    this.cachedHeroesList = [];
    this.previewHeroLevel = 0; // 0 = Live Shop Heroes, 1..10 = Interactive Level 1..10 Showcase!

    // RAM Cache Map<level, THREE.Group> so no .glb model is ever downloaded or parsed twice!
    this.glbTemplateCache = new Map();
    this.glbLoadingPromises = new Map();
    this.glbDisposables = [];
    this.pedestalSparkMeta = [];
    this.pedestalSparkPosAttr = null;
    this.sparkSpriteTex = null;

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
    this.viewMode = 'orbit'; // 'orbit' | 'city' | 'heroes' | 'throne' | 'department'
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
    this._buildHallOfHeroes();

    // Bind Smooth Mouse & Touch OrbitControls + Spire Raycast Click
    this._bindControls();
    window.addEventListener('resize', this._boundResize);
    this.resize();

    this.initialized = true;
  }

  _buildMaterials() {
    const mcGoldBright = makeLuxuryMatcap('#D4AF37', '#FFFBEB', '#FDE68A', 38, 0.46);
    const mcGoldBrushed = makeLuxuryMatcap('#B89146', '#FFF3C4', '#D4AF37', 22, 0.32);
    const mcObsidian = makeLuxuryMatcap('#11141C', '#687387', '#C5A059', 30, 0.22);
    const mcAnthracite = makeLuxuryMatcap('#1B202B', '#7C879B', '#9CA3AF', 18, 0.18);
    const mcCrimsonVelvet = makeLuxuryMatcap('#7A0D1B', '#C94A5A', '#D4AF37', 10, 0.24);
    const mcSkin = makeLuxuryMatcap('#DDA988', '#FFF2E6', '#F4C6A8', 14, 0.20);
    const mcHair = makeLuxuryMatcap('#2A1E17', '#7A6252', '#C5A059', 20, 0.18);
    const mcWhiteShirt = makeLuxuryMatcap('#ECEFF4', '#FFFFFF', '#D4AF37', 16, 0.15);
    const mcBronzeDark = makeLuxuryMatcap('#3B3127', '#8F7657', '#B89146', 14, 0.20);
    const mcBurlap = makeLuxuryMatcap('#6E573B', '#AB906E', '#4A3924', 8, 0.14);
    const mcBronzeGold = makeLuxuryMatcap('#8F6A30', '#E8C57B', '#C5A059', 22, 0.30);
    const mcSilverSteel = makeLuxuryMatcap('#8E97A4', '#FFFFFF', '#D4AF37', 34, 0.36);
    const mcGunmetal = makeLuxuryMatcap('#343A44', '#C4CDD8', '#64748B', 32, 0.28);
    const mcIceCrystal = makeLuxuryMatcap('#38BDF8', '#FFFFFF', '#BAE6FD', 42, 0.52);
    const mcAmethyst = makeLuxuryMatcap('#9333EA', '#FFFFFF', '#F5D0FE', 44, 0.56);

    const facadeTex = makeSkyscraperFacadeTexture();
    this.sparkSpriteTex = makeSparkParticleTexture();

    this.mats = {
      goldBright: new THREE.MeshMatcapMaterial({ matcap: mcGoldBright }),
      goldBrushed: new THREE.MeshMatcapMaterial({ matcap: mcGoldBrushed }),
      obsidian: new THREE.MeshMatcapMaterial({ matcap: mcObsidian }),
      anthracite: new THREE.MeshMatcapMaterial({ matcap: mcAnthracite }),
      velvet: new THREE.MeshMatcapMaterial({ matcap: mcCrimsonVelvet }),
      skin: new THREE.MeshMatcapMaterial({ matcap: mcSkin }),
      hair: new THREE.MeshMatcapMaterial({ matcap: mcHair }),
      shirt: new THREE.MeshMatcapMaterial({ matcap: mcWhiteShirt }),
      funkiBronzeDark: new THREE.MeshMatcapMaterial({ matcap: mcBronzeDark }),
      funkiBurlap: new THREE.MeshMatcapMaterial({ matcap: mcBurlap }),
      funkiBronzeGold: new THREE.MeshMatcapMaterial({ matcap: mcBronzeGold }),
      funkiSilverSteel: new THREE.MeshMatcapMaterial({ matcap: mcSilverSteel }),
      funkiGunmetal: new THREE.MeshMatcapMaterial({ matcap: mcGunmetal }),
      funkiIceCrystal: new THREE.MeshMatcapMaterial({ matcap: mcIceCrystal }),
      funkiAmethyst: new THREE.MeshMatcapMaterial({ matcap: mcAmethyst }),
      plasmaBlue: new THREE.MeshBasicMaterial({ color: 0x38BDF8 }),
      potionGreen: new THREE.MeshBasicMaterial({ color: 0x4ADE80 }),
      potionRed: new THREE.MeshBasicMaterial({ color: 0xEF4444 }),
      amethystGlow: new THREE.MeshBasicMaterial({ color: 0xD8B4FE }),
      skyscraperFacade: new THREE.MeshBasicMaterial({ map: facadeTex }),
      beaconGold: new THREE.MeshBasicMaterial({ color: 0xFFE89E }),
      deptVeins: new THREE.MeshBasicMaterial({ color: 0xFFFFFF }),
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
  // PHASE 1A: DEEP FUNKEN-UNIVERSUM STAR COSMOS & ATMOSPHERIC SKY DOME
  // ==========================================================================
  _buildCosmosAndSky() {
    this.spaceGroup = new THREE.Group();
    this.scene.add(this.spaceGroup);

    // Sky Dome blending seamlessly from Deep Space (#010206) to Golden-Sapphire Horizon
    this.skyUniforms = {
      uSpaceBlend: { value: 1.0 },
      uSunDir: { value: new THREE.Vector3(-0.38, 0.24, -0.89).normalize() }
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
        varying vec3 vWorldDir;
        void main() {
          vec3 d = normalize(vWorldDir);
          float h = clamp(d.y, -0.2, 1.0);

          // Deep Space Cosmos background
          vec3 spaceCol = mix(vec3(0.012, 0.018, 0.038), vec3(0.003, 0.005, 0.012), clamp(h * 1.4 + 0.2, 0.0, 1.0));
          float nebula = pow(max(0.0, dot(d, normalize(vec3(0.45, 0.35, -0.82)))), 3.0);
          spaceCol += vec3(0.075, 0.055, 0.022) * nebula;

          // Atmospheric Ocean-Metropolis Sky (Rich Sapphire Zenith -> Golden Horizon)
          vec3 zenithCol = vec3(0.018, 0.048, 0.105);
          vec3 midSkyCol = vec3(0.055, 0.145, 0.265);
          vec3 horizonCol = vec3(0.24, 0.26, 0.33);
          float tSky = pow(1.0 - clamp(h, 0.0, 1.0), 2.6);
          vec3 citySky = mix(zenithCol, midSkyCol, smoothstep(0.85, 0.18, h));
          citySky = mix(citySky, horizonCol, tSky);

          // Golden Sun Halo above the Ocean Horizon
          float sunDot = max(0.0, dot(d, uSunDir));
          citySky += vec3(0.85, 0.66, 0.32) * pow(sunDot, 7.0) * 0.48;
          citySky += vec3(1.00, 0.94, 0.76) * pow(sunDot, 96.0) * 0.95;

          vec3 col = mix(citySky, spaceCol, uSpaceBlend);
          gl_FragColor = vec4(col, 1.0);
        }
      `
    });
    const skyDome = new THREE.Mesh(new THREE.SphereGeometry(18500, 40, 28), skyMat);
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
      uSunDir: { value: new THREE.Vector3(-0.38, 0.24, -0.89).normalize() }
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
          vec3 deepWater = vec3(0.015, 0.062, 0.125);
          vec3 lagoonWater = vec3(0.032, 0.145, 0.235);
          vec3 waterBase = mix(deepWater, lagoonWater, lagoonMask * 0.72);

          // True Fresnel Sky Reflection
          float fresnel = pow(1.0 - max(0.0, dot(N, V)), 4.0);
          vec3 reflDir = reflect(-V, N);
          float skyGrad = clamp(reflDir.y * 1.6, 0.0, 1.0);
          vec3 skyRefl = mix(vec3(0.28, 0.27, 0.29), vec3(0.045, 0.125, 0.245), skyGrad);
          vec3 col = mix(waterBase, skyRefl, clamp(0.16 + fresnel * 0.78, 0.0, 0.92));

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
          col = mix(col, vec3(0.16, 0.19, 0.26), horizonFog);

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
  // PHASE 2B: STARGATE-ATLANTIS WATER METROPOLIS (7 PIERS + RING BRIDGES + INSTANCED TOWERS)
  // ==========================================================================
  _buildAtlantisMetropolis() {
    this.cityGroup = new THREE.Group();
    this.scene.add(this.cityGroup);

    const rng = mulberry32(20260707);

    // 1. Central Metropolis Core Island (Multi-Tiered Obsidian & 24K-Gold Seawall Terraces)
    const coreRadii = [
      { r: 148, h: 3.2, y: 1.6, segs: 14, mat: this.mats.anthracite },
      { r: 149.2, h: 0.9, y: 3.3, segs: 14, mat: this.mats.goldBrushed },
      { r: 132, h: 3.4, y: 4.9, segs: 14, mat: this.mats.obsidian },
      { r: 133.0, h: 0.85, y: 6.7, segs: 14, mat: this.mats.goldBright },
      { r: 96,  h: 2.8, y: 8.0, segs: 14, mat: this.mats.obsidian }
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

    const wingDeckGeo = new THREE.ExtrudeGeometry(wingShape, {
      depth: 4.2,
      bevelEnabled: true,
      bevelThickness: 0.8,
      bevelSize: 1.2,
      bevelSegments: 1
    });
    wingDeckGeo.rotateX(Math.PI / 2);

    const wingGoldTrimGeo = new THREE.ExtrudeGeometry(wingShape, {
      depth: 0.75,
      bevelEnabled: true,
      bevelThickness: 0.35,
      bevelSize: 1.8,
      bevelSegments: 1
    });
    wingGoldTrimGeo.rotateX(Math.PI / 2);

    // Collect Instance Transforms for Hundreds of Skyscrapers, Crowns, Pylons & Department Veins!
    const towerTransforms = [];
    const goldFrameTransforms = [];
    const spireTransforms = [];
    const deptVeinItems = []; // { matrix, color }

    const pushMat = (arr, x, y, z, sx, sy, sz, rotY = 0) => {
      this.dummyObj.position.set(x, y, z);
      this.dummyObj.rotation.set(0, rotY, 0);
      this.dummyObj.scale.set(sx, sy, sz);
      this.dummyObj.updateMatrix();
      arr.push(this.dummyObj.matrix.clone());
    };

    const pushDeptVein = (x, y, z, sx, sy, sz, rotY, hexCol) => {
      this.dummyObj.position.set(x, y, z);
      this.dummyObj.rotation.set(0, rotY, 0);
      this.dummyObj.scale.set(sx, sy, sz);
      this.dummyObj.updateMatrix();
      deptVeinItems.push({ matrix: this.dummyObj.matrix.clone(), color: new THREE.Color(hexCol) });
    };

    // Helper to add a multi-tier stepped Atlantis skyscraper at world (wx, baseY, wz)
    const addSteppedSkyscraper = (wx, baseY, wz, baseW, baseD, totalH, rotY, deptHex = null) => {
      // Main lower shaft
      const h1 = totalH * 0.64;
      pushMat(towerTransforms, wx, baseY + h1 * 0.5, wz, baseW, h1, baseD, rotY);
      pushMat(goldFrameTransforms, wx, baseY + h1 + 0.6, wz, baseW * 1.06, 1.2, baseD * 1.06, rotY);

      // Stepped mid setback
      const h2 = totalH * 0.26;
      const w2 = baseW * 0.74;
      const d2 = baseD * 0.74;
      pushMat(towerTransforms, wx, baseY + h1 + h2 * 0.5, wz, w2, h2, d2, rotY);
      pushMat(goldFrameTransforms, wx, baseY + h1 + h2 + 0.5, wz, w2 * 1.08, 1.0, d2 * 1.08, rotY);

      // Upper penthouse crown + 24K-Gold Spire Needle
      const h3 = totalH * 0.12;
      const w3 = baseW * 0.48;
      pushMat(goldFrameTransforms, wx, baseY + h1 + h2 + h3 * 0.5, wz, w3, h3, w3, rotY);
      const spireH = Math.max(8, totalH * 0.20);
      pushMat(spireTransforms, wx, baseY + totalH + spireH * 0.48, wz, Math.max(0.7, baseW * 0.08), spireH, Math.max(0.7, baseW * 0.08), rotY);

      // ~10% Subtle Department Vertical Optical Light Vein on Pier Towers
      if (deptHex) {
        const vx = wx + Math.sin(rotY) * (baseD * 0.51);
        const vz = wz + Math.cos(rotY) * (baseD * 0.51);
        pushDeptVein(vx, baseY + (h1 + h2) * 0.5, vz, 0.55, (h1 + h2) * 0.92, 0.55, rotY, deptHex);
      }
    };

    // 2. Populate Dense Central Core Skyscraper Cluster (~92 stepped towers rising toward Supertower!)
    const coreRings = [
      { count: 14, rad: 46,  minH: 132, maxH: 196, w: 12.5 },
      { count: 22, rad: 74,  minH: 92,  maxH: 152, w: 11.8 },
      { count: 28, rad: 102, minH: 62,  maxH: 116, w: 11.0 },
      { count: 30, rad: 126, minH: 40,  maxH: 82,  w: 10.2 }
    ];
    coreRings.forEach((ring, rIdx) => {
      for (let i = 0; i < ring.count; i++) {
        const ang = (i / ring.count) * Math.PI * 2 + rIdx * 0.21 + (rng() - 0.5) * 0.08;
        // Leave narrow grand sightline corridor on front +Z axis so MEIN SEELENFUNKE tower gleams unobstructed
        const frontAlign = Math.cos(ang - Math.PI * 0.5);
        const rad = ring.rad + (rng() - 0.5) * 6.5;
        const wx = Math.cos(ang) * rad;
        const wz = Math.sin(ang) * rad;
        let h = lerp(ring.minH, ring.maxH, rng());
        if (frontAlign > 0.92 && rIdx >= 1) h *= 0.68;
        const w = ring.w * (0.84 + rng() * 0.34);
        const d = ring.w * (0.84 + rng() * 0.34);
        const deptAccent = (i % 5 === 0) ? DEPARTMENTS[i % 7].hex : null;
        addSteppedSkyscraper(wx, 8.2, wz, w, d, h, -ang + Math.PI * 0.5, deptAccent);
      }
    });

    // 3. Build the 7 Radial Atlantis Department Piers + Inner/Outer Water-Lagoon Ring Bridges!
    const causewayGeo = new THREE.BoxGeometry(30, 3.4, 142);
    const causewayGoldTrimGeo = new THREE.BoxGeometry(32.2, 0.85, 142);
    const landingPadOctGeo = new THREE.CylinderGeometry(34, 37, 4.2, 8);
    const landingPadRingGeo = new THREE.TorusGeometry(24, 1.15, 8, 8);
    landingPadRingGeo.rotateX(Math.PI / 2);

    this.pierAngles = [];

    for (let k = 0; k < 7; k++) {
      const dept = DEPARTMENTS[k];
      // Pier 0 angled dramatically toward front-right like Alina's Stargate-Atlantis photo!
      const theta = (k / 7) * Math.PI * 2 + 0.36;
      this.pierAngles.push(theta);

      const pierGrp = new THREE.Group();
      // Local +Z of pierGrp points along radial angle theta: (sin(rotY), 0, cos(rotY))
      const rotY = Math.atan2(Math.cos(theta), Math.sin(theta));
      pierGrp.rotation.y = rotY;
      this.cityGroup.add(pierGrp);

      // 3a. Inner Radial Causeway over Water (r = 142 .. 284m)
      const cwDeck = new THREE.Mesh(causewayGeo, this.mats.obsidian);
      cwDeck.position.set(0, 3.8, 213);
      pierGrp.add(cwDeck);

      const cwTrim = new THREE.Mesh(causewayGoldTrimGeo, this.mats.goldBrushed);
      cwTrim.position.set(0, 5.4, 213);
      pierGrp.add(cwTrim);

      // Sea Pylons under Causeway
      for (let pz = 168; pz <= 264; pz += 32) {
        const wx = Math.sin(rotY) * pz;
        const wz = Math.cos(rotY) * pz;
        pushMat(goldFrameTransforms, wx, 2.0, wz, 26, 4.4, 5.5, rotY);
      }

      // Subtle ~10% Department Optical Veins running along Pier Causeway
      for (const side of [-12.5, 12.5]) {
        const lx = side * Math.cos(rotY) + 213 * Math.sin(rotY);
        const lz = -side * Math.sin(rotY) + 213 * Math.cos(rotY);
        pushDeptVein(lx, 5.95, lz, 0.85, 0.45, 136, rotY, dept.hex);
      }

      // 3b. Broad Chamfered Atlantis Outer Pier Wing (r = 275 .. 504m)
      const wingDeck = new THREE.Mesh(wingDeckGeo, this.mats.anthracite);
      wingDeck.position.set(0, 4.8, 0);
      pierGrp.add(wingDeck);

      const wingGold = new THREE.Mesh(wingGoldTrimGeo, this.mats.goldBright);
      wingGold.position.set(0, 5.52, 0);
      pierGrp.add(wingGold);

      // Raised Central Pier Citadel Podium
      const pod = new THREE.Mesh(new THREE.BoxGeometry(58, 3.2, 128), this.mats.obsidian);
      pod.position.set(0, 6.4, 382);
      pierGrp.add(pod);

      // Twin Sleek Black-Gold Hydro-Cruisers berthed in the Atlantis Pier Slip (just like the reference photo!)
      for (const slipX of [-13, 13]) {
        const hull = new THREE.Mesh(new THREE.ConeGeometry(4.2, 34, 6), this.mats.obsidian);
        hull.rotation.x = Math.PI * 0.5;
        hull.scale.set(1.0, 1.0, 0.42);
        hull.position.set(slipX, 1.6, 478);
        pierGrp.add(hull);

        const trim = new THREE.Mesh(new THREE.BoxGeometry(2.2, 1.1, 24), this.mats.goldBright);
        trim.position.set(slipX, 2.6, 476);
        pierGrp.add(trim);
      }

      // 3c. Iconic Pier-Tip Octagonal Atlantis Starport / Landing Pad (r = 538m)
      const linkBridge = new THREE.Mesh(new THREE.BoxGeometry(18, 2.8, 28), this.mats.obsidian);
      linkBridge.position.set(0, 3.8, 508);
      pierGrp.add(linkBridge);

      const pad = new THREE.Mesh(landingPadOctGeo, this.mats.obsidian);
      pad.position.set(0, 2.6, 540);
      pierGrp.add(pad);

      const padGoldRing = new THREE.Mesh(landingPadRingGeo, this.mats.goldBright);
      padGoldRing.position.set(0, 4.85, 540);
      pierGrp.add(padGoldRing);

      // Subtle Department Ring Accent inside Octagonal Landing Pad
      const wxPad = Math.sin(rotY) * 540;
      const wzPad = Math.cos(rotY) * 540;
      pushDeptVein(wxPad, 4.92, wzPad, 16, 0.35, 16, rotY, dept.hex);

      // 3d-click. Invisible Department Pier Raycast Proxy + Matte Accent Ring Beacons (Zero artificial bloom!)
      const deptClickProxy = new THREE.Mesh(
        new THREE.BoxGeometry(68, 52, 290),
        new THREE.MeshBasicMaterial({ visible: false })
      );
      deptClickProxy.position.set(0, 26, 360);
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
        depthWrite: false
      });
      const podiumBeaconGeo = new THREE.RingGeometry(24.2, 26.8, 32);
      podiumBeaconGeo.rotateX(-Math.PI * 0.5);
      const podiumBeacon = new THREE.Mesh(podiumBeaconGeo, beaconMat);
      podiumBeacon.position.set(0, 8.12, 362);
      pierGrp.add(podiumBeacon);

      const padBeaconGeo = new THREE.RingGeometry(17.2, 20.4, 8);
      padBeaconGeo.rotateX(-Math.PI * 0.5);
      const padBeacon = new THREE.Mesh(padBeaconGeo, beaconMat);
      padBeacon.position.set(0, 4.98, 540);
      pierGrp.add(padBeacon);

      this.departmentBeacons[dept.key] = {
        mat: beaconMat,
        podiumBeacon,
        padBeacon
      };

      // 3d. 22 Stepped Skyscrapers on this Department Pier Wing!
      const pierTowerSpots = [
        // Central Pier Spine High-Rises
        [0, 332, 114, 13.5], [-18, 348, 92, 11.2], [18, 348, 96, 11.2],
        [0, 376, 104, 12.8], [-22, 392, 78, 10.5], [22, 392, 82, 10.5],
        [0, 418, 72, 11.0],
        // Left & Right Stepped Atlantis Wing Towers
        [-44, 342, 66, 10.2], [44, 342, 68, 10.2],
        [-52, 374, 58, 9.6],  [52, 374, 62, 9.6],
        [-48, 408, 52, 9.4],  [48, 408, 54, 9.4],
        [-38, 438, 44, 8.8],  [38, 438, 46, 8.8],
        [-58, 426, 36, 8.4],  [58, 426, 38, 8.4],
        [-32, 314, 54, 9.2],  [32, 314, 56, 9.2],
        [-26, 462, 34, 8.2],  [26, 462, 36, 8.2],
        [0,   302, 64, 10.4]
      ];

      pierTowerSpots.forEach(([lx, lz, hBase, wBase], idx) => {
        const wx = lx * Math.cos(rotY) + lz * Math.sin(rotY);
        const wz = -lx * Math.sin(rotY) + lz * Math.cos(rotY);
        const h = hBase * (0.90 + rng() * 0.22);
        const w = wBase * (0.92 + rng() * 0.18);
        const baseY = Math.abs(lx) < 26 && lz > 318 && lz < 444 ? 7.8 : 5.2;
        addSteppedSkyscraper(wx, baseY, wz, w, w * 0.94, h, rotY, idx % 2 === 0 ? dept.hex : null);
      });
    }

    // 4. Outer & Mid Ring Bridges Connecting Adjacent Piers Over Shimmering Water Lagoons!
    const ringRadii = [
      { r: 252, width: 14.5, y: 4.2 },
      { r: 412, width: 16.5, y: 4.6 }
    ];

    ringRadii.forEach((rb, rIdx) => {
      for (let k = 0; k < 7; k++) {
        const a1 = this.pierAngles[k];
        const a2 = this.pierAngles[(k + 1) % 7];
        const p1 = new THREE.Vector2(Math.cos(a1) * rb.r, Math.sin(a1) * rb.r);
        const p2 = new THREE.Vector2(Math.cos(a2) * rb.r, Math.sin(a2) * rb.r);
        const dx = p2.x - p1.x;
        const dz = p2.y - p1.y;
        const spanLen = Math.hypot(dx, dz);
        const midX = (p1.x + p2.x) * 0.5;
        const midZ = (p1.y + p2.y) * 0.5;
        const bridgeRotY = Math.atan2(dx, dz);

        // Obsidian Bridge Deck
        const deck = new THREE.Mesh(new THREE.BoxGeometry(rb.width, 2.2, spanLen), this.mats.obsidian);
        deck.position.set(midX, rb.y, midZ);
        deck.rotation.y = bridgeRotY;
        this.cityGroup.add(deck);

        // 24K-Gold Suspension Side Rails
        const goldRail = new THREE.Mesh(new THREE.BoxGeometry(rb.width + 1.8, 0.65, spanLen), this.mats.goldBrushed);
        goldRail.position.set(midX, rb.y + 1.25, midZ);
        goldRail.rotation.y = bridgeRotY;
        this.cityGroup.add(goldRail);

        // Bridge Support Pylons in the Water Basin
        for (let s = 0.25; s <= 0.76; s += 0.25) {
          const px = lerp(p1.x, p2.x, s);
          const pz = lerp(p1.y, p2.y, s);
          pushMat(goldFrameTransforms, px, 2.2, pz, rb.width * 0.85, 4.8, 4.2, bridgeRotY);
        }

        // Subtle Department Light Vein on Ring Bridge
        pushDeptVein(midX, rb.y + 1.68, midZ, 0.75, 0.35, spanLen * 0.88, bridgeRotY, DEPARTMENTS[(k + rIdx) % 7].hex);
      }
    });

    // 5. Commit All Batched InstancedMeshes (Ultra-Low Draw Calls = Locked 60 FPS!)
    const unitBox = new THREE.BoxGeometry(1, 1, 1);
    const unitSpire = new THREE.ConeGeometry(0.5, 1, 6);

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

    // 1. Grand Octagonal Base Podium (Y = 8 .. 26m)
    const basePodium = new THREE.Mesh(new THREE.CylinderGeometry(30, 36, 18, 8), this.mats.obsidian);
    basePodium.position.y = 17;
    this.supertowerGroup.add(basePodium);

    const baseGoldCollar = new THREE.Mesh(new THREE.CylinderGeometry(31.2, 31.2, 2.2, 8), this.mats.goldBright);
    baseGoldCollar.position.y = 26.2;
    this.supertowerGroup.add(baseGoldCollar);

    // 2. Spiraling Burj-Khalifa Setback Tiers (Y = 26 .. 218m) in 24K Gold & Obsidian Curtain-Wall
    const coreShaft = new THREE.Mesh(new THREE.CylinderGeometry(13.2, 20.5, 194, 8), this.mats.skyscraperFacade);
    coreShaft.position.y = 123;
    this.supertowerGroup.add(coreShaft);

    // 8 Soaring 24K-Gold Vertical Exoskeleton Ribs up the Entire Supertower
    for (let i = 0; i < 8; i++) {
      const ang = (i / 8) * Math.PI * 2 + Math.PI / 8;
      const rib = new THREE.Mesh(new THREE.BoxGeometry(1.75, 232, 2.8), this.mats.goldBright);
      const rMid = 16.8;
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

      const wingShaft = new THREE.Mesh(new THREE.CylinderGeometry(rad * 0.84, rad, h - 24, 6), this.mats.skyscraperFacade);
      wingShaft.position.set(Math.cos(ang) * dist, 24 + (h - 24) * 0.5, Math.sin(ang) * dist);
      this.supertowerGroup.add(wingShaft);

      // Gleaming 24K-Gold Setback Terrace Crown + Mini Spire on Each Buttress
      const cap = new THREE.Mesh(new THREE.CylinderGeometry(rad * 0.92, rad * 0.96, 2.4, 6), this.mats.goldBright);
      cap.position.set(Math.cos(ang) * dist, h + 1.0, Math.sin(ang) * dist);
      this.supertowerGroup.add(cap);

      const fin = new THREE.Mesh(new THREE.ConeGeometry(1.1, 16, 6), this.mats.goldBright);
      fin.position.set(Math.cos(ang) * dist, h + 9.5, Math.sin(ang) * dist);
      this.supertowerGroup.add(fin);
    }

    // Horizontal Golden Ring Bands along Main Shaft
    [54, 88, 122, 156, 190, 218].forEach((ry, idx) => {
      const rr = lerp(20.6, 14.4, idx / 5);
      const band = new THREE.Mesh(new THREE.CylinderGeometry(rr, rr, 1.6, 8), this.mats.goldBright);
      band.position.y = ry;
      this.supertowerGroup.add(band);
    });

    // 3. Avengers Stark-Tower Cantilevered Upper Sky-Citadel & "MEIN SEELENFUNKE" Monumental Plaque (Y = 218 .. 258m)
    const starkLower = new THREE.Mesh(new THREE.CylinderGeometry(18.6, 14.2, 18, 8), this.mats.obsidian);
    starkLower.position.y = 227;
    this.supertowerGroup.add(starkLower);

    // Cantilevered Avengers-Style Angled Sky-Deck Collar jutting boldly outward
    const starkDeck = new THREE.Mesh(new THREE.BoxGeometry(44, 10.5, 32), this.mats.obsidian);
    starkDeck.position.set(0, 238.5, 2.2);
    this.supertowerGroup.add(starkDeck);

    const starkGoldFrameTop = new THREE.Mesh(new THREE.BoxGeometry(45.6, 1.5, 33.6), this.mats.goldBright);
    starkGoldFrameTop.position.set(0, 244.2, 2.2);
    this.supertowerGroup.add(starkGoldFrameTop);

    const starkGoldFrameBot = new THREE.Mesh(new THREE.BoxGeometry(45.6, 1.5, 33.6), this.mats.goldBright);
    starkGoldFrameBot.position.set(0, 232.8, 2.2);
    this.supertowerGroup.add(starkGoldFrameBot);

    // Diagonal Stark-Tower 24K-Gold Structural Struts
    for (const sx of [-18.5, 18.5]) {
      const strut = new THREE.Mesh(new THREE.BoxGeometry(2.0, 26, 2.4), this.mats.goldBright);
      strut.position.set(sx, 224, 11.5);
      strut.rotation.z = sx > 0 ? 0.24 : -0.24;
      strut.rotation.x = -0.22;
      this.supertowerGroup.add(strut);
    }

    // High-Resolution Monumental "MEIN SEELENFUNKE" Gold Facade Signage (Front +Z & Back -Z!)
    const signTex = makeMeinSeelenfunkePlaqueTexture();
    const signMat = new THREE.MeshBasicMaterial({ map: signTex });
    const signGeo = new THREE.PlaneGeometry(42.5, 8.0);

    const frontSign = new THREE.Mesh(signGeo, signMat);
    frontSign.position.set(0, 238.5, 18.35);
    this.supertowerGroup.add(frontSign);

    const backSign = new THREE.Mesh(signGeo, signMat);
    backSign.position.set(0, 238.5, -13.95);
    backSign.rotation.y = Math.PI;
    this.supertowerGroup.add(backSign);

    // Neck Transition Pedestal Supporting the Crown Penthouse Throne Room (Y = 245 .. 258m)
    const neckPedestal = new THREE.Mesh(new THREE.CylinderGeometry(14.2, 16.8, 13.0, 16), this.mats.obsidian);
    neckPedestal.position.y = 251.2;
    this.supertowerGroup.add(neckPedestal);

    const penthouseRimBase = new THREE.Mesh(new THREE.CylinderGeometry(14.6, 14.2, 1.2, 32), this.mats.goldBright);
    penthouseRimBase.position.y = THRONE_FLOOR_Y - 0.55;
    this.supertowerGroup.add(penthouseRimBase);

    // 4. Clickable Crown Spire & Golden Filigree Cupola Above Penthouse (Y = 266.2 .. 328.0m)
    const cupolaRing = new THREE.Mesh(new THREE.CylinderGeometry(11.8, 13.8, 1.4, 32), this.mats.goldBright);
    cupolaRing.position.y = THRONE_FLOOR_Y + 8.4;
    this.supertowerGroup.add(cupolaRing);
    this.throneClickTargets.push(cupolaRing);

    // Open Filigree Golden Ribbed Dome over Throne Room
    for (let i = 0; i < 8; i++) {
      const ang = (i / 8) * Math.PI;
      const arch = new THREE.Mesh(new THREE.TorusGeometry(11.6, 0.36, 8, 32, Math.PI), this.mats.goldBright);
      arch.position.y = THRONE_FLOOR_Y + 8.5;
      arch.rotation.y = ang;
      this.supertowerGroup.add(arch);
    }

    // Telescopic Burj-Khalifa 24K-Gold Spire Needle Rising to 328m!
    const spireBase = new THREE.Mesh(new THREE.CylinderGeometry(3.2, 6.4, 14, 16), this.mats.goldBright);
    spireBase.position.y = THRONE_FLOOR_Y + 24.5;
    this.supertowerGroup.add(spireBase);
    this.throneClickTargets.push(spireBase);

    const spireNeedle = new THREE.Mesh(new THREE.ConeGeometry(2.8, 42, 16), this.mats.goldBright);
    spireNeedle.position.y = THRONE_FLOOR_Y + 51.0;
    this.supertowerGroup.add(spireNeedle);
    this.throneClickTargets.push(spireNeedle);

    const apexStar = new THREE.Mesh(new THREE.OctahedronGeometry(2.2, 0), this.mats.beaconGold);
    apexStar.position.y = TOWER_APEX_Y;
    this.supertowerGroup.add(apexStar);
    this.throneClickTargets.push(apexStar);

    // Invisible Generous Click Proxy Sphere around Crown Penthouse & Spire for Effortless Raycast Click!
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
  // REAL COMPRESSED TRIPO 3D FUNKI (.GLB) LOADER, RAM CACHE & 3-POINT LIGHTING
  // ==========================================================================
  _buildPantheonLighting() {
    // Attached directly inside hallOfHeroesGroup so +Z_local shines straight onto all 5 front-facing Funki .glb heroes!
    const hemiLight = new THREE.HemisphereLight(0xFFFDF5, 0x4A5878, 2.05);
    hemiLight.position.set(0, 34.0, 0);
    this.hallOfHeroesGroup.add(hemiLight);

    const ambLight = new THREE.AmbientLight(0xFFF9EE, 0.92);
    this.hallOfHeroesGroup.add(ambLight);

    const keyLight = new THREE.DirectionalLight(0xFFF0CC, 2.45);
    keyLight.position.set(8.5, 22.0, 24.0);
    keyLight.target.position.set(0, 3.8, -5.0);
    this.hallOfHeroesGroup.add(keyLight);
    this.hallOfHeroesGroup.add(keyLight.target);

    const fillLight = new THREE.DirectionalLight(0xFFE4B5, 1.15);
    fillLight.position.set(-10.5, 14.0, 21.0);
    fillLight.target.position.set(0, 3.5, -4.5);
    this.hallOfHeroesGroup.add(fillLight);
    this.hallOfHeroesGroup.add(fillLight.target);

    const rimLight = new THREE.DirectionalLight(0xBAE6FD, 0.95);
    rimLight.position.set(-14.0, 17.0, -20.0);
    rimLight.target.position.set(0, 4.0, -4.0);
    this.hallOfHeroesGroup.add(rimLight);
    this.hallOfHeroesGroup.add(rimLight.target);
  }

  _loadFunkiGlbTemplate(rawLevel) {
    const lvl = clamp(Math.round(Number(rawLevel) || 1), 1, 10);
    if (this.glbTemplateCache.has(lvl)) {
      return Promise.resolve(this.glbTemplateCache.get(lvl));
    }
    if (this.glbLoadingPromises.has(lvl)) {
      return this.glbLoadingPromises.get(lvl);
    }

    const url = LOCAL_FUNKI_GLB_URLS[lvl] || LOCAL_FUNKI_GLB_URLS[1];
    const p = fetch(url, { method: 'GET', credentials: 'same-origin' })
      .then((resp) => {
        if (!resp.ok) {
          throw new Error('GLB HTTP ' + resp.status);
        }
        return resp.arrayBuffer();
      })
      .then((buf) => parseGlbBinary(buf, MAX_ANISO, this.glbDisposables))
      .then((parsed) => {
        const sceneRoot = parsed.scene;
        sceneRoot.updateMatrixWorld(true);
        const bbox = new THREE.Box3().setFromObject(sceneRoot);
        const rawH = Math.max(0.001, bbox.max.y - bbox.min.y);
        const centerX = (bbox.min.x + bbox.max.x) * 0.5;
        const centerZ = (bbox.min.z + bbox.max.z) * 0.5;
        const minY = bbox.min.y;

        const targetH = (lvl === 1) ? 1.62 : 2.08;
        const normScale = targetH / rawH;
        sceneRoot.scale.setScalar(normScale);
        // Align boots/base flush on pedestal surface (bbox.min.y -> y = 0.000) and center X/Z
        sceneRoot.position.set(-centerX * normScale, -minY * normScale, -centerZ * normScale);

        // Verified Tripo local front axis (+X) -> -Math.PI * 0.5 (-90°) faces +Z straight to the camera!
        const frontOriented = new THREE.Group();
        frontOriented.rotation.y = FUNKI_GLB_FRONT_ROTATION_Y;
        frontOriented.add(sceneRoot);

        const templateGroup = new THREE.Group();
        templateGroup.add(frontOriented);
        templateGroup.userData = {
          level: lvl,
          hasEmbeddedAnimations: !!(parsed.animations && parsed.animations.length > 0)
        };

        this.glbTemplateCache.set(lvl, templateGroup);
        this.glbLoadingPromises.delete(lvl);
        return templateGroup;
      })
      .catch((err) => {
        this.glbLoadingPromises.delete(lvl);
        throw err;
      });

    this.glbLoadingPromises.set(lvl, p);
    return p;
  }

  _warmPreloadAllFunkiGlbs() {
    // Stagger background RAM-cache warm-up of all 10 local .glb models so "✨ Stufen" switches in 0.0ms!
    for (let lvl = 1; lvl <= 10; lvl++) {
      setTimeout(() => {
        this._loadFunkiGlbTemplate(lvl).catch(() => {});
      }, lvl * 65);
    }
  }

  buildFunki3DByLevel(level, statueScale = 1.0, slotSeed = 1) {
    const lvl = clamp(Math.round(Number(level) || 1), 1, 10);
    const spec = FUNKI_LEVEL_SPECS[lvl];
    const root = new THREE.Group();
    root.scale.setScalar(statueScale);

    const auraMat = new THREE.MeshBasicMaterial({ color: spec.auraHex });
    const auraOuter = new THREE.Mesh(new THREE.TorusGeometry(0.96, 0.036, 10, 40), auraMat);
    auraOuter.rotation.x = Math.PI * 0.5;
    auraOuter.position.y = 0.036;
    root.add(auraOuter);

    const auraInnerMat = new THREE.MeshBasicMaterial({
      color: 0xFDE68A,
      side: THREE.DoubleSide,
      transparent: true,
      opacity: 0.78
    });
    const auraInner = new THREE.Mesh(new THREE.RingGeometry(0.58, 0.68, 8), auraInnerMat);
    auraInner.rotation.x = -Math.PI * 0.5;
    auraInner.position.y = 0.028;
    root.add(auraInner);

    let divineOuterRing = null;
    if (lvl === 10) {
      divineOuterRing = new THREE.Mesh(new THREE.TorusGeometry(1.32, 0.026, 8, 40), this.mats.goldBright);
      divineOuterRing.rotation.y = Math.PI * 0.25;
      divineOuterRing.position.y = 1.18;
      root.add(divineOuterRing);
    }

    const motionRig = new THREE.Group();
    root.add(motionRig);

    const funkiRecord = {
      root,
      motionRig,
      floatGroup: motionRig,
      haloRing: auraOuter,
      auraOuter,
      auraInner,
      divineOuterRing,
      auraMat,
      level: lvl,
      sitting: spec.sitting,
      baseFloatY: 0,
      phase: slotSeed * 1.17,
      disposed: false
    };

    if (this.glbTemplateCache.has(lvl)) {
      motionRig.add(this.glbTemplateCache.get(lvl).clone(true));
    } else {
      this._loadFunkiGlbTemplate(lvl)
        .then((templateGroup) => {
          if (funkiRecord.disposed) return;
          motionRig.add(templateGroup.clone(true));
        })
        .catch(() => {
          if (funkiRecord.disposed) return;
          const fallback = this._buildProceduralFallbackFunki(lvl, 1.0, slotSeed);
          motionRig.add(fallback.root);
        });
    }

    return funkiRecord;
  }

  // Procedural Fallback (used only if local .glb file were ever unreachable)
  _buildProceduralFallbackFunki(level, statueScale = 1.0, slotSeed = 1) {
    const lvl = clamp(Math.round(Number(level) || 1), 1, 10);
    const spec = FUNKI_LEVEL_SPECS[lvl];
    const root = new THREE.Group();
    root.scale.setScalar(statueScale);

    const auraMat = new THREE.MeshBasicMaterial({ color: spec.auraHex });
    const armorMat = lvl === 1 ? this.mats.funkiBronzeDark
      : (lvl === 2 ? this.mats.funkiBronzeGold
      : (lvl === 3 ? this.mats.funkiBronzeGold
      : (lvl === 4 || lvl === 5 ? this.mats.funkiSilverSteel
      : (lvl === 6 ? this.mats.funkiGunmetal
      : (lvl === 7 ? this.mats.goldBrushed
      : (lvl === 8 ? this.mats.goldBright
      : (lvl === 9 ? this.mats.funkiSilverSteel
      : this.mats.goldBright)))))));

    const trimMat = lvl === 6 ? this.mats.funkiSilverSteel
      : (lvl === 9 ? this.mats.funkiIceCrystal
      : (lvl === 10 ? this.mats.funkiAmethyst : this.mats.goldBright));

    // Pedestal Top Aura Ring (+ Double Divine Aura on Level 10!)
    const baseAuraRing = new THREE.Mesh(new THREE.TorusGeometry(1.06, 0.045, 10, 40), auraMat);
    baseAuraRing.rotation.x = Math.PI * 0.5;
    baseAuraRing.position.y = 0.05;
    root.add(baseAuraRing);

    const floatGroup = new THREE.Group();
    floatGroup.position.y = spec.sitting ? 0.04 : 0.10;
    root.add(floatGroup);

    const haloRing = new THREE.Mesh(
      new THREE.TorusGeometry(lvl === 10 ? 1.12 : 0.82, lvl >= 8 ? 0.032 : 0.022, 8, 36),
      auraMat
    );
    haloRing.rotation.x = Math.PI * 0.36;
    haloRing.position.y = spec.sitting ? 0.92 : 1.25;
    haloRing.visible = lvl >= 2;
    floatGroup.add(haloRing);

    let divineOuterRing = null;
    if (lvl === 10) {
      divineOuterRing = new THREE.Mesh(new THREE.TorusGeometry(1.36, 0.030, 8, 40), this.mats.goldBright);
      divineOuterRing.rotation.y = Math.PI * 0.25;
      divineOuterRing.position.y = 1.32;
      floatGroup.add(divineOuterRing);
    }

    // ------------------------------------------------------------------------
    // 1. LEGS & ARMORED BOOTS (Seated on Lvl 1 vs Heroic Wide Stance Lvl 2..10)
    // ------------------------------------------------------------------------
    if (spec.sitting) {
      // Level 1 ("Ursprung / Rags"): Sitting on pedestal with rag-wrapped legs stretched forward!
      for (const side of [-1, 1]) {
        const sx = side * 0.24;
        const thigh = new THREE.Mesh(new THREE.CapsuleGeometry(0.14, 0.34, 6, 10), this.mats.funkiBurlap);
        thigh.rotation.x = Math.PI * 0.46;
        thigh.rotation.z = side * -0.14;
        thigh.position.set(sx, 0.20, 0.24);
        floatGroup.add(thigh);

        const kneeCap = new THREE.Mesh(new THREE.SphereGeometry(0.11, 10, 8), this.mats.funkiBronzeGold);
        kneeCap.position.set(sx * 1.04, 0.31, 0.18);
        floatGroup.add(kneeCap);

        const boot = new THREE.Mesh(new RoundedBoxGeometry(0.27, 0.22, 0.36, 2, 0.06), this.mats.funkiBronzeDark);
        boot.position.set(sx * 1.14, 0.14, 0.50);
        boot.rotation.y = side * -0.18;
        floatGroup.add(boot);
      }
    } else {
      // Levels 2..10: Sturdy Wide-Stanced Chibi-Knight Legs, Knee-Guards & Heavy Boots!
      for (const side of [-1, 1]) {
        const sx = side * 0.27;
        const leg = new THREE.Mesh(new THREE.CylinderGeometry(0.155, 0.175, 0.54, 14), armorMat);
        leg.position.set(sx, 0.42, 0);
        leg.rotation.z = side * -0.09;
        floatGroup.add(leg);

        const knee = new THREE.Mesh(new THREE.SphereGeometry(0.135, 12, 10), trimMat);
        knee.scale.set(1.0, 0.92, 0.72);
        knee.position.set(sx * 1.04, 0.44, 0.14);
        floatGroup.add(knee);

        const boot = new THREE.Mesh(new RoundedBoxGeometry(0.32, 0.22, 0.44, 3, 0.06), armorMat);
        boot.position.set(sx * 1.10, 0.12, 0.08);
        boot.rotation.y = side * -0.12;
        floatGroup.add(boot);

        const bootSole = new THREE.Mesh(new RoundedBoxGeometry(0.34, 0.06, 0.46, 2, 0.02), this.mats.obsidian);
        bootSole.position.set(sx * 1.10, 0.03, 0.08);
        bootSole.rotation.y = side * -0.12;
        floatGroup.add(bootSole);

        // Boot Toe Spikes / Crystal Shards on Levels 6..10!
        if (lvl >= 6) {
          const spikeMat = lvl === 9 ? this.mats.funkiIceCrystal : (lvl === 10 ? this.mats.funkiAmethyst : trimMat);
          for (const bx of [-0.08, 0, 0.08]) {
            const bSpike = new THREE.Mesh(new THREE.ConeGeometry(0.038, 0.13, 5), spikeMat);
            bSpike.position.set(sx * 1.10 + bx, 0.23, 0.24);
            bSpike.rotation.x = 0.42;
            floatGroup.add(bSpike);
          }
        }
      }
    }

    // ------------------------------------------------------------------------
    // 2. TORSO, "Funki" BREASTPLATE, UTILITY BELT, POUCHES, FLASKS & CAPE
    // ------------------------------------------------------------------------
    const torsoBaseY = spec.sitting ? 0.56 : 0.88;
    const torsoGrp = new THREE.Group();
    torsoGrp.position.set(0, torsoBaseY, 0);
    if (spec.sitting) torsoGrp.rotation.x = 0.08;
    floatGroup.add(torsoGrp);

    const torsoCore = new THREE.Mesh(new RoundedBoxGeometry(0.68, 0.58, 0.44, 3, 0.08), armorMat);
    torsoGrp.add(torsoCore);

    // Front "Funki" Chest Plate
    const chestPlate = new THREE.Mesh(
      new THREE.PlaneGeometry(0.48, 0.25),
      new THREE.MeshBasicMaterial({ map: makeFunkiChestTexture(lvl) })
    );
    chestPlate.position.set(0, 0.06, 0.226);
    torsoGrp.add(chestPlate);

    // Level 8: White Star-Sparks Glinting on Royal-Gold Chest!
    if (lvl === 8) {
      for (const [gx, gy] of [[-0.22, 0.18], [0.20, 0.16], [0.16, 0.04]]) {
        const starGem = new THREE.Mesh(new THREE.OctahedronGeometry(0.042, 0), this.mats.shirt);
        starGem.position.set(gx, gy, 0.24);
        torsoGrp.add(starGem);
      }
    }

    // Neck Scarf / Burlap Mantle
    const scarfMat = lvl <= 5 ? this.mats.funkiBurlap : (lvl <= 8 ? this.mats.obsidian : trimMat);
    const scarf = new THREE.Mesh(new THREE.TorusGeometry(0.28, 0.085, 8, 20), scarfMat);
    scarf.rotation.x = Math.PI * 0.5;
    scarf.position.set(0, 0.28, 0.02);
    torsoGrp.add(scarf);

    // Flowing Tattered Cape (Levels 2..10)
    if (lvl >= 2) {
      const capeMat = lvl <= 5 ? this.mats.funkiBurlap
        : (lvl === 6 ? this.mats.funkiGunmetal
        : (lvl <= 8 ? this.mats.funkiBronzeDark
        : (lvl === 9 ? this.mats.anthracite : this.mats.funkiAmethyst)));
      const cape = new THREE.Mesh(
        new THREE.ConeGeometry(0.64, 1.08, 12, 1, true, Math.PI * 0.62, Math.PI * 1.76),
        capeMat
      );
      cape.position.set(0.06, -0.22, -0.18);
      cape.rotation.x = 0.22;
      cape.rotation.z = -0.10;
      torsoGrp.add(cape);
    }

    // Utility Belt + Buckle + Side Pouches + Alchemy Potion Vials
    const belt = new THREE.Mesh(new RoundedBoxGeometry(0.73, 0.13, 0.48, 2, 0.03), lvl <= 5 ? this.mats.funkiBurlap : this.mats.obsidian);
    belt.position.set(0, -0.22, 0);
    torsoGrp.add(belt);

    const buckle = new THREE.Mesh(new RoundedBoxGeometry(0.15, 0.14, 0.05, 2, 0.02), this.mats.goldBright);
    buckle.position.set(0, -0.22, 0.245);
    torsoGrp.add(buckle);

    if (lvl >= 2) {
      for (const px of [-0.22, 0.22]) {
        const pouch = new THREE.Mesh(new RoundedBoxGeometry(0.13, 0.15, 0.09, 2, 0.02), lvl <= 5 ? this.mats.funkiBurlap : armorMat);
        pouch.position.set(px, -0.23, 0.23);
        torsoGrp.add(pouch);
      }

      // Right-hip glowing cyan vial (Lvl 3..9)
      if (lvl >= 3 && lvl <= 9) {
        const blueVial = new THREE.Mesh(new THREE.SphereGeometry(0.062, 10, 8), this.mats.plasmaBlue);
        blueVial.position.set(-0.24, -0.34, 0.22);
        torsoGrp.add(blueVial);
      }

      // Levels 6..8: Iconic Green & Ruby-Red Alchemy Potion Vials on Left Hip!
      if (lvl >= 6 && lvl <= 8) {
        const greenVial = new THREE.Mesh(new THREE.SphereGeometry(0.062, 10, 8), this.mats.potionGreen);
        greenVial.position.set(0.16, -0.35, 0.23);
        torsoGrp.add(greenVial);

        const redVial = new THREE.Mesh(new THREE.SphereGeometry(0.058, 10, 8), this.mats.potionRed);
        redVial.position.set(0.27, -0.35, 0.21);
        torsoGrp.add(redVial);
      } else if (lvl === 9 || lvl === 10) {
        const orbMat = lvl === 9 ? this.mats.plasmaBlue : this.mats.amethystGlow;
        for (const ox of [-0.22, 0.16, 0.28]) {
          const cOrb = new THREE.Mesh(new THREE.SphereGeometry(0.062, 10, 8), orbMat);
          cOrb.position.set(ox, -0.34, 0.23);
          torsoGrp.add(cOrb);
        }
      }
    }

    // ------------------------------------------------------------------------
    // 3. PAULDRONS, ARMS, GAUNTLETS & HANDHELD ARTIFACTS (FLOWER / PLASMA / BLADES)
    // ------------------------------------------------------------------------
    for (const side of [-1, 1]) {
      // side = -1 is viewer's Left (Funki's Right Arm!); side = +1 is viewer's Right (Funki's Left Arm!)
      const sx = side * 0.42;

      if (lvl >= 2) {
        const pauldron = new THREE.Mesh(new THREE.SphereGeometry(0.19, 14, 12), armorMat);
        pauldron.scale.set(1.12, 0.86, 1.06);
        pauldron.position.set(sx, 0.20, 0.02);
        torsoGrp.add(pauldron);

        // Lvl 2..5: Glowing Blue Shoulder Energy Core / Sapphire Gems
        if ((lvl === 2 || lvl === 3) && side === 1) {
          const core = new THREE.Mesh(new THREE.SphereGeometry(0.055, 10, 8), this.mats.plasmaBlue);
          core.position.set(sx + 0.04, 0.22, 0.16);
          torsoGrp.add(core);
        } else if (lvl === 4 || lvl === 5) {
          const gem = new THREE.Mesh(new THREE.SphereGeometry(0.048, 10, 8), this.mats.plasmaBlue);
          gem.position.set(sx * 0.84, 0.16, 0.18);
          torsoGrp.add(gem);
        }

        // Lvl 5..8: Spiked Shoulder Pauldrons!
        if (lvl >= 5 && lvl <= 8) {
          const spikeCount = lvl >= 8 ? 5 : 3;
          for (let s = 0; s < spikeCount; s++) {
            const ang = lerp(-0.5, 0.65, s / Math.max(1, spikeCount - 1));
            const spk = new THREE.Mesh(new THREE.ConeGeometry(0.042, lvl >= 8 ? 0.22 : 0.15, 5), trimMat);
            spk.position.set(sx + side * Math.cos(ang) * 0.12, 0.34 + Math.sin(ang) * 0.04, (s - 1) * 0.05);
            spk.rotation.z = side * -0.48;
            torsoGrp.add(spk);
          }
        }

        // Lvl 9 & 10: Erupting Ice-Crystal / Amethyst-Crystal Shoulder Clusters!
        if (lvl >= 9) {
          const cMat = lvl === 9 ? this.mats.funkiIceCrystal : this.mats.funkiAmethyst;
          for (let c = 0; c < 5; c++) {
            const shard = new THREE.Mesh(new THREE.ConeGeometry(0.065, 0.34 + (c % 2) * 0.12, 5), cMat);
            shard.position.set(sx + side * (0.08 + c * 0.03), 0.34 + (c % 2) * 0.06, -0.08 + c * 0.045);
            shard.rotation.z = side * (-0.38 - c * 0.09);
            shard.rotation.x = (c - 2) * 0.12;
            torsoGrp.add(shard);
          }
        }
      } else {
        // Lvl 1 Tattered Burlap Shoulder Shawl
        const rag = new THREE.Mesh(new THREE.SphereGeometry(0.17, 10, 8), this.mats.funkiBurlap);
        rag.scale.set(1.1, 0.65, 1.0);
        rag.position.set(sx * 0.92, 0.18, 0.04);
        torsoGrp.add(rag);
      }

      // Arm & Gauntlet
      const arm = new THREE.Mesh(new THREE.CapsuleGeometry(0.105, 0.28, 6, 10), lvl === 1 ? this.mats.funkiBurlap : armorMat);
      if (lvl === 1 && side === -1) {
        // Right arm raised slightly forward holding the drooping wilted golden flower!
        arm.position.set(-0.42, 0.02, 0.18);
        arm.rotation.x = -0.62;
        arm.rotation.z = 0.22;
      } else {
        arm.position.set(sx * 1.06, -0.06, side === -1 ? 0.10 : 0.04);
        arm.rotation.z = side * -0.24;
        arm.rotation.x = side === -1 ? -0.36 : -0.14;
      }
      torsoGrp.add(arm);

      const fistPos = (lvl === 1 && side === -1)
        ? new THREE.Vector3(-0.44, -0.06, 0.36)
        : new THREE.Vector3(sx * 1.18, -0.24, side === -1 ? 0.22 : 0.10);

      const fist = new THREE.Mesh(new THREE.SphereGeometry(0.11, 10, 10), armorMat);
      fist.position.copy(fistPos);
      torsoGrp.add(fist);

      // Level 1: Drooping Wilted Golden Flower in Right Hand!
      if (lvl === 1 && side === -1) {
        const stem = new THREE.Mesh(new THREE.CylinderGeometry(0.014, 0.014, 0.42, 8), this.mats.goldBrushed);
        stem.position.set(-0.46, 0.06, 0.40);
        stem.rotation.z = 0.28;
        torsoGrp.add(stem);

        const crook = new THREE.Mesh(new THREE.TorusGeometry(0.085, 0.014, 6, 14, Math.PI), this.mats.goldBrushed);
        crook.position.set(-0.60, 0.25, 0.42);
        crook.rotation.z = 0.15;
        torsoGrp.add(crook);

        const blossom = new THREE.Mesh(new THREE.ConeGeometry(0.085, 0.14, 7), this.mats.goldBright);
        blossom.position.set(-0.68, 0.18, 0.42);
        blossom.rotation.z = 0.35;
        torsoGrp.add(blossom);
      }

      // Levels 2..9: Crackling Cyan-Blue Plasma Claw Tool in Right Hand!
      if (lvl >= 2 && lvl <= 9 && side === -1) {
        const claw = new THREE.Mesh(new THREE.TorusGeometry(0.11, 0.032, 8, 16, Math.PI * 1.35), trimMat);
        claw.position.set(fistPos.x - 0.06, fistPos.y - 0.02, fistPos.z + 0.08);
        claw.rotation.y = 0.5;
        torsoGrp.add(claw);

        const plasmaCore = new THREE.Mesh(new THREE.OctahedronGeometry(0.095, 1), this.mats.plasmaBlue);
        plasmaCore.position.set(fistPos.x - 0.08, fistPos.y - 0.02, fistPos.z + 0.10);
        torsoGrp.add(plasmaCore);
      }

      // Level 10: Twin Colossal Glowing Amethyst-Crystal Forearm Energy Blades on BOTH Arms!
      if (lvl === 10) {
        const blade = new THREE.Mesh(new THREE.ConeGeometry(0.17, 0.96, 4), this.mats.funkiAmethyst);
        blade.scale.set(0.45, 1.0, 1.25);
        blade.position.set(sx * 1.48, -0.52, 0.12);
        blade.rotation.z = side * 2.58;
        blade.rotation.x = 0.14;
        torsoGrp.add(blade);

        const bladeCore = new THREE.Mesh(new THREE.ConeGeometry(0.10, 0.72, 4), this.mats.amethystGlow);
        bladeCore.position.copy(blade.position);
        bladeCore.rotation.copy(blade.rotation);
        torsoGrp.add(bladeCore);
      }
    }

    // ------------------------------------------------------------------------
    // 4. BACK WEAPONS (GREATSWORD / SHIELD / BATTLE SPEAR / CROSSED X-SPEARS)
    // ------------------------------------------------------------------------
    if (lvl >= 3 && lvl <= 6) {
      // Diagonal Greatsword strapped across the back (Hilt jutting above right/viewer-right shoulder!)
      const swordGrp = new THREE.Group();
      swordGrp.position.set(0.14, 0.18, -0.26);
      swordGrp.rotation.z = -0.46;
      torsoGrp.add(swordGrp);

      const bladeW = lvl >= 4 ? 0.34 : 0.22;
      const bladeMat = lvl === 6 ? this.mats.funkiGunmetal : this.mats.funkiSilverSteel;
      const blade = new THREE.Mesh(new THREE.BoxGeometry(bladeW, 1.26, 0.05), bladeMat);
      blade.position.y = -0.18;
      swordGrp.add(blade);

      if (lvl === 4 || lvl === 5) {
        // Ornate Gold-Filigree Shield/Scabbard Frame on Lvl 4 & 5!
        const shieldRim = new THREE.Mesh(new THREE.BoxGeometry(0.46, 0.92, 0.065), this.mats.goldBright);
        shieldRim.position.y = -0.05;
        swordGrp.add(shieldRim);
      }

      const guard = new THREE.Mesh(new THREE.BoxGeometry(bladeW + 0.20, 0.08, 0.08), lvl === 6 ? this.mats.funkiGunmetal : this.mats.goldBright);
      guard.position.y = 0.46;
      swordGrp.add(guard);

      const grip = new THREE.Mesh(new THREE.CylinderGeometry(0.038, 0.038, 0.30, 10), this.mats.funkiBurlap);
      grip.position.y = 0.64;
      swordGrp.add(grip);

      const pommel = new THREE.Mesh(new THREE.CylinderGeometry(0.062, 0.050, 0.07, 10), lvl === 6 ? this.mats.funkiGunmetal : this.mats.goldBright);
      pommel.position.y = 0.81;
      swordGrp.add(pommel);
    } else if (lvl === 7 || lvl === 8) {
      // Long Diagonal Battle Spear (Silver Tip on Lvl 7, 24K Royal-Gold Dragon Spear on Lvl 8!)
      const spearGrp = new THREE.Group();
      spearGrp.position.set(0.10, 0.22, -0.26);
      spearGrp.rotation.z = -0.52;
      torsoGrp.add(spearGrp);

      const shaft = new THREE.Mesh(new THREE.CylinderGeometry(0.034, 0.030, 1.85, 10), this.mats.funkiBronzeDark);
      shaft.position.y = 0.18;
      spearGrp.add(shaft);

      const collar = new THREE.Mesh(new THREE.CylinderGeometry(0.058, 0.042, 0.14, 10), this.mats.goldBright);
      collar.position.y = 1.08;
      spearGrp.add(collar);

      const tipMat = lvl === 8 ? this.mats.goldBright : this.mats.funkiSilverSteel;
      const spearTip = new THREE.Mesh(new THREE.ConeGeometry(0.105, 0.44, 4), tipMat);
      spearTip.scale.set(1.0, 1.0, 0.45);
      spearTip.position.y = 1.36;
      spearGrp.add(spearTip);
    } else if (lvl === 9 || lvl === 10) {
      // Twin Colossal Crossed Crystal Spears / God-Lances ("X"-Shape behind both shoulders!)
      const lanceMat = lvl === 9 ? this.mats.funkiIceCrystal : this.mats.funkiAmethyst;
      const shaftMat = lvl === 9 ? this.mats.funkiGunmetal : this.mats.goldBrushed;
      for (const side of [-1, 1]) {
        const xLance = new THREE.Group();
        xLance.position.set(0, 0.18, -0.28);
        xLance.rotation.z = side * -0.54;
        torsoGrp.add(xLance);

        const shaft = new THREE.Mesh(new THREE.CylinderGeometry(0.036, 0.030, 2.15, 10), shaftMat);
        shaft.position.y = 0.16;
        xLance.add(shaft);

        const guard = new THREE.Mesh(new THREE.OctahedronGeometry(0.11, 0), lvl === 10 ? this.mats.goldBright : this.mats.funkiSilverSteel);
        guard.position.y = 1.18;
        xLance.add(guard);

        const crystalHead = new THREE.Mesh(new THREE.ConeGeometry(0.145, lvl === 10 ? 0.64 : 0.52, 5), lanceMat);
        crystalHead.scale.set(1.0, 1.0, 0.55);
        crystalHead.position.y = lvl === 10 ? 1.54 : 1.46;
        xLance.add(crystalHead);
      }
    }

    // ------------------------------------------------------------------------
    // 5. SIGNATURE FUNKI TV-SCREEN HEAD, KNIGHT VISOR, HORNS & CRYSTAL CROWN!
    // ------------------------------------------------------------------------
    const headGrp = new THREE.Group();
    headGrp.position.set(0, 0.66, 0.02);
    headGrp.rotation.z = spec.sitting ? 0.14 : (lvl <= 5 ? 0.06 : 0.02);
    torsoGrp.add(headGrp);

    const headCasingMat = lvl <= 3 ? this.mats.funkiBronzeGold
      : (lvl === 4 || lvl === 5 || lvl === 9 ? this.mats.funkiSilverSteel
      : (lvl === 6 ? this.mats.funkiGunmetal : this.mats.goldBright));

    const tvHead = new THREE.Mesh(new RoundedBoxGeometry(0.94, 0.60, 0.48, 3, 0.08), headCasingMat);
    headGrp.add(tvHead);

    // Raised Gold/Metal Screen Inner Bezel
    const tvBezel = new THREE.Mesh(new RoundedBoxGeometry(0.86, 0.52, 0.05, 2, 0.04), lvl === 6 ? this.mats.funkiGunmetal : this.mats.goldBright);
    tvBezel.position.set(0, 0, 0.228);
    headGrp.add(tvBezel);

    // 512x320 HD Funki Face Screen (with Signature Bottom-Right Golden Gears!)
    const faceScreen = new THREE.Mesh(
      new THREE.PlaneGeometry(0.79, 0.45),
      new THREE.MeshBasicMaterial({ map: makeFunkiTvFaceTexture(lvl) })
    );
    faceScreen.position.set(0, 0, 0.256);
    headGrp.add(faceScreen);

    // Twin Side Ear-Caps
    for (const ex of [-0.49, 0.49]) {
      const ear = new THREE.Mesh(new THREE.CylinderGeometry(0.13, 0.13, 0.08, 14), headCasingMat);
      ear.rotation.z = Math.PI * 0.5;
      ear.position.set(ex, 0.0, 0.0);
      headGrp.add(ear);
    }

    // Top Cap Knob (Lvl 1..5) + Rag Forehead Strip (Lvl 1..3)
    if (lvl <= 5) {
      const topKnob = new THREE.Mesh(new THREE.CylinderGeometry(0.14, 0.16, 0.13, 16), this.mats.obsidian);
      topKnob.position.set(-0.04, 0.35, 0);
      headGrp.add(topKnob);

      const knobBand = new THREE.Mesh(new THREE.CylinderGeometry(0.155, 0.165, 0.038, 16), this.mats.goldBright);
      knobBand.position.set(-0.04, 0.31, 0);
      headGrp.add(knobBand);
    }

    if (lvl <= 3) {
      // Tattered burlap rag draped over top-left forehead of TV screen!
      const browRag = new THREE.Mesh(new RoundedBoxGeometry(0.46, 0.14, 0.52, 2, 0.04), this.mats.funkiBurlap);
      browRag.position.set(-0.16, 0.27, 0.02);
      browRag.rotation.z = 0.08;
      headGrp.add(browRag);
    }

    // Levels 4..10: Sculpted Knight Visor Crest Helmet Over the TV-Head!
    if (lvl >= 4) {
      const visorHelm = new THREE.Mesh(new RoundedBoxGeometry(0.98, 0.22, 0.54, 3, 0.05), headCasingMat);
      visorHelm.position.set(0, 0.25, 0.02);
      headGrp.add(visorHelm);

      const noseCrest = new THREE.Mesh(new THREE.ConeGeometry(0.12, 0.24, 4), trimMat);
      noseCrest.rotation.z = Math.PI;
      noseCrest.position.set(0, 0.20, 0.28);
      headGrp.add(noseCrest);

      if (lvl === 4 || lvl === 5) {
        const browGem = new THREE.Mesh(new THREE.OctahedronGeometry(0.068, 0), this.mats.beaconGold);
        browGem.position.set(0, 0.24, 0.30);
        headGrp.add(browGem);
      }

      if (lvl === 5) {
        for (const hx of [-0.36, -0.22, 0.22, 0.36]) {
          const hSpk = new THREE.Mesh(new THREE.ConeGeometry(0.038, 0.12, 5), this.mats.goldBright);
          hSpk.position.set(hx, 0.39, 0.06);
          headGrp.add(hSpk);
        }
      }

      // Levels 6..10: Curved Bull Horns / Golden Dragon Horns / Ice-Crystal Horns / Amethyst God-Spire!
      if (lvl >= 6) {
        const hornMat = lvl === 6 ? this.mats.funkiGunmetal
          : (lvl === 7 ? this.mats.goldBrushed
          : (lvl === 8 ? this.mats.goldBright
          : (lvl === 9 ? this.mats.funkiIceCrystal : this.mats.funkiAmethyst)));

        const hornH = lvl >= 8 ? 0.54 : 0.40;
        for (const side of [-1, 1]) {
          const horn = new THREE.Mesh(new THREE.ConeGeometry(0.095, hornH, 8), hornMat);
          horn.position.set(side * 0.44, 0.44, 0.04);
          horn.rotation.z = side * -0.26;
          horn.rotation.x = 0.12;
          headGrp.add(horn);

          if (lvl >= 8) {
            // Secondary jagged crown sub-horn
            const subHorn = new THREE.Mesh(new THREE.ConeGeometry(0.062, hornH * 0.62, 6), this.mats.goldBright);
            subHorn.position.set(side * 0.26, 0.42, 0.14);
            subHorn.rotation.z = side * -0.14;
            headGrp.add(subHorn);
          }
        }

        // Level 10 ("Gott des Seelenfunkens"): Towering Central Jagged Amethyst-Crystal Crown Spire!
        if (lvl === 10) {
          const godSpire = new THREE.Mesh(new THREE.ConeGeometry(0.135, 0.78, 5), this.mats.funkiAmethyst);
          godSpire.position.set(0, 0.66, 0.12);
          headGrp.add(godSpire);

          const godSpireCore = new THREE.Mesh(new THREE.ConeGeometry(0.08, 0.56, 5), this.mats.amethystGlow);
          godSpireCore.position.set(0, 0.62, 0.14);
          headGrp.add(godSpireCore);
        }
      }
    }

    return {
      root,
      floatGroup,
      haloRing,
      divineOuterRing,
      auraMat,
      level: lvl,
      sitting: spec.sitting,
      baseFloatY: spec.sitting ? 0.04 : 0.10,
      phase: slotSeed * 1.15
    };
  }

  _buildHallOfHeroes() {
    // Positioned at the Southern/Foreground Grand Pier (Pier 1, r = 598m),
    // with unobstructed view straight back (-Z_local) to the MEIN SEELENFUNKE Supertower & (+Z_local) out to the Open Ocean!
    const pierTheta = this.pierAngles[1];
    const pierRotY = Math.atan2(Math.cos(pierTheta), Math.sin(pierTheta));
    const hallRadius = 598.0;

    this.hallOfHeroesGroup = new THREE.Group();
    this.hallOfHeroesGroup.position.set(
      Math.sin(pierRotY) * hallRadius,
      5.0,
      Math.cos(pierRotY) * hallRadius
    );
    this.hallOfHeroesGroup.rotation.y = pierRotY;
    this.cityGroup.add(this.hallOfHeroesGroup);

    // 3-Point Pantheon Lighting (HemisphereLight + AmbientLight + Warm Front KeyLight + Sapphire RimLight)
    this._buildPantheonLighting();

    // 1. Grand Ehrenpromenade Causeway Connecting Pier 1 Starport Plaza (r=540) to the Pantheon (r=598)
    const promBridge = new THREE.Mesh(new THREE.BoxGeometry(22.0, 3.2, 42.0), this.mats.obsidian);
    promBridge.position.set(0, -1.2, -39.0);
    this.hallOfHeroesGroup.add(promBridge);

    const promGoldRails = new THREE.Mesh(new THREE.BoxGeometry(23.6, 0.65, 42.0), this.mats.goldBright);
    promGoldRails.position.set(0, 0.45, -39.0);
    this.hallOfHeroesGroup.add(promGoldRails);

    // 2. Stepped 3-Tier Pantheon Stylobate Platform (Obsidian Sea-Base + Alabaster Upper Plinth)
    const alabasterStepMat = new THREE.MeshBasicMaterial({ color: 0xDFDCD3 });
    const sapphireTrimMat = new THREE.MeshBasicMaterial({ color: 0x133B5C });
    const steps = [
      { w: 56.0, d: 58.0, h: 3.4, y: -1.7, mat: this.mats.obsidian, trimMat: this.mats.goldBright },
      { w: 52.0, d: 54.0, h: 0.9, y: 0.45, mat: sapphireTrimMat, trimMat: this.mats.goldBright },
      { w: 48.0, d: 50.0, h: 0.8, y: 1.25, mat: alabasterStepMat, trimMat: sapphireTrimMat }
    ];
    steps.forEach((s) => {
      const m = new THREE.Mesh(new THREE.BoxGeometry(s.w, s.h, s.d), s.mat);
      m.position.set(0, s.y, 0);
      this.hallOfHeroesGroup.add(m);

      const trim = new THREE.Mesh(new THREE.BoxGeometry(s.w + 0.8, 0.26, s.d + 0.8), s.trimMat);
      trim.position.set(0, s.y + s.h * 0.5, 0);
      this.hallOfHeroesGroup.add(trim);
    });

    const floorTopY = 1.80;

    // High-Contrast Carrara-Alabaster & Atlantis-Sapphire Marble Floor (Maximaler Kontrast zu 24K-Gold!)
    const marbleTex = makeHallOfHeroesMarbleTexture();
    const marbleFloor = new THREE.Mesh(
      new THREE.PlaneGeometry(47.2, 49.2),
      new THREE.MeshBasicMaterial({ map: marbleTex })
    );
    marbleFloor.rotation.x = -Math.PI * 0.5;
    marbleFloor.position.set(0, floorTopY + 0.03, 0);
    this.hallOfHeroesGroup.add(marbleFloor);

    // 3. 28 Monumental 24K-Gold & Obsidian Colonnade Columns via THREE.InstancedMesh (Locked 60 FPS!)
    const colCoords = [];
    // Left & Right Grand Colonnades (8 columns per side)
    for (let i = 0; i < 8; i++) {
      const cz = lerp(-21.5, 21.5, i / 7);
      colCoords.push([-21.5, cz], [21.5, cz]);
    }
    // Seaward Front Portico (+Z) & Tower-Facing Triumphal Portico (-Z), leaving wide 17m central sightline!
    for (const cx of [-15.2, -9.2, 9.2, 15.2]) {
      colCoords.push([cx, 21.5], [cx, -21.5]);
    }
    // 4 Wide Outer Rotunda Pendentive Piers (well outside the central sightline!)
    colCoords.push([-14.8, -14.8], [14.8, -14.8], [-14.8, 14.8], [14.8, 14.8]);

    const colHeight = 12.6;
    const shaftInst = new THREE.InstancedMesh(
      new THREE.CylinderGeometry(0.68, 0.80, colHeight, 14),
      this.mats.goldBrushed,
      colCoords.length
    );
    const capInst = new THREE.InstancedMesh(
      new THREE.BoxGeometry(2.05, 0.82, 2.05),
      this.mats.goldBright,
      colCoords.length
    );

    colCoords.forEach(([cx, cz], idx) => {
      this.dummyObj.position.set(cx, floorTopY + colHeight * 0.5, cz);
      this.dummyObj.rotation.set(0, 0, 0);
      this.dummyObj.scale.set(1, 1, 1);
      this.dummyObj.updateMatrix();
      shaftInst.setMatrixAt(idx, this.dummyObj.matrix);

      this.dummyObj.position.set(cx, floorTopY + colHeight + 0.35, cz);
      this.dummyObj.updateMatrix();
      capInst.setMatrixAt(idx, this.dummyObj.matrix);
    });
    shaftInst.instanceMatrix.needsUpdate = true;
    capInst.instanceMatrix.needsUpdate = true;
    this.hallOfHeroesGroup.add(shaftInst);
    this.hallOfHeroesGroup.add(capInst);

    // 4. Sculpted Obsidian & 24K-Gold Entablature Perimeter Beams + "HALLE DER HELDEN" Friezes
    const entY = floorTopY + colHeight + 1.45;
    const beamMat = this.mats.obsidian;
    const sideBeamGeo = new THREE.BoxGeometry(2.4, 1.9, 45.6);
    for (const sx of [-21.5, 21.5]) {
      const b = new THREE.Mesh(sideBeamGeo, beamMat);
      b.position.set(sx, entY, 0);
      this.hallOfHeroesGroup.add(b);

      const g = new THREE.Mesh(new THREE.BoxGeometry(2.65, 0.35, 46.0), this.mats.goldBright);
      g.position.set(sx, entY + 1.05, 0);
      this.hallOfHeroesGroup.add(g);
    }

    const crossBeamGeo = new THREE.BoxGeometry(45.6, 2.1, 2.5);
    for (const sz of [-21.5, 21.5]) {
      const cb = new THREE.Mesh(crossBeamGeo, beamMat);
      cb.position.set(0, entY, sz);
      this.hallOfHeroesGroup.add(cb);

      const cg = new THREE.Mesh(new THREE.BoxGeometry(46.2, 0.38, 2.8), this.mats.goldBright);
      cg.position.set(0, entY + 1.15, sz);
      this.hallOfHeroesGroup.add(cg);
    }

    const friezeTex = makeHallOfHeroesFriezeTexture();
    const friezeMat = new THREE.MeshBasicMaterial({ map: friezeTex });
    const friezeGeo = new THREE.PlaneGeometry(24.0, 3.75);

    // Seaward Exterior Portico Frieze (greets the over-water camera fly-in!)
    const outerFriezeBack = new THREE.Mesh(new THREE.BoxGeometry(24.8, 4.15, 0.9), this.mats.goldBright);
    outerFriezeBack.position.set(0, entY + 3.15, 21.65);
    this.hallOfHeroesGroup.add(outerFriezeBack);

    const outerFrieze = new THREE.Mesh(friezeGeo, friezeMat);
    outerFrieze.position.set(0, entY + 3.15, 22.15);
    this.hallOfHeroesGroup.add(outerFrieze);

    // Interior Triumphal Arch Frieze Mounted CLEANLY ABOVE the Entablature Beam (100% Unclipped!)
    const innerFriezeBack = new THREE.Mesh(new THREE.BoxGeometry(22.6, 3.7, 0.8), this.mats.goldBright);
    innerFriezeBack.position.set(0, entY + 3.05, -20.65);
    this.hallOfHeroesGroup.add(innerFriezeBack);

    const innerFrieze = new THREE.Mesh(new THREE.PlaneGeometry(22.0, 3.35), friezeMat);
    innerFrieze.position.set(0, entY + 3.05, -20.18);
    this.hallOfHeroesGroup.add(innerFrieze);

    // 5. Open Filigree 24K-Gold Pantheon Ribbed Cupola Dome Mounted CLEANLY ABOVE the Frieze Attica!
    //    Half-segment rib offset ((r + 0.5) / 6) guarantees ZERO central bar on x = 0!
    const domeBaseY = entY + 5.35;
    const domeRadius = 17.5;
    const oculusRadius = 5.2;
    const ribArc = Math.acos(oculusRadius / domeRadius);

    // 4 Corner Attica Drum Columns at (±14.8, ±14.8) seated seamlessly on the 4 Rotunda Capitals up to domeBaseRing
    const drumBottomY = entY - 0.72;
    const drumHeight = domeBaseY - drumBottomY;
    for (const [px, pz] of [[-14.8, -14.8], [14.8, -14.8], [-14.8, 14.8], [14.8, 14.8]]) {
      const drumPost = new THREE.Mesh(new THREE.CylinderGeometry(0.62, 0.70, drumHeight, 14), this.mats.goldBrushed);
      drumPost.position.set(px, drumBottomY + drumHeight * 0.5, pz);
      this.hallOfHeroesGroup.add(drumPost);
    }

    const domeBaseRing = new THREE.Mesh(new THREE.TorusGeometry(domeRadius, 0.42, 10, 56), this.mats.goldBright);
    domeBaseRing.rotation.x = Math.PI * 0.5;
    domeBaseRing.position.set(0, domeBaseY, 0);
    this.hallOfHeroesGroup.add(domeBaseRing);

    for (let r = 0; r < 12; r++) {
      const rib = new THREE.Mesh(new THREE.TorusGeometry(domeRadius, 0.24, 8, 32, ribArc), this.mats.goldBright);
      rib.position.set(0, domeBaseY, 0);
      rib.rotation.y = ((r + 0.5) / 6) * Math.PI;
      this.hallOfHeroesGroup.add(rib);
    }

    const oculusY = domeBaseY + Math.sqrt(domeRadius * domeRadius - oculusRadius * oculusRadius);
    const oculusRing = new THREE.Mesh(new THREE.TorusGeometry(oculusRadius, 0.34, 10, 40), this.mats.goldBright);
    oculusRing.rotation.x = Math.PI * 0.5;
    oculusRing.position.set(0, oculusY, 0);
    this.hallOfHeroesGroup.add(oculusRing);

    const domeStar = new THREE.Mesh(new THREE.OctahedronGeometry(1.45, 0), this.mats.beaconGold);
    domeStar.position.set(0, oculusY + 2.4, 0);
    this.hallOfHeroesGroup.add(domeStar);

    // Clickable Raycast Proxy so clicking the Pantheon from City Overview glides right in!
    const hallClickProxy = new THREE.Mesh(
      new THREE.BoxGeometry(52, 34, 54),
      new THREE.MeshBasicMaterial({ visible: false })
    );
    hallClickProxy.position.set(0, 16, 0);
    this.hallOfHeroesGroup.add(hallClickProxy);
    this.heroesClickTargets.push(hallClickProxy);

    // 6. Central Court of Honor: Top 5 Tiered Pedestals + Authentic 3D Funki Level 1..10 Statues!
    //    Founding-phase default levels showcase the evolution arc (#1=Lvl 10, #2=Lvl 8, #3=Lvl 5, #4=Lvl 3, #5=Lvl 1)
    const pedestalSpecs = [
      { slot: 1, defaultLevel: 10, x:  0.0, z: -7.8, h: 2.35, w: 3.10, scale: 1.24, rotY: 0.0 },
      { slot: 2, defaultLevel: 8,  x: -4.9, z: -4.6, h: 1.85, w: 2.65, scale: 1.08, rotY: 0.22 },
      { slot: 3, defaultLevel: 5,  x:  4.9, z: -4.6, h: 1.85, w: 2.65, scale: 1.08, rotY: -0.22 },
      { slot: 4, defaultLevel: 3,  x: -9.2, z: -0.8, h: 1.50, w: 2.45, scale: 0.98, rotY: 0.36 },
      { slot: 5, defaultLevel: 1,  x:  9.2, z: -0.8, h: 1.50, w: 2.45, scale: 0.98, rotY: -0.36 }
    ];

    this.heroPedestalEntries = [];
    pedestalSpecs.forEach((sp) => {
      const pGrp = new THREE.Group();
      pGrp.position.set(sp.x, floorTopY, sp.z);
      pGrp.rotation.y = sp.rotY;
      this.hallOfHeroesGroup.add(pGrp);

      const pedCore = new THREE.Mesh(
        new THREE.CylinderGeometry(sp.w * 0.52, sp.w * 0.60, sp.h, 8),
        this.mats.obsidian
      );
      pedCore.position.y = sp.h * 0.5;
      pGrp.add(pedCore);

      const pedCap = new THREE.Mesh(
        new THREE.CylinderGeometry(sp.w * 0.57, sp.w * 0.55, 0.18, 8),
        this.mats.goldBright
      );
      pedCap.position.y = sp.h + 0.06;
      pGrp.add(pedCap);

      const pedBase = new THREE.Mesh(
        new THREE.CylinderGeometry(sp.w * 0.63, sp.w * 0.66, 0.18, 8),
        this.mats.goldBright
      );
      pedBase.position.y = 0.09;
      pGrp.add(pedBase);

      // Front Engraved 24K-Gold Name Plaque (1024x512)
      const plaqueCanvas = document.createElement('canvas');
      plaqueCanvas.width = 1024;
      plaqueCanvas.height = 512;
      paintHeroPedestalCanvas(plaqueCanvas, sp.slot, null, 0);
      const plaqueTex = configureHdTex(new THREE.CanvasTexture(plaqueCanvas), 4);

      const pW = sp.w * 0.84;
      const pH = pW * 0.50;
      const plaqueFrame = new THREE.Mesh(new THREE.BoxGeometry(pW + 0.12, pH + 0.12, 0.08), this.mats.goldBright);
      plaqueFrame.position.set(0, sp.h * 0.54, sp.w * 0.56);
      plaqueFrame.rotation.x = -0.08;
      pGrp.add(plaqueFrame);

      const plaqueMesh = new THREE.Mesh(
        new THREE.PlaneGeometry(pW, pH),
        new THREE.MeshBasicMaterial({ map: plaqueTex })
      );
      plaqueMesh.position.set(0, sp.h * 0.54, sp.w * 0.56 + 0.048);
      plaqueMesh.rotation.x = -0.08;
      pGrp.add(plaqueMesh);

      const statueMount = new THREE.Group();
      statueMount.position.set(0, sp.h + 0.15, 0);
      pGrp.add(statueMount);

      const funki = this.buildFunki3DByLevel(sp.defaultLevel, sp.scale, sp.slot);
      statueMount.add(funki.root);

      this.heroPedestalEntries.push({
        slot: sp.slot,
        defaultLevel: sp.defaultLevel,
        scale: sp.scale,
        statueMount,
        canvas: plaqueCanvas,
        texture: plaqueTex,
        funki
      });
    });

    // 7. 24 Golden Honor Steles Along Both Colonnades via THREE.InstancedMesh (Slots #6 .. #29)
    const steleCount = 24;
    const steleObsInst = new THREE.InstancedMesh(
      new THREE.BoxGeometry(1.35, 3.10, 0.34),
      this.mats.obsidian,
      steleCount
    );
    const steleGoldInst = new THREE.InstancedMesh(
      new THREE.BoxGeometry(1.48, 3.24, 0.26),
      this.mats.goldBright,
      steleCount
    );

    this.heroSteleEntries = [];
    for (let i = 0; i < steleCount; i++) {
      const slot = 6 + i;
      const isRight = (i % 2 === 1);
      const row = Math.floor(i / 2); // 0..11
      const sx = isRight ? 16.4 : -16.4;
      const sz = lerp(-15.5, 16.5, row / 11);
      const rotY = isRight ? -Math.PI * 0.42 : Math.PI * 0.42;

      this.dummyObj.position.set(sx, floorTopY + 1.62, sz);
      this.dummyObj.rotation.set(0, rotY, 0);
      this.dummyObj.scale.set(1, 1, 1);
      this.dummyObj.updateMatrix();
      steleObsInst.setMatrixAt(i, this.dummyObj.matrix);
      steleGoldInst.setMatrixAt(i, this.dummyObj.matrix);

      const stCanvas = document.createElement('canvas');
      stCanvas.width = 256;
      stCanvas.height = 512;
      paintHeroSteleCanvas(stCanvas, slot, null);
      const stTex = configureHdTex(new THREE.CanvasTexture(stCanvas), 2);

      const facePlane = new THREE.Mesh(
        new THREE.PlaneGeometry(1.18, 2.36),
        new THREE.MeshBasicMaterial({ map: stTex })
      );
      facePlane.position.set(
        sx + Math.sin(rotY) * 0.185,
        floorTopY + 1.68,
        sz + Math.cos(rotY) * 0.185
      );
      facePlane.rotation.y = rotY;
      this.hallOfHeroesGroup.add(facePlane);

      this.heroSteleEntries.push({
        slot,
        canvas: stCanvas,
        texture: stTex
      });
    }

    steleObsInst.instanceMatrix.needsUpdate = true;
    steleGoldInst.instanceMatrix.needsUpdate = true;
    this.hallOfHeroesGroup.add(steleGoldInst);
    this.hallOfHeroesGroup.add(steleObsInst);

    // 8. Rising Golden Seelenfunken Spark Particles around all 5 Honor Pedestals (1 batched Points draw call!)
    const sparksPerPedestal = 26;
    const totalPedSparks = pedestalSpecs.length * sparksPerPedestal;
    const sparkPositions = new Float32Array(totalPedSparks * 3);
    const sparkColors = new Float32Array(totalPedSparks * 3);
    const sparkRng = mulberry32(20261117);
    this.pedestalSparkMeta = [];

    pedestalSpecs.forEach((pSpec, spi) => {
      const accentRgb = hexToRgb(FUNKI_LEVEL_SPECS[pSpec.defaultLevel]?.auraHex || 0xC5A059);
      for (let sj = 0; sj < sparksPerPedestal; sj++) {
        const sIdx = spi * sparksPerPedestal + sj;
        const phaseVal = sparkRng();
        const baseR = pSpec.w * (0.34 + sparkRng() * 0.26);
        const spanH = (2.35 + sparkRng() * 0.95) * pSpec.scale;
        const spd = 0.14 + sparkRng() * 0.16;
        const sw = (0.65 + sparkRng() * 0.75) * (sj % 2 === 0 ? 1 : -1);
        const startY = floorTopY + pSpec.h + 0.16;

        this.pedestalSparkMeta.push({
          baseX: pSpec.x,
          baseY: startY,
          baseZ: pSpec.z,
          radius: baseR,
          heightSpan: spanH,
          speed: spd,
          phase: phaseVal,
          swirl: sw
        });

        sparkPositions[sIdx * 3] = pSpec.x + Math.cos(phaseVal * 6.283) * baseR;
        sparkPositions[sIdx * 3 + 1] = startY + phaseVal * spanH;
        sparkPositions[sIdx * 3 + 2] = pSpec.z + Math.sin(phaseVal * 6.283) * baseR;

        const useAccent = (sj % 3 === 0);
        sparkColors[sIdx * 3] = useAccent ? (accentRgb[0] / 255) : 1.0;
        sparkColors[sIdx * 3 + 1] = useAccent ? (accentRgb[1] / 255) : 0.89;
        sparkColors[sIdx * 3 + 2] = useAccent ? (accentRgb[2] / 255) : 0.52;
      }
    });

    const pedSparkGeo = new THREE.BufferGeometry();
    this.pedestalSparkPosAttr = new THREE.BufferAttribute(sparkPositions, 3);
    pedSparkGeo.setAttribute('position', this.pedestalSparkPosAttr);
    pedSparkGeo.setAttribute('color', new THREE.BufferAttribute(sparkColors, 3));
    const pedSparkMat = new THREE.PointsMaterial({
      size: 0.22,
      map: this.sparkSpriteTex || null,
      vertexColors: true,
      transparent: true,
      opacity: 0.88,
      blending: THREE.AdditiveBlending,
      depthWrite: false
    });
    const pedSparkPoints = new THREE.Points(pedSparkGeo, pedSparkMat);
    pedSparkPoints.frustumCulled = false;
    this.hallOfHeroesGroup.add(pedSparkPoints);

    // Warm-preload all 10 local .glb models into RAM cache for instant 0ms "✨ Stufen" cycling!
    this._warmPreloadAllFunkiGlbs();
  }

  async refreshHeroesFromServer(force = false) {
    const now = Date.now();
    if (!force && (this.heroesFetchInFlight || (now - this.lastHeroesFetchMs < 20000))) return;
    this.heroesFetchInFlight = true;
    try {
      const resp = await fetch('/api/funkenwerk-city/heroes', { cache: 'no-store' });
      if (!resp.ok) return;
      const data = await resp.json();
      const list = Array.isArray(data?.heroes) ? data.heroes : [];
      this.lastHeroesFetchMs = Date.now();
      this.applyHeroesData(list);
    } catch {
      // Quiet offline founding-phase fallback ("Freier Ehrenplatz" remains active)
    } finally {
      this.heroesFetchInFlight = false;
    }
  }

  applyHeroesData(heroesList) {
    if (Array.isArray(heroesList)) {
      this.cachedHeroesList = heroesList;
    }
    const activeList = this.cachedHeroesList || [];
    const bySlot = new Map();
    activeList.forEach((h, idx) => {
      if (!h || typeof h !== 'object') return;
      const s = Number(h.slot) > 0 ? Number(h.slot) : (idx + 1);
      if (!bySlot.has(s)) bySlot.set(s, h);
    });

    this.heroPedestalEntries.forEach((entry) => {
      const hero = bySlot.get(entry.slot) || null;
      const rawName = hero && !hero.is_placeholder && hero.display_name
        ? String(hero.display_name).trim()
        : '';
      const isRealHero = rawName.length > 0 && rawName.toLowerCase() !== 'freier ehrenplatz';

      let targetLevel;
      let plaquePreviewLvl = 0;

      if (this.previewHeroLevel >= 1 && this.previewHeroLevel <= 10 && entry.slot === 1) {
        targetLevel = this.previewHeroLevel;
        plaquePreviewLvl = this.previewHeroLevel;
      } else if (isRealHero) {
        targetLevel = clamp(Math.round(Number(hero.level) || 1), 1, 10);
      } else {
        targetLevel = entry.defaultLevel;
      }

      if (entry.funki.level !== targetLevel) {
        entry.funki.disposed = true;
        entry.statueMount.remove(entry.funki.root);
        entry.funki = this.buildFunki3DByLevel(targetLevel, entry.scale, entry.slot);
        entry.statueMount.add(entry.funki.root);
      }

      paintHeroPedestalCanvas(entry.canvas, entry.slot, hero, plaquePreviewLvl);
      entry.texture.needsUpdate = true;

      if (plaquePreviewLvl > 0) {
        entry.funki.auraMat.color.setHex(FUNKI_LEVEL_SPECS[plaquePreviewLvl].auraHex);
      } else if (isRealHero) {
        entry.funki.auraMat.color.setHex(resolveHeroAuraHex(hero));
      } else {
        entry.funki.auraMat.color.setHex(FUNKI_LEVEL_SPECS[targetLevel].auraHex);
      }
    });

    this.heroSteleEntries.forEach((entry) => {
      const hero = bySlot.get(entry.slot) || null;
      paintHeroSteleCanvas(entry.canvas, entry.slot, hero);
      entry.texture.needsUpdate = true;
    });
  }

  cyclePreviewHeroLevel() {
    this.init();
    if (this.viewMode !== 'heroes') {
      this.flyToHallOfHeroes(false);
    }
    this.previewHeroLevel = (this.previewHeroLevel + 1) % 11; // 1..10 -> 0 (Live)
    this.applyHeroesData(this.cachedHeroesList);
    this._updateHudActiveButtons();
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

    // Tight near/far clip plane in Throne Room vs Space Orbit for zero z-fighting!
    const inThroneScale = camPos.distanceTo(new THREE.Vector3(0, THRONE_FLOOR_Y + 2, 0)) < 36;
    const wantNear = inThroneScale ? 0.12 : 0.6;
    const wantFar = distFromOrigin > 1400 ? 28000 : 19500;
    if (Math.abs(this.camera.near - wantNear) > 0.02 || Math.abs(this.camera.far - wantFar) > 100) {
      this.camera.near = wantNear;
      this.camera.far = wantFar;
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
    } else if (this.viewMode === 'heroes') {
      this.orbit.minRadius = 2.2;
      this.orbit.maxRadius = 220.0;
      this.orbit.minY = 6.9;
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
    } else if (this.camera.position.length() < 1200 && this.viewMode !== 'heroes' && this.viewMode !== 'department') {
      target.set(0, clamp(target.y, 8, 265), 0);
    }
    const keepMode = (this.viewMode === 'throne' || this.viewMode === 'heroes' || this.viewMode === 'department')
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
    const heroCityPos = new THREE.Vector3(175, 178, 520);
    const heroCityTarget = new THREE.Vector3(0, 112, 0);

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
    this.flight.toPos.copy(heroCityPos);
    this.flight.toTarget.copy(heroCityTarget);
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

    // Pier-Citadel target (lx = 0, y = 18.0, lz = 362) & elevated 3/4 lagoon viewpoint (lx = 54, y = 40.0, lz = 248)
    const destTarget = new THREE.Vector3(
      362 * Math.sin(rotY),
      18.0,
      362 * Math.cos(rotY)
    );
    const destPos = new THREE.Vector3(
      54 * Math.cos(rotY) + 248 * Math.sin(rotY),
      40.0,
      -54 * Math.sin(rotY) + 248 * Math.cos(rotY)
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

  // Phase 4 Cinematic Low-Over-Water Glide into the Monumental "HALLE DER HELDEN" Pantheon!
  flyToHallOfHeroes(instant = false) {
    this.init();
    this.refreshHeroesFromServer(false);
    this.viewMode = 'heroes';
    this._emitDepartmentChange(null);
    this._updateHudActiveButtons();

    this.hallOfHeroesGroup.updateMatrixWorld(true);
    const destPos = new THREE.Vector3(0.0, 5.45, 19.6).applyMatrix4(this.hallOfHeroesGroup.matrixWorld);
    const destTarget = new THREE.Vector3(0.0, 7.15, -7.2).applyMatrix4(this.hallOfHeroesGroup.matrixWorld);
    const overWaterMid = new THREE.Vector3(24.0, 15.5, 82.0).applyMatrix4(this.hallOfHeroesGroup.matrixWorld);

    if (instant) {
      this.flight.active = false;
      this.camera.position.copy(destPos);
      this.camera.lookAt(destTarget);
      this._syncPlanetVsCityBlend(destPos);
      this._adoptCameraIntoOrbitControls(destTarget, 'heroes');
      return;
    }

    if (this.camera.position.length() > 1400) {
      const seaEntry = new THREE.Vector3(65.0, 95.0, 190.0).applyMatrix4(this.hallOfHeroesGroup.matrixWorld);
      this.camera.position.copy(seaEntry);
      this.orbit.target.copy(destTarget);
    }

    this.flight.active = true;
    this.flight.type = 'glide_heroes';
    this.flight.elapsed = 0;
    this.flight.duration = 2.85;
    this.flight.fromPos.copy(this.camera.position);
    this.flight.fromTarget.copy(this.orbit.target);
    this.flight.midPos.copy(overWaterMid);
    this.flight.toPos.copy(destPos);
    this.flight.toTarget.copy(destTarget);
  }

  _updateHudActiveButtons() {
    const bOrbit = document.getElementById('btn-fw-city-orbit');
    const bCity = document.getElementById('btn-fw-city-overview');
    const bDepts = document.getElementById('btn-fw-city-departments');
    const deptMenu = document.getElementById('fw-city-dept-menu');
    const bHeroes = document.getElementById('btn-fw-city-heroes');
    const bLevels = document.getElementById('btn-fw-city-hero-levels');
    const bThrone = document.getElementById('btn-fw-city-throne');
    bOrbit?.classList.toggle('active', this.viewMode === 'orbit');
    bCity?.classList.toggle('active', this.viewMode === 'city');
    bHeroes?.classList.toggle('active', this.viewMode === 'heroes');
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
    if (bLevels) {
      bLevels.classList.toggle('hidden', this.viewMode !== 'heroes');
      bLevels.classList.toggle('active', this.previewHeroLevel > 0);
      bLevels.textContent = this.previewHeroLevel > 0 ? `✨ Stufe ${this.previewHeroLevel}` : '✨ Stufen';
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
        } else if (this.viewMode !== 'heroes' && this.raycaster.intersectObjects(this.heroesClickTargets, false).length > 0) {
          hitLabel = '🏛️ Halle der Helden';
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

      // Click without drag: 1. Throne Room -> 2. Hall of Heroes -> 3. Department Pier -> 4. Open Water return
      if (this.orbit.movedDist < 6 && e.button === 0 && this.cityGroup?.visible) {
        const rect = el.getBoundingClientRect();
        this.pointerNdc.x = ((e.clientX - rect.left) / Math.max(1, rect.width)) * 2 - 1;
        this.pointerNdc.y = -((e.clientY - rect.top) / Math.max(1, rect.height)) * 2 + 1;
        this.raycaster.setFromCamera(this.pointerNdc, this.camera);

        if (this.viewMode !== 'throne' && this.raycaster.intersectObjects(this.throneClickTargets, false).length > 0) {
          this.flyToThroneRoom(false);
        } else if (this.viewMode !== 'heroes' && this.raycaster.intersectObjects(this.heroesClickTargets, false).length > 0) {
          this.flyToHallOfHeroes(false);
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
    this.refreshHeroesFromServer(false);

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
    } else if (modeStr === 'heroes') {
      this.flyToHallOfHeroes(false);
    } else if (modeStr === 'city') {
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
    }

    // 2. Subtle Poised Vigilance Animation for 3D Falcon in the Penthouse Throne Room
    if (this.falconChestGroup && this.falconHeadGroup) {
      this.falconChestGroup.position.y = 0.94 + Math.sin(t * 1.85) * 0.008;
      this.falconHeadGroup.rotation.y = Math.sin(t * 0.65) * 0.09;
      this.falconHeadGroup.rotation.z = Math.cos(t * 0.85) * 0.022;
    }

    // 3. 60-FPS Living Pedestal Animation for the Real Tripo 3D Funki (.glb) Heroes + Counter-Rotating Aura Rings & Sparks!
    for (let i = 0; i < this.heroPedestalEntries.length; i++) {
      const f = this.heroPedestalEntries[i].funki;
      const rig = f.motionRig || f.floatGroup;
      if (!rig) continue;

      const breathWave = Math.sin(t * 1.85 + f.phase);
      const breathScaleY = 1.0 + breathWave * 0.013;
      const breathScaleXZ = 1.0 - breathWave * 0.005;
      rig.scale.set(breathScaleXZ, breathScaleY, breathScaleXZ);

      if (f.level >= 8) {
        // High-tier Masters, Lightbringers & Gods hover with majestic aura levitation
        rig.position.y = 0.035 + (0.5 + 0.5 * breathWave) * 0.055;
      } else if (!f.sitting) {
        rig.position.y = Math.max(0, breathWave) * 0.016;
      } else {
        rig.position.y = 0;
      }

      // Proud upper-body & gaze presence toward the Hall of Heroes
      rig.rotation.y = Math.sin(t * 0.72 + f.phase) * 0.082;
      rig.rotation.z = Math.sin(t * 0.52 + f.phase * 1.3) * 0.016;
      rig.rotation.x = Math.sin(t * 0.94 + f.phase) * 0.012;

      if (f.auraOuter) {
        const auraPulse = 1.0 + Math.sin(t * 2.2 + f.phase) * 0.032;
        f.auraOuter.scale.set(auraPulse, auraPulse, 1.0);
        f.auraOuter.rotation.z = t * 0.65 + f.phase;
      }
      if (f.auraInner) {
        f.auraInner.rotation.z = -t * 0.95 - f.phase;
      }
      if (f.divineOuterRing) {
        f.divineOuterRing.rotation.x = t * 0.45 + f.phase;
        f.divineOuterRing.rotation.z = -t * 0.35;
      }
    }

    // Rising Golden Seelenfunken Spark Particles around the 5 Honor Pedestals (0 allocations per frame!)
    if (this.pedestalSparkPosAttr && this.cityGroup?.visible) {
      const posArr = this.pedestalSparkPosAttr.array;
      for (let si = 0; si < this.pedestalSparkMeta.length; si++) {
        const sm = this.pedestalSparkMeta[si];
        const su = ((sm.phase + t * sm.speed) % 1 + 1) % 1;
        const sang = t * sm.swirl + sm.phase * 6.283185;
        const sr = sm.radius * (0.76 + 0.24 * Math.sin(su * 3.14159));
        const p3 = si * 3;
        posArr[p3] = sm.baseX + Math.cos(sang) * sr;
        posArr[p3 + 1] = sm.baseY + su * sm.heightSpan;
        posArr[p3 + 2] = sm.baseZ + Math.sin(sang) * sr;
      }
      this.pedestalSparkPosAttr.needsUpdate = true;
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
          : (this.flight.type === 'glide_heroes'
            ? 'heroes'
            : (this.flight.type === 'glide_department' ? 'department' : 'city'));
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
document.getElementById('btn-fw-city-heroes')?.addEventListener('click', () => {
  window.FunkenwerkCity3D?.flyToHallOfHeroes(false);
});
document.getElementById('btn-fw-city-hero-levels')?.addEventListener('click', () => {
  window.FunkenwerkCity3D?.cyclePreviewHeroLevel();
});
