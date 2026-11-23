package de.meinseelenfunke.app.util

import android.content.Context
import android.os.SystemClock
import android.security.keystore.KeyGenParameterSpec
import android.security.keystore.KeyProperties
import de.meinseelenfunke.app.ui.screens.TrichterUploadItem
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.sync.Mutex
import kotlinx.coroutines.sync.withLock
import kotlinx.coroutines.withContext
import org.json.JSONArray
import org.json.JSONObject
import java.io.File
import java.security.KeyStore
import java.security.SecureRandom
import javax.crypto.Cipher
import javax.crypto.KeyGenerator
import javax.crypto.SecretKey
import javax.crypto.spec.GCMParameterSpec

data class TrichterScanDraftSnapshot(
    val scannedPages: List<ByteArray> = emptyList(),
    val nativeCombinedPdfBytes: ByteArray? = null,
    val queuedFiles: List<TrichterUploadItem> = emptyList(),
    val splitIntoSinglePdfs: Boolean = true,
    val trichterTitle: String = "",
    val scannerSessionOpen: Boolean = false
) {
    val hasContent: Boolean
        get() = scannedPages.isNotEmpty() || queuedFiles.isNotEmpty() || (nativeCombinedPdfBytes?.isNotEmpty() == true)
}

/**
 * Encrypted-at-rest session draft store for multi-page document scans.
 * Ensures zero page loss across BiometricLock verification, external ML Kit camera launches,
 * configuration changes, and OS background activity recreation.
 */
object TrichterScanDraftStore {

    private const val DRAFT_DIR_NAME = "trichter_scan_draft"
    private const val META_FILE_NAME = "draft_manifest.enc"
    private const val PREFS_NAME = "trichter_scanner_session_prefs"
    private const val PREF_LAUNCHED_WALL_MS = "scanner_launched_wall_ms"
    private const val PREF_SESSION_OPEN = "scanner_session_open"
    private const val KEY_ALIAS = "funkenwerk_trichter_draft_key_v1"
    private const val GCM_IV_BYTES = 12
    private const val GCM_TAG_BITS = 128

    // Application-surviving IO scope so ML Kit page extraction never cancels during UI recomposition or lock overlay
    val extractionScope = CoroutineScope(SupervisorJob() + Dispatchers.IO)

    private val mutex = Mutex()
    private var hydratedFromDisk = false

    @Volatile
    private var launchedElapsedRealtimeMs: Long = 0L

    @Volatile
    private var jvmFallbackKey: SecretKey? = null

    private val _snapshot = MutableStateFlow(TrichterScanDraftSnapshot())
    val snapshot: StateFlow<TrichterScanDraftSnapshot> = _snapshot.asStateFlow()

    private fun draftDir(context: Context): File {
        return File(context.applicationContext.filesDir, DRAFT_DIR_NAME).apply {
            if (!exists()) mkdirs()
        }
    }

    private fun prefs(context: Context) =
        context.applicationContext.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)

    private fun getOrCreateSecretKey(): SecretKey {
        return try {
            val ks = KeyStore.getInstance("AndroidKeyStore").apply { load(null) }
            val existing = ks.getKey(KEY_ALIAS, null) as? SecretKey
            if (existing != null) {
                existing
            } else {
                val keyGen = KeyGenerator.getInstance(KeyProperties.KEY_ALGORITHM_AES, "AndroidKeyStore")
                val spec = KeyGenParameterSpec.Builder(
                    KEY_ALIAS,
                    KeyProperties.PURPOSE_ENCRYPT or KeyProperties.PURPOSE_DECRYPT
                )
                    .setBlockModes(KeyProperties.BLOCK_MODE_GCM)
                    .setEncryptionPaddings(KeyProperties.ENCRYPTION_PADDING_NONE)
                    .setKeySize(256)
                    .build()
                keyGen.init(spec)
                keyGen.generateKey()
            }
        } catch (_: Throwable) {
            jvmFallbackKey ?: synchronized(this) {
                jvmFallbackKey ?: KeyGenerator.getInstance("AES").apply { init(256) }.generateKey().also {
                    jvmFallbackKey = it
                }
            }
        }
    }

    private fun encryptBytes(plain: ByteArray): ByteArray {
        val cipher = Cipher.getInstance("AES/GCM/NoPadding")
        val key = getOrCreateSecretKey()
        try {
            cipher.init(Cipher.ENCRYPT_MODE, key)
        } catch (_: Exception) {
            val iv = ByteArray(GCM_IV_BYTES).also { SecureRandom().nextBytes(it) }
            cipher.init(Cipher.ENCRYPT_MODE, key, GCMParameterSpec(GCM_TAG_BITS, iv))
        }
        val iv = cipher.iv
        val enc = cipher.doFinal(plain)
        return ByteArray(1 + iv.size + enc.size).apply {
            this[0] = iv.size.toByte()
            System.arraycopy(iv, 0, this, 1, iv.size)
            System.arraycopy(enc, 0, this, 1 + iv.size, enc.size)
        }
    }

    private fun decryptBytes(packed: ByteArray): ByteArray? {
        if (packed.size <= GCM_IV_BYTES + 16) return null
        return try {
            val ivLen = packed[0].toInt() and 0xFF
            if (ivLen !in 12..16 || packed.size <= 1 + ivLen + 16) return null
            val iv = packed.copyOfRange(1, 1 + ivLen)
            val cipherBytes = packed.copyOfRange(1 + ivLen, packed.size)
            val cipher = Cipher.getInstance("AES/GCM/NoPadding")
            cipher.init(Cipher.DECRYPT_MODE, getOrCreateSecretKey(), GCMParameterSpec(GCM_TAG_BITS, iv))
            cipher.doFinal(cipherBytes)
        } catch (_: Throwable) {
            null
        }
    }

    fun markExternalScannerLaunched(context: Context) {
        val nowWall = System.currentTimeMillis()
        launchedElapsedRealtimeMs = try { SystemClock.elapsedRealtime() } catch (_: Throwable) { 0L }
        prefs(context).edit()
            .putLong(PREF_LAUNCHED_WALL_MS, nowWall)
            .putBoolean(PREF_SESSION_OPEN, true)
            .apply()
        _snapshot.value = _snapshot.value.copy(scannerSessionOpen = true)
    }

    fun markExternalScannerReturned(context: Context) {
        launchedElapsedRealtimeMs = 0L
        val keepOpen = _snapshot.value.hasContent || hasDraftFilesOnDisk(context)
        prefs(context).edit()
            .remove(PREF_LAUNCHED_WALL_MS)
            .putBoolean(PREF_SESSION_OPEN, keepOpen)
            .apply()
    }

    fun isWithinActiveScannerGraceWindow(
        context: Context,
        maxDurationMs: Long = 15 * 60_000L
    ): Boolean {
        val wallLaunched = prefs(context).getLong(PREF_LAUNCHED_WALL_MS, 0L)
        if (wallLaunched > 0L) {
            val delta = System.currentTimeMillis() - wallLaunched
            if (delta in 0..maxDurationMs) {
                return true
            }
        }
        val elapsedLaunched = launchedElapsedRealtimeMs
        if (elapsedLaunched > 0L) {
            val nowElapsed = try { SystemClock.elapsedRealtime() } catch (_: Throwable) { 0L }
            if (nowElapsed >= elapsedLaunched && (nowElapsed - elapsedLaunched) <= maxDurationMs) {
                return true
            }
        }
        return false
    }

    fun hasDraftFilesOnDisk(context: Context): Boolean {
        val meta = File(draftDir(context), META_FILE_NAME)
        return meta.exists() && meta.isFile && meta.length() > 0L
    }

    fun hasActiveScannerSessionOrDraft(context: Context): Boolean {
        if (_snapshot.value.hasContent) return true
        if (isWithinActiveScannerGraceWindow(context)) return true
        if (prefs(context).getBoolean(PREF_SESSION_OPEN, false) && hasDraftFilesOnDisk(context)) return true
        return hasDraftFilesOnDisk(context)
    }

    suspend fun ensureHydrated(context: Context): TrichterScanDraftSnapshot = withContext(Dispatchers.IO) {
        mutex.withLock {
            if (hydratedFromDisk && _snapshot.value.hasContent) {
                return@withContext _snapshot.value
            }
            val loaded = readDraftLocked(context)
            hydratedFromDisk = true
            _snapshot.value = loaded
            loaded
        }
    }

    suspend fun updatePreferencesOnly(
        context: Context,
        splitIntoSinglePdfs: Boolean = _snapshot.value.splitIntoSinglePdfs,
        trichterTitle: String = _snapshot.value.trichterTitle
    ) = withContext(Dispatchers.IO) {
        mutex.withLock {
            val next = _snapshot.value.copy(
                splitIntoSinglePdfs = splitIntoSinglePdfs,
                trichterTitle = trichterTitle
            )
            _snapshot.value = next
            if (next.hasContent) {
                persistDraftLocked(context, next)
            }
        }
    }

    suspend fun updateDraft(
        context: Context,
        transform: (TrichterScanDraftSnapshot) -> TrichterScanDraftSnapshot
    ): TrichterScanDraftSnapshot = withContext(Dispatchers.IO) {
        mutex.withLock {
            if (!hydratedFromDisk && !_snapshot.value.hasContent && hasDraftFilesOnDisk(context)) {
                _snapshot.value = readDraftLocked(context)
            }
            hydratedFromDisk = true
            val updated = transform(_snapshot.value)
            val finalSnap = updated.copy(scannerSessionOpen = updated.hasContent)
            _snapshot.value = finalSnap
            if (finalSnap.hasContent) {
                persistDraftLocked(context, finalSnap)
                prefs(context).edit().putBoolean(PREF_SESSION_OPEN, true).apply()
            } else {
                wipeDraftDirLocked(context)
                prefs(context).edit()
                    .remove(PREF_LAUNCHED_WALL_MS)
                    .putBoolean(PREF_SESSION_OPEN, false)
                    .apply()
            }
            finalSnap
        }
    }

    suspend fun clearDraft(context: Context) = withContext(Dispatchers.IO) {
        mutex.withLock {
            launchedElapsedRealtimeMs = 0L
            hydratedFromDisk = true
            _snapshot.value = TrichterScanDraftSnapshot()
            wipeDraftDirLocked(context)
            prefs(context).edit()
                .remove(PREF_LAUNCHED_WALL_MS)
                .putBoolean(PREF_SESSION_OPEN, false)
                .apply()
        }
    }

    private fun persistDraftLocked(context: Context, snap: TrichterScanDraftSnapshot) {
        try {
            val dir = draftDir(context)
            val expectedNames = mutableSetOf<String>()

            val pagesJson = JSONArray()
            snap.scannedPages.forEachIndexed { idx, bytes ->
                val fName = "page_$idx.bin"
                File(dir, fName).writeBytes(encryptBytes(bytes))
                expectedNames.add(fName)
                pagesJson.put(fName)
            }

            var combinedFileName: String? = null
            if (snap.nativeCombinedPdfBytes != null && snap.nativeCombinedPdfBytes.isNotEmpty()) {
                combinedFileName = "combined_native.bin"
                File(dir, combinedFileName).writeBytes(encryptBytes(snap.nativeCombinedPdfBytes))
                expectedNames.add(combinedFileName)
            }

            val filesJson = JSONArray()
            snap.queuedFiles.forEachIndexed { idx, item ->
                val fName = "queued_$idx.bin"
                File(dir, fName).writeBytes(encryptBytes(item.bytes))
                expectedNames.add(fName)
                filesJson.put(JSONObject().apply {
                    put("bin", fName)
                    put("id", item.id)
                    put("file_name", item.fileName)
                    put("mime_type", item.mimeType)
                })
            }

            val manifest = JSONObject().apply {
                put("split_into_single_pdfs", snap.splitIntoSinglePdfs)
                put("trichter_title", snap.trichterTitle)
                put("pages", pagesJson)
                if (combinedFileName != null) put("combined_pdf", combinedFileName)
                put("queued_files", filesJson)
                put("saved_at_ms", System.currentTimeMillis())
            }
            File(dir, META_FILE_NAME).writeBytes(encryptBytes(manifest.toString().toByteArray(Charsets.UTF_8)))
            expectedNames.add(META_FILE_NAME)

            dir.listFiles()?.forEach { f ->
                if (f.isFile && f.name !in expectedNames) {
                    try { f.delete() } catch (_: Throwable) {}
                }
            }
        } catch (_: Throwable) {
        }
    }

    private fun readDraftLocked(context: Context): TrichterScanDraftSnapshot {
        return try {
            val dir = draftDir(context)
            val metaFile = File(dir, META_FILE_NAME)
            if (!metaFile.exists() || !metaFile.isFile) {
                return TrichterScanDraftSnapshot()
            }
            val plainMeta = decryptBytes(metaFile.readBytes()) ?: return TrichterScanDraftSnapshot()
            val manifest = JSONObject(String(plainMeta, Charsets.UTF_8))

            val pages = mutableListOf<ByteArray>()
            val pagesArr = manifest.optJSONArray("pages") ?: JSONArray()
            for (i in 0 until pagesArr.length()) {
                val binName = pagesArr.optString(i, "")
                if (binName.matches(Regex("^page_\\d+\\.bin$"))) {
                    val f = File(dir, binName)
                    if (f.exists()) {
                        decryptBytes(f.readBytes())?.takeIf { it.isNotEmpty() }?.let { pages.add(it) }
                    }
                }
            }

            val combinedName = manifest.optString("combined_pdf", "")
            val combinedBytes = if (combinedName == "combined_native.bin" && File(dir, combinedName).exists()) {
                decryptBytes(File(dir, combinedName).readBytes())
            } else null

            val queued = mutableListOf<TrichterUploadItem>()
            val filesArr = manifest.optJSONArray("queued_files") ?: JSONArray()
            for (i in 0 until filesArr.length()) {
                val obj = filesArr.optJSONObject(i) ?: continue
                val binName = obj.optString("bin", "")
                if (binName.matches(Regex("^queued_\\d+\\.bin$"))) {
                    val f = File(dir, binName)
                    if (f.exists()) {
                        val dec = decryptBytes(f.readBytes())
                        if (dec != null && dec.isNotEmpty()) {
                            queued.add(
                                TrichterUploadItem(
                                    id = obj.optString("id").ifBlank { java.util.UUID.randomUUID().toString() },
                                    bytes = dec,
                                    fileName = obj.optString("file_name", "Dokument.pdf"),
                                    mimeType = obj.optString("mime_type", "application/pdf")
                                )
                            )
                        }
                    }
                }
            }

            val hasData = pages.isNotEmpty() || queued.isNotEmpty() || (combinedBytes?.isNotEmpty() == true)
            TrichterScanDraftSnapshot(
                scannedPages = pages,
                nativeCombinedPdfBytes = combinedBytes,
                queuedFiles = queued,
                splitIntoSinglePdfs = manifest.optBoolean("split_into_single_pdfs", true),
                trichterTitle = manifest.optString("trichter_title", ""),
                scannerSessionOpen = hasData
            )
        } catch (_: Throwable) {
            TrichterScanDraftSnapshot()
        }
    }

    private fun wipeDraftDirLocked(context: Context) {
        try {
            val dir = draftDir(context)
            dir.listFiles()?.forEach { f ->
                try {
                    if (f.isFile && f.length() in 1..(32L * 1024L * 1024L)) {
                        java.io.RandomAccessFile(f, "rw").use { raf ->
                            val zeros = ByteArray(minOf(f.length().toInt(), 32768))
                            raf.seek(0)
                            raf.write(zeros)
                            raf.fd.sync()
                        }
                    }
                    f.delete()
                } catch (_: Throwable) {
                    try { f.delete() } catch (_: Throwable) {}
                }
            }
        } catch (_: Throwable) {
        }
    }
}
