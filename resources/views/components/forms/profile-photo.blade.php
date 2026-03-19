@props(['user', 'class' => ''])

<div class="@if(isset($class)){{ $class }}@endif">

    <div class="mt-2 flex items-center gap-x-3">

        <input wire:model="photo" type="file" id="photo" class="hidden">

        <label for="photo">
            @if($user->profile->photo_path != null)
                @php
                    $pp = str_replace('public/', '', $user->profile->photo_path);
                    $src = (str_starts_with($pp, 'images/') || str_starts_with($pp, '/')) 
                           ? asset($pp) : Storage::url($pp);
                @endphp
                <img class="h-24 w-24 object-cover rounded-full cursor-pointer border border-gray-200"
                     src="{{ $src }}"
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
