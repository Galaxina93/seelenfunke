<!DOCTYPE html>
<html lang="de">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Notfall- und Todesfallplan</title>
    <style>
        @page { size: A4 portrait; margin: 15mm 15mm 20mm 15mm; }
        body { font-family: sans-serif; font-size: 11px; color: #333333; margin: 0; padding: 0; line-height: 1.5; background-color: #ffffff; }

        h1, h2, h3, h4 { color: #000000; margin-top: 20px; margin-bottom: 10px; }
        h1 { font-size: 18px; border-bottom: 1px solid #cccccc; padding-bottom: 5px; color: #b91c1c; }
        h2 { font-size: 15px; border-bottom: 1px solid #eeeeee; padding-bottom: 4px; color: #7f1d1d; }
        h3 { font-size: 13px; }
        p { margin-bottom: 10px; }
        
        .header { margin-bottom: 30px; padding-bottom: 10px; border-bottom: 2px solid #b91c1c; }
        .doc-title { font-size: 22px; font-weight: bold; color: #b91c1c; margin: 0 0 5px 0; }
        .doc-meta { font-size: 9px; color: #666666; margin-top: 10px; }

        table { width: 100%; border-collapse: collapse; margin-bottom: 15px; margin-top: 10px; page-break-inside: avoid; }
        th, td { border: 1px solid #dddddd; padding: 6px 8px; text-align: left; font-size: 10px; }
        th { background-color: #fef2f2; font-weight: bold; color: #991b1b; }
        
        ul, ol { margin-bottom: 15px; padding-left: 20px; }
        li { margin-bottom: 5px; }
        strong { color: #000000; }
        
        .page-break { page-break-after: always; }
        
        #footer { position: fixed; bottom: -15mm; left: 0px; right: 0px; height: 10mm; font-size: 8px; color: #666666; border-top: 1px solid #cccccc; padding-top: 4px; }
        .footer-table { width: 100%; border-collapse: collapse; border: none; }
        .footer-table td { border: none; padding: 0; background: transparent; }
        .page-number:after { content: "Seite " counter(page); font-weight: bold; }
        
        .box { border: 1px solid #fee2e2; background-color: #fffaf0; padding: 10px; border-radius: 4px; margin-bottom: 15px; }
        .box-title { font-weight: bold; color: #991b1b; margin-bottom: 5px; font-size: 11px; text-transform: uppercase; }
        .box-value { font-size: 12px; }
    </style>
</head>
<body>

<div id="footer">
    <table class="footer-table">
        <tr>
            <td style="text-align: left; width: 33%;">Notfall- & Todesfallplan</td>
            <td style="text-align: center; width: 33%; color: #000000;" class="page-number"></td>
            <td style="text-align: right; width: 33%;">Stand: {{ $date }}</td>
        </tr>
    </table>
</div>

<div class="header">
    <div class="doc-title">Notfall- und Todesfallplan</div>
    <div class="doc-meta">
        <strong>Firma:</strong> {{ $settings['company_name'] ?? 'Mein Seelenfunke' }}<br>
        <strong>Inhaber(in):</strong> {{ $settings['owner_proprietor'] ?? 'Alina Steinhauer' }}<br>
        <strong>Erstellt am:</strong> {{ $date }}<br>
        <strong style="color: #b91c1c;">WICHTIG: Dieses Dokument enthält sensible Informationen für Hinterbliebene und Vertrauenspersonen.</strong>
    </div>
</div>

<div class="content">

    <h2>Phase 1: Sofortmaßnahmen (Digitale Zugänge)</h2>
    <p>Um Zugriff auf die gesamten Geschäftsdaten, Konten und Abonnements zu erhalten, wird die zentrale Passwort-Datenbank (KeePass) benötigt.</p>
    
    <div class="box">
        <div class="box-title">Ort der KeePass-Datenbank</div>
        <div class="box-value">{{ $settings['emergency_keepass_location'] ?? 'Keine Angabe' }}</div>
    </div>
    
    <div class="box">
        <div class="box-title">Ort des Master-Passworts</div>
        <div class="box-value">{{ $settings['emergency_master_password_location'] ?? 'Keine Angabe' }}</div>
    </div>
    
    <div class="box">
        <div class="box-title">Hardware PINs (Handy / PC / Tablet)</div>
        <div class="box-value">{{ $settings['emergency_hardware_pins'] ?? 'Keine Angabe' }}</div>
    </div>

    <h2>Phase 2: Das Geschäft absichern & Kontakte</h2>
    <p>Das Geschäft muss bei längerer Abwesenheit oder im Todesfall umgehend pausiert oder abgewickelt werden. Hierzu sind folgende Kontakte zu informieren:</p>
    
    <table style="width:100%">
        <tr>
            <th style="width: 30%">Rolle</th>
            <th>Kontaktinformationen</th>
        </tr>
        <tr>
            <td><strong>Familie / Notfallkontakt</strong></td>
            <td>{{ $settings['emergency_contact_family'] ?? 'Jan Steinhauer, Kerstin Steinhauer, Tim Steinhauer' }}</td>
        </tr>
        <tr>
            <td><strong>Notar / Nachlassverwalter</strong></td>
            <td>{{ $settings['emergency_contact_notary'] ?? 'Keine Angabe' }}</td>
        </tr>
        <tr>
            <td><strong>Steuerberater</strong></td>
            <td>{{ $settings['emergency_contact_tax_advisor'] ?? 'Keine Angabe' }}</td>
        </tr>
    </table>

    <div class="page-break"></div>

    <h2>Phase 3: Finanzielle Verpflichtungen & Verträge</h2>
    <p>Die folgende Liste wurde live aus der Buchhaltungs-Software generiert. Sie enthält alle aktuell laufenden Fixkosten, Abonnements, Serverkosten und Versicherungen. Diese müssen einzeln geprüft und ggf. gekündigt oder umgeschrieben werden.</p>

    @if(isset($groups) && count($groups) > 0)
        @foreach($groups as $group)
            <h3>{{ $group->name }}</h3>
            <table>
                <thead>
                    <tr>
                        <th style="width: 30%">Titel / Anbieter</th>
                        <th style="width: 25%">Intervall</th>
                        <th style="width: 25%">Kosten</th>
                        <th style="width: 20%">Aktion (Zum Abhaken)</th>
                    </tr>
                </thead>
                <tbody>
                    @if($group->items->count() > 0)
                        @foreach($group->items as $item)
                        <tr>
                            <td>
                                <strong>{{ $item->name }}</strong>
                                @if($item->provider_company)
                                    <br><span style="color: #666; font-size: 9px;">Anbieter: {{ $item->provider_company }}</span>
                                @endif
                            </td>
                            <td>
                                @if($item->interval_months == 1) Monatlich 
                                @elseif($item->interval_months == 3) Quartalsweise
                                @elseif($item->interval_months == 6) Halbjährlich
                                @elseif($item->interval_months == 12) Jährlich
                                @else {{ $item->interval_months }} Monate @endif
                            </td>
                            <td>{{ number_format($item->amount, 2, ',', '.') }} €</td>
                            <td>[ ] Kündigen<br>[ ] Übernehmen</td>
                        </tr>
                        @endforeach
                    @else
                        <tr>
                            <td colspan="4" style="text-align: center; color: #999;">Keine aktiven Kosten in dieser Kategorie.</td>
                        </tr>
                    @endif
                </tbody>
            </table>
        @endforeach
    @else
        <p style="color: #999; font-style: italic;">Es wurden keine Buchhaltungsdaten gefunden oder die Fixkosten sind leer.</p>
    @endif

    <div class="page-break"></div>

    <h2>Phase 4: Standard To-Dos & Checkliste</h2>
    <p>Diese Liste enthält die wichtigsten organisatorischen Schritte im Trauerfall:</p>
    
    <ul>
        <li><strong>Sterbeurkunde beantragen:</strong> Wird für alle weiteren rechtlichen Schritte, Kündigungen und Bankgeschäfte zwingend im Original benötigt.</li>
        <li><strong>Banken informieren:</strong> Wichtig, damit keine unautorisierten Lastschriften erfolgen. Vorsicht: Oft werden Konten gesperrt, bis ein Erbschein vorliegt, Daueraufträge laufen aber meist weiter.</li>
        <li><strong>Shop & Gewerbe pausieren:</strong> Laufende E-Commerce Systeme wie "Mein Seelenfunke" in den Wartungsmodus setzen, damit keine neuen unbezahlten oder ungelieferten Bestellungen auflaufen.</li>
        <li><strong>Rückerstattungen:</strong> Offene, aber nicht versendete Bestellungen über den Shop oder Zahlungsdienstleister (PayPal, Stripe) zurückerstatten.</li>
        <li><strong>Steuerberater kontaktieren:</strong> Eine Betriebsaufgabe oder Übergabe zieht oft sofortige steuerliche Fristen nach sich.</li>
        <li><strong>Social Media:</strong> Accounts (Instagram, Facebook etc.) in den Gedenkzustand versetzen oder löschen lassen. Ggf. ein letztes Posting absetzen, um Kunden zu informieren.</li>
        <li><strong>E-Mail-Postfächer:</strong> Auto-Responder (Abwesenheitsnotiz) einrichten: <em>"Der Geschäftsbetrieb ruht aktuell aufgrund eines Trauerfalls."</em></li>
        <li><strong>Versicherungen informieren:</strong> Insbesondere Lebensversicherungen und Unfallversicherungen haben oft eine strikte Meldefrist (häufig 24 bis 72 Stunden!).</li>
    </ul>

    @if(isset($groups) && count($groups) > 0)
        @foreach($groups as $group)
            @foreach($group->items as $item)
                <div class="page-break"></div>
                <div style="font-size: 11pt; line-height: 1.5; padding-top: 20px;">
                    
                    @php
                        $senderName = $settings['owner_proprietor'] ?? 'Keine Angabe';
                        $senderStreetAndHouse = $settings['company_street'] ?? '';
                        $senderZipAndCity = ($settings['company_zip'] ?? '') . ' ' . ($settings['company_city'] ?? '');
                        $ownerPhone = $settings['company_phone'] ?? '';
                        $ownerEmail = $settings['company_email'] ?? '';
                    @endphp

                    <!-- Empfänger -->
                    <div style="margin-top: 40px; margin-bottom: 40px;">
                        <strong>{{ $item->provider_company ?? $item->name }}</strong><br>
                        @if($item->provider_street)
                            {{ $item->provider_street }} {{ $item->provider_house_number }}<br>
                        @endif
                        @if($item->provider_zip || $item->provider_city)
                            {{ $item->provider_zip }} {{ $item->provider_city }}
                        @endif
                    </div>

                    <!-- Info-Block rechtsbündig -->
                    <div style="text-align: right; margin-bottom: 30px;">
                        <strong>Absender:</strong><br>
                        {{ $senderName }}<br>
                        {{ $senderStreetAndHouse }}<br>
                        {{ $senderZipAndCity }}<br><br>
                        @if($item->contract_number)
                            <strong>Vertragsnummer:</strong> {{ $item->contract_number }}<br>
                        @endif
                        Datum: {{ $date }}
                    </div>

                    <h1 style="color: #000; border-bottom: none; padding-bottom: 0;">Sonderkündigung im Todesfall - Vertrag: "{{ $item->name }}"</h1>

                    <p>Sehr geehrte Damen und Herren,</p>

                    <p>
                        hiermit kündige ich den oben genannten Vertrag (ggf. Vertragsnummer: 
                        @if($item->contract_number) <strong>{{ $item->contract_number }}</strong> @else _________________ @endif)
                        sowie alle damit verbundenen Zusatzleistungen <strong>außerordentlich aufgrund des Versterbens der Vertragsinhaberin ({{ $senderName }})</strong> zum nächstmöglichen Zeitpunkt.
                    </p>

                    <p>
                        Die Sterbeurkunde liegt diesem Schreiben in Kopie bei.
                    </p>

                    <p>
                        Mit Wirksamwerden der Kündigung erlischt gleichzeitig eine etwaig erteilte Einzugsermächtigung zum Einzug der fälligen Beträge per Lastschrift.
                    </p>

                    <p>
                        Bitte senden Sie mir innerhalb der nächsten 14 Tage eine schriftliche Bestätigung dieser Kündigung unter Angabe des Beendigungszeitpunktes zu.
                    </p>

                    <p>Vielen Dank für Ihr Verständnis in dieser schweren Zeit.</p>
                    <p>Mit freundlichen Grüßen</p>

                    <div style="margin-top: 50px;">
                        _________________________________________<br>
                        Unterschrift ({{ $settings['emergency_contact_family'] ?? 'Jan Steinhauer, Kerstin Steinhauer, Tim Steinhauer' }})<br>
                        <span style="font-size: 9px; color: #666;">in Vertretung für die Verstorbene / den Nachlass</span>
                    </div>
                </div>
            @endforeach
        @endforeach
    @endif

</div>

</body>
</html>
