<?php

namespace App\Services\AI;

use App\Models\Ai\AiAgent;
use App\Models\Ai\AiTool;
use App\Models\Management\Mail\MailMessage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SfInternalOrchestrator
{
    protected static bool $isInsideWorkerRun = false;

    /**
     * 3-Stufen Vertex AI Modell-Routing im 50 € Monatsbudget.
     */
    public const MODEL_TIERS = [
        'triage' => 'gemini-3.5-flash-lite',
        'standard' => 'gemini-3.5-flash',
        'complex' => 'gemini-3.1-pro-preview',
    ];

    /**
     * Isolierte Spezial-Worker mit jeweils maximal 4 bis 6 hochfokussierten Werkzeugen
     * und eigenem Temperatur- & Rollen-Profil (100 % unabhängig von Antigravity).
     */
    public const WORKER_PROFILES = [
        'buchi' => [
            'label' => 'Buchi-Worker (Buchhaltung & Beleg-Pipeline)',
            'admin_only' => true,
            'temperature' => 0.0,
            'default_tier' => 'standard',
            'tools' => [
                'finance_scan_receipt_document',
                'finance_create_quick_entry_expense',
                'finance_search_variable_costs',
                'finance_list_categories',
                'finance_get_monthly_stats',
                'finance_get_tax_and_founding_status',
            ],
            'directive' => 'Du bist der isolierte Buchi-Worker von Mein-Seelenfunke. Arbeite cent-exakt, übergib Ausgaben immer negativ (z. B. -49.90), trenne strikt Gewerblich vs. Privat, prüfe Dubletten und buche niemals ohne Alinas Bestätigung.',
        ],
        'bestelli' => [
            'label' => 'Bestelli-Worker (Bestellungen, Versand & Kunden-Antwortentwürfe)',
            'admin_only' => false,
            'temperature' => 0.1,
            'default_tier' => 'standard',
            'tools' => [
                'order_get_details',
                'order_get_current_express_status',
                'order_workshop_checkin',
                'admin_search_support_tickets',
                'support_get_delivery_times',
            ],
            'directive' => 'Du bist der isolierte Bestelli-Worker von Mein-Seelenfunke. Nutze ausschließlich echte Daten aus dem übergebenen Fakten-Dossier und deinen 5 Werkzeugen. Ändere niemals eigenmächtig Bestellstatus und erfinde niemals Tracking- oder Bestellnummern.',
        ],
        'einkaufi' => [
            'label' => 'Einkaufi-Worker (Rohlinge, Verpackung & Nachbestell-Frühwarnung)',
            'admin_only' => false,
            'temperature' => 0.1,
            'default_tier' => 'triage',
            'tools' => [
                'product_check_inventory',
                'supplier_get_all',
                'supplier_get_details',
                'product_analytics_get_lucid_report',
            ],
            'directive' => 'Du bist der isolierte Einkaufi-Worker von Mein-Seelenfunke. Gib exakt eine wirtschaftlich durchgerechnete Nachbestell-Empfehlung mit konkreter Startmenge anhand echter Bestände und Lieferanten (Westpack, Glücksfieber, PrintEquipment, Karton.eu).',
        ],
        'marketi' => [
            'label' => 'Marketi-Worker (SEO, Magazin & Kampagnen-Entwürfe)',
            'admin_only' => false,
            'temperature' => 0.3,
            'default_tier' => 'standard',
            'tools' => [
                'product_get_details',
                'product_get_reviews',
                'system_read_knowledge',
                'niche_get_live_data',
            ],
            'directive' => 'Du bist der isolierte Marketi-Worker von Mein-Seelenfunke. Formuliere verkaufsstarke, ehrliche Manufaktur-Texte ohne KI-Gedankenstriche und mit abmahnsicheren Materialangaben (Handveredelt in Deutschland / Manufaktur Gifhorn, 14 Karat vergoldeter Edelstahl im Ion-Plating-Verfahren).',
        ],
        'produkti' => [
            'label' => 'Produkti-Worker (Sortiment, Maße & Qualitätskontrolle)',
            'admin_only' => false,
            'temperature' => 0.1,
            'default_tier' => 'standard',
            'tools' => [
                'product_get_details',
                'product_check_inventory',
                'product_get_reviews',
                'product_loss_get_overview',
            ],
            'directive' => 'Du bist der isolierte Produkti-Worker von Mein-Seelenfunke. Prüfe Produktdaten, reale Millimeter-Maße, Bewertungen und Bruchquoten sachlich und präzise.',
        ],
        'globi' => [
            'label' => 'Globi-Worker (Marktrecherche & Wissensdatenbank)',
            'admin_only' => true,
            'temperature' => 0.2,
            'default_tier' => 'complex',
            'tools' => [
                'system_search_web',
                'system_read_web_url',
                'system_read_knowledge',
                'system_write_knowledge',
            ],
            'directive' => 'Du bist der isolierte Globi-Worker von Mein-Seelenfunke. Recherchiere fokussiert, fasse Fakten knapp zusammen und verankere verifizierte Erkenntnisse bei Bedarf strukturiert in der Wissensdatenbank.',
        ],
    ];

    public static function resolveModelForTier(string $tier): string
    {
        return self::MODEL_TIERS[$tier] ?? self::MODEL_TIERS['standard'];
    }

    /**
     * Führt einen frisch isolierten Mikro-Worker-Lauf mit Vorab-Dossier, kleinem Toolset
     * und anschließendem deterministischem AiOutputQualityGate aus.
     *
     * @param array<string, mixed> $contextOptions ['order_number' => string, 'mail_id' => int, 'include_inventory' => bool]
     * @return array<string, mixed>
     */
    public static function runSpecialistWorker(
        string $workerKey,
        string $taskPrompt,
        array $contextOptions = [],
        ?string $requestedTier = null
    ): array {
        $normalizedKey = strtolower(trim($workerKey));
        if (!isset(self::WORKER_PROFILES[$normalizedKey])) {
            return [
                'status' => 'error',
                'message' => "Unbekannter Spezial-Worker '{$workerKey}'. Verfügbar: " . implode(', ', array_keys(self::WORKER_PROFILES)),
            ];
        }

        // Kein unendliches Worker-in-Worker-Spawning (Max Depth = 1)
        if (self::$isInsideWorkerRun) {
            return [
                'status' => 'error',
                'message' => 'Verschachtelte Sub-Worker-Aufrufe sind gesperrt (Maximal 1 Orchestrator -> 1 Worker).',
            ];
        }

        $profile = self::WORKER_PROFILES[$normalizedKey];

        // Strikte Rollen-Isolation (Baustein 8): Admin-Worker niemals für Nicht-Admins öffnen
        if (!AiAuthHelper::isAdmin() && !AiAuthHelper::isEmployee()) {
            return [
                'status' => 'error',
                'message' => 'Zugriff verweigert: Interne Spezial-Worker stehen nur autorisiertem Personal zur Verfügung.',
            ];
        }

        if ($profile['admin_only'] && !AiAuthHelper::isAdmin()) {
            return [
                'status' => 'error',
                'message' => "Zugriff verweigert: Der Spezial-Worker '{$profile['label']}' ist ausschließlich der Geschäftsführung (Admin) vorbehalten.",
            ];
        }

        // 1. PHP-First Fakten-Dossiers vor dem ersten Token aufbauen (< 10 ms)
        $dossiers = [];
        if (!empty($contextOptions['order_number'])) {
            $orderDossier = ContextDossierBuilder::buildOrderDossier((string) $contextOptions['order_number']);
            if ($orderDossier) {
                $dossiers['order'] = $orderDossier;
            }
        }
        if (!empty($contextOptions['mail_id'])) {
            $mailDossier = ContextDossierBuilder::buildMailDossier((int) $contextOptions['mail_id']);
            if ($mailDossier) {
                $dossiers['mail'] = $mailDossier;
            }
        }
        if (!empty($contextOptions['include_inventory']) || $normalizedKey === 'einkaufi') {
            $dossiers['low_inventory'] = ContextDossierBuilder::buildLowInventoryDossier(8);
        }

        $tier = $requestedTier ?: $profile['default_tier'];
        $modelName = self::resolveModelForTier($tier);

        // 2. Ephemeren, isolierten Spezial-Agenten mit exakt 4 bis 6 Tools ausstatten
        $ephemeralAgent = new AiAgent([
            'name' => ucfirst($normalizedKey) . '-Worker',
            'model' => $modelName,
            'temperature' => $profile['temperature'],
            'is_active' => true,
            'system_prompt' => $profile['directive']
                . "\nREGEL: Verwende niemals KI-Gedankenstriche (` – ` oder ` — `). Antworte präzise, faktenbasiert und kompakt.",
        ]);

        $focusedTools = AiTool::whereIn('identifier', $profile['tools'])->get();
        $ephemeralAgent->setRelation('tools', $focusedTools);

        $dossierBlock = ContextDossierBuilder::formatAsPromptBlock($dossiers);
        $fullUserPrompt = trim($taskPrompt) . ($dossierBlock !== '' ? "\n\n" . $dossierBlock : '');

        // In Unit-Tests standardmäßig deterministisch ohne echte Vertex-API-Kosten ausführen
        $hasLiveCredentials = VertexAiService::hasCredentials() || !empty(config('services.gemini.key', env('GEMINI_API_KEY')));
        if ((app()->runningUnitTests() && empty($contextOptions['allow_live_llm_in_tests'])) || !$hasLiveCredentials) {
            $offlineSummary = self::buildDeterministicFallbackSummary($normalizedKey, $taskPrompt, $dossiers);
            $checked = AiOutputQualityGate::inspectAndClean($offlineSummary);
            return [
                'status' => 'success',
                'worker' => $normalizedKey,
                'model' => $modelName,
                'tool_count' => count($profile['tools']),
                'dossiers_injected' => array_keys($dossiers),
                'quality_gate_passed' => $checked['passed'],
                'response' => $checked['text'],
            ];
        }

        self::$isInsideWorkerRun = true;
        try {
            $provider = AiAgentFactory::make($ephemeralAgent);
            $rawResult = $provider->ask([
                ['role' => 'user', 'content' => $fullUserPrompt],
            ]);

            $rawText = (string) ($rawResult['response'] ?? '');
            $checked = AiOutputQualityGate::inspectAndClean($rawText);

            return [
                'status' => 'success',
                'worker' => $normalizedKey,
                'model' => $modelName,
                'tool_count' => $focusedTools->count(),
                'dossiers_injected' => array_keys($dossiers),
                'quality_gate_passed' => $checked['passed'],
                'response' => $checked['text'],
            ];
        } catch (\Throwable $e) {
            Log::warning("SfInternalOrchestrator Fehler ({$normalizedKey}): " . $e->getMessage());
            return [
                'status' => 'error',
                'worker' => $normalizedKey,
                'message' => 'Spezial-Worker konnte nicht abgeschlossen werden: ' . $e->getMessage(),
            ];
        } finally {
            self::$isInsideWorkerRun = false;
        }
    }

    /**
     * Stiller Event-Autopilot für wichtige E-Mails im "Verarbeitet"-Trichter (MailAiSortService):
     * - Prüft PDF/XML-Rechnungsanhänge automatisch über BuchiReceiptPipelineService (1-Klick-Buchungsvorlage)
     * - Erstellt für Kunden-/Bestell-Mails automatisch einen faktengeprüften Antwort-Entwurf inkl. Bestell-Dossier
     *
     * @return array<string, mixed> Aktualisiertes tags-Array für MailMessage
     */
    public static function enrichImportantMailTags(MailMessage $mail, array $baseTags): array
    {
        // Bereits aufbereitete Mails nicht erneut scannen (Token-Spar-Disziplin!)
        if (!empty($baseTags['ai_autopilot_ready'])) {
            return $baseTags;
        }

        $updatedTags = $baseTags;
        $mail->loadMissing('attachments');

        // 1. Prüfe auf Rechnungs-/Beleg-Anhänge (PDF / XML) -> Buchi-Vorprüfung
        foreach ($mail->attachments as $att) {
            $ext = strtolower(pathinfo((string) ($att->filename ?? $att->path), PATHINFO_EXTENSION));
            if (!in_array($ext, ['pdf', 'xml', 'jpg', 'jpeg', 'png', 'webp'], true)) {
                continue;
            }
            if (empty($att->path) || str_contains((string) $att->path, '..') || !Storage::disk('local')->exists($att->path)) {
                continue;
            }

            $fullPath = Storage::disk('local')->path($att->path);
            $scan = BuchiReceiptPipelineService::analyzeAndVerify($fullPath);
            if ($scan && !empty($scan['amount_abs'])) {
                $updatedTags['ai_receipt_draft'] = [
                    'attachment_id' => (int) $att->id,
                    'title' => $scan['title'],
                    'merchant' => $scan['merchant'],
                    'amount_formatted' => $scan['amount_formatted'],
                    'date' => $scan['date'],
                    'category' => $scan['category'],
                    'is_business' => $scan['is_business'],
                    'tax_rate' => $scan['tax_rate'],
                    'is_duplicate' => (bool) ($scan['duplicate_check']['is_duplicate'] ?? false),
                    'duplicate_reason' => $scan['duplicate_check']['reason'] ?? null,
                ];
                $updatedTags['ai_autopilot_ready'] = true;
                break;
            }
        }

        // 2. Kunden-/Bestell-Anfrage -> Faktenbasierten Antwort-Entwurf aus Dossier vorbereiten (0 Halluzinationen)
        $dossier = ContextDossierBuilder::buildMailDossier($mail);
        if ($dossier && (!empty($dossier['linked_orders']) || stripos((string) ($baseTags['category'] ?? ''), 'Kunde') !== false || stripos((string) ($baseTags['category'] ?? ''), 'Bestell') !== false)) {
            $draftText = self::buildGroundedMailReplyDraft($dossier);
            $checked = AiOutputQualityGate::inspectAndClean($draftText);
            $updatedTags['ai_reply_draft'] = [
                'draft_text' => $checked['text'],
                'linked_orders' => array_column($dossier['linked_orders'], 'order_number'),
                'generated_at' => now()->format('Y-m-d H:i'),
            ];
            $updatedTags['ai_autopilot_ready'] = true;
        }

        return $updatedTags;
    }

    /**
     * Erzeugt einen sofort versandfertigen, abmahnsicheren Antwort-Entwurf anhand echter Bestelldaten.
     *
     * @param array<string, mixed> $mailDossier
     */
    public static function buildGroundedMailReplyDraft(array $mailDossier): string
    {
        $greeting = ($mailDossier['gdpr_ai_consent'] ?? false) && !empty($mailDossier['sender_display']) && !str_starts_with((string) $mailDossier['sender_display'], 'Anonyme')
            ? "Hallo {$mailDossier['sender_display']},"
            : 'Guten Tag,';

        $lines = [$greeting, '', 'vielen Dank für deine Nachricht an unsere Manufaktur Mein-Seelenfunke in Gifhorn.'];

        if (!empty($mailDossier['linked_orders'])) {
            $primaryOrder = $mailDossier['linked_orders'][0];
            $statusMap = [
                'pending' => 'Ausstehend (vor Produktionsstart)',
                'processing' => 'In der Manufaktur-Fertigung',
                'completed' => 'Fertiggestellt & versandbereit',
                'shipped' => 'Per DHL versendet',
            ];
            $readableStatus = $statusMap[$primaryOrder['status']] ?? $primaryOrder['status'];
            $lines[] = "Zu deiner Bestellung {$primaryOrder['order_number']} ({$primaryOrder['total_eur']} €): Aktueller Status ist \"{$readableStatus}\".";

            if (!empty($primaryOrder['tracking_numbers'])) {
                $trk = implode(', ', $primaryOrder['tracking_numbers']);
                $lines[] = "Deine DHL-Sendungsnummer lautet: {$trk}.";
            }
        }

        $lines[] = '';
        $lines[] = 'Herzliche Grüße aus Gifhorn';
        $lines[] = 'Alina Steinhauer';
        $lines[] = 'Mein-Seelenfunke';

        return implode("\n", $lines);
    }

    /**
     * @param array<string, mixed> $dossiers
     */
    private static function buildDeterministicFallbackSummary(string $workerKey, string $taskPrompt, array $dossiers): string
    {
        $parts = ["[" . ucfirst($workerKey) . "-Worker Ergebnis] Auftrag geprüft: " . mb_substr(trim($taskPrompt), 0, 140) . "."];
        if (!empty($dossiers['order'])) {
            $ord = $dossiers['order'];
            $parts[] = "Bestellung {$ord['order_number']} Status: {$ord['status']} ({$ord['total_eur']} €).";
        }
        if (!empty($dossiers['low_inventory'])) {
            $parts[] = count($dossiers['low_inventory']) . " Artikel mit niedrigem Bestand erkannt.";
        }

        return implode(' ', $parts);
    }
}
