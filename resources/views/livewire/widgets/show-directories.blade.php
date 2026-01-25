<div>
    <div class="p-6 bg-white dark:bg-gray-800 rounded-lg shadow space-y-6">

        @if (!$selectedDirectory)
            <!-- Ansicht: Liste der freigegebenen Verzeichnisse -->
            <div>
                <h3 class="text-xl font-semibold text-gray-800 dark:text-white border-b border-gray-200 dark:border-gray-700 pb-4">
                    Freigaben
                </h3>
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 mt-6">
                    @forelse ($sharedDirectories as $directory)
                        <div
                            class="p-4 bg-gray-100 dark:bg-gray-700 rounded-lg flex items-center gap-3 hover:bg-gray-200 dark:hover:bg-gray-600 cursor-pointer transition-colors"
                            wire:click="selectDirectory({{ $directory->id }})"
                            title="{{ $directory->name }}"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6 text-blue-500 flex-shrink-0">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 9.776c.112-.017.227-.026.344-.026h15.812c.117 0 .232.009.344.026m-16.5 0a2.25 2.25 0 0 0-1.883 2.542l.857 6a2.25 2.25 0 0 0 2.227 1.932H19.05a2.25 2.25 0 0 0 2.227-1.932l.857-6a2.25 2.25 0 0 0-1.883-2.542m-16.5 0V6A2.25 2.25 0 0 1 4.5 3.75h15A2.25 2.25 0 0 1 21.75 6v3.776" />
                            </svg>
                            <span class="truncate font-medium">{{ $directory->name }}</span>
                        </div>
                    @empty
                        <p class="col-span-full text-gray-500 dark:text-gray-400">Es wurden keine Verzeichnisse für Sie freigegeben.</p>
                    @endforelse
                </div>
            </div>

        @else
            <!-- Ansicht: Inhalt eines ausgewählten Verzeichnisses -->
            <div>
                {{-- Titel und Pfadanzeige --}}
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-gray-200 dark:border-gray-700 pb-4">
                    <h3 class="text-xl font-semibold text-gray-800 dark:text-white">
                        Verzeichnis: <span class="text-primary">{{ $selectedDirectory->name }}</span>
                    </h3>
                    <div class="text-md text-gray-600 dark:text-gray-300 font-mono truncate">
                        Pfad: {{ $this->getRelativePath() ?: '/' }}
                    </div>
                </div>

                {{-- Zurück-Buttons --}}
                <div class="flex items-center gap-2 mt-4">
                    <x-forms.button
                        title="Zurück zur Übersicht"
                        category="secondary"
                        wireClick="unselectDirectory"
                        type="button"
                    />
                    @if ($this->getRelativePath() && $this->getRelativePath() !== '/')
                        <x-forms.button
                            title="Ordner zurück"
                            category="secondary"
                            wireClick="goBack"
                            type="button"
                        />
                    @endif
                </div>

                {{-- Ordneranzeige --}}
                @if (!empty($folders))
                    <div>
                        <h4 class="text-lg font-medium text-gray-700 dark:text-gray-300 mb-2 mt-6">Ordner</h4>
                        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                            @foreach ($folders as $folder)
                                <div
                                    class="p-4 bg-gray-100 dark:bg-gray-700 rounded-lg flex items-center gap-3 hover:bg-gray-200 dark:hover:bg-gray-600 cursor-pointer transition-colors"
                                    wire:click="goToFolder('{{ $folder }}')"
                                    title="{{ $folder }}"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6 text-yellow-500 flex-shrink-0">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 0 1 4.5 9.75h15A2.25 2.25 0 0 1 21.75 12v.75m-8.69-6.44-2.12-2.12a1.5 1.5 0 0 0-1.061-.44H4.5A2.25 2.25 0 0 0 2.25 6v12a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9a2.25 2.25 0 0 0-2.25-2.25h-5.379a1.5 1.5 0 0 1-1.06-.44Z" />
                                    </svg>
                                    <span class="truncate font-medium">{{ $folder }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Dateianzeige --}}
                <div class="mt-6">
                    <h4 class="text-lg font-medium text-gray-700 dark:text-gray-300 mb-2">Dateien</h4>
                    @if($paginatedFiles->isEmpty())
                        <div class="p-4 text-center text-gray-500 dark:text-gray-400 border-2 border-dashed rounded-lg dark:border-gray-600">
                            In diesem Ordner wurden keine Dateien gefunden.
                        </div>
                    @else
                        {{-- Desktop Table View --}}
                        <div class="hidden md:block">
                            <div class="overflow-x-auto">
                                <table class="w-full table-auto border border-gray-200 dark:border-gray-600 text-sm">
                                    <thead class="bg-gray-100 dark:bg-gray-700 text-left">
                                    <tr>
                                        <th class="p-2 w-24">Vorschau</th>
                                        <th class="p-2">Dateiname</th>
                                        <th class="p-2 w-32 text-center">Aktion</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach ($paginatedFiles as $file)
                                        <tr class="border-t border-gray-200 dark:border-gray-600">
                                            <td class="p-2"><img src="{{ $file['url'] }}" alt="{{ $file['name'] }}" class="w-16 h-16 object-cover rounded"></td>
                                            <td class="p-2 align-middle"><span class="break-all">{{ $file['name'] }}</span></td>
                                            <td class="p-2 align-middle text-center">
                                                <x-forms.button title="Download" category="primary" wire:click="downloadFile('{{ $file['name'] }}')" type="button" />
                                            </td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        {{-- Mobile Grid View --}}
                        <div class="grid grid-cols-2 gap-4 mt-4 md:hidden">
                            @foreach ($paginatedFiles as $file)
                                <div class="p-4 bg-gray-100 dark:bg-gray-700 rounded-lg shadow space-y-2">
                                    <div class="flex justify-center items-center"><img src="{{ $file['url'] }}" alt="{{ $file['name'] }}" class="w-full h-24 object-cover rounded"></div>
                                    <div class="text-sm text-center font-medium break-all">{{ $file['name'] }}</div>
                                    <div class="flex justify-center items-center pt-2">
                                        <x-forms.button title="Download" category="primary" wire:click="downloadFile('{{ $file['name'] }}')" type="button" />
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Pagination --}}
                @if ($paginatedFiles->hasPages())
                    <div class="mt-4">
                        {{ $paginatedFiles->links() }}
                    </div>
                @endif
            </div>
        @endif
    </div>

</div>
