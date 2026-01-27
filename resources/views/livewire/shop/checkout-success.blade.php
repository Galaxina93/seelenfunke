<div>
    <div class="bg-white min-h-screen flex items-center justify-center px-4">
        <div class="max-w-md w-full text-center space-y-6">
            <div class="mx-auto w-24 h-24 bg-green-100 rounded-full flex items-center justify-center mb-6 animate-fade-in-up">
                <svg class="w-12 h-12 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
            </div>

            <h1 class="text-3xl font-serif font-bold text-gray-900">Vielen Dank!</h1>
            <p class="text-gray-500">Deine Bestellung war erfolgreich. Wir haben dir eine Bestätigung per E-Mail gesendet.</p>

            <div class="pt-6">
                <a href="{{ route('home') }}" class="inline-block bg-primary text-white px-8 py-3 rounded-full font-bold hover:bg-primary-dark transition shadow-lg shadow-primary/30">
                    Zurück zum Shop
                </a>
            </div>
        </div>
    </div>
</div>
