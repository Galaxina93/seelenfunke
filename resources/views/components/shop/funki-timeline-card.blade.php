{{-- DAS IST DIE funki-timeline-card.blade.php im Deep Dark UX Design --}}
@props([
    'date',
    'title',
    'subtitle' => null, // z.B. Gutscheincode
    'state' => 'future', // Optionen: 'past', 'next', 'future'
    'type' => 'mail', // Optionen: 'mail', 'voucher' (steuert die Farbe bei 'next')
    'badgeText' => 'Nächster Job',
    'eventKey' => null,
    'eventName' => null
])

@php
    // Farbschema basierend auf Typ (Glow & Akzente)
    $themeColor = $type === 'voucher' ? 'purple' : 'blue';

    // Klassenlogik für das Dark Design
    $containerClasses = match($state) {
        'past'   => 'border-emerald-500/30 bg-emerald-500/5 opacity-80 shadow-[inset_0_0_20px_rgba(16,185,129,0.05)]',
        'next'   => "border-{$themeColor}-500 bg-gray-900 shadow-[0_0_30px_rgba(var(--color-{$themeColor}-500),0.2)] ring-1 ring-{$themeColor}-400/30 z-10 scale-[1.02]",
        'future' => 'border-gray-800 bg-gray-950/40 opacity-60 grayscale-[0.3]',
        default  => 'border-gray-800 bg-gray-900'
    };

    // Textfarben für Datum & Highlights
    $textClasses = match($state) {
        'past'   => 'text-emerald-400',
        'next'   => "text-{$themeColor}-400 drop-shadow-[0_0_8px_rgba(var(--color-{$themeColor}-400),0.5)]",
        'future' => 'text-gray-500',
    };

    // Icon Mapping für Feiertage
    $eventIcon = match($eventKey) {
        'valentines' => '❤️',
        'womens_day' => '💐',
        'easter' => '🐰',
        'mothers_day' => '🤱',
        'fathers_day' => '🧔',
        'halloween' => '🎃',
        'advent_1' => '🕯️',
        'christmas' => '🎄',
        'new_year' => '🎆',
        'sale_summer' => '☀️',
        'sale_winter' => '❄️',
        'registered_date' => '🎂',
        default => '📅'
    };

    // Bereinige den Event-Namen
    $cleanEventName = $eventName ? preg_replace('/\s\(.*\)/', '', $eventName) : '';
@endphp

<div class="w-full h-full p-6 rounded-[2rem] ml-2 mt-2 border transition-all duration-500 relative flex flex-col justify-between group min-h-[160px] backdrop-blur-sm {{ $containerClasses }}">

    {{-- 1. STATUS ICON (Gefixt in einer festen 8x8 Box rechts oben) --}}
    <div class="absolute top-5 right-5 z-20 w-8 h-8 flex items-center justify-center">
        @if($state === 'past')
            {{-- Erledigt: Smaragd-Check --}}
            <div class="w-8 h-8 bg-gray-900 rounded-full text-emerald-500 flex items-center justify-center border border-emerald-500/20 shadow-lg">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5">
                    <path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12Zm13.36-1.814a.75.75 0 1 0-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 0 0-1.06 1.06l2.25 2.25a.75.75 0 0 0 1.14-.094l3.75-5.25Z" clip-rule="evenodd" />
                </svg>
            </div>
        @elseif($state === 'next')
            {{-- Aktiv: Pulsierender Ring --}}
            <div class="relative w-8 h-8 flex items-center justify-center">
                <span class="animate-ping absolute inline-flex h-8 w-8 rounded-full bg-{{ $themeColor }}-400 opacity-40"></span>
                <div class="relative bg-gray-900 rounded-full w-8 h-8 border border-{{ $themeColor }}-500/50 text-{{ $themeColor }}-400 shadow-xl flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-4 h-4 animate-spin">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                    </svg>
                </div>
            </div>
        @else
            {{-- Wartend: Dezent --}}
            <div class="w-8 h-8 flex items-center justify-center text-gray-700">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
            </div>
        @endif
    </div>

    {{-- 2. INHALT (Datum & Titel) - pr-12 schützt vor Überlappung mit dem Icon --}}
    <div class="mt-1 relative z-10 pr-12">
        <div class="text-[10px] font-black uppercase tracking-[0.2em] mb-3 flex items-center gap-1 {{ $textClasses }}">
            {{ $date->format('d.m.Y') }}
        </div>

        <h5 class="font-bold text-gray-100 text-sm leading-snug line-clamp-2 mb-3 group-hover:text-white transition-colors" title="{{ $title }}">
            {{ str_replace('📧 ', '', $title) }}
        </h5>

        {{-- Event Badge --}}
        @if($cleanEventName)
            <div class="inline-flex items-center gap-2 bg-black/40 border border-gray-800 px-3 py-1.5 rounded-xl mb-2 max-w-full shadow-inner">
                <span class="text-sm leading-none filter drop-shadow-sm">{{ $eventIcon }}</span>
                <span class="text-[9px] font-black text-gray-400 uppercase tracking-widest truncate">{{ $cleanEventName }}</span>
            </div>
        @endif

        @if($subtitle)
            <div class="text-[10px] font-mono text-{{ $themeColor }}-400/70 mt-2 truncate bg-{{ $themeColor }}-400/5 px-2 py-1 rounded border border-{{ $themeColor }}-400/10">
                {{ $subtitle }}
            </div>
        @endif
    </div>

    {{-- 3. ACTION BADGE (Nur bei 'next') --}}
    @if($state === 'next')
        <div class="mt-5 relative group/badge w-full">
            <div class="absolute -inset-1 bg-{{ $themeColor }}-500/20 blur opacity-40 rounded-xl"></div>
            <div class="relative flex items-center justify-center gap-2 w-full text-[10px] font-black text-white bg-{{ $themeColor }}-600 px-3 py-2.5 rounded-xl shadow-lg border border-{{ $themeColor }}-400/30 uppercase tracking-widest overflow-hidden font-sans">
                <div class="absolute inset-0 bg-white/10 transform -skew-x-12 -translate-x-[150%] group-hover/badge:translate-x-[150%] transition-transform duration-700"></div>
                <span class="relative z-10">{{ $badgeText }}</span>
            </div>
        </div>
    @endif
</div>
