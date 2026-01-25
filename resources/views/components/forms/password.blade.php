@props([
    'title' => 'Passwort',
    'variable' => 'password',
    'id' => null,
    'required' => false,
    'class' => '',
    'placeholder' => '••••••••',
])

<div {{ $attributes->class([$class, 'w-full'])->merge() }} x-data="{ show: false }">
    <label for="{{ $id ?? $variable }}" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">
        {{ $title }}
        @if($required)
            <span aria-hidden="true" class="text-red-500">*</span>
        @endif
    </label>

    <div class="relative">
        <input
            id="{{ $id ?? $variable }}"
            name="{{ $variable }}"
            type="password"
            :type="show ? 'text' : 'password'"
            wire:model.defer="{{ $variable }}"
            placeholder="{{ $placeholder }}"
            @if($required) required aria-required="true" @endif
            autocomplete="current-password"
            class="block w-full rounded-lg border border-gray-300 px-3 py-2 sm:text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 @error($variable) border-red-500 @enderror"
            aria-invalid="{{ $errors->has($variable) ? 'true' : 'false' }}"
            aria-describedby="{{ $variable }}-error"
        >

        <button
            type="button"
            x-on:click="show = !show"
            :aria-label="show ? 'Passwort verbergen' : 'Passwort anzeigen'"
            class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-600 focus:outline-none"
        >
            <span x-show="!show" class="sr-only">Passwort anzeigen</span>
            <span x-show="show" class="sr-only">Passwort verbergen</span>

            <x-heroicon-m-eye-slash x-show="!show" class="cursor-pointer hover:text-primary w-6 h-6 text-gray-500 transform hover:scale-110 duration-100 h-5 w-5" aria-hidden="true"/>
            <x-heroicon-m-eye x-show="show" class="cursor-pointer hover:text-primary w-6 h-6 text-gray-500 transform hover:scale-110 duration-100 h-5 w-5" aria-hidden="true"/>
        </button>
    </div>

    @error($variable)
    <p id="{{ $variable }}-error" class="mt-1 text-sm text-red-600">
        {{ $message }}
    </p>
    @enderror
</div>
