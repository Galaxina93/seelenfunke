x-data="(()=>{
return {
isUploading: false,
showDrawingBoard: true,
transformTarget: 'none',
transformMode: 'translate',
modelPath: '{{ $product?->three_d_model_path ? asset('storage/'.$product->three_d_model_path) : '' }}',
bgPath: '{{ $product?->three_d_background_path ? asset('storage/'.$product->three_d_background_path) : '' }}',
fallbackImg: '{{ $product?->preview_image_path ? asset('storage/'.$product->preview_image_path) : '' }}',
configSettings: @entangle('configSettings'),
isDraggingPoint: null,
isInitialized: false,

// 1. ENGINE ZU 100% VON ALPINE ISOLIEREN
getEngine() {
if (!window._adminThreeEngine) {
window._adminThreeEngine = {
scene: null, camera: null, renderer: null, controls: null, tControls: null,
model: null, modelContainer: null, texturePlane: null, textureCanvas: null,
textureCtx: null, texture: null
};
}
return window._adminThreeEngine;
},

// 2. RAW HELPER: Entfernt garantiert alle Alpine.js / Vue Proxies!
getRaw(obj) {
if (!obj) return obj;
if (typeof Alpine !== 'undefined' && Alpine.raw) return Alpine.raw(obj);
if (obj.__v_raw) return obj.__v_raw;
return obj;
},

init() {
if (!this.configSettings) this.configSettings = {};

if (typeof this.configSettings.area_shape === 'undefined') this.configSettings.area_shape = 'rect';
if (typeof this.configSettings.area_left === 'undefined') this.configSettings.area_left = 10;
if (typeof this.configSettings.area_top === 'undefined') this.configSettings.area_top = 10;
if (typeof this.configSettings.area_width === 'undefined') this.configSettings.area_width = 80;
if (typeof this.configSettings.area_height === 'undefined') this.configSettings.area_height = 80;
if (!this.configSettings.custom_points || this.configSettings.custom_points.length === 0) {
this.configSettings.custom_points = [{x:20,y:20}, {x:80,y:20}, {x:80,y:80}, {x:20,y:80}];
}

if (typeof this.configSettings.material_type === 'undefined') this.configSettings.material_type = 'glass';
if (typeof this.configSettings.model_scale === 'undefined') this.configSettings.model_scale = 100;
if (typeof this.configSettings.model_pos_x === 'undefined') this.configSettings.model_pos_x = 0;
if (typeof this.configSettings.model_pos_y === 'undefined') this.configSettings.model_pos_y = 0;
if (typeof this.configSettings.model_pos_z === 'undefined') this.configSettings.model_pos_z = 0;
if (typeof this.configSettings.model_rot_x === 'undefined') this.configSettings.model_rot_x = 0;
if (typeof this.configSettings.model_rot_y === 'undefined') this.configSettings.model_rot_y = 0;
if (typeof this.configSettings.model_rot_z === 'undefined') this.configSettings.model_rot_z = 0;

if (typeof this.configSettings.engraving_scale === 'undefined') this.configSettings.engraving_scale = 100;
if (typeof this.configSettings.engraving_pos_x === 'undefined') this.configSettings.engraving_pos_x = 0;
if (typeof this.configSettings.engraving_pos_y === 'undefined') this.configSettings.engraving_pos_y = 0;
if (typeof this.configSettings.engraving_pos_z === 'undefined') this.configSettings.engraving_pos_z = 0;
if (typeof this.configSettings.engraving_rot_x === 'undefined') this.configSettings.engraving_rot_x = 0;
if (typeof this.configSettings.engraving_rot_y === 'undefined') this.configSettings.engraving_rot_y = 0;
if (typeof this.configSettings.engraving_rot_z === 'undefined') this.configSettings.engraving_rot_z = 0;

this.$watch('configSettings', () => {
if (this.modelPath && this.isInitialized) {
this.updateTexture();
this.applyModelTransforms();
}
}, { deep: true });

this.$nextTick(() => {
const startAdmin3D = () => {
if (this.modelPath) {
this.init3D();
} else {
this.showDrawingBoard = true;
}
};

const checkDependencies = () => {
if (window.THREE && window.GLTFLoader && window.OrbitControls && window.TransformControls) {
startAdmin3D();
} else {
setTimeout(checkDependencies, 50);
}
};
checkDependencies();

const observer = new MutationObserver(() => {
let eng = this.getEngine();
let container = this.$refs.adminContainer3d;
if (eng.renderer && container && !container.contains(eng.renderer.domElement)) {
container.innerHTML = '';
container.appendChild(eng.renderer.domElement);
}
});
if(this.$refs.adminContainer3d) {
observer.observe(this.$refs.adminContainer3d, { childList: true });
}
});
},

init3D() {
const container = this.$refs.adminContainer3d;
if (!container) return;

let eng = this.getEngine();
eng.scene = new window.THREE.Scene();
eng.scene.background = null;

eng.camera = new window.THREE.PerspectiveCamera(45, container.offsetWidth / container.offsetHeight, 0.1, 1000);

eng.renderer = new window.THREE.WebGLRenderer({ antialias: true, alpha: true });
eng.renderer.setSize(container.offsetWidth, container.offsetHeight);
eng.renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
container.innerHTML = '';
container.appendChild(eng.renderer.domElement);

eng.scene.add(new window.THREE.AmbientLight(0xffffff, 0.9));
const light = new window.THREE.DirectionalLight(0xffffff, 1.5);
light.position.set(5, 10, 7.5);
eng.scene.add(light);

eng.controls = new window.OrbitControls(eng.camera, eng.renderer.domElement);
eng.controls.enableDamping = true;
eng.controls.enablePan = false;

if (window.TransformControls) {
eng.tControls = new window.TransformControls(eng.camera, eng.renderer.domElement);
eng.tControls.addEventListener('dragging-changed', (event) => {
eng.controls.enabled = !event.value;
if (!event.value) this.syncTransformsToConfig();
});

// 3. FIX: TransformControls Object3D Kompatibilität herstellen
let controlObj = eng.tControls.getHelper ? eng.tControls.getHelper() : eng.tControls;
if (typeof controlObj.isObject3D === 'undefined') {
controlObj.isObject3D = true; // Zwingt ThreeJS das Objekt zu akzeptieren
}
eng.scene.add(this.getRaw(controlObj));
}

eng.textureCanvas = document.createElement('canvas');
eng.textureCanvas.width = 2048;
eng.textureCanvas.height = 2048;
eng.textureCtx = eng.textureCanvas.getContext('2d');
eng.texture = new window.THREE.CanvasTexture(eng.textureCanvas);
eng.texture.flipY = true;

const loader = new window.GLTFLoader();
loader.load(this.modelPath, (gltf) => {
eng.model = gltf.scene;
const box = new window.THREE.Box3().setFromObject(eng.model);
const size = box.getSize(new window.THREE.Vector3());
const center = box.getCenter(new window.THREE.Vector3());

eng.model.position.sub(center);

eng.modelContainer = new window.THREE.Group();

// Raw-Versionen verwenden, um Proxy-Abstürze beim .add() zu vermeiden
eng.modelContainer.add(this.getRaw(eng.model));

const maxPlaneSize = Math.max(size.x, size.y);
const planeGeo = new window.THREE.PlaneGeometry(maxPlaneSize, maxPlaneSize);
const planeMat = new window.THREE.MeshBasicMaterial({
map: eng.texture,
transparent: true,
depthWrite: false,
side: window.THREE.DoubleSide
});
eng.texturePlane = new window.THREE.Mesh(planeGeo, planeMat);
eng.texturePlane.userData.baseZ = (size.z / 2) + 0.05;
eng.texturePlane.position.z = eng.texturePlane.userData.baseZ;

eng.modelContainer.add(this.getRaw(eng.texturePlane));

const maxDim = Math.max(size.x, size.y, size.z);
const fov = eng.camera.fov * (Math.PI / 180);
let cameraZ = Math.abs(maxDim / 2 / Math.tan(fov / 2));

eng.camera.position.set(0, maxDim * 0.2, cameraZ * 1.5);
eng.controls.target.set(0, 0, 0);
eng.camera.updateProjectionMatrix();

eng.scene.add(this.getRaw(eng.modelContainer));

this.isInitialized = true;
this.applyMaterial();
this.updateTexture();
this.applyModelTransforms();
this.updateTransformTarget();

const animate = () => {
if (!this.modelPath || !eng.renderer) return;
requestAnimationFrame(animate);
if (eng.controls) eng.controls.update();
if (eng.scene && eng.camera) eng.renderer.render(eng.scene, eng.camera);
};
animate();
});
},

syncTransformsToConfig() {
let eng = this.getEngine();
if(!eng.tControls || !eng.tControls.object) return;

let obj = eng.tControls.object;
let c = this.configSettings;

if (obj === eng.modelContainer) {
c.model_pos_x = parseFloat((obj.position.x * 10).toFixed(2));
c.model_pos_y = parseFloat((obj.position.y * 10).toFixed(2));
c.model_pos_z = parseFloat((obj.position.z * 10).toFixed(2));
c.model_rot_x = parseFloat((obj.rotation.x * (180/Math.PI)).toFixed(2));
c.model_rot_y = parseFloat((obj.rotation.y * (180/Math.PI)).toFixed(2));
c.model_rot_z = parseFloat((obj.rotation.z * (180/Math.PI)).toFixed(2));
c.model_scale = parseFloat((obj.scale.x * 100).toFixed(2));
} else if (obj === eng.texturePlane) {
c.engraving_pos_x = parseFloat((obj.position.x * 10).toFixed(2));
c.engraving_pos_y = parseFloat((obj.position.y * 10).toFixed(2));
c.engraving_pos_z = parseFloat(((obj.position.z - obj.userData.baseZ) * 10).toFixed(2));
c.engraving_rot_x = parseFloat((obj.rotation.x * (180/Math.PI)).toFixed(2));
c.engraving_rot_y = parseFloat((obj.rotation.y * (180/Math.PI)).toFixed(2));
c.engraving_rot_z = parseFloat((obj.rotation.z * (180/Math.PI)).toFixed(2));
c.engraving_scale = parseFloat((obj.scale.x * 100).toFixed(2));
}
},

updateTransformTarget() {
let eng = this.getEngine();
if(!eng.tControls) return;

eng.tControls.setMode(this.transformMode);

if (this.transformTarget === 'model' && eng.modelContainer) {
eng.tControls.attach(this.getRaw(eng.modelContainer));
} else if (this.transformTarget === 'overlay' && eng.texturePlane) {
eng.tControls.attach(this.getRaw(eng.texturePlane));
} else {
eng.tControls.detach();
}
},

applyMaterial() {
let eng = this.getEngine();
if (!eng.model || !this.configSettings) return;

const matType = this.configSettings.material_type || 'glass';

eng.model.traverse((child) => {
if (child.isMesh && child.material) {
if (matType === 'glass') {
child.material.transparent = true;
child.material.opacity = 0.80;
child.material.roughness = 0.05;
child.material.metalness = 0.3;
child.material.color.setHex(0xffffff);
child.material.depthWrite = false;
child.material.side = window.THREE.FrontSide;
} else if (matType === 'wood') {
child.material.transparent = false;
child.material.opacity = 1.0;
child.material.roughness = 0.9;
child.material.metalness = 0.0;
child.material.depthWrite = true;
} else if (matType === 'metal') {
child.material.transparent = false;
child.material.opacity = 1.0;
child.material.roughness = 0.2;
child.material.metalness = 0.9;
child.material.color.setHex(0xdddddd);
child.material.depthWrite = true;
} else {
child.material.transparent = false;
child.material.opacity = 1.0;
child.material.roughness = 0.5;
child.material.metalness = 0.1;
child.material.depthWrite = true;
}
child.material.needsUpdate = true;
}
});
},

applyModelTransforms() {
let eng = this.getEngine();
if (!eng.modelContainer || !this.configSettings) return;

const c = this.configSettings;
if(eng.tControls && eng.tControls.dragging) return;

const s = (c.model_scale || 100) / 100;
eng.modelContainer.scale.set(s, s, s);

eng.modelContainer.position.set(
(c.model_pos_x || 0) * 0.1,
(c.model_pos_y || 0) * 0.1,
(c.model_pos_z || 0) * 0.1
);

eng.modelContainer.rotation.set(
(c.model_rot_x || 0) * (Math.PI / 180),
(c.model_rot_y || 0) * (Math.PI / 180),
(c.model_rot_z || 0) * (Math.PI / 180)
);

if(eng.texturePlane) {
const s_eng = (c.engraving_scale || 100) / 100;
eng.texturePlane.scale.set(s_eng, s_eng, s_eng);

eng.texturePlane.position.set(
(c.engraving_pos_x || 0) * 0.1,
(c.engraving_pos_y || 0) * 0.1,
(eng.texturePlane.userData.baseZ || 0) + ((c.engraving_pos_z || 0) * 0.1)
);

eng.texturePlane.rotation.set(
(c.engraving_rot_x || 0) * (Math.PI / 180),
(c.engraving_rot_y || 0) * (Math.PI / 180),
(c.engraving_rot_z || 0) * (Math.PI / 180)
);
}
},

updateTexture() {
let eng = this.getEngine();
if(!eng.textureCtx || !this.configSettings) return;

eng.textureCtx.clearRect(0, 0, 2048, 2048);

eng.textureCtx.fillStyle = 'rgba(16, 185, 129, 0.4)';
eng.textureCtx.strokeStyle = '#10b981';
eng.textureCtx.lineWidth = 10;

const shape = this.configSettings.area_shape || 'rect';

if (shape !== 'custom') {
let x = ((this.configSettings.area_left || 0) / 100) * 2048;
let y = ((this.configSettings.area_top || 0) / 100) * 2048;
let w = ((this.configSettings.area_width || 100) / 100) * 2048;
let h = ((this.configSettings.area_height || 100) / 100) * 2048;

if (shape === 'rect') {
eng.textureCtx.fillRect(x, y, w, h);
eng.textureCtx.strokeRect(x, y, w, h);
} else if (shape === 'circle') {
eng.textureCtx.beginPath();
eng.textureCtx.ellipse(x + w/2, y + h/2, w/2, h/2, 0, 0, Math.PI * 2);
eng.textureCtx.fill();
eng.textureCtx.stroke();
}
} else {
let pts = this.configSettings.custom_points;
if(pts && pts.length > 0) {
eng.textureCtx.beginPath();
pts.forEach((p, i) => {
let px = ((p.x || 0) / 100) * 2048;
let py = ((p.y || 0) / 100) * 2048;
if(i === 0) eng.textureCtx.moveTo(px, py);
else eng.textureCtx.lineTo(px, py);
});
eng.textureCtx.closePath();
eng.textureCtx.fill();
eng.textureCtx.stroke();
}
}

if(eng.texture) eng.texture.needsUpdate = true;
},

startDragPoint(idx, event) {
this.isDraggingPoint = idx;
},

dragPoint(event) {
if(this.isDraggingPoint === null || this.configSettings.area_shape !== 'custom') return;

const rect = this.$refs.adminContainer2d.getBoundingClientRect();
if (!rect.width || !rect.height) return;

const x = Math.max(0, Math.min(100, ((event.clientX - rect.left) / rect.width) * 100));
const y = Math.max(0, Math.min(100, ((event.clientY - rect.top) / rect.height) * 100));

// Klone das Array sauber, um Reaktivitäts-Konflikte zu vermeiden
let pts = JSON.parse(JSON.stringify(this.configSettings.custom_points)).filter(p => p !== null);

if(pts[this.isDraggingPoint]) {
pts[this.isDraggingPoint].x = parseFloat(x.toFixed(2));
pts[this.isDraggingPoint].y = parseFloat(y.toFixed(2));

// Zuweisung löst Alpine/Livewire Sync aus
this.configSettings.custom_points = pts;
this.updateTexture();
}
},

stopDragPoint() {
this.isDraggingPoint = null;
},

addPoint(e) {
// 1. Nur ausführen, wenn die Form auf 'custom' steht
if(this.configSettings.area_shape !== 'custom') return;

// 2. Nur ausführen, wenn das Zeichenbrett aktiv ist (verhindert Infinity-Fehler)
if(!this.showDrawingBoard) return;

const rect = this.$refs.adminContainer2d.getBoundingClientRect();

// 3. Sicherheitscheck: Wenn das Element keine Maße hat (z.B. ausgeblendet), Abbruch
if (!rect.width || !rect.height) return;

// 4. Verhindern, dass Klicks auf existierende Punkte neue Punkte erzeugen
if(e.target.closest('.point-handle')) return;

const x = parseFloat((((e.clientX - rect.left) / rect.width) * 100).toFixed(2));
const y = parseFloat((((e.clientY - rect.top) / rect.height) * 100).toFixed(2));

// 5. Validierung der Werte
if (isNaN(x) || isNaN(y) || !isFinite(x) || !isFinite(y)) return;

// 6. Sauberes Update des Arrays
let pts = JSON.parse(JSON.stringify(this.configSettings.custom_points || [])).filter(p => p !== null);
pts.push({x: x, y: y});

this.configSettings.custom_points = pts;
this.updateTexture();
},

resetModel() {
this.configSettings.model_scale=100;
this.configSettings.model_pos_x=0; this.configSettings.model_pos_y=0; this.configSettings.model_pos_z=0;
this.configSettings.model_rot_x=0; this.configSettings.model_rot_y=0; this.configSettings.model_rot_z=0;
this.applyModelTransforms();
},

resetOverlay() {
this.configSettings.engraving_scale=100;
this.configSettings.engraving_pos_x=0; this.configSettings.engraving_pos_y=0; this.configSettings.engraving_pos_z=0;
this.configSettings.engraving_rot_x=0; this.configSettings.engraving_rot_y=0; this.configSettings.engraving_rot_z=0;
this.applyModelTransforms();
}
};
})()"
