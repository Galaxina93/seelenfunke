/*!
 * Mein-Seelenfunke · Begehbarer 3D-Louvre-Showroom (Schwarz-Gold PBR Engine)
 * On-Demand ES-Modul: 0 KB Initial-Last auf /shop, 0 % CPU/GPU nach dem Schliessen.
 */

const FUNKI_GLB_FRONT_ROTATION_Y = -Math.PI * 0.5;

const DEFAULT_FUNKI_MODELS = {
    1: '/shop/customer/gamification/models/funki_lvl_1_rags.glb',
    2: '/shop/customer/gamification/models/funki_lvl_2_basic.glb',
    3: '/shop/customer/gamification/models/funki_lvl_3_helper.glb',
    4: '/shop/customer/gamification/models/funki_lvl_4_novice.glb',
    5: '/shop/customer/gamification/models/funki_lvl_5_apprentice.glb',
    6: '/shop/customer/gamification/models/funki_lvl_6_craftsman.glb',
    7: '/shop/customer/gamification/models/funki_lvl_7_artisan.glb',
    8: '/shop/customer/gamification/models/funki_lvl_8_master.glb',
    9: '/shop/customer/gamification/models/funki_lvl_9_lightbringer.glb',
    10: '/shop/customer/gamification/models/funki_lvl_10_god_of_soul.glb',
};

function clamp(v, lo, hi) {
    return Math.max(lo, Math.min(hi, v));
}

function lerp(a, b, t) {
    return a + (b - a) * t;
}

function lerpAngle(a, b, t) {
    let diff = ((b - a + Math.PI) % (Math.PI * 2)) - Math.PI;
    if (diff < -Math.PI) diff += Math.PI * 2;
    return a + diff * t;
}

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

function applySrgbColorSpace(THREE, tex) {
    if (!tex) return tex;
    if ('colorSpace' in tex && THREE.SRGBColorSpace) {
        tex.colorSpace = THREE.SRGBColorSpace;
    } else if (THREE.sRGBEncoding) {
        tex.encoding = THREE.sRGBEncoding;
    }
    return tex;
}

function applyLinearColorSpace(THREE, tex) {
    if (!tex) return tex;
    if ('colorSpace' in tex && typeof THREE.NoColorSpace !== 'undefined') {
        tex.colorSpace = THREE.NoColorSpace;
    } else if (THREE.LinearEncoding) {
        tex.encoding = THREE.LinearEncoding;
    }
    return tex;
}

function loadThreeLibrary() {
    if (window.THREE) {
        return Promise.resolve(window.THREE);
    }
    if (window.__sfThreeLoadingPromise) {
        return window.__sfThreeLoadingPromise;
    }
    window.__sfThreeLoadingPromise = new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = '/vendor/three/three.min.js';
        script.async = true;
        script.onload = () => {
            if (window.THREE) {
                resolve(window.THREE);
            } else {
                window.__sfThreeLoadingPromise = null;
                reject(new Error('Three.js konnte nicht initialisiert werden.'));
            }
        };
        script.onerror = () => {
            window.__sfThreeLoadingPromise = null;
            reject(new Error('Three.js konnte nicht geladen werden.'));
        };
        document.head.appendChild(script);
    });
    return window.__sfThreeLoadingPromise;
}

/**
 * Nativer, abhaengigkeitsfreier GLB 2.0 Parser fuer komprimierte Seelenfunke Produkt- & Funki-Modelle.
 */
function parseGlbBinary(THREE, arrayBuffer, maxAniso, disposables) {
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
        5120: { Ctor: Int8Array, bytes: 1, normDiv: 127.0, read: (v, o) => v.getInt8(o) },
        5121: { Ctor: Uint8Array, bytes: 1, normDiv: 255.0, read: (v, o) => v.getUint8(o) },
        5122: { Ctor: Int16Array, bytes: 2, normDiv: 32767.0, read: (v, o) => v.getInt16(o, true) },
        5123: { Ctor: Uint16Array, bytes: 2, normDiv: 65535.0, read: (v, o) => v.getUint16(o, true) },
        5125: { Ctor: Uint32Array, bytes: 4, normDiv: 4294967295.0, read: (v, o) => v.getUint32(o, true) },
        5126: { Ctor: Float32Array, bytes: 4, normDiv: 1.0, read: (v, o) => v.getFloat32(o, true) },
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

        let rawArr;
        if (stride === packedStride && (byteStart % spec.bytes) === 0) {
            rawArr = new spec.Ctor(arrayBuffer, byteStart, count * itemSize).slice();
        } else {
            rawArr = new spec.Ctor(count * itemSize);
            for (let i = 0; i < count; i++) {
                const baseOff = byteStart + i * stride;
                for (let c = 0; c < itemSize; c++) {
                    rawArr[i * itemSize + c] = spec.read(dv, baseOff + c * spec.bytes);
                }
            }
        }

        if (acc.normalized && acc.componentType !== 5126) {
            const floatOut = new Float32Array(rawArr.length);
            for (let k = 0; k < rawArr.length; k++) {
                floatOut[k] = Math.max(-1.0, rawArr[k] / spec.normDiv);
            }
            return { array: floatOut, itemSize };
        }

        return { array: rawArr, itemSize };
    }

    const localTexPromises = {};
    const localMatPromises = {};

    function loadEmbeddedTexture(texIndex, isSrgb) {
        if (texIndex === undefined || texIndex === null || !jsonObj.textures || !jsonObj.textures[texIndex]) {
            return Promise.resolve(null);
        }
        const key = `${texIndex}:${isSrgb ? 'srgb' : 'lin'}`;
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
                    applySrgbColorSpace(THREE, tex);
                } else {
                    applyLinearColorSpace(THREE, tex);
                }
                tex.needsUpdate = true;
                if (disposables) disposables.push(tex);
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
            loadEmbeddedTexture(emisIdx, true),
        ]).then((texArr) => {
            const [baseTex, mrTex, normTex, emisTex] = texArr;
            const bcf = Array.isArray(pbr.baseColorFactor) ? pbr.baseColorFactor : [1, 1, 1, 1];
            const rawMetal = (typeof pbr.metallicFactor === 'number') ? pbr.metallicFactor : (mrTex ? 1.0 : 0.18);
            const rawRough = (typeof pbr.roughnessFactor === 'number') ? pbr.roughnessFactor : 0.65;

            const opts = {
                name: matDef.name || '',
                color: new THREE.Color(bcf[0], bcf[1], bcf[2]),
                metalness: clamp(rawMetal, 0.0, 1.0),
                roughness: clamp(rawRough, 0.03, 1.0),
                side: matDef.doubleSided ? THREE.DoubleSide : THREE.FrontSide,
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
                opts.alphaTest = (typeof matDef.alphaCutoff === 'number') ? matDef.alphaCutoff : 0.45;
            } else if (matDef.alphaMode === 'BLEND') {
                opts.transparent = true;
                opts.opacity = (typeof bcf[3] === 'number') ? bcf[3] : 1.0;
            }

            const mat = new THREE.MeshStandardMaterial(opts);
            mat.userData = { extensions: matDef.extensions || {} };
            if (disposables) disposables.push(mat);
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

                    const colData = readAccessorArray(attrs.COLOR_0);
                    if (colData) {
                        geo.setAttribute('color', new THREE.BufferAttribute(colData.array, colData.itemSize));
                        mat.vertexColors = true;
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
                    if (disposables) disposables.push(geo);

                    const mesh = new THREE.Mesh(geo, mat);
                    nodeObj.add(mesh);
                }));
            }
        }

        const childIndices = Array.isArray(nDef.children) ? nDef.children : [];
        const childTasks = childIndices.map((ci) => buildNodeAsync(ci).then((childGrp) => {
            nodeObj.add(childGrp);
        }));

        return Promise.all(primTasks.concat(childTasks)).then(() => nodeObj);
    }

    const sceneIdx = (typeof jsonObj.scene === 'number') ? jsonObj.scene : 0;
    const rootNodes = (jsonObj.scenes && jsonObj.scenes[sceneIdx] && Array.isArray(jsonObj.scenes[sceneIdx].nodes))
        ? jsonObj.scenes[sceneIdx].nodes
        : [0];

    const rootGroup = new THREE.Group();
    return Promise.all(rootNodes.map((ri) => buildNodeAsync(ri).then((child) => {
        rootGroup.add(child);
    }))).then(() => ({
        scene: rootGroup,
    }));
}

/**
 * Erzeugt ein hochrealistisches prozedurales Louvre-Studio-Environment via PMREMGenerator,
 * damit K9-Kristallglas (ior 1.52) und 14K/585-Gold scharfe, physikalisch echte Lichtkanten zeigen.
 */
function buildLouvreStudioEnvironment(THREE, renderer, disposables) {
    if (!THREE.PMREMGenerator) return null;

    const pmrem = new THREE.PMREMGenerator(renderer);
    if (typeof pmrem.compileCubemapShader === 'function') {
        pmrem.compileCubemapShader();
    }

    const envScene = new THREE.Scene();
    envScene.background = new THREE.Color(0x0b0e14);

    const tempMeshes = [];
    const addLightPanel = (w, h, colorHex, x, y, z, rx, ry, rz) => {
        const g = new THREE.PlaneGeometry(w, h);
        const m = new THREE.MeshBasicMaterial({ color: colorHex, side: THREE.DoubleSide });
        const mesh = new THREE.Mesh(g, m);
        mesh.position.set(x, y, z);
        mesh.rotation.set(rx || 0, ry || 0, rz || 0);
        envScene.add(mesh);
        tempMeshes.push(g, m);
    };

    // Oberlicht-Pyramiden-Softboxen fuer scharfe Kristall- und Gold-Glanzkanten
    addLightPanel(18, 18, 0xfffcf5, 0, 14, 0, Math.PI * 0.5, 0, 0);
    addLightPanel(14, 5.5, 0xfff7e6, 0, 9.5, -11, 0.52, 0, 0);
    addLightPanel(14, 5.5, 0xfff7e6, 0, 9.5, 11, -0.52, 0, 0);
    addLightPanel(14, 5.5, 0xf4f7fc, -11, 9.0, 0, 0, Math.PI * 0.5, -0.45);
    addLightPanel(14, 5.5, 0xf4f7fc, 11, 9.0, 0, 0, -Math.PI * 0.5, 0.45);

    // Warme 24K-Messing-Reflexstreifen auf Augenhoehe fuer Schmuck-Brillanz
    addLightPanel(20, 1.4, 0xe6c687, -13, 2.8, 0, 0, Math.PI * 0.5, 0);
    addLightPanel(20, 1.4, 0xe6c687, 13, 2.8, 0, 0, -Math.PI * 0.5, 0);
    addLightPanel(16, 1.2, 0xc5a059, 0, 3.2, -13, 0, 0, 0);
    addLightPanel(16, 1.2, 0xdbe6f4, 0, 3.6, 13, 0, Math.PI, 0);

    // Dunkler Marmorboden-Gegenpol fuer hohen Kontrast
    addLightPanel(30, 30, 0x07090d, 0, -6, 0, -Math.PI * 0.5, 0, 0);

    const envRT = pmrem.fromScene(envScene, 0.03);
    tempMeshes.forEach((d) => d.dispose());
    pmrem.dispose();

    if (disposables && envRT) {
        disposables.push(envRT);
    }
    return envRT ? envRT.texture : null;
}

/**
 * 2048x2048 Prozeduraler Nero-Marquina-Marmorboden (Albedo + RoughnessMap + BumpMap).
 * Tiefschwarzer Naturstein mit feinen weissen Calcit-Adern und #C5A059 24K-Goldadern.
 */
function buildNeroMarquinaFloorMaps(THREE, maxAniso, isMobile, repeatX, repeatZ, disposables) {
    const S = isMobile ? 1024 : 2048;

    const cAlbedo = document.createElement('canvas');
    cAlbedo.width = S;
    cAlbedo.height = S;
    const ctxA = cAlbedo.getContext('2d');

    const cRough = document.createElement('canvas');
    cRough.width = S;
    cRough.height = S;
    const ctxR = cRough.getContext('2d');

    const cBump = document.createElement('canvas');
    cBump.width = S;
    cBump.height = S;
    const ctxB = cBump.getContext('2d');

    const rng = mulberry32(20261116);

    // Grundstein-Basis
    ctxA.fillStyle = '#090b10';
    ctxA.fillRect(0, 0, S, S);

    ctxR.fillStyle = 'rgb(38, 38, 38)';
    ctxR.fillRect(0, 0, S, S);

    ctxB.fillStyle = 'rgb(128, 128, 128)';
    ctxB.fillRect(0, 0, S, S);

    // Wolkige Tiefenschattierung im Naturstein
    for (let i = 0; i < 68; i++) {
        const cx = rng() * S;
        const cy = rng() * S;
        const rad = (0.08 + rng() * 0.22) * S;
        const bright = rng() > 0.5;

        const gA = ctxA.createRadialGradient(cx, cy, rad * 0.05, cx, cy, rad);
        gA.addColorStop(0, bright ? 'rgba(24, 29, 40, 0.24)' : 'rgba(4, 5, 8, 0.30)');
        gA.addColorStop(1, 'rgba(9, 11, 16, 0)');
        ctxA.fillStyle = gA;
        ctxA.fillRect(0, 0, S, S);

        const gR = ctxR.createRadialGradient(cx, cy, rad * 0.05, cx, cy, rad);
        gR.addColorStop(0, bright ? 'rgba(54, 54, 54, 0.18)' : 'rgba(28, 28, 28, 0.18)');
        gR.addColorStop(1, 'rgba(38, 38, 38, 0)');
        ctxR.fillStyle = gR;
        ctxR.fillRect(0, 0, S, S);
    }

    // Organische Marmor-Adern (Kristallweiss & 24K-Gold)
    const drawMarbleVein = (isGold, thicknessScale) => {
        let x = rng() * S;
        let y = rng() * S;
        let angle = (rng() * 0.65 + 0.18) * Math.PI * (rng() > 0.5 ? 1 : -1);
        const steps = 64 + Math.floor(rng() * 54);
        const stepLen = (S / 95) * (0.75 + rng() * 0.65);

        ctxA.beginPath();
        ctxR.beginPath();
        ctxB.beginPath();
        ctxA.moveTo(x, y);
        ctxR.moveTo(x, y);
        ctxB.moveTo(x, y);

        for (let s = 0; s < steps; s++) {
            angle += (rng() - 0.5) * 0.44;
            x += Math.cos(angle) * stepLen;
            y += Math.sin(angle) * stepLen;
            ctxA.lineTo(x, y);
            ctxR.lineTo(x, y);
            ctxB.lineTo(x, y);

            // Feine Seitenverzweigung
            if (rng() > 0.86) {
                let bx = x;
                let by = y;
                let bAng = angle + (rng() > 0.5 ? 0.58 : -0.58);
                ctxA.moveTo(bx, by);
                for (let bs = 0; bs < 14; bs++) {
                    bAng += (rng() - 0.5) * 0.35;
                    bx += Math.cos(bAng) * stepLen * 0.6;
                    by += Math.sin(bAng) * stepLen * 0.6;
                    ctxA.lineTo(bx, by);
                }
                ctxA.moveTo(x, y);
            }
        }

        const lw = (isGold ? (1.1 + rng() * 2.1) : (0.9 + rng() * 2.5)) * (S / 1024) * thicknessScale;
        ctxA.strokeStyle = isGold
            ? `rgba(197, 160, 89, ${(0.28 + rng() * 0.34).toFixed(2)})`
            : `rgba(236, 240, 247, ${(0.16 + rng() * 0.28).toFixed(2)})`;
        ctxA.lineWidth = lw;
        ctxA.lineCap = 'round';
        ctxA.lineJoin = 'round';
        ctxA.stroke();

        ctxR.strokeStyle = isGold ? 'rgba(24, 24, 24, 0.42)' : 'rgba(96, 96, 96, 0.36)';
        ctxR.lineWidth = lw * 1.3;
        ctxR.stroke();

        ctxB.strokeStyle = isGold ? 'rgba(150, 150, 150, 0.35)' : 'rgba(142, 142, 142, 0.28)';
        ctxB.lineWidth = lw;
        ctxB.stroke();
    };

    for (let v = 0; v < 38; v++) {
        drawMarbleVein(false, 1.0);
    }
    for (let gv = 0; gv < 18; gv++) {
        drawMarbleVein(true, 0.95);
    }

    // Grossformatige Louvre-Steinplattenfugen (4x4 Raster pro Kachel)
    const slabs = 4;
    const step = S / slabs;
    for (let i = 0; i <= slabs; i++) {
        const pos = Math.round(i * step);
        ctxA.fillStyle = 'rgba(197, 160, 89, 0.16)';
        ctxA.fillRect(pos - 1, 0, 2, S);
        ctxA.fillRect(0, pos - 1, S, 2);

        ctxR.fillStyle = 'rgb(130, 130, 130)';
        ctxR.fillRect(pos - 2, 0, 4, S);
        ctxR.fillRect(0, pos - 2, S, 4);

        ctxB.fillStyle = 'rgb(88, 88, 88)';
        ctxB.fillRect(pos - 2, 0, 4, S);
        ctxB.fillRect(0, pos - 2, S, 4);
    }

    const setupRepeatTex = (canvas, isSrgb) => {
        const t = new THREE.CanvasTexture(canvas);
        t.wrapS = THREE.RepeatWrapping;
        t.wrapT = THREE.RepeatWrapping;
        t.repeat.set(repeatX, repeatZ);
        t.generateMipmaps = true;
        t.minFilter = THREE.LinearMipmapLinearFilter;
        t.magFilter = THREE.LinearFilter;
        t.anisotropy = Math.min(maxAniso || 4, 8);
        if (isSrgb) {
            applySrgbColorSpace(THREE, t);
        } else {
            applyLinearColorSpace(THREE, t);
        }
        t.needsUpdate = true;
        if (disposables) disposables.push(t);
        return t;
    };

    return {
        map: setupRepeatTex(cAlbedo, true),
        roughnessMap: setupRepeatTex(cRough, false),
        bumpMap: setupRepeatTex(cBump, false),
    };
}

/**
 * Weicher Baked Ambient Occlusion Kontaktschatten fuer Stelen und Funki auf dem Marmorboden.
 */
function buildContactShadowTexture(THREE, disposables) {
    const S = 256;
    const c = document.createElement('canvas');
    c.width = S;
    c.height = S;
    const ctx = c.getContext('2d');

    const g = ctx.createRadialGradient(S * 0.5, S * 0.5, S * 0.08, S * 0.5, S * 0.5, S * 0.49);
    g.addColorStop(0.0, 'rgba(0, 0, 0, 0.86)');
    g.addColorStop(0.48, 'rgba(0, 0, 0, 0.54)');
    g.addColorStop(0.78, 'rgba(0, 0, 0, 0.16)');
    g.addColorStop(1.0, 'rgba(0, 0, 0, 0.0)');
    ctx.fillStyle = g;
    ctx.fillRect(0, 0, S, S);

    const tex = new THREE.CanvasTexture(c);
    tex.needsUpdate = true;
    if (disposables) disposables.push(tex);
    return tex;
}

/**
 * Edler goldener Stirnfries "MEIN SEELENFUNKE" an der Hauptwand des Louvre.
 */
function buildLouvreFriezeTexture(THREE, maxAniso, titleText, subText, disposables) {
    const W = 2048;
    const H = 384;
    const c = document.createElement('canvas');
    c.width = W;
    c.height = H;
    const ctx = c.getContext('2d');

    const bg = ctx.createLinearGradient(0, 0, 0, H);
    bg.addColorStop(0, '#0c0f16');
    bg.addColorStop(0.5, '#07090e');
    bg.addColorStop(1, '#0c0f16');
    ctx.fillStyle = bg;
    ctx.fillRect(0, 0, W, H);

    ctx.strokeStyle = '#C5A059';
    ctx.lineWidth = 8;
    ctx.strokeRect(16, 16, W - 32, H - 32);

    ctx.strokeStyle = 'rgba(230, 198, 135, 0.42)';
    ctx.lineWidth = 2;
    ctx.strokeRect(32, 32, W - 64, H - 64);

    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';

    const goldGrad = ctx.createLinearGradient(0, H * 0.18, 0, H * 0.72);
    goldGrad.addColorStop(0.0, '#FFF8E1');
    goldGrad.addColorStop(0.38, '#E6C687');
    goldGrad.addColorStop(0.72, '#C5A059');
    goldGrad.addColorStop(1.0, '#9A7738');

    ctx.font = '700 104px Georgia, "Times New Roman", serif';
    if ('letterSpacing' in ctx) {
        ctx.letterSpacing = '14px';
    }
    ctx.fillStyle = 'rgba(0, 0, 0, 0.85)';
    ctx.fillText(titleText, W * 0.5 + 3, H * 0.44 + 4, W - 140);
    ctx.fillStyle = goldGrad;
    ctx.fillText(titleText, W * 0.5, H * 0.44, W - 140);

    ctx.font = '600 30px system-ui, -apple-system, "Segoe UI", sans-serif';
    if ('letterSpacing' in ctx) {
        ctx.letterSpacing = '9px';
    }
    ctx.fillStyle = '#C5A059';
    ctx.fillText(subText, W * 0.5, H * 0.77, W - 160);

    const tex = new THREE.CanvasTexture(c);
    tex.anisotropy = Math.min(maxAniso || 4, 8);
    applySrgbColorSpace(THREE, tex);
    tex.needsUpdate = true;
    if (disposables) disposables.push(tex);
    return tex;
}

/**
 * Gold-Schwarz Museums-Plakette an jeder Produkt-Stele.
 */
function buildStelePlaqueTexture(THREE, maxAniso, product, index, total, disposables) {
    const W = 512;
    const H = 256;
    const c = document.createElement('canvas');
    c.width = W;
    c.height = H;
    const ctx = c.getContext('2d');

    ctx.fillStyle = '#090b10';
    ctx.fillRect(0, 0, W, H);

    ctx.strokeStyle = '#C5A059';
    ctx.lineWidth = 5;
    ctx.strokeRect(8, 8, W - 16, H - 16);

    ctx.strokeStyle = 'rgba(197, 160, 89, 0.35)';
    ctx.lineWidth = 1.5;
    ctx.strokeRect(16, 16, W - 32, H - 32);

    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';

    const cat = String(product.category_name || 'KOLLEKTION').toUpperCase();
    ctx.fillStyle = '#C5A059';
    ctx.font = '700 18px system-ui, -apple-system, sans-serif';
    ctx.fillText(`EXPONAT ${index + 1}/${total} · ${cat}`, W * 0.5, 48, W - 56);

    const name = String(product.name || 'Unikat');
    ctx.fillStyle = '#FAF8F5';
    ctx.font = '700 28px Georgia, serif';
    if (name.length > 26) {
        const words = name.split(' ');
        let line1 = '';
        let line2 = '';
        for (let i = 0; i < words.length; i++) {
            if ((line1 + ' ' + words[i]).trim().length <= 24 || line1 === '') {
                line1 = (line1 + ' ' + words[i]).trim();
            } else {
                line2 = (line2 + ' ' + words[i]).trim();
            }
        }
        if (line2) {
            ctx.fillText(line1, W * 0.5, 98, W - 48);
            ctx.fillText(line2, W * 0.5, 132, W - 48);
        } else {
            ctx.fillText(name, W * 0.5, 114, W - 48);
        }
    } else {
        ctx.fillText(name, W * 0.5, 114, W - 48);
    }

    ctx.fillStyle = '#E6C687';
    ctx.font = '700 28px system-ui, -apple-system, sans-serif';
    ctx.fillText(String(product.price_formatted || ''), W * 0.5, 178, W - 56);

    ctx.fillStyle = 'rgba(197, 160, 89, 0.88)';
    ctx.font = '600 15px system-ui, -apple-system, sans-serif';
    ctx.fillText('ANTIPPEN FUER DETAILS', W * 0.5, 220, W - 56);

    const tex = new THREE.CanvasTexture(c);
    tex.anisotropy = Math.min(maxAniso || 4, 4);
    applySrgbColorSpace(THREE, tex);
    tex.needsUpdate = true;
    if (disposables) disposables.push(tex);
    return tex;
}

export class SeelenfunkeLouvreShowroom3D {
    constructor(options = {}) {
        this.canvas = options.canvas;
        this.products = Array.isArray(options.products) ? options.products : [];
        this.playerFunki = options.playerFunki || { is_guest: true, level: 2, glb_url: DEFAULT_FUNKI_MODELS[2] };
        this.onSelectProduct = typeof options.onSelectProduct === 'function' ? options.onSelectProduct : null;
        this.onActiveExhibitChange = typeof options.onActiveExhibitChange === 'function' ? options.onActiveExhibitChange : null;

        this.THREE = null;
        this.renderer = null;
        this.scene = null;
        this.camera = null;
        this.envTexture = null;

        this.disposables = [];
        this.glbCache = new Map();
        this.running = false;
        this.destroyed = false;
        this.rafId = 0;
        this.lastTimeMs = 0;
        this.elapsedTime = 0;
        this.maxAniso = 4;

        this.hallHalfW = 10.6;
        this.hallNorthZ = -18;
        this.hallSouthZ = 14;

        this.steles = [];
        this.interactiveHitMeshes = [];
        this.floorMesh = null;
        this.walkTargetRing = null;
        this.walkTargetTimer = 0;

        this.player = {
            root: null,
            rig: null,
            x: 0,
            z: 8.5,
            yaw: Math.PI,
            walkPhase: 0,
            isMoving: false,
            autoTarget: null,
            autoLookAt: null,
            pendingSelectIdx: null,
        };

        this.camControl = {
            yaw: 0,
            pitch: 0.28,
            distance: 4.5,
            desiredYaw: 0,
            desiredPitch: 0.28,
            desiredDistance: 4.5,
            lookAtCurrent: null,
            isDragging: false,
            dragMoved: 0,
            lastX: 0,
            lastY: 0,
            pinchDist: 0,
        };

        this.keys = {};
        this.joystick = { x: 0, y: 0 };
        this.activeIndex = 0;

        this.raycaster = null;
        this.pointerNdc = null;

        this._boundTick = (ts) => this._tick(ts);
        this._boundResize = () => this.resize();
        this._boundKeyDown = (e) => this._onKeyDown(e);
        this._boundKeyUp = (e) => this._onKeyUp(e);
        this._boundPointerDown = (e) => this._onPointerDown(e);
        this._boundPointerMove = (e) => this._onPointerMove(e);
        this._boundPointerUp = (e) => this._onPointerUp(e);
        this._boundWheel = (e) => this._onWheel(e);
        this._boundTouchStart = (e) => this._onTouchStart(e);
        this._boundTouchMove = (e) => this._onTouchMove(e);
        this._boundContextMenu = (e) => e.preventDefault();
    }

    async init() {
        const THREE = await loadThreeLibrary();
        if (this.destroyed || !this.canvas) return;

        this.THREE = THREE;
        this.raycaster = new THREE.Raycaster();
        this.pointerNdc = new THREE.Vector2();
        this.camControl.lookAtCurrent = new THREE.Vector3(0, 1.45, 4.0);

        const isMobile = window.innerWidth < 768;
        const count = Math.max(1, this.products.length);
        const rows = Math.ceil(count / 2);
        const baySpacing = 6.2;
        const gallerySpan = Math.max(16, rows * baySpacing + 6);

        this.hallSouthZ = 12.0;
        this.hallNorthZ = this.hallSouthZ - gallerySpan - 8.0;
        this.player.x = 0;
        this.player.z = this.hallSouthZ - 3.2;
        this.player.yaw = Math.PI;

        this.renderer = new THREE.WebGLRenderer({
            canvas: this.canvas,
            antialias: !isMobile,
            alpha: false,
            precision: 'highp',
            powerPreference: 'high-performance',
            stencil: false,
            depth: true,
        });

        if (this.renderer.debug) {
            this.renderer.debug.checkShaderErrors = false;
        }

        if ('outputColorSpace' in this.renderer && THREE.SRGBColorSpace) {
            this.renderer.outputColorSpace = THREE.SRGBColorSpace;
        } else if (THREE.sRGBEncoding) {
            this.renderer.outputEncoding = THREE.sRGBEncoding;
        }
        this.renderer.toneMapping = THREE.ACESFilmicToneMapping;
        this.renderer.toneMappingExposure = 1.12;
        this.maxAniso = this.renderer.capabilities ? this.renderer.capabilities.getMaxAnisotropy() : 4;

        this.scene = new THREE.Scene();
        this.scene.background = new THREE.Color(0x0b0e15);
        this.scene.fog = new THREE.FogExp2(0x0a0d14, 0.0085);

        this.envTexture = buildLouvreStudioEnvironment(THREE, this.renderer, this.disposables);
        if (this.envTexture) {
            this.scene.environment = this.envTexture;
        }

        this._buildLighting();
        this._buildLouvreArchitecture(isMobile);
        this._buildExhibitSteles(baySpacing);
        this._buildPlayerCharacter();
        this._bindEvents();

        this.resize();

        if (this.steles.length > 0 && this.onActiveExhibitChange) {
            this.onActiveExhibitChange(0, this.products[0]);
        }

        this.running = true;
        this.lastTimeMs = performance.now();
        this.rafId = requestAnimationFrame(this._boundTick);
    }

    _trackDisposable(item) {
        if (item) this.disposables.push(item);
        return item;
    }

    _buildLighting() {
        const THREE = this.THREE;

        const hemi = new THREE.HemisphereLight(0xf6f2e9, 0x151821, 0.95);
        hemi.position.set(0, 16, 0);
        this.scene.add(hemi);

        const dirMain = new THREE.DirectionalLight(0xfffaf0, 1.45);
        dirMain.position.set(6, 18, 8);
        this.scene.add(dirMain);

        const dirFill = new THREE.DirectionalLight(0xe5ecf8, 0.85);
        dirFill.position.set(-8, 14, -10);
        this.scene.add(dirFill);

        // Warme goldene Akzentbeleuchtung auf den Stirnfries
        const friezeLight = new THREE.PointLight(0xfff0cc, 1.35, 28, 1.6);
        friezeLight.position.set(0, 5.8, this.hallNorthZ + 4.5);
        this.scene.add(friezeLight);
    }

    _buildLouvreArchitecture(isMobile) {
        const THREE = this.THREE;
        const totalLength = (this.hallSouthZ - this.hallNorthZ) + 6.0;
        const centerZ = (this.hallSouthZ + this.hallNorthZ) * 0.5;
        const hallW = 24.0;
        const wallH = 7.4;
        const pyramidApexY = 14.8;

        // Gemeinsame Louvre Schwarz-Gold Materialien
        this.mats = {
            obsidianWall: this._trackDisposable(new THREE.MeshStandardMaterial({
                color: 0x0c0f15,
                roughness: 0.66,
                metalness: 0.18,
                envMapIntensity: 0.55,
            })),
            cofferRecess: this._trackDisposable(new THREE.MeshStandardMaterial({
                color: 0x07090d,
                roughness: 0.78,
                metalness: 0.12,
                envMapIntensity: 0.35,
            })),
            archSteel: this._trackDisposable(new THREE.MeshStandardMaterial({
                color: 0x0d1016,
                roughness: 0.38,
                metalness: 0.72,
                envMapIntensity: 0.9,
            })),
            goldBrushed: this._trackDisposable(new THREE.MeshStandardMaterial({
                color: 0xc5a059,
                roughness: 0.25,
                metalness: 0.88,
                envMapIntensity: 1.25,
            })),
            goldChampagne: this._trackDisposable(new THREE.MeshStandardMaterial({
                color: 0xe6c687,
                roughness: 0.19,
                metalness: 0.92,
                envMapIntensity: 1.35,
            })),
            velvetPad: this._trackDisposable(new THREE.MeshStandardMaterial({
                color: 0x11131a,
                roughness: 0.94,
                metalness: 0.04,
            })),
            pyramidGlass: this._trackDisposable(new THREE.MeshPhysicalMaterial({
                color: 0xdbe6f2,
                roughness: 0.07,
                metalness: 0.05,
                reflectivity: 0.92,
                transparent: true,
                opacity: 0.19,
                depthWrite: false,
                side: THREE.DoubleSide,
            })),
            vitrineGlass: this._trackDisposable(new THREE.MeshPhysicalMaterial({
                color: 0xf5f8fc,
                roughness: 0.04,
                metalness: 0.02,
                ior: 1.5,
                reflectivity: 0.95,
                clearcoat: 1.0,
                clearcoatRoughness: 0.04,
                envMapIntensity: 1.15,
                transparent: true,
                opacity: 0.14,
                depthWrite: false,
            })),
            spotlightCone: this._trackDisposable(new THREE.MeshBasicMaterial({
                color: 0xfff3d6,
                transparent: true,
                opacity: 0.042,
                depthWrite: false,
                side: THREE.DoubleSide,
            })),
        };

        this.contactShadowTex = buildContactShadowTexture(THREE, this.disposables);
        this.mats.contactShadow = this._trackDisposable(new THREE.MeshBasicMaterial({
            map: this.contactShadowTex,
            transparent: true,
            opacity: 0.78,
            depthWrite: false,
        }));

        // 1. Atmosphaerische Pariser Abendhimmel-Kuppel ueber der Glaspyramide
        const skyGeo = this._trackDisposable(new THREE.SphereGeometry(140, 28, 18));
        const skyCanvas = document.createElement('canvas');
        skyCanvas.width = 64;
        skyCanvas.height = 256;
        const skyCtx = skyCanvas.getContext('2d');
        const skyGrad = skyCtx.createLinearGradient(0, 0, 0, 256);
        skyGrad.addColorStop(0.0, '#0c1220');
        skyGrad.addColorStop(0.38, '#182236');
        skyGrad.addColorStop(0.54, '#2b2927');
        skyGrad.addColorStop(1.0, '#07090d');
        skyCtx.fillStyle = skyGrad;
        skyCtx.fillRect(0, 0, 64, 256);
        const skyTex = this._trackDisposable(new THREE.CanvasTexture(skyCanvas));
        applySrgbColorSpace(THREE, skyTex);
        const skyMat = this._trackDisposable(new THREE.MeshBasicMaterial({ map: skyTex, side: THREE.BackSide, fog: false }));
        const skyDome = new THREE.Mesh(skyGeo, skyMat);
        skyDome.position.set(0, 0, centerZ);
        this.scene.add(skyDome);

        // 2. Polierter Nero-Marquina-Marmorboden (2048x2048 PBR)
        const repX = hallW / 6.0;
        const repZ = totalLength / 6.0;
        const marbleMaps = buildNeroMarquinaFloorMaps(THREE, this.maxAniso, isMobile, repX, repZ, this.disposables);
        const floorMat = this._trackDisposable(new THREE.MeshStandardMaterial({
            map: marbleMaps.map,
            roughnessMap: marbleMaps.roughnessMap,
            bumpMap: marbleMaps.bumpMap,
            bumpScale: 0.007,
            roughness: 0.18,
            metalness: 0.08,
            envMapIntensity: 1.15,
        }));

        const floorGeo = this._trackDisposable(new THREE.PlaneGeometry(hallW, totalLength));
        this.floorMesh = new THREE.Mesh(floorGeo, floorMat);
        this.floorMesh.rotation.x = -Math.PI * 0.5;
        this.floorMesh.position.set(0, 0, centerZ);
        this.floorMesh.userData = { isFloor: true };
        this.scene.add(this.floorMesh);

        // Goldene Louvre-Intarsien-Linien entlang der Mittelachse
        const inlayLength = totalLength - 6.0;
        const inlayStripGeo = this._trackDisposable(new THREE.PlaneGeometry(0.045, inlayLength));
        [-2.25, -2.12, 2.12, 2.25].forEach((ix) => {
            const strip = new THREE.Mesh(inlayStripGeo, this.mats.goldBrushed);
            strip.rotation.x = -Math.PI * 0.5;
            strip.position.set(ix, 0.0025, centerZ);
            this.scene.add(strip);
        });

        // Zentrales goldenes Louvre-Rauten-Medaillon im Marmorboden
        const medOuterGeo = this._trackDisposable(new THREE.RingGeometry(1.18, 1.28, 4));
        const medInnerGeo = this._trackDisposable(new THREE.RingGeometry(0.62, 0.69, 4));
        const medOuter = new THREE.Mesh(medOuterGeo, this.mats.goldChampagne);
        medOuter.rotation.x = -Math.PI * 0.5;
        medOuter.position.set(0, 0.003, centerZ);
        this.scene.add(medOuter);

        const medInner = new THREE.Mesh(medInnerGeo, this.mats.goldBrushed);
        medInner.rotation.x = -Math.PI * 0.5;
        medInner.position.set(0, 0.003, centerZ);
        this.scene.add(medInner);

        // Tap-to-Walk Zielring auf dem Boden
        const targetRingGeo = this._trackDisposable(new THREE.RingGeometry(0.28, 0.36, 32));
        const targetRingMat = this._trackDisposable(new THREE.MeshBasicMaterial({
            color: 0xe6c687,
            transparent: true,
            opacity: 0.0,
            side: THREE.DoubleSide,
            depthWrite: false,
        }));
        this.walkTargetRing = new THREE.Mesh(targetRingGeo, targetRingMat);
        this.walkTargetRing.rotation.x = -Math.PI * 0.5;
        this.walkTargetRing.position.set(0, 0.012, 0);
        this.scene.add(this.walkTargetRing);

        // 3. Museums-Kolonnaden & Kassettenwaende (Links & Rechts)
        const sideWallGeo = this._trackDisposable(new THREE.BoxGeometry(0.6, wallH, totalLength));
        [-hallW * 0.5, hallW * 0.5].forEach((wx) => {
            const wall = new THREE.Mesh(sideWallGeo, this.mats.cofferRecess);
            wall.position.set(wx, wallH * 0.5, centerZ);
            this.scene.add(wall);

            // Durchgehende #C5A059 Messing-Sockel- und Kranzgesimsleisten
            const trimGeo = this._trackDisposable(new THREE.BoxGeometry(0.72, 0.08, totalLength));
            const baseTrim = new THREE.Mesh(trimGeo, this.mats.goldBrushed);
            baseTrim.position.set(wx, 0.12, centerZ);
            this.scene.add(baseTrim);

            const corniceBlockGeo = this._trackDisposable(new THREE.BoxGeometry(0.95, 0.42, totalLength));
            const cornice = new THREE.Mesh(corniceBlockGeo, this.mats.obsidianWall);
            cornice.position.set(wx, wallH, centerZ);
            this.scene.add(cornice);

            const topGoldTrim = new THREE.Mesh(trimGeo, this.mats.goldBrushed);
            topGoldTrim.position.set(wx, wallH - 0.22, centerZ);
            this.scene.add(topGoldTrim);
        });

        // Kolonnaden-Pfeiler via InstancedMesh
        const pierSpacing = 6.2;
        const pierCountSide = Math.max(3, Math.floor(totalLength / pierSpacing));
        const pierGeo = this._trackDisposable(new THREE.BoxGeometry(0.75, wallH - 0.3, 0.75));
        const capGeo = this._trackDisposable(new THREE.BoxGeometry(0.92, 0.12, 0.92));

        const totalPiers = pierCountSide * 2;
        const pierInst = new THREE.InstancedMesh(pierGeo, this.mats.obsidianWall, totalPiers);
        const capTopInst = new THREE.InstancedMesh(capGeo, this.mats.goldBrushed, totalPiers);
        const capBotInst = new THREE.InstancedMesh(capGeo, this.mats.goldBrushed, totalPiers);

        const dummy = new THREE.Object3D();
        let pIdx = 0;
        for (let i = 0; i < pierCountSide; i++) {
            const pz = this.hallNorthZ + 2.5 + i * ((totalLength - 5.0) / Math.max(1, pierCountSide - 1));
            [-hallW * 0.5 + 0.42, hallW * 0.5 - 0.42].forEach((px) => {
                dummy.position.set(px, (wallH - 0.3) * 0.5, pz);
                dummy.rotation.set(0, 0, 0);
                dummy.scale.set(1, 1, 1);
                dummy.updateMatrix();
                pierInst.setMatrixAt(pIdx, dummy.matrix);

                dummy.position.set(px, wallH - 0.32, pz);
                dummy.updateMatrix();
                capTopInst.setMatrixAt(pIdx, dummy.matrix);

                dummy.position.set(px, 0.18, pz);
                dummy.updateMatrix();
                capBotInst.setMatrixAt(pIdx, dummy.matrix);
                pIdx++;
            });
        }
        this.scene.add(pierInst);
        this.scene.add(capTopInst);
        this.scene.add(capBotInst);

        // 4. Nord- & Sued-Stirnwaende + goldener MEIN SEELENFUNKE Stirnfries
        const endWallGeo = this._trackDisposable(new THREE.BoxGeometry(hallW + 0.6, wallH + 0.4, 0.7));
        const northWall = new THREE.Mesh(endWallGeo, this.mats.obsidianWall);
        northWall.position.set(0, (wallH + 0.4) * 0.5, this.hallNorthZ);
        this.scene.add(northWall);

        const southWall = new THREE.Mesh(endWallGeo, this.mats.obsidianWall);
        southWall.position.set(0, (wallH + 0.4) * 0.5, this.hallSouthZ);
        this.scene.add(southWall);

        // Giebeldreiecke an Nord- und Suedwand bis zur Pyramidenspitze
        const gableShape = new THREE.Shape();
        gableShape.moveTo(-hallW * 0.5, wallH);
        gableShape.lineTo(0, pyramidApexY);
        gableShape.lineTo(hallW * 0.5, wallH);
        gableShape.closePath();
        const gableGeo = this._trackDisposable(new THREE.ShapeGeometry(gableShape));

        const northGableGlass = new THREE.Mesh(gableGeo, this.mats.pyramidGlass);
        northGableGlass.position.set(0, 0, this.hallNorthZ);
        this.scene.add(northGableGlass);

        const southGableGlass = new THREE.Mesh(gableGeo, this.mats.pyramidGlass);
        southGableGlass.position.set(0, 0, this.hallSouthZ);
        this.scene.add(southGableGlass);

        // Monumentaler Stirnfries an der Nordwand
        const friezeTex = buildLouvreFriezeTexture(
            THREE,
            this.maxAniso,
            'MEIN SEELENFUNKE',
            'MANUFAKTUR GIFHORN · LOUVRE GALERIE',
            this.disposables
        );
        const friezeMat = this._trackDisposable(new THREE.MeshStandardMaterial({
            map: friezeTex,
            roughness: 0.32,
            metalness: 0.35,
            envMapIntensity: 0.9,
        }));
        const friezeBacking = new THREE.Mesh(
            this._trackDisposable(new THREE.BoxGeometry(11.6, 2.24, 0.22)),
            this.mats.goldBrushed
        );
        friezeBacking.position.set(0, 4.95, this.hallNorthZ + 0.42);
        this.scene.add(friezeBacking);

        const friezePlane = new THREE.Mesh(
            this._trackDisposable(new THREE.PlaneGeometry(11.36, 2.04)),
            friezeMat
        );
        friezePlane.position.set(0, 4.95, this.hallNorthZ + 0.54);
        this.scene.add(friezePlane);

        // Dezentes Eingangs-Fries an der Suedwand
        const southFriezeTex = buildLouvreFriezeTexture(
            THREE,
            this.maxAniso,
            'UNSERE KOLLEKTION',
            'BEGEHBARER 3D SHOWROOM · SCHWARZ GOLD',
            this.disposables
        );
        const southFriezeMat = this._trackDisposable(new THREE.MeshStandardMaterial({
            map: southFriezeTex,
            roughness: 0.34,
            metalness: 0.32,
        }));
        const southFriezePlane = new THREE.Mesh(
            this._trackDisposable(new THREE.PlaneGeometry(9.6, 1.72)),
            southFriezeMat
        );
        southFriezePlane.rotation.y = Math.PI;
        southFriezePlane.position.set(0, 4.95, this.hallSouthZ - 0.42);
        this.scene.add(southFriezePlane);

        // 5. Ikonisches Louvre-Glaspyramiden-Rautengewoelbe
        const halfSpanX = hallW * 0.5;
        const riseY = pyramidApexY - wallH;
        const slopeLen = Math.hypot(halfSpanX, riseY);
        const slopeAngle = Math.atan2(riseY, halfSpanX);

        // Zwei geneigte Museumsglas-Dachflaechen
        const roofPaneGeo = this._trackDisposable(new THREE.PlaneGeometry(slopeLen, totalLength));

        const leftRoofGlass = new THREE.Mesh(roofPaneGeo, this.mats.pyramidGlass);
        leftRoofGlass.position.set(-halfSpanX * 0.5, wallH + riseY * 0.5, centerZ);
        leftRoofGlass.rotation.order = 'YXZ';
        leftRoofGlass.rotation.y = Math.PI * 0.5;
        leftRoofGlass.rotation.x = -(Math.PI * 0.5 - slopeAngle);
        // Exaktere Ausrichtung ueber Gruppe fuer beide Dachseiten
        this.scene.remove(leftRoofGlass);

        [-1, 1].forEach((sideSign) => {
            const slopeGroup = new THREE.Group();
            slopeGroup.position.set(sideSign * halfSpanX * 0.5, wallH + riseY * 0.5, centerZ);
            slopeGroup.rotation.z = -sideSign * slopeAngle;

            const glassMesh = new THREE.Mesh(
                this._trackDisposable(new THREE.PlaneGeometry(slopeLen, totalLength)),
                this.mats.pyramidGlass
            );
            glassMesh.rotation.x = -Math.PI * 0.5;
            slopeGroup.add(glassMesh);

            // Louvre-Rautenfachwerk (45-Grad Diagonalstreben aus mattschwarzem Architekturstahl + 24K-Goldknoten)
            const uDivs = 6;
            const vStep = 2.6;
            const vDivs = Math.max(8, Math.round(totalLength / vStep));

            const beamSegments = [];
            const goldNodes = [];

            const mapUVToSlope = (u, v) => ({
                x: (u - 0.5) * slopeLen,
                z: (v - 0.5) * totalLength,
            });

            // Querrippen & Laengspfetten
            for (let iu = 0; iu <= uDivs; iu++) {
                const u = iu / uDivs;
                const pA = mapUVToSlope(u, 0);
                const pB = mapUVToSlope(u, 1);
                beamSegments.push([pA.x, 0, pA.z, pB.x, 0, pB.z, 0.055]);
            }

            // Louvre-Rauten-Diagonalen + Gold-Knotenverbinder an allen Kreuzungspunkten
            for (let iv = 0; iv < vDivs; iv++) {
                const v0 = iv / vDivs;
                const v1 = (iv + 1) / vDivs;
                for (let iu = 0; iu < uDivs; iu++) {
                    const u0 = iu / uDivs;
                    const u1 = (iu + 1) / uDivs;

                    const c00 = mapUVToSlope(u0, v0);
                    const c10 = mapUVToSlope(u1, v0);
                    const c01 = mapUVToSlope(u0, v1);
                    const c11 = mapUVToSlope(u1, v1);
                    const mid = mapUVToSlope((u0 + u1) * 0.5, (v0 + v1) * 0.5);

                    beamSegments.push([c00.x, 0, c00.z, c11.x, 0, c11.z, 0.068]);
                    beamSegments.push([c10.x, 0, c10.z, c01.x, 0, c01.z, 0.068]);

                    goldNodes.push([c00.x, -0.02, c00.z]);
                    goldNodes.push([mid.x, -0.02, mid.z]);
                }
            }

            const unitBeamGeo = this._trackDisposable(new THREE.BoxGeometry(1, 0.09, 1));
            const beamInst = new THREE.InstancedMesh(unitBeamGeo, this.mats.archSteel, beamSegments.length);
            const bDummy = new THREE.Object3D();

            for (let b = 0; b < beamSegments.length; b++) {
                const [x1, y1, z1, x2, y2, z2, thick] = beamSegments[b];
                const dx = x2 - x1;
                const dz = z2 - z1;
                const len = Math.hypot(dx, dz);
                bDummy.position.set((x1 + x2) * 0.5, (y1 + y2) * 0.5, (z1 + z2) * 0.5);
                bDummy.rotation.set(0, -Math.atan2(dz, dx), 0);
                bDummy.scale.set(len, 1.0, thick);
                bDummy.updateMatrix();
                beamInst.setMatrixAt(b, bDummy.matrix);
            }
            slopeGroup.add(beamInst);

            // Gebuerstete #C5A059 24K-Gold-Knotenverbinder
            const nodeGeo = this._trackDisposable(new THREE.OctahedronGeometry(0.085, 0));
            const nodeInst = new THREE.InstancedMesh(nodeGeo, this.mats.goldChampagne, goldNodes.length);
            for (let n = 0; n < goldNodes.length; n++) {
                bDummy.position.set(goldNodes[n][0], goldNodes[n][1], goldNodes[n][2]);
                bDummy.rotation.set(0, Math.PI * 0.25, 0);
                bDummy.scale.set(1.0, 0.65, 1.0);
                bDummy.updateMatrix();
                nodeInst.setMatrixAt(n, bDummy.matrix);
            }
            slopeGroup.add(nodeInst);

            this.scene.add(slopeGroup);
        });

        // Goldene Firstleiste an der Pyramidenspitze
        const ridgeBeam = new THREE.Mesh(
            this._trackDisposable(new THREE.BoxGeometry(0.24, 0.24, totalLength)),
            this.mats.goldBrushed
        );
        ridgeBeam.position.set(0, pyramidApexY, centerZ);
        this.scene.add(ridgeBeam);
    }

    _buildExhibitSteles(baySpacing) {
        const THREE = this.THREE;
        const count = this.products.length;
        if (count === 0) return;

        // Wiederverwendbare Stelen-Geometrien
        const shadowGeo = this._trackDisposable(new THREE.PlaneGeometry(2.15, 2.15));
        const plinthBaseGeo = this._trackDisposable(new THREE.BoxGeometry(1.04, 0.08, 1.04));
        const monolithGeo = this._trackDisposable(new THREE.BoxGeometry(0.92, 1.04, 0.92));
        const goldCollarGeo = this._trackDisposable(new THREE.BoxGeometry(0.96, 0.045, 0.96));
        const velvetTopGeo = this._trackDisposable(new THREE.BoxGeometry(0.84, 0.04, 0.84));
        const vitrineBoxGeo = this._trackDisposable(new THREE.BoxGeometry(0.82, 0.86, 0.82));
        const vitrineTopCapGeo = this._trackDisposable(new THREE.BoxGeometry(0.84, 0.022, 0.84));
        const cornerPostGeo = this._trackDisposable(new THREE.BoxGeometry(0.016, 0.86, 0.016));
        const lightConeGeo = this._trackDisposable(new THREE.ConeGeometry(0.72, 3.4, 24, 1, true));
        const plaqueBackingGeo = this._trackDisposable(new THREE.BoxGeometry(0.62, 0.31, 0.03));
        const plaqueFaceGeo = this._trackDisposable(new THREE.PlaneGeometry(0.58, 0.29));
        const clickProxyGeo = this._trackDisposable(new THREE.BoxGeometry(1.16, 2.15, 1.16));
        const invisibleMat = this._trackDisposable(new THREE.MeshBasicMaterial({
            transparent: true,
            opacity: 0.0,
            depthWrite: false,
        }));

        for (let i = 0; i < count; i++) {
            const product = this.products[i];
            const row = Math.floor(i / 2);
            const isSingleLast = (count % 2 === 1) && (i === count - 1);

            let sx;
            let sz;
            let facingYaw;

            if (isSingleLast) {
                sx = 0;
                sz = this.hallNorthZ + 4.8;
                facingYaw = 0; // Zeigt nach +Z zum Besucherweg
            } else {
                const isLeft = (i % 2 === 0);
                sx = isLeft ? -4.35 : 4.35;
                sz = (this.hallSouthZ - 6.4) - row * baySpacing;
                facingYaw = isLeft ? (Math.PI * 0.34) : (-Math.PI * 0.34);
            }

            const steleRoot = new THREE.Group();
            steleRoot.position.set(sx, 0, sz);
            steleRoot.rotation.y = facingYaw;
            this.scene.add(steleRoot);

            // 1. Baked Ambient Occlusion Kontaktschatten auf dem Marmorboden
            const aoMesh = new THREE.Mesh(shadowGeo, this.mats.contactShadow);
            aoMesh.rotation.x = -Math.PI * 0.5;
            aoMesh.position.y = 0.004;
            steleRoot.add(aoMesh);

            // 2. Mattschwarze Louvre-Monolith-Stele + #C5A059 24K-Goldfase
            const plinthBase = new THREE.Mesh(plinthBaseGeo, this.mats.goldBrushed);
            plinthBase.position.y = 0.04;
            steleRoot.add(plinthBase);

            const monolith = new THREE.Mesh(monolithGeo, this.mats.obsidianWall);
            monolith.position.y = 0.08 + 0.52;
            steleRoot.add(monolith);

            const goldCollar = new THREE.Mesh(goldCollarGeo, this.mats.goldChampagne);
            goldCollar.position.y = 1.135;
            steleRoot.add(goldCollar);

            const velvetPad = new THREE.Mesh(velvetTopGeo, this.mats.velvetPad);
            velvetPad.position.y = 1.17;
            steleRoot.add(velvetPad);

            // 3. Museumsglas-Vitrine mit feinen goldenen Eckprofilen
            const vitrineHood = new THREE.Mesh(vitrineBoxGeo, this.mats.vitrineGlass);
            vitrineHood.position.y = 1.19 + 0.43;
            vitrineHood.renderOrder = 4;
            steleRoot.add(vitrineHood);

            const vitrineCap = new THREE.Mesh(vitrineTopCapGeo, this.mats.goldBrushed);
            vitrineCap.position.y = 1.19 + 0.87;
            steleRoot.add(vitrineCap);

            const postOff = 0.405;
            [
                [-postOff, -postOff],
                [postOff, -postOff],
                [-postOff, postOff],
                [postOff, postOff],
            ].forEach(([px, pz]) => {
                const post = new THREE.Mesh(cornerPostGeo, this.mats.goldBrushed);
                post.position.set(px, 1.19 + 0.43, pz);
                steleRoot.add(post);
            });

            // 4. Weicher Museums-Lichtkegel von oben
            const cone = new THREE.Mesh(lightConeGeo, this.mats.spotlightCone);
            cone.position.y = 3.55;
            cone.renderOrder = 3;
            steleRoot.add(cone);

            // 5. Gold-Schwarz Namens- & Preisschild
            const plaqueTex = buildStelePlaqueTexture(THREE, this.maxAniso, product, i, count, this.disposables);
            const plaqueMat = this._trackDisposable(new THREE.MeshBasicMaterial({ map: plaqueTex }));

            const plaqueGroup = new THREE.Group();
            plaqueGroup.position.set(0, 0.82, 0.475);
            plaqueGroup.rotation.x = -0.22;

            const pBack = new THREE.Mesh(plaqueBackingGeo, this.mats.goldBrushed);
            plaqueGroup.add(pBack);

            const pFace = new THREE.Mesh(plaqueFaceGeo, plaqueMat);
            pFace.position.z = 0.017;
            plaqueGroup.add(pFace);
            steleRoot.add(plaqueGroup);

            // 6. Rotierender Exponat-Anker im Inneren der Vitrine
            const exhibitTurntable = new THREE.Group();
            exhibitTurntable.position.set(0, 1.56, 0);
            steleRoot.add(exhibitTurntable);

            // Unsichtbare Klick-Hitbox fuer bequemes Antippen auf Desktop & Mobile
            const hitBox = new THREE.Mesh(clickProxyGeo, invisibleMat);
            hitBox.position.y = 1.08;
            hitBox.userData = { steleIndex: i };
            steleRoot.add(hitBox);
            this.interactiveHitMeshes.push(hitBox);

            // Idealer Betrachter-Standpunkt 2.15m frontal vor der Stele
            const standDist = 2.15;
            const standX = sx + Math.sin(facingYaw) * standDist;
            const standZ = sz + Math.cos(facingYaw) * standDist;

            const steleRecord = {
                index: i,
                product,
                x: sx,
                z: sz,
                facingYaw,
                standX,
                standZ,
                turntable: exhibitTurntable,
                goldCollar,
            };
            this.steles.push(steleRecord);

            // Exponat laden (.glb 3D-Modell oder gerahmtes Museums-Passepartout)
            if (product.glb_url) {
                this._populateExhibitGlb(steleRecord);
            } else {
                this._populateFramedPhotoExhibit(steleRecord);
            }
        }
    }

    _loadGlbCached(url) {
        if (this.glbCache.has(url)) {
            return this.glbCache.get(url);
        }
        const promise = fetch(url, { method: 'GET', credentials: 'same-origin' })
            .then((res) => {
                if (!res.ok) throw new Error(`HTTP ${res.status}`);
                return res.arrayBuffer();
            })
            .then((buf) => parseGlbBinary(this.THREE, buf, this.maxAniso, this.disposables));

        this.glbCache.set(url, promise);
        return promise;
    }

    _populateExhibitGlb(steleRecord) {
        const THREE = this.THREE;
        const { product, turntable } = steleRecord;

        this._loadGlbCached(product.glb_url)
            .then((parsed) => {
                if (this.destroyed) return;
                const modelClone = parsed.scene.clone(true);
                modelClone.updateMatrixWorld(true);

                const bbox = new THREE.Box3().setFromObject(modelClone);
                const size = bbox.getSize(new THREE.Vector3());
                const center = bbox.getCenter(new THREE.Vector3());
                const maxDim = Math.max(0.001, size.x, size.y, size.z);

                const targetSize = product.material_hint === 'crystal' ? 0.48 : 0.44;
                const scale = targetSize / maxDim;
                modelClone.scale.setScalar(scale);
                modelClone.position.set(
                    -center.x * scale,
                    -center.y * scale,
                    -center.z * scale
                );

                this._refineProductPbrMaterial(modelClone, product);
                turntable.add(modelClone);
            })
            .catch(() => {
                if (!this.destroyed) {
                    this._populateFramedPhotoExhibit(steleRecord);
                }
            });
    }

    /**
     * Veredelt die .glb-Materialien physikalisch exakt passend zu K9-Kristallglas, 14K/585-Gold, Silber & Schiefer.
     */
    _refineProductPbrMaterial(modelRoot, product) {
        const THREE = this.THREE;
        const hint = product.material_hint || 'metal';

        modelRoot.traverse((child) => {
            if (!child.isMesh || !child.material) return;
            const origMat = Array.isArray(child.material) ? child.material[0] : child.material;
            const origMap = origMat ? origMat.map : null;
            const matName = origMat ? (origMat.name || '') : '';

            if (hint === 'crystal' || matName === 'Optical_K9_Crystal_Glass') {
                child.renderOrder = 2;
                if (child.geometry && child.geometry.index) {
                    const idxCount = child.geometry.index.count;
                    child.geometry.clearGroups();
                    child.geometry.addGroup(0, idxCount, 0);
                    child.geometry.addGroup(0, idxCount, 1);

                    const matBack = this._trackDisposable(new THREE.MeshPhysicalMaterial({
                        map: origMap,
                        color: 0xeaf6fb,
                        metalness: 0.02,
                        roughness: 0.04,
                        ior: 1.52,
                        reflectivity: 0.92,
                        envMapIntensity: 0.95,
                        transparent: true,
                        opacity: 0.42,
                        depthWrite: false,
                        side: THREE.BackSide,
                    }));

                    const matFront = this._trackDisposable(new THREE.MeshPhysicalMaterial({
                        map: origMap,
                        color: 0xffffff,
                        metalness: 0.04,
                        roughness: 0.02,
                        ior: 1.52,
                        reflectivity: 1.0,
                        clearcoat: 1.0,
                        clearcoatRoughness: 0.02,
                        envMapIntensity: 1.35,
                        transparent: true,
                        opacity: 0.92,
                        depthWrite: false,
                        side: THREE.FrontSide,
                    }));

                    child.material = [matBack, matFront];
                } else {
                    child.material = this._trackDisposable(new THREE.MeshPhysicalMaterial({
                        map: origMap,
                        color: 0xf5fbff,
                        metalness: 0.04,
                        roughness: 0.03,
                        ior: 1.52,
                        clearcoat: 1.0,
                        envMapIntensity: 1.25,
                        transparent: true,
                        opacity: 0.86,
                    }));
                }
            } else if (hint === 'glass') {
                child.renderOrder = 2;
                child.material = this._trackDisposable(new THREE.MeshPhysicalMaterial({
                    map: origMap,
                    color: 0xf7fbff,
                    metalness: 0.05,
                    roughness: 0.05,
                    ior: 1.5,
                    clearcoat: 1.0,
                    clearcoatRoughness: 0.03,
                    envMapIntensity: 1.2,
                    transparent: true,
                    opacity: 0.78,
                    depthWrite: false,
                    side: THREE.DoubleSide,
                }));
            } else if (hint === 'gold') {
                child.material = this._trackDisposable(new THREE.MeshStandardMaterial({
                    map: origMap,
                    color: origMap ? 0xfff6e0 : 0xd8b263,
                    metalness: origMap ? 0.72 : 0.9,
                    roughness: 0.21,
                    envMapIntensity: 1.35,
                    side: THREE.DoubleSide,
                }));
            } else if (hint === 'silver') {
                child.material = this._trackDisposable(new THREE.MeshStandardMaterial({
                    map: origMap,
                    color: origMap ? 0xffffff : 0xe5e9ef,
                    metalness: origMap ? 0.68 : 0.88,
                    roughness: 0.22,
                    envMapIntensity: 1.25,
                    side: THREE.DoubleSide,
                }));
            } else if (hint === 'slate') {
                child.material = this._trackDisposable(new THREE.MeshStandardMaterial({
                    map: origMap,
                    color: origMap ? 0xffffff : 0x262930,
                    metalness: 0.08,
                    roughness: 0.78,
                    envMapIntensity: 0.65,
                    side: THREE.DoubleSide,
                }));
            }
        });
    }

    /**
     * Edles gerahmtes Museums-Aufsteller-Exponat (schwarzes Passepartout + #C5A059 24K-Goldrahmen)
     * fuer Produkte ohne eigenes .glb-Modell (z. B. Geschenkgutscheine & digitale Produkte).
     */
    _populateFramedPhotoExhibit(steleRecord) {
        const THREE = this.THREE;
        const { product, turntable } = steleRecord;

        const frameGroup = new THREE.Group();
        frameGroup.rotation.x = -0.14;

        const outerFrame = new THREE.Mesh(
            this._trackDisposable(new THREE.BoxGeometry(0.46, 0.46, 0.026)),
            this.mats.goldBrushed
        );
        frameGroup.add(outerFrame);

        const passepartout = new THREE.Mesh(
            this._trackDisposable(new THREE.BoxGeometry(0.42, 0.42, 0.03)),
            this.mats.cofferRecess
        );
        frameGroup.add(passepartout);

        const innerGoldBevel = new THREE.Mesh(
            this._trackDisposable(new THREE.BoxGeometry(0.355, 0.355, 0.033)),
            this.mats.goldChampagne
        );
        frameGroup.add(innerGoldBevel);

        const photoMat = this._trackDisposable(new THREE.MeshBasicMaterial({ color: 0xfaf8f5 }));
        const photoPlane = new THREE.Mesh(
            this._trackDisposable(new THREE.PlaneGeometry(0.34, 0.34)),
            photoMat
        );
        photoPlane.position.z = 0.018;
        frameGroup.add(photoPlane);

        // Rückseitige Bildflaeche, damit das Rahmen-Exponat bei sanfter Drehung beidseitig edel wirkt
        const photoPlaneBack = new THREE.Mesh(
            this._trackDisposable(new THREE.PlaneGeometry(0.34, 0.34)),
            photoMat
        );
        photoPlaneBack.rotation.y = Math.PI;
        photoPlaneBack.position.z = -0.018;
        frameGroup.add(photoPlaneBack);

        // Kleiner Messing-Aufstellerfuss
        const standFoot = new THREE.Mesh(
            this._trackDisposable(new THREE.BoxGeometry(0.18, 0.02, 0.14)),
            this.mats.goldBrushed
        );
        standFoot.position.set(0, -0.23, -0.04);
        frameGroup.add(standFoot);

        turntable.add(frameGroup);

        if (product.preview_image_url) {
            const img = new Image();
            img.crossOrigin = 'anonymous';
            img.onload = () => {
                if (this.destroyed) return;
                const tex = this._trackDisposable(new THREE.Texture(img));
                tex.anisotropy = Math.min(this.maxAniso || 4, 4);
                applySrgbColorSpace(THREE, tex);
                tex.needsUpdate = true;
                photoMat.map = tex;
                photoMat.color.setHex(0xffffff);
                photoMat.needsUpdate = true;
            };
            img.src = product.preview_image_url;
        }
    }

    _buildPlayerCharacter() {
        const THREE = this.THREE;
        const lvl = clamp(parseInt(this.playerFunki.level, 10) || 2, 1, 10);
        const modelUrl = this.playerFunki.glb_url || DEFAULT_FUNKI_MODELS[lvl] || DEFAULT_FUNKI_MODELS[2];

        const root = new THREE.Group();
        root.position.set(this.player.x, 0, this.player.z);
        root.rotation.y = this.player.yaw;
        this.scene.add(root);

        // Weicher Kontaktschatten + feiner #C5A059 Kompassring unter Funki
        const footShadow = new THREE.Mesh(
            this._trackDisposable(new THREE.PlaneGeometry(1.15, 1.15)),
            this.mats.contactShadow
        );
        footShadow.rotation.x = -Math.PI * 0.5;
        footShadow.position.y = 0.005;
        root.add(footShadow);

        const baseRing = new THREE.Mesh(
            this._trackDisposable(new THREE.RingGeometry(0.36, 0.41, 32)),
            this.mats.goldBrushed
        );
        baseRing.rotation.x = -Math.PI * 0.5;
        baseRing.position.y = 0.008;
        root.add(baseRing);

        const rig = new THREE.Group();
        root.add(rig);

        this.player.root = root;
        this.player.rig = rig;

        this._loadGlbCached(modelUrl)
            .then((parsed) => {
                if (this.destroyed) return;
                const funkiScene = parsed.scene.clone(true);
                funkiScene.updateMatrixWorld(true);

                const bbox = new THREE.Box3().setFromObject(funkiScene);
                const rawH = Math.max(0.001, bbox.max.y - bbox.min.y);
                const cx = (bbox.min.x + bbox.max.x) * 0.5;
                const cz = (bbox.min.z + bbox.max.z) * 0.5;
                const minY = bbox.min.y;

                const targetH = lvl === 1 ? 1.32 : 1.56;
                const scale = targetH / rawH;
                funkiScene.scale.setScalar(scale);
                funkiScene.position.set(-cx * scale, -minY * scale, -cz * scale);

                // Verifizierte Funki-Frontausrichtung: rotation.y = -Math.PI * 0.5 zeigt nach lokal +Z
                const orient = new THREE.Group();
                orient.rotation.y = FUNKI_GLB_FRONT_ROTATION_Y;
                orient.add(funkiScene);
                rig.add(orient);
            })
            .catch(() => {
                if (this.destroyed) return;
                // Schlichter Schwarz-Gold Fallback-Avatar falls .glb im Testumfeld nicht geladen wird
                const body = new THREE.Mesh(
                    this._trackDisposable(new THREE.CylinderGeometry(0.22, 0.28, 0.95, 16)),
                    this.mats.obsidianWall
                );
                body.position.y = 0.52;
                rig.add(body);

                const head = new THREE.Mesh(
                    this._trackDisposable(new THREE.BoxGeometry(0.42, 0.34, 0.34)),
                    this.mats.goldBrushed
                );
                head.position.y = 1.18;
                rig.add(head);
            });
    }

    _bindEvents() {
        const el = this.canvas;
        window.addEventListener('resize', this._boundResize);
        window.addEventListener('keydown', this._boundKeyDown);
        window.addEventListener('keyup', this._boundKeyUp);

        el.addEventListener('contextmenu', this._boundContextMenu);
        el.addEventListener('pointerdown', this._boundPointerDown);
        el.addEventListener('pointermove', this._boundPointerMove);
        el.addEventListener('pointerup', this._boundPointerUp);
        el.addEventListener('pointercancel', this._boundPointerUp);
        el.addEventListener('wheel', this._boundWheel, { passive: false });
        el.addEventListener('touchstart', this._boundTouchStart, { passive: true });
        el.addEventListener('touchmove', this._boundTouchMove, { passive: true });
    }

    _onKeyDown(e) {
        if (!this.running) return;
        const tag = (e.target && e.target.tagName) ? e.target.tagName.toLowerCase() : '';
        if (tag === 'input' || tag === 'textarea' || tag === 'select') return;

        const code = e.code || e.key;
        this.keys[code] = true;

        if (code === 'Enter' || code === 'KeyE') {
            const nearest = this._findNearestStele(4.2);
            const targetStele = nearest || this.steles[this.activeIndex];
            if (targetStele && this.onSelectProduct) {
                this.activeIndex = targetStele.index;
                if (this.onActiveExhibitChange) {
                    this.onActiveExhibitChange(targetStele.index, targetStele.product);
                }
                this.onSelectProduct(targetStele.product, targetStele.index);
            }
        }
    }

    _onKeyUp(e) {
        const code = e.code || e.key;
        this.keys[code] = false;
    }

    _onPointerDown(e) {
        if (!this.running) return;
        this.camControl.isDragging = true;
        this.camControl.dragMoved = 0;
        this.camControl.lastX = e.clientX;
        this.camControl.lastY = e.clientY;
        this.canvas.style.cursor = 'grabbing';
        try {
            this.canvas.setPointerCapture(e.pointerId);
        } catch (err) {
            // PointerCapture optional
        }
    }

    _onPointerMove(e) {
        if (!this.running || !this.camControl.isDragging) return;
        const dx = e.clientX - this.camControl.lastX;
        const dy = e.clientY - this.camControl.lastY;
        this.camControl.lastX = e.clientX;
        this.camControl.lastY = e.clientY;
        this.camControl.dragMoved += Math.hypot(dx, dy);

        this.camControl.desiredYaw -= dx * 0.0055;
        this.camControl.desiredPitch = clamp(this.camControl.desiredPitch + dy * 0.0042, 0.06, 0.78);
    }

    _onPointerUp(e) {
        if (!this.camControl.isDragging) return;
        this.camControl.isDragging = false;
        this.canvas.style.cursor = 'grab';
        try {
            this.canvas.releasePointerCapture(e.pointerId);
        } catch (err) {
            // PointerCapture optional
        }

        if (this.camControl.dragMoved < 7 && (e.button === 0 || e.pointerType === 'touch')) {
            this._handleCanvasTap(e.clientX, e.clientY);
        }
    }

    _onWheel(e) {
        if (!this.running) return;
        e.preventDefault();
        const factor = e.deltaY > 0 ? 1.09 : 0.91;
        this.camControl.desiredDistance = clamp(this.camControl.desiredDistance * factor, 2.5, 7.8);
    }

    _onTouchStart(e) {
        if (e.touches.length === 2) {
            const dx = e.touches[0].clientX - e.touches[1].clientX;
            const dy = e.touches[0].clientY - e.touches[1].clientY;
            this.camControl.pinchDist = Math.hypot(dx, dy);
        }
    }

    _onTouchMove(e) {
        if (e.touches.length === 2 && this.camControl.pinchDist > 8) {
            const dx = e.touches[0].clientX - e.touches[1].clientX;
            const dy = e.touches[0].clientY - e.touches[1].clientY;
            const dist = Math.hypot(dx, dy);
            const scale = this.camControl.pinchDist / Math.max(8, dist);
            this.camControl.pinchDist = dist;
            this.camControl.desiredDistance = clamp(this.camControl.desiredDistance * scale, 2.5, 7.8);
        }
    }

    _handleCanvasTap(clientX, clientY) {
        if (!this.canvas || !this.camera) return;
        const rect = this.canvas.getBoundingClientRect();
        this.pointerNdc.x = ((clientX - rect.left) / Math.max(1, rect.width)) * 2 - 1;
        this.pointerNdc.y = -((clientY - rect.top) / Math.max(1, rect.height)) * 2 + 1;
        this.raycaster.setFromCamera(this.pointerNdc, this.camera);

        // 1. Pruefen ob eine Produkt-Stele / ein Exponat angetippt wurde
        if (this.interactiveHitMeshes.length > 0) {
            const steleHits = this.raycaster.intersectObjects(this.interactiveHitMeshes, false);
            if (steleHits.length > 0) {
                const idx = steleHits[0].object.userData.steleIndex;
                this.goToExhibit(idx, true);
                return;
            }
        }

        // 2. Tap-to-Walk auf den Nero-Marquina-Marmorboden
        if (this.floorMesh) {
            const floorHits = this.raycaster.intersectObject(this.floorMesh, false);
            if (floorHits.length > 0) {
                const pt = floorHits[0].point;
                const tx = clamp(pt.x, -this.hallHalfW + 1.2, this.hallHalfW - 1.2);
                const tz = clamp(pt.z, this.hallNorthZ + 1.8, this.hallSouthZ - 1.8);
                this.player.autoTarget = { x: tx, z: tz };
                this.player.autoLookAt = null;
                this.player.pendingSelectIdx = null;

                if (this.walkTargetRing) {
                    this.walkTargetRing.position.set(tx, 0.012, tz);
                    this.walkTargetTimer = 1.35;
                }
            }
        }
    }

    goToExhibit(index, openDrawer = true) {
        if (this.steles.length === 0) return;
        const safeIdx = ((index % this.steles.length) + this.steles.length) % this.steles.length;
        const stele = this.steles[safeIdx];
        this.activeIndex = safeIdx;

        this.player.autoTarget = { x: stele.standX, z: stele.standZ };
        this.player.autoLookAt = { x: stele.x, z: stele.z };

        // Kamera sanft Richtung Exponat ausrichten
        const lookYaw = Math.atan2(this.player.autoTarget.x - stele.x, this.player.autoTarget.z - stele.z);
        this.camControl.desiredYaw = lookYaw;
        this.camControl.desiredPitch = 0.23;
        this.camControl.desiredDistance = 3.65;

        if (this.walkTargetRing) {
            this.walkTargetRing.position.set(stele.standX, 0.012, stele.standZ);
            this.walkTargetTimer = 1.2;
        }

        if (this.onActiveExhibitChange) {
            this.onActiveExhibitChange(safeIdx, stele.product);
        }

        if (openDrawer && this.onSelectProduct) {
            this.onSelectProduct(stele.product, safeIdx);
        }
    }

    setJoystickVector(nx, ny) {
        this.joystick.x = clamp(Number(nx) || 0, -1, 1);
        this.joystick.y = clamp(Number(ny) || 0, -1, 1);
    }

    _findNearestStele(maxRadius) {
        let best = null;
        let bestDist = maxRadius;
        for (let i = 0; i < this.steles.length; i++) {
            const st = this.steles[i];
            const d = Math.hypot(this.player.x - st.x, this.player.z - st.z);
            if (d < bestDist) {
                bestDist = d;
                best = st;
            }
        }
        return best;
    }

    _resolveCollisions(nx, nz) {
        let x = clamp(nx, -this.hallHalfW + 1.0, this.hallHalfW - 1.0);
        let z = clamp(nz, this.hallNorthZ + 1.5, this.hallSouthZ - 1.4);

        const minSteleRadius = 0.98;
        for (let i = 0; i < this.steles.length; i++) {
            const st = this.steles[i];
            const dx = x - st.x;
            const dz = z - st.z;
            const dist = Math.hypot(dx, dz);
            if (dist < minSteleRadius && dist > 0.0001) {
                const push = (minSteleRadius - dist);
                x += (dx / dist) * push;
                z += (dz / dist) * push;
            }
        }
        return { x, z };
    }

    _tick(nowMs) {
        if (!this.running || this.destroyed) return;
        this.rafId = requestAnimationFrame(this._boundTick);

        const dt = clamp((nowMs - this.lastTimeMs) / 1000, 0.001, 0.05);
        this.lastTimeMs = nowMs;
        this.elapsedTime += dt;

        // 1. Bewegung aus Tastatur (WASD / Pfeiltasten) + Mobile-Stick
        let inputForward = 0;
        let inputRight = 0;

        if (this.keys.KeyW || this.keys.ArrowUp) inputForward += 1;
        if (this.keys.KeyS || this.keys.ArrowDown) inputForward -= 1;
        if (this.keys.KeyD || this.keys.ArrowRight) inputRight += 1;
        if (this.keys.KeyA || this.keys.ArrowLeft) inputRight -= 1;

        if (Math.abs(this.joystick.x) > 0.04 || Math.abs(this.joystick.y) > 0.04) {
            inputRight += this.joystick.x;
            inputForward -= this.joystick.y;
        }

        const hasManualInput = Math.hypot(inputForward, inputRight) > 0.05;
        if (hasManualInput) {
            this.player.autoTarget = null;
            this.player.autoLookAt = null;
        }

        const moveSpeed = 5.2;
        let vx = 0;
        let vz = 0;

        if (hasManualInput) {
            const len = Math.max(1.0, Math.hypot(inputForward, inputRight));
            const nf = inputForward / len;
            const nr = inputRight / len;
            const sinY = Math.sin(this.camControl.yaw);
            const cosY = Math.cos(this.camControl.yaw);

            // Kamera steht bei (+sinY, +cosY) relativ zu Funki -> Vorwaerts ist (-sinY, -cosY)
            vx = (-sinY * nf + cosY * nr) * moveSpeed;
            vz = (-cosY * nf - sinY * nr) * moveSpeed;
        } else if (this.player.autoTarget) {
            const dx = this.player.autoTarget.x - this.player.x;
            const dz = this.player.autoTarget.z - this.player.z;
            const dist = Math.hypot(dx, dz);
            if (dist > 0.08) {
                const stepSpeed = Math.min(moveSpeed * 1.18, Math.max(1.8, dist * 3.4));
                vx = (dx / dist) * stepSpeed;
                vz = (dz / dist) * stepSpeed;
            } else {
                this.player.autoTarget = null;
                if (this.player.autoLookAt) {
                    const faceYaw = Math.atan2(
                        this.player.autoLookAt.x - this.player.x,
                        this.player.autoLookAt.z - this.player.z
                    );
                    this.player.yaw = faceYaw;
                    this.player.autoLookAt = null;
                }
            }
        }

        const speedMag = Math.hypot(vx, vz);
        this.player.isMoving = speedMag > 0.08;

        if (this.player.isMoving) {
            const nextPos = this._resolveCollisions(
                this.player.x + vx * dt,
                this.player.z + vz * dt
            );
            this.player.x = nextPos.x;
            this.player.z = nextPos.z;

            const moveYaw = Math.atan2(vx, vz);
            this.player.yaw = lerpAngle(this.player.yaw, moveYaw, 1.0 - Math.exp(-12.0 * dt));
            this.player.walkPhase += dt * (speedMag * 2.35);

            // Aktives Exponat beim Vorbeigehen aktualisieren
            const near = this._findNearestStele(3.5);
            if (near && near.index !== this.activeIndex) {
                this.activeIndex = near.index;
                if (this.onActiveExhibitChange) {
                    this.onActiveExhibitChange(near.index, near.product);
                }
            }
        }

        // 2. Lebendige Funki Lauf- & Ruhe-Animation
        if (this.player.root && this.player.rig) {
            this.player.root.position.set(this.player.x, 0, this.player.z);
            this.player.root.rotation.y = this.player.yaw;

            if (this.player.isMoving) {
                const bob = Math.abs(Math.sin(this.player.walkPhase)) * 0.065;
                const sway = Math.sin(this.player.walkPhase) * 0.045;
                this.player.rig.position.y = bob;
                this.player.rig.rotation.z = sway;
                this.player.rig.rotation.x = 0.05;
            } else {
                const breath = Math.sin(this.elapsedTime * 2.1) * 0.018;
                this.player.rig.position.y = Math.max(0, breath);
                this.player.rig.rotation.z = lerp(this.player.rig.rotation.z, 0, 8 * dt);
                this.player.rig.rotation.x = lerp(this.player.rig.rotation.x, 0, 8 * dt);
            }
        }

        // 3. Sanfte Rotation aller Exponate in ihren Vitrinen
        for (let i = 0; i < this.steles.length; i++) {
            const st = this.steles[i];
            if (st.turntable) {
                st.turntable.rotation.y = this.elapsedTime * 0.34 + i * 0.6;
                st.turntable.position.y = 1.56 + Math.sin(this.elapsedTime * 1.5 + i) * 0.012;
            }
        }

        // Tap-to-Walk Zielring sanft ausblenden
        if (this.walkTargetRing && this.walkTargetTimer > 0) {
            this.walkTargetTimer = Math.max(0, this.walkTargetTimer - dt);
            const pulse = 1.0 + (1.35 - this.walkTargetTimer) * 0.35;
            this.walkTargetRing.scale.set(pulse, pulse, 1);
            this.walkTargetRing.material.opacity = clamp(this.walkTargetTimer * 0.65, 0, 0.75);
        }

        // 4. Gedämpfte Museums-Verfolgerkamera (kollisionsfrei innerhalb der Halle)
        const camDamp = 1.0 - Math.exp(-9.5 * dt);
        this.camControl.yaw = lerpAngle(this.camControl.yaw, this.camControl.desiredYaw, camDamp);
        this.camControl.pitch = lerp(this.camControl.pitch, this.camControl.desiredPitch, camDamp);
        this.camControl.distance = lerp(this.camControl.distance, this.camControl.desiredDistance, camDamp);

        const targetX = this.player.x;
        const targetY = 1.38;
        const targetZ = this.player.z;

        this.camControl.lookAtCurrent.x = lerp(this.camControl.lookAtCurrent.x, targetX, camDamp);
        this.camControl.lookAtCurrent.y = lerp(this.camControl.lookAtCurrent.y, targetY, camDamp);
        this.camControl.lookAtCurrent.z = lerp(this.camControl.lookAtCurrent.z, targetZ, camDamp);

        const horizDist = Math.cos(this.camControl.pitch) * this.camControl.distance;
        const rawCamX = this.camControl.lookAtCurrent.x + Math.sin(this.camControl.yaw) * horizDist;
        const rawCamY = this.camControl.lookAtCurrent.y + Math.sin(this.camControl.pitch) * this.camControl.distance;
        const rawCamZ = this.camControl.lookAtCurrent.z + Math.cos(this.camControl.yaw) * horizDist;

        this.camera.position.set(
            clamp(rawCamX, -this.hallHalfW + 0.65, this.hallHalfW - 0.65),
            clamp(rawCamY, 0.85, 11.5),
            clamp(rawCamZ, this.hallNorthZ + 0.75, this.hallSouthZ - 0.55)
        );
        this.camera.lookAt(this.camControl.lookAtCurrent);

        this.renderer.render(this.scene, this.camera);
    }

    resize() {
        if (!this.renderer || !this.canvas) return;
        const parent = this.canvas.parentElement;
        const w = Math.max(1, parent ? parent.clientWidth : window.innerWidth);
        const h = Math.max(1, parent ? parent.clientHeight : window.innerHeight);

        if (!this.camera) {
            this.camera = new this.THREE.PerspectiveCamera(50, w / h, 0.1, 240);
        } else {
            this.camera.aspect = w / h;
            this.camera.updateProjectionMatrix();
        }

        const maxDpr = window.innerWidth < 768 ? 1.35 : 1.75;
        this.renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, maxDpr));
        this.renderer.setSize(w, h, false);
    }

    destroy() {
        this.running = false;
        this.destroyed = true;
        if (this.rafId) {
            cancelAnimationFrame(this.rafId);
            this.rafId = 0;
        }

        window.removeEventListener('resize', this._boundResize);
        window.removeEventListener('keydown', this._boundKeyDown);
        window.removeEventListener('keyup', this._boundKeyUp);

        if (this.canvas) {
            this.canvas.removeEventListener('contextmenu', this._boundContextMenu);
            this.canvas.removeEventListener('pointerdown', this._boundPointerDown);
            this.canvas.removeEventListener('pointermove', this._boundPointerMove);
            this.canvas.removeEventListener('pointerup', this._boundPointerUp);
            this.canvas.removeEventListener('pointercancel', this._boundPointerUp);
            this.canvas.removeEventListener('wheel', this._boundWheel);
            this.canvas.removeEventListener('touchstart', this._boundTouchStart);
            this.canvas.removeEventListener('touchmove', this._boundTouchMove);
        }

        for (let i = 0; i < this.disposables.length; i++) {
            const d = this.disposables[i];
            if (d && typeof d.dispose === 'function') {
                d.dispose();
            }
        }
        this.disposables = [];
        this.glbCache.clear();
        this.steles = [];
        this.interactiveHitMeshes = [];

        if (this.renderer) {
            this.renderer.dispose();
            if (typeof this.renderer.forceContextLoss === 'function') {
                try {
                    this.renderer.forceContextLoss();
                } catch (err) {
                    // Context bereits freigegeben
                }
            }
            this.renderer = null;
        }
        this.scene = null;
        this.camera = null;
    }
}

export function createLouvreShowroom(options) {
    const instance = new SeelenfunkeLouvreShowroom3D(options);
    instance.init();
    return instance;
}
