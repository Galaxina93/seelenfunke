(function () {
    'use strict';

    if (typeof THREE === 'undefined') return;

    function clamp(v, lo, hi) { return Math.max(lo, Math.min(hi, v)); }
    function lerp(a, b, t) { return a + (b - a) * t; }

    function hexToRgb(hex) {
        var clean = String(hex).replace('#', '');
        var n = parseInt(clean, 16);
        return [(n >> 16) & 255, (n >> 8) & 255, n & 255];
    }

    var maxAnisotropy = 4;

    function applySrgb(tex) {
        if (!tex) return tex;
        if (THREE.SRGBColorSpace) {
            tex.colorSpace = THREE.SRGBColorSpace;
        } else if (THREE.sRGBEncoding) {
            tex.encoding = THREE.sRGBEncoding;
        }
        return tex;
    }

    function configureStaticTexture(tex) {
        if (!tex) return tex;
        tex.generateMipmaps = true;
        tex.minFilter = THREE.LinearMipmapLinearFilter;
        tex.magFilter = THREE.LinearFilter;
        tex.anisotropy = maxAnisotropy;
        return applySrgb(tex);
    }

    function configureFastTexture(tex) {
        if (!tex) return tex;
        tex.generateMipmaps = false;
        tex.minFilter = THREE.LinearFilter;
        tex.magFilter = THREE.LinearFilter;
        return applySrgb(tex);
    }

    // Inline Three.js RoundedBoxGeometry (1:1 port of Mini-PC vendor/RoundedBoxGeometry.js, compatible with r128..r170+)
    var _rbTempNormal = new THREE.Vector3();
    function getRoundedBoxUv(faceDirVector, normal, uvAxis, projectionAxis, radius, sideLength) {
        var totArcLength = 2 * Math.PI * radius / 4;
        var centerLength = Math.max(sideLength - 2 * radius, 0);
        var halfArc = Math.PI / 4;

        _rbTempNormal.copy(normal);
        _rbTempNormal[projectionAxis] = 0;
        _rbTempNormal.normalize();

        var arcUvRatio = 0.5 * totArcLength / (totArcLength + centerLength);
        var arcAngleRatio = 1.0 - (_rbTempNormal.angleTo(faceDirVector) / halfArc);

        if (Math.sign(_rbTempNormal[uvAxis]) === 1) {
            return arcAngleRatio * arcUvRatio;
        }
        var lenUv = centerLength / (totArcLength + centerLength);
        return lenUv + arcUvRatio + arcUvRatio * (1.0 - arcAngleRatio);
    }

    function createRoundedBoxGeometry(width, height, depth, segments, radius) {
        var segs = segments || 2;
        var totalSegments = segs * 2 + 1;
        var rad = Math.min(width / 2, height / 2, depth / 2, radius || 0.02);
        var baseGeo = new THREE.BoxGeometry(1, 1, 1, totalSegments, totalSegments, totalSegments);
        if (totalSegments === 1 || !baseGeo.toNonIndexed) {
            return new THREE.BoxGeometry(width, height, depth);
        }

        var geo = baseGeo.toNonIndexed();
        baseGeo.dispose();

        var position = new THREE.Vector3();
        var normal = new THREE.Vector3();
        var box = new THREE.Vector3(width, height, depth).divideScalar(2).subScalar(rad);

        var positions = geo.attributes.position.array;
        var normals = geo.attributes.normal.array;
        var uvs = geo.attributes.uv.array;

        var faceTris = positions.length / 6;
        var faceDirVector = new THREE.Vector3();
        var halfSegmentSize = 0.5 / totalSegments;

        for (var i = 0, j = 0; i < positions.length; i += 3, j += 2) {
            position.fromArray(positions, i);
            normal.copy(position);
            normal.x -= Math.sign(normal.x) * halfSegmentSize;
            normal.y -= Math.sign(normal.y) * halfSegmentSize;
            normal.z -= Math.sign(normal.z) * halfSegmentSize;
            normal.normalize();

            positions[i]     = box.x * Math.sign(position.x) + normal.x * rad;
            positions[i + 1] = box.y * Math.sign(position.y) + normal.y * rad;
            positions[i + 2] = box.z * Math.sign(position.z) + normal.z * rad;

            normals[i]     = normal.x;
            normals[i + 1] = normal.y;
            normals[i + 2] = normal.z;

            var side = Math.floor(i / faceTris);
            switch (side) {
                case 0:
                    faceDirVector.set(1, 0, 0);
                    uvs[j]     = getRoundedBoxUv(faceDirVector, normal, 'z', 'y', rad, depth);
                    uvs[j + 1] = 1.0 - getRoundedBoxUv(faceDirVector, normal, 'y', 'z', rad, height);
                    break;
                case 1:
                    faceDirVector.set(-1, 0, 0);
                    uvs[j]     = 1.0 - getRoundedBoxUv(faceDirVector, normal, 'z', 'y', rad, depth);
                    uvs[j + 1] = 1.0 - getRoundedBoxUv(faceDirVector, normal, 'y', 'z', rad, height);
                    break;
                case 2:
                    faceDirVector.set(0, 1, 0);
                    uvs[j]     = 1.0 - getRoundedBoxUv(faceDirVector, normal, 'x', 'z', rad, width);
                    uvs[j + 1] = getRoundedBoxUv(faceDirVector, normal, 'z', 'x', rad, depth);
                    break;
                case 3:
                    faceDirVector.set(0, -1, 0);
                    uvs[j]     = 1.0 - getRoundedBoxUv(faceDirVector, normal, 'x', 'z', rad, width);
                    uvs[j + 1] = getRoundedBoxUv(faceDirVector, normal, 'z', 'x', rad, depth);
                    break;
                case 4:
                    faceDirVector.set(0, 0, 1);
                    uvs[j]     = 1.0 - getRoundedBoxUv(faceDirVector, normal, 'x', 'y', rad, width);
                    uvs[j + 1] = 1.0 - getRoundedBoxUv(faceDirVector, normal, 'y', 'x', rad, height);
                    break;
                case 5:
                    faceDirVector.set(0, 0, -1);
                    uvs[j]     = getRoundedBoxUv(faceDirVector, normal, 'x', 'y', rad, width);
                    uvs[j + 1] = 1.0 - getRoundedBoxUv(faceDirVector, normal, 'y', 'x', rad, height);
                    break;
            }
        }
        geo.computeBoundingSphere();
        return geo;
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

    // 1:1 Mini-PC Luxury Studio MatCap Generator
    function makeStudioMatcapTexture(baseHex, specHex, rimHex, specPower, rimStrength) {
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
        var lx2 = 0.50,  ly2 = 0.34, lz2 = 0.796;
        var hx2 = 0.31,  hy2 = 0.22, hz2 = 0.925;
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
                    var horizonBand = Math.exp(-Math.pow((ny + 0.14) * 5.2, 2)) * (specPower >= 20 ? 0.11 : 0.03);
                    var hemi = skyBounce + 0.47 * ndl1 + 0.21 * ndl2 + horizonBand;

                    var ndh1 = Math.max(0, nx * hx1 + ny * hy1 + nz * hz1);
                    var ndh2 = Math.max(0, nx * hx2 + ny * hy2 + nz * hz2);
                    var spBroad = Math.pow(ndh1, Math.max(6, specPower * 0.45)) * 0.26;
                    var spCrisp = Math.pow(ndh1, specPower * 1.35) * 0.78 + Math.pow(ndh2, specPower * 0.9) * 0.18;
                    var sp = spBroad + spCrisp;
                    var fresnel = Math.pow(1.0 - nz, 2.65) * rimStrength;

                    r = base[0] * hemi + spec[0] * sp + rim[0] * fresnel;
                    g = base[1] * hemi + spec[1] * sp + rim[1] * fresnel;
                    b = base[2] * hemi + spec[2] * sp + rim[2] * fresnel;
                } else {
                    var len = Math.sqrt(r2);
                    var uy = ny / len;
                    var h2 = 0.35 + 0.25 * (uy * 0.5 + 0.5);
                    r = base[0] * h2 + rim[0] * rimStrength * 0.92;
                    g = base[1] * h2 + rim[1] * rimStrength * 0.92;
                    b = base[2] * h2 + rim[2] * rimStrength * 0.92;
                }
                var p = (y * size + x) * 4;
                d[p]     = clamp(Math.round(r), 0, 255);
                d[p + 1] = clamp(Math.round(g), 0, 255);
                d[p + 2] = clamp(Math.round(b), 0, 255);
                d[p + 3] = 255;
            }
        }
        ctx.putImageData(img, 0, 0);
        return configureStaticTexture(new THREE.CanvasTexture(c));
    }

    // 1:1 Mini-PC Funkira Chest Emblem ("FUNKIRA / Mein-Seelenfunke")
    function makeChestEmblemTexture() {
        var c = document.createElement('canvas');
        c.width = 768;
        c.height = 384;
        var ctx = c.getContext('2d');
        ctx.clearRect(0, 0, 768, 384);
        ctx.save();
        ctx.scale(2, 2);

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

        var txtGrad = ctx.createLinearGradient(0, 52, 0, 102);
        txtGrad.addColorStop(0, '#fff4f8');
        txtGrad.addColorStop(0.55, '#ffd2e1');
        txtGrad.addColorStop(1, '#e88ca6');
        ctx.fillStyle = txtGrad;
        ctx.font = '900 50px sans-serif';
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
        return configureStaticTexture(new THREE.CanvasTexture(c));
    }

    // 1:1 Mini-PC Funkira "★ BOSS" Left-Forearm Shield Badge
    function makeBossBadgeTexture() {
        var c = document.createElement('canvas');
        c.width = 384;
        c.height = 384;
        var ctx = c.getContext('2d');
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
        var shGrad = ctx.createLinearGradient(0, 20, 0, 178);
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
        ctx.font = '900 34px sans-serif';
        ctx.fillText('BOSS', 96, 98);

        ctx.strokeStyle = '#e7a1b4';
        ctx.lineWidth = 5.5;
        ctx.beginPath();
        ctx.moveTo(58, 122);
        ctx.lineTo(96, 143);
        ctx.lineTo(134, 122);
        ctx.stroke();
        ctx.restore();
        return configureStaticTexture(new THREE.CanvasTexture(c));
    }

    function makeStudioFloorDiscTexture() {
        var c = document.createElement('canvas');
        c.width = 512;
        c.height = 512;
        var ctx = c.getContext('2d');
        ctx.clearRect(0, 0, 512, 512);

        var g = ctx.createRadialGradient(256, 256, 14, 256, 256, 248);
        g.addColorStop(0.0, 'rgba(0, 0, 0, 0.82)');
        g.addColorStop(0.38, 'rgba(30, 18, 26, 0.56)');
        g.addColorStop(0.72, 'rgba(226, 134, 157, 0.14)');
        g.addColorStop(1.0, 'rgba(8, 10, 16, 0.0)');
        ctx.fillStyle = g;
        ctx.beginPath();
        ctx.arc(256, 256, 248, 0, Math.PI * 2);
        ctx.fill();

        ctx.strokeStyle = 'rgba(226, 134, 157, 0.46)';
        ctx.lineWidth = 3.8;
        ctx.beginPath();
        ctx.arc(256, 256, 186, 0, Math.PI * 2);
        ctx.stroke();

        ctx.strokeStyle = 'rgba(197, 160, 89, 0.34)';
        ctx.lineWidth = 2.8;
        ctx.beginPath();
        ctx.arc(256, 256, 218, 0, Math.PI * 2);
        ctx.stroke();

        return configureStaticTexture(new THREE.CanvasTexture(c));
    }

    function makeStudioBackdropTexture() {
        var c = document.createElement('canvas');
        c.width = 768;
        c.height = 768;
        var ctx = c.getContext('2d');
        ctx.clearRect(0, 0, 768, 768);

        var mainHalo = ctx.createRadialGradient(384, 360, 20, 384, 360, 365);
        mainHalo.addColorStop(0.0, 'rgba(226, 134, 157, 0.20)');
        mainHalo.addColorStop(0.42, 'rgba(197, 160, 89, 0.09)');
        mainHalo.addColorStop(0.75, 'rgba(90, 48, 66, 0.03)');
        mainHalo.addColorStop(1.0, 'rgba(8, 10, 16, 0.0)');
        ctx.fillStyle = mainHalo;
        ctx.fillRect(0, 0, 768, 768);

        var bokehSpots = [
            [180, 220, 52, 0.09],
            [592, 208, 62, 0.08],
            [144, 486, 46, 0.07],
            [616, 468, 50, 0.07],
            [384, 146, 40, 0.08]
        ];
        for (var i = 0; i < bokehSpots.length; i++) {
            var b = bokehSpots[i];
            var bg = ctx.createRadialGradient(b[0], b[1], 2, b[0], b[1], b[2]);
            bg.addColorStop(0.0, 'rgba(255, 224, 236, ' + b[3] + ')');
            bg.addColorStop(0.65, 'rgba(197, 160, 89, ' + (b[3] * 0.45).toFixed(3) + ')');
            bg.addColorStop(1.0, 'rgba(197, 160, 89, 0)');
            ctx.fillStyle = bg;
            ctx.beginPath();
            ctx.arc(b[0], b[1], b[2], 0, Math.PI * 2);
            ctx.fill();
        }

        return configureStaticTexture(new THREE.CanvasTexture(c));
    }

    function makeSparkSpriteTexture() {
        var c = document.createElement('canvas');
        c.width = 64;
        c.height = 64;
        var ctx = c.getContext('2d');
        var g = ctx.createRadialGradient(32, 32, 1, 32, 32, 30);
        g.addColorStop(0.0, 'rgba(255, 244, 248, 1.0)');
        g.addColorStop(0.40, 'rgba(232, 152, 174, 0.68)');
        g.addColorStop(1.0, 'rgba(197, 160, 89, 0.0)');
        ctx.fillStyle = g;
        ctx.fillRect(0, 0, 64, 64);
        return configureFastTexture(new THREE.CanvasTexture(c));
    }

    // 1:1 Mini-PC Emotion Presets (mapped cleanly to all mobile & web emotion keys)
    var EMOTION_PRESETS = {
        idle:      { lidTop: 0.03, lidBot: 0.02, browTilt: -0.04, browY: 0.0,  mouthCurve: 0.34, mouthOpen: 0.04, pupilScale: 1.02, blush: 0.38, headRoll: 0.00,  headPitch: 0.00  },
        neutral:   { lidTop: 0.03, lidBot: 0.02, browTilt: 0.00,  browY: 0.0,  mouthCurve: 0.28, mouthOpen: 0.04, pupilScale: 1.00, blush: 0.36, headRoll: 0.00,  headPitch: 0.00  },
        listening: { lidTop: 0.00, lidBot: 0.06, browTilt: -0.14, browY: -3.2, mouthCurve: 0.38, mouthOpen: 0.06, pupilScale: 1.08, blush: 0.48, headRoll: 0.06,  headPitch: 0.03  },
        thinking:  { lidTop: 0.06, lidBot: 0.08, browTilt: -0.16, browY: -3.6, mouthCurve: 0.22, mouthOpen: 0.08, pupilScale: 1.06, blush: 0.42, headRoll: 0.09,  headPitch: 0.03  },
        speaking:  { lidTop: 0.04, lidBot: 0.10, browTilt: -0.10, browY: -2.4, mouthCurve: 0.56, mouthOpen: 0.28, pupilScale: 1.05, blush: 0.54, headRoll: 0.03,  headPitch: -0.02 },
        happy:     { lidTop: 0.06, lidBot: 0.20, browTilt: -0.12, browY: -3.0, mouthCurve: 0.72, mouthOpen: 0.22, pupilScale: 1.08, blush: 0.68, headRoll: 0.05,  headPitch: -0.03 },
        proud:     { lidTop: 0.12, lidBot: 0.22, browTilt: -0.08, browY: -2.2, mouthCurve: 0.64, mouthOpen: 0.14, pupilScale: 1.04, blush: 0.56, headRoll: -0.05, headPitch: -0.05 },
        focused:   { lidTop: 0.10, lidBot: 0.10, browTilt: 0.14,  browY: 1.4,  mouthCurve: 0.20, mouthOpen: 0.04, pupilScale: 0.98, blush: 0.34, headRoll: 0.00,  headPitch: 0.04  },
        sweet:     { lidTop: 0.10, lidBot: 0.26, browTilt: -0.22, browY: -2.4, mouthCurve: 0.58, mouthOpen: 0.12, pupilScale: 1.14, blush: 0.88, headRoll: 0.10,  headPitch: 0.03  },
        excited:   { lidTop: 0.00, lidBot: 0.16, browTilt: -0.18, browY: -4.6, mouthCurve: 0.86, mouthOpen: 0.44, pupilScale: 1.15, blush: 0.84, headRoll: -0.05, headPitch: -0.04 }
    };

    var state = {
        mode: 'idle',
        audioLevel: 0.0,
        emotion: 'idle',
        isDark: true,
        cameraActive: false
    };

    var canvas = document.getElementById('falcon-canvas') || document.getElementById('funkira-canvas');
    if (!canvas) return;

    var renderer;
    try {
        renderer = new THREE.WebGLRenderer({
            canvas: canvas,
            antialias: true,
            alpha: false,
            powerPreference: 'high-performance'
        });
    } catch (e) {
        return;
    }

    if ('outputColorSpace' in renderer && THREE.SRGBColorSpace) {
        renderer.outputColorSpace = THREE.SRGBColorSpace;
    } else if ('outputEncoding' in renderer && THREE.sRGBEncoding) {
        renderer.outputEncoding = THREE.sRGBEncoding;
    }
    renderer.setClearColor(0x080A10, 1.0);
    maxAnisotropy = Math.min(8, Math.max(1, (renderer.capabilities && renderer.capabilities.getMaxAnisotropy) ? renderer.capabilities.getMaxAnisotropy() : 4));

    var scene = new THREE.Scene();
    scene.background = new THREE.Color(0x080A10);

    var camera = new THREE.PerspectiveCamera(38, 9 / 20, 0.12, 18);
    var userZoom = 1.0;

    // 1:1 Mini-PC Funkira Shared MatCap Materials (zero dynamic GPU lights -> locked 60 FPS!)
    var mats = {
        roseGold:     new THREE.MeshMatcapMaterial({ matcap: makeStudioMatcapTexture('#e2869d', '#fff8fb', '#ffbdd0', 28, 0.44) }),
        darkArmor:    new THREE.MeshMatcapMaterial({ matcap: makeStudioMatcapTexture('#1d1721', '#8e6c7c', '#e490a6', 22, 0.35) }),
        jointDark:    new THREE.MeshMatcapMaterial({ matcap: makeStudioMatcapTexture('#110d14', '#4e3e49', '#7a5c6b', 16, 0.20) }),
        gold24k:      new THREE.MeshMatcapMaterial({ matcap: makeStudioMatcapTexture('#c89f4d', '#fffbe6', '#f7d88b', 32, 0.48) }),
        obsidian:     new THREE.MeshMatcapMaterial({ matcap: makeStudioMatcapTexture('#16141c', '#544e63', '#C5A059', 18, 0.18) }),
        scarfRed:     new THREE.MeshMatcapMaterial({ matcap: makeStudioMatcapTexture('#b52648', '#ed6888', '#ffa1b6', 10, 0.22) }),
        warmBulb:     new THREE.MeshBasicMaterial({ color: 0xFFE9AD }),
        sirenMat:     new THREE.MeshBasicMaterial({ color: 0xe8829b }),
        crystalGlass: new THREE.MeshBasicMaterial({ color: 0xffe6f0, transparent: true, opacity: 0.34, depthWrite: false })
    };

    // Pure Studio Stage (no 3D house world around Funkira)
    var floorDisc = new THREE.Mesh(
        new THREE.PlaneGeometry(2.10, 2.10),
        new THREE.MeshBasicMaterial({
            map: makeStudioFloorDiscTexture(),
            transparent: true,
            depthWrite: false,
            polygonOffset: true,
            polygonOffsetFactor: -1,
            polygonOffsetUnits: -1
        })
    );
    floorDisc.rotation.x = -Math.PI / 2;
    floorDisc.position.y = 0.008;
    scene.add(floorDisc);

    var haloPlane = new THREE.Mesh(
        new THREE.PlaneGeometry(2.95, 2.95),
        new THREE.MeshBasicMaterial({ map: makeStudioBackdropTexture(), transparent: true, depthWrite: false })
    );
    haloPlane.position.set(0, 1.02, -0.82);
    scene.add(haloPlane);

    var SPARK_COUNT = 24;
    var sparkPositions = new Float32Array(SPARK_COUNT * 3);
    var sparkSpeeds = new Float32Array(SPARK_COUNT);
    var sparkPhase = new Float32Array(SPARK_COUNT);
    for (var si = 0; si < SPARK_COUNT; si++) {
        sparkPositions[si * 3]     = (Math.random() - 0.5) * 1.95;
        sparkPositions[si * 3 + 1] = 0.12 + Math.random() * 1.85;
        sparkPositions[si * 3 + 2] = -0.22 - Math.random() * 0.70;
        sparkSpeeds[si] = 0.042 + Math.random() * 0.060;
        sparkPhase[si] = Math.random() * Math.PI * 2;
    }
    var sparkGeo = new THREE.BufferGeometry();
    sparkGeo.setAttribute('position', new THREE.BufferAttribute(sparkPositions, 3));
    var sparkPoints = new THREE.Points(
        sparkGeo,
        new THREE.PointsMaterial({
            map: makeSparkSpriteTexture(),
            size: 0.044,
            transparent: true,
            opacity: 0.58,
            depthWrite: false,
            blending: THREE.AdditiveBlending
        })
    );
    scene.add(sparkPoints);

    // ========================================================================
    // 1:1 MINI-PC PRE-BAKED 2X RETINA VISOR BACKDROP, CRYSTAL IRIS & BLUSH
    // ========================================================================
    var visorBgCanvas = document.createElement('canvas');
    visorBgCanvas.width = 768;
    visorBgCanvas.height = 480;
    (function bakeVisorBg() {
        var bctx = visorBgCanvas.getContext('2d', { alpha: false });
        bctx.save();
        bctx.scale(2, 2);

        var bgGrad = bctx.createRadialGradient(192, 118, 20, 192, 120, 235);
        bgGrad.addColorStop(0, '#1a1520');
        bgGrad.addColorStop(0.68, '#130f17');
        bgGrad.addColorStop(1, '#0b080e');
        bctx.fillStyle = bgGrad;
        bctx.fillRect(0, 0, 384, 240);

        bctx.fillStyle = 'rgba(255, 210, 228, 0.014)';
        for (var y = 18; y < 232; y += 4) {
            bctx.fillRect(8, y, 368, 1);
        }

        var topGrad = bctx.createLinearGradient(0, 0, 384, 0);
        topGrad.addColorStop(0, '#c46f84');
        topGrad.addColorStop(0.35, '#f6b4c6');
        topGrad.addColorStop(0.5, '#ffe4ec');
        topGrad.addColorStop(0.65, '#f5b2c4');
        topGrad.addColorStop(1, '#b86277');
        bctx.fillStyle = topGrad;
        bctx.fillRect(8, 8.5, 368, 7.5);

        bctx.fillStyle = 'rgba(218, 178, 102, 0.52)';
        bctx.fillRect(14, 17.5, 356, 1.2);

        bctx.beginPath();
        bctx.moveTo(0, 18);
        bctx.lineTo(192, 18);
        bctx.lineTo(116, 240);
        bctx.lineTo(0, 240);
        bctx.closePath();
        var sheen = bctx.createLinearGradient(0, 18, 165, 240);
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
    })();

    var irisSpriteCanvas = document.createElement('canvas');
    irisSpriteCanvas.width = 192;
    irisSpriteCanvas.height = 192;
    (function bakeCrystalIris() {
        var ictx = irisSpriteCanvas.getContext('2d');
        ictx.save();
        ictx.scale(2, 2);

        ictx.fillStyle = '#1d050c';
        ictx.beginPath();
        ictx.ellipse(48, 48, 40.2, 42.2, 0, 0, Math.PI * 2);
        ictx.fill();

        var ig = ictx.createRadialGradient(48, 59, 5, 48, 48, 42);
        ig.addColorStop(0, '#ff9ebc');
        ig.addColorStop(0.28, '#df4b78');
        ig.addColorStop(0.62, '#8c1d3e');
        ig.addColorStop(0.88, '#3d0919');
        ig.addColorStop(1, '#1e040c');
        ictx.fillStyle = ig;
        ictx.beginPath();
        ictx.ellipse(48, 48, 38.6, 40.6, 0, 0, Math.PI * 2);
        ictx.fill();

        for (var f = 0; f < 28; f++) {
            var ang = (f / 28) * Math.PI * 2;
            var rIn = 19.5 + (f % 2) * 2.0;
            var rOut = 36.2 - (f % 3) * 1.8;
            ictx.strokeStyle = (f % 4 === 0)
                ? 'rgba(255, 222, 168, 0.30)'
                : 'rgba(255, 162, 192, 0.26)';
            ictx.lineWidth = (f % 2 === 0) ? 1.35 : 0.85;
            ictx.beginPath();
            ictx.moveTo(48 + Math.cos(ang) * rIn, 47 + Math.sin(ang) * rIn);
            ictx.lineTo(48 + Math.cos(ang) * rOut, 48 + Math.sin(ang) * rOut);
            ictx.stroke();
        }

        var topShad = ictx.createLinearGradient(0, 7, 0, 42);
        topShad.addColorStop(0, 'rgba(18, 3, 9, 0.62)');
        topShad.addColorStop(1, 'rgba(18, 3, 9, 0)');
        ictx.fillStyle = topShad;
        ictx.beginPath();
        ictx.ellipse(48, 48, 38.6, 40.6, 0, 0, Math.PI * 2);
        ictx.fill();

        ictx.strokeStyle = 'rgba(255, 196, 212, 0.44)';
        ictx.lineWidth = 1.4;
        ictx.fillStyle = '#18030a';
        ictx.beginPath();
        ictx.ellipse(48, 46, 19.6, 21.6, 0, 0, Math.PI * 2);
        ictx.fill();
        ictx.stroke();

        var caustGrad = ictx.createLinearGradient(22, 68, 74, 68);
        caustGrad.addColorStop(0, 'rgba(255, 168, 195, 0.45)');
        caustGrad.addColorStop(0.5, 'rgba(255, 226, 190, 0.88)');
        caustGrad.addColorStop(1, 'rgba(255, 168, 195, 0.45)');
        ictx.strokeStyle = caustGrad;
        ictx.lineWidth = 3.4;
        ictx.lineCap = 'round';
        ictx.beginPath();
        ictx.arc(48, 50.5, 26.2, 0.20 * Math.PI, 0.80 * Math.PI);
        ictx.stroke();

        ictx.fillStyle = '#ffffff';
        ictx.beginPath();
        ictx.ellipse(35.5, 33.5, 10.6, 12.2, -0.22, 0, Math.PI * 2);
        ictx.fill();

        ictx.fillStyle = 'rgba(255, 244, 248, 0.96)';
        ictx.beginPath();
        ictx.arc(61.5, 59.5, 4.9, 0, Math.PI * 2);
        ictx.fill();

        ictx.fillStyle = 'rgba(255, 246, 218, 0.92)';
        ictx.beginPath();
        for (var k = 0; k < 8; k++) {
            var ka = (k / 8) * Math.PI * 2;
            var kr = (k % 2 === 0) ? 4.2 : 1.3;
            var kx = 63.0 + Math.cos(ka) * kr;
            var ky = 32.0 + Math.sin(ka) * kr;
            if (k === 0) ictx.moveTo(kx, ky); else ictx.lineTo(kx, ky);
        }
        ictx.closePath();
        ictx.fill();
        ictx.restore();
    })();

    var blushSpriteCanvas = document.createElement('canvas');
    blushSpriteCanvas.width = 128;
    blushSpriteCanvas.height = 80;
    (function bakeBlushSprite() {
        var blctx = blushSpriteCanvas.getContext('2d');
        blctx.save();
        blctx.scale(2, 2);
        var blushGrad = blctx.createRadialGradient(32, 20, 2, 32, 20, 27);
        blushGrad.addColorStop(0, 'rgba(242, 92, 130, 0.48)');
        blushGrad.addColorStop(0.6, 'rgba(235, 84, 122, 0.20)');
        blushGrad.addColorStop(1, 'rgba(235, 84, 122, 0)');
        blctx.fillStyle = blushGrad;
        blctx.fillRect(0, 0, 64, 40);

        blctx.strokeStyle = 'rgba(245, 110, 144, 0.88)';
        blctx.lineWidth = 2.1;
        blctx.lineCap = 'round';
        for (var s = -2; s <= 2; s++) {
            blctx.beginPath();
            blctx.moveTo(32 + s * 6.8 - 2.8, 24.5);
            blctx.lineTo(32 + s * 6.8 + 2.8, 15.5);
            blctx.stroke();
        }
        blctx.restore();
    })();

    var visorCanvas = document.createElement('canvas');
    visorCanvas.width = 768;
    visorCanvas.height = 480;
    var visorCtx = visorCanvas.getContext('2d', { alpha: false });
    var visorTexture = configureFastTexture(new THREE.CanvasTexture(visorCanvas));

    // ========================================================================
    // 1:1 MINI-PC CHIBI FUNKIRA RIG (_buildFunkiraRig WITHOUT 3D HOUSE WORLD)
    // ========================================================================
    var rig = {};

    var root = new THREE.Group();
    scene.add(root);
    rig.root = root;

    var pelvis = new THREE.Group();
    pelvis.position.y = 0.50;
    root.add(pelvis);
    rig.pelvis = pelvis;

    var hipPoints = [
        new THREE.Vector2(0.00, -0.09),
        new THREE.Vector2(0.17, -0.08),
        new THREE.Vector2(0.24, -0.01),
        new THREE.Vector2(0.225, 0.08),
        new THREE.Vector2(0.00, 0.08)
    ];
    var hipMesh = new THREE.Mesh(new THREE.LatheGeometry(hipPoints, 28), mats.darkArmor);
    hipMesh.scale.set(1.04, 1.0, 0.86);
    pelvis.add(hipMesh);

    var waistRing = new THREE.Mesh(new THREE.TorusGeometry(0.232, 0.025, 14, 32), mats.roseGold);
    waistRing.rotation.x = Math.PI / 2;
    waistRing.position.y = 0.072;
    var beltBuckleBack = new THREE.Mesh(createRoundedBoxGeometry(0.088, 0.052, 0.024, 2, 0.010), mats.obsidian);
    beltBuckleBack.position.set(0, 0.072, 0.206);
    var beltBuckleRim = new THREE.Mesh(createRoundedBoxGeometry(0.096, 0.058, 0.018, 2, 0.010), mats.gold24k);
    beltBuckleRim.position.set(0, 0.072, 0.203);
    var beltStarGem = new THREE.Mesh(new THREE.OctahedronGeometry(0.020, 0), mats.gold24k);
    beltStarGem.scale.set(1.0, 1.25, 0.45);
    beltStarGem.position.set(0, 0.072, 0.218);
    pelvis.add(waistRing, beltBuckleRim, beltBuckleBack, beltStarGem);

    var thighGeo = new THREE.CylinderGeometry(0.068, 0.058, 0.20, 22);
    var kneeGeo = new THREE.SphereGeometry(0.058, 16, 12);
    var kneeRingGeo = new THREE.TorusGeometry(0.048, 0.007, 8, 20);
    var shinGeo = new THREE.CylinderGeometry(0.062, 0.076, 0.19, 22);
    var cuffGeo = new THREE.TorusGeometry(0.078, 0.017, 12, 28);
    var bootUpperGeo = createRoundedBoxGeometry(0.165, 0.115, 0.235, 3, 0.048);
    var bootWeltGeo = createRoundedBoxGeometry(0.170, 0.013, 0.242, 2, 0.014);
    var bootSoleGeo = createRoundedBoxGeometry(0.172, 0.034, 0.245, 2, 0.014);
    var bootBuckleGeo = createRoundedBoxGeometry(0.066, 0.020, 0.016, 1, 0.005);

    function buildLeg(sideSign) {
        var hipPivot = new THREE.Group();
        hipPivot.position.set(sideSign * 0.115, -0.04, 0);
        pelvis.add(hipPivot);

        var thigh = new THREE.Mesh(thighGeo, mats.darkArmor);
        thigh.position.y = -0.10;
        var kneeJoint = new THREE.Mesh(kneeGeo, mats.roseGold);
        kneeJoint.position.y = -0.20;
        var kneeGoldRing = new THREE.Mesh(kneeRingGeo, mats.gold24k);
        kneeGoldRing.position.set(0, -0.20, 0.022);
        hipPivot.add(thigh, kneeJoint, kneeGoldRing);

        var kneePivot = new THREE.Group();
        kneePivot.position.y = -0.20;
        hipPivot.add(kneePivot);

        var shin = new THREE.Mesh(shinGeo, mats.darkArmor);
        shin.position.y = -0.095;
        var bootCuff = new THREE.Mesh(cuffGeo, mats.roseGold);
        bootCuff.rotation.x = Math.PI / 2;
        bootCuff.position.y = -0.168;
        var bootBuckle = new THREE.Mesh(bootBuckleGeo, mats.gold24k);
        bootBuckle.position.set(0, -0.170, 0.096);
        var bootUpper = new THREE.Mesh(bootUpperGeo, mats.roseGold);
        bootUpper.position.set(0, -0.215, 0.032);
        var bootWelt = new THREE.Mesh(bootWeltGeo, mats.gold24k);
        bootWelt.position.set(0, -0.252, 0.032);
        var bootSole = new THREE.Mesh(bootSoleGeo, mats.jointDark);
        bootSole.position.set(0, -0.268, 0.032);
        kneePivot.add(shin, bootCuff, bootBuckle, bootUpper, bootWelt, bootSole);

        return { hipPivot: hipPivot, kneePivot: kneePivot };
    }
    rig.legL = buildLeg(-1);
    rig.legR = buildLeg(1);

    var torsoPivot = new THREE.Group();
    torsoPivot.position.y = 0.08;
    pelvis.add(torsoPivot);
    rig.torso = torsoPivot;

    var torsoPoints = [
        new THREE.Vector2(0.00, 0.00),
        new THREE.Vector2(0.222, 0.00),
        new THREE.Vector2(0.242, 0.12),
        new THREE.Vector2(0.268, 0.28),
        new THREE.Vector2(0.252, 0.39),
        new THREE.Vector2(0.185, 0.43),
        new THREE.Vector2(0.00, 0.43)
    ];
    var chestMesh = new THREE.Mesh(new THREE.LatheGeometry(torsoPoints, 32), mats.darkArmor);
    chestMesh.scale.set(1.08, 1.0, 0.84);
    torsoPivot.add(chestMesh);

    var emblemGeo = new THREE.CylinderGeometry(0.268, 0.242, 0.26, 32, 1, true, -0.78, 1.56);
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
    emblemMesh.scale.set(1.115, 1.0, 0.885);
    emblemMesh.position.y = 0.22;
    emblemMesh.renderOrder = 2;
    torsoPivot.add(emblemMesh);

    // Rear Seelenfunke Power-Core Medallion (visible when rotating Funkira 360°)
    var backCoreRing = new THREE.Mesh(new THREE.TorusGeometry(0.068, 0.011, 10, 24), mats.gold24k);
    backCoreRing.position.set(0, 0.25, -0.218);
    var backCorePlate = new THREE.Mesh(new THREE.CylinderGeometry(0.058, 0.058, 0.014, 20), mats.obsidian);
    backCorePlate.rotation.x = Math.PI / 2;
    backCorePlate.position.set(0, 0.25, -0.216);
    var backCoreStar = new THREE.Mesh(new THREE.OctahedronGeometry(0.026, 0), mats.warmBulb);
    backCoreStar.scale.set(1.0, 1.25, 0.35);
    backCoreStar.position.set(0, 0.25, -0.224);
    torsoPivot.add(backCoreRing, backCorePlate, backCoreStar);

    var collarRing = new THREE.Mesh(new THREE.TorusGeometry(0.186, 0.036, 14, 32), mats.roseGold);
    collarRing.rotation.x = Math.PI / 2;
    collarRing.position.y = 0.432;
    var collarGoldTrim = new THREE.Mesh(new THREE.TorusGeometry(0.194, 0.009, 10, 32), mats.gold24k);
    collarGoldTrim.rotation.x = Math.PI / 2;
    collarGoldTrim.position.y = 0.408;
    var collarBrooch = new THREE.Mesh(new THREE.OctahedronGeometry(0.024, 0), mats.gold24k);
    collarBrooch.scale.set(1.0, 1.3, 0.45);
    collarBrooch.position.set(0, 0.422, 0.206);
    var neckCyl = new THREE.Mesh(new THREE.CylinderGeometry(0.10, 0.12, 0.11, 20), mats.jointDark);
    neckCyl.position.y = 0.47;
    torsoPivot.add(collarRing, collarGoldTrim, collarBrooch, neckCyl);

    // Cozy Signature Scarf
    var scarfGroup = new THREE.Group();
    var scarfRing = new THREE.Mesh(new THREE.TorusGeometry(0.205, 0.052, 14, 30), mats.scarfRed);
    scarfRing.rotation.x = Math.PI / 2;
    scarfRing.position.y = 0.43;
    var scarfTail = new THREE.Mesh(createRoundedBoxGeometry(0.095, 0.26, 0.038, 2, 0.014), mats.scarfRed);
    scarfTail.position.set(0.11, 0.30, 0.215);
    scarfTail.rotation.z = 0.14;
    var scarfGoldTip = new THREE.Mesh(createRoundedBoxGeometry(0.098, 0.022, 0.040, 1, 0.006), mats.gold24k);
    scarfGoldTip.position.set(0.093, 0.175, 0.216);
    scarfGoldTip.rotation.z = 0.14;
    scarfGroup.add(scarfRing, scarfTail, scarfGoldTip);
    torsoPivot.add(scarfGroup);

    // Capsule-equivalent finger segment helper (compatible with Three.js r128..r170+)
    function makeCapsuleSegment(radius, length, mat) {
        var g = new THREE.Group();
        var cyl = new THREE.Mesh(new THREE.CylinderGeometry(radius, radius, length, 10), mat);
        var capTop = new THREE.Mesh(new THREE.SphereGeometry(radius, 10, 8), mat);
        capTop.position.y = length * 0.5;
        var capBot = new THREE.Mesh(new THREE.SphereGeometry(radius, 10, 8), mat);
        capBot.position.y = -length * 0.5;
        g.add(cyl, capTop, capBot);
        return g;
    }

    var shCuffGeo = new THREE.TorusGeometry(0.092, 0.023, 12, 26);
    var shGoldRimGeo = new THREE.TorusGeometry(0.098, 0.008, 10, 26);
    var shPauldronGeo = new THREE.SphereGeometry(0.096, 18, 12, 0, Math.PI * 2, 0, Math.PI * 0.48);
    var shBallGeo = new THREE.SphereGeometry(0.074, 16, 14);
    var upperArmGeo = new THREE.CylinderGeometry(0.068, 0.060, 0.21, 22);
    var upTrimGeo = new THREE.TorusGeometry(0.064, 0.011, 10, 24);
    var elBallGeo = new THREE.SphereGeometry(0.058, 14, 12);
    var forearmGeo = new THREE.CylinderGeometry(0.062, 0.075, 0.21, 22);
    var wrCuffGeo = new THREE.TorusGeometry(0.073, 0.016, 12, 24);
    var wrGoldGeo = new THREE.TorusGeometry(0.077, 0.006, 8, 24);
    var palmGeo = new THREE.SphereGeometry(0.075, 16, 14);
    var knuckleRingGeo = new THREE.TorusGeometry(0.0148, 0.0032, 6, 14);

    function buildArm(sideSign, hasBossShield) {
        var cuffHolder = new THREE.Group();
        cuffHolder.position.set(sideSign * 0.295, 0.345, 0.0);
        torsoPivot.add(cuffHolder);

        var shoulderCuff = new THREE.Mesh(shCuffGeo, mats.roseGold);
        shoulderCuff.rotation.y = sideSign * (Math.PI / 2 - 0.22);
        var shoulderGoldRim = new THREE.Mesh(shGoldRimGeo, mats.gold24k);
        shoulderGoldRim.rotation.y = sideSign * (Math.PI / 2 - 0.22);
        shoulderGoldRim.position.x = sideSign * 0.012;
        var pauldron = new THREE.Mesh(shPauldronGeo, mats.roseGold);
        pauldron.scale.set(1.05, 0.68, 0.96);
        pauldron.position.set(sideSign * 0.018, 0.022, 0);
        pauldron.rotation.z = -sideSign * 0.42;
        cuffHolder.add(shoulderCuff, shoulderGoldRim, pauldron);

        var shoulderPivot = new THREE.Group();
        shoulderPivot.position.set(sideSign * 0.015, -0.005, 0);
        cuffHolder.add(shoulderPivot);

        var shoulderBall = new THREE.Mesh(shBallGeo, mats.jointDark);
        var upperArm = new THREE.Mesh(upperArmGeo, mats.darkArmor);
        upperArm.position.y = -0.11;
        var upperTrim = new THREE.Mesh(upTrimGeo, mats.gold24k);
        upperTrim.rotation.x = Math.PI / 2;
        upperTrim.position.y = -0.12;
        shoulderPivot.add(shoulderBall, upperArm, upperTrim);

        var elbowPivot = new THREE.Group();
        elbowPivot.position.y = -0.215;
        shoulderPivot.add(elbowPivot);

        var elbowBall = new THREE.Mesh(elBallGeo, mats.jointDark);
        var forearm = new THREE.Mesh(forearmGeo, mats.darkArmor);
        forearm.position.y = -0.105;
        elbowPivot.add(elbowBall, forearm);

        if (hasBossShield) {
            var badgePlane = new THREE.Mesh(
                new THREE.PlaneGeometry(0.135, 0.135),
                new THREE.MeshBasicMaterial({
                    map: makeBossBadgeTexture(),
                    transparent: true,
                    depthWrite: false,
                    side: THREE.DoubleSide,
                    polygonOffset: true,
                    polygonOffsetFactor: -2,
                    polygonOffsetUnits: -2
                })
            );
            badgePlane.position.set(sideSign * 0.052, -0.105, 0.049);
            badgePlane.rotation.y = sideSign * 0.72;
            badgePlane.renderOrder = 2;
            elbowPivot.add(badgePlane);
        }

        var wristCuff = new THREE.Mesh(wrCuffGeo, mats.roseGold);
        wristCuff.rotation.x = Math.PI / 2;
        wristCuff.position.y = -0.205;
        var wristGold = new THREE.Mesh(wrGoldGeo, mats.gold24k);
        wristGold.rotation.x = Math.PI / 2;
        wristGold.position.y = -0.192;
        elbowPivot.add(wristCuff, wristGold);

        var wristPivot = new THREE.Group();
        wristPivot.position.y = -0.235;
        elbowPivot.add(wristPivot);

        var palm = new THREE.Mesh(palmGeo, mats.roseGold);
        palm.scale.set(1.02, 0.92, 0.74);
        palm.position.y = -0.032;
        var palmEmblem = new THREE.Mesh(new THREE.OctahedronGeometry(0.015, 0), mats.gold24k);
        palmEmblem.scale.set(1.0, 1.2, 0.35);
        palmEmblem.position.set(0, -0.030, -0.054);
        wristPivot.add(palm, palmEmblem);

        var fingers = [];
        var fingerOffsets = [-0.046, -0.016, 0.015, 0.045];
        var fingerScales = [0.88, 1.0, 0.98, 0.82];
        for (var f = 0; f < 4; f++) {
            var fp = new THREE.Group();
            fp.position.set(fingerOffsets[f], -0.080, 0.008);
            var sc = fingerScales[f];
            var prox = makeCapsuleSegment(0.0145, 0.016, mats.roseGold);
            prox.scale.y = sc;
            prox.position.y = -0.012 * sc;
            var kRing = new THREE.Mesh(knuckleRingGeo, mats.gold24k);
            kRing.rotation.x = Math.PI / 2;
            kRing.position.y = -0.023 * sc;
            var dist = makeCapsuleSegment(0.0125, 0.014, mats.roseGold);
            dist.scale.y = sc;
            dist.position.set(0, -0.034 * sc, 0.002);
            dist.rotation.x = -0.10;
            fp.add(prox, kRing, dist);
            wristPivot.add(fp);
            fingers.push(fp);
        }

        var thumbPivot = new THREE.Group();
        thumbPivot.position.set(-sideSign * 0.062, -0.038, 0.022);
        thumbPivot.rotation.z = -sideSign * 0.62;
        var thumbProx = makeCapsuleSegment(0.0145, 0.016, mats.roseGold);
        thumbProx.position.y = -0.012;
        var thumbRing = new THREE.Mesh(knuckleRingGeo, mats.gold24k);
        thumbRing.rotation.x = Math.PI / 2;
        thumbRing.position.y = -0.022;
        var thumbDist = makeCapsuleSegment(0.0125, 0.014, mats.roseGold);
        thumbDist.position.y = -0.032;
        thumbPivot.add(thumbProx, thumbRing, thumbDist);
        wristPivot.add(thumbPivot);

        return { shoulderPivot: shoulderPivot, elbowPivot: elbowPivot, wristPivot: wristPivot, fingers: fingers };
    }

    rig.armR = buildArm(-1, false);
    rig.armL = buildArm(1, true);

    // Rose-Gold Head Shell, Dual Bezel, Visor, Crystal Siren Dome & Side Ear Pods
    var headPivot = new THREE.Group();
    headPivot.position.y = 0.52;
    torsoPivot.add(headPivot);
    rig.head = headPivot;

    var headW = 0.78;
    var headH = 0.54;
    var headD = 0.38;
    var headGeo = new THREE.ExtrudeGeometry(createRoundedRectShape(headW, headH, 0.11), {
        depth: headD,
        curveSegments: 12,
        bevelEnabled: true,
        bevelThickness: 0.038,
        bevelSize: 0.032,
        bevelSegments: 5
    });
    headGeo.center();
    var headShell = new THREE.Mesh(headGeo, mats.roseGold);
    headShell.position.y = 0.26;
    headPivot.add(headShell);

    var bezelFrame = new THREE.Mesh(createRoundedBoxGeometry(0.718, 0.472, 0.016, 3, 0.046), mats.darkArmor);
    bezelFrame.position.set(0, 0.26, headD * 0.5 + 0.032);
    var goldInnerBezel = new THREE.Mesh(createRoundedBoxGeometry(0.698, 0.452, 0.012, 3, 0.042), mats.gold24k);
    goldInnerBezel.position.set(0, 0.26, headD * 0.5 + 0.037);
    headPivot.add(bezelFrame, goldInnerBezel);

    var visorPlane = new THREE.Mesh(
        new THREE.PlaneGeometry(0.688, 0.442),
        new THREE.MeshBasicMaterial({
            map: visorTexture,
            polygonOffset: true,
            polygonOffsetFactor: -1,
            polygonOffsetUnits: -1
        })
    );
    visorPlane.position.set(0, 0.26, headD * 0.5 + 0.047);
    headPivot.add(visorPlane);

    var sirenBase = new THREE.Mesh(new THREE.CylinderGeometry(0.116, 0.140, 0.046, 24), mats.roseGold);
    sirenBase.position.set(0, 0.578, 0);
    var sirenGoldCollar = new THREE.Mesh(new THREE.TorusGeometry(0.118, 0.010, 10, 24), mats.gold24k);
    sirenGoldCollar.rotation.x = Math.PI / 2;
    sirenGoldCollar.position.set(0, 0.600, 0);
    var sirenDome = new THREE.Mesh(new THREE.CylinderGeometry(0.084, 0.102, 0.112, 24), mats.sirenMat);
    sirenDome.position.set(0, 0.648, 0);
    var sirenCrystalBell = new THREE.Mesh(new THREE.CylinderGeometry(0.098, 0.116, 0.120, 24), mats.crystalGlass);
    sirenCrystalBell.position.set(0, 0.650, 0);
    var sirenCap = new THREE.Mesh(new THREE.CylinderGeometry(0.052, 0.094, 0.020, 24), mats.gold24k);
    sirenCap.position.set(0, 0.714, 0);
    var sirenStar = new THREE.Mesh(new THREE.OctahedronGeometry(0.018, 0), mats.gold24k);
    sirenStar.position.set(0, 0.734, 0);
    headPivot.add(sirenBase, sirenGoldCollar, sirenDome, sirenCrystalBell, sirenCap, sirenStar);

    var earGeo = new THREE.CylinderGeometry(0.114, 0.102, 0.068, 24);
    var earRingGeo = new THREE.TorusGeometry(0.096, 0.012, 10, 24);
    var earGoldRingGeo = new THREE.TorusGeometry(0.068, 0.008, 10, 24);
    var earCapGeo = new THREE.CylinderGeometry(0.046, 0.046, 0.012, 20);
    for (var es = -1; es <= 1; es += 2) {
        var earPod = new THREE.Mesh(earGeo, mats.roseGold);
        earPod.rotation.z = es * Math.PI / 2;
        earPod.position.set(es * (headW * 0.5 + 0.050), 0.25, 0);
        var earRing = new THREE.Mesh(earRingGeo, mats.darkArmor);
        earRing.rotation.y = Math.PI / 2;
        earRing.position.set(es * (headW * 0.5 + 0.084), 0.25, 0);
        var earGoldRing = new THREE.Mesh(earGoldRingGeo, mats.gold24k);
        earGoldRing.rotation.y = Math.PI / 2;
        earGoldRing.position.set(es * (headW * 0.5 + 0.088), 0.25, 0);
        var earCap = new THREE.Mesh(earCapGeo, mats.roseGold);
        earCap.rotation.z = es * Math.PI / 2;
        earCap.position.set(es * (headW * 0.5 + 0.087), 0.25, 0);
        headPivot.add(earPod, earRing, earGoldRing, earCap);
    }

    // ========================================================================
    // 1:1 MINI-PC RETINA VISOR RENDERER (_renderVisorFace)
    // ========================================================================
    var faceCur = {
        lidTop: 0.03,
        lidBot: 0.02,
        browTilt: -0.04,
        browY: 0.0,
        mouthCurve: 0.34,
        mouthOpen: 0.04,
        pupilScale: 1.02,
        blush: 0.38,
        headRoll: 0.0,
        headPitch: 0.0
    };
    var blinkVal = 0.0;
    var nextBlinkMs = performance.now() + 2400;
    var lookCur = { x: 0, y: 0 };
    var lookTarget = { x: 0, y: 0 };
    var nextSaccadeMs = performance.now() + 1700;

    function resolveActiveEmotionKey() {
        if (state.emotion && EMOTION_PRESETS[state.emotion] && state.emotion !== 'idle') {
            return state.emotion;
        }
        if (state.mode === 'speaking') return 'speaking';
        if (state.mode === 'listening') return 'listening';
        if (state.mode === 'thinking' || state.mode === 'connecting') return 'thinking';
        return (state.emotion && EMOTION_PRESETS[state.emotion]) ? state.emotion : 'idle';
    }

    function renderVisorFace(nowMs, dt) {
        var emoKey = resolveActiveEmotionKey();
        var preset = EMOTION_PRESETS[emoKey] || EMOTION_PRESETS.idle;
        var blend = 1.0 - Math.exp(-dt * 8.5);
        for (var k in preset) {
            faceCur[k] = lerp(faceCur[k], preset[k], blend);
        }

        if (nowMs >= nextBlinkMs) {
            blinkVal = 1.0;
            nextBlinkMs = nowMs + 2600 + Math.random() * 3400;
        }
        if (blinkVal > 0) {
            blinkVal = Math.max(0, blinkVal - dt * 7.8);
        }
        var blinkCurve = blinkVal > 0.001 ? Math.sin(blinkVal * Math.PI) : 0;

        if (nowMs >= nextSaccadeMs) {
            if (state.mode === 'listening' || state.mode === 'speaking') {
                lookTarget.x = (Math.random() - 0.5) * 0.22;
                lookTarget.y = (Math.random() - 0.5) * 0.14;
            } else if (emoKey === 'thinking') {
                lookTarget.x = 0.14;
                lookTarget.y = 0.06;
            } else {
                lookTarget.x = (Math.random() - 0.5) * 0.34;
                lookTarget.y = (Math.random() - 0.5) * 0.18;
            }
            nextSaccadeMs = nowMs + 1500 + Math.random() * 2400;
        }
        lookCur.x = lerp(lookCur.x, lookTarget.x, 1.0 - Math.exp(-dt * 6.2));
        lookCur.y = lerp(lookCur.y, lookTarget.y, 1.0 - Math.exp(-dt * 6.2));

        var isSpeakingNow = state.mode === 'speaking' || (state.audioLevel > 0.04 && state.mode !== 'listening');
        var speechOsc = isSpeakingNow
            ? (0.35 + 0.65 * Math.abs(Math.sin(nowMs * 0.021)) * Math.abs(Math.cos(nowMs * 0.013)))
            : 0.0;
        var amp = clamp(state.audioLevel || 0, 0, 1);
        var voiceOpen = isSpeakingNow ? clamp((0.18 + amp * 1.35) * speechOsc, 0.08, 0.95) : 0.0;
        var openAmt = clamp(Math.max(faceCur.mouthOpen || 0.04, voiceOpen), 0.02, 0.96);
        var curve = faceCur.mouthCurve;
        var lidTop = clamp(faceCur.lidTop + blinkCurve * 0.96, 0, 0.98);
        var lidBot = clamp(faceCur.lidBot, 0, 0.65);
        var gx = lookCur.x * 18.0;
        var gy = lookCur.y * 11.0;

        visorCtx.drawImage(visorBgCanvas, 0, 0);
        visorCtx.save();
        visorCtx.scale(2, 2);

        var eyeY = 114 + gy * 0.72;
        var eyeCenters = [111 + gx * 0.80, 273 + gx * 0.80];
        var rx = 43.5;
        var ry = 45.0;
        var irisSize = 96 * (faceCur.pupilScale || 1.0);

        for (var idx = 0; idx < 2; idx++) {
            var side = idx === 0 ? -1 : 1;
            var ex = eyeCenters[idx];

            visorCtx.save();
            visorCtx.beginPath();
            visorCtx.ellipse(ex, eyeY, rx, ry, 0, 0, Math.PI * 2);
            visorCtx.clip();

            visorCtx.fillStyle = '#1c0c13';
            visorCtx.fillRect(ex - rx - 4, eyeY - ry - 4, rx * 2 + 8, ry * 2 + 8);

            var px = ex + gx * 0.34;
            var py = eyeY + gy * 0.34;
            visorCtx.drawImage(irisSpriteCanvas, px - irisSize * 0.5, py - irisSize * 0.5, irisSize, irisSize);

            if (emoKey === 'happy' || emoKey === 'sweet' || emoKey === 'excited') {
                visorCtx.fillStyle = '#ffe3ec';
                visorCtx.beginPath();
                visorCtx.arc(px + 10, py - 12, 3.2, 0, Math.PI * 2);
                visorCtx.fill();
            }

            if (lidTop > 0.01) {
                visorCtx.fillStyle = '#151218';
                visorCtx.fillRect(ex - rx - 8, eyeY - ry - 10, rx * 2 + 16, ry * 2 * lidTop + 6);
            }
            if (lidBot > 0.01) {
                visorCtx.fillStyle = '#151218';
                var bh = ry * 1.65 * lidBot;
                visorCtx.beginPath();
                visorCtx.ellipse(ex, eyeY + ry + 8 - bh * 0.45, rx + 10, ry * 0.85, 0, Math.PI, Math.PI * 2);
                visorCtx.fill();
            }
            visorCtx.restore();

            visorCtx.strokeStyle = '#151218';
            visorCtx.lineWidth = 7.5;
            visorCtx.beginPath();
            visorCtx.ellipse(ex, eyeY, rx + 3.5, ry + 3.5, 0, 0, Math.PI * 2);
            visorCtx.stroke();

            var archY = eyeY - ry * (1 - lidTop * 1.45);
            visorCtx.strokeStyle = '#e88da3';
            visorCtx.lineWidth = 4.6;
            visorCtx.lineCap = 'round';
            visorCtx.beginPath();
            visorCtx.ellipse(ex, eyeY, rx + 1, ry * Math.max(0.08, 1 - lidTop * 1.1), 0, Math.PI * 1.08, Math.PI * 1.92);
            visorCtx.stroke();

            if (lidTop < 0.82) {
                visorCtx.strokeStyle = '#df8299';
                visorCtx.lineWidth = 4.0;
                for (var l = 0; l < 3; l++) {
                    var lx0 = ex + side * (rx - 4.5 - l * 5.2);
                    var ly0 = archY + 10.5 + l * 4.5;
                    visorCtx.beginPath();
                    visorCtx.moveTo(lx0, ly0);
                    visorCtx.quadraticCurveTo(lx0 + side * 9, ly0 - 4.5, lx0 + side * (14 - l * 1.5), ly0 - (10.5 - l * 2.2));
                    visorCtx.stroke();
                }
            }

            var by = 55 + gy * 0.45 + (faceCur.browY || 0);
            var tilt = (faceCur.browTilt || 0) * side;
            visorCtx.save();
            visorCtx.translate(ex, by);
            visorCtx.rotate(tilt);
            visorCtx.strokeStyle = '#e78ca2';
            visorCtx.lineWidth = 4.6;
            visorCtx.lineCap = 'round';
            visorCtx.beginPath();
            visorCtx.moveTo(-24, 4.5);
            visorCtx.quadraticCurveTo(0, -7.5, 24, 4.5);
            visorCtx.stroke();
            visorCtx.restore();

            var blushAlpha = clamp(faceCur.blush || 0.36, 0, 1);
            if (blushAlpha > 0.08) {
                visorCtx.globalAlpha = blushAlpha;
                visorCtx.drawImage(blushSpriteCanvas, ex + side * 10 - 32, eyeY + 50 - 20, 64, 40);
                visorCtx.globalAlpha = 1.0;
            }
        }

        var mx = 192 + gx * 0.55;
        var my = 170 + gy * 0.55;
        var mw = 25.0 + openAmt * 10.5 + Math.max(0, curve) * 7.5;
        var mh = 4.5 + openAmt * 30.0;

        if (openAmt > 0.13) {
            visorCtx.beginPath();
            visorCtx.moveTo(mx - mw, my - 3);
            visorCtx.quadraticCurveTo(mx, my - 3 + curve * 7.5, mx + mw, my - 3);
            visorCtx.quadraticCurveTo(mx + mw * 0.85, my + mh, mx, my + mh);
            visorCtx.quadraticCurveTo(mx - mw * 0.85, my + mh, mx - mw, my - 3);
            visorCtx.closePath();
            visorCtx.fillStyle = '#471020';
            visorCtx.fill();

            visorCtx.fillStyle = '#e35d7d';
            visorCtx.beginPath();
            visorCtx.ellipse(mx, my + mh * 0.62, mw * 0.52, mh * 0.32, 0, 0, Math.PI * 2);
            visorCtx.fill();

            visorCtx.strokeStyle = '#24060f';
            visorCtx.lineWidth = 2.4;
            visorCtx.stroke();
        } else {
            visorCtx.strokeStyle = '#e88da3';
            visorCtx.lineWidth = 4.0;
            visorCtx.lineCap = 'round';
            visorCtx.beginPath();
            visorCtx.moveTo(mx - mw * 0.85, my);
            visorCtx.quadraticCurveTo(mx, my + curve * 19.0, mx + mw * 0.85, my);
            visorCtx.stroke();
        }

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
        visorTexture.needsUpdate = true;
    }

    // ========================================================================
    // CAMERA FRAMING + 1-FINGER 360° ROTATE + 2-FINGER PINCH ZOOM
    // ========================================================================
    function updateCameraFraming() {
        var tanHalfFov = Math.tan((camera.fov * 0.5) * Math.PI / 180.0);
        // Full chibi character height 0.00m..1.85m centered at y = 0.94m with generous HUD margin
        var distW = 0.84 / Math.max(0.12, tanHalfFov * camera.aspect);
        var distH = 1.12 / tanHalfFov;
        var camDist = Math.max(distW, distH) * userZoom;
        camera.position.set(0, 1.00, camDist);
        camera.lookAt(0, 0.94, 0);
        camera.updateProjectionMatrix();
    }

    function resize() {
        var docEl = document.documentElement;
        var w = Math.max(180, Math.round((docEl && docEl.clientWidth) ? docEl.clientWidth : (window.innerWidth || 412)));
        var h = Math.max(200, Math.round((docEl && docEl.clientHeight) ? docEl.clientHeight : (window.innerHeight || 915)));
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
            userYaw = clamp(userYaw + dx * 0.0085, -Math.PI, Math.PI);
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
        userYaw = clamp(userYaw + dx * 0.0085, -Math.PI, Math.PI);
        userPitch = clamp(userPitch + dy * 0.0035, -0.22, 0.22);
    });
    window.addEventListener('mouseup', function () { dragActive = false; });
    canvas.addEventListener('wheel', function (e) {
        userZoom = clamp(userZoom + (e.deltaY > 0 ? 0.06 : -0.06), 0.72, 1.45);
        updateCameraFraming();
    }, { passive: true });

    // ========================================================================
    // 1:1 MINI-PC CHIBI ARM POSES & 60-FPS ANIMATION LOOP
    // ========================================================================
    function solveArmTargets(gesture, tSec, smoothAmp) {
        var pose = {
            rS: [0.06, 0, -0.20], rE: [-0.32, 0, 0.04], rW: [0, 0, 0], rCurl: 0.25,
            lS: [0.06, 0,  0.20], lE: [-0.32, 0, -0.04], lW: [0, 0, 0], lCurl: 0.25
        };
        var wv = Math.sin(tSec * 6.8);

        if (gesture === 'wave') {
            pose.lS = [-0.62, 0.18, 0.82 + wv * 0.12];
            pose.lE = [-1.42, 0.25, -0.18 + wv * 0.22];
            pose.lW = [0.18, 0, wv * 0.24];
            pose.lCurl = 0.05;
            pose.rS = [0.12, 0, -0.24];
            pose.rE = [-0.36, 0, 0];
            pose.rCurl = 0.28;
        } else if (gesture === 'hands_on_hips') {
            pose.rS = [0.15, 0.25, -0.78];
            pose.rE = [-1.25, -0.35, 0.72];
            pose.lS = [0.15, -0.25, 0.78];
            pose.lE = [-1.25, 0.35, -0.72];
            pose.rCurl = 0.65;
            pose.lCurl = 0.65;
        } else if (gesture === 'hands_on_cheeks') {
            pose.rS = [-0.78, -0.25, -0.52];
            pose.rE = [-1.68, -0.32, 0.38];
            pose.lS = [-0.78, 0.25, 0.52];
            pose.lE = [-1.68, 0.32, -0.38];
            pose.rCurl = 0.15;
            pose.lCurl = 0.15;
        } else if (gesture === 'listening') {
            var att = Math.sin(tSec * 2.1) * 0.03;
            pose.rS = [-0.04, 0.10, -0.28 - att];
            pose.rE = [-0.52, -0.10, 0.16];
            pose.lS = [-0.04, -0.10, 0.28 + att];
            pose.lE = [-0.52, 0.10, -0.16];
            pose.rCurl = 0.22;
            pose.lCurl = 0.22;
        } else if (gesture === 'speaking') {
            var spR = Math.sin(tSec * 5.0) * (0.08 + smoothAmp * 0.14);
            var spL = Math.sin(tSec * 5.0 + 1.1) * (0.08 + smoothAmp * 0.14);
            pose.rS = [-0.14 - Math.abs(spR) * 0.55, 0.08, -0.24 - Math.abs(spR) * 0.20];
            pose.rE = [-0.56 - Math.abs(spR) * 1.25, 0, 0.16];
            pose.rW = [0.10, 0, -spR * 0.30];
            pose.lS = [-0.14 - Math.abs(spL) * 0.55, -0.08, 0.24 + Math.abs(spL) * 0.20];
            pose.lE = [-0.56 - Math.abs(spL) * 1.25, 0, -0.16];
            pose.lW = [0.10, 0, spL * 0.30];
            pose.rCurl = 0.18;
            pose.lCurl = 0.18;
        } else {
            var br = Math.sin(tSec * 1.9) * 0.04;
            pose.rS = [0.06, 0, -0.20 - br];
            pose.rE = [-0.32, 0, 0.04];
            pose.lS = [0.06, 0, 0.20 + br];
            pose.lE = [-0.32, 0, -0.04];
            pose.rCurl = 0.25;
            pose.lCurl = 0.25;
        }
        return pose;
    }

    function applyArmSmoothing(armRig, sTarget, eTarget, wTarget, curl, blend) {
        armRig.shoulderPivot.rotation.x = lerp(armRig.shoulderPivot.rotation.x, sTarget[0], blend);
        armRig.shoulderPivot.rotation.y = lerp(armRig.shoulderPivot.rotation.y, sTarget[1], blend);
        armRig.shoulderPivot.rotation.z = lerp(armRig.shoulderPivot.rotation.z, sTarget[2], blend);

        armRig.elbowPivot.rotation.x = lerp(armRig.elbowPivot.rotation.x, eTarget[0], blend);
        armRig.elbowPivot.rotation.y = lerp(armRig.elbowPivot.rotation.y, eTarget[1], blend);
        armRig.elbowPivot.rotation.z = lerp(armRig.elbowPivot.rotation.z, eTarget[2], blend);

        armRig.wristPivot.rotation.x = lerp(armRig.wristPivot.rotation.x, wTarget[0], blend);
        armRig.wristPivot.rotation.y = lerp(armRig.wristPivot.rotation.y, wTarget[1], blend);
        armRig.wristPivot.rotation.z = lerp(armRig.wristPivot.rotation.z, wTarget[2], blend);

        for (var i = 0; i < armRig.fingers.length; i++) {
            armRig.fingers[i].rotation.x = lerp(armRig.fingers[i].rotation.x, -curl * 1.15, blend);
        }
    }

    function updateSparks(dt, tSec) {
        var posAttr = sparkGeo.attributes.position;
        var arr = posAttr.array;
        for (var i = 0; i < SPARK_COUNT; i++) {
            arr[i * 3 + 1] += sparkSpeeds[i] * dt;
            arr[i * 3] += Math.sin(tSec * 0.8 + sparkPhase[i]) * 0.012 * dt;
            if (arr[i * 3 + 1] > 2.05) {
                arr[i * 3 + 1] = 0.12;
                arr[i * 3] = (Math.random() - 0.5) * 1.90;
            }
        }
        posAttr.needsUpdate = true;
    }

    var bootTimeMs = performance.now();
    var lastFrameMs = bootTimeMs;
    var smoothAmp = 0.0;
    var prevMode = 'idle';
    var greetWaveUntilMs = bootTimeMs + 2800;

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
                greetWaveUntilMs = nowMs + 2200;
            }
            prevMode = state.mode;
        }

        if (!dragActive) {
            userYaw = lerp(userYaw, 0.0, 1.0 - Math.exp(-dt * 4.2));
            userPitch = lerp(userPitch, 0.0, 1.0 - Math.exp(-dt * 4.8));
        }

        smoothAmp = lerp(smoothAmp, clamp(state.audioLevel || 0, 0, 1), 1.0 - Math.exp(-dt * 14.0));
        var emoKey = resolveActiveEmotionKey();
        var preset = EMOTION_PRESETS[emoKey] || EMOTION_PRESETS.idle;

        // Chibi breathing & gentle bounce (exact Mini-PC idleBounce)
        var idleBounce = Math.sin(tSec * 2.2) * 0.012
            + ((emoKey === 'happy' || emoKey === 'excited') ? Math.abs(Math.sin(tSec * 5.8)) * 0.014 : 0);
        pelvis.position.y = lerp(pelvis.position.y, 0.50 + idleBounce, 1.0 - Math.exp(-dt * 12.0));

        root.rotation.y = userYaw + Math.sin(tSec * 0.7) * 0.032;

        var headBlend = 1.0 - Math.exp(-dt * 8.5);
        var torsoPitchTarget = userPitch * 0.42 + (state.mode === 'listening' ? 0.045 : 0.0);
        rig.torso.rotation.x = lerp(rig.torso.rotation.x, torsoPitchTarget, headBlend);
        rig.torso.rotation.y = lerp(rig.torso.rotation.y, lookCur.x * 0.16, headBlend);
        rig.torso.rotation.z = lerp(rig.torso.rotation.z, Math.sin(tSec * 1.6) * 0.020, headBlend);

        var speakNod = (state.mode === 'speaking')
            ? (Math.sin(tSec * 9.0) * 0.035 * (0.3 + smoothAmp))
            : 0.0;
        rig.head.rotation.y = lerp(rig.head.rotation.y, lookCur.x * 0.32 + userYaw * 0.12, headBlend);
        rig.head.rotation.x = lerp(rig.head.rotation.x, lookCur.y * 0.20 + (preset.headPitch || 0) + speakNod + userPitch * 0.48, headBlend);
        rig.head.rotation.z = lerp(rig.head.rotation.z, (preset.headRoll || 0) - lookCur.x * 0.07 + Math.sin(tSec * 0.9) * 0.018, headBlend);

        // Crystal Siren Dome subtle rose pulse when active/listening/speaking
        var isActivating = (state.mode === 'connecting' || emoKey === 'thinking');
        var sirenBoost = (state.mode === 'speaking')
            ? (0.08 + smoothAmp * 0.24)
            : ((state.mode === 'listening' || isActivating || state.cameraActive)
                ? (0.08 + 0.12 * Math.abs(Math.sin(tSec * 4.2)))
                : 0.0);
        mats.sirenMat.color.setRGB(
            Math.min(1, 0.91 + sirenBoost),
            Math.min(1, 0.51 + sirenBoost * 0.5),
            Math.min(1, 0.61 + sirenBoost * 0.5)
        );
        floorDisc.material.opacity = isActivating
            ? (0.86 + 0.14 * Math.sin(tSec * 3.4))
            : 0.94;

        // Active Chibi Arm Gesture Selection
        var activeGesture = 'idle';
        if (nowMs < greetWaveUntilMs || state.mode === 'connecting') {
            activeGesture = 'wave';
        } else if (state.mode === 'speaking') {
            activeGesture = 'speaking';
        } else if (state.mode === 'listening') {
            activeGesture = 'listening';
        } else if (emoKey === 'proud' || emoKey === 'thinking' || emoKey === 'focused') {
            activeGesture = 'hands_on_hips';
        } else if (emoKey === 'sweet') {
            activeGesture = 'hands_on_cheeks';
        } else if (emoKey === 'happy' || emoKey === 'excited') {
            activeGesture = 'wave';
        }

        var armPose = solveArmTargets(activeGesture, tSec, smoothAmp);
        var armBlend = 1.0 - Math.exp(-dt * 8.6);
        applyArmSmoothing(rig.armR, armPose.rS, armPose.rE, armPose.rW, armPose.rCurl, armBlend);
        applyArmSmoothing(rig.armL, armPose.lS, armPose.lE, armPose.lW, armPose.lCurl, armBlend);

        renderVisorFace(nowMs, dt);
        updateSparks(dt, tSec);

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
