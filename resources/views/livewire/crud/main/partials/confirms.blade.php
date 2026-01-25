@if($confirmingDelete)
    <div>
        <div class="mb-4 text-center">
            Sind Sie sicher, dass Sie diesen Datensatz archivieren möchten?
        </div>

        <div class="flex justify-center">

            <x-forms.button wireClick="delete" title="Archivieren" category="danger" type="button" class="mr-4"/>
            <x-forms.button wireClick="$toggle('confirmingDelete')" title="Abbrechen" category="secondary"
                            type="button"/>

        </div>
    </div>
@endif
@if($confirmingForceDelete)
    <div>
        <div class="mb-4 text-center">
            Sind Sie sicher, dass Sie diesen Datensatz löschen möchten?
        </div>

        <div class="flex justify-center">

            <x-forms.button wireClick="forceDelete" title="Löschen" category="danger" type="button" class="mr-4"/>
            <x-forms.button wireClick="$toggle('confirmingForceDelete')" title="Abbrechen" category="secondary"
                            type="button"/>

        </div>
    </div>
@endif
