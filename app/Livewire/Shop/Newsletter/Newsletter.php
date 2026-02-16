<?php

namespace App\Livewire\Shop\Newsletter;

use App\Mail\NewsletterMail;
use App\Models\NewsletterTemplate; // Neue Model Klasse
use App\Models\NewsletterSubscriber;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Livewire\Component;
use Livewire\WithPagination;

class Newsletter extends Component
{
    use WithPagination;

    // --- View State ---
    public $activeTab = 'calendar'; // 'calendar', 'archive', 'subscribers'
    public $calendarView = 'year';
    public $selectedYear;
    public $selectedMonth;
    public $search = '';

    // --- Edit State (für bestehende Templates) ---
    public $editingTemplateId = null;
    public $edit_subject, $edit_content, $edit_offset;

    // --- Tooltip Texte ---
    public $infoTexts = [
        'system_integrity' => 'Aktive Zyklen: Anzahl der eingeschalteten jährlichen Kampagnen. Empfänger: Anzahl der bestätigten Abonnenten (Double-Opt-In).',
    ];

    // --- Event Definitions ---
    public $availableEvents = [
        'valentines' => 'Valentinstag (14.02.)',
        'womens_day' => 'Weltfrauentag (08.03.)',
        'easter' => 'Ostern (Variabel)',
        'mothers_day' => 'Muttertag (2. So im Mai)',
        'fathers_day' => 'Vatertag (Christi Himmelfahrt)',
        'halloween' => 'Halloween (31.10.)',
        'advent_1' => '1. Advent (Variabel)',
        'christmas' => 'Weihnachten (24.12.)',
        'new_year' => 'Neujahr (01.01.)',
        'sale_summer' => 'Sommer Sale (01.07.)',
        'sale_winter' => 'Winter Sale (01.01.)',
    ];

    public function mount()
    {
        $this->selectedYear = date('Y');
        $this->selectedMonth = date('n');
    }

    public function sendTestMail()
    {
        // 1. Validierung
        $this->validate([
            'edit_subject' => 'required|string',
            'edit_content' => 'required|string',
        ]);

        $adminEmail = shop_setting('owner_email', 'kontakt@mein-seelenfunke.de');

        // 2. FIX: forceFill nutzen, um Mass Assignment Protection zu umgehen
        // Da das Model nicht gespeichert wird, ist das sicher.
        $tempTemplate = new NewsletterTemplate();
        $tempTemplate->forceFill([
            'subject' => '[TEST] ' . $this->edit_subject,
            'content' => $this->edit_content,
            'days_offset' => 0,
            'target_event_key' => 'test_preview'
        ]);

        // Dummy Subscriber
        $dummySubscriber = new NewsletterSubscriber();
        $dummySubscriber->email = $adminEmail;
        $dummySubscriber->first_name = 'Admin';
        $dummySubscriber->last_name = 'User';

        try {
            // Mail Versand
            Mail::to($adminEmail)->send(new NewsletterMail($tempTemplate, $dummySubscriber));

            session()->flash('test_success', 'Testmail wurde an ' . $adminEmail . ' versendet! ✨');
        } catch (\Exception $e) {
            // Fehler Logging für Debugging
            \Illuminate\Support\Facades\Log::error('Newsletter Test Mail Error: ' . $e->getMessage());
            session()->flash('test_error', 'Fehler: ' . $e->getMessage());
        }
    }

    // --- Calculation Helpers ---
    private function getHolidayDate($key, $year)
    {
        switch ($key) {
            case 'valentines': return Carbon::create($year, 2, 14);
            case 'womens_day': return Carbon::create($year, 3, 8);
            case 'halloween': return Carbon::create($year, 10, 31);
            case 'christmas': return Carbon::create($year, 12, 24);
            case 'new_year': return Carbon::create($year, 1, 1);
            case 'sale_summer': return Carbon::create($year, 7, 1);
            case 'sale_winter': return Carbon::create($year, 1, 1);
            case 'easter': return Carbon::createFromDate($year, 3, 21)->addDays(easter_days($year));
            case 'mothers_day': return Carbon::create($year, 5, 1)->nthOfMonth(2, Carbon::SUNDAY);
            case 'fathers_day': return Carbon::createFromDate($year, 3, 21)->addDays(easter_days($year))->addDays(39);
            case 'advent_1': return Carbon::create($year, 11, 26)->next(Carbon::SUNDAY);
            default: return Carbon::now();
        }
    }

    // --- Properties ---

    public function getTemplatesProperty()
    {
        return NewsletterTemplate::all();
    }

    public function getNextScheduledSendProperty()
    {
        $today = Carbon::today();
        $nextSend = null;
        $minDiff = 999;

        // Wir iterieren durch die Templates und berechnen den nächsten Termin
        foreach ($this->templates as $tmpl) {
            if (!$tmpl->is_active) continue;

            $eventDate = $this->getHolidayDate($tmpl->target_event_key, $this->selectedYear);
            $sendDate = $eventDate->copy()->subDays($tmpl->days_offset);

            if ($sendDate->isPast()) {
                $eventDate = $this->getHolidayDate($tmpl->target_event_key, $this->selectedYear + 1);
                $sendDate = $eventDate->copy()->subDays($tmpl->days_offset);
            }

            $diff = $today->diffInDays($sendDate, false);

            if ($diff >= 0 && $diff < $minDiff) {
                $minDiff = $diff;
                $nextSend = [
                    'subject' => $tmpl->subject,
                    'send_date' => $sendDate,
                    'days_left' => $diff
                ];
            }
        }
        return $nextSend;
    }

    public function getCalendarDataProperty()
    {
        $events = [];
        $templates = $this->templates;

        foreach ($this->availableEvents as $key => $label) {
            $date = $this->getHolidayDate($key, $this->selectedYear);

            // 1. Feiertag selbst
            $events[] = [
                'type' => 'holiday',
                'date' => $date,
                'title' => $label,
                'is_action' => false
            ];

            // 2. Suche passendes Template
            $tmpl = $templates->where('target_event_key', $key)->first();

            if($tmpl && $tmpl->is_active) {
                $sendDate = $date->copy()->subDays($tmpl->days_offset);
                $status = $sendDate->isPast() ? 'sent' : 'scheduled';

                $events[] = [
                    'type' => 'mail',
                    'date' => $sendDate,
                    'title' => '📧 ' . $tmpl->subject,
                    'template_id' => $tmpl->id,
                    'is_action' => true,
                    'days_before' => $tmpl->days_offset,
                    'target_event' => $label,
                    'status' => $status
                ];
            }
        }

        usort($events, fn($a, $b) => $a['date'] <=> $b['date']);
        return collect($events);
    }

    // --- Actions ---

    // Template zum Bearbeiten laden
    public function editTemplate($id)
    {
        $t = NewsletterTemplate::find($id);
        if(!$t) return;

        $this->editingTemplateId = $t->id;
        $this->edit_subject = $t->subject;
        $this->edit_content = $t->content;
        $this->edit_offset = $t->days_offset;
    }

    public function saveTemplate()
    {
        $this->validate([
            'edit_subject' => 'required',
            'edit_offset' => 'required|integer',
        ]);

        NewsletterTemplate::find($this->editingTemplateId)->update([
            'subject' => $this->edit_subject,
            'content' => $this->edit_content,
            'days_offset' => $this->edit_offset
        ]);

        $this->editingTemplateId = null;
        session()->flash('success', 'Vorlage aktualisiert.');
    }

    // "Löschen" aus dem Kalender = Archivieren (Deaktivieren)
    public function archiveTemplate($id)
    {
        NewsletterTemplate::find($id)->update(['is_active' => false]);
        session()->flash('success', 'Automatische Mail deaktiviert und ins Archiv verschoben.');
    }

    // Wiederherstellen aus Archiv
    public function restoreTemplate($id)
    {
        NewsletterTemplate::find($id)->update(['is_active' => true]);
        session()->flash('success', 'Automatische Mail wieder aktiviert.');
    }

    public function cancelEdit() {
        $this->editingTemplateId = null;
    }

    public function deleteSubscriber($id)
    {
        NewsletterSubscriber::find($id)->delete();
    }

    public function render()
    {
        // Archivierte Templates laden
        $archivedTemplates = [];
        if($this->activeTab === 'archive') {
            $archivedTemplates = NewsletterTemplate::where('is_active', false)->get();
        }

        // Abonnenten
        $subscribers = [];
        if($this->activeTab === 'subscribers') {
            $subscribers = NewsletterSubscriber::query()
                ->where('email', 'like', '%' . $this->search . '%')
                ->latest()
                ->paginate(10);
        }

        // Kalender View Logic (Jahresliste passiert im Blade, hier nur Monats Grid)
        $calendarGrid = [];
        if ($this->calendarView === 'month' && $this->activeTab === 'calendar') {
            $startOfMonth = Carbon::create($this->selectedYear, $this->selectedMonth, 1);
            $startGrid = $startOfMonth->copy()->startOfWeek();
            $endGrid = $startOfMonth->copy()->endOfMonth()->endOfWeek();

            $current = $startGrid->copy();
            $allEvents = $this->calendarData; // Computed Property nutzen

            while ($current <= $endGrid) {
                $dayEvents = $allEvents->filter(fn($e) => $e['date']->isSameDay($current));

                $calendarGrid[] = [
                    'date' => $current->copy(),
                    'is_current_month' => $current->month == $this->selectedMonth,
                    'is_today' => $current->isToday(),
                    'events' => $dayEvents
                ];
                $current->addDay();
            }
        }

        return view('livewire.shop.newsletter.newsletter', [
            'calendarGrid' => $calendarGrid,
            'archivedTemplates' => $archivedTemplates,
            'subscribers' => $subscribers,
            'subscriberCount' => NewsletterSubscriber::count(),
            'activeTemplatesCount' => NewsletterTemplate::where('is_active', true)->count()
        ]);
    }
}
