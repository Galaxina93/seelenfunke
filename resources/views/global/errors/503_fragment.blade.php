<div class="w-full relative min-h-[100dvh] flex flex-col items-center justify-center overflow-hidden bg-gradient-to-br from-slate-50 to-slate-200"
     x-data="{
        score: 0,
        combo: 1,
        highscore: localStorage.getItem('seelenfunke_highscore') || 0,
        message: 'Fange die Seelenfunken!',
        messageColor: 'text-gray-600',

        handleScore(e) {
            const points = e.detail.points;
            if(points < 0) {
                this.combo = 1;
                this.score = Math.max(0, this.score + points);
                this.message = 'Achtung! Rote Splitter meiden.';
                this.messageColor = 'text-red-500';
            } else {
                this.score += (points * this.combo);
                this.combo++;

                if(this.combo >= 10) {
                    this.message = 'UNGLAUBLICH! Combo x' + this.combo;
                    this.messageColor = 'text-purple-600';
                } else if(this.combo >= 5) {
                    this.message = 'Super! Combo x' + this.combo;
                    this.messageColor = 'text-primary';
                } else {
                    this.message = 'Weiter so!';
                    this.messageColor = 'text-emerald-600';
                }
            }

            if(this.score > this.highscore) {
                this.highscore = this.score;
                localStorage.setItem('seelenfunke_highscore', this.highscore);
            }
        }
     }"
     @update-score.window="handleScore($event)"
     id="maintenance-game-wrapper">

    {{-- Three.js Canvas Container --}}
    <div id="funki-game-container" class="absolute inset-0 z-0 cursor-crosshair"></div>

    {{-- UI Overlay --}}
    <div class="relative z-10 pointer-events-none select-none text-center max-w-xl mx-auto bg-white/60 backdrop-blur-xl p-8 md:p-12 rounded-[3rem] shadow-[0_20px_60px_rgba(0,0,0,0.08)] border border-white my-12 flex flex-col items-center">

        <div class="mb-6 flex justify-center">
            <img src="{{ asset('images/projekt/logo/mein-seelenfunke-logo.png') }}" alt="Mein Seelenfunke" class="h-20 w-auto drop-shadow-xl">
        </div>

        <h1 class="text-3xl md:text-4xl font-serif font-bold text-gray-900 mb-2 tracking-tight">
            Kreative Pause.
        </h1>

        <p class="text-gray-600 mb-8 text-sm md:text-base leading-relaxed font-medium max-w-md mx-auto transition-colors duration-300" :class="messageColor" x-text="message">
            Wir optimieren gerade den Shop für dich.
        </p>

        {{-- Score Board --}}
        <div class="flex flex-col items-center gap-4 w-full max-w-xs">
            <div class="w-full bg-slate-900 text-white px-6 py-4 rounded-3xl shadow-2xl border-2 border-primary/50 relative overflow-hidden pointer-events-auto hover:scale-105 transition-transform duration-300">
                <div class="absolute inset-0 bg-gradient-to-t from-primary/20 to-transparent opacity-50"></div>

                <div class="relative flex justify-between items-center mb-1">
                    <span class="text-[10px] font-black uppercase tracking-widest text-primary">Score</span>
                    <span class="text-[10px] font-black uppercase tracking-widest text-gray-400" x-show="combo > 1" x-transition>Combo x<span x-text="combo"></span></span>
                </div>

                <div class="relative flex justify-between items-end">
                    <div class="font-mono text-4xl font-bold tracking-tighter" x-text="score">0</div>
                    <div class="text-xs font-bold text-gray-400 pb-1">Highscore: <span class="text-white" x-text="highscore"></span></div>
                </div>
            </div>

            <div class="grid grid-cols-3 gap-2 w-full mt-2">
                <div class="bg-white/80 border border-gray-200 rounded-xl p-2 text-center shadow-sm">
                    <div class="w-3 h-3 bg-[#C5A059] rounded-sm mx-auto mb-1 rotate-45"></div>
                    <span class="text-[9px] font-black text-gray-500 uppercase">+10</span>
                </div>
                <div class="bg-white/80 border border-gray-200 rounded-xl p-2 text-center shadow-sm">
                    <div class="w-3 h-3 bg-[#06b6d4] rounded-sm mx-auto mb-1 rotate-45"></div>
                    <span class="text-[9px] font-black text-cyan-600 uppercase">+50</span>
                </div>
                <div class="bg-white/80 border border-gray-200 rounded-xl p-2 text-center shadow-sm">
                    <div class="w-3 h-3 bg-[#ef4444] rounded-sm mx-auto mb-1 rotate-45"></div>
                    <span class="text-[9px] font-black text-red-500 uppercase">-20</span>
                </div>
            </div>

            <div class="inline-flex items-center gap-3 px-5 py-2.5 bg-white/90 border border-amber-200 rounded-full text-[10px] text-amber-700 uppercase tracking-widest font-black mt-6 shadow-sm">
                <span class="relative flex h-2 w-2">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2 w-2 bg-amber-500"></span>
                </span>
                Wartungsmodus aktiv
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            if (typeof THREE === 'undefined') return;

            const container = document.getElementById('funki-game-container');
            const scene = new THREE.Scene();
            // Leichter Nebel passend zum Hintergrund
            scene.fog = new THREE.FogExp2(0xf8fafc, 0.02);

            const camera = new THREE.PerspectiveCamera(70, container.offsetWidth / container.offsetHeight, 0.1, 1000);
            const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });

            renderer.setSize(container.offsetWidth, container.offsetHeight);
            renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
            container.appendChild(renderer.domElement);

            // Elegantes Lighting
            const ambientLight = new THREE.AmbientLight(0xffffff, 0.7);
            scene.add(ambientLight);

            const directionalLight = new THREE.DirectionalLight(0xffffff, 1.5);
            directionalLight.position.set(10, 20, 10);
            scene.add(directionalLight);

            const pointLight = new THREE.PointLight(0xC5A059, 2);
            pointLight.position.set(-5, 5, 5);
            scene.add(pointLight);

            camera.position.z = 12;

            const interactables = [];
            const particles = [];
            const raycaster = new THREE.Raycaster();
            const mouse = new THREE.Vector2();

            // Geometrie Basis (wird später skaliert für Kristall-Form)
            const baseGeometry = new THREE.OctahedronGeometry(0.5, 0);

            // Materialien
            const materials = {
                gold: new THREE.MeshPhysicalMaterial({ color: 0xC5A059, metalness: 0.8, roughness: 0.2, transparent: true, opacity: 0.9, flatShading: true }),
                rare: new THREE.MeshPhysicalMaterial({ color: 0x06b6d4, metalness: 0.5, roughness: 0.1, transmission: 0.5, transparent: true, opacity: 0.9, flatShading: true }),
                danger: new THREE.MeshPhysicalMaterial({ color: 0xef4444, metalness: 0.3, roughness: 0.8, transparent: true, opacity: 0.9, flatShading: true })
            };

            function spawnObject() {
                const rand = Math.random();
                let type = 'gold';
                let pts = 10;
                let mat = materials.gold;
                let scaleY = 1.8; // Längliche Kristalle
                let speedMulti = 1;

                if (rand < 0.15) {
                    type = 'rare';
                    pts = 50;
                    mat = materials.rare;
                    scaleY = 2.2;
                    speedMulti = 1.5;
                } else if (rand < 0.35) {
                    type = 'danger';
                    pts = -20;
                    mat = materials.danger;
                    scaleY = 1.0; // Eher kugelig/gedrungen
                    speedMulti = 1.2;
                }

                const mesh = new THREE.Mesh(baseGeometry, mat);
                mesh.scale.set(1, scaleY, 1);

                // Spawne überall, aber mit Tendenz nach außen, damit sie nicht alle hinterm Modal sind
                const rangeX = 14;
                mesh.position.x = (Math.random() - 0.5) * rangeX;
                mesh.position.y = 15 + Math.random() * 5;
                mesh.position.z = (Math.random() - 0.5) * 6; // Tiefeneffekt

                mesh.userData = {
                    points: pts,
                    type: type,
                    speed: (0.03 + Math.random() * 0.04) * speedMulti,
                    rotX: (Math.random() - 0.5) * 0.05,
                    rotY: (Math.random() - 0.5) * 0.05
                };

                scene.add(mesh);
                interactables.push(mesh);
            }

            // --- Partikel System für Explosionen ---
            const particleGeo = new THREE.BoxGeometry(0.1, 0.1, 0.1);
            function createExplosion(position, colorHex) {
                const pMaterial = new THREE.MeshBasicMaterial({ color: colorHex, transparent: true });
                for (let i = 0; i < 8; i++) {
                    const p = new THREE.Mesh(particleGeo, pMaterial);
                    p.position.copy(position);
                    p.userData = {
                        velocity: new THREE.Vector3((Math.random() - 0.5) * 0.3, (Math.random() - 0.5) * 0.3, (Math.random() - 0.5) * 0.3),
                        life: 1.0
                    };
                    scene.add(p);
                    particles.push(p);
                }
            }

            // --- DOM Floating Text ---
            function showFloatingText(x, y, points) {
                const el = document.createElement('div');
                const isPositive = points > 0;
                el.className = `floating-score font-black text-2xl absolute pointer-events-none z-50 ${isPositive ? 'text-emerald-500 drop-shadow-[0_0_8px_rgba(16,185,129,0.5)]' : 'text-red-500 drop-shadow-[0_0_8px_rgba(239,68,68,0.5)]'}`;
                el.innerText = isPositive ? `+${points}` : points;
                el.style.left = `${x}px`;
                el.style.top = `${y}px`;
                document.getElementById('maintenance-game-wrapper').appendChild(el);

                setTimeout(() => el.remove(), 1000);
            }

            function onInteraction(event) {
                // Verhindern, dass Klicks auf das UI-Fenster als Game-Klicks gewertet werden
                if(event.target.closest('.pointer-events-auto')) return;

                const clientX = event.touches ? event.touches[0].clientX : event.clientX;
                const clientY = event.touches ? event.touches[0].clientY : event.clientY;

                const rect = container.getBoundingClientRect();
                mouse.x = ((clientX - rect.left) / container.offsetWidth) * 2 - 1;
                mouse.y = -((clientY - rect.top) / container.offsetHeight) * 2 + 1;

                raycaster.setFromCamera(mouse, camera);
                const intersects = raycaster.intersectObjects(interactables);

                if (intersects.length > 0) {
                    const obj = intersects[0].object;
                    const pts = obj.userData.points;

                    // Visuelles Feedback
                    createExplosion(obj.position, obj.material.color.getHex());
                    showFloatingText(clientX, clientY, pts);

                    // Objekt entfernen
                    const index = interactables.indexOf(obj);
                    if (index > -1) interactables.splice(index, 1);
                    scene.remove(obj);

                    // Punkte an Alpine.js senden
                    window.dispatchEvent(new CustomEvent('update-score', { detail: { points: pts } }));
                }
            }

            container.addEventListener('mousedown', onInteraction);
            container.addEventListener('touchstart', (e) => {
                if(!e.target.closest('.pointer-events-auto')) e.preventDefault();
                onInteraction(e);
            }, {passive: false});

            // Initiale Objekte spawnen
            for(let i=0; i<10; i++) {
                spawnObject();
                interactables[i].position.y = (Math.random() - 0.5) * 15; // In der Szene verteilen
            }

            function animate() {
                requestAnimationFrame(animate);

                // Kristalle animieren
                for (let i = interactables.length - 1; i >= 0; i--) {
                    let obj = interactables[i];
                    obj.position.y -= obj.userData.speed;
                    obj.rotation.x += obj.userData.rotX;
                    obj.rotation.y += obj.userData.rotY;

                    // Wenn unten rausgefallen -> Löschen
                    if (obj.position.y < -15) {
                        scene.remove(obj);
                        interactables.splice(i, 1);
                    }
                }

                // Partikel animieren
                for (let i = particles.length - 1; i >= 0; i--) {
                    let p = particles[i];
                    p.position.add(p.userData.velocity);
                    p.userData.life -= 0.02;
                    p.material.opacity = p.userData.life;
                    p.rotation.x += 0.1;
                    p.scale.setScalar(p.userData.life);

                    if (p.userData.life <= 0) {
                        scene.remove(p);
                        particles.splice(i, 1);
                    }
                }

                // Neue Kristalle spawnen (max 25 gleichzeitig)
                if (Math.random() < 0.03 && interactables.length < 25) {
                    spawnObject();
                }

                renderer.render(scene, camera);
            }

            window.addEventListener('resize', () => {
                camera.aspect = container.offsetWidth / container.offsetHeight;
                camera.updateProjectionMatrix();
                renderer.setSize(container.offsetWidth, container.offsetHeight);
            });

            animate();
        });
    </script>

    <style>
        #funki-game-container canvas {
            display: block;
            width: 100% !important;
            height: 100% !important;
        }

        /* Floating Animation für die Punkte */
        @keyframes floatScore {
            0% { transform: translate(-50%, -50%) scale(1); opacity: 1; }
            50% { transform: translate(-50%, -100%) scale(1.5); opacity: 1; }
            100% { transform: translate(-50%, -150%) scale(1); opacity: 0; }
        }

        .floating-score {
            animation: floatScore 1s cubic-bezier(0.16, 1, 0.3, 1) forwards;
            user-select: none;
        }
    </style>
</div>
