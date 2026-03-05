<?php

namespace App\Livewire\Shop\Newsletter;

use App\Mail\AutomaticNewsletterMail;
use App\Models\Newsletter\Newsletter;
use App\Models\Newsletter\NewsletterSubscriber;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Livewire\Component;
use Livewire\WithPagination;

class ShopNewsletter extends Component
{
    use WithPagination;

    public $activeTab = 'templates'; // 'templates', 'subscribers', 'archive'
    public $search = '';

    // Editor State
    public $editingTemplateId = null;
    public $edit_subject;
    public $edit_content;
    public $edit_offset;
    public $edit_event_date = null; // Neu: Speichert das Datum des Events für die Live-Vorschau

    public function setTab($tab)
    {
        $this->activeTab = $tab;
        $this->resetPage();
        $this->cancelEdit();
    }

    public function editTemplate($id)
    {
        $t = Newsletter::findOrFail($id);
        $this->editingTemplateId = $t->id;
        $this->edit_subject = $t->subject;

        // Entfernt alle führenden Leerzeichen/Tabs am Anfang jeder Zeile für eine saubere Code-Ansicht
        $this->edit_content = preg_replace('/^[ \t]+/m', '', $t->content);

        $this->edit_offset = $t->days_offset;

        // Berechne das dazugehörige Event-Datum für dieses oder nächstes Jahr
        $year = date('Y');
        $eventDate = $this->getHolidayDate($t->target_event_key, $year);
        $sendDate = $eventDate->copy()->subDays($t->days_offset);

        if ($sendDate->isPast() && !$sendDate->isToday()) {
            $eventDate = $this->getHolidayDate($t->target_event_key, $year + 1);
        }

        $this->edit_event_date = $eventDate->format('Y-m-d');
    }

    public function saveTemplate()
    {
        $this->validate([
            'edit_subject' => 'required|string',
            'edit_content' => 'required|string',
            'edit_offset' => 'required|integer'
        ]);

        Newsletter::find($this->editingTemplateId)->update([
            'subject' => $this->edit_subject,
            'content' => $this->edit_content,
            'days_offset' => $this->edit_offset
        ]);

        $this->editingTemplateId = null;
        session()->flash('success', 'Kampagne aktualisiert.');
    }

    public function archiveTemplate($id)
    {
        Newsletter::where('id', $id)->update(['is_active' => false]);
        session()->flash('success', 'Kampagne ins Archiv verschoben.');
        $this->cancelEdit();
    }

    public function restoreTemplate($id)
    {
        Newsletter::where('id', $id)->update(['is_active' => true]);
        session()->flash('success', 'Kampagne reaktiviert.');
    }

    public function cancelEdit()
    {
        $this->editingTemplateId = null;
        $this->reset(['edit_subject', 'edit_content', 'edit_offset', 'edit_event_date']);
    }

    public function sendTestMail()
    {
        $this->validate([
            'edit_subject' => 'required|string|min:3',
            'edit_content' => 'required|string|min:3',
        ]);

        try {
            $adminEmail = shop_setting('owner_email', 'kontakt@mein-seelenfunke.de');
            $testSubject = '[TEST] ' . $this->edit_subject;

            // 1. Ersetze die Standard-Tags mit echten Test-Werten
            $contentReplaced = str_replace(
                ['{first_name}', '{year}', '{NAME}', '{URL}', '{UNSUBSCRIBE_LINK}'],
                ['Admin', date('Y'), 'Admin', url('/'), url('/newsletter')],
                $this->edit_content
            );

            // 2. Erzeuge Dummy-Objekte
            $templateMock = (object) ['subject' => $testSubject];
            $subscriberMock = (object) ['email' => $adminEmail];

            // 3. Render das Blade-Template zu reinem HTML
            $html = view('global.mails.newsletter.default', [
                'template'   => $templateMock,
                'content'    => $contentReplaced,
                'subscriber' => $subscriberMock
            ])->render();

            // 4. Versende das HTML direkt via SMTP
            Mail::html($html, function ($message) use ($adminEmail, $testSubject) {
                $message->to($adminEmail)
                    ->subject($testSubject);
            });

            session()->flash('test_success', 'Testmail an ' . $adminEmail . ' gesendet! ✨');
        } catch (\Exception $e) {
            Log::error('Newsletter Testmail Fehler: ' . $e->getMessage());
            session()->flash('test_error', 'Fehler: ' . $e->getMessage());
        }
    }

    public function deleteSubscriber($id)
    {
        if (NewsletterSubscriber::destroy($id)) {
            session()->flash('success', 'Empfänger entfernt.');
        }
    }

    // --- Interne Helper für die Feiertags-Timeline ---

    private function getAvailableEvents(): array
    {
        return [
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
    }

    private function getHolidayDate($key, $year): Carbon
    {
        switch ($key) {
            case 'valentines': return Carbon::create($year, 2, 14);
            case 'womens_day': return Carbon::create($year, 3, 8);
            case 'halloween': return Carbon::create($year, 10, 31);
            case 'christmas': return Carbon::create($year, 12, 24);
            case 'new_year': return Carbon::create($year, 1, 1);
            case 'sale_summer': return Carbon::create($year, 7, 1);
            case 'sale_winter': return Carbon::create($year, 1, 1);
            case 'easter': return $this->getEasterDate($year);
            case 'mothers_day': return Carbon::create($year, 5, 1)->nthOfMonth(2, Carbon::SUNDAY);
            case 'fathers_day': return $this->getEasterDate($year)->addDays(39);
            case 'advent_1': return Carbon::create($year, 11, 26)->next(Carbon::SUNDAY);
            default: return Carbon::now();
        }
    }

    private function getEasterDate($year): Carbon
    {
        $K = (int)($year / 100);
        $M = 15 + (int)((3 * $K + 3) / 4) - (int)((8 * $K + 13) / 25);
        $S = 2 - (int)((3 * $K + 3) / 4);
        $A = $year % 19;
        $D = (19 * $A + $M) % 30;
        $R = (int)($D / 29) + ((int)($D / 28) - (int)($D / 29)) * (int)($A / 11);
        $OG = 21 + $D - $R;
        $SZ = 7 - ($year + (int)($year / 4) + $S) % 7;
        $OE = 7 - ($OG - $SZ) % 7;

        return Carbon::createFromDate($year, 3, 1)->addDays($OG + $OE - 1);
    }

    private function getNewsletterTimeline($year)
    {
        $templates = Newsletter::where('is_active', true)->get();
        $events = [];

        foreach ($this->getAvailableEvents() as $key => $label) {
            $tmpl = $templates->where('target_event_key', $key)->first();

            if ($tmpl) {
                $eventDate = $this->getHolidayDate($key, $year);
                $sendDate = $eventDate->copy()->subDays($tmpl->days_offset);

                // Wenn das Datum für dieses Jahr schon vorbei ist, nimm das nächste Jahr
                if ($sendDate->isPast() && !$sendDate->isToday()) {
                    $eventDate = $this->getHolidayDate($key, $year + 1);
                    $sendDate = $eventDate->copy()->subDays($tmpl->days_offset);
                }

                $events[] = [
                    'date' => $sendDate, // Wird für die korrekte chronologische Sortierung genutzt
                    'event_date' => $eventDate, // Das eigentliche Ereignis-Datum
                    'title' => $tmpl->subject,
                    'template_id' => $tmpl->id,
                    'type' => 'mail',
                    'event_key' => $key,
                    'event_name' => $label
                ];
            }
        }

        // Chronologisch nach dem VERSAND-Datum sortieren
        return collect($events)->sortBy('date');
    }

    public function render()
    {
        $subscribers = collect();
        if ($this->activeTab === 'subscribers') {
            $subscribers = NewsletterSubscriber::where('email', 'like', "%{$this->search}%")
                ->paginate(15);
        }

        $archivedTemplates = collect();
        if ($this->activeTab === 'archive') {
            $archivedTemplates = Newsletter::where('is_active', false)->get();
        }

        return view('livewire.shop.newsletter.shop-newsletter', [
            'newsletterTimeline' => $this->getNewsletterTimeline(date('Y')),
            'subscribers' => $subscribers,
            'archivedTemplates' => $archivedTemplates,
            'stats' => [
                'subscribers' => NewsletterSubscriber::count(),
                'active_templates' => Newsletter::where('is_active', true)->count()
            ]
        ]);
    }
}
