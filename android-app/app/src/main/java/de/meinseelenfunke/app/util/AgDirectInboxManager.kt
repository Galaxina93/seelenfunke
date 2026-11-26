package de.meinseelenfunke.app.util

import android.content.Context
import android.graphics.Bitmap
import android.graphics.BitmapFactory
import android.graphics.Matrix
import android.media.ExifInterface
import android.net.Uri
import android.provider.OpenableColumns
import android.util.LruCache
import androidx.compose.ui.graphics.ImageBitmap
import androidx.compose.ui.graphics.asImageBitmap
import com.google.gson.Gson
import com.google.gson.annotations.SerializedName
import com.google.gson.reflect.TypeToken
import de.meinseelenfunke.app.data.api.AgDirectApi
import de.meinseelenfunke.app.data.api.AgDirectCountsDto
import de.meinseelenfunke.app.data.api.AgDirectCreateRequest
import de.meinseelenfunke.app.data.api.AgDirectInboxItemDto
import de.meinseelenfunke.app.data.api.AgDirectResolveRequest
import de.meinseelenfunke.app.di.ServiceLocator
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.Job
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.MutableSharedFlow
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.SharedFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asSharedFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.isActive
import kotlinx.coroutines.launch
import kotlinx.coroutines.sync.Mutex
import kotlinx.coroutines.sync.withLock
import kotlinx.coroutines.withContext
import android.os.SystemClock
import java.io.ByteArrayOutputStream
import java.io.File
import java.time.Instant
import java.util.Base64
import java.util.Locale
import java.util.UUID
import java.util.concurrent.atomic.AtomicInteger
import kotlin.math.max
import kotlin.math.roundToInt

enum class AgDirectStatus(val code: String, val badgeLabel: String) {
    QUEUED("queued", "Wartet"),
    PENDING("pending", "Gesendet"),
    DELIVERED("delivered", "Bei Antigravity"),
    AWAITING_GO("awaiting_go", "Go / Antwort fehlt"),
    COMPLETED("completed", "Erledigt ✓")
}

enum class AgDirectChannel(val code: String, val chipLabel: String) {
    LEITUNG("leitung", "👑 Leitung"),
    SHOP("shop", "🛍️ Shop"),
    ANDROID("android", "📱 Android"),
    MINIPC("minipc", "🖥️ Mini-PC"),
    BUCHHALTUNG("buchhaltung", "💶 Buchhaltung"),
    NEW("new", "➕ Neu")
}

data class AgDirectOutboxEntry(
    @SerializedName("client_uuid") val client_uuid: String,
    @SerializedName("title") val title: String,
    @SerializedName("body") val body: String = "",
    @SerializedName("checklist") val checklist: List<String> = emptyList(),
    @SerializedName("execution_mode") val execution_mode: String = "concept",
    @SerializedName("channel") val channel: String? = "leitung",
    @SerializedName("attachment_base64") val attachment_base64: String? = null,
    @SerializedName("attachment_mime") val attachment_mime: String? = null,
    @SerializedName("attachment_name") val attachment_name: String? = null,
    @SerializedName("created_at") val created_at: String = Instant.now().toString()
)

data class PreparedAgAttachment(
    val base64Jpeg: String,
    val mimeType: String = "image/jpeg",
    val fileName: String = "screenshot.jpg",
    val previewBitmap: ImageBitmap? = null,
    val width: Int = 0,
    val height: Int = 0
) {
    val isAudio: Boolean get() = mimeType.startsWith("audio/") ||
        fileName.endsWith(".m4a", true) ||
        fileName.endsWith(".mp3", true) ||
        fileName.endsWith(".wav", true) ||
        fileName.endsWith(".ogg", true) ||
        fileName.endsWith(".aac", true) ||
        fileName.endsWith(".opus", true) ||
        fileName.endsWith(".webm", true)
}

data class AgDirectDisplayItem(
    val id: String,
    val clientUuid: String,
    val title: String,
    val body: String,
    val checklist: List<String>,
    val executionMode: String,
    val status: AgDirectStatus,
    val isLocalOutbox: Boolean,
    val hasAttachment: Boolean,
    val attachmentName: String?,
    val attachmentMime: String?,
    val localAttachmentBase64: String?,
    val agResponseSummary: String?,
    val createdAt: String?,
    val deliveredAt: String?,
    val completedAt: String?,
    val channel: String = AgDirectChannel.LEITUNG.code,
    val pendingQuestion: String? = null,
    val awaitingGoAt: String? = null
)

data class AgRealtimeStatusSummary(
    val activeWorkingCount: Int = 0,
    val waitingForGoCount: Int = 0,
    val completedRecentCount: Int = 0,
    val activeHeadline: String = "",
    val primaryState: String = "idle", // "working" | "waiting_go" | "completed" | "idle"
    val isSyncing: Boolean = false
)

data class AgStatusTransitionEvent(
    val itemId: String,
    val title: String,
    val channel: String,
    val previousStatus: AgDirectStatus?,
    val newStatus: AgDirectStatus,
    val responseSummary: String? = null,
    val pendingQuestion: String? = null,
    val timestampMs: Long = System.currentTimeMillis()
) {
    fun toHumanEventLine(): String {
        return when (newStatus) {
            AgDirectStatus.DELIVERED -> "⚡ Antigravity arbeitet aktiv an: $title"
            AgDirectStatus.AWAITING_GO -> {
                val hint = pendingQuestion?.takeIf { it.isNotBlank() }?.take(120)
                    ?: responseSummary?.takeIf { it.isNotBlank() }?.take(120)
                if (hint != null) "⏳ Wartet auf dein Go: $title ($hint)" else "⏳ Wartet auf dein Go: $title"
            }
            AgDirectStatus.COMPLETED -> {
                val sum = responseSummary?.takeIf { it.isNotBlank() }?.take(140)
                if (sum != null) "✓ Erledigt: $title · $sum" else "✓ Erledigt: $title"
            }
            AgDirectStatus.PENDING -> "📤 An Antigravity übermittelt: $title"
            AgDirectStatus.QUEUED -> "📦 Offline vorgemerkt: $title"
        }
    }
}

object AgDirectInboxManager {
    const val OUTBOX_FILE_NAME = "ag_direct_outbox.json"
    const val PREFS_NAME = "seelenfunke_ag_direct_prefs"
    const val KEY_VOICE_FORCE_DIRECT = "ag_voice_force_direct"
    const val MAX_IMAGE_EDGE_PX = 1440
    const val JPEG_QUALITY = 82
    const val FAST_POLL_INTERVAL_MS = 2_800L
    const val IDLE_POLL_INTERVAL_MS = 9_000L
    const val FRESH_CACHE_FASTPATH_MS = 2_500L
    private const val MAX_OUTBOX_ITEMS = 50
    private const val MAX_ATTACHMENT_BYTES = 15 * 1024 * 1024

    private val gson = Gson()
    private val outboxLock = Any()
    private val flushMutex = Mutex()
    private val syncMutex = Mutex()
    private val managerScope = CoroutineScope(SupervisorJob() + Dispatchers.IO)

    private var cachedServerItems: List<AgDirectInboxItemDto> = emptyList()
    private var cachedServerCounts: AgDirectCountsDto? = null
    private var knownStatusByKey: MutableMap<String, AgDirectStatus> = mutableMapOf()
    private var hasInitializedTransitionBaseline: Boolean = false
    @Volatile
    private var lastCompletedSyncElapsedMs: Long = 0L
    @Volatile
    private var fastPollBoostUntilElapsedMs: Long = 0L
    private val activeRealtimeObservers = AtomicInteger(0)
    private var realtimePollJob: Job? = null

    internal var elapsedRealtimeProvider: () -> Long = { SystemClock.elapsedRealtime() }

    private val attachmentBitmapCache = object : LruCache<String, ImageBitmap>(12) {}

    private val _itemsFlow = MutableStateFlow<List<AgDirectDisplayItem>>(emptyList())
    val itemsFlow: StateFlow<List<AgDirectDisplayItem>> = _itemsFlow.asStateFlow()

    private val _pendingBlockadesFlow = MutableStateFlow<List<AgDirectDisplayItem>>(emptyList())
    val pendingBlockadesFlow: StateFlow<List<AgDirectDisplayItem>> = _pendingBlockadesFlow.asStateFlow()

    private val _openCountFlow = MutableStateFlow(0)
    val openCountFlow: StateFlow<Int> = _openCountFlow.asStateFlow()

    private val _isSyncingFlow = MutableStateFlow(false)
    val isSyncingFlow: StateFlow<Boolean> = _isSyncingFlow.asStateFlow()

    private val _realtimeStatusFlow = MutableStateFlow(AgRealtimeStatusSummary())
    val realtimeStatusFlow: StateFlow<AgRealtimeStatusSummary> = _realtimeStatusFlow.asStateFlow()

    private val _statusTransitionEvents = MutableSharedFlow<AgStatusTransitionEvent>(extraBufferCapacity = 16)
    val statusTransitionEvents: SharedFlow<AgStatusTransitionEvent> = _statusTransitionEvents.asSharedFlow()

    private val _voiceDirectExecutionFlow = MutableStateFlow(true)
    val voiceDirectExecutionFlow: StateFlow<Boolean> = _voiceDirectExecutionFlow.asStateFlow()

    private val _stagedAttachmentFlow = MutableStateFlow<PreparedAgAttachment?>(null)
    val stagedAttachmentFlow: StateFlow<PreparedAgAttachment?> = _stagedAttachmentFlow.asStateFlow()

    fun setStagedAttachment(attachment: PreparedAgAttachment?) {
        _stagedAttachmentFlow.value = attachment
    }

    fun clearStagedAttachment() {
        _stagedAttachmentFlow.value = null
    }

    fun getActiveStagedAttachment(): PreparedAgAttachment? {
        _stagedAttachmentFlow.value?.let { return it }
        val photo = FalconCameraController.lastCapturedPhoto.value
        if (photo != null && !photo.attachedToInbox) {
            return photo.toPreparedAgAttachment()
        }
        return null
    }

    fun hasActiveStagedAttachment(): Boolean {
        return getActiveStagedAttachment() != null
    }

    fun isVoiceDirectExecutionEnabled(context: Context): Boolean {
        return try {
            val prefs = context.applicationContext.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
            val enabled = prefs.getBoolean(KEY_VOICE_FORCE_DIRECT, true)
            _voiceDirectExecutionFlow.value = enabled
            enabled
        } catch (_: Throwable) {
            true
        }
    }

    fun setVoiceDirectExecutionEnabled(context: Context, enabled: Boolean) {
        try {
            val prefs = context.applicationContext.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
            prefs.edit().putBoolean(KEY_VOICE_FORCE_DIRECT, enabled).apply()
            _voiceDirectExecutionFlow.value = enabled
        } catch (_: Throwable) {
            _voiceDirectExecutionFlow.value = enabled
        }
    }

    fun mapStatus(rawStatus: String?, isLocalOutbox: Boolean = false): AgDirectStatus {
        if (isLocalOutbox) return AgDirectStatus.QUEUED
        return when (rawStatus?.trim()?.lowercase(Locale.ROOT)) {
            "queued", "outbox", "waiting" -> AgDirectStatus.QUEUED
            "delivered", "in_chat", "processing" -> AgDirectStatus.DELIVERED
            "awaiting_go", "needs_go", "waiting_for_go" -> AgDirectStatus.AWAITING_GO
            "completed", "done", "resolved" -> AgDirectStatus.COMPLETED
            else -> AgDirectStatus.PENDING
        }
    }

    fun normalizeExecutionMode(rawMode: String?): String {
        return if (rawMode?.trim()?.lowercase(Locale.ROOT) == "direct") "direct" else "concept"
    }

    fun resolveChannelEnum(rawChannel: String?): AgDirectChannel {
        return when (rawChannel?.trim()?.lowercase(Locale.ROOT)) {
            "shop", "laravel", "konfigurator", "produkte" -> AgDirectChannel.SHOP
            "android", "app", "mobile", "widget", "widgets" -> AgDirectChannel.ANDROID
            "minipc", "mini-pc", "mini_pc", "funkenwerk", "laser", "werkstatt" -> AgDirectChannel.MINIPC
            "buchhaltung", "accounting", "finanzen", "belege", "marketing" -> AgDirectChannel.BUCHHALTUNG
            "new", "neu" -> AgDirectChannel.NEW
            else -> AgDirectChannel.LEITUNG
        }
    }

    fun normalizeChannel(rawChannel: String?): String {
        return resolveChannelEnum(rawChannel).code
    }

    fun splitTitleAndBody(
        rawText: String,
        checklist: List<String>,
        hasAttachment: Boolean
    ): Pair<String, String> {
        val cleaned = rawText.trim()
        val cleanChecklist = checklist.map { it.trim() }.filter { it.isNotEmpty() }
        if (cleaned.isEmpty()) {
            val fallbackTitle = when {
                cleanChecklist.isNotEmpty() -> cleanChecklist.first().take(180)
                hasAttachment -> "Screenshot"
                else -> ""
            }
            return fallbackTitle to ""
        }

        val lines = cleaned.lines()
        val firstNonBlankIndex = lines.indexOfFirst { it.isNotBlank() }
        if (firstNonBlankIndex < 0) {
            return "" to ""
        }
        val firstLine = lines[firstNonBlankIndex].trim()
        val restLines = lines.drop(firstNonBlankIndex + 1).joinToString("\n").trim()

        return if (firstLine.length <= 180) {
            firstLine to restLines
        } else {
            val cutAt = firstLine.lastIndexOf(' ', startIndex = 160).takeIf { it >= 80 } ?: 160
            val shortTitle = firstLine.substring(0, cutAt).trimEnd() + "…"
            shortTitle to cleaned
        }
    }

    fun serializeOutboxEntries(entries: List<AgDirectOutboxEntry>, encrypt: Boolean = true): String {
        if (entries.isEmpty()) return ""
        val json = gson.toJson(entries)
        return if (encrypt) ServiceLocator.encryptPayloadForPrefs(json) else json
    }

    fun deserializeOutboxEntries(rawPayload: String?): MutableList<AgDirectOutboxEntry> {
        if (rawPayload.isNullOrBlank()) return mutableListOf()
        return try {
            val json = ServiceLocator.decryptPayloadFromPrefs(rawPayload) ?: return mutableListOf()
            if (json.isBlank()) return mutableListOf()
            val type = object : TypeToken<MutableList<AgDirectOutboxEntry>>() {}.type
            gson.fromJson<MutableList<AgDirectOutboxEntry>>(json, type) ?: mutableListOf()
        } catch (_: Throwable) {
            mutableListOf()
        }
    }

    fun readOutboxFromFile(outboxFile: File): MutableList<AgDirectOutboxEntry> {
        return synchronized(outboxLock) {
            try {
                if (!outboxFile.exists()) return@synchronized mutableListOf()
                val raw = outboxFile.readText(Charsets.UTF_8)
                deserializeOutboxEntries(raw)
            } catch (_: Throwable) {
                mutableListOf()
            }
        }
    }

    fun writeOutboxToFile(outboxFile: File, entries: List<AgDirectOutboxEntry>, encrypt: Boolean = true) {
        synchronized(outboxLock) {
            try {
                if (entries.isEmpty()) {
                    if (outboxFile.exists()) {
                        outboxFile.delete()
                    }
                    return@synchronized
                }
                outboxFile.parentFile?.mkdirs()
                val capped = if (entries.size > MAX_OUTBOX_ITEMS) {
                    entries.takeLast(MAX_OUTBOX_ITEMS)
                } else {
                    entries
                }
                val encoded = serializeOutboxEntries(capped, encrypt = encrypt)
                outboxFile.writeText(encoded, Charsets.UTF_8)
            } catch (_: Throwable) {
            }
        }
    }

    fun upsertOutboxEntry(
        existing: List<AgDirectOutboxEntry>,
        entry: AgDirectOutboxEntry
    ): List<AgDirectOutboxEntry> {
        val mutable = existing.filterNot { it.client_uuid == entry.client_uuid }.toMutableList()
        mutable.add(entry)
        while (mutable.size > MAX_OUTBOX_ITEMS) {
            mutable.removeAt(0)
        }
        return mutable
    }

    fun mergeOutboxAndServerItems(
        outboxEntries: List<AgDirectOutboxEntry>,
        serverItems: List<AgDirectInboxItemDto>
    ): List<AgDirectDisplayItem> {
        val serverClientUuids = serverItems.mapNotNull {
            it.client_uuid?.trim()?.takeIf { uuid -> uuid.isNotEmpty() }
        }.toSet()

        val uniqueLocalByUuid = LinkedHashMap<String, AgDirectOutboxEntry>()
        for (entry in outboxEntries) {
            val uuid = entry.client_uuid.trim()
            if (uuid.isNotEmpty() && uuid !in serverClientUuids) {
                uniqueLocalByUuid[uuid] = entry
            }
        }

        val localDisplayItems = uniqueLocalByUuid.values.toList().asReversed().map { local ->
            AgDirectDisplayItem(
                id = "local_${local.client_uuid}",
                clientUuid = local.client_uuid,
                title = local.title,
                body = local.body,
                checklist = local.checklist,
                executionMode = normalizeExecutionMode(local.execution_mode),
                status = AgDirectStatus.QUEUED,
                isLocalOutbox = true,
                hasAttachment = !local.attachment_base64.isNullOrBlank(),
                attachmentName = local.attachment_name,
                attachmentMime = local.attachment_mime,
                localAttachmentBase64 = local.attachment_base64,
                agResponseSummary = null,
                createdAt = local.created_at,
                deliveredAt = null,
                completedAt = null,
                channel = normalizeChannel(local.channel)
            )
        }

        val seenServerIds = HashSet<String>()
        val seenServerUuids = HashSet<String>()
        val remoteDisplayItems = serverItems.mapNotNull { remote ->
            val cleanId = remote.id.trim()
            val cleanUuid = remote.client_uuid?.trim().orEmpty()
            if (cleanId.isNotEmpty() && !seenServerIds.add(cleanId)) {
                return@mapNotNull null
            }
            if (cleanUuid.isNotEmpty() && !seenServerUuids.add(cleanUuid)) {
                return@mapNotNull null
            }
            AgDirectDisplayItem(
                id = cleanId.ifEmpty { "srv_${cleanUuid.ifEmpty { UUID.randomUUID().toString() }}" },
                clientUuid = cleanUuid,
                title = remote.title.ifBlank { "Nachricht" },
                body = remote.body.orEmpty(),
                checklist = remote.checklist?.map { it.trim() }?.filter { it.isNotEmpty() } ?: emptyList(),
                executionMode = normalizeExecutionMode(remote.execution_mode),
                status = mapStatus(remote.status, isLocalOutbox = false),
                isLocalOutbox = false,
                hasAttachment = remote.has_attachment,
                attachmentName = remote.attachment_name,
                attachmentMime = remote.attachment_mime,
                localAttachmentBase64 = null,
                agResponseSummary = remote.ag_response_summary?.trim()?.takeIf { it.isNotEmpty() },
                createdAt = remote.created_at,
                deliveredAt = remote.delivered_at,
                completedAt = remote.completed_at,
                channel = normalizeChannel(remote.channel),
                pendingQuestion = remote.pending_question?.trim()?.takeIf { it.isNotEmpty() },
                awaitingGoAt = remote.awaiting_go_at?.trim()?.takeIf { it.isNotEmpty() }
            )
        }

        return localDisplayItems + remoteDisplayItems
    }

    fun computePendingBlockades(mergedItems: List<AgDirectDisplayItem>): List<AgDirectDisplayItem> {
        return mergedItems.filter { item ->
            !item.isLocalOutbox && (
                item.status == AgDirectStatus.AWAITING_GO ||
                    (item.executionMode == "concept" && item.status != AgDirectStatus.COMPLETED)
                )
        }
    }

    fun computeOpenCount(
        mergedItems: List<AgDirectDisplayItem>,
        counts: AgDirectCountsDto? = null
    ): Int {
        val localQueued = mergedItems.count { it.isLocalOutbox }
        return if (counts != null) {
            localQueued + max(0, counts.pending) + max(0, counts.delivered) + max(0, counts.awaiting_go)
        } else {
            mergedItems.count {
                it.status == AgDirectStatus.QUEUED ||
                    it.status == AgDirectStatus.PENDING ||
                    it.status == AgDirectStatus.DELIVERED ||
                    it.status == AgDirectStatus.AWAITING_GO
            }
        }
    }

    private fun outboxFileFor(context: Context): File {
        return File(context.applicationContext.filesDir, OUTBOX_FILE_NAME)
    }

    fun computeRealtimeStatusSummary(
        items: List<AgDirectDisplayItem>,
        isSyncing: Boolean = _isSyncingFlow.value
    ): AgRealtimeStatusSummary {
        val working = items.filter {
            it.status == AgDirectStatus.QUEUED ||
                it.status == AgDirectStatus.PENDING ||
                it.status == AgDirectStatus.DELIVERED
        }
        val waitingGo = items.filter { it.status == AgDirectStatus.AWAITING_GO }
        val completed = items.filter { it.status == AgDirectStatus.COMPLETED }

        val (primaryState, headline) = when {
            working.isNotEmpty() -> {
                val lead = working.first().title.take(36)
                "working" to if (working.size == 1) {
                    "⚡ Antigravity arbeitet aktiv: $lead"
                } else {
                    "⚡ Antigravity arbeitet aktiv (${working.size}) · $lead"
                }
            }
            waitingGo.isNotEmpty() -> {
                val lead = waitingGo.first().title.take(36)
                "waiting_go" to if (waitingGo.size == 1) {
                    "⏳ Wartet auf dein Go: $lead"
                } else {
                    "⏳ ${waitingGo.size} Aufträge warten auf dein Go"
                }
            }
            completed.isNotEmpty() -> {
                "completed" to "✓ ${completed.size} erledigt · ${completed.first().title.take(34)}"
            }
            else -> "idle" to ""
        }

        return AgRealtimeStatusSummary(
            activeWorkingCount = working.size,
            waitingForGoCount = waitingGo.size,
            completedRecentCount = completed.size,
            activeHeadline = headline,
            primaryState = primaryState,
            isSyncing = isSyncing
        )
    }

    private fun updateFlowsFromCache(context: Context, emitTransitions: Boolean = false) {
        val outbox = readOutboxFromFile(outboxFileFor(context))
        val merged = mergeOutboxAndServerItems(outbox, cachedServerItems)

        synchronized(outboxLock) {
            if (!hasInitializedTransitionBaseline) {
                merged.forEach { item ->
                    val key = item.clientUuid.ifBlank { item.id }
                    if (key.isNotBlank()) knownStatusByKey[key] = item.status
                }
                if (merged.isNotEmpty()) {
                    hasInitializedTransitionBaseline = true
                }
            } else if (emitTransitions) {
                for (item in merged.asReversed()) {
                    val key = item.clientUuid.ifBlank { item.id }
                    if (key.isBlank()) continue
                    val prev = knownStatusByKey[key]
                    if (prev != item.status) {
                        knownStatusByKey[key] = item.status
                        if (prev != null || item.status != AgDirectStatus.COMPLETED) {
                            _statusTransitionEvents.tryEmit(
                                AgStatusTransitionEvent(
                                    itemId = item.id,
                                    title = item.title,
                                    channel = item.channel,
                                    previousStatus = prev,
                                    newStatus = item.status,
                                    responseSummary = item.agResponseSummary,
                                    pendingQuestion = item.pendingQuestion
                                )
                            )
                        }
                    }
                }
            }
        }

        _itemsFlow.value = merged
        _pendingBlockadesFlow.value = computePendingBlockades(merged)
        _openCountFlow.value = computeOpenCount(merged, cachedServerCounts)
        _realtimeStatusFlow.value = computeRealtimeStatusSummary(merged, _isSyncingFlow.value)
    }

    fun boostFastPollWindow(durationMs: Long = 25_000L) {
        fastPollBoostUntilElapsedMs = elapsedRealtimeProvider() + durationMs.coerceAtLeast(3_000L)
    }

    fun computeRecommendedPollIntervalMs(nowElapsedMs: Long = elapsedRealtimeProvider()): Long {
        val summary = _realtimeStatusFlow.value
        return if (summary.activeWorkingCount > 0 || nowElapsedMs < fastPollBoostUntilElapsedMs) {
            FAST_POLL_INTERVAL_MS
        } else {
            IDLE_POLL_INTERVAL_MS
        }
    }

    fun acquireRealtimeObserver(context: Context) {
        val appCtx = context.applicationContext
        val count = activeRealtimeObservers.incrementAndGet()
        if (count == 1 || realtimePollJob?.isActive != true) {
            realtimePollJob?.cancel()
            realtimePollJob = managerScope.launch {
                hydrateLocalState(appCtx)
                syncAll(appCtx)
                while (isActive && activeRealtimeObservers.get() > 0) {
                    val waitMs = computeRecommendedPollIntervalMs()
                    delay(waitMs)
                    if (!isActive || activeRealtimeObservers.get() <= 0) break
                    syncAll(appCtx)
                }
            }
        } else {
            refreshAsync(appCtx)
        }
    }

    fun releaseRealtimeObserver() {
        val rem = activeRealtimeObservers.decrementAndGet().coerceAtLeast(0)
        activeRealtimeObservers.set(rem)
        if (rem == 0) {
            realtimePollJob?.cancel()
            realtimePollJob = null
        }
    }

    suspend fun syncIfStale(
        context: Context,
        maxAgeMs: Long = FRESH_CACHE_FASTPATH_MS,
        apiOverride: AgDirectApi? = null
    ) {
        val appCtx = context.applicationContext
        if (apiOverride != null) {
            syncAll(appCtx, apiOverride)
            return
        }
        val nowElapsed = elapsedRealtimeProvider()
        val age = nowElapsed - lastCompletedSyncElapsedMs
        if (lastCompletedSyncElapsedMs > 0L && age in 0L..maxAgeMs) {
            if (age > 1_200L) {
                refreshAsync(appCtx)
            }
            return
        }
        syncAll(appCtx)
    }

    fun hydrateLocalState(context: Context) {
        try {
            ServiceLocator.ensureInitialized(context.applicationContext)
            isVoiceDirectExecutionEnabled(context.applicationContext)
            updateFlowsFromCache(context.applicationContext)
        } catch (_: Throwable) {
        }
    }

    fun refreshAsync(context: Context) {
        val appCtx = context.applicationContext
        managerScope.launch {
            syncAll(appCtx)
        }
    }

    fun refreshFromServer(context: Context) {
        refreshAsync(context)
    }

    suspend fun resolveBlockade(
        context: Context,
        itemId: String,
        decision: String,
        answerText: String,
        apiOverride: AgDirectApi? = null
    ): Boolean = withContext(Dispatchers.IO) {
        val appCtx = context.applicationContext
        val cleanId = itemId.trim()
        if (cleanId.isEmpty()) return@withContext false

        val cleanDecision = decision.trim().lowercase(Locale.ROOT).ifBlank { "approved" }
        val cleanAnswer = answerText.trim()

        try {
            if (apiOverride == null) {
                ServiceLocator.ensureInitialized(appCtx)
            }
            val api = apiOverride ?: ServiceLocator.agDirectApi
            val resp = api.resolveBlockade(
                id = cleanId,
                request = AgDirectResolveRequest(
                    decision = cleanDecision,
                    answer_text = cleanAnswer
                )
            )
            if (resp.success) {
                val updatedList = cachedServerItems.map { existing ->
                    if (existing.id == cleanId && resp.resolved_item != null) {
                        resp.resolved_item
                    } else {
                        existing
                    }
                }.toMutableList()

                if (resp.followup_item != null && updatedList.none { it.id == resp.followup_item.id }) {
                    updatedList.add(0, resp.followup_item)
                }

                cachedServerItems = updatedList
                if (resp.counts != null) {
                    cachedServerCounts = resp.counts
                } else {
                    cachedServerCounts = cachedServerCounts?.let { c ->
                        c.copy(
                            awaiting_go = max(0, c.awaiting_go - 1),
                            pending = if (resp.followup_item != null) c.pending + 1 else c.pending,
                            completed = if (cleanDecision != "deferred") c.completed + 1 else c.completed
                        )
                    }
                }
                boostFastPollWindow(30_000L)
                lastCompletedSyncElapsedMs = elapsedRealtimeProvider()
                updateFlowsFromCache(appCtx, emitTransitions = true)
                return@withContext true
            }
        } catch (_: Throwable) {
        }

        // Offline-Fallback bei Freigabe / Antwort: lokal in Outbox einreihen, damit keine Antwort verloren geht
        if (cleanDecision == "approved" || cleanDecision == "info_provided") {
            val orig = _itemsFlow.value.firstOrNull { it.id == cleanId }
            val fallbackTitle = if (orig != null) "GO & Antwort zu: ${orig.title}" else "GO & Antwort zu: $cleanId"
            val fallbackBody = buildString {
                append("Referenz-Ticket: ").append(cleanId)
                if (cleanAnswer.isNotEmpty()) {
                    append("\nAntwort: ").append(cleanAnswer)
                }
            }
            submitOutboxEntry(
                context = appCtx,
                title = fallbackTitle,
                body = fallbackBody,
                executionMode = "direct",
                channel = orig?.channel ?: AgDirectChannel.LEITUNG.code
            )
        }
        false
    }

    suspend fun flushOutbox(
        context: Context,
        apiOverride: AgDirectApi? = null
    ): Int = withContext(Dispatchers.IO) {
        val appCtx = context.applicationContext
        if (apiOverride == null) {
            ServiceLocator.ensureInitialized(appCtx)
            if (ServiceLocator.getAuthToken().isNullOrBlank()) {
                return@withContext 0
            }
        }

        flushMutex.withLock {
            val file = outboxFileFor(appCtx)
            val current = readOutboxFromFile(file)
            if (current.isEmpty()) return@withLock 0

            val api = apiOverride ?: ServiceLocator.agDirectApi
            var flushed = 0
            val remaining = current.toMutableList()
            val newlyCreatedServerItems = mutableListOf<AgDirectInboxItemDto>()

            val iterator = remaining.iterator()
            while (iterator.hasNext()) {
                val entry = iterator.next()
                try {
                    val primaryRequest = AgDirectCreateRequest(
                        client_uuid = entry.client_uuid.take(64),
                        title = entry.title.trim().ifBlank { "Sammelpaket" }.take(480),
                        body = entry.body.take(14_000),
                        checklist = entry.checklist.map { it.trim().take(950) }.filter { it.isNotEmpty() }.take(45),
                        execution_mode = normalizeExecutionMode(entry.execution_mode),
                        channel = normalizeChannel(entry.channel),
                        attachment_base64 = entry.attachment_base64,
                        attachment_mime = entry.attachment_mime,
                        attachment_name = entry.attachment_name
                    )
                    val response = try {
                        api.createItem(primaryRequest)
                    } catch (httpErr: retrofit2.HttpException) {
                        val status = httpErr.code()
                        if (status in listOf(400, 413, 422)) {
                            // Self-Heal legacy/strict server validation limits (<= 295 char checklist items, <= 4800 char body)
                            val fallbackBody = buildString {
                                if (entry.body.isNotBlank()) {
                                    append(entry.body.trim())
                                } else if (entry.checklist.isNotEmpty()) {
                                    entry.checklist.forEachIndexed { i, b -> append("${i + 1}. $b\n") }
                                }
                            }.trim().take(4800)
                            val compactRequest = primaryRequest.copy(
                                title = primaryRequest.title.take(450),
                                body = fallbackBody,
                                checklist = entry.checklist.map { it.trim().take(290) }.filter { it.isNotEmpty() }.take(20)
                            )
                            try {
                                api.createItem(compactRequest)
                            } catch (secondErr: retrofit2.HttpException) {
                                if (secondErr.code() in listOf(400, 413, 422) && !entry.attachment_base64.isNullOrBlank()) {
                                    api.createItem(
                                        compactRequest.copy(
                                            body = (fallbackBody + "\n[Anhang lokal verworfen wegen Server-Limit]").take(4800),
                                            attachment_base64 = null,
                                            attachment_mime = null,
                                            attachment_name = null
                                        )
                                    )
                                } else {
                                    throw secondErr
                                }
                            }
                        } else {
                            throw httpErr
                        }
                    }

                    if (response.success || response.data != null) {
                        iterator.remove()
                        writeOutboxToFile(file, remaining)
                        response.data?.let { newlyCreatedServerItems.add(it) }
                        flushed++
                    } else {
                        break
                    }
                } catch (httpFatal: retrofit2.HttpException) {
                    val code = httpFatal.code()
                    if (code == 401 || code == 403 || code == 429 || code >= 500) {
                        break
                    }
                    // Non-recoverable 4xx on this single entry must NOT head-of-line-block newer Sammelpakete/messages
                    continue
                } catch (_: Throwable) {
                    break
                }
            }

            if (newlyCreatedServerItems.isNotEmpty()) {
                val newUuids = newlyCreatedServerItems.mapNotNull { it.client_uuid }.toSet()
                cachedServerItems = newlyCreatedServerItems.asReversed() + cachedServerItems.filterNot {
                    it.client_uuid != null && it.client_uuid in newUuids
                }
            }
            if (flushed > 0) {
                updateFlowsFromCache(appCtx)
            }
            flushed
        }
    }

    suspend fun syncAll(
        context: Context,
        apiOverride: AgDirectApi? = null
    ) = withContext(Dispatchers.IO) {
        val appCtx = context.applicationContext
        if (apiOverride == null) {
            ServiceLocator.ensureInitialized(appCtx)
            if (ServiceLocator.getAuthToken().isNullOrBlank()) {
                updateFlowsFromCache(appCtx, emitTransitions = false)
                return@withContext
            }
        }

        syncMutex.withLock {
            _isSyncingFlow.value = true
            _realtimeStatusFlow.value = _realtimeStatusFlow.value.copy(isSyncing = true)
            try {
                flushOutbox(appCtx, apiOverride)
                val api = apiOverride ?: ServiceLocator.agDirectApi
                val listResp = api.listInbox(limit = 30)
                if (listResp.success || listResp.items != null) {
                    val serverItems = listResp.items ?: emptyList()
                    cachedServerItems = serverItems
                    cachedServerCounts = listResp.counts

                    val serverUuids = serverItems.mapNotNull { it.client_uuid?.trim()?.takeIf { s -> s.isNotEmpty() } }.toSet()
                    if (serverUuids.isNotEmpty()) {
                        val file = outboxFileFor(appCtx)
                        val outbox = readOutboxFromFile(file)
                        val pruned = outbox.filterNot { it.client_uuid in serverUuids }
                        if (pruned.size != outbox.size) {
                            writeOutboxToFile(file, pruned)
                        }
                    }
                    lastCompletedSyncElapsedMs = elapsedRealtimeProvider()
                }
            } catch (_: Throwable) {
            } finally {
                _isSyncingFlow.value = false
                updateFlowsFromCache(appCtx, emitTransitions = true)
            }
        }
    }

    suspend fun submitOutboxEntry(
        context: Context,
        title: String,
        body: String = "",
        checklist: List<String> = emptyList(),
        executionMode: String = "direct",
        channel: String = AgDirectChannel.LEITUNG.code
    ): Boolean = submitMessage(
        context = context,
        rawText = if (body.isNotBlank()) "$title\n$body" else title,
        checklist = checklist,
        executionMode = executionMode,
        attachment = null,
        channel = channel
    )

    suspend fun submitMessage(
        context: Context,
        rawText: String,
        checklist: List<String> = emptyList(),
        executionMode: String = "concept",
        attachment: PreparedAgAttachment? = null,
        channel: String = AgDirectChannel.LEITUNG.code,
        clientUuid: String = UUID.randomUUID().toString(),
        apiOverride: AgDirectApi? = null
    ): Boolean = submitNewEntry(
        context = context,
        rawText = rawText,
        checklist = checklist,
        executionMode = executionMode,
        attachment = attachment,
        channel = channel,
        clientUuid = clientUuid,
        apiOverride = apiOverride
    )

    suspend fun submitNewEntry(
        context: Context,
        rawText: String,
        checklist: List<String>,
        executionMode: String,
        attachment: PreparedAgAttachment?,
        channel: String = AgDirectChannel.LEITUNG.code,
        clientUuid: String = UUID.randomUUID().toString(),
        apiOverride: AgDirectApi? = null
    ): Boolean = withContext(Dispatchers.IO) {
        val appCtx = context.applicationContext
        if (apiOverride == null) {
            ServiceLocator.ensureInitialized(appCtx)
        }

        val cleanChecklist = checklist.map { it.trim() }.filter { it.isNotEmpty() }
        val (title, body) = splitTitleAndBody(rawText, cleanChecklist, attachment != null)
        if (title.isBlank()) return@withContext false

        val normalizedMode = normalizeExecutionMode(executionMode)
        val normalizedChannel = normalizeChannel(channel)
        val entry = AgDirectOutboxEntry(
            client_uuid = clientUuid,
            title = title,
            body = body,
            checklist = cleanChecklist,
            execution_mode = normalizedMode,
            channel = normalizedChannel,
            attachment_base64 = attachment?.base64Jpeg,
            attachment_mime = attachment?.mimeType,
            attachment_name = attachment?.fileName,
            created_at = Instant.now().toString()
        )

        if (attachment?.previewBitmap != null) {
            attachmentBitmapCache.put("uuid_$clientUuid", attachment.previewBitmap)
        }

        val file = outboxFileFor(appCtx)
        synchronized(outboxLock) {
            val current = readOutboxFromFile(file)
            val updated = upsertOutboxEntry(current, entry)
            writeOutboxToFile(file, updated)
        }
        boostFastPollWindow(30_000L)
        updateFlowsFromCache(appCtx, emitTransitions = true)

        val flushed = flushOutbox(appCtx, apiOverride)
        if (flushed > 0) {
            try {
                val api = apiOverride ?: ServiceLocator.agDirectApi
                val listResp = api.listInbox(limit = 30)
                if (listResp.success || listResp.items != null) {
                    cachedServerItems = listResp.items ?: cachedServerItems
                    cachedServerCounts = listResp.counts ?: cachedServerCounts
                    lastCompletedSyncElapsedMs = elapsedRealtimeProvider()
                    updateFlowsFromCache(appCtx, emitTransitions = true)
                }
            } catch (_: Throwable) {
            }
        }

        val remainingUuids = readOutboxFromFile(file).map { it.client_uuid }.toSet()
        clientUuid !in remainingUuids
    }

    suspend fun deleteEntry(
        context: Context,
        item: AgDirectDisplayItem,
        apiOverride: AgDirectApi? = null
    ): Boolean = withContext(Dispatchers.IO) {
        val appCtx = context.applicationContext
        val file = outboxFileFor(appCtx)
        if (item.isLocalOutbox) {
            val updated = readOutboxFromFile(file).filterNot { it.client_uuid == item.clientUuid }
            writeOutboxToFile(file, updated)
            updateFlowsFromCache(appCtx)
            return@withContext true
        }

        return@withContext try {
            if (apiOverride == null) {
                ServiceLocator.ensureInitialized(appCtx)
            }
            val api = apiOverride ?: ServiceLocator.agDirectApi
            val resp = api.deleteItem(item.id)
            if (resp.success) {
                cachedServerItems = cachedServerItems.filterNot { it.id == item.id }
                cachedServerCounts = cachedServerCounts?.let { c ->
                    when (item.status) {
                        AgDirectStatus.PENDING -> c.copy(pending = max(0, c.pending - 1))
                        AgDirectStatus.DELIVERED -> c.copy(delivered = max(0, c.delivered - 1))
                        AgDirectStatus.AWAITING_GO -> c.copy(awaiting_go = max(0, c.awaiting_go - 1))
                        AgDirectStatus.COMPLETED -> c.copy(completed = max(0, c.completed - 1))
                        AgDirectStatus.QUEUED -> c
                    }
                }
                attachmentBitmapCache.remove("id_${item.id}")
                updateFlowsFromCache(appCtx)
                true
            } else {
                false
            }
        } catch (_: Throwable) {
            false
        }
    }

    suspend fun loadAttachmentBitmap(
        item: AgDirectDisplayItem,
        apiOverride: AgDirectApi? = null
    ): ImageBitmap? = withContext(Dispatchers.IO) {
        if (item.clientUuid.isNotBlank()) {
            attachmentBitmapCache.get("uuid_${item.clientUuid}")?.let { return@withContext it }
        }
        if (!item.isLocalOutbox && item.id.isNotBlank()) {
            attachmentBitmapCache.get("id_${item.id}")?.let { return@withContext it }
        }

        val localB64 = item.localAttachmentBase64
        if (!localB64.isNullOrBlank()) {
            return@withContext try {
                val cleanB64 = localB64.substringAfter("base64,", localB64)
                val bytes = Base64.getMimeDecoder().decode(cleanB64)
                val bmp = BitmapFactory.decodeByteArray(bytes, 0, bytes.size)?.asImageBitmap()
                if (bmp != null && item.clientUuid.isNotBlank()) {
                    attachmentBitmapCache.put("uuid_${item.clientUuid}", bmp)
                }
                bmp
            } catch (_: Throwable) {
                null
            }
        }

        if (item.isLocalOutbox || !item.hasAttachment || item.id.isBlank()) {
            return@withContext null
        }

        return@withContext try {
            val api = apiOverride ?: ServiceLocator.agDirectApi
            val responseBody = api.getAttachment(item.id)
            responseBody.use { body ->
                val contentLength = body.contentLength()
                if (contentLength > MAX_ATTACHMENT_BYTES) return@use null
                val bytes = body.bytes()
                if (bytes.isEmpty() || bytes.size > MAX_ATTACHMENT_BYTES) return@use null
                val decoded = BitmapFactory.decodeByteArray(bytes, 0, bytes.size)?.asImageBitmap()
                if (decoded != null) {
                    attachmentBitmapCache.put("id_${item.id}", decoded)
                }
                decoded
            }
        } catch (_: Throwable) {
            null
        }
    }

    suspend fun prepareAttachmentFromUri(
        context: Context,
        uri: Uri
    ): PreparedAgAttachment? = withContext(Dispatchers.IO) {
        try {
            val resolver = context.contentResolver
            val rawName = (queryDisplayName(context, uri) ?: uri.lastPathSegment ?: uri.path)
                ?.substringAfterLast('/')
                ?.substringAfterLast('\\')
            val declaredMime = try { resolver.getType(uri)?.lowercase(Locale.ROOT) } catch (_: Throwable) { null }
            val rawExt = rawName?.substringAfterLast('.', "")?.lowercase(Locale.ROOT) ?: ""

            val isAudioCandidate = declaredMime?.startsWith("audio/") == true ||
                rawExt in listOf("m4a", "mp3", "wav", "ogg", "aac", "opus", "webm")

            if (isAudioCandidate) {
                val maxAudioBytes = 8 * 1024 * 1024
                val audioBytes = resolver.openInputStream(uri)?.use { stream ->
                    val out = ByteArrayOutputStream()
                    val buf = ByteArray(16384)
                    var total = 0
                    var r: Int
                    while (stream.read(buf).also { r = it } != -1) {
                        total += r
                        if (total > maxAudioBytes) return@withContext null
                        out.write(buf, 0, r)
                    }
                    out.toByteArray()
                } ?: return@withContext null

                if (audioBytes.isEmpty() || audioBytes.size > maxAudioBytes) {
                    return@withContext null
                }

                val canonicalExt = when {
                    rawExt.isNotBlank() -> rawExt
                    declaredMime == "audio/mpeg" || declaredMime == "audio/mp3" -> "mp3"
                    declaredMime == "audio/wav" || declaredMime == "audio/x-wav" -> "wav"
                    declaredMime == "audio/ogg" -> "ogg"
                    declaredMime == "audio/aac" -> "aac"
                    else -> "m4a"
                }

                val resolvedMime = declaredMime?.takeIf { it.startsWith("audio/") } ?: when (canonicalExt) {
                    "mp3" -> "audio/mpeg"
                    "wav" -> "audio/wav"
                    "ogg" -> "audio/ogg"
                    "aac" -> "audio/aac"
                    "opus" -> "audio/opus"
                    "webm" -> "audio/webm"
                    else -> "audio/mp4"
                }

                val baseName = rawName
                    ?.substringBeforeLast('.')
                    ?.replace(Regex("[^A-Za-z0-9._-]"), "_")
                    ?.take(48)
                    ?.takeIf { it.isNotBlank() }
                    ?: "audio_${System.currentTimeMillis()}"
                val finalFileName = "$baseName.$canonicalExt"
                val b64 = Base64.getEncoder().encodeToString(audioBytes)

                return@withContext PreparedAgAttachment(
                    base64Jpeg = b64,
                    mimeType = resolvedMime,
                    fileName = finalFileName,
                    previewBitmap = null,
                    width = 0,
                    height = 0
                )
            }

            val baseName = rawName
                ?.substringBeforeLast('.')
                ?.replace(Regex("[^A-Za-z0-9._-]"), "_")
                ?.take(48)
                ?.takeIf { it.isNotBlank() }
                ?: "screenshot"
            val finalFileName = "$baseName.jpg"

            val boundsOptions = BitmapFactory.Options().apply {
                inJustDecodeBounds = true
            }
            resolver.openInputStream(uri)?.use { stream ->
                BitmapFactory.decodeStream(stream, null, boundsOptions)
            }
            val srcW = boundsOptions.outWidth
            val srcH = boundsOptions.outHeight
            if (srcW <= 0 || srcH <= 0) return@withContext null

            var sampleSize = 1
            while ((srcW / sampleSize) > (MAX_IMAGE_EDGE_PX * 2) || (srcH / sampleSize) > (MAX_IMAGE_EDGE_PX * 2)) {
                sampleSize *= 2
            }

            val rotationDegrees = try {
                resolver.openInputStream(uri)?.use { exifStream ->
                    val exif = ExifInterface(exifStream)
                    when (exif.getAttributeInt(ExifInterface.TAG_ORIENTATION, ExifInterface.ORIENTATION_NORMAL)) {
                        ExifInterface.ORIENTATION_ROTATE_90 -> 90f
                        ExifInterface.ORIENTATION_ROTATE_180 -> 180f
                        ExifInterface.ORIENTATION_ROTATE_270 -> 270f
                        else -> 0f
                    }
                } ?: 0f
            } catch (_: Throwable) {
                0f
            }

            val decodeOptions = BitmapFactory.Options().apply {
                inSampleSize = sampleSize
                inPreferredConfig = Bitmap.Config.ARGB_8888
            }
            val decoded = resolver.openInputStream(uri)?.use { stream ->
                BitmapFactory.decodeStream(stream, null, decodeOptions)
            } ?: return@withContext null

            val rotated = if (rotationDegrees != 0f) {
                val matrix = Matrix().apply { postRotate(rotationDegrees) }
                Bitmap.createBitmap(decoded, 0, 0, decoded.width, decoded.height, matrix, true).also {
                    if (it !== decoded) decoded.recycle()
                }
            } else {
                decoded
            }

            val longestEdge = max(rotated.width, rotated.height)
            val scaled = if (longestEdge > MAX_IMAGE_EDGE_PX) {
                val ratio = MAX_IMAGE_EDGE_PX.toFloat() / longestEdge.toFloat()
                val targetW = max(1, (rotated.width * ratio).roundToInt())
                val targetH = max(1, (rotated.height * ratio).roundToInt())
                Bitmap.createScaledBitmap(rotated, targetW, targetH, true).also {
                    if (it !== rotated) rotated.recycle()
                }
            } else {
                rotated
            }

            val jpegBytes = ByteArrayOutputStream().use { out ->
                scaled.compress(Bitmap.CompressFormat.JPEG, JPEG_QUALITY, out)
                out.toByteArray()
            }
            val base64Jpeg = Base64.getEncoder().encodeToString(jpegBytes)
            val preview = BitmapFactory.decodeByteArray(jpegBytes, 0, jpegBytes.size)?.asImageBitmap()
                ?: scaled.asImageBitmap()

            PreparedAgAttachment(
                base64Jpeg = base64Jpeg,
                mimeType = "image/jpeg",
                fileName = finalFileName,
                previewBitmap = preview,
                width = scaled.width,
                height = scaled.height
            )
        } catch (_: Throwable) {
            null
        }
    }

    private fun queryDisplayName(context: Context, uri: Uri): String? {
        return try {
            context.contentResolver.query(uri, arrayOf(OpenableColumns.DISPLAY_NAME), null, null, null)?.use { cursor ->
                val idx = cursor.getColumnIndex(OpenableColumns.DISPLAY_NAME)
                if (idx >= 0 && cursor.moveToFirst()) cursor.getString(idx) else null
            }
        } catch (_: Throwable) {
            null
        }
    }

    internal fun resetMemoryCacheForTests() {
        realtimePollJob?.cancel()
        realtimePollJob = null
        activeRealtimeObservers.set(0)
        cachedServerItems = emptyList()
        cachedServerCounts = null
        synchronized(outboxLock) {
            knownStatusByKey.clear()
            hasInitializedTransitionBaseline = false
        }
        lastCompletedSyncElapsedMs = 0L
        fastPollBoostUntilElapsedMs = 0L
        _itemsFlow.value = emptyList()
        _pendingBlockadesFlow.value = emptyList()
        _openCountFlow.value = 0
        _isSyncingFlow.value = false
        _realtimeStatusFlow.value = AgRealtimeStatusSummary()
        _voiceDirectExecutionFlow.value = true
        _stagedAttachmentFlow.value = null
    }
}
