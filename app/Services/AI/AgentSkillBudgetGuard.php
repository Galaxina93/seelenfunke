<?php

namespace App\Services\AI;

use App\Models\Ai\AiAgent;
use Illuminate\Support\Str;

class AgentSkillBudgetGuard
{
    /**
     * Verbindliche Werkzeug-Obergrenzen je Rolle (Kontext-Schutz gegen Token-Aufblähung).
     */
    public const MAX_SPECIALIST_ROLE_TOOLS = 35;
    public const MAX_FUNKIRA_CORE_TOOLS = 56;
    public const MAX_SUPPORT_AIRGAP_TOOLS = 25;
    public const ESTIMATED_TOKENS_PER_TOOL_SCHEMA = 95;

    /**
     * Unveränderlicher Air-Gap-Werkzeugkatalog für den öffentlichen Frontend-Support-Agenten "Funki".
     * Funki darf NIEMALS durch Antigravity, Rollen-UI oder Skill-Eskalation neue Fähigkeiten erhalten!
     */
    public const IMMUTABLE_FUNKI_TOOLS = [
        'support_get_my_tickets',
        'support_get_website_links',
        'support_analyze_current_cart',
        'support_get_delivery_times',
        'support_check_returns_policy',
        'support_create_claim_ticket',
        'support_modify_pending_order',
        'support_get_customer_orders',
        'support_get_order_status',
        'support_get_ticket_status',
        'support_get_order_details',
        'support_get_tracking_link',
        'support_get_gamification_stats',
        'support_get_customer_full_profile',
        'support_get_product_info',
        'support_mark_needs_employee',
        'support_resolve_chat',
        'support_get_invoice_link',
        'support_get_manufacturing_guidelines',
        'support_get_cart_total',
        'support_penalize_offtopic',
        'support_get_legal_documents',
        'system_get_current_time',
    ];

    public static function isFunkiAgent(mixed $agentOrName): bool
    {
        if ($agentOrName instanceof AiAgent) {
            return strcasecmp(trim((string) $agentOrName->name), 'Funki') === 0;
        }
        if (is_string($agentOrName)) {
            return strcasecmp(trim($agentOrName), 'Funki') === 0;
        }
        if (is_array($agentOrName)) {
            return strcasecmp(trim((string) ($agentOrName['name'] ?? '')), 'Funki') === 0;
        }

        return false;
    }

    public static function isSupporterRole(mixed $roleOrName): bool
    {
        if ($roleOrName instanceof \App\Models\Ai\AiRole) {
            return strcasecmp(trim((string) $roleOrName->name), 'Supporter') === 0;
        }
        if (is_string($roleOrName)) {
            return strcasecmp(trim($roleOrName), 'Supporter') === 0;
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    public static function getImmutableFunkiToolIdentifiers(): array
    {
        return self::IMMUTABLE_FUNKI_TOOLS;
    }

    /**
     * Prüft zu 100 % deterministisch in 0 Tokens, wie viele Werkzeuge ein Agent bereits besitzt,
     * wie hoch seine Schema-Kontextlast ist und wie Antigravity eine neue Fähigkeit sauber einbauen soll,
     * ohne den Kontext des Agenten aufzublähen.
     *
     * @return array<string, mixed>
     */
    public static function evaluateAgentSkillBudget(?AiAgent $agent): array
    {
        if (!$agent) {
            return [
                'agent_name' => 'Funkira',
                'role_name' => 'Teamleiter',
                'department_name' => 'Leitung',
                'current_tool_count' => 0,
                'max_tool_cap' => self::MAX_FUNKIRA_CORE_TOOLS,
                'estimated_schema_tokens' => 0,
                'can_add_direct_tool' => true,
                'expansion_strategy' => 'skill_pool',
                'architecture_recommendation' => 'Neue Fähigkeit in passenden On-Demand Skill-Pool (FunkiraSkillPoolService) aufnehmen, damit Funkiras Core-Kontext unter 55 Tools bleibt.',
            ];
        }

        $agent->loadMissing(['role.tools', 'department']);
        $agentName = (string) $agent->name;
        $roleName = (string) ($agent->role?->name ?? 'Unbekannt');
        $deptName = (string) ($agent->department?->name ?? 'Leitung');
        $isExec = FunkiraSkillPoolService::isExecutiveRoot($agentName);
        $isSupport = strtolower($agentName) === 'funki';

        $currentCount = $isExec
            ? count(FunkiraSkillPoolService::getCoreToolIdentifiers())
            : ($agent->role ? $agent->role->tools->count() : 0);

        $maxCap = $isExec
            ? self::MAX_FUNKIRA_CORE_TOOLS
            : ($isSupport ? self::MAX_SUPPORT_AIRGAP_TOOLS : self::MAX_SPECIALIST_ROLE_TOOLS);

        $estimatedTokens = $currentCount * self::ESTIMATED_TOKENS_PER_TOOL_SCHEMA;
        $canAddDirect = !$isExec && !$isSupport && ($currentCount < ($maxCap - 1));

        if ($isSupport) {
            $currentCount = count(self::IMMUTABLE_FUNKI_TOOLS);
            $maxCap = $currentCount;
            $estimatedTokens = $currentCount * self::ESTIMATED_TOKENS_PER_TOOL_SCHEMA;
            $strategy = 'immutable_frontend_support_locked';
            $recommendation = "ABSOLUTES SKILL-ERWEITERUNGS-VERBOT (FUNKI FROZEN {$currentCount}/{$maxCap} Tools): Funki ist der reine Frontend-Kundensupport und bleibt exakt so wie er ist. Antigravity darf Funki NIEMALS neue Fähigkeiten, Tools oder Rollenrechte geben!";
        } elseif ($isExec) {
            $strategy = 'on_demand_skill_pool';
            $recommendation = "KONTEXT-SCHUTZ FUNKIRA (Core aktuell {$currentCount}/{$maxCap} Tools, ~{$estimatedTokens} Schema-Tokens): NIEMALS blind in den Core-Pool legen, sondern in den passenden Abteilungs-Pool von FunkiraSkillPoolService einhängen.";
        } elseif ($canAddDirect) {
            $remaining = $maxCap - $currentCount;
            $strategy = 'direct_role_tool';
            $recommendation = "SKILL-BUDGET FREI ({$currentCount}/{$maxCap} Tools in Rolle '{$roleName}', ~{$estimatedTokens} Schema-Tokens, noch {$remaining} Slots frei): Kann als fokussiertes Direkt-Tool in {$deptName} ergänzt und in AiAgentSeeder für '{$roleName}' freigeschaltet werden.";
        } else {
            $strategy = 'consolidate_or_pool';
            $recommendation = "KONTEXT-LIMIT ERREICHT ({$currentCount}/{$maxCap} Tools in Rolle '{$roleName}', ~{$estimatedTokens} Schema-Tokens): KEIN weiteres Einzel-Tool anhängen! Bestehendes Abteilungs-Tool um einen action-Parameter erweitern oder selten genutzte Tools bündeln.";
        }

        return [
            'agent_name' => $agentName,
            'role_name' => $roleName,
            'department_name' => $deptName,
            'current_tool_count' => $currentCount,
            'max_tool_cap' => $maxCap,
            'estimated_schema_tokens' => $estimatedTokens,
            'can_add_direct_tool' => $canAddDirect,
            'expansion_strategy' => $strategy,
            'architecture_recommendation' => $recommendation,
        ];
    }

    /**
     * Erzeugt das kompakte, strukturierte Übergabe-Dossier an Antigravity inklusive Grund & Budget-Prüfung.
     *
     * @param array<string, mixed> $budget
     */
    public static function formatAntigravitySkillGapDossier(
        array $budget,
        string $missingCapability,
        string $whyBlocked,
        string $affectedArea = 'System'
    ): string {
        $cleanMissing = AiOutputQualityGate::sanitizeText(Str::limit(trim($missingCapability), 220));
        $cleanWhy = AiOutputQualityGate::sanitizeText(Str::limit(trim($whyBlocked), 280));
        $cleanArea = AiOutputQualityGate::sanitizeText(Str::limit(trim($affectedArea), 120));

        return implode("\n", [
            "AUTONOME WEITERLEITUNG & SKILL-ANFORDERUNG AN ANTIGRAVITY",
            "- Meldender Agent: {$budget['agent_name']} (Abt. {$budget['department_name']} · Rolle {$budget['role_name']})",
            "- Betroffener Bereich: {$cleanArea}",
            "- Fehlender Skill / Funktion: {$cleanMissing}",
            "- Warum der Agent es nicht selbst ausführen konnte: {$cleanWhy}",
            "- 0-Token Kontext- & Skill-Budget-Prüfung: {$budget['architecture_recommendation']}",
        ]);
    }
}
