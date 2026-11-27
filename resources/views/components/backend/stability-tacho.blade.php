@props([
    'score' => 100,
    'openCount' => 0,
    'pillars' => [],
])

@php
    $safeOpen = max(0, (int) $openCount);
    $rawScore = max(0, min(100, (int) $score));
    // Eiserne Garantie: Sobald mindestens 1 Aufgabe oder Rückfrage offen ist, zeigt der Tacho niemals 100 %!
    $effectiveScore = $safeOpen > 0 ? min(99, $rawScore) : 100;

    $arcLength = 125.66; // pi * r (r = 40)
    $arcOffset = round($arcLength - (($effectiveScore / 100) * $arcLength), 2);
    $needleDeg = round(-90 + (($effectiveScore / 100) * 180), 1);

    if ($effectiveScore === 100) {
        $strokeHex = '#10b981';
        $textColorClass = 'text-emerald-600 dark:text-emerald-400';
        $badgeClass = 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-500/30';
        $statusLabel = '100 % Stabil';
    } elseif ($effectiveScore >= 50) {
        $strokeHex = '#C5A059';
        $textColorClass = 'text-[#9A7B3E] dark:text-[#C5A059]';
        $badgeClass = 'bg-[#C5A059]/15 text-[#8a6820] dark:text-[#C5A059] border-[#C5A059]/40';
        $statusLabel = $safeOpen === 1 ? '1 Punkt offen' : ($safeOpen . ' Punkte offen');
    } else {
        $strokeHex = '#ef4444';
        $textColorClass = 'text-red-600 dark:text-red-400';
        $badgeClass = 'bg-red-500/10 text-red-700 dark:text-red-400 border-red-500/30';
        $statusLabel = 'Fokus benötigt (' . $safeOpen . ')';
    }
@endphp

<div data-stability-tacho="true"
     data-stability-score="{{ $effectiveScore }}"
     data-stability-open="{{ $safeOpen }}"
     {{ $attributes->merge(['class' => 'flex flex-col sm:flex-row items-center gap-4 sm:gap-5 px-4 py-3 rounded-2xl bg-gray-50/90 dark:bg-gray-950/80 border border-gray-200/80 dark:border-gray-800']) }}>

    {{-- Halbkreis-Präzisions-Tacho (0-100 %) --}}
    <div class="relative w-28 h-16 shrink-0 flex items-end justify-center">
        <svg class="w-28 h-16 overflow-visible" viewBox="0 0 100 58" aria-hidden="true">
            <path d="M 10 50 A 40 40 0 0 1 90 50"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="7"
                  stroke-linecap="round"
                  class="text-gray-200 dark:text-gray-800"/>
            <path d="M 10 50 A 40 40 0 0 1 90 50"
                  fill="none"
                  stroke="{{ $strokeHex }}"
                  stroke-width="7"
                  stroke-linecap="round"
                  stroke-dasharray="{{ $arcLength }}"
                  stroke-dashoffset="{{ $arcOffset }}"
                  style="transition: stroke-dashoffset 700ms cubic-bezier(0.22, 1, 0.36, 1);"/>
            {{-- Dezente Skalen-Markierungen (0 / 50 / 100) --}}
            <circle cx="10" cy="50" r="1.5" class="fill-gray-400 dark:fill-gray-600"/>
            <circle cx="50" cy="10" r="1.5" class="fill-gray-400 dark:fill-gray-600"/>
            <circle cx="90" cy="50" r="1.5" class="fill-gray-400 dark:fill-gray-600"/>
            {{-- Gedämpfte Tacho-Nadel --}}
            <g transform="translate(50, 50) rotate({{ $needleDeg }})" style="transition: transform 700ms cubic-bezier(0.22, 1, 0.36, 1);">
                <line x1="0" y1="0" x2="0" y2="-30" stroke="{{ $strokeHex }}" stroke-width="2.2" stroke-linecap="round"/>
                <circle cx="0" cy="0" r="3.5" fill="{{ $strokeHex }}"/>
            </g>
        </svg>
    </div>

    <div class="flex flex-col items-center sm:items-start text-center sm:text-left min-w-0">
        <div class="flex items-center gap-2 flex-wrap justify-center sm:justify-start">
            <span class="text-xl font-black tracking-tight {{ $textColorClass }}">{{ $effectiveScore }}&nbsp;%</span>
            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider border {{ $badgeClass }}">
                {{ $statusLabel }}
            </span>
        </div>
        <span class="text-[10px] font-bold uppercase tracking-widest text-gray-500 dark:text-gray-400 mt-1">
            Stabilitäts-Tacho
        </span>

        @if(!empty($pillars))
            <div class="flex flex-wrap items-center justify-center sm:justify-start gap-1.5 mt-2">
                @foreach($pillars as $pillar)
                    @php $pCount = (int) ($pillar['count'] ?? 0); @endphp
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg text-[10px] font-bold border {{ $pCount > 0 ? 'bg-white dark:bg-gray-900 border-[#C5A059]/40 text-gray-900 dark:text-white' : 'bg-white/60 dark:bg-gray-900/50 border-gray-200 dark:border-gray-800 text-gray-500 dark:text-gray-400' }}">
                        <span>{{ $pillar['label'] }}:</span>
                        <strong class="{{ $pCount > 0 ? 'text-[#9A7B3E] dark:text-[#C5A059]' : 'text-emerald-600 dark:text-emerald-400' }}">{{ $pCount > 0 ? $pCount : '✓' }}</strong>
                    </span>
                @endforeach
            </div>
        @endif
    </div>
</div>
