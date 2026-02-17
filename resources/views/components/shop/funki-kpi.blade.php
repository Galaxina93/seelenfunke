@props([
    'label',
    'value',
    'color' => 'blue' // blue, purple, orange, green, red
])

@php
    $colors = [
        'blue' => 'group-hover:border-blue-200 group-hover:text-blue-500',
        'purple' => 'group-hover:border-purple-200 group-hover:text-purple-500',
        'orange' => 'group-hover:border-orange-200 group-hover:text-orange-500',
        'green' => 'group-hover:border-green-200 group-hover:text-green-500',
        'red' => 'group-hover:border-red-200 group-hover:text-red-500',
    ];
    $hoverClass = $colors[$color] ?? $colors['blue'];
@endphp

<div class="bg-slate-50 p-4 rounded-2xl border border-slate-100 text-center min-w-[140px] group transition-colors {{ $hoverClass }}">
    <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1 transition-colors {{ str_replace('border', 'text', $hoverClass) }}">
        {{ $label }}
    </div>
    <div class="text-2xl font-black text-slate-900 group-hover:scale-110 transition-transform">
        {{ $value }}
    </div>
</div>
