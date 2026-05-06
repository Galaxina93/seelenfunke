<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>PERSONENPROFIL</title>
    <style>
        body { font-family: "Courier New", Courier, monospace; color: #111; font-size: 12px; background: #fff; margin: 0; padding: 20px; }
        .header { border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 20px; text-align: center; }
        .top-secret { font-size: 18px; font-weight: bold; color: #b91c1c; letter-spacing: 5px; margin-bottom: 5px; }
        .id-bar { font-size: 10px; color: #666; }
        .stamp { position: absolute; top: 50px; right: 50px; border: 3px solid #b91c1c; color: #b91c1c; padding: 10px; font-size: 20px; font-weight: bold; transform: rotate(15deg); }
        .content { width: 100%; display: table; }
        .col-left { display: table-cell; width: 35%; vertical-align: top; padding-right: 20px; }
        .col-right { display: table-cell; width: 65%; vertical-align: top; }
        .photo-box { width: 100%; min-height: 200px; border: 2px solid #000; background: #eee; text-align: center; margin-bottom: 15px; }
        .photo-box img { width: 100%; height: auto; max-height: 250px; object-fit: cover; filter: grayscale(100%) contrast(120%); }
        .data-row { margin-bottom: 10px; border-bottom: 1px dotted #ccc; padding-bottom: 5px; }
        .label { font-weight: bold; font-size: 10px; text-transform: uppercase; color: #555; display: block; margin-bottom: 2px; }
        .value { font-size: 14px; font-weight: bold; }
        h1 { font-size: 24px; margin: 0 0 5px 0; text-transform: uppercase; }
        h3 { font-size: 14px; margin: 20px 0 10px 0; border-bottom: 1px solid #000; padding-bottom: 2px; text-transform: uppercase; }
        .summary { background: #f9f9f9; border: 1px solid #ddd; padding: 10px; font-family: "Georgia", serif; font-size: 12px; line-height: 1.5; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #ddd; padding: 6px; text-align: left; font-size: 11px; }
        th { background: #f0f0f0; font-weight: bold; }
    </style>
</head>
<body>

@php
    $p = is_array($persona) ? $persona : (array)$persona;
    $name = $p['name'] ?? 'Unbekannt';
    $aliases = $p['aliases'] ?? '-';
    $status = $p['status'] ?? 'Unknown';
    $origin = $p['origin'] ?? 'Classified';
    $birthDate = $p['birth_date'] ?? 'REDACTED';
    $imageUrl = $p['image_url'] ?? null;
    $summary = $p['summary'] ?? 'Keine Geheimdienst-Informationen verfügbar.';
    $careerTimeline = $p['career_timeline'] ?? [];
    $associates = $p['known_associates'] ?? [];
@endphp

<div class="stamp">CLASSIFIED</div>

<div class="header">
    <div class="top-secret">TOP SECRET // EYES ONLY</div>
    <div class="id-bar">PERSONENPROFIL ID: {{ substr(md5($name), 0, 8) }}-{{ date('Y') }} | GENERATED: {{ date('Y-m-d H:i') }}</div>
</div>

<div class="content">
    <div class="col-left">
        <div class="photo-box">
            @if($imageUrl)
                <img src="{{ $imageUrl }}" alt="{{ $name }}">
            @else
                <br><br><br>NO PHOTOGRAPH<br>AVAILABLE
            @endif
        </div>
        
        <div class="data-row">
            <span class="label">CURRENT STATUS</span>
            <span class="value">{{ strtoupper($status) }}</span>
        </div>
        <div class="data-row">
            <span class="label">ORIGIN / LOCATION</span>
            <span class="value">{{ $origin }}</span>
        </div>
        <div class="data-row">
            <span class="label">DATE OF BIRTH</span>
            <span class="value">{{ $birthDate }}</span>
        </div>
    </div>
    
    <div class="col-right">
        <h1>{{ $name }}</h1>
        <div class="data-row" style="border: none;">
            <span class="label">KNOWN ALIASES / ROLES</span>
            <span class="value" style="font-weight: normal;">{{ $aliases }}</span>
        </div>
        
        <h3>ZUSAMMENFASSUNG</h3>
        <div class="summary">
            {{ $summary }}
        </div>
        
        <h3>CAREER TIMELINE</h3>
        @if(count($careerTimeline) > 0)
            <table>
                <thead>
                    <tr>
                        <th width="20%">YEAR</th>
                        <th width="80%">EVENT / MILESTONE</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($careerTimeline as $ct)
                        <tr>
                            <td><strong>{{ $ct['year'] ?? '' }}</strong></td>
                            <td>{{ $ct['event'] ?? '' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p style="font-size: 11px; color: #666;">No timeline data available.</p>
        @endif
        
        <h3>KNOWN ASSOCIATES</h3>
        @if(count($associates) > 0)
            <ul style="font-size: 12px; margin: 0; padding-left: 20px;">
                @foreach($associates as $assoc)
                    <li>{{ $assoc }}</li>
                @endforeach
            </ul>
        @else
            <p style="font-size: 11px; color: #666;">No associates on record.</p>
        @endif
    </div>
</div>

<div style="margin-top: 50px; text-align: center; font-size: 9px; color: #999; border-top: 1px solid #ccc; padding-top: 10px;">
    UNAUTHORIZED DISCLOSURE SUBJECT TO CRIMINAL SANCTIONS.<br>
    DO NOT DUPLICATE.
</div>

</body>
</html>
