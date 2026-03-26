                t3.startTime = performance.now();
                t3.lastActivityTime = performance.now();

                window.addEventListener('resize', this.onWindowResize.bind(this));

                t3.raycaster = new THREE.Raycaster();
                t3.mouse = new THREE.Vector2(-9999, -9999);
                t3.mouseMoveListener = (event) => {
                    t3.mouse.x = (event.clientX / window.innerWidth) * 2 - 1;
                    t3.mouse.y = -(event.clientY / window.innerHeight) * 2 + 1;
                    t3.lastActivityTime = performance.now();
                };
                window.addEventListener('mousemove', t3.mouseMoveListener);

                t3.clickListener = (event) => {
                    t3.lastActivityTime = performance.now();
                    
                    t3.mouse.x = (event.clientX / window.innerWidth) * 2 - 1;
                    t3.mouse.y = -(event.clientY / window.innerHeight) * 2 + 1;

                    if (t3.raycaster && t3.camera && t3.hitboxMesh && !t3.isShuttingDown) {
                        t3.raycaster.setFromCamera(t3.mouse, t3.camera);
                        const intersects = t3.raycaster.intersectObject(t3.hitboxMesh);
                        if (intersects.length > 0) {
                            if (typeof t3.clickLevel === 'undefined') t3.clickLevel = 0;
                            t3.clickLevel++;
                            
                            if (t3.clickLevel === 1) {
                                t3.targetCameraDist = 800;
                                t3.zoomSpeed = 0.04;
                            } else if (t3.clickLevel === 2) {
                                t3.targetCameraDist = 1100;
                                t3.zoomSpeed = 0.04;
                            } else if (t3.clickLevel === 3) {
                                t3.targetCameraDist = 1500;
                                t3.zoomSpeed = 0.04;
                            } else {
                                t3.clickLevel = 0;
                                t3.targetCameraDist = 500;
                                t3.zoomSpeed = 0.006; 
                                
                                let zoomAudio = new Audio('/funkira/sounds/funkira_zoom_in.mp3');
                                zoomAudio.volume = 0.7;
                                zoomAudio.play().catch(e => console.log('Zoom in sound blocked or missing:', e));
                            }

                            if (t3.clickLevel > 0) {
                                const clickAudio = document.getElementById('audio-funki-click');
                                if (clickAudio) {
                                    clickAudio.currentTime = 0;
                                    clickAudio.volume = 0.6;
                                    clickAudio.play().catch(e => console.log(e));
                                }
                            }
                        }
                    }
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

                let targetColorHex = 0x00ff88; 
                if (this.thinking) {
                    targetColorHex = 0xff66b2; 
                } else if (this.systemState === 'good' || this.systemState === true) {
                    targetColorHex = 0x00ff88; 
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

                if (t3.raycaster && t3.camera && t3.hitboxMesh && t3.raymarchUniforms && !t3.isShuttingDown) {
                    t3.raycaster.setFromCamera(t3.mouse, t3.camera);
                    const intersects = t3.raycaster.intersectObject(t3.hitboxMesh);

                    if (intersects.length > 0) {
                        document.body.style.cursor = 'pointer';
                        t3.raymarchUniforms.hoverState.value += (1.0 - t3.raymarchUniforms.hoverState.value) * 0.05; 
                        t3.raymarchUniforms.hoverTime.value += delta;
                        if(t3.controls) t3.controls.autoRotateSpeed = 0.15; 
                    } else {
                        document.body.style.cursor = 'default';
                        t3.raymarchUniforms.hoverState.value += (0.0 - t3.raymarchUniforms.hoverState.value) * 0.05; 
                        t3.raymarchUniforms.hoverTime.value = Math.max(0.0, t3.raymarchUniforms.hoverTime.value - delta * 2.0); 
                        if(t3.controls) t3.controls.autoRotateSpeed = 0.05; 
                    }
                } else if (t3.isShuttingDown && t3.raymarchUniforms) {
                     t3.raymarchUniforms.hoverState.value += (0.0 - t3.raymarchUniforms.hoverState.value) * 0.1;
                     t3.raymarchUniforms.hoverTime.value = Math.max(0.0, t3.raymarchUniforms.hoverTime.value - delta * 3.0);
                }

                if (t3.heartbeatAudio && t3.raymarchUniforms && t3.raymarchUniforms.initProgress.value >= 1.0 && !t3.isShuttingDown) {
                    const hover = t3.raymarchUniforms.hoverState.value; 
                    const targetVolume = hover * 0.6;
                    const targetRate = 1.0 + hover * 0.8;

                    t3.heartbeatAudio.volume += (targetVolume - t3.heartbeatAudio.volume) * 0.05;
                    t3.heartbeatAudio.playbackRate += (targetRate - t3.heartbeatAudio.playbackRate) * 0.05;
                }

                if (t3.coreMesh) {
                    const pulse = 0.75 + Math.sin(time * 2.0) * 0.015;
                    t3.coreMesh.scale.set(pulse, pulse, pulse);
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

                        let rightVector = new THREE.Vector3(1, 0, 0).applyQuaternion(t3.camera.quaternion);
                        let upVector = new THREE.Vector3(0, 1, 0).applyQuaternion(t3.camera.quaternion);

                        let targetPos = new THREE.Vector3(0, 0, 0);
                        targetPos.add(rightVector.multiplyScalar(offsetX));
                        targetPos.add(upVector.multiplyScalar(offsetY));

                        t3.cssObject.position.lerp(targetPos, 0.15);
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
                }

                const container = document.getElementById('funki-canvas-container');
                if (container) container.innerHTML = '';
            }
        }));
    });
</script>
