<div class="max-w-3xl mx-auto px-4 py-16 my-8 text-center animate-fade-in">
    <div class="mb-8 flex justify-center">
        <div class="w-20 h-20 bg-green-100 text-green-600 rounded-full flex items-center justify-center shadow-sm">
            <svg class="w-10 h-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
            </svg>
        </div>
    </div>

    <h1 class="text-4xl font-serif font-bold text-gray-900 mb-4">Vielen Dank!</h1>
    <p class="text-xl text-gray-600 mb-8">Deine Bestellung war erfolgreich.</p>

    <div class="bg-white border border-gray-200 rounded-2xl p-8 shadow-sm mb-10">
        <p class="text-sm text-gray-500 uppercase tracking-widest mb-2">Bestellnummer</p>
        <p class="text-2xl font-mono font-bold text-primary">{{ $finalOrderNumber }}</p>
        <div class="mt-6 pt-6 border-t border-gray-100">
            <p class="text-gray-600">
                Wir haben dir eine Bestätigung an <span class="font-bold text-gray-900">{{ $email }}</span> gesendet.
            </p>
        </div>
    </div>

    <div class="flex flex-col sm:flex-row gap-4 justify-center">
        <a href="{{ route('shop') }}" class="px-8 py-3 bg-gray-900 text-white rounded-full font-bold hover:bg-black transition shadow-lg">
            Weiter einkaufen
        </a>
        @if(Auth::guard('customer')->check())
            <a href="{{ route('customer.dashboard') }}" class="px-8 py-3 bg-white text-gray-900 border border-gray-200 rounded-full font-bold hover:bg-gray-50 transition">
                Bestellung verfolgen
            </a>
        @endif
    </div>
</div>
