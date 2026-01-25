@if ($errors->any())
    <div id="error-alert" class="alert alert-danger text-red-500">
        @foreach ($errors->all() as $error)
            {{ $error }}<br>
        @endforeach
    </div>
@endif
