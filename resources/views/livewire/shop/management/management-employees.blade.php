<div class="space-y-6 pb-20 font-sans antialiased text-gray-700 dark:text-gray-300"
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
                    <x-heroicon-s-user-group class="w-3.5 h-3.5" />
                    <span>Leitung • Personal & Arbeitsverträge</span>
                </div>
                <h1 class="text-2xl sm:text-3xl font-serif font-bold text-gray-900 dark:text-white tracking-tight">
                    Mitarbeiter & Lohnzentrale
                </h1>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    Digitales Onboarding per Link (§ 2 NachwG), Arbeitszeiterfassung (§ 17 MiLoG) & vorbereitende Lohnabrechnung.
                </p>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <div class="bg-gray-50 dark:bg-gray-950/80 border border-gray-200 dark:border-gray-800 rounded-2xl px-4 py-3">
                    <div class="text-[9px] font-black uppercase tracking-widest text-gray-500">Aktives Team</div>
                    <div class="text-lg font-black text-gray-900 dark:text-white font-mono mt-0.5">{{ $activeCount }}</div>
                    <div class="text-[10px] text-gray-500">{{ $pendingInvitesCount }} Einladung(en) offen</div>
                </div>

                <div class="bg-gray-50 dark:bg-gray-950/80 border border-gray-200 dark:border-gray-800 rounded-2xl px-4 py-3">
                    <div class="text-[9px] font-black uppercase tracking-widest text-gray-500">Stunden ({{ $monthNames[$selectedMonth] ?? '' }})</div>
                    <div class="text-lg font-black text-[var(--theme-color)] font-mono mt-0.5">{{ number_format($totalWorkedHoursMonth, 1, ',', '.') }} Std.</div>
                    <div class="text-[10px] text-gray-500">Erfasste Netto-Zeit</div>
                </div>

                <div class="bg-gray-50 dark:bg-gray-950/80 border border-gray-200 dark:border-gray-800 rounded-2xl px-4 py-3">
                    <div class="text-[9px] font-black uppercase tracking-widest text-gray-500">Offene Überweisungen</div>
                    <div class="text-lg font-black {{ $openPayslipsCount > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400' }} font-mono mt-0.5">
                        {{ number_format($openPayslipsNetCents / 100, 2, ',', '.') }} €
                    </div>
                    <div class="text-[10px] text-gray-500">{{ $openPayslipsCount }} Lohnzettel offen</div>
                </div>

                <div class="bg-gray-50 dark:bg-gray-950/80 border border-gray-200 dark:border-gray-800 rounded-2xl px-4 py-3">
                    <div class="text-[9px] font-black uppercase tracking-widest text-gray-500">AG-Gesamtaufwand</div>
                    <div class="text-lg font-black text-gray-900 dark:text-white font-mono mt-0.5">
                        {{ number_format($monthTotalEmployerCostCents / 100, 2, ',', '.') }} €
                    </div>
                    <div class="text-[10px] text-gray-500">Brutto + AG-Nebenkosten</div>
                </div>
            </div>
        </div>
    </div>

    {{-- FLASH MELDUNGEN --}}
    @if(session()->has('success'))
        <div class="bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/30 text-emerald-800 dark:text-emerald-300 px-5 py-3.5 rounded-2xl text-xs font-bold flex items-center justify-between shadow-xs">
            <div class="flex items-center gap-2.5">
                <x-heroicon-s-check-circle class="w-5 h-5 text-emerald-600 dark:text-emerald-400 shrink-0" />
                <span>{{ session('success') }}</span>
            </div>
        </div>
    @endif

    {{-- 2. AKTIVER EINLADUNGSLINK & 6-STELLIGER CODE (WENN AUSGEWÄHLT ODER NEU ERSTELLT) --}}
    @if($activeInviteSlot && $activeInviteSlot->invitation_url)
        <div class="bg-white dark:bg-gray-900/95 border-2 border-[var(--theme-color-50)] rounded-2xl p-5 shadow-md dark:shadow-xl transition-colors">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="px-2.5 py-0.5 rounded-md bg-[var(--theme-color-20)] text-[var(--theme-color)] text-[10px] font-black uppercase tracking-widest">
                            Einladung bereit
                        </span>
                        <span class="text-sm font-bold text-gray-900 dark:text-white">{{ $activeInviteSlot->invitation_label }} ({{ $activeInviteSlot->job_title }})</span>
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        Sende dem neuen Mitarbeiter den Einladungslink und den 6-stelligen Sicherheitscode. Nach der digitalen Unterschrift werden Arbeitsvertrag & Personalbogen automatisch generiert.
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2.5" x-data="{ copiedLink: false, copiedCode: false }">
                    <div class="flex items-center bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl px-3 py-2 gap-2">
                        <span class="text-[10px] font-black uppercase tracking-wider text-gray-500">Link:</span>
                        <span class="text-xs font-mono text-gray-800 dark:text-gray-200 max-w-[260px] truncate">{{ $activeInviteSlot->invitation_url }}</span>
                        <button type="button"
                                @click="navigator.clipboard.writeText(@js($activeInviteSlot->invitation_url)); copiedLink = true; setTimeout(() => copiedLink = false, 2000)"
                                class="px-2 py-1 rounded-lg bg-[var(--theme-color-15)] hover:bg-[var(--theme-color-30)] text-[var(--theme-color)] text-[10px] font-black uppercase cursor-pointer">
                            <span x-text="copiedLink ? 'Kopiert!' : 'Kopieren'"></span>
                        </button>
                    </div>

                    <div class="flex items-center bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl px-3 py-2 gap-2">
                        <span class="text-[10px] font-black uppercase tracking-wider text-gray-500">6-stelliger Code:</span>
                        <span class="text-sm font-mono font-black tracking-widest text-[var(--theme-color)]">{{ $activeInviteSlot->verification_code }}</span>
                        <button type="button"
                                @click="navigator.clipboard.writeText(@js($activeInviteSlot->verification_code)); copiedCode = true; setTimeout(() => copiedCode = false, 2000)"
                                class="px-2 py-1 rounded-lg bg-[var(--theme-color-15)] hover:bg-[var(--theme-color-30)] text-[var(--theme-color)] text-[10px] font-black uppercase cursor-pointer">
                            <span x-text="copiedCode ? 'Kopiert!' : 'Code kopieren'"></span>
                        </button>
                    </div>

                    <button type="button" wire:click="$set('activeInviteId', null)" class="p-2 rounded-xl bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white cursor-pointer">
                        <x-heroicon-m-x-mark class="w-4 h-4" />
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- 3. EINZEILIGE FILTER- & AKTIONS-LEISTE --}}
    <div class="bg-white dark:bg-gray-900/90 backdrop-blur-xl rounded-2xl border border-gray-200 dark:border-gray-800 px-5 py-3.5 shadow-sm dark:shadow-lg transition-colors">
        <div class="flex flex-col xl:flex-row xl:items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-2.5">
                <div class="relative w-64">
                    <x-heroicon-m-magnifying-glass class="w-4 h-4 text-gray-500 absolute left-3 top-1/2 -translate-y-1/2" />
                    <input type="text" wire:model.live.debounce.250ms="search"
                           placeholder="Name, E-Mail, Vertrags-Nr. ..."
                           class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 text-gray-900 dark:text-white text-xs rounded-xl pl-9 pr-3 py-2 focus:border-[var(--theme-color)] outline-none" />
                </div>

                <div class="inline-flex rounded-xl bg-gray-100 dark:bg-gray-950 p-1 border border-gray-200 dark:border-gray-800">
                    <button type="button" wire:click="$set('filterStatus', 'active')"
                            class="px-3 py-1.5 rounded-lg text-[11px] font-bold transition cursor-pointer {{ $filterStatus === 'active' ? 'bg-[var(--theme-color)] text-gray-950' : 'text-gray-500 hover:text-gray-900 dark:hover:text-white' }}">
                        Aktiv & Einladungen
                    </button>
                    <button type="button" wire:click="$set('filterStatus', 'pending')"
                            class="px-3 py-1.5 rounded-lg text-[11px] font-bold transition cursor-pointer {{ $filterStatus === 'pending' ? 'bg-[var(--theme-color)] text-gray-950' : 'text-gray-500 hover:text-gray-900 dark:hover:text-white' }}">
                        Offene Einladungen ({{ $pendingInvitesCount }})
                    </button>
                    <button type="button" wire:click="$set('filterStatus', 'archived')"
                            class="px-3 py-1.5 rounded-lg text-[11px] font-bold transition cursor-pointer {{ $filterStatus === 'archived' ? 'bg-[var(--theme-color)] text-gray-950' : 'text-gray-500 hover:text-gray-900 dark:hover:text-white' }}">
                        Archiv
                    </button>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2.5">
                <div class="flex items-center gap-1.5">
                    <span class="text-[10px] font-black uppercase tracking-widest text-gray-500">Abrechnungsmonat:</span>
                    <select wire:model.live="selectedMonth"
                            class="bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 text-gray-900 dark:text-white text-xs font-bold rounded-xl px-3 py-2 focus:border-[var(--theme-color)] outline-none cursor-pointer">
                        @foreach($monthNames as $mNum => $mLabel)
                            <option value="{{ $mNum }}">{{ $mLabel }}</option>
                        @endforeach
                    </select>
                    <select wire:model.live="selectedYear"
                            class="bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 text-gray-900 dark:text-white text-xs font-bold rounded-xl px-3 py-2 focus:border-[var(--theme-color)] outline-none cursor-pointer">
                        @for($y = now()->year + 1; $y >= 2024; $y--)
                            <option value="{{ $y }}">{{ $y }}</option>
                        @endfor
                    </select>
                </div>

                <button type="button" wire:click="openInviteModal"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-[var(--theme-color)] hover:opacity-90 text-gray-950 text-xs font-black uppercase tracking-wider shadow-sm dark:shadow-md transition cursor-pointer">
                    <x-heroicon-s-user-plus class="w-4 h-4" />
                    <span>+ Mitarbeiter einladen</span>
                </button>
            </div>
        </div>
    </div>

    {{-- 4. INLINE MODAL: NEUEN MITARBEITER PER LINK EINLADEN --}}
    @if($showInviteModal)
        <div class="bg-white dark:bg-gray-900/95 rounded-3xl border-2 border-[var(--theme-color-40)] p-6 shadow-xl dark:shadow-2xl space-y-5 transition-colors">
            <div class="flex items-center justify-between border-b border-gray-200 dark:border-gray-800 pb-4">
                <div>
                    <h2 class="text-lg font-serif font-bold text-gray-900 dark:text-white">Neuen Mitarbeiter per Onboarding-Link einladen</h2>
                    <p class="text-xs text-gray-500">Lege die Vertrags-Eckdaten fest. Der Mitarbeiter füllt seine Steuer-/SV-Daten selbst aus und unterschreibt digital.</p>
                </div>
                <button type="button" wire:click="$set('showInviteModal', false)" class="p-2 rounded-xl bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white cursor-pointer">
                    <x-heroicon-m-x-mark class="w-5 h-5" />
                </button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="text-[10px] font-black uppercase tracking-wider text-gray-600 dark:text-gray-400">Name / Kurzbezeichnung *</label>
                    <input type="text" wire:model="inviteLabel" placeholder="z. B. Jonas Mitarbeiter"
                           class="w-full mt-1 bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl px-3.5 py-2.5 text-xs text-gray-900 dark:text-white focus:border-[var(--theme-color)] outline-none" />
                </div>

                <div>
                    <label class="text-[10px] font-black uppercase tracking-wider text-gray-600 dark:text-gray-400">Tätigkeit / Position *</label>
                    <input type="text" wire:model="inviteJobTitle"
                           class="w-full mt-1 bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl px-3.5 py-2.5 text-xs text-gray-900 dark:text-white focus:border-[var(--theme-color)] outline-none" />
                </div>

                <div>
                    <label class="text-[10px] font-black uppercase tracking-wider text-gray-600 dark:text-gray-400">Beschäftigungsart *</label>
                    <select wire:model.live="inviteEmploymentType"
                            class="w-full mt-1 bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl px-3.5 py-2.5 text-xs text-gray-900 dark:text-white focus:border-[var(--theme-color)] outline-none">
                        <option value="minijob">Minijob (Geringfügig bis 556,00 €)</option>
                        <option value="part_time">Teilzeit (Sozialversicherungspflichtig)</option>
                        <option value="full_time">Vollzeit (Sozialversicherungspflichtig)</option>
                        <option value="working_student">Werkstudent/in</option>
                        <option value="short_term">Kurzfristige Beschäftigung</option>
                    </select>
                </div>

                <div>
                    <label class="text-[10px] font-black uppercase tracking-wider text-gray-600 dark:text-gray-400">Vergütungsmodell</label>
                    <select wire:model.live="inviteCompensationType"
                            class="w-full mt-1 bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl px-3.5 py-2.5 text-xs text-gray-900 dark:text-white focus:border-[var(--theme-color)] outline-none">
                        <option value="hourly">Stundenlohn (nach Zeiterfassung)</option>
                        <option value="fixed_salary">Festes Monatsgehalt (Brutto)</option>
                    </select>
                </div>

                <div>
                    <label class="text-[10px] font-black uppercase tracking-wider text-gray-600 dark:text-gray-400">Brutto-Stundenlohn (€) *</label>
                    <input type="text" wire:model="inviteHourlyRateEur"
                           class="w-full mt-1 bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl px-3.5 py-2.5 text-xs font-mono text-gray-900 dark:text-white focus:border-[var(--theme-color)] outline-none" />
                    @error('inviteHourlyRateEur') <span class="text-[10px] text-rose-600 dark:text-rose-400 font-bold">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="text-[10px] font-black uppercase tracking-wider text-gray-600 dark:text-gray-400">Monats-Brutto Richtwert / Festgehalt (€)</label>
                    <input type="text" wire:model="inviteMonthlyGrossEur"
                           class="w-full mt-1 bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl px-3.5 py-2.5 text-xs font-mono text-gray-900 dark:text-white focus:border-[var(--theme-color)] outline-none" />
                </div>

                <div>
                    <label class="text-[10px] font-black uppercase tracking-wider text-gray-600 dark:text-gray-400">Wochenstunden (Soll)</label>
                    <input type="text" wire:model="inviteWeeklyHours"
                           class="w-full mt-1 bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl px-3.5 py-2.5 text-xs font-mono text-gray-900 dark:text-white focus:border-[var(--theme-color)] outline-none" />
                </div>

                <div>
                    <label class="text-[10px] font-black uppercase tracking-wider text-gray-600 dark:text-gray-400">Urlaubstage / Jahr</label>
                    <input type="number" wire:model="inviteVacationDays"
                           class="w-full mt-1 bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl px-3.5 py-2.5 text-xs font-mono text-gray-900 dark:text-white focus:border-[var(--theme-color)] outline-none" />
                </div>

                <div>
                    <label class="text-[10px] font-black uppercase tracking-wider text-gray-600 dark:text-gray-400">Eintrittsdatum</label>
                    <input type="date" wire:model="inviteStartDate"
                           class="w-full mt-1 bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl px-3.5 py-2.5 text-xs text-gray-900 dark:text-white focus:border-[var(--theme-color)] outline-none" />
                </div>
            </div>

            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pt-2 border-t border-gray-200 dark:border-gray-800">
                <div class="flex flex-wrap items-center gap-4">
                    <label class="inline-flex items-center gap-2 cursor-pointer text-xs text-gray-700 dark:text-gray-300">
                        <input type="checkbox" wire:model.live="inviteIsFixedTerm" class="rounded bg-white dark:bg-gray-950 border-gray-300 dark:border-gray-700 text-[var(--theme-color)]" />
                        <span>Befristeter Arbeitsvertrag (§ 14 TzBfG)</span>
                    </label>

                    @if($inviteIsFixedTerm)
                        <div class="flex items-center gap-2">
                            <span class="text-[10px] font-bold text-amber-600 dark:text-amber-400 uppercase">Enddatum:</span>
                            <input type="date" wire:model="inviteEndDate"
                                   class="bg-gray-50 dark:bg-gray-950 border border-amber-500/40 rounded-lg px-2.5 py-1 text-xs text-gray-900 dark:text-white" />
                        </div>
                        <span class="text-[11px] text-amber-700 dark:text-amber-300 font-semibold">
                            Hinweis (§ 14 Abs. 4 TzBfG): Befristete Verträge müssen zusätzlich auf Papier im Original unterschrieben werden!
                        </span>
                    @endif
                </div>

                <div class="flex items-center gap-2.5">
                    <button type="button" wire:click="$set('showInviteModal', false)"
                            class="px-4 py-2 rounded-xl bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 text-xs font-bold cursor-pointer">
                        Abbrechen
                    </button>
                    <button type="button" wire:click="createInvitation"
                            class="px-5 py-2 rounded-xl bg-[var(--theme-color)] text-gray-950 text-xs font-black uppercase tracking-wider shadow-md cursor-pointer">
                        Einladungslink & Code erzeugen
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- 5. ZENTRALE MITARBEITER- & LOHN-TABELLE --}}
    <div class="bg-white dark:bg-gray-900/85 backdrop-blur-xl rounded-3xl border border-gray-200 dark:border-gray-800 overflow-hidden shadow-sm dark:shadow-xl transition-colors">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-gray-800 bg-gray-50/70 dark:bg-gray-950/60 text-[10px] font-black uppercase tracking-wider text-gray-500">
                        <th class="py-3.5 px-5">Mitarbeiter & Vertrag</th>
                        <th class="py-3.5 px-4">Konditionen & Status</th>
                        <th class="py-3.5 px-4">Zeiterfassung ({{ $monthNames[$selectedMonth] ?? '' }})</th>
                        <th class="py-3.5 px-4 text-center">Personalakte (PDF)</th>
                        <th class="py-3.5 px-5 text-right">Lohnabrechnung & Aktion</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800/70 text-xs">
                    @forelse($rows as $row)
                        @php
                            /** @var \App\Models\Employee\EmployeeContract $contract */
                            $contract = $row['contract'];
                            $employee = $contract->employee;
                            $stats = $row['stats'];
                            /** @var \App\Models\Employee\EmployeePayslip|null $payslip */
                            $payslip = $row['payslip'];
                            $fullName = $employee ? trim($employee->first_name . ' ' . $employee->last_name) : $contract->invitation_label;
                        @endphp
                        <tr class="hover:bg-gray-50/80 dark:hover:bg-gray-800/40 transition">
                            {{-- Spalte 1: Mitarbeiter --}}
                            <td class="py-4 px-5">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-xl bg-[var(--theme-color-15)] border border-[var(--theme-color-30)] flex items-center justify-center text-[var(--theme-color)] font-serif font-bold text-sm shrink-0">
                                        {{ mb_substr($fullName ?: 'M', 0, 1) }}
                                    </div>
                                    <div>
                                        <div class="font-bold text-gray-900 dark:text-white flex items-center gap-2">
                                            <span>{{ $fullName }}</span>
                                            <span class="px-2 py-0.5 rounded text-[9px] font-black uppercase bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-700">
                                                {{ $contract->contract_number ?: 'ENTWURF' }}
                                            </span>
                                        </div>
                                        <div class="text-[11px] text-gray-500 mt-0.5">
                                            {{ $contract->job_title }} • {{ $employee->email ?? 'Einladung noch nicht eingelöst' }}
                                        </div>
                                    </div>
                                </div>
                            </td>

                            {{-- Spalte 2: Konditionen & Onboarding-Status --}}
                            <td class="py-4 px-4">
                                <div class="flex flex-wrap items-center gap-1.5 mb-1">
                                    <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-[var(--theme-color-15)] text-[var(--theme-color)] border border-[var(--theme-color-30)]">
                                        {{ $contract->employment_type_label }}
                                    </span>
                                    @if($contract->signed_at)
                                        <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-400 dark:border-emerald-500/20">
                                            Unterschrieben {{ $contract->signed_at->format('d.m.Y') }}
                                        </span>
                                    @else
                                        <button type="button" wire:click="showInvitationCredentials('{{ $contract->id }}')"
                                                class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-amber-50 text-amber-800 border border-amber-200 hover:bg-amber-100 dark:bg-amber-500/15 dark:text-amber-300 dark:border-amber-500/30 dark:hover:bg-amber-500/25 cursor-pointer">
                                            Einladungslink & Code anzeigen
                                        </button>
                                    @endif
                                </div>
                                <div class="text-[11px] text-gray-500 dark:text-gray-400 font-mono">
                                    @if($contract->compensation_type === 'fixed_salary')
                                        Festgehalt: {{ number_format($contract->monthly_gross_cents / 100, 2, ',', '.') }} €
                                    @else
                                        {{ number_format($contract->hourly_rate_cents / 100, 2, ',', '.') }} € / Std.
                                    @endif
                                    • {{ number_format((float) $contract->weekly_hours, 1, ',', '.') }} Std./Wo. • {{ $contract->remaining_vacation_days }}/{{ $contract->vacation_days_per_year }} Urlaubstage
                                </div>
                            </td>

                            {{-- Spalte 3: Monatsstunden & Brutto-Vorschau --}}
                            <td class="py-4 px-4">
                                <div class="font-mono font-bold text-gray-900 dark:text-white">
                                    {{ $stats['worked_hours_formatted'] }}
                                    <span class="text-gray-500 font-normal">/ {{ number_format($stats['target_hours_decimal'], 1, ',', '.') }} Soll-Std.</span>
                                </div>
                                <div class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">
                                    Brutto-Anspruch: <strong class="text-gray-900 dark:text-white font-mono">{{ number_format($stats['estimated_gross_cents'] / 100, 2, ',', '.') }} €</strong>
                                    @if($stats['minijob_warning'])
                                        <span class="ml-1 px-1.5 py-0.5 rounded bg-rose-50 text-rose-700 border border-rose-200 dark:bg-rose-500/20 dark:text-rose-300 dark:border-rose-500/30 text-[9px] font-black uppercase">
                                            > 556 € Minijob-Grenze!
                                        </span>
                                    @endif
                                </div>
                            </td>

                            {{-- Spalte 4: Personalakte PDFs --}}
                            <td class="py-4 px-4 text-center">
                                @if($contract->signed_at)
                                    <div class="inline-flex items-center gap-1.5">
                                        <a href="{{ route('admin.employees.contract.pdf', ['contract' => $contract->id]) }}" target="_blank"
                                           class="px-2.5 py-1 rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-800 dark:text-gray-200 text-[10px] font-bold border border-gray-200 dark:border-gray-700 transition-colors"
                                           title="Arbeitsvertrag PDF (§ 2 NachwG)">
                                            Vertrag PDF
                                        </a>
                                        <a href="{{ route('admin.employees.questionnaire.pdf', ['contract' => $contract->id]) }}" target="_blank"
                                           class="px-2.5 py-1 rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-800 dark:text-gray-200 text-[10px] font-bold border border-gray-200 dark:border-gray-700 transition-colors"
                                           title="Personalfragebogen PDF für Steuerberater">
                                            Personalbogen
                                        </a>
                                        <a href="{{ route('admin.employees.timesheet.pdf', ['contract' => $contract->id, 'year' => $selectedYear, 'month' => $selectedMonth]) }}" target="_blank"
                                           class="px-2.5 py-1 rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-800 dark:text-gray-200 text-[10px] font-bold border border-gray-200 dark:border-gray-700 transition-colors"
                                           title="Monats-Stundenzettel PDF (§ 17 MiLoG)">
                                            Stundenzettel
                                        </a>
                                    </div>
                                @else
                                    <button type="button" wire:click="regenerateInvite('{{ $contract->id }}')"
                                            class="px-2.5 py-1 rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-700 text-[10px] font-bold cursor-pointer">
                                        Link erneuern
                                    </button>
                                @endif
                            </td>

                            {{-- Spalte 5: Lohnabrechnung & Aktionen --}}
                            <td class="py-4 px-5 text-right">
                                <div class="inline-flex items-center justify-end gap-2">
                                    @if($payslip)
                                        @if($payslip->status === 'paid')
                                            <span class="px-2.5 py-1 rounded-lg bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-400 dark:border-emerald-500/30 text-[10px] font-black uppercase">
                                                Überwiesen ({{ number_format($payslip->net_payout_cents / 100, 2, ',', '.') }} €)
                                            </span>
                                        @else
                                            <span class="px-2.5 py-1 rounded-lg bg-amber-50 text-amber-800 border border-amber-200 dark:bg-amber-500/15 dark:text-amber-300 dark:border-amber-500/30 text-[10px] font-black uppercase">
                                                Offen: {{ number_format($payslip->net_payout_cents / 100, 2, ',', '.') }} €
                                            </span>
                                        @endif
                                        <a href="{{ route('admin.employees.payslip.pdf', ['payslip' => $payslip->id]) }}" target="_blank"
                                           class="px-2.5 py-1 rounded-lg bg-cyan-50 hover:bg-cyan-100 text-cyan-800 border border-cyan-200 dark:bg-cyan-500/15 dark:hover:bg-cyan-500/25 dark:text-cyan-300 dark:border-cyan-500/30 text-[10px] font-bold">
                                            Lohnzettel PDF
                                        </a>
                                    @endif

                                    @if($contract->signed_at)
                                        <button type="button" wire:click="openPayrollPanel('{{ $contract->id }}')"
                                                class="px-3 py-1.5 rounded-xl bg-[var(--theme-color)] hover:opacity-90 text-gray-950 text-[10px] font-black uppercase tracking-wider cursor-pointer">
                                            {{ $payslip ? 'Lohn bearbeiten' : 'Lohn abrechnen' }}
                                        </button>
                                    @endif

                                    <button type="button" wire:click="startEditContract('{{ $contract->id }}')"
                                            class="p-1.5 rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white border border-gray-200 dark:border-gray-700 cursor-pointer"
                                            title="Vertragskonditionen bearbeiten">
                                        <x-heroicon-m-pencil-square class="w-4 h-4" />
                                    </button>

                                    <button type="button" wire:click="toggleArchive('{{ $contract->id }}')"
                                            class="p-1.5 rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-600 dark:text-gray-400 hover:text-rose-500 dark:hover:text-rose-400 border border-gray-200 dark:border-gray-700 cursor-pointer"
                                            title="{{ $contract->is_archived ? 'Wiederherstellen' : 'Archivieren' }}">
                                        <x-heroicon-m-archive-box class="w-4 h-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>

                        {{-- INLINE PANEL: VERTRAGSKONDITIONEN BEARBEITEN --}}
                        @if($editingContractId === $contract->id)
                            <tr class="bg-gray-50/90 dark:bg-gray-950/90">
                                <td colspan="5" class="p-5 border-l-4 border-[var(--theme-color)]">
                                    <div class="grid grid-cols-1 sm:grid-cols-6 gap-3 items-end">
                                        <div class="sm:col-span-2">
                                            <label class="text-[10px] font-black uppercase text-gray-600 dark:text-gray-400">Position / Tätigkeit</label>
                                            <input type="text" wire:model="editJobTitle" class="w-full mt-1 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl px-3 py-2 text-xs text-gray-900 dark:text-white" />
                                        </div>
                                        <div>
                                            <label class="text-[10px] font-black uppercase text-gray-600 dark:text-gray-400">Stundenlohn (€)</label>
                                            <input type="text" wire:model="editHourlyRateEur" class="w-full mt-1 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl px-3 py-2 text-xs font-mono text-gray-900 dark:text-white" />
                                        </div>
                                        <div>
                                            <label class="text-[10px] font-black uppercase text-gray-600 dark:text-gray-400">Wochenstunden</label>
                                            <input type="text" wire:model="editWeeklyHours" class="w-full mt-1 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl px-3 py-2 text-xs font-mono text-gray-900 dark:text-white" />
                                        </div>
                                        <div>
                                            <label class="text-[10px] font-black uppercase text-gray-600 dark:text-gray-400">Urlaub (Genommen / Ges.)</label>
                                            <div class="flex items-center gap-1 mt-1">
                                                <input type="number" wire:model="editVacationTaken" class="w-1/2 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl px-2 py-2 text-xs font-mono text-gray-900 dark:text-white" />
                                                <input type="number" wire:model="editVacationDays" class="w-1/2 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl px-2 py-2 text-xs font-mono text-gray-900 dark:text-white" />
                                            </div>
                                        </div>
                                        <div class="flex items-center justify-end gap-2">
                                            <button type="button" wire:click="cancelEditContract" class="px-3 py-2 rounded-xl bg-gray-200 hover:bg-gray-300 dark:bg-gray-800 text-gray-700 dark:text-gray-300 text-xs font-bold cursor-pointer">Abbrechen</button>
                                            <button type="button" wire:click="saveEditContract" class="px-4 py-2 rounded-xl bg-[var(--theme-color)] text-gray-950 text-xs font-black uppercase cursor-pointer">Speichern</button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endif

                        {{-- INLINE PANEL: VORBEREITENDE LOHNABRECHNUNG, DATEV-UPLOAD & SEPA-AUSZAHLUNG --}}
                        @if($activePayrollContractId === $contract->id)
                            <tr class="bg-gray-50/95 dark:bg-gray-950/95">
                                <td colspan="5" class="p-6 border-l-4 border-[var(--theme-color)]">
                                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                                        <div class="lg:col-span-2 space-y-4">
                                            <div class="flex items-center justify-between">
                                                <div>
                                                    <h3 class="text-sm font-bold text-gray-900 dark:text-white">
                                                        Lohnabrechnung {{ $monthNames[$selectedMonth] ?? '' }} {{ $selectedYear }} — {{ $fullName }}
                                                    </h3>
                                                    <p class="text-[11px] text-gray-500 dark:text-gray-400">
                                                        Erfasste Arbeitszeit: <strong>{{ $stats['worked_hours_formatted'] }}</strong> ({{ $stats['entries_count'] }} Schichten) • Steuerklasse {{ $contract->tax_class }} • {{ $contract->health_insurance_name ?: 'Minijob-Zentrale' }}
                                                    </p>
                                                </div>
                                            </div>

                                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                                <div>
                                                    <label class="text-[10px] font-black uppercase text-gray-600 dark:text-gray-400">Steuer-Brutto (€)</label>
                                                    <input type="text" wire:model="payrollGrossEur"
                                                           class="w-full mt-1 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl px-3 py-2 text-xs font-mono text-gray-900 dark:text-white" />
                                                </div>
                                                <div>
                                                    <label class="text-[10px] font-black uppercase text-gray-600 dark:text-gray-400">Netto-Überweisung an Mitarbeiter (€)</label>
                                                    <input type="text" wire:model="payrollNetEur"
                                                           class="w-full mt-1 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl px-3 py-2 text-xs font-mono text-emerald-600 dark:text-emerald-400 font-bold" />
                                                </div>
                                                <div>
                                                    <label class="text-[10px] font-black uppercase text-gray-600 dark:text-gray-400">AG-Nebenkosten / Pauschale (€)</label>
                                                    <input type="text" wire:model="payrollEmployerChargesEur"
                                                           class="w-full mt-1 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl px-3 py-2 text-xs font-mono text-gray-700 dark:text-gray-300" />
                                                </div>
                                            </div>

                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                                <div>
                                                    <label class="text-[10px] font-black uppercase text-gray-600 dark:text-gray-400">Optional: Offizielle DATEV / Minijob-Zentrale PDF hochladen</label>
                                                    <input type="file" wire:model="externalPayslipUpload" accept="application/pdf"
                                                           class="w-full mt-1 text-xs text-gray-600 dark:text-gray-400 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-[10px] file:font-black file:bg-gray-200 dark:file:bg-gray-800 file:text-gray-900 dark:file:text-white" />
                                                </div>
                                                <div>
                                                    <label class="text-[10px] font-black uppercase text-gray-600 dark:text-gray-400">Notiz auf Abrechnung</label>
                                                    <input type="text" wire:model="payrollNotes" placeholder="z. B. inkl. Feiertagszuschlag / Minijob-Pauschale"
                                                           class="w-full mt-1 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl px-3 py-2 text-xs text-gray-900 dark:text-white" />
                                                </div>
                                            </div>

                                            <div class="flex flex-wrap items-center gap-3 pt-2">
                                                <button type="button" wire:click="generateMonthlyPayslip"
                                                        class="px-4 py-2 rounded-xl bg-[var(--theme-color)] text-gray-950 text-xs font-black uppercase tracking-wider cursor-pointer">
                                                    Lohnabrechnung & Stundenzettel speichern
                                                </button>

                                                @if($payslip && $payslip->status === 'open')
                                                    <button type="button" wire:click="markPayslipPaid('{{ $payslip->id }}')"
                                                            class="px-4 py-2 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-white dark:text-gray-950 text-xs font-black uppercase tracking-wider cursor-pointer">
                                                        Als überwiesen markieren & in Buchhaltung buchen
                                                    </button>
                                                @endif
                                            </div>
                                        </div>

                                        {{-- Rechts: SEPA-GiroCode & Bankdaten --}}
                                        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-4 flex flex-col justify-between">
                                            <div>
                                                <div class="text-[10px] font-black uppercase tracking-widest text-[var(--theme-color)]">SEPA-Gehaltsüberweisung</div>
                                                <div class="text-xs font-bold text-gray-900 dark:text-white mt-1">{{ $contract->account_holder ?: $fullName }}</div>
                                                <div class="text-xs font-mono text-gray-600 dark:text-gray-300 mt-0.5">{{ $contract->masked_iban ?: 'Keine IBAN hinterlegt' }}</div>
                                            </div>

                                            @if($row['epc_qr_svg'])
                                                <div class="my-3 flex items-center gap-3">
                                                    <div class="bg-white p-2 rounded-xl border border-gray-200 shrink-0">
                                                        {!! $row['epc_qr_svg'] !!}
                                                    </div>
                                                    <div class="text-[11px] text-gray-600 dark:text-gray-400">
                                                        <strong>GiroCode scannen:</strong><br>
                                                        Überweist exakt <strong>{{ number_format(($payslip->net_payout_cents ?? 0) / 100, 2, ',', '.') }} €</strong> mit Beleg-Nr. {{ $payslip->payslip_number ?? '' }}.
                                                    </div>
                                                </div>
                                            @else
                                                <div class="text-[11px] text-gray-500 my-4">
                                                    Speichere zuerst die Lohnabrechnung, um den SEPA-QR-Code für die Banking-App zu generieren.
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="5" class="py-10 text-center text-gray-500 text-xs">
                                Noch keine Mitarbeiter in dieser Ansicht vorhanden. Klicke oben rechts auf <strong>+ Mitarbeiter einladen</strong>.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- UNTEN MITTIG: ERKLÄRUNG ANZEIGEN --}}
        <div class="border-t border-gray-200 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-950/50 px-5 py-3 text-center">
            <button type="button" wire:click="$toggle('showExplanation')"
                    class="inline-flex items-center gap-1.5 text-xs font-bold text-gray-500 hover:text-[var(--theme-color)] transition cursor-pointer">
                <span>{{ $showExplanation ? 'Erklärung ausblenden' : 'Erklärung anzeigen' }}</span>
                <x-heroicon-m-chevron-down class="w-4 h-4 transition-transform {{ $showExplanation ? 'rotate-180' : '' }}" />
            </button>

            @if($showExplanation)
                <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-800 text-left grid grid-cols-1 md:grid-cols-3 gap-4 text-xs text-gray-600 dark:text-gray-400">
                    <div class="bg-white dark:bg-gray-900/60 border border-gray-200 dark:border-gray-800 rounded-xl p-3.5">
                        <div class="font-bold text-gray-900 dark:text-white mb-1">1. Digitaler Arbeitsvertrag (§ 2 NachwG / BEG IV)</div>
                        Unbefristete Arbeitsverträge sind durch die digitale Unterschrift im Onboarding und den dauerhaften PDF-Abruf im Mitarbeiter-Dashboard rechtlich wirksam. Bei befristeten Verträgen (§ 14 Abs. 4 TzBfG) muss das PDF zusätzlich auf Papier unterschrieben werden.
                    </div>
                    <div class="bg-white dark:bg-gray-900/60 border border-gray-200 dark:border-gray-800 rounded-xl p-3.5">
                        <div class="font-bold text-gray-900 dark:text-white mb-1">2. Arbeitszeiterfassung (§ 17 MiLoG & § 4 ArbZG)</div>
                        Alle Schichten des Mitarbeiters werden inkl. gesetzlicher Ruhepausen (ab 6 Std. = 30 Min., ab 9 Std. = 45 Min.) protokolliert und als Stundenzettel-PDF für Zoll-/Rentenversicherungsprüfungen archiviert.
                    </div>
                    <div class="bg-white dark:bg-gray-900/60 border border-gray-200 dark:border-gray-800 rounded-xl p-3.5">
                        <div class="font-bold text-gray-900 dark:text-white mb-1">3. Brücke zu Buchhaltung & Steuerberater</div>
                        Offene Lohnzettel erscheinen automatisch auf dem Master-Dashboard in der To-do-Liste. Sobald du auf „Als überwiesen markieren“ klickst, landet der Beleg automatisch in der Buchhaltung (SKR03 4120/4190) und im Steuerberater-Dashboard.
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
