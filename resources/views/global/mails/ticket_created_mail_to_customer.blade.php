{{-- HTML TREE --}}
@include('global.mails.partials.mail_html_tree', ['title' => 'Dein Ticket wurde eröffnet'])

<div class="container">

    {{-- LOGO --}}
    @include('global.mails.partials.mail_logo')

    {{-- ANSPRACHE --}}
    <h1>Hallo {{ $customer->first_name ?? 'lieber Kunde' }},</h1>

    <p>vielen Dank für deine Nachricht an die Manufaktur der Magie. Wir haben dein Ticket erfolgreich in unserem System erfasst!</p>

    <div style="margin-top: 20px; margin-bottom: 20px; padding: 15px; background-color: #f8f9fa; border-left: 4px solid #c5a059; border-radius: 4px;">
        <strong>Ticket:</strong> {{ $ticket->ticket_number }}<br>
        <strong>Betreff:</strong> {{ $ticket->subject }}
    </div>

    <p>Einer unserer Support-Magier wird sich in Kürze deines Anliegens annehmen und dir so schnell wie möglich antworten. Du erhältst automatisch eine weitere Benachrichtigung, sobald eine Antwort für dich vorliegt.</p>

    {{-- CALL TO ACTION BUTTON --}}
    <div style="text-align: center; margin: 40px 0;">
        <a href="{{ route('customer.support') }}" style="display: inline-block; padding: 14px 30px; background-color: #c5a059; color: #111111; text-decoration: none; font-weight: bold; border-radius: 6px; text-transform: uppercase; letter-spacing: 1px;">
            Zum Kundenkonto
        </a>
    </div>

    {{-- FOOTER --}}
    @include('global.mails.partials.mail_footer')

</div>

</body>
</html>
