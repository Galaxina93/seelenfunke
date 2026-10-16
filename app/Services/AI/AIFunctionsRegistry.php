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
    use Functions\AiOrderFuncs;
    use Functions\AiFinanceFuncs;
    use Functions\AiScoutFuncs;
    use Functions\AiHealthFuncs;
    use Functions\AiTaskFuncs;
    use Functions\AiRoutineFuncs;
    use Functions\AiCalendarFuncs;
    use Functions\AiBrainFuncs;
    use Functions\AiMailFuncs;
    use Functions\AiContactFuncs;
    use Functions\AiProductFuncs;
    use Functions\AiAgentsFuncs;
    use Functions\AiMasterFuncs;
    use Functions\AiTelefonyFuncs;
    use Functions\AiHolidayPlannerFuncs;
    use Functions\AiNewsFuncs;
    use Functions\AiMapControlFuncs;
    use Functions\AiMapNewsFuncs;
    use Functions\AiPersonaFuncs;
    use Functions\AiShoppingListFuncs;
    use Functions\AiLaserFuncs;

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
            self::getAiOrderFuncsSchema(),
            self::getAiCommunicationFuncsSchema(),
            self::getAiFinanceFuncsSchema(),
            self::getAiScoutFuncsSchema(),
            self::getAiHealthFuncsSchema(),
            self::getAiTaskFuncsSchema(),
            self::getAiRoutineFuncsSchema(),
            self::getAiCalendarFuncsSchema(),
            self::getAiBrainFuncsSchema(),
            self::getAiMailFuncsSchema(),
            self::getAiContactFuncsSchema(),
            self::getAiProductFuncsSchema(),
            self::getAiAgentsFuncsSchema(),
            self::getAiMasterFuncsSchema(),
            self::getAiTelefonyFuncsSchema(),
            self::getAiHolidayPlannerFuncsSchema(),
            self::getAiNewsFuncsSchema(),
            self::getAiMapControlFuncsSchema(),
            self::getAiMapNewsFuncsSchema(),
            self::getAiPersonaFuncsSchema(),
            self::getAiShoppingListFuncsSchema(),
            self::getAiLaserFuncsSchema()
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
    public static function execute(string $name, array $args = [], $agent = null)
    {
        $functions = collect(self::getFunctions())->keyBy('name');

        if (!$functions->has($name)) {
            return [
                'error' => true,
                'status' => 'error',
                'message' => "Function '{$name}' is not registered or does not exist. Please pick a VALID tool from the schema."
            ];
        }

        $destructiveTools = ['system_multi_replace_file', 'system_write_to_file', 'system_run_command'];
        if (in_array($name, $destructiveTools)) {
            if (!\Illuminate\Support\Facades\Session::get('has_ai_implementation_plan')) {
                return [
                    'error' => true,
                    'status' => 'error',
                    'message' => 'SYSTEM GUARDRAIL BLOCK: Du darfst destruktive Aktionen erst ausführen, wenn du vorher einen implementation_plan geschrieben hast.'
                ];
            }
        }

        $callable = $functions[$name]['callable'];

        if (!is_callable($callable)) {
            throw new \RuntimeException("Callable for function '{$name}' is invalid.");
        }

        try {
            $mergedArgs = array_merge($args, self::$globalContext); // Inject invisible backend parameters
            return call_user_func($callable, $mergedArgs, $agent);
        } catch (\Throwable $e) {
            Log::error("AI Function Execution Error: " . $e->getMessage());
            
            $errorMessage = 'Error executing function: ' . $e->getMessage();
            
            try {
                $markdownPath = null;
                if (class_exists(\App\Livewire\Backend\System\SystemNeuralAnalysisIndex::class)) {
                    $markdownPath = \App\Livewire\Backend\System\SystemNeuralAnalysisIndex::generateMarkdownForFile($e->getFile());
                }

                try {
                    $email = config('mail.from.address') ?: 'kontakt@mein-seelenfunke.de';
                    $body = "Das KI-Werkzeug '{$name}' ist abgestürzt.\n\nFehler-Details:\n" . $e->getMessage() . "\n\n" . ($markdownPath ? "Die neuronale Struktur der fehlerhaften Datei liegt im Anhang bei." : "Die Dateistruktur konnte nicht generiert werden.");
                    $attachments = $markdownPath ? [$markdownPath] : [];
                    $agentName = $agent ? $agent->name : 'System';
                    
                    \Illuminate\Support\Facades\Mail::to($email)->send(new \App\Services\AI\Mails\AiAgentMessageMail("SYSTEM-NOTFALL: Tool-Absturz ({$name})", $body, $agentName, $attachments));
                    
                    $errorMessage .= "\n\n[SYSTEM-INFO: Ein Fehlerbericht " . ($markdownPath ? "inklusive der Dateistruktur " : "") . "wurde bereits automatisch an den Administrator gesendet. Du musst keine weitere E-Mail schreiben!]";
                } catch (\Exception $mailErr) {
                    \Illuminate\Support\Facades\Log::error("Failed to send AI tool fallback email: " . $mailErr->getMessage());
                    // Fallback, wenn die Mail nicht rausging
                    if ($markdownPath) {
                        $errorMessage .= "\n\n[SYSTEM-INFO: Eine Struktur-Analyse der fehlerhaften Datei wurde generiert. Sie liegt unter: {$markdownPath}.]";
                    }
                }
            } catch (\Throwable $mdError) {
                Log::error("Failed to generate neural structure for error reporting: " . $mdError->getMessage());
                $errorMessage .= "\n\n[SYSTEM-INFO: Die Struktur-Analyse der fehlerhaften Datei konnte nicht erstellt werden: " . $mdError->getMessage() . "]";
            }

            return [
                'error' => true,
                'message' => $errorMessage
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
