package de.meinseelenfunke.app.util

import android.content.Context
import com.google.gson.Gson
import com.google.gson.JsonArray
import com.google.gson.JsonElement
import com.google.gson.JsonObject
import de.meinseelenfunke.app.data.api.AgDirectApi
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import kotlinx.coroutines.withTimeoutOrNull
import java.util.Calendar
import java.util.Locale

data class FalconPlaylistSemanticProfile(
    val playlistId: String,
    val playlistName: String,
    val trackCount: Int,
    val isFavoritesPool: Boolean,
    val isSingleSongPlaylist: Boolean,
    val primaryCluster: String,
    val clusterScores: Map<String, Int>,
    val seedTrackSummary: String?
)

data class FalconMusicMatchResult(
    val tracks: List<MusicTrackDto>,
    val playlistId: String,
    val playlistName: String?,
    val shuffle: Boolean,
    val usedFallbackFavorites: Boolean,
    val matchedCluster: String? = null,
    val isSingleSongSeedMatch: Boolean = false,
    val singleSongExtendedCount: Int = 0
)

data class FalconResolvedDestination(
    val target: NavigationBridge.NavTarget,
    val organizerSubTab: Int = 0,
    val openReceiptScanner: Boolean = false,
    val label: String
)

enum class FalconInactivityStage {
    ACTIVE,
    CHECK_SENT_WAITING_QUESTION,
    CHECK_SENT_WAITING_USER_REPLY,
    FAREWELL_CLOSING,
    DISCONNECTED
}

enum class FalconInactivityAction {
    NONE,
    SEND_STAGE1_CHECK,
    SEND_STAGE2_FAREWELL,
    FORCE_DISCONNECT
}

class FalconInactivityTracker(
    val stage1SilenceMs: Long = STAGE1_SILENCE_MS,
    val stage1QuestionMaxWaitMs: Long = STAGE1_QUESTION_MAX_WAIT_MS,
    val stage2FollowupSilenceMs: Long = STAGE2_FOLLOWUP_SILENCE_MS,
    val farewellPostSpeechBufferMs: Long = FAREWELL_POST_SPEECH_BUFFER_MS,
    val farewellNoSpeechFallbackMs: Long = FAREWELL_NO_SPEECH_FALLBACK_MS,
    val farewellSafetyMaxSpeechMs: Long = FAREWELL_SAFETY_MAX_SPEECH_MS
) {
    companion object {
        const val STAGE1_SILENCE_MS = 60_000L
        const val STAGE1_QUESTION_MAX_WAIT_MS = 12_000L
        const val STAGE2_FOLLOWUP_SILENCE_MS = 25_000L
        const val FAREWELL_POST_SPEECH_BUFFER_MS = 1_400L
        const val FAREWELL_NO_SPEECH_FALLBACK_MS = 12_000L
        const val FAREWELL_SAFETY_MAX_SPEECH_MS = 90_000L
    }

    @get:Synchronized
    var stage: FalconInactivityStage = FalconInactivityStage.ACTIVE
        private set

    @get:Synchronized
    var lastInteractionMs: Long = 0L
        private set

    @get:Synchronized
    var inactivityCheckSent: Boolean = false
        private set

    @get:Synchronized
    var inactivityFollowupStartedMs: Long = 0L
        private set

    private var initialized: Boolean = false
    private var stage1SpeechObserved: Boolean = false
    private var stage1QuestionFinishedMs: Long = 0L
    private var farewellSentAtMs: Long = 0L
    private var farewellSpeechObserved: Boolean = false
    private var farewellSpeechEndedMs: Long = 0L

    @Synchronized
    fun onSessionStarted(nowMs: Long) {
        initialized = true
        stage = FalconInactivityStage.ACTIVE
        lastInteractionMs = nowMs
        inactivityCheckSent = false
        inactivityFollowupStartedMs = 0L
        stage1SpeechObserved = false
        stage1QuestionFinishedMs = 0L
        farewellSentAtMs = 0L
        farewellSpeechObserved = false
        farewellSpeechEndedMs = 0L
    }

    @Synchronized
    fun onUserSpeech(nowMs: Long) {
        if (stage == FalconInactivityStage.DISCONNECTED) {
            return
        }
        stage = FalconInactivityStage.ACTIVE
        inactivityCheckSent = false
        inactivityFollowupStartedMs = 0L
        stage1SpeechObserved = false
        stage1QuestionFinishedMs = 0L
        farewellSentAtMs = 0L
        farewellSpeechObserved = false
        farewellSpeechEndedMs = 0L
        lastInteractionMs = nowMs
    }

    @Synchronized
    fun onAiActivity(nowMs: Long) {
        when (stage) {
            FalconInactivityStage.ACTIVE -> {
                lastInteractionMs = nowMs
            }
            FalconInactivityStage.CHECK_SENT_WAITING_QUESTION -> {
                stage1SpeechObserved = true
            }
            FalconInactivityStage.CHECK_SENT_WAITING_USER_REPLY -> {
                stage1QuestionFinishedMs = nowMs
            }
            FalconInactivityStage.FAREWELL_CLOSING -> {
                farewellSpeechObserved = true
                farewellSpeechEndedMs = 0L
            }
            FalconInactivityStage.DISCONNECTED -> {}
        }
    }

    @Synchronized
    fun onAiSpeechFinished(nowMs: Long) {
        when (stage) {
            FalconInactivityStage.ACTIVE -> {
                lastInteractionMs = nowMs
            }
            FalconInactivityStage.CHECK_SENT_WAITING_QUESTION -> {
                stage = FalconInactivityStage.CHECK_SENT_WAITING_USER_REPLY
                stage1QuestionFinishedMs = nowMs
            }
            FalconInactivityStage.CHECK_SENT_WAITING_USER_REPLY -> {
                if (stage1QuestionFinishedMs < nowMs) {
                    stage1QuestionFinishedMs = nowMs
                }
            }
            FalconInactivityStage.FAREWELL_CLOSING -> {
                if (farewellSpeechObserved && farewellSpeechEndedMs == 0L) {
                    farewellSpeechEndedMs = nowMs
                }
            }
            FalconInactivityStage.DISCONNECTED -> {}
        }
    }

    @Synchronized
    fun onToolActivity(nowMs: Long) {
        if (stage == FalconInactivityStage.ACTIVE) {
            lastInteractionMs = nowMs
        }
    }

    @Synchronized
    fun evaluate(
        nowMs: Long,
        isAiSpeaking: Boolean,
        activeToolCalls: Int = 0,
        isUserSpeaking: Boolean = false
    ): FalconInactivityAction {
        if (!initialized) {
            initialized = true
            lastInteractionMs = nowMs
        }
        if (stage != FalconInactivityStage.DISCONNECTED && isUserSpeaking) {
            onUserSpeech(nowMs)
            return FalconInactivityAction.NONE
        }
        return when (stage) {
            FalconInactivityStage.DISCONNECTED -> FalconInactivityAction.NONE

            FalconInactivityStage.ACTIVE -> {
                if (isAiSpeaking || activeToolCalls > 0) {
                    lastInteractionMs = nowMs
                    FalconInactivityAction.NONE
                } else if (nowMs - lastInteractionMs >= stage1SilenceMs) {
                    stage = FalconInactivityStage.CHECK_SENT_WAITING_QUESTION
                    inactivityCheckSent = true
                    inactivityFollowupStartedMs = nowMs
                    stage1SpeechObserved = false
                    stage1QuestionFinishedMs = 0L
                    FalconInactivityAction.SEND_STAGE1_CHECK
                } else {
                    FalconInactivityAction.NONE
                }
            }

            FalconInactivityStage.CHECK_SENT_WAITING_QUESTION -> {
                if (isAiSpeaking || activeToolCalls > 0) {
                    stage1SpeechObserved = true
                    FalconInactivityAction.NONE
                } else if (stage1SpeechObserved || (nowMs - inactivityFollowupStartedMs >= stage1QuestionMaxWaitMs)) {
                    stage = FalconInactivityStage.CHECK_SENT_WAITING_USER_REPLY
                    stage1QuestionFinishedMs = nowMs
                    FalconInactivityAction.NONE
                } else {
                    FalconInactivityAction.NONE
                }
            }

            FalconInactivityStage.CHECK_SENT_WAITING_USER_REPLY -> {
                // Never interrupt an active AI sentence or active tool call!
                if (isAiSpeaking || activeToolCalls > 0) {
                    stage1QuestionFinishedMs = nowMs
                    FalconInactivityAction.NONE
                } else if (nowMs - stage1QuestionFinishedMs >= stage2FollowupSilenceMs) {
                    stage = FalconInactivityStage.FAREWELL_CLOSING
                    farewellSentAtMs = nowMs
                    farewellSpeechObserved = false
                    farewellSpeechEndedMs = 0L
                    FalconInactivityAction.SEND_STAGE2_FAREWELL
                } else {
                    FalconInactivityAction.NONE
                }
            }

            FalconInactivityStage.FAREWELL_CLOSING -> {
                if (isAiSpeaking || activeToolCalls > 0) {
                    farewellSpeechObserved = true
                    farewellSpeechEndedMs = 0L
                    // Only fail-safe disconnect if audio is stuck for > 35s; NEVER cut off a normal sentence!
                    return if (nowMs - farewellSentAtMs >= farewellSafetyMaxSpeechMs) {
                        stage = FalconInactivityStage.DISCONNECTED
                        FalconInactivityAction.FORCE_DISCONNECT
                    } else {
                        FalconInactivityAction.NONE
                    }
                } else if (farewellSpeechObserved && farewellSpeechEndedMs == 0L) {
                    farewellSpeechEndedMs = nowMs
                }

                val finishedSpeechWithBuffer = farewellSpeechObserved &&
                    farewellSpeechEndedMs > 0L &&
                    (nowMs - farewellSpeechEndedMs >= farewellPostSpeechBufferMs)
                val noSpeechFallbackReached = !farewellSpeechObserved &&
                    (nowMs - farewellSentAtMs >= farewellNoSpeechFallbackMs)
                val safetyMaxReached = (nowMs - farewellSentAtMs >= farewellSafetyMaxSpeechMs)

                if (finishedSpeechWithBuffer || noSpeechFallbackReached || safetyMaxReached) {
                    stage = FalconInactivityStage.DISCONNECTED
                    FalconInactivityAction.FORCE_DISCONNECT
                } else {
                    FalconInactivityAction.NONE
                }
            }
        }
    }
}

object FalconClientTools {
    private val gson = Gson()

    const val STAGE1_INACTIVITY_PROMPT =
        "[SYSTEM-INAKTIVITÄT 60 SEKUNDEN: Alina hat seit 60 Sekunden nichts gesagt. Frage sie jetzt in 1 kurzen, freundlichen Satz auf Deutsch ('Bist du noch da?'), ob sie gerade noch etwas braucht oder ob du die Verbindung schließen sollst. Rufe noch KEIN end_call auf, sondern warte ihre Antwort ab.]"

    const val BEDTIME_2200_PROMPT =
        "[SYSTEM-ROUTINE-ERINNERUNG 22:00 UHR (GILT AUCH AM WOCHENENDE): Es ist jetzt 22:00 Uhr (Bettfertig & Runterfahren). Erinnere Alina jetzt herzlich-bestimmt in 1 bis 2 kurzen Sätzen auf Deutsch daran, langsam runterzufahren und sich bettfertig zu machen, damit um 22:30 Uhr pünktlich Nachtruhe ist.]"

    const val BEDTIME_2230_PROMPT =
        "[SYSTEM-SCHLAFENSZEIT-ERINNERUNG 22:30 UHR (GESUNDHEITS-PFLICHT AUCH AM WOCHENENDE): Es ist jetzt Nachtruhe (ab 22:30 Uhr). Erinnere Alina jetzt freundlich, aber bestimmt in 1 bis 2 kurzen Sätzen auf Deutsch daran, das Gespräch abzuschließen und schlafen zu gehen!]"

    const val STAGE2_AUTO_END_PROMPT =
        "[SYSTEM-AUTO-ENDE: Keine Antwort erhalten. Verabschiede dich jetzt in 1 kurzen Satz freundlich auf Deutsch (z. B. dass du die Verbindung schließt und auf 'Hey Funkira' wartest) und rufe ERST NACH deinem gesprochenen Satz end_call auf.]"

    val SPORT_KEYWORDS = listOf(
        "sport", "workout", "fitness", "gym", "power",
        "training", "energy", "cardio", "laufen", "motivation", "hype", "pump"
    )

    val VALID_EMOTIONS = setOf(
        "idle", "listening", "thinking", "speaking", "happy", "proud", "focused", "sweet", "excited"
    )

    val DECLARED_WS_LOCAL_TOOL_NAMES = setOf(
        "falcon_music_control",
        "falcon_camera_control",
        "falcon_smarthome_control",
        "falcon_send_to_antigravity",
        "falcon_stage_to_package",
        "falcon_list_draft_packages",
        "falcon_release_package",
        "falcon_get_recent_memory",
        "falcon_resolve_antigravity_blockade",
        "falcon_check_antigravity_status",
        "falcon_app_navigate"
    )

    val LOCAL_TOOL_NAMES = DECLARED_WS_LOCAL_TOOL_NAMES + "falcon_set_emotion"

    const val DEVICE_REFLEX_COALESCE_WINDOW_MS = 4_500L

    private data class ReflexInFlightEntry(
        val startedAtMs: Long,
        val deferred: kotlinx.coroutines.CompletableDeferred<JsonObject>
    )

    private val reflexCacheLock = Any()
    private val recentDeviceReflexCache = LinkedHashMap<String, ReflexInFlightEntry>()

    fun clearDeviceReflexCacheForTests() {
        synchronized(reflexCacheLock) {
            recentDeviceReflexCache.clear()
        }
    }

    fun buildLiveThinkingConfig(
        modelName: String,
        includeThinkingConfig: Boolean = true,
        isDirectGoogleAiStudio: Boolean = false
    ): JsonObject? {
        if (!includeThinkingConfig) return null
        val lower = modelName.trim().lowercase(Locale.ROOT)
        return when {
            lower.contains("2.5") -> JsonObject().apply {
                addProperty("thinkingBudget", 0)
            }
            isDirectGoogleAiStudio &&
                !lower.startsWith("projects/") &&
                (lower.contains("3.1") || lower.contains("gemini-3")) -> JsonObject().apply {
                addProperty("thinkingLevel", "minimal")
            }
            else -> null
        }
    }

    private fun normalizeReflexText(raw: String): String {
        return raw.lowercase(Locale.GERMAN)
            .replace("ä", "ae")
            .replace("ö", "oe")
            .replace("ü", "ue")
            .replace("ß", "ss")
            .replace(Regex("""[,.;:!\-_/()\[\]"']"""), " ")
            .replace(Regex("""\s+"""), " ")
            .trim()
    }

    fun canonicalDeviceReflexKey(toolName: String, args: JsonObject): String? {
        return when (toolName) {
            "falcon_camera_control" -> {
                val act = args.optString("action").ifBlank { "enable_vision" }.trim().lowercase(Locale.ROOT)
                val lens = args.optString("lens").trim().lowercase(Locale.ROOT)
                when (act) {
                    "disable_vision", "off", "stop" -> "cam:off"
                    "switch_camera", "flip" -> "cam:switch:${lens.ifBlank { "toggle" }}"
                    "take_photo", "photo", "snapshot" -> "cam:photo:${lens.ifBlank { "default" }}"
                    else -> "cam:on:${lens.ifBlank { "back" }}"
                }
            }

            "falcon_smarthome_control" -> {
                val rawAct = args.optString("action").trim().lowercase(Locale.ROOT)
                val rawTarget = args.optString("device_name").trim()
                    .ifBlank { args.optString("target").trim() }
                val rawPos = args.optInt("position_percent", args.optInt("position", -1))

                if (rawAct in setOf("", "toggle", "plug_toggle", "group_toggle", "umschalten", "wechseln", "list", "status", "devices", "list_devices")) {
                    return null
                }

                val intent = when (rawAct) {
                    "on", "ein", "einschalten", "turn_on", "plug_on", "all_on", "alle_an", "turn_all_on", "group_on",
                    "open", "up", "hoch", "auf", "oeffnen", "öffnen", "shutter_open", "group_open" -> "on_open"
                    "off", "aus", "ausschalten", "turn_off", "plug_off", "all_off", "alle_aus", "turn_all_off", "group_off",
                    "close", "down", "runter", "zu", "schliessen", "schließen", "shutter_close", "group_close" -> "off_close"
                    "stop", "halt", "stopp", "shutter_stop", "group_stop" -> "stop"
                    "my", "fav", "favorit", "shutter_my", "group_my" -> "my"
                    "position", "set_position", "setclosure", "shutter_position", "group_position" ->
                        "pos:${rawPos.coerceIn(0, 100)}"
                    else -> return null
                }

                val normTarget = normalizeReflexText(rawTarget)
                    .split(" ")
                    .filterNot {
                        it in setOf(
                            "alle", "beide", "beiden", "die", "den", "das", "der", "meine",
                            "bitte", "mal", "kurz", "rollladen", "rolllaeden", "rolladen", "rollaeden", "gruppe"
                        )
                    }
                    .joinToString(" ")
                    .trim()

                val canonicalTarget = when {
                    normTarget.contains("lichterkette") -> when {
                        normTarget.contains("spiegel") && !normTarget.contains("fussleiste") -> "lichterkettespiegel"
                        normTarget.contains("fussleiste") && !normTarget.contains("spiegel") -> "lichterkettefussleiste"
                        else -> "all_lichterketten"
                    }
                    normTarget.contains("spiegel") -> "lichterkettespiegel"
                    normTarget.contains("fussleiste") -> "lichterkettefussleiste"
                    normTarget.contains("packtisch") -> "packtisch"
                    normTarget.isEmpty() || normTarget in setOf("steckdosen", "steckdose", "lichter", "lampen", "geraete", "alles", "ueberall", "komplett") -> {
                        if (rawAct.contains("shutter") || rawTarget.lowercase(Locale.ROOT).contains("roll")) {
                            "all_shutters"
                        } else {
                            "all_plugs"
                        }
                    }
                    else -> normTarget.replace(" ", "")
                }

                "sh:$intent:$canonicalTarget"
            }

            "falcon_release_package" -> {
                val pkgUuid = args.optString("package_uuid").trim().lowercase(Locale.ROOT)
                "pkg:release:${pkgUuid.ifBlank { "latest" }}"
            }

            else -> null
        }
    }

    private fun evictOppositeReflexKeysLocked(canonicalKey: String) {
        when {
            canonicalKey == "cam:off" -> {
                val toRemove = recentDeviceReflexCache.keys.filter { it.startsWith("cam:on:") }
                toRemove.forEach { recentDeviceReflexCache.remove(it) }
            }
            canonicalKey.startsWith("cam:on:") -> {
                recentDeviceReflexCache.remove("cam:off")
            }
            canonicalKey.startsWith("sh:on_open:") -> {
                val target = canonicalKey.removePrefix("sh:on_open:")
                recentDeviceReflexCache.remove("sh:off_close:$target")
                recentDeviceReflexCache.remove("sh:stop:$target")
            }
            canonicalKey.startsWith("sh:off_close:") -> {
                val target = canonicalKey.removePrefix("sh:off_close:")
                recentDeviceReflexCache.remove("sh:on_open:$target")
                recentDeviceReflexCache.remove("sh:stop:$target")
            }
            canonicalKey.startsWith("sh:stop:") -> {
                val target = canonicalKey.removePrefix("sh:stop:")
                recentDeviceReflexCache.remove("sh:on_open:$target")
                recentDeviceReflexCache.remove("sh:off_close:$target")
            }
        }
    }

    fun matchDeterministicDeviceReflex(utterance: String): Pair<String, JsonObject>? {
        val raw = utterance.trim()
        if (raw.isEmpty() || raw.length > 72 || raw.contains('?')) return null

        val norm = normalizeReflexText(raw)
        if (norm.isEmpty()) return null
        if (norm.any { it.isDigit() }) return null

        val rawTokens = norm.split(" ").filter { it.isNotEmpty() }
        val blockedWords = setOf(
            "nicht", "kein", "keine", "keinen", "keiner", "niemals", "nie", "ohne",
            "warte", "spaeter", "morgen", "gestern", "warum", "wieso", "weshalb", "wann",
            "welche", "welcher", "welches", "wieviel", "status", "konzept", "antigravity",
            "ticket", "paket", "sammelpaket", "fehler", "problem", "erklaer", "erklaere",
            "pruef", "pruefe", "check", "wenn", "falls", "obwohl", "oder", "aber", "und"
        )
        if (rawTokens.any { it in blockedWords }) return null

        val fillerPrefixAndMid = setOf(
            "hey", "hi", "hallo", "funkira", "falcone", "falcon", "bitte", "mal",
            "kurz", "schnell", "sofort", "direkt", "jetzt", "eben", "kannst", "koenntest", "du", "mir"
        )
        val cleaned = rawTokens.filterNot { it in fillerPrefixAndMid }.joinToString(" ").trim()
        if (cleaned.isEmpty()) return null

        // 1. Camera deterministic imperatives
        Regex("""^(?:(?:mach|mache|schalt|schalte) )?(?:die )?kamera (?:wieder )?(?:aus|ausschalten|stopp|stoppen|beenden)$""").matchEntire(cleaned)?.let {
            return "falcon_camera_control" to JsonObject().apply { addProperty("action", "disable_vision") }
        }
        Regex("""^schliess(?:e)? (?:die )?kamera$""").matchEntire(cleaned)?.let {
            return "falcon_camera_control" to JsonObject().apply { addProperty("action", "disable_vision") }
        }
        Regex("""^(?:(?:wechsel|wechsle|dreh|drehe) (?:die )?kamera(?: um)?|kamera (?:wechseln|umdrehen))$""").matchEntire(cleaned)?.let {
            return "falcon_camera_control" to JsonObject().apply { addProperty("action", "switch_camera") }
        }
        Regex("""^(?:(?:mach|mache|schalt|schalte|wechsel auf) )?(?:die )?(?:frontkamera|selfiekamera|vorderkamera)(?: an| ein)?$""").matchEntire(cleaned)?.let {
            return "falcon_camera_control" to JsonObject().apply {
                addProperty("action", "switch_camera")
                addProperty("lens", "front")
            }
        }
        Regex("""^(?:(?:mach|mache|schalt|schalte|wechsel auf) )?(?:die )?(?:rueckkamera|hauptkamera)(?: an| ein)?$""").matchEntire(cleaned)?.let {
            return "falcon_camera_control" to JsonObject().apply {
                addProperty("action", "switch_camera")
                addProperty("lens", "back")
            }
        }
        Regex("""^(?:(?:mach|mache|schiess|schiesse|nimm) (?:ein )?(?:foto|bild)(?: auf)?|foto (?:machen|aufnehmen))$""").matchEntire(cleaned)?.let {
            return "falcon_camera_control" to JsonObject().apply { addProperty("action", "take_photo") }
        }
        Regex("""^(?:(?:mach|mache|schalt|schalte) )?(?:die )?kamera (?:an|ein|einschalten|starten|aktivieren)$""").matchEntire(cleaned)?.let {
            return "falcon_camera_control" to JsonObject().apply { addProperty("action", "enable_vision") }
        }
        Regex("""^oeffne (?:die )?kamera$""").matchEntire(cleaned)?.let {
            return "falcon_camera_control" to JsonObject().apply { addProperty("action", "enable_vision") }
        }

        // 2. SmartHome deterministic imperatives (idempotent states only)
        Regex("""^(?:(?:mach|mache|schalt|schalte) )?(?:(?:alle|beide|die) )?lichterketten (an|ein|einschalten|aus|ausschalten)$""").matchEntire(cleaned)?.let { m ->
            val turnOn = m.groupValues[1] in setOf("an", "ein", "einschalten")
            return "falcon_smarthome_control" to JsonObject().apply {
                addProperty("action", if (turnOn) "turn_on" else "turn_off")
                addProperty("device_name", "alle Lichterketten")
            }
        }

        Regex("""^(?:(?:mach|mache|schalt|schalte) )?(?:(?:die|das|den) )?(lichterkette spiegel|spiegel lichterkette|lichterkette fussleiste|fussleiste lichterkette|packtisch warm licht|packtisch licht|packtisch) (an|ein|einschalten|aus|ausschalten)$""").matchEntire(cleaned)?.let { m ->
            val rawDev = m.groupValues[1]
            val turnOn = m.groupValues[2] in setOf("an", "ein", "einschalten")
            val canonicalDev = when {
                rawDev.contains("spiegel") -> "Lichterkette Spiegel"
                rawDev.contains("fussleiste") -> "Lichterkette Fußleiste"
                else -> "Packtisch Warm Licht"
            }
            return "falcon_smarthome_control" to JsonObject().apply {
                addProperty("action", if (turnOn) "turn_on" else "turn_off")
                addProperty("device_name", canonicalDev)
            }
        }

        Regex("""^(?:(?:mach|mache|schalt|schalte) )?alle (?:steckdosen|lichter|lampen) (an|ein|einschalten|aus|ausschalten)$""").matchEntire(cleaned)?.let { m ->
            val turnOn = m.groupValues[1] in setOf("an", "ein", "einschalten")
            return "falcon_smarthome_control" to JsonObject().apply {
                addProperty("action", if (turnOn) "turn_all_on" else "turn_all_off")
                addProperty("device_name", "alle")
            }
        }

        Regex("""^(?:(?:mach|mache|fahr|fahre) )?(?:(?:den|die) )?(alle )?(?:rollladen|rolllaeden|rolladen|rollaeden)(?: (?:im|in der|am|an der))?(?: ([a-z]+))? (hoch|auf|oeffnen|runter|zu|schliessen|stopp|stop|halt)$""").matchEntire(cleaned)?.let { m ->
            val roomToken = m.groupValues[2].trim()
            val verb = m.groupValues[3]
            val action = when (verb) {
                "hoch", "auf", "oeffnen" -> "shutter_open"
                "runter", "zu", "schliessen" -> "shutter_close"
                else -> "shutter_stop"
            }
            val targetName = when {
                roomToken == "terrassentuer" || roomToken == "terasse" || roomToken == "terrasse" -> "Terrassentür"
                roomToken == "wohnzimmer" -> "Wohnzimmer"
                roomToken == "schlafzimmer" -> "Schlafzimmer"
                roomToken.isNotBlank() -> roomToken.replaceFirstChar { it.titlecase(Locale.GERMAN) }
                else -> "alle"
            }
            return "falcon_smarthome_control" to JsonObject().apply {
                addProperty("action", action)
                addProperty("device_name", targetName)
            }
        }

        return null
    }

    /**
     * Deterministic voice reflex for explicit Sammelpaket release imperatives
     * ("Sammelpaket absenden", "Schick das Paket an Antigravity", "Paket jetzt abschicken").
     * Strictly rejects negations, questions, and staging phrases ("ins Paket legen").
     */
    fun matchDeterministicPackageReleaseReflex(utterance: String): Pair<String, JsonObject>? {
        val raw = utterance.trim()
        if (raw.isEmpty() || raw.length > 96 || raw.contains('?')) return null
        val norm = normalizeReflexText(raw)
        if (norm.isEmpty()) return null

        val tokens = norm.split(" ").filter { it.isNotEmpty() }
        val forbidden = setOf(
            "nicht", "kein", "keine", "keinen", "niemals", "nie", "ohne", "warte", "spaeter",
            "ins", "in", "hinein", "dazu", "hinzufuegen", "ergaenzen", "aufnehmen", "erweitern",
            "loeschen", "verwerfen", "welche", "welches", "status", "warum", "wieso", "oder", "falls", "wenn"
        )
        if (tokens.any { it in forbidden }) return null

        val hasPackageNoun = tokens.any { it in setOf("sammelpaket", "paket") }
        if (!hasPackageNoun) return null

        val hasReleaseVerb = Regex(
            """\b(?:absenden|abschicken|rausschicken|wegsc?hicken|freigeben|uebermitteln|raus)\b"""
        ).containsMatchIn(norm) || Regex(
            """\b(?:schick|schicke|send|sende)\b.*\b(?:sammelpaket|paket)\b.*\b(?:ab|raus|los|antigravity)\b"""
        ).containsMatchIn(norm) || Regex(
            """\b(?:sammelpaket|paket)\b.*\ban antigravity\b.*\b(?:senden|schicken|uebermitteln)?"""
        ).containsMatchIn(norm)

        if (!hasReleaseVerb) return null

        val modeOverride = when {
            tokens.contains("direkt") -> "direct"
            tokens.contains("konzept") -> "concept"
            else -> null
        }
        return "falcon_release_package" to JsonObject().apply {
            if (modeOverride != null) addProperty("execution_mode", modeOverride)
        }
    }

    suspend fun executeDeviceToolWithReflexDedupe(
        context: Context,
        toolName: String,
        argsElement: JsonElement?,
        chatSessionId: String,
        onEmotionChanged: (String) -> Unit = {},
        onNavigate: (FalconResolvedDestination) -> Unit = {},
        nowMs: Long = System.currentTimeMillis(),
        smartHomeExecutorOverride: (suspend (action: String, ips: List<String>) -> Pair<Boolean, String>)? = null,
        smartHomeRelayExecutorOverride: (suspend (action: String, target: String, position: Int?) -> Pair<Boolean, String>)? = null
    ): JsonObject {
        val argsObj = if (argsElement != null && argsElement.isJsonObject) argsElement.asJsonObject else JsonObject()
        val key = canonicalDeviceReflexKey(toolName, argsObj)
            ?: return executeLocalFalconToolInternal(
                context = context,
                toolName = toolName,
                args = argsObj,
                chatSessionId = chatSessionId,
                onEmotionChanged = onEmotionChanged,
                onNavigate = onNavigate,
                agApiOverride = null,
                smartHomeExecutorOverride = smartHomeExecutorOverride,
                smartHomeRelayExecutorOverride = smartHomeRelayExecutorOverride
            )

        var existingDeferred: kotlinx.coroutines.CompletableDeferred<JsonObject>? = null
        val ownDeferred = kotlinx.coroutines.CompletableDeferred<JsonObject>()
        synchronized(reflexCacheLock) {
            val staleKeys = recentDeviceReflexCache.entries
                .filter { (nowMs - it.value.startedAtMs) > DEVICE_REFLEX_COALESCE_WINDOW_MS && it.value.deferred.isCompleted }
                .map { it.key }
            staleKeys.forEach { recentDeviceReflexCache.remove(it) }

            evictOppositeReflexKeysLocked(key)

            val existing = recentDeviceReflexCache[key]
            if (existing != null && (nowMs - existing.startedAtMs) <= DEVICE_REFLEX_COALESCE_WINDOW_MS) {
                existingDeferred = existing.deferred
            } else {
                recentDeviceReflexCache[key] = ReflexInFlightEntry(nowMs, ownDeferred)
            }
        }

        existingDeferred?.let { shared ->
            val cached = shared.await()
            return gson.fromJson(gson.toJson(cached), JsonObject::class.java).apply {
                addProperty("coalesced_fast_lane", true)
            }
        }

        return try {
            val fresh = executeLocalFalconToolInternal(
                context = context,
                toolName = toolName,
                args = argsObj,
                chatSessionId = chatSessionId,
                onEmotionChanged = onEmotionChanged,
                onNavigate = onNavigate,
                agApiOverride = null,
                smartHomeExecutorOverride = smartHomeExecutorOverride,
                smartHomeRelayExecutorOverride = smartHomeRelayExecutorOverride
            )
            val statusStr = fresh.optString("status")
            if (statusStr == "error" || statusStr == "not_found") {
                synchronized(reflexCacheLock) {
                    if (recentDeviceReflexCache[key]?.deferred === ownDeferred) {
                        recentDeviceReflexCache.remove(key)
                    }
                }
            }
            ownDeferred.complete(fresh)
            fresh
        } catch (t: Throwable) {
            synchronized(reflexCacheLock) {
                if (recentDeviceReflexCache[key]?.deferred === ownDeferred) {
                    recentDeviceReflexCache.remove(key)
                }
            }
            ownDeferred.completeExceptionally(t)
            throw t
        }
    }

    val SUPERSEDED_SERVER_LIVE_TOOLS = setOf(
        "ag_send_to_antigravity",
        "ag_check_antigravity_status",
        "ag_resolve_pending_blockade",
        "system_prepare_antigravity_handoff",
        "system_open_nav_item",
        "system_open_zentrum",
        "system_close_zentrum",
        "system_close_ui",
        "falcon_set_emotion",
        "smarthome_control",
        "smarthome_get_devices_and_groups",
        "smarthome_control_plug",
        "smarthome_list_plugs",
        "music_play",
        "music_pause",
        "management_music_play",
        "management_music_pause",
        "management_music_control"
    )

    const val POST_SPEECH_ECHO_TAIL_SPEAKER_MS = 450L
    const val POST_SPEECH_ECHO_TAIL_HEADSET_MS = 150L
    const val BARGE_IN_REQUIRED_SPEAKER_FRAMES = 3
    const val BARGE_IN_REQUIRED_HEADSET_FRAMES = 2

    fun isSustainedHumanBargeInFrame(
        rms: Double,
        peak: Int,
        isHeadsetRouted: Boolean
    ): Boolean {
        return if (isHeadsetRouted) {
            rms >= 2200.0 && peak >= 9000
        } else {
            rms >= 3800.0 && peak >= 14500
        }
    }

    fun requiredBargeInConsecutiveFrames(isHeadsetRouted: Boolean): Int {
        return if (isHeadsetRouted) BARGE_IN_REQUIRED_HEADSET_FRAMES else BARGE_IN_REQUIRED_SPEAKER_FRAMES
    }

    fun shouldSuppressMicForAiPlaybackOrEchoTail(
        isAiSpeakingNow: Boolean,
        nowUptimeMs: Long,
        lastAiSpeechActiveUptimeMs: Long,
        isHeadsetRouted: Boolean
    ): Boolean {
        if (isAiSpeakingNow) return true
        if (lastAiSpeechActiveUptimeMs <= 0L) return false
        val tailMs = if (isHeadsetRouted) POST_SPEECH_ECHO_TAIL_HEADSET_MS else POST_SPEECH_ECHO_TAIL_SPEAKER_MS
        val elapsed = nowUptimeMs - lastAiSpeechActiveUptimeMs
        return elapsed in 0L until tailMs
    }

    fun inferEmotionFromLiveTurn(transcript: String?, toolName: String? = null): String {
        val tName = toolName?.trim()?.lowercase(Locale.ROOT).orEmpty()
        if (tName.isNotEmpty()) {
            return when {
                tName == "falcon_music_control" || tName.startsWith("music_") -> "happy"
                tName.contains("antigravity") || tName.startsWith("ag_") || tName.startsWith("finance_") || tName.startsWith("order_") -> "focused"
                tName.startsWith("routine_") || tName.startsWith("task_") || tName.startsWith("calendar_") -> "proud"
                else -> "focused"
            }
        }
        val text = transcript?.trim()?.lowercase(Locale.GERMAN).orEmpty()
        if (text.isEmpty()) return "speaking"
        return when {
            text.contains("erledigt") || text.contains("ausgerollt") || text.contains("perfekt") ||
                text.contains("geschafft") || text.contains("klasse") || text.contains("stark") -> "proud"
            text.contains("gerne") || text.contains("freut mich") || text.contains("schlaf gut") ||
                text.contains("gute nacht") || text.contains("wunderbar") || text.contains("super") -> "happy"
            text.contains("moment") || text.contains("sekunde") || text.contains("prüfe") ||
                text.contains("pruefe") || text.contains("schaue") || text.contains("achtung") -> "focused"
            else -> "speaking"
        }
    }

    fun deduplicateFunctionCallsInBatch(calls: JsonArray): List<JsonObject> {
        val result = ArrayList<JsonObject>(calls.size())
        val seenSignatures = HashSet<String>()
        for (i in 0 until calls.size()) {
            val el = calls.get(i)
            if (el == null || !el.isJsonObject) continue
            val obj = el.asJsonObject
            val name = obj.get("name")?.takeIf { it.isJsonPrimitive }?.asString?.trim().orEmpty()
            if (name.isEmpty()) continue
            val argsCanonical = if (obj.has("args") && !obj.get("args").isJsonNull) {
                gson.toJson(obj.get("args"))
            } else {
                "{}"
            }
            val sig = "$name|$argsCanonical"
            if (seenSignatures.add(sig)) {
                result.add(obj)
            }
        }
        return result
    }

    fun buildBatchedToolResponsePacket(
        responses: List<Triple<String, String, JsonElement>>
    ): JsonObject {
        return JsonObject().apply {
            add("toolResponse", JsonObject().apply {
                add("functionResponses", JsonArray().apply {
                    for ((callId, fnName, resEl) in responses) {
                        add(JsonObject().apply {
                            add("response", resEl)
                            addProperty("id", callId)
                            addProperty("name", fnName)
                        })
                    }
                })
            })
        }
    }

    private val SMARTHOME_STOP_WORDS = setOf(
        "mach", "mache", "machen", "schalte", "schalt", "schalten",
        "bitte", "mal", "jetzt", "sofort", "kurz",
        "die", "der", "das", "den", "dem", "des", "ein", "eine", "einen", "einem",
        "meine", "mein", "meinen", "unser", "unsere",
        "an", "aus", "auf", "zu", "im", "in", "am", "bei", "beim", "unter", "neben",
        "alle", "alles", "beide", "beiden",
        "steckdose", "steckdosen", "smart", "smarthome", "home", "plug", "plugs", "geraet", "geraete", "lampe", "lampen", "leuchte"
    )

    fun isLocalFalconTool(name: String?): Boolean {
        return name != null && name in LOCAL_TOOL_NAMES
    }

    fun shouldEnterStandbyAfterTool(toolName: String?, action: String? = null): Boolean {
        val clean = toolName?.trim()?.lowercase(Locale.ROOT) ?: return false
        val cleanAct = action?.trim()?.lowercase(Locale.ROOT).orEmpty()
        if (cleanAct in setOf("analyze_playlist", "list_playlists", "inspect_playlist")) {
            return false
        }
        return clean == "falcon_music_control" ||
            clean.startsWith("music_") ||
            clean.startsWith("management_music_")
    }

    fun normalizeEmotion(raw: String?): String {
        val clean = raw?.trim()?.lowercase(Locale.ROOT).orEmpty()
        return when {
            clean in VALID_EMOTIONS -> clean
            clean == "neutral" || clean == "calm" -> "idle"
            clean == "excited" || clean == "smile" || clean == "joy" -> "happy"
            clean == "concentrated" || clean == "serious" -> "focused"
            else -> "idle"
        }
    }

    fun resolveFalcon3dMode(
        isConnecting: Boolean,
        isConnected: Boolean,
        isAiSpeaking: Boolean,
        isRecording: Boolean
    ): String {
        return when {
            isConnecting -> "connecting"
            isConnected && isAiSpeaking -> "speaking"
            isConnected && isRecording -> "listening"
            isConnected -> "thinking"
            else -> "idle"
        }
    }

    fun buildFalconSceneStateJson(
        mode: String,
        audioLevel: Float,
        emotion: String,
        isDark: Boolean,
        cameraActive: Boolean
    ): String {
        val clampedAudio = audioLevel.coerceIn(0f, 1f)
        val roundedAudio = kotlin.math.round(clampedAudio * 1000f) / 1000f
        val obj = JsonObject().apply {
            addProperty("mode", mode)
            addProperty("audioLevel", roundedAudio)
            addProperty("emotion", normalizeEmotion(emotion))
            addProperty("isDark", isDark)
            addProperty("cameraActive", cameraActive)
        }
        return gson.toJson(obj)
    }

    fun buildRealtimeVideoFramePacket(base64Jpeg: String): JsonObject {
        return JsonObject().apply {
            add("realtimeInput", JsonObject().apply {
                add("video", JsonObject().apply {
                    addProperty("mimeType", "image/jpeg")
                    addProperty("data", base64Jpeg)
                })
            })
        }
    }

    fun buildRealtimeTextPromptPacket(prompt: String): JsonObject {
        return JsonObject().apply {
            add("realtimeInput", JsonObject().apply {
                addProperty("text", prompt)
            })
        }
    }

    fun buildClientContentKickPacket(prompt: String): JsonObject {
        return JsonObject().apply {
            add("clientContent", JsonObject().apply {
                add("turns", JsonArray().apply {
                    add(JsonObject().apply {
                        addProperty("role", "user")
                        add("parts", JsonArray().apply {
                            add(JsonObject().apply {
                                addProperty("text", prompt)
                            })
                        })
                    })
                })
                addProperty("turnComplete", true)
            })
        }
    }

    fun buildSilentClientContextPacket(prompt: String): JsonObject {
        return JsonObject().apply {
            add("clientContent", JsonObject().apply {
                add("turns", JsonArray().apply {
                    add(JsonObject().apply {
                        addProperty("role", "user")
                        add("parts", JsonArray().apply {
                            add(JsonObject().apply {
                                addProperty("text", prompt)
                            })
                        })
                    })
                })
                addProperty("turnComplete", false)
            })
        }
    }

    fun hasExplicitConceptIntent(title: String?, body: String?): Boolean {
        val hay = "${title.orEmpty()} ${body.orEmpty()}".lowercase(Locale.GERMAN)
        return Regex("\\b(konzept|konzeptionieren|erst\\s+planen|nur\\s+konzept|als\\s+konzept)\\b").containsMatchIn(hay)
    }

    fun buildAttachedMediaSystemDirective(staged: PreparedAgAttachment?): String? {
        val pool = FalconCameraController.mediaPoolFlow.value
        if (staged == null && pool.isEmpty()) return null
        val names = (pool.map { it.fileName } + listOfNotNull(staged?.fileName)).distinct()
        val hasVideo = pool.any { it.mimeType.startsWith("video/", ignoreCase = true) }
        val desc = if (hasVideo) {
            "${names.size} Medien inkl. 20s-Video & Keyframes (${names.joinToString(", ")})"
        } else {
            "${names.size} Foto(s)/Screenshot(s) (${names.joinToString(", ")})"
        }
        return "WICHTIGER APP-STATUS: Alina hat BEREITS $desc in der App hinterlegt! " +
            "Frage NICHT, ob du ein Foto machen sollst, und rufe NIEMALS falcon_camera_control / take_photo / Kamera-Aufnahme auf, " +
            "solange sie nicht ausdrücklich 'mach ein neues Foto' verlangt! Nutze direkt die hinterlegten Medien " +
            "(beim Packen oder Senden an Antigravity werden sie automatisch angehängt)."
    }

    fun normalizeGermanSmartHomeText(raw: String?): String {
        if (raw.isNullOrBlank()) return ""
        return raw
            .lowercase(Locale.GERMAN)
            .replace("ß", "ss")
            .replace("ä", "ae")
            .replace("ö", "oe")
            .replace("ü", "ue")
            .replace(Regex("[^a-z0-9]+"), " ")
            .trim()
            .replace(Regex("\\s+"), " ")
    }

    fun stemGermanSmartHomeToken(token: String): String {
        var s = token.trim()
        if (s.length >= 6) {
            when {
                s.endsWith("en") -> s = s.dropLast(2)
                s.endsWith("e") -> s = s.dropLast(1)
                s.endsWith("n") && !s.endsWith("in") -> s = s.dropLast(1)
            }
        }
        return s
    }

    fun matchSmartHomeDevices(
        devices: List<SmartPlugDevice>,
        query: String?
    ): List<SmartPlugDevice> {
        if (devices.isEmpty()) return emptyList()
        val normalizedQuery = normalizeGermanSmartHomeText(query)
        if (normalizedQuery.isEmpty()) return emptyList()

        val rawTokens = normalizedQuery.split(" ").filter { it.length >= 2 }
        if (rawTokens.all { it in setOf("alle", "alles", "beide", "beiden") }) {
            return devices
        }

        val meaningfulTokens = rawTokens
            .filterNot { it in SMARTHOME_STOP_WORDS }
            .ifEmpty { rawTokens }
        val queryStems = meaningfulTokens.map { stemGermanSmartHomeToken(it) }
        val compactQuery = queryStems.joinToString("")

        data class ScoredDevice(
            val device: SmartPlugDevice,
            val matchedTokens: Int,
            val totalQueryTokens: Int,
            val exactCompactMatch: Boolean
        )

        val scored = devices.map { dev ->
            val normName = normalizeGermanSmartHomeText(dev.name)
            val devTokens = normName.split(" ").filter { it.isNotEmpty() }
            val devStems = devTokens.map { stemGermanSmartHomeToken(it) }
            val compactDev = devStems.joinToString("")
            val ipDirectMatch = query?.trim() == dev.ip.trim()

            var matchedCount = 0
            for (i in meaningfulTokens.indices) {
                val qt = meaningfulTokens[i]
                val qs = queryStems[i]
                val tokenHit = devTokens.indices.any { j ->
                    val dt = devTokens[j]
                    val ds = devStems[j]
                    dt == qt ||
                        ds == qs ||
                        (qs.length >= 4 && (dt.startsWith(qs) || ds.startsWith(qs)) && (ds.length - qs.length) <= 4) ||
                        (ds.length >= 4 && (qt.startsWith(ds) || qs.startsWith(ds)) && (qs.length - ds.length) <= 2)
                } || (qs.length >= 5 && compactDev.contains(qs))
                if (tokenHit) matchedCount++
            }

            val exactCompact = ipDirectMatch ||
                (compactQuery.length >= 5 && (compactDev.contains(compactQuery) || compactQuery.contains(compactDev)))

            ScoredDevice(
                device = dev,
                matchedTokens = if (ipDirectMatch) meaningfulTokens.size else matchedCount,
                totalQueryTokens = meaningfulTokens.size,
                exactCompactMatch = exactCompact
            )
        }

        val fullMatches = scored.filter {
            it.totalQueryTokens > 0 && (it.matchedTokens == it.totalQueryTokens || it.exactCompactMatch)
        }
        if (fullMatches.isNotEmpty()) {
            return fullMatches.map { it.device }
        }

        val maxMatched = scored.maxOfOrNull { it.matchedTokens } ?: 0
        if (maxMatched > 0) {
            val top = scored.filter { it.matchedTokens == maxMatched }
            // Avoid matching every device on a generic word when a distinguishing word failed
            if (top.size < devices.size || devices.size == 1) {
                return top.map { it.device }
            }
        }
        return emptyList()
    }

    fun normalizeSmartHomeTargetKey(raw: String?): String {
        if (raw.isNullOrBlank()) return ""
        return normalizeGermanSmartHomeText(raw)
            .replace("terrasse", "terasse")
            .replace("rolllaeden", "rolladen")
            .replace("rollaeden", "rolladen")
            .replace("rollladen", "rolladen")
            .trim()
    }

    fun splitCompoundSmartHomeTargets(query: String?): List<String> {
        if (query.isNullOrBlank()) return emptyList()
        return query
            .split(Regex("\\s+(?:und|sowie|\\+|&)\\s+|\\s*[,;]\\s*", RegexOption.IGNORE_CASE))
            .map { it.trim() }
            .filter { it.isNotEmpty() }
    }

    fun findMatchingSmartHomeGroup(
        groups: List<SmartHomeGroupDto>,
        query: String?
    ): SmartHomeGroupDto? {
        if (groups.isEmpty() || query.isNullOrBlank()) return null
        val stripped = query.trim().replace(Regex("^(?:die\\s+|meine\\s+)?gruppe\\s+", RegexOption.IGNORE_CASE), "").trim()
        val nq = normalizeSmartHomeTargetKey(stripped)
        if (nq.isEmpty()) return null

        groups.firstOrNull { normalizeSmartHomeTargetKey(it.name) == nq }?.let { return it }
        return groups.firstOrNull { g ->
            val ng = normalizeSmartHomeTargetKey(g.name)
            ng.isNotEmpty() && (ng.contains(nq) || nq.contains(ng))
        }
    }

    fun matchSmartHomePlugsWithGroupsAndCompounds(
        devices: List<SmartPlugDevice>,
        groups: List<SmartHomeGroupDto>,
        query: String?
    ): List<SmartPlugDevice> {
        if (devices.isEmpty() || query.isNullOrBlank()) return emptyList()
        val matchedGroup = findMatchingSmartHomeGroup(groups, query)
        if (matchedGroup != null && matchedGroup.plugTargets.isNotEmpty()) {
            val fromGroup = LinkedHashMap<String, SmartPlugDevice>()
            for (pt in matchedGroup.plugTargets) {
                matchSmartHomeDevices(devices, pt).forEach { d -> fromGroup[d.ip] = d }
            }
            if (fromGroup.isNotEmpty()) return fromGroup.values.toList()
        }

        val parts = splitCompoundSmartHomeTargets(query)
        if (parts.size > 1) {
            val combined = LinkedHashMap<String, SmartPlugDevice>()
            for (part in parts) {
                matchSmartHomeDevices(devices, part).forEach { d -> combined[d.ip] = d }
            }
            if (combined.isNotEmpty()) return combined.values.toList()
        }

        return matchSmartHomeDevices(devices, query)
    }

    fun matchSmartShutters(
        shutters: List<SmartShutterDevice>,
        query: String?
    ): List<SmartShutterDevice> {
        if (shutters.isEmpty() || query.isNullOrBlank()) return emptyList()
        val nq = normalizeSmartHomeTargetKey(query)
        if (nq in setOf("alle", "alles", "alle rolladen", "ueberall", "komplett")) {
            return shutters
        }
        val parts = splitCompoundSmartHomeTargets(query).ifEmpty { listOf(query) }
        val hits = LinkedHashMap<String, SmartShutterDevice>()
        for (part in parts) {
            val np = normalizeSmartHomeTargetKey(part)
                .replace(Regex("^rolladen\\s+"), "")
                .trim()
            if (np.isEmpty()) continue
            for (sh in shutters) {
                val nl = normalizeSmartHomeTargetKey(sh.label)
                val key = sh.deviceUrl.ifBlank { sh.label }
                if (nl.isNotEmpty() && (nl == np || nl.contains(np) || np.contains(nl) || sh.deviceUrl.equals(part.trim(), ignoreCase = true))) {
                    hits[key] = sh
                }
            }
        }
        return hits.values.toList()
    }

    fun buildSmartHomeLiveInventoryDirective(
        plugs: List<SmartPlugDevice>,
        shutters: List<SmartShutterDevice>,
        groups: List<SmartHomeGroupDto>
    ): String {
        val parts = mutableListOf<String>()
        if (plugs.isNotEmpty()) {
            parts.add("Lichterketten & Steckdosen: " + plugs.joinToString(", ") { it.name })
        }
        if (shutters.isNotEmpty()) {
            parts.add("Connexoon Somfy Rollläden: " + shutters.joinToString(", ") { "${it.label} (${it.closurePercent}%)" })
        } else {
            parts.add("Connexoon Somfy Rollläden (Gateway 192.168.188.29): per Raumname wie 'Wohnzimmer', 'Terrassentür', 'alle' oder Kombi 'Wohnzimmer und Terrassentür' steuerbar")
        }
        if (groups.isNotEmpty()) {
            parts.add(
                "SmartHome-Gruppen (Steckdosen, Rollläden & Hybrid): " + groups.joinToString(" | ") { g ->
                    val members = (g.shutterTargets + g.plugTargets).joinToString(", ")
                    "${g.name} [$members]"
                }
            )
        }
        return "AKTIVER SMARTHOME-BESTAND (immer lokal via falcon_smarthome_control steuern, inkl. einzelner Lichterketten, 'alle Lichterketten', einzelner Rollläden, Kombis mit 'und' sowie aller Gruppen): " + parts.joinToString(" · ")
    }

    fun shouldAttachCapturedPhoto(
        explicitAttachFlag: Boolean?,
        photo: FalconCapturedPhoto?,
        @Suppress("UNUSED_PARAMETER") currentSessionId: String?,
        @Suppress("UNUSED_PARAMETER") nowMs: Long = System.currentTimeMillis()
    ): Boolean {
        if (photo == null) return false
        if (!photo.attachedToInbox) return true
        return explicitAttachFlag == true
    }

    fun isSportQuery(query: String?): Boolean {
        if (query.isNullOrBlank()) return false
        val lower = query.lowercase(Locale.ROOT)
        return SPORT_KEYWORDS.any { kw -> lower.contains(kw) }
    }

    private val MUSIC_CLUSTER_KEYWORDS: Map<String, List<String>> = mapOf(
        "sport" to (SPORT_KEYWORDS + listOf("hiit", "joggen", "ausdauer", "krafttraining", "aufwaermen", "warmup")),
        "rave_energy" to listOf(
            "rave", "tekk", "hardtekk", "techno", "abriss", "party", "club", "festival", "bass",
            "wachmacher", "wach", "vollgas", "hardstyle", "scooter", "gebrueder brett", "gebrüder brett",
            "lilly palmer", "charlotte de witte", "maddix", "neelix", "harris & ford", "timmy trumpet"
        ),
        "feelgood_routine" to listOf(
            "gute laune", "gutelaune", "happy", "positiv", "feelgood", "feel good", "lifeisgood", "life is good",
            "morgen", "morgenroutine", "aufstehen", "fruehstueck", "frühstück", "tag starten", "alltag",
            "verpacken", "packtisch", "manufaktur", "pop", "80s", "90s", "dance", "sommer", "sonne",
            "michael jackson", "abba", "queen", "dua lipa", "ed sheeran", "mark morrison", "froehlich", "fröhlich", "locker"
        ),
        "focus_flow" to listOf(
            "fokus", "focus", "konzentration", "konzentriert", "deep work", "flow", "arbeiten",
            "buero", "büro", "buchhaltung", "belege", "steuer", "lernen", "produktiv",
            "instrumental", "lofi", "lo-fi", "hintergrund", "synthwave"
        ),
        "calm_evening" to listOf(
            "ruhig", "chill", "chillout", "entspannung", "entspannt", "entspannen", "feierabend",
            "abend", "abendroutine", "nacht", "nachtruhe", "schlafen", "einschlafen", "pause",
            "durchatmen", "runterfahren", "runterkommen", "sanft", "leise", "ambient", "piano",
            "acoustic", "akustik", "ballade", "meditation", "enya", "ludovico", "relax", "wellness",
            "gemuetlich", "gemütlich", "only time"
        )
    )

    private val GENERIC_MUSIC_QUERIES = setOf(
        "musik", "music", "passende musik", "spiel musik", "mach musik an", "musik abspielen",
        "routine", "tagesroutine", "kontext", "etwas passendes", "ueberrasch mich", "überrasch mich",
        "irgendwas", "smart", "auto"
    )

    private val EXPLICIT_FAVORITES_QUERIES = listOf(
        "favorit", "lieblingssong", "lieblingslied", "lieblingsmusik", "meine favoriten", "favorites", "liked"
    )

    fun isFavoritesPlaylist(pl: MusicPlaylistDto): Boolean {
        val normName = pl.name.trim().lowercase(Locale.GERMAN)
        val normId = pl.id.trim().lowercase(Locale.ROOT)
        return normId == "favorites" ||
            normId == "all" ||
            normName in setOf("lieblingssongs", "favoriten", "meine favoriten", "favorites", "liked songs", "alle songs")
    }

    private fun scoreTextAgainstMusicClusters(rawText: String, weight: Int = 1): Map<String, Int> {
        val hay = rawText.lowercase(Locale.GERMAN)
        val scores = LinkedHashMap<String, Int>()
        for ((cluster, kws) in MUSIC_CLUSTER_KEYWORDS) {
            var hits = 0
            for (kw in kws) {
                if (hay.contains(kw)) hits += weight
            }
            scores[cluster] = hits
        }
        return scores
    }

    /**
     * Deeply analyzes any playlist (including playlists containing only 1 single song!)
     * by inspecting both playlist metadata AND the embedded track title(s), artist(s), and vibe cues.
     */
    fun analyzePlaylistSemanticProfile(pl: MusicPlaylistDto): FalconPlaylistSemanticProfile {
        val isFav = isFavoritesPlaylist(pl)
        val isSingle = pl.tracks.size == 1
        val combinedScores = LinkedHashMap<String, Int>()
        for (cluster in MUSIC_CLUSTER_KEYWORDS.keys) {
            combinedScores[cluster] = 0
        }

        val metaScores = scoreTextAgainstMusicClusters("${pl.name} ${pl.description}", weight = 4)
        for ((c, s) in metaScores) {
            combinedScores[c] = (combinedScores[c] ?: 0) + s
        }

        if (pl.tracks.isNotEmpty() && !isFav) {
            val perTrackWeight = when {
                isSingle -> 5
                pl.tracks.size <= 5 -> 3
                pl.tracks.size <= 15 -> 2
                else -> 1
            }
            val trackAcc = LinkedHashMap<String, Int>()
            for (tr in pl.tracks.take(40)) {
                val tHay = "${tr.title} ${tr.artist}".lowercase(Locale.GERMAN)
                val ts = scoreTextAgainstMusicClusters(tHay, weight = perTrackWeight)
                for ((c, s) in ts) {
                    trackAcc[c] = (trackAcc[c] ?: 0) + s
                }
                if (isSingle) {
                    // Extra subtle song-level mood inference when a 1-song playlist has a neutral playlist title
                    when {
                        Regex("\\b(dream|moon|night|slow|soft|rain|ocean|calm|peace|heart|piano|love|time)\\b").containsMatchIn(tHay) ->
                            trackAcc["calm_evening"] = (trackAcc["calm_evening"] ?: 0) + 3
                        Regex("\\b(remix|vip|edit|hard|club|drop|kick|rave|tekk|techno)\\b").containsMatchIn(tHay) ->
                            trackAcc["rave_energy"] = (trackAcc["rave_energy"] ?: 0) + 3
                        Regex("\\b(sun|good|dance|groove|smile|summer|magic|boogie)\\b").containsMatchIn(tHay) ->
                            trackAcc["feelgood_routine"] = (trackAcc["feelgood_routine"] ?: 0) + 3
                    }
                }
            }
            val cap = if (pl.tracks.size > 15) 12 else Int.MAX_VALUE
            for ((c, s) in trackAcc) {
                combinedScores[c] = (combinedScores[c] ?: 0) + s.coerceAtMost(cap)
            }
        }

        val bestEntry = combinedScores.maxByOrNull { it.value }
        val primaryCluster = if (bestEntry != null && bestEntry.value > 0) {
            bestEntry.key
        } else if (isFav) {
            "favorites"
        } else {
            "feelgood_routine"
        }

        val seedSummary = when {
            pl.tracks.size == 1 -> {
                val t = pl.tracks.first()
                "1-Song-Seed: '${t.title}' von ${t.artist.ifBlank { "Unbekannt" }}"
            }
            pl.tracks.size in 2..3 -> {
                pl.tracks.joinToString(", ") { "'${it.title}' (${it.artist.ifBlank { "Unbekannt" }})" }
            }
            else -> null
        }

        return FalconPlaylistSemanticProfile(
            playlistId = pl.id,
            playlistName = pl.name,
            trackCount = pl.tracks.size,
            isFavoritesPool = isFav,
            isSingleSongPlaylist = isSingle,
            primaryCluster = primaryCluster,
            clusterScores = combinedScores,
            seedTrackSummary = seedSummary
        )
    }

    fun buildMusicLibraryLiveDirective(playlists: List<MusicPlaylistDto>): String {
        val visible = playlists.filter {
            !it.name.equals("__FUNKENWERK_FAV_SYNC__", ignoreCase = true) && it.tracks.isNotEmpty()
        }
        if (visible.isEmpty()) return ""
        val items = visible.take(14).map { pl ->
            val prof = analyzePlaylistSemanticProfile(pl)
            if (prof.seedTrackSummary != null) {
                "${pl.name} (${pl.tracks.size} Song(s), ${prof.seedTrackSummary}, Vibe: ${prof.primaryCluster})"
            } else {
                "${pl.name} (${pl.tracks.size} Songs, Vibe: ${prof.primaryCluster})"
            }
        }
        return "AKTIVE MUSIK-BIBLIOTHEK (für falcon_music_control): " + items.joinToString(" | ") +
            " · MUSIK-INTELLIGENZ: Wähle Playlists klug nach Kontext, Tageszeit/Routine oder Stimmung (action='play_smart' oder 'play_query') statt pauschal Lieblingssongs abzuspielen! Playlists mit nur 1 Song sind vollwertige Seed-Playlists (du kennst ihren exakten Song & Vibe und kannst sie direkt abspielen oder mit action='analyze_playlist' analysieren)."
    }

    fun expandSingleSongPlaylistQueue(
        seedTrack: MusicTrackDto,
        seedProfile: FalconPlaylistSemanticProfile,
        consolidatedTracks: List<MusicTrackDto>,
        maxExtra: Int = 14
    ): List<MusicTrackDto> {
        if (consolidatedTracks.size <= 1 || maxExtra <= 0) return listOf(seedTrack)
        val seedArtistNorm = seedTrack.artist.trim().lowercase(Locale.GERMAN)
        val targetCluster = seedProfile.primaryCluster
        val scoredCompanions = consolidatedTracks
            .filter { it.id != seedTrack.id }
            .mapNotNull { candidate ->
                val cHay = "${candidate.title} ${candidate.artist}".lowercase(Locale.GERMAN)
                val cScores = scoreTextAgainstMusicClusters(cHay, weight = 2)
                var score = 0
                if (seedArtistNorm.length >= 3 && candidate.artist.trim().lowercase(Locale.GERMAN).contains(seedArtistNorm)) {
                    score += 8
                }
                val clusterAffinity = cScores[targetCluster] ?: 0
                score += clusterAffinity
                if (targetCluster in setOf("calm_evening", "focus_flow") &&
                    ((cScores["rave_energy"] ?: 0) > 0 || (cScores["sport"] ?: 0) > 0)
                ) {
                    score -= 10
                }
                if (score > 0) Pair(candidate, score) else null
            }
            .sortedByDescending { it.second }
            .take(maxExtra)
            .map { it.first }

        return listOf(seedTrack) + scoredCompanions
    }

    private fun resolveRoutineClusterForHour(hourOfDay: Int, routineContext: String?): List<String> {
        if (!routineContext.isNullOrBlank()) {
            val ctxScores = scoreTextAgainstMusicClusters(routineContext, weight = 2)
            val top = ctxScores.entries.filter { it.value > 0 }.sortedByDescending { it.value }.map { it.key }
            if (top.isNotEmpty()) return top
        }
        return when (hourOfDay) {
            in 6..11 -> listOf("feelgood_routine", "focus_flow")
            in 12..17 -> listOf("feelgood_routine", "focus_flow")
            in 18..20 -> listOf("feelgood_routine", "calm_evening")
            else -> listOf("calm_evening", "focus_flow")
        }
    }

    fun matchMusicForFalcon(
        playlists: List<MusicPlaylistDto>,
        allTracks: List<MusicTrackDto>,
        action: String,
        query: String?,
        currentHour: Int = -1,
        routineContext: String? = null
    ): FalconMusicMatchResult {
        val visiblePlaylists = playlists.filterNot {
            it.name.equals("__FUNKENWERK_FAV_SYNC__", ignoreCase = true)
        }
        val consolidatedTracks = if (allTracks.isNotEmpty()) {
            allTracks
        } else {
            visiblePlaylists.flatMap { it.tracks }.distinctBy { it.id }
        }

        val cleanAction = action.trim().lowercase(Locale.ROOT)
        val cleanQuery = query?.trim().orEmpty()
        val lowerQuery = cleanQuery.lowercase(Locale.GERMAN)
        val profilesById = visiblePlaylists.associate { it.id to analyzePlaylistSemanticProfile(it) }
        val treatAsSport = cleanAction == "play_sport" || isSportQuery(cleanQuery)

        // 1. Explicit Sport / Workout path (also recognizes 1-song sport playlists via track analysis!)
        if (treatAsSport) {
            val sportPlaylists = visiblePlaylists
                .filter { pl ->
                    pl.tracks.isNotEmpty() && !isFavoritesPlaylist(pl) &&
                        ((profilesById[pl.id]?.clusterScores?.get("sport") ?: 0) > 0)
                }
                .sortedByDescending { profilesById[it.id]?.clusterScores?.get("sport") ?: 0 }

            if (sportPlaylists.isNotEmpty()) {
                val chosenPl = sportPlaylists.first()
                val prof = profilesById[chosenPl.id]!!
                val isSingle = chosenPl.tracks.size == 1
                val queue = if (isSingle && cleanAction in setOf("play_smart", "play_context", "play_radio")) {
                    expandSingleSongPlaylistQueue(chosenPl.tracks.first(), prof, consolidatedTracks)
                } else {
                    chosenPl.tracks
                }
                return FalconMusicMatchResult(
                    tracks = queue,
                    playlistId = chosenPl.id,
                    playlistName = chosenPl.name,
                    shuffle = !isSingle,
                    usedFallbackFavorites = false,
                    matchedCluster = "sport",
                    isSingleSongSeedMatch = isSingle,
                    singleSongExtendedCount = (queue.size - chosenPl.tracks.size).coerceAtLeast(0)
                )
            }

            val sportTracks = consolidatedTracks.filter { tr ->
                val hay = "${tr.title} ${tr.artist}".lowercase(Locale.GERMAN)
                (scoreTextAgainstMusicClusters(hay)["sport"] ?: 0) > 0
            }
            if (sportTracks.isNotEmpty()) {
                return FalconMusicMatchResult(
                    tracks = sportTracks,
                    playlistId = "all",
                    playlistName = "Sport-Auswahl",
                    shuffle = true,
                    usedFallbackFavorites = false,
                    matchedCluster = "sport"
                )
            }

            // Explicit sport request with zero sport playlists/tracks -> deterministic fallback to Favorites
            val favorites = consolidatedTracks.filter { it.isFavorite }
            val fallbackPool = favorites.ifEmpty { consolidatedTracks }
            return FalconMusicMatchResult(
                tracks = fallbackPool,
                playlistId = if (favorites.isNotEmpty()) "favorites" else "all",
                playlistName = if (favorites.isNotEmpty()) "Lieblingssongs" else "Alle Songs",
                shuffle = true,
                usedFallbackFavorites = true
            )
        }

        // 2. Explicit Favorites request ("Spiel meine Lieblingssongs / Favoriten")
        if (lowerQuery.isNotEmpty() && EXPLICIT_FAVORITES_QUERIES.any { lowerQuery.contains(it) }) {
            val favPl = visiblePlaylists.firstOrNull { isFavoritesPlaylist(it) && it.tracks.isNotEmpty() }
            val favorites = favPl?.tracks ?: consolidatedTracks.filter { it.isFavorite }
            val pool = favorites.ifEmpty { consolidatedTracks }
            return FalconMusicMatchResult(
                tracks = pool,
                playlistId = favPl?.id ?: if (favorites.isNotEmpty()) "favorites" else "all",
                playlistName = favPl?.name ?: if (favorites.isNotEmpty()) "Lieblingssongs" else "Alle Songs",
                shuffle = true,
                usedFallbackFavorites = false,
                matchedCluster = "favorites"
            )
        }

        val isGenericOrEmptyQuery = lowerQuery.isEmpty() || lowerQuery in GENERIC_MUSIC_QUERIES

        // 3. Specific non-generic query: Direct Playlist, 1-Song Playlist Content Match, Semantic Mood/Routine, or Track Search
        if (!isGenericOrEmptyQuery) {
            val stopQueryWords = setOf("playlist", "play", "spiele", "spiel", "mach", "bitte", "musik", "songs", "lied", "lieder", "meine", "mein", "von", "mit", "zum", "zur", "fuer", "für")
            val tokens = lowerQuery
                .split(Regex("\\s+"))
                .map { it.trim() }
                .filter { it.length >= 2 && it !in stopQueryWords }
                .ifEmpty {
                    lowerQuery.split(Regex("\\s+")).filter { it.length >= 2 }.ifEmpty { listOf(lowerQuery) }
                }

            val wantsRadioExtension = cleanAction in setOf("play_smart", "play_context", "play_radio") ||
                lowerQuery.contains("radio") || lowerQuery.contains("ähnlich") || lowerQuery.contains("aehnlich") || lowerQuery.contains("weiter")

            // 3a. Direct match on Playlist Name/Description OR (for 1..5 song playlists!) on the Song Title / Artist inside it!
            val nonFavFirst = visiblePlaylists.sortedBy { if (isFavoritesPlaylist(it)) 1 else 0 }
            val directPl = nonFavFirst.firstOrNull { pl ->
                if (pl.tracks.isEmpty()) return@firstOrNull false
                val metaHay = "${pl.name} ${pl.description}".lowercase(Locale.GERMAN)
                val smallPlaylistTracksHay = if (!isFavoritesPlaylist(pl) && pl.tracks.size in 1..5) {
                    pl.tracks.joinToString(" ") { "${it.title} ${it.artist}" }.lowercase(Locale.GERMAN)
                } else {
                    ""
                }
                val combinedHay = "$metaHay $smallPlaylistTracksHay".trim()
                combinedHay.contains(lowerQuery) || tokens.all { combinedHay.contains(it) }
            }
            if (directPl != null) {
                val prof = profilesById[directPl.id] ?: analyzePlaylistSemanticProfile(directPl)
                val isSingle = directPl.tracks.size == 1
                val queue = if (isSingle && wantsRadioExtension) {
                    expandSingleSongPlaylistQueue(directPl.tracks.first(), prof, consolidatedTracks)
                } else {
                    directPl.tracks
                }
                return FalconMusicMatchResult(
                    tracks = queue,
                    playlistId = directPl.id,
                    playlistName = directPl.name,
                    shuffle = false,
                    usedFallbackFavorites = false,
                    matchedCluster = prof.primaryCluster,
                    isSingleSongSeedMatch = isSingle,
                    singleSongExtendedCount = (queue.size - directPl.tracks.size).coerceAtLeast(0)
                )
            }

            // 3b. Semantic Mood / Activity / Routine Cluster Match (e.g. "ruhige Musik", "Gute Laune", "Morgenroutine", "Rave", "Fokus")
            val queryClusterScores = scoreTextAgainstMusicClusters("$lowerQuery ${routineContext.orEmpty()}", weight = 2)
            val activeQueryClusters = queryClusterScores.entries
                .filter { it.value > 0 }
                .sortedByDescending { it.value }
                .map { it.key }

            if (activeQueryClusters.isNotEmpty()) {
                val bestSemanticPlaylist = visiblePlaylists
                    .filter { pl -> pl.tracks.isNotEmpty() && !isFavoritesPlaylist(pl) }
                    .mapNotNull { pl ->
                        val prof = profilesById[pl.id] ?: return@mapNotNull null
                        val affinity = activeQueryClusters.mapIndexed { idx, cl ->
                            val priorityMul = if (idx == 0) 2 else 1
                            (prof.clusterScores[cl] ?: 0) * priorityMul
                        }.sum()
                        if (affinity > 0) Triple(pl, prof, affinity) else null
                    }
                    .maxWithOrNull(compareBy<Triple<MusicPlaylistDto, FalconPlaylistSemanticProfile, Int>> { it.third }
                        .thenBy { if (it.first.tracks.size >= 2) 1 else 0 })

                if (bestSemanticPlaylist != null) {
                    val (chosenPl, prof, _) = bestSemanticPlaylist
                    val isSingle = chosenPl.tracks.size == 1
                    val queue = if (isSingle) {
                        expandSingleSongPlaylistQueue(chosenPl.tracks.first(), prof, consolidatedTracks)
                    } else {
                        chosenPl.tracks
                    }
                    return FalconMusicMatchResult(
                        tracks = queue,
                        playlistId = chosenPl.id,
                        playlistName = chosenPl.name,
                        shuffle = !isSingle,
                        usedFallbackFavorites = false,
                        matchedCluster = activeQueryClusters.first(),
                        isSingleSongSeedMatch = isSingle,
                        singleSongExtendedCount = (queue.size - chosenPl.tracks.size).coerceAtLeast(0)
                    )
                }

                // Track-level semantic pool when no dedicated playlist exists for that mood (prevents blasting Hardtekk on "ruhige Musik"!)
                val primaryCluster = activeQueryClusters.first()
                val semanticTracks = consolidatedTracks.filter { tr ->
                    val tScores = scoreTextAgainstMusicClusters("${tr.title} ${tr.artist}")
                    val posHit = (tScores[primaryCluster] ?: 0) > 0
                    val hardConflict = primaryCluster in setOf("calm_evening", "focus_flow") &&
                        ((tScores["rave_energy"] ?: 0) > 0 || (tScores["sport"] ?: 0) > 0)
                    posHit && !hardConflict
                }
                if (semanticTracks.isNotEmpty()) {
                    val label = when (primaryCluster) {
                        "calm_evening" -> "Ruhige Auswahl"
                        "focus_flow" -> "Fokus-Auswahl"
                        "rave_energy" -> "Energie-Auswahl"
                        else -> "Stimmungs-Auswahl"
                    }
                    return FalconMusicMatchResult(
                        tracks = semanticTracks,
                        playlistId = "all",
                        playlistName = label,
                        shuffle = true,
                        usedFallbackFavorites = false,
                        matchedCluster = primaryCluster
                    )
                }
            }

            // 3c. Direct Track / Artist search across consolidated library
            val matchedTracks = consolidatedTracks.filter { tr ->
                val hay = "${tr.title} ${tr.artist}".lowercase(Locale.GERMAN)
                hay.contains(lowerQuery) || tokens.all { hay.contains(it) }
            }
            if (matchedTracks.isNotEmpty()) {
                return FalconMusicMatchResult(
                    tracks = matchedTracks,
                    playlistId = "all",
                    playlistName = cleanQuery,
                    shuffle = false,
                    usedFallbackFavorites = false
                )
            }
        }

        // 4. Contextual Time-of-Day / Routine Selection when query is empty or generic ("Mach Musik an", "Passende Musik")
        if (isGenericOrEmptyQuery) {
            val effectiveHour = if (currentHour in 0..23) {
                currentHour
            } else {
                Calendar.getInstance().get(Calendar.HOUR_OF_DAY)
            }
            val preferredClusters = resolveRoutineClusterForHour(effectiveHour, routineContext)
            val isNightCalmWindow = effectiveHour >= 21 || effectiveHour in 0..5

            val nonFavCandidates = visiblePlaylists.filter { pl ->
                pl.tracks.isNotEmpty() &&
                    !isFavoritesPlaylist(pl) &&
                    pl.id != "local_offline_cache" &&
                    pl.id != "device_media_music"
            }

            if (nonFavCandidates.isNotEmpty()) {
                val scoredContextual = nonFavCandidates.mapNotNull { pl ->
                    val prof = profilesById[pl.id] ?: analyzePlaylistSemanticProfile(pl)
                    // Never auto-pick aggressive Rave/Workout playlists late at night unless explicitly requested
                    if (isNightCalmWindow && prof.primaryCluster in setOf("rave_energy", "sport") &&
                        (prof.clusterScores["calm_evening"] ?: 0) == 0
                    ) {
                        return@mapNotNull null
                    }
                    var ctxScore = 1
                    preferredClusters.forEachIndexed { idx, cl ->
                        val weight = if (idx == 0) 3 else 1
                        ctxScore += (prof.clusterScores[cl] ?: 0) * weight
                        if (prof.primaryCluster == cl) ctxScore += 4
                    }
                    Triple(pl, prof, ctxScore)
                }.maxByOrNull { it.third }

                if (scoredContextual != null) {
                    val (chosenPl, prof, _) = scoredContextual
                    val isSingle = chosenPl.tracks.size == 1
                    val queue = if (isSingle) {
                        expandSingleSongPlaylistQueue(chosenPl.tracks.first(), prof, consolidatedTracks)
                    } else {
                        chosenPl.tracks
                    }
                    return FalconMusicMatchResult(
                        tracks = queue,
                        playlistId = chosenPl.id,
                        playlistName = chosenPl.name,
                        shuffle = !isSingle,
                        usedFallbackFavorites = false,
                        matchedCluster = prof.primaryCluster,
                        isSingleSongSeedMatch = isSingle,
                        singleSongExtendedCount = (queue.size - chosenPl.tracks.size).coerceAtLeast(0)
                    )
                }
            }
        }

        // 5. Final guaranteed fallback: Shuffle over favorites (or full library)
        val favorites = consolidatedTracks.filter { it.isFavorite }
        val fallbackPool = favorites.ifEmpty { consolidatedTracks }
        return FalconMusicMatchResult(
            tracks = fallbackPool,
            playlistId = if (favorites.isNotEmpty()) "favorites" else "all",
            playlistName = if (favorites.isNotEmpty()) "Lieblingssongs" else "Alle Songs",
            shuffle = true,
            usedFallbackFavorites = true
        )
    }

    fun resolveFalconAppDestination(rawDestination: String?): FalconResolvedDestination {
        return when (rawDestination?.trim()?.lowercase(Locale.ROOT)) {
            "dashboard", "zentrum", "home" -> FalconResolvedDestination(
                target = NavigationBridge.NavTarget.ZENTRUM,
                label = "Dashboard"
            )
            "finances", "finance", "finanzen", "buchhaltung" -> FalconResolvedDestination(
                target = NavigationBridge.NavTarget.FINANCES,
                label = "Finanzen"
            )
            "receipt_scanner", "beleg", "belegscanner", "scanner" -> FalconResolvedDestination(
                target = NavigationBridge.NavTarget.ZENTRUM,
                openReceiptScanner = true,
                label = "Beleg-Scanner"
            )
            "orders", "bestellungen", "auftraege" -> FalconResolvedDestination(
                target = NavigationBridge.NavTarget.ORDERS,
                label = "Bestellungen"
            )
            "tasks", "aufgaben", "todo" -> FalconResolvedDestination(
                target = NavigationBridge.NavTarget.ORGANIZER,
                organizerSubTab = 0,
                label = "Aufgaben"
            )
            "calendar", "kalender", "termine" -> FalconResolvedDestination(
                target = NavigationBridge.NavTarget.ORGANIZER,
                organizerSubTab = 1,
                label = "Kalender"
            )
            "routine", "tagesroutine" -> FalconResolvedDestination(
                target = NavigationBridge.NavTarget.ORGANIZER,
                organizerSubTab = 2,
                label = "Routine"
            )
            "shopping", "einkauf", "einkaufsliste" -> FalconResolvedDestination(
                target = NavigationBridge.NavTarget.ORGANIZER,
                organizerSubTab = 3,
                label = "Einkaufsliste"
            )
            "mail", "email", "postfach" -> FalconResolvedDestination(
                target = NavigationBridge.NavTarget.ORGANIZER,
                organizerSubTab = 4,
                label = "E-Mail"
            )
            "music", "musik", "player" -> FalconResolvedDestination(
                target = NavigationBridge.NavTarget.ORGANIZER,
                organizerSubTab = 5,
                label = "Musik"
            )
            "alarm", "wecker", "timer" -> FalconResolvedDestination(
                target = NavigationBridge.NavTarget.ORGANIZER,
                organizerSubTab = 6,
                label = "Wecker"
            )
            "phone", "telefon", "anrufbeantworter" -> FalconResolvedDestination(
                target = NavigationBridge.NavTarget.ORGANIZER,
                organizerSubTab = 7,
                label = "Anrufbeantworter"
            )
            "settings", "einstellungen" -> FalconResolvedDestination(
                target = NavigationBridge.NavTarget.SETTINGS,
                label = "Einstellungen"
            )
            else -> FalconResolvedDestination(
                target = NavigationBridge.NavTarget.ZENTRUM,
                label = "Dashboard"
            )
        }
    }

    fun appendFalconToolDeclarations(decls: JsonArray) {
        for (i in decls.size() - 1 downTo 0) {
            val n = decls.get(i)?.takeIf { it.isJsonObject }?.asJsonObject?.get("name")?.asString
            if (n != null && n in SUPERSEDED_SERVER_LIVE_TOOLS) {
                decls.remove(i)
            }
        }

        val existing = mutableSetOf<String>()
        for (i in 0 until decls.size()) {
            val n = decls.get(i)?.takeIf { it.isJsonObject }?.asJsonObject?.get("name")?.asString
            if (!n.isNullOrBlank()) existing.add(n)
        }

        if ("falcon_music_control" !in existing) {
            decls.add(JsonObject().apply {
                addProperty("name", "falcon_music_control")
                addProperty(
                    "description",
                    "Steuert intelligent den lokalen Musikplayer auf Alinas Smartphone (kontext-/routinebasierte Auswahl, 1-Song-Playlist-Analyse & Seed-Wiedergabe, Sportmusik, Playlist/Song-Suche, Pause, Weiter, Zurück, Shuffle, Lautstärke). Bestätige bei Musikstart danach nur ultrakurz ohne Gegenfrage, da Funkira direkt in den Ruhemodus wechselt."
                )
                add("parameters", JsonObject().apply {
                    addProperty("type", "OBJECT")
                    add("properties", JsonObject().apply {
                        add("action", JsonObject().apply {
                            addProperty("type", "STRING")
                            addProperty(
                                "description",
                                "Aktion: play_smart, play_query, play_sport, analyze_playlist, list_playlists, pause, resume, next, previous, shuffle, set_volume"
                            )
                        })
                        add("query", JsonObject().apply {
                            addProperty("type", "STRING")
                            addProperty(
                                "description",
                                "Optionaler Suchbegriff für Playlist (auch 1-Song-Playlists), Stimmung/Kontext (z. B. ruhig, Gute Laune, Morgenroutine, Fokus, Rave), Song oder Künstler."
                            )
                        })
                        add("routine_context", JsonObject().apply {
                            addProperty("type", "STRING")
                            addProperty(
                                "description",
                                "Optionaler aktueller Routine- oder Aktivitäts-Kontext (z. B. Morgenroutine, Manufaktur Verpacken, Büro Fokus, Feierabend, Nachtruhe)."
                            )
                        })
                        add("volume_percent", JsonObject().apply {
                            addProperty("type", "INTEGER")
                            addProperty("description", "Optionale Lautstärke von 0 bis 100 bei set_volume.")
                        })
                    })
                    add("required", JsonArray().apply { add("action") })
                })
            })
        }

        if ("falcon_camera_control" !in existing) {
            decls.add(JsonObject().apply {
                addProperty("name", "falcon_camera_control")
                addProperty(
                    "description",
                    "Steuert Funkiras Kamera-Augen auf dem Smartphone (Live-Sicht ein-/ausschalten, Kamera wechseln oder scharfes hochauflösendes Foto schießen). WICHTIG: Rufe NIEMALS take_photo oder enable_vision auf, wenn Alina bereits ein Foto/Screenshot in der App hinterlegt hat, außer sie verlangt ausdrücklich ein NEUES Kamerafoto (force_new_photo=true) oder Live-Kamera (force_live_vision=true)!"
                )
                add("parameters", JsonObject().apply {
                    addProperty("type", "OBJECT")
                    add("properties", JsonObject().apply {
                        add("action", JsonObject().apply {
                            addProperty("type", "STRING")
                            addProperty("description", "Aktion: enable_vision, disable_vision, switch_camera, take_photo")
                        })
                        add("lens", JsonObject().apply {
                            addProperty("type", "STRING")
                            addProperty("description", "Optionale Linse: back oder front")
                        })
                        add("force_new_photo", JsonObject().apply {
                            addProperty("type", "BOOLEAN")
                            addProperty("description", "Nur auf true setzen, wenn Alina trotz bereits hinterlegtem Bild ausdrücklich verlangt, ein neues Foto mit der Kamera aufzunehmen.")
                        })
                        add("force_live_vision", JsonObject().apply {
                            addProperty("type", "BOOLEAN")
                            addProperty("description", "Nur auf true setzen, wenn Alina ausdrücklich Live-Kamera-Sicht ('mach deine Kamera an') wünscht.")
                        })
                    })
                    add("required", JsonArray().apply { add("action") })
                })
            })
        }

        if ("falcon_smarthome_control" !in existing) {
            decls.add(JsonObject().apply {
                addProperty("name", "falcon_smarthome_control")
                addProperty(
                    "description",
                    "Steuert direkt auf Alinas Smartphone ALLE Smart-Home-Geräte und Gruppen analog zur Funkenwerk Smart-Home-Sektion: Lichterketten & Steckdosen (KLAP v2, einzeln oder 'alle Lichterketten'), Connexoon Window Somfy Rollläden (einzeln, Kombi mit 'und' oder 'alle') sowie alle SmartHome-Gruppen (Steckdosen-Gruppen, Rollladen-Gruppen und gemischte Hybrid-Gruppen)."
                )
                add("parameters", JsonObject().apply {
                    addProperty("type", "OBJECT")
                    add("properties", JsonObject().apply {
                        add("action", JsonObject().apply {
                            addProperty("type", "STRING")
                            addProperty(
                                "description",
                                "Aktion: turn_on, turn_off, toggle, turn_all_on, turn_all_off, shutter_open, shutter_close, shutter_stop, shutter_my, shutter_position, group_open, group_close, group_stop, group_my, group_position, group_on, group_off, group_toggle, list_devices"
                            )
                        })
                        add("device_name", JsonObject().apply {
                            addProperty("type", "STRING")
                            addProperty(
                                "description",
                                "Name der Lichterkette/Steckdose (z. B. Lichterkette Fußleiste, Lichterkette Spiegel, alle Lichterketten, Packtisch Warm Licht), des Rollladens (z. B. Wohnzimmer, Terrassentür, alle), einer Gruppe oder Kombi wie 'Wohnzimmer und Terrassentür'."
                            )
                        })
                        add("position_percent", JsonObject().apply {
                            addProperty("type", "INTEGER")
                            addProperty(
                                "description",
                                "Optionaler Schließgrad 0 bis 100 Prozent bei shutter_position oder group_position (0 = offen, 100 = geschlossen)."
                            )
                        })
                    })
                    add("required", JsonArray().apply { add("action") })
                })
            })
        } else {
            for (i in 0 until decls.size()) {
                val obj = decls.get(i)?.takeIf { it.isJsonObject }?.asJsonObject ?: continue
                if (obj.get("name")?.asString == "falcon_smarthome_control") {
                    val props = obj.getAsJsonObject("parameters")?.getAsJsonObject("properties")
                    if (props != null && !props.has("position_percent")) {
                        props.add("position_percent", JsonObject().apply {
                            addProperty("type", "INTEGER")
                            addProperty(
                                "description",
                                "Optionaler Schließgrad 0 bis 100 Prozent bei shutter_position oder group_position (0 = offen, 100 = geschlossen)."
                            )
                        })
                    }
                }
            }
        }

        if ("falcon_send_to_antigravity" !in existing) {
            decls.add(JsonObject().apply {
                addProperty("name", "falcon_send_to_antigravity")
                addProperty(
                    "description",
                    "Sendet einen Entwicklungsauftrag, eine Idee oder einen Bug-Bericht (optional inklusive des gerade geschossenen Kamera-Fotos) direkt in den passenden Abteilungs-Kanal im Antigravity-Orchester."
                )
                add("parameters", JsonObject().apply {
                    addProperty("type", "OBJECT")
                    add("properties", JsonObject().apply {
                        add("title", JsonObject().apply {
                            addProperty("type", "STRING")
                            addProperty("description", "Kurzer klarer Haupttitel des Auftrags.")
                        })
                        add("body", JsonObject().apply {
                            addProperty("type", "STRING")
                            addProperty("description", "Optionale detaillierte Beschreibung.")
                        })
                        add("checklist", JsonObject().apply {
                            addProperty("type", "ARRAY")
                            add("items", JsonObject().apply { addProperty("type", "STRING") })
                            addProperty("description", "Optionale Liste konkreter Unterpunkte.")
                        })
                        add("execution_mode", JsonObject().apply {
                            addProperty("type", "STRING")
                            addProperty("description", "Modus: concept (Erst Konzept) oder direct (Direkt umsetzen).")
                        })
                        add("channel", JsonObject().apply {
                            addProperty("type", "STRING")
                            addProperty(
                                "description",
                                "Optionaler Ziel-Kanal: 'leitung' für abteilungsübergreifende Themen (Standard), 'shop' für Webportal/Shop/Produkte/Konfigurator sowie ALLE 3D-Modelle & 3D-Druck-Konstruktionen, 'android' für Android-App/Widgets, 'minipc' NUR für reine Werkstatt-Mini-PC Linux/Kiosk-Technik (NIEMALS für 3D-Modelle/Konstruktionen!), 'buchhaltung' für Buchhaltung/Belege/Marketing, 'new' wenn Alina explizit einen ganz neuen Einzel-Chat wünscht."
                            )
                        })
                        add("attach_captured_photo", JsonObject().apply {
                            addProperty("type", "BOOLEAN")
                            addProperty("description", "True, wenn das zuletzt mit take_photo aufgenommene Foto angehängt werden soll.")
                        })
                    })
                    add("required", JsonArray().apply { add("title") })
                })
            })
        } else {
            for (i in 0 until decls.size()) {
                val obj = decls.get(i)?.takeIf { it.isJsonObject }?.asJsonObject ?: continue
                if (obj.get("name")?.asString == "falcon_send_to_antigravity") {
                    val props = obj.getAsJsonObject("parameters")?.getAsJsonObject("properties")
                    if (props != null && !props.has("channel")) {
                        props.add("channel", JsonObject().apply {
                            addProperty("type", "STRING")
                            addProperty(
                                "description",
                                "Optionaler Ziel-Kanal: 'leitung' für abteilungsübergreifende Themen (Standard), 'shop' für Webportal/Shop/Produkte/Konfigurator sowie ALLE 3D-Modelle & 3D-Druck-Konstruktionen, 'android' für Android-App/Widgets, 'minipc' NUR für reine Werkstatt-Mini-PC Linux/Kiosk-Technik (NIEMALS für 3D-Modelle/Konstruktionen!), 'buchhaltung' für Buchhaltung/Belege/Marketing, 'new' wenn Alina explizit einen ganz neuen Einzel-Chat wünscht."
                            )
                        })
                    }
                }
            }
        }

        if ("falcon_check_antigravity_status" !in existing) {
            decls.add(JsonObject().apply {
                addProperty("name", "falcon_check_antigravity_status")
                addProperty(
                    "description",
                    "Prüft den aktuellen Live-Status der letzten Aufträge im Antigravity-Entwicklungsleitstand inklusive Antworten und wartender Go-Blockaden."
                )
                add("parameters", JsonObject().apply {
                    addProperty("type", "OBJECT")
                    add("properties", JsonObject())
                })
            })
        }

        if ("falcon_resolve_antigravity_blockade" !in existing) {
            decls.add(JsonObject().apply {
                addProperty("name", "falcon_resolve_antigravity_blockade")
                addProperty(
                    "description",
                    "Löst eine offene Antigravity-Blockade oder Freigabe-Rückfrage (awaiting_go) direkt mit Alinas Antwort auf."
                )
                add("parameters", JsonObject().apply {
                    addProperty("type", "OBJECT")
                    add("properties", JsonObject().apply {
                        add("item_id", JsonObject().apply {
                            addProperty("type", "STRING")
                            addProperty("description", "Ticket-ID (z. B. ag_...) oder 'latest' bei genau einer offenen Blockade.")
                        })
                        add("decision", JsonObject().apply {
                            addProperty("type", "STRING")
                            addProperty("description", "Entscheidung: approved, info_provided, dismissed oder deferred.")
                        })
                        add("answer_text", JsonObject().apply {
                            addProperty("type", "STRING")
                            addProperty("description", "Alinas konkrete Antwort oder Vorgabe.")
                        })
                    })
                    add("required", JsonArray().apply {
                        add("item_id")
                        add("decision")
                    })
                })
            })
        }

        if ("falcon_stage_to_package" !in existing) {
            decls.add(JsonObject().apply {
                addProperty("name", "falcon_stage_to_package")
                addProperty(
                    "description",
                    "Sammelt eine oder mehrere destillierte Anforderungen (plus optional ein gerade aufgenommenes oder hinterlegtes Foto) in einem offenen Sammelpaket (Entwurf), OHNE es schon an Antigravity abzuschicken. Standard-Werkzeug zum Buendeln."
                )
                add("parameters", JsonObject().apply {
                    addProperty("type", "OBJECT")
                    add("properties", JsonObject().apply {
                        add("item", JsonObject().apply {
                            addProperty("type", "STRING")
                            addProperty("description", "Einzelne klar formulierte Anforderung fuer das Sammelpaket.")
                        })
                        add("items", JsonObject().apply {
                            addProperty("type", "ARRAY")
                            add("items", JsonObject().apply { addProperty("type", "STRING") })
                            addProperty("description", "Optional mehrere Anforderungen auf einmal.")
                        })
                        add("package_uuid", JsonObject().apply {
                            addProperty("type", "STRING")
                            addProperty("description", "Optionale UUID eines bestehenden Sammelpakets zum Erweitern.")
                        })
                        add("package_title", JsonObject().apply {
                            addProperty("type", "STRING")
                            addProperty("description", "Optionaler Kurztitel des Sammelpakets.")
                        })
                        add("channel", JsonObject().apply {
                            addProperty("type", "STRING")
                            addProperty("description", "Ziel-Kanal: leitung, shop, android, minipc oder buchhaltung.")
                        })
                        add("execution_mode", JsonObject().apply {
                            addProperty("type", "STRING")
                            addProperty("description", "concept (Erst Konzept) oder direct (Direkt umsetzen).")
                        })
                        add("attach_captured_photo", JsonObject().apply {
                            addProperty("type", "BOOLEAN")
                            addProperty("description", "True, um ein hinterlegtes oder gerade aufgenommenes Foto direkt ins Sammelpaket zu legen.")
                        })
                    })
                    add("required", JsonArray().apply { add("item") })
                })
            })
        }

        if ("falcon_list_draft_packages" !in existing) {
            decls.add(JsonObject().apply {
                addProperty("name", "falcon_list_draft_packages")
                addProperty(
                    "description",
                    "Listet alle offenen Sammelpakete (Entwuerfe) inklusive Titel, Kanal, gesammelten Punkten und Foto-Anzahl auf."
                )
                add("parameters", JsonObject().apply {
                    addProperty("type", "OBJECT")
                    add("properties", JsonObject())
                })
            })
        }

        if ("falcon_release_package" !in existing) {
            decls.add(JsonObject().apply {
                addProperty("name", "falcon_release_package")
                addProperty(
                    "description",
                    "Gibt ein fertiges Sammelpaket geschlossen frei und sendet es als EINEN gebuendelten Master-Auftrag inklusive angehaengter Fotos an Antigravity. Nur auf Alinas ausdruecklichen Absende-Befehl aufrufen."
                )
                add("parameters", JsonObject().apply {
                    addProperty("type", "OBJECT")
                    add("properties", JsonObject().apply {
                        add("package_uuid", JsonObject().apply {
                            addProperty("type", "STRING")
                            addProperty("description", "UUID des Sammelpakets (leer lassen fuer das neueste offene Sammelpaket).")
                        })
                        add("execution_mode", JsonObject().apply {
                            addProperty("type", "STRING")
                            addProperty("description", "Optionaler Modus: concept oder direct.")
                        })
                    })
                })
            })
        }

        if ("falcon_get_recent_memory" !in existing) {
            decls.add(JsonObject().apply {
                addProperty("name", "falcon_get_recent_memory")
                addProperty(
                    "description",
                    "Laedt die neuesten gespeicherten Gespraechsverlaeufe, Kernfakten und das exakte letzte Thema aus Funkiras lokalem Kurzzeitgedaechtnis."
                )
                add("parameters", JsonObject().apply {
                    addProperty("type", "OBJECT")
                    add("properties", JsonObject().apply {
                        add("limit", JsonObject().apply {
                            addProperty("type", "INTEGER")
                            addProperty("description", "Anzahl der letzten Gespraechs-Turns (Standard 12).")
                        })
                    })
                })
            })
        }

        if ("falcon_app_navigate" !in existing) {
            decls.add(JsonObject().apply {
                addProperty("name", "falcon_app_navigate")
                addProperty(
                    "description",
                    "Öffnet sofort den gewünschten Bereich in Alinas Funkenwerk Android-App."
                )
                add("parameters", JsonObject().apply {
                    addProperty("type", "OBJECT")
                    add("properties", JsonObject().apply {
                        add("destination", JsonObject().apply {
                            addProperty("type", "STRING")
                            addProperty(
                                "description",
                                "Ziel: dashboard, finances, receipt_scanner, orders, tasks, calendar, routine, mail, music, phone, settings"
                            )
                        })
                    })
                    add("required", JsonArray().apply { add("destination") })
                })
            })
        }

        // falcon_set_emotion wird bewusst NICHT als WebSocket-Tool registriert, da Emotionen lokal in 0 ms
        // aus Sprache & Aktionen abgeleitet werden (verhindert zusätzliche ToolResponse-Turns & Doppelantworten).
    }

    suspend fun executeLocalFalconTool(
        context: Context,
        toolName: String,
        argsElement: JsonElement?,
        chatSessionId: String,
        onEmotionChanged: (String) -> Unit,
        onNavigate: (FalconResolvedDestination) -> Unit,
        agApiOverride: AgDirectApi? = null,
        smartHomeExecutorOverride: (suspend (action: String, ips: List<String>) -> Pair<Boolean, String>)? = null,
        smartHomeRelayExecutorOverride: (suspend (action: String, target: String, position: Int?) -> Pair<Boolean, String>)? = null
    ): JsonObject {
        val args = if (argsElement != null && argsElement.isJsonObject) argsElement.asJsonObject else JsonObject()
        if ((toolName == "falcon_smarthome_control" ||
                toolName == "falcon_camera_control" ||
                toolName == "falcon_release_package") &&
            agApiOverride == null &&
            smartHomeExecutorOverride == null &&
            smartHomeRelayExecutorOverride == null
        ) {
            return executeDeviceToolWithReflexDedupe(
                context = context,
                toolName = toolName,
                argsElement = args,
                chatSessionId = chatSessionId,
                onEmotionChanged = onEmotionChanged,
                onNavigate = onNavigate
            )
        }
        return executeLocalFalconToolInternal(
            context = context,
            toolName = toolName,
            args = args,
            chatSessionId = chatSessionId,
            onEmotionChanged = onEmotionChanged,
            onNavigate = onNavigate,
            agApiOverride = agApiOverride,
            smartHomeExecutorOverride = smartHomeExecutorOverride,
            smartHomeRelayExecutorOverride = smartHomeRelayExecutorOverride
        )
    }

    private suspend fun executeLocalFalconToolInternal(
        context: Context,
        toolName: String,
        args: JsonObject,
        chatSessionId: String,
        onEmotionChanged: (String) -> Unit,
        onNavigate: (FalconResolvedDestination) -> Unit,
        agApiOverride: AgDirectApi? = null,
        smartHomeExecutorOverride: (suspend (action: String, ips: List<String>) -> Pair<Boolean, String>)? = null,
        smartHomeRelayExecutorOverride: (suspend (action: String, target: String, position: Int?) -> Pair<Boolean, String>)? = null
    ): JsonObject {
        val appCtx = context.applicationContext

        return when (toolName) {
            "falcon_set_emotion" -> {
                val emo = normalizeEmotion(args.optString("emotion"))
                onEmotionChanged(emo)
                JsonObject().apply {
                    addProperty("status", "success")
                    addProperty("emotion", emo)
                }
            }

            "falcon_app_navigate" -> {
                val destRaw = args.optString("destination")
                val resolved = resolveFalconAppDestination(destRaw)
                withContext(Dispatchers.Main.immediate) {
                    if (resolved.openReceiptScanner) {
                        NavigationBridge.pendingOpenQuickEntry = true
                    }
                    NavigationBridge.triggerNavigation(resolved.target, resolved.organizerSubTab)
                    onNavigate(resolved)
                }
                JsonObject().apply {
                    addProperty("status", "success")
                    addProperty("destination", resolved.label)
                }
            }

            "falcon_music_control" -> {
                val action = args.optString("action").ifBlank { "play_smart" }.lowercase(Locale.ROOT)
                val query = args.optString("query").trim()
                val routineCtx = args.optString("routine_context").trim().ifBlank { null }
                val volPercent = args.optInt("volume_percent", -1)

                withContext(Dispatchers.Main.immediate) {
                    MusicPlayerManager.ensureCatalogLoadedFast(appCtx)
                }

                val isPureResume = action == "resume" ||
                    (action == "play" && query.isBlank() && routineCtx == null &&
                        MusicPlayerManager.currentTrack.value != null && !MusicPlayerManager.isPlaying.value)

                val musicRes = when {
                    action in setOf("pause", "stop") -> {
                        withContext(Dispatchers.Main.immediate) {
                            MusicPlayerManager.pauseIfPlaying(appCtx)
                        }
                        JsonObject().apply {
                            addProperty("status", "success")
                            addProperty("action", "pause")
                            addProperty("message", "Musik pausiert.")
                        }
                    }

                    isPureResume -> {
                        withContext(Dispatchers.Main.immediate) {
                            MusicPlayerManager.resumeOrStart(appCtx)
                        }
                        JsonObject().apply {
                            addProperty("status", "success")
                            addProperty("action", "resume")
                            addProperty("track", MusicPlayerManager.currentTrack.value?.title ?: "")
                        }
                    }

                    action in setOf("next", "skip") -> {
                        withContext(Dispatchers.Main.immediate) {
                            MusicPlayerManager.playNext(appCtx)
                        }
                        JsonObject().apply {
                            addProperty("status", "success")
                            addProperty("action", "next")
                            addProperty("track", MusicPlayerManager.currentTrack.value?.title ?: "")
                        }
                    }

                    action in setOf("previous", "prev", "back") -> {
                        withContext(Dispatchers.Main.immediate) {
                            MusicPlayerManager.playPrevious(appCtx)
                        }
                        JsonObject().apply {
                            addProperty("status", "success")
                            addProperty("action", "previous")
                            addProperty("track", MusicPlayerManager.currentTrack.value?.title ?: "")
                        }
                    }

                    action == "shuffle" -> {
                        withContext(Dispatchers.Main.immediate) {
                            MusicPlayerManager.setShuffleEnabled(appCtx, true)
                            if (!MusicPlayerManager.isPlaying.value) {
                                MusicPlayerManager.resumeOrStart(appCtx)
                            } else {
                                MusicPlayerManager.playNext(appCtx)
                            }
                        }
                        JsonObject().apply {
                            addProperty("status", "success")
                            addProperty("action", "shuffle")
                            addProperty("track", MusicPlayerManager.currentTrack.value?.title ?: "")
                        }
                    }

                    action in setOf("set_volume", "volume") -> {
                        val applied = MusicPlayerManager.setUserVolumePercent(
                            appCtx,
                            if (volPercent in 0..100) volPercent else 70
                        )
                        JsonObject().apply {
                            addProperty("status", "success")
                            addProperty("action", "set_volume")
                            addProperty("volume_percent", applied)
                        }
                    }

                    action in setOf("analyze_playlist", "list_playlists", "inspect_playlist") -> {
                        var playlists = MusicPlayerManager.playlists.value
                        var allTracks = MusicPlayerManager.allTracks.value
                        if (playlists.isEmpty()) {
                            val loaded = kotlinx.coroutines.CompletableDeferred<Unit>()
                            withContext(Dispatchers.Main.immediate) {
                                MusicPlayerManager.fetchPlaylists(appCtx) { loaded.complete(Unit) }
                            }
                            withTimeoutOrNull(1800L) { loaded.await() }
                            playlists = MusicPlayerManager.playlists.value
                            allTracks = MusicPlayerManager.allTracks.value
                        }
                        val visible = playlists.filterNot { it.name.equals("__FUNKENWERK_FAV_SYNC__", ignoreCase = true) }
                        val filtered = if (query.isNotBlank()) {
                            val lq = query.lowercase(Locale.GERMAN)
                            visible.filter { pl ->
                                "${pl.name} ${pl.description}".lowercase(Locale.GERMAN).contains(lq) ||
                                    pl.tracks.any { "${it.title} ${it.artist}".lowercase(Locale.GERMAN).contains(lq) }
                            }.ifEmpty { visible }
                        } else {
                            visible
                        }
                        val consolidated = if (allTracks.isNotEmpty()) allTracks else visible.flatMap { it.tracks }.distinctBy { it.id }
                        val plArr = JsonArray()
                        filtered.forEach { pl ->
                            val prof = analyzePlaylistSemanticProfile(pl)
                            plArr.add(JsonObject().apply {
                                addProperty("id", pl.id)
                                addProperty("name", pl.name)
                                addProperty("track_count", pl.tracks.size)
                                addProperty("is_single_song_playlist", prof.isSingleSongPlaylist)
                                addProperty("primary_vibe", prof.primaryCluster)
                                if (prof.seedTrackSummary != null) {
                                    addProperty("seed_summary", prof.seedTrackSummary)
                                }
                                if (prof.isSingleSongPlaylist && pl.tracks.isNotEmpty()) {
                                    val companions = expandSingleSongPlaylistQueue(pl.tracks.first(), prof, consolidated, maxExtra = 5).drop(1)
                                    add("recommended_companion_tracks", JsonArray().apply {
                                        companions.forEach { add("${it.title} (${it.artist})") }
                                    })
                                }
                            })
                        }
                        return JsonObject().apply {
                            addProperty("status", "success")
                            addProperty("action", action)
                            addProperty("auto_standby", false)
                            add("playlists", plArr)
                        }
                    }

                    else -> {
                        // play, play_smart, play_context, play_sport, play_query, play_radio
                        var playlists = MusicPlayerManager.playlists.value
                        var allTracks = MusicPlayerManager.allTracks.value

                        if (allTracks.isEmpty()) {
                            val loaded = kotlinx.coroutines.CompletableDeferred<Unit>()
                            withContext(Dispatchers.Main.immediate) {
                                MusicPlayerManager.fetchPlaylists(appCtx) {
                                    loaded.complete(Unit)
                                }
                            }
                            withTimeoutOrNull(1800L) { loaded.await() }
                            playlists = MusicPlayerManager.playlists.value
                            allTracks = MusicPlayerManager.allTracks.value
                        } else {
                            withContext(Dispatchers.Main.immediate) {
                                MusicPlayerManager.fetchPlaylists(appCtx)
                            }
                        }

                        val match = matchMusicForFalcon(
                            playlists = playlists,
                            allTracks = allTracks,
                            action = action,
                            query = query,
                            routineContext = routineCtx
                        )
                        val startedTrack = if (match.tracks.isNotEmpty()) {
                            withContext(Dispatchers.Main.immediate) {
                                MusicPlayerManager.playQueueFromFalcon(
                                    context = appCtx,
                                    tracks = match.tracks,
                                    shuffle = match.shuffle,
                                    playlistId = match.playlistId
                                )
                            }
                        } else {
                            null
                        }

                        JsonObject().apply {
                            addProperty("status", if (startedTrack != null) "success" else "empty_library")
                            addProperty("action", action)
                            addProperty("playlist", match.playlistName ?: "")
                            addProperty("track", startedTrack?.title ?: "")
                            addProperty("artist", startedTrack?.artist ?: "")
                            addProperty("shuffle", match.shuffle)
                            addProperty("used_favorites_fallback", match.usedFallbackFavorites)
                            if (match.matchedCluster != null) addProperty("matched_vibe", match.matchedCluster)
                            addProperty("is_single_song_playlist", match.isSingleSongSeedMatch)
                            if (match.singleSongExtendedCount > 0) {
                                addProperty("single_song_companion_tracks_added", match.singleSongExtendedCount)
                            }
                        }
                    }
                }
                musicRes.addProperty("auto_standby", true)
                musicRes.addProperty(
                    "standby_directive",
                    "Bestätige ultrakurz in maximal 3 bis 5 Wörtern ohne Gegenfrage, da du danach direkt in den Ruhemodus wechselst."
                )
                musicRes
            }

            "falcon_camera_control" -> {
                val action = args.optString("action").ifBlank { "enable_vision" }.lowercase(Locale.ROOT)
                val lens = args.optString("lens").takeIf { it.isNotBlank() }
                val forceNewPhoto = args.optNullableBoolean("force_new_photo") == true
                val forceLiveVision = args.optNullableBoolean("force_live_vision") == true
                val existingAttachment = AgDirectInboxManager.getActiveStagedAttachment()

                val isPhotoCaptureRequest = action in setOf("take_photo", "photo", "snapshot")
                val isImplicitVisionWhenPhotoAttached = action == "enable_vision" && !forceLiveVision && !forceNewPhoto
                if (existingAttachment != null && ((isPhotoCaptureRequest && !forceNewPhoto) || isImplicitVisionWhenPhotoAttached)) {
                    return JsonObject().apply {
                        addProperty("status", "already_attached")
                        addProperty("has_attached_image", true)
                        addProperty("file_name", existingAttachment.fileName)
                        addProperty(
                            "message",
                            "Alina hat bereits ein Foto/Screenshot in der App hinterlegt. Kamera wurde NICHT erneut geöffnet; das hinterlegte Bild steht bereit und wird beim Direktauftrag automatisch mitgesendet."
                        )
                    }
                }

                if (action != "disable_vision" && !FalconCameraController.hasCameraPermission(appCtx)) {
                    FalconCameraController.requestPermissionForAction(action, lens)
                    return JsonObject().apply {
                        addProperty("status", "permission_requested")
                        addProperty("action", action)
                        addProperty(
                            "message",
                            "Kamera-Berechtigung wurde auf dem Smartphone angefragt. Sobald Alina bestätigt, wird die Kamera automatisch aktiviert."
                        )
                    }
                }

                when (action) {
                    "disable_vision", "off", "stop" -> {
                        FalconCameraController.stopCamera()
                        JsonObject().apply {
                            addProperty("status", "success")
                            addProperty("vision_active", false)
                            addProperty("message", "Kamera-Sicht ausgeschaltet.")
                        }
                    }

                    "switch_camera", "flip" -> {
                        FalconCameraController.switchCameraAsync(appCtx, lens)
                        JsonObject().apply {
                            addProperty("status", "success")
                            addProperty("vision_active", FalconCameraController.isVisionActive.value)
                            addProperty("lens", lens ?: "switched")
                        }
                    }

                    "take_photo", "photo", "snapshot" -> {
                        val photo = FalconCameraController.captureHighResPhoto(
                            context = appCtx,
                            lens = lens,
                            sessionId = chatSessionId
                        )
                        if (photo != null) {
                            JsonObject().apply {
                                addProperty("status", "success")
                                addProperty("photo_captured", true)
                                addProperty("file_name", photo.fileName)
                                addProperty("width", photo.width)
                                addProperty("height", photo.height)
                                addProperty(
                                    "message",
                                    "Scharfes Foto (${photo.width}x${photo.height}) erfolgreich geschossen und für Anhänge bereitgelegt."
                                )
                            }
                        } else {
                            JsonObject().apply {
                                addProperty("status", "error")
                                addProperty("photo_captured", false)
                                addProperty("message", "Foto konnte nicht aufgenommen werden.")
                            }
                        }
                    }

                    else -> {
                        val cleanLens = if (lens?.trim().equals("front", ignoreCase = true)) "front" else "back"
                        FalconCameraController.startVisionAsync(appCtx, cleanLens)
                        JsonObject().apply {
                            addProperty("status", "success")
                            addProperty("vision_active", true)
                            addProperty("lens", cleanLens)
                        }
                    }
                }
            }

            "falcon_send_to_antigravity" -> {
                val title = args.optString("title").trim()
                val body = args.optString("body").trim()
                val checklist = args.optStringList("checklist")
                val rawMode = args.optString("execution_mode")
                val isVoiceDirect = AgDirectInboxManager.isVoiceDirectExecutionEnabled(appCtx)
                val explicitConceptOverride = hasExplicitConceptIntent(title, body)
                val mode = when {
                    explicitConceptOverride -> "concept"
                    isVoiceDirect || rawMode.trim().lowercase(Locale.ROOT) == "direct" -> "direct"
                    else -> AgDirectInboxManager.normalizeExecutionMode(rawMode)
                }
                val channel = AgDirectInboxManager.normalizeChannel(args.optString("channel"))
                val explicitAttach = args.optNullableBoolean("attach_captured_photo")

                val pooledSnapshot = if (explicitAttach != false) FalconCameraController.mediaPoolFlow.value else emptyList()
                val stagedAttachment = AgDirectInboxManager.getActiveStagedAttachment()
                val currentPhoto = FalconCameraController.lastCapturedPhoto.value
                val preparedAttachment = when {
                    pooledSnapshot.isNotEmpty() -> pooledSnapshot.first()
                    stagedAttachment != null -> stagedAttachment
                    shouldAttachCapturedPhoto(
                        explicitAttachFlag = explicitAttach,
                        photo = currentPhoto,
                        currentSessionId = chatSessionId
                    ) && currentPhoto != null -> currentPhoto.toPreparedAgAttachment()
                    else -> null
                }

                val combinedText = when {
                    title.isNotBlank() && body.isNotBlank() -> "$title\n$body"
                    title.isNotBlank() -> title
                    else -> body
                }

                val sentLive = AgDirectInboxManager.submitMessage(
                    context = appCtx,
                    rawText = combinedText,
                    checklist = checklist,
                    executionMode = mode,
                    attachment = preparedAttachment,
                    channel = channel,
                    apiOverride = agApiOverride
                )
                if (pooledSnapshot.size > 1) {
                    pooledSnapshot.drop(1).forEachIndexed { idx, extraAtt ->
                        AgDirectInboxManager.submitMessage(
                            context = appCtx,
                            rawText = "${title.ifBlank { "Direktauftrag" }} (Medien-Anhang #${idx + 2}/${pooledSnapshot.size})\n${extraAtt.fileName}",
                            checklist = emptyList(),
                            executionMode = mode,
                            attachment = extraAtt,
                            channel = channel,
                            apiOverride = agApiOverride
                        )
                    }
                }
                if (preparedAttachment != null) {
                    FalconCameraController.markLastPhotoAttached()
                }

                val channelLabel = AgDirectInboxManager.resolveChannelEnum(channel).chipLabel
                JsonObject().apply {
                    addProperty("status", "success")
                    addProperty("sent_live", sentLive)
                    addProperty("queued_offline", !sentLive)
                    addProperty("execution_mode", mode)
                    addProperty("channel", channel)
                    addProperty("channel_label", channelLabel)
                    addProperty("photo_attached", preparedAttachment != null)
                    addProperty(
                        "message",
                        if (sentLive) {
                            "Auftrag erfolgreich live in Kanal $channelLabel übermittelt."
                        } else {
                            "Auftrag für Kanal $channelLabel verschlüsselt in der Offline-Warteschlange gesichert."
                        }
                    )
                }
            }

            "falcon_check_antigravity_status" -> {
                withTimeoutOrNull(1800L) {
                    AgDirectInboxManager.syncIfStale(appCtx, maxAgeMs = 2500L, apiOverride = agApiOverride)
                } ?: AgDirectInboxManager.hydrateLocalState(appCtx)

                val rt = AgDirectInboxManager.realtimeStatusFlow.value
                val recent = AgDirectInboxManager.itemsFlow.value.take(6)
                val itemsArr = JsonArray()
                recent.forEach { item ->
                    itemsArr.add(JsonObject().apply {
                        addProperty("id", item.id)
                        addProperty("title", item.title)
                        addProperty("status", item.status.badgeLabel)
                        addProperty("status_code", item.status.code)
                        addProperty("is_actively_working", item.status in setOf(AgDirectStatus.QUEUED, AgDirectStatus.PENDING, AgDirectStatus.DELIVERED))
                        addProperty("is_waiting_for_go", item.status == AgDirectStatus.AWAITING_GO)
                        addProperty("execution_mode", item.executionMode)
                        addProperty("channel", item.channel)
                        addProperty("channel_label", AgDirectInboxManager.resolveChannelEnum(item.channel).chipLabel)
                        addProperty("has_attachment", item.hasAttachment)
                        addProperty("pending_question", item.pendingQuestion ?: "")
                        addProperty("ag_response_summary", item.agResponseSummary ?: "")
                    })
                }
                JsonObject().apply {
                    addProperty("status", "success")
                    addProperty("open_count", AgDirectInboxManager.openCountFlow.value)
                    addProperty("working_count", rt.activeWorkingCount)
                    addProperty("pending_blockades_count", AgDirectInboxManager.pendingBlockadesFlow.value.size)
                    addProperty("primary_state", rt.primaryState)
                    addProperty("status_headline", rt.activeHeadline)
                    add("items", itemsArr)
                }
            }

            "falcon_resolve_antigravity_blockade" -> {
                var targetId = args.optString("item_id").ifBlank { args.optString("id") }.trim()
                val decision = args.optString("decision").ifBlank { "approved" }.trim()
                val answerText = args.optString("answer_text").ifBlank { args.optString("answer") }.trim()

                val blockades = AgDirectInboxManager.pendingBlockadesFlow.value
                if ((targetId.isEmpty() || targetId.equals("latest", ignoreCase = true)) && blockades.size == 1) {
                    targetId = blockades.first().id
                }

                if (targetId.isEmpty()) {
                    return JsonObject().apply {
                        addProperty("status", "error")
                        addProperty("message", "Keine eindeutige offene Blockade gefunden. Bitte item_id angeben.")
                    }
                }

                val ok = AgDirectInboxManager.resolveBlockade(
                    context = appCtx,
                    itemId = targetId,
                    decision = decision,
                    answerText = answerText,
                    apiOverride = agApiOverride
                )
                AgDirectInboxManager.refreshFromServer(appCtx)

                JsonObject().apply {
                    addProperty("status", if (ok) "success" else "queued_offline")
                    addProperty("item_id", targetId)
                    addProperty("decision", decision)
                    addProperty(
                        "message",
                        if (ok) {
                            "Antigravity-Blockade $targetId erfolgreich mit Entscheidung '$decision' aufgelöst."
                        } else {
                            "Antwort zu $targetId lokal für die Übermittlung gesichert."
                        }
                    )
                }
            }

            "falcon_stage_to_package" -> {
                val singleItem = args.optString("item").trim()
                val multiItems = args.optStringList("items")
                val pkgUuid = args.optString("package_uuid").trim().takeIf { it.isNotBlank() }
                val pkgTitle = args.optString("package_title").trim().takeIf { it.isNotBlank() }
                val channel = AgDirectInboxManager.normalizeChannel(args.optString("channel"))
                val rawMode = args.optString("execution_mode")
                val mode = if (rawMode.isNotBlank()) rawMode else "concept"
                val explicitAttach = args.optNullableBoolean("attach_captured_photo")

                val pooledItems = if (explicitAttach != false) FalconCameraController.mediaPoolFlow.value else emptyList()
                val stagedAttachment = AgDirectInboxManager.getActiveStagedAttachment()
                val currentPhoto = FalconCameraController.lastCapturedPhoto.value
                val attachmentToPack = when {
                    pooledItems.isNotEmpty() -> pooledItems.first()
                    stagedAttachment != null && explicitAttach != false -> stagedAttachment
                    shouldAttachCapturedPhoto(
                        explicitAttachFlag = explicitAttach,
                        photo = currentPhoto,
                        currentSessionId = chatSessionId
                    ) && currentPhoto != null -> currentPhoto.toPreparedAgAttachment()
                    else -> null
                }

                var stagedPkg = FunkiraPackageStore.stageRequirement(
                    context = appCtx,
                    item = singleItem.takeIf { it.isNotBlank() },
                    items = multiItems,
                    packageUuid = pkgUuid,
                    packageTitle = pkgTitle,
                    channel = channel,
                    executionMode = mode,
                    attachment = attachmentToPack,
                    attachmentCaption = singleItem.ifBlank { pkgTitle.orEmpty() },
                    source = "live_call"
                )
                if (stagedPkg != null && pooledItems.size > 1) {
                    for (extraAtt in pooledItems.drop(1)) {
                        stagedPkg = FunkiraPackageStore.attachMediaToPackage(
                            context = appCtx,
                            attachment = extraAtt,
                            caption = extraAtt.fileName,
                            packageUuid = stagedPkg!!.uuid,
                            channel = channel
                        ) ?: stagedPkg
                    }
                    FalconCameraController.markLastPhotoAttached()
                }
                if (stagedPkg == null) {
                    JsonObject().apply {
                        addProperty("status", "error")
                        addProperty("message", "Mindestens ein Punkt, Titel oder Foto wird fuer das Sammelpaket benoetigt.")
                    }
                } else {
                    JsonObject().apply {
                        addProperty("status", "success")
                        addProperty("uuid", stagedPkg.uuid)
                        addProperty("title", stagedPkg.title)
                        addProperty("channel", stagedPkg.channel)
                        addProperty("execution_mode", stagedPkg.executionMode)
                        addProperty("item_count", stagedPkg.checklist.size)
                        addProperty("attachment_count", stagedPkg.attachmentCount)
                        addProperty("photo_attached", attachmentToPack != null)
                        addProperty(
                            "reply_rule",
                            "Bestaetige kurz einmalig, dass der Punkt im Sammelpaket liegt, und frage genau EINMAL ob weiter gesammelt oder abgesendet werden soll. Nicht wiederholen."
                        )
                    }
                }
            }

            "falcon_list_draft_packages" -> {
                val drafts = FunkiraPackageStore.hydrate(appCtx)
                val arr = JsonArray()
                drafts.take(8).forEach { pkg ->
                    val itemsArr = JsonArray().apply { pkg.checklist.forEach { add(it) } }
                    arr.add(JsonObject().apply {
                        addProperty("uuid", pkg.uuid)
                        addProperty("title", pkg.title)
                        addProperty("channel", pkg.channel)
                        addProperty("execution_mode", pkg.executionMode)
                        addProperty("item_count", pkg.checklist.size)
                        addProperty("attachment_count", pkg.attachmentCount)
                        add("items", itemsArr)
                    })
                }
                JsonObject().apply {
                    addProperty("status", "success")
                    addProperty("count", drafts.size)
                    add("packages", arr)
                }
            }

            "falcon_release_package" -> {
                val pkgUuid = args.optString("package_uuid").trim().takeIf { it.isNotBlank() }
                val rawMode = args.optString("execution_mode").trim().takeIf { it.isNotBlank() }
                val rel = FunkiraPackageStore.releasePackage(
                    context = appCtx,
                    packageUuid = pkgUuid,
                    executionModeOverride = rawMode,
                    apiOverride = agApiOverride
                )
                JsonObject().apply {
                    addProperty("status", if (rel.ok) "success" else "error")
                    addProperty("sent_live", rel.sentLive)
                    addProperty("title", rel.packageTitle)
                    addProperty("item_count", rel.itemCount)
                    addProperty("attachment_count", rel.attachmentCount)
                    addProperty("channel", rel.channel)
                    addProperty("message", rel.message)
                    if (rel.ok) {
                        addProperty(
                            "reply_rule",
                            "Bestaetige GENAU EINMAL ultrakurz ('Erledigt, Alina! Sammelpaket ist raus.') und schweige sofort danach."
                        )
                    }
                }
            }

            "falcon_get_recent_memory" -> {
                val limit = args.optInt("limit", 12).coerceIn(1, FunkiraLocalMemoryStore.MAX_RAW_TURNS)
                val snap = FunkiraLocalMemoryStore.hydrate(appCtx)
                val arr = JsonArray()
                snap.turns.takeLast(limit).forEach { turn ->
                    arr.add(JsonObject().apply {
                        addProperty("role", turn.role)
                        addProperty("mode", turn.mode)
                        addProperty("time", turn.timeShort)
                        addProperty("text", turn.body.take(240))
                    })
                }
                JsonObject().apply {
                    addProperty("status", "success")
                    addProperty("memory_count", snap.memoryCount)
                    addProperty("last_topic_summary", snap.lastTopicSummary)
                    addProperty("rolling_digest", snap.rollingDigest)
                    add("recent_turns", arr)
                }
            }

            "falcon_smarthome_control" -> {
                val rawAction = args.optString("action").trim().lowercase(Locale.ROOT)
                val deviceQuery = args.optString("device_name").trim()
                    .ifBlank { args.optString("target").trim() }
                val rawPos = args.optInt("position_percent", args.optInt("position", -1))
                val positionPercent: Int? = if (rawPos in 0..100) rawPos else null

                FunkenwerkAlarmManager.ensureInitialized(appCtx)
                val preliminaryPlugs = FunkenwerkAlarmManager.smartPlugsFlow.value
                    .ifEmpty { FunkenwerkAlarmManager.defaultSmartPlugs() }
                val preliminaryLocalPlugHit = matchSmartHomePlugsWithGroupsAndCompounds(
                    preliminaryPlugs,
                    FunkenwerkAlarmManager.smartGroupsFlow.value,
                    deviceQuery
                ).isNotEmpty()
                val syncStale = (System.currentTimeMillis() - FunkenwerkAlarmManager.lastPortalSmartHomeSyncMs) > 60_000L
                if (!preliminaryLocalPlugHit &&
                    syncStale &&
                    FunkenwerkAlarmManager.smartShuttersFlow.value.isEmpty() &&
                    FunkenwerkAlarmManager.smartGroupsFlow.value.isEmpty() &&
                    smartHomeExecutorOverride == null &&
                    smartHomeRelayExecutorOverride == null
                ) {
                    withTimeoutOrNull(1200L) {
                        FunkenwerkAlarmManager.refreshSmartHomeInventoryFromPortalSuspend(appCtx)
                    }
                }

                val savedDevices = FunkenwerkAlarmManager.smartPlugsFlow.value
                    .ifEmpty { FunkenwerkAlarmManager.defaultSmartPlugs() }
                val savedShutters = FunkenwerkAlarmManager.smartShuttersFlow.value
                val savedGroups = FunkenwerkAlarmManager.smartGroupsFlow.value

                val availableArr = JsonArray().apply {
                    savedDevices.forEach { add(it.name) }
                }
                val availableShuttersArr = JsonArray().apply {
                    savedShutters.forEach { add(it.label) }
                }
                val availableGroupsArr = JsonArray().apply {
                    savedGroups.forEach { add(it.name) }
                }

                val normalizedAction = when (rawAction) {
                    "on", "ein", "einschalten", "turn_on", "plug_on" -> "turn_on"
                    "off", "aus", "ausschalten", "turn_off", "plug_off" -> "turn_off"
                    "toggle", "umschalten", "wechseln", "plug_toggle" -> "toggle"
                    "all_on", "alle_an", "turn_all_on" -> "turn_all_on"
                    "all_off", "alle_aus", "turn_all_off" -> "turn_all_off"
                    "open", "up", "hoch", "auf", "oeffnen", "öffnen", "shutter_open" -> "shutter_open"
                    "close", "down", "runter", "zu", "schliessen", "schließen", "shutter_close" -> "shutter_close"
                    "stop", "halt", "stopp", "shutter_stop" -> "shutter_stop"
                    "my", "fav", "favorit", "shutter_my" -> "shutter_my"
                    "position", "set_position", "setclosure", "shutter_position" -> "shutter_position"
                    "group_open", "group_close", "group_stop", "group_my", "group_position", "group_on", "group_off", "group_toggle" -> rawAction
                    "list", "status", "devices", "list_devices" -> "list_devices"
                    else -> if (rawAction.isBlank()) "list_devices" else rawAction
                }

                if (normalizedAction == "list_devices") {
                    val detailArr = JsonArray()
                    savedDevices.forEach { d ->
                        detailArr.add(JsonObject().apply {
                            addProperty("name", d.name)
                            addProperty("ip", d.ip)
                            addProperty("room", d.room)
                            addProperty("model", d.model)
                        })
                    }
                    val shuttersDetailArr = JsonArray()
                    savedShutters.forEach { s ->
                        shuttersDetailArr.add(JsonObject().apply {
                            addProperty("label", s.label)
                            addProperty("device_url", s.deviceUrl)
                            addProperty("closure_percent", s.closurePercent)
                            addProperty("is_available", s.isAvailable)
                        })
                    }
                    val groupsDetailArr = JsonArray()
                    savedGroups.forEach { g ->
                        groupsDetailArr.add(JsonObject().apply {
                            addProperty("id", g.id)
                            addProperty("name", g.name)
                            add("shutter_targets", JsonArray().apply { g.shutterTargets.forEach { add(it) } })
                            add("plug_targets", JsonArray().apply { g.plugTargets.forEach { add(it) } })
                        })
                    }
                    val (tapoEmail, tapoPass) = FunkenwerkAlarmManager.getTapoCredentials(appCtx)
                    return JsonObject().apply {
                        addProperty("status", "success")
                        addProperty("action", "list_devices")
                        addProperty("credentials_configured", tapoEmail.isNotBlank() && tapoPass.isNotBlank())
                        add("available_devices", availableArr)
                        add("available_shutters", availableShuttersArr)
                        add("available_groups", availableGroupsArr)
                        add("devices", detailArr)
                        add("shutters", shuttersDetailArr)
                        add("groups", groupsDetailArr)
                    }
                }

                suspend fun runRelay(relayAct: String, relayTarget: String, relayPos: Int?): Pair<Boolean, String> {
                    return if (smartHomeRelayExecutorOverride != null) {
                        smartHomeRelayExecutorOverride(relayAct, relayTarget, relayPos)
                    } else {
                        FunkenwerkAlarmManager.dispatchSmartHomeRelayCommandSuspend(appCtx, relayAct, relayTarget, relayPos)
                    }
                }

                suspend fun runShutters(
                    shutterAct: String,
                    shutterTarget: String,
                    targets: List<SmartShutterDevice>,
                    pos: Int?
                ): Pair<Boolean, String> {
                    return if (smartHomeRelayExecutorOverride != null) {
                        smartHomeRelayExecutorOverride(shutterAct, shutterTarget, pos)
                    } else {
                        FunkenwerkAlarmManager.executeShuttersOrRelaySuspend(appCtx, shutterAct, shutterTarget, targets, pos)
                    }
                }

                suspend fun runPlugsLocal(plugAct: String, plugs: List<SmartPlugDevice>): Pair<Boolean, String> {
                    val ips = plugs.map { it.ip }
                    return if (smartHomeExecutorOverride != null) {
                        smartHomeExecutorOverride(plugAct, ips)
                    } else {
                        when (plugAct) {
                            "turn_on", "turn_all_on", "group_on" ->
                                FunkenwerkAlarmManager.triggerSmartPlugsSuspend(appCtx, ips, turnOn = true)
                            "turn_off", "turn_all_off", "group_off" ->
                                FunkenwerkAlarmManager.triggerSmartPlugsSuspend(appCtx, ips, turnOn = false)
                            else ->
                                FunkenwerkAlarmManager.toggleSmartPlugsSuspend(appCtx, ips)
                        }
                    }
                }

                val matchedGroup = findMatchingSmartHomeGroup(savedGroups, deviceQuery)
                val groupHasShutters = matchedGroup != null && matchedGroup.shutterTargets.isNotEmpty()
                val groupHasPlugs = matchedGroup != null && matchedGroup.plugTargets.isNotEmpty()
                val isPlugOnlyGroup = matchedGroup != null && groupHasPlugs && !groupHasShutters

                val groupResolvedShutters = if (matchedGroup != null && matchedGroup.shutterTargets.isNotEmpty()) {
                    val map = LinkedHashMap<String, SmartShutterDevice>()
                    for (st in matchedGroup.shutterTargets) {
                        matchSmartShutters(savedShutters, st).forEach { s -> map[s.deviceUrl.ifBlank { s.label }] = s }
                    }
                    map.values.toList()
                } else {
                    emptyList()
                }

                val matchedShuttersForQuery = if (matchedGroup != null) {
                    groupResolvedShutters
                } else {
                    matchSmartShutters(savedShutters, deviceQuery)
                }

                val matchedPlugsForQuery = matchSmartHomePlugsWithGroupsAndCompounds(savedDevices, savedGroups, deviceQuery)
                val looksLikeShutterRoomQuery = normalizeSmartHomeTargetKey(deviceQuery).let { nk ->
                    nk.contains("rolladen") || nk.contains("wohnzimmer") || nk.contains("terasse") ||
                        nk.contains("schlafzimmer") || nk.contains("connexoon") || nk.contains("somfy") ||
                        nk.contains("markise") || nk.contains("dachfenster")
                }
                val isExplicitShutterAction = normalizedAction.startsWith("shutter_")
                val isMixedCompoundQuery = matchedGroup == null &&
                    splitCompoundSmartHomeTargets(deviceQuery).size > 1 &&
                    matchedPlugsForQuery.isNotEmpty() &&
                    matchedShuttersForQuery.isNotEmpty()

                // 1. Handle Shutter, Shutter-Group, Hybrid-Group (Plugs + Shutters), or Mixed Compound commands!
                //    Note: Plug-only groups (e.g. 'Abendlicht' / 'Lichterketten') route directly to Step 2 even for group_on/group_off!
                if (!isPlugOnlyGroup && (
                        isExplicitShutterAction ||
                            groupHasShutters ||
                            isMixedCompoundQuery ||
                            (matchedGroup == null && matchedPlugsForQuery.isEmpty() && (matchedShuttersForQuery.isNotEmpty() || looksLikeShutterRoomQuery))
                        )
                ) {
                    val effectiveAction = when {
                        matchedGroup != null && normalizedAction in setOf("turn_on", "group_on", "shutter_open", "group_open") -> "group_open"
                        matchedGroup != null && normalizedAction in setOf("turn_off", "group_off", "shutter_close", "group_close") -> "group_close"
                        matchedGroup != null && normalizedAction in setOf("toggle", "group_toggle") -> {
                            val avgClosure = if (groupResolvedShutters.isNotEmpty()) groupResolvedShutters.map { it.closurePercent }.average() else 100.0
                            if (avgClosure >= 50.0) "group_open" else "group_close"
                        }
                        matchedGroup != null && normalizedAction.startsWith("shutter_") ->
                            normalizedAction.replaceFirst("shutter_", "group_")
                        normalizedAction == "turn_on" -> "shutter_open"
                        normalizedAction == "turn_off" -> "shutter_close"
                        normalizedAction == "toggle" -> {
                            val avgClosure = if (matchedShuttersForQuery.isNotEmpty()) matchedShuttersForQuery.map { it.closurePercent }.average() else 100.0
                            if (avgClosure >= 50.0) "shutter_open" else "shutter_close"
                        }
                        else -> normalizedAction
                    }

                    var localPlugSummary: String? = null
                    var localPlugOk = false
                    val plugsToAlsoSwitch = when {
                        groupHasPlugs -> matchedPlugsForQuery
                        isMixedCompoundQuery -> matchedPlugsForQuery
                        else -> emptyList()
                    }
                    if (plugsToAlsoSwitch.isNotEmpty() &&
                        normalizedAction in setOf("group_on", "group_off", "group_toggle", "turn_on", "turn_off", "toggle", "group_open", "group_close", "shutter_open", "shutter_close")
                    ) {
                        val plugAct = when (normalizedAction) {
                            "group_on", "turn_on", "group_open", "shutter_open" -> "turn_on"
                            "group_off", "turn_off", "group_close", "shutter_close" -> "turn_off"
                            else -> "toggle"
                        }
                        val (pOk, pMsg) = runPlugsLocal(plugAct, plugsToAlsoSwitch)
                        localPlugOk = pOk
                        localPlugSummary = pMsg
                    }

                    val shutterTargetName = matchedGroup?.name ?: deviceQuery.ifBlank { "alle" }
                    val effectivePos = if (effectiveAction in setOf("shutter_position", "group_position")) {
                        (positionPercent ?: 50).coerceIn(0, 100)
                    } else {
                        positionPercent
                    }

                    val (shutterOk, shutterMsg) = runShutters(effectiveAction, shutterTargetName, matchedShuttersForQuery, effectivePos)
                    val combinedOk = localPlugOk || shutterOk
                    val combinedMsg = listOfNotNull(
                        localPlugSummary?.takeIf { localPlugOk },
                        shutterMsg
                    ).joinToString(" · ").ifBlank { shutterMsg }

                    return JsonObject().apply {
                        addProperty("status", if (combinedOk) "success" else "error")
                        addProperty("action", effectiveAction)
                        addProperty("target", shutterTargetName)
                        if (effectivePos != null) addProperty("position_percent", effectivePos)
                        if (matchedGroup != null) addProperty("matched_group", matchedGroup.name)
                        if (plugsToAlsoSwitch.isNotEmpty()) {
                            add("switched_devices", JsonArray().apply { plugsToAlsoSwitch.forEach { add(it.name) } })
                        }
                        add("available_devices", availableArr)
                        add("available_shutters", availableShuttersArr)
                        add("available_groups", availableGroupsArr)
                        addProperty("message", combinedMsg)
                    }
                }

                // 2. Handle Smart Plugs & Lichterketten (single, 'alle Lichterketten', 'alle', compound 'Packtisch und Spiegel', or Plug-only Group!)
                val effectivePlugAction = when (normalizedAction) {
                    "group_on", "group_open" -> "turn_on"
                    "group_off", "group_close" -> "turn_off"
                    "group_toggle" -> "toggle"
                    else -> normalizedAction
                }
                val normTargetKey = normalizeSmartHomeTargetKey(deviceQuery)
                val isGlobalAllPlugs = normTargetKey.isEmpty() || normTargetKey in setOf(
                    "alle", "alles", "beide", "beiden", "alle steckdosen", "alle lichter", "alle lampen", "alle geraete", "ueberall", "komplett"
                )
                val targetDevices = when (effectivePlugAction) {
                    "turn_all_on", "turn_all_off" -> {
                        if (isGlobalAllPlugs) {
                            savedDevices
                        } else {
                            matchedPlugsForQuery.ifEmpty { savedDevices }
                        }
                    }
                    else -> if (isGlobalAllPlugs && deviceQuery.isNotBlank()) savedDevices else matchedPlugsForQuery
                }

                if (targetDevices.isEmpty()) {
                    return JsonObject().apply {
                        addProperty("status", "not_found")
                        addProperty("action", effectivePlugAction)
                        addProperty("requested_device", deviceQuery)
                        add("available_devices", availableArr)
                        add("available_shutters", availableShuttersArr)
                        add("available_groups", availableGroupsArr)
                        addProperty(
                            "message",
                            "Kein Smart-Home-Gerät oder keine Gruppe passend zu '$deviceQuery' gefunden. Lichterketten & Steckdosen: ${savedDevices.joinToString(", ") { it.name }}." +
                                (if (savedShutters.isNotEmpty()) " Rollläden: ${savedShutters.joinToString(", ") { it.label }}." else "") +
                                (if (savedGroups.isNotEmpty()) " Gruppen: ${savedGroups.joinToString(", ") { it.name }}." else "")
                        )
                    }
                }

                val switchedNames = JsonArray().apply {
                    targetDevices.forEach { add(it.name) }
                }

                var (anySuccess, summary) = runPlugsLocal(effectivePlugAction, targetDevices)

                // Remote out-of-home fallback: if local Wi-Fi KLAP failed and no test override was injected, relay via Portal to PC Funkenwerk SmartHome!
                if (!anySuccess && smartHomeExecutorOverride == null) {
                    val relayPlugAction = when (effectivePlugAction) {
                        "turn_on", "turn_all_on" -> if (matchedGroup != null) "group_on" else "plug_on"
                        "turn_off", "turn_all_off" -> if (matchedGroup != null) "group_off" else "plug_off"
                        else -> "plug_toggle"
                    }
                    val relayTarget = matchedGroup?.name ?: if (effectivePlugAction.contains("_all_") && isGlobalAllPlugs) "alle" else deviceQuery
                    val (rOk, rMsg) = runRelay(relayPlugAction, relayTarget, null)
                    if (rOk) {
                        anySuccess = true
                        summary = rMsg
                    }
                }

                val desiredStateLabel = when (effectivePlugAction) {
                    "turn_on", "turn_all_on" -> "on"
                    "turn_off", "turn_all_off" -> "off"
                    else -> "toggled"
                }

                JsonObject().apply {
                    addProperty("status", if (anySuccess) "success" else "error")
                    addProperty("action", effectivePlugAction)
                    addProperty("new_state", desiredStateLabel)
                    if (matchedGroup != null) addProperty("matched_group", matchedGroup.name)
                    add("switched_devices", switchedNames)
                    add("available_devices", availableArr)
                    add("available_shutters", availableShuttersArr)
                    add("available_groups", availableGroupsArr)
                    addProperty("message", summary)
                }
            }

            else -> JsonObject().apply {
                addProperty("status", "error")
                addProperty("message", "Unbekanntes lokales Tool: $toolName")
            }
        }
    }

    private fun JsonObject.optString(key: String): String {
        val el = this.get(key) ?: return ""
        return if (el.isJsonPrimitive) el.asString else ""
    }

    private fun JsonObject.optInt(key: String, default: Int): Int {
        val el = this.get(key) ?: return default
        return try {
            if (el.isJsonPrimitive) el.asInt else default
        } catch (_: Throwable) {
            default
        }
    }

    private fun JsonObject.optNullableBoolean(key: String): Boolean? {
        val el = this.get(key) ?: return null
        return try {
            if (el.isJsonPrimitive) el.asBoolean else null
        } catch (_: Throwable) {
            null
        }
    }

    private fun JsonObject.optStringList(key: String): List<String> {
        val el = this.get(key) ?: return emptyList()
        if (!el.isJsonArray) return emptyList()
        val out = mutableListOf<String>()
        el.asJsonArray.forEach { item ->
            if (item != null && item.isJsonPrimitive) {
                val s = item.asString.trim()
                if (s.isNotEmpty()) out.add(s)
            }
        }
        return out
    }
}
