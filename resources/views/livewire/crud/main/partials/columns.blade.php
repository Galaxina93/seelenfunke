@foreach($this->getModelFields('index') as $attribute)

    <th class="px-4 py-2">

        @if(isset($this->config['sortable']) && in_array($attribute, $this->config['sortable']))
            <button wire:click="sortBy('{{ $attribute }}')" class="flex items-center">
                {{ $this->config['fields'][$attribute]['translation'] ?? ucfirst($attribute) }}

                {{-- Sortierungs Icon (Pfeile) --}}
                <span class="ml-1">
                    @if($sortField === $attribute)
                        @if($this->config['sort_direction'] === 'asc')
                            &uarr;
                        @else
                            &darr;
                        @endif
                    @endif
                </span>

            </button>
        @else
            <div class="text-start">
                {{ $this->config['fields'][$attribute]['translation'] ?? ucfirst($attribute) }}
            </div>
        @endif

    </th>

@endforeach

<th class="px-4 py-2">Aktionen</th>
