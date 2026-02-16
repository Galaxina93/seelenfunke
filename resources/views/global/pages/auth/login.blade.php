<x-layouts.guest>
    {{--
        Haupt-Container:
        - 'bg-black' für den durchgehenden schwarzen Hintergrund.
        - 'flex-col' für vertikale Anordnung.
    --}}
    <div class="min-h-screen bg-black flex flex-col font-sans antialiased">

        {{--
            1. VIDEO HEADER
            - 'h-64 md:h-96': Ausreichend Höhe, damit das Video wirken kann.
            - 'shrink-0': Verhindert Stauchen.
        --}}
        <div class="w-full h-48 md:h-64 relative overflow-hidden shrink-0">
            <video autoplay loop muted playsinline class="absolute inset-0 w-full h-full object-cover opacity-80">
                <source src="{{ asset('videos/login_header_sk_b.mp4') }}" type="video/mp4">
                Ihr Browser unterstützt dieses Video-Format nicht.
            </video>

            {{-- Verlauf ins Schwarze: Damit der Übergang zur Box weicher ist --}}
            <div class="absolute inset-0 bg-gradient-to-b from-transparent via-black/10 to-black"></div>
        </div>

        {{--
            2. LOGIN CONTAINER (Overlap Logic)
            - '-mt-12 md:-mt-20': HIER GEÄNDERT.
              Zieht die Box nur moderat (ca. 50-80px) nach oben, nicht mehr so extrem.
            - 'z-10': Liegt über dem Video.
            - 'justify-start': Beginnt direkt nach dem negativen Margin.
        --}}


            {{-- Wrapper für konsistente Breite --}}
            <div>

                {{-- Status / Errors (außerhalb der Component, schwebend) --}}
                @if (session('status'))
                    <div class="bg-green-500/10 backdrop-blur-md border border-green-500/20 text-green-400 px-4 py-3 rounded-xl text-sm shadow-lg mb-2">
                        {{ session('status') }}
                    </div>
                @endif

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
