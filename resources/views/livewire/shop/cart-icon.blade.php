<div class="relative group">
    <a href="{{ route('cart') }}" class="text-white hover:text-primary transition-colors duration-300 p-1 block relative">
        {{-- Icon --}}
        <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7 transform group-hover:scale-110 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
        </svg>

        {{-- Badge (Rote leuchtende Zahl) --}}
        @if($totals['item_count'] > 0)
            <span class="absolute -top-1 -right-1 flex h-5 w-5">
              <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
              <span class="relative inline-flex rounded-full h-5 w-5 bg-red-500 text-white text-[10px] font-bold justify-center items-center">
                  {{ $totals['item_count'] }}
              </span>
            </span>
        @endif
    </a>

    {{-- Tooltip / Mini-Warenkorb Vorschau --}}
    @if($totals['item_count'] > 0)
        <div class="absolute right-0 top-full mt-2 w-64 bg-white rounded-xl shadow-xl border border-gray-100 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 transform group-hover:translate-y-0 translate-y-2 z-50 overflow-hidden">

            <div class="p-4 space-y-3">
                {{-- Zeile: Zwischensumme --}}
                <div class="flex justify-between text-xs text-gray-600">
                    <span>Zwischensumme</span>
                    <span>{{ number_format($totals['subtotal'] / 100, 2, ',', '.') }} €</span>
                </div>

                {{-- Zeile: MwSt --}}
                <div class="flex justify-between text-[10px] text-gray-400">
                    <span>Enthaltene MwSt.</span>
                    <span>{{ number_format($totals['tax'] / 100, 2, ',', '.') }} €</span>
                </div>

                {{-- Zeile: Versand --}}
                <div class="flex justify-between text-xs">
                    <span class="text-gray-600">Versand</span>
                    @if($totals['shipping'] == 0)
                        <span class="text-green-600 font-bold">Kostenlos</span>
                    @else
                        <span>{{ number_format($totals['shipping'] / 100, 2, ',', '.') }} €</span>
                    @endif
                </div>

                <hr class="border-gray-100">

                {{-- Zeile: Gesamtsumme --}}
                <div class="flex justify-between items-center">
                    <span class="font-bold text-gray-900 text-sm">Gesamt</span>
                    <span class="font-bold text-primary text-lg">{{ number_format($totals['total'] / 100, 2, ',', '.') }} €</span>
                </div>
            </div>

            {{-- Footer Button --}}
            <a href="{{ route('cart') }}" class="block bg-gray-900 text-white text-center py-3 text-xs font-bold uppercase tracking-wider hover:bg-black transition">
                Zum Warenkorb
            </a>

            {{-- Kleiner Pfeil oben am Tooltip --}}
            <div class="absolute top-0 right-3 -mt-1 w-2 h-2 bg-white transform rotate-45 border-l border-t border-gray-100"></div>
        </div>
    @endif
</div>
