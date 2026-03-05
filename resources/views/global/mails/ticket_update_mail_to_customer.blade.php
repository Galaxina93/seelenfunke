{{-- HTML TREE --}}
@include('global.mails.partials.mail_html_tree', ['title' => 'Neuigkeiten zu deinem Ticket'])

<div class="container">

    {{-- LOGO --}}
    @include('global.mails.partials.mail_logo')

    {{-- ANSPRACHE --}}
    <h1>Hallo {{ $customer->first_name ?? 'lieber Kunde' }},</h1>

    <p>es gibt Neuigkeiten zu deiner Support-Anfrage bei der Manufaktur der Magie.</p>

    <div style="margin-top: 20px; margin-bottom: 20px; padding: 15px; background-color: #f8f9fa; border-left: 4px solid #c5a059; border-radius: 4px;">
        <strong>Ticket:</strong> {{ $ticket->ticket_number }}<br>
        <strong>Betreff:</strong> {{ $ticket->subject }}
    </div>

    <p>Aus Gründen des Datenschutzes und der Sicherheit bitten wir dich, dich in dein Kundenkonto einzuloggen, um die Antwort unseres Teams zu lesen und gegebenenfalls direkt zu antworten.</p>

    {{-- CALL TO ACTION BUTTON --}}
    <div style="text-align: center; margin: 40px 0;">
        <a href="{{ route('login') }}" style="display: inline-block; padding: 14px 30px; background-color: #c5a059; color: #111111; text-decoration: none; font-weight: bold; border-radius: 6px; text-transform: uppercase; letter-spacing: 1px;">
            Jetzt einloggen & ansehen
        </a>
    </div>

    {{-- FOOTER --}}
    @include('global.mails.partials.mail_footer')

</div>

</body>
</html>
