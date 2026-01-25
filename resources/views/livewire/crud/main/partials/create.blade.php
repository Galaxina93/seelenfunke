<div class="pt-4">
    <x-forms.form submit="saveOrUpdate()" :grid="true">
        @foreach($this->config['fields'] as $attribute => $field)
            @if(
                ($showCreate && (!isset($field['hide_on_create']) || !$field['hide_on_create'])) ||
                ($showEdit && (!isset($field['hide_on_update']) || !$field['hide_on_update']))
            )

                @include('livewire.crud.main.partials.field')

                @if ($errors->has('record.' . $attribute))
                    <div class="text-danger col-span-full">
                        {{ $errors->first('record.' . $attribute) }}
                    </div>
                @endif

            @endif
        @endforeach

        <div class="flex justify-content-around mt-4 col-span-full">

            @if($this->hasPermission('create'))
                <x-forms.button title="Speichern" category="primary" type="submit"/>
            @endif
            <x-forms.button wireClick="toggleVisibility('create', false)" title="Abbrechen" category="secondary"
                            type="button" class="ml-4"/>
        </div>

    </x-forms.form>

    <x-alerts.message sessionVariable="successCreate"/>

    @if($errors->has('saveError'))
        <div class="alert alert-danger mt-4">
            {{ $errors->first('saveError') }}
        </div>
    @endif

</div>

