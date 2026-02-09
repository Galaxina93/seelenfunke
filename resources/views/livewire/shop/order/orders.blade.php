<div class="min-h-screen bg-gray-50 p-4 md:p-6">

    {{-- VIEW 1: BESTELLÜBERSICHT (LISTE) --}}
    @if(!$selectedOrderId)

        @include("livewire.shop.order.orders-partials.header-stats-and-search")

        @include("livewire.shop.order.orders-partials.table")

        {{-- VIEW 2: DETAIL ANSICHT (SPLIT SCREEN) --}}
    @else
        {{-- FIX: Main Container handles split. Mobile: Vertical Stack (col), Desktop: Horizontal (row) --}}
        <div
            class="h-[calc(100vh-3rem)] flex flex-col bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden">

            @include("livewire.shop.order.orders-partials.detail-header")

            {{-- SPLIT CONTENT: Mobile = Column (Stacked), Desktop = Row (Side by Side) --}}
            <div class="flex flex-col lg:flex-row flex-1 overflow-hidden">

                {{-- LINKS: Order Details & Liste (Scrollbar) --}}
                @include('livewire.shop.shared.detail-content', [
                    'model' => $selectedOrder,      // Wir mappen $selectedOrder auf $model
                    'context' => 'order',           // Kontext setzen
                    'selectedItemId' => $selectedOrderItemId
                ])

                {{-- RECHTS: Configurator (FIX: Scrollbar auf den Parent Container) --}}
                @include("livewire.shop.order.orders-partials.right-column")

            </div>
        </div>
    @endif

    {{-- VERSAND-SICHERHEITS-MODAL --}}
    @if($confirmingShipmentId)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4 transition-opacity">
            {{-- Modal Container --}}
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-md overflow-hidden transform transition-all animate-fade-in-up border border-gray-100">

                {{-- HEADER: Goldener Touch --}}
                <div class="bg-primary/5 px-6 py-4 border-b border-primary/10 flex items-center gap-4">
                    <div class="bg-white p-2.5 rounded-full text-primary shadow-sm border border-primary/10">
                        {{-- Icon: Paket / Versand --}}
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-lg font-serif font-bold text-gray-900">Bestellung versenden?</h3>
                        <p class="text-[10px] uppercase tracking-widest text-primary font-bold">Sicherheitsabfrage</p>
                    </div>
                </div>

                {{-- BODY --}}
                <div class="p-6">
                    <p class="text-sm text-gray-600 mb-6 leading-relaxed">
                        Du bist dabei, den Status dieser Bestellung auf <strong class="text-primary">"Versendet"</strong> zu ändern.
                        <br><br>
                        Möchtest du dem Kunden automatisch die <span class="underline decoration-primary/30 decoration-2 underline-offset-2">Versandbestätigung per E-Mail</span> senden?
                    </p>

                    <div class="flex flex-col gap-3">
                        {{-- Button: JA + MAIL --}}
                        <button wire:click="confirmShipment(true)"
                                class="group w-full flex items-center justify-center gap-3 px-4 py-3 bg-primary text-white rounded-lg font-bold hover:bg-primary-dark transition-all shadow-md hover:shadow-lg transform hover:-translate-y-0.5">
                            <svg class="w-5 h-5 text-white/90 group-hover:animate-pulse" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                            <span>Ja, Status ändern & E-Mail senden</span>
                        </button>

                        {{-- Button: NUR STATUS --}}
                        <button wire:click="confirmShipment(false)"
                                class="w-full flex items-center justify-center gap-2 px-4 py-2.5 bg-white border border-gray-200 text-gray-600 rounded-lg font-medium hover:border-primary hover:text-primary hover:bg-primary/5 transition-colors">
                            Nur Status ändern (Keine Mail)
                        </button>
                    </div>
                </div>

                {{-- FOOTER --}}
                <div class="bg-gray-50 px-6 py-3 border-t border-gray-100 flex justify-center">
                    <button wire:click="cancelShipment" class="text-xs font-bold text-gray-400 hover:text-gray-600 uppercase tracking-wider transition-colors">
                        Abbrechen
                    </button>
                </div>
            </div>
        </div>
    @endif

</div>
