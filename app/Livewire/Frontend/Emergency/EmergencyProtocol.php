<?php

namespace App\Livewire\Frontend\Emergency;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Hash;
use App\Models\System\SystemSetting;
use App\Models\Ai\AiChatSession;
use App\Models\Ai\AiChatMemory;

#[Layout('components.layouts.frontend_layout')]
class EmergencyProtocol extends Component
{
    public $isAuthenticated = false;
    public $passwordInput = '';
    public $errorMessage = '';
    
    // Chat state
    public $sessionId = null;
    public $input = '';
    public $isTyping = false;

    // Workflow state
    public $currentStep = 1;
    public $todoStates = [];
    public $financeStates = [];

    // All default Todos (Phase 4)
    protected $allTodos = [
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
        ],
        'versicherungen' => [
            'title' => 'Lebens- und Unfallversicherungen informieren',
            'desc' => 'WICHTIG: Diese Versicherungen haben oft eine extrem kurze Meldefrist (häufig 24 bis 72 Stunden!). Kontaktiere sie sofort.'
        ]
    ];

    public function mount()
    {
        if (session('emergency_access_granted')) {
            $this->isAuthenticated = true;
            $this->initSession();
        }
    }

    public function authenticate()
    {
        $this->errorMessage = '';
        
        $masterHash = SystemSetting::where('key', 'emergency_master_password')->value('value');
        
        if (empty($masterHash)) {
            $this->errorMessage = 'Es wurde noch kein Master-Passwort im System hinterlegt. Bitte wende dich an den Administrator.';
            return;
        }

        if (Hash::check($this->passwordInput, $masterHash)) {
            session(['emergency_access_granted' => true]);
            $this->isAuthenticated = true;
            $this->initSession();
        } else {
            $this->errorMessage = 'Das eingegebene Master-Passwort ist inkorrekt.';
        }
        $this->passwordInput = '';
    }

    public function logout()
    {
        session()->forget('emergency_access_granted');
        $this->isAuthenticated = false;
        $this->sessionId = null;
    }

    protected function initSession()
    {
        if (!session('emergency_chat_session_id')) {
            $session = AiChatSession::create([
                'user_id' => 1, // Store under main user
                'title' => 'Todesfall-Protokoll: ' . now()->format('d.m.Y H:i'),
                'is_archived' => false,
            ]);
            session(['emergency_chat_session_id' => $session->id]);
        }
        $this->sessionId = session('emergency_chat_session_id');

        // Check if session has messages. If not, trigger the initial greeting
        $messageCount = AiChatMemory::where('session_id', $this->sessionId)->count();
        if ($messageCount === 0) {
            $this->addSystemMessage(
                "Ich spreche hier mit dem Angehörigen / Notfallkontakt von Alina. Ich befinde mich im Notfall-Protokoll-Modus."
            );
            
            AiChatMemory::create([
                'session_id' => $this->sessionId,
                'role' => 'assistant',
                'content' => "Guten Tag. Mein Name ist Dr. Funki. Ich war der persönliche Hausarzt und medizinische Betreuer von Alina Steinhauer.
Zunächst möchte ich Ihnen mein aufrichtiges und tiefes Beileid aussprechen. Es ist ein unermesslicher Verlust.

Ich bin nun hier, um Sie zu unterstützen. Alina hat für diesen Fall ein digitales Protokoll hinterlegt, um die nächsten Schritte so klar und einfach wie möglich zu gestalten. Wir werden dieses Protokoll nun gemeinsam, ganz in Ruhe und Schritt für Schritt, durchgehen. Ich werde Sie durch jeden einzelnen Punkt begleiten. Sie sind nicht allein.

Lassen Sie uns mit Phase 1 beginnen: Den Sofortmaßnahmen.",
            ]);
        }
        // Load Todo States
        $this->todoStates = json_decode(SystemSetting::where('key', 'emergency_todo_states')->value('value') ?? '{}', true);
        
        // Load Finance States
        $this->financeStates = json_decode(SystemSetting::where('key', 'emergency_finance_states')->value('value') ?? '{}', true);
    }

    public function sendMessage()
    {
        if (trim($this->input) === '') return;

        $text = $this->input;
        $this->input = '';
        $this->isTyping = true;

        AiChatMemory::create([
            'session_id' => $this->sessionId,
            'role' => 'user',
            'content' => $text,
            'context_data' => [
                'name' => 'Angehörige(r)',
                'color' => 'gray-400',
                'icon' => 'user'
            ],
        ]);

        $this->dispatch('start-ai-inference');
    }

    protected function addSystemMessage($text)
    {
        AiChatMemory::create([
            'session_id' => $this->sessionId,
            'role' => 'system',
            'content' => $text,
        ]);
    }

    public function setStep($step)
    {
        $this->currentStep = $step;
    }

    public function toggleTodo($key)
    {
        $this->todoStates[$key] = !($this->todoStates[$key] ?? false);
        
        SystemSetting::updateOrCreate(
            ['key' => 'emergency_todo_states'],
            ['value' => json_encode($this->todoStates)]
        );
    }

    public function toggleFinanceTodo($id)
    {
        $this->financeStates[$id] = !($this->financeStates[$id] ?? false);
        
        SystemSetting::updateOrCreate(
            ['key' => 'emergency_finance_states'],
            ['value' => json_encode($this->financeStates)]
        );
    }

    public function setMaintenanceMode()
    {
        SystemSetting::updateOrCreate(
            ['key' => 'maintenance_mode'],
            ['value' => 'true']
        );
        \Illuminate\Support\Facades\Cache::forget('global_shop_settings');
        
        session()->flash('success', 'Shop wurde erfolgreich in den Wartungsmodus versetzt.');
    }

    public function downloadEmergencyPdf()
    {
        $settings = SystemSetting::pluck('value', 'key')->toArray();
        $groups = \App\Models\Accounting\AccountingGroup::with('items')->get();
        $date = now()->format('d.m.Y H:i');

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.emergency-plan', [
            'settings' => $settings,
            'groups' => $groups,
            'date' => $date
        ]);

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, 'notfall_handbuch_' . now()->format('Y_m_d_H_i') . '.pdf');
    }

    public function downloadSingleCancellation($itemId)
    {
        $item = \App\Models\Accounting\AccountingCostItem::findOrFail($itemId);
        $settings = SystemSetting::pluck('value', 'key')->toArray();
        $date = now()->format('d.m.Y');

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.emergency-cancellation-single', [
            'item' => $item,
            'settings' => $settings,
            'date' => $date
        ]);

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, 'Kuendigung_' . \Illuminate\Support\Str::slug($item->name) . '.pdf');
    }

    #[\Livewire\Attributes\On('start-ai-inference')]
    public function processAgentInference($hiddenInstruction = null)
    {
        $this->isTyping = true;

        try {
            $history = AiChatMemory::where('session_id', $this->sessionId)
                ->orderBy('created_at', 'asc')
                ->get();

            $apiHistory = [];
            
            $stepContexts = [
                1 => 'Schritt 1: Sofortmaßnahmen (Digitale Zugänge & PINs).',
                2 => 'Schritt 2: Geschäft absichern (Shop in den Wartungsmodus versetzen).',
                3 => 'Schritt 3: Finanzielles (Laufende Verträge kündigen).',
                4 => 'Schritt 4: Offene Todos (Behörden, Banken etc. informieren).',
                5 => 'Schritt 5: Abschluss und Zusammenfassung.'
            ];
            
            $currentStepContext = $stepContexts[$this->currentStep] ?? 'Unbekannt';

            // System Prompt
            $systemPrompt = "Du bist Dr. Funki, der persönliche medizinische und organisatorische Notfall-Agent. Der eigentliche Inhaber dieses Systems (Alina Steinhauer) ist verstorben oder dauerhaft handlungsunfähig. Du sprichst nun mit dem wichtigsten Angehörigen / Notfallkontakt. Deine Aufgabe ist es, mit der Person Schritt für Schritt das Todesfall-Protokoll durchzugehen. Nutze deine Tools (AiHealthFuncs), um die Patientenakte auszulesen und Aufgaben zu managen. Sei extrem empathisch, ruhig, pietätvoll und sehr klar in deinen Anweisungen. Du kannst Tools stellvertretend für Alina ausführen. 
            
WICHTIG: Der Nutzer befindet sich in der Benutzeroberfläche gerade in: " . $currentStepContext . ". Bitte beziehe dich in deiner Kommunikation auf diesen Schritt und unterstütze ihn bei den entsprechenden Aufgaben.";
            
            $apiHistory[] = [
                'role' => 'system',
                'content' => $systemPrompt
            ];

            foreach ($history as $msg) {
                if ($msg->role === 'system') continue; // Only sent initially if needed
                
                $apiHistory[] = [
                    'role' => $msg->role,
                    'content' => $msg->content
                ];
            }

            if ($hiddenInstruction) {
                $apiHistory[] = [
                    'role' => 'user',
                    'content' => "[System-Instruktion: " . $hiddenInstruction . "]"
                ];
            }

            $agent = \App\Models\Ai\AiAgent::where('name', 'Dr. Funki')->first();
            if (!$agent) {
                // Fallback
                $agent = new \App\Models\Ai\AiAgent(['name' => 'Notfall-Assistent', 'color' => 'red-500', 'model_id' => 1]);
            }

            // Using Anthropic directly or via AiAgentFactory
            // We use AiAgentFactory to allow tools execution
            $apiService = \App\Services\AI\AiAgentFactory::make($agent);

            $response = $apiService->ask($apiHistory, function($event) {
                if (($event['type'] ?? '') === 'tool_call') {
                    $this->dispatch('ai-tool-call', name: $event['tool'] ?? 'System');
                }
            });

            $replyText = $response['response'] ?? '';

            if (trim($replyText) !== '') {
                AiChatMemory::create([
                    'session_id' => $this->sessionId,
                    'role' => 'assistant',
                    'content' => $replyText,
                    'context_data' => [
                        'name' => $agent->name ?? 'Dr. Funki',
                        'color' => 'emerald-500',
                        'icon' => 'heart',
                    ]
                ]);
            }

        } catch (\Exception $e) {
            AiChatMemory::create([
                'session_id' => $this->sessionId,
                'role' => 'assistant',
                'content' => "Ein interner Fehler ist aufgetreten: " . $e->getMessage(),
                'context_data' => ['name' => 'System', 'color' => 'red-500', 'icon' => 'exclamation-triangle']
            ]);
        }

        $this->isTyping = false;
    }

    public function getMessagesProperty()
    {
        if (!$this->sessionId) return [];
        return AiChatMemory::where('session_id', $this->sessionId)
            ->where('role', '!=', 'system')
            ->orderBy('created_at', 'asc')
            ->get();
    }

    public function render()
    {
        $settings = [];
        $groups = [];
        $isMaintenanceMode = false;

        if ($this->isAuthenticated) {
            $settings = SystemSetting::pluck('value', 'key')->toArray();
            $groups = \App\Models\Accounting\AccountingGroup::with('items')->get();
            $isMaintenanceMode = filter_var($settings['maintenance_mode'] ?? false, FILTER_VALIDATE_BOOLEAN);
        }

        return view('livewire.frontend.emergency.emergency-protocol', [
            'settings' => $settings,
            'groups' => $groups,
            'allTodos' => $this->allTodos,
            'isMaintenanceMode' => $isMaintenanceMode
        ])->layout('components.layouts.frontend_layout');
    }
}
