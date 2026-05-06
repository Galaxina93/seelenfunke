<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #333;
            line-height: 1.5;
            margin: 0;
            padding: 20px;
        }
        a {
            color: #3182ce;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
        h1 {
            color: #1a202c;
            border-bottom: 3px solid #3182ce;
            padding-bottom: 10px;
            margin-bottom: 5px;
        }
        .subtitle {
            color: #4a5568;
            font-size: 1.2em;
            margin-bottom: 20px;
            font-weight: bold;
        }
        h2 {
            color: #2b6cb0;
            margin-top: 30px;
            border-bottom: 2px solid #ebf8ff;
            padding-bottom: 5px;
        }
        .page-break {
            page-break-before: always;
        }
        h3 {
            color: #2d3748;
            margin-bottom: 10px;
        }
        .description {
            font-size: 1.05em;
            color: #4a5568;
            margin-bottom: 25px;
            font-style: italic;
            background: #f7fafc;
            padding: 15px;
            border-left: 4px solid #cbd5e0;
        }
        .box {
            background-color: #f7fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .calendar-box {
            background-color: #fffaf0;
            border-left: 4px solid #dd6b20;
            padding: 15px;
            margin-bottom: 20px;
        }
        .calendar-box h3 {
            color: #c05621;
            margin-top: 0;
        }
        
        table.logistics {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        table.logistics th, table.logistics td {
            text-align: left;
            padding: 8px;
            border-bottom: 1px solid #edf2f7;
            vertical-align: top;
        }
        table.logistics th {
            width: 30%;
            color: #4a5568;
        }
        
        .packing-grid {
            display: table;
            width: 100%;
            margin-bottom: 20px;
        }
        .packing-col {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            padding-right: 15px;
        }
        .packing-category {
            margin-bottom: 15px;
            background: #fff;
            padding: 10px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
        }
        .packing-category h4 {
            margin: 0 0 8px 0;
            color: #2b6cb0;
            font-size: 1em;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 5px;
        }
        .packing-category ul {
            margin: 0;
            padding-left: 20px;
            list-style-type: none;
            font-size: 0.9em;
        }
        .packing-category ul li {
            position: relative;
            margin-bottom: 3px;
        }
        .packing-category ul li:before {
            content: "☐";
            position: absolute;
            left: -18px;
            color: #a0aec0;
            font-size: 1.2em;
            line-height: 1;
        }
        
        .attraction {
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px dashed #e2e8f0;
        }
        .attraction:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        .attraction-name {
            font-weight: bold;
            color: #2d3748;
            font-size: 1.1em;
        }
        .attraction-address {
            font-size: 0.85em;
            color: #718096;
            margin-bottom: 5px;
        }
        .attraction-tips {
            font-size: 0.9em;
            color: #4a5568;
        }
        
        .day-block {
            margin-bottom: 25px;
            page-break-inside: avoid;
        }
        .activity {
            margin-bottom: 10px;
            padding-left: 15px;
            border-left: 3px solid #3182ce;
            background: #fff;
            padding-top: 5px;
            padding-bottom: 5px;
        }
        .activity-desc {
            margin-top: 2px;
            color: #718096;
            font-size: 0.9em;
        }
        
        .footer {
            margin-top: 50px;
            text-align: center;
            font-size: 0.8em;
            color: #a0aec0;
            border-top: 1px solid #edf2f7;
            padding-top: 20px;
        }
    </style>
</head>
<body>

    @php
    if (!function_exists('make_links_clickable')) {
        function make_links_clickable($text) {
            return preg_replace('!(((f|ht)tp(s)?://)[-a-zA-Zа-яА-Я()0-9@:%_+.~#?&;//=]+)!i', '<a href="$1" target="_blank">Link öffnen</a>', e($text));
        }
    }
    @endphp

    <h1>{{ $title }}</h1>
    
    @if(!empty($startDate) && !empty($endDate))
        <div class="subtitle">{{ $startDate }} - {{ $endDate }}</div>
    @endif
    
    @if(!empty($description))
        <p class="description">{{ $description }}</p>
    @endif

    @if(!empty($calendarEvents) && count($calendarEvents) > 0)
        <div class="calendar-box">
            <h3>📅 Termine während der Reise</h3>
            <p style="margin-top:0; font-size:0.9em; color:#718096;">Zur Erinnerung: Diese Termine aus deinem Kalender fallen in deinen Reisezeitraum.</p>
            <ul style="margin-bottom:0;">
                @foreach($calendarEvents as $event)
                    <li>{{ $event }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if(!empty($logistics))
        <h2>Reise-Logistik & Unterkunft</h2>
        <div class="box">
            <table class="logistics">
                @if(!empty($logistics['start_address']))
                <tr>
                    <th>Startadresse</th>
                    <td>{{ $logistics['start_address'] }}</td>
                </tr>
                @endif
                @if(!empty($logistics['destination_address']))
                <tr>
                    <th>Zieladresse</th>
                    <td>{{ $logistics['destination_address'] }}</td>
                </tr>
                @endif
                @if(!empty($logistics['accommodation']))
                <tr>
                    <th>Unterkunft</th>
                    <td>
                        <strong>{{ $logistics['accommodation']['name'] ?? '' }}</strong><br>
                        <span style="font-size:0.9em; color:#718096;">{{ $logistics['accommodation']['address'] ?? '' }}</span><br>
                        <span style="font-size:0.9em;">{!! make_links_clickable($logistics['accommodation']['details'] ?? '') !!}</span>
                    </td>
                </tr>
                @endif
            </table>
            
            @if(!empty($logistics['route_stops']) && count($logistics['route_stops']) > 0)
                <h4 style="margin-top: 15px; margin-bottom: 5px;">Anreise & Route</h4>
                <ul style="font-size: 0.95em; padding-left: 20px;">
                    @foreach($logistics['route_stops'] as $stop)
                        <li><strong>{{ $stop['type'] ?? 'Stopp' }}:</strong> {!! make_links_clickable($stop['details'] ?? '') !!}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif

    @if(!empty($packingList))
        <div class="page-break"></div>
        <h2>Koffer packen: Packliste</h2>
        @php
            $categories = [
                'documents' => 'Wichtige Dokumente & Unterlagen',
                'medication' => 'Persönliche Medikamente',
                'hygiene' => 'Hygiene & Pflege',
                'clothing' => 'Kleidung',
                'comfort' => 'Komfort',
                'tech' => 'Technik & Unterhaltung',
                'specific' => 'Reisespezifische Extras'
            ];
            $half = ceil(count($categories) / 2);
            $catKeys = array_keys($categories);
            $col1 = array_slice($catKeys, 0, $half);
            $col2 = array_slice($catKeys, $half);
        @endphp
        
        <div class="packing-grid">
            <div class="packing-col">
                @foreach($col1 as $cat)
                    @if(!empty($packingList[$cat]) && count($packingList[$cat]) > 0)
                        <div class="packing-category">
                            <h4>{{ $categories[$cat] }}</h4>
                            <ul>
                                @foreach($packingList[$cat] as $item)
                                    <li>{{ $item }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                @endforeach
            </div>
            <div class="packing-col">
                @foreach($col2 as $cat)
                    @if(!empty($packingList[$cat]) && count($packingList[$cat]) > 0)
                        <div class="packing-category">
                            <h4>{{ $categories[$cat] }}</h4>
                            <ul>
                                @foreach($packingList[$cat] as $item)
                                    <li>{{ $item }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    @endif

    @if(!empty($attractions) && count($attractions) > 0)
        <div class="page-break"></div>
        <h2>Sehenswürdigkeiten & Tipps</h2>
        <div class="box" style="page-break-inside: avoid;">
            @foreach($attractions as $attraction)
                <div class="attraction">
                    <div class="attraction-name">{{ $attraction['name'] ?? '' }}</div>
                    <div class="attraction-address">{{ $attraction['address'] ?? '' }}</div>
                    <div class="attraction-tips"><em>Tipp:</em> {!! make_links_clickable($attraction['tips'] ?? '') !!}</div>
                </div>
            @endforeach
        </div>
    @endif

    @if(!empty($generalTips) && count($generalTips) > 0)
        <div class="page-break"></div>
        <h2>Allgemeine Reisetipps & Ideen</h2>
        <div class="box">
            <ul style="font-size: 0.95em; color: #4a5568; line-height: 1.6; padding-left: 20px;">
                @foreach($generalTips as $tip)
                    <li style="margin-bottom: 10px;">{!! make_links_clickable($tip) !!}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if(!empty($itinerary))
        <div class="page-break"></div>
        <h2>Detaillierter Reiseverlauf</h2>
        @foreach($itinerary as $dayPlan)
            <div class="day-block">
                <h3>{{ $dayPlan['day'] ?? 'Tag X' }}</h3>
                @if(!empty($dayPlan['activities']))
                    @foreach($dayPlan['activities'] as $activity)
                        <div class="activity">
                            <table style="width: 100%; border-collapse: collapse; margin-bottom: 2px;">
                                <tr>
                                    <td style="width: 110px; font-weight: bold; color: #3182ce; vertical-align: top;">{{ $activity['time'] ?? '' }}</td>
                                    <td style="font-weight: bold; color: #2d3748; vertical-align: top;">{{ $activity['title'] ?? 'Aktivität' }}</td>
                                </tr>
                            </table>
                            @if(!empty($activity['description']))
                                <div class="activity-desc">{!! make_links_clickable($activity['description']) !!}</div>
                            @endif
                        </div>
                    @endforeach
                @endif
            </div>
        @endforeach
    @endif

    <div class="footer">
        Erstellt von deinem digitalen Reise-Assistenten (AI Workspace)
    </div>

</body>
</html>
