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
  { key: 'support',     name: 'Support',     hex: '#06B6D4', color: 0x06B6D4 },
  { key: 'products',    name: 'Produkte',    hex: '#3B82F6', color: 0x3B82F6 },
  { key: 'marketing',   name: 'Marketing',   hex: '#A855F7', color: 0xA855F7 },
  { key: 'orders',      name: 'Bestellungen',hex: '#F59E0B', color: 0xF59E0B },
  { key: 'accounting',  name: 'Buchhaltung', hex: '#10B981', color: 0x10B981 },
  { key: 'agents',      name: 'Agenten',     hex: '#6366F1', color: 0x6366F1 },
  { key: 'system',      name: 'System',      hex: '#EF4444', color: 0xEF4444 }
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

// Falcon's Armored Suit Chest Emblem ("FALCON · Mitgründer")
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
  ctx.font = '900 66px "Liberation Sans", "Segoe UI", sans-serif';
  ctx.fillText('FALCON', W * 0.5, H * 0.40);

  ctx.fillStyle = '#C5A059';
  ctx.fillRect(W * 0.22, H * 0.60, W * 0.56, 3);

  ctx.fillStyle = '#FFF6D6';
  ctx.font = '700 36px "Liberation Sans", "Segoe UI", sans-serif';
  ctx.fillText('FALCON · Mitgründer', W * 0.5, H * 0.77);

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
    this.viewMode = 'orbit'; // 'orbit' | 'city' | 'throne'
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
    this.renderer.toneMappingExposure = 1.08;
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
    const mcGoldBright = makeLuxuryMatcap('#D4AF37', '#FFFBEB', '#FDE68A', 38, 0.46);
    const mcGoldBrushed = makeLuxuryMatcap('#B89146', '#FFF3C4', '#D4AF37', 22, 0.32);
    const mcObsidian = makeLuxuryMatcap('#11141C', '#687387', '#C5A059', 30, 0.22);
    const mcAnthracite = makeLuxuryMatcap('#1B202B', '#7C879B', '#9CA3AF', 18, 0.18);
    const mcCrimsonVelvet = makeLuxuryMatcap('#7A0D1B', '#C94A5A', '#D4AF37', 10, 0.24);
    const mcSkin = makeLuxuryMatcap('#DDA988', '#FFF2E6', '#F4C6A8', 14, 0.20);
    const mcHair = makeLuxuryMatcap('#2A1E17', '#7A6252', '#C5A059', 20, 0.18);
    const mcWhiteShirt = makeLuxuryMatcap('#ECEFF4', '#FFFFFF', '#D4AF37', 16, 0.15);

    const facadeTex = makeSkyscraperFacadeTexture();

    this.mats = {
      goldBright: new THREE.MeshMatcapMaterial({ matcap: mcGoldBright }),
      goldBrushed: new THREE.MeshMatcapMaterial({ matcap: mcGoldBrushed }),
      obsidian: new THREE.MeshMatcapMaterial({ matcap: mcObsidian }),
      anthracite: new THREE.MeshMatcapMaterial({ matcap: mcAnthracite }),
      velvet: new THREE.MeshMatcapMaterial({ matcap: mcCrimsonVelvet }),
      skin: new THREE.MeshMatcapMaterial({ matcap: mcSkin }),
      hair: new THREE.MeshMatcapMaterial({ matcap: mcHair }),
      shirt: new THREE.MeshMatcapMaterial({ matcap: mcWhiteShirt }),
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
      'FALCON',
      'FALCON · Rechte Hand & Mitgründer',
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
    } else {
      this.orbit.minRadius = 28.0;
      this.orbit.maxRadius = 6200.0;
      this.orbit.minY = 4.5;
    }
    this._updateHudActiveButtons();
  }

  cancelFlightToFreeOrbit() {
    if (!this.flight.active) return;
    this.flight.active = false;
    if (this.cloudVeilEl) this.cloudVeilEl.style.opacity = '0';

    // If cancelled mid-space-dive, ensure the city is cleanly visible if close enough, or keep current look target
    const curLook = new THREE.Vector3();
    this.camera.getWorldDirection(curLook);
    const dist = clamp(this.camera.position.length() * 0.45, 4.0, 900.0);
    const target = this.camera.position.clone().addScaledVector(curLook, dist);
    if (this.camera.position.length() < 1200) {
      target.set(0, clamp(target.y, 8, 265), 0);
    }
    this._adoptCameraIntoOrbitControls(target, this.viewMode === 'throne' ? 'throne' : 'city');
  }

  // Phase 1 -> Phase 2: Epic 6.0s Orbital Planet Fly-In to the Floating City of Funkenwerk!
  startOrbitalFlyIn() {
    this.init();
    this.viewMode = 'orbit';
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

  // Phase 3 Smooth Panoramic Ascent into Alina & Falcon's Penthouse Throne Room!
  flyToThroneRoom(instant = false) {
    this.init();
    this.viewMode = 'throne';
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
    const bThrone = document.getElementById('btn-fw-city-throne');
    bOrbit?.classList.toggle('active', this.viewMode === 'orbit');
    bCity?.classList.toggle('active', this.viewMode === 'city');
    bThrone?.classList.toggle('active', this.viewMode === 'throne');
  }

  _bindControls() {
    const el = this.canvas;
    if (!el) return;

    el.addEventListener('contextmenu', (e) => e.preventDefault());

    el.addEventListener('pointerdown', (e) => {
      if (!this.running) return;
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
      if (!this.running || !this.orbit.isDragging) return;
      const dx = e.clientX - this.orbit.lastX;
      const dy = e.clientY - this.orbit.lastY;
      this.orbit.lastX = e.clientX;
      this.orbit.lastY = e.clientY;
      this.orbit.movedDist += Math.hypot(dx, dy);

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

    const endPointer = (e) => {
      if (!this.orbit.isDragging) return;
      this.orbit.isDragging = false;
      try { el.releasePointerCapture(e.pointerId); } catch { }

      // Click without drag on Golden Supertower Crown/Spire -> Glide into Alina's Throne Room!
      if (this.orbit.movedDist < 6 && e.button === 0 && this.viewMode !== 'throne') {
        const rect = el.getBoundingClientRect();
        this.pointerNdc.x = ((e.clientX - rect.left) / Math.max(1, rect.width)) * 2 - 1;
        this.pointerNdc.y = -((e.clientY - rect.top) / Math.max(1, rect.height)) * 2 + 1;
        this.raycaster.setFromCamera(this.pointerNdc, this.camera);
        const hits = this.raycaster.intersectObjects(this.throneClickTargets, false);
        if (hits.length > 0) {
          this.flyToThroneRoom(false);
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

    if (initialMode === 'throne') {
      this.flyToThroneRoom(false);
    } else if (initialMode === 'city') {
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
        const settledMode = this.flight.type === 'glide_throne' ? 'throne' : 'city';
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

window.FunkenwerkCity3D = new FunkenwerkCityEngine();
