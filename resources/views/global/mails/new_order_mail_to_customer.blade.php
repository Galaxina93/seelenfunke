{{-- HTML TREE --}}
@include('global.mails.partials.mail_html_tree', [
    'title' => 'Bestellbestätigung'
])

<div class="container">

    {{-- LOGO --}}
    @include('global.mails.partials.mail_logo')

    {{-- ANSPRACHE --}}
    <h1>Vielen Dank, {{ $data['contact']['vorname'] }}!</h1>
    <p>Wir haben deine Bestellung <strong>#{{ $data['quote_number'] }}</strong> erhalten und bereiten diese nun mit viel Liebe für dich vor.</p>

    {{-- ARTIKEL LISTE --}}
    @include('global.mails.partials.mail_item_list')

    {{--PREISAUFSTELLUNG--}}
    @include('global.mails.partials.mail_price_list')

    {{-- DETAILS / ADRESSEN & KUNDENDATEN --}}
    @include('global.mails.partials.mail_customer_info', ['showContactCard' => true])

    {{--FOOTER--}}
    @include('global.mails.partials.mail_footer')

</div>

</body>
</html>
