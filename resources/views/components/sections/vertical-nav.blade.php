{{-- FILE: resources\views\components\sections\vertical-nav.blade.php --}}
<nav class="flex flex-1 flex-col custom-scrollbar">
    <ul role="list" class="flex flex-1 flex-col gap-y-6">
        {{ $slot }}
    </ul>
</nav>
