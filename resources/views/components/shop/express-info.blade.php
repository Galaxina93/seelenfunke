@props([
    'label' => 'Express-Service',
    'iconClass' => 'w-4 h-4',
])
@php
    $expressPercent = number_format((float) shop_setting('express_surcharge_percent', 20.0), 0, ',', '.');
    $expressMin = number_format(((int) shop_setting('express_surcharge_min', 500)) / 100, 2, ',', '.');
@endphp
<span x-data="{
        open: false,
        x: 0,
        y: 0,
        above: true,
        show(el) {
            const rect = el.getBoundingClientRect();
            this.x = Math.min(Math.max(rect.left + rect.width / 2, 150), window.innerWidth - 150);
            this.above = rect.top > 160;
            this.y = this.above ? rect.top - 8 : rect.bottom + 8;
            this.open = true;
        }
    }"
    @mouseenter="show($el)"
    @mouseleave="open = false"
    @scroll.window="if(open) open = false"
    {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 cursor-help']) }}>
    @if($slot->isEmpty())
        <span>{{ $label }}</span>
        <svg class="{{ $iconClass }} text-primary shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
    @else
        {{ $slot }}
    @endif

    <template x-teleport="body">
        <div x-show="open"
             x-cloak
             x-transition:enter="transition ease-out duration-150"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="transition ease-in duration-100"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95"
             style="display: none;"
             :style="`position: fixed; left: ${x}px; top: ${y}px; transform: translate(-50%, ${above ? '-100%' : '0'}); z-index: 99999;`"
             class="w-72 p-3 rounded-xl bg-gray-950/95 border border-primary/40 text-white shadow-2xl backdrop-blur-md pointer-events-none text-left font-sans normal-case tracking-normal print:hidden">
            <div class="flex items-center gap-1.5 text-[11px] font-bold text-primary mb-1.5 border-b border-gray-800 pb-1.5">
                <span>Express-Service</span>
                <svg class="w-3.5 h-3.5 text-primary shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            </div>
            <div class="space-y-1.5 text-[11px] leading-snug text-gray-200 font-normal">
                <p><strong class="text-white font-semibold">Vorteil:</strong> Sofortige Priorisierung in der Manufaktur, dein Auftrag wird regulären Bestellungen vorgezogen.</p>
                <p><strong class="text-white font-semibold">Zusammensetzung:</strong> {{ $expressPercent }}&nbsp;% vom Warenwert (mind. {{ $expressMin }}&nbsp;€ Basis-Pauschale) für die bevorzugte Einzelfertigung.</p>
            </div>
        </div>
    </template>
</span>
