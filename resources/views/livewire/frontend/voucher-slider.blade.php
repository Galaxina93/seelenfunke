<div class="flex flex-col h-full max-h-[80vh]">
    {{-- Header --}}
    <div class="p-8 pb-4 flex justify-between items-start">
        <div>
            <h3 class="font-serif font-bold text-2xl text-gray-900 leading-none">Deine Funken</h3>
            <p class="text-[11px] text-primary font-black uppercase tracking-widest mt-2">Limitierte Angebote</p>
        </div>
    </div>

    {{-- Content --}}
    <div class="px-6 py-4 overflow-y-auto custom-scrollbar space-y-6">
        @forelse($vouchers as $voucher)
            <div class="relative group animate-fade-in-up">
                <div class="bg-white border border-gray-100 rounded-2xl p-5 shadow-[0_10px_20px_rgba(0,0,0,0.03)] hover:shadow-[0_15px_30px_rgba(197,160,89,0.1)] transition-all duration-500 border-b-4 border-b-primary/20">
                    <div class="flex justify-between items-center mb-4">
                        <span class="text-[9px] font-black bg-gray-100 px-2 py-0.5 rounded text-gray-500 uppercase tracking-widest">{{ $voucher->title }}</span>
                    </div>

                    <div class="flex items-baseline gap-1 mb-4">
                        <span class="text-4xl font-serif font-bold text-gray-900">{{ $voucher->value }}</span>
                        <span class="text-xl font-serif font-bold text-primary">{{ $voucher->type === 'percent' ? '%' : '€' }}</span>
                        <span class="text-xs font-bold text-gray-400 uppercase ml-2 tracking-tighter">Rabatt</span>
                    </div>

                    {{-- Copy Button mit stabilisiertem Layout und Glow-Effekt --}}
                    <div x-data="{
                                    copied: false,
                                    copyCode() {
                                        if (navigator.clipboard && navigator.clipboard.writeText) {
                                            navigator.clipboard.writeText('{{ $voucher->code }}').then(() => {
                                                this.copied = true;
                                                setTimeout(() => this.copied = false, 2500);
                                            });
                                        } else {
                                            let textArea = document.createElement('textarea');
                                            textArea.value = '{{ $voucher->code }}';
                                            document.body.appendChild(textArea);
                                            textArea.select();
                                            document.execCommand('copy');
                                            document.body.removeChild(textArea);
                                            this.copied = true;
                                            setTimeout(() => this.copied = false, 2500);
                                        }
                                    }
                                }"
                         @click="copyCode()"
                         class="relative overflow-hidden bg-gray-50 hover:bg-gray-900 rounded-xl min-h-[54px] px-4 flex justify-between items-center cursor-pointer transition-all duration-300 border border-gray-100 group/code"
                         {{-- Glow-Effekt bei Erfolg --}}
                         :class="copied ? 'bg-green-50 border-green-300 ring-2 ring-green-100' : ''">

                        {{-- Text-Bereich --}}
                        <span class="font-mono font-bold text-gray-900 group-hover/code:text-primary transition-colors tracking-widest uppercase truncate mr-2"
                              :class="copied ? 'text-green-600' : ''"
                              x-text="copied ? 'KOPIERT!' : '{{ $voucher->code }}'">
                        </span>

                        {{-- Icon-Container: shrink-0 verhindert das Abschneiden --}}
                        <div class="flex-shrink-0 flex items-center justify-center w-6 h-6">
                            {{-- Standard Copy Icon --}}
                            <svg x-show="!copied" class="w-5 h-5 text-gray-300 group-hover/code:text-primary transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2" />
                            </svg>

                            {{-- Erfolg Check Icon --}}
                            <svg x-show="copied" x-cloak class="w-6 h-6 text-green-600 animate-fade-in" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                            </svg>
                        </div>
                    </div>
                    @if($voucher->min_order_value > 0)
                        <p class="text-[10px] text-gray-400 mt-3 text-center italic">Gültig ab {{ number_format($voucher->min_order_value / 100, 2, ',', '.') }}€ Warenwert</p>
                    @endif
                </div>
            </div>
        @empty
            <div class="text-center py-10 opacity-40">Keine Gutscheine</div>
        @endforelse
    </div>

    <div class="p-8 pt-4 bg-gray-50/50 border-t border-gray-100">
        <p class="text-[10px] font-medium leading-tight text-gray-400 italic">Nur ein Gutschein pro Bestellung einlösbar.</p>
    </div>
</div>
