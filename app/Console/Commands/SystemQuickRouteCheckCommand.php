<?php

namespace App\Console\Commands;

use App\Services\System\SystemBlueprintService;
use App\Services\System\SystemBrainDiagnosticsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class SystemQuickRouteCheckCommand extends Command
{
    protected $signature = 'system:check-routes
                            {--json : Gibt das Prüfergebnis als reines JSON aus}
                            {--routes-only : Prüft ausschließlich den isolierten HTTP-Routen-Durchlauf}';

    protected $description = 'Schnelltest für alle Frontend-, Backend-Navigations- und Portal-Routen inkl. Blueprint-, Schema- und Projekt-Gehirn-Kopplung.';

    public function handle(SystemBrainDiagnosticsService $diagnostics, SystemBlueprintService $blueprintService): int
    {
        $asJson = (bool) $this->option('json');
        $routesOnly = (bool) $this->option('routes-only');

        $routeSweep = $diagnostics->executeIsolatedHttpRouteSweep();

        if ($routesOnly) {
            if ($asJson) {
                $this->line(json_encode($routeSweep, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            } else {
                $this->renderCliSummary($routeSweep, null);
            }
            return $routeSweep['passed'] ? self::SUCCESS : self::FAILURE;
        }

        // Kombiniere echten HTTP-Durchlauf mit Blueprint-, Schema- und SystemLog-Diagnose
        $syntheticIssues = [];
        foreach ($routeSweep['failures'] ?? [] as $fail) {
            $syntheticIssues[] = [
                'category' => $fail['scope'] ?? 'route_http',
                'severity' => 'error',
                'title' => 'Routen-Fehler (' . ($fail['uri'] ?? '?') . ')',
                'message' => (string) ($fail['error'] ?? 'HTTP Status Fehler'),
                'file_path' => $fail['file_path'] ?? null,
                'route' => $fail['uri'] ?? '',
            ];
        }

        $bpAudit = $blueprintService->auditArchitecture();
        if (empty($bpAudit['is_fully_compliant'])) {
            foreach ($bpAudit['violations'] ?? [] as $v) {
                $syntheticIssues[] = [
                    'category' => 'blueprints',
                    'severity' => 'error',
                    'title' => 'Blueprint-Abweichung',
                    'message' => (string) $v,
                    'file_path' => 'app/Services/System/SystemBlueprintService.php',
                ];
            }
        }

        $models = $blueprintService->discoverAllModels();
        $missingTables = 0;
        $sqlModelsCount = 0;
        foreach ($models as $m) {
            $fqcn = (string) ($m['class'] ?? '');
            if ($fqcn !== '' && defined($fqcn . '::VIRTUAL_MODEL_NO_TABLE') && constant($fqcn . '::VIRTUAL_MODEL_NO_TABLE') === true) {
                continue;
            }
            $sqlModelsCount++;
            $table = (string) ($m['table'] ?? '');
            if ($table !== '' && !Schema::hasTable($table)) {
                $missingTables++;
                $syntheticIssues[] = [
                    'category' => 'database_schema',
                    'severity' => 'error',
                    'title' => "Fehlende DB-Tabelle ({$table})",
                    'message' => "Model {$m['class']} erwartet fehlende Tabelle '{$table}'.",
                    'file_path' => $m['relative_path'] ?? null,
                ];
            }
        }

        $overlay = $diagnostics->getActiveErrorOverlay($syntheticIssues);
        $overallPassed = $routeSweep['passed']
            && !empty($bpAudit['is_fully_compliant'])
            && $missingTables === 0
            && !$overlay['has_errors'];

        $fullPayload = [
            'passed' => $overallPassed,
            'duration_ms' => $routeSweep['duration_ms'],
            'route_sweep' => $routeSweep,
            'blueprints_compliant' => !empty($bpAudit['is_fully_compliant']),
            'models_checked' => $models->count(),
            'sql_models_checked' => $sqlModelsCount,
            'missing_tables' => $missingTables,
            'overlay' => $overlay,
        ];

        if ($asJson) {
            $this->line(json_encode($fullPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            return $overallPassed ? self::SUCCESS : self::FAILURE;
        }

        $this->renderCliSummary($routeSweep, $fullPayload);

        return $overallPassed ? self::SUCCESS : self::FAILURE;
    }

    protected function renderCliSummary(array $routeSweep, ?array $fullPayload): void
    {
        $this->newLine();
        $this->info('⚡ Seelenfunke System- & Routen-Schnelltest (Frontend + Backend-Navigation + Projekt-Gehirn)');
        $this->line(str_repeat('─', 88));

        $rows = [
            [
                'Frontend-Routen',
                $routeSweep['frontend_checked'] . ' Routen',
                collect($routeSweep['failures'])->where('scope', 'frontend_routes')->isEmpty() ? 'GRÜN (100% OK)' : 'FEHLER',
            ],
            [
                'Backend-Navigation & Admin',
                $routeSweep['backend_checked'] . ' Seiten',
                collect($routeSweep['failures'])->where('scope', 'backend_routes')->isEmpty() ? 'GRÜN (100% OK)' : 'FEHLER',
            ],
            [
                'Rollen-Portale',
                $routeSweep['portal_checked'] . ' Portale',
                collect($routeSweep['failures'])->where('scope', 'portal_routes')->isEmpty() ? 'GRÜN (100% OK)' : 'FEHLER',
            ],
        ];

        if ($fullPayload !== null) {
            $rows[] = [
                'Architektur-Blueprints',
                $fullPayload['models_checked'] . ' Models',
                $fullPayload['blueprints_compliant'] ? 'GRÜN (100% Konform)' : 'ABWEICHUNG',
            ];
            $rows[] = [
                'Datenbank-Tabellen',
                ($fullPayload['sql_models_checked'] ?? $fullPayload['models_checked']) . ' Tabellen',
                $fullPayload['missing_tables'] === 0 ? 'GRÜN (Vollständig)' : ($fullPayload['missing_tables'] . ' fehlen'),
            ];
            $overlay = $fullPayload['overlay'];
            $rows[] = [
                'System-Log & Fehlerkerne',
                $overlay['error_core_count'] . ' Fehlerkerne',
                !$overlay['has_errors']
                    ? 'GRÜN (0 Fehler)'
                    : ('ROT (' . $overlay['open_errors_count'] . ' Fehler / ' . $overlay['open_warnings_count'] . ' Warnungen)'),
            ];
        }

        $this->table(['Prüfbereich', 'Umfang', 'Status'], $rows);

        if (!empty($routeSweep['failures'])) {
            $this->error('Festgestellte Routen-Fehler:');
            foreach ($routeSweep['failures'] as $f) {
                $this->line("  ✗ [{$f['scope']}] {$f['uri']} -> {$f['error']} (Datei: " . ($f['file_path'] ?: '-') . ')');
            }
        }

        if ($fullPayload !== null && !empty($fullPayload['overlay']['error_cores'])) {
            $this->newLine();
            $this->error('Aktive Fehlerkerne im Projekt-Gehirn (ROT) inkl. verbundener Dateien:');
            foreach ($fullPayload['overlay']['error_cores'] as $core) {
                $connectedNames = implode(', ', array_map(fn ($c) => $c['name'], array_slice($core['connected_files'], 0, 8)));
                $this->line("  ● [ROT] {$core['file_path']} -> {$core['primary_title']}");
                $this->line('    ↳ Verbundene Dateien: ' . ($connectedNames !== '' ? $connectedNames : 'Keine'));
            }
        }

        $this->newLine();
        $passed = $fullPayload ? $fullPayload['passed'] : $routeSweep['passed'];
        if ($passed) {
            $this->info("✅ Ergebnis: 100% SICHER & GRÜN ({$routeSweep['total_checked']} Routen in {$routeSweep['duration_ms']} ms geprüft).");
        } else {
            $this->error("❌ Ergebnis: Abweichungen gefunden ({$routeSweep['duration_ms']} ms).");
        }
    }
}
