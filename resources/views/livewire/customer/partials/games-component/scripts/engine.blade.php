window.Match3DEngine = class Match3DEngine {
constructor(container, callbacks) {
this.container = container; this.callbacks = callbacks; this.audio = new window.ArcadeAudio();

// MOBIL OPTIMIERT
const isMobile = window.innerWidth < 768;
this.cellSize = isMobile ? 1.5 : 1.3;
this.baseCScale = isMobile ? 1.4 : 1.0;
this.rows = 8; this.cols = 8;

this.board = []; this.crystals = []; this.particles = []; this.tweens = []; this.lasers = [];
this.isProcessing = false; this.selectedMesh = null; this.shakeTime = 0;

this.initScene();
this.loadAssets();
this.setupInteractions();
this.animate();
}

resize() {
if (!this.container || !this.camera || !this.renderer) return;
const width = this.container.offsetWidth || 800;
const height = this.container.offsetHeight || 800;
const aspect = width / height;

this.camera.aspect = aspect;
this.camera.updateProjectionMatrix();
this.renderer.setSize(width, height);

const gridWidth = this.cols * this.cellSize;
const gridHeight = this.rows * this.cellSize;

const fovRad = this.camera.fov * (Math.PI / 180);
let cameraZ = (gridHeight + 1.0) / (2 * Math.tan(fovRad / 2));

if (aspect < 1) {
const hFovRad = 2 * Math.atan(Math.tan(fovRad / 2) * aspect);
const distW = (gridWidth + 0.5) / (2 * Math.tan(hFovRad / 2));
cameraZ = Math.max(cameraZ, distW);
}

this.camera.position.set(0, 0, cameraZ);
this.camera.lookAt(0, 0, 0);
}

initScene() {
this.scene = new THREE.Scene();
const width = this.container.offsetWidth || 800; const height = this.container.offsetHeight || 800;
this.camera = new THREE.PerspectiveCamera(45, width / height, 0.1, 100);
this.renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
this.renderer.setSize(width, height);
this.renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
this.renderer.setClearColor(0x000000, 0);

this.renderer.domElement.style.touchAction = 'none';
this.renderer.domElement.style.width = '100%';
this.renderer.domElement.style.height = '100%';
this.renderer.domElement.style.position = 'absolute';
this.renderer.domElement.style.top = '0';
this.renderer.domElement.style.left = '0';

this.container.appendChild(this.renderer.domElement);
this.scene.add(new THREE.AmbientLight(0xffffff, 1.5));
const dirLight = new THREE.DirectionalLight(0xffffff, 2.0); dirLight.position.set(5, 10, 10); this.scene.add(dirLight);

const resizeObserver = new ResizeObserver(() => this.resize());
resizeObserver.observe(this.container);
this.resize();
}

loadAssets() {
this.geometries = [
null,
new THREE.OctahedronGeometry(0.5, 0), // 1 Red
new THREE.IcosahedronGeometry(0.5, 0), // 2 Blue
new THREE.DodecahedronGeometry(0.4, 0), // 3 Green
new THREE.TetrahedronGeometry(0.5, 0), // 4 Yellow
new THREE.CylinderGeometry(0.35, 0.35, 0.8, 6), // 5 Purple
new THREE.TorusGeometry(0.35, 0.15, 8, 16), // 6 Cyan
new THREE.OctahedronGeometry(0.6, 1), // 7 Master
new THREE.SphereGeometry(0.45, 32, 32), // 8 Nova
new THREE.TorusKnotGeometry(0.3, 0.1, 64, 8), // 9 Time
new THREE.TorusKnotGeometry(0.35, 0.12, 64, 8), // 10 Phantom
new THREE.IcosahedronGeometry(0.6, 1), // 11 Vortex
new THREE.DodecahedronGeometry(0.5, 0) // 12 Lightning
];

this.colorsHex = [
null, 0xef4444, 0x3b82f6, 0x10b981, 0xeab308, 0xa855f7, 0x06b6d4,
0xffffff, 0x111111, 0xf472b6, 0xf97316, 0x1d4ed8, 0xfde047
];

this.materials = this.colorsHex.map((c, index) => {
if (index === 0) return null;
if (index === 7) return new THREE.MeshStandardMaterial({ color: 0xffffff, metalness: 0.5, roughness: 0.1, emissive: 0xffffff, emissiveIntensity: 0.6 });
if (index === 8) return new THREE.MeshStandardMaterial({ color: 0x111111, metalness: 0.8, roughness: 0.2, emissive: 0x6b21a8, emissiveIntensity: 0.8 });
if (index === 9) return new THREE.MeshStandardMaterial({ color: 0xf472b6, metalness: 0.3, roughness: 0.1, emissive: 0xf472b6, emissiveIntensity: 0.5 });
if (index === 10) return new THREE.MeshStandardMaterial({ color: 0xf97316, metalness: 0.5, roughness: 0.2, emissive: 0xf97316, emissiveIntensity: 0.7 });
if (index === 11) return new THREE.MeshStandardMaterial({ color: 0x1d4ed8, metalness: 0.8, roughness: 0.1, emissive: 0x1d4ed8, emissiveIntensity: 0.9 });
if (index === 12) return new THREE.MeshStandardMaterial({ color: 0xfde047, metalness: 0.1, roughness: 0.1, emissive: 0xfde047, emissiveIntensity: 1.0 });
return new THREE.MeshStandardMaterial({ color: c, metalness: 0.2, roughness: 0.1, emissive: c, emissiveIntensity: 0.4 });
});
this.highlightMat = new THREE.MeshBasicMaterial({ color: 0xffffff, wireframe: true, transparent: true, opacity: 0.8 });
}

start() {
this.isProcessing = true;
this.clearBoard();
this.fillBoard();

while(this.findMatches().length > 0) {
this.clearBoard();
this.fillBoard();
}
this.isProcessing = false;
}

clearBoard() {
this.crystals.forEach(m => this.scene.remove(m));
this.crystals = [];
this.board = Array.from({ length: this.rows }, () => Array(this.cols).fill(0));
}

getPos(r, c) { return { x: (c - this.cols / 2 + 0.5) * this.cellSize, y: -(r - this.rows / 2 + 0.5) * this.cellSize, z: 0 }; }

fillBoard() {
for (let r = 0; r < this.rows; r++) {
for (let c = 0; c < this.cols; c++) {
if (this.board[r][c] === 0) {
this.spawnCrystal(r, c);
}
}
}
}

spawnCrystal(r, c, dropFromTop = false) {
const rand = Math.random();
let typeIndex = Math.floor(Math.random() * 6) + 1; // 1-6 Normal

// Drop Rates für Special Steine
if (rand < 0.015) typeIndex = 8; // Nova
else if (rand < 0.030) typeIndex = 9; // Time
else if (rand < 0.045) typeIndex = 7; // Master
else if (rand < 0.055) typeIndex = 10; // Phantom
else if (rand < 0.065) typeIndex = 11; // Vortex
else if (rand < 0.075) typeIndex = 12; // Lightning

const mat = (typeIndex >= 7) ? this.materials[typeIndex].clone() : this.materials[typeIndex];
const mesh = new THREE.Mesh(this.geometries[typeIndex], mat);
mesh.rotation.set(Math.random() * Math.PI, Math.random() * Math.PI, 0);
mesh.scale.setScalar(this.baseCScale);

mesh.userData = { r, c, type: typeIndex, baseScale: this.baseCScale };
if(typeIndex === 10) mesh.userData.charges = 3;

const targetPos = this.getPos(r, c);
if (dropFromTop) { mesh.position.set(targetPos.x, targetPos.y + 12, targetPos.z); this.tween(mesh.position, targetPos, 400, 'easeOutBounce'); }
else { mesh.position.set(targetPos.x, targetPos.y, targetPos.z); }

this.scene.add(mesh); this.crystals.push(mesh); this.board[r][c] = mesh;
}

// ==========================================
// EXAKT DIE ALTE, FUNKTIONIERENDE LOGIK VOM STAGE-SERVER!
// ==========================================
setupInteractions() {
this.raycaster = new THREE.Raycaster();
this.mouse = new THREE.Vector2();
let startX = 0;
let startY = 0;
let isDragging = false;

const getEventCoords = (e) => {
if (e.changedTouches && e.changedTouches.length > 0) {
return { x: e.changedTouches[0].clientX, y: e.changedTouches[0].clientY };
}
return { x: e.clientX, y: e.clientY };
};

const onPointerDown = (event) => {
if (this.isProcessing || this.callbacks.getMoves() <= 0 || event.target !== this.renderer.domElement) return;

const rect = this.renderer.domElement.getBoundingClientRect();
const coords = getEventCoords(event);

this.mouse.x = ((coords.x - rect.left) / rect.width) * 2 - 1;
this.mouse.y = -((coords.y - rect.top) / rect.height) * 2 + 1;

this.raycaster.setFromCamera(this.mouse, this.camera);

// WICHTIG: Nur "this.crystals" checken und false (nicht rekursiv) setzen,
// um den weißen Auswahl-Rand restlos zu ignorieren!
const intersects = this.raycaster.intersectObjects(this.crystals, false);

if (intersects.length > 0) {
const clickedMesh = intersects[0].object;

if (!this.selectedMesh) {
// ERSTER KLICK (Stein markieren)
this.selectMesh(clickedMesh);
this.audio.playSwap();
startX = coords.x;
startY = coords.y;
isDragging = true;
} else {
// ZWEITER KLICK
if (this.selectedMesh === clickedMesh) {
this.deselectMesh();
isDragging = false;
return;
}
const r1 = this.selectedMesh.userData.r, c1 = this.selectedMesh.userData.c;
const r2 = clickedMesh.userData.r, c2 = clickedMesh.userData.c;

const isAdj = (Math.abs(r1 - r2) + Math.abs(c1 - c2)) === 1;

// TELEPORT LOGIK FÜR ALLE SUPERSTEINE
const type1 = this.selectedMesh.userData.type;
const type2 = clickedMesh.userData.type;
const specialTypes = [8, 10, 11, 12]; // Nova, Phantom, Vortex, Lightning
const isTeleport = specialTypes.includes(type1) || specialTypes.includes(type2);

if (isAdj || isTeleport) {
this.executeSwap(this.selectedMesh, clickedMesh, isTeleport);
isDragging = false;
} else {
// Falscher Stein -> neuen markieren
this.deselectMesh();
this.selectMesh(clickedMesh);
this.audio.playSwap();
startX = coords.x;
startY = coords.y;
isDragging = true;
}
}
} else {
this.deselectMesh();
isDragging = false;
}
};

const onPointerMove = (event) => {
if (!isDragging || !this.selectedMesh || this.isProcessing) return;

const coords = getEventCoords(event);
const diffX = coords.x - startX;
const diffY = coords.y - startY;
const threshold = window.innerWidth < 600 ? 15 : 30; // Etwas feinfühliger auf dem Handy

if (Math.abs(diffX) > threshold || Math.abs(diffY) > threshold) {
isDragging = false; // Wir haben gewischt!

let targetR = this.selectedMesh.userData.r;
let targetC = this.selectedMesh.userData.c;

// Richtung bestimmen
if (Math.abs(diffX) > Math.abs(diffY)) {
targetC += diffX > 0 ? 1 : -1;
} else {
targetR += diffY > 0 ? 1 : -1; // Y geht nach unten ins Positive!
}

if (targetR >= 0 && targetR < this.rows && targetC >= 0 && targetC < this.cols) {
const targetMesh = this.board[targetR][targetC];
if (targetMesh && targetMesh !== 0) {
const type1 = this.selectedMesh.userData.type;
const type2 = targetMesh.userData.type;
const specialTypes = [8, 10, 11, 12];
const isTeleport = specialTypes.includes(type1) || specialTypes.includes(type2);

this.executeSwap(this.selectedMesh, targetMesh, isTeleport);
} else {
this.deselectMesh();
}
} else {
this.deselectMesh();
}
}
};

const onPointerUp = () => { isDragging = false; };

// Pointer Events statt Mouse & Touch bündeln doppelte Events (klickt nicht doppelt auf Mobile!)
this.container.addEventListener('pointerdown', onPointerDown);
this.container.addEventListener('pointermove', onPointerMove);
this.container.addEventListener('pointerleave', onPointerUp);
this.container.addEventListener('pointercancel', onPointerUp);
window.addEventListener('pointerup', onPointerUp);
}

selectMesh(mesh) {
this.selectedMesh = mesh; this.tween(mesh.scale, {x: mesh.userData.baseScale * 1.3, y: mesh.userData.baseScale * 1.3, z: mesh.userData.baseScale * 1.3}, 150);
const outline = new THREE.Mesh(mesh.geometry, this.highlightMat); outline.scale.set(1.1, 1.1, 1.1);
mesh.add(outline); mesh.userData.outline = outline;
}

deselectMesh() {
if(this.selectedMesh) {
this.tween(this.selectedMesh.scale, {x: this.selectedMesh.userData.baseScale, y: this.selectedMesh.userData.baseScale, z: this.selectedMesh.userData.baseScale}, 150);
if(this.selectedMesh.userData.outline) this.selectedMesh.remove(this.selectedMesh.userData.outline);
this.selectedMesh = null;
}
}

async executeSwap(mesh1, mesh2, isTeleport = false) {
this.isProcessing = true;
this.deselectMesh();
this.audio.playSwap();

const r1 = mesh1.userData.r, c1 = mesh1.userData.c;
const r2 = mesh2.userData.r, c2 = mesh2.userData.c;

mesh1.userData.r = r2; mesh1.userData.c = c2;
mesh2.userData.r = r1; mesh2.userData.c = c1;
this.board[r1][c1] = mesh2; this.board[r2][c2] = mesh1;

if (isTeleport) {
this.tween(mesh1.position, this.getPos(r2, c2), 400, 'easeOutBounce');
this.tween(mesh2.position, this.getPos(r1, c1), 400, 'easeOutBounce');
await this.sleep(400);
} else {
this.tween(mesh1.position, this.getPos(r2, c2), 250);
this.tween(mesh2.position, this.getPos(r1, c1), 250);
await this.sleep(250);
}

// =========================
// SUPERKRÄFTE ABHANDELN
// =========================
let costMove = true;
let phantomMesh = null;

if (mesh1.userData.type === 10) phantomMesh = mesh1;
else if (mesh2.userData.type === 10) phantomMesh = mesh2;

if (phantomMesh) {
costMove = false; // Kostet keinen Zug!
phantomMesh.userData.charges -= 1;
this.showFloatingText(phantomMesh.position, phantomMesh.userData.charges + " Leben!", '#f97316', document.getElementById('floating-scores-layer'), true);
}

if (costMove) {
this.callbacks.onMoveUsed();
}

// Farb-Vortex
let voidTargetColor = null, voidPos = null;
if (mesh1.userData.type === 11 && mesh2.userData.type <= 6) { voidTargetColor = mesh2.material.color; voidPos = mesh1.position; }
else if (mesh2.userData.type === 11 && mesh1.userData.type <= 6) { voidTargetColor = mesh1.material.color; voidPos = mesh2.position; }

// Kettenblitz
let lightningPos = null;
if (mesh1.userData.type === 12) lightningPos = mesh1.position;
else if (mesh2.userData.type === 12) lightningPos = mesh2.position;

// Nova
let triggersNova1 = mesh1.userData.type === 8;
let triggersNova2 = mesh2.userData.type === 8;

if (voidTargetColor) await this.triggerVoidExplosion(voidPos, voidTargetColor);
if (lightningPos) await this.triggerLightning(lightningPos);
if (triggersNova1) await this.triggerNovaExplosion(mesh1.userData.r, mesh1.userData.c);
if (triggersNova2) await this.triggerNovaExplosion(mesh2.userData.r, mesh2.userData.c);

// Zerstöre Phantom wenn Leer
if (phantomMesh && phantomMesh.userData.charges <= 0) {
this.createExplosion(phantomMesh.position, phantomMesh.material.color);
this.board[phantomMesh.userData.r][phantomMesh.userData.c] = 0;
this.scene.remove(phantomMesh);
this.crystals = this.crystals.filter(cr => cr !== phantomMesh);
}

if (voidTargetColor || lightningPos || triggersNova1 || triggersNova2 || (phantomMesh && phantomMesh.userData.charges <= 0)) {
await this.sleep(200); this.applyGravity(); await this.sleep(400);
}

await this.processMatches();
this.isProcessing = false;
}

async triggerNovaExplosion(r, c) {
this.audio.playLaser(); this.shakeTime = 40; let destroyedCount = 0;
for (let rr = r - 1; rr <= r + 1; rr++) {
for (let cc = c - 1; cc <= c + 1; cc++) {
if (rr >= 0 && rr < this.rows && cc >= 0 && cc < this.cols) {
let mesh = this.board[rr][cc];
if (mesh && mesh !== 0) {
this.createExplosion(mesh.position, mesh.material.color); this.scene.remove(mesh);
this.board[rr][cc] = 0; this.crystals = this.crystals.filter(cr => cr !== mesh); destroyedCount++;
}
}
}
}
this.callbacks.onScore(destroyedCount, 3, new THREE.Vector3(this.getPos(r, c).x, this.getPos(r, c).y, 0), '#a855f7');
}

async triggerVoidExplosion(pos, targetColorHex) {
this.audio.playLaser(); this.shakeTime = 40; let destroyedCount = 0;
for (let r = 0; r < this.rows; r++) {
for (let c = 0; c < this.cols; c++) {
let mesh = this.board[r][c];
if (mesh && mesh !== 0 && (mesh.material.color.getHex() === targetColorHex.getHex() || mesh.userData.type === 11)) {
this.createLaser(pos, mesh.position, 0x1d4ed8);
setTimeout(() => {
if(this.board[r][c] === mesh) {
this.createExplosion(mesh.position, mesh.material.color);
this.scene.remove(mesh); this.board[r][c] = 0;
this.crystals = this.crystals.filter(cr => cr !== mesh);
}
}, 150);
destroyedCount++;
}
}
}
await this.sleep(200);
this.callbacks.onScore(destroyedCount, 4, pos.clone(), '#1d4ed8');
}

async triggerLightning(pos) {
this.audio.playLaser(); this.shakeTime = 50; let destroyedCount = 0;
let targets = [];
let pool = this.crystals.filter(c => c.userData.type <= 6);

for(let i=0; i<6; i++) {
if(pool.length > 0) {
let idx = Math.floor(Math.random() * pool.length);
targets.push(pool[idx]); pool.splice(idx, 1);
}
}
let selfBlock = this.crystals.find(c => c.position.equals(pos));
if(selfBlock) targets.push(selfBlock);

targets.forEach(mesh => {
this.createLaser(pos, mesh.position, 0xfde047);
setTimeout(() => {
const r = mesh.userData.r; const c = mesh.userData.c;
if(this.board[r][c] === mesh) {
this.createExplosion(mesh.position, mesh.material.color);
this.scene.remove(mesh); this.board[r][c] = 0;
this.crystals = this.crystals.filter(cr => cr !== mesh);
}
}, 200);
destroyedCount++;
});
await this.sleep(250);
this.callbacks.onScore(destroyedCount, 3, pos.clone(), '#fde047');
}

findMatches() {
let matched = new Set();
for (let r = 0; r < this.rows; r++) {
for (let c = 0; c < this.cols - 2; c++) {
let m1 = this.board[r][c], m2 = this.board[r][c+1], m3 = this.board[r][c+2];
if (m1 && m2 && m3 && m1.userData.type <= 7 && m1.userData.type === m2.userData.type && m1.userData.type === m3.userData.type) { matched.add(m1); matched.add(m2); matched.add(m3); }
}
}
for (let c = 0; c < this.cols; c++) {
for (let r = 0; r < this.rows - 2; r++) {
let m1 = this.board[r][c], m2 = this.board[r+1][c], m3 = this.board[r+2][c];
if (m1 && m2 && m3 && m1.userData.type <= 7 && m1.userData.type === m2.userData.type && m1.userData.type === m3.userData.type) { matched.add(m1); matched.add(m2); matched.add(m3); }
}
}
return Array.from(matched);
}

async processMatches(combo = 1) {
let matches = this.findMatches(); if (matches.length === 0) return;
this.audio.playMatch();

let masterDiamonds = matches.filter(m => m.userData.type === 7);
if(masterDiamonds.length > 0) await this.triggerMasterDiamond(masterDiamonds, matches);

let timeCrystals = matches.filter(m => m.userData.type === 9);
if(timeCrystals.length > 0) this.callbacks.onAddMoves(timeCrystals.length * 2, matches[1].position.clone());

let colorHex = matches[0].userData.type === 7 ? "ffffff" : matches[0].material.color.getHexString();
this.callbacks.onScore(matches.length, combo, matches[1].position.clone(), '#' + colorHex);

matches.forEach(mesh => {
if (this.board[mesh.userData.r][mesh.userData.c] !== 0) {
this.createExplosion(mesh.position, mesh.material.color); this.board[mesh.userData.r][mesh.userData.c] = 0;
this.scene.remove(mesh); this.crystals = this.crystals.filter(c => c !== mesh);
}
});
await this.sleep(150); this.applyGravity(); await this.sleep(400); await this.processMatches(combo + 1);
}

async triggerMasterDiamond(masterDiamonds, currentMatches) {
this.audio.playLaser(); this.shakeTime = 30;
masterDiamonds.forEach(md => {
const r = md.userData.r, c = md.userData.c;
for(let i = 0; i < this.cols; i++) {
if(this.board[r][i] && this.board[r][i] !== 0) { currentMatches.push(this.board[r][i]); this.createLaser(md.position, this.board[r][i].position, 0xffffff); }
}
for(let i = 0; i < this.rows; i++) {
if(this.board[i][c] && this.board[i][c] !== 0) { currentMatches.push(this.board[i][c]); this.createLaser(md.position, this.board[i][c].position, 0xffffff); }
}
});
await this.sleep(200);
}

createLaser(startPos, endPos, colorHex) {
const material = new THREE.LineBasicMaterial({ color: colorHex, transparent: true, opacity: 0.8, linewidth: 2 });
const points = [startPos.clone(), endPos.clone()];
const geometry = new THREE.BufferGeometry().setFromPoints(points);
this.scene.add(new THREE.Line(geometry, material)); this.lasers.push({ mesh: new THREE.Line(geometry, material), life: 1.0 });
}

createExplosion(position, color) {
const mat = new THREE.MeshBasicMaterial({ color: color });
for (let i = 0; i < 10; i++) {
const p = new THREE.Mesh(new THREE.TetrahedronGeometry(0.2), mat); p.position.copy(position);
const v = new THREE.Vector3((Math.random() - 0.5) * 0.4, (Math.random() - 0.5) * 0.4, (Math.random() - 0.5) * 0.4 + 0.1);
this.scene.add(p); this.particles.push({ mesh: p, velocity: v, life: 1.0 });
}
}

applyGravity() {
for (let c = 0; c < this.cols; c++) {
let writePos = this.rows - 1, missing = 0;
for (let r = this.rows - 1; r >= 0; r--) {
let mesh = this.board[r][c];
if (mesh !== 0) {
if (writePos !== r) {
this.board[writePos][c] = mesh; this.board[r][c] = 0; mesh.userData.r = writePos;
this.tween(mesh.position, this.getPos(writePos, c), 300, 'easeOutBounce');
}
writePos--;
} else { missing++; }
}
for (let i = 0; i < missing; i++) { this.spawnCrystal(writePos, c, true); writePos--; }
}
}

showFloatingText(pos3D, text, colorHex, layerElement, isBonus = false) {
if (!layerElement) return;
const vector = pos3D.clone(); vector.project(this.camera);
const x = (vector.x * .5 + .5) * this.container.offsetWidth; const y = (vector.y * -.5 + .5) * this.container.offsetHeight;
const el = document.createElement('div');
el.className = isBonus ? 'floating-score floating-bonus' : 'floating-score'; el.innerText = text; el.style.left = `${x}px`; el.style.top = `${y}px`; if(!isBonus) el.style.color = colorHex;
layerElement.appendChild(el);
setTimeout(() => { if (el.parentNode === layerElement) layerElement.removeChild(el); }, 1500);
}

tween(obj, target, duration, easing = 'linear') { this.tweens.push({ obj, start: { x: obj.x, y: obj.y, z: obj.z }, target, duration, startTime: Date.now(), easing }); }

updateTweens() {
const now = Date.now();
for (let i = this.tweens.length - 1; i >= 0; i--) {
const t = this.tweens[i]; let progress = (now - t.startTime) / t.duration;
if (progress >= 1) {
if (t.target.x !== undefined) t.obj.x = t.target.x; if (t.target.y !== undefined) t.obj.y = t.target.y; if (t.target.z !== undefined) t.obj.z = t.target.z;
this.tweens.splice(i, 1);
} else {
let e = progress;
if(t.easing === 'easeOutBounce') {
const n1 = 7.5625, d1 = 2.75;
if (progress < 1 / d1) { e = n1 * progress * progress; } else if (progress < 2 / d1) { e = n1 * (progress -= 1.5 / d1) * progress + 0.75; } else if (progress < 2.5 / d1) { e = n1 * (progress -= 2.25 / d1) * progress + 0.9375; } else { e = n1 * (progress -= 2.625 / d1) * progress + 0.984375; }
}
if (t.target.x !== undefined) t.obj.x = t.start.x + (t.target.x - t.start.x) * e; if (t.target.y !== undefined) t.obj.y = t.start.y + (t.target.y - t.start.y) * e; if (t.target.z !== undefined) t.obj.z = t.start.z + (t.target.z - t.start.z) * e;
}
}
}

updateParticles() {
for (let i = this.particles.length - 1; i >= 0; i--) {
let p = this.particles[i]; p.mesh.position.add(p.velocity); p.mesh.rotation.x += 0.1; p.mesh.rotation.y += 0.1; p.velocity.y -= 0.01; p.life -= 0.03; p.mesh.scale.setScalar(Math.max(0, p.life));
if (p.life <= 0) { this.scene.remove(p.mesh); this.particles.splice(i, 1); }
}
for (let i = this.lasers.length - 1; i >= 0; i--) {
let l = this.lasers[i]; l.life -= 0.1; l.mesh.material.opacity = Math.max(0, l.life);
if (l.life <= 0) { this.scene.remove(l.mesh); this.lasers.splice(i, 1); }
}
}

animate() {
requestAnimationFrame(() => this.animate());
this.crystals.forEach(c => {
c.rotation.x += 0.005; c.rotation.y += 0.01;
if(c.userData.type === 8 && c.material.emissiveIntensity !== undefined) c.material.emissiveIntensity = 0.5 + Math.sin(Date.now() * 0.008) * 0.4;
if(c.userData.type === 7 && c.material.emissiveIntensity !== undefined) c.material.emissiveIntensity = 0.2 + Math.sin(Date.now() * 0.005) * 0.2;
if(c.userData.type === 10 && c.material.emissiveIntensity !== undefined) c.material.emissiveIntensity = 0.5 + Math.sin(Date.now() * 0.01) * 0.3; // Phantom Pulse
});
this.updateTweens(); this.updateParticles();
if (this.shakeTime > 0) { this.camera.position.x += (Math.random() - 0.5) * 0.4; this.camera.position.y += (Math.random() - 0.5) * 0.4; this.shakeTime--; }
else { this.camera.position.x *= 0.9; this.camera.position.y *= 0.9; }
if(this.renderer && this.scene && this.camera) this.renderer.render(this.scene, this.camera);
}

sleep(ms) { return new Promise(resolve => setTimeout(resolve, ms)); }
};
