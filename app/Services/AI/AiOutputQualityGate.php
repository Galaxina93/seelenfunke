<?php

namespace App\Services\AI;

use App\Models\Order\OrderOrder;

class AiOutputQualityGate
{
    /**
     * Reine Lese-Werkzeuge zählen NICHT als Beweis für eine durchgeführte Schreib-/Ausführungsaktion.
     */
    protected const READ_ONLY_TOOL_PREFIXES = [
        'system_get_',
        'system_read_',
        'system_search_',
        'brain_search',
        'workspace_find_',
        'workspace_get_',
        'order_get_',
        'product_get_',
        'product_check_',
        'product_analytics_',
        'product_loss_',
        'supplier_get_',
        'finance_list_',
        'finance_search_',
        'finance_get_',
        'finance_read_',
        'support_get_',
        'admin_search_',
        'calendar_list',
        'calendar_get',
        'task_list',
        'task_get',
        'mail_list',
        'mail_read',
        'mail_search',
        'contact_list',
        'contact_search',
        'routine_get',
        'routine_list',
        'health_list',
        'health_get',
        'ag_check_',
        'ag_list_',
        'funkira_equip_skill_pool',
        'procurement_evaluate_reorder',
    ];

    /**
     * Deterministische Nachkontrolle für alle internen Seelenfunke-KI-Ausgaben:
     * 1) Entfernt verbotene KI-Gedankenstriche (Baustein 1)
     * 2) Ersetzt abmahngefährdete Material- & Herkunftsbegriffe (Baustein 5)
     * 3) Prüft genannte Bestellnummern gegen die echte Datenbank
     */
    public static function sanitizeText(string $text): string
    {
        if ($text === '') {
            return '';
        }

        // 1. Verbotene KI-Gedankenstriche sauber durch natürliche Satzzeichen ersetzen
        $clean = preg_replace('/(\*\*[^*\n]+\*\*)\s*[\x{2013}\x{2014}]\s*/u', '$1: ', $text) ?? $text;
        $clean = preg_replace('/\s+[\x{2013}\x{2014}]\s+/u', ', ', $clean) ?? $clean;
        $clean = preg_replace('/,\s*,/u', ',', $clean) ?? $clean;

        // 2. Abmahnsichere Material- & Verpackungsbegriffe erzwingen (Baustein 5)
        $clean = preg_replace('/\bMade\s+in\s+Germany\b/iu', 'Handveredelt in Deutschland (Manufaktur Gifhorn)', $clean) ?? $clean;
        $clean = preg_replace('/(?<!kein\s)(?<!nicht\s)\bEchtgold\b/iu', '14 Karat vergoldeter Edelstahl (Ion-Plating)', $clean) ?? $clean;
        $clean = preg_replace('/(?<!kein\s)(?<!keinen\s)\bSamtbeutel(?:n|s)?\b/iu', 'Geschenkverpackung', $clean) ?? $clean;

        return trim($clean);
    }

    /**
     * Prüft, ob alle im Text erwähnten ORD-... Bestellnummern real in der Datenbank existieren.
     *
     * @return array{valid: bool, mentioned_order_numbers: array<int, string>, missing_order_numbers: array<int, string>}
     */
    public static function verifyOrderReferences(string $text): array
    {
        if (!preg_match_all('/\b(ORD-[A-Z0-9\-]{4,30})\b/u', $text, $matches)) {
            return [
                'valid' => true,
                'mentioned_order_numbers' => [],
                'missing_order_numbers' => [],
            ];
        }

        $mentioned = array_values(array_unique($matches[1]));
        $existing = OrderOrder::whereIn('order_number', $mentioned)
            ->pluck('order_number')
            ->all();

        $missing = array_values(array_diff($mentioned, $existing));

        return [
            'valid' => empty($missing),
            'mentioned_order_numbers' => $mentioned,
            'missing_order_numbers' => $missing,
        ];
    }

    /**
     * Prüft deterministisch in 0 Tokens, ob ein Werkzeug eine echte Schreib-/Ausführungsaktion war.
     */
    public static function isActionTool(string $toolName): bool
    {
        $normalized = strtolower(trim($toolName));
        if ($normalized === '') {
            return false;
        }

        foreach (self::READ_ONLY_TOOL_PREFIXES as $prefix) {
            if ($normalized === $prefix || str_starts_with($normalized, $prefix)) {
                return false;
            }
        }

        return true;
    }

    /**
     * 0-Token Anti-Bluff-Garantie ("No Tool = No Promise"):
     * Verhindert zu 100 %, dass ein Agent leere Versprechungen macht ("nehme ich in Arbeit",
     * "habe ich eingetragen"), obwohl gar kein ausführendes Werkzeug gelaufen ist.
     *
     * @param array<int, string> $executedTools
     * @return array{bluff_detected: bool, text: string, action_tools_executed: array<int, string>, suggest_antigravity_handoff: bool}
     */
    public static function inspectExecutionTruth(string $text, array $executedTools = []): array
    {
        $sanitized = self::sanitizeText($text);
        $actionTools = array_values(array_filter($executedTools, fn ($t) => is_string($t) && self::isActionTool($t)));

        if (!empty($actionTools)) {
            return [
                'bluff_detected' => false,
                'text' => $sanitized,
                'action_tools_executed' => $actionTools,
                'suggest_antigravity_handoff' => false,
            ];
        }

        // Muster für unbelegte Ausführungs- oder Hintergrund-Versprechen ohne echten Tool-Lauf
        $bluffPatterns = [
            '/\bnehme\s+(?:das|es|die\s+aufgabe|dies)\s+(?:gerne\s+|jetzt\s+|mal\s+|dann\s+|direkt\s+|sofort\s+|für\s+dich\s+)*in\s+arbeit\b/iu',
            '/\bin\s+arbeit\s+nehmen\b/iu',
            '/\bich\s+arbeite\s+(?:jetzt\s+|im\s+hintergrund\s+|weiter\s+)*daran\b/iu',
            '/\bkümmere\s+mich\s+(?:jetzt\s+|sofort\s+|später\s+|direkt\s+|drum|darum)\b/iu',
            '/\bhabe\s+(?:ich\s+|es\s+|das\s+)*(?:soeben\s+|gerade\s+|bereits\s+|für\s+dich\s+|direkt\s+)*(?:eingetragen|gespeichert|bestellt|angelegt|geändert|aktualisiert|gelöscht|gebucht|überwiesen|versendet|abgeschickt)\b/iu',
            '/\bwurde\s+(?:soeben\s+|erfolgreich\s+)*(?:von\s+mir\s+)*(?:eingetragen|bestellt|gebucht|ausgeführt|geändert|gespeichert)\b/iu',
            '/\bich\s+melde\s+mich\s+(?:gleich|später|sobald)\b/iu',
        ];

        $isBluff = false;
        foreach ($bluffPatterns as $pattern) {
            if (preg_match($pattern, $sanitized)) {
                $isBluff = true;
                break;
            }
        }

        if (!$isBluff) {
            return [
                'bluff_detected' => false,
                'text' => $sanitized,
                'action_tools_executed' => [],
                'suggest_antigravity_handoff' => false,
            ];
        }

        return [
            'bluff_detected' => true,
            'text' => 'Dafür steht mir im aktuellen Schritt kein passendes Ausführungswerkzeug zur Verfügung (0-Token Sicherheitsstopp statt leerem Versprechen). Soll ich diese Anforderung direkt als Entwicklungs-Ticket an Antigravity übergeben?',
            'action_tools_executed' => [],
            'suggest_antigravity_handoff' => true,
        ];
    }

    /**
     * Komplett-Gate nach einem internen Agenten-Lauf: bereinigt Text, prüft Referenzen und verhindert Bluffs.
     *
     * @param array<int, string> $executedTools
     * @return array{text: string, passed: bool, valid_orders: bool, missing_order_numbers: array<int, string>, bluff_detected: bool, suggest_antigravity_handoff: bool}
     */
    public static function inspectAndClean(string $text, array $executedTools = []): array
    {
        $truth = self::inspectExecutionTruth($text, $executedTools);
        $sanitized = $truth['text'];
        $orderCheck = self::verifyOrderReferences($sanitized);

        if (!$orderCheck['valid']) {
            foreach ($orderCheck['missing_order_numbers'] as $fakeOrder) {
                $sanitized = str_replace($fakeOrder, '[ungeprüfte Bestellreferenz]', $sanitized);
            }
        }

        return [
            'text' => $sanitized,
            'passed' => $orderCheck['valid'] && !$truth['bluff_detected'] && $sanitized !== '',
            'valid_orders' => $orderCheck['valid'],
            'missing_order_numbers' => $orderCheck['missing_order_numbers'],
            'bluff_detected' => $truth['bluff_detected'],
            'suggest_antigravity_handoff' => $truth['suggest_antigravity_handoff'],
        ];
    }
}
