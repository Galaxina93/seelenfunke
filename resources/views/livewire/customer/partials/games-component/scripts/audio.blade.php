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
    if(this.ctx.state === 'suspended') this.ctx.resume();
    const now = this.ctx.currentTime;
    
    // 5 verschiedene, sanfte Profile für Abwechslung
    const variation = Math.floor(Math.random() * 5);
    let startFreq, endFreq, dropTime, noiseStartFreq, noiseDur, waveType;

    if (variation === 0) { // Standard, mittlerer Wumms
        waveType = 'triangle'; startFreq = 100; endFreq = 20; dropTime = 0.3; noiseStartFreq = 500; noiseDur = 0.3;
    } else if (variation === 1) { // Tiefer, längerer Bass
        waveType = 'sine'; startFreq = 80; endFreq = 15; dropTime = 0.4; noiseStartFreq = 300; noiseDur = 0.4;
    } else if (variation === 2) { // Kurzes, hohles "Pock"
        waveType = 'triangle'; startFreq = 130; endFreq = 30; dropTime = 0.2; noiseStartFreq = 600; noiseDur = 0.2;
    } else if (variation === 3) { // Etwas "staubiger" (mehr weiches Rauschen)
        waveType = 'triangle'; startFreq = 90; endFreq = 25; dropTime = 0.35; noiseStartFreq = 700; noiseDur = 0.4;
    } else { // Schneller, knackiger Kick
        waveType = 'sine'; startFreq = 140; endFreq = 20; dropTime = 0.25; noiseStartFreq = 400; noiseDur = 0.25;
    }

    // Leichte organische Zufälligkeit nochmals obendrauf
    startFreq += Math.random() * 15 - 7;
    dropTime += Math.random() * 0.05;

    // 1. Weicher Bass-Wumms
    const osc = this.ctx.createOscillator(); 
    const gain = this.ctx.createGain();
    osc.type = waveType;
    osc.frequency.setValueAtTime(startFreq, now);
    osc.frequency.exponentialRampToValueAtTime(endFreq, now + dropTime);
    
    gain.gain.setValueAtTime(0.2, now);
    gain.gain.exponentialRampToValueAtTime(0.001, now + dropTime + 0.1);
    
    osc.connect(gain); 
    gain.connect(this.ctx.destination); 
    osc.start(now); 
    osc.stop(now + dropTime + 0.1);

    // 2. Mattes, kurzes Rauschen ("Puff"-Geräusch)
    const bufferSize = Math.floor(this.ctx.sampleRate * noiseDur);
    if (bufferSize > 0) {
        const buffer = this.ctx.createBuffer(1, bufferSize, this.ctx.sampleRate);
        const data = buffer.getChannelData(0);
        for (let i = 0; i < bufferSize; i++) { data[i] = Math.random() * 2 - 1; }
        
        const noise = this.ctx.createBufferSource();
        noise.buffer = buffer;
        
        const noiseFilter = this.ctx.createBiquadFilter();
        noiseFilter.type = 'lowpass';
        noiseFilter.frequency.setValueAtTime(noiseStartFreq, now);
        noiseFilter.frequency.exponentialRampToValueAtTime(80, now + noiseDur);
        
        const noiseGain = this.ctx.createGain();
        noiseGain.gain.setValueAtTime(0.08, now);
        noiseGain.gain.exponentialRampToValueAtTime(0.001, now + noiseDur);
        
        noise.connect(noiseFilter);
        noiseFilter.connect(noiseGain);
        noiseGain.connect(this.ctx.destination);
        noise.start(now);
    }
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
