import * as THREE from '/vendor/funkenwerk-3d/build/three.module.min.js';
import { OrbitControls } from '/vendor/funkenwerk-3d/jsm/controls/OrbitControls.js';
import { STLLoader } from '/vendor/funkenwerk-3d/jsm/loaders/STLLoader.js';
import { ThreeMFLoader } from '/vendor/funkenwerk-3d/jsm/loaders/3MFLoader.js';
import { GLTFLoader } from '/vendor/funkenwerk-3d/jsm/loaders/GLTFLoader.js';
import { OBJLoader } from '/vendor/funkenwerk-3d/jsm/loaders/OBJLoader.js';

let renderer = null;
let scene = null;
let camera = null;
let controls = null;
let animFrameId = null;
let modelGroup = null;
let bedBoxHelper = null;
let activeMode = 'hologram'; // 'hologram' | 'matte'
let turntableActive = true;
let activeContainer = null;

function disposeCurrentModel() {
    if (!modelGroup) return;
    modelGroup.traverse((child) => {
        if (child.geometry) child.geometry.dispose();
        if (child.material) {
            if (Array.isArray(child.material)) child.material.forEach((m) => m.dispose());
            else child.material.dispose();
        }
    });
    if (scene) scene.remove(modelGroup);
    modelGroup = null;
}

function applyMaterialMode(mode) {
    activeMode = mode;
    if (!modelGroup) return;

    modelGroup.traverse((child) => {
        if (!child.isMesh) return;
        if (child.material) {
            if (Array.isArray(child.material)) child.material.forEach((m) => m.dispose());
            else child.material.dispose();
        }
        if (mode === 'hologram') {
            child.material = new THREE.MeshStandardMaterial({
                color: 0x38bdf8,
                emissive: 0x0284c7,
                emissiveIntensity: 0.45,
                wireframe: true,
                transparent: true,
                opacity: 0.88,
            });
        } else {
            child.material = new THREE.MeshStandardMaterial({
                color: 0x262930,
                roughness: 0.78,
                metalness: 0.08,
                flatShading: false,
            });
        }
    });
}

function buildBambuBedCage() {
    const group = new THREE.Group();
    const bedW = 256;
    const bedD = 256;
    const bedH = 256;

    const grid = new THREE.GridHelper(bedW, 16, 0xc5a059, 0x1e293b);
    grid.position.y = 0;
    group.add(grid);

    const boxGeom = new THREE.BoxGeometry(bedW, bedH, bedD);
    const edges = new THREE.EdgesGeometry(boxGeom);
    boxGeom.dispose();
    const lineMat = new THREE.LineBasicMaterial({
        color: 0xc5a059,
        transparent: true,
        opacity: 0.35,
    });
    const cage = new THREE.LineSegments(edges, lineMat);
    cage.position.y = bedH * 0.5;
    group.add(cage);

    return group;
}

function ensureScene(container) {
    if (renderer && activeContainer === container) {
        const w = Math.max(320, container.clientWidth);
        const h = Math.max(260, container.clientHeight);
        renderer.setSize(w, h);
        camera.aspect = w / h;
        camera.updateProjectionMatrix();
        return;
    }

    if (renderer) {
        renderer.dispose();
        renderer = null;
    }

    activeContainer = container;
    container.innerHTML = '';

    const w = Math.max(320, container.clientWidth);
    const h = Math.max(260, container.clientHeight);

    renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true, powerPreference: 'high-performance' });
    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
    renderer.setSize(w, h);
    renderer.outputColorSpace = THREE.SRGBColorSpace;
    container.appendChild(renderer.domElement);

    scene = new THREE.Scene();
    camera = new THREE.PerspectiveCamera(40, w / h, 0.5, 4000);
    camera.position.set(280, 210, 310);

    scene.add(new THREE.HemisphereLight(0xe0f2fe, 0x0f172a, 0.95));
    const dir1 = new THREE.DirectionalLight(0xffffff, 1.35);
    dir1.position.set(260, 420, 300);
    scene.add(dir1);
    const dir2 = new THREE.DirectionalLight(0xc5a059, 0.65);
    dir2.position.set(-240, 180, -220);
    scene.add(dir2);

    bedBoxHelper = buildBambuBedCage();
    scene.add(bedBoxHelper);

    controls = new OrbitControls(camera, renderer.domElement);
    controls.enableDamping = true;
    controls.dampingFactor = 0.07;
    controls.target.set(0, 65, 0);
}

function startLoop() {
    if (animFrameId) cancelAnimationFrame(animFrameId);
    let lastTime = performance.now();

    const tick = (now) => {
        animFrameId = requestAnimationFrame(tick);
        const dt = Math.min(0.05, (now - lastTime) / 1000);
        lastTime = now;

        if (turntableActive && modelGroup) {
            modelGroup.rotation.y += dt * 0.55;
        }
        if (controls) controls.update();
        if (renderer && scene && camera) renderer.render(scene, camera);
    };
    animFrameId = requestAnimationFrame(tick);
}

export async function openHologramModel(container, url, ext, onStatsCallback) {
    ensureScene(container);
    disposeCurrentModel();

    const cleanExt = String(ext || '').toLowerCase().replace(/^\./, '');
    let root = new THREE.Group();

    if (cleanExt === 'stl') {
        const loader = new STLLoader();
        const geom = await loader.loadAsync(url);
        geom.computeVertexNormals();
        const mesh = new THREE.Mesh(geom);
        mesh.rotation.x = -Math.PI / 2; // Z-up CAD -> Y-up Three.js
        root.add(mesh);
    } else if (cleanExt === '3mf') {
        const loader = new ThreeMFLoader();
        const obj3mf = await loader.loadAsync(url);
        obj3mf.rotation.x = -Math.PI / 2;
        root.add(obj3mf);
    } else if (cleanExt === 'obj') {
        const loader = new OBJLoader();
        const obj = await loader.loadAsync(url);
        root.add(obj);
    } else {
        const loader = new GLTFLoader();
        const gltf = await loader.loadAsync(url);
        root.add(gltf.scene);
    }

    root.updateMatrixWorld(true);
    const box = new THREE.Box3().setFromObject(root);
    const size = new THREE.Vector3();
    const center = new THREE.Vector3();
    box.getSize(size);
    box.getCenter(center);

    // Falls GLB in Metern vorliegt (< 2.5 Einheiten), auf Millimeter skalieren
    if (Math.max(size.x, size.y, size.z) > 0 && Math.max(size.x, size.y, size.z) < 2.5 && (cleanExt === 'glb' || cleanExt === 'gltf')) {
        root.scale.multiplyScalar(1000);
        root.updateMatrixWorld(true);
        box.setFromObject(root);
        box.getSize(size);
        box.getCenter(center);
    }

    // Exakt mittig auf das Bambu-Lab-A1-Druckbett (Y = 0) stellen
    root.position.x -= center.x;
    root.position.z -= center.z;
    root.position.y -= box.min.y;

    const wrapper = new THREE.Group();
    wrapper.add(root);
    modelGroup = wrapper;
    scene.add(modelGroup);

    applyMaterialMode(activeMode);

    let triangles = 0;
    modelGroup.traverse((c) => {
        if (c.isMesh && c.geometry) {
            const idx = c.geometry.index;
            const pos = c.geometry.attributes.position;
            if (idx) triangles += Math.round(idx.count / 3);
            else if (pos) triangles += Math.round(pos.count / 3);
        }
    });

    const maxDim = Math.max(size.x, size.y, size.z, 60);
    controls.target.set(0, size.y * 0.45, 0);
    camera.position.set(maxDim * 1.35, Math.max(size.y * 1.15, 110), maxDim * 1.55);
    controls.update();

    if (typeof onStatsCallback === 'function') {
        onStatsCallback({
            widthMm: Math.round(size.x * 10) / 10,
            depthMm: Math.round(size.z * 10) / 10,
            heightMm: Math.round(size.y * 10) / 10,
            triangles,
            fitsBambuA1: size.x <= 256.1 && size.z <= 256.1 && size.y <= 256.1,
        });
    }

    startLoop();
}

export function setHologramStyle(mode) {
    applyMaterialMode(mode);
}

export function setHologramTurntable(enabled) {
    turntableActive = !!enabled;
}

export function setHologramBedVisible(visible) {
    if (bedBoxHelper) bedBoxHelper.visible = !!visible;
}

export function stopHologramLoop() {
    if (animFrameId) {
        cancelAnimationFrame(animFrameId);
        animFrameId = null;
    }
    disposeCurrentModel();
}

window.SfTicketHologram = {
    openHologramModel,
    setHologramStyle,
    setHologramTurntable,
    setHologramBedVisible,
    stopHologramLoop,
};
