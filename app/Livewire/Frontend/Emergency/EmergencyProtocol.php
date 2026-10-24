<?php

namespace App\Livewire\Frontend\Emergency;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use App\Models\System\SystemSetting;
use App\Models\System\SystemBlockedIp;
use App\Services\System\SystemSecurityService;

#[Layout('components.layouts.frontend_layout')]
class EmergencyProtocol extends Component
{
    #[Locked]
    public $isAuthenticated = false;
    public $passwordInput = '';
    public $errorMessage = '';

    // Workflow state
    public $currentStep = 1;
    #[Locked]
    public $todoStates = [];
    #[Locked]
    public $financeStates = [];

    protected $allTodos = [
        'versicherungen' => [
            'title' => 'Lebens- und Unfallversicherungen informieren',
            'desc' => 'WICHTIG: Diese Versicherungen haben oft eine extrem kurze Meldefrist (häufig 24 bis 72 Stunden!). Kontaktiere sie sofort.'
        ],
        'sterbeurkunde' => [
            'title' => 'Sterbeurkunde beantragen',
            'desc' => 'Gehe zum Standesamt des Sterbeortes (oft übernimmt dies auch der Bestatter). Du benötigst mehrere Originale für Kündigungen, Banken und das Nachlassgericht.'
        ],
        'banken' => [
            'title' => 'Banken informieren',
            'desc' => 'Informiere alle bekannten Banken. Achtung: Oft werden Konten gesperrt, bis ein Erbschein vorliegt, Daueraufträge laufen aber meist weiter.'
        ],
        'ruhe' => [
            'title' => 'Shop & Gewerbe pausieren (Wartungsmodus)',
            'desc' => 'Aktiviere im Schritt 2 ("Geschäft absichern") den Wartungsmodus, damit keine neuen Bestellungen mehr eingehen, die nicht mehr bearbeitet werden können.'
        ],
        'erstattungen' => [
            'title' => 'Rückerstattungen offener Bestellungen anstoßen',
            'desc' => 'Prüfe im Shop-System oder bei PayPal/Stripe, ob noch unversendete Bestellungen offen sind, und erstatte diese zurück.'
        ],
        'steuer' => [
            'title' => 'Steuerberater kontaktieren',
            'desc' => 'Eine Betriebsaufgabe oder Übergabe zieht sofortige steuerliche Fristen nach sich. Der Steuerberater (siehe Schritt 5) muss zwingend informiert werden.'
        ],
        'social' => [
            'title' => 'Social Media Accounts (Gedenkzustand / Löschen)',
            'desc' => 'Setze Accounts (Instagram, Facebook etc.) in den Gedenkzustand oder lösche sie. Ggf. ein letztes Posting absetzen, um Kunden zu informieren.'
        ],
        'email' => [
            'title' => 'E-Mail Auto-Responder einrichten',
            'desc' => 'Richte im E-Mail-Postfach eine automatische Abwesenheitsnotiz ein: "Der Geschäftsbetrieb ruht aktuell aufgrund eines Trauerfalls."'
        ]
    ];

    public function mount()
    {
        if (SystemBlockedIp::isBlocked(request()->ip())) {
            abort(403, 'Zugriff aufgrund wiederholter Sicherheitsverletzungen verweigert.');
        }

        if (session('emergency_access_granted') === true) {
            $this->isAuthenticated = true;
            $this->todoStates = json_decode(SystemSetting::where('key', 'emergency_todo_states')->value('value') ?? '{}', true);
            $this->financeStates = json_decode(SystemSetting::where('key', 'emergency_finance_states')->value('value') ?? '{}', true);
        }
    }

    public function authenticate()
    {
        $this->errorMessage = '';
        $ip = request()->ip();

        if (SystemBlockedIp::isBlocked($ip)) {
            abort(403, 'Zugriff verweigert.');
        }

        if (empty(trim($this->passwordInput)) || strlen($this->passwordInput) > 128) {
            $this->errorMessage = 'Bitte gib ein gültiges Master-Passwort ein.';
            return;
        }

        $throttleKey = 'emergency_auth_' . $ip;
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            $minutes = ceil($seconds / 60);

            // Automatische IP-Sperre für 24 Stunden bei fortgesetzten Brute-Force-Versuchen
            SystemBlockedIp::blockIp($ip, 'Brute-Force-Schutz: Zu viele Fehlversuche am Notfall-Protokoll', 24, 'Notfall-Monitor');
            SystemSecurityService::logEvent(
                'security:emergency_auth_failed',
                'Notfall-Protokoll Brute-Force blockiert',
                "IP {$ip} wurde nach zu vielen Master-Passwort-Fehlversuchen am Notfall-Protokoll für 24h gesperrt.",
                'error',
                ['ip_address' => $ip]
            );

            $this->errorMessage = "Zu viele Fehlversuche. Dieser Bereich wurde aus Sicherheitsgründen für {$minutes} Minute(n) gesperrt.";
            return;
        }
        
        $masterHash = SystemSetting::where('key', 'emergency_master_password')->value('value');
        
        if (empty($masterHash)) {
            $this->errorMessage = 'Es wurde noch kein Master-Passwort im System hinterlegt. Bitte wende dich an den Administrator.';
            return;
        }

        if (Hash::check($this->passwordInput, $masterHash)) {
            RateLimiter::clear($throttleKey);
            session()->regenerate();
            session(['emergency_access_granted' => true]);
            $this->isAuthenticated = true;
            $this->todoStates = json_decode(SystemSetting::where('key', 'emergency_todo_states')->value('value') ?? '{}', true);
            $this->financeStates = json_decode(SystemSetting::where('key', 'emergency_finance_states')->value('value') ?? '{}', true);
        } else {
            RateLimiter::hit($throttleKey, 900); // 15 Minuten Throttling-Fenster
            $remaining = RateLimiter::remaining($throttleKey, 5);
            SystemSecurityService::logEvent(
                'security:emergency_auth_failed',
                'Fehlversuch am Notfall-Protokoll',
                "Inkorrektes Master-Passwort am Notfall-Protokoll von IP {$ip} (Verbleibend: {$remaining}).",
                'warning',
                ['ip_address' => $ip, 'remaining_attempts' => $remaining]
            );
            $this->errorMessage = "Das eingegebene Master-Passwort ist inkorrekt. (Verbleibende Versuche: {$remaining})";
        }
        $this->passwordInput = '';
    }

    public function logout()
    {
        session()->forget('emergency_access_granted');
        $this->isAuthenticated = false;
    }

    protected function ensureAuthenticated(): void
    {
        if (!$this->isAuthenticated || session('emergency_access_granted') !== true) {
            abort(403, 'Zugriff verweigert.');
        }
    }

    public function setStep($step)
    {
        $this->ensureAuthenticated();
        $this->currentStep = min(5, max(1, (int)$step));
    }

    public function toggleTodo($key)
    {
        $this->ensureAuthenticated();
        if (!is_string($key) || !array_key_exists($key, $this->allTodos)) {
            return;
        }

        $this->todoStates[$key] = !($this->todoStates[$key] ?? false);
        
        SystemSetting::updateOrCreate(
            ['key' => 'emergency_todo_states'],
            ['value' => json_encode($this->todoStates)]
        );
    }

    public function toggleFinanceTodo($id)
    {
        $this->ensureAuthenticated();
        if (!\App\Models\Accounting\AccountingCostItem::where('id', $id)->exists()) {
            return;
        }

        $this->financeStates[$id] = !($this->financeStates[$id] ?? false);
        
        SystemSetting::updateOrCreate(
            ['key' => 'emergency_finance_states'],
            ['value' => json_encode($this->financeStates)]
        );
    }

    public function setMaintenanceMode()
    {
        $this->ensureAuthenticated();
        SystemSetting::updateOrCreate(
            ['key' => 'maintenance_mode'],
            ['value' => 'true']
        );
        \Illuminate\Support\Facades\Cache::forget('global_shop_settings');
        
        session()->flash('success', 'Shop wurde erfolgreich in den Wartungsmodus versetzt.');
    }

    protected function getSafeEmergencySettings(): array
    {
        $settings = SystemSetting::pluck('value', 'key')->toArray();
        foreach (array_keys($settings) as $key) {
            if (preg_match('/(password|secret|token|key|pin|hash|credential)/i', (string) $key)) {
                unset($settings[$key]);
            }
        }
        return $settings;
    }

    public function downloadEmergencyPdf()
    {
        $this->ensureAuthenticated();
        $settings = $this->getSafeEmergencySettings();
        $groups = \App\Models\Accounting\AccountingGroup::with('items')->get();
        $date = now()->format('d.m.Y H:i');

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.emergency-plan', [
            'settings' => $settings,
            'groups' => $groups,
            'date' => $date
        ])->setOptions(['isRemoteEnabled' => false]);

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, 'notfall_handbuch_' . now()->format('Y_m_d_H_i') . '.pdf');
    }

    public function downloadSingleCancellation($itemId)
    {
        $this->ensureAuthenticated();
        $item = \App\Models\Accounting\AccountingCostItem::findOrFail($itemId);
        $settings = $this->getSafeEmergencySettings();
        $date = now()->format('d.m.Y');

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.emergency-cancellation-single', [
            'item' => $item,
            'settings' => $settings,
            'date' => $date
        ])->setOptions(['isRemoteEnabled' => false]);

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, 'Kuendigung_' . \Illuminate\Support\Str::slug($item->name) . '.pdf');
    }



    public function render()
    {
        $settings = [];
        $groups = [];
        $openOrders = collect();
        $isMaintenanceMode = false;

        if ($this->isAuthenticated && session('emergency_access_granted') === true) {
            $settings = $this->getSafeEmergencySettings();
            $groups = \App\Models\Accounting\AccountingGroup::with('items')->get();
            $isMaintenanceMode = filter_var($settings['maintenance_mode'] ?? false, FILTER_VALIDATE_BOOLEAN);
            
            // Fetch open orders
            $openOrders = \App\Models\Order\OrderOrder::whereIn('status', ['pending', 'processing'])
                ->orderBy('created_at', 'desc')
                ->get();
        }

        return view('livewire.frontend.emergency.emergency-protocol', [
            'settings' => $settings,
            'groups' => $groups,
            'openOrders' => $openOrders,
            'allTodos' => $this->allTodos,
            'isMaintenanceMode' => $isMaintenanceMode
        ])->layout('components.layouts.frontend_layout');
    }
}
