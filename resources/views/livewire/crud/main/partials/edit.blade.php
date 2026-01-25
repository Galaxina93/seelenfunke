<div class="pt-4">

    <x-forms.form submit="saveOrUpdate()" :grid="true">

        @foreach($this->getModelFields('edit') as $attribute)
            @if (isset($this->config['fields'][$attribute]) && is_string($this->config['fields'][$attribute]['type']) && strpos($this->config['fields'][$attribute]['type'], '@') !== false)
                @php
                    [$class, $method] = explode('@', $this->config['fields'][$attribute]['type']);
                @endphp
                {!! $class::{$method}($this->record) !!}
            @else

                @include('livewire.crud.main.partials.field')

                @if ($errors->has('record.' . $attribute))
                    <div class="text-danger col-span-full">
                        {{ $errors->first('record.' . $attribute) }}
                    </div>
                @endif

            @endif
        @endforeach

        <div class="flex justify-content-around mt-4 col-span-full">

            @if($this->hasPermission('edit'))
                <x-forms.button title="Speichern" category="primary" type="submit"/>
            @endif

            <x-forms.button wireClick="toggleVisibility('edit', false)" title="Abbrechen" category="secondary"
                            type="button" class="ml-4"/>
        </div>

    </x-forms.form>

    <x-alerts.message sessionVariable="successUpdate"/>

    @if($errors->has('saveError'))
        <div class="alert alert-danger mt-4">
            {{ $errors->first('saveError') }}
        </div>
    @endif

</div>
