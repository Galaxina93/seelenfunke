<x-layouts.guest>
    {{--
        Haupt-Container:
        - 'bg-black' für den durchgehenden schwarzen Hintergrund.
        - 'flex-col' für vertikale Anordnung.
    --}}
    <div class="min-h-screen bg-black flex flex-col font-sans antialiased">

        {{-- Wrapper für konsistente Breite --}}
        <div>
            @if ($errors->any())
                <div class="bg-red-500/10 backdrop-blur-md border border-red-500/20 text-red-400 px-4 py-3 rounded-xl text-sm shadow-lg mb-2">
                    <ul class="list-disc pl-5 space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Die eigentliche Login-Karte --}}
            @livewire('global.auth.login')

        </div>

    </div>
</x-layouts.guest>
