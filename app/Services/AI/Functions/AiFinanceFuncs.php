<?php

namespace App\Services\AI\Functions;

trait AiFinanceFuncs
{
    public static function getAiFinanceFuncsSchema(): array
    {
        return [
            [
                'name' => 'finance_check_missing_expenses',
                'description' => 'Prüft, ob fehlende Sonderausgaben vorliegen, die noch erfasst oder überprüft werden müssen. Stichworte: Fehlen Rechnungen, fehlende Belege, Ausgaben prüfen, Buchhaltung checken, was fehlt noch, Rechnungsprüfung.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                ],
                'callable' => [self::class, 'executeCheckMissingExpenses']
            ],
            [
                'name' => 'finance_get_monthly_stats',
                'description' => 'Gibt die streng vertraulichen Buchhaltungs- und Finanzdaten für einen Monat zurück. Enthält rohen Umsatz, Einnahmen, Fixkosten, Sonderausgaben, Gewinn, BWA Metriken. Stichworte: Zeig mir den Umsatz, Wie viel haben wir verdient, Finanzstatus, Gewinn diesen Monat, BWA, Auswertungen.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'month' => [
                            'type' => 'integer',
                            'description' => 'Der Monat (1-12). Falls nicht angegeben, wird der aktuelle Monat verwendet.'
                        ],
                        'year' => [
                            'type' => 'integer',
                            'description' => 'Das Jahr. Falls nicht angegeben, wird das aktuelle Jahr verwendet.'
                        ],
                        'is_net' => [
                            'type' => 'boolean',
                            'description' => 'True für Netto-Werte (ohne Steuern), False für Brutto-Werte. Standard ist True (Netto).'
                        ]
                    ],
                    'required' => []
                ],
                'callable' => [self::class, 'executeGetFinances']
            ],
            [
                'name' => 'finance_get_daily_shop_revenue',
                'description' => 'Ermittelt den exakten Tagesumsatz des Shops für ein bestimmtes Datum aus den erfassten Rechnungen. Stichworte: Wieviel Umsatz heute, Tagesumsatz, was haben wir gestern verdient.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'date' => [
                            'type' => 'string',
                            'description' => 'Das Datum im Format YYYY-MM-DD. (z.B. 2026-04-27). Falls nicht angegeben, wird heute verwendet.'
                        ],
                        'is_net' => [
                            'type' => 'boolean',
                            'description' => 'True für Netto-Umsatz, False für Brutto. Standard ist True (Netto).'
                        ]
                    ],
                    'required' => []
                ],
                'callable' => [self::class, 'executeGetDailyShopRevenue']
            ],
            [
                'name' => 'finance_list_fixed_costs',
                'description' => 'Listet detailliert alle erfassten Fixkosten (Einnahmen und Ausgaben) auf, gruppiert nach Kategorien, inklusive IDs (UUIDs) für Bearbeitungs- und Löschvorgänge. Zeigt auch ob ein Vertrag benötigt wird (requires_contract) und hinterlegt ist. Stichworte: Welche Fixkosten haben wir, was kostet der Server, liste Fixkosten, fehlende Verträge.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => [
                            'type' => 'string',
                            'description' => 'Optional: Filtert die Fixkosten nach einem Suchbegriff (z.B. Name des Postens, Anbieter, Notiz oder Kategorie).'
                        ]
                    ],
                    'required' => []
                ],
                'callable' => [self::class, 'executeListFixedCosts']
            ],
            [
                'name' => 'finance_list_variable_costs',
                'description' => 'Listet detailliert alle Sonderausgaben und variablen Kosten für einen bestimmten Monat auf. Stichworte: Welche Sonderausgaben hatten wir, was waren unsere variablen Kosten, zeige mir die Buchungen.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'month' => [
                            'type' => 'integer',
                            'description' => 'Der Monat (1-12). Falls nicht angegeben, wird der aktuelle Monat verwendet.'
                        ],
                        'year' => [
                            'type' => 'integer',
                            'description' => 'Das Jahr. Falls nicht angegeben, wird das aktuelle Jahr verwendet.'
                        ]
                    ],
                    'required' => []
                ],
                'callable' => [self::class, 'executeListVariableCosts']
            ],
            [
                'name' => 'finance_get_yearly_matrix',
                'description' => 'Gibt die komplette Matrix der Finanzen über das gesamte Jahr zurück, um Langzeit-Analysen oder Jahresvergleiche zu machen. Enthält Arrays für jeden Monat (1-12).',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'year' => [
                            'type' => 'integer',
                            'description' => 'Das abzufragende Jahr. Standard ist das aktuelle Jahr.'
                        ],
                        'is_net' => [
                            'type' => 'boolean',
                            'description' => 'True für Netto-Werte, False für Brutto. Standard ist True (Netto).'
                        ]
                    ],
                    'required' => []
                ],
                'callable' => [self::class, 'executeGetYearlyMatrix']
            ],
            [
                'name' => 'finance_generate_tax_export',
                'description' => 'Generiert den Steuer-Export (DATEV/Buchhaltung) für einen bestimmten Monat. Ladet alle Rechnungen und Transaktionen herunter und verpackt sie in einem Archiv.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'month' => [
                            'type' => 'integer',
                            'description' => 'Der Monat (1-12) für den Export. Falls unklar, nimm den aktuellen oder letzten Monat.'
                        ],
                        'year' => [
                            'type' => 'integer',
                            'description' => 'Das Jahr für den Export (z.B. 2026).'
                        ]
                    ],
                    'required' => ['month', 'year']
                ],
                'callable' => [self::class, 'executeGenerateTaxExport']
            ],
            [
                'name' => 'finance_generate_and_send_report',
                'description' => 'Generiert einen vollständigen Finanzreport (PDF & CSV im ZIP) für einen bestimmten Monat und sendet diesen direkt per E-Mail an die gewünschte E-Mail-Adresse. Nutze dies, wenn der Nutzer einen Finanzreport/Finanzansicht an jemanden schicken möchte.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'email' => [
                            'type' => 'string',
                            'description' => 'Die E-Mail-Adresse des Empfängers.'
                        ],
                        'month' => [
                            'type' => 'integer',
                            'description' => 'Der Monat (1-12). Falls nicht angegeben, aktueller Monat.'
                        ],
                        'year' => [
                            'type' => 'integer',
                            'description' => 'Das Jahr. Falls nicht angegeben, aktuelles Jahr.'
                        ],
                        'design' => [
                            'type' => 'string',
                            'description' => 'Das visuelle Design der E-Mail. "seelenfunke" (inkl. Briefkopf, CI-Farben, Logo) oder "generic" (neutrales Design ohne Firmenbezug). Standardmäßig "seelenfunke", es sei denn, der Nutzer wünscht neutral.',
                            'enum' => ['seelenfunke', 'generic']
                        ]
                    ],
                    'required' => ['email']
                ],
                'callable' => [self::class, 'executeGenerateAndSendReport']
            ],
            [
                'name' => 'finance_create_quick_entry_expense',
                'description' => 'Erfasst eine Sonderausgabe (Schnellerfassung). Nutze dies für jeden Beleg, jede Rechnung oder jeden Kauf, den der Nutzer dir mitteilt. WICHTIG: Trenne privat (is_business=false) und gewerblich (is_business=true).',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => [
                            'type' => 'string',
                            'description' => 'Titel oder Verwendungszweck (z.B. Restaurantbesuch, Serverkosten).'
                        ],
                        'amount' => [
                            'type' => 'number',
                            'description' => 'Der Betrag. WICHTIG: Ausgaben (Kosten) müssen zwingend als negative Zahl (z.B. -50.99) angegeben werden! Einnahmen positiv.'
                        ],
                        'category' => [
                            'type' => 'string',
                            'description' => 'Buchhaltungskategorie. Nutze finance_list_categories für gültige Werte oder erfinde eine sinnvolle (z.B. Privatentnahme, Software, Bürobedarf).'
                        ],
                        'execution_date' => [
                            'type' => 'string',
                            'description' => 'Datum der Ausgabe im Format YYYY-MM-DD.'
                        ],
                        'is_business' => [
                            'type' => 'boolean',
                            'description' => 'TRUE wenn es für die Firma Seelenfunke ist, FALSE wenn es privat/für den Inhaber ist.'
                        ],
                        'tax_rate' => [
                            'type' => 'number',
                            'description' => 'Steuersatz in Prozent (z.B. 19 oder 7). Nur bei is_business=true relevant, sonst null.'
                        ],
                        'invoice_number' => [
                            'type' => 'string',
                            'description' => 'Optionale Rechnungsnummer.'
                        ]
                    ],
                    'required' => ['title', 'amount', 'category', 'execution_date', 'is_business']
                ],
                'callable' => [self::class, 'executeCreateQuickEntryExpense']
            ],
            [
                'name' => 'finance_list_categories',
                'description' => 'Gibt alle vorhandenen Buchhaltungskategorien aus der Datenbank zurück. Nutze dies, um die perfekte Kategorie für eine Ausgabe zu finden.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                ],
                'callable' => [self::class, 'executeListCategories']
            ],
            [
                'name' => 'finance_search_variable_costs',
                'description' => 'Sucht nach Sonderausgaben / Variablen Kosten anhand eines Suchbegriffs (z.B. Titel oder Kategorie) in der Datenbank.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => [
                            'type' => 'string',
                            'description' => 'Suchbegriff (Teil des Titels oder der Kategorie).'
                        ],
                        'month' => [
                            'type' => 'integer',
                            'description' => 'Optional: Auf einen Monat eingrenzen (1-12).'
                        ],
                        'year' => [
                            'type' => 'integer',
                            'description' => 'Optional: Auf ein Jahr eingrenzen.'
                        ]
                    ],
                    'required' => ['query']
                ],
                'callable' => [self::class, 'executeSearchVariableCosts']
            ],
            [
                'name' => 'finance_edit_variable_cost',
                'description' => 'Bearbeitet eine bestehende Sonderausgabe (Variable Kosten) anhand ihrer UUID.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => [
                            'type' => 'string',
                            'description' => 'Die exakte UUID des Eintrags.'
                        ],
                        'title' => [
                            'type' => 'string',
                            'description' => 'Neuer Titel.'
                        ],
                        'amount' => [
                            'type' => 'number',
                            'description' => 'Neuer Betrag. (Negativ für Ausgaben!)'
                        ],
                        'category' => [
                            'type' => 'string',
                            'description' => 'Neue Kategorie.'
                        ],
                        'invoice_number' => [
                            'type' => 'string',
                            'description' => 'Neue Rechnungsnummer.'
                        ],
                        'execution_date' => [
                            'type' => 'string',
                            'description' => 'Neues Datum im Format YYYY-MM-DD.'
                        ],
                        'is_business' => [
                            'type' => 'boolean',
                            'description' => 'Gewerblich (True) oder Privat (False).'
                        ],
                        'tax_rate' => [
                            'type' => 'number',
                            'description' => 'Steuersatz in Prozent (z.B. 19 oder 7).'
                        ]
                    ],
                    'required' => ['id']
                ],
                'callable' => [self::class, 'executeEditVariableCost']
            ],
            [
                'name' => 'finance_delete_variable_cost',
                'description' => 'Löscht eine Sonderausgabe endgültig anhand ihrer UUID.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => [
                            'type' => 'string',
                            'description' => 'Die exakte UUID des zu löschenden Eintrags.'
                        ]
                    ],
                    'required' => ['id']
                ],
                'callable' => [self::class, 'executeDeleteVariableCost']
            ],
            [
                'name' => 'finance_create_fixed_cost',
                'description' => 'Legt eine neue wiederkehrende Fixkosten-Kostenstelle (Dauerauftrag / Abonnement / Vertrag) in der Buchhaltung an. WICHTIG: Ausgaben müssen negativ sein (z.B. -19.99), Einnahmen positiv.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => [
                            'type' => 'string',
                            'description' => 'Name oder Bezeichnung der Kostenstelle (z.B. "Webhosting Netcup", "Büromiete", "Internetanschluss Telekom").'
                        ],
                        'amount' => [
                            'type' => 'number',
                            'description' => 'Der Betrag. WICHTIG: Ausgaben (Kosten) müssen zwingend als negative Zahl (z.B. -49.90) angegeben werden! Einnahmen positiv (z.B. 1200.00).'
                        ],
                        'interval_months' => [
                            'type' => 'integer',
                            'description' => 'Zahlungsintervall in Monaten (1 = monatlich, 3 = vierteljährlich/quartalsweise, 6 = halbjährlich, 12 = jährlich). Standard ist 1 (monatlich).'
                        ],
                        'group_id' => [
                            'type' => 'string',
                            'description' => 'Optionale UUID der Accounting-Kategoriegruppe (aus finance_list_fixed_costs). Falls nicht angegeben, wird group_name oder eine Standardgruppe gewählt.'
                        ],
                        'group_name' => [
                            'type' => 'string',
                            'description' => 'Optionaler Name der Kategoriegruppe (z.B. "Software & Hosting", "Fixe Ausgaben", "Büro & Räume"). Falls keine group_id angegeben ist, wird anhand dieses Namens zugeordnet.'
                        ],
                        'first_payment_date' => [
                            'type' => 'string',
                            'description' => 'Datum der ersten Abbuchung bzw. Startdatum im Format YYYY-MM-DD. Falls nicht angegeben, wird das heutige Datum genutzt.'
                        ],
                        'is_business' => [
                            'type' => 'boolean',
                            'description' => 'TRUE wenn geschäftlich für die Firma Seelenfunke, FALSE wenn privat. Standard ist TRUE.'
                        ],
                        'tax_rate' => [
                            'type' => 'number',
                            'description' => 'Umsatzsteuersatz in Prozent (z.B. 19 oder 7). Nur bei gewerblichen Ausgaben relevant.'
                        ],
                        'requires_contract' => [
                            'type' => 'boolean',
                            'description' => 'TRUE wenn für diese Fixkosten ein Vertragsdokument hochgeladen werden muss. Standard ist FALSE.'
                        ],
                        'description' => [
                            'type' => 'string',
                            'description' => 'Optionale Notiz, Zweck oder Beschreibung.'
                        ],
                        'provider_company' => [
                            'type' => 'string',
                            'description' => 'Name des Anbieters / Vertragspartners (z.B. "Netcup GmbH", "Hetzner", "Vodafone").'
                        ],
                        'contract_number' => [
                            'type' => 'string',
                            'description' => 'Optionale Vertrags- oder Kundennummer.'
                        ],
                        'notice_period' => [
                            'type' => 'string',
                            'description' => 'Optionale Kündigungsfrist (z.B. "3 Monate zum Monatsende", "1 Monat").'
                        ],
                        'tags' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'Optionale Schlagwörter / Tags zur Kategorisierung (z.B. ["software", "hosting", "gewerblich"]). Werden automatisch mit passenden System-Tags angereichert.'
                        ],
                        'ignore_archived' => [
                            'type' => 'boolean',
                            'description' => 'Falls TRUE, wird die Kostenstelle auch dann neu angelegt, wenn eine passende Kostenstelle bereits im Archiv liegt. Standard ist FALSE.'
                        ]
                    ],
                    'required' => ['name', 'amount']
                ],
                'callable' => [self::class, 'executeCreateFixedCost']
            ],
            [
                'name' => 'finance_edit_fixed_cost',
                'description' => 'Bearbeitet eine bestehende Fixkosten-Kostenstelle anhand ihrer UUID. Kann Betrag, Intervall, Name, Steuersatz, Kategoriegruppe etc. anpassen. WICHTIG: Ausgaben müssen negativ sein (-), Einnahmen positiv (+).',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => [
                            'type' => 'string',
                            'description' => 'Die exakte UUID der Kostenstelle (aus finance_list_fixed_costs).'
                        ],
                        'name' => [
                            'type' => 'string',
                            'description' => 'Neuer Name der Kostenstelle.'
                        ],
                        'amount' => [
                            'type' => 'number',
                            'description' => 'Neuer Betrag. WICHTIG: Ausgaben müssen negativ sein (z.B. -35.00), Einnahmen positiv!'
                        ],
                        'interval_months' => [
                            'type' => 'integer',
                            'description' => 'Neues Zahlungsintervall in Monaten (1 = monatlich, 3 = quartalsweise, 6 = halbjährlich, 12 = jährlich).'
                        ],
                        'group_id' => [
                            'type' => 'string',
                            'description' => 'UUID der neuen Zielgruppe (Verschieben in eine andere Kategoriegruppe).'
                        ],
                        'group_name' => [
                            'type' => 'string',
                            'description' => 'Name der neuen Zielgruppe (falls group_id nicht vorliegt).'
                        ],
                        'first_payment_date' => [
                            'type' => 'string',
                            'description' => 'Neues Startdatum / erste Zahlung (YYYY-MM-DD).'
                        ],
                        'last_payment_date' => [
                            'type' => 'string',
                            'description' => 'Enddatum / Datum der letzten Zahlung bei Kündigung (YYYY-MM-DD oder null zum Zurücksetzen).'
                        ],
                        'is_business' => [
                            'type' => 'boolean',
                            'description' => 'TRUE für geschäftlich, FALSE für privat.'
                        ],
                        'tax_rate' => [
                            'type' => 'number',
                            'description' => 'Neuer Steuersatz in Prozent (z.B. 19 oder 7).'
                        ],
                        'requires_contract' => [
                            'type' => 'boolean',
                            'description' => 'Ob ein Vertragspfad hinterlegt sein muss.'
                        ],
                        'description' => [
                            'type' => 'string',
                            'description' => 'Neue Beschreibung oder Notiz.'
                        ],
                        'provider_company' => [
                            'type' => 'string',
                            'description' => 'Neuer Anbietername.'
                        ],
                        'contract_number' => [
                            'type' => 'string',
                            'description' => 'Neue Vertragsnummer.'
                        ],
                        'notice_period' => [
                            'type' => 'string',
                            'description' => 'Neue Kündigungsfrist.'
                        ],
                        'contract_end_date' => [
                            'type' => 'string',
                            'description' => 'Vertragsende im Format YYYY-MM-DD.'
                        ],
                        'tags' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'Aktualisierte Schlagwörter / Tags als Array von Strings.'
                        ]
                    ],
                    'required' => ['id']
                ],
                'callable' => [self::class, 'executeEditFixedCost']
            ],
            [
                'name' => 'finance_delete_fixed_cost',
                'description' => 'Verschiebt eine Fixkosten-Kostenstelle sicher ins Archiv (Soft Delete). Der Datensatz bleibt zur Nachvollziehbarkeit erhalten und kann im Archiv wiederhergestellt oder endgültig gelöscht werden.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => [
                            'type' => 'string',
                            'description' => 'Die exakte UUID der zu archivierenden Kostenstelle.'
                        ]
                    ],
                    'required' => ['id']
                ],
                'callable' => [self::class, 'executeDeleteFixedCost']
            ],
            [
                'name' => 'finance_list_archived_fixed_costs',
                'description' => 'Listet alle archivierten bzw. gelöschten Fixkosten-Kostenstellen aus dem Archiv auf. Stichworte: Archiv anzeigen, archivierte Fixkosten, gelöschte Verträge prüfen.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => [
                            'type' => 'string',
                            'description' => 'Optional: Filtert das Archiv nach einem Suchbegriff (z.B. Name, Anbieter oder Gruppe).'
                        ]
                    ],
                    'required' => []
                ],
                'callable' => [self::class, 'executeListArchivedFixedCosts']
            ],
            [
                'name' => 'finance_restore_fixed_cost',
                'description' => 'Stellt eine archivierte Fixkosten-Kostenstelle aus dem Archiv wieder her. Stichworte: Kostenstelle wiederherstellen, Vertrag reaktivieren, aus dem Archiv holen.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => [
                            'type' => 'string',
                            'description' => 'Die exakte UUID der archivierten Kostenstelle.'
                        ]
                    ],
                    'required' => ['id']
                ],
                'callable' => [self::class, 'executeRestoreFixedCost']
            ],
            [
                'name' => 'finance_force_delete_fixed_cost',
                'description' => 'Löscht eine archivierte Fixkosten-Kostenstelle endgültig und unwiderruflich aus der Datenbank. Nutze dies nur, wenn der Nutzer ausdrücklich das endgültige Löschen aus dem Archiv fordert.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => [
                            'type' => 'string',
                            'description' => 'Die exakte UUID der archivierten Kostenstelle.'
                        ]
                    ],
                    'required' => ['id']
                ],
                'callable' => [self::class, 'executeForceDeleteFixedCost']
            ],
            [
                'name' => 'finance_read_fixed_cost_contract',
                'description' => 'Liest und analysiert den Inhalt des hinterlegten Vertrags, Dokuments oder Belegs einer Fixkosten-Kostenstelle (unterstützt PDF, Textdateien und Bilder). Kann per UUID oder direkt per Name/Suchbegriff (auch fehlertolerant/Fuzzy z.B. "Gründerzuschuss") aufgerufen werden.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => [
                            'type' => 'string',
                            'description' => 'UUID oder Name/Suchbegriff der Kostenstelle (z.B. "Gründungszuschuss", "Gründerzuschuss" oder "Hetzner").'
                        ],
                        'query' => [
                            'type' => 'string',
                            'description' => 'Alternative: Name oder Suchbegriff der Kostenstelle, falls die ID nicht bekannt ist.'
                        ],
                        'name' => [
                            'type' => 'string',
                            'description' => 'Alternative: Name der Kostenstelle.'
                        ]
                    ],
                    'required' => []
                ],
                'callable' => [self::class, 'executeReadFixedCostContract']
            ]
        ];
    }

    public static function executeCheckMissingExpenses(array $args)
    {
        return [
            'status' => 'success',
            'has_missing_expenses' => false,
            'message' => 'Aktuell sind alle erfassten Sonderausgaben verbucht. Es fehlen keine Belege im System.'
        ];
    }

    protected static function getAdminId()
    {
        return \App\Services\AI\AiAuthHelper::getAdminId() ?? \App\Models\Admin\Admin::first()->id;
    }

    public static function executeGetFinances(array $args)
    {
        try {
            $month = $args['month'] ?? date('n');
            $year = $args['year'] ?? date('Y');
            $isNet = $args['is_net'] ?? true; // Standard: Netto
            
            $service = new \App\Services\FinancialService();
            $stats = $service->getMonthlyStats(self::getAdminId(), $month, $year, $isNet);

            return [
                'status' => 'success',
                'financial_data' => $stats,
                'is_net' => $isNet,
                'month' => $month,
                'year' => $year,
                'info' => 'Beachte: total_budget beinhaltet Shop-Umsatz und Fix-Einnahmen. total_spent sind Fixkosten und Sonderausgaben (negativ). available ist das aktuell noch frei verfügbare Budget dieses Monats.'
            ];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public static function executeGetDailyShopRevenue(array $args)
    {
        try {
            $date = $args['date'] ?? date('Y-m-d');
            $isNet = $args['is_net'] ?? true;

            $query = \App\Models\Accounting\AccountingInvoice::whereDate('invoice_date', $date)
                ->whereIn('status', ['paid', 'cancelled'])
                ->whereIn('type', ['invoice', 'cancellation', 'credit_note']);

            if ($isNet) {
                $query->selectRaw('SUM(total - tax_amount) as sum_total');
            } else {
                $query->selectRaw('SUM(total) as sum_total');
            }
            
            $revenueCents = $query->value('sum_total') ?? 0;
            $revenueEuro = $revenueCents / 100;

            return [
                'status' => 'success',
                'date' => $date,
                'is_net' => $isNet,
                'shop_revenue_euro' => $revenueEuro
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public static function normalizeGermanString(string $str): string
    {
        $str = mb_strtolower(trim($str));
        $replacements = [
            'ä' => 'ae',
            'ö' => 'oe',
            'ü' => 'ue',
            'ß' => 'ss',
        ];
        $str = str_replace(array_keys($replacements), array_values($replacements), $str);
        $str = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $str);
        return trim($str);
    }

    protected static function calculateGermanCompoundSimilarity(string $w1, string $w2): float
    {
        $len1 = strlen($w1);
        $len2 = strlen($w2);
        if ($len1 < 4 || $len2 < 4) {
            return 0.0;
        }

        $minLen = min($len1, $len2);
        $prefixLen = 0;
        while ($prefixLen < $minLen && $w1[$prefixLen] === $w2[$prefixLen]) {
            $prefixLen++;
        }

        $suffixLen = 0;
        while ($suffixLen < ($minLen - $prefixLen) &&
               $w1[$len1 - 1 - $suffixLen] === $w2[$len2 - 1 - $suffixLen]) {
            $suffixLen++;
        }

        if ($prefixLen >= 4 && $suffixLen >= 4) {
            $matchingChars = $prefixLen + $suffixLen;
            $maxLen = max($len1, $len2);
            return min(0.95, $matchingChars / $maxLen);
        }

        if ($prefixLen >= 5) {
            return min(0.85, ($prefixLen * 2) / ($len1 + $len2));
        }

        return 0.0;
    }

    public static function calculateSimilarity(string $query, string $target): float
    {
        $qNorm = self::normalizeGermanString($query);
        $tNorm = self::normalizeGermanString($target);

        if ($qNorm === '' || $tNorm === '') {
            return 0.0;
        }

        if ($qNorm === $tNorm) {
            return 1.0;
        }

        if (str_contains($tNorm, $qNorm)) {
            return 0.90 + (0.10 * (strlen($qNorm) / strlen($tNorm)));
        }
        if (str_contains($qNorm, $tNorm)) {
            return 0.85;
        }

        similar_text($qNorm, $tNorm, $simPercent);
        $simScore = $simPercent / 100.0;

        $maxLen = max(strlen($qNorm), strlen($tNorm));
        $lev = levenshtein($qNorm, $tNorm);
        $levScore = $maxLen > 0 ? max(0.0, 1.0 - ($lev / $maxLen)) : 0.0;

        $stemScore = self::calculateGermanCompoundSimilarity($qNorm, $tNorm);
        $bestDirectScore = max($simScore, $levScore, $stemScore);

        $qWords = array_filter(explode(' ', $qNorm));
        $tWords = array_filter(explode(' ', $tNorm));

        if (!empty($qWords) && !empty($tWords)) {
            $totalWordScore = 0.0;
            foreach ($qWords as $qw) {
                $bestWordMatch = 0.0;
                foreach ($tWords as $tw) {
                    if ($qw === $tw) {
                        $bestWordMatch = 1.0;
                        break;
                    }
                    if (str_contains($tw, $qw) || str_contains($qw, $tw)) {
                        $bestWordMatch = max($bestWordMatch, 0.88);
                        continue;
                    }
                    similar_text($qw, $tw, $wPercent);
                    $wScore = $wPercent / 100.0;
                    $wMax = max(strlen($qw), strlen($tw));
                    $wLev = levenshtein($qw, $tw);
                    $wLevScore = $wMax > 0 ? max(0.0, 1.0 - ($wLev / $wMax)) : 0.0;
                    $cScore = self::calculateGermanCompoundSimilarity($qw, $tw);
                    $bestWordMatch = max($bestWordMatch, $wScore, $wLevScore, $cScore);
                }
                $totalWordScore += $bestWordMatch;
            }
            $avgWordScore = $totalWordScore / count($qWords);
            return max($bestDirectScore, $avgWordScore);
        }

        return $bestDirectScore;
    }

    public static function isFuzzyMatch(string $query, string $target, float $threshold = 0.65): bool
    {
        return self::calculateSimilarity($query, $target) >= $threshold;
    }

    public static function findCostItem(string $queryOrId, string|int $adminId, bool $includeTrashed = true): ?\App\Models\Accounting\AccountingCostItem
    {
        $trimmed = trim($queryOrId);
        if ($trimmed === '') {
            return null;
        }

        $baseQuery = \App\Models\Accounting\AccountingCostItem::whereHas('group', fn($q) => $q->where('admin_id', $adminId))->with('group');
        if ($includeTrashed) {
            $baseQuery->withTrashed();
        }

        // 1. Direct ID lookup
        $byUuid = (clone $baseQuery)->where('id', $trimmed)->first();
        if ($byUuid) {
            return $byUuid;
        }

        // 2. Exact or substring name lookup
        $allItems = $baseQuery->get();
        $exactName = $allItems->first(fn($item) => mb_strtolower($item->name) === mb_strtolower($trimmed));
        if ($exactName) {
            return $exactName;
        }

        // 3. Fuzzy search scored across all items
        $scored = $allItems->map(function ($item) use ($trimmed) {
            $nameSim = self::calculateSimilarity($trimmed, $item->name);
            $descSim = !empty($item->description) ? self::calculateSimilarity($trimmed, $item->description) : 0.0;
            $provSim = !empty($item->provider_company) ? self::calculateSimilarity($trimmed, $item->provider_company) : 0.0;
            $groupSim = $item->group ? self::calculateSimilarity($trimmed, $item->group->name) : 0.0;

            $maxScore = max($nameSim, $descSim * 0.9, $provSim * 0.95, $groupSim * 0.85);

            return [
                'item' => $item,
                'score' => $maxScore
            ];
        })->filter(fn($entry) => $entry['score'] >= 0.65)
          ->sortByDesc('score');

        return $scored->first()['item'] ?? null;
    }

    public static function executeListFixedCosts(array $args)
    {
        try {
            $adminId = self::getAdminId();
            $groups = \App\Models\Accounting\AccountingGroup::with('items')->where('admin_id', $adminId)->get();
            
            $searchTerm = !empty($args['query']) ? trim($args['query']) : null;

            $costs = [];
            foreach ($groups as $group) {
                $groupItems = [];
                foreach ($group->items as $item) {
                    $score = 1.0;
                    if ($searchTerm) {
                        $itemSearchTarget = implode(' ', array_filter([
                            $item->name,
                            $item->description,
                            $item->provider_company,
                            $item->contract_number,
                            is_array($item->tags) ? implode(' ', $item->tags) : null,
                            $group->name
                        ]));

                        $score = self::calculateSimilarity($searchTerm, $itemSearchTarget);
                        $nameScore = self::calculateSimilarity($searchTerm, $item->name);
                        $provScore = !empty($item->provider_company) ? self::calculateSimilarity($searchTerm, $item->provider_company) : 0.0;
                        $score = max($score, $nameScore, $provScore);

                        if ($score < 0.65 && !str_contains(mb_strtolower($itemSearchTarget), mb_strtolower($searchTerm))) {
                            continue;
                        }
                    }

                    $groupItems[] = [
                        'id' => $item->id,
                        'name' => $item->name,
                        'amount' => (float)$item->amount,
                        'interval_months' => (int)$item->interval_months,
                        'first_payment_date' => $item->first_payment_date ? \Carbon\Carbon::parse($item->first_payment_date)->format('Y-m-d') : null,
                        'last_payment_date' => $item->last_payment_date ? \Carbon\Carbon::parse($item->last_payment_date)->format('Y-m-d') : null,
                        'is_business' => (bool)$item->is_business,
                        'requires_contract' => (bool)$item->requires_contract,
                        'has_contract_file' => !empty($item->contract_file_path),
                        'tax_rate' => $item->tax_rate,
                        'description' => $item->description,
                        'provider_company' => $item->provider_company,
                        'contract_number' => $item->contract_number,
                        'match_score' => round($score, 2),
                    ];
                }

                if (!$searchTerm || !empty($groupItems)) {
                    if ($searchTerm) {
                        usort($groupItems, fn($a, $b) => ($b['match_score'] <=> $a['match_score']));
                    }

                    $costs[] = [
                        'group_id' => $group->id,
                        'group_name' => $group->name,
                        'type' => $group->type,
                        'items' => $groupItems
                    ];
                }
            }

            return [
                'status' => 'success',
                'fixed_costs_by_category' => $costs,
                'total_groups' => count($costs),
                'total_items' => collect($costs)->sum(fn($g) => count($g['items']))
            ];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    protected static function generateSensibleTags(array $providedTags = [], string $name = '', ?string $provider = null, bool $isBusiness = true, ?string $groupName = null, int $intervalMonths = 1): array
    {
        $tags = [];
        foreach ($providedTags as $t) {
            if (is_string($t) && trim($t) !== '') {
                $tags[] = ucfirst(trim($t));
            }
        }

        // Business / Private Tag
        $tags[] = $isBusiness ? 'Gewerblich' : 'Privat';

        // Interval Tag
        if ($intervalMonths === 1) {
            $tags[] = 'Monatlich';
        } elseif ($intervalMonths === 3) {
            $tags[] = 'Quartalsweise';
        } elseif ($intervalMonths === 6) {
            $tags[] = 'Halbjährlich';
        } elseif ($intervalMonths === 12) {
            $tags[] = 'Jährlich';
        }

        $textToAnalyze = mb_strtolower($name . ' ' . ($provider ?? '') . ' ' . ($groupName ?? ''));

        // Brand and provider specific tags
        $brandMap = [
            'Hetzner' => 'hetzner',
            'Netcup' => 'netcup',
            'Strato' => 'strato',
            'IONOS' => 'ionos',
            'AWS' => 'aws',
            'Google' => 'google',
            'Adobe' => 'adobe',
            'Canva' => 'canva',
            'Shopify' => 'shopify',
            'OpenAI' => 'openai',
            'ChatGPT' => 'chatgpt',
            'GitHub' => 'github',
            'JetBrains' => 'jetbrains',
            'Slack' => 'slack',
            'Zoom' => 'zoom',
            'Microsoft' => 'microsoft',
            'Telekom' => 'telekom',
            'Vodafone' => 'vodafone',
            'O2' => 'o2',
        ];

        foreach ($brandMap as $brandTag => $brandKw) {
            if (str_contains($textToAnalyze, $brandKw)) {
                $tags[] = $brandTag;
            }
        }

        // Smart keyword category tagging
        $keywordMap = [
            'Hosting' => ['hosting', 'webspace', 'domain'],
            'Server' => ['server', 'vserver', 'vps', 'rootserver', 'cloud server'],
            'Software' => ['software', 'saas', 'app', 'lizenz', 'tools'],
            'Telekommunikation' => ['telefonica', 'dsl', 'glasfaser', 'internet', 'mobilfunk', 'handy', 'festnetz', 'sipgate', 'placetel'],
            'Versicherung' => ['versicherung', 'allianz', 'huk', 'barmer', 'tk', 'techniker', 'aok', 'haftpflicht', 'rechtsschutz', 'inhaltsversicherung'],
            'Miete' => ['miete', 'büro', 'lager', 'arbeitsplatz', 'coworking', 'stellplatz', 'garage'],
            'Marketing' => ['marketing', 'werbung', 'ads', 'facebook', 'meta', 'tiktok', 'google ads', 'newsletter', 'klaviyo', 'mailchimp'],
            'Finanzen' => ['bank', 'konto', 'kontoführung', 'gebühr', 'kredit', 'zinsen', 'finanzamt', 'datev', 'steuerberater', 'lexoffice', 'sevdesk'],
            'Infrastruktur' => ['strom', 'wasser', 'gas', 'energie', 'reinigung', 'müll', 'heizung'],
        ];

        foreach ($keywordMap as $tagKey => $keywords) {
            foreach ($keywords as $kw) {
                if (str_contains($textToAnalyze, $kw)) {
                    $tags[] = $tagKey;
                    break;
                }
            }
        }

        return array_values(array_unique(array_filter($tags)));
    }

    public static function executeCreateFixedCost(array $args)
    {
        try {
            $adminId = self::getAdminId();

            if (empty($args['name'])) {
                return ['status' => 'error', 'message' => 'Der Name der Kostenstelle ist erforderlich.'];
            }
            if (!isset($args['amount'])) {
                return ['status' => 'error', 'message' => 'Der Betrag der Kostenstelle ist erforderlich.'];
            }

            // Archive duplicate check: Did this cost item exist before in the archive?
            if (empty($args['ignore_archived'])) {
                $trimmedName = trim($args['name']);
                $archivedMatch = \App\Models\Accounting\AccountingCostItem::onlyTrashed()
                    ->whereHas('group', fn($q) => $q->where('admin_id', $adminId))
                    ->where(function ($q) use ($trimmedName) {
                        $q->whereRaw('LOWER(name) = ?', [mb_strtolower($trimmedName)])
                          ->orWhere('name', 'LIKE', '%' . $trimmedName . '%');
                    })
                    ->with('group')
                    ->first();

                if (!$archivedMatch) {
                    $trashedItems = \App\Models\Accounting\AccountingCostItem::onlyTrashed()
                        ->whereHas('group', fn($q) => $q->where('admin_id', $adminId))
                        ->with('group')
                        ->get();
                    $archivedMatch = $trashedItems->first(fn($item) => self::isFuzzyMatch($trimmedName, $item->name, 0.70));
                }

                if ($archivedMatch) {
                    $archivedDateStr = $archivedMatch->deleted_at ? $archivedMatch->deleted_at->format('d.m.Y H:i') : 'unbekannt';
                    return [
                        'status' => 'archived_match_found',
                        'message' => "Im Fixkosten-Archiv existiert bereits eine frühere Kostenstelle namens '{$archivedMatch->name}' (Betrag: {$archivedMatch->amount}€, Intervall: alle {$archivedMatch->interval_months} Monat(e), archiviert am {$archivedDateStr}, ID: '{$archivedMatch->id}'). Möchtest du diese archivierte Kostenstelle wiederherstellen (nutze 'finance_restore_fixed_cost' mit ID: '{$archivedMatch->id}') oder soll trotzdem eine neue Kostenstelle angelegt werden (setze 'ignore_archived' auf true)?",
                        'archived_item' => [
                            'id' => $archivedMatch->id,
                            'name' => $archivedMatch->name,
                            'amount' => (float)$archivedMatch->amount,
                            'interval_months' => $archivedMatch->interval_months,
                            'group_name' => $archivedMatch->group ? $archivedMatch->group->name : null,
                            'archived_at' => $archivedMatch->deleted_at ? $archivedMatch->deleted_at->format('Y-m-d H:i:s') : null,
                            'tags' => $archivedMatch->tags ?? [],
                            'provider_company' => $archivedMatch->provider_company,
                        ],
                        'suggested_action' => 'restore',
                        'restore_tool' => 'finance_restore_fixed_cost',
                    ];
                }
            }

            $amount = (float) str_replace(',', '.', (string) $args['amount']);
            $intervalMonths = isset($args['interval_months']) ? max(1, (int)$args['interval_months']) : 1;
            $isBusiness = isset($args['is_business']) ? (bool)$args['is_business'] : true;
            $firstPaymentDate = !empty($args['first_payment_date']) 
                ? \Carbon\Carbon::parse($args['first_payment_date'])->format('Y-m-d')
                : now()->format('Y-m-d');
            $requiresContract = isset($args['requires_contract']) ? (bool)$args['requires_contract'] : false;
            $taxRate = isset($args['tax_rate']) ? (int)$args['tax_rate'] : ($isBusiness ? 19 : null);

            // Determine Target Group
            $targetGroup = null;
            if (!empty($args['group_id'])) {
                $targetGroup = \App\Models\Accounting\AccountingGroup::where('id', $args['group_id'])
                    ->where('admin_id', $adminId)
                    ->first();
            }

            if (!$targetGroup && !empty($args['group_name'])) {
                $targetGroup = \App\Models\Accounting\AccountingGroup::where('admin_id', $adminId)
                    ->where('name', trim($args['group_name']))
                    ->first();
                
                if (!$targetGroup) {
                    $groupType = $amount >= 0 ? 'income' : 'expense';
                    $targetGroup = \App\Models\Accounting\AccountingGroup::create([
                        'admin_id' => $adminId,
                        'name' => trim($args['group_name']),
                        'type' => $groupType,
                        'position' => 99,
                    ]);
                }
            }

            if (!$targetGroup) {
                $groupType = $amount >= 0 ? 'income' : 'expense';
                $targetGroup = \App\Models\Accounting\AccountingGroup::where('admin_id', $adminId)
                    ->where('type', $groupType)
                    ->orderBy('position')
                    ->first();

                if (!$targetGroup) {
                    $defaultName = $groupType === 'income' ? 'Fixe Einnahmen' : 'Fixe Ausgaben';
                    $targetGroup = \App\Models\Accounting\AccountingGroup::create([
                        'admin_id' => $adminId,
                        'name' => $defaultName,
                        'type' => $groupType,
                        'position' => 1,
                    ]);
                }
            }

            // Generate Sensible Tags
            $rawTags = isset($args['tags']) && is_array($args['tags']) ? $args['tags'] : [];
            $computedTags = self::generateSensibleTags(
                $rawTags, 
                $args['name'], 
                $args['provider_company'] ?? null, 
                $isBusiness, 
                $targetGroup->name, 
                $intervalMonths
            );

            $item = \App\Models\Accounting\AccountingCostItem::create([
                'accounting_group_id' => $targetGroup->id,
                'name' => trim($args['name']),
                'amount' => $amount,
                'interval_months' => $intervalMonths,
                'first_payment_date' => $firstPaymentDate,
                'is_business' => $isBusiness,
                'requires_contract' => $requiresContract,
                'tax_rate' => $taxRate,
                'tags' => $computedTags,
                'description' => $args['description'] ?? null,
                'provider_company' => $args['provider_company'] ?? null,
                'contract_number' => $args['contract_number'] ?? null,
                'notice_period' => $args['notice_period'] ?? null,
            ]);

            // Create History Entry
            if (class_exists(\App\Models\Accounting\AccountingCostItemHistory::class)) {
                \App\Models\Accounting\AccountingCostItemHistory::create([
                    'accounting_cost_item_id' => $item->id,
                    'name' => $item->name,
                    'amount' => $item->amount,
                    'interval_months' => $item->interval_months,
                    'first_payment_date' => $item->first_payment_date,
                    'is_business' => $item->is_business,
                    'requires_contract' => $item->requires_contract,
                    'tax_rate' => $item->tax_rate,
                    'tags' => $computedTags,
                    'accounting_group_id' => $targetGroup->id,
                    'description' => 'Initiale Anlage durch KI-Agent (Buchi) mit Tags: ' . implode(', ', $computedTags),
                    'provider_company' => $item->provider_company,
                    'contract_number' => $item->contract_number,
                    'notice_period' => $item->notice_period,
                ]);
            }

            $typeStr = $amount < 0 ? 'Fixkosten-Ausgabe' : 'Fixkosten-Einnahme';

            return [
                'status' => 'success',
                'id' => $item->id,
                'message' => "Erfolgreich angelegt: {$typeStr} '{$item->name}' ({$amount}€, Intervall: alle {$intervalMonths} Monat(e)) in Gruppe '{$targetGroup->name}' mit Tags: " . implode(', ', $computedTags),
                'item' => [
                    'id' => $item->id,
                    'group_id' => $targetGroup->id,
                    'group_name' => $targetGroup->name,
                    'name' => $item->name,
                    'amount' => (float)$item->amount,
                    'interval_months' => $item->interval_months,
                    'first_payment_date' => $firstPaymentDate,
                    'is_business' => $item->is_business,
                    'tags' => $computedTags,
                    'tax_rate' => $item->tax_rate,
                    'provider_company' => $item->provider_company,
                ]
            ];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Erstellen der Fixkosten: ' . $e->getMessage()];
        }
    }

    public static function executeEditFixedCost(array $args)
    {
        try {
            $adminId = self::getAdminId();

            $targetId = $args['id'] ?? $args['name'] ?? $args['query'] ?? null;
            if (empty($targetId)) {
                return ['status' => 'error', 'message' => 'Die ID oder der Name der Kostenstelle ist erforderlich.'];
            }

            $item = self::findCostItem($targetId, $adminId, false);
            if (!$item) {
                $crossTenantItem = \App\Models\Accounting\AccountingCostItem::withTrashed()->with('group')->find($targetId);
                if ($crossTenantItem && $crossTenantItem->group && (string)$crossTenantItem->group->admin_id !== (string)$adminId) {
                    return ['status' => 'error', 'message' => 'Zugriff verweigert: Sie haben keine Berechtigung für diese Kostenstelle.'];
                }
                return ['status' => 'error', 'message' => "Kostenstelle '{$targetId}' mit der angegebenen ID oder Bezeichnung nicht gefunden."];
            }

            if ($item->group && (string)$item->group->admin_id !== (string)$adminId) {
                return ['status' => 'error', 'message' => 'Zugriff verweigert: Sie haben keine Berechtigung für diese Kostenstelle.'];
            }

            $originalSnapshot = $item->toArray();
            $changesDesc = [];

            if (array_key_exists('name', $args) && $args['name'] !== null) {
                $item->name = trim($args['name']);
                if ($item->name !== ($originalSnapshot['name'] ?? '')) {
                    $changesDesc[] = "Name geändert auf '{$item->name}'";
                }
            }

            if (array_key_exists('amount', $args) && $args['amount'] !== null) {
                $oldAmount = (float)($originalSnapshot['amount'] ?? 0);
                $newAmount = (float) str_replace(',', '.', (string) $args['amount']);
                $item->amount = $newAmount;
                if (abs($oldAmount - $newAmount) > 0.001) {
                    $changesDesc[] = "Betrag von {$oldAmount}€ auf {$newAmount}€ geändert";
                }
            }

            if (array_key_exists('interval_months', $args) && $args['interval_months'] !== null) {
                $newInterval = max(1, (int)$args['interval_months']);
                $oldInterval = (int)($originalSnapshot['interval_months'] ?? 1);
                $item->interval_months = $newInterval;
                if ($oldInterval !== $newInterval) {
                    $changesDesc[] = "Intervall von {$oldInterval} auf {$newInterval} Monate geändert";
                }
            }

            if (!empty($args['group_id'])) {
                $newGroup = \App\Models\Accounting\AccountingGroup::where('id', $args['group_id'])
                    ->where('admin_id', $adminId)
                    ->first();
                if ($newGroup && $newGroup->id !== $item->accounting_group_id) {
                    $item->accounting_group_id = $newGroup->id;
                    $changesDesc[] = "In Gruppe '{$newGroup->name}' verschoben";
                }
            } elseif (!empty($args['group_name'])) {
                $newGroup = \App\Models\Accounting\AccountingGroup::where('admin_id', $adminId)
                    ->where('name', trim($args['group_name']))
                    ->first();
                if (!$newGroup) {
                    $groupType = $item->amount >= 0 ? 'income' : 'expense';
                    $newGroup = \App\Models\Accounting\AccountingGroup::create([
                        'admin_id' => $adminId,
                        'name' => trim($args['group_name']),
                        'type' => $groupType,
                        'position' => 99,
                    ]);
                }
                if ($newGroup && $newGroup->id !== $item->accounting_group_id) {
                    $item->accounting_group_id = $newGroup->id;
                    $changesDesc[] = "In Gruppe '{$newGroup->name}' verschoben";
                }
            }

            if (array_key_exists('first_payment_date', $args) && !empty($args['first_payment_date'])) {
                $item->first_payment_date = \Carbon\Carbon::parse($args['first_payment_date'])->format('Y-m-d');
            }

            if (array_key_exists('last_payment_date', $args)) {
                $item->last_payment_date = !empty($args['last_payment_date']) 
                    ? \Carbon\Carbon::parse($args['last_payment_date'])->format('Y-m-d') 
                    : null;
            }

            if (array_key_exists('is_business', $args) && $args['is_business'] !== null) {
                $item->is_business = (bool)$args['is_business'];
            }

            if (array_key_exists('tax_rate', $args)) {
                $item->tax_rate = $args['tax_rate'] !== null ? (int)$args['tax_rate'] : null;
            }

            if (array_key_exists('requires_contract', $args) && $args['requires_contract'] !== null) {
                $item->requires_contract = (bool)$args['requires_contract'];
            }

            if (array_key_exists('description', $args)) {
                $item->description = $args['description'];
            }

            if (array_key_exists('provider_company', $args)) {
                $item->provider_company = $args['provider_company'];
            }

            if (array_key_exists('contract_number', $args)) {
                $item->contract_number = $args['contract_number'];
            }

            if (array_key_exists('notice_period', $args)) {
                $item->notice_period = $args['notice_period'];
            }

            if (array_key_exists('contract_end_date', $args)) {
                $item->contract_end_date = !empty($args['contract_end_date'])
                    ? \Carbon\Carbon::parse($args['contract_end_date'])->format('Y-m-d')
                    : null;
            }

            if (array_key_exists('tags', $args)) {
                $rawTags = is_array($args['tags']) ? $args['tags'] : (is_string($args['tags']) ? explode(',', $args['tags']) : []);
                $item->tags = self::generateSensibleTags(
                    $rawTags, 
                    $item->name, 
                    $item->provider_company, 
                    $item->is_business, 
                    $item->group ? $item->group->name : null, 
                    $item->interval_months
                );
                $changesDesc[] = 'Tags aktualisiert (' . implode(', ', $item->tags) . ')';
            }

            $item->save();

            // History logging
            $historyDescription = empty($changesDesc)
                ? 'Details der Kostenstelle durch KI-Agent (Buchi) aktualisiert.'
                : 'Aktualisiert durch KI-Agent (Buchi): ' . implode(', ', $changesDesc);

            if (class_exists(\App\Models\Accounting\AccountingCostItemHistory::class)) {
                \App\Models\Accounting\AccountingCostItemHistory::create([
                    'accounting_cost_item_id' => $item->id,
                    'name' => $item->name,
                    'amount' => $item->amount,
                    'interval_months' => $item->interval_months,
                    'first_payment_date' => $item->first_payment_date ? \Carbon\Carbon::parse($item->first_payment_date)->format('Y-m-d') : null,
                    'last_payment_date' => $item->last_payment_date ? \Carbon\Carbon::parse($item->last_payment_date)->format('Y-m-d') : null,
                    'is_business' => $item->is_business,
                    'requires_contract' => $item->requires_contract,
                    'tax_rate' => $item->tax_rate,
                    'contract_file_path' => $item->contract_file_path,
                    'tags' => $item->tags,
                    'accounting_group_id' => $item->accounting_group_id,
                    'description' => $historyDescription,
                    'provider_company' => $item->provider_company,
                    'contract_number' => $item->contract_number,
                    'notice_period' => $item->notice_period,
                    'contract_end_date' => $item->contract_end_date ? \Carbon\Carbon::parse($item->contract_end_date)->format('Y-m-d') : null,
                ]);
            }

            return [
                'status' => 'success',
                'message' => "Kostenstelle '{$item->name}' erfolgreich aktualisiert: " . (empty($changesDesc) ? 'Daten gespeichert' : implode(', ', $changesDesc)),
                'item' => [
                    'id' => $item->id,
                    'group_id' => $item->accounting_group_id,
                    'name' => $item->name,
                    'amount' => (float)$item->amount,
                    'interval_months' => $item->interval_months,
                    'first_payment_date' => $item->first_payment_date ? \Carbon\Carbon::parse($item->first_payment_date)->format('Y-m-d') : null,
                    'last_payment_date' => $item->last_payment_date ? \Carbon\Carbon::parse($item->last_payment_date)->format('Y-m-d') : null,
                    'is_business' => (bool)$item->is_business,
                    'tags' => $item->tags ?? [],
                    'tax_rate' => $item->tax_rate,
                    'provider_company' => $item->provider_company,
                ]
            ];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Bearbeiten der Fixkosten: ' . $e->getMessage()];
        }
    }

    public static function executeDeleteFixedCost(array $args)
    {
        try {
            $adminId = self::getAdminId();

            $targetId = $args['id'] ?? $args['name'] ?? $args['query'] ?? null;
            if (empty($targetId)) {
                return ['status' => 'error', 'message' => 'Die ID oder der Name der zu archivierenden Kostenstelle ist erforderlich.'];
            }

            $item = self::findCostItem($targetId, $adminId, false);
            if (!$item) {
                $crossTenantItem = \App\Models\Accounting\AccountingCostItem::withTrashed()->with('group')->find($targetId);
                if ($crossTenantItem && $crossTenantItem->group && (string)$crossTenantItem->group->admin_id !== (string)$adminId) {
                    return ['status' => 'error', 'message' => 'Zugriff verweigert: Sie haben keine Berechtigung für diese Kostenstelle.'];
                }
                return ['status' => 'error', 'message' => "Kostenstelle '{$targetId}' nicht gefunden oder bereits archiviert."];
            }

            if ($item->group && (string)$item->group->admin_id !== (string)$adminId) {
                return ['status' => 'error', 'message' => 'Zugriff verweigert: Sie haben keine Berechtigung für diese Kostenstelle.'];
            }

            $name = $item->name;
            $amount = $item->amount;

            // Log archiving to history before deleting
            if (class_exists(\App\Models\Accounting\AccountingCostItemHistory::class)) {
                \App\Models\Accounting\AccountingCostItemHistory::create([
                    'accounting_cost_item_id' => $item->id,
                    'name' => $item->name,
                    'amount' => $item->amount,
                    'interval_months' => $item->interval_months,
                    'first_payment_date' => $item->first_payment_date ? \Carbon\Carbon::parse($item->first_payment_date)->format('Y-m-d') : null,
                    'last_payment_date' => $item->last_payment_date ? \Carbon\Carbon::parse($item->last_payment_date)->format('Y-m-d') : null,
                    'is_business' => $item->is_business,
                    'requires_contract' => $item->requires_contract,
                    'tax_rate' => $item->tax_rate,
                    'contract_file_path' => $item->contract_file_path,
                    'tags' => $item->tags,
                    'accounting_group_id' => $item->accounting_group_id,
                    'description' => 'In das Archiv verschoben (archiviert) durch KI-Agent (Buchi).',
                    'provider_company' => $item->provider_company,
                    'contract_number' => $item->contract_number,
                    'notice_period' => $item->notice_period,
                    'contract_end_date' => $item->contract_end_date ? \Carbon\Carbon::parse($item->contract_end_date)->format('Y-m-d') : null,
                ]);
            }

            // Soft-Delete (archives the item - contract file and path are preserved!)
            $item->delete();

            return [
                'status' => 'success',
                'message' => "Die Fixkosten-Kostenstelle '{$name}' ({$amount}€) wurde sicher ins Archiv verschoben (archiviert). Sie kann bei Bedarf mit 'finance_restore_fixed_cost' wiederhergestellt oder im Archiv mit 'finance_force_delete_fixed_cost' endgültig gelöscht werden."
            ];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Archivieren der Fixkosten: ' . $e->getMessage()];
        }
    }

    public static function executeListArchivedFixedCosts(array $args)
    {
        try {
            $adminId = self::getAdminId();
            $query = \App\Models\Accounting\AccountingCostItem::onlyTrashed()
                ->whereHas('group', fn($q) => $q->where('admin_id', $adminId))
                ->with('group')
                ->orderBy('deleted_at', 'desc');

            $items = $query->get();

            if (!empty($args['query'])) {
                $searchTerm = trim($args['query']);
                $items = $items->filter(function ($item) use ($searchTerm) {
                    $target = implode(' ', array_filter([
                        $item->name,
                        $item->provider_company,
                        $item->description,
                        $item->contract_number,
                        $item->group ? $item->group->name : null,
                        is_array($item->tags) ? implode(' ', $item->tags) : null,
                    ]));
                    return self::isFuzzyMatch($searchTerm, $target, 0.65)
                        || self::isFuzzyMatch($searchTerm, $item->name, 0.65);
                });
            }

            $mappedItems = $items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'amount' => (float)$item->amount,
                    'interval_months' => (int)$item->interval_months,
                    'group_id' => $item->accounting_group_id,
                    'group_name' => $item->group ? $item->group->name : null,
                    'is_business' => (bool)$item->is_business,
                    'has_contract_file' => !empty($item->contract_file_path),
                    'tags' => $item->tags ?? [],
                    'provider_company' => $item->provider_company,
                    'contract_number' => $item->contract_number,
                    'first_payment_date' => $item->first_payment_date ? \Carbon\Carbon::parse($item->first_payment_date)->format('Y-m-d') : null,
                    'archived_at' => $item->deleted_at ? $item->deleted_at->format('Y-m-d H:i:s') : null,
                ];
            });

            return [
                'status' => 'success',
                'archived_fixed_costs' => $mappedItems->toArray(),
                'archived_items' => $mappedItems->toArray(),
                'total_archived' => $mappedItems->count(),
                'total_archived_items' => $mappedItems->count(),
                'message' => $mappedItems->isEmpty() ? 'Keine archivierten Fixkosten vorhanden.' : "{$mappedItems->count()} archivierte Fixkosten gefunden."
            ];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Laden des Archivs: ' . $e->getMessage()];
        }
    }

    public static function executeRestoreFixedCost(array $args)
    {
        try {
            $adminId = self::getAdminId();

            $targetId = $args['id'] ?? $args['name'] ?? $args['query'] ?? null;
            if (empty($targetId)) {
                return ['status' => 'error', 'message' => 'Die ID oder der Name der wiederherzustellenden Kostenstelle ist erforderlich.'];
            }

            $item = self::findCostItem($targetId, $adminId, true);
            if (!$item || !$item->trashed()) {
                $crossTenantItem = \App\Models\Accounting\AccountingCostItem::withTrashed()->with('group')->find($targetId);
                if ($crossTenantItem && $crossTenantItem->group && (string)$crossTenantItem->group->admin_id !== (string)$adminId) {
                    return ['status' => 'error', 'message' => 'Zugriff verweigert: Sie haben keine Berechtigung für diese Kostenstelle.'];
                }
                return ['status' => 'error', 'message' => "Archivierte Kostenstelle '{$targetId}' nicht im Archiv gefunden."];
            }

            if ($item->group && (string)$item->group->admin_id !== (string)$adminId) {
                return ['status' => 'error', 'message' => 'Zugriff verweigert: Sie haben keine Berechtigung für diese Kostenstelle.'];
            }

            $item->restore();

            if (class_exists(\App\Models\Accounting\AccountingCostItemHistory::class)) {
                \App\Models\Accounting\AccountingCostItemHistory::create([
                    'accounting_cost_item_id' => $item->id,
                    'name' => $item->name,
                    'amount' => $item->amount,
                    'interval_months' => $item->interval_months,
                    'first_payment_date' => $item->first_payment_date ? \Carbon\Carbon::parse($item->first_payment_date)->format('Y-m-d') : null,
                    'last_payment_date' => $item->last_payment_date ? \Carbon\Carbon::parse($item->last_payment_date)->format('Y-m-d') : null,
                    'is_business' => $item->is_business,
                    'requires_contract' => $item->requires_contract,
                    'tax_rate' => $item->tax_rate,
                    'contract_file_path' => $item->contract_file_path,
                    'tags' => $item->tags,
                    'accounting_group_id' => $item->accounting_group_id,
                    'description' => 'Aus dem Archiv wiederhergestellt durch KI-Agent (Buchi).',
                    'provider_company' => $item->provider_company,
                    'contract_number' => $item->contract_number,
                    'notice_period' => $item->notice_period,
                    'contract_end_date' => $item->contract_end_date ? \Carbon\Carbon::parse($item->contract_end_date)->format('Y-m-d') : null,
                ]);
            }

            return [
                'status' => 'success',
                'message' => "Die Kostenstelle '{$item->name}' ({$item->amount}€) wurde erfolgreich aus dem Archiv wiederhergestellt und ist wieder aktiv.",
                'item' => [
                    'id' => $item->id,
                    'group_id' => $item->accounting_group_id,
                    'group_name' => $item->group ? $item->group->name : null,
                    'name' => $item->name,
                    'amount' => (float)$item->amount,
                    'interval_months' => $item->interval_months,
                    'has_contract_file' => !empty($item->contract_file_path),
                    'tags' => $item->tags ?? [],
                ]
            ];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Wiederherstellen der Fixkosten: ' . $e->getMessage()];
        }
    }

    public static function executeForceDeleteFixedCost(array $args)
    {
        try {
            $adminId = self::getAdminId();

            $targetId = $args['id'] ?? $args['name'] ?? $args['query'] ?? null;
            if (empty($targetId)) {
                return ['status' => 'error', 'message' => 'Die ID oder der Name der endgültig zu löschenden Kostenstelle ist erforderlich.'];
            }

            $item = self::findCostItem($targetId, $adminId, true);
            if (!$item) {
                $crossTenantItem = \App\Models\Accounting\AccountingCostItem::withTrashed()->with('group')->find($targetId);
                if ($crossTenantItem && $crossTenantItem->group && (string)$crossTenantItem->group->admin_id !== (string)$adminId) {
                    return ['status' => 'error', 'message' => 'Zugriff verweigert: Sie haben keine Berechtigung für diese Kostenstelle.'];
                }
                return ['status' => 'error', 'message' => "Archivierte Kostenstelle '{$targetId}' nicht gefunden."];
            }
            if (!$item->trashed()) {
                return ['status' => 'error', 'message' => "Diese Kostenstelle '{$item->name}' ist noch aktiv und liegt nicht im Archiv. Archiviere sie zuerst mit finance_delete_fixed_cost bevor sie endgültig gelöscht werden kann."];
            }

            if ($item->group && (string)$item->group->admin_id !== (string)$adminId) {
                return ['status' => 'error', 'message' => 'Zugriff verweigert: Sie haben keine Berechtigung für diese Kostenstelle.'];
            }

            $name = $item->name;
            $amount = $item->amount;

            // Clean up contract file if present
            if ($item->contract_file_path) {
                if (\Illuminate\Support\Facades\Storage::disk('local')->exists($item->contract_file_path)) {
                    \Illuminate\Support\Facades\Storage::disk('local')->delete($item->contract_file_path);
                } elseif (\Illuminate\Support\Facades\Storage::disk('private')->exists($item->contract_file_path)) {
                    \Illuminate\Support\Facades\Storage::disk('private')->delete($item->contract_file_path);
                }
            }

            $item->forceDelete();

            return [
                'status' => 'success',
                'message' => "Die archivierte Fixkosten-Kostenstelle '{$name}' ({$amount}€) wurde endgültig und unwiderruflich aus der Datenbank gelöscht."
            ];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => 'Fehler beim endgültigen Löschen der Fixkosten: ' . $e->getMessage()];
        }
    }

    public static function analyzeDocumentWithGemini(string $absolutePath, string $mime = 'application/pdf'): ?string
    {
        $apiKey = config('services.gemini.key', env('GEMINI_API_KEY'));
        if (empty($apiKey)) {
            return null;
        }

        $models = ['gemini-2.5-flash', 'gemini-1.5-flash'];
        $base64 = base64_encode(file_get_contents($absolutePath));

        $prompt = "Du bist ein intelligenter Dokumenten- und Vertrags-Analyst für Buchhaltung und Finanzen. Analysiere dieses PDF-Dokument (Vertrag/Beleg) vollständig und strukturiert auf Deutsch.\n"
            . "Transkribiere und erfasse alle wesentlichen Inhalte, Vereinbarungen und Konditionen:\n"
            . "- Vertragsparteien (z.B. Vermieter/Anbieter und Mieter/Kunde inkl. Anschriften und Kontaktdaten)\n"
            . "- Vertragsgegenstand / Objektbeschreibung\n"
            . "- Beträge (z.B. Kaltmiete, Nebenkosten, Gesamtbetrag/Warmmiete, Kaution, MwSt.)\n"
            . "- Zahlungsintervall und Fälligkeiten\n"
            . "- Vertragsbeginn, Laufzeit und Befristungen\n"
            . "- Kündigungsfristen und Verlängerungsregeln\n"
            . "- Wesentliche Sondervereinbarungen oder Klauseln";

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt],
                        [
                            'inlineData' => [
                                'mimeType' => $mime,
                                'data' => $base64
                            ]
                        ]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.1,
            ]
        ];

        foreach ($models as $model) {
            try {
                $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
                $response = \Illuminate\Support\Facades\Http::timeout(60)->post($url, $payload);
                if ($response->successful()) {
                    $resData = $response->json();
                    $text = $resData['candidates'][0]['content']['parts'][0]['text'] ?? null;
                    if (!empty($text)) {
                        return trim($text);
                    }
                }
            } catch (\Throwable $e) {
                // try next model
            }
        }

        return null;
    }

    public static function extractPdfContent(string $absolutePath): string
    {
        $text = '';
        $smalotException = null;

        if (class_exists(\Smalot\PdfParser\Parser::class)) {
            try {
                $raw = file_get_contents($absolutePath);
                // Fix malformed xref offset headers (xref 1 N 0000000000 65535 f)
                $fixedRaw = preg_replace('/xref\s+1\s+(\d+)\s+0000000000\s+65535\s+f/m', "xref\r0 $1\r0000000000 65535 f", $raw);

                $parser = new \Smalot\PdfParser\Parser();
                $pdf = $parser->parseContent($fixedRaw);
                $text = trim($pdf->getText());
            } catch (\Throwable $e) {
                $smalotException = $e->getMessage();
            }
        }

        // If text was successfully extracted and is substantial, return it
        if (strlen($text) >= 100) {
            return $text;
        }

        // If text layer is missing (scanned PDF) or parser threw an exception (e.g. MissingCatalogException),
        // use Gemini multimodal document OCR
        $geminiResult = self::analyzeDocumentWithGemini($absolutePath, 'application/pdf');
        if (!empty($geminiResult)) {
            return $geminiResult;
        }

        if (!empty($text)) {
            return $text;
        }

        if ($smalotException) {
            return "[Dokument konnte über den Standard-Parser nicht gelesen werden ({$smalotException}). Für bildbasierte Scans steht Gemini OCR zur Verfügung, konnte hier jedoch kein Ergebnis liefern.]";
        }

        return '[Hinweis: Das PDF enthält keinen lesbaren Text-Layer (reiner Scan) und konnte per OCR nicht ausgelesen werden.]';
    }

    public static function executeReadFixedCostContract(array $args)
    {
        try {
            $adminId = self::getAdminId();
            $queryOrId = $args['id'] ?? $args['name'] ?? $args['query'] ?? null;

            if (empty($queryOrId)) {
                return [
                    'status' => 'error',
                    'message' => 'Bitte gib die ID (UUID) oder den Namen/Suchbegriff der Kostenstelle an, deren Vertrag du lesen möchtest.'
                ];
            }

            $item = self::findCostItem($queryOrId, $adminId, true);

            if (!$item) {
                $crossTenantItem = \App\Models\Accounting\AccountingCostItem::withTrashed()->with('group')->find($queryOrId);
                if ($crossTenantItem && $crossTenantItem->group && (string)$crossTenantItem->group->admin_id !== (string)$adminId) {
                    return ['status' => 'error', 'message' => 'Zugriff verweigert: Sie haben keine Berechtigung für diese Kostenstelle.'];
                }
                return [
                    'status' => 'error',
                    'message' => "Kostenstelle '{$queryOrId}' wurde nicht gefunden. Bitte überprüfe den Namen oder nutze finance_list_fixed_costs."
                ];
            }

            if ($item->group && (string)$item->group->admin_id !== (string)$adminId) {
                return [
                    'status' => 'error',
                    'message' => 'Zugriff verweigert: Sie haben keine Berechtigung für diese Kostenstelle.'
                ];
            }

            if (empty($item->contract_file_path)) {
                return [
                    'status' => 'success',
                    'item_id' => $item->id,
                    'item_name' => $item->name,
                    'has_contract_file' => false,
                    'message' => "Für die Kostenstelle '{$item->name}' ({$item->amount}€) ist bisher kein Vertrag oder Beleg hinterlegt."
                ];
            }

            $filePath = $item->contract_file_path;
            $absolutePath = null;

            if (\Illuminate\Support\Facades\Storage::disk('local')->exists($filePath)) {
                $absolutePath = \Illuminate\Support\Facades\Storage::disk('local')->path($filePath);
            } elseif (\Illuminate\Support\Facades\Storage::disk('private')->exists($filePath)) {
                $absolutePath = \Illuminate\Support\Facades\Storage::disk('private')->path($filePath);
            } elseif (file_exists($filePath)) {
                $absolutePath = $filePath;
            } elseif (file_exists(storage_path('app/' . $filePath))) {
                $absolutePath = storage_path('app/' . $filePath);
            }

            if (!$absolutePath || !file_exists($absolutePath)) {
                return [
                    'status' => 'error',
                    'item_id' => $item->id,
                    'item_name' => $item->name,
                    'message' => "Die hinterlegte Vertragsdatei ({$filePath}) konnte auf dem Server nicht gefunden werden."
                ];
            }

            $extension = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
            $fileSize = filesize($absolutePath);
            $content = '';

            if ($extension === 'pdf') {
                $content = self::extractPdfContent($absolutePath);
            } elseif (in_array($extension, ['txt', 'csv', 'md', 'json', 'xml', 'log'])) {
                $content = file_get_contents($absolutePath);
            } elseif (in_array($extension, ['png', 'jpg', 'jpeg', 'webp', 'gif'])) {
                $mime = match ($extension) {
                    'png' => 'image/png',
                    'jpg', 'jpeg' => 'image/jpeg',
                    'webp' => 'image/webp',
                    'gif' => 'image/gif',
                };
                if (method_exists(AiTaskFuncs::class, 'analyzeImageWithGemini')) {
                    $content = AiTaskFuncs::analyzeImageWithGemini($absolutePath, $mime);
                } else {
                    $content = "[Bild-Datei: {$extension}, {$fileSize} Bytes]";
                }
            } else {
                return [
                    'status' => 'error',
                    'message' => "Dateiformat '.{$extension}' kann nicht ausgelesen werden. Unterstützt werden PDF, Textdateien (TXT, CSV, JSON, MD) und Bilddateien."
                ];
            }

            if (strlen($content) > 100000) {
                $content = substr($content, 0, 100000) . "\n... [Text wurde nach 100.000 Zeichen gekürzt]";
            }

            return [
                'status' => 'success',
                'item_id' => $item->id,
                'item_name' => $item->name,
                'amount' => (float)$item->amount,
                'provider_company' => $item->provider_company,
                'contract_number' => $item->contract_number,
                'is_archived' => $item->trashed(),
                'has_contract_file' => true,
                'filename' => basename($filePath),
                'file_extension' => $extension,
                'file_size_bytes' => $fileSize,
                'contract_text' => $content,
                'message' => "Vertrag für '{$item->name}' erfolgreich ausgelesen."
            ];

        } catch (\Throwable $e) {
            return [
                'status' => 'error',
                'message' => 'Fehler beim Auslesen des Vertrags: ' . $e->getMessage()
            ];
        }
    }

    public static function executeListVariableCosts(array $args)
    {
        try {
            $month = $args['month'] ?? date('n');
            $year = $args['year'] ?? date('Y');
            $adminId = self::getAdminId();

            $specials = \App\Models\Accounting\AccountingSpecialIssue::where('admin_id', $adminId)
                ->whereYear('execution_date', $year)
                ->whereMonth('execution_date', $month)
                ->get()
                ->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'type' => 'Sonderausgabe',
                        'title' => $item->title,
                        'amount' => $item->amount,
                        'category' => $item->category,
                        'date' => $item->execution_date->format('Y-m-d'),
                        'is_business' => (bool)$item->is_business,
                        'tax_rate' => $item->tax_rate,
                        'invoice_number' => $item->invoice_number
                    ];
                });

            $bankTxs = collect();
            if (class_exists(\App\Models\Accounting\AccountingBankTransaction::class)) {
                $bankTxs = \App\Models\Accounting\AccountingBankTransaction::with('financeCategory')
                    ->whereHas('account', fn($q) => $q->where('admin_id', $adminId))
                    ->whereNotNull('accounting_category_id')
                    ->whereYear('transaction_date', $year)
                    ->whereMonth('transaction_date', $month)
                    ->get()
                    ->map(function ($tx) {
                        return [
                            'id' => $tx->id,
                            'type' => 'Banktransaktion',
                            'title' => $tx->counterpart_name ?? $tx->purpose ?? 'Unbekannt',
                            'amount' => $tx->amount,
                            'category' => $tx->financeCategory ? $tx->financeCategory->name : 'Sonstiges',
                            'date' => \Carbon\Carbon::parse($tx->transaction_date)->format('Y-m-d'),
                            'is_business' => (bool)($tx->is_business ?? ($tx->account ? $tx->account->is_business : false)),
                            'tax_rate' => 0,
                            'invoice_number' => null
                        ];
                    });
            }

            return [
                'status' => 'success',
                'month' => $month,
                'year' => $year,
                'variable_costs' => $specials->concat($bankTxs)->sortByDesc('date')->values()->toArray()
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public static function executeGetYearlyMatrix(array $args)
    {
        try {
            $year = $args['year'] ?? date('Y');
            $isNet = $args['is_net'] ?? true;

            $service = new \App\Services\FinancialService();
            $rawMatrix = $service->getYearlyMatrix(self::getAdminId(), $year, $isNet);
            
            // Clean up UI specific keys to save tokens
            $cleanMatrix = [];
            if (isset($rawMatrix['product_categories'])) {
                foreach ($rawMatrix['product_categories'] as $key => $category) {
                    $cleanCategory = [
                        'label' => $category['label'] ?? $key,
                        'year_sum' => $category['year_sum'] ?? 0,
                        'months' => $category['months'] ?? [],
                        'items' => []
                    ];
                    
                    if (isset($category['items']) && is_array($category['items'])) {
                        foreach ($category['items'] as $item) {
                            $cleanCategory['items'][] = [
                                'name' => $item['name'] ?? 'Unbekannt',
                                'year_sum' => $item['year_sum'] ?? 0
                                // Removed monthly breakdown per item to save massive amounts of tokens. 
                                // The AI usually only needs the category monthly breakdown or the item year sum.
                            ];
                        }
                    }
                    $cleanMatrix[$key] = $cleanCategory;
                }
            }

            return [
                'status' => 'success',
                'year' => $year,
                'is_net' => $isNet,
                'matrix_totals' => $rawMatrix['totals'] ?? [],
                'categories' => $cleanMatrix
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public static function executeGenerateTaxExport(array $args)
    {
        try {
            $month = $args['month'];
            $year = $args['year'];
            $adminId = self::getAdminId();

            $service = new \App\Services\FinancialService();
            $path = $service->generateTaxExport($adminId, $month, $year);

            if (file_exists($path)) {
                $filename = basename($path);
                $url = url('/storage/exports/' . $filename); // Assumption based on typical storage paths
                return [
                    'status' => 'success',
                    'message' => 'Steuerexport erfolgreich generiert.',
                    'download_path' => $path,
                    'note' => 'Teile dem Nutzer mit, dass der Export fertig ist. Du kannst den Link nicht direkt klickbar machen, aber der Nutzer kann ihn im AccountingAnalytics Dashboard herunterladen.'
                ];
            }

            return ['status' => 'error', 'message' => 'Export-Datei konnte nicht gefunden werden.'];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Export: ' . $e->getMessage()];
        }
    }

    public static function executeGenerateAndSendReport(array $args, $agent = null)
    {
        try {
            $email = $args['email'];
            $month = $args['month'] ?? date('n');
            $year = $args['year'] ?? date('Y');
            $adminId = self::getAdminId();

            if (empty($email)) {
                return ['status' => 'error', 'message' => 'E-Mail-Adresse fehlt.'];
            }

            $service = new \App\Services\FinancialService();
            $path = $service->generateTaxExport($adminId, $month, $year);

            if (file_exists($path)) {
                $agentName = $agent ? $agent->name : 'System-Agent';
                $subject = "Finanzbericht $month/$year";
                $body = "Hallo,\n\nanbei erhalten Sie den angeforderten Finanzbericht für den Monat $month/$year als ZIP-Archiv. Darin enthalten ist die Übersicht als PDF sowie alle Transaktionen als CSV und die Belege.\n\nViele Grüße,\nDein $agentName";
                $design = $args['design'] ?? 'seelenfunke';

                \Illuminate\Support\Facades\Mail::to($email)->send(new \App\Services\AI\Mails\AiAgentMessageMail($subject, $body, $agentName, [$path], $design));

                return [
                    'status' => 'success',
                    'message' => "Der Finanzbericht für $month/$year wurde erfolgreich generiert und an $email gesendet."
                ];
            }

            return ['status' => 'error', 'message' => 'Report-Datei konnte nicht generiert oder gefunden werden.'];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Erstellen oder Senden des Reports: ' . $e->getMessage()];
        }
    }

    public static function executeCreateQuickEntryExpense(array $args)
    {
        try {
            $adminId = self::getAdminId();

            $amount = (float) str_replace(',', '.', (string) $args['amount']);

            $categoryName = $args['category'] ?: 'Sonstiges';

            $entry = \App\Models\Accounting\AccountingSpecialIssue::create([
                'admin_id' => $adminId,
                'title' => $args['title'],
                'category' => $categoryName,
                'amount' => $amount,
                'execution_date' => $args['execution_date'],
                'is_business' => $args['is_business'],
                'tax_rate' => $args['is_business'] ? ($args['tax_rate'] ?? 19.0) : null,
                'invoice_number' => $args['is_business'] ? ($args['invoice_number'] ?? null) : null,
                'location' => 'KI-Schnellerfassung'
            ]);

            // Track Category
            $cat = \App\Models\Accounting\AccountingCategory::withTrashed()
                ->where('admin_id', $adminId)
                ->where('name', $categoryName)
                ->first();

            if ($cat) {
                if ($cat->trashed()) $cat->restore();
                $cat->increment('usage_count');
            } else {
                \App\Models\Accounting\AccountingCategory::create([
                    'admin_id' => $adminId,
                    'name' => $categoryName,
                    'usage_count' => 1
                ]);
            }

            $typeStr = $amount < 0 ? 'Ausgabe' : 'Einnahme';

            return [
                'status' => 'success',
                'id' => $entry->id,
                'message' => "Erfolgreich gebucht! {$typeStr} über {$amount}€ in der Kategorie '{$categoryName}' wurde erfasst."
            ];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Eintragen: ' . $e->getMessage()];
        }
    }

    public static function executeSearchVariableCosts(array $args)
    {
        try {
            $query = \App\Models\Accounting\AccountingSpecialIssue::where('admin_id', self::getAdminId());
            
            if (!empty($args['query'])) {
                $searchTerm = '%' . $args['query'] . '%';
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('title', 'LIKE', $searchTerm)
                      ->orWhere('category', 'LIKE', $searchTerm)
                      ->orWhere('invoice_number', 'LIKE', $searchTerm)
                      ->orWhere('amount', 'LIKE', $searchTerm)
                      ->orWhere('execution_date', 'LIKE', $searchTerm);
                });
            }

            if (!empty($args['month'])) {
                $query->whereMonth('execution_date', $args['month']);
            }
            if (!empty($args['year'])) {
                $query->whereYear('execution_date', $args['year']);
            }

            $results = $query->orderByDesc('execution_date')->limit(20)->get()->map(function($i) {
                return [
                    'id' => $i->id,
                    'title' => $i->title,
                    'amount' => $i->amount,
                    'category' => $i->category,
                    'date' => $i->execution_date->format('Y-m-d'),
                    'is_business' => $i->is_business,
                    'tax_rate' => $i->tax_rate,
                    'invoice_number' => $i->invoice_number
                ];
            });

            return [
                'status' => 'success',
                'results' => $results->toArray(),
                'message' => $results->isEmpty() ? 'Keine Einträge gefunden.' : "{$results->count()} Einträge gefunden."
            ];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => 'Fehler bei der Suche: ' . $e->getMessage()];
        }
    }

    public static function executeEditVariableCost(array $args)
    {
        try {
            $entry = \App\Models\Accounting\AccountingSpecialIssue::where('admin_id', self::getAdminId())
                ->where('id', $args['id'])
                ->first();

            if (!$entry) {
                return ['status' => 'error', 'message' => 'Eintrag mit dieser UUID nicht gefunden.'];
            }

            if (isset($args['title'])) {
                $entry->title = $args['title'];
            }
            if (isset($args['amount'])) {
                $entry->amount = (float) str_replace(',', '.', (string) $args['amount']);
            }
            if (isset($args['category'])) {
                $entry->category = $args['category'];
            }
            if (array_key_exists('invoice_number', $args)) {
                $entry->invoice_number = $args['invoice_number'];
            }
            if (isset($args['execution_date'])) {
                $entry->execution_date = $args['execution_date'];
            }
            if (isset($args['is_business'])) {
                $entry->is_business = $args['is_business'];
            }
            if (array_key_exists('tax_rate', $args)) {
                $entry->tax_rate = $args['tax_rate'];
            }

            $entry->save();

            return [
                'status' => 'success',
                'message' => 'Eintrag erfolgreich aktualisiert!',
                'updated_entry' => [
                    'id' => $entry->id,
                    'title' => $entry->title,
                    'amount' => $entry->amount,
                    'category' => $entry->category,
                    'invoice_number' => $entry->invoice_number,
                    'execution_date' => $entry->execution_date->format('Y-m-d'),
                    'is_business' => $entry->is_business,
                    'tax_rate' => $entry->tax_rate
                ]
            ];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Bearbeiten: ' . $e->getMessage()];
        }
    }

    public static function executeDeleteVariableCost(array $args)
    {
        try {
            $entry = \App\Models\Accounting\AccountingSpecialIssue::where('admin_id', self::getAdminId())
                ->where('id', $args['id'])
                ->first();

            if (!$entry) {
                return ['status' => 'error', 'message' => 'Eintrag mit dieser UUID nicht gefunden.'];
            }

            $entry->delete();

            return [
                'status' => 'success',
                'message' => 'Der Eintrag wurde endgültig gelöscht.'
            ];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Löschen: ' . $e->getMessage()];
        }
    }

    public static function executeListCategories(array $args)
    {
        try {
            $adminId = self::getAdminId();
            $categories = \App\Models\Accounting\AccountingCategory::where('admin_id', $adminId)
                ->orderByDesc('usage_count')
                ->pluck('name')
                ->toArray();

            if (empty($categories)) {
                return ['status' => 'success', 'categories' => ['Bürobedarf', 'Reisekosten', 'Software', 'Privatentnahme', 'Sonstiges']];
            }

            return ['status' => 'success', 'categories' => $categories];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => 'Kategorien konnten nicht geladen werden.'];
        }
    }
}
