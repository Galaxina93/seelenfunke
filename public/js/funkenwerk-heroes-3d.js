(function () {
  'use strict';

  if (window.SeelenfunkeHeroes3D) {
    return;
  }

  var PLANET_RADIUS = 1800.0;
  var PANTHEON_Z = 216.0;
  var PANTHEON_Y = 5.0;
  var TOTAL_DISPLAY_SLOTS = 29; // Top 5 Pedestal Funki Characters + 24 Colonnade Honor Steles

  // Verified Tripo 3D local front axis (+X) -> -Math.PI / 2 rotates TV face & "Funki" chest text straight to +Z camera
  var FUNKI_GLB_FRONT_ROTATION_Y = -Math.PI * 0.5;

  var DEFAULT_FUNKI_GLB_URLS = {
    1: '/shop/customer/gamification/models/funki_lvl_1_rags.glb',
    2: '/shop/customer/gamification/models/funki_lvl_2_basic.glb',
    3: '/shop/customer/gamification/models/funki_lvl_3_helper.glb',
    4: '/shop/customer/gamification/models/funki_lvl_4_novice.glb',
    5: '/shop/customer/gamification/models/funki_lvl_5_apprentice.glb',
    6: '/shop/customer/gamification/models/funki_lvl_6_craftsman.glb',
    7: '/shop/customer/gamification/models/funki_lvl_7_artisan.glb',
    8: '/shop/customer/gamification/models/funki_lvl_8_master.glb',
    9: '/shop/customer/gamification/models/funki_lvl_9_lightbringer.glb',
    10: '/shop/customer/gamification/models/funki_lvl_10_god_of_soul.glb'
  };

  var PIER_ACCENTS = [
    '#D4AF37', '#FDE68A', '#C5A059', '#EAB308', '#38BDF8', '#F59E0B', '#FFF3C4'
  ];

  function clamp(v, lo, hi) {
    return Math.max(lo, Math.min(hi, v));
  }

  function lerp(a, b, t) {
    return a + (b - a) * t;
  }

  function easeInOutQuint(t) {
    var x = clamp(t, 0, 1);
    return x < 0.5 ? 16 * x * x * x * x * x : 1 - Math.pow(-2 * x + 2, 5) / 2;
  }

  function easeInOutCubic(t) {
    var x = clamp(t, 0, 1);
    return x < 0.5 ? 4 * x * x * x : 1 - Math.pow(-2 * x + 2, 3) / 2;
  }

  function hexToRgb(hex) {
    var n = typeof hex === 'number' ? hex : parseInt(String(hex).replace('#', ''), 16);
    return [(n >> 16) & 255, (n >> 8) & 255, n & 255];
  }

  function mulberry32(seed) {
    var a = seed >>> 0;
    return function () {
      a |= 0;
      a = (a + 0x6D2B79F5) | 0;
      var t = Math.imul(a ^ (a >>> 15), 1 | a);
      t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
      return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
    };
  }

  function configureTex(THREE, tex, maxAniso, aniso) {
    tex.generateMipmaps = true;
    tex.minFilter = THREE.LinearMipmapLinearFilter;
    tex.magFilter = THREE.LinearFilter;
    tex.anisotropy = Math.min(maxAniso || 4, aniso || 4);
    if ('colorSpace' in tex && THREE.SRGBColorSpace) {
      tex.colorSpace = THREE.SRGBColorSpace;
    } else if (THREE.sRGBEncoding) {
      tex.encoding = THREE.sRGBEncoding;
    }
    return tex;
  }

  function makeLuxuryMatcap(THREE, maxAniso, baseHex, specHex, rimHex, specPower, rimStrength) {
    var size = 256;
    var c = document.createElement('canvas');
    c.width = size;
    c.height = size;
    var ctx = c.getContext('2d');
    var img = ctx.createImageData(size, size);
    var d = img.data;

    var base = hexToRgb(baseHex);
    var spec = hexToRgb(specHex);
    var rim = hexToRgb(rimHex);

    var lx1 = -0.36, ly1 = 0.64, lz1 = 0.677;
    var hx1 = -0.20, hy1 = 0.39, hz1 = 0.899;
    var lx2 = 0.52, ly2 = 0.32, lz2 = 0.791;
    var hx2 = 0.32, hy2 = 0.21, hz2 = 0.924;

    var half = size * 0.5;
    for (var y = 0; y < size; y++) {
      var ny = (half - y - 0.5) / half;
      for (var x = 0; x < size; x++) {
        var nx = (x + 0.5 - half) / half;
        var r2 = nx * nx + ny * ny;
        var r, g, b;
        if (r2 <= 1.0) {
          var nz = Math.sqrt(Math.max(0, 1.0 - r2));
          var ndl1 = Math.max(0, nx * lx1 + ny * ly1 + nz * lz1);
          var ndl2 = Math.max(0, nx * lx2 + ny * ly2 + nz * lz2);
          var skyBounce = 0.34 + 0.30 * (ny * 0.5 + 0.5);
          var horizonBand = Math.exp(-Math.pow((ny + 0.12) * 5.4, 2)) * (specPower >= 22 ? 0.14 : 0.03);
          var hemi = skyBounce + 0.48 * ndl1 + 0.22 * ndl2 + horizonBand;

          var ndh1 = Math.max(0, nx * hx1 + ny * hy1 + nz * hz1);
          var ndh2 = Math.max(0, nx * hx2 + ny * hy2 + nz * hz2);
          var sp = Math.pow(ndh1, Math.max(5, specPower * 0.42)) * 0.24 +
            Math.pow(ndh1, specPower * 1.35) * 0.82 +
            Math.pow(ndh2, specPower * 0.88) * 0.18;
          var fresnel = Math.pow(1.0 - nz, 2.7) * rimStrength;

          r = base[0] * hemi + spec[0] * sp + rim[0] * fresnel;
          g = base[1] * hemi + spec[1] * sp + rim[1] * fresnel;
          b = base[2] * hemi + spec[2] * sp + rim[2] * fresnel;
        } else {
          var len = Math.sqrt(r2);
          var uy = ny / len;
          var hemiOut = 0.34 + 0.24 * (uy * 0.5 + 0.5);
          r = base[0] * hemiOut + rim[0] * rimStrength * 0.9;
          g = base[1] * hemiOut + rim[1] * rimStrength * 0.9;
          b = base[2] * hemiOut + rim[2] * rimStrength * 0.9;
        }
        var p = (y * size + x) * 4;
        d[p] = clamp(Math.round(r), 0, 255);
        d[p + 1] = clamp(Math.round(g), 0, 255);
        d[p + 2] = clamp(Math.round(b), 0, 255);
        d[p + 3] = 255;
      }
    }
    ctx.putImageData(img, 0, 0);
    return configureTex(THREE, new THREE.CanvasTexture(c), maxAniso, 2);
  }

  // 1024x1024 Interior-Lit Skyscraper Curtain-Wall Atlas + Emissive Map + PBR Roughness Map (4x Anisotropy)
  function makeSkyscraperFacadeAtlas(THREE, maxAniso) {
    var W = 1024, H = 1024;
    var cCol = document.createElement('canvas');
    cCol.width = W;
    cCol.height = H;
    var ctx = cCol.getContext('2d');

    var cEmis = document.createElement('canvas');
    cEmis.width = W;
    cEmis.height = H;
    var ectx = cEmis.getContext('2d');

    var cRough = document.createElement('canvas');
    cRough.width = W;
    cRough.height = H;
    var rctx = cRough.getContext('2d');

    var rng = mulberry32(20261116);

    var bg = ctx.createLinearGradient(0, 0, 0, H);
    bg.addColorStop(0, '#0e1520');
    bg.addColorStop(0.55, '#090e17');
    bg.addColorStop(1, '#06090f');
    ctx.fillStyle = bg;
    ctx.fillRect(0, 0, W, H);

    ectx.fillStyle = '#000000';
    ectx.fillRect(0, 0, W, H);

    rctx.fillStyle = 'rgb(199, 199, 199)';
    rctx.fillRect(0, 0, W, H);

    for (var g = 0; g < 4000; g++) {
      ctx.fillStyle = rng() > 0.5 ? 'rgba(255,250,235,0.035)' : 'rgba(0,0,0,0.22)';
      ctx.fillRect(rng() * W, rng() * H, 3, 2);
    }

    var floors = 24, cols = 12;
    var fh = H / floors, cw = W / cols;

    for (var f = 0; f < floors; f++) {
      var y = f * fh;
      var bandHighlight = (f % 8 === 0);
      var suiteLit = rng() > 0.46;
      var suiteWarmth = rng();

      for (var col = 0; col < cols; col++) {
        if (col % 3 === 0 && rng() > 0.35) {
          suiteLit = rng() > 0.44;
          suiteWarmth = rng();
        }
        var x = col * cw;
        var wx = x + 4.5, wy = y + 5.5;
        var ww = cw - 9.0, wh = fh - 10.0;

        rctx.fillStyle = 'rgb(36, 36, 36)';
        rctx.fillRect(wx, wy, ww, wh);

        if (bandHighlight) {
          var lg = ctx.createLinearGradient(0, wy, 0, wy + wh);
          lg.addColorStop(0, '#FFEAB3');
          lg.addColorStop(0.5, '#C5A059');
          lg.addColorStop(1, '#5E4720');
          ctx.fillStyle = lg;
          ctx.fillRect(wx, wy, ww, wh);

          ectx.fillStyle = 'rgba(225, 182, 98, 0.72)';
          ectx.fillRect(wx, wy + 2, ww, wh - 4);
        } else if (suiteLit && rng() > 0.14) {
          var roomGrad = ctx.createLinearGradient(0, wy, 0, wy + wh);
          roomGrad.addColorStop(0.0, suiteWarmth > 0.62 ? '#FFF6D6' : '#FDE68A');
          roomGrad.addColorStop(0.48, suiteWarmth > 0.62 ? '#F3C878' : '#D4AF37');
          roomGrad.addColorStop(1.0, '#58411E');
          ctx.fillStyle = roomGrad;
          ctx.fillRect(wx, wy, ww, wh);

          ctx.fillStyle = 'rgba(14, 18, 26, 0.42)';
          ctx.fillRect(wx, wy + wh * 0.66, ww, 2.2);
          if (rng() > 0.45) {
            ctx.fillRect(wx + ww * (0.35 + rng() * 0.35), wy, 2.0, wh);
          }

          var emisGrad = ectx.createLinearGradient(0, wy, 0, wy + wh);
          emisGrad.addColorStop(0.0, suiteWarmth > 0.62 ? 'rgba(255, 243, 201, 0.94)' : 'rgba(245, 208, 125, 0.78)');
          emisGrad.addColorStop(0.75, 'rgba(197, 160, 89, 0.52)');
          emisGrad.addColorStop(1.0, 'rgba(92, 68, 30, 0.25)');
          ectx.fillStyle = emisGrad;
          ectx.fillRect(wx + 1, wy + 1, ww - 2, wh - 2);
        } else {
          var dg = ctx.createLinearGradient(0, wy, 0, wy + wh);
          dg.addColorStop(0, '#142136');
          dg.addColorStop(0.6, '#0d1626');
          dg.addColorStop(1, '#080d17');
          ctx.fillStyle = dg;
          ctx.fillRect(wx, wy, ww, wh);
        }
      }

      ctx.fillStyle = '#141923';
      ctx.fillRect(0, y, W, 4.5);
      ctx.fillStyle = bandHighlight ? '#E5C158' : 'rgba(197, 160, 89, 0.52)';
      ctx.fillRect(0, y + (bandHighlight ? 0.5 : 1.5), W, bandHighlight ? 3.4 : 1.8);

      rctx.fillStyle = bandHighlight ? 'rgb(76, 76, 76)' : 'rgb(186, 186, 186)';
      rctx.fillRect(0, y, W, 5.0);
    }

    for (var colLine = 0; colLine <= cols; colLine++) {
      var xl = colLine * cw;
      var major = (colLine % 4 === 0);
      var mw = major ? 4.4 : 2.0;
      ctx.fillStyle = major ? '#D4AF37' : 'rgba(197, 160, 89, 0.44)';
      ctx.fillRect(xl - mw * 0.5, 0, mw, H);
      if (major) {
        ctx.fillStyle = 'rgba(255, 248, 214, 0.45)';
        ctx.fillRect(xl - 0.8, 0, 1.2, H);
      }
      rctx.fillStyle = 'rgb(82, 82, 82)';
      rctx.fillRect(xl - mw * 0.5, 0, mw, H);
    }

    var colorTex = configureTex(THREE, new THREE.CanvasTexture(cCol), maxAniso, 4);
    colorTex.wrapS = THREE.RepeatWrapping;
    colorTex.wrapT = THREE.RepeatWrapping;

    var emissiveTex = configureTex(THREE, new THREE.CanvasTexture(cEmis), maxAniso, 4);
    emissiveTex.wrapS = THREE.RepeatWrapping;
    emissiveTex.wrapT = THREE.RepeatWrapping;

    var roughnessTex = new THREE.CanvasTexture(cRough);
    roughnessTex.generateMipmaps = true;
    roughnessTex.minFilter = THREE.LinearMipmapLinearFilter;
    roughnessTex.magFilter = THREE.LinearFilter;
    roughnessTex.anisotropy = Math.min(maxAniso || 4, 4);
    if ('colorSpace' in roughnessTex && typeof THREE.NoColorSpace !== 'undefined') {
      roughnessTex.colorSpace = THREE.NoColorSpace;
    } else if (THREE.LinearEncoding) {
      roughnessTex.encoding = THREE.LinearEncoding;
    }
    roughnessTex.wrapS = THREE.RepeatWrapping;
    roughnessTex.wrapT = THREE.RepeatWrapping;

    return { colorTex: colorTex, emissiveTex: emissiveTex, roughnessTex: roughnessTex };
  }

  // Seamless 2K (2048x1024) Equirectangular Golden-Hour Sky & Volumetric Cloud Panorama (0 external CDN assets!)
  function makePhotorealSkyPanoramaTexture(THREE, maxAniso, sunDirVec) {
    var W = 2048, H = 1024;
    var c = document.createElement('canvas');
    c.width = W;
    c.height = H;
    var ctx = c.getContext('2d');

    var sx = sunDirVec ? sunDirVec.x : -0.35;
    var sy = sunDirVec ? sunDirVec.y : 0.32;
    var sz = sunDirVec ? sunDirVec.z : -0.88;
    var sLen = Math.hypot(sx, sy, sz) || 1.0;
    var ux = sx / sLen, uy = sy / sLen, uz = sz / sLen;

    var cw = 512, ch = 256;
    var cloudCanvas = document.createElement('canvas');
    cloudCanvas.width = cw;
    cloudCanvas.height = ch;
    var cctx = cloudCanvas.getContext('2d');
    var img = cctx.createImageData(cw, ch);
    var data = img.data;

    function hash3(ix, iy, iz) {
      var n = (Math.imul(ix, 127) + Math.imul(iy, 311) + Math.imul(iz, 74)) | 0;
      n = (n << 13) ^ n;
      return ((Math.imul(n, (Math.imul(n, Math.imul(n, 15731)) + 789221) | 0) + 1376312589) >>> 0) / 4294967296;
    }

    function noise3(px, py, pz) {
      var ix = Math.floor(px), iy = Math.floor(py), iz = Math.floor(pz);
      var fx = px - ix, fy = py - iy, fz = pz - iz;
      fx = fx * fx * (3 - 2 * fx);
      fy = fy * fy * (3 - 2 * fy);
      fz = fz * fz * (3 - 2 * fz);
      var n000 = hash3(ix, iy, iz), n100 = hash3(ix + 1, iy, iz);
      var n010 = hash3(ix, iy + 1, iz), n110 = hash3(ix + 1, iy + 1, iz);
      var n001 = hash3(ix, iy, iz + 1), n101 = hash3(ix + 1, iy, iz + 1);
      var n011 = hash3(ix, iy + 1, iz + 1), n111 = hash3(ix + 1, iy + 1, iz + 1);
      var nx00 = n000 + (n100 - n000) * fx;
      var nx10 = n010 + (n110 - n010) * fx;
      var nx01 = n001 + (n101 - n001) * fx;
      var nx11 = n011 + (n111 - n011) * fx;
      var nxy0 = nx00 + (nx10 - nx00) * fy;
      var nxy1 = nx01 + (nx11 - nx01) * fy;
      return nxy0 + (nxy1 - nxy0) * fz;
    }

    function cloudFbm(px, py, pz) {
      var v = 0.0;
      v += 0.52 * noise3(px, py, pz);
      v += 0.27 * noise3(px * 2.07 + 3.1, py * 2.07 + 1.7, pz * 2.07 + 4.3);
      v += 0.14 * noise3(px * 4.23 + 7.4, py * 4.23 + 5.2, pz * 4.23 + 2.9);
      v += 0.07 * noise3(px * 8.51 + 1.9, py * 8.51 + 8.6, pz * 8.51 + 6.1);
      return v;
    }

    for (var y = 0; y < ch; y++) {
      var lat = (0.5 - (y + 0.5) / ch) * Math.PI;
      var sinLat = Math.sin(lat);
      var cosLat = Math.cos(lat);

      for (var x = 0; x < cw; x++) {
        var u = (x + 0.5) / cw;
        var lon = (u - 0.5) * Math.PI * 2.0;
        var dx = cosLat * Math.cos(lon);
        var dy = sinLat;
        var dz = cosLat * Math.sin(lon);

        var sunDot = Math.max(0, dx * ux + dy * uy + dz * uz);
        var r = 0, g = 0, b = 0;

        if (dy >= -0.02) {
          var h = clamp(dy, 0.0, 1.0);
          var tMid = Math.pow(1.0 - h, 1.85);
          var tHor = Math.pow(1.0 - h, 5.0);
          var azGlow = Math.pow(sunDot, 2.3);

          r = lerp(12, 42, tMid);
          g = lerp(36, 94, tMid);
          b = lerp(74, 148, tMid);

          var horR = lerp(102, 242, azGlow);
          var horG = lerp(112, 192, azGlow);
          var horB = lerp(138, 120, azGlow);
          r = lerp(r, horR, tHor * 0.92);
          g = lerp(g, horG, tHor * 0.92);
          b = lerp(b, horB, tHor * 0.92);

          var cloudBand = clamp((h - 0.015) / 0.14, 0.0, 1.0) * clamp((0.78 - h) / 0.52, 0.0, 1.0);
          if (cloudBand > 0.004) {
            var projScale = 3.8 / (h + 0.24);
            var cxp = dx * projScale;
            var cyp = dy * 2.4;
            var czp = dz * projScale;

            var dens = cloudFbm(cxp, cyp, czp);
            var cov = clamp((dens - 0.47) / 0.30, 0.0, 1.0);
            var cloudAlpha = cov * cov * (3 - 2 * cov) * cloudBand * 0.86;

            if (cloudAlpha > 0.005) {
              var litSample = cloudFbm(cxp + ux * 0.42, cyp + uy * 0.35, czp + uz * 0.42);
              var shadow = clamp((litSample - dens) * 3.8, -0.45, 0.65);
              var sunLit = clamp(0.58 - shadow + Math.pow(sunDot, 3.0) * 0.42, 0.10, 1.0);
              var silverLining = Math.pow(sunDot, 6.0) * clamp(1.0 - cov * 0.75, 0.15, 1.0);

              var cr = lerp(38, 248, sunLit) + silverLining * 52;
              var cg = lerp(52, 212, sunLit) + silverLining * 36;
              var cb = lerp(78, 160, sunLit) + silverLining * 14;

              var mist = Math.pow(1.0 - h, 4.2) * 0.60;
              cr = lerp(cr, horR, mist);
              cg = lerp(cg, horG, mist);
              cb = lerp(cb, horB, mist);

              r = lerp(r, cr, cloudAlpha);
              g = lerp(g, cg, cloudAlpha);
              b = lerp(b, cb, cloudAlpha);
            }
          }

          var mie = Math.pow(sunDot, 8.5) * 0.44 + Math.pow(sunDot, 60.0) * 0.82 + Math.pow(sunDot, 360.0) * 1.40;
          r += 255 * mie * 0.92;
          g += 226 * mie * 0.82;
          b += 160 * mie * 0.58;
        } else {
          var depth = clamp(-dy, 0.0, 1.0);
          var reflSun = Math.max(0, dx * ux + (-dy) * uy + dz * uz);
          var waterGlint = Math.pow(reflSun, 14.0) * 0.52;
          r = lerp(54, 10, Math.pow(depth, 0.55)) + 212 * waterGlint;
          g = lerp(74, 30, Math.pow(depth, 0.55)) + 175 * waterGlint;
          b = lerp(102, 58, Math.pow(depth, 0.55)) + 92 * waterGlint;
        }

        var p = (y * cw + x) * 4;
        data[p] = clamp(Math.round(r), 0, 255);
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

    var hzGrad = ctx.createLinearGradient(0, H * 0.43, 0, H * 0.54);
    hzGrad.addColorStop(0.0, 'rgba(245, 206, 134, 0.0)');
    hzGrad.addColorStop(0.58, 'rgba(238, 196, 124, 0.14)');
    hzGrad.addColorStop(0.75, 'rgba(182, 160, 136, 0.18)');
    hzGrad.addColorStop(1.0, 'rgba(22, 44, 74, 0.0)');
    ctx.fillStyle = hzGrad;
    ctx.fillRect(0, H * 0.43, W, H * 0.11);

    var tex = configureTex(THREE, new THREE.CanvasTexture(c), maxAniso, 4);
    if (THREE.EquirectangularReflectionMapping) {
      tex.mapping = THREE.EquirectangularReflectionMapping;
    }
    tex.wrapS = THREE.RepeatWrapping;
    tex.wrapT = THREE.ClampToEdgeWrapping;
    return tex;
  }

  // Procedural Soft Squircle Contact-Shadow Map for Baked Ambient Occlusion
  function makeBakedAoShadowTexture(THREE) {
    var S = 256;
    var c = document.createElement('canvas');
    c.width = S;
    c.height = S;
    var ctx = c.getContext('2d');
    var img = ctx.createImageData(S, S);
    var d = img.data;
    var half = S * 0.5;

    for (var y = 0; y < S; y++) {
      var ny = Math.abs((y + 0.5 - half) / half);
      for (var x = 0; x < S; x++) {
        var nx = Math.abs((x + 0.5 - half) / half);
        var dist = Math.pow(Math.pow(nx, 2.8) + Math.pow(ny, 2.8), 1.0 / 2.8);
        var t = clamp(1.0 - dist / 0.95, 0.0, 1.0);
        var alpha = Math.pow(t, 1.85) * 0.72;
        var p = (y * S + x) * 4;
        d[p] = 4;
        d[p + 1] = 6;
        d[p + 2] = 11;
        d[p + 3] = clamp(Math.round(alpha * 255), 0, 255);
      }
    }
    ctx.putImageData(img, 0, 0);
    var tex = new THREE.CanvasTexture(c);
    tex.generateMipmaps = true;
    tex.minFilter = THREE.LinearMipmapLinearFilter;
    tex.magFilter = THREE.LinearFilter;
    tex.needsUpdate = true;
    return tex;
  }

  function makeBannerPlaqueTexture(THREE, maxAniso, textStr) {
    var W = 2048, H = 320;
    var c = document.createElement('canvas');
    c.width = W;
    c.height = H;
    var ctx = c.getContext('2d');

    var bg = ctx.createLinearGradient(0, 0, 0, H);
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
    ctx.font = '900 116px "Liberation Serif", "Georgia", serif';
    if (typeof ctx.letterSpacing !== 'undefined') {
      ctx.letterSpacing = '14px';
    }

    ctx.fillStyle = 'rgba(0, 0, 0, 0.92)';
    ctx.fillText(textStr, W * 0.5 + 4, H * 0.53 + 5, W - 100);

    var tg = ctx.createLinearGradient(0, H * 0.20, 0, H * 0.82);
    tg.addColorStop(0.0, '#FFFDF4');
    tg.addColorStop(0.3, '#FDE68A');
    tg.addColorStop(0.58, '#D4AF37');
    tg.addColorStop(0.85, '#FFF2C2');
    tg.addColorStop(1.0, '#AD8333');
    ctx.fillStyle = tg;
    ctx.fillText(textStr, W * 0.5, H * 0.53, W - 100);

    return configureTex(THREE, new THREE.CanvasTexture(c), maxAniso, 4);
  }

  // High-Contrast Silk-Matte Carrara-Alabaster & Deep Atlantis-Sapphire Pantheon Floor (#ECEAE4 / #E2DFD7, #94A3B8 veins, #133B5C border)
  function makeHallOfHeroesMarbleTexture(THREE, maxAniso) {
    var S = 1024;
    var c = document.createElement('canvas');
    c.width = S;
    c.height = S;
    var ctx = c.getContext('2d');
    var cx = S * 0.5, cy = S * 0.5;

    var rg = ctx.createRadialGradient(cx, cy, 40, cx, cy, S * 0.68);
    rg.addColorStop(0.0, '#ECEAE4');
    rg.addColorStop(0.65, '#E2DFD7');
    rg.addColorStop(1.0, '#D8D4CA');
    ctx.fillStyle = rg;
    ctx.fillRect(0, 0, S, S);

    var slabs = 8;
    var step = S / slabs;
    var rng = mulberry32(20261445);
    for (var r = 0; r < slabs; r++) {
      for (var col = 0; col < slabs; col++) {
        var shade = Math.round((rng() - 0.5) * 10);
        ctx.fillStyle = 'rgba(' + (232 + shade) + ', ' + (229 + shade) + ', ' + (222 + shade) + ', 0.44)';
        ctx.fillRect(col * step + 1.5, r * step + 1.5, step - 3, step - 3);
      }
    }

    // Fine Cool Silver-Slate Carrara Marble Veins (#94A3B8)
    for (var i = 0; i < 54; i++) {
      ctx.strokeStyle = i % 3 === 0 ? 'rgba(148, 163, 184, 0.28)' : 'rgba(100, 116, 139, 0.22)';
      ctx.lineWidth = i % 4 === 0 ? 2.1 : 1.2;
      ctx.beginPath();
      var x = rng() * S;
      var y = rng() * S;
      ctx.moveTo(x, y);
      for (var s = 0; s < 6; s++) {
        x += (rng() - 0.42) * 185;
        y += (rng() - 0.48) * 145;
        ctx.lineTo(x, y);
      }
      ctx.stroke();
    }

    ctx.strokeStyle = 'rgba(148, 163, 184, 0.34)';
    ctx.lineWidth = 1.6;
    for (var g = 1; g < slabs; g++) {
      var p = g * step;
      ctx.beginPath(); ctx.moveTo(p, 0); ctx.lineTo(p, S); ctx.stroke();
      ctx.beginPath(); ctx.moveTo(0, p); ctx.lineTo(S, p); ctx.stroke();
    }

    // Deep Atlantis-Sapphire Outer Frame (#133B5C)
    ctx.strokeStyle = '#133B5C';
    ctx.lineWidth = 44;
    ctx.strokeRect(42, 42, S - 84, S - 84);

    ctx.strokeStyle = '#0E2B45';
    ctx.lineWidth = 6;
    ctx.strokeRect(68, 68, S - 136, S - 136);

    ctx.fillStyle = 'rgba(19, 59, 92, 0.14)';
    ctx.fillRect(cx - 88, 68, 176, S - 136);
    ctx.strokeStyle = '#133B5C';
    ctx.lineWidth = 4;
    ctx.strokeRect(cx - 88, 68, 176, S - 136);

    ctx.fillStyle = '#133B5C';
    ctx.beginPath();
    ctx.arc(cx, cy - 48, 196, 0, Math.PI * 2);
    ctx.fill();

    ctx.fillStyle = '#ECEAE4';
    ctx.beginPath();
    ctx.arc(cx, cy - 48, 164, 0, Math.PI * 2);
    ctx.fill();

    ctx.strokeStyle = '#133B5C';
    ctx.lineWidth = 5;
    ctx.beginPath();
    ctx.arc(cx, cy - 48, 112, 0, Math.PI * 2);
    ctx.stroke();

    for (var k = 0; k < 8; k++) {
      var ang = (k / 8) * Math.PI * 2;
      ctx.strokeStyle = k % 2 === 0 ? '#133B5C' : 'rgba(19, 59, 92, 0.48)';
      ctx.lineWidth = k % 2 === 0 ? 4.5 : 2.2;
      ctx.beginPath();
      ctx.moveTo(cx + Math.cos(ang) * 26, (cy - 48) + Math.sin(ang) * 26);
      ctx.lineTo(cx + Math.cos(ang) * 158, (cy - 48) + Math.sin(ang) * 158);
      ctx.stroke();
    }

    return configureTex(THREE, new THREE.CanvasTexture(c), maxAniso, 4);
  }

  // Funki TV-Screen Face Texture (Lv 1: sad, Lv 2-3: focused, Lv 4-9: joyful smile, Lv 10: glowing violet eyes!)
  function makeFunkiScreenFaceTexture(THREE, maxAniso, level) {
    var W = 256, H = 192;
    var c = document.createElement('canvas');
    c.width = W;
    c.height = H;
    var ctx = c.getContext('2d');

    var bg = ctx.createRadialGradient(W * 0.5, H * 0.5, 10, W * 0.5, H * 0.5, W * 0.65);
    bg.addColorStop(0, level === 10 ? '#1e1033' : '#141923');
    bg.addColorStop(1, '#07090e');
    ctx.fillStyle = bg;
    ctx.fillRect(0, 0, W, H);

    // Subtle CRT scanlines
    ctx.fillStyle = 'rgba(255, 255, 255, 0.03)';
    for (var y = 0; y < H; y += 4) {
      ctx.fillRect(0, y, W, 1);
    }

    var eyeColor = '#FDE68A';
    var glowColor = 'rgba(253, 230, 138, 0.55)';
    if (level === 10) {
      eyeColor = '#D8B4FE'; // Luminous Amethyst Violet Eyes for Lv 10!
      glowColor = 'rgba(192, 132, 252, 0.85)';
    } else if (level === 9) {
      eyeColor = '#7DD3FC';
      glowColor = 'rgba(56, 189, 248, 0.72)';
    } else if (level === 1) {
      eyeColor = '#D6C79C';
      glowColor = 'rgba(197, 160, 89, 0.28)';
    }

    ctx.save();
    ctx.shadowColor = glowColor;
    ctx.shadowBlur = level >= 8 ? 18 : 10;
    ctx.fillStyle = eyeColor;
    ctx.strokeStyle = eyeColor;
    ctx.lineCap = 'round';

    if (level === 1) {
      // Sad droopy eyes & melancholic downturned mouth
      for (var sx = -1; sx <= 1; sx += 2) {
        var ex = W * 0.5 + sx * 46;
        ctx.beginPath();
        ctx.ellipse(ex, H * 0.46, 16, 13, sx * 0.22, 0, Math.PI * 2);
        ctx.fill();
        ctx.lineWidth = 4;
        ctx.beginPath();
        ctx.moveTo(ex - sx * 16, H * 0.28);
        ctx.lineTo(ex + sx * 18, H * 0.34);
        ctx.stroke();
      }
      ctx.lineWidth = 5;
      ctx.beginPath();
      ctx.arc(W * 0.5, H * 0.78, 22, Math.PI * 1.18, Math.PI * 1.82, false);
      ctx.stroke();
    } else {
      // Bright heroic eyes
      for (var hx = -1; hx <= 1; hx += 2) {
        var hexX = W * 0.5 + hx * 48;
        ctx.beginPath();
        ctx.ellipse(hexX, H * 0.44, 17, level >= 4 ? 20 : 16, 0, 0, Math.PI * 2);
        ctx.fill();

        // Starlight pupil glint
        ctx.fillStyle = '#FFFFFF';
        ctx.beginPath();
        ctx.arc(hexX + 5, H * 0.39, 5, 0, Math.PI * 2);
        ctx.fill();
        ctx.fillStyle = eyeColor;
      }

      ctx.lineWidth = 5.5;
      if (level >= 4) {
        // Confident warm smile
        ctx.beginPath();
        ctx.arc(W * 0.5, H * 0.64, 24, Math.PI * 0.15, Math.PI * 0.85, false);
        ctx.stroke();
      } else {
        // Determined gentle apprentice expression
        ctx.beginPath();
        ctx.arc(W * 0.5, H * 0.66, 16, Math.PI * 0.22, Math.PI * 0.78, false);
        ctx.stroke();
      }
    }
    ctx.restore();

    return configureTex(THREE, new THREE.CanvasTexture(c), maxAniso, 4);
  }

  // "Funki" Engraved Chestplate Emblem
  function makeFunkiChestEmblemTexture(THREE, maxAniso, bgHex, borderHex, textHex) {
    var W = 256, H = 128;
    var c = document.createElement('canvas');
    c.width = W;
    c.height = H;
    var ctx = c.getContext('2d');

    ctx.fillStyle = bgHex;
    ctx.fillRect(0, 0, W, H);
    ctx.strokeStyle = borderHex;
    ctx.lineWidth = 7;
    ctx.strokeRect(8, 8, W - 16, H - 16);

    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.font = '900 54px "Georgia", "Segoe UI", serif';
    ctx.fillStyle = textHex;
    ctx.fillText('Funki', W * 0.5, H * 0.54, W - 36);

    return configureTex(THREE, new THREE.CanvasTexture(c), maxAniso, 2);
  }

  function paintHeroPedestalCanvas(c, slotNumber, hero) {
    var W = c.width, H = c.height;
    var ctx = c.getContext('2d');
    var isPlaceholder = !hero || !!hero.is_placeholder;
    var isMe = !isPlaceholder && !!hero.is_me;

    var bg = ctx.createRadialGradient(W * 0.5, H * 0.5, 20, W * 0.5, H * 0.5, W * 0.62);
    bg.addColorStop(0, isMe ? '#241b09' : '#141720');
    bg.addColorStop(0.7, '#0a0c12');
    bg.addColorStop(1, '#06070b');
    ctx.fillStyle = bg;
    ctx.fillRect(0, 0, W, H);

    ctx.strokeStyle = isMe ? '#FDE68A' : '#C5A059';
    ctx.lineWidth = isMe ? 13 : 10;
    ctx.strokeRect(14, 14, W - 28, H - 28);
    ctx.strokeStyle = 'rgba(253, 230, 138, 0.5)';
    ctx.lineWidth = 2.5;
    ctx.strokeRect(30, 30, W - 60, H - 60);

    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';

    var cleanName = (!isPlaceholder && hero.display_name)
      ? String(hero.display_name).replace(/\s[\u2013\u2014]\s/g, ' · ').trim()
      : '';

    if (cleanName) {
      var lvl = clamp(Number(hero.level) || 1, 1, 10);
      var rkRaw = String(hero.rank_class || 'C').replace(/\s[\u2013\u2014]\s/g, ' · ').trim().toUpperCase();
      var rk = rkRaw.indexOf('KLASSE') === 0 ? rkRaw : ('KLASSE ' + rkRaw);

      ctx.fillStyle = isMe ? '#FDE68A' : '#C5A059';
      ctx.font = '800 38px "Liberation Sans", "Segoe UI", sans-serif';
      ctx.fillText('#' + slotNumber + ' · ' + rk + ' · LVL ' + lvl + (isMe ? ' · DU' : ''), W * 0.5, H * 0.27, W - 90);

      ctx.fillStyle = 'rgba(197, 160, 89, 0.45)';
      ctx.fillRect(W * 0.18, H * 0.42, W * 0.64, 2.5);

      var ng = ctx.createLinearGradient(0, H * 0.46, 0, H * 0.86);
      ng.addColorStop(0, '#FFFDF5');
      ng.addColorStop(0.35, '#FDE68A');
      ng.addColorStop(0.7, '#D4AF37');
      ng.addColorStop(1, '#B88D3B');
      ctx.fillStyle = ng;
      ctx.font = '900 82px "Liberation Sans", "Segoe UI", sans-serif';
      ctx.fillText(cleanName, W * 0.5, H * 0.67, W - 88);
    } else {
      ctx.fillStyle = 'rgba(197, 160, 89, 0.85)';
      ctx.font = '800 36px "Liberation Sans", "Segoe UI", sans-serif';
      ctx.fillText('✦ EHRENPLATZ #' + slotNumber + ' ✦', W * 0.5, H * 0.28, W - 90);

      ctx.fillStyle = 'rgba(197, 160, 89, 0.32)';
      ctx.fillRect(W * 0.22, H * 0.44, W * 0.56, 2);

      ctx.fillStyle = '#FDE68A';
      ctx.font = '800 68px "Liberation Sans", "Segoe UI", sans-serif';
      ctx.fillText('Freier Ehrenplatz', W * 0.5, H * 0.67, W - 88);
    }
  }

  function paintHeroSteleCanvas(c, slotNumber, hero) {
    var W = c.width, H = c.height;
    var ctx = c.getContext('2d');
    var isPlaceholder = !hero || !!hero.is_placeholder;
    var isMe = !isPlaceholder && !!hero.is_me;

    ctx.fillStyle = isMe ? '#1f1708' : '#090b10';
    ctx.fillRect(0, 0, W, H);
    ctx.strokeStyle = isMe ? '#FDE68A' : '#C5A059';
    ctx.lineWidth = isMe ? 10 : 7;
    ctx.strokeRect(10, 10, W - 20, H - 20);

    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';

    ctx.fillStyle = '#D4AF37';
    ctx.font = '900 38px "Liberation Sans", sans-serif';
    ctx.fillText('✦', W * 0.5, H * 0.16);

    var cleanName = (!isPlaceholder && hero.display_name)
      ? String(hero.display_name).replace(/\s[\u2013\u2014]\s/g, ' · ').trim()
      : '';

    if (cleanName) {
      var lvl = clamp(Number(hero.level) || 1, 1, 10);
      var rkRaw = String(hero.rank_class || 'C').replace(/\s[\u2013\u2014]\s/g, ' · ').trim().toUpperCase();
      var rk = rkRaw.indexOf('KLASSE') === 0 ? rkRaw : ('KLASSE ' + rkRaw);

      ctx.fillStyle = '#FDE68A';
      ctx.font = '800 40px "Liberation Sans", sans-serif';
      ctx.fillText(cleanName, W * 0.5, H * 0.42, W - 36);

      ctx.fillStyle = '#C5A059';
      ctx.font = '700 26px "Liberation Sans", sans-serif';
      ctx.fillText('#' + slotNumber + ' · ' + rk, W * 0.5, H * 0.63, W - 36);
      ctx.fillText('LVL ' + lvl + (isMe ? ' · DU' : ''), W * 0.5, H * 0.79, W - 36);
    } else {
      ctx.fillStyle = 'rgba(197, 160, 89, 0.85)';
      ctx.font = '700 28px "Liberation Sans", sans-serif';
      ctx.fillText('#' + slotNumber, W * 0.5, H * 0.36);

      ctx.fillStyle = '#FDE68A';
      ctx.font = '800 33px "Liberation Sans", sans-serif';
      ctx.fillText('Freier', W * 0.5, H * 0.56, W - 36);
      ctx.fillText('Ehrenplatz', W * 0.5, H * 0.70, W - 36);
    }
  }

  function loadThreeScript() {
    if (window.THREE) {
      return Promise.resolve(window.THREE);
    }
    if (window.__sfThreeLoadingPromise) {
      return window.__sfThreeLoadingPromise;
    }
    window.__sfThreeLoadingPromise = new Promise(function (resolve, reject) {
      var s = document.createElement('script');
      s.src = '/vendor/three/three.min.js';
      s.async = true;
      s.onload = function () {
        if (window.THREE) {
          resolve(window.THREE);
        } else {
          reject(new Error('Three.js konnte nicht initialisiert werden.'));
        }
      };
      s.onerror = function () {
        window.__sfThreeLoadingPromise = null;
        reject(new Error('Three.js konnte nicht geladen werden.'));
      };
      document.head.appendChild(s);
    });
    return window.__sfThreeLoadingPromise;
  }

  function makeSparkParticleTexture(THREE) {
    var S = 64;
    var c = document.createElement('canvas');
    c.width = S;
    c.height = S;
    var ctx = c.getContext('2d');
    var g = ctx.createRadialGradient(S * 0.5, S * 0.5, 1, S * 0.5, S * 0.5, S * 0.48);
    g.addColorStop(0.0, 'rgba(255, 255, 255, 1.0)');
    g.addColorStop(0.28, 'rgba(255, 238, 170, 0.92)');
    g.addColorStop(0.65, 'rgba(212, 175, 55, 0.35)');
    g.addColorStop(1.0, 'rgba(197, 160, 89, 0.0)');
    ctx.fillStyle = g;
    ctx.fillRect(0, 0, S, S);
    var tex = new THREE.CanvasTexture(c);
    tex.needsUpdate = true;
    return tex;
  }

  // Zero-Dependency Native Binary GLB 2.0 Parser (0 external CDN requests!)
  function parseGlbBinary(THREE, arrayBuffer, maxAniso, disposablesList) {
    if (!arrayBuffer || arrayBuffer.byteLength < 20) {
      return Promise.reject(new Error('Ungueltiger GLB-Datenstrom.'));
    }

    var dv = new DataView(arrayBuffer);
    var magic = dv.getUint32(0, true);
    var version = dv.getUint32(4, true);
    if (magic !== 0x46546C67 || version !== 2) {
      return Promise.reject(new Error('Nicht unterstuetztes GLB-2.0-Format.'));
    }

    var jsonObj = null;
    var binOffset = 0;
    var binLength = 0;
    var offset = 12;

    while (offset + 8 <= arrayBuffer.byteLength) {
      var chunkLen = dv.getUint32(offset, true);
      var chunkType = dv.getUint32(offset + 4, true);
      var dataStart = offset + 8;
      if (dataStart + chunkLen > arrayBuffer.byteLength) break;

      if (chunkType === 0x4E4F534A) {
        var jsonBytes = new Uint8Array(arrayBuffer, dataStart, chunkLen);
        var jsonStr = '';
        if (typeof TextDecoder !== 'undefined') {
          jsonStr = new TextDecoder('utf-8').decode(jsonBytes);
        } else {
          for (var bi = 0; bi < jsonBytes.length; bi++) {
            jsonStr += String.fromCharCode(jsonBytes[bi]);
          }
        }
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

    var COMP_SPECS = {
      5120: { Ctor: Int8Array, bytes: 1, read: function (v, o) { return v.getInt8(o); } },
      5121: { Ctor: Uint8Array, bytes: 1, read: function (v, o) { return v.getUint8(o); } },
      5122: { Ctor: Int16Array, bytes: 2, read: function (v, o) { return v.getInt16(o, true); } },
      5123: { Ctor: Uint16Array, bytes: 2, read: function (v, o) { return v.getUint16(o, true); } },
      5125: { Ctor: Uint32Array, bytes: 4, read: function (v, o) { return v.getUint32(o, true); } },
      5126: { Ctor: Float32Array, bytes: 4, read: function (v, o) { return v.getFloat32(o, true); } }
    };
    var TYPE_COUNTS = { SCALAR: 1, VEC2: 2, VEC3: 3, VEC4: 4, MAT4: 16 };

    function readAccessorArray(accIdx) {
      if (accIdx === undefined || accIdx === null || !jsonObj.accessors || !jsonObj.accessors[accIdx]) {
        return null;
      }
      var acc = jsonObj.accessors[accIdx];
      var bv = (jsonObj.bufferViews && jsonObj.bufferViews[acc.bufferView]) ? jsonObj.bufferViews[acc.bufferView] : null;
      var spec = COMP_SPECS[acc.componentType];
      var itemSize = TYPE_COUNTS[acc.type] || 1;
      if (!bv || !spec) return null;

      var count = acc.count || 0;
      var byteStart = binOffset + (bv.byteOffset || 0) + (acc.byteOffset || 0);
      var packedStride = itemSize * spec.bytes;
      var stride = bv.byteStride || packedStride;

      if (stride === packedStride && (byteStart % spec.bytes) === 0) {
        return {
          array: new spec.Ctor(arrayBuffer, byteStart, count * itemSize).slice(),
          itemSize: itemSize
        };
      }

      var out = new spec.Ctor(count * itemSize);
      for (var i = 0; i < count; i++) {
        var baseOff = byteStart + i * stride;
        for (var c = 0; c < itemSize; c++) {
          out[i * itemSize + c] = spec.read(dv, baseOff + c * spec.bytes);
        }
      }
      return { array: out, itemSize: itemSize };
    }

    var localTexPromises = {};
    var localMatPromises = {};

    function loadEmbeddedTexture(texIndex, isSrgb) {
      if (texIndex === undefined || texIndex === null || !jsonObj.textures || !jsonObj.textures[texIndex]) {
        return Promise.resolve(null);
      }
      var key = texIndex + (isSrgb ? ':srgb' : ':lin');
      if (localTexPromises[key]) return localTexPromises[key];

      var texDef = jsonObj.textures[texIndex];
      var imgDef = (jsonObj.images && texDef.source !== undefined) ? jsonObj.images[texDef.source] : null;
      if (!imgDef || imgDef.bufferView === undefined || !jsonObj.bufferViews || !jsonObj.bufferViews[imgDef.bufferView]) {
        return Promise.resolve(null);
      }

      var bv = jsonObj.bufferViews[imgDef.bufferView];
      var imgStart = binOffset + (bv.byteOffset || 0);
      var imgBytes = new Uint8Array(arrayBuffer, imgStart, bv.byteLength);
      var blob = new Blob([imgBytes], { type: imgDef.mimeType || 'image/jpeg' });
      var blobUrl = URL.createObjectURL(blob);

      localTexPromises[key] = new Promise(function (resolve) {
        var imgEl = new Image();
        imgEl.onload = function () {
          URL.revokeObjectURL(blobUrl);
          var tex = new THREE.Texture(imgEl);
          tex.flipY = false;
          tex.wrapS = THREE.RepeatWrapping;
          tex.wrapT = THREE.RepeatWrapping;
          tex.generateMipmaps = true;
          tex.minFilter = THREE.LinearMipmapLinearFilter;
          tex.magFilter = THREE.LinearFilter;
          tex.anisotropy = Math.min(maxAniso || 4, 4);
          if (isSrgb) {
            if ('colorSpace' in tex && THREE.SRGBColorSpace) {
              tex.colorSpace = THREE.SRGBColorSpace;
            } else if (THREE.sRGBEncoding) {
              tex.encoding = THREE.sRGBEncoding;
            }
          } else {
            if ('colorSpace' in tex && typeof THREE.NoColorSpace !== 'undefined') {
              tex.colorSpace = THREE.NoColorSpace;
            } else if (THREE.LinearEncoding) {
              tex.encoding = THREE.LinearEncoding;
            }
          }
          tex.needsUpdate = true;
          if (disposablesList) disposablesList.push(tex);
          resolve(tex);
        };
        imgEl.onerror = function () {
          URL.revokeObjectURL(blobUrl);
          resolve(null);
        };
        imgEl.src = blobUrl;
      });

      return localTexPromises[key];
    }

    function buildMaterial(matIndex) {
      var key = (matIndex === undefined || matIndex === null) ? 'default' : String(matIndex);
      if (localMatPromises[key]) return localMatPromises[key];

      var matDef = (jsonObj.materials && jsonObj.materials[matIndex]) ? jsonObj.materials[matIndex] : {};
      var pbr = matDef.pbrMetallicRoughness || {};
      var baseIdx = (pbr.baseColorTexture && pbr.baseColorTexture.index !== undefined) ? pbr.baseColorTexture.index : null;
      var mrIdx = (pbr.metallicRoughnessTexture && pbr.metallicRoughnessTexture.index !== undefined) ? pbr.metallicRoughnessTexture.index : null;
      var normIdx = (matDef.normalTexture && matDef.normalTexture.index !== undefined) ? matDef.normalTexture.index : null;
      var emisIdx = (matDef.emissiveTexture && matDef.emissiveTexture.index !== undefined) ? matDef.emissiveTexture.index : null;

      localMatPromises[key] = Promise.all([
        loadEmbeddedTexture(baseIdx, true),
        loadEmbeddedTexture(mrIdx, false),
        loadEmbeddedTexture(normIdx, false),
        loadEmbeddedTexture(emisIdx, true)
      ]).then(function (texArr) {
        var baseTex = texArr[0];
        var mrTex = texArr[1];
        var normTex = texArr[2];
        var emisTex = texArr[3];

        var bcf = Array.isArray(pbr.baseColorFactor) ? pbr.baseColorFactor : [1, 1, 1, 1];
        var rawMetal = (typeof pbr.metallicFactor === 'number') ? pbr.metallicFactor : (mrTex ? 1.0 : 0.16);
        var safeMetalness = mrTex ? clamp(rawMetal * 0.34, 0.14, 0.36) : clamp(rawMetal, 0.12, 0.28);
        var rawRough = (typeof pbr.roughnessFactor === 'number') ? pbr.roughnessFactor : 0.78;
        var safeRoughness = mrTex ? clamp(rawRough * 0.74, 0.32, 0.86) : clamp(rawRough * 0.84, 0.38, 0.88);

        var opts = {
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
          var ns = (matDef.normalTexture && typeof matDef.normalTexture.scale === 'number') ? matDef.normalTexture.scale : 0.85;
          opts.normalScale = new THREE.Vector2(ns, ns);
        }
        if (emisTex) {
          opts.emissiveMap = emisTex;
          var ef = Array.isArray(matDef.emissiveFactor) ? matDef.emissiveFactor : [1, 1, 1];
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

        var mat = new THREE.MeshStandardMaterial(opts);
        if (disposablesList) disposablesList.push(mat);
        return mat;
      });

      return localMatPromises[key];
    }

    function buildNodeAsync(nodeIdx) {
      var nDef = (jsonObj.nodes && jsonObj.nodes[nodeIdx]) ? jsonObj.nodes[nodeIdx] : null;
      if (!nDef) return Promise.resolve(new THREE.Group());

      var nodeObj = new THREE.Group();
      if (Array.isArray(nDef.matrix) && nDef.matrix.length === 16) {
        var m4 = new THREE.Matrix4();
        m4.fromArray(nDef.matrix);
        m4.decompose(nodeObj.position, nodeObj.quaternion, nodeObj.scale);
      } else {
        if (Array.isArray(nDef.translation)) nodeObj.position.fromArray(nDef.translation);
        if (Array.isArray(nDef.rotation)) nodeObj.quaternion.fromArray(nDef.rotation);
        if (Array.isArray(nDef.scale)) nodeObj.scale.fromArray(nDef.scale);
      }

      var primTasks = [];
      if (nDef.mesh !== undefined && jsonObj.meshes && jsonObj.meshes[nDef.mesh]) {
        var prims = jsonObj.meshes[nDef.mesh].primitives || [];
        for (var pi = 0; pi < prims.length; pi++) {
          (function (prim) {
            primTasks.push(buildMaterial(prim.material).then(function (mat) {
              var geo = new THREE.BufferGeometry();
              var attrs = prim.attributes || {};
              var posData = readAccessorArray(attrs.POSITION);
              if (!posData) return;
              geo.setAttribute('position', new THREE.BufferAttribute(posData.array, 3));

              var normData = readAccessorArray(attrs.NORMAL);
              if (normData) {
                geo.setAttribute('normal', new THREE.BufferAttribute(normData.array, 3));
              }

              var uvData = readAccessorArray(attrs.TEXCOORD_0);
              if (uvData) {
                geo.setAttribute('uv', new THREE.BufferAttribute(uvData.array, 2));
              }

              var idxData = readAccessorArray(prim.indices);
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
          })(prims[pi]);
        }
      }

      var childIndices = Array.isArray(nDef.children) ? nDef.children : [];
      var childTasks = [];
      for (var ci = 0; ci < childIndices.length; ci++) {
        childTasks.push(buildNodeAsync(childIndices[ci]).then(function (childGrp) {
          nodeObj.add(childGrp);
        }));
      }

      return Promise.all(primTasks.concat(childTasks)).then(function () {
        return nodeObj;
      });
    }

    var sceneIdx = (typeof jsonObj.scene === 'number') ? jsonObj.scene : 0;
    var rootNodes = (jsonObj.scenes && jsonObj.scenes[sceneIdx] && Array.isArray(jsonObj.scenes[sceneIdx].nodes))
      ? jsonObj.scenes[sceneIdx].nodes
      : [0];

    var rootGroup = new THREE.Group();
    var rootPromises = [];
    for (var ri = 0; ri < rootNodes.length; ri++) {
      rootPromises.push(buildNodeAsync(rootNodes[ri]).then(function (child) {
        rootGroup.add(child);
      }));
    }

    return Promise.all(rootPromises).then(function () {
      return {
        scene: rootGroup,
        animations: Array.isArray(jsonObj.animations) ? jsonObj.animations : []
      };
    });
  }

  function FunkenwerkHeroes3DEngine() {
    this.initialized = false;
    this.running = false;
    this.rafId = 0;
    this.maxAniso = 4;
    this.clockTime = 0;
    this.lastFrameMs = 0;
    this.renderScale = 1.0;
    this.smoothDt = 0.0166;

    this.modalEl = null;
    this.canvas = null;
    this.statusPillEl = null;
    this.cloudVeilEl = null;
    this.prevBodyOverflow = '';

    this.THREE = null;
    this.renderer = null;
    this.scene = null;
    this.camera = null;

    this.heroesPayload = {
      founding_era: true,
      total_heroes: 0,
      my_hero_slot: null,
      customer_logged_in: false,
      customer_opt_in: false,
      level_models: {},
      heroes: []
    };

    // RAM Cache Map<level, THREE.Group> so no .glb model is ever downloaded or parsed twice!
    this.glbTemplateCache = new Map();
    this.glbLoadingPromises = new Map();
    this.glbDisposables = [];
    this._monumentGeneration = 0;

    this.slotAnchors = {};
    this.interactiveTargets = [];
    this.dynamicTextures = [];
    this.pantheonDynamicGroup = null;
    this.animatedFunkiFigures = [];
    this.pedestalSparkMeta = [];
    this.pedestalSparkPosAttr = null;

    this.viewMode = 'orbit';
    this.flight = null;
    this.orbit = null;

    var self = this;
    this._boundLoop = function (ts) { self._tick(ts); };
    this._boundResize = function () { self.resize(); };
    this._boundPageHide = function () { self.dispose(); };
    this._boundKeydown = function (e) {
      if (e.key === 'Escape' && self.running) {
        self.close();
      }
    };
  }

  FunkenwerkHeroes3DEngine.prototype._ensureModalDom = function () {
    if (this.modalEl && document.body.contains(this.modalEl)) {
      return;
    }

    var root = document.createElement('div');
    root.id = 'sf-funkenwerk-heroes-modal';
    root.setAttribute('role', 'dialog');
    root.setAttribute('aria-modal', 'true');
    root.setAttribute('aria-label', 'Funkenwerk 3D und Halle der Helden');
    root.style.cssText = [
      'position:fixed',
      'inset:0',
      'z-index:2147483000',
      'display:none',
      'background:#f8f5ee',
      'font-family:system-ui,-apple-system,"Segoe UI",sans-serif',
      'user-select:none',
      'overflow:hidden'
    ].join(';');

    root.innerHTML = [
      '<canvas id="sf-funkenwerk-heroes-canvas" style="position:absolute;inset:0;width:100%;height:100%;display:block;touch-action:none;cursor:grab;"></canvas>',
      '<div id="sf-fw-cloud-veil" style="position:absolute;inset:0;pointer-events:none;background:radial-gradient(circle at 50% 50%, rgba(255,251,235,0.85), rgba(248,245,238,0.45));opacity:0;transition:opacity 180ms linear;"></div>',
      '<div style="position:absolute;top:14px;left:12px;right:12px;z-index:20;display:flex;flex-wrap:wrap;align-items:center;justify-content:center;gap:8px;pointer-events:none;">',
      '  <div style="pointer-events:auto;display:flex;flex-wrap:wrap;align-items:center;justify-content:center;gap:6px;padding:7px 10px;border-radius:9999px;background:rgba(255,255,255,0.95);border:1px solid rgba(197,160,89,0.55);box-shadow:0 10px 28px rgba(17,24,39,0.14);">',
      '    <button type="button" data-fw-nav="orbit" style="padding:7px 13px;border-radius:9999px;border:1px solid rgba(197,160,89,0.45);background:#ffffff;color:#111827;font-size:12px;font-weight:800;cursor:pointer;transition:all 150ms ease;">🪐 Anflug</button>',
      '    <button type="button" data-fw-nav="city" style="padding:7px 13px;border-radius:9999px;border:1px solid rgba(197,160,89,0.45);background:#ffffff;color:#111827;font-size:12px;font-weight:800;cursor:pointer;transition:all 150ms ease;">🏙️ Funkenwerk</button>',
      '    <button type="button" data-fw-nav="hall" style="padding:7px 13px;border-radius:9999px;border:1px solid rgba(197,160,89,0.45);background:#ffffff;color:#111827;font-size:12px;font-weight:800;cursor:pointer;transition:all 150ms ease;">🏛️ Halle der Helden</button>',
      '    <button type="button" data-fw-nav="my_place" style="padding:7px 13px;border-radius:9999px;border:1px solid rgba(197,160,89,0.45);background:#ffffff;color:#111827;font-size:12px;font-weight:800;cursor:pointer;transition:all 150ms ease;">⭐ Mein Platz</button>',
      '    <button type="button" data-fw-nav="close" style="padding:7px 13px;border-radius:9999px;border:1px solid #111827;background:#111827;color:#ffffff;font-size:12px;font-weight:800;cursor:pointer;transition:all 150ms ease;">✕ Schließen</button>',
      '  </div>',
      '</div>',
      '<div style="position:absolute;bottom:18px;left:16px;right:16px;z-index:20;display:flex;justify-content:center;pointer-events:none;">',
      '  <div id="sf-fw-status-pill" style="pointer-events:auto;padding:8px 18px;border-radius:9999px;background:rgba(255,255,255,0.95);border:1px solid rgba(197,160,89,0.6);color:#111827;font-size:12px;font-weight:800;letter-spacing:0.02em;box-shadow:0 8px 24px rgba(17,24,39,0.12);text-align:center;">Wasserstadt Funkenwerk · Halle der Helden</div>',
      '</div>'
    ].join('');

    document.body.appendChild(root);
    this.modalEl = root;
    this.canvas = root.querySelector('#sf-funkenwerk-heroes-canvas');
    this.statusPillEl = root.querySelector('#sf-fw-status-pill');
    this.cloudVeilEl = root.querySelector('#sf-fw-cloud-veil');

    var self = this;
    var buttons = root.querySelectorAll('[data-fw-nav]');
    for (var i = 0; i < buttons.length; i++) {
      buttons[i].addEventListener('click', function (ev) {
        var action = ev.currentTarget.getAttribute('data-fw-nav');
        if (action === 'orbit') self.startOrbitalFlyIn();
        else if (action === 'city') self.flyToCityOverview(false);
        else if (action === 'hall') self.flyToHallOfHeroes(false);
        else if (action === 'my_place') self.flyToMyPlace();
        else if (action === 'close') self.close();
      });
    }
  };

  FunkenwerkHeroes3DEngine.prototype._setStatusText = function (text) {
    if (this.statusPillEl) {
      this.statusPillEl.textContent = String(text).replace(/\s[\u2013\u2014]\s/g, ' · ');
    }
  };

  FunkenwerkHeroes3DEngine.prototype._updateNavHighlight = function () {
    if (!this.modalEl) return;
    var buttons = this.modalEl.querySelectorAll('[data-fw-nav]');
    for (var i = 0; i < buttons.length; i++) {
      var btn = buttons[i];
      var key = btn.getAttribute('data-fw-nav');
      if (key === 'close') continue;
      var isAct = key === this.viewMode;
      btn.style.background = isAct ? '#C5A059' : '#ffffff';
      btn.style.borderColor = isAct ? '#C5A059' : 'rgba(197,160,89,0.45)';
      btn.style.color = '#111827';
    }
  };

  FunkenwerkHeroes3DEngine.prototype._fetchHeroesData = function () {
    var self = this;
    return fetch('/funkenwerk-welt/heroes-data', {
      method: 'GET',
      credentials: 'same-origin',
      cache: 'no-store',
      headers: { 'Accept': 'application/json' }
    })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (json) {
        if (json && json.success && Array.isArray(json.heroes)) {
          self.heroesPayload = json;
        }
        return self.heroesPayload;
      })
      .catch(function () {
        return self.heroesPayload;
      });
  };

  FunkenwerkHeroes3DEngine.prototype._initScene = function (THREE) {
    if (this.initialized) return;
    this.THREE = THREE;

    this.flight = {
      active: false,
      type: 'none',
      elapsed: 0,
      duration: 5.4,
      fromPos: new THREE.Vector3(),
      fromTarget: new THREE.Vector3(),
      midPos: new THREE.Vector3(),
      toPos: new THREE.Vector3(),
      toTarget: new THREE.Vector3(),
      settledMode: 'city'
    };

    this.orbit = {
      target: new THREE.Vector3(0, 96, 0),
      desiredTarget: new THREE.Vector3(0, 96, 0),
      radius: 520,
      desiredRadius: 520,
      theta: 0.28,
      desiredTheta: 0.28,
      phi: 1.24,
      desiredPhi: 1.24,
      minRadius: 2.6,
      maxRadius: 5600,
      minY: 4.0,
      isDragging: false,
      dragButton: 0,
      lastX: 0,
      lastY: 0,
      movedDist: 0,
      pinchDist: 0
    };

    this.dummyObj = new THREE.Object3D();
    this.raycaster = new THREE.Raycaster();
    this.pointerNdc = new THREE.Vector2();

    this.renderer = new THREE.WebGLRenderer({
      canvas: this.canvas,
      antialias: true,
      alpha: false,
      powerPreference: 'high-performance',
      stencil: false
    });
    if ('outputColorSpace' in this.renderer && THREE.SRGBColorSpace) {
      this.renderer.outputColorSpace = THREE.SRGBColorSpace;
    } else if (THREE.sRGBEncoding) {
      this.renderer.outputEncoding = THREE.sRGBEncoding;
    }
    this.renderer.toneMapping = THREE.ACESFilmicToneMapping;
    this.renderer.toneMappingExposure = 1.10;
    this.renderer.setClearColor(0x030711, 1);

    this.maxAniso = Math.min(8, (this.renderer.capabilities && this.renderer.capabilities.getMaxAnisotropy) ? this.renderer.capabilities.getMaxAnisotropy() : 4);

    this.scene = new THREE.Scene();
    this.camera = new THREE.PerspectiveCamera(46, window.innerWidth / Math.max(1, window.innerHeight), 0.25, 26000);

    this._buildMaterials();
    this._buildPantheonLighting();
    this._buildCosmosAndSky();
    this._buildOrbitalPlanet();
    this._buildOcean();
    this._buildWaterMetropolis();
    this._buildSupertower();
    this._buildPantheonArchitecture();

    this._bindControls();
    window.addEventListener('resize', this._boundResize);
    window.addEventListener('keydown', this._boundKeydown);
    window.addEventListener('pagehide', this._boundPageHide);
    this.resize();

    this.initialized = true;
  };

  // Noble 3-Point Pantheon Lighting so MeshStandardMaterial on the real Tripo 3D Funki .glb models gleams richly!
  FunkenwerkHeroes3DEngine.prototype._buildPantheonLighting = function () {
    var THREE = this.THREE;

    var hemiLight = new THREE.HemisphereLight(0xFFFDF5, 0x2E3C56, 1.35);
    hemiLight.position.set(0, PANTHEON_Y + 34, PANTHEON_Z);
    this.scene.add(hemiLight);

    var ambLight = new THREE.AmbientLight(0xFFF8EB, 0.58);
    this.scene.add(ambLight);

    var keyLight = new THREE.DirectionalLight(0xFFEBC2, 1.75);
    keyLight.position.set(9.5, PANTHEON_Y + 24.0, PANTHEON_Z + 22.0);
    keyLight.target.position.set(0, PANTHEON_Y + 4.0, PANTHEON_Z - 5.0);
    this.scene.add(keyLight);
    this.scene.add(keyLight.target);

    var rimLight = new THREE.DirectionalLight(0xBAE6FD, 0.72);
    rimLight.position.set(-14.0, PANTHEON_Y + 17.0, PANTHEON_Z - 20.0);
    rimLight.target.position.set(0, PANTHEON_Y + 4.0, PANTHEON_Z - 4.0);
    this.scene.add(rimLight);
    this.scene.add(rimLight.target);
  };

  FunkenwerkHeroes3DEngine.prototype._buildMaterials = function () {
    var THREE = this.THREE;
    this.staticDisposables = [];
    this._pantheonRefVec = new THREE.Vector3(0, PANTHEON_Y + 4.0, PANTHEON_Z);

    var sunDir = new THREE.Vector3(-0.35, 0.32, -0.88).normalize();
    this.skyPanoramaTex = makePhotorealSkyPanoramaTexture(THREE, this.maxAniso, sunDir);
    this.staticDisposables.push(this.skyPanoramaTex);

    if (this.renderer && typeof THREE.PMREMGenerator === 'function') {
      var pmremGen = new THREE.PMREMGenerator(this.renderer);
      if (typeof pmremGen.compileEquirectangularShader === 'function') {
        pmremGen.compileEquirectangularShader();
      }
      this.pmremTarget = pmremGen.fromEquirectangular(this.skyPanoramaTex);
      this.scene.environment = this.pmremTarget.texture;
      this.staticDisposables.push(this.pmremTarget);
      pmremGen.dispose();
    }

    var mcGoldBright = makeLuxuryMatcap(THREE, this.maxAniso, '#D4AF37', '#FFFBEB', '#FDE68A', 38, 0.46);
    var mcGoldBrushed = makeLuxuryMatcap(THREE, this.maxAniso, '#B89146', '#FFF3C4', '#D4AF37', 22, 0.32);
    var mcGoldAntique = makeLuxuryMatcap(THREE, this.maxAniso, '#8F6A28', '#FDE68A', '#C5A059', 18, 0.28);
    var mcObsidian = makeLuxuryMatcap(THREE, this.maxAniso, '#11141C', '#687387', '#C5A059', 30, 0.22);
    var mcGunmetal = makeLuxuryMatcap(THREE, this.maxAniso, '#232936', '#8A96AC', '#64748B', 26, 0.26);
    var mcSilverSteel = makeLuxuryMatcap(THREE, this.maxAniso, '#CBD5E1', '#FFFFFF', '#94A3B8', 32, 0.34);
    var mcPlatinum = makeLuxuryMatcap(THREE, this.maxAniso, '#E2E8F0', '#FFFFFF', '#7DD3FC', 36, 0.42);
    var mcBurlap = makeLuxuryMatcap(THREE, this.maxAniso, '#7C6549', '#B59B78', '#5C4932', 8, 0.14);
    var mcBronzeLeather = makeLuxuryMatcap(THREE, this.maxAniso, '#8B4F22', '#E5A96C', '#C5A059', 16, 0.24);
    var mcScarf = makeLuxuryMatcap(THREE, this.maxAniso, '#7A1E24', '#D96B70', '#C5A059', 12, 0.20);
    var mcSapphireCrystal = makeLuxuryMatcap(THREE, this.maxAniso, '#0284C7', '#E0F2FE', '#7DD3FC', 40, 0.58);
    var mcAmethystCrystal = makeLuxuryMatcap(THREE, this.maxAniso, '#7E22CE', '#F3E8FF', '#D8B4FE', 42, 0.62);

    var facadeAtlas = makeSkyscraperFacadeAtlas(THREE, this.maxAniso);
    var stColorTex = facadeAtlas.colorTex.clone();
    stColorTex.repeat.set(2, 3);
    stColorTex.needsUpdate = true;
    var stEmisTex = facadeAtlas.emissiveTex.clone();
    stEmisTex.repeat.set(2, 3);
    stEmisTex.needsUpdate = true;
    var stRoughTex = facadeAtlas.roughnessTex.clone();
    stRoughTex.repeat.set(2, 3);
    stRoughTex.needsUpdate = true;

    var aoTex = makeBakedAoShadowTexture(THREE);
    this.staticDisposables.push(
      facadeAtlas.colorTex, facadeAtlas.emissiveTex, facadeAtlas.roughnessTex,
      stColorTex, stEmisTex, stRoughTex, aoTex
    );
    this.sparkSpriteTex = makeSparkParticleTexture(THREE);

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
        envMapIntensity: 1.32,
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
      goldAntique: new THREE.MeshMatcapMaterial({ matcap: mcGoldAntique }),
      obsidian: new THREE.MeshMatcapMaterial({ matcap: mcObsidian }),
      anthracite: new THREE.MeshMatcapMaterial({ matcap: mcGunmetal }),
      gunmetal: new THREE.MeshMatcapMaterial({ matcap: mcGunmetal }),
      silverSteel: new THREE.MeshMatcapMaterial({ matcap: mcSilverSteel }),
      platinum: new THREE.MeshMatcapMaterial({ matcap: mcPlatinum }),
      burlap: new THREE.MeshMatcapMaterial({ matcap: mcBurlap }),
      bronzeLeather: new THREE.MeshMatcapMaterial({ matcap: mcBronzeLeather }),
      scarf: new THREE.MeshMatcapMaterial({ matcap: mcScarf }),
      sapphireCrystal: new THREE.MeshMatcapMaterial({ matcap: mcSapphireCrystal }),
      amethystCrystal: new THREE.MeshMatcapMaterial({ matcap: mcAmethystCrystal }),
      alabasterStep: new THREE.MeshBasicMaterial({ color: 0xE2DFD7 }),
      sapphireTrim: new THREE.MeshBasicMaterial({ color: 0x133B5C }),
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
        envMapIntensity: 1.26
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
      plasmaBlue: new THREE.MeshBasicMaterial({ color: 0x38BDF8 }),
      gemYellow: new THREE.MeshBasicMaterial({ color: 0xFACC15 }),
      vialGreen: new THREE.MeshBasicMaterial({ color: 0x10B981 }),
      vialRed: new THREE.MeshBasicMaterial({ color: 0xEF4444 }),
      amethystGlow: new THREE.MeshBasicMaterial({ color: 0xC084FC }),
      mySlotGlow: new THREE.MeshBasicMaterial({
        color: 0xFDE68A,
        side: THREE.DoubleSide,
        polygonOffset: true,
        polygonOffsetFactor: -2,
        polygonOffsetUnits: -4
      }),
      pierVeins: new THREE.MeshBasicMaterial({
        color: 0xFFFFFF,
        polygonOffset: true,
        polygonOffsetFactor: -2,
        polygonOffsetUnits: -4
      })
    };
  };

  FunkenwerkHeroes3DEngine.prototype._buildCosmosAndSky = function () {
    var THREE = this.THREE;
    this.spaceGroup = new THREE.Group();
    this.scene.add(this.spaceGroup);

    this.skyUniforms = {
      uSpaceBlend: { value: 1.0 },
      uSunDir: { value: new THREE.Vector3(-0.35, 0.32, -0.88).normalize() },
      uSkyPano: { value: this.skyPanoramaTex }
    };

    var skyMat = new THREE.ShaderMaterial({
      side: THREE.BackSide,
      depthWrite: false,
      uniforms: this.skyUniforms,
      vertexShader: [
        'varying vec3 vWorldDir;',
        'void main() {',
        '  vec4 wp = modelMatrix * vec4(position, 1.0);',
        '  vWorldDir = normalize(position);',
        '  gl_Position = projectionMatrix * viewMatrix * wp;',
        '}'
      ].join('\n'),
      fragmentShader: [
        'uniform float uSpaceBlend;',
        'uniform vec3 uSunDir;',
        'uniform sampler2D uSkyPano;',
        'varying vec3 vWorldDir;',
        'void main() {',
        '  vec3 d = normalize(vWorldDir);',
        '  float h = clamp(d.y, -0.2, 1.0);',
        '  vec3 spaceCol = mix(vec3(0.015, 0.022, 0.045), vec3(0.004, 0.006, 0.015), clamp(h * 1.4 + 0.2, 0.0, 1.0));',
        '  float nebula = pow(max(0.0, dot(d, normalize(vec3(0.42, 0.36, -0.82)))), 3.0);',
        '  spaceCol += vec3(0.085, 0.062, 0.025) * nebula;',
        '  vec2 equirectUv = vec2(',
        '    atan(d.z, d.x) * 0.15915494 + 0.5,',
        '    asin(clamp(d.y, -1.0, 1.0)) * 0.31830989 + 0.5',
        '  );',
        '  vec3 citySky = texture2D(uSkyPano, equirectUv).rgb;',
        '  vec3 col = mix(citySky, spaceCol, uSpaceBlend);',
        '  gl_FragColor = vec4(col, 1.0);',
        '}'
      ].join('\n')
    });
    this.spaceGroup.add(new THREE.Mesh(new THREE.SphereGeometry(18500, 44, 28), skyMat));

    var rng = mulberry32(424242);
    var starCount = 2800;
    var posArr = new Float32Array(starCount * 3);
    var colArr = new Float32Array(starCount * 3);
    var palette = [
      [1.00, 0.93, 0.75],
      [0.96, 0.98, 1.00],
      [0.76, 0.88, 1.00],
      [0.88, 0.72, 0.40]
    ];

    for (var i = 0; i < starCount; i++) {
      var theta = rng() * Math.PI * 2;
      var phi = Math.acos(2 * rng() - 1);
      var rad = 11500 + rng() * 5000;
      posArr[i * 3] = rad * Math.sin(phi) * Math.cos(theta);
      posArr[i * 3 + 1] = rad * Math.cos(phi);
      posArr[i * 3 + 2] = rad * Math.sin(phi) * Math.sin(theta);

      var c = palette[Math.floor(rng() * palette.length)];
      var br = 0.58 + rng() * 0.42;
      colArr[i * 3] = c[0] * br;
      colArr[i * 3 + 1] = c[1] * br;
      colArr[i * 3 + 2] = c[2] * br;
    }

    var starGeo = new THREE.BufferGeometry();
    starGeo.setAttribute('position', new THREE.BufferAttribute(posArr, 3));
    starGeo.setAttribute('color', new THREE.BufferAttribute(colArr, 3));

    this.starMat = new THREE.PointsMaterial({
      size: 40,
      vertexColors: true,
      transparent: true,
      opacity: 0.92,
      blending: THREE.AdditiveBlending,
      depthWrite: false
    });
    this.spaceGroup.add(new THREE.Points(starGeo, this.starMat));
  };

  FunkenwerkHeroes3DEngine.prototype._buildOrbitalPlanet = function () {
    var THREE = this.THREE;
    this.planetGroup = new THREE.Group();
    this.planetGroup.position.set(0, -PLANET_RADIUS, 0);
    this.scene.add(this.planetGroup);

    var orbitalLightDir = new THREE.Vector3(0.28, 0.86, 0.42).normalize();
    this.planetSurfaceUniforms = {
      uTime: { value: 0 },
      uAlpha: { value: 1.0 },
      uLightDir: { value: orbitalLightDir }
    };

    var planetSurfMat = new THREE.ShaderMaterial({
      transparent: true,
      uniforms: this.planetSurfaceUniforms,
      vertexShader: [
        'varying vec3 vNormalObj;',
        'varying vec3 vWorldPos;',
        'varying vec3 vWorldNormal;',
        'void main() {',
        '  vNormalObj = normalize(position);',
        '  vec4 wp = modelMatrix * vec4(position, 1.0);',
        '  vWorldPos = wp.xyz;',
        '  vWorldNormal = normalize(mat3(modelMatrix) * normal);',
        '  gl_Position = projectionMatrix * viewMatrix * wp;',
        '}'
      ].join('\n'),
      fragmentShader: [
        'uniform float uTime;',
        'uniform float uAlpha;',
        'uniform vec3 uLightDir;',
        'varying vec3 vNormalObj;',
        'varying vec3 vWorldPos;',
        'varying vec3 vWorldNormal;',
        'void main() {',
        '  vec3 N = normalize(vWorldNormal);',
        '  vec3 V = normalize(cameraPosition - vWorldPos);',
        '  vec3 L = normalize(uLightDir);',
        '  float band = 0.5 + 0.5 * sin(vNormalObj.x * 9.0 + vNormalObj.z * 7.0);',
        '  vec3 baseCol = mix(vec3(0.020, 0.088, 0.185), vec3(0.055, 0.255, 0.395), band * 0.65);',
        '  float ndl = max(0.0, dot(N, L));',
        '  vec3 H = normalize(L + V);',
        '  float spec = pow(max(0.0, dot(N, H)), 38.0) * 0.68;',
        '  float fres = pow(1.0 - max(0.0, dot(N, V)), 3.2);',
        '  vec3 rimCol = mix(vec3(0.10, 0.54, 0.84), vec3(0.88, 0.70, 0.36), ndl) * fres * 0.85;',
        '  float distToCity = length(vNormalObj - vec3(0.0, 1.0, 0.0));',
        '  float pulse = 0.88 + 0.12 * sin(uTime * 3.0);',
        '  vec3 cityGlow = vec3(1.00, 0.86, 0.45) * (exp(-distToCity * 56.0) * 2.1 + exp(-distToCity * 14.0) * 0.62) * pulse;',
        '  vec3 col = baseCol * (0.08 + 0.94 * ndl) + vec3(1.0, 0.90, 0.62) * spec + rimCol + cityGlow;',
        '  gl_FragColor = vec4(col, uAlpha);',
        '}'
      ].join('\n')
    });
    this.planetGroup.add(new THREE.Mesh(new THREE.SphereGeometry(PLANET_RADIUS, 72, 54), planetSurfMat));
  };

  FunkenwerkHeroes3DEngine.prototype._buildOcean = function () {
    var THREE = this.THREE;
    this.oceanUniforms = {
      uTime: { value: 0 },
      uAlpha: { value: 1.0 },
      uSunDir: { value: new THREE.Vector3(-0.35, 0.32, -0.88).normalize() },
      uSkyPano: { value: this.skyPanoramaTex }
    };

    var oceanMat = new THREE.ShaderMaterial({
      transparent: true,
      uniforms: this.oceanUniforms,
      vertexShader: [
        'uniform float uTime;',
        'varying vec3 vWorldPos;',
        'void main() {',
        '  vec3 pos = position;',
        '  float r = length(pos.xz);',
        '  float swell = (sin(pos.x * 0.045 + uTime * 1.25) + cos(pos.z * 0.038 - uTime * 1.05)) * 0.25;',
        '  pos.y += swell * smoothstep(2400.0, 180.0, r);',
        '  vec4 wp = modelMatrix * vec4(pos, 1.0);',
        '  vWorldPos = wp.xyz;',
        '  gl_Position = projectionMatrix * viewMatrix * wp;',
        '}'
      ].join('\n'),
      fragmentShader: [
        'uniform float uTime;',
        'uniform float uAlpha;',
        'uniform vec3 uSunDir;',
        'uniform sampler2D uSkyPano;',
        'varying vec3 vWorldPos;',
        'vec3 getOceanNormal(vec2 p, float dist) {',
        '  float fade = smoothstep(3600.0, 120.0, dist);',
        '  float w1 = sin(p.x * 0.085 + p.y * 0.042 + uTime * 1.42);',
        '  float w2 = cos(p.x * -0.052 + p.y * 0.095 - uTime * 1.18);',
        '  float w3 = sin(p.x * 0.24 + p.y * -0.19 + uTime * 2.10) * fade;',
        '  float dx = 0.035 * w1 - 0.020 * w2 + 0.038 * w3;',
        '  float dz = 0.018 * w1 + 0.036 * w2 - 0.030 * w3;',
        '  return normalize(vec3(-dx, 1.0, -dz));',
        '}',
        'void main() {',
        '  vec2 xz = vWorldPos.xz;',
        '  float dist = length(xz);',
        '  vec3 V = normalize(cameraPosition - vWorldPos);',
        '  vec3 N = getOceanNormal(xz, dist);',
        '  vec3 L = normalize(uSunDir);',
        '  float lagoon = smoothstep(660.0, 160.0, dist);',
        '  vec3 waterBase = mix(vec3(0.020, 0.080, 0.160), vec3(0.042, 0.195, 0.305), lagoon * 0.78);',
        '  float fresnel = pow(1.0 - max(0.0, dot(N, V)), 3.8);',
        '  vec3 rawRefl = reflect(-V, N);',
        '  vec3 reflDir = normalize(vec3(rawRefl.x, abs(rawRefl.y) + 0.015, rawRefl.z));',
        '  vec2 reflUv = vec2(',
        '    atan(reflDir.z, reflDir.x) * 0.15915494 + 0.5,',
        '    asin(clamp(reflDir.y, -1.0, 1.0)) * 0.31830989 + 0.5',
        '  );',
        '  vec3 skyRefl = texture2D(uSkyPano, reflUv).rgb * 0.90;',
        '  vec3 col = mix(waterBase, skyRefl, clamp(0.18 + fresnel * 0.74, 0.0, 0.90));',
        '  vec3 H = normalize(L + V);',
        '  float spec = pow(max(0.0, dot(N, H)), 150.0) * 1.45 + pow(max(0.0, dot(N, H)), 22.0) * 0.26;',
        '  col += vec3(1.00, 0.92, 0.68) * spec;',
        '  float coreGlow = exp(-max(0.0, dist - 140.0) * 0.012) * smoothstep(90.0, 165.0, dist);',
        '  col += vec3(0.86, 0.68, 0.34) * coreGlow * 0.35;',
        '  float fog = smoothstep(3200.0, 9000.0, dist);',
        '  vec3 horCol = texture2D(uSkyPano, vec2(atan(vWorldPos.z, vWorldPos.x) * 0.15915494 + 0.5, 0.505)).rgb;',
        '  col = mix(col, horCol * 0.88, fog);',
        '  gl_FragColor = vec4(col, uAlpha);',
        '}'
      ].join('\n')
    });

    var oceanGeo = new THREE.RingGeometry(0.1, 9600, 84, 20);
    oceanGeo.rotateX(-Math.PI / 2);
    this.oceanMesh = new THREE.Mesh(oceanGeo, oceanMat);
    this.scene.add(this.oceanMesh);
  };

  FunkenwerkHeroes3DEngine.prototype._buildWaterMetropolis = function () {
    var THREE = this.THREE;
    var self = this;
    this.cityGroup = new THREE.Group();
    this.scene.add(this.cityGroup);

    var rng = mulberry32(20260707);

    // Cleanly stepped >= 0.70m vertical separation across all Core Island terraces (zero coplanar overlap!)
    var coreTiers = [
      { r: 148.0, h: 2.80, y: 1.40, mat: this.mats.anthracite },
      { r: 149.2, h: 0.68, y: 3.16, mat: this.mats.goldBrushed },
      { r: 132.0, h: 2.90, y: 4.98, mat: this.mats.obsidian },
      { r: 133.2, h: 0.68, y: 6.80, mat: this.mats.goldBright },
      { r: 96.0,  h: 2.30, y: 8.32, mat: this.mats.obsidian }
    ];
    for (var ct = 0; ct < coreTiers.length; ct++) {
      var tier = coreTiers[ct];
      var tm = new THREE.Mesh(new THREE.CylinderGeometry(tier.r * 0.97, tier.r, tier.h, 16), tier.mat);
      tm.position.y = tier.y;
      this.cityGroup.add(tm);
    }

    var wingShape = new THREE.Shape();
    wingShape.moveTo(-22, 275);
    wingShape.lineTo(-68, 325);
    wingShape.lineTo(-74, 428);
    wingShape.lineTo(-46, 488);
    wingShape.lineTo(-19, 502);
    wingShape.lineTo(-19, 456);
    wingShape.lineTo(-7, 456);
    wingShape.lineTo(-7, 504);
    wingShape.lineTo(7, 504);
    wingShape.lineTo(7, 456);
    wingShape.lineTo(19, 456);
    wingShape.lineTo(19, 502);
    wingShape.lineTo(46, 488);
    wingShape.lineTo(74, 428);
    wingShape.lineTo(68, 325);
    wingShape.lineTo(22, 275);
    wingShape.closePath();

    // wingDeck top at Y = 4.80 + 0.70 = 5.50m; wingGoldTrim wider seawall promenade stepped at Y = 4.70 + 0.32 = 5.02m (0.48m step!)
    var wingDeckGeo = new THREE.ExtrudeGeometry(wingShape, {
      depth: 4.1, bevelEnabled: true, bevelThickness: 0.70, bevelSize: 0.90, bevelSegments: 1
    });
    wingDeckGeo.rotateX(Math.PI / 2);

    var wingGoldTrimGeo = new THREE.ExtrudeGeometry(wingShape, {
      depth: 0.72, bevelEnabled: true, bevelThickness: 0.32, bevelSize: 2.35, bevelSegments: 1
    });
    wingGoldTrimGeo.rotateX(Math.PI / 2);

    var towerTransforms = [];
    var goldFrameTransforms = [];
    var spireTransforms = [];
    var aoTransforms = [];
    var veinItems = [];

    function pushMat(arr, x, y, z, sx, sy, sz, rotY) {
      self.dummyObj.position.set(x, y, z);
      self.dummyObj.rotation.set(0, rotY || 0, 0);
      self.dummyObj.scale.set(sx, sy, sz);
      self.dummyObj.updateMatrix();
      arr.push(self.dummyObj.matrix.clone());
    }

    function pushAo(x, y, z, sx, sz, rotY) {
      self.dummyObj.position.set(x, y, z);
      self.dummyObj.rotation.set(0, rotY || 0, 0);
      self.dummyObj.scale.set(sx, 1, sz);
      self.dummyObj.updateMatrix();
      aoTransforms.push(self.dummyObj.matrix.clone());
    }

    function pushVein(x, y, z, sx, sy, sz, rotY, hexCol) {
      self.dummyObj.position.set(x, y, z);
      self.dummyObj.rotation.set(0, rotY || 0, 0);
      self.dummyObj.scale.set(sx, sy, sz);
      self.dummyObj.updateMatrix();
      veinItems.push({ matrix: self.dummyObj.matrix.clone(), color: new THREE.Color(hexCol) });
    }

    pushAo(0, 9.55, 0, 94, 94, 0);
    pushAo(0, 6.51, 0, 212, 212, 0);

    function addSteppedSkyscraper(wx, baseY, wz, baseW, baseD, totalH, rotY, accentHex) {
      pushAo(wx, baseY + 0.08, wz, baseW * 1.66, baseD * 1.66, rotY);

      var h1 = totalH * 0.64;
      pushMat(towerTransforms, wx, baseY + h1 * 0.5, wz, baseW, h1, baseD, rotY);
      pushMat(goldFrameTransforms, wx, baseY + h1 + 0.62, wz, baseW * 1.07, 1.2, baseD * 1.07, rotY);

      var h2 = totalH * 0.26;
      var w2 = baseW * 0.74;
      var d2 = baseD * 0.74;
      pushMat(towerTransforms, wx, baseY + h1 + h2 * 0.5, wz, w2, h2, d2, rotY);
      pushMat(goldFrameTransforms, wx, baseY + h1 + h2 + 0.54, wz, w2 * 1.09, 1.0, d2 * 1.09, rotY);

      var h3 = totalH * 0.12;
      var w3 = baseW * 0.48;
      pushMat(goldFrameTransforms, wx, baseY + h1 + h2 + h3 * 0.5, wz, w3, h3, w3, rotY);
      var spireH = Math.max(8, totalH * 0.20);
      pushMat(spireTransforms, wx, baseY + totalH + spireH * 0.48, wz, Math.max(0.7, baseW * 0.08), spireH, Math.max(0.7, baseW * 0.08), rotY);

      if (accentHex) {
        var vx = wx + Math.sin(rotY) * (baseD * 0.535);
        var vz = wz + Math.cos(rotY) * (baseD * 0.535);
        pushVein(vx, baseY + (h1 + h2) * 0.5, vz, 0.58, (h1 + h2) * 0.90, 0.58, rotY, accentHex);
      }
    }

    var coreRings = [
      { count: 14, rad: 46, minH: 132, maxH: 192, w: 12.2 },
      { count: 20, rad: 74, minH: 90, maxH: 148, w: 11.4 },
      { count: 26, rad: 102, minH: 60, maxH: 112, w: 10.8 },
      { count: 28, rad: 126, minH: 38, maxH: 78, w: 10.0 }
    ];
    for (var rIdx = 0; rIdx < coreRings.length; rIdx++) {
      var ring = coreRings[rIdx];
      for (var ci = 0; ci < ring.count; ci++) {
        var ang = (ci / ring.count) * Math.PI * 2 + rIdx * 0.21 + (rng() - 0.5) * 0.06;
        var wx = Math.cos(ang) * ring.rad;
        var wz = Math.sin(ang) * ring.rad;
        if (wz > 30 && Math.abs(wx) < 30) {
          continue;
        }
        var h = lerp(ring.minH, ring.maxH, rng());
        var w = ring.w * (0.86 + rng() * 0.28);
        var coreBaseY = ring.rad < 95.0 ? 9.47 : 6.43;
        addSteppedSkyscraper(wx, coreBaseY, wz, w, w, h, -ang + Math.PI * 0.5, (ci % 4 === 0) ? PIER_ACCENTS[ci % 7] : null);
      }
    }

    // Shortened end-faces (-0.30m per end) & >= 0.38m vertical step separation
    var causewayGeo = new THREE.BoxGeometry(30.0, 3.20, 134.0);
    var causewayGoldTrimGeo = new THREE.BoxGeometry(32.6, 0.68, 133.4);
    var causewayParapetGeo = new THREE.BoxGeometry(1.10, 0.42, 133.4);
    var landingPadOctGeo = new THREE.CylinderGeometry(34, 37, 4.2, 8);
    var landingPadRingGeo = new THREE.TorusGeometry(24, 0.62, 8, 8);
    landingPadRingGeo.rotateX(Math.PI / 2);

    this.pierAngles = [];

    for (var k = 0; k < 7; k++) {
      var theta = Math.PI * 0.5 + (k / 7) * Math.PI * 2;
      this.pierAngles.push(theta);

      var pierGrp = new THREE.Group();
      var rotY = Math.atan2(Math.cos(theta), Math.sin(theta));
      pierGrp.rotation.y = rotY;
      this.cityGroup.add(pierGrp);

      var cwDeck = new THREE.Mesh(causewayGeo, k === 0 ? this.mats.alabasterStep : this.mats.obsidian);
      cwDeck.position.set(0, 3.48, 211.5);
      pierGrp.add(cwDeck);

      var cwTrim = new THREE.Mesh(causewayGoldTrimGeo, this.mats.goldBright);
      cwTrim.position.set(0, 4.36, 211.5);
      pierGrp.add(cwTrim);

      for (var cpSide = -1; cpSide <= 1; cpSide += 2) {
        var parapet = new THREE.Mesh(causewayParapetGeo, this.mats.goldBright);
        parapet.position.set(cpSide * 14.45, 5.29, 211.5);
        pierGrp.add(parapet);
      }

      var wingDeck = new THREE.Mesh(wingDeckGeo, this.mats.anthracite);
      wingDeck.position.set(0, 4.80, 0);
      pierGrp.add(wingDeck);

      var wingGold = new THREE.Mesh(wingGoldTrimGeo, this.mats.goldBright);
      wingGold.position.set(0, 4.70, 0);
      pierGrp.add(wingGold);

      var linkBridge = new THREE.Mesh(new THREE.BoxGeometry(18, 2.50, 26.4), this.mats.obsidian);
      linkBridge.position.set(0, 3.70, 506);
      pierGrp.add(linkBridge);

      var pad = new THREE.Mesh(landingPadOctGeo, this.mats.obsidian);
      pad.position.set(0, 2.40, 540);
      pierGrp.add(pad);

      var padGoldRing = new THREE.Mesh(landingPadRingGeo, this.mats.goldBright);
      padGoldRing.position.set(0, 4.92, 540);
      pierGrp.add(padGoldRing);

      pushAo(Math.sin(rotY) * 540, 0.16, Math.cos(rotY) * 540, 88, 88, rotY);

      var pierTowerSpots = [
        [-44, 342, 66, 10.2], [44, 342, 68, 10.2],
        [-52, 374, 58, 9.6], [52, 374, 62, 9.6],
        [-48, 408, 52, 9.4], [48, 408, 54, 9.4],
        [-38, 438, 44, 8.8], [38, 438, 46, 8.8],
        [-26, 462, 34, 8.2], [26, 462, 36, 8.2]
      ];
      if (k !== 0) {
        pierTowerSpots.push(
          [0, 332, 110, 13.0], [-18, 348, 90, 11.0], [18, 348, 92, 11.0],
          [0, 376, 100, 12.4], [0, 418, 70, 10.8]
        );
      }

      for (var pts = 0; pts < pierTowerSpots.length; pts++) {
        var sp = pierTowerSpots[pts];
        var twx = sp[0] * Math.cos(rotY) + sp[1] * Math.sin(rotY);
        var twz = -sp[0] * Math.sin(rotY) + sp[1] * Math.cos(rotY);
        addSteppedSkyscraper(twx, 5.50, twz, sp[3], sp[3] * 0.94, sp[2] * (0.9 + rng() * 0.2), rotY, PIER_ACCENTS[k]);
      }
    }

    var ringRadii = [{ r: 266, width: 14.0, y: 3.55 }, { r: 412, width: 16.0, y: 3.95 }];
    for (var rbIdx = 0; rbIdx < ringRadii.length; rbIdx++) {
      var rb = ringRadii[rbIdx];
      var deckTop = rb.y + 1.0;
      for (var rk = 0; rk < 7; rk++) {
        var a1 = this.pierAngles[rk];
        var a2 = this.pierAngles[(rk + 1) % 7];
        var p1x = Math.cos(a1) * rb.r, p1z = Math.sin(a1) * rb.r;
        var p2x = Math.cos(a2) * rb.r, p2z = Math.sin(a2) * rb.r;
        var dx = p2x - p1x, dz = p2z - p1z;
        var spanLen = Math.hypot(dx, dz);
        var deckLen = Math.max(12, spanLen - 2.4);
        var railLen = Math.max(10, deckLen - 0.60);
        var midX = (p1x + p2x) * 0.5, midZ = (p1z + p2z) * 0.5;
        var bridgeRotY = Math.atan2(dx, dz);

        var deck = new THREE.Mesh(new THREE.BoxGeometry(rb.width, 2.0, deckLen), this.mats.obsidian);
        deck.position.set(midX, rb.y, midZ);
        deck.rotation.y = bridgeRotY;
        this.cityGroup.add(deck);

        var rail = new THREE.Mesh(new THREE.BoxGeometry(rb.width + 2.2, 0.56, railLen), this.mats.goldBrushed);
        rail.position.set(midX, deckTop - 0.68, midZ);
        rail.rotation.y = bridgeRotY;
        this.cityGroup.add(rail);

        for (var rs = -1; rs <= 1; rs += 2) {
          var rOff = rs * (rb.width * 0.5 - 0.48);
          var rx = midX + rOff * Math.cos(bridgeRotY);
          var rz = midZ - rOff * Math.sin(bridgeRotY);
          var sideBar = new THREE.Mesh(new THREE.BoxGeometry(0.95, 0.42, railLen), this.mats.goldBright);
          sideBar.position.set(rx, deckTop + 0.21, rz);
          sideBar.rotation.y = bridgeRotY;
          this.cityGroup.add(sideBar);
        }
      }
    }

    var unitBox = new THREE.BoxGeometry(1, 1, 1);
    var unitSpire = new THREE.ConeGeometry(0.5, 1, 6);
    var unitAoPlane = new THREE.PlaneGeometry(1, 1);
    unitAoPlane.rotateX(-Math.PI * 0.5);

    function commitInst(geo, mat, matrices) {
      if (!matrices.length) return null;
      var inst = new THREE.InstancedMesh(geo, mat, matrices.length);
      for (var idx = 0; idx < matrices.length; idx++) {
        inst.setMatrixAt(idx, matrices[idx]);
      }
      inst.instanceMatrix.needsUpdate = true;
      inst.frustumCulled = false;
      self.cityGroup.add(inst);
      return inst;
    }

    commitInst(unitBox, this.mats.skyscraperFacade, towerTransforms);
    commitInst(unitBox, this.mats.goldBrushed, goldFrameTransforms);
    commitInst(unitSpire, this.mats.goldBright, spireTransforms);

    var aoInst = commitInst(unitAoPlane, this.mats.bakedAo, aoTransforms);
    if (aoInst) aoInst.renderOrder = 1;

    if (veinItems.length > 0) {
      var veinInst = new THREE.InstancedMesh(unitBox, this.mats.pierVeins, veinItems.length);
      for (var vi = 0; vi < veinItems.length; vi++) {
        veinInst.setMatrixAt(vi, veinItems[vi].matrix);
        veinInst.setColorAt(vi, veinItems[vi].color);
      }
      veinInst.instanceMatrix.needsUpdate = true;
      if (veinInst.instanceColor) veinInst.instanceColor.needsUpdate = true;
      veinInst.frustumCulled = false;
      this.cityGroup.add(veinInst);
    }

    var gliderCount = 72;
    var gliderGeo = new THREE.ConeGeometry(1.1, 5.2, 4);
    gliderGeo.rotateX(Math.PI * 0.5);
    gliderGeo.scale(1.6, 0.42, 1.0);
    this.gliderInst = new THREE.InstancedMesh(gliderGeo, this.mats.beaconGold, gliderCount);
    this.gliderInst.frustumCulled = false;
    this.cityGroup.add(this.gliderInst);

    this.gliderData = [];
    for (var gi = 0; gi < gliderCount; gi++) {
      var mode = gi < 35 ? 'pier' : (gi < 58 ? 'ring' : 'tower_orbit');
      this.gliderData.push({
        mode: mode,
        pierIdx: gi % 7,
        ringR: (gi % 2 === 0) ? 266 : 412,
        phase: rng(),
        speed: (0.030 + rng() * 0.032) * (gi % 2 === 0 ? 1 : -1),
        alt: mode === 'tower_orbit' ? (120 + rng() * 110) : (26.5 + (gi % 3) * 4.5),
        laneOffset: (gi % 2 === 0 ? 9.5 : -9.5)
      });
    }
  };

  // Strictly Customer-Isolated Supertower ("MEIN SEELENFUNKE", ZERO internal admin throne room!)
  FunkenwerkHeroes3DEngine.prototype._buildSupertower = function () {
    var THREE = this.THREE;
    var towerGrp = new THREE.Group();
    this.cityGroup.add(towerGrp);

    var basePodium = new THREE.Mesh(new THREE.CylinderGeometry(30, 36, 17, 8), this.mats.obsidianPbr);
    basePodium.position.y = 17.5;
    towerGrp.add(basePodium);

    var baseGoldCollar = new THREE.Mesh(new THREE.CylinderGeometry(31.2, 31.2, 2.2, 8), this.mats.goldPbrBright);
    baseGoldCollar.position.y = 26.8;
    towerGrp.add(baseGoldCollar);

    var coreShaft = new THREE.Mesh(new THREE.CylinderGeometry(13.2, 20.5, 194, 8), this.mats.supertowerFacade);
    coreShaft.position.y = 123;
    towerGrp.add(coreShaft);

    for (var i = 0; i < 8; i++) {
      var ang = (i / 8) * Math.PI * 2 + Math.PI / 8;
      var rib = new THREE.Mesh(new THREE.BoxGeometry(1.75, 232, 2.8), this.mats.goldPbrBright);
      rib.position.set(Math.cos(ang) * 16.95, 138, Math.sin(ang) * 16.95);
      rib.rotation.y = -ang + Math.PI * 0.5;
      towerGrp.add(rib);
    }

    var wingHeights = [66, 98, 128, 158, 186, 212];
    for (var w = 0; w < 6; w++) {
      var wang = (w / 6) * Math.PI * 2 + 0.25;
      var wh = wingHeights[w];
      var dist = 20.5 - w * 1.15;
      var rad = 8.8 - w * 0.55;

      var wingShaft = new THREE.Mesh(new THREE.CylinderGeometry(rad * 0.84, rad, wh - 24, 6), this.mats.supertowerFacade);
      wingShaft.position.set(Math.cos(wang) * dist, 24 + (wh - 24) * 0.5, Math.sin(wang) * dist);
      towerGrp.add(wingShaft);

      var cap = new THREE.Mesh(new THREE.CylinderGeometry(rad * 0.94, rad * 0.98, 2.4, 6), this.mats.goldPbrBright);
      cap.position.set(Math.cos(wang) * dist, wh + 1.2, Math.sin(wang) * dist);
      towerGrp.add(cap);
    }

    var starkDeck = new THREE.Mesh(new THREE.BoxGeometry(44, 10.2, 32), this.mats.obsidianPbr);
    starkDeck.position.set(0, 238.5, 2.2);
    towerGrp.add(starkDeck);

    var starkGoldTop = new THREE.Mesh(new THREE.BoxGeometry(45.6, 1.5, 33.6), this.mats.goldPbrBright);
    starkGoldTop.position.set(0, 244.45, 2.2);
    towerGrp.add(starkGoldTop);

    var starkGoldBot = new THREE.Mesh(new THREE.BoxGeometry(45.6, 1.5, 33.6), this.mats.goldPbrBright);
    starkGoldBot.position.set(0, 232.55, 2.2);
    towerGrp.add(starkGoldBot);

    var signTex = makeBannerPlaqueTexture(THREE, this.maxAniso, 'MEIN SEELENFUNKE');
    var signMat = new THREE.MeshBasicMaterial({
      map: signTex,
      polygonOffset: true,
      polygonOffsetFactor: -2,
      polygonOffsetUnits: -4
    });
    var signGeo = new THREE.PlaneGeometry(42.5, 6.8);

    var frontSign = new THREE.Mesh(signGeo, signMat);
    frontSign.position.set(0, 238.5, 18.55);
    towerGrp.add(frontSign);

    var backSign = new THREE.Mesh(signGeo, signMat);
    backSign.position.set(0, 238.5, -14.15);
    backSign.rotation.y = Math.PI;
    towerGrp.add(backSign);

    var crownLantern = new THREE.Mesh(new THREE.CylinderGeometry(11.5, 15.5, 22, 16), this.mats.goldPbrBrushed);
    crownLantern.position.y = 256;
    towerGrp.add(crownLantern);

    var spireBase = new THREE.Mesh(new THREE.CylinderGeometry(3.2, 8.5, 18, 16), this.mats.goldPbrBright);
    spireBase.position.y = 276;
    towerGrp.add(spireBase);

    var spireNeedle = new THREE.Mesh(new THREE.ConeGeometry(2.8, 42, 16), this.mats.goldPbrBright);
    spireNeedle.position.y = 306;
    towerGrp.add(spireNeedle);

    var apexStar = new THREE.Mesh(new THREE.OctahedronGeometry(2.4, 0), this.mats.beaconGold);
    apexStar.position.y = 329;
    towerGrp.add(apexStar);
  };

  // ============================================================================
  // REAL COMPRESSED TRIPO 3D FUNKI (.GLB) LOADER + RAM CACHE (Map<level, Group>)
  // Verified Front Rotation: rotation.y = -Math.PI * 0.5 (TV Face & "Funki" Chest -> +Z)
  // ============================================================================
  FunkenwerkHeroes3DEngine.prototype._getFunkiAuraHex = function (lvl) {
    if (lvl === 10) return 0xA855F7;
    if (lvl === 9) return 0x38BDF8;
    if (lvl === 8) return 0xFDE68A;
    if (lvl === 7) return 0xD4AF37;
    if (lvl === 6) return 0xF43F5E;
    if (lvl === 5) return 0x10B981;
    if (lvl === 4) return 0x38BDF8;
    if (lvl === 3) return 0xEAB308;
    if (lvl === 2) return 0xD97706;
    return 0xC5A059;
  };

  FunkenwerkHeroes3DEngine.prototype._resolveFunkiModelUrl = function (lvl, hero) {
    if (hero && !hero.is_placeholder && hero.model_url) {
      return String(hero.model_url);
    }
    if (this.heroesPayload && this.heroesPayload.level_models && this.heroesPayload.level_models[lvl]) {
      return String(this.heroesPayload.level_models[lvl]);
    }
    return DEFAULT_FUNKI_GLB_URLS[lvl] || DEFAULT_FUNKI_GLB_URLS[1];
  };

  FunkenwerkHeroes3DEngine.prototype._loadFunkiGlbTemplate = function (rawLevel, modelUrl) {
    var self = this;
    var THREE = this.THREE;
    var lvl = clamp(parseInt(rawLevel, 10) || 1, 1, 10);

    if (this.glbTemplateCache.has(lvl)) {
      return Promise.resolve(this.glbTemplateCache.get(lvl));
    }
    if (this.glbLoadingPromises.has(lvl)) {
      return this.glbLoadingPromises.get(lvl);
    }

    var url = modelUrl || this._resolveFunkiModelUrl(lvl, null);
    var p = fetch(url, {
      method: 'GET',
      credentials: 'same-origin'
    })
      .then(function (resp) {
        if (!resp.ok) {
          throw new Error('GLB HTTP ' + resp.status);
        }
        return resp.arrayBuffer();
      })
      .then(function (buf) {
        return parseGlbBinary(THREE, buf, self.maxAniso, self.glbDisposables);
      })
      .then(function (parsed) {
        var sceneRoot = parsed.scene;
        sceneRoot.updateMatrixWorld(true);
        var bbox = new THREE.Box3().setFromObject(sceneRoot);
        var rawH = Math.max(0.001, bbox.max.y - bbox.min.y);
        var centerX = (bbox.min.x + bbox.max.x) * 0.5;
        var centerZ = (bbox.min.z + bbox.max.z) * 0.5;
        var minY = bbox.min.y;

        var targetH = (lvl === 1) ? 1.62 : 2.08;
        var normScale = targetH / rawH;
        sceneRoot.scale.setScalar(normScale);
        // Align boots flush on pedestal surface (bbox.min.y -> y = 0.000) and center X/Z
        sceneRoot.position.set(-centerX * normScale, -minY * normScale, -centerZ * normScale);

        var frontOriented = new THREE.Group();
        frontOriented.rotation.y = FUNKI_GLB_FRONT_ROTATION_Y;
        frontOriented.add(sceneRoot);

        var templateGroup = new THREE.Group();
        templateGroup.add(frontOriented);
        templateGroup.userData = {
          level: lvl,
          hasEmbeddedAnimations: !!(parsed.animations && parsed.animations.length > 0)
        };

        self.glbTemplateCache.set(lvl, templateGroup);
        self.glbLoadingPromises.delete(lvl);
        return templateGroup;
      })
      .catch(function (err) {
        self.glbLoadingPromises.delete(lvl);
        throw err;
      });

    this.glbLoadingPromises.set(lvl, p);
    return p;
  };

  FunkenwerkHeroes3DEngine.prototype._createFunkiCharacterByLevel = function (rawLevel, slotNum, scaleFactor, hero) {
    var THREE = this.THREE;
    var self = this;
    var lvl = clamp(parseInt(rawLevel, 10) || 1, 1, 10);
    var mountGen = this._monumentGeneration;

    var root = new THREE.Group();
    root.scale.setScalar(scaleFactor || 1.0);

    var auraHex = this._getFunkiAuraHex(lvl);
    var auraOuterMat = new THREE.MeshBasicMaterial({ color: auraHex });
    this.dynamicTextures.push({ dispose: function () { auraOuterMat.dispose(); } });
    var auraOuter = new THREE.Mesh(new THREE.TorusGeometry(0.96, 0.036, 10, 40), auraOuterMat);
    auraOuter.rotation.x = Math.PI * 0.5;
    auraOuter.position.y = 0.036;
    root.add(auraOuter);

    var auraInnerMat = new THREE.MeshBasicMaterial({
      color: 0xFDE68A,
      side: THREE.DoubleSide,
      transparent: true,
      opacity: 0.78
    });
    this.dynamicTextures.push({ dispose: function () { auraInnerMat.dispose(); } });
    var auraInner = new THREE.Mesh(new THREE.RingGeometry(0.58, 0.68, 8), auraInnerMat);
    auraInner.rotation.x = -Math.PI * 0.5;
    auraInner.position.y = 0.028;
    root.add(auraInner);

    var motionRig = new THREE.Group();
    root.add(motionRig);

    var animRecord = {
      motionRig: motionRig,
      auraOuter: auraOuter,
      auraInner: auraInner,
      level: lvl,
      isSitting: (lvl === 1),
      phase: slotNum * 1.17
    };
    this.animatedFunkiFigures.push(animRecord);

    var modelUrl = this._resolveFunkiModelUrl(lvl, hero || null);
    this._loadFunkiGlbTemplate(lvl, modelUrl)
      .then(function (templateGroup) {
        if (mountGen !== self._monumentGeneration || !self.THREE) return;
        var inst = templateGroup.clone(true);
        motionRig.add(inst);
      })
      .catch(function () {
        if (mountGen !== self._monumentGeneration || !self.THREE) return;
        var fallbackGrp = self._buildProceduralFallbackFunki(lvl, slotNum);
        motionRig.add(fallbackGrp);
      });

    return root;
  };

  FunkenwerkHeroes3DEngine.prototype._buildProceduralFallbackFunki = function (rawLevel, slotNum) {
    var THREE = this.THREE;
    var lvl = clamp(parseInt(rawLevel, 10) || 1, 1, 10);

    var root = new THREE.Group();

    // Choose Primary Armor & Trim Materials per Official Funki Level (1..10)
    var armorMat = this.mats.goldBright;
    var trimMat = this.mats.goldBrushed;
    var chestBg = '#141821';
    var chestBorder = '#C5A059';
    var chestText = '#FDE68A';
    var auraColorHex = 0xC5A059;

    if (lvl === 1) {
      armorMat = this.mats.burlap;
      trimMat = this.mats.bronzeLeather;
      chestBg = '#5c4932';
      chestBorder = '#a68963';
      chestText = '#efe4cc';
      auraColorHex = 0xC5A059;
    } else if (lvl === 2) {
      armorMat = this.mats.bronzeLeather;
      trimMat = this.mats.goldAntique;
      chestBg = '#4a2910';
      auraColorHex = 0xD97706;
    } else if (lvl === 3) {
      armorMat = this.mats.goldAntique;
      trimMat = this.mats.goldBright;
      auraColorHex = 0xEAB308;
    } else if (lvl === 4) {
      armorMat = this.mats.silverSteel;
      trimMat = this.mats.gunmetal;
      chestBg = '#1e293b';
      chestBorder = '#cbd5e1';
      chestText = '#f8fafc';
      auraColorHex = 0x38BDF8;
    } else if (lvl === 5) {
      armorMat = this.mats.platinum;
      trimMat = this.mats.goldBright;
      chestBg = '#0f172a';
      chestBorder = '#d4af37';
      auraColorHex = 0x10B981;
    } else if (lvl === 6) {
      armorMat = this.mats.gunmetal;
      trimMat = this.mats.silverSteel;
      chestBg = '#090d14';
      chestBorder = '#94a3b8';
      auraColorHex = 0xF43F5E;
    } else if (lvl === 7) {
      armorMat = this.mats.goldAntique;
      trimMat = this.mats.goldBright;
      auraColorHex = 0xD4AF37;
    } else if (lvl === 8) {
      armorMat = this.mats.goldBright;
      trimMat = this.mats.goldBrushed;
      auraColorHex = 0xFDE68A;
    } else if (lvl === 9) {
      armorMat = this.mats.platinum;
      trimMat = this.mats.sapphireCrystal;
      chestBg = '#082f49';
      chestBorder = '#7dd3fc';
      chestText = '#e0f2fe';
      auraColorHex = 0x38BDF8;
    } else if (lvl === 10) {
      armorMat = this.mats.goldBright;
      trimMat = this.mats.amethystCrystal;
      chestBg = '#2e1065';
      chestBorder = '#facc15';
      chestText = '#f3e8ff';
      auraColorHex = 0xA855F7;
    }

    var auraMat = new THREE.MeshBasicMaterial({ color: auraColorHex });
    var baseRing = new THREE.Mesh(new THREE.TorusGeometry(0.95, 0.042, 10, 36), auraMat);
    baseRing.rotation.x = Math.PI * 0.5;
    baseRing.position.y = 0.05;
    root.add(baseRing);

    var bodyRig = new THREE.Group();
    root.add(bodyRig);

    var isSitting = (lvl === 1);

    // 1. BOOTS & LEGS (Lv 1 sitting with legs forward; Lv 2..10 heroic standing stance)
    if (isSitting) {
      for (var slx = -0.18; slx <= 0.18; slx += 0.36) {
        var sitLeg = new THREE.Mesh(new THREE.CylinderGeometry(0.10, 0.09, 0.42, 12), armorMat);
        sitLeg.rotation.x = Math.PI * 0.46;
        sitLeg.position.set(slx, 0.16, 0.24);
        bodyRig.add(sitLeg);

        var sitBoot = new THREE.Mesh(new THREE.BoxGeometry(0.20, 0.16, 0.24), trimMat);
        sitBoot.position.set(slx, 0.15, 0.46);
        bodyRig.add(sitBoot);
      }
    } else {
      for (var lx = -0.20; lx <= 0.20; lx += 0.40) {
        var boot = new THREE.Mesh(new THREE.BoxGeometry(0.22, 0.20, 0.30), trimMat);
        boot.position.set(lx, 0.10, 0.04);
        bodyRig.add(boot);

        var shin = new THREE.Mesh(new THREE.CylinderGeometry(0.115, 0.10, 0.46, 12), armorMat);
        shin.position.set(lx, 0.38, 0);
        bodyRig.add(shin);
      }
    }

    // 2. TORSO, "Funki" CHESTPLATE & BELT
    var torsoY = isSitting ? 0.54 : 0.88;
    var upperGroup = new THREE.Group();
    upperGroup.position.set(0, torsoY, 0);
    if (isSitting) {
      upperGroup.rotation.x = 0.10;
    }
    bodyRig.add(upperGroup);

    var torsoMesh = new THREE.Mesh(new THREE.BoxGeometry(0.66, 0.62, 0.42), armorMat);
    upperGroup.add(torsoMesh);

    var chestTex = makeFunkiChestEmblemTexture(THREE, this.maxAniso, chestBg, chestBorder, chestText);
    this.dynamicTextures.push(chestTex);
    var chestPlate = new THREE.Mesh(
      new THREE.PlaneGeometry(0.48, 0.24),
      new THREE.MeshBasicMaterial({ map: chestTex })
    );
    chestPlate.position.set(0, 0.06, 0.216);
    upperGroup.add(chestPlate);

    var belt = new THREE.Mesh(new THREE.BoxGeometry(0.70, 0.10, 0.45), trimMat);
    belt.position.set(0, -0.24, 0);
    upperGroup.add(belt);

    var buckle = new THREE.Mesh(new THREE.BoxGeometry(0.14, 0.13, 0.05), this.mats.goldBright);
    buckle.position.set(0, -0.24, 0.23);
    upperGroup.add(buckle);

    // Lv 6 Special: Green & Red Belt Potion Vials!
    if (lvl === 6) {
      var greenVial = new THREE.Mesh(new THREE.CylinderGeometry(0.045, 0.045, 0.15, 10), this.mats.vialGreen);
      greenVial.position.set(-0.28, -0.26, 0.18);
      upperGroup.add(greenVial);

      var redVial = new THREE.Mesh(new THREE.CylinderGeometry(0.045, 0.045, 0.15, 10), this.mats.vialRed);
      redVial.position.set(0.28, -0.26, 0.18);
      upperGroup.add(redVial);
    }

    // Lv 2+ Scarf / Royal Cape
    if (lvl >= 2) {
      var capeMat = (lvl === 2) ? this.mats.scarf : (lvl === 10 ? this.mats.amethystCrystal : (lvl === 9 ? this.mats.sapphireCrystal : trimMat));
      if (lvl === 2) {
        var neckScarf = new THREE.Mesh(new THREE.TorusGeometry(0.26, 0.065, 8, 20), this.mats.scarf);
        neckScarf.rotation.x = Math.PI * 0.5;
        neckScarf.position.set(0, 0.33, 0.02);
        upperGroup.add(neckScarf);
      }
      var backCape = new THREE.Mesh(new THREE.BoxGeometry(0.60, 0.72, 0.05), capeMat);
      backCape.position.set(0, -0.10, -0.23);
      backCape.rotation.x = 0.14;
      upperGroup.add(backCape);
    }

    // 3. PAULDRONS & SHOULDER SPIKES / CRYSTALS (Lv 3..10)
    if (lvl >= 3) {
      for (var px = -0.40; px <= 0.40; px += 0.80) {
        var pSide = px > 0 ? 1 : -1;
        var pauldron = new THREE.Mesh(new THREE.SphereGeometry(0.18, 14, 12), trimMat);
        pauldron.scale.set(1.15, 0.82, 1.05);
        pauldron.position.set(px, 0.25, 0);
        upperGroup.add(pauldron);

        // Lv 5: Spiked Shoulders with Blue Sapphires
        if (lvl === 5) {
          var pSpike5 = new THREE.Mesh(new THREE.ConeGeometry(0.055, 0.22, 8), this.mats.goldBright);
          pSpike5.position.set(px + pSide * 0.06, 0.42, 0);
          pSpike5.rotation.z = -pSide * 0.35;
          upperGroup.add(pSpike5);

          var sapphGem = new THREE.Mesh(new THREE.OctahedronGeometry(0.065, 0), this.mats.plasmaBlue);
          sapphGem.position.set(px, 0.28, 0.15);
          upperGroup.add(sapphGem);
        }

        // Lv 8: Gold-Spiked Dragon Shoulders
        if (lvl === 8) {
          for (var sp8 = -1; sp8 <= 1; sp8 += 2) {
            var gSpike = new THREE.Mesh(new THREE.ConeGeometry(0.055, 0.26, 8), this.mats.goldBright);
            gSpike.position.set(px + pSide * 0.07, 0.42, sp8 * 0.06);
            gSpike.rotation.z = -pSide * 0.42;
            upperGroup.add(gSpike);
          }
        }

        // Lv 9 & Lv 10: Elemental Crystal Shoulder Spikes (Ice Sapphire / Amethyst)
        if (lvl === 9 || lvl === 10) {
          var cryMat = (lvl === 10) ? this.mats.amethystCrystal : this.mats.sapphireCrystal;
          var crySpike = new THREE.Mesh(new THREE.ConeGeometry(0.075, 0.34, 6), cryMat);
          crySpike.position.set(px + pSide * 0.08, 0.45, 0);
          crySpike.rotation.z = -pSide * 0.45;
          upperGroup.add(crySpike);
        }
      }
    }

    // 4. ARMS, HANDS & HANDHELD ITEMS (Lv 1 Wilted Flower, Lv 2 Plasma Tool, Lv 10 Dual Amethyst Arm-Blades!)
    for (var ax = -1; ax <= 1; ax += 2) {
      var armGrp = new THREE.Group();
      armGrp.position.set(ax * 0.43, 0.18, 0);
      if (isSitting && ax === 1) {
        armGrp.rotation.x = -0.55;
        armGrp.rotation.z = -0.15;
      } else if (lvl === 2 && ax === 1) {
        armGrp.rotation.x = -0.48;
        armGrp.rotation.z = -0.18;
      } else {
        armGrp.rotation.z = ax * -0.16;
      }
      upperGroup.add(armGrp);

      var armCyl = new THREE.Mesh(new THREE.CylinderGeometry(0.088, 0.078, 0.44, 12), armorMat);
      armCyl.position.y = -0.18;
      armGrp.add(armCyl);

      var hand = new THREE.Mesh(new THREE.SphereGeometry(0.09, 12, 10), trimMat);
      hand.position.y = -0.42;
      armGrp.add(hand);

      // Lv 1: Drooping Wilted Golden Flower in Right Hand (ax === 1)
      if (lvl === 1 && ax === 1) {
        var stemLow = new THREE.Mesh(new THREE.CylinderGeometry(0.016, 0.016, 0.28, 8), this.mats.bronzeLeather);
        stemLow.position.set(0, -0.34, 0.14);
        stemLow.rotation.x = Math.PI * 0.35;
        armGrp.add(stemLow);

        var stemDroop = new THREE.Mesh(new THREE.CylinderGeometry(0.014, 0.014, 0.16, 8), this.mats.bronzeLeather);
        stemDroop.position.set(0, -0.30, 0.30);
        stemDroop.rotation.x = Math.PI * 0.72;
        armGrp.add(stemDroop);

        var wiltedHead = new THREE.Mesh(new THREE.ConeGeometry(0.075, 0.14, 7), this.mats.goldBrushed);
        wiltedHead.position.set(0, -0.39, 0.35);
        wiltedHead.rotation.x = Math.PI * 0.88;
        armGrp.add(wiltedHead);
      }

      // Lv 2: Glowing Blue Plasma Tool in Right Hand (ax === 1)
      if (lvl === 2 && ax === 1) {
        var toolGrip = new THREE.Mesh(new THREE.CylinderGeometry(0.032, 0.032, 0.26, 10), this.mats.gunmetal);
        toolGrip.position.set(0, -0.42, 0.12);
        toolGrip.rotation.x = Math.PI * 0.5;
        armGrp.add(toolGrip);

        var plasmaCore = new THREE.Mesh(new THREE.OctahedronGeometry(0.078, 0), this.mats.plasmaBlue);
        plasmaCore.position.set(0, -0.42, 0.29);
        armGrp.add(plasmaCore);
      }

      // Lv 10: Luminous Violet Amethyst Crystal Arm-Blades on BOTH Forearms!
      if (lvl === 10) {
        var armBlade = new THREE.Mesh(new THREE.ConeGeometry(0.065, 0.52, 4), this.mats.amethystGlow);
        armBlade.position.set(ax * 0.08, -0.26, 0.10);
        armBlade.rotation.x = Math.PI * 0.78;
        armGrp.add(armBlade);
      }
    }

    // 5. BACK WEAPONS (Lv 3..10: Broadsword, Greatsword+Shield, Paladin Greatsword, Dark Greatsword, Spears & Crossed X-Lances!)
    var addDiagonalSword = function (bladeMat, hiltMat, bladeLen, bladeW, zOffset, tiltAngle) {
      var swGrp = new THREE.Group();
      swGrp.position.set(0, 0.08, zOffset);
      swGrp.rotation.z = tiltAngle;
      upperGroup.add(swGrp);

      var blade = new THREE.Mesh(new THREE.BoxGeometry(bladeW, bladeLen, 0.035), bladeMat);
      blade.position.y = 0.05;
      swGrp.add(blade);

      var tip = new THREE.Mesh(new THREE.ConeGeometry(bladeW * 0.55, 0.18, 4), bladeMat);
      tip.rotation.y = Math.PI * 0.25;
      tip.position.y = 0.05 + bladeLen * 0.5 + 0.08;
      swGrp.add(tip);

      var guard = new THREE.Mesh(new THREE.BoxGeometry(bladeW * 2.5, 0.055, 0.06), hiltMat);
      guard.position.y = 0.05 + bladeLen * 0.5 - 0.12;
      // Put crossguard at top-right shoulder end so hilt rises proudly over shoulder!
      guard.position.y = 0.36;
      blade.position.y = -0.18;
      tip.position.y = -0.18 - bladeLen * 0.5 - 0.08;
      tip.rotation.z = Math.PI;

      var grip = new THREE.Mesh(new THREE.CylinderGeometry(0.028, 0.028, 0.24, 8), hiltMat);
      grip.position.y = 0.50;
      swGrp.add(grip);

      var pommel = new THREE.Mesh(new THREE.OctahedronGeometry(0.055, 0), hiltMat);
      pommel.position.y = 0.64;
      swGrp.add(pommel);
    };

    var addBackSpear = function (shaftMat, tipMat, totalLen, tiltZ) {
      var spGrp = new THREE.Group();
      spGrp.position.set(0, 0.12, -0.27);
      spGrp.rotation.z = tiltZ;
      upperGroup.add(spGrp);

      var shaft = new THREE.Mesh(new THREE.CylinderGeometry(0.024, 0.022, totalLen, 10), shaftMat);
      spGrp.add(shaft);

      var spearHead = new THREE.Mesh(new THREE.ConeGeometry(0.085, 0.36, 6), tipMat);
      spearHead.position.y = totalLen * 0.5 + 0.16;
      spGrp.add(spearHead);
    };

    if (lvl === 3) {
      addDiagonalSword(this.mats.silverSteel, this.mats.goldBright, 0.92, 0.11, -0.26, -0.58);
    } else if (lvl === 4) {
      addDiagonalSword(this.mats.silverSteel, this.mats.goldBright, 1.12, 0.13, -0.30, -0.55);
      var shield = new THREE.Mesh(new THREE.CylinderGeometry(0.32, 0.26, 0.06, 6), this.mats.silverSteel);
      shield.rotation.x = Math.PI * 0.5;
      shield.position.set(0, 0.04, -0.25);
      upperGroup.add(shield);
      var shieldRim = new THREE.Mesh(new THREE.TorusGeometry(0.29, 0.028, 8, 6), this.mats.goldBright);
      shieldRim.position.set(0, 0.04, -0.28);
      upperGroup.add(shieldRim);
    } else if (lvl === 5) {
      addDiagonalSword(this.mats.platinum, this.mats.goldBright, 1.24, 0.15, -0.28, -0.54);
    } else if (lvl === 6) {
      addDiagonalSword(this.mats.gunmetal, this.mats.silverSteel, 1.26, 0.16, -0.28, -0.54);
    } else if (lvl === 7) {
      addBackSpear(this.mats.goldAntique, this.mats.silverSteel, 1.72, -0.52);
    } else if (lvl === 8) {
      addBackSpear(this.mats.goldBright, this.mats.beaconGold, 1.84, -0.50);
    } else if (lvl === 9) {
      // 2 Crossed Sapphire Ice-Crystal Spears (X-Shape)!
      addBackSpear(this.mats.platinum, this.mats.sapphireCrystal, 1.88, -0.54);
      addBackSpear(this.mats.platinum, this.mats.sapphireCrystal, 1.88, 0.54);
    } else if (lvl === 10) {
      // 2 Crossed Amethyst Crystal Lances (X-Shape)!
      addBackSpear(this.mats.goldBright, this.mats.amethystGlow, 1.98, -0.54);
      addBackSpear(this.mats.goldBright, this.mats.amethystGlow, 1.98, 0.54);
    }

    // 6. ICONIC FUNKI TV-SCREEN MONITOR HEAD + SIDE GEARS + HELMETS/HORNS/CROWNS!
    var headGroup = new THREE.Group();
    headGroup.position.set(0, 0.68, 0.02);
    if (isSitting) {
      headGroup.rotation.x = 0.12;
    }
    upperGroup.add(headGroup);

    var tvCasing = new THREE.Mesh(new THREE.BoxGeometry(0.76, 0.58, 0.48), armorMat);
    headGroup.add(tvCasing);

    var tvBezel = new THREE.Mesh(new THREE.BoxGeometry(0.68, 0.48, 0.04), trimMat);
    tvBezel.position.set(0, 0, 0.235);
    headGroup.add(tvBezel);

    var faceTex = makeFunkiScreenFaceTexture(THREE, this.maxAniso, lvl);
    this.dynamicTextures.push(faceTex);
    var tvScreen = new THREE.Mesh(
      new THREE.PlaneGeometry(0.60, 0.40),
      new THREE.MeshBasicMaterial({ map: faceTex })
    );
    tvScreen.position.set(0, 0, 0.26);
    headGroup.add(tvScreen);

    // Side Mechanical Gears (Zahnräder) on Both Monitor Temples!
    var gearWheelL = null, gearWheelR = null;
    for (var gx = -1; gx <= 1; gx += 2) {
      var gearGrp = new THREE.Group();
      gearGrp.position.set(gx * 0.41, 0.0, 0.0);
      headGroup.add(gearGrp);

      var gearDisc = new THREE.Mesh(new THREE.CylinderGeometry(0.13, 0.13, 0.06, 12), trimMat);
      gearDisc.rotation.z = Math.PI * 0.5;
      gearGrp.add(gearDisc);

      for (var gt = 0; gt < 6; gt++) {
        var gang = (gt / 6) * Math.PI * 2;
        var tooth = new THREE.Mesh(new THREE.BoxGeometry(0.05, 0.045, 0.31), this.mats.goldBright);
        tooth.rotation.x = gang;
        gearGrp.add(tooth);
      }
      if (gx < 0) gearWheelL = gearGrp; else gearWheelR = gearGrp;
    }

    // Level-Specific Helmets, Visors, Horns & God Crown!
    if (lvl === 4 || lvl === 5) {
      var visorCrest = new THREE.Mesh(new THREE.BoxGeometry(0.80, 0.14, 0.52), trimMat);
      visorCrest.position.set(0, 0.30, 0.02);
      headGroup.add(visorCrest);

      var browGem = new THREE.Mesh(
        new THREE.OctahedronGeometry(0.068, 0),
        lvl === 4 ? this.mats.gemYellow : this.mats.plasmaBlue
      );
      browGem.position.set(0, 0.30, 0.29);
      headGroup.add(browGem);

      if (lvl === 5) {
        for (var c5 = -2; c5 <= 2; c5++) {
          var jag = new THREE.Mesh(new THREE.ConeGeometry(0.05, 0.16, 4), this.mats.goldBright);
          jag.position.set(c5 * 0.14, 0.43, 0.16);
          headGroup.add(jag);
        }
      }
    }

    if (lvl >= 6) {
      var hornMat = this.mats.goldBright;
      var hornH = 0.36;
      if (lvl === 6) { hornMat = this.mats.silverSteel; hornH = 0.34; }
      else if (lvl === 7) { hornMat = this.mats.goldAntique; hornH = 0.40; }
      else if (lvl === 8) { hornMat = this.mats.goldBright; hornH = 0.48; }
      else if (lvl === 9) { hornMat = this.mats.sapphireCrystal; hornH = 0.48; }
      else if (lvl === 10) { hornMat = this.mats.goldBright; hornH = 0.50; }

      for (var hxSide = -1; hxSide <= 1; hxSide += 2) {
        var hornBase = new THREE.Mesh(new THREE.ConeGeometry(0.085, hornH, 8), hornMat);
        hornBase.position.set(hxSide * 0.34, 0.38, 0.0);
        hornBase.rotation.z = hxSide * -0.48;
        headGroup.add(hornBase);

        var hornTip = new THREE.Mesh(new THREE.ConeGeometry(0.055, hornH * 0.65, 8), lvl === 10 ? this.mats.amethystGlow : hornMat);
        hornTip.position.set(hxSide * 0.46, 0.56, 0.02);
        hornTip.rotation.z = hxSide * -0.12;
        headGroup.add(hornTip);
      }

      if (lvl === 8) {
        var dragCrest = new THREE.Mesh(new THREE.ConeGeometry(0.075, 0.32, 5), this.mats.beaconGold);
        dragCrest.position.set(0, 0.44, 0.08);
        headGroup.add(dragCrest);
      }

      if (lvl === 9) {
        var iceCrown = new THREE.Mesh(new THREE.OctahedronGeometry(0.12, 0), this.mats.sapphireCrystal);
        iceCrown.scale.set(0.8, 1.8, 0.8);
        iceCrown.position.set(0, 0.48, 0.08);
        headGroup.add(iceCrown);
      }

      if (lvl === 10) {
        // Central Luminous Violet Amethyst Crown Spire + Royal Halo!
        var godCrownBand = new THREE.Mesh(new THREE.BoxGeometry(0.78, 0.11, 0.50), this.mats.goldBright);
        godCrownBand.position.set(0, 0.31, 0.0);
        headGroup.add(godCrownBand);

        var amethystSpire = new THREE.Mesh(new THREE.ConeGeometry(0.11, 0.46, 6), this.mats.amethystGlow);
        amethystSpire.position.set(0, 0.56, 0.08);
        headGroup.add(amethystSpire);
      }
    }

    return root;
  };

  // ============================================================================
  // PANTHEON "HALLE DER HELDEN" WITH UNOBSTRUCTED FRIEZE & CARRARA-ALABASTER FLOOR
  // ============================================================================
  FunkenwerkHeroes3DEngine.prototype._buildPantheonArchitecture = function () {
    var THREE = this.THREE;
    var self = this;
    this.pantheonGroup = new THREE.Group();
    this.pantheonGroup.position.set(0, PANTHEON_Y, PANTHEON_Z);
    this.cityGroup.add(this.pantheonGroup);

    // 1. Stepped 3-Tier Pantheon Stylobate (Clean >= 0.40m Vertical Steps -> Zero Coplanar Overlap!)
    var steps = [
      { w: 56.0, d: 58.0, h: 3.10, y: -1.95, trimY: -0.98, mat: this.mats.obsidian, trimMat: this.mats.goldBright },
      { w: 52.0, d: 54.0, h: 0.90, y: 0.08,  trimY: -0.08, mat: this.mats.sapphireTrim, trimMat: this.mats.goldBright },
      { w: 48.0, d: 50.0, h: 0.80, y: 0.86,  trimY: 0.70,  mat: this.mats.alabasterStep, trimMat: this.mats.sapphireTrim }
    ];
    for (var si = 0; si < steps.length; si++) {
      var s = steps[si];
      var sm = new THREE.Mesh(new THREE.BoxGeometry(s.w, s.h, s.d), s.mat);
      sm.position.set(0, s.y, 0);
      this.pantheonGroup.add(sm);

      var trim = new THREE.Mesh(new THREE.BoxGeometry(s.w + 1.1, 0.34, s.d + 1.1), s.trimMat);
      trim.position.set(0, s.trimY, 0);
      this.pantheonGroup.add(trim);
    }

    var floorTopY = 1.66;

    // Stepped Plinth Riser (1.26 .. 1.66, +0.40m step above step[2] top 1.26!) + Carrara-Alabaster Floor
    var floorRiser = new THREE.Mesh(new THREE.BoxGeometry(46.8, 0.38, 48.8), this.mats.sapphireTrim);
    floorRiser.position.set(0, 1.45, 0);
    this.pantheonGroup.add(floorRiser);

    // 2. High-Contrast Carrara-Alabaster & Deep Atlantis-Sapphire Marble Floor (#ECEAE4 / #E2DFD7, #94A3B8, #133B5C)
    var marbleTex = makeHallOfHeroesMarbleTexture(THREE, this.maxAniso);
    this.staticDisposables.push(marbleTex);
    var marbleFloor = new THREE.Mesh(
      new THREE.PlaneGeometry(46.8, 48.8),
      new THREE.MeshBasicMaterial({
        map: marbleTex,
        polygonOffset: true,
        polygonOffsetFactor: -1,
        polygonOffsetUnits: -2
      })
    );
    marbleFloor.rotation.x = -Math.PI * 0.5;
    marbleFloor.position.set(0, floorTopY + 0.02, 0);
    this.pantheonGroup.add(marbleFloor);

    var hallAoMatrices = [];
    function pushHallAo(x, y, z, sx, sz) {
      self.dummyObj.position.set(x, y, z);
      self.dummyObj.rotation.set(0, 0, 0);
      self.dummyObj.scale.set(sx, 1, sz);
      self.dummyObj.updateMatrix();
      hallAoMatrices.push(self.dummyObj.matrix.clone());
    }
    pushHallAo(0, -4.84, 0, 68.0, 70.0);

    // 3. 28 Monumental 24K-Gold Colonnade Columns via InstancedMesh (leaving wide 18.4m central sightline!)
    var colCoords = [];
    for (var ci = 0; ci < 8; ci++) {
      var cz = lerp(-21.5, 21.5, ci / 7);
      colCoords.push([-21.5, cz], [21.5, cz]);
    }
    var porticoXs = [-15.2, -9.2, 9.2, 15.2];
    for (var pxi = 0; pxi < porticoXs.length; pxi++) {
      colCoords.push([porticoXs[pxi], 21.5], [porticoXs[pxi], -21.5]);
    }
    colCoords.push([-14.8, -14.8], [14.8, -14.8], [-14.8, 14.8], [14.8, 14.8]);

    var colHeight = 12.6;
    var shaftInst = new THREE.InstancedMesh(
      new THREE.CylinderGeometry(0.68, 0.80, colHeight, 14),
      this.mats.goldBrushed,
      colCoords.length
    );
    var capInst = new THREE.InstancedMesh(
      new THREE.BoxGeometry(2.05, 0.82, 2.05),
      this.mats.goldBright,
      colCoords.length
    );

    for (var cIdx = 0; cIdx < colCoords.length; cIdx++) {
      var coord = colCoords[cIdx];
      pushHallAo(coord[0], floorTopY + 0.05, coord[1], 3.1, 3.1);

      this.dummyObj.position.set(coord[0], floorTopY + colHeight * 0.5, coord[1]);
      this.dummyObj.rotation.set(0, 0, 0);
      this.dummyObj.scale.set(1, 1, 1);
      this.dummyObj.updateMatrix();
      shaftInst.setMatrixAt(cIdx, this.dummyObj.matrix);

      this.dummyObj.position.set(coord[0], floorTopY + colHeight + 0.35, coord[1]);
      this.dummyObj.updateMatrix();
      capInst.setMatrixAt(cIdx, this.dummyObj.matrix);
    }
    shaftInst.instanceMatrix.needsUpdate = true;
    capInst.instanceMatrix.needsUpdate = true;
    this.pantheonGroup.add(shaftInst);
    this.pantheonGroup.add(capInst);

    var hallAoGeo = new THREE.PlaneGeometry(1, 1);
    hallAoGeo.rotateX(-Math.PI * 0.5);
    var hallAoInst = new THREE.InstancedMesh(hallAoGeo, this.mats.bakedAo, hallAoMatrices.length);
    for (var hai = 0; hai < hallAoMatrices.length; hai++) {
      hallAoInst.setMatrixAt(hai, hallAoMatrices[hai]);
    }
    hallAoInst.instanceMatrix.needsUpdate = true;
    hallAoInst.frustumCulled = false;
    hallAoInst.renderOrder = 1;
    this.pantheonGroup.add(hallAoInst);

    // 4. Entablature Perimeter Beams + 100% Unobstructed "HALLE DER HELDEN" Friezes
    var entY = floorTopY + colHeight + 1.45;
    var sideBeamGeo = new THREE.BoxGeometry(2.4, 1.9, 45.0);
    var sideXs = [-21.5, 21.5];
    for (var sbi = 0; sbi < sideXs.length; sbi++) {
      var b = new THREE.Mesh(sideBeamGeo, this.mats.obsidian);
      b.position.set(sideXs[sbi], entY, 0);
      this.pantheonGroup.add(b);

      var g = new THREE.Mesh(new THREE.BoxGeometry(2.65, 0.35, 45.4), this.mats.goldBright);
      g.position.set(sideXs[sbi], entY + 1.15, 0);
      this.pantheonGroup.add(g);
    }

    var crossBeamGeo = new THREE.BoxGeometry(45.6, 2.1, 2.5);
    var crossZs = [-21.5, 21.5];
    for (var cbi = 0; cbi < crossZs.length; cbi++) {
      var cb = new THREE.Mesh(crossBeamGeo, this.mats.obsidian);
      cb.position.set(0, entY, crossZs[cbi]);
      this.pantheonGroup.add(cb);

      var cg = new THREE.Mesh(new THREE.BoxGeometry(46.2, 0.38, 2.8), this.mats.goldBright);
      cg.position.set(0, entY + 1.25, crossZs[cbi]);
      this.pantheonGroup.add(cg);
    }

    var friezeTex = makeBannerPlaqueTexture(THREE, this.maxAniso, 'HALLE DER HELDEN');
    this.staticDisposables.push(friezeTex);
    var friezeMat = new THREE.MeshBasicMaterial({
      map: friezeTex,
      polygonOffset: true,
      polygonOffsetFactor: -2,
      polygonOffsetUnits: -4
    });

    // Seaward Front Portico Frieze (+Z)
    var outerFriezeBack = new THREE.Mesh(new THREE.BoxGeometry(24.8, 3.85, 0.9), this.mats.goldBright);
    outerFriezeBack.position.set(0, entY + 2.15, 21.65);
    this.pantheonGroup.add(outerFriezeBack);

    var outerFrieze = new THREE.Mesh(new THREE.PlaneGeometry(24.0, 3.45), friezeMat);
    outerFrieze.position.set(0, entY + 2.15, 22.15);
    this.pantheonGroup.add(outerFrieze);

    // Inner Triumphal Arch Frieze (-Z, with MEIN SEELENFUNKE Supertower rising directly behind it!)
    var innerFriezeBack = new THREE.Mesh(new THREE.BoxGeometry(22.6, 3.5, 0.8), this.mats.goldBright);
    innerFriezeBack.position.set(0, entY + 1.85, -21.5);
    this.pantheonGroup.add(innerFriezeBack);

    var innerFrieze = new THREE.Mesh(new THREE.PlaneGeometry(22.0, 3.15), friezeMat);
    innerFrieze.position.set(0, entY + 1.85, -21.04);
    this.pantheonGroup.add(innerFrieze);

    // 5. Cupola Base Ring & Ribs Mounted CLEANLY ABOVE the Frieze (domeBaseY = entY + 4.35)
    //    + Half-Segment Offset ((r + 0.5) / 6) * Math.PI -> ZERO bar or ring in front of HALLE DER HELDEN!
    var domeBaseY = entY + 4.35;
    var domeRadius = 17.5;
    var oculusRadius = 5.2;
    var ribArc = Math.acos(oculusRadius / domeRadius);

    var drumCorners = [[-14.8, -14.8], [14.8, -14.8], [-14.8, 14.8], [14.8, 14.8]];
    for (var dci = 0; dci < drumCorners.length; dci++) {
      var dp = drumCorners[dci];
      var drumPost = new THREE.Mesh(new THREE.CylinderGeometry(0.62, 0.72, 3.6, 12), this.mats.goldBright);
      drumPost.position.set(dp[0], entY + 2.45, dp[1]);
      this.pantheonGroup.add(drumPost);
    }

    var domeBaseRing = new THREE.Mesh(new THREE.TorusGeometry(domeRadius, 0.42, 10, 56), this.mats.goldBright);
    domeBaseRing.rotation.x = Math.PI * 0.5;
    domeBaseRing.position.set(0, domeBaseY, 0);
    this.pantheonGroup.add(domeBaseRing);

    for (var r = 0; r < 12; r++) {
      var rib = new THREE.Mesh(new THREE.TorusGeometry(domeRadius, 0.24, 8, 32, ribArc), this.mats.goldBright);
      rib.position.set(0, domeBaseY, 0);
      rib.rotation.y = ((r + 0.5) / 6) * Math.PI;
      this.pantheonGroup.add(rib);
    }

    var oculusY = domeBaseY + Math.sqrt(domeRadius * domeRadius - oculusRadius * oculusRadius);
    var oculusRing = new THREE.Mesh(new THREE.TorusGeometry(oculusRadius, 0.34, 10, 40), this.mats.goldBright);
    oculusRing.rotation.x = Math.PI * 0.5;
    oculusRing.position.set(0, oculusY, 0);
    this.pantheonGroup.add(oculusRing);

    var domeStar = new THREE.Mesh(new THREE.OctahedronGeometry(1.45, 0), this.mats.beaconGold);
    domeStar.position.set(0, oculusY + 2.4, 0);
    this.pantheonGroup.add(domeStar);

    var domeClickProxy = new THREE.Mesh(
      new THREE.BoxGeometry(52, 34, 54),
      new THREE.MeshBasicMaterial({ visible: false })
    );
    domeClickProxy.position.set(0, 16, 0);
    domeClickProxy.userData = { navAction: 'hall' };
    this.pantheonGroup.add(domeClickProxy);
    this.pantheonDomeProxy = domeClickProxy;

    this.pantheonDynamicGroup = new THREE.Group();
    this.pantheonGroup.add(this.pantheonDynamicGroup);
  };

  FunkenwerkHeroes3DEngine.prototype._populateHeroesMonuments = function () {
    if (!this.pantheonDynamicGroup) return;
    var THREE = this.THREE;
    var self = this;

    while (this.pantheonDynamicGroup.children.length > 0) {
      this.pantheonDynamicGroup.remove(this.pantheonDynamicGroup.children[0]);
    }
    for (var t = 0; t < this.dynamicTextures.length; t++) {
      this.dynamicTextures[t].dispose();
    }
    this.dynamicTextures = [];
    this.animatedFunkiFigures = [];
    this.pedestalSparkMeta = [];
    this.pedestalSparkPosAttr = null;
    this._monumentGeneration++;
    this.slotAnchors = {};
    this.interactiveTargets = this.pantheonDomeProxy ? [this.pantheonDomeProxy] : [];

    var heroesList = (this.heroesPayload && Array.isArray(this.heroesPayload.heroes)) ? this.heroesPayload.heroes : [];
    var heroBySlot = {};
    for (var hi = 0; hi < heroesList.length; hi++) {
      var hObj = heroesList[hi];
      heroBySlot[hObj.slot || (hi + 1)] = hObj;
    }

    var floorTopY = 1.66;

    // ========================================================================
    // A. COURT OF HONOR: TOP 5 PEDESTALS WITH LEVEL 1..10 3D FUNKI CHARACTERS
    // ========================================================================
    var pedestalSpecs = [
      { slot: 1, x: 0.0, z: -7.8, h: 2.35, w: 3.10, scale: 1.24, rotY: 0.0 },
      { slot: 2, x: -4.9, z: -4.6, h: 1.85, w: 2.65, scale: 1.08, rotY: 0.22 },
      { slot: 3, x: 4.9, z: -4.6, h: 1.85, w: 2.65, scale: 1.08, rotY: -0.22 },
      { slot: 4, x: -9.2, z: -0.8, h: 1.50, w: 2.45, scale: 0.98, rotY: 0.36 },
      { slot: 5, x: 9.2, z: -0.8, h: 1.50, w: 2.45, scale: 0.98, rotY: -0.36 }
    ];

    for (var pi = 0; pi < pedestalSpecs.length; pi++) {
      var sp = pedestalSpecs[pi];
      var hero = heroBySlot[sp.slot] || null;
      var isPlaceholder = !hero || !!hero.is_placeholder;
      var displayLevel = !isPlaceholder ? clamp(Number(hero.level) || 1, 1, 10) : 1;

      var pGrp = new THREE.Group();
      pGrp.position.set(sp.x, floorTopY, sp.z);
      pGrp.rotation.y = sp.rotY;
      this.pantheonDynamicGroup.add(pGrp);

      var pedCore = new THREE.Mesh(
        new THREE.CylinderGeometry(sp.w * 0.52, sp.w * 0.60, sp.h, 8),
        this.mats.obsidian
      );
      pedCore.position.y = sp.h * 0.5;
      pGrp.add(pedCore);

      var pedCap = new THREE.Mesh(
        new THREE.CylinderGeometry(sp.w * 0.57, sp.w * 0.55, 0.18, 8),
        this.mats.goldBright
      );
      pedCap.position.y = sp.h + 0.06;
      pGrp.add(pedCap);

      var pedBase = new THREE.Mesh(
        new THREE.CylinderGeometry(sp.w * 0.63, sp.w * 0.66, 0.18, 8),
        this.mats.goldBright
      );
      pedBase.position.y = 0.09;
      pGrp.add(pedBase);

      if (!isPlaceholder && hero.is_me) {
        var myRing = new THREE.Mesh(new THREE.TorusGeometry(sp.w * 0.72, 0.09, 10, 36), this.mats.mySlotGlow);
        myRing.rotation.x = Math.PI * 0.5;
        myRing.position.y = 0.14;
        pGrp.add(myRing);
      }

      // Front Engraved 24K-Gold Name Plaque (Real Hero vs honest "Freier Ehrenplatz"!)
      var plaqueCanvas = document.createElement('canvas');
      plaqueCanvas.width = 1024;
      plaqueCanvas.height = 512;
      paintHeroPedestalCanvas(plaqueCanvas, sp.slot, hero);
      var plaqueTex = configureTex(THREE, new THREE.CanvasTexture(plaqueCanvas), this.maxAniso, 4);
      this.dynamicTextures.push(plaqueTex);

      var pW = sp.w * 0.84;
      var pH = pW * 0.50;
      var plaqueFrame = new THREE.Mesh(new THREE.BoxGeometry(pW + 0.12, pH + 0.12, 0.08), this.mats.goldBright);
      plaqueFrame.position.set(0, sp.h * 0.54, sp.w * 0.56);
      plaqueFrame.rotation.x = -0.08;
      pGrp.add(plaqueFrame);

      var plaqueMesh = new THREE.Mesh(
        new THREE.PlaneGeometry(pW, pH),
        new THREE.MeshBasicMaterial({
          map: plaqueTex,
          polygonOffset: true,
          polygonOffsetFactor: -2,
          polygonOffsetUnits: -4
        })
      );
      plaqueMesh.position.set(0, sp.h * 0.54, sp.w * 0.56 + 0.048);
      plaqueMesh.rotation.x = -0.08;
      pGrp.add(plaqueMesh);

      var pedAoMesh = new THREE.Mesh(
        new THREE.PlaneGeometry(sp.w * 1.75, sp.w * 1.75),
        this.mats.bakedAo
      );
      pedAoMesh.rotation.x = -Math.PI * 0.5;
      pedAoMesh.position.y = 0.04;
      pedAoMesh.renderOrder = 1;
      pGrp.add(pedAoMesh);

      // Authentic Compressed Level 1..10 Tripo 3D Funki (.glb) Character flush on pedestal top (sp.h + 0.15)!
      var funkiChar = this._createFunkiCharacterByLevel(displayLevel, sp.slot, sp.scale, hero);
      funkiChar.position.set(0, sp.h + 0.15, 0);
      pGrp.add(funkiChar);

      var proxy = new THREE.Mesh(
        new THREE.CylinderGeometry(sp.w * 0.7, sp.w * 0.7, sp.h + 3.4, 12),
        new THREE.MeshBasicMaterial({ visible: false })
      );
      proxy.position.y = (sp.h + 3.4) * 0.5;
      proxy.userData = { slot: sp.slot, hero: isPlaceholder ? null : hero };
      pGrp.add(proxy);
      this.interactiveTargets.push(proxy);

      var worldTarget = new THREE.Vector3(sp.x, PANTHEON_Y + floorTopY + sp.h + 1.15, PANTHEON_Z + sp.z);
      var camForward = new THREE.Vector3(Math.sin(sp.rotY), 0, Math.cos(sp.rotY));
      var worldCam = worldTarget.clone().addScaledVector(camForward, 6.8);
      worldCam.y = worldTarget.y + 0.45;
      this.slotAnchors[sp.slot] = { camPos: worldCam, target: worldTarget, hero: isPlaceholder ? null : hero, slot: sp.slot };
    }

    // Rising Golden Seelenfunken Spark Particles around all 5 Honor Pedestals (1 batched Points draw call!)
    var sparksPerPedestal = 26;
    var totalPedSparks = pedestalSpecs.length * sparksPerPedestal;
    var sparkPositions = new Float32Array(totalPedSparks * 3);
    var sparkColors = new Float32Array(totalPedSparks * 3);
    var sparkRng = mulberry32(20261117);

    for (var spi = 0; spi < pedestalSpecs.length; spi++) {
      var pSpec = pedestalSpecs[spi];
      var pHero = heroBySlot[pSpec.slot] || null;
      var pLvl = (pHero && !pHero.is_placeholder) ? clamp(Number(pHero.level) || 1, 1, 10) : 1;
      var accentRgb = hexToRgb(this._getFunkiAuraHex(pLvl));

      for (var sj = 0; sj < sparksPerPedestal; sj++) {
        var sIdx = spi * sparksPerPedestal + sj;
        var phaseVal = sparkRng();
        var baseR = pSpec.w * (0.34 + sparkRng() * 0.26);
        var spanH = (2.35 + sparkRng() * 0.95) * pSpec.scale;
        var spd = 0.14 + sparkRng() * 0.16;
        var sw = (0.65 + sparkRng() * 0.75) * (sj % 2 === 0 ? 1 : -1);
        var startY = floorTopY + pSpec.h + 0.16;

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

        var useAccent = (sj % 3 === 0);
        sparkColors[sIdx * 3] = useAccent ? (accentRgb[0] / 255) : 1.0;
        sparkColors[sIdx * 3 + 1] = useAccent ? (accentRgb[1] / 255) : 0.89;
        sparkColors[sIdx * 3 + 2] = useAccent ? (accentRgb[2] / 255) : 0.52;
      }
    }

    var pedSparkGeo = new THREE.BufferGeometry();
    this.pedestalSparkPosAttr = new THREE.BufferAttribute(sparkPositions, 3);
    pedSparkGeo.setAttribute('position', this.pedestalSparkPosAttr);
    pedSparkGeo.setAttribute('color', new THREE.BufferAttribute(sparkColors, 3));
    var pedSparkMat = new THREE.PointsMaterial({
      size: 0.22,
      map: this.sparkSpriteTex || null,
      vertexColors: true,
      transparent: true,
      opacity: 0.88,
      blending: THREE.AdditiveBlending,
      depthWrite: false
    });
    this.dynamicTextures.push({ dispose: function () { pedSparkGeo.dispose(); pedSparkMat.dispose(); } });
    var pedSparkPoints = new THREE.Points(pedSparkGeo, pedSparkMat);
    pedSparkPoints.frustumCulled = false;
    this.pantheonDynamicGroup.add(pedSparkPoints);

    // ========================================================================
    // B. COLONNADE HONOR STELES VIA INSTANCEDMESH (SLOTS #6..#29+, 60 FPS!)
    // ========================================================================
    var totalSlots = Math.max(TOTAL_DISPLAY_SLOTS, Math.min(99, heroesList.length + 6));
    var steleCount = Math.max(0, totalSlots - 5);

    if (steleCount > 0) {
      var steleObsInst = new THREE.InstancedMesh(
        new THREE.BoxGeometry(1.35, 3.10, 0.34),
        this.mats.obsidian,
        steleCount
      );
      var steleGoldInst = new THREE.InstancedMesh(
        new THREE.BoxGeometry(1.48, 3.24, 0.26),
        this.mats.goldBright,
        steleCount
      );
      steleObsInst.frustumCulled = false;
      steleGoldInst.frustumCulled = false;
      this.pantheonDynamicGroup.add(steleGoldInst);
      this.pantheonDynamicGroup.add(steleObsInst);

      for (var i = 0; i < steleCount; i++) {
        var slot = 6 + i;
        var sHero = heroBySlot[slot] || null;
        var sIsPlaceholder = !sHero || !!sHero.is_placeholder;

        var isRight = (i % 2 === 1);
        var row = Math.floor(i / 2);
        var tierOffset = Math.floor(row / 12) * 3.0;
        var rowInColonnade = row % 12;
        var sx = (isRight ? 16.4 : -16.4) + (isRight ? -tierOffset : tierOffset);
        var sz = lerp(-15.5, 16.5, rowInColonnade / 11);
        var rotY = isRight ? -Math.PI * 0.42 : Math.PI * 0.42;

        self.dummyObj.position.set(sx, floorTopY + 1.62, sz);
        self.dummyObj.rotation.set(0, rotY, 0);
        self.dummyObj.scale.set(1, 1, 1);
        self.dummyObj.updateMatrix();
        steleObsInst.setMatrixAt(i, self.dummyObj.matrix);
        steleGoldInst.setMatrixAt(i, self.dummyObj.matrix);

        var stCanvas = document.createElement('canvas');
        stCanvas.width = 256;
        stCanvas.height = 512;
        paintHeroSteleCanvas(stCanvas, slot, sHero);
        var stTex = configureTex(THREE, new THREE.CanvasTexture(stCanvas), this.maxAniso, 2);
        this.dynamicTextures.push(stTex);

        var facePlane = new THREE.Mesh(
          new THREE.PlaneGeometry(1.18, 2.36),
          new THREE.MeshBasicMaterial({
            map: stTex,
            polygonOffset: true,
            polygonOffsetFactor: -2,
            polygonOffsetUnits: -4
          })
        );
        facePlane.position.set(
          sx + Math.sin(rotY) * 0.185,
          floorTopY + 1.68,
          sz + Math.cos(rotY) * 0.185
        );
        facePlane.rotation.y = rotY;
        this.pantheonDynamicGroup.add(facePlane);

        if (!sIsPlaceholder && sHero.is_me) {
          var mySteleRing = new THREE.Mesh(new THREE.TorusGeometry(1.15, 0.075, 8, 28), this.mats.mySlotGlow);
          mySteleRing.rotation.x = Math.PI * 0.5;
          mySteleRing.position.set(sx, floorTopY + 0.08, sz);
          this.pantheonDynamicGroup.add(mySteleRing);
        }

        var stProxy = new THREE.Mesh(
          new THREE.BoxGeometry(1.7, 3.4, 0.8),
          new THREE.MeshBasicMaterial({ visible: false })
        );
        stProxy.position.set(sx, floorTopY + 1.65, sz);
        stProxy.rotation.y = rotY;
        stProxy.userData = { slot: slot, hero: sIsPlaceholder ? null : sHero };
        this.pantheonDynamicGroup.add(stProxy);
        this.interactiveTargets.push(stProxy);

        var stWorldTarget = new THREE.Vector3(sx, PANTHEON_Y + floorTopY + 1.75, PANTHEON_Z + sz);
        var stNormal = new THREE.Vector3(Math.sin(rotY), 0, Math.cos(rotY));
        var stWorldCam = stWorldTarget.clone().addScaledVector(stNormal, 4.6);
        stWorldCam.y = stWorldTarget.y + 0.25;
        this.slotAnchors[slot] = { camPos: stWorldCam, target: stWorldTarget, hero: sIsPlaceholder ? null : sHero, slot: slot };
      }

      steleObsInst.instanceMatrix.needsUpdate = true;
      steleGoldInst.instanceMatrix.needsUpdate = true;
    }
  };

  FunkenwerkHeroes3DEngine.prototype._syncPlanetVsCityBlend = function (camPos) {
    var distFromOrigin = camPos.length();
    var planetVis = clamp((distFromOrigin - 820) / 760, 0, 1);
    var cityVis = clamp(1.0 - (distFromOrigin - 680) / 860, 0, 1);

    if (this.planetGroup) {
      this.planetGroup.visible = planetVis > 0.005;
      this.planetSurfaceUniforms.uAlpha.value = planetVis;
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
      this.starMat.opacity = lerp(0.18, 0.96, planetVis);
    }
    if (this.cloudVeilEl) {
      var cloudBand = Math.exp(-Math.pow((distFromOrigin - 960) / 210, 2));
      var op = (this.flight && this.flight.active) ? (cloudBand * 0.32) : 0;
      this.cloudVeilEl.style.opacity = op > 0.005 ? op.toFixed(3) : '0';
    }

    // Dynamic 3-Stage Camera Clipping Planes (Zero Z-Fighting at 600m..1400m & 0 CPU Overhead!)
    if (this.camera && this.orbit) {
      var distTarget = camPos.distanceTo(this.orbit.target);
      var distPantheon = this._pantheonRefVec ? camPos.distanceTo(this._pantheonRefVec) : distTarget;
      var camDist = distFromOrigin > 1400
        ? distFromOrigin
        : Math.min(distFromOrigin, distTarget, distPantheon);

      var newNear;
      var newFar;
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
  };

  FunkenwerkHeroes3DEngine.prototype._adoptCameraIntoOrbit = function (targetVec, modeHint) {
    var THREE = this.THREE;
    if (modeHint) this.viewMode = modeHint;
    this.orbit.target.copy(targetVec);
    this.orbit.desiredTarget.copy(targetVec);

    var offset = new THREE.Vector3().subVectors(this.camera.position, targetVec);
    var r = Math.max(2.4, offset.length());
    var phi = clamp(Math.acos(clamp(offset.y / r, -0.995, 0.995)), 0.08, Math.PI * 0.495);
    var theta = Math.atan2(offset.x, offset.z);

    this.orbit.radius = this.orbit.desiredRadius = r;
    this.orbit.phi = this.orbit.desiredPhi = phi;
    this.orbit.theta = this.orbit.desiredTheta = theta;

    if (this.viewMode === 'hall' || this.viewMode === 'my_place') {
      this.orbit.minRadius = 2.4;
      this.orbit.maxRadius = 180.0;
      this.orbit.minY = PANTHEON_Y + 2.1;
    } else {
      this.orbit.minRadius = 24.0;
      this.orbit.maxRadius = 5800.0;
      this.orbit.minY = 4.5;
    }
    this._updateNavHighlight();
  };

  FunkenwerkHeroes3DEngine.prototype._startSmoothFlight = function (destPos, destTarget, duration, modeAfter, typeName) {
    if (this.camera.position.length() > 1400 && typeName !== 'orbital_dive') {
      this.camera.position.set(120, 145, 420);
      this.orbit.target.set(0, 64, 120);
    }

    this.flight.active = true;
    this.flight.type = typeName || 'glide';
    this.flight.elapsed = 0;
    this.flight.duration = duration;
    this.flight.settledMode = modeAfter;
    this.flight.fromPos.copy(this.camera.position);
    this.flight.fromTarget.copy(this.orbit.target);
    this.flight.midPos.set(
      lerp(this.camera.position.x, destPos.x, 0.5) + 12,
      Math.max(this.camera.position.y, destPos.y) + (typeName === 'orbital_dive' ? 0 : 16),
      lerp(this.camera.position.z, destPos.z, 0.5) + 18
    );
    this.flight.toPos.copy(destPos);
    this.flight.toTarget.copy(destTarget);
  };

  FunkenwerkHeroes3DEngine.prototype.startOrbitalFlyIn = function () {
    if (!this.initialized) return;
    var THREE = this.THREE;
    this.viewMode = 'orbit';
    this._updateNavHighlight();
    this._setStatusText('Anflug aus dem Funken-Universum auf die Wasserstadt Funkenwerk');

    var startPos = new THREE.Vector3(880, 3350, 3650);
    var startTarget = new THREE.Vector3(0, -1250, 0);
    var destPos = new THREE.Vector3(96, 86, 375);
    var destTarget = new THREE.Vector3(0, 64, 95);

    this.camera.position.copy(startPos);
    this.camera.lookAt(startTarget);
    this._syncPlanetVsCityBlend(startPos);

    this.flight.active = true;
    this.flight.type = 'orbital_dive';
    this.flight.elapsed = 0;
    this.flight.duration = 5.2;
    this.flight.settledMode = 'city';
    this.flight.fromPos.copy(startPos);
    this.flight.fromTarget.copy(startTarget);
    this.flight.midPos.set(280, 880, 1120);
    this.flight.toPos.copy(destPos);
    this.flight.toTarget.copy(destTarget);
  };

  FunkenwerkHeroes3DEngine.prototype.flyToCityOverview = function (instant) {
    if (!this.initialized) return;
    var THREE = this.THREE;
    this.viewMode = 'city';
    this._updateNavHighlight();
    var total = this.heroesPayload.total_heroes || 0;
    this._setStatusText('Wasserstadt Funkenwerk · ' + total + (total === 1 ? ' eingetragener Held' : ' eingetragene Helden'));

    var destPos = new THREE.Vector3(145, 138, 465);
    var destTarget = new THREE.Vector3(0, 92, 60);

    if (instant) {
      this.flight.active = false;
      this.camera.position.copy(destPos);
      this.camera.lookAt(destTarget);
      this._syncPlanetVsCityBlend(destPos);
      this._adoptCameraIntoOrbit(destTarget, 'city');
      return;
    }
    this._startSmoothFlight(destPos, destTarget, 2.3, 'city', 'glide');
  };

  FunkenwerkHeroes3DEngine.prototype.flyToHallOfHeroes = function (instant) {
    if (!this.initialized) return;
    var THREE = this.THREE;
    this.viewMode = 'hall';
    this._updateNavHighlight();

    var total = this.heroesPayload.total_heroes || 0;
    var label = total > 0
      ? ('🏛️ Halle der Helden · ' + total + (total === 1 ? ' Held verewigt' : ' Helden verewigt'))
      : '🏛️ Halle der Helden · Gründer-Ära mit freien Ehrenplätzen';
    this._setStatusText(label);

    // Framed so the unobstructed "HALLE DER HELDEN" frieze, Carrara-Alabaster floor, Top-5 Funki statues & MEIN SEELENFUNKE tower shine!
    var destPos = new THREE.Vector3(0, PANTHEON_Y + 6.8, PANTHEON_Z + 18.5);
    var destTarget = new THREE.Vector3(0, PANTHEON_Y + 4.8, PANTHEON_Z - 6.5);

    if (instant) {
      this.flight.active = false;
      this.camera.position.copy(destPos);
      this.camera.lookAt(destTarget);
      this._syncPlanetVsCityBlend(destPos);
      this._adoptCameraIntoOrbit(destTarget, 'hall');
      return;
    }
    this._startSmoothFlight(destPos, destTarget, 2.4, 'hall', 'glide');
  };

  FunkenwerkHeroes3DEngine.prototype.flyToSlot = function (slotNumber, modeOverride) {
    var anchor = this.slotAnchors[slotNumber];
    if (!anchor) {
      this.flyToHallOfHeroes(false);
      return;
    }
    this.viewMode = modeOverride || 'hall';
    this._updateNavHighlight();

    if (anchor.hero) {
      var rk = String(anchor.hero.rank_class || 'C').toUpperCase();
      var rkLabel = rk.indexOf('KLASSE') === 0 ? rk : ('KLASSE ' + rk);
      this._setStatusText(
        'Platz #' + slotNumber + ' · ' + anchor.hero.display_name +
        ' · Level ' + anchor.hero.level + ' · ' + rkLabel +
        (anchor.hero.is_me ? ' (Dein Ehrenplatz)' : '')
      );
    } else {
      this._setStatusText('Platz #' + slotNumber + ' · Freier Ehrenplatz');
    }

    this._startSmoothFlight(anchor.camPos, anchor.target, 2.1, this.viewMode, 'glide');
  };

  FunkenwerkHeroes3DEngine.prototype.flyToMyPlace = function () {
    if (!this.initialized) return;
    var mySlot = this.heroesPayload && this.heroesPayload.my_hero_slot ? parseInt(this.heroesPayload.my_hero_slot, 10) : 0;
    if (mySlot > 0 && this.slotAnchors[mySlot]) {
      this.flyToSlot(mySlot, 'my_place');
      return;
    }

    var firstFreeSlot = Math.min(TOTAL_DISPLAY_SLOTS, (this.heroesPayload.total_heroes || 0) + 1);
    var anchor = this.slotAnchors[firstFreeSlot] || this.slotAnchors[1];
    this.viewMode = 'my_place';
    this._updateNavHighlight();

    if (this.heroesPayload.customer_logged_in) {
      this._setStatusText('Freier Ehrenplatz #' + firstFreeSlot + ' · Im Kundenkonto aktivierbar');
    } else {
      this._setStatusText('Freier Ehrenplatz #' + firstFreeSlot + ' · Für Seelenfunke-Sammler bereit');
    }

    if (anchor) {
      this._startSmoothFlight(anchor.camPos, anchor.target, 2.1, 'my_place', 'glide');
    } else {
      this.flyToHallOfHeroes(false);
    }
  };

  FunkenwerkHeroes3DEngine.prototype._bindControls = function () {
    var self = this;
    var el = this.canvas;
    if (!el) return;

    el.addEventListener('contextmenu', function (e) { e.preventDefault(); });

    el.addEventListener('pointerdown', function (e) {
      if (!self.running) return;
      if (self.flight.active) {
        self.flight.active = false;
        self._adoptCameraIntoOrbit(self.orbit.target, self.viewMode === 'orbit' ? 'city' : self.viewMode);
      }
      self.orbit.isDragging = true;
      self.orbit.dragButton = e.button;
      self.orbit.lastX = e.clientX;
      self.orbit.lastY = e.clientY;
      self.orbit.movedDist = 0;
      el.style.cursor = 'grabbing';
      try { el.setPointerCapture(e.pointerId); } catch (err) { /* ignore */ }
    });

    el.addEventListener('pointermove', function (e) {
      if (!self.running || !self.orbit.isDragging) return;
      var dx = e.clientX - self.orbit.lastX;
      var dy = e.clientY - self.orbit.lastY;
      self.orbit.lastX = e.clientX;
      self.orbit.lastY = e.clientY;
      self.orbit.movedDist += Math.hypot(dx, dy);

      if (self.orbit.dragButton === 2 || e.shiftKey) {
        var panScale = self.orbit.radius * 0.0014;
        var right = new self.THREE.Vector3();
        var up = new self.THREE.Vector3(0, 1, 0);
        self.camera.getWorldDirection(right);
        right.cross(up).normalize();
        self.orbit.desiredTarget.addScaledVector(right, -dx * panScale);
        self.orbit.desiredTarget.y = clamp(self.orbit.desiredTarget.y + dy * panScale, 2.0, 260.0);
      } else {
        self.orbit.desiredTheta -= dx * 0.0052;
        self.orbit.desiredPhi = clamp(self.orbit.desiredPhi - dy * 0.0046, 0.08, Math.PI * 0.488);
      }
    });

    function endPointer(e) {
      if (!self.orbit.isDragging) return;
      self.orbit.isDragging = false;
      el.style.cursor = 'grab';
      try { el.releasePointerCapture(e.pointerId); } catch (err) { /* ignore */ }

      if (self.orbit.movedDist < 6 && e.button === 0 && self.interactiveTargets.length > 0) {
        var rect = el.getBoundingClientRect();
        self.pointerNdc.x = ((e.clientX - rect.left) / Math.max(1, rect.width)) * 2 - 1;
        self.pointerNdc.y = -((e.clientY - rect.top) / Math.max(1, rect.height)) * 2 + 1;
        self.raycaster.setFromCamera(self.pointerNdc, self.camera);
        var hits = self.raycaster.intersectObjects(self.interactiveTargets, false);
        if (hits.length > 0) {
          var ud = hits[0].object.userData || {};
          if (ud.slot) {
            self.flyToSlot(ud.slot, 'hall');
          } else if (ud.navAction === 'hall') {
            self.flyToHallOfHeroes(false);
          }
        }
      }
    }

    el.addEventListener('pointerup', endPointer);
    el.addEventListener('pointercancel', endPointer);

    el.addEventListener('wheel', function (e) {
      if (!self.running) return;
      e.preventDefault();
      if (self.flight.active) {
        self.flight.active = false;
        self._adoptCameraIntoOrbit(self.orbit.target, self.viewMode === 'orbit' ? 'city' : self.viewMode);
      }
      var zoomFactor = e.deltaY > 0 ? 1.11 : 0.90;
      self.orbit.desiredRadius = clamp(self.orbit.desiredRadius * zoomFactor, self.orbit.minRadius, self.orbit.maxRadius);
    }, { passive: false });

    el.addEventListener('touchstart', function (e) {
      if (e.touches.length === 2) {
        if (self.flight.active) self.flight.active = false;
        var dx = e.touches[0].clientX - e.touches[1].clientX;
        var dy = e.touches[0].clientY - e.touches[1].clientY;
        self.orbit.pinchDist = Math.hypot(dx, dy);
      }
    }, { passive: true });

    el.addEventListener('touchmove', function (e) {
      if (e.touches.length === 2 && self.orbit.pinchDist > 8) {
        var dx = e.touches[0].clientX - e.touches[1].clientX;
        var dy = e.touches[0].clientY - e.touches[1].clientY;
        var dist = Math.hypot(dx, dy);
        var scale = self.orbit.pinchDist / Math.max(8, dist);
        self.orbit.pinchDist = dist;
        self.orbit.desiredRadius = clamp(self.orbit.desiredRadius * scale, self.orbit.minRadius, self.orbit.maxRadius);
      }
    }, { passive: true });
  };

  FunkenwerkHeroes3DEngine.prototype.open = function (initialMode) {
    var self = this;
    this._ensureModalDom();
    this.prevBodyOverflow = document.body.style.overflow || '';
    document.body.style.overflow = 'hidden';
    this.modalEl.style.display = 'block';
    this._setStatusText('Lade goldene Wasserstadt Funkenwerk …');

    Promise.all([loadThreeScript(), this._fetchHeroesData()])
      .then(function (results) {
        var THREE = results[0];
        self._initScene(THREE);
        self._populateHeroesMonuments();
        self.resize();

        if (initialMode === 'hall') {
          self.flyToHallOfHeroes(true);
        } else if (initialMode === 'my_place') {
          self.flyToHallOfHeroes(true);
          self.flyToMyPlace();
        } else if (initialMode === 'city') {
          self.flyToCityOverview(true);
        } else {
          self.startOrbitalFlyIn();
        }

        if (!self.running) {
          self.running = true;
          self.lastFrameMs = performance.now();
          self.rafId = requestAnimationFrame(self._boundLoop);
        }
      })
      .catch(function () {
        self._setStatusText('3D-Ansicht konnte auf diesem Gerät nicht gestartet werden.');
      });
  };

  FunkenwerkHeroes3DEngine.prototype.close = function () {
    this.running = false;
    if (this.flight) {
      this.flight.active = false;
    }
    if (this.rafId) {
      cancelAnimationFrame(this.rafId);
      this.rafId = 0;
    }
    if (this.cloudVeilEl) {
      this.cloudVeilEl.style.opacity = '0';
    }
    if (this.modalEl) {
      this.modalEl.style.display = 'none';
    }
    document.body.style.overflow = this.prevBodyOverflow;
  };

  FunkenwerkHeroes3DEngine.prototype.dispose = function () {
    this.close();
    this._monumentGeneration++;

    for (var dtIdx = 0; dtIdx < this.dynamicTextures.length; dtIdx++) {
      if (this.dynamicTextures[dtIdx] && typeof this.dynamicTextures[dtIdx].dispose === 'function') {
        this.dynamicTextures[dtIdx].dispose();
      }
    }
    this.dynamicTextures = [];

    for (var gdIdx = 0; gdIdx < this.glbDisposables.length; gdIdx++) {
      if (this.glbDisposables[gdIdx] && typeof this.glbDisposables[gdIdx].dispose === 'function') {
        this.glbDisposables[gdIdx].dispose();
      }
    }
    this.glbDisposables = [];
    this.glbTemplateCache.clear();
    this.glbLoadingPromises.clear();

    if (this.sparkSpriteTex && typeof this.sparkSpriteTex.dispose === 'function') {
      this.sparkSpriteTex.dispose();
      this.sparkSpriteTex = null;
    }

    if (this.staticDisposables) {
      for (var sdIdx = 0; sdIdx < this.staticDisposables.length; sdIdx++) {
        if (this.staticDisposables[sdIdx] && typeof this.staticDisposables[sdIdx].dispose === 'function') {
          this.staticDisposables[sdIdx].dispose();
        }
      }
      this.staticDisposables = [];
    }
    this.skyPanoramaTex = null;
    this.pmremTarget = null;

    window.removeEventListener('resize', this._boundResize);
    window.removeEventListener('keydown', this._boundKeydown);
    window.removeEventListener('pagehide', this._boundPageHide);

    if (this.renderer) {
      this.renderer.dispose();
      this.renderer = null;
    }
    this.scene = null;
    this.camera = null;
    this.initialized = false;
  };

  FunkenwerkHeroes3DEngine.prototype.resize = function () {
    if (!this.renderer || !this.camera) return;
    var w = Math.max(1, window.innerWidth);
    var h = Math.max(1, window.innerHeight);
    var baseDpr = Math.min(window.devicePixelRatio || 1, 1.4);
    this.renderer.setPixelRatio(baseDpr * this.renderScale);
    this.renderer.setSize(w, h, false);
    this.camera.aspect = w / h;
    this.camera.updateProjectionMatrix();
  };

  FunkenwerkHeroes3DEngine.prototype._updateAnimations = function (t) {
    if (this.gliderInst && this.cityGroup && this.cityGroup.visible) {
      for (var i = 0; i < this.gliderData.length; i++) {
        var g = this.gliderData[i];
        var u = ((g.phase + t * g.speed) % 1 + 1) % 1;
        var wx = 0, wy = g.alt, wz = 0, yaw = 0;

        if (g.mode === 'pier') {
          var ang = this.pierAngles[g.pierIdx];
          var rotY = Math.atan2(Math.cos(ang), Math.sin(ang));
          var rDist = lerp(165, 520, u);
          wx = g.laneOffset * Math.cos(rotY) + rDist * Math.sin(rotY);
          wz = -g.laneOffset * Math.sin(rotY) + rDist * Math.cos(rotY);
          yaw = g.speed >= 0 ? rotY : (rotY + Math.PI);
        } else if (g.mode === 'ring') {
          var rang = u * Math.PI * 2;
          wx = Math.cos(rang) * g.ringR;
          wz = Math.sin(rang) * g.ringR;
          var dirSign = g.speed >= 0 ? 1 : -1;
          yaw = Math.atan2(-Math.sin(rang) * dirSign, Math.cos(rang) * dirSign);
        } else {
          var tang = u * Math.PI * 2;
          var rOrb = 54 + (i % 3) * 16;
          wx = Math.cos(tang) * rOrb;
          wz = Math.sin(tang) * rOrb;
          wy = g.alt + Math.sin(t * 1.4 + i) * 2.5;
          var tSign = g.speed >= 0 ? 1 : -1;
          yaw = Math.atan2(-Math.sin(tang) * tSign, Math.cos(tang) * tSign);
        }

        this.dummyObj.position.set(wx, wy, wz);
        this.dummyObj.rotation.set(0, yaw, 0);
        this.dummyObj.scale.set(1, 1, 1);
        this.dummyObj.updateMatrix();
        this.gliderInst.setMatrixAt(i, this.dummyObj.matrix);
      }
      this.gliderInst.instanceMatrix.needsUpdate = true;
    }

    // 60-FPS Living Pedestal Animation for the 3D Funki (.glb) Heroes + Counter-Rotating Level Aura Rings!
    for (var fi = 0; fi < this.animatedFunkiFigures.length; fi++) {
      var fg = this.animatedFunkiFigures[fi];
      if (!fg.motionRig) continue;

      var breathWave = Math.sin(t * 1.85 + fg.phase);
      var breathScaleY = 1.0 + breathWave * 0.013;
      var breathScaleXZ = 1.0 - breathWave * 0.005;
      fg.motionRig.scale.set(breathScaleXZ, breathScaleY, breathScaleXZ);

      if (fg.level >= 8) {
        // High-tier Masters, Lightbringers & Gods hover with majestic aura levitation
        fg.motionRig.position.y = 0.035 + (0.5 + 0.5 * breathWave) * 0.055;
      } else if (!fg.isSitting) {
        fg.motionRig.position.y = Math.max(0, breathWave) * 0.016;
      } else {
        fg.motionRig.position.y = 0;
      }

      // Subtle proud upper-body & gaze presence toward the Hall of Heroes
      fg.motionRig.rotation.y = Math.sin(t * 0.72 + fg.phase) * 0.082;
      fg.motionRig.rotation.z = Math.sin(t * 0.52 + fg.phase * 1.3) * 0.016;
      fg.motionRig.rotation.x = Math.sin(t * 0.94 + fg.phase) * 0.012;

      if (fg.auraOuter) {
        var auraPulse = 1.0 + Math.sin(t * 2.2 + fg.phase) * 0.032;
        fg.auraOuter.scale.set(auraPulse, auraPulse, 1.0);
        fg.auraOuter.rotation.z = t * 0.65 + fg.phase;
      }
      if (fg.auraInner) {
        fg.auraInner.rotation.z = -t * 0.95 - fg.phase;
      }
    }

    // Rising Golden Seelenfunken Spark Particles around the 5 Honor Pedestals (0 allocations per frame!)
    if (this.pedestalSparkPosAttr && this.cityGroup && this.cityGroup.visible) {
      var posArr = this.pedestalSparkPosAttr.array;
      for (var si = 0; si < this.pedestalSparkMeta.length; si++) {
        var sm = this.pedestalSparkMeta[si];
        var su = ((sm.phase + t * sm.speed) % 1 + 1) % 1;
        var sang = t * sm.swirl + sm.phase * 6.283185;
        var sr = sm.radius * (0.76 + 0.24 * Math.sin(su * 3.14159));
        var p3 = si * 3;
        posArr[p3] = sm.baseX + Math.cos(sang) * sr;
        posArr[p3 + 1] = sm.baseY + su * sm.heightSpan;
        posArr[p3 + 2] = sm.baseZ + Math.sin(sang) * sr;
      }
      this.pedestalSparkPosAttr.needsUpdate = true;
    }
  };

  FunkenwerkHeroes3DEngine.prototype._tick = function (nowMs) {
    if (!this.running) return;
    this.rafId = requestAnimationFrame(this._boundLoop);

    var rawDt = (nowMs - this.lastFrameMs) / 1000;
    this.lastFrameMs = nowMs;
    var dt = clamp(rawDt, 0.001, 0.05);
    this.clockTime += dt;

    this.smoothDt = lerp(this.smoothDt, dt, 0.08);
    if (this.smoothDt > 0.022 && this.renderScale > 0.78) {
      this.renderScale = Math.max(0.78, this.renderScale - 0.05);
      this.resize();
    } else if (this.smoothDt < 0.0155 && this.renderScale < 1.0) {
      this.renderScale = Math.min(1.0, this.renderScale + 0.04);
      this.resize();
    }

    this.planetSurfaceUniforms.uTime.value = this.clockTime;
    this.oceanUniforms.uTime.value = this.clockTime;

    if (this.flight.active) {
      this.flight.elapsed += dt;
      var rawT = clamp(this.flight.elapsed / this.flight.duration, 0, 1);
      var e = this.flight.type === 'orbital_dive' ? easeInOutQuint(rawT) : easeInOutCubic(rawT);
      var omt = 1.0 - e;

      this.camera.position.set(
        omt * omt * this.flight.fromPos.x + 2 * omt * e * this.flight.midPos.x + e * e * this.flight.toPos.x,
        omt * omt * this.flight.fromPos.y + 2 * omt * e * this.flight.midPos.y + e * e * this.flight.toPos.y,
        omt * omt * this.flight.fromPos.z + 2 * omt * e * this.flight.midPos.z + e * e * this.flight.toPos.z
      );

      var targetE = this.flight.type === 'orbital_dive' ? Math.pow(e, 1.35) : e;
      var curTarget = new this.THREE.Vector3().lerpVectors(this.flight.fromTarget, this.flight.toTarget, targetE);
      this.camera.lookAt(curTarget);
      this.orbit.target.copy(curTarget);

      if (rawT >= 1.0) {
        this.flight.active = false;
        this._adoptCameraIntoOrbit(this.flight.toTarget, this.flight.settledMode || 'city');
        if (this.flight.type === 'orbital_dive') {
          var total = this.heroesPayload.total_heroes || 0;
          this._setStatusText('Wasserstadt Funkenwerk · Klicke auf „🏛️ Halle der Helden“ oder „⭐ Mein Platz“ (' + total + ' Helden)');
        }
      }
    } else {
      var damp = 1.0 - Math.exp(-10.5 * dt);
      this.orbit.target.lerp(this.orbit.desiredTarget, damp);
      this.orbit.radius = lerp(this.orbit.radius, this.orbit.desiredRadius, damp);
      this.orbit.theta = lerp(this.orbit.theta, this.orbit.desiredTheta, damp);
      this.orbit.phi = lerp(this.orbit.phi, this.orbit.desiredPhi, damp);

      var sinPhi = Math.sin(this.orbit.phi);
      var cx = this.orbit.target.x + this.orbit.radius * sinPhi * Math.sin(this.orbit.theta);
      var cy = Math.max(this.orbit.minY, this.orbit.target.y + this.orbit.radius * Math.cos(this.orbit.phi));
      var cz = this.orbit.target.z + this.orbit.radius * sinPhi * Math.cos(this.orbit.theta);

      this.camera.position.set(cx, cy, cz);
      this.camera.lookAt(this.orbit.target);
    }

    this._syncPlanetVsCityBlend(this.camera.position);
    this._updateAnimations(this.clockTime);
    this.renderer.render(this.scene, this.camera);
  };

  window.SeelenfunkeHeroes3D = new FunkenwerkHeroes3DEngine();
  window.openFunkenwerkHeroes3D = function (mode) {
    window.SeelenfunkeHeroes3D.open(mode || 'orbit');
  };
})();
