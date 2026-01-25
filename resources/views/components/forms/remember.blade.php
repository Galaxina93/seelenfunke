@props([
    'class' => '',
    'remember' => 'remember',
    'forgotAction' => 'setPasswordResetView',
])

<div {{ $attributes->class(['flex items-center justify-between', $class]) }}>
    <div class="flex items-center space-x-2">
        <div class="flex items-center">
            <input
                id="{{ $remember }}"
                type="checkbox"
                wire:model.defer="{{ $remember }}"
                aria-describedby="{{ $remember }}-label"
                class="w-4 h-4 border border-gray-300 rounded bg-white focus:ring-2 focus:ring-indigo-500"
            >
        </div>
        <div class="text-sm">
            <label id="{{ $remember }}-label" for="{{ $remember }}" class="text-gray-700">
                Login merken
            </label>
        </div>
    </div>

    <button
        type="button"
        wire:click="{{ $forgotAction }}"
        class="text-sm font-medium text-indigo-600 hover:underline focus:outline-none"
    >
        Passwort vergessen?
    </button>
</div>
