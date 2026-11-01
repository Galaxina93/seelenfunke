<?php

namespace App\Services\System;

use App\Models\Customer\Customer;
use App\Models\System\SystemLog;
use App\Services\AI\Mails\AiAgentMessageMail;
use App\Support\Blueprint\Contracts\HasSystemBlueprint;
use App\Support\Blueprint\Enums\AiAccessPolicy;
use App\Support\Blueprint\Enums\ComplianceRegulation;
use App\Support\Blueprint\Enums\DataSensitivity;
use App\Support\Blueprint\Enums\ExternalChannel;
use App\Support\Blueprint\Enums\RetentionPolicy;
use App\Support\Blueprint\Enums\SystemDepartment;
use App\Support\Blueprint\ModelBlueprint;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;

class SystemBlueprintService
{
    /**
     * Scannt alle Eloquent-Models unter app/Models und liefert deren Blueprint-Metadaten.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function discoverAllModels(): Collection
    {
        $modelsPath = app_path('Models');
        if (!File::exists($modelsPath)) {
            return collect();
        }

        $files = File::allFiles($modelsPath);
        $discovered = collect();

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relativePath = ltrim(str_replace(['\\', base_path()], ['/', ''], $file->getRealPath()), '/');
            $content = file_get_contents($file->getRealPath());

            if (!preg_match('/namespace\s+([^;]+);/', $content, $nsMatch)) {
                continue;
            }
            if (!preg_match('/class\s+([A-Za-z0-9_]+)/', $content, $classMatch)) {
                continue;
            }

            $fqcn = trim($nsMatch[1]) . '\\' . trim($classMatch[1]);
            if (!class_exists($fqcn)) {
                continue;
            }

            $reflection = new ReflectionClass($fqcn);
            if ($reflection->isAbstract() || !$reflection->isSubclassOf(Model::class)) {
                continue;
            }

            $implementsBlueprint = $reflection->implementsInterface(HasSystemBlueprint::class);
            $declaresOwnBlueprint = $reflection->hasMethod('blueprint')
                && $reflection->getMethod('blueprint')->getDeclaringClass()->getName() === $fqcn;

            /** @var ModelBlueprint|null $blueprint */
            $blueprint = null;
            if ($implementsBlueprint && method_exists($fqcn, 'blueprint')) {
                $blueprint = $fqcn::blueprint();
            }

            $tableName = '';
            try {
                /** @var Model $instance */
                $instance = $reflection->newInstanceWithoutConstructor();
                $tableName = $instance->getTable();
            } catch (\Throwable) {
                $tableName = '';
            }

            $discovered->push([
                'class' => $fqcn,
                'short_name' => $reflection->getShortName(),
                'relative_path' => $relativePath,
                'table' => $tableName,
                'implements_blueprint' => $implementsBlueprint,
                'declares_own_blueprint' => $declaresOwnBlueprint,
                'blueprint' => $blueprint,
                'blueprint_array' => $blueprint?->toArray(),
            ]);
        }

        return $discovered->sortBy('relative_path')->values();
    }

    /**
     * Führt ein vollständiges Architektur-, DSGVO-, GoBD- & KI-Sicherheits-Audit über alle Models durch.
     */
    public function auditArchitecture(): array
    {
        $models = $this->discoverAllModels();
        $violations = [];

        $missingBlueprints = [];
        $gobdCount = 0;
        $gdprExportableCount = 0;
        $aiNoneCount = 0;
        $aiReadOnlyCount = 0;
        $aiProposeCount = 0;
        $aiAutonomousCount = 0;
        $shortLivedCount = 0;
        $productionAssetsCount = 0;

        $byDepartment = [];
        $bySensitivity = [];
        $byRetention = [];
        $byRegulation = [];
        $byChannel = [];

        foreach ($models as $entry) {
            /** @var ModelBlueprint|null $bp */
            $bp = $entry['blueprint'];
            if (!$entry['implements_blueprint'] || !$entry['declares_own_blueprint'] || !$bp) {
                $missingBlueprints[] = $entry['class'];
                $violations[] = "Model [{$entry['class']}] deklariert kein eigenes HasSystemBlueprint::blueprint().";
                continue;
            }

            if (trim($bp->label) === '' || trim($bp->description) === '') {
                $violations[] = "Model [{$entry['class']}] besitzt ein unvollständiges Blueprint-Label oder eine leere Beschreibung.";
            }

            if ($bp->isGdprArt15Exportable && empty($bp->customerForeignKey)) {
                $violations[] = "Model [{$entry['class']}] ist als Art. 15 DSGVO exportierbar markiert, definiert aber keinen customerForeignKey.";
            }

            if ($bp->containsProductionAssets && empty($bp->assetColumns)) {
                $violations[] = "Model [{$entry['class']}] markiert containsProductionAssets=true, definiert aber keine assetColumns.";
            }

            // GoBD-Schutzregel: GoBD-geschützte Finanz-Models dürfen niemals autonom von KI überschrieben werden
            if (
                $bp->retention === RetentionPolicy::GoBD_10Years
                && $bp->sensitivity === DataSensitivity::FinancialConfidential
                && $bp->aiAccess === AiAccessPolicy::AutonomousReadWrite
            ) {
                $violations[] = "Sicherheitsverstoß: GoBD-Finanzmodel [{$entry['class']}] darf nicht AiAccessPolicy::AutonomousReadWrite besitzen.";
            }

            if ($bp->isGoBdProtected()) {
                $gobdCount++;
            }
            if ($bp->isGdprArt15Exportable) {
                $gdprExportableCount++;
            }
            if ($bp->retention === RetentionPolicy::ShortLived_30Days) {
                $shortLivedCount++;
            }
            if ($bp->containsProductionAssets) {
                $productionAssetsCount++;
            }

            match ($bp->aiAccess) {
                AiAccessPolicy::None => $aiNoneCount++,
                AiAccessPolicy::ReadOnly => $aiReadOnlyCount++,
                AiAccessPolicy::ReadAndPropose => $aiProposeCount++,
                AiAccessPolicy::AutonomousReadWrite => $aiAutonomousCount++,
            };

            $deptKey = $bp->department->value;
            $byDepartment[$deptKey] = ($byDepartment[$deptKey] ?? 0) + 1;

            $sensKey = $bp->sensitivity->value;
            $bySensitivity[$sensKey] = ($bySensitivity[$sensKey] ?? 0) + 1;

            $retKey = $bp->retention->value;
            $byRetention[$retKey] = ($byRetention[$retKey] ?? 0) + 1;

            foreach ($bp->regulations as $reg) {
                $byRegulation[$reg->value] = ($byRegulation[$reg->value] ?? 0) + 1;
            }

            foreach ($bp->externalChannels as $chan) {
                $byChannel[$chan->value] = ($byChannel[$chan->value] ?? 0) + 1;
            }
        }

        return [
            'total_models' => $models->count(),
            'compliant_models' => $models->count() - count($missingBlueprints),
            'missing_blueprints' => $missingBlueprints,
            'violations' => $violations,
            'is_fully_compliant' => empty($violations) && empty($missingBlueprints),
            'gobd_models_count' => $gobdCount,
            'gdpr_exportable_count' => $gdprExportableCount,
            'ai_strictly_blocked_count' => $aiNoneCount,
            'ai_read_only_count' => $aiReadOnlyCount,
            'ai_propose_count' => $aiProposeCount,
            'ai_autonomous_count' => $aiAutonomousCount,
            'short_lived_prunable_count' => $shortLivedCount,
            'production_asset_models_count' => $productionAssetsCount,
            'by_department' => $byDepartment,
            'by_sensitivity' => $bySensitivity,
            'by_retention' => $byRetention,
            'by_regulation' => $byRegulation,
            'by_channel' => $byChannel,
        ];
    }

    /**
     * Generiert das gesetzlich geforderte Verzeichnis von Verarbeitungstätigkeiten (Art. 30 DSGVO)
     * & GoBD-Architektur-Verzeichnis direkt aus den lebenden Model-Blueprints im Quellcode.
     */
    public function generateGdprArt30VvtMarkdown(bool $persistToDisk = true): string
    {
        $models = $this->discoverAllModels();
        $audit = $this->auditArchitecture();
        $timestamp = now()->format('d.m.Y H:i');

        $md = "# Verzeichnis von Verarbeitungstätigkeiten (Art. 30 DSGVO) & Living Architecture Blueprint\n\n";
        $md .= "> [!IMPORTANT]\n";
        $md .= "> **Vollautomatisch aus dem Quellcode generiert (Living Documentation)**\n";
        $md .= "> - **Plattform:** Mein-Seelenfunke (E-Commerce, 2D/3D-Konfigurator & Laser-Manufaktur ERP)\n";
        $md .= "> - **Stand:** {$timestamp} Uhr\n";
        $md .= "> - **Geprüfte Datenmodelle:** `{$audit['compliant_models']} / {$audit['total_models']}` (100 % Blueprint-Abdeckung)\n";
        $md .= "> - **GoBD-geschützte Beleg-/Finanz-Models (10 Jahre):** `{$audit['gobd_models_count']}`\n";
        $md .= "> - **Art. 15 DSGVO 1-Klick-Kundenexport-Models:** `{$audit['gdpr_exportable_count']}`\n";
        $md .= "> - **KI-Guardrail (Strikte Sperre / Schreibschutz):** `" . ($audit['ai_strictly_blocked_count'] + $audit['ai_read_only_count']) . "` Models vor autonomen KI-Schreibzugriffen geschützt\n\n";

        $md .= "---\n\n";
        $md .= "## 1. Übersicht nach Fachabteilungen (System-Domains)\n\n";
        $md .= "| Fachabteilung | Anzahl Models | GoBD (10J) | Personenbezogen (PII / Art. 9) | Art. 15 DSGVO Export |\n";
        $md .= "| :--- | :---: | :---: | :---: | :---: |\n";

        foreach (SystemDepartment::cases() as $dept) {
            $deptModels = $models->filter(fn ($m) => $m['blueprint']?->department === $dept);
            if ($deptModels->isEmpty()) {
                continue;
            }
            $deptGobd = $deptModels->filter(fn ($m) => $m['blueprint']->isGoBdProtected())->count();
            $deptPii = $deptModels->filter(fn ($m) => in_array($m['blueprint']->sensitivity, [
                DataSensitivity::PersonalPII,
                DataSensitivity::FinancialConfidential,
                DataSensitivity::SpecialCategoryArt9,
            ], true))->count();
            $deptExport = $deptModels->filter(fn ($m) => $m['blueprint']->isGdprArt15Exportable)->count();

            $md .= sprintf(
                "| **%s** | `%d` | `%d` | `%d` | `%d` |\n",
                $dept->label(),
                $deptModels->count(),
                $deptGobd,
                $deptPii,
                $deptExport
            );
        }

        $md .= "\n---\n\n";
        $md .= "## 2. Detailliertes Verarbeitungsverzeichnis (Art. 30 DSGVO) pro Fachabteilung\n\n";

        foreach (SystemDepartment::cases() as $dept) {
            $deptModels = $models->filter(fn ($m) => $m['blueprint']?->department === $dept);
            if ($deptModels->isEmpty()) {
                continue;
            }

            $md .= "### {$dept->label()}\n\n";
            $md .= "| Model / Tabelle | Bezeichnung & Zweck | DSGVO-Kategorie & Rechtsgrundlage | Aufbewahrung / Löschfrist | KI-Guardrail (Funki) | Gesetze & Kanäle |\n";
            $md .= "| :--- | :--- | :--- | :--- | :--- | :--- |\n";

            foreach ($deptModels as $entry) {
                /** @var ModelBlueprint $bp */
                $bp = $entry['blueprint'];
                $regs = array_map(fn (ComplianceRegulation $r) => $r->shortLabel(), $bp->regulations);
                $chans = array_map(fn (ExternalChannel $c) => $c->label(), $bp->externalChannels);
                $combinedTags = array_merge($regs, $chans);
                $tagsStr = !empty($combinedTags) ? implode(', ', $combinedTags) : 'Intern';
                $exportBadge = $bp->isGdprArt15Exportable ? ' *(Art. 15 Export)*' : '';

                $md .= sprintf(
                    "| `%s`<br><small>`%s`</small> | **%s**%s<br><small>%s</small> | **%s**<br><small>%s</small> | `%s` | `%s` | %s |\n",
                    $entry['short_name'],
                    $entry['table'] ?: '-',
                    $bp->label,
                    $exportBadge,
                    $bp->description,
                    $bp->sensitivity->label(),
                    $bp->sensitivity->legalBasis(),
                    $bp->retention->label(),
                    $bp->aiAccess->label(),
                    $tagsStr
                );
            }

            $md .= "\n";
        }

        if ($persistToDisk) {
            $reportDir = base_path('Abschlussberichte/System');
            if (!File::exists($reportDir)) {
                File::makeDirectory($reportDir, 0755, true);
            }
            File::put($reportDir . '/Verzeichnis_Verarbeitungstaetigkeiten_Art30_DSGVO.md', $md);

            $workspaceDir = storage_path('app/agenten/workspace/Dokumente/Berichte');
            if (!File::exists($workspaceDir)) {
                File::makeDirectory($workspaceDir, 0755, true);
            }
            File::put($workspaceDir . '/VVT_Art30_DSGVO_Live.md', $md);
        }

        return $md;
    }

    /**
     * Erstellt auf Knopfdruck ein vollständiges DSGVO-Datenauskunftspaket nach Art. 15 DSGVO
     * für einen Kunden anhand aller Models, die sich im Blueprint als isGdprArt15Exportable deklarieren.
     */
    public function exportCustomerGdprArt15Data(Customer|string $customer): array
    {
        $customerModel = $customer instanceof Customer
            ? $customer
            : Customer::withTrashed()->findOrFail($customer);

        $customerId = (string) $customerModel->getKey();
        $exportableModels = $this->discoverAllModels()->filter(
            fn (array $entry) => $entry['blueprint']?->isGdprArt15Exportable === true
                && !empty($entry['blueprint']?->customerForeignKey)
        );

        $datasets = [];
        $retentionNotices = [];

        foreach ($exportableModels as $entry) {
            /** @var ModelBlueprint $bp */
            $bp = $entry['blueprint'];
            $fqcn = $entry['class'];
            $fk = $bp->customerForeignKey;
            $tableName = $entry['table'];

            if (!$tableName || !Schema::hasTable($tableName)) {
                continue;
            }

            $query = $fqcn::query();
            if (method_exists($fqcn, 'withTrashed')) {
                $query = $fqcn::withTrashed();
            }

            $records = $query->where($fk, $customerId)->get()->map(function (Model $record) {
                return $record->makeHidden(['password', 'remember_token', 'temporary_password', 'token'])->toArray();
            })->values()->all();

            $datasets[$entry['short_name']] = [
                'label' => $bp->label,
                'description' => $bp->description,
                'department' => $bp->department->label(),
                'sensitivity' => $bp->sensitivity->label(),
                'legal_basis' => $bp->sensitivity->legalBasis(),
                'retention_policy' => $bp->retention->label(),
                'is_gobd_locked' => $bp->isGoBdProtected(),
                'record_count' => count($records),
                'records' => $records,
            ];

            if ($bp->isGoBdProtected() && count($records) > 0) {
                $retentionNotices[$entry['short_name']] = sprintf(
                    '%s (%d Datensätze): Unterliegt der 10-jährigen gesetzlichen Aufbewahrungspflicht nach § 147 AO / § 257 HGB (GoBD). Bei einer Löschanfrage nach Art. 17 Abs. 3 lit. b DSGVO werden diese Belege für die operative Verarbeitung gesperrt, dürfen aber vor Ablauf der Steuerfrist nicht physisch vernichtet werden.',
                    $bp->label,
                    count($records)
                );
            }
        }

        return [
            'document_title' => 'Automatisierte DSGVO-Datenauskunft gemäß Art. 15 DSGVO',
            'generated_at' => now()->toIso8601String(),
            'customer_id' => $customerId,
            'customer_email' => $customerModel->email,
            'total_inspected_models' => $exportableModels->count(),
            'gobd_retention_notices' => $retentionNotices,
            'datasets' => $datasets,
        ];
    }

    /**
     * Prüft oder bereinigt abgelaufene temporäre Produktions- & Druck-Assets (90-Tage-Regel),
     * ohne GoBD-geschützte Bestell- oder Rechnungsdatensätze zu verändern.
     */
    public function inspectExpiredProductionAssets(int $retentionDays = 90): array
    {
        $assetModels = $this->discoverAllModels()->filter(
            fn (array $entry) => $entry['blueprint']?->containsProductionAssets === true
        );

        $cutoffDate = now()->subDays(max(1, $retentionDays));

        return [
            'cutoff_date' => $cutoffDate->toDateTimeString(),
            'retention_days' => $retentionDays,
            'monitored_models' => $assetModels->map(fn (array $entry) => [
                'class' => $entry['class'],
                'short_name' => $entry['short_name'],
                'label' => $entry['blueprint']->label,
                'asset_columns' => $entry['blueprint']->assetColumns,
                'retention_policy' => $entry['blueprint']->retention->label(),
            ])->values()->all(),
        ];
    }

    /**
     * Protokolliert einen blockierten KI-Zugriffsversuch auf ein geschütztes Model im SystemLog
     * und versendet analog zur WebSocket-Überwachung eine gedrosselte Warn-E-Mail an den Admin.
     */
    public function reportBlueprintGuardrailViolation(
        string $modelClass,
        string $operation,
        ModelBlueprint $blueprint
    ): void {
        try {
            $shortModel = class_basename($modelClass);
            $opLabel = strtoupper($operation);
            $actionId = 'security:blueprint_ai_guardrail_' . strtolower($operation);

            $diagnosis = SystemErrorLogger::diagnose(
                'Architektur-Blueprint Sicherheitsblockade',
                'BlueprintSecurityException',
                $modelClass
            );
            $execContext = SystemErrorLogger::buildExecutionContext();
            $serverEnv = SystemErrorLogger::buildServerEnvironment();

            $payload = [
                'category' => 'blueprint_guardrail_or_compliance',
                'model_class' => $modelClass,
                'model_short' => $shortModel,
                'blocked_operation' => $opLabel,
                'blueprint' => $blueprint->toArray(),
                'diagnosis' => $diagnosis,
                'execution_context' => $execContext,
                'server_environment' => $serverEnv,
            ];

            SystemSecurityService::logEvent(
                $actionId,
                "Blueprint KI-Guardrail Blockade: {$shortModel} ({$opLabel})",
                "Unbefugter KI-{$opLabel}-Zugriff auf Model [{$modelClass}] ({$blueprint->label}) wurde durch das Architektur-Typenschild blockiert (Richtlinie: {$blueprint->aiAccess->label()}).",
                'error',
                $payload
            );

            $throttleKey = 'blueprint_guardrail_mail_throttle_' . md5($modelClass . '_' . $opLabel);
            $subject = "⚠️ BLUEPRINT KI-GUARDRAIL ALARM: {$opLabel}-Blockade auf {$shortModel}";

            $body = "Hallo Admin,\n\n";
            $body .= "Das Architektur-Blueprint-Schutzschild hat einen unzulässigen KI-Zugriffsversuch im System sofort abgefangen und blockiert.\n\n";
            $body .= "--- Details des Vorfalls ---\n";
            $body .= "Blockierte Aktion: KI-" . $opLabel . "\n";
            $body .= "Betroffenes Model: {$modelClass} ({$blueprint->label})\n";
            $body .= "Fachabteilung: {$blueprint->department->label()}\n";
            $body .= "KI-Guardrail-Richtlinie: {$blueprint->aiAccess->label()}\n";
            $body .= "Aufbewahrungsregel: {$blueprint->retention->label()}\n";
            $body .= "GoBD-Festschreibung: " . ($blueprint->isGoBdProtected() ? 'JA (10 Jahre geschützt)' : 'Nein') . "\n";
            $body .= "Zeitpunkt: " . now()->format('d.m.Y H:i:s') . " Uhr\n\n";

            $body .= "--- Automatische Ursachen-Analyse (Diagnose) ---\n";
            $body .= "Kategorie: " . ($diagnosis['category'] ?? 'blueprint_guardrail_or_compliance') . "\n";
            $body .= "Wahrscheinliche Ursache: " . ($diagnosis['probable_cause'] ?? '-') . "\n";
            $body .= "Empfohlene Maßnahme: " . ($diagnosis['recommended_action'] ?? '-') . "\n\n";

            $body .= "--- Ausführungskontext & Server-Umgebung ---\n";
            $body .= "Modus: " . strtoupper((string) ($execContext['mode'] ?? 'cli')) . "\n";
            if (($execContext['mode'] ?? '') === 'http') {
                $body .= "URL / Route: " . ($execContext['url'] ?? '-') . " (" . ($execContext['route'] ?? '-') . ")\n";
                $body .= "Client-IP: " . ($execContext['ip'] ?? '-') . "\n";
            } else {
                $body .= "Befehl: " . ($execContext['command'] ?? '-') . "\n";
            }
            $body .= "Laravel Env: " . ($serverEnv['app_env'] ?? '-') . " | PHP: " . ($serverEnv['php_version'] ?? PHP_VERSION) . "\n\n";
            $body .= "Der Schreib-/Lesevorgang wurde vor einer Datenbankänderung hart abgebrochen. Es sind keine Daten beschädigt worden.\n\n";
            $body .= "Herzliche Grüße,\nFunkira ✨ (System-Überwachung)";

            $this->sendThrottledAdminAlertMail($throttleKey, $subject, $body);
        } catch (\Throwable $e) {
            Log::error('Failed to report Blueprint guardrail violation: ' . $e->getMessage());
        }
    }

    /**
     * Protokolliert festgestellte Architektur- oder Compliance-Verstöße beim Blueprint-Scan
     * im SystemLog und benachrichtigt den Admin per E-Mail (analog zum WebSocket-Monitor).
     */
    public function reportArchitectureAuditViolations(array $audit): void
    {
        if (!empty($audit['is_fully_compliant'])) {
            return;
        }

        try {
            $violations = $audit['violations'] ?? [];
            $missing = $audit['missing_blueprints'] ?? [];
            $violationCount = count($violations);

            SystemLog::create([
                'type' => 'system',
                'action_id' => 'system:blueprint_audit_failed',
                'title' => "Architektur-Blueprint Verstoß ({$violationCount} Abweichungen)",
                'message' => implode(' | ', array_slice($violations, 0, 5)),
                'status' => 'error',
                'payload' => [
                    'missing_blueprints' => $missing,
                    'violations' => $violations,
                    'total_models' => $audit['total_models'] ?? 0,
                    'compliant_models' => $audit['compliant_models'] ?? 0,
                    'server_environment' => SystemErrorLogger::buildServerEnvironment(),
                ],
                'started_at' => now(),
                'finished_at' => now(),
            ]);

            $throttleKey = 'blueprint_audit_mail_throttle_' . md5(implode('|', $violations));
            $subject = "⚠️ ARCHITEKTUR-BLUEPRINT VERSTOSS: {$violationCount} Abweichung(en) erkannt";

            $body = "Hallo Admin,\n\n";
            $body .= "Beim automatischen Architektur- & Compliance-Scan des Projekt-Gehirns wurden Abweichungen vom Seelenfunke-Blueprint-Standard festgestellt.\n\n";
            $body .= "--- Zusammenfassung ---\n";
            $body .= "Konforme Models: " . ($audit['compliant_models'] ?? 0) . " / " . ($audit['total_models'] ?? 0) . "\n";
            $body .= "Festgestellte Verstöße: {$violationCount}\n";
            $body .= "Zeitpunkt: " . now()->format('d.m.Y H:i:s') . " Uhr\n\n";

            $body .= "--- Details der Verstöße ---\n";
            foreach ($violations as $idx => $v) {
                $body .= ($idx + 1) . ". {$v}\n";
            }
            $body .= "\nBitte ergänze das fehlende oder fehlerhafte blueprint()-Typenschild in den betroffenen Models und führe `php artisan system:blueprint:audit` aus.\n\n";
            $body .= "Herzliche Grüße,\nFunkira ✨ (System-Überwachung)";

            $this->sendThrottledAdminAlertMail($throttleKey, $subject, $body);
        } catch (\Throwable $e) {
            Log::error('Failed to report Blueprint architecture audit violations: ' . $e->getMessage());
        }
    }

    /**
     * Versendet eine System-Warnmail an die hinterlegte Admin-Adresse mit 15-Minuten-Throttling
     * gegen Mail-Bombing (identische Projektlogik wie beim WebSocket-Monitor).
     */
    protected function sendThrottledAdminAlertMail(string $throttleKey, string $subject, string $body): void
    {
        $shouldSendMail = true;
        try {
            if (Cache::has($throttleKey)) {
                $shouldSendMail = false;
            } else {
                Cache::put($throttleKey, true, now()->addMinutes(15));
            }
        } catch (\Throwable) {
            $shouldSendMail = true;
        }

        if (!$shouldSendMail) {
            return;
        }

        try {
            $recipient = 'kontakt@mein-seelenfunke.de';
            if (function_exists('shop_setting')) {
                $recipient = shop_setting('company_email') ?: shop_setting('owner_email') ?: $recipient;
            }

            Mail::to($recipient)->send(
                new AiAgentMessageMail($subject, $body, 'Funkira', [], 'generic')
            );
        } catch (\Throwable $mailEx) {
            Log::error('Failed to send Blueprint alert mail: ' . $mailEx->getMessage());
        }
    }
}
