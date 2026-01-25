<div>
    <div class="w-full bg-white rounded-lg shadow dark:border md:mt-0 sm:max-w-md xl:p-0 dark:bg-gray-800 dark:border-gray-700">
        <div class="p-6 space-y-4 md:space-y-6 sm:p-8">

            <h1 class="text-xl font-bold leading-tight tracking-tight text-gray-900 md:text-2xl dark:text-white">
                Passwort Zurücksetzen
            </h1>

            <span class="text-sm"> Gib deine E-Mail Adresse und dein neues Passwort ein. </span>

            <x-forms.form submit="submit" :grid="false">

                <x-forms.email class="mt-4"/>

                <x-forms.password title="Passwort" variable="password" class="mt-4"/>
                <x-forms.password title="Passwort Wiederholen" variable="passwordConfirm" class="mt-4"/>

                <x-forms.button title="Passwort Zurücksetzen" category="primary" type="submit" class="mt-4"/>

                <x-alerts.message sessionVariable="status"/>

                <x-alerts.errors/>

            </x-forms.form>

            @if (session()->has('error'))
                <p class="text-sm text-red-500">{{ session('error') }}</p>
            @endif

        </div>
    </div>
</div>
