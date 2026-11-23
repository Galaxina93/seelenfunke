            async openFunkiView(isRestore = false) {
                if (t3.closeTimerOuter) {
                    clearTimeout(t3.closeTimerOuter);
                    t3.closeTimerOuter = null;
                }
                if (t3.closeTimerInner) {
                    clearTimeout(t3.closeTimerInner);
                    t3.closeTimerInner = null;
                }
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
                            setTimeout(() => {
                                initAudio.play().catch(e => console.log("Audio play prevented", e));
                            }, 400);
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

                                if (this.isMobile && this.isLiveMode) {
                                    // Do nothing while mobile live mode is active
                                    return;
                                }

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
                        try {
                            this.initThreeJS();
                        } catch (e) {
                            console.error('Funki 3D initThreeJS error:', e);
                        }
                        if (this.isMapMode || this.isMapFocus) {
                            this.ensureMapboxReady();
                        }
                        
                        if (!this.isLiveMode) {
                            this.toggleLiveMode(); // Start voice interaction automatically!
                        }
                    });
                };

                const loadVendorScript = (globalCheck, src) => {
                    if (globalCheck()) return Promise.resolve();
                    const existing = document.querySelector(`script[src="${src}"]`);
                    if (existing) {
                        return new Promise((resolve) => {
                            if (globalCheck()) return resolve();
                            existing.addEventListener('load', () => resolve(), { once: true });
                            existing.addEventListener('error', () => resolve(), { once: true });
                        });
                    }
                    return new Promise((resolve, reject) => {
                        const script = document.createElement('script');
                        script.src = src;
                        script.onload = resolve;
                        script.onerror = reject;
                        document.head.appendChild(script);
                    });
                };
                this._loadVendorScript = loadVendorScript;

                try {
                    await Promise.all([
                        loadVendorScript(() => typeof THREE !== 'undefined', "{{ asset('vendor/three/three.min.js') }}")
                            .then(() => loadVendorScript(() => typeof THREE !== 'undefined' && typeof THREE.OrbitControls !== 'undefined', "{{ asset('vendor/three/OrbitControls.js') }}")),
                        loadVendorScript(() => typeof anime !== 'undefined', "{{ asset('vendor/animejs/anime.min.js') }}"),
                    ]);
                } catch (vendorErr) {
                    console.warn('Vendor script load warning in 3D view:', vendorErr);
                }

                startAnimation();
            },

            async ensureMapboxReady() {
                if (window.funkiMap) {
                    if (window.funkiMap.loaded && window.funkiMap.loaded()) {
                        return window.funkiMap;
                    }
                    return new Promise(resolve => {
                        window.funkiMap.once('load', () => resolve(window.funkiMap));
                        setTimeout(() => resolve(window.funkiMap), 800);
                    });
                }
                if (this._mapboxInitPromise) {
                    return this._mapboxInitPromise;
                }

                this._mapboxInitPromise = (async () => {
                    try {
                        const cssHref = "{{ asset('vendor/mapbox-gl/mapbox-gl.css') }}";
                        if (!document.querySelector(`link[href="${cssHref}"]`)) {
                            const link = document.createElement('link');
                            link.rel = 'stylesheet';
                            link.href = cssHref;
                            document.head.appendChild(link);
                        }

                        const loader = this._loadVendorScript || ((globalCheck, src) => {
                            if (globalCheck()) return Promise.resolve();
                            return new Promise((resolve, reject) => {
                                const s = document.createElement('script');
                                s.src = src;
                                s.onload = resolve;
                                s.onerror = reject;
                                document.head.appendChild(s);
                            });
                        });

                        await loader(() => typeof mapboxgl !== 'undefined', "{{ asset('vendor/mapbox-gl/mapbox-gl.js') }}");
                        this.initMapbox();

                        if (window.funkiMap) {
                            await new Promise(resolve => {
                                if (window.funkiMap.loaded && window.funkiMap.loaded()) return resolve();
                                window.funkiMap.once('load', () => resolve());
                                setTimeout(() => resolve(), 900);
                            });
                        }
                    } catch (err) {
                        console.warn('Lazy Mapbox init warning:', err);
                    } finally {
                        this._mapboxInitPromise = null;
                    }
                    return window.funkiMap;
                })();

                return this._mapboxInitPromise;
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

                t3.closeTimerOuter = setTimeout(() => {
                    t3.closeTimerOuter = null;
                    this.showFunkiView = false; 
                    if (this.flightDataInterval) {
                        clearInterval(this.flightDataInterval);
                        this.flightDataInterval = null;
                    }
                    if (window.pauseBrainMap) window.pauseBrainMap();
                    // Auto-Wipe Top Secret RAM beim Verlassen des 3D-Zentrums
                    this.isSecretMode = false;
                    this.secretVaultIntel = [];
                    this.secretVaultRelations = [];
                    this.secretNetworkNodes = [];
                    this.secretNetworkSvg = '';

                    t3.closeTimerInner = setTimeout(() => {
                        t3.closeTimerInner = null;
                        this.destroyMapbox();
                        this.destroyThreeJS();
                        document.body.style.overflow = 'auto'; 
                    }, 300);
                }, 650);
            }, 

            toggleBackgroundAudio() {
                this.isAudioMuted = !this.isAudioMuted;
                localStorage.setItem('funki_isAudioMuted', this.isAudioMuted);
                this.enforceAudioMuteState();
            },

            enforceAudioMuteState() {
                const bgAudio = document.getElementById('audio-funki-background');
                const ambientAudio = document.getElementById('audio-funki-default-ambient');
                const pulseAudio = document.getElementById('audio-funki-pulse');
                const heartbeatAudio = document.getElementById('audio-funki-heartbeat');

                // Force mute on mobile devices during Live Mode to prevent OS hardware mixing clipping
                const mobileLiveMute = this.isMobile && this.isLiveMode;
                const effectiveMute = this.isAudioMuted || mobileLiveMute;

                if (bgAudio) {
                    bgAudio.muted = effectiveMute;
                    if (!effectiveMute && !t3.isShuttingDown) {
                        bgAudio.volume = this.bgVolume / 100;
                        if (bgAudio.paused) bgAudio.play().catch(e=>e);
                    } else if (mobileLiveMute) {
                        bgAudio.volume = 0;
                        bgAudio.pause();
                    } else if (this.isAudioMuted) {
                        bgAudio.pause();
                    }
                }

                if (ambientAudio) {
                    ambientAudio.muted = effectiveMute;
                    if (this.isAudioMuted && !t3.isShuttingDown && !mobileLiveMute) {
                        ambientAudio.volume = 0.05;
                        if (ambientAudio.paused) ambientAudio.play().catch(e=>e);
                    } else {
                        ambientAudio.volume = 0;
                        if (effectiveMute) ambientAudio.pause();
                    }
                    if (!effectiveMute && !this.isAudioMuted && !t3.isShuttingDown) {
                        if (ambientAudio.paused) ambientAudio.play().catch(e=>e);
                    }
                }

                if (pulseAudio) {
                    pulseAudio.muted = effectiveMute;
                    if (effectiveMute) pulseAudio.pause();
                }
                if (heartbeatAudio) {
                    heartbeatAudio.muted = effectiveMute;
                    if (effectiveMute) heartbeatAudio.pause();
                    else if (heartbeatAudio.paused && !t3.isShuttingDown) heartbeatAudio.play().catch(e=>e);
                }
            },

            initMapbox() {
                if (typeof mapboxgl === 'undefined') return;
                const mapContainer = document.getElementById('funki-mapbox-container');
                if (!mapContainer) return;
                const mbToken = '{{ config("services.mapbox.token") }}';
                if (!mbToken) return;
                if (window.funkiMap) {
                    try { window.funkiMap.resize(); } catch (e) {}
                    return;
                }
                mapboxgl.accessToken = mbToken;

                const escapeMapHtml = (val) => String(val ?? '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
                
                window.funkiMap = new mapboxgl.Map({
                    container: 'funki-mapbox-container',
                    style: this.activeMapStyle,
                    center: [10.5467, 52.4787], // Manufaktur Gifhorn / Deutschland-Zentrum
                    zoom: 5.5,
                    pitch: 45,
                    bearing: 0,
                    antialias: false,
                    fadeDuration: 0,
                    maxTileCacheSize: 90
                });

                const ensureCustomMapLayers = () => {
                    if (!window.funkiMap) return;
                    const currentStyle = window.funkiMap.getStyle();
                    if (currentStyle && currentStyle.sprite && String(currentStyle.sprite).includes('dark-v')) {
                        try {
                            if (window.funkiMap.getLayer('water')) {
                                window.funkiMap.setPaintProperty('water', 'fill-color', '#041428');
                            }
                            if (window.funkiMap.getLayer('land')) {
                                window.funkiMap.setPaintProperty('land', 'background-color', '#050811');
                            }
                        } catch (e) {}
                    }

                    const layers = (currentStyle && currentStyle.layers) ? currentStyle.layers : [];
                    let labelLayerId;
                    for (let i = 0; i < layers.length; i++) {
                        if (layers[i].type === 'symbol' && layers[i].layout && layers[i].layout['text-field']) {
                            labelLayerId = layers[i].id;
                            break;
                        }
                    }

                    if (window.funkiMap.getSource('composite') && !window.funkiMap.getLayer('3d-buildings')) {
                        try {
                            window.funkiMap.addLayer({
                                'id': '3d-buildings',
                                'source': 'composite',
                                'source-layer': 'building',
                                'filter': ['==', 'extrude', 'true'],
                                'type': 'fill-extrusion',
                                'minzoom': 14.5,
                                'paint': {
                                    'fill-extrusion-color': '#C5A059',
                                    'fill-extrusion-height': ['interpolate', ['linear'], ['zoom'], 14.5, 0, 15.05, ['get', 'height']],
                                    'fill-extrusion-base': ['interpolate', ['linear'], ['zoom'], 14.5, 0, 15.05, ['get', 'min_height']],
                                    'fill-extrusion-opacity': 0.65
                                }
                            }, labelLayerId);
                        } catch (e) {}
                    }

                    if (!window.funkiMap.getSource('live-flights')) {
                        window.funkiMap.addSource('live-flights', {
                            type: 'geojson',
                            data: window._lastFlightsGeoJson || { type: 'FeatureCollection', features: [] }
                        });
                    }
                    if (!window.funkiMap.getLayer('live-flights-layer')) {
                        window.funkiMap.addLayer({
                            id: 'live-flights-layer',
                            type: 'symbol',
                            source: 'live-flights',
                            layout: {
                                'text-field': '✈',
                                'text-size': 18,
                                'text-rotate': ['get', 'heading'],
                                'text-rotation-alignment': 'map',
                                'text-allow-overlap': true,
                                'text-ignore-placement': true
                            },
                            paint: {
                                'text-color': '#00f0ff',
                                'text-halo-color': '#000000',
                                'text-halo-width': 1.2
                            }
                        });
                    }

                    if (!window.funkiMap.getSource('live-crises')) {
                        window.funkiMap.addSource('live-crises', {
                            type: 'geojson',
                            data: window._lastCrisesGeoJson || { type: 'FeatureCollection', features: [] }
                        });
                    }
                    if (!window.funkiMap.getLayer('live-crises-layer')) {
                        window.funkiMap.addLayer({
                            id: 'live-crises-layer',
                            type: 'circle',
                            source: 'live-crises',
                            paint: {
                                'circle-color': '#ff2244',
                                'circle-radius': ['interpolate', ['linear'], ['zoom'], 2, 10, 8, 28],
                                'circle-blur': 0.6,
                                'circle-opacity': 0.55
                            }
                        });
                    }
                };

                window.funkiMap.on('style.load', () => {
                    ensureCustomMapLayers();
                    if (this.isFlightDataActive) {
                        this.fetchLiveFlightData();
                        this.generateCrisisData();
                    }
                });

                window.funkiMap.on('load', () => {
                    ensureCustomMapLayers();

                    window.funkiMap.on('click', 'live-flights-layer', (e) => {
                        if (!e.features || !e.features[0]) return;
                        const coordinates = e.features[0].geometry.coordinates.slice();
                        const p = e.features[0].properties || {};
                        const alt = p.geo_altitude ? `${Math.round(Number(p.geo_altitude))} m` : 'k.A.';
                        const spd = p.velocity ? `${Math.round(Number(p.velocity) * 3.6)} km/h` : 'k.A.';
                        new mapboxgl.Popup({ closeButton: true })
                            .setLngLat(coordinates)
                            .setHTML(`<div class="text-[11px] font-mono bg-gray-950 text-white p-1.5 rounded"><strong class="text-emerald-400 uppercase">Flug ${escapeMapHtml(p.callsign || 'UNKNOWN')}<\/strong><br><span class="text-gray-300">Land: ${escapeMapHtml(p.origin_country || p.country || 'Unbekannt')}<br>Höhe: ${escapeMapHtml(alt)} · ${escapeMapHtml(spd)}<\/span><\/div>`)
                            .addTo(window.funkiMap);
                    });
                    window.funkiMap.on('mouseenter', 'live-flights-layer', () => { window.funkiMap.getCanvas().style.cursor = 'pointer'; });
                    window.funkiMap.on('mouseleave', 'live-flights-layer', () => { window.funkiMap.getCanvas().style.cursor = ''; });

                    window.funkiMap.on('click', 'live-crises-layer', (e) => {
                        if (!e.features || !e.features[0]) return;
                        const coordinates = e.features[0].geometry.coordinates.slice();
                        const p = e.features[0].properties || {};
                        new mapboxgl.Popup({ closeButton: true })
                            .setLngLat(coordinates)
                            .setHTML(`<div class="text-[11px] font-mono bg-gray-950 text-white p-1.5 rounded"><strong class="text-rose-400 uppercase">${escapeMapHtml(p.name || 'Region')}<\/strong><\/div>`)
                            .addTo(window.funkiMap);
                    });
                });

                const syncMarkerCount = () => {
                    this.mapMarkerCount = (window.funkiMarker ? 1 : 0)
                        + (Array.isArray(window.newsMarkers) ? window.newsMarkers.length : 0)
                        + (Array.isArray(window.placeMarkers) ? window.placeMarkers.length : 0);
                };

                window.flyToLocation = (lng, lat, zoom = 14, pitch = 50, markerText = '') => {
                    if (!window.funkiMap) return;
                    
                    window.funkiMap.flyTo({
                        center: [Number(lng), Number(lat)],
                        zoom: Number(zoom) || 14,
                        pitch: Number(pitch) || 50,
                        bearing: Number(zoom) >= 15 ? -15 : 0,
                        essential: true,
                        duration: 1650,
                        easing: (t) => 1 - Math.pow(1 - t, 3)
                    });

                    if (window.funkiMarker) {
                        window.funkiMarker.remove();
                        window.funkiMarker = null;
                    }
                    
                    if (markerText) {
                        const el = document.createElement('div');
                        el.className = 'marker';
                        const pill = document.createElement('div');
                        pill.className = 'bg-gray-950/90 border border-[#C5A059] text-white text-[11px] font-mono font-bold px-2.5 py-1 rounded-lg shadow-xl whitespace-nowrap';
                        pill.textContent = String(markerText);
                        el.appendChild(pill);
                        
                        window.funkiMarker = new mapboxgl.Marker(el)
                            .setLngLat([Number(lng), Number(lat)])
                            .addTo(window.funkiMap);
                    }
                    syncMarkerCount();
                };

                const fitMarkersViewport = (markers, fallbackZoom = 12, bbox = null) => {
                    if (!window.funkiMap || !markers || !markers.length) return;
                    if (markers.length === 1) {
                        window.funkiMap.flyTo({
                            center: [Number(markers[0].lng), Number(markers[0].lat)],
                            zoom: fallbackZoom,
                            pitch: 45,
                            bearing: 0,
                            essential: true,
                            duration: 1500
                        });
                        return;
                    }
                    let minLng = Infinity, minLat = Infinity, maxLng = -Infinity, maxLat = -Infinity;
                    if (Array.isArray(bbox) && bbox.length === 4) {
                        [minLng, minLat, maxLng, maxLat] = bbox.map(Number);
                    } else {
                        markers.forEach(m => {
                            const x = Number(m.lng), y = Number(m.lat);
                            if (x < minLng) minLng = x;
                            if (x > maxLng) maxLng = x;
                            if (y < minLat) minLat = y;
                            if (y > maxLat) maxLat = y;
                        });
                    }
                    if (Math.abs(maxLng - minLng) < 0.002 && Math.abs(maxLat - minLat) < 0.002) {
                        window.funkiMap.flyTo({ center: [minLng, minLat], zoom: fallbackZoom, pitch: 45, duration: 1500, essential: true });
                    } else {
                        window.funkiMap.fitBounds([[minLng, minLat], [maxLng, maxLat]], {
                            padding: { top: 100, bottom: 140, left: 90, right: 90 },
                            maxZoom: 15,
                            pitch: 40,
                            duration: 1650,
                            essential: true
                        });
                    }
                };

                window.markNewsEvents = (markers, bbox = null) => {
                    if (!window.funkiMap || !markers || !markers.length) return;
                    
                    if (window.newsMarkers) {
                        window.newsMarkers.forEach(m => m.remove());
                    }
                    window.newsMarkers = [];

                    fitMarkersViewport(markers, 5, bbox);

                    markers.forEach(marker => {
                        const el = document.createElement('div');
                        el.className = 'marker pointer-events-auto';
                        const card = document.createElement('div');
                        card.className = 'bg-rose-950/90 border border-rose-500 text-rose-100 text-[10px] font-mono px-2.5 py-1 rounded-lg shadow-lg cursor-pointer hover:scale-105 transition-transform relative z-50 max-w-[220px] truncate font-bold';
                        card.textContent = String(marker.title || 'Ereignis');
                        card.title = String((marker.title || '') + (marker.location_name ? ' · ' + marker.location_name : ''));
                        el.appendChild(card);

                        el.addEventListener('click', (e) => {
                            e.stopPropagation();
                            if (marker.url && /^https?:\/\//i.test(String(marker.url))) {
                                window.open(marker.url, '_blank', 'noopener,noreferrer');
                            } else {
                                window.funkiMap.flyTo({ center: [Number(marker.lng), Number(marker.lat)], zoom: 12, duration: 1100 });
                            }
                        });

                        const m = new mapboxgl.Marker(el)
                            .setLngLat([Number(marker.lng), Number(marker.lat)])
                            .addTo(window.funkiMap);
                            
                        window.newsMarkers.push(m);
                    });
                    syncMarkerCount();
                };

                window.markPlaces = (markers, bbox = null) => {
                    if (!window.funkiMap || !markers || !markers.length) return;
                    
                    if (window.placeMarkers) {
                        window.placeMarkers.forEach(m => m.remove());
                    }
                    window.placeMarkers = [];

                    fitMarkersViewport(markers, 13, bbox);

                    markers.forEach(marker => {
                        const el = document.createElement('div');
                        el.className = 'marker pointer-events-auto group';
                        const safeTitle = escapeMapHtml(marker.title || 'Ort');
                        const safeLoc = escapeMapHtml(marker.location_name || '');
                        el.innerHTML = `
                            <div class="bg-gray-950/90 border border-emerald-400 text-emerald-100 text-[10px] font-mono px-2.5 py-1 rounded-lg shadow-lg cursor-pointer hover:scale-105 transition-transform relative z-50">
                                <span class="relative z-10 font-bold">${safeTitle}<\/span>
                                <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 hidden group-hover:block w-52 bg-gray-950/95 border border-gray-700 rounded-lg p-2 text-left shadow-2xl z-50 pointer-events-none">
                                    <div class="text-white font-bold mb-1 border-b border-gray-800 pb-1 whitespace-normal leading-tight">${safeTitle}<\/div>
                                    <div class="text-gray-300 text-[9px] whitespace-normal">${safeLoc}<\/div>
                                <\/div>
                            <\/div>
                        `;

                        el.addEventListener('click', (e) => {
                            e.stopPropagation();
                            window.funkiMap.flyTo({ center: [Number(marker.lng), Number(marker.lat)], zoom: 16, pitch: 60, duration: 1200 });
                        });

                        const m = new mapboxgl.Marker(el)
                            .setLngLat([Number(marker.lng), Number(marker.lat)])
                            .addTo(window.funkiMap);
                            
                        window.placeMarkers.push(m);
                    });
                    syncMarkerCount();
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
                    syncMarkerCount();
                };
            },
            
            async fetchLiveFlightData() {
                if (!this.isFlightDataActive || !window.funkiMap) return;
                try {
                    let url = '/api/flights';
                    try {
                        const b = window.funkiMap.getBounds();
                        if (b) {
                            const params = new URLSearchParams({
                                lamin: Math.max(-85, b.getSouth()).toFixed(1),
                                lamax: Math.min(85, b.getNorth()).toFixed(1),
                                lomin: Math.max(-180, b.getWest()).toFixed(1),
                                lomax: Math.min(180, b.getEast()).toFixed(1)
                            });
                            url += '?' + params.toString();
                        }
                    } catch (bErr) {}

                    const response = await fetch(url, { headers: { 'Accept': 'application/json' } });
                    if (!response.ok) return;
                    const data = await response.json();
                    
                    const features = [];
                    if (data && Array.isArray(data.states)) {
                        const limit = Math.min(data.states.length, 600);
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
                                        callsign: state[1] ? String(state[1]).trim() : 'UNKNOWN',
                                        country: state[2] || 'Unbekannt',
                                        origin_country: state[2] || 'Unbekannt',
                                        geo_altitude: state[13] ?? state[7] ?? 0,
                                        velocity: state[9] ?? 0,
                                        heading: state[10] ?? 0
                                    }
                                });
                            }
                        }
                    }
                    
                    const fc = { type: 'FeatureCollection', features: features };
                    window._lastFlightsGeoJson = fc;
                    if (window.funkiMap.getSource('live-flights')) {
                        window.funkiMap.getSource('live-flights').setData(fc);
                    }
                } catch (e) {
                    console.log('Flight data fetch error:', e);
                }
            },

            generateCrisisData() {
                if (!this.isFlightDataActive || !window.funkiMap) return;
                
                const crises = [
                    { lng: 37.0, lat: 48.0, name: "Ukraine" },
                    { lng: 34.5, lat: 31.5, name: "Nahost" },
                    { lng: 30.0, lat: 15.0, name: "Sudan" },
                    { lng: 43.0, lat: 15.0, name: "Jemen" },
                    { lng: 28.0, lat: -4.0, name: "DR Kongo" },
                    { lng: 96.0, lat: 21.0, name: "Myanmar" }
                ];
                
                const fc = {
                    type: 'FeatureCollection',
                    features: crises.map(c => ({
                        type: 'Feature',
                        geometry: { type: 'Point', coordinates: [c.lng, c.lat] },
                        properties: { name: c.name }
                    }))
                };
                window._lastCrisesGeoJson = fc;
                
                if (window.funkiMap.getSource('live-crises')) {
                    window.funkiMap.getSource('live-crises').setData(fc);
                }
            },

            destroyMapbox() {
                if (this.flightDataInterval) {
                    clearInterval(this.flightDataInterval);
                    this.flightDataInterval = null;
                }
                if (window.funkiMap) {
                    window.funkiMap.remove();
                    window.funkiMap = null;
                }
            },
