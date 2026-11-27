package de.meinseelenfunke.app.util

import android.content.Context
import android.content.SharedPreferences
import com.google.gson.Gson
import com.google.gson.annotations.SerializedName
import com.google.gson.reflect.TypeToken
import de.meinseelenfunke.app.di.ServiceLocator
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import java.time.Instant
import java.time.ZoneId
import java.time.format.DateTimeFormatter
import java.util.Locale
import java.util.UUID

data class FunkiraMemoryTurn(
    @SerializedName("id") val id: String = UUID.randomUUID().toString(),
    @SerializedName("role") val role: String = "user",
    @SerializedName("mode") val mode: String = "live_call",
    @SerializedName("body") val body: String = "",
    @SerializedName("created_at_ms") val createdAtMs: Long = System.currentTimeMillis(),
    @SerializedName("time_short") val timeShort: String = ""
)

data class FunkiraMemorySnapshot(
    val turns: List<FunkiraMemoryTurn> = emptyList(),
    val lastTopicSummary: String = "",
    val rollingDigest: String = "",
    val memoryCount: Int = 0
)

/**
 * Encrypted local short-term conversational brain for Funkira ("Lokales Gehirn").
 * Mirrors EloCrest's cross-restart memory cache + noise-gate + rolling digest compaction
 * using AndroidKeyStore AES-256-GCM via ServiceLocator.
 */
object FunkiraLocalMemoryStore {
    const val PREFS_NAME = "seelenfunke_funkira_memory_v1"
    private const val KEY_TURNS_JSON = "enc_turns_json"
    private const val KEY_LAST_TOPIC = "enc_last_topic"
    private const val KEY_ROLLING_DIGEST = "enc_rolling_digest"
    private const val KEY_TOTAL_COUNT = "memory_total_count"

    const val MAX_RAW_TURNS = 30
    const val HOT_WINDOW_TURNS = 12
    const val MAX_HOT_TURN_CHARS = 260
    const val MAX_DIGEST_FACTS = 8
    const val MAX_DIGEST_CHARS = 600
    const val MAX_MEMORY_PROMPT_CHARS = 3600
    const val DEDUP_WINDOW_MS = 15_000L

    private val NOISE_ACK_SET = setOf(
        "ok", "okay", "ja", "nein", "hm", "hmm", "gut", "super", "danke", "passt", "alles klar", "erledigt"
    )
    private val HIGH_SIGNAL_REGEX = Regex(
        "\\b(deadline|termin|mittwald|laser|xtool|beleg|rechnung|bestellung|preis|rabatt|darkmode|wake|foto|bild|paket|konzept|wichtig|immer|nie|regel|tonfall|alina|funkira|antigravity|smarthome|rolladen)\\b",
        RegexOption.IGNORE_CASE
    )
    private val TIME_FMT = DateTimeFormatter.ofPattern("dd.MM. HH:mm", Locale.GERMANY)

    private val gson = Gson()
    private val memoryLock = Any()

    private val _snapshotFlow = MutableStateFlow(FunkiraMemorySnapshot())
    val snapshotFlow: StateFlow<FunkiraMemorySnapshot> = _snapshotFlow.asStateFlow()

    private val _turnsFlow = MutableStateFlow<List<FunkiraMemoryTurn>>(emptyList())
    val turnsFlow: StateFlow<List<FunkiraMemoryTurn>> = _turnsFlow.asStateFlow()

    private val _memoryCountFlow = MutableStateFlow(0)
    val memoryCountFlow: StateFlow<Int> = _memoryCountFlow.asStateFlow()

    private val _lastTopicFlow = MutableStateFlow("")
    val lastTopicFlow: StateFlow<String> = _lastTopicFlow.asStateFlow()

    private fun prefs(context: Context): SharedPreferences {
        return context.applicationContext.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
    }

    fun hydrate(context: Context): FunkiraMemorySnapshot {
        val appCtx = context.applicationContext
        ServiceLocator.ensureInitialized(appCtx)
        return synchronized(memoryLock) {
            val p = prefs(appCtx)
            val turns = decodeTurns(ServiceLocator.getDecryptedPref(p, KEY_TURNS_JSON, "") ?: "")
            val storedTopic = ServiceLocator.getDecryptedPref(p, KEY_LAST_TOPIC, "") ?: ""
            val digest = ServiceLocator.getDecryptedPref(p, KEY_ROLLING_DIGEST, "") ?: ""
            val totalCount = maxOf(turns.size, p.getInt(KEY_TOTAL_COUNT, turns.size))
            val resolvedTopic = storedTopic.ifBlank { computeLastTopicSummary(turns) }
            val snap = FunkiraMemorySnapshot(
                turns = turns,
                lastTopicSummary = resolvedTopic,
                rollingDigest = digest,
                memoryCount = totalCount
            )
            publishSnapshot(snap)
            snap
        }
    }

    fun recordTurn(
        context: Context,
        role: String,
        body: String,
        mode: String = "live_call",
        nowMs: Long = System.currentTimeMillis()
    ): Boolean {
        val appCtx = context.applicationContext
        ServiceLocator.ensureInitialized(appCtx)

        val cleanRole = if (role.trim().lowercase(Locale.ROOT) in setOf("user", "alina")) "user" else "funkira"
        val cleanMode = mode.trim().lowercase(Locale.ROOT).take(30).ifBlank { "live_call" }
        val cleanBody = sanitizeBody(body)
        if (cleanBody.isBlank()) return false

        // Layer 3 Write-Time Noise-Gate against single-word filler/ack bloat
        val lowNorm = cleanBody.lowercase(Locale.GERMAN)
            .replace(Regex("[^\\p{L}\\p{N}\\s]+"), "")
            .replace(Regex("\\s+"), " ")
            .trim()
        if (lowNorm.length < 12 && lowNorm in NOISE_ACK_SET) {
            return false
        }

        synchronized(memoryLock) {
            val p = prefs(appCtx)
            val currentTurns = decodeTurns(ServiceLocator.getDecryptedPref(p, KEY_TURNS_JSON, "") ?: "").toMutableList()
            val lastSameRole = currentTurns.lastOrNull { it.role == cleanRole }
            if (lastSameRole != null &&
                lastSameRole.body == cleanBody &&
                (nowMs - lastSameRole.createdAtMs) in 0L..DEDUP_WINDOW_MS
            ) {
                return false
            }

            val timeShort = try {
                TIME_FMT.format(Instant.ofEpochMilli(nowMs).atZone(ZoneId.systemDefault()))
            } catch (_: Throwable) {
                ""
            }

            currentTurns.add(
                FunkiraMemoryTurn(
                    id = UUID.randomUUID().toString(),
                    role = cleanRole,
                    mode = cleanMode,
                    body = cleanBody,
                    createdAtMs = nowMs,
                    timeShort = timeShort
                )
            )

            var digest = ServiceLocator.getDecryptedPref(p, KEY_ROLLING_DIGEST, "") ?: ""
            if (currentTurns.size > MAX_RAW_TURNS) {
                val overflowCount = currentTurns.size - MAX_RAW_TURNS
                val evicted = currentTurns.take(overflowCount)
                repeat(overflowCount) { currentTurns.removeAt(0) }
                digest = foldEvictedTurnsIntoDigest(digest, evicted)
            }

            val lastTopic = computeLastTopicSummary(currentTurns)
            val nextTotal = maxOf(currentTurns.size, p.getInt(KEY_TOTAL_COUNT, 0) + 1)

            ServiceLocator.putEncryptedPref(p, KEY_TURNS_JSON, gson.toJson(currentTurns))
            ServiceLocator.putEncryptedPref(p, KEY_LAST_TOPIC, lastTopic)
            if (digest.isNotBlank()) {
                ServiceLocator.putEncryptedPref(p, KEY_ROLLING_DIGEST, digest)
            }
            p.edit().putInt(KEY_TOTAL_COUNT, nextTotal).apply()

            publishSnapshot(
                FunkiraMemorySnapshot(
                    turns = currentTurns.toList(),
                    lastTopicSummary = lastTopic,
                    rollingDigest = digest,
                    memoryCount = nextTotal
                )
            )
            return true
        }
    }

    fun clearMemory(context: Context) {
        val appCtx = context.applicationContext
        ServiceLocator.ensureInitialized(appCtx)
        synchronized(memoryLock) {
            prefs(appCtx).edit().clear().apply()
            publishSnapshot(FunkiraMemorySnapshot())
        }
    }

    fun buildMemoryContextPromptDirective(context: Context, maxTurns: Int = HOT_WINDOW_TURNS): String {
        val snap = hydrate(context)
        if (snap.turns.isEmpty() && snap.rollingDigest.isBlank()) return ""

        val header = buildString {
            append("--- LOKALES KURZZEITGEDAECHTNIS (ANDROID BRAIN) ---\n")
            if (snap.lastTopicSummary.isNotBlank()) {
                append("LETZTES THEMA: ").append(snap.lastTopicSummary.take(280)).append('\n')
            }
            if (snap.rollingDigest.isNotBlank()) {
                append("GEMERKTE KERNFAKTEN: ").append(snap.rollingDigest.take(MAX_DIGEST_CHARS)).append('\n')
            }
        }
        val footer = buildString {
            append("--- ENDE LOKALES KURZZEITGEDAECHTNIS ---\n")
            append("Setze das Gespraech nahtlos fort und beziehe dich bei Rueckfragen auf dieses Gedaechtnis.")
        }

        val tail = snap.turns.takeLast(maxTurns.coerceIn(1, MAX_RAW_TURNS))
        val selectedLines = ArrayList<String>(tail.size)
        if (tail.isNotEmpty()) {
            val sectionHeader = "LETZTER VERLAUF:\n"
            val remainingBudget = (MAX_MEMORY_PROMPT_CHARS - header.length - sectionHeader.length - footer.length).coerceAtLeast(0)
            var used = 0
            for (t in tail.asReversed()) {
                val speaker = if (t.role == "user") "Alina" else "Funkira"
                val prefix = if (t.timeShort.isNotBlank()) "[${t.timeShort}] $speaker" else speaker
                val rawLine = "$prefix: ${t.body.take(MAX_HOT_TURN_CHARS)}\n"
                if (used + rawLine.length <= remainingBudget) {
                    selectedLines.add(rawLine)
                    used += rawLine.length
                } else if (selectedLines.isEmpty() && remainingBudget > 24) {
                    val maxBodyLen = (remainingBudget - "$prefix: \n".length).coerceAtLeast(16)
                    selectedLines.add("$prefix: ${t.body.take(maxBodyLen)}\n")
                    break
                } else {
                    break
                }
            }
            selectedLines.reverse()
        }

        val assembled = buildString {
            append(header)
            if (selectedLines.isNotEmpty()) {
                append("LETZTER VERLAUF:\n")
                selectedLines.forEach { append(it) }
            }
            append(footer)
        }
        return if (assembled.length <= MAX_MEMORY_PROMPT_CHARS) assembled else assembled.take(MAX_MEMORY_PROMPT_CHARS)
    }

    internal fun sanitizeBody(raw: String): String {
        return raw
            .replace(" \u2013 ", ", ")
            .replace(" \u2014 ", ", ")
            .replace(Regex("\\s+"), " ")
            .trim()
            .take(1200)
    }

    internal fun computeLastTopicSummary(turns: List<FunkiraMemoryTurn>): String {
        if (turns.isEmpty()) return ""
        val lastUser = turns.lastOrNull { it.role == "user" }
        val lastAi = turns.lastOrNull { it.role != "user" }
        return buildString {
            lastUser?.let { append("Alina: ").append(it.body.take(160)) }
            if (lastUser != null && lastAi != null) append(" | ")
            lastAi?.let { append("Funkira: ").append(it.body.take(160)) }
        }.take(340)
    }

    private fun foldEvictedTurnsIntoDigest(existingDigest: String, evicted: List<FunkiraMemoryTurn>): String {
        val facts = existingDigest
            .split(" | ")
            .map { it.trim() }
            .filter { it.isNotEmpty() }
            .toMutableList()

        for (row in evicted) {
            if (row.role != "user") continue
            val txt = row.body.replace(Regex("\\s+"), " ").trim()
            if (txt.length < 18) continue
            if (HIGH_SIGNAL_REGEX.containsMatchIn(txt) || facts.size < 3) {
                val snippet = txt.take(85)
                if (snippet !in facts) {
                    facts.add(snippet)
                }
            }
        }
        return facts.takeLast(MAX_DIGEST_FACTS).joinToString(" | ").take(MAX_DIGEST_CHARS)
    }

    private fun decodeTurns(rawJson: String): List<FunkiraMemoryTurn> {
        if (rawJson.isBlank()) return emptyList()
        return try {
            val type = object : TypeToken<List<FunkiraMemoryTurn>>() {}.type
            gson.fromJson<List<FunkiraMemoryTurn>>(rawJson, type).orEmpty()
        } catch (_: Throwable) {
            emptyList()
        }
    }

    private fun publishSnapshot(snap: FunkiraMemorySnapshot) {
        _snapshotFlow.value = snap
        _turnsFlow.value = snap.turns
        _memoryCountFlow.value = snap.memoryCount
        _lastTopicFlow.value = snap.lastTopicSummary
    }
}
