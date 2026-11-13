package de.meinseelenfunke.app.util

import android.content.Context
import android.content.Intent
import android.net.Uri
import android.webkit.MimeTypeMap
import android.widget.Toast
import androidx.core.content.FileProvider
import de.meinseelenfunke.app.di.ServiceLocator
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import okhttp3.Request
import java.io.File
import java.io.FileOutputStream
import java.util.Locale

object SecureFileOpener {
    private const val SECURE_DIR_NAME = "secure_downloads"
    private const val MAX_DOWNLOAD_BYTES = 25L * 1024L * 1024L // 25 MB
    private const val STALE_FILE_MAX_AGE_MS = 15L * 60L * 1000L // 15 minutes
    private val ioScope = CoroutineScope(SupervisorJob() + Dispatchers.IO)

    /**
     * Downloads an authenticated backend file via Authorization: Bearer header
     * (never putting the Sanctum token into a browser URL!) into a scoped cache directory
     * and opens it via FileProvider with temporary read permission.
     */
    fun openAuthenticatedApiFile(
        context: Context,
        relativeApiEndpoint: String,
        fallbackFileName: String = "dokument.pdf"
    ) {
        val appCtx = context.applicationContext
        ServiceLocator.ensureInitialized(appCtx)
        val token = ServiceLocator.getAuthToken()
        if (token.isNullOrBlank()) {
            Toast.makeText(context, "Nicht angemeldet.", Toast.LENGTH_SHORT).show()
            return
        }

        Toast.makeText(context, "Dokument wird sicher geladen...", Toast.LENGTH_SHORT).show()

        ioScope.launch {
            val result = downloadProtectedFile(appCtx, relativeApiEndpoint, fallbackFileName, token)
            withContext(Dispatchers.Main) {
                result.onSuccess { (file, mimeType) ->
                    try {
                        val uri = FileProvider.getUriForFile(
                            appCtx,
                            "${appCtx.packageName}.fileprovider",
                            file
                        )
                        val viewIntent = Intent(Intent.ACTION_VIEW).apply {
                            setDataAndType(uri, mimeType)
                            addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
                            addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
                        }
                        appCtx.startActivity(viewIntent)
                    } catch (_: Exception) {
                        Toast.makeText(
                            appCtx,
                            "Keine passende App zum Öffnen dieses Dateityps gefunden.",
                            Toast.LENGTH_LONG
                        ).show()
                    }
                }.onFailure { err ->
                    Toast.makeText(
                        appCtx,
                        err.localizedMessage ?: "Dokument konnte nicht geladen werden.",
                        Toast.LENGTH_LONG
                    ).show()
                }
            }
        }
    }

    private fun downloadProtectedFile(
        context: Context,
        relativeApiEndpoint: String,
        fallbackFileName: String,
        token: String
    ): Result<Pair<File, String>> {
        return try {
            purgeStaleDownloads(context)

            val baseUrl = ServiceLocator.getBaseUrl().trimEnd('/')
            val cleanEndpoint = relativeApiEndpoint.trimStart('/')
            val fullUrl = "$baseUrl/$cleanEndpoint"

            if (!ServiceLocator.isAllowedApiRequestUrl(fullUrl)) {
                return Result.failure(SecurityException("Unzulässige Ziel-URL blockiert."))
            }

            val client = ServiceLocator.buildAuthenticatedDownloadClient()
            val request = Request.Builder()
                .url(fullUrl)
                .header("Authorization", "Bearer $token")
                .get()
                .build()

            client.newCall(request).execute().use { response ->
                if (!response.isSuccessful) {
                    return Result.failure(Exception("Abruf fehlgeschlagen (HTTP ${response.code})."))
                }

                val body = response.body ?: return Result.failure(Exception("Leere Server-Antwort."))
                val contentLength = body.contentLength()
                if (contentLength > MAX_DOWNLOAD_BYTES) {
                    return Result.failure(Exception("Datei überschreitet das Sicherheitslimit von 25 MB."))
                }

                val contentTypeHeader = response.header("Content-Type")
                    ?.substringBefore(';')
                    ?.trim()
                    ?.lowercase(Locale.ROOT)
                    .orEmpty()

                val dispositionName = extractFilenameFromDisposition(response.header("Content-Disposition"))
                val rawName = dispositionName?.ifBlank { null } ?: fallbackFileName
                val safeName = sanitizeFileName(rawName, contentTypeHeader)

                val dir = File(context.cacheDir, SECURE_DIR_NAME).apply { mkdirs() }
                val targetFile = File(dir, "${System.currentTimeMillis()}_$safeName")
                val canonicalDir = dir.canonicalPath + File.separator
                if (!targetFile.canonicalPath.startsWith(canonicalDir)) {
                    return Result.failure(SecurityException("Ungültiger Dateiname blockiert."))
                }

                var totalBytes = 0L
                body.byteStream().use { input ->
                    FileOutputStream(targetFile).use { output ->
                        val buffer = ByteArray(16 * 1024)
                        while (true) {
                            val read = input.read(buffer)
                            if (read <= 0) break
                            totalBytes += read
                            if (totalBytes > MAX_DOWNLOAD_BYTES) {
                                output.close()
                                targetFile.delete()
                                return Result.failure(Exception("Datei überschreitet das Sicherheitslimit von 25 MB."))
                            }
                            output.write(buffer, 0, read)
                        }
                    }
                }

                val ext = targetFile.extension.lowercase(Locale.ROOT)
                val resolvedMime = when {
                    contentTypeHeader.isNotBlank() && contentTypeHeader != "application/octet-stream" -> contentTypeHeader
                    ext == "svg" -> "image/svg+xml"
                    ext == "pdf" -> "application/pdf"
                    else -> MimeTypeMap.getSingleton().getMimeTypeFromExtension(ext) ?: "application/octet-stream"
                }

                Result.success(Pair(targetFile, resolvedMime))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    /**
     * Opens an external URL safely (strictly https://, mailto:, or allowed local dev http:// host)
     * with CATEGORY_BROWSABLE to block intent://, file://, content://, and javascript: injection.
     */
    fun openSafeExternalUrl(context: Context, rawUrl: String?) {
        if (rawUrl.isNullOrBlank()) return
        val trimmed = rawUrl.trim()
        val parsed = try {
            Uri.parse(trimmed)
        } catch (_: Exception) {
            null
        } ?: return

        val scheme = parsed.scheme?.lowercase(Locale.ROOT) ?: return
        val host = parsed.host?.lowercase(Locale.ROOT).orEmpty()

        val isSafe = when (scheme) {
            "https" -> host.isNotBlank()
            "mailto" -> true
            "http" -> ServiceLocator.isAllowedCleartextHost(host)
            else -> false
        }

        if (!isSafe) {
            Toast.makeText(context, "Unsicherer Link blockiert.", Toast.LENGTH_SHORT).show()
            return
        }

        try {
            val intent = Intent(Intent.ACTION_VIEW, parsed).apply {
                if (scheme == "https" || scheme == "http") {
                    addCategory(Intent.CATEGORY_BROWSABLE)
                }
                addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
            }
            context.startActivity(intent)
        } catch (_: Exception) {
            Toast.makeText(context, "Link konnte nicht geöffnet werden.", Toast.LENGTH_SHORT).show()
        }
    }

    fun purgeStaleDownloads(context: Context, deleteAll: Boolean = false) {
        try {
            val dir = File(context.cacheDir, SECURE_DIR_NAME)
            if (!dir.exists()) return
            val now = System.currentTimeMillis()
            dir.listFiles()?.forEach { file ->
                if (deleteAll || (now - file.lastModified()) > STALE_FILE_MAX_AGE_MS) {
                    file.delete()
                }
            }
        } catch (_: Exception) {
        }
    }

    private fun extractFilenameFromDisposition(disposition: String?): String? {
        if (disposition.isNullOrBlank()) return null
        val match = Regex("filename\\*?=(?:UTF-8'')?\"?([^\";]+)\"?", RegexOption.IGNORE_CASE)
            .find(disposition)
        return match?.groupValues?.getOrNull(1)?.trim()
    }

    fun sanitizeFileName(rawName: String, contentType: String = ""): String {
        val base = rawName.substringAfterLast('/').substringAfterLast('\\')
            .replace("..", "_")
            .replace(Regex("[^A-Za-z0-9._-]"), "_")
            .trim('_')
            .take(100)
            .ifBlank { "dokument" }

        return if (base.contains('.')) {
            base
        } else {
            val ext = when (contentType) {
                "application/pdf" -> ".pdf"
                "image/svg+xml" -> ".svg"
                "image/jpeg" -> ".jpg"
                "image/png" -> ".png"
                "image/webp" -> ".webp"
                else -> ""
            }
            base + ext
        }
    }
}
