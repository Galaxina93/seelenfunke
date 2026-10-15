<x-layouts.guest>
    {{--
        Haupt-Container:
        - 'bg-black' für den durchgehenden schwarzen Hintergrund.
        - 'flex-col' für vertikale Anordnung.
    --}}
    <div class="min-h-screen bg-gray-50 flex flex-col font-sans antialiased">

        {{-- Wrapper für konsistente Breite --}}
        <div>
            @if ($errors->any())
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-sm shadow-sm mb-2 max-w-md mx-auto mt-4">
                    <ul class="list-disc pl-5 space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Die eigentliche Login-Karte --}}
            @livewire('auth.auth-login')

        </div>

    </div>
</x-layouts.guest>
