<div class="min-h-screen relative flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8 bg-[#0a0a0a] overflow-hidden" style="--theme-color: {{ $themeColor }}; --theme-color-50: {{ $themeColor }}80; --theme-color-20: {{ $themeColor }}33;">
    <!-- Background Effects -->
    <div class="absolute inset-0 z-0">
        <div class="absolute top-0 -left-4 w-72 h-72 bg-[var(--theme-color)] rounded-full mix-blend-multiply filter blur-[128px] opacity-20 animate-blob"></div>
        <div class="absolute top-0 -right-4 w-72 h-72 bg-[var(--theme-color)] rounded-full mix-blend-multiply filter blur-[128px] opacity-10 animate-blob animation-delay-2000"></div>
        <div class="absolute -bottom-8 left-20 w-72 h-72 bg-primary rounded-full mix-blend-multiply filter blur-[128px] opacity-20 animate-blob animation-delay-4000"></div>
    </div>

    <!-- Main Container -->
    <div class="max-w-md w-full space-y-8 z-10 relative">

        <!-- Profile Header -->
        <div class="text-center animate-fade-in-up">
            <div class="relative inline-block">
                @if($profileImage)
                    <img src="{{ asset($profileImage) }}" alt="Profile" class="mx-auto h-24 w-24 rounded-full object-cover border-2 border-[var(--theme-color-50)]" style="box-shadow: 0 0 15px var(--theme-color-50);">
                @else
                    <div class="mx-auto h-24 w-24 rounded-full bg-gradient-to-tr from-gray-900 to-gray-800 border-2 border-[var(--theme-color-50)] flex items-center justify-center" style="box-shadow: 0 0 15px var(--theme-color-50);">
                        <x-heroicon-o-user class="h-10 w-10 text-[var(--theme-color)]" />
                    </div>
                @endif
            </div>

            <h2 class="mt-6 text-center text-2xl font-extrabold text-white tracking-tight">
                {{ shop_setting('owner_proprietor', shop_setting('shop_name', 'Seelenfunke')) }}
            </h2>
            <p class="mt-2 text-center text-sm text-gray-400">
                Willkommen auf meiner digitalen Visitenkarte
            </p>
        </div>

        <!-- Links List -->
        <div class="mt-8 space-y-4">
            @foreach($links as $link)
                <button wire:click="handleLinkClick('{{ $link->id }}', '{{ $link->url }}', '{{ $link->type }}')" class="w-full group relative overflow-hidden rounded-xl bg-white/5 border border-white/10 p-4 transition-all duration-300 hover:bg-white/10 hover:border-[var(--theme-color-50)] hover:-translate-y-1 backdrop-blur-md" style="--tw-shadow: 0 0 20px var(--theme-color-20); --tw-shadow-colored: 0 0 20px var(--theme-color-20); box-shadow: var(--tw-ring-offset-shadow, 0 0 #0000), var(--tw-ring-shadow, 0 0 #0000), var(--tw-shadow);">

                    @if($link->type === 'highlight')
                        <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-[var(--theme-color)] via-primary to-[var(--theme-color)] animate-gradient-x"></div>
                    @endif

                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-4">
                            <div class="flex-shrink-0">
                                <div class="p-2 bg-[var(--theme-color-10)] rounded-lg group-hover:bg-[var(--theme-color-20)] transition-colors">
                                    @if($link->icon)
                                        <x-dynamic-component :component="'heroicon-o-' . $link->icon" class="h-6 w-6 text-[var(--theme-color)]" />
                                    @else
                                        <x-heroicon-o-link class="h-6 w-6 text-[var(--theme-color)]" />
                                    @endif
                                </div>
                            </div>
                            <div class="flex-1 text-left">
                                <p class="text-sm font-medium text-gray-200 group-hover:text-white transition-colors">
                                    {{ $link->title }}
                                </p>
                            </div>
                        </div>
                        <div class="flex-shrink-0">
                            <x-heroicon-m-chevron-right class="h-5 w-5 text-gray-500 group-hover:text-[var(--theme-color)] transition-colors transform group-hover:translate-x-1" />
                        </div>
                    </div>
                </button>
            @endforeach
        </div>

        <!-- Social Icons Footer -->
        <div class="mt-12 flex justify-center space-x-6">
            @if(shop_setting('social_instagram'))
            <a href="{{ shop_setting('social_instagram') }}" target="_blank" class="text-gray-400 hover:text-[var(--theme-color)] transition-colors transform hover:scale-110">
                <span class="sr-only">Instagram</span>
                <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path fill-rule="evenodd" d="M12.315 2c2.43 0 2.784.013 3.808.06 1.064.049 1.791.218 2.427.465a4.902 4.902 0 011.772 1.153 4.902 4.902 0 011.153 1.772c.247.636.416 1.363.465 2.427.048 1.067.06 1.407.06 4.123v.08c0 2.643-.012 2.987-.06 4.043-.049 1.064-.218 1.791-.465 2.427a4.902 4.902 0 01-1.153 1.772 4.902 4.902 0 01-1.772 1.153c-.636.247-1.363.416-2.427.465-1.067.048-1.407.06-4.123.06h-.08c-2.643 0-2.987-.012-4.043-.06-1.064-.049-1.791-.218-2.427-.465a4.902 4.902 0 01-1.772-1.153 4.902 4.902 0 01-1.153-1.772c-.247-.636-.416-1.363-.465-2.427-.047-1.024-.06-1.379-.06-3.808v-.63c0-2.43.013-2.784.06-3.808.049-1.064.218-1.791.465-2.427a4.902 4.902 0 011.153-1.772A4.902 4.902 0 015.45 2.525c.636-.247 1.363-.416 2.427-.465C8.901 2.013 9.256 2 11.685 2h.63zm-.081 1.802h-.468c-2.456 0-2.784.011-3.807.058-.975.045-1.504.207-1.857.344-.467.182-.8.398-1.15.748-.35.35-.566.683-.748 1.15-.137.353-.3.882-.344 1.857-.047 1.023-.058 1.351-.058 3.807v.468c0 2.456.011 2.784.058 3.807.045.975.207 1.504.344 1.857.182.466.399.8.748 1.15.35.35.683.566 1.15.748.353.137.882.3 1.857.344 1.054.048 1.37.058 4.041.058h.08c2.597 0 2.917-.01 3.96-.058.976-.045 1.505-.207 1.858-.344.466-.182.8-.398 1.15-.748.35-.35.566-.683.748-1.15.137-.353.3-.882.344-1.857.048-1.055.058-1.37.058-4.041v-.08c0-2.597-.01-2.917-.058-3.96-.045-.976-.207-1.505-.344-1.858a3.097 3.097 0 00-.748-1.15 3.098 3.098 0 00-1.15-.748c-.353-.137-.882-.3-1.857-.344-1.023-.047-1.351-.058-3.807-.058zM12 6.865a5.135 5.135 0 110 10.27 5.135 5.135 0 010-10.27zm0 1.802a3.333 3.333 0 100 6.666 3.333 3.333 0 000-6.666zm5.338-3.205a1.2 1.2 0 110 2.4 1.2 1.2 0 010-2.4z" clip-rule="evenodd" />
                </svg>
            </a>
            @endif
        </div>

    </div>

    <!-- Secure Link Modal (DSGVO Gate) -->
    @if($showSecureModal)
    <div class="relative z-50" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="fixed inset-0 bg-[#0a0a0a]/80 backdrop-blur-sm transition-opacity" wire:click="$set('showSecureModal', false)"></div>

        <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
            <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                <div class="relative transform overflow-hidden rounded-2xl bg-gray-900 border border-gray-800 px-4 pb-4 pt-5 text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-sm sm:p-6">
                    <div>
                        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-yellow-400/10">
                            <x-heroicon-o-shield-exclamation class="h-6 w-6 text-yellow-500" />
                        </div>
                        <div class="mt-3 text-center sm:mt-5">
                            <h3 class="text-lg font-semibold leading-6 text-white" id="modal-title">Externe Weiterleitung</h3>
                            <div class="mt-2">
                                <p class="text-sm text-gray-400">Du verlässt nun unsere Seite und wirst zu einem externen Anbieter weitergeleitet. Wir übernehmen keine Haftung für externe Inhalte und deren Datenschutzbestimmungen.</p>
                                <p class="mt-3 text-xs text-gray-500 break-all">{{ $secureLinkUrl }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="mt-5 sm:mt-6 sm:grid sm:grid-flow-row-dense sm:grid-cols-2 sm:gap-3">
                        <button type="button" wire:click="proceedSecureLink" class="inline-flex w-full justify-center rounded-lg bg-[var(--theme-color)] px-3 py-2 text-sm font-semibold text-black shadow-sm hover:opacity-90 sm:col-start-2 transition-colors">Trotzdem öffnen</button>
                        <button type="button" wire:click="$set('showSecureModal', false)" class="mt-3 inline-flex w-full justify-center rounded-lg bg-white/10 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-white/20 sm:col-start-1 sm:mt-0 transition-colors">Abbrechen</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
