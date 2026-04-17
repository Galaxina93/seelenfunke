<div style="--theme-color: {{ $this->themeColorHex }}; --theme-color-5: {{ $this->themeColorHex }}0D; --theme-color-10: {{ $this->themeColorHex }}1A; --theme-color-15: {{ $this->themeColorHex }}26; --theme-color-20: {{ $this->themeColorHex }}33; --theme-color-30: {{ $this->themeColorHex }}4D; --theme-color-40: {{ $this->themeColorHex }}66; --theme-color-50: {{ $this->themeColorHex }}80; --theme-color-70: {{ $this->themeColorHex }}B3;" class="space-y-6">

    {{-- EDUCATIONAL SECTION --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-2">
        <div class="bg-gray-900/50 border border-gray-800 rounded-3xl p-6 lg:p-8 backdrop-blur-sm shadow-xl">
            <h3 class="text-white font-serif font-bold text-lg mb-4 flex items-center gap-2">
                <x-heroicon-o-light-bulb class="w-6 h-6 text-[var(--theme-color)]" />
                Tipps zum Umgang mit Widerrufen
            </h3>
            <ul class="space-y-4 text-sm text-gray-400">
                <li class="flex items-start gap-3">
                    <span class="mt-0.5 bg-gray-800 p-1 rounded-md text-[var(--theme-color)]"><x-heroicon-s-check class="w-4 h-4" /></span>
                    <div>
                        <strong class="text-gray-200 block mb-0.5">Sonderanfertigungen sind ausgeschlossen</strong>
                        Ist ein Gravur- oder Personalisierungsauftrag bereits in Produktion oder versendet, kannst du den Widerruf mit Verweis auf § 312g Abs. 2 Nr. 1 BGB rechtmäßig ablehnen. Lass dich nicht unter Druck setzen.
                    </div>
                </li>
                <li class="flex items-start gap-3">
                    <span class="mt-0.5 bg-gray-800 p-1 rounded-md text-[var(--theme-color)]"><x-heroicon-s-check class="w-4 h-4" /></span>
                    <div>
                        <strong class="text-gray-200 block mb-0.5">Teil-Widerrufe prüfen</strong>
                        Oft widerrufen Kunden nur einen Teil der Bestellung. Achte darauf, dass du beim Erfassen im System (Gutschrifterstellung) auch wirklich nur die retournierten Positionen gutschreibst.
                    </div>
                </li>
            </ul>
        </div>
        <div class="bg-gray-900/50 border border-gray-800 rounded-3xl p-6 lg:p-8 backdrop-blur-sm shadow-xl">
            <h3 class="text-white font-serif font-bold text-lg mb-4 flex items-center gap-2">
                <x-heroicon-o-exclamation-triangle class="w-6 h-6 text-emerald-500" />
                Zusätzliche Hinweise
            </h3>
            <ul class="space-y-4 text-sm text-gray-400">
                <li class="flex items-start gap-3">
                    <span class="mt-0.5 bg-gray-800 p-1 rounded-md text-emerald-500"><x-heroicon-o-document-text class="w-4 h-4" /></span>
                    <div>
                        <strong class="text-gray-200 block mb-0.5">Eingangsbestätigung per E-Mail</strong>
                        Der Kunde hat durch das System automatisch eine Eingangsbestätigung per E-Mail erhalten (was rechtlich vollkommen ausreicht). Du musst diesen Eingang nicht noch einmal manuell bestätigen!
                    </div>
                </li>
                <li class="flex items-start gap-3">
                    <span class="mt-0.5 bg-gray-800 p-1 rounded-md text-emerald-500"><x-heroicon-s-arrows-right-left class="w-4 h-4" /></span>
                    <div>
                        <strong class="text-gray-200 block mb-0.5">Retourenlabel beilegen?</strong>
                        Bietest du kostenlose Rücksendungen an, vergiss nicht, in deiner Kommunikation mit dem Kunden direkt das DHL-Label als PDF-Anhang in der Mail mitzusenden.
                    </div>
                </li>
            </ul>
        </div>
    </div>


    <!-- LIST VIEW: WORKFLOW STYLE -->
    <div class="mt-8">
        <h2 class="text-xl font-bold mb-4 text-white flex items-center gap-2">
            <i class="solar-clipboard-list-bold-duotone text-[var(--theme-color)]"></i>
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
                    $statusColor = 'text-gray-400 border-gray-700 bg-gray-800/50';
                    if ($isRefunded) {
                        $statusLabel = 'Vollständig Erledigt';
                        $statusColor = 'text-emerald-400 bg-emerald-500/10 border-emerald-500/30';
                    } elseif ($isDeclined) {
                        $statusLabel = 'Widerruf Abgelehnt';
                        $statusColor = 'text-rose-400 bg-rose-500/10 border-rose-500/30';
                    } elseif ($isCustomerNotified) {
                        $statusLabel = 'Kunde informiert';
                        $statusColor = 'text-amber-400 bg-amber-500/10 border-amber-500/30';
                    } elseif ($isLegalChecked) {
                        $statusLabel = 'Prüfung abgeschlossen';
                        $statusColor = 'text-blue-400 bg-blue-500/10 border-blue-500/30';
                    } else {
                        $statusLabel = 'Neu Eingegangen';
                        $statusColor = 'text-red-400 bg-red-500/10 border-red-500/30';
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
                @endphp

                <div x-data="{ expanded: false }" class="bg-gray-900 border border-gray-800 rounded-2xl p-5 hover:border-[var(--theme-color-40)] transition-all flex flex-col shadow-inner {{ $isDone ? 'opacity-60' : '' }}">
                    <div class="flex flex-col sm:flex-row justify-between sm:items-center gap-4 w-full cursor-pointer" @click="expanded = !expanded">
                        <div class="pl-2 flex-1">
                            <div class="flex items-center gap-2 mb-1">
                                <i class="bi bi-chevron-down text-gray-500 transition-transform" :class="expanded ? 'rotate-180' : ''"></i>
                                <h3 class="font-bold text-gray-100 text-lg line-clamp-1">Bestellung {{ $revocation->order_number }}</h3>
                                <span class="text-[10px] px-2 py-0.5 rounded-full uppercase tracking-widest font-bold border {{ $statusColor }}">
                                    {{ $statusLabel }}
                                </span>
                            </div>
                            <p class="text-xs text-gray-400 ml-7">
                                Kunde: <span class="font-bold text-white">{{ $revocation->name }}</span> ({{ $revocation->email }})
                            </p>
                        </div>

                        <div class="flex items-center gap-2 pl-7 sm:pl-0 shrink-0">
                            @if(!$isDone)
                                <button wire:click.stop="deleteRevocation('{{ $revocation->id }}')" wire:confirm="Soll dieser Widerruf unwiderruflich gelöscht werden?" class="btn btn-sm bg-gray-800 hover:bg-gray-700 text-gray-400 hover:text-red-500 p-2.5 rounded-lg shadow-lg flex items-center justify-center transition-colors" title="Löschen">
                                    <x-heroicon-o-trash class="w-4 h-4" />
                                </button>
                            @endif
                        </div>
                    </div>

                    <!-- Smart Progress Bar -->
                    <div class="mt-4 mb-2 pl-2 pr-2 w-full">
                        <div class="flex justify-between items-end mb-1">
                            <span class="text-xs font-semibold text-gray-400">Prozessfortschritt</span>
                            <span class="text-xs font-bold {{ $barTextColor }}">{{ $progressPercent }}%</span>
                        </div>
                        <div class="w-full bg-gray-950 rounded-full h-2 border border-gray-800 overflow-hidden relative shadow-inner">
                            <div class="h-full {{ $barBgColor }} transition-all duration-500" style="width: {{ $progressPercent }}%"></div>
                        </div>
                    </div>

                    <!-- Steps Checklist -->
                    <div x-show="expanded" x-collapse>
                        <div class="mt-4 space-y-3 border-t border-gray-800 pt-5 w-full pl-2 pr-2">
                            <div class="flex justify-between items-start mb-3 bg-gray-950 p-4 rounded-xl border border-gray-800">
                                <div>
                                    <span class="text-[10px] font-bold text-[var(--theme-color)] uppercase tracking-widest block mb-1">Widerrufene Artikel</span>
                                    <div class="text-sm text-gray-300">{{ $revocation->items ?: 'Gesamte Bestellung' }}</div>
                                </div>
                                <div class="text-right">
                                    <span class="text-[10px] font-bold text-gray-500 uppercase tracking-widest block mb-1">Anhänge</span>
                                    @if(!empty($revocation->attachments) && is_array($revocation->attachments) && count($revocation->attachments) > 0)
                                        <div class="flex flex-col gap-1 items-end">
                                            @foreach($revocation->attachments as $attachment)
                                                @php $fileName = basename($attachment); @endphp
                                                <a href="{{ route('admin.widerruf.file', ['revocation' => $revocation->id, 'fileName' => $fileName]) }}" target="_blank" class="inline-flex items-center gap-1.5 px-2 py-1 bg-gray-800 hover:bg-gray-700 text-[10px] font-medium text-gray-300 rounded border border-gray-700 transition-colors" title="{{ $fileName }}">
                                                    <i class="bi bi-paperclip"></i>
                                                    <span class="truncate max-w-[150px]">{{ $fileName }}</span>
                                                </a>
                                            @endforeach
                                        </div>
                                    @else
                                        <div class="text-[10px] text-gray-500 italic">Keine Anhänge</div>
                                    @endif
                                </div>
                            </div>

                            <!-- STEP 1: Auto -->
                            <div class="flex items-start gap-3 p-3 rounded-xl transition-colors opacity-50 bg-gray-950 border border-gray-800/50">
                                <div class="mt-1 shrink-0">
                                    <i class="solar-check-circle-bold text-emerald-500 text-2xl drop-shadow-[0_0_8px_rgba(16,185,129,0.3)]"></i>
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
                            <div class="flex items-start gap-3 p-3 rounded-xl transition-colors {{ $isLegalChecked ? 'opacity-50 bg-gray-950 border border-gray-800/50' : 'bg-gray-900 border border-gray-700 shadow-sm' }}">
                                <div class="mt-1 shrink-0">
                                    @if($isLegalChecked)
                                        <i class="solar-check-circle-bold text-emerald-500 text-2xl drop-shadow-[0_0_8px_rgba(16,185,129,0.3)]"></i>
                                    @else
                                        <i class="solar-danger-circle-bold-duotone text-red-500 text-2xl"></i>
                                    @endif
                                </div>
                                <div class="flex-1">
                                    <p class="text-sm font-semibold {{ $isLegalChecked ? 'line-through text-gray-500' : 'text-gray-200' }}">Rechtliche Prüfung</p>
                                    @if(!$isLegalChecked)
                                        <div class="mt-2 text-[11px] text-gray-400">
                                            Handelt es sich um personalisierte Waren (§ 312g)? Oder ein normales Standard-Produkt?
                                        </div>
                                        <div class="mt-3 flex flex-wrap gap-2">
                                            <button wire:click="markLegalCheck('{{ $revocation->id }}', 'standard')" class="bg-blue-500/10 hover:bg-blue-500 text-blue-400 hover:text-white border border-blue-500/30 px-3 py-1.5 rounded text-[10px] font-black uppercase tracking-widest transition-colors flex items-center gap-1.5">
                                                <i class="solar-box-bold-duotone"></i> Standard
                                            </button>
                                            <button wire:click="markLegalCheck('{{ $revocation->id }}', 'personalized')" class="bg-rose-500/10 hover:bg-rose-500 text-rose-400 hover:text-white border border-rose-500/30 px-3 py-1.5 rounded text-[10px] font-black uppercase tracking-widest transition-colors flex items-center gap-1.5">
                                                <i class="solar-magic-stick-3-bold-duotone"></i> Personalisiert
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
                                            <button wire:click.stop="undoLegalCheck('{{ $revocation->id }}')" class="text-red-500 hover:text-red-400 text-[9px] font-bold uppercase tracking-widest flex items-center gap-1 transition-colors"><i class="bi bi-x-circle"></i> Lösen</button>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <!-- STEP 3: Mail an Kunde -->
                            <div class="flex items-start gap-3 p-3 rounded-xl transition-colors {{ !$isLegalChecked ? 'opacity-30 pointer-events-none' : ($isCustomerNotified ? 'opacity-50 bg-gray-950 border border-gray-800/50' : 'bg-gray-900 border border-gray-700 shadow-sm') }}">
                                <div class="mt-1 shrink-0">
                                    @if($isCustomerNotified)
                                        <i class="solar-check-circle-bold text-emerald-500 text-2xl drop-shadow-[0_0_8px_rgba(16,185,129,0.3)]"></i>
                                    @else
                                        <i class="solar-letter-opened-bold-duotone text-amber-500 text-2xl"></i>
                                    @endif
                                </div>
                                <div class="flex-1 w-full overflow-hidden">
                                    <p class="text-sm font-semibold {{ $isCustomerNotified ? 'line-through text-gray-500' : 'text-gray-200' }}">Mail an Kunde</p>
                                    
                                    @if($isLegalChecked && !$isCustomerNotified)
                                        <div class="mt-2 text-[11px] text-gray-400">
                                            Sende dem Kunden nun eine E-Mail mit dem Rücksende-Label. Alternativ kannst du den Widerruf formal ablehnen, falls es sich um personalisierte Ware handelt.
                                        </div>
                                        <div class="mt-3 flex flex-wrap justify-end gap-2" x-data="{ rejectMode: false, rejectReason: '{{ $revocation->product_type === 'personalized' ? 'personalized' : 'other' }}' }">
                                            <template x-if="!rejectMode">
                                                <div class="flex gap-2">
                                                    <button @click="rejectMode = true" class="bg-rose-500/10 hover:bg-rose-500 text-rose-400 hover:text-white border border-rose-500/30 px-3 py-1.5 rounded text-[10px] font-black uppercase tracking-widest transition-colors flex items-center gap-1.5">
                                                        <i class="solar-close-circle-bold-duotone"></i> Widerruf ablehnen
                                                    </button>
                                                    
                                                    <button wire:click="markCustomerNotified('{{ $revocation->id }}')" class="bg-amber-500/10 hover:bg-amber-500 text-amber-400 hover:text-white border border-amber-500/30 px-3 py-1.5 rounded text-[10px] font-black uppercase tracking-widest transition-colors flex items-center gap-1.5">
                                                        <i class="bi bi-send-check"></i> Label gesendet
                                                    </button>
                                                </div>
                                            </template>
                                            
                                            <template x-if="rejectMode">
                                                <div class="flex flex-wrap items-center gap-2 bg-gray-900 border border-rose-500/30 p-1.5 rounded-lg w-full justify-end">
                                                    <select x-model="rejectReason" class="bg-gray-800 text-gray-300 border-gray-700 text-[11px] rounded px-2 py-1.5 focus:border-rose-500 focus:ring focus:ring-rose-500/20 font-medium">
                                                        <option value="personalized">Personalisierter Artikel</option>
                                                        <option value="damaged">Ware beschädigt / Gebrauchsspuren</option>
                                                        <option value="expired">14-Tage Frist abgelaufen</option>
                                                        <option value="other">Sonstiges</option>
                                                    </select>
                                                    <button @click="$wire.rejectRevocation('{{ $revocation->id }}', rejectReason)" class="bg-rose-600 hover:bg-rose-500 text-white shadow-[0_0_10px_rgba(225,29,72,0.3)] border border-rose-400/30 px-3 py-1.5 rounded text-[10px] font-black uppercase tracking-widest transition-all gap-1.5 flex items-center">
                                                        <i class="bi bi-send-x"></i> Senden
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
                                            <button wire:click.stop="undoCustomerNotified('{{ $revocation->id }}')" class="text-amber-500 hover:text-amber-400 text-[9px] font-bold uppercase tracking-widest flex items-center gap-1 transition-colors"><i class="bi bi-arrow-counterclockwise"></i> Zurücksetzen</button>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <!-- STEP 4: Abwicklung / Erstattung -->
                            <div class="flex items-start gap-3 p-3 rounded-xl transition-colors {{ !$isCustomerNotified ? 'opacity-30 pointer-events-none' : ($isDone ? 'opacity-50 bg-gray-950 border ' . ($isDeclined ? 'border-rose-500/20' : 'border-emerald-500/20') : 'bg-gray-900 border border-emerald-500/50 shadow-[0_0_15px_rgba(16,185,129,0.1)]') }}">
                                <div class="mt-1 shrink-0">
                                    @if($isRefunded)
                                        <i class="solar-check-circle-bold text-emerald-500 text-2xl drop-shadow-[0_0_8px_rgba(16,185,129,0.3)]"></i>
                                    @elseif($isDeclined)
                                        <i class="solar-danger-circle-bold-duotone text-rose-500 text-2xl"></i>
                                    @else
                                        <i class="solar-wallet-bold-duotone text-emerald-500 text-2xl animate-pulse"></i>
                                    @endif
                                </div>
                                <div class="flex-1 w-full overflow-hidden">
                                    <p class="text-sm font-semibold {{ $isRefunded ? 'line-through text-gray-500' : ($isDeclined ? 'text-rose-400' : 'text-emerald-400') }}">Endgültiger Abschluss</p>
                                    
                                    @if($isCustomerNotified && !$isDone)
                                        <div class="mt-2 text-[11px] text-gray-400">
                                            Warte, bis die Retoure physisch eingetroffen ist. Erstelle dann eine Gutschrift und schließe diesen Vorgang final ab.
                                        </div>
                                        <div class="mt-3 flex justify-end">
                                            <button wire:click="markAsProcessed('{{ $revocation->id }}')" wire:confirm="Wurde die Retoure ordnungsgemäß abgewickelt und das Geld gutgeschrieben?" class="bg-emerald-600 hover:bg-emerald-500 text-white shadow-[0_0_15px_rgba(16,185,129,0.3)] border border-emerald-400/30 px-3 py-1.5 rounded text-[10px] font-black uppercase tracking-widest transition-all gap-1.5 flex items-center">
                                                <i class="bi bi-check-circle-fill"></i> Abwicklung abgeschlossen
                                            </button>
                                        </div>
                                    @elseif($isDone)
                                        <div class="flex items-center justify-between mt-1">
                                            @if($isDeclined)
                                                <div class="text-[10px] text-rose-500/70 font-mono">Status: Abgelehnt / Geschlossen</div>
                                                <button wire:click.stop="markAsPending('{{ $revocation->id }}')" class="text-rose-500 hover:text-rose-400 text-[9px] font-bold uppercase tracking-widest flex items-center gap-1 transition-colors"><i class="bi bi-arrow-counterclockwise"></i> Öffnen</button>
                                            @else
                                                <div class="text-[10px] text-emerald-500/70 font-mono">Status: Erledigt / Erstattet</div>
                                                <button wire:click.stop="markAsPending('{{ $revocation->id }}')" class="text-emerald-500 hover:text-emerald-400 text-[9px] font-bold uppercase tracking-widest flex items-center gap-1 transition-colors"><i class="bi bi-arrow-counterclockwise"></i> Zurücksetzen</button>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            @empty
                <div class="p-8 text-center text-gray-500 bg-gray-900/30 rounded-xl border border-dashed border-gray-800">
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

</div>
