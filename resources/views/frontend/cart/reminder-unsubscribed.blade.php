<x-layouts.frontend_layout>
    <div class="min-h-[70vh] bg-slate-50/50 flex items-center justify-center px-4 py-16 sm:px-6 lg:px-8 font-sans">
        <div class="max-w-md w-full bg-white p-8 sm:p-10 rounded-[2.5rem] shadow-xl shadow-slate-200/50 border border-slate-100 relative overflow-hidden transition-all duration-500">
            
            @if($status === 'success')
                {{-- Erfolgreiche Abmeldung --}}
                <div class="text-center">
                    <div class="w-16 h-16 mx-auto mb-6 rounded-full bg-emerald-50 border border-emerald-200 flex items-center justify-center text-emerald-500 shadow-sm">
                        <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>

                    <h2 class="text-2xl font-serif font-bold text-slate-800 tracking-tight mb-2">
                        Erfolgreich abgemeldet
                    </h2>

                    <p class="text-xs uppercase tracking-widest text-emerald-600 font-bold mb-4">
                        Widerspruch nach § 7 Abs. 3 UWG / Art. 21 DSGVO
                    </p>

                    <p class="text-sm text-slate-600 leading-relaxed mb-6">
                        @if($alreadyUnsubscribed)
                            Deine E-Mail-Adresse <strong>{{ $customer->email }}</strong> war bereits von automatischen Warenkorb-Erinnerungen abgemeldet.
                        @else
                            Wir haben deine E-Mail-Adresse <strong>{{ $customer->email }}</strong> erfolgreich aus dem Verteiler für automatische Warenkorb-Erinnerungen ausgetragen.
                        @endif
                        <br><br>
                        Dein Kundenkonto und eventuell gespeicherte Warenkörbe bleiben selbstverständlich unverändert erhalten.
                    </p>

                    <div class="space-y-3">
                        <a href="{{ route('cart') }}" class="block w-full py-3 px-4 bg-[var(--theme-color,#d1b464)] hover:opacity-95 text-white font-bold rounded-xl text-sm transition-all shadow-md">
                            Zum Warenkorb zurückkehren
                        </a>

                        <a href="{{ url('/') }}" class="block w-full py-3 px-4 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl text-sm transition-colors">
                            Zurück zum Shop
                        </a>
                    </div>

                    <div class="mt-8 pt-6 border-t border-slate-100 text-xs text-slate-400">
                        Möchtest du auch allgemeine Newsletter verwalten?
                        <a href="{{ route('newsletter.page') }}" class="text-[var(--theme-color,#d1b464)] underline hover:text-slate-600 ml-1">
                            Zur Newsletter-Verwaltung
                        </a>
                    </div>
                </div>
            @else
                {{-- Fehlerfall --}}
                <div class="text-center">
                    <div class="w-16 h-16 mx-auto mb-6 rounded-full bg-rose-50 border border-rose-200 flex items-center justify-center text-rose-500 shadow-sm">
                        <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </div>

                    <h2 class="text-2xl font-serif font-bold text-slate-800 tracking-tight mb-2">
                        Hinweis
                    </h2>

                    <p class="text-sm text-slate-600 leading-relaxed mb-6">
                        {{ $message ?? 'Dieser Abmeldelink ist ungültig oder wurde bereits verwendet.' }}
                    </p>

                    <a href="{{ url('/') }}" class="block w-full py-3 px-4 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl text-sm transition-colors">
                        Zurück zur Startseite
                    </a>
                </div>
            @endif

        </div>
    </div>
</x-layouts.frontend_layout>
