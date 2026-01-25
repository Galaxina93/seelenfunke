<div
    x-data="{
        message: '',
        showNotification: false
    }"
    x-on:notify.window="message = $event.detail.message; showNotification = true; setTimeout(() => showNotification = false, 4000)"
    class="p-4 sm:p-6 bg-white dark:bg-gray-800 rounded-lg shadow-lg space-y-6"
>
    <!-- Notification -->
    <div aria-live="polite" class="h-0">
        <div x-show="showNotification" x-transition class="fixed top-5 right-5 z-50 bg-green-100 border border-green-300 text-green-800 px-4 py-2 rounded shadow-lg">
            <span x-text="message"></span>
        </div>
    </div>

    <!-- Header -->
    <div>
        <h3 class="text-xl font-semibold text-gray-800 dark:text-white">Verzeichnisverwaltung</h3>
        <p class="text-sm text-gray-600 dark:text-gray-300">Erstellen, verwalten und befüllen Sie freigegebene Verzeichnisse.</p>
    </div>

    <!-- Directory Table -->
    <div class="overflow-x-auto">
        <table class="w-full table-auto border border-gray-200 dark:border-gray-700 text-sm rounded-lg">
            <thead class="bg-gray-100 dark:bg-gray-700 text-left">
            <!-- Suchleiste -->
            <tr>
                <th class="p-2" colspan="3">
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="search"
                        placeholder="Verzeichnisse durchsuchen..."
                        class="w-full block rounded-lg border-gray-300 px-3 py-2 sm:text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-primary dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                    >
                </th>
            </tr>
            <!-- Tabellenkopf -->
            <tr>
                <th class="p-3">Name</th>
                <th class="p-3 text-center">Freigaben</th>
                <th class="p-3">Aktionen</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($directories as $directory)
                <tr wire:key="dir-view-{{ $directory->id }}" class="border-t border-gray-200 dark:border-gray-600 @if($selectedDirectory?->id === $directory->id) bg-primary-50 dark:bg-primary-900/50 @endif">
                    <!-- Viewing State -->
                    @if ($editingDirectoryId !== $directory->id)
                        <td class="p-3 font-medium dark:text-gray-200">
                            <div class="flex items-center gap-3 cursor-pointer" wire:click="selectDirectory({{ $directory->id }})">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-yellow-500" viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z" />
                                </svg>
                                <span class="hover:underline">{{ $directory->name }}</span>
                            </div>
                        </td>
                        <td class="p-3 text-center dark:text-gray-300">
                            {{ $directory->admins_count + $directory->employees_count + $directory->customers_count }} Benutzer
                        </td>
                        <td class="p-3">
                            <div class="flex items-center justify-start gap-1">
                                <!-- Verwalten Icon -->
                                <button title="Verwalten" wire:click.prevent="selectDirectory({{ $directory->id }})" class="p-2 text-gray-500 rounded-full hover:bg-gray-200 dark:hover:bg-gray-600 hover:text-green-600 transition-colors">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                        <path d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                                        <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.022 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd" />
                                    </svg>
                                </button>

                                <!-- Umbenennen Icon -->
                                <button title="Umbenennen" wire:click.prevent="startEditing({{ $directory->id }})" class="p-2 text-gray-500 rounded-full hover:bg-gray-200 dark:hover:bg-gray-600 hover:text-blue-600 transition-colors">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                        <path d="M17.414 2.586a2 2 0 00-2.828 0L7 10.172V13h2.828l7.586-7.586a2 2 0 000-2.828z" />
                                        <path fill-rule="evenodd" d="M2 6a2 2 0 012-2h4a1 1 0 010 2H4v10h10v-4a1 1 0 112 0v4a2 2 0 01-2 2H4a2 2 0 01-2-2V6z" clip-rule="evenodd" />
                                    </svg>
                                </button>

                                <!-- Löschen Icon -->
                                <button title="Löschen" wire:click.prevent="deleteDirectory({{ $directory->id }})" wire:confirm="Sind Sie sicher, dass Sie dieses Verzeichnis und alle darin enthaltenen Dateien löschen möchten?" class="p-2 text-gray-500 rounded-full hover:bg-gray-200 dark:hover:bg-gray-600 hover:text-red-600 transition-colors">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm4 0a1 1 0 012 0v6a1 1 0 11-2 0V8z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </div>
                        </td>
                    @else
                        <!-- Editing State -->
                        <td colspan="3" class="p-3" wire:key="dir-edit-{{ $directory->id }}">
                            <div class="flex items-center gap-2">
                                <input type="text" wire:model="editingDirectoryName" class="w-full block rounded-lg border-gray-300 dark:bg-gray-600 dark:border-gray-500 dark:text-white" wire:keydown.enter="updateDirectoryName" wire:keydown.escape="cancelEditing">
                                <x-forms.button title="Speichern" category="primary" wireClick="updateDirectoryName" />
                                <x-forms.button title="Abbrechen" category="secondary" wireClick="cancelEditing" />
                            </div>
                            @error('editingDirectoryName') <span class="text-sm text-red-500 mt-1">{{ $message }}</span> @enderror
                        </td>
                    @endif
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="p-6 text-center text-gray-500 dark:text-gray-400">Keine Verzeichnisse gefunden.</td>
                </tr>
            @endforelse
            </tbody>
            {{-- Create New Directory --}}
            <tfoot>
            <tr class="border-t-2 border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50">
                <td class="p-3" colspan="3">
                    <div class="flex items-center gap-2">
                        <input
                            type="text"
                            wire:model="newDirectoryName"
                            placeholder="Neues Verzeichnis erstellen..."
                            class="w-full block rounded-lg border border-gray-300 px-3 py-2 sm:text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-primary dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                            wire:keydown.enter="createDirectory"
                        >
                        <div wire:loading.remove wire:target="createDirectory">
                            <x-forms.button title="Erstellen" category="primary" wire:click="createDirectory" type="button" />
                        </div>
                        <div wire:loading wire:target="createDirectory">
                            <x-forms.button title="Erstelle..." category="primary" disabled type="button" />
                        </div>
                    </div>
                    @error('newDirectoryName') <span class="text-sm text-red-500 mt-1">{{ $message }}</span> @enderror
                </td>
            </tr>
            </tfoot>
        </table>
        {{-- Pagination --}}
        @if(count($files) > 0)
            <div class="mt-6">
                {{ $files->links() }}
            </div>
        @endif
    </div>

    <!-- Management Section (Users & Files) -->
    @if ($selectedDirectory)
        <div class="mt-6 p-4 sm:p-6 border-t dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50 rounded-lg" x-transition>
            <div class="flex justify-between items-center mb-4">
                <h4 class="text-lg font-semibold dark:text-white">Verzeichnis verwalten: "<span class="text-primary">{{ $selectedDirectory->name }}</span>"</h4>
                <button wire:click="unselectDirectory" class="text-gray-500 hover:text-gray-800 dark:hover:text-gray-200 font-bold text-xl">&times;</button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <!-- User Assignment -->
                <div>
                    <h5 class="font-semibold mb-2 dark:text-white">Freigabe für Benutzer</h5>
                    <div class="max-h-64 overflow-y-auto pr-2 border rounded-md p-2 dark:border-gray-600">
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">Wählen Sie die Benutzer aus, die Zugriff haben sollen.</p>
                        <div class="space-y-1">
                            @foreach ($users as $user)
                                <label class="flex items-center p-2 rounded-md hover:bg-gray-200 dark:hover:bg-gray-700 cursor-pointer">
                                    <input type="checkbox" wire:model.live="assignedUsers" value="{{ $user['key'] }}" class="rounded">
                                    <span class="ml-3 dark:text-gray-200">{{ $user['name'] }} <span class="text-xs text-gray-500">({{ $user['type'] }})</span></span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                    <div class="flex justify-end mt-4">
                        <x-forms.button title="Freigaben speichern" category="primary" wireClick="syncUsers" />
                    </div>
                </div>

                <!-- File Management -->
                <div class="space-y-4">
                    <h5 class="font-semibold mb-2 dark:text-white">Dateien im Verzeichnis</h5>
                    <!-- File Upload -->
                    <div class="p-4 border rounded-lg dark:border-gray-600">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Neue Dateien hochladen</label>
                        <input type="file" wire:model="uploads" multiple class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-gray-200 dark:file:bg-gray-600 dark:file:text-white file:text-gray-700 hover:file:bg-gray-300 mt-1">
                        <div wire:loading wire:target="uploads" class="text-sm text-gray-500 mt-1">Lade hoch...</div>
                        @error('uploads.*') <span class="text-sm text-red-500">{{ $message }}</span> @enderror
                        <div class="flex justify-end mt-2">
                            <x-forms.button title="Hochladen" category="primary" wireClick="uploadFiles" :disabled="empty($uploads)" />
                        </div>
                    </div>

                    <!-- File List -->
                    <div class="space-y-2">
                        @forelse($files as $file)
                            <div class="flex items-center justify-between p-2 bg-white dark:bg-gray-800 rounded-md shadow-sm" wire:key="file-{{ $file['name'] }}">
                                <div class="flex items-center gap-3 truncate">
                                    @if($file['isImage'])
                                        <img src="{{ $file['url'] }}" class="w-8 h-8 object-cover rounded">
                                    @else
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-gray-400">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                        </svg>
                                    @endif
                                    <span class="truncate dark:text-gray-200">{{ $file['name'] }}</span>
                                </div>
                                <button wire:click="deleteFile('{{ $file['name'] }}')" class="text-red-500 hover:text-red-700 font-bold text-xl">&times;</button>
                            </div>
                        @empty
                            <p class="text-center text-gray-500 p-4">Keine Dateien in diesem Verzeichnis.</p>
                        @endforelse
                    </div>
                    <div class="mt-4">
                        {{ $files->links() }}
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
