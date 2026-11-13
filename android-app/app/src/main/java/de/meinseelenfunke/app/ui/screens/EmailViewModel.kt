package de.meinseelenfunke.app.ui.screens

import android.content.Context
import android.content.Intent
import android.net.Uri
import android.util.Base64
import androidx.core.content.FileProvider
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import de.meinseelenfunke.app.data.api.EmailAccount
import de.meinseelenfunke.app.data.api.EmailAttachment
import de.meinseelenfunke.app.data.api.EmailMessage
import de.meinseelenfunke.app.data.api.EmailMessageAttachment
import de.meinseelenfunke.app.data.api.EmailProcessedSummary
import de.meinseelenfunke.app.data.api.EmailRecipientDto
import de.meinseelenfunke.app.data.api.ManagementContact
import de.meinseelenfunke.app.di.ServiceLocator
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import java.io.ByteArrayOutputStream
import java.net.URI
import java.nio.charset.Charset
import java.text.SimpleDateFormat
import java.util.Calendar
import java.util.Locale

sealed class EmailUiState {
    object Loading : EmailUiState()
    object Success : EmailUiState()
    data class Error(val message: String) : EmailUiState()
}

data class ComposePrefillState(
    val title: String = "Neue E-Mail schreiben",
    val draftId: String? = null,
    val accountId: String? = null,
    val to: String = "",
    val cc: String = "",
    val bcc: String = "",
    val subject: String = "",
    val body: String = "",
    val forwardedAttachments: List<EmailAttachment> = emptyList()
)

/**
 * 1:1 Port of Funkenwerk Windows Desktop Suite (MainWindow.xaml.cs) & FetchMailsCommand
 * email subject/body sanitizer. Guarantees zero "???", zero raw HTML/CSS dumps, and clean
 * readable text in both snippets and full email reader.
 */
object EmailTextSanitizer {

    private val rfc2047Regex = Regex("""=\?([^?]+)\?([BbQq])\?([^?]*)\?=""")
    private val invisibleCharsRegex = Regex("""[\u200B-\u200D\uFEFF\u00AD\u034F\u2060]+""")
    private val nonVisibleBlocksRegex = Regex(
        """<(head|style|script|xml|title|noscript|svg)\b[^>]*>.*?</\1>""",
        setOf(RegexOption.IGNORE_CASE, RegexOption.DOT_MATCHES_ALL)
    )
    private val htmlCommentsRegex = Regex("""<!--.*?-->""", RegexOption.DOT_MATCHES_ALL)
    private val hiddenSpansRegex = Regex(
        """<([a-z0-9]+)\b[^>]*style\s*=\s*["'][^"']*(?:display\s*:\s*none|visibility\s*:\s*hidden|font-size\s*:\s*0(?:px|pt|em|rem|%)?|line-height\s*:\s*0(?:px|pt|em|rem|%)?|max-height\s*:\s*0(?:px)?|mso-hide\s*:\s*all)[^"']*["'][^>]*>.*?</\1>""",
        setOf(RegexOption.IGNORE_CASE, RegexOption.DOT_MATCHES_ALL)
    )
    private val cssImportRegex = Regex("""@import\s+url\([^)]*\)\s*;?""", RegexOption.IGNORE_CASE)
    private val cssRuleBlockRegex = Regex("""(?:^|\n)\s*(?:[.#@a-zA-Z0-9_:,\-\s\[\]*>+()]+\{[^{}]*\}\s*)+""")
    private val rawHtmlIndicatorRegex = Regex(
        """(?:<!DOCTYPE|<html\b|<head\b|<style\b|@import\s+url|font-family\s*:|text-decoration\s*:|#outlook|<table\b|<div\b|<p\b|<br\b)""",
        RegexOption.IGNORE_CASE
    )
    private val longAngleUrlRegex = Regex("""<(https?://[^>]{65,})>""")

    fun decodeRfc2047(raw: String?): String {
        val str = (raw ?: "").trim()
        if (str.isEmpty() || !str.contains("=?")) return str

        val collapsed = str.replace(Regex("""(\?=)\s+(=\?)"""), "$1$2")
        return rfc2047Regex.replace(collapsed) { match ->
            try {
                val charsetName = match.groupValues[1].trim()
                val mode = match.groupValues[2].uppercase(Locale.ROOT)
                val payload = match.groupValues[3]
                val charset = try {
                    Charset.forName(charsetName)
                } catch (_: Exception) {
                    Charsets.UTF_8
                }

                if (mode == "B") {
                    val decodedBytes = Base64.decode(payload, Base64.DEFAULT)
                    String(decodedBytes, charset)
                } else {
                    val q = payload.replace('_', ' ')
                    val baos = ByteArrayOutputStream()
                    var i = 0
                    while (i < q.length) {
                        val c = q[i]
                        if (c == '=' && i + 2 < q.length) {
                            val hex = q.substring(i + 1, i + 3)
                            val byteVal = hex.toIntOrNull(16)
                            if (byteVal != null) {
                                baos.write(byteVal)
                                i += 3
                                continue
                            }
                        }
                        baos.write(c.code)
                        i++
                    }
                    String(baos.toByteArray(), charset)
                }
            } catch (_: Exception) {
                match.value
            }
        }
    }

    fun repairSubject(rawSubject: String?): String {
        var s = decodeRfc2047(rawSubject).trim()
        if (s.isEmpty()) return "(Kein Betreff)"

        if (s.contains("&")) {
            s = decodeHtmlEntities(s)
        }

        if (s.contains("??") || s.contains('\uFFFD')) {
            s = s.replace('\uFFFD', '?')
            s = s.replace(Regex("""^(\[EXPRESS\]\s+)?\?{2,4}\s*(\[NEUE BESTELLUNG\])"""), "$1💰 $2")
            s = s.replace(Regex("""^\?{2,4}\s*(\[ANGEBOT ANGENOMMEN\])"""), "✅ $1")
            s = s.replace(Regex("""^\?{2,4}\s*(Deine Bestellung\b)"""), "📦 $1")
            s = s.replace(Regex("""^\?{2,4}\s*(Deine Rechnung\b)"""), "🧾 $1")
            s = s.replace(Regex("""^\?{2,4}\s*(Juhu!\s*Ein Freund hat)"""), "🎁 $1")
            s = s.replace(Regex("""^\?{2,4}\s*(Dein Mein-Seelenfunke Gutschein\b)"""), "🎁 $1")
            s = s.replace(Regex("""^\?{2,4}\s*(Dein Geschenkgutschein von\b)"""), "✨ $1")
            s = s.replace(Regex("""^\?{2,4}\s*(Dein exklusives Angebot\b)"""), "✨ $1")
            s = s.replace(Regex("""^\?{2,4}\s*(Ein besonderer Seelenfunke\b)"""), "✨ $1")
            s = s.replace(Regex("""^\?{2,4}\s*(Gute Nachrichten!\s*Deine Bestellung\b)"""), "🚚 $1")
            s = s.replace(Regex("""^\?{2,4}\s*(Vergiss deine Unikate nicht\b)"""), "🛒 $1")
            s = s.replace(Regex("""^\?{2,8}\s*(Wie gefällt dir dein Unikat\b)"""), "📸✨ $1")
            s = s.replace(Regex("""^\?{2,4}\s*(Ihr persönliches Angebot von\b)"""), "🧾 $1")
            s = s.replace(Regex("""^\?{2,4}\s*((\[EXPRESS\]\s+)?Neue Anfrage:)"""), "🧾 $1")
            s = s.replace(Regex("""(Du hast da etwas vergessen!)\s*\?{2,4}$"""), "$1 🛒")
            s = s.replace(Regex("""(wurde versendet!)\s*\?{2,4}$"""), "$1 🚚")
            s = s.replace(Regex("""(Wichtige Rückfrage zu deinem Foto[^?]+)\s*\?{3,8}$"""), "$1 📸✨")
            // Strip any remaining leading/trailing ??? emoji corruption
            s = s.replace(Regex("""^\?{3,8}\s*"""), "")
            s = s.replace(Regex("""\s*\?{3,8}$"""), "")
        }

        return s.trim().ifEmpty { "(Kein Betreff)" }
    }

    fun cleanSenderName(rawName: String?, fallbackEmail: String?): String {
        var name = decodeRfc2047(rawName).trim().trim('"', '\'')
        if (name.contains("&")) {
            name = decodeHtmlEntities(name)
        }
        name = name.replace(Regex("""\?{3,}"""), "").replace("\uFFFD", "").trim()
        return name.ifEmpty { (fallbackEmail ?: "").trim().ifEmpty { "Unbekannt" } }
    }

    fun formatCleanEmailBody(plain: String?, html: String?): String {
        val rawPlain = (plain ?: "").trim()
        val rawHtml = (html ?: "").trim()

        val plainLooksDirty = rawPlain.isEmpty() ||
            rawHtmlIndicatorRegex.containsMatchIn(rawPlain) ||
            (rawPlain.length > 220 && !rawPlain.contains('\n') && rawHtml.isNotEmpty()) ||
            rawPlain.contains("Mein KontoAlina")

        var text = when {
            !plainLooksDirty -> cleanPlainTextFallback(rawPlain)
            rawHtml.isNotEmpty() -> stripHtmlToStructuredText(rawHtml)
            else -> cleanPlainTextFallback(rawPlain)
        }

        if (text.isBlank() && rawHtml.isNotEmpty()) {
            text = stripHtmlToStructuredText(rawHtml)
        }

        if (text.isBlank()) {
            return "(Kein Textinhalt in dieser E-Mail)"
        }

        text = text.replace("\r\n", "\n").replace("\r", "\n")
        text = invisibleCharsRegex.replace(text, "")
        text = text.replace("&zwnj;", "").replace("&nbsp;", " ").replace("\u00A0", " ")
        text = text.replace("\uFFFD", "")

        // Shorten overly long tracking URLs in angle brackets <https://...>
        text = longAngleUrlRegex.replace(text) { m ->
            try {
                val host = URI(m.groupValues[1]).host
                if (!host.isNullOrBlank()) "[$host]" else ""
            } catch (_: Exception) {
                ""
            }
        }

        // Remove isolated lines of ??? corruption
        val lines = text.split('\n').map { line ->
            line.replace(Regex("""[ \t]+"""), " ")
                .replace(Regex("""^\?{3,}\s*"""), "")
                .trim()
        }
        val joined = lines.joinToString("\n")
        return joined.replace(Regex("""\n{3,}"""), "\n\n").trim()
            .ifEmpty { "(Kein Textinhalt in dieser E-Mail)" }
    }

    private fun cleanPlainTextFallback(plain: String): String {
        if (plain.isBlank()) return ""
        var s = plain
        if (rawHtmlIndicatorRegex.containsMatchIn(s)) {
            s = stripHtmlToStructuredText(s)
        }
        s = cssImportRegex.replace(s, "")
        s = cssRuleBlockRegex.replace(s, "\n")
        if (s.contains("&")) {
            s = decodeHtmlEntities(decodeHtmlEntities(s))
        }
        return s.trim()
    }

    fun stripHtmlToStructuredText(html: String?): String {
        if (html.isNullOrBlank()) return ""
        var s = html

        // 1. Strip non-visible blocks (head, style, script, xml, title, noscript, svg) & comments
        s = nonVisibleBlocksRegex.replace(s, "")
        s = htmlCommentsRegex.replace(s, "")
        s = hiddenSpansRegex.replace(s, "")

        // 2. Convert structural HTML tags into clean readable line & paragraph breaks
        s = s.replace(Regex("""<br\s*/?>""", RegexOption.IGNORE_CASE), "\n")
        s = s.replace(Regex("""<hr\s*/?>""", RegexOption.IGNORE_CASE), "\n────────────────────────\n")
        s = s.replace(Regex("""<li(?:\s[^>]*)?>""", RegexOption.IGNORE_CASE), "\n  • ")
        s = s.replace(Regex("""</li>""", RegexOption.IGNORE_CASE), "\n")
        s = s.replace(Regex("""</(td|th)>\s*<(td|th)(?:\s[^>]*)?>""", RegexOption.IGNORE_CASE), "  ·  ")
        s = s.replace(
            Regex("""</?(p|div|tr|h[1-6]|table|blockquote|section|article|header|footer)(?:\s[^>]*)?>""", RegexOption.IGNORE_CASE),
            "\n\n"
        )
        s = s.replace(Regex("""</(td|th)>""", RegexOption.IGNORE_CASE), "  ")

        // 3. Strip remaining inline tags
        s = s.replace(Regex("""<[^>]+>"""), "")

        // 4. Strip leftover CSS rules & decode HTML entities twice
        s = cssImportRegex.replace(s, "")
        s = cssRuleBlockRegex.replace(s, "\n")
        if (s.contains("&")) {
            s = decodeHtmlEntities(decodeHtmlEntities(s))
        }
        s = invisibleCharsRegex.replace(s, "")
        s = s.replace("\u00A0", " ").replace("\uFFFD", "")

        val lines = s.replace("\r\n", "\n").replace("\r", "\n")
            .split('\n')
            .map { it.replace(Regex("""[ \t]+"""), " ").trim() }
        return lines.joinToString("\n").replace(Regex("""\n{3,}"""), "\n\n").trim()
    }

    private fun decodeHtmlEntities(input: String): String {
        var s = input
            .replace("&nbsp;", " ")
            .replace("&zwnj;", "")
            .replace("&amp;", "&")
            .replace("&quot;", "\"")
            .replace("&#039;", "'")
            .replace("&apos;", "'")
            .replace("&lt;", "<")
            .replace("&gt;", ">")
            .replace("&ndash;", "–")
            .replace("&mdash;", "—")
            .replace("&euro;", "€")
            .replace("&copy;", "©")
            .replace("&reg;", "®")
            .replace("&trade;", "™")
            .replace("&bull;", "•")
            .replace("&hellip;", "…")
            .replace("&auml;", "ä")
            .replace("&ouml;", "ö")
            .replace("&uuml;", "ü")
            .replace("&Auml;", "Ä")
            .replace("&Ouml;", "Ö")
            .replace("&Uuml;", "Ü")
            .replace("&szlig;", "ß")

        // Numeric decimal entities &#1234;
        s = Regex("""&#(\d+);""").replace(s) { m ->
            val code = m.groupValues[1].toIntOrNull()
            if (code != null && code in 32..0x10FFFF) {
                String(Character.toChars(code))
            } else ""
        }
        // Numeric hex entities &#x1F4E6;
        s = Regex("""&#[xX]([0-9a-fA-F]+);""").replace(s) { m ->
            val code = m.groupValues[1].toIntOrNull(16)
            if (code != null && code in 32..0x10FFFF) {
                String(Character.toChars(code))
            } else ""
        }
        return s
    }

    fun getPlainSnippet(message: EmailMessage): String {
        val cleanBody = formatCleanEmailBody(message.body, message.htmlBody)
        if (cleanBody == "(Kein Textinhalt in dieser E-Mail)") return ""

        var singleLine = cleanBody.replace(Regex("""\s+"""), " ").trim()
        val cleanSubj = repairSubject(message.subject)
        if (cleanSubj.isNotBlank() && singleLine.startsWith(cleanSubj, ignoreCase = true)) {
            singleLine = singleLine.substring(cleanSubj.length).trimStart(' ', '-', '–', '—', ':', '·')
        }
        return if (singleLine.length > 95) singleLine.take(95) + "…" else singleLine
    }

    fun formatShortDate(rawDate: String?): String {
        if (rawDate.isNullOrBlank()) return ""
        return try {
            val clean = rawDate.trim().replace("T", " ").substringBefore(".")
            val parser = if (clean.length >= 19) {
                SimpleDateFormat("yyyy-MM-dd HH:mm:ss", Locale.GERMANY)
            } else {
                SimpleDateFormat("yyyy-MM-dd HH:mm", Locale.GERMANY)
            }
            val parsed = parser.parse(clean.take(19)) ?: return rawDate.take(10)
            val nowCal = Calendar.getInstance()
            val msgCal = Calendar.getInstance().apply { time = parsed }
            val isToday = nowCal.get(Calendar.YEAR) == msgCal.get(Calendar.YEAR) &&
                nowCal.get(Calendar.DAY_OF_YEAR) == msgCal.get(Calendar.DAY_OF_YEAR)
            if (isToday) {
                SimpleDateFormat("HH:mm", Locale.GERMANY).format(parsed)
            } else {
                SimpleDateFormat("dd.MM.yy", Locale.GERMANY).format(parsed)
            }
        } catch (_: Exception) {
            rawDate.take(10)
        }
    }

    fun formatFullDate(rawDate: String?): String {
        if (rawDate.isNullOrBlank()) return ""
        return try {
            val clean = rawDate.trim().replace("T", " ").substringBefore(".")
            val parser = if (clean.length >= 19) {
                SimpleDateFormat("yyyy-MM-dd HH:mm:ss", Locale.GERMANY)
            } else {
                SimpleDateFormat("yyyy-MM-dd HH:mm", Locale.GERMANY)
            }
            val parsed = parser.parse(clean.take(19)) ?: return rawDate
            SimpleDateFormat("dd.MM.yyyy HH:mm", Locale.GERMANY).format(parsed)
        } catch (_: Exception) {
            rawDate
        }
    }

    fun formatAttachmentSize(bytes: Long): String {
        return when {
            bytes >= 1024L * 1024L -> String.format(Locale.GERMANY, "%.2f MB", bytes / (1024.0 * 1024.0))
            bytes >= 1024L -> String.format(Locale.GERMANY, "%.1f KB", bytes / 1024.0)
            else -> "$bytes B"
        }
    }
}

class EmailViewModel : ViewModel() {

    private val repository = ServiceLocator.emailRepository

    private val _uiState = MutableStateFlow<EmailUiState>(EmailUiState.Loading)
    val uiState: StateFlow<EmailUiState> = _uiState.asStateFlow()

    private val _accounts = MutableStateFlow<List<EmailAccount>>(emptyList())
    val accounts: StateFlow<List<EmailAccount>> = _accounts.asStateFlow()

    private val _processedSummary = MutableStateFlow(EmailProcessedSummary())
    val processedSummary: StateFlow<EmailProcessedSummary> = _processedSummary.asStateFlow()

    private val _messages = MutableStateFlow<List<EmailMessage>>(emptyList())
    val messages: StateFlow<List<EmailMessage>> = _messages.asStateFlow()

    private val _selectedAccount = MutableStateFlow<EmailAccount?>(null)
    val selectedAccount: StateFlow<EmailAccount?> = _selectedAccount.asStateFlow()

    // Supports "INBOX", "Sent", "Drafts", "Junk", "Trash", "Archive", custom folders,
    // plus global funnel views: "Processed" (✨ Verarbeitet) and "GlobalArchive" (📦 Archiv)
    private val _selectedFolder = MutableStateFlow("INBOX")
    val selectedFolder: StateFlow<String> = _selectedFolder.asStateFlow()

    private val _funnelPriorityFilter = MutableStateFlow("all") // "all", "high", "normal"
    val funnelPriorityFilter: StateFlow<String> = _funnelPriorityFilter.asStateFlow()

    private val _funnelCategoryFilter = MutableStateFlow("all")
    val funnelCategoryFilter: StateFlow<String> = _funnelCategoryFilter.asStateFlow()

    private val _selectedMessage = MutableStateFlow<EmailMessage?>(null)
    val selectedMessage: StateFlow<EmailMessage?> = _selectedMessage.asStateFlow()

    private val _searchQuery = MutableStateFlow("")
    val searchQuery: StateFlow<String> = _searchQuery.asStateFlow()

    private val _unreadOnly = MutableStateFlow(false)
    val unreadOnly: StateFlow<Boolean> = _unreadOnly.asStateFlow()

    private val _isSending = MutableStateFlow(false)
    val isSending: StateFlow<Boolean> = _isSending.asStateFlow()

    private val _isSyncing = MutableStateFlow(false)
    val isSyncing: StateFlow<Boolean> = _isSyncing.asStateFlow()

    private val _isAiSorting = MutableStateFlow(false)
    val isAiSorting: StateFlow<Boolean> = _isAiSorting.asStateFlow()

    private val _statusBanner = MutableStateFlow<String?>(null)
    val statusBanner: StateFlow<String?> = _statusBanner.asStateFlow()

    private val _composeAttachments = MutableStateFlow<List<Uri>>(emptyList())
    val composeAttachments: StateFlow<List<Uri>> = _composeAttachments.asStateFlow()

    private val _composePrefill = MutableStateFlow(ComposePrefillState())
    val composePrefill: StateFlow<ComposePrefillState> = _composePrefill.asStateFlow()

    private val _contacts = MutableStateFlow<List<ManagementContact>>(emptyList())
    val contacts: StateFlow<List<ManagementContact>> = _contacts.asStateFlow()

    private val _recipientSuggestions = MutableStateFlow<List<EmailRecipientDto>>(emptyList())
    val recipientSuggestions: StateFlow<List<EmailRecipientDto>> = _recipientSuggestions.asStateFlow()

    fun clearStatusBanner() {
        _statusBanner.value = null
    }

    fun showStatusBanner(message: String) {
        _statusBanner.value = message
    }

    fun prepareComposeNew() {
        _composeAttachments.value = emptyList()
        _composePrefill.value = ComposePrefillState(
            title = "Neue E-Mail schreiben",
            accountId = _selectedAccount.value?.id
        )
    }

    fun prepareComposeReply(msg: EmailMessage) {
        _composeAttachments.value = emptyList()
        val cleanSubj = EmailTextSanitizer.repairSubject(msg.subject)
        val replySubj = if (cleanSubj.startsWith("Re:", ignoreCase = true)) cleanSubj else "Re: $cleanSubj"
        val origBody = EmailTextSanitizer.formatCleanEmailBody(msg.body, msg.htmlBody)
        val senderName = EmailTextSanitizer.cleanSenderName(msg.fromName, msg.fromEmail)
        val senderLabel = if (!msg.fromEmail.isNullOrBlank() && senderName != msg.fromEmail) {
            "$senderName <${msg.fromEmail}>"
        } else {
            msg.fromEmail ?: senderName
        }
        val dateStr = EmailTextSanitizer.formatFullDate(msg.receivedAt)
        val quoteHeader = if (dateStr.isNotBlank()) {
            "Am $dateStr schrieb $senderLabel:"
        } else {
            "Ursprüngliche Nachricht von $senderLabel:"
        }
        val quotedBody = "\n\n────────────────────────────────────────\n$quoteHeader\n$origBody"

        _composePrefill.value = ComposePrefillState(
            title = "Auf E-Mail antworten",
            accountId = msg.accountId ?: _selectedAccount.value?.id,
            to = msg.fromEmail ?: "",
            cc = msg.ccEmail ?: "",
            subject = replySubj,
            body = quotedBody
        )
    }

    fun prepareComposeForward(msg: EmailMessage) {
        _composeAttachments.value = emptyList()
        val cleanSubj = EmailTextSanitizer.repairSubject(msg.subject)
        val fwdSubj = if (cleanSubj.startsWith("Fwd:", ignoreCase = true)) cleanSubj else "Fwd: $cleanSubj"
        val origBody = EmailTextSanitizer.formatCleanEmailBody(msg.body, msg.htmlBody)
        val dateStr = EmailTextSanitizer.formatFullDate(msg.receivedAt)
        val quotedBody = "\n\n────────────────────────────────────────\nWeitergeleitete Nachricht von: ${msg.fromEmail ?: msg.from}\nDatum: $dateStr\n\n$origBody"

        val carriedAttachments = msg.attachments?.map { att ->
            EmailAttachment(
                id = att.id,
                path = att.path ?: "",
                filename = att.filename,
                mime = att.contentType ?: "application/octet-stream",
                size = att.size
            )
        } ?: emptyList()

        _composePrefill.value = ComposePrefillState(
            title = "E-Mail weiterleiten",
            accountId = msg.accountId ?: _selectedAccount.value?.id,
            subject = fwdSubj,
            body = quotedBody,
            forwardedAttachments = carriedAttachments
        )
    }

    fun prepareComposeEditDraft(draft: EmailMessage) {
        _composeAttachments.value = emptyList()
        val cleanSubj = EmailTextSanitizer.repairSubject(draft.subject)
        val cleanBody = EmailTextSanitizer.formatCleanEmailBody(draft.body, draft.htmlBody)
        _composePrefill.value = ComposePrefillState(
            title = "📝 Entwurf weiterbearbeiten",
            draftId = draft.id,
            accountId = draft.accountId ?: _selectedAccount.value?.id,
            to = draft.toEmail ?: "",
            cc = draft.ccEmail ?: "",
            bcc = draft.bccEmail ?: "",
            subject = if (cleanSubj == "(Kein Betreff)") "" else cleanSubj,
            body = if (cleanBody == "(Kein Textinhalt in dieser E-Mail)") "" else cleanBody
        )
    }

    fun addComposeAttachment(uri: Uri) {
        val current = _composeAttachments.value.toMutableList()
        if (!current.contains(uri)) {
            current.add(uri)
            _composeAttachments.value = current
        }
    }

    fun removeComposeAttachment(uri: Uri) {
        val current = _composeAttachments.value.toMutableList()
        current.remove(uri)
        _composeAttachments.value = current
    }

    fun clearComposeAttachments() {
        _composeAttachments.value = emptyList()
    }

    private val webSocketListener: (String, String) -> Unit = { event, _ ->
        if (event == "new_email") {
            loadAccountsAndMessages(showLoading = false)
        }
    }

    init {
        loadAccountsAndMessages()
        loadContactsAndRecipients()
        de.meinseelenfunke.app.data.EmailWebSocketClient.addListener(webSocketListener)
    }

    fun loadContactsAndRecipients() {
        viewModelScope.launch {
            repository.getContacts().onSuccess { contactsList ->
                _contacts.value = contactsList
                rebuildRecipientSuggestions()
            }
            repository.getRecipients().onSuccess { recipientsList ->
                mergeRecipientSuggestions(recipientsList)
            }
        }
    }

    fun loadContacts() {
        loadContactsAndRecipients()
    }

    private fun mergeRecipientSuggestions(fromApi: List<EmailRecipientDto>) {
        val map = linkedMapOf<String, EmailRecipientDto>()
        for (r in _recipientSuggestions.value) {
            if (r.email.isNotBlank()) map[r.email.lowercase(Locale.ROOT)] = r
        }
        for (r in fromApi) {
            val key = r.email.trim().lowercase(Locale.ROOT)
            if (key.isBlank() || !key.contains("@")) continue
            val existing = map[key]
            if (existing == null) {
                map[key] = r.copy(email = key)
            } else {
                map[key] = existing.copy(
                    name = if (existing.name.isBlank() && r.name.isNotBlank()) r.name else existing.name,
                    count = maxOf(existing.count, r.count)
                )
            }
        }
        rebuildRecipientSuggestions(map.values.toList())
    }

    private fun rebuildRecipientSuggestions(seed: List<EmailRecipientDto> = _recipientSuggestions.value) {
        val map = linkedMapOf<String, EmailRecipientDto>()
        for (r in seed) {
            val key = r.email.trim().lowercase(Locale.ROOT)
            if (key.isNotBlank() && key.contains("@")) {
                map[key] = r.copy(email = key)
            }
        }
        for (c in _contacts.value) {
            val em = (c.email ?: "").trim().lowercase(Locale.ROOT)
            if (em.isNotBlank() && em.contains("@")) {
                val fullName = "${c.firstName} ${c.lastName ?: ""}".trim()
                val existing = map[em]
                if (existing == null) {
                    map[em] = EmailRecipientDto(email = em, name = fullName, count = 2)
                } else if (existing.name.isBlank() && fullName.isNotBlank()) {
                    map[em] = existing.copy(name = fullName)
                }
            }
        }
        for (m in _messages.value) {
            val fe = (m.fromEmail ?: "").trim().lowercase(Locale.ROOT)
            if (fe.isNotBlank() && fe.contains("@")) {
                val fn = EmailTextSanitizer.cleanSenderName(m.fromName, null).takeIf { it != "Unbekannt" } ?: ""
                val existing = map[fe]
                if (existing == null) {
                    map[fe] = EmailRecipientDto(email = fe, name = fn, count = 1)
                } else if (existing.name.isBlank() && fn.isNotBlank()) {
                    map[fe] = existing.copy(name = fn)
                }
            }
        }
        _recipientSuggestions.value = map.values.sortedWith(
            compareByDescending<EmailRecipientDto> { it.count }.thenBy { it.email }
        )
    }

    override fun onCleared() {
        super.onCleared()
        de.meinseelenfunke.app.data.EmailWebSocketClient.removeListener(webSocketListener)
    }

    private fun sanitizeMessage(msg: EmailMessage): EmailMessage {
        val repairedSubj = EmailTextSanitizer.repairSubject(msg.subject)
        val cleanName = EmailTextSanitizer.cleanSenderName(msg.fromName, msg.fromEmail)
        return msg.copy(
            subject = repairedSubj,
            fromName = cleanName
        )
    }

    fun loadAccountsAndMessages(showLoading: Boolean = true) {
        viewModelScope.launch {
            if (showLoading) {
                _uiState.value = EmailUiState.Loading
            }
            repository.getAccountsResponse().onSuccess { response ->
                val accountsList = response.data
                _accounts.value = accountsList
                response.processedSummary?.let { _processedSummary.value = it }

                val currentSel = _selectedAccount.value
                if (currentSel == null && accountsList.isNotEmpty()) {
                    _selectedAccount.value = accountsList.firstOrNull { it.isDefault } ?: accountsList.first()
                } else if (currentSel != null) {
                    _selectedAccount.value = accountsList.find { it.id == currentSel.id } ?: accountsList.firstOrNull()
                }
                loadMessages(showLoading = false)
            }.onFailure { exception ->
                _uiState.value = EmailUiState.Error(exception.message ?: "Fehler beim Laden der E-Mail-Konten.")
            }
        }
    }

    fun loadMessages(showLoading: Boolean = false) {
        viewModelScope.launch {
            if (showLoading) {
                _uiState.value = EmailUiState.Loading
            }
            val currentFolder = _selectedFolder.value
            val isGlobalProcessed = currentFolder == "Processed"
            val isGlobalArchive = currentFolder == "GlobalArchive"

            val apiFolder = when {
                isGlobalProcessed -> "Processed"
                isGlobalArchive -> "Archive"
                else -> currentFolder
            }
            val apiAccountId = if (isGlobalProcessed || isGlobalArchive) null else _selectedAccount.value?.id

            repository.getMessages(
                folder = apiFolder,
                accountId = apiAccountId,
                search = _searchQuery.value.ifBlank { null },
                unreadOnly = if (_unreadOnly.value) true else null,
                global = 1
            ).onSuccess { messagesList ->
                val sanitized = messagesList.map { sanitizeMessage(it) }
                _messages.value = sanitized
                rebuildRecipientSuggestions()
                _uiState.value = EmailUiState.Success
            }.onFailure { exception ->
                _uiState.value = EmailUiState.Error(exception.message ?: "Fehler beim Laden der E-Mails.")
            }
        }
    }

    fun selectAccount(account: EmailAccount) {
        _selectedAccount.value = account
        if (_selectedFolder.value == "Processed" || _selectedFolder.value == "GlobalArchive") {
            _selectedFolder.value = "INBOX"
        }
        _funnelPriorityFilter.value = "all"
        _funnelCategoryFilter.value = "all"
        _selectedMessage.value = null
        loadMessages(showLoading = true)
    }

    fun selectFolder(folder: String) {
        _selectedFolder.value = folder
        _funnelPriorityFilter.value = "all"
        _funnelCategoryFilter.value = "all"
        _selectedMessage.value = null
        loadMessages(showLoading = true)
    }

    fun selectGlobalProcessed() {
        _selectedFolder.value = "Processed"
        _funnelPriorityFilter.value = "all"
        _funnelCategoryFilter.value = "all"
        _selectedMessage.value = null
        loadMessages(showLoading = true)
    }

    fun selectGlobalArchive() {
        _selectedFolder.value = "GlobalArchive"
        _funnelPriorityFilter.value = "all"
        _funnelCategoryFilter.value = "all"
        _selectedMessage.value = null
        loadMessages(showLoading = true)
    }

    fun setFunnelPriorityFilter(priority: String) {
        _funnelPriorityFilter.value = priority
    }

    fun setFunnelCategoryFilter(category: String) {
        _funnelCategoryFilter.value = category
    }

    fun clearSelectedMessage() {
        _selectedMessage.value = null
    }

    fun selectMessage(message: EmailMessage) {
        viewModelScope.launch {
            val cleanInitial = sanitizeMessage(message)
            _selectedMessage.value = cleanInitial
            if (!message.isRead) {
                _messages.value = _messages.value.map { msg ->
                    if (msg.id == message.id) msg.copy(isRead = true) else msg
                }
                repository.markMessageRead(message.id, true).onSuccess {
                    repository.getAccountsResponse().onSuccess { resp ->
                        _accounts.value = resp.data
                        resp.processedSummary?.let { _processedSummary.value = it }
                    }
                }
            }
            repository.getMessageDetails(message.id).onSuccess { detailedMsg ->
                val cleanDetailed = sanitizeMessage(detailedMsg.copy(isRead = true))
                _selectedMessage.value = cleanDetailed
                _messages.value = _messages.value.map { msg ->
                    if (msg.id == message.id) cleanDetailed else msg
                }
            }
        }
    }

    fun toggleMessageRead(message: EmailMessage) {
        viewModelScope.launch {
            val newReadStatus = !message.isRead
            _messages.value = _messages.value.map { msg ->
                if (msg.id == message.id) msg.copy(isRead = newReadStatus) else msg
            }
            if (_selectedMessage.value?.id == message.id) {
                _selectedMessage.value = _selectedMessage.value?.copy(isRead = newReadStatus)
            }
            repository.markMessageRead(message.id, newReadStatus).onSuccess {
                repository.getAccountsResponse().onSuccess { resp ->
                    _accounts.value = resp.data
                    resp.processedSummary?.let { _processedSummary.value = it }
                }
            }
        }
    }

    fun setSearchQuery(query: String) {
        _searchQuery.value = query
        loadMessages(showLoading = false)
    }

    fun setUnreadOnly(unread: Boolean) {
        _unreadOnly.value = unread
        loadMessages(showLoading = true)
    }

    fun syncEmails() {
        if (_isSyncing.value) return
        _isSyncing.value = true
        viewModelScope.launch {
            repository.syncEmails().onSuccess { msg ->
                _isSyncing.value = false
                _statusBanner.value = msg
                loadAccountsAndMessages(showLoading = false)
            }.onFailure { err ->
                _isSyncing.value = false
                _statusBanner.value = err.message ?: "Synchronisation fehlgeschlagen."
            }
        }
    }

    fun runAiSort() {
        if (_isAiSorting.value) return
        _isAiSorting.value = true
        viewModelScope.launch {
            repository.aiSortEmails().onSuccess { msg ->
                _isAiSorting.value = false
                _selectedFolder.value = "Processed"
                _funnelPriorityFilter.value = "all"
                _funnelCategoryFilter.value = "all"
                _statusBanner.value = msg
                loadAccountsAndMessages(showLoading = false)
            }.onFailure { err ->
                _isAiSorting.value = false
                _statusBanner.value = err.message ?: "KI-Sortierung fehlgeschlagen."
            }
        }
    }

    fun resolveMessageImportance(message: EmailMessage, makeImportant: Boolean) {
        viewModelScope.launch {
            repository.resolveMessageImportance(message.id, makeImportant).onSuccess { feedback ->
                _statusBanner.value = feedback
                if (!makeImportant && _selectedFolder.value == "Processed") {
                    _messages.value = _messages.value.filter { it.id != message.id }
                    if (_selectedMessage.value?.id == message.id) {
                        _selectedMessage.value = null
                    }
                } else {
                    val newPrio = if (makeImportant) "high" else "normal"
                    _messages.value = _messages.value.map {
                        if (it.id == message.id) it.copy(priority = newPrio) else it
                    }
                    if (_selectedMessage.value?.id == message.id) {
                        _selectedMessage.value = _selectedMessage.value?.copy(priority = newPrio)
                    }
                }
                loadAccountsAndMessages(showLoading = false)
            }.onFailure { err ->
                _statusBanner.value = err.message ?: "Status konnte nicht aktualisiert werden."
            }
        }
    }

    fun createTaskFromMail(message: EmailMessage) {
        viewModelScope.launch {
            repository.createTaskFromMail(message.id).onSuccess { feedback ->
                _statusBanner.value = feedback
            }.onFailure { err ->
                _statusBanner.value = err.message ?: "Aufgabe konnte nicht erstellt werden."
            }
        }
    }

    fun createContactFromMail(message: EmailMessage) {
        viewModelScope.launch {
            repository.createContactFromMail(message.id).onSuccess { feedback ->
                _statusBanner.value = feedback
                loadContactsAndRecipients()
            }.onFailure { err ->
                _statusBanner.value = err.message ?: "Kontakt konnte nicht angelegt werden."
            }
        }
    }

    fun createSenderRule(message: EmailMessage, targetFolder: String, targetFolderLabel: String) {
        viewModelScope.launch {
            repository.createSenderRule(message.id, targetFolder).onSuccess {
                _statusBanner.value = "Regel aktiv: Absender wird automatisch nach '$targetFolderLabel' sortiert."
                loadAccountsAndMessages(showLoading = false)
            }.onFailure { err ->
                _statusBanner.value = err.message ?: "Sortierregel konnte nicht erstellt werden."
            }
        }
    }

    fun downloadAndOpenAttachment(context: Context, attachment: EmailMessageAttachment) {
        viewModelScope.launch {
            _statusBanner.value = "Lade Anhang '${attachment.filename}'..."
            repository.downloadAttachmentToFile(context, attachment.id, attachment.filename).onSuccess { file ->
                _statusBanner.value = null
                try {
                    val uri = FileProvider.getUriForFile(
                        context,
                        "${context.packageName}.fileprovider",
                        file
                    )
                    val mime = attachment.contentType?.takeIf { it.isNotBlank() } ?: "*/*"
                    val viewIntent = Intent(Intent.ACTION_VIEW).apply {
                        setDataAndType(uri, mime)
                        addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION or Intent.FLAG_ACTIVITY_NEW_TASK)
                    }
                    context.startActivity(Intent.createChooser(viewIntent, attachment.filename).apply {
                        addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
                    })
                } catch (_: Exception) {
                    try {
                        val uri = FileProvider.getUriForFile(
                            context,
                            "${context.packageName}.fileprovider",
                            file
                        )
                        val shareIntent = Intent(Intent.ACTION_SEND).apply {
                            type = attachment.contentType ?: "*/*"
                            putExtra(Intent.EXTRA_STREAM, uri)
                            addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
                        }
                        context.startActivity(Intent.createChooser(shareIntent, attachment.filename).apply {
                            addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
                        })
                    } catch (ex: Exception) {
                        _statusBanner.value = "Gespeichert: ${file.name}"
                    }
                }
            }.onFailure { err ->
                _statusBanner.value = err.message ?: "Anhang konnte nicht geladen werden."
            }
        }
    }

    fun deleteMessage(message: EmailMessage) {
        viewModelScope.launch {
            _messages.value = _messages.value.filter { it.id != message.id }
            if (_selectedMessage.value?.id == message.id) {
                _selectedMessage.value = null
            }
            repository.deleteMessage(message.id).onSuccess {
                loadAccountsAndMessages(showLoading = false)
            }
        }
    }

    fun moveMessage(message: EmailMessage, folder: String) {
        viewModelScope.launch {
            _messages.value = _messages.value.filter { it.id != message.id }
            if (_selectedMessage.value?.id == message.id) {
                _selectedMessage.value = null
            }
            repository.moveMessage(message.id, folder).onSuccess {
                loadAccountsAndMessages(showLoading = false)
            }
        }
    }

    fun saveAccount(
        id: String?,
        name: String,
        email: String,
        password: String?,
        imapHost: String,
        imapPort: Int,
        imapEncryption: String?,
        imapUsername: String?,
        smtpHost: String,
        smtpPort: Int,
        smtpEncryption: String?,
        smtpUsername: String?,
        signature: String?,
        isDefault: Boolean,
        isCommercial: Boolean,
        onComplete: (Boolean, String?) -> Unit
    ) {
        viewModelScope.launch {
            repository.saveAccount(
                id, name, email, password, imapHost, imapPort, imapEncryption, imapUsername,
                smtpHost, smtpPort, smtpEncryption, smtpUsername, signature, isDefault, isCommercial
            ).onSuccess {
                loadAccountsAndMessages(showLoading = false)
                onComplete(true, null)
            }.onFailure { exception ->
                val errorMsg = if (exception is retrofit2.HttpException) {
                    try {
                        exception.response()?.errorBody()?.string() ?: exception.message()
                    } catch (e: Exception) {
                        exception.message()
                    }
                } else {
                    exception.localizedMessage ?: exception.message
                }
                onComplete(false, errorMsg)
            }
        }
    }

    fun deleteAccount(id: String, onComplete: (Boolean) -> Unit) {
        viewModelScope.launch {
            repository.deleteAccount(id).onSuccess {
                val updatedAccounts = _accounts.value.filter { it.id != id }
                _accounts.value = updatedAccounts
                if (_selectedAccount.value?.id == id) {
                    _selectedAccount.value = updatedAccounts.firstOrNull()
                }
                loadAccountsAndMessages(showLoading = false)
                onComplete(true)
            }.onFailure {
                onComplete(false)
            }
        }
    }

    fun saveDraft(
        draftId: String?,
        accountId: String?,
        to: String,
        cc: String,
        bcc: String,
        subject: String,
        body: String,
        onComplete: (Boolean) -> Unit = {}
    ) {
        val accId = accountId ?: _selectedAccount.value?.id ?: _accounts.value.firstOrNull()?.id ?: return
        if (to.isBlank() && cc.isBlank() && bcc.isBlank() && subject.isBlank() && body.isBlank()) {
            onComplete(false)
            return
        }
        viewModelScope.launch {
            repository.saveDraft(
                draftId = draftId,
                accountId = accId,
                to = to,
                cc = cc,
                bcc = bcc,
                subject = subject,
                body = body
            ).onSuccess {
                _statusBanner.value = "Entwurf gespeichert."
                loadAccountsAndMessages(showLoading = false)
                onComplete(true)
            }.onFailure {
                onComplete(false)
            }
        }
    }

    fun sendEmail(
        context: Context,
        to: String,
        subject: String,
        body: String,
        cc: String? = null,
        bcc: String? = null,
        accountId: String? = null,
        draftId: String? = null,
        forwardedAttachments: List<EmailAttachment> = emptyList(),
        onComplete: (Boolean) -> Unit
    ) {
        val fromAccount = _accounts.value.find { it.id == accountId }
            ?: _selectedAccount.value
            ?: _accounts.value.firstOrNull()
            ?: return
        _isSending.value = true
        viewModelScope.launch {
            try {
                val uploadedAttachments = mutableListOf<EmailAttachment>()
                uploadedAttachments.addAll(forwardedAttachments)

                val uris = _composeAttachments.value
                for (uri in uris) {
                    val uploadResult = repository.uploadAttachment(context, uri)
                    if (uploadResult.isSuccess) {
                        uploadedAttachments.add(uploadResult.getOrThrow())
                    } else {
                        _isSending.value = false
                        onComplete(false)
                        return@launch
                    }
                }

                val htmlFormattedBody = if (body.contains("<br") || body.contains("<p")) {
                    body
                } else {
                    android.text.TextUtils.htmlEncode(body).replace("\n", "<br>")
                }

                repository.sendEmail(
                    accountId = fromAccount.id,
                    from = fromAccount.email,
                    to = to,
                    subject = subject,
                    body = htmlFormattedBody,
                    attachments = if (uploadedAttachments.isEmpty()) null else uploadedAttachments,
                    draftId = draftId,
                    cc = cc?.takeIf { it.isNotBlank() },
                    bcc = bcc?.takeIf { it.isNotBlank() }
                ).onSuccess {
                    _isSending.value = false
                    clearComposeAttachments()
                    _statusBanner.value = "E-Mail erfolgreich gesendet."
                    onComplete(true)
                    loadAccountsAndMessages(showLoading = false)
                }.onFailure { err ->
                    _isSending.value = false
                    _statusBanner.value = err.message ?: "Senden fehlgeschlagen (als Entwurf gesichert)."
                    onComplete(false)
                }
            } catch (e: Exception) {
                _isSending.value = false
                onComplete(false)
            }
        }
    }

    fun createFolder(name: String, onComplete: (Boolean) -> Unit) {
        val accountId = _selectedAccount.value?.id ?: _accounts.value.firstOrNull()?.id ?: return
        viewModelScope.launch {
            repository.createFolder(accountId, name).onSuccess {
                repository.getAccountsResponse().onSuccess { resp ->
                    val accountsList = resp.data
                    _accounts.value = accountsList
                    val updatedAccount = accountsList.find { it.id == accountId }
                    if (updatedAccount != null) {
                        _selectedAccount.value = updatedAccount
                    }
                }
                onComplete(true)
            }.onFailure {
                onComplete(false)
            }
        }
    }

    fun deleteFolder(name: String, onComplete: (Boolean) -> Unit) {
        val accountId = _selectedAccount.value?.id ?: return
        viewModelScope.launch {
            repository.deleteFolder(accountId, name).onSuccess {
                repository.getAccountsResponse().onSuccess { resp ->
                    val accountsList = resp.data
                    _accounts.value = accountsList
                    val updatedAccount = accountsList.find { it.id == accountId }
                    if (updatedAccount != null) {
                        _selectedAccount.value = updatedAccount
                    }
                }
                if (_selectedFolder.value == name) {
                    _selectedFolder.value = "INBOX"
                }
                loadMessages(showLoading = true)
                onComplete(true)
            }.onFailure {
                onComplete(false)
            }
        }
    }
}
