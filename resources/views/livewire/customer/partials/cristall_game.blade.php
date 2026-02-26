{{-- ========================================== --}}
{{-- STYLES FÜR KRISTALL-KOLLAPS --}}
{{-- ========================================== --}}
<style>
    .game-grid {
        display: grid;
        grid-template-columns: repeat(8, 1fr);
        gap: 6px;
        padding: 10px;
        background: #030712; /* ganz dunkles grau/schwarz */
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
        transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.2s;
        position: relative;
    }

    .crystal:hover { transform: scale(1.08); z-index: 10; }

    .crystal.selected {
        transform: scale(1.15);
        z-index: 20;
        box-shadow: 0 0 20px rgba(255,255,255,0.6);
        outline: 2px solid #fff;
        outline-offset: 3px;
    }

    .crystal.exploded {
        opacity: 0;
        transform: scale(0) rotate(45deg);
        transition: all 0.4s ease-out;
    }

    /* Die 6 Kristall-Typen - Leuchtende Fragmente */
    .c-type-1 { background: radial-gradient(circle at 30% 30%, #ef4444, #991b1b); box-shadow: inset 0 0 15px #fca5a5, 0 4px 6px rgba(0,0,0,0.5); } /* Rubin */
    .c-type-2 { background: radial-gradient(circle at 30% 30%, #3b82f6, #1e40af); box-shadow: inset 0 0 15px #93c5fd, 0 4px 6px rgba(0,0,0,0.5); } /* Saphir */
    .c-type-3 { background: radial-gradient(circle at 30% 30%, #10b981, #065f46); box-shadow: inset 0 0 15px #6ee7b7, 0 4px 6px rgba(0,0,0,0.5); } /* Smaragd */
    .c-type-4 { background: radial-gradient(circle at 30% 30%, #eab308, #854d0e); box-shadow: inset 0 0 15px #fde047, 0 4px 6px rgba(0,0,0,0.5); } /* Topas */
    .c-type-5 { background: radial-gradient(circle at 30% 30%, #a855f7, #6b21a8); box-shadow: inset 0 0 15px #d8b4fe, 0 4px 6px rgba(0,0,0,0.5); } /* Amethyst */
    .c-type-6 { background: radial-gradient(circle at 30% 30%, #06b6d4, #164e63); box-shadow: inset 0 0 15px #67e8f9, 0 4px 6px rgba(0,0,0,0.5); } /* Diamant */
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
            moves: 20,
            isProcessing: false,
            selected: null,

            init() {
                // Sobald das Spiel geöffnet wird, startet es neu
                this.$watch('activeGame', value => {
                    if(value === 'kristall') this.start();
                });
            },
            start() {
                this.active = true;
                this.score = 0;
                this.moves = 20;
                this.isProcessing = true;
                this.selected = null;

                // Generiere ein Feld, das am Anfang keine fertigen Matches hat
                do {
                    this.fillBoard();
                } while (this.findMatches().length > 0);

                this.isProcessing = false;
            },
            fillBoard() {
                this.board = Array.from({length: this.rows}, (_, r) =>
                    Array.from({length: this.cols}, (_, c) => ({
                        type: Math.floor(Math.random() * this.types) + 1,
                        id: `${r}-${c}-${Date.now()}`
                    }))
                );
            },
            async handleClick(r, c) {
                if (this.isProcessing || !this.active) return;

                if (!this.selected) {
                    this.selected = {r, c};
                } else {
                    // Klick auf den gleichen -> abwählen
                    if (this.selected.r === r && this.selected.c === c) {
                        this.selected = null;
                        return;
                    }

                    // Prüfen ob die Steine nebeneinander liegen
                    const isAdj = (Math.abs(this.selected.r - r) + Math.abs(this.selected.c - c)) === 1;

                    if (isAdj) {
                        let p1 = this.selected;
                        this.selected = null;
                        await this.trySwap(p1, {r, c});
                    } else {
                        // Wenn zu weit weg, wähle einfach den neuen aus
                        this.selected = {r, c};
                    }
                }
            },
            async trySwap(p1, p2) {
                this.isProcessing = true;

                // Steine physisch tauschen
                await this.swap(p1, p2);

                // Prüfen ob ein Match entstanden ist
                if (this.findMatches().length > 0) {
                    this.moves--;
                    await this.processMatches();
                } else {
                    // Kein Match -> Zurücktauschen
                    await this.swap(p1, p2);
                }

                this.isProcessing = false;
                if(this.moves <= 0) this.active = false;
            },
            async swap(p1, p2) {
                let tmp = this.board[p1.r][p1.c];
                this.board[p1.r][p1.c] = this.board[p2.r][p2.c];
                this.board[p2.r][p2.c] = tmp;

                // Warten, damit das Auge es wahrnimmt
                await new Promise(r => setTimeout(r, 200));
            },
            findMatches() {
                let m = new Set();

                // Horizontale Matches finden
                for (let r=0; r<this.rows; r++) {
                    for (let c=0; c<this.cols-2; c++) {
                        let t = this.board[r][c].type;
                        if(t !== 0 && t === this.board[r][c+1].type && t === this.board[r][c+2].type) {
                            [0,1,2].forEach(i => m.add(`${r},${c+i}`));
                        }
                    }
                }

                // Vertikale Matches finden
                for (let c=0; c<this.cols; c++) {
                    for (let r=0; r<this.rows-2; r++) {
                        let t = this.board[r][c].type;
                        if(t !== 0 && t === this.board[r+1][c].type && t === this.board[r+2][c].type) {
                            [0,1,2].forEach(i => m.add(`${r+i},${c}`));
                        }
                    }
                }

                // Set in Array konvertieren
                return Array.from(m).map(s => { let [r,c] = s.split(','); return {r:+r, c:+c}; });
            },
            async processMatches() {
                let matches;

                // Kettenreaktionen auflösen, solange es Matches gibt
                while ((matches = this.findMatches()).length > 0) {

                    // 1. Markiere betroffene als Typ 0 (explodiert)
                    matches.forEach(p => {
                        this.board[p.r][p.c].type = 0;
                        this.score += 10;
                    });

                    await new Promise(r => setTimeout(r, 400)); // Animations-Zeit geben

                    // 2. Gravitation anwenden (Nachrutschen)
                    this.applyGravity();

                    await new Promise(r => setTimeout(r, 300)); // Warten bis sie gefallen sind
                }
            },
            applyGravity() {
                for (let c=0; c<this.cols; c++) {
                    let col = [];

                    // Alle NICHT zerstörten Steine der Spalte sammeln
                    for(let r=0; r<this.rows; r++) {
                        if(this.board[r][c].type !== 0) col.push(this.board[r][c]);
                    }

                    let missing = this.rows - col.length;

                    // Neue Steine oben auffüllen
                    for(let i=0; i<missing; i++) {
                        col.unshift({
                            type: Math.floor(Math.random() * this.types) + 1,
                            id: `new-${c}-${Math.random()}-${Date.now()}`
                        });
                    }

                    // Spalte zurück ins Board schreiben
                    for(let r=0; r<this.rows; r++) {
                        this.board[r][c] = col[r];
                    }
                }
            }
        }));
    });
</script>

{{-- ========================================== --}}
{{-- MODAL: DIE SPIELE-ARCADE                   --}}
{{-- ========================================== --}}
{{-- FIX 1: items-start und pt-10 setzt das Modal ganz nach oben --}}
<div x-show="showGameModal" style="display: none;" class="fixed inset-0 z-[5000] flex items-start justify-center pt-4 sm:pt-10 p-4 sm:p-6 overflow-hidden">

    {{-- Backdrop --}}
    <div x-show="showGameModal" x-transition.opacity.duration.500ms class="absolute inset-0 bg-gray-950/90 backdrop-blur-xl" @click="showGameModal = false; activeGame = null"></div>

    {{-- MODAL CONTAINER --}}
    {{-- FIX 2: h-[90vh] zwingt das Modal physisch eine Größe einzunehmen, sodass absolute Kinder es nicht kollabieren lassen --}}
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
                {{-- Zurück-Button, nur wenn ein Spiel aktiv ist --}}
                <button x-show="activeGame" @click="activeGame = null" class="px-4 py-2 bg-gray-800 text-gray-300 rounded-xl font-black text-[10px] uppercase tracking-widest hover:bg-gray-700 transition-all flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg> Zurück
                </button>

                <div class="flex items-center gap-3 bg-gray-900 border border-gray-800 px-5 py-2.5 rounded-2xl shadow-inner">
                    <svg class="w-5 h-5 text-blue-500 animate-pulse" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                    <div class="flex flex-col">
                        <span class="text-[9px] text-gray-500 font-black uppercase tracking-widest leading-none">Seelen-Energie</span>
                        <span class="text-white font-bold leading-none mt-1">5 / 5</span>
                    </div>
                </div>

                <button @click="showGameModal = false; activeGame = null" class="p-3 rounded-full bg-gray-800 text-gray-400 hover:text-white hover:bg-red-500 transition-colors">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>
        </div>

        {{-- MODAL CONTENT (Isoliert den Flex-Bereich, der den Viewport ausfüllt) --}}
        <div class="relative flex-1 w-full bg-gray-950 overflow-hidden">

            {{-- ANSICHT 1: AUSWAHLMENÜ --}}
            <div x-show="!activeGame" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 -translate-x-10" x-transition:enter-end="opacity-100 translate-x-0" class="absolute inset-0 overflow-y-auto no-scrollbar p-6 sm:p-10">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 sm:gap-8 max-w-6xl mx-auto">

                    {{-- SPIEL 1: KRISTALL-KOLLAPS --}}
                    <div @click="activeGame = 'kristall'" class="group relative bg-gray-900 rounded-3xl border border-gray-800 p-8 hover:border-emerald-500/50 transition-all duration-500 hover:-translate-y-2 hover:shadow-[0_20px_40px_rgba(16,185,129,0.15)] flex flex-col cursor-pointer">
                        <div class="absolute inset-0 bg-gradient-to-br from-emerald-500/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity rounded-3xl pointer-events-none"></div>
                        <div class="w-20 h-20 rounded-2xl bg-gray-950 border border-gray-800 flex items-center justify-center mb-8 group-hover:scale-110 transition-transform shadow-inner"><span class="text-4xl">💎</span></div>
                        <h4 class="text-3xl font-serif font-bold text-white mb-3">Kristall-Kollaps</h4>
                        <p class="text-gray-400 text-sm leading-relaxed mb-8 flex-1">Kombiniere leuchtende Fragmente in Kettenreaktionen. Ein rasantes Match-3 Erlebnis für Taktiker.</p>
                        <button class="w-full py-4 bg-gray-950 text-emerald-500 border border-emerald-500/30 rounded-xl font-black text-[10px] uppercase tracking-widest group-hover:bg-emerald-500 group-hover:text-white transition-all shadow-inner">Spielen (1 Energie)</button>
                    </div>

                    {{-- SPIEL 2: SEELEN-SCHMIEDE (Gesperrt) --}}
                    <div class="group relative bg-gray-900 rounded-3xl border border-gray-800 p-8 opacity-50 grayscale flex flex-col cursor-not-allowed">
                        <div class="w-20 h-20 rounded-2xl bg-gray-950 border border-gray-800 flex items-center justify-center mb-8 shadow-inner"><span class="text-4xl">🏺</span></div>
                        <h4 class="text-3xl font-serif font-bold text-white mb-3">Seelen-Schmiede</h4>
                        <p class="text-gray-400 text-sm leading-relaxed mb-8 flex-1">Verschmelze Elemente zu epischen Artefakten, bevor das Gefäß überläuft.</p>
                        <div class="w-full py-4 bg-gray-950 text-gray-500 border border-gray-800 rounded-xl font-black text-[10px] uppercase tracking-widest text-center shadow-inner">Bald verfügbar</div>
                    </div>

                    {{-- SPIEL 3: STERNEN-DRIFT (Gesperrt) --}}
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
                    <div class="bg-gray-900 border border-gray-800 rounded-3xl p-8 flex flex-col items-center gap-6 shadow-[0_20px_40px_rgba(0,0,0,0.5)]">
                        <div class="text-center w-full">
                            <p class="text-gray-500 text-[10px] font-black uppercase tracking-widest mb-2">Gesammelte Punkte</p>
                            <h4 class="text-6xl font-serif font-bold text-emerald-400 drop-shadow-[0_0_15px_rgba(16,185,129,0.4)]" x-text="score"></h4>
                        </div>
                        <div class="w-full h-px bg-gray-800"></div>
                        <div class="text-center w-full">
                            <p class="text-gray-500 text-[10px] font-black uppercase tracking-widest mb-2">Züge übrig</p>
                            <h4 class="text-5xl font-serif font-bold text-white transition-colors" :class="{'text-red-500 drop-shadow-[0_0_10px_rgba(239,68,68,0.5)]': moves <= 5}" x-text="moves"></h4>
                        </div>
                    </div>

                    {{-- Anleitung --}}
                    <div class="bg-blue-500/10 border border-blue-500/30 p-5 rounded-2xl text-blue-300 text-sm leading-relaxed flex gap-4 items-start shadow-inner">
                        <svg class="w-6 h-6 shrink-0 mt-0.5 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        <p>Tippe auf einen Kristall und dann auf einen benachbarten, um sie zu tauschen. Bilde Reihen aus 3 oder mehr gleichen Kristallen, um Funken zu erzeugen.</p>
                    </div>

                    {{-- Game Over Screen (Erscheint in der Sidebar) --}}
                    <div x-show="!active && moves <= 0" class="bg-emerald-500/10 border border-emerald-500/30 p-6 rounded-3xl text-center shadow-inner animate-fade-in-up">
                        <p class="text-emerald-400 font-bold text-2xl mb-2">Großartig!</p>
                        <p class="text-gray-300 text-sm mb-6">Du hast <span class="text-emerald-400 font-bold" x-text="score"></span> Funken in dieser Runde gesammelt.</p>
                        <button @click="start()" class="w-full py-3.5 bg-emerald-500 text-gray-900 rounded-xl font-black text-[10px] uppercase tracking-widest hover:bg-emerald-400 transition-all shadow-[0_0_20px_rgba(16,185,129,0.4)]">
                            Nochmal spielen (1 Energie)
                        </button>
                    </div>
                </div>

                {{-- Das Spielfeld --}}
                <div class="w-full max-w-[550px] relative shrink-0">

                    {{-- Lade-Overlay während das Spiel Steine generiert oder tauscht --}}
                    <div x-show="isProcessing" class="absolute inset-0 bg-gray-950/20 z-30 rounded-[1.5rem] flex items-center justify-center transition-opacity duration-200 pointer-events-none"></div>

                    {{-- Das Raster (Game Grid) --}}
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
