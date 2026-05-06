            initThreeJS() {
                const container = document.getElementById('funki-canvas-container');
                if (!container) return;

                container.innerHTML = '';

                t3.scene = new THREE.Scene();

                t3.camera = new THREE.PerspectiveCamera(50, window.innerWidth / window.innerHeight, 1.0, 3000);
                t3.camera.position.z = 500;
                t3.camera.position.y = 50;
                t3.camera.lookAt(0, 0, 0);

                if (!t3.renderer) {
                    t3.renderer = new THREE.WebGLRenderer({ antialias: false, alpha: true, powerPreference: "high-performance" });
                    t3.renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 0.75)); 
                }
                container.appendChild(t3.renderer.domElement);
                t3.renderer.setSize(window.innerWidth, window.innerHeight);
                t3.camera.aspect = window.innerWidth / window.innerHeight;
                t3.camera.updateProjectionMatrix();

                // Bind OrbitControls directly to WebGLRenderer
                t3.controls = new THREE.OrbitControls(t3.camera, t3.renderer.domElement);
                t3.controls.enableDamping = true;
                t3.controls.dampingFactor = 0.05;
                t3.controls.autoRotate = true; 
                t3.controls.autoRotateSpeed = 0.3;
                t3.controls.maxDistance = 800;
                t3.controls.minDistance = 200;

                t3.scene.add(new THREE.AmbientLight(0x111122));
                t3.coreLight = new THREE.PointLight(0x10b981, 2, 400);
                t3.scene.add(t3.coreLight);

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
                    uniform float isThinking; 

                    varying vec3 vWorldPosition;
                    varying vec3 vLocalPosition;

                    mat3 rotY(float a) {
                        float s = sin(a), c = cos(a);
                        return mat3(c, 0.0, s, 0.0, 1.0, 0.0, -s, 0.0, c);
                    }
                    mat3 rotX(float a) {
                        float s = sin(a), c = cos(a);
                        return mat3(1.0, 0.0, 0.0, 0.0, c, -s, 0.0, s, c);
                    }

                    float smoothFluid(vec3 p) {
                        float timeScale = time * (1.2 + isThinking * 2.0); 

                        float l = length(p.xz);
                        float angle = l * 0.05 - timeScale * 0.8;
                        float s = sin(angle), c = cos(angle);
                        p.xz *= mat2(c, -s, s, c);

                        float d1 = sin(p.x*0.06 + timeScale) * cos(p.y*0.05 - timeScale*0.8) * sin(p.z*0.07 + timeScale);
                        float d2 = cos(p.x*0.12 - timeScale*1.5) * sin(p.y*0.13 + timeScale*1.2) * cos(p.z*0.11 - timeScale);
                        float d3 = sin(p.x*0.25 + p.y*0.25 + timeScale*3.0) * cos(p.z*0.25 - timeScale*2.0);

                        return (d1 * 18.0) + (d2 * 9.0) + (d3 * 4.0);
                    }

                    float map(vec3 p) {
                        float critical = min(hoverTime / 12.0, 1.0); 

                        float rotSpeed = time * (0.02 + hoverState * 0.05 + critical * 0.03 + isThinking * 0.15);
                        p = rotY(rotSpeed) * rotX(rotSpeed * 0.6) * p;

                        float baseRadius = 45.0 + hoverState * 8.0 + critical * 12.0;
                        baseRadius *= smoothstep(0.0, 0.8, initProgress);
                        baseRadius *= (1.0 - smoothstep(0.0, 1.0, shutdownProgress));

                        float displacement = smoothFluid(p);

                        float d = length(p) - baseRadius;
                        d -= displacement * smoothstep(0.2, 1.0, initProgress);

                        if (shutdownProgress > 0.01) {
                            float melt = sin(p.y * 0.15 - time * 4.0) * cos(p.x * 0.15) * 45.0;
                            d += melt * shutdownProgress;
                        }
                        // Shader eyes removed to prevent double eyes
                        return d;
                    }

                    void main() {
                        vec3 ro = cameraPos;
                        vec3 rd = normalize(vWorldPosition - ro);

                        float t = distance(ro, vWorldPosition) + 0.1;
                        float maxT = t + 240.0; 

                        vec3 accumulatedColor = vec3(0.0);
                        float accumulatedAlpha = 0.0;

                        float critical = min(hoverTime / 12.0, 1.0);

                        vec3 currentGlowColor = mix(glowColor, glowColor * 1.05, hoverState * 0.95);
                        currentGlowColor = mix(currentGlowColor, currentGlowColor * 1.1, isThinking);

                        vec3 criticalColor = vec3(1.0, 0.05, 0.0); 
                        currentGlowColor = mix(currentGlowColor, criticalColor * 1.2, critical);

                        vec3 hotCoreColor = currentGlowColor * (1.1 + critical * 0.5 + isThinking * 0.2); 

                        for(int i = 0; i < 45; i++) {
                            vec3 p = ro + rd * t;
                            float d = map(p);

                            float proximity = abs(d);
                            float density = exp(-proximity * 0.08);

                            if (d < 0.0) {
                                accumulatedColor += hotCoreColor * 0.1;
                                accumulatedAlpha += 0.2;
                            } else {
                                accumulatedColor += currentGlowColor * density * 0.1;
                                accumulatedColor += hotCoreColor * exp(-proximity * 0.4) * 0.03; 
                                accumulatedAlpha += density * 0.02;
                            }

                            t += max(proximity * 0.5, 0.5);

                            if (t > maxT || accumulatedAlpha > 0.99) break;
                        }

                        float speedBoost = critical * 5.0 + isThinking * 25.0;
                        float breathingPulse = 0.8 + 0.3 * sin(time * (3.0 + speedBoost));
                        float quickPulse = 0.7 + 0.5 * sin(time * (8.0 + speedBoost));
                        float combinedPulse = mix(breathingPulse, quickPulse, hoverState);

                        vec3 finalColor = accumulatedColor * combinedPulse * smoothstep(0.2, 1.0, initProgress);

                        float softWaggle = sin(vWorldPosition.x * 0.03 + time * 3.0) * (1.5 + isThinking * 1.0);
                        finalColor += currentGlowColor * max(hoverState, isThinking * 0.5) * softWaggle * 0.15;

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
                    shutdownProgress: { value: 0.0 },
                    isThinking: { value: 0.0 } 
                };

                t3.coreMaterial = new THREE.ShaderMaterial({
                    uniforms: t3.raymarchUniforms,
                    vertexShader: raymarchVertexShader,
                    fragmentShader: raymarchFragmentShader,
                    transparent: true,
                    side: THREE.FrontSide, 
                    depthWrite: false
                });

                t3.defaultGeometry = coreGeometry;
                t3.coreMesh = new THREE.Mesh(coreGeometry, t3.coreMaterial);
                t3.scene.add(t3.coreMesh);


                const hitboxGeo = new THREE.SphereGeometry(35, 16, 16); 
                const hitboxMat = new THREE.MeshBasicMaterial({ visible: false });
                t3.hitboxMesh = new THREE.Mesh(hitboxGeo, hitboxMat);
                t3.scene.add(t3.hitboxMesh);

                /* ========================================================= */
                /* ====== KI-KERN (SPHERE) & ABSTRAKTE VISUALISIERUNG ====== */
                /* ========================================================= */
                // Der eigentliche 3D-Kern wird durch raymarchUniforms.hoverState gesteuert.
                // Animationen der Ringe wurden entfernt, um den Fokus auf das Pulsieren zu legen.
                t3.hudRings = []; // Beibehalten als leeres Array, um Fehler in part6 zu vermeiden.

                /* ========================================================= */
                /* ================== KI-KERN ENDE ========================= */
                /* ========================================================= */

                if (THREE.CSS3DRenderer && THREE.CSS3DObject) {
                    const panelElement = document.getElementById('diagnostic-panel');
                    if (panelElement) {
                        t3.cssObject = new THREE.CSS3DObject(panelElement);
                        t3.cssObject.scale.set(0.85, 0.85, 0.85); 
                        t3.cssObject.position.set(-9999, 0, 0);
                        t3.scene.add(t3.cssObject);
                    }
                }
