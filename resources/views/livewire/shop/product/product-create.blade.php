<div class="min-h-screen bg-transparent font-sans text-gray-300" x-data="{ showPreview: true }">
    @if($viewMode === 'list')
        @include('livewire.shop.product.partials._list')
    @elseif($viewMode === 'edit')
        @include('livewire.shop.product.partials._edit')
    @endif
</div>
