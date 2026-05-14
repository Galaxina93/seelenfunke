{{-- HTML TREE --}}
@include('global.mails.partials.mail_html_tree', ['title' => $title])

<div class="container">

    {{-- LOGO --}}
    @include('global.mails.partials.mail_logo')

    {{-- ANSPRACHE --}}
    <h1>Hallo,</h1>

    <p>im Anhang dieser E-Mail findest du den angeforderten Bericht: <strong>{{ $title }}</strong>.</p>
    
    <p>Dieser Bericht wurde automatisch von deinem KI-System generiert.</p>

    {{-- FOOTER --}}
    @include('global.mails.partials.mail_footer')

</div>

</body>
</html>
