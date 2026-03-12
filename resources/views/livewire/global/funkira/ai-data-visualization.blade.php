<div x-data="{ open: @entangle('isOpen') }" 
     x-show="open" 
     x-cloak
     class="fixed inset-0 z-[200] flex items-center justify-center pointer-events-auto"
     aria-labelledby="modal-title" role="dialog" aria-modal="true">
     
    <!-- Backdrop Blur -->
    <div x-show="open" 
         x-transition:enter="ease-out duration-300" 
         x-transition:enter-start="opacity-0" 
         x-transition:enter-end="opacity-100" 
         x-transition:leave="ease-in duration-200" 
         x-transition:leave-start="opacity-100" 
         x-transition:leave-end="opacity-0" 
         class="fixed inset-0 bg-gray-950/80 backdrop-blur-xl transition-opacity"></div>

    <!-- Modal Panel -->
    <div x-show="open" 
         x-transition:enter="ease-out duration-300" 
         x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" 
         x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" 
         x-transition:leave="ease-in duration-200" 
         x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" 
         x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" 
         class="relative transform overflow-hidden rounded-2xl bg-gray-900 border border-gray-700/50 shadow-[0_0_50px_rgba(0,0,0,0.5)] transition-all max-w-4xl w-full m-4 max-h-[90vh] flex flex-col">
        
        <!-- Header -->
        <div class="px-6 py-4 border-b border-gray-800 bg-gray-900/50 flex items-center justify-between z-10">
            <h3 class="text-lg font-mono font-bold text-gray-200 flex items-center gap-3" id="modal-title">
                <span class="text-emerald-400"><i class="bi bi-box"></i></span>
                @if($category === 'voucher')
                    Gutschein Daten
                @elseif($category === 'customer')
                    Kundenakte
                @elseif($category === 'todo')
                    Aufgaben Liste
                @else
                    System Analyse
                @endif
            </h3>
            <button wire:click="close" type="button" class="rounded-full w-8 h-8 flex items-center justify-center bg-gray-800 text-gray-400 hover:text-white hover:bg-rose-500/20 hover:text-rose-400 transition-all focus:outline-none">
                <span class="sr-only">Schließen</span>
                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                  <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" />
                </svg>
            </button>
        </div>

        <!-- Body: Der Headless Router! -->
        <div class="p-6 overflow-y-auto w-full custom-scrollbar flex-1 relative bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-gray-800/20 via-gray-900 to-black">
            @if($category === 'voucher')
                @if(count($data) === 1)
                    <!-- Single Voucher Card -->
                    @include('livewire.global.funkira.blocks.voucher-single', ['voucher' => $data[0]])
                @elseif(count($data) > 1)
                    <!-- Voucher List/Table -->
                    @include('livewire.global.funkira.blocks.voucher-table', ['vouchers' => $data])
                @else
                    <div class="text-center py-10 text-gray-500 italic font-serif">Keine Gutschein-Daten gefunden.</div>
                @endif
            @else
                <!-- Fallback Raw JSON Display if no block exists yet -->
                <div class="bg-gray-950 p-4 rounded-xl border border-gray-800 relative z-10 w-full overflow-x-auto text-xs font-mono text-emerald-400">
                    <pre>{{ json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                </div>
            @endif
        </div>
    </div>
</div>
