<div x-data="{
        mediaLightbox: {
            open: false,
            items: [],
            index: 0,
            author: '',
            rating: 5,
            text: ''
        },
        openMediaLightbox(items, startIndex = 0, meta = {}) {
            if (!items || !items.length) return;
            this.mediaLightbox.items = items;
            this.mediaLightbox.index = startIndex;
            this.mediaLightbox.author = meta.author || 'Deine Bewertung';
            this.mediaLightbox.rating = meta.rating || 5;
            this.mediaLightbox.text = meta.text || '';
            this.mediaLightbox.open = true;
        },
        closeMediaLightbox() {
            this.mediaLightbox.open = false;
        },
        nextMediaSlide() {
            if (!this.mediaLightbox.items.length) return;
            this.mediaLightbox.index = (this.mediaLightbox.index + 1) % this.mediaLightbox.items.length;
        },
        prevMediaSlide() {
            if (!this.mediaLightbox.items.length) return;
            this.mediaLightbox.index = (this.mediaLightbox.index - 1 + this.mediaLightbox.items.length) % this.mediaLightbox.items.length;
        }
     }"
     @keydown.escape.window="if (mediaLightbox.open) closeMediaLightbox()"
     @keydown.arrow-right.window="if (mediaLightbox.open) nextMediaSlide()"
     @keydown.arrow-left.window="if (mediaLightbox.open) prevMediaSlide()"
     class="w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 lg:py-10 min-h-full flex flex-col relative z-10 text-gray-700 dark:text-gray-300">

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

    <div class="mb-8">
        <x-shop.customer-feedback-banner :darkAware="true" :showActionButtons="false" />
    </div>

    @if(session()->has('success') || session()->has('reviews_notice'))
        <div class="mb-6 bg-emerald-500/10 border border-emerald-500/30 text-emerald-700 dark:text-emerald-400 px-5 py-3.5 rounded-2xl flex items-center gap-2.5 text-xs font-bold">
            <span class="text-[#C5A059]">✓</span>
            <span>{{ session('success') ?? session('reviews_notice') }}</span>
        </div>
    @endif

    @if(session()->has('error'))
        <div class="mb-6 bg-red-500/10 border border-red-500/30 text-red-700 dark:text-red-400 px-5 py-3.5 rounded-2xl flex items-center gap-2.5 text-xs font-bold">
            <i class="bi bi-exclamation-triangle"></i>
            <span>{{ session('error') }}</span>
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
                        $isCurrentReviewEditing = $isEditing && (string) $userReviewId === (string) $review->id;
                        $statusBadge = match($review->status) {
                            'approved' => ['label' => 'Veröffentlicht', 'class' => 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-500/30'],
                            'rejected' => ['label' => 'Abgelehnt', 'class' => 'bg-red-500/10 text-red-700 dark:text-red-400 border-red-500/30'],
                            default => ['label' => 'In Prüfung', 'class' => 'bg-blue-500/10 text-blue-700 dark:text-blue-400 border-blue-500/30'],
                        };
                        $reviewMediaList = (!empty($review->media) && is_array($review->media))
                            ? collect($review->media)
                                ->filter(fn ($m) => is_string($m) && trim($m) !== '')
                                ->map(function ($mPath) {
                                    $mExt = strtolower(pathinfo((string) $mPath, PATHINFO_EXTENSION));
                                    return [
                                        'url' => '/storage/' . ltrim((string) $mPath, '/'),
                                        'type' => in_array($mExt, ['mp4', 'mov', 'webm'], true) ? 'video' : 'image',
                                    ];
                                })
                                ->values()
                                ->all()
                            : [];
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

                                        @if($review->is_anonymous)
                                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 border border-gray-200 dark:border-gray-700 inline-flex items-center gap-1">
                                                <i class="bi bi-incognito"></i> Anonym
                                            </span>
                                        @endif
                                    </div>

                                    @if(!$isCurrentReviewEditing)
                                        <div class="flex items-center gap-1 mt-2">
                                            @for($s = 1; $s <= 5; $s++)
                                                <svg class="w-4 h-4 text-[#CBD5E1] dark:text-[#64748B]"
                                                     viewBox="0 0 24 24"
                                                     fill="{{ $s <= (int) $review->rating ? '#F5B301' : 'none' }}"
                                                     stroke="{{ $s <= (int) $review->rating ? '#D97706' : 'currentColor' }}"
                                                     stroke-width="1.8">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z" />
                                                </svg>
                                            @endfor
                                            <span class="text-[11px] text-gray-400 dark:text-gray-500 ml-2">{{ $review->created_at->format('d.m.Y') }}</span>
                                        </div>

                                        @if(!empty($review->title))
                                            <p class="mt-2.5 text-sm font-bold text-gray-900 dark:text-white">{{ $review->title }}</p>
                                        @endif
                                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300 leading-relaxed whitespace-pre-line">{{ $review->content }}</p>

                                        @if(count($reviewMediaList) > 0)
                                            <div class="flex flex-wrap gap-3 mt-4 pt-3.5 border-t border-gray-100 dark:border-gray-800">
                                                @foreach($reviewMediaList as $mIdx => $mItem)
                                                    <button type="button"
                                                            @click="openMediaLightbox(@js($reviewMediaList), {{ $mIdx }}, {
                                                                author: @js($product->name ?? 'Deine Bewertung'),
                                                                rating: {{ (int) $review->rating }},
                                                                text: @js($review->content)
                                                            })"
                                                            class="w-24 h-24 sm:w-28 sm:h-28 rounded-2xl overflow-hidden border border-[#C5A059]/35 bg-gradient-to-br from-[#fdfbf7] via-[#f9f4e8] to-[#f4ecdc] dark:from-gray-800 dark:via-gray-850 dark:to-gray-900 shadow-sm relative group cursor-pointer transition-all duration-300 hover:border-[#C5A059] hover:shadow-md focus:outline-none p-1.5 flex items-center justify-center">
                                                        @if($mItem['type'] === 'video')
                                                            <video src="{{ $mItem['url'] }}" muted preload="metadata" class="relative z-10 max-w-full max-h-full object-contain rounded-xl transition-transform duration-300 group-hover:scale-105"></video>
                                                            <span class="absolute inset-0 z-20 flex items-center justify-center">
                                                                <span class="w-8 h-8 rounded-full bg-white/95 border border-[#C5A059] text-[#8a6820] flex items-center justify-center shadow-md">
                                                                    <svg class="w-4 h-4 ml-0.5" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                                                                </span>
                                                            </span>
                                                        @else
                                                            <img src="{{ $mItem['url'] }}" alt="Bewertungsbild {{ $mIdx + 1 }}" class="relative z-10 max-w-full max-h-full object-contain rounded-xl transition-transform duration-300 group-hover:scale-105">
                                                        @endif
                                                    </button>
                                                @endforeach
                                            </div>
                                        @endif
                                    @endif
                                </div>
                            </div>

                            @if(!$isCurrentReviewEditing)
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

                        {{-- ZENTRALE „DEINE BEWERTUNG BEARBEITEN“-KOMPONENTE --}}
                        @if($isCurrentReviewEditing)
                            <div wire:key="portal-review-form-edit-{{ $review->id }}" class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-800">
                                <x-shop.product-review-experience-form
                                    :isEditing="true"
                                    :showCancel="true"
                                    :productName="$activeFormProductName ?? ($product->name ?? null)"
                                    :darkAware="true"
                                    :existingMedia="$existingMedia"
                                    :accumulatedMedia="$accumulatedMedia"
                                />
                            </div>
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
                                $isCurrentProductCreating = !$isEditing && (string) $targetProductId === (string) $product->id;
                            @endphp
                            <div wire:key="unreviewed-prod-{{ $product->id }}"
                                 class="bg-white dark:bg-gray-900/80 border border-gray-200 dark:border-gray-800 rounded-3xl p-5 sm:p-6 shadow-sm flex flex-col gap-4 {{ $isCurrentProductCreating ? 'md:col-span-2' : '' }}">
                                <div class="flex items-center justify-between gap-4">
                                    <div class="flex items-center gap-3.5 min-w-0">
                                        <div class="w-14 h-14 rounded-2xl bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 overflow-hidden shrink-0 flex items-center justify-center">
                                            @if($pImg)
                                                <img src="{{ $pImg }}" alt="{{ $product->name }}" class="w-full h-full object-cover">
                                            @else
                                                <svg class="w-5 h-5 text-gray-400 dark:text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                                            @endif
                                        </div>
                                        @if(!empty($product->slug))
                                            <a href="{{ route('product.show', $product->slug) }}" class="text-sm sm:text-base font-bold text-gray-900 dark:text-white hover:text-[#C5A059] transition-colors truncate">
                                                {{ $product->name }}
                                            </a>
                                        @else
                                            <span class="text-sm sm:text-base font-bold text-gray-900 dark:text-white truncate">{{ $product->name }}</span>
                                        @endif
                                    </div>

                                    @if(!$isCurrentProductCreating)
                                        <button type="button"
                                                wire:click="startCreateProductReview('{{ $product->id }}')"
                                                class="px-4 py-2 rounded-xl bg-[#C5A059] text-xs font-black uppercase tracking-widest text-gray-900 hover:bg-[#d4b06a] transition-colors shrink-0">
                                            Bewerten
                                        </button>
                                    @endif
                                </div>

                                @if($isCurrentProductCreating)
                                    <div wire:key="portal-review-form-create-{{ $product->id }}" class="pt-4 border-t border-gray-200 dark:border-gray-800">
                                        <x-shop.product-review-experience-form
                                            :isEditing="false"
                                            :showCancel="true"
                                            :productName="$activeFormProductName ?? $product->name"
                                            :darkAware="true"
                                            :existingMedia="$existingMedia"
                                            :accumulatedMedia="$accumulatedMedia"
                                        />
                                    </div>
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
                                        class="p-1.5 rounded-xl hover:bg-gray-100 dark:hover:bg-gray-800 transition-transform hover:scale-110 focus:outline-none cursor-pointer"
                                        aria-label="{{ $card['title'] }} {{ $star }} Sterne">
                                    <svg class="w-7 h-7 text-[#CBD5E1] dark:text-[#64748B] transition-all duration-150"
                                         viewBox="0 0 24 24"
                                         fill="{{ $star <= $currentRating ? '#F5B301' : 'none' }}"
                                         stroke="{{ $star <= $currentRating ? '#D97706' : 'currentColor' }}"
                                         stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z" />
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

    {{-- FREUNDLICHES, HELLES LIGHTBOX SLIDER MODAL FÜR BEWERTUNGSBILDER & VIDEOS (IM SELBEN FENSTER) --}}
    <template x-teleport="body">
        <div x-show="mediaLightbox.open"
             style="display: none;"
             x-transition.opacity.duration.250ms
             class="fixed inset-0 z-[9800] flex items-center justify-center p-3 sm:p-6">

            <div class="fixed inset-0 bg-gray-900/35 backdrop-blur-sm"
                 @click="closeMediaLightbox()"></div>

            <template x-if="mediaLightbox.open && mediaLightbox.items.length > 0">
                <div class="relative z-10 w-full max-w-3xl rounded-[2rem] bg-white border-2 border-[#C5A059]/45 shadow-[0_25px_80px_rgba(138,104,32,0.22)] overflow-hidden flex flex-col max-h-[92vh]"
                     @click.stop>

                    <div class="flex items-center justify-between px-5 sm:px-7 py-4 border-b border-amber-100 bg-gradient-to-r from-[#fdfbf7] via-amber-50/45 to-[#fdfbf7]">
                        <div class="flex items-center gap-3.5 min-w-0">
                            <div class="w-11 h-11 rounded-full bg-amber-50 text-[#8a6820] font-serif font-bold text-base flex items-center justify-center border-2 border-white shadow-sm ring-2 ring-[#C5A059]/25 shrink-0 select-none"
                                 x-text="(mediaLightbox.author || 'B').charAt(0).toUpperCase()"></div>
                            <div class="min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="font-serif font-bold text-gray-900 text-base sm:text-lg truncate" x-text="mediaLightbox.author"></span>
                                    <div class="flex items-center gap-0.5">
                                        <template x-for="star in 5" :key="star">
                                            <svg class="w-4 h-4 text-[#CBD5E1]"
                                                 viewBox="0 0 24 24"
                                                 :fill="star <= mediaLightbox.rating ? '#F5B301' : 'none'"
                                                 :stroke="star <= mediaLightbox.rating ? '#D97706' : 'currentColor'"
                                                 stroke-width="1.8">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z" />
                                            </svg>
                                        </template>
                                    </div>
                                    <span class="hidden sm:inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-emerald-50 border border-emerald-200/80 text-emerald-700 text-[10px] font-bold">
                                        <span>✓</span>
                                        <span>Verifizierter Kauf</span>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-2.5 shrink-0">
                            <template x-if="mediaLightbox.items.length > 1">
                                <span class="px-3 py-1 rounded-full bg-amber-50 border border-[#C5A059]/40 text-xs font-bold text-[#8a6820]"
                                      x-text="(mediaLightbox.index + 1) + ' / ' + mediaLightbox.items.length"></span>
                            </template>
                            <button type="button"
                                    @click="closeMediaLightbox()"
                                    class="w-9 h-9 rounded-full bg-white hover:bg-amber-50 text-gray-500 hover:text-gray-900 border border-gray-200 hover:border-[#C5A059] shadow-xs flex items-center justify-center transition-all cursor-pointer"
                                    aria-label="Großansicht schließen">
                                ✕
                            </button>
                        </div>
                    </div>

                    <div class="relative flex-1 bg-gradient-to-b from-[#fdfbf7] via-[#f7f2e4] to-[#fdfbf7] flex items-center justify-center min-h-[300px] sm:min-h-[450px] max-h-[65vh] overflow-hidden select-none p-4 sm:p-6">
                        <template x-if="mediaLightbox.items[mediaLightbox.index]">
                            <div class="relative w-full h-full flex items-center justify-center">
                                <template x-if="mediaLightbox.items[mediaLightbox.index].type === 'video'">
                                    <video :key="mediaLightbox.items[mediaLightbox.index].url"
                                           :src="mediaLightbox.items[mediaLightbox.index].url"
                                           controls autoplay playsinline
                                           class="relative z-10 max-h-[58vh] max-w-full rounded-2xl shadow-[0_15px_40px_rgba(138,104,32,0.18)] border-2 border-white ring-1 ring-[#C5A059]/30 bg-white"></video>
                                </template>
                                <template x-if="mediaLightbox.items[mediaLightbox.index].type !== 'video'">
                                    <div class="relative w-full h-full flex items-center justify-center">
                                        <img :src="mediaLightbox.items[mediaLightbox.index].url"
                                             alt=""
                                             aria-hidden="true"
                                             class="absolute inset-0 w-full h-full object-cover blur-2xl opacity-20 scale-110 pointer-events-none">
                                        <img :key="mediaLightbox.items[mediaLightbox.index].url"
                                             :src="mediaLightbox.items[mediaLightbox.index].url"
                                             :alt="'Bewertungsbild zu ' + mediaLightbox.author"
                                             class="relative z-10 max-h-[58vh] max-w-full object-contain rounded-2xl shadow-[0_15px_40px_rgba(138,104,32,0.18)] border-2 border-white ring-1 ring-[#C5A059]/25 bg-white">
                                    </div>
                                </template>
                            </div>
                        </template>

                        <template x-if="mediaLightbox.items.length > 1">
                            <div>
                                <button type="button"
                                        @click="prevMediaSlide()"
                                        class="absolute left-3 sm:left-5 top-1/2 -translate-y-1/2 z-20 w-11 h-11 rounded-full bg-white/95 hover:bg-[#C5A059] text-[#8a6820] hover:text-white border border-[#C5A059]/45 flex items-center justify-center shadow-md hover:shadow-lg transition-all cursor-pointer"
                                        aria-label="Vorherige Aufnahme">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                                </button>
                                <button type="button"
                                        @click="nextMediaSlide()"
                                        class="absolute right-3 sm:right-5 top-1/2 -translate-y-1/2 z-20 w-11 h-11 rounded-full bg-white/95 hover:bg-[#C5A059] text-[#8a6820] hover:text-white border border-[#C5A059]/45 flex items-center justify-center shadow-md hover:shadow-lg transition-all cursor-pointer"
                                        aria-label="Nächste Aufnahme">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                                </button>
                            </div>
                        </template>
                    </div>

                    <div class="px-5 sm:px-7 py-4 bg-white border-t border-amber-100 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div class="flex items-start gap-2.5 flex-1 min-w-0" x-show="mediaLightbox.text">
                            <span class="text-2xl leading-none font-serif text-[#C5A059] select-none" aria-hidden="true">„</span>
                            <p class="text-xs sm:text-sm text-gray-700 italic line-clamp-2"
                               x-text="mediaLightbox.text + '“'"></p>
                        </div>

                        <template x-if="mediaLightbox.items.length > 1">
                            <div class="flex items-center gap-2.5 shrink-0 justify-center sm:justify-end">
                                <template x-for="(item, idx) in mediaLightbox.items" :key="idx">
                                    <button type="button"
                                            @click="mediaLightbox.index = idx"
                                            :class="mediaLightbox.index === idx ? 'ring-2 ring-[#C5A059] border-white opacity-100 scale-105 shadow-sm' : 'opacity-60 hover:opacity-95 border-amber-200/70'"
                                            class="relative w-14 h-14 rounded-xl overflow-hidden border transition-all cursor-pointer bg-[#fdfbf7] shrink-0">
                                        <template x-if="item.type === 'video'">
                                            <div class="w-full h-full relative">
                                                <video :src="item.url" muted preload="metadata" class="w-full h-full object-cover"></video>
                                                <span class="absolute inset-0 flex items-center justify-center bg-black/25 text-white">
                                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                                                </span>
                                            </div>
                                        </template>
                                        <template x-if="item.type !== 'video'">
                                            <img :src="item.url" class="w-full h-full object-cover" alt="">
                                        </template>
                                    </button>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>
            </template>
        </div>
    </template>

</div>
