<div style="--theme-color: {{ $this->themeColorHex }}; --theme-color-5: {{ $this->themeColorHex }}0D; --theme-color-10: {{ $this->themeColorHex }}1A; --theme-color-15: {{ $this->themeColorHex }}26; --theme-color-20: {{ $this->themeColorHex }}33; --theme-color-30: {{ $this->themeColorHex }}4D; --theme-color-40: {{ $this->themeColorHex }}66; --theme-color-50: {{ $this->themeColorHex }}80; --theme-color-70: {{ $this->themeColorHex }}B3;" class="min-h-screen bg-transparent font-sans text-gray-300" x-data="{ showPreview: true }">
    @if($viewMode === 'list')
        @include('livewire.shop.product.partials._list')
    @elseif($viewMode === 'edit')
        @include('livewire.shop.product.partials._edit')
    @endif
</div>
