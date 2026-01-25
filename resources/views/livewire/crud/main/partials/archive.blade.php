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

<div class="mt-4">
    {{ $this->data->links('components.pagination.pagination-links') }}
</div>
