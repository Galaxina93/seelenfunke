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
            color: #1e3a8a;
            border-bottom: 2px solid #bfdbfe;
            padding-bottom: 10px;
        }
        h2 {
            color: #1e40af;
            margin-top: 30px;
            border-bottom: 1px solid #dbeafe;
            padding-bottom: 5px;
        }
        .description {
            font-size: 1.1em;
            color: #4b5563;
            margin-bottom: 30px;
            font-style: italic;
        }
        .locations-list {
            background-color: #eff6ff;
            border-radius: 8px;
            padding: 15px 25px;
            margin-bottom: 30px;
        }
        .locations-list ul {
            margin: 0;
            padding-left: 20px;
        }
        .locations-list li {
            margin-bottom: 8px;
        }
        .footer {
            margin-top: 50px;
            text-align: center;
            font-size: 0.8em;
            color: #9ca3af;
            border-top: 1px solid #e5e7eb;
            padding-top: 20px;
        }
    </style>
</head>
<body>

    <h1>{{ $title }}</h1>
    
    @if(!empty($description))
        <p class="description">{{ $description }}</p>
    @endif

    @if(!empty($locations))
        <h2>Recherchierte Orte & Koordinaten</h2>
        <div class="locations-list">
            <ul>
                @foreach($locations as $loc)
                    <li>{{ $loc }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="footer">
        Erstellt von deinem digitalen KI-Assistenten
    </div>

</body>
</html>
