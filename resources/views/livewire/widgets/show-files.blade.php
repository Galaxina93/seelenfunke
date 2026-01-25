<div>
    <div class="p-6 bg-white dark:bg-gray-800 rounded-lg shadow space-y-6">

        {{-- Titel und Pfadanzeige --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-gray-200 dark:border-gray-700 pb-4">
            <h3 class="text-xl font-semibold text-gray-800 dark:text-white">
                Deine Projektdateien
            </h3>
            <div class="text-md text-gray-600 dark:text-gray-300 font-mono truncate">
                Pfad: files{{ $this->getRelativePath() ?: '/' }}
            </div>
        </div>

        {{-- Zurück-Button --}}
        @if ($this->getRelativePath() && $this->getRelativePath() !== '/')
            <div>
                <x-forms.button
                    title="Zurück"
                    category="secondary"
                    wireClick="goBack"
                    type="button"
                />
            </div>
        @endif

        {{-- Ordneranzeige (ist bereits responsiv) --}}
        @if (!empty($folders))
            <div>
                <h4 class="text-lg font-medium text-gray-700 dark:text-gray-300 mb-2">Ordner</h4>
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
        <div>
            <h4 class="text-lg font-medium text-gray-700 dark:text-gray-300 mb-2 mt-4">Dateien</h4>

            {{-- Desktop Table View --}}
            <div class="hidden md:block">
                <div class="overflow-x-auto">
                    <table class="w-full table-auto border border-gray-200 dark:border-gray-600 text-sm">
                        <thead class="bg-gray-100 dark:bg-gray-700 text-left">
                        <tr>
                            <th class="p-2 w-24">Vorschau</th>
                            <th class="p-2">Dateiname</th>
                            <th class="p-2 w-32">Aktion</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse ($paginatedFiles as $file)
                            <tr class="border-t border-gray-200 dark:border-gray-600">
                                {{-- Vorschau --}}
                                <td class="p-2">
                                    @if ($file['isImage'])
                                        <img src="{{ $file['url'] }}" alt="{{ $file['name'] }}" class="w-16 h-16 object-cover rounded">
                                    @else
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-12 text-gray-400">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                        </svg>
                                    @endif
                                </td>

                                {{-- Dateiname --}}
                                <td class="p-2 align-middle">
                                    <span class="break-all">{{ $file['name'] }}</span>
                                </td>

                                {{-- Aktionen (Download) --}}
                                <td class="p-2 align-middle">
                                    <div wire:loading.remove wire:target="downloadFile('{{ $file['name'] }}')">
                                        <x-forms.button
                                            title="Download"
                                            category="primary"
                                            wireClick="downloadFile('{{ $file['name'] }}')"
                                            type="button"
                                        />
                                    </div>
                                    <div wire:loading wire:target="downloadFile('{{ $file['name'] }}')" class="flex justify-center items-center">
                                        <svg class="w-5 h-5 animate-spin text-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                                        </svg>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="p-4 text-center text-gray-500 dark:text-gray-400">
                                    In diesem Ordner wurden keine Dateien gefunden.
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Mobile Grid View --}}
            <div class="grid grid-cols-2 gap-4 mt-4 md:hidden">
                @forelse ($paginatedFiles as $file)
                    <div class="p-4 bg-gray-100 dark:bg-gray-700 rounded-lg shadow space-y-2">

                        {{-- Vorschau --}}
                        <div class="flex justify-center items-center">
                            @if ($file['isImage'])
                                <img src="{{ $file['url'] }}" alt="{{ $file['name'] }}" class="w-full h-24 object-cover rounded">
                            @else
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-16 text-gray-400">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                </svg>
                            @endif
                        </div>

                        {{-- Dateiname --}}
                        <div class="text-sm text-center font-medium break-all">{{ $file['name'] }}</div>

                        {{-- Aktionen (Download) --}}
                        <div class="flex justify-center items-center pt-2">
                            <div wire:loading.remove wire:target="downloadFile('{{ $file['name'] }}')">
                                <x-forms.button
                                    title="Download"
                                    category="primary"
                                    wireClick="downloadFile('{{ $file['name'] }}')"
                                    type="button"
                                />
                            </div>
                            <div wire:loading wire:target="downloadFile('{{ $file['name'] }}')" class="flex justify-center items-center">
                                <svg class="w-5 h-5 animate-spin text-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                                </svg>
                            </div>
                        </div>

                    </div>
                @empty
                    <div class="col-span-2 text-center text-gray-500 dark:text-gray-400">
                        In diesem Ordner wurden keine Dateien gefunden.
                    </div>
                @endforelse
            </div>

        </div>

        {{-- Pagination --}}
        @if ($paginatedFiles->hasPages())
            <div class="mt-4">
                {{ $paginatedFiles->links() }}
            </div>
        @endif
    </div>
</div>
