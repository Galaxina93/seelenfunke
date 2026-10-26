<style>
@keyframes theme-pulse-glow {
    0%, 100% { box-shadow: 0 0 5px var(--theme-color), inset 0 0 5px var(--theme-color); border-color: var(--theme-color); }
    50% { box-shadow: 0 0 25px var(--theme-color), inset 0 0 10px var(--theme-color); border-color: var(--theme-color); }
}
@keyframes sf-chrono-rotate {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
@keyframes sf-chrono-rotate-rev {
    0% { transform: rotate(360deg); }
    100% { transform: rotate(0deg); }
}
@keyframes sf-energy-flow {
    0% { stroke-dashoffset: 260; }
    100% { stroke-dashoffset: 0; }
}
@keyframes sf-float-node {
    0%, 100% { transform: translateY(0px); }
    50% { transform: translateY(-6px); }
}
@keyframes sf-wave-bob {
    0%, 100% { transform: translateY(calc(var(--amp, 12px) * -1)); }
    50% { transform: translateY(var(--amp, 12px)); }
}
@keyframes sf-bar-pulse {
    0%, 100% { transform: scaleX(0.84); }
    50% { transform: scaleX(1.14); }
}
@keyframes sf-core-breathe {
    0%, 100% {
        box-shadow: 0 0 40px rgba(197, 160, 89, 0.30), 0 0 85px rgba(197, 160, 89, 0.14), inset 0 0 30px rgba(0, 0, 0, 0.85);
    }
    50% {
        box-shadow: 0 0 65px rgba(197, 160, 89, 0.52), 0 0 120px rgba(197, 160, 89, 0.24), inset 0 0 40px rgba(0, 0, 0, 0.9);
    }
}
.sf-chrono-core-dial {
    animation: sf-core-breathe 4s ease-in-out infinite;
    will-change: transform, box-shadow;
}
.sf-spin-slow {
    transform-origin: 150px 150px;
    will-change: transform;
    animation: sf-chrono-rotate 60s linear infinite;
}
.sf-spin-reverse {
    transform-origin: 150px 150px;
    will-change: transform;
    animation: sf-chrono-rotate-rev 38s linear infinite;
}
.sf-wave-dot {
    will-change: transform;
    animation: sf-wave-bob 2.35s ease-in-out infinite;
}
.sf-freq-bar {
    transform-origin: 150px 150px;
    will-change: transform;
    animation: sf-bar-pulse 2.7s ease-in-out infinite;
}
.sf-energy-stream {
    stroke-dasharray: 28 232;
    animation: sf-energy-flow 2.8s linear infinite;
}
.sf-floating-satellite {
    will-change: transform;
    animation: sf-float-node 4.5s ease-in-out infinite;
}
.sf-stage-height {
    transition: height 650ms cubic-bezier(0.22, 1, 0.36, 1);
}
.hover-theme-pulse { cursor: pointer; transition: all 0.2s ease; }
.hover-theme-pulse:hover, .active-theme-pulse { border-color: var(--theme-color) !important; z-index: 10; }
html.dark .hover-theme-pulse:hover, html.dark .active-theme-pulse { animation: theme-pulse-glow 2s ease-in-out infinite; }
</style>
<div x-data="{ activeTab: null }"
     x-init="$watch('activeTab', val => { if (val) { setTimeout(() => window.dispatchEvent(new Event('resize')), 80); setTimeout(() => window.dispatchEvent(new Event('resize')), 420); } })"
     class="space-y-6">

    @php
        // --- 1. SHOP HEALTH ---
        $shopScore = $stats['health_score'] ?? 0;
        $shopColorClass = $shopScore >= 80 ? 'text-emerald-400' : ($shopScore >= 50 ? 'text-amber-400' : 'text-red-400');
        $shopStrokeColor = $shopScore >= 80 ? '#34d399' : ($shopScore >= 50 ? '#fbbf24' : '#f87171');
        $circumference = 2 * pi() * 40;
        $shopOffset = $circumference - ($shopScore / 100) * $circumference;

        // --- 2. OPERATIVER SCORE ---
        $totalTodos = collect($healthChecks)->sum('count');
        $opHasErrors = collect($healthChecks)->contains('status', 'error');
        $opHasWarnings = collect($healthChecks)->contains('status', 'warning');
        $opErrorCount = collect($healthChecks)->where('status', 'error')->count();
        $opWarningCount = collect($healthChecks)->where('status', 'warning')->count();

        $operativeScore = 100 - ($opErrorCount * 25) - ($opWarningCount * 10) - ($totalTodos * 1);
        $operativeScore = max(0, min(100, $operativeScore));

        $opColorClass = $operativeScore >= 80 ? 'text-emerald-400' : ($operativeScore >= 50 ? 'text-amber-400' : 'text-red-400');
        $opStrokeColor = $operativeScore >= 80 ? '#34d399' : ($operativeScore >= 50 ? '#fbbf24' : '#f87171');
        $opOffset = $circumference - ($operativeScore / 100) * $circumference;

        $opScoreText = 'Alles im grünen Bereich';
        if ($opHasErrors || $operativeScore < 50) {
            $opScoreText = 'Kritische Todos offen';
        } elseif ($opHasWarnings || $operativeScore < 80) {
            $opScoreText = 'Aufgaben warten';
        }

        // --- 3. SYSTEM SCORE ---
        $services = [
            'database' => ['label' => 'Datenbank', 'host' => config('database.connections.mysql.host', '127.0.0.1'), 'port' => config('database.connections.mysql.port', '3306'), 'desc' => 'Speichert alle Produkte, Benutzer und Bestellungen.'],
            'storage' => ['label' => 'Server Speicher', 'host' => 'Lokal (SSD)', 'port' => 'N/A', 'desc' => 'Überwacht den verfügbaren Speicherplatz auf dem Server.'],
            'stripe' => ['label' => 'Stripe API', 'host' => 'api.stripe.com', 'port' => '443', 'desc' => 'Schnittstelle zu unserem Zahlungsdienstleister.'],
            'smtp' => ['label' => 'Mail Server', 'host' => config('mail.mailers.smtp.host', 'lokal'), 'port' => config('mail.mailers.smtp.port', '2525'), 'desc' => 'Postausgangsserver für alle E-Mails.'],
            'redis' => ['label' => 'Cache / Redis', 'host' => config('database.redis.default.host', '127.0.0.1'), 'port' => config('database.redis.default.port', '6379'), 'desc' => 'Speichert Sessions & Cache. Nutzt lokal den "file"-Cache und live den schnellen "redis"-Server.'],
            'queue' => ['label' => 'Queue Worker', 'host' => 'Hintergrund', 'port' => 'N/A', 'desc' => 'Verarbeitet Aufgaben (Mails, PDFs) im Hintergrund.'],
            'scheduler' => ['label' => 'Task Scheduler', 'host' => 'Cronjob', 'port' => 'CLI', 'desc' => 'Führt zeitgesteuerte Hintergrundaufgaben aus (z.B. Bereinigungen, Erinnerungen).'],
            'backup' => ['label' => 'System Backup', 'host' => 'Storage', 'port' => 'N/A', 'desc' => 'Prüft, ob in den letzten 48 Stunden eine Sicherung der Datenbank erstellt wurde.'],
            'ws' => ['label' => 'WebSocket (Events/Reverb)', 'host' => env('MIX_PUSHER_HOST', env('VITE_REVERB_HOST', env('PUSHER_HOST', '127.0.0.1'))), 'port' => env('MIX_PUSHER_PORT', env('VITE_REVERB_PORT', env('PUSHER_PORT', '6001'))), 'desc' => 'WebSocket-Verbindung (z.B. Reverb / Pusher) für Live-Updates (Chat, Analytics).'],
            'dhl' => ['label' => 'DHL Paket API', 'host' => 'api.dhl.com', 'port' => '443', 'desc' => 'Label-Generierung und Sendungsverfolgung.'],
            'finapi' => ['label' => 'finAPI (Bank)', 'host' => env('FINAPI_ENV', 'live') === 'live' ? 'live.finapi.io' : 'sandbox.finapi.io', 'port' => '443', 'desc' => 'Schnittstelle zur Bankkonto-Synchronisation.'],
            'gemini' => ['label' => 'Google Gemini', 'host' => 'generativelanguage.googleapis.com', 'port' => '443', 'desc' => 'Google AI Basismodell für komplexe Task-Bewältigung.'],
            'google_places' => ['label' => 'Google Places', 'host' => 'maps.googleapis.com', 'port' => '443', 'desc' => 'Schnittstelle für automatische Kundenbewertungen.'],
            'elster' => ['label' => 'Elster (ERiC)', 'host' => 'elster.de', 'port' => '443', 'desc' => 'Anbindung zur Finanzbehörde der Bundesrepublik Deutschland.'],
            'scraperapi' => ['label' => 'ScraperAPI', 'host' => 'api.scraperapi.com', 'port' => '80', 'desc' => 'Proxy-Service für Marktforschung und SEO-Agenten.'],
            'telephony' => ['label' => 'Gemini Audio Bridge', 'host' => 'localhost', 'port' => env('TWILIO_WS_PORT', '8081'), 'desc' => app()->environment('local') ? 'Lokal deaktiviert. Funktioniert nur auf Stage/Live und hat keinen Einfluss auf den Health-Score.' : 'Node.js WebSocket Server für Echtzeit-Sprachanrufe zwischen Twilio und KI.'],
        ];

        $systemGroups = [
            'Kernsysteme' => [
                'color' => 'bg-primary dark:shadow-[0_0_8px_rgba(197,160,89,0.8)]',
                'items' => ['database', 'storage', 'redis']
            ],
            'Schnittstellen & API' => [
                'color' => 'bg-blue-500 dark:shadow-[0_0_8px_rgba(59,130,246,0.8)]',
                'items' => ['ws', 'stripe', 'smtp', 'dhl', 'finapi', 'elster']
            ],
            'AI & Daten-Agenten' => [
                'color' => 'bg-emerald-500 dark:shadow-[0_0_8px_rgba(16,185,129,0.8)]',
                'items' => ['gemini', 'telephony', 'google_places', 'scraperapi']
            ],
            'Background & Security' => [
                'color' => 'bg-purple-500 dark:shadow-[0_0_8px_rgba(168,85,247,0.8)]',
                'items' => ['queue', 'scheduler', 'backup']
            ]
        ];

        $sysErrors = 0;
        $sysWarnings = 0;
        foreach($services as $sKey => $sInfo) {
            if (isset($systemHealth[$sKey])) {
                if ($systemHealth[$sKey]['status'] === 'error' || $systemHealth[$sKey]['status'] === 'unavailable') {
                    $sysErrors++;
                } elseif ($systemHealth[$sKey]['status'] === 'warning') {
                    $sysWarnings++;
                }
            }
        }
        $storageData = $systemHealth['storage'] ?? null;
        if ($storageData && isset($storageData['percent_free'])) {
            if ($storageData['percent_free'] < 10) {
                $sysErrors++;
            } elseif ($storageData['percent_free'] < 20) {
                $sysWarnings++;
            }
        }
        $systemScore = 100 - ($sysErrors * 25) - ($sysWarnings * 10);
        $systemScore = max(0, min(100, $systemScore));

        $sysColorClass = $systemScore >= 80 ? 'text-emerald-400' : ($systemScore >= 50 ? 'text-amber-400' : 'text-red-400');
        $sysStrokeColor = $systemScore >= 80 ? '#34d399' : ($systemScore >= 50 ? '#fbbf24' : '#f87171');
        $sysOffset = $circumference - ($systemScore / 100) * $circumference;

        $sysText = 'Alle Systeme online';
        if ($sysErrors > 0 || $systemScore < 50) {
            $sysText = 'Kritische System-Ausfälle';
        } elseif ($sysWarnings > 0 || $systemScore < 80) {
            $sysText = 'System-Warnungen';
        }

        // --- 4. SECURITY SCORE (Gecacht / Berechnet in MasterAnalytics) ---
        $secDetails = $this->securityScoreDetails;
        $failedLogins24h = $secDetails['failedLogins24h'];
        $securityWarnings24h = $secDetails['securityWarnings24h'];
        $securityScore = $secDetails['score'];
        $secColorClass = $secDetails['colorClass'];
        $secStrokeColor = $secDetails['strokeColor'];
        $secOffset = $secDetails['offset'];
        $secText = $secDetails['text'];
        $blockedIpsCount = $secDetails['blockedIpsCount'];

        // --- 5. KAPAZITÄTEN SCHWELLENWERTE (Drossel 1 & Speicher Warnung 1) ---
        $shopLoad = (float) \Illuminate\Support\Facades\Cache::get('shop_capacity_percentage', 0);
        $shopThreshold1 = (int) \Illuminate\Support\Facades\Cache::get(
            'shop_capacity_threshold_1',
            \App\Models\System\SystemSetting::where('key', 'shop_capacity_threshold_1')->value('value') ?? 60
        );
        $storageThreshold1 = (int) \Illuminate\Support\Facades\Cache::get(
            'storage_capacity_threshold_1',
            \App\Models\System\SystemSetting::where('key', 'storage_capacity_threshold_1')->value('value') ?? 70
        );
        $diskTotal = @disk_total_space(storage_path());
        $diskFree = @disk_free_space(storage_path());
        $storageUsedPct = ($diskTotal && $diskFree !== false && $diskTotal > 0)
            ? round((($diskTotal - $diskFree) / $diskTotal) * 100, 1)
            : (isset($storageData['percent_free']) ? (100 - (float) $storageData['percent_free']) : 0);

        $capacityNeedsAttention = ($shopLoad >= $shopThreshold1) || ($storageUsedPct >= $storageThreshold1);

        // --- 6. BESTELLUNGEN MOTIVATOR & ORBITAL-SATELLITEN (FOKUS-MODUS) ---
        $totalOrdersCount = (int) ($stats['orders_total'] ?? 0);
        $openOrdersCount = (int) ($healthChecks['open_orders']['count'] ?? 0);
        $bEven = $stats['break_even_monthly'] ?? 0;
        $currentRev = $stats['avg_revenue_monthly'] ?? 0;
        $missing = max(0, $bEven - $currentRev);
        $beColor = $missing > 0 ? 'text-red-500' : 'text-emerald-500';
        $onlineCount = collect($systemHealth)->count() > 0 ? $this->getActiveSessionsCount() : 0;

        $orbitalNodes = [];

        // Farb-Direktive Fokus-Modus: Primärfarbe Seelenfunke Gold (#C5A059), nur Kritisch in Rot (#ef4444)
        $sfGold = '#C5A059';
        $sfCritical = '#ef4444';

        if ($shopScore < 100) {
            $isCrit = $shopScore < 50;
            $orbitalNodes[] = [
                'tab' => 'shop',
                'sound' => $isCrit ? 'alert' : 'chart',
                'title' => 'SHOP HEALTH',
                'value' => $shopScore,
                'unit' => 'SCORE',
                'ring_pct' => max(8, $shopScore),
                'color' => $isCrit ? $sfCritical : $sfGold,
                'critical' => $isCrit,
                'metric' => number_format($stats['shop_revenue'] ?? 0, 0, ',', '.') . ' € UMSATZ',
                'status' => $shopScore >= 80 ? 'STABIL' : ($shopScore >= 50 ? 'OPTIMIERBAR' : 'KRITISCH · FOKUS NÖTIG'),
            ];
        }

        if ($operativeScore < 100) {
            $isCrit = $opHasErrors || $operativeScore < 50;
            $orbitalNodes[] = [
                'tab' => 'operative',
                'sound' => $isCrit ? 'alert' : 'select',
                'title' => 'OPERATIVER STATUS',
                'value' => $operativeScore,
                'unit' => 'SCORE',
                'ring_pct' => max(8, $operativeScore),
                'color' => $isCrit ? $sfCritical : $sfGold,
                'critical' => $isCrit,
                'metric' => $totalTodos . ' AUFGABEN',
                'status' => strtoupper($opScoreText),
            ];
        }

        if ($systemScore < 100) {
            $isCrit = $sysErrors > 0 || $systemScore < 50;
            $orbitalNodes[] = [
                'tab' => 'system',
                'sound' => $isCrit ? 'alert' : 'chart',
                'title' => 'INFRASTRUKTUR',
                'value' => $systemScore,
                'unit' => 'SCORE',
                'ring_pct' => max(8, $systemScore),
                'color' => $isCrit ? $sfCritical : $sfGold,
                'critical' => $isCrit,
                'metric' => ($sysErrors + $sysWarnings) . ' WARNUNGEN',
                'status' => strtoupper($sysText),
            ];
        }

        if ($securityScore < 100) {
            $isCrit = $securityScore < 50;
            $orbitalNodes[] = [
                'tab' => 'security',
                'sound' => $isCrit ? 'alert' : 'chart',
                'title' => 'SICHERHEIT & ABWEHR',
                'value' => $securityScore,
                'unit' => 'SCORE',
                'ring_pct' => max(8, $securityScore),
                'color' => $isCrit ? $sfCritical : $sfGold,
                'critical' => $isCrit,
                'metric' => ($failedLogins24h + $securityWarnings24h) . ' EREIGNISSE',
                'status' => strtoupper($secText),
            ];
        }

        if ($capacityNeedsAttention) {
            $isCrit = $shopLoad >= 90 || $storageUsedPct >= 90;
            $orbitalNodes[] = [
                'tab' => 'capacities',
                'sound' => $isCrit ? 'alert' : 'chart',
                'title' => 'KAPAZITÄTEN',
                'value' => $shopLoad . '%',
                'unit' => 'LAST',
                'ring_pct' => min(100, max(12, $shopLoad)),
                'color' => $isCrit ? $sfCritical : $sfGold,
                'critical' => $isCrit,
                'metric' => 'SPEICHER: ' . (isset($storageData['percent_free']) ? $storageData['percent_free'] : round(100 - $storageUsedPct)) . '% FREI',
                'status' => $shopLoad >= $shopThreshold1 ? 'DROSSEL AKTIV' : 'SPEICHER WARNUNG',
            ];
        }

        // Gewinn & Traffic hängen immer als frei schwebende Telemetrie-Satelliten am Kern
        $profitCrit = $missing > 0;
        $orbitalNodes[] = [
            'tab' => 'profit',
            'sound' => 'chart',
            'title' => 'GEWINN',
            'value' => number_format($stats['avg_profit'] ?? 0, 0, ',', '.'),
            'unit' => 'MTL. €',
            'ring_pct' => $bEven > 0 ? min(100, max(15, round(($currentRev / $bEven) * 100))) : 100,
            'color' => $profitCrit ? $sfCritical : $sfGold,
            'critical' => $profitCrit,
            'metric' => 'BREAK-EVEN',
            'status' => $missing > 0 ? '-' . number_format($missing, 0, ',', '.') . ' € FEHLEND' : 'ERREICHT!',
        ];

        $orbitalNodes[] = [
            'tab' => 'traffic',
            'sound' => 'chart',
            'title' => 'TRAFFIC & KUNDEN',
            'value' => number_format($stats['frontend_unique_total'] ?? 0, 0, ',', '.'),
            'unit' => 'BESUCHER',
            'ring_pct' => 78,
            'color' => $sfGold,
            'critical' => false,
            'metric' => number_format($stats['frontend_visits_total'] ?? 0, 0, ',', '.') . ' PAGEVIEWS',
            'status' => '● ' . $onlineCount . ' NUTZER ONLINE',
        ];

        // Live Routine als freier Satellit im Fokus-Modus
        $routineOpenCount = (int) ($healthChecks['routine']['count'] ?? 0);
        $routineCrit = ($healthChecks['routine']['status'] ?? 'ok') === 'error';
        $routineActiveTitle = isset($currentActiveRoutine) && $currentActiveRoutine
            ? strtoupper(\Illuminate\Support\Str::limit($currentActiveRoutine->title, 20))
            : 'KEIN AKTIVER BLOCK';
        $orbitalNodes[] = [
            'tab' => 'routine',
            'sound' => 'select',
            'title' => 'LIVE ROUTINE',
            'value' => isset($currentActiveRoutine) && $currentActiveRoutine
                ? \Carbon\Carbon::parse($currentActiveRoutine->start_time)->format('H:i')
                : ($routineOpenCount > 0 ? $routineOpenCount : 'OK'),
            'unit' => isset($currentActiveRoutine) && $currentActiveRoutine ? 'UHR' : ($routineOpenCount > 0 ? 'OFFEN' : 'TAG'),
            'ring_pct' => $routineOpenCount > 0 ? max(25, 100 - ($routineOpenCount * 15)) : 100,
            'color' => $routineCrit ? $sfCritical : $sfGold,
            'critical' => $routineCrit,
            'metric' => $routineActiveTitle,
            'status' => $routineOpenCount > 0 ? $routineOpenCount . ' ROUTINE-TODOS OFFEN' : 'IM ZEITPLAN',
        ];

        // Kalender als freier Satellit im Fokus-Modus
        $eventsCollection = isset($upcomingEvents) ? collect($upcomingEvents) : collect();
        $eventsCount = $eventsCollection->count();
        $nextEventObj = $eventsCollection->first();
        $calendarCrit = $eventsCollection->contains(fn($e) => isset($e->start_date) && $e->start_date->isToday() && ($e->priority ?? '') === 'high');
        $nextEventLabel = $nextEventObj
            ? strtoupper(\Illuminate\Support\Str::limit($nextEventObj->title, 20))
            : 'KEINE TERMINE';
        $nextEventStatus = $nextEventObj && isset($nextEventObj->start_date)
            ? ($nextEventObj->start_date->isToday() ? 'HEUTE · ' : ($nextEventObj->start_date->isTomorrow() ? 'MORGEN · ' : $nextEventObj->start_date->format('d.m.') . ' · ')) . ($nextEventObj->is_all_day ? 'GANZTÄGIG' : $nextEventObj->start_date->format('H:i') . ' UHR')
            : 'KALENDER FREI';
        $orbitalNodes[] = [
            'tab' => 'calendar',
            'sound' => 'select',
            'title' => 'KALENDER',
            'value' => $eventsCount,
            'unit' => $eventsCount === 1 ? 'TERMIN' : 'TERMINE',
            'ring_pct' => $eventsCount > 0 ? min(100, 35 + ($eventsCount * 15)) : 100,
            'color' => $calendarCrit ? $sfCritical : $sfGold,
            'critical' => $calendarCrit,
            'metric' => $nextEventLabel,
            'status' => $nextEventStatus,
        ];

        // --- SUB-SATELLITEN FÜR ALLE DRILL-DOWN TRANSFORMATIONEN IM FOKUS-MODUS ---
        $opRouteMap = [
            'inventory' => '/admin/products',
            'special_issues' => '/admin/financial-variable-costs',
            'leitung/contracts' => '/admin/financial-fix-costs',
            'open_orders' => '/admin/orders',
            'open_tickets' => '/admin/support-tickets',
            'open_chats' => '/admin/support-chats',
            'open_contact_requests' => '/admin/support-contact-form',
            'open_mails' => '/admin/inbox',
            'product_reviews' => '/admin/reviews',
            'open_credits' => '/admin/credit-management',
            'influencer_payouts' => '/admin/marketing/influencer',
            'employee_payroll' => '/admin/employees',
            'unassigned_tx' => '/admin/financial-banks',
            'open_tasks' => '/admin/tasks',
            'routine' => '/admin/routine',
            'open_quotes' => '/admin/quote-requests',
            'open_revocations' => '/admin/widerruf',
            'open_losses' => '/admin/product-fracture',
            'open_blog_posts' => '/admin/blog',
            'open_abandoned_carts' => '/admin/shopping-carts',
            'system_logs' => '/admin/global-logs',
        ];

        // 1. OPERATIVER STATUS SUB-NODES (Kategorien / Aufgaben aus To-do Liste & Operatives)
        $operativeSubNodes = [];
        foreach ($healthChecks as $hKey => $hCheck) {
            if (($hCheck['count'] ?? 0) > 0) {
                $isErr = ($hCheck['status'] ?? '') === 'error' || ($hCheck['count'] ?? 0) > 0;
                $operativeSubNodes[] = [
                    'url' => $opRouteMap[$hKey] ?? '/admin/dashboard',
                    'sound' => 'action',
                    'title' => strtoupper($hCheck['title'] ?? $hKey),
                    'value' => $hCheck['count'],
                    'unit' => 'OFFEN',
                    'ring_pct' => min(100, max(22, ($hCheck['count'] ?? 1) * 18)),
                    'color' => $isErr ? $sfCritical : $sfGold,
                    'critical' => $isErr,
                    'metric' => strtoupper(\Illuminate\Support\Str::limit($hCheck['message'] ?? '', 26)),
                    'status' => 'DIREKT ÖFFNEN ↗',
                ];
            }
        }
        if (count($operativeSubNodes) === 0) {
            foreach (['routine', 'open_orders', 'open_tickets', 'open_tasks', 'special_issues', 'product_reviews'] as $fallbackKey) {
                if (isset($healthChecks[$fallbackKey])) {
                    $hc = $healthChecks[$fallbackKey];
                    $operativeSubNodes[] = [
                        'url' => $opRouteMap[$fallbackKey] ?? '/admin/dashboard',
                        'sound' => 'action',
                        'title' => strtoupper($hc['title'] ?? $fallbackKey),
                        'value' => 'OK',
                        'unit' => 'CHECK',
                        'ring_pct' => 100,
                        'color' => $sfGold,
                        'critical' => false,
                        'metric' => strtoupper(\Illuminate\Support\Str::limit($hc['message'] ?? 'Alles erledigt', 26)),
                        'status' => 'ERLEDIGT ✓',
                    ];
                }
            }
        }
        $operativeSubNodes = array_slice($operativeSubNodes, 0, 8);

        // 2. SHOP HEALTH SUB-NODES
        $hasPendingInv = ($stats['pending_invoices']['sum'] ?? 0) > 0;
        $totalDeducts = ($opErrorCount * 5) + $opWarningCount + ($sysErrors * 10) + ($sysWarnings * 2);
        $shopSubNodes = [
            [
                'tab' => 'ecommerce',
                'sound' => 'chart',
                'title' => 'UMSATZRAHMEN',
                'value' => (($stats['revenue_growth'] ?? 0) >= 0 ? '+' : '') . ($stats['revenue_growth'] ?? 0) . '%',
                'unit' => 'TREND',
                'ring_pct' => 85,
                'color' => (($stats['revenue_growth'] ?? 0) < 0) ? $sfCritical : $sfGold,
                'critical' => (($stats['revenue_growth'] ?? 0) < 0),
                'metric' => number_format($stats['shop_revenue'] ?? 0, 0, ',', '.') . ' € UMSATZ',
                'status' => 'WACHSTUMS-SÄULE',
            ],
            [
                'tab' => 'profit',
                'sound' => 'chart',
                'title' => 'JAHRESPROGNOSE',
                'value' => number_format(($stats['projected_year'] ?? 0) / 1000, 1, ',', '.') . 'k',
                'unit' => 'JAHR €',
                'ring_pct' => 82,
                'color' => $sfGold,
                'critical' => false,
                'metric' => number_format($stats['projected_year'] ?? 0, 0, ',', '.') . ' € HOCHRECHNUNG',
                'status' => 'PROJEKTION',
            ],
            [
                'tab' => 'profit',
                'sound' => 'chart',
                'title' => 'RENTABILITÄT',
                'value' => ($stats['margin'] ?? 0) . '%',
                'unit' => 'MARGE',
                'ring_pct' => min(100, max(15, (int) ($stats['margin'] ?? 50))),
                'color' => $sfGold,
                'critical' => false,
                'metric' => number_format($stats['avg_profit'] ?? 0, 0, ',', '.') . ' € MTL. GEWINN',
                'status' => 'EFFIZIENZ-SÄULE',
            ],
            [
                'tab' => 'profit',
                'sound' => 'chart',
                'title' => 'BREAK-EVEN',
                'value' => number_format($bEven, 0, ',', '.'),
                'unit' => 'ZIEL €',
                'ring_pct' => $bEven > 0 ? min(100, max(15, round(($currentRev / $bEven) * 100))) : 100,
                'color' => $missing > 0 ? $sfCritical : $sfGold,
                'critical' => $missing > 0,
                'metric' => $missing > 0 ? '-' . number_format($missing, 0, ',', '.') . ' € FEHLEND' : 'ERREICHT!',
                'status' => 'GEWINN-KURVE ÖFFNEN ↗',
            ],
            [
                'url' => '/admin/financial-banks',
                'sound' => 'action',
                'title' => 'OFFENE POSTEN',
                'value' => $stats['pending_invoices']['count'] ?? 0,
                'unit' => 'BELEGE',
                'ring_pct' => $hasPendingInv ? 40 : 100,
                'color' => $hasPendingInv ? $sfCritical : $sfGold,
                'critical' => $hasPendingInv,
                'metric' => number_format($stats['pending_invoices']['sum'] ?? 0, 0, ',', '.') . ' € OFFEN',
                'status' => $hasPendingInv ? 'FORDERUNGEN OFFEN' : 'ALLES BEGLICHEN',
            ],
            [
                'tab' => 'operative',
                'sound' => 'select',
                'title' => 'SCORE-ABZÜGE',
                'value' => $totalDeducts > 0 ? '-' . $totalDeducts : '0',
                'unit' => 'PUNKTE',
                'ring_pct' => max(15, 100 - ($totalDeducts * 3)),
                'color' => $totalDeducts > 0 ? $sfCritical : $sfGold,
                'critical' => $totalDeducts > 0,
                'metric' => 'OP. & SYSTEM STÖRFÄLLE',
                'status' => $totalDeducts > 0 ? 'ZU DEN TODOS WECHSELN ↗' : 'KEINE ABZÜGE',
            ],
        ];

        // 3. SYSTEM / INFRASTRUKTUR SUB-NODES
        $systemSubNodes = [];
        foreach ($systemGroups as $gName => $gInfo) {
            $gTotal = count($gInfo['items']);
            $gOk = 0;
            $gErr = 0;
            foreach ($gInfo['items'] as $sK) {
                $st = $systemHealth[$sK]['status'] ?? 'connected';
                if ($st === 'error' || $st === 'unavailable') {
                    $gErr++;
                } else {
                    $gOk++;
                }
            }
            $systemSubNodes[] = [
                'tab' => 'system',
                'sound' => $gErr > 0 ? 'alert' : 'select',
                'title' => strtoupper($gName),
                'value' => $gOk . '/' . $gTotal,
                'unit' => 'ONLINE',
                'ring_pct' => $gTotal > 0 ? round(($gOk / $gTotal) * 100) : 100,
                'color' => $gErr > 0 ? $sfCritical : $sfGold,
                'critical' => $gErr > 0,
                'metric' => $gErr > 0 ? $gErr . ' DIENST(E) GESTÖRT' : 'ALLE DIENSTE STABIL',
                'status' => $gErr > 0 ? 'PRÜFUNG UNTEN ↓' : '100% INTEGRITÄT',
            ];
        }

        // 4. SECURITY SUB-NODES
        $securitySubNodes = [
            [
                'tab' => 'security',
                'sound' => 'select',
                'title' => 'FEHL-LOGINS (24H)',
                'value' => $failedLogins24h,
                'unit' => 'VERSUCHE',
                'ring_pct' => max(20, 100 - ($failedLogins24h * 3)),
                'color' => $failedLogins24h >= 10 ? $sfCritical : $sfGold,
                'critical' => $failedLogins24h >= 10,
                'metric' => 'AUTHENTIFIZIERUNG',
                'status' => $failedLogins24h >= 10 ? 'ERHÖHTE AKTIVITÄT' : 'NORMALBEREICH',
            ],
            [
                'tab' => 'security',
                'sound' => 'select',
                'title' => 'WARNUNGEN (24H)',
                'value' => $securityWarnings24h,
                'unit' => 'ALARME',
                'ring_pct' => max(20, 100 - ($securityWarnings24h * 10)),
                'color' => $securityWarnings24h > 0 ? $sfCritical : $sfGold,
                'critical' => $securityWarnings24h > 0,
                'metric' => 'THREAT DETECTION',
                'status' => $securityWarnings24h > 0 ? 'EREIGNISSE PRÜFEN ↓' : 'KEINE BEDROHUNGEN',
            ],
            [
                'tab' => 'security',
                'sound' => 'select',
                'title' => 'BLOCKIERTE IPS',
                'value' => $blockedIpsCount,
                'unit' => 'SPERREN',
                'ring_pct' => 100,
                'color' => $sfGold,
                'critical' => false,
                'metric' => 'AKTIVE FIREWALL-REGELN',
                'status' => 'ABWEHR AKTIV',
            ],
            [
                'url' => '/admin/global-logs',
                'sound' => 'action',
                'title' => 'SYSTEM LOGS',
                'value' => $healthChecks['system_logs']['count'] ?? 0,
                'unit' => 'LOGS',
                'ring_pct' => (($healthChecks['system_logs']['count'] ?? 0) > 0) ? 45 : 100,
                'color' => (($healthChecks['system_logs']['count'] ?? 0) > 0) ? $sfCritical : $sfGold,
                'critical' => (($healthChecks['system_logs']['count'] ?? 0) > 0),
                'metric' => 'GLOBALES LOGBUCH',
                'status' => 'LOGS ÖFFNEN ↗',
            ],
        ];

        // 5. CAPACITIES SUB-NODES
        $capacitiesSubNodes = [
            [
                'tab' => 'capacities',
                'sound' => 'select',
                'title' => 'PRODUKTIONS-LAST',
                'value' => $shopLoad . '%',
                'unit' => 'AKTUELL',
                'ring_pct' => min(100, max(12, $shopLoad)),
                'color' => $shopLoad >= 90 ? $sfCritical : $sfGold,
                'critical' => $shopLoad >= 90,
                'metric' => 'MANUFAKTUR & GEWERKE',
                'status' => $shopLoad >= $shopThreshold1 ? 'DROSSEL 1 ERREICHT' : 'FREIE KAPAZITÄT',
            ],
            [
                'tab' => 'capacities',
                'sound' => 'select',
                'title' => 'DROSSEL-SCHWELLE 1',
                'value' => $shopThreshold1 . '%',
                'unit' => 'SCHWELLE',
                'ring_pct' => $shopThreshold1,
                'color' => $sfGold,
                'critical' => false,
                'metric' => 'AUTO-REGULIERUNG',
                'status' => 'SCHWELLENWERT',
            ],
            [
                'tab' => 'capacities',
                'sound' => 'select',
                'title' => 'SERVER-SPEICHER',
                'value' => round($storageUsedPct) . '%',
                'unit' => 'BELEGT',
                'ring_pct' => min(100, max(12, round($storageUsedPct))),
                'color' => $storageUsedPct >= 90 ? $sfCritical : $sfGold,
                'critical' => $storageUsedPct >= 90,
                'metric' => round(100 - $storageUsedPct) . '% SPEICHER FREI',
                'status' => $storageUsedPct >= $storageThreshold1 ? 'WARNUNG 1 AKTIV' : 'OPTIMAL',
            ],
            [
                'tab' => 'capacities',
                'sound' => 'select',
                'title' => 'SPEICHER-WARNUNG 1',
                'value' => $storageThreshold1 . '%',
                'unit' => 'SCHWELLE',
                'ring_pct' => $storageThreshold1,
                'color' => $sfGold,
                'critical' => false,
                'metric' => 'STORAGE-WÄCHTER',
                'status' => 'WARNSCHWELLE',
            ],
        ];

        // 6. GEWINN SUB-NODES (Begleiten den nach oben fliegenden Chrono-Kern über dem Chart)
        $profitSubNodes = [
            [
                'tab' => 'profit',
                'sound' => 'select',
                'title' => 'MONATSUMSATZ (Ø)',
                'value' => number_format($currentRev, 0, ',', '.'),
                'unit' => 'MTL. €',
                'ring_pct' => 85,
                'color' => $sfGold,
                'critical' => false,
                'metric' => 'DURCHSCHNITTLICH PRO MONAT',
                'status' => 'UMSATZ-BASIS',
            ],
            [
                'tab' => 'profit',
                'sound' => 'select',
                'title' => 'BREAK-EVEN ZIEL',
                'value' => number_format($bEven, 0, ',', '.'),
                'unit' => 'MTL. €',
                'ring_pct' => $bEven > 0 ? min(100, max(15, round(($currentRev / $bEven) * 100))) : 100,
                'color' => $missing > 0 ? $sfCritical : $sfGold,
                'critical' => $missing > 0,
                'metric' => 'KOSTENDECKUNGS-PUNKT',
                'status' => $missing > 0 ? 'NOCH OFFEN' : 'ERREICHT ✓',
            ],
            [
                'tab' => 'profit',
                'sound' => 'select',
                'title' => 'NETTOMARGE',
                'value' => ($stats['margin'] ?? 0) . '%',
                'unit' => 'QUOTE',
                'ring_pct' => min(100, max(15, (int) ($stats['margin'] ?? 50))),
                'color' => $sfGold,
                'critical' => false,
                'metric' => 'RENTABILITÄT VOM UMSATZ',
                'status' => 'EFFIZIENZ',
            ],
            [
                'tab' => 'shop',
                'sound' => 'select',
                'title' => $missing > 0 ? 'FEHLBETRAG' : 'GEWINN-PUFFER',
                'value' => ($missing > 0 ? '-' : '+') . number_format($missing > 0 ? $missing : max(0, $currentRev - $bEven), 0, ',', '.'),
                'unit' => 'MTL. €',
                'ring_pct' => $missing > 0 ? 35 : 100,
                'color' => $missing > 0 ? $sfCritical : $sfGold,
                'critical' => $missing > 0,
                'metric' => 'DIFFERENZ ZUM BREAK-EVEN',
                'status' => 'SHOP HEALTH DETAILS ↗',
            ],
        ];

        // 7. E-COMMERCE SUB-NODES
        $abandonedCount = count($stats['abandoned_carts']['details'] ?? []);
        $ecommerceSubNodes = [
            [
                'url' => '/admin/orders',
                'sound' => 'action',
                'title' => 'OFFENE ORDERS',
                'value' => $openOrdersCount,
                'unit' => 'OFFEN',
                'ring_pct' => $openOrdersCount > 0 ? 50 : 100,
                'color' => $openOrdersCount > 0 ? $sfCritical : $sfGold,
                'critical' => $openOrdersCount > 0,
                'metric' => 'VERSAND & PRODUKTION',
                'status' => 'BESTELLUNGEN ÖFFNEN ↗',
            ],
            [
                'url' => '/admin/shopping-carts',
                'sound' => 'action',
                'title' => 'WARENKÖRBE',
                'value' => $abandonedCount,
                'unit' => 'ABBRÜCHE',
                'ring_pct' => 75,
                'color' => $sfGold,
                'critical' => false,
                'metric' => 'LIEGENGEBLIEBENE KÖRBE',
                'status' => 'WARENKÖRBE ÖFFNEN ↗',
            ],
            [
                'tab' => 'profit',
                'sound' => 'chart',
                'title' => 'CHAMPION UNIKAT',
                'value' => number_format($stats['high_revenue_prod']['total'] ?? 0, 0, ',', '.') . '€',
                'unit' => 'TOP',
                'ring_pct' => 95,
                'color' => $sfGold,
                'critical' => false,
                'metric' => strtoupper(\Illuminate\Support\Str::limit($stats['high_revenue_prod']['product_name'] ?? 'Bestseller', 22)),
                'status' => 'UMSÄTZE UNTEN ↓',
            ],
            [
                'url' => '/admin/voucher',
                'sound' => 'action',
                'title' => 'GUTSCHEINE',
                'value' => 'AKTIV',
                'unit' => 'PROMO',
                'ring_pct' => 100,
                'color' => $sfGold,
                'critical' => false,
                'metric' => 'KAMPAGNEN & RABATTE',
                'status' => 'GUTSCHEINE ÖFFNEN ↗',
            ],
        ];

        // 8. TRAFFIC & KUNDEN SUB-NODES
        $totalDev = max(1, ($stats['desktop_visits'] ?? 0) + ($stats['mobile_visits'] ?? 0));
        $mobPct = round((($stats['mobile_visits'] ?? 0) / $totalDev) * 100);
        $trafficSubNodes = [
            [
                'tab' => 'traffic',
                'sound' => 'select',
                'title' => 'SEITENAUFRUFE',
                'value' => number_format($stats['frontend_visits_total'] ?? 0, 0, ',', '.'),
                'unit' => 'VIEWS',
                'ring_pct' => 88,
                'color' => $sfGold,
                'critical' => false,
                'metric' => 'PAGEVIEWS IM ZEITRAUM',
                'status' => 'ANALYSE UNTEN ↓',
            ],
            [
                'tab' => 'traffic',
                'sound' => 'select',
                'title' => 'LIVE ONLINE',
                'value' => $onlineCount,
                'unit' => 'NUTZER',
                'ring_pct' => 100,
                'color' => $sfGold,
                'critical' => false,
                'metric' => 'AKTIVE SITZUNGEN JETZT',
                'status' => 'ECHTZEIT-PULS',
            ],
            [
                'tab' => 'traffic',
                'sound' => 'select',
                'title' => 'MOBILE QUOTE',
                'value' => $mobPct . '%',
                'unit' => 'MOBIL',
                'ring_pct' => $mobPct,
                'color' => $sfGold,
                'critical' => false,
                'metric' => 'SMARTPHONE & TABLET',
                'status' => 'ENDGERÄTE-MIX',
            ],
            [
                'url' => '/admin/marketing/tracking',
                'sound' => 'action',
                'title' => 'NEUKUNDEN & LINKS',
                'value' => number_format($stats['total_new_customers_period'] ?? 0, 0, ',', '.'),
                'unit' => 'NEU',
                'ring_pct' => 85,
                'color' => $sfGold,
                'critical' => false,
                'metric' => 'VERIFIZIERTE REGISTRIERUNGEN',
                'status' => 'TRACKING ÖFFNEN ↗',
            ],
        ];

        // 9. LIVE ROUTINE SUB-NODES
        $routineSubNodes = [
            [
                'url' => route('admin.routine'),
                'sound' => 'action',
                'title' => 'AKTIVER BLOCK',
                'value' => isset($currentActiveRoutine) && $currentActiveRoutine ? \Carbon\Carbon::parse($currentActiveRoutine->start_time)->format('H:i') : 'FREI',
                'unit' => isset($currentActiveRoutine) && $currentActiveRoutine ? 'START' : 'PAUSE',
                'ring_pct' => 100,
                'color' => $sfGold,
                'critical' => false,
                'metric' => $routineActiveTitle,
                'status' => isset($currentActiveRoutine) && $currentActiveRoutine ? ($currentActiveRoutine->duration_minutes . ' MINUTEN DAUER') : 'KEIN GEPLANTER BLOCK',
            ],
            [
                'url' => route('admin.routine'),
                'sound' => 'action',
                'title' => 'TAGES-TODOS',
                'value' => $routineOpenCount,
                'unit' => $routineOpenCount > 0 ? 'OFFEN' : 'OK',
                'ring_pct' => $routineOpenCount > 0 ? max(25, 100 - ($routineOpenCount * 15)) : 100,
                'color' => $routineCrit ? $sfCritical : $sfGold,
                'critical' => $routineCrit,
                'metric' => $routineOpenCount > 0 ? $routineOpenCount . ' ROUTINE-SCHRITTE OFFEN' : 'TAGES-CHECK-IN ERLEDIGT',
                'status' => 'JETZT ABHAKEN ↗',
            ],
            [
                'tab' => 'operative',
                'sound' => 'select',
                'title' => 'OPERATIVER SCORE',
                'value' => $operativeScore,
                'unit' => 'SCORE',
                'ring_pct' => max(10, $operativeScore),
                'color' => ($opHasErrors || $operativeScore < 50) ? $sfCritical : $sfGold,
                'critical' => ($opHasErrors || $operativeScore < 50),
                'metric' => 'EINFLUSS AUF TAGES-SCORE',
                'status' => 'ZUM OPERATIVEN STATUS ↗',
            ],
            [
                'url' => route('admin.routine'),
                'sound' => 'action',
                'title' => 'ROUTINE-ZENTRUM',
                'value' => 'GO',
                'unit' => 'LINK',
                'ring_pct' => 100,
                'color' => $sfGold,
                'critical' => false,
                'metric' => 'ALLE ROUTINEN VERWALTEN',
                'status' => 'ROUTINE ÖFFNEN ↗',
            ],
        ];

        // 10. KALENDER SUB-NODES
        $calendarSubNodes = [];
        foreach ($eventsCollection->take(5) as $ev) {
            $isEvToday = isset($ev->start_date) && $ev->start_date->isToday();
            $isEvTom = isset($ev->start_date) && $ev->start_date->isTomorrow();
            $evDateLbl = $isEvToday ? 'HEUTE' : ($isEvTom ? 'MORGEN' : (isset($ev->start_date) ? $ev->start_date->format('d.m.') : 'TERMIN'));
            $evTimeLbl = $ev->is_all_day ? 'GANZTAGS' : (isset($ev->start_date) ? $ev->start_date->format('H:i') : '');
            $isEvCrit = $isEvToday && ($ev->priority ?? '') === 'high';
            $calendarSubNodes[] = [
                'url' => route('admin.calender'),
                'sound' => 'action',
                'title' => $evDateLbl . ($evTimeLbl ? ' · ' . $evTimeLbl : ''),
                'value' => isset($ev->start_date) ? $ev->start_date->format('d.m') : '●',
                'unit' => $evDateLbl,
                'ring_pct' => 100,
                'color' => $isEvCrit ? $sfCritical : $sfGold,
                'critical' => $isEvCrit,
                'metric' => strtoupper(\Illuminate\Support\Str::limit($ev->title ?? 'Termin', 22)),
                'status' => strtoupper(\Illuminate\Support\Str::limit($ev->description ?: 'IM KALENDER ANSEHEN ↗', 24)),
            ];
        }
        $calendarSubNodes[] = [
            'url' => route('admin.calender'),
            'sound' => 'action',
            'title' => 'KALENDER-ZENTRUM',
            'value' => $eventsCount,
            'unit' => 'TOTAL',
            'ring_pct' => 100,
            'color' => $sfGold,
            'critical' => false,
            'metric' => 'TERMINPLANUNG & EVENTS',
            'status' => 'KALENDER ÖFFNEN ↗',
        ];

        // 11. ZEITRAUM & FILTER EINSTELLUNGEN (ÜBER GOLDENES ZAHNRAD IM CHRONO-CORE)
        $startCarbon = !empty($dateStart) ? \Carbon\Carbon::parse($dateStart) : now()->startOfMonth();
        $endCarbon = !empty($dateEnd) ? \Carbon\Carbon::parse($dateEnd) : now()->endOfMonth();
        $filterModeLabel = $filterType === 'business' ? 'Gewerbe' : ($filterType === 'private' ? 'Privat' : 'Alles');

        $settingsSubNodes = [
            // LINKE SEITE (4 ZEITRAUM-SATELLITEN)
            [
                'dateModel' => 'dateStart',
                'sound' => 'select',
                'title' => 'ZEITRAUM VON',
                'value' => $startCarbon->format('d.m.'),
                'unit' => $startCarbon->format('Y'),
                'ring_pct' => 100,
                'color' => $sfGold,
                'critical' => false,
                'metric' => $startCarbon->format('d.m.Y'),
                'status' => 'STARTDATUM WÄHLEN ✎',
            ],
            [
                'dateModel' => 'dateEnd',
                'sound' => 'select',
                'title' => 'ZEITRAUM BIS',
                'value' => $endCarbon->format('d.m.'),
                'unit' => $endCarbon->format('Y'),
                'ring_pct' => 100,
                'color' => $sfGold,
                'critical' => false,
                'metric' => $endCarbon->format('d.m.Y'),
                'status' => 'ENDDATUM WÄHLEN ✎',
            ],
            [
                'wireClick' => 'setCurrentMonth',
                'sound' => 'action',
                'title' => 'SCHNELLWAHL',
                'value' => 'MONAT',
                'unit' => now()->format('m/y'),
                'ring_pct' => $rangeMode === 'current_month' ? 100 : 35,
                'color' => $sfGold,
                'critical' => false,
                'metric' => 'AKTUELLER MONAT',
                'status' => $rangeMode === 'current_month' ? '● AKTIV AUSGEWÄHLT' : 'ZEITRAUM SETZEN ↗',
            ],
            [
                'wireClick' => 'setWholeYear',
                'sound' => 'action',
                'title' => 'SCHNELLWAHL',
                'value' => 'JAHR',
                'unit' => now()->format('Y'),
                'ring_pct' => $rangeMode === 'year' ? 100 : 35,
                'color' => $sfGold,
                'critical' => false,
                'metric' => 'GANZES JAHR',
                'status' => $rangeMode === 'year' ? '● AKTIV AUSGEWÄHLT' : 'ZEITRAUM SETZEN ↗',
            ],
            // RECHTE SEITE (3 KUNDEN-FILTER SATELLITEN: ALLES / GEWERBE / PRIVAT)
            [
                'wireClick' => "\$set('filterType', 'all')",
                'sound' => 'switch',
                'title' => 'MODUS-SWITCH',
                'value' => 'ALLE',
                'unit' => '100%',
                'ring_pct' => $filterType === 'all' ? 100 : 35,
                'color' => $sfGold,
                'critical' => false,
                'metric' => 'ALLES (B2C & B2B)',
                'status' => $filterType === 'all' ? '● AKTIV AUSGEWÄHLT' : 'MODUS AKTIVIEREN ↗',
            ],
            [
                'wireClick' => "\$set('filterType', 'business')",
                'sound' => 'switch',
                'title' => 'MODUS-SWITCH',
                'value' => 'B2B',
                'unit' => 'FIRMA',
                'ring_pct' => $filterType === 'business' ? 100 : 35,
                'color' => $sfGold,
                'critical' => false,
                'metric' => 'GEWERBE',
                'status' => $filterType === 'business' ? '● AKTIV AUSGEWÄHLT' : 'MODUS AKTIVIEREN ↗',
            ],
            [
                'wireClick' => "\$set('filterType', 'private')",
                'sound' => 'switch',
                'title' => 'MODUS-SWITCH',
                'value' => 'B2C',
                'unit' => 'PRIVAT',
                'ring_pct' => $filterType === 'private' ? 100 : 35,
                'color' => $sfGold,
                'critical' => false,
                'metric' => 'PRIVAT',
                'status' => $filterType === 'private' ? '● AKTIV AUSGEWÄHLT' : 'MODUS AKTIVIEREN ↗',
            ],
        ];

        // --- ALLE ORBITAL-SZENEN (ROOT + DRILL-DOWNS + SETTINGS) ---
        $orbitalScenes = [
            'root' => [
                'isRoot' => true,
                'elevated' => false,
                'coreTitle' => 'Bestellungen',
                'coreValue' => number_format($totalOrdersCount, 0, ',', '.'),
                'coreBadgeHtml' => ($openOrdersCount > 0 ? $openOrdersCount . ' Offen · ' : '') . '<span class="text-[#ffe39f]">' . number_format($stats['shop_revenue'] ?? 0, 0, ',', '.') . ' €</span>' . ($openOrdersCount > 0 ? '' : ' Umsatz'),
                'coreClick' => "window.sfPlaySound && window.sfPlaySound('chart'); activeTab = 'ecommerce'",
                'nodes' => $orbitalNodes,
            ],
            'settings' => [
                'isRoot' => false,
                'elevated' => false,
                'coreTitle' => 'Zeitraum & Filter',
                'coreValue' => $filterModeLabel,
                'coreBadgeHtml' => '<span class="text-[#ffe39f]">' . $startCarbon->format('d.m.y') . ' – ' . $endCarbon->format('d.m.y') . '</span>',
                'coreClick' => "window.sfPlaySound && window.sfPlaySound('back'); activeTab = null",
                'nodes' => $settingsSubNodes,
            ],
            'operative' => [
                'isRoot' => false,
                'elevated' => false,
                'coreTitle' => 'Operativer Status',
                'coreValue' => $operativeScore,
                'coreBadgeHtml' => '<span class="text-[#ffe39f]">' . $totalTodos . ' Aufgaben</span> · ' . e($opScoreText),
                'coreClick' => "window.sfPlaySound && window.sfPlaySound('back'); activeTab = null",
                'nodes' => $operativeSubNodes,
            ],
            'shop' => [
                'isRoot' => false,
                'elevated' => true,
                'coreTitle' => 'Shop Health',
                'coreValue' => $shopScore,
                'coreBadgeHtml' => 'Ø <span class="text-[#ffe39f]">' . number_format($stats['avg_profit'] ?? 0, 0, ',', '.') . ' €</span> Mtl. Gewinn',
                'coreClick' => "window.sfPlaySound && window.sfPlaySound('back'); activeTab = null",
                'nodes' => $shopSubNodes,
            ],
            'system' => [
                'isRoot' => false,
                'elevated' => true,
                'coreTitle' => 'Infrastruktur',
                'coreValue' => $systemScore,
                'coreBadgeHtml' => '<span class="text-[#ffe39f]">' . e($sysText) . '</span>',
                'coreClick' => "window.sfPlaySound && window.sfPlaySound('back'); activeTab = null",
                'nodes' => $systemSubNodes,
            ],
            'security' => [
                'isRoot' => false,
                'elevated' => true,
                'coreTitle' => 'Sicherheit & Abwehr',
                'coreValue' => $securityScore,
                'coreBadgeHtml' => '<span class="text-[#ffe39f]">' . e($secText) . '</span>',
                'coreClick' => "window.sfPlaySound && window.sfPlaySound('back'); activeTab = null",
                'nodes' => $securitySubNodes,
            ],
            'capacities' => [
                'isRoot' => false,
                'elevated' => true,
                'coreTitle' => 'Kapazitäten',
                'coreValue' => $shopLoad . '%',
                'coreBadgeHtml' => 'Speicher: <span class="text-[#ffe39f]">' . round(100 - $storageUsedPct) . '% frei</span>',
                'coreClick' => "window.sfPlaySound && window.sfPlaySound('back'); activeTab = null",
                'nodes' => $capacitiesSubNodes,
            ],
            'profit' => [
                'isRoot' => false,
                'elevated' => true,
                'coreTitle' => 'Gewinn (Mtl.)',
                'coreValue' => number_format($stats['avg_profit'] ?? 0, 0, ',', '.') . ' €',
                'coreBadgeHtml' => $missing > 0 ? '<span class="text-red-400">-' . number_format($missing, 0, ',', '.') . ' €</span> bis Break-Even' : '<span class="text-[#ffe39f]">Break-Even erreicht!</span>',
                'coreClick' => "window.sfPlaySound && window.sfPlaySound('back'); activeTab = null",
                'nodes' => $profitSubNodes,
            ],
            'ecommerce' => [
                'isRoot' => false,
                'elevated' => true,
                'coreTitle' => 'E-Commerce',
                'coreValue' => number_format($totalOrdersCount, 0, ',', '.'),
                'coreBadgeHtml' => '<span class="text-[#ffe39f]">' . number_format($stats['shop_revenue'] ?? 0, 0, ',', '.') . ' €</span> Umsatz',
                'coreClick' => "window.sfPlaySound && window.sfPlaySound('back'); activeTab = null",
                'nodes' => $ecommerceSubNodes,
            ],
            'traffic' => [
                'isRoot' => false,
                'elevated' => true,
                'coreTitle' => 'Traffic & Kunden',
                'coreValue' => number_format($stats['frontend_unique_total'] ?? 0, 0, ',', '.'),
                'coreBadgeHtml' => '● <span class="text-[#ffe39f]">' . $onlineCount . ' Nutzer</span> online',
                'coreClick' => "window.sfPlaySound && window.sfPlaySound('back'); activeTab = null",
                'nodes' => $trafficSubNodes,
            ],
            'routine' => [
                'isRoot' => false,
                'elevated' => false,
                'coreTitle' => 'Live Routine',
                'coreValue' => isset($currentActiveRoutine) && $currentActiveRoutine ? \Carbon\Carbon::parse($currentActiveRoutine->start_time)->format('H:i') : ($routineOpenCount > 0 ? $routineOpenCount : 'OK'),
                'coreBadgeHtml' => '<span class="text-[#ffe39f]">' . e($routineActiveTitle) . '</span>',
                'coreClick' => "window.sfPlaySound && window.sfPlaySound('back'); activeTab = null",
                'nodes' => $routineSubNodes,
            ],
            'calendar' => [
                'isRoot' => false,
                'elevated' => false,
                'coreTitle' => 'Kalender',
                'coreValue' => $eventsCount,
                'coreBadgeHtml' => '<span class="text-[#ffe39f]">' . e($nextEventLabel) . '</span>',
                'coreClick' => "window.sfPlaySound && window.sfPlaySound('back'); activeTab = null",
                'nodes' => $calendarSubNodes,
            ],
        ];

        // Gemeinsames Prozent-Koordinatensystem (0..100%) für SVG (0..1000) & HTML-Satelliten
        $buildCoords = function (int $count, string $side): array {
            $yPcts = match ($count) {
                0 => [],
                1 => [50],
                2 => [28, 72],
                3 => [17, 50, 83],
                4 => [12, 37, 63, 88],
                default => array_map(fn($i) => round(10 + ($i * (80 / max(1, $count - 1))), 1), range(0, $count - 1)),
            };
            $coords = [];
            foreach ($yPcts as $yPct) {
                $arcOffset = round((abs($yPct - 50) / 40) * 5.5, 1);
                $xPct = $side === 'left' ? (27.5 + $arcOffset) : (72.5 - $arcOffset);
                $coords[] = [
                    'xPct' => $xPct,
                    'yPct' => $yPct,
                    'svgX' => $xPct * 10,
                    'svgY' => $yPct * 10,
                ];
            }
            return $coords;
        };

        // Vorberechnung aller Szenen inkl. winkelgenauer Andock-Segmente (Links = 90°..270°, Rechts = -90°..+90°)
        $segCirc = round(2 * pi() * 140, 2);
        $preparedScenes = [];
        foreach ($orbitalScenes as $sceneKey => $scene) {
            $sNodes = $scene['nodes'];
            $sHalf = (int) ceil(count($sNodes) / 2);
            $sLeftNodes = array_values(array_slice($sNodes, 0, $sHalf));
            $sRightNodes = array_values(array_slice($sNodes, $sHalf));
            $sLeftCoords = $buildCoords(count($sLeftNodes), 'left');
            $sRightCoords = $buildCoords(count($sRightNodes), 'right');

            $lCount = count($sLeftNodes);
            $rCount = count($sRightNodes);
            $leftPrepared = [];
            $rightPrepared = [];
            $dockSegments = [];

            // Linke Satelliten: docken auf dem linken Halbkreis (von 270° oben gegen den Uhrzeigersinn bis 90° unten)
            foreach ($sLeftNodes as $lIdx => $lNode) {
                $lStepDeg = 180 / max(1, $lCount);
                $centerAngleDeg = 270 - (($lIdx + 0.5) * $lStepDeg);
                $segRot = round(270 - (($lIdx + 1) * $lStepDeg) + 3.5, 2);
                $segLen = round(max(14, (($segCirc / 2) / max(1, $lCount)) - 11), 2);
                $rad = deg2rad($centerAngleDeg);
                $c1x = round(500 + cos($rad) * 115, 1);
                $c1y = round(500 + sin($rad) * 115, 1);
                $coord = $sLeftCoords[$lIdx];
                $ex = $coord['svgX'];
                $ey = $coord['svgY'];
                $c2x = round($ex + 65, 1);

                $leftPrepared[] = array_merge($lNode, [
                    'coord' => $coord,
                    'pathD' => "M 500 500 C {$c1x} {$c1y}, {$c2x} {$ey}, {$ex} {$ey}",
                ]);
                $dockSegments[] = [
                    'rot' => $segRot,
                    'len' => $segLen,
                    'color' => $lNode['critical'] ? '#ef4444' : ($lIdx % 2 === 0 ? '#e5c178' : '#C5A059'),
                ];
            }

            // Rechte Satelliten: docken auf dem rechten Halbkreis (von -90° oben im Uhrzeigersinn bis +90° unten)
            foreach ($sRightNodes as $rIdx => $rNode) {
                $rStepDeg = 180 / max(1, $rCount);
                $centerAngleDeg = -90 + (($rIdx + 0.5) * $rStepDeg);
                $segRot = round(-90 + ($rIdx * $rStepDeg) + 3.5, 2);
                $segLen = round(max(14, (($segCirc / 2) / max(1, $rCount)) - 11), 2);
                $rad = deg2rad($centerAngleDeg);
                $c1x = round(500 + cos($rad) * 115, 1);
                $c1y = round(500 + sin($rad) * 115, 1);
                $coord = $sRightCoords[$rIdx];
                $ex = $coord['svgX'];
                $ey = $coord['svgY'];
                $c2x = round($ex - 65, 1);

                $rightPrepared[] = array_merge($rNode, [
                    'coord' => $coord,
                    'pathD' => "M 500 500 C {$c1x} {$c1y}, {$c2x} {$ey}, {$ex} {$ey}",
                ]);
                $dockSegments[] = [
                    'rot' => $segRot,
                    'len' => $segLen,
                    'color' => $rNode['critical'] ? '#ef4444' : ($rIdx % 2 === 0 ? '#e5c178' : '#C5A059'),
                ];
            }

            $preparedScenes[$sceneKey] = array_merge($scene, [
                'showExpr' => $sceneKey === 'root' ? 'activeTab === null' : "activeTab === '{$sceneKey}'",
                'leftNodes' => $leftPrepared,
                'rightNodes' => $rightPrepared,
                'allNodes' => array_merge($leftPrepared, $rightPrepared),
                'dockSegments' => $dockSegments,
            ]);
        }
    @endphp

    <!-- ===================================================================== -->
    <!-- A. FOKUS-MODUS: ANIME.JS CHRONO-CORE & INTERAKTIVE ORBITAL-SZENEN     -->
    <!-- ===================================================================== -->
    <div x-show="focusMode"
         x-transition.opacity.duration.300ms
         class="relative w-full pt-2 pb-4 select-none overflow-visible">

        <!-- 1. DESKTOP ORBITAL-BÜHNE (>= 1024px): Persistenter Kern & überlappende Radial-Übergänge -->
        <div :style="['shop', 'system', 'security', 'capacities', 'profit', 'ecommerce', 'traffic'].includes(activeTab) ? 'height: 510px;' : ''"
             class="hidden lg:block relative w-full h-[680px] xl:h-[740px] sf-stage-height overflow-visible">

            <!-- SZENEN-LAYER: ENERGIESTRAHLEN & SATELLITEN (Überlappender Radial-Warp beim Öffnen & Zurückgehen) -->
            @foreach($preparedScenes as $sceneKey => $scene)
                <div wire:key="orbital-scene-desktop-{{ $sceneKey }}"
                     x-show="{{ $scene['showExpr'] }}"
                     @if($sceneKey !== 'root') x-cloak @endif
                     x-transition:enter="transition-all duration-500 ease-[cubic-bezier(0.16,1,0.3,1)]"
                     x-transition:enter-start="opacity-0 scale-[0.86]"
                     x-transition:enter-end="opacity-100 scale-100"
                     x-transition:leave="transition-all duration-300 ease-[cubic-bezier(0.4,0,0.2,1)] pointer-events-none"
                     x-transition:leave-start="opacity-100 scale-100"
                     x-transition:leave-end="opacity-0 scale-[0.86]"
                     class="absolute inset-0 w-full h-full overflow-visible origin-center">

                    <!-- SVG ENERGY BEAMS (GPU-optimiert ohne teuren Fullscreen-GaussianBlur) -->
                    <svg class="absolute inset-0 w-full h-full pointer-events-none z-0 overflow-visible" viewBox="0 0 1000 1000" preserveAspectRatio="none">
                        <!-- Linke Energiestrahlen vom Zentrum (500, 500) durch das zugehörige Ring-Segment zum Satelliten -->
                        @foreach($scene['leftNodes'] as $idx => $node)
                            @php
                                $c = $node['coord'];
                                $ex = $c['svgX'];
                                $ey = $c['svgY'];
                                $pathD = $node['pathD'];
                            @endphp
                            <path d="{{ $pathD }}" fill="none" stroke="{{ $node['color'] }}" stroke-width="6" stroke-opacity="0.14" stroke-linecap="round" />
                            <path d="{{ $pathD }}" fill="none" stroke="{{ $node['color'] }}" stroke-width="1.75" stroke-opacity="0.48" />
                            <path d="{{ $pathD }}" fill="none" stroke="{{ $node['color'] }}" stroke-width="2.8" stroke-opacity="0.95" stroke-linecap="round" class="sf-energy-stream" style="animation-delay: -{{ $idx * 0.55 }}s;" />
                            <circle r="3.4" fill="{{ $node['color'] }}">
                                <animateMotion dur="{{ 2.6 + ($idx * 0.35) }}s" repeatCount="indefinite" path="{{ $pathD }}" />
                            </circle>
                            <circle cx="{{ $ex }}" cy="{{ $ey }}" r="4.5" fill="{{ $node['color'] }}" />
                            <circle cx="{{ $ex }}" cy="{{ $ey }}" r="8.5" fill="none" stroke="{{ $node['color'] }}" stroke-opacity="0.38" stroke-width="1.2" />
                        @endforeach

                        <!-- Rechte Energiestrahlen vom Zentrum (500, 500) durch das zugehörige Ring-Segment zum Satelliten -->
                        @foreach($scene['rightNodes'] as $idx => $node)
                            @php
                                $c = $node['coord'];
                                $ex = $c['svgX'];
                                $ey = $c['svgY'];
                                $pathD = $node['pathD'];
                            @endphp
                            <path d="{{ $pathD }}" fill="none" stroke="{{ $node['color'] }}" stroke-width="6" stroke-opacity="0.14" stroke-linecap="round" />
                            <path d="{{ $pathD }}" fill="none" stroke="{{ $node['color'] }}" stroke-width="1.75" stroke-opacity="0.48" />
                            <path d="{{ $pathD }}" fill="none" stroke="{{ $node['color'] }}" stroke-width="2.8" stroke-opacity="0.95" stroke-linecap="round" class="sf-energy-stream" style="animation-delay: -{{ ($idx + 1) * 0.45 }}s;" />
                            <circle r="3.4" fill="{{ $node['color'] }}">
                                <animateMotion dur="{{ 2.7 + ($idx * 0.35) }}s" repeatCount="indefinite" path="{{ $pathD }}" />
                            </circle>
                            <circle cx="{{ $ex }}" cy="{{ $ey }}" r="4.5" fill="{{ $node['color'] }}" />
                            <circle cx="{{ $ex }}" cy="{{ $ey }}" r="8.5" fill="none" stroke="{{ $node['color'] }}" stroke-opacity="0.38" stroke-width="1.2" />
                        @endforeach
                    </svg>

                    <!-- LINKE SATELLITEN -->
                    @foreach($scene['leftNodes'] as $idx => $node)
                        @php
                            $c = $node['coord'];
                            $rCirc = 2 * pi() * 34;
                            $rOffset = $rCirc - (($node['ring_pct'] / 100) * $rCirc);
                            $soundType = $node['sound'] ?? 'select';
                            if (isset($node['dateModel'])) {
                                $clickJs = "window.sfPlaySound && window.sfPlaySound('{$soundType}'); const inp = \$el.querySelector('input[type=date]'); if (inp) { inp.showPicker ? inp.showPicker() : inp.focus(); }";
                            } elseif (isset($node['wireClick'])) {
                                $clickJs = "window.sfPlaySound && window.sfPlaySound('{$soundType}')";
                            } elseif (isset($node['url'])) {
                                $clickJs = "window.sfPlaySound && window.sfPlaySound('{$soundType}'); window.location.href = '{$node['url']}'";
                            } else {
                                $clickJs = "window.sfPlaySound && window.sfPlaySound('{$soundType}'); activeTab = '{$node['tab']}'";
                            }
                        @endphp
                        <div style="left: {{ $c['xPct'] }}%; top: {{ $c['yPct'] }}%; transform: translate(-100%, -50%);"
                             class="absolute z-20 pr-4">
                            <div @mouseenter="window.sfPlaySound && window.sfPlaySound('hover')"
                                 @if(isset($node['wireClick'])) wire:click.prevent="{{ $node['wireClick'] }}" @endif
                                 @click="{{ $clickJs }}"
                                 class="group cursor-pointer flex items-center justify-end gap-4 bg-transparent border-0 p-0 transition-transform duration-300 hover:scale-105 sf-floating-satellite"
                                 style="animation-delay: -{{ $idx * 1.1 }}s;">

                                <div class="text-right max-w-[195px] xl:max-w-[240px]">
                                    <div class="text-[10px] font-black uppercase tracking-[0.22em] text-gray-500 dark:text-gray-400 group-hover:text-[#C5A059] transition-colors truncate">
                                        {{ $node['title'] }}
                                    </div>
                                    @if(isset($node['dateModel']))
                                        <div class="mt-0.5 flex justify-end">
                                            <input type="date"
                                                   wire:model.live.blur="{{ $node['dateModel'] }}"
                                                   @click.stop
                                                   @change="window.sfPlaySound && window.sfPlaySound('action')"
                                                   class="bg-transparent border-0 border-b border-[#C5A059]/60 focus:border-[#C5A059] focus:ring-0 p-0 pb-0.5 text-xs font-black uppercase tracking-widest text-gray-900 dark:text-white cursor-pointer text-right" />
                                        </div>
                                    @else
                                        <div class="text-xs font-black uppercase tracking-widest text-gray-900 dark:text-white mt-0.5 truncate">
                                            {{ $node['metric'] }}
                                        </div>
                                    @endif
                                    <div class="text-[10px] font-black uppercase tracking-widest mt-0.5 truncate" style="color: {{ $node['color'] }};">
                                        {{ $node['status'] }}
                                    </div>
                                </div>

                                <div class="relative w-20 h-20 flex items-center justify-center shrink-0">
                                    <svg class="w-full h-full overflow-visible" viewBox="0 0 84 84">
                                        <circle cx="42" cy="42" r="39" fill="none" stroke="{{ $node['color'] }}" stroke-opacity="0.3" stroke-width="1" stroke-dasharray="2 4" />
                                        <circle cx="42" cy="42" r="34" fill="none" stroke="currentColor" stroke-width="5" class="text-gray-200/70 dark:text-gray-800/90" />
                                        <circle cx="42" cy="42" r="34" fill="none" stroke="{{ $node['color'] }}" stroke-width="5"
                                                stroke-dasharray="{{ $rCirc }}" stroke-dashoffset="{{ $rOffset }}" stroke-linecap="round"
                                                transform="rotate(-90 42 42)"
                                                style="filter: drop-shadow(0 0 6px {{ $node['color'] }});"
                                                class="transition-all duration-700" />
                                    </svg>
                                    <div class="absolute inset-0 flex flex-col items-center justify-center">
                                        <span class="text-base font-black leading-none tracking-tight text-gray-900 dark:text-white" style="text-shadow: 0 0 12px {{ $node['color'] }}55;">
                                            {{ $node['value'] }}
                                        </span>
                                        <span class="text-[7px] font-black uppercase tracking-widest text-gray-400 dark:text-gray-500 mt-0.5">
                                            {{ $node['unit'] }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach

                    <!-- RECHTE SATELLITEN -->
                    @foreach($scene['rightNodes'] as $idx => $node)
                        @php
                            $c = $node['coord'];
                            $rCirc = 2 * pi() * 34;
                            $rOffset = $rCirc - (($node['ring_pct'] / 100) * $rCirc);
                            $soundType = $node['sound'] ?? 'select';
                            if (isset($node['dateModel'])) {
                                $clickJs = "window.sfPlaySound && window.sfPlaySound('{$soundType}'); const inp = \$el.querySelector('input[type=date]'); if (inp) { inp.showPicker ? inp.showPicker() : inp.focus(); }";
                            } elseif (isset($node['wireClick'])) {
                                $clickJs = "window.sfPlaySound && window.sfPlaySound('{$soundType}')";
                            } elseif (isset($node['url'])) {
                                $clickJs = "window.sfPlaySound && window.sfPlaySound('{$soundType}'); window.location.href = '{$node['url']}'";
                            } else {
                                $clickJs = "window.sfPlaySound && window.sfPlaySound('{$soundType}'); activeTab = '{$node['tab']}'";
                            }
                        @endphp
                        <div style="left: {{ $c['xPct'] }}%; top: {{ $c['yPct'] }}%; transform: translate(0%, -50%);"
                             class="absolute z-20 pl-4">
                            <div @mouseenter="window.sfPlaySound && window.sfPlaySound('hover')"
                                 @if(isset($node['wireClick'])) wire:click.prevent="{{ $node['wireClick'] }}" @endif
                                 @click="{{ $clickJs }}"
                                 class="group cursor-pointer flex items-center justify-start gap-4 bg-transparent border-0 p-0 transition-transform duration-300 hover:scale-105 sf-floating-satellite"
                                 style="animation-delay: -{{ ($idx + 0.5) * 1.1 }}s;">

                                <div class="relative w-20 h-20 flex items-center justify-center shrink-0">
                                    <svg class="w-full h-full overflow-visible" viewBox="0 0 84 84">
                                        <circle cx="42" cy="42" r="39" fill="none" stroke="{{ $node['color'] }}" stroke-opacity="0.3" stroke-width="1" stroke-dasharray="2 4" />
                                        <circle cx="42" cy="42" r="34" fill="none" stroke="currentColor" stroke-width="5" class="text-gray-200/70 dark:text-gray-800/90" />
                                        <circle cx="42" cy="42" r="34" fill="none" stroke="{{ $node['color'] }}" stroke-width="5"
                                                stroke-dasharray="{{ $rCirc }}" stroke-dashoffset="{{ $rOffset }}" stroke-linecap="round"
                                                transform="rotate(-90 42 42)"
                                                style="filter: drop-shadow(0 0 6px {{ $node['color'] }});"
                                                class="transition-all duration-700" />
                                    </svg>
                                    <div class="absolute inset-0 flex flex-col items-center justify-center">
                                        <span class="text-base font-black leading-none tracking-tight text-gray-900 dark:text-white" style="text-shadow: 0 0 12px {{ $node['color'] }}55;">
                                            {{ $node['value'] }}
                                        </span>
                                        <span class="text-[7px] font-black uppercase tracking-widest text-gray-400 dark:text-gray-500 mt-0.5">
                                            {{ $node['unit'] }}
                                        </span>
                                    </div>
                                </div>

                                <div class="text-left max-w-[195px] xl:max-w-[240px]">
                                    <div class="text-[10px] font-black uppercase tracking-[0.22em] text-gray-500 dark:text-gray-400 group-hover:text-[#C5A059] transition-colors truncate">
                                        {{ $node['title'] }}
                                    </div>
                                    @if(isset($node['dateModel']))
                                        <div class="mt-0.5 flex justify-start">
                                            <input type="date"
                                                   wire:model.live.blur="{{ $node['dateModel'] }}"
                                                   @click.stop
                                                   @change="window.sfPlaySound && window.sfPlaySound('action')"
                                                   class="bg-transparent border-0 border-b border-[#C5A059]/60 focus:border-[#C5A059] focus:ring-0 p-0 pb-0.5 text-xs font-black uppercase tracking-widest text-gray-900 dark:text-white cursor-pointer" />
                                        </div>
                                    @else
                                        <div class="text-xs font-black uppercase tracking-widest text-gray-900 dark:text-white mt-0.5 truncate">
                                            {{ $node['metric'] }}
                                        </div>
                                    @endif
                                    <div class="text-[10px] font-black uppercase tracking-widest mt-0.5 truncate" style="color: {{ $node['color'] }};">
                                        {{ $node['status'] }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endforeach

            <!-- ZENTRUM (DESKTOP): PERSISTENTER ANIME.JS SEELENFUNKE CHRONO-KERN AUF (50%, 50%) -->
            <div style="left: 50%; top: 50%; transform: translate(-50%, -50%);"
                 class="absolute z-30 flex items-center justify-center">
                <!-- Äußere konzentrische Radar-Ringe -->
                <div class="absolute w-[370px] h-[370px] rounded-full border border-dashed border-[#C5A059]/20 pointer-events-none"></div>
                <div class="absolute w-[425px] h-[425px] rounded-full border border-[#C5A059]/10 pointer-events-none"></div>
                <div x-show="orderIncomingPulse"
                     x-cloak
                     class="absolute w-[355px] h-[355px] rounded-full border-2 border-[#ffe39f] animate-ping pointer-events-none opacity-75"></div>

                <!-- Live-Bestellung Toast-Impuls über dem Chrono-Core -->
                <div x-show="orderIncomingPulse"
                     x-cloak
                     x-transition:enter="transition-all duration-300 ease-out"
                     x-transition:enter-start="opacity-0 translate-y-3 scale-90"
                     x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                     x-transition:leave="transition-all duration-300 ease-in"
                     x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                     x-transition:leave-end="opacity-0 -translate-y-2 scale-95"
                     class="absolute -top-12 left-1/2 -translate-x-1/2 z-40 pointer-events-none whitespace-nowrap px-4 py-1.5 rounded-full bg-[#141417]/95 border-2 border-[#ffe39f] shadow-[0_0_30px_rgba(255,227,159,0.75)] flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-ping"></span>
                    <span class="text-[10px] font-black uppercase tracking-[0.22em] text-[#ffe39f]">
                        ✨ Neue Bestellung eingegangen
                    </span>
                </div>

                <div @mouseenter="window.sfPlaySound && window.sfPlaySound('hover')"
                     @click="if (activeTab === null) { window.sfPlaySound && window.sfPlaySound('chart'); activeTab = 'ecommerce'; } else { window.sfPlaySound && window.sfPlaySound('back'); activeTab = null; }"
                     :class="orderIncomingPulse ? 'ring-4 ring-[#ffe39f]/90 shadow-[0_0_95px_rgba(255,227,159,0.85)] scale-[1.04]' : ''"
                     class="relative w-[310px] h-[310px] xl:w-[330px] xl:h-[330px] rounded-full bg-[#141417] sf-chrono-core-dial flex items-center justify-center cursor-pointer group transition-all duration-500 hover:scale-[1.02]">

                    <svg class="w-full h-full overflow-visible" viewBox="0 0 300 300">
                        <defs>
                            <radialGradient id="sfDialSurfaceMain" cx="50%" cy="50%" r="50%">
                                <stop offset="0%" stop-color="#221e18" />
                                <stop offset="72%" stop-color="#151518" />
                                <stop offset="100%" stop-color="#0c0c0f" />
                            </radialGradient>
                            <radialGradient id="sfCenterReadabilityMain" cx="50%" cy="50%" r="50%">
                                <stop offset="0%" stop-color="#0b0b0e" stop-opacity="0.93" />
                                <stop offset="66%" stop-color="#101014" stop-opacity="0.80" />
                                <stop offset="100%" stop-color="#151518" stop-opacity="0" />
                            </radialGradient>
                        </defs>

                        <circle cx="150" cy="150" r="147" fill="#101013" stroke="#C5A059" stroke-opacity="0.35" stroke-width="1.5" />
                        <circle cx="150" cy="150" r="134" fill="url(#sfDialSurfaceMain)" />

                        <!-- LAYER 1: WINKELGENAUER SEGMENT-RING (Exakt dort Rot/Gold, wo die jeweilige Kachel andockt!) -->
                        @foreach($preparedScenes as $sceneKey => $scene)
                            <g x-show="{{ $scene['showExpr'] }}"
                               @if($sceneKey !== 'root') x-cloak @endif
                               x-transition.opacity.duration.350ms>
                                @foreach($scene['dockSegments'] as $seg)
                                    <circle cx="150" cy="150" r="140" fill="none"
                                            stroke="{{ $seg['color'] }}"
                                            stroke-width="3.6"
                                            stroke-dasharray="{{ $seg['len'] }} {{ $segCirc }}"
                                            stroke-linecap="round"
                                            style="filter: drop-shadow(0 0 4px {{ $seg['color'] }});"
                                            transform="rotate({{ $seg['rot'] }} 150 150)" />
                                @endforeach
                            </g>
                        @endforeach

                        <!-- LAYER 2: 120 feine Chronographen-Teilstriche in Seelenfunke Gold (Einmalig gerendert, 100% GPU) -->
                        <g class="sf-spin-slow">
                            @for($i = 0; $i < 120; $i++)
                                @php
                                    $angle = $i * 3;
                                    $isMajor = ($i % 5 === 0);
                                    $y1 = $isMajor ? 18 : 20;
                                    $y2 = $isMajor ? 27 : 25;
                                @endphp
                                <line x1="150" y1="{{ $y1 }}" x2="150" y2="{{ $y2 }}"
                                      stroke="{{ $isMajor ? '#e5c178' : '#9a7b3e' }}"
                                      stroke-width="{{ $isMajor ? '1.3' : '0.8' }}"
                                      stroke-opacity="{{ $isMajor ? '0.92' : '0.55' }}"
                                      transform="rotate({{ $angle }} 150 150)" />
                            @endfor
                        </g>

                        <!-- LAYER 3: Horizontale Laser-Frequenzlinien in Seelenfunke Gold (100% GPU CSS-Keyframes) -->
                        <g>
                            @for($b = 0; $b < 39; $b++)
                                @php
                                    $by = 62 + ($b * 4.5);
                                    $normY = ($b - 19) / 19;
                                    $envelope = cos($normY * 1.35) * (0.72 + 0.35 * sin(abs($normY) * 4.2));
                                    $halfW = max(8, round(68 * abs($envelope)));
                                @endphp
                                <line x1="{{ 150 - $halfW }}"
                                      y1="{{ $by }}"
                                      x2="{{ 150 + $halfW }}"
                                      y2="{{ $by }}"
                                      stroke="#C5A059"
                                      stroke-width="1.5"
                                      stroke-opacity="0.38"
                                      stroke-linecap="round"
                                      class="sf-freq-bar"
                                      style="animation-delay: -{{ round($b * 0.09, 2) }}s;" />
                            @endfor
                        </g>

                        <!-- Horizontale Mittel-Achse -->
                        <line x1="42" y1="150" x2="258" y2="150" stroke="#C5A059" stroke-width="1.6" stroke-opacity="0.75" />

                        <!-- LAYER 4: 3 feine gegenläufige Champagner-Gold Orbital-Bögen -->
                        <g class="sf-spin-reverse" opacity="0.9">
                            <circle cx="150" cy="150" r="108" fill="none" stroke="#fff3d1" stroke-width="1.1" stroke-dasharray="95 600" stroke-linecap="round" transform="rotate(15 150 150)" />
                            <circle cx="150" cy="150" r="113" fill="none" stroke="#e5c178" stroke-width="1.3" stroke-dasharray="120 600" stroke-linecap="round" transform="rotate(5 150 150)" />
                            <circle cx="150" cy="150" r="118" fill="none" stroke="#C5A059" stroke-width="1.5" stroke-dasharray="145 600" stroke-linecap="round" transform="rotate(-5 150 150)" />
                        </g>

                        <!-- Dezente Glas-Reflexion oben links -->
                        <path d="M 56 175 A 102 102 0 0 1 135 52 L 143 68 A 84 84 0 0 0 72 168 Z" fill="rgba(255,255,255,0.045)" />

                        <!-- LAYER 5: GPU-animierte Sinus-Punktwelle (0% JS-Last, 120 FPS) -->
                        <g>
                            @for($w = 0; $w < 23; $w++)
                                @php
                                    $envAmp = round(4 + 14 * sin(($w / 22) * pi()), 1);
                                @endphp
                                <circle cx="{{ round(62 + ($w * (176 / 22)), 2) }}"
                                        cy="150"
                                        r="3.1"
                                        fill="#e5c178"
                                        class="sf-wave-dot"
                                        style="--amp: {{ $envAmp }}px; animation-delay: -{{ round($w * 0.13, 2) }}s;" />
                            @endfor
                            <circle cx="66" cy="122" r="2.8" fill="#C5A059" class="sf-wave-dot" style="--amp: 5px; animation-delay: -0.4s;" />
                            <circle cx="72" cy="122" r="2.8" fill="#e5c178" class="sf-wave-dot" style="--amp: -5px; animation-delay: -1.1s;" />
                            <circle cx="56" cy="202" r="2.8" fill="#C5A059" class="sf-wave-dot" style="--amp: 6px; animation-delay: -0.8s;" />
                            <circle cx="232" cy="104" r="2.8" fill="#e5c178" class="sf-wave-dot" style="--amp: 7px; animation-delay: -1.5s;" />
                            <circle cx="222" cy="168" r="2.8" fill="#C5A059" class="sf-wave-dot" style="--amp: 5px; animation-delay: -0.3s;" />
                            <circle cx="228" cy="168" r="2.8" fill="#e5c178" class="sf-wave-dot" style="--amp: -5px; animation-delay: -1.7s;" />
                        </g>

                        <!-- Sanfte zentrale Abdunklung für gestochen scharfe Lesbarkeit -->
                        <circle cx="150" cy="150" r="84" fill="url(#sfCenterReadabilityMain)" />
                    </svg>

                    <!-- GOLDENES ZAHNRAD-SYMBOL MITTIG OBEN IM KREIS (Öffnet die fliegenden Zeitraum- & Filter-Einstellungen) -->
                    <button type="button"
                            @mouseenter="window.sfPlaySound && window.sfPlaySound('hover')"
                            @click.stop="window.sfPlaySound && window.sfPlaySound(activeTab === 'settings' ? 'back' : 'select'); activeTab = (activeTab === 'settings' ? null : 'settings')"
                            title="Zeitraum & Filter-Einstellungen öffnen"
                            :class="activeTab === 'settings'
                                ? 'bg-[#C5A059] text-gray-950 shadow-[0_0_22px_rgba(197,160,89,0.95)] scale-110'
                                : 'bg-[#09090c]/90 text-[#e5c178] hover:bg-[#C5A059] hover:text-gray-950 shadow-[0_0_16px_rgba(197,160,89,0.45)]'"
                            class="absolute top-4 left-1/2 -translate-x-1/2 z-30 pointer-events-auto w-9 h-9 rounded-full border border-[#C5A059] flex items-center justify-center transition-all duration-300 active:scale-90 group/gear">
                        <x-heroicon-s-cog-6-tooth class="w-4.5 h-4.5 transition-transform duration-500 group-hover/gear:rotate-90" ::class="activeTab === 'settings' ? 'rotate-90' : ''" />
                    </button>

                    <!-- LAYER 6: ZENTRALE ANZEIGE IM HAUPTKREIS (Sanfter Crossfade & Scale beim Wechsel) -->
                    @foreach($preparedScenes as $sceneKey => $scene)
                        <div wire:key="orbital-core-desktop-{{ $sceneKey }}"
                             x-show="{{ $scene['showExpr'] }}"
                             @if($sceneKey !== 'root') x-cloak @endif
                             x-transition:enter="transition-all duration-400 ease-[cubic-bezier(0.22,1,0.36,1)]"
                             x-transition:enter-start="opacity-0 scale-90 translate-y-1.5"
                             x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                             x-transition:leave="transition-all duration-200 ease-in"
                             x-transition:leave-start="opacity-100 scale-100"
                             x-transition:leave-end="opacity-0 scale-95 -translate-y-1.5"
                             class="absolute inset-0 flex flex-col items-center justify-center text-center pointer-events-none px-6 z-20">
                            <span class="text-[10px] sm:text-[11px] font-black uppercase tracking-[0.28em] text-[#f5dca0] drop-shadow-[0_2px_6px_rgba(0,0,0,1)] mb-1">
                                {{ $scene['coreTitle'] }}
                            </span>

                            <span :class="orderIncomingPulse ? 'scale-115 text-[#ffe39f]' : 'text-[#fffdf7]'"
                                  class="text-4xl sm:text-5xl xl:text-6xl font-serif font-black tracking-tight leading-none my-1 transition-all duration-500"
                                  style="text-shadow: 0 0 24px rgba(197, 160, 89, 0.95), 0 3px 10px rgba(0, 0, 0, 1);">
                                {{ $scene['coreValue'] }}
                            </span>

                            <div class="mt-2 inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-[#09090c]/95 border-2 border-[#C5A059] shadow-[0_0_20px_rgba(197,160,89,0.45)]">
                                <span class="w-2 h-2 rounded-full bg-[#e5c178] animate-ping shrink-0"></span>
                                <span class="text-[10px] sm:text-[11px] font-black uppercase tracking-widest text-white drop-shadow-[0_1px_3px_rgba(0,0,0,1)]">
                                    {!! $scene['coreBadgeHtml'] !!}
                                </span>
                            </div>
                        </div>
                    @endforeach

                    <!-- ZURÜCK-BUTTON IM HAUPTKREIS UNTEN MITTIG (Bei allen Drill-Down & Settings-Ansichten) -->
                    <button type="button"
                            x-show="activeTab !== null"
                            x-cloak
                            x-transition:enter="transition-all duration-300 ease-out"
                            x-transition:enter-start="opacity-0 translate-y-2 scale-90"
                            x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                            x-transition:leave="transition-all duration-200 ease-in"
                            x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                            x-transition:leave-end="opacity-0 translate-y-2 scale-90"
                            @mouseenter="window.sfPlaySound && window.sfPlaySound('hover')"
                            @click.stop="window.sfPlaySound && window.sfPlaySound('back'); activeTab = null"
                            title="Zurück zur Hauptansicht"
                            class="absolute bottom-4 left-1/2 -translate-x-1/2 z-30 pointer-events-auto group/back flex items-center gap-1.5 px-3.5 py-1.5 rounded-full bg-[#09090c]/95 border border-[#C5A059] text-[#f5dca0] hover:bg-[#C5A059] hover:text-gray-950 transition-all duration-200 shadow-[0_0_16px_rgba(197,160,89,0.55)] active:scale-90">
                        <x-heroicon-s-arrow-uturn-left class="w-3.5 h-3.5 transition-transform group-hover/back:-translate-x-0.5" />
                        <span class="text-[9px] font-black uppercase tracking-widest">Zurück</span>
                    </button>
                </div>
            </div>

            <!-- VERTIKALER ENERGIESTRAHL NACH UNTEN (Wenn ein Chart / Detailbereich unterhalb geöffnet ist) -->
            <div x-show="['shop', 'system', 'security', 'capacities', 'profit', 'ecommerce', 'traffic'].includes(activeTab)"
                 x-cloak
                 x-transition.opacity.duration.400ms
                 class="absolute left-1/2 bottom-0 -translate-x-1/2 w-0.5 h-16 bg-gradient-to-b from-[#C5A059] via-[#C5A059]/60 to-transparent shadow-[0_0_12px_#C5A059] pointer-events-none z-10"></div>
        </div>

        <!-- 2. MOBILE & TABLET ANSICHT (< 1024px): Persistenter Kern & geschmeidige Satelliten-Liste -->
        <div class="lg:hidden flex flex-col items-center w-full">
            <div @click="if (activeTab === null) { window.sfPlaySound && window.sfPlaySound('chart'); activeTab = 'ecommerce'; } else { window.sfPlaySound && window.sfPlaySound('back'); activeTab = null; }"
                 :class="orderIncomingPulse ? 'ring-4 ring-[#ffe39f]/90 shadow-[0_0_75px_rgba(255,227,159,0.85)] scale-[1.03]' : ''"
                 class="relative w-[290px] h-[290px] sm:w-[320px] sm:h-[320px] rounded-full bg-[#141417] sf-chrono-core-dial flex items-center justify-center cursor-pointer transition-all duration-500">
                <svg class="w-full h-full overflow-visible" viewBox="0 0 300 300">
                    <circle cx="150" cy="150" r="147" fill="#101013" stroke="#C5A059" stroke-opacity="0.35" stroke-width="1.5" />
                    <circle cx="150" cy="150" r="134" fill="#151518" />
                    @foreach($preparedScenes as $sceneKey => $scene)
                        <g x-show="{{ $scene['showExpr'] }}" @if($sceneKey !== 'root') x-cloak @endif>
                            @foreach($scene['dockSegments'] as $seg)
                                <circle cx="150" cy="150" r="140" fill="none"
                                        stroke="{{ $seg['color'] }}"
                                        stroke-width="3.5"
                                        stroke-dasharray="{{ $seg['len'] }} {{ $segCirc }}"
                                        stroke-linecap="round"
                                        transform="rotate({{ $seg['rot'] }} 150 150)" />
                            @endforeach
                        </g>
                    @endforeach
                    <line x1="42" y1="150" x2="258" y2="150" stroke="#C5A059" stroke-width="1.5" stroke-opacity="0.6" />
                    @for($w = 0; $w < 23; $w++)
                        @php
                            $envAmp = round(4 + 13 * sin(($w / 22) * pi()), 1);
                        @endphp
                        <circle cx="{{ round(62 + ($w * (176 / 22)), 2) }}"
                                cy="150"
                                r="3"
                                fill="#e5c178"
                                class="sf-wave-dot"
                                style="--amp: {{ $envAmp }}px; animation-delay: -{{ round($w * 0.13, 2) }}s;" />
                    @endfor
                    <circle cx="150" cy="150" r="84" fill="url(#sfCenterReadabilityMain)" />
                </svg>

                <!-- GOLDENES ZAHNRAD-SYMBOL MITTIG OBEN IM KREIS (MOBILE) -->
                <button type="button"
                        @click.stop="window.sfPlaySound && window.sfPlaySound(activeTab === 'settings' ? 'back' : 'select'); activeTab = (activeTab === 'settings' ? null : 'settings')"
                        title="Zeitraum & Filter-Einstellungen öffnen"
                        :class="activeTab === 'settings'
                            ? 'bg-[#C5A059] text-gray-950 shadow-[0_0_18px_rgba(197,160,89,0.9)]'
                            : 'bg-[#09090c]/90 text-[#e5c178] shadow-[0_0_12px_rgba(197,160,89,0.4)]'"
                        class="absolute top-3.5 left-1/2 -translate-x-1/2 z-30 pointer-events-auto w-8 h-8 rounded-full border border-[#C5A059] flex items-center justify-center transition-all">
                    <x-heroicon-s-cog-6-tooth class="w-4 h-4" ::class="activeTab === 'settings' ? 'rotate-90' : ''" />
                </button>

                @foreach($preparedScenes as $sceneKey => $scene)
                    <div wire:key="orbital-core-mobile-{{ $sceneKey }}"
                         x-show="{{ $scene['showExpr'] }}"
                         @if($sceneKey !== 'root') x-cloak @endif
                         x-transition.opacity.duration.300ms
                         class="absolute inset-0 flex flex-col items-center justify-center text-center pointer-events-none px-6 z-20">
                        <span class="text-[10px] font-black uppercase tracking-[0.28em] text-[#f5dca0] drop-shadow-[0_2px_6px_rgba(0,0,0,1)] mb-1">
                            {{ $scene['coreTitle'] }}
                        </span>
                        <span :class="orderIncomingPulse ? 'scale-110 text-[#ffe39f]' : 'text-[#fffdf7]'"
                              class="text-4xl sm:text-5xl font-serif font-black tracking-tight leading-none my-1 transition-all duration-500"
                              style="text-shadow: 0 0 22px rgba(197, 160, 89, 0.95), 0 3px 10px rgba(0, 0, 0, 1);">
                            {{ $scene['coreValue'] }}
                        </span>
                        <div class="mt-2 inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-[#09090c]/95 border-2 border-[#C5A059] shadow-[0_0_15px_rgba(197,160,89,0.4)]">
                            <span class="w-2 h-2 rounded-full bg-[#e5c178] animate-ping shrink-0"></span>
                            <span class="text-[10px] font-black uppercase tracking-widest text-white">
                                {!! $scene['coreBadgeHtml'] !!}
                            </span>
                        </div>
                    </div>
                @endforeach

                <button type="button"
                        x-show="activeTab !== null"
                        x-cloak
                        @click.stop="window.sfPlaySound && window.sfPlaySound('back'); activeTab = null"
                        class="absolute bottom-3.5 left-1/2 -translate-x-1/2 z-30 pointer-events-auto flex items-center gap-1.5 px-3 py-1 rounded-full bg-[#09090c]/95 border border-[#C5A059] text-[#f5dca0] text-[9px] font-black uppercase tracking-widest shadow-[0_0_12px_rgba(197,160,89,0.5)]">
                    <x-heroicon-s-arrow-uturn-left class="w-3 h-3" />
                    <span>Zurück</span>
                </button>
            </div>

            <div class="w-0.5 h-10 bg-gradient-to-b from-[#C5A059] to-[#C5A059]/30 shadow-[0_0_10px_#C5A059]"></div>

            @foreach($preparedScenes as $sceneKey => $scene)
                <div wire:key="orbital-list-mobile-{{ $sceneKey }}"
                     x-show="{{ $scene['showExpr'] }}"
                     @if($sceneKey !== 'root') x-cloak @endif
                     x-transition:enter="transition-all duration-400 ease-out"
                     x-transition:enter-start="opacity-0 translate-y-3"
                     x-transition:enter-end="opacity-100 translate-y-0"
                     class="relative w-full max-w-md space-y-6 px-3 pt-2">
                    <div class="absolute left-12 top-4 bottom-4 w-px bg-gradient-to-b from-[#C5A059]/60 via-[#C5A059]/30 to-transparent pointer-events-none"></div>

                    @foreach($scene['allNodes'] as $idx => $node)
                        @php
                            $rCirc = 2 * pi() * 30;
                            $rOffset = $rCirc - (($node['ring_pct'] / 100) * $rCirc);
                            $soundType = $node['sound'] ?? 'select';
                            if (isset($node['dateModel'])) {
                                $clickJs = "window.sfPlaySound && window.sfPlaySound('{$soundType}'); const inp = \$el.querySelector('input[type=date]'); if (inp) { inp.showPicker ? inp.showPicker() : inp.focus(); }";
                            } elseif (isset($node['wireClick'])) {
                                $clickJs = "window.sfPlaySound && window.sfPlaySound('{$soundType}')";
                            } elseif (isset($node['url'])) {
                                $clickJs = "window.sfPlaySound && window.sfPlaySound('{$soundType}'); window.location.href = '{$node['url']}'";
                            } else {
                                $clickJs = "window.sfPlaySound && window.sfPlaySound('{$soundType}'); activeTab = '{$node['tab']}'";
                            }
                        @endphp
                        <div @if(isset($node['wireClick'])) wire:click.prevent="{{ $node['wireClick'] }}" @endif
                             @click="{{ $clickJs }}"
                             class="relative flex items-center gap-4 cursor-pointer bg-transparent p-0">
                            <div class="relative w-18 h-18 flex items-center justify-center shrink-0 bg-gray-50/90 dark:bg-gray-950/90 rounded-full">
                                <svg class="w-18 h-18 overflow-visible" viewBox="0 0 72 72">
                                    <circle cx="36" cy="36" r="30" fill="none" stroke="currentColor" stroke-width="4.5" class="text-gray-200 dark:text-gray-800" />
                                    <circle cx="36" cy="36" r="30" fill="none" stroke="{{ $node['color'] }}" stroke-width="4.5"
                                            stroke-dasharray="{{ $rCirc }}" stroke-dashoffset="{{ $rOffset }}" stroke-linecap="round"
                                            transform="rotate(-90 36 36)"
                                            style="filter: drop-shadow(0 0 5px {{ $node['color'] }});" />
                                </svg>
                                <div class="absolute inset-0 flex flex-col items-center justify-center">
                                    <span class="text-sm font-black text-gray-900 dark:text-white leading-none">{{ $node['value'] }}</span>
                                    <span class="text-[6px] font-black uppercase tracking-widest text-gray-400 mt-0.5">{{ $node['unit'] }}</span>
                                </div>
                            </div>

                            <div class="w-5 h-0.5 shrink-0 rounded-full" style="background: linear-gradient(90deg, {{ $node['color'] }}, transparent); box-shadow: 0 0 8px {{ $node['color'] }};"></div>

                            <div class="flex-1 min-w-0">
                                <div class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-500 dark:text-gray-400 truncate">{{ $node['title'] }}</div>
                                @if(isset($node['dateModel']))
                                    <input type="date"
                                           wire:model.live.blur="{{ $node['dateModel'] }}"
                                           @click.stop
                                           @change="window.sfPlaySound && window.sfPlaySound('action')"
                                           class="mt-0.5 bg-transparent border-0 border-b border-[#C5A059]/60 focus:border-[#C5A059] focus:ring-0 p-0 pb-0.5 text-xs font-black uppercase tracking-wider text-gray-900 dark:text-white cursor-pointer" />
                                @else
                                    <div class="text-xs font-black uppercase tracking-wider text-gray-900 dark:text-white mt-0.5 truncate">{{ $node['metric'] }}</div>
                                @endif
                                <div class="text-[10px] font-black uppercase tracking-wider mt-0.5 truncate" style="color: {{ $node['color'] }};">{{ $node['status'] }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>
    </div>

    <!-- ===================================================================== -->
    <!-- B. STANDARD-MODUS („ALLE KACHELN“): EXAKTES 4ER-GRID WIE IM SCREENSHOT -->
    <!-- ===================================================================== -->
    <div x-show="!focusMode" x-cloak x-transition.opacity.duration.200ms>
        <!-- TOP ROW: THE 4 SCORES (4 NEBENEINANDER) -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">

            <!-- 1. SHOP HEALTH SCORE -->
            <div @click="activeTab = activeTab === 'shop' ? null : 'shop'"
                 class="bg-white/90 dark:bg-gray-900/80 backdrop-blur-md rounded-[2rem] p-6 dark:shadow-2xl border flex flex-col items-center text-center relative overflow-hidden group transition-colors duration-300 hover-theme-pulse"
                 :class="activeTab === 'shop' ? 'active-theme-pulse' : 'border-gray-200 dark:border-gray-800'">

                <div class="absolute top-4 right-4 z-10" x-data="{ tooltip: false }" @mouseenter="tooltip = true" @mouseleave="tooltip = false">
                    <div class="text-gray-400 hover:text-gray-900 dark:hover:text-white cursor-help">
                        <x-heroicon-o-information-circle class="w-5 h-5" />
                    </div>
                    <div x-show="tooltip" x-transition.opacity.duration.200ms class="absolute top-full right-0 mt-2 w-56 p-4 bg-white dark:bg-gray-950 border border-gray-200 dark:border-gray-700 rounded-xl shadow-lg dark:shadow-2xl text-left pointer-events-none" style="display: none;">
                        <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-2 border-b border-gray-200 dark:border-gray-800 pb-2">Zusammensetzung</p>
                        <ul class="text-xs text-gray-600 dark:text-gray-300 space-y-1.5">
                            <li class="flex justify-between items-center"><span class="text-gray-500 dark:text-gray-400">Break-Even:</span> <span class="text-emerald-500 dark:text-emerald-400">+30</span></li>
                            <li class="flex justify-between items-center"><span class="text-gray-500 dark:text-gray-400">Netto-Ziel:</span> <span class="text-emerald-500 dark:text-emerald-400">+20</span></li>
                            <li class="flex justify-between items-center"><span class="text-gray-500 dark:text-gray-400">Gewinn-Marge:</span> <span class="text-emerald-500 dark:text-emerald-400">+25</span></li>
                            <li class="flex justify-between items-center"><span class="text-gray-500 dark:text-gray-400">Umsatz-Trend:</span> <span class="text-emerald-500 dark:text-emerald-400">+15</span></li>
                            <li class="flex justify-between items-center"><span class="text-gray-500 dark:text-gray-400">Offene Posten:</span> <span class="text-emerald-500 dark:text-emerald-400">+10</span></li>
                            <li class="flex justify-between items-center pt-1 mt-1 border-t border-gray-200 dark:border-gray-800"><span class="text-gray-500 dark:text-gray-400">Abzüge Op.:</span> <span class="text-red-500 dark:text-red-400">-5 / -1</span></li>
                            <li class="flex justify-between items-center"><span class="text-gray-500 dark:text-gray-400">Abzüge Sys.:</span> <span class="text-red-500 dark:text-red-400">-10 / -2</span></li>
                        </ul>
                    </div>
                </div>

                <h3 class="text-xs font-black text-gray-400 dark:text-gray-500 uppercase tracking-[0.2em] mb-4">Shop Health</h3>

                <div class="relative w-28 h-28 flex items-center justify-center shrink-0 mb-4">
                    <svg class="w-full h-full transform -rotate-90 overflow-visible" viewBox="0 0 100 100">
                        <circle cx="50" cy="50" r="40" fill="transparent" stroke="currentColor" stroke-width="8" class="text-gray-200 dark:text-gray-800"></circle>
                        <circle cx="50" cy="50" r="40" fill="transparent" stroke="{{ $shopStrokeColor }}" stroke-width="8" stroke-dasharray="{{ $circumference }}" stroke-dashoffset="{{ $shopOffset }}" stroke-linecap="round" class="transition-all duration-1000 ease-out dark:drop-shadow-[0_0_8px_currentColor]"></circle>
                    </svg>
                    <div class="absolute flex flex-col items-center justify-center">
                        <span class="text-3xl font-black {{ $shopColorClass }} dark:drop-shadow-[0_0_10px_currentColor]">{{ $shopScore }}</span>
                        <span class="text-[9px] font-black uppercase tracking-widest text-gray-400 dark:text-gray-500">Score</span>
                    </div>
                </div>

            </div>

            <!-- 2. OPERATIVER SCORE -->
            <div @click="activeTab = activeTab === 'operative' ? null : 'operative'"
                 class="bg-white/90 dark:bg-gray-900/80 backdrop-blur-md rounded-[2rem] p-6 dark:shadow-2xl border flex flex-col items-center text-center relative overflow-hidden group transition-colors duration-300 hover-theme-pulse"
                 :class="activeTab === 'operative' ? 'active-theme-pulse' : 'border-gray-200 dark:border-gray-800'">

                <div class="absolute top-4 right-4 z-10" x-data="{ tooltip: false }" @mouseenter="tooltip = true" @mouseleave="tooltip = false">
                    <div class="text-gray-400 hover:text-gray-900 dark:hover:text-white cursor-help">
                        <x-heroicon-o-information-circle class="w-5 h-5" />
                    </div>
                    <div x-show="tooltip" x-transition.opacity.duration.200ms class="absolute top-full right-0 mt-2 w-56 p-4 bg-white dark:bg-gray-950 border border-gray-200 dark:border-gray-700 rounded-xl shadow-lg dark:shadow-2xl text-left pointer-events-none" style="display: none;">
                        <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-2 border-b border-gray-200 dark:border-gray-800 pb-2">Berechnung</p>
                        <div class="text-xs text-gray-600 dark:text-gray-300 space-y-2">
                            <p class="text-gray-500 dark:text-gray-400 text-[10px] leading-tight mb-2">Startwert: <span class="text-gray-900 dark:text-white">100 Punkte</span></p>
                            <ul class="space-y-1.5">
                                <li class="flex justify-between items-center"><span class="text-gray-500 dark:text-gray-400">Pro offenes Todo:</span> <span class="text-red-500 dark:text-red-400">-1</span></li>
                                <li class="flex justify-between items-center"><span class="text-gray-500 dark:text-gray-400">Pro Warnung:</span> <span class="text-red-500 dark:text-red-400">-10</span></li>
                                <li class="flex justify-between items-center"><span class="text-gray-500 dark:text-gray-400">Pro Fehler:</span> <span class="text-red-500 dark:text-red-400">-25</span></li>
                            </ul>
                        </div>
                    </div>
                </div>

                <h3 class="text-xs font-black text-gray-400 dark:text-gray-500 uppercase tracking-[0.2em] mb-4">Operativer Status</h3>

                <div class="relative w-28 h-28 flex items-center justify-center shrink-0 mb-4">
                    <svg class="w-full h-full transform -rotate-90 overflow-visible" viewBox="0 0 100 100">
                        <circle cx="50" cy="50" r="40" fill="transparent" stroke="currentColor" stroke-width="8" class="text-gray-200 dark:text-gray-800"></circle>
                        <circle cx="50" cy="50" r="40" fill="transparent" stroke="{{ $opStrokeColor }}" stroke-width="8" stroke-dasharray="{{ $circumference }}" stroke-dashoffset="{{ $opOffset }}" stroke-linecap="round" class="transition-all duration-1000 ease-out dark:drop-shadow-[0_0_8px_currentColor]"></circle>
                    </svg>
                    <div class="absolute flex flex-col items-center justify-center">
                        <span class="text-3xl font-black {{ $opColorClass }} dark:drop-shadow-[0_0_10px_currentColor]">{{ $operativeScore }}</span>
                        <span class="text-[9px] font-black uppercase tracking-widest text-gray-400 dark:text-gray-500">Score</span>
                    </div>
                </div>

                <div class="mb-4">
                    @if($totalTodos > 0)
                        <p class="text-[10px] font-bold uppercase tracking-widest mt-0.5 text-gray-500 dark:text-gray-400">
                            <span class="text-gray-900 dark:text-white">{{ $totalTodos }}</span> Aufgaben
                        </p>
                        <p class="text-[10px] font-bold uppercase tracking-widest mt-1.5 {{ $opColorClass }} animate-pulse">{{ $opScoreText }}</p>
                    @else
                        <p class="text-[10px] font-bold uppercase tracking-widest mt-0.5 text-gray-500 dark:text-gray-400">Keine Todos</p>
                        <p class="text-[10px] font-bold uppercase tracking-widest mt-1.5 text-emerald-500">{{ $opScoreText }}</p>
                    @endif
                </div>

            </div>

            <!-- 3. SYSTEM SCORE -->
            <div @click="activeTab = activeTab === 'system' ? null : 'system'"
                 class="bg-white/90 dark:bg-gray-900/80 backdrop-blur-md rounded-[2rem] p-6 dark:shadow-2xl border flex flex-col items-center text-center relative overflow-hidden group transition-colors duration-300 hover-theme-pulse"
                 :class="activeTab === 'system' ? 'active-theme-pulse' : 'border-gray-200 dark:border-gray-800'">

                <div class="absolute top-4 right-4 text-[9px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 bg-gray-100 dark:bg-gray-800 px-2.5 py-1 rounded-full border border-gray-200 dark:border-gray-700">Live</div>

                <h3 class="text-xs font-black text-gray-400 dark:text-gray-500 uppercase tracking-[0.2em] mb-4">Infrastruktur</h3>

                <div class="relative w-28 h-28 flex items-center justify-center shrink-0 mb-4">
                    <svg class="w-full h-full transform -rotate-90 overflow-visible" viewBox="0 0 100 100">
                        <circle cx="50" cy="50" r="40" fill="transparent" stroke="currentColor" stroke-width="8" class="text-gray-200 dark:text-gray-800"></circle>
                        <circle cx="50" cy="50" r="40" fill="transparent" stroke="{{ $sysStrokeColor }}" stroke-width="8" stroke-dasharray="{{ $circumference }}" stroke-dashoffset="{{ $sysOffset }}" stroke-linecap="round" class="transition-all duration-1000 ease-out dark:drop-shadow-[0_0_8px_currentColor]"></circle>
                    </svg>
                    <div class="absolute flex flex-col items-center justify-center">
                        <span class="text-3xl font-black {{ $sysColorClass }} dark:drop-shadow-[0_0_10px_currentColor]">{{ $systemScore }}</span>
                        <span class="text-[9px] font-black uppercase tracking-widest text-gray-400 dark:text-gray-500">Score</span>
                    </div>
                </div>

                <div class="mb-4">
                    @if($sysErrors > 0 || $sysWarnings > 0)
                        <p class="text-[10px] font-bold uppercase tracking-widest mt-0.5 text-gray-500 dark:text-gray-400">
                            <span class="text-gray-900 dark:text-white">{{ $sysErrors + $sysWarnings }}</span> Warnungen
                        </p>
                        <p class="text-[10px] font-bold uppercase tracking-widest mt-1.5 {{ $sysColorClass }} animate-pulse">{{ $sysText }}</p>
                    @else
                        <p class="text-[10px] font-bold uppercase tracking-widest mt-0.5 text-gray-500 dark:text-gray-400">Alle APIs stabil</p>
                        <p class="text-[10px] font-bold uppercase tracking-widest mt-1.5 text-emerald-500">{{ $sysText }}</p>
                    @endif
                </div>

            </div>

            <!-- 4. SECURITY SCORE -->
            <div @click="activeTab = activeTab === 'security' ? null : 'security'"
                 class="bg-white/90 dark:bg-gray-900/80 backdrop-blur-md rounded-[2rem] p-6 dark:shadow-2xl border flex flex-col items-center text-center relative overflow-hidden group transition-colors duration-300 hover-theme-pulse"
                 :class="activeTab === 'security' ? 'active-theme-pulse' : 'border-gray-200 dark:border-gray-800'">

                <div class="absolute top-4 right-4 z-10" x-data="{ tooltip: false }" @mouseenter="tooltip = true" @mouseleave="tooltip = false">
                    <div class="text-gray-400 hover:text-gray-900 dark:hover:text-white cursor-help">
                        <x-heroicon-o-information-circle class="w-5 h-5" />
                    </div>
                    <div x-show="tooltip" x-transition.opacity.duration.200ms class="absolute top-full right-0 mt-2 w-56 p-4 bg-white dark:bg-gray-950 border border-gray-200 dark:border-gray-700 rounded-xl shadow-lg dark:shadow-2xl text-left pointer-events-none" style="display: none;">
                        <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-2 border-b border-gray-200 dark:border-gray-800 pb-2">Berechnung (Letzte 24h)</p>
                        <div class="text-xs text-gray-600 dark:text-gray-300 space-y-2">
                            <p class="text-gray-500 dark:text-gray-400 text-[10px] leading-tight mb-2">Startwert: <span class="text-gray-900 dark:text-white">100 Punkte</span></p>
                            <ul class="space-y-1.5">
                                <li class="flex justify-between items-center"><span class="text-gray-500 dark:text-gray-400">Pro Brute-Force IP (&ge;3 Versuche):</span> <span class="text-red-500 dark:text-red-400">-15</span></li>
                                <li class="flex justify-between items-center"><span class="text-gray-500 dark:text-gray-400">Pro Sicherheits-Alarm:</span> <span class="text-red-500 dark:text-red-400">-10</span></li>
                                <li class="flex justify-between items-center"><span class="text-gray-500 dark:text-gray-400">Isolierte Fehl-Logins (max -20):</span> <span class="text-red-500 dark:text-red-400">-1</span></li>
                            </ul>
                        </div>
                    </div>
                </div>

                <h3 class="text-xs font-black text-gray-400 dark:text-gray-500 uppercase tracking-[0.2em] mb-4">Sicherheit & Abwehr</h3>

                <div class="relative w-28 h-28 flex items-center justify-center shrink-0 mb-4">
                    <svg class="w-full h-full transform -rotate-90 overflow-visible" viewBox="0 0 100 100">
                        <circle cx="50" cy="50" r="40" fill="transparent" stroke="currentColor" stroke-width="8" class="text-gray-200 dark:text-gray-800"></circle>
                        <circle cx="50" cy="50" r="40" fill="transparent" stroke="{{ $secStrokeColor }}" stroke-width="8" stroke-dasharray="{{ $circumference }}" stroke-dashoffset="{{ $secOffset }}" stroke-linecap="round" class="transition-all duration-1000 ease-out dark:drop-shadow-[0_0_8px_currentColor]"></circle>
                    </svg>
                    <div class="absolute flex flex-col items-center justify-center">
                        <span class="text-3xl font-black {{ $secColorClass }} dark:drop-shadow-[0_0_10px_currentColor]">{{ $securityScore }}</span>
                        <span class="text-[9px] font-black uppercase tracking-widest text-gray-400 dark:text-gray-500">Score</span>
                    </div>
                </div>

                <div class="mb-4">
                    @if($failedLogins24h > 0 || $securityWarnings24h > 0)
                        <p class="text-[10px] font-bold uppercase tracking-widest mt-0.5 text-gray-500 dark:text-gray-400">
                            <span class="text-gray-900 dark:text-white">{{ $failedLogins24h + $securityWarnings24h }}</span> Ereignisse (24h)
                        </p>
                        <p class="text-[10px] font-bold uppercase tracking-widest mt-1.5 {{ $secColorClass }} animate-pulse">{{ $secText }}</p>
                    @else
                        <p class="text-[10px] font-bold uppercase tracking-widest mt-0.5 text-gray-500 dark:text-gray-400">0 Ereignisse</p>
                        <p class="text-[10px] font-bold uppercase tracking-widest mt-1.5 text-purple-500 dark:text-purple-400">{{ $secText }}</p>
                    @endif
                </div>

            </div>
        </div>

        <!-- 2nd ROW: THE 4 BENTO WIDGETS (4 NEBENEINANDER) -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 mb-6">
            <!-- 4. SPEICHER & PRODUKTIONS-LAST -->
            <div @click="activeTab = activeTab === 'capacities' ? null : 'capacities'"
                 class="bg-white/90 dark:bg-gray-900/80 backdrop-blur-md rounded-3xl p-4 dark:shadow-xl border flex flex-col items-center text-center relative overflow-hidden group transition-colors duration-300 hover-theme-pulse"
                 :class="activeTab === 'capacities' ? 'active-theme-pulse' : 'border-gray-200 dark:border-gray-800'">
                 
                 <h3 class="text-[10px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-[0.2em] mb-2.5">Kapazitäten</h3>

                <div class="relative w-24 h-24 flex items-center justify-center shrink-0 mb-3 bg-gray-50 dark:bg-gray-950 rounded-2xl border border-gray-200 dark:border-gray-800">
                    <div class="absolute inset-0 flex flex-col items-center justify-center">
                        <x-heroicon-s-cog class="w-6 h-6 {{ $shopLoad > 90 ? 'text-red-500 animate-spin-slow' : ($shopLoad >= $shopThreshold1 ? 'text-orange-500' : 'text-emerald-500') }} mb-1 opacity-80" />
                        <span class="text-base font-black text-gray-900 dark:text-white tracking-tighter leading-none">{{ $shopLoad }}%</span>
                        <span class="text-[8px] font-bold text-gray-500 uppercase tracking-widest mt-0.5">Auslastung</span>
                    </div>
                </div>

                <div class="mb-3">
                    <p class="text-[9px] font-bold uppercase tracking-widest text-gray-500 dark:text-gray-400">Speicherplatz</p>
                    <p class="text-[10px] font-black uppercase tracking-widest mt-1 {{ $storageUsedPct >= $storageThreshold1 ? 'text-amber-500' : 'text-blue-500' }}">{{ isset($storageData['percent_free']) ? $storageData['percent_free'] . '% Frei' : round(100 - $storageUsedPct) . '% Frei' }}</p>
                </div>

            </div>

            <!-- 5. GEWINN-ENTWICKLUNG -->
            <div @click="activeTab = activeTab === 'profit' ? null : 'profit'"
                 class="bg-white/90 dark:bg-gray-900/80 backdrop-blur-md rounded-3xl p-4 dark:shadow-xl border flex flex-col items-center text-center relative overflow-visible group transition-colors duration-300 hover-theme-pulse"
                 :class="activeTab === 'profit' ? 'active-theme-pulse' : 'border-gray-200 dark:border-gray-800'">
                 
                 <div class="flex items-center justify-center gap-1.5 mb-2.5">
                     <h3 class="text-[10px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-[0.2em]">Gewinn</h3>
                     @include('livewire.shop.master.master-analytics-partials.profit-breakdown-popover', ['positionClass' => 'bottom-full left-1/2 -translate-x-1/2 mb-3'])
                 </div>

                <div class="relative w-24 h-24 flex items-center justify-center shrink-0 mb-3 bg-gray-50 dark:bg-gray-950 rounded-2xl border border-gray-200 dark:border-gray-800">
                    <div class="absolute inset-0 flex flex-col items-center justify-center">
                        <x-heroicon-s-currency-euro class="w-6 h-6 text-emerald-400 mb-1 opacity-80 dark:drop-shadow-[0_0_8px_rgba(52,211,153,0.5)]" />
                        <span class="text-base font-black text-gray-900 dark:text-white tracking-tighter leading-none">{{ number_format($stats['avg_profit'] ?? 0, 0, ',', '.') }}</span>
                        <span class="text-[8px] font-bold text-gray-500 uppercase tracking-widest mt-0.5">Mtl. Gewinn</span>
                    </div>
                </div>

                <div class="mb-3">
                    <p class="text-[9px] font-bold uppercase tracking-widest text-gray-500 dark:text-gray-400">Break-Even</p>
                    @if($missing > 0)
                        <p class="text-[10px] font-black uppercase tracking-widest mt-1 {{ $beColor }}">-{{ number_format($missing, 0, ',', '.') }} € fehlend</p>
                    @else
                        <p class="text-[10px] font-black uppercase tracking-widest mt-1 {{ $beColor }}">Erreicht!</p>
                    @endif
                </div>

            </div>

            <!-- 6. E-COMMERCE EINBLICKE -->
            <div @click="activeTab = activeTab === 'ecommerce' ? null : 'ecommerce'"
                 class="bg-white/90 dark:bg-gray-900/80 backdrop-blur-md rounded-3xl p-4 dark:shadow-xl border flex flex-col items-center text-center relative overflow-hidden group transition-all duration-300 hover-theme-pulse"
                 :class="[
                    activeTab === 'ecommerce' ? 'active-theme-pulse' : 'border-gray-200 dark:border-gray-800',
                    orderIncomingPulse ? 'ring-2 ring-[#C5A059] shadow-[0_0_35px_rgba(197,160,89,0.65)] scale-[1.03]' : ''
                 ]">
                 
                 <h3 class="text-[10px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-[0.2em] mb-2.5">E-Commerce</h3>

                <div class="relative w-24 h-24 flex items-center justify-center shrink-0 mb-3 bg-gray-50 dark:bg-gray-950 rounded-2xl border border-gray-200 dark:border-gray-800">
                    <div class="absolute inset-0 flex flex-col items-center justify-center">
                        <x-heroicon-s-shopping-bag class="w-6 h-6 text-[#C5A059] mb-1 opacity-80" />
                        <span class="text-base font-black text-gray-900 dark:text-white tracking-tighter leading-none">{{ number_format($stats['orders_total'] ?? 0, 0, ',', '.') }}</span>
                        <span class="text-[8px] font-bold text-gray-500 uppercase tracking-widest mt-0.5">Insg. Orders</span>
                    </div>
                </div>

                <div class="mb-3">
                    <p class="text-[9px] font-bold uppercase tracking-widest text-gray-500 dark:text-gray-400">Verkäufe Detail</p>
                    <p class="text-[10px] font-black uppercase tracking-widest mt-1 text-[#C5A059]">Umsatz & Artikel</p>
                </div>

            </div>

            <!-- 7. TRAFFIC & KUNDEN -->
            <div @click="activeTab = activeTab === 'traffic' ? null : 'traffic'"
                 class="bg-white/90 dark:bg-gray-900/80 backdrop-blur-md rounded-3xl p-4 dark:shadow-xl border flex flex-col items-center text-center relative overflow-hidden group transition-colors duration-300 hover-theme-pulse"
                 :class="activeTab === 'traffic' ? 'active-theme-pulse' : 'border-gray-200 dark:border-gray-800'">
                 
                 <h3 class="text-[10px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-[0.2em] mb-2.5">Traffic & Kunden</h3>

                <div class="relative w-24 h-24 flex items-center justify-center shrink-0 mb-3 bg-gray-50 dark:bg-gray-950 rounded-2xl border border-gray-200 dark:border-gray-800">
                    <div class="absolute inset-0 flex flex-col items-center justify-center">
                        <x-heroicon-s-globe-alt class="w-6 h-6 text-blue-400 mb-1 opacity-80 dark:drop-shadow-[0_0_8px_rgba(96,165,250,0.5)]" />
                        <span class="text-base font-black text-gray-900 dark:text-white tracking-tighter leading-none">{{ number_format($stats['frontend_unique_total'] ?? 0, 0, ',', '.') }}</span>
                        <span class="text-[8px] font-bold text-gray-500 uppercase tracking-widest mt-0.5">Besucher</span>
                    </div>
                </div>

                <div class="mb-3 space-y-2">
                    <div>
                        <p class="text-[9px] font-bold uppercase tracking-widest text-gray-500 dark:text-gray-400">Total Pageviews</p>
                        <p class="text-[10px] font-black uppercase tracking-widest mt-0.5 text-blue-600 dark:text-blue-300">{{ number_format($stats['frontend_visits_total'] ?? 0, 0, ',', '.') }}</p>
                    </div>
                    <div>
                        <p class="text-[9px] font-bold uppercase tracking-widest text-gray-500 dark:text-gray-400">Aktuell Online</p>
                        <div class="flex items-center justify-center gap-1.5 mt-0.5">
                            <span class="relative flex h-1.5 w-1.5">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-1.5 w-1.5 bg-emerald-500"></span>
                            </span>
                            <p class="text-[10px] font-black uppercase tracking-widest text-emerald-500 dark:text-emerald-400">{{ $onlineCount }} Nutzer</p>
                        </div>
                    </div>
                </div>

            </div>

        </div>
    </div>

    <!-- MASTER DETAILS VIEW -->
    <div x-show="activeTab !== null && (!focusMode || !['operative', 'routine', 'calendar', 'settings'].includes(activeTab))"
         :class="focusMode ? 'mt-0' : 'mt-8'"
         x-transition:enter="transition-all duration-500 ease-[cubic-bezier(0.22,1,0.36,1)]"
         x-transition:enter-start="opacity-0 translate-y-6 scale-[0.98]"
         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
         x-transition:leave="transition-all duration-250 ease-in"
         x-transition:leave-start="opacity-100 translate-y-0 scale-100"
         x-transition:leave-end="opacity-0 translate-y-4 scale-[0.98]"
         x-cloak>
        <div :class="focusMode ? 'bg-transparent border-0 shadow-none p-0' : 'bg-white/95 dark:bg-gray-900/90 backdrop-blur-xl rounded-[2.5rem] p-6 lg:p-10 dark:shadow-2xl border border-gray-200 dark:border-gray-800'"
             class="relative z-20 transition-all duration-300">

            <!-- 1. SHOP DETAILS -->
            <div x-show="activeTab === 'shop'"
                 x-cloak
                 x-transition:enter="transition-all duration-500 ease-[cubic-bezier(0.22,1,0.36,1)]"
                 x-transition:enter-start="opacity-0 translate-y-4"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 class="space-y-6">

                @if($opErrorCount > 0 || $opWarningCount > 0)
                    <div class="bg-red-500/10 dark:bg-red-950/20 rounded-xl p-5 border border-red-500/20 relative">
                         <div class="flex items-start gap-3">
                             <x-heroicon-o-exclamation-triangle class="w-5 h-5 text-red-500 mt-0.5 shrink-0" />
                             <div>
                                 <h3 class="text-xs font-bold text-red-600 dark:text-red-400 uppercase tracking-widest mb-1.5">Operative Abzüge: -{{ ($opErrorCount * 5) + ($opWarningCount * 1) }} Punkte</h3>
                                 <p class="text-[10px] text-red-600/90 dark:text-red-400/80 leading-relaxed font-semibold">Dein finanzieller Basis-Score wurde aufgrund operativer Störfälle aus den Fachbereichen (z.B. Fehlerhafte Bestellungen, offene Schadensmeldungen, liegengebliebene Todos) reduziert. Behebe diese Fehlstände im <span class="text-gray-900 dark:text-white underline cursor-pointer" @click="activeTab = 'operative'">Operativen Status</span>, um die 100 Punkte wieder zu erreichen.</p>
                             </div>
                         </div>
                    </div>
                @endif

                <div class="flex flex-col gap-8 w-full">

                    <!-- KACHEL 1: FINANZIELLE GESUNDHEIT -->
                    <div :class="focusMode ? 'bg-transparent border-0 shadow-none p-0' : 'bg-white/80 dark:bg-gray-900/60 border border-gray-200 dark:border-gray-800 rounded-3xl p-6 lg:p-7 shadow-sm'"
                         class="flex flex-col justify-between gap-6">
                        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 border-b border-gray-200 dark:border-gray-800 pb-5">
                            <div>
                                <h2 class="text-2xl font-serif font-bold text-gray-900 dark:text-white mb-1">Finanzielle Gesundheit</h2>
                                <p class="text-xs font-bold text-gray-500 leading-relaxed max-w-md">Gesicherter Statuswert aus Liquiditätsreserven, Break-Even, Rentabilität und aktuellen Markttrends.</p>
                            </div>
                            <div class="flex items-center gap-4 shrink-0">
                                <div class="text-right">
                                    <span class="text-[10px] font-black uppercase tracking-widest text-gray-500 block mb-1">Monatsgewinn (Ø)</span>
                                    <div class="flex items-center justify-end gap-2">
                                        <span class="text-2xl font-black text-gray-900 dark:text-white tracking-tighter">{{ number_format($stats['avg_profit'] ?? 0, 0, ',', '.') }} €</span>
                                        @include('livewire.shop.master.master-analytics-partials.profit-breakdown-popover', ['positionClass' => 'bottom-full right-0 mb-3'])
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <!-- Säule 1: WACHSTUM -->
                            <div class="bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800/80 rounded-[1.5rem] p-4 shadow-inner flex flex-col gap-4">
                                <div class="flex items-center justify-between border-b border-gray-200 dark:border-gray-800/80 pb-2.5">
                                    <div class="flex items-center gap-2">
                                        <x-heroicon-o-arrow-trending-up class="w-4 h-4 text-blue-500" />
                                        <h3 class="text-xs font-semibold text-gray-900 dark:text-white uppercase tracking-widest">Wachstum</h3>
                                    </div>
                                    @include('components.alerts.info-tooltip', ['key' => 'pillar_growth'])
                                </div>
                                <div class="flex flex-col gap-2.5">
                                    <div class="flex justify-between items-center group">
                                        <span class="text-[9px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-widest flex items-center gap-1.5">
                                            <x-heroicon-o-banknotes class="w-3 h-3 text-gray-500" /> Umsatzrahmen
                                            @include('components.alerts.info-tooltip', ['key' => 'shop_rev'])
                                        </span>
                                        <span class="text-sm font-black text-gray-800 dark:text-gray-200 group-hover:text-gray-900 dark:group-hover:text-white transition-colors">{{ number_format($stats['shop_revenue'] ?? 0, 0, ',', '.') }} €</span>
                                    </div>
                                    <div class="w-full h-px bg-gray-200 dark:bg-gray-800/50"></div>
                                    <div class="flex justify-between items-center group">
                                        <span class="text-[9px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-widest flex items-center gap-1.5">
                                            <x-heroicon-o-chart-bar class="w-3 h-3 text-gray-500" /> Trend
                                            @include('components.alerts.info-tooltip', ['key' => 'trend'])
                                        </span>
                                        <span class="text-sm font-black {{ ($stats['revenue_growth'] ?? 0) >= 0 ? 'text-emerald-500' : 'text-red-500' }} group-hover:dark:drop-shadow-[0_0_8px_currentColor] transition-all">{{ ($stats['revenue_growth'] ?? 0) > 0 ? '+' : '' }}{{ $stats['revenue_growth'] ?? 0 }} %</span>
                                    </div>
                                    <div class="w-full h-px bg-gray-200 dark:bg-gray-800/50"></div>
                                    <div class="flex justify-between items-center group">
                                        <span class="text-[9px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-widest flex items-center gap-1.5">
                                            <x-heroicon-o-calendar-days class="w-3 h-3 text-gray-500" /> Jahr
                                            @include('components.alerts.info-tooltip', ['key' => 'projected_year'])
                                        </span>
                                        <span class="text-sm font-black text-blue-600 dark:text-blue-400 group-hover:text-blue-500 dark:group-hover:text-blue-300 transition-colors">{{ number_format($stats['projected_year'] ?? 0, 0, ',', '.') }} €</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Säule 2: EFFIZIENZ -->
                            <div class="bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800/80 rounded-[1.5rem] p-4 shadow-inner flex flex-col gap-4">
                                <div class="flex items-center justify-between border-b border-gray-200 dark:border-gray-800/80 pb-2.5">
                                    <div class="flex items-center gap-2">
                                        <x-heroicon-o-scale class="w-4 h-4 text-emerald-500" />
                                        <h3 class="text-xs font-semibold text-gray-900 dark:text-white uppercase tracking-widest">Rentabilität</h3>
                                    </div>
                                    @include('components.alerts.info-tooltip', ['key' => 'pillar_profitability'])
                                </div>
                                <div class="flex flex-col gap-2.5">
                                    <div class="flex justify-between items-center group">
                                        <span class="text-[9px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-widest flex items-center gap-1.5">
                                            <x-heroicon-o-receipt-percent class="w-3 h-3 text-gray-500" /> Nettomarge
                                            @include('components.alerts.info-tooltip', ['key' => 'margin'])
                                        </span>
                                        <span class="text-sm font-black text-emerald-500 group-hover:text-emerald-400 transition-colors">{{ $stats['margin'] ?? 0 }} %</span>
                                    </div>
                                    <div class="w-full h-px bg-gray-200 dark:bg-gray-800/50"></div>
                                    <div class="flex justify-between items-center group">
                                        <span class="text-[9px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-widest flex items-center gap-1.5">
                                            <x-heroicon-o-currency-dollar class="w-3 h-3 text-gray-500" /> Mtl. Gewinn
                                            @include('livewire.shop.master.master-analytics-partials.profit-breakdown-popover', ['positionClass' => 'bottom-full left-0 mb-3'])
                                        </span>
                                        <span class="text-sm font-black text-gray-800 dark:text-gray-200 group-hover:text-gray-900 dark:group-hover:text-white transition-colors">{{ number_format($stats['avg_profit'] ?? 0, 0, ',', '.') }} €</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Säule 3: SICHERHEIT -->
                            <div class="bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800/80 rounded-[1.5rem] p-4 shadow-inner flex flex-col gap-4" x-data="{ showCosts: false }">
                                <div class="flex items-center justify-between border-b border-gray-200 dark:border-gray-800/80 pb-2.5">
                                    <div class="flex items-center gap-2">
                                        <x-heroicon-o-shield-check class="w-4 h-4 text-amber-500" />
                                        <h3 class="text-xs font-semibold text-gray-900 dark:text-white uppercase tracking-widest">Sicherheit</h3>
                                    </div>
                                    @include('components.alerts.info-tooltip', ['key' => 'pillar_security'])
                                </div>
                                <div class="flex flex-col gap-2.5">
                                    <div class="flex justify-between items-center group">
                                        <span class="text-[9px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-widest flex items-center gap-1.5">
                                            <x-heroicon-o-flag class="w-3 h-3 text-gray-500" /> Break-Even
                                            @include('components.alerts.info-tooltip', ['key' => 'break_even_monthly'])
                                        </span>
                                        <span class="text-sm font-black text-amber-500 group-hover:text-amber-400 transition-colors">{{ number_format($stats['break_even_monthly'] ?? 0, 0, ',', '.') }} €</span>
                                    </div>
                                    <div class="w-full h-px bg-gray-200 dark:bg-gray-800/50"></div>

                                    @php
                                        $hasPending = ($stats['pending_invoices']['sum'] ?? 0) > 0;
                                        $pendingCount = $stats['pending_invoices']['count'] ?? 0;
                                    @endphp
                                    <div class="flex justify-between items-center group {{ $hasPending ? 'bg-red-500/10 dark:bg-red-950/20 -mx-1 px-1.5 py-0.5 rounded-lg border border-red-500/20' : '' }}">
                                        <span class="text-[9px] font-semibold uppercase tracking-widest flex items-center gap-1.5 {{ $hasPending ? 'text-red-500 dark:text-red-400' : 'text-gray-500 dark:text-gray-400' }}">
                                            <x-heroicon-o-document-magnifying-glass class="w-3 h-3 {{ $hasPending ? 'text-red-500' : 'text-gray-500' }}" /> Offen
                                            @include('components.alerts.info-tooltip', ['key' => 'pending_invoices'])
                                        </span>
                                        <div class="text-right">
                                            <span class="text-sm font-black {{ $hasPending ? 'text-red-500 animate-pulse' : 'text-gray-500' }}">{{ number_format($stats['pending_invoices']['sum'] ?? 0, 0, ',', '.') }} €</span>
                                        </div>
                                    </div>

                                    <div class="w-full h-px bg-gray-200 dark:bg-gray-800/50"></div>

                                    <div>
                                        <button @click="showCosts = !showCosts" class="w-full flex justify-between items-center text-[9px] font-bold uppercase tracking-widest text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white transition-colors bg-gray-100 dark:bg-gray-900 px-2.5 py-1.5 rounded-lg border border-gray-200 dark:border-gray-800 hover:border-gray-300 dark:hover:border-gray-700">
                                            <span>Kostenstruktur</span>
                                            <x-heroicon-m-chevron-down class="w-3 h-3 transition-transform" ::class="showCosts ? 'rotate-180' : ''" />
                                        </button>
                                        <div x-show="showCosts" x-cloak x-collapse class="space-y-1.5 mt-2 pt-1 border-t border-gray-200 dark:border-gray-800/50">
                                            <div class="flex justify-between items-center"><span class="text-[8px] font-semibold text-gray-500 uppercase tracking-widest">Einnahmen Fix</span><span class="text-[11px] font-black text-emerald-500/80">{{ number_format($stats['fixed_income_total'] ?? 0, 0, ',', '.') }} €</span></div>
                                            <div class="flex justify-between items-center"><span class="text-[8px] font-semibold text-gray-500 uppercase tracking-widest">Kosten Privat</span><span class="text-[11px] font-black text-purple-500 dark:text-purple-400/80">{{ number_format($stats['fixed_expenses_priv'] ?? 0, 0, ',', '.') }} €</span></div>
                                            <div class="flex justify-between items-center"><span class="text-[8px] font-semibold text-gray-500 uppercase tracking-widest">Kosten Gewerbe</span><span class="text-[11px] font-black text-blue-500 dark:text-blue-400/80">{{ number_format($stats['fixed_expenses_gew'] ?? 0, 0, ',', '.') }} €</span></div>
                                            <div class="flex justify-between items-center"><span class="text-[8px] font-semibold text-gray-500 uppercase tracking-widest">Sonderkosten</span><span class="text-[11px] font-black text-amber-500/80">{{ number_format($stats['variable_expenses'] ?? 0, 0, ',', '.') }} €</span></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- KACHEL 2: 10-JAHRES FIRMENREISE & MEILENSTEINE (Gleiches Design!) -->
                    <div :class="focusMode ? 'bg-transparent border-0 shadow-none p-0' : 'bg-white/80 dark:bg-gray-900/60 border border-gray-200 dark:border-gray-800 rounded-3xl p-6 lg:p-7 shadow-sm'"
                         class="flex flex-col justify-between">
                        @include('livewire.shop.master.master-analytics-partials.company-journey-tile')
                    </div>

                </div>

            </div>

            <!-- 2. OPERATIVE DETAILS -->
            <div x-show="activeTab === 'operative'" x-cloak x-transition.opacity.duration.300ms x-data="{ showAllOperative: false }">
                <div class="flex justify-between items-end border-b border-gray-200 dark:border-gray-800 pb-6 mb-8">
                    <div>
                        <h2 class="text-2xl font-serif font-bold text-gray-900 dark:text-white mb-1">To-do Liste & Operatives</h2>
                        <p class="text-xs font-bold text-gray-500 leading-relaxed max-w-md">Aktuelle Aufgaben aus dem Tagesgeschäft, Buchhaltung und Service.</p>
                    </div>
                    <button type="button" @click="showAllOperative = !showAllOperative" :class="showAllOperative ? 'bg-[#C5A059]/20 text-[#C5A059] border-[#C5A059]/50' : 'bg-gray-100 dark:bg-gray-900 border-gray-200 dark:border-gray-700 text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:border-gray-400 dark:hover:border-gray-500'" class="w-10 h-10 rounded-full border flex items-center justify-center transition-colors dark:shadow-lg" title="Ansicht umschalten (Nur offene / Alle zeigen)">
                        <x-heroicon-o-funnel class="w-5 h-5" />
                    </button>
                </div>

                @php
                    $descriptions = [
                        'inventory' => 'Prüft, ob physische Artikel den Mindestbestand unterschritten haben und nachbestellt werden müssen.',
                        'special_issues' => 'Zeigt Sonderausgaben, für die noch kein Beleg oder keine Rechnung hochgeladen wurde.',
                        'leitung/contracts' => 'Listet Fixkosten-Positionen auf, für die noch kein Vertragsdokument hinterlegt ist.',
                        'open_orders' => 'Bestellungen, die bezahlt, aber noch nicht komplett versendet / abgeschlossen sind.',
                        'open_tickets' => 'Kunden-Tickets aus dem Support, die noch nicht auf "erledigt" gesetzt wurden.',
                        'open_chats' => 'Live-Chat Anfragen von Kunden, die auf eine Antwort vom System warten.',
                        'open_contact_requests' => 'Eingegangene Anfragen über das Kontaktformular, die unbearbeitet sind.',
                        'open_mails' => 'Ungelesene E-Mails im Posteingang, die Aufmerksamkeit erfordern.',
                        'product_reviews' => 'Von Kunden eingereichte Produktbewertungen, die auf manuelle Freigabe warten.',
                        'open_credits' => 'Erstellte Gutschriften oder Stornos, die noch nicht per E-Mail an den Kunden verschickt wurden.',
                        'influencer_payouts' => 'Offene Creator-Provisionen und angeforderte Gutschriften (§ 14 UStG), die unter /admin/marketing/influencer geprüft oder überwiesen werden müssen.',
                        'employee_payroll' => 'Offene Lohn- und Gehaltsabrechnungen deiner Mitarbeiter, die unter /admin/employees (Leitung -> Mitarbeiter) per SEPA überwiesen werden müssen.',
                        'unassigned_tx' => 'Banktransaktionen, die noch keinem Auftrag und keiner Rechnung zugeordnet wurden.',
                        'open_tasks' => 'Allgemeine, systemübergreifende To-dos, die von Mitarbeitern noch offen sind.',
                        'routine' => 'Täglicher Check-in für deine Tagesroutine (Mo–Fr). Sobald du heute deine erledigten Routinen abgehakt hast, gilt der Tag als eingecheckt; vergangene Tage verfallen automatisch um Mitternacht.',
                        'open_quotes' => 'Kunden-Anfragen für individuelle Spezial-Angebote, die noch nicht kalkuliert wurden.',
                        'open_revocations' => 'Kunden-Widerrufe, die noch nicht final bearbeitet oder gutgeschrieben wurden.',
                        'open_losses' => 'Transport-Schäden oder Bruch auf dem Transportweg, die auf Rückerstattung oder Nachsendung warten.',
                        'open_blog_posts' => 'Artikel, die sich momentan im Entwurf befinden oder auf ihre Veröffentlichung warten.',
                        'open_abandoned_carts' => 'Kunden haben ihren Einkaufsprozess abgebrochen. Überprüfe die Warenkörbe und sende ggf. eine Erinnerung.',
                        'system_logs' => 'Offene System-Fehler und Warnungen, die im Hintergrund-Prozess protokolliert wurden.',
                    ];

                    $actionNeeded = [];
                    $done = [];

                    foreach($healthChecks as $key => $check) {
                        $check['description'] = $descriptions[$key] ?? 'Systemhinweis zu ' . $check['title'];
                        if ($check['count'] > 0) {
                            $actionNeeded[$key] = $check;
                        } else {
                            $done[$key] = $check;
                        }
                    }
                @endphp

                <table class="w-full text-left border-separate border-spacing-y-2">
                    <thead>
                        <tr>
                            <th class="pb-2 pl-4 text-[10px] font-bold uppercase tracking-widest text-gray-500">Kategorie / Aufgabe</th>
                            <th class="pb-2 pr-4 text-[10px] font-bold uppercase tracking-widest text-gray-500 text-right">To-Do Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach(array_merge($actionNeeded, $done) as $key => $check)
                            @php
                                $actionUrl = match($key) {
                                    'inventory' => '/admin/products',
                                    'special_issues' => '/admin/financial-variable-costs',
                                    'leitung/contracts' => '/admin/financial-fix-costs',
                                    'open_orders' => '/admin/orders',
                                    'open_tickets' => '/admin/support-tickets',
                                    'open_chats' => '/admin/support-chats',
                                    'open_contact_requests' => '/admin/support-contact-form',
                                    'open_mails' => '/admin/inbox',
                                    'product_reviews' => '/admin/reviews',
                                    'open_credits' => '/admin/credit-management',
                                    'influencer_payouts' => '/admin/marketing/influencer',
                                    'employee_payroll' => '/admin/employees',
                                    'unassigned_tx' => '/admin/financial-banks',
                                    'open_tasks' => '/admin/tasks',
                                    'routine' => '/admin/routine',
                                    'open_quotes' => '/admin/quote-requests',
                                    'open_revocations' => '/admin/widerruf',
                                    'open_losses' => '/admin/product-fracture',
                                    'open_blog_posts' => '/admin/blog',
                                    'open_abandoned_carts' => '/admin/shopping-carts',
                                    'system_logs' => '/admin/global-logs',
                                    default => '/admin/dashboard'
                                };

                                $statusClass = match($check['status'] ?? 'error') {
                                    'success' => 'text-emerald-500 group-hover:text-emerald-400',
                                    'todo' => 'text-blue-500 group-hover:text-blue-400',
                                    'warning' => 'text-amber-500 group-hover:text-amber-400',
                                    default => 'text-red-500 group-hover:text-red-400',
                                };

                                $needsAction = $check['count'] > 0;
                            @endphp

                            <tr @if(!$needsAction) x-show="showAllOperative" x-transition.opacity @endif
                                class="bg-gray-50/80 dark:bg-gray-950 hover:bg-gray-100/90 dark:hover:bg-gray-900/80 transition-all group cursor-pointer relative"
                                onclick="window.open('{{ $actionUrl }}', '_blank')">

                                <td class="py-4 pl-4 rounded-l-xl border-y border-l border-gray-200 dark:border-gray-800/80 group-hover:border-gray-300 dark:group-hover:border-gray-700 relative w-full">
                                    <div class="flex items-center gap-4">
                                        <div class="shrink-0 transition-colors {{ $statusClass }}">
                                            <x-dynamic-component :component="'heroicon-o-' . $check['icon']" class="w-6 h-6" />
                                        </div>
                                        <div>
                                            <div class="flex items-center gap-2">
                                                <h4 class="text-sm font-semibold text-gray-800 dark:text-gray-300 group-hover:text-gray-950 dark:group-hover:text-white transition-colors">{{ $check['title'] }}</h4>

                                                <div class="relative" x-data="{ tooltip: false }" @mouseenter="tooltip = true" @mouseleave="tooltip = false" @click.stop>
                                                    <x-heroicon-m-information-circle class="w-4 h-4 text-gray-400 dark:text-gray-600 hover:text-[#C5A059] transition-colors" />

                                                    <!-- Tooltip -->
                                                    <div x-show="tooltip" x-transition.opacity.duration.200ms class="absolute bottom-full mb-2 left-1/2 -translate-x-1/2 w-[280px] p-4 bg-white dark:bg-gray-950 border border-[#C5A059]/40 rounded-xl shadow-2xl z-[100] text-center" style="display: none;">
                                                        <div class="text-[11.5px] text-gray-700 dark:text-gray-300 font-medium leading-relaxed">{{ $check['description'] }}</div>
                                                        <div class="absolute -bottom-1.5 left-1/2 -translate-x-1/2 w-3 h-3 bg-white dark:bg-gray-950 border-b border-r border-[#C5A059]/40 rotate-45"></div>
                                                    </div>
                                                </div>
                                            </div>
                                            <p class="text-xs text-gray-500 mt-0.5">{{ $check['message'] }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-4 pr-4 rounded-r-xl border-y border-r border-gray-200 dark:border-gray-800/80 group-hover:border-gray-300 dark:group-hover:border-gray-700 text-right whitespace-nowrap">
                                    @if($needsAction)
                                        <div class="inline-flex items-center gap-2 bg-gray-100 dark:bg-gray-900/50 border border-gray-200 dark:border-gray-800 px-3 py-1.5 rounded-lg">
                                            <div class="w-1.5 h-1.5 rounded-full bg-red-500 animate-pulse"></div>
                                            <span class="text-xs font-bold text-gray-800 dark:text-gray-300">{{ $check['count'] }}</span>
                                            <x-heroicon-m-chevron-right class="w-4 h-4 text-gray-400 dark:text-gray-600 group-hover:text-[#C5A059] transition-colors ml-2" />
                                        </div>
                                    @else
                                        <div class="inline-flex items-center gap-2 px-3 py-1.5">
                                            <x-heroicon-m-check-circle class="w-5 h-5 text-emerald-500/60 dark:text-emerald-500/50" />
                                            <span class="text-xs font-bold text-gray-500">Erledigt</span>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <div x-show="!showAllOperative && {{ count($actionNeeded) }} === 0" class="py-12 text-center border border-dashed border-gray-200 dark:border-gray-800 rounded-2xl">
                    <div class="w-16 h-16 bg-emerald-500/10 rounded-full flex items-center justify-center mx-auto mb-4">
                        <x-heroicon-o-check-badge class="w-8 h-8 text-emerald-500" />
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-1">Alles erledigt!</h3>
                    <p class="text-sm text-gray-500 max-w-sm mx-auto">Es gibt aktuell keine systemseitigen Aufgaben oder Probleme, die deiner Aufmerksamkeit bedürfen.</p>
                </div>
            </div>

            <!-- 3. SYSTEM DETAILS -->
            <div x-show="activeTab === 'system'" x-cloak x-transition.opacity.duration.300ms wire:init="checkSystemHealth">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 border-b border-gray-200 dark:border-gray-800 pb-6 mb-8">
                    <div>
                        <h2 class="text-2xl font-serif font-bold text-gray-900 dark:text-white mb-1">System & Infrastruktur</h2>
                        <p class="text-xs font-bold text-gray-500 leading-relaxed max-w-md">Latenzen, API Verbindungen und Hintergrund-Jobs zur Serverintegrität.</p>
                    </div>
                    <button type="button" wire:click="fixSystem" wire:loading.attr="disabled" class="px-5 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-widest text-gray-900 bg-primary hover:bg-primary-dark hover:text-white shadow-glow flex items-center gap-2">
                        <span wire:loading.remove wire:target="fixSystem">Fix System Starten</span>
                        <span wire:loading wire:target="fixSystem" class="animate-pulse">Arbeite...</span>
                    </button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5 lg:gap-8">
                    @foreach($systemGroups as $groupName => $groupInfo)
                        <div class="bg-gray-50/60 dark:bg-gray-950/40 rounded-[2rem] border border-gray-200 dark:border-gray-800 flex flex-col p-6 shadow-inner gap-5">
                            <div class="flex items-center gap-3 border-b border-gray-200 dark:border-gray-800 pb-3">
                                <div class="w-1.5 h-4 rounded-full {{ $groupInfo['color'] }}"></div>
                                <h5 class="text-xs font-black text-gray-700 dark:text-gray-300 uppercase tracking-widest">{{ $groupName }}</h5>
                            </div>

                            <div class="flex flex-col gap-4">
                                @foreach($groupInfo['items'] as $sKey)
                                    @if($sKey === 'ws')
                                        <div x-data="{ 
                                            wsStatus: 'checking', 
                                            tooltip: false,
                                            wsHost: 'Lade...',
                                            wsPort: 'Lade...',
                                            checkConnection() { 
                                                if(window.Echo && window.Echo.connector && window.Echo.connector.pusher) { 
                                                    this.wsStatus = window.Echo.connector.pusher.connection.state;
                                                    
                                                    // Wir lesen die ECHTEN, aktiv genutzten Werte direkt aus dem laufenden Echo-Client aus
                                                    this.wsHost = window.Echo.connector.pusher.config.wsHost || 'Unbekannt';
                                                    this.wsPort = window.Echo.connector.pusher.config.wsPort || 'Unbekannt';

                                                    window.Echo.connector.pusher.connection.bind('state_change', (states) => {
                                                        this.wsStatus = states.current;
                                                    });
                                                } else { 
                                                    this.wsStatus = 'unavailable'; 
                                                } 
                                            } 
                                        }" x-init="checkConnection()" @mouseenter="tooltip = true" @mouseleave="tooltip = false" class="relative cursor-help">
                                            <div class="flex items-center gap-3">
                                                <span class="relative w-2 h-2 rounded-full shrink-0" :class="{'bg-emerald-500 dark:shadow-[0_0_8px_rgba(16,185,129,0.8)]': wsStatus === 'connected', 'bg-red-500 dark:shadow-[0_0_8px_rgba(239,68,68,0.8)]': wsStatus === 'unavailable', 'bg-gray-400 dark:bg-gray-500': wsStatus === 'checking'}">
                                                </span>
                                                <div class="flex-1 flex justify-between items-center text-[10px] uppercase tracking-widest font-black">
                                                    <span class="text-gray-500 dark:text-gray-400">{{ $services['ws']['label'] }}</span>
                                                    <span :class="{'text-emerald-500 dark:text-emerald-400': wsStatus === 'connected', 'text-red-500 dark:text-red-400': wsStatus === 'unavailable', 'text-gray-500': wsStatus === 'checking'}" x-text="wsStatus"></span>
                                                </div>
                                            </div>

                                            <!-- Tooltip für WebSocket -->
                                            <div x-show="tooltip" x-transition.opacity.duration.200ms class="absolute bottom-full mb-2 right-0 w-[240px] sm:w-[280px] p-4 bg-white dark:bg-gray-950 border border-[#C5A059]/40 rounded-xl shadow-2xl z-[100] text-center" style="display: none;">
                                                
                                                @php
                                                    $correctWsHost = app()->environment('local') ? '127.0.0.1' : 'ws.mein-seelenfunke.de';
                                                    $correctWsPort = app()->environment('local') ? '6001' : '443';
                                                @endphp

                                                <div class="flex flex-col gap-2 text-[9px] text-left font-mono text-gray-600 dark:text-gray-400">
                                                    <div class="flex justify-between gap-4">
                                                        <span class="font-bold text-gray-500">REALER JS HOST:</span>
                                                        <span class="truncate" :class="wsHost === '{{ $correctWsHost }}' || wsHost === 'localhost' ? 'text-emerald-500 dark:text-emerald-400 font-bold' : 'text-red-500 dark:text-red-400 font-black'" x-text="wsHost"></span>
                                                    </div>
                                                    <div class="flex justify-between gap-4">
                                                        <span class="font-bold text-gray-500">REALER JS PORT:</span>
                                                        <span :class="wsPort == '{{ $correctWsPort }}' ? 'text-emerald-500 dark:text-emerald-400 font-bold' : 'text-red-500 dark:text-red-400 font-black'" x-text="wsPort"></span>
                                                    </div>
                                                    <div class="flex justify-between gap-4">
                                                        <span class="font-bold text-gray-500 opacity-60">SOLL-WERT:</span>
                                                        <span class="text-gray-500 dark:text-gray-600">{{ $correctWsHost }} : {{ $correctWsPort }}</span>
                                                    </div>

                                                    <div class="border-t border-gray-200 dark:border-gray-800 my-1"></div>
                                                    
                                                    <div class="flex flex-col gap-1 text-left font-mono my-2 bg-emerald-500/10 dark:bg-emerald-900/20 p-2 rounded-lg border border-emerald-500/30 dark:border-emerald-800/50">
                                                        <span class="text-[9px] font-bold text-emerald-600 dark:text-emerald-500 uppercase tracking-widest mb-1"><i class="bi bi-robot"></i> Intelligentes Routing aktiv:</span>
                                                        <div class="text-[9px] text-emerald-700 dark:text-emerald-400/80 leading-relaxed">Das JS-Frontend erkennt die Umgebung dynamisch anhand der Browser-URL. Es sind <b>keine</b> manuellen `.env`-Wechsel vor dem lokalen Kompilieren mehr nötig!</div>
                                                    </div>

                                                    <div class="border-t border-gray-200 dark:border-gray-800 my-1"></div>
                                                    
                                                    <div class="flex flex-col gap-1 text-left font-mono my-2 bg-gray-100 dark:bg-gray-900/50 p-2 rounded-lg border border-gray-200 dark:border-gray-800">
                                                        <span class="text-[9px] font-bold text-[#C5A059] uppercase tracking-widest mb-1"><i class="bi bi-hdd-network"></i> Backend Status:</span>
                                                        <div class="flex justify-between gap-4 text-[9px] truncate">
                                                            <span class="text-gray-500 font-bold shrink-0">Server Ping:</span>
                                                            <span class="{{ ($systemHealth['ws']['status'] ?? '') === 'connected' ? 'text-emerald-500 dark:text-emerald-400' : 'text-red-500 dark:text-red-400' }} font-black">
                                                                {{ $systemHealth['ws']['value'] ?? 'Unbekannt' }}
                                                            </span>
                                                        </div>
                                                        @if(($systemHealth['ws']['status'] ?? '') !== 'connected')
                                                             <div class="text-[9px] text-red-500 dark:text-red-400 mt-1 whitespace-normal leading-tight">
                                                                {{ $systemHealth['ws']['error'] ?? '' }}
                                                            </div>
                                                        @endif
                                                    </div>

                                                    <div class="border-t border-gray-200 dark:border-gray-800 my-1"></div>

                                                    <div x-show="wsStatus === 'disconnected'" class="text-red-500 dark:text-red-400 font-sans font-bold leading-relaxed text-center">Fehler: Der WebSocket-Server antwortet nicht.</div>
                                                    <div x-show="wsStatus === 'unavailable'" class="text-red-500 dark:text-red-400 font-sans font-bold leading-relaxed text-center">Fehler: Laravel Echo konnte (im Browser) nicht initialisiert werden.</div>
                                                    <div x-show="wsStatus === 'connected'" class="text-emerald-600 dark:text-emerald-400 font-sans font-bold leading-relaxed flex items-center justify-center gap-1.5"><i class="bi bi-shield-check"></i> System läuft zu 100% stabil.</div>
                                                </div>

                                                <div class="mt-3">
                                                    <button type="button" wire:click="fixSystem('ws')" wire:loading.attr="disabled" class="w-full px-2 py-1.5 rounded-lg border border-primary/50 bg-primary/10 hover:bg-primary/30 text-primary hover:text-white transition-colors text-[9px] font-black uppercase tracking-widest text-center shadow-inner cursor-pointer flex items-center justify-center gap-1.5">
                                                        <span wire:loading.remove wire:target="fixSystem('ws')"><i class="bi bi-braces-asterisk"></i> JS Frontend neu bauen</span>
                                                        <span wire:loading wire:target="fixSystem('ws')" class="animate-pulse">Baut JS... (kann dauern)</span>
                                                    </button>
                                                </div>

                                                <div class="absolute -bottom-1.5 right-4 w-3 h-3 bg-white dark:bg-gray-950 border-b border-r border-[#C5A059]/40 rotate-45"></div>
                                            </div>
                                        </div>
                                    @else
                                        @php
                                            $health = $systemHealth[$sKey] ?? null;
                                            $status = $health ? $health['status'] : 'checking';
                                            $msg = $health ? $health['value'] : 'Prüfe...';
                                            $dotColor = match($status) { 'connected' => 'bg-emerald-500 dark:shadow-[0_0_8px_rgba(16,185,129,0.8)]', 'warning' => 'bg-amber-500 dark:shadow-[0_0_8px_rgba(245,158,11,0.8)]', 'error', 'unavailable' => 'bg-red-500 dark:shadow-[0_0_8px_rgba(239,68,68,0.8)]', default => 'bg-gray-400 dark:bg-gray-500' };
                                            $textColor = match($status) { 'connected' => 'text-emerald-500 dark:text-emerald-400', 'warning' => 'text-amber-500 dark:text-amber-400', 'error', 'unavailable' => 'text-red-500 dark:text-red-400', default => 'text-gray-500' };
                                        @endphp
                                        <div class="relative cursor-help" x-data="{ tooltip: false }" @mouseenter="tooltip = true" @mouseleave="tooltip = false">
                                            <div class="flex items-center gap-3">
                                                <span class="relative w-2 h-2 rounded-full shrink-0 {{ $dotColor }}">
                                                    @if($status === 'connected' || $status === 'warning') <span class="absolute inset-0 rounded-full animate-ping opacity-50 {{ $dotColor }}"></span> @endif
                                                </span>
                                                <div class="flex-1 flex justify-between items-center text-[10px] uppercase tracking-widest font-black">
                                                    <span class="text-gray-500 dark:text-gray-400">{{ $services[$sKey]['label'] }}</span>
                                                    <span class="{{ $textColor }} text-right">{{ $msg }}</span>
                                                </div>
                                            </div>

                                            <!-- Tooltip für Services -->
                                            <div x-show="tooltip" x-transition.opacity.duration.200ms class="absolute bottom-full mb-2 right-0 w-[240px] p-3 bg-white dark:bg-gray-950 border border-[#C5A059]/40 rounded-xl shadow-2xl z-[100] text-center" style="display: none;">
                                                <div class="text-[10px] text-[#C5A059] font-black uppercase tracking-widest mb-1">{{ $services[$sKey]['host'] }}:{{ $services[$sKey]['port'] }}</div>
                                                <div class="text-[11px] text-gray-700 dark:text-gray-300 font-medium leading-relaxed">{{ $services[$sKey]['desc'] }}</div>
                                                
                                                @if($sKey === 'queue' && $health)
                                                    <div class="flex justify-between gap-4 mt-2 bg-gray-100 dark:bg-gray-900/50 p-2 rounded-lg border border-gray-200 dark:border-gray-800 text-left">
                                                        <span class="font-bold text-[9px] text-gray-500">WARTEND: <span class="text-gray-900 dark:text-white">{{ $health['pending'] ?? 0 }} Jobs</span></span>
                                                        <span class="font-bold text-[9px] text-gray-500">FEHLER: <span class="{{ ($health['failed'] ?? 0) > 0 ? 'text-red-500 dark:text-red-400' : 'text-emerald-500 dark:text-emerald-400' }}">{{ $health['failed'] ?? 0 }} Jobs</span></span>
                                                    </div>
                                                    @if(($health['failed'] ?? 0) > 0)
                                                        <button type="button" wire:click="flushFailedJobs" class="w-full mt-2 px-2 py-1.5 rounded-lg border border-red-500/50 dark:border-red-700 bg-red-500/10 dark:bg-red-900/30 hover:bg-red-500/20 dark:hover:bg-red-800 text-red-600 dark:text-red-300 hover:text-red-700 dark:hover:text-white transition-colors text-[9px] font-black uppercase tracking-widest text-center shadow-inner cursor-pointer">
                                                            Fehlgeschlagene Jobs final löschen
                                                        </button>
                                                    @endif
                                                @endif

                                                @if($sKey === 'scheduler')
                                                    <div class="border-t border-gray-200 dark:border-gray-800 my-2"></div>
                                                    
                                                    <!-- Cronjob Status -->
                                                    <div class="flex flex-col gap-1 text-left font-mono my-2 bg-gray-100 dark:bg-gray-900/50 p-2 rounded-lg border border-gray-200 dark:border-gray-800">
                                                        <span class="text-[9px] font-bold text-[#C5A059] uppercase tracking-widest mb-1"><i class="bi bi-clock-history"></i> Cronjob Status:</span>
                                                        <div class="flex justify-between gap-4 text-[9px] truncate">
                                                            <span class="text-gray-500 font-bold shrink-0">Heartbeat:</span>
                                                            <span class="{{ ($health['status'] ?? '') === 'connected' ? 'text-emerald-500 dark:text-emerald-400' : 'text-red-500 dark:text-red-400' }} font-black">
                                                                {{ $health['value'] ?? 'Unbekannt' }}
                                                            </span>
                                                        </div>
                                                        <div class="flex justify-between gap-4 text-[9px] truncate">
                                                            <span class="text-gray-500 font-bold shrink-0">Letzter Boot-Versuch:</span>
                                                            @if(isset($health['last_bootstrap_diff']))
                                                                <span class="{{ $health['last_bootstrap_diff'] < 10 ? 'text-emerald-500 dark:text-emerald-400' : 'text-amber-500' }} font-black">
                                                                    Vor {{ $health['last_bootstrap_diff'] }} Min
                                                                </span>
                                                            @else
                                                                <span class="text-red-500 dark:text-red-400 font-black">Bisher nie (Cache leer)</span>
                                                            @endif
                                                        </div>
                                                        <div class="flex justify-between gap-4 text-[9px] truncate">
                                                            <span class="text-gray-500 font-bold shrink-0">Herzschlag-Job (DB):</span>
                                                            @if(isset($health['heartbeat_present']) && $health['heartbeat_present'])
                                                                <span class="{{ (isset($health['heartbeat_active']) && $health['heartbeat_active']) ? 'text-emerald-500 dark:text-emerald-400' : 'text-amber-500' }} font-bold">
                                                                    {{ (isset($health['heartbeat_active']) && $health['heartbeat_active']) ? 'Aktiv' : 'Deaktiviert' }}
                                                                </span>
                                                            @else
                                                                <span class="text-red-500 dark:text-red-400 font-black">Fehlt in DB!</span>
                                                            @endif
                                                        </div>
                                                        <div class="flex justify-between gap-4 text-[9px] truncate">
                                                            <span class="text-gray-500 font-bold shrink-0">Artisan Datei:</span>
                                                            @if(isset($health['artisan_file_exists']) && $health['artisan_file_exists'])
                                                                <span class="{{ (isset($health['artisan_file_readable']) && $health['artisan_file_readable']) ? 'text-emerald-500 dark:text-emerald-400' : 'text-amber-500' }} font-bold">
                                                                    Vorhanden {{ (isset($health['artisan_file_readable']) && $health['artisan_file_readable']) ? '(Lesbar)' : '(Nicht lesbar!)' }}
                                                                </span>
                                                            @else
                                                                <span class="text-red-500 dark:text-red-400 font-black">Fehlt!</span>
                                                            @endif
                                                        </div>
                                                        @if(($health['status'] ?? '') !== 'connected')
                                                            <div class="text-[9px] text-red-500 dark:text-red-400 mt-1 whitespace-normal leading-tight">
                                                                {{ $health['error'] ?? 'Mittwald Cronjob läuft nicht oder hängt.' }}
                                                            </div>
                                                        @endif
                                                    </div>

                                                    <!-- Globaler Boot-Fehler -->
                                                    @if(isset($health['last_exception']) && $health['last_exception'])
                                                        <div class="flex flex-col gap-1 text-left font-mono my-2 bg-red-500/10 dark:bg-red-950/30 p-2 rounded-lg border border-red-500/30 dark:border-red-900/50">
                                                            <span class="text-[9px] font-bold text-red-600 dark:text-red-400 uppercase tracking-widest mb-1">
                                                                <i class="bi bi-exclamation-octagon-fill"></i> Globaler Boot-Fehler:
                                                            </span>
                                                            <div class="text-[9px] text-red-600 dark:text-red-300 font-semibold leading-normal whitespace-normal break-words">
                                                                {{ $health['last_exception']['message'] }}
                                                            </div>
                                                            <div class="text-[8px] text-gray-500 dark:text-gray-400 mt-1 truncate">
                                                                In {{ basename($health['last_exception']['file']) }}:{{ $health['last_exception']['line'] }}
                                                            </div>
                                                            <div class="text-[8px] text-gray-500">
                                                                {{ \Carbon\Carbon::parse($health['last_exception']['timestamp'])->diffForHumans() }}
                                                            </div>
                                                        </div>
                                                    @endif

                                                    <!-- Job-Registrierungsfehler -->
                                                    @if(isset($health['job_errors']) && count($health['job_errors']) > 0)
                                                        <div class="flex flex-col gap-1 text-left font-mono my-2 bg-amber-500/10 dark:bg-amber-950/20 p-2 rounded-lg border border-amber-500/30 dark:border-amber-900/50">
                                                            <span class="text-[9px] font-bold text-amber-600 dark:text-amber-400 uppercase tracking-widest mb-1">
                                                                <i class="bi bi-exclamation-triangle-fill"></i> Job-Registrierungsfehler ({{ count($health['job_errors']) }}):
                                                            </span>
                                                            <div class="space-y-1.5 max-h-[150px] overflow-y-auto pr-1">
                                                                @foreach($health['job_errors'] as $jobId => $jobError)
                                                                    <div class="text-[8px] border-b border-amber-500/20 dark:border-amber-900/30 pb-1 last:border-0 last:pb-0">
                                                                        <div class="text-gray-900 dark:text-white font-bold truncate">Job ID: {{ $jobId }}</div>
                                                                        <div class="text-amber-700 dark:text-amber-300 whitespace-normal leading-tight">
                                                                            {{ $jobError['message'] }}
                                                                        </div>
                                                                        <div class="text-gray-500 text-[7px] mt-0.5">
                                                                            Gemeldet: {{ $jobError['timestamp'] }}
                                                                        </div>
                                                                    </div>
                                                                @endforeach
                                                            </div>
                                                        </div>
                                                    @endif

                                                    <!-- Ungültige Präfix-Befehle -->
                                                    @if(isset($health['invalid_prefix_commands']) && count($health['invalid_prefix_commands']) > 0)
                                                        <div class="flex flex-col gap-1 text-left font-mono my-2 bg-amber-500/10 dark:bg-amber-950/30 p-2 rounded-lg border border-amber-500/30 dark:border-amber-900/50">
                                                            <span class="text-[9px] font-bold text-amber-600 dark:text-amber-400 uppercase tracking-widest mb-1">
                                                                <i class="bi bi-exclamation-triangle-fill"></i> Präfix-Fehler ({{ count($health['invalid_prefix_commands']) }}):
                                                            </span>
                                                            <div class="text-[8.5px] text-amber-700 dark:text-amber-300 leading-normal whitespace-normal">
                                                                Folgende Jobs enthalten das Präfix "php artisan", was im Scheduler scheitert. Klicke unten auf "reparieren & anstoßen" zum Bereinigen:
                                                            </div>
                                                            <div class="space-y-1 mt-1 max-h-[80px] overflow-y-auto pr-1">
                                                                @foreach($health['invalid_prefix_commands'] as $invCmd)
                                                                    <div class="text-[8px] text-gray-500 dark:text-gray-400 border-t border-amber-500/20 dark:border-amber-900/20 pt-1">
                                                                        <b>{{ $invCmd['name'] }}:</b> <span class="text-red-500 dark:text-red-400 font-mono break-all">{{ $invCmd['command'] }}</span>
                                                                    </div>
                                                                @endforeach
                                                            </div>
                                                        </div>
                                                    @endif

                                                    <!-- PHP CLI Versionen -->
                                                    @if(isset($health['cli_versions']) && count($health['cli_versions']) > 0)
                                                        <div class="flex flex-col gap-1 text-left font-mono my-2 bg-gray-100 dark:bg-gray-900/50 p-2 rounded-lg border border-gray-200 dark:border-gray-800">
                                                            <span class="text-[9px] font-bold text-gray-500 uppercase tracking-widest mb-1"><i class="bi bi-cpu"></i> PHP CLI Versionen:</span>
                                                            @foreach($health['cli_versions'] as $path => $diag)
                                                                <div class="flex justify-between gap-4 text-[9px] truncate">
                                                                    <span class="text-gray-500 shrink-0">{{ basename($path) }}:</span>
                                                                    <span class="{{ version_compare($diag['version'], '8.4.0', '>=') ? 'text-emerald-500 dark:text-emerald-400 font-bold' : 'text-amber-500' }}">
                                                                        {{ $diag['version'] }} (proc: {{ $diag['proc_open'] ? 'JA' : 'NEIN' }})
                                                                    </span>
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    @endif

                                                    <!-- Hängende Sperren -->
                                                    @if(isset($health['locked_count']))
                                                        <div class="flex flex-col gap-1 text-left font-mono my-2 bg-red-500/10 dark:bg-red-950/20 p-2 rounded-lg border border-red-500/30 dark:border-red-900/50">
                                                            <span class="text-[9px] font-bold text-red-600 dark:text-red-400 uppercase tracking-widest mb-1"><i class="bi bi-lock"></i> Hängende Sperren:</span>
                                                            <div class="flex justify-between gap-4 text-[9px]">
                                                                <span class="text-gray-500">Sperren aktiv:</span>
                                                                <span class="{{ $health['locked_count'] > 0 ? 'text-red-500 dark:text-red-400 font-bold animate-pulse' : 'text-emerald-500 dark:text-emerald-400' }}">
                                                                    {{ $health['locked_count'] }}
                                                                </span>
                                                            </div>
                                                            @if($health['locked_count'] > 0)
                                                                <div class="text-[8px] text-red-600/80 dark:text-red-400/80 leading-normal mt-1 whitespace-normal">
                                                                    Sperren blockieren den Cronjob. Beim manuellen Anstoßen werden sie automatisch bereinigt.
                                                                </div>
                                                            @endif
                                                        </div>
                                                    @endif
                                                    
                                                    <div class="flex flex-col gap-1 text-left font-mono my-2 bg-purple-500/10 dark:bg-purple-900/20 p-2 rounded-lg border border-purple-500/30 dark:border-purple-800/50">
                                                        <span class="text-[9px] font-bold text-purple-600 dark:text-purple-500 uppercase tracking-widest mb-1"><i class="bi bi-shield-lock"></i> Daemon-Sperre Aktiv:</span>
                                                        <div class="text-[9px] text-purple-700 dark:text-purple-400/80 leading-relaxed whitespace-normal">
                                                            Der interne Scheduler blockiert automatisch Befehle wie <code>reverb:start</code> oder <code>queue:work</code>, um Endlos-Hänger im Mittwald-Docker-Container zu verhindern.
                                                        </div>
                                                    </div>

                                                    <div class="mt-2">
                                                        <button type="button" wire:click="fixSystem('scheduler')" wire:loading.attr="disabled" class="w-full px-2 py-1.5 rounded-lg border border-primary/50 bg-primary/10 hover:bg-primary/30 text-primary hover:text-white transition-colors text-[9px] font-black uppercase tracking-widest text-center shadow-inner cursor-pointer flex items-center justify-center gap-1.5 pointer-events-auto">
                                                            <span wire:loading.remove wire:target="fixSystem('scheduler')"><i class="bi bi-play-circle"></i> Scheduler reparieren & anstoßen</span>
                                                            <span wire:loading wire:target="fixSystem('scheduler')" class="animate-pulse">Repariert...</span>
                                                        </button>
                                                    </div>
                                                    
                                                    <!-- Mittwald Setup Hilfe -->
                                                     <div class="flex flex-col gap-1 text-left font-mono my-2 bg-gray-100 dark:bg-gray-900/80 p-2.5 rounded-lg border border-gray-200 dark:border-gray-800">
                                                         <span class="text-[9px] font-bold text-gray-700 dark:text-gray-300 uppercase tracking-widest mb-1.5"><i class="bi bi-info-circle-fill text-primary"></i> Mittwald Cronjob Setup:</span>
                                                         <div class="flex flex-col gap-1 text-[8.5px] text-gray-500 dark:text-gray-400">
                                                             <div class="flex justify-between"><span class="font-bold">Intervall:</span><span class="text-gray-900 dark:text-white">* * * * * (Jede Minute)</span></div>
                                                             <div class="flex justify-between"><span class="font-bold">Typ:</span><span class="text-gray-900 dark:text-white">Befehl ausführen</span></div>
                                                             <div class="flex justify-between"><span class="font-bold">Interpreter:</span><span class="text-gray-900 dark:text-white">/usr/bin/php (PHP 8.4+)</span></div>
                                                             <div class="flex justify-between gap-2"><span class="font-bold shrink-0">Datei:</span><span class="text-emerald-500 dark:text-emerald-400 truncate">{{ base_path('artisan') }}</span></div>
                                                             <div class="flex justify-between"><span class="font-bold">Parameter:</span><span class="text-gray-900 dark:text-white">schedule:run</span></div>
                                                         </div>
                                                     </div>
                                                @endif

                                                @if($sKey === 'backup')
                                                    <button type="button" wire:click="fixSystem('backup')" wire:loading.attr="disabled" class="w-full mt-2 px-2 py-1.5 rounded-lg border border-purple-500/50 dark:border-purple-700 bg-purple-500/10 dark:bg-purple-900/30 hover:bg-purple-500/20 dark:hover:bg-purple-800 text-purple-600 dark:text-purple-300 hover:text-purple-700 dark:hover:text-white transition-colors text-[9px] font-black uppercase tracking-widest text-center shadow-inner cursor-pointer flex items-center justify-center gap-1.5">
                                                        <span wire:loading.remove wire:target="fixSystem('backup')"><i class="bi bi-clock-history"></i> Backup manuell anstoßen</span>
                                                        <span wire:loading wire:target="fixSystem('backup')" class="animate-pulse">Backup läuft...</span>
                                                    </button>
                                                @endif

                                                @if($sKey === 'telephony')
                                                    @if($status === 'connected')
                                                        <div class="mt-2 text-[10px] text-emerald-600 dark:text-emerald-400 bg-emerald-500/10 dark:bg-emerald-950/30 p-2 rounded-lg border border-emerald-500/30 dark:border-emerald-900/50 text-left font-medium leading-relaxed">
                                                            <i class="bi bi-check-circle-fill mr-1"></i> Perfekt! Die Node.js App ist wach, der Port ist offen und das Routing funktioniert. Du kannst jetzt anrufen!
                                                        </div>
                                                    @else
                                                        <div class="mt-2 text-[10px] text-gray-700 dark:text-gray-300 bg-red-500/10 dark:bg-red-950/30 p-2 rounded-lg border border-red-500/30 dark:border-red-900/50 text-left font-medium leading-relaxed">
                                                            <strong class="text-red-600 dark:text-red-400 block mb-2 uppercase tracking-wider"><i class="bi bi-exclamation-triangle-fill"></i> SSH Notfall-Befehl (Copy & Paste):</strong>
                                                            <div class="bg-black/10 dark:bg-black/50 rounded p-2 text-gray-700 dark:text-gray-300 font-mono text-[9.5px] leading-relaxed cursor-text selection:bg-purple-500/30 border border-red-500/30 dark:border-red-900/50 mb-2">
                                                                cd /html/seelenfunke-stage<br>
                                                                git reset --hard<br>
                                                                git pull<br>
                                                                cd /html/twilio-bridge<br>
                                                                cp ../seelenfunke-stage/server-twilio.js .<br>
                                                                cp ../seelenfunke-stage/.env .<br>
                                                                npm install ws dotenv wavefile<br>
                                                                kill -9 $(ps aux | grep '[n]ode' | awk '{print $2}')
                                                            </div>
                                                            <span class="text-gray-500 dark:text-gray-400 italic">Die Node.js App startet danach von alleine neu. Warte ca. 1-2 Minuten, bis die Anzeige grün wird!</span>
                                                        </div>
                                                    @endif
                                                @endif

                                                @if(isset($health['error']) && $health['error'])
                                                    <div class="mt-1.5 pt-1.5 border-t border-red-500/20 text-red-500 text-[10px] font-bold">{{ $health['error'] }}</div>
                                                @endif
                                                @if(isset($health['path']) && $health['path'])
                                                    <div class="mt-1.5 pt-1.5 border-t border-gray-200 dark:border-gray-800 text-gray-500 dark:text-gray-400 text-[9px] font-mono break-all">{{ $health['path'] }}</div>
                                                @endif
                                                <div class="absolute -bottom-1.5 right-4 w-3 h-3 bg-white dark:bg-gray-950 border-b border-r border-[#C5A059]/40 rotate-45"></div>
                                            </div>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- REPAIR LOG --}}
                @if(count($repairLogs) > 0)
                    <div class="mt-6 border border-gray-200 dark:border-gray-800 rounded-[1.5rem] bg-gray-50 dark:bg-gray-950 overflow-hidden shadow-inner flex flex-col">
                        <div class="bg-gray-100 dark:bg-gray-900 border-b border-gray-200 dark:border-gray-800 px-4 py-3 flex items-center justify-between">
                            <h4 class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-500 dark:text-gray-400">Reparatur-Log</h4>
                            <button type="button" wire:click="$set('repairLogs', [])" class="text-gray-400 hover:text-gray-900 dark:text-gray-500 dark:hover:text-white"><i class="bi bi-x-lg text-xs"></i></button>
                        </div>
                        <div class="p-4 font-mono text-[10px] sm:text-xs leading-relaxed max-h-[200px] overflow-y-auto custom-scrollbar flex flex-col gap-1.5">
                            @foreach($repairLogs as $log)
                                <div class="flex gap-3"><span class="text-gray-400 dark:text-gray-600 shrink-0">[{{ $log['time'] }}]</span><span class="text-gray-700 dark:text-gray-300">{{ $log['message'] }}</span></div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <!-- 4. SECURITY DETAILS -->
            <div x-show="activeTab === 'security'" x-cloak x-transition.opacity.duration.300ms>
                @php
                    $defStats = $this->securityDefenseStats;
                    $catCounts = $defStats['categories'] ?? [];
                    $activeBlockedIps = $defStats['active_blocked_ips'] ?? collect();
                    $autoBans48h = $defStats['auto_bans_48h'] ?? 0;
                    $adminTotal = $defStats['admin_total'] ?? 0;
                    $admin2fa = $defStats['admin_2fa'] ?? 0;
                @endphp

                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-end border-b border-gray-200 dark:border-gray-800 pb-6 mb-8 mt-8 gap-4">
                    <div>
                        <h2 class="text-2xl font-serif font-bold text-gray-900 dark:text-white mb-1">Sicherheit & Abwehr (Zero-Trust Schild)</h2>
                        <p class="text-xs font-bold text-gray-500 leading-relaxed max-w-xl">Echtzeit-Überwachung von Brute-Force-Attacken, Bot-Honeypots, 2FA-Integrität, KI-Sandbox-Zugriffen, IDOR-Manipulationsversuchen und aktiver IP-Firewall.</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[10px] font-black uppercase tracking-widest bg-emerald-500/10 border border-emerald-500/30 text-emerald-600 dark:text-emerald-400">
                            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                            Auto-IP-Ban (≥10 Fehlversuche) aktiv
                        </span>
                    </div>
                </div>

                <!-- TOP KPI CARDS (4 SÄULEN) -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <!-- Stat 1: Failed Logins -->
                    <div class="bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800/80 rounded-[1.5rem] p-5 shadow-inner flex flex-col gap-3 relative">
                        <div class="flex items-center justify-between border-b border-gray-200 dark:border-gray-800/80 pb-3">
                            <div class="flex items-center gap-3">
                                <x-heroicon-o-finger-print class="w-5 h-5 text-purple-500" />
                                <h3 class="text-xs font-black text-gray-900 dark:text-white uppercase tracking-widest">Login-Abwehr</h3>
                            </div>
                            <div class="relative" x-data="{ tooltip: false }" @mouseenter="tooltip = true" @mouseleave="tooltip = false">
                                <x-heroicon-o-information-circle class="w-4 h-4 text-gray-400 hover:text-purple-500 cursor-help transition-colors" />
                                <div x-show="tooltip" x-transition.opacity.duration.150ms class="absolute right-0 top-full mt-2 w-64 p-3.5 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl shadow-2xl z-30 text-left pointer-events-none" style="display: none;">
                                    <p class="text-[10px] font-black uppercase tracking-wider text-purple-500 mb-1">Was bedeutet Login-Abwehr?</p>
                                    <p class="text-[11px] text-gray-600 dark:text-gray-300 leading-relaxed">Zählt alle fehlgeschlagenen Anmeldeversuche (Web, Checkout, Google OAuth & Mobile API) der letzten 24 Stunden. Ab 10 Fehlversuchen in 15 Minuten sperrt das System die Angreifer-IP automatisch.</p>
                                </div>
                            </div>
                        </div>
                        <div class="flex justify-between items-center mt-2">
                            <span class="text-3xl font-black {{ $failedLogins24h > 10 ? 'text-red-500' : 'text-purple-500 dark:text-purple-400' }}">{{ $failedLogins24h }}</span>
                            <span class="text-[10px] font-bold text-gray-500 uppercase tracking-widest text-right">Fehlversuche<br>(Letzte 24h)</span>
                        </div>
                    </div>

                    <!-- Stat 2: Security Alarms -->
                    <div class="bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800/80 rounded-[1.5rem] p-5 shadow-inner flex flex-col gap-3 relative">
                        <div class="flex items-center justify-between border-b border-gray-200 dark:border-gray-800/80 pb-3">
                            <div class="flex items-center gap-3">
                                <x-heroicon-o-shield-exclamation class="w-5 h-5 text-red-500" />
                                <h3 class="text-xs font-black text-gray-900 dark:text-white uppercase tracking-widest">Abwehr-Ereignisse</h3>
                            </div>
                            <div class="relative" x-data="{ tooltip: false }" @mouseenter="tooltip = true" @mouseleave="tooltip = false">
                                <x-heroicon-o-information-circle class="w-4 h-4 text-gray-400 hover:text-red-500 cursor-help transition-colors" />
                                <div x-show="tooltip" x-transition.opacity.duration.150ms class="absolute right-0 top-full mt-2 w-64 p-3.5 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl shadow-2xl z-30 text-left pointer-events-none" style="display: none;">
                                    <p class="text-[10px] font-black uppercase tracking-wider text-red-500 mb-1">Was sind Abwehr-Ereignisse?</p>
                                    <p class="text-[11px] text-gray-600 dark:text-gray-300 leading-relaxed">Summe aller aktiv abgewehrten Sicherheitsvorfälle der letzten 24h: ausgelöste Spam-Honeypots, Rate-Limits, unbefugte Ticket-/Checkout-Zugriffe (IDOR), SSRF-Versuche und KI-Sandbox-Blockaden.</p>
                                </div>
                            </div>
                        </div>
                        <div class="flex justify-between items-center mt-2">
                            <span class="text-3xl font-black {{ $securityWarnings24h > 0 ? 'text-amber-500' : 'text-emerald-500' }}">{{ $securityWarnings24h }}</span>
                            <span class="text-[10px] font-bold text-gray-500 uppercase tracking-widest text-right">Blockierte Angriffe<br>(Letzte 24h)</span>
                        </div>
                    </div>

                    <!-- Stat 3: WAF / Rate Limit Status & IP Firewall -->
                    <div class="bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800/80 rounded-[1.5rem] p-5 shadow-inner flex flex-col gap-3 relative">
                        <div class="flex items-center justify-between border-b border-gray-200 dark:border-gray-800/80 pb-3">
                            <div class="flex items-center gap-3">
                                <x-heroicon-o-lock-closed class="w-5 h-5 text-emerald-500" />
                                <h3 class="text-xs font-black text-gray-900 dark:text-white uppercase tracking-widest">IP-Firewall & Ban</h3>
                            </div>
                            <div class="relative" x-data="{ tooltip: false }" @mouseenter="tooltip = true" @mouseleave="tooltip = false">
                                <x-heroicon-o-information-circle class="w-4 h-4 text-gray-400 hover:text-emerald-500 cursor-help transition-colors" />
                                <div x-show="tooltip" x-transition.opacity.duration.150ms class="absolute right-0 top-full mt-2 w-64 p-3.5 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl shadow-2xl z-30 text-left pointer-events-none" style="display: none;">
                                    <p class="text-[10px] font-black uppercase tracking-wider text-emerald-500 mb-1">Wie schützt die IP-Firewall?</p>
                                    <p class="text-[11px] text-gray-600 dark:text-gray-300 leading-relaxed">Zeigt die Anzahl aktuell gesperrter Angreifer-IPs sowie die automatischen 24h-Bans der letzten 48 Stunden. Gesperrte IPs werden bei jedem Login-, API- und Checkout-Versuch sofort abgewiesen.</p>
                                </div>
                            </div>
                        </div>
                        <div class="flex justify-between items-center mt-2">
                            <div>
                                <span class="text-3xl font-black {{ $blockedIpsCount > 0 ? 'text-amber-500 dark:text-amber-400' : 'text-emerald-500' }}">{{ $blockedIpsCount }}</span>
                                <span class="block text-[10px] font-bold text-gray-500 mt-0.5">{{ $autoBans48h }} Auto-Bans (48h)</span>
                            </div>
                            <span class="text-[10px] font-bold text-gray-500 uppercase tracking-widest text-right">Aktive<br>IP-Sperren</span>
                        </div>
                    </div>

                    <!-- Stat 4: 2FA & Zero-Trust Schutzschild -->
                    <div class="bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800/80 rounded-[1.5rem] p-5 shadow-inner flex flex-col gap-3 relative">
                        <div class="flex items-center justify-between border-b border-gray-200 dark:border-gray-800/80 pb-3">
                            <div class="flex items-center gap-3">
                                <x-heroicon-o-shield-check class="w-5 h-5 text-cyan-500" />
                                <h3 class="text-xs font-black text-gray-900 dark:text-white uppercase tracking-widest">Zero-Trust & 2FA</h3>
                            </div>
                            <div class="relative" x-data="{ tooltip: false }" @mouseenter="tooltip = true" @mouseleave="tooltip = false">
                                <x-heroicon-o-information-circle class="w-4 h-4 text-gray-400 hover:text-cyan-500 cursor-help transition-colors" />
                                <div x-show="tooltip" x-transition.opacity.duration.150ms class="absolute right-0 top-full mt-2 w-64 p-3.5 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl shadow-2xl z-30 text-left pointer-events-none" style="display: none;">
                                    <p class="text-[10px] font-black uppercase tracking-wider text-cyan-500 mb-1">Was bedeutet Zero-Trust & 2FA?</p>
                                    <p class="text-[11px] text-gray-600 dark:text-gray-300 leading-relaxed">Zeigt den Anteil der Administrator-Konten mit aktivierter Zwei-Faktor-Authentifizierung (2FA) sowie den Status der serverseitigen Attribut-Sperren (#[Locked]) und der KI-Tool-Sandbox.</p>
                                </div>
                            </div>
                        </div>
                        <div class="flex justify-between items-center mt-2">
                            <div>
                                <span class="text-3xl font-black text-cyan-500 dark:text-cyan-400">{{ $admin2fa }}/{{ $adminTotal }}</span>
                                <span class="block text-[10px] font-bold text-emerald-500 mt-0.5">KI-Sandbox & Locked aktiv</span>
                            </div>
                            <span class="text-[10px] font-bold text-gray-500 uppercase tracking-widest text-right">Admin-Konten<br>mit 2FA</span>
                        </div>
                    </div>
                </div>

                <!-- 5-SÄULEN ABWEHR-MATRIX (FILTERBAR) -->
                <div class="mb-8">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-[11px] font-black text-gray-500 dark:text-gray-400 uppercase tracking-widest">Abwehr-Kategorien (48h Telemetrie — Klick zum Filtern)</h3>
                        @if($threatCategoryFilter !== 'all')
                            <button wire:click="setThreatCategoryFilter('all')" class="text-[10px] font-bold text-purple-500 hover:underline uppercase tracking-wider">Filter zurücksetzen (Alle zeigen)</button>
                        @endif
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
                        @php
                            $defensePillars = [
                                [
                                    'key' => 'brute_force',
                                    'label' => 'Brute-Force & Login',
                                    'desc' => 'Login-Versuche & Auto-Bans',
                                    'color' => 'purple',
                                    'info' => 'Erfasst fehlgeschlagene Passwort-Eingaben und Passwort-Reset-Throttling. Schützt Konten vor automatisierten Wörterbuch- und Credential-Stuffing-Attacken.'
                                ],
                                [
                                    'key' => 'bot_defense',
                                    'label' => 'Bot & Rate-Limiter',
                                    'desc' => 'Honeypots & Spam-Abwehr',
                                    'color' => 'amber',
                                    'info' => 'Zählt abgefangene Spam-Bots in unsichtbaren Honeypot-Feldern (Registrierung, Widerruf) sowie ausgelöste Nachrichten- und Ticket-Rate-Limits.'
                                ],
                                [
                                    'key' => 'auth_2fa',
                                    'label' => '2FA & Identität',
                                    'desc' => 'OAuth, 2FA & Token-Schutz',
                                    'color' => 'cyan',
                                    'info' => 'Überwacht die Zwei-Faktor-Authentifizierung (TOTP & Recovery-Codes), Google-OAuth-Prüfungen und blockiert fehlerhafte 2FA-Codes auf Web & Mobile-App.'
                                ],
                                [
                                    'key' => 'api_ai_sandbox',
                                    'label' => 'KI & API-Sandbox',
                                    'desc' => 'Tool-Gate & Bridge-Schutz',
                                    'color' => 'emerald',
                                    'info' => 'Zero-Trust-Schranke für KI-Agenten und WebSocket-Bridge: Verhindert, dass öffentliche Support-Chats interne Verwaltungs-Tools aufrufen oder fremde Requests Transkripte manipulieren.'
                                ],
                                [
                                    'key' => 'idor_integrity',
                                    'label' => 'IDOR & Integrität',
                                    'desc' => 'Checkout, Ticket & Mail-Schutz',
                                    'color' => 'rose',
                                    'info' => 'Blockiert Manipulationsversuche an fremden Ticket-IDs, Bestellsummen/Stripe-Intents, Dateipfaden (LFI) und internen Mail-Server-Adressen (SSRF).'
                                ],
                            ];
                        @endphp
                        @foreach($defensePillars as $pillar)
                            @php
                                $pCount = $catCounts[$pillar['key']] ?? 0;
                                $isActiveFilter = $threatCategoryFilter === $pillar['key'];
                            @endphp
                            <div class="relative group" x-data="{ tooltip: false }">
                                <button type="button"
                                        wire:click="setThreatCategoryFilter('{{ $isActiveFilter ? 'all' : $pillar['key'] }}')"
                                        class="w-full text-left p-4 rounded-2xl border transition-all {{ $isActiveFilter ? 'bg-purple-500/10 border-purple-500 shadow-md' : 'bg-white dark:bg-gray-950/80 border-gray-200 dark:border-gray-800 hover:border-gray-400 dark:hover:border-gray-700' }}">
                                    <div class="flex items-center justify-between mb-1 pr-5">
                                        <span class="text-[10px] font-black uppercase tracking-wider text-gray-700 dark:text-gray-200">{{ $pillar['label'] }}</span>
                                        <span class="text-sm font-black {{ $pCount > 0 ? 'text-amber-500' : 'text-emerald-500' }}">{{ $pCount }}</span>
                                    </div>
                                    <p class="text-[10px] text-gray-400 dark:text-gray-500 leading-tight">{{ $pillar['desc'] }}</p>
                                </button>
                                <div class="absolute top-3.5 right-3 z-20" @mouseenter="tooltip = true" @mouseleave="tooltip = false">
                                    <x-heroicon-o-information-circle class="w-4 h-4 text-gray-400 hover:text-purple-500 cursor-help transition-colors" />
                                    <div x-show="tooltip" x-transition.opacity.duration.150ms class="absolute right-0 top-full mt-2 w-60 p-3 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl shadow-2xl z-30 text-left pointer-events-none" style="display: none;">
                                        <p class="text-[10px] font-black uppercase tracking-wider text-purple-500 mb-1">{{ $pillar['label'] }}</p>
                                        <p class="text-[11px] text-gray-600 dark:text-gray-300 leading-relaxed">{{ $pillar['info'] }}</p>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- AKTIVE IP-FIREWALL & MANUELLE SPERRE -->
                <div class="bg-gray-50/60 dark:bg-gray-950/40 rounded-[2rem] border border-gray-200 dark:border-gray-800 p-6 shadow-inner mb-8">
                    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 pb-5 mb-5 border-b border-gray-200 dark:border-gray-800">
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="text-sm font-black text-gray-900 dark:text-white uppercase tracking-widest flex items-center gap-2">
                                    <x-heroicon-o-no-symbol class="w-5 h-5 text-rose-500" />
                                    Aktive IP-Firewall & Sofort-Sperre
                                </h3>
                                <div class="relative" x-data="{ tooltip: false }" @mouseenter="tooltip = true" @mouseleave="tooltip = false">
                                    <x-heroicon-o-information-circle class="w-4 h-4 text-gray-400 hover:text-rose-500 cursor-help transition-colors" />
                                    <div x-show="tooltip" x-transition.opacity.duration.150ms class="absolute left-0 top-full mt-2 w-72 p-3.5 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl shadow-2xl z-30 text-left pointer-events-none" style="display: none;">
                                        <p class="text-[10px] font-black uppercase tracking-wider text-rose-500 mb-1">Wie funktioniert die IP-Sperrliste?</p>
                                        <p class="text-[11px] text-gray-600 dark:text-gray-300 leading-relaxed">Hier siehst du alle aktuell gesperrten IPv4/IPv6-Adressen. Du kannst auffällige IPs jederzeit manuell sperren oder irrtümlich gesperrte IPs mit einem Klick wieder freigeben.</p>
                                    </div>
                                </div>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">Gesperrte IP-Adressen werden bei Login, OAuth, API und Checkout sofort abgewiesen.</p>
                        </div>

                        <form wire:submit.prevent="blockManualIp" class="flex flex-wrap sm:flex-nowrap items-center gap-2">
                            <input type="text" wire:model="manualBlockIp" placeholder="IP-Adresse (z.B. 203.0.113.5)" class="text-xs px-3 py-2 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-gray-900 dark:text-white focus:outline-none focus:border-purple-500 w-44" />
                            <input type="text" wire:model="manualBlockReason" placeholder="Grund (optional)" class="text-xs px-3 py-2 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-gray-900 dark:text-white focus:outline-none focus:border-purple-500 w-44" />
                            <select wire:model="manualBlockHours" class="text-xs px-3 py-2 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-gray-900 dark:text-white focus:outline-none focus:border-purple-500">
                                <option value="24">24 Stunden</option>
                                <option value="168">7 Tage</option>
                                <option value="0">Permanent</option>
                            </select>
                            <button type="submit" class="text-[10px] font-black uppercase tracking-widest px-4 py-2.5 rounded-xl bg-rose-600 hover:bg-rose-500 text-white transition-colors shrink-0">
                                IP sperren
                            </button>
                        </form>
                    </div>
                    @error('manualBlockIp')
                        <p class="text-xs text-rose-500 font-bold mb-4">{{ $message }}</p>
                    @enderror

                    @if($activeBlockedIps->isNotEmpty())
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                            @foreach($activeBlockedIps as $blocked)
                                <div class="bg-white dark:bg-gray-900 rounded-xl p-3.5 border border-rose-500/30 flex items-center justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-2">
                                            <span class="font-mono text-xs font-black text-rose-600 dark:text-rose-400">{{ $blocked->ip_address }}</span>
                                            <span class="text-[9px] font-bold uppercase px-2 py-0.5 rounded-full bg-rose-500/10 text-rose-500">
                                                {{ $blocked->blocked_until ? 'bis ' . $blocked->blocked_until->format('d.m. H:i') : 'Permanent' }}
                                            </span>
                                        </div>
                                        <p class="text-[11px] text-gray-500 dark:text-gray-400 truncate mt-1" title="{{ $blocked->reason }}">{{ $blocked->reason ?: 'Manuelle Sperre' }}</p>
                                    </div>
                                    <button wire:click="unblockIpAddress('{{ $blocked->ip_address }}')" class="text-[9px] font-bold uppercase tracking-wider px-2.5 py-1.5 rounded-lg bg-gray-100 dark:bg-gray-800 hover:bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 border border-gray-200 dark:border-gray-700 shrink-0 transition-colors">
                                        Freigeben
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-xs text-gray-500 dark:text-gray-400">Aktuell befinden sich keine IP-Adressen auf der Sperrliste.</p>
                    @endif
                </div>

                <!-- THREAT MONITOR -->
                <div class="bg-gray-50/60 dark:bg-gray-950/40 rounded-[2rem] border border-gray-200 dark:border-gray-800 p-6 shadow-inner">
                    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
                        <div class="flex items-center gap-2">
                            <h3 class="text-sm font-black text-gray-900 dark:text-white uppercase tracking-widest flex items-center gap-2">
                                <x-heroicon-s-eye class="w-5 h-5 text-purple-500" />
                                Threat Monitor (Aktuelle Bedrohungen & Abwehr-Log)
                            </h3>
                            <div class="relative" x-data="{ tooltip: false }" @mouseenter="tooltip = true" @mouseleave="tooltip = false">
                                <x-heroicon-o-information-circle class="w-4 h-4 text-gray-400 hover:text-purple-500 cursor-help transition-colors" />
                                <div x-show="tooltip" x-transition.opacity.duration.150ms class="absolute left-0 top-full mt-2 w-72 p-3.5 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl shadow-2xl z-30 text-left pointer-events-none" style="display: none;">
                                    <p class="text-[10px] font-black uppercase tracking-wider text-purple-500 mb-1">Was zeigt der Threat Monitor?</p>
                                    <p class="text-[11px] text-gray-600 dark:text-gray-300 leading-relaxed">Bündelt fehlgeschlagene Logins (gruppiert nach Angreifer-IP) und abgewehrte Sicherheitsvorfälle der letzten 48 Stunden. Über „KI-Threat-Scan“ erstellt Funkira einen tiefgehenden Bedrohungsbericht.</p>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 w-full sm:w-auto justify-end">
                            <button wire:click="triggerAiSecurityThreatAudit" 
                                    wire:loading.attr="disabled"
                                    class="text-[10px] font-bold uppercase tracking-widest px-3 py-1.5 rounded-lg border border-purple-400/50 dark:border-purple-500/40 text-purple-600 dark:text-purple-300 hover:text-purple-900 dark:hover:text-white hover:bg-purple-100/50 dark:hover:bg-purple-900/30 bg-purple-50 dark:bg-purple-950/40 transition-colors flex items-center gap-2">
                                <x-heroicon-o-sparkles class="w-3.5 h-3.5 text-purple-500 dark:text-purple-400" />
                                <span wire:loading.remove wire:target="triggerAiSecurityThreatAudit">KI-Threat-Scan</span>
                                <span wire:loading wire:target="triggerAiSecurityThreatAudit">Analysiere...</span>
                            </button>

                            <button x-data="{ success: false }" 
                                    x-on:click="$wire.clearSecurityLogs().then(() => { success = true; setTimeout(() => success = false, 3000) })" 
                                    :class="success ? 'bg-emerald-500/20 border-emerald-500/50 text-emerald-600 dark:text-emerald-400' : 'text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white border-gray-200 dark:border-gray-800 hover:border-gray-400 dark:hover:border-gray-600 bg-gray-100 dark:bg-gray-900'" 
                                    class="text-[10px] font-bold uppercase tracking-widest px-3 py-1.5 rounded-lg border transition-colors flex items-center gap-2">
                                <x-heroicon-o-trash x-show="!success" class="w-3.5 h-3.5" />
                                <x-heroicon-o-check x-show="success" class="w-3.5 h-3.5" x-cloak />
                                <span x-text="success ? 'Erfolgreich' : 'Alle leeren'"></span>
                            </button>
                        </div>
                    </div>
                    
                    <div class="space-y-3 max-h-[420px] overflow-y-auto custom-scrollbar pr-2">
                        @forelse($this->aggregatedThreatLogs as $log)
                            <div class="bg-white dark:bg-gray-900 rounded-xl p-4 border border-gray-200 dark:border-gray-800/80 hover:border-gray-300 dark:hover:border-gray-700 transition-colors flex flex-col sm:flex-row gap-4 items-start sm:items-center justify-between">
                                <div class="flex gap-4 items-start flex-1 min-w-0">
                                    <div class="shrink-0 w-10 h-10 rounded-lg flex items-center justify-center {{ ($log['status'] ?? '') === 'error' ? 'bg-red-500/10 border-red-500/20 text-red-500' : 'bg-amber-500/10 border-amber-500/20 text-amber-500' }} border">
                                        @if(($log['category'] ?? '') === 'brute_force')
                                            <x-heroicon-o-finger-print class="w-5 h-5" />
                                        @else
                                            <x-heroicon-o-shield-exclamation class="w-5 h-5" />
                                        @endif
                                    </div>
                                    <div class="flex-1 min-w-0 pt-0.5">
                                        <div class="flex flex-wrap justify-between items-start gap-2">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <h4 class="text-sm font-bold text-gray-800 dark:text-gray-200 truncate">{{ $log['title'] ?? 'Unbekanntes Ereignis' }}</h4>
                                                @if(!empty($log['category_label']))
                                                    <span class="bg-purple-500/10 border border-purple-500/30 text-purple-600 dark:text-purple-300 text-[9px] font-bold uppercase px-2 py-0.5 rounded-full">{{ $log['category_label'] }}</span>
                                                @endif
                                                @if($log['is_blocked'] ?? false)
                                                    <span class="bg-rose-500/20 border border-rose-500/50 text-rose-600 dark:text-rose-400 text-[9px] font-black uppercase px-2 py-0.5 rounded-full">IP Gesperrt</span>
                                                @endif
                                            </div>
                                            <span class="text-[10px] font-mono text-gray-400 dark:text-gray-500 whitespace-nowrap">{{ \Carbon\Carbon::parse($log['timestamp'])->format('d.m.Y H:i:s') }}</span>
                                        </div>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 leading-relaxed">{{ $log['message'] ?? '' }}</p>
                                    </div>
                                </div>

                                @if(!empty($log['ip_address']))
                                    <div class="shrink-0 pt-1 sm:pt-0 self-end sm:self-center">
                                        @if($log['is_blocked'] ?? false)
                                            <button wire:click="unblockIpAddress('{{ $log['ip_address'] }}')" class="text-[9px] font-bold uppercase tracking-wider px-2.5 py-1.5 rounded-lg bg-gray-100 dark:bg-gray-800 hover:bg-emerald-100 dark:hover:bg-emerald-900/50 text-emerald-600 dark:text-emerald-400 border border-gray-200 dark:border-gray-700 hover:border-emerald-500/50 transition-colors">
                                                Entsperren
                                            </button>
                                        @else
                                            <button wire:click="blockIpAddress('{{ $log['ip_address'] }}', 24)" class="text-[9px] font-bold uppercase tracking-wider px-2.5 py-1.5 rounded-lg bg-rose-50 dark:bg-rose-950/40 hover:bg-rose-100 dark:hover:bg-rose-900/60 text-rose-600 dark:text-rose-400 border border-rose-200 dark:border-rose-800/50 hover:border-rose-500 transition-colors">
                                                IP sperren
                                            </button>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        @empty
                            <div class="py-12 flex flex-col items-center justify-center text-gray-500 text-center">
                                <x-heroicon-o-shield-check class="w-12 h-12 mb-4 text-emerald-500/50" />
                                <p class="font-bold text-sm text-gray-800 dark:text-gray-300">Keine Bedrohungen erkannt.</p>
                                <p class="text-xs mt-1">Dein System hat in dieser Kategorie keine Sicherheitswarnungen protokolliert.</p>
                            </div>
                        @endforelse
                    </div>
                </div>

                {{-- AI SECURITY REPORT MODAL --}}
                @if($showSecurityReportModal)
                    <div class="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 flex items-center justify-center p-4 xl:p-10" x-data @keydown.escape.window="$wire.closeSecurityReportModal()">
                        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-purple-500/40 rounded-3xl w-full max-w-4xl max-h-[85vh] overflow-hidden relative shadow-2xl dark:shadow-[0_0_50px_rgba(168,85,247,0.2)] flex flex-col">
                            <div class="p-6 border-b border-gray-200 dark:border-gray-800 flex justify-between items-center bg-gray-50 dark:bg-gray-950/80">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-xl bg-purple-500/20 border border-purple-500/40 flex items-center justify-center text-purple-600 dark:text-purple-400">
                                        <x-heroicon-o-shield-check class="w-6 h-6" />
                                    </div>
                                    <div>
                                        <h3 class="text-gray-900 dark:text-white font-bold font-serif text-xl">KI-Sicherheitsanalyse (Threat Report)</h3>
                                        <p class="text-[10px] text-gray-500 font-black uppercase tracking-widest mt-0.5">{{ $latestSecurityReport }}</p>
                                    </div>
                                </div>
                                <button wire:click="closeSecurityReportModal" class="text-gray-400 dark:text-gray-500 bg-gray-100 dark:bg-gray-800/50 p-2 rounded-full hover:bg-gray-200 dark:hover:bg-gray-700 hover:text-gray-900 dark:hover:text-white transition-colors">
                                    <x-heroicon-o-x-mark class="w-6 h-6" />
                                </button>
                            </div>
                            <div class="p-6 overflow-y-auto flex-1 custom-scrollbar text-xs sm:text-sm text-gray-700 dark:text-gray-300">
                                <div class="whitespace-pre-wrap font-mono text-xs bg-gray-50 dark:bg-gray-950 p-4 rounded-xl border border-gray-200 dark:border-gray-800 text-gray-800 dark:text-gray-300 leading-relaxed">{{ $securityReportContent }}</div>
                            </div>
                            <div class="p-4 border-t border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-950 flex justify-end gap-3">
                                <button wire:click="closeSecurityReportModal" class="px-4 py-2 bg-gray-200 dark:bg-gray-800 hover:bg-gray-300 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-xl text-xs font-bold transition-colors">
                                    Schließen
                                </button>
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            <!-- 4. CAPACITIES (SPEICHER & PRODUKTION) -->
            <div x-show="activeTab === 'capacities'"
                 x-cloak
                 x-transition:enter="transition-all duration-500 ease-[cubic-bezier(0.22,1,0.36,1)]"
                 x-transition:enter-start="opacity-0 translate-y-4"
                 x-transition:enter-end="opacity-100 translate-y-0">
                <div x-show="!focusMode" x-cloak class="flex justify-between items-end border-b border-gray-200 dark:border-gray-800 pb-6 mb-8 mt-8">
                    <div>
                        <h2 class="text-2xl font-serif font-bold text-gray-900 dark:text-white mb-1">Kapazitäten</h2>
                        <p class="text-xs font-bold text-gray-500 leading-relaxed max-w-md">Aktuelle Speicherauslastung und produzierende Shop-Gewerke.</p>
                    </div>
                </div>
                <!-- Capacities Content -->
                <div class="space-y-8">
                    <livewire:shop.master.master-shop-capacity />
                    <livewire:shop.master.master-storage-capacity />
                </div>
            </div>

            <!-- 5. PROFIT -->
            <div x-show="activeTab === 'profit'"
                 x-cloak
                 x-transition:enter="transition-all duration-500 ease-[cubic-bezier(0.22,1,0.36,1)]"
                 x-transition:enter-start="opacity-0 translate-y-4"
                 x-transition:enter-end="opacity-100 translate-y-0">
                <div x-show="!focusMode" x-cloak class="flex justify-between items-end border-b border-gray-200 dark:border-gray-800 pb-6 mb-8 mt-8">
                    <div>
                        <h2 class="text-2xl font-serif font-bold text-gray-900 dark:text-white mb-1">Gewinn-Entwicklung</h2>
                        <p class="text-xs font-bold text-gray-500 leading-relaxed max-w-md">Darstellung der realen Shop-Umsätze gegenüber den Ausgaben, zur Kalkulation des Break-Even.</p>
                    </div>
                </div>
                <!-- Profit Content -->
                @include('livewire.shop.master.master-analytics-partials.profit')
            </div>

            <!-- 6. E-COMMERCE -->
            <div x-show="activeTab === 'ecommerce'"
                 x-cloak
                 x-transition:enter="transition-all duration-500 ease-[cubic-bezier(0.22,1,0.36,1)]"
                 x-transition:enter-start="opacity-0 translate-y-4"
                 x-transition:enter-end="opacity-100 translate-y-0">
                <div x-show="!focusMode" x-cloak class="flex justify-between items-end border-b border-gray-200 dark:border-gray-800 pb-6 mb-8 mt-8">
                    <div>
                        <h2 class="text-2xl font-serif font-bold text-gray-900 dark:text-white mb-1">E-Commerce Einblicke</h2>
                        <p class="text-xs font-bold text-gray-500 leading-relaxed max-w-md">Detaillierte Analyse zu Warenkörben, verlassenen Checkouts und Artikel-Statistiken.</p>
                    </div>
                </div>
                <!-- E-Commerce Content -->
                @include('livewire.shop.master.master-analytics-partials.charts')
            </div>

            <!-- 7. TRAFFIC & KUNDEN -->
            <div x-show="activeTab === 'traffic'"
                 x-cloak
                 x-transition:enter="transition-all duration-500 ease-[cubic-bezier(0.22,1,0.36,1)]"
                 x-transition:enter-start="opacity-0 translate-y-4"
                 x-transition:enter-end="opacity-100 translate-y-0">
                <div x-show="!focusMode" x-cloak class="flex justify-between items-end border-b border-gray-200 dark:border-gray-800 pb-6 mb-8 mt-8">
                    <div>
                        <h2 class="text-2xl font-serif font-bold text-gray-900 dark:text-white mb-1">Traffic & Kunden</h2>
                        <p class="text-xs font-bold text-gray-500 leading-relaxed max-w-md">Web-Traffic, Kundenverhalten und Wachstumsstatistiken für optimale Performance.</p>
                    </div>
                </div>
                <!-- Traffic Content -->
                <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
                    @include('livewire.shop.master.master-analytics-partials.traffic')
                </div>
                <div class="mt-8 pt-8 border-t border-gray-200 dark:border-gray-800/80">
                    <h3 class="text-xl font-serif font-bold text-gray-900 dark:text-white mb-6">Quellenanalyse & Akquise-Kanäle</h3>
                    @include('livewire.shop.master.master-analytics-partials.sources')
                </div>
                <div class="mt-8 pt-8 border-t border-gray-200 dark:border-gray-800/80">
                    <h3 class="text-xl font-serif font-bold text-gray-900 dark:text-white mb-6">Kundengewinnung & Registrierungen</h3>
                    @include('livewire.shop.master.master-analytics-partials.customers')
                </div>
            </div>

            <!-- 8. LIVE ROUTINE DETAIL (FOKUS-SATELLIT) -->
            <div x-show="activeTab === 'routine'" x-cloak x-transition.opacity.duration.300ms>
                <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 border-b border-gray-200 dark:border-gray-800 pb-6 mb-8">
                    <div>
                        <h2 class="text-2xl font-serif font-bold text-gray-900 dark:text-white mb-1">Live Routine & Tagesablauf</h2>
                        <p class="text-xs font-bold text-gray-500 leading-relaxed max-w-md">Dein aktueller Routine-Block und offene Tages-Check-ins.</p>
                    </div>
                    <a href="{{ route('admin.routine') }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-[#C5A059] text-gray-950 text-xs font-black uppercase tracking-widest hover:brightness-110 transition-all shadow-[0_0_15px_rgba(197,160,89,0.3)]">
                        Zur Routine-Verwaltung <x-heroicon-s-arrow-right class="w-4 h-4" />
                    </a>
                </div>

                @if(isset($currentActiveRoutine) && $currentActiveRoutine)
                    @php
                        $startT = \Carbon\Carbon::parse($currentActiveRoutine->start_time)->format('H:i');
                        $endT = \Carbon\Carbon::parse($currentActiveRoutine->start_time)->addMinutes($currentActiveRoutine->duration_minutes)->format('H:i');
                    @endphp
                    <div class="bg-gray-50 dark:bg-gray-950/90 rounded-2xl p-6 border border-[#C5A059]/40">
                        <div class="flex items-center justify-between mb-3">
                            <h4 class="text-xl font-black text-gray-900 dark:text-white">{{ $currentActiveRoutine->title }}</h4>
                            <span class="bg-[#C5A059]/20 text-[#C5A059] border border-[#C5A059]/50 px-3 py-1 rounded-full text-xs font-bold">Aktiv ({{ $startT }} - {{ $endT }} Uhr)</span>
                        </div>
                        <p class="text-sm text-gray-600 dark:text-gray-300 leading-relaxed">{{ $currentActiveRoutine->message ?: 'Keine weiteren Details hinterlegt.' }}</p>
                    </div>
                @else
                    <div class="bg-gray-50 dark:bg-gray-950/80 rounded-2xl p-8 border border-gray-200 dark:border-gray-800 text-center">
                        <x-heroicon-o-clock class="w-12 h-12 text-[#C5A059] mx-auto mb-3 opacity-80" />
                        <h4 class="text-lg font-bold text-gray-900 dark:text-white">Aktuell läuft kein zeitgebundener Block</h4>
                        <p class="text-xs text-gray-500 mt-1">
                            @if(($healthChecks['routine']['count'] ?? 0) > 0)
                                Es sind noch {{ $healthChecks['routine']['count'] }} Routine-Schritte für heute offen.
                            @else
                                Alle fälligen Routine-Schritte für heute sind erledigt.
                            @endif
                        </p>
                    </div>
                @endif
            </div>

            <!-- 9. KALENDER DETAIL (FOKUS-SATELLIT) -->
            <div x-show="activeTab === 'calendar'" x-cloak x-transition.opacity.duration.300ms>
                <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 border-b border-gray-200 dark:border-gray-800 pb-6 mb-8">
                    <div>
                        <h2 class="text-2xl font-serif font-bold text-gray-900 dark:text-white mb-1">Kalender & Nächste Termine</h2>
                        <p class="text-xs font-bold text-gray-500 leading-relaxed max-w-md">Deine anstehenden Termine und Ereignisse im Überblick.</p>
                    </div>
                    <a href="{{ route('admin.calender') }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-[#C5A059] text-gray-950 text-xs font-black uppercase tracking-widest hover:brightness-110 transition-all shadow-[0_0_15px_rgba(197,160,89,0.3)]">
                        Zum Kalender <x-heroicon-s-arrow-right class="w-4 h-4" />
                    </a>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @forelse($upcomingEvents as $event)
                        @php
                            $isToday = $event->start_date->isToday();
                            $isTomorrow = $event->start_date->isTomorrow();
                            $dateLabel = $isToday ? 'Heute' : ($isTomorrow ? 'Morgen' : $event->start_date->format('d.m.'));
                            $timeStr = $event->is_all_day ? 'Ganztägig' : $event->start_date->format('H:i');
                            $colorClass = $event->priority === 'high' ? 'text-red-400 bg-red-500/10 border-red-500/30' : 'text-[#C5A059] bg-[#C5A059]/10 border-[#C5A059]/30';
                        @endphp
                        <div class="bg-gray-50 dark:bg-gray-950 rounded-xl p-4 border border-gray-200 dark:border-gray-800 flex items-start gap-4">
                            <div class="flex flex-col items-center justify-center shrink-0 w-14 h-14 rounded-lg border {{ $colorClass }}">
                                <span class="text-[10px] font-bold uppercase">{{ $dateLabel }}</span>
                                <span class="{{ $event->is_all_day ? 'text-[8px] tracking-tight uppercase mt-0.5' : 'text-[13px]' }} font-black">{{ $timeStr }}</span>
                            </div>
                            <div class="flex-1 min-w-0 py-0.5">
                                <h4 class="text-sm font-bold text-gray-900 dark:text-white truncate">{{ $event->title }}</h4>
                                <p class="text-xs text-gray-500 truncate mt-1">{{ $event->description ?: 'Keine Beschreibung' }}</p>
                            </div>
                        </div>
                    @empty
                        <div class="col-span-full bg-gray-50 dark:bg-gray-950/80 rounded-2xl p-8 border border-gray-200 dark:border-gray-800 text-center">
                            <x-heroicon-o-calendar-days class="w-10 h-10 text-[#C5A059] mx-auto mb-2 opacity-80" />
                            <p class="text-sm font-bold text-gray-500">Keine anstehenden Termine</p>
                        </div>
                    @endforelse
                </div>
            </div>

        </div>
    </div>

</div>
