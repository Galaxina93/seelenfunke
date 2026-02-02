{{-- HTML TREE --}}
@include('global.mails.partials.mail_html_tree')

<div class="container">

    {{-- HEADER --}}
    <div class="header">
        <img src="{{ asset('images/projekt/logo/mein-seelenfunke-logo.png') }}" alt="Mein Seelenfunke" class="logo">
    </div>

    {{-- ANSPRACHE --}}
    <h1>Neue Bestellung eingegangen! 🥂
        @if(!empty($data['express']))
            <span class="badge-express">EXPRESS</span>
        @endif
    </h1>
    <p>Hallo Alina, herzlichen Glückwunsch! Ein Kunde hat gerade eine neue Bestellung aufgegeben. Hier findest du alle Details zur Abwicklung:</p>

    {{-- ARTIKEL LISTE --}}
    @include('global.mails.partials.mail_bought_products_list')

    {{--PREISAUFSTELLUNG--}}
    @include('global.mails.partials.mail_price_list')

    {{-- DETAILS / ADRESSEN & KUNDENDATEN --}}
    @include('global.mails.partials.mail_customer_info', ['showContactCard' => true])

    {{--FOOTER--}}
    @include('global.mails.partials.mail_footer')

</div>

</body>
</html>
