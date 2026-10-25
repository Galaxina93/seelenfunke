<?php

namespace App\Livewire\Shop\Management;

use Livewire\Attributes\Layout;
use App\Models\Management\ManagementDayRoutine as RoutineModel;
use App\Models\Management\ManagementDayRoutineStep;
use App\Models\Management\ManagementDayRoutineLog;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Livewire\Component;
use App\Livewire\Traits\WithDepartmentTheming;

#[Layout('components.layouts.backend_layout')]
class ManagementRoutine extends Component
{
    use WithDepartmentTheming;

    public string $themingDepartment = 'Leitung';

    public $routines;
    public $isEditing = false;
    public $filterDays = 'all'; // 'all', 'weekdays', 'weekend'

    // View & Evaluation Controls
    public string $viewMode = 'combined'; // 'combined', 'routine', 'tracker'
    public string $evalPeriod = 'week'; // 'week', 'month', 'year'
    public int $periodOffset = 0;
    public string $selectedDate = '';
    public string $scoreMode = 'smart'; // 'smart' (fair, fälligkeitsbasiert) | 'strict' (alle Werktage der Periode)
    public array $expandedSteps = [];

    // Form fields
    #[\Livewire\Attributes\Locked]
    public $r_id;
    public $r_time, $r_title, $r_message, $r_duration, $r_type = 'general', $r_days_of_week = 'all';
    public array $r_steps = [];

    public function boot(): void
    {
        if (!\Illuminate\Support\Facades\Auth::guard('admin')->check() && !\Illuminate\Support\Facades\Auth::guard('employee')->check() && !\App\Services\AI\AiAuthHelper::isAdmin()) {
            abort(403, 'Zugriff verweigert.');
        }
    }

    public function mount()
    {
        if (!\Illuminate\Support\Facades\Auth::guard('admin')->check() && !\Illuminate\Support\Facades\Auth::guard('employee')->check() && !\App\Services\AI\AiAuthHelper::isAdmin()) {
            abort(403, 'Zugriff verweigert.');
        }

        $now = Carbon::now();
        if ($now->isWeekend()) {
            $this->selectedDate = $now->copy()->startOfWeek(Carbon::MONDAY)->addDays(4)->format('Y-m-d');
        } else {
            $this->selectedDate = $now->format('Y-m-d');
        }
    }

    public function setEvalPeriod(string $period): void
    {
        if (in_array($period, ['week', 'month', 'year'])) {
            $this->evalPeriod = $period;
            $this->periodOffset = 0;
        }
    }

    public function prevPeriod(): void
    {
        $this->periodOffset--;
        $this->syncSelectedDateToPeriod();
    }

    public function nextPeriod(): void
    {
        if ($this->periodOffset < 0) {
            $this->periodOffset++;
            $this->syncSelectedDateToPeriod();
        }
    }

    public function resetPeriod(): void
    {
        $this->periodOffset = 0;
        $now = Carbon::now();
        $this->selectedDate = $now->isWeekend()
            ? $now->copy()->startOfWeek(Carbon::MONDAY)->addDays(4)->format('Y-m-d')
            : $now->format('Y-m-d');
    }

    public function selectDate(string $date): void
    {
        $this->selectedDate = $date;
    }

    public function toggleScoreMode(): void
    {
        $this->scoreMode = $this->scoreMode === 'smart' ? 'strict' : 'smart';
    }

    public function toggleExpandSteps(string $routineId): void
    {
        if (in_array($routineId, $this->expandedSteps)) {
            $this->expandedSteps = array_values(array_diff($this->expandedSteps, [$routineId]));
        } else {
            $this->expandedSteps[] = $routineId;
        }
    }

    private function syncSelectedDateToPeriod(): void
    {
        if ($this->evalPeriod === 'week') {
            $ref = Carbon::now()->addWeeks($this->periodOffset);
            $monday = $ref->copy()->startOfWeek(Carbon::MONDAY);
            $friday = $monday->copy()->addDays(4);
            $current = Carbon::parse($this->selectedDate);
            if ($current->lt($monday) || $current->gt($friday)) {
                $this->selectedDate = ($this->periodOffset === 0 && !Carbon::now()->isWeekend())
                    ? Carbon::now()->format('Y-m-d')
                    : $friday->format('Y-m-d');
            }
        }
    }

    /**
     * Markiert oder ent-markiert einen ganzen Tag als Krankheitstag / Genesungstag (Medizin-Funki)
     */
    public function toggleSickDay(?string $date = null): void
    {
        $targetDate = $date ?: $this->selectedDate ?: Carbon::now()->format('Y-m-d');
        $this->selectedDate = $targetDate;

        $routines = RoutineModel::where('is_active', true)->get();
        if ($routines->isEmpty()) {
            return;
        }

        $alreadySick = ManagementDayRoutineLog::whereDate('date', $targetDate)
            ->where('is_sick', true)
            ->exists();

        if ($alreadySick) {
            ManagementDayRoutineLog::whereDate('date', $targetDate)
                ->update(['is_sick' => false]);
        } else {
            foreach ($routines as $routine) {
                $existing = ManagementDayRoutineLog::where('day_routine_id', $routine->id)
                    ->whereDate('date', $targetDate)
                    ->first();

                if ($existing) {
                    $existing->update([
                        'is_sick' => true,
                    ]);
                } else {
                    ManagementDayRoutineLog::create([
                        'day_routine_id' => $routine->id,
                        'date' => $targetDate,
                        'is_completed' => false,
                        'is_sick' => true,
                        'completed_steps' => [],
                        'completed_at' => null,
                    ]);
                }
            }
        }
    }

    /**
     * Schnelles Abhaken / Ent-Haken einer kompletten Routine für ein bestimmtes Datum
     */
    public function toggleRoutineLog(string $routineId, ?string $date = null): void
    {
        $targetDate = $date ?: $this->selectedDate ?: Carbon::now()->format('Y-m-d');
        $routine = RoutineModel::with('steps')->find($routineId);
        if (!$routine) {
            return;
        }

        $log = ManagementDayRoutineLog::where('day_routine_id', $routineId)
            ->whereDate('date', $targetDate)
            ->first();

        if ($log && $log->is_completed) {
            $log->update([
                'is_completed' => false,
                'completed_steps' => [],
                'completed_at' => null,
            ]);
        } else {
            $allStepIds = $routine->steps->pluck('id')->map(fn($id) => (string) $id)->toArray();
            ManagementDayRoutineLog::updateOrCreate(
                [
                    'day_routine_id' => $routineId,
                    'date' => $targetDate,
                ],
                [
                    'is_completed' => true,
                    'completed_steps' => $allStepIds,
                    'completed_at' => Carbon::now(),
                ]
            );
        }
    }

    /**
     * Abhaken eines einzelnen Unterschritts (Step) innerhalb einer Routine
     */
    public function toggleStepLog(string $routineId, string $stepId, ?string $date = null): void
    {
        $targetDate = $date ?: $this->selectedDate ?: Carbon::now()->format('Y-m-d');
        $routine = RoutineModel::with('steps')->find($routineId);
        if (!$routine) {
            return;
        }

        $log = ManagementDayRoutineLog::firstOrNew([
            'day_routine_id' => $routineId,
            'date' => $targetDate,
        ]);

        $completedSteps = is_array($log->completed_steps) ? $log->completed_steps : [];
        if ($log->exists && $log->is_completed && empty($completedSteps) && $routine->steps->count() > 0) {
            $completedSteps = $routine->steps->pluck('id')->map(fn($id) => (string) $id)->toArray();
        }

        if (in_array($stepId, $completedSteps)) {
            $completedSteps = array_values(array_diff($completedSteps, [$stepId]));
        } else {
            $completedSteps[] = $stepId;
        }

        $totalSteps = $routine->steps->count();
        $isAllCompleted = $totalSteps > 0 ? (count($completedSteps) >= $totalSteps) : !empty($completedSteps);

        $log->completed_steps = $completedSteps;
        $log->is_completed = $isAllCompleted;
        $log->completed_at = $isAllCompleted ? Carbon::now() : null;
        $log->save();
    }

    /**
     * Alle aktiven Routinen eines Tages auf einmal abhaken oder zurücksetzen
     */
    public function toggleAllForDate(string $date, bool $onlyDue = false): void
    {
        $routines = RoutineModel::with('steps')
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereIn('days_of_week', ['all', 'weekdays'])->orWhereNull('days_of_week');
            })
            ->orderBy('start_time')
            ->get();

        if ($onlyDue && $date === Carbon::now()->format('Y-m-d')) {
            $nowTime = Carbon::now()->format('H:i');
            $routines = $routines->filter(function ($r) use ($nowTime) {
                return Carbon::parse($r->start_time)->format('H:i') <= $nowTime;
            });
        }

        if ($routines->isEmpty()) {
            return;
        }

        $existingCompletedCount = ManagementDayRoutineLog::whereDate('date', $date)
            ->whereIn('day_routine_id', $routines->pluck('id'))
            ->where('is_completed', true)
            ->count();

        $shouldComplete = $existingCompletedCount < $routines->count();

        foreach ($routines as $routine) {
            $allStepIds = $shouldComplete
                ? $routine->steps->pluck('id')->map(fn($id) => (string) $id)->toArray()
                : [];

            ManagementDayRoutineLog::updateOrCreate(
                [
                    'day_routine_id' => $routine->id,
                    'date' => $date,
                ],
                [
                    'is_completed' => $shouldComplete,
                    'is_sick' => false,
                    'completed_steps' => $allStepIds,
                    'completed_at' => $shouldComplete ? Carbon::now() : null,
                ]
            );
        }
    }

    public function render()
    {
        $query = RoutineModel::with('steps')->orderBy('start_time');

        if ($this->filterDays === 'weekdays') {
            $query->where(function ($q) {
                $q->whereIn('days_of_week', ['all', 'weekdays'])->orWhereNull('days_of_week');
            });
        } elseif ($this->filterDays === 'weekend') {
            $query->where(function ($q) {
                $q->whereIn('days_of_week', ['all', 'weekend'])->orWhereNull('days_of_week');
            });
        }

        $this->routines = $query->get();

        $analytics = $this->buildRoutineAnalytics();

        return view('livewire.shop.management.management-routine', $analytics);
    }

    /**
     * Berechnet alle Tracking-Matrizen, Auswertungen (Woche/Monat/Jahr),
     * Krankheitstage (Medizin-Funki), Funki-Bewertungsstufen und System-Tipps.
     */
    private function buildRoutineAnalytics(): array
    {
        $now = Carbon::now();
        $todayStr = $now->format('Y-m-d');
        $nowTimeStr = $now->format('H:i');

        // Alle aktiven Werktags-Routinen (Mo-Fr Fokus)
        $focusRoutines = RoutineModel::with('steps')
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereIn('days_of_week', ['all', 'weekdays'])->orWhereNull('days_of_week');
            })
            ->orderBy('start_time')
            ->get();

        $GermanDaysShort = [1 => 'Mo', 2 => 'Di', 3 => 'Mi', 4 => 'Do', 5 => 'Fr', 6 => 'Sa', 7 => 'So'];
        $GermanMonthsShort = [
            1 => 'Jan', 2 => 'Feb', 3 => 'Mär', 4 => 'Apr', 5 => 'Mai', 6 => 'Jun',
            7 => 'Jul', 8 => 'Aug', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Dez'
        ];
        $GermanMonthsFull = [
            1 => 'Januar', 2 => 'Februar', 3 => 'März', 4 => 'April', 5 => 'Mai', 6 => 'Juni',
            7 => 'Juli', 8 => 'August', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Dezember'
        ];

        // 1. Zeitraum bestimmen (Woche / Monat / Jahr)
        if ($this->evalPeriod === 'week') {
            $ref = $now->copy()->addWeeks($this->periodOffset);
            $periodStart = $ref->copy()->startOfWeek(Carbon::MONDAY);
            $periodEnd = $ref->copy()->endOfWeek(Carbon::SUNDAY);
            $workEnd = $periodStart->copy()->addDays(4);
            $periodLabel = 'KW ' . $periodStart->isoWeek() . ' · ' . $periodStart->format('d.m.') . ' – ' . $workEnd->format('d.m.Y');
            $periodSubLabel = 'Mo – Fr Fokus · Sa & So Heilige Freizeit';
        } elseif ($this->evalPeriod === 'month') {
            $ref = $now->copy()->startOfMonth()->addMonths($this->periodOffset);
            $periodStart = $ref->copy()->startOfMonth();
            $periodEnd = $ref->copy()->endOfMonth();
            $periodLabel = $GermanMonthsFull[$ref->month] . ' ' . $ref->year;
            $periodSubLabel = 'Monatsauswertung (Nur Werktage Mo – Fr)';
        } else {
            $ref = $now->copy()->startOfYear()->addYears($this->periodOffset);
            $periodStart = $ref->copy()->startOfYear();
            $periodEnd = $ref->copy()->endOfYear();
            $periodLabel = 'Jahresübersicht ' . $ref->year;
            $periodSubLabel = '12-Monats-Auswertung (Nur Werktage Mo – Fr)';
        }

        // 2. Logs laden (für Zeitraum + letzte 90 Tage für Streak-Berechnung + SelectedDate)
        $streakLookbackStart = $now->copy()->subDays(90)->startOfDay();
        $fetchStart = $periodStart->lt($streakLookbackStart) ? $periodStart->copy() : $streakLookbackStart;
        $fetchEnd = $periodEnd->gt($now) ? $periodEnd->copy() : $now->copy()->endOfDay();

        $allLogs = ManagementDayRoutineLog::whereBetween('date', [$fetchStart->format('Y-m-d'), $fetchEnd->format('Y-m-d')])
            ->get();

        // Index: $logMap[$routineId][$dateStr] = ManagementDayRoutineLog
        // Und: $sickDateMap[$dateStr] = true, falls dieser Tag als Krankheitstag markiert wurde
        $logMap = [];
        $sickDateMap = [];
        foreach ($allLogs as $log) {
            $dStr = $log->date instanceof Carbon ? $log->date->format('Y-m-d') : substr((string) $log->date, 0, 10);
            $logMap[$log->day_routine_id][$dStr] = $log;
            if ($log->is_sick) {
                $sickDateMap[$dStr] = true;
            }
        }

        // Zusätzlich für $selectedDate sicherstellen, dass Logs geladen sind
        if ($this->selectedDate && ($this->selectedDate < $fetchStart->format('Y-m-d') || $this->selectedDate > $fetchEnd->format('Y-m-d'))) {
            $selLogs = ManagementDayRoutineLog::whereDate('date', $this->selectedDate)->get();
            foreach ($selLogs as $log) {
                $dStr = $log->date instanceof Carbon ? $log->date->format('Y-m-d') : substr((string) $log->date, 0, 10);
                $logMap[$log->day_routine_id][$dStr] = $log;
                if ($log->is_sick) {
                    $sickDateMap[$dStr] = true;
                }
            }
        }

        $isSelectedDateSick = !empty($sickDateMap[$this->selectedDate]);
        $isTodaySick = !empty($sickDateMap[$todayStr]);

        // Erste Tracking-Aktivität überhaupt (für intelligente Fair-Score-Berechnung im Monats-/Jahres-Tab)
        $firstLogDateRaw = ManagementDayRoutineLog::where(function ($q) {
            $q->where('is_completed', true)->orWhere('is_sick', true);
        })->min('date');
        $firstTrackedMonday = $firstLogDateRaw
            ? Carbon::parse($firstLogDateRaw)->startOfWeek(Carbon::MONDAY)
            : $now->copy()->startOfWeek(Carbon::MONDAY);

        // 3. Alle Tage im Zeitraum durchlaufen (und Werktage Mo-Fr sowie Krankheitstage identifizieren)
        $daysInPeriod = [];
        $workdaysInPeriod = [];
        $healthyWorkdaysInPeriod = [];
        $dueWorkdaysInPeriod = [];
        $smartDueWorkdaysInPeriod = [];
        $sickWorkdaysInPeriodCount = 0;

        $cursor = $periodStart->copy();
        while ($cursor->lte($periodEnd)) {
            $dStr = $cursor->format('Y-m-d');
            $isWeekend = $cursor->isWeekend();
            $isFuture = $dStr > $todayStr;
            $isToday = $dStr === $todayStr;
            $isSick = !empty($sickDateMap[$dStr]);

            $dayObj = [
                'date' => $dStr,
                'day_num' => $cursor->format('d'),
                'day_short' => $GermanDaysShort[$cursor->dayOfWeekIso],
                'label' => $GermanDaysShort[$cursor->dayOfWeekIso] . ' ' . $cursor->format('d.m.'),
                'is_weekend' => $isWeekend,
                'is_today' => $isToday,
                'is_future' => $isFuture,
                'is_sick' => $isSick,
                'is_selected' => $dStr === $this->selectedDate,
                'month' => $cursor->month,
            ];

            $daysInPeriod[] = $dayObj;

            if (!$isWeekend) {
                $workdaysInPeriod[] = $dayObj;
                if ($isSick) {
                    $sickWorkdaysInPeriodCount++;
                } else {
                    $healthyWorkdaysInPeriod[] = $dayObj;
                    if (!$isFuture) {
                        $dueWorkdaysInPeriod[] = $dayObj;
                        if ($this->evalPeriod === 'week' || $cursor->gte($firstTrackedMonday)) {
                            $smartDueWorkdaysInPeriod[] = $dayObj;
                        }
                    }
                }
            }

            $cursor->addDay();
        }

        $effectiveDueWorkdays = ($this->scoreMode === 'smart' && !empty($smartDueWorkdaysInPeriod))
            ? $smartDueWorkdaysInPeriod
            : $dueWorkdaysInPeriod;

        // 4. Aktuelle Wochen-Tage (Mo-Fr + Sa/So) für den Quick-Switcher in der Tagesroutine
        $selectedCarbon = $this->selectedDate ? Carbon::parse($this->selectedDate) : $now->copy();
        $quickWeekMonday = ($this->evalPeriod === 'week')
            ? $periodStart->copy()
            : $selectedCarbon->copy()->startOfWeek(Carbon::MONDAY);

        $quickWeekDays = [];
        for ($i = 0; $i < 7; $i++) {
            $qd = $quickWeekMonday->copy()->addDays($i);
            $qdStr = $qd->format('Y-m-d');
            $doneCount = 0;
            foreach ($focusRoutines as $fr) {
                if (!empty($logMap[$fr->id][$qdStr]) && $logMap[$fr->id][$qdStr]->is_completed) {
                    $doneCount++;
                }
            }
            $quickWeekDays[] = [
                'date' => $qdStr,
                'day_short' => $GermanDaysShort[$qd->dayOfWeekIso],
                'day_num' => $qd->format('d.m.'),
                'is_weekend' => $qd->isWeekend(),
                'is_sick' => !empty($sickDateMap[$qdStr]),
                'is_today' => $qdStr === $todayStr,
                'is_selected' => $qdStr === $this->selectedDate,
                'done_count' => $doneCount,
                'total_count' => $focusRoutines->count(),
                'pct' => $focusRoutines->count() > 0 ? round(($doneCount / $focusRoutines->count()) * 100) : 0,
            ];
        }

        // 5. Streak-Berechnung pro Routine (Wochenende UND Krankheitstage unterbrechen die 🔥 Serie NICHT!)
        $routineStreaks = [];
        foreach ($focusRoutines as $r) {
            $streak = 0;
            $checkDay = $now->copy();
            if ($checkDay->isWeekend()) {
                $checkDay = $checkDay->startOfWeek(Carbon::MONDAY)->addDays(4);
            }

            for ($stepBack = 0; $stepBack < 65; $stepBack++) {
                if ($checkDay->isWeekend()) {
                    $checkDay->subDay();
                    continue;
                }
                $cStr = $checkDay->format('Y-m-d');
                $isCompleted = !empty($logMap[$r->id][$cStr]) && $logMap[$r->id][$cStr]->is_completed;
                $isSickDay = !empty($sickDateMap[$cStr]);

                if ($isCompleted) {
                    $streak++;
                } elseif ($isSickDay) {
                    // Krankheitstag friert die Serie ein (kein Bruch!)
                    $checkDay->subDay();
                    continue;
                } else {
                    if ($cStr === $todayStr) {
                        $checkDay->subDay();
                        continue;
                    }
                    break;
                }
                $checkDay->subDay();
            }
            $routineStreaks[$r->id] = $streak;
        }

        // 6. Matrix-Daten & Kennzahlen pro Routine berechnen (Krankheitstage sind entschuldigt!)
        $matrixRows = [];
        $healthyWorkdayCount = count($healthyWorkdaysInPeriod);
        $totalPeriodTargetSlots = $healthyWorkdayCount * $focusRoutines->count();
        $totalDueSlots = 0;
        $totalCompletedInPeriod = 0;
        $totalCompletedInDue = 0;
        $totalCompletedMinutes = 0;
        $totalDueMinutes = 0;

        $dueWorkdayDateStrings = array_column($effectiveDueWorkdays, 'date');
        $allWorkdayDateStrings = array_column($workdaysInPeriod, 'date');

        foreach ($focusRoutines as $r) {
            $rTimeStr = Carbon::parse($r->start_time)->format('H:i');
            $completedInWorkdays = 0;
            $completedInDue = 0;
            $dueCountForRoutine = 0;

            foreach ($allWorkdayDateStrings as $wDateStr) {
                $isDone = !empty($logMap[$r->id][$wDateStr]) && $logMap[$r->id][$wDateStr]->is_completed;
                if ($isDone) {
                    $completedInWorkdays++;
                    $totalCompletedInPeriod++;
                    $totalCompletedMinutes += (int) $r->duration_minutes;
                }
            }

            foreach ($dueWorkdayDateStrings as $dDateStr) {
                $isDone = !empty($logMap[$r->id][$dDateStr]) && $logMap[$r->id][$dDateStr]->is_completed;
                $isDueToday = true;
                if ($this->scoreMode === 'smart' && $dDateStr === $todayStr && $rTimeStr > $nowTimeStr && !$isDone) {
                    $isDueToday = false;
                }

                if ($isDueToday) {
                    $dueCountForRoutine++;
                    $totalDueSlots++;
                    $totalDueMinutes += (int) $r->duration_minutes;
                    if ($isDone) {
                        $completedInDue++;
                        $totalCompletedInDue++;
                    }
                }
            }

            // Für Jahr: 12 Monats-Zellen, sonst Tages-Zellen
            $cells = [];
            if ($this->evalPeriod === 'year') {
                for ($m = 1; $m <= 12; $m++) {
                    $mStart = Carbon::create($periodStart->year, $m, 1)->startOfMonth();
                    $mEnd = $mStart->copy()->endOfMonth();
                    $mHealthyWorkdays = 0;
                    $mSickDays = 0;
                    $mDone = 0;
                    $mc = $mStart->copy();
                    while ($mc->lte($mEnd)) {
                        if (!$mc->isWeekend()) {
                            $mcStr = $mc->format('Y-m-d');
                            if (!empty($sickDateMap[$mcStr])) {
                                $mSickDays++;
                            } else {
                                $mHealthyWorkdays++;
                            }
                            if (!empty($logMap[$r->id][$mcStr]) && $logMap[$r->id][$mcStr]->is_completed) {
                                $mDone++;
                            }
                        }
                        $mc->addDay();
                    }
                    $cells[] = [
                        'month' => $m,
                        'label' => $GermanMonthsShort[$m],
                        'done' => $mDone,
                        'target' => $mHealthyWorkdays,
                        'sick_days' => $mSickDays,
                        'pct' => $mHealthyWorkdays > 0 ? min(100, round(($mDone / $mHealthyWorkdays) * 100)) : ($mSickDays > 0 ? 100 : 0),
                        'is_future' => $mStart->gt($now),
                    ];
                }
            } else {
                $sourceDays = ($this->evalPeriod === 'week') ? $workdaysInPeriod : $daysInPeriod;
                foreach ($sourceDays as $dObj) {
                    $dStr = $dObj['date'];
                    $logEntry = $logMap[$r->id][$dStr] ?? null;
                    $isDone = $logEntry && $logEntry->is_completed;
                    $cells[] = [
                        'date' => $dStr,
                        'day_short' => $dObj['day_short'],
                        'day_num' => $dObj['day_num'],
                        'is_weekend' => $dObj['is_weekend'],
                        'is_sick' => !empty($sickDateMap[$dStr]),
                        'is_today' => $dObj['is_today'],
                        'is_future' => $dObj['is_future'],
                        'is_completed' => $isDone,
                    ];
                }
            }

            $targetWorkdaysCount = $healthyWorkdayCount;
            $effectiveDenominator = $dueCountForRoutine > 0 ? $dueCountForRoutine : $targetWorkdaysCount;
            $fairPct = $effectiveDenominator > 0
                ? round(($completedInDue / $effectiveDenominator) * 100)
                : ($sickWorkdaysInPeriodCount > 0 ? 100 : 0);
            $periodPct = $targetWorkdaysCount > 0
                ? round(($completedInWorkdays / $targetWorkdaysCount) * 100)
                : ($sickWorkdaysInPeriodCount > 0 ? 100 : 0);

            $displayPct = ($this->scoreMode === 'smart' && $dueCountForRoutine > 0) ? $fairPct : $periodPct;

            $matrixRows[] = [
                'routine' => $r,
                'id' => $r->id,
                'title' => $r->title,
                'start_time' => Carbon::parse($r->start_time)->format('H:i'),
                'type' => $r->type ?? 'general',
                'duration_minutes' => $r->duration_minutes,
                'cells' => $cells,
                'completed_count' => $completedInWorkdays,
                'target_count' => $targetWorkdaysCount,
                'due_count' => $dueCountForRoutine,
                'pct' => min(100, $displayPct),
                'period_pct' => min(100, $periodPct),
                'streak' => $routineStreaks[$r->id] ?? 0,
                'stars' => $completedInWorkdays,
            ];
        }

        // 7. Verlaufskurve (Trend-Chart Daten) & Wochentags-Auswertung (Mo - Fr)
        $trendPoints = [];
        if ($this->evalPeriod === 'year') {
            for ($m = 1; $m <= 12; $m++) {
                $mStart = Carbon::create($periodStart->year, $m, 1)->startOfMonth();
                $mEnd = $mStart->copy()->endOfMonth();
                $mPossible = 0;
                $mDone = 0;
                $mc = $mStart->copy();
                while ($mc->lte($mEnd)) {
                    if (!$mc->isWeekend()) {
                        $mcStr = $mc->format('Y-m-d');
                        $isSickDay = !empty($sickDateMap[$mcStr]);
                        foreach ($focusRoutines as $fr) {
                            if (!$isSickDay) {
                                $mPossible++;
                            }
                            if (!empty($logMap[$fr->id][$mcStr]) && $logMap[$fr->id][$mcStr]->is_completed) {
                                $mDone++;
                            }
                        }
                    }
                    $mc->addDay();
                }
                $trendPoints[] = [
                    'label' => $GermanMonthsShort[$m],
                    'sub' => $mDone . '/' . $mPossible,
                    'pct' => $mPossible > 0 ? min(100, round(($mDone / $mPossible) * 100)) : 0,
                    'done' => $mDone,
                    'target' => $mPossible,
                    'is_sick' => false,
                    'is_future' => $mStart->gt($now),
                ];
            }
        } else {
            foreach ($workdaysInPeriod as $wDay) {
                $wStr = $wDay['date'];
                $isSickDay = $wDay['is_sick'];
                $wDone = 0;
                $wTarget = $focusRoutines->count();
                foreach ($focusRoutines as $fr) {
                    if (!empty($logMap[$fr->id][$wStr]) && $logMap[$fr->id][$wStr]->is_completed) {
                        $wDone++;
                    }
                }
                $trendPoints[] = [
                    'label' => $this->evalPeriod === 'week' ? $wDay['day_short'] : $wDay['day_num'] . '.',
                    'full_label' => $wDay['label'] . ($isSickDay ? ' (Krank 🤒)' : ''),
                    'date' => $wStr,
                    'sub' => $isSickDay ? 'Krank 🤒' : ($wDone . '/' . $wTarget),
                    'pct' => $isSickDay ? 100 : ($wTarget > 0 ? round(($wDone / $wTarget) * 100) : 0),
                    'done' => $wDone,
                    'target' => $wTarget,
                    'is_sick' => $isSickDay,
                    'is_today' => $wDay['is_today'],
                    'is_future' => $wDay['is_future'],
                ];
            }
        }

        // Wochentags-Performance (Mo, Di, Mi, Do, Fr)
        $weekdayStats = [];
        for ($iso = 1; $iso <= 5; $iso++) {
            $wdPossible = 0;
            $wdDone = 0;
            $wdSickCount = 0;
            foreach ($workdaysInPeriod as $wdItem) {
                if (Carbon::parse($wdItem['date'])->dayOfWeekIso === $iso && !$wdItem['is_future']) {
                    if ($wdItem['is_sick']) {
                        $wdSickCount++;
                    } elseif (in_array($wdItem['date'], $dueWorkdayDateStrings)) {
                        foreach ($focusRoutines as $fr) {
                            $wdPossible++;
                            if (!empty($logMap[$fr->id][$wdItem['date']]) && $logMap[$fr->id][$wdItem['date']]->is_completed) {
                                $wdDone++;
                            }
                        }
                    }
                }
            }
            $weekdayStats[] = [
                'day' => $GermanDaysShort[$iso],
                'done' => $wdDone,
                'target' => $wdPossible,
                'sick_days' => $wdSickCount,
                'pct' => $wdPossible > 0 ? min(100, round(($wdDone / $wdPossible) * 100)) : ($wdSickCount > 0 ? 100 : 0),
            ];
        }

        // Kategorie- & Tageszeit-Performance (Matte, hochkontrastreiche Farben für Light & Dark Mode)
        $typeLabels = [
            'hygiene' => ['label' => 'Morgen- & Abendpflege', 'color' => 'bg-purple-600 dark:bg-purple-500', 'text' => 'text-purple-700 dark:text-purple-300'],
            'sport' => ['label' => 'Sport & Workout', 'color' => 'bg-rose-600 dark:bg-rose-500', 'text' => 'text-rose-700 dark:text-rose-300'],
            'work' => ['label' => 'Deep Work & Business', 'color' => 'bg-blue-600 dark:bg-blue-500', 'text' => 'text-blue-700 dark:text-blue-300'],
            'food' => ['label' => 'Ernährung & Energie', 'color' => 'bg-emerald-600 dark:bg-emerald-500', 'text' => 'text-emerald-700 dark:text-emerald-300'],
            'break' => ['label' => 'Orga, Reset & Freizeit', 'color' => 'bg-amber-500 dark:bg-amber-400', 'text' => 'text-amber-700 dark:text-amber-300'],
            'sleep' => ['label' => 'Nachtruhe & Schlaf', 'color' => 'bg-indigo-600 dark:bg-indigo-500', 'text' => 'text-indigo-700 dark:text-indigo-300'],
            'general' => ['label' => 'Allgemein', 'color' => 'bg-gray-600 dark:bg-gray-400', 'text' => 'text-gray-700 dark:text-gray-300'],
        ];

        $categoryStats = [];
        foreach ($matrixRows as $row) {
            $t = $row['type'];
            if (!isset($categoryStats[$t])) {
                $meta = $typeLabels[$t] ?? $typeLabels['general'];
                $categoryStats[$t] = [
                    'type' => $t,
                    'label' => $meta['label'],
                    'color' => $meta['color'],
                    'text' => $meta['text'],
                    'done' => 0,
                    'target' => 0,
                ];
            }
            $categoryStats[$t]['done'] += $row['completed_count'];
            $categoryStats[$t]['target'] += ($this->scoreMode === 'smart' && $row['due_count'] > 0) ? $row['due_count'] : $row['target_count'];
        }
        foreach ($categoryStats as &$cs) {
            $cs['pct'] = $cs['target'] > 0 ? min(100, round(($cs['done'] / $cs['target']) * 100)) : 0;
        }
        unset($cs);

        // 8. Gesamt-Bewertung (Funki Evaluation 0-25%, 25-50%, 50-75%, 75-100% + Medizin-Funki!)
        $strictOverallPct = $totalPeriodTargetSlots > 0
            ? min(100, round(($totalCompletedInPeriod / $totalPeriodTargetSlots) * 100))
            : ($sickWorkdaysInPeriodCount > 0 ? 100 : 0);

        $smartOverallPct = $totalDueSlots > 0
            ? min(100, round(($totalCompletedInDue / $totalDueSlots) * 100))
            : $strictOverallPct;

        $evaluationPct = $this->scoreMode === 'smart' ? $smartOverallPct : $strictOverallPct;

        $funkiEvaluation = $this->resolveFunkiEvaluation(
            $evaluationPct,
            $now->isWeekend(),
            $isSelectedDateSick || $isTodaySick,
            $sickWorkdaysInPeriodCount
        );

        // 9. Intelligente System-Tipps berechnen (inkl. Genesungs-Tipps bei Krankheit)
        $systemTips = $this->generateSmartSystemTips(
            $matrixRows,
            $weekdayStats,
            $evaluationPct,
            $now->isWeekend(),
            $isSelectedDateSick || $isTodaySick,
            $sickWorkdaysInPeriodCount
        );

        return [
            'periodLabel' => $periodLabel,
            'periodSubLabel' => $periodSubLabel,
            'daysInPeriod' => $daysInPeriod,
            'workdaysInPeriod' => $workdaysInPeriod,
            'quickWeekDays' => $quickWeekDays,
            'matrixRows' => $matrixRows,
            'trendPoints' => $trendPoints,
            'weekdayStats' => $weekdayStats,
            'categoryStats' => array_values($categoryStats),
            'funkiEvaluation' => $funkiEvaluation,
            'systemTips' => $systemTips,
            'isSelectedDateSick' => $isSelectedDateSick,
            'overallStats' => [
                'evaluation_pct' => $evaluationPct,
                'smart_pct' => $smartOverallPct,
                'strict_pct' => $strictOverallPct,
                'completed_slots' => $totalCompletedInPeriod,
                'due_slots' => $totalDueSlots,
                'target_slots' => $totalPeriodTargetSlots,
                'workdays_total' => count($workdaysInPeriod),
                'workdays_healthy' => $healthyWorkdayCount,
                'workdays_due' => count($effectiveDueWorkdays),
                'sick_days_count' => $sickWorkdaysInPeriodCount,
                'completed_hours' => round($totalCompletedMinutes / 60, 1),
                'due_hours' => round($totalDueMinutes / 60, 1),
                'best_streak' => !empty($routineStreaks) ? max($routineStreaks) : 0,
                'is_weekend_today' => $now->isWeekend(),
            ],
            'logMapForSelectedDate' => array_map(fn($dates) => $dates[$this->selectedDate] ?? null, $logMap),
        ];
    }

    /**
     * Bestimmt das Funki-Bewertungs-Level (Matte, hochkontrastreiche Darstellung für Light & Dark Mode)
     */
    private function resolveFunkiEvaluation(int $pct, bool $isWeekendToday, bool $isActiveSickMode, int $sickDaysCount): array
    {
        if ($isActiveSickMode) {
            return [
                'tier' => 'sick',
                'range' => 'Genesungs-Modus',
                'pct' => $pct,
                'speech' => 'GUTE BESSERUNG!',
                'title' => 'Medizin-Funki wünscht gute Besserung!',
                'subtitle' => 'Krankheitstag aktiv: Deine Serien sind eingefroren und deine Quote wird nicht belastet. Kurier dich in Ruhe aus!',
                'image' => asset('shop/projekt/funki/routine/funki_sick.webp'),
                'badge_bg' => 'bg-sky-100 text-sky-800 border-sky-300 dark:bg-sky-950/80 dark:text-sky-200 dark:border-sky-800',
                'accent_border' => 'border-gray-200 dark:border-gray-800',
                'bar_color' => 'bg-sky-600 dark:bg-sky-500',
                'text_color' => 'text-sky-700 dark:text-sky-400',
            ];
        }

        if ($pct <= 25) {
            return [
                'tier' => '0_25',
                'range' => '0% – 25% · Loser-Zone',
                'pct' => $pct,
                'speech' => 'DU LOSER!',
                'title' => 'Disziplin-Alarm: Zu wenig umgesetzt!',
                'subtitle' => $isWeekendToday
                    ? 'Unter 25% deiner Mo–Fr Routine erreicht. Starte am Montag um 07:00 Uhr konsequent durch!'
                    : 'Weniger als ein Viertel deiner fälligen Routine erledigt. Streich die Ausreden und leg los!',
                'image' => asset('shop/projekt/funki/routine/funki_0_25.webp'),
                'badge_bg' => 'bg-red-100 text-red-800 border-red-300 dark:bg-red-950/80 dark:text-red-200 dark:border-red-800',
                'accent_border' => 'border-gray-200 dark:border-gray-800',
                'bar_color' => 'bg-red-600 dark:bg-red-500',
                'text_color' => 'text-red-700 dark:text-red-400',
            ];
        }

        if ($pct <= 50) {
            return [
                'tier' => '25_50',
                'range' => '26% – 50% · Ausreden-Zone',
                'pct' => $pct,
                'speech' => 'KEINE AUSREDEN!',
                'title' => 'Unter 50% Zielerreichung',
                'subtitle' => 'Mehr als die Hälfte deiner geplanten Routine bleibt liegen. Fokus auf die ersten zwei Blöcke am Morgen!',
                'image' => asset('shop/projekt/funki/routine/funki_25_50.webp'),
                'badge_bg' => 'bg-orange-100 text-orange-800 border-orange-300 dark:bg-orange-950/80 dark:text-orange-200 dark:border-orange-800',
                'accent_border' => 'border-gray-200 dark:border-gray-800',
                'bar_color' => 'bg-orange-600 dark:bg-orange-500',
                'text_color' => 'text-orange-700 dark:text-orange-400',
            ];
        }

        if ($pct <= 75) {
            return [
                'tier' => '50_75',
                'range' => '51% – 75% · Solide Basis',
                'pct' => $pct,
                'speech' => 'DA GEHT MEHR!',
                'title' => 'Gute Basis – Da geht noch mehr!',
                'subtitle' => 'Du ziehst den Großteil deiner Mo–Fr Routine durch. Schließe jetzt noch die letzten Lücken!',
                'image' => asset('shop/projekt/funki/routine/funki_50_75.webp'),
                'badge_bg' => 'bg-amber-100 text-amber-900 border-amber-300 dark:bg-amber-950/80 dark:text-amber-200 dark:border-amber-800',
                'accent_border' => 'border-gray-200 dark:border-gray-800',
                'bar_color' => 'bg-amber-500 dark:bg-amber-400',
                'text_color' => 'text-amber-700 dark:text-amber-400',
            ];
        }

        return [
            'tier' => '75_100',
            'range' => '76% – 100% · Champion',
            'pct' => $pct,
            'speech' => 'CHAMPION!',
            'title' => 'Stark durchgezogen – Champion-Disziplin!',
            'subtitle' => 'Starke Konstanz von Montag bis Freitag. Du hast deinen Tagesablauf fest im Griff!',
            'image' => asset('shop/projekt/funki/routine/funki_75_100.webp'),
            'badge_bg' => 'bg-emerald-100 text-emerald-800 border-emerald-300 dark:bg-emerald-950/80 dark:text-emerald-200 dark:border-emerald-800',
            'accent_border' => 'border-gray-200 dark:border-gray-800',
            'bar_color' => 'bg-emerald-600 dark:bg-emerald-500',
            'text_color' => 'text-emerald-700 dark:text-emerald-400',
        ];
    }

    /**
     * Generiert 3 knappe, logisch errechnete System-Tipps anhand der realen Routine-Daten
     */
    private function generateSmartSystemTips(array $matrixRows, array $weekdayStats, int $overallPct, bool $isWeekendToday, bool $isActiveSickMode, int $sickDaysCount): array
    {
        if (empty($matrixRows)) {
            return [];
        }

        $tips = [];

        if ($isActiveSickMode) {
            $tips[] = [
                'type' => 'success',
                'badge' => '❤️‍🩹 Serien- & Score-Schutz aktiv',
                'title' => 'Deine 🔥 Serien & Quoten sind während der Krankheit geschützt',
                'advice' => 'Krankheitstage werden aus dem Pflicht-Soll herausgerechnet. Kurier dich ohne schlechtes Gewissen zu 100% aus!',
            ];
            $tips[] = [
                'type' => 'info',
                'badge' => '🍵 Medizin-Funkis Rezept',
                'title' => 'Fokus heute: Schlaf, viel Flüssigkeit & Bildschirm-Pause',
                'advice' => 'Streiche Sport und lange Deep-Work-Sessions. Wenn du magst, kannst du einzelne leichte Pflege-Schritte optional abhaken.',
            ];
            $tips[] = [
                'type' => 'weekend',
                'badge' => '📊 Krankenstand im Zeitraum',
                'title' => $sickDaysCount . ' ' . ($sickDaysCount === 1 ? 'Krankheitstag' : 'Krankheitstage') . ' im aktuellen Zeitraum dokumentiert',
                'advice' => 'Nach deiner Genesung steigst du einfach wieder sanft mit dem 07:00 Uhr Frischestart ein.',
            ];
            return $tips;
        }

        // 1. Größter Engpass (Schwächste Routine identifizieren)
        $sortedByWeakest = $matrixRows;
        usort($sortedByWeakest, function ($a, $b) {
            if ($a['pct'] === $b['pct']) {
                return strcmp($a['start_time'], $b['start_time']);
            }
            return $a['pct'] <=> $b['pct'];
        });
        $weakest = $sortedByWeakest[0];
        $denom = ($this->scoreMode === 'smart' && $weakest['due_count'] > 0) ? $weakest['due_count'] : $weakest['target_count'];

        if ($weakest['pct'] < 80) {
            $tips[] = [
                'type' => 'warning',
                'badge' => 'Größter Hebel',
                'title' => $weakest['start_time'] . ' ' . $weakest['title'] . ' (' . $weakest['completed_count'] . '/' . $denom . ' Tagen · ' . $weakest['pct'] . '%)',
                'advice' => $weakest['pct'] <= 40
                    ? 'Häufigster Abbruchpunkt: Prüfe, ob Uhrzeit (' . $weakest['start_time'] . ') oder Dauer (' . $weakest['duration_minutes'] . ' Min) angepasst werden müssen.'
                    : 'Fast stabil: Setze dir für diesen Block morgen früh einen festen Anker-Trigger ohne Aufschub.',
            ];
        } else {
            $tips[] = [
                'type' => 'success',
                'badge' => 'Keine Schwachstelle',
                'title' => 'Alle Routinen liegen bei mindestens ' . $weakest['pct'] . '%!',
                'advice' => 'Deine aktuelle Tagesstruktur passt perfekt zu deinem realen Bio-Rhythmus. Beibehalten!',
            ];
        }

        // 2. Stärkster Anker / Serie
        $sortedByStrongest = $matrixRows;
        usort($sortedByStrongest, function ($a, $b) {
            if ($b['pct'] === $a['pct']) {
                return $b['streak'] <=> $a['streak'];
            }
            return $b['pct'] <=> $a['pct'];
        });
        $strongest = $sortedByStrongest[0];

        if ($strongest['completed_count'] > 0) {
            $tips[] = [
                'type' => 'success',
                'badge' => 'Stärkste Konstanz',
                'title' => $strongest['start_time'] . ' ' . $strongest['title'] . ' (' . $strongest['pct'] . '% · 🔥 ' . $strongest['streak'] . 'er Serie)',
                'advice' => 'Dein stabilster Routine-Anker! Kopple schwierigere Aufgaben direkt an diesen Erfolgsblock.',
            ];
        } else {
            $tips[] = [
                'type' => 'info',
                'badge' => 'Quick-Start Tipp',
                'title' => 'Starte heute mit der 07:00 Uhr Morgen-Kette',
                'advice' => 'Hake deinen ersten erledigten Block direkt in der Matrix oder Timeline ab, um deine 🔥 Serie zu starten.',
            ];
        }

        // 3. Tageszeit- oder Wochentags-Muster
        $morningRows = array_filter($matrixRows, fn($r) => $r['start_time'] < '10:30');
        $dayRows = array_filter($matrixRows, fn($r) => $r['start_time'] >= '10:30' && $r['start_time'] < '17:00');
        $eveningRows = array_filter($matrixRows, fn($r) => $r['start_time'] >= '17:00');

        $avgFn = fn(array $rows) => count($rows) > 0 ? round(array_sum(array_column($rows, 'pct')) / count($rows)) : 0;
        $morningAvg = $avgFn($morningRows);
        $dayAvg = $avgFn($dayRows);
        $eveningAvg = $avgFn($eveningRows);

        if ($isWeekendToday) {
            $tips[] = [
                'type' => 'weekend',
                'badge' => 'Heiliges Wochenende',
                'title' => 'Sa & So = 100% Freie Gestaltung (Kein Pflicht-Tracking)',
                'advice' => 'Deine Serien (🔥) sind am Wochenende geschützt. Tanke Energie für den Montag-Start um 07:00 Uhr!',
            ];
        } elseif ($morningAvg + 15 < $dayAvg) {
            $tips[] = [
                'type' => 'info',
                'badge' => 'Bio-Rhythmus Analyse',
                'title' => 'Morgenstart (' . $morningAvg . '%) schwächer als Tagesfokus (' . $dayAvg . '%)',
                'advice' => 'Der Einstieg zwischen 07:00 und 10:00 Uhr bremst dich aus. Abends um 22:00 Uhr konsequent Bildschirme aus!',
            ];
        } elseif ($eveningAvg + 15 < $morningAvg) {
            $tips[] = [
                'type' => 'info',
                'badge' => 'Bio-Rhythmus Analyse',
                'title' => 'Starker Morgen (' . $morningAvg . '%), aber Abend-Drop (' . $eveningAvg . '%)',
                'advice' => 'Nach 17:00 Uhr sinkt die Disziplin. Halte den Feierabend-Reset um 17:00 Uhr strikt ein.',
            ];
        } else {
            $tips[] = [
                'type' => 'info',
                'badge' => 'Bio-Rhythmus Balance',
                'title' => 'Morgen (' . $morningAvg . '%) · Tag (' . $dayAvg . '%) · Abend (' . $eveningAvg . '%)',
                'advice' => 'Gleichmäßige Energieverteilung über den gesamten Werktag. Fokus auf Mo–Fr Konstanz halten!',
            ];
        }

        return $tips;
    }

    public function toggleActive($id)
    {
        $routine = RoutineModel::find($id);
        if ($routine) {
            $routine->is_active = !$routine->is_active;
            $routine->save();
        }
    }

    public function create()
    {
        $this->resetInput();
        $this->isEditing = true;
    }

    public function edit($id)
    {
        $r = RoutineModel::with('steps')->find($id);
        if (!$r) return;
        $this->r_id = $r->id;
        $this->r_time = Carbon::parse($r->start_time)->format('H:i');
        $this->r_title = $r->title;
        $this->r_message = $r->message;
        $this->r_duration = $r->duration_minutes;
        $this->r_type = $r->type ?? 'general';
        $this->r_days_of_week = $r->days_of_week ?? 'all';
        $this->r_steps = $r->steps->map(fn($s) => [
            'id' => $s->id,
            'title' => $s->title,
            'duration_minutes' => $s->duration_minutes,
        ])->toArray();
        $this->isEditing = true;
    }

    public function addEditorStep(): void
    {
        $this->r_steps[] = [
            'id' => null,
            'title' => '',
            'duration_minutes' => 5,
        ];
    }

    public function removeEditorStep(int $index): void
    {
        if (isset($this->r_steps[$index])) {
            unset($this->r_steps[$index]);
            $this->r_steps = array_values($this->r_steps);
        }
    }

    public function save()
    {
        $this->validate([
            'r_time' => 'required',
            'r_title' => 'required|string|max:255',
            'r_message' => 'nullable|string|max:2000',
            'r_duration' => 'required|integer|min:1|max:1440',
            'r_type' => 'required|string|max:50',
            'r_days_of_week' => 'nullable|string|in:all,weekdays,weekend'
        ]);

        $existing = $this->r_id ? RoutineModel::find($this->r_id) : null;
        $attributes = [
            'start_time' => $this->r_time,
            'title' => $this->r_title,
            'message' => $this->r_message,
            'duration_minutes' => (int) $this->r_duration,
            'type' => $this->r_type,
            'days_of_week' => $this->r_days_of_week ?? 'all',
            'is_active' => $existing ? $existing->is_active : true,
        ];

        if ($existing) {
            $existing->update($attributes);
            $routine = $existing;
        } else {
            $routine = RoutineModel::create($attributes);
        }

        $keepIds = [];
        foreach ($this->r_steps as $idx => $stepData) {
            $stepTitle = trim((string) ($stepData['title'] ?? ''));
            if ($stepTitle === '') {
                continue;
            }
            $stepDur = max(1, (int) ($stepData['duration_minutes'] ?? 5));
            if (!empty($stepData['id'])) {
                $stepModel = ManagementDayRoutineStep::where('day_routine_id', $routine->id)
                    ->where('id', $stepData['id'])
                    ->first();
                if ($stepModel) {
                    $stepModel->update([
                        'title' => $stepTitle,
                        'duration_minutes' => $stepDur,
                        'position' => $idx + 1,
                    ]);
                    $keepIds[] = $stepModel->id;
                    continue;
                }
            }
            $createdStep = ManagementDayRoutineStep::create([
                'id' => Str::uuid(),
                'day_routine_id' => $routine->id,
                'title' => $stepTitle,
                'duration_minutes' => $stepDur,
                'position' => $idx + 1,
            ]);
            $keepIds[] = $createdStep->id;
        }

        ManagementDayRoutineStep::where('day_routine_id', $routine->id)
            ->whereNotIn('id', $keepIds)
            ->delete();

        $this->isEditing = false;
        $this->resetInput();
    }

    public function delete($id)
    {
        RoutineModel::destroy($id);
    }

    public function cancel()
    {
        $this->isEditing = false;
        $this->resetInput();
    }

    private function resetInput()
    {
        $this->reset(['r_id', 'r_time', 'r_title', 'r_message', 'r_duration', 'r_steps']);
        $this->r_type = 'general';
        $this->r_days_of_week = 'all';
    }
}
