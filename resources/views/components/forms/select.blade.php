<div class="@if(isset($class)){{ $class }}@endif">
    <label for="{{ $variable }}" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">
        @if(isset($title)) {{ $title }} @endif
    </label>

    <div class="relative">
        <select
            wire:model="{{ $variable }}"
            name="{{ $variable }}"
            @if(isset($id)) id="{{ $variable }}" @endif
            @if(isset($required)) required @endif
            class="border border-1 border-gray-300 text-gray-900 sm:text-sm rounded-lg block w-full p-2.5">
            @if(isset($placeholder))
                <option value="" disabled>{{ $placeholder }}</option>
            @endif

            @if(isset($options))
                @foreach($options as $optionKey => $optionValue)
                    <option value="{{ $optionKey }}">{{ $optionValue }}</option>
                @endforeach
            @endif

        </select>
    </div>

    @error( $variable )
    <p class="text-red-500">{{ $message }}</p>
    @enderror
</div>
