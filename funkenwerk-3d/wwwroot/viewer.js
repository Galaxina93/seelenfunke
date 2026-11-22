import * as THREE from './vendor/build/three.module.min.js';
import { OrbitControls } from './vendor/jsm/controls/OrbitControls.js';
import { RoomEnvironment } from './vendor/jsm/environments/RoomEnvironment.js';
import { GLTFLoader } from './vendor/jsm/loaders/GLTFLoader.js';
import { DRACOLoader } from './vendor/jsm/loaders/DRACOLoader.js';
import { MeshoptDecoder } from './vendor/jsm/libs/meshopt_decoder.module.js';
import { STLLoader } from './vendor/jsm/loaders/STLLoader.js';
import { OBJLoader } from './vendor/jsm/loaders/OBJLoader.js';
import { MTLLoader } from './vendor/jsm/loaders/MTLLoader.js';
import { PLYLoader } from './vendor/jsm/loaders/PLYLoader.js';
import { FBXLoader } from './vendor/jsm/loaders/FBXLoader.js';
import { ThreeMFLoader } from './vendor/jsm/loaders/3MFLoader.js';
import { TransformControls } from './vendor/jsm/controls/TransformControls.js';
import {
    prepareCharacterGeometryAndMaterial,
    computeDefaultJointLandmarks,
    buildRiggedCharacterFromLandmarks,
    extractExistingRigState,
    exportRiggedCharacterToGlbBase64Async
} from './rigging-engine.js';

const wrap = document.getElementById('canvas-wrap');
const emptyState = document.getElementById('empty-state');
const dimLabelsWrap = document.getElementById('dim-labels');
const loadingPill = document.getElementById('loading-pill');

function postHost(payload) {
    if (window.chrome && window.chrome.webview) {
        window.chrome.webview.postMessage(payload);
    }
}

const renderer = new THREE.WebGLRenderer({
    antialias: true,
    alpha: true,
    preserveDrawingBuffer: true,
    powerPreference: 'high-performance'
});
renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
renderer.setSize(window.innerWidth, window.innerHeight);
renderer.outputColorSpace = THREE.SRGBColorSpace;
renderer.toneMapping = THREE.NeutralToneMapping;
renderer.toneMappingExposure = 1.05;
renderer.shadowMap.enabled = true;
renderer.shadowMap.type = THREE.PCFSoftShadowMap;
wrap.insertBefore(renderer.domElement, emptyState);

const scene = new THREE.Scene();
const camera = new THREE.PerspectiveCamera(42, window.innerWidth / Math.max(1, window.innerHeight), 0.001, 2000);
camera.position.set(2.2, 1.6, 2.6);

const pmrem = new THREE.PMREMGenerator(renderer);
const studioEnvTex = pmrem.fromScene(new RoomEnvironment(), 0.04).texture;
scene.environment = studioEnvTex;
scene.environmentIntensity = 0.92;

const hemiLight = new THREE.HemisphereLight(0xffffff, 0x3b4252, 0.65);
scene.add(hemiLight);

const keyLight = new THREE.DirectionalLight(0xfffaf0, 1.35);
keyLight.position.set(4, 7, 5);
scene.add(keyLight);

const rimLight = new THREE.DirectionalLight(0xe2e8f0, 0.55);
rimLight.position.set(-5, 3, -4);
scene.add(rimLight);

const controls = new OrbitControls(camera, renderer.domElement);
controls.enableDamping = true;
controls.dampingFactor = 0.065;
controls.autoRotate = false;
controls.autoRotateSpeed = 1.6;
controls.screenSpacePanning = true;

const boneTransformControls = new TransformControls(camera, renderer.domElement);
boneTransformControls.setMode('rotate');
boneTransformControls.setSpace('local');
boneTransformControls.setSize(0.55);
const boneTransformHelper = typeof boneTransformControls.getHelper === 'function'
    ? boneTransformControls.getHelper()
    : boneTransformControls;
scene.add(boneTransformHelper);

boneTransformControls.addEventListener('dragging-changed', ev => {
    controls.enabled = !ev.value;
    if (ev.value && animPlaying && activeAction) {
        animPlaying = false;
        activeAction.paused = true;
        postHost({ type: 'poseManualStarted' });
    }
});

// Ground Studio Grid
let gridHelper = null;
let showGrid = true;
let isDarkTheme = true;
let activeBackdrop = 'auto'; // auto | shop_light | atelier_dark | crystal_black | pure_white

// Loaded model & Exoskeleton state
let currentRoot = null;
let currentSourceContent = null;
let unriggedSourceTemplate = null;
let activeRigState = null;
let skeletonHelper = null;
let showBonesOverlay = false;
let currentFormat = '';
let rawBoundsSize = new THREE.Vector3(0, 0, 0);
let normalizedRadius = 1.0;
let normalizedBox = new THREE.Box3();
let bboxHelperGroup = null;
let showDimensions = false;
let wireframeEnabled = false;
let unitMode = 'auto'; // auto | mm | m
let meshRegistry = [];
let mixer = null;
let animations = [];
let activeAction = null;
let animPlaying = true;
const clock = new THREE.Clock();

// Reusable Loaders
const dracoLoader = new DRACOLoader();
dracoLoader.setDecoderPath('./vendor/jsm/libs/draco/gltf/');

const gltfLoader = new GLTFLoader();
gltfLoader.setDRACOLoader(dracoLoader);
if (MeshoptDecoder) {
    gltfLoader.setMeshoptDecoder(MeshoptDecoder);
}

const stlLoader = new STLLoader();
const objLoader = new OBJLoader();
const mtlLoader = new MTLLoader();
const plyLoader = new PLYLoader();
const fbxLoader = new FBXLoader();
const threeMfLoader = new ThreeMFLoader();

// Dimension HTML tags (X, Y, Z)
const tagX = createDimTag();
const tagY = createDimTag();
const tagZ = createDimTag();

function createDimTag() {
    const el = document.createElement('div');
    el.className = 'dim-tag';
    el.style.display = 'none';
    dimLabelsWrap.appendChild(el);
    return el;
}

function getEffectiveBackgroundHex() {
    if (activeBackdrop === 'shop_light') return '#F4F6F9';
    if (activeBackdrop === 'atelier_dark') return '#0E121B';
    if (activeBackdrop === 'crystal_black') return '#050608';
    if (activeBackdrop === 'pure_white') return '#FFFFFF';
    return isDarkTheme ? '#0E121B' : '#F4F6F9';
}

function isLightBackdrop() {
    const hex = getEffectiveBackgroundHex();
    return hex === '#F4F6F9' || hex === '#FFFFFF';
}

function applyThemeAndBackdrop() {
    const bgHex = getEffectiveBackgroundHex();
    const lightBg = isLightBackdrop();
    document.body.classList.toggle('light-mode', lightBg);
    document.body.style.background = bgHex;
    scene.background = new THREE.Color(bgHex);
    rebuildGrid();
}

function rebuildGrid() {
    if (gridHelper) {
        scene.remove(gridHelper);
        gridHelper.geometry.dispose();
        if (Array.isArray(gridHelper.material)) gridHelper.material.forEach(m => m.dispose());
        else gridHelper.material.dispose();
        gridHelper = null;
    }
    if (!showGrid) return;

    const lightBg = isLightBackdrop();
    const span = Math.max(2.0, Math.ceil(normalizedRadius * 4.0));
    const centerColor = 0xC5A059;
    const gridColor = lightBg ? 0xCBD5E1 : 0x232A3B;
    gridHelper = new THREE.GridHelper(span, 20, centerColor, gridColor);
    gridHelper.material.opacity = lightBg ? 0.65 : 0.55;
    gridHelper.material.transparent = true;
    gridHelper.position.y = currentRoot ? normalizedBox.min.y : 0;
    scene.add(gridHelper);
}

function disposeSkeletonHelper() {
    if (skeletonHelper) {
        scene.remove(skeletonHelper);
        if (skeletonHelper.geometry) skeletonHelper.geometry.dispose();
        if (skeletonHelper.material) {
            if (Array.isArray(skeletonHelper.material)) skeletonHelper.material.forEach(m => m.dispose());
            else skeletonHelper.material.dispose();
        }
        skeletonHelper = null;
    }
}

function rebuildSkeletonHelper() {
    disposeSkeletonHelper();
    if (!showBonesOverlay || !activeRigState || !activeRigState.bones || activeRigState.bones.length === 0) {
        return;
    }
    const rootBoneParent = activeRigState.bones[0].parent || activeRigState.exportGroup;
    skeletonHelper = new THREE.SkeletonHelper(rootBoneParent);
    skeletonHelper.frustumCulled = false;
    if (skeletonHelper.material) {
        skeletonHelper.material.depthTest = false;
        skeletonHelper.material.transparent = true;
        skeletonHelper.material.opacity = 0.92;
    }
    skeletonHelper.renderOrder = 999;
    scene.add(skeletonHelper);
}

function collectReferencedTextures(root) {
    const set = new Set();
    if (!root) return set;
    root.traverse(obj => {
        if (obj.material) {
            const mats = Array.isArray(obj.material) ? obj.material : [obj.material];
            for (const m of mats) {
                for (const k of Object.keys(m)) {
                    const val = m[k];
                    if (val && val.isTexture) set.add(val);
                }
            }
        }
    });
    return set;
}

function clearCurrentModel(preserveUnriggedBackup = false) {
    boneTransformControls.detach();
    disposeSkeletonHelper();

    if (mixer) {
        mixer.stopAllAction();
        mixer = null;
    }
    animations = [];
    activeAction = null;
    activeRigState = null;
    currentSourceContent = null;
    meshRegistry = [];

    if (!preserveUnriggedBackup && unriggedSourceTemplate) {
        unriggedSourceTemplate.traverse(obj => {
            if (obj.geometry) obj.geometry.dispose();
            if (obj.material) {
                const mats = Array.isArray(obj.material) ? obj.material : [obj.material];
                mats.forEach(m => m.dispose());
            }
        });
        unriggedSourceTemplate = null;
    }

    const protectedTextures = preserveUnriggedBackup
        ? collectReferencedTextures(unriggedSourceTemplate)
        : new Set();

    if (bboxHelperGroup) {
        scene.remove(bboxHelperGroup);
        bboxHelperGroup.traverse(o => {
            if (o.geometry) o.geometry.dispose();
            if (o.material) {
                if (Array.isArray(o.material)) o.material.forEach(m => m.dispose());
                else o.material.dispose();
            }
        });
        bboxHelperGroup = null;
    }

    if (currentRoot) {
        scene.remove(currentRoot);
        currentRoot.traverse(obj => {
            if (obj.geometry) obj.geometry.dispose();
            if (obj.material) {
                const mats = Array.isArray(obj.material) ? obj.material : [obj.material];
                for (const m of mats) {
                    for (const k of Object.keys(m)) {
                        const val = m[k];
                        if (val && val.isTexture && !protectedTextures.has(val)) {
                            val.dispose();
                        }
                    }
                    m.dispose();
                }
            }
        });
        currentRoot = null;
    }

    tagX.style.display = 'none';
    tagY.style.display = 'none';
    tagZ.style.display = 'none';
}

function createMatteDefaultMaterial(hasVertexColors) {
    return new THREE.MeshStandardMaterial({
        color: hasVertexColors ? 0xffffff : 0xd8dee9,
        metalness: 0.18,
        roughness: 0.42,
        vertexColors: !!hasVertexColors,
        side: THREE.DoubleSide
    });
}

function computeMillimeters(vec) {
    let scaleToMm = 1.0;
    if (unitMode === 'm') {
        scaleToMm = 1000.0;
    } else if (unitMode === 'mm') {
        scaleToMm = 1.0;
    } else {
        // Auto: Khronos glTF/GLB standard unit is meters (1.0 = 1000 mm); STL/3MF/OBJ/PLY CAD exports are typically millimeters
        if (currentFormat === 'glb' || currentFormat === 'gltf') {
            scaleToMm = 1000.0;
        } else {
            scaleToMm = 1.0;
        }
    }
    return {
        widthMm: vec.x * scaleToMm,
        heightMm: vec.y * scaleToMm,
        depthMm: vec.z * scaleToMm,
        inferredUnit: scaleToMm === 1000.0 ? 'm -> mm (glTF-Standard)' : 'mm (1 Einheit = 1 mm)'
    };
}

function formatMm(val) {
    if (!isFinite(val)) return '0,0 mm';
    if (val >= 10000) return (val / 1000.0).toFixed(2).replace('.', ',') + ' m';
    return val.toFixed(1).replace('.', ',') + ' mm';
}

function buildDimensionCage() {
    if (bboxHelperGroup) {
        scene.remove(bboxHelperGroup);
        bboxHelperGroup = null;
    }
    if (!currentRoot || !showDimensions) {
        tagX.style.display = 'none';
        tagY.style.display = 'none';
        tagZ.style.display = 'none';
        return;
    }

    bboxHelperGroup = new THREE.Group();
    const boxGeom = new THREE.BoxGeometry(
        Math.max(0.0001, normalizedBox.max.x - normalizedBox.min.x),
        Math.max(0.0001, normalizedBox.max.y - normalizedBox.min.y),
        Math.max(0.0001, normalizedBox.max.z - normalizedBox.min.z)
    );
    const edges = new THREE.EdgesGeometry(boxGeom);
    boxGeom.dispose();
    const lineMat = new THREE.LineBasicMaterial({ color: 0xC5A059, transparent: true, opacity: 0.88 });
    const cage = new THREE.LineSegments(edges, lineMat);
    normalizedBox.getCenter(cage.position);
    bboxHelperGroup.add(cage);
    scene.add(bboxHelperGroup);
}

function updateDimensionLabelsScreenPos() {
    if (!currentRoot || !showDimensions) {
        tagX.style.display = 'none';
        tagY.style.display = 'none';
        tagZ.style.display = 'none';
        return;
    }

    const mm = computeMillimeters(rawBoundsSize);
    tagX.textContent = 'B: ' + formatMm(mm.widthMm);
    tagY.textContent = 'H: ' + formatMm(mm.heightMm);
    tagZ.textContent = 'T: ' + formatMm(mm.depthMm);

    const midX = new THREE.Vector3((normalizedBox.min.x + normalizedBox.max.x) * 0.5, normalizedBox.min.y, normalizedBox.max.z);
    const midY = new THREE.Vector3(normalizedBox.max.x, (normalizedBox.min.y + normalizedBox.max.y) * 0.5, normalizedBox.max.z);
    const midZ = new THREE.Vector3(normalizedBox.max.x, normalizedBox.min.y, (normalizedBox.min.z + normalizedBox.max.z) * 0.5);

    placeTag(tagX, midX);
    placeTag(tagY, midY);
    placeTag(tagZ, midZ);
}

function placeTag(el, worldPt) {
    const p = worldPt.clone().project(camera);
    if (p.z > 1 || p.z < -1) {
        el.style.display = 'none';
        return;
    }
    const x = (p.x * 0.5 + 0.5) * window.innerWidth;
    const y = (-p.y * 0.5 + 0.5) * window.innerHeight;
    el.style.left = `${x.toFixed(1)}px`;
    el.style.top = `${y.toFixed(1)}px`;
    el.style.display = 'block';
}

function applyWireframeState() {
    if (!currentRoot) return;
    currentRoot.traverse(obj => {
        if (obj.isMesh && obj.material) {
            const mats = Array.isArray(obj.material) ? obj.material : [obj.material];
            mats.forEach(m => { m.wireframe = wireframeEnabled; });
        }
    });
}

function setCameraView(preset) {
    if (!currentRoot) return;
    const center = new THREE.Vector3();
    normalizedBox.getCenter(center);
    const dist = Math.max(0.25, normalizedRadius * 2.55);

    controls.target.copy(center);
    switch (preset) {
        case 'front':
            camera.position.set(center.x, center.y, center.z + dist);
            break;
        case 'back':
            camera.position.set(center.x, center.y, center.z - dist);
            break;
        case 'left':
            camera.position.set(center.x - dist, center.y, center.z);
            break;
        case 'right':
            camera.position.set(center.x + dist, center.y, center.z);
            break;
        case 'top':
            camera.position.set(center.x, center.y + dist, center.z + 0.001);
            break;
        case 'iso':
        case 'reset':
        default:
            camera.position.set(center.x + dist * 0.72, center.y + dist * 0.52, center.z + dist * 0.82);
            break;
    }
    camera.near = Math.max(0.0005, normalizedRadius / 500.0);
    camera.far = Math.max(100.0, normalizedRadius * 250.0);
    camera.updateProjectionMatrix();
    controls.update();
}

function snapshotUnriggedTemplate(rootObj) {
    const snapGroup = new THREE.Group();
    rootObj.updateMatrixWorld(true);
    rootObj.traverse(node => {
        if (node.isMesh && node.geometry && node.geometry.attributes.position) {
            const g = node.geometry.clone();
            g.applyMatrix4(node.matrixWorld);
            if (g.attributes.skinIndex) g.deleteAttribute('skinIndex');
            if (g.attributes.skinWeight) g.deleteAttribute('skinWeight');
            const rawMat = Array.isArray(node.material) ? node.material[0] : node.material;
            const m = rawMat ? rawMat.clone() : createMatteDefaultMaterial(!!g.attributes.color);
            m.skinning = false;
            const part = new THREE.Mesh(g, m);
            part.name = node.name || '';
            snapGroup.add(part);
        }
    });
    return snapGroup;
}

function finalizeLoadedObject(rootObj, formatExt, animClips = [], explicitRigState = null, preserveBackup = false) {
    clearCurrentModel(preserveBackup);
    currentFormat = formatExt;
    currentSourceContent = rootObj;

    if (!preserveBackup) {
        unriggedSourceTemplate = snapshotUnriggedTemplate(rootObj);
    }

    const wrapper = new THREE.Group();
    wrapper.add(rootObj);
    wrapper.updateMatrixWorld(true);

    // Measure raw unscaled bounds first for exact physical mm reporting
    const rawBox = new THREE.Box3().setFromObject(rootObj);
    if (rawBox.isEmpty()) {
        rawBox.min.set(-0.5, -0.5, -0.5);
        rawBox.max.set(0.5, 0.5, 0.5);
    }
    rawBox.getSize(rawBoundsSize);

    const rawCenter = new THREE.Vector3();
    rawBox.getCenter(rawCenter);
    const maxAxis = Math.max(rawBoundsSize.x, rawBoundsSize.y, rawBoundsSize.z, 1e-6);

    // Normalize on wrapper so rootObj preserves pristine 1:1 physical meter coordinates & skeleton bind matrices
    const normScale = 2.0 / maxAxis;
    wrapper.scale.setScalar(normScale);
    wrapper.position.set(-rawCenter.x * normScale, -rawBox.min.y * normScale, -rawCenter.z * normScale);

    scene.add(wrapper);
    wrapper.updateMatrixWorld(true);
    currentRoot = wrapper;

    normalizedBox = new THREE.Box3().setFromObject(wrapper);
    const sphere = new THREE.Sphere();
    normalizedBox.getBoundingSphere(sphere);
    normalizedRadius = Math.max(0.25, sphere.radius);

    // Gather geometry stats & mesh list
    let triangles = 0;
    let vertices = 0;
    const materialSet = new Set();
    meshRegistry = [];

    rootObj.traverse(obj => {
        if (obj.isMesh && obj.geometry) {
            const geom = obj.geometry;
            if (!geom.attributes.normal) {
                geom.computeVertexNormals();
            }
            const pos = geom.attributes.position;
            if (pos) {
                vertices += pos.count;
                if (geom.index) {
                    triangles += Math.floor(geom.index.count / 3);
                } else {
                    triangles += Math.floor(pos.count / 3);
                }
            }
            const mats = Array.isArray(obj.material) ? obj.material : (obj.material ? [obj.material] : []);
            mats.forEach(m => materialSet.add(m.uuid));

            const idx = meshRegistry.length;
            const cleanName = (obj.name && obj.name.trim()) ? obj.name.trim() : `Bauteil ${idx + 1}`;
            meshRegistry.push({ index: idx, name: cleanName, visible: obj.visible !== false, ref: obj });
        }
    });

    // Animations & Skeleton detection
    animations = Array.isArray(animClips) ? animClips : [];
    activeRigState = explicitRigState || extractExistingRigState(rootObj, animations);

    if (animations.length > 0) {
        mixer = new THREE.AnimationMixer(rootObj);
        activeAction = mixer.clipAction(animations[0]);
        if (animPlaying) activeAction.play();
    }

    rebuildSkeletonHelper();
    emptyState.classList.add('hidden');
    applyWireframeState();
    rebuildGrid();
    buildDimensionCage();
    setCameraView('iso');

    sendStatsToHost(triangles, vertices, materialSet.size);
}

function sendStatsToHost(triangles, vertices, materialCount) {
    const mm = computeMillimeters(rawBoundsSize);
    postHost({
        type: 'modelLoaded',
        widthMm: mm.widthMm,
        heightMm: mm.heightMm,
        depthMm: mm.depthMm,
        rawX: rawBoundsSize.x,
        rawY: rawBoundsSize.y,
        rawZ: rawBoundsSize.z,
        unitLabel: mm.inferredUnit,
        triangles: triangles ?? 0,
        vertices: vertices ?? 0,
        meshCount: meshRegistry.length,
        materialCount: materialCount ?? 0,
        isRigged: !!activeRigState,
        boneCount: activeRigState ? activeRigState.bones.length : 0,
        showBones: showBonesOverlay,
        bones: activeRigState
            ? activeRigState.bones.map((b, i) => ({ index: i, name: b.name || `Bone_${i}` }))
            : [],
        meshes: meshRegistry.map(m => ({ index: m.index, name: m.name, visible: m.visible })),
        animations: animations.map((a, i) => ({ index: i, name: a.name || `Animation ${i + 1}`, durationSec: Number((a.duration || 0).toFixed(2)) }))
    });
}

async function loadModelFromVirtualUrl(url, formatExt, mtlSiblingName) {
    loadingPill.style.display = 'block';
    try {
        const ext = (formatExt || '').toLowerCase().replace(/^\./, '');
        if (ext === 'glb' || ext === 'gltf') {
            const gltf = await gltfLoader.loadAsync(url);
            finalizeLoadedObject(gltf.scene || gltf.scenes[0], ext, gltf.animations || []);
        } else if (ext === 'stl') {
            const geom = await stlLoader.loadAsync(url);
            geom.computeVertexNormals();
            const hasCol = !!geom.attributes.color;
            const mesh = new THREE.Mesh(geom, createMatteDefaultMaterial(hasCol));
            // STL CAD coordinates are Z-up -> rotate -90 deg on X so models stand upright on floor
            mesh.rotation.x = -Math.PI / 2;
            mesh.name = 'STL Volumenkörper';
            finalizeLoadedObject(mesh, ext, []);
        } else if (ext === 'obj') {
            if (mtlSiblingName) {
                try {
                    const baseSlash = url.substring(0, url.lastIndexOf('/') + 1);
                    mtlLoader.setResourcePath(baseSlash);
                    const materials = await mtlLoader.loadAsync(baseSlash + encodeURIComponent(mtlSiblingName));
                    materials.preload();
                    objLoader.setMaterials(materials);
                } catch {
                    objLoader.setMaterials(null);
                }
            } else {
                objLoader.setMaterials(null);
            }
            const obj = await objLoader.loadAsync(url);
            let hadMat = false;
            obj.traverse(c => {
                if (c.isMesh) {
                    if (!c.material) c.material = createMatteDefaultMaterial(!!c.geometry?.attributes?.color);
                    else hadMat = true;
                }
            });
            void hadMat;
            finalizeLoadedObject(obj, ext, []);
        } else if (ext === 'ply') {
            const geom = await plyLoader.loadAsync(url);
            geom.computeVertexNormals();
            const mesh = new THREE.Mesh(geom, createMatteDefaultMaterial(!!geom.attributes.color));
            mesh.name = 'PLY Punktwolke / Mesh';
            finalizeLoadedObject(mesh, ext, []);
        } else if (ext === 'fbx') {
            const fbx = await fbxLoader.loadAsync(url);
            finalizeLoadedObject(fbx, ext, fbx.animations || []);
        } else if (ext === '3mf') {
            const group = await threeMfLoader.loadAsync(url);
            group.rotation.x = -Math.PI / 2;
            finalizeLoadedObject(group, ext, []);
        } else {
            throw new Error(`Nicht unterstütztes 3D-Format: .${ext}`);
        }
    } catch (err) {
        postHost({
            type: 'modelError',
            message: err && err.message ? String(err.message) : String(err)
        });
    } finally {
        loadingPill.style.display = 'none';
    }
}

function capturePngDataUrl(transparent) {
    const prevBg = scene.background;
    const prevGridVis = gridHelper ? gridHelper.visible : false;
    const prevCageVis = bboxHelperGroup ? bboxHelperGroup.visible : false;
    const prevSkelVis = skeletonHelper ? skeletonHelper.visible : false;
    const prevGizmoVis = boneTransformHelper ? boneTransformHelper.visible : false;

    try {
        if (transparent) {
            scene.background = null;
            renderer.setClearColor(0x000000, 0);
            if (gridHelper) gridHelper.visible = false;
            if (bboxHelperGroup) bboxHelperGroup.visible = false;
            if (skeletonHelper) skeletonHelper.visible = false;
            if (boneTransformHelper) boneTransformHelper.visible = false;
        }
        renderer.render(scene, camera);
        return renderer.domElement.toDataURL('image/png');
    } finally {
        scene.background = prevBg;
        if (gridHelper) gridHelper.visible = prevGridVis;
        if (bboxHelperGroup) bboxHelperGroup.visible = prevCageVis;
        if (skeletonHelper) skeletonHelper.visible = prevSkelVis;
        if (boneTransformHelper) boneTransformHelper.visible = prevGizmoVis;
        renderer.render(scene, camera);
    }
}

function handleGenerateExoskeleton(msg) {
    try {
        const sourceRoot = unriggedSourceTemplate || currentSourceContent;
        if (!sourceRoot) {
            throw new Error('Bitte zuerst ein 3D-Modell öffnen.');
        }

        loadingPill.style.display = 'block';
        const prepared = prepareCharacterGeometryAndMaterial(sourceRoot, {
            stripPedestal: msg.stripPedestal !== false,
            variant: msg.variant || 'original'
        });
        const landmarks = computeDefaultJointLandmarks(msg.profile || 'auto', prepared.height, prepared.widthRatio);
        const rigged = buildRiggedCharacterFromLandmarks(prepared, landmarks);

        showBonesOverlay = msg.showBones !== false;
        animPlaying = msg.autoPlay !== false;
        finalizeLoadedObject(rigged.exportGroup, 'glb', rigged.clips, rigged, true);

        postHost({
            type: 'exoskeletonReady',
            requestId: msg.requestId || '',
            boneCount: rigged.bones.length,
            clipCount: rigged.clips.length,
            profileResolved: landmarks.profileResolved
        });
    } catch (err) {
        postHost({
            type: 'exoskeletonError',
            requestId: msg.requestId || '',
            message: err && err.message ? String(err.message) : String(err)
        });
    } finally {
        loadingPill.style.display = 'none';
    }
}

async function handleExportRiggedGlb(msg) {
    try {
        if (!activeRigState) {
            throw new Error('Noch kein Exoskelett vorhanden. Bitte zuerst Exoskelett erzeugen.');
        }
        loadingPill.style.display = 'block';
        const attachedBone = boneTransformControls.object || null;
        boneTransformControls.detach();
        try {
            const base64Glb = await exportRiggedCharacterToGlbBase64Async(activeRigState, {
                keepCurrentPose: !!msg.keepCurrentPose
            });
            postHost({
                type: 'glbExportReady',
                requestId: msg.requestId || '',
                base64Glb,
                boneCount: activeRigState.bones.length,
                clipCount: (activeRigState.clips || []).length
            });
        } finally {
            if (attachedBone && showBonesOverlay) {
                boneTransformControls.attach(attachedBone);
            }
        }
    } catch (err) {
        postHost({
            type: 'glbExportError',
            requestId: msg.requestId || '',
            message: err && err.message ? String(err.message) : String(err)
        });
    } finally {
        loadingPill.style.display = 'none';
    }
}

// Bridge listener from C# Host
if (window.chrome && window.chrome.webview) {
    window.chrome.webview.addEventListener('message', ev => {
        const msg = ev.data;
        if (!msg || typeof msg !== 'object') return;

        switch (msg.cmd) {
            case 'loadModel':
                loadModelFromVirtualUrl(msg.url, msg.ext, msg.mtlSibling || '');
                break;
            case 'setTheme':
                isDarkTheme = !!msg.dark;
                applyThemeAndBackdrop();
                break;
            case 'setBackdrop':
                activeBackdrop = msg.backdrop || 'auto';
                applyThemeAndBackdrop();
                break;
            case 'setExposure':
                renderer.toneMappingExposure = Math.max(0.2, Math.min(3.0, Number(msg.exposure) || 1.05));
                break;
            case 'setWireframe':
                wireframeEnabled = !!msg.enabled;
                applyWireframeState();
                break;
            case 'setAutoRotate':
                controls.autoRotate = !!msg.enabled;
                break;
            case 'setGrid':
                showGrid = !!msg.enabled;
                rebuildGrid();
                break;
            case 'setDimensions':
                showDimensions = !!msg.enabled;
                buildDimensionCage();
                break;
            case 'setUnitMode':
                unitMode = msg.unitMode || 'auto';
                if (currentRoot) {
                    const mm = computeMillimeters(rawBoundsSize);
                    postHost({
                        type: 'dimensionsUpdated',
                        widthMm: mm.widthMm,
                        heightMm: mm.heightMm,
                        depthMm: mm.depthMm,
                        unitLabel: mm.inferredUnit
                    });
                }
                break;
            case 'setCameraView':
                setCameraView(msg.view || 'iso');
                break;
            case 'setMeshVisible': {
                const entry = meshRegistry[msg.index];
                if (entry && entry.ref) {
                    entry.visible = !!msg.visible;
                    entry.ref.visible = entry.visible;
                }
                break;
            }
            case 'setAnimation': {
                boneTransformControls.detach();
                if (!mixer || !animations.length) break;
                const idx = Math.max(0, Math.min(animations.length - 1, Number(msg.index) || 0));
                mixer.stopAllAction();
                activeAction = mixer.clipAction(animations[idx]);
                animPlaying = msg.playing !== false;
                if (animPlaying) activeAction.play();
                break;
            }
            case 'toggleAnimationPlay': {
                animPlaying = !!msg.playing;
                if (animPlaying) boneTransformControls.detach();
                if (activeAction) {
                    activeAction.paused = !animPlaying;
                    if (animPlaying && !activeAction.isRunning()) activeAction.play();
                }
                break;
            }
            case 'generateExoskeleton':
                handleGenerateExoskeleton(msg);
                break;
            case 'setShowBones':
                showBonesOverlay = !!msg.enabled;
                if (!showBonesOverlay) boneTransformControls.detach();
                rebuildSkeletonHelper();
                break;
            case 'selectBoneForPose': {
                if (!activeRigState || !activeRigState.bones) break;
                const bIdx = Number(msg.index);
                if (!isFinite(bIdx) || bIdx < 0 || bIdx >= activeRigState.bones.length) {
                    boneTransformControls.detach();
                    break;
                }
                if (!showBonesOverlay) {
                    showBonesOverlay = true;
                    rebuildSkeletonHelper();
                }
                if (activeAction && animPlaying) {
                    animPlaying = false;
                    activeAction.paused = true;
                    postHost({ type: 'poseManualStarted' });
                }
                boneTransformControls.attach(activeRigState.bones[bIdx]);
                break;
            }
            case 'resetPose': {
                boneTransformControls.detach();
                if (activeRigState && activeRigState.skeleton) {
                    activeRigState.skeleton.pose();
                }
                if (activeAction) {
                    activeAction.reset();
                    if (!animPlaying) activeAction.paused = true;
                }
                break;
            }
            case 'exportRiggedGlb':
                handleExportRiggedGlb(msg);
                break;
            case 'captureScreenshot': {
                const dataUrl = capturePngDataUrl(!!msg.transparent);
                postHost({
                    type: 'screenshotReady',
                    requestId: msg.requestId || '',
                    transparent: !!msg.transparent,
                    dataUrl
                });
                break;
            }
        }
    });
}

renderer.domElement.addEventListener('dblclick', () => {
    setCameraView('reset');
});

const dropShield = document.getElementById('drop-shield');
let dragDepth = 0;

window.addEventListener('dragenter', e => {
    e.preventDefault();
    dragDepth++;
    if (dropShield) dropShield.classList.add('active');
});

window.addEventListener('dragover', e => {
    e.preventDefault();
    if (e.dataTransfer) e.dataTransfer.dropEffect = 'copy';
});

window.addEventListener('dragleave', e => {
    e.preventDefault();
    dragDepth = Math.max(0, dragDepth - 1);
    if (dragDepth === 0 && dropShield) dropShield.classList.remove('active');
});

window.addEventListener('drop', e => {
    e.preventDefault();
    dragDepth = 0;
    if (dropShield) dropShield.classList.remove('active');
    if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length > 0) {
        if (window.chrome && window.chrome.webview && typeof window.chrome.webview.postMessageWithAdditionalObjects === 'function') {
            window.chrome.webview.postMessageWithAdditionalObjects(
                { type: 'filesDropped' },
                Array.from(e.dataTransfer.files)
            );
        }
    }
});

window.addEventListener('resize', () => {
    const w = Math.max(1, window.innerWidth);
    const h = Math.max(1, window.innerHeight);
    camera.aspect = w / h;
    camera.updateProjectionMatrix();
    renderer.setSize(w, h);
});

function animate() {
    requestAnimationFrame(animate);
    const dt = clock.getDelta();
    if (mixer && animPlaying) {
        mixer.update(dt);
    }
    controls.update();
    renderer.render(scene, camera);
    if (showDimensions && currentRoot) {
        updateDimensionLabelsScreenPos();
    }
}

applyThemeAndBackdrop();
animate();
postHost({ type: 'viewerReady' });
