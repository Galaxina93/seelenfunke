<x-sections.profile-section-area title="Browser-Sitzungen" description="Verwalte deine aktiven Sitzungen auf anderen Browsern und Geräten.">
    <div class="mt-3 max-w-xl text-sm text-gray-600">
        <p>
            Falls erforderlich, kannst du dich von allen anderen Browser-Sitzungen auf allen deinen Geräten abmelden. Einige deiner letzten Sitzungen sind unten aufgeführt.
            diese Liste ist jedoch möglicherweise nicht vollständig. Wenn du das Gefühl hast, dass dein Konto kompromittiert wurde, solltest du auch dein Passwort aktualisieren.
        </p>
    </div>

    <div class="mt-5 space-y-6">
        @foreach ($this->getBrowserSessions($user) as $session)
            <div class="flex items-center mt-4">

                @if ($session->device_type === 'Desktop')
                    <x-heroicon-o-computer-desktop class="cursor-pointer hover:text-primary w-6 h-6 text-gray-500 transform hover:scale-110 duration-100"/>
                @elseif ($session->device_type === 'Mobile')
                    <x-heroicon-o-device-phone-mobile class="cursor-pointer hover:text-primary w-6 h-6 text-gray-500 transform hover:scale-110 duration-100"/>
                @endif

                <div class="ml-3">
                    <div class="text-sm text-gray-600">
                        {{ $session->user_agent }}
                    </div>

                    <div>
                        <div class="text-xs">
                            {{ $session->ip_address }},
                            @if ($loop->first)
                                <span class="text-green-400 font-semibold">Dieses Gerät</span>
                            @else
                                Last active {{ \Carbon\Carbon::createFromTimestamp($session->last_activity)->diffForHumans() }}
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <x-forms.form submit="deleteOtherSessions" :grid="true">
        <x-forms.button title="Andere Sitzungen abmelden" category="secondary" type="submit" class="col-span-4 mt-4"/>
        <x-alerts.message sessionVariable="success" class="col-span-full"/>
        <x-alerts.message sessionVariable="info" class="col-span-full"/>
    </x-forms.form>
</x-sections.profile-section-area>
