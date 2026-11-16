(function () {
    'use strict';

    function clamp(v, lo, hi) { return Math.max(lo, Math.min(hi, v)); }
    function lerp(a, b, t) { return a + (b - a) * t; }

    function hexToRgb(hex) {
        var clean = String(hex).replace('#', '');
        var n = parseInt(clean, 16);
        return [(n >> 16) & 255, (n >> 8) & 255, n & 255];
    }

    function applySrgb(tex) {
        if (!tex || typeof THREE === 'undefined') return tex;
        if (THREE.SRGBColorSpace) {
            tex.colorSpace = THREE.SRGBColorSpace;
        } else if (THREE.sRGBEncoding) {
            tex.encoding = THREE.sRGBEncoding;
        }
        return tex;
    }

    function makeStudioMatcap(baseHex, specHex, rimHex, specPower, rimStrength) {
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

        var lx1 = -0.34, ly1 = 0.62, lz1 = 0.706;
        var hx1 = -0.19, hy1 = 0.38, hz1 = 0.905;
        var lx2 = 0.50, ly2 = 0.34, lz2 = 0.796;
        var hx2 = 0.31, hy2 = 0.22, hz2 = 0.925;
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
                    var horizon = Math.exp(-Math.pow((ny + 0.14) * 5.0, 2)) * (specPower >= 22 ? 0.12 : 0.03);
                    var hemi = skyBounce + 0.48 * ndl1 + 0.21 * ndl2 + horizon;

                    var ndh1 = Math.max(0, nx * hx1 + ny * hy1 + nz * hz1);
                    var ndh2 = Math.max(0, nx * hx2 + ny * hy2 + nz * hz2);
                    var spBroad = Math.pow(ndh1, Math.max(6, specPower * 0.45)) * 0.26;
                    var spCrisp = Math.pow(ndh1, specPower * 1.35) * 0.80 + Math.pow(ndh2, specPower * 0.9) * 0.18;
                    var sp = spBroad + spCrisp;
                    var fresnel = Math.pow(1.0 - nz, 2.6) * rimStrength;

                    r = base[0] * hemi + spec[0] * sp + rim[0] * fresnel;
                    g = base[1] * hemi + spec[1] * sp + rim[1] * fresnel;
                    b = base[2] * hemi + spec[2] * sp + rim[2] * fresnel;
                } else {
                    var len = Math.sqrt(r2);
                    var uy = ny / len;
                    var h2 = 0.35 + 0.25 * (uy * 0.5 + 0.5);
                    r = base[0] * h2 + rim[0] * rimStrength * 0.9;
                    g = base[1] * h2 + rim[1] * rimStrength * 0.9;
                    b = base[2] * h2 + rim[2] * rimStrength * 0.9;
                }
                var p = (y * size + x) * 4;
                d[p] = clamp(Math.round(r), 0, 255);
                d[p + 1] = clamp(Math.round(g), 0, 255);
                d[p + 2] = clamp(Math.round(b), 0, 255);
                d[p + 3] = 255;
            }
        }
        ctx.putImageData(img, 0, 0);
        var tex = new THREE.CanvasTexture(c);
        tex.minFilter = THREE.LinearFilter;
        tex.magFilter = THREE.LinearFilter;
        return applySrgb(tex);
    }

    function createRoundedRectShape(w, h, r) {
        var s = new THREE.Shape();
        var hw = w * 0.5;
        var hh = h * 0.5;
        var rad = Math.min(r, hw * 0.9, hh * 0.9);
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

    function createRoundedRectHolePath(w, h, r) {
        var p = new THREE.Path();
        var hw = w * 0.5;
        var hh = h * 0.5;
        var rad = Math.min(r, hw * 0.9, hh * 0.9);
        p.moveTo(-hw + rad, -hh);
        p.quadraticCurveTo(-hw, -hh, -hw, -hh + rad);
        p.lineTo(-hw, hh - rad);
        p.quadraticCurveTo(-hw, hh, -hw + rad, hh);
        p.lineTo(hw - rad, hh);
        p.quadraticCurveTo(hw, hh, hw, hh - rad);
        p.lineTo(hw, -hh + rad);
        p.quadraticCurveTo(hw, -hh, hw - rad, -hh);
        p.lineTo(-hw + rad, -hh);
        return p;
    }

    function makeChestEmblemTexture() {
        var c = document.createElement('canvas');
        c.width = 768;
        c.height = 320;
        var ctx = c.getContext('2d');
        ctx.clearRect(0, 0, c.width, c.height);

        var goldGrad = ctx.createLinearGradient(180, 90, 588, 250);
        goldGrad.addColorStop(0.0, '#E8CA82');
        goldGrad.addColorStop(0.35, '#FFF3C4');
        goldGrad.addColorStop(0.65, '#C5A059');
        goldGrad.addColorStop(1.0, '#9E7832');

        ctx.save();
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.shadowColor = 'rgba(0, 0, 0, 0.65)';
        ctx.shadowBlur = 8;
        ctx.shadowOffsetY = 3;

        ctx.fillStyle = goldGrad;
        ctx.font = '900 78px sans-serif';
        ctx.fillText('FALCON', 384, 152);

        ctx.font = '700 42px Georgia, serif';
        ctx.fillStyle = '#E5C37B';
        ctx.fillText('Mitgründer', 384, 224);
        ctx.restore();

        var tex = new THREE.CanvasTexture(c);
        tex.minFilter = THREE.LinearFilter;
        tex.magFilter = THREE.LinearFilter;
        return applySrgb(tex);
    }

    function makeArmShieldTexture() {
        var c = document.createElement('canvas');
        c.width = 256;
        c.height = 256;
        var ctx = c.getContext('2d');
        ctx.clearRect(0, 0, 256, 256);

        ctx.fillStyle = '#11141C';
        ctx.strokeStyle = '#D4AF37';
        ctx.lineWidth = 12;
        ctx.beginPath();
        ctx.moveTo(44, 34);
        ctx.lineTo(212, 34);
        ctx.lineTo(202, 154);
        ctx.quadraticCurveTo(128, 226, 128, 226);
        ctx.quadraticCurveTo(128, 226, 54, 154);
        ctx.closePath();
        ctx.fill();
        ctx.stroke();

        // Gold star
        ctx.fillStyle = '#F3D587';
        ctx.beginPath();
        for (var i = 0; i < 10; i++) {
            var a = -Math.PI / 2 + (i * Math.PI) / 5;
            var rad = (i % 2 === 0) ? 34 : 14;
            var sx = 128 + Math.cos(a) * rad;
            var sy = 102 + Math.sin(a) * rad;
            if (i === 0) ctx.moveTo(sx, sy); else ctx.lineTo(sx, sy);
        }
        ctx.closePath();
        ctx.fill();

        // Gold chevron
        ctx.strokeStyle = '#D4AF37';
        ctx.lineWidth = 13;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        ctx.beginPath();
        ctx.moveTo(82, 148);
        ctx.lineTo(128, 182);
        ctx.lineTo(174, 148);
        ctx.stroke();

        var tex = new THREE.CanvasTexture(c);
        tex.minFilter = THREE.LinearFilter;
        tex.magFilter = THREE.LinearFilter;
        return applySrgb(tex);
    }

    function makeMonocleLensTexture() {
        var c = document.createElement('canvas');
        c.width = 256;
        c.height = 256;
        var ctx = c.getContext('2d');
        var rg = ctx.createRadialGradient(128, 128, 18, 128, 128, 124);
        rg.addColorStop(0, 'rgba(255, 248, 225, 0.10)');
        rg.addColorStop(0.75, 'rgba(212, 175, 55, 0.16)');
        rg.addColorStop(1, 'rgba(255, 238, 188, 0.42)');
        ctx.fillStyle = rg;
        ctx.beginPath();
        ctx.arc(128, 128, 124, 0, Math.PI * 2);
        ctx.fill();

        ctx.save();
        ctx.beginPath();
        ctx.arc(128, 128, 120, 0, Math.PI * 2);
        ctx.clip();
        var glint = ctx.createLinearGradient(30, 30, 210, 210);
        glint.addColorStop(0.0, 'rgba(255,255,255,0)');
        glint.addColorStop(0.36, 'rgba(255,252,240,0.42)');
        glint.addColorStop(0.46, 'rgba(255,255,255,0.06)');
        glint.addColorStop(1.0, 'rgba(255,255,255,0)');
        ctx.fillStyle = glint;
        ctx.fillRect(0, 0, 256, 256);
        ctx.restore();

        var tex = new THREE.CanvasTexture(c);
        tex.minFilter = THREE.LinearFilter;
        tex.magFilter = THREE.LinearFilter;
        return applySrgb(tex);
    }

    function makeStudioFloorTexture(isDark) {
        var c = document.createElement('canvas');
        c.width = 512;
        c.height = 512;
        var ctx = c.getContext('2d');
        ctx.clearRect(0, 0, 512, 512);

        var g = ctx.createRadialGradient(256, 256, 12, 256, 256, 248);
        if (isDark) {
            g.addColorStop(0.0, 'rgba(0, 0, 0, 0.72)');
            g.addColorStop(0.38, 'rgba(18, 15, 10, 0.45)');
            g.addColorStop(0.76, 'rgba(197, 160, 89, 0.08)');
            g.addColorStop(1.0, 'rgba(9, 11, 16, 0.0)');
        } else {
            g.addColorStop(0.0, 'rgba(35, 28, 18, 0.24)');
            g.addColorStop(0.40, 'rgba(197, 160, 89, 0.11)');
            g.addColorStop(0.80, 'rgba(197, 160, 89, 0.04)');
            g.addColorStop(1.0, 'rgba(248, 246, 241, 0.0)');
        }
        ctx.fillStyle = g;
        ctx.beginPath();
        ctx.arc(256, 256, 248, 0, Math.PI * 2);
        ctx.fill();

        ctx.strokeStyle = isDark ? 'rgba(197, 160, 89, 0.42)' : 'rgba(197, 160, 89, 0.50)';
        ctx.lineWidth = 3.0;
        ctx.beginPath();
        ctx.arc(256, 256, 196, 0, Math.PI * 2);
        ctx.stroke();

        ctx.strokeStyle = isDark ? 'rgba(197, 160, 89, 0.18)' : 'rgba(197, 160, 89, 0.24)';
        ctx.lineWidth = 1.5;
        ctx.beginPath();
        ctx.arc(256, 256, 224, 0, Math.PI * 2);
        ctx.stroke();

        var tex = new THREE.CanvasTexture(c);
        tex.minFilter = THREE.LinearFilter;
        tex.magFilter = THREE.LinearFilter;
        return applySrgb(tex);
    }

    function makeBackdropHaloTexture(isDark) {
        var c = document.createElement('canvas');
        c.width = 512;
        c.height = 512;
        var ctx = c.getContext('2d');
        var g = ctx.createRadialGradient(256, 256, 10, 256, 256, 246);
        if (isDark) {
            g.addColorStop(0.0, 'rgba(197, 160, 89, 0.24)');
            g.addColorStop(0.45, 'rgba(197, 160, 89, 0.09)');
            g.addColorStop(1.0, 'rgba(9, 11, 16, 0.0)');
        } else {
            g.addColorStop(0.0, 'rgba(197, 160, 89, 0.18)');
            g.addColorStop(0.50, 'rgba(197, 160, 89, 0.06)');
            g.addColorStop(1.0, 'rgba(248, 246, 241, 0.0)');
        }
        ctx.fillStyle = g;
        ctx.fillRect(0, 0, 512, 512);
        var tex = new THREE.CanvasTexture(c);
        tex.minFilter = THREE.LinearFilter;
        tex.magFilter = THREE.LinearFilter;
        return applySrgb(tex);
    }

    var EMOTION_PRESETS = {
        idle:      { lidTop: 0.04, lidBot: 0.02, browTilt: -0.03, browY: 0.0,  mouthCurve: 0.30, mouthOpen: 0.03, pupilScale: 1.00, mustacheLift: 0.08, headPitch: 0.00,  headRoll: 0.00  },
        listening: { lidTop: 0.00, lidBot: 0.05, browTilt: -0.11, browY: -3.5, mouthCurve: 0.26, mouthOpen: 0.06, pupilScale: 1.08, mustacheLift: 0.14, headPitch: 0.05,  headRoll: 0.04  },
        thinking:  { lidTop: 0.16, lidBot: 0.12, browTilt: 0.18,  browY: 1.5,  mouthCurve: 0.10, mouthOpen: 0.02, pupilScale: 0.95, mustacheLift: 0.04, headPitch: -0.04, headRoll: -0.06 },
        speaking:  { lidTop: 0.02, lidBot: 0.06, browTilt: -0.07, browY: -2.0, mouthCurve: 0.40, mouthOpen: 0.28, pupilScale: 1.03, mustacheLift: 0.20, headPitch: -0.01, headRoll: 0.02  },
        happy:     { lidTop: 0.08, lidBot: 0.24, browTilt: -0.14, browY: -3.8, mouthCurve: 0.78, mouthOpen: 0.24, pupilScale: 1.08, mustacheLift: 0.38, headPitch: -0.03, headRoll: 0.05  },
        proud:     { lidTop: 0.12, lidBot: 0.20, browTilt: -0.08, browY: -2.5, mouthCurve: 0.66, mouthOpen: 0.14, pupilScale: 1.04, mustacheLift: 0.34, headPitch: -0.07, headRoll: -0.04 },
        focused:   { lidTop: 0.18, lidBot: 0.14, browTilt: 0.24,  browY: 3.2,  mouthCurve: 0.12, mouthOpen: 0.04, pupilScale: 0.92, mustacheLift: 0.02, headPitch: 0.04,  headRoll: 0.00  }
    };

    var state = {
        mode: 'idle',
        audioLevel: 0.0,
        emotion: 'idle',
        isDark: true,
        cameraActive: false
    };

    var canvas = document.getElementById('falcon-canvas');
    var renderer = new THREE.WebGLRenderer({
        canvas: canvas,
        antialias: true,
        alpha: false,
        powerPreference: 'high-performance'
    });
    if ('outputColorSpace' in renderer && THREE.SRGBColorSpace) {
        renderer.outputColorSpace = THREE.SRGBColorSpace;
    } else if ('outputEncoding' in renderer && THREE.sRGBEncoding) {
        renderer.outputEncoding = THREE.sRGBEncoding;
    }

    var scene = new THREE.Scene();
    scene.background = new THREE.Color(0x090B10);

    var camera = new THREE.PerspectiveCamera(36, 9 / 16, 0.1, 30);
    camera.position.set(0, 1.15, 2.38);
    camera.lookAt(0, 1.06, 0);

    // Materials
    var mats = {
        gold24k:      new THREE.MeshMatcapMaterial({ matcap: makeStudioMatcap('#C89F4D', '#FFFDF2', '#F7DA88', 32, 0.50) }),
        goldBright:   new THREE.MeshMatcapMaterial({ matcap: makeStudioMatcap('#DEC070', '#FFFFFF', '#FFEAA6', 36, 0.55) }),
        suitDark:     new THREE.MeshMatcapMaterial({ matcap: makeStudioMatcap('#161922', '#5A6075', '#C5A059', 20, 0.28) }),
        suitLight:    new THREE.MeshMatcapMaterial({ matcap: makeStudioMatcap('#1F2430', '#6D748C', '#C5A059', 20, 0.32) }),
        obsidian:     new THREE.MeshMatcapMaterial({ matcap: makeStudioMatcap('#0E1016', '#464B5E', '#C5A059', 24, 0.22) }),
        hairObsidian: new THREE.MeshMatcapMaterial({ matcap: makeStudioMatcap('#171310', '#6E5A44', '#D4AF37', 26, 0.34) }),
        jointDark:    new THREE.MeshMatcapMaterial({ matcap: makeStudioMatcap('#0B0D12', '#3A3E4D', '#8A703D', 14, 0.16) }),
        earGlow:      new THREE.MeshBasicMaterial({ color: 0xE6BD68 }),
        monocleLens:  new THREE.MeshBasicMaterial({ map: makeMonocleLensTexture(), transparent: true, opacity: 0.85, depthWrite: false, side: THREE.DoubleSide })
    };

    var floorTexDark = makeStudioFloorTexture(true);
    var floorTexLight = makeStudioFloorTexture(false);
    var floorMat = new THREE.MeshBasicMaterial({ map: floorTexDark, transparent: true, depthWrite: false });
    var floorDisc = new THREE.Mesh(new THREE.PlaneGeometry(2.35, 2.35), floorMat);
    floorDisc.rotation.x = -Math.PI / 2;
    floorDisc.position.y = 0.01;
    scene.add(floorDisc);

    var haloTexDark = makeBackdropHaloTexture(true);
    var haloTexLight = makeBackdropHaloTexture(false);
    var haloMat = new THREE.MeshBasicMaterial({ map: haloTexDark, transparent: true, depthWrite: false });
    var haloPlane = new THREE.Mesh(new THREE.PlaneGeometry(2.6, 2.6), haloMat);
    haloPlane.position.set(0, 1.12, -0.68);
    scene.add(haloPlane);

    // Pre-bake Retina 768x480 Visor Backdrop & 192x192 Amber-Gold Iris Sprite
    var visorBgCanvas = document.createElement('canvas');
    visorBgCanvas.width = 768;
    visorBgCanvas.height = 480;
    (function bakeVisorBg() {
        var bctx = visorBgCanvas.getContext('2d', { alpha: false });
        bctx.save();
        bctx.scale(2, 2);
        var bgGrad = bctx.createRadialGradient(192, 114, 18, 192, 120, 238);
        bgGrad.addColorStop(0, '#191714');
        bgGrad.addColorStop(0.68, '#101116');
        bgGrad.addColorStop(1, '#08090D');
        bctx.fillStyle = bgGrad;
        bctx.fillRect(0, 0, 384, 240);

        bctx.fillStyle = 'rgba(229, 192, 123, 0.016)';
        for (var y = 16; y < 228; y += 4) {
            bctx.fillRect(10, y, 364, 1);
        }

        // Top 24K Gold accent bar inside visor
        var topGrad = bctx.createLinearGradient(16, 0, 368, 0);
        topGrad.addColorStop(0, '#8E6D2F');
        topGrad.addColorStop(0.35, '#E5C07B');
        topGrad.addColorStop(0.5, '#FFF2C6');
        topGrad.addColorStop(0.65, '#D4AF37');
        topGrad.addColorStop(1, '#8E6D2F');
        bctx.fillStyle = topGrad;
        bctx.fillRect(18, 10, 348, 4.5);

        // Diagonal luxury glass reflection
        bctx.beginPath();
        bctx.moveTo(0, 14);
        bctx.lineTo(176, 14);
        bctx.lineTo(98, 240);
        bctx.lineTo(0, 240);
        bctx.closePath();
        var sheen = bctx.createLinearGradient(0, 14, 155, 240);
        sheen.addColorStop(0, 'rgba(255, 246, 222, 0.10)');
        sheen.addColorStop(0.55, 'rgba(255, 242, 210, 0.035)');
        sheen.addColorStop(1, 'rgba(255, 242, 210, 0.005)');
        bctx.fillStyle = sheen;
        bctx.fill();

        bctx.strokeStyle = 'rgba(197, 160, 89, 0.34)';
        bctx.lineWidth = 1.4;
        bctx.strokeRect(9, 9, 366, 222);
        bctx.restore();
    })();

    var irisSpriteCanvas = document.createElement('canvas');
    irisSpriteCanvas.width = 192;
    irisSpriteCanvas.height = 192;
    (function bakeGoldIris() {
        var ictx = irisSpriteCanvas.getContext('2d');
        ictx.save();
        ictx.scale(2, 2);

        ictx.fillStyle = '#1E1103';
        ictx.beginPath();
        ictx.ellipse(48, 48, 40.5, 42.0, 0, 0, Math.PI * 2);
        ictx.fill();

        var ig = ictx.createRadialGradient(48, 58, 5, 48, 48, 42);
        ig.addColorStop(0.0, '#FFF1B8');
        ig.addColorStop(0.28, '#E3A83B');
        ig.addColorStop(0.62, '#9A6314');
        ig.addColorStop(0.88, '#472906');
        ig.addColorStop(1.0, '#211202');
        ictx.fillStyle = ig;
        ictx.beginPath();
        ictx.ellipse(48, 48, 38.8, 40.4, 0, 0, Math.PI * 2);
        ictx.fill();

        for (var f = 0; f < 28; f++) {
            var ang = (f / 28) * Math.PI * 2;
            var rIn = 19.0 + (f % 2) * 2.0;
            var rOut = 36.4 - (f % 3) * 1.8;
            ictx.strokeStyle = (f % 3 === 0)
                ? 'rgba(255, 242, 195, 0.36)'
                : 'rgba(232, 174, 72, 0.28)';
            ictx.lineWidth = (f % 2 === 0) ? 1.4 : 0.9;
            ictx.beginPath();
            ictx.moveTo(48 + Math.cos(ang) * rIn, 47 + Math.sin(ang) * rIn);
            ictx.lineTo(48 + Math.cos(ang) * rOut, 48 + Math.sin(ang) * rOut);
            ictx.stroke();
        }

        var topShad = ictx.createLinearGradient(0, 7, 0, 42);
        topShad.addColorStop(0, 'rgba(18, 10, 2, 0.62)');
        topShad.addColorStop(1, 'rgba(18, 10, 2, 0)');
        ictx.fillStyle = topShad;
        ictx.beginPath();
        ictx.ellipse(48, 48, 38.8, 40.4, 0, 0, Math.PI * 2);
        ictx.fill();

        ictx.strokeStyle = 'rgba(255, 224, 150, 0.50)';
        ictx.lineWidth = 1.5;
        ictx.fillStyle = '#140B02';
        ictx.beginPath();
        ictx.ellipse(48, 46.5, 18.8, 20.6, 0, 0, Math.PI * 2);
        ictx.fill();
        ictx.stroke();

        var caustGrad = ictx.createLinearGradient(22, 68, 74, 68);
        caustGrad.addColorStop(0, 'rgba(235, 178, 74, 0.45)');
        caustGrad.addColorStop(0.5, 'rgba(255, 244, 200, 0.92)');
        caustGrad.addColorStop(1, 'rgba(235, 178, 74, 0.45)');
        ictx.strokeStyle = caustGrad;
        ictx.lineWidth = 3.5;
        ictx.lineCap = 'round';
        ictx.beginPath();
        ictx.arc(48, 50.5, 26.2, 0.20 * Math.PI, 0.80 * Math.PI);
        ictx.stroke();

        // Catchlights
        ictx.fillStyle = '#FFFFFF';
        ictx.beginPath();
        ictx.ellipse(35.5, 33.5, 10.4, 11.8, -0.22, 0, Math.PI * 2);
        ictx.fill();

        ictx.fillStyle = 'rgba(255, 248, 228, 0.95)';
        ictx.beginPath();
        ictx.arc(61.5, 59.5, 4.8, 0, Math.PI * 2);
        ictx.fill();

        ictx.restore();
    })();

    var visorCanvas = document.createElement('canvas');
    visorCanvas.width = 768;
    visorCanvas.height = 480;
    var visorCtx = visorCanvas.getContext('2d', { alpha: false });
    var visorTex = applySrgb(new THREE.CanvasTexture(visorCanvas));
    visorTex.minFilter = THREE.LinearFilter;
    visorTex.magFilter = THREE.LinearFilter;

    // ========================================================================
    // BUILD FALCON 3D RIG
    // ========================================================================
    var rig = {};
    var suitMeshes = [];

    var root = new THREE.Group();
    scene.add(root);
    rig.root = root;

    var pelvis = new THREE.Group();
    pelvis.position.y = 0.56;
    root.add(pelvis);
    rig.pelvis = pelvis;

    var hipPoints = [
        new THREE.Vector2(0.00, -0.10),
        new THREE.Vector2(0.19, -0.09),
        new THREE.Vector2(0.255, -0.01),
        new THREE.Vector2(0.245, 0.085),
        new THREE.Vector2(0.00, 0.085)
    ];
    var hipMesh = new THREE.Mesh(new THREE.LatheGeometry(hipPoints, 28), mats.suitDark);
    hipMesh.scale.set(1.06, 1.0, 0.84);
    pelvis.add(hipMesh);
    suitMeshes.push(hipMesh);

    var beltRing = new THREE.Mesh(new THREE.TorusGeometry(0.248, 0.024, 14, 34), mats.gold24k);
    beltRing.rotation.x = Math.PI / 2;
    beltRing.position.y = 0.075;
    var buckleFrame = new THREE.Mesh(new THREE.BoxGeometry(0.105, 0.062, 0.026), mats.gold24k);
    buckleFrame.position.set(0, 0.075, 0.215);
    var buckleCore = new THREE.Mesh(new THREE.BoxGeometry(0.076, 0.040, 0.030), mats.obsidian);
    buckleCore.position.set(0, 0.075, 0.217);
    var buckleGem = new THREE.Mesh(new THREE.OctahedronGeometry(0.018, 0), mats.goldBright);
    buckleGem.scale.set(1.0, 1.2, 0.45);
    buckleGem.position.set(0, 0.075, 0.233);
    pelvis.add(beltRing, buckleFrame, buckleCore, buckleGem);

    function buildLeg(sideSign) {
        var hipPivot = new THREE.Group();
        hipPivot.position.set(sideSign * 0.125, -0.05, 0);
        pelvis.add(hipPivot);

        var thigh = new THREE.Mesh(new THREE.CylinderGeometry(0.078, 0.066, 0.24, 22), mats.suitDark);
        thigh.position.y = -0.12;
        suitMeshes.push(thigh);

        var kneeJoint = new THREE.Mesh(new THREE.SphereGeometry(0.064, 16, 12), mats.obsidian);
        kneeJoint.position.y = -0.24;
        var kneeTrim = new THREE.Mesh(new THREE.TorusGeometry(0.063, 0.008, 10, 24), mats.gold24k);
        kneeTrim.rotation.x = Math.PI / 2;
        kneeTrim.position.y = -0.23;

        var shin = new THREE.Mesh(new THREE.CylinderGeometry(0.066, 0.074, 0.22, 22), mats.suitDark);
        shin.position.y = -0.35;
        suitMeshes.push(shin);

        var bootCuff = new THREE.Mesh(new THREE.TorusGeometry(0.076, 0.015, 12, 26), mats.gold24k);
        bootCuff.rotation.x = Math.PI / 2;
        bootCuff.position.y = -0.45;
        var boot = new THREE.Mesh(new THREE.BoxGeometry(0.155, 0.095, 0.22), mats.obsidian);
        boot.position.set(0, -0.49, 0.03);
        hipPivot.add(thigh, kneeJoint, kneeTrim, shin, bootCuff, boot);
        return hipPivot;
    }
    rig.legL = buildLeg(-1);
    rig.legR = buildLeg(1);

    // Tailored Torso (Maßanzug-Rüstung)
    var torso = new THREE.Group();
    torso.position.y = 0.085;
    pelvis.add(torso);
    rig.torso = torso;

    var torsoPoints = [
        new THREE.Vector2(0.00, 0.00),
        new THREE.Vector2(0.238, 0.00),
        new THREE.Vector2(0.258, 0.14),
        new THREE.Vector2(0.292, 0.31),
        new THREE.Vector2(0.275, 0.43),
        new THREE.Vector2(0.195, 0.47),
        new THREE.Vector2(0.00, 0.47)
    ];
    var chestMesh = new THREE.Mesh(new THREE.LatheGeometry(torsoPoints, 34), mats.suitDark);
    chestMesh.scale.set(1.10, 1.0, 0.84);
    torso.add(chestMesh);
    suitMeshes.push(chestMesh);

    // Curved Chest Inscription: FALCON / Mitgründer
    var emblemGeo = new THREE.CylinderGeometry(0.295, 0.262, 0.24, 34, 1, true, -0.72, 1.44);
    var emblemMesh = new THREE.Mesh(
        emblemGeo,
        new THREE.MeshBasicMaterial({ map: makeChestEmblemTexture(), transparent: true, depthWrite: false })
    );
    emblemMesh.scale.set(1.115, 1.0, 0.875);
    emblemMesh.position.y = 0.205;
    torso.add(emblemMesh);

    // 24K Gold V-Lapel Suit Collar
    function buildLapelWing(sideSign) {
        var sh = new THREE.Shape();
        sh.moveTo(0, 0);
        sh.lineTo(sideSign * 0.145, 0.145);
        sh.lineTo(sideSign * 0.095, 0.175);
        sh.lineTo(0, 0.045);
        sh.closePath();
        var geo = new THREE.ExtrudeGeometry(sh, {
            depth: 0.022,
            bevelEnabled: true,
            bevelThickness: 0.006,
            bevelSize: 0.005,
            bevelSegments: 2
        });
        var m = new THREE.Mesh(geo, mats.gold24k);
        m.position.set(0, 0.305, 0.225);
        m.rotation.x = -0.18;
        m.rotation.y = sideSign * 0.16;
        return m;
    }
    torso.add(buildLapelWing(-1), buildLapelWing(1));

    var collarRing = new THREE.Mesh(new THREE.TorusGeometry(0.192, 0.030, 14, 32), mats.gold24k);
    collarRing.rotation.x = Math.PI / 2;
    collarRing.position.y = 0.468;
    var neckCyl = new THREE.Mesh(new THREE.CylinderGeometry(0.108, 0.128, 0.12, 22), mats.jointDark);
    neckCyl.position.y = 0.505;
    var tiePin = new THREE.Mesh(new THREE.OctahedronGeometry(0.022, 0), mats.goldBright);
    tiePin.scale.set(1.0, 1.3, 0.45);
    tiePin.position.set(0, 0.335, 0.246);
    torso.add(collarRing, neckCyl, tiePin);

    // Anchor on right lapel for monocle chain
    var lapelChainAnchor = new THREE.Group();
    lapelChainAnchor.position.set(0.155, 0.365, 0.228);
    torso.add(lapelChainAnchor);

    // Articulated Arms & Gold Hands
    function makeFingerSegment(r, h, mat) {
        var g = new THREE.Group();
        var cyl = new THREE.Mesh(new THREE.CylinderGeometry(r * 0.92, r, h, 10), mat);
        var cap1 = new THREE.Mesh(new THREE.SphereGeometry(r, 10, 8), mat);
        cap1.position.y = -h * 0.5;
        var cap2 = new THREE.Mesh(new THREE.SphereGeometry(r * 0.92, 10, 8), mat);
        cap2.position.y = h * 0.5;
        g.add(cyl, cap1, cap2);
        return g;
    }

    function buildArm(sideSign, withStarShield) {
        var shoulderMount = new THREE.Group();
        shoulderMount.position.set(sideSign * 0.325, 0.39, 0.0);
        torso.add(shoulderMount);

        var pauldron = new THREE.Mesh(
            new THREE.SphereGeometry(0.104, 20, 14, 0, Math.PI * 2, 0, Math.PI * 0.52),
            mats.suitDark
        );
        pauldron.rotation.z = -sideSign * 0.35;
        suitMeshes.push(pauldron);

        var shGoldRim = new THREE.Mesh(new THREE.TorusGeometry(0.102, 0.013, 12, 28), mats.gold24k);
        shGoldRim.rotation.x = Math.PI / 2;
        shGoldRim.rotation.y = -sideSign * 0.35;
        shoulderMount.add(pauldron, shGoldRim);

        var armPivot = new THREE.Group();
        shoulderMount.add(armPivot);

        var shJoint = new THREE.Mesh(new THREE.SphereGeometry(0.074, 16, 12), mats.jointDark);
        var upperArm = new THREE.Mesh(new THREE.CylinderGeometry(0.072, 0.062, 0.22, 22), mats.suitDark);
        upperArm.position.y = -0.115;
        suitMeshes.push(upperArm);

        var bicepGold = new THREE.Mesh(new THREE.TorusGeometry(0.067, 0.011, 10, 24), mats.gold24k);
        bicepGold.rotation.x = Math.PI / 2;
        bicepGold.position.y = -0.09;
        armPivot.add(shJoint, upperArm, bicepGold);

        var elbowPivot = new THREE.Group();
        elbowPivot.position.y = -0.225;
        armPivot.add(elbowPivot);

        var elbowBall = new THREE.Mesh(new THREE.SphereGeometry(0.060, 14, 12), mats.gold24k);
        var forearm = new THREE.Mesh(new THREE.CylinderGeometry(0.064, 0.076, 0.21, 22), mats.suitDark);
        forearm.position.y = -0.108;
        suitMeshes.push(forearm);

        var wristCuff = new THREE.Mesh(new THREE.TorusGeometry(0.076, 0.016, 12, 26), mats.gold24k);
        wristCuff.rotation.x = Math.PI / 2;
        wristCuff.position.y = -0.205;
        elbowPivot.add(elbowBall, forearm, wristCuff);

        if (withStarShield) {
            var shieldPlane = new THREE.Mesh(
                new THREE.PlaneGeometry(0.135, 0.135),
                new THREE.MeshBasicMaterial({ map: makeArmShieldTexture(), transparent: true, side: THREE.DoubleSide })
            );
            shieldPlane.position.set(sideSign * 0.072, -0.115, 0.025);
            shieldPlane.rotation.y = sideSign * 1.28;
            elbowPivot.add(shieldPlane);
        }

        var handPivot = new THREE.Group();
        handPivot.position.y = -0.245;
        elbowPivot.add(handPivot);

        var palm = new THREE.Mesh(new THREE.SphereGeometry(0.066, 16, 14), mats.obsidian);
        palm.scale.set(0.95, 1.05, 0.68);
        var palmBackPlate = new THREE.Mesh(new THREE.BoxGeometry(0.088, 0.082, 0.024), mats.gold24k);
        palmBackPlate.position.set(0, 0, 0.032);
        handPivot.add(palm, palmBackPlate);

        for (var fi = 0; fi < 4; fi++) {
            var fx = (fi - 1.5) * 0.024;
            var fSeg = makeFingerSegment(0.013, 0.044, mats.gold24k);
            fSeg.position.set(fx, -0.068, 0.008);
            fSeg.rotation.x = 0.28;
            handPivot.add(fSeg);
        }
        var thumb = makeFingerSegment(0.014, 0.042, mats.gold24k);
        thumb.position.set(-sideSign * 0.056, -0.022, 0.026);
        thumb.rotation.z = sideSign * 0.55;
        thumb.rotation.x = 0.32;
        handPivot.add(thumb);

        return { armPivot: armPivot, elbowPivot: elbowPivot, handPivot: handPivot };
    }

    // Viewer's right = Falcon's left (+x has star crest shield in avatar, or right arm adjusts monocle!)
    rig.armL = buildArm(-1, false);
    rig.armR = buildArm(1, true);

    // ========================================================================
    // HEAD HOUSING, 24K GOLD BEZEL, EARS, GENTLEMAN HAIR & 3D MONOCLE
    // ========================================================================
    var headPivot = new THREE.Group();
    headPivot.position.y = 0.62;
    torso.add(headPivot);
    rig.head = headPivot;

    var headW = 0.88, headH = 0.56, headR = 0.135;
    var casingGeo = new THREE.ExtrudeGeometry(createRoundedRectShape(headW, headH, headR), {
        depth: 0.28,
        bevelEnabled: true,
        bevelThickness: 0.045,
        bevelSize: 0.038,
        bevelSegments: 5
    });
    casingGeo.center();
    var casingMesh = new THREE.Mesh(casingGeo, mats.obsidian);
    headPivot.add(casingMesh);

    // 24K Gold Front Frame Bezel
    var bezelOuter = createRoundedRectShape(headW + 0.055, headH + 0.055, headR + 0.02);
    bezelOuter.holes.push(createRoundedRectHolePath(headW - 0.055, headH - 0.055, headR - 0.025));
    var bezelGeo = new THREE.ExtrudeGeometry(bezelOuter, {
        depth: 0.055,
        bevelEnabled: true,
        bevelThickness: 0.018,
        bevelSize: 0.016,
        bevelSegments: 4
    });
    bezelGeo.center();
    var bezelMesh = new THREE.Mesh(bezelGeo, mats.gold24k);
    bezelMesh.position.z = 0.155;
    headPivot.add(bezelMesh);

    // Rear 24K Gold Rim Accent
    var rearRim = new THREE.Mesh(bezelGeo, mats.gold24k);
    rearRim.scale.set(0.95, 0.95, 0.65);
    rearRim.position.z = -0.155;
    headPivot.add(rearRim);

    // Retina Visor Face Screen
    var visorPlane = new THREE.Mesh(
        new THREE.PlaneGeometry(headW - 0.04, headH - 0.04),
        new THREE.MeshBasicMaterial({ map: visorTex })
    );
    visorPlane.position.z = 0.172;
    headPivot.add(visorPlane);

    // Side Gold Ear Modules
    function buildEarModule(sideSign) {
        var eg = new THREE.Group();
        eg.position.set(sideSign * 0.495, 0.0, 0.0);
        var base = new THREE.Mesh(new THREE.CylinderGeometry(0.115, 0.125, 0.055, 24), mats.gold24k);
        base.rotation.z = Math.PI / 2;
        var cap = new THREE.Mesh(new THREE.CylinderGeometry(0.082, 0.095, 0.045, 24), mats.goldBright);
        cap.rotation.z = Math.PI / 2;
        cap.position.x = sideSign * 0.036;
        var glowRing = new THREE.Mesh(new THREE.TorusGeometry(0.096, 0.010, 10, 26), mats.earGlow);
        glowRing.rotation.y = Math.PI / 2;
        glowRing.position.x = sideSign * 0.022;
        eg.add(base, cap, glowRing);
        headPivot.add(eg);
        return glowRing;
    }
    rig.earGlowL = buildEarModule(-1);
    rig.earGlowR = buildEarModule(1);

    // Sculpted Multi-Strand Gentleman Hairstyle (Obsidian + 24K Gold Highlights)
    var hairGroup = new THREE.Group();
    hairGroup.position.set(0, 0.305, 0.02);
    headPivot.add(hairGroup);

    function addHairStrand(pts, radius, isGold) {
        var curve = new THREE.CatmullRomCurve3(pts.map(function (p) {
            return new THREE.Vector3(p[0], p[1], p[2]);
        }));
        var mat = isGold ? mats.goldBright : mats.hairObsidian;
        var tube = new THREE.Mesh(new THREE.TubeGeometry(curve, 24, radius, 12, false), mat);
        tube.scale.set(1.0, 0.78, 1.08);
        var tipPt = pts[pts.length - 1];
        var tip = new THREE.Mesh(new THREE.ConeGeometry(radius * 0.78, radius * 2.4, 10), mat);
        tip.position.set(tipPt[0] + 0.028, tipPt[1] * 0.78 + 0.008, tipPt[2]);
        tip.rotation.z = -1.15;
        hairGroup.add(tube, tip);
    }

    // Crown volume base
    var crownBase = new THREE.Mesh(new THREE.SphereGeometry(0.24, 20, 14), mats.hairObsidian);
    crownBase.scale.set(1.25, 0.52, 0.82);
    crownBase.position.set(-0.02, 0.035, -0.01);
    hairGroup.add(crownBase);

    // Sweeping pompadour strands from left part sweeping right & upward
    addHairStrand([[-0.26, 0.01, 0.08], [-0.14, 0.13, 0.11], [0.05, 0.17, 0.08], [0.23, 0.11, 0.03]], 0.068, false);
    addHairStrand([[-0.22, 0.03, 0.12], [-0.08, 0.15, 0.14], [0.10, 0.18, 0.11], [0.26, 0.13, 0.05]], 0.034, true); // Gold streak 1
    addHairStrand([[-0.28, 0.00, 0.00], [-0.12, 0.14, 0.02], [0.09, 0.19, 0.00], [0.28, 0.13, -0.02]], 0.072, false);
    addHairStrand([[-0.19, 0.05, 0.05], [-0.03, 0.18, 0.06], [0.15, 0.20, 0.04], [0.30, 0.15, 0.00]], 0.032, true); // Gold streak 2
    addHairStrand([[-0.25, 0.00, -0.09], [-0.08, 0.12, -0.08], [0.11, 0.15, -0.07], [0.25, 0.08, -0.06]], 0.064, false);
    addHairStrand([[0.02, 0.08, 0.10], [0.14, 0.13, 0.08], [0.27, 0.10, 0.04], [0.34, 0.14, 0.02]], 0.042, false);
    addHairStrand([[0.06, 0.06, 0.12], [0.18, 0.10, 0.10], [0.29, 0.08, 0.06]], 0.024, true); // Gold streak 3

    // 3D Gold Monocle on Viewer's Right Eye (+x = +0.188)
    var monocleGroup = new THREE.Group();
    monocleGroup.position.set(0.185, -0.005, 0.196);
    headPivot.add(monocleGroup);
    rig.monocle = monocleGroup;

    var monocleRim = new THREE.Mesh(new THREE.TorusGeometry(0.134, 0.014, 16, 48), mats.gold24k);
    var monocleInnerRim = new THREE.Mesh(new THREE.TorusGeometry(0.123, 0.005, 10, 40), mats.goldBright);
    monocleInnerRim.position.z = 0.006;
    var monocleGlass = new THREE.Mesh(new THREE.CircleGeometry(0.122, 40), mats.monocleLens);
    monocleGlass.position.z = 0.003;
    var monocleLug = new THREE.Mesh(new THREE.TorusGeometry(0.018, 0.0055, 10, 20), mats.goldBright);
    monocleLug.position.set(0.144, -0.015, 0.0);
    monocleGroup.add(monocleRim, monocleInnerRim, monocleGlass, monocleLug);

    var monocleChainAnchor = new THREE.Group();
    monocleChainAnchor.position.set(0.148, -0.024, 0.0);
    monocleGroup.add(monocleChainAnchor);

    // 14-Link 3D Gold Monocle Chain (world-space links updated with spring physics!)
    var CHAIN_LINKS = 14;
    var chainMeshes = [];
    var linkGeo = new THREE.TorusGeometry(0.0145, 0.0044, 8, 16);
    for (var ci = 0; ci < CHAIN_LINKS; ci++) {
        var lm = new THREE.Mesh(linkGeo, (ci % 2 === 0) ? mats.goldBright : mats.gold24k);
        scene.add(lm);
        chainMeshes.push(lm);
    }

    // ========================================================================
    // RETINA VISOR DRAWING (GOLD EYES + EXPRESSIVE GENTLEMAN MUSTACHE + LIPSYNC)
    // ========================================================================
    var faceCur = {
        lidTop: 0.04,
        lidBot: 0.02,
        browTilt: -0.03,
        browY: 0.0,
        mouthCurve: 0.30,
        mouthOpen: 0.03,
        pupilScale: 1.0,
        mustacheLift: 0.08,
        headPitch: 0.0,
        headRoll: 0.0
    };
    var blinkVal = 0.0;
    var nextBlinkMs = performance.now() + 2200;
    var lookCur = { x: 0, y: 0 };
    var lookTarget = { x: 0, y: 0 };
    var nextSaccadeMs = performance.now() + 1800;

    function drawGentlemanMustache(ctx, cx, cy, lift, speechFlex) {
        ctx.save();
        ctx.translate(cx, cy);

        for (var s = -1; s <= 1; s += 2) {
            ctx.save();
            ctx.scale(s, 1);

            var tipY = -6.5 - lift * 12.0 - speechFlex * 4.5;
            var bodyDrop = 14.5 + speechFlex * 3.0;

            // Deep espresso-ebony mustache body
            var mg = ctx.createLinearGradient(0, -10, 74, 16);
            mg.addColorStop(0.0, '#1A130E');
            mg.addColorStop(0.55, '#2B2017');
            mg.addColorStop(1.0, '#16100C');

            ctx.fillStyle = mg;
            ctx.beginPath();
            ctx.moveTo(-1.5, -3.0);
            ctx.bezierCurveTo(14, -13.5, 38, -11.0, 56, 2.0 - lift * 4.0);
            ctx.bezierCurveTo(66, 8.0 - lift * 6.0, 75, 2.0 + tipY * 0.45, 78, tipY);
            ctx.bezierCurveTo(79, tipY + 8.5, 68, 17.5 - lift * 4.0, 50, bodyDrop);
            ctx.bezierCurveTo(30, bodyDrop + 3.0, 12, 7.5, -1.5, 5.2);
            ctx.closePath();
            ctx.fill();

            // Golden-bronze gentleman hair sheen along upper mustache ridge
            ctx.strokeStyle = 'rgba(212, 175, 55, 0.58)';
            ctx.lineWidth = 2.4;
            ctx.lineCap = 'round';
            ctx.beginPath();
            ctx.moveTo(4, -4.2);
            ctx.bezierCurveTo(20, -10.0, 40, -6.5, 62, 2.5 - lift * 4.5);
            ctx.stroke();

            ctx.strokeStyle = 'rgba(245, 214, 138, 0.34)';
            ctx.lineWidth = 1.4;
            ctx.beginPath();
            ctx.moveTo(10, -1.0);
            ctx.bezierCurveTo(28, -4.5, 48, 1.5, 73, tipY + 2.5);
            ctx.stroke();

            ctx.restore();
        }
        ctx.restore();
    }

    function resolveActiveEmotionKey() {
        if (state.emotion && EMOTION_PRESETS[state.emotion] && state.emotion !== 'idle') {
            return state.emotion;
        }
        if (state.mode === 'speaking') return 'speaking';
        if (state.mode === 'listening') return 'listening';
        if (state.mode === 'thinking' || state.mode === 'connecting') return 'thinking';
        return state.emotion && EMOTION_PRESETS[state.emotion] ? state.emotion : 'idle';
    }

    function renderVisorFace(nowMs, dt) {
        var emoKey = resolveActiveEmotionKey();
        var preset = EMOTION_PRESETS[emoKey] || EMOTION_PRESETS.idle;
        var blend = 1.0 - Math.exp(-dt * 9.0);
        for (var k in preset) {
            faceCur[k] = lerp(faceCur[k], preset[k], blend);
        }

        if (nowMs >= nextBlinkMs) {
            blinkVal = 1.0;
            nextBlinkMs = nowMs + 2600 + Math.random() * 3400;
        }
        if (blinkVal > 0) {
            blinkVal = Math.max(0, blinkVal - dt * 8.0);
        }
        var blinkCurve = blinkVal > 0.001 ? Math.sin(blinkVal * Math.PI) : 0;

        if (nowMs >= nextSaccadeMs) {
            if (state.mode === 'listening' || state.mode === 'speaking') {
                lookTarget.x = (Math.random() - 0.5) * 0.28;
                lookTarget.y = (Math.random() - 0.5) * 0.18;
            } else if (emoKey === 'thinking') {
                lookTarget.x = 0.35;
                lookTarget.y = -0.28;
            } else {
                lookTarget.x = (Math.random() - 0.5) * 0.45;
                lookTarget.y = (Math.random() - 0.5) * 0.24;
            }
            nextSaccadeMs = nowMs + 1400 + Math.random() * 2400;
        }
        lookCur.x = lerp(lookCur.x, lookTarget.x, 1.0 - Math.exp(-dt * 6.5));
        lookCur.y = lerp(lookCur.y, lookTarget.y, 1.0 - Math.exp(-dt * 6.5));

        var isSpeakingNow = state.mode === 'speaking' || (state.audioLevel > 0.04 && state.mode !== 'listening');
        var osc = isSpeakingNow
            ? (0.42 + 0.58 * Math.abs(Math.sin(nowMs * 0.022)) * Math.abs(Math.cos(nowMs * 0.014)))
            : 0.0;
        var amp = clamp(state.audioLevel || 0, 0, 1);
        var voiceOpen = isSpeakingNow ? clamp((0.18 + amp * 1.15) * osc, 0.06, 0.94) : 0.0;
        var openAmt = clamp(Math.max(faceCur.mouthOpen, voiceOpen), 0.02, 0.96);

        var lidTop = clamp(faceCur.lidTop + blinkCurve * 0.96, 0, 0.98);
        var lidBot = clamp(faceCur.lidBot, 0, 0.60);
        var gx = lookCur.x * 16.0;
        var gy = lookCur.y * 10.0;

        visorCtx.drawImage(visorBgCanvas, 0, 0);
        visorCtx.save();
        visorCtx.scale(2, 2);

        var eyeY = 104 + gy * 0.65;
        var eyeCenters = [110 + gx * 0.75, 274 + gx * 0.75];
        var rx = 44.0;
        var ry = 44.5;
        var irisSize = 94 * (faceCur.pupilScale || 1.0);

        for (var idx = 0; idx < 2; idx++) {
            var side = idx === 0 ? -1 : 1;
            var ex = eyeCenters[idx];

            visorCtx.fillStyle = '#181005';
            visorCtx.beginPath();
            visorCtx.ellipse(ex, eyeY, rx, ry, 0, 0, Math.PI * 2);
            visorCtx.fill();

            var px = ex + gx * 0.35;
            var py = eyeY + gy * 0.35;
            visorCtx.drawImage(irisSpriteCanvas, px - irisSize * 0.5, py - irisSize * 0.5, irisSize, irisSize);

            if (lidTop > 0.01) {
                visorCtx.fillStyle = '#111217';
                visorCtx.fillRect(ex - rx - 8, eyeY - ry - 12, rx * 2 + 16, ry * 2 * lidTop + 6);
            }
            if (lidBot > 0.01) {
                visorCtx.fillStyle = '#111217';
                var bh = ry * 1.6 * lidBot;
                visorCtx.beginPath();
                visorCtx.ellipse(ex, eyeY + ry + 8 - bh * 0.45, rx + 10, ry * 0.85, 0, Math.PI, Math.PI * 2);
                visorCtx.fill();
            }

            // Crisp eye socket mask & warm gold upper lid line
            visorCtx.strokeStyle = '#101116';
            visorCtx.lineWidth = 9.0;
            visorCtx.beginPath();
            visorCtx.ellipse(ex, eyeY, rx + 4.5, ry + 4.5, 0, 0, Math.PI * 2);
            visorCtx.stroke();

            visorCtx.strokeStyle = '#D4AF37';
            visorCtx.lineWidth = 3.8;
            visorCtx.lineCap = 'round';
            visorCtx.beginPath();
            visorCtx.ellipse(ex, eyeY, rx + 0.5, ry * Math.max(0.08, 1 - lidTop * 1.08), 0, Math.PI * 1.10, Math.PI * 1.90);
            visorCtx.stroke();

            // Subtle warm amber cheek glow streaks (matching falcon_avatar.png)
            visorCtx.strokeStyle = 'rgba(212, 160, 68, 0.34)';
            visorCtx.lineWidth = 2.0;
            for (var cs = -1; cs <= 1; cs++) {
                var sx = ex + side * 12 + cs * 7;
                var sy = eyeY + 50;
                visorCtx.beginPath();
                visorCtx.moveTo(sx - 2.5, sy + 3.5);
                visorCtx.lineTo(sx + 2.5, sy - 3.5);
                visorCtx.stroke();
            }

            // Expressive Gentleman Eyebrows
            var by = 48 + gy * 0.40 + faceCur.browY;
            var bTilt = faceCur.browTilt * side;
            visorCtx.save();
            visorCtx.translate(ex, by);
            visorCtx.rotate(bTilt);
            visorCtx.strokeStyle = '#2B2118';
            visorCtx.lineWidth = 7.2;
            visorCtx.lineCap = 'round';
            visorCtx.beginPath();
            visorCtx.moveTo(-27, 4.0);
            visorCtx.quadraticCurveTo(0, -7.5, 27, 3.5);
            visorCtx.stroke();

            visorCtx.strokeStyle = 'rgba(212, 175, 55, 0.58)';
            visorCtx.lineWidth = 2.2;
            visorCtx.beginPath();
            visorCtx.moveTo(-23, 2.2);
            visorCtx.quadraticCurveTo(0, -8.5, 23, 1.8);
            visorCtx.stroke();
            visorCtx.restore();
        }

        // Lip-Sync Mouth (warm ivory-gold aperture under mustache)
        var mx = 192 + gx * 0.45;
        var my = 177 + gy * 0.45;
        var mw = 22 + openAmt * 14 + Math.max(0, faceCur.mouthCurve) * 8;
        var mh = 3.5 + openAmt * 24;

        visorCtx.save();
        visorCtx.beginPath();
        visorCtx.moveTo(mx - mw, my - 2);
        visorCtx.quadraticCurveTo(mx, my - 2 + faceCur.mouthCurve * 6, mx + mw, my - 2);
        visorCtx.quadraticCurveTo(mx + mw * 0.78, my + mh, mx, my + mh);
        visorCtx.quadraticCurveTo(mx - mw * 0.78, my + mh, mx - mw, my - 2);
        visorCtx.closePath();

        var mouthGrad = visorCtx.createLinearGradient(mx, my - 2, mx, my + mh);
        mouthGrad.addColorStop(0, '#FFF5D6');
        mouthGrad.addColorStop(0.55, '#E5C07B');
        mouthGrad.addColorStop(1, '#8C6223');
        visorCtx.fillStyle = mouthGrad;
        visorCtx.fill();
        visorCtx.restore();

        // Sculpted Gentleman Mustache right above mouth
        drawGentlemanMustache(
            visorCtx,
            192 + gx * 0.48,
            165 + gy * 0.45 - openAmt * 2.5,
            faceCur.mustacheLift + (isSpeakingNow ? amp * 0.18 : 0),
            isSpeakingNow ? openAmt * 0.65 : 0
        );

        // Subtle Camera-Eyes HUD corner brackets when Vision is active
        if (state.cameraActive) {
            var pulse = 0.55 + 0.35 * Math.sin(nowMs * 0.005);
            visorCtx.strokeStyle = 'rgba(212, 175, 55, ' + pulse.toFixed(2) + ')';
            visorCtx.lineWidth = 2.2;
            var pad = 18, len = 14;
            // TL
            visorCtx.beginPath(); visorCtx.moveTo(pad, pad + len); visorCtx.lineTo(pad, pad); visorCtx.lineTo(pad + len, pad); visorCtx.stroke();
            // TR
            visorCtx.beginPath(); visorCtx.moveTo(384 - pad - len, pad); visorCtx.lineTo(384 - pad, pad); visorCtx.lineTo(384 - pad, pad + len); visorCtx.stroke();
            // BL
            visorCtx.beginPath(); visorCtx.moveTo(pad, 240 - pad - len); visorCtx.lineTo(pad, 240 - pad); visorCtx.lineTo(pad + len, 240 - pad); visorCtx.stroke();
            // BR
            visorCtx.beginPath(); visorCtx.moveTo(384 - pad - len, 240 - pad); visorCtx.lineTo(384 - pad, 240 - pad); visorCtx.lineTo(384 - pad, 240 - pad - len); visorCtx.stroke();
        }

        visorCtx.restore();
        visorTex.needsUpdate = true;
    }

    // ========================================================================
    // INTERACTIVE TOUCH ROTATION + SPRING PHYSICS MONOCLE CHAIN
    // ========================================================================
    var dragActive = false;
    var lastPtrX = 0;
    var lastPtrY = 0;
    var userYaw = 0.0;
    var userPitch = 0.0;
    var userYawVel = 0.0;

    function onDown(x, y) {
        dragActive = true;
        lastPtrX = x;
        lastPtrY = y;
    }
    function onMove(x, y) {
        if (!dragActive) return;
        var dx = x - lastPtrX;
        var dy = y - lastPtrY;
        lastPtrX = x;
        lastPtrY = y;
        var deltaYaw = dx * 0.0075;
        userYaw = clamp(userYaw + deltaYaw, -0.85, 0.85);
        userPitch = clamp(userPitch + dy * 0.0035, -0.22, 0.22);
        userYawVel = deltaYaw * 16.0;
    }
    function onUp() {
        dragActive = false;
    }

    canvas.addEventListener('pointerdown', function (e) { onDown(e.clientX, e.clientY); });
    window.addEventListener('pointermove', function (e) { onMove(e.clientX, e.clientY); });
    window.addEventListener('pointerup', onUp);
    window.addEventListener('pointercancel', onUp);

    var chainSwingX = 0.0, chainVelX = 0.0;
    var chainSwingZ = 0.0, chainVelZ = 0.0;
    var prevHeadWorldX = 0.0, prevHeadWorldZ = 0.0;
    var tmpStart = new THREE.Vector3();
    var tmpEnd = new THREE.Vector3();
    var tmpCur = new THREE.Vector3();
    var tmpNext = new THREE.Vector3();

    function updateMonocleChain(dt) {
        monocleChainAnchor.getWorldPosition(tmpStart);
        lapelChainAnchor.getWorldPosition(tmpEnd);

        var vx = (tmpStart.x - prevHeadWorldX) / Math.max(0.004, dt);
        var vz = (tmpStart.z - prevHeadWorldZ) / Math.max(0.004, dt);
        prevHeadWorldX = tmpStart.x;
        prevHeadWorldZ = tmpStart.z;

        // Damped spring pendulum driven by head motion inertia
        chainVelX += (-vx * 0.14 - chainSwingX * 26.0 - chainVelX * 5.2) * dt;
        chainVelZ += (-vz * 0.14 - chainSwingZ * 26.0 - chainVelZ * 5.2) * dt;
        chainSwingX = clamp(chainSwingX + chainVelX * dt, -0.09, 0.09);
        chainSwingZ = clamp(chainSwingZ + chainVelZ * dt, -0.08, 0.08);

        for (var i = 0; i < CHAIN_LINKS; i++) {
            var t = (i + 0.5) / CHAIN_LINKS;
            var sag = Math.sin(t * Math.PI);
            tmpCur.set(
                lerp(tmpStart.x, tmpEnd.x, t) + sag * (0.042 + chainSwingX),
                lerp(tmpStart.y, tmpEnd.y, t) - sag * 0.085,
                lerp(tmpStart.z, tmpEnd.z, t) + sag * (0.036 + chainSwingZ)
            );
            var t2 = Math.min(1.0, (i + 1.5) / CHAIN_LINKS);
            var sag2 = Math.sin(t2 * Math.PI);
            tmpNext.set(
                lerp(tmpStart.x, tmpEnd.x, t2) + sag2 * (0.042 + chainSwingX),
                lerp(tmpStart.y, tmpEnd.y, t2) - sag2 * 0.085,
                lerp(tmpStart.z, tmpEnd.z, t2) + sag2 * (0.036 + chainSwingZ)
            );
            var mesh = chainMeshes[i];
            mesh.position.copy(tmpCur);
            mesh.lookAt(tmpNext);
            if (i % 2 === 1) {
                mesh.rotateZ(Math.PI * 0.5);
            }
        }
    }

    // ========================================================================
    // POSE & THEME UPDATE + 60 FPS ANIMATION LOOP
    // ========================================================================
    var appliedDark = true;
    var bgDarkColor = new THREE.Color(0x090B10);
    var bgLightColor = new THREE.Color(0xF8F6F1);

    function applyThemeIfNeeded() {
        var wantDark = !!state.isDark;
        if (wantDark === appliedDark) return;
        appliedDark = wantDark;
        scene.background.copy(wantDark ? bgDarkColor : bgLightColor);
        floorMat.map = wantDark ? floorTexDark : floorTexLight;
        floorMat.needsUpdate = true;
        haloMat.map = wantDark ? haloTexDark : haloTexLight;
        haloMat.needsUpdate = true;
        var targetSuitMat = wantDark ? mats.suitDark : mats.suitLight;
        for (var i = 0; i < suitMeshes.length; i++) {
            suitMeshes[i].material = targetSuitMat;
        }
    }

    function resize() {
        var w = Math.max(240, window.innerWidth || 360);
        var h = Math.max(320, window.innerHeight || 640);
        var dpr = Math.min(window.devicePixelRatio || 1, 2.0);
        renderer.setPixelRatio(dpr);
        renderer.setSize(w, h, false);
        camera.aspect = w / h;
        // Adjust camera distance smoothly for portrait vs landscape aspect
        var aspectBoost = camera.aspect < 0.62 ? (0.62 - camera.aspect) * 1.65 : 0.0;
        camera.position.set(0, 1.14, 2.28 + aspectBoost);
        camera.lookAt(0, 1.07, 0);
        camera.updateProjectionMatrix();
    }
    window.addEventListener('resize', resize);
    resize();

    var lastFrameMs = performance.now();
    var smoothAmp = 0.0;

    function animate(nowMs) {
        requestAnimationFrame(animate);
        var dt = clamp((nowMs - lastFrameMs) * 0.001, 0.001, 0.05);
        lastFrameMs = nowMs;
        var tSec = nowMs * 0.001;

        applyThemeIfNeeded();

        if (!dragActive) {
            userYaw = lerp(userYaw, 0.0, 1.0 - Math.exp(-dt * 4.8));
            userPitch = lerp(userPitch, 0.0, 1.0 - Math.exp(-dt * 5.2));
            userYawVel *= Math.exp(-dt * 8.0);
        }

        smoothAmp = lerp(smoothAmp, clamp(state.audioLevel || 0, 0, 1), 1.0 - Math.exp(-dt * 14.0));
        var emoKey = resolveActiveEmotionKey();

        // Sovereign breathing & posture
        var breath = Math.sin(tSec * 1.9) * 0.012;
        pelvis.position.y = 0.56 + breath;
        root.rotation.y = userYaw + Math.sin(tSec * 0.65) * 0.045;
        torso.rotation.x = userPitch * 0.45 + (state.mode === 'listening' ? 0.06 : 0.0);

        // Head attitude
        var speakNod = (state.mode === 'speaking')
            ? (Math.sin(tSec * 6.8) * 0.035 * (0.3 + smoothAmp))
            : 0.0;
        var targetHeadPitch = (faceCur.headPitch || 0) + userPitch * 0.55 + speakNod;
        var targetHeadRoll = (faceCur.headRoll || 0) + Math.sin(tSec * 0.9) * 0.018;
        var targetHeadYaw = (emoKey === 'thinking' ? 0.08 : 0.0) + Math.sin(tSec * 0.75) * 0.04;

        headPivot.rotation.x = lerp(headPivot.rotation.x, targetHeadPitch, 1.0 - Math.exp(-dt * 7.5));
        headPivot.rotation.z = lerp(headPivot.rotation.z, targetHeadRoll, 1.0 - Math.exp(-dt * 7.5));
        headPivot.rotation.y = lerp(headPivot.rotation.y, targetHeadYaw, 1.0 - Math.exp(-dt * 7.5));

        // Ear acoustic pulse
        var earPulse = (state.mode === 'listening' || state.cameraActive)
            ? (0.72 + 0.28 * Math.sin(tSec * 5.5))
            : (state.mode === 'speaking' ? (0.65 + 0.35 * smoothAmp) : 0.52);
        mats.earGlow.color.setRGB(0.90 * earPulse, 0.74 * earPulse, 0.38 * earPulse);

        // Articulated Co-Founder Arm Gestures
        var rArmX = 0.08, rArmZ = -0.14, rElbX = -0.34, rElbZ = 0.08;
        var lArmX = 0.08, lArmZ = 0.14, lElbX = -0.30, lElbZ = -0.06;

        if (emoKey === 'thinking') {
            // Right hand lifts gracefully toward monocle / chin (signature Falcon pose!)
            rArmX = -0.92;
            rArmZ = -0.26;
            rElbX = -1.35;
            rElbZ = 0.42;
        } else if (state.mode === 'speaking') {
            var gWave = Math.sin(tSec * 4.4) * (0.16 + smoothAmp * 0.28);
            var gWave2 = Math.cos(tSec * 3.6) * (0.12 + smoothAmp * 0.22);
            rArmX = -0.32 - gWave;
            rArmZ = -0.18;
            rElbX = -0.82 - Math.abs(gWave) * 0.4;
            lArmX = -0.24 + gWave2 * 0.6;
            lArmZ = 0.18;
            lElbX = -0.68 - Math.abs(gWave2) * 0.35;
        } else if (state.mode === 'listening') {
            rArmX = -0.18;
            rElbX = -0.52;
            lArmX = -0.18;
            lElbX = -0.52;
        } else if (emoKey === 'proud' || emoKey === 'happy') {
            rArmX = -0.36;
            rArmZ = -0.22;
            rElbX = -0.95;
            lArmX = -0.20;
            lArmZ = 0.20;
            lElbX = -0.55;
        } else {
            // Sovereign idle breathing in arms
            rArmX = 0.06 + breath * 1.4;
            lArmX = 0.06 + breath * 1.4;
        }

        var armBlend = 1.0 - Math.exp(-dt * 6.5);
        rig.armR.armPivot.rotation.x = lerp(rig.armR.armPivot.rotation.x, rArmX, armBlend);
        rig.armR.armPivot.rotation.z = lerp(rig.armR.armPivot.rotation.z, rArmZ, armBlend);
        rig.armR.elbowPivot.rotation.x = lerp(rig.armR.elbowPivot.rotation.x, rElbX, armBlend);
        rig.armR.elbowPivot.rotation.z = lerp(rig.armR.elbowPivot.rotation.z, rElbZ, armBlend);

        rig.armL.armPivot.rotation.x = lerp(rig.armL.armPivot.rotation.x, lArmX, armBlend);
        rig.armL.armPivot.rotation.z = lerp(rig.armL.armPivot.rotation.z, lArmZ, armBlend);
        rig.armL.elbowPivot.rotation.x = lerp(rig.armL.elbowPivot.rotation.x, lElbX, armBlend);
        rig.armL.elbowPivot.rotation.z = lerp(rig.armL.elbowPivot.rotation.z, lElbZ, armBlend);

        renderVisorFace(nowMs, dt);
        scene.updateMatrixWorld(true);
        updateMonocleChain(dt);

        renderer.render(scene, camera);
    }

    window.FalconScene = {
        updateState: function (next) {
            if (!next || typeof next !== 'object') return;
            if (typeof next.mode === 'string') state.mode = next.mode;
            if (typeof next.audioLevel === 'number' && !isNaN(next.audioLevel)) {
                state.audioLevel = clamp(next.audioLevel, 0, 1);
            }
            if (typeof next.emotion === 'string') state.emotion = next.emotion;
            if (typeof next.isDark === 'boolean') state.isDark = next.isDark;
            if (typeof next.cameraActive === 'boolean') state.cameraActive = next.cameraActive;
        },
        getState: function () {
            return {
                mode: state.mode,
                audioLevel: state.audioLevel,
                emotion: state.emotion,
                isDark: state.isDark,
                cameraActive: state.cameraActive
            };
        }
    };

    renderVisorFace(performance.now(), 0.016);
    requestAnimationFrame(animate);
})();
