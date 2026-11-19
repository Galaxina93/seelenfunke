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
import java.util.Locale

data class FalconMusicMatchResult(
    val tracks: List<MusicTrackDto>,
    val playlistId: String,
    val playlistName: String?,
    val shuffle: Boolean,
    val usedFallbackFavorites: Boolean
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
    val farewellHardCeilingMs: Long = FAREWELL_HARD_CEILING_MS
) {
    companion object {
        const val STAGE1_SILENCE_MS = 120_000L
        const val STAGE1_QUESTION_MAX_WAIT_MS = 6_000L
        const val STAGE2_FOLLOWUP_SILENCE_MS = 25_000L
        const val FAREWELL_POST_SPEECH_BUFFER_MS = 1_200L
        const val FAREWELL_HARD_CEILING_MS = 7_000L
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
        if (stage == FalconInactivityStage.FAREWELL_CLOSING || stage == FalconInactivityStage.DISCONNECTED) {
            return
        }
        stage = FalconInactivityStage.ACTIVE
        inactivityCheckSent = false
        inactivityFollowupStartedMs = 0L
        stage1SpeechObserved = false
        stage1QuestionFinishedMs = 0L
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
        activeToolCalls: Int = 0
    ): FalconInactivityAction {
        if (!initialized) {
            initialized = true
            lastInteractionMs = nowMs
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
                if (isAiSpeaking) {
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
                if (isAiSpeaking) {
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
                if (isAiSpeaking) {
                    farewellSpeechObserved = true
                    farewellSpeechEndedMs = 0L
                } else if (farewellSpeechObserved && farewellSpeechEndedMs == 0L) {
                    farewellSpeechEndedMs = nowMs
                }

                val finishedSpeechWithBuffer = farewellSpeechObserved &&
                    !isAiSpeaking &&
                    farewellSpeechEndedMs > 0L &&
                    (nowMs - farewellSpeechEndedMs >= farewellPostSpeechBufferMs)
                val hitHardCeiling = (nowMs - farewellSentAtMs >= farewellHardCeilingMs)

                if (finishedSpeechWithBuffer || hitHardCeiling) {
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
        "[SYSTEM-INAKTIVITÄT 2 MINUTEN: Alina hat seit 2 Minuten nichts gesagt. Frage sie jetzt in 1 kurzen, freundlichen Satz auf Deutsch, ob sie gerade noch etwas braucht oder ob du die Verbindung schließen sollst.]"

    const val STAGE2_AUTO_END_PROMPT =
        "[SYSTEM-AUTO-ENDE: Keine Antwort erhalten. Verabschiede dich jetzt in 1 kurzen Satz freundlich auf Deutsch (z. B. dass du die Verbindung schließt und auf 'Hey Falcone' wartest) und rufe end_call auf.]"

    val SPORT_KEYWORDS = listOf(
        "sport", "workout", "fitness", "gym", "power",
        "training", "energy", "cardio", "laufen", "motivation", "hype", "pump"
    )

    val VALID_EMOTIONS = setOf(
        "idle", "listening", "thinking", "speaking", "happy", "proud", "focused"
    )

    val LOCAL_TOOL_NAMES = setOf(
        "falcon_music_control",
        "falcon_camera_control",
        "falcon_smarthome_control",
        "falcon_send_to_antigravity",
        "falcon_resolve_antigravity_blockade",
        "falcon_check_antigravity_status",
        "falcon_app_navigate",
        "falcon_set_emotion"
    )

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

    fun shouldEnterStandbyAfterTool(toolName: String?): Boolean {
        val clean = toolName?.trim()?.lowercase(Locale.ROOT) ?: return false
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

    fun buildAttachedMediaSystemDirective(staged: PreparedAgAttachment?): String? {
        if (staged == null) return null
        return "WICHTIGER APP-STATUS: Alina hat BEREITS ein Foto/Screenshot in der App hinterlegt ('${staged.fileName}')! " +
            "Frage NICHT, ob du ein Foto machen sollst, und rufe NIEMALS falcon_camera_control / take_photo / Kamera-Aufnahme auf, " +
            "solange sie nicht ausdrücklich 'mach ein neues Foto' verlangt! Nutze direkt das bereits hinterlegte Bild " +
            "(beim Senden an Antigravity wird es automatisch angehängt)."
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

    fun matchMusicForFalcon(
        playlists: List<MusicPlaylistDto>,
        allTracks: List<MusicTrackDto>,
        action: String,
        query: String?
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
        val treatAsSport = cleanAction == "play_sport" || isSportQuery(cleanQuery)

        if (treatAsSport) {
            val sportPlaylists = visiblePlaylists.filter { pl ->
                val hay = "${pl.name} ${pl.description}".lowercase(Locale.ROOT)
                SPORT_KEYWORDS.any { kw -> hay.contains(kw) } && pl.tracks.isNotEmpty()
            }
            if (sportPlaylists.isNotEmpty()) {
                val chosenPl = sportPlaylists.first()
                return FalconMusicMatchResult(
                    tracks = chosenPl.tracks,
                    playlistId = chosenPl.id,
                    playlistName = chosenPl.name,
                    shuffle = true,
                    usedFallbackFavorites = false
                )
            }

            val sportTracks = consolidatedTracks.filter { tr ->
                val hay = "${tr.title} ${tr.artist}".lowercase(Locale.ROOT)
                SPORT_KEYWORDS.any { kw -> hay.contains(kw) }
            }
            if (sportTracks.isNotEmpty()) {
                return FalconMusicMatchResult(
                    tracks = sportTracks,
                    playlistId = "all",
                    playlistName = "Sport-Auswahl",
                    shuffle = true,
                    usedFallbackFavorites = false
                )
            }
        } else if (cleanQuery.isNotEmpty()) {
            val tokens = cleanQuery
                .lowercase(Locale.ROOT)
                .split(Regex("\\s+"))
                .filter { it.length >= 2 }
                .ifEmpty { listOf(cleanQuery.lowercase(Locale.ROOT)) }

            val matchedPl = visiblePlaylists.firstOrNull { pl ->
                val hay = "${pl.name} ${pl.description}".lowercase(Locale.ROOT)
                pl.tracks.isNotEmpty() && (hay.contains(cleanQuery.lowercase(Locale.ROOT)) || tokens.all { hay.contains(it) })
            }
            if (matchedPl != null) {
                return FalconMusicMatchResult(
                    tracks = matchedPl.tracks,
                    playlistId = matchedPl.id,
                    playlistName = matchedPl.name,
                    shuffle = false,
                    usedFallbackFavorites = false
                )
            }

            val matchedTracks = consolidatedTracks.filter { tr ->
                val hay = "${tr.title} ${tr.artist}".lowercase(Locale.ROOT)
                hay.contains(cleanQuery.lowercase(Locale.ROOT)) || tokens.all { hay.contains(it) }
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

        // Guaranteed immediate music fallback: Shuffle over favorites (or full library)
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
                    "Steuert sofort den lokalen Musikplayer auf Alinas Smartphone (Sportmusik, Playlist/Song-Suche, Pause, Weiter, Zurück, Shuffle, Lautstärke). Bestätige danach nur ultrakurz ohne Gegenfrage, da Falcone direkt in den Ruhemodus wechselt."
                )
                add("parameters", JsonObject().apply {
                    addProperty("type", "OBJECT")
                    add("properties", JsonObject().apply {
                        add("action", JsonObject().apply {
                            addProperty("type", "STRING")
                            addProperty(
                                "description",
                                "Aktion: play_sport, play_query, pause, resume, next, previous, shuffle, set_volume"
                            )
                        })
                        add("query", JsonObject().apply {
                            addProperty("type", "STRING")
                            addProperty("description", "Optionaler Suchbegriff für Playlist, Genre, Song oder Künstler.")
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
                    "Steuert Falcones Kamera-Augen auf dem Smartphone (Live-Sicht ein-/ausschalten, Kamera wechseln oder scharfes hochauflösendes Foto schießen). WICHTIG: Rufe NIEMALS take_photo oder enable_vision auf, wenn Alina bereits ein Foto/Screenshot in der App hinterlegt hat, außer sie verlangt ausdrücklich ein NEUES Kamerafoto (force_new_photo=true) oder Live-Kamera (force_live_vision=true)!"
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
                    "Steuert sofort Alinas lokale Smart-Home-Steckdosen und Lichterketten im Heim-WLAN per KLAP v2 (z. B. Fußleisten Lichterkette, Spiegel Lichterkette, Packtisch Warm Licht)."
                )
                add("parameters", JsonObject().apply {
                    addProperty("type", "OBJECT")
                    add("properties", JsonObject().apply {
                        add("action", JsonObject().apply {
                            addProperty("type", "STRING")
                            addProperty(
                                "description",
                                "Aktion: turn_on, turn_off, toggle, turn_all_on, turn_all_off, list_devices"
                            )
                        })
                        add("device_name", JsonObject().apply {
                            addProperty("type", "STRING")
                            addProperty(
                                "description",
                                "Optionaler Gerätename wie Fußleisten Lichterkette, Spiegel Lichterkette, Packtisch oder Lichterkette."
                            )
                        })
                    })
                    add("required", JsonArray().apply { add("action") })
                })
            })
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
                                "Optionaler Ziel-Kanal: 'leitung' für abteilungsübergreifende Themen (Standard), 'shop' für Shop/Produkte/Konfigurator, 'android' für Android-App/Widgets, 'minipc' für Mini-PC/Laser/3D-Stadt Funkenwerk, 'buchhaltung' für Buchhaltung/Belege/Marketing, 'new' wenn Alina explizit einen ganz neuen Einzel-Chat wünscht."
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
                                "Optionaler Ziel-Kanal: 'leitung' für abteilungsübergreifende Themen (Standard), 'shop' für Shop/Produkte/Konfigurator, 'android' für Android-App/Widgets, 'minipc' für Mini-PC/Laser/3D-Stadt Funkenwerk, 'buchhaltung' für Buchhaltung/Belege/Marketing, 'new' wenn Alina explizit einen ganz neuen Einzel-Chat wünscht."
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

        if ("falcon_set_emotion" !in existing) {
            decls.add(JsonObject().apply {
                addProperty("name", "falcon_set_emotion")
                addProperty(
                    "description",
                    "Setzt Falcones 3D-Gesichtsausdruck und Körperhaltung passend zur Gesprächssituation."
                )
                add("parameters", JsonObject().apply {
                    addProperty("type", "OBJECT")
                    add("properties", JsonObject().apply {
                        add("emotion", JsonObject().apply {
                            addProperty("type", "STRING")
                            addProperty("description", "Emotion: idle, listening, thinking, speaking, happy, proud, focused")
                        })
                    })
                    add("required", JsonArray().apply { add("emotion") })
                })
            })
        }
    }

    suspend fun executeLocalFalconTool(
        context: Context,
        toolName: String,
        argsElement: JsonElement?,
        chatSessionId: String,
        onEmotionChanged: (String) -> Unit,
        onNavigate: (FalconResolvedDestination) -> Unit,
        agApiOverride: AgDirectApi? = null,
        smartHomeExecutorOverride: (suspend (action: String, ips: List<String>) -> Pair<Boolean, String>)? = null
    ): JsonObject {
        val appCtx = context.applicationContext
        val args = if (argsElement != null && argsElement.isJsonObject) argsElement.asJsonObject else JsonObject()

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
                val action = args.optString("action").ifBlank { "resume" }.lowercase(Locale.ROOT)
                val query = args.optString("query")
                val volPercent = args.optInt("volume_percent", -1)

                withContext(Dispatchers.Main.immediate) {
                    MusicPlayerManager.ensureCatalogLoadedFast(appCtx)
                }

                val musicRes = when (action) {
                    "pause", "stop" -> {
                        withContext(Dispatchers.Main.immediate) {
                            MusicPlayerManager.pauseIfPlaying(appCtx)
                        }
                        JsonObject().apply {
                            addProperty("status", "success")
                            addProperty("action", "pause")
                            addProperty("message", "Musik pausiert.")
                        }
                    }

                    "resume", "play" -> {
                        withContext(Dispatchers.Main.immediate) {
                            MusicPlayerManager.resumeOrStart(appCtx)
                        }
                        JsonObject().apply {
                            addProperty("status", "success")
                            addProperty("action", "resume")
                            addProperty("track", MusicPlayerManager.currentTrack.value?.title ?: "")
                        }
                    }

                    "next", "skip" -> {
                        withContext(Dispatchers.Main.immediate) {
                            MusicPlayerManager.playNext(appCtx)
                        }
                        JsonObject().apply {
                            addProperty("status", "success")
                            addProperty("action", "next")
                            addProperty("track", MusicPlayerManager.currentTrack.value?.title ?: "")
                        }
                    }

                    "previous", "prev", "back" -> {
                        withContext(Dispatchers.Main.immediate) {
                            MusicPlayerManager.playPrevious(appCtx)
                        }
                        JsonObject().apply {
                            addProperty("status", "success")
                            addProperty("action", "previous")
                            addProperty("track", MusicPlayerManager.currentTrack.value?.title ?: "")
                        }
                    }

                    "shuffle" -> {
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

                    "set_volume", "volume" -> {
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

                    else -> {
                        // play_sport or play_query
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

                        val match = matchMusicForFalcon(playlists, allTracks, action, query)
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
                        val started = FalconCameraController.startVision(appCtx, lens ?: "back")
                        JsonObject().apply {
                            addProperty("status", if (started) "success" else "error")
                            addProperty("vision_active", started)
                            addProperty("lens", FalconCameraController.activeLens.value)
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
                val mode = if (isVoiceDirect || rawMode.trim().lowercase(Locale.ROOT) == "direct") {
                    "direct"
                } else {
                    AgDirectInboxManager.normalizeExecutionMode(rawMode)
                }
                val channel = AgDirectInboxManager.normalizeChannel(args.optString("channel"))
                val explicitAttach = args.optNullableBoolean("attach_captured_photo")

                val stagedAttachment = AgDirectInboxManager.getActiveStagedAttachment()
                val currentPhoto = FalconCameraController.lastCapturedPhoto.value
                val preparedAttachment = when {
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
                withTimeoutOrNull(4500L) {
                    AgDirectInboxManager.syncAll(appCtx, apiOverride = agApiOverride)
                } ?: AgDirectInboxManager.hydrateLocalState(appCtx)

                val recent = AgDirectInboxManager.itemsFlow.value.take(5)
                val itemsArr = JsonArray()
                recent.forEach { item ->
                    itemsArr.add(JsonObject().apply {
                        addProperty("id", item.id)
                        addProperty("title", item.title)
                        addProperty("status", item.status.badgeLabel)
                        addProperty("status_code", item.status.code)
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
                    addProperty("pending_blockades_count", AgDirectInboxManager.pendingBlockadesFlow.value.size)
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

            "falcon_smarthome_control" -> {
                val rawAction = args.optString("action").trim().lowercase(Locale.ROOT)
                val deviceQuery = args.optString("device_name").trim()

                FunkenwerkAlarmManager.ensureInitialized(appCtx)
                val savedDevices = FunkenwerkAlarmManager.smartPlugsFlow.value
                    .ifEmpty { FunkenwerkAlarmManager.defaultSmartPlugs() }
                val availableArr = JsonArray().apply {
                    savedDevices.forEach { add(it.name) }
                }

                val normalizedAction = when (rawAction) {
                    "on", "ein", "einschalten", "turn_on" -> "turn_on"
                    "off", "aus", "ausschalten", "turn_off" -> "turn_off"
                    "toggle", "umschalten", "wechseln" -> "toggle"
                    "all_on", "alle_an", "turn_all_on" -> "turn_all_on"
                    "all_off", "alle_aus", "turn_all_off" -> "turn_all_off"
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
                    val (tapoEmail, tapoPass) = FunkenwerkAlarmManager.getTapoCredentials(appCtx)
                    return JsonObject().apply {
                        addProperty("status", "success")
                        addProperty("action", "list_devices")
                        addProperty("credentials_configured", tapoEmail.isNotBlank() && tapoPass.isNotBlank())
                        add("available_devices", availableArr)
                        add("devices", detailArr)
                    }
                }

                val targetDevices = when (normalizedAction) {
                    "turn_all_on", "turn_all_off" -> savedDevices
                    else -> matchSmartHomeDevices(savedDevices, deviceQuery)
                }

                if (targetDevices.isEmpty()) {
                    return JsonObject().apply {
                        addProperty("status", "not_found")
                        addProperty("action", normalizedAction)
                        addProperty("requested_device", deviceQuery)
                        add("available_devices", availableArr)
                        addProperty(
                            "message",
                            "Kein Smart-Home-Gerät passend zu '$deviceQuery' gefunden. Verfügbare Geräte: ${savedDevices.joinToString(", ") { it.name }}."
                        )
                    }
                }

                val targetIps = targetDevices.map { it.ip }
                val switchedNames = JsonArray().apply {
                    targetDevices.forEach { add(it.name) }
                }

                val (anySuccess, summary) = if (smartHomeExecutorOverride != null) {
                    smartHomeExecutorOverride(normalizedAction, targetIps)
                } else {
                    when (normalizedAction) {
                        "turn_on", "turn_all_on" ->
                            FunkenwerkAlarmManager.triggerSmartPlugsSuspend(appCtx, targetIps, turnOn = true)
                        "turn_off", "turn_all_off" ->
                            FunkenwerkAlarmManager.triggerSmartPlugsSuspend(appCtx, targetIps, turnOn = false)
                        "toggle" ->
                            FunkenwerkAlarmManager.toggleSmartPlugsSuspend(appCtx, targetIps)
                        else ->
                            Pair(false, "Unbekannte Smart-Home-Aktion: $normalizedAction")
                    }
                }

                val desiredStateLabel = when (normalizedAction) {
                    "turn_on", "turn_all_on" -> "on"
                    "turn_off", "turn_all_off" -> "off"
                    else -> "toggled"
                }

                JsonObject().apply {
                    addProperty("status", if (anySuccess) "success" else "error")
                    addProperty("action", normalizedAction)
                    addProperty("new_state", desiredStateLabel)
                    add("switched_devices", switchedNames)
                    add("available_devices", availableArr)
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
