package de.meinseelenfunke.app.util

import android.content.Context
import android.content.Intent
import android.content.pm.PackageInfo
import android.content.pm.PackageManager
import android.net.Uri
import android.os.Build
import android.os.Handler
import android.os.Looper
import android.provider.Settings
import androidx.core.content.FileProvider
import androidx.core.content.pm.PackageInfoCompat
import de.meinseelenfunke.app.di.ServiceLocator
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import okhttp3.OkHttpClient
import okhttp3.Request
import org.json.JSONObject
import java.io.File
import java.io.FileInputStream
import java.io.FileOutputStream
import java.security.MessageDigest
import java.util.Locale
import java.util.concurrent.TimeUnit
import java.util.concurrent.atomic.AtomicBoolean

data class OtaReleaseManifest(
    val versionCode: Long,
    val versionName: String,
    val sha256Hex: String,
    val fileSize: Long,
    val fileName: String,
    val publishedAt: String,
    val releaseNotes: String = ""
)

sealed class OtaUpdateState {
    object Idle : OtaUpdateState()
    object Checking : OtaUpdateState()
    data class UpToDate(val currentVersionName: String, val currentVersionCode: Long) : OtaUpdateState()
    data class Available(val manifest: OtaReleaseManifest) : OtaUpdateState()
    data class Downloading(val manifest: OtaReleaseManifest, val progressPercent: Int) : OtaUpdateState()
    data class AwaitingInstallPermission(val manifest: OtaReleaseManifest, val verifiedApkPath: String) : OtaUpdateState()
    data class InstallerLaunched(val manifest: OtaReleaseManifest) : OtaUpdateState()
    data class Error(val message: String) : OtaUpdateState()
}

private enum class ApkCertCheckResult {
    VERIFIED,
    LEGACY_DEBUG_SWITCH_REQUIRED,
    INVALID
}

object AppUpdateManager {

    private const val CACHE_SUBDIR = "apk_updates"
    internal const val MIN_APK_BYTES = 512L * 1024L
    internal const val MAX_APK_BYTES = 95L * 1024L * 1024L
    internal const val PINNED_RELEASE_CERT_SHA256 = "e4c85cdd79648fa19430c91b47e1db036544d60d2fa6136a0c5106300c849064"

    private val scope = CoroutineScope(SupervisorJob() + Dispatchers.IO)
    private val mainHandler = Handler(Looper.getMainLooper())
    private val checkInFlight = AtomicBoolean(false)
    private val downloadInFlight = AtomicBoolean(false)

    private val defaultHttpClient: OkHttpClient by lazy {
        OkHttpClient.Builder()
            .connectTimeout(12, TimeUnit.SECONDS)
            .readTimeout(90, TimeUnit.SECONDS)
            .followRedirects(false)
            .followSslRedirects(false)
            .build()
    }

    @Volatile
    internal var httpClientOverride: OkHttpClient? = null

    @Volatile
    internal var baseUrlOverrideForTests: String? = null

    @Volatile
    internal var authTokenOverrideForTests: String? = null

    private fun activeHttpClient(): OkHttpClient = httpClientOverride ?: defaultHttpClient

    private val _state = MutableStateFlow<OtaUpdateState>(OtaUpdateState.Idle)
    val state: StateFlow<OtaUpdateState> = _state.asStateFlow()

    private val _promptDismissed = MutableStateFlow(false)
    val promptDismissed: StateFlow<Boolean> = _promptDismissed.asStateFlow()

    @Volatile
    private var lastManifestTrack: OtaReleaseManifest? = null

    fun getLastManifest(): OtaReleaseManifest? = lastManifestTrack

    fun dismissStartupPrompt() {
        _promptDismissed.value = true
    }

    fun resetOnLogout(context: Context) {
        lastManifestTrack = null
        _promptDismissed.value = false
        _state.value = OtaUpdateState.Idle
        val appCtx = context.applicationContext
        scope.launch {
            try {
                File(appCtx.cacheDir, CACHE_SUBDIR).deleteRecursively()
            } catch (_: Exception) {
            }
        }
    }

    fun onAppStartCheck(context: Context) {
        val current = _state.value
        if (
            downloadInFlight.get() ||
            current is OtaUpdateState.Downloading ||
            current is OtaUpdateState.AwaitingInstallPermission
        ) {
            return
        }
        val appCtx = context.applicationContext
        if (authTokenOverrideForTests == null) {
            ServiceLocator.ensureInitialized(appCtx)
            if (!ServiceLocator.authRepository.isLoggedIn() || ServiceLocator.getUserType() != "admin") {
                return
            }
        }
        _promptDismissed.value = false
        checkForUpdate(appCtx, silent = true)
    }

    fun getInstalledVersionInfo(context: Context): Pair<String, Long> {
        return try {
            val pInfo = context.packageManager.getPackageInfo(context.packageName, 0)
            val vName = pInfo.versionName ?: "1.0"
            val vCode = PackageInfoCompat.getLongVersionCode(pInfo)
            Pair(vName, vCode)
        } catch (_: Exception) {
            Pair("1.0", 1L)
        }
    }

    fun cleanupCacheAsync(context: Context) {
        val appCtx = context.applicationContext
        scope.launch {
            if (downloadInFlight.get()) return@launch
            try {
                val (_, installedCode) = getInstalledVersionInfo(appCtx)
                val nowMs = System.currentTimeMillis()
                val staleCutoffMs = 6L * 3600L * 1000L
                val dir = File(appCtx.cacheDir, CACHE_SUBDIR)
                if (dir.exists() && dir.isDirectory) {
                    val verRegex = Regex("^Funkenwerk_v(\\d+)\\.apk$")
                    dir.listFiles()?.forEach { file ->
                        try {
                            val name = file.name
                            val match = verRegex.matchEntire(name)
                            val apkVer = match?.groupValues?.getOrNull(1)?.toLongOrNull()
                            val isExpired = (nowMs - file.lastModified()) > staleCutoffMs
                            val isAlreadyInstalledOrPart = name.endsWith(".part") || apkVer == null || apkVer <= installedCode
                            if (isAlreadyInstalledOrPart || isExpired) {
                                file.delete()
                            }
                        } catch (_: Exception) {
                        }
                    }
                }
            } catch (_: Exception) {
            }
        }
    }

    fun checkForUpdate(context: Context, silent: Boolean = false) {
        val appCtx = context.applicationContext
        if (downloadInFlight.get() || _state.value is OtaUpdateState.Downloading) return
        if (!checkInFlight.compareAndSet(false, true)) return

        scope.launch {
            try {
                if (!silent) {
                    _promptDismissed.value = false
                    _state.value = OtaUpdateState.Checking
                }

                val token = resolveAuthToken(appCtx)
                if (token.isNullOrEmpty()) {
                    if (!silent) _state.value = OtaUpdateState.Error("Keine aktive Sitzung.")
                    return@launch
                }

                val baseUrl = resolveTrustedBaseUrl()
                if (baseUrl == null) {
                    if (!silent) _state.value = OtaUpdateState.Error("Server-Host nicht freigegeben.")
                    return@launch
                }

                val normalizedBase = if (baseUrl.endsWith("/")) baseUrl else "$baseUrl/"
                val checkUrl = "${normalizedBase}funki/system/app-update/check"

                val req = Request.Builder()
                    .url(checkUrl)
                    .header("Authorization", "Bearer $token")
                    .header("Accept", "application/json")
                    .get()
                    .build()

                activeHttpClient().newCall(req).execute().use { resp ->
                    // Do not overwrite an active download or permission wait started while HTTP was in flight
                    if (
                        downloadInFlight.get() ||
                        _state.value is OtaUpdateState.Downloading ||
                        _state.value is OtaUpdateState.AwaitingInstallPermission
                    ) {
                        return@use
                    }

                    if (!resp.isSuccessful) {
                        if (!silent) {
                            _state.value = OtaUpdateState.Error("Prüfung fehlgeschlagen (HTTP ${resp.code}).")
                        }
                        return@use
                    }

                    val bodyStr = resp.body?.string().orEmpty()
                    val json = JSONObject(bodyStr)
                    val hasRelease = json.optBoolean("has_release", false)
                    val (curName, curCode) = getInstalledVersionInfo(appCtx)

                    if (!hasRelease) {
                        lastManifestTrack = null
                        _state.value = OtaUpdateState.UpToDate(curName, curCode)
                        return@use
                    }

                    val data = json.optJSONObject("data")
                    if (data == null) {
                        lastManifestTrack = null
                        _state.value = OtaUpdateState.UpToDate(curName, curCode)
                        return@use
                    }

                    val remotePkg = data.optString("package_name", "")
                    val remoteCode = data.optLong("version_code", 0L)
                    val remoteName = data.optString("version_name", "").trim()
                    val remoteSha = data.optString("sha256", "").trim().lowercase(Locale.ROOT)
                    val remoteSize = data.optLong("file_size", 0L)
                    val remoteFile = data.optString("file_name", "Funkenwerk-Update.apk").trim()
                    val remoteDate = data.optString("published_at", "").trim()
                    val remoteNotes = data.optString("release_notes", "").trim()

                    if (
                        remotePkg != appCtx.packageName ||
                        remoteCode <= 0L ||
                        remoteName.isEmpty() ||
                        remoteSha.length != 64 ||
                        hexToBytes(remoteSha) == null ||
                        remoteSize !in MIN_APK_BYTES..MAX_APK_BYTES
                    ) {
                        if (!silent) {
                            _state.value = OtaUpdateState.Error("Ungültiges Update-Manifest vom Server.")
                        }
                        return@use
                    }

                    if (remoteCode > curCode) {
                        val manifest = OtaReleaseManifest(
                            versionCode = remoteCode,
                            versionName = remoteName,
                            sha256Hex = remoteSha,
                            fileSize = remoteSize,
                            fileName = remoteFile,
                            publishedAt = remoteDate,
                            releaseNotes = remoteNotes
                        )
                        lastManifestTrack = manifest
                        _state.value = OtaUpdateState.Available(manifest)
                    } else {
                        lastManifestTrack = null
                        _state.value = OtaUpdateState.UpToDate(curName, curCode)
                    }
                }
            } catch (_: Exception) {
                if (!silent && !downloadInFlight.get()) {
                    _state.value = OtaUpdateState.Error("Server aktuell nicht erreichbar.")
                }
            } finally {
                checkInFlight.set(false)
            }
        }
    }

    fun downloadAndInstall(context: Context, manifest: OtaReleaseManifest) {
        val appCtx = context.applicationContext
        if (!downloadInFlight.compareAndSet(false, true)) return
        lastManifestTrack = manifest

        scope.launch {
            _state.value = OtaUpdateState.Downloading(manifest, 0)
            val updateDir = File(appCtx.cacheDir, CACHE_SUBDIR)
            if (!updateDir.exists()) {
                updateDir.mkdirs()
            }
            val canonicalDir = updateDir.canonicalFile
            val partFile = File(canonicalDir, "funkenwerk_ota_${manifest.versionCode}.apk.part").canonicalFile
            val finalApk = File(canonicalDir, "Funkenwerk_v${manifest.versionCode}.apk").canonicalFile

            if (!partFile.path.startsWith(canonicalDir.path) || !finalApk.path.startsWith(canonicalDir.path)) {
                downloadInFlight.set(false)
                _state.value = OtaUpdateState.Error("Ungültiger lokaler Update-Pfad.")
                return@launch
            }

            try {
                partFile.delete()

                // Fast-path: if exact verified APK is already cached locally, verify all 4 gates and launch immediately
                val expectedCachedBytes = hexToBytes(manifest.sha256Hex)
                if (
                    finalApk.exists() &&
                    finalApk.isFile &&
                    finalApk.length() == manifest.fileSize &&
                    hasZipMagicHeader(finalApk) &&
                    expectedCachedBytes != null
                ) {
                    val actualCachedBytes = computeFileSha256(finalApk)
                    if (
                        actualCachedBytes != null &&
                        MessageDigest.isEqual(actualCachedBytes, expectedCachedBytes) &&
                        verifyPackageAndSigningCertificate(appCtx, finalApk) == ApkCertCheckResult.VERIFIED
                    ) {
                        triggerInstallerOrPermission(appCtx, manifest, finalApk)
                        return@launch
                    }
                }

                finalApk.delete()

                val token = resolveAuthToken(appCtx)
                val baseUrl = resolveTrustedBaseUrl()
                if (token.isNullOrEmpty() || baseUrl == null) {
                    _state.value = OtaUpdateState.Error("Authentifizierung fehlgeschlagen.")
                    return@launch
                }

                val normalizedBase = if (baseUrl.endsWith("/")) baseUrl else "$baseUrl/"
                val downloadUrl = "${normalizedBase}funki/system/app-update/download"

                val req = Request.Builder()
                    .url(downloadUrl)
                    .header("Authorization", "Bearer $token")
                    .get()
                    .build()

                activeHttpClient().newCall(req).execute().use { resp ->
                    if (!resp.isSuccessful) {
                        _state.value = OtaUpdateState.Error("Download fehlgeschlagen (HTTP ${resp.code}).")
                        return@launch
                    }

                    val body = resp.body ?: run {
                        _state.value = OtaUpdateState.Error("Leere Server-Antwort.")
                        return@launch
                    }

                    val digest = MessageDigest.getInstance("SHA-256")
                    var totalRead = 0L
                    var lastReportedPct = 0

                    body.byteStream().use { input ->
                        FileOutputStream(partFile).use { output ->
                            val buffer = ByteArray(32 * 1024)
                            while (true) {
                                val read = input.read(buffer)
                                if (read == -1) break
                                totalRead += read
                                if (totalRead > MAX_APK_BYTES || totalRead > manifest.fileSize + 1024L) {
                                    throw SecurityException("APK überschreitet zulässige Dateigröße.")
                                }
                                digest.update(buffer, 0, read)
                                output.write(buffer, 0, read)

                                if (manifest.fileSize > 0L) {
                                    val pct = ((totalRead * 100L) / manifest.fileSize).toInt().coerceIn(0, 99)
                                    if (pct >= lastReportedPct + 2) {
                                        lastReportedPct = pct
                                        _state.value = OtaUpdateState.Downloading(manifest, pct)
                                    }
                                }
                            }
                            output.fd.sync()
                        }
                    }

                    // Gate 1: Exact byte size & ZIP/APK magic header check
                    if (totalRead != manifest.fileSize || partFile.length() != manifest.fileSize) {
                        securePurgeFile(partFile)
                        downloadInFlight.set(false)
                        _state.value = OtaUpdateState.Error("Dateigröße weicht vom Manifest ab.")
                        return@launch
                    }
                    if (!hasZipMagicHeader(partFile)) {
                        securePurgeFile(partFile)
                        downloadInFlight.set(false)
                        _state.value = OtaUpdateState.Error("Ungültiges APK-Format.")
                        return@launch
                    }

                    // Gate 2: Constant-time SHA-256 verification
                    val computedShaBytes = digest.digest()
                    val expectedShaBytes = hexToBytes(manifest.sha256Hex)
                    if (expectedShaBytes == null || !MessageDigest.isEqual(computedShaBytes, expectedShaBytes)) {
                        securePurgeFile(partFile)
                        downloadInFlight.set(false)
                        _state.value = OtaUpdateState.Error("SHA-256-Prüfsumme ungültig. Update abgebrochen.")
                        return@launch
                    }

                    // Gate 3 & 4: Package identity, Anti-Downgrade, and Signing Certificate Pinning
                    when (verifyPackageAndSigningCertificate(appCtx, partFile)) {
                        ApkCertCheckResult.VERIFIED -> Unit
                        ApkCertCheckResult.LEGACY_DEBUG_SWITCH_REQUIRED -> {
                            securePurgeFile(partFile)
                            downloadInFlight.set(false)
                            _state.value = OtaUpdateState.Error(
                                "Einmaliger Zertifikats-Wechsel: Bitte alte Version einmal deinstallieren und v${manifest.versionName} übers Portal installieren."
                            )
                            return@launch
                        }
                        ApkCertCheckResult.INVALID -> {
                            securePurgeFile(partFile)
                            downloadInFlight.set(false)
                            _state.value = OtaUpdateState.Error("Zertifikats-Prüfung fehlgeschlagen. Update blockiert.")
                            return@launch
                        }
                    }

                    if (!partFile.renameTo(finalApk)) {
                        partFile.copyTo(finalApk, overwrite = true)
                        securePurgeFile(partFile)
                    }
                }

                downloadInFlight.set(false)
                // Trigger Android Package Installer (or request one-time install permission) on Main Thread
                triggerInstallerOrPermission(appCtx, manifest, finalApk)
            } catch (_: Exception) {
                securePurgeFile(partFile)
                securePurgeFile(finalApk)
                downloadInFlight.set(false)
                _state.value = OtaUpdateState.Error("Update konnte nicht geladen werden.")
            } finally {
                downloadInFlight.set(false)
            }
        }
    }

    fun continueInstallOrOpenPermission(context: Context) {
        val appCtx = context.applicationContext
        val current = _state.value
        if (current !is OtaUpdateState.AwaitingInstallPermission) return
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O && !appCtx.packageManager.canRequestPackageInstalls()) {
            val apkFile = File(current.verifiedApkPath)
            triggerInstallerOrPermission(appCtx, current.manifest, apkFile)
            return
        }
        resumePendingInstallIfPermitted(appCtx)
    }

    fun resumePendingInstallIfPermitted(context: Context) {
        val appCtx = context.applicationContext
        val current = _state.value
        if (current !is OtaUpdateState.AwaitingInstallPermission) return
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O && !appCtx.packageManager.canRequestPackageInstalls()) {
            return
        }

        val apkFile = File(current.verifiedApkPath)
        if (!apkFile.exists() || !apkFile.isFile) {
            _state.value = OtaUpdateState.Available(current.manifest)
            return
        }

        scope.launch {
            // Re-verify SHA-256 and certificate before launching installer (TOCTOU protection)
            val expectedBytes = hexToBytes(current.manifest.sha256Hex)
            val actualBytes = computeFileSha256(apkFile)
            if (
                expectedBytes == null ||
                actualBytes == null ||
                !MessageDigest.isEqual(actualBytes, expectedBytes) ||
                verifyPackageAndSigningCertificate(appCtx, apkFile) != ApkCertCheckResult.VERIFIED
            ) {
                apkFile.delete()
                _state.value = OtaUpdateState.Error("Sicherheitsprüfung beim Fortsetzen fehlgeschlagen.")
                return@launch
            }
            launchSystemPackageInstaller(appCtx, current.manifest, apkFile)
        }
    }

    private fun triggerInstallerOrPermission(appCtx: Context, manifest: OtaReleaseManifest, apkFile: File) {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O && !appCtx.packageManager.canRequestPackageInstalls()) {
            _state.value = OtaUpdateState.AwaitingInstallPermission(manifest, apkFile.absolutePath)
            mainHandler.post {
                try {
                    val permIntent = Intent(
                        Settings.ACTION_MANAGE_UNKNOWN_APP_SOURCES,
                        Uri.parse("package:${appCtx.packageName}")
                    ).apply {
                        addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
                    }
                    appCtx.startActivity(permIntent)
                } catch (_: Exception) {
                    _state.value = OtaUpdateState.Error("Installations-Freigabe konnte nicht geöffnet werden.")
                }
            }
            return
        }
        launchSystemPackageInstaller(appCtx, manifest, apkFile)
    }

    private fun launchSystemPackageInstaller(appCtx: Context, manifest: OtaReleaseManifest, apkFile: File) {
        mainHandler.post {
            try {
                val uri = FileProvider.getUriForFile(
                    appCtx,
                    "${appCtx.packageName}.fileprovider",
                    apkFile
                )
                val installIntent = Intent(Intent.ACTION_VIEW).apply {
                    setDataAndType(uri, "application/vnd.android.package-archive")
                    addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
                    addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
                }
                _promptDismissed.value = true
                _state.value = OtaUpdateState.InstallerLaunched(manifest)
                appCtx.startActivity(installIntent)
            } catch (_: Exception) {
                _promptDismissed.value = false
                _state.value = OtaUpdateState.Error("Android-Installer konnte nicht gestartet werden.")
            }
        }
    }

    private fun resolveAuthToken(appCtx: Context): String? {
        authTokenOverrideForTests?.let { return it.trim().takeIf { s -> s.isNotEmpty() } }
        ServiceLocator.ensureInitialized(appCtx)
        return ServiceLocator.getAuthToken()?.trim()?.takeIf { it.isNotEmpty() }
    }

    private fun resolveTrustedBaseUrl(): String? {
        baseUrlOverrideForTests?.let { return it }
        val baseUrl = ServiceLocator.getBaseUrl()
        return if (ServiceLocator.isAllowedBaseUrl(baseUrl)) baseUrl else null
    }

    @Suppress("DEPRECATION")
    private fun verifyPackageAndSigningCertificate(context: Context, apkFile: File): ApkCertCheckResult {
        return try {
            val pm = context.packageManager
            val (_, installedCode) = getInstalledVersionInfo(context)

            var archiveInfo: PackageInfo? = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.P) {
                pm.getPackageArchiveInfo(apkFile.absolutePath, PackageManager.GET_SIGNING_CERTIFICATES)
            } else {
                pm.getPackageArchiveInfo(apkFile.absolutePath, PackageManager.GET_SIGNATURES)
            }

            if (archiveInfo == null || archiveInfo.packageName != context.packageName) {
                return ApkCertCheckResult.INVALID
            }

            val archiveCode = PackageInfoCompat.getLongVersionCode(archiveInfo)
            if (archiveCode <= installedCode) {
                return ApkCertCheckResult.INVALID
            }

            var archiveCertDigests = extractSignerSha256Digests(archiveInfo)
            if (archiveCertDigests.isEmpty() && Build.VERSION.SDK_INT >= Build.VERSION_CODES.P) {
                archiveInfo = pm.getPackageArchiveInfo(apkFile.absolutePath, PackageManager.GET_SIGNATURES)
                if (archiveInfo != null) {
                    archiveCertDigests = extractLegacySignatureDigests(archiveInfo)
                }
            }
            if (archiveCertDigests.isEmpty()) {
                return ApkCertCheckResult.INVALID
            }

            // Gate 4a: Downloaded APK MUST ALWAYS match the pinned 4096-bit RSA release certificate
            val pinnedBytes = hexToBytes(PINNED_RELEASE_CERT_SHA256) ?: return ApkCertCheckResult.INVALID
            val matchesPinned = archiveCertDigests.any { MessageDigest.isEqual(it, pinnedBytes) }
            if (!matchesPinned) {
                return ApkCertCheckResult.INVALID
            }

            // Gate 4b: Installed app MUST also share the same certificate for Android in-place upgrade
            val installedInfo: PackageInfo = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.P) {
                pm.getPackageInfo(context.packageName, PackageManager.GET_SIGNING_CERTIFICATES)
            } else {
                pm.getPackageInfo(context.packageName, PackageManager.GET_SIGNATURES)
            }
            var installedCertDigests = extractSignerSha256Digests(installedInfo)
            if (installedCertDigests.isEmpty() && Build.VERSION.SDK_INT >= Build.VERSION_CODES.P) {
                val legacyInstalled = pm.getPackageInfo(context.packageName, PackageManager.GET_SIGNATURES)
                installedCertDigests = extractLegacySignatureDigests(legacyInstalled)
            }
            if (installedCertDigests.isEmpty()) {
                return ApkCertCheckResult.INVALID
            }

            val matchesInstalled = archiveCertDigests.any { dlCert ->
                installedCertDigests.any { instCert -> MessageDigest.isEqual(dlCert, instCert) }
            }
            if (!matchesInstalled) {
                return ApkCertCheckResult.LEGACY_DEBUG_SWITCH_REQUIRED
            }

            ApkCertCheckResult.VERIFIED
        } catch (_: Exception) {
            ApkCertCheckResult.INVALID
        }
    }

    @Suppress("DEPRECATION")
    private fun extractSignerSha256Digests(pkgInfo: PackageInfo): List<ByteArray> {
        val rawSigs = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.P) {
            val signingInfo = pkgInfo.signingInfo ?: return emptyList()
            if (signingInfo.hasMultipleSigners()) {
                signingInfo.apkContentsSigners
            } else {
                signingInfo.signingCertificateHistory
            }
        } else {
            pkgInfo.signatures
        } ?: return emptyList()

        return rawSigs.mapNotNull { sig ->
            try {
                MessageDigest.getInstance("SHA-256").digest(sig.toByteArray())
            } catch (_: Exception) {
                null
            }
        }
    }

    @Suppress("DEPRECATION")
    private fun extractLegacySignatureDigests(pkgInfo: PackageInfo): List<ByteArray> {
        val rawSigs = pkgInfo.signatures ?: return emptyList()
        return rawSigs.mapNotNull { sig ->
            try {
                MessageDigest.getInstance("SHA-256").digest(sig.toByteArray())
            } catch (_: Exception) {
                null
            }
        }
    }

    internal fun securePurgeFile(file: File) {
        try {
            if (!file.exists()) return
            if (!file.delete()) {
                try {
                    FileOutputStream(file).use { }
                } catch (_: Exception) {
                }
                if (!file.delete()) {
                    file.deleteOnExit()
                }
            }
        } catch (_: Exception) {
        }
    }

    internal fun hasZipMagicHeader(file: File): Boolean {
        return try {
            FileInputStream(file).use { fis ->
                val hdr = ByteArray(4)
                val r = fis.read(hdr)
                r == 4 && hdr[0] == 0x50.toByte() && hdr[1] == 0x4B.toByte() &&
                    hdr[2] == 0x03.toByte() && hdr[3] == 0x04.toByte()
            }
        } catch (_: Exception) {
            false
        }
    }

    internal fun computeFileSha256(file: File): ByteArray? {
        return try {
            val md = MessageDigest.getInstance("SHA-256")
            FileInputStream(file).use { fis ->
                val buf = ByteArray(32 * 1024)
                while (true) {
                    val r = fis.read(buf)
                    if (r == -1) break
                    md.update(buf, 0, r)
                }
            }
            md.digest()
        } catch (_: Exception) {
            null
        }
    }

    internal fun hexToBytes(hex: String): ByteArray? {
        val clean = hex.trim()
        if (clean.length % 2 != 0) return null
        val out = ByteArray(clean.length / 2)
        for (i in out.indices) {
            val hi = Character.digit(clean[i * 2], 16)
            val lo = Character.digit(clean[i * 2 + 1], 16)
            if (hi == -1 || lo == -1) return null
            out[i] = ((hi shl 4) or lo).toByte()
        }
        return out
    }
}
