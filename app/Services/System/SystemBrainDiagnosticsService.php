<?php

namespace App\Services\System;

use App\Models\Admin\Admin;
use App\Models\Customer\Customer;
use App\Models\Employee\Employee;
use App\Models\Marketing\MarketingBlogPost;
use App\Models\Marketing\MarketingLandingPage;
use App\Models\Product\Product;
use App\Models\System\SystemLog;
use App\Models\System\SystemNeuralNode;
use App\Services\Navigation\BackendNavigationService;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Livewire\Component as LivewireComponent;
use ReflectionClass;

class SystemBrainDiagnosticsService
{
    /**
     * Liefert das Live-Fehler-Overlay aus SystemLog (und optionalen Check-Fehlern),
     * gekoppelt an die exakten Architektur-Knoten (Fehlerkerne in Rot) inklusive
     * aller eingehenden und ausgehenden Nachbardateien rund um den Fehlerkern.
     */
    public function getActiveErrorOverlay(array $syntheticIssues = []): array
    {
        $nodeIndex = $this->loadNodeIndex();
        $graphLinks = $this->loadGraphLinks();

        $rawEntries = [];
        $openErrorsCount = 0;
        $openWarningsCount = 0;

        if (Schema::hasTable('system_logs')) {
            $logs = SystemLog::query()
                ->whereIn('status', ['error', 'warning'])
                ->orderByRaw("CASE WHEN status = 'error' THEN 0 ELSE 1 END")
                ->orderBy('id', 'desc')
                ->limit(100)
                ->get();

            foreach ($logs as $log) {
                if ($log->status === 'error') {
                    $openErrorsCount++;
                } else {
                    $openWarningsCount++;
                }

                $payload = is_array($log->payload) ? $log->payload : [];
                $matchedFiles = $this->resolveFilesFromSystemLog($log, $nodeIndex);

                $rawEntries[] = [
                    'source' => 'system_log',
                    'id' => $log->id,
                    'status' => $log->status === 'error' ? 'error' : 'warning',
                    'type' => (string) ($log->type ?? 'system'),
                    'action_id' => (string) ($log->action_id ?? ''),
                    'title' => (string) ($log->title ?: 'System-Ereignis'),
                    'message' => (string) ($log->message ?: ''),
                    'line' => isset($payload['line']) && is_numeric($payload['line']) ? (int) $payload['line'] : null,
                    'occurrences_count' => max(1, (int) ($payload['occurrences_count'] ?? 1)),
                    'timestamp' => ($log->finished_at ?? $log->started_at ?? $log->created_at)?->format('d.m.Y H:i:s'),
                    'route' => (string) ($payload['execution_context']['route'] ?? $payload['execution_context']['url'] ?? ''),
                    'files' => $matchedFiles,
                ];
            }
        }

        // Sekundärer Fallback: Prüfe laravel.log auf rohe PHP-Fatal-Einträge ohne SystemLog-Zeile
        foreach ($this->extractRecentLaravelLogErrors($nodeIndex) as $fileError) {
            $alreadyCovered = collect($rawEntries)->contains(
                fn (array $e) => in_array($fileError['file_path'], $e['files'], true)
            );
            if (!$alreadyCovered) {
                $openErrorsCount++;
                $rawEntries[] = [
                    'source' => 'laravel_log',
                    'id' => null,
                    'status' => 'error',
                    'type' => 'system',
                    'action_id' => 'laravel_log:error',
                    'title' => 'Fehler in laravel.log',
                    'message' => $fileError['message'],
                    'line' => $fileError['line'],
                    'occurrences_count' => 1,
                    'timestamp' => now()->format('d.m.Y H:i:s'),
                    'route' => '',
                    'files' => [$fileError['file_path']],
                ];
            }
        }

        // Optionale Live-Befunde aus "System prüfen"
        foreach ($syntheticIssues as $issue) {
            $file = $this->normalizeToProjectRelativePath((string) ($issue['file_path'] ?? ''));
            $severity = (($issue['severity'] ?? 'error') === 'warning') ? 'warning' : 'error';
            if ($severity === 'error') {
                $openErrorsCount++;
            } else {
                $openWarningsCount++;
            }

            $rawEntries[] = [
                'source' => 'system_check',
                'id' => null,
                'status' => $severity,
                'type' => 'system_check',
                'action_id' => (string) ($issue['category'] ?? 'check'),
                'title' => (string) ($issue['title'] ?? 'Systemprüfung Befund'),
                'message' => (string) ($issue['message'] ?? ''),
                'line' => isset($issue['line']) && is_numeric($issue['line']) ? (int) $issue['line'] : null,
                'occurrences_count' => 1,
                'timestamp' => now()->format('d.m.Y H:i:s'),
                'route' => (string) ($issue['route'] ?? ''),
                'files' => $file ? [$file] : [],
            ];
        }

        $errorCores = [];
        $unmappedLogs = [];

        foreach ($rawEntries as $entry) {
            if (empty($entry['files'])) {
                $unmappedLogs[] = $entry;
                continue;
            }

            // Primärdatei ist der direkte Fehlerkern; weitere im Trace involvierte Projektdateien werden mitgekoppelt
            foreach ($entry['files'] as $idx => $filePath) {
                if (!isset($errorCores[$filePath])) {
                    $meta = $this->describeFileNode($filePath, $nodeIndex);
                    $neighborhood = $this->resolveConnectedNeighborhood($filePath, $nodeIndex, $graphLinks);

                    $errorCores[$filePath] = [
                        'file_path' => $filePath,
                        'name' => $meta['name'],
                        'group_id' => $meta['group_id'],
                        'group_label' => $meta['group_label'],
                        'severity' => $entry['status'],
                        'is_error' => $entry['status'] === 'error',
                        'is_primary_culprit' => $idx === 0,
                        'error_count' => 0,
                        'occurrences_total' => 0,
                        'primary_title' => $entry['title'],
                        'primary_message' => $entry['message'],
                        'line' => $idx === 0 ? $entry['line'] : null,
                        'route' => $entry['route'],
                        'logs' => [],
                        'incoming_files' => $neighborhood['incoming'],
                        'outgoing_files' => $neighborhood['outgoing'],
                        'connected_files' => $neighborhood['all'],
                    ];
                } else {
                    if ($entry['status'] === 'error' && $errorCores[$filePath]['severity'] !== 'error') {
                        $errorCores[$filePath]['severity'] = 'error';
                        $errorCores[$filePath]['is_error'] = true;
                        $errorCores[$filePath]['primary_title'] = $entry['title'];
                        $errorCores[$filePath]['primary_message'] = $entry['message'];
                    }
                    if ($idx === 0) {
                        $errorCores[$filePath]['is_primary_culprit'] = true;
                        if ($entry['line'] && !$errorCores[$filePath]['line']) {
                            $errorCores[$filePath]['line'] = $entry['line'];
                        }
                    }
                }

                $errorCores[$filePath]['error_count']++;
                $errorCores[$filePath]['occurrences_total'] += (int) $entry['occurrences_count'];
                $errorCores[$filePath]['logs'][] = [
                    'id' => $entry['id'],
                    'source' => $entry['source'],
                    'status' => $entry['status'],
                    'type' => $entry['type'],
                    'title' => $entry['title'],
                    'message' => Str::limit($entry['message'], 360),
                    'line' => $idx === 0 ? $entry['line'] : null,
                    'occurrences_count' => $entry['occurrences_count'],
                    'timestamp' => $entry['timestamp'],
                    'route' => $entry['route'],
                ];
            }
        }

        // Sortiere Fehlerkerne: echte Fehler zuerst, dann primäre Verursacher, dann nach Anzahl
        uasort($errorCores, function (array $a, array $b) {
            if ($a['is_error'] !== $b['is_error']) {
                return $a['is_error'] ? -1 : 1;
            }
            if ($a['is_primary_culprit'] !== $b['is_primary_culprit']) {
                return $a['is_primary_culprit'] ? -1 : 1;
            }
            return $b['error_count'] <=> $a['error_count'];
        });

        // Nachbarschafts-Index: Welche Dateien hängen direkt an welchem Fehlerkern?
        $neighbors = [];
        foreach ($errorCores as $corePath => $core) {
            foreach ($core['connected_files'] as $conn) {
                $nPath = $conn['file_path'];
                if (isset($errorCores[$nPath])) {
                    continue;
                }
                $neighbors[$nPath][] = $corePath;
            }
        }
        foreach ($neighbors as $nPath => $coreList) {
            $neighbors[$nPath] = array_values(array_unique($coreList));
        }

        $apiErrorsList = [];
        foreach ($errorCores as $core) {
            $lineHint = $core['line'] ? " (Zeile {$core['line']})" : '';
            $apiErrorsList[] = [
                'name' => $core['name'],
                'file_path' => $core['file_path'],
                'severity' => $core['severity'],
                'is_error' => $core['is_error'],
                'line' => $core['line'],
                'occurrences_count' => $core['occurrences_total'],
                'error_state' => "[{$core['primary_title']}{$lineHint}] " . Str::limit($core['primary_message'], 320),
                'connected_files' => array_map(fn (array $c) => $c['file_path'], $core['connected_files']),
                'incoming_files' => array_map(fn (array $c) => $c['file_path'], $core['incoming_files']),
                'outgoing_files' => array_map(fn (array $c) => $c['file_path'], $core['outgoing_files']),
            ];
        }

        return [
            'has_errors' => $openErrorsCount > 0 || $openWarningsCount > 0,
            'has_critical_errors' => $openErrorsCount > 0,
            'open_errors_count' => $openErrorsCount,
            'open_warnings_count' => $openWarningsCount,
            'error_core_count' => count($errorCores),
            'neighbor_count' => count($neighbors),
            'error_cores' => $errorCores,
            'neighbors' => $neighbors,
            'unmapped_logs' => $unmappedLogs,
            'api_errors_list' => $apiErrorsList,
        ];
    }

    /**
     * Reichert das JSON-Graph-Objekt für das 3D-Projekt-Gehirn live mit offenen SystemLog-Fehlern
     * (rote Fehlerkerne + markierte Nachbarknoten & Kanten) an.
     */
    public function enrichBrainGraphJson(array $graph, array $syntheticIssues = []): array
    {
        $overlay = $this->getActiveErrorOverlay($syntheticIssues);
        $cores = $overlay['error_cores'];
        $neighbors = $overlay['neighbors'];

        $seenNodePaths = [];
        if (isset($graph['nodes']) && is_array($graph['nodes'])) {
            foreach ($graph['nodes'] as &$node) {
                $path = (string) ($node['id'] ?? $node['path'] ?? '');
                $seenNodePaths[$path] = true;
                if (isset($cores[$path])) {
                    $core = $cores[$path];
                    $lineHint = $core['line'] ? " (Zeile {$core['line']})" : '';
                    $node['isError'] = true;
                    $node['severity'] = $core['severity'];
                    $node['errorMessage'] = "[{$core['primary_title']}{$lineHint}]\n" . $core['primary_message'];
                    $node['occurrencesCount'] = $core['occurrences_total'];
                    $node['connectedFiles'] = array_map(fn (array $c) => $c['file_path'], $core['connected_files']);
                    $node['incomingFiles'] = array_map(fn (array $c) => $c['file_path'], $core['incoming_files']);
                    $node['outgoingFiles'] = array_map(fn (array $c) => $c['file_path'], $core['outgoing_files']);
                    $node['isErrorNeighbor'] = false;
                } elseif (isset($neighbors[$path])) {
                    $node['isError'] = false;
                    $node['isErrorNeighbor'] = true;
                    $node['connectedErrorCores'] = $neighbors[$path];
                } else {
                    $node['isError'] = false;
                    $node['isErrorNeighbor'] = false;
                }
            }
            unset($node);

            foreach ($cores as $corePath => $core) {
                if (!isset($seenNodePaths[$corePath])) {
                    $lineHint = $core['line'] ? " (Zeile {$core['line']})" : '';
                    $graph['nodes'][] = [
                        'id' => $corePath,
                        'name' => $core['name'],
                        'path' => $corePath,
                        'group' => $core['group_id'],
                        'val' => 4,
                        'isError' => true,
                        'severity' => $core['severity'],
                        'errorMessage' => "[{$core['primary_title']}{$lineHint}]\n" . $core['primary_message'],
                        'occurrencesCount' => $core['occurrences_total'],
                        'connectedFiles' => array_map(fn (array $c) => $c['file_path'], $core['connected_files']),
                        'incomingFiles' => array_map(fn (array $c) => $c['file_path'], $core['incoming_files']),
                        'outgoingFiles' => array_map(fn (array $c) => $c['file_path'], $core['outgoing_files']),
                        'isErrorNeighbor' => false,
                    ];
                }
            }
        }

        if (isset($graph['links']) && is_array($graph['links'])) {
            foreach ($graph['links'] as &$link) {
                $src = is_array($link['source']) ? ($link['source']['id'] ?? '') : (string) ($link['source'] ?? '');
                $tgt = is_array($link['target']) ? ($link['target']['id'] ?? '') : (string) ($link['target'] ?? '');
                $link['isErrorLink'] = isset($cores[$src]) || isset($cores[$tgt]);
            }
            unset($link);
        }

        $graph['error_summary'] = [
            'has_errors' => $overlay['has_errors'],
            'open_errors_count' => $overlay['open_errors_count'],
            'open_warnings_count' => $overlay['open_warnings_count'],
            'error_core_count' => $overlay['error_core_count'],
            'neighbor_count' => $overlay['neighbor_count'],
            'errors' => $overlay['api_errors_list'],
        ];

        return $graph;
    }

    /**
     * Führt die logische Gesamt-Systemprüfung ("System prüfen") über Frontend-Routen,
     * Backend-Navigation, Portale, Sicherheits-Guards, Architektur-Blueprints, Datenbank-Schema
     * und das System-Log aus.
     */
    public function runFullSystemCheck(bool $allowSubprocessHttpSweep = true): array
    {
        $startedAt = microtime(true);
        $issues = [];
        $sections = [];

        // 1. Statische & strukturelle Prüfung aller Frontend-, Backend- & Portal-Routen
        $frontendTargets = $this->collectFrontendCheckRoutes();
        $backendTargets = $this->collectBackendCheckRoutes();
        $portalTargets = $this->collectPortalCheckRoutes();

        $frontendStaticFailures = $this->verifyRoutesStructurally($frontendTargets, false);
        $backendStaticFailures = $this->verifyRoutesStructurally($backendTargets, true);
        $portalStaticFailures = $this->verifyRoutesStructurally($portalTargets, false);

        foreach (array_merge($frontendStaticFailures, $backendStaticFailures, $portalStaticFailures) as $fail) {
            $issues[] = $fail;
        }

        // 2. Echter HTTP-Kernel-Schnelltest im isolierten Kindprozess (schützt aktive Livewire-Session zu 100%)
        $httpSweepRan = false;
        $frontendCheckedCount = count($frontendTargets);
        $backendCheckedCount = count($backendTargets);
        $portalCheckedCount = count($portalTargets);

        if ($allowSubprocessHttpSweep && !app()->runningUnitTests()) {
            try {
                $proc = Process::timeout(40)
                    ->path(base_path())
                    ->run([PHP_BINARY, 'artisan', 'system:check-routes', '--json', '--routes-only']);

                $rawOut = trim($proc->output());
                $jsonStart = strpos($rawOut, '{');
                if ($jsonStart !== false) {
                    $decoded = json_decode(substr($rawOut, $jsonStart), true);
                    if (is_array($decoded) && isset($decoded['route_checks'])) {
                        $httpSweepRan = true;
                        $frontendCheckedCount = (int) ($decoded['frontend_checked'] ?? $frontendCheckedCount);
                        $backendCheckedCount = (int) ($decoded['backend_checked'] ?? $backendCheckedCount);
                        $portalCheckedCount = (int) ($decoded['portal_checked'] ?? $portalCheckedCount);

                        foreach ($decoded['failures'] ?? [] as $httpFail) {
                            $issues[] = [
                                'category' => $httpFail['scope'] ?? 'route_http',
                                'severity' => 'error',
                                'title' => 'Routen-Fehler (' . ($httpFail['uri'] ?? '?') . ')',
                                'message' => (string) ($httpFail['error'] ?? 'HTTP Status Fehler'),
                                'file_path' => $httpFail['file_path'] ?? null,
                                'route' => $httpFail['uri'] ?? '',
                            ];
                        }
                    }
                }
            } catch (\Throwable) {
                // Falls Subprozess-Aufruf in restriktiver Umgebung gesperrt ist, greift nahtlos die Strukturprüfung
            }
        }

        $frontendIssueCount = count(array_filter($issues, fn ($i) => str_starts_with((string) ($i['category'] ?? ''), 'frontend')));
        $backendIssueCount = count(array_filter($issues, fn ($i) => str_starts_with((string) ($i['category'] ?? ''), 'backend')));
        $portalIssueCount = count(array_filter($issues, fn ($i) => str_starts_with((string) ($i['category'] ?? ''), 'portal')));

        $sections[] = [
            'key' => 'frontend_routes',
            'label' => 'Frontend-Routen',
            'passed' => $frontendIssueCount === 0,
            'metric' => ($frontendCheckedCount - $frontendIssueCount) . ' / ' . $frontendCheckedCount . ' OK',
            'mode' => $httpSweepRan ? 'HTTP + Struktur' : 'Struktur & Views',
        ];

        $sections[] = [
            'key' => 'backend_routes',
            'label' => 'Backend-Navigation & Admin',
            'passed' => $backendIssueCount === 0,
            'metric' => ($backendCheckedCount - $backendIssueCount) . ' / ' . $backendCheckedCount . ' OK',
            'mode' => $httpSweepRan ? 'HTTP + Auth-Guard' : 'Struktur & Auth-Guard',
        ];

        // 3. Architektur-Blueprints (GoBD / DSGVO / KI-Guardrails)
        $bpService = app(SystemBlueprintService::class);
        $bpAudit = $bpService->auditArchitecture();
        if (empty($bpAudit['is_fully_compliant'])) {
            foreach ($bpAudit['missing_blueprints'] ?? [] as $missingFqcn) {
                $issues[] = [
                    'category' => 'blueprints',
                    'severity' => 'error',
                    'title' => 'Fehlendes Model-Blueprint',
                    'message' => "Model {$missingFqcn} implementiert kein HasSystemBlueprint::blueprint().",
                    'file_path' => $this->reflectClassRelativePath($missingFqcn),
                ];
            }
            foreach ($bpAudit['violations'] ?? [] as $violationText) {
                $matchedFile = null;
                if (preg_match('/\[(App\\\\[^\]]+)\]/', (string) $violationText, $m)) {
                    $matchedFile = $this->reflectClassRelativePath($m[1]);
                }
                $issues[] = [
                    'category' => 'blueprints',
                    'severity' => 'error',
                    'title' => 'Blueprint-Compliance-Verstoß',
                    'message' => (string) $violationText,
                    'file_path' => $matchedFile ?: 'app/Services/System/SystemBlueprintService.php',
                ];
            }
        }

        $sections[] = [
            'key' => 'blueprints',
            'label' => 'Model-Blueprints (DSGVO/GoBD)',
            'passed' => !empty($bpAudit['is_fully_compliant']),
            'metric' => ($bpAudit['compliant_models'] ?? 0) . ' / ' . ($bpAudit['total_models'] ?? 0) . ' Konform',
            'mode' => 'Code-Reflexion',
        ];

        // 4. Datenbank-Verbindung & Tabellen-Schema aller Eloquent-Models
        $models = $bpService->discoverAllModels();
        $missingTables = [];
        $sqlModelsCount = 0;
        try {
            DB::connection()->getPdo();
            foreach ($models as $modelEntry) {
                $fqcn = (string) ($modelEntry['class'] ?? '');
                if ($fqcn !== '' && defined($fqcn . '::VIRTUAL_MODEL_NO_TABLE') && constant($fqcn . '::VIRTUAL_MODEL_NO_TABLE') === true) {
                    continue;
                }
                $sqlModelsCount++;
                $table = (string) ($modelEntry['table'] ?? '');
                if ($table !== '' && !Schema::hasTable($table)) {
                    $missingTables[] = $table;
                    $issues[] = [
                        'category' => 'database_schema',
                        'severity' => 'error',
                        'title' => "Datenbank-Tabelle fehlt ({$table})",
                        'message' => "Für Model {$modelEntry['class']} existiert keine Tabelle '{$table}' in der Datenbank.",
                        'file_path' => $modelEntry['relative_path'] ?? null,
                    ];
                }
            }
        } catch (\Throwable $dbEx) {
            $missingTables[] = 'connection';
            $issues[] = [
                'category' => 'database_schema',
                'severity' => 'error',
                'title' => 'Datenbank-Verbindungsfehler',
                'message' => SystemErrorLogger::sanitizeSensitiveText($dbEx->getMessage()),
                'file_path' => 'config/database.php',
            ];
        }

        $sections[] = [
            'key' => 'database_schema',
            'label' => 'Datenbank & Tabellen-Schema',
            'passed' => empty($missingTables),
            'metric' => max(0, $sqlModelsCount - count($missingTables)) . ' / ' . $sqlModelsCount . ' Tabellen',
            'mode' => 'Schema-Prüfung',
        ];

        // 5. Sicherheits-Guards & Rollen-Isolation
        $securityFailures = 0;
        if (!class_exists(\App\Livewire\Hooks\EmployeeRoleIsolationHook::class)) {
            $securityFailures++;
            $issues[] = [
                'category' => 'security_guards',
                'severity' => 'error',
                'title' => 'EmployeeRoleIsolationHook fehlt',
                'message' => 'Der zentrale Rollen-Isolations-Hook für Livewire-Komponenten konnte nicht geladen werden.',
                'file_path' => 'app/Providers/AppServiceProvider.php',
            ];
        }
        if (File::exists(storage_path('app/public/system-brain-map.json'))) {
            $securityFailures++;
            $issues[] = [
                'category' => 'security_guards',
                'severity' => 'error',
                'title' => 'Brain-Map öffentlich erreichbar',
                'message' => 'storage/app/public/system-brain-map.json existiert im öffentlichen Verzeichnis statt ausschließlich unter storage/app/private.',
                'file_path' => 'app/Console/Commands/GenerateSystemBrainMap.php',
            ];
        }

        $sections[] = [
            'key' => 'security_guards',
            'label' => 'Sicherheits-Guards & Portale',
            'passed' => $securityFailures === 0 && $portalIssueCount === 0,
            'metric' => ($securityFailures === 0 && $portalIssueCount === 0)
                ? ($portalCheckedCount . ' Portale geschützt')
                : (($securityFailures + $portalIssueCount) . ' Abweichungen'),
            'mode' => 'Zero-Trust Audit',
        ];

        // 6. Kopplung mit offenen SystemLog-Einträgen
        $overlay = $this->getActiveErrorOverlay($issues);

        $sections[] = [
            'key' => 'system_logs',
            'label' => 'System-Log & Fehlerkerne',
            'passed' => !$overlay['has_errors'],
            'metric' => !$overlay['has_errors']
                ? '0 offene Fehler'
                : ($overlay['open_errors_count'] . ' Fehler / ' . $overlay['open_warnings_count'] . ' Warnungen'),
            'mode' => 'Live-Kopplung',
        ];

        $durationMs = max(1, (int) round((microtime(true) - $startedAt) * 1000));
        $allPassed = collect($sections)->every(fn (array $s) => $s['passed'] === true);

        return [
            'passed' => $allPassed,
            'checked_at' => now()->format('d.m.Y H:i:s'),
            'duration_ms' => $durationMs,
            'http_sweep_executed' => $httpSweepRan,
            'frontend_checked' => $frontendCheckedCount,
            'backend_checked' => $backendCheckedCount,
            'portal_checked' => $portalCheckedCount,
            'sections' => $sections,
            'issues_count' => count($issues),
            'overlay' => $overlay,
        ];
    }

    /**
     * Führt echte HTTP-GET-Requests über den Laravel HttpKernel gegen alle Frontend-,
     * Backend-Navigations- und Portal-Routen innerhalb einer zurückgerollten DB-Transaktion aus.
     * Gedacht für CLI (`php artisan system:check-routes` / `./funki check`) und isolierte Subprozesse.
     */
    public function executeIsolatedHttpRouteSweep(): array
    {
        $startedAt = microtime(true);
        $frontendRoutes = $this->collectFrontendCheckRoutes();
        $backendRoutes = $this->collectBackendCheckRoutes();
        $portalRoutes = $this->collectPortalCheckRoutes();

        $failures = [];
        $results = [];

        DB::beginTransaction();
        try {
            $admin = Admin::query()->first() ?: Admin::create([
                'first_name' => 'System',
                'last_name' => 'Auditor',
                'email' => 'system-route-check-' . Str::random(8) . '@mein-seelenfunke.de',
                'password' => bcrypt(Str::random(24)),
            ]);

            $customer = Customer::query()->whereNotNull('email_verified_at')->first() ?: Customer::create([
                'first_name' => 'Portal',
                'last_name' => 'Prüfkunde',
                'email' => 'system-portal-customer-' . Str::random(8) . '@mein-seelenfunke.de',
                'password' => bcrypt(Str::random(24)),
                'email_verified_at' => now(),
            ]);

            $internalEmployee = Employee::query()
                ->where(function ($q) {
                    $q->whereNull('role')->orWhereNotIn('role', ['influencer', 'tax_advisor']);
                })
                ->first() ?: Employee::create([
                    'first_name' => 'Intern',
                    'last_name' => 'Prüfer',
                    'email' => 'system-portal-emp-' . Str::random(8) . '@mein-seelenfunke.de',
                    'password' => bcrypt(Str::random(24)),
                    'role' => 'internal',
                    'is_active' => true,
                ]);

            $influencerEmployee = Employee::query()
                ->where('role', 'influencer')
                ->whereHas('influencerProfile', fn ($q) => $q->where('is_active', true)->where('is_archived', false))
                ->first();

            if (!$influencerEmployee) {
                $influencerEmployee = Employee::create([
                    'first_name' => 'Partner',
                    'last_name' => 'Prüfer',
                    'email' => 'system-portal-inf-' . Str::random(8) . '@mein-seelenfunke.de',
                    'password' => bcrypt(Str::random(24)),
                    'role' => 'influencer',
                    'is_active' => true,
                ]);
                \App\Models\Marketing\MarketingInfluencer::create([
                    'employee_id' => $influencerEmployee->id,
                    'handle' => '@system_partner_' . strtolower(Str::random(5)),
                    'slug' => 'system-partner-' . strtolower(Str::random(6)),
                    'commission_type' => 'percent',
                    'commission_value' => 10,
                    'is_active' => true,
                    'is_archived' => false,
                ]);
            }

            $taxAdvisorEmployee = Employee::query()->where('role', 'tax_advisor')->first() ?: Employee::create([
                'first_name' => 'Kanzlei',
                'last_name' => 'Prüfer',
                'email' => 'system-portal-tax-' . Str::random(8) . '@mein-seelenfunke.de',
                'password' => bcrypt(Str::random(24)),
                'role' => 'tax_advisor',
                'is_active' => true,
            ]);

            // 1. Frontend Routes (Guest)
            Auth::forgetGuards();
            foreach ($frontendRoutes as $item) {
                $probe = $this->dispatchSingleKernelGet($item['uri'], null, null);
                $ok = in_array($probe['status'], [200, 301, 302], true) && empty($probe['deprecations']) && $probe['exception'] === null;
                $results[] = array_merge($item, $probe, ['ok' => $ok]);
                if (!$ok) {
                    $failures[] = [
                        'scope' => 'frontend_routes',
                        'uri' => $item['uri'],
                        'label' => $item['label'],
                        'status' => $probe['status'],
                        'file_path' => $probe['error_file'] ?: $item['file_path'],
                        'error' => $probe['error_summary'],
                    ];
                }
            }

            // 2. Backend Navigation & Admin Routes (Authenticated as Admin)
            foreach ($backendRoutes as $item) {
                $probe = $this->dispatchSingleKernelGet($item['uri'], $admin, 'admin');
                $ok = in_array($probe['status'], [200, 302], true) && empty($probe['deprecations']) && $probe['exception'] === null;
                $results[] = array_merge($item, $probe, ['ok' => $ok]);
                if (!$ok) {
                    $failures[] = [
                        'scope' => 'backend_routes',
                        'uri' => $item['uri'],
                        'label' => $item['label'],
                        'status' => $probe['status'],
                        'file_path' => $probe['error_file'] ?: $item['file_path'],
                        'error' => $probe['error_summary'],
                    ];
                }
            }

            // 3. Portal Role Routes
            foreach ($portalRoutes as $item) {
                $guard = $item['guard'] ?? 'customer';
                $actor = match ($guard) {
                    'customer' => $customer,
                    'employee_internal' => $internalEmployee,
                    'influencer' => $influencerEmployee,
                    'tax_advisor' => $taxAdvisorEmployee,
                    default => null,
                };
                $actualGuard = in_array($guard, ['employee_internal', 'influencer', 'tax_advisor'], true) ? 'employee' : $guard;

                if ($actor) {
                    $probe = $this->dispatchSingleKernelGet($item['uri'], $actor, $actualGuard);
                    $ok = in_array($probe['status'], [200, 302], true) && empty($probe['deprecations']) && $probe['exception'] === null;
                } else {
                    // Unauthenticated guest must be redirected to login (302) and never 500
                    $probe = $this->dispatchSingleKernelGet($item['uri'], null, null);
                    $ok = in_array($probe['status'], [302, 401, 403], true) && empty($probe['deprecations']) && $probe['exception'] === null;
                }

                $results[] = array_merge($item, $probe, ['ok' => $ok]);
                if (!$ok) {
                    $failures[] = [
                        'scope' => 'portal_routes',
                        'uri' => $item['uri'],
                        'label' => $item['label'],
                        'status' => $probe['status'],
                        'file_path' => $probe['error_file'] ?: $item['file_path'],
                        'error' => $probe['error_summary'],
                    ];
                }
            }
        } finally {
            Auth::forgetGuards();
            DB::rollBack();
        }

        $durationMs = max(1, (int) round((microtime(true) - $startedAt) * 1000));

        return [
            'passed' => empty($failures),
            'duration_ms' => $durationMs,
            'total_checked' => count($results),
            'frontend_checked' => count($frontendRoutes),
            'backend_checked' => count($backendRoutes),
            'portal_checked' => count($portalRoutes),
            'failures_count' => count($failures),
            'failures' => $failures,
            'route_checks' => $results,
        ];
    }

    /**
     * Sammelt alle öffentlichen Frontend-Routen inkl. realer Produkt-, Landingpage- und Blog-Slugs.
     */
    public function collectFrontendCheckRoutes(): array
    {
        $static = [
            ['uri' => '/', 'label' => 'Startseite'],
            ['uri' => '/shop', 'label' => 'Shop-Übersicht'],
            ['uri' => '/manufaktur', 'label' => 'Manufaktur'],
            ['uri' => '/blog', 'label' => 'Ratgeber / Blog'],
            ['uri' => '/calculator', 'label' => 'Sofort-Kalkulator'],
            ['uri' => '/kontakt', 'label' => 'Kontaktformular'],
            ['uri' => '/versand', 'label' => 'Versandinformationen'],
            ['uri' => '/newsletter', 'label' => 'Newsletter-Seite'],
            ['uri' => '/links', 'label' => 'Linktree (/links)'],
            ['uri' => '/warenkorb', 'label' => 'Warenkorb'],
            ['uri' => '/wunschliste', 'label' => 'Wunschliste'],
            ['uri' => '/gutscheine', 'label' => 'Geschenkgutschein-Weiterleitung'],
            ['uri' => '/impressum', 'label' => 'Impressum'],
            ['uri' => '/datenschutz', 'label' => 'Datenschutz'],
            ['uri' => '/agb', 'label' => 'AGB'],
            ['uri' => '/widerruf', 'label' => 'Widerrufsbelehrung & Formular'],
            ['uri' => '/widerruf/pdf', 'label' => 'Widerrufsformular PDF'],
            ['uri' => '/barrierefreiheit', 'label' => 'Barrierefreiheit'],
            ['uri' => '/verhaltenskodex', 'label' => 'Verhaltenskodex'],
            ['uri' => '/notfall', 'label' => 'Notfall-Protokoll'],
            ['uri' => '/login', 'label' => 'Login'],
            ['uri' => '/register', 'label' => 'Registrierung'],
            ['uri' => '/sitemap.xml', 'label' => 'XML-Sitemap'],
            ['uri' => '/llms.txt', 'label' => 'KI-Übersicht (llms.txt)'],
            ['uri' => '/llms-full.txt', 'label' => 'KI-Katalog (llms-full.txt)'],
            ['uri' => '/feed/google-shopping.xml', 'label' => 'Google Shopping XML-Feed'],
            ['uri' => '/feed/products.json', 'label' => 'JSON-Produktfeed'],
        ];

        try {
            if (Schema::hasTable('products')) {
                $activeProduct = Product::where('status', 'active')->latest('updated_at')->first();
                if ($activeProduct && !empty($activeProduct->slug)) {
                    $static[] = ['uri' => '/produkt/' . $activeProduct->slug, 'label' => 'Produktdetail (' . $activeProduct->slug . ')'];
                }
            }
            if (Schema::hasTable('marketing_landing_pages')) {
                $activeLp = MarketingLandingPage::where('status', 'active')->latest('updated_at')->first();
                if ($activeLp && !empty($activeLp->slug)) {
                    $static[] = ['uri' => '/l/' . $activeLp->slug, 'label' => 'Landingpage (' . $activeLp->slug . ')'];
                }
            }
            if (Schema::hasTable('marketing_blog_posts')) {
                $publishedPost = MarketingBlogPost::published()->latest('updated_at')->first();
                if ($publishedPost && !empty($publishedPost->slug)) {
                    $static[] = ['uri' => '/blog/' . $publishedPost->slug, 'label' => 'Blogbeitrag (' . $publishedPost->slug . ')'];
                }
            }
        } catch (\Throwable) {
        }

        return array_map(function (array $entry) {
            $entry['scope'] = 'frontend_routes';
            $entry['file_path'] = $this->resolveRouteTargetFile($entry['uri']) ?: 'routes/partials/global_routes.php';
            return $entry;
        }, $static);
    }

    /**
     * Sammelt alle Seiten aus BackendNavigationService::getStructure() sowie alle parameterlosen GET /admin/* Routen.
     */
    public function collectBackendCheckRoutes(): array
    {
        $collected = [];
        $seenUris = [];

        foreach (BackendNavigationService::getStructure() as $section) {
            $dept = (string) ($section['department'] ?? $section['label'] ?? 'Backend');
            if (!empty($section['route']) && Route::has($section['route'])) {
                $uri = '/' . ltrim(parse_url(route($section['route']), PHP_URL_PATH) ?: '', '/');
                if ($uri !== '/' && !isset($seenUris[$uri])) {
                    $seenUris[$uri] = true;
                    $collected[] = [
                        'scope' => 'backend_routes',
                        'uri' => $uri,
                        'route_name' => $section['route'],
                        'label' => $dept . ': ' . ($section['label'] ?? $section['route']),
                        'file_path' => $this->resolveRouteTargetFile($uri) ?: 'routes/partials/admin_routes.php',
                    ];
                }
            }

            foreach ($section['children'] ?? [] as $child) {
                $rName = $child['route'] ?? null;
                if ($rName && Route::has($rName)) {
                    $uri = '/' . ltrim(parse_url(route($rName), PHP_URL_PATH) ?: '', '/');
                    if ($uri !== '/' && !isset($seenUris[$uri])) {
                        $seenUris[$uri] = true;
                        $collected[] = [
                            'scope' => 'backend_routes',
                            'uri' => $uri,
                            'route_name' => $rName,
                            'label' => $dept . ' -> ' . ($child['label'] ?? $rName),
                            'file_path' => $this->resolveRouteTargetFile($uri) ?: 'routes/partials/admin_routes.php',
                        ];
                    }
                }
            }
        }

        // Ergänze alle weiteren parameterlosen GET /admin/* Routen
        foreach (Route::getRoutes() as $route) {
            if (!in_array('GET', $route->methods(), true)) {
                continue;
            }
            $uri = '/' . ltrim($route->uri(), '/');
            if (!str_starts_with($uri, '/admin') || str_contains($uri, '{')) {
                continue;
            }
            // Überspringe reine Datei-Download-, Vorschau- und Stream-Endpunkte mit Pflicht-Query-Parametern bei der Seiten-Prüfung
            $queryStreamEndpoints = [
                '/admin/inbox/compose-preview',
                '/admin/ai/workspace/file',
                '/admin/accounting/receipt',
                '/admin/employees/timesheet',
            ];
            if (
                str_contains($uri, '/download/')
                || str_ends_with($uri, '/download')
                || str_ends_with($uri, '/export')
                || in_array('signed', $route->gatherMiddleware(), true)
                || in_array($uri, $queryStreamEndpoints, true)
            ) {
                continue;
            }
            if (!isset($seenUris[$uri])) {
                $seenUris[$uri] = true;
                $collected[] = [
                    'scope' => 'backend_routes',
                    'uri' => $uri,
                    'route_name' => $route->getName() ?: $uri,
                    'label' => 'Admin-Subroute ' . $uri,
                    'file_path' => $this->resolveRouteTargetFile($uri) ?: 'routes/partials/admin_routes.php',
                ];
            }
        }

        return $collected;
    }

    /**
     * Sammelt die geschützten Kunden-, Mitarbeiter-, Influencer- und Steuerberater-Portale.
     */
    public function collectPortalCheckRoutes(): array
    {
        $portals = [
            ['uri' => '/customer/dashboard', 'label' => 'Kunden-Portal (/customer/dashboard)', 'guard' => 'customer', 'fallback' => 'routes/partials/customer_routes.php'],
            ['uri' => '/orders', 'label' => 'Kunden-Bestellungen (/orders)', 'guard' => 'customer', 'fallback' => 'routes/partials/customer_routes.php'],
            ['uri' => '/invoices', 'label' => 'Kunden-Rechnungen (/invoices)', 'guard' => 'customer', 'fallback' => 'routes/partials/customer_routes.php'],
            ['uri' => '/support', 'label' => 'Kunden-Support (/support)', 'guard' => 'customer', 'fallback' => 'routes/partials/customer_routes.php'],
            ['uri' => '/funki', 'label' => 'Kunden-Funki & Prämien (/funki)', 'guard' => 'customer', 'fallback' => 'routes/partials/customer_routes.php'],
            ['uri' => '/employee/dashboard', 'label' => 'Mitarbeiter-Portal (/employee/dashboard)', 'guard' => 'employee_internal', 'fallback' => 'routes/partials/employee_routes.php'],
            ['uri' => '/employee/influencer/dashboard', 'label' => 'Influencer-Portal (/employee/influencer/dashboard)', 'guard' => 'influencer', 'fallback' => 'routes/partials/employee_routes.php'],
            ['uri' => '/employee/tax-advisor/dashboard', 'label' => 'Steuerberater-Portal (/employee/tax-advisor/dashboard)', 'guard' => 'tax_advisor', 'fallback' => 'routes/partials/employee_routes.php'],
        ];

        return array_map(function (array $p) {
            return [
                'scope' => 'portal_routes',
                'uri' => $p['uri'],
                'label' => $p['label'],
                'guard' => $p['guard'],
                'file_path' => $this->resolveRouteTargetFile($p['uri']) ?: $p['fallback'],
            ];
        }, $portals);
    }

    /**
     * Prüft Routen ohne HttpKernel-Seiteneffekte auf Registrierung, Auth-Middleware,
     * Zielklasse (Controller / Livewire) und Existenz des zugehörigen Blade-Views.
     */
    protected function verifyRoutesStructurally(array $targets, bool $requireAdminAuth): array
    {
        $failures = [];
        $routes = Route::getRoutes();

        foreach ($targets as $item) {
            $uri = $item['uri'];
            try {
                $matched = $routes->match(Request::create($uri, 'GET'));
            } catch (\Throwable $e) {
                $failures[] = [
                    'category' => $item['scope'],
                    'severity' => 'error',
                    'title' => "Route nicht erreichbar ({$uri})",
                    'message' => "Route {$uri} ({$item['label']}) konnte nicht aufgelöst werden: " . $e->getMessage(),
                    'file_path' => $item['file_path'],
                    'route' => $uri,
                ];
                continue;
            }

            $middlewares = $matched->gatherMiddleware();
            if ($requireAdminAuth) {
                $hasAdminGuard = collect($middlewares)->contains(
                    fn ($mw) => is_string($mw) && (str_contains($mw, 'auth:admin') || str_contains($mw, 'auth:'))
                );
                if (!$hasAdminGuard) {
                    $failures[] = [
                        'category' => $item['scope'],
                        'severity' => 'error',
                        'title' => "Fehlender Auth-Schutz ({$uri})",
                        'message' => "Admin-Route {$uri} besitzt keine auth:admin Middleware!",
                        'file_path' => 'routes/partials/admin_routes.php',
                        'route' => $uri,
                    ];
                }
            }

            $actionName = $matched->getActionName();
            if ($actionName && $actionName !== 'Closure') {
                $className = explode('@', $actionName)[0];
                if (!class_exists($className)) {
                    $failures[] = [
                        'category' => $item['scope'],
                        'severity' => 'error',
                        'title' => "Zielklasse fehlt ({$uri})",
                        'message' => "Klasse {$className} für Route {$uri} existiert nicht.",
                        'file_path' => $item['file_path'],
                        'route' => $uri,
                    ];
                    continue;
                }

                $classRelPath = $this->reflectClassRelativePath($className) ?: $item['file_path'];
                if (is_subclass_of($className, LivewireComponent::class) && File::exists(base_path($classRelPath))) {
                    $source = file_get_contents(base_path($classRelPath));
                    if (preg_match('/view\(\s*[\'"](livewire\.[^\'"]+)[\'"]/', $source, $vm)) {
                        $viewDot = $vm[1];
                        if (!View::exists($viewDot)) {
                            $failures[] = [
                                'category' => $item['scope'],
                                'severity' => 'error',
                                'title' => "Blade-View fehlt ({$viewDot})",
                                'message' => "Livewire-Komponente {$className} ({$uri}) verweist auf fehlendes View '{$viewDot}'.",
                                'file_path' => $classRelPath,
                                'route' => $uri,
                            ];
                        }
                    }
                }
            }
        }

        return $failures;
    }

    /**
     * Führt einen einzelnen internen HTTP-GET-Request mit E_ALL Deprecation-Überwachung aus.
     */
    protected function dispatchSingleKernelGet(string $uri, mixed $actor = null, ?string $guard = null): array
    {
        if (app()->bound('redirect') && app('redirect') instanceof \Livewire\Features\SupportRedirects\Redirector) {
            app()->forgetInstance('redirect');
        }

        Auth::forgetGuards();
        if ($actor && $guard) {
            Auth::guard($guard)->setUser($actor);
            Auth::shouldUse($guard);
        }

        $deprecations = [];
        $prevReporting = error_reporting(E_ALL);
        set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline) use (&$deprecations) {
            if (in_array($errno, [E_DEPRECATED, E_USER_DEPRECATED, E_WARNING, E_USER_WARNING, E_NOTICE], true)) {
                if (!str_contains($errfile, '/vendor/')) {
                    $deprecations[] = [
                        'message' => $errstr,
                        'file' => $this->normalizeToProjectRelativePath($errfile) ?: $errfile,
                        'line' => $errline,
                    ];
                }
            }
            return false;
        });

        $status = 500;
        $exceptionMsg = null;
        $errorFile = null;

        try {
            $req = Request::create($uri, 'GET');
            /** @var HttpKernel $kernel */
            $kernel = app(HttpKernel::class);
            $resp = $kernel->handle($req);
            $status = $resp->getStatusCode();

            if (property_exists($resp, 'exception') && $resp->exception instanceof \Throwable && $status >= 400) {
                $ex = $resp->exception;
                $exceptionMsg = $ex->getMessage() . ' @ ' . basename($ex->getFile()) . ':' . $ex->getLine();
                $errorFile = $this->extractFirstProjectFileFromThrowable($ex);
            }
            $kernel->terminate($req, $resp);
        } catch (\Throwable $ex) {
            $status = 500;
            $exceptionMsg = $ex->getMessage() . ' @ ' . basename($ex->getFile()) . ':' . $ex->getLine();
            $errorFile = $this->extractFirstProjectFileFromThrowable($ex);
        } finally {
            if (app()->bound('redirect') && app('redirect') instanceof \Livewire\Features\SupportRedirects\Redirector) {
                app()->forgetInstance('redirect');
            }
            restore_error_handler();
            error_reporting($prevReporting);
        }

        if (!$errorFile && !empty($deprecations[0]['file'])) {
            $errorFile = $deprecations[0]['file'];
        }

        $errorSummary = null;
        if ($exceptionMsg !== null) {
            $errorSummary = "HTTP {$status}: {$exceptionMsg}";
        } elseif ($status >= 400) {
            $errorSummary = "Unerwarteter HTTP-Status {$status}";
        } elseif (!empty($deprecations)) {
            $d = $deprecations[0];
            $errorSummary = "PHP Warnung/Deprecation: {$d['message']} ({$d['file']}:{$d['line']})";
        }

        return [
            'status' => $status,
            'exception' => $exceptionMsg,
            'deprecations' => $deprecations,
            'error_file' => $errorFile,
            'error_summary' => $errorSummary,
        ];
    }

    protected function extractFirstProjectFileFromThrowable(\Throwable $e): ?string
    {
        $direct = $this->normalizeToProjectRelativePath($e->getFile());
        if ($direct) {
            return $direct;
        }
        if (preg_match('/\(View:\s*([^\)]+\.blade\.php)\)/', $e->getMessage(), $vm)) {
            $vRel = $this->normalizeToProjectRelativePath($vm[1]);
            if ($vRel) {
                return $vRel;
            }
        }
        foreach ($e->getTrace() as $frame) {
            $cand = $this->normalizeToProjectRelativePath((string) ($frame['file'] ?? ''));
            if ($cand) {
                return $cand;
            }
        }
        return null;
    }

    /**
     * Ermittelt die exakten Projektdateien (Fehlerkern + direkt beteiligte Trace-Dateien) für einen SystemLog-Eintrag.
     */
    public function resolveFilesFromSystemLog(SystemLog $log, ?array $nodeIndex = null): array
    {
        $nodeIndex = $nodeIndex ?? $this->loadNodeIndex();
        $payload = is_array($log->payload) ? $log->payload : [];
        $matched = [];

        $addCandidate = function (?string $candidate) use (&$matched) {
            $norm = $this->normalizeToProjectRelativePath($candidate);
            if ($norm && !in_array($norm, $matched, true)) {
                $matched[] = $norm;
            }
        };

        // 1. Expliziter View-Hinweis in der Fehlermeldung (z. B. Blade ViewException)
        if (preg_match('/\(View:\s*([^\)]+\.blade\.php)\)/', (string) $log->message, $vm)) {
            $addCandidate($vm[1]);
        }

        // 2. payload.file (inkl. automatischer Rückauflösung kompilierter Blade-Views)
        if (!empty($payload['file']) && is_string($payload['file'])) {
            $addCandidate($payload['file']);
        }

        // 3. Blueprint-Guardrail / Architektur-Audit Model-Klassen
        if (!empty($payload['model_class']) && is_string($payload['model_class'])) {
            $addCandidate($this->reflectClassRelativePath($payload['model_class']));
        }
        if (!empty($payload['missing_blueprints']) && is_array($payload['missing_blueprints'])) {
            foreach ($payload['missing_blueprints'] as $missingClass) {
                if (is_string($missingClass)) {
                    $addCandidate($this->reflectClassRelativePath($missingClass));
                }
            }
        }

        // 4. Trace- & Stack-Excerpts nach Projektdateien durchsuchen
        $traceLines = [];
        if (!empty($payload['trace_excerpt']) && is_array($payload['trace_excerpt'])) {
            $traceLines = array_merge($traceLines, $payload['trace_excerpt']);
        }
        if (!empty($payload['stack_excerpt']) && is_string($payload['stack_excerpt'])) {
            $traceLines = array_merge($traceLines, explode("\n", $payload['stack_excerpt']));
        }
        $traceLines[] = (string) $log->message;

        foreach ($traceLines as $line) {
            if (!is_string($line) || str_contains($line, '/vendor/')) {
                continue;
            }
            if (preg_match_all('/((?:app|resources\/views|routes|config)\/[A-Za-z0-9_\-\.\/]+\.php)/', str_replace('\\', '/', $line), $m)) {
                foreach ($m[1] as $foundRel) {
                    $addCandidate($foundRel);
                    if (count($matched) >= 2) {
                        break 2;
                    }
                }
            }
        }

        // 5. Fehlgeschlagener Artisan-Befehl / Cronjob -> Command-Klasse auflösen
        if (empty($matched) && !empty($payload['command']) && is_string($payload['command'])) {
            $cmdName = trim(explode(' ', trim($payload['command']))[0]);
            try {
                $allCommands = Artisan::all();
                if (isset($allCommands[$cmdName])) {
                    $addCandidate($this->reflectClassRelativePath(get_class($allCommands[$cmdName])));
                }
            } catch (\Throwable) {
            }
        }

        // 6. HTTP-Route / URL aus execution_context -> Controller oder Livewire-Klasse
        if (empty($matched)) {
            $routeHint = (string) ($payload['execution_context']['route'] ?? '');
            $urlHint = (string) ($payload['execution_context']['url'] ?? '');
            $resolvedRouteFile = $this->resolveRouteOrUrlToFile($routeHint, $urlHint);
            if ($resolvedRouteFile) {
                $addCandidate($resolvedRouteFile);
            }
        }

        return $matched;
    }

    /**
     * Normalisiert absolute Container-/Host-Pfade sowie kompilierte Blade-Views auf relative Projektpfade.
     */
    public function normalizeToProjectRelativePath(?string $rawPath): ?string
    {
        if ($rawPath === null || trim($rawPath) === '' || str_contains($rawPath, "\0")) {
            return null;
        }

        $clean = str_replace('\\', '/', trim($rawPath));
        // Entferne optionale Zeilennummern wie ":123" am Ende
        $clean = (string) preg_replace('/:\d+$/', '', $clean);

        // Kompilierte Blade-Views in storage/framework/views/*.php auf echte .blade.php zurückführen
        if (str_contains($clean, 'storage/framework/views/') && str_ends_with($clean, '.php')) {
            $compiledCandidate = File::exists($clean)
                ? $clean
                : base_path(substr($clean, (int) strpos($clean, 'storage/framework/views/')));
            if (File::exists($compiledCandidate)) {
                $compiledContent = @file_get_contents($compiledCandidate) ?: '';
                if (preg_match('/\/\*\*PATH\s+(.+?)\s+ENDPATH\*\*\//', $compiledContent, $pm)) {
                    $clean = str_replace('\\', '/', trim($pm[1]));
                }
            }
        }

        $baseNorm = rtrim(str_replace('\\', '/', base_path()), '/') . '/';
        if (str_starts_with($clean, $baseNorm)) {
            $clean = substr($clean, strlen($baseNorm));
        } elseif (str_starts_with($clean, '/var/www/html/')) {
            $clean = substr($clean, strlen('/var/www/html/'));
        }

        if (preg_match('/((?:app|resources\/views|routes|config)\/[A-Za-z0-9_\-\.\/]+\.php)$/', $clean, $m)) {
            $clean = $m[1];
        }

        $clean = ltrim($clean, '/');
        if (
            $clean === ''
            || str_contains($clean, '..')
            || str_contains($clean, '/vendor/')
            || !preg_match('/^(?:app|resources\/views|routes|config)\/[A-Za-z0-9_\-\.\/]+\.php$/', $clean)
        ) {
            return null;
        }

        return File::exists(base_path($clean)) ? $clean : null;
    }

    /**
     * Ermittelt alle direkt verbundenen Nachbardateien (eingehende Aufrufer + ausgehende Abhängigkeiten)
     * rund um eine Datei. Funktioniert sowohl mit SystemNeuralNode als auch dynamisch im Live-/Testbetrieb.
     */
    public function resolveConnectedNeighborhood(string $filePath, ?array $nodeIndex = null, ?array $graphLinks = null): array
    {
        $nodeIndex = $nodeIndex ?? $this->loadNodeIndex();
        $graphLinks = $graphLinks ?? $this->loadGraphLinks();

        $incomingPaths = [];
        $outgoingPaths = [];

        if (isset($nodeIndex[$filePath])) {
            $nodeData = $nodeIndex[$filePath];
            $props = is_array($nodeData['properties'] ?? null) ? $nodeData['properties'] : [];
            if (isset($props['incoming_dependents']) && is_array($props['incoming_dependents'])) {
                $incomingPaths = array_merge($incomingPaths, $props['incoming_dependents']);
            }
            if (isset($props['outgoing_dependencies']) && is_array($props['outgoing_dependencies'])) {
                $outgoingPaths = array_merge($outgoingPaths, $props['outgoing_dependencies']);
            }
            if (empty($incomingPaths) && empty($outgoingPaths) && !empty($nodeData['dependencies'])) {
                $outgoingPaths = array_merge($outgoingPaths, (array) $nodeData['dependencies']);
            }
        }

        // Ergänze Kanten aus system-brain-map.json falls vorhanden
        foreach ($graphLinks as $link) {
            $src = is_array($link['source']) ? ($link['source']['id'] ?? '') : (string) ($link['source'] ?? '');
            $tgt = is_array($link['target']) ? ($link['target']['id'] ?? '') : (string) ($link['target'] ?? '');
            if ($src === $filePath && $tgt !== '' && $tgt !== $filePath) {
                $outgoingPaths[] = $tgt;
            } elseif ($tgt === $filePath && $src !== '' && $src !== $filePath) {
                $incomingPaths[] = $src;
            }
        }

        // Dynamischer Fallback direkt aus der Datei & den Routen (z. B. in frischen Test-DBs ohne vorherigen Scan)
        if (empty($incomingPaths) && empty($outgoingPaths) && File::exists(base_path($filePath))) {
            $dyn = $this->parseDynamicNeighborsForFile($filePath);
            $incomingPaths = array_merge($incomingPaths, $dyn['incoming']);
            $outgoingPaths = array_merge($outgoingPaths, $dyn['outgoing']);
        }

        $incomingPaths = array_values(array_unique(array_filter($incomingPaths, fn ($p) => is_string($p) && $p !== '' && $p !== $filePath)));
        $outgoingPaths = array_values(array_unique(array_filter($outgoingPaths, fn ($p) => is_string($p) && $p !== '' && $p !== $filePath)));
        sort($incomingPaths);
        sort($outgoingPaths);

        $incomingFormatted = array_map(fn (string $p) => array_merge(
            $this->describeFileNode($p, $nodeIndex),
            ['direction' => in_array($p, $outgoingPaths, true) ? 'bidirectional' : 'incoming']
        ), $incomingPaths);

        $outgoingFormatted = array_map(fn (string $p) => array_merge(
            $this->describeFileNode($p, $nodeIndex),
            ['direction' => in_array($p, $incomingPaths, true) ? 'bidirectional' : 'outgoing']
        ), $outgoingPaths);

        $mergedMap = [];
        foreach ($incomingFormatted as $item) {
            $mergedMap[$item['file_path']] = $item;
        }
        foreach ($outgoingFormatted as $item) {
            if (!isset($mergedMap[$item['file_path']])) {
                $mergedMap[$item['file_path']] = $item;
            }
        }

        return [
            'incoming' => $incomingFormatted,
            'outgoing' => $outgoingFormatted,
            'all' => array_values($mergedMap),
        ];
    }

    protected function parseDynamicNeighborsForFile(string $filePath): array
    {
        $incoming = [];
        $outgoing = [];
        $abs = base_path($filePath);
        if (!File::exists($abs)) {
            return ['incoming' => [], 'outgoing' => []];
        }

        $content = file_get_contents($abs);

        // Ausgehende Klassenreferenzen (use + inline App\...)
        if (preg_match_all('/\b(App\\\\[A-Za-z0-9_\\\\]+)/', $content, $cm)) {
            foreach (array_unique($cm[1]) as $fqcn) {
                $rel = $this->reflectClassRelativePath($fqcn);
                if ($rel && $rel !== $filePath) {
                    $outgoing[] = $rel;
                }
            }
        }

        // Ausgehende Blade-Views
        if (preg_match_all('/(?:view|->layout|@include|@extends)\(\s*[\'"]([^\'"]+)[\'"]/', $content, $vm)) {
            foreach (array_unique($vm[1]) as $vName) {
                $candView = 'resources/views/' . str_replace('.', '/', $vName) . '.blade.php';
                if (File::exists(base_path($candView)) && $candView !== $filePath) {
                    $outgoing[] = $candView;
                }
            }
        }

        // Prüfe ob eine Route direkt auf diese Klasse zeigt
        if (preg_match('/namespace\s+([^;]+);/', $content, $ns) && preg_match('/\bclass\s+([A-Za-z0-9_]+)/', $content, $cl)) {
            $myFqcn = trim($ns[1]) . '\\' . trim($cl[1]);
            foreach (['routes/partials/admin_routes.php', 'routes/partials/global_routes.php', 'routes/partials/employee_routes.php', 'routes/partials/customer_routes.php', 'routes/api.php', 'routes/api/ai.php'] as $routeFile) {
                if (File::exists(base_path($routeFile))) {
                    $rContent = file_get_contents(base_path($routeFile));
                    if (str_contains($rContent, $myFqcn) || str_contains($rContent, trim($cl[1]) . '::class')) {
                        $incoming[] = $routeFile;
                    }
                }
            }
        }

        return ['incoming' => $incoming, 'outgoing' => $outgoing];
    }

    protected function describeFileNode(string $filePath, array $nodeIndex): array
    {
        if (isset($nodeIndex[$filePath])) {
            $groupId = (int) ($nodeIndex[$filePath]['group_id'] ?? $this->inferGroupId($filePath));
            return [
                'file_path' => $filePath,
                'name' => (string) ($nodeIndex[$filePath]['name'] ?? basename($filePath)),
                'group_id' => $groupId,
                'group_label' => $this->groupLabel($groupId),
            ];
        }

        $groupId = $this->inferGroupId($filePath);
        return [
            'file_path' => $filePath,
            'name' => basename($filePath),
            'group_id' => $groupId,
            'group_label' => $this->groupLabel($groupId),
        ];
    }

    public function inferGroupId(string $relativePath): int
    {
        if (str_contains($relativePath, 'app/Models')) return 2;
        if (str_contains($relativePath, 'app/Http/Controllers')) return 3;
        if (str_contains($relativePath, 'app/Livewire')) return 4;
        if (str_contains($relativePath, 'resources/views')) return 5;
        if (str_contains($relativePath, 'routes/')) return 6;
        if (str_contains($relativePath, 'config/')) return 7;
        if (str_contains($relativePath, 'app/Services')) return 8;
        if (str_contains($relativePath, 'app/Console')) return 9;
        return 1;
    }

    public function groupLabel(int $groupId): string
    {
        return match ($groupId) {
            2 => 'Model',
            3 => 'Controller',
            4 => 'Livewire',
            5 => 'View',
            6 => 'Route',
            7 => 'Config',
            8 => 'Service',
            9 => 'Command',
            default => 'System',
        };
    }

    protected function loadNodeIndex(): array
    {
        $index = [];
        try {
            if (Schema::hasTable('system_neural_nodes')) {
                $rows = SystemNeuralNode::query()->get(['id', 'file_path', 'name', 'group_id', 'dependencies', 'properties']);
                foreach ($rows as $row) {
                    $index[$row->file_path] = [
                        'id' => $row->id,
                        'file_path' => $row->file_path,
                        'name' => $row->name,
                        'group_id' => (int) $row->group_id,
                        'dependencies' => is_array($row->dependencies) ? $row->dependencies : [],
                        'properties' => is_array($row->properties) ? $row->properties : [],
                    ];
                }
            }
        } catch (\Throwable) {
        }

        return $index;
    }

    protected function loadGraphLinks(): array
    {
        $jsonPath = storage_path('app/private/system-brain-map.json');
        if (!File::exists($jsonPath)) {
            return [];
        }
        $decoded = json_decode((string) @file_get_contents($jsonPath), true);
        return (is_array($decoded) && isset($decoded['links']) && is_array($decoded['links']))
            ? $decoded['links']
            : [];
    }

    protected function extractRecentLaravelLogErrors(array $nodeIndex): array
    {
        $logPath = storage_path('logs/laravel.log');
        if (!File::exists($logPath)) {
            return [];
        }
        $filesize = (int) @filesize($logPath);
        if ($filesize <= 0) {
            return [];
        }

        $readLength = min(65536, $filesize);
        $fp = @fopen($logPath, 'rb');
        if (!$fp) {
            return [];
        }
        fseek($fp, -$readLength, SEEK_END);
        $content = (string) fread($fp, $readLength);
        fclose($fp);

        $found = [];
        $currentMsg = null;
        foreach (explode("\n", $content) as $line) {
            if (preg_match('/^\[[^\]]+\]\s+[a-zA-Z0-9_-]+\.ERROR:\s*(.+)$/', $line, $em)) {
                $currentMsg = SystemErrorLogger::sanitizeSensitiveText(trim($em[1]));
            }
            if ($currentMsg && !str_contains($line, '/vendor/')) {
                if (preg_match('/((?:app|resources\/views|routes|config)\/[A-Za-z0-9_\-\.\/]+\.php)(?:\((\d+)\)|:(\d+))?/', str_replace('\\', '/', $line), $fm)) {
                    $norm = $this->normalizeToProjectRelativePath($fm[1]);
                    if ($norm && !isset($found[$norm])) {
                        $lineNo = !empty($fm[2]) ? (int) $fm[2] : (!empty($fm[3]) ? (int) $fm[3] : null);
                        $found[$norm] = [
                            'file_path' => $norm,
                            'line' => $lineNo,
                            'message' => Str::limit($currentMsg, 300),
                        ];
                    }
                    $currentMsg = null;
                }
            }
        }

        return array_values($found);
    }

    protected function resolveRouteTargetFile(string $uri): ?string
    {
        try {
            $route = Route::getRoutes()->match(Request::create($uri, 'GET'));
            $action = $route->getActionName();
            if ($action && $action !== 'Closure') {
                $className = explode('@', $action)[0];
                return $this->reflectClassRelativePath($className);
            }
        } catch (\Throwable) {
        }
        return null;
    }

    protected function resolveRouteOrUrlToFile(string $routeHint, string $urlHint): ?string
    {
        if ($routeHint !== '') {
            $byName = Route::getRoutes()->getByName($routeHint);
            if ($byName) {
                $action = $byName->getActionName();
                if ($action && $action !== 'Closure') {
                    $rel = $this->reflectClassRelativePath(explode('@', $action)[0]);
                    if ($rel) {
                        return $rel;
                    }
                }
            }
            if (str_starts_with($routeHint, '/') || !str_contains($routeHint, '.')) {
                $rel = $this->resolveRouteTargetFile('/' . ltrim($routeHint, '/'));
                if ($rel) {
                    return $rel;
                }
            }
        }

        if ($urlHint !== '') {
            $path = parse_url($urlHint, PHP_URL_PATH);
            if (is_string($path) && $path !== '') {
                return $this->resolveRouteTargetFile($path);
            }
        }

        return null;
    }

    protected function reflectClassRelativePath(string $fqcn): ?string
    {
        $clean = ltrim(trim($fqcn), '\\');
        if ($clean === '' || !class_exists($clean) && !interface_exists($clean) && !trait_exists($clean)) {
            return null;
        }
        try {
            $ref = new ReflectionClass($clean);
            return $this->normalizeToProjectRelativePath($ref->getFileName() ?: null);
        } catch (\Throwable) {
            return null;
        }
    }
}
