<div class="relative group z-50">
    @once
        <script>
            (function () {
                function readCsrf() {
                    const meta = document.querySelector('meta[name="csrf-token"]');
                    return meta ? (meta.getAttribute('content') || '') : '';
                }

                if (!window.sfWishlist) {
                    const store = {
                        initialized: false,
                        seq: 0,
                        count: 0,
                        productIds: [],
                        previewItems: [],

                        bootstrap(initial) {
                            if (this.initialized || !initial || typeof initial !== 'object') {
                                return this.snapshot();
                            }
                            this.initialized = true;
                            const ids = Array.isArray(initial.productIds)
                                ? Array.from(new Set(initial.productIds.map(String).filter(Boolean)))
                                : [];
                            this.productIds = ids;
                            this.count = ids.length;
                            this.previewItems = Array.isArray(initial.previewItems) ? initial.previewItems : [];
                            return this.snapshot();
                        },

                        snapshot() {
                            return {
                                seq: this.seq,
                                count: this.count,
                                productIds: this.productIds.slice(),
                                previewItems: this.previewItems.slice(),
                            };
                        },

                        has(productId) {
                            const pid = String(productId || '').trim();
                            return pid !== '' && this.productIds.includes(pid);
                        },

                        broadcast() {
                            const snap = this.snapshot();
                            window.dispatchEvent(new CustomEvent('sf-wishlist-sync', { detail: snap }));
                        },

                        applyAuthoritative(payload) {
                            if (!payload || typeof payload !== 'object') return;
                            const rawIds = Array.isArray(payload.product_ids)
                                ? payload.product_ids
                                : (Array.isArray(payload.productIds) ? payload.productIds : null);
                            if (!rawIds) return;
                            this.initialized = true;
                            this.productIds = Array.from(new Set(rawIds.map(String).filter(Boolean)));
                            this.count = this.productIds.length;
                            if (Array.isArray(payload.preview_items)) {
                                this.previewItems = payload.preview_items;
                            } else if (Array.isArray(payload.previewItems)) {
                                this.previewItems = payload.previewItems;
                            } else {
                                this.previewItems = this.previewItems.filter((it) => this.productIds.includes(String(it.product_id)));
                            }
                            this.broadcast();
                        },

                        set(productId, shouldBeActive, domEvent, opts) {
                            const pid = String(productId || '').trim();
                            if (!pid) return false;

                            const nextActive = typeof shouldBeActive === 'boolean' ? shouldBeActive : !this.has(pid);
                            const mySeq = ++this.seq;
                            this.initialized = true;

                            const filtered = this.productIds.filter((id) => String(id) !== pid);
                            if (nextActive) {
                                filtered.push(pid);
                            } else {
                                this.previewItems = this.previewItems.filter((it) => String(it.product_id) !== pid);
                            }
                            this.productIds = filtered;
                            this.count = filtered.length;

                            window.dispatchEvent(new CustomEvent('wishlist-optimistic-delta', {
                                detail: { productId: pid, added: nextActive, seq: mySeq }
                            }));
                            this.broadcast();

                            if (domEvent && typeof window.sfTriggerWishlistHeartEffect === 'function') {
                                window.sfTriggerWishlistHeartEffect(domEvent, nextActive);
                            }

                            fetch('/wunschliste/toggle', {
                                method: 'POST',
                                credentials: 'same-origin',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest',
                                    'X-CSRF-TOKEN': readCsrf(),
                                },
                                body: JSON.stringify({
                                    product_id: pid,
                                    active: nextActive,
                                    client_seq: mySeq,
                                }),
                            })
                                .then((res) => (res.ok ? res.json() : null))
                                .then((json) => {
                                    if (!json || !json.ok) return;
                                    // Stale-Response-Guard: niemals neuere Klicks überschreiben!
                                    if (mySeq !== this.seq) return;
                                    this.applyAuthoritative(json);

                                    const onWishlistRoute = (window.location.pathname || '').indexOf('/wunschliste') === 0;
                                    if ((onWishlistRoute || (opts && opts.refreshPage)) && window.Livewire && typeof window.Livewire.dispatch === 'function') {
                                        window.Livewire.dispatch('wishlist-updated', {
                                            count: this.count,
                                            productIds: this.productIds.slice(),
                                        });
                                    }
                                })
                                .catch(() => {});

                            return nextActive;
                        },

                        refreshFromServer() {
                            const mySeq = ++this.seq;
                            fetch('/wunschliste/state', {
                                method: 'GET',
                                credentials: 'same-origin',
                                headers: {
                                    'Accept': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest',
                                },
                            })
                                .then((res) => (res.ok ? res.json() : null))
                                .then((json) => {
                                    if (!json || !json.ok || mySeq !== this.seq) return;
                                    this.applyAuthoritative(json);
                                })
                                .catch(() => {});
                        },
                    };

                    window.addEventListener('auth-status-changed', () => {
                        store.refreshFromServer();
                    });

                    window.addEventListener('wishlist-updated', (ev) => {
                        if (ev && ev.detail && (Array.isArray(ev.detail.productIds) || Array.isArray(ev.detail.product_ids))) {
                            store.applyAuthoritative(ev.detail);
                        } else {
                            store.refreshFromServer();
                        }
                    });

                    window.sfWishlist = store;
                }

                window.sfWishlistHeaderData = function (initial) {
                    const snap = window.sfWishlist.bootstrap(initial);
                    return {
                        localCount: snap.count,
                        trackedIds: snap.productIds,
                        items: snap.previewItems,
                        applySync(detail) {
                            if (!detail || typeof detail !== 'object') return;
                            const ids = Array.isArray(detail.productIds) ? detail.productIds.map(String).filter(Boolean) : [];
                            this.trackedIds = ids;
                            this.localCount = ids.length;
                            if (Array.isArray(detail.previewItems)) {
                                this.items = detail.previewItems;
                            }
                        },
                        removeFromHeader(productId, ev) {
                            window.sfWishlist.set(productId, false, ev);
                        },
                    };
                };

                window.sfWishlistButtonData = function (productId, initialWishlisted) {
                    const pid = String(productId || '').trim();
                    return {
                        productId: pid,
                        wishlisted: window.sfWishlist && window.sfWishlist.initialized
                            ? window.sfWishlist.has(pid)
                            : Boolean(initialWishlisted),
                        init() {
                            if (window.sfWishlist && window.sfWishlist.initialized) {
                                this.wishlisted = window.sfWishlist.has(this.productId);
                            }
                        },
                        syncFromStore(detail) {
                            if (detail && Array.isArray(detail.productIds)) {
                                this.wishlisted = detail.productIds.map(String).includes(String(this.productId));
                            }
                        },
                        syncOptimistic(detail) {
                            if (detail && String(detail.productId) === String(this.productId)) {
                                this.wishlisted = Boolean(detail.added);
                            }
                        },
                        toggle(ev) {
                            const next = !this.wishlisted;
                            this.wishlisted = next;
                            window.sfWishlist.set(this.productId, next, ev);
                        },
                    };
                };

                window.sfPlayWishlistSound = function (isAdding) {
                    try {
                        const AudioCtx = window.AudioContext || window.webkitAudioContext;
                        if (!AudioCtx) return;
                        const ctx = window._sfWishlistAudioCtx || (window._sfWishlistAudioCtx = new AudioCtx());
                        if (ctx.state === 'suspended') {
                            ctx.resume().catch(() => {});
                        }
                        const now = ctx.currentTime;
                        if (isAdding) {
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

                window.sfTriggerWishlistHeartEffect = function (event, isAdding = true) {
                    if (typeof window._sfActiveWishlistCanvasCancel === 'function') {
                        window._sfActiveWishlistCanvasCancel();
                        window._sfActiveWishlistCanvasCancel = null;
                    }

                    window.sfPlayWishlistSound(isAdding);

                    const prefersReducedMotion = Boolean(
                        window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches
                    );

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

                    if (prefersReducedMotion) {
                        return;
                    }

                    const canvas = document.createElement('canvas');
                    canvas.style.cssText = 'position:fixed;inset:0;width:100vw;height:100vh;pointer-events:none;z-index:2147483646;';
                    document.body.appendChild(canvas);

                    const dpr = Math.min(window.devicePixelRatio || 1, 2);
                    const w = window.innerWidth;
                    const h = window.innerHeight;
                    canvas.width = w * dpr;
                    canvas.height = h * dpr;
                    const ctx = canvas.getContext('2d');
                    if (!ctx) {
                        canvas.remove();
                        return;
                    }
                    ctx.scale(dpr, dpr);

                    let rafId = 0;
                    let cancelled = false;
                    const cancelCurrent = () => {
                        if (cancelled) return;
                        cancelled = true;
                        if (rafId) cancelAnimationFrame(rafId);
                        if (canvas.parentNode) canvas.parentNode.removeChild(canvas);
                        if (window._sfActiveWishlistCanvasCancel === cancelCurrent) {
                            window._sfActiveWishlistCanvasCancel = null;
                        }
                    };
                    window._sfActiveWishlistCanvasCancel = cancelCurrent;

                    if (!isAdding) {
                        headerIcons.forEach((el) => {
                            if (typeof el.animate === 'function') {
                                el.animate([
                                    { transform: 'scale(1)' },
                                    { transform: 'scale(0.88)' },
                                    { transform: 'scale(1)' }
                                ], { duration: 340, easing: 'cubic-bezier(0.22, 1, 0.36, 1)' });
                            }
                        });

                        const removeStart = performance.now();
                        const removeDuration = 340;
                        const animateRemove = (now) => {
                            if (cancelled) return;
                            const elapsed = now - removeStart;
                            const t = Math.min(1, elapsed / removeDuration);
                            ctx.clearRect(0, 0, w, h);

                            const inv = 1 - t;
                            const ringR = 14 + t * 26;
                            ctx.save();
                            ctx.beginPath();
                            ctx.arc(originX, originY, ringR, 0, Math.PI * 2);
                            ctx.strokeStyle = `rgba(225, 148, 162, ${(0.55 * inv).toFixed(3)})`;
                            ctx.lineWidth = 1.6 * inv;
                            ctx.stroke();

                            ctx.translate(originX, originY + t * 8);
                            const s = (10 + t * 3) * 0.12;
                            ctx.globalAlpha = Math.max(0, 0.72 * inv);
                            ctx.strokeStyle = '#9ca3af';
                            ctx.lineWidth = 1.6;
                            ctx.beginPath();
                            ctx.moveTo(0, s * 3 - s * 5.5);
                            ctx.bezierCurveTo(0, -s * 5.5, -s * 5, -s * 5.5, -s * 5, s * 3 - s * 5.5);
                            ctx.bezierCurveTo(-s * 5, s * 6 - s * 5.5, 0, s * 8.5 - s * 5.5, 0, s * 11 - s * 5.5);
                            ctx.bezierCurveTo(0, s * 8.5 - s * 5.5, s * 5, s * 6 - s * 5.5, s * 5, s * 3 - s * 5.5);
                            ctx.bezierCurveTo(s * 5, -s * 5.5, 0, -s * 5.5, 0, s * 3 - s * 5.5);
                            ctx.closePath();
                            ctx.stroke();
                            ctx.restore();

                            if (t < 1) {
                                rafId = requestAnimationFrame(animateRemove);
                            } else {
                                cancelCurrent();
                            }
                        };

                        rafId = requestAnimationFrame(animateRemove);
                        return;
                    }

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
                        if (cancelled) return;
                        const elapsed = now - startTime;
                        const t = Math.min(1, elapsed / duration);
                        ctx.clearRect(0, 0, w, h);

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

                        const ease = t < 0.5 ? 2 * t * t : 1 - Math.pow(-2 * t + 2, 2) / 2;
                        const inv = 1 - ease;
                        const flyX = inv * inv * originX + 2 * inv * ease * ctrlX + ease * ease * targetX;
                        const flyY = inv * inv * (originY - 8) + 2 * inv * ease * ctrlY + ease * ease * targetY;
                        const flyScale = 14 * (1 - 0.35 * ease);
                        drawHeart(flyX, flyY, flyScale, '#f43f5e', 1 - Math.pow(t, 4), 0);

                        if (t < 1) {
                            rafId = requestAnimationFrame(animate);
                        } else {
                            cancelCurrent();
                            headerIcons.forEach((el) => {
                                if (typeof el.animate === 'function') {
                                    el.animate([
                                        { transform: 'scale(1)' },
                                        { transform: 'scale(1.32)' },
                                        { transform: 'scale(1)' }
                                    ], { duration: 340, easing: 'cubic-bezier(0.22, 1, 0.36, 1)' });
                                }
                            });
                        }
                    };

                    rafId = requestAnimationFrame(animate);
                };
            })();
        </script>
    @endonce

    <div x-data="window.sfWishlistHeaderData(@js(['count' => (int) $count, 'productIds' => array_values(array_map('strval', $productIds ?? [])), 'previewItems' => array_values($previewItems ?? [])]))"
         @sf-wishlist-sync.window="applySync($event.detail)"
         class="relative">
        <a href="{{ route('wishlist') }}"
           data-sf-wishlist-header-icon
           class="text-white hover:text-primary transition-colors duration-300 p-1 block relative"
           aria-label="Wunschliste"
           title="Meine Wunschliste">
            {{-- Herz-Icon --}}
            <svg xmlns="http://www.w3.org/2000/svg"
                 :class="localCount > 0 ? 'text-primary fill-primary/25 drop-shadow-[0_0_8px_rgba(197,160,89,0.5)]' : ''"
                 class="h-7 w-7 transform group-hover:scale-110 transition-all duration-300"
                 fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z" />
            </svg>

            {{-- Badge: existiert per x-if ausschließlich bei localCount > 0 (niemals "0"-Kreis!) --}}
            <template x-if="localCount > 0">
                <span class="absolute -top-1 -right-1 flex h-5 w-5 pointer-events-none">
                    <span x-text="localCount" class="relative inline-flex rounded-full h-5 w-5 bg-primary text-gray-900 text-[10px] font-black justify-center items-center shadow-sm"></span>
                </span>
            </template>
        </a>

        {{-- Mini-Wunschliste Vorschau --}}
        <template x-if="localCount > 0 && items.length > 0">
            <div class="absolute right-0 top-full mt-2 w-72 bg-white rounded-xl shadow-2xl border border-gray-100 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 transform group-hover:translate-y-0 translate-y-2 z-50 overflow-hidden">
                <div class="bg-gray-50 px-4 py-2 border-b border-gray-100 flex items-center justify-between">
                    <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Wunschliste (<span x-text="localCount"></span>)</span>
                    <span class="text-[10px] font-bold text-primary">♥ Merkzettel</span>
                </div>

                <div class="p-3 space-y-2.5 max-h-64 overflow-y-auto">
                    <template x-for="item in items" :key="item.product_id">
                        <div class="flex items-center gap-2.5 group/item">
                            <a :href="item.url || ('/produkt/' + item.slug)" class="w-11 h-11 rounded-lg bg-gray-50 border border-gray-100 overflow-hidden shrink-0">
                                <template x-if="item.preview_url">
                                    <img :src="item.preview_url" :alt="item.name" class="w-full h-full object-cover">
                                </template>
                            </a>
                            <div class="min-w-0 flex-1">
                                <a :href="item.url || ('/produkt/' + item.slug)" class="block text-xs font-bold text-gray-900 hover:text-primary truncate transition-colors" x-text="item.name"></a>
                                <span class="text-[11px] font-semibold text-gray-500 whitespace-nowrap" x-text="item.formatted_price"></span>
                            </div>
                            <button type="button"
                                    @click.prevent="removeFromHeader(item.product_id, $event)"
                                    class="text-gray-300 hover:text-red-500 p-1 transition-colors cursor-pointer"
                                    title="Entfernen">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                    </template>
                </div>

                <a href="{{ route('wishlist') }}" class="block w-full bg-gray-900 text-white text-center py-3 text-xs font-bold uppercase tracking-wider hover:bg-black transition">
                    Zur Wunschliste
                </a>
            </div>
        </template>
    </div>
</div>
