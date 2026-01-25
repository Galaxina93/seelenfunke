<x-sections.profile-section-area title="2-Faktor-Authentifizierung" description="Erhöhen Sie die Sicherheit Ihres Kontos.">

    @if($twoFactorActive)
        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-400">
            Die Zwei-Faktor-Authentifizierung ist aktiviert
        </h3>

        <div class="mt-3 max-w-xl text-sm text-gray-600">
            <p>
                Jetzt wirst du während der Authentifizierung zur Eingabe eines sicheren, zufälligen Tokens aufgefordert. Du kannst diesen Token über die Google Authenticator-Anwendung deines Smartphones abrufen.
            </p>
        </div>

        @if(isset($qrCodeSvg))
            <p class="font-semibold py-6">
                Scannen Sie den folgenden QR-Code mit der Authenticator-Anwendung Ihres Smartphones.
            </p>

            <div>
                {!! $qrCodeSvg !!}
            </div>
        @endif

        <div class="mt-4 max-w-xl text-sm text-gray-600">
            <p class="font-semibold">
                Speichere diese Wiederherstellungscodes in einem sicheren Passwort-Manager. Sie können verwendet werden, um den Zugang zu deinem Konto wiederherzustellen, wenn dein Zwei-Faktor-Authentifizierungsgerät verloren geht.
            </p>
        </div>

        @if($twoFactorActive)
            <div x-data="{ showCodes: false }">

                <div class="flex mt-5">
                    <button x-ref="toggleButton" @click="showCodes = !showCodes; $refs.toggleButton.innerText = showCodes ? 'Codes ausblenden' : 'Codes anzeigen'" class="btn-primary mr-3">
                        Codes anzeigen
                    </button>

                    <x-forms.button title="Deaktivieren" category="danger" wireClick="deActivate"/>
                </div>


                <div x-show="showCodes">

                    <div class="grid gap-1 max-w-xl mt-4 px-4 py-4 font-mono text-sm bg-gray-100 rounded-lg">
                        @foreach (json_decode(decrypt($this->user->profile->two_factor_recovery_codes), true) as $code)
                            <div>{{ $code }}</div>
                        @endforeach
                    </div>

                    <button wire:click="generateRecoveryCodes" class="btn-secondary mt-5">
                        Codes neu generieren
                    </button>
                </div>
            </div>
        @endif

    @else

        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-400">
            Die Zwei-Faktor-Authentifizierung ist nicht aktiviert
        </h3>

        <div class="mt-3 max-w-xl text-sm text-gray-600">
            <p>
                Wenn die Zwei-Faktor-Authentifizierung aktiviert ist, werden Sie während des Logins zur Eingabe eines sicheren, zufälligen Tokens aufgefordert. Sie können dieses Token über die Google Authenticator-Anwendung Ihres Smartphones abrufen.
            </p>
        </div>

        @if($confirmPasswordOpener)
            <h2 class="text-xl font-semibold mb-4 mt-8">Passwort bestätigen</h2>
            <p>
                Zu Ihrer Sicherheit bestätigen Sie bitte Ihr Passwort, um fortzufahren.
            </p>

            <x-forms.form submit="activate" :grid="true">
                <x-forms.password variable="password" class="col-span-4"/>
                <x-forms.button title="Bestätigen" category="primary" type="submit" class="col-span-4"/>
            </x-forms.form>
        @else
            <x-forms.button title="Aktivieren" category="primary" class="mt-5" wireClick="confirmPassword"/>
        @endif

    @endif

</x-sections.profile-section-area>
