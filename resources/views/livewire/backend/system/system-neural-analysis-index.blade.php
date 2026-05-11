<div class="p-6">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-white">Neurale Analyse</h1>
            <p class="text-gray-400">Übersicht aller indexierten Systemknoten aus dem Projekt Gehirn.</p>
        </div>
        <button wire:click="$refresh" class="bg-[var(--theme-color)]/20 hover:bg-[var(--theme-color)]/40 text-[var(--theme-color)] hover:text-white px-4 py-2 rounded-lg border border-[var(--theme-color)]/50 shadow-[0_0_15px_var(--theme-color-20)] transition flex items-center gap-2 font-medium">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
              <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
            </svg>
            Aktualisieren
        </button>
    </div>

    <div class="bg-gray-900 border border-gray-800 rounded-xl overflow-hidden shadow-2xl">
        <div class="p-4 border-b border-gray-800 flex gap-4">
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Suche nach Datei..." class="bg-black/50 border border-gray-700 rounded-lg text-white px-4 py-2 w-full max-w-md focus:ring-[var(--theme-color)] focus:border-[var(--theme-color)]">
            <select wire:model.live="filterGroup" class="bg-black/50 border border-gray-700 rounded-lg text-white px-4 py-2 focus:ring-[var(--theme-color)] focus:border-[var(--theme-color)]">
                <option value="">Alle Layer</option>
                <option value="2">Models</option>
                <option value="3">Controllers</option>
                <option value="4">Livewire</option>
                <option value="5">Views</option>
                <option value="6">Routes</option>
                <option value="7">Config</option>
                <option value="8">Services</option>
                <option value="9">Console/Commands</option>
            </select>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-gray-400">
                <thead class="bg-gray-800 text-xs uppercase text-gray-300">
                    <tr>
                        <th class="px-6 py-3">Datei</th>
                        <th class="px-6 py-3">Layer</th>
                        <th class="px-6 py-3">Abhängigkeiten</th>
                        <th class="px-6 py-3">Aktion</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($nodes as $node)
                        <tr class="border-b border-gray-800 hover:bg-gray-800/50 transition">
                            <td class="px-6 py-4 font-mono text-[var(--theme-color)]">
                                {{ $node->file_path }}
                            </td>
                            <td class="px-6 py-4">
                                @switch($node->group_id)
                                    @case(2) <span class="text-emerald-400">Models</span> @break
                                    @case(3) <span class="text-blue-400">Controllers</span> @break
                                    @case(4) <span class="text-pink-400">Livewire</span> @break
                                    @case(5) <span class="text-amber-400">Views</span> @break
                                    @default <span class="text-gray-400">System</span>
                                @endswitch
                            </td>
                            <td class="px-6 py-4">
                                {{ count($node->dependencies ?? []) }} referenziert
                            </td>
                            <td class="px-6 py-4">
                                <button wire:click="generateStructure('{{ $node->id }}')" class="text-xs bg-[var(--theme-color)]/20 hover:bg-[var(--theme-color)]/40 text-[var(--theme-color)] hover:text-white px-3 py-1.5 rounded-lg border border-[var(--theme-color)]/50 transition flex items-center gap-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                      <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                    </svg>
                                    Struktur .md
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-8 text-center text-gray-500">
                                Keine Knoten gefunden. Bitte den Scanner laufen lassen.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-gray-800">
            {{ $nodes->links() }}
        </div>
    </div>
</div>
