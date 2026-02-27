if (typeof window.optInScreen === 'undefined') {
window.optInScreen = function() {
return {
mouseX: 0,
mouseY: 0,
isHovering: false,
isWarping: false,
isActivating: false,
phase: 0,
flash: false,

handleMouse(e) {
this.isHovering = true;
const rect = this.$el.getBoundingClientRect();
this.mouseX = e.clientX - rect.left;
this.mouseY = e.clientY - rect.top;
},
resetMouse() {
this.isHovering = false;
},
triggerEpicStart() {
this.isWarping = true;
this.isActivating = true;

// Sagt dem HTML, dass es sich ausblenden soll
window.dispatchEvent(new CustomEvent('warp-started'));

// Löst die Explosionseffekt-Funktion aus dem Universum-Skript aus (falls vorhanden)
if (window.spawnEpicExplosion) window.spawnEpicExplosion(this.mouseX, this.mouseY);

// Sagt dem Universum, dass es auf Lichtgeschwindigkeit beschleunigen soll
if (window.startWarpSpeed) window.startWarpSpeed();

setTimeout(() => {
this.phase = 1;
}, 50);

// Nach 2.5 Sekunden Warp-Flug laden wir die neue Seite im Backend
setTimeout(() => {
window.sessionStorage.setItem('funki_just_activated', 'true');
this.$wire.optIn(); // Livewire v3 Syntax
}, 2500);
}
};
};
}
