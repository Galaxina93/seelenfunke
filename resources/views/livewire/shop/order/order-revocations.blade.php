<div x-data="{ lightboxUrl: null, lightboxName: '', lightboxIsPdf: false }"
     @keydown.escape.window="lightboxUrl = null"
     style="--theme-color: {{ $this->themeColorHex }}; --theme-color-5: {{ $this->themeColorHex }}0D; --theme-color-10: {{ $this->themeColorHex }}1A; --theme-color-15: {{ $this->themeColorHex }}26; --theme-color-20: {{ $this->themeColorHex }}33; --theme-color-30: {{ $this->themeColorHex }}4D; --theme-color-40: {{ $this->themeColorHex }}66; --theme-color-50: {{ $this->themeColorHex }}80; --theme-color-70: {{ $this->themeColorHex }}B3;"
     class="space-y-6 text-gray-700 dark:text-gray-300">

    {{-- EDUCATIONAL SECTION --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-2">
        <div class="bg-white dark:bg-gray-900/50 border border-gray-200 dark:border-gray-800 rounded-3xl p-6 lg:p-8 backdrop-blur-sm shadow-sm dark:shadow-xl">
            <h3 class="text-gray-900 dark:text-white font-serif font-bold text-lg mb-4 flex items-center gap-2">
                <x-heroicon-o-light-bulb class="w-6 h-6 text-[var(--theme-color)]" />
                Tipps zum Umgang mit Widerrufen
            </h3>
            <ul class="space-y-4 text-sm text-gray-600 dark:text-gray-400">
                <li class="flex items-start gap-3">
                    <span class="mt-0.5 bg-gray-100 dark:bg-gray-800 p-1 rounded-md text-[var(--theme-color)]"><x-heroicon-s-check class="w-4 h-4" /></span>
                    <div>
                        <strong class="text-gray-900 dark:text-gray-200 block mb-0.5">Sonderanfertigungen sind ausgeschlossen</strong>
                        Ist ein Gravur- oder Personalisierungsauftrag bereits in Produktion oder versendet, kannst du den Widerruf mit Verweis auf § 312g Abs. 2 Nr. 1 BGB rechtmäßig ablehnen. Lass dich nicht unter Druck setzen.
                    </div>
                </li>
                <li class="flex items-start gap-3">
                    <span class="mt-0.5 bg-gray-100 dark:bg-gray-800 p-1 rounded-md text-[var(--theme-color)]"><x-heroicon-s-check class="w-4 h-4" /></span>
                    <div>
                        <strong class="text-gray-900 dark:text-gray-200 block mb-0.5">Teil-Widerrufe prüfen</strong>
                        Oft widerrufen Kunden nur einen Teil der Bestellung. Achte darauf, dass du beim Erfassen im System (Gutschrifterstellung) auch wirklich nur die retournierten Positionen gutschreibst.
                    </div>
                </li>
            </ul>
        </div>
        <div class="bg-white dark:bg-gray-900/50 border border-gray-200 dark:border-gray-800 rounded-3xl p-6 lg:p-8 backdrop-blur-sm shadow-sm dark:shadow-xl">
            <h3 class="text-gray-900 dark:text-white font-serif font-bold text-lg mb-4 flex items-center gap-2">
                <x-heroicon-o-exclamation-triangle class="w-6 h-6 text-emerald-500" />
                Zusätzliche Hinweise
            </h3>
            <ul class="space-y-4 text-sm text-gray-600 dark:text-gray-400">
                <li class="flex items-start gap-3">
                    <span class="mt-0.5 bg-gray-100 dark:bg-gray-800 p-1 rounded-md text-emerald-600 dark:text-emerald-500"><x-heroicon-o-document-text class="w-4 h-4" /></span>
                    <div>
                        <strong class="text-gray-900 dark:text-gray-200 block mb-0.5">Eingangsbestätigung per E-Mail</strong>
                        Der Kunde hat durch das System automatisch eine Eingangsbestätigung per E-Mail erhalten (was rechtlich vollkommen ausreicht). Du musst diesen Eingang nicht noch einmal manuell bestätigen!
                    </div>
                </li>
                <li class="flex items-start gap-3">
                    <span class="mt-0.5 bg-gray-100 dark:bg-gray-800 p-1 rounded-md text-emerald-600 dark:text-emerald-500"><x-heroicon-s-arrows-right-left class="w-4 h-4" /></span>
                    <div>
                        <strong class="text-gray-900 dark:text-gray-200 block mb-0.5">Retourenlabel beilegen?</strong>
                        Bietest du kostenlose Rücksendungen an, vergiss nicht, in deiner Kommunikation mit dem Kunden direkt das DHL-Label als PDF-Anhang in der Mail mitzusenden.
                    </div>
                </li>
            </ul>
        </div>
    </div>


    <!-- LIST VIEW: WORKFLOW STYLE -->
    <div class="mt-8">
        <h2 class="text-xl font-bold mb-4 text-gray-900 dark:text-white flex items-center gap-2">
            <x-heroicon-o-clipboard-document-list class="w-6 h-6 text-[var(--theme-color)]" />
            Widerrufsprotokolle
        </h2>

        <div class="grid gap-4">
            @forelse($revocations as $revocation)
                @php
                    $isAutoConfirmed = true; 
                    $isLegalChecked = !is_null($revocation->legal_check_at);
                    $isCustomerNotified = !is_null($revocation->customer_notified_at);
                    $isRefunded = $revocation->status === 'processed';
                    $isDeclined = $revocation->status === 'declined';
                    $isDone = $isRefunded || $isDeclined;

                    // Calculation of progress
                    $totalItems = 4;
                    $completedItems = 1;
                    if ($isLegalChecked) $completedItems++;
                    if ($isCustomerNotified) $completedItems++;
                    if ($isDone) $completedItems++;

                    $progressPercent = round(($completedItems / $totalItems) * 100);

                    // Dynamic Status color for the header
                    $statusLabel = 'Eingang (Auto)';
                    $statusColor = 'text-gray-600 dark:text-gray-400 border-gray-200 dark:border-gray-700 bg-gray-100 dark:bg-gray-800/50';
                    if ($isRefunded) {
                        $statusLabel = 'Vollständig Erledigt';
                        $statusColor = 'text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-500/10 border-emerald-200 dark:border-emerald-500/30';
                    } elseif ($isDeclined) {
                        $statusLabel = 'Widerruf Abgelehnt';
                        $statusColor = 'text-rose-600 dark:text-rose-400 bg-rose-50 dark:bg-rose-500/10 border-rose-200 dark:border-rose-500/30';
                    } elseif ($isCustomerNotified) {
                        $statusLabel = 'Kunde informiert';
                        $statusColor = 'text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-500/10 border-amber-200 dark:border-amber-500/30';
                    } elseif ($isLegalChecked) {
                        $statusLabel = 'Prüfung abgeschlossen';
                        $statusColor = 'text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-500/10 border-blue-200 dark:border-blue-500/30';
                    } else {
                        $statusLabel = 'Neu Eingegangen';
                        $statusColor = 'text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-500/10 border-red-200 dark:border-red-500/30';
                    }

                    // Dynamic Progress Bar Color
                    $barTextColor = 'text-red-400';
                    $barBgColor = 'bg-gradient-to-r from-red-600 to-red-400 shadow-[0_0_8px_rgba(239,68,68,0.5)]';
                    if ($progressPercent >= 100) {
                        $barTextColor = 'text-emerald-400';
                        $barBgColor = 'bg-gradient-to-r from-emerald-500 to-emerald-400 shadow-[0_0_8px_rgba(16,185,129,0.5)]';
                    } elseif ($progressPercent > 50) {
                        $barTextColor = 'text-amber-400';
                        $barBgColor = 'bg-gradient-to-r from-amber-500 to-amber-400 shadow-[0_0_8px_rgba(245,158,11,0.5)]';
                    }

                    $order = $revocation->linked_order;
                    $emailMatchesOrder = false;
                    if ($order) {
                        $revEmail = trim((string) $revocation->email);
                        $ordEmail = trim((string) $order->email);
                        $custEmail = trim((string) ($order->customer->email ?? ''));
                        $emailMatchesOrder = $revEmail !== '' && (strcasecmp($revEmail, $ordEmail) === 0 || ($custEmail !== '' && strcasecmp($revEmail, $custEmail) === 0));
                    }
                @endphp

                <div x-data="{ expanded: false }" class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-5 hover:border-[var(--theme-color-40)] transition-all flex flex-col shadow-sm dark:shadow-inner {{ $isDone ? 'opacity-60' : '' }}">
                    <div class="flex flex-col sm:flex-row justify-between sm:items-center gap-4 w-full cursor-pointer" @click="expanded = !expanded">
                        <div class="pl-2 flex-1">
                            <div class="flex items-center gap-2 mb-1 flex-wrap">
                                <x-heroicon-o-chevron-down class="w-4 h-4 text-gray-500 transition-transform duration-200 shrink-0" ::class="expanded ? 'rotate-180' : ''" />
                                <h3 class="font-bold text-gray-900 dark:text-gray-100 text-lg line-clamp-1">Bestellung {{ $revocation->order_number }}</h3>
                                <span class="text-[10px] px-2 py-0.5 rounded-full uppercase tracking-widest font-bold border {{ $statusColor }}">
                                    {{ $statusLabel }}
                                </span>
                                @if($order)
                                    <a href="{{ route('admin.orders', ['search' => $order->order_number]) }}" 
                                       class="inline-flex items-center gap-1 text-[11px] font-semibold px-2 py-0.5 rounded bg-amber-500/10 text-amber-600 dark:text-amber-400 hover:underline border border-amber-500/20" 
                                       title="Zur Bestellung springen ({{ number_format($order->total_price / 100, 2, ',', '.') }} €)" 
                                       @click.stop>
                                        <x-heroicon-o-arrow-top-right-on-square class="w-3.5 h-3.5" />
                                        Order #{{ $order->order_number }} ({{ number_format($order->total_price / 100, 2, ',', '.') }} €)
                                    </a>
                                    @if(!$emailMatchesOrder)
                                        <span class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded bg-amber-500/15 text-amber-700 dark:text-amber-300 border border-amber-500/30" title="Widerrufs-E-Mail ({{ $revocation->email }}) weicht von Bestell-E-Mail ({{ $order->email }}) ab">
                                            <x-heroicon-o-exclamation-triangle class="w-3.5 h-3.5" /> Abweichende E-Mail
                                        </span>
                                    @endif
                                    @php
                                        $revOrderSparks = app(\App\Services\Gamification\GamificationService::class)->calculateOrderSparks($order);
                                    @endphp
                                    @if($revOrderSparks > 0)
                                        <span class="inline-flex items-center gap-1 text-[10px] font-mono font-bold px-2 py-0.5 rounded {{ $isRefunded ? 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20' : 'bg-amber-500/10 text-amber-700 dark:text-amber-300 border border-amber-500/20' }}">
                                            ✨ {{ $isRefunded ? '-' : '' }}{{ number_format($revOrderSparks, 0, ',', '.') }} Funken {{ $isRefunded ? '(abgezogen)' : '(Widerrufs-Abzug)' }}
                                        </span>
                                    @endif
                                @else
                                    <span class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 border border-gray-300 dark:border-gray-700" title="Zur angegebenen Nummer wurde keine Bestellung im System gefunden">
                                        <x-heroicon-o-question-mark-circle class="w-3.5 h-3.5" /> Keine Order gefunden
                                    </span>
                                @endif
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-400 ml-6">
                                Kunde: <span class="font-bold text-gray-900 dark:text-white">{{ $revocation->name }}</span> ({{ $revocation->email }})
                            </p>
                        </div>

                        <div class="flex items-center gap-2 pl-6 sm:pl-0 shrink-0">
                            @if(!$isDone)
                                <button wire:click.stop="deleteRevocation('{{ $revocation->id }}')" wire:confirm="Soll dieser Widerruf unwiderruflich gelöscht werden?" class="btn btn-sm bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-500 dark:text-gray-400 hover:text-red-500 p-2.5 rounded-lg shadow-sm dark:shadow-lg flex items-center justify-center transition-colors" title="Löschen">
                                    <x-heroicon-o-trash class="w-4 h-4" />
                                </button>
                            @endif
                        </div>
                    </div>

                    <!-- Smart Progress Bar -->
                    <div class="mt-4 mb-2 pl-2 pr-2 w-full">
                        <div class="flex justify-between items-end mb-1">
                            <span class="text-xs font-semibold text-gray-500 dark:text-gray-400">Prozessfortschritt</span>
                            <span class="text-xs font-bold {{ $barTextColor }}">{{ $progressPercent }}%</span>
                        </div>
                        <div class="w-full bg-gray-100 dark:bg-gray-950 rounded-full h-2 border border-gray-200 dark:border-gray-800 overflow-hidden relative shadow-none dark:shadow-inner">
                            <div class="h-full {{ $barBgColor }} transition-all duration-500" style="width: {{ $progressPercent }}%"></div>
                        </div>
                    </div>

                    <!-- Steps Checklist -->
                    <div x-show="expanded" x-collapse>
                        <div class="mt-4 space-y-3 border-t border-gray-200 dark:border-gray-800 pt-5 w-full pl-2 pr-2">
                            <div class="flex justify-between items-start mb-3 bg-gray-50 dark:bg-gray-950 p-4 rounded-xl border border-gray-200 dark:border-gray-800">
                                <div>
                                    <span class="text-[10px] font-bold text-[var(--theme-color)] uppercase tracking-widest block mb-1">Widerrufene Artikel</span>
                                    <div class="text-sm text-gray-700 dark:text-gray-300">{{ $revocation->items ?: 'Gesamte Bestellung' }}</div>
                                </div>
                                <div class="text-right">
                                    <span class="text-[10px] font-bold text-gray-500 uppercase tracking-widest block mb-1">Anhänge</span>
                                    @if(!empty($revocation->attachments) && is_array($revocation->attachments) && count($revocation->attachments) > 0)
                                        <div class="flex flex-col gap-1 items-end">
                                            @foreach($revocation->attachments as $attachment)
                                                @php
                                                    $fileName = basename($attachment);
                                                    $fileUrl = route('admin.widerruf.file', ['revocation' => $revocation->id, 'fileName' => $fileName]);
                                                    $isPdfFile = strtolower(pathinfo($fileName, PATHINFO_EXTENSION)) === 'pdf';
                                                @endphp
                                                <button type="button"
                                                        @click.stop="lightboxUrl = @js($fileUrl); lightboxName = @js($fileName); lightboxIsPdf = {{ $isPdfFile ? 'true' : 'false' }}"
                                                        class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-[10px] font-medium text-gray-700 dark:text-gray-300 rounded border border-gray-200 dark:border-gray-700 transition-colors cursor-zoom-in"
                                                        title="Beleg in Lightbox öffnen ({{ $fileName }})">
                                                    <x-heroicon-o-paper-clip class="w-3.5 h-3.5 text-[var(--theme-color)] shrink-0" />
                                                    <span class="truncate max-w-[160px]">{{ $fileName }}</span>
                                                </button>
                                            @endforeach
                                        </div>
                                    @else
                                        <div class="text-[10px] text-gray-500 italic">Keine Anhänge</div>
                                    @endif
                                </div>
                            </div>

                            <!-- STEP 1: Auto -->
                            <div class="flex items-start gap-3 p-3 rounded-xl transition-colors opacity-50 bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800/50">
                                <div class="mt-0.5 shrink-0">
                                    <x-heroicon-s-check-circle class="w-6 h-6 text-emerald-500" />
                                </div>
                                <div class="flex-1 w-full overflow-hidden">
                                    <p class="text-sm font-semibold line-through text-gray-500">Bestätigung (Auto)</p>
                                    <div class="flex items-center justify-between mt-1">
                                        <div class="text-[10px] text-gray-500 font-mono">Eingegangen am: {{ $revocation->created_at->format('d.m.Y H:i') }}</div>
                                        <span class="text-[10px] text-emerald-500 font-bold uppercase tracking-widest">Bestätigungs-Mail versendet</span>
                                    </div>
                                </div>
                            </div>

                            <!-- STEP 2: Rechtliche Prüfung -->
                            <div class="flex items-start gap-3 p-3 rounded-xl transition-colors {{ $isLegalChecked ? 'opacity-50 bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800/50' : 'bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 shadow-sm' }}">
                                <div class="mt-0.5 shrink-0">
                                    @if($isLegalChecked)
                                        <x-heroicon-s-check-circle class="w-6 h-6 text-emerald-500" />
                                    @else
                                        <x-heroicon-o-exclamation-circle class="w-6 h-6 text-red-500" />
                                    @endif
                                </div>
                                <div class="flex-1">
                                    <p class="text-sm font-semibold {{ $isLegalChecked ? 'line-through text-gray-400 dark:text-gray-500' : 'text-gray-900 dark:text-gray-200' }}">Rechtliche Prüfung</p>
                                    @if(!$isLegalChecked)
                                        <div class="mt-2 text-[11px] text-gray-600 dark:text-gray-400">
                                            Handelt es sich um personalisierte Waren (§ 312g)? Oder ein normales Standard-Produkt?
                                        </div>
                                        <div class="mt-3 flex flex-wrap gap-2">
                                            <button wire:click="markLegalCheck('{{ $revocation->id }}', 'standard')" class="bg-blue-500/10 hover:bg-blue-500 text-blue-500 dark:text-blue-400 hover:text-white border border-blue-500/30 px-3 py-1.5 rounded text-[10px] font-black uppercase tracking-widest transition-colors flex items-center gap-1.5">
                                                <x-heroicon-o-cube class="w-3.5 h-3.5" /> Standard
                                            </button>
                                            <button wire:click="markLegalCheck('{{ $revocation->id }}', 'personalized')" class="bg-rose-500/10 hover:bg-rose-500 text-rose-500 dark:text-rose-400 hover:text-white border border-rose-500/30 px-3 py-1.5 rounded text-[10px] font-black uppercase tracking-widest transition-colors flex items-center gap-1.5">
                                                <x-heroicon-o-sparkles class="w-3.5 h-3.5" /> Personalisiert
                                            </button>
                                        </div>
                                    @else
                                        <div class="flex items-center justify-between mt-1">
                                            <div class="text-[10px] text-gray-500 font-mono flex items-center gap-2">
                                                <span>Geprüft am: <span class="text-blue-400">{{ $revocation->legal_check_at->format('d.m.Y H:i') }}</span></span>
                                                @if($revocation->product_type === 'personalized')
                                                    <span class="bg-rose-500/10 border border-rose-500/20 text-rose-400 px-1.5 py-0.5 rounded text-[9px] uppercase tracking-widest font-bold">Personalisierte Ware</span>
                                                @elseif($revocation->product_type === 'standard')
                                                    <span class="bg-blue-500/10 border border-blue-500/20 text-blue-400 px-1.5 py-0.5 rounded text-[9px] uppercase tracking-widest font-bold">Standard Produkt</span>
                                                @endif
                                            </div>
                                            <button wire:click.stop="undoLegalCheck('{{ $revocation->id }}')" class="text-red-500 hover:text-red-400 text-[9px] font-bold uppercase tracking-widest flex items-center gap-1 transition-colors"><x-heroicon-o-x-circle class="w-3.5 h-3.5" /> Lösen</button>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <!-- STEP 3: Mail an Kunde -->
                            <div class="flex items-start gap-3 p-3 rounded-xl transition-colors {{ !$isLegalChecked ? 'opacity-30 pointer-events-none' : ($isCustomerNotified ? 'opacity-50 bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800/50' : 'bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 shadow-sm') }}">
                                <div class="mt-0.5 shrink-0">
                                    @if($isCustomerNotified)
                                        <x-heroicon-s-check-circle class="w-6 h-6 text-emerald-500" />
                                    @else
                                        <x-heroicon-o-envelope-open class="w-6 h-6 text-amber-500" />
                                    @endif
                                </div>
                                <div class="flex-1 w-full overflow-hidden">
                                    <p class="text-sm font-semibold {{ $isCustomerNotified ? 'line-through text-gray-400 dark:text-gray-500' : 'text-gray-900 dark:text-gray-200' }}">Mail an Kunde</p>
                                    
                                    @if($isLegalChecked && !$isCustomerNotified)
                                        <div class="mt-2 text-[11px] text-gray-600 dark:text-gray-400">
                                            Sende dem Kunden nun eine E-Mail mit dem Rücksende-Label. Alternativ kannst du den Widerruf formal ablehnen, falls es sich um personalisierte Ware handelt.
                                        </div>
                                        <div class="mt-3 flex flex-wrap justify-end gap-2" x-data="{ rejectMode: false, rejectReason: '{{ $revocation->product_type === 'personalized' ? 'personalized' : 'other' }}' }">
                                            <template x-if="!rejectMode">
                                                <div class="flex gap-2">
                                                    <button @click="rejectMode = true" class="bg-rose-500/10 hover:bg-rose-500 text-rose-500 dark:text-rose-400 hover:text-white border border-rose-500/30 px-3 py-1.5 rounded text-[10px] font-black uppercase tracking-widest transition-colors flex items-center gap-1.5">
                                                        <x-heroicon-o-x-circle class="w-3.5 h-3.5" /> Widerruf ablehnen
                                                    </button>
                                                    
                                                    <button wire:click="markCustomerNotified('{{ $revocation->id }}')" class="bg-amber-500/10 hover:bg-amber-500 text-amber-600 dark:text-amber-400 hover:text-white border border-amber-500/30 px-3 py-1.5 rounded text-[10px] font-black uppercase tracking-widest transition-colors flex items-center gap-1.5">
                                                        <x-heroicon-o-paper-airplane class="w-3.5 h-3.5" /> Label gesendet
                                                    </button>
                                                </div>
                                            </template>
                                            
                                            <template x-if="rejectMode">
                                                <div class="flex flex-wrap items-center gap-2 bg-white dark:bg-gray-900 border border-rose-200 dark:border-rose-500/30 p-1.5 rounded-lg w-full justify-end">
                                                    <select x-model="rejectReason" class="bg-gray-100 dark:bg-gray-800 text-gray-800 dark:text-gray-300 border-gray-300 dark:border-gray-700 text-[11px] rounded px-2 py-1.5 focus:border-rose-500 focus:ring focus:ring-rose-500/20 font-medium">
                                                        <option value="personalized">Personalisierter Artikel</option>
                                                        <option value="damaged">Ware beschädigt / Gebrauchsspuren</option>
                                                        <option value="expired">14-Tage Frist abgelaufen</option>
                                                        <option value="other">Sonstiges</option>
                                                    </select>
                                                    <button @click="$wire.rejectRevocation('{{ $revocation->id }}', rejectReason)" class="bg-rose-600 hover:bg-rose-500 text-white shadow-[0_0_10px_rgba(225,29,72,0.3)] border border-rose-400/30 px-3 py-1.5 rounded text-[10px] font-black uppercase tracking-widest transition-all gap-1.5 flex items-center">
                                                        <x-heroicon-o-paper-airplane class="w-3.5 h-3.5" /> Senden
                                                    </button>
                                                    <button @click="rejectMode = false" class="text-gray-500 hover:text-gray-300 px-2 py-1.5 rounded text-[10px] font-bold uppercase tracking-widest transition-colors">
                                                        Abbrechen
                                                    </button>
                                                </div>
                                            </template>
                                        </div>
                                    @elseif($isCustomerNotified)
                                        <div class="flex items-center justify-between mt-1">
                                            <div class="text-[10px] text-gray-500 font-mono">Info gesendet am: {{ $revocation->customer_notified_at->format('d.m.Y H:i') }}</div>
                                            <button wire:click.stop="undoCustomerNotified('{{ $revocation->id }}')" class="text-amber-500 hover:text-amber-400 text-[9px] font-bold uppercase tracking-widest flex items-center gap-1 transition-colors"><x-heroicon-o-arrow-uturn-left class="w-3.5 h-3.5" /> Zurücksetzen</button>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <!-- STEP 4: Abwicklung / Erstattung -->
                            <div class="flex items-start gap-3 p-3 rounded-xl transition-colors {{ !$isCustomerNotified ? 'opacity-30 pointer-events-none' : ($isDone ? 'opacity-50 bg-gray-50 dark:bg-gray-950 border ' . ($isDeclined ? 'border-rose-200 dark:border-rose-500/20' : 'border-emerald-200 dark:border-emerald-500/20') : 'bg-gray-50 dark:bg-gray-900 border border-emerald-300 dark:border-emerald-500/50 shadow-sm dark:shadow-[0_0_15px_rgba(16,185,129,0.1)]') }}">
                                <div class="mt-0.5 shrink-0">
                                    @if($isRefunded)
                                        <x-heroicon-s-check-circle class="w-6 h-6 text-emerald-500" />
                                    @elseif($isDeclined)
                                        <x-heroicon-o-x-circle class="w-6 h-6 text-rose-500" />
                                    @else
                                        <x-heroicon-o-banknotes class="w-6 h-6 text-emerald-500 animate-pulse" />
                                    @endif
                                </div>
                                <div class="flex-1 w-full overflow-hidden">
                                    <p class="text-sm font-semibold {{ $isRefunded ? 'line-through text-gray-500' : ($isDeclined ? 'text-rose-400' : 'text-emerald-600 dark:text-emerald-400') }}">Endgültiger Abschluss</p>
                                    
                                    @if($isCustomerNotified && !$isDone)
                                        <div class="mt-2 text-[11px] text-gray-600 dark:text-gray-400">
                                            Warte, bis die Retoure physisch eingetroffen ist. Mit Klick auf Abschluss wird die verknüpfte Bestellung samt Rechnung automatisch GoBD-konform storniert und der Kunde per E-Mail informiert.
                                        </div>
                                        <div class="mt-3 flex justify-end">
                                            <button wire:click="markAsProcessed('{{ $revocation->id }}')" wire:confirm="Wurde die Retoure ordnungsgemäß geprüft und das Geld erstattet?" class="bg-emerald-600 hover:bg-emerald-500 text-white shadow-[0_0_15px_rgba(16,185,129,0.3)] border border-emerald-400/30 px-3 py-1.5 rounded text-[10px] font-black uppercase tracking-widest transition-all gap-1.5 flex items-center">
                                                <x-heroicon-s-check-circle class="w-4 h-4" /> Abwicklung abgeschlossen
                                            </button>
                                        </div>
                                    @elseif($isDone)
                                        <div class="flex items-center justify-between mt-1">
                                            @if($isDeclined)
                                                <div class="text-[10px] text-rose-500/70 font-mono">Status: Abgelehnt / Geschlossen</div>
                                                <button wire:click.stop="markAsPending('{{ $revocation->id }}')" class="text-rose-500 hover:text-rose-400 text-[9px] font-bold uppercase tracking-widest flex items-center gap-1 transition-colors"><x-heroicon-o-arrow-uturn-left class="w-3.5 h-3.5" /> Öffnen</button>
                                            @else
                                                <div class="text-[10px] text-emerald-500/70 font-mono">Status: Erledigt / Erstattet</div>
                                                <button wire:click.stop="markAsPending('{{ $revocation->id }}')" class="text-emerald-500 hover:text-emerald-400 text-[9px] font-bold uppercase tracking-widest flex items-center gap-1 transition-colors"><x-heroicon-o-arrow-uturn-left class="w-3.5 h-3.5" /> Zurücksetzen</button>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            @empty
                <div class="p-8 text-center text-gray-500 bg-gray-50 dark:bg-gray-900/30 rounded-xl border border-dashed border-gray-200 dark:border-gray-800">
                    <x-heroicon-o-face-smile class="w-12 h-12 mx-auto mb-3 opacity-30" />
                    <p class="font-serif italic text-lg text-gray-400">Bisher keine Widerrufe eingegangen.</p>
                </div>
            @endforelse
        </div>

        @if($revocations->hasPages())
            <div class="mt-6">
                {{ $revocations->links('pagination::tailwind') }}
            </div>
        @endif
    </div>

    {{-- LIGHTBOX MODAL FÜR BELEGE & BILDER (IM SELBEN FENSTER) --}}
    <div x-show="lightboxUrl"
         x-cloak
         style="display: none;"
         x-transition.opacity
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4 sm:p-6"
         @click.self="lightboxUrl = null">
        <div class="relative max-w-5xl w-full flex flex-col items-center">
            <div class="w-full flex items-center justify-between mb-3 px-2">
                <span class="text-xs font-mono text-gray-300 truncate max-w-md" x-text="lightboxName"></span>
                <div class="flex items-center gap-2">
                    <a :href="lightboxUrl" download class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-white/10 hover:bg-white/20 text-white text-xs font-bold transition-colors">
                        <x-heroicon-o-arrow-down-tray class="w-4 h-4" /> Herunterladen
                    </a>
                    <button type="button" @click="lightboxUrl = null" class="p-1.5 rounded-lg bg-white/10 hover:bg-rose-600 text-white transition-colors" title="Schließen">
                        <x-heroicon-o-x-mark class="w-5 h-5" />
                    </button>
                </div>
            </div>
            <template x-if="lightboxUrl && !lightboxIsPdf">
                <img :src="lightboxUrl" :alt="lightboxName" class="max-w-full max-h-[82vh] object-contain rounded-2xl shadow-2xl border border-gray-800 bg-gray-900">
            </template>
            <template x-if="lightboxUrl && lightboxIsPdf">
                <iframe :src="lightboxUrl" class="w-full h-[82vh] rounded-2xl shadow-2xl border border-gray-800 bg-white"></iframe>
            </template>
        </div>
    </div>

</div>
