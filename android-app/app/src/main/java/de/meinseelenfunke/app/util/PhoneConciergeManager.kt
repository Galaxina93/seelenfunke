package de.meinseelenfunke.app.util

import android.Manifest
import android.annotation.SuppressLint
import android.app.role.RoleManager
import android.content.Context
import android.content.Intent
import android.content.pm.PackageManager
import android.media.AudioManager
import android.net.Uri
import android.os.Build
import android.os.PowerManager
import android.os.SystemClock
import android.telecom.TelecomManager
import android.telephony.SubscriptionManager
import android.telephony.TelephonyCallback
import android.telephony.TelephonyManager
import android.widget.Toast
import androidx.core.content.ContextCompat
import com.google.gson.Gson
import com.google.gson.JsonObject
import com.google.gson.reflect.TypeToken
import de.meinseelenfunke.app.data.api.CallVerificationReportDto
import de.meinseelenfunke.app.data.api.ManagementContactDto
import de.meinseelenfunke.app.data.api.PhoneConciergeCompleteBody
import de.meinseelenfunke.app.data.api.PhoneConciergeConfigUpdateBody
import de.meinseelenfunke.app.data.api.PhoneConciergeSignalBody
import de.meinseelenfunke.app.data.api.PhoneSecretaryCallSummaryDto
import de.meinseelenfunke.app.data.api.PhoneWhitelistToggleBody
import de.meinseelenfunke.app.di.ServiceLocator
import de.meinseelenfunke.app.services.CallNoteVerificationEngine
import de.meinseelenfunke.app.services.CallVerificationInput
import de.meinseelenfunke.app.services.FunkiraPhoneConciergeService
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.Job
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.isActive
import kotlinx.coroutines.launch
import java.time.Instant
import java.time.ZoneId
import java.time.ZonedDateTime
import java.time.format.DateTimeFormatter
import java.util.Locale
import java.util.concurrent.ConcurrentHashMap

enum class CallRoutingDecision {
    REJECT_TO_CARRIER,
    DELAYED_CARRIER_DIVERT,
    ON_DEVICE_SECRETARY
}

data class LocalNoteEdit(
    val summary: String,
    val actionRequired: String,
    val rahmenbedingungen: List<String>,
    val updatedAtIso: String = Instant.now().toString()
)

data class PendingPhoneSecretaryNote(
    val callId: String,
    val contactId: String?,
    val callerName: String,
    val greetingName: String,
    val callerPhone: String?,
    val summary: String,
    val urgency: String,
    val callbackRequested: Boolean,
    val createdAtIso: String = Instant.now().toString(),
    val rahmenbedingungen: List<String> = emptyList(),
    val actionRequired: String = "",
    val durationSeconds: Int = 0,
    val transcript: List<String> = emptyList(),
    val verification: CallVerificationReportDto? = null
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

    const val DEFAULT_GATEWAY_PHONE = "+4953717269023"
    const val DEFAULT_CARRIER_NO_ANSWER_SEC = 20

    const val ANSWER_MODE_AUTO = "auto"
    const val ANSWER_MODE_CARRIER = "carrier"
    const val ANSWER_MODE_ON_DEVICE = "on_device"

    private const val PREFS_NAME = "funkira_phone_concierge_prefs"
    private const val KEY_ENABLED = "concierge_enabled"
    private const val KEY_RING_DELAY = "concierge_ring_delay_sec"
    private const val KEY_ANSWER_MODE = "concierge_answer_mode_v1"
    private const val KEY_CARRIER_CONFIRMED = "concierge_carrier_forwarding_confirmed_v1"
    private const val KEY_AUTO_VERIFY = "concierge_auto_verify_notes_v1"
    private const val KEY_GATEWAY_PHONE = "concierge_gateway_phone_v1"
    private const val KEY_CACHED_CONTACTS_JSON = "concierge_cached_contacts_json"
    private const val KEY_LOCAL_WHITELIST_IDS = "concierge_local_whitelist_ids"
    private const val KEY_DIRTY_CONFIG = "concierge_dirty_config_v1"
    private const val KEY_DIRTY_WHITELIST_MAP_JSON = "concierge_dirty_whitelist_map_v1"
    private const val KEY_DIAG_TRACE_JSON = "concierge_diag_trace_v1"
    private const val KEY_SEEDED_DEFAULTS = "concierge_seeded_papa_v1"
    private const val KEY_PENDING_NOTES_ENC = "concierge_pending_secretary_notes_enc_v1"
    private const val KEY_RECENT_CALLS_ENC = "concierge_recent_calls_enc_v1"
    private const val KEY_LOCAL_CALLS_ENC = "concierge_local_calls_enc_v1"
    private const val KEY_VERIFICATIONS_ENC = "concierge_verifications_enc_v1"
    private const val KEY_NOTE_EDITS_ENC = "concierge_note_edits_enc_v1"
    private const val KEY_SEEN_CALL_IDS = "concierge_seen_call_ids_v1"
    private val pendingLock = Any()
    private val _unseenCallCount = MutableStateFlow(0)
    val unseenCallCountFlow: StateFlow<Int> = _unseenCallCount.asStateFlow()
    private val _recentCallsFlow = MutableStateFlow<List<PhoneSecretaryCallSummaryDto>>(emptyList())
    val recentCallsFlow: StateFlow<List<PhoneSecretaryCallSummaryDto>> = _recentCallsFlow.asStateFlow()
    private val bgScope = CoroutineScope(SupervisorJob() + Dispatchers.Default)
    @Volatile private var delayedDivertJob: Job? = null
    @Volatile private var lastScreenedAtRealtimeMs: Long = 0L

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

    fun getAnswerMode(ctx: Context): String {
        val raw = prefs(ctx).getString(KEY_ANSWER_MODE, ANSWER_MODE_AUTO).orEmpty()
        return when (raw) {
            ANSWER_MODE_CARRIER, ANSWER_MODE_ON_DEVICE, ANSWER_MODE_AUTO -> raw
            else -> ANSWER_MODE_AUTO
        }
    }

    fun setAnswerMode(ctx: Context, mode: String) {
        val clean = when (mode) {
            ANSWER_MODE_CARRIER, ANSWER_MODE_ON_DEVICE -> mode
            else -> ANSWER_MODE_AUTO
        }
        prefs(ctx).edit().putString(KEY_ANSWER_MODE, clean).apply()
    }

    fun isCarrierForwardingConfirmed(ctx: Context): Boolean =
        prefs(ctx).getBoolean(KEY_CARRIER_CONFIRMED, false)

    fun setCarrierForwardingConfirmed(ctx: Context, confirmed: Boolean) {
        prefs(ctx).edit().putBoolean(KEY_CARRIER_CONFIRMED, confirmed).apply()
    }

    fun isAutoVerifyEnabled(ctx: Context): Boolean =
        prefs(ctx).getBoolean(KEY_AUTO_VERIFY, true)

    fun setAutoVerifyEnabled(ctx: Context, enabled: Boolean) {
        prefs(ctx).edit().putBoolean(KEY_AUTO_VERIFY, enabled).apply()
    }

    fun hasMicrophonePermission(ctx: Context): Boolean =
        ContextCompat.checkSelfPermission(ctx.applicationContext, Manifest.permission.RECORD_AUDIO) == PackageManager.PERMISSION_GRANTED

    fun isBatteryOptimizationIgnored(ctx: Context): Boolean {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.M) return true
        val pm = ctx.applicationContext.getSystemService(PowerManager::class.java) ?: return true
        return try {
            pm.isIgnoringBatteryOptimizations(ctx.applicationContext.packageName)
        } catch (_: Exception) {
            true
        }
    }

    internal fun resolveCallRoutingDecision(
        answerMode: String,
        carrierForwardingConfirmed: Boolean,
        delaySeconds: Int,
        hasAnswerPerms: Boolean
    ): CallRoutingDecision {
        return when (answerMode) {
            ANSWER_MODE_ON_DEVICE -> {
                if (hasAnswerPerms) {
                    CallRoutingDecision.ON_DEVICE_SECRETARY
                } else if (delaySeconds <= 0) {
                    CallRoutingDecision.REJECT_TO_CARRIER
                } else {
                    CallRoutingDecision.DELAYED_CARRIER_DIVERT
                }
            }
            ANSWER_MODE_CARRIER -> {
                if (delaySeconds <= 0) CallRoutingDecision.REJECT_TO_CARRIER else CallRoutingDecision.DELAYED_CARRIER_DIVERT
            }
            else -> {
                // AUTO: Nutze direkte Smartphone-Sekretärin, solange Netz-Rufumleitung (**004*) noch nicht bestätigt ist;
                // sobald Netz-Rufumleitung aktiv ist, übernimmt die schnellere Gifhorn-Leitung (Twilio).
                if (!carrierForwardingConfirmed && hasAnswerPerms) {
                    CallRoutingDecision.ON_DEVICE_SECRETARY
                } else if (delaySeconds <= 0) {
                    CallRoutingDecision.REJECT_TO_CARRIER
                } else {
                    CallRoutingDecision.DELAYED_CARRIER_DIVERT
                }
            }
        }
    }

    fun getGatewayPhone(ctx: Context): String =
        sanitizeGatewayPhoneForMmi(prefs(ctx).getString(KEY_GATEWAY_PHONE, DEFAULT_GATEWAY_PHONE))

    fun setGatewayPhoneLocal(ctx: Context, gatewayPhone: String) {
        prefs(ctx).edit().putString(KEY_GATEWAY_PHONE, sanitizeGatewayPhoneForMmi(gatewayPhone)).apply()
    }

    internal fun sanitizeGatewayPhoneForMmi(raw: String?): String {
        val cleaned = raw.orEmpty().trim().filterIndexed { idx, ch -> ch.isDigit() || (idx == 0 && ch == '+') }
        return if (cleaned.count { it.isDigit() } >= 7) cleaned else DEFAULT_GATEWAY_PHONE
    }

    internal fun buildCarrierForwardingActivateMmi(
        gatewayPhone: String? = DEFAULT_GATEWAY_PHONE,
        noAnswerSec: Int = DEFAULT_CARRIER_NO_ANSWER_SEC
    ): String {
        val dest = sanitizeGatewayPhoneForMmi(gatewayPhone)
        val stepSec = (((noAnswerSec.coerceIn(5, 30) + 2) / 5) * 5).coerceIn(5, 30)
        return "**004*$dest**$stepSec#"
    }

    internal fun buildCarrierForwardingDisableMmi(): String = "##004#"

    internal fun shouldRejectImmediatelyInScreening(isWhitelisted: Boolean, delaySeconds: Int): Boolean =
        isWhitelisted && delaySeconds <= 0

    fun markScreenedNow() {
        lastScreenedAtRealtimeMs = SystemClock.elapsedRealtime()
    }

    fun wasScreenedRecently(windowMs: Long = 5_000L): Boolean {
        val prev = lastScreenedAtRealtimeMs
        if (prev <= 0L) return false
        return (SystemClock.elapsedRealtime() - prev) in 0..windowMs
    }

    fun openCarrierForwardingDialer(ctx: Context, activate: Boolean) {
        val mmi = if (activate) {
            buildCarrierForwardingActivateMmi(getGatewayPhone(ctx))
        } else {
            buildCarrierForwardingDisableMmi()
        }
        if (!activate) {
            setCarrierForwardingConfirmed(ctx, false)
        }
        appendDiagnosticTrace(ctx, "Wählfeld geöffnet ($mmi)")
        try {
            // Uri.fromParts encodes trailing '#' as '%23' so Pixel Phone preserves the full GSM MMI string
            val dialUri = Uri.fromParts("tel", mmi, null)
            val intent = Intent(Intent.ACTION_DIAL, dialUri).apply {
                addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
            }
            ctx.startActivity(intent)
            val toastText = if (activate) {
                "Im Telefonfeld einmal den grünen Hörer tippen."
            } else {
                "Grünen Hörer tippen zum Aufheben der Rufumleitung."
            }
            Toast.makeText(ctx, toastText, Toast.LENGTH_LONG).show()
        } catch (_: Exception) {
            Toast.makeText(ctx, "Code: $mmi", Toast.LENGTH_LONG).show()
        }
    }

    fun signalWhitelistDiversionAsync(
        ctx: Context,
        matched: CachedWhitelistContact,
        rawNumber: String,
        callId: String? = null,
        audioMode: String = "twilio_divert"
    ) {
        val app = ctx.applicationContext
        bgScope.launch(Dispatchers.IO) {
            val pm = app.getSystemService(PowerManager::class.java)
            val wl = try {
                pm?.newWakeLock(PowerManager.PARTIAL_WAKE_LOCK, "funkenwerk:call_signal")?.apply {
                    setReferenceCounted(false)
                    acquire(10_000L)
                }
            } catch (_: Exception) { null }
            try {
                ServiceLocator.ensureInitialized(app)
                if (ServiceLocator.getAuthToken().isNullOrBlank()) return@launch
                val cleanId = matched.id.takeUnless { it == "default-papa" || it.isBlank() }
                ServiceLocator.getOrganizerApi().signalPhoneConciergeCall(
                    PhoneConciergeSignalBody(
                        call_id = callId,
                        state = "ringing",
                        is_simulation = false,
                        contact_id = cleanId,
                        caller_name = matched.fullName,
                        caller_nickname = matched.nickname,
                        relation_type = matched.relationType,
                        caller_phone = rawNumber,
                        system_instructions = matched.systemInstructions,
                        audio_mode = audioMode
                    )
                )
            } catch (_: Exception) {
            } finally {
                try {
                    if (wl?.isHeld == true) wl.release()
                } catch (_: Exception) { }
            }
        }
    }

    fun signalCallCancelledAsync(ctx: Context, callId: String?) {
        val app = ctx.applicationContext
        bgScope.launch(Dispatchers.IO) {
            try {
                ServiceLocator.ensureInitialized(app)
                if (ServiceLocator.getAuthToken().isNullOrBlank()) return@launch
                ServiceLocator.getOrganizerApi().signalPhoneConciergeCall(
                    PhoneConciergeSignalBody(
                        call_id = callId,
                        state = "cancelled"
                    )
                )
            } catch (_: Exception) { }
        }
    }

    @SuppressLint("MissingPermission")
    @Suppress("DEPRECATION")
    fun scheduleDelayedCallDiversion(
        ctx: Context,
        matched: CachedWhitelistContact,
        rawNumber: String,
        delaySeconds: Int,
        signalCallId: String? = null
    ) {
        val app = ctx.applicationContext
        delayedDivertJob?.cancel()
        delayedDivertJob = bgScope.launch {
            val pm = app.getSystemService(PowerManager::class.java)
            val wl = try {
                pm?.newWakeLock(PowerManager.PARTIAL_WAKE_LOCK, "funkenwerk:call_divert_timer")?.apply {
                    setReferenceCounted(false)
                    acquire(((delaySeconds.coerceIn(0, 25) + 6) * 1_000L))
                }
            } catch (_: Exception) { null }

            val telecom = app.getSystemService(TelecomManager::class.java)
            val audio = app.getSystemService(AudioManager::class.java)
            val telephony = app.getSystemService(TelephonyManager::class.java)
            val subStates = ConcurrentHashMap<Int, Int>()
            val registeredCallbacks = registerSubscriptionCallbacksSafe(app, telephony, subStates)

            try {
                val targetMs = delaySeconds.coerceIn(0, 30) * 1_000L
                var elapsedMs = 0L
                var sawRingingOnce = false
                val hasPhoneState = ContextCompat.checkSelfPermission(
                    app,
                    Manifest.permission.READ_PHONE_STATE
                ) == PackageManager.PERMISSION_GRANTED

                while (isActive && elapsedMs < targetMs) {
                    delay(250L)
                    elapsedMs += 250L
                    val inCall = if (hasPhoneState) {
                        try { telecom?.isInCall == true } catch (_: Exception) { true }
                    } else {
                        true
                    }
                    val legacyState = if (hasPhoneState) {
                        try { telephony?.callState ?: TelephonyManager.CALL_STATE_RINGING } catch (_: Exception) { TelephonyManager.CALL_STATE_RINGING }
                    } else {
                        TelephonyManager.CALL_STATE_RINGING
                    }
                    val unified = FunkiraPhoneConciergeService.resolveUnifiedCallState(
                        telecomInCall = inCall,
                        audioMode = audio?.mode ?: AudioManager.MODE_NORMAL,
                        legacyTelephonyState = legacyState,
                        subscriptionStates = subStates.values,
                        secretaryAudioOverrideActive = false
                    )
                    if (unified == TelephonyManager.CALL_STATE_RINGING) {
                        sawRingingOnce = true
                    }
                    if (unified == TelephonyManager.CALL_STATE_OFFHOOK) {
                        appendDiagnosticTrace(app, "Selbst angenommen (${matched.fullName})")
                        signalCallCancelledAsync(app, signalCallId)
                        return@launch
                    }
                    if ((sawRingingOnce || elapsedMs >= 3_500L) && unified == TelephonyManager.CALL_STATE_IDLE) {
                        appendDiagnosticTrace(app, "Anrufer aufgelegt (${matched.fullName})")
                        signalCallCancelledAsync(app, signalCallId)
                        return@launch
                    }
                }

                if (!isActive) return@launch
                if (ContextCompat.checkSelfPermission(app, Manifest.permission.ANSWER_PHONE_CALLS) == PackageManager.PERMISSION_GRANTED) {
                    val ended = try {
                        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.P) {
                            telecom?.endCall() == true
                        } else {
                            false
                        }
                    } catch (_: Exception) {
                        false
                    }
                    appendDiagnosticTrace(
                        app,
                        if (ended) "Nach ${delaySeconds}s an Funkira übergeben" else "Netz-Weiterleitung greift (${matched.fullName})"
                    )
                } else {
                    appendDiagnosticTrace(app, "Warte auf Netz-Weiterleitung (${matched.fullName})")
                }
            } finally {
                unregisterSubscriptionCallbacksSafe(registeredCallbacks)
                try {
                    if (wl?.isHeld == true) wl.release()
                } catch (_: Exception) { }
            }
        }
    }

    @SuppressLint("MissingPermission")
    private fun registerSubscriptionCallbacksSafe(
        app: Context,
        baseTelephony: TelephonyManager?,
        subStates: ConcurrentHashMap<Int, Int>
    ): List<Pair<TelephonyManager, TelephonyCallback>> {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.S || baseTelephony == null) return emptyList()
        if (ContextCompat.checkSelfPermission(app, Manifest.permission.READ_PHONE_STATE) != PackageManager.PERMISSION_GRANTED) {
            return emptyList()
        }
        val list = mutableListOf<Pair<TelephonyManager, TelephonyCallback>>()
        val subIds = mutableSetOf<Int>()
        try {
            val sm = app.getSystemService(SubscriptionManager::class.java)
            sm?.activeSubscriptionInfoList?.forEach { subIds.add(it.subscriptionId) }
        } catch (_: Exception) { }
        val targets = if (subIds.isNotEmpty()) {
            subIds.map { it to baseTelephony.createForSubscriptionId(it) }
        } else {
            listOf(-1 to baseTelephony)
        }
        for ((subId, tm) in targets) {
            try {
                val cb = object : TelephonyCallback(), TelephonyCallback.CallStateListener {
                    override fun onCallStateChanged(state: Int) {
                        subStates[subId] = state
                    }
                }
                tm.registerTelephonyCallback(app.mainExecutor, cb)
                list.add(tm to cb)
            } catch (_: Exception) { }
        }
        return list
    }

    private fun unregisterSubscriptionCallbacksSafe(registered: List<Pair<TelephonyManager, TelephonyCallback>>) {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.S) return
        for ((tm, cb) in registered) {
            try { tm.unregisterTelephonyCallback(cb) } catch (_: Exception) { }
        }
    }

    fun normalizePhoneDigits(raw: String?): String {
        if (raw.isNullOrBlank()) return ""
        val decoded = raw.trim()
            .replace("%2B", "+", ignoreCase = true)
            .removePrefix("tel:")
            .removePrefix("TEL:")
            .removePrefix("sips:")
            .removePrefix("SIPS:")
            .removePrefix("sip:")
            .removePrefix("SIP:")
            .substringBefore('@') // Strip VoWiFi / IMS SIP host (e.g. @ims.mnc001.mcc262.3gppnetwork.org)
        val firstClause = decoded.split(';', ',').firstOrNull()?.trim().orEmpty()
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

    fun appendDiagnosticTrace(ctx: Context, step: String) {
        try {
            val ts = java.time.LocalTime.now().withNano(0).toString()
            val entry = "$ts $step".take(160)
            synchronized(pendingLock) {
                val cur = getDiagnosticTraceLines(ctx).toMutableList()
                cur.add(entry)
                while (cur.size > 10) {
                    cur.removeAt(0)
                }
                prefs(ctx).edit().putString(KEY_DIAG_TRACE_JSON, gson.toJson(cur)).apply()
            }
        } catch (_: Exception) { }
    }

    fun getDiagnosticTraceLines(ctx: Context): List<String> {
        val raw = prefs(ctx).getString(KEY_DIAG_TRACE_JSON, null) ?: return emptyList()
        return try {
            val t = object : TypeToken<List<String>>() {}.type
            gson.fromJson<List<String>>(raw, t) ?: emptyList()
        } catch (_: Exception) {
            emptyList()
        }
    }

    fun clearDiagnosticTrace(ctx: Context) {
        synchronized(pendingLock) {
            prefs(ctx).edit().remove(KEY_DIAG_TRACE_JSON).apply()
        }
    }

    fun extractMatchSuffix(raw: String?): String {
        val norm = normalizePhoneDigits(raw)
        if (norm.isEmpty()) return ""
        return if (norm.length > 8) norm.takeLast(8) else norm
    }

    /**
     * Core runtime permissions strictly required for Whitelist Call Screening (including saved Google Contacts)
     * and delayed diversion (`TelecomManager.endCall()`).
     */
    fun hasRequiredPermissions(ctx: Context): Boolean {
        val app = ctx.applicationContext
        return ContextCompat.checkSelfPermission(app, Manifest.permission.READ_PHONE_STATE) == PackageManager.PERMISSION_GRANTED &&
            ContextCompat.checkSelfPermission(app, Manifest.permission.ANSWER_PHONE_CALLS) == PackageManager.PERMISSION_GRANTED &&
            ContextCompat.checkSelfPermission(app, Manifest.permission.READ_CONTACTS) == PackageManager.PERMISSION_GRANTED
    }

    fun hasAnswerExecutionPermissions(ctx: Context): Boolean {
        val app = ctx.applicationContext
        return ContextCompat.checkSelfPermission(app, Manifest.permission.READ_PHONE_STATE) == PackageManager.PERMISSION_GRANTED &&
            ContextCompat.checkSelfPermission(app, Manifest.permission.ANSWER_PHONE_CALLS) == PackageManager.PERMISSION_GRANTED
    }

    fun missingCorePermissionLabels(ctx: Context): List<String> {
        val app = ctx.applicationContext
        val missing = mutableListOf<String>()
        if (ContextCompat.checkSelfPermission(app, Manifest.permission.READ_PHONE_STATE) != PackageManager.PERMISSION_GRANTED) {
            missing.add("Telefonstatus")
        }
        if (ContextCompat.checkSelfPermission(app, Manifest.permission.ANSWER_PHONE_CALLS) != PackageManager.PERMISSION_GRANTED) {
            missing.add("Anrufe verwalten")
        }
        if (ContextCompat.checkSelfPermission(app, Manifest.permission.READ_CONTACTS) != PackageManager.PERMISSION_GRANTED) {
            missing.add("Kontakte")
        }
        return missing
    }

    fun buildRuntimePermissionRequestArray(sdkInt: Int = Build.VERSION.SDK_INT): Array<String> {
        val perms = mutableListOf(
            Manifest.permission.READ_PHONE_STATE,
            Manifest.permission.ANSWER_PHONE_CALLS,
            Manifest.permission.READ_CONTACTS
        )
        if (sdkInt >= Build.VERSION_CODES.TIRAMISU) {
            perms.add(Manifest.permission.POST_NOTIFICATIONS)
        }
        return perms.toTypedArray()
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

    private fun loadSeenCallIds(ctx: Context): Set<String> =
        prefs(ctx).getStringSet(KEY_SEEN_CALL_IDS, emptySet())?.toSet().orEmpty()

    internal fun countUnseenCallsInSet(
        calls: List<PhoneSecretaryCallSummaryDto>,
        seenIds: Set<String>
    ): Int {
        val validIds = calls.mapNotNull { it.id.takeIf(String::isNotBlank) }.distinct()
        if (validIds.isEmpty()) return 0
        return validIds.count { it !in seenIds }
    }

    fun countUnseenCalls(ctx: Context, calls: List<PhoneSecretaryCallSummaryDto>): Int =
        countUnseenCallsInSet(calls, loadSeenCallIds(ctx))

    fun refreshUnseenCount(ctx: Context): Int {
        val count = countUnseenCalls(ctx, loadCachedRecentCalls(ctx))
        _unseenCallCount.value = count
        return count
    }

    fun markAllCallsSeen(
        ctx: Context,
        calls: List<PhoneSecretaryCallSummaryDto> = loadCachedRecentCalls(ctx)
    ) {
        val idsToMark = calls.mapNotNull { it.id.takeIf(String::isNotBlank) }
        if (idsToMark.isNotEmpty()) {
            val merged = LinkedHashSet<String>()
            merged.addAll(idsToMark)
            merged.addAll(loadSeenCallIds(ctx))
            val trimmed = HashSet(merged.take(60))
            prefs(ctx).edit().putStringSet(KEY_SEEN_CALL_IDS, trimmed).apply()
        }
        _unseenCallCount.value = 0
    }

    private fun formatLocalCallTimeLabel(iso: String): String {
        return try {
            val berlinZone = ZoneId.of("Europe/Berlin")
            val dt = Instant.parse(iso).atZone(berlinZone)
            val today = ZonedDateTime.now(berlinZone).toLocalDate()
            val timeStr = dt.format(DateTimeFormatter.ofPattern("HH:mm", Locale.GERMAN))
            when (dt.toLocalDate()) {
                today -> "Heute, $timeStr"
                today.minusDays(1) -> "Gestern, $timeStr"
                else -> dt.format(DateTimeFormatter.ofPattern("dd.MM.yyyy, HH:mm", Locale.GERMAN))
            }
        } catch (_: Exception) {
            "Heute"
        }
    }

    private fun loadLocalCallsUnsafe(ctx: Context): MutableList<PhoneSecretaryCallSummaryDto> {
        val enc = prefs(ctx).getString(KEY_LOCAL_CALLS_ENC, null) ?: return mutableListOf()
        val json = ServiceLocator.decryptPayloadFromPrefs(enc) ?: return mutableListOf()
        return try {
            val t = object : TypeToken<MutableList<PhoneSecretaryCallSummaryDto>>() {}.type
            gson.fromJson<MutableList<PhoneSecretaryCallSummaryDto>>(json, t) ?: mutableListOf()
        } catch (_: Exception) {
            mutableListOf()
        }
    }

    private fun saveLocalCallsUnsafe(ctx: Context, calls: List<PhoneSecretaryCallSummaryDto>) {
        if (calls.isEmpty()) {
            prefs(ctx).edit().remove(KEY_LOCAL_CALLS_ENC).apply()
            return
        }
        val enc = ServiceLocator.encryptPayloadForPrefs(gson.toJson(calls.take(25)))
        prefs(ctx).edit().putString(KEY_LOCAL_CALLS_ENC, enc).apply()
    }

    private fun loadVerificationMapUnsafe(ctx: Context): MutableMap<String, CallVerificationReportDto> {
        val enc = prefs(ctx).getString(KEY_VERIFICATIONS_ENC, null) ?: return mutableMapOf()
        val json = ServiceLocator.decryptPayloadFromPrefs(enc) ?: return mutableMapOf()
        return try {
            val t = object : TypeToken<MutableMap<String, CallVerificationReportDto>>() {}.type
            gson.fromJson<MutableMap<String, CallVerificationReportDto>>(json, t) ?: mutableMapOf()
        } catch (_: Exception) {
            mutableMapOf()
        }
    }

    private fun saveVerificationMapUnsafe(ctx: Context, map: Map<String, CallVerificationReportDto>) {
        if (map.isEmpty()) {
            prefs(ctx).edit().remove(KEY_VERIFICATIONS_ENC).apply()
            return
        }
        val trimmed = map.entries.toList().takeLast(60).associate { it.key to it.value }
        val enc = ServiceLocator.encryptPayloadForPrefs(gson.toJson(trimmed))
        prefs(ctx).edit().putString(KEY_VERIFICATIONS_ENC, enc).apply()
    }

    private fun loadNoteEditsMapUnsafe(ctx: Context): MutableMap<String, LocalNoteEdit> {
        val enc = prefs(ctx).getString(KEY_NOTE_EDITS_ENC, null) ?: return mutableMapOf()
        val json = ServiceLocator.decryptPayloadFromPrefs(enc) ?: return mutableMapOf()
        return try {
            val t = object : TypeToken<MutableMap<String, LocalNoteEdit>>() {}.type
            gson.fromJson<MutableMap<String, LocalNoteEdit>>(json, t) ?: mutableMapOf()
        } catch (_: Exception) {
            mutableMapOf()
        }
    }

    private fun saveNoteEditsMapUnsafe(ctx: Context, map: Map<String, LocalNoteEdit>) {
        if (map.isEmpty()) {
            prefs(ctx).edit().remove(KEY_NOTE_EDITS_ENC).apply()
            return
        }
        val trimmed = map.entries.toList().takeLast(60).associate { it.key to it.value }
        val enc = ServiceLocator.encryptPayloadForPrefs(gson.toJson(trimmed))
        prefs(ctx).edit().putString(KEY_NOTE_EDITS_ENC, enc).apply()
    }

    internal fun applyLocalEnhancementsToCalls(
        remoteCalls: List<PhoneSecretaryCallSummaryDto>,
        localCalls: List<PhoneSecretaryCallSummaryDto>,
        verifications: Map<String, CallVerificationReportDto>,
        edits: Map<String, LocalNoteEdit>
    ): List<PhoneSecretaryCallSummaryDto> {
        val remoteIds = buildSet {
            remoteCalls.forEach { call ->
                if (call.id.isNotBlank()) add(call.id)
                call.client_call_id?.takeIf { it.isNotBlank() }?.let { add(it) }
            }
        }
        val combined = mutableListOf<PhoneSecretaryCallSummaryDto>()
        localCalls.forEach { local ->
            val localKeyPresent = (local.id.isNotBlank() && local.id in remoteIds) ||
                (!local.client_call_id.isNullOrBlank() && local.client_call_id in remoteIds)
            if (local.id.isNotBlank() && !localKeyPresent) {
                combined.add(local)
            }
        }
        combined.addAll(remoteCalls)

        return combined.map { call ->
            val ver = verifications[call.id]
                ?: call.client_call_id?.let { verifications[it] }
                ?: call.verification
            val edit = edits[call.id]
                ?: call.client_call_id?.let { edits[it] }
            val effectiveSummary = when {
                edit != null && edit.summary.isNotBlank() -> edit.summary
                ver != null && ver.status == CallNoteVerificationEngine.STATUS_CORRECTED && ver.corrected_summary.isNotBlank() ->
                    ver.corrected_summary
                else -> call.summary
            }
            val effectiveRahmen = when {
                edit != null -> edit.rahmenbedingungen
                ver != null && ver.status == CallNoteVerificationEngine.STATUS_CORRECTED && !ver.corrected_rahmenbedingungen.isNullOrEmpty() ->
                    ver.corrected_rahmenbedingungen
                else -> call.rahmenbedingungen
            }
            val effectiveAction = when {
                edit != null && edit.actionRequired.isNotBlank() -> edit.actionRequired
                ver != null && ver.status == CallNoteVerificationEngine.STATUS_CORRECTED && ver.corrected_action_required.isNotBlank() ->
                    ver.corrected_action_required
                else -> call.action_required
            }
            val effectiveUrgency = if (ver != null && ver.status == CallNoteVerificationEngine.STATUS_CORRECTED && ver.corrected_urgency.isNotBlank()) {
                ver.corrected_urgency
            } else {
                call.urgency
            }
            call.copy(
                summary = effectiveSummary,
                rahmenbedingungen = effectiveRahmen,
                action_required = effectiveAction,
                urgency = effectiveUrgency,
                verification = ver
            )
        }
    }

    fun loadCachedRecentCalls(ctx: Context): List<PhoneSecretaryCallSummaryDto> {
        val rawRemote = synchronized(pendingLock) {
            val enc = prefs(ctx).getString(KEY_RECENT_CALLS_ENC, null)
            val json = if (!enc.isNullOrBlank()) ServiceLocator.decryptPayloadFromPrefs(enc) else null
            if (json != null) {
                try {
                    val t = object : TypeToken<List<PhoneSecretaryCallSummaryDto>>() {}.type
                    gson.fromJson<List<PhoneSecretaryCallSummaryDto>>(json, t) ?: emptyList()
                } catch (_: Exception) {
                    emptyList()
                }
            } else {
                emptyList()
            }
        }
        val merged = synchronized(pendingLock) {
            applyLocalEnhancementsToCalls(
                remoteCalls = rawRemote,
                localCalls = loadLocalCallsUnsafe(ctx),
                verifications = loadVerificationMapUnsafe(ctx),
                edits = loadNoteEditsMapUnsafe(ctx)
            )
        }
        _recentCallsFlow.value = merged
        _unseenCallCount.value = countUnseenCalls(ctx, merged)
        return merged
    }

    private fun saveCachedRecentCalls(ctx: Context, calls: List<PhoneSecretaryCallSummaryDto>): List<PhoneSecretaryCallSummaryDto> {
        synchronized(pendingLock) {
            if (calls.isEmpty()) {
                prefs(ctx).edit().remove(KEY_RECENT_CALLS_ENC).apply()
            } else {
                val enc = ServiceLocator.encryptPayloadForPrefs(gson.toJson(calls))
                prefs(ctx).edit().putString(KEY_RECENT_CALLS_ENC, enc).apply()
            }
        }
        return loadCachedRecentCalls(ctx)
    }

    suspend fun fetchRecentCalls(ctx: Context): List<PhoneSecretaryCallSummaryDto> {
        ServiceLocator.ensureInitialized(ctx)
        if (ServiceLocator.getAuthToken().isNullOrBlank()) {
            return loadCachedRecentCalls(ctx)
        }
        val merged = try {
            val cfgRes = ServiceLocator.getOrganizerApi().getPhoneConciergeConfig()
            val cfg = cfgRes.data
            if (cfgRes.success && cfg != null) {
                saveCachedRecentCalls(ctx, cfg.recent_calls.orEmpty())
            } else {
                loadCachedRecentCalls(ctx)
            }
        } catch (_: Exception) {
            loadCachedRecentCalls(ctx)
        }
        if (isAutoVerifyEnabled(ctx)) {
            verifyUncheckedCallsInBackground(ctx, merged)
        }
        return loadCachedRecentCalls(ctx)
    }

    private suspend fun verifyUncheckedCallsInBackground(ctx: Context, calls: List<PhoneSecretaryCallSummaryDto>) {
        val candidates = calls.filter { c ->
            c.id.isNotBlank() &&
                c.verification == null &&
                !c.transcript.isNullOrEmpty() &&
                CallNoteVerificationEngine.extractCallerLines(c.transcript).isNotEmpty()
        }.take(2)
        for (c in candidates) {
            try {
                verifyCallNow(ctx, c)
            } catch (_: Exception) { }
        }
    }

    suspend fun verifyCallNow(ctx: Context, call: PhoneSecretaryCallSummaryDto): PhoneSecretaryCallSummaryDto {
        val input = CallVerificationInput(
            callerName = call.caller_name,
            greetingName = call.caller_name,
            transcriptLines = call.transcript.orEmpty(),
            understoodSummary = call.summary,
            understoodRahmenbedingungen = call.rahmenbedingungen.orEmpty(),
            understoodActionRequired = call.action_required,
            understoodUrgency = call.urgency,
            understoodCallbackRequested = call.callback_requested
        )
        val report = CallNoteVerificationEngine.verifyCallNotes(ctx, input, allowNetwork = true)
        synchronized(pendingLock) {
            val map = loadVerificationMapUnsafe(ctx)
            map[call.id] = report
            saveVerificationMapUnsafe(ctx, map)
        }
        val refreshed = loadCachedRecentCalls(ctx)
        return refreshed.firstOrNull { it.id == call.id } ?: call.copy(verification = report)
    }

    fun confirmCallVerifiedByUser(ctx: Context, callId: String): List<PhoneSecretaryCallSummaryDto> {
        synchronized(pendingLock) {
            val map = loadVerificationMapUnsafe(ctx)
            val prev = map[callId]
            val confirmed = (prev ?: CallVerificationReportDto()).copy(
                status = CallNoteVerificationEngine.STATUS_USER_CONFIRMED,
                confidence_score = 100,
                verification_summary = "Von Alina persönlich geprüft und bestätigt.",
                discrepancies = prev?.discrepancies.orEmpty().map { it.copy(resolved_by_correction = true) },
                checked_at_iso = Instant.now().toString()
            )
            map[callId] = confirmed
            saveVerificationMapUnsafe(ctx, map)
        }
        return loadCachedRecentCalls(ctx)
    }

    fun updateCallNoteByUser(
        ctx: Context,
        callId: String,
        summary: String,
        actionRequired: String,
        rahmenbedingungen: List<String>
    ): List<PhoneSecretaryCallSummaryDto> {
        val cleanSum = CallNoteVerificationEngine.sanitizeCleanText(summary)
        val cleanAct = CallNoteVerificationEngine.sanitizeCleanText(actionRequired)
        val cleanRahmen = rahmenbedingungen.map { CallNoteVerificationEngine.sanitizeCleanText(it) }.filter { it.isNotBlank() }
        synchronized(pendingLock) {
            val edits = loadNoteEditsMapUnsafe(ctx)
            edits[callId] = LocalNoteEdit(
                summary = cleanSum,
                actionRequired = cleanAct,
                rahmenbedingungen = cleanRahmen
            )
            saveNoteEditsMapUnsafe(ctx, edits)

            val map = loadVerificationMapUnsafe(ctx)
            val prev = map[callId]
            map[callId] = (prev ?: CallVerificationReportDto()).copy(
                status = CallNoteVerificationEngine.STATUS_USER_CONFIRMED,
                confidence_score = 100,
                verification_summary = "Von Alina manuell angepasst und bestätigt.",
                corrected_summary = cleanSum,
                corrected_action_required = cleanAct,
                corrected_rahmenbedingungen = cleanRahmen,
                discrepancies = prev?.discrepancies.orEmpty().map { it.copy(resolved_by_correction = true) },
                checked_at_iso = Instant.now().toString()
            )
            saveVerificationMapUnsafe(ctx, map)
        }
        return loadCachedRecentCalls(ctx)
    }

    private fun loadDirtyWhitelistMap(ctx: Context): MutableMap<String, Boolean> {
        val raw = prefs(ctx).getString(KEY_DIRTY_WHITELIST_MAP_JSON, null)
        if (raw.isNullOrBlank()) return mutableMapOf()
        return try {
            val t = object : TypeToken<MutableMap<String, Boolean>>() {}.type
            gson.fromJson<MutableMap<String, Boolean>>(raw, t) ?: mutableMapOf()
        } catch (_: Exception) {
            mutableMapOf()
        }
    }

    private fun saveDirtyWhitelistMap(ctx: Context, map: Map<String, Boolean>) {
        if (map.isEmpty()) {
            prefs(ctx).edit().remove(KEY_DIRTY_WHITELIST_MAP_JSON).apply()
        } else {
            prefs(ctx).edit().putString(KEY_DIRTY_WHITELIST_MAP_JSON, gson.toJson(map)).apply()
        }
    }

    internal fun matchWhitelistedContactInList(
        contacts: List<CachedWhitelistContact>,
        incomingNumber: String?
    ): CachedWhitelistContact? {
        val norm = normalizePhoneDigits(incomingNumber)
        val suffix = extractMatchSuffix(incomingNumber)
        if (norm.isEmpty() && suffix.isEmpty()) return null

        return contacts.firstOrNull { c ->
            if (!c.whitelisted) return@firstOrNull false
            val cNorm = c.normalizedPhone.ifEmpty { normalizePhoneDigits(c.phone) }
            val cSuffix = c.matchSuffix.ifEmpty { extractMatchSuffix(c.phone) }
            (cNorm.isNotEmpty() && cNorm == norm) ||
                (cSuffix.length >= 7 && suffix.length >= 7 && cSuffix == suffix)
        }
    }

    fun matchIncomingWhitelistedCaller(ctx: Context, incomingNumber: String?): CachedWhitelistContact? {
        if (!isEnabled(ctx)) return null
        return matchWhitelistedContactInList(loadCachedContacts(ctx), incomingNumber)
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

        // 0. Flush any unsynced local config or whitelist overrides first so local "Sofort" / switches never get overwritten
        if (p.getBoolean(KEY_DIRTY_CONFIG, false)) {
            try {
                val pushRes = api.updatePhoneConciergeConfig(
                    PhoneConciergeConfigUpdateBody(
                        enabled = isEnabled(ctx),
                        ring_delay_seconds = getRingDelaySeconds(ctx)
                    )
                )
                if (pushRes.success) {
                    p.edit().putBoolean(KEY_DIRTY_CONFIG, false).apply()
                }
            } catch (_: Exception) { }
        }

        val dirtyMap = loadDirtyWhitelistMap(ctx)
        if (dirtyMap.isNotEmpty()) {
            val syncedDirtyIds = mutableListOf<String>()
            for ((cid, flag) in dirtyMap) {
                try {
                    val wRes = api.updateContactCallWhitelist(cid, PhoneWhitelistToggleBody(funkira_call_whitelist = flag))
                    if (wRes.success) {
                        syncedDirtyIds.add(cid)
                    }
                } catch (_: Exception) { }
            }
            if (syncedDirtyIds.isNotEmpty()) {
                syncedDirtyIds.forEach { dirtyMap.remove(it) }
                saveDirtyWhitelistMap(ctx, dirtyMap)
            }
        }

        // 1. Sync global Phone-Concierge config from Portal
        var whitelistedIdsFromConfig: Set<String>? = null
        try {
            val cfgRes = api.getPhoneConciergeConfig()
            val cfg = cfgRes.data
            if (cfgRes.success && cfg != null) {
                if (!p.getBoolean(KEY_DIRTY_CONFIG, false)) {
                    setEnabledLocal(ctx, cfg.enabled)
                    setRingDelayLocal(ctx, cfg.ring_delay_seconds)
                }
                if (!cfg.gateway_phone.isNullOrBlank()) {
                    setGatewayPhoneLocal(ctx, cfg.gateway_phone)
                }
                saveCachedRecentCalls(ctx, cfg.recent_calls.orEmpty())
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
                    val dirtyOverride = dirtyMap[dto.id]
                    val isWhitelisted = when {
                        dirtyOverride != null -> dirtyOverride
                        dto.funkira_call_whitelist == true -> true
                        whitelistedIdsFromConfig != null -> whitelistedIdsFromConfig.contains(dto.id)
                        localIds.contains(dto.id) -> true
                        isPapaDefault -> {
                            localIds.add(dto.id)
                            true
                        }
                        else -> false
                    }
                    if (isWhitelisted) localIds.add(dto.id) else localIds.remove(dto.id)
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
        prefs(ctx).edit().putBoolean(KEY_DIRTY_CONFIG, true).apply()
        ServiceLocator.ensureInitialized(ctx)
        if (ServiceLocator.getAuthToken().isNullOrBlank()) return
        try {
            val res = ServiceLocator.getOrganizerApi().updatePhoneConciergeConfig(
                PhoneConciergeConfigUpdateBody(
                    enabled = enabled,
                    ring_delay_seconds = ringDelaySeconds
                )
            )
            if (res.success) {
                prefs(ctx).edit().putBoolean(KEY_DIRTY_CONFIG, false).apply()
            }
        } catch (_: Exception) { }
    }

    suspend fun toggleContactWhitelist(ctx: Context, contactId: String, whitelisted: Boolean): List<CachedWhitelistContact> {
        val p = prefs(ctx)
        val localIds = p.getStringSet(KEY_LOCAL_WHITELIST_IDS, emptySet())?.toMutableSet() ?: mutableSetOf()
        if (whitelisted) localIds.add(contactId) else localIds.remove(contactId)
        p.edit().putStringSet(KEY_LOCAL_WHITELIST_IDS, localIds).apply()

        val dirtyMap = loadDirtyWhitelistMap(ctx)
        dirtyMap[contactId] = whitelisted
        saveDirtyWhitelistMap(ctx, dirtyMap)

        val updated = loadCachedContacts(ctx).map {
            if (it.id == contactId) it.copy(whitelisted = whitelisted) else it
        }
        saveCachedContacts(ctx, updated)

        ServiceLocator.ensureInitialized(ctx)
        if (!ServiceLocator.getAuthToken().isNullOrBlank()) {
            try {
                val res = ServiceLocator.getOrganizerApi().updateContactCallWhitelist(
                    id = contactId,
                    body = PhoneWhitelistToggleBody(funkira_call_whitelist = whitelisted)
                )
                if (res.success) {
                    val afterMap = loadDirtyWhitelistMap(ctx)
                    afterMap.remove(contactId)
                    saveDirtyWhitelistMap(ctx, afterMap)
                }
            } catch (_: Exception) { }
        }
        return updated
    }

    internal fun buildEnrichedServerSummary(note: PendingPhoneSecretaryNote): String {
        val base = CallNoteVerificationEngine.sanitizeCleanText(note.summary)
        val rahmen = note.rahmenbedingungen.map { CallNoteVerificationEngine.sanitizeCleanText(it) }.filter { it.isNotBlank() }
        val action = CallNoteVerificationEngine.sanitizeCleanText(note.actionRequired)
        val parts = mutableListOf<String>()
        if (base.isNotBlank()) parts.add(base)
        if (rahmen.isNotEmpty()) parts.add("Eckdaten: ${rahmen.joinToString(", ")}")
        if (action.isNotBlank() && !base.contains(action, ignoreCase = true)) {
            parts.add("Zu tun: $action")
        }
        val combined = parts.joinToString(" | ").ifBlank { "Anruf entgegengenommen." }
        return if (combined.length > 960) combined.take(957) + "..." else combined
    }

    private fun saveLocalCallFromNote(ctx: Context, note: PendingPhoneSecretaryNote) {
        val displayLabel = if (note.greetingName.isNotBlank() && !note.greetingName.equals(note.callerName, ignoreCase = true)) {
            "${note.greetingName} (${note.callerName})"
        } else {
            note.callerName
        }
        val defaultAction = if (note.callbackRequested) {
            "${note.greetingName.ifBlank { note.callerName }} zurückrufen."
        } else {
            "Zur Kenntnis nehmen."
        }
        val entry = PhoneSecretaryCallSummaryDto(
            id = note.callId,
            client_call_id = note.callId,
            caller_name = displayLabel,
            created_at_label = formatLocalCallTimeLabel(note.createdAtIso),
            duration_seconds = note.durationSeconds,
            summary = CallNoteVerificationEngine.sanitizeCleanText(note.summary),
            rahmenbedingungen = note.rahmenbedingungen.map { CallNoteVerificationEngine.sanitizeCleanText(it) }.filter { it.isNotBlank() },
            action_required = CallNoteVerificationEngine.sanitizeCleanText(note.actionRequired).ifBlank { defaultAction },
            urgency = normalizeUrgency(note.urgency),
            callback_requested = note.callbackRequested,
            transcript = note.transcript,
            handled_by = "android_secretary",
            verification = note.verification
        )
        synchronized(pendingLock) {
            val locals = loadLocalCallsUnsafe(ctx)
            locals.removeAll { it.id == entry.id }
            locals.add(0, entry)
            saveLocalCallsUnsafe(ctx, locals.take(25))
            note.verification?.let { rep ->
                val vMap = loadVerificationMapUnsafe(ctx)
                vMap[entry.id] = rep
                saveVerificationMapUnsafe(ctx, vMap)
            }
        }
        loadCachedRecentCalls(ctx)
    }

    suspend fun persistOrQueueSecretaryCompletion(ctx: Context, note: PendingPhoneSecretaryNote): Boolean {
        ServiceLocator.ensureInitialized(ctx)
        saveLocalCallFromNote(ctx, note)
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
        val cleanSummary = CallNoteVerificationEngine.sanitizeCleanText(note.summary)
            .ifBlank { "Anruf entgegengenommen." }
            .take(1950)
        val cleanRahmen = note.rahmenbedingungen
            .map { CallNoteVerificationEngine.sanitizeCleanText(it).take(280) }
            .filter { it.isNotBlank() }
            .take(30)
        val cleanAction = CallNoteVerificationEngine.sanitizeCleanText(note.actionRequired)
            .ifBlank {
                if (note.callbackRequested) "${note.greetingName.ifBlank { note.callerName }} zurückrufen." else "Zur Kenntnis nehmen."
            }
            .take(480)
        val cleanTranscript = note.transcript
            .map { it.trim().take(1950) }
            .filter { it.isNotBlank() }
            .take(120)
        val serverSummaryFallback = buildEnrichedServerSummary(note)
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
                    summary = cleanSummary,
                    rahmenbedingungen = cleanRahmen,
                    action_required = cleanAction,
                    urgency = cleanUrgency,
                    callback_requested = note.callbackRequested,
                    duration_seconds = note.durationSeconds.coerceAtLeast(0),
                    transcript = cleanTranscript,
                    verification = note.verification
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
                        addProperty("title", "📞 Anruf von $displayLabel: $serverSummaryFallback".take(235))
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
