<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Log;

class AIFunctionsRegistry
{
    use Functions\DashboardFunctions;
    use Functions\ShopFunctions;
    use Functions\OrderFunctions;
    use Functions\AccountingFunctions;
    use Functions\SettingsFunctions;
    use Functions\CoreFunctions;
    use Functions\CalendarFunctions;
    use Functions\MarketingFunctions;
    use Functions\AgentFunctions;

    /**
     * Define all available functions the AI can call.
     * This acts as the "Remote Control" schema.
     */
    public static function getFunctions(): array
    {
        return array_merge(
            self::getDashboardFunctionsSchema(),
            self::getShopFunctionsSchema(),
            self::getOrderFunctionsSchema(),
            self::getAccountingFunctionsSchema(),
            self::getSettingsFunctionsSchema(),
            self::getCoreFunctionsSchema(),
            self::getCalendarFunctionsSchema(),
            self::getMarketingFunctionsSchema(),
            self::getAgentFunctionsSchema()
        );
    }

    /**
     * Return only the Schema (name, description, parameters) for the LLM.
     */
    public static function getSchema(): array
    {
        $functions = self::getFunctions();

        return array_map(function ($fn) {

            $props = $fn['parameters']['properties'];
            if (empty($props)) {
                $props = new \stdClass(); // Force {} instead of [] in JSON
            }

            // Allow bypassing required fields if empty
            $data = [
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

            if (isset($fn['parameters']['required'])) {
                $data['function']['parameters']['required'] = $fn['parameters']['required'];
            }

            return $data;
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

    /**
     * Parses the admin navigation blade file to automatically generate a map of routes and their human-readable names.
     */
    public static function getAdminNavigationMap(): array
    {
        return \Illuminate\Support\Facades\Cache::remember('ai_admin_nav_map', 3600, function () {
            $map = [];
            $path = resource_path('views/backend/admin/livewire/admin-navigation.blade.php');
            
            if (file_exists($path)) {
                $content = file_get_contents($path);
                
                // Extract <x-forms.list-item route="/admin/..." title="..." />
                if (preg_match_all('/<x-forms\.list-item[^>]+>/i', $content, $matches)) {
                    foreach ($matches[0] as $tag) {
                        preg_match('/route="([^"]+)"/i', $tag, $routeMatch);
                        preg_match('/title="([^"]+)"/i', $tag, $titleMatch);
                        
                        if (!empty($routeMatch[1]) && !empty($titleMatch[1])) {
                            $map[$routeMatch[1]] = trim($titleMatch[1]);
                        }
                    }
                }
                
                // Extract special manually built <a> tags (like Tickets)
                if (preg_match_all('/<a\s+href="(\/admin\/[^"]+)"[^>]*>.*?<span[^>]*>(.*?)<\/span>/is', $content, $aMatches)) {
                    foreach ($aMatches[1] as $index => $route) {
                        $title = trim(strip_tags($aMatches[2][$index]));
                        if (!empty($title)) {
                            $map[$route] = $title;
                        }
                    }
                }
            }
            
            // Minimalst-Fallback falls Parser fehlschlägt
            if (empty($map)) {
                $map['/admin/dashboard'] = 'Dashboard / Startseite';
            }
            
            return $map;
        });
    }
}
