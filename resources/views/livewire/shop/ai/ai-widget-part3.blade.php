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
                                type: 'symbol',
                                source: 'live-flights',
                                layout: {
                                    'icon-image': 'airport-15',
                                    'icon-size': 1.0,
                                    'icon-rotate': ['get', 'heading'],
                                    'icon-allow-overlap': true,
                                    'icon-ignore-placement': true
                                },
                                paint: {
                                    'icon-color': '#ffffff',
                                    'icon-halo-color': '#333333',
                                    'icon-halo-width': 1
                                }
                            });
                            
                            window.funkiMap.addSource('live-crises', { type: 'geojson', data: { type: 'FeatureCollection', features: [] } });
                            window.funkiMap.addLayer({
                                id: 'live-crises-layer',
                                type: 'circle',
                                source: 'live-crises',
                                paint: {
                                    'circle-radius': ['interpolate', ['linear'], ['zoom'], 2, 20, 10, 80],
                                    'circle-color': '#ff3300',
                                    'circle-opacity': 0.4,
                                    'circle-blur': 0.8
                                }
                            });
                            
                            // Map Events for Live Data
                            window.funkiMap.on('click', 'live-flights-layer', (e) => {
                                const coordinates = e.features[0].geometry.coordinates.slice();
                                const p = e.features[0].properties;
                                new mapboxgl.Popup({ closeButton: false })
                                    .setLngLat(coordinates)
                                    .setHTML(`<div class="text-[10px] font-mono"><strong class="text-emerald-500 uppercase">Flug: ${p.callsign || 'Unbekannt'}<\/strong><br><span class="text-gray-300">Herkunft: ${p.origin_country || 'Unbekannt'}<br>Höhe: ${Math.round(p.geo_altitude)}m<br>V: ${Math.round(p.velocity * 3.6)} km/h<\/span><\/div>`)
                                    .addTo(window.funkiMap);
                            });
                            window.funkiMap.on('mouseenter', 'live-flights-layer', () => { window.funkiMap.getCanvas().style.cursor = 'pointer'; });
                            window.funkiMap.on('mouseleave', 'live-flights-layer', () => { window.funkiMap.getCanvas().style.cursor = ''; });

                            window.funkiMap.on('click', 'live-crises-layer', (e) => {
                                const coordinates = e.features[0].geometry.coordinates.slice();
                                const p = e.features[0].properties;
                                new mapboxgl.Popup({ closeButton: false })
                                    .setLngLat(coordinates)
                                    .setHTML(`<div class="text-[10px] font-mono"><strong class="text-red-500 uppercase">Krisenherd: ${p.name || 'Unbekannt'}<\/strong><br><span class="text-gray-300">${p.description || 'Keine Details verfügbar'}<\/span><\/div>`)
                                    .addTo(window.funkiMap);
                            });
                            window.funkiMap.on('mouseenter', 'live-crises-layer', () => { window.funkiMap.getCanvas().style.cursor = 'pointer'; });
                            window.funkiMap.on('mouseleave', 'live-crises-layer', () => { window.funkiMap.getCanvas().style.cursor = ''; });
                        }
                        
                        this.fetchLiveFlightData();
                        this.flightDataInterval = setInterval(() => this.fetchLiveFlightData(), 15000);
                        
                        this.generateCrisisData();
                        
                        const openAudio = new Audio('/shop/ai/sounds/ai_click.mp3');
                        openAudio.volume = 0.4;
                        openAudio.play().catch(e=>console.log(e));
                    } else {
                        if (this.flightDataInterval) clearInterval(this.flightDataInterval);
                        
                        if (window.funkiMap.getSource('live-flights')) {
                            window.funkiMap.getSource('live-flights').setData({ type: 'FeatureCollection', features: [] });
                        }
                        if (window.funkiMap.getSource('live-crises')) {
                            window.funkiMap.getSource('live-crises').setData({ type: 'FeatureCollection', features: [] });
                        }
                    }
                });

                this.$watch('isMapFocus', value => {
                    if (value) {
                        this.isMapMode = true;
                        const openAudio = new Audio('/shop/ai/sounds/map_open.mp3');
                        openAudio.volume = 0.6;
                        openAudio.play().catch(e=>console.log(e));
                        // Rotations-Schleife entfernt, da sie den flyTo-Zoom blockierte.
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
                    const detail = e.detail?.payload || (Array.isArray(e.detail) ? e.detail[0] : e.detail);
                    const articles = detail?.articles || [];
                    
                    if (articles.length === 0) return;
                    
                    if (detail?.append) {
                        this.newsWidgets = [...this.newsWidgets, ...articles];
                    } else {
                        // Assign to Alpine state for 2D UI rendering
                        this.newsWidgets = articles;
                    }
                });

                window.addEventListener('hide-news-panel', (e) => {
                    this.newsWidgets = [];
                });

                window.addEventListener('hide-news-widget', (e) => {
                    const idx = e.detail?.index;
                    if (idx !== undefined && idx !== null) {
                        const targetIdx = parseInt(idx) - 1; // 1-based to 0-based
                        if (targetIdx >= 0 && targetIdx < this.newsWidgets.length) {
                            this.newsWidgets.splice(targetIdx, 1);
                        }
                        return;
                    }

                    const titleToHide = e.detail?.title?.toLowerCase();
                    if (!titleToHide) return;
                    
                    this.newsWidgets = this.newsWidgets.filter(w => {
                        const searchStr = (w.title + ' ' + (w.description || '')).toLowerCase();
                        return !searchStr.includes(titleToHide);
                    });
                });

                window.addEventListener('ai-show-youtube', (e) => {
                    const detail = e.detail?.payload || (Array.isArray(e.detail) ? e.detail[0] : e.detail);
                    const videos = detail?.articles || [];
                    
                    if (videos.length === 0) return;
                    
                    // Always append for pool effect
                    this.youtubeWidgets = [...this.youtubeWidgets, ...videos];
                });

                window.addEventListener('hide-youtube-widget', (e) => {
                    const idx = e.detail?.index;
                    if (idx !== undefined && idx !== null) {
                        const targetIdx = parseInt(idx) - 1;
                        if (targetIdx >= 0 && targetIdx < this.youtubeWidgets.length) {
                            this.youtubeWidgets.splice(targetIdx, 1);
                        }
                        return;
                    }
                    this.youtubeWidgets = [];
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
                    if (this.recognition && !t3.isShuttingDown && (this.listening || this.isLiveMode)) {
                        if (this.isRecognizing) return;
                        try { 
                            this.recognition.start(); 
                        } catch(e) {}
                    }
                    return;
                }
                setTimeout(() => {
                    if (this.recognition && !t3.isShuttingDown && (this.listening || this.isLiveMode)) {
                        if (this.isRecognizing) return; // Prevent InvalidStateError entirely
                        try { 
                            this.recognition.start(); 
                            console.log('Orb Voice: continuous listening started.');
                        } catch(e) {
                            if (e.name !== 'InvalidStateError') {
                                console.error('Orb Voice SDK failed to start:', e);
                                setTimeout(() => { 
                                    if(!this.isRecognizing && (this.listening || this.isLiveMode)) {
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
