<?php

namespace App\Livewire\Shop\Config;

use Livewire\Component;
use App\Models\Delivery\DeliveryTime;
use App\Models\Delivery\DeliverySetting;
use App\Models\Delivery\DeliveryFeedback;
use Livewire\Attributes\Computed;

class DeliveryTimes extends Component
{
    public $activeDeliveryTimeId = null;

    // Formular-Felder für neue Lieferzeit
    public $isAddingNew = false;
    public $newName = '';
    public $newMinDays = 3;
    public $newMaxDays = 5;
    public $newColor = 'green';
    public $newDescription = '';

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
            'sick_description' => 'Wir sind leider aktuell gesundheitlich etwas angeschlagen und liegen flach. 🤒 Bestellungen werden natürlich weiterhin angenommen, die Fertigung und der Versand verzögern sich jedoch, bis wir wieder fit sind. Wir bitten um dein Verständnis und wünschen dir trotzdem ganz viel Spaß beim Stöbern!'
        ]);

        // Standard 3 Ampel-Lieferzeiten anlegen, falls noch keine existieren
        if (DeliveryTime::count() === 0) {
            $dt1 = DeliveryTime::create([
                'name' => 'Standard',
                'min_days' => 3,
                'max_days' => 5,
                'color' => 'green',
                'description' => 'Da jedes Seelenstück ein Unikat ist, setzt sich diese Zeit aus der individuellen Fertigung in der Manufaktur und dem anschließenden Postweg zusammen.',
                'is_active' => true,
            ]);

            DeliveryTime::create([
                'name' => 'Erhöhtes Aufkommen',
                'min_days' => 5,
                'max_days' => 8,
                'color' => 'yellow',
                'description' => 'Aufgrund vieler Bestellungen benötigen wir aktuell etwas länger für die liebevolle Handfertigung deines Unikats. Danke für deine Geduld!',
                'is_active' => false,
            ]);

            DeliveryTime::create([
                'name' => 'Hohe Auslastung',
                'min_days' => 10,
                'max_days' => 14,
                'color' => 'red',
                'description' => 'Wir fertigen auf Hochtouren! Bitte beachte die deutlich verlängerte Bearbeitungszeit durch die aktuell extrem hohe Nachfrage.',
                'is_active' => false,
            ]);

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

    public function openAddForm()
    {
        $this->isAddingNew = true;
    }

    public function closeAddForm()
    {
        $this->isAddingNew = false;
    }

    public function addDeliveryTime()
    {
        $this->validate([
            'newName' => 'required|string|max:255',
            'newMinDays' => 'required|integer|min:0',
            'newMaxDays' => 'required|integer|gte:newMinDays',
            'newColor' => 'required|in:green,yellow,red',
            'newDescription' => 'nullable|string|max:500',
        ], [
            'newMaxDays.gte' => 'Das "Bis"-Feld muss größer oder gleich dem "Von"-Feld sein.'
        ]);

        DeliveryTime::create([
            'name' => $this->newName,
            'min_days' => $this->newMinDays,
            'max_days' => $this->newMaxDays,
            'color' => $this->newColor,
            'description' => $this->newDescription,
            'is_active' => false,
        ]);

        $this->newName = '';
        $this->newMinDays = 3;
        $this->newMaxDays = 5;
        $this->newColor = 'green';
        $this->newDescription = '';
        $this->isAddingNew = false;

        session()->flash('success', 'Lieferzeit erfolgreich hinzugefügt!');
    }

    public function removeDeliveryTime($id)
    {
        DeliveryTime::where('id', $id)->delete();
        if ($this->activeDeliveryTimeId == $id) {
            $this->activeDeliveryTimeId = null;
        }
        session()->flash('success', 'Lieferzeit gelöscht!');
    }

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
        return view('livewire.shop.config.delivery-times');
    }
}
