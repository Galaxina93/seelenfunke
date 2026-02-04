{{-- HTML TREE --}}
@include('global.mails.partials.mail_html_tree', [
    'title' => 'Neue Kalkulationsanfrage'
])

<div class="container">

    {{-- LOGO --}}
    @include('global.mails.partials.mail_logo')

    {{-- ANSPRACHE --}}
    <h1>Neue Angebotsanfrage! ✉️
        @if(!empty($data['express']))
            <span class="badge-express">EXPRESS</span>
        @endif
    </h1>
    <p>Hallo Alina, es ist eine neue Anfrage über den <strong>Calculator</strong> eingegangen. Der Kunde hat ein automatisches Angebot erhalten, aber du solltest die Details kurz prüfen:</p>

    <div style="background: #fffbeb; border: 1px solid #fde68a; padding: 15px; border-radius: 8px; margin-bottom: 25px;">
        <p style="margin: 0; color: #92400e; font-size: 13px;">
            <strong>Info:</strong> Dies ist noch keine feste Bestellung, sondern eine Preiskalkulation/Anfrage (#{{ $data['quote_number'] }}).
        </p>
    </div>


    {{-- KUNDENAUSWAHL --}}
    @include('global.mails.partials.mail_item_list')

    {{-- PREISAUFSTELLUNG --}}
    @include('global.mails.partials.mail_price_list')

    {{-- DETAILS / ADRESSEN & KUNDENDATEN --}}
    @include('global.mails.partials.mail_customer_info', ['showContactCard' => true])

    {{-- FOOTER --}}
    @include('global.mails.partials.mail_footer')

</div>

</body>
</html>
