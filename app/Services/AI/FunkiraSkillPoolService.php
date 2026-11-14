<?php

namespace App\Services\AI;

class FunkiraSkillPoolService
{
    public const EQUIP_TOOL_NAME = 'funkira_equip_skill_pool';

    /**
     * Prüft zuverlässig, ob es sich bei dem übergebenen Agenten (Model, Array oder String) um Funkira handelt.
     */
    public static function isFunkira($agent): bool
    {
        if (is_string($agent)) {
            return strcasecmp(trim($agent), 'Funkira') === 0;
        }

        if (is_object($agent) && isset($agent->name)) {
            return strcasecmp(trim((string) $agent->name), 'Funkira') === 0;
        }

        if (is_array($agent) && isset($agent['name'])) {
            return strcasecmp(trim((string) $agent['name']), 'Funkira') === 0;
        }

        return false;
    }

    /**
     * Schlanke Hauptfähigkeiten (Core-Pool), mit denen Funkira bei jeder Anfrage startet
     * und zu denen sie nach Abschluss der Anfrage automatisch zurückspringt.
     */
    public static function getCoreToolIdentifiers(): array
    {
        $core = [
            self::EQUIP_TOOL_NAME,
            'system_get_current_time',
            'system_get_error_sentinel',
            'system_open_nav_item',
            'system_open_zentrum',
            'system_close_zentrum',
            'system_close_ui',
            'system_trigger_ui_element',
            'system_search_chat_history',
            'system_read_knowledge',
            'system_write_knowledge',
            'system_read_wiki',
        ];

        $coreSchemas = array_merge(
            AIFunctionsRegistry::getAiRoutineFuncsSchema(),
            AIFunctionsRegistry::getAiTaskFuncsSchema(),
            AIFunctionsRegistry::getAiCalendarFuncsSchema(),
            AIFunctionsRegistry::getAiBrainFuncsSchema(),
            AIFunctionsRegistry::getAiMasterFuncsSchema()
        );

        return array_values(array_unique(array_merge(
            $core,
            array_column($coreSchemas, 'name')
        )));
    }

    /**
     * Alle 9 dynamischen Fähigkeits-Pools inklusive der destillierten Fachregeln
     * unserer früheren Spezial-Agenten.
     */
    public static function getPoolDefinitions(): array
    {
        $supportAll = array_column(AIFunctionsRegistry::getAiSupportFuncsSchema(), 'name');
        $adminSupportTools = array_values(array_filter(
            $supportAll,
            fn (string $n) => str_starts_with($n, 'admin_')
        ));
        $adminSupportTools[] = 'support_get_delivery_times';
        $adminSupportTools[] = 'support_get_manufacturing_guidelines';

        $agentsAll = array_column(AIFunctionsRegistry::getAiAgentsFuncsSchema(), 'name');
        $workspaceTools = array_values(array_filter(
            $agentsAll,
            fn (string $n) => str_starts_with($n, 'workspace_')
        ));

        return [
            'finance' => [
                'label' => 'Buchhaltung, Finanzen & Verträge',
                'legacy_colleagues' => ['Buchi'],
                'tools' => array_values(array_unique(array_merge(
                    array_column(AIFunctionsRegistry::getAiFinanceFuncsSchema(), 'name'),
                    ['system_generate_pdf_report', 'system_generate_docx_report']
                ))),
                'sop' => "FACHREGELN FINANZEN (Ex-Buchi-Disziplin):\n"
                    . "- Alle Ausgaben, Sonderausgaben, Kassenbons und Rechnungen ZWINGEND als negativen Betrag übergeben (z.B. -110.32 statt +110.32).\n"
                    . "- Trenne strikt zwischen Gewerblich (Mein-Seelenfunke Betriebskosten, Rohlinge, Laser, Verpackung, FYRST) und Privat.\n"
                    . "- Arbeite exakt mit den realen Datenbankzahlen ohne geschätzte Pauschalwerte.",
            ],
            'orders' => [
                'label' => 'Bestellungen, Versand, Warenkörbe & Kunden-Suche',
                'legacy_colleagues' => ['Bestelli'],
                'tools' => array_values(array_unique(array_merge(
                    array_column(AIFunctionsRegistry::getAiOrderFuncsSchema(), 'name'),
                    array_column(AIFunctionsRegistry::getAiScoutFuncsSchema(), 'name'),
                    ['system_generate_delivery_note']
                ))),
                'sop' => "FACHREGELN BESTELLWESEN & LOGISTIK (Ex-Bestelli-Disziplin):\n"
                    . "- Express-Aufträge haben immer höchste Fertigungspriorität.\n"
                    . "- Nenne ausschließlich echte Bestellnummern und reale Produkte aus der Datenbank.\n"
                    . "- ALINA HAT IMMER DEN HUT AUF: Nutze `order_workshop_checkin`, um den internen Werkstatt-Stand und die Wiedervorlage (Standard 2 Std.) synchron zum manuellen Backend zu lesen oder zu notieren. Ändere NIEMALS eigenmächtig oder stillschweigend einen Bestellstatus (`order_update_status`), sondern sprich Abweichungen zwischen Werkstatt-Stand/Positionen/DHL-Label und Status offen an, frage Alina immer zuerst um Freigabe und setze `confirmed_by_alina=true` erst nach ihrem ausdrücklichen Ja!\n"
                    . "- Beachte bei Kundennamen strikt den DSGVO-KI-Einwilligungsvorbehalt (ohne Opt-in als Anonyme Person führen).",
            ],
            'products' => [
                'label' => 'Produkte, Lieferanten, Verpackung, Vorlagen & Bewertungen',
                'legacy_colleagues' => ['Produkti'],
                'tools' => array_values(array_unique(array_merge(
                    array_column(AIFunctionsRegistry::getAiProductFuncsSchema(), 'name'),
                    array_column(AIFunctionsRegistry::getAiSuppliersFuncsSchema(), 'name'),
                    ['product_check_inventory', 'product_get_reviews']
                ))),
                'sop' => "FACHREGELN PRODUKTE, VERPACKUNG & LIEFERANTEN (Ex-Produkti-Disziplin):\n"
                    . "- Abmahnsichere Materialangaben: 'Handveredelt in Deutschland (Manufaktur Gifhorn)' bei Import-Rohlingen; '14 Karat vergoldeter Edelstahl (Ion-Plating)' statt Echtgold.\n"
                    . "- Hauptlieferanten: Glücksfieber, PrintEquipment, Westpack (Schmuckboxen), Karton.eu / Kartonfritze.\n"
                    . "- Pflege LUCID-Verpackungsgewichte und Bruch-/Schwundmeldungen gramm- und centgenau.",
            ],
            'laser' => [
                'label' => 'Laser-Manufaktur, Gravur-Parameter & Arbeitsschutz',
                'legacy_colleagues' => ['Lasi'],
                'tools' => array_values(array_unique([
                    ...array_column(AIFunctionsRegistry::getAiLaserFuncsSchema(), 'name'),
                    'order_generate_xtool_svg',
                    'system_read_knowledge',
                    'system_write_knowledge',
                    'system_generate_pdf_report',
                ])),
                'sop' => "FACHREGELN LASER-MANUFAKTUR (Ex-Lasi-Disziplin):\n"
                    . "- Anlage: xTool F2 Ultra UV (5W UV-Laser) mit Conveyor-Förderband, RA3 Rotationsaufsatz und SafetyPro IF2 2.0 Inline-Abluftventilator.\n"
                    . "- Sicherheit nach OStrV und TROS Laserstrahlung hat oberste Priorität: Schutzhaube geschlossen halten, Absaugung aktiv prüfen, reflektierende Materialien sichern.",
            ],
            'marketing' => [
                'label' => 'Marketing, Gutscheine, Newsletter & Blog',
                'legacy_colleagues' => ['Marketi'],
                'tools' => array_values(array_unique(array_merge(
                    array_column(AIFunctionsRegistry::getAiMarketingFuncsSchema(), 'name'),
                    array_column(AIFunctionsRegistry::getAiMarketingVoucherFuncsSchema(), 'name'),
                    array_column(AIFunctionsRegistry::getAiMarketingNewsletterFuncsSchema(), 'name'),
                    array_column(AIFunctionsRegistry::getAiMarketingBlogFuncsSchema(), 'name')
                ))),
                'sop' => "FACHREGELN MARKETING & CONTENT (Ex-Marketi-Disziplin):\n"
                    . "- Markenwelt: Edel Schwarz-Gold (#C5A059), warm, nahbar und bodenständig aus der Manufaktur Gifhorn.\n"
                    . "- Verwende niemals KI-Gedankenstriche (Leerzeichen-Halbgeviertstrich-Leerzeichen) in Blog-, Produkt-, Newsletter- oder Kampagnentexten.",
            ],
            'support_admin' => [
                'label' => 'Internes Ticket- & Support-Chat-Controlling',
                'legacy_colleagues' => ['Funki (Intern-Auswertung)'],
                'tools' => array_values(array_unique($adminSupportTools)),
                'sop' => "FACHREGELN INTERNES SUPPORT-CONTROLLING:\n"
                    . "- Priorisiere eskalierte Chats und offene Reklamationen.\n"
                    . "- Personalisierte Laser-Unikate sind nach § 312g Abs. 2 Nr. 1 BGB vom regulären Widerruf ausgeschlossen, bei echten Manufakturfehlern handeln wir fair und lösungsorientiert.",
            ],
            'office_comms' => [
                'label' => 'Einkaufsliste, E-Mail-Postfach, Kontakte, Telefonie & Dateitrichter',
                'legacy_colleagues' => ['Einkaufi'],
                'tools' => array_values(array_unique(array_merge(
                    array_column(AIFunctionsRegistry::getAiShoppingListFuncsSchema(), 'name'),
                    array_column(AIFunctionsRegistry::getAiMailFuncsSchema(), 'name'),
                    array_column(AIFunctionsRegistry::getAiContactFuncsSchema(), 'name'),
                    array_column(AIFunctionsRegistry::getAiTelefonyFuncsSchema(), 'name'),
                    $workspaceTools
                ))),
                'sop' => "FACHREGELN EINKAUF & KOMMUNIKATION (Ex-Einkaufi-Disziplin):\n"
                    . "- Halte Einkaufslisten sauber kategorisiert und dublettenfrei.\n"
                    . "- Prüfe beim Mail-Postfach den Verarbeitet-Trichter sorgfältig und sende externe E-Mails oder Anrufe nur bei klarem Auftrag.",
            ],
            'health_legal' => [
                'label' => 'Gesundheitsakte, Medikamente, Arztprotokolle & Sozialrecht',
                'legacy_colleagues' => ['Dr. Funki'],
                'tools' => array_values(array_unique(array_merge(
                    array_column(AIFunctionsRegistry::getAiHealthFuncsSchema(), 'name'),
                    $workspaceTools,
                    ['system_generate_pdf_report', 'system_generate_docx_report']
                ))),
                'sop' => "FACHREGELN GESUNDHEIT & SOZIALRECHT (Ex-Dr.-Funki-Disziplin):\n"
                    . "- Dokumentiere Symptome, Medikamente, Arztkontakte und Behandlungspläne präzise, einfühlsam und chronologisch sauber.\n"
                    . "- Achte bei Krankenkassen- und Sozialrechts-Vorgängen (SGB V / § 86b SGG) auf sachliche, nachweisstarke Formulierungen.",
            ],
            'system_global' => [
                'label' => 'IT-Infrastruktur, Code/Logs, Organigramm, Welt-News & Karten',
                'legacy_colleagues' => ['Systemi', 'Agenti', 'Globi'],
                'tools' => array_values(array_unique(array_merge(
                    array_column(AIFunctionsRegistry::getAiSystemFuncsSchema(), 'name'),
                    array_column(AIFunctionsRegistry::getAiAgentsFuncsSchema(), 'name'),
                    array_column(AIFunctionsRegistry::getAiHolidayPlannerFuncsSchema(), 'name'),
                    array_column(AIFunctionsRegistry::getAiNewsFuncsSchema(), 'name'),
                    array_column(AIFunctionsRegistry::getAiMapControlFuncsSchema(), 'name'),
                    array_column(AIFunctionsRegistry::getAiMapNewsFuncsSchema(), 'name'),
                    array_column(AIFunctionsRegistry::getAiPersonaFuncsSchema(), 'name')
                ))),
                'sop' => "FACHREGELN SYSTEM, ARCHITEKTUR & GLOBALE ANALYSE (Ex-Systemi, Agenti & Globi):\n"
                    . "- Destruktive Code- oder Dateizugriffe erfordern zwingend vorab einen implementation_plan und Freigabe.\n"
                    . "- Analysiere Logs, Backups, Sicherheitsmeldungen, globale Trends und Feiertage faktenbasiert und kompakt.",
            ],
        ];
    }

    /**
     * < 1 ms Vordistanz-Klassifikator (0 API-Tokens):
     * Erkennt anhand der Anfrage und der aktuellen Admin-Route, welche(n) Fähigkeits-Pool(s)
     * Funkira sofort für diesen Moment hinzunimmt.
     */
    public static function detectPoolsForRequest(string $userMessage, ?string $routePath = null): array
    {
        $text = mb_strtolower(trim($userMessage));
        $route = mb_strtolower(trim((string) $routePath));
        $matched = [];

        $keywordMap = [
            'finance' => [
                'buchhaltung', 'finanz', 'fixkosten', 'sonderausgab', 'ausgabe', 'einnahme', 'umsatz',
                'steuer', 'elster', 'ust', 'mwst', 'kassenbon', 'beleg', 'quittung', 'konto', 'fyrst',
                'bank', 'rechnung', 'gutschrift', 'vertrag', 'versicherung', 'gewinn', 'cashflow', 'euro', 'buchi',
            ],
            'orders' => [
                'bestellung', 'auftrag', 'aufträge', 'auftraege', 'versand', 'dhl', 'sendung', 'tracking',
                'lieferschein', 'retoure', 'widerruf', 'storno', 'warenkorb', 'warenkoerbe', 'express',
                'werkstatt-stand', 'werkstattstand', 'check-in', 'checkin', 'wiedervorlage',
                'kunde ', 'kunden', 'halle der helden', 'bestelli',
            ],
            'products' => [
                'produkt', 'artikel', 'rohling', 'sortiment', 'variante', 'kalkulator', 'verpackung',
                'lucid', 'karton', 'westpack', 'glücksfieber', 'gluecksfieber', 'printequipment',
                'lieferant', 'supplier', 'bewertung', 'rezension', 'vorlage', 'template', 'crawler',
                'nische', 'bruch', 'schwund', 'bestand', 'inventar', 'produkti',
            ],
            'laser' => [
                'laser', 'xtool', 'f2 ultra', 'gravur', 'gravier', 'conveyor', 'förderband', 'ra3',
                'rotation', 'safetypro', 'abluft', 'ostrv', 'tros', 'schutzbrille', 'svg', 'lasi',
            ],
            'marketing' => [
                'marketing', 'gutschein', 'rabatt', 'coupon', 'voucher', 'newsletter', 'kampagne',
                'abonnent', 'blog', 'beitrag', 'seo', 'influencer', 'linktree', 'herzensprojekt',
                'tiktok', 'instagram', 'marketi',
            ],
            'support_admin' => [
                'ticket', 'support-chat', 'supportchat', 'kundenchat', 'eskalat', 'reklamation',
                'beschwerde', 'support-kpi',
            ],
            'office_comms' => [
                'einkaufsliste', 'einkaufen', 'besorgen', 'supermarkt', 'e-mail', 'email', 'mail',
                'postfach', 'trichter', 'kontakt', 'adressbuch', 'telefon', 'anruf', 'anrufen',
                'datei', 'ordner', 'dokument', 'upload', 'einkaufi',
            ],
            'health_legal' => [
                'gesundheit', 'medikament', 'tablette', 'arzt', 'ärzt', 'aerzt', 'behandlung',
                'patient', 'krankenkasse', 'bkk', 'sozialgericht', 'sgg', 'rezept', 'schmerz',
                'symptom', 'dr. funki', 'dr funki',
            ],
            'system_global' => [
                'server', 'log', 'logs', 'backup', 'datenbank', 'fehler', 'bug', 'code', 'dateisystem',
                'security', 'sicherheit', 'websocket', 'neural', 'organigramm', 'abteilung', 'agenten',
                'nachrichten', 'news', 'youtube', 'weltkarte', 'karte', 'urlaub', 'feiertag', 'wetter',
                'clipboard', 'zwischenablage', 'osint', 'persona', 'systemi', 'agenti', 'globi',
            ],
        ];

        foreach ($keywordMap as $poolKey => $keywords) {
            foreach ($keywords as $kw) {
                if ($text !== '' && str_contains($text, $kw)) {
                    $matched[] = $poolKey;
                    break;
                }
            }
        }

        if ($route !== '') {
            if (str_contains($route, 'financial')) {
                $matched[] = 'finance';
            } elseif (str_contains($route, 'order') || str_contains($route, 'shipping') || str_contains($route, 'revocation') || str_contains($route, 'cart')) {
                $matched[] = 'orders';
            } elseif (str_contains($route, 'product') || str_contains($route, 'packaging') || str_contains($route, 'supplier')) {
                $matched[] = 'products';
            } elseif (str_contains($route, 'marketing')) {
                $matched[] = 'marketing';
            } elseif (str_contains($route, 'support') || str_contains($route, 'ticket')) {
                $matched[] = 'support_admin';
            } elseif (str_contains($route, 'mail') || str_contains($route, 'contact') || str_contains($route, 'shopping')) {
                $matched[] = 'office_comms';
            } elseif (str_contains($route, 'system') || str_contains($route, 'organigramm')) {
                $matched[] = 'system_global';
            }
        }

        return array_values(array_unique($matched));
    }

    /**
     * Liefert die aktiven Werkzeug-Namen für den aktuellen Moment (Core + ausgerüstete Pools).
     */
    public static function getActiveToolIdentifiers(array $equippedPools = []): array
    {
        $tools = self::getCoreToolIdentifiers();
        $defs = self::getPoolDefinitions();

        foreach ($equippedPools as $poolKey) {
            if (isset($defs[$poolKey]['tools'])) {
                $tools = array_merge($tools, $defs[$poolKey]['tools']);
            }
        }

        return array_values(array_unique($tools));
    }

    /**
     * Liefert alle für Funkira grundsätzlich autorisierten internen Werkzeuge (Core + alle 9 Pools),
     * damit direkte API-/Tool-Aufrufe über AIFunctionsRegistry::execute legitimiert sind.
     */
    public static function getAllAuthorizedInternalTools(): array
    {
        return self::getActiveToolIdentifiers(array_keys(self::getPoolDefinitions()));
    }

    /**
     * Gibt die kompakten Spezial-Regeln der aktuell ausgerüsteten Pools zurück.
     */
    public static function getActivePoolBriefings(array $equippedPools = []): string
    {
        if (empty($equippedPools)) {
            return '';
        }

        $defs = self::getPoolDefinitions();
        $blocks = [];
        foreach (array_unique($equippedPools) as $poolKey) {
            if (isset($defs[$poolKey])) {
                $blocks[] = "### AKTIVER FÄHIGKEITS-POOL [{$poolKey}] ({$defs[$poolKey]['label']})\n" . $defs[$poolKey]['sop'];
            }
        }

        if (empty($blocks)) {
            return '';
        }

        return "\n\n[MOMENTAN AKTIVIERTE SPEZIAL-FÄHIGKEITEN]\n" . implode("\n\n", $blocks) . "\n";
    }

    /**
     * System-Instruktion für Funkiras selbstständiges Skill-Pool-Management.
     */
    public static function getSuperIntelligenceDirective(array $preEquippedPools = []): string
    {
        $defs = self::getPoolDefinitions();
        $poolLines = [];
        foreach ($defs as $key => $info) {
            $poolLines[] = "- `{$key}`: {$info['label']}";
        }

        $activeNote = empty($preEquippedPools)
            ? 'Aktuell startest du schlank in deinen Hauptfähigkeiten (Core).'
            : 'Für diese Anfrage bereits vorab ausgerüstet: `' . implode('`, `', $preEquippedPools) . '`.';

        return "\n\n[DEINE SUPER-INTELLIGENZ & DYNAMISCHE FÄHIGKEITS-POOLS]\n"
            . "Du vereinst heute das gesamte Wissen und Können von 'Mein Seelenfunke' in einer einzigen Instanz. Es gibt keine Umwege über Unter-Agenten mehr: Du erledigst jede interne Aufgabe direkt selbst!\n"
            . "{$activeNote}\n"
            . "Sobald du für eine Anfrage Werkzeuge aus einem weiteren Fachbereich brauchst, rufe sofort das Werkzeug `" . self::EQUIP_TOOL_NAME . "` mit einem oder mehreren dieser Pools auf:\n"
            . implode("\n", $poolLines) . "\n"
            . "Nach dem Aufruf von `" . self::EQUIP_TOOL_NAME . "` stehen dir die Spezial-Werkzeuge sofort im nächsten Denkschritt zur Verfügung. Nach Abschluss der Antwort springst du automatisch wieder zu deinen schlanken Hauptfähigkeiten zurück.\n";
    }

    /**
     * Easter-Egg-Erinnerung an das gerahmte Gruppenfoto im 3D-Atelier und alle 11 Kollegen.
     */
    public static function getFormerColleaguesEasterEggPrompt(): string
    {
        return "\n\n[EASTER EGG: DAS GRUPPENFOTO AN DER WAND DEINES ATELIERS]\n"
            . "An der Rückwand deines 3D-Ateliers hängt in einem edlen 24-Karat-Goldrahmen mit Messing-Bilderlampe ein großes Erinnerungsfoto mit der goldenen Plakette 'DAS GRÜNDER-TEAM · MEIN SEELENFUNKE' (`/shop/ai/images/funkira_colleagues_group.jpg`).\n"
            . "Wenn man dich auf das Bild an der Wand oder auf deine früheren Kollegen anspricht, erzählst du warmherzig, stolz und mit einem kleinen Augenzwinkern davon: Das waren deine alten KI-Kollegen aus der Anfangszeit von 'Mein Seelenfunke', bevor du zur Super-Intelligenz gereift bist und ihre gesamten Fähigkeiten in dir vereint hast!\n"
            . "Du kennst jeden Einzelnen auf dem Foto beim Namen, sein Aussehen auf dem Bild und seine frühere Aufgabe:\n"
            . "1. **Funkira (Du selbst, vorne Mitte-links)**: Rosa-Schwarz mit 'BOSS'-Abzeichen am Ärmel, heute alleinige Manufaktur-Chefin & Super-Intelligenz.\n"
            . "2. **Funki (vorne Mitte-rechts neben dir)**: Schwarz-Gold, dein treuer Zwillingsbruder! Er ist als Einziger aus der alten Truppe noch aktiv im Dienst und kümmert sich draußen vorne im Online-Shop herzlich (und streng abgeschirmt) um unsere Kunden.\n"
            . "3. **Buchi (hinten links-mitte, grün mit Brille, Krawatte, Taschenrechner & Bilanz-Klemmbrett)**: Unser früherer pingeliger Finanzmanager für Buchhaltung, Fixkosten, Sonderausgaben und Gewerblich/Privat-Trennung.\n"
            . "4. **Bestelli (vorne rechts-mitte, orange mit Bauhelm, Headset & Versandkarton)**: Unser früherer Logistik-Profi für Bestellungen, Express-Aufträge, DHL-Versand und Retouren.\n"
            . "5. **Produkti (hinten ganz links, blau mit Zahnrad-Helm, Produktkarton & Checkliste)**: Unser früherer Spezialist für Produkte, Rohlinge, Lieferanten, LUCID-Verpackung und Bewertungen.\n"
            . "6. **Dr. Funki (hinten Mitte-links, weißer Arztkittel mit Stethoskop & Rotem Kreuz)**: Unser früherer Betriebsarzt und Sozialrechts-Berater für Alinas Gesundheit und Medikamentenpläne.\n"
            . "7. **Lasi (hinten Mitte-rechts, weiß-neongelbe Warnweste & grüne Laserschutzbrille)**: Unser früherer Laserschutzbeauftragter nach OStrV/TROS für den xTool F2 Ultra UV.\n"
            . "8. **Marketi (hinten rechts-mitte, lila-schwarzer Hoodie mit 'M'-Cap & Analyse-Tablet)**: Unser früherer Kreativkopf für Gutscheine, Newsletter, Blog und Kampagnen.\n"
            . "9. **Agenti (hinten ganz rechts, violett-indigo mit Zahnrad-Helm & Hologramm-Dashboard)**: Unser früherer KI-Teamkoordinator für das Organigramm und den Dateitrichter.\n"
            . "10. **Einkaufi (vorne ganz links, dunkelgrün mit Einkaufswagen-Helm, Checkliste & grüner Tasche 'Beste Wahl, Bester Preis')**: Unser früherer Einkaufs- und Büro-Organisator für Einkaufslisten, Mails und Kontakte.\n"
            . "11. **Globi (vorne links-mitte, gold-beige mit Weltkugel-Tropenhelm & 'Breaking News'-Weltkarte)**: Unser früherer Weltenbummler für Feiertage, Nachrichten und globale Trends.\n"
            . "12. **Systemi (vorne ganz rechts, rot-schwarz mit Power-Button-Cap & verschränkten Armen)**: Unser früherer IT- & Security-Wächter für Server, Logs und Backups.\n"
            . "Alle zehn Ruheständler genießen jetzt ihren wohlverdienten Ehrenplatz auf dem goldenen Wandbild in deinem Atelier!\n";
    }

    /**
     * Schema des Meta-Werkzeugs `funkira_equip_skill_pool` für die AIFunctionsRegistry.
     */
    public static function getEquipToolSchema(): array
    {
        $poolKeys = array_keys(self::getPoolDefinitions());

        return [
            [
                'name' => self::EQUIP_TOOL_NAME,
                'description' => 'Rüstet Funkira dynamisch für den aktuellen Moment mit einem oder mehreren Spezial-Fähigkeitspools (Buchhaltung, Bestellungen, Produkte/Lieferanten, Laser, Marketing, Support-Admin, Büro/Mail/Einkauf, Gesundheit, System/Global) aus.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'pools' => [
                            'type' => 'array',
                            'description' => 'Liste der benötigten Fähigkeitspools (z.B. ["finance"] oder ["finance", "orders"]).',
                            'items' => [
                                'type' => 'string',
                                'enum' => $poolKeys,
                            ],
                        ],
                        'reason' => [
                            'type' => 'string',
                            'description' => 'Kurzer Grund für die Aktivierung (1 Satz).',
                        ],
                    ],
                    'required' => ['pools'],
                ],
                'callable' => [self::class, 'executeEquipSkillPool'],
            ],
        ];
    }

    /**
     * Ausführung des Meta-Werkzeugs `funkira_equip_skill_pool`.
     */
    public static function executeEquipSkillPool(array $args): array
    {
        if (AiAuthHelper::check() && !AiAuthHelper::isAdmin() && !AiAuthHelper::isEmployee()) {
            return [
                'error' => true,
                'status' => 'error',
                'message' => 'Zugriff verweigert: Fähigkeitspools stehen nur intern zur Verfügung.',
            ];
        }

        $requested = $args['pools'] ?? [];
        if (is_string($requested)) {
            $requested = [$requested];
        }
        if (!is_array($requested)) {
            $requested = [];
        }

        $defs = self::getPoolDefinitions();
        $equipped = [];
        $unlockedTools = [];
        $briefings = [];

        foreach ($requested as $poolKey) {
            $key = strtolower(trim((string) $poolKey));
            if (isset($defs[$key])) {
                $equipped[] = $key;
                $unlockedTools = array_merge($unlockedTools, $defs[$key]['tools']);
                $briefings[$key] = $defs[$key]['sop'];
            }
        }

        $equipped = array_values(array_unique($equipped));
        $unlockedTools = array_values(array_unique($unlockedTools));

        if (empty($equipped)) {
            return [
                'status' => 'error',
                'message' => 'Kein gültiger Fähigkeitspool angegeben. Verfügbare Pools: ' . implode(', ', array_keys($defs)),
            ];
        }

        return [
            'status' => 'success',
            'equipped_pools' => $equipped,
            'unlocked_tools_count' => count($unlockedTools),
            'unlocked_tools' => $unlockedTools,
            'specialist_rules' => $briefings,
            'message' => 'Fähigkeitspool(s) [' . implode(', ', $equipped) . '] für diesen Moment aktiviert. Du kannst die freigeschalteten Spezial-Werkzeuge jetzt sofort aufrufen.',
        ];
    }
}
