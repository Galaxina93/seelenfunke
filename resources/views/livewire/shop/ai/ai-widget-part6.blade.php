                t3.startTime = performance.now();
                t3.lastActivityTime = performance.now();

                if (t3.resizeListener) {
                    window.removeEventListener('resize', t3.resizeListener);
                }
                t3.resizeListener = this.onWindowResize.bind(this);
                window.addEventListener('resize', t3.resizeListener);

                t3.raycaster = new THREE.Raycaster();
                t3.mouse = new THREE.Vector2(-9999, -9999);

                if (t3.clickListener) {
                    window.removeEventListener('pointerdown', t3.clickListener);
                }
                t3.clickListener = (event) => {
                    t3.lastActivityTime = performance.now();
                };
                window.addEventListener('pointerdown', t3.clickListener);

                t3.currentColor = new THREE.Color(0x00ff88);
                t3.targetColor = new THREE.Color(0x00ff88);
                t3.speakingColor = new THREE.Color(0x8A2BE2);
                t3.currentPixelRatio = 0.85;
                t3.currentThinking = 0.0;
                t3.targetThinking = 0.0;

                this.updateCoreColor(true);

                this.animate();
            },

            updateCoreColor(instant = false) {
                if(!t3.raymarchUniforms || !t3.coreLight || !t3.targetColor) return;

                let targetColorHex = this.getColorHex(this.agentColor); 
                if (this.thinking) {
                    targetColorHex = 0xff66b2; 
                } else if (this.systemState === 'warning') {
                    targetColorHex = 0xffcc00; 
                } else if (this.systemState === 'error' || this.systemState === false) {
                    targetColorHex = 0xff3333; 
                }

                t3.targetColor.setHex(targetColorHex);
                t3.targetThinking = this.thinking ? 1.0 : 0.0;

                if (instant) {
                    t3.currentColor.copy(t3.targetColor);
                    t3.currentThinking = t3.targetThinking;

                    t3.raymarchUniforms.glowColor.value.copy(t3.currentColor);
                    t3.raymarchUniforms.isThinking.value = t3.currentThinking;
                    t3.coreLight.color.copy(t3.currentColor);
                }
            },

            animate() {
                if (!this.showFunkiView) return;

                t3.animationId = requestAnimationFrame(this.boundAnimate);
                if (document.hidden) return;

                // Echte 60 FPS durchgehend! Im verkleinerten Eck-Modus (Map / Gehirn / Top Secret)
                // setzen wir das WebGL-Canvas physisch auf 300x300 px statt einen 2-Millionen-Pixel-Fullscreen-Buffer über Mapbox zu blenden!
                if (t3.renderer && t3.camera) {
                    const isCornerOverlay = (this.isMapFocus || this.isSecretMode || this.isBrainMode || this.jarvisMinimized) && !t3.isShuttingDown;
                    const desiredW = isCornerOverlay ? 300 : Math.max(window.innerWidth || 1280, 320);
                    const desiredH = isCornerOverlay ? 300 : Math.max(window.innerHeight || 720, 240);
                    const desiredPR = isCornerOverlay ? 0.85 : Math.min(window.devicePixelRatio || 1, 0.85);
                    if (t3.currentCanvasW !== desiredW || t3.currentCanvasH !== desiredH || Math.abs((t3.currentPixelRatio || 0) - desiredPR) > 0.02) {
                        t3.currentCanvasW = desiredW;
                        t3.currentCanvasH = desiredH;
                        t3.currentPixelRatio = desiredPR;
                        t3.renderer.setPixelRatio(desiredPR);
                        t3.renderer.setSize(desiredW, desiredH, true);
                        t3.camera.aspect = desiredW / desiredH;
                        t3.camera.updateProjectionMatrix();
                    }
                }

                const now = performance.now();
                const delta = t3.lastFrameTime ? Math.min((now - t3.lastFrameTime) / 1000.0, 0.05) : 0.016;
                t3.lastFrameTime = now;

                const elapsedTime = now - t3.startTime;
                const time = now * 0.001;
                let initProg = 0.0;
                let shutProg = 0.0;

                if(t3.raymarchUniforms) {
                    if (t3.currentColor && t3.targetColor) {
                        const lerpSpeed = Math.min(delta * 6.0, 0.25);
                        t3.currentColor.lerp(t3.targetColor, lerpSpeed);
                        t3.currentThinking += (t3.targetThinking - t3.currentThinking) * lerpSpeed;

                        if (!t3.speakingColor) t3.speakingColor = new THREE.Color();
                        t3.speakingColor.setHex((this.isSpeaking || this.isOutputActive()) ? 0x8A2BE2 : this.getColorHex(this.agentColor));
                        t3.currentColor.lerp(t3.speakingColor, Math.min(delta * 7.5, 0.3));

                        t3.raymarchUniforms.glowColor.value.copy(t3.currentColor);
                        t3.raymarchUniforms.isThinking.value = t3.currentThinking;

                        if(t3.coreLight) t3.coreLight.color.copy(t3.currentColor);
                    }

                    // Schnellerer, knackigerer Kern-Aufbau (900ms statt 3000ms!)
                    initProg = Math.min(elapsedTime / 900.0, 1.0);

                    t3.raymarchUniforms.time.value = time;
                    t3.raymarchUniforms.cameraPos.value.copy(t3.camera.position);
                    t3.raymarchUniforms.initProgress.value = initProg;

                    if (t3.isShuttingDown) {
                        const sdTime = now - t3.shutdownTime;
                        shutProg = Math.min(sdTime / 650.0, 1.0);
                        t3.raymarchUniforms.shutdownProgress.value = shutProg;
                    } else if (t3.isTransformingToJarvis) {
                        const transTime = now - t3.transformStartTime;
                        let transProg = Math.min(transTime / 450.0, 1.0);
                        t3.raymarchUniforms.shutdownProgress.value = transProg;
                        shutProg = transProg;
                    } else if (t3.isTransformingFromJarvis) {
                        const transTime = now - t3.transformStartTime;
                        let transProg = Math.max(1.0 - (transTime / 450.0), 0.0);
                        t3.raymarchUniforms.shutdownProgress.value = transProg;
                        shutProg = transProg;
                    } else {
                        t3.raymarchUniforms.shutdownProgress.value = 0.0;
                    }
                }

                if (t3.planetMesh) {
                    t3.planetMesh.rotation.y = time * 0.05;
                    t3.planetMesh.material.opacity = initProg * (1.0 - shutProg) * 0.6; 
                }



                if (t3.coreMesh) {
                    let speechPulse = 0;
                    if (this.isSpeaking || this.isOutputActive()) {
                        // Dezentes, weiches Pulsieren beim Sprechen
                        speechPulse = (Math.sin(time * 15.0) * 0.03 + Math.sin(time * 5.0) * 0.02);
                        
                        if (t3.raymarchUniforms) {
                            // Frame-rate-unabhängiges Aufwallen (delta-skaliert!)
                            t3.raymarchUniforms.hoverState.value += (1.1 - t3.raymarchUniforms.hoverState.value) * Math.min(delta * 6.0, 1.0);
                        }
                    } else if (t3.raymarchUniforms) {
                        // Smoothly beruhigen (delta-skaliert!)
                        t3.raymarchUniforms.hoverState.value += (0.0 - t3.raymarchUniforms.hoverState.value) * Math.min(delta * 6.0, 1.0);
                    }
                    
                    const pulse = 0.75 + Math.sin(time * 3.0) * 0.015 + speechPulse;
                    t3.coreMesh.scale.set(pulse, pulse, pulse);
                }

                if (t3.hudRings) {
                    const frameScale = delta * 60.0;
                    t3.hudRings.forEach((ring, index) => {
                        let baseSpeed = ring.userData.speed * frameScale;
                        let speechPulseOpacity = 0;
                        let speechScale = 1.0;
                        
                        if (this.isSpeaking || this.isOutputActive()) {
                            baseSpeed += baseSpeed * (Math.random() * 5.0 + 3.0);
                            speechPulseOpacity = Math.random() * 0.4 + Math.sin(time * 20.0 + index) * 0.2;
                            speechScale = 1.0 + (Math.random() * 0.05);
                        }
                        
                        ring.rotation.z += baseSpeed;
                        ring.scale.set(speechScale, speechScale, speechScale);
                        
                        if (ring.userData && ring.userData.solidMat) {
                            ring.userData.solidMat.opacity = ring.userData.baseOpacitySolid + Math.sin(time * 2.0 + index) * 0.02 + speechPulseOpacity;
                            ring.userData.solidMat.opacity = Math.min(ring.userData.solidMat.opacity * initProg * (1.0 - shutProg), 1.0);
                        }
                    });
                }
                
                if (t3.outerGrid) {
                    t3.outerGrid.rotation.y -= 0.3 * delta;
                    t3.outerGrid.rotation.x += 0.12 * delta;
                    t3.outerGrid.material.opacity = 0.05 * initProg * (1.0 - shutProg);
                }

                // FaceGroup animation completely removed

                let panelsVisible = this.showInfoPanel || this.showChartPanel || this.showErrorPanel;

                let targetFov = panelsVisible ? 60 : 50;
                if (Math.abs(t3.camera.fov - targetFov) > 0.1) {
                    t3.camera.fov += (targetFov - t3.camera.fov) * Math.min(delta * 3.0, 1.0);
                    t3.camera.updateProjectionMatrix();
                }

                // CSS3DObject und CSS3DRenderer wurden für 2D UI entfernt

                if (!t3.lastActivityTime) t3.lastActivityTime = now;

                if (this.thinking || this.isOutputActive()) {
                    t3.lastActivityTime = now;
                }

                if (t3.targetCameraDist && t3.camera && t3.controls) {
                    let currentDist = t3.camera.position.distanceTo(t3.controls.target);
                    if (Math.abs(currentDist - t3.targetCameraDist) > 1.0) {
                        let newDist = currentDist + (t3.targetCameraDist - currentDist) * Math.min((t3.zoomSpeed || 0.08) * delta * 60.0, 1.0);
                        t3.camera.position.sub(t3.controls.target).setLength(newDist).add(t3.controls.target);
                        
                        t3.controls.maxDistance = Math.max(800, newDist + 200);
                    }
                }

                if (t3.controls) {
                    // Im Eck-Modus (pointer-events-none) sparen wir uns das per-Frame OrbitControls-Matrix-Update und rotieren zeitbasiert!
                    const isCornerNow = (this.isMapFocus || this.isSecretMode || this.isBrainMode || this.jarvisMinimized) && !t3.isShuttingDown;
                    if (!isCornerNow) {
                        t3.controls.autoRotateSpeed = 0.65 * Math.min(Math.max(delta * 60.0, 0.5), 2.5);
                        t3.controls.update();
                    }
                }

                if(t3.renderer && t3.scene && t3.camera) {
                    t3.renderer.render(t3.scene, t3.camera);
                }

                // cssRenderer render loop entfernt
            },

            onWindowResize() {
                if (!t3.camera || !t3.renderer) return;
                const isCornerOverlay = (this.isMapFocus || this.isSecretMode || this.isBrainMode || this.jarvisMinimized) && !t3.isShuttingDown;
                const w = isCornerOverlay ? 300 : window.innerWidth;
                const h = isCornerOverlay ? 300 : window.innerHeight;
                t3.currentCanvasW = w;
                t3.currentCanvasH = h;
                t3.camera.aspect = w / h;
                t3.camera.updateProjectionMatrix();
                t3.renderer.setSize(w, h);
                // cssRenderer resize entfernt
            },

            destroyThreeJS() {
                if (t3.animationId) {
                    cancelAnimationFrame(t3.animationId);
                    t3.animationId = null;
                }
                if (t3.resizeListener) {
                    window.removeEventListener('resize', t3.resizeListener);
                    t3.resizeListener = null;
                }
                if(t3.clickListener) {
                    window.removeEventListener('pointerdown', t3.clickListener);
                    t3.clickListener = null;
                }

                if (t3.controls) {
                    t3.controls.dispose();
                    t3.controls = null;
                }

                if (t3.scene) {
                    t3.scene.traverse((object) => {
                        if (object.isMesh || object.isPoints || object.isLine) {
                            if (object.geometry) object.geometry.dispose();
                            if (object.material) {
                                if (Array.isArray(object.material)) object.material.forEach(m => m.dispose());
                                else object.material.dispose();
                            }
                        }
                    });

                    // cssObject cleanup entfernt
                    t3.scene.clear();
                    t3.scene = null;
                }

                if (t3.renderer) {
                    t3.renderer.dispose();
                    t3.renderer = null;
                }
                
                // cssRenderer cleanup entfernt
                
                t3.camera = null;

                const container = document.getElementById('funki-canvas-container');
                if (container) container.innerHTML = '';
            }
        }));
    });

    if (window.Alpine) {
        window.registerFunkiView();
    } else {
        document.addEventListener('alpine:init', window.registerFunkiView);
    }
</script>
