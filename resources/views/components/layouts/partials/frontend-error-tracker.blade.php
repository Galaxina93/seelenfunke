<script>
    (function() {
        // Vermeide doppelte Registrierung
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

        // Fange fehlgeschlagene Livewire-Requests (z.B. HTTP 500, 419, 502/504)
        document.addEventListener('livewire:init', function() {
            if (!window.Livewire || typeof window.Livewire.hook !== 'function') return;
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
