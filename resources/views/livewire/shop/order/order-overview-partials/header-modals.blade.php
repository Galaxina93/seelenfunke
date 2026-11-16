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
        $modalPreviewItem = $this->previewItem ?: $selectedOrder->items->first();
    @endphp

    <div x-cloak
         x-show="headerModal !== null"
         style="display: none;"
         class="fixed inset-0 z-[110] flex items-start sm:items-center justify-center bg-black/50 dark:bg-black/65 backdrop-blur-sm p-4 pt-[8vh] sm:pt-4 overflow-y-auto"
         @keydown.escape.window="closeHeaderModalClient()">
        <div @click.outside="closeHeaderModalClient()"
             :class="headerModal === 'files' ? 'max-w-2xl' : 'max-w-lg'"
             class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-3xl shadow-2xl w-full p-6 sm:p-7 relative my-auto space-y-5">

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
                        @click="closeHeaderModalClient()"
                        class="p-2 rounded-xl text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors cursor-pointer">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            {{-- 1. DATEIEN & DOWNLOADS MODAL --}}
            <div x-show="headerModal === 'files'" class="space-y-4">
                @if($selectedOrder->items->count() > 1)
                    <div class="flex items-center gap-2 overflow-x-auto custom-scrollbar pb-1">
                        @foreach($selectedOrder->items as $mItem)
                            @php $isSelMItem = $modalPreviewItem && (string) $mItem->id === (string) $modalPreviewItem->id; @endphp
                            <button type="button"
                                    wire:click="selectItemForPreview('{{ $mItem->id }}')"
                                    class="px-3 py-1.5 rounded-xl text-[10px] font-black uppercase tracking-wider border transition-all cursor-pointer shrink-0 {{ $isSelMItem
                                        ? 'bg-[var(--theme-color)] text-gray-950 border-[var(--theme-color)]'
                                        : 'bg-gray-50 dark:bg-gray-950 text-gray-700 dark:text-gray-300 border-gray-200 dark:border-gray-800 hover:border-[var(--theme-color-40)]' }}">
                                {{ $mItem->quantity }}&times; {{ $mItem->product_name }}
                            </button>
                        @endforeach
                    </div>
                @endif

                @php
                    $isModalCustomFields = $modalPreviewItem && (
                        (($modalPreviewItem->configuration['personalization_mode'] ?? null) === 'custom_fields')
                        || !empty($modalPreviewItem->configuration['custom_fields_data'])
                    );
                    $customFieldsModalClean = $modalPreviewItem ? array_filter($modalPreviewItem->configuration['custom_fields_data'] ?? [], function ($cfd) {
                        if (!is_array($cfd)) return false;
                        $v = trim((string) ($cfd['value'] ?? ''));
                        return $v !== '' && ($cfd['type'] ?? 'text') !== 'font' && !in_array(mb_strtolower($v), ['kein symbol', 'keine', 'nein', '-'], true);
                    }) : [];
                    $hasModalCustomContent = $isModalCustomFields && !empty($customFieldsModalClean);

                    $modalFiles = $modalPreviewItem ? ($modalPreviewItem->configuration['files'] ?? []) : [];
                    $modalLogoPath = $modalPreviewItem ? ($modalPreviewItem->configuration['logo_storage_path'] ?? null) : null;
                    $modalSnapshotPath = $modalPreviewItem ? ($modalPreviewItem->configuration['snapshot_path'] ?? null) : null;
                    $modalBackTexts = $modalPreviewItem
                        ? array_filter($modalPreviewItem->configuration['texts_back'] ?? [], fn ($t) => is_array($t) && trim((string) ($t['text'] ?? '')) !== '')
                        : [];
                    $hasModalBackLogo = $modalPreviewItem && !$isModalCustomFields && !empty($modalPreviewItem->configuration['logos_back']);
                    $hasModalBackContent = !$isModalCustomFields && (!empty($modalBackTexts) || $hasModalBackLogo);

                    $allModalFiles = [];

                    if ($modalPreviewItem) {
                        $isVoucherItem = ($modalPreviewItem->product->slug ?? '') === 'geschenkgutschein' || (!empty($modalPreviewItem->configuration['is_gift_voucher']));
                        if ($isVoucherItem) {
                            $giftVouchers = \App\Models\Marketing\MarketingGiftVoucher::where('order_item_id', $modalPreviewItem->id)->get();
                            foreach ($giftVouchers as $gv) {
                                $allModalFiles[] = [
                                    'label' => 'Laser-Vorlage 86×54mm Goldkarte (' . $gv->code . ')',
                                    'download_url' => route('customer.gift-voucher.download', ['voucher' => $gv->id, 'format' => 'laser_svg']),
                                    'ext' => 'svg',
                                ];
                                $allModalFiles[] = [
                                    'label' => 'Gutschein PDF (' . $gv->code . ')',
                                    'download_url' => route('customer.gift-voucher.download', $gv->id),
                                    'ext' => 'pdf',
                                ];
                            }
                        }
                    }

                    if ($modalLogoPath) {
                        $allModalFiles[] = [
                            'label' => 'Kunden-Logo',
                            'path' => $modalLogoPath,
                            'ext' => pathinfo($modalLogoPath, PATHINFO_EXTENSION),
                            'is_customer_file' => true,
                        ];
                    }

                    foreach ($modalFiles as $mf) {
                        $allModalFiles[] = [
                            'label' => basename($mf),
                            'path' => $mf,
                            'ext' => pathinfo($mf, PATHINFO_EXTENSION),
                            'is_customer_file' => true,
                        ];
                    }

                    if (!empty($modalSnapshotPath)) {
                        if (is_array($modalSnapshotPath)) {
                            foreach ($modalSnapshotPath as $sideKey => $sPath) {
                                if ($sideKey === 'back' && !$hasModalBackContent) {
                                    continue;
                                }
                                array_unshift($allModalFiles, [
                                    'label' => 'Sicherung (' . ($sideKey === 'front' ? 'Vorderseite' : ($sideKey === 'back' ? 'Rückseite' : ucfirst($sideKey))) . ')',
                                    'path' => $sPath,
                                    'ext' => pathinfo($sPath, PATHINFO_EXTENSION),
                                    'is_customer_file' => false,
                                ]);
                            }
                        } else {
                            array_unshift($allModalFiles, [
                                'label' => 'Sicherung (Konfiguration)',
                                'path' => $modalSnapshotPath,
                                'ext' => pathinfo($modalSnapshotPath, PATHINFO_EXTENSION),
                                'is_customer_file' => false,
                            ]);
                        }
                    }

                    $frontTextsModal = $modalPreviewItem
                        ? array_filter($modalPreviewItem->configuration['texts'] ?? [], fn ($t) => is_array($t) && trim((string) ($t['text'] ?? '')) !== '')
                        : [];
                    $hasFrontLogoModal = $modalPreviewItem && !$isModalCustomFields && (!empty($modalPreviewItem->configuration['logos']) || !empty($modalPreviewItem->configuration['logo_storage_path']));
                    $hasFrontExport = $modalPreviewItem && (!empty($frontTextsModal) || $hasFrontLogoModal || $hasModalCustomContent);
                @endphp

                <div class="space-y-2.5 max-h-[50vh] overflow-y-auto custom-scrollbar pr-1">
                    @forelse($allModalFiles as $fObj)
                        @php
                            $isCustFile = !empty($fObj['is_customer_file']);
                            $fPath = $fObj['path'] ?? '';
                            $fLabel = $fObj['label'] ?? '';
                            $pIssue = $modalPreviewItem->configuration['photo_issue'] ?? null;
                            $hasPIssue = $isCustFile && $pIssue && (($pIssue['file'] ?? '') === $fPath || ($pIssue['file_name'] ?? '') === $fLabel);
                            $isPResolved = $hasPIssue && !empty($pIssue['resolved']);
                        @endphp
                        <div class="flex items-center justify-between gap-3 p-3 rounded-2xl border border-gray-200 dark:border-gray-800 bg-gray-50/70 dark:bg-gray-950/70">
                            <div class="flex items-center gap-3 min-w-0">
                                <span class="w-10 h-10 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 flex items-center justify-center text-[10px] font-black uppercase text-[var(--theme-color)] shrink-0">
                                    {{ $fObj['ext'] ?: 'FILE' }}
                                </span>
                                <div class="min-w-0">
                                    <div class="text-xs font-bold text-gray-900 dark:text-white truncate">{{ $fLabel }}</div>
                                    @if($hasPIssue && !$isPResolved)
                                        <div class="text-[10px] font-bold text-amber-600 dark:text-amber-400">Nachbesserung angefordert: {{ $pIssue['reason'] ?? '' }}</div>
                                    @elseif($isPResolved)
                                        <div class="text-[10px] font-bold text-emerald-600 dark:text-emerald-400">✓ Nachgebessert</div>
                                    @endif
                                </div>
                            </div>

                            <div class="flex items-center gap-1.5 shrink-0">
                                @if($isCustFile && $modalPreviewItem)
                                    <button type="button"
                                            wire:click="openPhotoIssueModal('{{ $modalPreviewItem->id }}', '{{ $fPath }}', '{{ $fLabel }}')"
                                            title="Foto bemängeln / Nachbesserung anfordern"
                                            class="p-2 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-gray-500 hover:text-[var(--theme-color)] hover:border-[var(--theme-color)] transition-colors cursor-pointer">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                    </button>
                                    @if($hasPIssue && !$isPResolved)
                                        <button type="button"
                                                wire:click="resolvePhotoIssue('{{ $modalPreviewItem->id }}')"
                                                title="Mangel als erledigt markieren"
                                                class="p-2 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-emerald-600 hover:bg-emerald-500 hover:text-white transition-colors cursor-pointer">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                        </button>
                                    @endif
                                @endif

                                <a href="{{ $fObj['download_url'] ?? asset('storage/' . $fPath) }}"
                                   @if(empty($fObj['download_url'])) download="{{ $fLabel . '.' . $fObj['ext'] }}" @endif
                                   target="_blank"
                                   title="Herunterladen"
                                   class="p-2 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-gray-700 dark:text-gray-300 hover:bg-[var(--theme-color)] hover:text-gray-950 hover:border-[var(--theme-color)] transition-colors">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                </a>
                            </div>
                        </div>
                    @empty
                        <div class="py-6 text-center text-xs text-gray-500 dark:text-gray-400">
                            Keine Kunden-Uploads für diese Position hinterlegt.
                        </div>
                    @endforelse
                </div>

                @if($modalPreviewItem && ($hasFrontExport || $hasModalBackContent))
                    <div class="pt-3 border-t border-gray-200 dark:border-gray-800 space-y-2">
                        @if($hasFrontExport)
                            <div class="grid grid-cols-1 {{ $hasFrontLogoModal ? 'sm:grid-cols-3' : 'sm:grid-cols-2' }} gap-2">
                                <button type="button"
                                        wire:click.prevent="downloadLaserXcsFile('{{ $modalPreviewItem->id }}', 'front')"
                                        class="w-full flex items-center justify-center gap-1.5 px-3 py-2.5 rounded-xl bg-[var(--theme-color)] text-gray-950 text-[10px] font-black uppercase tracking-wider cursor-pointer">
                                    <span>xTool Projekt (.xcs)</span>
                                </button>
                                <button type="button"
                                        wire:click.prevent="downloadLaserFile('{{ $modalPreviewItem->id }}', 'front')"
                                        class="w-full flex items-center justify-center gap-1.5 px-3 py-2.5 rounded-xl bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-white border border-gray-200 dark:border-gray-700 text-[10px] font-black uppercase tracking-wider hover:border-[var(--theme-color)] cursor-pointer">
                                    <span>Laser-SVG</span>
                                </button>
                                @if($hasFrontLogoModal)
                                    <button type="button"
                                            wire:click.prevent="downloadLaserPngFile('{{ $modalPreviewItem->id }}', 'front')"
                                            class="w-full flex items-center justify-center gap-1.5 px-3 py-2.5 rounded-xl bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-white border border-gray-200 dark:border-gray-700 text-[10px] font-black uppercase tracking-wider hover:border-[var(--theme-color)] cursor-pointer">
                                        <span>Kontur-PNG</span>
                                    </button>
                                @endif
                            </div>
                        @endif

                        @if($hasModalBackContent)
                            <div class="grid grid-cols-1 {{ $hasModalBackLogo ? 'sm:grid-cols-3' : 'sm:grid-cols-2' }} gap-2">
                                <button type="button"
                                        wire:click.prevent="downloadLaserXcsFile('{{ $modalPreviewItem->id }}', 'back')"
                                        class="w-full flex items-center justify-center gap-1.5 px-3 py-2.5 rounded-xl bg-[var(--theme-color)] text-gray-950 text-[10px] font-black uppercase tracking-wider cursor-pointer">
                                    <span>.xcs (Rückseite)</span>
                                </button>
                                <button type="button"
                                        wire:click.prevent="downloadLaserFile('{{ $modalPreviewItem->id }}', 'back')"
                                        class="w-full flex items-center justify-center gap-1.5 px-3 py-2.5 rounded-xl bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-white border border-gray-200 dark:border-gray-700 text-[10px] font-black uppercase tracking-wider hover:border-[var(--theme-color)] cursor-pointer">
                                    <span>SVG (Rückseite)</span>
                                </button>
                                @if($hasModalBackLogo)
                                    <button type="button"
                                            wire:click.prevent="downloadLaserPngFile('{{ $modalPreviewItem->id }}', 'back')"
                                            class="w-full flex items-center justify-center gap-1.5 px-3 py-2.5 rounded-xl bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-white border border-gray-200 dark:border-gray-700 text-[10px] font-black uppercase tracking-wider hover:border-[var(--theme-color)] cursor-pointer">
                                        <span>PNG (Rückseite)</span>
                                    </button>
                                @endif
                            </div>
                        @endif
                    </div>
                @endif
            </div>

            {{-- 2. KOSTENÜBERSICHT MODAL --}}
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
