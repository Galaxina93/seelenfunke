{{-- HTML TREE --}}
@include('global.mails.partials.mail_html_tree', ['title' => 'Du hast noch Artikel in deinem Warenkorb'])

<body>

<div class="container">

    {{-- LOGO --}}
    @include('global.mails.partials.mail_logo')

    {{-- ANSPRACHE --}}
    <h1>Du hast da etwas vergessen! 🛒</h1>
    <p>Hallo {{ $cart->customer->first_name ?? 'liebe(r) Kundin/Kunde' }},</p>

    <p>wir haben bemerkt, dass du bei deinem letzten Besuch noch Artikel in deinem Warenkorb gelassen hast. Konntest du deinen Einkauf nicht abschließen?</p>
    <p>Keine Sorge, wir haben deine Artikel sicher für dich aufbewahrt, damit du jederzeit genau dort weitermachen kannst, wo du aufgehört hast.</p>

    {{-- ARTIKEL LISTE --}}
    <div style="margin-top: 30px; margin-bottom: 30px; padding: 20px; background-color: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0;">
        <h3 style="font-size: 16px; border-bottom: 1px solid #e2e8f0; padding-bottom: 10px; margin-bottom: 15px; margin-top: 0;">In deinem Warenkorb wartet auf dich:</h3>
        
        <ul style="padding-left: 20px; margin-bottom: 0;">
            @foreach($cart->items as $item)
                <li style="margin-bottom: 8px;">
                    <strong>{{ $item->quantity }}x</strong> {{ $item->product->name ?? 'Produkt' }}
                </li>
            @endforeach
        </ul>
    </div>

    {{-- CALL TO ACTION --}}
    <div style="text-align: center; margin-top: 35px; margin-bottom: 35px;">
        <a href="{{ $cartLink }}" 
           style="background-color: #d1b464; color: #ffffff; text-decoration: none; padding: 14px 28px; border-radius: 6px; font-weight: bold; font-size: 15px; display: inline-block;">
            Zum Warenkorb zurückkehren
        </a>
    </div>

    <div style="margin-top: 15px; padding: 15px; background-color: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 6px; font-size: 13px; color: #166534; line-height: 1.5;">
        <strong>💡 Brauchst du Hilfe?</strong><br>
        Bitte antworte nicht auf diese E-Mail. Falls es ein Problem beim Checkout gab oder du noch Fragen zu den Artikeln hast, erstelle einfach ein Ticket in deinem Kundenbereich unter: <br>
        <a href="{{ config('app.url') }}/support" style="color: #16a34a; text-decoration: underline; font-weight: bold;">Zum Kundenservice</a>. Wir helfen dir gerne weiter!
    </div>

    {{-- FOOTER --}}
    @include('global.mails.partials.mail_footer')

</div>

</body>
</html>
