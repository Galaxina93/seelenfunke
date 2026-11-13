package de.meinseelenfunke.app.util

import android.Manifest
import android.app.role.RoleManager
import android.content.Context
import android.content.pm.PackageManager
import android.os.Build
import androidx.core.content.ContextCompat
import com.google.gson.Gson
import com.google.gson.reflect.TypeToken
import de.meinseelenfunke.app.data.api.ManagementContactDto
import de.meinseelenfunke.app.data.api.PhoneConciergeConfigUpdateBody
import de.meinseelenfunke.app.data.api.PhoneWhitelistToggleBody
import de.meinseelenfunke.app.di.ServiceLocator

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
        val primary = raw.split('/').firstOrNull()?.trim().orEmpty()
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
            ContextCompat.checkSelfPermission(app, Manifest.permission.READ_CALL_LOG) == PackageManager.PERMISSION_GRANTED
        val btOk = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
            ContextCompat.checkSelfPermission(app, Manifest.permission.BLUETOOTH_CONNECT) == PackageManager.PERMISSION_GRANTED
        } else true
        return baseOk && btOk
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
}
