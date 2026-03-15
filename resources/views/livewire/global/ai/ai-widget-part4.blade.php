            async openFunkiView(isRestore = false) {
                this.isAudioMuted = localStorage.getItem('funki_isAudioMuted') !== null ? localStorage.getItem('funki_isAudioMuted') === 'true' : false; 
                localStorage.setItem('funki_isOpen', 'true');
                this.showFunkiView = true;
                t3.isShuttingDown = false;
                t3.shutdownTime = null;

                const startAnimation = () => {
                    if (t3.isShuttingDown) return; 

                    if (!isRestore) {
                        const initAudio = document.getElementById('audio-funki-init');
                        if(initAudio) {
                            initAudio.currentTime = 0;
                            initAudio.volume = 0.8;
                            initAudio.play().catch(e => console.log("Audio play prevented", e));
                        }
                    }

                    t3.bgAudio = document.getElementById('audio-funki-background');
                    t3.ambientAudio = document.getElementById('audio-funki-default-ambient');

                    if (t3.ambientAudio) {
                        t3.ambientAudio.volume = 0;
                        t3.ambientAudio.play().catch(e => console.log(e));
                    }

                    if (t3.bgAudio) {
                        t3.bgAudio.muted = this.isAudioMuted;
                        t3.bgAudio.volume = 0; 
                        t3.bgAudio.play().catch(e => console.log("Audio play prevented", e));
                        setTimeout(() => {
                            let volInt = setInterval(() => {
                                if(!this.showFunkiView || t3.isShuttingDown) { clearInterval(volInt); return; }

                                if (this.isAudioMuted) {
                                    if (t3.ambientAudio && t3.ambientAudio.volume < 0.05) {
                                        t3.ambientAudio.volume = Math.min(t3.ambientAudio.volume + 0.005, 0.05);
                                    }
                                } else {
                                    let targetVol = this.bgVolume / 100;
                                    if(t3.bgAudio.volume < targetVol) {
                                        t3.bgAudio.volume = Math.min(t3.bgAudio.volume + 0.01, targetVol);
                                    } else clearInterval(volInt);
                                }
                            }, 50);
                        }, 500);
                    }

                    t3.heartbeatAudio = document.getElementById('audio-funki-heartbeat');
                    if (t3.heartbeatAudio) {
                        t3.heartbeatAudio.volume = 0;
                        t3.heartbeatAudio.playbackRate = 1.0;
                        t3.heartbeatAudio.play().catch(e => console.log("Audio play prevented", e));
                    }

                    this.$nextTick(() => {
                        this.initThreeJS();
                        setTimeout(() => {
                            this.toggleSpeech();
                        }, 500);
                    });
                };

                if (typeof THREE === 'undefined') {
                    await new Promise((resolve, reject) => {
                        const script = document.createElement('script');
                        script.src = "https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js";
                        script.onload = resolve;
                        script.onerror = reject;
                        document.head.appendChild(script);
                    });
                }

                if (typeof THREE.OrbitControls === 'undefined') {
                    await new Promise((resolve, reject) => {
                        const script = document.createElement('script');
                        script.src = "https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/controls/OrbitControls.js";
                        script.onload = resolve;
                        script.onerror = reject;
                        document.head.appendChild(script);
                    });
                }

                if (typeof THREE.CSS2DRenderer === 'undefined') {
                    await new Promise((resolve, reject) => {
                        const script = document.createElement('script');
                        script.src = "https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/renderers/CSS2DRenderer.js";
                        script.onload = resolve;
                        script.onerror = reject;
                        document.head.appendChild(script);
                    });
                }

                startAnimation();
            },

            closeFunkiView() {
                if (t3.isShuttingDown) return; 
                localStorage.setItem('funki_isOpen', 'false');
                t3.isShuttingDown = true;

                this.listening = false; 

                const pulseAudio = document.getElementById('audio-funki-pulse');
                if(pulseAudio) {
                    pulseAudio.pause();
                    pulseAudio.currentTime = 0;
                }

                this.continuousMode = false;
                if (this.recognition) this.recognition.stop();
                if (window.funkiAudioPlayer) window.funkiAudioPlayer.pause();
                if (this.synthesis) this.synthesis.cancel();
                t3.shutdownTime = performance.now();
                document.body.style.cursor = 'default';

                const shutdownAudio = document.getElementById('audio-funki-shutdown');
                if (shutdownAudio) {
                    shutdownAudio.currentTime = 0;
                    shutdownAudio.volume = 0.8;
                    shutdownAudio.play().catch(e => console.log(e));
                }

                if (t3.heartbeatAudio) {
                    let hbInt = setInterval(() => {
                        if (t3.heartbeatAudio.volume > 0.05) t3.heartbeatAudio.volume -= 0.05;
                        else {
                            t3.heartbeatAudio.volume = 0;
                            t3.heartbeatAudio.pause();
                            t3.heartbeatAudio.currentTime = 0;
                            clearInterval(hbInt);
                        }
                    }, 50);
                }

                const globalBgAudio = document.getElementById('audio-funki-background');
                if (globalBgAudio && !globalBgAudio.paused) {
                    let bgInt = setInterval(() => {
                        if (globalBgAudio.volume > 0.05) globalBgAudio.volume -= 0.05;
                        else {
                            globalBgAudio.volume = 0;
                            globalBgAudio.pause();
                            globalBgAudio.currentTime = 0;
                            clearInterval(bgInt);
                        }
                    }, 50);
                }

                const globalAmbAudio = document.getElementById('audio-funki-default-ambient');
                if (globalAmbAudio && !globalAmbAudio.paused) {
                    let ambInt = setInterval(() => {
                        if (globalAmbAudio.volume > 0.05) globalAmbAudio.volume -= 0.05;
                        else {
                            globalAmbAudio.volume = 0;
                            globalAmbAudio.pause();
                            globalAmbAudio.currentTime = 0;
                            clearInterval(ambInt);
                        }
                    }, 50);
                }

                setTimeout(() => {
                    this.showFunkiView = false; 

                    setTimeout(() => {
                        this.destroyThreeJS();
                        document.body.style.overflow = 'auto'; 
                    }, 1000);
                }, 3500);
            }, 

            toggleBackgroundAudio() {
                this.isAudioMuted = !this.isAudioMuted;
                localStorage.setItem('funki_isAudioMuted', this.isAudioMuted);
                this.enforceAudioMuteState();
            },

            enforceAudioMuteState() {
                const bgAudio = document.getElementById('audio-funki-background');
                const ambientAudio = document.getElementById('audio-funki-default-ambient');

                if (bgAudio) {
                    bgAudio.muted = this.isAudioMuted;
                    if (!this.isAudioMuted && !t3.isShuttingDown) {
                        bgAudio.volume = this.bgVolume / 100;
                    }
                }

                if (ambientAudio) {
                    if (this.isAudioMuted && !t3.isShuttingDown) {
                        ambientAudio.volume = 0.05;
                    } else {
                        ambientAudio.volume = 0;
                    }
                }
            },
