<div class="min-h-screen relative flex items-center justify-center py-14 px-4 sm:px-6 lg:px-8 bg-gradient-to-br from-[#FAF8F5] via-white to-[#F3EDE2] text-gray-900 overflow-hidden"
     style="--theme-color: {{ $themeColor }}; --theme-color-10: {{ $themeColor }}1A; --theme-color-20: {{ $themeColor }}33; --theme-color-40: {{ $themeColor }}66; --theme-color-50: {{ $themeColor }}80;">

    <!-- Subtle Light Ambient Background Effects -->
    <div class="absolute inset-0 z-0 pointer-events-none">
        <div class="absolute -top-16 -left-16 w-80 h-80 bg-[var(--theme-color)] rounded-full filter blur-[110px] opacity-15"></div>
        <div class="absolute top-1/4 -right-16 w-72 h-72 bg-[var(--theme-color)] rounded-full filter blur-[120px] opacity-10"></div>
        <div class="absolute -bottom-16 left-1/3 w-80 h-80 bg-amber-200 rounded-full filter blur-[120px] opacity-25"></div>
    </div>

    <!-- Main Container -->
    <div class="max-w-md w-full space-y-8 z-10 relative">

        <!-- Profile Header -->
        <div class="text-center animate-fade-in-up">
            <div class="relative inline-block">
                @if($profileImage)
                    <img src="{{ asset($profileImage) }}" alt="Profilbild"
                         class="mx-auto h-24 w-24 rounded-full object-cover bg-white border-2 border-[var(--theme-color)] p-0.5 shadow-lg">
                @else
                    <div class="mx-auto h-24 w-24 rounded-full bg-white border-2 border-[var(--theme-color)] flex items-center justify-center shadow-lg">
                        <x-heroicon-o-user class="h-10 w-10 text-[var(--theme-color)]" />
                    </div>
                @endif
            </div>

            <h1 class="mt-5 text-center text-2xl sm:text-3xl font-serif font-bold text-gray-900 tracking-tight">
                {{ shop_setting('owner_proprietor', shop_setting('shop_name', 'Mein Seelenfunke')) }}
            </h1>
            <p class="mt-1.5 text-center text-sm text-gray-500 font-medium">
                Willkommen auf meiner digitalen Visitenkarte
            </p>
        </div>

        <!-- Links List (Alle Links öffnen in einem neuen Tab) -->
        <div class="mt-8 space-y-3.5">
            @foreach($links as $link)
                @php
                    $resolvedHref = $link->resolved_url;
                @endphp

                @if($link->type === 'secure')
                    <button type="button"
                            wire:click="handleLinkClick('{{ $link->id }}', '{{ $link->url }}', '{{ $link->type }}')"
                            class="w-full group relative overflow-hidden rounded-2xl bg-white/95 border border-gray-200/90 p-4 transition-all duration-300 hover:bg-white hover:border-[var(--theme-color)] hover:-translate-y-0.5 shadow-sm hover:shadow-md">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-4">
                                <div class="flex-shrink-0">
                                    <div class="p-2.5 bg-[var(--theme-color-10)] rounded-xl group-hover:bg-[var(--theme-color-20)] transition-colors">
                                        @if($link->icon)
                                            <x-dynamic-component :component="'heroicon-o-' . $link->icon" class="h-6 w-6 text-[var(--theme-color)]" />
                                        @else
                                            <x-heroicon-o-link class="h-6 w-6 text-[var(--theme-color)]" />
                                        @endif
                                    </div>
                                </div>
                                <div class="flex-1 text-left">
                                    <p class="text-sm font-semibold text-gray-800 group-hover:text-gray-950 transition-colors">
                                        {{ $link->title }}
                                    </p>
                                </div>
                            </div>
                            <div class="flex-shrink-0 flex items-center gap-1.5">
                                <x-heroicon-o-arrow-top-right-on-square class="h-4 w-4 text-gray-400 group-hover:text-[var(--theme-color)] transition-colors transform group-hover:translate-x-0.5" />
                            </div>
                        </div>
                    </button>
                @else
                    <a href="{{ $resolvedHref }}"
                       target="_blank"
                       rel="noopener noreferrer"
                       wire:click="trackClick('{{ $link->id }}')"
                       class="block w-full group relative overflow-hidden rounded-2xl bg-white/95 border {{ $link->type === 'highlight' ? 'border-[var(--theme-color-50)] ring-1 ring-[var(--theme-color-20)]' : 'border-gray-200/90' }} p-4 transition-all duration-300 hover:bg-white hover:border-[var(--theme-color)] hover:-translate-y-0.5 shadow-sm hover:shadow-md">

                        @if($link->type === 'highlight')
                            <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-[var(--theme-color)] via-amber-400 to-[var(--theme-color)]"></div>
                        @endif

                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-4">
                                <div class="flex-shrink-0">
                                    <div class="p-2.5 bg-[var(--theme-color-10)] rounded-xl group-hover:bg-[var(--theme-color-20)] transition-colors">
                                        @if($link->icon)
                                            <x-dynamic-component :component="'heroicon-o-' . $link->icon" class="h-6 w-6 text-[var(--theme-color)]" />
                                        @else
                                            <x-heroicon-o-link class="h-6 w-6 text-[var(--theme-color)]" />
                                        @endif
                                    </div>
                                </div>
                                <div class="flex-1 text-left">
                                    <p class="text-sm font-semibold text-gray-800 group-hover:text-gray-950 transition-colors">
                                        {{ $link->title }}
                                    </p>
                                </div>
                            </div>
                            <div class="flex-shrink-0 flex items-center gap-1.5">
                                <x-heroicon-o-arrow-top-right-on-square class="h-4 w-4 text-gray-400 group-hover:text-[var(--theme-color)] transition-colors transform group-hover:translate-x-0.5" />
                            </div>
                        </div>
                    </a>
                @endif
            @endforeach
        </div>

        <!-- Social Icons Footer -->
        <div class="mt-10 flex justify-center space-x-6">
            @if(shop_setting('social_instagram'))
                <a href="{{ shop_setting('social_instagram') }}" target="_blank" rel="noopener noreferrer" class="text-gray-400 hover:text-[var(--theme-color)] transition-colors transform hover:scale-110">
                    <span class="sr-only">Instagram</span>
                    <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path fill-rule="evenodd" d="M12.315 2c2.43 0 2.784.013 3.808.06 1.064.049 1.791.218 2.427.465a4.902 4.902 0 011.772 1.153 4.902 4.902 0 011.153 1.772c.247.636.416 1.363.465 2.427.048 1.067.06 1.407.06 4.123v.08c0 2.643-.012 2.987-.06 4.043-.049 1.064-.218 1.791-.465 2.427a4.902 4.902 0 01-1.153 1.772 4.902 4.902 0 01-1.772 1.153c-.636.247-1.363.416-2.427.465-1.067.048-1.407.06-4.123.06h-.08c-2.643 0-2.987-.012-4.043-.06-1.064-.049-1.791-.218-2.427-.465a4.902 4.902 0 01-1.772-1.153 4.902 4.902 0 01-1.153-1.772c-.247-.636-.416-1.363-.465-2.427-.047-1.024-.06-1.379-.06-3.808v-.63c0-2.43.013-2.784.06-3.808.049-1.064.218-1.791.465-2.427a4.902 4.902 0 011.153-1.772A4.902 4.902 0 015.45 2.525c.636-.247 1.363-.416 2.427-.465C8.901 2.013 9.256 2 11.685 2h.63zm-.081 1.802h-.468c-2.456 0-2.784.011-3.807.058-.975.045-1.504.207-1.857.344-.467.182-.8.398-1.15.748-.35.35-.566.683-.748 1.15-.137.353-.3.882-.344 1.857-.047 1.023-.058 1.351-.058 3.807v.468c0 2.456.011 2.784.058 3.807.045.975.207 1.504.344 1.857.182.466.399.8.748 1.15.35.35.683.566 1.15.748.353.137.882.3 1.857.344 1.054.048 1.37.058 4.041.058h.08c2.597 0 2.917-.01 3.96-.058.976-.045 1.505-.207 1.858-.344.466-.182.8-.398 1.15-.748.35-.35.566-.683.748-1.15.137-.353.3-.882.344-1.857.048-1.055.058-1.37.058-4.041v-.08c0-2.597-.01-2.917-.058-3.96-.045-.976-.207-1.505-.344-1.858a3.097 3.097 0 00-.748-1.15 3.098 3.098 0 00-1.15-.748c-.353-.137-.882-.3-1.857-.344-1.023-.047-1.351-.058-3.807-.058zM12 6.865a5.135 5.135 0 110 10.27 5.135 5.135 0 010-10.27zm0 1.802a3.333 3.333 0 100 6.666 3.333 3.333 0 000-6.666zm5.338-3.205a1.2 1.2 0 110 2.4 1.2 1.2 0 010-2.4z" clip-rule="evenodd" />
                    </svg>
                </a>
            @endif
        </div>

    </div>

    <!-- Secure Link Modal (DSGVO Gate - Helles Design & Neuer Tab) -->
    @if($showSecureModal)
    <div class="relative z-50" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="fixed inset-0 bg-gray-900/40 backdrop-blur-sm transition-opacity" wire:click="$set('showSecureModal', false)"></div>

        <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
            <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                <div class="relative transform overflow-hidden rounded-2xl bg-white border border-gray-200 px-5 pb-5 pt-6 text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-sm sm:p-6">
                    <div>
                        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-amber-50 border border-amber-200">
                            <x-heroicon-o-shield-exclamation class="h-6 w-6 text-amber-600" />
                        </div>
                        <div class="mt-3 text-center sm:mt-4">
                            <h3 class="text-lg font-bold leading-6 text-gray-900" id="modal-title">Weiterleitung öffnen</h3>
                            <div class="mt-2">
                                <p class="text-sm text-gray-600">Der Link wird in einem neuen Tab geöffnet. Bei externen Anbietern gelten deren jeweilige Datenschutzbestimmungen.</p>
                                <p class="mt-3 text-xs text-gray-500 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 break-all font-mono">{{ $secureLinkUrl }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="mt-5 sm:mt-6 sm:grid sm:grid-flow-row-dense sm:grid-cols-2 sm:gap-3">
                        <a href="{{ $secureLinkUrl }}"
                           target="_blank"
                           rel="noopener noreferrer"
                           wire:click="proceedSecureLink"
                           class="inline-flex w-full items-center justify-center gap-1.5 rounded-xl bg-[var(--theme-color)] px-3 py-2.5 text-sm font-bold text-gray-950 shadow-sm hover:brightness-105 sm:col-start-2 transition-all">
                            <span>Im neuen Tab öffnen</span>
                            <x-heroicon-o-arrow-top-right-on-square class="w-4 h-4" />
                        </a>
                        <button type="button"
                                wire:click="$set('showSecureModal', false)"
                                class="mt-3 inline-flex w-full justify-center rounded-xl bg-gray-100 px-3 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-200 sm:col-start-1 sm:mt-0 transition-colors">
                            Abbrechen
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
