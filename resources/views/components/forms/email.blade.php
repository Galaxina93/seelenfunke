@props([
    'class' => '',
    'autofocus' => false,
    'required' => false,
    'name' => 'email',
    'placeholder' => 'name@company.de',
])

<div {{ $attributes->class([$class, 'w-full'])->merge() }}>
    <label for="{{ $name }}" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">
        E-Mail
    </label>

    <input
        id="{{ $name }}"
        name="{{ $name }}"
        type="email"
        placeholder="{{ $placeholder }}"
        @if($autofocus) autofocus @endif
        @if($required) required aria-required="true" @endif
        wire:model.defer="{{ $name }}"
        class="border border-gray-300 rounded-lg block w-full p-2.5 sm:text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 @error($name) border-red-500 @enderror"
        aria-invalid="{{ $errors->has($name) ? 'true' : 'false' }}"
        aria-describedby="{{ $name }}-error"
    >

    @error($name)
    <p id="{{ $name }}-error" class="mt-1 text-sm text-red-600">
        {{ $message }}
    </p>
    @enderror
</div>
