window.ArcadeAudio = class ArcadeAudio {
constructor() {
    if (window.__sharedArcadeAudioCtx) {
        this.ctx = window.__sharedArcadeAudioCtx;
    } else {
        const AudioCtx = window.AudioContext || window.webkitAudioContext;
        this.ctx = AudioCtx ? new AudioCtx() : null;
        window.__sharedArcadeAudioCtx = this.ctx;
    }
    if (this.ctx && !window.__sharedArcadeNoiseBuffer) {
        const maxNoiseDur = 1.4;
        const bufferSize = Math.floor(this.ctx.sampleRate * maxNoiseDur);
        const buffer = this.ctx.createBuffer(1, bufferSize, this.ctx.sampleRate);
        const data = buffer.getChannelData(0);
        // Warmes, leicht geglättetes Pink/Brown-Rauschen statt schrillem White-Noise für matte, druckvolle Explosionen
        let b0 = 0, b1 = 0, b2 = 0;
        for (let i = 0; i < bufferSize; i++) {
            const white = Math.random() * 2 - 1;
            b0 = 0.99 * b0 + white * 0.18;
            b1 = 0.96 * b1 + white * 0.25;
            b2 = 0.85 * b2 + white * 0.35;
            data[i] = Math.max(-1, Math.min(1, (b0 + b1 + b2 + white * 0.12) * 0.65));
        }
        window.__sharedArcadeNoiseBuffer = buffer;
    }
    this.noiseBuffer = window.__sharedArcadeNoiseBuffer || null;

    // 6 individuell gestaltete, mehrstufige Meteoriten-Zerstörungs-Sounds (Pre-Baked für 0ms Latenz im Gefecht)
    if (this.ctx && !window.__sharedExplosionBuffers) {
        window.__sharedExplosionBuffers = this._buildExplosionSoundBank();
    }
    this.explosionBuffers = window.__sharedExplosionBuffers || [];
    this._recentExplosionIndices = [];
}

_buildExplosionSoundBank() {
    const sr = this.ctx.sampleRate;
    const buffers = [];

    // Hilfsfunktion: sanfter Attack ohne Klick + exponentieller Decay
    const env = (t, attack, decay) => {
        if (t < 0) return 0;
        if (t < attack) return 0.5 * (1 - Math.cos(Math.PI * (t / attack)));
        return Math.exp(-(t - attack) / decay);
    };

    // 6 völlig unterschiedliche akustische Rezepte (0..5)
    for (let variant = 0; variant < 6; variant++) {
        const dur = 0.52;
        const len = Math.floor(sr * dur);
        const buf = this.ctx.createBuffer(1, len, sr);
        const out = buf.getChannelData(0);

        let lp1 = 0, lp2 = 0, bpState = 0;
        let phase1 = 0, phase2 = 0, phase3 = 0;

        // Vorab-definierte Splitter-/Trümmer-Einschläge je nach Variante
        const grains = [];
        if (variant === 0) {
            // Variante 0: "Schwerer Basalt-Bruch" — Tiefer Gesteins-Donner + 4 schwere Felsbrocken-Brüche
            grains.push({ t0: 0.035, d: 0.055, f: 280, amp: 0.55 }, { t0: 0.095, d: 0.065, f: 210, amp: 0.48 }, { t0: 0.175, d: 0.075, f: 165, amp: 0.38 }, { t0: 0.265, d: 0.085, f: 130, amp: 0.28 });
        } else if (variant === 1) {
            // Variante 1: "Kristall-Scherben-Zersplitterung" — Warmer Punch + glitzernde Kristall-Splitterkaskade
            grains.push({ t0: 0.018, d: 0.045, f: 740, amp: 0.42 }, { t0: 0.055, d: 0.050, f: 980, amp: 0.36 }, { t0: 0.105, d: 0.055, f: 620, amp: 0.34 }, { t0: 0.160, d: 0.065, f: 1180, amp: 0.26 }, { t0: 0.230, d: 0.070, f: 840, amp: 0.22 });
        } else if (variant === 3) {
            // Variante 3: "Doppel-Detonation / Kern-Spaltung" — Erst äußere Kruste, 70ms später massiver Kern-Bruch
            grains.push({ t0: 0.072, d: 0.140, f: 145, amp: 0.85 }, { t0: 0.165, d: 0.080, f: 240, amp: 0.42 }, { t0: 0.245, d: 0.090, f: 175, amp: 0.32 });
        } else if (variant === 4) {
            // Variante 4: "Feurige Funken-Nova" — Energie-Verpuffung + knisternde Glut-Funken im Ausklang
            for (let g = 0; g < 7; g++) {
                grains.push({ t0: 0.04 + g * 0.042, d: 0.028, f: 420 + (g % 3) * 210, amp: 0.32 * (1 - g * 0.1) });
            }
        } else if (variant === 5) {
            // Variante 5: "Massiver Erz-Koloss-Crunch" — Dichter, körniger Erz-Zermalmer
            grains.push({ t0: 0.022, d: 0.060, f: 320, amp: 0.52 }, { t0: 0.062, d: 0.070, f: 255, amp: 0.50 }, { t0: 0.115, d: 0.080, f: 195, amp: 0.44 }, { t0: 0.180, d: 0.090, f: 150, amp: 0.35 }, { t0: 0.260, d: 0.100, f: 115, amp: 0.26 });
        }

        for (let i = 0; i < len; i++) {
            const t = i / sr;
            const white = Math.random() * 2 - 1;

            // Gefiltertes Pink/Brown-Rauschen als texturierte Basis
            lp1 = 0.965 * lp1 + 0.035 * white;
            lp2 = 0.880 * lp2 + 0.120 * white;
            const warmNoise = (lp1 * 0.65 + lp2 * 0.35);

            let sample = 0;

            if (variant === 0) {
                // 0: Schwerer Basalt-Bruch (Tiefer Sub-Wumms + trockenes Gesteins-Knirschen)
                const f1 = 34 + 95 * Math.exp(-t / 0.09);
                phase1 += (2 * Math.PI * f1) / sr;
                const subBody = Math.sin(phase1) * env(t, 0.010, 0.15);
                const rockRoar = warmNoise * env(t, 0.012, 0.17) * (0.75 + 0.25 * Math.sin(phase1 * 0.5));
                sample = subBody * 0.72 + rockRoar * 0.85;
            } else if (variant === 1) {
                // 1: Kristall-Meteoriten-Zersplitterung (Satter tiefer Impact + harmonische Kristall-Resonanz)
                const f1 = 42 + 120 * Math.exp(-t / 0.07);
                const f2 = 380 + 260 * Math.exp(-t / 0.11);
                phase1 += (2 * Math.PI * f1) / sr;
                phase2 += (2 * Math.PI * f2) / sr;
                const bass = Math.sin(phase1) * env(t, 0.009, 0.12);
                const crystalChime = Math.sin(phase2 + Math.sin(phase2 * 1.414) * 1.2) * env(t, 0.008, 0.095);
                const shatterNoise = (lp2 - lp1) * 2.2 * env(t, 0.010, 0.14);
                sample = bass * 0.65 + crystalChime * 0.38 + shatterNoise * 0.75;
            } else if (variant === 2) {
                // 2: Plasma-Vakuum-Implosion (Kurzer Saug-Sweep -> kräftige magnetische Druckwelle)
                const fSweep = t < 0.045
                    ? (340 - (t / 0.045) * 210)
                    : (30 + 145 * Math.exp(-(t - 0.045) / 0.11));
                phase1 += (2 * Math.PI * fSweep) / sr;
                phase2 += (2 * Math.PI * (fSweep * 1.48)) / sr;
                const suction = t < 0.045 ? env(t, 0.008, 0.04) * 0.55 : 0;
                const blastEnv = t >= 0.038 ? env(t - 0.038, 0.009, 0.16) : 0;
                const plasmaWave = (Math.sin(phase1) * 0.75 + Math.sin(phase2) * 0.35) * (suction + blastEnv);
                const ionNoise = warmNoise * (suction * 0.5 + blastEnv * 0.88);
                sample = plasmaWave * 0.78 + ionNoise * 0.82;
            } else if (variant === 3) {
                // 3: Doppel-Detonation / Kern-Spaltung (Zweistufiger Knall: Schalen-Crack + tiefer Kern-Boom)
                const fShell = 55 + 150 * Math.exp(-t / 0.045);
                phase1 += (2 * Math.PI * fShell) / sr;
                const shellCrack = (Math.sin(phase1) * 0.6 + lp2 * 0.8) * env(t, 0.008, 0.065);

                const t2 = t - 0.068;
                let coreBoom = 0;
                if (t2 > 0) {
                    const fCore = 28 + 105 * Math.exp(-t2 / 0.12);
                    phase2 += (2 * Math.PI * fCore) / sr;
                    coreBoom = (Math.sin(phase2) * 0.85 + warmNoise * 0.9) * env(t2, 0.010, 0.17);
                }
                sample = shellCrack * 0.75 + coreBoom * 0.88;
            } else if (variant === 4) {
                // 4: Feurige Funken-Nova (Breiter, warmer Flammen-Whoosh + goldene Oberton-Glut)
                const fNova = 36 + 85 * Math.exp(-t / 0.13);
                phase1 += (2 * Math.PI * fNova) / sr;
                phase2 += (2 * Math.PI * (fNova * 1.5)) / sr;
                const sweepAlpha = Math.max(0.04, 0.32 * Math.exp(-t / 0.18));
                bpState = (1 - sweepAlpha) * bpState + sweepAlpha * white;
                const fireWhoosh = bpState * 1.8 * env(t, 0.015, 0.20);
                const warmChord = (Math.sin(phase1) * 0.7 + Math.sin(phase2) * 0.3) * env(t, 0.012, 0.15);
                sample = fireWhoosh * 0.85 + warmChord * 0.62;
            } else {
                // 5: Massiver Erz-Koloss-Crunch (Dunkle metallisch-steinige Hohlraum-Resonanz + Geröll)
                const fOre1 = 44 + 80 * Math.exp(-t / 0.08);
                const fOre2 = 168 * Math.exp(-t / 0.14);
                const fOre3 = 258 * Math.exp(-t / 0.10);
                phase1 += (2 * Math.PI * fOre1) / sr;
                phase2 += (2 * Math.PI * fOre2) / sr;
                phase3 += (2 * Math.PI * fOre3) / sr;
                const oreModes = (Math.sin(phase1) * 0.75 + Math.sin(phase2) * 0.35 + Math.sin(phase3) * 0.25) * env(t, 0.010, 0.14);
                const gravelCrunch = warmNoise * (0.7 + 0.3 * Math.cos(phase2)) * env(t, 0.011, 0.18);
                sample = oreModes * 0.70 + gravelCrunch * 0.88;
            }

            // Individuelle Gesteins-/Kristall-Trümmer-Grains aufaddieren
            for (let g = 0; g < grains.length; g++) {
                const gr = grains[g];
                const gt = t - gr.t0;
                if (gt > 0 && gt < gr.d * 3.5) {
                    const gEnv = env(gt, 0.005, gr.d);
                    const gTone = Math.sin(2 * Math.PI * gr.f * gt * Math.exp(-gt / (gr.d * 1.8)));
                    sample += (gTone * 0.45 + lp2 * 0.75) * gEnv * gr.amp;
                }
            }

            // Warme Bandsättigung (tanh) verhindert harte Peaks und sorgt für druckvollen, edel-matten Arcade-Sound
            out[i] = Math.tanh(sample * 1.45) * 0.85;
        }

        buffers.push(buf);
    }

    return buffers;
}

playTone(freq, type, duration, vol = 0.1) {
if(!this.ctx) return;
if(this.ctx.state === 'suspended') this.ctx.resume();
const osc = this.ctx.createOscillator(); const gain = this.ctx.createGain();
osc.type = type; osc.frequency.setValueAtTime(freq, this.ctx.currentTime);
gain.gain.setValueAtTime(vol, this.ctx.currentTime); gain.gain.exponentialRampToValueAtTime(0.01, this.ctx.currentTime + duration);
osc.connect(gain); gain.connect(this.ctx.destination); osc.start(); osc.stop(this.ctx.currentTime + duration);
}

playSwap() { this.playTone(300, 'sine', 0.1, 0.05); setTimeout(() => this.playTone(400, 'sine', 0.15, 0.05), 100); }
playMatch() { this.playTone(523.25, 'sine', 0.3, 0.1); this.playTone(659.25, 'sine', 0.3, 0.1); this.playTone(783.99, 'sine', 0.4, 0.1); }

playLaser() {
if(!this.ctx) return;
if(this.ctx.state === 'suspended') this.ctx.resume();
const osc = this.ctx.createOscillator(); const gain = this.ctx.createGain();
osc.type = 'sawtooth'; osc.frequency.setValueAtTime(800, this.ctx.currentTime);
osc.frequency.exponentialRampToValueAtTime(100, this.ctx.currentTime + 0.3);
gain.gain.setValueAtTime(0.1, this.ctx.currentTime); gain.gain.exponentialRampToValueAtTime(0.01, this.ctx.currentTime + 0.3);
osc.connect(gain); gain.connect(this.ctx.destination); osc.start(); osc.stop(this.ctx.currentTime + 0.3);
}

playShoot() { this.playTone(600, 'square', 0.1, 0.02); }
playHit() {
    if (!this.ctx) return;
    if (this.ctx.state === 'suspended') this.ctx.resume();
    const now = this.ctx.currentTime;

    // Kurzer, matter Plasma-Treffer auf Gestein (ohne Mikrofon-Plopp)
    if (this.noiseBuffer) {
        const src = this.ctx.createBufferSource();
        src.buffer = this.noiseBuffer;

        const bp = this.ctx.createBiquadFilter();
        bp.type = 'bandpass';
        bp.frequency.setValueAtTime(520 + Math.random() * 140, now);
        bp.frequency.exponentialRampToValueAtTime(190, now + 0.08);
        bp.Q.setValueAtTime(1.1, now);

        const gain = this.ctx.createGain();
        gain.gain.setValueAtTime(0.001, now);
        gain.gain.linearRampToValueAtTime(0.11, now + 0.008);
        gain.gain.exponentialRampToValueAtTime(0.001, now + 0.085);

        src.connect(bp);
        bp.connect(gain);
        gain.connect(this.ctx.destination);
        src.start(now, Math.random() * 0.2, 0.09);
    }
}

playExplosion(intensity = 1.0) { 
    if (!this.ctx) return;
    if (this.ctx.state === 'suspended') this.ctx.resume();

    if (!this.explosionBuffers || this.explosionBuffers.length === 0) {
        this.explosionBuffers = this._buildExplosionSoundBank();
    }
    const count = this.explosionBuffers.length;
    if (count === 0) return;

    // Wähle zufällig einen der 6 Zerstörungs-Sounds, aber niemals denselben wie bei den letzten 2 Treffern
    const candidates = [];
    for (let i = 0; i < count; i++) {
        if (!this._recentExplosionIndices.includes(i)) {
            candidates.push(i);
        }
    }
    const chosenIdx = candidates.length > 0
        ? candidates[Math.floor(Math.random() * candidates.length)]
        : Math.floor(Math.random() * count);

    this._recentExplosionIndices.push(chosenIdx);
    if (this._recentExplosionIndices.length > 2) {
        this._recentExplosionIndices.shift();
    }

    const now = this.ctx.currentTime;
    const power = Math.max(0.65, Math.min(1.6, intensity));
    const rateJitter = (0.93 + Math.random() * 0.14) / Math.pow(power, 0.28);

    const src = this.ctx.createBufferSource();
    src.buffer = this.explosionBuffers[chosenIdx];
    src.playbackRate.setValueAtTime(rateJitter, now);

    // Sanfter Lowpass-Filter rundet den Klang ab (je größer der Meteorit, desto tiefer/wuchtiger)
    const filter = this.ctx.createBiquadFilter();
    filter.type = 'lowpass';
    filter.frequency.setValueAtTime(1650 + Math.random() * 450, now);
    filter.frequency.exponentialRampToValueAtTime(260, now + 0.42);

    const gain = this.ctx.createGain();
    gain.gain.setValueAtTime(Math.min(0.65, 0.38 * power), now);
    gain.gain.exponentialRampToValueAtTime(0.001, now + 0.48);

    src.connect(filter);
    filter.connect(gain);
    gain.connect(this.ctx.destination);
    src.start(now);
}

playShipExplosion() {
    if (!this.ctx) return;
    if (this.ctx.state === 'suspended') this.ctx.resume();
    const now = this.ctx.currentTime;

    // Mehrstufige, kräftige Raumschiff-Detonation (Haupt-Explosion + 2 Nachbeben)
    this.playExplosion(1.55);
    setTimeout(() => { if (this.ctx) this.playExplosion(1.2); }, 180);
    setTimeout(() => { if (this.ctx) this.playExplosion(0.95); }, 380);

    // Langer, matter Reaktor-Ausklang
    if (this.noiseBuffer) {
        const tail = this.ctx.createBufferSource();
        tail.buffer = this.noiseBuffer;
        tail.playbackRate.setValueAtTime(0.45, now);

        const lp = this.ctx.createBiquadFilter();
        lp.type = 'lowpass';
        lp.frequency.setValueAtTime(420, now);
        lp.frequency.exponentialRampToValueAtTime(48, now + 1.15);

        const gain = this.ctx.createGain();
        gain.gain.setValueAtTime(0.001, now);
        gain.gain.linearRampToValueAtTime(0.32, now + 0.04);
        gain.gain.exponentialRampToValueAtTime(0.001, now + 1.15);

        tail.connect(lp);
        lp.connect(gain);
        gain.connect(this.ctx.destination);
        tail.start(now, 0, 1.2);
    }
}
playPickup() { this.playTone(800, 'sine', 0.1, 0.05); setTimeout(() => this.playTone(1200, 'sine', 0.1, 0.05), 100); }
playHeartPickup() {
    if (!this.ctx) return;
    if (this.ctx.state === 'suspended') this.ctx.resume();
    this.playTone(523.25, 'sine', 0.14, 0.07);
    setTimeout(() => this.playTone(659.25, 'sine', 0.15, 0.08), 65);
    setTimeout(() => this.playTone(783.99, 'sine', 0.16, 0.09), 130);
    setTimeout(() => this.playTone(1046.50, 'triangle', 0.28, 0.10), 195);
}
playWeaponUpgrade() {
    if (!this.ctx) return;
    if (this.ctx.state === 'suspended') this.ctx.resume();
    this.playTone(440, 'triangle', 0.12, 0.07);
    setTimeout(() => this.playTone(587.33, 'triangle', 0.14, 0.08), 70);
    setTimeout(() => this.playTone(880, 'sine', 0.22, 0.09), 140);
}
playBossWarning() {
    if (!this.ctx) return;
    if (this.ctx.state === 'suspended') this.ctx.resume();
    const triggerPulse = (delayMs) => {
        setTimeout(() => {
            if (!this.ctx) return;
            const now = this.ctx.currentTime;
            const osc = this.ctx.createOscillator();
            const lp = this.ctx.createBiquadFilter();
            const gain = this.ctx.createGain();
            osc.type = 'sawtooth';
            osc.frequency.setValueAtTime(110, now);
            osc.frequency.linearRampToValueAtTime(78, now + 0.32);
            lp.type = 'lowpass';
            lp.frequency.setValueAtTime(360, now);
            gain.gain.setValueAtTime(0.001, now);
            gain.gain.linearRampToValueAtTime(0.16, now + 0.03);
            gain.gain.exponentialRampToValueAtTime(0.001, now + 0.34);
            osc.connect(lp);
            lp.connect(gain);
            gain.connect(this.ctx.destination);
            osc.start(now);
            osc.stop(now + 0.35);
        }, delayMs);
    };
    triggerPulse(0);
    triggerPulse(400);
}
playBossShot() {
    if (!this.ctx) return;
    if (this.ctx.state === 'suspended') this.ctx.resume();
    const now = this.ctx.currentTime;
    const osc = this.ctx.createOscillator();
    const lp = this.ctx.createBiquadFilter();
    const gain = this.ctx.createGain();
    osc.type = 'triangle';
    osc.frequency.setValueAtTime(260, now);
    osc.frequency.exponentialRampToValueAtTime(75, now + 0.14);
    lp.type = 'lowpass';
    lp.frequency.setValueAtTime(520, now);
    gain.gain.setValueAtTime(0.001, now);
    gain.gain.linearRampToValueAtTime(0.10, now + 0.01);
    gain.gain.exponentialRampToValueAtTime(0.001, now + 0.15);
    osc.connect(lp);
    lp.connect(gain);
    gain.connect(this.ctx.destination);
    osc.start(now);
    osc.stop(now + 0.16);
}
playTeleport() {
    if(!this.ctx) return;
    if(this.ctx.state === 'suspended') this.ctx.resume();
    const osc = this.ctx.createOscillator(); const gain = this.ctx.createGain();
    osc.type = 'sine'; osc.frequency.setValueAtTime(200, this.ctx.currentTime);
    osc.frequency.exponentialRampToValueAtTime(1500, this.ctx.currentTime + 0.15);
    gain.gain.setValueAtTime(0, this.ctx.currentTime);
    gain.gain.linearRampToValueAtTime(0.2, this.ctx.currentTime + 0.05);
    gain.gain.linearRampToValueAtTime(0, this.ctx.currentTime + 0.15);
    osc.connect(gain); gain.connect(this.ctx.destination); osc.start(); osc.stop(this.ctx.currentTime + 0.15);
}
};
