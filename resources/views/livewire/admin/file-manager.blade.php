<div
    x-data="{ message: '', show: false }"
    x-on:notify-uploaded.window="message = 'Dateien erfolgreich hochgeladen.'; show = true; setTimeout(() => show = false, 4000)"
    x-on:notify-deleted.window="message = 'Ausgewählte Elemente wurden gelöscht.'; show = true; setTimeout(() => show = false, 4000)"
    x-on:notify-folder-created.window="message = 'Ordner erfolgreich erstellt.'; show = true; setTimeout(() => show = false, 4000)"
    class="p-4 sm:p-6 bg-white dark:bg-gray-800 rounded-lg shadow-lg"
>

    {{-- Header: Titel & Benutzerauswahl --}}
    <div class="pb-4 border-b border-gray-200 dark:border-gray-700">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <h3 class="text-xl font-semibold text-gray-800 dark:text-white">Dateimanager</h3>
            <div class="flex items-center gap-3">
                <label for="user-select" class="text-sm font-medium text-gray-700 dark:text-gray-300">Benutzer:</label>
                <select id="user-select" wire:model.live="selectedUser" wire:change="selectedUserChanged" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-primary-500 focus:border-primary-500 min-w-[200px]">
                    @foreach ($users as $user)
                        <option value="{{ $user['key'] }}">{{ $user['name'] }} ({{ $user['type'] }})</option>
                    @endforeach
                </select>
            </div>
        </div>
        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
            Verwalten Sie die Ordner und Dateien eines ausgewählten Benutzers.
        </p>

        <p class="font-bold text-lg whitespace-nowrap mt-12">Dateien von:
            {{ $this->selectedUserName }}
        </p>
    </div>

    {{-- Benachrichtigungen --}}
    <div class="h-0" aria-live="polite">
        <div
            x-show="show"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 transform -translate-y-2"
            x-transition:enter-end="opacity-100 transform translate-y-0"
            x-transition:leave="transition ease-in duration-300"
            x-transition:leave-start="opacity-100 transform translate-y-0"
            x-transition:leave-end="opacity-0 transform -translate-y-2"
            class="mt-4 text-sm bg-green-100 border border-green-300 text-green-800 px-4 py-2 rounded w-full sm:w-fit"
            x-text="message"
        >
        </div>
    </div>

    {{-- Integrierte Ordner- & Dateiansicht (Desktop) --}}
    <div class="hidden md:block mt-8 overflow-x-auto">
        <table class="w-full table-auto border border-gray-200 dark:border-gray-700 text-sm rounded-lg">
            <thead class="bg-gray-100 dark:bg-gray-700 text-left">
            {{-- Integrierte Steuerleiste --}}
            <tr>
                <th colspan="4" class="p-2">
                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">

                        <div class="text-sm text-gray-600 dark:text-gray-300 font-mono truncate">
                            Pfad: files{{ $this->getRelativePath() ?: '/' }}
                        </div>
                        <input
                            type="text"
                            wire:model.live.debounce.300ms="search"
                            placeholder="Suchen..."
                            class="w-full md:w-1/3 block rounded-md border-gray-300 px-3 py-1.5 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-primary-500 dark:bg-gray-800 dark:border-gray-600 dark:text-white"
                        >
                        <div class="flex items-center gap-3 justify-end">
                            <label class="flex items-center space-x-2 text-sm text-gray-700 dark:text-gray-300 whitespace-nowrap">
                                <input type="checkbox" wire:model.live="selectAll" wire:click="toggleSelectAll" class="rounded border-gray-300 dark:border-gray-600 text-primary-600 focus:ring-primary-500">
                                <span>Alle auswählen</span>
                            </label>
                            <x-forms.button
                                title="Löschen"
                                category="danger"
                                wire:click="deleteSelected"
                                type="button"
                                :disabled="empty($selectedFiles)"
                            />
                        </div>
                    </div>
                </th>
            </tr>
            {{-- Tabellenkopf --}}
            <tr>
                <th class="p-2 w-16">Auswahl</th>
                <th class="p-2 w-24">Typ</th>
                <th class="p-2">Name</th>
                <th class="p-2 w-20">Aktionen</th>
            </tr>
            </thead>
            <tbody>
            {{-- Zurück-Button --}}
            @if($this->getRelativePath())
                <tr wire:click="goBack" class="border-t border-gray-200 dark:border-gray-700 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-900/50">
                    <td></td>
                    <td class="p-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-gray-500 dark:text-gray-400 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h10a8 8 0 018 8v2M3 10l6-6m-6 6l6 6" />
                        </svg>
                    </td>
                    <td class="p-2 font-medium text-gray-700 dark:text-gray-300" colspan="2">
                        ...
                    </td>
                </tr>
            @endif

            {{-- Ordner --}}
            @foreach ($folders as $folder)
                <tr class="border-t border-gray-200 dark:border-gray-700">
                    <td></td> {{-- Keine Checkbox für Ordner --}}
                    <td class="p-2" wire:click="goToFolder('{{ $folder }}')" class="cursor-pointer">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-yellow-500 mx-auto" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z" />
                        </svg>
                    </td>
                    <td wire:click="goToFolder('{{ $folder }}')" class="p-2 font-medium text-gray-800 dark:text-gray-200 cursor-pointer hover:underline">
                        {{ $folder }}
                    </td>
                    <td class="p-2 text-center">
                        <div x-on:click.stop>
                            <div wire:loading.remove wire:target="deleteFolder('{{ $folder }}')">
                                <x-forms.button
                                    title="✕"
                                    category="x"
                                    wire:click="deleteFolder('{{ $folder }}')"
                                    wire:confirm="Möchten Sie den Ordner '{{ $folder }}' und seinen gesamten Inhalt wirklich löschen?"
                                    type="button"
                                />
                            </div>
                        </div>
                    </td>
                </tr>
            @endforeach

            {{-- Dateien --}}
            @foreach ($paginatedFiles as $file)
                <tr class="border-t border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-900/50"
                    @class([
                        'bg-primary-50 dark:bg-primary-900/50' => in_array($file['name'], $selectedFiles),
                    ])
                >
                    <td class="p-2 text-center">
                        <input type="checkbox" wire:model.live="selectedFiles" value="{{ $file['name'] }}" class="rounded border-gray-300 dark:border-gray-600 text-primary-600 focus:ring-primary-500">
                    </td>
                    <td class="p-2">
                        @if ($file['isImage'])
                            <img src="{{ $file['url'] }}" alt="{{ $file['name'] }}" class="w-16 h-16 object-cover rounded-md">
                        @else
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-12 h-12 text-gray-400 mx-auto">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                            </svg>
                        @endif
                    </td>
                    <td class="p-2 font-medium text-gray-800 dark:text-gray-200">
                        <span class="break-all">{{ $file['name'] }}</span>
                    </td>
                    <td class="p-2 text-center">
                        <div wire:loading.remove wire:target="deleteFile('{{ $file['name'] }}')">
                            <x-forms.button
                                title="✕"
                                category="x"
                                wire:click="deleteFile('{{ $file['name'] }}')"
                                wire:confirm="Möchten Sie die Datei '{{ $file['name'] }}' wirklich löschen?"
                                type="button"
                            />
                        </div>
                    </td>
                </tr>
            @endforeach

            {{-- Leerer Zustand --}}
            @if(count($folders) === 0 && count($paginatedFiles) === 0)
                <tr>
                    <td colspan="4" class="p-6 text-center text-gray-500 dark:text-gray-400">Dieser Ordner ist leer.</td>
                </tr>
            @endif
            </tbody>
            {{-- Aktionen im Footer: Upload & Neuer Ordner --}}
            <tfoot>
            <tr class="border-t-2 border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50">
                <td colspan="4" class="p-3">
                    <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                        {{-- Neuer Ordner --}}
                        <div class="flex items-center gap-2 w-full sm:w-auto">
                            <input
                                type="text"
                                wire:model="newFolderName"
                                placeholder="Neuer Ordnername"
                                class="w-full block rounded-md border-gray-300 px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                wire:keydown.enter="createNewFolder"
                            >
                            <div wire:loading.remove wire:target="createNewFolder">
                                <x-forms.button title="Erstellen" category="secondary" wire:click="createNewFolder" type="button" />
                            </div>
                            <div wire:loading wire:target="createNewFolder">
                                <x-forms.button title="Erstelle..." category="secondary" disabled type="button" />
                            </div>
                        </div>
                        @error('newFolderName') <span class="text-xs text-red-500">{{ $message }}</span> @enderror

                        {{-- Upload --}}
                        <div class="flex items-center gap-2 w-full sm:w-auto">
                            <input type="file" wire:model="uploads" multiple id="desktop-upload" class="hidden">
                            <label for="desktop-upload" class="w-full sm:w-auto cursor-pointer flex items-center justify-center gap-2 text-sm px-4 py-2 rounded-lg font-semibold bg-gray-200 text-black hover:bg-gray-300 dark:bg-gray-600 dark:text-white dark:hover:bg-gray-500">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                                </svg>
                                <span>{{ count($uploads) ? count($uploads) . ' ausgewählt' : 'Dateien auswählen' }}</span>
                            </label>
                            <x-forms.button title="Hochladen" category="primary" type="button" wire:click="uploadFiles" :disabled="empty($uploads)" />
                        </div>
                    </div>
                    <div wire:loading wire:target="uploads" class="text-xs text-gray-500 mt-1 text-right">
                        Dateien werden vorbereitet...
                    </div>
                    @error('uploads.*') <span class="text-xs text-red-500 block text-right">{{ $message }}</span> @enderror
                </td>
            </tr>
            </tfoot>
        </table>
    </div>

    {{-- Mobile Ansicht (Grid) --}}
    <div class="md:hidden mt-6">
        {{-- Mobile Steuerleiste --}}
        <div class="space-y-4 p-4 bg-gray-100 dark:bg-gray-700 rounded-lg mb-4">
            <div class="text-sm text-gray-600 dark:text-gray-300 font-mono truncate">
                Pfad: files{{ $this->getRelativePath() ?: '/' }}
            </div>
            <input
                type="text"
                wire:model.live.debounce.300ms="search"
                placeholder="Suchen..."
                class="w-full block rounded-md border-gray-300 px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-primary-500 dark:bg-gray-800 dark:border-gray-600 dark:text-white"
            >
            <div class="flex items-center justify-between">
                <label class="flex items-center space-x-2 text-sm text-gray-700 dark:text-gray-300">
                    <input type="checkbox" wire:model.live="selectAll" wire:click="toggleSelectAll" class="rounded border-gray-300 dark:border-gray-600 text-primary-600 focus:ring-primary-500">
                    <span>Alle</span>
                </label>
                <x-forms.button
                    title="Löschen"
                    category="danger"
                    wire:click="deleteSelected"
                    type="button"
                    :disabled="empty($selectedFiles)"
                />
            </div>
        </div>

        {{-- Mobile Grid --}}
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
            {{-- Zurück-Button --}}
            @if($this->getRelativePath())
                <div wire:click="goBack" class="flex flex-col items-center justify-center p-4 bg-gray-100 dark:bg-gray-800 rounded-lg shadow cursor-pointer hover:bg-gray-200 dark:hover:bg-gray-700">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-gray-500 dark:text-gray-400 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h10a8 8 0 018 8v2M3 10l6-6m-6 6l6 6" />
                    </svg>
                    <span class="text-xs text-center font-medium break-all text-gray-700 dark:text-gray-300">Zurück</span>
                </div>
            @endif

            {{-- Ordner --}}
            @foreach ($folders as $folder)
                <div class="relative p-2 bg-gray-100 dark:bg-gray-800 rounded-lg shadow hover:bg-gray-200 dark:hover:bg-gray-700">
                    <div wire:click="goToFolder('{{ $folder }}')" class="flex flex-col items-center cursor-pointer">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 text-yellow-500 mb-2" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z" />
                        </svg>
                        <span class="text-xs text-center font-medium break-all text-gray-700 dark:text-gray-300">{{ $folder }}</span>
                    </div>
                    <div class="absolute top-1 right-1 z-10" x-on:click.stop>
                        <x-forms.button title="✕" category="x" wire:click="deleteFolder('{{ $folder }}')" wire:confirm="Möchten Sie den Ordner '{{ $folder }}' wirklich löschen?" type="button" />
                    </div>
                </div>
            @endforeach

            {{-- Dateien --}}
            @foreach ($paginatedFiles as $file)
                <div class="relative p-2 bg-gray-100 dark:bg-gray-800 rounded-lg shadow hover:bg-gray-200 dark:hover:bg-gray-700" @class(['ring-2 ring-primary-500' => in_array($file['name'], $selectedFiles)])>
                    <div class="absolute top-2 left-2 z-10">
                        <input type="checkbox" wire:model.live="selectedFiles" value="{{ $file['name'] }}" class="rounded border-gray-400 dark:border-gray-600">
                    </div>
                    <div class="flex flex-col items-center">
                        @if ($file['isImage'])
                            <img src="{{ $file['url'] }}" alt="{{ $file['name'] }}" class="w-full h-24 object-cover rounded-md mb-2">
                        @else
                            <div class="w-full h-24 flex items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-12 h-12 text-gray-400">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                </svg>
                            </div>
                        @endif
                        <span class="text-xs text-center font-medium break-all text-gray-700 dark:text-gray-300">{{ $file['name'] }}</span>
                        <div class="mt-2">
                            <div wire:loading.remove wire:target="deleteFile('{{ $file['name'] }}')">
                                <x-forms.button title="✕" category="x" wire:click="deleteFile('{{ $file['name'] }}')" wire:confirm="Möchten Sie die Datei '{{ $file['name'] }}' wirklich löschen?" type="button" />
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach

            {{-- Leerer Zustand --}}
            @if(count($folders) === 0 && count($paginatedFiles) === 0)
                <div class="col-span-2 sm:col-span-3 text-center text-gray-500 dark:text-gray-400 py-8">Dieser Ordner ist leer.</div>
            @endif
        </div>

        {{-- Mobile Aktionen --}}
        <div class="mt-6 p-4 bg-gray-100 dark:bg-gray-700 rounded-lg space-y-4">
            {{-- Neuer Ordner --}}
            <div>
                <input type="text" wire:model="newFolderName" placeholder="Neuer Ordnername" class="w-full block rounded-md border-gray-300 px-3 py-2 text-sm" wire:keydown.enter="createNewFolder">
                @error('newFolderName') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                <x-forms.button title="Ordner erstellen" category="secondary" type="button" wire:click="createNewFolder" class="w-full mt-2" />
            </div>
            {{-- Upload --}}
            <div>
                <input type="file" wire:model="uploads" multiple id="mobile-upload" class="block w-full text-sm text-gray-500 file:cursor-pointer file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-gray-200 hover:file:bg-gray-300 dark:file:bg-gray-600 dark:file:text-white dark:hover:file:bg-gray-500">
                <div wire:loading wire:target="uploads" class="text-xs text-gray-500 mt-1">Dateien werden vorbereitet...</div>
                @error('uploads.*') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                <x-forms.button title="Hochladen" category="primary" type="button" wire:click="uploadFiles" :disabled="empty($uploads)" class="w-full mt-3" />
            </div>
        </div>
    </div>

    {{-- Pagination --}}
    @if(count($paginatedFiles) > 0)
        <div class="mt-6">
            {{ $paginatedFiles->links() }}
        </div>
    @endif
</div>
