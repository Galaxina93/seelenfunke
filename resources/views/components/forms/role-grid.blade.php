<div class="{{ $class ?? '' }}">
    <div class="mt-4 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @foreach($data['roles'] as $roleArray)
            @php $role = (object)$roleArray; @endphp

            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow p-4 transition hover:shadow-lg">
                <h3 class="text-lg font-semibold text-primary mb-3">{{ $role->name }}</h3>

                <div class="flex flex-wrap">
                    @foreach($role->permissions as $permissionArray)
                        @php $permission = (object)$permissionArray; @endphp

                        <span class="flex items-center justify-between px-3 py-1 text-primary bg-primary/10 rounded-full m-1 text-sm hover:bg-primary/20 transition">
                            <span>{{ $permission->name }}</span>
                            <button wire:click="deletePermissionFromRole('{{ $role->id }}', '{{ $permission->id }}')"
                                    class="ml-2 text-primary hover:text-red-600 transition"
                                    title="Löschen">
                                <x-heroicon-m-trash class="h-4 w-4"/>
                            </button>
                        </span>
                    @endforeach
                </div>

                <div class="mt-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">
                        Berechtigung hinzufügen:
                    </label>

                    <div class="flex items-center gap-2">
                        {{-- Die Select-Box --}}
                        <select wire:model.live="selectedPermission.{{ $role->id }}" class="flex-1 rounded-lg border-gray-300 dark:bg-gray-700 dark:text-white focus:border-primary focus:ring-primary text-sm">
                            <option value="">Wähle eine Berechtigung...</option>
                            @foreach($data['permissions'] as $permissionArray)
                                @php $permission = (object)$permissionArray; @endphp
                                <option value="{{ $permission->id }}">{{ $permission->name }}</option>
                            @endforeach
                        </select>

                        {{-- Lade-Indikator für die Select-Box (erscheint bei Auswahl) --}}
                        <div wire:loading wire:target="selectedPermission.{{ $role->id }}">
                            <svg class="animate-spin h-5 w-5 text-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </div>

                        {{-- Der Button mit Ladezustand --}}
                        <x-forms.button
                            wire:click="addPermissionToRole('{{ $role->id }}')"
                            wire:loading.attr="disabled"
                            wire:target="addPermissionToRole('{{ $role->id }}')"
                            title="Hinzufügen"
                            category="primary"
                            class="text-sm">

                            {{-- Dieser Span wird angezeigt, wenn NICHT geladen wird --}}
                            <span wire:loading.remove wire:target="addPermissionToRole('{{ $role->id }}')">
                                Hinzufügen
                            </span>

                            {{-- Dieser Span wird angezeigt, WÄHREND geladen wird --}}
                            <span wire:loading wire:target="addPermissionToRole('{{ $role->id }}')">
                                Wird geladen...
                            </span>
                        </x-forms.button>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
