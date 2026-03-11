window.funkenflugExpress = function() {
    let engine = null;

    return {
        // Inherited state from parent or local state
        distance: 0,
        funkenCollected: 0,
        shieldEnergy: 100,
        gameState: 'ready', // 'ready', 'playing', 'gameover'
        energyWarning: false,
        bgmVolumeUi: 20,
        isBgmPlaying: false,
        isPaused: false,
        

        
        // Skills: [Multishoot, Teleport, Shield, Ultimate]
        skillLevels: [1, 1, 1, 1], // Unlocked
        skillCooldowns: [0, 0, 0, 0], // Current cooldowns
        skillMaxCooldowns: [30, 0.5, 20, 60], // 0.5s CD for Teleport (index 1)
        skillFlash: [false, false, false, false],

        init() {
            window.alpineComponentContext = this;
            window.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && this.gameState === 'playing') {
                    this.togglePause();
                }
                if (e.key?.toLowerCase() === 'v') {
                    this.toggleFullscreen();
                }
            });

            // Immediate Init if already active
            if (this.activeGame === 'funkenflug') {
                setTimeout(() => { if (!engine) this.initEngine(); }, 300);
            }

            this.$watch('bgmVolumeUi', val => {
                let audio = this.$refs.ffBgmAudio;
                if(audio) audio.volume = (val / 100) * 0.3;
            });

            // Watch for activeGame changes from parent scope
            this.$watch('activeGame', value => {
                if (value === 'funkenflug') {
                    setTimeout(() => {
                        if (!engine) { this.initEngine(); } else { engine.resize(); }
                    }, 300);
                } else {
                    this.quitGame();
                }
            });

            // Cooldown ticker
            setInterval(() => {
                if (this.gameState === 'playing') {
                    for(let i=0; i<4; i++) {
                        if (this.skillCooldowns[i] > 0) this.skillCooldowns[i]--;
                    }
                }
            }, 1000);
        },

        toggleFullscreen() {
            let elem = document.getElementById('funkenflug-container')?.parentElement;
            if (!elem) return;
            if (!document.fullscreenElement) {
                elem.requestFullscreen().catch(err => {
                    console.log(`Fehler beim Vollbild: ${err.message}`);
                });
            } else {
                document.exitFullscreen();
            }
        },

        togglePause() {
            if (this.gameState !== 'playing' || !engine) return;
            let audio = this.$refs.ffBgmAudio;
            if (this.isPaused) {
                this.isPaused = false;
                engine.resume();
                if (audio && this.isBgmPlaying) audio.play().catch(e => console.warn(e));
            } else {
                this.isPaused = true;
                engine.pause();
                if (audio) audio.pause();
            }
        },

        resumeGame() {
            if (this.gameState === 'playing' && this.isPaused && engine) {
                this.isPaused = false;
                engine.resume();
                let audio = this.$refs.ffBgmAudio;
                if (audio && this.isBgmPlaying) audio.play().catch(e => console.warn(e));
            }
        },

        quitGame() {
            this.gameState = 'ready';
            this.isPaused = false;
            if (engine) engine.cleanup();
            let audio = this.$refs.ffBgmAudio;
            if (audio) {
                audio.pause();
                audio.currentTime = 0;
            }
        },

        toggleMute() {
            let audio = this.$refs.ffBgmAudio;
            if(!audio) return;
            if (this.isBgmPlaying) { audio.pause(); this.isBgmPlaying = false; }
            else { audio.play().catch(e => console.log(e)); this.isBgmPlaying = true; }
        },

        quitGame() {
            let audio = this.$refs.ffBgmAudio;
            if (audio) {
                audio.pause();
                audio.currentTime = 0;
                this.isBgmPlaying = false;
            }
            if (engine) engine.cleanup();
            this.gameState = 'ready';
            if(this.activeGame === 'funkenflug') {
                this.activeGame = null;
            }
        },

        async attemptStartGame() {
            console.log("[Alpine] attemptStartGame called");
            if (typeof window.THREE === 'undefined') {
                alert("Three.js lädt noch. Bitte kurz warten...");
                return;
            }

            if (!engine) {
                console.log("[Alpine] Engine is null, initializing...");
                try {
                    this.initEngine();
                } catch (e) {
                    console.error("[Alpine] Failed to init engine:", e);
                    return;
                }
            }

            console.log("[Alpine] Calling $wire.consumeEnergy()...");
            try {
                let hasEnergy = await this.$wire.consumeEnergy();
                console.log("[Alpine] consumeEnergy returned:", hasEnergy);
                if (hasEnergy) {
                    this.startGame();
                } else {
                    this.energyWarning = true;
                    setTimeout(() => { this.energyWarning = false; }, 3000);
                }
            } catch (e) {
                console.warn("[Alpine] Backend-Check (consumeEnergy) failed:", e);
                console.log("Starte Spiel im Testmodus.");
                this.startGame();
            }
        },

        initEngine() {
            console.log("[Alpine] initEngine() running...");
            if (typeof window.THREE === 'undefined') {
                console.error("[Alpine] THREE is undefined");
                return;
            }

            const container = document.getElementById('funkenflug-container');
            const layer = document.getElementById('ff-floating-layer');
            
            if (!container || !layer) {
                console.error("[Alpine] Container or Layer strictly missing from DOM!", container, layer);
                return;
            }

            const callbacks = {
                onDistanceUpdate: (dist) => {
                    this.distance = Math.floor(dist);
                    // The instruction snippet had this.showUiMessage(text, color); here,
                    // but 'text' and 'color' are not defined in this scope.
                    // Assuming it was a placeholder or intended for a different context,
                    // keeping the original distance update logic.
                },
                onSparkCollected: () => {
                    this.funkenCollected += 1;
                    this.distance += 25;
                    if (window.ArcadeAudio) new window.ArcadeAudio().playPickup();
                },
                onShieldUpdate: (energy) => {
                    this.shieldEnergy = Math.max(0, Math.floor(energy));
                },
                onCooldownReduction: (amount) => {
                    for(let i=0; i<4; i++) {
                        if (this.skillCooldowns[i] > 0) {
                            this.skillCooldowns[i] = Math.max(0, this.skillCooldowns[i] - amount);
                        }
                    }
                },
                onGameOver: () => {
                    if (this.gameState !== 'gameover') {
                        this.gameState = 'gameover';
                        
                        // Stop music
                        let audio = this.$refs.ffBgmAudio;
                        if (audio) {
                            audio.pause();
                            this.isBgmPlaying = false;
                        }

                        try {
                            this.$wire.saveGameRecord(this.distance, this.funkenCollected);
                        } catch(e) {
                            console.error("[Alpine] Failed to save game record:", e);
                        }
                    }
                }
            };

            const assets = {
                rocket: "{{ asset('gamification/images/funki_rocket.glb') }}",
                meteor: "{{ asset('gamification/images/meteorit.glb') }}",
                sharp_stone: "{{ asset('gamification/images/sharp_stone.glb') }}"
            };

            console.log("[Alpine] Instantiating FunkenflugEngine...");
            engine = new window.FunkenflugEngine(container, layer, callbacks, assets);
            console.log("[Alpine] Engine created:", engine);
        },

        startGame() {
            console.log("[Alpine] startGame() called");
            this.distance = 0;
            this.funkenCollected = 0;
            this.shieldEnergy = 100;
            this.skillCooldowns = [0, 0, 0, 0];
            this.gameState = 'playing';
            this.isPaused = false;

            let audio = this.$refs.ffBgmAudio;
            if (audio) {
                audio.volume = (this.bgmVolumeUi / 100) * 0.3;
                audio.currentTime = 0; // Restart track
                audio.play().then(() => { this.isBgmPlaying = true; }).catch(e => console.warn(e));
            }

            if(engine) {
                console.log("[Alpine] engine.start() called");
                engine.start();
            } else {
                console.error("[Alpine] Engine is still null in startGame!");
            }
        },

        useSkill(index) {
            if (this.isPaused) return;
            let i = index - 1;
            if (this.skillLevels[i] > 0 && this.skillCooldowns[i] <= 0 && this.gameState === 'playing') {
                // Trigger skill in engine
                let success = false;
                if(engine) {
                    success = engine.triggerSkill(index);
                }

                if (success) {
                    this.skillCooldowns[i] = this.skillMaxCooldowns[i];
                    this.skillFlash[i] = true;
                    setTimeout(() => { this.skillFlash[i] = false; }, 200);
                }
            }
        }
    };
};
