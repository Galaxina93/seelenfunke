window.kristallKollaps3D = function() {
let engine = null;

return {
activeGame: null,
score: 0,
moves: 15,
gameState: 'ready',
bgmVolume: 0.1,
isBgmPlaying: false,
energyWarning: false,

init() {
this.$watch('bgmVolume', val => {
if (this.$refs.bgmAudio) this.$refs.bgmAudio.volume = val;
});

this.$watch('activeGame', value => {
if (value === 'kristall') {
setTimeout(() => {
if (!engine) { this.init3DEngine(); } else { engine.resize(); }
}, 300);
} else {
this.quitGame();
}
});
},

toggleMute() {
let audio = this.$refs.bgmAudio;
if(!audio) return;
if (this.isBgmPlaying) { audio.pause(); this.isBgmPlaying = false; }
else { audio.play().catch(e => console.log(e)); this.isBgmPlaying = true; }
},

quitGame() {
if (this.$refs.bgmAudio) {
this.$refs.bgmAudio.pause();
this.$refs.bgmAudio.currentTime = 0;
this.isBgmPlaying = false;
}
if (engine) engine.clearBoard();
this.gameState = 'ready';
this.activeGame = null;
},

async attemptStartGame() {
if (typeof window.THREE === 'undefined') {
alert("Three.js lädt noch. Bitte kurz warten...");
return;
}

if (engine && engine.audio) engine.audio.ctx.resume();

try {
let hasEnergy = await this.$wire.consumeEnergy();
if (hasEnergy) {
this.startGame();
} else {
this.energyWarning = true;
setTimeout(() => { this.energyWarning = false; }, 3000);
}
} catch (e) {
console.warn("Backend-Check übersprungen. Starte Spiel im Testmodus.");
this.startGame();
}
},

init3DEngine() {
if (typeof window.THREE === 'undefined') return;

const container = document.getElementById('threejs-match3-container');
const scoreLayer = document.getElementById('floating-scores-layer');

const callbacks = {
onScore: (points, combo, meshPos, colorHex) => {
let total = points * combo * 5; if(points > 3) total += 30;
this.score += total;
if(meshPos && engine) engine.showFloatingText(meshPos, `+${total}`, colorHex, scoreLayer);
},
onAddMoves: (extraMoves, meshPos) => {
this.moves += extraMoves;
if(meshPos && engine) engine.showFloatingText(meshPos, `+${extraMoves} Züge!`, '#f472b6', scoreLayer, true);
},
onMoveUsed: () => {
if (this.moves > 0) this.moves--;
if(this.moves <= 0 && this.gameState !== 'gameover') {
this.gameState = 'gameover';
let earnedFunken = Math.floor(this.score / 100);
if (earnedFunken > 0) { try { this.$wire.rewardGameSparks(earnedFunken); } catch(e) {} }
}
},
getMoves: () => { return this.moves; }
};

engine = new window.Match3DEngine(container, callbacks);
},

startGame() {
this.score = 0;
this.moves = 15;
this.gameState = 'playing';

if (this.$refs.bgmAudio) {
this.$refs.bgmAudio.volume = this.bgmVolume;
this.$refs.bgmAudio.play().then(() => { this.isBgmPlaying = true; }).catch(e => console.warn(e));
}
if(engine) engine.start();
}
};
};
