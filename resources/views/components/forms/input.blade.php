@props([
    'title' => null,
    'variable' => 'input',
    'type' => 'text',
    'id' => null,
    'placeholder' => '',
    'required' => false,
    'autocomplete' => null,
    'autofocus' => false,
    'class' => '',
])

<div {{ $attributes->class([$class, 'w-full'])->merge() }}>
    @if($title)
        <label for="{{ $id ?? $variable }}" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">
            {{ $title }}
            @if($required)
                <span aria-hidden="true" class="text-red-500">*</span>
            @endif
        </label>
    @endif

    <div class="relative">
        <input
            id="{{ $id ?? $variable }}"
            name="{{ $variable }}"
            type="{{ $type }}"
            wire:model.defer="{{ $variable }}"
            @if($placeholder) placeholder="{{ $placeholder }}" @endif
            @if($required) required aria-required="true" @endif
            @if($autocomplete) autocomplete="{{ $autocomplete }}" @endif
            @if($autofocus) autofocus @endif
            class="block w-full rounded-lg border border-gray-300 px-3 py-2 sm:text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 @error($variable) border-red-500 @enderror"
            aria-invalid="{{ $errors->has($variable) ? 'true' : 'false' }}"
            aria-describedby="{{ $variable }}-error"
        >
    </div>

    @error($variable)
    <p id="{{ $variable }}-error" class="mt-1 text-sm text-red-600">
        {{ $message }}
    </p>
    @enderror
</div>
