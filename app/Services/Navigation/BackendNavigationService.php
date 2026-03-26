<?php

namespace App\Services\Navigation;

use Illuminate\Support\Str;

class BackendNavigationService
{
    /**
     * The master configuration for the backend navigation.
     * Contains sections, groups, and singular items.
     */
    public static function getStructure(): array
    {
        return [
            [
                'section' => null, // No headline
                'items' => [
                    [
                        'id' => 'dashboard',
                        'type' => 'single',
                        'title' => 'Dashboard',
                        'route' => '/admin/dashboard',
                        'icon' => 'squares-2x2'
                    ],
                    [
                        'id' => 'ceo',
                        'type' => 'group',
                        'title' => 'Firmenleitung',
                        'icon' => 'bolt',
                        'ai_department_id' => '019d0000-0000-0000-0000-000000000000',
                        'children' => [
                            ['id' => 'inbox', 'title' => 'E-Mail', 'route' => '/admin/inbox', 'icon' => 'envelope-open'],
                            ['id' => 'person-profiles', 'title' => 'Kontakte', 'route' => '/admin/person-profiles', 'icon' => 'users'],
                            ['id' => 'routine', 'title' => 'Routine', 'route' => '/admin/routine', 'icon' => 'arrow-path'],
                            ['id' => 'tasks', 'title' => 'Aufgaben', 'route' => '/admin/tasks', 'icon' => 'check-circle'],
                            ['id' => 'calender', 'title' => 'Kalender', 'route' => '/admin/calender', 'icon' => 'calendar-days'],
                            ['id' => 'gesundheit', 'title' => 'Gesundheit', 'route' => '/admin/ceo/gesundheit', 'icon' => 'heart'],
                        ]
                    ]
                ]
            ],
            [
                'section' => 'Shopverwaltung',
                'items' => [
                    [
                        'id' => 'products',
                        'type' => 'group',
                        'title' => 'Produkte',
                        'icon' => 'cube',
                        'ai_department_id' => '019d1111-1111-1111-1111-111111111111',
                        'children' => [
                            ['id' => 'product-analytics', 'title' => 'Analyse', 'route' => '/admin/product-analytics', 'icon' => 'chart-pie'],
                            ['id' => 'product-fracture', 'title' => 'Schaden', 'route' => '/admin/product-fracture', 'icon' => 'exclamation-triangle'],
                            ['id' => 'products', 'title' => 'Produkte', 'route' => '/admin/products', 'icon' => 'cube'],
                            ['id' => 'product-templates', 'title' => 'Vorlagen', 'route' => '/admin/product-templates', 'icon' => 'clipboard-document-list'],
                            ['id' => 'product-suppliers', 'title' => 'Lieferanten', 'route' => '/admin/product-suppliers', 'icon' => 'truck'],
                            ['id' => 'reviews', 'title' => 'Bewertungen', 'route' => '/admin/reviews', 'icon' => 'star'],
                            ['id' => 'nischen-scout', 'title' => 'Nischen-Scout', 'route' => '/admin/products/nischen-scout', 'icon' => 'magnifying-glass'],
                            ['id' => 'product-packaging', 'title' => 'Verpackungsmaterial', 'route' => '/admin/product-packaging', 'icon' => 'archive-box'],
                        ]
                    ],
                    [
                        'id' => 'marketing',
                        'type' => 'group',
                        'title' => 'Marketing',
                        'icon' => 'at-symbol',
                        'ai_department_id' => '019d2222-2222-2222-2222-222222222222',
                        'children' => [
                            ['id' => 'newsletter', 'title' => 'Newsletter', 'route' => '/admin/newsletter', 'icon' => 'newspaper'],
                            ['id' => 'voucher', 'title' => 'Gutscheine', 'route' => '/admin/voucher', 'icon' => 'gift'],
                            ['id' => 'blog', 'title' => 'Blogeinträge', 'route' => '/admin/blog', 'icon' => 'document-text'],
                        ]
                    ],
                    [
                        'id' => 'orders',
                        'type' => 'group',
                        'title' => 'Bestellungen',
                        'icon' => 'shopping-bag',
                        'ai_department_id' => '019d3333-3333-3333-3333-333333333333',
                        'children' => [
                            ['id' => 'orders', 'title' => 'Bestellungen', 'route' => '/admin/orders', 'icon' => 'shopping-cart'],
                            ['id' => 'quote-requests', 'title' => 'Angebote', 'route' => '/admin/quote-requests', 'icon' => 'clipboard-document-list'],
                            ['id' => 'widerruf', 'title' => 'Widerrufe', 'route' => '/admin/widerruf', 'icon' => 'archive-box-x-mark'],
                        ]
                    ],
                    [
                        'id' => 'finance',
                        'type' => 'group',
                        'title' => 'Buchhaltung',
                        'icon' => 'currency-dollar',
                        'ai_department_id' => '019d4444-4444-4444-4444-444444444444',
                        'children' => [
                            ['id' => 'financial-banks', 'title' => 'Banken', 'route' => '/admin/financial-banks', 'icon' => 'scale'],
                            ['id' => 'financial-tax', 'title' => 'Steuern', 'route' => '/admin/financial-tax', 'icon' => 'banknotes'],
                            ['id' => 'financial-fix-costs', 'title' => 'Fixkosten', 'route' => '/admin/financial-fix-costs', 'icon' => 'banknotes'],
                            ['id' => 'financial-evaluation', 'title' => 'Auswertung', 'route' => '/admin/financial-evaluation', 'icon' => 'chart-bar'],
                            ['id' => 'credit-management', 'title' => 'Gutschriften', 'route' => '/admin/credit-management', 'icon' => 'document-minus'],
                            ['id' => 'invoices', 'title' => 'Rechnungen', 'route' => '/admin/invoices', 'icon' => 'document-text'],
                            ['id' => 'financial-variable-costs', 'title' => 'Variable Kosten', 'route' => '/admin/financial-variable-costs', 'icon' => 'banknotes'],
                            ['id' => 'financial-liquidity-planning', 'title' => 'Liquiditätsplanung', 'route' => '/admin/financial-liquidity-planning', 'icon' => 'shield-check'],
                        ]
                    ]
                ]
            ],
            [
                'section' => 'Systemsteuerung',
                'items' => [
                    [
                        'id' => 'system_ai',
                        'type' => 'group',
                        'title' => 'Ai Agents',
                        'icon' => 'cpu-chip',
                        'children' => [
                            ['id' => 'ai-analytics', 'title' => 'Analyse', 'route' => '/admin/ai-analytics', 'icon' => 'chart-bar'],
                            ['id' => 'rollen', 'title' => 'Rollen', 'route' => '/admin/rollen', 'icon' => 'briefcase'],
                            ['id' => 'agenten', 'title' => 'Agenten', 'route' => '/admin/agenten', 'icon' => 'cpu-chip'],
                            ['id' => 'organigramm', 'title' => 'Organigramm', 'route' => '/admin/organigramm', 'icon' => 'building-office-2'],
                            ['id' => 'ai-chat', 'title' => 'Chat', 'route' => '/admin/ai-chat', 'icon' => 'chat-bubble-left-ellipsis'],
                            ['id' => 'ai-knowledge_base', 'title' => 'Wiki', 'route' => '/admin/ai-knowledge_base', 'icon' => 'book-open'],
                            ['id' => 'ai-genui', 'title' => 'Gen-Ui', 'route' => '/admin/ai-genui', 'icon' => 'window'],
                        ]
                    ],
                    [
                        'id' => 'system',
                        'type' => 'group',
                        'title' => 'System',
                        'icon' => 'server',
                        'ai_department_id' => '019d5555-5555-5555-5555-555555555555',
                        'children' => [
                            ['id' => 'global-logs', 'title' => 'Log', 'route' => '/admin/global-logs', 'icon' => 'server-stack'],
                            ['id' => 'tickets', 'title' => 'Tickets', 'route' => '/admin/tickets', 'icon' => 'ticket', 'is_ticket' => true],
                            ['id' => 'user-management', 'title' => 'Benutzer', 'route' => '/admin/user-management', 'icon' => 'users'],
                            ['id' => 'company-map', 'title' => 'Architektur-Map', 'route' => '/admin/company-map', 'icon' => 'map'],
                            ['id' => 'system-info', 'title' => 'System-Info', 'route' => '/admin/system-info', 'icon' => 'server'],
                            ['id' => 'configuration', 'title' => 'Einstellungen', 'route' => '/admin/configuration', 'icon' => 'cog-8-tooth'],
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Tries to find the current active breadcrumb path based on the current URL.
     */
    public static function getBreadcrumbs(string $currentPath, string $guard = 'admin'): array
    {
        $structure = self::getStructure();
        $baseCrumb = 'Systemverwaltung / ' . ucfirst($guard);
        $normalizedPath = '/' . ltrim($currentPath, '/'); // e.g., /admin/dashboard

        $bestMatch = null;
        $bestMatchLength = 0;

        foreach ($structure as $section) {
            foreach ($section['items'] as $item) {
                if ($item['type'] === 'group') {
                    foreach ($item['children'] as $child) {
                        if ($normalizedPath === $child['route']) {
                            // Exact match
                            return [
                                'text' => $baseCrumb . ' / ' . $item['title'] . ' / ' . $child['title'],
                                'item' => $child,
                                'group' => $item
                            ];
                        } elseif (str_starts_with($normalizedPath, $child['route'] . '/') || str_starts_with($normalizedPath, $child['route'] . '?')) {
                            // Prefix match (must be followed by slash or query to avoid /admin/agenten matching /admin/agenten-rollen)
                            $len = strlen($child['route']);
                            if ($len > $bestMatchLength) {
                                $bestMatchLength = $len;
                                $bestMatch = [
                                    'text' => $baseCrumb . ' / ' . $item['title'] . ' / ' . $child['title'],
                                    'item' => $child,
                                    'group' => $item
                                ];
                            }
                        }
                    }
                } elseif ($item['type'] === 'single') {
                    if ($normalizedPath === $item['route']) {
                        return [
                            'text' => $baseCrumb . ' / ' . $item['title'],
                            'item' => $item
                        ];
                    } elseif (str_starts_with($normalizedPath, $item['route'] . '/') || str_starts_with($normalizedPath, $item['route'] . '?')) {
                        $len = strlen($item['route']);
                        if ($len > $bestMatchLength) {
                            $bestMatchLength = $len;
                            $bestMatch = [
                                'text' => $baseCrumb . ' / ' . $item['title'],
                                'item' => $item
                            ];
                        }
                    }
                }
            }
        }

        if ($bestMatch) {
            return $bestMatch;
        }

        // Special handling if current path equals exact group route (if applicable) or fallback
        return [
            'text' => $baseCrumb
        ];
    }

    /**
     * Checks if a specific group should be active (open) based on the current path.
     */
    public static function isGroupActive(array $group, string $currentPath): bool
    {
        $normalizedPath = '/' . ltrim($currentPath, '/');

        // Custom logic for /admin/organigramm
        if ($group['id'] === 'system_ai' && Str::startsWith($normalizedPath, '/admin/organigramm')) {
            return true;
        }

        foreach ($group['children'] as $child) {
            if ($child['route'] === $normalizedPath || str_starts_with($normalizedPath, $child['route'])) {
                return true;
            }
        }
        return false;
    }

    /**
     * Generates a text representation of the navigation tree for AI prompts.
     */
    public static function getAiNavigationPrompt(): string
    {
        $structure = self::getStructure();
        $prompt = "";

        foreach ($structure as $section) {
            foreach ($section['items'] as $item) {
                if ($item['type'] === 'single') {
                    $prompt .= "- \"" . $item['route'] . "\": " . $item['title'] . "\n";
                } elseif ($item['type'] === 'group') {
                    $prompt .= "[" . $item['title'] . "]\n";
                    foreach ($item['children'] as $child) {
                        $prompt .= "- \"" . $child['route'] . "\": " . $child['title'] . "\n";
                    }
                }
            }
        }

        return $prompt;
    }
}
