<x-sections.profile-section-area title="Profil Information" description="Aktualisiere dein Profil, um sicherzustellen, dass alle Informationen auf dem neuesten Stand sind.">

    <x-forms.form submit="saveProfile" :grid="true">

        <x-forms.profile-photo :user="$user" class="col-span-full"/>

        <x-forms.input      title="Vorname"         variable="firstName"    type="text" class="col-span-full md:col-span-3"     required/>
        <x-forms.input      title="Nachname"        variable="lastName"     type="text" class="col-span-full md:col-span-3"     required/>

        <x-forms.input      title="E-Mail"          variable="email"        type="email" class="col-span-full md:col-span-4"    required/>
        <x-forms.input      title="Telefon"         variable="phoneNumber"  type="text" class="col-span-full md:col-span-2"/>
        <x-forms.input      title="Straße"          variable="street"       type="text" class="col-span-full md:col-span-3"     required/>
        <x-forms.input      title="Haus Nummer"     variable="houseNumber"  type="text" class="col-span-full md:col-span-2"     required/>

        <x-forms.input      title="Postleitzahl"    variable="postal"       type="text" class="col-span-full md:col-span-2"     required/>
        <x-forms.input      title="Ort"             variable="city"         type="text" class="col-span-full md:col-span-4"     required/>
        <x-forms.input      title="Webseite"        variable="url"          type="text" class="col-span-full md:col-span-4"/>
        <x-forms.textarea   title="Über mich"       variable="about"        class="col-span-full"/>

        <x-forms.button     title="Speichern" category="primary"            type="submit" class="col-span-full md:col-span-1"/>

        <x-alerts.message sessionVariable="message" class="col-span-full md:col-span-full"/>

    </x-forms.form>

</x-sections.profile-section-area>
