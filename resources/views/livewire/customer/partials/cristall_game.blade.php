{{-- resources/views/livewire/customer/partials/cristall_game.blade.php --}}

{{-- ========================================== --}}
{{-- STYLES FÜR KRISTALL-KOLLAPS --}}
{{-- ========================================== --}}
<style>
    .game-grid {
        display: grid;
        grid-template-columns: repeat(8, 1fr);
        gap: 6px;
        padding: 10px;
        background: #030712;
        border-radius: 1.5rem;
        border: 1px solid #1f2937;
        aspect-ratio: 1;
        width: 100%;
        max-width: 550px;
        margin: 0 auto;
        box-shadow: inset 0 0 30px rgba(0,0,0,0.8);
    }

    .crystal {
        width: 100%;
        height: 100%;
        border-radius: 0.5rem;
        cursor: pointer;
        /* Schnellere und fließendere Animationen */
        transition: transform 0.15s cubic-bezier(0.4, 0, 0.2, 1), top 0.3s cubic-bezier(0.34, 1.56, 0.64, 1), left 0.3s cubic-bezier(0.34, 1.56, 0.64, 1), opacity 0.2s;
        position: relative;
        will-change: transform, top, left, opacity;
        animation: crystalBreathe 3s infinite alternate ease-in-out;
    }

    /* Leichte, ständige Bewegung (Atmen) für Interaktivität */
    @keyframes crystalBreathe {
        0% { transform: scale(1); }
        100% { transform: scale(0.96); }
    }

    .crystal:hover {
        transform: scale(1.08) !important; /* Wichtig um die breathe animation zu überschreiben */
        z-index: 10;
        animation: none;
    }

    .crystal.selected {
        transform: scale(1.15) !important;
        z-index: 20;
        box-shadow: 0 0 20px rgba(255,255,255,0.6);
        outline: 2px solid #fff;
        outline-offset: 3px;
        animation: none;
    }

    .crystal.exploded {
        opacity: 0;
        transform: scale(0) rotate(90deg) !important;
        transition: all 0.25s ease-out;
    }

    /* Partikel Explosion CSS */
    .particle {
        position: absolute;
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background-color: white;
        pointer-events: none;
        z-index: 30;
        opacity: 0;
        animation: explodeParticle 0.6s ease-out forwards;
    }

    @keyframes explodeParticle {
        0% { transform: translate(0,0) scale(1); opacity: 1; }
        100% { transform: translate(var(--tx), var(--ty)) scale(0); opacity: 0; }
    }

    .c-type-1 { background: radial-gradient(circle at 30% 30%, #ef4444, #991b1b); box-shadow: inset 0 0 15px #fca5a5, 0 4px 6px rgba(0,0,0,0.5); }
    .c-type-2 { background: radial-gradient(circle at 30% 30%, #3b82f6, #1e40af); box-shadow: inset 0 0 15px #93c5fd, 0 4px 6px rgba(0,0,0,0.5); }
    .c-type-3 { background: radial-gradient(circle at 30% 30%, #10b981, #065f46); box-shadow: inset 0 0 15px #6ee7b7, 0 4px 6px rgba(0,0,0,0.5); }
    .c-type-4 { background: radial-gradient(circle at 30% 30%, #eab308, #854d0e); box-shadow: inset 0 0 15px #fde047, 0 4px 6px rgba(0,0,0,0.5); }
    .c-type-5 { background: radial-gradient(circle at 30% 30%, #a855f7, #6b21a8); box-shadow: inset 0 0 15px #d8b4fe, 0 4px 6px rgba(0,0,0,0.5); }
    .c-type-6 { background: radial-gradient(circle at 30% 30%, #06b6d4, #164e63); box-shadow: inset 0 0 15px #67e8f9, 0 4px 6px rgba(0,0,0,0.5); }
</style>

{{-- ========================================== --}}
{{-- SCRIPT: SPIEL LOGIK (Isoliert) --}}
{{-- ========================================== --}}
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('kristallKollapsGame', () => ({
            rows: 8,
            cols: 8,
            types: 6,
            board: [],
            active: false,
            score: 0,
            moves: 15, // Anzahl Züge angepasst für besseres Balancing
            isProcessing: false,
            selected: null,
            particles: [], // Array für Explosionen

            init() {
                this.$watch('activeGame', value => {
                    if(value === 'kristall') {
                        // Resette das Spiel NICHT automatisch wenn man nur kurz zurück geht,
                        // außer es war bereits Game Over.
                        if(!this.active && this.moves <= 0) {
                            // Spiel muss manuell via Button gestartet werden
                        } else if(this.board.length === 0) {
                            this.fillBoard();
                        }
                    }
                });
            },

            async attemptStartGame() {
                // Prüfe via Livewire ob genug Energie vorhanden ist
                let hasEnergy = await @this.call('consumeEnergy');

                if (hasEnergy) {
                    this.start();
                }
            },

            start() {
                this.active = true;
                this.score = 0;
                this.moves = 15; // 15 Züge pro Energie
                this.isProcessing = true;
                this.selected = null;
                this.particles = [];

                do {
                    this.fillBoard();
                } while (this.findMatches().length > 0 || !this.hasPossibleMoves());

                this.isProcessing = false;
            },

            fillBoard() {
                this.board = Array.from({length: this.rows}, (_, r) =>
                    Array.from({length: this.cols}, (_, c) => ({
                        type: Math.floor(Math.random() * this.types) + 1,
                        id: `${r}-${c}-${Math.random()}`
                    }))
                );
            },

            // NEU: Prüft ob es überhaupt noch einen möglichen Zug gibt
            hasPossibleMoves() {
                for (let r = 0; r < this.rows; r++) {
                    for (let c = 0; c < this.cols; c++) {
                        // Simuliere Swap Rechts
                        if (c < this.cols - 1) {
                            this.swapSync({r,c}, {r, c:c+1});
                            let matches = this.findMatches();
                            this.swapSync({r,c}, {r, c:c+1}); // Zurücktauschen
                            if(matches.length > 0) return true;
                        }
                        // Simuliere Swap Unten
                        if (r < this.rows - 1) {
                            this.swapSync({r,c}, {r:r+1, c});
                            let matches = this.findMatches();
                            this.swapSync({r,c}, {r:r+1, c}); // Zurücktauschen
                            if(matches.length > 0) return true;
                        }
                    }
                }
                return false;
            },

            // Synchrone Swap-Hilfsfunktion für den Validierungscheck
            swapSync(p1, p2) {
                let tmp = this.board[p1.r][p1.c];
                this.board[p1.r][p1.c] = this.board[p2.r][p2.c];
                this.board[p2.r][p2.c] = tmp;
            },

            async handleClick(r, c) {
                if (this.isProcessing || !this.active) return;

                if (!this.selected) {
                    this.selected = {r, c};
                } else {
                    if (this.selected.r === r && this.selected.c === c) {
                        this.selected = null;
                        return;
                    }

                    const isAdj = (Math.abs(this.selected.r - r) + Math.abs(this.selected.c - c)) === 1;

                    if (isAdj) {
                        let p1 = this.selected;
                        this.selected = null;
                        await this.trySwap(p1, {r, c});
                    } else {
                        this.selected = {r, c};
                    }
                }
            },

            async trySwap(p1, p2) {
                this.isProcessing = true;

                await this.swap(p1, p2);

                let matches = this.findMatches();
                if (matches.length > 0) {
                    this.moves--;
                    await this.processMatches();
                } else {
                    await this.swap(p1, p2);
                }

                // Shuffle wenn keine Züge mehr möglich sind, aber Spiel noch läuft
                if(this.active && this.moves > 0 && !this.hasPossibleMoves()) {
                    await new Promise(r => setTimeout(r, 500));
                    this.shuffleBoard();
                }

                this.isProcessing = false;

                if(this.moves <= 0) {
                    this.endGame();
                }
            },

            async swap(p1, p2) {
                let tmp = this.board[p1.r][p1.c];
                this.board[p1.r][p1.c] = this.board[p2.r][p2.c];
                this.board[p2.r][p2.c] = tmp;
                await new Promise(r => setTimeout(r, 150)); // Schnellerer Swap
            },

            findMatches() {
                let m = new Set();

                for (let r=0; r<this.rows; r++) {
                    for (let c=0; c<this.cols-2; c++) {
                        let t = this.board[r][c].type;
                        if(t !== 0 && t === this.board[r][c+1].type && t === this.board[r][c+2].type) {
                            [0,1,2].forEach(i => m.add(`${r},${c+i}`));
                        }
                    }
                }

                for (let c=0; c<this.cols; c++) {
                    for (let r=0; r<this.rows-2; r++) {
                        let t = this.board[r][c].type;
                        if(t !== 0 && t === this.board[r+1][c].type && t === this.board[r+2][c].type) {
                            [0,1,2].forEach(i => m.add(`${r+i},${c}`));
                        }
                    }
                }

                return Array.from(m).map(s => { let [r,c] = s.split(','); return {r:+r, c:+c}; });
            },

            createExplosion(r, c, colorClass) {
                // Konvertiere die Typen-Klasse zu einer groben Farbe für die Partikel
                let color = '#fff';
                if(colorClass === 'c-type-1') color = '#ef4444';
                if(colorClass === 'c-type-2') color = '#3b82f6';
                if(colorClass === 'c-type-3') color = '#10b981';
                if(colorClass === 'c-type-4') color = '#eab308';
                if(colorClass === 'c-type-5') color = '#a855f7';
                if(colorClass === 'c-type-6') color = '#06b6d4';

                // Ermittle relative Position im Grid (grob)
                const percentX = (c / this.cols) * 100 + 6; // + Offset zur Mitte der Zelle
                const percentY = (r / this.rows) * 100 + 6;

                for(let i=0; i<8; i++) {
                    const angle = Math.random() * Math.PI * 2;
                    const distance = Math.random() * 40 + 20;
                    const tx = Math.cos(angle) * distance;
                    const ty = Math.sin(angle) * distance;

                    const p = {
                        id: Date.now() + Math.random(),
                        x: percentX,
                        y: percentY,
                        tx: `${tx}px`,
                        ty: `${ty}px`,
                        color: color
                    };
                    this.particles.push(p);

                    // Cleanup
                    setTimeout(() => {
                        this.particles = this.particles.filter(pt => pt.id !== p.id);
                    }, 600);
                }
            },

            async processMatches() {
                let matches;
                let comboMultiplier = 1;

                while ((matches = this.findMatches()).length > 0) {

                    // Berechne Punkte: Basis 2 pro Stein * Combo * (1 + Bonus für >3 Steine)
                    let pointsEarned = matches.length * 2 * comboMultiplier;
                    if(matches.length > 3) pointsEarned += 5; // Bonus für 4er oder 5er Reihen

                    this.score += pointsEarned;

                    // Markieren und explodieren lassen
                    matches.forEach(p => {
                        const cell = this.board[p.r][p.c];
                        this.createExplosion(p.r, p.c, 'c-type-' + cell.type);
                        cell.type = 0;
                    });

                    await new Promise(r => setTimeout(r, 250)); // Kürzere Explosionszeit

                    this.applyGravity();

                    await new Promise(r => setTimeout(r, 350)); // Fall Animation
                    comboMultiplier++;
                }
            },

            applyGravity() {
                for (let c=0; c<this.cols; c++) {
                    let col = [];
                    for(let r=0; r<this.rows; r++) {
                        if(this.board[r][c].type !== 0) col.push(this.board[r][c]);
                    }

                    let missing = this.rows - col.length;
                    for(let i=0; i<missing; i++) {
                        col.unshift({
                            type: Math.floor(Math.random() * this.types) + 1,
                            id: `new-${c}-${Math.random()}-${Date.now()}`
                        });
                    }

                    for(let r=0; r<this.rows; r++) {
                        this.board[r][c] = col[r];
                    }
                }
            },

            shuffleBoard() {
                do {
                    // Sammle alle Steine
                    let pool = [];
                    for(let r=0; r<this.rows; r++) {
                        for(let c=0; c<this.cols; c++) {
                            pool.push(this.board[r][c].type);
                        }
                    }
                    // Mischen
                    pool.sort(() => Math.random() - 0.5);
                    // Neu verteilen
                    let i = 0;
                    for(let r=0; r<this.rows; r++) {
                        for(let c=0; c<this.cols; c++) {
                            this.board[r][c].type = pool[i++];
                            this.board[r][c].id = `shuf-${r}-${c}-${Math.random()}`; // Zwingt Vue/Alpine zum neu rendern
                        }
                    }
                } while(this.findMatches().length > 0 || !this.hasPossibleMoves());
            },

            async endGame() {
                this.active = false;
                // Sende die gesammelten Funken an Livewire
                if(this.score > 0) {
                    await @this.call('rewardGameSparks', this.score);
                }
            }
        }));
    });
</script>

<div x-show="showGameModal" style="display: none;" class="fixed inset-0 z-[5000] flex items-start justify-center pt-4 sm:pt-10 p-4 sm:p-6 overflow-hidden">

    <div x-show="showGameModal" x-transition.opacity.duration.500ms class="absolute inset-0 bg-gray-950/90 backdrop-blur-xl" @click="showGameModal = false; activeGame = null"></div>

    <div x-show="showGameModal"
         x-transition:enter="transition ease-out duration-500 delay-100"
         x-transition:enter-start="opacity-0 scale-95 translate-y-8"
         x-transition:enter-end="opacity-100 scale-100 translate-y-0"
         x-transition:leave="transition ease-in duration-300"
         x-transition:leave-start="opacity-100 scale-100 translate-y-0"
         x-transition:leave-end="opacity-0 scale-95 translate-y-8"
         class="relative w-full max-w-7xl h-[90vh] bg-gray-900 border border-gray-800 rounded-[2.5rem] shadow-[0_0_80px_rgba(0,0,0,0.8)] overflow-hidden flex flex-col">

        {{-- MODAL HEADER --}}
        <div class="px-6 sm:px-8 py-5 border-b border-gray-800 flex flex-col sm:flex-row justify-between items-center gap-4 sm:gap-6 bg-gray-950/80 shrink-0 z-20 shadow-lg">
            <div>
                <h3 class="text-2xl sm:text-3xl font-serif font-bold text-white tracking-tight" x-text="activeGame ? 'Kristall-Kollaps' : 'Die Funken-Schmiede'"></h3>
                <p class="text-gray-500 text-[10px] font-black uppercase tracking-widest mt-1" x-text="activeGame ? 'Kombiniere die Fragmente' : 'Wähle dein Schicksal und sammle Schätze'"></p>
            </div>

            <div class="flex items-center gap-4 sm:gap-6">

                {{-- NEU: BALKEN FÜR NÄCHSTEN GUTSCHEIN --}}
                <div class="hidden md:flex flex-col items-end mr-4">
                    <span class="text-[9px] text-gray-500 font-black uppercase tracking-widest mb-1">Nächster Gutschein bei Level <span x-text="$wire.nextVoucherLevel"></span></span>
                    <div class="w-48 h-2 bg-gray-800 rounded-full overflow-hidden">
                        {{-- Simpler Fortschrittsbalken basierend auf aktuellem Level vs nächstem Ziel --}}
                        <div class="h-full bg-gradient-to-r from-emerald-500 to-emerald-400 transition-all duration-1000"
                             :style="`width: ${ Math.min(100, Math.max(5, ($wire.level / $wire.nextVoucherLevel) * 100)) }%;`"></div>
                    </div>
                </div>

                <button x-show="activeGame" @click="activeGame = null" class="px-4 py-2 bg-gray-800 text-gray-300 rounded-xl font-black text-[10px] uppercase tracking-widest hover:bg-gray-700 transition-all flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg> Zurück
                </button>

                <div class="flex items-center gap-3 bg-gray-900 border border-gray-800 px-5 py-2.5 rounded-2xl shadow-inner relative group">
                    <svg class="w-5 h-5 text-blue-500 animate-pulse" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                    <div class="flex flex-col">
                        <span class="text-[9px] text-gray-500 font-black uppercase tracking-widest leading-none">Seelen-Energie</span>
                        <span class="text-white font-bold leading-none mt-1"><span x-text="$wire.energyBalance"></span> / <span x-text="$wire.maxEnergy"></span></span>
                    </div>

                    {{-- Tooltip für Energie --}}
                    <div class="absolute top-full left-1/2 -translate-x-1/2 mt-2 w-48 p-3 bg-gray-800 border border-gray-700 text-xs text-gray-300 rounded-xl opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all shadow-xl z-50 text-center">
                        Energie füllt sich jeden Tag um Mitternacht wieder auf.
                    </div>
                </div>

                <button @click="showGameModal = false; activeGame = null" class="p-3 rounded-full bg-gray-800 text-gray-400 hover:text-white hover:bg-red-500 transition-colors">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>
        </div>

        <div class="relative flex-1 w-full bg-gray-950 overflow-hidden">

            {{-- ANSICHT 1: AUSWAHLMENÜ --}}
            <div x-show="!activeGame" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 -translate-x-10" x-transition:enter-end="opacity-100 translate-x-0" class="absolute inset-0 overflow-y-auto no-scrollbar p-6 sm:p-10">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 sm:gap-8 max-w-6xl mx-auto">

                    <div @click="activeGame = 'kristall'" class="group relative bg-gray-900 rounded-3xl border border-gray-800 p-8 hover:border-emerald-500/50 transition-all duration-500 hover:-translate-y-2 hover:shadow-[0_20px_40px_rgba(16,185,129,0.15)] flex flex-col cursor-pointer">
                        <div class="absolute inset-0 bg-gradient-to-br from-emerald-500/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity rounded-3xl pointer-events-none"></div>
                        <div class="w-20 h-20 rounded-2xl bg-gray-950 border border-gray-800 flex items-center justify-center mb-8 group-hover:scale-110 transition-transform shadow-inner"><span class="text-4xl">💎</span></div>
                        <h4 class="text-3xl font-serif font-bold text-white mb-3">Kristall-Kollaps</h4>
                        <p class="text-gray-400 text-sm leading-relaxed mb-8 flex-1">Kombiniere leuchtende Fragmente in Kettenreaktionen. Ein rasantes Match-3 Erlebnis für Taktiker.</p>
                        <button class="w-full py-4 bg-gray-950 text-emerald-500 border border-emerald-500/30 rounded-xl font-black text-[10px] uppercase tracking-widest group-hover:bg-emerald-500 group-hover:text-white transition-all shadow-inner">Öffnen</button>
                    </div>

                    <div class="group relative bg-gray-900 rounded-3xl border border-gray-800 p-8 opacity-50 grayscale flex flex-col cursor-not-allowed">
                        <div class="w-20 h-20 rounded-2xl bg-gray-950 border border-gray-800 flex items-center justify-center mb-8 shadow-inner"><span class="text-4xl">🏺</span></div>
                        <h4 class="text-3xl font-serif font-bold text-white mb-3">Seelen-Schmiede</h4>
                        <p class="text-gray-400 text-sm leading-relaxed mb-8 flex-1">Verschmelze Elemente zu epischen Artefakten, bevor das Gefäß überläuft.</p>
                        <div class="w-full py-4 bg-gray-950 text-gray-500 border border-gray-800 rounded-xl font-black text-[10px] uppercase tracking-widest text-center shadow-inner">Bald verfügbar</div>
                    </div>

                    <div class="group relative bg-gray-900 rounded-3xl border border-gray-800 p-8 opacity-50 grayscale flex flex-col cursor-not-allowed">
                        <div class="w-20 h-20 rounded-2xl bg-gray-950 border border-gray-800 flex items-center justify-center mb-8 shadow-inner"><span class="text-4xl">🚀</span></div>
                        <h4 class="text-3xl font-serif font-bold text-white mb-3">Sternen-Drift</h4>
                        <p class="text-gray-400 text-sm leading-relaxed mb-8 flex-1">Steuere Funki durch Asteroidenfelder und sammle goldene Schweife in Lichtgeschwindigkeit.</p>
                        <div class="w-full py-4 bg-gray-950 text-gray-500 border border-gray-800 rounded-xl font-black text-[10px] uppercase tracking-widest text-center shadow-inner">Bald verfügbar</div>
                    </div>
                </div>
            </div>

            {{-- ANSICHT 2: DAS KRISTALL-KOLLAPS SPIEL --}}
            <div x-show="activeGame === 'kristall'" x-data="kristallKollapsGame()" x-transition:enter="transition ease-out duration-300 delay-200" x-transition:enter-start="opacity-0 translate-x-10" x-transition:enter-end="opacity-100 translate-x-0" class="absolute inset-0 overflow-y-auto p-6 sm:p-10 flex flex-col lg:flex-row items-start justify-center gap-8 lg:gap-16">

                {{-- Sidebar: Stats & Info --}}
                <div class="w-full lg:w-80 flex flex-col gap-6 shrink-0 mt-4">

                    {{-- Punktetafel --}}
                    <div class="bg-gray-900 border border-gray-800 rounded-3xl p-8 flex flex-col items-center gap-6 shadow-[0_20px_40px_rgba(0,0,0,0.5)] relative overflow-hidden">
                        {{-- Leichter Glow hinter den Punkten --}}
                        <div class="absolute top-0 left-1/2 -translate-x-1/2 w-32 h-32 bg-emerald-500/10 rounded-full blur-2xl pointer-events-none"></div>

                        <div class="text-center w-full relative z-10">
                            <p class="text-gray-500 text-[10px] font-black uppercase tracking-widest mb-2">Erspielte Funken</p>
                            <h4 class="text-6xl font-serif font-bold text-emerald-400 drop-shadow-[0_0_15px_rgba(16,185,129,0.4)]" x-text="score"></h4>
                        </div>
                        <div class="w-full h-px bg-gray-800 relative z-10"></div>
                        <div class="text-center w-full relative z-10">
                            <p class="text-gray-500 text-[10px] font-black uppercase tracking-widest mb-2">Züge übrig</p>
                            <h4 class="text-5xl font-serif font-bold text-white transition-colors" :class="{'text-red-500 drop-shadow-[0_0_10px_rgba(239,68,68,0.5)] animate-pulse': moves <= 5}" x-text="moves"></h4>
                        </div>
                    </div>

                    {{-- Start Button (Wenn nicht aktiv) --}}
                    <button x-show="!active && moves === 20" @click="attemptStartGame()" class="w-full py-5 bg-gradient-to-r from-emerald-500 to-emerald-400 text-gray-900 rounded-2xl font-black text-sm uppercase tracking-widest hover:scale-105 transition-transform shadow-[0_0_30px_rgba(16,185,129,0.4)] relative overflow-hidden group">
                        <div class="absolute inset-0 bg-white/20 transform -skew-x-12 -translate-x-[150%] group-hover:translate-x-[150%] transition-transform duration-700 ease-in-out"></div>
                        Spiel Starten (1 Energie)
                    </button>

                    {{-- Anleitung --}}
                    <div class="bg-blue-500/5 border border-blue-500/20 p-5 rounded-2xl text-blue-300 text-sm leading-relaxed flex gap-4 items-start shadow-inner">
                        <svg class="w-6 h-6 shrink-0 mt-0.5 text-blue-400 opacity-70" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        <p>Tippe auf einen Kristall und dann auf einen benachbarten, um sie zu tauschen. Je mehr Kristalle du auf einmal kombinierst, desto höher der Punkte-Multiplikator!</p>
                    </div>

                    {{-- Game Over Screen --}}
                    <div x-show="!active && moves <= 0" x-transition class="bg-emerald-500/10 border border-emerald-500/30 p-6 rounded-3xl text-center shadow-inner animate-fade-in-up mt-4">
                        <p class="text-emerald-400 font-bold text-3xl font-serif mb-2">Großartig!</p>
                        <p class="text-gray-300 text-sm mb-6">Du hast <span class="text-emerald-400 font-bold text-lg" x-text="score"></span> Funken in dieser Runde gesammelt. Sie wurden deinem Konto gutgeschrieben.</p>
                        <button @click="attemptStartGame()" class="w-full py-4 bg-emerald-500 text-gray-900 rounded-xl font-black text-[10px] uppercase tracking-widest hover:bg-emerald-400 transition-all shadow-[0_0_20px_rgba(16,185,129,0.4)]">
                            Nochmal (1 Energie)
                        </button>
                    </div>
                </div>

                {{-- Das Spielfeld --}}
                <div class="w-full max-w-[550px] relative shrink-0">

                    {{-- Overlay wenn noch nicht gestartet --}}
                    <div x-show="!active && moves === 20" class="absolute inset-0 z-40 bg-gray-950/60 backdrop-blur-sm rounded-[1.5rem] flex items-center justify-center">
                        <button @click="attemptStartGame()" class="px-8 py-4 bg-emerald-500 text-gray-900 rounded-full font-black text-sm uppercase tracking-widest hover:scale-105 transition-transform shadow-[0_0_30px_rgba(16,185,129,0.5)]">
                            Jetzt Spielen
                        </button>
                    </div>

                    {{-- Lade-Overlay während Animationen --}}
                    <div x-show="isProcessing" class="absolute inset-0 bg-gray-950/10 z-30 rounded-[1.5rem] transition-opacity duration-200 pointer-events-none"></div>

                    {{-- Partikel Container (Liegt über dem Grid) --}}
                    <div class="absolute inset-0 z-40 pointer-events-none overflow-hidden rounded-[1.5rem]">
                        <template x-for="p in particles" :key="p.id">
                            <div class="particle" :style="`left: ${p.x}%; top: ${p.y}%; --tx: ${p.tx}; --ty: ${p.ty}; background-color: ${p.color}; box-shadow: 0 0 10px ${p.color};`"></div>
                        </template>
                    </div>

                    {{-- Das Raster --}}
                    <div class="game-grid">
                        <template x-for="(row, r) in board" :key="r">
                            <template x-for="(cell, c) in row" :key="cell.id">
                                <div class="crystal"
                                     :class="[
                                        'c-type-' + cell.type,
                                        selected && selected.r === r && selected.c === c ? 'selected' : '',
                                        cell.type === 0 ? 'exploded' : ''
                                     ]"
                                     @click="handleClick(r, c)">
                                </div>
                            </template>
                        </template>
                    </div>

                </div>
            </div>

        </div>
    </div>
</div>
