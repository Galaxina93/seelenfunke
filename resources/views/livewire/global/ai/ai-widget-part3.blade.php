            playAudioBase64(base64str) {
                if (window.funkiAudioPlayer) {
                    window.funkiAudioPlayer.pause();
                }
                const audio = new Audio("data:audio/mp3;base64," + base64str);

                audio.playbackRate = 1.0;
                this.isSpeaking = true;

                audio.play().catch(e => {
                    console.error("Audio play prevented:", e);
                    this.isSpeaking = false;
                });

                window.funkiAudioPlayer = audio;

                audio.onended = () => {
                    this.isSpeaking = false;
                    if (this.continuousMode) {
                        this.listening = true;
                        try { this.recognition.start(); } catch(e) {}
                    }
                };
            },

            stopSpeech() {
                if (window.funkiAudioPlayer) window.funkiAudioPlayer.pause();
                if (this.synthesis && this.synthesis.speaking) this.synthesis.cancel();
                this.isSpeaking = false;
                if (this.continuousMode && !this.listening) {
                    this.listening = true;
                    try { this.recognition.start(); } catch(e) {}
                }
            },

            speakResponse(text) {
                if (t3.isShuttingDown) return;

                if (this.synthesis) {
                    this.synthesis.cancel();
                }
                if (window.funkiAudioPlayer) {
                    window.funkiAudioPlayer.pause();
                    window.funkiAudioPlayer.currentTime = 0;
                }

                let cleanText = text.replace(/\[COMPONENT\].*?\[\/COMPONENT\]/gs, 'Visualisiere Komponente.');
                cleanText = cleanText.replace(/\[NAVIGATE\].*?\[\/NAVIGATE\]/gs, 'Navigiere dorthin.');
                cleanText = cleanText.replace(/\[TEXTBOX\].*?\[\/TEXTBOX\]/gs, 'Zeige Daten im Textfeld.');
                cleanText = cleanText.replace(/\[EVENT\].*?\[\/EVENT\]/gs, '');
                cleanText = cleanText.replace(/[*_#`~>]/g, '')
                                     .replace(/%0?0|\0/g, '')
                                     .replace(/\b([0-9\.]+)\s*(?:H|h)\b/g, '$1 Stunden')
                                     .replace(/\b([0-9\.]+)\s*[Mm](?=\s|$|[.,!?])/g, '$1 Minuten')
                                     .replace(/\b(\d{1,2})\.(\d{1,2})\.(\d{4})\b/g, '$1. $2. $3');

                if (this.isMobile && this.recognition) {
                    this.recognition.onend = null; 
                    try { this.recognition.abort(); } catch(e) {}
                }

                this.isSpeaking = true;

                fetch('/api/ai/voice', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'audio/mpeg',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                    },
                    body: JSON.stringify({ text: cleanText })
                })
                .then(response => {
                    if (!response.ok) throw new Error(`API Error: ${response.status}`);
                    return response.blob();
                })
                .then(blob => {
                    const audioUrl = URL.createObjectURL(blob);
                    window.funkiAudioPlayer = new Audio(audioUrl);
                    window.funkiAudioPlayer.playsInline = true;
                    window.funkiAudioPlayer.setAttribute('webkit-playsinline', 'true');
                    window.funkiAudioPlayer.playbackRate = 1.0;

                    window.funkiAudioPlayer.onended = () => {
                        this.isSpeaking = false;
                        if (this.continuousMode && !t3.isShuttingDown) {
                            if (this.isMobile && this.listening) {
                                if (this.recognition) {
                                    this.recognition.onend = () => { 
                                        if (this.continuousMode) this.restartRecognition(); 
                                    };
                                }
                                this.restartRecognition();
                            }
                        }
                        URL.revokeObjectURL(audioUrl);
                    };
                    window.funkiAudioPlayer.play().catch(e => {
                        this.fallbackToBrowserTTS(cleanText);
                    });
                })
                .catch(error => {
                    this.fallbackToBrowserTTS(cleanText);
                });
            },

            fallbackToBrowserTTS(cleanText) {
                if (!this.synthesis) {
                    this.isSpeaking = false;
                    return;
                }
                const utterance = new SpeechSynthesisUtterance(cleanText);
                this.isSpeaking = true;
                utterance.lang = 'de-DE';

                const voices = this.synthesis.getVoices();
                const germanVoice = voices.find(v => v.lang === 'de-DE' && (v.name.includes('Google') || v.name.includes('Neural')));
                if (germanVoice) {
                    utterance.voice = germanVoice;
                }

                utterance.rate = 1.05;
                utterance.pitch = 0.95;

                utterance.onend = () => {
                    this.isSpeaking = false;
                    if (this.continuousMode && !t3.isShuttingDown && this.isMobile && this.listening) {
                        if (this.recognition) {
                            this.recognition.onend = () => { 
                                if (this.continuousMode) this.restartRecognition(); 
                            };
                        }
                        this.restartRecognition();
                    }
                };

                this.synthesis.speak(utterance);
            },
            // --- END AI VOICE CHAT LOGIC ---

            get displayState() {
                if (this.systemState === true || this.systemState === 'good') return 'GOOD';
                if (this.systemState === 'warning') return 'WARNING';
                if (this.systemState === false || this.systemState === 'error') return 'ERROR';
                return String(this.systemState).toUpperCase();
            },

            get stateColor() {
                if (this.systemState === true || this.systemState === 'good') return 'good';
                if (this.systemState === 'warning') return 'warning';
                if (this.systemState === false || this.systemState === 'error') return 'error';
                return 'good';
            },

            init() {
                if (localStorage.getItem('funki_isOpen') === 'true') {
                    setTimeout(() => {
                        this.openFunkiView(true);
                    }, 500);
                }

                this.$watch('bgVolume', value => {
                    localStorage.setItem('funki_bgVolume', value);
                    const bgAudio = document.getElementById('audio-funki-background');
                    if(bgAudio && !this.isAudioMuted && !t3.isShuttingDown) {
                        bgAudio.volume = value / 100;
                    }
                });

                this.$watch('continuousMode', value => {
                    localStorage.setItem('funki_continuousMode', value);
                });

                this.$watch('requireWakeWord', value => {
                    localStorage.setItem('funki_requireWakeWord', value);
                });

                this.boundAnimate = () => this.animate();

                window.updateFunkiStatus = (state) => {
                    this.systemState = state;
                    if(t3.coreMesh) {
                        this.updateCoreColor();
                    }
                };

                this.initSpeech();
            },

            resetWatchdog() {
                if (this.watchdogTimer) clearInterval(this.watchdogTimer);
                this.watchdogTimer = setInterval(() => {
                    if (this.isMobile && this.isOutputActive()) return; 
                    
                    if (this.listening && this.continuousMode && (Date.now() - this.lastActivityTime > 45000)) {
                        console.log('Orb Voice watchdog restarting mic...');
                        if (this.recognition) {
                            this.recognition.onend = null;
                            try { this.recognition.abort(); } catch(e) {}
                            this.recognition = null;
                        }
                        this.initSpeech();
                        try { this.recognition.start(); } catch(e) {}
                        this.lastActivityTime = Date.now();
                    }
                }, 10000);
            },

            restartRecognition() {
                if (!this.listening || !this.continuousMode || t3.isShuttingDown) return;
                if (this.isMobile && this.isOutputActive()) return; 
                
                
                if (Date.now() - this.lastRestartTime < 1000) {
                    this.restartCount++;
                    if (this.restartCount > 5) {
                        console.warn('Voice restart loop detected in Orb, aborting...');
                        this.listening = false;
                        if (window.t3 && window.t3.coreMesh) this.updateCoreColor();
                        return;
                    }
                } else {
                    this.restartCount = 0;
                }
                this.lastRestartTime = Date.now();

                setTimeout(() => {
                    if (!this.listening || !this.continuousMode || t3.isShuttingDown) return;
                    try {
                        if (!this.recognition) this.initSpeech();
                        this.recognition.start();
                    } catch(e) {
                        if (e.name !== 'InvalidStateError') {
                            this.listening = false;
                            if (window.t3 && window.t3.coreMesh) this.updateCoreColor();
                        }
                    }
                }, 300);
            },

            initSpeech() {
                const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
                if (!SpeechRecognition) {
                    console.error("Speech Recognition API is not supported in this browser.");
                    return;
                }

                if (this.recognition) {
                    this.recognition.onend = null;
                    try { this.recognition.abort(); } catch(e) {}
                }

                this.recognition = new SpeechRecognition();
                this.recognition.lang = 'de-DE';
                this.recognition.continuous = true;
                this.recognition.interimResults = false;

                this.recognition.onstart = () => {
                    this.lastActivityTime = Date.now();
                    this.restartCount = 0;
                };

                this.recognition.onspeechend = () => {
                    this.lastActivityTime = Date.now();
                };

                this.recognition.onerror = (e) => {
                    if (e.error === 'not-allowed' || e.error === 'audio-capture') {
                        this.listening = false;
                        if (window.t3 && window.t3.coreMesh) this.updateCoreColor();
                    } else {
                        this.lastActivityTime = 0; 
                    }
                };

                this.recognition.onend = () => {
                    if (!this.continuousMode) {
                        this.listening = false;
                        if (window.t3 && window.t3.coreMesh) this.updateCoreColor();
                        return;
                    }
                    this.restartRecognition();
                };

                this.recognition.onresult = (event) => {
                    this.lastActivityTime = Date.now();
                    if (window.t3) window.t3.lastActivityTime = performance.now();
                    const transcript = event.results[event.results.length - 1][0].transcript.trim();

                    if (transcript.length > 0) {
                        const textToLower = transcript.toLowerCase();

                        const stopWords = ['stop', 'stopp', 'halt', 'ruhe', 'aufhören', 'pause', 'schweig', 'psst', 'leise'];
                        if (this.isOutputActive()) {
                            if (stopWords.some(w => textToLower === w || textToLower.startsWith(w) || textToLower.endsWith(w))) {
                                console.log('Interrupted by user:', transcript);
                                this.stopSpeech();
                                return; 
                            }
                        }

                        if (this.isOutputActive()) return;

                        if (!this.continuousMode) {
                            this.listening = false;
                            this.recognition.stop();
                            this.sendToAI(transcript);
                        } else {
                            if (this.requireWakeWord) {
                                const wakeWords = ['funkira', 'funki', 'kira'];

                                if (wakeWords.some(w => textToLower.includes(w))) {
                                    console.log('Funkira wake word heard: ', transcript);
                                    this.sendToAI(transcript);
                                } else {
                                    console.log('Funkira ignored: ', transcript);
                                }
                            } else {
                                console.log('Funkira (No Wake-Word) heard: ', transcript);
                                this.sendToAI(transcript);
                            }
                        }
                    }
                };
            },

            playUnclickSound() {
                const unclickAudio = document.getElementById('audio-funki-unclick');
                if (unclickAudio) {
                    unclickAudio.currentTime = 0;
                    unclickAudio.volume = 0.6;
                    unclickAudio.play().catch(e => console.log(e));
                }
            },
