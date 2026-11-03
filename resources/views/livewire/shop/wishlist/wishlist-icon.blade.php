<div class="relative group z-50">
    <a href="{{ route('wishlist') }}"
       data-sf-wishlist-header-icon
       class="text-white hover:text-primary transition-colors duration-300 p-1 block relative"
       aria-label="Wunschliste"
       title="Meine Wunschliste">
        {{-- Herz-Icon --}}
        <svg xmlns="http://www.w3.org/2000/svg"
             class="h-7 w-7 transform group-hover:scale-110 transition-all duration-300 {{ $count > 0 ? 'text-primary fill-primary/25 drop-shadow-[0_0_8px_rgba(197,160,89,0.5)]' : '' }}"
             fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z" />
        </svg>

        {{-- Badge --}}
        @if($count > 0)
            <span class="absolute -top-1 -right-1 flex h-5 w-5">
                <span class="relative inline-flex rounded-full h-5 w-5 bg-primary text-gray-900 text-[10px] font-black justify-center items-center shadow-sm">
                    {{ $count }}
                </span>
            </span>
        @endif
    </a>

    {{-- Mini-Wunschliste Vorschau --}}
    @if($count > 0)
        <div class="absolute right-0 top-full mt-2 w-72 bg-white rounded-xl shadow-2xl border border-gray-100 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 transform group-hover:translate-y-0 translate-y-2 z-50 overflow-hidden">
            <div class="bg-gray-50 px-4 py-2 border-b border-gray-100 flex items-center justify-between">
                <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Wunschliste ({{ $count }})</span>
                <span class="text-[10px] font-bold text-primary">♥ Merkzettel</span>
            </div>

            <div class="p-3 space-y-2.5 max-h-64 overflow-y-auto">
                @foreach($previewItems as $item)
                    <div class="flex items-center gap-2.5 group/item">
                        <a href="{{ route('product.show', $item['slug']) }}" class="w-11 h-11 rounded-lg bg-gray-50 border border-gray-100 overflow-hidden shrink-0">
                            @if($item['preview_url'])
                                <img src="{{ $item['preview_url'] }}" alt="{{ $item['name'] }}" class="w-full h-full object-cover">
                            @endif
                        </a>
                        <div class="min-w-0 flex-1">
                            <a href="{{ route('product.show', $item['slug']) }}" class="block text-xs font-bold text-gray-900 hover:text-primary truncate transition-colors">
                                {{ $item['name'] }}
                            </a>
                            <span class="text-[11px] font-semibold text-gray-500 whitespace-nowrap">{{ $item['formatted_price'] }}</span>
                        </div>
                        <button type="button" wire:click.prevent="removeItem('{{ $item['product_id'] }}')" class="text-gray-300 hover:text-red-500 p-1 transition-colors cursor-pointer" title="Entfernen">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                @endforeach
            </div>

            <a href="{{ route('wishlist') }}" class="block w-full bg-gray-900 text-white text-center py-3 text-xs font-bold uppercase tracking-wider hover:bg-black transition">
                Zur Wunschliste
            </a>
        </div>
    @endif

    @once
        <script>
            window.sfPlayWishlistSound = function(isAdding) {
                try {
                    const AudioCtx = window.AudioContext || window.webkitAudioContext;
                    if (!AudioCtx) return;
                    const ctx = window._sfWishlistAudioCtx || (window._sfWishlistAudioCtx = new AudioCtx());
                    if (ctx.state === 'suspended') {
                        ctx.resume().catch(() => {});
                    }
                    const now = ctx.currentTime;
                    if (isAdding) {
                        // Süßer, kristallklarer Harfen-/Magie-Klang (C6 -> E6 -> G6 -> C7)
                        const notes = [523.25, 659.25, 783.99, 1046.50];
                        notes.forEach((freq, idx) => {
                            const osc = ctx.createOscillator();
                            const gain = ctx.createGain();
                            osc.type = idx === 3 ? 'triangle' : 'sine';
                            const start = now + idx * 0.055;
                            const dur = idx === 3 ? 0.42 : 0.24;
                            osc.frequency.setValueAtTime(freq, start);
                            gain.gain.setValueAtTime(0.0001, start);
                            gain.gain.exponentialRampToValueAtTime(idx === 3 ? 0.11 : 0.08, start + 0.018);
                            gain.gain.exponentialRampToValueAtTime(0.0001, start + dur);
                            osc.connect(gain);
                            gain.connect(ctx.destination);
                            osc.start(start);
                            osc.stop(start + dur + 0.02);
                        });
                    } else {
                        // Sanfter 2-Ton-Pluck beim Entfernen
                        const notes = [587.33, 440.0];
                        notes.forEach((freq, idx) => {
                            const osc = ctx.createOscillator();
                            const gain = ctx.createGain();
                            osc.type = 'sine';
                            const start = now + idx * 0.06;
                            osc.frequency.setValueAtTime(freq, start);
                            gain.gain.setValueAtTime(0.0001, start);
                            gain.gain.exponentialRampToValueAtTime(0.05, start + 0.015);
                            gain.gain.exponentialRampToValueAtTime(0.0001, start + 0.16);
                            osc.connect(gain);
                            gain.connect(ctx.destination);
                            osc.start(start);
                            osc.stop(start + 0.18);
                        });
                    }
                } catch (e) {}
            };

            window.sfTriggerWishlistHeartEffect = function(event, isAdding = true) {
                window.sfPlayWishlistSound(isAdding);
                if (!isAdding) return;

                let originX = window.innerWidth / 2;
                let originY = window.innerHeight / 2;
                if (event && event.currentTarget) {
                    const rect = event.currentTarget.getBoundingClientRect();
                    originX = rect.left + rect.width / 2;
                    originY = rect.top + rect.height / 2;
                } else if (event && typeof event.clientX === 'number') {
                    originX = event.clientX;
                    originY = event.clientY;
                }

                // Ziel: Sichtbares Wunschlisten-Herz im Header finden
                let targetX = window.innerWidth - 90;
                let targetY = 36;
                const headerIcons = document.querySelectorAll('[data-sf-wishlist-header-icon]');
                headerIcons.forEach((el) => {
                    const r = el.getBoundingClientRect();
                    if (r.width > 0 && r.height > 0) {
                        targetX = r.left + r.width / 2;
                        targetY = r.top + r.height / 2;
                    }
                });

                const canvas = document.createElement('canvas');
                canvas.style.cssText = 'position:fixed;inset:0;width:100vw;height:100vh;pointer-events:none;z-index:2147483646;';
                document.body.appendChild(canvas);

                const dpr = Math.min(window.devicePixelRatio || 1, 2);
                const w = window.innerWidth;
                const h = window.innerHeight;
                canvas.width = w * dpr;
                canvas.height = h * dpr;
                const ctx = canvas.getContext('2d');
                ctx.scale(dpr, dpr);

                const colors = ['#f43f5e', '#fb7185', '#fbbf24', '#f59e0b', '#fde047', '#ffffff'];
                const particles = Array.from({ length: 34 }, (_, i) => {
                    const angle = (Math.PI * 2 * i) / 34 + (Math.random() - 0.5) * 0.25;
                    const speed = 2.2 + Math.random() * 6.5;
                    return {
                        x: originX,
                        y: originY,
                        vx: Math.cos(angle) * speed,
                        vy: Math.sin(angle) * speed - 1.2,
                        size: 3.5 + Math.random() * 5.5,
                        color: colors[i % colors.length],
                        isHeart: i % 3 === 0,
                        alpha: 1,
                        rot: (Math.random() - 0.5) * 0.8,
                        vRot: (Math.random() - 0.5) * 0.08
                    };
                });

                const drawHeart = (cx, cy, size, color, alpha, rot = 0) => {
                    ctx.save();
                    ctx.translate(cx, cy);
                    ctx.rotate(rot);
                    ctx.globalAlpha = Math.max(0, Math.min(1, alpha));
                    ctx.fillStyle = color;
                    ctx.shadowColor = color;
                    ctx.shadowBlur = 10;
                    ctx.beginPath();
                    const s = size * 0.12;
                    ctx.moveTo(0, s * 3);
                    ctx.bezierCurveTo(0, 0, -s * 5, 0, -s * 5, s * 3);
                    ctx.bezierCurveTo(-s * 5, s * 6, 0, s * 8.5, 0, s * 11);
                    ctx.bezierCurveTo(0, s * 8.5, s * 5, s * 6, s * 5, s * 3);
                    ctx.bezierCurveTo(s * 5, 0, 0, 0, 0, s * 3);
                    ctx.closePath();
                    ctx.fill();
                    ctx.restore();
                };

                const startTime = performance.now();
                const duration = 920;
                const ctrlX = (originX + targetX) / 2 + (originX < targetX ? -60 : 60);
                const ctrlY = Math.min(originY, targetY) - 110;

                const animate = (now) => {
                    const elapsed = now - startTime;
                    const t = Math.min(1, elapsed / duration);
                    ctx.clearRect(0, 0, w, h);

                    // 1. Magische Schockwelle um den Klickpunkt
                    if (elapsed < 480) {
                        const ringP = elapsed / 480;
                        const ringRadius = 8 + ringP * 58;
                        ctx.save();
                        ctx.beginPath();
                        ctx.arc(originX, originY, ringRadius, 0, Math.PI * 2);
                        ctx.strokeStyle = `rgba(251, 191, 36, ${1 - ringP})`;
                        ctx.lineWidth = 2.5 * (1 - ringP);
                        ctx.stroke();
                        ctx.restore();
                    }

                    // 2. Funken- & Herz-Partikel-Supernova
                    for (const p of particles) {
                        p.x += p.vx;
                        p.y += p.vy;
                        p.vy += 0.11;
                        p.vx *= 0.96;
                        p.rot += p.vRot;
                        p.alpha = Math.max(0, 1 - elapsed / 780);

                        if (p.alpha <= 0.01) continue;
                        if (p.isHeart) {
                            drawHeart(p.x, p.y, p.size * 1.4, p.color, p.alpha, p.rot);
                        } else {
                            ctx.save();
                            ctx.globalAlpha = p.alpha;
                            ctx.fillStyle = p.color;
                            ctx.shadowColor = '#fbbf24';
                            ctx.shadowBlur = 8;
                            ctx.beginPath();
                            ctx.arc(p.x, p.y, p.size * 0.45, 0, Math.PI * 2);
                            ctx.fill();
                            ctx.restore();
                        }
                    }

                    // 3. Leuchtendes Haupt-Herz fliegt im Bogen zum Header-Wunschlisten-Icon
                    const ease = t < 0.5 ? 2 * t * t : 1 - Math.pow(-2 * t + 2, 2) / 2;
                    const inv = 1 - ease;
                    const flyX = inv * inv * originX + 2 * inv * ease * ctrlX + ease * ease * targetX;
                    const flyY = inv * inv * (originY - 8) + 2 * inv * ease * ctrlY + ease * ease * targetY;
                    const flyScale = 14 * (1 - 0.35 * ease);
                    drawHeart(flyX, flyY, flyScale, '#f43f5e', 1 - Math.pow(t, 4), 0);

                    if (t < 1) {
                        requestAnimationFrame(animate);
                    } else {
                        canvas.remove();
                        headerIcons.forEach((el) => {
                            el.animate([
                                { transform: 'scale(1)' },
                                { transform: 'scale(1.32)' },
                                { transform: 'scale(1)' }
                            ], { duration: 340, easing: 'cubic-bezier(0.22, 1, 0.36, 1)' });
                        });
                    }
                };

                requestAnimationFrame(animate);
            };
        </script>
    @endonce
</div>
