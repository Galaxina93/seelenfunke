<div @if(isset($class)) class="{{ $class }}" @endif>
    @if (session($sessionVariable))
        <div id="success-alert" class="alert alert-success text-green-400">
            {{ session($sessionVariable) }}
        </div>
    @endif
</div>
