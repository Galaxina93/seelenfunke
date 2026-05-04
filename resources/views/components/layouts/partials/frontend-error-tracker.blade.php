<script>
    (function() {
        // Vermeide doppelte Registrierung
        if (window._frontendErrorTrackerInitialized) return;
        window._frontendErrorTrackerInitialized = true;

        function logErrorToBackend(message, source, lineno, colno, errorObj, type = 'error') {
            let payload = {
                message: message,
                source: source,
                lineno: lineno,
                colno: colno,
                url: window.location.href,
                userAgent: navigator.userAgent,
                type: type
            };

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
                message = JSON.stringify(event.reason);
            }

            logErrorToBackend(
                message,
                '',
                0,
                0,
                errorObj,
                'promise_rejection'
            );
        });
    })();
</script>
