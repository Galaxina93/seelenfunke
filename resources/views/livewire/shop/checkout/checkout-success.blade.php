<div class="max-w-3xl mx-auto px-4 py-16 my-8 text-center animate-fade-in">
    {{-- Success Icon --}}
    <div class="mb-8 flex justify-center">
        <div class="w-20 h-20 bg-green-100 text-green-600 rounded-full flex items-center justify-center shadow-sm">
            <svg class="w-10 h-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
            </svg>
        </div>
    </div>

    <h1 class="text-4xl font-serif font-bold text-gray-900 mb-4">Vielen Dank!</h1>
    <p class="text-xl text-gray-600 mb-12">Deine Bestellung war erfolgreich.</p>

    {{-- BESTELLBOX MIT FUNKI --}}
    {{-- 'relative' ist wichtig, damit sich das absolute Bild daran orientiert --}}
    {{-- 'mt-16' schafft Platz nach oben für das herausragende Bild --}}
    <div class="relative bg-white border border-gray-200 rounded-2xl p-8 shadow-sm mb-10 mt-16">

        {{-- FUNKI BILD --}}
        {{-- left-1/2 -translate-x-1/2: Zentriert horizontal --}}
        {{-- -top-20: Zieht das Bild nach oben (Wert anpassen je nach Bildhöhe) --}}
        {{-- h-24: Fixe Höhe, damit er nicht riesig wird --}}
        <div class="absolute -top-24 left-1/2 transform -translate-x-1/2">
            <img src="{{ asset('images/projekt/funki/checkout/funki_kiss.png') }}"
                 alt="Funki sendet einen Kuss"
                 class="h-28 w-auto object-contain filter drop-shadow-sm">
        </div>

        <p class="text-sm text-gray-500 uppercase tracking-widest mb-2 mt-4">Bestellnummer</p>
        <p class="text-3xl font-mono font-bold text-primary tracking-tight">{{ $finalOrderNumber ?? 'ORDER-12345' }}</p>

        <div class="mt-8 pt-6 border-t border-gray-100">
            <p class="text-gray-600">
                Wir haben dir eine Bestätigung an <span class="font-bold text-gray-900">{{ $email ?? 'deine E-Mail' }}</span> gesendet.
            </p>
        </div>
    </div>

    {{-- Buttons --}}
    <div class="flex flex-col sm:flex-row gap-4 justify-center">
        <a href="{{ route('shop') }}" class="px-8 py-3 bg-gray-900 text-white rounded-full font-bold hover:bg-black transition shadow-lg transform hover:-translate-y-0.5">
            Weiter einkaufen
        </a>
        @if(Auth::guard('customer')->check())
            <a href="{{ route('customer.dashboard') }}" class="px-8 py-3 bg-white text-gray-900 border border-gray-200 rounded-full font-bold hover:bg-gray-50 transition">
                Bestellung verfolgen
            </a>
        @endif
    </div>
</div>
