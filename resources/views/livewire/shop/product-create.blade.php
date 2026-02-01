<div class="min-h-screen bg-gray-50 font-sans text-gray-900" x-data>

    {{-- ========================================== --}}
    {{-- ANSICHT: LISTE (Übersicht aller Produkte)  --}}
    {{-- ========================================== --}}
    @if($viewMode === 'list')
        @include('livewire.shop.product-partials._list')
    {{-- ========================================== --}}
    {{-- ANSICHT: EDIT (Bearbeitungsmodus)          --}}
    {{-- ========================================== --}}
    @elseif($viewMode === 'edit')
        @include('livewire.shop.product-partials._edit')

    @endif
</div>
