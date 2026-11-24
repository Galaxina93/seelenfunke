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

        /* Funki Support Trigger: deutlich größer als die übrigen Dock-Icons, ruckelfreies GPU-Hover */
        .sf-floating-corner-btn.sf-floating-funki-btn {
            width: 3.875rem;
            height: 3.875rem;
            padding: 0;
            background: rgba(255, 255, 255, 0.98);
            border: 1.5px solid rgba(197, 160, 89, 0.65);
            box-shadow: 0 8px 22px rgba(0, 0, 0, 0.14), 0 0 0 1px rgba(255, 255, 255, 0.75);
            overflow: visible;
            transform: translate3d(0, 0, 0) scale(1);
            will-change: transform;
            backface-visibility: hidden;
        }
        @media (min-width: 640px) {
            .sf-floating-corner-btn.sf-floating-funki-btn {
                width: 3.875rem;
                height: 3.875rem;
                border-color: rgba(197, 160, 89, 0.6);
                box-shadow: 0 8px 22px rgba(0, 0, 0, 0.11);
            }
            .sf-support-chat-panel {
                right: 6rem !important;
            }
        }
        .sf-floating-corner-btn.sf-floating-funki-btn::before {
            background: radial-gradient(circle at 50% 45%, rgba(197, 160, 89, 0.16) 0%, transparent 72%);
        }
        .sf-floating-corner-btn.sf-floating-funki-btn:hover,
        .sf-floating-corner-btn.sf-floating-funki-btn:active,
        .sf-floating-corner-btn.sf-floating-funki-btn.sf-funki-open {
            transform: translate3d(0, -2px, 0) scale(1.06);
            border-color: #C5A059;
            background: #ffffff;
            box-shadow: 0 10px 24px rgba(197, 160, 89, 0.28),
                        0 4px 12px rgba(0, 0, 0, 0.12);
        }
        .sf-floating-corner-btn.sf-floating-funki-btn.sf-funki-open {
            box-shadow: 0 0 0 3px rgba(197, 160, 89, 0.28),
                        0 10px 24px rgba(197, 160, 89, 0.24),
                        0 4px 12px rgba(0, 0, 0, 0.12);
        }
        .sf-floating-corner-btn.sf-floating-funki-btn svg {
            width: 3.25rem;
            height: 3.25rem;
            overflow: visible;
            transform: none !important;
            transition: none !important;
        }
        .sf-floating-corner-btn.sf-floating-funki-btn:hover svg {
            transform: none !important;
        }

        /* Funki Gesichts-Animationen (konstante Dauer ohne Hover-Phasensprung) */
        .sf-funki-head {
            transform-origin: 32px 37px;
            animation: sfFunkiBob 4.8s ease-in-out infinite;
        }
        .sf-funki-eye-l {
            transform-origin: 22.5px 33.5px;
            animation: sfFunkiBlink 5.4s ease-in-out infinite;
        }
        .sf-funki-eye-r {
            transform-origin: 41.5px 33.5px;
            animation: sfFunkiBlink 5.4s ease-in-out infinite;
        }
        .sf-funki-pupils {
            animation: sfFunkiLook 7.6s ease-in-out infinite;
        }
        .sf-funki-gear-lg {
            transform-origin: 47.2px 46.8px;
            animation: sfFunkiGearCw 11s linear infinite;
        }
        .sf-funki-gear-sm {
            transform-origin: 41.6px 49.4px;
            animation: sfFunkiGearCcw 8s linear infinite;
        }
        .sf-funki-cheek {
            opacity: 0;
            transition: opacity 0.28s ease;
        }
        .sf-floating-funki-btn:hover .sf-funki-cheek {
            opacity: 0.45;
        }

        @keyframes sfFunkiBob {
            0%, 100% { transform: translateY(0px) rotate(-1.8deg); }
            35% { transform: translateY(-1.2px) rotate(1.6deg); }
            65% { transform: translateY(-0.5px) rotate(2.2deg); }
            85% { transform: translateY(-1.1px) rotate(-0.8deg); }
        }
        @keyframes sfFunkiBlink {
            0%, 44%, 48%, 88%, 92%, 95%, 100% { transform: scaleY(1); }
            46% { transform: scaleY(0.08); }
            90% { transform: scaleY(0.08); }
            93.5% { transform: scaleY(0.08); }
        }
        @keyframes sfFunkiLook {
            0%, 18%, 100% { transform: translate(0px, 0px); }
            26%, 38% { transform: translate(-1.15px, -0.35px); }
            46%, 56% { transform: translate(0px, 0px); }
            64%, 76% { transform: translate(1.15px, -0.45px); }
            84%, 92% { transform: translate(0px, 0.35px); }
        }
        @keyframes sfFunkiGearCw {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        @keyframes sfFunkiGearCcw {
            from { transform: rotate(360deg); }
            to { transform: rotate(0deg); }
        }
        @media (prefers-reduced-motion: reduce) {
            .sf-funki-head,
            .sf-funki-eye-l,
            .sf-funki-eye-r,
            .sf-funki-pupils,
            .sf-funki-gear-lg,
            .sf-funki-gear-sm {
                animation: none !important;
            }
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

    {{-- 4. LIVE SUPPORT CHAT (Animiertes Funki-Gesicht, ein Tuck größer als die übrigen Dock-Icons) --}}
    <button @click="chatOpen = !chatOpen; if(chatOpen) { vouchersOpen = false; setTimeout(() => $dispatch('chat-opened'), 50); }"
            class="sf-floating-corner-btn sf-floating-funki-btn"
            :class="chatOpen ? 'sf-funki-open' : ''"
            title="Funki Support-Chat"
            aria-label="KI-Support-Chat">
        <svg viewBox="1 2 62 58" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <defs>
                <linearGradient id="sf-funki-gold" x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" stop-color="#F6E0A2"/>
                    <stop offset="38%" stop-color="#C5A059"/>
                    <stop offset="76%" stop-color="#9A7428"/>
                    <stop offset="100%" stop-color="#E4C474"/>
                </linearGradient>
                <linearGradient id="sf-funki-gold-collar" x1="0%" y1="0%" x2="100%" y2="0%">
                    <stop offset="0%" stop-color="#9B752A"/>
                    <stop offset="35%" stop-color="#F3DA97"/>
                    <stop offset="70%" stop-color="#C5A059"/>
                    <stop offset="100%" stop-color="#8A6414"/>
                </linearGradient>
                <linearGradient id="sf-funki-screen" x1="15%" y1="0%" x2="85%" y2="100%">
                    <stop offset="0%" stop-color="#2B2E34"/>
                    <stop offset="48%" stop-color="#181A1F"/>
                    <stop offset="100%" stop-color="#0E1014"/>
                </linearGradient>
                <linearGradient id="sf-funki-cap" x1="0%" y1="0%" x2="100%" y2="0%">
                    <stop offset="0%" stop-color="#16181C"/>
                    <stop offset="35%" stop-color="#3C4048"/>
                    <stop offset="75%" stop-color="#1D2026"/>
                    <stop offset="100%" stop-color="#0E1013"/>
                </linearGradient>
                <radialGradient id="sf-funki-iris" cx="46%" cy="54%" r="55%">
                    <stop offset="0%" stop-color="#FFF0B5"/>
                    <stop offset="38%" stop-color="#E5A22E"/>
                    <stop offset="76%" stop-color="#9E5B0E"/>
                    <stop offset="100%" stop-color="#4A2706"/>
                </radialGradient>
                <linearGradient id="sf-funki-sheen" x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" stop-color="#FFFFFF" stop-opacity="0.18"/>
                    <stop offset="65%" stop-color="#FFFFFF" stop-opacity="0.04"/>
                    <stop offset="100%" stop-color="#FFFFFF" stop-opacity="0"/>
                </linearGradient>
                <clipPath id="sf-funki-screen-clip">
                    <rect x="11.5" y="18.2" width="41" height="33.6" rx="7.2"/>
                </clipPath>
            </defs>

            <g class="sf-funki-head">
                {{-- Seitliche goldene Ohren / Kappen --}}
                <rect x="4.5" y="29" width="5.5" height="12.5" rx="2.75" fill="url(#sf-funki-gold)" stroke="#6E5017" stroke-width="1"/>
                <rect x="7.4" y="30.6" width="1.6" height="9.3" rx="0.8" fill="#2A2112" opacity="0.55"/>
                <rect x="54" y="29" width="5.5" height="12.5" rx="2.75" fill="url(#sf-funki-gold)" stroke="#6E5017" stroke-width="1"/>
                <rect x="55" y="30.6" width="1.6" height="9.3" rx="0.8" fill="#2A2112" opacity="0.55"/>

                {{-- Oberer Funki-Zylinder (Hütchen) --}}
                <rect x="24.5" y="6.8" width="15" height="6.8" rx="3" fill="url(#sf-funki-cap)" stroke="#111317" stroke-width="0.9"/>
                <rect x="22.8" y="11.8" width="18.4" height="3.9" rx="1.8" fill="url(#sf-funki-gold-collar)" stroke="#6E5017" stroke-width="0.9"/>

                {{-- Äußerer goldener Rahmen --}}
                <rect x="8.5" y="15.2" width="47" height="39.6" rx="10.2" fill="url(#sf-funki-gold)" stroke="#6E5017" stroke-width="1.15"/>
                {{-- Innerer schwarzer Display-Kopf --}}
                <rect x="11.5" y="18.2" width="41" height="33.6" rx="7.2" fill="url(#sf-funki-screen)" stroke="#4A3611" stroke-width="0.8"/>

                <g clip-path="url(#sf-funki-screen-clip)">
                    {{-- Diagonaler Glas-Glanz --}}
                    <polygon points="10,16 33,16 21,54 10,54" fill="url(#sf-funki-sheen)"/>
                    {{-- Signature goldene Stirn-Linie --}}
                    <line x1="13.5" y1="20.7" x2="50.5" y2="20.7" stroke="#C5A059" stroke-width="1.25" stroke-linecap="round" opacity="0.9"/>

                    {{-- Signature kleine goldene Zahnräder rechts unten --}}
                    <g class="sf-funki-gear-lg" opacity="0.88">
                        <g fill="#C5A059" stroke="#6E5017" stroke-width="0.45">
                            <rect x="46.4" y="42.6" width="1.6" height="8.4" rx="0.55"/>
                            <rect x="46.4" y="42.6" width="1.6" height="8.4" rx="0.55" transform="rotate(45 47.2 46.8)"/>
                            <rect x="46.4" y="42.6" width="1.6" height="8.4" rx="0.55" transform="rotate(90 47.2 46.8)"/>
                            <rect x="46.4" y="42.6" width="1.6" height="8.4" rx="0.55" transform="rotate(135 47.2 46.8)"/>
                            <circle cx="47.2" cy="46.8" r="3"/>
                        </g>
                        <circle cx="47.2" cy="46.8" r="1.25" fill="#181A1F" stroke="#8A6414" stroke-width="0.45"/>
                    </g>
                    <g class="sf-funki-gear-sm" opacity="0.85">
                        <g fill="#D8B66D" stroke="#6E5017" stroke-width="0.38">
                            <rect x="41.0" y="46.7" width="1.2" height="5.4" rx="0.45"/>
                            <rect x="41.0" y="46.7" width="1.2" height="5.4" rx="0.45" transform="rotate(60 41.6 49.4)"/>
                            <rect x="41.0" y="46.7" width="1.2" height="5.4" rx="0.45" transform="rotate(120 41.6 49.4)"/>
                            <circle cx="41.6" cy="49.4" r="1.95"/>
                        </g>
                        <circle cx="41.6" cy="49.4" r="0.85" fill="#181A1F"/>
                    </g>
                </g>

                {{-- Linkes Auge (Goldfassung + blinzelndes warmes Auge) --}}
                <circle cx="22.5" cy="33.5" r="7.2" fill="#0D0F13" stroke="#C5A059" stroke-width="1.25"/>
                <g class="sf-funki-eye-l">
                    <circle cx="22.5" cy="33.5" r="6.1" fill="#FFFDF6"/>
                    <g class="sf-funki-pupils">
                        <circle cx="22.7" cy="33.6" r="4.65" fill="url(#sf-funki-iris)"/>
                        <circle cx="22.7" cy="33.6" r="2.55" fill="#1A0E05"/>
                        <circle cx="20.9" cy="31.7" r="1.35" fill="#FFFFFF"/>
                        <circle cx="24.5" cy="35.3" r="0.6" fill="#FFF6D1"/>
                    </g>
                </g>

                {{-- Rechtes Auge (Goldfassung + blinzelndes warmes Auge) --}}
                <circle cx="41.5" cy="33.5" r="7.2" fill="#0D0F13" stroke="#C5A059" stroke-width="1.25"/>
                <g class="sf-funki-eye-r">
                    <circle cx="41.5" cy="33.5" r="6.1" fill="#FFFDF6"/>
                    <g class="sf-funki-pupils">
                        <circle cx="41.3" cy="33.6" r="4.65" fill="url(#sf-funki-iris)"/>
                        <circle cx="41.3" cy="33.6" r="2.55" fill="#1A0E05"/>
                        <circle cx="39.5" cy="31.7" r="1.35" fill="#FFFFFF"/>
                        <circle cx="43.1" cy="35.3" r="0.6" fill="#FFF6D1"/>
                    </g>
                </g>

                {{-- Sanfter Hover-Wangen-Schimmer --}}
                <ellipse class="sf-funki-cheek" cx="17" cy="41.2" rx="2.3" ry="1.2" fill="#E59866"/>
                <ellipse class="sf-funki-cheek" cx="47" cy="41.2" rx="2.3" ry="1.2" fill="#E59866"/>

                {{-- Fröhliches Funki-Lächeln --}}
                <g class="sf-funki-mouth">
                    <path d="M25.8 42.3 Q32 44.3 38.2 42.3 Q37.4 48.9 32 49.1 Q26.6 48.9 25.8 42.3 Z" fill="#2A110E" stroke="#0C0D10" stroke-width="0.6"/>
                    <path d="M27.3 43.2 Q32 44.5 36.7 43.2 Q36.3 44.4 32 44.8 Q27.7 44.4 27.3 43.2 Z" fill="#FFFDF8" opacity="0.92"/>
                    <path d="M28.8 47.4 Q32 45.7 35.2 47.4 Q32 49.1 28.8 47.4 Z" fill="#E2584D"/>
                </g>
            </g>
        </svg>
    </button>

    {{-- GUTSCHEIN MODAL (Wird über das Geschenk-Icon neben den Gutschein-Inputfeldernzentriert geöffnet) --}}
    <template x-teleport="body">
        <div x-show="vouchersOpen"
             x-cloak
             @click.self="vouchersOpen = false"
             x-transition:enter="transition ease-out duration-250"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 flex items-center justify-center p-4 bg-black/35 backdrop-blur-[2px] pointer-events-auto"
             style="z-index: 99998;"
        >
            <div x-show="vouchersOpen"
                 @click.outside="if (!$event.target.closest('[data-voucher-trigger]')) vouchersOpen = false"
                 x-transition:enter="transition ease-[cubic-bezier(0.23,1,0.32,1)] duration-300"
                 x-transition:enter-start="translate-y-4 opacity-0 scale-95"
                 x-transition:enter-end="translate-y-0 opacity-100 scale-100"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="translate-y-0 opacity-100 scale-100"
                 x-transition:leave-end="translate-y-4 opacity-0 scale-95"
                 class="relative w-full max-w-[380px] max-h-[82dvh] bg-white/95 backdrop-blur-2xl shadow-[0_25px_60px_rgba(0,0,0,0.22)] rounded-[2rem] border border-stone-200/90 flex flex-col overflow-hidden"
            >
                @livewire('shop.marketing.marketing-voucher-slider')
            </div>
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
             class="sf-support-chat-panel bg-white shadow-[0_30px_80px_rgba(0,0,0,0.4)] rounded-3xl border border-gray-100 overflow-hidden flex flex-col pointer-events-auto transition-all duration-500 ease-[cubic-bezier(0.23,1,0.32,1)]"
             :style="chatMaximized ? 'z-index: 100001; max-height: calc(100dvh - 5.5rem);' : 'z-index: 99999; max-height: calc(100dvh - 5.5rem);'"
        >
            @livewire('frontend.support.ai-frontend-support-chat')
            <div class="hidden sm:w-[450px] sm:h-[calc(100vh-5rem)] sm:w-[380px] sm:h-[75vh] sm:max-h-[600px] right-2.5 left-2.5 bottom-3 top-[4.75rem] sm:left-auto sm:right-20 sm:bottom-10 sm:top-10 sm:bottom-6 sm:top-auto"></div>
        </div>
    </template>
</div>
