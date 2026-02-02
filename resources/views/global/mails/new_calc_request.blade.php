{{-- HTML TREE --}}
@include('global.mails.partials.mail_html_tree')

<div class="container">

    {{-- HEADER --}}
    <div class="header">
        <img src="{{ asset('images/projekt/logo/mein-seelenfunke-logo.png') }}" alt="Mein Seelenfunke" class="logo">
    </div>

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

    {{-- ARTIKEL LISTE --}}
    <h3 style="font-size: 14px; color: #888; text-transform: uppercase; margin-bottom: 10px;">Angefragte Produkte</h3>
    @include('global.mails.partials.mail_bought_products_list')

    {{-- PREISAUFSTELLUNG --}}
    @include('global.mails.partials.mail_price_list')

    {{-- DETAILS / ADRESSEN & KUNDENDATEN --}}
    @include('global.mails.partials.mail_customer_info', ['showContactCard' => true])

    {{-- FOOTER --}}
    @include('global.mails.partials.mail_footer')

</div>

</body>
</html>
