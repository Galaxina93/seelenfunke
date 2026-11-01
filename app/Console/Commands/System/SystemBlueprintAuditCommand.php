<?php

namespace App\Console\Commands\System;

use App\Services\System\SystemBlueprintService;
use Illuminate\Console\Command;

class SystemBlueprintAuditCommand extends Command
{
    protected $signature = 'system:blueprint:audit
                            {--export-vvt : Generiert das aktuelle Art. 30 DSGVO Verarbeitungsverzeichnis als Markdown}
                            {--json : Gibt das Audit-Ergebnis als JSON aus}';

    protected $description = 'Prüft alle Eloquent-Models auf ein gültiges Architektur- & Compliance-Typenschild (ModelBlueprint), alarmiert bei Verstößen per SystemLog/Mail und erstellt auf Wunsch das DSGVO-Verarbeitungsverzeichnis (Art. 30).';

    public function handle(SystemBlueprintService $blueprintService): int
    {
        $audit = $blueprintService->auditArchitecture();

        if (!$audit['is_fully_compliant']) {
            $blueprintService->reportArchitectureAuditViolations($audit);
        }

        if ($this->option('json')) {
            $this->line(json_encode($audit, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return $audit['is_fully_compliant'] ? self::SUCCESS : self::FAILURE;
        }

        $this->info('================================================================================');
        $this->info(' SEELENFUNKE ARCHITEKTUR- & COMPLIANCE-BLUEPRINT AUDIT (LIVING DOCUMENTATION)');
        $this->info('================================================================================');

        $this->table(
            ['Kennzahl / Dimension', 'Wert', 'Status'],
            [
                ['Geprüfte Eloquent-Models (Gesamt)', $audit['total_models'], 'INFO'],
                ['Models mit vollständigem Blueprint', "{$audit['compliant_models']} / {$audit['total_models']}", $audit['is_fully_compliant'] ? '100% OK' : 'FEHLER'],
                ['GoBD-geschützte Models (10 Jahre Aufbewahrung)', $audit['gobd_models_count'], 'GESCHÜTZT'],
                ['Art. 15 DSGVO 1-Klick-Kundenexport-Models', $audit['gdpr_exportable_count'], 'AKTIV'],
                ['KI-Guardrail: Strikte KI-Sperre (None)', $audit['ai_strictly_blocked_count'], 'HART GESPERRT'],
                ['KI-Guardrail: Nur Lesen für Funki (ReadOnly)', $audit['ai_read_only_count'], 'SCHREIBGESCHÜTZT'],
                ['KI-Guardrail: Entwurfs-Modus (ReadAndPropose)', $audit['ai_propose_count'], 'FREIGABEPFLICHT'],
                ['KI-Guardrail: Autonom (AutonomousReadWrite)', $audit['ai_autonomous_count'], 'AUTONOM'],
                ['Kurzzeit-Speicher (30 Tage Auto-Pruning)', $audit['short_lived_prunable_count'], 'ÜBERWACHT'],
                ['Models mit Produktions-/Druck-Assets (90-Tage-Cleaner)', $audit['production_asset_models_count'], 'ÜBERWACHT'],
            ]
        );

        if (!empty($audit['violations'])) {
            $this->error('Folgende Architektur-Verstöße wurden festgestellt (SystemLog & Admin-Mail wurden ausgelöst):');
            foreach ($audit['violations'] as $violation) {
                $this->line("  - {$violation}");
            }
            return self::FAILURE;
        }

        if ($this->option('export-vvt')) {
            $blueprintService->generateGdprArt30VvtMarkdown(true);
            $this->info('Art. 30 DSGVO Verarbeitungsverzeichnis erfolgreich unter Abschlussberichte/System/Verzeichnis_Verarbeitungstaetigkeiten_Art30_DSGVO.md generiert!');
        }

        $this->info('Alle ' . $audit['total_models'] . ' Models erfüllen die Seelenfunke-Blueprint-Architektur zu 100 %!');
        return self::SUCCESS;
    }
}
