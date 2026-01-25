<div>

    @php
        $partialsPath = 'livewire.crud.main.partials.'
    @endphp

    <div class="p-3 my-6 border-4 border-gray-300 border-opacity-20 rounded-lg">

        <div class="flex justify-between items-center font-bold text-2xl text-gray-400 text-opacity-40">

            @include($partialsPath . 'header')

        </div>

        @if($showArchive)
            @include($partialsPath . 'archive')
        @elseif($showEdit)
            @include($partialsPath . 'edit')
        @elseif($showCreate)
            @include($partialsPath . 'create')
        @else
            @include($partialsPath . 'index')
        @endif

    </div>

</div>
