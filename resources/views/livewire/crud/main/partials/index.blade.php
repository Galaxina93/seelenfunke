<x-forms.search class="my-4"/>

<div class="overflow-x-auto">
    <table class="table-auto w-full">
        <thead>
        <tr>

            @include('livewire.crud.main.partials.columns')

        </tr>
        </thead>
        <tbody>

            @include('livewire.crud.main.partials.rows')

        </tbody>
    </table>
</div>

@if($this->hasPermission('create'))
    <div class="flex justify-center">
        <span title="{{ $this->config['create_btn_name'] ?? 'Anlegen' }}">
            <x-forms.button wireClick="toggleVisibility('create', true)" title="{{ $this->config['create_btn_name'] ?? 'Anlegen' }}" category="primary"
                            type="button" class="my-4"/>
        </span>
    </div>
@endif

<div class="flex justify-center mt-4">
    <div>
        {{ $this->data->links('components.pagination.pagination-links') }}
    </div>
</div>

@include('livewire.crud.main.partials.confirms')
