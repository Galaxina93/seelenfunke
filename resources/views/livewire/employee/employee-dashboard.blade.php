<div style="--theme-color: #C5A059; --theme-color-10: #C5A0591A; --theme-color-15: #C5A05926; --theme-color-20: #C5A05933; --theme-color-30: #C5A0594D; --theme-color-50: #C5A05980;"
     class="min-h-screen bg-slate-50 dark:bg-gray-950 text-gray-900 dark:text-gray-100 p-3 sm:p-5 lg:p-6 space-y-4 font-sans transition-colors">

    {{-- Feedback --}}
    @if (session()->has('success'))
        <div class="p-3.5 rounded-2xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/30 text-emerald-800 dark:text-emerald-300 text-xs font-bold">
            ✓ {{ session('success') }}
        </div>
    @endif

    @if (session()->has('error'))
        <div class="p-3.5 rounded-2xl bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/30 text-rose-800 dark:text-rose-300 text-xs font-bold">
            {{ session('error') }}
        </div>
    @endif

    @if(!$contract)
        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-8 text-center text-sm font-bold text-gray-500">
            Noch kein aktiver Arbeitsvertrag hinterlegt.
        </div>
    @else
        {{-- ========================================================================= --}}
        {{-- 1. ZENTRALE DATENKACHEL (Profil, Rang/EP, Live-Stempeluhr, Stunden & PDFs) --}}
        {{-- ========================================================================= --}}
        <div class="bg-white dark:bg-gray-900/90 border border-gray-200 dark:border-gray-800 rounded-2xl p-4 sm:p-5 shadow-xs space-y-4">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">

                {{-- Links: Klickbares Profilbild + Name + Rang/Titel + Dokumenten-Buttons --}}
                <div class="flex flex-wrap items-center gap-3.5 min-w-0">
                    <button type="button"
                            wire:click="openOperatorProfileModal"
                            title="Mein Profil öffnen"
                            class="flex items-center gap-3 group cursor-pointer text-left">
                        @if(!empty($operatorProfile['avatar_url']))
                            <img src="{{ $operatorProfile['avatar_url'] }}"
                                 alt="{{ $employee->first_name }}"
                                 class="w-12 h-12 rounded-2xl object-cover border-2 border-[#C5A059] group-hover:scale-105 transition-transform shrink-0" />
                        @else
                            <div class="w-12 h-12 rounded-2xl bg-[#C5A059]/15 border-2 border-[#C5A059] flex items-center justify-center text-sm font-black text-[#C5A059] group-hover:scale-105 transition-transform shrink-0">
                                {{ $operatorProfile['initials'] ?? 'SF' }}
                            </div>
                        @endif

                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h1 class="text-base sm:text-lg font-black text-gray-900 dark:text-white group-hover:text-[#C5A059] transition-colors">
                                    {{ $employee->first_name }} {{ $employee->last_name }}
                                </h1>
                                @if($operatorProfile)
                                    <span class="px-2 py-0.5 rounded-lg bg-[#C5A059]/15 border border-[#C5A059]/40 text-[10px] font-black text-[#C5A059]">
                                        {{ $operatorProfile['rank_class'] }} · {{ $operatorProfile['title_label'] }} ({{ $operatorProfile['ep_total'] }} EP)
                                    </span>
                                @endif
                            </div>
                            <div class="text-xs font-medium text-gray-500 dark:text-gray-400">
                                {{ $contract->job_title }} · {{ $contract->department }}
                            </div>
                        </div>
                    </button>
                </div>

                {{-- Rechts: 1-Klick Stempeluhr & PDF-Schnellzugriff --}}
                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('employee.contract.pdf') }}" target="_blank"
                       class="px-3 py-2 rounded-xl bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-800 dark:text-gray-200 text-xs font-bold transition-colors">
                        Vertrag PDF
                    </a>
                    <a href="{{ route('employee.timesheet.pdf', ['year' => $selectedYear, 'month' => $selectedMonth]) }}" target="_blank"
                       class="px-3 py-2 rounded-xl bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-800 dark:text-gray-200 text-xs font-bold transition-colors">
                        Stundenzettel PDF
                    </a>

                    @if($runningEntry)
                        <span class="px-3 py-2 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/30 text-emerald-700 dark:text-emerald-300 text-xs font-black">
                            ● Aktiv seit {{ $runningEntry->started_at?->format('H:i') }} Uhr
                        </span>
                        <button type="button" wire:click="clockOut"
                                class="px-4 py-2 rounded-xl bg-rose-600 hover:bg-rose-500 text-white font-black text-xs cursor-pointer transition-colors">
                            ⏹ Ausstempeln
                        </button>
                    @else
                        <button type="button" wire:click="clockIn"
                                class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-black text-xs cursor-pointer transition-colors">
                            ▶ Einstempeln
                        </button>
                    @endif
                </div>
            </div>

            {{-- 4 Kompakte Kennzahlen-Kacheln innerhalb der Datenkachel --}}
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-2.5 pt-3 border-t border-gray-100 dark:border-gray-800">
                <div class="p-3 rounded-xl bg-gray-50 dark:bg-gray-950/70 border border-gray-200/70 dark:border-gray-800 flex items-center justify-between gap-2">
                    <span class="text-[11px] font-bold text-gray-500 dark:text-gray-400">Monatsstunden</span>
                    <strong class="text-sm font-black text-gray-900 dark:text-white">{{ $stats['worked_hours_formatted'] ?? '0,0 Std.' }}</strong>
                </div>

                <div class="p-3 rounded-xl bg-gray-50 dark:bg-gray-950/70 border border-gray-200/70 dark:border-gray-800 flex items-center justify-between gap-2">
                    <span class="text-[11px] font-bold text-gray-500 dark:text-gray-400">Resturlaub</span>
                    <strong class="text-sm font-black text-gray-900 dark:text-white">{{ $contract->remaining_vacation_days }} / {{ $contract->vacation_days_per_year }} Tg.</strong>
                </div>

                <div class="p-3 rounded-xl bg-gray-50 dark:bg-gray-950/70 border border-gray-200/70 dark:border-gray-800 flex items-center justify-between gap-2">
                    <span class="text-[11px] font-bold text-gray-500 dark:text-gray-400">Meine Aufgaben</span>
                    <strong class="text-sm font-black text-[#C5A059]">{{ $assignedOpenCount }} offen</strong>
                </div>

                <button type="button"
                        wire:click="openOperatorProfileModal"
                        class="p-3 rounded-xl bg-gray-50 dark:bg-gray-950/70 border border-gray-200/70 dark:border-gray-800 hover:border-[#C5A059] flex items-center justify-between gap-2 cursor-pointer transition-colors text-left">
                    <span class="text-[11px] font-bold text-gray-500 dark:text-gray-400">Score & Profil</span>
                    <strong class="text-sm font-black text-emerald-600 dark:text-emerald-400">{{ $operatorProfile['efficiency_score'] ?? 100 }} %</strong>
                </button>
            </div>
        </div>

        {{-- ========================================================================= --}}
        {{-- MODUL-NAVIGATION (Start = Übersicht mit Daten- & Ticketkachel)            --}}
        {{-- ========================================================================= --}}
        <div class="flex flex-wrap items-center gap-2">
            <button type="button" wire:click="switchTab('overview')"
                    class="px-4 py-2 rounded-xl text-xs font-black border transition cursor-pointer {{ $activeTab === 'overview' ? 'bg-gray-900 text-white border-gray-900 dark:bg-[#C5A059] dark:text-gray-950 dark:border-[#C5A059]' : 'bg-white dark:bg-gray-900 text-gray-600 dark:text-gray-300 border-gray-200 dark:border-gray-800' }}">
                Übersicht
            </button>

            @if($contract->can_access_production)
                <button type="button" wire:click="switchTab('production')"
                        class="px-4 py-2 rounded-xl text-xs font-black border transition cursor-pointer {{ $activeTab === 'production' ? 'bg-gray-900 text-white border-gray-900 dark:bg-[#C5A059] dark:text-gray-950 dark:border-[#C5A059]' : 'bg-white dark:bg-gray-900 text-gray-600 dark:text-gray-300 border-gray-200 dark:border-gray-800' }}">
                    Packtisch & Laser ({{ $openOrders->count() }})
                </button>
            @endif

            @if($contract->can_access_support)
                <button type="button" wire:click="switchTab('support')"
                        class="px-4 py-2 rounded-xl text-xs font-black border transition cursor-pointer {{ $activeTab === 'support' ? 'bg-gray-900 text-white border-gray-900 dark:bg-[#C5A059] dark:text-gray-950 dark:border-[#C5A059]' : 'bg-white dark:bg-gray-900 text-gray-600 dark:text-gray-300 border-gray-200 dark:border-gray-800' }}">
                    Support ({{ $openTickets->count() }})
                </button>
            @endif

            @if($contract->can_access_inventory)
                <button type="button" wire:click="switchTab('inventory')"
                        class="px-4 py-2 rounded-xl text-xs font-black border transition cursor-pointer {{ $activeTab === 'inventory' ? 'bg-gray-900 text-white border-gray-900 dark:bg-[#C5A059] dark:text-gray-950 dark:border-[#C5A059]' : 'bg-white dark:bg-gray-900 text-gray-600 dark:text-gray-300 border-gray-200 dark:border-gray-800' }}">
                    Bruch melden
                </button>
            @endif

            <button type="button" wire:click="switchTab('time')"
                    class="px-4 py-2 rounded-xl text-xs font-black border transition cursor-pointer {{ $activeTab === 'time' ? 'bg-gray-900 text-white border-gray-900 dark:bg-[#C5A059] dark:text-gray-950 dark:border-[#C5A059]' : 'bg-white dark:bg-gray-900 text-gray-600 dark:text-gray-300 border-gray-200 dark:border-gray-800' }}">
                Zeiten & Urlaub
            </button>

            <button type="button" wire:click="switchTab('payslips')"
                    class="px-4 py-2 rounded-xl text-xs font-black border transition cursor-pointer {{ $activeTab === 'payslips' ? 'bg-gray-900 text-white border-gray-900 dark:bg-[#C5A059] dark:text-gray-950 dark:border-[#C5A059]' : 'bg-white dark:bg-gray-900 text-gray-600 dark:text-gray-300 border-gray-200 dark:border-gray-800' }}">
                Lohnzettel ({{ $payslips->count() }})
            </button>
        </div>

        {{-- ========================================================================= --}}
        {{-- 2. TICKETKACHEL AUF DER STARTSEITE (Meine Tickets + Team-Tickets + Chat)  --}}
        {{-- ========================================================================= --}}
        @if(($activeTab === 'overview' || $activeTab === 'tasks') && $canAccessTasks)
            <div wire:key="employee-tasks-kachel">
                <livewire:shop.management.management-task :embeddedMode="true" :key="'emp-tasks-board-' . $employee->id" />
            </div>
        @endif

        {{-- ========================================================================= --}}
        {{-- PACKTISCH & LASER (Im Tab "production" oder bei offenen Auftraegen)       --}}
        {{-- ========================================================================= --}}
        @if($contract->can_access_production && ($activeTab === 'production' || ($activeTab === 'overview' && $openOrders->isNotEmpty())))
            <div class="space-y-3">
                @if($activeTab === 'overview')
                    <div class="flex items-center justify-between px-1">
                        <span class="text-xs font-black uppercase tracking-wider text-gray-500 dark:text-gray-400">Offene Fertigung ({{ $openOrders->count() }})</span>
                    </div>
                @endif

                @forelse($openOrders as $order)
                    @php
                        $addr = $order->shipping_address ?: $order->billing_address ?: [];
                    @endphp
                    <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-4 sm:p-5 space-y-4">
                        <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-800 pb-3">
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-bold font-mono text-gray-900 dark:text-white">#{{ $order->order_number }}</span>
                                @if($order->is_express)
                                    <x-shop.express-info iconClass="w-3.5 h-3.5" class="px-2.5 py-0.5 rounded-md border border-primary/40 text-gray-800 dark:text-gray-100 text-[11px] font-bold" />
                                @endif
                            </div>
                            <span class="text-xs text-gray-400">{{ $order->created_at?->format('d.m.Y H:i') }}</span>
                        </div>

                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                            {{-- 1. Lasern --}}
                            <div class="p-3.5 rounded-xl bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 space-y-2.5">
                                <div class="text-xs font-bold text-gray-900 dark:text-white">1. Lasern</div>
                                @foreach($order->items as $item)
                                    @php
                                        $cfg = is_array($item->configuration) ? $item->configuration : [];
                                        $hasCustom = !empty($cfg['texts']) || !empty($cfg['logos']) || !empty($cfg['text']) || !empty($cfg['custom_text']);
                                        $hasBack = !empty($cfg['back_texts']) || !empty($cfg['back_logos']) || !empty($cfg['has_back_side']);
                                    @endphp
                                    <div class="p-2.5 rounded-lg bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 space-y-2">
                                        <div class="flex items-center justify-between gap-2">
                                            <span class="text-xs font-bold text-gray-900 dark:text-white">
                                                {{ $item->quantity }}x {{ $item->product_name }}
                                            </span>
                                            <button type="button" wire:click="toggleOrderItemCompleted('{{ $item->id }}')"
                                                    class="px-2.5 py-1 rounded-lg text-[11px] font-bold cursor-pointer {{ $item->is_completed ? 'bg-emerald-600 text-white' : 'bg-gray-200 dark:bg-gray-800 text-gray-700 dark:text-gray-300' }}">
                                                {{ $item->is_completed ? '✓ Gelasert' : 'Offen' }}
                                            </button>
                                        </div>

                                        @if(!empty($cfg['text']) || !empty($cfg['custom_text']))
                                            <div class="text-xs text-gray-600 dark:text-gray-300 font-mono bg-gray-50 dark:bg-gray-950 px-2 py-1 rounded">
                                                {{ $cfg['text'] ?? $cfg['custom_text'] }}
                                            </div>
                                        @endif

                                        @if(!empty($cfg['notes']))
                                            <div class="text-[11px] text-amber-700 dark:text-amber-300">
                                                Hinweis: {{ $cfg['notes'] }}
                                            </div>
                                        @endif

                                        @if($hasCustom)
                                            <div class="flex flex-wrap gap-1.5 pt-1">
                                                <a href="{{ route('employee.order-item.laser-svg', ['item' => $item->id, 'side' => 'front']) }}"
                                                   class="px-2.5 py-1 rounded-md bg-gray-900 dark:bg-[#C5A059] text-white dark:text-gray-950 text-[11px] font-bold">
                                                    ⬇ SVG Vorne
                                                </a>
                                                @if($hasBack)
                                                    <a href="{{ route('employee.order-item.laser-svg', ['item' => $item->id, 'side' => 'back']) }}"
                                                       class="px-2.5 py-1 rounded-md bg-gray-200 dark:bg-gray-800 text-gray-800 dark:text-gray-200 text-[11px] font-bold">
                                                        ⬇ SVG Hinten
                                                    </a>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>

                            {{-- 2. Adresse --}}
                            <div class="p-3.5 rounded-xl bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 space-y-1.5">
                                <div class="text-xs font-bold text-gray-900 dark:text-white">2. Lieferadresse</div>
                                <div class="text-xs text-gray-800 dark:text-gray-200 leading-relaxed pt-1">
                                    @if(!empty($addr['company']))
                                        <div class="font-bold">{{ $addr['company'] }}</div>
                                    @endif
                                    <div class="font-bold">{{ ($addr['first_name'] ?? '') . ' ' . ($addr['last_name'] ?? '') }}</div>
                                    <div>{{ ($addr['street'] ?? '') . ' ' . ($addr['house_number'] ?? '') }}</div>
                                    <div>{{ ($addr['postal_code'] ?? $addr['postal'] ?? '') . ' ' . ($addr['city'] ?? '') }}</div>
                                    <div class="text-gray-400">{{ $addr['country'] ?? 'DE' }}</div>
                                </div>
                            </div>

                            {{-- 3. Versenden --}}
                            <div class="p-3.5 rounded-xl bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 flex flex-col justify-between gap-3">
                                <div class="space-y-2">
                                    <div class="text-xs font-bold text-gray-900 dark:text-white">3. Versenden</div>
                                    <input type="text" wire:model="trackingNumbers.{{ $order->id }}" placeholder="Sendungsnummer (optional)"
                                           class="w-full bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl px-3 py-2 text-xs font-mono text-gray-900 dark:text-white outline-none" />
                                </div>

                                <button type="button" wire:click="markOrderShipped('{{ $order->id }}')"
                                        class="w-full py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs cursor-pointer">
                                    ✓ Als versendet abschließen
                                </button>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-8 text-center text-xs font-semibold text-gray-400">
                        Keine offenen Bestellungen. Alles erledigt!
                    </div>
                @endforelse
            </div>
        @endif

        {{-- ========================================================================= --}}
        {{-- TAB: SUPPORT                                                              --}}
        {{-- ========================================================================= --}}
        @if($activeTab === 'support' && $contract->can_access_support)
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-3 space-y-2">
                    <div class="text-xs font-bold text-gray-900 dark:text-white px-1">1. Offene Anfragen</div>
                    @forelse($openTickets as $t)
                        <button type="button" wire:click="selectSupportTicket('{{ $t->id }}')"
                                class="w-full text-left p-3 rounded-xl border transition cursor-pointer {{ $activeTicketId === $t->id ? 'border-gray-900 bg-gray-50 dark:border-[#C5A059] dark:bg-gray-950' : 'border-gray-200 dark:border-gray-800' }}">
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-xs font-bold text-gray-900 dark:text-white truncate">{{ $t->subject }}</span>
                                <span class="text-[10px] font-mono text-gray-400 shrink-0">#{{ $t->ticket_number }}</span>
                            </div>
                            <div class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5 truncate">
                                {{ $t->customer ? ($t->customer->first_name . ' ' . $t->customer->last_name) : 'Kunde' }}
                            </div>
                        </button>
                    @empty
                        <div class="p-6 text-center text-xs text-gray-400">Keine offenen Tickets.</div>
                    @endforelse
                </div>

                <div class="lg:col-span-2 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-4 sm:p-5 space-y-4">
                    @if($activeTicket)
                        <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-800 pb-3">
                            <div>
                                <div class="text-sm font-bold text-gray-900 dark:text-white">{{ $activeTicket->subject }}</div>
                                <div class="text-xs text-gray-400">#{{ $activeTicket->ticket_number }}</div>
                            </div>
                            <button type="button" wire:click="closeSupportTicket('{{ $activeTicket->id }}')"
                                    class="px-3 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold cursor-pointer">
                                ✓ Erledigt
                            </button>
                        </div>

                        <div class="space-y-2.5 max-h-80 overflow-y-auto pr-1">
                            @foreach($activeTicket->messages as $msg)
                                <div class="p-3 rounded-xl text-xs {{ $msg->sender_type === 'customer' ? 'bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-white mr-8' : 'bg-gray-900 text-white dark:bg-[#C5A059]/20 dark:text-white ml-8' }}">
                                    <div class="text-[10px] opacity-60 mb-1">
                                        {{ $msg->sender_type === 'customer' ? 'Kunde' : 'Support' }} · {{ $msg->created_at?->format('d.m.Y H:i') }}
                                    </div>
                                    <div class="whitespace-pre-line">{{ $msg->message }}</div>
                                </div>
                            @endforeach
                        </div>

                        <form wire:submit.prevent="sendSupportReply" class="space-y-2 pt-2 border-t border-gray-100 dark:border-gray-800">
                            <textarea wire:model="ticketReplyMessage" rows="3" placeholder="Antwort schreiben..."
                                      class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl p-3 text-xs text-gray-900 dark:text-white outline-none"></textarea>
                            @error('ticketReplyMessage') <p class="text-[11px] text-rose-600">{{ $message }}</p> @enderror
                            <div class="flex justify-end">
                                <button type="submit" class="px-5 py-2 rounded-xl bg-gray-900 dark:bg-[#C5A059] text-white dark:text-gray-950 font-bold text-xs cursor-pointer">
                                    Antwort senden
                                </button>
                            </div>
                        </form>
                    @else
                        <div class="p-8 text-center text-xs text-gray-400">Bitte links ein Ticket auswählen.</div>
                    @endif
                </div>
            </div>
        @endif

        {{-- ========================================================================= --}}
        {{-- TAB: BRUCH MELDEN                                                         --}}
        {{-- ========================================================================= --}}
        @if($activeTab === 'inventory' && $contract->can_access_inventory)
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-5 space-y-4">
                <form wire:submit.prevent="saveFracture" class="grid grid-cols-1 sm:grid-cols-4 gap-3 items-end">
                    <div class="sm:col-span-2">
                        <label class="text-[11px] font-bold text-gray-600 dark:text-gray-400">1. Produkt wählen</label>
                        <select wire:model="lossProductId"
                                class="w-full mt-1 bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl px-3 py-2.5 text-xs text-gray-900 dark:text-white outline-none">
                            <option value="">Produkt auswählen</option>
                            @foreach($physicalProducts as $prod)
                                <option value="{{ $prod->id }}">{{ $prod->name }} (Bestand: {{ $prod->quantity }})</option>
                            @endforeach
                        </select>
                        @error('lossProductId') <span class="text-[11px] text-rose-600">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="text-[11px] font-bold text-gray-600 dark:text-gray-400">2. Menge</label>
                        <input type="number" min="1" wire:model="lossQuantity"
                               class="w-full mt-1 bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl px-3 py-2.5 text-xs text-gray-900 dark:text-white outline-none" />
                        @error('lossQuantity') <span class="text-[11px] text-rose-600">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="text-[11px] font-bold text-gray-600 dark:text-gray-400">3. Grund</label>
                        <input type="text" wire:model="lossReason" placeholder="z. B. Laser-Ausschuss"
                               class="w-full mt-1 bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl px-3 py-2.5 text-xs text-gray-900 dark:text-white outline-none" />
                        @error('lossReason') <span class="text-[11px] text-rose-600">{{ $message }}</span> @enderror
                    </div>

                    <div class="sm:col-span-4 flex justify-end pt-1">
                        <button type="submit" class="px-5 py-2.5 rounded-xl bg-gray-900 dark:bg-[#C5A059] text-white dark:text-gray-950 font-bold text-xs cursor-pointer">
                            ✓ Bruch speichern
                        </button>
                    </div>
                </form>

                @if($recentLosses->isNotEmpty())
                    <div class="pt-3 border-t border-gray-100 dark:border-gray-800 space-y-1.5">
                        @foreach($recentLosses as $loss)
                            <div class="flex items-center justify-between text-xs py-1.5 px-2 rounded-lg bg-gray-50 dark:bg-gray-950">
                                <span class="font-semibold text-gray-900 dark:text-white">{{ $loss->quantity }}x {{ $loss->product?->name ?? 'Produkt' }}</span>
                                <span class="text-gray-500">{{ $loss->reason }}</span>
                                <span class="text-gray-400 font-mono">{{ $loss->created_at?->format('d.m.Y H:i') }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif

        {{-- ========================================================================= --}}
        {{-- TAB: ZEITEN & URLAUB                                                      --}}
        {{-- ========================================================================= --}}
        @if($activeTab === 'time')
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-4 sm:p-5 space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-4 text-xs">
                        <div>
                            <span class="text-gray-400 block text-[10px]">Stunden ({{ str_pad((string)$selectedMonth, 2, '0', STR_PAD_LEFT) }}/{{ $selectedYear }})</span>
                            <strong class="text-sm text-gray-900 dark:text-white">{{ $stats['worked_hours_formatted'] }}</strong>
                        </div>
                        <div>
                            <span class="text-gray-400 block text-[10px]">Urlaub frei</span>
                            <strong class="text-sm text-gray-900 dark:text-white">{{ $contract->remaining_vacation_days }} / {{ $contract->vacation_days_per_year }} Tage</strong>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" wire:click="$toggle('showManualEntryForm')"
                                class="px-3.5 py-2 rounded-xl bg-gray-900 dark:bg-[#C5A059] text-white dark:text-gray-950 text-xs font-bold cursor-pointer">
                            + Arbeitszeit
                        </button>
                        <button type="button" wire:click="$toggle('showLeaveForm')"
                                class="px-3.5 py-2 rounded-xl bg-gray-100 dark:bg-gray-800 text-gray-800 dark:text-gray-200 text-xs font-bold cursor-pointer">
                            + Urlaub / Krank
                        </button>
                    </div>
                </div>

                @if($showManualEntryForm)
                    <form wire:submit.prevent="saveManualTimeEntry" class="p-4 rounded-xl bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 grid grid-cols-1 sm:grid-cols-5 gap-3 items-end">
                        <div>
                            <label class="text-[11px] font-bold text-gray-500">Datum</label>
                            <input type="date" wire:model="manualWorkDate" class="w-full mt-1 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl px-3 py-2 text-xs text-gray-900 dark:text-white" />
                        </div>
                        <div>
                            <label class="text-[11px] font-bold text-gray-500">Von</label>
                            <input type="time" wire:model="manualStartTime" class="w-full mt-1 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl px-3 py-2 text-xs text-gray-900 dark:text-white" />
                        </div>
                        <div>
                            <label class="text-[11px] font-bold text-gray-500">Bis</label>
                            <input type="time" wire:model="manualEndTime" class="w-full mt-1 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl px-3 py-2 text-xs text-gray-900 dark:text-white" />
                        </div>
                        <div>
                            <label class="text-[11px] font-bold text-gray-500">Notiz</label>
                            <input type="text" wire:model="manualActivityNote" class="w-full mt-1 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl px-3 py-2 text-xs text-gray-900 dark:text-white" />
                        </div>
                        <button type="submit" class="py-2 px-4 rounded-xl bg-gray-900 dark:bg-[#C5A059] text-white dark:text-gray-950 font-bold text-xs cursor-pointer">
                            Speichern
                        </button>
                    </form>
                @endif

                @if($showLeaveForm)
                    <form wire:submit.prevent="saveLeaveEntry" class="p-4 rounded-xl bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 grid grid-cols-1 sm:grid-cols-5 gap-3 items-end">
                        <div>
                            <label class="text-[11px] font-bold text-gray-500">1. Art</label>
                            <select wire:model="leaveType" class="w-full mt-1 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl px-3 py-2 text-xs text-gray-900 dark:text-white">
                                <option value="vacation">Urlaub</option>
                                <option value="sick">Krank</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-[11px] font-bold text-gray-500">2. Von</label>
                            <input type="date" wire:model="leaveStartDate" class="w-full mt-1 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl px-3 py-2 text-xs text-gray-900 dark:text-white" />
                        </div>
                        <div>
                            <label class="text-[11px] font-bold text-gray-500">3. Bis</label>
                            <input type="date" wire:model="leaveEndDate" class="w-full mt-1 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl px-3 py-2 text-xs text-gray-900 dark:text-white" />
                        </div>
                        <div>
                            <label class="text-[11px] font-bold text-gray-500">Notiz (optional)</label>
                            <input type="text" wire:model="leaveNote" class="w-full mt-1 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl px-3 py-2 text-xs text-gray-900 dark:text-white" />
                        </div>
                        <button type="submit" class="py-2 px-4 rounded-xl bg-gray-900 dark:bg-[#C5A059] text-white dark:text-gray-950 font-bold text-xs cursor-pointer">
                            Eintragen
                        </button>
                    </form>
                @endif

                <div class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse($stats['entries'] as $entry)
                        <div class="py-2.5 flex items-center justify-between gap-3 text-xs">
                            <div class="flex items-center gap-3">
                                <span class="font-mono font-bold text-gray-900 dark:text-white">{{ $entry->work_date?->format('d.m.Y') }}</span>
                                @if($entry->entry_type === 'vacation')
                                    <span class="px-2 py-0.5 rounded-md bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300 font-bold">Urlaub</span>
                                @elseif($entry->entry_type === 'sick')
                                    <span class="px-2 py-0.5 rounded-md bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300 font-bold">Krank</span>
                                @else
                                    <span class="font-mono text-gray-500">{{ $entry->started_at?->format('H:i') }} bis {{ $entry->ended_at?->format('H:i') }} ({{ $entry->formatted_hours }})</span>
                                @endif
                                <span class="text-gray-400">{{ $entry->activity_note }}</span>
                            </div>

                            <div class="flex items-center gap-2">
                                @if($entry->status === 'pending_approval')
                                    <span class="text-amber-600 dark:text-amber-400 font-semibold">Wartet auf Freigabe</span>
                                @elseif($entry->status === 'approved')
                                    <span class="text-emerald-600 dark:text-emerald-400 font-semibold">✓ Genehmigt</span>
                                @endif

                                @if($entry->status !== 'approved')
                                    <button type="button" wire:click="deleteTimeEntry('{{ $entry->id }}')"
                                            class="text-gray-400 hover:text-rose-600 font-bold cursor-pointer">
                                        ✕
                                    </button>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="py-6 text-center text-xs text-gray-400">Noch keine Einträge in diesem Monat.</div>
                    @endforelse
                </div>
            </div>
        @endif

        {{-- ========================================================================= --}}
        {{-- TAB: LOHNZETTEL                                                           --}}
        {{-- ========================================================================= --}}
        @if($activeTab === 'payslips')
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-4 sm:p-5 divide-y divide-gray-100 dark:divide-gray-800">
                @forelse($payslips as $slip)
                    <div class="py-3 flex items-center justify-between gap-3">
                        <div>
                            <div class="text-sm font-bold text-gray-900 dark:text-white">{{ $slip->period_label }}</div>
                            <div class="text-xs text-gray-500">Auszahlung: {{ number_format($slip->net_payout_cents / 100, 2, ',', '.') }} €</div>
                        </div>
                        <a href="{{ route('employee.payslip.pdf', ['payslip' => $slip->id]) }}" target="_blank"
                           class="px-3.5 py-2 rounded-xl bg-gray-900 dark:bg-[#C5A059] text-white dark:text-gray-950 text-xs font-bold">
                            ⬇ PDF
                        </a>
                    </div>
                @empty
                    <div class="py-6 text-center text-xs text-gray-400">Noch keine Lohnabrechnungen vorhanden.</div>
                @endforelse
            </div>
        @endif
    @endif

    {{-- OPERATOR PROFIL-MODAL FUER DEN MITARBEITER --}}
    @include('livewire.shop.management.partials.task-operator-profile-modal')
</div>
