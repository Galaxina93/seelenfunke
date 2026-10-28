<div id="frontend-action-dock"
     x-data="{
        atTop: true,
        atBottom: false,
        vouchersOpen: false,
        chatOpen: false,
        chatMaximized: false
    }"
     x-init="
        const updateScrollState = () => {
            const scrollTop = window.pageYOffset || document.documentElement.scrollTop || document.body.scrollTop || 0;
            const docHeight = Math.max(
                document.body ? document.body.scrollHeight : 0,
                document.documentElement ? document.documentElement.scrollHeight : 0,
                document.body ? document.body.offsetHeight : 0,
                document.documentElement ? document.documentElement.offsetHeight : 0
            );
            const winHeight = window.innerHeight || document.documentElement.clientHeight || 0;
            atTop = scrollTop < 220;
            atBottom = docHeight <= (winHeight + 80) || (winHeight + scrollTop) >= (docHeight - 100);
        };
        updateScrollState();
        setTimeout(updateScrollState, 350);
        window.addEventListener('scroll', updateScrollState, { passive: true });
        window.addEventListener('resize', updateScrollState, { passive: true });
        window.addEventListener('load', updateScrollState, { passive: true });
    "
     @toggle-chat-size.window="chatMaximized = !chatMaximized"
     @close-support-chat.window="chatOpen = false; setTimeout(() => chatMaximized = false, 400)"
     @toggle-vouchers.window="vouchersOpen = !vouchersOpen; if(vouchersOpen) chatOpen = false"
     @close-vouchers.window="vouchersOpen = false"
     @keydown.escape.window="chatOpen = false; vouchersOpen = false"
     :class="(chatOpen || vouchersOpen) ? 'sf-dock-modal-open' : ''"
     class="fixed right-3 bottom-4 sm:right-5 sm:bottom-5 z-[9990] flex flex-col items-center gap-2 pointer-events-none"
>
    <style>
        #frontend-action-dock {
            position: fixed;
            right: max(0.75rem, env(safe-area-inset-right, 0px));
            bottom: max(1rem, env(safe-area-inset-bottom, 0px));
            z-index: 9990;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
            pointer-events: none;
            transition: opacity 0.25s ease, transform 0.25s ease;
        }
        @media (min-width: 640px) {
            #frontend-action-dock {
                right: 1.25rem;
                bottom: 1.25rem;
            }
        }
        @media (max-width: 639px) {
            #frontend-action-dock.sf-dock-modal-open {
                opacity: 0;
                pointer-events: none !important;
                transform: translateY(8px);
            }
            #frontend-action-dock.sf-dock-modal-open .sf-floating-corner-btn {
                pointer-events: none !important;
            }
        }
        .sf-floating-corner-btn {
            position: relative;
            width: 2.625rem;
            height: 2.625rem;
            border-radius: 9999px;
            background: rgba(255, 255, 255, 0.96);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(197, 160, 89, 0.45);
            color: #292524;
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.14), 0 0 0 1px rgba(255, 255, 255, 0.6);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            pointer-events: auto;
            cursor: pointer;
            -webkit-tap-highlight-color: transparent;
            touch-action: manipulation;
            transition: transform 0.28s cubic-bezier(0.22, 1, 0.36, 1),
                        border-color 0.28s ease,
                        box-shadow 0.28s ease,
                        color 0.28s ease,
                        background 0.28s ease;
            overflow: hidden;
        }
        @media (min-width: 640px) {
            .sf-floating-corner-btn {
                width: 2.5rem;
                height: 2.5rem;
                border-color: rgba(214, 211, 209, 0.85);
                color: #44403c;
                box-shadow: 0 6px 18px rgba(0, 0, 0, 0.08);
            }
        }
        .sf-floating-corner-btn::before {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: 9999px;
            background: radial-gradient(circle at 50% 35%, rgba(253, 230, 138, 0.55) 0%, rgba(197, 160, 89, 0.22) 55%, transparent 100%);
            opacity: 0;
            transition: opacity 0.28s ease;
            pointer-events: none;
        }
        .sf-floating-corner-btn:hover,
        .sf-floating-corner-btn:active {
            transform: translateY(-2px) scale(1.07);
            border-color: #C5A059;
            color: #9a6f1a;
            background: #ffffff;
            box-shadow: 0 0 18px rgba(251, 191, 36, 0.65),
                        0 0 32px rgba(197, 160, 89, 0.35),
                        0 8px 20px rgba(0, 0, 0, 0.1);
        }
        .sf-floating-corner-btn:hover::before,
        .sf-floating-corner-btn:active::before {
            opacity: 1;
        }
        .sf-floating-corner-btn svg {
            position: relative;
            z-index: 2;
            transition: transform 0.28s ease;
        }
        .sf-floating-corner-btn:hover svg {
            transform: scale(1.1);
        }
    </style>

    {{-- 1. NACH OBEN SCROLLEN (Eigenständiges Icon) --}}
    <button x-show="!atTop"
            x-cloak
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-75 translate-y-2"
            x-transition:enter-end="opacity-100 scale-100 translate-y-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 scale-100 translate-y-0"
            x-transition:leave-end="opacity-0 scale-75 translate-y-2"
            @click="window.scrollTo({top: 0, behavior: 'smooth'})"
            class="sf-floating-corner-btn"
            title="Nach oben scrollen"
            aria-label="Nach oben scrollen">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/>
        </svg>
    </button>

    {{-- 2. NACH UNTEN SCROLLEN (Eigenständiges Icon) --}}
    <button x-show="!atBottom"
            x-cloak
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-75 translate-y-2"
            x-transition:enter-end="opacity-100 scale-100 translate-y-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 scale-100 translate-y-0"
            x-transition:leave-end="opacity-0 scale-75 translate-y-2"
            @click="window.scrollTo({top: Math.max(document.body.scrollHeight, document.documentElement.scrollHeight), behavior: 'smooth'})"
            class="sf-floating-corner-btn"
            title="Nach unten scrollen"
            aria-label="Nach unten scrollen">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
        </svg>
    </button>

    {{-- 3. COOKIE-EINSTELLUNGEN (Eigenständiges Icon) --}}
    <button @click="if (window.CookieConsent && typeof window.CookieConsent.showPreferences === 'function') { window.CookieConsent.showPreferences(); }"
            class="sf-floating-corner-btn"
            title="Cookie-Einstellungen"
            aria-label="Cookie-Einstellungen">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path d="M12 21a9 9 0 100-18 9 9 0 000 18z"/>
            <path d="M9 8h.01M12 12h.01M15 10h.01M11 16h.01M14 15h.01" stroke-linecap="round" stroke-width="3"/>
            <path d="M18.5 12c-1 0-2-1-2-2s1-2 2-2"/>
        </svg>
    </button>

    {{-- 4. LIVE SUPPORT CHAT (Eigenständiges Icon) --}}
    <button @click="chatOpen = !chatOpen; if(chatOpen) { vouchersOpen = false; setTimeout(() => $dispatch('chat-opened'), 50); }"
            class="sf-floating-corner-btn"
            :class="chatOpen ? 'border-primary text-[#9a6f1a] ring-2 ring-primary/30' : ''"
            title="Live Support"
            aria-label="Live Support">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 20.25c4.97 0 9-3.694 9-8.25s-4.03-8.25-9-8.25S3 7.444 3 12c0 2.104.859 4.023 2.273 5.38-.49 1.636-1.61 2.898-1.674 2.97-.1.116-.08.286.035.385.116.1.286.08.386-.035.056-.065 1.558-1.849 2.503-3.08A9.096 9.096 0 0012 20.25z" />
        </svg>
    </button>

    {{-- GUTSCHEIN PANEL (Wird über das Geschenk-Icon neben den Gutschein-Inputfeldern geöffnet) --}}
    <template x-teleport="body">
        <div x-show="vouchersOpen"
             x-cloak
             @click.outside="if (!$event.target.closest('[data-voucher-trigger]')) vouchersOpen = false"
             x-transition:enter="transition ease-[cubic-bezier(0.23,1,0.32,1)] duration-300"
             x-transition:enter-start="translate-y-4 opacity-0 scale-95"
             x-transition:enter-end="translate-y-0 opacity-100 scale-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="translate-y-0 opacity-100 scale-100"
             x-transition:leave-end="translate-y-4 opacity-0 scale-95"
             class="fixed left-3 right-3 sm:left-auto sm:right-6 top-1/2 -translate-y-1/2 bg-white/95 backdrop-blur-2xl sm:w-[340px] max-h-[82dvh] shadow-[0_25px_60px_rgba(0,0,0,0.18)] rounded-[2rem] border border-stone-200/90 flex flex-col pointer-events-auto overflow-hidden"
             style="z-index: 99998;"
        >
            @livewire('shop.marketing.marketing-voucher-slider')
        </div>
    </template>

    {{-- SUPPORT CHAT PANEL --}}
    <template x-teleport="body">
        <div x-show="chatOpen"
             x-cloak
             x-transition:enter="transition ease-[cubic-bezier(0.23,1,0.32,1)] duration-500"
             x-transition:enter-start="translate-x-10 opacity-0"
             x-transition:enter-end="translate-x-0 opacity-100"
             x-transition:leave="transition ease-in duration-300"
             x-transition:leave-start="translate-x-0 opacity-100"
             x-transition:leave-end="translate-x-10 opacity-0"
             :class="chatMaximized ? 'fixed right-2.5 left-2.5 bottom-3 top-[4.75rem] sm:left-auto sm:right-20 sm:bottom-10 sm:top-10 sm:w-[450px] sm:h-[calc(100vh-5rem)]' : 'fixed right-2.5 left-2.5 bottom-3 top-[4.75rem] sm:left-auto sm:right-20 sm:bottom-6 sm:top-auto sm:w-[380px] sm:h-[75vh] sm:max-h-[600px]'"
             class="bg-white shadow-[0_30px_80px_rgba(0,0,0,0.4)] rounded-3xl border border-gray-100 overflow-hidden flex flex-col pointer-events-auto transition-all duration-500 ease-[cubic-bezier(0.23,1,0.32,1)]"
             :style="chatMaximized ? 'z-index: 100001; max-height: calc(100dvh - 5.5rem);' : 'z-index: 99999; max-height: calc(100dvh - 5.5rem);'"
        >
            @livewire('frontend.support.ai-frontend-support-chat')
            <div class="hidden sm:w-[450px] sm:h-[calc(100vh-5rem)] sm:w-[380px] sm:h-[75vh] sm:max-h-[600px] right-2.5 left-2.5 bottom-3 top-[4.75rem] sm:left-auto sm:right-20 sm:bottom-10 sm:top-10 sm:bottom-6 sm:top-auto"></div>
        </div>
    </template>
</div>
