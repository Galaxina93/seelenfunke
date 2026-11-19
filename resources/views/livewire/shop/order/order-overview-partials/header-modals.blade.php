@if($selectedOrder)
    @php
        $bModal = is_array($selectedOrder->billing_address) ? $selectedOrder->billing_address : [];
        $modalFullName = trim(($bModal['first_name'] ?? '') . ' ' . ($bModal['last_name'] ?? ''));
        $modalCityZip = trim(($bModal['postal_code'] ?? '') . ' ' . ($bModal['city'] ?? ''));
        $billingDisplayName = $modalFullName !== '' ? $modalFullName : ($selectedOrder->customer_name ?? 'Gast');
        $rawBillingCompany = trim((string) ($bModal['company'] ?? ''));
        $cleanBillingCompany = \App\Models\Order\OrderOrder::isRedundantCompanyName($rawBillingCompany, $billingDisplayName)
            ? ''
            : $rawBillingCompany;

        $modalBilling = [
            'name' => $billingDisplayName,
            'company' => $cleanBillingCompany,
            'address' => trim(($bModal['address'] ?? '') . (!empty($bModal['address_addition']) ? ' ' . $bModal['address_addition'] : '')),
            'city_zip' => $modalCityZip,
            'country' => $bModal['country'] ?? 'DE',
            'email' => $selectedOrder->email ?? ($bModal['email'] ?? ''),
        ];

        $modalShipping = is_array($selectedOrder->shipping_address) ? $selectedOrder->shipping_address : null;
        $shippingDisplayName = $modalShipping
            ? (trim(($modalShipping['first_name'] ?? '') . ' ' . ($modalShipping['last_name'] ?? '')) ?: $modalBilling['name'])
            : $modalBilling['name'];
        $rawShippingCompany = $modalShipping ? trim((string) ($modalShipping['company'] ?? '')) : $cleanBillingCompany;
        $cleanShippingCompany = \App\Models\Order\OrderOrder::isRedundantCompanyName($rawShippingCompany, $shippingDisplayName)
            ? ''
            : $rawShippingCompany;

        $isShippingDifferent = $selectedOrder->hasDifferentShippingAddress();
    @endphp

    <div x-cloak
         x-show="headerModal !== null"
         style="display: none;"
         @click.self="closeHeaderModalClient()"
         class="fixed inset-0 z-[110] flex items-start sm:items-center justify-center bg-black/50 dark:bg-black/65 backdrop-blur-sm p-4 pt-[8vh] sm:pt-4 overflow-y-auto"
         @keydown.escape.window="closeHeaderModalClient()">
        <div @click.outside="closeHeaderModalClient()"
             class="max-w-lg bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-3xl shadow-2xl w-full p-6 sm:p-7 relative my-auto space-y-5">

            {{-- Modal Header --}}
            <div class="flex items-center justify-between gap-3 pb-4 border-b border-gray-200 dark:border-gray-800">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-10 h-10 rounded-xl bg-[var(--theme-color-10)] border border-[var(--theme-color-30)] text-[var(--theme-color)] flex items-center justify-center shrink-0">
                        <svg x-show="headerModal === 'files'" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
                        <svg x-show="headerModal === 'costs'" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14.25 7.756a4.5 4.5 0 100 8.488M7.5 10.5h5.25m-5.25 3h5.25M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <svg x-show="headerModal === 'customer'" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        <svg x-show="headerModal === 'shipping'" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    </div>
                    <div class="min-w-0">
                        <h3 class="text-base sm:text-lg font-serif font-bold text-gray-900 dark:text-white truncate">
                            <span x-show="headerModal === 'files'">Dateien &amp; Downloads</span>
                            <span x-show="headerModal === 'costs'">Kostenübersicht</span>
                            <span x-show="headerModal === 'customer'">Kunden Infos</span>
                            <span x-show="headerModal === 'shipping'">Lieferadresse</span>
                        </h3>
                        <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500 dark:text-gray-400">
                            Auftrag #{{ $selectedOrder->order_number }}
                        </p>
                    </div>
                </div>

                <button type="button"
                        @click.stop="closeHeaderModalClient()"
                        title="Schließen"
                        class="p-2 rounded-xl text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors cursor-pointer">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            {{-- Programmatischer Fallback für openHeaderModal('files') delegiert direkt an media-dropdown-card --}}
            <div x-show="headerModal === 'files'" title="xTool Projekt (.xcs)" class="space-y-3">
                @include('livewire.shop.order.order-overview-partials.media-dropdown-card', [
                    'targetItem' => $this->previewItem ?: $selectedOrder->items->first(),
                    'inCockpit' => false,
                    'showItemSwitcher' => true,
                ])
            </div>

            {{-- 1. KOSTENÜBERSICHT MODAL --}}
            <div x-show="headerModal === 'costs'" class="space-y-4">
                <x-shop.cost-summary :model="$selectedOrder" :showTitle="false" design="adaptive" containerOverride="p-4 sm:p-5 rounded-2xl bg-gray-50 dark:bg-gray-950 border border-gray-200/80 dark:border-gray-800" />

                @if($selectedOrder->invoices && $selectedOrder->invoices->isNotEmpty())
                    <div class="space-y-2">
                        <div class="text-[10px] font-black uppercase tracking-widest text-gray-400">Rechnungen &amp; Belege</div>
                        <div class="flex flex-wrap gap-2">
                            @foreach($selectedOrder->invoices as $invoice)
                                <a href="{{ route('invoice.download', $invoice->id) }}"
                                   target="_blank"
                                   class="inline-flex items-center gap-2 px-3 py-2 rounded-xl bg-gray-50 dark:bg-gray-950 border border-gray-200/80 dark:border-gray-800 hover:border-[var(--theme-color)] text-xs font-bold text-gray-800 dark:text-gray-200 hover:text-[var(--theme-color)] transition-colors">
                                    <svg class="w-4 h-4 text-[var(--theme-color)] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                    <span>{{ $invoice->invoice_number }} (PDF)</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            {{-- 3. KUNDEN INFOS MODAL --}}
            <div x-show="headerModal === 'customer'" class="space-y-4">
                <div class="p-4 rounded-2xl bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 space-y-1 text-sm text-gray-700 dark:text-gray-300">
                    <div class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1.5">Rechnungsadresse</div>
                    <div class="font-bold text-gray-900 dark:text-white text-base">{{ $modalBilling['name'] }}</div>
                    @if(!empty($modalBilling['company']))
                        <div class="text-xs font-bold text-[var(--theme-color)]">{{ $modalBilling['company'] }}</div>
                    @endif
                    <div>{{ $modalBilling['address'] }}</div>
                    <div>{{ $modalBilling['city_zip'] }}</div>
                    <div class="text-[10px] font-black uppercase tracking-wider text-gray-500 pt-0.5">{{ $modalBilling['country'] }}</div>
                </div>

                @if(!empty($modalBilling['email']))
                    <div class="p-3.5 rounded-2xl bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <div class="text-[10px] font-black uppercase tracking-widest text-gray-400">E-Mail</div>
                            <a href="mailto:{{ $modalBilling['email'] }}" class="text-xs font-bold text-[var(--theme-color)] hover:underline truncate block">
                                {{ $modalBilling['email'] }}
                            </a>
                        </div>
                        <button type="button"
                                x-data="{ copiedMail: false }"
                                @click="if (navigator.clipboard && window.isSecureContext) { navigator.clipboard.writeText(@js($modalBilling['email'])); } else { const ta = document.createElement('textarea'); ta.value = @js($modalBilling['email']); document.body.appendChild(ta); ta.select(); document.execCommand('copy'); document.body.removeChild(ta); } copiedMail = true; setTimeout(() => copiedMail = false, 1800)"
                                class="px-2.5 py-1.5 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-[10px] font-black uppercase tracking-wider text-gray-700 dark:text-gray-300 hover:border-[var(--theme-color)] cursor-pointer shrink-0">
                            <span x-show="!copiedMail">Kopieren</span>
                            <span x-show="copiedMail" x-cloak class="text-[var(--theme-color)]">✓ Kopiert</span>
                        </button>
                    </div>
                @endif
            </div>

            {{-- 4. LIEFERADRESSE MODAL --}}
            <div x-show="headerModal === 'shipping'" class="space-y-4">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-black uppercase tracking-widest text-gray-400">Empfänger</span>
                    <span class="px-2.5 py-0.5 rounded-lg text-[10px] font-black uppercase tracking-wider border {{ $isShippingDifferent
                        ? 'bg-[var(--theme-color-15)] text-[var(--theme-color)] border-[var(--theme-color-40)]'
                        : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 border-gray-200 dark:border-gray-700' }}">
                        {{ $isShippingDifferent ? 'Abweichend von Rechnung' : 'Identisch mit Rechnung' }}
                    </span>
                </div>

                <div class="p-4 rounded-2xl bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 space-y-1 text-sm text-gray-700 dark:text-gray-300">
                    @if($modalShipping)
                        <div class="font-bold text-gray-900 dark:text-white text-base">
                            {{ $shippingDisplayName }}
                        </div>
                        @if(!empty($cleanShippingCompany))
                            <div class="text-xs font-bold text-[var(--theme-color)]">{{ $cleanShippingCompany }}</div>
                        @endif
                        <div>{{ trim(($modalShipping['address'] ?? '') . (!empty($modalShipping['address_addition']) ? ' ' . $modalShipping['address_addition'] : '')) }}</div>
                        <div>{{ trim(($modalShipping['postal_code'] ?? '') . ' ' . ($modalShipping['city'] ?? '')) }}</div>
                        <div class="text-[10px] font-black uppercase tracking-wider text-gray-500 pt-0.5">{{ $modalShipping['country'] ?? 'DE' }}</div>
                    @else
                        <div class="font-bold text-gray-900 dark:text-white text-base">{{ $modalBilling['name'] }}</div>
                        @if(!empty($modalBilling['company']))
                            <div class="text-xs font-bold text-[var(--theme-color)]">{{ $modalBilling['company'] }}</div>
                        @endif
                        <div>{{ $modalBilling['address'] }}</div>
                        <div>{{ $modalBilling['city_zip'] }}</div>
                        <div class="text-[10px] font-black uppercase tracking-wider text-gray-500 pt-0.5">{{ $modalBilling['country'] }}</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endif
