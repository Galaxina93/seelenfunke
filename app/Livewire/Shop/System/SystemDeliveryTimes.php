<?php

namespace App\Livewire\Shop\System;

use Livewire\Component;
use App\Models\Delivery\DeliveryTime;
use App\Models\Delivery\DeliverySetting;
use App\Models\Delivery\DeliveryFeedback;
use Livewire\Attributes\Computed;

class SystemDeliveryTimes extends Component
{
    public $activeDeliveryTimeId = null;

    // Settings-Werte
    public $isVacationMode = false;
    public $vacationStartDate;
    public $vacationEndDate;
    public $vacationDescription = '';

    public $isSickMode = false;
    public $sickDescription = '';

    public function mount()
    {
        $setting = DeliverySetting::firstOrCreate(['id' => 1], [
            'is_vacation_mode' => false,
            'is_sick_mode' => false,
            'vacation_description' => 'Mein Seelenfunke macht eine kurze Kreativpause! ☀️ Bestellungen sind weiterhin möglich, werden aber erst nach unserer Rückkehr gefertigt und versendet. Wir wünschen dir trotzdem ganz viel Spaß beim Stöbern im Shop!',
            'sick_description' => 'Einige aus unseren Team sind leider aktuell gesundheitlich etwas angeschlagen und liegen flach. 🤒 Bestellungen werden natürlich weiterhin angenommen, die Fertigung und der Versand verzögern sich jedoch, bis wir wieder fit sind. Wir bitten um dein Verständnis und wünschen dir trotzdem ganz viel Spaß beim Stöbern!'
        ]);

        // Standard 3 Ampel-Lieferzeiten exakt nach Wunsch aufsetzen/updaten
        $dt1 = DeliveryTime::updateOrCreate(
            ['name' => 'Standard'],
            [
                'min_days' => 3,
                'max_days' => 5,
                'color' => 'green',
                'description' => 'Da jedes Seelenstück ein Unikat ist, setzt sich diese Zeit aus der individuellen Fertigung in der Manufaktur und dem anschließenden Postweg zusammen.',
            ]
        );

        DeliveryTime::updateOrCreate(
            ['name' => 'Erhöhtes Aufkommen'],
            [
                'min_days' => 5,
                'max_days' => 8,
                'color' => 'yellow',
                'description' => 'Aufgrund vieler Bestellungen benötigen wir aktuell etwas länger für die liebevolle Handfertigung deines Unikats. Danke für deine Geduld!',
            ]
        );

        DeliveryTime::updateOrCreate(
            ['name' => 'Hohe Auslastung'],
            [
                'min_days' => 10,
                'max_days' => 14,
                'color' => 'red',
                'description' => 'Wir fertigen auf Hochtouren! Bitte beachte die deutlich verlängerte Bearbeitungszeit durch die aktuell extrem hohe Nachfrage.',
            ]
        );

        DeliveryTime::updateOrCreate(
            ['name' => 'Extreme Auslastung'],
            [
                'min_days' => 16,
                'max_days' => 21,
                'color' => 'red',
                'description' => 'Aufgrund extrem hoher Auslastung kommt es derzeit zu Verzögerungen. Dein Unikat wird mit größter Sorgfalt, aber etwas später gefertigt. Danke für dein Verständnis!',
            ]
        );

        // Aufräumen: Alles was nicht diese 4 sind, wird rigoros gelöscht
        DeliveryTime::whereNotIn('name', ['Standard', 'Erhöhtes Aufkommen', 'Hohe Auslastung', 'Extreme Auslastung'])->delete();

        // Aktiven Status ermitteln, Default ist Standard
        $activeCount = DeliveryTime::where('is_active', true)->count();
        if ($activeCount === 0) {
            $dt1->update(['is_active' => true]);
            $this->activeDeliveryTimeId = $dt1->id;
        } else {
            $active = DeliveryTime::where('is_active', true)->first();
            $this->activeDeliveryTimeId = $active ? $active->id : null;
        }

        $this->isVacationMode = $setting->is_vacation_mode;
        $this->vacationStartDate = $setting->vacation_start_date ? $setting->vacation_start_date->format('Y-m-d') : '';
        $this->vacationEndDate = $setting->vacation_end_date ? $setting->vacation_end_date->format('Y-m-d') : '';
        $this->vacationDescription = $setting->vacation_description;

        $this->isSickMode = $setting->is_sick_mode;
        $this->sickDescription = $setting->sick_description;
    }

    #[Computed]
    public function deliveryTimes()
    {
        return DeliveryTime::orderBy('created_at')->get();
    }

    #[Computed]
    public function feedbackLogs()
    {
        return DeliveryFeedback::latest()->get();
    }

    #[Computed]
    public function vacationWishesCount()
    {
        return DeliveryFeedback::where('type', 'vacation')->count();
    }

    #[Computed]
    public function sickWishesCount()
    {
        return DeliveryFeedback::where('type', 'sick')->count();
    }

    // Adding/Deleting custom times has been permanently removed by CEO logic restriction.

    public function setActiveDeliveryTime($id)
    {
        DeliveryTime::query()->update(['is_active' => false]);
        DeliveryTime::where('id', $id)->update(['is_active' => true]);
        $this->activeDeliveryTimeId = $id;
        session()->flash('success', 'Standard-Lieferzeit wurde aktualisiert!');
    }

    public function updatedIsVacationMode($value)
    {
        if ($value) {
            $this->isSickMode = false;
        }
        $this->saveSettings();
    }

    public function updatedIsSickMode($value)
    {
        if ($value) {
            $this->isVacationMode = false;
        }
        $this->saveSettings();
    }

    public function saveSettings()
    {
        $setting = DeliverySetting::first();
        if ($setting) {
            $setting->update([
                'is_vacation_mode' => $this->isVacationMode,
                'vacation_start_date' => $this->vacationStartDate ?: null,
                'vacation_end_date' => $this->vacationEndDate ?: null,
                'vacation_description' => $this->vacationDescription,
                'is_sick_mode' => $this->isSickMode,
                'sick_description' => $this->sickDescription,
            ]);
        }

        session()->flash('success', 'Einstellungen erfolgreich gespeichert!');
    }

    public function render()
    {
        return view('livewire.shop.system.system-delivery-times');
    }
}
