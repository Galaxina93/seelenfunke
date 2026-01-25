@props([
    'submit' => null,
    'grid' => true,
])

<form
    @if($submit) wire:submit.prevent.stop="{{ $submit }}" @endif
{{ $attributes->merge(['class' => 'space-y-0']) }}
novalidate
>
    @csrf

    <div @class([
        'grid grid-cols-6 gap-6' => $grid,
        'space-y-6' => !$grid,
    ])>
        {{ $slot }}
    </div>
</form>
