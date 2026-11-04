<script>
    (function() {
        // Universeller Clipboard-Fallback & Polyfill (damit navigator.clipboard.writeText & window.sfCopyText
        // auch über http://127.0.0.1 oder lokale IPs ohne HTTPS jederzeit fehlerfrei funktionieren)
        function fallbackCopyText(text) {
            try {
                const val = String(text ?? '');
                const ta = document.createElement('textarea');
                ta.value = val;
                ta.setAttribute('readonly', '');
                ta.style.position = 'fixed';
                ta.style.top = '-9999px';
                ta.style.left = '-9999px';
                ta.style.opacity = '0';
                ta.style.pointerEvents = 'none';
                document.body.appendChild(ta);
                ta.focus();
                ta.select();
                ta.setSelectionRange(0, val.length);
                const ok = document.execCommand('copy');
                document.body.removeChild(ta);
                return ok;
            } catch (err) {
                return false;
            }
        }

        const nativeClipboard = (typeof navigator !== 'undefined' && navigator.clipboard && typeof navigator.clipboard.writeText === 'function')
            ? navigator.clipboard
            : null;
        const nativeWriteText = nativeClipboard ? nativeClipboard.writeText.bind(nativeClipboard) : null;

        window.sfCopyText = function(text) {
            const val = String(text ?? '');
            if (nativeWriteText && window.isSecureContext) {
                return nativeWriteText(val).catch(function() {
                    fallbackCopyText(val);
                });
            }
            fallbackCopyText(val);
            return Promise.resolve();
        };

        if (typeof navigator !== 'undefined' && (!navigator.clipboard || typeof navigator.clipboard.writeText !== 'function')) {
            const clipboardPolyfill = {
                writeText: function(text) {
                    return window.sfCopyText(text);
                },
                readText: function() {
                    return Promise.resolve('');
                }
            };
            try {
                Object.defineProperty(navigator, 'clipboard', {
                    value: clipboardPolyfill,
                    writable: true,
                    configurable: true
                });
            } catch (e) {
                try {
                    Object.defineProperty(Navigator.prototype, 'clipboard', {
                        get: function() { return clipboardPolyfill; },
                        configurable: true
                    });
                } catch (e2) {}
            }
        }

        // Vermeide doppelte Registrierung des Error-Trackers
        if (window._frontendErrorTrackerInitialized) return;
        window._frontendErrorTrackerInitialized = true;

        function logErrorToBackend(message, source, lineno, colno, errorObj, type = 'js_error', extra = {}) {
            let payload = Object.assign({
                message: String(message || 'Unknown Frontend Error'),
                source: source || window.location.pathname,
                lineno: lineno || 0,
                colno: colno || 0,
                url: window.location.href,
                page_title: document.title || '',
                viewport: (window.innerWidth || 0) + 'x' + (window.innerHeight || 0),
                userAgent: navigator.userAgent,
                type: type
            }, extra);

            if (errorObj && errorObj.stack) {
                payload.stack = errorObj.stack;
            }

            fetch('/api/log/frontend-error', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    // Fallback CSRF-Token, falls im Meta-Tag vorhanden
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                body: JSON.stringify(payload)
            }).catch(e => {
                // Silently fail if logging itself fails to avoid infinite loops
            });
        }

        // Fange klassische JavaScript-Fehler
        window.addEventListener('error', function(event) {
            logErrorToBackend(
                event.message,
                event.filename,
                event.lineno,
                event.colno,
                event.error,
                'js_error'
            );
        });

        // Fange unbehandelte Promise Rejections (z.B. von fetch oder async Funktionen)
        window.addEventListener('unhandledrejection', function(event) {
            let message = 'Unhandled Promise Rejection';
            let errorObj = null;

            if (event.reason instanceof Error) {
                message = event.reason.message;
                errorObj = event.reason;
            } else if (typeof event.reason === 'string') {
                message = event.reason;
            } else {
                try {
                    message = JSON.stringify(event.reason);
                } catch (e) {
                    message = String(event.reason);
                }
            }

            logErrorToBackend(
                message,
                window.location.pathname,
                0,
                0,
                errorObj,
                'promise_rejection'
            );
        });

        // Fange fehlgeschlagene Livewire-Requests (z.B. HTTP 500, 419, 502/504) & synchronisiere Root-Keys gegen PublicPropertyNotFoundException [$]
        document.addEventListener('livewire:init', function() {
            if (!window.Livewire || typeof window.Livewire.hook !== 'function') return;

            window.Livewire.hook('commit.prepare', ({ component }) => {
                try {
                    if (!component || !component.canonical || !component.ephemeral) return;
                    delete component.canonical[''];
                    delete component.ephemeral[''];

                    const cKeys = Object.keys(component.canonical);
                    const eKeys = Object.keys(component.ephemeral);
                    if (cKeys.length === eKeys.length && cKeys.some((k, i) => k !== eKeys[i])) {
                        const aligned = {};
                        eKeys.forEach((k) => {
                            if (Object.prototype.hasOwnProperty.call(component.canonical, k)) {
                                aligned[k] = component.canonical[k];
                            }
                        });
                        cKeys.forEach((k) => {
                            if (!Object.prototype.hasOwnProperty.call(aligned, k)) {
                                aligned[k] = component.canonical[k];
                            }
                        });
                        component.canonical = aligned;
                    }
                } catch (e) {}
            });

            window.Livewire.hook('commit', ({ component, commit }) => {
                try {
                    if (commit && commit.updates && typeof commit.updates === 'object') {
                        Object.keys(commit.updates).forEach((k) => {
                            if (k === '' || k.startsWith('.')) {
                                delete commit.updates[k];
                            }
                        });
                    }
                } catch (e) {}
            });

            window.Livewire.hook('request', ({ uri, options, payload, respond, succeed, fail }) => {
                fail(({ status, content, preventDefault }) => {
                    if (status === 419) return; // Regulärer Session-Ablauf
                    const snippet = typeof content === 'string' ? content.substring(0, 500) : '';
                    logErrorToBackend(
                        `Livewire Request fehlgeschlagen (HTTP ${status}) auf ${window.location.pathname}`,
                        uri || '/livewire/update',
                        0,
                        0,
                        null,
                        'livewire_request_error',
                        { status_code: status, stack: snippet }
                    );
                });
            });
        });
    })();
</script>
