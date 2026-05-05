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
                        if (t3.faceMatSolid) {
                            t3.faceMatSolid.color.copy(t3.currentColor);
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
                        // Dynamisches Pulsieren, das wie ein Audio-Ausschlag aussieht
                        speechPulse = (Math.random() * 0.15 + Math.sin(time * 25.0) * 0.1) * 1.2;
                    }
                    const pulse = 0.75 + Math.sin(time * 3.0) * 0.015 + speechPulse;
                    t3.coreMesh.scale.set(pulse, pulse, pulse);
                }

                if (t3.hudRings) {
                    t3.hudRings.forEach((ring, index) => {
                        let baseSpeed = ring.userData.speed;
                        let speechPulseOpacity = 0;
                        
                        if (this.isSpeaking || this.isOutputActive()) {
                            // Ringe drehen sich erratischer/schneller, wenn gesprochen wird
                            baseSpeed += baseSpeed * (Math.random() * 2.0);
                            speechPulseOpacity = Math.random() * 0.15;
                        }
                        
                        ring.rotation.z += baseSpeed;
                        
                        if (ring.userData && ring.userData.solidMat) {
                            // Pulse the opacity slightly
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

                if (t3.faceGroup && t3.faceMatSolid) {
                    // Face always looks at the camera
                    t3.faceGroup.lookAt(t3.camera.position);
                    
                    // Fade in/out depending on if speaking
                    let targetOpacitySolid = (this.isSpeaking || this.isOutputActive()) ? 0.8 : 0.0;
                    
                    t3.faceMatSolid.opacity += (targetOpacitySolid - t3.faceMatSolid.opacity) * 0.1;
                    
                    // Animate mouth equalizer
                    if (t3.mouthBars && (this.isSpeaking || this.isOutputActive())) {
                        t3.mouthBars.forEach((barObj, index) => {
                            // Fluid spectrum analyzer effect: combine random noise with a sine wave sweeping across
                            const wave = Math.sin(time * 15.0 + barObj.angle * 10.0);
                            const noise = Math.random() * 1.5;
                            const targetScale = 1.0 + Math.max(0, wave * 1.5) + noise;
                            
                            barObj.solid.scale.y += (targetScale - barObj.solid.scale.y) * 0.3;
                        });
                    } else if (t3.mouthBars) {
                        t3.mouthBars.forEach(barObj => {
                            barObj.solid.scale.y += (1.0 - barObj.solid.scale.y) * 0.2;
                        });
                    }
                }

                let panelsVisible = this.showInfoPanel || this.showChartPanel || this.showErrorPanel;

                let targetFov = panelsVisible ? 60 : 50;
                if (Math.abs(t3.camera.fov - targetFov) > 0.1) {
                    t3.camera.fov += (targetFov - t3.camera.fov) * 0.05;
                    t3.camera.updateProjectionMatrix();
                }

                if (t3.cssObject) {
                    if (panelsVisible) {
                        let dist = t3.camera.position.length();
                        let vFov = (t3.camera.fov * Math.PI) / 180;
                        let visibleHeight = 2 * Math.tan(vFov / 2) * dist;
                        let visibleWidth = visibleHeight * t3.camera.aspect;

                        let percentX = t3.camera.aspect > 1.2 ? -0.28 : -0.15;
                        let percentY = 0.08; 

                        let offsetX = visibleWidth * percentX;
                        let offsetY = visibleHeight * percentY;

                        if(!t3.tempRight) t3.tempRight = new THREE.Vector3();
                        if(!t3.tempUp) t3.tempUp = new THREE.Vector3();
                        if(!t3.tempTarget) t3.tempTarget = new THREE.Vector3();

                        t3.tempRight.set(1, 0, 0).applyQuaternion(t3.camera.quaternion);
                        t3.tempUp.set(0, 1, 0).applyQuaternion(t3.camera.quaternion);

                        t3.tempTarget.set(0, 0, 0);
                        t3.tempTarget.add(t3.tempRight.multiplyScalar(offsetX));
                        t3.tempTarget.add(t3.tempUp.multiplyScalar(offsetY));

                        t3.cssObject.position.lerp(t3.tempTarget, 0.15);
                    } else {
                        t3.cssObject.position.set(-9999, 0, 0);
                    }
                }

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

                    if (t3.cssObject && t3.cssObject.element) {
                        t3.cssObject.element.style.display = 'none';
                        if (t3.cssObject.parent && typeof t3.cssObject.parent.remove === 'function') {
                            try { t3.cssObject.parent.remove(t3.cssObject); } catch(e) {}
                        } else {
                            try { t3.scene.remove(t3.cssObject); } catch(e) {}
                        }
                    }
                    t3.scene.clear();
                    t3.scene = null;
                }

                if (t3.renderer) {
                    t3.renderer.dispose();
                    t3.renderer = null;
                }
                
                if (t3.cssRenderer) {
                    t3.cssRenderer = null;
                }
                
                t3.camera = null;

                const container = document.getElementById('funki-canvas-container');
                if (container) container.innerHTML = '';
            }
        }));
    });
</script>
