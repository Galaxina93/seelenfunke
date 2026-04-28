{{-- HTML TREE --}}
@include('global.mails.partials.mail_html_tree', ['title' => 'Gutschrift / Storno - ' . $invoice->invoice_number])

<div class="container">

    {{-- LOGO --}}
    @include('global.mails.partials.mail_logo')

    {{-- ANSPRACHE --}}
    <h1>Hallo {{ $invoice->customer->first_name }},</h1>
    <p>wir haben soeben eine neue Gutschrift bzw. einen Stornobeleg für dich ausgestellt.</p>

    {{-- INHALT --}}
    <div style="margin-top: 30px; padding: 25px; background-color: #f8f9fa; border: 1px solid #e9ecef; border-radius: 8px;">
        <h3 style="margin-top: 0; color: #111827;">Belegübersicht</h3>
        <table style="width: 100%; border-collapse: collapse; font-size: 15px; color: #4b5563;">
            <tr>
                <td style="padding: 8px 0; border-bottom: 1px solid #e5e7eb;"><strong>Belegnummer:</strong></td>
                <td style="padding: 8px 0; border-bottom: 1px solid #e5e7eb; text-align: right;">{{ $invoice->invoice_number }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; border-bottom: 1px solid #e5e7eb;"><strong>Datum:</strong></td>
                <td style="padding: 8px 0; border-bottom: 1px solid #e5e7eb; text-align: right;">{{ $invoice->created_at->format('d.m.Y') }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; border-bottom: 1px solid #e5e7eb;"><strong>Betreff:</strong></td>
                <td style="padding: 8px 0; border-bottom: 1px solid #e5e7eb; text-align: right;">{{ $invoice->subject ?? 'Gutschrift / Rechnungskorrektur' }}</td>
            </tr>
            <tr>
                <td style="padding: 12px 0 0 0; color: #111827;"><strong>Gutschrift-Volumen:</strong></td>
                <td style="padding: 12px 0 0 0; text-align: right; color: #C5A059; font-weight: bold; font-size: 18px;">{{ number_format($invoice->total / 100, 2, ',', '.') }} €</td>
            </tr>
        </table>
    </div>

    <p style="margin-top: 30px;">Alle Details sowie die genauen Rechnungspositionen findest du im angehängten <strong>PDF-Dokument</strong>.</p>
    
    <p>Wenn du Fragen hast, melde dich jederzeit gerne bei uns!</p>

    {{--FOOTER--}}
    @include('global.mails.partials.mail_footer')

</div>

</body>
</html>
