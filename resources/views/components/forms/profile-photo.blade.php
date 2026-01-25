@props(['user', 'class' => ''])

<div class="@if(isset($class)){{ $class }}@endif">

    <div class="mt-2 flex items-center gap-x-3">

        <input wire:model="photo" type="file" id="photo" class="hidden">

        <label for="photo">
            @if($user->profile->photo_path != null)
                <img class="h-24 w-24 text-gray-300 rounded-full cursor-pointer"
                     src="{{ Storage::url($user->profile->photo_path) }}"
                     title="Klicken Sie hier, um das Foto zu ändern">
            @else
                <img class="h-24 w-24 text-gray-300 rounded-full cursor-pointer"
                     src="{{ URL::to('/images/profile.webp') }}"
                     title="Klicken Sie hier, um das Foto zu ändern">
            @endif
        </label>

        @if($user->profile->photo_path)
            <button wire:click="deletePhoto" type="button"
                    class="btn-secondary">
                Entfernen
            </button>
        @endif
    </div>

    <x-alerts.errors/>

</div>
