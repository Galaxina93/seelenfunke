<div class="relative">
    <input
        type="search"
        wire:model.live.debounce.300ms="search"
        name="search"
        placeholder="Suche..."

        class="border border-1 border-gray-300 text-gray-900 sm:text-sm rounded-lg block w-full p-2.5 @if(isset($class)){{ $class }}@endif">
</div>
