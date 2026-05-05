<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #333;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
        }
        h1 {
            color: #1a202c;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 10px;
        }
        h2 {
            color: #2d3748;
            margin-top: 30px;
            border-bottom: 1px solid #edf2f7;
            padding-bottom: 5px;
        }
        .description {
            font-size: 1.1em;
            color: #4a5568;
            margin-bottom: 30px;
            font-style: italic;
        }
        .packing-list {
            background-color: #f7fafc;
            border-radius: 8px;
            padding: 15px 25px;
            margin-bottom: 30px;
        }
        .packing-list ul {
            margin: 0;
            padding-left: 20px;
        }
        .day-block {
            margin-bottom: 30px;
            page-break-inside: avoid;
        }
        .activity {
            margin-bottom: 15px;
            padding-left: 15px;
            border-left: 3px solid #3182ce;
        }
        .activity-time {
            font-weight: bold;
            color: #3182ce;
            display: inline-block;
            width: 60px;
        }
        .activity-title {
            font-weight: bold;
            color: #2d3748;
        }
        .activity-desc {
            margin-top: 5px;
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

    <h1>{{ $title }}</h1>
    
    @if(!empty($description))
        <p class="description">{{ $description }}</p>
    @endif

    @if(!empty($packingList))
        <h2>Koffer packen: Packliste</h2>
        <div class="packing-list">
            <ul>
                @foreach($packingList as $item)
                    <li>{{ $item }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if(!empty($itinerary))
        <h2>Reiseverlauf</h2>
        @foreach($itinerary as $dayPlan)
            <div class="day-block">
                <h3>{{ $dayPlan['day'] ?? 'Tag X' }}</h3>
                @if(!empty($dayPlan['activities']))
                    @foreach($dayPlan['activities'] as $activity)
                        <div class="activity">
                            <div>
                                <span class="activity-time">{{ $activity['time'] ?? '' }}</span>
                                <span class="activity-title">{{ $activity['title'] ?? 'Aktivität' }}</span>
                            </div>
                            @if(!empty($activity['description']))
                                <div class="activity-desc">{{ $activity['description'] }}</div>
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
