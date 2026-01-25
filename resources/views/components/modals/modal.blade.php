<div x-data="{ open: false }" @keydown.escape.window="open = false" {{ $attributes }}>

    <div @click="open = true" @click="showModal = true">
        {{ $trigger }}
    </div>

    {{-- full screen bg --}}
    <div x-show="open" class="fixed inset-0 flex items-center justify-center z-50 bg-black bg-opacity-60">

        {{-- modal window --}}
        <div @click.away="open = false" class="relative bg-white bg-opacity-90 w-full h-full p-6 sm:p-8 md:p-10 lg:p-12 xl:w-auto xl:h-auto xl:rounded-lg">

            {{ $slot }}

            {{-- Close Icon --}}
            <div @click="open = false" class="absolute top-1 right-1">
                <x-heroicon-m-x-mark class="cursor-pointer hover:text-primary w-6 h-6 text-gray-500 transform hover:scale-110 duration-100"/>
            </div>

        </div>

    </div>
</div>


{{--EXAMPLE--}}

{{--
<x-modals.modal>
    <x-slot name="trigger">
        <x-forms.button title="Aktivieren" category="primary" class="mt-5"/>
    </x-slot>

    <h2 class="text-xl font-semibold mb-4">Passwort bestätigen</h2>
    <p>
        Zu Ihrer Sicherheit bestätigen Sie bitte Ihr Passwort, um fortzufahren.
    </p>

    <x-forms.form submit="activate">
        <x-forms.password variable="password" class="col-span-4"/>
        <x-forms.button title="Bestätigen" category="primary" type="submit" class="col-span-4"/>
    </x-forms.form>

</x-modals.modal>--}}
