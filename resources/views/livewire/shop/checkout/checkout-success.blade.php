<div class="max-w-3xl mx-auto px-4 py-16 my-8 text-center animate-fade-in">
    {{-- FUNKI STATT GRÜNEM HAKEN --}}
    <div class="mb-6 flex justify-center">
        {{-- h-40 macht ihn schön groß und präsent --}}
        <img src="{{ asset('images/projekt/funki/checkout/funki_kiss.png') }}"
             alt="Vielen Dank"
             class="h-40 w-auto object-contain filter drop-shadow-sm animate-bounce-slow">
    </div>

    <h1 class="text-4xl font-serif font-bold text-gray-900 mb-4">Vielen Dank!</h1>
    <p class="text-xl text-gray-600 mb-12">Deine Bestellung war erfolgreich.</p>

    {{-- BESTELLBOX (Clean, da Funki jetzt oben ist) --}}
    <div class="bg-white border border-gray-200 rounded-2xl p-8 shadow-sm mb-10">
        <p class="text-sm text-gray-500 uppercase tracking-widest mb-2">Bestellnummer</p>
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
