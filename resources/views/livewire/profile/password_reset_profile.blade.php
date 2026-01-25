<x-sections.profile-section-area title="Passwort aktualisieren" description="Stellen Sie sicher, dass Ihr Konto ein langes, zufälliges Passwort verwendet, um sicher zu sein.">

    <x-forms.form submit="updatePassword" :grid="true">
        <x-forms.password title="Aktuelles Passwort" variable="currentPassword" class="col-span-4"/>
        <x-forms.password title="Neues Passwort" variable="newPassword" class="col-span-4"/>
        <x-forms.password title="Neues Passwort Wiederholen" variable="repeatNewPassword" class="col-span-4"/>
        <x-forms.button title="Speichern" category="primary" type="submit" class="col-span-4"/>

        <x-alerts.message sessionVariable="password-updated" class="col-span-full"/>

    </x-forms.form>

</x-sections.profile-section-area>
