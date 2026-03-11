<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Log;
use App\Models\Expense; // Assuming you have this
use App\Models\Order;   // Assuming you have this
use App\Livewire\Global\Widgets\FunkiAnalytics;

class AIFunctionsRegistry
{
    /**
     * Define all available functions the AI can call.
     * This acts as the "Remote Control" schema.
     */
    public static function getFunctions(): array
    {
        return [
            [
                'name' => 'check_missing_expenses',
                'description' => 'Checks if there are missing Sonderausgaben (special expenses) that need to be recorded or reviewed.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [],
                ],
                'callable' => [self::class, 'executeCheckMissingExpenses']
            ],
            [
                'name' => 'get_next_order_deadline',
                'description' => 'Returns the date and time when the next pending or open order must be finished or shipped.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [],
                ],
                'callable' => [self::class, 'executeGetNextOrderDeadline']
            ],
            [
                'name' => 'get_system_health',
                'description' => 'Returns the overall system status, active sessions, and health metrics. Useful to determine if the system is running smoothly.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [],
                ],
                'callable' => [self::class, 'executeGetSystemHealth']
            ],
        ];
    }

    /**
     * Return only the Schema (name, description, parameters) for the LLM.
     */
    public static function getSchema(): array
    {
        $functions = self::getFunctions();
        
        // Transform internal representation to LLM JSON Schema format
        return array_map(function ($fn) {
            
            $props = $fn['parameters']['properties'];
            if (empty($props)) {
                $props = new \stdClass(); // Force {} instead of [] in JSON
            }
            
            return [
                'type' => 'function',
                'function' => [
                    'name' => $fn['name'],
                    'description' => $fn['description'],
                    'parameters' => [
                        'type' => $fn['parameters']['type'],
                        'properties' => $props
                    ]
                ]
            ];
        }, $functions);
    }

    /**
     * Executes a function by name if it exists in the registry.
     */
    public static function execute(string $name, array $args = [])
    {
        $functions = collect(self::getFunctions())->keyBy('name');

        if (!$functions->has($name)) {
            throw new \InvalidArgumentException("Function '{$name}' is not registered in the AI Remote Control.");
        }

        $callable = $functions[$name]['callable'];

        if (!is_callable($callable)) {
            throw new \RuntimeException("Callable for function '{$name}' is invalid.");
        }

        try {
            return call_user_func($callable, $args);
        } catch (\Exception $e) {
            Log::error("AI Function Execution Error: " . $e->getMessage());
            return [
                'error' => true,
                'message' => 'Error executing function: ' . $e->getMessage()
            ];
        }
    }

    // ==========================================
    // ACTUAL IMPLEMENTATIONS (The Controller Logic)
    // ==========================================

    public static function executeCheckMissingExpenses(array $args)
    {
        // Example implementation - adjust based on actual DB structure
        // Here we might check some App\Models\Expense where status is missing etc.
        // For demonstration, returning a mock intelligent response
        return [
            'status' => 'success',
            'has_missing_expenses' => false,
            'message' => 'Aktuell sind alle erfassten Sonderausgaben verbucht. Es fehlen keine Belege im System.'
        ];
    }

    public static function executeGetNextOrderDeadline(array $args)
    {
        // Example implementation checking orders
        // Order::where('status', 'processing')->orderBy('deadline', 'asc')->first();
        return [
            'status' => 'success',
            'next_deadline' => now()->addDays(2)->format('Y-m-d H:i:s'),
            'type' => 'Express-Versand',
            'message' => 'Die nächste Bestellung muss übermorgen fertiggestellt werden.'
        ];
    }

    public static function executeGetSystemHealth(array $args)
    {
        // Utilize the existing FunkiAnalytics class to give the AI real info
        try {
            $analytics = new FunkiAnalytics();
            $analytics->checkSystemHealth();
            $isHealthy = $analytics->isSystemHealthy();
            
            // We need to set up minimal state for the component to load stats
            $analytics->dateStart = now()->startOfMonth()->format('Y-m-d');
            $analytics->dateEnd = now()->endOfMonth()->format('Y-m-d');
            $analytics->filterType = 'all';
            
            $service = app(\App\Services\FunkiAnalyticsService::class);
            $analytics->loadStats($service);
            $stats = $analytics->stats;

            return [
                'status' => 'success',
                'is_healthy' => $isHealthy,
                'active_sessions' => $stats['summary']['active_sessions'] ?? 0,
                'avg_profit' => $stats['summary']['avg_profit'] ?? 0,
                'total_orders' => $stats['summary']['total_orders'] ?? 0,
                'message' => $isHealthy ? 'Das System läuft einwandfrei.' : 'Es gibt Systemwarnungen.'
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Konnte Systemstatus nicht abrufen: ' . $e->getMessage()
            ];
        }
    }
}
