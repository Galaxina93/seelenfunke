<?php

namespace App\Livewire\Shop\System;

use App\Livewire\Traits\WithDepartmentTheming;

use Livewire\Component;
use App\Models\Delivery\DeliveryTime;
use App\Models\Delivery\DeliverySetting;
use App\Models\Delivery\DeliveryFeedback;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;

class SystemDeliveryTimes extends Component
{
    use WithDepartmentTheming;
    
    public string $themingDepartment = 'System';

    public $activeDeliveryTimeId = null;

    // Settings-Werte
    public $isVacationMode = false;
    public $vacationStartDate;
    public $vacationEndDate;
    public $vacationDescription = '';

    public $isSickMode = false;
    public $sickDescription = '';

    public function boot(): void
    {
        if (!Auth::guard('admin')->check() && !\App\Services\AI\AiAuthHelper::isEmployee() && !\App\Services\AI\AiAuthHelper::isAdmin()) {
            abort(403);
        }
    }

    public function mount()
    {
        if (!Auth::guard('admin')->check() && !\App\Services\AI\AiAuthHelper::isEmployee() && !\App\Services\AI\AiAuthHelper::isAdmin()) {
            abort(403);
        }

        $setting = DeliverySetting::firstOrCreate(['id' => 1], [
            'is_vacation_mode' => false,
            'is_sick_mode' => false,
            'vacation_description' => 'Mein Seelenfunke macht eine kurze Kreativpause! ☀️ Bestellungen sind weiterhin möglich, werden aber erst nach unserer Rückkehr gefertigt und versendet. Wir wünschen dir trotzdem ganz viel Spaß beim Stöbern im Shop!',
            'sick_description' => 'Einige aus unseren Team sind leider aktuell gesundheitlich etwas angeschlagen und liegen flach. 🤒 Bestellungen werden natürlich weiterhin angenommen, die Fertigung und der Versand verzögern sich jedoch, bis wir wieder fit sind. Wir bitten um dein Verständnis und wünschen dir trotzdem ganz viel Spaß beim Stöbern!'
        ]);

        $standardNames = ['Standard', 'Erhöhtes Aufkommen', 'Hohe Auslastung', 'Extreme Auslastung'];
        $existingNames = DeliveryTime::pluck('name')->toArray();

        if (count($existingNames) !== 4 || count(array_diff($standardNames, $existingNames)) > 0 || count(array_diff($existingNames, $standardNames)) > 0) {
            DeliveryTime::updateOrCreate(
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

            DeliveryTime::whereNotIn('name', $standardNames)->delete();
        }

        // Aktiven Status ermitteln, Default ist Standard (und Mehrfach-Aktivierungen bereinigen)
        $activeRows = DeliveryTime::where('is_active', true)->orderBy('id')->get();
        if ($activeRows->isEmpty()) {
            $dt1 = DeliveryTime::where('name', 'Standard')->first() ?? DeliveryTime::first();
            if ($dt1) {
                $dt1->update(['is_active' => true]);
                $this->activeDeliveryTimeId = $dt1->id;
            }
        } else {
            $firstActive = $activeRows->first();
            $this->activeDeliveryTimeId = $firstActive->id;
            if ($activeRows->count() > 1) {
                DeliveryTime::where('id', '!=', $firstActive->id)->update(['is_active' => false]);
            }
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
        if (!Auth::guard('admin')->check() && !\App\Services\AI\AiAuthHelper::isEmployee() && !\App\Services\AI\AiAuthHelper::isAdmin()) {
            abort(403);
        }

        $target = DeliveryTime::findOrFail($id);
        DeliveryTime::query()->update(['is_active' => false]);
        $target->update(['is_active' => true]);
        $this->activeDeliveryTimeId = $target->id;
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

    public function saveSettings(): bool
    {
        if (!Auth::guard('admin')->check() && !\App\Services\AI\AiAuthHelper::isEmployee() && !\App\Services\AI\AiAuthHelper::isAdmin()) {
            abort(403);
        }
        $rules = [
            'isVacationMode' => 'boolean',
            'vacationStartDate' => 'nullable|date',
            'vacationEndDate' => 'nullable|date',
            'vacationDescription' => 'nullable|string|max:2000',
            'isSickMode' => 'boolean',
            'sickDescription' => 'nullable|string|max:2000',
        ];
        if ($this->isVacationMode && !empty($this->vacationStartDate) && !empty($this->vacationEndDate)) {
            $rules['vacationEndDate'] = 'nullable|date|after_or_equal:vacationStartDate';
        }
        $this->validate($rules, [
            'vacationEndDate.after_or_equal' => 'Das Enddatum des Urlaubs darf nicht vor dem Startdatum liegen.',
        ], [
            'vacationStartDate' => 'Urlaubsbeginn',
            'vacationEndDate' => 'Urlaubsende',
        ]);

        $isVacation = (bool) $this->isVacationMode;
        $isSick = (bool) $this->isSickMode;
        if ($isVacation && $isSick) {
            $isSick = false;
            $this->isSickMode = false;
        }

        $setting = DeliverySetting::first();
        if ($setting) {
            $setting->update([
                'is_vacation_mode' => $isVacation,
                'vacation_start_date' => $this->vacationStartDate ?: null,
                'vacation_end_date' => $this->vacationEndDate ?: null,
                'vacation_description' => $this->vacationDescription,
                'is_sick_mode' => $isSick,
                'sick_description' => $this->sickDescription,
            ]);
        }

        \App\Models\System\SystemSetting::updateOrCreate(
            ['key' => 'is_vacation_mode'],
            ['value' => $isVacation ? 'true' : 'false']
        );
        \Illuminate\Support\Facades\Cache::forget('global_shop_settings');

        session()->flash('success', 'Einstellungen erfolgreich gespeichert!');
        return true;
    }

    public function render()
    {
        return view('livewire.shop.system.system-delivery-times');
    }
}
