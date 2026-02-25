<div class="p-8 min-h-[400px] relative z-10 bg-gray-900">
    @if($items->isEmpty())
        <div class="text-center py-20 text-gray-500">
            <p class="text-lg font-serif">Das Lager ist leer.</p>
        </div>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 perspective-1000">
            @foreach($items as $item)
                @php
                    $isOwned = in_array($item->id, $ownedItemIds);
                    $isEquipped = in_array($item->id, [$activeBg, $activeFrame, $activeSkin]);
                @endphp

                <div x-data="{
                                tiltX: 0, tiltY: 0,
                                handleMouse(e) {
                                    const rect = this.$el.getBoundingClientRect();
                                    const x = e.clientX - rect.left;
                                    const y = e.clientY - rect.top;
                                    const centerX = rect.width / 2;
                                    const centerY = rect.height / 2;
                                    this.tiltX = ((y - centerY) / centerY) * -10;
                                    this.tiltY = ((x - centerX) / centerX) * 10;
                                },
                                resetMouse() { this.tiltX = 0; this.tiltY = 0; }
                            }"
                     @mousemove="handleMouse($event)"
                     @mouseleave="resetMouse()"
                     style="transform-style: preserve-3d;"
                     :style="`transform: rotateX(${tiltX}deg) rotateY(${tiltY}deg); transition: transform 0.1s ease-out;`"
                     class="bg-gray-800 rounded-2xl overflow-hidden border border-gray-700 flex flex-col group shadow-lg relative will-change-transform">

                    <div class="h-1 w-full {{ str_replace(['bg-', 'text-', 'border-', 'shadow-'], ['bg-', '', '', 'shadow-'], $item->rarity_color) }}"></div>

                    <div class="relative h-48 bg-black overflow-hidden flex items-center justify-center p-4">
                        @if($item->type === 'background')
                            <img src="{{ asset('storage/' . $item->preview_image_path) }}" class="absolute inset-0 w-full h-full object-cover opacity-60 group-hover:opacity-100 transition-opacity duration-500">
                        @else
                            <div class="absolute inset-0 bg-gradient-to-b from-gray-800 to-black"></div>
                            <img src="{{ asset('storage/' . $item->preview_image_path) }}" class="relative z-10 max-h-full max-w-full object-contain transform group-hover:scale-125 transition-transform duration-500 ease-out" style="transform: translateZ(30px);">
                        @endif
                        <div class="absolute top-3 left-3 flex flex-col gap-1 z-20" style="transform: translateZ(20px);">
                            <span class="px-2 py-1 text-[9px] font-black uppercase tracking-widest rounded {{ $item->rarity_color }} shadow-lg">{{ $item->rarity_name }}</span>
                        </div>
                    </div>

                    <div class="p-5 flex-1 flex flex-col bg-gray-800 relative z-10" style="transform: translateZ(10px);">
                        <h3 class="font-bold text-white text-lg mb-1 truncate">{{ $item->name }}</h3>
                        <p class="text-xs text-gray-400 mb-4 line-clamp-2 flex-1">{{ $item->description }}</p>

                        @if($isOwned)
                            <button wire:click="toggleEquip({{ $item->id }})" class="w-full py-3 rounded-xl text-sm font-bold uppercase tracking-widest transition-all mt-auto {{ $isEquipped ? 'bg-white text-gray-900 shadow-[0_0_15px_rgba(255,255,255,0.3)]' : 'bg-gray-700 text-white hover:bg-gray-600' }}">
                                {{ $isEquipped ? 'Ablegen' : 'Ausrüsten' }}
                            </button>
                        @else
                            <div class="mt-auto flex flex-col gap-2">
                                @if($item->price_funken)
                                    <button wire:click="buyWithFunken({{ $item->id }})" wire:confirm="Dieses Item für {{ $item->price_funken }} Funken freischalten?" @disabled($balance < $item->price_funken) class="w-full py-2.5 rounded-xl text-xs font-bold uppercase tracking-widest transition-all flex items-center justify-center gap-2 {{ $balance >= $item->price_funken ? 'bg-primary text-gray-900 hover:bg-primary-light' : 'bg-gray-700 text-gray-500 cursor-not-allowed' }}">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                                        {{ $item->price_funken }} Funken
                                    </button>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
