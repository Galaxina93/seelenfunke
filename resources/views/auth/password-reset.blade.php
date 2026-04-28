<x-layouts.guest>
    {{--
        Haupt-Container:
        - 'bg-black' für den durchgehenden schwarzen Hintergrund.
        - 'flex-col' für vertikale Anordnung.
    --}}
    <div class="min-h-screen bg-black flex flex-col font-sans antialiased">

        {{-- Wrapper für konsistente Breite --}}
        <div>
            {{-- Die eigentliche Passwort-Reset-Karte --}}
            @livewire('auth.auth-password-reset', ['token' => $token])
        </div>

    </div>
</x-layouts.guest>
