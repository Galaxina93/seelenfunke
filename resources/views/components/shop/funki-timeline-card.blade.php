{{--DAS IST DIE funki-timeline-card.blade.php--}}
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
    // Farbschema basierend auf Typ (nur relevant, wenn Status = 'next')
    $themeColor = $type === 'voucher' ? 'purple' : 'blue';

    // Klassenlogik basierend auf Status
    $containerClasses = match($state) {
        'past'   => 'border-green-500 bg-green-50/50',
        // FIX: scale-[1.02] entfernt, damit die linke Kante bündig bleibt
        'next'   => "border-{$themeColor}-500 bg-white ring-4 ring-{$themeColor}-50 shadow-lg z-10",
        'future' => 'border-slate-200 bg-slate-50 opacity-70 grayscale-[0.5]',
        default  => 'border-slate-200 bg-white'
    };

    // Textfarben für Datum & Highlights
    $textClasses = match($state) {
        'past'   => 'text-green-600/70',
        'next'   => "text-{$themeColor}-600",
        'future' => 'text-slate-400',
    };

    // NEU: Icon Mapping für Feiertage
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

    // Bereinige den Event-Namen (Datum in Klammern entfernen, falls vorhanden)
    // Macht aus "Valentinstag (14.02.)" -> "Valentinstag"
    $cleanEventName = $eventName ? preg_replace('/\s\(.*\)/', '', $eventName) : '';
@endphp

<div class="snap-start shrink-0 w-56 p-5 rounded-2xl border transition-all duration-300 relative flex flex-col justify-between group min-h-[130px] {{ $containerClasses }}">

    {{-- 1. STATUS ICON (Oben Rechts) --}}
    <div class="absolute top-4 right-4">
        @if($state === 'past')
            {{-- Grüner Haken --}}
            <div class="bg-white rounded-full text-green-500 shadow-sm">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-6 h-6">
                    <path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12Zm13.36-1.814a.75.75 0 1 0-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 0 0-1.06 1.06l2.25 2.25a.75.75 0 0 0 1.14-.094l3.75-5.25Z" clip-rule="evenodd" />
                </svg>
            </div>
        @elseif($state === 'next')
            {{-- Pulsierender Spinner --}}
            <div class="relative flex items-center justify-center">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-{{ $themeColor }}-400 opacity-75"></span>
                <div class="relative bg-white rounded-full p-1 shadow-sm text-{{ $themeColor }}-600">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-4 h-4 animate-spin">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                    </svg>
                </div>
            </div>
        @else
            {{-- Uhr Icon (Wartend) --}}
            <div class="text-slate-300">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
            </div>
        @endif
    </div>

    {{-- 2. INHALT (Datum & Titel) --}}
    <div class="mt-1">
        <div class="text-[10px] font-black uppercase tracking-wider mb-2 flex items-center gap-1 {{ $textClasses }}">
            {{ $date->format('d.m.Y') }}
        </div>

        <h5 class="font-bold text-slate-900 text-sm leading-tight line-clamp-2 mb-2" title="{{ $title }}">
            {{ str_replace('📧 ', '', $title) }}
        </h5>

        {{-- NEU: Anzeige des Feiertags --}}
        @if($cleanEventName)
            <div class="inline-flex items-center gap-1.5 bg-slate-50 border border-slate-100 px-2 py-1 rounded-md mb-1 max-w-full">
                <span class="text-sm leading-none">{{ $eventIcon }}</span>
                <span class="text-[10px] font-bold text-slate-500 uppercase tracking-tight truncate">{{ $cleanEventName }}</span>
            </div>
        @endif

        @if($subtitle)
            <div class="text-[10px] font-mono text-slate-500 mt-1 truncate">
                {{ $subtitle }}
            </div>
        @endif
    </div>

    {{-- 3. BADGE (Nur bei 'next') --}}
    @if($state === 'next')
        <div class="mt-4 inline-flex items-center justify-center gap-1.5 w-full text-[10px] font-black text-white bg-{{ $themeColor }}-500 px-3 py-1.5 rounded-lg shadow-md shadow-{{ $themeColor }}-200">
            <span>{{ $badgeText }}</span>
        </div>
    @endif
</div>
