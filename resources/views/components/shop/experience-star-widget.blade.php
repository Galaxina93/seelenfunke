@props([
    'area',
    'productId' => null,
    'requireFunkenreichVisit' => false,
    'darkAware' => false,
])

@php
    $areaKey = is_string($area ?? null) ? trim($area) : '';
    $customer = auth()->guard('customer')->user();

    $canRate = $customer !== null
        && (!method_exists($customer, 'trashed') || !$customer->trashed())
        && (method_exists($customer, 'hasVerifiedEmail') ? $customer->hasVerifiedEmail() : !empty($customer->email_verified_at))
        && empty($customer->needs_password_change);

    $alreadyRated = false;
    if ($canRate && $areaKey !== '') {
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('support_experience_reviews')) {
                if (class_exists(\App\Models\Support\SupportExperienceReview::class)) {
                    $alreadyRated = \App\Models\Support\SupportExperienceReview::query()
                        ->where('customer_id', $customer->id)
                        ->where('area', $areaKey)
                        ->exists();
                } else {
                    $reviewQuery = \Illuminate\Support\Facades\DB::table('support_experience_reviews')
                        ->where('customer_id', $customer->id)
                        ->where('area', $areaKey);
                    if (\Illuminate\Support\Facades\Schema::hasColumn('support_experience_reviews', 'deleted_at')) {
                        $reviewQuery->whereNull('deleted_at');
                    }
                    $alreadyRated = $reviewQuery->exists();
                }
            }
        } catch (\Throwable $_e) {
            $alreadyRated = false;
        }
    }

    $shouldRender = $canRate && $areaKey !== '' && !$alreadyRated;

    $labels = [
        'configurator' => 'Konfigurator bewerten',
        'funkenreich' => 'Funkenreich bewerten',
        'calculator' => 'Kalkulator bewerten',
    ];
    $widgetLabel = $labels[$areaKey] ?? 'Erlebnis bewerten';

    $surfaceClass = $darkAware
        ? 'bg-[#FAF8F5] dark:bg-gray-900/80 border border-[#C5A059]/30 text-gray-800 dark:text-gray-200'
        : 'bg-[#FAF8F5] border border-[#E8DFCE] text-gray-800';

    $dividerClass = $darkAware
        ? 'border-[#C5A059]/20 dark:border-gray-800'
        : 'border-[#E8DFCE]';

    $inputClass = $darkAware
        ? 'bg-white dark:bg-gray-950 border-[#E8DFCE] dark:border-gray-700 text-gray-800 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500'
        : 'bg-white border-[#E8DFCE] text-gray-800 placeholder-gray-400';

    $changeLinkClass = $darkAware
        ? 'text-[#9A7B3E] dark:text-[#C5A059] hover:text-[#7A5F2B] dark:hover:text-[#e2c17e]'
        : 'text-[#9A7B3E] hover:text-[#7A5F2B]';
@endphp

@if($shouldRender)
    <div wire:ignore
         data-snapshot-ignore="true"
         data-experience-star-widget="{{ $areaKey }}"
         x-data="{
             area: @js($areaKey),
             productId: @js($productId),
             requireVisit: @js((bool) $requireFunkenreichVisit),
             isVisible: @js(!(bool) $requireFunkenreichVisit),
             rating: 0,
             hoverRating: 0,
             comment: '',
             mode: 'idle',
             saving: false,
             init() {
                 if (this.requireVisit) {
                     this.isVisible = this.hasVisitedFunkenreich();
                 }
             },
             hasVisitedFunkenreich() {
                 return Boolean(typeof Storage !== 'undefined' && localStorage.getItem('sf_visited_funkenreich_v1') === '1');
             },
             onFunkenreichVisited() {
                 if (this.requireVisit) {
                     this.isVisible = true;
                 }
             },
             resolveCsrfToken() {
                 const meta = document.querySelector('meta[name=csrf-token]');
                 return meta ? meta.getAttribute('content') : @js(csrf_token());
             },
             persistReview(payload, targetMode) {
                 this.saving = true;
                 fetch('/customer/experience-reviews', {
                     method: 'POST',
                     credentials: 'same-origin',
                     headers: {
                         'Content-Type': 'application/json',
                         'Accept': 'application/json',
                         'X-Requested-With': 'XMLHttpRequest',
                         'X-CSRF-TOKEN': this.resolveCsrfToken()
                     },
                     body: JSON.stringify(payload)
                 })
                 .then(() => {
                     this.mode = targetMode;
                 })
                 .catch(() => {
                     this.mode = targetMode;
                 })
                 .finally(() => {
                     this.saving = false;
                 });
             },
             selectStar(stars) {
                 this.rating = stars;
                 this.hoverRating = 0;
                 const payload = {
                     area: this.area,
                     rating: stars,
                     product_id: this.productId || null
                 };
                 if (stars >= 4) {
                     this.mode = 'done';
                     this.persistReview(payload, 'done');
                 } else {
                     this.mode = 'feedback';
                     this.persistReview(payload, 'feedback');
                     this.$nextTick(() => {
                         if (this.$refs && this.$refs.commentInput) {
                             this.$refs.commentInput.focus();
                         }
                     });
                 }
             },
             submitFeedback() {
                 if (this.saving || this.rating < 1) return;
                 const payload = {
                     area: this.area,
                     rating: this.rating,
                     comment: String(this.comment || '').trim(),
                     product_id: this.productId || null
                 };
                 this.mode = 'done';
                 this.persistReview(payload, 'done');
             },
             reopenStars() {
                 this.hoverRating = 0;
                 this.mode = 'editing';
             }
         }"
         @sf-funkenreich-visited.window="onFunkenreichVisited()"
         x-show="isVisible"
         @if($requireFunkenreichVisit) x-cloak style="display: none;" @endif
         {{ $attributes->merge(['class' => 'py-1.5 px-3 rounded-xl transition-colors ' . $surfaceClass]) }}>

        {{-- Aktive 5-Sterne-Zeile --}}
        <div x-show="mode !== 'done'" class="flex items-center justify-between gap-2 min-h-[22px]">
            <span class="text-[11px] sm:text-xs font-semibold tracking-tight truncate">{{ $widgetLabel }}</span>
            <div class="flex items-center gap-0.5 text-[#C5A059]/55 shrink-0" role="radiogroup" aria-label="{{ $widgetLabel }}">
                @for($star = 1; $star <= 5; $star++)
                    <button type="button"
                            @mouseenter="hoverRating = {{ $star }}"
                            @mouseleave="hoverRating = 0"
                            @focus="hoverRating = {{ $star }}"
                            @blur="hoverRating = 0"
                            @click="selectStar({{ $star }})"
                            aria-label="{{ $star }} {{ $star === 1 ? 'Stern' : 'Sterne' }}"
                            class="p-0.5 rounded focus:outline-none focus-visible:ring-1 focus-visible:ring-[#C5A059] transition-transform hover:scale-110 active:scale-95 cursor-pointer">
                        <svg class="w-[15px] h-[15px] transition-colors"
                             viewBox="0 0 24 24"
                             :fill="((hoverRating || rating) >= {{ $star }}) ? '#C5A059' : 'none'"
                             :stroke="((hoverRating || rating) >= {{ $star }}) ? '#C5A059' : 'currentColor'"
                             stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z" />
                        </svg>
                    </button>
                @endfor
            </div>
        </div>

        {{-- Ruhige Bestätigungszeile (ohne Auto-Hide) --}}
        <div x-show="mode === 'done'"
             x-cloak
             style="display: none;"
             class="flex items-center justify-between gap-2 min-h-[22px]">
            <div class="flex items-center gap-1.5 min-w-0">
                <div class="flex items-center gap-0.5 text-[#C5A059]/45 shrink-0" aria-hidden="true">
                    @for($star = 1; $star <= 5; $star++)
                        <svg class="w-[15px] h-[15px]"
                             viewBox="0 0 24 24"
                             :fill="rating >= {{ $star }} ? '#C5A059' : 'none'"
                             :stroke="rating >= {{ $star }} ? '#C5A059' : 'currentColor'"
                             stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z" />
                        </svg>
                    @endfor
                </div>
                <span class="text-xs opacity-40 select-none">·</span>
                <span class="text-[11px] sm:text-xs font-bold truncate">Danke!</span>
            </div>
            <button type="button"
                    @click="reopenStars()"
                    class="text-[11px] font-semibold underline underline-offset-2 transition-colors cursor-pointer shrink-0 {{ $changeLinkClass }}">
                Ändern
            </button>
        </div>

        {{-- Kompaktes 1-Zeilen-Verbesserungsfeld bei 1 bis 3 Sternen --}}
        <div x-show="mode === 'feedback'"
             x-cloak
             style="display: none;"
             x-collapse.duration.200ms
             class="pt-1.5 mt-1.5 border-t {{ $dividerClass }}">
            <div class="flex items-center gap-1.5">
                <input x-ref="commentInput"
                       type="text"
                       x-model="comment"
                       maxlength="500"
                       @keydown.stop
                       @keydown.enter.prevent.stop="submitFeedback()"
                       placeholder="Was können wir verbessern?"
                       class="flex-1 min-w-0 px-2.5 py-1 rounded-lg border text-xs focus:outline-none focus:border-[#C5A059] focus:ring-1 focus:ring-[#C5A059]/40 transition {{ $inputClass }}">
                <button type="button"
                        @click="submitFeedback()"
                        :disabled="saving"
                        class="shrink-0 px-2.5 py-1 rounded-lg bg-[#C5A059] hover:bg-[#b38e47] text-gray-950 text-[11px] font-bold transition cursor-pointer disabled:opacity-60">
                    Senden
                </button>
            </div>
        </div>
    </div>
@endif
