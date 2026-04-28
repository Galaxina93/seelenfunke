window.ArcadeAudio = class ArcadeAudio {
constructor() { this.ctx = new (window.AudioContext || window.webkitAudioContext)(); }

playTone(freq, type, duration, vol = 0.1) {
if(this.ctx.state === 'suspended') this.ctx.resume();
const osc = this.ctx.createOscillator(); const gain = this.ctx.createGain();
osc.type = type; osc.frequency.setValueAtTime(freq, this.ctx.currentTime);
gain.gain.setValueAtTime(vol, this.ctx.currentTime); gain.gain.exponentialRampToValueAtTime(0.01, this.ctx.currentTime + duration);
osc.connect(gain); gain.connect(this.ctx.destination); osc.start(); osc.stop(this.ctx.currentTime + duration);
}

playSwap() { this.playTone(300, 'sine', 0.1, 0.05); setTimeout(() => this.playTone(400, 'sine', 0.15, 0.05), 100); }
playMatch() { this.playTone(523.25, 'sine', 0.3, 0.1); this.playTone(659.25, 'sine', 0.3, 0.1); this.playTone(783.99, 'sine', 0.4, 0.1); }

playLaser() {
if(this.ctx.state === 'suspended') this.ctx.resume();
const osc = this.ctx.createOscillator(); const gain = this.ctx.createGain();
osc.type = 'sawtooth'; osc.frequency.setValueAtTime(800, this.ctx.currentTime);
osc.frequency.exponentialRampToValueAtTime(100, this.ctx.currentTime + 0.3);
gain.gain.setValueAtTime(0.1, this.ctx.currentTime); gain.gain.exponentialRampToValueAtTime(0.01, this.ctx.currentTime + 0.3);
osc.connect(gain); gain.connect(this.ctx.destination); osc.start(); osc.stop(this.ctx.currentTime + 0.3);
}

playShoot() { this.playTone(600, 'square', 0.1, 0.02); }
playExplosion() { 
    // Randomize tone slightly for variation
    const baseFreq = 80 + Math.random() * 40;
    const secondFreq = baseFreq - 20;
    const dur1 = 0.2 + Math.random() * 0.15;
    const dur2 = 0.1 + Math.random() * 0.1;
    this.playTone(baseFreq, 'sawtooth', dur1, 0.1); 
    setTimeout(() => this.playTone(secondFreq, 'square', dur2, 0.1), 50 + Math.random() * 30); 
}
playPickup() { this.playTone(800, 'sine', 0.1, 0.05); setTimeout(() => this.playTone(1200, 'sine', 0.1, 0.05), 100); }
playTeleport() {
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
