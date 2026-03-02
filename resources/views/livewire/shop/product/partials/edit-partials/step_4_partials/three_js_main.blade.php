x-data="(()=>{
return {
isUploading: false,
showDrawingBoard: true,
isRaycasting: false,
transformTarget: 'none',
transformMode: 'translate',
modelPath: '{{ $product?->three_d_model_path ? asset("storage/".$product->three_d_model_path) : "" }}',
bgPath: '{{ !empty($product?->three_d_background_path) ? asset("storage/".$product->three_d_background_path) : "" }}',
fallbackImg: '{{ $product?->preview_image_path ? asset("storage/".$product->preview_image_path) : "" }}',
configSettings: @entangle('configSettings'),

activeSide: 'front',

isDraggingPoint: null,
isDraggingMirroredPoint: null,
isInitialized: false,

getEngine() {
if (!window._adminThreeEngine) {
window._adminThreeEngine = {
scene: null, camera: null, renderer: null, controls: null, tControls: null,
model: null, modelContainer: null,
texturePlaneFront: null, texturePlaneBack: null,
textureCanvas: null, textureCtx: null,
textureFront: null, textureBack: null,
areaDummy: null
};
}
return window._adminThreeEngine;
},

getRaw(obj) {
if (!obj) return obj;
if (typeof Alpine !== 'undefined' && Alpine.raw) return Alpine.raw(obj);
if (obj.__v_raw) return obj.__v_raw;
return obj;
},

reinit3D() {
let eng = this.getEngine();
if (eng.renderer) {
eng.renderer.dispose();
if(eng.renderer.domElement && eng.renderer.domElement.parentNode) {
eng.renderer.domElement.parentNode.innerHTML = '';
}
eng.renderer = null;
}
if (eng.tControls) {
eng.tControls.detach();
}
this.isInitialized = false;

if (this.modelPath) {
this.showDrawingBoard = false;
setTimeout(() => this.init3D(), 50);
} else {
this.showDrawingBoard = true;
}
},

init() {
if (!this.configSettings) this.configSettings = {};

if (typeof this.configSettings.area_shape === 'undefined') this.configSettings.area_shape = 'rect';
if (typeof this.configSettings.area_left === 'undefined') this.configSettings.area_left = 10;
if (typeof this.configSettings.area_top === 'undefined') this.configSettings.area_top = 10;
if (typeof this.configSettings.area_width === 'undefined') this.configSettings.area_width = 100;
if (typeof this.configSettings.area_height === 'undefined') this.configSettings.area_height = 64.7;

if (typeof this.configSettings.mirror_polygon === 'undefined') this.configSettings.mirror_polygon = true;

if (typeof this.configSettings.back_engraving_scale === 'undefined') this.configSettings.back_engraving_scale = 100;
if (typeof this.configSettings.back_engraving_pos_x === 'undefined') this.configSettings.back_engraving_pos_x = 0;
if (typeof this.configSettings.back_engraving_pos_y === 'undefined') this.configSettings.back_engraving_pos_y = 0;
if (typeof this.configSettings.back_engraving_pos_z === 'undefined') this.configSettings.back_engraving_pos_z = 0;
if (typeof this.configSettings.back_engraving_rot_x === 'undefined') this.configSettings.back_engraving_rot_x = 0;
if (typeof this.configSettings.back_engraving_rot_y === 'undefined') this.configSettings.back_engraving_rot_y = 0;
if (typeof this.configSettings.back_engraving_rot_z === 'undefined') this.configSettings.back_engraving_rot_z = 0;

if (!this.configSettings.custom_points || this.configSettings.custom_points.length === 0) {
this.configSettings.custom_points = [{x:20,y:20}, {x:80,y:20}, {x:80,y:80}, {x:20,y:80}];
}

if (typeof this.configSettings.material_type === 'undefined') this.configSettings.material_type = 'glass';
if (typeof this.configSettings.overlay_type === 'undefined') this.configSettings.overlay_type = 'cylinder';

this.$watch('configSettings.overlay_type', () => {
if (this.isInitialized) this.updateOverlayGeometry();
});

this.$watch('configSettings.area_shape', () => {
if (this.isInitialized && this.configSettings.overlay_type === 'cylinder') {
this.updateOverlayGeometry();
}
});

this.$watch('activeSide', (val) => {
let eng = this.getEngine();
if (eng.camera && eng.controls) {
eng.camera.position.z *= -1;
eng.camera.position.x *= -1;
eng.controls.update();
}
this.applyModelTransforms();
this.updateTransformTarget();
});

this.$watch('configSettings', () => {
if (this.modelPath && this.isInitialized && !this.isRaycasting) {
this.updateTexture();
this.applyModelTransforms();
}
}, { deep: true });

this.$nextTick(() => {
if (this.modelPath) {
this.init3D();
} else {
this.showDrawingBoard = true;
}
});
},

init3D() {
const container = this.$refs.adminContainer3d;
if (!container) return;

let eng = this.getEngine();
eng.scene = new window.THREE.Scene();

// Zeige einen dunkelgrauen statt pechschwarzen Hintergrund, damit man Kanten besser sieht
if (!this.bgPath) {
eng.scene.background = new window.THREE.Color('#222222');
} else {
eng.scene.background = null;
}

eng.camera = new window.THREE.PerspectiveCamera(45, container.offsetWidth / container.offsetHeight, 0.1, 1000);
eng.scene.add(eng.camera);

eng.renderer = new window.THREE.WebGLRenderer({ antialias: true, alpha: true });
eng.renderer.setSize(container.offsetWidth, container.offsetHeight);
eng.renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));

container.innerHTML = '';
container.appendChild(eng.renderer.domElement);

// EXTREM STARKES LICHT SETUP, damit niemals etwas unsichtbar bleibt
const headLamp = new window.THREE.DirectionalLight(0xffffff, 4.0);
headLamp.position.set(0, 0, 1);
eng.camera.add(headLamp);

eng.scene.add(new window.THREE.AmbientLight(0xffffff, 3.0));
eng.scene.add(new window.THREE.HemisphereLight(0xffffff, 0x444444, 3.0));

const mainLight = new window.THREE.DirectionalLight(0xffffff, 4.0);
mainLight.position.set(10, 10, 10);
eng.scene.add(mainLight);

const backLight = new window.THREE.DirectionalLight(0xffffff, 3.0);
backLight.position.set(-10, 10, -10);
eng.scene.add(backLight);

eng.controls = new window.OrbitControls(eng.camera, eng.renderer.domElement);
eng.controls.enableDamping = true;
eng.controls.dampingFactor = 0.08;
eng.controls.enablePan = false;
eng.controls.maxPolarAngle = Math.PI / 1.6;

if(this.bgPath) {
const loader = new window.THREE.TextureLoader();
loader.load(this.bgPath, (t) => {
t.mapping = window.THREE.EquirectangularReflectionMapping;
eng.scene.environment = t;
eng.scene.background = t;
});
}

eng.areaDummy = new window.THREE.Group();
eng.scene.add(eng.areaDummy);

if (window.TransformControls) {
eng.tControls = new window.TransformControls(eng.camera, eng.renderer.domElement);

eng.tControls.addEventListener('dragging-changed', (event) => {
eng.controls.enabled = !event.value;
if (!event.value) {
if (eng.tControls.object === eng.modelContainer || eng.tControls.object === eng.texturePlaneFront || eng.tControls.object === eng.texturePlaneBack) {
this.syncTransformsToConfig();
} else if (eng.tControls.object === eng.areaDummy) {
this.applyDummyToArea(false);
}
} else {
if (eng.tControls.object === eng.areaDummy) {
eng.areaDummy.position.set(0,0,0);
eng.areaDummy.scale.set(1,1,1);
this._dragStartTop = parseFloat(this.configSettings.area_top || 0);
this._dragStartHeight = parseFloat(this.configSettings.area_height || 100);
this._dragStartLeft = parseFloat(this.configSettings.area_left || 0);
this._dragStartWidth = parseFloat(this.configSettings.area_width || 100);
}
}
});

eng.tControls.addEventListener('change', () => {
if (eng.tControls.object === eng.areaDummy && eng.tControls.dragging) {
this.applyDummyToArea(true);
}
});

let controlObj = eng.tControls.getHelper ? eng.tControls.getHelper() : eng.tControls;
if (typeof controlObj.isObject3D === 'undefined') {
controlObj.isObject3D = true;
}
eng.scene.add(this.getRaw(controlObj));
}

eng.textureCanvas = document.createElement('canvas');
eng.textureCanvas.width = 2048;
eng.textureCanvas.height = 2048;
eng.textureCtx = eng.textureCanvas.getContext('2d');

eng.textureFront = new window.THREE.CanvasTexture(eng.textureCanvas);
eng.textureFront.flipY = true;

eng.textureBack = new window.THREE.CanvasTexture(eng.textureCanvas);
eng.textureBack.flipY = true;
eng.textureBack.wrapS = window.THREE.RepeatWrapping;
eng.textureBack.repeat.x = -1;
eng.textureBack.offset.x = 1;

const loader = new window.GLTFLoader();
loader.load(this.modelPath, (gltf) => {
eng.model = gltf.scene;
const box = new window.THREE.Box3().setFromObject(eng.model);
const size = box.getSize(new window.THREE.Vector3());
const center = box.getCenter(new window.THREE.Vector3());

eng.model.position.sub(center);

eng.modelContainer = new window.THREE.Group();
eng.modelContainer.add(this.getRaw(eng.model));

const maxDim = Math.max(size.x, size.y, size.z);
const fov = eng.camera.fov * (Math.PI / 180);
let cameraZ = Math.abs(maxDim / 2 / Math.tan(fov / 2));

eng.controls.minDistance = maxDim * 0.7;
eng.controls.maxDistance = maxDim * 3.0;

eng.camera.position.set(0, maxDim * 0.2, cameraZ * 1.5);
eng.controls.target.set(0, 0, 0);
eng.camera.updateProjectionMatrix();

eng.scene.add(this.getRaw(eng.modelContainer));

this.isInitialized = true;
this.applyMaterial();
this.updateOverlayGeometry();

const animate = () => {
if (!this.modelPath || !eng.renderer) return;
requestAnimationFrame(animate);
if (eng.controls) eng.controls.update();

if (eng.texturePlaneFront && eng.texturePlaneFront.material) {
eng.texturePlaneFront.material.opacity = 0.6 + Math.sin(Date.now() * 0.004) * 0.35;
}
if (eng.texturePlaneBack && eng.texturePlaneBack.material) {
eng.texturePlaneBack.material.opacity = 0.6 + Math.sin(Date.now() * 0.004) * 0.35;
}

if (eng.scene && eng.camera) eng.renderer.render(eng.scene, eng.camera);
};
animate();
});
},

applyDummyToArea(isDragging) {
let eng = this.getEngine();
if (!eng.areaDummy) return;

let dy = eng.areaDummy.position.y;
let dx = eng.areaDummy.position.x;
let sy = eng.areaDummy.scale.y;
let sx = eng.areaDummy.scale.x;

const box = new window.THREE.Box3().setFromObject(eng.model);
const sizeY = box.getSize(new window.THREE.Vector3()).y;
const sizeX = box.getSize(new window.THREE.Vector3()).x;

let deltaTop = -(dy / sizeY) * 100;
let deltaLeft = (dx / sizeX) * 100;

let newHeight = this._dragStartHeight * sy;
let newWidth = this._dragStartWidth * sx;

let newTop = this._dragStartTop + deltaTop;
let newLeft = this._dragStartLeft + deltaLeft;

newHeight = Math.max(1, Math.min(100, newHeight));
newTop = Math.max(0, Math.min(100 - newHeight, newTop));

newWidth = Math.max(1, Math.min(100, newWidth));
newLeft = Math.max(0, Math.min(100 - newWidth, newLeft));

this.configSettings.area_top = parseFloat(newTop.toFixed(2));
this.configSettings.area_height = parseFloat(newHeight.toFixed(2));
this.configSettings.area_left = parseFloat(newLeft.toFixed(2));
this.configSettings.area_width = parseFloat(newWidth.toFixed(2));

if (!isDragging) {
eng.areaDummy.position.set(0,0,0);
eng.areaDummy.scale.set(1,1,1);
}

this.updateTexture();
},

updateOverlayGeometry() {
let eng = this.getEngine();
if (!eng.modelContainer || !eng.textureFront || !eng.model) return;

const isCylinder = this.configSettings.overlay_type === 'cylinder';

if (isCylinder) {
this.isRaycasting = true;
}

const buildGeometryLogic = () => {
let wasAttachedFront = false;
let wasAttachedBack = false;

if (eng.tControls) {
if (eng.tControls.object === eng.texturePlaneFront) wasAttachedFront = true;
if (eng.tControls.object === eng.texturePlaneBack) wasAttachedBack = true;
eng.tControls.detach();
}

if (eng.texturePlaneFront) {
if(eng.texturePlaneFront.geometry) eng.texturePlaneFront.geometry.dispose();
eng.modelContainer.remove(this.getRaw(eng.texturePlaneFront));
}
if (eng.texturePlaneBack) {
if(eng.texturePlaneBack.geometry) eng.texturePlaneBack.geometry.dispose();
eng.modelContainer.remove(this.getRaw(eng.texturePlaneBack));
}

const box = new window.THREE.Box3().setFromObject(eng.model);
const size = box.getSize(new window.THREE.Vector3());

let geo;
let baseZ = 0;

if (isCylinder) {
geo = new window.THREE.CylinderGeometry(1, 1, size.y, 32, 16, true);
const pos = geo.attributes.position;
const raycaster = new window.THREE.Raycaster();
eng.model.updateMatrixWorld(true);

for (let i = 0; i < pos.count; i++) {
let py = pos.getY(i);
let dir = new window.THREE.Vector3(pos.getX(i), 0, pos.getZ(i)).normalize();

let rayOrigin = new window.THREE.Vector3(dir.x * 50, py, dir.z * 50);
let rayDir = dir.clone().negate();
raycaster.set(rayOrigin, rayDir);

let intersects = raycaster.intersectObject(eng.model, true);

if (intersects.length > 0) {
let hit = intersects[0].point;
let dist = Math.sqrt(hit.x * hit.x + hit.z * hit.z);
let finalRadius = dist + 0.001;
pos.setX(i, dir.x * finalRadius);
pos.setZ(i, dir.z * finalRadius);
} else {
pos.setX(i, dir.x * (size.x / 2 + 0.001));
pos.setZ(i, dir.z * (size.z / 2 + 0.001));
}
}
geo.computeVertexNormals();
baseZ = 0;
} else {
const maxPlaneSize = Math.max(size.x, size.y, size.z) * 1.5;
geo = new window.THREE.PlaneGeometry(maxPlaneSize, maxPlaneSize);
baseZ = (size.z / 2) + 0.001;
}

const matFront = new window.THREE.MeshBasicMaterial({ map: eng.textureFront, transparent: true, depthWrite: false, side: window.THREE.DoubleSide });
eng.texturePlaneFront = new window.THREE.Mesh(geo, matFront);
eng.texturePlaneFront.userData.baseZ = baseZ;
eng.modelContainer.add(this.getRaw(eng.texturePlaneFront));

if (this.configSettings.has_back_side) {
const matBack = new window.THREE.MeshBasicMaterial({ map: eng.textureBack, transparent: true, depthWrite: false, side: window.THREE.DoubleSide });
eng.texturePlaneBack = new window.THREE.Mesh(geo, matBack);
eng.texturePlaneBack.userData.baseZ = baseZ;
eng.modelContainer.add(this.getRaw(eng.texturePlaneBack));
}

this.updateTexture();
this.applyModelTransforms();

if (eng.tControls) {
if (wasAttachedFront) eng.tControls.attach(this.getRaw(eng.texturePlaneFront));
else if (wasAttachedBack && eng.texturePlaneBack) eng.tControls.attach(this.getRaw(eng.texturePlaneBack));
else this.updateTransformTarget();
}

if (isCylinder) {
this.isRaycasting = false;
}
};

if (isCylinder) {
setTimeout(buildGeometryLogic, 100);
} else {
buildGeometryLogic();
}
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
} else if (obj === eng.texturePlaneFront || obj === eng.texturePlaneBack) {
const prefix = obj === eng.texturePlaneBack ? 'back_engraving_' : 'engraving_';
c[prefix + 'pos_x'] = parseFloat((obj.position.x * 10).toFixed(2));
c[prefix + 'pos_y'] = parseFloat((obj.position.y * 10).toFixed(2));
c[prefix + 'pos_z'] = parseFloat(((obj.position.z - obj.userData.baseZ) * 10).toFixed(2));
c[prefix + 'rot_x'] = parseFloat((obj.rotation.x * (180/Math.PI)).toFixed(2));
c[prefix + 'rot_y'] = parseFloat((obj.rotation.y * (180/Math.PI)).toFixed(2));
c[prefix + 'rot_z'] = parseFloat((obj.rotation.z * (180/Math.PI)).toFixed(2));
c[prefix + 'scale'] = parseFloat((obj.scale.x * 100).toFixed(2));
}
},

updateTransformTarget() {
let eng = this.getEngine();
if(!eng.tControls) return;
eng.tControls.setMode(this.transformMode);

if (this.transformTarget === 'model' && eng.modelContainer) {
eng.tControls.attach(this.getRaw(eng.modelContainer));
} else if (this.transformTarget === 'overlay') {
if (this.activeSide === 'back' && eng.texturePlaneBack) {
eng.tControls.attach(this.getRaw(eng.texturePlaneBack));
} else if (eng.texturePlaneFront) {
eng.tControls.attach(this.getRaw(eng.texturePlaneFront));
} else {
eng.tControls.detach();
}
} else if (this.transformTarget === 'area' && eng.areaDummy) {
eng.areaDummy.position.set(0,0,0);
eng.areaDummy.scale.set(1,1,1);
eng.tControls.attach(this.getRaw(eng.areaDummy));
} else {
eng.tControls.detach();
}
},

applyMaterial() {
let eng = this.getEngine();
if (!eng.model || !this.configSettings) return;

const matType = this.configSettings.material_type || 'glass';
const hasEnv = (this.bgPath && this.bgPath.trim() !== '');

eng.model.traverse((child) => {
if (child.isMesh && child.material) {
const oldMap = child.material.map || null;

// Zwinge Modelle ohne Textur dazu, ein sauberes Grau/Weiß zu haben, damit sie nicht schwarz bleiben
const baseColor = oldMap ? 0xffffff : 0xcccccc;

if (matType === 'glass') {
child.material = new window.THREE.MeshPhysicalMaterial({ map: oldMap, color: baseColor, metalness: hasEnv ? 0.3 : 0.0, roughness: hasEnv ? 0.05 : 0.1, transparent: true, opacity: 0.80, depthWrite: false, side: window.THREE.FrontSide });
} else if (matType === 'wood') {
child.material = new window.THREE.MeshStandardMaterial({ map: oldMap, color: baseColor, roughness: 0.9, metalness: 0.0 });
} else if (matType === 'metal') {
child.material = new window.THREE.MeshStandardMaterial({ map: oldMap, color: baseColor, roughness: hasEnv ? 0.2 : 0.4, metalness: hasEnv ? 0.9 : 0.1 });
} else {
child.material = new window.THREE.MeshStandardMaterial({ map: oldMap, color: baseColor, roughness: 0.5, metalness: 0.0 });
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

const posX = (c.model_pos_x || 0) * 0.1;
const posY = (c.model_pos_y || 0) * 0.1;
const posZ = (c.model_pos_z || 0) * 0.1;

eng.modelContainer.position.set(posX, posY, posZ);

if (eng.controls) {
eng.controls.target.set(posX, posY, posZ);
eng.controls.update();
}

eng.modelContainer.rotation.set(
(c.model_rot_x || 0) * (Math.PI / 180),
(c.model_rot_y || 0) * (Math.PI / 180),
(c.model_rot_z || 0) * (Math.PI / 180)
);

if(eng.texturePlaneFront) {
const s_front = (c.engraving_scale || 100) / 100;
eng.texturePlaneFront.scale.set(s_front, s_front, s_front);
eng.texturePlaneFront.position.set(
(c.engraving_pos_x || 0) * 0.1,
(c.engraving_pos_y || 0) * 0.1,
(eng.texturePlaneFront.userData.baseZ || 0) + ((c.engraving_pos_z || 0) * 0.1)
);
eng.texturePlaneFront.rotation.set(
(c.engraving_rot_x || 0) * (Math.PI / 180),
(c.engraving_rot_y || 0) * (Math.PI / 180),
(c.engraving_rot_z || 0) * (Math.PI / 180)
);
}

if(eng.texturePlaneBack && c.has_back_side) {
const s_back = (c.back_engraving_scale || 100) / 100;
eng.texturePlaneBack.scale.set(s_back, s_back, s_back);
eng.texturePlaneBack.position.set(
(c.back_engraving_pos_x || 0) * 0.1,
(c.back_engraving_pos_y || 0) * 0.1,
(eng.texturePlaneBack.userData.baseZ || 0) + ((c.back_engraving_pos_z || 0) * 0.1)
);
eng.texturePlaneBack.rotation.set(
(c.back_engraving_rot_x || 0) * (Math.PI / 180),
(c.back_engraving_rot_y || 0) * (Math.PI / 180),
(c.back_engraving_rot_z || 0) * (Math.PI / 180)
);
}
},

updateTexture() {
let eng = this.getEngine();
if(!eng.textureCtx || !this.configSettings) return;

const maxC = 2048;
eng.textureCtx.clearRect(0, 0, maxC, maxC);

eng.textureCtx.fillStyle = 'rgba(16, 185, 129, 0.2)';
eng.textureCtx.strokeStyle = '#10b981';

const lw = 20;
eng.textureCtx.lineWidth = lw;

const shape = this.configSettings.area_shape || 'rect';
let isFullWrap = (this.configSettings.overlay_type === 'cylinder' && this.configSettings.area_width >= 99);

let top = parseFloat(this.configSettings.area_top || 0);
let height = parseFloat(this.configSettings.area_height || 100);
let left = parseFloat(this.configSettings.area_left || 0);
let width = parseFloat(this.configSettings.area_width || 100);

if (top + height > 100) { top = 100 - height; this.configSettings.area_top = parseFloat(top.toFixed(2)); }
if (left + width > 100) { left = 100 - width; this.configSettings.area_left = parseFloat(left.toFixed(2)); }

if (shape !== 'custom') {
let x = (left / 100) * maxC; let y = (top / 100) * maxC; let w = (width / 100) * maxC; let h = (height / 100) * maxC;

if (!isFullWrap) { if (x < lw/2) x = lw/2; if (x + w > maxC - lw/2) w = maxC - lw/2 - x; }
if (y < lw/2) y = lw/2; if (y + h > maxC - lw/2) h = maxC - lw/2 - y;

if (shape === 'rect') {
eng.textureCtx.fillRect(x, y, w, h);
eng.textureCtx.beginPath();
if (isFullWrap) {
eng.textureCtx.moveTo(x, y); eng.textureCtx.lineTo(x + w, y); eng.textureCtx.moveTo(x, y + h); eng.textureCtx.lineTo(x + w, y + h);
} else {
eng.textureCtx.rect(x, y, w, h);
}
eng.textureCtx.stroke();
} else if (shape === 'circle') {
eng.textureCtx.beginPath(); eng.textureCtx.ellipse(x + w/2, y + h/2, w/2, h/2, 0, 0, Math.PI * 2); eng.textureCtx.fill(); eng.textureCtx.stroke();
}
} else {
let pts = this.configSettings.custom_points;
if(pts && pts.length > 0) {
eng.textureCtx.beginPath();
pts.forEach((p, i) => {
let px = ((p.x || 0) / 100) * maxC; let py = ((p.y || 0) / 100) * maxC;
if (!isFullWrap) { if (px < lw/2) px = lw/2; if (px > maxC - lw/2) px = maxC - lw/2; }
if (py < lw/2) py = lw/2; if (py > maxC - lw/2) py = maxC - lw/2;
if(i === 0) eng.textureCtx.moveTo(px, py); else eng.textureCtx.lineTo(px, py);
});
eng.textureCtx.closePath(); eng.textureCtx.fill(); eng.textureCtx.stroke();
}
}

if(eng.textureFront) eng.textureFront.needsUpdate = true;
if(eng.textureBack) eng.textureBack.needsUpdate = true;
},

startDragPoint(idx, event) {
this.isDraggingPoint = idx;
if (this.configSettings.mirror_polygon) {
let pts = this.configSettings.custom_points;
let p = pts[idx];
this.isDraggingMirroredPoint = pts.findIndex((other, i) => i !== idx && Math.abs(other.x - (100 - p.x)) < 3 && Math.abs(other.y - p.y) < 3);
if (this.isDraggingMirroredPoint === -1) this.isDraggingMirroredPoint = null;
} else {
this.isDraggingMirroredPoint = null;
}
},

dragPoint(event) {
if(this.isDraggingPoint === null || this.configSettings.area_shape !== 'custom') return;

const rect = this.$refs.adminContainer2d.getBoundingClientRect();
if (!rect.width || !rect.height) return;

const newX = Math.max(0, Math.min(100, ((event.clientX - rect.left) / rect.width) * 100));
const newY = Math.max(0, Math.min(100, ((event.clientY - rect.top) / rect.height) * 100));

let pts = JSON.parse(JSON.stringify(this.configSettings.custom_points)).filter(p => p !== null);

if(pts[this.isDraggingPoint]) {
pts[this.isDraggingPoint].x = parseFloat(newX.toFixed(2));
pts[this.isDraggingPoint].y = parseFloat(newY.toFixed(2));

if (this.isDraggingMirroredPoint !== null && pts[this.isDraggingMirroredPoint]) {
pts[this.isDraggingMirroredPoint].x = parseFloat((100 - newX).toFixed(2));
pts[this.isDraggingMirroredPoint].y = parseFloat(newY.toFixed(2));
}

this.configSettings.custom_points = pts;
this.updateTexture();
}
},

stopDragPoint() {
this.isDraggingPoint = null;
this.isDraggingMirroredPoint = null;
},

addNewDefaultPoint() {
let pts = JSON.parse(JSON.stringify(this.configSettings.custom_points || [])).filter(p => p !== null);
if (this.configSettings.mirror_polygon) {
let halfIndex = Math.floor(pts.length / 2);
pts.splice(halfIndex, 0, {x: 80, y: 50}, {x: 20, y: 50});
} else {
pts.push({x: 50, y: 50});
}
this.configSettings.custom_points = pts;
this.updateTexture();
},

addPoint(e) {
if(this.configSettings.area_shape !== 'custom') return;
if(!this.showDrawingBoard) return;

const rect = this.$refs.adminContainer2d.getBoundingClientRect();
if (!rect.width || !rect.height) return;
if(e.target.closest('.point-handle')) return;

const x = parseFloat((((e.clientX - rect.left) / rect.width) * 100).toFixed(2));
const y = parseFloat((((e.clientY - rect.top) / rect.height) * 100).toFixed(2));

if (x < 0 || x > 100 || y < 0 || y > 100) return;
if (isNaN(x) || isNaN(y) || !isFinite(x) || !isFinite(y)) return;

let pts = JSON.parse(JSON.stringify(this.configSettings.custom_points || [])).filter(p => p !== null);

if (this.configSettings.mirror_polygon && Math.abs(x - 50) > 1) {
let mirrorX = parseFloat((100 - x).toFixed(2));
let halfIndex = Math.floor(pts.length / 2);
pts.splice(halfIndex, 0, {x: x, y: y}, {x: mirrorX, y: y});
} else {
pts.push({x: x, y: y});
}

this.configSettings.custom_points = pts;
this.updateTexture();
},

deletePoint(idx) {
let pts = this.configSettings.custom_points;
let p = pts[idx];

if (this.configSettings.mirror_polygon) {
let mirrorIdx = pts.findIndex((other, i) => i !== idx && Math.abs(other.x - (100 - p.x)) < 3 && Math.abs(other.y - p.y) < 3);
if (mirrorIdx > -1) {
if (mirrorIdx > idx) {
pts.splice(mirrorIdx, 1);
pts.splice(idx, 1);
} else {
pts.splice(idx, 1);
pts.splice(mirrorIdx, 1);
}
this.updateTexture();
return;
}
}

pts.splice(idx, 1);
this.updateTexture();
},

resetModel() {
this.configSettings.model_scale=100;
this.configSettings.model_pos_x=0;
this.configSettings.model_pos_y=0;
this.configSettings.model_pos_z=0;
this.configSettings.model_rot_x=0;
this.configSettings.model_rot_y=0;
this.configSettings.model_rot_z=0;
this.applyModelTransforms();
},

resetOverlay() {
const prefix = this.activeSide === 'back' ? 'back_engraving_' : 'engraving_';
this.configSettings[prefix + 'scale']=100;
this.configSettings[prefix + 'pos_x']=0;
this.configSettings[prefix + 'pos_y']=0;
this.configSettings[prefix + 'pos_z']=0;
this.configSettings[prefix + 'rot_x']=0;
this.configSettings[prefix + 'rot_y']=0;
this.configSettings[prefix + 'rot_z']=0;
this.updateOverlayGeometry();
this.applyModelTransforms();
}
};
})()"
@3d-model-updated.window="modelPath = $event.detail.url; reinit3D();"
@3d-bg-updated.window="bgPath = $event.detail.url;"
@2d-preview-updated.window="fallbackImg = $event.detail.url;"
