<div style="--theme-color: {{ $this->themeColorHex }}; --theme-color-5: {{ $this->themeColorHex }}0D; --theme-color-10: {{ $this->themeColorHex }}1A; --theme-color-15: {{ $this->themeColorHex }}26; --theme-color-20: {{ $this->themeColorHex }}33; --theme-color-30: {{ $this->themeColorHex }}4D; --theme-color-40: {{ $this->themeColorHex }}66; --theme-color-50: {{ $this->themeColorHex }}80; --theme-color-70: {{ $this->themeColorHex }}B3;" style="--theme-color: {{ $this->themeColorHex }}; --theme-color-5: {{ $this->themeColorHex }}0D; --theme-color-10: {{ $this->themeColorHex }}1A; --theme-color-15: {{ $this->themeColorHex }}26; --theme-color-20: {{ $this->themeColorHex }}33; --theme-color-30: {{ $this->themeColorHex }}4D; --theme-color-40: {{ $this->themeColorHex }}66; --theme-color-50: {{ $this->themeColorHex }}80; --theme-color-70: {{ $this->themeColorHex }}B3;" class="space-y-6">
    <!-- Header & Actions -->
    <div class="flex justify-between items-center sm:flex-row flex-col gap-4">
        <div>
            <h2 class="text-2xl font-bold leading-7 text-white sm:truncate sm:text-3xl sm:tracking-tight">
                Nischen-Scout & Crawler
            </h2>
            <p class="mt-1 text-sm text-gray-400">
                Stealth Crawler Engine zur Analyse von Etsy & Amazon Geschenkartikeln.
            </p>
        </div>
        <div class="flex gap-2">
            @if(count($savedRuns) > 0)
                <select wire:model.live="selectedRunId" wire:change="loadHistoricalRun($event.target.value)" class="inline-flex items-center rounded-xl bg-gray-800 px-3 py-2 text-sm font-semibold text-white shadow-sm ring-1 ring-inset ring-gray-700 hover:bg-gray-700 transition-all duration-300 outline-none">
                    <option value="">-- Live Scan Ansicht --</option>
                    @foreach($savedRuns as $run)
                        <option value="{{ $run['id'] }}">{{ $run['name'] }} ({{ \Carbon\Carbon::parse($run['created_at'])->format('d.m. H:i') }})</option>
                    @endforeach
                </select>
            @endif

            <button wire:click="exportTop5Pdf" class="bg-[var(--theme-color)] text-gray-900 px-6 py-3.5 rounded-xl font-black text-[10px] uppercase tracking-widest shadow-[0_0_20px_var(--theme-color-30)] hover:bg-white hover:scale-[1.02] transition-all flex items-center gap-2">
                <svg class="-ml-0.5 mr-1.5 h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                PDF Export
            </button>
            <button wire:click="clearData" class="inline-flex items-center rounded-xl bg-white/5 px-3 py-2 text-sm font-semibold text-white shadow-sm ring-1 ring-inset ring-white/10 hover:bg-white/10 transition-all duration-300">
                Daten leeren
            </button>
        </div>
    </div>

    @if (session()->has('message'))
        <div class="rounded-xl bg-green-500/10 border border-green-500/20 p-4 backdrop-blur-md">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-green-400">{{ session('message') }}</p>
                </div>
            </div>
        </div>
    @endif

    <!-- Live Progress Tracker -->
    @if($hasActiveJobs)
        <div wire:poll.1s class="space-y-4">
            @foreach($activeJobs as $job)
                <div class="bg-gray-900/80 backdrop-blur-xl border border-[var(--theme-color-50)] shadow-[0_0_25px_rgba(197,160,89,0.2)] sm:rounded-2xl p-6 relative overflow-hidden">
                    <div class="absolute inset-0 bg-[var(--theme-color-5)] animate-pulse"></div>
                    <div class="relative z-10">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-lg font-black text-[var(--theme-color)] flex items-center gap-3">
                                <svg class="h-5 w-5 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Scanner: {{ $job['platform'] ?? 'Etsy' }} ("{{ $job['keyword'] ?? '' }}")
                            </h3>
                            <div class="flex items-center gap-3">
                                <span class="text-sm font-black text-white bg-white/10 px-3 py-1 rounded-full">{{ $job['progress'] ?? 0 }}%</span>
                                <button wire:click="cancelCrawler('{{ $job['id'] }}')" class="bg-red-500/10 hover:bg-red-500/20 text-red-500 border border-red-500/20 px-3 py-1 rounded-full text-xs font-bold transition-colors">Abbrechen</button>
                            </div>
                        </div>

                        <p class="text-sm text-gray-300 mb-4 font-mono">{{ $job['status'] ?? '' }}</p>

                        <div class="h-4 w-full bg-gray-900 rounded-full overflow-hidden border border-gray-800 shadow-inner">
                            <div class="h-full bg-[var(--theme-color)] rounded-full transition-all duration-1000 ease-out shadow-[0_0_10px_rgba(197,160,89,0.8)]" style="width: {{ $job['progress'] ?? 0 }}%"></div>
                        </div>
                    </div>
                </div>
            @endforeach

            <div class="mt-5 flex items-start gap-3 text-sm text-gray-400 bg-white/5 rounded-xl p-4 border border-white/10">
                <svg class="h-5 w-5 text-blue-400 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                </svg>
                <p><b class="text-white">Du kannst diese Seite gefahrlos neu laden oder vorübergehend verlassen.</b> Der Stealth-Crawler läuft sicher und asynchron als Background-Job auf dem Server weiter. Nach erfolgreichem Abschluss werden die eingesammelten Daten direkt gespeichert.</p>
            </div>
        </div>
    @endif

    <!-- Crawler Control Panel -->
    <div class="bg-gray-900/80 backdrop-blur-xl border border-gray-800 shadow-xl sm:rounded-2xl">
        <div class="px-4 py-5 sm:p-6">
            <h3 class="text-base font-semibold leading-6 text-white">Neuen Scan starten</h3>
            <div class="mt-2 text-sm text-gray-400">
                <p>Der Stealth-Crawler arbeitet im Hintergrund über die Queue. Er nutzt wechselnde User-Agents und zufällige Delays, um Sperren zu verhindern. Ein Suchlauf kann mehrere Minuten dauern.</p>
            </div>
            <form wire:submit.prevent="dispatchCrawler" class="mt-5 sm:flex sm:items-center gap-3">
                <div class="w-full sm:w-auto flex flex-col sm:flex-row gap-3">
                    <div class="flex items-center gap-4 bg-white/5 px-4 py-2 rounded-xl ring-1 ring-inset ring-white/10">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" wire:model="crawlPlatforms" value="Etsy" class="rounded border-gray-700 bg-gray-900 text-[var(--theme-color)] focus:ring-[var(--theme-color)] focus:ring-offset-gray-900">
                            <span class="text-sm text-gray-300">Etsy</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" wire:model="crawlPlatforms" value="Amazon" class="rounded border-gray-700 bg-gray-900 text-[var(--theme-color)] focus:ring-[var(--theme-color)] focus:ring-offset-gray-900">
                            <span class="text-sm text-gray-300">Amazon</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" wire:model="crawlPlatforms" value="Alibaba" class="rounded border-gray-700 bg-gray-900 text-[var(--theme-color)] focus:ring-[var(--theme-color)] focus:ring-offset-gray-900">
                            <span class="text-sm text-gray-300">Alibaba</span>
                        </label>
                    </div>

                    <div class="w-full sm:max-w-xs">
                        <label for="keyword" class="sr-only">Suchbegriff</label>
                        <input type="text" wire:model="crawlKeyword" id="keyword" class="block w-full rounded-xl border-0 py-2 bg-white/5 text-white shadow-sm ring-1 ring-inset ring-white/10 placeholder:text-gray-500 focus:ring-2 focus:ring-inset focus:ring-[var(--theme-color)] sm:text-sm sm:leading-6" placeholder="z.B. personalisiertes geschenk">
                    </div>
                </div>

                <div class="flex flex-wrap gap-3 mt-3 sm:mt-0">
                    <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-[var(--theme-color)] px-4 py-2 text-sm font-bold text-gray-900 shadow-xl shadow-[var(--theme-color-20)] hover:bg-[var(--theme-color)]/90 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary transition-all duration-300">
                        Crawler Starten
                    </button>
                    @if(!$isHistorical && $products->total() > 0)
                    <button type="button" wire:click="saveCurrentRun" class="mt-3 inline-flex w-full items-center justify-center rounded-xl bg-blue-500/10 px-3 py-2 text-sm font-bold text-blue-400 shadow-xl shadow-blue-500/20 hover:bg-blue-500/20 ring-1 ring-blue-500/30 transition-all duration-300 sm:mt-0 sm:ml-3 sm:w-auto">
                        Als Snapshot speichern
                    </button>
                @endif
                @if($isHistorical)
                    <button type="button" wire:click="deleteRun({{ $selectedRunId }})" wire:confirm="Snapshot wirklich unwiderruflich löschen?" class="mt-3 inline-flex w-full items-center justify-center rounded-xl bg-red-500/10 px-3 py-2 text-sm font-bold text-red-500 hover:bg-red-500/20 ring-1 ring-red-500/30 transition-all duration-300 sm:mt-0 sm:ml-3 sm:w-auto">
                        Snapshot löschen
                    </button>
                @endif
            </form>
        </div>
    </div>

    <!-- Filters & Trends -->
    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
        <!-- Filter Card -->
        <div class="bg-gray-900/80 backdrop-blur-xl border border-gray-800 shadow-xl sm:rounded-2xl p-6">
            <h3 class="text-base font-semibold leading-6 text-white mb-4">Ergebnisse filtern</h3>
            <div class="space-y-4">
                <div>
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Produkt suchen..." class="block w-full rounded-xl border-0 py-1.5 bg-white/5 text-white shadow-sm ring-1 ring-inset ring-white/10 placeholder:text-gray-500 focus:ring-2 focus:ring-inset focus:ring-[var(--theme-color)] sm:text-sm sm:leading-6">
                </div>
                <div>
                    <select wire:model.live="filterPlatform" class="block w-full rounded-xl border-0 py-1.5 bg-white/5 text-white shadow-sm ring-1 ring-inset ring-white/10 focus:ring-2 focus:ring-inset focus:ring-[var(--theme-color)] sm:text-sm sm:leading-6 [&>option]:text-gray-900">
                        <option value="">Alle Plattformen</option>
                        <option value="Etsy">Etsy</option>
                        <option value="Amazon">Amazon</option>
                        <option value="Alibaba">Alibaba</option>
                    </select>
                </div>
                <div>
                    <select wire:model.live="filterMinScore" class="block w-full rounded-xl border-0 py-1.5 bg-white/5 text-white shadow-sm ring-1 ring-inset ring-white/10 focus:ring-2 focus:ring-inset focus:ring-[var(--theme-color)] sm:text-sm sm:leading-6 [&>option]:text-gray-900">
                        <option value="0">Alle Scores</option>
                        <option value="50">Ab 50+ (Solide)</option>
                        <option value="75">Ab 75+ (Spitzen-Nische)</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="bg-gray-900/40 backdrop-blur-xl border border-dashed border-gray-800 shadow-xl sm:rounded-2xl p-6 lg:col-span-2 flex items-center justify-center relative overflow-hidden">
                <div class="absolute inset-0 bg-[var(--theme-color-5)] blur-3xl opacity-50 rounded-full mix-blend-screen pointer-events-none"></div>
                <div class="text-center relative z-10">
                    <p class="text-sm text-gray-400 font-medium tracking-wide uppercase">@if($isHistorical) Gespeicherte Scan-Produkte @else Gescannte Nischen-Produkte @endif</p>
                    <p class="text-5xl font-black text-white mt-2 drop-shadow-[0_0_15px_rgba(255,255,255,0.3)]">{{ $isHistorical ? count($products) : $products->total() }}</p>
                </div>
        </div>
    </div>

    <!-- Top 3 Podium & AI Advisor -->
    @if(count($top3Products) >= 3)
    <div class="bg-gray-900/80 backdrop-blur-xl border border-gray-800 shadow-2xl sm:rounded-2xl overflow-hidden mt-6">
        <div class="px-4 py-5 sm:p-6 text-center">
            <h3 class="text-2xl font-black text-transparent bg-clip-text bg-gradient-to-r from-[var(--theme-color)] to-yellow-600 mb-8 uppercase tracking-widest">
                Top 3 Nischen Produkte
            </h3>

            <div class="flex flex-col md:flex-row justify-center items-center md:items-end gap-6 mb-10 w-full max-w-5xl mx-auto">
                {{-- Platz 2 (Silber) --}}
                <div class="order-2 md:order-1 flex-1 flex flex-col items-center w-full max-w-xs md:max-w-none">
                    <div class="w-24 h-24 rounded-full border-4 border-gray-400 overflow-hidden mb-4 shadow-[0_0_15px_rgba(156,163,175,0.5)] bg-gray-800">
                        @if($top3Products[1]->image_url)<img src="{{ $top3Products[1]->image_url }}" class="w-full h-full object-cover">@endif
                    </div>
                    <div class="bg-gradient-to-t from-gray-700 to-gray-400 w-full rounded-t-lg shadow-xl p-4 flex flex-col justify-end" style="height: 160px;">
                        <span class="text-4xl font-black text-white/50 mb-2">2</span>
                        <p class="text-white font-bold text-sm truncate w-full px-2" title="{{ $top3Products[1]->title }}">{{ \Illuminate\Support\Str::limit($top3Products[1]->title, 20) }}</p>
                        <p class="text-gray-100 font-medium text-xs">{{ $top3Products[1]->niche_score }} Score</p>
                    </div>
                </div>

                {{-- Platz 1 (Gold) --}}
                <div class="order-1 md:order-2 w-full max-w-xs md:w-1/3 flex flex-col items-center z-10 transform md:-translate-y-4">
                    <div class="relative">
                        <div class="absolute -inset-2 bg-gradient-to-r from-yellow-400 to-[var(--theme-color)] rounded-full blur opacity-70 animate-pulse"></div>
                        <div class="w-32 h-32 rounded-full border-4 border-yellow-400 overflow-hidden mb-4 relative z-10 bg-gray-800">
                            @if($top3Products[0]->image_url)<img src="{{ $top3Products[0]->image_url }}" class="w-full h-full object-cover">@endif
                        </div>
                    </div>
                    <div class="bg-gradient-to-t from-yellow-700 to-yellow-500 w-full rounded-t-lg shadow-2xl p-4 flex flex-col justify-end ring-1 ring-yellow-400/50" style="height: 200px;">
                        <span class="text-6xl font-black text-white/50 mb-2 drop-shadow-md">1</span>
                        <p class="text-white font-bold text-base truncate w-full px-2 drop-shadow-md" title="{{ $top3Products[0]->title }}">{{ \Illuminate\Support\Str::limit($top3Products[0]->title, 25) }}</p>
                        <p class="text-yellow-100 font-bold text-sm drop-shadow-md">{{ $top3Products[0]->niche_score }} Score</p>
                    </div>
                </div>

                {{-- Platz 3 (Bronze) --}}
                <div class="order-3 md:order-3 flex-1 flex flex-col items-center w-full max-w-xs md:max-w-none">
                    <div class="w-24 h-24 rounded-full border-4 border-orange-700 overflow-hidden mb-4 shadow-[0_0_15px_rgba(194,65,12,0.3)] bg-gray-800">
                        @if($top3Products[2]->image_url)<img src="{{ $top3Products[2]->image_url }}" class="w-full h-full object-cover">@endif
                    </div>
                    <div class="bg-gradient-to-t from-orange-900 to-orange-700 w-full rounded-t-lg shadow-xl p-4 flex flex-col justify-end" style="height: 140px;">
                        <span class="text-4xl font-black text-white/30 mb-2">3</span>
                        <p class="text-white font-bold text-sm truncate w-full px-2" title="{{ $top3Products[2]->title }}">{{ \Illuminate\Support\Str::limit($top3Products[2]->title, 20) }}</p>
                        <p class="text-orange-200 font-medium text-xs">{{ $top3Products[2]->niche_score }} Score</p>
                    </div>
                </div>
            </div>

        </div>
    </div>
    @endif

    <!-- Top 6 Charts -->
    @if($products->total() > 0 || ($isHistorical && $historicalRunData->count() > 0))
    @php
        $top6 = $isHistorical ? $historicalRunData->sortByDesc('niche_score')->take(6)->values() : $products->take(6);
        $maxPrice = $top6->max('price') ?: 1;
        $maxSales = $top6->max('sales_volume') ?: 1;
        $maxScore = 100;
    @endphp
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8 mt-8">
        <!-- Chart: Nischen Score -->
        <div class="bg-gray-900/80 backdrop-blur-xl border border-gray-800 shadow-xl sm:rounded-2xl p-6 relative overflow-hidden">
            <div class="absolute inset-0 bg-blue-500/5 blur-3xl rounded-full pointer-events-none"></div>
            <h4 class="text-white font-bold mb-4 flex items-center gap-2 relative z-10">
                <svg class="w-5 h-5 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
                Nischen Score (Top 6)
            </h4>
            <div class="space-y-3 relative z-10">
                @foreach($top6 as $idx => $p)
                    @php $percent = min(100, ($p->niche_score / $maxScore) * 100); @endphp
                    <div class="flex items-center gap-3 group">
                        <div class="text-xs text-gray-500 group-hover:text-blue-400 w-4 text-right font-mono transition-colors">#{{ $idx+1 }}</div>
                        <div class="flex-1">
                            <div class="flex justify-between text-xs mb-1">
                                <span class="text-gray-300 truncate w-32 sm:w-48 group-hover:text-white transition-colors" title="{{ $p->title }}">{{ Str::limit($p->title, 25) }}</span>
                                <span class="font-bold text-blue-400">{{ number_format($p->niche_score, 0) }}</span>
                            </div>
                            <div class="w-full bg-gray-800 rounded-full h-2 overflow-hidden flex items-center">
                                <div class="bg-gradient-to-r from-blue-600 to-blue-400 h-2 rounded-full transform origin-left transition-transform duration-1000" style="width: {{ $percent }}%"></div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Chart: Sales Volume -->
        <div class="bg-gray-900/80 backdrop-blur-xl border border-gray-800 shadow-xl sm:rounded-2xl p-6 relative overflow-hidden">
            <div class="absolute inset-0 bg-emerald-500/5 blur-3xl rounded-full pointer-events-none"></div>
            <h4 class="text-white font-bold mb-4 flex items-center gap-2 relative z-10">
                <svg class="w-5 h-5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                Sales Wert (Ø Monat)
            </h4>
            <div class="space-y-3 relative z-10">
                @foreach($top6 as $idx => $p)
                    @php $percent = min(100, ($p->sales_volume / $maxSales) * 100); @endphp
                    <div class="flex items-center gap-3 group">
                        <div class="text-xs text-gray-500 group-hover:text-emerald-400 w-4 text-right font-mono transition-colors">#{{ $idx+1 }}</div>
                        <div class="flex-1">
                            <div class="flex justify-between text-xs mb-1">
                                <span class="text-gray-300 truncate w-32 sm:w-48 group-hover:text-white transition-colors" title="{{ $p->title }}">{{ Str::limit($p->title, 25) }}</span>
                                <span class="font-bold text-emerald-400">{{ number_format($p->sales_volume, 0, ',', '.') }}</span>
                            </div>
                            <div class="w-full bg-gray-800 rounded-full h-2 overflow-hidden flex items-center">
                                <div class="bg-gradient-to-r from-emerald-600 to-emerald-400 h-2 rounded-full transform origin-left transition-transform duration-1000" style="width: {{ $percent }}%"></div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Chart: Price -->
        <div class="bg-gray-900/80 backdrop-blur-xl border border-gray-800 shadow-xl sm:rounded-2xl p-6 relative overflow-hidden">
            <div class="absolute inset-0 bg-amber-500/5 blur-3xl rounded-full pointer-events-none"></div>
            <h4 class="text-white font-bold mb-4 flex items-center gap-2 relative z-10">
                <svg class="w-5 h-5 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg>
                Preis (Ø Angebot)
            </h4>
            <div class="space-y-3 relative z-10">
                @foreach($top6 as $idx => $p)
                    @php $percent = min(100, ($p->price / $maxPrice) * 100); @endphp
                    <div class="flex items-center gap-3 group">
                        <div class="text-xs text-gray-500 group-hover:text-amber-400 w-4 text-right font-mono transition-colors">#{{ $idx+1 }}</div>
                        <div class="flex-1">
                            <div class="flex justify-between text-xs mb-1">
                                <span class="text-gray-300 truncate w-32 sm:w-48 group-hover:text-white transition-colors" title="{{ $p->title }}">{{ Str::limit($p->title, 25) }}</span>
                                <span class="font-bold text-amber-400">{{ number_format($p->price, 2, ',', '.') }} €</span>
                            </div>
                            <div class="w-full bg-gray-800 rounded-full h-2 overflow-hidden flex items-center">
                                <div class="bg-gradient-to-r from-amber-600 to-amber-400 h-2 rounded-full transform origin-left transition-transform duration-1000" style="width: {{ $percent }}%"></div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    <!-- Data Table -->
    <div class="bg-gray-900/80 backdrop-blur-xl border border-gray-800 shadow-2xl sm:rounded-2xl">
        <div class="px-4 py-5 sm:p-6">
            <h3 class="text-base font-semibold leading-6 text-white mb-4">Top 40 Nischen Ranking</h3>

            <div class="overflow-x-auto ring-1 ring-white/5 sm:mx-0 sm:rounded-xl">
                <table class="min-w-full divide-y divide-gray-800">
                    <thead class="bg-white/5">
                        <tr>
                            <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-white sm:pl-6">Produkt</th>
                            <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-white">Plattform</th>
                            <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-white">Preis</th>
                            <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-white">Sales (Schätzung)</th>
                            <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-white">Niche Score</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800 bg-transparent">
                        @forelse($products as $product)
                        <tr>
                            <td class="bg-whitespace-nowrap py-4 pl-4 pr-3 text-sm sm:pl-6">
                                <div class="flex items-center">
                                    <div class="h-10 w-10 flex-shrink-0">
                                        @if($product->image_url)
                                            <img class="h-10 w-10 rounded-md object-cover" src="{{ $product->image_url }}" alt="">
                                        @else
                                            <div class="h-10 w-10 rounded-lg bg-gray-800 flex items-center justify-center text-gray-500 border border-gray-700">
                                              <!-- SVG Icon -->
                                              <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24"><path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/></svg>
                                            </div>
                                        @endif
                                    </div>
                                    <div class="ml-4">
                                        <div class="font-medium text-white truncate max-w-[250px]" title="{{ $product->title }}">{{ $product->title }}</div>
                                        <a href="{{ $product->url }}" target="_blank" class="text-[var(--theme-color)] hover:text-[var(--theme-color)]/80 hover:underline text-xs transition-colors">Auf {{ $product->platform }} ansehen &rarr;</a>
                                    </div>
                                </div>
                            </td>
                            <td class="bg-whitespace-nowrap px-3 py-4 text-sm text-gray-400">
                                <span class="inline-flex items-center rounded-md bg-orange-400/10 px-2 py-1 text-xs font-medium text-orange-400 ring-1 ring-inset ring-orange-400/20">{{ $product->platform }}</span>
                            </td>
                            <td class="bg-whitespace-nowrap px-3 py-4 text-sm text-white font-medium">
                                {{ $product->price ? number_format($product->price, 2, ',', '.') . ' €' : 'N/A' }}
                            </td>
                            <td class="bg-whitespace-nowrap px-3 py-4 text-sm text-gray-400">
                                <div class="flex flex-col">
                                  <span class="font-bold text-white">{{ number_format($product->sales_volume, 0, ',', '.') }}</span>
                                  <span class="text-xs text-gray-500">{{ number_format($product->review_count, 0, ',', '.') }} Reviews</span>
                                </div>
                            </td>
                            <td class="bg-whitespace-nowrap px-3 py-4 text-sm">
                                <div class="flex items-center gap-3">
                                    <div class="h-2.5 w-full bg-gray-800 rounded-full max-w-[100px] overflow-hidden">
                                        <div class="h-full rounded-full {{ $product->niche_score >= 75 ? 'bg-green-500 shadow-[0_0_10px_rgba(34,197,94,0.5)]' : ($product->niche_score >= 50 ? 'bg-yellow-500 shadow-[0_0_10px_rgba(234,179,8,0.5)]' : 'bg-red-500 shadow-[0_0_10px_rgba(239,68,68,0.5)]') }}" style="width: {{ $product->niche_score }}%"></div>
                                    </div>
                                    <span class="font-bold {{ $product->niche_score >= 75 ? 'text-green-400' : ($product->niche_score >= 50 ? 'text-yellow-400' : 'text-red-400') }}">
                                        {{ $product->niche_score }}
                                    </span>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="px-3 py-12 text-center text-sm text-gray-500">
                                Noch keine Nischen-Produkte eingescannt. Starte den Crawler oben, lehne dich zurück und lade die Seite nach ein paar Minuten neu.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                @if(!$isHistorical && method_exists($products, 'links'))
                    {{ $products->links() }}
                @endif
            </div>
        </div>
    </div>
</div>
