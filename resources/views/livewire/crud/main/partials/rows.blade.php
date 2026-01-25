@forelse($this->data as $item)
    <tr @if($showArchive) class="text-red-600 relative line-through hover:bg-gray-100" @endif class="hover:bg-gray-100">

        @foreach($this->getModelFields('index') as $attribute)
            <td class="border px-4 py-2">
                @if (
                    isset($this->config['fields'][$attribute]['closure_function']) &&
                    !empty($this->config['fields'][$attribute]['closure_function'])
                )
                    @php
                        [$class, $method] = explode('@', $this->config['fields'][$attribute]['closure_function']);
                    @endphp
                    {!! $class::{$method}($item) !!}
                @else
                    @if (isset($this->config['fields'][$attribute]['relation']))
                        {{ $item->{$this->config['fields'][$attribute]['relation']}->{$attribute} ?? '' }}
                    @else
                        {{ $item->{$attribute} ?? '' }}
                    @endif
                @endif
            </td>
        @endforeach

        <td class="border px-4 py-2">
            <div class="flex justify-content-around">
                @if($showArchive)
                    @if($this->hasPermission('archive'))
                        <span title="Wiederherstellen">
                            <x-heroicon-o-arrow-path wire:click="restoreItem('{{ $item->id }}')" class="cursor-pointer hover:text-primary w-6 h-6 text-gray-500 transform hover:scale-110 duration-100" />
                        </span>
                    @endif
                @else
                    @if($this->hasPermission('edit'))
                        <span title="Bearbeiten">
                            <x-heroicon-o-pencil-square wire:click="edit('{{ $item->id }}')" class="cursor-pointer hover:text-primary w-6 h-6 text-gray-500 transform hover:scale-110 duration-100" />
                        </span>
                    @endif
                    @if($this->hasPermission('delete'))
                        <span title="Archivieren">
                            <x-heroicon-o-archive-box wire:click="confirmDelete('{{ $item->id }}')" class="cursor-pointer hover:text-primary w-6 h-6 text-gray-500 transform hover:scale-110 duration-100" />
                        </span>
                    @endif
                    @if($this->hasPermission('force_delete'))
                        <span title="Löschen">
                            <x-heroicon-o-trash wire:click="confirmForceDelete('{{ $item->id }}')" class="cursor-pointer hover:text-primary w-6 h-6 text-gray-500 transform hover:scale-110 duration-100" />
                        </span>
                    @endif
                @endif
            </div>
        </td>
    </tr>
@empty
    <tr>
        <td colspan="{{ count($this->getModelFields('index')) + 1 }}" class="text-center py-4">
            Keine Daten gefunden.
        </td>
    </tr>
@endforelse
