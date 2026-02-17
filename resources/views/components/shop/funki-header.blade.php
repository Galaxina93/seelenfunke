@props([
    'title' => 'Willkommen bei Funki!',
    'subtitle' => 'Ich halte hier die Stellung.',
    'status' => 'Online'
])

<div class="mb-10 max-w-7xl mx-auto">
    <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-100 overflow-hidden relative">
        {{-- Background Pattern --}}
        <div class="absolute inset-0 opacity-[0.03] pointer-events-none" style="background-image: radial-gradient(#6366f1 1px, transparent 1px); background-size: 20px 20px;"></div>

        <div class="p-8 md:p-10">
            {{-- OBERER TEIL (Avatar, Text, KPIs) --}}
            <div class="flex flex-col md:flex-row items-center gap-8 relative z-10">
                {{-- FUNKI AVATAR --}}
                <div class="shrink-0 relative group">
                    <div class="absolute inset-0 bg-blue-500 rounded-full blur-xl opacity-20 group-hover:opacity-30 transition-opacity duration-500"></div>
                    <img src="{{ asset('images/projekt/funki/funki_selfie.png') }}"
                         class="relative w-24 h-24 md:w-32 md:h-32 object-contain drop-shadow-xl transform group-hover:scale-105 group-hover:rotate-3 transition-transform duration-500"
                         alt="Funki">

                    <div class="absolute -bottom-2 -right-2 bg-white rounded-xl shadow-lg border border-slate-100 p-2 flex items-center gap-2 animate-bounce-slow">
                        <span class="relative flex h-3 w-3">
                          <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                          <span class="relative inline-flex rounded-full h-3 w-3 bg-green-500"></span>
                        </span>
                        <span class="text-[10px] font-black text-slate-600 uppercase tracking-wider">{{ $status }}</span>
                    </div>
                </div>

                {{-- TEXT --}}
                <div class="text-center md:text-left flex-1">
                    <h2 class="text-2xl md:text-3xl font-black text-slate-900 leading-tight mb-2">
                        {{ $title }}
                    </h2>
                    <p class="text-slate-500 font-medium text-sm md:text-base max-w-xl mx-auto md:mx-0 leading-relaxed">
                        {{ $subtitle }}
                    </p>
                </div>

                {{-- KPI GRID SLOT (Standard-Slot) --}}
                <div class="grid grid-cols-2 gap-4 w-full md:w-auto">
                    {{ $slot }}
                </div>
            </div>

            {{-- UNTERER TEIL (Optionaler Footer Slot für Timelines etc.) --}}
            @if(isset($footer))
                <div class="mt-8 pt-8 border-t border-slate-100 relative z-10 animate-fade-in">
                    {{ $footer }}
                </div>
            @endif
        </div>
    </div>
</div>
