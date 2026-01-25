@if($showArchive)
    <div>{{ $this->config['crud_title'] ?? $modelName }} - Archiv</div>

    @if($this->hasPermission('archive'))
        <x-heroicon-o-archive-box-x-mark wire:click="toggleVisibility('archive', false)" class="cursor-pointer hover:text-primary w-6 h-6 text-gray-500 transform hover:scale-110 duration-100" />
    @endif

@elseif($showEdit)
    <div>{{ $this->config['crud_title'] ?? $modelName }} - Bearbeiten</div>
@elseif($showCreate)
    <div>{{ $this->config['crud_title'] ?? $modelName }} - Erstellen</div>
@else
    <div>{{ $this->config['crud_title'] ?? $modelName }}</div>

    @if($this->hasPermission('archive'))
        <x-heroicon-o-archive-box-arrow-down wire:click="toggleVisibility('archive', true)" class="cursor-pointer hover:text-primary w-6 h-6 text-gray-500 transform hover:scale-110 duration-100" />
    @endif

@endif
