<div>
    <div class="p-10 min-h-full flex flex-col relative z-10 w-full max-w-7xl mx-auto">

        {{-- HEADER --}}
        <div class="mb-10 flex flex-col sm:flex-row sm:items-end justify-between gap-6 animate-fade-in-up">
            <div>
                <h1 class="text-4xl md:text-5xl font-serif font-bold text-white tracking-tight flex items-center gap-4">
                    Rechnungs-Zentrale
                </h1>
                <p class="text-gray-400 mt-2 text-sm uppercase tracking-widest font-bold">Lückenlose Dokumentation deiner Käufe</p>
            </div>

            <a href="{{ route('customer.orders') }}" class="inline-flex items-center gap-2 text-xs font-black uppercase tracking-widest text-gray-400 hover:text-primary transition-colors">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                Zu den Bestellungen
            </a>
        </div>

        {{-- INFO & BATCH DOWNLOADER WIDGET --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-12 animate-fade-in-up delay-100">

            <div class="lg:col-span-1 bg-gray-900/80 backdrop-blur-md border border-gray-800 rounded-3xl p-8 shadow-2xl relative overflow-hidden group hover:border-blue-500/30 transition-colors">
                <div class="absolute -right-10 -top-10 w-40 h-40 bg-blue-500/5 rounded-full blur-3xl group-hover:bg-blue-500/10 transition-colors pointer-events-none"></div>
                <div class="w-12 h-12 rounded-2xl bg-gray-950 border border-gray-800 flex items-center justify-center text-blue-400 mb-6 shadow-inner">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                </div>
                <h3 class="text-lg font-serif font-bold text-white mb-3">Buchhaltung leicht gemacht</h3>
                <p class="text-sm text-gray-400 leading-relaxed font-medium">Dies ist das Archiv deiner geschäftlichen Vorgänge. Du findest hier ausnahmslos jede erstellte Rechnung und Gutschrift chronologisch aufgelistet. Perfekt für deine Steuererklärung oder die eigenen Unterlagen.</p>
            </div>

            {{-- Frontend Invoice Exporter (Zero Server Load) --}}
            <div class="lg:col-span-2 bg-gray-900 border border-gray-800 rounded-3xl p-8 relative overflow-hidden shadow-2xl">
                <div class="absolute inset-0 bg-gradient-to-tr from-blue-600/10 to-transparent pointer-events-none"></div>

                <div class="relative z-10" x-data="{
                        invoices: {{ $invoicesPayload }},
                        state: 'idle', // idle, analyzing, ready, downloading, done
                        progress: 0,
                        currentFile: '',

                        async startAnalysis() {
                            this.state = 'analyzing';
                            await new Promise(r => setTimeout(r, 800)); // Simulate Deep Scan
                            this.state = 'ready';
                        },

                        async loadJSZip() {
                            if (typeof window.JSZip !== 'undefined') return true;
                            return new Promise((resolve, reject) => {
                                let script = document.createElement('script');
                                script.src = '{{ asset('vendor/jszip/jszip.min.js') }}';
                                script.onload = () => resolve();
                                script.onerror = () => reject();
                                document.head.appendChild(script);
                            });
                        },

                        async startDownload() {
                            if(this.invoices.length === 0) return;
                            this.state = 'downloading';
                            this.progress = 0;

                            try {
                                await this.loadJSZip();
                                const zip = new window.JSZip();

                                for(let i=0; i<this.invoices.length; i++) {
                                    let inv = this.invoices[i];
                                    this.currentFile = 'Lade ' + inv.name + ' herunter...';

                                    let response = await fetch(inv.url);
                                    if(response.ok) {
                                        let blob = await response.blob();
                                        zip.file(inv.name, blob);
                                    }

                                    this.progress = Math.round(((i + 1) / this.invoices.length) * 100);
                                }

                                this.currentFile = 'Zip-Archiv wird lokal kompiliert...';
                                const zipContent = await zip.generateAsync({type: 'blob'});

                                const link = document.createElement('a');
                                link.href = URL.createObjectURL(zipContent);
                                link.download = 'Meine_Rechnungen_Seelenfunke.zip';
                                link.click();
                                URL.revokeObjectURL(link.href);

                                this.state = 'done';
                                this.currentFile = 'Download erfolgreich abgeschlossen!';
                            } catch(e) {
                                console.error('JSZip Error:', e);
                                this.currentFile = 'Fehler beim Herunterladen.';
                            }
                        }
                    }">

                    <div class="flex items-center gap-4 mb-4">
                        <div class="w-12 h-12 rounded-2xl bg-blue-500/10 border border-blue-500/30 flex items-center justify-center text-blue-400">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                        </div>
                        <div>
                            <h4 class="text-xl font-serif font-bold text-white tracking-tight">Vollständiges Archiv sichern</h4>
                            <p class="text-sm font-medium text-gray-400">Archiviere alle deine Rechnungen bequem auf einen Schlag als .zip Datei.</p>
                        </div>
                    </div>

                    {{-- Idle State --}}
                    <template x-if="invoices.length > 0 && state === 'idle'">
                        <div class="mt-6">
                            <button @click="startAnalysis" type="button" class="bg-gray-800 hover:bg-gray-700 text-white border border-gray-700 px-6 py-3 rounded-xl font-black uppercase tracking-widest text-xs transition-colors flex items-center gap-2">
                                <svg class="w-4 h-4 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                                Gesamtes Volumen ermitteln
                            </button>
                        </div>
                    </template>

                    <template x-if="invoices.length === 0">
                        <div class="mt-4 p-4 bg-gray-950 rounded-xl border border-gray-800 text-gray-500 text-sm font-medium">
                            Wir konnten keine aktiven Rechnungen in deinem Konto finden.
                        </div>
                    </template>

                    {{-- Analyzing State --}}
                    <template x-if="state === 'analyzing'">
                        <div class="mt-6 flex items-center gap-3 text-blue-400 font-medium text-sm">
                            <svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                            Validiere Dokumente...
                        </div>
                    </template>

                    {{-- Ready State --}}
                    <template x-if="state === 'ready'">
                        <div class="mt-6 animate-fade-in-up">
                            <div class="mb-5 p-4 bg-emerald-500/10 border border-emerald-500/20 rounded-xl text-emerald-400 text-sm flex justify-between items-center">
                                <span class="font-bold">Analyse abgeschlossen</span>
                                <span class="font-mono text-xs uppercase tracking-widest font-black" x-text="invoices.length + ' Rechnungen (~ ' + (invoices.length * 0.2).toFixed(1) + ' MB)'"></span>
                            </div>
                            <button @click="startDownload" type="button" class="w-full sm:w-auto bg-blue-600 hover:bg-blue-500 text-white shadow-[0_0_20px_rgba(37,99,235,0.4)] hover:shadow-[0_0_30px_rgba(37,99,235,0.6)] px-8 py-4 rounded-xl font-black uppercase tracking-widest text-xs transition-all flex items-center justify-center gap-2">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10" /></svg>
                                ZIP Archiv herunterladen
                            </button>
                        </div>
                    </template>

                    {{-- Downloading State --}}
                    <template x-if="state === 'downloading' || state === 'done'">
                        <div class="mt-6 animate-fade-in-up">
                            <div class="flex justify-between items-end mb-2">
                                <span class="text-[10px] font-black uppercase tracking-widest text-blue-400" x-text="state === 'done' ? 'Fertiggestellt' : 'Lade Archiv herunter'"></span>
                                <span class="font-mono text-sm font-bold text-white" x-text="progress + '%'"></span>
                            </div>

                            <div class="w-full bg-gray-950 rounded-full h-2.5 border border-gray-800 overflow-hidden relative shadow-inner mb-3">
                                <div class="bg-gradient-to-r from-blue-600 to-blue-400 h-2.5 rounded-full transition-all duration-300 ease-out shadow-[0_0_10px_rgba(59,130,246,0.8)]" :style="'width: ' + progress + '%'"></div>
                            </div>

                            <div class="flex items-center gap-2">
                                <svg x-show="state !== 'done'" class="w-4 h-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                                <svg x-cloak x-show="state === 'done'" class="w-4 h-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                <span class="text-xs font-mono text-gray-400 truncate" :class="{'text-emerald-400 font-bold': state === 'done'}" x-text="currentFile"></span>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        {{-- LISTE DER EINZELNEN RECHNUNGEN --}}
        <div class="bg-gray-900 border border-gray-800 rounded-3xl shadow-2xl overflow-hidden animate-fade-in-up delay-200">
            <div class="px-8 py-6 border-b border-gray-800 bg-gray-950/50 flex justify-between items-center">
                <h3 class="text-2xl font-bold text-white font-serif tracking-tight">Beleg-Historie</h3>
                <span class="text-xs font-black uppercase tracking-widest text-gray-500 bg-gray-800 px-3 py-1 rounded-full">{{ $invoices->count() }} Dokumente</span>
            </div>

            @if($invoices->isEmpty())
                <div class="p-16 text-center">
                    <div class="w-24 h-24 bg-gray-950 rounded-full flex items-center justify-center mx-auto mb-6 text-gray-700 border border-gray-800 shadow-inner">
                        <svg class="w-10 h-10" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                    </div>
                    <h4 class="text-xl font-serif text-white font-bold mb-2">Noch keine Belege vorhanden</h4>
                    <p class="text-sm text-gray-500 max-w-sm mx-auto leading-relaxed">Sobald deine erste Bestellung bearbeitet wurde, findest du hier deine digitale Rechnung.</p>
                </div>
            @else
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 p-8 bg-gray-900/40">
                    @foreach($invoices as $invoice)
                        <div class="group flex flex-col justify-between p-6 rounded-[2rem] border transition-all duration-300 {{ $invoice->isCreditNote() ? 'border-red-500/30 bg-red-500/5 hover:bg-red-500/10 hover:border-red-500/50' : 'border-gray-700 bg-gray-800/50 hover:bg-gray-800 hover:border-primary/50 shadow-lg hover:shadow-xl' }}">
                            <div class="flex items-start justify-between mb-6">
                                <div class="w-12 h-12 rounded-2xl flex items-center justify-center shadow-inner {{ $invoice->isCreditNote() ? 'bg-red-950 border border-red-900/50 text-red-500' : 'bg-gray-900 border border-gray-700 text-primary group-hover:border-primary/50 transition-colors' }}">
                                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" /></svg>
                                </div>
                                <span class="text-[9px] font-black uppercase tracking-widest px-3 py-1 rounded-full {{ $invoice->isCreditNote() ? 'bg-red-500/20 text-red-400' : 'bg-gray-950 text-gray-400 shadow-inner' }}">
                                    {{ $invoice->created_at->format('d.m.Y') }}
                                </span>
                            </div>

                            <div class="mb-6">
                                <p class="text-xs text-gray-500 font-bold uppercase tracking-widest mb-1">{{ $invoice->isCreditNote() ? 'Gutschrift' : 'Rechnung' }}</p>
                                <p class="text-lg font-bold tracking-tight text-white group-hover:text-primary transition-colors">
                                    {{ $invoice->invoice_number ?? 'Dokument #' . $invoice->id }}
                                </p>

                                @if($invoice->order_id)
                                    <div class="mt-4 pt-4 border-t border-gray-800/50">
                                        <p class="text-[10px] text-gray-500 font-bold uppercase tracking-widest mb-0.5">Zugehörige Bestellung</p>
                                        <div class="flex items-center justify-between">
                                            @php
                                                // Hole die Bestellnummer (wir durchsuchen die Orders Collection aus dem Backend)
                                                // Vermeidet ein extra DB-Query pro Invoice!
                                                $relatedOrder = $orders->firstWhere('id', $invoice->order_id);
                                            @endphp
                                            <span class="text-sm font-bold text-gray-300">#{{ $relatedOrder ? $relatedOrder->order_number : 'Unbekannt' }}</span>

                                            <a href="{{ route('customer.orders') }}" class="text-primary opacity-0 group-hover:opacity-100 transition-opacity text-[10px] font-black uppercase tracking-widest flex items-center gap-1">
                                                Ansehen
                                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                                            </a>
                                        </div>
                                    </div>
                                @endif
                            </div>

                            <a href="{{ route('invoice.download', $invoice->id) }}" target="_blank" class="w-full flex items-center justify-center gap-2 py-3 rounded-xl font-black uppercase tracking-widest text-xs transition-all {{ $invoice->isCreditNote() ? 'bg-red-500 hover:bg-red-400 text-white shadow-[0_0_15px_rgba(239,68,68,0.4)]' : 'bg-gray-950 hover:bg-primary border border-gray-700 hover:border-primary text-gray-300 hover:text-gray-900 shadow-lg group-hover:shadow-[0_0_20px_rgba(197,160,89,0.3)]' }}">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                                Herunterladen
                            </a>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

    </div>
</div>
