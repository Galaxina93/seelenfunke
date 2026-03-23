<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>Behandlungsplan: {{ $plan->title }}</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #333333;
            line-height: 1.5;
            font-size: 13px;
            margin: 0;
            padding: 20px;
        }

        .header {
            border-bottom: 2px solid #14b8a6; /* teal-500 */
            padding-bottom: 10px;
            margin-bottom: 30px;
        }

        .header table {
            width: 100%;
            border-collapse: collapse;
        }

        .header td {
            vertical-align: top;
        }

        .company-info h1 {
            color: #14b8a6;
            margin: 0 0 5px 0;
            font-size: 24px;
        }

        .company-info p {
            margin: 0;
            font-size: 12px;
            color: #666;
        }

        .doc-meta {
            text-align: right;
        }

        .doc-meta h2 {
            margin: 0 0 5px 0;
            font-size: 20px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #1f2937;
        }

        .doc-meta p {
            margin: 0;
            font-size: 12px;
            color: #666;
        }

        .section-title {
            background-color: #f3f4f6;
            padding: 8px 12px;
            border-left: 4px solid #1f2937;
            font-weight: bold;
            font-size: 14px;
            margin-top: 30px;
            margin-bottom: 15px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .info-grid {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .info-grid td {
            padding: 5px;
            vertical-align: top;
        }

        .info-label {
            font-weight: bold;
            color: #4b5563;
            width: 30%;
        }

        .info-value {
            color: #1f2937;
        }

        .diagnosis-box {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 30px;
        }

        table.items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        table.items-table th {
            background-color: #1f2937;
            color: white;
            text-align: left;
            padding: 10px;
            font-size: 12px;
            text-transform: uppercase;
            border-bottom: 2px solid #14b8a6;
        }

        table.items-table td {
            padding: 10px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 13px;
        }

        table.items-table tr:nth-child(even) {
            background-color: #f9fafb;
        }

        .footer {
            margin-top: 50px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            font-size: 10px;
            color: #9ca3af;
        }

        .status-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .status-active {
            background-color: #fef3c7;
            color: #d97706;
            border: 1px solid #fcd34d;
        }

        .status-completed {
            background-color: #d1fae5;
            color: #059669;
            border: 1px solid #6ee7b7;
        }

        .evaluation-box {
            margin-top: 30px;
            padding: 15px;
            background-color: #eff6ff;
            border-left: 4px solid #3b82f6;
            border-radius: 0 4px 4px 0;
        }
    </style>
</head>
<body>

    <div class="header">
        <table>
            <tr>
                <td class="company-info">
                    <h1>Seelenfunke</h1>
                    <p>Internal Medical Assistant</p>
                    <p>Attending: Dr. Funki (AI)</p>
                </td>
                <td class="doc-meta">
                    <h2>Behandlungsplan</h2>
                    <p><strong>Datum:</strong> {{ now()->format('d.m.Y') }}</p>
                    <p><strong>Patient:</strong> {{ $plan->user->first_name ?? 'CEO' }} {{ $plan->user->last_name ?? '' }}</p>
                    <p>
                        <strong>Status:</strong> 
                        <span class="status-badge {{ $plan->status === 'completed' ? 'status-completed' : 'status-active' }}">
                            {{ $plan->status === 'completed' ? 'Durchgeführt' : 'Aktiv' }}
                        </span>
                    </p>
                </td>
            </tr>
        </table>
    </div>

    <div class="section-title">Allgemeine Daten</div>
    <table class="info-grid">
        <tr>
            <td class="info-label">Titel der Behandlung:</td>
            <td class="info-value"><strong>{{ $plan->title }}</strong></td>
        </tr>
        <tr>
            <td class="info-label">Zeitraum:</td>
            <td class="info-value">
                {{ $plan->start_date ? $plan->start_date->format('d.m.Y') : 'Unbekannt' }} 
                bis 
                {{ $plan->end_date ? $plan->end_date->format('d.m.Y') : 'Unbekannt' }}
            </td>
        </tr>
    </table>

    <div class="section-title">Diagnose & Zusammenfassung</div>
    <div class="diagnosis-box">
        {!! nl2br(e($plan->diagnosis_summary)) !!}
    </div>

    <div class="section-title">Medikation & Aufgaben</div>
    <table class="items-table">
        <thead>
            <tr>
                <th width="30%">Präparat / Massnahme</th>
                <th width="20%">Dosierung</th>
                <th width="15%">Dauer (Tage)</th>
                <th width="35%">Zusätzliche Hinweise</th>
            </tr>
        </thead>
        <tbody>
            @forelse($plan->items as $item)
            <tr>
                <td><strong>{{ $item->name }}</strong></td>
                <td>{{ $item->dosage }}</td>
                <td>{{ $item->duration_days ?? '-' }}</td>
                <td>{{ $item->notes ?? '-' }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="4" style="text-align: center; color: #6b7280; font-style: italic;">Keine spezifischen Medikamente verordnet.</td>
            </tr>
            @endforelse
        </tbody>
    </table>

    @if($plan->status === 'completed' && $plan->result_evaluation)
    <div class="evaluation-box">
        <h4 style="margin-top: 0; color: #1e40af; font-size: 14px;">Abschlussevaluation:</h4>
        {!! nl2br(e($plan->result_evaluation)) !!}
    </div>
    @endif

    <div class="footer">
        Generiert durch Seelenfunke AI (Agent: {{ $plan->agent->name ?? 'Dr. Funki' }}) am {{ now()->format('d.m.Y H:i') }} Uhr. Dieses Dokument dient internen Tracking-Zwecken der Geschäftsführung.
    </div>

</body>
</html>
