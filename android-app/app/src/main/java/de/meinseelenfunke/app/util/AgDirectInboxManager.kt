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
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import kotlinx.coroutines.sync.Mutex
import kotlinx.coroutines.sync.withLock
import kotlinx.coroutines.withContext
import java.io.ByteArrayOutputStream
import java.io.File
import java.time.Instant
import java.util.Base64
import java.util.Locale
import java.util.UUID
import kotlin.math.max
import kotlin.math.roundToInt

enum class AgDirectStatus(val code: String, val badgeLabel: String) {
    QUEUED("queued", "Wartet"),
    PENDING("pending", "Gesendet"),
    DELIVERED("delivered", "Bei Falcone"),
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
    val previewBitmap: ImageBitmap,
    val width: Int,
    val height: Int
)

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

object AgDirectInboxManager {
    const val OUTBOX_FILE_NAME = "ag_direct_outbox.json"
    const val PREFS_NAME = "seelenfunke_ag_direct_prefs"
    const val KEY_VOICE_FORCE_DIRECT = "ag_voice_force_direct"
    const val MAX_IMAGE_EDGE_PX = 1440
    const val JPEG_QUALITY = 82
    private const val MAX_OUTBOX_ITEMS = 50
    private const val MAX_ATTACHMENT_BYTES = 15 * 1024 * 1024

    private val gson = Gson()
    private val outboxLock = Any()
    private val flushMutex = Mutex()
    private val managerScope = CoroutineScope(SupervisorJob() + Dispatchers.IO)

    private var cachedServerItems: List<AgDirectInboxItemDto> = emptyList()
    private var cachedServerCounts: AgDirectCountsDto? = null

    private val attachmentBitmapCache = object : LruCache<String, ImageBitmap>(12) {}

    private val _itemsFlow = MutableStateFlow<List<AgDirectDisplayItem>>(emptyList())
    val itemsFlow: StateFlow<List<AgDirectDisplayItem>> = _itemsFlow.asStateFlow()

    private val _pendingBlockadesFlow = MutableStateFlow<List<AgDirectDisplayItem>>(emptyList())
    val pendingBlockadesFlow: StateFlow<List<AgDirectDisplayItem>> = _pendingBlockadesFlow.asStateFlow()

    private val _openCountFlow = MutableStateFlow(0)
    val openCountFlow: StateFlow<Int> = _openCountFlow.asStateFlow()

    private val _isSyncingFlow = MutableStateFlow(false)
    val isSyncingFlow: StateFlow<Boolean> = _isSyncingFlow.asStateFlow()

    private val _voiceDirectExecutionFlow = MutableStateFlow(true)
    val voiceDirectExecutionFlow: StateFlow<Boolean> = _voiceDirectExecutionFlow.asStateFlow()

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

    private fun updateFlowsFromCache(context: Context) {
        val outbox = readOutboxFromFile(outboxFileFor(context))
        val merged = mergeOutboxAndServerItems(outbox, cachedServerItems)
        _itemsFlow.value = merged
        _pendingBlockadesFlow.value = computePendingBlockades(merged)
        _openCountFlow.value = computeOpenCount(merged, cachedServerCounts)
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
                updateFlowsFromCache(appCtx)
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
                    val response = api.createItem(
                        AgDirectCreateRequest(
                            client_uuid = entry.client_uuid,
                            title = entry.title,
                            body = entry.body,
                            checklist = entry.checklist,
                            execution_mode = normalizeExecutionMode(entry.execution_mode),
                            channel = normalizeChannel(entry.channel),
                            attachment_base64 = entry.attachment_base64,
                            attachment_mime = entry.attachment_mime,
                            attachment_name = entry.attachment_name
                        )
                    )
                    if (response.success || response.data != null) {
                        iterator.remove()
                        writeOutboxToFile(file, remaining)
                        response.data?.let { newlyCreatedServerItems.add(it) }
                        flushed++
                    } else {
                        break
                    }
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
                updateFlowsFromCache(appCtx)
                return@withContext
            }
        }

        _isSyncingFlow.value = true
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
            }
        } catch (_: Throwable) {
        } finally {
            updateFlowsFromCache(appCtx)
            _isSyncingFlow.value = false
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

        if (attachment != null) {
            attachmentBitmapCache.put("uuid_$clientUuid", attachment.previewBitmap)
        }

        val file = outboxFileFor(appCtx)
        synchronized(outboxLock) {
            val current = readOutboxFromFile(file)
            val updated = upsertOutboxEntry(current, entry)
            writeOutboxToFile(file, updated)
        }
        updateFlowsFromCache(appCtx)

        val flushed = flushOutbox(appCtx, apiOverride)
        if (flushed > 0) {
            try {
                val api = apiOverride ?: ServiceLocator.agDirectApi
                val listResp = api.listInbox(limit = 30)
                if (listResp.success || listResp.items != null) {
                    cachedServerItems = listResp.items ?: cachedServerItems
                    cachedServerCounts = listResp.counts ?: cachedServerCounts
                    updateFlowsFromCache(appCtx)
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
        cachedServerItems = emptyList()
        cachedServerCounts = null
        _itemsFlow.value = emptyList()
        _pendingBlockadesFlow.value = emptyList()
        _openCountFlow.value = 0
        _isSyncingFlow.value = false
        _voiceDirectExecutionFlow.value = true
    }
}
