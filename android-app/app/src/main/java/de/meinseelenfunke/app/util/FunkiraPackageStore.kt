package de.meinseelenfunke.app.util

import android.content.Context
import com.google.gson.Gson
import com.google.gson.annotations.SerializedName
import com.google.gson.reflect.TypeToken
import de.meinseelenfunke.app.data.api.AgDirectApi
import de.meinseelenfunke.app.di.ServiceLocator
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.NonCancellable
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.sync.Mutex
import kotlinx.coroutines.sync.withLock
import kotlinx.coroutines.withContext
import java.io.File
import java.time.Instant
import java.util.Locale
import java.util.UUID

data class FunkiraPackageAttachment(
    @SerializedName("id") val id: String = UUID.randomUUID().toString(),
    @SerializedName("file_name") val fileName: String = "foto.jpg",
    @SerializedName("mime_type") val mimeType: String = "image/jpeg",
    @SerializedName("base64_data") val base64Data: String = "",
    @SerializedName("caption") val caption: String = "Foto",
    @SerializedName("size_kb") val sizeKb: Int = 0,
    @SerializedName("created_at") val createdAtIso: String = Instant.now().toString()
)

data class FunkiraDraftPackage(
    @SerializedName("uuid") val uuid: String = UUID.randomUUID().toString(),
    @SerializedName("title") val title: String = "",
    @SerializedName("channel") val channel: String = AgDirectChannel.LEITUNG.code,
    @SerializedName("execution_mode") val executionMode: String = "concept",
    @SerializedName("checklist") val checklist: List<String> = emptyList(),
    @SerializedName("attachments") val attachments: List<FunkiraPackageAttachment> = emptyList(),
    @SerializedName("source") val source: String = "voice",
    @SerializedName("created_at") val createdAtIso: String = Instant.now().toString(),
    @SerializedName("updated_at") val updatedAtIso: String = Instant.now().toString()
) {
    val attachmentCount: Int get() = attachments.size
    val itemCount: Int get() = checklist.size
}

data class FunkiraPackageReleaseResult(
    val ok: Boolean,
    val sentLive: Boolean,
    val packageTitle: String,
    val itemCount: Int,
    val attachmentCount: Int,
    val channel: String,
    val message: String
)

/**
 * Encrypted local Sammelpaket ("Pakete schnueren") staging store for Funkira.
 * Collects distilled requirements, concepts, and camera/gallery photos into cohesive draft packages
 * before dispatching ONE consolidated order to Antigravity via AgDirectInboxManager.
 */
object FunkiraPackageStore {
    const val PACKAGES_FILE_NAME = "funkira_draft_packages.enc.json"
    private const val MAX_PACKAGES = 12
    private const val MAX_ITEMS_PER_PACKAGE = 30
    private const val MAX_ATTACHMENTS_PER_PACKAGE = 6

    private val gson = Gson()
    private val storeLock = Any()
    private val releaseMutex = Mutex()

    private val _packagesFlow = MutableStateFlow<List<FunkiraDraftPackage>>(emptyList())
    val packagesFlow: StateFlow<List<FunkiraDraftPackage>> = _packagesFlow.asStateFlow()

    private fun packagesFile(context: Context): File {
        return File(context.applicationContext.filesDir, PACKAGES_FILE_NAME)
    }

    fun hydrate(context: Context): List<FunkiraDraftPackage> {
        val appCtx = context.applicationContext
        ServiceLocator.ensureInitialized(appCtx)
        return synchronized(storeLock) {
            val loaded = readPackagesUnlocked(packagesFile(appCtx))
            _packagesFlow.value = loaded
            loaded
        }
    }

    fun stageRequirement(
        context: Context,
        item: String? = null,
        items: List<String> = emptyList(),
        packageUuid: String? = null,
        packageTitle: String? = null,
        channel: String = AgDirectChannel.LEITUNG.code,
        executionMode: String = "concept",
        attachment: PreparedAgAttachment? = null,
        attachmentCaption: String = "",
        source: String = "voice"
    ): FunkiraDraftPackage? {
        val appCtx = context.applicationContext
        ServiceLocator.ensureInitialized(appCtx)

        val cleanItems = (items + listOfNotNull(item))
            .map { sanitizeText(it).take(360) }
            .filter { it.isNotEmpty() }
            .distinctBy { it.lowercase(Locale.GERMAN) }

        val cleanTitle = sanitizeText(packageTitle.orEmpty()).take(120)
        if (cleanItems.isEmpty() && cleanTitle.isEmpty() && attachment == null) {
            return null
        }

        val normalizedChannel = AgDirectInboxManager.normalizeChannel(channel)
        val normalizedMode = normalizePackageMode(executionMode)
        val nowIso = Instant.now().toString()

        val resultPkg = synchronized(storeLock) {
            val file = packagesFile(appCtx)
            val list = readPackagesUnlocked(file).toMutableList()
            val targetIdx = resolveTargetPackageIndex(list, packageUuid, cleanTitle, normalizedChannel)

            val updatedPkg = if (targetIdx >= 0) {
                val old = list[targetIdx]
                val mergedItems = mergeChecklist(old.checklist, cleanItems)
                val mergedAtts = mergeAttachment(old.attachments, attachment, attachmentCaption)
                val nextTitle = if (cleanTitle.isNotBlank() && old.title.startsWith("Sammelpaket:", ignoreCase = true)) {
                    cleanTitle
                } else {
                    old.title
                }
                val updated = old.copy(
                    title = nextTitle,
                    executionMode = if (executionMode.isNotBlank()) normalizedMode else old.executionMode,
                    checklist = mergedItems,
                    attachments = mergedAtts,
                    updatedAtIso = nowIso
                )
                list.removeAt(targetIdx)
                list.add(0, updated)
                updated
            } else {
                val effectiveItems = cleanItems.ifEmpty {
                    listOf(cleanTitle.ifBlank { attachmentCaption.ifBlank { "Foto-Hinweis" } })
                }
                val channelLabel = AgDirectInboxManager.resolveChannelEnum(normalizedChannel).chipLabel
                    .replace(Regex("^[^\\p{L}\\p{N}]+\\s*"), "")
                val autoTitle = cleanTitle.ifBlank {
                    val snippet = effectiveItems.first().take(52)
                    "$channelLabel-Paket: $snippet"
                }
                val mergedAtts = mergeAttachment(emptyList(), attachment, attachmentCaption)
                val created = FunkiraDraftPackage(
                    uuid = UUID.randomUUID().toString(),
                    title = autoTitle,
                    channel = normalizedChannel,
                    executionMode = normalizedMode,
                    checklist = effectiveItems.take(MAX_ITEMS_PER_PACKAGE),
                    attachments = mergedAtts,
                    source = source.take(32),
                    createdAtIso = nowIso,
                    updatedAtIso = nowIso
                )
                list.add(0, created)
                created
            }

            val capped = list.take(MAX_PACKAGES)
            writePackagesUnlocked(file, capped)
            _packagesFlow.value = capped
            updatedPkg
        }

        if (attachment != null) {
            FalconCameraController.markLastPhotoAttached()
        }
        return resultPkg
    }

    fun attachMediaToPackage(
        context: Context,
        attachment: PreparedAgAttachment,
        caption: String = "",
        packageUuid: String? = null,
        channel: String = AgDirectChannel.LEITUNG.code
    ): FunkiraDraftPackage? {
        return stageRequirement(
            context = context,
            item = null,
            items = emptyList(),
            packageUuid = packageUuid,
            packageTitle = null,
            channel = channel,
            executionMode = "concept",
            attachment = attachment,
            attachmentCaption = caption.ifBlank { attachment.fileName },
            source = "camera"
        )
    }

    fun removeItem(context: Context, packageUuid: String, index: Int): Boolean {
        val appCtx = context.applicationContext
        ServiceLocator.ensureInitialized(appCtx)
        return synchronized(storeLock) {
            val file = packagesFile(appCtx)
            val list = readPackagesUnlocked(file).toMutableList()
            val idx = list.indexOfFirst { it.uuid == packageUuid }
            if (idx < 0) return@synchronized false

            val old = list[idx]
            val mutableItems = old.checklist.toMutableList()
            if (index !in mutableItems.indices) return@synchronized false
            mutableItems.removeAt(index)

            if (mutableItems.isEmpty() && old.attachments.isEmpty()) {
                list.removeAt(idx)
            } else {
                list[idx] = old.copy(
                    checklist = mutableItems,
                    updatedAtIso = Instant.now().toString()
                )
            }
            writePackagesUnlocked(file, list)
            _packagesFlow.value = list
            true
        }
    }

    fun removeAttachment(context: Context, packageUuid: String, attachmentId: String): Boolean {
        val appCtx = context.applicationContext
        ServiceLocator.ensureInitialized(appCtx)
        return synchronized(storeLock) {
            val file = packagesFile(appCtx)
            val list = readPackagesUnlocked(file).toMutableList()
            val idx = list.indexOfFirst { it.uuid == packageUuid }
            if (idx < 0) return@synchronized false

            val old = list[idx]
            val nextAtts = old.attachments.filterNot { it.id == attachmentId }
            if (nextAtts.size == old.attachments.size) return@synchronized false

            if (old.checklist.isEmpty() && nextAtts.isEmpty()) {
                list.removeAt(idx)
            } else {
                list[idx] = old.copy(
                    attachments = nextAtts,
                    updatedAtIso = Instant.now().toString()
                )
            }
            writePackagesUnlocked(file, list)
            _packagesFlow.value = list
            true
        }
    }

    fun updateMode(context: Context, packageUuid: String, executionMode: String): Boolean {
        val appCtx = context.applicationContext
        ServiceLocator.ensureInitialized(appCtx)
        val norm = normalizePackageMode(executionMode)
        return synchronized(storeLock) {
            val file = packagesFile(appCtx)
            val list = readPackagesUnlocked(file).toMutableList()
            val idx = list.indexOfFirst { it.uuid == packageUuid }
            if (idx < 0) return@synchronized false
            list[idx] = list[idx].copy(
                executionMode = norm,
                updatedAtIso = Instant.now().toString()
            )
            writePackagesUnlocked(file, list)
            _packagesFlow.value = list
            true
        }
    }

    fun deletePackage(context: Context, packageUuid: String): Boolean {
        val appCtx = context.applicationContext
        ServiceLocator.ensureInitialized(appCtx)
        return synchronized(storeLock) {
            val file = packagesFile(appCtx)
            val list = readPackagesUnlocked(file)
            val next = list.filterNot { it.uuid == packageUuid }
            if (next.size == list.size) return@synchronized false
            writePackagesUnlocked(file, next)
            _packagesFlow.value = next
            true
        }
    }

    suspend fun releasePackage(
        context: Context,
        packageUuid: String? = null,
        executionModeOverride: String? = null,
        apiOverride: AgDirectApi? = null
    ): FunkiraPackageReleaseResult = withContext(NonCancellable + Dispatchers.IO) {
        releaseMutex.withLock {
            val appCtx = context.applicationContext
            ServiceLocator.ensureInitialized(appCtx)

            val targetPkg = synchronized(storeLock) {
                val list = readPackagesUnlocked(packagesFile(appCtx))
                _packagesFlow.value = list
                if (!packageUuid.isNullOrBlank()) {
                    list.firstOrNull { it.uuid == packageUuid.trim() }
                } else {
                    list.firstOrNull()
                }
            } ?: return@withLock FunkiraPackageReleaseResult(
                ok = false,
                sentLive = false,
                packageTitle = "",
                itemCount = 0,
                attachmentCount = 0,
                channel = AgDirectChannel.LEITUNG.code,
                message = "Kein offenes Sammelpaket zum Absenden vorhanden."
            )

            val effectiveMode = AgDirectInboxManager.normalizeExecutionMode(
                executionModeOverride?.takeIf { it.isNotBlank() } ?: targetPkg.executionMode
            )
            val safeTitle = targetPkg.title.trim().ifBlank { "Sammelpaket" }.take(240)
            val compiledBody = compilePackageBody(targetPkg).take(14_000)
            val rawCombined = "$safeTitle\n$compiledBody"
            val safeChecklist = targetPkg.checklist
                .map { sanitizeText(it).take(360) }
                .filter { it.isNotEmpty() }
                .take(MAX_ITEMS_PER_PACKAGE)

            val validAttachments = targetPkg.attachments.filter { it.base64Data.isNotBlank() }
            val primaryAttachment = validAttachments.firstOrNull()?.let { att ->
                PreparedAgAttachment(
                    base64Jpeg = att.base64Data,
                    mimeType = att.mimeType.ifBlank { "image/jpeg" },
                    fileName = normalizeSafeAttachmentName(att.fileName, att.mimeType),
                    previewBitmap = null,
                    width = 0,
                    height = 0
                )
            }

            val sentLive = AgDirectInboxManager.submitMessage(
                context = appCtx,
                rawText = rawCombined,
                checklist = safeChecklist,
                executionMode = effectiveMode,
                attachment = primaryAttachment,
                channel = targetPkg.channel,
                clientUuid = targetPkg.uuid,
                apiOverride = apiOverride
            )

            // Dispatch any additional photos (#2..#N) as linked attachments referencing this Sammelpaket
            var allAttachmentsDispatchedLive = sentLive
            if (validAttachments.size > 1) {
                validAttachments.drop(1).forEachIndexed { idx, extraAtt ->
                    val photoIndex = idx + 2
                    val extraPrepared = PreparedAgAttachment(
                        base64Jpeg = extraAtt.base64Data,
                        mimeType = extraAtt.mimeType.ifBlank { "image/jpeg" },
                        fileName = normalizeSafeAttachmentName(extraAtt.fileName, extraAtt.mimeType),
                        previewBitmap = null,
                        width = 0,
                        height = 0
                    )
                    val extraOk = AgDirectInboxManager.submitMessage(
                        context = appCtx,
                        rawText = "Anhang #$photoIndex/${validAttachments.size} zu Paket: $safeTitle\n${extraAtt.caption.take(300)}",
                        checklist = emptyList(),
                        executionMode = effectiveMode,
                        attachment = extraPrepared,
                        channel = targetPkg.channel,
                        clientUuid = "${targetPkg.uuid}_att_$photoIndex",
                        apiOverride = apiOverride
                    )
                    allAttachmentsDispatchedLive = allAttachmentsDispatchedLive && extraOk
                }
            }

            deletePackage(appCtx, targetPkg.uuid)

            val channelLabel = AgDirectInboxManager.resolveChannelEnum(targetPkg.channel).chipLabel
            FunkiraPackageReleaseResult(
                ok = true,
                sentLive = allAttachmentsDispatchedLive,
                packageTitle = targetPkg.title,
                itemCount = targetPkg.checklist.size,
                attachmentCount = validAttachments.size,
                channel = targetPkg.channel,
                message = if (allAttachmentsDispatchedLive) {
                    "Sammelpaket '${targetPkg.title}' (${targetPkg.checklist.size} Punkte, ${validAttachments.size} Fotos) gebündelt in $channelLabel übermittelt."
                } else {
                    "Sammelpaket '${targetPkg.title}' verschlüsselt in der Offline-Warteschlange für $channelLabel gesichert."
                }
            )
        }
    }

    fun compilePackageBody(pkg: FunkiraDraftPackage): String {
        val lines = mutableListOf<String>()
        lines.add("[Sammelpaket: ${pkg.title} | ${pkg.checklist.size} Anforderungen | ${pkg.attachments.size} Anhänge]")
        pkg.checklist.forEachIndexed { idx, item ->
            lines.add("${idx + 1}. $item")
        }
        return lines.joinToString("\n")
    }

    fun buildPackagesPromptDirective(context: Context): String {
        val pkgs = hydrate(context)
        if (pkgs.isEmpty()) return ""
        return buildString {
            append("--- OFFENE SAMMELPAKETE (ENTWÜRFE VOR VERSAND) ---\n")
            pkgs.take(5).forEach { p ->
                append("[PAKET uuid=${p.uuid} kanal=${p.channel} modus=${p.executionMode} fotos=${p.attachmentCount}] ${p.title}: ")
                append(p.checklist.take(5).joinToString(" | "))
                append('\n')
            }
            append("Nutze falcon_stage_to_package zum Buendeln neuer Gedanken in ein offenes Sammelpaket und falcon_release_package erst auf Alinas ausdrueckliches Go zum Absenden.")
        }
    }

    fun buildProactivePackageKick(context: Context): String? {
        val pkgs = hydrate(context)
        if (pkgs.isEmpty()) return null
        val top = pkgs.first()
        val count = maxOf(1, top.checklist.size)
        val preview = top.checklist.take(3).joinToString(", ").take(130).ifBlank { top.title }
        val photoNote = if (top.attachmentCount > 0) " und ${top.attachmentCount} Foto(s)" else ""
        val extra = if (pkgs.size > 1) " (plus ${pkgs.size - 1} weiteres Paket)" else ""
        return "SYSTEM-SAMMELPAKET-HINWEIS FÜR FUNKIRA: Begrüße Alina kurz und melde in exakt einem kurzen, herzlichen Satz, " +
            "dass noch das offene Sammelpaket '${top.title}'$extra mit $count ${if (count == 1) "Punkt" else "Punkten"}$photoNote ($preview) bereitliegt, " +
            "und frage, ob sie es erweitern oder abschicken möchte."
    }

    fun clearAllForTests(context: Context) {
        val appCtx = context.applicationContext
        synchronized(storeLock) {
            val file = packagesFile(appCtx)
            if (file.exists()) file.delete()
            _packagesFlow.value = emptyList()
        }
    }

    internal fun normalizePackageMode(rawMode: String?): String {
        return when (rawMode?.trim()?.lowercase(Locale.ROOT)) {
            "direct", "execute" -> "direct"
            else -> "concept"
        }
    }

    private fun resolveTargetPackageIndex(
        list: List<FunkiraDraftPackage>,
        packageUuid: String?,
        cleanTitle: String,
        normalizedChannel: String
    ): Int {
        if (!packageUuid.isNullOrBlank()) {
            val byUuid = list.indexOfFirst { it.uuid == packageUuid.trim() }
            if (byUuid >= 0) return byUuid
        }
        if (cleanTitle.isNotBlank()) {
            val needle = cleanTitle.lowercase(Locale.GERMAN)
            val byTitle = list.indexOfFirst { pkg ->
                val t = pkg.title.lowercase(Locale.GERMAN)
                t == needle || t.contains(needle) || needle.contains(t)
            }
            if (byTitle >= 0) return byTitle
        }
        if (packageUuid.isNullOrBlank() && cleanTitle.isBlank()) {
            val byChannel = list.indexOfFirst { it.channel == normalizedChannel }
            if (byChannel >= 0) return byChannel
        }
        return -1
    }

    private fun mergeChecklist(existing: List<String>, additions: List<String>): List<String> {
        val out = existing.toMutableList()
        val seen = existing.map { it.trim().lowercase(Locale.GERMAN) }.toMutableSet()
        for (item in additions) {
            val key = item.trim().lowercase(Locale.GERMAN)
            if (key.isNotEmpty() && seen.add(key)) {
                out.add(item)
            }
        }
        return out.take(MAX_ITEMS_PER_PACKAGE)
    }

    private fun mergeAttachment(
        existing: List<FunkiraPackageAttachment>,
        attachment: PreparedAgAttachment?,
        caption: String
    ): List<FunkiraPackageAttachment> {
        if (attachment == null || attachment.base64Jpeg.isBlank()) return existing
        if (existing.size >= MAX_ATTACHMENTS_PER_PACKAGE) return existing
        val headSig = attachment.base64Jpeg.take(48)
        val duplicate = existing.any {
            it.fileName == attachment.fileName && it.base64Data.take(48) == headSig
        }
        if (duplicate) return existing

        val sizeKb = maxOf(1, (attachment.base64Jpeg.length * 3) / (4 * 1024))
        val newAtt = FunkiraPackageAttachment(
            id = UUID.randomUUID().toString(),
            fileName = attachment.fileName,
            mimeType = attachment.mimeType,
            base64Data = attachment.base64Jpeg,
            caption = caption.trim().ifBlank { attachment.fileName }.take(80),
            sizeKb = sizeKb,
            createdAtIso = Instant.now().toString()
        )
        return existing + newAtt
    }

    private fun normalizeSafeAttachmentName(rawName: String, mimeType: String): String {
        val ext = when (mimeType.trim().lowercase(Locale.ROOT)) {
            "image/png" -> "png"
            "image/webp" -> "webp"
            else -> "jpg"
        }
        val stem = rawName
            .substringBeforeLast('.')
            .replace(Regex("[^A-Za-z0-9._-]"), "_")
            .trim('_', '.', ' ')
            .take(48)
            .ifBlank { "paket_foto" }
        return "$stem.$ext"
    }

    private fun sanitizeText(raw: String): String {
        return raw
            .replace(" \u2013 ", ", ")
            .replace(" \u2014 ", ", ")
            .replace(Regex("\\s+"), " ")
            .trim()
    }

    private fun readPackagesUnlocked(file: File): List<FunkiraDraftPackage> {
        return try {
            if (!file.exists()) return emptyList()
            val raw = file.readText(Charsets.UTF_8)
            val json = ServiceLocator.decryptPayloadFromPrefs(raw) ?: return emptyList()
            if (json.isBlank()) return emptyList()
            val type = object : TypeToken<List<FunkiraDraftPackage>>() {}.type
            gson.fromJson<List<FunkiraDraftPackage>>(json, type).orEmpty()
        } catch (_: Throwable) {
            emptyList()
        }
    }

    private fun writePackagesUnlocked(file: File, items: List<FunkiraDraftPackage>) {
        try {
            if (items.isEmpty()) {
                if (file.exists()) file.delete()
                return
            }
            file.parentFile?.mkdirs()
            val encrypted = ServiceLocator.encryptPayloadForPrefs(gson.toJson(items.take(MAX_PACKAGES)))
            file.writeText(encrypted, Charsets.UTF_8)
        } catch (_: Throwable) {
        }
    }
}
