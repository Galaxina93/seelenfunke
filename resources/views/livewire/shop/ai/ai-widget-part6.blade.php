                t3.startTime = performance.now();
                t3.lastActivityTime = performance.now();

                window.addEventListener('resize', this.onWindowResize.bind(this));

                t3.raycaster = new THREE.Raycaster();
                t3.mouse = new THREE.Vector2(-9999, -9999);

                t3.clickListener = (event) => {
                    t3.lastActivityTime = performance.now();
                };
                window.addEventListener('pointerdown', t3.clickListener);

                t3.currentColor = new THREE.Color(0x00ff88);
                t3.targetColor = new THREE.Color(0x00ff88);
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

                const now = performance.now();
                const delta = t3.lastFrameTime ? (now - t3.lastFrameTime) / 1000.0 : 0.016;
                t3.lastFrameTime = now;

                const elapsedTime = now - t3.startTime;

                const time = performance.now() * 0.001;
                let initProg = 0.0;
                let shutProg = 0.0;

                if(t3.raymarchUniforms) {
                    if (t3.currentColor && t3.targetColor) {
                        t3.currentColor.lerp(t3.targetColor, 0.04);
                        t3.currentThinking += (t3.targetThinking - t3.currentThinking) * 0.04;

                        t3.raymarchUniforms.glowColor.value.copy(t3.currentColor);
                        t3.raymarchUniforms.isThinking.value = t3.currentThinking;

                        // Lila Farbe erzwingen, wenn die KI spricht
                        const targetColor = new THREE.Color((this.isSpeaking || this.isOutputActive()) ? 0x8A2BE2 : this.getColorHex(this.agentColor));
                        t3.currentColor.lerp(targetColor, 0.05);

                        if(t3.coreLight) t3.coreLight.color.copy(t3.currentColor);
                        
                        // Let the HUD rings inherit the core's color, but slightly dimmed
                        if (t3.hudRings) {
                            t3.hudRings.forEach(ring => {
                                if (ring.userData && ring.userData.solidMat) {
                                    ring.userData.solidMat.color.copy(t3.currentColor);
                                }
                            });
                        }
                        if (t3.outerGrid) {
                            t3.outerGrid.material.color.copy(t3.currentColor);
                        }
                    }

                    initProg = Math.min(elapsedTime / 3000.0, 1.0);

                    t3.raymarchUniforms.time.value = time;
                    t3.raymarchUniforms.cameraPos.value.copy(t3.camera.position);
                    t3.raymarchUniforms.initProgress.value = initProg;

                    if (t3.isShuttingDown) {
                        const sdTime = performance.now() - t3.shutdownTime;
                        shutProg = Math.min(sdTime / 3500.0, 1.0);
                        t3.raymarchUniforms.shutdownProgress.value = shutProg;
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
                            // Leichtes Aufwallen des inneren Shaders
                            t3.raymarchUniforms.hoverState.value += (1.1 - t3.raymarchUniforms.hoverState.value) * 0.1;
                        }
                    } else if (t3.raymarchUniforms) {
                        // Smoothly beruhigen
                        t3.raymarchUniforms.hoverState.value += (0.0 - t3.raymarchUniforms.hoverState.value) * 0.1;
                    }
                    
                    const pulse = 0.75 + Math.sin(time * 3.0) * 0.015 + speechPulse;
                    t3.coreMesh.scale.set(pulse, pulse, pulse);
                }

                if (t3.hudRings) {
                    t3.hudRings.forEach((ring, index) => {
                        let baseSpeed = ring.userData.speed;
                        let speechPulseOpacity = 0;
                        let speechScale = 1.0;
                        
                        if (this.isSpeaking || this.isOutputActive()) {
                            // Ringe drehen sich rasend schnell und reagieren auf Audio
                            baseSpeed += baseSpeed * (Math.random() * 5.0 + 3.0);
                            speechPulseOpacity = Math.random() * 0.4 + Math.sin(time * 20.0 + index) * 0.2;
                            speechScale = 1.0 + (Math.random() * 0.05); // Leichtes Zittern in der Größe
                        }
                        
                        ring.rotation.z += baseSpeed;
                        ring.scale.set(speechScale, speechScale, speechScale);
                        
                        if (ring.userData && ring.userData.solidMat) {
                            // Starkes Aufblinken beim Sprechen
                            ring.userData.solidMat.opacity = ring.userData.baseOpacitySolid + Math.sin(time * 2.0 + index) * 0.02 + speechPulseOpacity;
                            ring.userData.solidMat.opacity = Math.min(ring.userData.solidMat.opacity * initProg * (1.0 - shutProg), 1.0);
                        }
                    });
                }
                
                if (t3.outerGrid) {
                    t3.outerGrid.rotation.y -= 0.005;
                    t3.outerGrid.rotation.x += 0.002;
                    t3.outerGrid.material.opacity = 0.05 * initProg * (1.0 - shutProg);
                }

                // FaceGroup animation completely removed

                let panelsVisible = this.showInfoPanel || this.showChartPanel || this.showErrorPanel;

                let targetFov = panelsVisible ? 60 : 50;
                if (Math.abs(t3.camera.fov - targetFov) > 0.1) {
                    t3.camera.fov += (targetFov - t3.camera.fov) * 0.05;
                    t3.camera.updateProjectionMatrix();
                }

                // CSS3DObject und CSS3DRenderer wurden für 2D UI entfernt

                if (!t3.lastActivityTime) t3.lastActivityTime = now;

                if (this.thinking || this.isOutputActive()) {
                    t3.lastActivityTime = now;
                }

                const idleSeconds = (now - t3.lastActivityTime) / 1000.0;

                if (t3.targetCameraDist && t3.camera && t3.controls) {
                    let currentDist = t3.camera.position.distanceTo(t3.controls.target);
                    if (Math.abs(currentDist - t3.targetCameraDist) > 1.0) {
                        let newDist = currentDist + (t3.targetCameraDist - currentDist) * t3.zoomSpeed;
                        t3.camera.position.sub(t3.controls.target).setLength(newDist).add(t3.controls.target);
                        
                        t3.controls.maxDistance = Math.max(800, newDist + 200);
                    }
                }

                if (t3.controls) {
                    t3.controls.update();
                }

                if(t3.renderer && t3.scene && t3.camera) {
                    t3.renderer.render(t3.scene, t3.camera);
                }

                // cssRenderer render loop entfernt
            },

            onWindowResize() {
                if (!t3.camera || !t3.renderer) return;
                t3.camera.aspect = window.innerWidth / window.innerHeight;
                t3.camera.updateProjectionMatrix();
                t3.renderer.setSize(window.innerWidth, window.innerHeight);
                // cssRenderer resize entfernt
            },

            destroyThreeJS() {
                if (t3.animationId) {
                    cancelAnimationFrame(t3.animationId);
                }
                window.removeEventListener('resize', this.onWindowResize);
                if(t3.clickListener) {
                    window.removeEventListener('click', t3.clickListener);
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
</script>
