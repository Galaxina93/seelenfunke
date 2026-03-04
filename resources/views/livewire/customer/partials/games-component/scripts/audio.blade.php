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
};
