(function () {
    'use strict';

    function clamp(v, lo, hi) { return Math.max(lo, Math.min(hi, v)); }
    function lerp(a, b, t) { return a + (b - a) * t; }

    function hexToRgb(hex) {
        var clean = String(hex).replace('#', '');
        var n = parseInt(clean, 16);
        return [(n >> 16) & 255, (n >> 8) & 255, n & 255];
    }

    var maxAnisotropy = 4;

    function applySrgb(tex) {
        if (!tex || typeof THREE === 'undefined') return tex;
        if (THREE.SRGBColorSpace) {
            tex.colorSpace = THREE.SRGBColorSpace;
        } else if (THREE.sRGBEncoding) {
            tex.encoding = THREE.sRGBEncoding;
        }
        return tex;
    }

    function configureStaticTexture(tex) {
        if (!tex || typeof THREE === 'undefined') return tex;
        tex.generateMipmaps = true;
        tex.minFilter = THREE.LinearMipmapLinearFilter;
        tex.magFilter = THREE.LinearFilter;
        tex.anisotropy = maxAnisotropy;
        return applySrgb(tex);
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
                    var skyBounce = 0.38 + 0.32 * (ny * 0.5 + 0.5);
                    var horizon = Math.exp(-Math.pow((ny + 0.14) * 5.0, 2)) * (specPower >= 22 ? 0.13 : 0.04);
                    var hemi = skyBounce + 0.50 * ndl1 + 0.22 * ndl2 + horizon;

                    var ndh1 = Math.max(0, nx * hx1 + ny * hy1 + nz * hz1);
                    var ndh2 = Math.max(0, nx * hx2 + ny * hy2 + nz * hz2);
                    var spBroad = Math.pow(ndh1, Math.max(6, specPower * 0.45)) * 0.28;
                    var spCrisp = Math.pow(ndh1, specPower * 1.35) * 0.82 + Math.pow(ndh2, specPower * 0.9) * 0.20;
                    var sp = spBroad + spCrisp;
                    var fresnel = Math.pow(1.0 - nz, 2.4) * rimStrength;

                    r = base[0] * hemi + spec[0] * sp + rim[0] * fresnel;
                    g = base[1] * hemi + spec[1] * sp + rim[1] * fresnel;
                    b = base[2] * hemi + spec[2] * sp + rim[2] * fresnel;
                } else {
                    var len = Math.sqrt(r2);
                    var uy = ny / len;
                    var h2 = 0.36 + 0.26 * (uy * 0.5 + 0.5);
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
        c.width = 1024;
        c.height = 512;
        var ctx = c.getContext('2d');
        ctx.clearRect(0, 0, c.width, c.height);
        ctx.save();
        ctx.scale(1024 / 768, 512 / 320);

        var goldGrad = ctx.createLinearGradient(160, 60, 608, 260);
        goldGrad.addColorStop(0.0, '#E8CA82');
        goldGrad.addColorStop(0.25, '#FFF8E0');
        goldGrad.addColorStop(0.55, '#D4A843');
        goldGrad.addColorStop(0.85, '#FFF0C0');
        goldGrad.addColorStop(1.0, '#A88038');

        var goldStroke = ctx.createLinearGradient(120, 40, 648, 280);
        goldStroke.addColorStop(0.0, 'rgba(243, 213, 135, 0.88)');
        goldStroke.addColorStop(0.5, 'rgba(250, 234, 186, 0.94)');
        goldStroke.addColorStop(1.0, 'rgba(197, 160, 89, 0.85)');

        // 1. Matte Obsidian-Schildplatte & feiner goldener Wappenschild-Rahmen (verhindert schwarzes Alpha-Ausbluten in Mipmaps)
        ctx.save();
        var shieldBg = ctx.createRadialGradient(384, 166, 24, 384, 168, 290);
        shieldBg.addColorStop(0.0, 'rgba(21, 25, 35, 0.94)');
        shieldBg.addColorStop(0.75, 'rgba(16, 19, 27, 0.92)');
        shieldBg.addColorStop(1.0, 'rgba(13, 16, 23, 0.88)');
        ctx.fillStyle = shieldBg;
        ctx.shadowColor = 'rgba(0, 0, 0, 0.85)';
        ctx.shadowBlur = 10;
        ctx.shadowOffsetY = 4;

        ctx.beginPath();
        ctx.moveTo(110, 58);
        ctx.quadraticCurveTo(384, 38, 658, 58);
        ctx.lineTo(658, 210);
        ctx.quadraticCurveTo(640, 272, 384, 296);
        ctx.quadraticCurveTo(128, 272, 110, 210);
        ctx.closePath();
        ctx.fill();

        ctx.shadowBlur = 0;
        ctx.shadowOffsetY = 0;
        ctx.strokeStyle = goldStroke;
        ctx.lineWidth = 4.6;
        ctx.stroke();

        ctx.strokeStyle = 'rgba(212, 168, 67, 0.52)';
        ctx.lineWidth = 2.8;
        ctx.beginPath();
        ctx.moveTo(123, 69);
        ctx.quadraticCurveTo(384, 51, 645, 69);
        ctx.lineTo(645, 203);
        ctx.quadraticCurveTo(629, 259, 384, 281);
        ctx.quadraticCurveTo(139, 259, 123, 203);
        ctx.closePath();
        ctx.stroke();
        ctx.restore();

        // 2. Glänzende Stern-Facetten
        function drawFacetStar(cx, cy, rOuter, rInner) {
            ctx.save();
            ctx.translate(cx, cy);
            var starGrad = ctx.createRadialGradient(0, 0, 1, 0, 0, rOuter);
            starGrad.addColorStop(0.0, '#FFFDF4');
            starGrad.addColorStop(0.35, '#FFF2C6');
            starGrad.addColorStop(0.70, '#D4A843');
            starGrad.addColorStop(1.0, '#8E6D2F');
            ctx.fillStyle = starGrad;

            ctx.beginPath();
            for (var si = 0; si < 8; si++) {
                var ang = (si * Math.PI) / 4;
                var rad = (si % 2 === 0) ? rOuter : rInner;
                var x = Math.cos(ang) * rad;
                var y = Math.sin(ang) * rad;
                if (si === 0) ctx.moveTo(x, y); else ctx.lineTo(x, y);
            }
            ctx.closePath();
            ctx.fill();

            ctx.strokeStyle = 'rgba(245, 224, 168, 0.65)';
            ctx.lineWidth = 2.2;
            ctx.lineCap = 'round';
            ctx.beginPath();
            ctx.moveTo(-rOuter * 1.25, 0);
            ctx.lineTo(rOuter * 1.25, 0);
            ctx.moveTo(0, -rOuter * 1.25);
            ctx.lineTo(0, rOuter * 1.25);
            ctx.stroke();
            ctx.restore();
        }

        drawFacetStar(384, 52, 14, 5.5);
        drawFacetStar(175, 140, 11, 4.2);
        drawFacetStar(593, 140, 11, 4.2);
        drawFacetStar(228, 226, 8, 3.2);
        drawFacetStar(540, 226, 8, 3.2);

        // 3. Typografie FUNKIRA
        ctx.save();
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.shadowColor = 'rgba(0, 0, 0, 0.90)';
        ctx.shadowBlur = 10;
        ctx.shadowOffsetY = 3;

        ctx.fillStyle = goldGrad;
        ctx.font = '900 68px sans-serif';
        ctx.fillText('FUNKIRA', 384, 140);

        ctx.strokeStyle = 'rgba(255, 248, 220, 0.58)';
        ctx.lineWidth = 2.2;
        ctx.strokeText('FUNKIRA', 384, 140);

        // 4. Typografie Stabschefin
        ctx.font = '700 42px Georgia, serif';
        ctx.fillStyle = '#F3D587';
        ctx.fillText('Stabschefin', 384, 226);

        ctx.strokeStyle = 'rgba(255, 244, 206, 0.48)';
        ctx.lineWidth = 1.8;
        ctx.strokeText('Stabschefin', 384, 226);
        ctx.restore();

        // 5. Flimmerfreie, vertikal weichgezeichnete Matte-Gold-Trennlinie (kein harter 1px-Kontrast bei Atembewegung!)
        ctx.save();
        var ruleY = 183;
        var softBandV = ctx.createLinearGradient(0, ruleY - 6.0, 0, ruleY + 6.0);
        softBandV.addColorStop(0.0, 'rgba(197, 160, 89, 0.0)');
        softBandV.addColorStop(0.26, 'rgba(197, 160, 89, 0.34)');
        softBandV.addColorStop(0.50, 'rgba(232, 198, 118, 0.86)');
        softBandV.addColorStop(0.74, 'rgba(197, 160, 89, 0.34)');
        softBandV.addColorStop(1.0, 'rgba(197, 160, 89, 0.0)');
        ctx.fillStyle = softBandV;
        ctx.beginPath();
        ctx.moveTo(198, ruleY);
        ctx.quadraticCurveTo(280, ruleY - 6.0, 384, ruleY - 6.0);
        ctx.quadraticCurveTo(488, ruleY - 6.0, 570, ruleY);
        ctx.quadraticCurveTo(488, ruleY + 6.0, 384, ruleY + 6.0);
        ctx.quadraticCurveTo(280, ruleY + 6.0, 198, ruleY);
        ctx.closePath();
        ctx.fill();

        var coreGradH = ctx.createLinearGradient(206, ruleY, 562, ruleY);
        coreGradH.addColorStop(0.0, 'rgba(197, 160, 89, 0.0)');
        coreGradH.addColorStop(0.18, 'rgba(212, 168, 67, 0.82)');
        coreGradH.addColorStop(0.50, 'rgba(243, 213, 135, 0.96)');
        coreGradH.addColorStop(0.82, 'rgba(212, 168, 67, 0.82)');
        coreGradH.addColorStop(1.0, 'rgba(197, 160, 89, 0.0)');
        ctx.fillStyle = coreGradH;
        ctx.beginPath();
        ctx.moveTo(206, ruleY);
        ctx.quadraticCurveTo(290, ruleY - 2.6, 384, ruleY - 2.6);
        ctx.quadraticCurveTo(478, ruleY - 2.6, 562, ruleY);
        ctx.quadraticCurveTo(478, ruleY + 2.6, 384, ruleY + 2.6);
        ctx.quadraticCurveTo(290, ruleY + 2.6, 206, ruleY);
        ctx.closePath();
        ctx.fill();

        // Zentraler matter Gold-Rautenakzent auf der Trennlinie
        ctx.fillStyle = '#F3D587';
        ctx.beginPath();
        ctx.moveTo(384, ruleY - 5.2);
        ctx.lineTo(391, ruleY);
        ctx.lineTo(384, ruleY + 5.2);
        ctx.lineTo(377, ruleY);
        ctx.closePath();
        ctx.fill();
        ctx.restore();

        ctx.restore();
        return configureStaticTexture(new THREE.CanvasTexture(c));
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

        ctx.strokeStyle = '#D4AF37';
        ctx.lineWidth = 13;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        ctx.beginPath();
        ctx.moveTo(82, 148);
        ctx.lineTo(128, 182);
        ctx.lineTo(174, 148);
        ctx.stroke();

        return configureStaticTexture(new THREE.CanvasTexture(c));
    }

    function makeMonocleLensTexture() {
        var c = document.createElement('canvas');
        c.width = 256;
        c.height = 256;
        var ctx = c.getContext('2d');
        var rg = ctx.createRadialGradient(128, 128, 18, 128, 128, 124);
        rg.addColorStop(0, 'rgba(255, 248, 225, 0.04)');
        rg.addColorStop(0.78, 'rgba(212, 175, 55, 0.10)');
        rg.addColorStop(1, 'rgba(255, 238, 188, 0.34)');
        ctx.fillStyle = rg;
        ctx.beginPath();
        ctx.arc(128, 128, 124, 0, Math.PI * 2);
        ctx.fill();

        ctx.save();
        ctx.beginPath();
        ctx.arc(128, 128, 120, 0, Math.PI * 2);
        ctx.clip();
        var glint = ctx.createLinearGradient(25, 25, 200, 200);
        glint.addColorStop(0.0, 'rgba(255,255,255,0)');
        glint.addColorStop(0.24, 'rgba(255,252,240,0.32)');
        glint.addColorStop(0.32, 'rgba(255,255,255,0.04)');
        glint.addColorStop(1.0, 'rgba(255,255,255,0)');
        ctx.fillStyle = glint;
        ctx.fillRect(0, 0, 256, 256);
        ctx.restore();

        return configureStaticTexture(new THREE.CanvasTexture(c));
    }

    function makeDarkPenthouseFloorTexture() {
        var c = document.createElement('canvas');
        c.width = 512;
        c.height = 512;
        var ctx = c.getContext('2d');
        ctx.clearRect(0, 0, 512, 512);

        var g = ctx.createRadialGradient(256, 256, 12, 256, 256, 248);
        g.addColorStop(0.0, 'rgba(0, 0, 0, 0.85)');
        g.addColorStop(0.36, 'rgba(24, 19, 12, 0.62)');
        g.addColorStop(0.70, 'rgba(197, 160, 89, 0.15)');
        g.addColorStop(1.0, 'rgba(8, 10, 16, 0.0)');
        ctx.fillStyle = g;
        ctx.beginPath();
        ctx.arc(256, 256, 248, 0, Math.PI * 2);
        ctx.fill();

        ctx.strokeStyle = 'rgba(212, 175, 55, 0.54)';
        ctx.lineWidth = 4.2;
        ctx.beginPath();
        ctx.arc(256, 256, 190, 0, Math.PI * 2);
        ctx.stroke();

        ctx.strokeStyle = 'rgba(197, 160, 89, 0.28)';
        ctx.lineWidth = 3.0;
        ctx.beginPath();
        ctx.arc(256, 256, 222, 0, Math.PI * 2);
        ctx.stroke();

        return configureStaticTexture(new THREE.CanvasTexture(c));
    }

    function makeDarkStudioBackdropTexture() {
        var c = document.createElement('canvas');
        c.width = 1024;
        c.height = 1024;
        var ctx = c.getContext('2d');
        ctx.clearRect(0, 0, 1024, 1024);
        ctx.save();
        ctx.scale(1024 / 768, 1024 / 768);

        // Warm golden architectural rim-glow & subtle studio bokeh
        var mainHalo = ctx.createRadialGradient(384, 350, 20, 384, 360, 370);
        mainHalo.addColorStop(0.0, 'rgba(212, 175, 55, 0.28)');
        mainHalo.addColorStop(0.38, 'rgba(197, 160, 89, 0.12)');
        mainHalo.addColorStop(0.72, 'rgba(120, 92, 42, 0.04)');
        mainHalo.addColorStop(1.0, 'rgba(8, 10, 16, 0.0)');
        ctx.fillStyle = mainHalo;
        ctx.fillRect(0, 0, 768, 768);

        // Subtle warm penthouse bokeh orbs
        var bokehSpots = [
            [185, 220, 56, 0.10],
            [595, 205, 68, 0.09],
            [140, 490, 48, 0.07],
            [620, 470, 52, 0.08],
            [384, 145, 42, 0.08]
        ];
        for (var i = 0; i < bokehSpots.length; i++) {
            var b = bokehSpots[i];
            var bg = ctx.createRadialGradient(b[0], b[1], 2, b[0], b[1], b[2]);
            bg.addColorStop(0.0, 'rgba(255, 232, 172, ' + b[3] + ')');
            bg.addColorStop(0.65, 'rgba(197, 160, 89, ' + (b[3] * 0.45).toFixed(3) + ')');
            bg.addColorStop(1.0, 'rgba(197, 160, 89, 0)');
            ctx.fillStyle = bg;
            ctx.beginPath();
            ctx.arc(b[0], b[1], b[2], 0, Math.PI * 2);
            ctx.fill();
        }

        // Subtle golden arch lines (broadened for flicker-free mipmapping)
        ctx.strokeStyle = 'rgba(197, 160, 89, 0.14)';
        ctx.lineWidth = 3.8;
        ctx.beginPath();
        ctx.arc(384, 390, 290, Math.PI * 1.12, Math.PI * 1.88);
        ctx.stroke();

        ctx.restore();
        return configureStaticTexture(new THREE.CanvasTexture(c));
    }

    function makeSparkSpriteTexture() {
        var c = document.createElement('canvas');
        c.width = 64;
        c.height = 64;
        var ctx = c.getContext('2d');
        var g = ctx.createRadialGradient(32, 32, 1, 32, 32, 30);
        g.addColorStop(0.0, 'rgba(255, 248, 215, 1.0)');
        g.addColorStop(0.35, 'rgba(229, 192, 123, 0.72)');
        g.addColorStop(1.0, 'rgba(197, 160, 89, 0.0)');
        ctx.fillStyle = g;
        ctx.fillRect(0, 0, 64, 64);
        var tex = new THREE.CanvasTexture(c);
        tex.minFilter = THREE.LinearFilter;
        tex.magFilter = THREE.LinearFilter;
        return applySrgb(tex);
    }

    var EMOTION_PRESETS = {
        idle:      { lidTop: 0.02, lidBot: 0.02, browTilt: -0.04, browY: 0.0,  mouthCurve: 0.34, mouthOpen: 0.04, pupilScale: 1.02, mustacheLift: 0.10, headPitch: 0.00,  headRoll: 0.00  },
        listening: { lidTop: 0.00, lidBot: 0.05, browTilt: -0.11, browY: -3.5, mouthCurve: 0.28, mouthOpen: 0.06, pupilScale: 1.08, mustacheLift: 0.15, headPitch: 0.05,  headRoll: 0.03  },
        thinking:  { lidTop: 0.10, lidBot: 0.10, browTilt: 0.08,  browY: -1.0, mouthCurve: 0.22, mouthOpen: 0.03, pupilScale: 1.00, mustacheLift: 0.12, headPitch: 0.07,  headRoll: 0.02  },
        speaking:  { lidTop: 0.02, lidBot: 0.06, browTilt: -0.07, browY: -2.0, mouthCurve: 0.42, mouthOpen: 0.30, pupilScale: 1.04, mustacheLift: 0.22, headPitch: -0.01, headRoll: 0.02  },
        happy:     { lidTop: 0.06, lidBot: 0.24, browTilt: -0.14, browY: -3.8, mouthCurve: 0.80, mouthOpen: 0.24, pupilScale: 1.08, mustacheLift: 0.38, headPitch: -0.02, headRoll: 0.04  },
        proud:     { lidTop: 0.10, lidBot: 0.20, browTilt: -0.08, browY: -2.5, mouthCurve: 0.68, mouthOpen: 0.14, pupilScale: 1.04, mustacheLift: 0.34, headPitch: -0.04, headRoll: -0.03 },
        focused:   { lidTop: 0.14, lidBot: 0.12, browTilt: 0.16,  browY: 1.8,  mouthCurve: 0.16, mouthOpen: 0.04, pupilScale: 0.96, mustacheLift: 0.08, headPitch: 0.05,  headRoll: 0.00  }
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
    renderer.setClearColor(0x080A10, 1.0);
    maxAnisotropy = Math.min(16, Math.max(1, (renderer.capabilities && renderer.capabilities.getMaxAnisotropy) ? renderer.capabilities.getMaxAnisotropy() : 4));

    var scene = new THREE.Scene();
    // Always deep velvet-obsidian Black-Gold Studio Stage (#080A10), never white!
    scene.background = new THREE.Color(0x080A10);

    var camera = new THREE.PerspectiveCamera(38, 9 / 20, 0.12, 18);
    var userZoom = 1.0;

    // Rose-Gold, 24K Gold & Obsidian Materials matching Mini-PC Funkira 1:1 (without 3D house world)
    var mats = {
        gold24k:      new THREE.MeshMatcapMaterial({ matcap: makeStudioMatcap('#E698A8', '#FFFFFF', '#FFE6EE', 34, 0.58) }),
        goldBright:   new THREE.MeshMatcapMaterial({ matcap: makeStudioMatcap('#D9B15F', '#FFFFFF', '#FFF7DA', 40, 0.66) }),
        suitArmor:    new THREE.MeshMatcapMaterial({ matcap: makeStudioMatcap('#15131B', '#685970', '#E698A8', 28, 0.48) }),
        obsidian:     new THREE.MeshMatcapMaterial({ matcap: makeStudioMatcap('#121018', '#4E4359', '#D9B15F', 26, 0.38) }),
        scarfRed:     new THREE.MeshMatcapMaterial({ matcap: makeStudioMatcap('#C83750', '#FF9CB0', '#FFD8E2', 20, 0.34) }),
        jointDark:    new THREE.MeshMatcapMaterial({ matcap: makeStudioMatcap('#0D1017', '#434859', '#B88496', 14, 0.24) }),
        earGlow:      new THREE.MeshBasicMaterial({ color: 0xE8829B }),
        sirenCrystal: new THREE.MeshBasicMaterial({ color: 0xFFE4EE, transparent: true, opacity: 0.34, depthWrite: false })
    };

    var floorDisc = new THREE.Mesh(
        new THREE.PlaneGeometry(2.35, 2.35),
        new THREE.MeshBasicMaterial({
            map: makeDarkPenthouseFloorTexture(),
            transparent: true,
            depthWrite: false,
            polygonOffset: true,
            polygonOffsetFactor: -1,
            polygonOffsetUnits: -1
        })
    );
    floorDisc.rotation.x = -Math.PI / 2;
    floorDisc.position.y = 0.01;
    scene.add(floorDisc);

    var haloPlane = new THREE.Mesh(
        new THREE.PlaneGeometry(3.2, 3.2),
        new THREE.MeshBasicMaterial({ map: makeDarkStudioBackdropTexture(), transparent: true, depthWrite: false })
    );
    haloPlane.position.set(0, 1.20, -0.85);
    scene.add(haloPlane);

    // Subtle floating 24K Gold Sparks in the studio background
    var SPARK_COUNT = 26;
    var sparkPositions = new Float32Array(SPARK_COUNT * 3);
    var sparkSpeeds = new Float32Array(SPARK_COUNT);
    var sparkPhase = new Float32Array(SPARK_COUNT);
    for (var si = 0; si < SPARK_COUNT; si++) {
        sparkPositions[si * 3] = (Math.random() - 0.5) * 2.1;
        sparkPositions[si * 3 + 1] = 0.15 + Math.random() * 2.1;
        sparkPositions[si * 3 + 2] = -0.25 - Math.random() * 0.75;
        sparkSpeeds[si] = 0.045 + Math.random() * 0.065;
        sparkPhase[si] = Math.random() * Math.PI * 2;
    }
    var sparkGeo = new THREE.BufferGeometry();
    sparkGeo.setAttribute('position', new THREE.BufferAttribute(sparkPositions, 3));
    var sparkPoints = new THREE.Points(
        sparkGeo,
        new THREE.PointsMaterial({
            map: makeSparkSpriteTexture(),
            size: 0.048,
            transparent: true,
            opacity: 0.62,
            depthWrite: false,
            blending: THREE.AdditiveBlending
        })
    );
    scene.add(sparkPoints);

    // ========================================================================
    // PRE-BAKE RETINA 768x480 VISOR BACKDROP & 192x192 AMBER-GOLD IRIS SPRITE
    // ========================================================================
    var visorBgCanvas = document.createElement('canvas');
    visorBgCanvas.width = 768;
    visorBgCanvas.height = 480;
    (function bakeVisorBg() {
        var bctx = visorBgCanvas.getContext('2d', { alpha: false });
        bctx.save();
        bctx.scale(2, 2);
        var bgGrad = bctx.createRadialGradient(192, 114, 18, 192, 120, 238);
        bgGrad.addColorStop(0, '#1D1914');
        bgGrad.addColorStop(0.68, '#111319');
        bgGrad.addColorStop(1, '#090A0F');
        bctx.fillStyle = bgGrad;
        bctx.fillRect(0, 0, 384, 240);

        bctx.fillStyle = 'rgba(229, 192, 123, 0.020)';
        for (var y = 16; y < 228; y += 4) {
            bctx.fillRect(10, y, 364, 1);
        }

        // Top 24K Gold accent bar inside visor
        var topGrad = bctx.createLinearGradient(16, 0, 368, 0);
        topGrad.addColorStop(0, '#8E6D2F');
        topGrad.addColorStop(0.35, '#E5C07B');
        topGrad.addColorStop(0.5, '#FFF4CE');
        topGrad.addColorStop(0.65, '#D4AF37');
        topGrad.addColorStop(1, '#8E6D2F');
        bctx.fillStyle = topGrad;
        bctx.fillRect(22, 12, 340, 4.5);

        // Diagonal luxury glass reflection
        bctx.beginPath();
        bctx.moveTo(0, 14);
        bctx.lineTo(168, 14);
        bctx.lineTo(94, 240);
        bctx.lineTo(0, 240);
        bctx.closePath();
        var sheen = bctx.createLinearGradient(0, 14, 150, 240);
        sheen.addColorStop(0, 'rgba(255, 246, 222, 0.11)');
        sheen.addColorStop(0.55, 'rgba(255, 242, 210, 0.038)');
        sheen.addColorStop(1, 'rgba(255, 242, 210, 0.005)');
        bctx.fillStyle = sheen;
        bctx.fill();

        bctx.strokeStyle = 'rgba(212, 175, 55, 0.36)';
        bctx.lineWidth = 1.4;
        bctx.strokeRect(12, 11, 360, 218);
        bctx.restore();
    })();

    var irisSpriteCanvas = document.createElement('canvas');
    irisSpriteCanvas.width = 192;
    irisSpriteCanvas.height = 192;
    (function bakeGoldIris() {
        var ictx = irisSpriteCanvas.getContext('2d');
        ictx.save();
        ictx.scale(2, 2);

        ictx.fillStyle = '#241404';
        ictx.beginPath();
        ictx.ellipse(48, 48, 41.0, 42.2, 0, 0, Math.PI * 2);
        ictx.fill();

        var ig = ictx.createRadialGradient(48, 58, 5, 48, 48, 42);
        ig.addColorStop(0.0, '#FFEBF2');
        ig.addColorStop(0.28, '#F07E9C');
        ig.addColorStop(0.62, '#A8385B');
        ig.addColorStop(0.88, '#4A1528');
        ig.addColorStop(1.0, '#220913');
        ictx.fillStyle = ig;
        ictx.beginPath();
        ictx.ellipse(48, 48, 39.2, 40.6, 0, 0, Math.PI * 2);
        ictx.fill();

        for (var f = 0; f < 28; f++) {
            var ang = (f / 28) * Math.PI * 2;
            var rIn = 18.8 + (f % 2) * 2.0;
            var rOut = 36.8 - (f % 3) * 1.8;
            ictx.strokeStyle = (f % 3 === 0)
                ? 'rgba(255, 228, 238, 0.45)'
                : 'rgba(238, 130, 162, 0.36)';
            ictx.lineWidth = (f % 2 === 0) ? 1.45 : 0.95;
            ictx.beginPath();
            ictx.moveTo(48 + Math.cos(ang) * rIn, 47 + Math.sin(ang) * rIn);
            ictx.lineTo(48 + Math.cos(ang) * rOut, 48 + Math.sin(ang) * rOut);
            ictx.stroke();
        }

        var topShad = ictx.createLinearGradient(0, 7, 0, 40);
        topShad.addColorStop(0, 'rgba(18, 10, 2, 0.54)');
        topShad.addColorStop(1, 'rgba(18, 10, 2, 0)');
        ictx.fillStyle = topShad;
        ictx.beginPath();
        ictx.ellipse(48, 48, 39.2, 40.6, 0, 0, Math.PI * 2);
        ictx.fill();

        ictx.strokeStyle = 'rgba(255, 232, 162, 0.58)';
        ictx.lineWidth = 1.6;
        ictx.fillStyle = '#140B02';
        ictx.beginPath();
        ictx.ellipse(48, 46.5, 18.2, 20.0, 0, 0, Math.PI * 2);
        ictx.fill();
        ictx.stroke();

        var caustGrad = ictx.createLinearGradient(22, 68, 74, 68);
        caustGrad.addColorStop(0, 'rgba(242, 188, 82, 0.55)');
        caustGrad.addColorStop(0.5, 'rgba(255, 248, 212, 0.96)');
        caustGrad.addColorStop(1, 'rgba(242, 188, 82, 0.55)');
        ictx.strokeStyle = caustGrad;
        ictx.lineWidth = 3.8;
        ictx.lineCap = 'round';
        ictx.beginPath();
        ictx.arc(48, 50.5, 26.2, 0.20 * Math.PI, 0.80 * Math.PI);
        ictx.stroke();

        // Studio Catchlights
        ictx.fillStyle = '#FFFFFF';
        ictx.beginPath();
        ictx.ellipse(35.0, 33.0, 10.8, 12.2, -0.22, 0, Math.PI * 2);
        ictx.fill();

        ictx.fillStyle = 'rgba(255, 250, 232, 0.96)';
        ictx.beginPath();
        ictx.arc(62.0, 59.5, 5.2, 0, Math.PI * 2);
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
    var hipMesh = new THREE.Mesh(new THREE.LatheGeometry(hipPoints, 28), mats.suitArmor);
    hipMesh.scale.set(1.06, 1.0, 0.84);
    pelvis.add(hipMesh);

    function buildHipTasset(sideSign) {
        var tassetGroup = new THREE.Group();
        var w = 0.078, h = 0.115;
        var sh = new THREE.Shape();
        sh.moveTo(-w * 0.5, 0);
        sh.lineTo(w * 0.5, 0);
        sh.lineTo(w * 0.5, -h * 0.65);
        sh.quadraticCurveTo(w * 0.45, -h * 0.90, 0, -h);
        sh.quadraticCurveTo(-w * 0.45, -h * 0.90, -w * 0.5, -h * 0.65);
        sh.closePath();

        var plateGeo = new THREE.ExtrudeGeometry(sh, {
            depth: 0.012,
            bevelEnabled: true,
            bevelThickness: 0.003,
            bevelSize: 0.003,
            bevelSegments: 2
        });
        plateGeo.center();
        var plate = new THREE.Mesh(plateGeo, mats.obsidian);

        var rimOuter = new THREE.Shape();
        var rw = w + 0.010, rh = h + 0.010;
        rimOuter.moveTo(-rw * 0.5, 0);
        rimOuter.lineTo(rw * 0.5, 0);
        rimOuter.lineTo(rw * 0.5, -rh * 0.65);
        rimOuter.quadraticCurveTo(rw * 0.45, -rh * 0.90, 0, -rh);
        rimOuter.quadraticCurveTo(-rw * 0.45, -rh * 0.90, -rw * 0.5, -rh * 0.65);
        rimOuter.closePath();

        var rimHole = new THREE.Path();
        rimHole.moveTo(-w * 0.40, -0.006);
        rimHole.lineTo(w * 0.40, -0.006);
        rimHole.lineTo(w * 0.40, -h * 0.60);
        rimHole.quadraticCurveTo(w * 0.36, -h * 0.82, 0, -h * 0.88);
        rimHole.quadraticCurveTo(-w * 0.36, -h * 0.82, -w * 0.40, -h * 0.60);
        rimHole.closePath();
        rimOuter.holes.push(rimHole);

        var rimGeo = new THREE.ExtrudeGeometry(rimOuter, {
            depth: 0.014,
            bevelEnabled: true,
            bevelThickness: 0.002,
            bevelSize: 0.002,
            bevelSegments: 2
        });
        rimGeo.center();
        var rim = new THREE.Mesh(rimGeo, mats.gold24k);
        rim.position.z = 0.002;

        var crest = new THREE.Mesh(new THREE.OctahedronGeometry(0.013, 0), mats.goldBright);
        crest.scale.set(1.0, 1.25, 0.4);
        crest.position.set(0, 0.008, 0.010);

        tassetGroup.add(plate, rim, crest);
        tassetGroup.position.set(sideSign * 0.242, 0.025, 0.065);
        tassetGroup.rotation.z = -sideSign * 0.20;
        tassetGroup.rotation.y = sideSign * 0.30;
        tassetGroup.rotation.x = -0.08;
        return tassetGroup;
    }

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
    pelvis.add(beltRing, buckleFrame, buckleCore, buckleGem, buildHipTasset(-1), buildHipTasset(1));

    function buildLeg(sideSign) {
        var hipPivot = new THREE.Group();
        hipPivot.position.set(sideSign * 0.125, -0.05, 0);
        pelvis.add(hipPivot);

        var thigh = new THREE.Mesh(new THREE.CylinderGeometry(0.078, 0.066, 0.24, 22), mats.suitArmor);
        thigh.position.y = -0.12;
        var kneeJoint = new THREE.Mesh(new THREE.SphereGeometry(0.064, 16, 12), mats.obsidian);
        kneeJoint.position.y = -0.24;
        var kneeTrim = new THREE.Mesh(new THREE.TorusGeometry(0.063, 0.008, 10, 24), mats.gold24k);
        kneeTrim.rotation.x = Math.PI / 2;
        kneeTrim.position.y = -0.23;

        var shin = new THREE.Mesh(new THREE.CylinderGeometry(0.066, 0.074, 0.22, 22), mats.suitArmor);
        shin.position.y = -0.35;
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
    var chestMesh = new THREE.Mesh(new THREE.LatheGeometry(torsoPoints, 34), mats.suitArmor);
    chestMesh.scale.set(1.10, 1.0, 0.84);
    torso.add(chestMesh);

    // Curved Chest Inscription: FALCONE / Mitgründer (48x8 curvature + normal clearance + polygonOffset)
    var emblemGeo = new THREE.CylinderGeometry(0.300, 0.266, 0.24, 48, 8, true, -0.72, 1.44);
    var emblemMesh = new THREE.Mesh(
        emblemGeo,
        new THREE.MeshBasicMaterial({
            map: makeChestEmblemTexture(),
            transparent: true,
            depthWrite: false,
            polygonOffset: true,
            polygonOffsetFactor: -2,
            polygonOffsetUnits: -2
        })
    );
    emblemMesh.scale.set(1.125, 1.0, 0.888);
    emblemMesh.position.y = 0.205;
    emblemMesh.renderOrder = 2;
    torso.add(emblemMesh);

    // Stilisierte Brustplatten-Rüstungs-Flügel / Kontur-Rippen links und rechts neben dem Emblem
    function buildChestArmorWing(sideSign) {
        var wingGroup = new THREE.Group();

        var shTop = new THREE.Shape();
        shTop.moveTo(0, 0);
        shTop.lineTo(sideSign * 0.052, 0.020);
        shTop.lineTo(sideSign * 0.062, 0.085);
        shTop.lineTo(sideSign * 0.012, 0.100);
        shTop.lineTo(0, 0.046);
        shTop.closePath();

        var geoTop = new THREE.ExtrudeGeometry(shTop, {
            depth: 0.016,
            bevelEnabled: true,
            bevelThickness: 0.004,
            bevelSize: 0.003,
            bevelSegments: 2
        });
        var plateTop = new THREE.Mesh(geoTop, mats.obsidian);

        var goldEdgeTop = new THREE.Mesh(
            new THREE.BoxGeometry(0.010, 0.082, 0.020),
            mats.gold24k
        );
        goldEdgeTop.position.set(sideSign * 0.058, 0.054, 0.009);
        goldEdgeTop.rotation.z = -sideSign * 0.14;

        var shBot = new THREE.Shape();
        shBot.moveTo(0, 0);
        shBot.lineTo(sideSign * 0.046, 0.016);
        shBot.lineTo(sideSign * 0.054, 0.068);
        shBot.lineTo(sideSign * 0.010, 0.080);
        shBot.lineTo(0, 0.038);
        shBot.closePath();

        var geoBot = new THREE.ExtrudeGeometry(shBot, {
            depth: 0.014,
            bevelEnabled: true,
            bevelThickness: 0.004,
            bevelSize: 0.003,
            bevelSegments: 2
        });
        var plateBot = new THREE.Mesh(geoBot, mats.suitArmor);
        plateBot.position.set(sideSign * 0.008, -0.068, -0.008);

        var goldEdgeBot = new THREE.Mesh(
            new THREE.BoxGeometry(0.009, 0.066, 0.018),
            mats.goldBright
        );
        goldEdgeBot.position.set(sideSign * 0.056, -0.028, 0.0);
        goldEdgeBot.rotation.z = -sideSign * 0.12;

        wingGroup.add(plateTop, goldEdgeTop, plateBot, goldEdgeBot);
        wingGroup.position.set(sideSign * 0.218, 0.178, 0.168);
        wingGroup.rotation.y = sideSign * 0.62;
        wingGroup.rotation.x = -0.10;
        return wingGroup;
    }
    torso.add(buildChestArmorWing(-1), buildChestArmorWing(1));

    // 24K Gold V-Lapel Suit Collar (sits cleanly on front of tailored chest!)
    function buildLapelWing(sideSign) {
        var sh = new THREE.Shape();
        sh.moveTo(0, 0);
        sh.lineTo(sideSign * 0.148, 0.148);
        sh.lineTo(sideSign * 0.102, 0.172);
        sh.lineTo(0, 0.042);
        sh.closePath();
        var geo = new THREE.ExtrudeGeometry(sh, {
            depth: 0.024,
            bevelEnabled: true,
            bevelThickness: 0.006,
            bevelSize: 0.005,
            bevelSegments: 2
        });
        var m = new THREE.Mesh(geo, mats.gold24k);
        m.position.set(0, 0.305, 0.248);
        m.rotation.x = -0.22;
        m.rotation.y = sideSign * 0.18;
        return m;
    }
    torso.add(buildLapelWing(-1), buildLapelWing(1));

    var collarRing = new THREE.Mesh(new THREE.TorusGeometry(0.178, 0.026, 14, 32), mats.gold24k);
    collarRing.rotation.x = Math.PI / 2;
    collarRing.position.y = 0.468;
    var tiePin = new THREE.Mesh(new THREE.OctahedronGeometry(0.022, 0), mats.goldBright);
    tiePin.scale.set(1.0, 1.3, 0.45);
    tiePin.position.set(0, 0.335, 0.265);
    torso.add(collarRing, tiePin);

    // 2-Stage Articulated Black-Gold Neck Group (rig.neck at y = 0.475 on torso)
    var neckPivot = new THREE.Group();
    neckPivot.position.y = 0.475;
    torso.add(neckPivot);
    rig.neck = neckPivot;

    var neckColumn = new THREE.Mesh(new THREE.CylinderGeometry(0.086, 0.106, 0.135, 24), mats.obsidian);
    neckColumn.position.y = 0.065;
    var neckServoRing1 = new THREE.Mesh(new THREE.TorusGeometry(0.100, 0.0085, 12, 28), mats.gold24k);
    neckServoRing1.rotation.x = Math.PI / 2;
    neckServoRing1.position.y = 0.036;
    var neckServoRing2 = new THREE.Mesh(new THREE.TorusGeometry(0.093, 0.0080, 12, 28), mats.goldBright);
    neckServoRing2.rotation.x = Math.PI / 2;
    neckServoRing2.position.y = 0.080;
    var neckUpperBall = new THREE.Mesh(new THREE.SphereGeometry(0.084, 18, 14), mats.jointDark);
    neckUpperBall.position.y = 0.128;
    neckPivot.add(neckColumn, neckServoRing1, neckServoRing2, neckUpperBall);

    // Anchor on right lapel for monocle chain
    var lapelChainAnchor = new THREE.Group();
    lapelChainAnchor.position.set(0.165, 0.365, 0.232);
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

        // Primäre Haupt-Schulterkappe (Suit-Armor mit 24K Goldrand)
        var pauldron = new THREE.Mesh(
            new THREE.SphereGeometry(0.104, 20, 14, 0, Math.PI * 2, 0, Math.PI * 0.52),
            mats.suitArmor
        );
        pauldron.rotation.z = -sideSign * 0.35;

        var shGoldRim = new THREE.Mesh(new THREE.TorusGeometry(0.102, 0.013, 12, 28), mats.gold24k);
        shGoldRim.rotation.x = Math.PI / 2;
        shGoldRim.rotation.y = -sideSign * 0.35;

        // Mehrstufiger Schulterpanzer: Zweite, leicht nach unten/außen gestaffelte Rüstungslamelle
        var pauldronTier2 = new THREE.Mesh(
            new THREE.SphereGeometry(0.098, 18, 12, 0, Math.PI * 2, 0, Math.PI * 0.50),
            mats.obsidian
        );
        pauldronTier2.position.set(sideSign * 0.022, -0.042, 0.0);
        pauldronTier2.rotation.z = -sideSign * 0.44;

        var shGoldRim2 = new THREE.Mesh(new THREE.TorusGeometry(0.096, 0.011, 10, 26), mats.gold24k);
        shGoldRim2.position.set(sideSign * 0.022, -0.042, 0.0);
        shGoldRim2.rotation.x = Math.PI / 2;
        shGoldRim2.rotation.y = -sideSign * 0.44;

        shoulderMount.add(pauldron, shGoldRim, pauldronTier2, shGoldRim2);

        var armPivot = new THREE.Group();
        shoulderMount.add(armPivot);

        var shJoint = new THREE.Mesh(new THREE.SphereGeometry(0.074, 16, 12), mats.jointDark);
        var upperArm = new THREE.Mesh(new THREE.CylinderGeometry(0.072, 0.062, 0.22, 22), mats.suitArmor);
        upperArm.position.y = -0.115;

        var bicepGold = new THREE.Mesh(new THREE.TorusGeometry(0.067, 0.011, 10, 24), mats.gold24k);
        bicepGold.rotation.x = Math.PI / 2;
        bicepGold.position.y = -0.09;
        armPivot.add(shJoint, upperArm, bicepGold);

        var elbowPivot = new THREE.Group();
        elbowPivot.position.y = -0.225;
        armPivot.add(elbowPivot);

        var elbowBall = new THREE.Mesh(new THREE.SphereGeometry(0.060, 14, 12), mats.gold24k);
        var forearm = new THREE.Mesh(new THREE.CylinderGeometry(0.064, 0.076, 0.21, 22), mats.suitArmor);
        forearm.position.y = -0.108;

        var wristCuff = new THREE.Mesh(new THREE.TorusGeometry(0.076, 0.016, 12, 26), mats.gold24k);
        wristCuff.rotation.x = Math.PI / 2;
        wristCuff.position.y = -0.205;
        elbowPivot.add(elbowBall, forearm, wristCuff);

        if (withStarShield) {
            var shieldPlane = new THREE.Mesh(
                new THREE.PlaneGeometry(0.135, 0.135),
                new THREE.MeshBasicMaterial({
                    map: makeArmShieldTexture(),
                    transparent: true,
                    depthWrite: false,
                    side: THREE.DoubleSide,
                    polygonOffset: true,
                    polygonOffsetFactor: -2,
                    polygonOffsetUnits: -2
                })
            );
            shieldPlane.position.set(sideSign * 0.072, -0.115, 0.025);
            shieldPlane.rotation.y = sideSign * 1.28;
            shieldPlane.renderOrder = 2;
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

    rig.armL = buildArm(-1, false);
    rig.armR = buildArm(1, true);

    // ========================================================================
    // HEAD HOUSING, 24K GOLD BEZEL, EARS, GENTLEMAN HAIR & 3D MONOCLE
    // ========================================================================
    var headPivot = new THREE.Group();
    // Attached to articulated neckPivot (0.475 + 0.430 = 0.905 on torso -> ~11cm clear visible neck!)
    headPivot.position.y = 0.430;
    neckPivot.add(headPivot);
    rig.head = headPivot;

    // Casing total Z after bevels & centering: (0.28 + 2 * 0.045) / 2 = +/- 0.185
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

    // 24K Gold Front Frame Bezel (centered half-depth = 0.0455 -> front edge at 0.168 + 0.0455 = +0.2135)
    var bezelOuter = createRoundedRectShape(headW + 0.065, headH + 0.065, headR + 0.022);
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
    bezelMesh.position.z = 0.168;
    headPivot.add(bezelMesh);

    // Rear 24K Gold Rim Accent
    var rearRim = new THREE.Mesh(bezelGeo, mats.gold24k);
    rearRim.scale.set(0.95, 0.95, 0.65);
    rearRim.position.z = -0.168;
    headPivot.add(rearRim);

    // Retina Visor Face Screen at z = +0.191 (strictly IN FRONT of casingMesh +0.185!)
    var visorPlane = new THREE.Mesh(
        new THREE.PlaneGeometry(0.84, 0.52),
        new THREE.MeshBasicMaterial({
            map: visorTex,
            polygonOffset: true,
            polygonOffsetFactor: -1,
            polygonOffsetUnits: -1
        })
    );
    visorPlane.position.z = 0.191;
    headPivot.add(visorPlane);

    // Side Gold Ear Modules
    function buildEarModule(sideSign) {
        var eg = new THREE.Group();
        eg.position.set(sideSign * 0.50, 0.0, 0.0);
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

    // Funkira Signature Crystal Siren Dome & Cozy Scarf (exact Mini-PC funkira3d.js rig, zero house world)
    var sirenGroup = new THREE.Group();
    sirenGroup.position.set(0, 0.315, 0.0);
    headPivot.add(sirenGroup);

    var sirenBase = new THREE.Mesh(new THREE.CylinderGeometry(0.116, 0.140, 0.046, 24), mats.gold24k);
    sirenBase.position.set(0, 0.015, 0);
    var sirenGoldCollar = new THREE.Mesh(new THREE.TorusGeometry(0.118, 0.010, 10, 24), mats.goldBright);
    sirenGoldCollar.rotation.x = Math.PI / 2;
    sirenGoldCollar.position.set(0, 0.038, 0);
    var sirenDome = new THREE.Mesh(new THREE.CylinderGeometry(0.084, 0.102, 0.112, 24), mats.earGlow);
    sirenDome.position.set(0, 0.086, 0);
    var sirenCrystalBell = new THREE.Mesh(new THREE.CylinderGeometry(0.098, 0.116, 0.120, 24), mats.sirenCrystal);
    sirenCrystalBell.position.set(0, 0.088, 0);
    var sirenCap = new THREE.Mesh(new THREE.CylinderGeometry(0.052, 0.094, 0.020, 24), mats.goldBright);
    sirenCap.position.set(0, 0.152, 0);
    var sirenStar = new THREE.Mesh(new THREE.OctahedronGeometry(0.020, 0), mats.goldBright);
    sirenStar.position.set(0, 0.174, 0);
    sirenGroup.add(sirenBase, sirenGoldCollar, sirenDome, sirenCrystalBell, sirenCap, sirenStar);

    if (rig.torso) {
        var scarfGroup = new THREE.Group();
        var scarfRing = new THREE.Mesh(new THREE.TorusGeometry(0.205, 0.046, 14, 30), mats.scarfRed);
        scarfRing.rotation.x = Math.PI / 2;
        scarfRing.position.y = 0.43;
        var scarfTail = new THREE.Mesh(new THREE.BoxGeometry(0.088, 0.24, 0.034), mats.scarfRed);
        scarfTail.position.set(0.11, 0.30, 0.215);
        scarfTail.rotation.z = 0.14;
        var scarfGoldTip = new THREE.Mesh(new THREE.BoxGeometry(0.092, 0.022, 0.036), mats.goldBright);
        scarfGoldTip.position.set(0.093, 0.18, 0.216);
        scarfGoldTip.rotation.z = 0.14;
        scarfGroup.add(scarfRing, scarfTail, scarfGoldTip);
        rig.torso.add(scarfGroup);
    }

    // ========================================================================
    // RETINA VISOR DRAWING (GOLD EYES + EXPRESSIVE GENTLEMAN MUSTACHE + LIPSYNC)
    // ========================================================================
    var faceCur = {
        lidTop: 0.02,
        lidBot: 0.02,
        browTilt: -0.04,
        browY: 0.0,
        mouthCurve: 0.34,
        mouthOpen: 0.04,
        pupilScale: 1.02,
        mustacheLift: 0.10,
        headPitch: 0.0,
        headRoll: 0.0
    };
    var blinkVal = 0.0;
    var nextBlinkMs = performance.now() + 2600;
    var lookCur = { x: 0, y: 0 };
    var lookTarget = { x: 0, y: 0 };
    var nextSaccadeMs = performance.now() + 1800;

    function drawGentlemanMustache(ctx, cx, cy, lift, speechFlex) {
        ctx.save();
        ctx.translate(cx, cy);

        // Soft drop shadow so the sculptural mustache stands out in 3D relief
        ctx.shadowColor = 'rgba(0, 0, 0, 0.85)';
        ctx.shadowBlur = 6;
        ctx.shadowOffsetY = 3;

        for (var s = -1; s <= 1; s += 2) {
            ctx.save();
            ctx.scale(s, 1);

            var tipY = -8.5 - lift * 11.0 - speechFlex * 4.5;
            var bodyDrop = 16.5 + speechFlex * 3.0;

            var mg = ctx.createLinearGradient(0, -14, 82, 18);
            mg.addColorStop(0.0, '#4E3D2C');
            mg.addColorStop(0.45, '#6B533B');
            mg.addColorStop(0.85, '#3E3022');
            mg.addColorStop(1.0, '#2B2016');

            ctx.fillStyle = mg;
            ctx.strokeStyle = '#C5A059';
            ctx.lineWidth = 1.6;
            ctx.beginPath();
            ctx.moveTo(-1.0, -4.5);
            ctx.bezierCurveTo(15, -17.0, 42, -14.0, 58, 0.5 - lift * 4.0);
            ctx.bezierCurveTo(68, 7.0 - lift * 5.5, 77, 0.5 + tipY * 0.45, 83, tipY);
            ctx.bezierCurveTo(85, tipY + 10.0, 72, 20.0 - lift * 3.5, 51, bodyDrop);
            ctx.bezierCurveTo(30, bodyDrop + 3.5, 13, 8.5, -1.0, 6.0);
            ctx.closePath();
            ctx.fill();
            ctx.stroke();

            ctx.shadowBlur = 0;
            ctx.shadowOffsetY = 0;

            // Sculpted hair fiber ridges in warm champagne gold
            ctx.strokeStyle = 'rgba(238, 202, 126, 0.82)';
            ctx.lineWidth = 2.4;
            ctx.lineCap = 'round';
            ctx.beginPath();
            ctx.moveTo(4, -5.8);
            ctx.bezierCurveTo(21, -12.5, 42, -8.2, 64, 1.5 - lift * 4.2);
            ctx.stroke();

            ctx.strokeStyle = 'rgba(255, 234, 168, 0.55)';
            ctx.lineWidth = 1.6;
            ctx.beginPath();
            ctx.moveTo(10, -1.2);
            ctx.bezierCurveTo(28, -5.0, 50, 2.0, 77, tipY + 2.5);
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
            nextBlinkMs = nowMs + 2800 + Math.random() * 3400;
        }
        if (blinkVal > 0) {
            blinkVal = Math.max(0, blinkVal - dt * 8.0);
        }
        var blinkCurve = blinkVal > 0.001 ? Math.sin(blinkVal * Math.PI) : 0;

        if (nowMs >= nextSaccadeMs) {
            if (state.mode === 'listening' || state.mode === 'speaking') {
                lookTarget.x = (Math.random() - 0.5) * 0.24;
                lookTarget.y = (Math.random() - 0.5) * 0.16;
            } else if (emoKey === 'thinking') {
                lookTarget.x = 0.12;
                lookTarget.y = 0.05;
            } else {
                lookTarget.x = (Math.random() - 0.5) * 0.36;
                lookTarget.y = (Math.random() - 0.5) * 0.20;
            }
            nextSaccadeMs = nowMs + 1500 + Math.random() * 2400;
        }
        lookCur.x = lerp(lookCur.x, lookTarget.x, 1.0 - Math.exp(-dt * 6.5));
        lookCur.y = lerp(lookCur.y, lookTarget.y, 1.0 - Math.exp(-dt * 6.5));

        var isSpeakingNow = state.mode === 'speaking' || (state.audioLevel > 0.04 && state.mode !== 'listening');
        var osc = isSpeakingNow
            ? (0.42 + 0.58 * Math.abs(Math.sin(nowMs * 0.022)) * Math.abs(Math.cos(nowMs * 0.014)))
            : 0.0;
        var amp = clamp(state.audioLevel || 0, 0, 1);
        var voiceOpen = isSpeakingNow ? clamp((0.18 + amp * 1.15) * osc, 0.06, 0.94) : 0.0;
        var openAmt = clamp(Math.max(faceCur.mouthOpen, voiceOpen), 0.03, 0.96);

        var lidTop = clamp(faceCur.lidTop + blinkCurve * 0.96, 0, 0.98);
        var lidBot = clamp(faceCur.lidBot, 0, 0.60);
        var gx = lookCur.x * 14.0;
        var gy = lookCur.y * 9.0;

        visorCtx.drawImage(visorBgCanvas, 0, 0);
        visorCtx.save();
        visorCtx.scale(2, 2);

        var eyeY = 104 + gy * 0.65;
        var eyeCenters = [110 + gx * 0.70, 274 + gx * 0.70];
        var rx = 44.0;
        var ry = 44.5;
        var irisSize = 95 * (faceCur.pupilScale || 1.0);

        for (var idx = 0; idx < 2; idx++) {
            var side = idx === 0 ? -1 : 1;
            var ex = eyeCenters[idx];

            // Clip iris & eyelids cleanly inside the eye ellipse so zero dark mask ever spills onto the visor!
            visorCtx.save();
            visorCtx.beginPath();
            visorCtx.ellipse(ex, eyeY, rx, ry, 0, 0, Math.PI * 2);
            visorCtx.clip();

            visorCtx.fillStyle = '#1C1205';
            visorCtx.fillRect(ex - rx - 4, eyeY - ry - 4, rx * 2 + 8, ry * 2 + 8);

            var px = ex + gx * 0.35;
            var py = eyeY + gy * 0.35;
            visorCtx.drawImage(irisSpriteCanvas, px - irisSize * 0.5, py - irisSize * 0.5, irisSize, irisSize);

            if (lidTop > 0.01) {
                visorCtx.fillStyle = '#14151C';
                visorCtx.fillRect(ex - rx - 6, eyeY - ry - 6, rx * 2 + 12, ry * 2 * lidTop + 4);
            }
            if (lidBot > 0.01) {
                visorCtx.fillStyle = '#14151C';
                var bh = ry * 1.6 * lidBot;
                visorCtx.fillRect(ex - rx - 6, eyeY + ry - bh, rx * 2 + 12, bh + 8);
            }
            visorCtx.restore();

            visorCtx.strokeStyle = '#E88DA3';
            visorCtx.lineWidth = 4.2;
            visorCtx.lineCap = 'round';
            visorCtx.beginPath();
            visorCtx.ellipse(ex, eyeY, rx + 0.5, ry * Math.max(0.08, 1 - lidTop * 1.08), 0, Math.PI * 1.10, Math.PI * 1.90);
            visorCtx.stroke();

            // 3 feminine curved eyelashes (matching Mini-PC funkira3d.js)
            if (lidTop < 0.82) {
                var archY = eyeY - ry * (1 - lidTop * 1.45);
                visorCtx.strokeStyle = '#DF8299';
                visorCtx.lineWidth = 3.8;
                for (var l = 0; l < 3; l++) {
                    var lx0 = ex + side * (rx - 4.5 - l * 5.2);
                    var ly0 = archY + 10.5 + l * 4.5;
                    visorCtx.beginPath();
                    visorCtx.moveTo(lx0, ly0);
                    visorCtx.quadraticCurveTo(lx0 + side * 9, ly0 - 4.5, lx0 + side * (14 - l * 1.5), ly0 - (10.5 - l * 2.2));
                    visorCtx.stroke();
                }
            }

            // Soft Rose Blush Cheeks
            var blushGrad = visorCtx.createRadialGradient(ex + side * 10, eyeY + 52, 2, ex + side * 10, eyeY + 52, 28);
            blushGrad.addColorStop(0.0, 'rgba(244, 118, 152, 0.42)');
            blushGrad.addColorStop(1.0, 'rgba(244, 118, 152, 0.0)');
            visorCtx.fillStyle = blushGrad;
            visorCtx.beginPath();
            visorCtx.ellipse(ex + side * 10, eyeY + 52, 28, 16, 0, 0, Math.PI * 2);
            visorCtx.fill();

            var by = 46 + gy * 0.40 + faceCur.browY;
            var bTilt = faceCur.browTilt * side;
            visorCtx.save();
            visorCtx.translate(ex, by);
            visorCtx.rotate(bTilt);
            visorCtx.strokeStyle = '#E78CA2';
            visorCtx.lineWidth = 4.6;
            visorCtx.lineCap = 'round';
            visorCtx.beginPath();
            visorCtx.moveTo(-24, 4.5);
            visorCtx.quadraticCurveTo(0, -7.5, 24, 4.5);
            visorCtx.stroke();
            visorCtx.restore();
        }

        // Lip-Sync Mouth (Funkira Rose-Pink Lip Glow, no mustache!)
        var mx = 192 + gx * 0.45;
        var my = 172 + gy * 0.45;
        var mw = 24 + openAmt * 12 + Math.max(0, faceCur.mouthCurve) * 8;
        var mh = 4.5 + openAmt * 26;

        visorCtx.save();
        visorCtx.beginPath();
        visorCtx.moveTo(mx - mw, my - 2);
        visorCtx.quadraticCurveTo(mx, my - 2 + faceCur.mouthCurve * 7, mx + mw, my - 2);
        visorCtx.quadraticCurveTo(mx + mw * 0.82, my + mh, mx, my + mh);
        visorCtx.quadraticCurveTo(mx - mw * 0.82, my + mh, mx - mw, my - 2);
        visorCtx.closePath();

        var mouthGrad = visorCtx.createLinearGradient(mx, my - 2, mx, my + mh);
        mouthGrad.addColorStop(0, '#FFEBF0');
        mouthGrad.addColorStop(0.55, '#F08CA5');
        mouthGrad.addColorStop(1, '#58162A');
        visorCtx.fillStyle = mouthGrad;
        visorCtx.fill();
        visorCtx.restore();

        if (state.cameraActive) {
            var pulse = 0.55 + 0.35 * Math.sin(nowMs * 0.005);
            visorCtx.strokeStyle = 'rgba(229, 192, 112, ' + pulse.toFixed(2) + ')';
            visorCtx.lineWidth = 2.2;
            var pad = 18, len = 14;
            visorCtx.beginPath(); visorCtx.moveTo(pad, pad + len); visorCtx.lineTo(pad, pad); visorCtx.lineTo(pad + len, pad); visorCtx.stroke();
            visorCtx.beginPath(); visorCtx.moveTo(384 - pad - len, pad); visorCtx.lineTo(384 - pad, pad); visorCtx.lineTo(384 - pad, pad + len); visorCtx.stroke();
            visorCtx.beginPath(); visorCtx.moveTo(pad, 240 - pad - len); visorCtx.lineTo(pad, 240 - pad); visorCtx.lineTo(pad + len, 240 - pad); visorCtx.stroke();
            visorCtx.beginPath(); visorCtx.moveTo(384 - pad - len, 240 - pad); visorCtx.lineTo(384 - pad, 240 - pad); visorCtx.lineTo(384 - pad, 240 - pad - len); visorCtx.stroke();
        }

        visorCtx.restore();
        visorTex.needsUpdate = true;
    }

    // ========================================================================
    // EXACT 2-AXIS PORTRAIT CAMERA FRAMING + 1-FINGER ROTATE + 2-FINGER PINCH ZOOM
    // ========================================================================
    function updateCameraFraming() {
        var tanHalfFov = Math.tan((camera.fov * 0.5) * Math.PI / 180.0);
        // Horizontal half-span 0.96m -> generous symmetrical margin around both gold ears & shoulders on 412x915
        var distW = 0.96 / Math.max(0.12, tanHalfFov * camera.aspect);
        // Vertical half-span 1.06m centered around Y=1.18 -> full figure + hair + clear headroom below Top-Bar & above Bottom-Dock
        var distH = 1.06 / tanHalfFov;
        var camDist = Math.max(distW, distH) * userZoom;
        camera.position.set(0, 1.24, camDist);
        camera.lookAt(0, 1.18, 0);
        camera.updateProjectionMatrix();
    }

    function resize() {
        var docEl = document.documentElement;
        var w = Math.max(200, Math.round((docEl && docEl.clientWidth) ? docEl.clientWidth : (window.innerWidth || 412)));
        var h = Math.max(240, Math.round((docEl && docEl.clientHeight) ? docEl.clientHeight : (window.innerHeight || 915)));
        var dpr = Math.min(window.devicePixelRatio || 1, 2.0);
        renderer.setPixelRatio(dpr);
        renderer.setSize(w, h, false);
        camera.aspect = w / h;
        updateCameraFraming();
    }
    window.addEventListener('resize', resize);
    resize();

    var dragActive = false;
    var pinchActive = false;
    var pinchStartDist = 0.0;
    var pinchStartZoom = 1.0;
    var lastPtrX = 0;
    var lastPtrY = 0;
    var userYaw = 0.0;
    var userPitch = 0.0;

    function touchDistance(t0, t1) {
        var dx = t0.clientX - t1.clientX;
        var dy = t0.clientY - t1.clientY;
        return Math.sqrt(dx * dx + dy * dy);
    }

    canvas.addEventListener('touchstart', function (e) {
        if (e.touches.length >= 2) {
            pinchActive = true;
            dragActive = false;
            pinchStartDist = Math.max(10, touchDistance(e.touches[0], e.touches[1]));
            pinchStartZoom = userZoom;
        } else if (e.touches.length === 1) {
            pinchActive = false;
            dragActive = true;
            lastPtrX = e.touches[0].clientX;
            lastPtrY = e.touches[0].clientY;
        }
    }, { passive: true });

    canvas.addEventListener('touchmove', function (e) {
        if (pinchActive && e.touches.length >= 2) {
            var curDist = Math.max(10, touchDistance(e.touches[0], e.touches[1]));
            var ratio = pinchStartDist / curDist;
            userZoom = clamp(pinchStartZoom * ratio, 0.72, 1.45);
            updateCameraFraming();
        } else if (dragActive && e.touches.length === 1) {
            var dx = e.touches[0].clientX - lastPtrX;
            var dy = e.touches[0].clientY - lastPtrY;
            lastPtrX = e.touches[0].clientX;
            lastPtrY = e.touches[0].clientY;
            userYaw = clamp(userYaw + dx * 0.0075, -0.85, 0.85);
            userPitch = clamp(userPitch + dy * 0.0035, -0.22, 0.22);
        }
    }, { passive: true });

    function endTouch(e) {
        if (!e.touches || e.touches.length === 0) {
            dragActive = false;
            pinchActive = false;
        } else if (e.touches.length === 1) {
            pinchActive = false;
            dragActive = true;
            lastPtrX = e.touches[0].clientX;
            lastPtrY = e.touches[0].clientY;
        }
    }
    canvas.addEventListener('touchend', endTouch, { passive: true });
    canvas.addEventListener('touchcancel', endTouch, { passive: true });

    // Mouse / Desktop fallback for local preview & testing
    canvas.addEventListener('mousedown', function (e) {
        dragActive = true;
        lastPtrX = e.clientX;
        lastPtrY = e.clientY;
    });
    window.addEventListener('mousemove', function (e) {
        if (!dragActive || pinchActive) return;
        var dx = e.clientX - lastPtrX;
        var dy = e.clientY - lastPtrY;
        lastPtrX = e.clientX;
        lastPtrY = e.clientY;
        userYaw = clamp(userYaw + dx * 0.0075, -0.85, 0.85);
        userPitch = clamp(userPitch + dy * 0.0035, -0.22, 0.22);
    });
    window.addEventListener('mouseup', function () { dragActive = false; });
    canvas.addEventListener('wheel', function (e) {
        userZoom = clamp(userZoom + (e.deltaY > 0 ? 0.06 : -0.06), 0.72, 1.45);
        updateCameraFraming();
    }, { passive: true });

    // ========================================================================
    // FLOATING SPARKS + 60 FPS ANIMATION LOOP (FUNKIRA STANDALONE 3D)
    // ========================================================================
    function updateMonocleChain() {
        // Monocle removed: pure Funkira 3D character without monocle/house world
    }

    function updateSparks(dt, tSec) {
        var posAttr = sparkGeo.attributes.position;
        var arr = posAttr.array;
        for (var i = 0; i < SPARK_COUNT; i++) {
            arr[i * 3 + 1] += sparkSpeeds[i] * dt;
            arr[i * 3] += Math.sin(tSec * 0.8 + sparkPhase[i]) * 0.012 * dt;
            if (arr[i * 3 + 1] > 2.25) {
                arr[i * 3 + 1] = 0.15;
                arr[i * 3] = (Math.random() - 0.5) * 2.0;
            }
        }
        posAttr.needsUpdate = true;
    }

    var lastFrameMs = performance.now();
    var smoothAmp = 0.0;
    var prevMode = 'idle';
    var bowPhase = 0.0;

    function animate(nowMs) {
        requestAnimationFrame(animate);
        stepScene(nowMs);
    }

    function stepScene(nowMs) {
        var dt = clamp((nowMs - lastFrameMs) * 0.001, 0.001, 0.05);
        lastFrameMs = nowMs;
        var tSec = nowMs * 0.001;

        if (state.mode !== prevMode) {
            if (state.mode === 'connecting' || (prevMode === 'idle' && state.mode === 'listening')) {
                bowPhase = 1.0;
            }
            prevMode = state.mode;
        }
        if (bowPhase > 0.0) {
            bowPhase = Math.max(0.0, bowPhase - dt * 0.85);
        }
        var gentlemanBow = bowPhase > 0.001 ? Math.sin(bowPhase * Math.PI) * 0.14 : 0.0;

        if (!dragActive) {
            userYaw = lerp(userYaw, 0.0, 1.0 - Math.exp(-dt * 4.8));
            userPitch = lerp(userPitch, 0.0, 1.0 - Math.exp(-dt * 5.2));
        }

        smoothAmp = lerp(smoothAmp, clamp(state.audioLevel || 0, 0, 1), 1.0 - Math.exp(-dt * 14.0));
        var emoKey = resolveActiveEmotionKey();

        var breath = Math.sin(tSec * 1.9) * 0.012;
        pelvis.position.y = 0.56 + breath;
        root.rotation.y = userYaw + Math.sin(tSec * 0.65) * 0.030;
        torso.rotation.x = userPitch * 0.45 + (state.mode === 'listening' ? 0.04 : 0.0) + gentlemanBow * 0.28;

        var speakNod = (state.mode === 'speaking')
            ? (Math.sin(tSec * 6.8) * 0.034 * (0.3 + smoothAmp))
            : 0.0;
        var connectNod = (state.mode === 'connecting')
            ? (0.04 + 0.03 * Math.sin(tSec * 2.6))
            : 0.0;
        var totalPitch = (faceCur.headPitch || 0) + userPitch * 0.65 + speakNod + connectNod + gentlemanBow;
        var totalRoll = (faceCur.headRoll || 0) + Math.sin(tSec * 0.9) * 0.020;
        var totalYaw = (emoKey === 'thinking' ? 0.04 : 0.0) + Math.sin(tSec * 0.75) * 0.045 + userYaw * 0.18;

        var neckBreathPitch = Math.sin(tSec * 1.9 + 0.5) * 0.016 + (state.mode === 'listening' ? 0.035 : 0.0);
        var neckBlend = 1.0 - Math.exp(-dt * 6.8);
        var headBlend = 1.0 - Math.exp(-dt * 8.2);

        // 40% articulated neck motion + 60% upper head joint motion for natural organic posture!
        rig.neck.rotation.x = lerp(rig.neck.rotation.x, totalPitch * 0.40 + neckBreathPitch, neckBlend);
        rig.neck.rotation.z = lerp(rig.neck.rotation.z, totalRoll * 0.40, neckBlend);
        rig.neck.rotation.y = lerp(rig.neck.rotation.y, totalYaw * 0.42, neckBlend);

        headPivot.rotation.x = lerp(headPivot.rotation.x, totalPitch * 0.60, headBlend);
        headPivot.rotation.z = lerp(headPivot.rotation.z, totalRoll * 0.60, headBlend);
        headPivot.rotation.y = lerp(headPivot.rotation.y, totalYaw * 0.58, headBlend);

        var isActivating = (state.mode === 'connecting' || emoKey === 'thinking');
        var earPulse = (state.mode === 'listening' || state.cameraActive || isActivating)
            ? (0.78 + 0.22 * Math.sin(tSec * 5.2))
            : (state.mode === 'speaking' ? (0.68 + 0.32 * smoothAmp) : 0.58);
        mats.earGlow.color.setRGB(0.94 * earPulse, 0.78 * earPulse, 0.42 * earPulse);
        mats.monocleLens.opacity = isActivating
            ? (0.74 + 0.22 * Math.abs(Math.sin(tSec * 3.6)))
            : 0.72;
        floorDisc.material.opacity = isActivating
            ? (0.86 + 0.14 * Math.sin(tSec * 3.4))
            : 0.94;

        // ====================================================================
        // SYMMETRICAL GENTLEMAN / CEO WAIST-LEVEL ARM POSTURES
        // Both upper arms stay strictly low along the sides of the torso;
        // forearms fold symmetrically toward the golden belt buckle.
        // ====================================================================
        var rArmX = 0.04, rArmZ = 0.04, rElbX = -0.32, rElbZ = -0.18;
        var lArmX = 0.04, lArmZ = -0.04, lElbX = -0.32, lElbZ = 0.18;

        if (state.mode === 'connecting' || emoKey === 'thinking' || emoKey === 'focused') {
            // Symmetrical Gentleman Belt-Clasp: both hands folded calmly at waist/belt level
            var subtlePulse = Math.sin(tSec * 2.2) * 0.025;
            rArmX = -0.10;
            lArmX = -0.10;
            rArmZ = 0.06;
            lArmZ = -0.06;
            rElbX = -0.96 + subtlePulse;
            lElbX = -0.96 + subtlePulse;
            rElbZ = -0.78;
            lElbZ = 0.78;
        } else if (state.mode === 'listening') {
            // Attentive symmetrical open-belt posture
            rArmX = -0.08;
            lArmX = -0.08;
            rArmZ = 0.05;
            lArmZ = -0.05;
            rElbX = -0.74;
            lElbX = -0.74;
            rElbZ = -0.52;
            lElbZ = 0.52;
        } else if (state.mode === 'speaking') {
            // Low, symmetrical waist-level micro-gestures while speaking
            var sBeatR = Math.sin(tSec * 4.2) * (0.04 + smoothAmp * 0.08);
            var sBeatL = Math.sin(tSec * 4.2 + 0.9) * (0.04 + smoothAmp * 0.08);
            rArmX = -0.09 - Math.abs(sBeatR) * 0.35;
            lArmX = -0.09 - Math.abs(sBeatL) * 0.35;
            rArmZ = 0.06;
            lArmZ = -0.06;
            rElbX = -0.76 - Math.abs(sBeatR) * 1.1;
            lElbX = -0.76 - Math.abs(sBeatL) * 1.1;
            rElbZ = -0.50;
            lElbZ = 0.50;
        } else if (emoKey === 'proud' || emoKey === 'happy') {
            rArmX = -0.08;
            lArmX = -0.08;
            rArmZ = 0.06;
            lArmZ = -0.06;
            rElbX = -0.68;
            lElbX = -0.68;
            rElbZ = -0.46;
            lElbZ = 0.46;
        } else {
            rArmX = 0.04 + breath * 1.1;
            lArmX = 0.04 + breath * 1.1;
            rArmZ = 0.04;
            lArmZ = -0.04;
            rElbX = -0.32;
            lElbX = -0.32;
            rElbZ = -0.18;
            lElbZ = 0.18;
        }

        // Hard mathematical safety clamp: no upper arm can EVER lift forward/upward!
        rArmX = clamp(rArmX, -0.18, 0.12);
        lArmX = clamp(lArmX, -0.18, 0.12);
        rArmZ = clamp(rArmZ, -0.14, 0.14);
        lArmZ = clamp(lArmZ, -0.14, 0.14);
        rElbX = clamp(rElbX, -1.10, -0.20);
        lElbX = clamp(lElbX, -1.10, -0.20);

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
        updateSparks(dt, tSec);
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
        stepForPreview: function (frames, stepMs) {
            var n = frames || 60;
            var ms = stepMs || 16.67;
            var simNow = lastFrameMs;
            for (var i = 0; i < n; i++) {
                simNow += ms;
                stepScene(simNow);
            }
        },
        getState: function () {
            return {
                mode: state.mode,
                audioLevel: state.audioLevel,
                emotion: state.emotion,
                isDark: state.isDark,
                cameraActive: state.cameraActive,
                userZoom: userZoom
            };
        }
    };

    window.FunkiraScene = window.FalconScene;

    renderVisorFace(performance.now(), 0.016);
    requestAnimationFrame(animate);
})();
