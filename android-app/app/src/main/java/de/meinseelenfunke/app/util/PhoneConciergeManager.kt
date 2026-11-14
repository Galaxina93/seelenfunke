package de.meinseelenfunke.app.util

import android.Manifest
import android.app.role.RoleManager
import android.content.Context
import android.content.pm.PackageManager
import android.os.Build
import androidx.core.content.ContextCompat
import com.google.gson.Gson
import com.google.gson.JsonObject
import com.google.gson.reflect.TypeToken
import de.meinseelenfunke.app.data.api.ManagementContactDto
import de.meinseelenfunke.app.data.api.PhoneConciergeCompleteBody
import de.meinseelenfunke.app.data.api.PhoneConciergeConfigUpdateBody
import de.meinseelenfunke.app.data.api.PhoneWhitelistToggleBody
import de.meinseelenfunke.app.di.ServiceLocator
import java.time.Instant

data class PendingPhoneSecretaryNote(
    val callId: String,
    val contactId: String?,
    val callerName: String,
    val greetingName: String,
    val callerPhone: String?,
    val summary: String,
    val urgency: String,
    val callbackRequested: Boolean,
    val createdAtIso: String = Instant.now().toString()
)

data class CachedWhitelistContact(
    val id: String,
    val fullName: String,
    val nickname: String,
    val relationType: String,
    val phone: String,
    val normalizedPhone: String,
    val matchSuffix: String,
    val systemInstructions: String,
    val whitelisted: Boolean
)

object PhoneConciergeManager {

    private const val PREFS_NAME = "funkira_phone_concierge_prefs"
    private const val KEY_ENABLED = "concierge_enabled"
    private const val KEY_RING_DELAY = "concierge_ring_delay_sec"
    private const val KEY_CACHED_CONTACTS_JSON = "concierge_cached_contacts_json"
    private const val KEY_LOCAL_WHITELIST_IDS = "concierge_local_whitelist_ids"
    private const val KEY_SEEDED_DEFAULTS = "concierge_seeded_papa_v1"
    private const val KEY_PENDING_NOTES_ENC = "concierge_pending_secretary_notes_enc_v1"
    private val pendingLock = Any()

    private val gson = Gson()

    private fun prefs(ctx: Context) =
        ctx.applicationContext.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)

    fun isEnabled(ctx: Context): Boolean =
        prefs(ctx).getBoolean(KEY_ENABLED, true)

    fun setEnabledLocal(ctx: Context, enabled: Boolean) {
        prefs(ctx).edit().putBoolean(KEY_ENABLED, enabled).apply()
    }

    fun getRingDelaySeconds(ctx: Context): Int =
        prefs(ctx).getInt(KEY_RING_DELAY, 12).coerceIn(0, 30)

    fun setRingDelayLocal(ctx: Context, seconds: Int) {
        prefs(ctx).edit().putInt(KEY_RING_DELAY, seconds.coerceIn(0, 30)).apply()
    }

    fun normalizePhoneDigits(raw: String?): String {
        if (raw.isNullOrBlank()) return ""
        val firstClause = raw.split(';', ',').firstOrNull()?.trim().orEmpty()
        val slashParts = firstClause.split('/')
        val primary = if (slashParts.size >= 2 &&
            slashParts[0].count { it.isDigit() } >= 7 &&
            slashParts[1].count { it.isDigit() } >= 7
        ) {
            slashParts[0].trim()
        } else {
            firstClause.trim()
        }
        val hasPlus = primary.startsWith("+")
        val digits = primary.filter { it.isDigit() }
        if (digits.isEmpty()) return ""
        return when {
            hasPlus -> digits.trimStart('0')
            digits.startsWith("00") && digits.length > 4 -> digits.substring(2)
            digits.startsWith("0") && digits.length >= 7 -> "49" + digits.substring(1)
            else -> digits
        }
    }

    fun extractMatchSuffix(raw: String?): String {
        val norm = normalizePhoneDigits(raw)
        if (norm.isEmpty()) return ""
        return if (norm.length > 8) norm.takeLast(8) else norm
    }

    fun hasRequiredPermissions(ctx: Context): Boolean {
        val app = ctx.applicationContext
        val baseOk = ContextCompat.checkSelfPermission(app, Manifest.permission.READ_PHONE_STATE) == PackageManager.PERMISSION_GRANTED &&
            ContextCompat.checkSelfPermission(app, Manifest.permission.ANSWER_PHONE_CALLS) == PackageManager.PERMISSION_GRANTED &&
            ContextCompat.checkSelfPermission(app, Manifest.permission.READ_CALL_LOG) == PackageManager.PERMISSION_GRANTED &&
            ContextCompat.checkSelfPermission(app, Manifest.permission.RECORD_AUDIO) == PackageManager.PERMISSION_GRANTED
        val btOk = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
            ContextCompat.checkSelfPermission(app, Manifest.permission.BLUETOOTH_CONNECT) == PackageManager.PERMISSION_GRANTED
        } else true
        return baseOk && btOk
    }

    fun normalizeUrgency(raw: String?): String {
        return when (raw?.trim()?.lowercase()) {
            "hoch", "high", "dringend", "urgent" -> "hoch"
            "niedrig", "low" -> "niedrig"
            else -> "mittel"
        }
    }

    fun hasCallScreeningRole(ctx: Context): Boolean {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.Q) return true
        val rm = ctx.getSystemService(RoleManager::class.java) ?: return false
        return rm.isRoleHeld(RoleManager.ROLE_CALL_SCREENING)
    }

    fun loadCachedContacts(ctx: Context): List<CachedWhitelistContact> {
        val raw = prefs(ctx).getString(KEY_CACHED_CONTACTS_JSON, null)
        if (!raw.isNullOrBlank()) {
            try {
                val listType = object : TypeToken<List<CachedWhitelistContact>>() {}.type
                val parsed: List<CachedWhitelistContact>? = gson.fromJson(raw, listType)
                if (!parsed.isNullOrEmpty()) return parsed
            } catch (_: Exception) { }
        }
        // Built-in safe bootstrap default for Papa (Jan Steinhauer) before first sync
        return listOf(
            CachedWhitelistContact(
                id = "default-papa",
                fullName = "Jan Steinhauer",
                nickname = "Papa",
                relationType = "Familie",
                phone = "+49 176 57793016",
                normalizedPhone = "4917657793016",
                matchSuffix = "57793016",
                systemInstructions = "",
                whitelisted = true
            )
        )
    }

    private fun saveCachedContacts(ctx: Context, contacts: List<CachedWhitelistContact>) {
        prefs(ctx).edit().putString(KEY_CACHED_CONTACTS_JSON, gson.toJson(contacts)).apply()
    }

    fun matchIncomingWhitelistedCaller(ctx: Context, incomingNumber: String?): CachedWhitelistContact? {
        if (!isEnabled(ctx)) return null
        val norm = normalizePhoneDigits(incomingNumber)
        val suffix = extractMatchSuffix(incomingNumber)
        if (norm.isEmpty() && suffix.isEmpty()) return null

        return loadCachedContacts(ctx).firstOrNull { c ->
            if (!c.whitelisted) return@firstOrNull false
            val cNorm = c.normalizedPhone.ifEmpty { normalizePhoneDigits(c.phone) }
            val cSuffix = c.matchSuffix.ifEmpty { extractMatchSuffix(c.phone) }
            (cNorm.isNotEmpty() && cNorm == norm) ||
                (cSuffix.length >= 8 && suffix.length >= 8 && cSuffix == suffix)
        }
    }

    suspend fun syncFromBackend(ctx: Context): List<CachedWhitelistContact> {
        ServiceLocator.ensureInitialized(ctx)
        if (ServiceLocator.getAuthToken().isNullOrBlank()) {
            return loadCachedContacts(ctx)
        }

        flushPendingCompletions(ctx)

        val api = ServiceLocator.getOrganizerApi()
        val p = prefs(ctx)
        val localIds = p.getStringSet(KEY_LOCAL_WHITELIST_IDS, emptySet())?.toMutableSet() ?: mutableSetOf()

        // 1. Try syncing global Phone-Concierge config from Portal (graceful if Stage isn't deployed yet)
        var whitelistedIdsFromConfig: Set<String>? = null
        try {
            val cfgRes = api.getPhoneConciergeConfig()
            val cfg = cfgRes.data
            if (cfgRes.success && cfg != null) {
                setEnabledLocal(ctx, cfg.enabled)
                setRingDelayLocal(ctx, cfg.ring_delay_seconds)
                whitelistedIdsFromConfig = cfg.whitelist_contacts.orEmpty().map { it.id }.toSet()
            }
        } catch (_: Exception) { }

        // 2. Fetch full contact list (with phone numbers) so Alina can toggle any contact in Settings
        try {
            val contactsRes = api.getContacts()
            val allWithPhone = contactsRes.data.orEmpty().filter { !it.phone.isNullOrBlank() }
            if (allWithPhone.isNotEmpty()) {
                val seededOnce = p.getBoolean(KEY_SEEDED_DEFAULTS, false)
                val mapped = allWithPhone.map { dto ->
                    val full = dto.full_name?.takeIf { it.isNotBlank() }
                        ?: listOfNotNull(dto.first_name, dto.last_name).joinToString(" ").trim().ifEmpty { "Kontakt" }
                    val nick = dto.nickname.orEmpty().trim()
                    val isPapaDefault = !seededOnce && (
                        nick.equals("Papa", ignoreCase = true) ||
                        full.contains("Jan Steinhauer", ignoreCase = true) ||
                        normalizePhoneDigits(dto.phone) == "4917657793016"
                    )
                    val isWhitelisted = when {
                        whitelistedIdsFromConfig != null -> whitelistedIdsFromConfig.contains(dto.id)
                        dto.funkira_call_whitelist == true -> true
                        localIds.contains(dto.id) -> true
                        isPapaDefault -> {
                            localIds.add(dto.id)
                            true
                        }
                        else -> false
                    }
                    if (isWhitelisted) localIds.add(dto.id)
                    CachedWhitelistContact(
                        id = dto.id,
                        fullName = full,
                        nickname = nick,
                        relationType = dto.relation_type.orEmpty(),
                        phone = dto.phone.orEmpty(),
                        normalizedPhone = dto.normalized_phone?.takeIf { it.isNotBlank() } ?: normalizePhoneDigits(dto.phone),
                        matchSuffix = dto.match_suffix?.takeIf { it.isNotBlank() } ?: extractMatchSuffix(dto.phone),
                        systemInstructions = dto.system_instructions.orEmpty(),
                        whitelisted = isWhitelisted
                    )
                }
                p.edit()
                    .putBoolean(KEY_SEEDED_DEFAULTS, true)
                    .putStringSet(KEY_LOCAL_WHITELIST_IDS, localIds)
                    .apply()
                saveCachedContacts(ctx, mapped)
                return mapped
            }
        } catch (_: Exception) { }

        return loadCachedContacts(ctx)
    }

    suspend fun updateGlobalConfig(ctx: Context, enabled: Boolean? = null, ringDelaySeconds: Int? = null) {
        enabled?.let { setEnabledLocal(ctx, it) }
        ringDelaySeconds?.let { setRingDelayLocal(ctx, it) }
        ServiceLocator.ensureInitialized(ctx)
        if (ServiceLocator.getAuthToken().isNullOrBlank()) return
        try {
            ServiceLocator.getOrganizerApi().updatePhoneConciergeConfig(
                PhoneConciergeConfigUpdateBody(
                    enabled = enabled,
                    ring_delay_seconds = ringDelaySeconds
                )
            )
        } catch (_: Exception) { }
    }

    suspend fun toggleContactWhitelist(ctx: Context, contactId: String, whitelisted: Boolean): List<CachedWhitelistContact> {
        val p = prefs(ctx)
        val localIds = p.getStringSet(KEY_LOCAL_WHITELIST_IDS, emptySet())?.toMutableSet() ?: mutableSetOf()
        if (whitelisted) localIds.add(contactId) else localIds.remove(contactId)
        p.edit().putStringSet(KEY_LOCAL_WHITELIST_IDS, localIds).apply()

        val updated = loadCachedContacts(ctx).map {
            if (it.id == contactId) it.copy(whitelisted = whitelisted) else it
        }
        saveCachedContacts(ctx, updated)

        ServiceLocator.ensureInitialized(ctx)
        if (!ServiceLocator.getAuthToken().isNullOrBlank()) {
            try {
                ServiceLocator.getOrganizerApi().updateContactCallWhitelist(
                    id = contactId,
                    body = PhoneWhitelistToggleBody(funkira_call_whitelist = whitelisted)
                )
            } catch (_: Exception) { }
        }
        return updated
    }

    suspend fun persistOrQueueSecretaryCompletion(ctx: Context, note: PendingPhoneSecretaryNote): Boolean {
        ServiceLocator.ensureInitialized(ctx)
        if (!ServiceLocator.getAuthToken().isNullOrBlank()) {
            if (sendSingleCompletionToServer(ctx, note)) {
                flushPendingCompletions(ctx)
                return true
            }
        }
        enqueuePendingCompletion(ctx, note)
        return false
    }

    suspend fun flushPendingCompletions(ctx: Context): Int {
        ServiceLocator.ensureInitialized(ctx)
        if (ServiceLocator.getAuthToken().isNullOrBlank()) return 0
        val pending = synchronized(pendingLock) { loadPendingCompletionsUnsafe(ctx).toList() }
        if (pending.isEmpty()) return 0

        val sentIds = mutableSetOf<String>()
        for (item in pending) {
            if (sendSingleCompletionToServer(ctx, item)) {
                sentIds.add(item.callId)
            }
        }
        if (sentIds.isNotEmpty()) {
            synchronized(pendingLock) {
                val remaining = loadPendingCompletionsUnsafe(ctx).filterNot { it.callId in sentIds }
                savePendingCompletionsUnsafe(ctx, remaining)
            }
            ErrorSentinelReporter.captureAutoHeal(
                context = ctx,
                component = "phone_secretary_sync",
                title = "Gepufferte Anrufnotizen nachträglich synchronisiert",
                message = "${sentIds.size} Anrufnotiz(en) aus dem Offline-Puffer erfolgreich übertragen.",
                healSummary = "Alle offline aufgenommenen Anrufanliegen wurden verschlüsselt zwischengespeichert und als Dashboard-Aufgabe übertragen.",
                extraContext = mapOf("flushed_count" to sentIds.size.toString())
            )
        }
        return sentIds.size
    }

    private suspend fun sendSingleCompletionToServer(ctx: Context, note: PendingPhoneSecretaryNote): Boolean {
        val cleanUrgency = normalizeUrgency(note.urgency)
        val cleanContactId = note.contactId?.takeUnless { it == "default-papa" || it.isBlank() }
        try {
            val res = ServiceLocator.getOrganizerApi().completePhoneConciergeCall(
                PhoneConciergeCompleteBody(
                    call_id = note.callId,
                    is_simulation = false,
                    contact_id = cleanContactId,
                    caller_name = note.callerName,
                    greeting_name = note.greetingName.ifBlank { note.callerName },
                    caller_phone = note.callerPhone,
                    handled_by = "android_secretary",
                    summary = note.summary,
                    urgency = cleanUrgency,
                    callback_requested = note.callbackRequested
                )
            )
            if (res.success) return true
        } catch (e: retrofit2.HttpException) {
            if (e.code() == 404) {
                // Fallback if Stage has not pulled /complete yet: create To-Do directly via task_create!
                try {
                    val displayLabel = if (note.greetingName.isNotBlank() && note.greetingName != note.callerName) {
                        "${note.greetingName} (${note.callerName})"
                    } else {
                        note.callerName
                    }
                    val prio = if (cleanUrgency == "hoch" || note.callbackRequested) "hoch" else cleanUrgency
                    val args = JsonObject().apply {
                        addProperty("title", "📞 Anruf von $displayLabel: ${note.summary}".take(235))
                        addProperty("priority", prio)
                    }
                    val fallbackRes = ServiceLocator.aiRepository.executeTool("task_create", args, "android_secretary_${note.callId}")
                    if (fallbackRes.isSuccess) return true
                } catch (_: Exception) { }
            }
            ErrorSentinelReporter.captureError(
                context = ctx,
                tag = "phone_secretary_complete",
                message = "Anrufnotiz konnte vorübergehend nicht gesendet werden (HTTP ${e.code()})",
                throwable = e,
                extraContext = mapOf("http_status" to e.code().toString(), "call_id" to note.callId)
            )
        } catch (e: Exception) {
            ErrorSentinelReporter.captureError(
                context = ctx,
                tag = "phone_secretary_complete",
                message = "Netzwerkfehler beim Speichern der Anrufnotiz (wird lokal verschlüsselt gepuffert): ${e.message}",
                throwable = e,
                extraContext = mapOf("call_id" to note.callId)
            )
        }
        return false
    }

    internal fun serializeEncryptedPendingNotes(notes: List<PendingPhoneSecretaryNote>): String {
        if (notes.isEmpty()) return ""
        return ServiceLocator.encryptPayloadForPrefs(gson.toJson(notes))
    }

    internal fun deserializeEncryptedPendingNotes(encrypted: String?): MutableList<PendingPhoneSecretaryNote> {
        if (encrypted.isNullOrBlank()) return mutableListOf()
        val json = ServiceLocator.decryptPayloadFromPrefs(encrypted) ?: return mutableListOf()
        return try {
            val t = object : TypeToken<MutableList<PendingPhoneSecretaryNote>>() {}.type
            gson.fromJson<MutableList<PendingPhoneSecretaryNote>>(json, t) ?: mutableListOf()
        } catch (_: Exception) {
            mutableListOf()
        }
    }

    private fun enqueuePendingCompletion(ctx: Context, note: PendingPhoneSecretaryNote) {
        synchronized(pendingLock) {
            val list = loadPendingCompletionsUnsafe(ctx)
            list.removeAll { it.callId == note.callId }
            list.add(note)
            while (list.size > 30) {
                list.removeAt(0)
            }
            savePendingCompletionsUnsafe(ctx, list)
        }
    }

    private fun loadPendingCompletionsUnsafe(ctx: Context): MutableList<PendingPhoneSecretaryNote> {
        val enc = prefs(ctx).getString(KEY_PENDING_NOTES_ENC, null)
        return deserializeEncryptedPendingNotes(enc)
    }

    private fun savePendingCompletionsUnsafe(ctx: Context, list: List<PendingPhoneSecretaryNote>) {
        if (list.isEmpty()) {
            prefs(ctx).edit().remove(KEY_PENDING_NOTES_ENC).apply()
            return
        }
        prefs(ctx).edit().putString(KEY_PENDING_NOTES_ENC, serializeEncryptedPendingNotes(list)).apply()
    }
}
