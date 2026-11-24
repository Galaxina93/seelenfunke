<?php

namespace App\Services\System;

use App\Models\Accounting\AccountingBankTransaction;
use App\Models\Accounting\AccountingCostItem;
use App\Models\Accounting\AccountingInvoice;
use App\Models\Accounting\AccountingSpecialIssue;
use App\Models\Ai\AiKnowledgeBase;
use App\Models\Management\ManagementContact;
use App\Models\Management\ManagementTask;
use App\Models\Order\OrderOrder;
use App\Models\Product\Product;
use App\Models\Product\ProductSupplier;
use App\Models\System\SystemSetting;
use App\Services\AI\ConceptLifecycleService;
use App\Services\Navigation\BackendNavigationService;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class GlobalProjectSearchService
{
    /**
     * Vollstaendiger Katalog aller Einstellungen in der Shop-Konfiguration (/admin/configuration)
     * fuer 0-ms-Soforttreffer, Synonym-Abgleich und Deep-Links (?tab=...&focus=...).
     *
     * @return array<int, array<string, string>>
     */
    public static function getShopConfigCatalog(): array
    {
        return [
            // TAB 1: Allgemein & Steuern
            [
                'key' => 'shop_is_live',
                'title' => 'Personalisierter Shop (Livegang-Schalter)',
                'tab' => 'general',
                'tab_label' => 'Allgemein & Steuern',
                'section' => 'Shop-Domains & Livegang-Status',
                'keywords' => 'live gehen livegang online stellen verkaufsstart mein-seelenfunke freischalten vorbereitung',
            ],
            [
                'key' => 'maintenance_mode',
                'title' => 'Wartungsmodus (Schaufenster)',
                'tab' => 'general',
                'tab_label' => 'Allgemein & Steuern',
                'section' => 'Shop-Domains & Livegang-Status',
                'keywords' => 'wartung kauf pausieren checkout sperren urlaubspause schaufenster offline',
            ],
            [
                'key' => 'showroom_multiplayer_enabled',
                'title' => '3D-Multiplayer (Halle & Chat)',
                'tab' => 'general',
                'tab_label' => 'Allgemein & Steuern',
                'section' => 'Shop-Domains & Livegang-Status',
                'keywords' => '3d welt wasserstadt halle der helden raum chat mehrspieler solo modus showroom',
            ],
            [
                'key' => 'quote_calculator_enabled',
                'title' => 'B2B-Angebotskalkulator (/calculator)',
                'tab' => 'general',
                'tab_label' => 'Allgemein & Steuern',
                'section' => 'Shop-Domains & Livegang-Status',
                'keywords' => 'angebot kalkulator firmenkunden b2b anfrage feature schalter',
            ],
            [
                'key' => 'is_small_business',
                'title' => 'Kleinunternehmer-Regelung (§ 19 UStG)',
                'tab' => 'general',
                'tab_label' => 'Allgemein & Steuern',
                'section' => 'Steuern & Benachrichtigungen',
                'keywords' => 'kleinunternehmer regelbesteuerung umsatzsteuer mehrwertsteuer 19 ustg steuerwechsel',
            ],
            [
                'key' => 'default_tax_rate',
                'title' => 'Regulärer Steuersatz (19 %)',
                'tab' => 'general',
                'tab_label' => 'Allgemein & Steuern',
                'section' => 'Steuern & Benachrichtigungen',
                'keywords' => 'mwst mehrwertsteuer umsatzsteuer steuersatz 19 prozent standard',
            ],
            [
                'key' => 'reduced_tax_rate',
                'title' => 'Ermäßigter Steuersatz (7 %)',
                'tab' => 'general',
                'tab_label' => 'Allgemein & Steuern',
                'section' => 'Steuern & Benachrichtigungen',
                'keywords' => 'mwst ermaessigt 7 prozent umsatzsteuer steuersatz',
            ],
            [
                'key' => 'send_order_email_to_admin',
                'title' => 'Bestell-Benachrichtigung per E-Mail an Admin',
                'tab' => 'general',
                'tab_label' => 'Allgemein & Steuern',
                'section' => 'Steuern & Benachrichtigungen',
                'keywords' => 'email benachrichtigung neue bestellung kauf alarm admin mail',
            ],
            [
                'key' => 'order_quote_validity_days',
                'title' => 'Angebots-Bindungsfrist (Tage)',
                'tab' => 'general',
                'tab_label' => 'Allgemein & Steuern',
                'section' => 'Kalkulation & Gebühren',
                'keywords' => 'angebot gueltigkeit tage frist b2b',
            ],
            [
                'key' => 'herzensprojekt_cents_per_order',
                'title' => 'Herzensprojekt-Rücklage pro Artikel (€)',
                'tab' => 'general',
                'tab_label' => 'Allgemein & Steuern',
                'section' => 'Kalkulation & Gebühren',
                'keywords' => 'spende kinderprojekte herzensprojekt ruecklage soziales',
            ],
            [
                'key' => 'stripe_fee_percent',
                'title' => 'Stripe Zahlungsgebühr (%)',
                'tab' => 'general',
                'tab_label' => 'Allgemein & Steuern',
                'section' => 'Kalkulation & Gebühren',
                'keywords' => 'stripe kreditkarte paypal klarna gebuehr marge zahlungsanbieter prozent',
            ],
            [
                'key' => 'stripe_fee_fixed_cents',
                'title' => 'Stripe Fixgebühr pro Transaktion (€)',
                'tab' => 'general',
                'tab_label' => 'Allgemein & Steuern',
                'section' => 'Kalkulation & Gebühren',
                'keywords' => 'stripe fixgebuehr cent transaktion zahlung marge',
            ],

            // TAB 2: Produkt & Marketing
            [
                'key' => 'inventory_low_stock_threshold',
                'title' => 'Mindestbestand / Warn-Limit (Lager)',
                'tab' => 'products',
                'tab_label' => 'Produkt & Marketing',
                'section' => 'Lager & Preislogik',
                'keywords' => 'lagerbestand warnung rohlinge nachbestellen limit knappheit bestand',
            ],
            [
                'key' => 'skip_shipping_for_digital',
                'title' => 'Versand für digitale Produkte überspringen',
                'tab' => 'products',
                'tab_label' => 'Produkt & Marketing',
                'section' => 'Lager & Preislogik',
                'keywords' => 'digital download pdf gutschein versandfrei',
            ],
            [
                'key' => 'prices_entered_gross',
                'title' => 'Bruttopreis-Pflege im Admin',
                'tab' => 'products',
                'tab_label' => 'Produkt & Marketing',
                'section' => 'Lager & Preislogik',
                'keywords' => 'brutto netto preise eingabe inkl mwst',
            ],
            [
                'key' => 'google_tag_enabled',
                'title' => 'Google Ads & Conversion-Tracking aktiv',
                'tab' => 'products',
                'tab_label' => 'Produkt & Marketing',
                'section' => 'Google Ads & Merchant Center',
                'keywords' => 'google ads gtag pixel tracking marketing werbung analytics',
            ],
            [
                'key' => 'google_shopping_require_real_photos',
                'title' => 'Google Shopping Echtfoto-Schutzschranke',
                'tab' => 'products',
                'tab_label' => 'Produkt & Marketing',
                'section' => 'Google Ads & Merchant Center',
                'keywords' => 'merchant center produktfeed ki bilder sperre echte produktfotos shopping',
            ],
            [
                'key' => 'google_tag_id',
                'title' => 'Google Tag ID (AW-...)',
                'tab' => 'products',
                'tab_label' => 'Produkt & Marketing',
                'section' => 'Google Ads & Merchant Center',
                'keywords' => 'google tag aw id conversion',
            ],
            [
                'key' => 'google_ads_conversion_label',
                'title' => 'Google Ads Conversion-Label',
                'tab' => 'products',
                'tab_label' => 'Produkt & Marketing',
                'section' => 'Google Ads & Merchant Center',
                'keywords' => 'conversion label kauf abschluss google ads',
            ],

            // TAB 3: Versand & Lieferzeit
            [
                'key' => 'shipping_cost',
                'title' => 'Standard-Versandkosten (€)',
                'tab' => 'shipping',
                'tab_label' => 'Versand & Lieferzeit',
                'section' => 'Versandkosten & Express',
                'keywords' => 'porto dhl paket versandpreis lieferkosten standardversand',
            ],
            [
                'key' => 'shipping_free_threshold',
                'title' => 'Versandkostenfrei ab (€)',
                'tab' => 'shipping',
                'tab_label' => 'Versand & Lieferzeit',
                'section' => 'Versandkosten & Express',
                'keywords' => 'versandfrei freigrenze kostenloser versand warenkorbwert gratisversand',
            ],
            [
                'key' => 'shipping_cost_voucher',
                'title' => 'Versandkosten physische Gutscheinkarte (€)',
                'tab' => 'shipping',
                'tab_label' => 'Versand & Lieferzeit',
                'section' => 'Versandkosten & Express',
                'keywords' => 'gutschein brief grossbrief umschlag porto versand',
            ],
            [
                'key' => 'express_surcharge_percent',
                'title' => 'Express-Zuschlag (%)',
                'tab' => 'shipping',
                'tab_label' => 'Versand & Lieferzeit',
                'section' => 'Versandkosten & Express',
                'keywords' => 'express fertigung eilauftrag aufschlag prozent',
            ],
            [
                'key' => 'express_surcharge_min',
                'title' => 'Express-Mindestzuschlag (€)',
                'tab' => 'shipping',
                'tab_label' => 'Versand & Lieferzeit',
                'section' => 'Versandkosten & Express',
                'keywords' => 'express mindestbetrag aufpreis euro',
            ],
            [
                'key' => 'packaging_weight_grams',
                'title' => 'Leergewicht Versandkarton (g)',
                'tab' => 'shipping',
                'tab_label' => 'Versand & Lieferzeit',
                'section' => 'DHL Gewichte & Puffer',
                'keywords' => 'dhl gewicht karton tara verpackungsgewicht gramm label',
            ],
            [
                'key' => 'packaging_weight_envelope_grams',
                'title' => 'Leergewicht Versand-Umschlag (g)',
                'tab' => 'shipping',
                'tab_label' => 'Versand & Lieferzeit',
                'section' => 'DHL Gewichte & Puffer',
                'keywords' => 'luftpolster umschlag grossbrief gewicht gramm',
            ],

            // TAB 4: Stammdaten
            [
                'key' => 'company_name',
                'title' => 'Firmenname & Geschäftsadresse',
                'tab' => 'owner',
                'tab_label' => 'Stammdaten',
                'section' => 'Unternehmensdaten',
                'keywords' => 'mein seelenfunke firma anschrift strasse plz ort gifhorn impressum rechnung',
            ],
            [
                'key' => 'owner_proprietor',
                'title' => 'Inhaberin & Kontaktdaten',
                'tab' => 'owner',
                'tab_label' => 'Stammdaten',
                'section' => 'Inhaberin',
                'keywords' => 'alina steinhauer inhaber telefon email webseite impressum',
            ],
            [
                'key' => 'owner_tax_id',
                'title' => 'Steuernummer (Finanzamt)',
                'tab' => 'owner',
                'tab_label' => 'Stammdaten',
                'section' => 'Steuern & Behördennummern',
                'keywords' => 'steuernummer finanzamt gifhorn rechnung pflichtangabe elster',
            ],
            [
                'key' => 'owner_ust_id',
                'title' => 'Umsatzsteuer-IdNr. (DE...)',
                'tab' => 'owner',
                'tab_label' => 'Stammdaten',
                'section' => 'Steuern & Behördennummern',
                'keywords' => 'ust idnr umsatzsteuer identifikationsnummer eu reverse charge westpack fbm',
            ],
            [
                'key' => 'owner_economic_ident_nr',
                'title' => 'Wirtschafts-IdNr. & Behörden-Nummern',
                'tab' => 'owner',
                'tab_label' => 'Stammdaten',
                'section' => 'Steuern & Behördennummern',
                'keywords' => 'wirtschafts id steuer id sozialversicherung krankenkasse arbeitsagentur amtsgericht',
            ],
            [
                'key' => 'owner_iban',
                'title' => 'IBAN, BIC & Geschäftsbank (FYRST)',
                'tab' => 'owner',
                'tab_label' => 'Stammdaten',
                'section' => 'Bankverbindung',
                'keywords' => 'iban bic swift bankverbindung fyrst deutsche bank geschäftskonto vorkasse rechnung',
            ],

            // TAB 5: Jahreszeiten
            [
                'key' => 'season_auto_mode',
                'title' => 'Jahreszeiten-Automatik & Shop-Effekte',
                'tab' => 'seasons',
                'tab_label' => 'Jahreszeiten',
                'section' => 'Die 4 Jahreszeiten',
                'keywords' => 'fruehling sommer herbst winter weihnachten schnee blueten animationen saison',
            ],

            // TAB 6: System
            [
                'key' => 'androidVersionName',
                'title' => 'Funkenwerk Android App (OTA-Release & APK)',
                'tab' => 'system',
                'tab_label' => 'System',
                'section' => 'Funkenwerk Android App',
                'keywords' => 'android app apk ota update version build download smartphone',
            ],

            // TAB 7: Cronjobs
            [
                'key' => 'cronjobs',
                'title' => 'Hintergrund-Dienste & Cronjobs',
                'tab' => 'cronjobs',
                'tab_label' => 'Cronjobs',
                'section' => 'System-Automatisierung',
                'keywords' => 'cronjob scheduler zeitplan selbstheilung backup mail sync automatisierung',
            ],

            // TAB 8: Notfallplan
            [
                'key' => 'emergency_keepass_location',
                'title' => 'Notfall-Handbuch, Tresor-Ablage & Kontakte',
                'tab' => 'emergency',
                'tab_label' => 'Notfallplan',
                'section' => 'Notfall-Handbuch & Nachlass',
                'keywords' => 'notfall vorsorge nachlass keepass tresor master passwort notar steuerberater familie pins',
            ],
        ];
    }

    /**
     * Kanonischer Navigations- & Modul-Index fuer alle Admin-Bereiche inkl. Unteransichten & Synonymen.
     *
     * @return array<int, array<string, string>>
     */
    public static function getNavigationCatalog(): array
    {
        $items = [];
        $seenRoutes = [];

        $synonymMap = [
            '/admin/dashboard' => 'kommandozentrale startseite operativer status datenanalysezentrum uebersicht',
            '/admin/tickets' => 'funkira ki universum zentrale tickets agenten chat',
            '/admin/inbox' => 'email postfach webmail nachrichten imap',
            '/admin/contacts' => 'adressbuch kundenkontakte lieferantenkontakte crm',
            '/admin/employees' => 'personal vertraege lohnabrechnung stundenzettel team',
            '/admin/routine' => 'tagesroutine morgenroutine gewohnheiten gesundheit',
            '/admin/tasks' => 'aufgaben todos meine tickets fahrplan livegang',
            '/admin/shopping' => 'einkaufsliste besorgungen vorrat',
            '/admin/calender' => 'kalender termine zeitplan fristen',
            '/admin/music' => 'musikplayer soundplayer playlists songs youtube',
            '/admin/support-tickets' => 'kundensupport kundenanfragen reklamation hilfe',
            '/admin/support-chats' => 'livechat funki kundenchat eskalation',
            '/admin/support/telephony' => 'telefonie anrufe ki telefon twilio',
            '/admin/products' => 'artikel sortiment rohlinge kalkulator varianten bestand',
            '/admin/product-configurator' => '2d 3d konfigurator gravur vorschau laserbereich',
            '/admin/product-crawler' => 'marktrecherche nischen b2b ideen',
            '/admin/marketing/landing-pages' => 'landingpages seo verkaufsseiten',
            '/admin/marketing/herzensprojekte' => 'spendenaktionen wohltätigkeit',
            '/admin/newsletter' => 'kampagnen abonnenten mailings',
            '/admin/voucher' => 'rabattcodes aktionen geschenkgutscheine',
            '/admin/blog' => 'magazin ratgeber artikel seo geo',
            '/admin/marketing/linktree' => 'social media bio links instagram tiktok',
            '/admin/marketing/influencer' => 'creator partner provision auszahlungen',
            '/admin/orders' => 'bestelluebersicht laser cockpit fertigung versand dhl auftraege',
            '/admin/shopping-carts' => 'offene warenkoerbe kaufabbrecher',
            '/admin/quote-requests' => 'b2b angebote grossauftraege anfragen',
            '/admin/widerruf' => 'retouren storno ruecksendungen widerrufe',
            '/admin/financial-analytics' => 'finanzanalyse euer gewinn umsatz auswertung',
            '/admin/financial-banks' => 'bankkonto fyrst csv import kontoumsaetze zuordnung',
            '/admin/financial-tax' => 'steuerberater datev export ustva belege steuern',
            '/admin/financial-fix-costs' => 'vertraege versicherungen abos miete dauerauftraege',
            '/admin/credit-management' => 'gutschriften stornorechnungen erstattungen',
            '/admin/invoices' => 'ausgangsrechnungen rechnungsarchiv pdf e-rechnung',
            '/admin/financial-variable-costs' => 'sonderausgaben belege einkaeufe anschaffungen quittungen',
            '/admin/financial-liquidity-planning' => 'liquiditaet cashflow finanzplanung prognose',
            '/admin/system/neural-analysis' => 'architektur blueprints systemkarte knoten',
            '/admin/global-logs' => 'systemlog fehlerprotokoll ereignisse',
            '/admin/user-management' => 'benutzer konten rollen rechte passwort',
            '/admin/system/backups' => 'datensicherung datenbank backup wiederherstellung',
            '/admin/configuration' => 'shop konfiguration einstellungen systemeinstellungen',
        ];

        $quoteEnabled = true;
        if (Schema::hasTable('shop_settings')) {
            $rawQuote = SystemSetting::where('key', 'quote_calculator_enabled')->value('value');
            if ($rawQuote !== null) {
                $quoteEnabled = filter_var($rawQuote, FILTER_VALIDATE_BOOLEAN);
            }
        }

        foreach (BackendNavigationService::getStructure() as $section) {
            foreach ($section['items'] ?? [] as $item) {
                if (($item['type'] ?? '') === 'single' && !empty($item['route'])) {
                    $route = (string) $item['route'];
                    $seenRoutes[$route] = true;
                    $items[] = [
                        'title' => (string) $item['title'],
                        'subtitle' => 'Hauptbereich',
                        'url' => $route,
                        'keywords' => $synonymMap[$route] ?? '',
                    ];
                } elseif (($item['type'] ?? '') === 'group') {
                    $groupTitle = (string) ($item['title'] ?? '');
                    foreach ($item['children'] ?? [] as $child) {
                        $route = (string) ($child['route'] ?? '');
                        if ($route === '') {
                            continue;
                        }
                        if ($route === '/admin/quote-requests' && !$quoteEnabled) {
                            continue;
                        }
                        $seenRoutes[$route] = true;
                        $items[] = [
                            'title' => $child['title'] === 'Analyse' ? "{$groupTitle} · Analyse" : (string) $child['title'],
                            'subtitle' => $groupTitle,
                            'url' => $route,
                            'keywords' => trim($groupTitle . ' ' . ($synonymMap[$route] ?? '')),
                        ];
                    }
                }
            }
        }

        // Ergaenzende Direkt-Ansichten (Verpackung, Lieferanten, Vorlagen, Wissensgehirn, Konzepte, Organigramm)
        $extraPages = [
            [
                'title' => 'Lieferanten & Rohlinge',
                'subtitle' => 'Produkte · Einkauf',
                'url' => '/admin/product-suppliers',
                'keywords' => 'gluecksfieber westpack printequipment kartonfritze fbm lieferanten b2b einkauf',
            ],
            [
                'title' => 'Verpackungs-Kalkulator & LUCID',
                'subtitle' => 'Produkte · Verpackung',
                'url' => '/admin/product-packaging',
                'keywords' => 'karton schmuckbox geschenkpapier gewicht verpackungslizenz lucid',
            ],
            [
                'title' => 'Anlass- & Produktvorlagen',
                'subtitle' => 'Produkte · Vorlagen',
                'url' => '/admin/product-templates',
                'keywords' => 'templates sprueche designs vorlagen konfigurator',
            ],
            [
                'title' => 'Zentrales Wissensgehirn',
                'subtitle' => 'KI-Universum · Wissen',
                'url' => '/admin/tickets?view=knowledge',
                'keywords' => 'knowledge base abschlussberichte dokumentation gedächtnis regeln',
            ],
            [
                'title' => 'Zentrale Konzept-Verwaltung',
                'subtitle' => 'KI-Universum · Konzepte',
                'url' => '/admin/tickets?view=files&ftype=concept',
                'keywords' => 'konzepte entwuerfe architekturplaene offen geprueft',
            ],
            [
                'title' => 'KI-Organigramm & Agenten',
                'subtitle' => 'KI-Universum · Team',
                'url' => '/admin/tickets?view=organigramm',
                'keywords' => 'falcone funkira buchi bestelli produkti lasi marketi systemi globi einkaufi dr funki',
            ],
        ];

        foreach ($extraPages as $ep) {
            if (!isset($seenRoutes[$ep['url']])) {
                $items[] = $ep;
            }
        }

        return $items;
    }

    /**
     * Fuehrt die globale Admin-Suche ueber Einstellungen, Module und Live-Datenbank-Entitaeten aus.
     *
     * @return array{query: string, total: int, groups: array<int, array<string, mixed>>}
     */
    public function searchAll(string $rawQuery, int $limitPerGroup = 5): array
    {
        $query = trim(preg_replace('/\s+/u', ' ', $rawQuery) ?? '');
        if (mb_strlen($query) < 1) {
            return ['query' => '', 'total' => 0, 'groups' => []];
        }

        $limit = max(2, min(10, $limitPerGroup));
        $tokens = $this->tokenize($query);
        $groups = [];

        // 1. Shop-Einstellungen (0ms In-Memory + Fuzzy)
        $settingHits = $this->matchCatalogItems(
            self::getShopConfigCatalog(),
            $tokens,
            fn (array $item) => $item['title'] . ' ' . $item['section'] . ' ' . $item['tab_label'] . ' ' . $item['keywords'] . ' ' . $item['key'],
            $limit
        );
        if (!empty($settingHits)) {
            $groups[] = [
                'key' => 'settings',
                'label' => 'Shop-Konfiguration',
                'items' => array_map(fn (array $hit) => [
                    'id' => 'cfg_' . $hit['key'],
                    'title' => $hit['title'],
                    'subtitle' => $hit['tab_label'] . ' · ' . $hit['section'],
                    'badge' => 'Einstellung',
                    'url' => '/admin/configuration?tab=' . urlencode($hit['tab']) . '&focus=' . urlencode($hit['key']),
                    'icon' => 'cog',
                ], $settingHits),
            ];
        }

        // 2. Seiten & Module (0ms In-Memory + Fuzzy)
        $navHits = $this->matchCatalogItems(
            self::getNavigationCatalog(),
            $tokens,
            fn (array $item) => $item['title'] . ' ' . $item['subtitle'] . ' ' . $item['keywords'] . ' ' . $item['url'],
            $limit
        );
        if (!empty($navHits)) {
            $groups[] = [
                'key' => 'navigation',
                'label' => 'Seiten & Module',
                'items' => array_map(fn (array $hit) => [
                    'id' => 'nav_' . md5($hit['url']),
                    'title' => $hit['title'],
                    'subtitle' => $hit['subtitle'],
                    'badge' => 'Bereich',
                    'url' => $hit['url'],
                    'icon' => 'compass',
                ], $navHits),
            ];
        }

        // Ab 2 Zeichen zusaetzlich Live-Datenbank-Entitaeten abfragen
        if (mb_strlen($query) >= 2) {
            $escaped = addcslashes($query, '%_\\');
            $like = '%' . $escaped . '%';

            // 3. Bestellungen
            try {
                if (Schema::hasTable('order_orders')) {
                    $orders = OrderOrder::query()
                        ->where(function ($q) use ($like) {
                            $q->where('order_number', 'like', $like)
                                ->orWhere('email', 'like', $like)
                                ->orWhere('billing_address', 'like', $like)
                                ->orWhere('shipping_address', 'like', $like);
                        })
                        ->orderByDesc('created_at')
                        ->limit($limit)
                        ->get(['id', 'order_number', 'email', 'status', 'total_price', 'billing_address', 'created_at']);

                    if ($orders->isNotEmpty()) {
                        $groups[] = [
                            'key' => 'orders',
                            'label' => 'Bestellungen',
                            'items' => $orders->map(function (OrderOrder $o) {
                                $billing = is_array($o->billing_address) ? $o->billing_address : [];
                                $customer = trim(($billing['first_name'] ?? '') . ' ' . ($billing['last_name'] ?? '')) ?: ($o->email ?: 'Kunde');
                                $total = number_format(((int) $o->total_price) / 100, 2, ',', '.') . ' €';
                                return [
                                    'id' => 'ord_' . $o->id,
                                    'title' => '#' . ltrim((string) $o->order_number, '#') . ' · ' . $customer,
                                    'subtitle' => ($o->created_at ? $o->created_at->format('d.m.Y') . ' · ' : '') . $total,
                                    'badge' => strtoupper((string) $o->status),
                                    'url' => '/admin/orders?openOrder=' . urlencode((string) $o->id),
                                    'icon' => 'bag',
                                ];
                            })->values()->all(),
                        ];
                    }
                }
            } catch (\Throwable) {
                // Isolierter Schutz je Modul
            }

            // 4. Produkte & Lieferanten
            $productItems = [];
            try {
                if (Schema::hasTable('products')) {
                    $products = Product::query()
                        ->where(function ($q) use ($like) {
                            $q->where('name', 'like', $like)
                                ->orWhere('sku', 'like', $like)
                                ->orWhere('slug', 'like', $like);
                        })
                        ->orderByDesc('status')
                        ->orderBy('name')
                        ->limit($limit)
                        ->get(['id', 'name', 'sku', 'price', 'quantity', 'status']);

                    foreach ($products as $p) {
                        $priceEur = number_format(((int) $p->price) / 100, 2, ',', '.') . ' €';
                        $productItems[] = [
                            'id' => 'prd_' . $p->id,
                            'title' => (string) $p->name,
                            'subtitle' => trim(($p->sku ? $p->sku . ' · ' : '') . $priceEur . ' · Bestand: ' . (int) $p->quantity),
                            'badge' => $p->status === 'active' ? 'Aktiv' : 'Entwurf',
                            'url' => '/admin/products?editProduct=' . urlencode((string) $p->id),
                            'icon' => 'cube',
                        ];
                    }
                }
            } catch (\Throwable) {
            }

            try {
                if (count($productItems) < $limit && Schema::hasTable('product_suppliers')) {
                    $suppliers = ProductSupplier::query()
                        ->where(function ($q) use ($like) {
                            $q->where('name', 'like', $like)
                                ->orWhere('company_name', 'like', $like)
                                ->orWhere('contact_person', 'like', $like)
                                ->orWhere('email', 'like', $like)
                                ->orWhere('website', 'like', $like);
                        })
                        ->limit($limit - count($productItems))
                        ->get(['id', 'name', 'website']);

                    foreach ($suppliers as $s) {
                        $productItems[] = [
                            'id' => 'sup_' . $s->id,
                            'title' => (string) $s->name,
                            'subtitle' => $s->website ?: 'Lieferant',
                            'badge' => 'Lieferant',
                            'url' => '/admin/product-suppliers',
                            'icon' => 'truck',
                        ];
                    }
                }
            } catch (\Throwable) {
            }

            if (!empty($productItems)) {
                $groups[] = [
                    'key' => 'products',
                    'label' => 'Produkte & Lieferanten',
                    'items' => $productItems,
                ];
            }

            // 5. Aufgaben (ManagementTask Haupt-Tickets)
            try {
                if (Schema::hasTable('management_tasks')) {
                    $tasks = ManagementTask::query()
                        ->rootTickets()
                        ->where(function ($q) use ($like) {
                            $q->where('title', 'like', $like)
                                ->orWhere('ai_plan', 'like', $like)
                                ->orWhere('assignee_name', 'like', $like);
                        })
                        ->orderBy('is_completed', 'asc')
                        ->orderBy('position', 'asc')
                        ->limit($limit)
                        ->get(['id', 'title', 'status', 'is_completed', 'assignee_name']);

                    if ($tasks->isNotEmpty()) {
                        $groups[] = [
                            'key' => 'tasks',
                            'label' => 'Aufgaben',
                            'items' => $tasks->map(fn (ManagementTask $t) => [
                                'id' => 'tsk_' . $t->id,
                                'title' => Str::limit((string) $t->title, 85),
                                'subtitle' => $t->assignee_name ? ('Bearbeiter: ' . $t->assignee_name) : 'Aufgaben-Ticket',
                                'badge' => $t->status_label,
                                'url' => '/admin/tasks?task=' . urlencode((string) $t->id),
                                'icon' => 'check',
                            ])->values()->all(),
                        ];
                    }
                }
            } catch (\Throwable) {
            }

            // 6. Buchhaltung (Rechnungen, Sonderausgaben, Fixkosten, Bank) & Kontakte
            $accountingItems = [];
            try {
                if (Schema::hasTable('accounting_invoices')) {
                    $invoices = AccountingInvoice::query()
                        ->where(function ($q) use ($like) {
                            $q->where('invoice_number', 'like', $like)
                                ->orWhere('reference_number', 'like', $like)
                                ->orWhere('subject', 'like', $like)
                                ->orWhere('billing_address', 'like', $like);
                        })
                        ->orderByDesc('invoice_date')
                        ->limit(3)
                        ->get(['id', 'invoice_number', 'billing_address', 'subject', 'total', 'status']);

                    foreach ($invoices as $inv) {
                        $invBilling = is_array($inv->billing_address) ? $inv->billing_address : [];
                        $invCustomer = trim((string) ($invBilling['company'] ?? ''))
                            ?: trim(($invBilling['first_name'] ?? '') . ' ' . ($invBilling['last_name'] ?? ''))
                            ?: (string) ($inv->subject ?? '');
                        $accountingItems[] = [
                            'id' => 'inv_' . $inv->id,
                            'title' => (string) $inv->invoice_number . ($invCustomer !== '' ? ' · ' . $invCustomer : ''),
                            'subtitle' => number_format(((int) $inv->total) / 100, 2, ',', '.') . ' €',
                            'badge' => 'Rechnung',
                            'url' => '/admin/invoices',
                            'icon' => 'doc',
                        ];
                    }
                }
            } catch (\Throwable) {
            }

            try {
                if (count($accountingItems) < $limit && Schema::hasTable('accounting_special_issues')) {
                    $specials = AccountingSpecialIssue::query()
                        ->where(function ($q) use ($like) {
                            $q->where('title', 'like', $like)
                                ->orWhere('location', 'like', $like)
                                ->orWhere('invoice_number', 'like', $like)
                                ->orWhere('category', 'like', $like);
                        })
                        ->orderByDesc('execution_date')
                        ->limit(max(1, $limit - count($accountingItems)))
                        ->get(['id', 'title', 'location', 'amount', 'execution_date']);

                    foreach ($specials as $sp) {
                        $accountingItems[] = [
                            'id' => 'spc_' . $sp->id,
                            'title' => (string) $sp->title,
                            'subtitle' => trim(($sp->location ? $sp->location . ' · ' : '') . number_format((float) $sp->amount, 2, ',', '.') . ' €'),
                            'badge' => 'Beleg / Ausgabe',
                            'url' => '/admin/financial-variable-costs',
                            'icon' => 'banknotes',
                        ];
                    }
                }
            } catch (\Throwable) {
            }

            try {
                if (count($accountingItems) < $limit && Schema::hasTable('accounting_cost_items')) {
                    $fixes = AccountingCostItem::query()
                        ->where(function ($q) use ($like) {
                            $q->where('name', 'like', $like)
                                ->orWhere('provider_company', 'like', $like)
                                ->orWhere('contract_number', 'like', $like);
                        })
                        ->limit(max(1, $limit - count($accountingItems)))
                        ->get(['id', 'name', 'amount']);

                    foreach ($fixes as $fx) {
                        $accountingItems[] = [
                            'id' => 'fix_' . $fx->id,
                            'title' => (string) $fx->name,
                            'subtitle' => number_format((float) $fx->amount, 2, ',', '.') . ' €',
                            'badge' => 'Fixkosten',
                            'url' => '/admin/financial-fix-costs',
                            'icon' => 'banknotes',
                        ];
                    }
                }
            } catch (\Throwable) {
            }

            try {
                if (count($accountingItems) < $limit && Schema::hasTable('accounting_bank_transactions')) {
                    $bankTxs = AccountingBankTransaction::query()
                        ->where(function ($q) use ($like) {
                            $q->where('counterpart_name', 'like', $like)
                                ->orWhere('purpose', 'like', $like)
                                ->orWhere('counterpart_iban', 'like', $like);
                        })
                        ->orderByDesc('transaction_date')
                        ->limit(max(1, $limit - count($accountingItems)))
                        ->get(['id', 'counterpart_name', 'purpose', 'amount', 'transaction_date']);

                    foreach ($bankTxs as $btx) {
                        $accountingItems[] = [
                            'id' => 'btx_' . $btx->id,
                            'title' => (string) ($btx->counterpart_name ?: Str::limit((string) $btx->purpose, 48)),
                            'subtitle' => ($btx->transaction_date ? $btx->transaction_date->format('d.m.Y') . ' · ' : '') . number_format((float) $btx->amount, 2, ',', '.') . ' €',
                            'badge' => 'Bankumsatz',
                            'url' => '/admin/financial-banks',
                            'icon' => 'banknotes',
                        ];
                    }
                }
            } catch (\Throwable) {
            }

            try {
                if (count($accountingItems) < $limit && Schema::hasTable('management_contacts')) {
                    $contacts = ManagementContact::query()
                        ->where(function ($q) use ($like) {
                            $q->where('first_name', 'like', $like)
                                ->orWhere('last_name', 'like', $like)
                                ->orWhere('nickname', 'like', $like)
                                ->orWhere('relation_type', 'like', $like)
                                ->orWhere('email', 'like', $like)
                                ->orWhere('phone', 'like', $like);
                        })
                        ->limit(max(1, $limit - count($accountingItems)))
                        ->get(['id', 'first_name', 'last_name', 'nickname', 'relation_type', 'email']);

                    foreach ($contacts as $ct) {
                        $fullName = trim(($ct->first_name ?? '') . ' ' . ($ct->last_name ?? '')) ?: ($ct->nickname ?: 'Kontakt');
                        $accountingItems[] = [
                            'id' => 'cnt_' . $ct->id,
                            'title' => $fullName,
                            'subtitle' => trim(($ct->relation_type ? $ct->relation_type . ' · ' : '') . ($ct->email ?? '')),
                            'badge' => 'Kontakt',
                            'url' => '/admin/contacts',
                            'icon' => 'user',
                        ];
                    }
                }
            } catch (\Throwable) {
            }

            if (!empty($accountingItems)) {
                $groups[] = [
                    'key' => 'accounting',
                    'label' => 'Buchhaltung & Kontakte',
                    'items' => array_slice($accountingItems, 0, $limit),
                ];
            }

            // 7. Wissen & Konzepte
            $knowledgeItems = [];
            try {
                if (class_exists(ConceptLifecycleService::class)) {
                    $concepts = ConceptLifecycleService::listConcepts('all', $query);
                    foreach (array_slice($concepts, 0, 3) as $concept) {
                        $cTitle = (string) ($concept['concept_title'] ?? $concept['name'] ?? 'Konzept');
                        $isOpen = ($concept['concept_status'] ?? 'open') === 'open';
                        $knowledgeItems[] = [
                            'id' => 'cnc_' . md5((string) ($concept['id'] ?? $cTitle)),
                            'title' => $cTitle,
                            'subtitle' => 'Zentrale Konzept-Verwaltung',
                            'badge' => $isOpen ? 'Konzept · Offen' : 'Konzept · Geprüft',
                            'url' => '/admin/tickets?view=files&ftype=concept',
                            'icon' => 'sparkles',
                        ];
                    }
                }
            } catch (\Throwable) {
            }

            try {
                if (count($knowledgeItems) < $limit && Schema::hasTable('ai_knowledge_bases')) {
                    $kbEntries = AiKnowledgeBase::query()
                        ->where(function ($q) use ($like) {
                            $q->where('title', 'like', $like)
                                ->orWhere('content', 'like', $like);
                        })
                        ->orderByDesc('updated_at')
                        ->limit($limit - count($knowledgeItems))
                        ->get(['id', 'title']);

                    foreach ($kbEntries as $kb) {
                        $knowledgeItems[] = [
                            'id' => 'kb_' . $kb->id,
                            'title' => Str::limit((string) $kb->title, 80),
                            'subtitle' => 'Zentrales Wissensgehirn',
                            'badge' => 'Wissen',
                            'url' => '/admin/tickets?view=knowledge',
                            'icon' => 'book',
                        ];
                    }
                }
            } catch (\Throwable) {
            }

            if (!empty($knowledgeItems)) {
                $groups[] = [
                    'key' => 'knowledge',
                    'label' => 'Konzepte & Wissensgehirn',
                    'items' => $knowledgeItems,
                ];
            }
        }

        $total = 0;
        foreach ($groups as $g) {
            $total += count($g['items']);
        }

        return [
            'query' => $query,
            'total' => $total,
            'groups' => $groups,
        ];
    }

    /**
     * @return array<int, string>
     */
    public function tokenize(string $text): array
    {
        $norm = self::normalizeSearchText($text);
        $parts = preg_split('/\s+/', $norm, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        return array_values(array_unique(array_filter($parts, fn ($p) => strlen($p) > 0)));
    }

    public static function normalizeSearchText(string $text): string
    {
        $lower = mb_strtolower(trim($text));
        $mapped = strtr($lower, [
            'ä' => 'ae',
            'ö' => 'oe',
            'ü' => 'ue',
            'ß' => 'ss',
            '§' => ' ',
        ]);
        return trim(preg_replace('/[^a-z0-9#\-_.\s\/]/', ' ', $mapped) ?? '');
    }

    /**
     * @param array<int, array<string, mixed>> $catalog
     * @param array<int, string> $tokens
     * @param callable(array<string, mixed>): string $haystackBuilder
     * @return array<int, array<string, mixed>>
     */
    private function matchCatalogItems(array $catalog, array $tokens, callable $haystackBuilder, int $limit): array
    {
        if (empty($tokens)) {
            return [];
        }

        $scored = [];
        foreach ($catalog as $item) {
            $titleNorm = self::normalizeSearchText((string) ($item['title'] ?? ''));
            $fullNorm = self::normalizeSearchText($haystackBuilder($item));
            $words = preg_split('/[\s\/\-_.]+/', $fullNorm, -1, PREG_SPLIT_NO_EMPTY) ?: [];

            $score = 0;
            $allMatched = true;

            foreach ($tokens as $tok) {
                if (str_starts_with($titleNorm, $tok)) {
                    $score += 35;
                } elseif (str_contains($titleNorm, $tok)) {
                    $score += 24;
                } elseif (str_contains($fullNorm, $tok)) {
                    $score += 12;
                } else {
                    // Tippfehler-Toleranz (Levenshtein <= 1 ab 4 Zeichen, <= 2 ab 7 Zeichen)
                    $maxDist = strlen($tok) >= 7 ? 2 : (strlen($tok) >= 4 ? 1 : 0);
                    $fuzzyHit = false;
                    if ($maxDist > 0) {
                        foreach ($words as $w) {
                            if (abs(strlen($w) - strlen($tok)) <= $maxDist && levenshtein($tok, $w) <= $maxDist) {
                                $fuzzyHit = true;
                                $score += 6;
                                break;
                            }
                        }
                    }
                    if (!$fuzzyHit) {
                        $allMatched = false;
                        break;
                    }
                }
            }

            if ($allMatched && $score > 0) {
                $scored[] = ['score' => $score, 'item' => $item];
            }
        }

        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_map(fn ($entry) => $entry['item'], array_slice($scored, 0, $limit));
    }
}
