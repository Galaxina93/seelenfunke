<?php

namespace App\Livewire\Shop\System;

use App\Livewire\Traits\WithDepartmentTheming;
use App\Models\Delivery\DeliveryFeedback;
use App\Models\Delivery\DeliverySetting;
use App\Models\Delivery\DeliveryTime;
use App\Models\System\SystemSetting;
use App\Services\AI\AiAuthHelper;
use App\Services\Order\ShopCapacityService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;
use Livewire\Component;

class SystemDeliveryTimes extends Component
{
    use WithDepartmentTheming;

    public string $themingDepartment = 'System';

    public $activeDeliveryTimeId = null;

    public bool $capacityAutopilotEnabled = false;
    public float $capacityPercentage = 0.0;
    public int $capacityLevel = 0;

    public $isVacationMode = false;
    public $vacationStartDate;
    public $vacationEndDate;
    public $vacationDescription = '';

    public $isSickMode = false;
    public $sickDescription = '';

    private function assertAuthorized(): void
    {
        if (!Auth::guard('admin')->check() && !AiAuthHelper::isEmployee() && !AiAuthHelper::isAdmin()) {
            abort(403);
        }
    }

    public function boot(): void
    {
        $this->assertAuthorized();
    }

    public function mount(): void
    {
        $this->assertAuthorized();

        $setting = DeliverySetting::firstOrCreate(['id' => 1], [
            'is_vacation_mode' => false,
            'is_sick_mode' => false,
            'vacation_description' => 'Mein Seelenfunke macht eine kurze Kreativpause. Bestellungen sind weiterhin möglich und werden direkt nach unserer Rückkehr in unserer Manufaktur gefertigt und versendet.',
            'sick_description' => 'Aktuell verzögert sich die Fertigung in unserer Manufaktur gesundheitsbedingt um einige Tage. Bestellungen werden selbstverständlich angenommen und mit größter Sorgfalt bearbeitet, sobald wir wieder fit sind.',
        ]);

        $capacityService = app(ShopCapacityService::class);
        $capacityService->ensureCanonicalDeliveryProfiles(true);

        $this->refreshCapacityState();

        $activeRow = DeliveryTime::where('is_active', true)->orderBy('id')->first();
        $this->activeDeliveryTimeId = $activeRow?->id;

        $this->isVacationMode = (bool) $setting->is_vacation_mode;
        $this->vacationStartDate = $setting->vacation_start_date ? $setting->vacation_start_date->format('Y-m-d') : '';
        $this->vacationEndDate = $setting->vacation_end_date ? $setting->vacation_end_date->format('Y-m-d') : '';
        $this->vacationDescription = (string) ($setting->vacation_description ?? '');

        $this->isSickMode = (bool) $setting->is_sick_mode;
        $this->sickDescription = (string) ($setting->sick_description ?? '');
    }

    private function refreshCapacityState(): void
    {
        $snap = app(ShopCapacityService::class)->evaluateCapacitySnapshot(false);
        $this->capacityAutopilotEnabled = $snap['autopilot_enabled'];
        $this->capacityPercentage = $snap['percentage'];
        $this->capacityLevel = $snap['level'];
    }

    #[Computed]
    public function deliveryTimes()
    {
        $order = array_keys(ShopCapacityService::CANONICAL_PROFILES);

        return DeliveryTime::all()->sortBy(static function (DeliveryTime $dt) use ($order): int {
            $idx = array_search($dt->name, $order, true);
            return $idx === false ? 99 : $idx;
        })->values();
    }

    #[Computed]
    public function feedbackLogs()
    {
        return DeliveryFeedback::latest()->get();
    }

    #[Computed]
    public function vacationWishesCount(): int
    {
        return DeliveryFeedback::where('type', 'vacation')->count();
    }

    #[Computed]
    public function sickWishesCount(): int
    {
        return DeliveryFeedback::where('type', 'sick')->count();
    }

    public function toggleCapacityAutopilot(): void
    {
        $this->assertAuthorized();

        $next = !ShopCapacityService::isAutopilotEnabled();
        ShopCapacityService::setAutopilotEnabled($next);

        $synced = app(ShopCapacityService::class)->syncAndApplyAutopilot();
        $this->capacityAutopilotEnabled = $synced['autopilot_enabled'];
        $this->capacityPercentage = $synced['percentage'];
        $this->capacityLevel = $synced['level'];

        $activeRow = DeliveryTime::where('is_active', true)->first();
        $this->activeDeliveryTimeId = $activeRow?->id;

        unset($this->deliveryTimes);

        if ($next) {
            session()->flash('success', "Kapazitäts-Autopilot aktiviert: Profil „{$synced['target_profile_name']}“ ({$synced['capped_percentage']} % Auslastung) ist im Shop aktiv.");
        } else {
            session()->flash('success', 'Kapazitäts-Autopilot deaktiviert: Du steuerst die aktive Lieferzeit jetzt manuell.');
        }
    }

    public function setActiveDeliveryTime($id): void
    {
        $this->assertAuthorized();

        $target = DeliveryTime::findOrFail($id);
        $wasAutopilot = ShopCapacityService::isAutopilotEnabled();

        DeliveryTime::query()->update(['is_active' => false]);
        $target->update(['is_active' => true]);
        $this->activeDeliveryTimeId = $target->id;

        // Wenn manuell ein abweichendes Profil gewählt wird, Autopilot transparent auf Manuell stellen,
        // damit der 5-Minuten-Cronjob die manuelle Auswahl nicht im Hintergrund überschreibt!
        $snap = app(ShopCapacityService::class)->evaluateCapacitySnapshot(false);
        if ($wasAutopilot && $target->name !== $snap['target_profile_name']) {
            ShopCapacityService::setAutopilotEnabled(false);
            $this->capacityAutopilotEnabled = false;
            unset($this->deliveryTimes);
            session()->flash('success', "Lieferzeit auf „{$target->name}“ gesetzt (Autopilot pausiert, damit deine manuelle Wahl bestehen bleibt).");
            return;
        }

        unset($this->deliveryTimes);
        session()->flash('success', "Standard-Lieferzeit „{$target->name}“ ist im Shop aktiv.");
    }

    public function updatedIsVacationMode($value): void
    {
        if ($value) {
            $this->isSickMode = false;
        }
        $this->saveSettings();
    }

    public function updatedIsSickMode($value): void
    {
        if ($value) {
            $this->isVacationMode = false;
        }
        $this->saveSettings();
    }

    public function saveSettings(): bool
    {
        $this->assertAuthorized();

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

        SystemSetting::updateOrCreate(
            ['key' => 'is_vacation_mode'],
            ['value' => $isVacation ? 'true' : 'false']
        );
        Cache::forget('global_shop_settings');

        // Wenn Urlaubs- und Krankheitsmodus beendet werden und Autopilot aktiv ist, sofort optimales Profil nachziehen!
        if (!$isVacation && !$isSick && ShopCapacityService::isAutopilotEnabled()) {
            app(ShopCapacityService::class)->syncAndApplyAutopilot();
            $activeRow = DeliveryTime::where('is_active', true)->first();
            $this->activeDeliveryTimeId = $activeRow?->id;
            unset($this->deliveryTimes);
        }

        $this->refreshCapacityState();
        session()->flash('success', 'Einstellungen gespeichert.');

        return true;
    }

    public function render()
    {
        return view('livewire.shop.system.system-delivery-times');
    }
}
