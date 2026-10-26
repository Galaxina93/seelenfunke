<div class="w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-5 sm:py-6 lg:py-7 animate-fade-in-up space-y-6 pb-16 font-sans antialiased text-gray-700 dark:text-gray-300"
     style="--theme-color: {{ $this->themeColorHex }}; --theme-color-5: {{ $this->themeColorHex }}0D; --theme-color-10: {{ $this->themeColorHex }}1A; --theme-color-15: {{ $this->themeColorHex }}26; --theme-color-20: {{ $this->themeColorHex }}33; --theme-color-30: {{ $this->themeColorHex }}4D; --theme-color-40: {{ $this->themeColorHex }}66; --theme-color-50: {{ $this->themeColorHex }}80; --theme-color-70: {{ $this->themeColorHex }}B3;">

    @php
        $monthNames = [
            1 => 'Januar', 2 => 'Februar', 3 => 'März', 4 => 'April',
            5 => 'Mai', 6 => 'Juni', 7 => 'Juli', 8 => 'August',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Dezember'
        ];
    @endphp

    {{-- 1. SCHLANKER HEADER MIT 4 KERN-KPIS --}}
    <div class="bg-white dark:bg-gray-900/85 backdrop-blur-xl rounded-3xl border border-gray-200 dark:border-gray-800 p-6 sm:p-7 shadow-sm dark:shadow-xl transition-colors">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6">
            <div>
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-[var(--theme-color-10)] border border-[var(--theme-color-30)] text-[var(--theme-color)] text-[10px] font-black uppercase tracking-widest mb-2">
                    <x-heroicon-s-briefcase class="w-3.5 h-3.5" />
                    <span>Mitarbeiter Cockpit • {{ $contract?->contract_number ?: 'Personalakte' }}</span>
                </div>
                <h1 class="text-2xl sm:text-3xl font-serif font-bold text-gray-900 dark:text-white tracking-tight">
                    Hallo {{ $employee->first_name }}!
                </h1>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    @if($contract)
                        <strong class="text-gray-900 dark:text-gray-200">{{ $contract->job_title }}</strong> • {{ $contract->employment_type_label }} •
                        {{ number_format($contract->hourly_rate_cents / 100, 2, ',', '.') }} € / Std. •
                        Wochensoll: {{ number_format((float) $contract->weekly_hours, 1, ',', '.') }} Std.
                    @else
                        Dein Arbeitsvertrag wird derzeit von der Geschäftsleitung vorbereitet.
                    @endif
                </p>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <div class="bg-gray-50 dark:bg-gray-950/80 border border-gray-200 dark:border-gray-800 rounded-2xl px-4 py-3">
                    <div class="text-[9px] font-black uppercase tracking-widest text-gray-500">Stunden ({{ $monthNames[$selectedMonth] ?? '' }})</div>
                    <div class="text-lg font-black text-gray-900 dark:text-white font-mono mt-0.5">{{ $stats['worked_hours_formatted'] }}</div>
                    <div class="text-[10px] text-gray-500">Soll: {{ number_format($stats['target_hours_decimal'], 1, ',', '.') }} Std.</div>
                </div>

                <div class="bg-gray-50 dark:bg-gray-950/80 border border-gray-200 dark:border-gray-800 rounded-2xl px-4 py-3">
                    <div class="text-[9px] font-black uppercase tracking-widest text-gray-500">Brutto-Anspruch</div>
                    <div class="text-lg font-black text-[var(--theme-color)] font-mono mt-0.5">
                        {{ number_format($stats['estimated_gross_cents'] / 100, 2, ',', '.') }} €
                    </div>
                    <div class="text-[10px] text-gray-500">
                        Netto ca. {{ number_format($stats['estimated_net_cents'] / 100, 2, ',', '.') }} €
                    </div>
                </div>

                <div class="bg-gray-50 dark:bg-gray-950/80 border border-gray-200 dark:border-gray-800 rounded-2xl px-4 py-3">
                    <div class="text-[9px] font-black uppercase tracking-widest text-gray-500">Resturlaub</div>
                    <div class="text-lg font-black text-emerald-600 dark:text-emerald-400 font-mono mt-0.5">
                        {{ $contract ? $contract->remaining_vacation_days : 0 }} Tage
                    </div>
                    <div class="text-[10px] text-gray-500">von {{ $contract?->vacation_days_per_year ?? 0 }} Tagen / Jahr</div>
                </div>

                <div class="bg-gray-50 dark:bg-gray-950/80 border border-gray-200 dark:border-gray-800 rounded-2xl px-4 py-3">
                    <div class="text-[9px] font-black uppercase tracking-widest text-gray-500">Vertrag (§ 2 NachwG)</div>
                    <div class="text-sm font-black {{ $contract?->signed_at ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400' }} mt-1">
                        {{ $contract?->signed_at ? 'Digital unterzeichnet' : 'In Vorbereitung' }}
                    </div>
                    <div class="text-[10px] text-gray-500">
                        {{ $contract?->signed_at ? $contract->signed_at->format('d.m.Y') : '—' }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if(session()->has('success'))
        <div class="bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/30 text-emerald-800 dark:text-emerald-300 px-5 py-3.5 rounded-2xl text-xs font-bold flex items-center gap-2.5 shadow-xs">
            <x-heroicon-s-check-circle class="w-5 h-5 text-emerald-600 dark:text-emerald-400 shrink-0" />
            <span>{{ session('success') }}</span>
        </div>
    @endif

    {{-- 2. EINZEILIGE AKTIONS- & DOKUMENTEN-BAR (STEMPELUHR + DIREKTE PDF-DOWNLOADS) --}}
    @if($contract)
        <div class="bg-white dark:bg-gray-900/90 backdrop-blur-xl rounded-2xl border border-[var(--theme-color-30)] px-5 py-3.5 shadow-sm dark:shadow-lg transition-colors">
            <div class="flex flex-col xl:flex-row xl:items-center justify-between gap-4">
                {{-- Links: Zeitraum & Stempeluhr --}}
                <div class="flex flex-wrap items-center gap-2.5">
                    <select wire:model.live="selectedMonth"
                            class="bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 text-gray-900 dark:text-white text-xs font-bold rounded-xl px-3 py-2 focus:border-[var(--theme-color)] outline-none cursor-pointer">
                        @foreach($monthNames as $mNum => $mLabel)
                            <option value="{{ $mNum }}">{{ $mLabel }}</option>
                        @endforeach
                    </select>

                    <select wire:model.live="selectedYear"
                            class="bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 text-gray-900 dark:text-white text-xs font-bold rounded-xl px-3 py-2 focus:border-[var(--theme-color)] outline-none cursor-pointer">
                        @for($y = now()->year; $y >= 2024; $y--)
                            <option value="{{ $y }}">{{ $y }}</option>
                        @endfor
                    </select>

                    @if($runningEntry)
                        <button type="button" wire:click="clockOutNow"
                                class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-rose-500 hover:bg-rose-400 text-white dark:text-gray-950 text-xs font-black uppercase tracking-wider shadow-md animate-pulse cursor-pointer">
                            <x-heroicon-s-stop class="w-4 h-4" />
                            <span>Ausstempeln (seit {{ $runningEntry->started_at?->format('H:i') }} Uhr)</span>
                        </button>
                    @else
                        <button type="button" wire:click="clockInNow"
                                class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-white dark:text-gray-950 text-xs font-black uppercase tracking-wider shadow-sm dark:shadow-md cursor-pointer">
                            <x-heroicon-s-play class="w-4 h-4" />
                            <span>Jetzt einstempeln</span>
                        </button>
                    @endif

                    <button type="button" wire:click="$toggle('showAddEntryForm')"
                            class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-800 dark:text-gray-200 text-xs font-bold border border-gray-200 dark:border-gray-700 transition-colors cursor-pointer">
                        <x-heroicon-m-plus class="w-4 h-4 text-[var(--theme-color)]" />
                        <span>Arbeitszeit eintragen</span>
                    </button>
                </div>

                {{-- Rechts: 1-Klick PDF-Downloads (Arbeitsvertrag, Personalbogen, Stundenzettel) --}}
                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('employee.contract.pdf') }}" target="_blank"
                       class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-[var(--theme-color)] hover:opacity-90 text-gray-950 text-xs font-black uppercase tracking-wider shadow-sm">
                        <x-heroicon-s-document-check class="w-4 h-4" />
                        <span>Arbeitsvertrag (PDF)</span>
                    </a>

                    <a href="{{ route('employee.questionnaire.pdf') }}" target="_blank"
                       class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-800 dark:text-gray-200 text-xs font-bold border border-gray-200 dark:border-gray-700 transition-colors">
                        <x-heroicon-m-identification class="w-4 h-4 text-[var(--theme-color)]" />
                        <span>Personalbogen (PDF)</span>
                    </a>

                    <a href="{{ route('employee.timesheet.pdf', ['year' => $selectedYear, 'month' => $selectedMonth]) }}" target="_blank"
                       class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-800 dark:text-gray-200 text-xs font-bold border border-gray-200 dark:border-gray-700 transition-colors">
                        <x-heroicon-m-clock class="w-4 h-4 text-emerald-600 dark:text-emerald-400" />
                        <span>Stundenzettel (PDF)</span>
                    </a>
                </div>
            </div>
        </div>
    @endif

    {{-- 3. EINE ZENTRALE HAUPT-KACHEL MIT 2 SAUBEREN TABS --}}
    <div class="bg-white dark:bg-gray-900/85 backdrop-blur-xl rounded-3xl border border-gray-200 dark:border-gray-800 overflow-hidden shadow-sm dark:shadow-xl transition-colors">
        {{-- Tab-Navigation --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 px-6 py-4 border-b border-gray-200 dark:border-gray-800 bg-gray-50/70 dark:bg-gray-950/50">
            <div class="flex items-center gap-2">
                <button type="button" wire:click="$set('mainTab', 'timesheets')"
                        class="px-4 py-2 rounded-xl text-xs font-black uppercase tracking-wider transition cursor-pointer {{ $mainTab === 'timesheets' ? 'bg-[var(--theme-color)] text-gray-950 shadow-sm' : 'bg-white dark:bg-gray-900 text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white border border-gray-200 dark:border-gray-800' }}">
                    Arbeitszeiten & Stundenzettel ({{ $stats['entries_count'] }})
                </button>
                <button type="button" wire:click="$set('mainTab', 'payslips')"
                        class="px-4 py-2 rounded-xl text-xs font-black uppercase tracking-wider transition cursor-pointer {{ $mainTab === 'payslips' ? 'bg-[var(--theme-color)] text-gray-950 shadow-sm' : 'bg-white dark:bg-gray-900 text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white border border-gray-200 dark:border-gray-800' }}">
                    Gehaltsabrechnungen ({{ $payslips->count() }})
                </button>
            </div>

            @if($stats['is_minijob'])
                <div class="flex items-center gap-2 text-xs">
                    <span class="text-gray-500 dark:text-gray-400">Minijob-Grenze (556,00 €):</span>
                    <span class="font-mono font-bold {{ $stats['minijob_warning'] ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                        {{ $stats['minijob_usage_percent'] }} % ausgeschöpft
                    </span>
                </div>
            @endif
        </div>

        {{-- TAB 1: ARBEITSZEITEN & STUNDENZETTEL --}}
        @if($mainTab === 'timesheets')
            @if($showAddEntryForm)
                <form wire:submit.prevent="saveTimeEntry" class="p-5 bg-gray-50 dark:bg-gray-950/90 border-b border-gray-200 dark:border-gray-800">
                    <div class="grid grid-cols-1 sm:grid-cols-6 gap-3 items-end">
                        <div>
                            <label class="text-[10px] font-black uppercase text-gray-500 dark:text-gray-400">Datum *</label>
                            <input type="date" wire:model="entryDate" class="w-full mt-1 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl px-3 py-2 text-xs text-gray-900 dark:text-white" />
                        </div>
                        <div>
                            <label class="text-[10px] font-black uppercase text-gray-500 dark:text-gray-400">Beginn *</label>
                            <input type="time" wire:model="entryStartTime" class="w-full mt-1 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl px-3 py-2 text-xs text-gray-900 dark:text-white" />
                        </div>
                        <div>
                            <label class="text-[10px] font-black uppercase text-gray-500 dark:text-gray-400">Ende *</label>
                            <input type="time" wire:model="entryEndTime" class="w-full mt-1 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl px-3 py-2 text-xs text-gray-900 dark:text-white" />
                        </div>
                        <div>
                            <label class="text-[10px] font-black uppercase text-gray-500 dark:text-gray-400">Pause (Min.)</label>
                            <input type="number" wire:model="entryBreakMinutes" class="w-full mt-1 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl px-3 py-2 text-xs font-mono text-gray-900 dark:text-white" />
                        </div>
                        <div>
                            <label class="text-[10px] font-black uppercase text-gray-500 dark:text-gray-400">Tätigkeit</label>
                            <input type="text" wire:model="entryActivityNote" class="w-full mt-1 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl px-3 py-2 text-xs text-gray-900 dark:text-white" />
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="submit" class="w-full py-2 px-4 rounded-xl bg-[var(--theme-color)] text-gray-950 text-xs font-black uppercase cursor-pointer">
                                Speichern
                            </button>
                        </div>
                    </div>
                </form>
            @endif

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-800 bg-gray-50/70 dark:bg-gray-950/40 text-[10px] font-black uppercase tracking-wider text-gray-500">
                            <th class="py-3.5 px-5">Datum</th>
                            <th class="py-3.5 px-4">Zeitraum</th>
                            <th class="py-3.5 px-4">Pause (§ 4 ArbZG)</th>
                            <th class="py-3.5 px-4">Netto-Arbeitszeit</th>
                            <th class="py-3.5 px-4">Tätigkeit</th>
                            <th class="py-3.5 px-5 text-right">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800/70 text-xs">
                        @forelse($stats['entries'] as $entry)
                            <tr class="hover:bg-gray-50/80 dark:hover:bg-gray-800/30 transition">
                                <td class="py-3.5 px-5 font-mono font-bold text-gray-900 dark:text-white">
                                    {{ $entry->work_date ? $entry->work_date->format('d.m.Y') : '—' }}
                                </td>
                                <td class="py-3.5 px-4 font-mono text-gray-700 dark:text-gray-300">
                                    {{ $entry->started_at ? $entry->started_at->format('H:i') : '—' }} – {{ $entry->ended_at ? $entry->ended_at->format('H:i') : '—' }} Uhr
                                </td>
                                <td class="py-3.5 px-4 text-gray-500 dark:text-gray-400">
                                    {{ $entry->break_minutes }} Min.
                                </td>
                                <td class="py-3.5 px-4 font-mono font-bold text-[var(--theme-color)]">
                                    {{ $entry->formatted_hours }}
                                </td>
                                <td class="py-3.5 px-4 text-gray-700 dark:text-gray-300">
                                    {{ $entry->activity_note ?: 'Manufaktur & Versand' }}
                                </td>
                                <td class="py-3.5 px-5 text-right">
                                    @if($entry->status === 'approved')
                                        <span class="px-2 py-0.5 rounded bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-400 dark:border-emerald-500/30 text-[10px] font-bold">Abgerechnet</span>
                                    @else
                                        <div class="inline-flex items-center gap-2">
                                            <span class="px-2 py-0.5 rounded bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-700 text-[10px] font-bold">Erfasst</span>
                                            <button type="button" wire:click="deleteTimeEntry('{{ $entry->id }}')"
                                                    class="text-gray-400 hover:text-rose-500 dark:hover:text-rose-400 cursor-pointer" title="Eintrag löschen">
                                                <x-heroicon-m-trash class="w-4 h-4" />
                                            </button>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-10 text-center text-gray-500 text-xs">
                                    Noch keine Arbeitszeiten für {{ $monthNames[$selectedMonth] ?? '' }} {{ $selectedYear }} eingetragen. Nutze oben <strong>Jetzt einstempeln</strong> oder <strong>Arbeitszeit eintragen</strong>.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif

        {{-- TAB 2: GEHALTSABRECHNUNGEN (PAYSLIPS) --}}
        @if($mainTab === 'payslips')
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-800 bg-gray-50/70 dark:bg-gray-950/40 text-[10px] font-black uppercase tracking-wider text-gray-500">
                            <th class="py-3.5 px-5">Beleg-Nr.</th>
                            <th class="py-3.5 px-4">Abrechnungsmonat</th>
                            <th class="py-3.5 px-4">Gearbeitete Stunden</th>
                            <th class="py-3.5 px-4 text-right">Steuer-Brutto</th>
                            <th class="py-3.5 px-4 text-right">Netto-Auszahlung</th>
                            <th class="py-3.5 px-5 text-right">Status & Download</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800/70 text-xs">
                        @forelse($payslips as $slip)
                            <tr class="hover:bg-gray-50/80 dark:hover:bg-gray-800/30 transition">
                                <td class="py-3.5 px-5 font-mono font-bold text-gray-900 dark:text-white">{{ $slip->payslip_number }}</td>
                                <td class="py-3.5 px-4 font-bold text-gray-800 dark:text-gray-200">{{ $slip->period_label }}</td>
                                <td class="py-3.5 px-4 font-mono text-gray-500 dark:text-gray-400">{{ number_format(($slip->worked_minutes ?? 0) / 60, 2, ',', '.') }} Std.</td>
                                <td class="py-3.5 px-4 text-right font-mono text-gray-700 dark:text-gray-300">{{ number_format(($slip->gross_amount_cents ?? 0) / 100, 2, ',', '.') }} €</td>
                                <td class="py-3.5 px-4 text-right font-mono font-bold text-emerald-600 dark:text-emerald-400">{{ number_format(($slip->net_payout_cents ?? 0) / 100, 2, ',', '.') }} €</td>
                                <td class="py-3.5 px-5 text-right">
                                    <div class="inline-flex items-center gap-2">
                                        @if($slip->status === 'paid')
                                            <span class="px-2 py-0.5 rounded bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-400 dark:border-emerald-500/30 text-[10px] font-bold">
                                                Überwiesen {{ $slip->paid_at ? $slip->paid_at->format('d.m.Y') : '' }}
                                            </span>
                                        @else
                                            <span class="px-2 py-0.5 rounded bg-amber-50 text-amber-800 border border-amber-200 dark:bg-amber-500/15 dark:text-amber-300 dark:border-amber-500/30 text-[10px] font-bold">
                                                Freigegeben
                                            </span>
                                        @endif

                                        <a href="{{ route('employee.payslip.pdf', ['payslip' => $slip->id]) }}" target="_blank"
                                           class="px-3 py-1 rounded-lg bg-[var(--theme-color)] text-gray-950 text-[10px] font-black uppercase">
                                            PDF Download
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-10 text-center text-gray-500 text-xs">
                                    Sobald deine monatliche Lohnabrechnung erstellt wurde, kannst du sie hier als PDF herunterladen.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif

        {{-- UNTEN MITTIG: ERKLÄRUNG ANZEIGEN --}}
        <div class="border-t border-gray-200 dark:border-gray-800 bg-gray-50/60 dark:bg-gray-950/60 px-5 py-3 text-center">
            <button type="button" wire:click="$toggle('showExplanation')"
                    class="inline-flex items-center gap-1.5 text-xs font-bold text-gray-500 hover:text-[var(--theme-color)] transition cursor-pointer">
                <span>{{ $showExplanation ? 'Erklärung ausblenden' : 'Erklärung anzeigen' }}</span>
                <x-heroicon-m-chevron-down class="w-4 h-4 transition-transform {{ $showExplanation ? 'rotate-180' : '' }}" />
            </button>

            @if($showExplanation)
                <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-800 text-left grid grid-cols-1 md:grid-cols-3 gap-4 text-xs text-gray-600 dark:text-gray-400">
                    <div class="bg-white dark:bg-gray-900/60 border border-gray-200 dark:border-gray-800 rounded-xl p-3.5">
                        <div class="font-bold text-gray-900 dark:text-white mb-1">Gesetzliche Ruhepausen (§ 4 ArbZG)</div>
                        Bei einer täglichen Arbeitszeit von mehr als 6 Stunden werden automatisch mind. 30 Minuten Ruhepause berücksichtigt, ab 9 Stunden mind. 45 Minuten.
                    </div>
                    <div class="bg-white dark:bg-gray-900/60 border border-gray-200 dark:border-gray-800 rounded-xl p-3.5">
                        <div class="font-bold text-gray-900 dark:text-white mb-1">Aufzeichnungspflicht (§ 17 MiLoG)</div>
                        Bitte erfasse deine Arbeitszeiten spätestens innerhalb von 7 Kalendertagen. Deinen offiziellen Stundenzettel kannst du jederzeit oben rechts als PDF herunterladen.
                    </div>
                    <div class="bg-white dark:bg-gray-900/60 border border-gray-200 dark:border-gray-800 rounded-xl p-3.5">
                        <div class="font-bold text-gray-900 dark:text-white mb-1">Arbeitsvertrag & Lohnabrechnungen</div>
                        Dein digital unterzeichneter Arbeitsvertrag (§ 2 NachwG) sowie deine monatlichen Entgeltabrechnungen (§ 108 GewO) stehen dir dauerhaft zum Speichern und Ausdrucken bereit.
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
