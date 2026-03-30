<?php

namespace App\Services\AI\Functions;

use Illuminate\Support\Facades\Artisan;
use App\Services\AnalyticsService;

trait AiMasterFuncs
{
    public static function getAiMasterFuncsSchema(): array
    {
        return [
            [
                'name' => 'master_get_core_kpis',
                'description' => 'Holt die zentralen Unternehmens-KPIs (Umsatz, Gewinn, Fixkosten, Marge) für einen bestimmten Zeitraum frisch aus der Datenbank. Nutze dies, um strategische Fragen des CEOs zur Rentabilität zu beantworten.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'timeframe' => [
                            'type' => 'string',
                            'description' => 'Der Zeitraum: "this_month", "last_month", "this_year", "last_year" (Standard: "this_month")',
                            'enum' => ['this_month', 'last_month', 'this_year', 'last_year']
                        ]
                    ],
                ],
                'callable' => [self::class, 'executeGetCoreKpis']
            ],
            [
                'name' => 'master_get_operational_health',
                'description' => 'Scant sofort alle Abteilungen (Support, Buchhaltung, Bestellungen) auf offene Todos (z.B. offene Tickets, unversendete Bestellungen, unassoziierte Bank-Transaktionen).',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                ],
                'callable' => [self::class, 'executeGetOperationalHealth']
            ],
            [
                'name' => 'master_toggle_shop_maintenance',
                'description' => 'Setzt den gesamten Seelenfunke Front-Shop in den Wartungsmodus (Offline) oder schaltet ihn wieder online. ACHTUNG: Nur im absoluten Notfall verwenden!',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'action' => [
                            'type' => 'string',
                            'description' => 'Was soll getan werden? "down" = Shop offline nehmen, "up" = Shop online stellen.',
                            'enum' => ['down', 'up']
                        ],
                        'secret_passphrase' => [
                            'type' => 'string',
                            'description' => 'Muss exakt "CONFIRM_CEO_OVERRIDE" lauten, sonst wird der Befehl ignoriert.'
                        ]
                    ],
                    'required' => ['action', 'secret_passphrase']
                ],
                'callable' => [self::class, 'executeToggleMaintenance']
            ],
        ];
    }

    public static function executeGetCoreKpis(array $args)
    {
        try {
            $timeframe = $args['timeframe'] ?? 'this_month';
            
            $start = match($timeframe) {
                'last_month' => now()->subMonth()->startOfMonth()->format('Y-m-d'),
                'this_year' => now()->startOfYear()->format('Y-m-d'),
                'last_year' => now()->subYear()->startOfYear()->format('Y-m-d'),
                default => now()->startOfMonth()->format('Y-m-d'),
            };
            
            $end = match($timeframe) {
                'last_month' => now()->subMonth()->endOfMonth()->format('Y-m-d'),
                'this_year' => now()->endOfYear()->format('Y-m-d'),
                'last_year' => now()->subYear()->endOfYear()->format('Y-m-d'),
                default => now()->endOfMonth()->format('Y-m-d'),
            };
            
            $service = app(AnalyticsService::class);
            $stats = $service->getStats($start, $end, 'all', collect()); 
            
            return [
                'status' => 'success',
                'timeframe' => "$start bis $end",
                'summary' => $stats['summary'] ?? []
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Laden der KPIs: ' . $e->getMessage()];
        }
    }

    public static function executeGetOperationalHealth(array $args)
    {
        try {
            $issues = [];
            
            if (class_exists(\App\Models\Support\SupportTicket::class)) {
                $openTickets = \App\Models\Support\SupportTicket::where('status', 'open')->count();
                if ($openTickets > 0) $issues[] = "- Support: $openTickets offene Tickets";
            }
            
            if (class_exists(\App\Models\Order\OrderOrder::class)) {
                $openOrders = \App\Models\Order\OrderOrder::whereIn('status', ['pending', 'processing'])->count();
                if ($openOrders > 0) $issues[] = "- Logistik: $openOrders offene / unversendete Bestellungen";
            }
            
            if (class_exists(\App\Models\Accounting\AccountingBankTransaction::class)) {
                $unassignedTx = \App\Models\Accounting\AccountingBankTransaction::whereHas('account', function($q) {
                    $q->where('is_active_for_analysis', true);
                })->whereNull('assigned_by_type')->count();
                if ($unassignedTx > 0) $issues[] = "- Buchhaltung: $unassignedTx unassoziierte Bank-Umsätze";
            }

            if (class_exists(\App\Models\Management\ManagementTask::class)) {
                $openTasks = \App\Models\Management\ManagementTask::where('is_completed', false)->count();
                if ($openTasks > 0) $issues[] = "- Leitung: $openTasks offene Todos für den CEO";
            }
            
            if (empty($issues)) {
                return ['status' => 'success', 'message' => 'Hervorragend. Alle operativen Abteilungen sind auf Null (keine offenen Tickets, keine unversendeten Bestellungen).'];
            }
            
            return [
                'status' => 'warning',
                'message' => "Es gibt operative Rückstände in den Abteilungen:\n" . implode("\n", $issues)
            ];
        } catch (\Exception $e) {
             return ['status' => 'error', 'message' => 'Fehler beim Abrufen der operativen Abteilungen: ' . $e->getMessage()];
        }
    }

    public static function executeToggleMaintenance(array $args)
    {
        $action = $args['action'] ?? '';
        $pass = $args['secret_passphrase'] ?? '';
        
        if ($pass !== 'CONFIRM_CEO_OVERRIDE') {
            return ['status' => 'error', 'message' => 'Autorisierung fehlgeschlagen. Ungültige Passphrase.'];
        }
        
        try {
            if ($action === 'down') {
                Artisan::call('down', ['--secret' => 'funkira-maintenance']);
                return ['status' => 'success', 'message' => 'Der Store wurde erfolgreich in den Wartungsmodus versetzt. (Bypass-Url: /funkira-maintenance)'];
            } else {
                Artisan::call('up');
                return ['status' => 'success', 'message' => 'Der Store wurde wieder LIVE geschaltet.'];
            }
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Ausführen der Store-Kontrolle: ' . $e->getMessage()];
        }
    }
}
