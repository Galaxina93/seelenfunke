<div class="@if(isset($class)){{ $class }}@endif">
    <label for="{{ $variable }}" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">
        @if(isset($title))  {{ $title }} @endif
    </label>

    <div class="relative">
        <textarea
            wire:model.blur="{{ $variable }}"
            name="{{ $variable }}"
            @if(isset($placeholder)) placeholder="{{ $placeholder }}" @endif
            @if(isset($id)) id="{{ $variable }}" @endif
            @if(isset($required)) required @endif
            class="border border-1 border-gray-300 text-gray-900 sm:text-sm rounded-lg block w-full h-24 p-2.5"></textarea>
    </div>

    @error( $variable )
    <p class="text-red-500">{{ $message }}</p>
    @enderror
</div>
