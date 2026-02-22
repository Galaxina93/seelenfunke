<div class="w-full relative min-h-[750px] flex flex-col items-center justify-center overflow-hidden bg-slate-50"
     x-data="{ score: 0 }"
     @update-score.window="score++" {{-- HIER: Neues Event-Listening --}}
     id="maintenance-game-wrapper">

    {{-- Three.js Canvas Container --}}
    <div id="funki-game-container" class="absolute inset-0 z-0 cursor-crosshair"></div>

    {{-- UI Overlay --}}
    <div class="relative z-10 pointer-events-none select-none text-center max-w-xl mx-auto bg-white/70 backdrop-blur-md p-8 md:p-12 rounded-[3rem] shadow-[0_20px_50px_rgba(0,0,0,0.05)] border border-white/80 my-12">
        <div class="mb-6 flex justify-center">
            <img src="{{ asset('images/projekt/logo/mein-seelenfunke-logo.png') }}" alt="Logo" class="h-20 w-auto drop-shadow-md">
        </div>

        <h1 class="text-3xl font-serif font-bold text-gray-900 mb-4 italic">
            Kreative Pause.
        </h1>

        <p class="text-gray-600 mb-6 text-sm leading-relaxed">
            Wir optimieren gerade den Shop für dich.<br>
            Fang die <span class="text-primary font-bold">goldenen Kristalle</span> am Rand ein!
        </p>

        <div class="flex flex-col items-center gap-4">
            <div class="bg-slate-900 text-white px-8 py-3 rounded-2xl font-mono text-2xl shadow-xl border-2 border-primary/50 pointer-events-auto transition-transform active:scale-95">
                <span class="text-primary">✨</span> <span x-text="score">0</span>
            </div>

            <div class="inline-flex items-center gap-3 px-5 py-2 bg-white/80 border border-amber-100 rounded-full text-[10px] text-gray-400 uppercase tracking-widest font-black">
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
            const camera = new THREE.PerspectiveCamera(75, container.offsetWidth / container.offsetHeight, 0.1, 1000);
            const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });

            renderer.setSize(container.offsetWidth, container.offsetHeight);
            renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
            container.appendChild(renderer.domElement);

            const ambientLight = new THREE.AmbientLight(0xffffff, 0.6);
            scene.add(ambientLight);
            const pointLight = new THREE.PointLight(0xC5A059, 3);
            pointLight.position.set(5, 5, 5);
            scene.add(pointLight);

            camera.position.z = 10;

            const crystals = [];
            const raycaster = new THREE.Raycaster();
            const mouse = new THREE.Vector2();
            const geometry = new THREE.OctahedronGeometry(0.5, 0); // Etwas größer

            function spawnCrystal() {
                const material = new THREE.MeshPhongMaterial({
                    color: 0xC5A059,
                    shininess: 150,
                    transparent: true,
                    opacity: 0.9,
                    flatShading: true
                });

                const crystal = new THREE.Mesh(geometry, material);

                // LOGIK: Spawne nur links ODER rechts vom Fenster (Fenster ist ca. 6 Einheiten breit)
                const side = Math.random() > 0.5 ? 1 : -1;
                const minDistance = 4; // Mindestabstand zur Mitte
                const maxDistance = 10;

                crystal.position.x = side * (minDistance + Math.random() * (maxDistance - minDistance));
                crystal.position.y = 12; // Start weit oben
                crystal.position.z = (Math.random() - 0.5) * 4;

                crystal.userData = {
                    speed: 0.015 + Math.random() * 0.02, // Deutlich langsamer
                    rot: (Math.random() - 0.5) * 0.02
                };

                scene.add(crystal);
                crystals.push(crystal);
            }

            function onInteraction(event) {
                const clientX = event.touches ? event.touches[0].clientX : event.clientX;
                const clientY = event.touches ? event.touches[0].clientY : event.clientY;

                const rect = container.getBoundingClientRect();
                mouse.x = ((clientX - rect.left) / container.offsetWidth) * 2 - 1;
                mouse.y = -((clientY - rect.top) / container.offsetHeight) * 2 + 1;

                raycaster.setFromCamera(mouse, camera);
                const intersects = raycaster.intersectObjects(crystals);

                if (intersects.length > 0) {
                    const obj = intersects[0].object;

                    // Treffer-Animation
                    obj.scale.set(2, 2, 2);
                    obj.material.opacity = 0;

                    const index = crystals.indexOf(obj);
                    if (index > -1) crystals.splice(index, 1);
                    setTimeout(() => scene.remove(obj), 50);

                    // --- SCORE UPDATE ---
                    window.dispatchEvent(new CustomEvent('update-score'));
                }
            }

            container.addEventListener('mousedown', onInteraction);
            container.addEventListener('touchstart', (e) => onInteraction(e), {passive: true});

            function animate() {
                requestAnimationFrame(animate);

                for (let i = crystals.length - 1; i >= 0; i--) {
                    crystals[i].position.y -= crystals[i].userData.speed;
                    crystals[i].rotation.x += crystals[i].userData.rot;
                    crystals[i].rotation.y += crystals[i].userData.rot;

                    if (crystals[i].position.y < -12) {
                        scene.remove(crystals[i]);
                        crystals.splice(i, 1);
                    }
                }

                if (Math.random() < 0.02 && crystals.length < 15) {
                    spawnCrystal();
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
        .cursor-crosshair {
            cursor: crosshair;
        }
    </style>
</div>
