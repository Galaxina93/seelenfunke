<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; color: #333; line-height: 1.5; margin: 0; padding: 20px; }
        h1 { color: #1a202c; border-bottom: 3px solid #3182ce; padding-bottom: 10px; margin-bottom: 5px; }
        .description { font-size: 1.05em; color: #4a5568; margin-bottom: 25px; font-style: italic; background: #f7fafc; padding: 15px; border-left: 4px solid #cbd5e0; }
        .place-box { background-color: #f7fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px; margin-bottom: 20px; page-break-inside: avoid; }
        .place-name { font-weight: bold; font-size: 1.2em; color: #2b6cb0; margin-bottom: 10px; border-bottom: 1px solid #edf2f7; padding-bottom: 5px; }
        .detail-row { margin-bottom: 5px; font-size: 0.95em; }
        .detail-label { font-weight: bold; color: #4a5568; display: inline-block; width: 100px; }
        a { color: #3182ce; text-decoration: none; }
        .footer { margin-top: 50px; text-align: center; font-size: 0.8em; color: #a0aec0; border-top: 1px solid #edf2f7; padding-top: 20px; }
    </style>
</head>
<body>
    @php
    if (!function_exists('make_links_clickable_places')) {
        function make_links_clickable_places($text) {
            return preg_replace('!(((f|ht)tp(s)?://)[-a-zA-Zа-яА-Я()0-9@:%_+.~#?&;//=]+)!i', '<a href="$1" target="_blank">$1</a>', e($text));
        }
    }
    @endphp

    <h1>{{ $title }}</h1>
    
    @if(!empty($description))
        <p class="description">{{ $description }}</p>
    @endif

    @if(!empty($places) && count($places) > 0)
        @foreach($places as $place)
            <div class="place-box">
                <div class="place-name">{{ $place['name'] ?? 'Unbekannt' }}</div>
                
                @if(!empty($place['address']))
                    <div class="detail-row">
                        <span class="detail-label">Adresse:</span>
                        <span>{{ $place['address'] }}</span>
                    </div>
                @endif
                
                @if(!empty($place['phone']))
                    <div class="detail-row">
                        <span class="detail-label">Telefon:</span>
                        <span>{{ $place['phone'] }}</span>
                    </div>
                @endif
                
                @if(!empty($place['email']))
                    <div class="detail-row">
                        <span class="detail-label">E-Mail:</span>
                        <span><a href="mailto:{{ $place['email'] }}">{{ $place['email'] }}</a></span>
                    </div>
                @endif
                
                @if(!empty($place['website']))
                    <div class="detail-row">
                        <span class="detail-label">Webseite:</span>
                        <span>{!! make_links_clickable_places($place['website']) !!}</span>
                    </div>
                @endif
                
                @if(!empty($place['description']))
                    <div class="detail-row" style="margin-top: 10px; color: #718096; font-size: 0.9em; border-top: 1px dashed #e2e8f0; padding-top: 10px;">
                        {!! make_links_clickable_places($place['description']) !!}
                    </div>
                @endif
            </div>
        @endforeach
    @else
        <p>Keine Orte gefunden.</p>
    @endif

    <div class="footer">
        Erstellt von deinem digitalen Assistenten (AI Workspace)
    </div>
</body>
</html>
