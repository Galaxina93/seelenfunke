<x-sections.profile-section-area title="Account löschen" description="Lösche dein Konto dauerhaft.">
    <div class="mt-3 max-w-xl text-sm text-gray-600">
        <p>
            Sobald dein Konto gelöscht wurde, werden alle Ressourcen und Daten dauerhaft gelöscht.
            Bevor du dein Konto löschst, lade bitte alle Daten oder Informationen herunter, die du beibehalten möchtest.
        </p>
    </div>

    <div x-data="{ confirming: false }" class="mt-4">
        <x-forms.button
            x-show="!confirming"
            @click="confirming = true"
            title="Account löschen"
            category="danger"
            type="button"
        />

        <div x-show="confirming" class="mt-4 space-y-3">
            <p class="text-red-600 font-semibold">
                Bist du sicher, dass du deinen Account unwiderruflich löschen möchtest?
            </p>

            <x-forms.form submit="deleteAccount" :grid="true">
                <x-forms.button
                    title="Ja, Account löschen"
                    category="danger"
                    type="submit"
                    class="col-span-4"
                />
                <button type="button" @click="confirming = false" class="btn-secondary mt-2">
                    Abbrechen
                </button>
                <x-alerts.message sessionVariable="deleteAccount" class="col-span-full"/>
            </x-forms.form>
        </div>
    </div>

</x-sections.profile-section-area>
