<div class="@if(isset($class)){{ $class }}@endif">
    <label for="{{ $variable }}" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">
        @if(isset($title)) {{ $title }} @endif
    </label>

    <div class="relative">
        <input
            wire:model.blur="{{ $variable }}"
            type="range"
            @if(isset($min_range)) min="{{ $min_range }}" @endif
            @if(isset($max_range)) max="{{ $max_range }}" @endif
            @if(isset($placeholder)) placeholder="{{ $placeholder }}" @endif
            @if(isset($id)) id="{{ $variable }}" @endif
            @if(isset($required)) required @endif
            class="cursor-pointer">
    </div>

    @error( $variable )
    <p class="text-red-500">{{ $message }}</p>
    @enderror
</div>
