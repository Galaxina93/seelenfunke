<div class="min-h-screen bg-slate-50 dark:bg-gray-950 text-gray-900 dark:text-gray-100 p-4 sm:p-6 lg:p-8 space-y-5 transition-colors">

    {{-- Top-Leiste --}}
    <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-4 sm:p-5 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
        <div class="flex items-center gap-3">
            <h1 class="text-xl font-bold text-gray-900 dark:text-white">Personal</h1>
            <span class="px-2.5 py-0.5 rounded-lg bg-gray-100 dark:bg-gray-800 text-xs font-semibold text-gray-700 dark:text-gray-300">
                {{ $activeCount }} Aktiv
            </span>
            @if($pendingInvitesCount > 0)
                <span class="px-2.5 py-0.5 rounded-lg bg-amber-50 dark:bg-amber-500/10 text-xs font-semibold text-amber-700 dark:text-amber-300">
                    {{ $pendingInvitesCount }} Offen
                </span>
            @endif
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <select wire:model.live="selectedMonth"
                    class="bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl px-3 py-2 text-xs font-semibold text-gray-900 dark:text-white outline-none">
                @foreach([1=>'Januar',2=>'Februar',3=>'März',4=>'April',5=>'Mai',6=>'Juni',7=>'Juli',8=>'August',9=>'September',10=>'Oktober',11=>'November',12=>'Dezember'] as $mNum => $mLabel)
                    <option value="{{ $mNum }}">{{ $mLabel }}</option>
                @endforeach
            </select>

            <select wire:model.live="selectedYear"
                    class="bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl px-3 py-2 text-xs font-semibold text-gray-900 dark:text-white outline-none">
                @for($y = now()->year - 1; $y <= now()->year + 1; $y++)
                    <option value="{{ $y }}">{{ $y }}</option>
                @endfor
            </select>

            <button type="button" wire:click="openInviteModal"
                    class="px-4 py-2 rounded-xl bg-gray-900 hover:bg-gray-800 dark:bg-[#C5A059] dark:hover:bg-[#d4b06a] text-white dark:text-gray-950 font-bold text-xs transition cursor-pointer">
                + Mitarbeiter einladen
            </button>
        </div>
    </div>

    {{-- Feedback --}}
    @if (session()->has('success'))
        <div class="p-3.5 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/30 text-emerald-800 dark:text-emerald-300 text-xs font-semibold flex items-center justify-between">
            <span>✓ {{ session('success') }}</span>
        </div>
    @endif

    {{-- Offene Urlaubs- & Krankmeldungen (1-Klick Freigabe) --}}
    @if(isset($pendingLeaveEntries) && $pendingLeaveEntries->isNotEmpty())
        <div class="bg-amber-50/70 dark:bg-gray-900 border border-amber-200 dark:border-amber-500/30 rounded-2xl p-4 space-y-2">
            <div class="text-xs font-bold uppercase tracking-wider text-amber-800 dark:text-amber-300">
                Offene Anträge ({{ $pendingLeaveEntries->count() }})
            </div>
            <div class="divide-y divide-amber-200/60 dark:divide-gray-800">
                @foreach($pendingLeaveEntries as $leave)
                    <div class="py-2 flex flex-wrap items-center justify-between gap-3">
                        <div class="flex items-center gap-2.5 text-xs">
                            <span class="px-2 py-0.5 rounded-md font-bold {{ $leave->entry_type === 'sick' ? 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300' : 'bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300' }}">
                                {{ $leave->entry_type === 'sick' ? 'Krank' : 'Urlaub' }}
                            </span>
                            <span class="font-bold text-gray-900 dark:text-white">
                                {{ $leave->contract?->display_name ?? 'Mitarbeiter' }}
                            </span>
                            <span class="font-mono text-gray-600 dark:text-gray-300">
                                {{ $leave->work_date?->format('d.m.Y') }}
                            </span>
                            @if($leave->activity_note)
                                <span class="text-gray-500 dark:text-gray-400">• {{ $leave->activity_note }}</span>
                            @endif
                        </div>
                        <div class="flex items-center gap-1.5">
                            <button type="button" wire:click="approveLeave('{{ $leave->id }}')"
                                    class="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold cursor-pointer">
                                ✓ Genehmigen
                            </button>
                            <button type="button" wire:click="rejectLeave('{{ $leave->id }}')"
                                    class="px-3 py-1.5 rounded-lg bg-gray-200 hover:bg-gray-300 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 text-xs font-bold cursor-pointer">
                                ✕ Ablehnen
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Aktive Einladungs-Zugangsdaten (Schritt 1 & 2 zum Kopieren) --}}
    @if($activeInviteSlot && $activeInviteSlot->invitation_url)
        <div class="bg-white dark:bg-gray-900 border-2 border-gray-900 dark:border-[#C5A059] rounded-2xl p-4 sm:p-5 space-y-4">
            <div class="flex items-center justify-between">
                <div class="text-sm font-bold text-gray-900 dark:text-white">
                    Zugang für {{ $activeInviteSlot->display_name }}
                </div>
                <button type="button" wire:click="$set('activeInviteId', null)" class="text-xs font-bold text-gray-400 hover:text-gray-700 dark:hover:text-white cursor-pointer">
                    ✕ Schließen
                </button>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-3">
                <div class="lg:col-span-2 p-3 rounded-xl bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 flex items-center justify-between gap-2">
                    <div class="min-w-0">
                        <span class="text-[10px] font-bold uppercase text-gray-400 block">1. Link</span>
                        <input type="text" readonly value="{{ $activeInviteSlot->invitation_url }}"
                               class="w-full bg-transparent text-xs font-mono text-gray-900 dark:text-white outline-none truncate" />
                    </div>
                    <button type="button"
                            onclick="navigator.clipboard.writeText(@js($activeInviteSlot->invitation_url)); this.innerText='✓ Kopiert'; setTimeout(()=>this.innerText='Kopieren', 2000);"
                            class="px-3 py-2 rounded-lg bg-gray-900 dark:bg-[#C5A059] text-white dark:text-gray-950 text-xs font-bold shrink-0 cursor-pointer">
                        Kopieren
                    </button>
                </div>

                <div class="p-3 rounded-xl bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 flex items-center justify-between gap-2">
                    <div>
                        <span class="text-[10px] font-bold uppercase text-gray-400 block">2. Code</span>
                        <span class="text-base font-mono font-bold tracking-widest text-gray-900 dark:text-[#C5A059]">
                            {{ $activeInviteSlot->plain_invitation_code }}
                        </span>
                    </div>
                    <button type="button"
                            onclick="navigator.clipboard.writeText(@js($activeInviteSlot->plain_invitation_code)); this.innerText='✓ Kopiert'; setTimeout(()=>this.innerText='Kopieren', 2000);"
                            class="px-3 py-2 rounded-lg bg-gray-200 dark:bg-gray-800 text-gray-900 dark:text-white text-xs font-bold shrink-0 cursor-pointer">
                        Kopieren
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- 4 Kennzahlen (Matt & Clean) --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-4">
            <span class="text-[11px] font-semibold text-gray-400 block">Team</span>
            <div class="text-xl font-bold text-gray-900 dark:text-white mt-0.5">{{ $activeCount }}</div>
        </div>
        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-4">
            <span class="text-[11px] font-semibold text-gray-400 block">Stunden ({{ str_pad((string)$selectedMonth, 2, '0', STR_PAD_LEFT) }}/{{ $selectedYear }})</span>
            <div class="text-xl font-bold text-gray-900 dark:text-white mt-0.5">{{ number_format($totalWorkedHoursMonth, 1, ',', '.') }} Std.</div>
        </div>
        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-4">
            <span class="text-[11px] font-semibold text-gray-400 block">Offene Auszahlung</span>
            <div class="text-xl font-bold text-amber-600 dark:text-amber-400 mt-0.5">{{ number_format($openPayslipsNetCents / 100, 2, ',', '.') }} €</div>
        </div>
        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-4">
            <span class="text-[11px] font-semibold text-gray-400 block">Gesamtkosten Monat</span>
            <div class="text-xl font-bold text-gray-900 dark:text-white mt-0.5">{{ number_format($monthTotalEmployerCostCents / 100, 2, ',', '.') }} €</div>
        </div>
    </div>

    {{-- Filter & Suche --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div class="inline-flex rounded-xl bg-white dark:bg-gray-900 p-1 border border-gray-200 dark:border-gray-800">
            <button type="button" wire:click="$set('filterStatus', 'active')"
                    class="px-3 py-1.5 rounded-lg text-xs font-bold transition cursor-pointer {{ $filterStatus === 'active' ? 'bg-gray-900 text-white dark:bg-[#C5A059] dark:text-gray-950' : 'text-gray-500 hover:text-gray-900 dark:hover:text-white' }}">
                Aktiv
            </button>
            <button type="button" wire:click="$set('filterStatus', 'pending')"
                    class="px-3 py-1.5 rounded-lg text-xs font-bold transition cursor-pointer {{ $filterStatus === 'pending' ? 'bg-gray-900 text-white dark:bg-[#C5A059] dark:text-gray-950' : 'text-gray-500 hover:text-gray-900 dark:hover:text-white' }}">
                Einladungen ({{ $pendingInvitesCount }})
            </button>
            <button type="button" wire:click="$set('filterStatus', 'archived')"
                    class="px-3 py-1.5 rounded-lg text-xs font-bold transition cursor-pointer {{ $filterStatus === 'archived' ? 'bg-gray-900 text-white dark:bg-[#C5A059] dark:text-gray-950' : 'text-gray-500 hover:text-gray-900 dark:hover:text-white' }}">
                Archiv
            </button>
        </div>

        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Name suchen..."
               class="w-full sm:w-64 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl px-3.5 py-2 text-xs text-gray-900 dark:text-white outline-none" />
    </div>

    {{-- Mitarbeiter-Liste --}}
    <div class="space-y-3">
        @forelse($rows as $row)
            @php
                /** @var \App\Models\Employee\EmployeeContract $contract */
                $contract = $row['contract'];
                $stats = $row['stats'];
                $payslip = $row['payslip'];
                $epcQrSvg = $row['epc_qr_svg'];
                $isSigned = $contract->invitation_accepted_at !== null;
            @endphp

            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-4 sm:p-5 space-y-4">
                <div class="flex flex-col xl:flex-row xl:items-center xl:justify-between gap-4">
                    {{-- Name & 1-Klick Arbeitsbereiche --}}
                    <div class="space-y-2">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-base font-bold text-gray-900 dark:text-white">{{ $contract->display_name }}</span>
                            <span class="px-2 py-0.5 rounded-md text-[11px] font-semibold bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300">
                                {{ $contract->employment_type_label }}
                            </span>
                            @if(!$isSigned)
                                <span class="px-2 py-0.5 rounded-md text-[11px] font-semibold bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300">
                                    Einladung offen
                                </span>
                            @endif
                        </div>

                        <div class="text-xs text-gray-500 dark:text-gray-400">
                            {{ $contract->job_title }} • {{ number_format($contract->hourly_rate_cents / 100, 2, ',', '.') }} €/Std. • {{ number_format($contract->weekly_hours, 1, ',', '.') }} Std./Woche • Urlaub: {{ $contract->remaining_vacation_days }}/{{ $contract->vacation_days_per_year }} Tage frei
                        </div>

                        {{-- 1-Klick Berechtigungs-Schalter --}}
                        <div class="flex flex-wrap items-center gap-1.5 pt-1">
                            <button type="button" wire:click="togglePermission('{{ $contract->id }}', 'can_access_production')"
                                    class="px-2.5 py-1 rounded-lg text-[11px] font-bold border transition cursor-pointer {{ $contract->can_access_production ? 'bg-gray-900 text-white border-gray-900 dark:bg-[#C5A059] dark:text-gray-950 dark:border-[#C5A059]' : 'bg-gray-50 text-gray-400 border-gray-200 dark:bg-gray-950 dark:border-gray-800' }}">
                                {{ $contract->can_access_production ? '✓' : '○' }} Packtisch & Laser
                            </button>
                            <button type="button" wire:click="togglePermission('{{ $contract->id }}', 'can_access_support')"
                                    class="px-2.5 py-1 rounded-lg text-[11px] font-bold border transition cursor-pointer {{ $contract->can_access_support ? 'bg-gray-900 text-white border-gray-900 dark:bg-[#C5A059] dark:text-gray-950 dark:border-[#C5A059]' : 'bg-gray-50 text-gray-400 border-gray-200 dark:bg-gray-950 dark:border-gray-800' }}">
                                {{ $contract->can_access_support ? '✓' : '○' }} Support
                            </button>
                            <button type="button" wire:click="togglePermission('{{ $contract->id }}', 'can_access_inventory')"
                                    class="px-2.5 py-1 rounded-lg text-[11px] font-bold border transition cursor-pointer {{ $contract->can_access_inventory ? 'bg-gray-900 text-white border-gray-900 dark:bg-[#C5A059] dark:text-gray-950 dark:border-[#C5A059]' : 'bg-gray-50 text-gray-400 border-gray-200 dark:bg-gray-950 dark:border-gray-800' }}">
                                {{ $contract->can_access_inventory ? '✓' : '○' }} Lager & Bruch
                            </button>
                        </div>
                    </div>

                    {{-- Monatswerte & Aktionen --}}
                    <div class="flex flex-wrap items-center gap-3">
                        <div class="px-3 py-2 rounded-xl bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 text-xs">
                            <span class="text-gray-400 block text-[10px]">Stunden</span>
                            <strong class="text-gray-900 dark:text-white">{{ $stats['worked_hours_formatted'] }}</strong>
                        </div>

                        <div class="px-3 py-2 rounded-xl bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 text-xs">
                            <span class="text-gray-400 block text-[10px]">Auszahlung</span>
                            <strong class="text-gray-900 dark:text-white">
                                {{ number_format(($payslip ? $payslip->net_payout_cents : $stats['estimated_net_cents']) / 100, 2, ',', '.') }} €
                            </strong>
                        </div>

                        <div class="flex flex-wrap items-center gap-1.5">
                            @if(!$isSigned)
                                <button type="button" wire:click="showInvitationCredentials('{{ $contract->id }}')"
                                        class="px-3 py-2 rounded-xl bg-gray-900 dark:bg-[#C5A059] text-white dark:text-gray-950 text-xs font-bold cursor-pointer">
                                    Link & Code
                                </button>
                                <button type="button" wire:click="regenerateInvite('{{ $contract->id }}')"
                                        class="px-3 py-2 rounded-xl bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 text-xs font-semibold cursor-pointer">
                                    Neu
                                </button>
                            @else
                                <a href="{{ route('admin.employees.contract.pdf', ['contract' => $contract->id]) }}" target="_blank"
                                   class="px-3 py-2 rounded-xl bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-800 dark:text-gray-200 text-xs font-semibold">
                                    Vertrag
                                </a>
                                <a href="{{ route('admin.employees.questionnaire.pdf', ['contract' => $contract->id]) }}" target="_blank"
                                   class="px-3 py-2 rounded-xl bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-800 dark:text-gray-200 text-xs font-semibold">
                                    Personalbogen
                                </a>
                                <a href="{{ route('admin.employees.timesheet.pdf', ['contract' => $contract->id, 'year' => $selectedYear, 'month' => $selectedMonth]) }}" target="_blank"
                                   class="px-3 py-2 rounded-xl bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-800 dark:text-gray-200 text-xs font-semibold">
                                    Stundenzettel
                                </a>
                            @endif

                            <button type="button" wire:click="openPayrollPanel('{{ $contract->id }}')"
                                    class="px-3 py-2 rounded-xl bg-gray-900 hover:bg-gray-800 dark:bg-[#C5A059] dark:hover:bg-[#d4b06a] text-white dark:text-gray-950 text-xs font-bold cursor-pointer">
                                Lohnabrechnung
                            </button>

                            <button type="button" wire:click="startEditContract('{{ $contract->id }}')"
                                    class="px-3 py-2 rounded-xl bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 text-gray-700 dark:text-gray-300 text-xs font-semibold cursor-pointer">
                                Bearbeiten
                            </button>

                            <button type="button" wire:click="toggleArchive('{{ $contract->id }}')"
                                    class="px-2.5 py-2 rounded-xl bg-gray-100 hover:bg-rose-50 dark:bg-gray-800 text-gray-500 hover:text-rose-600 text-xs font-semibold cursor-pointer"
                                    title="Archivieren">
                                {{ $contract->is_archived ? 'Aktivieren' : 'Archiv' }}
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Bearbeiten-Panel --}}
                @if($editingContractId === $contract->id)
                    <div class="pt-4 border-t border-gray-100 dark:border-gray-800 space-y-3">
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3">
                            <div class="lg:col-span-2">
                                <label class="text-[11px] font-semibold text-gray-500">Bezeichnung</label>
                                <input type="text" wire:model="editJobTitle" class="w-full mt-1 bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl px-3 py-2 text-xs text-gray-900 dark:text-white" />
                            </div>
                            <div>
                                <label class="text-[11px] font-semibold text-gray-500">Art</label>
                                <select wire:model="editEmploymentType" class="w-full mt-1 bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl px-3 py-2 text-xs text-gray-900 dark:text-white">
                                    <option value="minijob">Minijob</option>
                                    <option value="part_time">Teilzeit</option>
                                    <option value="full_time">Vollzeit</option>
                                    <option value="working_student">Werkstudent</option>
                                    <option value="short_term">Kurzfristig</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-[11px] font-semibold text-gray-500">Stundenlohn (€)</label>
                                <input type="text" wire:model="editHourlyRateEur" class="w-full mt-1 bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl px-3 py-2 text-xs text-gray-900 dark:text-white" />
                            </div>
                            <div>
                                <label class="text-[11px] font-semibold text-gray-500">Std. / Woche</label>
                                <input type="text" wire:model="editWeeklyHours" class="w-full mt-1 bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl px-3 py-2 text-xs text-gray-900 dark:text-white" />
                            </div>
                            <div>
                                <label class="text-[11px] font-semibold text-gray-500">Urlaubstage / Genommen</label>
                                <div class="grid grid-cols-2 gap-1.5 mt-1">
                                    <input type="number" wire:model="editVacationDays" class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl px-2.5 py-2 text-xs text-gray-900 dark:text-white" />
                                    <input type="number" wire:model="editVacationTaken" class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl px-2.5 py-2 text-xs text-gray-900 dark:text-white" />
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end gap-2">
                            <button type="button" wire:click="cancelEditContract" class="px-3.5 py-1.5 rounded-xl bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 text-xs font-semibold cursor-pointer">
                                Abbrechen
                            </button>
                            <button type="button" wire:click="saveEditContract" class="px-4 py-1.5 rounded-xl bg-gray-900 dark:bg-[#C5A059] text-white dark:text-gray-950 text-xs font-bold cursor-pointer">
                                Speichern
                            </button>
                        </div>
                    </div>
                @endif

                {{-- Lohnabrechnungs- & SEPA-Panel --}}
                @if($activePayrollContractId === $contract->id)
                    <div class="pt-4 border-t border-gray-100 dark:border-gray-800 grid grid-cols-1 lg:grid-cols-3 gap-4">
                        <div class="lg:col-span-2 space-y-3">
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <div>
                                    <label class="text-[11px] font-semibold text-gray-500">1. Brutto (€)</label>
                                    <input type="text" wire:model="payrollGrossEur" class="w-full mt-1 bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl px-3 py-2 text-sm font-bold text-gray-900 dark:text-white" />
                                </div>
                                <div>
                                    <label class="text-[11px] font-semibold text-gray-500">2. Netto-Auszahlung (€)</label>
                                    <input type="text" wire:model="payrollNetEur" class="w-full mt-1 bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl px-3 py-2 text-sm font-bold text-emerald-700 dark:text-emerald-400" />
                                </div>
                                <div>
                                    <label class="text-[11px] font-semibold text-gray-500">3. AG-Abgaben (€)</label>
                                    <input type="text" wire:model="payrollEmployerChargesEur" class="w-full mt-1 bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl px-3 py-2 text-sm font-bold text-gray-900 dark:text-white" />
                                </div>
                            </div>

                            <div class="flex flex-wrap items-center gap-2 pt-1">
                                <button type="button" wire:click="generateMonthlyPayslip"
                                        class="px-4 py-2 rounded-xl bg-gray-900 dark:bg-[#C5A059] text-white dark:text-gray-950 font-bold text-xs cursor-pointer">
                                    1. Lohnzettel erstellen
                                </button>

                                @if($payslip)
                                    <a href="{{ route('admin.employees.payslip.pdf', ['payslip' => $payslip->id]) }}" target="_blank"
                                       class="px-3.5 py-2 rounded-xl bg-gray-100 dark:bg-gray-800 text-gray-800 dark:text-gray-200 text-xs font-semibold">
                                        Lohnzettel PDF
                                    </a>

                                    @if($payslip->status !== 'paid')
                                        <button type="button" wire:click="markPayslipPaid('{{ $payslip->id }}')"
                                                class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs cursor-pointer">
                                            2. ✓ Als überwiesen verbuchen
                                        </button>
                                    @else
                                        <span class="px-3 py-2 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 text-xs font-bold">
                                            ✓ Überwiesen am {{ $payslip->paid_at?->format('d.m.Y') }}
                                        </span>
                                    @endif
                                @endif
                            </div>
                        </div>

                        {{-- GiroCode zum Scannen --}}
                        <div class="bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl p-4 flex flex-col items-center justify-center text-center">
                            @if($payslip && $epcQrSvg)
                                <div class="bg-white p-2.5 rounded-xl border border-gray-200 mb-2">
                                    {!! $epcQrSvg !!}
                                </div>
                                <div class="text-sm font-bold text-gray-900 dark:text-white">
                                    {{ number_format($payslip->net_payout_cents / 100, 2, ',', '.') }} €
                                </div>
                                <div class="text-[11px] font-mono text-gray-500 mt-0.5">
                                    {{ $contract->masked_iban }}
                                </div>
                            @else
                                <span class="text-xs text-gray-400">
                                    Nach Klick auf „1. Lohnzettel erstellen“ erscheint hier der Banking-QR-Code.
                                </span>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        @empty
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-10 text-center space-y-3">
                <div class="text-sm font-bold text-gray-900 dark:text-white">Noch keine Mitarbeiter angelegt</div>
                <button type="button" wire:click="openInviteModal"
                        class="px-4 py-2 rounded-xl bg-gray-900 dark:bg-[#C5A059] text-white dark:text-gray-950 font-bold text-xs cursor-pointer">
                    + Mitarbeiter einladen
                </button>
            </div>
        @endforelse
    </div>

    {{-- Modal: Mitarbeiter einladen (Matt & Clean, Schritt für Schritt) --}}
    @if($showInviteModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60 backdrop-blur-xs p-4">
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl max-w-lg w-full p-6 space-y-5 shadow-xl">
                <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-800 pb-3">
                    <h2 class="text-base font-bold text-gray-900 dark:text-white">Mitarbeiter einladen</h2>
                    <button type="button" wire:click="$set('showInviteModal', false)" class="text-gray-400 hover:text-gray-900 dark:hover:text-white text-sm font-bold cursor-pointer">✕</button>
                </div>

                <div class="space-y-4">
                    {{-- 1. Wer? --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="text-[11px] font-bold text-gray-600 dark:text-gray-400">1. Name</label>
                            <input type="text" wire:model="inviteLabel" placeholder="z. B. Lisa Müller"
                                   class="w-full mt-1 bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl px-3 py-2 text-xs text-gray-900 dark:text-white outline-none" />
                        </div>
                        <div>
                            <label class="text-[11px] font-bold text-gray-600 dark:text-gray-400">E-Mail (optional für Direktversand)</label>
                            <input type="email" wire:model="inviteEmail" placeholder="lisa@beispiel.de"
                                   class="w-full mt-1 bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl px-3 py-2 text-xs text-gray-900 dark:text-white outline-none" />
                        </div>
                    </div>

                    {{-- 2. Vertrag --}}
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        <div class="col-span-2">
                            <label class="text-[11px] font-bold text-gray-600 dark:text-gray-400">2. Anstellungsart</label>
                            <select wire:model.live="inviteEmploymentType"
                                    class="w-full mt-1 bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl px-3 py-2 text-xs text-gray-900 dark:text-white outline-none">
                                <option value="minijob">Minijob (556 €)</option>
                                <option value="part_time">Teilzeit</option>
                                <option value="full_time">Vollzeit</option>
                                <option value="working_student">Werkstudent</option>
                                <option value="short_term">Kurzfristig</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-[11px] font-bold text-gray-600 dark:text-gray-400">€/Std.</label>
                            <input type="text" wire:model="inviteHourlyRateEur"
                                   class="w-full mt-1 bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl px-3 py-2 text-xs text-gray-900 dark:text-white outline-none" />
                        </div>
                        <div>
                            <label class="text-[11px] font-bold text-gray-600 dark:text-gray-400">Std./Woche</label>
                            <input type="text" wire:model="inviteWeeklyHours"
                                   class="w-full mt-1 bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl px-3 py-2 text-xs text-gray-900 dark:text-white outline-none" />
                        </div>
                    </div>
                    @error('inviteHourlyRateEur') <p class="text-[11px] text-rose-600">{{ $message }}</p> @enderror

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-[11px] font-bold text-gray-600 dark:text-gray-400">Startdatum</label>
                            <input type="date" wire:model="inviteStartDate"
                                   class="w-full mt-1 bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl px-3 py-2 text-xs text-gray-900 dark:text-white outline-none" />
                        </div>
                        <div>
                            <label class="text-[11px] font-bold text-gray-600 dark:text-gray-400">Urlaubstage / Jahr</label>
                            <input type="number" wire:model="inviteVacationDays"
                                   class="w-full mt-1 bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl px-3 py-2 text-xs text-gray-900 dark:text-white outline-none" />
                        </div>
                    </div>

                    {{-- 3. Was darf der Mitarbeiter sehen & machen? --}}
                    <div class="space-y-2 pt-1">
                        <label class="text-[11px] font-bold text-gray-600 dark:text-gray-400 block">3. Freigeschaltete Arbeitsbereiche</label>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                            <label class="flex items-center gap-2 p-3 rounded-xl border cursor-pointer transition {{ $inviteCanAccessProduction ? 'border-gray-900 bg-gray-50 dark:border-[#C5A059] dark:bg-gray-950' : 'border-gray-200 dark:border-gray-800' }}">
                                <input type="checkbox" wire:model.live="inviteCanAccessProduction" class="rounded text-gray-900 dark:text-[#C5A059]" />
                                <span class="text-xs font-bold text-gray-900 dark:text-white">Packtisch & Laser</span>
                            </label>
                            <label class="flex items-center gap-2 p-3 rounded-xl border cursor-pointer transition {{ $inviteCanAccessSupport ? 'border-gray-900 bg-gray-50 dark:border-[#C5A059] dark:bg-gray-950' : 'border-gray-200 dark:border-gray-800' }}">
                                <input type="checkbox" wire:model.live="inviteCanAccessSupport" class="rounded text-gray-900 dark:text-[#C5A059]" />
                                <span class="text-xs font-bold text-gray-900 dark:text-white">Support</span>
                            </label>
                            <label class="flex items-center gap-2 p-3 rounded-xl border cursor-pointer transition {{ $inviteCanAccessInventory ? 'border-gray-900 bg-gray-50 dark:border-[#C5A059] dark:bg-gray-950' : 'border-gray-200 dark:border-gray-800' }}">
                                <input type="checkbox" wire:model.live="inviteCanAccessInventory" class="rounded text-gray-900 dark:text-[#C5A059]" />
                                <span class="text-xs font-bold text-gray-900 dark:text-white">Lager & Bruch</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-gray-100 dark:border-gray-800">
                    <button type="button" wire:click="$set('showInviteModal', false)"
                            class="px-4 py-2 rounded-xl bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 text-xs font-semibold cursor-pointer">
                        Abbrechen
                    </button>
                    <button type="button" wire:click="createInvitation"
                            class="px-5 py-2 rounded-xl bg-gray-900 dark:bg-[#C5A059] text-white dark:text-gray-950 font-bold text-xs cursor-pointer">
                        Einladung erstellen
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
