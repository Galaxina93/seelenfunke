<?php

namespace App\Livewire\Shop\Management;

use Livewire\Attributes\Layout;

use App\Models\Management\ManagementCalendarEvent;
use Carbon\Carbon;
use DateInterval;
use Livewire\Component;
use App\Livewire\Traits\WithDepartmentTheming;
use Livewire\WithFileUploads;

#[Layout('components.layouts.backend_layout')]
class ManagementCalender extends Component
{
    use WithDepartmentTheming;

    public string $themingDepartment = 'Leitung';

    use WithFileUploads;

    public $currentDate;
    public $view = 'month'; // month, week, day, year, multi-week, list
    public $importFile;
    public $isImporting = false;

    // --- EDITOR VARIABLES ---
    public $showEditModal = false;
    public $editingEventId = null;
    public $editTitle;
    public $editStartDate;
    public $editStartTime;
    public $editEndDate;
    public $editEndTime;
    public $editIsAllDay = false;
    public $editCategory;
    public $editDescription;
    public $editPriority = 'low';

    // Wiederholung & Erinnerung
    public $editRecurrence = null;
    public $editRecurrenceEnd;
    public $editReminderMinutes = null;

    public function mount()
    {
        $this->currentDate = Carbon::now();
    }

    // --- NAVIGATION ---

    public function next()
    {
        if ($this->view === 'year') $this->currentDate->addYear();
        elseif ($this->view === 'month' || $this->view === 'list') $this->currentDate->addMonth();
        elseif ($this->view === 'multi-week') $this->currentDate->addWeeks(4);
        elseif ($this->view === 'week') $this->currentDate->addWeek();
        else $this->currentDate->addDay();
    }

    public function prev()
    {
        if ($this->view === 'year') $this->currentDate->subYear();
        elseif ($this->view === 'month' || $this->view === 'list') $this->currentDate->subMonth();
        elseif ($this->view === 'multi-week') $this->currentDate->subWeeks(4);
        elseif ($this->view === 'week') $this->currentDate->subWeek();
        else $this->currentDate->subDay();
    }

    public function today()
    {
        $this->currentDate = Carbon::now();
    }

    public function setView($view)
    {
        $this->view = $view;
    }

    public function goToDay($dateStr)
    {
        $this->currentDate = Carbon::parse($dateStr);
        $this->view = 'day';
    }

    /**
     * Springt zu einem spezifischen Monat (aus Jahresansicht)
     */
    public function zoomIntoMonth($dateStr)
    {
        $this->currentDate = Carbon::parse($dateStr);
        $this->view = 'month';
    }

    // --- CRUD ---

    public function createEvent($date = null)
    {
        $this->resetEditor();
        $targetDate = $date ? Carbon::parse($date) : $this->currentDate->copy();

        $this->editStartDate = $targetDate->format('Y-m-d');
        $this->editEndDate = $targetDate->format('Y-m-d');

        $this->editStartTime = Carbon::now()->addHour()->startOfHour()->format('H:i');
        $this->editEndTime = Carbon::now()->addHour()->startOfHour()->addHour()->format('H:i');

        $this->editIsAllDay = false;
        $this->showEditModal = true;
    }

    public function editEvent($id)
    {
        $event = ManagementCalendarEvent::find($id);
        if (!$event) return;

        $this->editingEventId = $event->id;
        $this->editTitle = $event->title;
        $this->editStartDate = $event->start_date->format('Y-m-d');
        $this->editStartTime = $event->is_all_day ? '' : $event->start_date->format('H:i');

        $this->editEndDate = $event->end_date ? $event->end_date->format('Y-m-d') : $this->editStartDate;
        $this->editEndTime = ($event->end_date && !$event->is_all_day) ? $event->end_date->format('H:i') : '';

        $this->editIsAllDay = $event->is_all_day;
        $this->editCategory = $event->category;
        $this->editDescription = $event->description;
        $this->editPriority = $event->priority ?? 'low';

        $this->editRecurrence = $event->recurrence;
        $this->editRecurrenceEnd = $event->recurrence_end_date ? $event->recurrence_end_date->format('Y-m-d') : '';
        $this->editReminderMinutes = $event->reminder_minutes;

        $this->showEditModal = true;
    }

    public function saveEvent()
    {
        $this->validate([
            'editTitle' => 'required|min:2',
            'editStartDate' => 'required|date',
        ]);

        $startString = $this->editStartDate . ' ' . ($this->editIsAllDay ? '00:00:00' : ($this->editStartTime ?: '09:00:00'));
        $start = Carbon::parse($startString);

        if ($this->editIsAllDay) {
            $endString = ($this->editEndDate ?: $this->editStartDate) . ' 23:59:59';
            $end = Carbon::parse($endString);
        } else {
            $endTimeStr = $this->editEndTime ?: $start->copy()->addHour()->format('H:i');
            $endString = ($this->editEndDate ?: $this->editStartDate) . ' ' . $endTimeStr;
            $end = Carbon::parse($endString);

            if($end < $start) $end = $start->copy()->addHour();
        }

        $data = [
            'title' => $this->editTitle,
            'start_date' => $start,
            'end_date' => $end,
            'is_all_day' => $this->editIsAllDay,
            'category' => $this->editCategory,
            'description' => $this->editDescription,
            'priority' => $this->editPriority,
            'recurrence' => $this->editRecurrence === '' ? null : $this->editRecurrence,
            'recurrence_end_date' => $this->editRecurrenceEnd ? Carbon::parse($this->editRecurrenceEnd) : null,
            'reminder_minutes' => $this->editReminderMinutes === '' ? null : $this->editReminderMinutes,
        ];

        if ($this->editingEventId) {
            ManagementCalendarEvent::find($this->editingEventId)->update($data);
        } else {
            ManagementCalendarEvent::create($data);
        }

        session()->flash('calendar_success', 'Termin gespeichert.');
        $this->closeModal();
    }

    public function deleteEvent()
    {
        if ($this->editingEventId) {
            ManagementCalendarEvent::destroy($this->editingEventId);
            session()->flash('calendar_success', 'Termin gelöscht.');
        }
        $this->closeModal();
    }

    public function closeModal()
    {
        $this->showEditModal = false;
        $this->resetEditor();
    }

    private function resetEditor()
    {
        $this->reset([
            'editingEventId', 'editTitle', 'editStartDate', 'editStartTime',
            'editEndDate', 'editEndTime', 'editIsAllDay', 'editCategory',
            'editDescription', 'editPriority', 'editRecurrence', 'editRecurrenceEnd', 'editReminderMinutes'
        ]);
        $this->editCategory = 'general';
        $this->editPriority = 'low';
        $this->editIsAllDay = false;
    }

    // --- IMPORT ---

    public function importEvents()
    {
        $this->validate(['importFile' => 'required|file|max:2048']);
        $content = file_get_contents($this->importFile->getRealPath());
        $count = $this->parseIcs($content);
        $this->reset('importFile', 'isImporting');
        session()->flash('calendar_success', "$count Termine erfolgreich importiert.");
    }

    private function parseIcs($content)
    {
        $content = preg_replace('/\r\n[ \t]/', '', $content);
        preg_match_all('/BEGIN:VEVENT(.*?)END:VEVENT/s', $content, $matches);
        $count = 0;

        foreach ($matches[1] as $eventBlock) {
            preg_match('/UID:(.*?)(?:\r\n|\n|$)/', $eventBlock, $mUid);
            $uid = isset($mUid[1]) ? trim($mUid[1]) : null;

            preg_match('/SUMMARY:(.*?)(?:\r\n|\n|$)/', $eventBlock, $mTitle);
            $title = 'Termin';
            if (isset($mTitle[1])) {
                $title = trim($mTitle[1]);
                $title = str_replace(['&shy;', '\,', '\;', '\n', '\N'], ['', ',', ';', ' ', ' '], $title);
                $title = html_entity_decode($title);
            }

            preg_match('/DTSTART([^:]*):(\d{8}(?:T\d{6}Z?)?)/', $eventBlock, $mStart);
            if (empty($mStart[2])) continue;

            $params = $mStart[1];
            $rawDate = $mStart[2];
            $timezone = config('app.timezone');
            if (preg_match('/TZID=([^;]+)/', $params, $mTz)) {
                $timezone = $mTz[1];
            }

            preg_match('/DTEND([^:]*):(\d{8}(?:T\d{6}Z?)?)/', $eventBlock, $mEnd);

            try {
                $isAllDay = (strlen($rawDate) === 8);
                if ($isAllDay) {
                    $startDate = Carbon::createFromFormat('Ymd', $rawDate, $timezone)->startOfDay();
                } else {
                    if (str_ends_with($rawDate, 'Z')) {
                        $startDate = Carbon::parse($rawDate)->setTimezone($timezone);
                    } else {
                        $startDate = Carbon::createFromFormat('Ymd\THis', $rawDate, $timezone);
                    }
                }

                if (!empty($mEnd[2])) {
                    $rawEnd = $mEnd[2];
                    if (strlen($rawEnd) === 8) {
                        // ICS End dates for all day events are EXCLUSIVE (next day at 00:00).
                        $endDate = Carbon::createFromFormat('Ymd', $rawEnd, $timezone)->subDay()->endOfDay();
                    } else {
                        if (str_ends_with($rawEnd, 'Z')) {
                            $endDate = Carbon::parse($rawEnd)->setTimezone($timezone);
                        } else {
                            $endDate = Carbon::createFromFormat('Ymd\THis', $rawEnd, $timezone);
                        }
                    }
                } else {
                    $endDate = $isAllDay ? $startDate->copy()->endOfDay() : $startDate->copy()->addHour();
                }

                $reminderMinutes = null;
                if (preg_match('/TRIGGER:(?:-)?(P.*?)(?:\r\n|\n|$)/', $eventBlock, $mTrigger)) {
                    try {
                        $interval = new DateInterval(trim($mTrigger[1]));
                        $minutes = ($interval->d * 1440) + ($interval->h * 60) + $interval->i;
                        if ($minutes > 0) $reminderMinutes = $minutes;
                    } catch (\Exception $e) {}
                }

                $category = $this->detectCategory($title);

                ManagementCalendarEvent::updateOrCreate(
                    ['ics_uid' => $uid],
                    [
                        'title' => $title,
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                        'is_all_day' => $isAllDay,
                        'category' => $category,
                        'reminder_minutes' => $reminderMinutes,
                        'description' => 'Importiert aus ICS',
                        'priority' => 'low'
                    ]
                );
                $count++;

            } catch (\Exception $e) { continue; }
        }
        return $count;
    }

    private function detectCategory($title)
    {
        $t = mb_strtolower($title);
        if (str_contains($t, 'restmüll') || str_contains($t, 'graue')) return 'restmuell';
        if (str_contains($t, 'papier') || str_contains($t, 'blaue')) return 'altpapier';
        if (str_contains($t, 'bio') || str_contains($t, 'braune')) return 'biomuell';
        if (str_contains($t, 'gelber') || str_contains($t, 'wertstoff')) return 'gelber_sack';
        if (str_contains($t, 'schadstoff') || str_contains($t, 'gift')) return 'schadstoffe';
        if (str_contains($t, 'sperrmüll')) return 'sperrmuell';
        if (str_contains($t, 'grün') || str_contains($t, 'garten')) return 'gruen';
        if (str_contains($t, 'baum') || str_contains($t, 'weihnacht')) return 'baum';
        if (str_contains($t, 'anruf')) return 'call';
        if (str_contains($t, 'besprechung') || str_contains($t, 'meeting')) return 'meeting';
        if (str_contains($t, 'geburtstag')) return 'birthday';
        if (str_contains($t, 'urlaub') || str_contains($t, 'ferien')) return 'vacation';
        if (str_contains($t, 'reise')) return 'travel';
        if (str_contains($t, 'projekt')) return 'project';
        if (str_contains($t, 'kunde')) return 'customer';
        return 'general';
    }

    // --- VIEW DATA ---

    public function getEventsProperty()
    {
        // Zeitraum definieren
        if ($this->view === 'year') {
            $start = $this->currentDate->copy()->startOfYear();
            $end = $this->currentDate->copy()->endOfYear();
        } elseif ($this->view === 'multi-week') {
            $start = $this->currentDate->copy()->startOfWeek();
            $end = $this->currentDate->copy()->addWeeks(4)->endOfWeek();
        } elseif ($this->view === 'list') {
            // Liste zeigt standardmäßig den aktuellen Monat (für Navigation)
            $start = $this->currentDate->copy()->startOfMonth();
            $end = $this->currentDate->copy()->endOfMonth();
        } else {
            // Month, Week, Day
            $start = $this->currentDate->copy()->startOfMonth()->startOfWeek();
            $end = $this->currentDate->copy()->endOfMonth()->endOfWeek();
        }

        // Normale Events
        $normalEvents = ManagementCalendarEvent::where(function($query) use ($start, $end) {
            $query->where('start_date', '<=', $end)
                  ->where('end_date', '>=', $start);
        })
        ->whereNull('recurrence')
        ->get();

        // Wiederholungen berechnen
        $recurringEvents = ManagementCalendarEvent::whereNotNull('recurrence')->get();
        $generatedEvents = collect();

        foreach ($recurringEvents as $template) {
            $evtStart = $template->start_date;

            if ($template->recurrence_end_date && $template->recurrence_end_date < $start) continue;

            $simDate = $evtStart->copy();

            // Loop durch die Tage des View-Zeitraums
            while ($simDate <= $end) {
                if ($simDate >= $start && $simDate <= $end) {
                    if ($template->recurrence_end_date && $simDate > $template->recurrence_end_date) break;

                    $instance = $template->replicate();
                    $instance->id = $template->id;
                    $instance->start_date = $simDate->copy();

                    if (!$template->is_all_day) {
                        $instance->start_date->setTimeFrom($template->start_date);
                    }

                    $duration = $template->start_date->diffInSeconds($template->end_date);
                    $instance->end_date = $instance->start_date->copy()->addSeconds(abs($duration));
                    $instance->is_recurring_instance = true;

                    $generatedEvents->push($instance);
                }

                switch ($template->recurrence) {
                    case 'daily': $simDate->addDay(); break;
                    case 'weekly': $simDate->addWeek(); break;
                    case 'monthly': $simDate->addMonth(); break;
                    case 'yearly': $simDate->addYear(); break;
                    default: $simDate->addYear(100);
                }
            }
        }

        return $normalEvents->toBase()->concat($generatedEvents)->sortBy('start_date')->values();
    }

    public function getCalendarGridProperty()
    {
        $grid = [];

        // Start/Ende je nach View für das Grid Layout
        if ($this->view === 'multi-week') {
            $start = $this->currentDate->copy()->startOfWeek();
            $end = $this->currentDate->copy()->addWeeks(3)->endOfWeek(); // Insgesamt 4 Wochen
        } elseif ($this->view === 'week') {
            $start = $this->currentDate->copy()->startOfWeek();
            $end = $this->currentDate->copy()->endOfWeek();
        } elseif ($this->view === 'day') {
            $start = $this->currentDate->copy()->startOfDay();
            $end = $this->currentDate->copy()->endOfDay();
        } else {
            // Month
            $start = $this->currentDate->copy()->startOfMonth()->startOfWeek();
            $end = $this->currentDate->copy()->endOfMonth()->endOfWeek();
        }

        $curr = $start->copy();

        while ($curr <= $end) {
            $dayStart = $curr->copy()->startOfDay();
            $dayEnd = $curr->copy()->endOfDay();

            $dayEvents = $this->events->filter(function($event) use ($dayStart, $dayEnd) {
                $evtStart = $event->start_date;
                $evtEnd = $event->end_date ?: $event->start_date->copy()->endOfDay();
                return $evtStart <= $dayEnd && $evtEnd >= $dayStart;
            })->map(function($event) use ($dayStart, $dayEnd) {
                $e = clone $event;
                $evtStart = $event->start_date;
                $evtEnd = $event->end_date ?: $event->start_date->copy()->endOfDay();

                $e->is_multi_day = !$evtStart->isSameDay($evtEnd);
                if ($e->is_multi_day) {
                    if ($evtStart->isSameDay($dayStart)) {
                        $e->span_type = 'start';
                    } elseif ($evtEnd->isSameDay($dayStart)) {
                        $e->span_type = 'end';
                    } else {
                        $e->span_type = 'middle';
                    }
                } else {
                    $e->span_type = 'single';
                }
                return $e;
            })->values();

            $grid[] = [
                'date' => $curr->copy(),
                'is_current_month' => $curr->isSameMonth($this->currentDate),
                'is_today' => $curr->isToday(),
                'events' => $dayEvents
            ];
            $curr->addDay();
        }

        return $grid;
    }

    public function getWeeksProperty()
    {
        $grid = $this->calendarGrid;
        $weeks = [];
        $chunks = array_chunk($grid, 7);

        foreach ($chunks as $weekDays) {
            $weekStart = $weekDays[0]['date']->copy()->startOfDay();
            $weekEnd = $weekDays[6]['date']->copy()->endOfDay();

            // Find all events overlapping this week
            $weekEvents = $this->events->filter(function($event) use ($weekStart, $weekEnd) {
                $evtStart = $event->start_date;
                $evtEnd = $event->end_date ?: $event->start_date->copy()->endOfDay();
                return $evtStart <= $weekEnd && $evtEnd >= $weekStart;
            });

            // Sort events: multi-day first (longer duration first), then by start date/time
            $sortedEvents = $weekEvents->sortBy(function($event) {
                $duration = $event->start_date->diffInSeconds($event->end_date ?: $event->start_date);
                $isMulti = $event->start_date->isSameDay($event->end_date ?: $event->start_date) ? 1 : 0;
                return sprintf('%d-%012d-%s', $isMulti, 999999999999 - $duration, $event->start_date->toDateTimeString());
            })->values();

            // Track allocation
            $tracks = []; // trackIndex => [colIndex => occupied]
            $allocatedEvents = [];

            foreach ($sortedEvents as $event) {
                $evtStart = $event->start_date;
                $evtEnd = $event->end_date ?: $event->start_date->copy()->endOfDay();

                if ($evtStart < $weekStart) {
                    $startCol = 1;
                } else {
                    $startCol = $evtStart->dayOfWeekIso;
                }

                if ($evtEnd > $weekEnd) {
                    $endCol = 7;
                } else {
                    $endCol = $evtEnd->dayOfWeekIso;
                }

                $span = $endCol - $startCol + 1;

                $allocatedTrack = 0;
                while (true) {
                    $overlap = false;
                    for ($c = $startCol; $c <= $endCol; $c++) {
                        if (isset($tracks[$allocatedTrack][$c])) {
                            $overlap = true;
                            break;
                        }
                    }
                    if (!$overlap) {
                        break;
                    }
                    $allocatedTrack++;
                }

                for ($c = $startCol; $c <= $endCol; $c++) {
                    $tracks[$allocatedTrack][$c] = true;
                }

                $allocatedEvents[] = [
                    'event' => $event,
                    'track' => $allocatedTrack,
                    'start_col' => $startCol,
                    'span' => $span
                ];
            }

            $maxTrack = count($tracks) > 0 ? max(array_keys($tracks)) : 0;

            $weeks[] = [
                'days' => $weekDays,
                'events' => $allocatedEvents,
                'max_track' => $maxTrack,
                'start' => $weekStart,
                'end' => $weekEnd
            ];
        }

        return $weeks;
    }

    public function getYearGridProperty()
    {
        $months = [];
        $startOfYear = $this->currentDate->copy()->startOfYear();

        for ($i = 0; $i < 12; $i++) {
            $monthStart = $startOfYear->copy()->addMonths($i);
            $monthEnd = $monthStart->copy()->endOfMonth();

            $days = [];
            $curr = $monthStart->copy()->startOfWeek();
            $gridEnd = $monthEnd->copy()->endOfWeek();

            while ($curr <= $gridEnd) {
                $dayStart = $curr->copy()->startOfDay();
                $dayEnd = $curr->copy()->endOfDay();

                $hasEvents = $this->events->contains(function($e) use ($dayStart, $dayEnd) {
                    $evtStart = $e->start_date;
                    $evtEnd = $e->end_date ?: $e->start_date->copy()->endOfDay();
                    return $evtStart <= $dayEnd && $evtEnd >= $dayStart;
                });

                $days[] = [
                    'day' => $curr->day,
                    'date' => $curr->copy(),
                    'is_current' => $curr->isSameMonth($monthStart),
                    'has_events' => $hasEvents
                ];
                $curr->addDay();
            }

            $months[] = [
                'name' => $monthStart->locale('de')->monthName,
                'days' => $days
            ];
        }

        return $months;
    }

    public function render()
    {
        return view('livewire.shop.management.management-calender');
    }
}
