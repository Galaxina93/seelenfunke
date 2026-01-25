<div class="@if(isset($class)){{ $class }}@endif">
    <label for="{{ $variable }}" class="text-sm font-medium text-gray-900 dark:text-white">
        @if(isset($title)) {{ $title }} @endif
    </label><br>
        <input
            type="checkbox"
            wire:model="{{ $variable }}"
            @if(isset($id)) id="{{ $variable }}" @endif
            @if(isset($title)) {{ $title }} @endif
            @if(isset($required)) required @endif
            class="sm:text-sm"
        />

    @error( $variable )
    <p class="text-red-500">{{ $message }}</p>
    @enderror
</div>
