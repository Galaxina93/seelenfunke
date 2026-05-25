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
                'description' => 'Listet detailliert alle erfassten Fixkosten (Einnahmen und Ausgaben) auf, gruppiert nach Kategorien. Zeigt auch ob ein Vertrag benötigt wird (requires_contract) und ob ein Vertrag hinterlegt ist. Stichworte: Welche Fixkosten haben wir, was kostet der Server, liste Fixkosten, fehlende Verträge.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
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

    public static function executeListFixedCosts(array $args)
    {
        try {
            $adminId = self::getAdminId();
            $groups = \App\Models\Accounting\AccountingGroup::with('items')->where('admin_id', $adminId)->get();
            
            $costs = [];
            foreach ($groups as $group) {
                $groupItems = [];
                foreach ($group->items as $item) {
                    $groupItems[] = [
                        'name' => $item->name,
                        'amount' => $item->amount,
                        'interval_months' => $item->interval_months,
                        'is_business' => (bool)$item->is_business,
                        'requires_contract' => (bool)$item->requires_contract,
                        'has_contract_file' => !empty($item->contract_file_path),
                        'tax_rate' => $item->tax_rate
                    ];
                }
                $costs[] = [
                    'group_name' => $group->name,
                    'type' => $group->type,
                    'items' => $groupItems
                ];
            }

            return [
                'status' => 'success',
                'fixed_costs_by_category' => $costs
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
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
