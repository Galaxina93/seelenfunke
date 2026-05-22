<script>
    window.addEventListener('ai-trigger-ui-element', (e) => {
        const text = e.detail?.text?.toLowerCase();
        if (!text) return;
        const elements = Array.from(document.querySelectorAll('button, a, summary, .cursor-pointer, [role="button"], [wire\\:click], [x-on\\:click]'));
        const found = elements.find(el => el.textContent.toLowerCase().includes(text) && el.offsetParent !== null);
        if (found) {
            found.click();
        } else {
            console.warn('AI Trigger: UI element not found for text:', text);
        }
    });

    window.addEventListener('download-file', (e) => {
        const { url, filename } = e.detail;
        if (url) {
            const a = document.createElement('a');
            a.href = url;
            a.download = filename || 'download.pdf';
            a.target = '_blank'; // Fallback for some browsers to open in new tab
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        }
    });

    window.registerFunkiView = window.registerFunkiView || (() => {
        let t3 = {
            scene: null,
            camera: null,
            renderer: null,
            coreMesh: null,
            hitboxMesh: null,
            coreMaterial: null,
            raymarchUniforms: null,
            coreLight: null,
            // CSS3D removed
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

        Alpine.data('funkiView', (initialAgentColor = 'emerald-500', initialAgentId = null, initialState = 'good', initialSparks = 42, avgProfit = 0, totalOrders = 0, lastSync = '', initialVolume = 15, initialAgentName = 'System', initialAllowInterruption = true) => ({
            activeAgentName: initialAgentName,
            // State
            agentColor: initialAgentColor,
            activeAgentId: initialAgentId,
            showFunkiView: false,
            showErrorPanel: false,
            showDebugLog: false,
            showWorkspaceModal: false,
            showTasks: false,
            showNewsPanel: false,
            allowVoiceInterruption: initialAllowInterruption,
            newsWidgets: [],
            youtubeWidgets: [],
            personaWidgets: [],
            mainScreenWidget: null,
            isMapFocus: false,
            isMapMode: false,
            isBrainFocus: false,
            isBrainMode: false,
            isFlightDataActive: false,
            isSecretMode: false,
            currentChatSessionId: null,
            isJarvis: false,
            jarvisMinimized: false,
            showJarvisFlash: false,
            intelWidgets: [],
            shelfWidgets: [],
            cameraWidget: null,
            orgChartWidget: null,
            activeMapStyle: 'mapbox://styles/mapbox/dark-v11',
            mapStyles: [
                { id: 'mapbox://styles/mapbox/dark-v11', name: 'Dark Cyber' },
                { id: 'mapbox://styles/mapbox/satellite-streets-v12', name: 'Satellit' },
                { id: 'mapbox://styles/mapbox/light-v11', name: 'Light Base' },
                { id: 'mapbox://styles/mapbox/standard', name: '3D Standard' },
                { id: 'mapbox://styles/mapbox/outdoors-v12', name: 'Outdoors' }
            ],

            isAudioMuted: localStorage.getItem('funki_isAudioMuted') !== null ? localStorage.getItem('funki_isAudioMuted') === 'true' : true, // Default to muted as requested
            isMicMuted: false,
            bgVolume: initialVolume,
            systemState: initialState, // 'good', 'warning', 'error', true, false
            activeSparks: initialSparks,
            avgProfit: avgProfit + ' €',
            totalOrders: totalOrders,
            lastSync: lastSync,
            errorText: '',
            chatHistory: @js($this->messages),
            idleProgress: 0, // 0-100 indicating time until spontaneous action
            isMobile: /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent),
            // Voice AI State
            listening: false,
            thinking: false,
            isLiveMode: false,
            agentWakeWord: '{{ addslashes($agentWakeWord ?? "funkira") }}'.toLowerCase(),
            allowSpontaneous: true, // Default: On (Spontaneous Self-Analysis)
            recognition: null,
            synthesis: window.speechSynthesis,
            tokenUsage: '',
            funkiLogs: [],
            watchdogTimer: null,
            lastActivityTime: Date.now(),
            restartCount: 0,
            lastRestartTime: 0,
            isSpeaking: false,
            activeAgentName: initialAgentName, // Neu: Speichert den Namen des antwortenden Agenten
            agentTtsEnabled: false, // Prevents calling TTS apis

            chatAbortController: null,
            voiceAbortController: null,
            clipboardNeedsPermission: false,

            // Live API State
            isLiveMode: false,
            isSetupComplete: false,
            continuousMode: false,
            liveWs: null,
            audioContext: null,
            localAudioStream: null,

            async readClipboard(isDirectClick = false) {
                try {
                    this.clipboardNeedsPermission = false;
                    const clipboardItems = await navigator.clipboard.read();
                    for (const item of clipboardItems) {
                        if (item.types.some(type => type.startsWith('image/'))) {
                            const imageType = item.types.find(type => type.startsWith('image/'));
                            const blob = await item.getType(imageType);
                            const reader = new FileReader();
                            reader.onloadend = () => {
                                if (this.isLiveMode && this.liveWs && this.liveWs.readyState === WebSocket.OPEN) {
                                    const base64 = reader.result.split(',')[1];
                                    const clipMsg = {
                                        clientContent: {
                                            turns: [{
                                                role: "user",
                                                parts: [
                                                    { inlineData: { mimeType: imageType, data: base64 } },
                                                    { text: "*(System: Das Bild aus dem Zwischenspeicher wurde erfolgreich übermittelt. Bitte verarbeite es jetzt wie vom Nutzer gefordert.)*" }
                                                ]
                                            }],
                                            turnComplete: true
                                        }
                                    };
                                    this.liveWs.send(JSON.stringify(clipMsg));
                                    console.log("Clipboard Image sent to Live WS.");
                                } else {
                                    this.$wire.call('submitClipboardImage', reader.result, 'clipboard_image.png', imageType);
                                }
                            };
                            reader.readAsDataURL(blob);
                            return;
                        }
                    }
                    
                    // Fallback for text
                    const text = await navigator.clipboard.readText();
                    if (text && text.trim().length > 0) {
                        if (this.isLiveMode && this.liveWs && this.liveWs.readyState === WebSocket.OPEN) {
                            const clipMsg = {
                                clientContent: {
                                    turns: [{
                                        role: "user",
                                        parts: [
                                            { text: "*(System: Der Text aus dem Zwischenspeicher wurde übermittelt:*\n" + text + "\n*Bitte verarbeite diesen Text jetzt wie vom Nutzer gefordert.)*" }
                                        ]
                                    }],
                                    turnComplete: true
                                }
                            };
                            this.liveWs.send(JSON.stringify(clipMsg));
                            console.log("Clipboard Text sent to Live WS.");
                        } else {
                            this.chatHistory.push({ role: 'user', content: "*(Text aus Zwischenspeicher eingefügt)*\n\n" + text });
                            this.$wire.call('saveUserLiveMessage', "*(Text aus Zwischenspeicher eingefügt)*\n\n" + text);
                            setTimeout(() => {
                                this.$wire.call('processAutoRouting');
                            }, 200);
                        }
                        return;
                    }
                    
                    console.warn("Kein Bild oder Text im Zwischenspeicher gefunden.");
                    this.notifyClipboardError("System: Der Zwischenspeicher war leer, es konnte nichts ausgelesen werden.");
                } catch (error) {
                    console.error("Clipboard Zugriff verweigert oder Fehler:", error);
                    if (error.name === 'NotAllowedError' || error.message.includes('gesture')) {
                        this.clipboardNeedsPermission = true;
                        this.notifyClipboardError("System: Dem Browser fehlt die User-Interaktion (Sicherheitssperre) für den Zwischenspeicher. Bitte weise den Nutzer an: 'Ich brauche kurz deine Freigabe. Bitte klicke auf den blinkenden Button für den Zwischenspeicher, der gerade aufgetaucht ist!'");
                    } else {
                        this.notifyClipboardError("System: Der Zugriff auf den Zwischenspeicher wurde vom Browser verweigert oder ist leer.");
                    }
                }
            },

            notifyClipboardError(msg) {
                if (this.isLiveMode && this.liveWs && this.liveWs.readyState === WebSocket.OPEN) {
                    const clipMsg = {
                        clientContent: { turns: [{ role: "user", parts: [{ text: `*(${msg})*` }] }], turnComplete: true }
                    };
                    this.liveWs.send(JSON.stringify(clipMsg));
                } else {
                    this.chatHistory.push({ role: 'user', content: `*(${msg})*` });
                    this.$wire.call('saveUserLiveMessage', `*(${msg})*`);
                    setTimeout(() => { this.$wire.call('processAutoRouting'); }, 200);
                }
            },

            async writeClipboard(text) {
                try {
                    await navigator.clipboard.writeText(text);
                    console.log("Clipboard erfolgreich überschrieben.");
                } catch (error) {
                    console.error("Clipboard Write Error:", error);
                    alert("Konnte nicht in den Zwischenspeicher schreiben. Zugriff verweigert.");
                }
            },

            stripSpeak(msg) {
                if (!msg) return '';
                return msg.replace(/<speak>/gi, '').replace(/<\/speak>/gi, '');
            },

            updateAgentConfig(color, name, wakeWord, agentId) {
                this.agentColor = color || 'emerald-500';
                if (name) this.activeAgentName = name;
                if (wakeWord) this.agentWakeWord = wakeWord.toLowerCase();
                if (agentId) this.activeAgentId = agentId;
                this.updateCoreColor(true);

                if (this.isLiveMode) {
                    this.toggleLiveMode(); // Gracefully turn off Live Mode (UI & WS)
                    setTimeout(() => {
                        this.toggleLiveMode(); // Gracefully turn it back on after cleanup
                    }, 1200); // 1.2s gives the old AudioContext and WebSocket enough time to fully close
                }
            },

            jarvisTime: 0,
            jarvisAnimId: null,
            initJarvisCanvas() {
                const canvas = document.getElementById('jarvisCanvas');
                if (!canvas) return;
                const ctx = canvas.getContext('2d');

                const resize = () => {
                    const container = document.getElementById('funki-canvas-container');
                    if (container) {
                        canvas.width = container.clientWidth || window.innerWidth;
                        canvas.height = container.clientHeight || window.innerHeight;
                    } else {
                        canvas.width = window.innerWidth;
                        canvas.height = window.innerHeight;
                    }
                };
                window.addEventListener('resize', resize);
                resize();

                class Ring {
                    constructor(radius, speed, width, dashArray, color = '#00d4ff', pulseSpeed = 0.02, pulseAmp = 5) {
                        this.radius = radius;
                        this.speed = speed;
                        this.originalSpeed = speed;
                        this.width = width;
                        this.originalWidth = width;
                        this.dashArray = dashArray;
                        this.color = color;
                        this.originalColor = color;
                        this.pulseSpeed = pulseSpeed;
                        this.pulseAmp = pulseAmp;
                    }

                    draw(ctx, time) {
                        ctx.save();
                        ctx.beginPath();
                        ctx.strokeStyle = this.color;
                        ctx.lineWidth = this.width;
                        ctx.setLineDash(this.dashArray);
                        
                        // Rotation
                        ctx.lineDashOffset = time * this.speed;
                        
                        // Pulsieren
                        const pulse = Math.sin(time * this.pulseSpeed) * this.pulseAmp;
                        
                        ctx.arc(canvas.width / 2, canvas.height / 2, this.radius + pulse, 0, Math.PI * 2);
                        ctx.stroke();
                        ctx.restore();
                    }
                }

                const rings = [
                    new Ring(60, 2, 1, [2, 10]),
                    new Ring(70, -3, 2, [5, 20]),
                    new Ring(120, 1.2, 4, [100, 50]),
                    new Ring(125, -0.8, 1, [200, 10]),
                    new Ring(180, 0.5, 2, [300, 40], 'rgba(0, 212, 255, 0.5)'),
                    new Ring(220, -1.5, 0.5, [10, 10], 'rgba(0, 212, 255, 0.3)'),
                    new Ring(250, 0.2, 1, [1, 150], '#ffffff')
                ];

                const particles = [];
                for(let i = 0; i < 40; i++) {
                    particles.push({
                        angle: Math.random() * Math.PI * 2,
                        radius: 50 + Math.random() * 200,
                        speed: 0.005 + Math.random() * 0.01,
                        size: Math.random() * 2
                    });
                }

                const drawParticles = (speaking) => {
                    ctx.fillStyle = speaking ? '#ffffff' : '#00d4ff';
                    particles.forEach(p => {
                        p.angle += p.speed * (speaking ? 3 : 1);
                        const x = canvas.width / 2 + Math.cos(p.angle) * p.radius;
                        const y = canvas.height / 2 + Math.sin(p.angle) * p.radius;
                        ctx.beginPath();
                        ctx.arc(x, y, p.size * (speaking ? 1.5 : 1), 0, Math.PI * 2);
                        ctx.fill();
                    });
                };

                const animate = () => {
                    if (!this.isJarvis) {
                        this.jarvisAnimId = null;
                        return; // Stop animation loop
                    }
                    
                    ctx.clearRect(0, 0, canvas.width, canvas.height); 

                    const speaking = this.isSpeaking || (typeof this.isOutputActive === 'function' ? this.isOutputActive() : false);
                    const pulse = speaking ? Math.abs(Math.sin(this.jarvisTime * 0.15)) : 0;

                    ctx.shadowBlur = speaking ? 15 + pulse * 15 : 5;
                    ctx.shadowColor = '#00d4ff';

                    rings.forEach(ring => {
                        if (speaking) {
                            ring.width = ring.originalWidth + pulse * 2;
                            ring.color = '#00ffff'; 
                            ring.speed = ring.originalSpeed * 1.5;
                        } else {
                            ring.width = ring.originalWidth;
                            ring.color = ring.originalColor;
                            ring.speed = ring.originalSpeed;
                        }
                        ring.draw(ctx, this.jarvisTime);
                    });

                    drawParticles(speaking);

                    // Draw J.A.R.V.I.S. Text
                    ctx.shadowBlur = speaking ? 20 + pulse * 20 : 10;
                    ctx.shadowColor = '#00d4ff';
                    ctx.font = 'bold 18px "Courier New", Courier, monospace';
                    if ('letterSpacing' in ctx) {
                        ctx.letterSpacing = '8px';
                    }
                    ctx.fillStyle = speaking ? `rgba(255, 255, 255, ${0.8 + pulse * 0.2})` : 'rgba(0, 212, 255, 0.7)';
                    ctx.textAlign = 'center';
                    ctx.textBaseline = 'middle';
                    // Shift text slightly to perfectly center the letters when letterSpacing is applied
                    ctx.fillText('J.A.R.V.I.S.', canvas.width / 2 + 4, canvas.height / 2);

                    ctx.shadowBlur = 0;
                    if ('letterSpacing' in ctx) {
                        ctx.letterSpacing = '0px';
                    }

                    this.jarvisTime++;
                    this.jarvisAnimId = requestAnimationFrame(animate);
                };

                if (!this.jarvisAnimId) {
                    animate();
                }
            },

            updateJarvisMode() {
                if (!t3 || !t3.scene || !t3.coreMesh) return;

                if (this.isJarvis) {
                    // Forward Transformation (3D Shader Dissolve)
                    const initAudio = document.getElementById('audio-funki-init');
                    if (initAudio && initAudio.paused) {
                        initAudio.currentTime = 0;
                        initAudio.volume = 0.8;
                        initAudio.play().catch(e=>e);
                    }

                    // Start the 3D Core Dissolve effect
                    t3.isTransformingToJarvis = true;
                    t3.transformStartTime = performance.now();

                    // Wait for dissolve to finish (faster than normal shutdown, e.g. 1200ms)
                    setTimeout(() => {
                        t3.coreMesh.visible = false;
                        t3.isTransformingToJarvis = false; // Reset flag
                        
                        this.$nextTick(() => {
                            this.initJarvisCanvas();
                            
                            const canvas = document.getElementById('jarvisCanvas');
                            if (canvas) {
                                // "Assembling" effect: start scaled down, transparent and blurred
                                canvas.style.transform = 'scale(0.5)';
                                canvas.style.opacity = '0';
                                canvas.style.filter = 'blur(15px)';
                                
                                setTimeout(() => {
                                    canvas.style.transition = 'all 1s cubic-bezier(0.1, 0.9, 0.2, 1)';
                                    canvas.style.transform = ''; // Tailwind takes over
                                    canvas.style.opacity = '1';
                                    canvas.style.filter = 'blur(0px) drop-shadow(0 0 15px rgba(0, 212, 255, 0.6))';
                                }, 50);
                            }
                        });
                    }, 1200);

                } else {
                    // Reverse Transformation (Assemble 3D Core)
                    const shutdownAudio = document.getElementById('audio-funki-shutdown');
                    if (shutdownAudio && shutdownAudio.paused) {
                        shutdownAudio.currentTime = 0;
                        shutdownAudio.volume = 0.8;
                        shutdownAudio.play().catch(e=>e);
                    }

                    const canvas = document.getElementById('jarvisCanvas');
                    if (canvas) {
                        // Disassemble Jarvis
                        canvas.style.opacity = '0';
                        canvas.style.transform = 'scale(1.5)';
                        canvas.style.filter = 'blur(15px)';
                        canvas.style.transition = 'all 0.8s ease-in';
                    }
                    
                    setTimeout(() => {
                        // Make 3D Core visible but completely dissolved (progress = 1.0)
                        if (t3 && t3.coreMesh) {
                            t3.coreMesh.visible = true;
                            t3.coreMesh.scale.set(1, 1, 1);
                        }
                        this.updateCoreColor(true); 
                        
                        // Start reverse dissolve (materialize)
                        t3.isTransformingFromJarvis = true;
                        t3.transformStartTime = performance.now();
                        
                        setTimeout(() => {
                            t3.isTransformingFromJarvis = false;
                        }, 1200);
                        
                    }, 600); // Wait for canvas to fade out
                }
            },

            handleAgentSwitch(agentId) {
                setTimeout(() => {
                    if (!this.isSpeaking && !this.thinking) {
                        this.$wire.set('agentId', agentId);
                        return;
                    }

                    const checkInterval = setInterval(() => {
                        if (!this.isSpeaking && !this.thinking) {
                            clearInterval(checkInterval);
                            this.$wire.set('agentId', agentId);
                        }
                    }, 500);

                    setTimeout(() => {
                        clearInterval(checkInterval);
                        this.$wire.set('agentId', agentId);
                    }, 15000);
                }, 1000);
            },

            getColorHex(colorStr) {
                const map = {
                    'red': 0xef4444,
                    'orange': 0xf97316,
                    'amber': 0xf59e0b,
                    'yellow': 0xeab308,
                    'lime': 0x84cc16,
                    'green': 0x22c55e,
                    'emerald': 0x10b981,
                    'teal': 0x14b8a6,
                    'cyan': 0x06b6d4,
                    'sky': 0x0ea5e9,
                    'blue': 0x3b82f6,
                    'indigo': 0x6366f1,
                    'violet': 0x8b5cf6,
                    'purple': 0xa855f7,
                    'fuchsia': 0xd946ef,
                    'pink': 0xec4899,
                    'rose': 0xf43f5e
                };
                for (const key in map) {
                    if (colorStr.includes(key)) return map[key];
                }
                return 0x10b981; // fallback emerald
            },

            getHexColorStr(colorStr) {
                if (!colorStr) return '#10b981';
                if (colorStr.startsWith('#')) return colorStr;
                let hexNum = this.getColorHex(colorStr);
                return '#' + hexNum.toString(16).padStart(6, '0');
            },

            isOutputActive() {
                return this.isSpeaking || this.thinking;
            },

            setMainScreenWidget(type, index) {
                this.mainScreenWidget = { type: type, index: parseInt(index) };
            },

            clearMainScreenWidget() {
                this.mainScreenWidget = null;
            },

            // --- AI VOICE CHAT LOGIC ---
            toggleLiveMode() {
                this.isLiveMode = !this.isLiveMode;
                this.enforceAudioMuteState(); // Re-evaluate audio muting for mobile
                if (this.isLiveMode) {
                    if (this.listening) {
                        this.listening = false;
                        if (this.recognition) {
                            this.recognition.onstart = null;
                            this.recognition.onend = null;
                            try { this.recognition.abort(); } catch(e) {}
                            try { this.recognition.stop(); } catch(e) {}
                        }
                        this.stopSpeech();
                    }
                    this.systemState = 'good';
                    this.thinking = true; // Show loading state
                    this.updateCoreColor(true);

                    if (typeof this.initLiveMode === 'function') {
                        this.initLiveMode();
                    } else {
                        console.error("Live Mode Logik (part7) fehlt!");
                        this.isLiveMode = false;
                        this.thinking = false;
                        this.updateCoreColor(true);
                    }
                } else {
                    if (typeof this.stopLiveMode === 'function') {
                        this.stopLiveMode();
                    }
                }
            },

            toggleSpeech() {
                if (this.thinking || this.isLiveMode) return;

                if (this.isMobile) {
                    return;
                }

                if (this.listening) {
                    this.listening = false;
                    if (this.recognition) {
                        this.recognition.onend = null;
                        try { this.recognition.abort(); } catch(e) {}
                        this.recognition = null;
                    }
                } else {
                    if (window.funkiAudioPlayer) window.funkiAudioPlayer.pause();
                    if (this.synthesis && this.synthesis.speaking) this.synthesis.cancel();

                    this.listening = true;
                    this.restartCount = 0;
                    if (!this.recognition) this.initSpeech();
                    this.startSafeRecognition(200);
                }
            },

            fullStop() {
                if (this.isLiveMode) {
                    this.toggleLiveMode();
                    return;
                }
                this.listening = false;
                if (this.recognition) {
                    this.recognition.onstart = null;
                    this.recognition.onend = null;
                    try { this.recognition.abort(); } catch(e) {}
                    try { this.recognition.stop(); } catch(e) {}
                }
                this.stopSpeech();
                this.updateCoreColor();
            },

            startPushToTalk() {
                if (this.thinking) return;

                if (this.isOutputActive()) {
                    this.stopSpeech();
                }

                if (window.funkiAudioPlayer) window.funkiAudioPlayer.pause();
                if (this.synthesis) this.synthesis.cancel();

                this.playClickSound();
                this.listening = true;
                this.updateCoreColor();
                this.restartCount = 0;

                this.initSpeech();
                this.startSafeRecognition(100);
            },

            stopPushToTalk() {
                if (!this.listening || !this.recognition) return;
                this.playUnclickSound();
                this.listening = false;
                this.updateCoreColor();
                this.recognition.stop();
            },

            async sendToAI(promptText, isSpontaneous = false) {
                if (this.isLiveMode && this.liveWs && this.liveWs.readyState === WebSocket.OPEN) {
                    const msg = {
                        clientContent: {
                            turns: [{
                                role: 'user',
                                parts: [{ text: promptText }]
                            }],
                            turnComplete: true
                        }
                    };
                    this.liveWs.send(JSON.stringify(msg));
                    
                    if (!isSpontaneous) {
                        this.chatHistory.push({ role: 'user', content: promptText });
                        this.funkiLogs.push({ role: 'user', time: new Date().toLocaleTimeString('de-DE'), message: promptText });
                        if (this.$wire) {
                            this.$wire.saveUserLiveMessage(promptText);
                        }
                    } else {
                        this.funkiLogs.push({ role: 'tool', time: new Date().toLocaleTimeString('de-DE'), message: 'Spontane Analyse in Live API gesendet.' });
                    }
                    return;
                }

                this.thinking = true;
                this.updateCoreColor();

                if (this.chatAbortController) this.chatAbortController.abort();
                this.chatAbortController = new AbortController();

                const pulseAudio = document.getElementById('audio-funki-pulse');
                if (pulseAudio) {
                    pulseAudio.volume = 0.5;
                    pulseAudio.play().catch(e => console.log('Pulse blocked', e));
                }

                try {
                    if (!isSpontaneous) {
                        this.chatHistory.push({ role: 'user', content: promptText });
                        this.funkiLogs.push({ role: 'user', time: new Date().toLocaleTimeString('de-DE'), message: promptText });
                    } else {
                        this.chatHistory.push({ role: 'system', content: 'SYSTEM-BEFEHL (Verdeckt): ' + promptText });
                        this.funkiLogs.push({ role: 'tool', time: new Date().toLocaleTimeString('de-DE'), message: 'Spontane Analyse ausgelöst.' });
                    }

                    const response = await fetch('/api/ai/chat', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify({
                            history: this.chatHistory,
                            agent_id: this.activeAgentId || {!! $widgetAgent ? "'" . $widgetAgent->id . "'" : 'null' !!},
                            chat_session_id: this.$wire.currentChatSessionId
                        }),
                        signal: this.chatAbortController.signal
                    });

                    let data;
                    try {
                        const clonedResponse = response.clone();
                        data = await response.json();
                    } catch (jsonErr) {
                        this.thinking = false;
                        this.updateCoreColor();

                        let errorTextHTML = "Unbekannter Fehler beim Lesen der Antwort.";
                        try {
                            errorTextHTML = await clonedResponse.text();
                        } catch(e) { /* Ignore */ }

                        console.error("SyntaxError Fallback:", errorTextHTML);

                        this.errorText = "⚠️ Subraum Kommunikation abgebrochen:\nDer Server hat eine HTML-Fehlerseite (Status " + response.status + ") statt JSON zurückgegeben.\n\nDies bedeutet meist, dass der API Code abgestürzt ist.\n\nAuszug:\n" + errorTextHTML.substring(0, 300) + "...\n\nBitte sende diesen Fehler an Gemini!";
                        this.showErrorPanel = true;
                        this.systemState = 'error';
                        this.updateCoreColor(true);
                        return;
                    }

                    if(data.status === 'success') {
                        // Do not overwrite this.chatHistory with data.history to preserve 'name' and 'color' attributes in the UI.
                        // The UI will only append new messages manually.

                        if (data.context_data && data.context_data.length > 0) {
                            data.context_data.forEach(ctx => {
                                this.funkiLogs.push({ role: 'tool', time: new Date().toLocaleTimeString('de-DE'), message: `Werkzeug: ${ctx.function}` });
                            });
                        }

                        if (data.events_data && data.events_data.length > 0) {
                            data.events_data.forEach(evt => {
                                if (evt.name === 'open-ai-visualization') {
                                    this.playClickSound();
                                    Livewire.dispatch('open-ai-visualization', { payload: evt.detail });
                                } else if (evt.type === 'navigate') {
                                    if (this.showFunkiView) {
                                        localStorage.setItem('funki_isOpen', 'false');
                                    }
                                    setTimeout(() => {
                                        window.location.href = evt.url;
                                    }, 400);
                                } else if (evt.type === 'dispatch' || !evt.type) {
                                    if (evt.detail !== undefined) {
                                        window.dispatchEvent(new CustomEvent(evt.name, { detail: evt.detail }));
                                    } else {
                                        window.dispatchEvent(new Event(evt.name));
                                    }
                                }
                            });
                        }

                        if (data.agent_name) {
                            this.activeAgentName = data.agent_name;
                        }

                        if (data.tts_enabled !== undefined) {
                            this.agentTtsEnabled = data.tts_enabled;
                        }

                        if (data.response) {
                            this.funkiLogs.push({ role: 'ai', time: new Date().toLocaleTimeString('de-DE'), message: data.response.replace(/\[.*?\]/s, '') });
                            this.chatHistory.push({ role: 'assistant', content: data.response, name: data.agent_name || this.activeAgentName });
                        }

                        if (this.funkiLogs.length > 15) this.funkiLogs = this.funkiLogs.slice(-15);

                        if (data.usage && data.usage.total_tokens) {
                            this.tokenUsage = `TOKENS: ${data.usage.total_tokens}`;
                        }

                        if (data.audio) {
                            this.playAudioBase64(data.audio);
                        } else if (data.response && data.response.trim() !== '') {
                            if (this.agentTtsEnabled) {
                                this.speakResponse(data.response);
                            }
                        }
                    } else {
                        console.error("AI Error:", data);
                        this.speakResponse("Verbindung zum Schiffscomputer fehlgeschlagen.");
                        this.funkiLogs.push({ role: 'tool', time: new Date().toLocaleTimeString('de-DE'), message: `Fehler: API lieferte keinen Erfolgsstatus.` });
                        Livewire.dispatch('log-widget-error', { message: 'Widget API lieferte keinen Erfolgsstatus: ' + (data.error || 'Unbekannt') });
                        if (this.funkiLogs.length > 15) this.funkiLogs = this.funkiLogs.slice(-15);
                    }
                } catch (err) {
                    if (err.name === 'AbortError') {
                        console.log('AI Fetch aborted by user.');
                        return;
                    }
                    console.error("Fetch Error:", err);
                    this.speakResponse("Subraumkommunikation abgebrochen.");
                    this.funkiLogs.push({ role: 'tool', time: new Date().toLocaleTimeString('de-DE'), message: `Fehler: ${err.message}` });
                    Livewire.dispatch('log-widget-error', { message: 'Widget Fetch-Fehler: ' + err.message });
                    if (this.funkiLogs.length > 15) this.funkiLogs = this.funkiLogs.slice(-15);
                } finally {
                    this.thinking = false;
                    this.updateCoreColor();


                    const pulseAudio = document.getElementById('audio-funki-pulse');
                    if (pulseAudio) {
                        pulseAudio.pause();
                        pulseAudio.currentTime = 0;
                    }

                    setTimeout(() => {
                        if (this.continuousMode && !this.isOutputActive()) {
                            this.listening = true;
                            try { this.recognition.start(); } catch(e) {}
                        }
                    }, 100);
                }
            },

            renderAnalytics(contextData, aiResponseText = '') {
                if (!contextData || !Array.isArray(contextData)) return;

                const closeCommand = contextData.find(c => c.function === 'close_ui');
                if (closeCommand && closeCommand.data && closeCommand.data.status === 'success') {
                    this.showInfoPanel = false;
                    this.showChartPanel = false;
                    this.showErrorPanel = false;
                    this.tableData = [];
                    this.chartListData = [];
                    this.destroyCurrentChart();
                    this.showChartCanvas = false;
                    return;
                }

                const switchCommand = contextData.find(c => c.function === 'system_switch_agent');
                if (switchCommand && switchCommand.data && switchCommand.data.status === 'success') {
                    if (switchCommand.data.agent_id) {
                        this.$wire.set('agentId', switchCommand.data.agent_id);
                        return;
                    }
                }

                let chartType = 'doughnut';
                let chartLabels = [];
                let chartDataset = [];
                let title = 'System Metriken';
                let foundData = false;

                this.chartListData = [];
                this.tableData = [];
                this.tableHeaders = [];

                const aiText = aiResponseText.toLowerCase();
                const lastUserMsg = this.chatHistory.slice().reverse().find(msg => msg.role === 'user');
                const lastUserContent = lastUserMsg ? lastUserMsg.content.toLowerCase() : '';

                const userRequestedGraphic = (
                    lastUserContent.includes('grafik') ||
                    lastUserContent.includes('diagramm') ||
                    lastUserContent.includes('chart')
                );

                const statData = contextData.find(c => c.function === 'get_shop_stats');
                if (statData && statData.data && statData.data.scaling_metrics) {
                    title = 'Marketing & Skalierung';
                    foundData = true;

                    if (userRequestedGraphic) {
                        chartLabels = ['Active Auto-Vouchers', 'Active Manual-Vouchers', 'Abandoned Carts (24h)'];
                        chartDataset = [
                            statData.data.scaling_metrics.active_auto_vouchers || 0,
                            statData.data.scaling_metrics.active_manual_vouchers || 0,
                            statData.data.scaling_metrics.abandoned_carts_count || 0
                        ];
                        chartType = 'polarArea';
                    } else {
                        this.tableHeaders = ['Metrik', 'Wert'];
                        this.tableData.push({
                            cells: [
                                { value: 'Auto-Gutscheine (Aktiv)', color: 'text-gray-300' },
                                { value: statData.data.scaling_metrics.active_auto_vouchers + 'x', color: 'text-emerald-400 font-bold' }
                            ]
                        });
                        this.tableData.push({
                            cells: [
                                { value: 'Manuelle-Gutscheine (Aktiv)', color: 'text-gray-300' },
                                { value: statData.data.scaling_metrics.active_manual_vouchers + 'x', color: 'text-blue-400 font-bold' }
                            ]
                        });
                        this.tableData.push({
                            cells: [
                                { value: 'Abgebrochene Carts (24h)', color: 'text-gray-300' },
                                { value: statData.data.scaling_metrics.abandoned_carts_count + 'x', color: 'text-yellow-400 font-bold' }
                            ]
                        });

                        this.chartListData.push({
                            title: 'Verlorener Umsatz (24h)',
                            titleColor: 'text-rose-400',
                            badge: 'Warnung',
                            subtitle: `Potenzieller Verlust: ${statData.data.scaling_metrics.potential_lost_revenue || 0} €`
                        });
                    }
                }

                const financeData = contextData.find(c => c.function === 'get_finances');
                if (!foundData && financeData && financeData.data && financeData.data.financial_data_net) {
                    title = 'Finanzübersicht (' + (financeData.data.current_month || 'Laufender Monat') + ')';
                    foundData = true;

                    let fd = financeData.data.financial_data_net;

                    if (userRequestedGraphic || true) {
                        chartLabels = ['Shop Netto-Umsatz', 'Fixkosten', 'Sonderausgaben'];
                        chartDataset = [
                            fd.shop_income || 0,
                            Math.abs(fd.fixed_expenses || 0),
                            Math.abs(fd.special_expenses || 0)
                        ];
                        chartType = 'doughnut';

                        let sum = chartDataset.reduce((a,b) => a+b, 0);
                        if (sum === 0) {
                            chartLabels = ['Keine Daten in diesem Monat'];
                            chartDataset = [1];
                        }
                    }
                }

                const taskData = contextData.find(c => c.function === 'get_tasks');
                if (!foundData && taskData && taskData.data && taskData.data.tasks) {
                    title = 'Meine Aufgaben';
                    foundData = true;

                    if (taskData.data.tasks.length === 0) {
                        this.chartListData.push({
                            title: 'Alles erledigt!',
                            titleColor: 'text-emerald-400',
                            badge: 'Aufgaben',
                            subtitle: 'Du hast aktuell keine offenen Aufgaben.'
                        });
                    } else if (userRequestedGraphic) {
                        chartType = 'bar';
                        chartLabels = ['Hoch', 'Mittel', 'Niedrig'];
                        let high = 0, med = 0, low = 0;
                        taskData.data.tasks.forEach(t => {
                            if (t.priority === 'hoch') high++;
                            else if (t.priority === 'mittel') med++;
                            else low++;
                        });
                        chartDataset = [high, med, low];
                    } else {
                        this.tableHeaders = ['Titel', 'Priorität', 'Erstellt am'];
                        taskData.data.tasks.forEach(t => {
                            let pColor = 'text-gray-300';
                            let renderPriority = (t.priority || 'Normal').toString().toUpperCase();
                            let rawPrio = renderPriority.toLowerCase();

                            if (rawPrio === 'high' || rawPrio === 'hoch' || rawPrio === '1') {
                                pColor = 'text-red-400';
                            } else if (rawPrio === 'medium' || rawPrio === 'mittel' || rawPrio === '2') {
                                pColor = 'text-yellow-400';
                            } else {
                                pColor = 'text-gray-300';
                            }

                            let dateStr = t.created_at ? new Date(t.created_at).toLocaleDateString('de-DE') : 'Neu';

                            this.tableData.push({
                                cells: [
                                    { value: t.title, color: 'text-gray-100' },
                                    { value: renderPriority, color: pColor },
                                    { value: dateStr, color: 'text-gray-500' }
                                ]
                            });
                        });
                    }
                }

                const actionData = contextData.find(c => c.function === 'create_task' || c.function === 'complete_task' || c.function === 'delete_task');
                if (!foundData && actionData && actionData.data && actionData.data.status === 'success') {
                    title = 'System-Aktion';
                    foundData = true;
                    this.chartListData.push({
                        title: actionData.data.message || 'Aktion erfolgreich',
                        titleColor: 'text-emerald-400',
                        badge: 'OK',
                        subtitle: 'Von Funkira ausgeführt'
                    });
                }

                const errorData = contextData.find(c => c.data && c.data.status === 'error');
                if (!foundData && errorData) {
                    foundData = true;
                    this.errorText = `Modul: ${errorData.function}\nFehlerbericht:\n${errorData.data.message || 'Unbekannter Systemfehler'}`;
                    this.showErrorPanel = true;
                    this.systemState = 'error';
                    this.updateCoreColor();
                }

                const calData = contextData.find(c => c.function === 'get_calendar_events');
                if (!foundData && calData && calData.data && calData.data.upcoming_events) {
                    title = 'Meine Termine';
                    foundData = true;

                    if (calData.data.upcoming_events.length === 0) {
                        this.chartListData.push({
                            title: 'Freie Zeit',
                            titleColor: 'text-emerald-400',
                            badge: 'Kalender',
                            subtitle: 'Du hast in den nächsten 7 Tagen keine Termine.'
                        });
                    } else if (userRequestedGraphic) {
                        chartType = 'polarArea';
                        let categories = {};
                        calData.data.upcoming_events.forEach(e => {
                            let cat = e.category || 'Allgemein';
                            categories[cat] = (categories[cat] || 0) + 1;
                        });
                        chartLabels = Object.keys(categories);
                        chartDataset = Object.values(categories);
                    } else {
                        calData.data.upcoming_events.forEach(e => {
                            let dateStr = new Date(e.start_date).toLocaleString('de-DE', { weekday:'short', day:'2-digit', month:'2-digit', hour:'2-digit', minute:'2-digit' });
                            this.chartListData.push({
                                title: e.title,
                                titleColor: 'text-indigo-400',
                                badge: e.category || 'Termin',
                                subtitle: `Zeit: ${dateStr}`
                            });
                        });
                    }
                }

                const routineData = contextData.find(c => c.function === 'get_day_routines');
                if (!foundData && routineData && routineData.data && routineData.data.routines) {
                    title = 'Fokus-Routinen';
                    foundData = true;

                    if (routineData.data.routines.length === 0) {
                        this.chartListData.push({
                            title: 'Keine Routinen',
                            titleColor: 'text-emerald-400',
                            badge: 'Routinen',
                            subtitle: 'Du hast heute noch keine Fokus-Routinen geplant.'
                        });
                    } else {
                        chartType = 'doughnut';
                        routineData.data.routines.forEach(r => {
                            let rTitle = r.title || 'Unbenannt';
                            let duration = r.duration_minutes || 10;
                            chartLabels.push(rTitle);
                            chartDataset.push(duration);

                            let stepText = '';
                            if (r.steps && r.steps.length > 0) {
                                stepText = r.steps.map(s => `• ${s.title}`).join('\n');
                            }

                            this.chartListData.push({
                                title: rTitle,
                                titleColor: 'text-emerald-400',
                                badge: duration + ' Min',
                                subtitle: stepText
                            });
                        });
                    }
                }

                const lbData = contextData.find(c => c.function === 'get_gamification_leaderboard');
                if (!foundData && lbData && lbData.data && lbData.data.leaderboard) {
                    title = 'Seelenfunke Highscores';
                    foundData = true;

                    if (lbData.data.leaderboard.length === 0) {
                        this.chartListData.push({
                            title: 'Keine Rekorde',
                            titleColor: 'text-gray-400',
                            badge: 'Gamification',
                            subtitle: 'Noch hat sich niemand in der Rangliste qualifiziert.'
                        });
                    } else {
                        this.tableHeaders = ['Rang', 'Kunde', 'Titel', 'Level', 'XP'];
                        lbData.data.leaderboard.forEach(l => {
                            let rankColor = l.rank === 1 ? 'text-yellow-400 font-bold' : (l.rank === 2 ? 'text-gray-300 font-bold' : (l.rank === 3 ? 'text-amber-600 font-bold' : 'text-indigo-400'));
                            this.tableData.push({
                                cells: [
                                    { value: '#' + l.rank, color: rankColor },
                                    { value: l.customer, color: 'text-white' },
                                    { value: l.title, color: 'text-gray-400' },
                                    { value: 'LVL ' + l.level, color: 'text-emerald-400' },
                                    { value: l.xp, color: 'text-emerald-600' }
                                ]
                            });
                        });
                    }
                }

                if (!foundData) {
                    const healthData = contextData.find(c => c.function === 'get_system_health');
                    if (healthData && healthData.data && healthData.data.active_sessions !== undefined) {
                        title = 'Sitzungen vs Bestellungen';
                        chartType = 'bar';
                        chartLabels = ['Aktive Sitzungen', 'Bestellungen'];
                        chartDataset = [healthData.data.active_sessions || 0, healthData.data.total_orders || 0];
                    } else {
                        return;
                    }
                }

                this.chartTitle = title;
                this.showChartPanel = true;

                this.$nextTick(() => {
                    this.playClickSound();
                    this.destroyCurrentChart();

                    const chartWrapper = document.getElementById('funki-canvas-wrapper');

                    if (this.chartListData.length > 0 || this.tableData.length > 0) {
                        this.showChartCanvas = false;
                        return;
                    }

                    this.showChartCanvas = true;

                    setTimeout(() => {
                        const ctx = document.getElementById('funkiDynamicChart');
                        if (!ctx) return;

                        if (typeof Chart === 'undefined') {
                            console.error('Chart.js is not loaded yet.');
                            return;
                        }

                        Chart.defaults.color = "rgba(255, 255, 255, 0.6)";
                        Chart.defaults.font.family = "'JetBrains Mono', monospace";
                        Chart.defaults.plugins.tooltip.backgroundColor = "rgba(0, 0, 0, 0.8)";

                        this.currentChart = new Chart(ctx, {
                            type: chartType,
                            data: {
                                labels: chartLabels,
                                datasets: [{
                                    data: chartDataset,
                                    backgroundColor: [
                                        'rgba(16, 185, 129, 0.6)',
                                        'rgba(59, 130, 246, 0.6)',
                                        'rgba(139, 92, 246, 0.6)'
                                    ],
                                    borderColor: [
                                        'rgba(16, 185, 129, 1)',
                                        'rgba(59, 130, 246, 1)',
                                        'rgba(139, 92, 246, 1)'
                                    ],
                                    borderWidth: 1,
                                    hoverOffset: 4
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                cutout: chartType === 'doughnut' ? '75%' : undefined,
                                plugins: {
                                    legend: {
                                        position: 'bottom',
                                        labels: { padding: 20, color: 'rgba(255,255,255,0.7)' }
                                    }
                                },
                                animation: {
                                    animateScale: true,
                                    animateRotate: true
                                }
                            }
                        });
                    }, 300);
                });



            },

            destroyCurrentChart() {
                if (this.currentChart) {
                    this.currentChart.destroy();
                    this.currentChart = null;
                }
            },

            playClickSound() {
                const clickAudio = document.getElementById('audio-funki-click');
                if (clickAudio) {
                    clickAudio.currentTime = 0;
                    clickAudio.volume = 0.6;
                    clickAudio.play().catch(e => console.log(e));
                }
            },

            // --- Multimodal Live API ---
            nextPlayTime: 0,

            async initLiveMode() {
                try {
                    this.thinking = true;
                    this.updateCoreColor(true);
                    this.isSetupComplete = false;

                    let activeChatId = this.$wire.currentChatSessionId || '';

                    // 1. Fetch Credentials securely
                    const response = await fetch('/api/ai/live-credentials?agent_id=' + (this.activeAgentId || '') + '&chat_session_id=' + activeChatId + '&session_id={{ session()->getId() }}&t=' + Date.now(), {
                        credentials: 'same-origin',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                        }
                    });

                    if (!response.ok) throw new Error('Konnte keine Live-Credentials abrufen.');
                    const creds = await response.json();

                    if (!creds.token) throw new Error('Token fehlt.');

                    // 2. Setup WebSocket
                    let wsUrl = creds.ws_url;
                    if (window.location.protocol === 'https:' && wsUrl.startsWith('ws://')) {
                        wsUrl = wsUrl.replace('ws://', 'wss://');
                    }
                    wsUrl = `${wsUrl}${wsUrl.includes('?') ? '&' : '?'}token=${creds.token}`;
                    this.liveWs = new WebSocket(wsUrl);

                    this.liveWs.onopen = () => {
                        console.log('Gemini Live WS Connected');

                        // Send Initial Setup
                        const setupMsg = {
                            setup: {
                                model: "models/gemini-3.1-flash-live-preview",
                                systemInstruction: {
                                    parts: [{ text: creds.system_instruction }]
                                },
                                generationConfig: {
                                    responseModalities: ["AUDIO"],
                                    speechConfig: {
                                        voiceConfig: {
                                            prebuiltVoiceConfig: {
                                                voiceName: creds.voice_name || "Puck"
                                            }
                                        }
                                    }
                                },
                                tools: creds.tools || []
                            }
                        };
                        this.liveWs.send(JSON.stringify(setupMsg));

                        this.thinking = false;
                        this.updateCoreColor(true);

                        // Start Microphone immediately on connection open
                        this.startMicrophone();
                    };

                    this.liveWs.onmessage = async (event) => {
                        let data;
                        if (event.data instanceof Blob) {
                            const text = await event.data.text();
                            data = JSON.parse(text);
                        } else {
                            data = JSON.parse(event.data);
                        }

                        this.handleWsMessage(data);
                    };

                    this.liveWs.onerror = (error) => {
                        console.error('WebSocket Error:', error);
                        alert('WebSocket Error aufgetreten (siehe Console).');
                        this.stopLiveMode();
                    };

                    this.liveWs.onclose = (event) => {
                        console.log('WebSocket Closed', event);
                        if (event.code !== 1000 && event.code !== 1005) {
                            alert('WebSocket geschlossen! Code: ' + event.code + ' Reason: ' + event.reason);
                        }
                        this.stopLiveMode();
                    };

                } catch (err) {
                    console.error("Live Mode Init Error:", err);
                    alert("Init Error: " + err.message);
                    this.stopLiveMode();
                }
            },

            async startMicrophone() {
                try {
                    // Try to initialize AudioContext with target 16000Hz, fallback to default if unsupported
                    try {
                        this.audioContext = new (window.AudioContext || window.webkitAudioContext)({ sampleRate: 16000 });
                    } catch (e) {
                        console.warn('Could not initialize AudioContext with sampleRate 16000, falling back to default:', e);
                        this.audioContext = new (window.AudioContext || window.webkitAudioContext)();
                    }
                    
                    console.log('🎤 AudioContext initialisiert. Zustand vor Start:', this.audioContext.state, 'SampleRate:', this.audioContext.sampleRate);
                    
                    // Hardware AEC enabled with flexible / ideal constraints to prevent OverconstrainedError
                    let stream;
                    try {
                        stream = await navigator.mediaDevices.getUserMedia({
                            audio: {
                                channelCount: { ideal: 1 },
                                echoCancellation: { ideal: true },
                                noiseSuppression: { ideal: true },
                                autoGainControl: { ideal: true }
                            }
                        });
                    } catch (err) {
                        console.warn('Standard getUserMedia constraints failed, trying simple fallback:', err);
                        try {
                            stream = await navigator.mediaDevices.getUserMedia({
                                audio: {
                                    echoCancellation: true
                                }
                            });
                        } catch (err2) {
                            console.warn('Fallback constraints failed, requesting basic audio:', err2);
                            stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                        }
                    }

                    this.localAudioStream = stream;
                    console.log('🎤 Mikrofon-Stream erfolgreich erhalten.');

                    this.audioInput = this.audioContext.createMediaStreamSource(stream);

                    // Explicitly resume context if suspended (common in browsers when setup completes asynchronously)
                    if (this.audioContext.state === 'suspended') {
                        console.log('🎤 AudioContext ist suspended. Versuche zu aktivieren...');
                        await this.audioContext.resume();
                        console.log('🎤 AudioContext Zustand nach Aktivierungsversuch:', this.audioContext.state);
                    }

                    const processor = this.audioContext.createScriptProcessor(4096, 1, 1);
                    this.audioWorklet = processor;

                    processor.onaudioprocess = (e) => {
                        if (!this.liveWs || this.liveWs.readyState !== WebSocket.OPEN) return;

                        // We only send audio when the mic is not explicitly muted
                        if (this.isMicMuted) return;

                        // Prevent AI from hearing itself and interrupting (echo cancellation workaround)
                        if (this.isOutputActive() && !this.allowVoiceInterruption) return;

                        // Do not send audio data before setup is completed and acknowledged
                        if (!this.isSetupComplete) return;

                        let inputData = e.inputBuffer.getChannelData(0);
                        
                        // Downsample to 16000Hz if the AudioContext is not running at 16000Hz
                        if (this.audioContext.sampleRate !== 16000) {
                            const ratio = this.audioContext.sampleRate / 16000;
                            const newLength = Math.round(inputData.length / ratio);
                            const resampledData = new Float32Array(newLength);
                            for (let i = 0; i < newLength; i++) {
                                const position = i * ratio;
                                const index = Math.floor(position);
                                const fraction = position - index;
                                if (index + 1 < inputData.length) {
                                    resampledData[i] = inputData[index] * (1 - fraction) + inputData[index + 1] * fraction;
                                } else {
                                    resampledData[i] = inputData[index];
                                }
                            }
                            inputData = resampledData;
                        }

                        const pcmData = new Int16Array(inputData.length);
                        for (let i = 0; i < inputData.length; i++) {
                            let s = Math.max(-1, Math.min(1, inputData[i]));
                            pcmData[i] = s < 0 ? s * 0x8000 : s * 0x7FFF;
                        }

                        // Convert Int16Array directly to base64 string
                        let binary = '';
                        const bytes = new Uint8Array(pcmData.buffer);
                        for (let i = 0; i < bytes.byteLength; i++) {
                            binary += String.fromCharCode(bytes[i]);
                        }
                        const base64Audio = btoa(binary);

                        const msg = {
                            realtimeInput: {
                                audio: {
                                    mimeType: 'audio/pcm;rate=16000',
                                    data: base64Audio
                                }
                            }
                        };
                        this.liveWs.send(JSON.stringify(msg));
                    };

                    this.audioInput.connect(processor);
                    processor.connect(this.audioContext.destination);

                    // Parallele Spracherkennung auf Mobilgeräten deaktivieren, um Hardware-Konflikte & 'Dudumm'-Geräusche zu vermeiden
                    if (!this.isMobile) {
                        this.startSpeechRecognition();
                    }

                } catch (err) {
                    console.error('Mikrofon Fehler:', err);
                    alert('Mikrofon Zugriff verweigert oder Fehler: ' + err.message);
                    this.stopLiveMode();
                }
            },

            startSpeechRecognition() {
                if (this.isLiveMode && this.isMobile) {
                    console.log('🎤 Mobile Live Mode: Überspringe parallele webkitSpeechRecognition zur Vermeidung von Hardware-Konflikten.');
                    return;
                }
                if (!('webkitSpeechRecognition' in window) && !('SpeechRecognition' in window)) return;

                const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
                this.recognition = new SpeechRecognition();
                this.recognition.continuous = true;
                this.recognition.interimResults = false;
                this.recognition.lang = 'de-DE';

                this.recognition.onresult = (event) => {
                    for (let i = event.resultIndex; i < event.results.length; ++i) {
                        if (event.results[i].isFinal) {
                            const transcript = event.results[i][0].transcript.trim();
                            if (transcript) {
                                this.funkiLogs.push({ role: 'user', time: new Date().toLocaleTimeString('de-DE'), message: transcript });
                                this.chatHistory.push({ role: 'user', content: transcript });
                                if (this.$wire) {
                                    this.$wire.saveUserLiveMessage(transcript);
                                }
                            }
                        }
                    }
                };

                this.recognition.onerror = (e) => {
                    /*console.log('Speech recognition error', e);*/
                };

                this.recognition.onend = () => {
                    if (this.isLiveMode && !this.isMicMuted) {
                        try { this.recognition.start(); } catch(e) {}
                    }
                };

                try { this.recognition.start(); } catch(e) {}
            },

            stopSpeechRecognition() {
                if (this.recognition) {
                    this.recognition.onend = null;
                    try { this.recognition.stop(); } catch(e) {}
                    this.recognition = null;
                }
            },

            async handleWsMessage(data) {
                if (data.setupComplete) {
                    console.log('Gemini Live Setup Complete received');
                    this.isSetupComplete = true;
                    return;
                }
                if (!this.hasOwnProperty('currentLiveTranscript')) {
                    this.currentLiveTranscript = "";
                }

                if (data.serverContent && data.serverContent.modelTurn) {
                    const parts = data.serverContent.modelTurn.parts;
                    let chunkText = "";
                    parts.forEach(part => {
                        if (part.inlineData && part.inlineData.data) {
                            this.playLiveAudioChunk(part.inlineData.data);
                        }
                        if (part.text) {
                            chunkText += part.text;
                        }
                    });

                    if (chunkText) {
                        this.currentLiveTranscript += chunkText;
                    }
                }

                // Wait until the AI is completely done speaking to save the block
                if (data.serverContent && data.serverContent.turnComplete) {
                    if (this.currentLiveTranscript.trim() !== '') {
                        let finalTxt = this.currentLiveTranscript.trim();
                        let agentName = data.agent_name || this.activeAgentName;
                        this.chatHistory.push({ role: 'assistant', content: finalTxt, name: agentName });
                        this.funkiLogs.push({ role: 'ai', time: new Date().toLocaleTimeString('de-DE'), message: finalTxt.replace(/\[.*?\]/s, '') });
                        this.currentLiveTranscript = ""; // Reset for next turn
                    }
                }

                if (data.serverContent && data.serverContent.interrupted) {
                    this.stopCurrentAudioPlayback();
                }

                // Handle Tool Calls from WebSocket
                if (data.toolCall) {
                    const call = data.toolCall.functionCalls[0];
                    if (call) {
                        try {
                            const res = await fetch('/api/ai/execute', {
                                method: 'POST',
                                credentials: 'same-origin',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                                },
                                body: JSON.stringify({
                                    function: call.name,
                                    args: call.args,
                                    chat_session_id: this.$wire.currentChatSessionId,
                                    session_id: '{{ session()->getId() }}'
                                })
                            });
                            const resultData = await res.json();

                            // Visualize
                            this.renderAnalytics([resultData]);

                            // MAGIC: Synchronize frontend UI if the agent decided to switch itself!
                            if (call.name === 'system_switch_agent' && resultData.result && resultData.result.status === 'success') {
                                // This triggers the Livewire updatedAgentId hook which in turn dispatches 'agent-changed',
                                // naturally shutting down this WS connection and spinning up the new one,
                                // exactly as if the user clicked the dropdown manually!
                                @this.set('agentId', resultData.result.agent_id);
                            }

                            // MAGIC: Handle explicit UI events returned by tools (like navigation)
                            let frontendEvents = resultData.result && resultData.result._frontend_events;
                            let frontendEvent = resultData.result && (resultData.result._frontend_event || resultData.result._event);

                            let eventsToProcess = [];
                            if (frontendEvents && Array.isArray(frontendEvents)) eventsToProcess = [...frontendEvents];
                            if (frontendEvent) eventsToProcess.push(frontendEvent);

                            eventsToProcess.forEach(ev => {
                                if (ev.type === 'navigate' && ev.url) {
                                    if (typeof window.Livewire !== 'undefined') {
                                        window.Livewire.navigate(ev.url);
                                    } else {
                                        window.location.href = ev.url;
                                    }
                                } else if ((ev.type === 'dispatch' || !ev.type) && ev.name) {
                                    window.dispatchEvent(new CustomEvent(ev.name, { detail: ev.detail || {} }));
                                }
                            });

                            // Send Tool Response back to Gemini
                            const toolResp = {
                                toolResponse: {
                                    functionResponses: [{
                                        id: call.id,
                                        name: call.name,
                                        response: {
                                            result: resultData
                                        }
                                    }]
                                }
                            };
                            this.liveWs.send(JSON.stringify(toolResp));
                        } catch (e) {
                            console.error("Tool execution failed", e);
                        }
                    }
                }
            },

            stopCurrentAudioPlayback() {
                if (this.audioContext) {
                    this.audioContext.suspend();
                }
                if (this.activeAudioSources && this.activeAudioSources.length > 0) {
                    this.activeAudioSources.forEach(source => {
                        try { source.stop(); } catch(e) {}
                        try { source.disconnect(); } catch(e) {}
                    });
                    this.activeAudioSources = [];
                }
                this.nextPlayTime = 0;
                this.isSpeaking = false;
                this.updateCoreColor(true);

                if (this.audioContext) {
                    setTimeout(() => {
                        if (this.audioContext) this.audioContext.resume();
                    }, 20);
                }
            },

            playLiveAudioChunk(base64Data) {
                if (!this.audioContext) return;

                this.thinking = false;
                this.isSpeaking = true;
                this.updateCoreColor(true);

                const binaryString = window.atob(base64Data);
                const len = binaryString.length;
                const bytes = new Uint8Array(len);
                for (let i = 0; i < len; i++) {
                    bytes[i] = binaryString.charCodeAt(i);
                }

                const pcm16 = new Int16Array(bytes.buffer);
                const float32 = new Float32Array(pcm16.length);
                for (let i = 0; i < pcm16.length; i++) {
                    float32[i] = pcm16[i] / 32768;
                }

                const audioBuffer = this.audioContext.createBuffer(1, float32.length, 24000);
                audioBuffer.getChannelData(0).set(float32);

                const source = this.audioContext.createBufferSource();
                source.buffer = audioBuffer;

                // Directly connect to output for pure, clean Gemini audio
                source.connect(this.audioContext.destination);

                if (this.nextPlayTime < this.audioContext.currentTime) {
                    this.nextPlayTime = this.audioContext.currentTime;
                }

                if (!this.activeAudioSources) this.activeAudioSources = [];
                this.activeAudioSources.push(source);

                source.start(this.nextPlayTime);
                this.nextPlayTime += audioBuffer.duration;

                source.onended = () => {
                    this.activeAudioSources = this.activeAudioSources.filter(s => s !== source);
                    if (this.audioContext && this.audioContext.currentTime >= this.nextPlayTime - 0.1) {
                        this.isSpeaking = false;
                        this.updateCoreColor(true);
                    }
                };
            },

            stopLiveMode() {
                if (this.liveWs) {
                    this.liveWs.close();
                    this.liveWs = null;
                }
                if (this.localAudioStream) {
                    console.log('🎤 Beende alle Mikrofon-Tracks...');
                    this.localAudioStream.getTracks().forEach(track => track.stop());
                    this.localAudioStream = null;
                }
                if (this.audioInput) {
                    this.audioInput.disconnect();
                    this.audioInput = null;
                }
                if (this.audioWorklet) {
                    this.audioWorklet.disconnect();
                    this.audioWorklet = null;
                }

                this.stopSpeechRecognition();

                this.isLiveMode = false;
                if (this.audioContext) {
                    this.audioContext.close();
                    this.audioContext = null;
                }
                if (this.liveRecognition) {
                    this.liveRecognition.onend = null;
                    try { this.liveRecognition.abort(); } catch(e) {}
                    try { this.liveRecognition.stop(); } catch(e) {}
                    this.liveRecognition = null;
                }
                this.nextPlayTime = 0;
                this.isSpeaking = false;
                this.thinking = false;
                this.updateCoreColor(true);
            },

            // --- CAMERA VISION STREAMING ---
            cameraFps: '0.00',
            cameraInterval: null,
            cameraCanvas: null,
            cameraContext: null,
            lastDecodedFrames: 0,
            cameraExpanded: false,

            startCameraCapture() {
                if (this.cameraInterval) clearInterval(this.cameraInterval);
                
                const video = document.getElementById('funki-local-camera');
                if (!video) return;

                if (!this.cameraCanvas) {
                    this.cameraCanvas = document.createElement('canvas');
                    this.cameraContext = this.cameraCanvas.getContext('2d');
                }

                this.lastDecodedFrames = 0;

                this.cameraInterval = setInterval(() => {
                    if (!video || !video.srcObject) {
                        this.cameraFps = '0.00';
                        return;
                    }

                    // Calculate real FPS if supported
                    if (typeof video.getVideoPlaybackQuality === 'function') {
                        const quality = video.getVideoPlaybackQuality();
                        const currentFrames = quality.totalVideoFrames;
                        const fps = currentFrames - this.lastDecodedFrames;
                        this.lastDecodedFrames = currentFrames;
                        this.cameraFps = Math.max(0, fps).toFixed(2);
                    } else {
                        // Fallback static FPS if API not supported
                        this.cameraFps = '30.00';
                    }

                    // Only send if Live Mode is active and WebSocket is open
                    if (this.isLiveMode && this.liveWs && this.liveWs.readyState === WebSocket.OPEN) {
                        if (video.videoWidth > 0 && video.videoHeight > 0) {
                            const MAX_WIDTH = 640;
                            let width = video.videoWidth;
                            let height = video.videoHeight;
                            
                            if (width > MAX_WIDTH) {
                                height = height * (MAX_WIDTH / width);
                                width = MAX_WIDTH;
                            }
                            
                            this.cameraCanvas.width = width;
                            this.cameraCanvas.height = height;
                            this.cameraContext.drawImage(video, 0, 0, width, height);
                            
                            // Get base64 JPEG
                            const dataUrl = this.cameraCanvas.toDataURL('image/jpeg', 0.6);
                            const base64Data = dataUrl.split(',')[1];
                            
                            const msg = {
                                realtimeInput: {
                                    mediaChunks: [{
                                        mimeType: "image/jpeg",
                                        data: base64Data
                                    }]
                                }
                            };
                            this.liveWs.send(JSON.stringify(msg));
                        }
                    }
                }, 1000);
            },

            init() {
                this.startCameraCapture();
                
                window.addEventListener('ai-analyze-camera', async (e) => {
                    const video = document.getElementById('funki-local-camera');
                    if (!video || !video.srcObject) {
                        this.$wire.set('input', '[SYSTEM_LOG]: Fehler: Die Kamera ist aktuell nicht eingeschaltet. Bitte informiere den Nutzer darüber.');
                        this.$wire.sendMessage();
                        return;
                    }
                    if (video.videoWidth > 0 && video.videoHeight > 0) {
                        let width = video.videoWidth;
                        let height = video.videoHeight;
                        
                        if (!this.cameraCanvas) {
                            this.cameraCanvas = document.createElement('canvas');
                            this.cameraContext = this.cameraCanvas.getContext('2d');
                        }
                        this.cameraCanvas.width = width;
                        this.cameraCanvas.height = height;
                        this.cameraContext.drawImage(video, 0, 0, width, height);
                        
                        this.cameraCanvas.toBlob(async (blob) => {
                            if (!blob) return;
                            
                            let formData = new FormData();
                            formData.append('image', blob, 'snapshot.jpg');
                            
                            try {
                                const response = await fetch('/api/ai/camera/snapshot', {
                                    method: 'POST',
                                    headers: {
                                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                                    },
                                    body: formData
                                });
                                const resData = await response.json();
                                if(resData.status === 'success') {
                                    let systemInfo = '\n\n[SYSTEM_INFO]: Dieses Bild liegt nun im Dateimanager unter dem Pfad: ' + resData.file_path + ' . Nutze diesen Pfad zusammen mit dem Werkzeug "camera_process_snapshot", wenn der Nutzer dich bittet, das Bild per Mail zu senden, im PDF zu speichern oder im Dateimanager strukturiert abzulegen.';
                                    
                                    if(window.Livewire) {
                                        this.$dispatch('refreshFileManager');
                                    }

                                    // Send text + path to the agent using the NEW protocol
                                    this.$wire.set('input', 'Hier ist das aktuelle Kamerabild für deine Analyse.' + systemInfo + '\n\n[SYSTEM_IMAGE_PATH]: ' + resData.file_path);
                                    this.$wire.sendMessage();
                                } else {
                                    console.error('Failed to save snapshot locally:', resData);
                                    this.$wire.set('input', '[SYSTEM_LOG]: Fehler: Das Kamerabild konnte nicht auf den Server geladen werden (' + (resData.message || 'Unbekannt') + ').');
                                    this.$wire.sendMessage();
                                }
                            } catch(err) {
                                console.error('Failed to upload snapshot:', err);
                                this.$wire.set('input', '[SYSTEM_LOG]: Fehler: Der Server-Upload des Kamerabildes ist fehlgeschlagen.');
                                this.$wire.sendMessage();
                            }
                        }, 'image/jpeg', 0.9);
                    }
                });
            },
