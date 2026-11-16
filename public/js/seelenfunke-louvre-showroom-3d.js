/*!
 * Mein-Seelenfunke · Begehbarer 3D-Louvre-Showroom (60-FPS Schwarz-Gold PBR & Gelenk-Rig Engine)
 * On-Demand ES-Modul: 0 KB Initial-Last auf /shop, 0 % CPU/GPU nach dem Schliessen.
 */

const TWO_PI = Math.PI * 2;
const PROXIMITY_FOCUS_ENTER = 3.4;
const PROXIMITY_FOCUS_LEAVE = 3.75;

function clamp(v, lo, hi) {
    return Math.max(lo, Math.min(hi, v));
}

function lerp(a, b, t) {
    return a + (b - a) * t;
}

function lerpAngle(a, b, t) {
    let diff = ((b - a + Math.PI) % TWO_PI) - Math.PI;
    if (diff < -Math.PI) diff += TWO_PI;
    return a + diff * t;
}

function hexToRgb(hex) {
    const clean = String(hex).replace('#', '');
    const n = parseInt(clean, 16);
    return [(n >> 16) & 255, (n >> 8) & 255, n & 255];
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
 * Erzeugt eine schnelle, vorberechnete Studio-MatCap-Textur fuer den voll artikulierten Avatar
 * (null Echtzeit-Lichtberechnung pro Gliedmasse, edle 24K-Gold- und Obsidian-Glanzkanten).
 */
function buildStudioMatcapTexture(THREE, baseHex, specHex, rimHex, specPower, rimStrength, disposables) {
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

    const lx1 = -0.34;
    const ly1 = 0.62;
    const lz1 = 0.706;
    const hx1 = -0.19;
    const hy1 = 0.38;
    const hz1 = 0.905;
    const lx2 = 0.50;
    const ly2 = 0.34;
    const lz2 = 0.796;
    const hx2 = 0.31;
    const hy2 = 0.22;
    const hz2 = 0.925;
    const half = size * 0.5;

    for (let y = 0; y < size; y++) {
        const ny = (half - y - 0.5) / half;
        for (let x = 0; x < size; x++) {
            const nx = (x + 0.5 - half) / half;
            const r2 = nx * nx + ny * ny;
            let r;
            let g;
            let b;

            if (r2 <= 1.0) {
                const nz = Math.sqrt(Math.max(0, 1.0 - r2));
                const ndl1 = Math.max(0, nx * lx1 + ny * ly1 + nz * lz1);
                const ndl2 = Math.max(0, nx * lx2 + ny * ly2 + nz * lz2);
                const skyBounce = 0.38 + 0.32 * (ny * 0.5 + 0.5);
                const horizon = Math.exp(-Math.pow((ny + 0.14) * 5.0, 2)) * (specPower >= 22 ? 0.13 : 0.04);
                const hemi = skyBounce + 0.50 * ndl1 + 0.22 * ndl2 + horizon;

                const ndh1 = Math.max(0, nx * hx1 + ny * hy1 + nz * hz1);
                const ndh2 = Math.max(0, nx * hx2 + ny * hy2 + nz * hz2);
                const spBroad = Math.pow(ndh1, Math.max(6, specPower * 0.45)) * 0.28;
                const spCrisp = Math.pow(ndh1, specPower * 1.35) * 0.82 + Math.pow(ndh2, specPower * 0.9) * 0.20;
                const sp = spBroad + spCrisp;
                const fresnel = Math.pow(1.0 - nz, 2.4) * rimStrength;

                r = base[0] * hemi + spec[0] * sp + rim[0] * fresnel;
                g = base[1] * hemi + spec[1] * sp + rim[1] * fresnel;
                b = base[2] * hemi + spec[2] * sp + rim[2] * fresnel;
            } else {
                const len = Math.sqrt(r2);
                const uy = ny / len;
                const h2 = 0.36 + 0.26 * (uy * 0.5 + 0.5);
                r = base[0] * h2 + rim[0] * rimStrength * 0.9;
                g = base[1] * h2 + rim[1] * rimStrength * 0.9;
                b = base[2] * h2 + rim[2] * rimStrength * 0.9;
            }

            const p = (y * size + x) * 4;
            d[p] = clamp(Math.round(r), 0, 255);
            d[p + 1] = clamp(Math.round(g), 0, 255);
            d[p + 2] = clamp(Math.round(b), 0, 255);
            d[p + 3] = 255;
        }
    }

    ctx.putImageData(img, 0, 0);
    const tex = new THREE.CanvasTexture(c);
    tex.minFilter = THREE.LinearFilter;
    tex.magFilter = THREE.LinearFilter;
    applySrgbColorSpace(THREE, tex);
    tex.needsUpdate = true;
    if (disposables) disposables.push(tex);
    return tex;
}

/**
 * Skaliert grosse Texturbilder vor dem GPU-Upload auf maximal maxSide x maxSide herunter,
 * damit beim Nachladen niemals Frame-Spikes entstehen.
 */
function downscaleImageToCanvas(imgEl, maxSide) {
    const srcW = imgEl.naturalWidth || imgEl.width || 256;
    const srcH = imgEl.naturalHeight || imgEl.height || 256;
    if (srcW <= maxSide && srcH <= maxSide) {
        return imgEl;
    }
    const scale = Math.min(maxSide / srcW, maxSide / srcH);
    const dstW = Math.max(64, Math.round(srcW * scale));
    const dstH = Math.max(64, Math.round(srcH * scale));
    const c = document.createElement('canvas');
    c.width = dstW;
    c.height = dstH;
    const ctx = c.getContext('2d');
    ctx.drawImage(imgEl, 0, 0, dstW, dstH);
    return c;
}

/**
 * Nativer, abhaengigkeitsfreier GLB 2.0 Parser fuer komprimierte Seelenfunke Produkt-Modelle.
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
                const source = downscaleImageToCanvas(imgEl, 512);
                const tex = new THREE.Texture(source);
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

    const tmpMat4 = new THREE.Matrix4();

    function buildNodeAsync(nodeIdx) {
        const nDef = (jsonObj.nodes && jsonObj.nodes[nodeIdx]) ? jsonObj.nodes[nodeIdx] : null;
        if (!nDef) return Promise.resolve(new THREE.Group());

        const nodeObj = new THREE.Group();
        if (Array.isArray(nDef.matrix) && nDef.matrix.length === 16) {
            tmpMat4.fromArray(nDef.matrix);
            tmpMat4.decompose(nodeObj.position, nodeObj.quaternion, nodeObj.scale);
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
 * Erzeugt ein vorberechnetes Louvre-Studio-Environment via PMREMGenerator,
 * damit K9-Kristallglas und 14K-Ion-Plating-Gold scharfe Lichtkanten ohne Echtzeit-Spotlights zeigen.
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

    addLightPanel(18, 18, 0xfffcf5, 0, 14, 0, Math.PI * 0.5, 0, 0);
    addLightPanel(14, 5.5, 0xfff7e6, 0, 9.5, -11, 0.52, 0, 0);
    addLightPanel(14, 5.5, 0xfff7e6, 0, 9.5, 11, -0.52, 0, 0);
    addLightPanel(14, 5.5, 0xf4f7fc, -11, 9.0, 0, 0, Math.PI * 0.5, -0.45);
    addLightPanel(14, 5.5, 0xf4f7fc, 11, 9.0, 0, 0, -Math.PI * 0.5, 0.45);

    addLightPanel(20, 1.4, 0xe6c687, -13, 2.8, 0, 0, Math.PI * 0.5, 0);
    addLightPanel(20, 1.4, 0xe6c687, 13, 2.8, 0, 0, -Math.PI * 0.5, 0);
    addLightPanel(16, 1.2, 0xc5a059, 0, 3.2, -13, 0, 0, 0);
    addLightPanel(16, 1.2, 0xdbe6f4, 0, 3.6, 13, 0, Math.PI, 0);
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
 * Schneller, nahtloser Nero-Marquina-Marmorboden (1024x1024 Desktop / 512x512 Mobile, lokale Bounding-Fills).
 */
function buildNeroMarquinaFloorMaps(THREE, maxAniso, isMobile, repeatX, repeatZ, disposables) {
    const S = isMobile ? 512 : 1024;

    const cAlbedo = document.createElement('canvas');
    cAlbedo.width = S;
    cAlbedo.height = S;
    const ctxA = cAlbedo.getContext('2d');

    const cRough = document.createElement('canvas');
    cRough.width = S;
    cRough.height = S;
    const ctxR = cRough.getContext('2d');

    const rng = mulberry32(20261116);

    ctxA.fillStyle = '#090b10';
    ctxA.fillRect(0, 0, S, S);

    ctxR.fillStyle = 'rgb(40, 40, 40)';
    ctxR.fillRect(0, 0, S, S);

    // Lokale Begrenzungsrechtecke statt Fullscreen-Fills (90 % schnellere Initialisierung)
    for (let i = 0; i < 42; i++) {
        const cx = rng() * S;
        const cy = rng() * S;
        const rad = (0.08 + rng() * 0.20) * S;
        const bright = rng() > 0.5;
        const bx = Math.max(0, Math.floor(cx - rad));
        const by = Math.max(0, Math.floor(cy - rad));
        const bw = Math.min(S - bx, Math.ceil(rad * 2));
        const bh = Math.min(S - by, Math.ceil(rad * 2));

        const gA = ctxA.createRadialGradient(cx, cy, rad * 0.05, cx, cy, rad);
        gA.addColorStop(0, bright ? 'rgba(24, 29, 40, 0.24)' : 'rgba(4, 5, 8, 0.30)');
        gA.addColorStop(1, 'rgba(9, 11, 16, 0)');
        ctxA.fillStyle = gA;
        ctxA.fillRect(bx, by, bw, bh);

        const gR = ctxR.createRadialGradient(cx, cy, rad * 0.05, cx, cy, rad);
        gR.addColorStop(0, bright ? 'rgba(56, 56, 56, 0.18)' : 'rgba(28, 28, 28, 0.18)');
        gR.addColorStop(1, 'rgba(40, 40, 40, 0)');
        ctxR.fillStyle = gR;
        ctxR.fillRect(bx, by, bw, bh);
    }

    const drawMarbleVein = (isGold, thicknessScale) => {
        let x = rng() * S;
        let y = rng() * S;
        let angle = (rng() * 0.65 + 0.18) * Math.PI * (rng() > 0.5 ? 1 : -1);
        const steps = 52 + Math.floor(rng() * 44);
        const stepLen = (S / 90) * (0.75 + rng() * 0.65);

        ctxA.beginPath();
        ctxR.beginPath();
        ctxA.moveTo(x, y);
        ctxR.moveTo(x, y);

        for (let s = 0; s < steps; s++) {
            angle += (rng() - 0.5) * 0.44;
            x += Math.cos(angle) * stepLen;
            y += Math.sin(angle) * stepLen;
            ctxA.lineTo(x, y);
            ctxR.lineTo(x, y);

            if (rng() > 0.87) {
                let bx = x;
                let by = y;
                let bAng = angle + (rng() > 0.5 ? 0.58 : -0.58);
                ctxA.moveTo(bx, by);
                for (let bs = 0; bs < 12; bs++) {
                    bAng += (rng() - 0.5) * 0.35;
                    bx += Math.cos(bAng) * stepLen * 0.6;
                    by += Math.sin(bAng) * stepLen * 0.6;
                    ctxA.lineTo(bx, by);
                }
                ctxA.moveTo(x, y);
            }
        }

        const lw = (isGold ? (1.1 + rng() * 2.0) : (0.9 + rng() * 2.3)) * (S / 1024) * thicknessScale;
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
    };

    for (let v = 0; v < 32; v++) drawMarbleVein(false, 1.0);
    for (let gv = 0; gv < 16; gv++) drawMarbleVein(true, 0.95);

    const slabs = 4;
    const step = S / slabs;
    for (let i = 0; i <= slabs; i++) {
        const pos = Math.round(i * step);
        ctxA.fillStyle = 'rgba(197, 160, 89, 0.16)';
        ctxA.fillRect(pos - 1, 0, 2, S);
        ctxA.fillRect(0, pos - 1, S, 2);

        ctxR.fillStyle = 'rgb(126, 126, 126)';
        ctxR.fillRect(pos - 2, 0, 3, S);
        ctxR.fillRect(0, pos - 2, S, 3);
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
    };
}

/**
 * Weicher vorberechneter Ambient-Occlusion Kontaktschatten auf dem Marmorboden (null Echtzeit-Shadow-Maps).
 */
function buildContactShadowTexture(THREE, disposables) {
    const S = 128;
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
    tex.minFilter = THREE.LinearFilter;
    tex.magFilter = THREE.LinearFilter;
    tex.needsUpdate = true;
    if (disposables) disposables.push(tex);
    return tex;
}

/**
 * Goldener Stirnfries "MEIN SEELENFUNKE" an der Hauptwand des Louvre.
 */
function buildLouvreFriezeTexture(THREE, maxAniso, titleText, subText, disposables) {
    const W = 1024;
    const H = 256;
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
    ctx.lineWidth = 5;
    ctx.strokeRect(10, 10, W - 20, H - 20);

    ctx.strokeStyle = 'rgba(230, 198, 135, 0.42)';
    ctx.lineWidth = 1.5;
    ctx.strokeRect(20, 20, W - 40, H - 40);

    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';

    const goldGrad = ctx.createLinearGradient(0, H * 0.18, 0, H * 0.72);
    goldGrad.addColorStop(0.0, '#FFF8E1');
    goldGrad.addColorStop(0.38, '#E6C687');
    goldGrad.addColorStop(0.72, '#C5A059');
    goldGrad.addColorStop(1.0, '#9A7738');

    ctx.font = '700 58px Georgia, "Times New Roman", serif';
    if ('letterSpacing' in ctx) {
        ctx.letterSpacing = '8px';
    }
    ctx.fillStyle = 'rgba(0, 0, 0, 0.85)';
    ctx.fillText(titleText, W * 0.5 + 2, H * 0.43 + 3, W - 80);
    ctx.fillStyle = goldGrad;
    ctx.fillText(titleText, W * 0.5, H * 0.43, W - 80);

    ctx.font = '600 19px system-ui, -apple-system, "Segoe UI", sans-serif';
    if ('letterSpacing' in ctx) {
        ctx.letterSpacing = '5px';
    }
    ctx.fillStyle = '#C5A059';
    ctx.fillText(subText, W * 0.5, H * 0.77, W - 90);

    const tex = new THREE.CanvasTexture(c);
    tex.generateMipmaps = true;
    tex.minFilter = THREE.LinearMipmapLinearFilter;
    tex.magFilter = THREE.LinearFilter;
    tex.anisotropy = Math.min(maxAniso || 4, 8);
    applySrgbColorSpace(THREE, tex);
    tex.needsUpdate = true;
    if (disposables) disposables.push(tex);
    return tex;
}

/**
 * Stufe 1: Helles Seelenfunke Light-Mode Museums-Schild (3D Plaque) direkt an der Vorderkante jedes Podests.
 * Klar lesbar mit Produktname & Preis ("ab XX,XX €"), Mipmapping und Anisotropie.
 */
function buildStelePlaqueTexture(THREE, maxAniso, product, disposables) {
    const W = 512;
    const H = 256;
    const c = document.createElement('canvas');
    c.width = W;
    c.height = H;
    const ctx = c.getContext('2d');

    ctx.fillStyle = '#FAF8F5';
    ctx.fillRect(0, 0, W, H);

    ctx.strokeStyle = '#C5A059';
    ctx.lineWidth = 8;
    ctx.strokeRect(10, 10, W - 20, H - 20);

    ctx.strokeStyle = 'rgba(197, 160, 89, 0.45)';
    ctx.lineWidth = 2;
    ctx.strokeRect(22, 22, W - 44, H - 44);

    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';

    const cat = String(product.category_name || 'KOLLEKTION').toUpperCase();
    ctx.fillStyle = '#9A7738';
    ctx.font = '700 18px system-ui, -apple-system, sans-serif';
    ctx.fillText(cat, W * 0.5, 52, W - 72);

    const name = String(product.name || 'Unikat');
    ctx.fillStyle = '#111827';
    ctx.font = '700 30px Georgia, serif';
    if (name.length > 24) {
        const words = name.split(' ');
        let line1 = '';
        let line2 = '';
        for (let i = 0; i < words.length; i++) {
            if ((line1 + ' ' + words[i]).trim().length <= 22 || line1 === '') {
                line1 = (line1 + ' ' + words[i]).trim();
            } else {
                line2 = (line2 + ' ' + words[i]).trim();
            }
        }
        if (line2) {
            ctx.fillText(line1, W * 0.5, 104, W - 64);
            ctx.fillText(line2, W * 0.5, 140, W - 64);
        } else {
            ctx.fillText(name, W * 0.5, 120, W - 64);
        }
    } else {
        ctx.fillText(name, W * 0.5, 120, W - 64);
    }

    ctx.strokeStyle = 'rgba(197, 160, 89, 0.45)';
    ctx.lineWidth = 2;
    ctx.beginPath();
    ctx.moveTo(136, 170);
    ctx.lineTo(W - 136, 170);
    ctx.stroke();

    const rawPrice = String(product.price_formatted || '').trim();
    const displayPrice = (product.is_personalizable && rawPrice && !/^ab\s/i.test(rawPrice))
        ? `ab ${rawPrice}`
        : rawPrice;

    ctx.fillStyle = '#111827';
    ctx.font = '800 30px system-ui, -apple-system, sans-serif';
    ctx.fillText(displayPrice, W * 0.5, 206, W - 72);

    const tex = new THREE.CanvasTexture(c);
    tex.generateMipmaps = true;
    tex.minFilter = THREE.LinearMipmapLinearFilter;
    tex.magFilter = THREE.LinearFilter;
    tex.anisotropy = Math.min(maxAniso || 4, 8);
    applySrgbColorSpace(THREE, tex);
    tex.needsUpdate = true;
    if (disposables) disposables.push(tex);
    return tex;
}

/**
 * Brust-Wappen fuer das artikulierte Seelenfunke Besucher- / Funki-Rig.
 */
function buildFunkiChestBadgeTexture(THREE, level, disposables) {
    const c = document.createElement('canvas');
    c.width = 256;
    c.height = 128;
    const ctx = c.getContext('2d');
    ctx.clearRect(0, 0, 256, 128);

    ctx.strokeStyle = '#C5A059';
    ctx.lineWidth = 4;
    ctx.strokeRect(18, 18, 220, 92);

    ctx.fillStyle = '#F3D587';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.font = '800 30px Georgia, serif';
    ctx.fillText('SEELENFUNKE', 128, 52, 200);

    ctx.fillStyle = '#C5A059';
    ctx.font = '700 20px system-ui, -apple-system, sans-serif';
    ctx.fillText(`FUNKI LV. ${level}`, 128, 86, 190);

    const tex = new THREE.CanvasTexture(c);
    tex.minFilter = THREE.LinearFilter;
    tex.magFilter = THREE.LinearFilter;
    applySrgbColorSpace(THREE, tex);
    tex.needsUpdate = true;
    if (disposables) disposables.push(tex);
    return tex;
}

/**
 * Freundliches Funki-Visor-Gesicht (offen & geschlossen fuer sanftes Blinzeln ohne Pro-Frame-Canvas-Redraw).
 */
function buildFunkiVisorTexture(THREE, isBlinking, disposables) {
    const c = document.createElement('canvas');
    c.width = 256;
    c.height = 160;
    const ctx = c.getContext('2d');

    const bg = ctx.createRadialGradient(128, 76, 12, 128, 80, 140);
    bg.addColorStop(0, '#181c26');
    bg.addColorStop(1, '#090b10');
    ctx.fillStyle = bg;
    ctx.fillRect(0, 0, 256, 160);

    ctx.strokeStyle = 'rgba(197, 160, 89, 0.55)';
    ctx.lineWidth = 4;
    ctx.strokeRect(8, 8, 240, 144);

    ctx.fillStyle = '#F5D98E';
    ctx.strokeStyle = '#F5D98E';
    ctx.lineCap = 'round';

    if (isBlinking) {
        ctx.lineWidth = 7;
        ctx.beginPath();
        ctx.moveTo(62, 68);
        ctx.quadraticCurveTo(84, 76, 106, 68);
        ctx.stroke();

        ctx.beginPath();
        ctx.moveTo(150, 68);
        ctx.quadraticCurveTo(172, 76, 194, 68);
        ctx.stroke();
    } else {
        ctx.beginPath();
        ctx.ellipse(84, 66, 16, 20, 0, 0, TWO_PI);
        ctx.fill();

        ctx.beginPath();
        ctx.ellipse(172, 66, 16, 20, 0, 0, TWO_PI);
        ctx.fill();

        ctx.fillStyle = '#FFFFFF';
        ctx.beginPath();
        ctx.arc(78, 58, 5.5, 0, TWO_PI);
        ctx.arc(166, 58, 5.5, 0, TWO_PI);
        ctx.fill();
    }

    // Freundliches Lächeln
    ctx.strokeStyle = '#E6C687';
    ctx.lineWidth = 6;
    ctx.beginPath();
    ctx.arc(128, 96, 26, 0.18 * Math.PI, 0.82 * Math.PI);
    ctx.stroke();

    const tex = new THREE.CanvasTexture(c);
    tex.minFilter = THREE.LinearFilter;
    tex.magFilter = THREE.LinearFilter;
    applySrgbColorSpace(THREE, tex);
    tex.needsUpdate = true;
    if (disposables) disposables.push(tex);
    return tex;
}

/**
 * Kompaktes 3D-Namensschild ueber Mitspielern (100 % Zero-PII: nur gepruefter Gamification-Name + Klasse C/B/A/S).
 */
function buildPeerNameplateTexture(THREE, displayName, level, classLabel, accentHex) {
    const W = 384;
    const H = 96;
    const c = document.createElement('canvas');
    c.width = W;
    c.height = H;
    const ctx = c.getContext('2d');

    ctx.clearRect(0, 0, W, H);
    ctx.fillStyle = 'rgba(11, 14, 20, 0.88)';
    ctx.beginPath();
    if (typeof ctx.roundRect === 'function') {
        ctx.roundRect(8, 8, W - 16, H - 16, 20);
    } else {
        ctx.rect(8, 8, W - 16, H - 16);
    }
    ctx.fill();

    ctx.strokeStyle = accentHex || '#C5A059';
    ctx.lineWidth = 3;
    ctx.stroke();

    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillStyle = '#FAF8F5';
    ctx.font = '700 24px system-ui, -apple-system, "Segoe UI", sans-serif';
    ctx.fillText(String(displayName || 'Funki').slice(0, 22), W * 0.5, 36, W - 40);

    ctx.fillStyle = '#E6C687';
    ctx.font = '700 16px system-ui, -apple-system, "Segoe UI", sans-serif';
    const metaLabel = `LV. ${ clamp(parseInt(level, 10) || 1, 1, 10) } · ${ String(classLabel || 'KLASSE C') }`;
    ctx.fillText(metaLabel, W * 0.5, 66, W - 44);

    const tex = new THREE.CanvasTexture(c);
    tex.minFilter = THREE.LinearFilter;
    tex.magFilter = THREE.LinearFilter;
    applySrgbColorSpace(THREE, tex);
    tex.needsUpdate = true;
    return tex;
}

/**
 * Helle Seelenfunke Light-Mode 3D-Sprechblase (max. 100 Zeichen, 7 Sekunden sichtbar).
 */
function buildSpeechBubbleTexture(THREE, rawText) {
    const W = 512;
    const H = 160;
    const c = document.createElement('canvas');
    c.width = W;
    c.height = H;
    const ctx = c.getContext('2d');

    ctx.clearRect(0, 0, W, H);
    ctx.fillStyle = 'rgba(250, 248, 245, 0.97)';
    ctx.strokeStyle = '#C5A059';
    ctx.lineWidth = 4;

    ctx.beginPath();
    if (typeof ctx.roundRect === 'function') {
        ctx.roundRect(12, 10, W - 24, H - 36, 24);
    } else {
        ctx.rect(12, 10, W - 24, H - 36);
    }
    ctx.fill();
    ctx.stroke();

    // Kleiner Pfeil nach unten zum Funki
    ctx.beginPath();
    ctx.moveTo(W * 0.5 - 14, H - 26);
    ctx.lineTo(W * 0.5, H - 8);
    ctx.lineTo(W * 0.5 + 14, H - 26);
    ctx.closePath();
    ctx.fill();
    ctx.stroke();

    const clean = String(rawText || '').trim().slice(0, 100);
    const words = clean.split(/\s+/);
    let line1 = '';
    let line2 = '';
    for (let i = 0; i < words.length; i++) {
        const w = words[i];
        if ((line1 + ' ' + w).trim().length <= 34 || line1 === '') {
            line1 = (line1 + ' ' + w).trim();
        } else {
            line2 = (line2 + ' ' + w).trim();
        }
    }
    if (line2.length > 38) {
        line2 = line2.slice(0, 36) + '...';
    }

    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillStyle = '#111827';
    ctx.font = '700 22px system-ui, -apple-system, "Segoe UI", sans-serif';

    if (line2) {
        ctx.fillText(line1, W * 0.5, 50, W - 56);
        ctx.fillText(line2, W * 0.5, 86, W - 56);
    } else {
        ctx.fillText(line1, W * 0.5, 68, W - 56);
    }

    const tex = new THREE.CanvasTexture(c);
    tex.minFilter = THREE.LinearFilter;
    tex.magFilter = THREE.LinearFilter;
    applySrgbColorSpace(THREE, tex);
    tex.needsUpdate = true;
    return tex;
}

export class SeelenfunkeLouvreShowroom3D {
    constructor(options = {}) {
        this.canvas = options.canvas;
        this.floatingCardEl = options.floatingCardEl || null;
        this.products = Array.isArray(options.products) ? options.products : [];
        this.playerFunki = options.playerFunki || { is_guest: true, level: 2 };
        this.onSelectProduct = typeof options.onSelectProduct === 'function' ? options.onSelectProduct : null;
        this.onActiveExhibitChange = typeof options.onActiveExhibitChange === 'function' ? options.onActiveExhibitChange : null;
        this.onFocusProductChange = typeof options.onFocusProductChange === 'function' ? options.onFocusProductChange : null;

        this.THREE = null;
        this.renderer = null;
        this.scene = null;
        this.camera = null;
        this.envTexture = null;

        this.disposables = [];
        this.glbCache = new Map();
        this.abortController = typeof AbortController !== 'undefined' ? new AbortController() : null;

        this.running = false;
        this.destroyed = false;
        this.rafId = 0;
        this.queueTimerId = 0;
        this.lastTimeMs = 0;
        this.elapsedTime = 0;
        this.maxAniso = 4;
        this._viewW = 1;
        this._viewH = 1;

        this.hallHalfW = 10.6;
        this.hallNorthZ = -18;
        this.hallSouthZ = 14;

        this.steles = [];
        this.exhibitLoadQueue = [];
        this.isLoadingExhibit = false;
        this.interactiveHitMeshes = [];
        this.floorMesh = null;
        this.walkTargetRing = null;
        this.walkTargetTimer = 0;
        this.focusHaloMesh = null;

        this.activeIndex = 0;
        this.focusedSteleIndex = -1;
        this.pinnedSteleIndex = -1;
        this._cardScreenX = -9999;
        this._cardScreenY = -9999;
        this._cardVisible = false;

        this.player = {
            root: null,
            joints: null,
            visorMat: null,
            visorTexOpen: null,
            visorTexBlink: null,
            isBlinking: false,
            nextBlinkAt: 2.8,
            blinkEndAt: 0,
            x: 0,
            y: 0,
            z: 8.5,
            vy: 0,
            isJumping: false,
            landSquash: 0,
            yaw: Math.PI,
            walkPhase: 0,
            moveBlend: 0,
            turnBank: 0,
            headYaw: 0,
            headPitch: 0,
            isMoving: false,
            autoTarget: null,
            autoLookAt: null,
            weaponActionSeq: 0,
            weaponSwingTimer: 0,
            weaponCooldown: 0,
            bubbleSprite: null,
            bubbleTex: null,
            bubbleMat: null,
            bubbleExpiresAt: 0,
        };

        // Multi-User 12er-Raum, Kauf-Ruheschutz & FPS-Waechter (0 Dynamic Lights, Shared Geometry Cache)
        this._avatarShared = null;
        this.peers = new Map();
        this.weaponBursts = [];
        this.soloMode = Boolean(options.soloMode);
        this.focusProtected = false;
        this.mutedPlayerIds = new Set();
        this.fpsEstimate = 60.0;

        this.camControl = {
            yaw: 0,
            pitch: 0.26,
            distance: 4.35,
            desiredYaw: 0,
            desiredPitch: 0.26,
            desiredDistance: 4.35,
            lookAtCurrent: null,
            isDragging: false,
            dragMoved: 0,
            lastX: 0,
            lastY: 0,
            pinchDist: 0,
        };

        this.keys = {};
        this.joystick = { x: 0, y: 0 };

        this.raycaster = null;
        this.pointerNdc = null;
        this._projVec = null;
        this._collisionOut = { x: 0, z: 0 };

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
        this._projVec = new THREE.Vector3();
        this.camControl.lookAtCurrent = new THREE.Vector3(0, 1.42, 4.0);

        const isMobile = window.innerWidth < 768;
        const count = Math.max(1, this.products.length);
        const rows = Math.ceil(count / 2);
        const baySpacing = 6.2;
        const gallerySpan = Math.max(16, rows * baySpacing + 6);

        this.hallSouthZ = 12.0;
        this.hallNorthZ = this.hallSouthZ - gallerySpan - 8.0;
        this.player.x = 0;
        this.player.z = this.hallSouthZ - 3.6;
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

        // 0 Echtzeit-Shadow-Maps fuer kompromisslose 60 FPS
        this.renderer.shadowMap.enabled = false;
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
        this.scene.fog = new THREE.FogExp2(0x0a0d14, 0.0082);

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

        // Gestaffeltes Nachladen der Exponate nach Distanz zum Spieler starten
        this._scheduleNextExhibitLoad(16);

        this.running = true;
        this.lastTimeMs = performance.now();
        this.rafId = requestAnimationFrame(this._boundTick);
    }

    _trackDisposable(item) {
        if (item) this.disposables.push(item);
        return item;
    }

    /**
     * Zero Dynamic Light Overload: Exakt 1 HemisphereLight + 1 DirectionalLight ohne Shadow-Maps.
     */
    _buildLighting() {
        const THREE = this.THREE;

        const hemi = new THREE.HemisphereLight(0xf8f4ec, 0x161a24, 1.08);
        hemi.position.set(0, 16, 0);
        this.scene.add(hemi);

        const dirMain = new THREE.DirectionalLight(0xfffaf0, 1.55);
        dirMain.position.set(6, 18, 9);
        dirMain.castShadow = false;
        this.scene.add(dirMain);
    }

    _buildLouvreArchitecture(isMobile) {
        const THREE = this.THREE;
        const totalLength = (this.hallSouthZ - this.hallNorthZ) + 6.0;
        const centerZ = (this.hallSouthZ + this.hallNorthZ) * 0.5;
        const hallW = 24.0;
        const wallH = 7.4;
        const pyramidApexY = 14.8;

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
            pyramidGlass: this._trackDisposable(new THREE.MeshBasicMaterial({
                color: 0xc8d8ec,
                transparent: true,
                opacity: 0.14,
                depthWrite: false,
                side: THREE.DoubleSide,
            })),
            vitrineGlass: this._trackDisposable(new THREE.MeshStandardMaterial({
                color: 0xf4f8fc,
                roughness: 0.06,
                metalness: 0.12,
                envMapIntensity: 1.2,
                transparent: true,
                opacity: 0.13,
                depthWrite: false,
            })),
            spotlightCone: this._trackDisposable(new THREE.MeshBasicMaterial({
                color: 0xfff3d6,
                transparent: true,
                opacity: 0.036,
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

        // 1. Atmosphaerische Abendhimmel-Kuppel ueber der Glaspyramide
        const skyGeo = this._trackDisposable(new THREE.SphereGeometry(140, 20, 14));
        const skyCanvas = document.createElement('canvas');
        skyCanvas.width = 32;
        skyCanvas.height = 128;
        const skyCtx = skyCanvas.getContext('2d');
        const skyGrad = skyCtx.createLinearGradient(0, 0, 0, 128);
        skyGrad.addColorStop(0.0, '#0c1220');
        skyGrad.addColorStop(0.38, '#182236');
        skyGrad.addColorStop(0.54, '#2b2927');
        skyGrad.addColorStop(1.0, '#07090d');
        skyCtx.fillStyle = skyGrad;
        skyCtx.fillRect(0, 0, 32, 128);
        const skyTex = this._trackDisposable(new THREE.CanvasTexture(skyCanvas));
        applySrgbColorSpace(THREE, skyTex);
        const skyMat = this._trackDisposable(new THREE.MeshBasicMaterial({ map: skyTex, side: THREE.BackSide, fog: false }));
        const skyDome = new THREE.Mesh(skyGeo, skyMat);
        skyDome.position.set(0, 0, centerZ);
        this.scene.add(skyDome);

        // 2. Polierter Nero-Marquina-Marmorboden
        const repX = hallW / 6.0;
        const repZ = totalLength / 6.0;
        const marbleMaps = buildNeroMarquinaFloorMaps(THREE, this.maxAniso, isMobile, repX, repZ, this.disposables);
        const floorMat = this._trackDisposable(new THREE.MeshStandardMaterial({
            map: marbleMaps.map,
            roughnessMap: marbleMaps.roughnessMap,
            roughness: 0.19,
            metalness: 0.08,
            envMapIntensity: 1.1,
        }));

        const floorGeo = this._trackDisposable(new THREE.PlaneGeometry(hallW, totalLength));
        this.floorMesh = new THREE.Mesh(floorGeo, floorMat);
        this.floorMesh.rotation.x = -Math.PI * 0.5;
        this.floorMesh.position.set(0, 0, centerZ);
        this.floorMesh.userData = { isFloor: true };
        this.scene.add(this.floorMesh);

        // Goldene Boden-Intarsien als gebuendeltes InstancedMesh (1 Draw-Call)
        const inlayLength = totalLength - 6.0;
        const inlayStripGeo = this._trackDisposable(new THREE.PlaneGeometry(0.045, inlayLength));
        const inlayPositions = [-2.25, -2.12, 2.12, 2.25];
        const inlayInst = new THREE.InstancedMesh(inlayStripGeo, this.mats.goldBrushed, inlayPositions.length);
        const dummy = new THREE.Object3D();
        for (let idx = 0; idx < inlayPositions.length; idx++) {
            dummy.position.set(inlayPositions[idx], 0.0025, centerZ);
            dummy.rotation.set(-Math.PI * 0.5, 0, 0);
            dummy.scale.set(1, 1, 1);
            dummy.updateMatrix();
            inlayInst.setMatrixAt(idx, dummy.matrix);
        }
        this.scene.add(inlayInst);

        const medOuter = new THREE.Mesh(
            this._trackDisposable(new THREE.RingGeometry(1.18, 1.28, 4)),
            this.mats.goldChampagne
        );
        medOuter.rotation.x = -Math.PI * 0.5;
        medOuter.position.set(0, 0.003, centerZ);
        this.scene.add(medOuter);

        const medInner = new THREE.Mesh(
            this._trackDisposable(new THREE.RingGeometry(0.62, 0.69, 4)),
            this.mats.goldBrushed
        );
        medInner.rotation.x = -Math.PI * 0.5;
        medInner.position.set(0, 0.003, centerZ);
        this.scene.add(medInner);

        // Tap-to-Walk Zielring auf dem Boden
        const targetRingGeo = this._trackDisposable(new THREE.RingGeometry(0.26, 0.34, 28));
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

        // 3. Seitenwaende & Gesimsleisten (geteilte Geometrien + InstancedMesh)
        const sideWallGeo = this._trackDisposable(new THREE.BoxGeometry(0.6, wallH, totalLength));
        const trimGeo = this._trackDisposable(new THREE.BoxGeometry(0.72, 0.08, totalLength));
        const corniceBlockGeo = this._trackDisposable(new THREE.BoxGeometry(0.95, 0.42, totalLength));

        [-hallW * 0.5, hallW * 0.5].forEach((wx) => {
            const wall = new THREE.Mesh(sideWallGeo, this.mats.cofferRecess);
            wall.position.set(wx, wallH * 0.5, centerZ);
            this.scene.add(wall);

            const baseTrim = new THREE.Mesh(trimGeo, this.mats.goldBrushed);
            baseTrim.position.set(wx, 0.12, centerZ);
            this.scene.add(baseTrim);

            const cornice = new THREE.Mesh(corniceBlockGeo, this.mats.obsidianWall);
            cornice.position.set(wx, wallH, centerZ);
            this.scene.add(cornice);

            const topGoldTrim = new THREE.Mesh(trimGeo, this.mats.goldBrushed);
            topGoldTrim.position.set(wx, wallH - 0.22, centerZ);
            this.scene.add(topGoldTrim);
        });

        // Kolonnaden-Pfeiler & Gold-Kapitelle via InstancedMesh
        const pierSpacing = 6.2;
        const pierCountSide = Math.max(3, Math.floor(totalLength / pierSpacing));
        const pierGeo = this._trackDisposable(new THREE.BoxGeometry(0.75, wallH - 0.3, 0.75));
        const capGeo = this._trackDisposable(new THREE.BoxGeometry(0.92, 0.12, 0.92));

        const totalPiers = pierCountSide * 2;
        const pierInst = new THREE.InstancedMesh(pierGeo, this.mats.obsidianWall, totalPiers);
        const capInst = new THREE.InstancedMesh(capGeo, this.mats.goldBrushed, totalPiers * 2);

        let pIdx = 0;
        let cIdx = 0;
        for (let i = 0; i < pierCountSide; i++) {
            const pz = this.hallNorthZ + 2.5 + i * ((totalLength - 5.0) / Math.max(1, pierCountSide - 1));
            [-hallW * 0.5 + 0.42, hallW * 0.5 - 0.42].forEach((px) => {
                dummy.position.set(px, (wallH - 0.3) * 0.5, pz);
                dummy.rotation.set(0, 0, 0);
                dummy.scale.set(1, 1, 1);
                dummy.updateMatrix();
                pierInst.setMatrixAt(pIdx++, dummy.matrix);

                dummy.position.set(px, wallH - 0.32, pz);
                dummy.updateMatrix();
                capInst.setMatrixAt(cIdx++, dummy.matrix);

                dummy.position.set(px, 0.18, pz);
                dummy.updateMatrix();
                capInst.setMatrixAt(cIdx++, dummy.matrix);
            });
        }
        this.scene.add(pierInst);
        this.scene.add(capInst);

        // 4. Nord- & Sued-Stirnwaende + goldener Stirnfries
        const endWallGeo = this._trackDisposable(new THREE.BoxGeometry(hallW + 0.6, wallH + 0.4, 0.7));
        const northWall = new THREE.Mesh(endWallGeo, this.mats.obsidianWall);
        northWall.position.set(0, (wallH + 0.4) * 0.5, this.hallNorthZ);
        this.scene.add(northWall);

        const southWall = new THREE.Mesh(endWallGeo, this.mats.obsidianWall);
        southWall.position.set(0, (wallH + 0.4) * 0.5, this.hallSouthZ);
        this.scene.add(southWall);

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

        const friezeTex = buildLouvreFriezeTexture(
            THREE,
            this.maxAniso,
            'MEIN SEELENFUNKE',
            'MANUFAKTUR GIFHORN · LOUVRE GALERIE',
            this.disposables
        );
        const friezeMat = this._trackDisposable(new THREE.MeshBasicMaterial({ map: friezeTex }));
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

        const southFriezeTex = buildLouvreFriezeTexture(
            THREE,
            this.maxAniso,
            'UNSERE KOLLEKTION',
            'BEGEHBARER 3D SHOWROOM · SCHWARZ GOLD',
            this.disposables
        );
        const southFriezeMat = this._trackDisposable(new THREE.MeshBasicMaterial({ map: southFriezeTex }));
        const southFriezePlane = new THREE.Mesh(
            this._trackDisposable(new THREE.PlaneGeometry(9.6, 1.72)),
            southFriezeMat
        );
        southFriezePlane.rotation.y = Math.PI;
        southFriezePlane.position.set(0, 4.95, this.hallSouthZ - 0.42);
        this.scene.add(southFriezePlane);

        // 5. Louvre-Glaspyramiden-Dach (beide Dachhaelften gebuendelt)
        const halfSpanX = hallW * 0.5;
        const riseY = pyramidApexY - wallH;
        const slopeLen = Math.hypot(halfSpanX, riseY);
        const slopeAngle = Math.atan2(riseY, halfSpanX);
        const sharedSlopeGlassGeo = this._trackDisposable(new THREE.PlaneGeometry(slopeLen, totalLength));
        const unitBeamGeo = this._trackDisposable(new THREE.BoxGeometry(1, 0.09, 1));
        const nodeGeo = this._trackDisposable(new THREE.OctahedronGeometry(0.085, 0));

        const uDivs = 6;
        const vDivs = Math.max(8, Math.round(totalLength / 2.8));
        const mapUVToSlope = (u, v) => ({
            x: (u - 0.5) * slopeLen,
            z: (v - 0.5) * totalLength,
        });

        const beamSegments = [];
        const goldNodes = [];
        for (let iu = 0; iu <= uDivs; iu++) {
            const u = iu / uDivs;
            const pA = mapUVToSlope(u, 0);
            const pB = mapUVToSlope(u, 1);
            beamSegments.push([pA.x, 0, pA.z, pB.x, 0, pB.z, 0.055]);
        }
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

        [-1, 1].forEach((sideSign) => {
            const slopeGroup = new THREE.Group();
            slopeGroup.position.set(sideSign * halfSpanX * 0.5, wallH + riseY * 0.5, centerZ);
            slopeGroup.rotation.z = -sideSign * slopeAngle;

            const glassMesh = new THREE.Mesh(sharedSlopeGlassGeo, this.mats.pyramidGlass);
            glassMesh.rotation.x = -Math.PI * 0.5;
            slopeGroup.add(glassMesh);

            const beamInst = new THREE.InstancedMesh(unitBeamGeo, this.mats.archSteel, beamSegments.length);
            for (let b = 0; b < beamSegments.length; b++) {
                const [x1, y1, z1, x2, y2, z2, thick] = beamSegments[b];
                const dx = x2 - x1;
                const dz = z2 - z1;
                const len = Math.hypot(dx, dz);
                dummy.position.set((x1 + x2) * 0.5, (y1 + y2) * 0.5, (z1 + z2) * 0.5);
                dummy.rotation.set(0, -Math.atan2(dz, dx), 0);
                dummy.scale.set(len, 1.0, thick);
                dummy.updateMatrix();
                beamInst.setMatrixAt(b, dummy.matrix);
            }
            slopeGroup.add(beamInst);

            const nodeInst = new THREE.InstancedMesh(nodeGeo, this.mats.goldChampagne, goldNodes.length);
            for (let n = 0; n < goldNodes.length; n++) {
                dummy.position.set(goldNodes[n][0], goldNodes[n][1], goldNodes[n][2]);
                dummy.rotation.set(0, Math.PI * 0.25, 0);
                dummy.scale.set(1.0, 0.65, 1.0);
                dummy.updateMatrix();
                nodeInst.setMatrixAt(n, dummy.matrix);
            }
            slopeGroup.add(nodeInst);
            this.scene.add(slopeGroup);
        });

        const ridgeBeam = new THREE.Mesh(
            this._trackDisposable(new THREE.BoxGeometry(0.24, 0.24, totalLength)),
            this.mats.goldBrushed
        );
        ridgeBeam.position.set(0, pyramidApexY, centerZ);
        this.scene.add(ridgeBeam);
    }

    /**
     * Baut alle Produkt-Podeste ueber gebuendelte InstancedMeshes (minimale Draw-Calls)
     * plus physisches Light-Mode Podest-Schild (Stufe 1) an jeder Vorderkante.
     */
    _buildExhibitSteles(baySpacing) {
        const THREE = this.THREE;
        const count = this.products.length;
        if (count === 0) return;

        const shadowGeo = this._trackDisposable(new THREE.PlaneGeometry(2.15, 2.15));
        const plinthBaseGeo = this._trackDisposable(new THREE.BoxGeometry(1.04, 0.08, 1.04));
        const monolithGeo = this._trackDisposable(new THREE.BoxGeometry(0.92, 1.04, 0.92));
        const goldCollarGeo = this._trackDisposable(new THREE.BoxGeometry(0.96, 0.045, 0.96));
        const velvetTopGeo = this._trackDisposable(new THREE.BoxGeometry(0.84, 0.04, 0.84));
        const vitrineBoxGeo = this._trackDisposable(new THREE.BoxGeometry(0.82, 0.86, 0.82));
        const vitrineTopCapGeo = this._trackDisposable(new THREE.BoxGeometry(0.84, 0.022, 0.84));
        const cornerPostGeo = this._trackDisposable(new THREE.BoxGeometry(0.016, 0.86, 0.016));
        const lightConeGeo = this._trackDisposable(new THREE.ConeGeometry(0.72, 3.4, 20, 1, true));
        const plaqueBackingGeo = this._trackDisposable(new THREE.BoxGeometry(0.68, 0.34, 0.032));
        const plaqueFaceGeo = this._trackDisposable(new THREE.PlaneGeometry(0.64, 0.315));
        const clickProxyGeo = this._trackDisposable(new THREE.BoxGeometry(1.18, 2.18, 1.18));
        const placeholderCrystalGeo = this._trackDisposable(new THREE.OctahedronGeometry(0.14, 0));

        // Geteilte Rahmen-Geometrien fuer Foto-Exponate
        this._sharedFrameGeos = {
            outer: this._trackDisposable(new THREE.BoxGeometry(0.46, 0.46, 0.026)),
            passepartout: this._trackDisposable(new THREE.BoxGeometry(0.42, 0.42, 0.03)),
            innerBevel: this._trackDisposable(new THREE.BoxGeometry(0.355, 0.355, 0.033)),
            photoPlane: this._trackDisposable(new THREE.PlaneGeometry(0.34, 0.34)),
            standFoot: this._trackDisposable(new THREE.BoxGeometry(0.18, 0.02, 0.14)),
        };

        const invisibleMat = this._trackDisposable(new THREE.MeshBasicMaterial({
            transparent: true,
            opacity: 0.0,
            depthWrite: false,
        }));

        // Alle statischen Podest-Bauteile als InstancedMesh gebuendelt (10 Draw-Calls statt >240!)
        const aoInst = new THREE.InstancedMesh(shadowGeo, this.mats.contactShadow, count);
        const plinthBaseInst = new THREE.InstancedMesh(plinthBaseGeo, this.mats.goldBrushed, count);
        const monolithInst = new THREE.InstancedMesh(monolithGeo, this.mats.obsidianWall, count);
        const goldCollarInst = new THREE.InstancedMesh(goldCollarGeo, this.mats.goldChampagne, count);
        const velvetPadInst = new THREE.InstancedMesh(velvetTopGeo, this.mats.velvetPad, count);
        const vitrineHoodInst = new THREE.InstancedMesh(vitrineBoxGeo, this.mats.vitrineGlass, count);
        vitrineHoodInst.renderOrder = 4;
        const vitrineCapInst = new THREE.InstancedMesh(vitrineTopCapGeo, this.mats.goldBrushed, count);
        const cornerPostInst = new THREE.InstancedMesh(cornerPostGeo, this.mats.goldBrushed, count * 4);
        const lightConeInst = new THREE.InstancedMesh(lightConeGeo, this.mats.spotlightCone, count);
        lightConeInst.renderOrder = 3;
        const plaqueBackingInst = new THREE.InstancedMesh(plaqueBackingGeo, this.mats.goldBrushed, count);

        const parentDummy = new THREE.Object3D();
        const childDummy = new THREE.Object3D();
        const combinedMat = new THREE.Matrix4();
        const postOff = 0.405;
        const postOffsets = [
            [-postOff, -postOff],
            [postOff, -postOff],
            [-postOff, postOff],
            [postOff, postOff],
        ];

        const setLocalInstance = (instMesh, index, lx, ly, lz, rx = 0, ry = 0, rz = 0) => {
            childDummy.position.set(lx, ly, lz);
            childDummy.rotation.set(rx, ry, rz);
            childDummy.scale.set(1, 1, 1);
            childDummy.updateMatrix();
            combinedMat.multiplyMatrices(parentDummy.matrix, childDummy.matrix);
            instMesh.setMatrixAt(index, combinedMat);
        };

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
                facingYaw = 0;
            } else {
                const isLeft = (i % 2 === 0);
                sx = isLeft ? -4.35 : 4.35;
                sz = (this.hallSouthZ - 6.4) - row * baySpacing;
                facingYaw = isLeft ? (Math.PI * 0.34) : (-Math.PI * 0.34);
            }

            parentDummy.position.set(sx, 0, sz);
            parentDummy.rotation.set(0, facingYaw, 0);
            parentDummy.scale.set(1, 1, 1);
            parentDummy.updateMatrix();

            setLocalInstance(aoInst, i, 0, 0.004, 0, -Math.PI * 0.5, 0, 0);
            setLocalInstance(plinthBaseInst, i, 0, 0.04, 0);
            setLocalInstance(monolithInst, i, 0, 0.60, 0);
            setLocalInstance(goldCollarInst, i, 0, 1.135, 0);
            setLocalInstance(velvetPadInst, i, 0, 1.17, 0);
            setLocalInstance(vitrineHoodInst, i, 0, 1.62, 0);
            setLocalInstance(vitrineCapInst, i, 0, 2.06, 0);
            setLocalInstance(lightConeInst, i, 0, 3.55, 0);
            setLocalInstance(plaqueBackingInst, i, 0, 0.84, 0.48, -0.26, 0, 0);

            for (let p = 0; p < 4; p++) {
                setLocalInstance(cornerPostInst, i * 4 + p, postOffsets[p][0], 1.62, postOffsets[p][1]);
            }

            const steleRoot = new THREE.Group();
            steleRoot.position.set(sx, 0, sz);
            steleRoot.rotation.y = facingYaw;
            this.scene.add(steleRoot);

            // Stufe 1: Physisches Light-Mode Museums-Schild direkt an der Vorderkante des Podests
            const plaqueTex = buildStelePlaqueTexture(THREE, this.maxAniso, product, this.disposables);
            const plaqueMat = this._trackDisposable(new THREE.MeshBasicMaterial({ map: plaqueTex }));
            const pFace = new THREE.Mesh(plaqueFaceGeo, plaqueMat);
            pFace.position.set(0, 0.84 + Math.sin(0.26) * 0.019, 0.48 + Math.cos(0.26) * 0.019);
            pFace.rotation.x = -0.26;
            steleRoot.add(pFace);

            // Rotierender Exponat-Anker im Inneren der Vitrine + dezenter Platzhalter bis zum Nachladen
            const exhibitTurntable = new THREE.Group();
            exhibitTurntable.position.set(0, 1.56, 0);
            steleRoot.add(exhibitTurntable);

            const placeholderMesh = new THREE.Mesh(placeholderCrystalGeo, this.mats.goldChampagne);
            exhibitTurntable.add(placeholderMesh);

            // Unsichtbare Klick-Hitbox
            const hitBox = new THREE.Mesh(clickProxyGeo, invisibleMat);
            hitBox.position.y = 1.08;
            hitBox.userData = { steleIndex: i };
            steleRoot.add(hitBox);
            this.interactiveHitMeshes.push(hitBox);

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
                placeholderMesh,
                loaded: false,
            };
            this.steles.push(steleRecord);
            this.exhibitLoadQueue.push(steleRecord);
        }

        this.scene.add(
            aoInst,
            plinthBaseInst,
            monolithInst,
            goldCollarInst,
            velvetPadInst,
            vitrineHoodInst,
            vitrineCapInst,
            cornerPostInst,
            lightConeInst,
            plaqueBackingInst
        );

        // Dezenter goldener Fokus-Indikatorring auf dem aktuell fokussierten Podest (nur 1 Mesh fuer die gesamte Szene!)
        this.focusHaloMesh = new THREE.Mesh(
            this._trackDisposable(new THREE.RingGeometry(0.49, 0.54, 32)),
            this.mats.goldChampagne
        );
        this.focusHaloMesh.rotation.x = -Math.PI * 0.5;
        this.focusHaloMesh.visible = false;
        this.scene.add(this.focusHaloMesh);
    }

    /**
     * Gestaffelte Nachlade-Queue (1 Exponat pro Tick, dynamisch sortiert nach Distanz zum Spieler).
     */
    _scheduleNextExhibitLoad(delayMs = 55) {
        if (this.destroyed || this.isLoadingExhibit || this.exhibitLoadQueue.length === 0) {
            return;
        }
        if (this.queueTimerId) {
            clearTimeout(this.queueTimerId);
        }
        this.queueTimerId = setTimeout(() => {
            this.queueTimerId = 0;
            this._processNextExhibitInQueue();
        }, delayMs);
    }

    _processNextExhibitInQueue() {
        if (this.destroyed || this.isLoadingExhibit || this.exhibitLoadQueue.length === 0) {
            return;
        }

        // Naechstgelegenes noch ungeladenes Exponat zuerst laden
        let bestIdx = 0;
        let bestDistSq = Infinity;
        for (let i = 0; i < this.exhibitLoadQueue.length; i++) {
            const st = this.exhibitLoadQueue[i];
            const dx = this.player.x - st.x;
            const dz = this.player.z - st.z;
            const dSq = dx * dx + dz * dz;
            if (dSq < bestDistSq) {
                bestDistSq = dSq;
                bestIdx = i;
            }
        }

        const nextStele = this.exhibitLoadQueue.splice(bestIdx, 1)[0];
        if (!nextStele || nextStele.loaded) {
            this._scheduleNextExhibitLoad(20);
            return;
        }

        this.isLoadingExhibit = true;
        const done = () => {
            nextStele.loaded = true;
            this.isLoadingExhibit = false;
            if (!this.destroyed && this.exhibitLoadQueue.length > 0) {
                this._scheduleNextExhibitLoad(60);
            }
        };

        if (nextStele.product.glb_url) {
            this._populateExhibitGlb(nextStele).finally(done);
        } else {
            this._populateFramedPhotoExhibit(nextStele).finally(done);
        }
    }

    _loadGlbCached(url) {
        if (this.glbCache.has(url)) {
            return this.glbCache.get(url);
        }
        const fetchOpts = { method: 'GET', credentials: 'same-origin' };
        if (this.abortController) {
            fetchOpts.signal = this.abortController.signal;
        }
        const promise = fetch(url, fetchOpts)
            .then((res) => {
                if (!res.ok) throw new Error(`HTTP ${res.status}`);
                return res.arrayBuffer();
            })
            .then((buf) => parseGlbBinary(this.THREE, buf, this.maxAniso, this.disposables));

        this.glbCache.set(url, promise);
        return promise;
    }

    _clearPlaceholder(steleRecord) {
        if (steleRecord.placeholderMesh && steleRecord.turntable) {
            steleRecord.turntable.remove(steleRecord.placeholderMesh);
            steleRecord.placeholderMesh = null;
        }
    }

    _populateExhibitGlb(steleRecord) {
        const THREE = this.THREE;
        const { product, turntable } = steleRecord;

        return this._loadGlbCached(product.glb_url)
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
                this._clearPlaceholder(steleRecord);
                turntable.add(modelClone);
            })
            .catch(() => {
                if (!this.destroyed) {
                    return this._populateFramedPhotoExhibit(steleRecord);
                }
            });
    }

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
                child.material = this._trackDisposable(new THREE.MeshStandardMaterial({
                    map: origMap,
                    color: 0xf4fbff,
                    metalness: 0.14,
                    roughness: 0.04,
                    envMapIntensity: 1.5,
                    transparent: true,
                    opacity: 0.86,
                    depthWrite: false,
                    side: THREE.DoubleSide,
                }));
            } else if (hint === 'glass') {
                child.renderOrder = 2;
                child.material = this._trackDisposable(new THREE.MeshStandardMaterial({
                    map: origMap,
                    color: 0xf7fbff,
                    metalness: 0.1,
                    roughness: 0.06,
                    envMapIntensity: 1.3,
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

    _populateFramedPhotoExhibit(steleRecord) {
        const THREE = this.THREE;
        const { product, turntable } = steleRecord;
        const geos = this._sharedFrameGeos;

        const frameGroup = new THREE.Group();
        frameGroup.rotation.x = -0.14;

        frameGroup.add(new THREE.Mesh(geos.outer, this.mats.goldBrushed));
        frameGroup.add(new THREE.Mesh(geos.passepartout, this.mats.cofferRecess));
        frameGroup.add(new THREE.Mesh(geos.innerBevel, this.mats.goldChampagne));

        const photoMat = this._trackDisposable(new THREE.MeshBasicMaterial({ color: 0xfaf8f5 }));
        const photoPlane = new THREE.Mesh(geos.photoPlane, photoMat);
        photoPlane.position.z = 0.018;
        frameGroup.add(photoPlane);

        const photoPlaneBack = new THREE.Mesh(geos.photoPlane, photoMat);
        photoPlaneBack.rotation.y = Math.PI;
        photoPlaneBack.position.z = -0.018;
        frameGroup.add(photoPlaneBack);

        const standFoot = new THREE.Mesh(geos.standFoot, this.mats.goldBrushed);
        standFoot.position.set(0, -0.23, -0.04);
        frameGroup.add(standFoot);

        this._clearPlaceholder(steleRecord);
        turntable.add(frameGroup);

        if (!product.preview_image_url) {
            return Promise.resolve();
        }

        return new Promise((resolve) => {
            const img = new Image();
            img.crossOrigin = 'anonymous';
            img.onload = () => {
                if (!this.destroyed) {
                    const scaled = downscaleImageToCanvas(img, 512);
                    const tex = this._trackDisposable(new THREE.Texture(scaled));
                    tex.generateMipmaps = true;
                    tex.minFilter = THREE.LinearMipmapLinearFilter;
                    tex.magFilter = THREE.LinearFilter;
                    tex.anisotropy = Math.min(this.maxAniso || 4, 4);
                    applySrgbColorSpace(THREE, tex);
                    tex.needsUpdate = true;
                    photoMat.map = tex;
                    photoMat.color.setHex(0xffffff);
                    photoMat.needsUpdate = true;
                }
                resolve();
            };
            img.onerror = () => resolve();
            img.src = product.preview_image_url;
        });
    }

    /**
     * Erzeugt einmalig den geteilten Geometrie- & MatCap-Pool fuer bis zu 12 gleichzeitige Raum-Avatare + Gamification-Waffen
     * (Shared Geometry Cache: < 8 % GPU-Mehrbedarf im vollen 12er-Raum, 0 doppelte Geometrie-Uploads).
     */
    _ensureAvatarSharedPool() {
        if (this._avatarShared) {
            return this._avatarShared;
        }
        const THREE = this.THREE;

        const mats = {
            suitObsidian: this._trackDisposable(new THREE.MeshMatcapMaterial({
                matcap: buildStudioMatcapTexture(THREE, '#121620', '#5E6882', '#D8B257', 28, 0.50, this.disposables),
            })),
            suitDark: this._trackDisposable(new THREE.MeshMatcapMaterial({
                matcap: buildStudioMatcapTexture(THREE, '#0B0D14', '#40485C', '#C5A059', 24, 0.36, this.disposables),
            })),
            gold24k: this._trackDisposable(new THREE.MeshMatcapMaterial({
                matcap: buildStudioMatcapTexture(THREE, '#C5A059', '#FFFFFF', '#FFF3CC', 34, 0.62, this.disposables),
            })),
            goldBright: this._trackDisposable(new THREE.MeshMatcapMaterial({
                matcap: buildStudioMatcapTexture(THREE, '#E8CA82', '#FFFFFF', '#FFFBE8', 38, 0.66, this.disposables),
            })),
            jointDark: this._trackDisposable(new THREE.MeshMatcapMaterial({
                matcap: buildStudioMatcapTexture(THREE, '#0E1118', '#4A5064', '#9B7E45', 16, 0.25, this.disposables),
            })),
            sapphireCrystal: this._trackDisposable(new THREE.MeshMatcapMaterial({
                matcap: buildStudioMatcapTexture(THREE, '#1D8AC8', '#FFFFFF', '#A5F3FC', 36, 0.64, this.disposables),
            })),
            emeraldCrystal: this._trackDisposable(new THREE.MeshMatcapMaterial({
                matcap: buildStudioMatcapTexture(THREE, '#0D9468', '#FFFFFF', '#A7F3D0', 36, 0.64, this.disposables),
            })),
            cosmosGold: this._trackDisposable(new THREE.MeshMatcapMaterial({
                matcap: buildStudioMatcapTexture(THREE, '#F59E0B', '#FFFFFF', '#FEF3C7', 42, 0.72, this.disposables),
            })),
        };

        const torsoProfile = [
            new THREE.Vector2(0.00, 0.00),
            new THREE.Vector2(0.155, 0.00),
            new THREE.Vector2(0.175, 0.14),
            new THREE.Vector2(0.205, 0.30),
            new THREE.Vector2(0.192, 0.40),
            new THREE.Vector2(0.135, 0.43),
            new THREE.Vector2(0.00, 0.43),
        ];

        const geos = {
            footShadow: this._trackDisposable(new THREE.PlaneGeometry(1.12, 1.12)),
            baseRing: this._trackDisposable(new THREE.RingGeometry(0.34, 0.385, 32)),
            pelvis: this._trackDisposable(new THREE.CylinderGeometry(0.155, 0.145, 0.14, 20)),
            beltRing: this._trackDisposable(new THREE.TorusGeometry(0.162, 0.018, 10, 26)),
            buckleGem: this._trackDisposable(new THREE.OctahedronGeometry(0.022, 0)),
            hipBall: this._trackDisposable(new THREE.SphereGeometry(0.064, 14, 10)),
            thigh: this._trackDisposable(new THREE.CylinderGeometry(0.068, 0.056, 0.34, 18)),
            thighStripe: this._trackDisposable(new THREE.BoxGeometry(0.012, 0.30, 0.018)),
            kneeBall: this._trackDisposable(new THREE.SphereGeometry(0.055, 14, 10)),
            kneeRing: this._trackDisposable(new THREE.TorusGeometry(0.054, 0.007, 8, 20)),
            shin: this._trackDisposable(new THREE.CylinderGeometry(0.056, 0.048, 0.34, 18)),
            ankleBall: this._trackDisposable(new THREE.SphereGeometry(0.046, 12, 10)),
            bootCuff: this._trackDisposable(new THREE.TorusGeometry(0.052, 0.010, 8, 20)),
            bootUpper: this._trackDisposable(new THREE.BoxGeometry(0.105, 0.052, 0.175)),
            bootToe: this._trackDisposable(new THREE.SphereGeometry(0.052, 14, 10)),
            bootSole: this._trackDisposable(new THREE.BoxGeometry(0.114, 0.016, 0.215)),
            chest: this._trackDisposable(new THREE.LatheGeometry(torsoProfile, 24)),
            lapel: this._trackDisposable(new THREE.BoxGeometry(0.032, 0.18, 0.016)),
            collarRing: this._trackDisposable(new THREE.TorusGeometry(0.125, 0.018, 10, 24)),
            badgePlane: this._trackDisposable(new THREE.PlaneGeometry(0.19, 0.095)),
            neckCyl: this._trackDisposable(new THREE.CylinderGeometry(0.058, 0.068, 0.075, 16)),
            headShell: this._trackDisposable(new THREE.BoxGeometry(0.33, 0.24, 0.25)),
            headFrame: this._trackDisposable(new THREE.BoxGeometry(0.346, 0.256, 0.028)),
            visorScreen: this._trackDisposable(new THREE.PlaneGeometry(0.305, 0.215)),
            earCap: this._trackDisposable(new THREE.CylinderGeometry(0.038, 0.038, 0.026, 14)),
            crestSmall: this._trackDisposable(new THREE.OctahedronGeometry(0.034, 0)),
            crestMaster: this._trackDisposable(new THREE.OctahedronGeometry(0.048, 0)),
            crownHalo: this._trackDisposable(new THREE.TorusGeometry(0.11, 0.010, 8, 24)),
            shoulderCap: this._trackDisposable(new THREE.SphereGeometry(0.068, 14, 10)),
            shoulderTrim: this._trackDisposable(new THREE.TorusGeometry(0.066, 0.009, 8, 20)),
            upperArm: this._trackDisposable(new THREE.CylinderGeometry(0.052, 0.045, 0.24, 16)),
            elbowBall: this._trackDisposable(new THREE.SphereGeometry(0.046, 12, 10)),
            forearm: this._trackDisposable(new THREE.CylinderGeometry(0.045, 0.038, 0.22, 16)),
            cuffRing: this._trackDisposable(new THREE.TorusGeometry(0.041, 0.008, 8, 18)),
            palm: this._trackDisposable(new THREE.BoxGeometry(0.036, 0.064, 0.062)),
            dorsalPlate: this._trackDisposable(new THREE.BoxGeometry(0.040, 0.046, 0.046)),
            thumb: this._trackDisposable(new THREE.CylinderGeometry(0.010, 0.012, 0.042, 8)),
            finger: this._trackDisposable(new THREE.CylinderGeometry(0.0085, 0.010, 0.048, 8)),
            // Geteilte Gamification-Waffen- & Effekt-Geometrien
            weaponHilt: this._trackDisposable(new THREE.CylinderGeometry(0.013, 0.015, 0.16, 10)),
            weaponGuard: this._trackDisposable(new THREE.BoxGeometry(0.13, 0.022, 0.032)),
            weaponBlade: this._trackDisposable(new THREE.ConeGeometry(0.028, 0.56, 4)),
            weaponStaffShaft: this._trackDisposable(new THREE.CylinderGeometry(0.012, 0.014, 0.72, 10)),
            weaponStaffOrb: this._trackDisposable(new THREE.OctahedronGeometry(0.055, 0)),
            weaponBowArc: this._trackDisposable(new THREE.TorusGeometry(0.24, 0.013, 8, 20, Math.PI * 0.92)),
            weaponChord: this._trackDisposable(new THREE.CylinderGeometry(0.003, 0.003, 0.46, 6)),
            slashArc: this._trackDisposable(new THREE.RingGeometry(0.58, 0.92, 24, 1, -Math.PI * 0.42, Math.PI * 0.84)),
            sparkDiamond: this._trackDisposable(new THREE.OctahedronGeometry(0.036, 0)),
        };

        const badgeMatByLevel = new Map();
        const getBadgeMat = (lvl) => {
            const safeLvl = clamp(parseInt(lvl, 10) || 2, 1, 10);
            if (!badgeMatByLevel.has(safeLvl)) {
                const tex = buildFunkiChestBadgeTexture(THREE, safeLvl, this.disposables);
                const mat = this._trackDisposable(new THREE.MeshBasicMaterial({
                    map: tex,
                    transparent: true,
                    depthWrite: false,
                }));
                badgeMatByLevel.set(safeLvl, mat);
            }
            return badgeMatByLevel.get(safeLvl);
        };

        const visorTexOpen = buildFunkiVisorTexture(THREE, false, this.disposables);
        const visorTexBlink = buildFunkiVisorTexture(THREE, true, this.disposables);
        const peerVisorMat = this._trackDisposable(new THREE.MeshBasicMaterial({ map: visorTexOpen }));

        this._avatarShared = {
            mats,
            geos,
            getBadgeMat,
            visorTexOpen,
            visorTexBlink,
            peerVisorMat,
        };
        return this._avatarShared;
    }

    _buildWeaponAttachment(weaponInfo) {
        if (!weaponInfo || typeof weaponInfo !== 'object') return null;
        const THREE = this.THREE;
        const shared = this._ensureAvatarSharedPool();
        const { mats, geos } = shared;
        const effectType = String(weaponInfo.effect_type || 'gold_blade');

        const wGroup = new THREE.Group();
        // Liegt natuerlich in der rechten Handflaeche und zeigt schraeg nach vorn
        wGroup.position.set(0.004, -0.048, 0.026);
        wGroup.rotation.x = Math.PI * 0.42;

        if (effectType === 'sapphire_staff') {
            const shaft = new THREE.Mesh(geos.weaponStaffShaft, mats.gold24k);
            shaft.position.y = 0.18;
            const orb = new THREE.Mesh(geos.weaponStaffOrb, mats.sapphireCrystal);
            orb.position.y = 0.56;
            const halo = new THREE.Mesh(geos.crownHalo, mats.goldBright);
            halo.scale.setScalar(0.68);
            halo.position.y = 0.56;
            wGroup.add(shaft, orb, halo);
        } else if (effectType === 'emerald_bow') {
            const bow = new THREE.Mesh(geos.weaponBowArc, mats.gold24k);
            bow.rotation.z = -Math.PI * 0.46;
            bow.rotation.y = Math.PI * 0.5;
            const chord = new THREE.Mesh(geos.weaponChord, mats.emeraldCrystal);
            chord.position.set(0, 0, -0.03);
            const gem = new THREE.Mesh(geos.buckleGem, mats.emeraldCrystal);
            gem.scale.setScalar(1.4);
            gem.position.set(0, 0, 0.21);
            wGroup.add(bow, chord, gem);
        } else {
            const bladeMat = effectType === 'cosmos_blade' ? mats.cosmosGold : mats.goldBright;
            const hilt = new THREE.Mesh(geos.weaponHilt, mats.suitDark);
            const guard = new THREE.Mesh(geos.weaponGuard, mats.gold24k);
            guard.position.y = 0.08;
            const blade = new THREE.Mesh(geos.weaponBlade, bladeMat);
            blade.scale.set(1.0, 1.0, 0.34);
            blade.position.y = 0.37;
            wGroup.add(hilt, guard, blade);

            if (effectType === 'cosmos_blade') {
                const crest = new THREE.Mesh(geos.crestSmall, mats.cosmosGold);
                crest.position.y = 0.12;
                wGroup.add(crest);
            }
        }

        return wGroup;
    }

    /**
     * Erzeugt eine voll artikulierte Avatar-Instanz aus dem Shared-Pool (fuer lokalen Spieler & bis zu 11 Mitspieler).
     */
    _createArticulatedAvatarInstance(level, weaponInfo, useOwnBlinkingVisor = false) {
        const THREE = this.THREE;
        const shared = this._ensureAvatarSharedPool();
        const { mats, geos } = shared;
        const lvl = clamp(parseInt(level, 10) || 2, 1, 10);

        const root = new THREE.Group();

        const footShadow = new THREE.Mesh(geos.footShadow, this.mats.contactShadow);
        footShadow.rotation.x = -Math.PI * 0.5;
        footShadow.position.y = 0.005;
        root.add(footShadow);

        const baseRing = new THREE.Mesh(geos.baseRing, this.mats.goldBrushed);
        baseRing.rotation.x = -Math.PI * 0.5;
        baseRing.position.y = 0.007;
        root.add(baseRing);

        // Hueft-Zentrum auf y = 0.76m (ergibt 0.76 - 0.02 - 0.34 - 0.34 - 0.06 = exakt 0.000m Sohlenkontakt!)
        const pelvisPivot = new THREE.Group();
        pelvisPivot.position.set(0, 0.76, 0);
        root.add(pelvisPivot);

        const pelvisMesh = new THREE.Mesh(geos.pelvis, mats.suitObsidian);
        pelvisMesh.scale.set(1.08, 1.0, 0.82);
        pelvisPivot.add(pelvisMesh);

        const beltRing = new THREE.Mesh(geos.beltRing, mats.gold24k);
        beltRing.rotation.x = Math.PI * 0.5;
        beltRing.scale.set(1.06, 0.82, 1.0);
        beltRing.position.y = 0.055;
        pelvisPivot.add(beltRing);

        const buckleGem = new THREE.Mesh(geos.buckleGem, mats.goldBright);
        buckleGem.scale.set(1.0, 1.15, 0.45);
        buckleGem.position.set(0, 0.055, 0.145);
        pelvisPivot.add(buckleGem);

        const buildArticulatedLeg = (sideSign) => {
            const hipPivot = new THREE.Group();
            hipPivot.position.set(sideSign * 0.112, -0.02, 0);
            pelvisPivot.add(hipPivot);

            const hipBall = new THREE.Mesh(geos.hipBall, mats.jointDark);
            const thigh = new THREE.Mesh(geos.thigh, mats.suitObsidian);
            thigh.position.y = -0.17;
            const stripe = new THREE.Mesh(geos.thighStripe, mats.gold24k);
            stripe.position.set(sideSign * 0.058, -0.17, 0);
            hipPivot.add(hipBall, thigh, stripe);

            const kneePivot = new THREE.Group();
            kneePivot.position.set(0, -0.34, 0);
            hipPivot.add(kneePivot);

            const kneeBall = new THREE.Mesh(geos.kneeBall, mats.suitDark);
            const kneeRing = new THREE.Mesh(geos.kneeRing, mats.gold24k);
            kneeRing.rotation.x = Math.PI * 0.5;
            const shin = new THREE.Mesh(geos.shin, mats.suitObsidian);
            shin.position.y = -0.17;
            kneePivot.add(kneeBall, kneeRing, shin);

            const anklePivot = new THREE.Group();
            anklePivot.position.set(0, -0.34, 0);
            kneePivot.add(anklePivot);

            const ankleBall = new THREE.Mesh(geos.ankleBall, mats.jointDark);
            const bootCuff = new THREE.Mesh(geos.bootCuff, mats.gold24k);
            bootCuff.rotation.x = Math.PI * 0.5;
            bootCuff.position.y = -0.012;

            const bootUpper = new THREE.Mesh(geos.bootUpper, mats.suitDark);
            bootUpper.position.set(0, -0.032, 0.025);

            const bootToe = new THREE.Mesh(geos.bootToe, mats.suitDark);
            bootToe.scale.set(0.98, 0.56, 1.15);
            bootToe.position.set(0, -0.036, 0.092);

            const bootSole = new THREE.Mesh(geos.bootSole, mats.gold24k);
            bootSole.position.set(0, -0.052, 0.036);

            anklePivot.add(ankleBall, bootCuff, bootUpper, bootToe, bootSole);
            return { hipPivot, kneePivot, anklePivot };
        };

        const legL = buildArticulatedLeg(1);
        const legR = buildArticulatedLeg(-1);

        const torsoPivot = new THREE.Group();
        torsoPivot.position.set(0, 0.05, 0);
        pelvisPivot.add(torsoPivot);

        const chestMesh = new THREE.Mesh(geos.chest, mats.suitObsidian);
        chestMesh.scale.set(1.08, 1.0, 0.80);
        torsoPivot.add(chestMesh);

        [-1, 1].forEach((sideSign) => {
            const lapel = new THREE.Mesh(geos.lapel, mats.gold24k);
            lapel.position.set(sideSign * 0.058, 0.31, 0.152);
            lapel.rotation.z = -sideSign * 0.32;
            lapel.rotation.x = -0.14;
            torsoPivot.add(lapel);
        });

        const collarRing = new THREE.Mesh(geos.collarRing, mats.gold24k);
        collarRing.rotation.x = Math.PI * 0.5;
        collarRing.position.y = 0.425;
        torsoPivot.add(collarRing);

        const badgePlane = new THREE.Mesh(geos.badgePlane, shared.getBadgeMat(lvl));
        badgePlane.position.set(0, 0.21, 0.165);
        torsoPivot.add(badgePlane);

        const neckPivot = new THREE.Group();
        neckPivot.position.set(0, 0.435, 0);
        torsoPivot.add(neckPivot);

        const neckCyl = new THREE.Mesh(geos.neckCyl, mats.jointDark);
        neckCyl.position.y = 0.032;
        neckPivot.add(neckCyl);

        const headPivot = new THREE.Group();
        headPivot.position.set(0, 0.068, 0);
        neckPivot.add(headPivot);

        const headShell = new THREE.Mesh(geos.headShell, mats.suitObsidian);
        headShell.position.y = 0.125;
        headPivot.add(headShell);

        const headFrame = new THREE.Mesh(geos.headFrame, mats.gold24k);
        headFrame.position.set(0, 0.125, 0.118);
        headPivot.add(headFrame);

        const visorMat = useOwnBlinkingVisor
            ? this._trackDisposable(new THREE.MeshBasicMaterial({ map: shared.visorTexOpen }))
            : shared.peerVisorMat;

        const visorScreen = new THREE.Mesh(geos.visorScreen, visorMat);
        visorScreen.position.set(0, 0.125, 0.134);
        headPivot.add(visorScreen);

        [-1, 1].forEach((sideSign) => {
            const earCap = new THREE.Mesh(geos.earCap, mats.gold24k);
            earCap.rotation.z = Math.PI * 0.5;
            earCap.position.set(sideSign * 0.175, 0.125, 0);
            headPivot.add(earCap);
        });

        const sparkCrest = new THREE.Mesh(
            lvl >= 8 ? geos.crestMaster : geos.crestSmall,
            mats.goldBright
        );
        sparkCrest.position.set(0, 0.285, 0.02);
        headPivot.add(sparkCrest);

        if (lvl >= 6) {
            const crownHalo = new THREE.Mesh(geos.crownHalo, mats.goldBright);
            crownHalo.rotation.x = Math.PI * 0.5;
            crownHalo.position.set(0, 0.27, 0);
            headPivot.add(crownHalo);
        }

        const buildArticulatedArm = (sideSign) => {
            const shoulderPivot = new THREE.Group();
            shoulderPivot.position.set(sideSign * 0.228, 0.375, 0);
            torsoPivot.add(shoulderPivot);

            const shoulderCap = new THREE.Mesh(geos.shoulderCap, lvl >= 4 ? mats.gold24k : mats.suitObsidian);
            const shoulderTrim = new THREE.Mesh(geos.shoulderTrim, mats.gold24k);
            shoulderTrim.rotation.y = Math.PI * 0.5;
            shoulderTrim.rotation.x = 0.35;

            const upperArm = new THREE.Mesh(geos.upperArm, mats.suitObsidian);
            upperArm.position.y = -0.12;
            shoulderPivot.add(shoulderCap, shoulderTrim, upperArm);

            const elbowPivot = new THREE.Group();
            elbowPivot.position.set(0, -0.24, 0);
            shoulderPivot.add(elbowPivot);

            const elbowBall = new THREE.Mesh(geos.elbowBall, mats.jointDark);
            const forearm = new THREE.Mesh(geos.forearm, mats.suitObsidian);
            forearm.position.y = -0.11;
            const cuffRing = new THREE.Mesh(geos.cuffRing, mats.gold24k);
            cuffRing.rotation.x = Math.PI * 0.5;
            cuffRing.position.y = -0.21;
            elbowPivot.add(elbowBall, forearm, cuffRing);

            const wristPivot = new THREE.Group();
            wristPivot.position.set(0, -0.22, 0);
            elbowPivot.add(wristPivot);

            const palm = new THREE.Mesh(geos.palm, mats.suitDark);
            palm.position.set(0, -0.034, 0.004);
            const dorsalPlate = new THREE.Mesh(geos.dorsalPlate, mats.gold24k);
            dorsalPlate.position.set(sideSign * 0.004, -0.032, 0.004);

            const thumb = new THREE.Mesh(geos.thumb, mats.goldBright);
            thumb.position.set(-sideSign * 0.012, -0.036, 0.036);
            thumb.rotation.x = -0.55;
            thumb.rotation.z = sideSign * 0.25;

            const fingerCurl = new THREE.Group();
            fingerCurl.position.set(0, -0.064, 0.004);
            const zOffsets = [0.020, 0.007, -0.006, -0.019];
            const lengths = [0.96, 1.04, 0.98, 0.84];
            for (let fi = 0; fi < 4; fi++) {
                const fMesh = new THREE.Mesh(geos.finger, mats.gold24k);
                fMesh.scale.y = lengths[fi];
                fMesh.position.set(-sideSign * 0.004, -0.022 * lengths[fi], zOffsets[fi]);
                fMesh.rotation.z = -sideSign * 0.16;
                fingerCurl.add(fMesh);
            }

            wristPivot.add(palm, dorsalPlate, thumb, fingerCurl);
            return { shoulderPivot, elbowPivot, wristPivot, fingerCurl };
        };

        const armL = buildArticulatedArm(1);
        const armR = buildArticulatedArm(-1);

        const weaponMesh = this._buildWeaponAttachment(weaponInfo);
        if (weaponMesh) {
            armR.wristPivot.add(weaponMesh);
        }

        return {
            root,
            visorMat,
            visorTexOpen: shared.visorTexOpen,
            visorTexBlink: shared.visorTexBlink,
            weaponMesh,
            joints: {
                footShadow,
                baseRing,
                pelvisPivot,
                torsoPivot,
                neckPivot,
                headPivot,
                sparkCrest,
                hipPivotL: legL.hipPivot,
                kneePivotL: legL.kneePivot,
                anklePivotL: legL.anklePivot,
                hipPivotR: legR.hipPivot,
                kneePivotR: legR.kneePivot,
                anklePivotR: legR.anklePivot,
                shoulderPivotL: armL.shoulderPivot,
                elbowPivotL: armL.elbowPivot,
                wristPivotL: armL.wristPivot,
                fingerCurlL: armL.fingerCurl,
                shoulderPivotR: armR.shoulderPivot,
                elbowPivotR: armR.elbowPivot,
                wristPivotR: armR.wristPivot,
                fingerCurlR: armR.fingerCurl,
            },
        };
    }

    /**
     * Voll artikuliertes hierarchisches 3D-Gelenk-Rig im edlen Seelenfunke Funki-/Besucher-Stil:
     * - Bein-Kinematik links & rechts: hipPivotL/R -> Oberschenkel -> kneePivotL/R -> Unterschenkel -> anklePivotL/R -> Fuss/Schuh
     * - Arm- & Hand-Kinematik links & rechts: shoulderPivotL/R -> Oberarm -> elbowPivotL/R -> Unterarm -> wristPivotL/R + modellierte Hand/Daumen/Finger
     * - Rumpf-, Hueft-, Hals- & Kopf-Rig inkl. Blinzel-Visor, Gamification-Waffe und Level-Insignien (Lv. 1 bis 10)
     */
    _buildPlayerCharacter() {
        const lvl = clamp(parseInt(this.playerFunki.level, 10) || 2, 1, 10);
        const weaponInfo = (!this.playerFunki.is_guest && this.playerFunki.weapon) ? this.playerFunki.weapon : null;

        const built = this._createArticulatedAvatarInstance(lvl, weaponInfo, true);
        built.root.position.set(this.player.x, this.player.y, this.player.z);
        built.root.rotation.y = this.player.yaw;
        this.scene.add(built.root);

        this.player.root = built.root;
        this.player.joints = built.joints;
        this.player.visorMat = built.visorMat;
        this.player.visorTexOpen = built.visorTexOpen;
        this.player.visorTexBlink = built.visorTexBlink;
    }

    /**
     * Biomechanisch natuerliche Geh-, Sprung-, Waffen- & Ruhe-Kinematik fuer Arme, Haende, Knie, Beine, Fuesse, Huefte & Kopf.
     * Schrittphase exakt gekoppelt an stepDist (0 % Boden-Schleifen / Foot-Sliding).
     */
    _updatePlayerKinematics(dt, stepDist, turnRate, nearestForGaze) {
        const j = this.player.joints;
        if (!this.player.root || !j) return;

        const jumpH = Math.max(0, this.player.y || 0);
        const airBlend = clamp(jumpH / 0.26, 0, 1);
        const squash = this.player.landSquash || 0;

        this.player.root.position.set(this.player.x, jumpH, this.player.z);
        this.player.root.rotation.y = this.player.yaw;

        // 1. Distanzgekoppelte Schrittphase (4.48 rad/m entspricht exakt ~0.70 m Einzelschrittlaenge)
        const strideFrequency = 4.48;
        if (stepDist > 0.0001 && airBlend < 0.65) {
            this.player.walkPhase = (this.player.walkPhase + stepDist * strideFrequency) % TWO_PI;
        }

        // Butterweicher Uebergang Gehen <-> Stehen ohne hartes Schnappen
        const instantSpeed = stepDist / Math.max(0.001, dt);
        const targetBlend = this.player.isMoving ? clamp(instantSpeed / 4.8, 0.25, 1.0) : 0.0;
        const blendFactor = 1.0 - Math.exp(-dt * 10.0);
        this.player.moveBlend = lerp(this.player.moveBlend, targetBlend, blendFactor);

        const mb = this.player.moveBlend * (1.0 - airBlend * 0.65);
        const idleWeight = (1.0 - mb) * (1.0 - airBlend);
        const phaseL = this.player.walkPhase;
        const phaseR = phaseL + Math.PI;

        // 2. Bein-, Knie- & Fuss-Abrollkinematik + Sprung-/Landebeugung
        const applyLegGait = (hipPivot, kneePivot, anklePivot, p, sideSign) => {
            const sinP = Math.sin(p);
            const swingForward = Math.max(0, Math.cos(p - 0.16));
            const stanceWeight = Math.max(0, -Math.cos(p));

            const hipPitch = -0.54 * sinP * mb - (sideSign > 0 ? 0.34 : 0.18) * airBlend - squash * 0.42;
            const kneeBend = Math.max(
                0,
                (Math.pow(swingForward, 1.32) * 0.72 + stanceWeight * 0.06) * mb
                    + (sideSign > 0 ? 0.62 : 0.44) * airBlend
                    + squash * 0.85
            );

            const heelStrike = Math.max(0, sinP - 0.45) * 0.22;
            const toePushOff = Math.max(0, -sinP - 0.35) * 0.28;
            const swingToeLift = -swingForward * 0.26;
            const anklePitch = (swingToeLift + heelStrike + toePushOff - stanceWeight * 0.04) * mb
                + 0.22 * airBlend
                - squash * 0.35;

            hipPivot.rotation.x = hipPitch;
            hipPivot.rotation.z = sideSign * (0.015 * idleWeight + 0.022 * mb + 0.03 * airBlend);
            kneePivot.rotation.x = kneeBend;
            anklePivot.rotation.x = anklePitch;
        };

        applyLegGait(j.hipPivotL, j.kneePivotL, j.anklePivotL, phaseL, 1);
        applyLegGait(j.hipPivotR, j.kneePivotR, j.anklePivotR, phaseR, -1);

        // 3. Kreuzgang-Arm-, Ellbogen-, Handgelenk- & Finger-Kinematik + Prestige-Waffenschwung rechts
        const applyArmGait = (shoulderPivot, elbowPivot, wristPivot, fingerCurl, oppositeLegPhase, sideSign) => {
            const sinArm = Math.sin(oppositeLegPhase);
            const cosArm = Math.cos(oppositeLegPhase);
            const forwardAmt = Math.max(0, sinArm);
            const idleBreathArm = Math.sin(this.elapsedTime * 1.9 + sideSign * 0.4) * 0.018 * idleWeight;

            shoulderPivot.rotation.x = -0.40 * sinArm * mb + idleBreathArm - 0.38 * airBlend;
            shoulderPivot.rotation.z = sideSign * (0.075 + 0.032 * mb + Math.max(0, -sinArm) * 0.025 * mb + 0.16 * airBlend);
            shoulderPivot.rotation.y = -sideSign * 0.06 * sinArm * mb;

            elbowPivot.rotation.x = -0.16 - (0.11 + 0.34 * forwardAmt) * mb - 0.28 * airBlend;

            wristPivot.rotation.x = (-0.08 * cosArm - 0.05 * forwardAmt) * mb;
            wristPivot.rotation.z = sideSign * (0.04 + 0.05 * sinArm * mb);
            fingerCurl.rotation.z = -sideSign * (0.12 + 0.10 * forwardAmt * mb);
        };

        applyArmGait(j.shoulderPivotL, j.elbowPivotL, j.wristPivotL, j.fingerCurlL, phaseR, 1);
        applyArmGait(j.shoulderPivotR, j.elbowPivotR, j.wristPivotR, j.fingerCurlR, phaseL, -1);

        if (this.player.weaponSwingTimer > 0) {
            const pSwing = 1.0 - (this.player.weaponSwingTimer / 0.52);
            const arc = Math.sin(clamp(pSwing, 0, 1) * Math.PI);
            const sweep = Math.cos(clamp(pSwing, 0, 1) * Math.PI);
            j.shoulderPivotR.rotation.x = -1.18 * arc;
            j.shoulderPivotR.rotation.y = 0.58 * sweep * arc;
            j.shoulderPivotR.rotation.z = -0.34 * arc;
            j.elbowPivotR.rotation.x = -0.42 * arc;
            j.wristPivotR.rotation.x = 0.36 * arc;
        }

        // 4. Hueft-Impuls (2 * walkPhase), Lande-Federung & Kontaktschatten fest auf dem Marmorboden
        const doubleStepBounce = Math.cos(2.0 * phaseL) * 0.024 * mb;
        const stanceSink = -0.014 * mb - squash * 0.16;
        const idleBreath = Math.sin(this.elapsedTime * 2.05) * 0.009 * idleWeight;

        j.pelvisPivot.position.y = 0.76 + doubleStepBounce + stanceSink + idleBreath;
        j.pelvisPivot.position.x = -Math.sin(phaseL) * 0.018 * mb;
        j.pelvisPivot.rotation.y = Math.sin(phaseL) * 0.065 * mb;
        j.pelvisPivot.rotation.z = -Math.sin(phaseL) * 0.032 * mb;

        const targetBank = clamp(-turnRate * 0.045, -0.085, 0.085) * mb;
        this.player.turnBank = lerp(this.player.turnBank, targetBank, blendFactor);

        j.torsoPivot.rotation.x = 0.068 * mb + Math.sin(this.elapsedTime * 2.05) * 0.012 * idleWeight + squash * 0.12;
        j.torsoPivot.rotation.y = -Math.sin(phaseL) * 0.085 * mb;
        j.torsoPivot.rotation.z = this.player.turnBank + Math.sin(phaseL) * 0.022 * mb;

        // Kontaktschatten & Kompassring bleiben beim Springen exakt auf y = 0.005 / 0.007 ueber dem Marmor
        j.footShadow.position.y = 0.005 - jumpH;
        j.baseRing.position.y = 0.007 - jumpH;
        const shadowScale = (1.0 - doubleStepBounce * 1.8) / (1.0 + jumpH * 0.65);
        j.footShadow.scale.set(shadowScale, shadowScale, 1);
        j.sparkCrest.rotation.y = this.elapsedTime * 1.4;

        // Lokale 3D-Sprechblase nach 7 Sekunden oder im Fokus-Schutz ausblenden
        if (this.player.bubbleSprite) {
            const showOwnBubble = !this.focusProtected && !this.soloMode && this.elapsedTime < this.player.bubbleExpiresAt;
            this.player.bubbleSprite.visible = showOwnBubble;
        }

        // 5. Natuerlicher Kopf-Blick zum naechstgelegenen Exponat im Stand & langsamen Schlendern
        let desiredHeadYaw = 0;
        let desiredHeadPitch = -0.02 * mb;

        if (nearestForGaze) {
            const worldToSteleYaw = Math.atan2(nearestForGaze.x - this.player.x, nearestForGaze.z - this.player.z);
            let relYaw = ((worldToSteleYaw - this.player.yaw + Math.PI) % TWO_PI) - Math.PI;
            if (relYaw < -Math.PI) relYaw += TWO_PI;

            if (Math.abs(relYaw) < 1.35) {
                const gazeStrength = clamp(1.0 - mb * 0.65, 0.35, 1.0);
                desiredHeadYaw = clamp(relYaw * 0.68, -0.56, 0.56) * gazeStrength;
                desiredHeadPitch = -0.06 * gazeStrength;
            }
        }

        const headSmooth = 1.0 - Math.exp(-dt * 6.5);
        this.player.headYaw = lerp(this.player.headYaw, desiredHeadYaw, headSmooth);
        this.player.headPitch = lerp(this.player.headPitch, desiredHeadPitch, headSmooth);
        j.headPivot.rotation.y = this.player.headYaw;
        j.headPivot.rotation.x = this.player.headPitch;
        j.headPivot.rotation.z = -this.player.headYaw * 0.12;

        // Dezentes Blinzeln auf dem Visor
        if (this.player.visorMat) {
            if (!this.player.isBlinking && this.elapsedTime >= this.player.nextBlinkAt) {
                this.player.isBlinking = true;
                this.player.blinkEndAt = this.elapsedTime + 0.13;
                this.player.visorMat.map = this.player.visorTexBlink;
                this.player.visorMat.needsUpdate = true;
            } else if (this.player.isBlinking && this.elapsedTime >= this.player.blinkEndAt) {
                this.player.isBlinking = false;
                this.player.nextBlinkAt = this.elapsedTime + 3.2 + ((this.elapsedTime * 17.3) % 2.4);
                this.player.visorMat.map = this.player.visorTexOpen;
                this.player.visorMat.needsUpdate = true;
            }
        }
    }

    /**
     * Loest einen parabolischen Sprung auf dem Marmorboden aus (Leertaste am PC oder kompakter Sprung-Button mobil).
     */
    triggerJump() {
        if (!this.running || this.destroyed) return false;
        if (this.player.isJumping || this.player.y > 0.02) return false;
        this.player.isJumping = true;
        this.player.vy = 5.45;
        return true;
    }

    /**
     * Zueckt die ausgeruestete Gamification-Waffe (KLASSE C/B/A/S) und erzeugt edle Prestige-Funken ohne Rueckstoss/Schaden.
     */
    triggerWeaponAction() {
        if (!this.running || this.destroyed) return false;
        if (!this.playerFunki || this.playerFunki.is_guest || !this.playerFunki.weapon) return false;
        if (this.player.weaponCooldown > 0) return false;

        this.player.weaponCooldown = 0.55;
        this.player.weaponSwingTimer = 0.52;
        this.player.weaponActionSeq += 1;

        const w = this.playerFunki.weapon;
        this._spawnWeaponBurst(
            this.player.x,
            (this.player.y || 0) + 1.06,
            this.player.z,
            this.player.yaw,
            w.accent_hex || '#C5A059',
            w.effect_type || 'gold_blade',
            false
        );
        return true;
    }

    _spawnWeaponBurst(x, y, z, yaw, accentHex, effectType, isRemote) {
        if (!this.scene || !this.THREE) return;
        if (isRemote && (this.focusProtected || this.soloMode || this.fpsEstimate < 40.0)) {
            return;
        }
        if (this.weaponBursts.length >= 10) {
            const oldest = this.weaponBursts.shift();
            if (oldest) this._disposeWeaponBurst(oldest);
        }

        const THREE = this.THREE;
        const shared = this._ensureAvatarSharedPool();
        const group = new THREE.Group();
        group.position.set(x + Math.sin(yaw) * 0.42, y, z + Math.cos(yaw) * 0.42);
        group.rotation.y = yaw;

        const arcMat = new THREE.MeshBasicMaterial({
            color: new THREE.Color(accentHex || '#C5A059'),
            transparent: true,
            opacity: 0.88,
            side: THREE.DoubleSide,
            depthWrite: false,
        });
        const slashMesh = new THREE.Mesh(shared.geos.slashArc, arcMat);
        slashMesh.rotation.x = Math.PI * 0.42;
        slashMesh.rotation.z = effectType === 'sapphire_staff' ? 0.0 : -0.36;
        group.add(slashMesh);

        const sparks = [];
        const sparkCount = isRemote ? 5 : 8;
        const sparkMat = new THREE.MeshBasicMaterial({
            color: new THREE.Color(accentHex || '#E6C687'),
            transparent: true,
            opacity: 0.95,
            depthWrite: false,
        });

        for (let i = 0; i < sparkCount; i++) {
            const angle = -0.75 + (i / Math.max(1, sparkCount - 1)) * 1.5;
            const sMesh = new THREE.Mesh(shared.geos.sparkDiamond, sparkMat);
            sMesh.position.set(Math.sin(angle) * 0.45, (i % 2 === 0 ? 0.06 : -0.05), Math.cos(angle) * 0.45);
            group.add(sMesh);
            sparks.push({
                mesh: sMesh,
                vx: Math.sin(angle) * (1.6 + (i % 3) * 0.35),
                vy: 0.45 + (i % 3) * 0.25,
                vz: Math.cos(angle) * (1.8 + (i % 2) * 0.4),
            });
        }

        this.scene.add(group);
        this.weaponBursts.push({
            group,
            slashMesh,
            arcMat,
            sparkMat,
            sparks,
            age: 0,
            duration: 0.46,
            isRemote: Boolean(isRemote),
        });
    }

    _disposeWeaponBurst(burst) {
        if (!burst) return;
        if (this.scene && burst.group) {
            this.scene.remove(burst.group);
        }
        if (burst.arcMat) burst.arcMat.dispose();
        if (burst.sparkMat) burst.sparkMat.dispose();
    }

    _updateWeaponBursts(dt) {
        for (let i = this.weaponBursts.length - 1; i >= 0; i--) {
            const b = this.weaponBursts[i];
            if (b.isRemote && (this.focusProtected || this.soloMode)) {
                this._disposeWeaponBurst(b);
                this.weaponBursts.splice(i, 1);
                continue;
            }

            b.age += dt;
            const t = b.age / b.duration;
            if (t >= 1.0) {
                this._disposeWeaponBurst(b);
                this.weaponBursts.splice(i, 1);
                continue;
            }

            const fade = Math.max(0, 1.0 - t * t);
            b.arcMat.opacity = 0.85 * fade;
            b.sparkMat.opacity = 0.95 * fade;
            const arcScale = 0.72 + t * 0.68;
            b.slashMesh.scale.set(arcScale, arcScale, 1);
            b.slashMesh.rotation.z += dt * 2.8;

            for (let s = 0; s < b.sparks.length; s++) {
                const sp = b.sparks[s];
                sp.mesh.position.x += sp.vx * dt;
                sp.mesh.position.y += sp.vy * dt;
                sp.mesh.position.z += sp.vz * dt;
                sp.vy -= 3.8 * dt;
                sp.mesh.rotation.y += dt * 6.0;
                sp.mesh.scale.setScalar(Math.max(0.1, 1.0 - t * 0.85));
            }
        }
    }

    getLocalSyncState() {
        return {
            x: Number((this.player.x || 0).toFixed(3)),
            y: Number((this.player.y || 0).toFixed(3)),
            z: Number((this.player.z || 0).toFixed(3)),
            rotY: Number((this.player.yaw || 0).toFixed(3)),
            jumping: Boolean(this.player.isJumping || (this.player.y || 0) > 0.02),
            weaponActionSeq: this.player.weaponActionSeq || 0,
        };
    }

    setSoloMode(flag) {
        this.soloMode = Boolean(flag);
        if (this.soloMode) {
            this._clearAllPeers();
        }
    }

    setFocusProtected(flag) {
        this.focusProtected = Boolean(flag);
    }

    setMutedPlayers(playerIds) {
        this.mutedPlayerIds = new Set(Array.isArray(playerIds) ? playerIds : []);
        this.peers.forEach((peer, pid) => {
            if (this.mutedPlayerIds.has(pid) && peer.bubbleSprite) {
                peer.bubbleSprite.visible = false;
            }
        });
    }

    /**
     * Zeigt fuer 7 Sekunden eine dezente Light-Mode 3D-Sprechblase ueber dem jeweiligen Funki.
     */
    showChatBubble(playerId, text) {
        if (this.destroyed || !this.THREE || !text) return;
        if (this.focusProtected || this.soloMode || this.mutedPlayerIds.has(playerId)) return;

        const THREE = this.THREE;
        const myPid = this.playerFunki && this.playerFunki.player_id ? this.playerFunki.player_id : null;

        if (myPid && playerId === myPid && this.player.root) {
            if (this.player.bubbleTex) this.player.bubbleTex.dispose();
            const tex = buildSpeechBubbleTexture(THREE, text);
            if (!this.player.bubbleSprite) {
                const mat = new THREE.SpriteMaterial({ map: tex, transparent: true, depthWrite: false });
                const sprite = new THREE.Sprite(mat);
                sprite.scale.set(1.75, 0.55, 1);
                sprite.position.set(0, 2.38, 0);
                this.player.root.add(sprite);
                this.player.bubbleSprite = sprite;
                this.player.bubbleMat = mat;
            } else {
                this.player.bubbleMat.map = tex;
                this.player.bubbleMat.needsUpdate = true;
            }
            this.player.bubbleTex = tex;
            this.player.bubbleExpiresAt = this.elapsedTime + 7.0;
            this.player.bubbleSprite.visible = true;
            return;
        }

        const peer = this.peers.get(playerId);
        if (!peer || !peer.root) return;

        if (peer.bubbleTex) peer.bubbleTex.dispose();
        const tex = buildSpeechBubbleTexture(THREE, text);
        if (!peer.bubbleSprite) {
            const mat = new THREE.SpriteMaterial({ map: tex, transparent: true, depthWrite: false });
            const sprite = new THREE.Sprite(mat);
            sprite.scale.set(1.75, 0.55, 1);
            sprite.position.set(0, 2.52, 0);
            peer.root.add(sprite);
            peer.bubbleSprite = sprite;
            peer.bubbleMat = mat;
        } else {
            peer.bubbleMat.map = tex;
            peer.bubbleMat.needsUpdate = true;
        }
        peer.bubbleTex = tex;
        peer.bubbleExpiresAt = this.elapsedTime + 7.0;
        peer.bubbleSprite.visible = true;
    }

    _disposePeerEntry(peer) {
        if (!peer) return;
        if (this.scene && peer.root) {
            this.scene.remove(peer.root);
        }
        if (peer.nameplateTex) peer.nameplateTex.dispose();
        if (peer.nameplateMat) peer.nameplateMat.dispose();
        if (peer.bubbleTex) peer.bubbleTex.dispose();
        if (peer.bubbleMat) peer.bubbleMat.dispose();
    }

    _clearAllPeers() {
        this.peers.forEach((peer) => this._disposePeerEntry(peer));
        this.peers.clear();
    }

    /**
     * Synchronisiert die bis zu 11 Mitspieler im selben 12er-Raum (5 Hz State-Ticks -> 60 FPS Lerp-Glaettung, Zero Griefing).
     */
    updatePeers(peersArray) {
        if (this.destroyed || !this.THREE || !this.scene) return;
        if (this.soloMode || !Array.isArray(peersArray)) {
            this._clearAllPeers();
            return;
        }

        const THREE = this.THREE;
        const incomingIds = new Set();

        for (let i = 0; i < peersArray.length && i < 11; i++) {
            const pData = peersArray[i];
            if (!pData || !pData.player_id) continue;

            const pid = String(pData.player_id);
            incomingIds.add(pid);

            let peer = this.peers.get(pid);
            const tx = clamp(Number(pData.x) || 0, -this.hallHalfW + 0.8, this.hallHalfW - 0.8);
            const ty = clamp(Number(pData.y) || 0, 0, 4.5);
            const tz = clamp(Number(pData.z) || 8.4, this.hallNorthZ + 1.2, this.hallSouthZ - 1.2);
            const tYaw = Number(pData.rot_y) || 0;
            const wSeq = parseInt(pData.weapon_seq, 10) || 0;

            if (!peer) {
                const weapon = pData.weapon || null;
                const built = this._createArticulatedAvatarInstance(pData.level || 1, weapon, false);
                built.root.position.set(tx, ty, tz);
                built.root.rotation.y = tYaw;

                const classLabel = (weapon && weapon.class_label) ? weapon.class_label : 'KLASSE C';
                const accentHex = (weapon && weapon.accent_hex) ? weapon.accent_hex : '#C5A059';
                const nameplateTex = buildPeerNameplateTexture(
                    THREE,
                    pData.display_name || 'Funki',
                    pData.level || 1,
                    classLabel,
                    accentHex
                );
                const nameplateMat = new THREE.SpriteMaterial({
                    map: nameplateTex,
                    transparent: true,
                    depthWrite: false,
                });
                const nameplateSprite = new THREE.Sprite(nameplateMat);
                nameplateSprite.scale.set(1.28, 0.32, 1);
                nameplateSprite.position.set(0, 2.04, 0);
                built.root.add(nameplateSprite);

                this.scene.add(built.root);

                peer = {
                    playerId: pid,
                    root: built.root,
                    joints: built.joints,
                    x: tx,
                    y: ty,
                    z: tz,
                    yaw: tYaw,
                    targetX: tx,
                    targetY: ty,
                    targetZ: tz,
                    targetYaw: tYaw,
                    walkPhase: 0,
                    moveBlend: 0,
                    weaponSeq: wSeq,
                    weaponSwingTimer: 0,
                    accentHex,
                    effectType: (weapon && weapon.effect_type) ? weapon.effect_type : 'gold_blade',
                    nameplateSprite,
                    nameplateTex,
                    nameplateMat,
                    bubbleSprite: null,
                    bubbleTex: null,
                    bubbleMat: null,
                    bubbleExpiresAt: 0,
                };
                this.peers.set(pid, peer);
            } else {
                peer.targetX = tx;
                peer.targetY = ty;
                peer.targetZ = tz;
                peer.targetYaw = tYaw;

                if (wSeq > peer.weaponSeq) {
                    peer.weaponSeq = wSeq;
                    if (!this.mutedPlayerIds.has(pid) && !this.focusProtected) {
                        peer.weaponSwingTimer = 0.52;
                        this._spawnWeaponBurst(
                            peer.x,
                            peer.y + 1.06,
                            peer.z,
                            peer.yaw,
                            peer.accentHex,
                            peer.effectType,
                            true
                        );
                    }
                }
            }
        }

        // Verlassene Spieler sauber entfernen
        this.peers.forEach((peer, pid) => {
            if (!incomingIds.has(pid)) {
                this._disposePeerEntry(peer);
                this.peers.delete(pid);
            }
        });
    }

    _updatePeersKinematics(dt) {
        if (this.peers.size === 0) return;

        const smooth = 1.0 - Math.exp(-dt * 9.5);
        const strideFrequency = 4.48;

        this.peers.forEach((peer, pid) => {
            const prevX = peer.x;
            const prevZ = peer.z;

            peer.x = lerp(peer.x, peer.targetX, smooth);
            peer.y = lerp(peer.y, peer.targetY, smooth);
            peer.z = lerp(peer.z, peer.targetZ, smooth);
            peer.yaw = lerpAngle(peer.yaw, peer.targetYaw, smooth);

            const stepDist = Math.hypot(peer.x - prevX, peer.z - prevZ);
            const jumpH = Math.max(0, peer.y);
            const airBlend = clamp(jumpH / 0.26, 0, 1);

            peer.root.position.set(peer.x, jumpH, peer.z);
            peer.root.rotation.y = peer.yaw;

            if (stepDist > 0.0002 && airBlend < 0.65) {
                peer.walkPhase = (peer.walkPhase + stepDist * strideFrequency) % TWO_PI;
            }

            const speed = stepDist / Math.max(0.001, dt);
            const targetBlend = speed > 0.12 ? clamp(speed / 4.8, 0.25, 1.0) : 0.0;
            peer.moveBlend = lerp(peer.moveBlend, targetBlend, smooth);

            const mb = peer.moveBlend * (1.0 - airBlend * 0.65);
            const j = peer.joints;
            const pL = peer.walkPhase;
            const pR = pL + Math.PI;

            j.hipPivotL.rotation.x = -0.52 * Math.sin(pL) * mb - 0.30 * airBlend;
            j.hipPivotR.rotation.x = -0.52 * Math.sin(pR) * mb - 0.18 * airBlend;
            j.kneePivotL.rotation.x = Math.max(0, Math.cos(pL - 0.16) * 0.68 * mb + 0.56 * airBlend);
            j.kneePivotR.rotation.x = Math.max(0, Math.cos(pR - 0.16) * 0.68 * mb + 0.42 * airBlend);

            j.shoulderPivotL.rotation.x = -0.38 * Math.sin(pR) * mb - 0.34 * airBlend;
            j.elbowPivotL.rotation.x = -0.16 - 0.28 * Math.max(0, Math.sin(pR)) * mb;

            if (peer.weaponSwingTimer > 0) {
                peer.weaponSwingTimer = Math.max(0, peer.weaponSwingTimer - dt);
                const pSwing = 1.0 - (peer.weaponSwingTimer / 0.52);
                const arc = Math.sin(clamp(pSwing, 0, 1) * Math.PI);
                j.shoulderPivotR.rotation.x = -1.16 * arc;
                j.shoulderPivotR.rotation.z = -0.32 * arc;
                j.elbowPivotR.rotation.x = -0.40 * arc;
            } else {
                j.shoulderPivotR.rotation.x = -0.38 * Math.sin(pL) * mb - 0.34 * airBlend;
                j.shoulderPivotR.rotation.z = -0.08;
                j.elbowPivotR.rotation.x = -0.16 - 0.28 * Math.max(0, Math.sin(pL)) * mb;
            }

            const bounce = Math.cos(2.0 * pL) * 0.022 * mb;
            j.pelvisPivot.position.y = 0.76 + bounce;
            j.footShadow.position.y = 0.005 - jumpH;
            j.baseRing.position.y = 0.007 - jumpH;
            const sScale = 1.0 / (1.0 + jumpH * 0.65);
            j.footShadow.scale.set(sScale, sScale, 1);
            j.sparkCrest.rotation.y = this.elapsedTime * 1.4;

            if (peer.nameplateSprite) {
                peer.nameplateSprite.visible = !this.focusProtected && !this.soloMode;
            }
            if (peer.bubbleSprite) {
                const bubbleVisible = !this.focusProtected
                    && !this.soloMode
                    && !this.mutedPlayerIds.has(pid)
                    && this.elapsedTime < peer.bubbleExpiresAt;
                peer.bubbleSprite.visible = bubbleVisible;
            }
        });
    }

    /**
     * Stufe 2: Aktualisiert das schwebende World-Space Raum-Schild (GPU translate3d, 0 DOM-Reflows)
     * und aktiviert automatisch den Kauf-Ruheschutz (Fokus-Schutz), sobald ein Exponat betrachtet wird.
     */
    _setFocusedSteleIndex(nextIdx) {
        if (this.focusedSteleIndex === nextIdx) return;
        this.focusedSteleIndex = nextIdx;

        if (nextIdx >= 0 && this.steles[nextIdx]) {
            const st = this.steles[nextIdx];
            this.activeIndex = nextIdx;
            this.focusProtected = true;
            if (this.onActiveExhibitChange) {
                this.onActiveExhibitChange(nextIdx, st.product);
            }
            if (this.onFocusProductChange) {
                this.onFocusProductChange(st.product, nextIdx);
            }
        } else {
            this.focusProtected = false;
            if (this.onFocusProductChange) {
                this.onFocusProductChange(null, -1);
            }
        }
    }

    _updateFloatingCardProjection() {
        const cardEl = this.floatingCardEl;
        const idx = this.focusedSteleIndex;

        if (idx < 0 || !this.steles[idx]) {
            if (this.focusHaloMesh) this.focusHaloMesh.visible = false;
            if (cardEl && this._cardVisible) {
                this._cardVisible = false;
                cardEl.style.opacity = '0';
                cardEl.style.visibility = 'hidden';
                cardEl.style.pointerEvents = 'none';
            }
            return;
        }

        const st = this.steles[idx];
        if (this.focusHaloMesh) {
            this.focusHaloMesh.visible = true;
            this.focusHaloMesh.position.set(st.x, 2.075, st.z);
            this.focusHaloMesh.rotation.z = this.elapsedTime * 0.45;
        }

        if (!cardEl || !this.camera) return;

        // 3D-Weltpunkt direkt ueber der Vitrine in den Bildschirmraum projizieren
        this._projVec.set(st.x, 2.24, st.z).project(this.camera);

        // Ausblenden, wenn das fokussierte Podest hinter der Kamera liegt
        if (this._projVec.z < -1.0 || this._projVec.z > 1.0) {
            if (this._cardVisible) {
                this._cardVisible = false;
                cardEl.style.opacity = '0';
                cardEl.style.visibility = 'hidden';
                cardEl.style.pointerEvents = 'none';
            }
            return;
        }

        const rawX = (this._projVec.x * 0.5 + 0.5) * this._viewW;
        const rawY = (-this._projVec.y * 0.5 + 0.5) * this._viewH;

        const marginX = Math.min(148, this._viewW * 0.46);
        const clampedX = clamp(rawX, marginX, Math.max(marginX, this._viewW - marginX));
        const clampedY = clamp(rawY, 224, Math.max(224, this._viewH - 92));

        if (!this._cardVisible) {
            this._cardVisible = true;
            this._cardScreenX = clampedX;
            this._cardScreenY = clampedY;
            cardEl.style.visibility = 'visible';
            cardEl.style.opacity = '1';
            cardEl.style.pointerEvents = 'none';
            cardEl.style.transform = `translate3d(${clampedX.toFixed(1)}px, ${clampedY.toFixed(1)}px, 0) translate(-50%, -100%)`;
            return;
        }

        if (Math.abs(clampedX - this._cardScreenX) > 0.2 || Math.abs(clampedY - this._cardScreenY) > 0.2) {
            this._cardScreenX = clampedX;
            this._cardScreenY = clampedY;
            cardEl.style.transform = `translate3d(${clampedX.toFixed(1)}px, ${clampedY.toFixed(1)}px, 0) translate(-50%, -100%)`;
        }
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

        if (code === 'Space' || e.key === ' ') {
            e.preventDefault();
            this.triggerJump();
            return;
        }

        if (code === 'KeyF') {
            this.triggerWeaponAction();
            return;
        }

        if (code === 'Enter' || code === 'KeyE') {
            if (this.focusedSteleIndex >= 0 && this.steles[this.focusedSteleIndex]) {
                const prod = this.steles[this.focusedSteleIndex].product;
                if (this.onSelectProduct) {
                    this.onSelectProduct(prod, this.focusedSteleIndex);
                } else if (prod && prod.product_url) {
                    window.location.href = prod.product_url;
                }
            } else {
                const nearest = this._findNearestStele(5.0) || this.steles[this.activeIndex];
                if (nearest) {
                    this.goToExhibit(nearest.index, false);
                }
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
        } else if (this.camControl.dragMoved < 7 && e.button === 2) {
            this.triggerWeaponAction();
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

        // 1. Antippen eines Podests -> Avatar laeuft hin & schwebende Raum-Tafel fokussiert sofort das Exponat
        if (this.interactiveHitMeshes.length > 0) {
            const steleHits = this.raycaster.intersectObjects(this.interactiveHitMeshes, false);
            if (steleHits.length > 0) {
                const idx = steleHits[0].object.userData.steleIndex;
                this.goToExhibit(idx, false);
                return;
            }
        }

        // 2. Tap-to-Walk auf den Marmorboden (loest angepinnten Fokus, Proximity uebernimmt fliessend)
        if (this.floorMesh) {
            const floorHits = this.raycaster.intersectObject(this.floorMesh, false);
            if (floorHits.length > 0) {
                const pt = floorHits[0].point;
                const tx = clamp(pt.x, -this.hallHalfW + 1.2, this.hallHalfW - 1.2);
                const tz = clamp(pt.z, this.hallNorthZ + 1.8, this.hallSouthZ - 1.8);
                this.pinnedSteleIndex = -1;
                this.player.autoTarget = { x: tx, z: tz };
                this.player.autoLookAt = null;

                if (this.walkTargetRing) {
                    this.walkTargetRing.position.set(tx, 0.012, tz);
                    this.walkTargetTimer = 1.35;
                }
            }
        }
    }

    goToExhibit(index, triggerSelectCallback = false) {
        if (this.steles.length === 0) return;
        const safeIdx = ((index % this.steles.length) + this.steles.length) % this.steles.length;
        const stele = this.steles[safeIdx];
        this.activeIndex = safeIdx;
        this.pinnedSteleIndex = safeIdx;

        this.player.autoTarget = { x: stele.standX, z: stele.standZ };
        this.player.autoLookAt = { x: stele.x, z: stele.z };

        const lookYaw = Math.atan2(stele.standX - stele.x, stele.standZ - stele.z);
        this.camControl.desiredYaw = lookYaw;
        this.camControl.desiredPitch = 0.24;
        this.camControl.desiredDistance = 3.85;

        if (this.walkTargetRing) {
            this.walkTargetRing.position.set(stele.standX, 0.012, stele.standZ);
            this.walkTargetTimer = 1.2;
        }

        this._setFocusedSteleIndex(safeIdx);

        if (triggerSelectCallback && this.onSelectProduct) {
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
        this._collisionOut.x = x;
        this._collisionOut.z = z;
        return this._collisionOut;
    }

    _tick(nowMs) {
        if (!this.running || this.destroyed) return;
        this.rafId = requestAnimationFrame(this._boundTick);

        const dt = clamp((nowMs - this.lastTimeMs) / 1000, 0.001, 0.05);
        this.lastTimeMs = nowMs;
        this.elapsedTime += dt;

        // Glatter FPS-Waechter (drosselt fremde Waffen-Partikel unter 40 FPS auf aelteren Smartphones automatisch)
        const instantFps = 1.0 / Math.max(0.001, dt);
        this.fpsEstimate = lerp(this.fpsEstimate, clamp(instantFps, 15, 120), 0.08);

        // Parabolische Sprungphysik & Waffen-Timer auf dem Marmorboden
        if (this.player.weaponCooldown > 0) {
            this.player.weaponCooldown = Math.max(0, this.player.weaponCooldown - dt);
        }
        if (this.player.weaponSwingTimer > 0) {
            this.player.weaponSwingTimer = Math.max(0, this.player.weaponSwingTimer - dt);
        }
        if (this.player.isJumping || this.player.y > 0) {
            this.player.vy -= 15.2 * dt;
            this.player.y += this.player.vy * dt;
            if (this.player.y <= 0) {
                this.player.y = 0;
                this.player.vy = 0;
                this.player.isJumping = false;
                this.player.landSquash = 0.16;
            }
        }
        if (this.player.landSquash > 0) {
            this.player.landSquash = Math.max(0, this.player.landSquash - dt * 0.85);
        }

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
            this.pinnedSteleIndex = -1;
        }

        const moveSpeed = 5.0;
        let vx = 0;
        let vz = 0;

        if (hasManualInput) {
            const len = Math.max(1.0, Math.hypot(inputForward, inputRight));
            const nf = inputForward / len;
            const nr = inputRight / len;
            const sinY = Math.sin(this.camControl.yaw);
            const cosY = Math.cos(this.camControl.yaw);

            vx = (-sinY * nf + cosY * nr) * moveSpeed;
            vz = (-cosY * nf - sinY * nr) * moveSpeed;
        } else if (this.player.autoTarget) {
            const dx = this.player.autoTarget.x - this.player.x;
            const dz = this.player.autoTarget.z - this.player.z;
            const dist = Math.hypot(dx, dz);
            if (dist > 0.07) {
                const stepSpeed = Math.min(moveSpeed * 1.15, Math.max(1.65, dist * 3.3));
                vx = (dx / dist) * stepSpeed;
                vz = (dz / dist) * stepSpeed;
            } else {
                this.player.autoTarget = null;
                this.pinnedSteleIndex = -1;
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

        const prevX = this.player.x;
        const prevZ = this.player.z;
        const prevYaw = this.player.yaw;

        if (this.player.isMoving) {
            const nextPos = this._resolveCollisions(
                this.player.x + vx * dt,
                this.player.z + vz * dt
            );
            this.player.x = nextPos.x;
            this.player.z = nextPos.z;

            const moveYaw = Math.atan2(vx, vz);
            this.player.yaw = lerpAngle(this.player.yaw, moveYaw, 1.0 - Math.exp(-12.0 * dt));
        }

        const stepDist = Math.hypot(this.player.x - prevX, this.player.z - prevZ);
        let yawDiff = ((this.player.yaw - prevYaw + Math.PI) % TWO_PI) - Math.PI;
        if (yawDiff < -Math.PI) yawDiff += TWO_PI;
        const turnRate = yawDiff / dt;

        // 2. Automatischer Proximity-Fokus (< 3.4m) fuer die schwebende Raum-Tafel ohne Popups
        const nearestGaze = this._findNearestStele(4.2);
        if (this.pinnedSteleIndex >= 0) {
            this._setFocusedSteleIndex(this.pinnedSteleIndex);
        } else {
            const radius = this.focusedSteleIndex >= 0 ? PROXIMITY_FOCUS_LEAVE : PROXIMITY_FOCUS_ENTER;
            const nearFocus = this._findNearestStele(radius);
            if (nearFocus) {
                const enterCheck = (nearFocus.index === this.focusedSteleIndex)
                    || (Math.hypot(this.player.x - nearFocus.x, this.player.z - nearFocus.z) < PROXIMITY_FOCUS_ENTER);
                if (enterCheck) {
                    this._setFocusedSteleIndex(nearFocus.index);
                }
            } else {
                this._setFocusedSteleIndex(-1);
            }
        }

        // 3. Voll artikulierte Avatar-Gelenkanimation, Mitspieler-Interpolation & Prestige-Waffen-Funken aktualisieren
        this._updatePlayerKinematics(dt, stepDist, turnRate, nearestGaze);
        this._updatePeersKinematics(dt);
        this._updateWeaponBursts(dt);

        // 4. Sanfte Rotation aller Exponate in ihren Vitrinen
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

        // 5. Gedaempfte Museums-Verfolgerkamera
        const camDamp = 1.0 - Math.exp(-9.5 * dt);
        this.camControl.yaw = lerpAngle(this.camControl.yaw, this.camControl.desiredYaw, camDamp);
        this.camControl.pitch = lerp(this.camControl.pitch, this.camControl.desiredPitch, camDamp);
        this.camControl.distance = lerp(this.camControl.distance, this.camControl.desiredDistance, camDamp);

        this.camControl.lookAtCurrent.x = lerp(this.camControl.lookAtCurrent.x, this.player.x, camDamp);
        this.camControl.lookAtCurrent.y = lerp(this.camControl.lookAtCurrent.y, 1.38 + (this.player.y || 0) * 0.35, camDamp);
        this.camControl.lookAtCurrent.z = lerp(this.camControl.lookAtCurrent.z, this.player.z, camDamp);

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

        // 6. Schwebende Raum-Tafel per GPU-Matrix positionieren (nach Kamera-Update, 0 Layout-Reflows)
        this._updateFloatingCardProjection();

        this.renderer.render(this.scene, this.camera);
    }

    resize() {
        if (!this.renderer || !this.canvas) return;
        const parent = this.canvas.parentElement;
        const w = Math.max(1, parent ? parent.clientWidth : window.innerWidth);
        const h = Math.max(1, parent ? parent.clientHeight : window.innerHeight);
        this._viewW = w;
        this._viewH = h;

        if (!this.camera) {
            this.camera = new this.THREE.PerspectiveCamera(50, w / h, 0.1, 220);
        } else {
            this.camera.aspect = w / h;
            this.camera.updateProjectionMatrix();
        }

        // Strikte Pixel-Ratio-Begrenzung auf max. 1.5 gegen 4K/Retina-Fillrate-Einbrueche
        this.renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 1.5));
        this.renderer.setSize(w, h, false);
    }

    destroy() {
        this.running = false;
        this.destroyed = true;

        if (this.rafId) {
            cancelAnimationFrame(this.rafId);
            this.rafId = 0;
        }
        if (this.queueTimerId) {
            clearTimeout(this.queueTimerId);
            this.queueTimerId = 0;
        }
        if (this.abortController) {
            try {
                this.abortController.abort();
            } catch (err) {
                // Bereits abgebrochen
            }
            this.abortController = null;
        }

        if (this.floatingCardEl) {
            this.floatingCardEl.style.opacity = '0';
            this.floatingCardEl.style.pointerEvents = 'none';
            this.floatingCardEl = null;
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

        this._clearAllPeers();
        for (let bi = 0; bi < this.weaponBursts.length; bi++) {
            this._disposeWeaponBurst(this.weaponBursts[bi]);
        }
        this.weaponBursts = [];
        if (this.player.bubbleTex) {
            this.player.bubbleTex.dispose();
            this.player.bubbleTex = null;
        }
        if (this.player.bubbleMat) {
            this.player.bubbleMat.dispose();
            this.player.bubbleMat = null;
        }
        this.player.bubbleSprite = null;

        for (let i = 0; i < this.disposables.length; i++) {
            const d = this.disposables[i];
            if (d && typeof d.dispose === 'function') {
                d.dispose();
            }
        }
        this.disposables = [];
        this._avatarShared = null;
        this.glbCache.clear();
        this.exhibitLoadQueue = [];
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
