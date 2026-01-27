<div>
    <div class="min-h-screen bg-gray-50 flex flex-col justify-center py-12 sm:px-6 lg:px-8">
        <div class="sm:mx-auto sm:w-full sm:max-w-md">
            <h2 class="text-center text-3xl font-serif font-bold text-gray-900">
                Angebot {{ $quote->quote_number }}
            </h2>
        </div>

        <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
            <div class="bg-white py-8 px-4 shadow sm:rounded-lg sm:px-10 border border-gray-100">

                @if($success)
                    <div class="text-center">
                        <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-green-100 mb-4">
                            <svg class="h-6 w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                        </div>
                        <h3 class="text-lg leading-6 font-medium text-gray-900">Vielen Dank!</h3>
                        <p class="mt-2 text-sm text-gray-500">
                            Sie haben das Angebot erfolgreich angenommen. Wir haben Ihnen soeben eine Bestellbestätigung per E-Mail gesendet.
                        </p>
                        <div class="mt-6">
                            <a href="/" class="text-primary hover:underline font-bold">Zur Startseite</a>
                        </div>
                    </div>
                @elseif($error)
                    <div class="text-center">
                        <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100 mb-4">
                            <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </div>
                        <h3 class="text-lg leading-6 font-medium text-gray-900">Nicht möglich</h3>
                        <p class="mt-2 text-sm text-gray-500">
                            {{ $error }}
                        </p>
                        <div class="mt-6">
                            <a href="/kontakt" class="text-primary hover:underline font-bold">Kontakt aufnehmen</a>
                        </div>
                    </div>
                @else
                    <div>
                        <p class="text-gray-600 mb-6 text-center">
                            Hallo <strong>{{ $quote->first_name }} {{ $quote->last_name }}</strong>,<br>
                            möchten Sie das Angebot über <strong>{{ number_format($quote->gross_total / 100, 2, ',', '.') }} €</strong> verbindlich annehmen?
                        </p>

                        <button wire:click="acceptQuote" wire:loading.attr="disabled" class="w-full flex justify-center py-3 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary transition-colors">
                            <span wire:loading.remove>Ja, Angebot jetzt zahlungspflichtig annehmen</span>
                            <span wire:loading>Verarbeite...</span>
                        </button>

                        <p class="mt-4 text-xs text-gray-400 text-center">
                            Durch Klick auf den Button entsteht eine zahlungspflichtige Bestellung gemäß unserer AGB.
                        </p>
                    </div>
                @endif

            </div>
        </div>
    </div>
</div>
