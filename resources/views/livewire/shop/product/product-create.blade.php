<div class="min-h-screen bg-transparent font-sans text-gray-300" x-data>

    {{-- ========================================== --}}
    {{-- ANSICHT: LISTE (Übersicht aller Produkte)  --}}
    {{-- ========================================== --}}
    @if($viewMode === 'list')
        @include('livewire.shop.product.partials._list')

        {{-- ========================================== --}}
        {{-- ANSICHT: EDIT (Bearbeitungsmodus)          --}}
        {{-- ========================================== --}}
    @elseif($viewMode === 'edit')
        @include('livewire.shop.product.partials._edit')

    @endif
</div>
