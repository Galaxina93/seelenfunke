<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Log;

class AIFunctionsRegistry
{
    use Functions\AiSystemFuncs;
    use Functions\AiSupportFuncs;
    use Functions\AiMarketingFuncs;
    use Functions\AiMarketingNewsletterFuncs;
    use Functions\AiMarketingVoucherFuncs;
    use Functions\AiMarketingBlogFuncs;
    use Functions\AiSalesFuncs;
    use Functions\AiFinanceFuncs;
    use Functions\AiScoutFuncs;
    use Functions\AiHealthFuncs;
    use Functions\AiTaskFuncs;
    use Functions\AiRoutineFuncs;
    use Functions\AiCalendarFuncs;
    use Functions\AiBrainFuncs;
    use Functions\AiMailFuncs;
    use Functions\AiContactFuncs;
    use Functions\AiProductAnalyticsFuncs;
    use Functions\AiProductFractureFuncs;
    use Functions\AiProductCreateFuncs;
    use Functions\AiSuppliersFuncs;
    use Functions\AiProductTemplatesFuncs;
    use Functions\AiProductControlReviewsFuncs;
    use Functions\AiProductNicheScannerFuncs;
    use Functions\AiProductPackagingConfiguratorFuncs;
    use Functions\AiAgentsFuncs;
    use Functions\AiMasterFuncs;
    use Functions\AiTelefonyFuncs;

    /**
     * Optional static context merged into all executed function arguments.
     * Useful for standalone scripts that use AiAgentFactory but need hidden system parameters.
     */
    protected static array $globalContext = [];

    public static function setGlobalContext(array $context): void
    {
        self::$globalContext = $context;
    }

    /**
     * Define all available functions the AI can call.
     * This acts as the "Remote Control" schema.
     */
    public static function getFunctions(): array
    {
        return array_merge(
            self::getAiSystemFuncsSchema(),
            self::getAiSupportFuncsSchema(),
            self::getAiMarketingFuncsSchema(),
            self::getAiMarketingNewsletterFuncsSchema(),
            self::getAiMarketingVoucherFuncsSchema(),
            self::getAiMarketingBlogFuncsSchema(),
            self::getAiSalesFuncsSchema(),
            self::getAiFinanceFuncsSchema(),
            self::getAiScoutFuncsSchema(),
            self::getAiHealthFuncsSchema(),
            self::getAiTaskFuncsSchema(),
            self::getAiRoutineFuncsSchema(),
            self::getAiCalendarFuncsSchema(),
            self::getAiBrainFuncsSchema(),
            self::getAiMailFuncsSchema(),
            self::getAiContactFuncsSchema(),
            self::getAiProductAnalyticsFuncsSchema(),
            self::getAiProductFractureFuncsSchema(),
            self::getAiProductCreateFuncsSchema(),
            self::getAiSuppliersFuncsSchema(),
            self::getAiProductTemplatesFuncsSchema(),
            self::getAiProductControlReviewsFuncsSchema(),
            self::getAiProductNicheScannerFuncsSchema(),
            self::getAiProductPackagingConfiguratorFuncsSchema(),
            self::getAiAgentsFuncsSchema(),
            self::getAiMasterFuncsSchema(),
            self::getAiTelefonyFuncsSchema()
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
            return [
                'error' => true,
                'status' => 'error',
                'message' => "Function '{$name}' is not registered or does not exist. Please pick a VALID tool from the schema."
            ];
        }

        $callable = $functions[$name]['callable'];

        if (!is_callable($callable)) {
            throw new \RuntimeException("Callable for function '{$name}' is invalid.");
        }

        // --- ANTIGRAVITY GUARDRAIL ENFORCER ---
        $destructiveTools = [
            'system_multi_replace_file', 
            'system_edit_file', 
            'system_write_to_file',
            'system_run_command'
        ];

        if (in_array($name, $destructiveTools)) {
            $hasPlan = session()->get('has_ai_implementation_plan', false);
            if (!$hasPlan) {
                return [
                    'status' => 'error',
                    'message' => "SYSTEM GUARDRAIL BLOCK: You are attempting to run a destructive/modifying tool ('{$name}') without having written an implementation plan first! RULE: You MUST execute 'system_write_artifact' with 'artifact_name' = 'implementation_plan' to outline your changes before you are allowed to mutate state!"
                ];
            }
        }

        try {
            $mergedArgs = array_merge($args, self::$globalContext); // Inject invisible backend parameters 
            return call_user_func($callable, $mergedArgs);
        } catch (\Throwable $e) {
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
