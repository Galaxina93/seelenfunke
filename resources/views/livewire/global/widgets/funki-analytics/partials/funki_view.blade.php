<div x-init="init()" 
     @open-funki.window="openFunkiView()"
     @funki-event.window="updateFunkiStatus($event.detail.state)"
     @keyup.escape.window="closeFunkiView()">
     
    <template x-teleport="body">
        <div x-show="showFunkiView"
             x-transition:enter="transition ease-out duration-1000"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-1000"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             style="display: none;"
             class="fixed inset-0 z-[99999] bg-[#03050a] overflow-hidden font-mono">
    
    <!-- CSS2D Container for HTML elements in 3D -->
    <div id="css2d-container" class="absolute inset-0 w-full h-full pointer-events-none z-10" style="pointer-events: none;"></div>
    
    <!-- Canvas Container -->
    <div id="funki-canvas-container" class="absolute inset-0 w-full h-full"></div>
    
    <!-- UI Overlay Navigation -->
    <div class="absolute top-6 right-6 z-10 transition-transform hover:scale-105">
        <button @click="closeFunkiView()" class="px-5 py-2.5 bg-gray-900/80 border border-gray-700 rounded-full text-xs font-black uppercase tracking-widest text-gray-300 hover:text-white hover:border-primary hover:bg-black transition-all shadow-glow flex items-center gap-2 backdrop-blur-md">
            <i class="bi bi-x-lg"></i> Funki-Zentrum verlassen
        </button>
    </div>
    <!-- Audio Elements -->
    <audio id="audio-funki-background" src="{{ asset('storage/sounds/funki_background.mp3') }}" preload="auto" loop></audio>
    <audio id="audio-funki-init" src="{{ asset('storage/sounds/funki_Initialize.mp3') }}" preload="auto"></audio>
    <audio id="audio-funki-shutdown" src="{{ asset('storage/sounds/funki_shutdown.mp3') }}" preload="auto"></audio>
    <audio id="audio-funki-heartbeat" src="{{ asset('storage/sounds/funki_heartbeat.mp3') }}" preload="auto" loop></audio>
    <audio id="audio-funki-click" src="{{ asset('storage/sounds/funki_click.mp3') }}" preload="auto"></audio>
    <audio id="audio-funki-unclick" src="{{ asset('storage/sounds/funki_unclick.mp3') }}" preload="auto"></audio>
    
    <!-- Floating Info Panel (Mapped to 3D Space) -->
    <div id="diagnostic-panel"
         x-show="showInfoPanel"
         @click.away="if(showInfoPanel) { showInfoPanel = false; playUnclickSound(); }"
         class="w-[600px] max-w-[90vw] pointer-events-none flex justify-center items-center"
         style="display: none;">
        
        <div x-show="showInfoPanel"
             x-transition:enter="transition ease-out duration-500"
             x-transition:enter-start="opacity-0 scale-95 translate-y-4"
             x-transition:enter-end="opacity-100 scale-100 translate-y-0"
             x-transition:leave="transition ease-in duration-300"
             x-transition:leave-start="opacity-100 scale-100 translate-y-0"
             x-transition:leave-end="opacity-0 scale-95 translate-y-4"
             class="w-full pointer-events-auto">
             
            <!-- Header -->
            <div class="px-6 py-4 flex justify-between items-center drop-shadow-md">
                <div class="flex items-center gap-3">
                    <div class="w-2.5 h-2.5 rounded-full shadow-[0_0_10px_rgba(16,185,129,1)]"
                         :class="{'bg-emerald-500 shadow-[0_0_10px_rgba(16,185,129,1)]': stateColor === 'good', 'bg-yellow-500 shadow-[0_0_10px_rgba(234,179,8,1)]': stateColor === 'warning', 'bg-red-500 shadow-[0_0_10px_rgba(239,68,68,1)]': stateColor === 'error'}"></div>
                    <h3 class="font-bold text-gray-100 tracking-wider">Kern-Diagnostik</h3>
                </div>
                <button @click="showInfoPanel = false; playUnclickSound();" class="text-gray-400 hover:text-white transition-colors drop-shadow-md">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            
            <!-- Body -->
            <div class="p-6 grid grid-cols-2 gap-6 relative drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)]">
                
                <div class="space-y-4 relative z-10">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-1">Status</p>
                        <p class="text-lg font-mono text-emerald-400" 
                           :class="{'text-emerald-400': stateColor === 'good', 'text-yellow-400': stateColor === 'warning', 'text-red-400': stateColor === 'error'}">
                           <span x-text="displayState"></span>
                        </p>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-1">Aktive Sitzungen</p>
                        <p class="text-2xl font-mono text-white flex items-baseline gap-2">
                            <span x-text="activeSparks"></span>
                            <span class="text-xs text-primary bg-primary/20 px-1.5 py-0.5 rounded">Online</span>
                        </p>
                    </div>
                </div>

                <div class="space-y-4 relative z-10">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-1">Ø Tagesgewinn / Bestellungen</p>
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-mono text-emerald-400" x-text="avgProfit"></span>
                            <span class="text-gray-600">|</span>
                            <span class="text-xs font-mono text-gray-300" x-text="totalOrders + ' Orders'"></span>
                        </div>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-1">Letzter Sync</p>
                        <p class="text-sm font-mono text-gray-300" x-text="lastSync"></p>
                    </div>
                </div>
                </div>
            </div>
        </div>
    </div>

        </div>
    </template>
</div>

<script>
    document.addEventListener('alpine:init', () => {
        let t3 = {
            scene: null,
            camera: null,
            renderer: null,
            coreMesh: null,
            hitboxMesh: null,
            coreMaterial: null,
            raymarchUniforms: null,
            coreLight: null,
            cssRenderer: null,
            cssObject: null,
            animationId: null,
            controls: null,
            raycaster: null,
            mouse: null,
            mouseMoveListener: null,
            clickListener: null,
            startTime: null,
            shutdownTime: null,
            isShuttingDown: false,
            bgAudio: null,
            heartbeatAudio: null,
        };

        Alpine.data('funkiView', (initialState = 'good', initialSparks = 42, avgProfit = 0, totalOrders = 0, lastSync = '') => ({
            // State
            showFunkiView: false,
            showInfoPanel: false,
            systemState: initialState, // 'good', 'warning', 'error', true, false
            activeSparks: initialSparks,
            avgProfit: avgProfit + ' €',
            totalOrders: totalOrders,
            lastSync: lastSync,

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
                // Bind listeners once to prevent memory leaks in the animation loop
                this.boundAnimate = () => this.animate();
                
                // Expose method to window so Livewire or Echo can trigger events
                window.updateFunkiStatus = (state) => {
                    this.systemState = state;
                    if(t3.coreMesh) {
                        this.updateCoreColor();
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

            async openFunkiView() {
                this.showFunkiView = true;
                t3.isShuttingDown = false;
                t3.shutdownTime = null;
                
                // Play Init Sound
                const initAudio = document.getElementById('audio-funki-init');
                if(initAudio) {
                    initAudio.currentTime = 0;
                    initAudio.volume = 0.8;
                    initAudio.play().catch(e => console.log("Audio play prevented", e));
                }

                // Setup & Play Background Music
                t3.bgAudio = document.getElementById('audio-funki-background');
                if (t3.bgAudio) {
                    t3.bgAudio.volume = 0; // Start at 0, fade in
                    t3.bgAudio.play().catch(e => console.log("Audio play prevented", e));
                    // Fade in slightly delayed
                    setTimeout(() => {
                        let volInt = setInterval(() => {
                            if(!this.showFunkiView || t3.isShuttingDown) { clearInterval(volInt); return; }
                            if(t3.bgAudio.volume < 0.3) t3.bgAudio.volume += 0.01;
                            else clearInterval(volInt);
                        }, 50);
                    }, 500);
                }

                // Setup Heartbeat Audio
                t3.heartbeatAudio = document.getElementById('audio-funki-heartbeat');
                if (t3.heartbeatAudio) {
                    t3.heartbeatAudio.volume = 0;
                    t3.heartbeatAudio.playbackRate = 1.0;
                    t3.heartbeatAudio.play().catch(e => console.log("Audio play prevented", e));
                }
                
                // Load Three.js dynamically to prevent multiple instances warning
                if (typeof THREE === 'undefined') {
                    await new Promise((resolve, reject) => {
                        const script = document.createElement('script');
                        script.src = "https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js";
                        script.onload = resolve;
                        script.onerror = reject;
                        document.head.appendChild(script);
                    });
                }
                
                // Load OrbitControls
                if (typeof THREE.OrbitControls === 'undefined') {
                    await new Promise((resolve, reject) => {
                        const script = document.createElement('script');
                        script.src = "https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/controls/OrbitControls.js";
                        script.onload = resolve;
                        script.onerror = reject;
                        document.head.appendChild(script);
                    });
                }
                
                // Load CSS2DRenderer
                if (typeof THREE.CSS2DRenderer === 'undefined') {
                    await new Promise((resolve, reject) => {
                        const script = document.createElement('script');
                        script.src = "https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/renderers/CSS2DRenderer.js";
                        script.onload = resolve;
                        script.onerror = reject;
                        document.head.appendChild(script);
                    });
                }
                
                this.$nextTick(() => {
                    this.initThreeJS();
                });
            },

            closeFunkiView() {
                if (t3.isShuttingDown) return; // Prevent double clicks
                t3.isShuttingDown = true;
                t3.shutdownTime = performance.now();
                document.body.style.cursor = 'default';
                
                // Play Shutdown Sound
                const shutdownAudio = document.getElementById('audio-funki-shutdown');
                if(shutdownAudio) {
                    shutdownAudio.currentTime = 0;
                    shutdownAudio.volume = 0.8;
                    shutdownAudio.play().catch(e => console.log("Audio play prevented", e));
                }

                // Stop Heartbeat softly
                if (t3.heartbeatAudio) {
                    let hbInt = setInterval(() => {
                        if (t3.heartbeatAudio.volume > 0.05) t3.heartbeatAudio.volume -= 0.05;
                        else {
                            t3.heartbeatAudio.volume = 0;
                            t3.heartbeatAudio.pause();
                            clearInterval(hbInt);
                        }
                    }, 50);
                }

                // Stop BG softly
                if (t3.bgAudio) {
                    let bgInt = setInterval(() => {
                        if (t3.bgAudio.volume > 0.02) t3.bgAudio.volume -= 0.02;
                        else {
                            t3.bgAudio.volume = 0;
                            t3.bgAudio.pause();
                            clearInterval(bgInt);
                        }
                    }, 50);
                }

                // Wait for animation (e.g., 2.5 seconds) before actually closing
                setTimeout(() => {
                    this.showFunkiView = false;
                    this.destroyThreeJS();
                }, 2500);
            },

            initThreeJS() {
                const container = document.getElementById('funki-canvas-container');
                if (!container) return;
                
                // Cleanup previous instances just in case
                container.innerHTML = '';

                t3.scene = new THREE.Scene();

                t3.camera = new THREE.PerspectiveCamera(60, window.innerWidth / window.innerHeight, 1.0, 3000);
                t3.camera.position.z = 250;
                t3.camera.position.y = 80;
                t3.camera.lookAt(0, 0, 0);

                // REUSE WebGL Renderer to prevent context limit exhaustion (16 contexts max per tab)
                if (!t3.renderer) {
                    t3.renderer = new THREE.WebGLRenderer({ antialias: false, alpha: true, powerPreference: "high-performance" });
                    t3.renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 0.75)); // CRITICAL: Prevent GPU TDR timeout on 4K AMD/Apple screens by raymarching slightly lower res
                }
                container.appendChild(t3.renderer.domElement);
                // Force size AFTER appending to the visible DOM
                t3.renderer.setSize(window.innerWidth, window.innerHeight);
                t3.camera.aspect = window.innerWidth / window.innerHeight;
                t3.camera.updateProjectionMatrix();
                
                // Setup CSS2DRenderer
                const cssContainer = document.getElementById('css2d-container');
                if (cssContainer) cssContainer.innerHTML = ''; // Reset
                if (THREE.CSS2DRenderer && cssContainer) {
                    if (!t3.cssRenderer) {
                        t3.cssRenderer = new THREE.CSS2DRenderer();
                        t3.cssRenderer.domElement.style.position = 'absolute';
                        t3.cssRenderer.domElement.style.top = '0px';
                        t3.cssRenderer.domElement.style.pointerEvents = 'none'; 
                    }
                    t3.cssRenderer.setSize(window.innerWidth, window.innerHeight);
                    cssContainer.appendChild(t3.cssRenderer.domElement);
                }

                // Attach controls to the main WebGL canvas so mouse drag works correctly
                t3.controls = new THREE.OrbitControls(t3.camera, t3.renderer.domElement);
                t3.controls.enableDamping = true;
                t3.controls.dampingFactor = 0.05;
                t3.controls.autoRotate = true; // Chill vibe
                t3.controls.autoRotateSpeed = 0.3;
                t3.controls.maxDistance = 600; 
                // CRITICAL: Prevent camera from entering the proxy box, which causes the bounding cube mesh to cull and disappear
                t3.controls.minDistance = 150;  
            
                // Add Lights
                t3.scene.add(new THREE.AmbientLight(0x111122));
                t3.coreLight = new THREE.PointLight(0x10b981, 2, 400);
                t3.scene.add(t3.coreLight);

                // Volumetric Raymarching Proxy Box 
                // Increased box size significantly to support massive displacement and thick aura
                const coreGeometry = new THREE.BoxGeometry(250, 250, 250); 
                
                const raymarchVertexShader = `
                    varying vec3 vWorldPosition;
                    varying vec3 vLocalPosition;

                    void main() {
                        vLocalPosition = position;
                        vec4 worldPosition = modelMatrix * vec4(position, 1.0);
                        vWorldPosition = worldPosition.xyz;
                        gl_Position = projectionMatrix * viewMatrix * worldPosition;
                    }
                `;

                const raymarchFragmentShader = `
                    uniform float time;
                    uniform vec3 glowColor;
                    uniform vec3 cameraPos;
                    uniform float hoverState;
                    uniform float hoverTime;
                    uniform float initProgress;
                    uniform float shutdownProgress;

                    varying vec3 vWorldPosition;
                    varying vec3 vLocalPosition;

                    // Rotational matrices
                    mat3 rotY(float a) {
                        float s = sin(a), c = cos(a);
                        return mat3(c, 0.0, s, 0.0, 1.0, 0.0, -s, 0.0, c);
                    }
                    mat3 rotX(float a) {
                        float s = sin(a), c = cos(a);
                        return mat3(1.0, 0.0, 0.0, 0.0, c, -s, 0.0, s, c);
                    }

                    // --- EPIC Smooth Organic Fluid Displacement ---
                    float smoothFluid(vec3 p) {
                        float timeScale = time * 1.2;
                        
                        // Epic twisting vortex effect (Slower in center, faster on edges)
                        float l = length(p.xz);
                        float angle = l * 0.05 - timeScale * 0.8;
                        float s = sin(angle), c = cos(angle);
                        p.xz *= mat2(c, -s, s, c); 
                        
                        // Layer 1: Massive planetary waves
                        float d1 = sin(p.x*0.06 + timeScale) * cos(p.y*0.05 - timeScale*0.8) * sin(p.z*0.07 + timeScale);
                        
                        // Layer 2: Faster magma flows
                        float d2 = cos(p.x*0.12 - timeScale*1.5) * sin(p.y*0.13 + timeScale*1.2) * cos(p.z*0.11 - timeScale);
                        
                        // Layer 3: Energized surface ripples
                        float d3 = sin(p.x*0.25 + p.y*0.25 + timeScale*3.0) * cos(p.z*0.25 - timeScale*2.0);
                        
                        return (d1 * 18.0) + (d2 * 9.0) + (d3 * 4.0);
                    }

                    // Signed Distance Field for the Core
                    float map(vec3 p) {
                        float critical = min(hoverTime / 4.0, 1.0); // Reaches max critical at 4 seconds
                        
                        // Dynamic rotation, spins aggressively on hover
                        float rotSpeed = time * (0.02 + hoverState * 0.05 + critical * 0.3);
                        p = rotY(rotSpeed) * rotX(rotSpeed * 0.6) * p;
                        
                        // Base size shrinks on init/shutdown, gets super fat on hover
                        float baseRadius = 45.0 + hoverState * 8.0 + critical * 12.0; 
                        baseRadius *= smoothstep(0.0, 0.8, initProgress);
                        baseRadius *= (1.0 - smoothstep(0.0, 1.0, shutdownProgress));
                        
                        // Apply the perfectly smooth but massive fluid displacement
                        float displacement = smoothFluid(p);
                        
                        float d = length(p) - baseRadius;
                        d -= displacement * smoothstep(0.2, 1.0, initProgress);
                        
                        // Epic melting transition 
                        if (shutdownProgress > 0.01) {
                            float melt = sin(p.y * 0.15 - time * 4.0) * cos(p.x * 0.15) * 45.0;
                            d += melt * shutdownProgress;
                        }
                        
                        return d;
                    }

                    void main() {
                        vec3 ro = cameraPos;
                        vec3 rd = normalize(vWorldPosition - ro);
                        
                        float t = distance(ro, vWorldPosition) + 0.1;
                        float maxT = t + 240.0; // Extend ray distance for larger aura
                        
                        vec3 accumulatedColor = vec3(0.0);
                        float accumulatedAlpha = 0.0;
                        
                        // Pure, deeply saturated colors! No white out in the center.
                        float critical = min(hoverTime / 4.0, 1.0);
                        
                        // Glow gets much hotter and brighter in center, but stays true to the base color (Green/Yellow/Red)
                        vec3 currentGlowColor = mix(glowColor, glowColor * 1.5, hoverState * 0.95);
                        vec3 criticalColor = vec3(1.0, 0.05, 0.0); // Pure deep red warning
                        currentGlowColor = mix(currentGlowColor, criticalColor * 2.0, critical);
                        
                        vec3 hotCoreColor = currentGlowColor * (2.5 + critical * 2.0); // Force the center to be intensely saturated
                        
                        // Raymarching Loop (Thick, voluminous plasma logic)
                        for(int i = 0; i < 45; i++) { 
                            vec3 p = ro + rd * t;
                            float d = map(p);
                            
                            float proximity = abs(d);
                            // Exponential falloff mimics light scattering in dense gas/plasma
                            float density = exp(-proximity * 0.08); 
                            
                            if (d < 0.0) {
                                // Inside: Pure hot energy blocking light
                                accumulatedColor += hotCoreColor * 0.2; 
                                accumulatedAlpha += 0.2; 
                            } else {
                                // Outside: Aura and corona
                                accumulatedColor += currentGlowColor * density * 0.1;
                                accumulatedColor += hotCoreColor * exp(-proximity * 0.4) * 0.05; // Bright inner rim
                                accumulatedAlpha += density * 0.02;
                            }
                            
                            // Safe smooth step
                            t += max(proximity * 0.5, 0.5);
                            
                            // Early exit
                            if (t > maxT || accumulatedAlpha > 0.99) break; 
                        }
                        
                        // Dynamic Pulse System (Huge heartbeats)
                        float speedBoost = critical * 15.0;
                        float breathingPulse = 0.8 + 0.3 * sin(time * (2.5 + speedBoost));
                        float quickPulse = 0.7 + 0.5 * sin(time * (7.0 + speedBoost));
                        float combinedPulse = mix(breathingPulse, quickPulse, hoverState);
                        
                        vec3 finalColor = accumulatedColor * combinedPulse * smoothstep(0.2, 1.0, initProgress);
                        
                        // Ambient hover corona waggle (outer energy flares)
                        float softWaggle = sin(vWorldPosition.x * 0.03 + time * 3.0) * 1.5;
                        finalColor += currentGlowColor * hoverState * softWaggle * 0.15;
                        
                        // Shutdown desaturation
                        float luma = dot(finalColor, vec3(0.299, 0.587, 0.114));
                        finalColor = mix(finalColor, vec3(luma) * 0.3, shutdownProgress); 
                        
                        float finalAlpha = min(accumulatedAlpha, 1.0);
                        finalAlpha *= smoothstep(0.0, 0.5, initProgress);
                        finalAlpha *= (1.0 - smoothstep(0.5, 1.0, shutdownProgress)); 
                        
                        if (finalAlpha <= 0.01) discard; 
                        
                        gl_FragColor = vec4(finalColor, finalAlpha);
                    }
                `;

                t3.raymarchUniforms = {
                    time: { value: 0 },
                    glowColor: { value: new THREE.Color(0x00ff88) },
                    cameraPos: { value: t3.camera.position },
                    hoverState: { value: 0.0 },
                    hoverTime: { value: 0.0 },
                    initProgress: { value: 0.0 },
                    shutdownProgress: { value: 0.0 }
                };

                t3.coreMaterial = new THREE.ShaderMaterial({
                    uniforms: t3.raymarchUniforms,
                    vertexShader: raymarchVertexShader,
                    fragmentShader: raymarchFragmentShader,
                    transparent: true,
                    side: THREE.FrontSide, // Render starting from the outside of the proxy box
                    depthWrite: false
                });

                t3.coreMesh = new THREE.Mesh(coreGeometry, t3.coreMaterial);
                t3.scene.add(t3.coreMesh);
                
                // --- SAFE THREE.JS STARFIELD & GRID (No Shader Compilation required) ---
                const starsGeometry = new THREE.BufferGeometry();
                const starsCount = 2000;
                const posArray = new Float32Array(starsCount * 3);
                const colorArray = new Float32Array(starsCount * 3);
                
                for(let i = 0; i < starsCount * 3; i+=3) {
                    const r = 300 + Math.random() * 800; // Even deeper space
                    const theta = 2 * Math.PI * Math.random();
                    const phi = Math.acos(2 * Math.random() - 1);
                    
                    posArray[i] = r * Math.sin(phi) * Math.cos(theta);
                    posArray[i+1] = r * Math.sin(phi) * Math.sin(theta);
                    posArray[i+2] = r * Math.cos(phi);
                    
                    // Match star colors somewhat to the core vibe (slightly cyan/green/blue)
                    colorArray[i] = 0.2 + Math.random() * 0.3;
                    colorArray[i+1] = 0.7 + Math.random() * 0.3;
                    colorArray[i+2] = 0.8 + Math.random() * 0.2;
                }
                
                starsGeometry.setAttribute('position', new THREE.BufferAttribute(posArray, 3));
                starsGeometry.setAttribute('color', new THREE.BufferAttribute(colorArray, 3));
                
                const starsMaterial = new THREE.PointsMaterial({
                    size: 1.5,
                    vertexColors: true,
                    transparent: true,
                    opacity: 0,
                    sizeAttenuation: false,
                    blending: THREE.AdditiveBlending // Epic glow blending
                });
                
                t3.starsMesh = new THREE.Points(starsGeometry, starsMaterial);
                t3.scene.add(t3.starsMesh);
                
                // Epic Cyber/Sci-Fi Grid Floor (Extremely cheap to render, huge visual impact)
                const gridSize = 2000;
                const gridDivisions = 80;
                const gridColorCore = new THREE.Color(0x002211); // Very dark green
                const gridColorLines = new THREE.Color(0x004422);
                
                t3.gridHelper = new THREE.GridHelper(gridSize, gridDivisions, gridColorCore, gridColorLines);
                t3.gridHelper.position.y = -180; // Far below the core
                t3.gridHelper.material.transparent = true;
                t3.gridHelper.material.opacity = 0; // Fade in
                
                t3.scene.add(t3.gridHelper);
                // -------------------------------------------------------------
                
                // Invisible Hitbox specifically for tight raycasting (matches visual core size)
                const hitboxGeo = new THREE.SphereGeometry(50, 16, 16); // Increased hitbox to match much fatter core
                const hitboxMat = new THREE.MeshBasicMaterial({ visible: false });
                t3.hitboxMesh = new THREE.Mesh(hitboxGeo, hitboxMat);
                t3.scene.add(t3.hitboxMesh);
                
                // Attach Panel as a CSS2DObject
                if (THREE.CSS2DObject) {
                    const panelElement = document.getElementById('diagnostic-panel');
                    if (panelElement) {
                        t3.cssObject = new THREE.CSS2DObject(panelElement);
                        // Position it slightly to the right and above the core
                        t3.cssObject.position.set(100, 50, 0); 
                        t3.hitboxMesh.add(t3.cssObject); // Attach it to the hitbox so it spins with the object
                    }
                }
                
                t3.startTime = performance.now();

                // Setup resize listener & Raycaster
                window.addEventListener('resize', this.onWindowResize.bind(this));
                
                t3.raycaster = new THREE.Raycaster();
                t3.mouse = new THREE.Vector2(-9999, -9999);
                t3.mouseMoveListener = (event) => {
                    t3.mouse.x = (event.clientX / window.innerWidth) * 2 - 1;
                    t3.mouse.y = -(event.clientY / window.innerHeight) * 2 + 1;
                };
                window.addEventListener('mousemove', t3.mouseMoveListener);

                // Start anim loop
                this.animate();
                
                // Add Click Event Listener
                t3.clickListener = (event) => {
                    if (t3.raycaster && t3.camera && t3.hitboxMesh && !t3.isShuttingDown && !this.showInfoPanel) {
                        t3.raycaster.setFromCamera(t3.mouse, t3.camera);
                        const intersects = t3.raycaster.intersectObject(t3.hitboxMesh);
                        if (intersects.length > 0) {
                            // Play click sound
                            const clickAudio = document.getElementById('audio-funki-click');
                            if (clickAudio) {
                                clickAudio.currentTime = 0;
                                clickAudio.volume = 0.6;
                                clickAudio.play().catch(e => console.log(e));
                            }
                            
                            // Open panel
                            this.showInfoPanel = true;
                        }
                    }
                };
                window.addEventListener('click', t3.clickListener);

                // Ensure initial color reflects state
                this.updateCoreColor();
            },

            updateCoreColor() {
                if(!t3.raymarchUniforms || !t3.coreLight) return;
                
                let targetColor = 0x00ff88; // default green
                if (this.systemState === 'good' || this.systemState === true) {
                    targetColor = 0x00ff88; // Green
                } else if (this.systemState === 'warning') {
                    targetColor = 0xffcc00; // Yellow
                } else if (this.systemState === 'error' || this.systemState === false) {
                    targetColor = 0xff3333; // Red
                }
                
                t3.raymarchUniforms.glowColor.value.setHex(targetColor);
                t3.coreLight.color.setHex(targetColor);
                
                // Also tint the environment grid to match the system state
                if (t3.gridHelper) {
                    const tint = new THREE.Color(targetColor);
                    tint.multiplyScalar(0.25); // Darken for grid
                    t3.gridHelper.material.color.copy(tint);
                }
            },

            animate() {
                if (!this.showFunkiView) return;
                
                // Use the bound loop helper to avoid closure memory leaks every frame
                t3.animationId = requestAnimationFrame(this.boundAnimate);

                const now = performance.now();
                const delta = t3.lastFrameTime ? (now - t3.lastFrameTime) / 1000.0 : 0.016;
                t3.lastFrameTime = now;
                
                const elapsedTime = now - t3.startTime;
                
                // Update Shader Time uniforms
                const time = performance.now() * 0.001;
                let initProg = 0.0;
                let shutProg = 0.0;
                
                if(t3.raymarchUniforms) {
                    initProg = Math.min(elapsedTime / 3000.0, 1.0);
                    
                    t3.raymarchUniforms.time.value = time;
                    t3.raymarchUniforms.cameraPos.value.copy(t3.camera.position);
                    t3.raymarchUniforms.initProgress.value = initProg;
                    
                    if (t3.isShuttingDown) {
                        const sdTime = performance.now() - t3.shutdownTime;
                        shutProg = Math.min(sdTime / 2500.0, 1.0);
                        t3.raymarchUniforms.shutdownProgress.value = shutProg;
                    } else {
                        t3.raymarchUniforms.shutdownProgress.value = 0.0;
                    }
                }
                
                // Fade environment in/out to match core
                if (t3.starsMesh) {
                    t3.starsMesh.rotation.y = time * 0.02; // Slow rotation
                    let targetOpacity = initProg * (1.0 - shutProg) * 0.6; // Slightly dimmer stars so core pops
                    t3.starsMesh.material.opacity = targetOpacity;
                }
                if (t3.gridHelper) {
                    t3.gridHelper.position.z = (time * 15.0) % 25; // Continuous forward motion illusion
                    let gridOpacity = initProg * (1.0 - shutProg) * 0.25; 
                    t3.gridHelper.material.opacity = gridOpacity;
                }

                // Raycasting Interaction (Hovering the Hitbox, NOT the massive proxy box)
                if (t3.raycaster && t3.camera && t3.hitboxMesh && t3.raymarchUniforms && !t3.isShuttingDown) {
                    t3.raycaster.setFromCamera(t3.mouse, t3.camera);
                    const intersects = t3.raycaster.intersectObject(t3.hitboxMesh);
                    
                    if (intersects.length > 0) {
                        document.body.style.cursor = 'pointer';
                        t3.raymarchUniforms.hoverState.value += (1.0 - t3.raymarchUniforms.hoverState.value) * 0.05; // Slower transition
                        t3.raymarchUniforms.hoverTime.value += delta;
                        if(t3.controls) t3.controls.autoRotateSpeed = 0.5; // Spin moderately faster instead of chaotic
                    } else {
                        document.body.style.cursor = 'default';
                        t3.raymarchUniforms.hoverState.value += (0.0 - t3.raymarchUniforms.hoverState.value) * 0.05; // Slower transition
                        t3.raymarchUniforms.hoverTime.value = Math.max(0.0, t3.raymarchUniforms.hoverTime.value - delta * 2.0); // Cool down faster
                        if(t3.controls) t3.controls.autoRotateSpeed = 0.2; // Return to chill speed
                    }
                } else if (t3.isShuttingDown && t3.raymarchUniforms) {
                     t3.raymarchUniforms.hoverState.value += (0.0 - t3.raymarchUniforms.hoverState.value) * 0.1;
                     t3.raymarchUniforms.hoverTime.value = Math.max(0.0, t3.raymarchUniforms.hoverTime.value - delta * 3.0);
                }

                // Smooth heartbeat audio control based on hoverState
                if (t3.heartbeatAudio && t3.raymarchUniforms && t3.raymarchUniforms.initProgress.value >= 1.0 && !t3.isShuttingDown) {
                    const hover = t3.raymarchUniforms.hoverState.value; // goes from 0 to 1 smoothly
                    // Max volume on hover 0.6, base 0.0
                    const targetVolume = hover * 0.6; 
                    // High pitch/speed on hover, normal speed otherwise. 
                    // To prevent changing pitch randomly we scale it between 1.0 and 1.8 (slower max)
                    const targetRate = 1.0 + hover * 0.8; 
                    
                    t3.heartbeatAudio.volume += (targetVolume - t3.heartbeatAudio.volume) * 0.05;
                    t3.heartbeatAudio.playbackRate += (targetRate - t3.heartbeatAudio.playbackRate) * 0.05;
                }

                // Smoothly pulsating Core Proxy Box (minor movement to maintain 3D feeling)
                if (t3.coreMesh) {
                    const pulse = 1 + Math.sin(time * 2.0) * 0.02;
                    t3.coreMesh.scale.set(pulse, pulse, pulse);
                }

                // Update Controls
                if (t3.controls) {
                    t3.controls.update();
                }

                if(t3.renderer && t3.scene && t3.camera) {
                    t3.renderer.render(t3.scene, t3.camera);
                }
                
                if(t3.cssRenderer && t3.scene && t3.camera) {
                    t3.cssRenderer.render(t3.scene, t3.camera);
                }
            },

            onWindowResize() {
                if (!t3.camera || !t3.renderer) return;
                t3.camera.aspect = window.innerWidth / window.innerHeight;
                t3.camera.updateProjectionMatrix();
                t3.renderer.setSize(window.innerWidth, window.innerHeight);
                if(t3.cssRenderer) {
                    t3.cssRenderer.setSize(window.innerWidth, window.innerHeight);
                }
            },

            destroyThreeJS() {
                if (t3.animationId) {
                    cancelAnimationFrame(t3.animationId);
                }
                window.removeEventListener('resize', this.onWindowResize);
                if(t3.mouseMoveListener) {
                    window.removeEventListener('mousemove', t3.mouseMoveListener);
                }
                if(t3.clickListener) {
                    window.removeEventListener('click', t3.clickListener);
                }
                
                if (t3.controls) {
                    t3.controls.dispose();
                    t3.controls = null;
                }
                
                // IMPORTANT: We explicitly do NOT destroy the WebGL renderer here. 
                // We leave it registered in memory inside the `t3` object.
                // Browsers hard-limit tabs to roughly 8-16 WebGL contexts. If we create a new context
                // every time the popup opens, it permanently breaks until a full page reload.
                
                // We just clear out the objects from the scene to save live memory.
                if (t3.scene) {
                    // Primitive deep dispose
                    t3.scene.traverse((object) => {
                        if (object.geometry) object.geometry.dispose();
                        if (object.material) {
                            if (Array.isArray(object.material)) {
                                object.material.forEach(mat => mat.dispose());
                            } else {
                                object.material.dispose();
                            }
                        }
                    });
                }
                
                const container = document.getElementById('funki-canvas-container');
                if (container) container.innerHTML = '';
            }
        }));
    });
</script>
