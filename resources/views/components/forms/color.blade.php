<div class="@if(isset($class)){{ $class }}@endif">
    <label for="{{ $variable }}" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">
        @if(isset($title)) {{ $title }} @endif
    </label>

    <div class="relative">
        <input
            wire:model="{{ $variable }}"
            @if(isset($placeholder)) placeholder="{{ $placeholder }}" @endif
            @if(isset($id)) id="{{ $variable }}" @endif
            @if(isset($required)) required @endif
            type="color"
            class="cursor-pointer">
    </div>

    @error( $variable )
    <p class="text-red-500">{{ $message }}</p>
    @enderror
</div>
