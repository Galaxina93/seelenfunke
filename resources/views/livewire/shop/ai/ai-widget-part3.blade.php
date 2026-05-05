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
                };
            },

            stopSpeech() {
                if (this.chatAbortController) this.chatAbortController.abort();
                if (this.voiceAbortController) this.voiceAbortController.abort();
                
                if (window.funkiAudioPlayer) window.funkiAudioPlayer.pause();
                if (this.synthesis && this.synthesis.speaking) this.synthesis.cancel();
                
                this.isSpeaking = false;
                this.thinking = false;
            },

            speakResponse(text) {
                if (t3.isShuttingDown) return;
                
                if (this.agentTtsEnabled === false) {
                    this.isSpeaking = false;
                    return;
                }

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
                                     .replace(/\p{Emoji_Presentation}/gu, '')
                                     .replace(/\b([0-9\.]+)\s*(?:H|h)\b/g, '$1 Stunden')
                                     .replace(/\b([0-9\.]+)\s*[Mm](?=\s|$|[.,!?])/g, '$1 Minuten')
                                     .replace(/\b(\d{1,2})\.(\d{1,2})\.(\d{4})\b/g, '$1. $2. $3');

                if (this.recognition) {
                    this.recognition.onend = null; 
                    try { this.recognition.abort(); } catch(e) {}
                }

                this.isSpeaking = true;
                
                if (this.voiceAbortController) this.voiceAbortController.abort();
                this.voiceAbortController = new AbortController();

                this.fallbackToBrowserTTS(cleanText);
            },

            speakFeedback(text) {
                if (t3.isShuttingDown) return;
                if (this.agentTtsEnabled === false) return;

                if (this.synthesis) {
                    this.synthesis.cancel();
                }
                if (window.funkiAudioPlayer) {
                    window.funkiAudioPlayer.pause();
                    window.funkiAudioPlayer.currentTime = 0;
                }
                
                if (this.recognition) {
                    this.recognition.onend = null; 
                    try { this.recognition.abort(); } catch(e) {}
                }

                this.isSpeaking = true;
                
                if (this.voiceAbortController) this.voiceAbortController.abort();
                this.voiceAbortController = new AbortController();

                this.fallbackToBrowserTTS(text);
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

                // Dynamische Anpassung an den Agenten!
                utterance.rate = 1.0;
                utterance.pitch = 1.0;
                
                if (this.activeAgentName) {
                    const name = this.activeAgentName.toLowerCase();
                    if (name.includes('buchi') || name.includes('finanz')) {
                        utterance.pitch = 0.8; // Tiefere Stimme
                        utterance.rate = 0.95; // Etwas ruhiger
                    } else if (name.includes('marketi') || name.includes('marketing')) {
                        utterance.pitch = 1.2; // Höhere Stimme
                        utterance.rate = 1.1; // Etwas schneller
                    } else if (name.includes('system') || name.includes('admin')) {
                        utterance.pitch = 0.5; // Roboterhaft tief
                        utterance.rate = 1.0;
                    }
                }

                utterance.onend = () => {
                    this.isSpeaking = false;
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

                this.$watch('activeMapStyle', value => {
                    if (window.funkiMap) {
                        window.funkiMap.setStyle(value);
                    }
                });

                this.$watch('isFlightDataActive', value => {
                    if (!window.funkiMap) return;
                    if (value) {
                        // Flashing effect for sci-fi feel
                        if (!window.funkiMap.getSource('live-flights')) {
                            window.funkiMap.addSource('live-flights', { type: 'geojson', data: { type: 'FeatureCollection', features: [] } });
                            window.funkiMap.addLayer({
                                id: 'live-flights-layer',
                                type: 'circle',
                                source: 'live-flights',
                                paint: {
                                    'circle-radius': ['interpolate', ['linear'], ['zoom'], 4, 3, 10, 5, 15, 8],
                                    'circle-color': '#00f0ff',
                                    'circle-opacity': 0.8,
                                    'circle-stroke-width': 1,
                                    'circle-stroke-color': '#fff'
                                }
                            });
                            
                            window.funkiMap.addSource('live-ships', { type: 'geojson', data: { type: 'FeatureCollection', features: [] } });
                            window.funkiMap.addLayer({
                                id: 'live-ships-layer',
                                type: 'circle',
                                source: 'live-ships',
                                paint: {
                                    'circle-radius': ['interpolate', ['linear'], ['zoom'], 4, 2, 10, 4, 15, 7],
                                    'circle-color': '#ff00f0',
                                    'circle-opacity': 0.8,
                                    'circle-stroke-width': 1,
                                    'circle-stroke-color': '#fff'
                                }
                            });
                        }
                        
                        this.fetchLiveFlightData();
                        this.flightDataInterval = setInterval(() => this.fetchLiveFlightData(), 15000);
                        
                        this.generateMockShipData();
                        this.shipDataInterval = setInterval(() => this.generateMockShipData(), 5000);
                        
                        const openAudio = new Audio('/shop/ai/sounds/ai_click.mp3');
                        openAudio.volume = 0.4;
                        openAudio.play().catch(e=>console.log(e));
                    } else {
                        if (this.flightDataInterval) clearInterval(this.flightDataInterval);
                        if (this.shipDataInterval) clearInterval(this.shipDataInterval);
                        
                        if (window.funkiMap.getSource('live-flights')) {
                            window.funkiMap.getSource('live-flights').setData({ type: 'FeatureCollection', features: [] });
                        }
                        if (window.funkiMap.getSource('live-ships')) {
                            window.funkiMap.getSource('live-ships').setData({ type: 'FeatureCollection', features: [] });
                        }
                    }
                });

                this.$watch('isMapFocus', value => {
                    if (value) {
                        this.isMapMode = true;
                        const openAudio = new Audio('/shop/ai/sounds/map_open.mp3');
                        openAudio.volume = 0.6;
                        openAudio.play().catch(e=>console.log(e));
                        
                        // Start slow rotation if mapbox is loaded
                        if (window.funkiMap) {
                            if (window.mapRotateAnimationFrame) cancelAnimationFrame(window.mapRotateAnimationFrame);
                            const rotateCamera = () => {
                                if (window.funkiMap && this.isMapFocus) {
                                    window.funkiMap.rotateTo(window.funkiMap.getBearing() + 0.02, { duration: 0 });
                                    window.mapRotateAnimationFrame = requestAnimationFrame(rotateCamera);
                                }
                            };
                            rotateCamera();
                        }
                    } else {
                        this.isMapMode = false;
                        const closeAudio = new Audio('/shop/ai/sounds/map_close.mp3');
                        closeAudio.volume = 0.6;
                        closeAudio.play().catch(e=>console.log(e));
                    }
                });

                this.boundAnimate = () => this.animate();

                window.updateFunkiStatus = (state) => {
                    this.systemState = state;
                    if(t3.coreMesh) {
                        this.updateCoreColor();
                    }
                };

                window.addEventListener('toggle-mapfocus', (e) => {
                    console.log('AI EVENT RECEIVED: toggle-mapfocus', e.detail);
                    const detail = e.detail?.payload || (Array.isArray(e.detail) ? e.detail[0] : e.detail);
                    const val = detail?.active;
                    const active = (val === true || val === 'true' || val === 1 || val === '1');
                    console.log('Setting isMapFocus to:', active);
                    this.isMapFocus = active;
                    if (active) {
                        this.isMapMode = true;
                    }
                });

                window.addEventListener('map-fly-to', (e) => {
                    const detail = e.detail.payload || (Array.isArray(e.detail) ? e.detail[0] : e.detail);
                    if(typeof window.flyToLocation === 'function') {
                        window.flyToLocation(detail.lng, detail.lat, detail.zoom, detail.pitch, detail.markerText);
                    }
                    this.isMapFocus = true;
                    this.isMapMode = true;
                });

                window.addEventListener('ai-show-news', (e) => {
                    const detail = e.detail.payload || (Array.isArray(e.detail) ? e.detail[0] : e.detail);
                    const articles = detail?.articles || [];
                    const container = document.getElementById('news-panel-content');
                    if (!container) return;
                    
                    container.innerHTML = '';
                    if (articles.length === 0) {
                        container.innerHTML = '<p class="text-xs text-gray-500 font-mono">Keine aktuellen Daten empfangen.</p>';
                    } else {
                        articles.forEach(a => {
                            const imgHtml = a.image ? `<div class="relative overflow-hidden rounded-lg mb-3 group-hover:shadow-[0_0_15px_rgba(0,240,255,0.3)] transition-all duration-300"><img src="${a.image}" class="w-full h-28 object-cover opacity-80 group-hover:scale-105 group-hover:opacity-100 transition-all duration-500" onerror="this.style.display='none'"><div class="absolute inset-0 bg-gradient-to-t from-black/80 to-transparent"></div></div>` : '';
                            const html = `
                                <div class="relative border border-cyan-500/10 bg-gradient-to-br from-black/60 to-cyan-900/10 p-4 rounded-xl hover:bg-cyan-900/20 hover:border-cyan-400/30 transition-all duration-300 group overflow-hidden">
                                    <div class="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAiIGhlaWdodD0iNDAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PGRlZnM+PHBhdHRlcm4gaWQ9ImdyaWQiIHdpZHRoPSI0MCIgaGVpZ2h0PSI0MCIgcGF0dGVyblVuaXRzPSJ1c2VyU3BhY2VPblVzZSI+PHBhdGggZD0iTSA0MCAwIEwgMCAwIDAgNDAiIGZpbGw9Im5vbmUiIHN0cm9rZT0icmdiYSgwLCAyNDAsIDI1NSwgMC4wNSkiIHN0cm9rZS13aWR0aD0iMSIvPjwvcGF0dGVybj48L2RlZnM+PHJlY3Qgd2lkdGg9IjEwMCUiIGhlaWdodD0iMTAwJSIgZmlsbD0idXJsKCNncmlkKSIvPjwvc3ZnPg==')] opacity-30"></div>
                                    <div class="relative z-10">
                                        ${imgHtml}
                                        <h4 class="text-sm text-cyan-50 font-bold mb-2 leading-tight drop-shadow-md"><a href="${a.url || '#'}" target="_blank" class="hover:text-cyan-300 transition-colors">${a.title}</a></h4>
                                        <p class="text-[11px] text-gray-400/90 font-mono leading-relaxed line-clamp-3">${a.description || ''}</p>
                                        <div class="mt-3 flex items-center justify-between text-[9px] text-cyan-500/80 uppercase tracking-widest font-bold">
                                            <span><span class="text-cyan-300/50 mr-1">SRC:</span>${a.source || 'Intercept'}</span>
                                            <span class="px-2 py-0.5 rounded-full bg-cyan-900/30 border border-cyan-500/20">${a.date || 'Live'}</span>
                                        </div>
                                    </div>
                                </div>
                            `;
                            container.insertAdjacentHTML('beforeend', html);
                        });
                    }
                    this.showNewsPanel = true;
                });

                window.addEventListener('hide-news-panel', (e) => {
                    this.showNewsPanel = false;
                });

                window.addEventListener('toggle-livedata', (e) => {
                    const detail = e.detail.payload || (Array.isArray(e.detail) ? e.detail[0] : e.detail);
                    const val = detail.active;
                    const active = (val === true || val === 'true' || val === 1 || val === '1');
                    if (this.isFlightDataActive !== active) {
                        this.isFlightDataActive = active;
                    }
                });

                this.initSpeech();

                document.addEventListener('livewire:navigating', () => {
                    this.destroy();
                    localStorage.setItem('funki_isOpen', 'false');
                });

                window.addEventListener('beforeunload', () => {
                    localStorage.setItem('funki_isOpen', 'false');
                });

                document.addEventListener('livewire:navigated', () => {
                    // Do nothing on navigate since continuous mode is gone
                });
            },

            destroy() {
                if (this.recognition) {
                    // Fully detach to prevent Chrome from holding the media stream
                    this.recognition.onstart = null;
                    this.recognition.onresult = null;
                    this.recognition.onerror = null;
                    this.recognition.onspeechend = null;
                    this.recognition.onend = null;
                    try { this.recognition.abort(); } catch(e) {}
                    try { this.recognition.stop(); } catch(e) {}
                    this.recognition = null;
                }
                if (this.watchdogTimer) clearInterval(this.watchdogTimer);
            },

            startSafeRecognition(delay = 1000) {
                if (delay === 0) {
                    if (this.recognition && !t3.isShuttingDown) {
                        if (this.isRecognizing) return;
                        try { 
                            this.recognition.start(); 
                        } catch(e) {}
                    }
                    return;
                }
                setTimeout(() => {
                    if (this.recognition && !t3.isShuttingDown) {
                        if (this.isRecognizing) return; // Prevent InvalidStateError entirely
                        try { 
                            this.recognition.start(); 
                            console.log('Orb Voice: continuous listening started.');
                        } catch(e) {
                            if (e.name !== 'InvalidStateError') {
                                console.error('Orb Voice SDK failed to start:', e);
                                setTimeout(() => { 
                                    if(!this.isRecognizing) {
                                        try { this.recognition.start(); } catch(e2) {} 
                                    }
                                }, 1500);
                            }
                        }
                    }
                }, delay);
            },

            initSpeech() {
                const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
                if (!SpeechRecognition) {
                    console.error("Speech Recognition API is not supported in this browser.");
                    return;
                }

                if (this.recognition) {
                    this.recognition.onstart = null;
                    this.recognition.onend = null;
                    try { this.recognition.abort(); } catch(e) {}
                }

                this.isRecognizing = false;
                this.recognition = new SpeechRecognition();
                this.recognition.lang = 'de-DE';
                this.recognition.continuous = true;
                this.recognition.interimResults = false;

                this.recognition.onstart = () => {
                    this.isRecognizing = true;
                    this.lastActivityTime = Date.now();
                    this.restartCount = 0;
                };

                this.recognition.onspeechend = () => {
                    this.lastActivityTime = Date.now();
                };

                this.recognition.onerror = (e) => {
                    this.isRecognizing = false;
                    if (e.error === 'not-allowed' || e.error === 'audio-capture') {
                        this.listening = false;
                        if (window.t3 && window.t3.coreMesh) this.updateCoreColor();
                    } else {
                        this.lastActivityTime = 0; 
                    }
                };

                this.recognition.onend = () => {
                    this.isRecognizing = false;
                    this.listening = false;
                    if (window.t3 && window.t3.coreMesh) this.updateCoreColor();
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

                        this.listening = false;
                        this.recognition.stop();
                        this.sendToAI(transcript);
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
