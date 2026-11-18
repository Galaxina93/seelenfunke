<div class="w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 lg:py-10 min-h-full flex flex-col relative z-10 text-gray-700 dark:text-gray-300">

    {{-- SEITEN-HEADER & TABS (Clean Design ohne Subtexte) --}}
    <div class="mb-8 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <h1 class="text-3xl sm:text-4xl font-serif font-bold text-gray-900 dark:text-white tracking-tight">Meine Bewertungen</h1>

        <div class="inline-flex bg-white dark:bg-gray-900 p-1.5 rounded-2xl border border-gray-200 dark:border-gray-800 shadow-sm w-full sm:w-auto">
            <button type="button"
                    wire:click="switchTab('products')"
                    class="flex-1 sm:flex-none px-5 py-2.5 rounded-xl text-xs font-black uppercase tracking-widest transition-all {{ $activeTab === 'products' ? 'bg-[#C5A059] text-gray-900 shadow-sm' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}">
                Produktbewertungen
            </button>
            <button type="button"
                    wire:click="switchTab('experience')"
                    class="flex-1 sm:flex-none px-5 py-2.5 rounded-xl text-xs font-black uppercase tracking-widest transition-all {{ $activeTab === 'experience' ? 'bg-[#C5A059] text-gray-900 shadow-sm' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}">
                Erlebnis-Feedback
            </button>
        </div>
    </div>

    @if(session()->has('reviews_notice'))
        <div class="mb-6 bg-emerald-500/10 border border-emerald-500/30 text-emerald-700 dark:text-emerald-400 px-5 py-3.5 rounded-2xl flex items-center gap-2.5 text-xs font-bold">
            <span class="text-[#C5A059]">✓</span>
            <span>{{ session('reviews_notice') }}</span>
        </div>
    @endif

    {{-- ================================================================= --}}
    {{-- TAB 1: PRODUKTBEWERTUNGEN --}}
    {{-- ================================================================= --}}
    @if($activeTab === 'products')
        <div class="space-y-8">
            <div class="space-y-4">
                @forelse($productReviews as $review)
                    @php
                        $product = $review->product;
                        $imgUrl = $product ? ($product->resolveMainImageMeta()['url'] ?? null) : null;
                        $statusBadge = match($review->status) {
                            'approved' => ['label' => 'Veröffentlicht', 'class' => 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-500/30'],
                            'rejected' => ['label' => 'Abgelehnt', 'class' => 'bg-red-500/10 text-red-700 dark:text-red-400 border-red-500/30'],
                            default => ['label' => 'In Prüfung', 'class' => 'bg-blue-500/10 text-blue-700 dark:text-blue-400 border-blue-500/30'],
                        };
                    @endphp

                    <div wire:key="cust-prod-review-{{ $review->id }}" class="bg-white dark:bg-gray-900/80 border border-gray-200 dark:border-gray-800 rounded-3xl p-5 sm:p-6 shadow-sm">
                        <div class="flex flex-col md:flex-row md:items-start justify-between gap-5">
                            <div class="flex items-start gap-4 min-w-0 flex-1">
                                <div class="w-16 h-16 rounded-2xl bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 overflow-hidden shrink-0 flex items-center justify-center">
                                    @if($imgUrl)
                                        <img src="{{ $imgUrl }}" alt="{{ $product->name ?? 'Produkt' }}" class="w-full h-full object-cover">
                                    @else
                                        <svg class="w-6 h-6 text-gray-400 dark:text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                                    @endif
                                </div>

                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2.5">
                                        @if($product && !empty($product->slug))
                                            <a href="{{ route('product.show', $product->slug) }}" class="text-base font-bold text-gray-900 dark:text-white hover:text-[#C5A059] transition-colors truncate">
                                                {{ $product->name }}
                                            </a>
                                        @else
                                            <span class="text-base font-bold text-gray-900 dark:text-white truncate">{{ $product->name ?? 'Manufaktur-Unikat' }}</span>
                                        @endif

                                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-widest border {{ $statusBadge['class'] }}">
                                            {{ $statusBadge['label'] }}
                                        </span>
                                    </div>

                                    @if($editingProductReviewId !== $review->id)
                                        <div class="flex items-center gap-1 mt-2">
                                            @for($s = 1; $s <= 5; $s++)
                                                <svg class="w-4 h-4 {{ $s <= (int) $review->rating ? 'text-[#C5A059]' : 'text-gray-300 dark:text-gray-700' }}" viewBox="0 0 20 20" fill="currentColor">
                                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                                </svg>
                                            @endfor
                                            <span class="text-[11px] text-gray-400 dark:text-gray-500 ml-2">{{ $review->created_at->format('d.m.Y') }}</span>
                                        </div>

                                        @if(!empty($review->title))
                                            <p class="mt-2.5 text-sm font-bold text-gray-900 dark:text-white">{{ $review->title }}</p>
                                        @endif
                                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300 leading-relaxed whitespace-pre-line">{{ $review->content }}</p>
                                    @endif
                                </div>
                            </div>

                            @if($editingProductReviewId !== $review->id)
                                <div class="flex items-center gap-2 shrink-0">
                                    <button type="button"
                                            wire:click="startEditProductReview('{{ $review->id }}')"
                                            class="px-4 py-2 rounded-xl bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-xs font-bold text-gray-700 dark:text-gray-200 hover:border-[#C5A059] hover:text-[#C5A059] transition-colors">
                                        Bearbeiten
                                    </button>
                                    <button type="button"
                                            wire:click="deleteProductReview('{{ $review->id }}')"
                                            wire:confirm="Bewertung wirklich löschen?"
                                            class="px-4 py-2 rounded-xl bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-xs font-bold text-gray-500 dark:text-gray-400 hover:border-red-500/40 hover:text-red-600 dark:hover:text-red-400 transition-colors">
                                        Löschen
                                    </button>
                                </div>
                            @endif
                        </div>

                        {{-- INLINE BEARBEITUNG --}}
                        @if($editingProductReviewId === $review->id)
                            <form wire:submit.prevent="saveProductReview" class="mt-5 pt-5 border-t border-gray-200 dark:border-gray-800 space-y-4">
                                <div class="flex items-center gap-1.5">
                                    @for($s = 1; $s <= 5; $s++)
                                        <button type="button"
                                                wire:click="$set('editProductRating', {{ $s }})"
                                                class="p-1 focus:outline-none transition-transform hover:scale-110"
                                                aria-label="{{ $s }} Sterne">
                                            <svg class="w-6 h-6 {{ $s <= $editProductRating ? 'text-[#C5A059]' : 'text-gray-300 dark:text-gray-700' }}" viewBox="0 0 20 20" fill="currentColor">
                                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                            </svg>
                                        </button>
                                    @endfor
                                </div>

                                <input type="text"
                                       wire:model="editProductTitle"
                                       maxlength="255"
                                       placeholder="Kurzer Titel (optional)"
                                       class="w-full rounded-xl bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-700 px-4 py-2.5 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:border-[#C5A059] focus:ring-1 focus:ring-[#C5A059] outline-none">

                                <textarea wire:model="editProductContent"
                                          rows="3"
                                          maxlength="1000"
                                          placeholder="Deine Erfahrung mit diesem Unikat..."
                                          class="w-full rounded-xl bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-700 px-4 py-3 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:border-[#C5A059] focus:ring-1 focus:ring-[#C5A059] outline-none"></textarea>

                                @error('editProductContent')
                                    <p class="text-xs font-bold text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror

                                <div class="flex items-center justify-end gap-2">
                                    <button type="button"
                                            wire:click="cancelEditProductReview"
                                            class="px-4 py-2 rounded-xl bg-gray-100 dark:bg-gray-800 text-xs font-bold text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white transition-colors">
                                        Abbrechen
                                    </button>
                                    <button type="submit"
                                            class="px-5 py-2 rounded-xl bg-[#C5A059] text-xs font-black uppercase tracking-widest text-gray-900 hover:bg-[#d4b06a] transition-colors">
                                        Speichern
                                    </button>
                                </div>
                            </form>
                        @endif
                    </div>
                @empty
                    <div class="bg-white dark:bg-gray-900/80 border border-gray-200 dark:border-gray-800 rounded-3xl p-10 text-center">
                        <span class="text-xs font-black uppercase tracking-widest text-gray-500 dark:text-gray-400">Noch keine Produktbewertungen abgegeben</span>
                    </div>
                @endforelse
            </div>

            {{-- GEKAUFTE PHYSISCHE PRODUKTE OHNE BEWERTUNG --}}
            @if($unreviewedProducts->isNotEmpty())
                <div class="space-y-4">
                    <h2 class="text-lg font-serif font-bold text-gray-900 dark:text-white">Offene Einkäufe bewerten</h2>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @foreach($unreviewedProducts as $product)
                            @php
                                $pImg = $product->resolveMainImageMeta()['url'] ?? null;
                            @endphp
                            <div wire:key="unreviewed-prod-{{ $product->id }}" class="bg-white dark:bg-gray-900/80 border border-gray-200 dark:border-gray-800 rounded-3xl p-5 shadow-sm flex flex-col gap-4">
                                <div class="flex items-center justify-between gap-4">
                                    <div class="flex items-center gap-3.5 min-w-0">
                                        <div class="w-14 h-14 rounded-2xl bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 overflow-hidden shrink-0 flex items-center justify-center">
                                            @if($pImg)
                                                <img src="{{ $pImg }}" alt="{{ $product->name }}" class="w-full h-full object-cover">
                                            @else
                                                <svg class="w-5 h-5 text-gray-400 dark:text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                                            @endif
                                        </div>
                                        <span class="text-sm font-bold text-gray-900 dark:text-white truncate">{{ $product->name }}</span>
                                    </div>

                                    @if($creatingProductId !== $product->id)
                                        <button type="button"
                                                wire:click="startCreateProductReview('{{ $product->id }}')"
                                                class="px-4 py-2 rounded-xl bg-[#C5A059] text-xs font-black uppercase tracking-widest text-gray-900 hover:bg-[#d4b06a] transition-colors shrink-0">
                                            Bewerten
                                        </button>
                                    @endif
                                </div>

                                @if($creatingProductId === $product->id)
                                    <form wire:submit.prevent="submitNewProductReview" class="pt-3 border-t border-gray-200 dark:border-gray-800 space-y-3">
                                        <div class="flex items-center gap-1">
                                            @for($s = 1; $s <= 5; $s++)
                                                <button type="button"
                                                        wire:click="$set('newProductRating', {{ $s }})"
                                                        class="p-1 focus:outline-none transition-transform hover:scale-110"
                                                        aria-label="{{ $s }} Sterne">
                                                    <svg class="w-6 h-6 {{ $s <= $newProductRating ? 'text-[#C5A059]' : 'text-gray-300 dark:text-gray-700' }}" viewBox="0 0 20 20" fill="currentColor">
                                                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                                    </svg>
                                                </button>
                                            @endfor
                                        </div>

                                        <input type="text"
                                               wire:model="newProductTitle"
                                               maxlength="255"
                                               placeholder="Kurzer Titel (optional)"
                                               class="w-full rounded-xl bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-700 px-3.5 py-2 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:border-[#C5A059] focus:ring-1 focus:ring-[#C5A059] outline-none">

                                        <textarea wire:model="newProductContent"
                                                  rows="3"
                                                  maxlength="1000"
                                                  placeholder="Deine Erfahrung..."
                                                  class="w-full rounded-xl bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-700 px-3.5 py-2.5 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:border-[#C5A059] focus:ring-1 focus:ring-[#C5A059] outline-none"></textarea>

                                        @error('newProductContent')
                                            <p class="text-xs font-bold text-red-600 dark:text-red-400">{{ $message }}</p>
                                        @enderror

                                        <div class="flex items-center justify-end gap-2">
                                            <button type="button"
                                                    wire:click="cancelCreateProductReview"
                                                    class="px-3.5 py-2 rounded-xl bg-gray-100 dark:bg-gray-800 text-xs font-bold text-gray-600 dark:text-gray-300">
                                                Abbrechen
                                            </button>
                                            <button type="submit"
                                                    class="px-4 py-2 rounded-xl bg-[#C5A059] text-xs font-black uppercase tracking-widest text-gray-900 hover:bg-[#d4b06a] transition-colors">
                                                Absenden
                                            </button>
                                        </div>
                                    </form>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    @endif

    {{-- ================================================================= --}}
    {{-- TAB 2: ERLEBNIS-FEEDBACK --}}
    {{-- ================================================================= --}}
    @if($activeTab === 'experience')
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            @foreach($experienceCards as $card)
                @php
                    $area = $card['area'];
                    $currentRating = (int) ($experienceRatings[$area] ?? 0);
                    $isSaved = (bool) ($experienceSaved[$area] ?? false);
                @endphp

                <div wire:key="exp-card-{{ $area }}" class="bg-white dark:bg-gray-900/80 border border-gray-200 dark:border-gray-800 rounded-3xl p-6 shadow-sm flex flex-col justify-between gap-5">
                    <div class="space-y-4">
                        <div class="flex items-center justify-between gap-3">
                            <h2 class="text-lg font-serif font-bold text-gray-900 dark:text-white">{{ $card['title'] }}</h2>
                            @if($isSaved)
                                <span class="text-xs font-bold text-[#C5A059]">✓ Gespeichert</span>
                            @elseif($currentRating === 0)
                                <span class="text-[11px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">Noch nicht bewertet</span>
                            @else
                                <span class="text-xs font-bold text-gray-600 dark:text-gray-300">{{ $currentRating }} / 5 ★</span>
                            @endif
                        </div>

                        <div class="flex items-center gap-1.5">
                            @for($star = 1; $star <= 5; $star++)
                                <button type="button"
                                        wire:click="rateExperience('{{ $area }}', {{ $star }})"
                                        class="p-1.5 rounded-xl hover:bg-gray-100 dark:hover:bg-gray-800 transition-transform hover:scale-110 focus:outline-none"
                                        aria-label="{{ $card['title'] }} {{ $star }} Sterne">
                                    <svg class="w-7 h-7 {{ $star <= $currentRating ? 'text-[#C5A059]' : 'text-gray-300 dark:text-gray-700' }}" viewBox="0 0 20 20" fill="currentColor">
                                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                    </svg>
                                </button>
                            @endfor
                        </div>
                    </div>

                    @if($currentRating >= 1 && $currentRating <= 3)
                        <div class="pt-4 border-t border-gray-200 dark:border-gray-800 space-y-3">
                            <label for="exp-comment-{{ $area }}" class="block text-xs font-bold text-gray-700 dark:text-gray-300">Was können wir verbessern?</label>
                            <textarea id="exp-comment-{{ $area }}"
                                      wire:model="experienceComments.{{ $area }}"
                                      rows="3"
                                      maxlength="500"
                                      placeholder="Was können wir verbessern?"
                                      class="w-full rounded-xl bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-700 px-3.5 py-2.5 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:border-[#C5A059] focus:ring-1 focus:ring-[#C5A059] outline-none"></textarea>
                            <div class="flex justify-end">
                                <button type="button"
                                        wire:click="saveExperienceFeedback('{{ $area }}')"
                                        class="px-4 py-2 rounded-xl bg-[#C5A059] text-xs font-black uppercase tracking-widest text-gray-900 hover:bg-[#d4b06a] transition-colors">
                                    Speichern
                                </button>
                            </div>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

</div>
