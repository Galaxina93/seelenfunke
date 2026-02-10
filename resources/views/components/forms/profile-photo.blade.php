@props(['user', 'class' => ''])

<div class="@if(isset($class)){{ $class }}@endif">

    <div class="mt-2 flex items-center gap-x-3">

        <input wire:model="photo" type="file" id="photo" class="hidden">

        <label for="photo">
            @if($user->profile->photo_path != null)
                {{-- WICHTIG: str_replace entfernt 'public/', damit der Link stimmt --}}
                <img class="h-24 w-24 object-cover rounded-full cursor-pointer border border-gray-200"
                     src="{{ Storage::url(str_replace('public/', '', $user->profile->photo_path)) }}"
                     alt="{{ $user->first_name }}"
                     title="Klicken Sie hier, um das Foto zu ändern">
            @else
                <img class="h-24 w-24 object-cover rounded-full cursor-pointer border border-gray-200"
                     src="{{ asset('images/profile.webp') }}" {{-- Besser asset() statt URL::to() --}}
                     alt="Profilbild Platzhalter"
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
