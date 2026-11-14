package de.meinseelenfunke.app.util

object EmailHtmlSanitizer {

    /**
     * Strips active content, structural wrapper tags, base-URL hijacking tags, external stylesheets,
     * meta refreshes, dangerous URI schemes, and inline JavaScript event handlers from raw email HTML.
     */
    fun sanitizeBodyFragment(rawHtml: String?): String {
        if (rawHtml.isNullOrBlank()) return ""
        return rawHtml
            .replace(Regex("(?is)<script\\b[^>]*>.*?</script>"), "")
            .replace(Regex("(?is)<script\\b[^>]*>"), "")
            .replace(Regex("(?is)<iframe\\b[^>]*>.*?</iframe>"), "")
            .replace(Regex("(?is)<iframe\\b[^>]*>"), "")
            .replace(Regex("(?is)<object\\b[^>]*>.*?</object>"), "")
            .replace(Regex("(?is)<object\\b[^>]*>"), "")
            .replace(Regex("(?is)<embed\\b[^>]*>"), "")
            .replace(Regex("(?is)<form\\b[^>]*>.*?</form>"), "")
            .replace(Regex("(?is)<meta\\b[^>]*http-equiv\\s*=\\s*['\"]?refresh['\"]?[^>]*>"), "")
            .replace(Regex("(?is)<base\\b[^>]*>"), "")
            .replace(Regex("(?is)<link\\b[^>]*>"), "")
            .replace(Regex("(?is)</?head\\b[^>]*>"), "")
            .replace(Regex("(?is)</?body\\b[^>]*>"), "")
            .replace(Regex("(?is)</?html\\b[^>]*>"), "")
            .replace(Regex("(?i)\\bon[a-z0-9_]+\\s*=\\s*('[^']*'|\"[^\"]*\"|[^\\s>]+)"), "")
            .replace(Regex("(?i)(href|src)\\s*=\\s*(['\"]?)\\s*(javascript|vbscript|file):[^'\"\\s>]*\\2"), "$1=\"#\"")
    }

    /**
     * Builds the Content-Security-Policy directive string.
     * Defaults to `img-src data:;` to block remote tracking pixels unless [allowExternalImages] is enabled.
     */
    fun buildCspPolicy(allowExternalImages: Boolean = false): String {
        val imgSrc = if (allowExternalImages) "https: data:" else "data:"
        return "default-src 'none'; style-src 'unsafe-inline'; img-src $imgSrc; font-src data:;"
    }

    /**
     * Wraps sanitized HTML inside a locked-down HTML5 document with a strict CSP header.
     */
    fun wrapSafeHtmlDocument(rawHtml: String?, allowExternalImages: Boolean = false): String {
        val sanitized = sanitizeBodyFragment(rawHtml)
        val csp = buildCspPolicy(allowExternalImages)
        return """
            <!DOCTYPE html>
            <html>
            <head>
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <meta http-equiv="Content-Security-Policy" content="$csp">
            <style>
                body { font-family: sans-serif; word-wrap: break-word; margin: 12px; color: #0F172A; background: #FFFFFF; }
                img { max-width: 100%; height: auto; }
                table { max-width: 100% !important; }
            </style>
            </head>
            <body>$sanitized</body>
            </html>
        """.trimIndent()
    }

    /**
     * Enforces WebView request interception: blocks all network schemes (`https`, `http`, etc.)
     * unless [allowExternalImages] was explicitly enabled by the user, in which case only `https` is allowed.
     */
    fun isAllowedWebResourceScheme(scheme: String?, allowExternalImages: Boolean): Boolean {
        val clean = scheme?.lowercase()?.trim() ?: return false
        if (clean == "data" || clean == "about") return true
        if (allowExternalImages && clean == "https") return true
        return false
    }
}
