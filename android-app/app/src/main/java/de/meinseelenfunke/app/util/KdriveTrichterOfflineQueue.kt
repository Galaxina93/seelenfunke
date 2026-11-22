package de.meinseelenfunke.app.util

import android.content.Context
import android.security.keystore.KeyGenParameterSpec
import android.security.keystore.KeyProperties
import de.meinseelenfunke.app.data.repository.FinanceRepository
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.sync.Mutex
import kotlinx.coroutines.sync.withLock
import kotlinx.coroutines.withContext
import org.json.JSONObject
import retrofit2.HttpException
import java.io.File
import java.io.IOException
import java.security.KeyStore
import java.security.SecureRandom
import java.util.UUID
import javax.crypto.Cipher
import javax.crypto.KeyGenerator
import javax.crypto.SecretKey
import javax.crypto.spec.GCMParameterSpec

data class QueuedTrichterScanMeta(
    val id: String,
    val fileName: String,
    val mimeType: String,
    val customTitle: String?,
    val source: String,
    val batchIndex: Int?,
    val batchTotal: Int?,
    val createdAtMs: Long
)

object KdriveTrichterOfflineQueue {

    private const val QUEUE_DIR_NAME = "kdrive_trichter_queue"
    private const val KEY_ALIAS = "funkenwerk_kdrive_trichter_key_v1"
    private const val GCM_IV_BYTES = 12
    private const val GCM_TAG_BITS = 128
    private val SAFE_UUID_REGEX = Regex("^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$")

    private val mutex = Mutex()
    private val _pendingCount = MutableStateFlow(0)
    val pendingCount: StateFlow<Int> = _pendingCount.asStateFlow()

    @Volatile
    private var jvmFallbackKey: SecretKey? = null

    private fun queueDir(context: Context): File {
        return File(context.filesDir, QUEUE_DIR_NAME).apply {
            if (!exists()) mkdirs()
        }
    }

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

    fun refreshCount(context: Context): Int {
        val dir = queueDir(context)
        val count = dir.listFiles { f -> f.isFile && f.name.endsWith(".json") }?.size ?: 0
        _pendingCount.value = count
        return count
    }

    fun isTransientNetworkFailure(error: Throwable?): Boolean {
        if (error == null) return false
        if (error is IOException) return true
        if (error is HttpException) {
            return error.code() in listOf(408, 429, 500, 502, 503, 504)
        }
        val msg = error.message?.lowercase() ?: ""
        return msg.contains("nicht erreichbar") ||
            msg.contains("zeitüberschreitung") ||
            msg.contains("timeout") ||
            msg.contains("internetverbindung")
    }

    suspend fun enqueue(
        context: Context,
        bytes: ByteArray,
        fileName: String,
        mimeType: String,
        customTitle: String?,
        source: String,
        batchIndex: Int? = null,
        batchTotal: Int? = null
    ): Boolean = withContext(Dispatchers.IO) {
        if (bytes.isEmpty()) return@withContext false
        mutex.withLock {
            try {
                val dir = queueDir(context)
                val id = UUID.randomUUID().toString()
                val binFile = File(dir, "$id.bin")
                val metaFile = File(dir, "$id.json")

                binFile.writeBytes(encryptBytes(bytes))
                val metaJson = JSONObject().apply {
                    put("id", id)
                    put("file_name", fileName)
                    put("mime_type", mimeType)
                    put("custom_title", customTitle ?: "")
                    put("source", source)
                    if (batchIndex != null) put("batch_index", batchIndex)
                    if (batchTotal != null) put("batch_total", batchTotal)
                    put("created_at_ms", System.currentTimeMillis())
                }
                val metaUtf8 = metaJson.toString().toByteArray(Charsets.UTF_8)
                try {
                    metaFile.writeBytes(encryptBytes(metaUtf8))
                } finally {
                    metaUtf8.fill(0)
                }
                refreshCount(context)
                true
            } catch (_: Throwable) {
                false
            }
        }
    }

    private fun wipeAndDeleteFile(file: File) {
        try {
            if (file.exists() && file.isFile) {
                val len = file.length()
                if (len in 1..(64L * 1024L * 1024L)) {
                    java.io.RandomAccessFile(file, "rw").use { raf ->
                        val zeros = ByteArray(minOf(len.toInt(), 65536))
                        var remaining = len
                        raf.seek(0)
                        while (remaining > 0) {
                            val chunk = minOf(remaining, zeros.size.toLong()).toInt()
                            raf.write(zeros, 0, chunk)
                            remaining -= chunk
                        }
                        raf.fd.sync()
                    }
                }
                file.delete()
            }
        } catch (_: Throwable) {
            try { file.delete() } catch (_: Throwable) {}
        }
    }

    suspend fun flushPending(
        context: Context,
        financeRepository: FinanceRepository
    ): Int = withContext(Dispatchers.IO) {
        mutex.withLock {
            val dir = queueDir(context)
            val metaFiles = dir.listFiles { f -> f.isFile && f.name.endsWith(".json") }
                ?.mapNotNull { file ->
                    try {
                        val rawMeta = file.readBytes()
                        val decMeta = decryptBytes(rawMeta)
                        val jsonStr = if (decMeta != null) {
                            try {
                                String(decMeta, Charsets.UTF_8)
                            } finally {
                                decMeta.fill(0)
                            }
                        } else {
                            String(rawMeta, Charsets.UTF_8)
                        }
                        val json = JSONObject(jsonStr)
                        val id = json.optString("id", "").trim()
                        if (!SAFE_UUID_REGEX.matches(id)) {
                            wipeAndDeleteFile(file)
                            return@mapNotNull null
                        }
                        QueuedTrichterScanMeta(
                            id = id,
                            fileName = json.optString("file_name", "Scan.pdf"),
                            mimeType = json.optString("mime_type", "application/pdf"),
                            customTitle = json.optString("custom_title", "").ifBlank { null },
                            source = json.optString("source", "android_offline_queue"),
                            batchIndex = if (json.has("batch_index")) json.optInt("batch_index") else null,
                            batchTotal = if (json.has("batch_total")) json.optInt("batch_total") else null,
                            createdAtMs = json.optLong("created_at_ms", 0L)
                        ) to file
                    } catch (_: Throwable) {
                        wipeAndDeleteFile(file)
                        null
                    }
                }
                ?.sortedBy { it.first.createdAtMs }
                ?: emptyList()

            var uploaded = 0
            for ((meta, metaFile) in metaFiles) {
                val binFile = File(dir, "${meta.id}.bin")
                if (!binFile.exists()) {
                    wipeAndDeleteFile(metaFile)
                    continue
                }
                val plainBytes = decryptBytes(binFile.readBytes())
                if (plainBytes == null || plainBytes.isEmpty()) {
                    wipeAndDeleteFile(binFile)
                    wipeAndDeleteFile(metaFile)
                    continue
                }

                try {
                    val res = financeRepository.sendToKdriveTrichterDirect(
                        fileBytes = plainBytes,
                        fileName = meta.fileName,
                        mimeType = meta.mimeType,
                        customTitle = meta.customTitle,
                        source = meta.source,
                        batchIndex = meta.batchIndex,
                        batchTotal = meta.batchTotal
                    )
                    if (res.isSuccess) {
                        wipeAndDeleteFile(binFile)
                        wipeAndDeleteFile(metaFile)
                        uploaded++
                    } else if (isTransientNetworkFailure(res.exceptionOrNull())) {
                        // Stop flushing until network returns
                        break
                    } else {
                        // Permanent validation rejection (e.g. unsupported file type) -> drop item so queue isn't poisoned
                        val errMsg = res.exceptionOrNull()?.message ?: ""
                        if (!errMsg.contains("Sitzung abgelaufen")) {
                            wipeAndDeleteFile(binFile)
                            wipeAndDeleteFile(metaFile)
                        } else {
                            break
                        }
                    }
                } finally {
                    plainBytes.fill(0)
                }
            }

            refreshCount(context)
            uploaded
        }
    }
}
