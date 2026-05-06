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
                        this.initMapbox();
                        
                        if (!this.isLiveMode) {
                            this.toggleLiveMode(); // Start voice interaction automatically!
                        }
                        
                        setTimeout(() => {
                            if (window.funkiMap) {
                                window.funkiMap.resize();
                            }
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
                        this.destroyMapbox();
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

            initMapbox() {
                if (typeof mapboxgl === 'undefined') return;
                mapboxgl.accessToken = '{{ env("MAPBOX_TOKEN") }}';
                
                window.funkiMap = new mapboxgl.Map({
                    container: 'funki-mapbox-container',
                    style: this.activeMapStyle,
                    center: [13.4050, 52.5200], // Berlin
                    zoom: 4,
                    pitch: 45,
                    bearing: -17.6,
                    antialias: true
                });

                window.funkiMap.on('style.load', () => {
                    const currentStyle = window.funkiMap.getStyle();
                    // In 'Dark Cyber' mode, the container has a filter: hue-rotate(180deg). 
                    // So we color the water #cc4400 (orange/brown) so it hue-rotates into a beautiful deep cyan-blue!
                    if (currentStyle && currentStyle.sprite && currentStyle.sprite.includes('dark-v')) {
                        if (window.funkiMap.getLayer('water')) {
                            window.funkiMap.setPaintProperty('water', 'fill-color', '#cc4400');
                        }
                    }

                    const layers = currentStyle.layers || [];
                    let labelLayerId;
                    for (let i = 0; i < layers.length; i++) {
                        if (layers[i].type === 'symbol' && layers[i].layout['text-field']) {
                            labelLayerId = layers[i].id;
                            break;
                        }
                    }

                    if (window.funkiMap.getSource('composite')) {
                        window.funkiMap.addLayer({
                            'id': '3d-buildings',
                            'source': 'composite',
                            'source-layer': 'building',
                            'filter': ['==', 'extrude', 'true'],
                            'type': 'fill-extrusion',
                            'minzoom': 15,
                            'paint': {
                                'fill-extrusion-color': '#00f0ff',
                                'fill-extrusion-height': [
                                    'interpolate',
                                    ['linear'],
                                    ['zoom'],
                                    15, 0,
                                    15.05, ['get', 'height']
                                ],
                                'fill-extrusion-base': [
                                    'interpolate',
                                    ['linear'],
                                    ['zoom'],
                                    15, 0,
                                    15.05, ['get', 'min_height']
                                ],
                                'fill-extrusion-opacity': 0.6
                            }
                        }, labelLayerId);
                    }
                });

                window.funkiMap.on('load', () => {
                    // Rotations-Schleife entfernt
                });

                window.funkiMap.on('dragstart', () => {
                    if (window.mapRotateAnimationFrame) cancelAnimationFrame(window.mapRotateAnimationFrame);
                });

                window.flyToLocation = (lng, lat, zoom = 14, pitch = 60, markerText = '') => {
                    if(!window.funkiMap) return;
                    
                    // Stop any ongoing camera rotation so it doesn't cancel flyTo immediately
                    if (window.mapRotateAnimationFrame) {
                        cancelAnimationFrame(window.mapRotateAnimationFrame);
                        window.mapRotateAnimationFrame = null;
                    }
                    
                    window.funkiMap.flyTo({
                        center: [lng, lat],
                        zoom: zoom,
                        pitch: pitch,
                        essential: true, // Forces animation even if another one is running
                        bearing: Math.random() * 90 - 45,
                        duration: 4500, // Fixed smooth cinematic zoom duration instead of speed
                        easing: (t) => t * (2 - t) // EaseOutQuad for smooth arrival
                    });

                    // Resume rotation removed to allow user map control without interruption.
                    window.funkiMap.once('moveend', () => {
                        // done
                    });

                    if (window.funkiMarker) window.funkiMarker.remove();
                    
                    if (markerText) {
                        const el = document.createElement('div');
                        el.className = 'marker';
                        el.innerHTML = `<div class="bg-cyan-900/80 border border-cyan-400 text-cyan-100 text-[10px] font-mono px-2 py-1 rounded shadow-[0_0_15px_rgba(0,240,255,0.5)] animate-pulse whitespace-nowrap">${markerText}<\/div>`;
                        
                        window.funkiMarker = new mapboxgl.Marker(el)
                            .setLngLat([lng, lat])
                            .addTo(window.funkiMap);
                    }
                };

                window.markNewsEvents = (markers) => {
                    if (!window.funkiMap || !markers || !markers.length) return;
                    
                    if (window.newsMarkers) {
                        window.newsMarkers.forEach(m => m.remove());
                    }
                    window.newsMarkers = [];

                    if (window.mapRotateAnimationFrame) {
                        cancelAnimationFrame(window.mapRotateAnimationFrame);
                        window.mapRotateAnimationFrame = null;
                    }
                    
                    window.funkiMap.flyTo({
                        center: [markers[0].lng, markers[0].lat],
                        zoom: 4, 
                        pitch: 45,
                        essential: true,
                        duration: 4000
                    });

                    markers.forEach(marker => {
                        const el = document.createElement('div');
                        el.className = 'marker pointer-events-auto';
                        el.innerHTML = `
                            <div class="bg-red-900/80 border border-red-400 text-red-100 text-[10px] font-mono px-2 py-1 rounded shadow-[0_0_15px_rgba(255,0,0,0.5)] cursor-pointer hover:scale-110 transition-transform relative z-50">
                                <div class="absolute -inset-1 bg-red-500/30 rounded blur animate-pulse"></div>
                                <span class="relative z-10 font-bold">${marker.title}</span>
                            </div>
                        `;

                        el.addEventListener('click', (e) => {
                            e.stopPropagation();
                            if(marker.url) window.open(marker.url, '_blank');
                        });

                        const m = new mapboxgl.Marker(el)
                            .setLngLat([marker.lng, marker.lat])
                            .addTo(window.funkiMap);
                            
                        window.newsMarkers.push(m);
                    });
                };

                window.markPlaces = (markers) => {
                    if (!window.funkiMap || !markers || !markers.length) return;
                    
                    if (window.placeMarkers) {
                        window.placeMarkers.forEach(m => m.remove());
                    }
                    window.placeMarkers = [];

                    if (window.mapRotateAnimationFrame) {
                        cancelAnimationFrame(window.mapRotateAnimationFrame);
                        window.mapRotateAnimationFrame = null;
                    }
                    
                    window.funkiMap.flyTo({
                        center: [markers[0].lng, markers[0].lat],
                        zoom: 12, 
                        pitch: 45,
                        essential: true,
                        duration: 4000
                    });

                    markers.forEach(marker => {
                        const el = document.createElement('div');
                        el.className = 'marker pointer-events-auto group';
                        el.innerHTML = `
                            <div class="bg-cyan-900/80 border border-cyan-400 text-cyan-100 text-[10px] font-mono px-2 py-1 rounded shadow-[0_0_15px_rgba(0,240,255,0.5)] cursor-pointer hover:scale-110 transition-transform relative z-50">
                                <div class="absolute -inset-1 bg-cyan-500/30 rounded blur animate-pulse"></div>
                                <span class="relative z-10 font-bold">${marker.title}</span>
                                
                                <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 hidden group-hover:block w-48 bg-gray-900/95 border border-gray-700 rounded p-2 text-left shadow-2xl z-50 pointer-events-none">
                                    <div class="text-white font-bold mb-1 border-b border-gray-700 pb-1 whitespace-normal leading-tight">${marker.title}</div>
                                    <div class="text-gray-300 text-[9px] mb-1 whitespace-normal">${marker.location_name || ''}</div>
                                </div>
                            </div>
                        `;

                        el.addEventListener('click', (e) => {
                            e.stopPropagation();
                            window.funkiMap.flyTo({ center: [marker.lng, marker.lat], zoom: 16 });
                        });

                        const m = new mapboxgl.Marker(el)
                            .setLngLat([marker.lng, marker.lat])
                            .addTo(window.funkiMap);
                            
                        window.placeMarkers.push(m);
                    });
                };

                window.clearMarkers = () => {
                    if (window.funkiMarker) {
                        window.funkiMarker.remove();
                        window.funkiMarker = null;
                    }
                    if (window.newsMarkers) {
                        window.newsMarkers.forEach(m => m.remove());
                        window.newsMarkers = [];
                    }
                    if (window.placeMarkers) {
                        window.placeMarkers.forEach(m => m.remove());
                        window.placeMarkers = [];
                    }
                };
            },
            
            async fetchLiveFlightData() {
                if (!this.isFlightDataActive || !window.funkiMap) return;
                try {
                    // Using OpenSky API for live flights over Europe/World.
                    // For performance, we limit to the first 1000 flights returned, or a bounding box if we had one.
                    const response = await fetch('https://opensky-network.org/api/states/all');
                    if (!response.ok) return;
                    const data = await response.json();
                    
                    const features = [];
                    if (data && data.states) {
                        // Take up to 1000 planes to keep performance high
                        const limit = Math.min(data.states.length, 1000);
                        for (let i = 0; i < limit; i++) {
                            const state = data.states[i];
                            const lng = state[5];
                            const lat = state[6];
                            const onGround = state[8];
                            
                            if (lng !== null && lat !== null && !onGround) {
                                features.push({
                                    type: 'Feature',
                                    geometry: { type: 'Point', coordinates: [lng, lat] },
                                    properties: {
                                        callsign: state[1] ? state[1].trim() : 'UNKNOWN',
                                        country: state[2],
                                        velocity: state[9],
                                        heading: state[10]
                                    }
                                });
                            }
                        }
                    }
                    
                    if (window.funkiMap.getSource('live-flights')) {
                        window.funkiMap.getSource('live-flights').setData({
                            type: 'FeatureCollection',
                            features: features
                        });
                    }
                } catch (e) {
                    console.log('Flight data fetch error:', e);
                }
            },

            generateCrisisData() {
                if (!this.isFlightDataActive || !window.funkiMap) return;
                
                // Approximate coordinates of current major crisis zones
                const crises = [
                    { lng: 37.0, lat: 48.0, name: "Ukraine" },
                    { lng: 34.5, lat: 31.5, name: "Gaza / Israel" },
                    { lng: 30.0, lat: 15.0, name: "Sudan" },
                    { lng: 43.0, lat: 15.0, name: "Yemen" },
                    { lng: 28.0, lat: -4.0, name: "DR Congo" },
                    { lng: 96.0, lat: 21.0, name: "Myanmar" }
                ];
                
                const features = crises.map(c => ({
                    type: 'Feature',
                    geometry: { type: 'Point', coordinates: [c.lng, c.lat] },
                    properties: { name: c.name }
                }));
                
                if (window.funkiMap.getSource('live-crises')) {
                    window.funkiMap.getSource('live-crises').setData({
                        type: 'FeatureCollection',
                        features: features
                    });
                }
            },

            destroyMapbox() {
                if (window.funkiMap) {
                    window.funkiMap.remove();
                    window.funkiMap = null;
                }
            },
