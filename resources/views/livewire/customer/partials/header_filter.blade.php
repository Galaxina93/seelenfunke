<div id="shop-header-container" class="relative h-[85vh] flex items-center justify-center overflow-hidden border-b border-gray-800">
    <canvas id="funki-sparks-bg" class="absolute inset-0 z-0 opacity-50 w-full h-full pointer-events-none" wire:ignore></canvas>
    <div class="absolute inset-0 bg-gradient-to-b from-transparent via-gray-950/40 to-gray-950 z-1 pointer-events-none"></div>

    <div class="relative z-10 text-center flex flex-col items-center max-w-5xl px-6">

        <div class="relative mb-16">
            <button @click.stop="showTitlesModal=true" class="absolute -top-6 left-1/2 -translate-x-1/2 bg-gray-900 border border-primary px-6 py-2.5 rounded-full font-black uppercase tracking-widest text-[10px] text-white shadow-[0_0_25px_rgba(197,160,89,0.6)] flex items-center gap-3 hover:bg-primary hover:text-gray-900 transition-all z-20 hover:scale-110 group cursor-pointer">
                {{ $currentRankName }}
                <x-heroicon-s-cog-6-tooth class="w-4 h-4 group-hover:rotate-90 transition-transform duration-500" />
            </button>
            <button @click="open3DModal()" class="group relative transform hover:scale-105 transition-all duration-700">
                <div class="w-64 h-64 md:w-80 md:h-80 lg:w-[24rem] lg:h-[24rem] rounded-full bg-gradient-to-br from-gray-800 to-black p-3 shadow-[0_0_80px_rgba(0,0,0,0.8)] border-2 border-gray-700 group-hover:border-primary transition-all duration-700 overflow-hidden relative">
                    <img :src="currentImagePath" src="{{ $imagePath }}" class="w-full h-full object-contain p-4 rounded-full group-hover:scale-110 transition-transform duration-700 animate-[float_4s_ease-in-out_infinite]">
                    <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                        <span class="bg-primary text-gray-900 px-8 py-4 rounded-2xl font-black text-sm uppercase tracking-widest shadow-2xl">3D Modell öffnen</span>
                    </div>
                </div>
                <div class="absolute -bottom-6 left-1/2 -translate-x-1/2 bg-gradient-to-r from-primary to-primary-dark text-gray-900 px-10 py-3.5 rounded-full font-black text-sm uppercase tracking-[0.2em] shadow-2xl animate-pulse">Level {{ $level }}</div>
            </button>
        </div>

        <h2 class="text-6xl md:text-8xl font-serif font-bold mb-4 tracking-tight drop-shadow-2xl">Willkommen, {{ auth()->user()->first_name }}</h2>

        @if(count($profileSteps) > 0)
            <div class="mb-8 flex flex-wrap justify-center gap-3 bg-gray-900/50 backdrop-blur border border-gray-800 p-4 rounded-2xl shadow-inner">
                <span class="w-full block text-[10px] text-gray-500 uppercase tracking-[0.2em] font-black mb-1">Profil vervollständigen:</span>
                @foreach($profileSteps as $step)
                    <button @click="{!! $step['action'] !!}" class="px-5 py-2.5 bg-red-500/10 border border-red-500/30 text-red-400 rounded-full text-[10px] font-black uppercase tracking-widest hover:bg-red-500 hover:text-white transition-all shadow-[0_0_15px_rgba(239,68,68,0.2)] animate-pulse hover:scale-105">{{ $step['label'] }}</button>
                @endforeach
            </div>
        @else
            <p class="text-gray-400 text-xl md:text-2xl mb-12 max-w-3xl leading-relaxed">Deine persönliche Manufaktur ist bereit. Verwalte deine Schätze oder rüste Funki für neue Abenteuer auf.</p>
        @endif

        <div class="flex flex-wrap justify-center gap-8">
            <a href="{{ route('shop') }}" target="_blank" class="px-12 py-6 bg-white text-gray-900 rounded-2xl font-black text-base hover:bg-primary transition-all uppercase tracking-widest shadow-xl hover:-translate-y-1">Neues Unikat gestalten</a>
            <button @click="document.getElementById('orders-section').scrollIntoView({behavior: 'smooth'})" class="px-12 py-6 bg-gray-800 text-white rounded-2xl font-black text-base hover:bg-gray-700 border border-gray-700 transition-all uppercase tracking-widest shadow-xl hover:-translate-y-1">Bestellungen ansehen</button>
        </div>
    </div>
</div>

<div class="px-8 py-6 bg-gray-800/60 backdrop-blur-sm border-b border-gray-800 flex flex-col lg:flex-row justify-between items-center gap-4 relative z-10">
    <div class="flex bg-black/60 p-1.5 rounded-xl border border-gray-700 w-full lg:w-auto overflow-x-auto no-scrollbar">
        <button wire:click="$set('filterType', 'all')" class="px-5 py-2 rounded-lg text-xs font-bold uppercase tracking-wider transition-all whitespace-nowrap {{ $filterType === 'all' ? 'bg-primary text-gray-900 shadow-md' : 'text-gray-400 hover:text-white' }}">Alle Items</button>
        <button wire:click="$set('filterType', 'background')" class="px-5 py-2 rounded-lg text-xs font-bold uppercase tracking-wider transition-all whitespace-nowrap {{ $filterType === 'background' ? 'bg-primary text-gray-900 shadow-md' : 'text-gray-400 hover:text-white' }}">Hintergründe</button>
        <button wire:click="$set('filterType', 'frame')" class="px-5 py-2 rounded-lg text-xs font-bold uppercase tracking-wider transition-all whitespace-nowrap {{ $filterType === 'frame' ? 'bg-primary text-gray-900 shadow-md' : 'text-gray-400 hover:text-white' }}">Rahmen</button>
        <button wire:click="$set('filterType', 'skin')" class="px-5 py-2 rounded-lg text-xs font-bold uppercase tracking-wider transition-all whitespace-nowrap {{ $filterType === 'skin' ? 'bg-primary text-gray-900 shadow-md' : 'text-gray-400 hover:text-white' }}">Skins</button>
    </div>

    <div class="flex w-full lg:w-auto gap-4">
        <select wire:model.live="filterRarity" class="bg-black/60 border border-gray-700 text-gray-300 text-sm rounded-xl px-4 py-2 focus:ring-primary focus:border-primary cursor-pointer outline-none">
            <option value="all">Alle Seltenheiten</option>
            <option value="common">Gewöhnlich</option>
            <option value="rare">Selten</option>
            <option value="epic">Episch</option>
            <option value="legendary">Legendär</option>
        </select>
    </div>
</div>
