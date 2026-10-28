// =========================================================================
// SPIELER-COCKPIT WEB-AUDIO SOUND-SYNTHESIZER (6x RANDOM CLICK & 6x SWITCH)
// =========================================================================
window.__sfCockpitAudioCtx = null;
window.__sfLastClickVariant = -1;
window.__sfLastSwitchVariant = -1;
window.__sfLastBackVariant = -1;

window.sfCockpitSound = function(type = 'click') {
    try {
        if (localStorage.getItem('sf_cockpit_sound_muted') === '1') return;
        const AudioCtx = window.AudioContext || window.webkitAudioContext;
        if (!AudioCtx) return;
        if (!window.__sfCockpitAudioCtx) {
            window.__sfCockpitAudioCtx = new AudioCtx();
        }
        const ctx = window.__sfCockpitAudioCtx;
        if (ctx.state === 'suspended') {
            ctx.resume().catch(() => {});
        }
        const now = ctx.currentTime;

        const pickVariant = (count, lastKey) => {
            let idx = Math.floor(Math.random() * count);
            if (count > 1 && idx === window[lastKey]) {
                idx = (idx + 1 + Math.floor(Math.random() * (count - 1))) % count;
            }
            window[lastKey] = idx;
            return idx;
        };

        // 1. 6 VERSCHIEDENE MATTE, MODERNE KLICK-SOUNDS
        if (type === 'click') {
            const v = pickVariant(6, '__sfLastClickVariant');
            const osc1 = ctx.createOscillator();
            const osc2 = ctx.createOscillator();
            const filter = ctx.createBiquadFilter();
            const gain = ctx.createGain();

            filter.type = 'lowpass';
            osc1.connect(filter);
            osc2.connect(filter);
            filter.connect(gain);
            gain.connect(ctx.destination);

            if (v === 0) {
                // Var 1: Warm Matte Gold Tactile Tap
                osc1.type = 'triangle';
                osc2.type = 'sine';
                osc1.frequency.setValueAtTime(640, now);
                osc1.frequency.exponentialRampToValueAtTime(195, now + 0.048);
                osc2.frequency.setValueAtTime(1280, now);
                osc2.frequency.exponentialRampToValueAtTime(390, now + 0.035);
                filter.frequency.setValueAtTime(2400, now);
                gain.gain.setValueAtTime(0.001, now);
                gain.gain.linearRampToValueAtTime(0.085, now + 0.004);
                gain.gain.exponentialRampToValueAtTime(0.0008, now + 0.052);
                osc1.start(now); osc2.start(now);
                osc1.stop(now + 0.055); osc2.stop(now + 0.055);
            } else if (v === 1) {
                // Var 2: Silk Crystal Micro-Click
                osc1.type = 'sine';
                osc2.type = 'triangle';
                osc1.frequency.setValueAtTime(880, now);
                osc1.frequency.exponentialRampToValueAtTime(330, now + 0.042);
                osc2.frequency.setValueAtTime(1760, now);
                osc2.frequency.exponentialRampToValueAtTime(660, now + 0.03);
                filter.frequency.setValueAtTime(3200, now);
                gain.gain.setValueAtTime(0.001, now);
                gain.gain.linearRampToValueAtTime(0.075, now + 0.003);
                gain.gain.exponentialRampToValueAtTime(0.0008, now + 0.046);
                osc1.start(now); osc2.start(now);
                osc1.stop(now + 0.05); osc2.stop(now + 0.05);
            } else if (v === 2) {
                // Var 3: Satin Horology Dual-Tick
                osc1.type = 'triangle';
                osc2.type = 'sine';
                osc1.frequency.setValueAtTime(520, now);
                osc1.frequency.exponentialRampToValueAtTime(175, now + 0.052);
                osc2.frequency.setValueAtTime(1040, now + 0.012);
                osc2.frequency.exponentialRampToValueAtTime(520, now + 0.048);
                filter.frequency.setValueAtTime(2100, now);
                gain.gain.setValueAtTime(0.001, now);
                gain.gain.linearRampToValueAtTime(0.09, now + 0.004);
                gain.gain.exponentialRampToValueAtTime(0.0008, now + 0.056);
                osc1.start(now); osc2.start(now + 0.012);
                osc1.stop(now + 0.06); osc2.stop(now + 0.06);
            } else if (v === 3) {
                // Var 4: Precision Cockpit Soft-Snap
                osc1.type = 'sine';
                osc2.type = 'sine';
                osc1.frequency.setValueAtTime(740, now);
                osc1.frequency.exponentialRampToValueAtTime(240, now + 0.045);
                osc2.frequency.setValueAtTime(1110, now);
                osc2.frequency.exponentialRampToValueAtTime(370, now + 0.04);
                filter.frequency.setValueAtTime(2800, now);
                gain.gain.setValueAtTime(0.001, now);
                gain.gain.linearRampToValueAtTime(0.08, now + 0.003);
                gain.gain.exponentialRampToValueAtTime(0.0008, now + 0.048);
                osc1.start(now); osc2.start(now);
                osc1.stop(now + 0.052); osc2.stop(now + 0.052);
            } else if (v === 4) {
                // Var 5: Warm Amber Harmonic Pluck
                osc1.type = 'triangle';
                osc2.type = 'sine';
                osc1.frequency.setValueAtTime(587.33, now);
                osc1.frequency.exponentialRampToValueAtTime(293.66, now + 0.055);
                osc2.frequency.setValueAtTime(880, now);
                osc2.frequency.exponentialRampToValueAtTime(440, now + 0.045);
                filter.frequency.setValueAtTime(1950, now);
                gain.gain.setValueAtTime(0.001, now);
                gain.gain.linearRampToValueAtTime(0.085, now + 0.005);
                gain.gain.exponentialRampToValueAtTime(0.0008, now + 0.06);
                osc1.start(now); osc2.start(now);
                osc1.stop(now + 0.064); osc2.stop(now + 0.064);
            } else {
                // Var 6: Modern Matte Glass Touch
                osc1.type = 'sine';
                osc2.type = 'triangle';
                osc1.frequency.setValueAtTime(659.25, now);
                osc1.frequency.exponentialRampToValueAtTime(329.63, now + 0.046);
                osc2.frequency.setValueAtTime(987.77, now + 0.008);
                osc2.frequency.exponentialRampToValueAtTime(659.25, now + 0.046);
                filter.frequency.setValueAtTime(2600, now);
                gain.gain.setValueAtTime(0.001, now);
                gain.gain.linearRampToValueAtTime(0.078, now + 0.004);
                gain.gain.exponentialRampToValueAtTime(0.0008, now + 0.052);
                osc1.start(now); osc2.start(now + 0.008);
                osc1.stop(now + 0.056); osc2.stop(now + 0.056);
            }
            return;
        }

        // 2. 6 VERSCHIEDENE ANSICHTSWECHSEL- & TRANSFORMATIONS-SOUNDS
        if (type === 'switch') {
            const v = pickVariant(6, '__sfLastSwitchVariant');
            const patterns = [
                // Var 1: Golden Horizon Morph (C4 -> G4 -> D5)
                { notes: [261.63, 392.00, 587.33], wave: 'triangle', spacing: 0.042, dur: 0.22, cutoff: 2200 },
                // Var 2: Velvet Astrolabe Shift (D4 -> A4 -> F#5)
                { notes: [293.66, 440.00, 739.99], wave: 'sine', spacing: 0.038, dur: 0.20, cutoff: 2600 },
                // Var 3: Silk Portal Unfold (E4 -> B4 -> G#5)
                { notes: [329.63, 493.88, 830.61], wave: 'triangle', spacing: 0.045, dur: 0.23, cutoff: 2100 },
                // Var 4: Starlight Lens Focus (F4 -> C5 -> G5)
                { notes: [349.23, 523.25, 783.99], wave: 'sine', spacing: 0.040, dur: 0.21, cutoff: 2800 },
                // Var 5: Warm Magnetic Lock (G4 -> D5 -> A5)
                { notes: [392.00, 587.33, 880.00], wave: 'triangle', spacing: 0.036, dur: 0.19, cutoff: 2300 },
                // Var 6: Royal Amber Glide (A3 -> E4 -> C#5)
                { notes: [220.00, 329.63, 554.37], wave: 'sine', spacing: 0.044, dur: 0.24, cutoff: 2000 }
            ];
            const p = patterns[v];

            // Sanfter Luft-Filter-Sweep im Hintergrund für geschmeidiges Morph-Gefühl
            const sweepOsc = ctx.createOscillator();
            const sweepFilter = ctx.createBiquadFilter();
            const sweepGain = ctx.createGain();
            sweepOsc.type = 'sawtooth';
            sweepOsc.frequency.setValueAtTime(110, now);
            sweepOsc.frequency.exponentialRampToValueAtTime(330, now + 0.18);
            sweepFilter.type = 'bandpass';
            sweepFilter.frequency.setValueAtTime(320, now);
            sweepFilter.frequency.exponentialRampToValueAtTime(1600, now + 0.18);
            sweepFilter.Q.value = 2.2;
            sweepGain.gain.setValueAtTime(0.001, now);
            sweepGain.gain.linearRampToValueAtTime(0.025, now + 0.06);
            sweepGain.gain.exponentialRampToValueAtTime(0.0005, now + 0.21);
            sweepOsc.connect(sweepFilter);
            sweepFilter.connect(sweepGain);
            sweepGain.connect(ctx.destination);
            sweepOsc.start(now);
            sweepOsc.stop(now + 0.22);

            p.notes.forEach((freq, idx) => {
                const t = now + idx * p.spacing;
                const osc = ctx.createOscillator();
                const flt = ctx.createBiquadFilter();
                const gn = ctx.createGain();
                osc.type = p.wave;
                osc.frequency.setValueAtTime(freq * 0.98, t);
                osc.frequency.exponentialRampToValueAtTime(freq, t + 0.035);
                flt.type = 'lowpass';
                flt.frequency.setValueAtTime(p.cutoff, t);
                gn.gain.setValueAtTime(0.001, t);
                gn.gain.linearRampToValueAtTime(0.058 - idx * 0.008, t + 0.015);
                gn.gain.exponentialRampToValueAtTime(0.0008, t + p.dur);
                osc.connect(flt);
                flt.connect(gn);
                gn.connect(ctx.destination);
                osc.start(t);
                osc.stop(t + p.dur + 0.01);
            });
            return;
        }

        // 3. 4 VERSCHIEDENE ZURÜCK- / EINKLAPP-SOUNDS
        if (type === 'back') {
            const v = pickVariant(4, '__sfLastBackVariant');
            const backPatterns = [
                [523.25, 392.00, 261.63],
                [587.33, 440.00, 293.66],
                [659.25, 493.88, 329.63],
                [493.88, 369.99, 246.94]
            ];
            const notes = backPatterns[v];
            notes.forEach((freq, idx) => {
                const t = now + idx * 0.036;
                const osc = ctx.createOscillator();
                const flt = ctx.createBiquadFilter();
                const gn = ctx.createGain();
                osc.type = 'triangle';
                osc.frequency.setValueAtTime(freq, t);
                flt.type = 'lowpass';
                flt.frequency.setValueAtTime(1800, t);
                gn.gain.setValueAtTime(0.001, t);
                gn.gain.linearRampToValueAtTime(0.052 - idx * 0.008, t + 0.012);
                gn.gain.exponentialRampToValueAtTime(0.0008, t + 0.16);
                osc.connect(flt);
                flt.connect(gn);
                gn.connect(ctx.destination);
                osc.start(t);
                osc.stop(t + 0.17);
            });
            return;
        }

        // 4. DEZENTER HOVER-IMPULS
        if (type === 'hover') {
            const osc = ctx.createOscillator();
            const gn = ctx.createGain();
            osc.type = 'sine';
            osc.frequency.setValueAtTime(920, now);
            osc.frequency.exponentialRampToValueAtTime(540, now + 0.018);
            gn.gain.setValueAtTime(0.014, now);
            gn.gain.exponentialRampToValueAtTime(0.0005, now + 0.02);
            osc.connect(gn);
            gn.connect(ctx.destination);
            osc.start(now);
            osc.stop(now + 0.022);
        }
    } catch (e) {}
};

window.kristallKollaps3D = function(initialModelPath = '', initialImagePath = '', initialLevel = 1) {
    let engine = null;
    const funkiBase = (typeof window.funkiHub === 'function')
        ? window.funkiHub(initialModelPath, initialImagePath, initialLevel)
        : {};

    return {
        ...funkiBase,
        activeGame: null,
        score: 0,
        moves: 15,
        gameState: 'ready',
        bgmVolumeUi: 20,
        isBgmPlaying: false,
        energyWarning: false,
        isFullscreen: false,

        // Spieler-Cockpit State (60 FPS Focus-Mode & Loader)
        cockpitReady: !!window.__sfCockpitAlreadyLoaded,
        cockpitLoadProgress: window.__sfCockpitAlreadyLoaded ? 100 : 12,
        activeCockpitPanel: null, // null | 'info' | 'legende' | 'prestige' | 'hangar' | 'ranking'
        cockpitMorphPhase: 'idle', // 'idle' | 'leaving' | 'entering'
        cockpitMorphDir: 'forward', // 'forward' | 'back'
        cockpitSoundMuted: localStorage.getItem('sf_cockpit_sound_muted') === '1',
        inspectedHero: null,
        hoveredNodeTitle: null,
        hoveredNodeDesc: null,
        mobileNodeInfoText: null,

        openHeroInspector(payload) {
            if (window.sfCockpitSound) {
                window.sfCockpitSound('click');
            }
            this.inspectedHero = payload;
        },

        init() {
            if (typeof this.initFunki === 'function') {
                this.initFunki();
            }

            // URL-Parameter prüfen (z. B. ?panel=ranking)
            try {
                const params = new URLSearchParams(window.location.search);
                const requestedPanel = params.get('panel');
                if (requestedPanel && ['info', 'legende', 'prestige', 'hangar', 'ranking'].includes(requestedPanel)) {
                    this.activeCockpitPanel = requestedPanel;
                }
            } catch (e) {}

            // 60 FPS Cockpit-Ladeanimation beim ersten Betreten (verhindert Grafik-Flackern)
            if (!window.__sfCockpitAlreadyLoaded) {
                this.startCockpitLoader();
            } else {
                this.cockpitReady = true;
                this.cockpitLoadProgress = 100;
            }

            this.$watch('bgmVolumeUi', val => {
                if (this.$refs.bgmAudio) this.$refs.bgmAudio.volume = (val / 100) * 0.3;
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

            window.addEventListener('fullscreenchange', () => {
                this.isFullscreen = !!document.fullscreenElement;
            });
            window.addEventListener('webkitfullscreenchange', () => {
                this.isFullscreen = !!document.webkitFullscreenElement;
            });
        },

        startCockpitLoader() {
            const heroImgUrl = this.currentImagePath || initialImagePath;
            let imgLoaded = !heroImgUrl;

            if (heroImgUrl) {
                const preloadImg = new Image();
                preloadImg.onload = () => { imgLoaded = true; };
                preloadImg.onerror = () => { imgLoaded = true; };
                preloadImg.src = heroImgUrl;
            }

            const startT = performance.now();
            const minDuration = 560; // Geschmeidiger 60 FPS Ladevorgang ohne Ruckler

            const step = (now) => {
                const elapsed = now - startT;
                const timeRatio = Math.min(1, elapsed / minDuration);
                const eased = 1 - Math.pow(1 - timeRatio, 3);
                const cap = imgLoaded ? 100 : 88;
                this.cockpitLoadProgress = Math.min(cap, Math.max(12, Math.round(12 + eased * 88)));

                if (timeRatio < 1 || (!imgLoaded && elapsed < 1400)) {
                    requestAnimationFrame(step);
                } else {
                    this.cockpitLoadProgress = 100;
                    setTimeout(() => {
                        this.cockpitReady = true;
                        window.__sfCockpitAlreadyLoaded = true;
                        if (this.activeCockpitPanel === 'hangar') {
                            setTimeout(() => window.dispatchEvent(new CustomEvent('hangar-panel-opened')), 100);
                        }
                    }, 90);
                }
            };
            requestAnimationFrame(step);
        },

        toggleCockpitSound() {
            this.cockpitSoundMuted = !this.cockpitSoundMuted;
            localStorage.setItem('sf_cockpit_sound_muted', this.cockpitSoundMuted ? '1' : '0');
            if (!this.cockpitSoundMuted && window.sfCockpitSound) {
                window.sfCockpitSound('click');
            }
        },

        // 60 FPS Fokus-Modus Transformation zwischen den Smart-Kacheln am Helden
        switchCockpitPanel(targetPanel) {
            if (this.cockpitMorphPhase !== 'idle') return;

            const isClosing = (this.activeCockpitPanel === targetPanel) || (targetPanel === null);
            const nextPanel = isClosing ? null : targetPanel;

            if (window.sfCockpitSound) {
                window.sfCockpitSound('click');
                setTimeout(() => {
                    window.sfCockpitSound(nextPanel === null ? 'back' : 'switch');
                }, 35);
            }

            this.cockpitMorphDir = nextPanel === null ? 'back' : 'forward';
            this.cockpitMorphPhase = 'leaving';

            setTimeout(() => {
                this.activeCockpitPanel = nextPanel;
                this.cockpitMorphPhase = 'entering';

                this.$nextTick(() => {
                    requestAnimationFrame(() => {
                        requestAnimationFrame(() => {
                            this.cockpitMorphPhase = 'idle';
                            if (nextPanel === 'hangar') {
                                window.dispatchEvent(new CustomEvent('hangar-panel-opened'));
                            }
                            if (nextPanel !== null) {
                                const stageEl = document.getElementById('sf-cockpit-focus-stage');
                                if (stageEl) {
                                    stageEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                                }
                            }
                        });
                    });
                });
            }, 175);
        },

        openGameArena(gameKey) {
            if (window.sfCockpitSound) {
                window.sfCockpitSound('click');
                setTimeout(() => window.sfCockpitSound('switch'), 40);
            }
            this.activeCockpitPanel = null;
            this.activeGame = gameKey;
            window.scrollTo({ top: 0, behavior: 'smooth' });
        },

        closeGameArena() {
            if (window.sfCockpitSound) {
                window.sfCockpitSound('back');
            }
            if (this.activeGame === 'kristall') {
                this.quitGame();
            } else {
                this.activeGame = null;
            }
        },

toggleFullscreen() {
let elem = this.$refs.canvasContainer ? this.$refs.canvasContainer.parentElement : null;
if (!elem) return;
if (!document.fullscreenElement) {
    if (elem.requestFullscreen) { elem.requestFullscreen(); }
    else if (elem.webkitRequestFullscreen) { elem.webkitRequestFullscreen(); } // Safari
    this.isFullscreen = true;
} else {
    if (document.exitFullscreen) { document.exitFullscreen(); }
    else if (document.webkitExitFullscreen) { document.webkitExitFullscreen(); }
    this.isFullscreen = false;
}
},

toggleMute() {
let audio = this.$refs.bgmAudio;
if(!audio) return;
if (this.isBgmPlaying) { audio.pause(); this.isBgmPlaying = false; }
else { audio.play().catch(e => console.log(e)); this.isBgmPlaying = true; }
},

quitGame() {
if (this.gameState === 'playing') {
    if (!confirm('Möchtest du das Spiel wirklich abbrechen? Die eingesetzte Energie geht dabei unwiderruflich verloren!')) {
        return;
    }
}
if (this.isFullscreen && document.exitFullscreen) { document.exitFullscreen(); this.isFullscreen = false; }
else if (this.isFullscreen && document.webkitExitFullscreen) { document.webkitExitFullscreen(); this.isFullscreen = false; }
if (this.$refs.bgmAudio) {
this.$refs.bgmAudio.pause();
this.$refs.bgmAudio.currentTime = 0;
this.isBgmPlaying = false;
}
if (engine) engine.clearBoard();
this.gameState = 'ready';
if (this.activeGame === 'kristall') { this.activeGame = null; }
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

// Stop music
if (this.$refs.bgmAudio) {
this.$refs.bgmAudio.pause();
this.isBgmPlaying = false;
}

let earnedFunken = Math.floor(this.score / 100);
if (earnedFunken > 0) { try { this.$wire.rewardGameSparks(earnedFunken); } catch(e) {} }
}
},
getMoves: () => { return this.moves; }
};

engine = new window.Match3DEngine(container, callbacks);
},

startGame() {
// AUTO FULLSCREEN ON MOBILE
if (window.innerWidth <= 768 && !document.fullscreenElement) {
    this.toggleFullscreen();
}
this.score = 0;
this.moves = 15;
this.gameState = 'playing';
if (this.$refs.bgmAudio) {
this.$refs.bgmAudio.volume = (this.bgmVolumeUi / 100) * 0.3;
this.$refs.bgmAudio.currentTime = 0; // Restart
this.$refs.bgmAudio.play().then(() => { this.isBgmPlaying = true; }).catch(e => console.warn(e));
}
if(engine) engine.start();
}
};
};
