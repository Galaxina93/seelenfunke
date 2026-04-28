window.goldDust = function() {
return {
init() {
const canvas = document.getElementById('gold-dust-canvas');
if (!canvas) return;
const ctx = canvas.getContext('2d');

let width, height;
let stars = [];
let planets = [];

// Warp-State
let isWarping = false;
let warpSpeedMultiplier = 0;
let uiFadeOut = 1; // Blendet die Planeten aus

window.startWarpSpeed = () => {
isWarping = true;
};

const config = {
starsCount: 300,
planetsCount: 3,
colors: {
gold: '197, 160, 89',
white: '255, 255, 255',
blue: '100, 150, 255',
copper: '217, 119, 83'
}
};

const resize = () => {
width = canvas.width = document.body.clientWidth;
height = canvas.height = window.innerHeight;
};
window.addEventListener('resize', resize);
resize();

const random = (min, max) => Math.random() * (max - min) + min;

for (let i = 0; i < config.starsCount; i++) {
stars.push({
x: random(0, width), y: random(0, height),
z: random(0.1, 2),
r: random(0.2, 1.2),
baseAlpha: random(0.1, 0.6),
angle: random(0, Math.PI * 2),
speed: random(0.005, 0.02),
color: Math.random() > 0.8 ? config.colors.gold : config.colors.white
});
}

for (let i = 0; i < config.planetsCount; i++) {
planets.push({
x: random(0, width), y: random(0, height),
r: random(200, 500),
vx: random(-0.01, 0.01), vy: random(-0.01, 0.01),
color: [config.colors.gold, config.colors.blue, config.colors.copper][Math.floor(Math.random() * 3)],
maxAlpha: random(0.02, 0.06)
});
}

const loop = () => {
ctx.clearRect(0, 0, width, height);
let cx = width / 2;
let cy = height / 2;

if (isWarping) {
warpSpeedMultiplier += 0.05;
if (warpSpeedMultiplier > 15) warpSpeedMultiplier = 15;
uiFadeOut -= 0.02;
if (uiFadeOut < 0) uiFadeOut = 0;
}

if (uiFadeOut > 0) {
planets.forEach(p => {
p.x += p.vx; p.y += p.vy;
if (p.x - p.r > width) p.x = -p.r; if (p.x + p.r < 0) p.x = width + p.r;
if (p.y - p.r > height) p.y = -p.r; if (p.y + p.r < 0) p.y = height + p.r;

let grad = ctx.createRadialGradient(p.x, p.y, 0, p.x, p.y, p.r);
grad.addColorStop(0, `rgba(${p.color}, ${p.maxAlpha * uiFadeOut})`);
grad.addColorStop(1, 'rgba(0,0,0,0)');
ctx.beginPath(); ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2); ctx.fillStyle = grad; ctx.fill();
});
}

stars.forEach(s => {
if (!isWarping) {
s.angle += s.speed;
let currentAlpha = s.baseAlpha + Math.sin(s.angle) * 0.3;
if (currentAlpha < 0) currentAlpha = 0;

s.x -= s.z * 0.1;
if (s.x < 0) s.x = width;

ctx.beginPath();
ctx.arc(s.x, s.y, s.r, 0, Math.PI * 2);
ctx.fillStyle = `rgba(${s.color}, ${currentAlpha})`;
ctx.fill();
} else {
let dx = s.x - cx;
let dy = s.y - cy;
let dist = Math.sqrt(dx*dx + dy*dy);
if (dist === 0) dist = 0.1;

let moveX = (dx / dist) * (warpSpeedMultiplier * s.z);
let moveY = (dy / dist) * (warpSpeedMultiplier * s.z);

s.x += moveX;
s.y += moveY;

ctx.beginPath();
ctx.moveTo(s.x, s.y);
ctx.lineTo(s.x - moveX * 4, s.y - moveY * 4);
ctx.strokeStyle = `rgba(${s.color}, ${Math.min(1, s.baseAlpha + 0.5)})`;
ctx.lineWidth = s.r * (warpSpeedMultiplier * 0.2);
ctx.lineCap = 'round';
ctx.stroke();

if (s.x < 0 || s.x > width || s.y < 0 || s.y > height) {
s.x = cx + (Math.random() - 0.5) * 50;
s.y = cy + (Math.random() - 0.5) * 50;
s.z = random(0.5, 3);
}
}
});

requestAnimationFrame(loop);
};
loop();
}
};
};
