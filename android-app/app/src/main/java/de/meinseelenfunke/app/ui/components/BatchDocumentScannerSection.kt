package de.meinseelenfunke.app.ui.components

import android.app.Activity
import android.content.Context
import android.content.ContextWrapper
import android.net.Uri
import android.provider.OpenableColumns
import android.webkit.MimeTypeMap
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.IntentSenderRequest
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.AttachFile
import androidx.compose.material.icons.filled.Close
import androidx.compose.material.icons.filled.CloudUpload
import androidx.compose.material.icons.filled.Description
import androidx.compose.material.icons.filled.DocumentScanner
import androidx.compose.material.icons.filled.FilterNone
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.OutlinedTextFieldDefaults
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.core.content.FileProvider
import com.google.mlkit.vision.documentscanner.GmsDocumentScannerOptions
import com.google.mlkit.vision.documentscanner.GmsDocumentScanning
import com.google.mlkit.vision.documentscanner.GmsDocumentScanningResult
import de.meinseelenfunke.app.di.ServiceLocator
import de.meinseelenfunke.app.ui.screens.TrichterFeedback
import de.meinseelenfunke.app.ui.screens.TrichterUploadItem
import de.meinseelenfunke.app.ui.theme.AppTheme
import de.meinseelenfunke.app.ui.theme.Emerald500
import de.meinseelenfunke.app.ui.theme.Gold
import de.meinseelenfunke.app.ui.theme.Rose500
import de.meinseelenfunke.app.ui.theme.SpaceBlack
import de.meinseelenfunke.app.util.ImageUtils
import de.meinseelenfunke.app.util.KdriveTrichterOfflineQueue
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import java.io.ByteArrayOutputStream
import java.io.File
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale

private tailrec fun Context.findHostActivity(): Activity? = when (this) {
    is Activity -> this
    is ContextWrapper -> baseContext.findHostActivity()
    else -> null
}

internal fun compileScannedPagesForTrichter(
    pages: List<ByteArray>,
    splitIntoSinglePdfs: Boolean,
    cachedMergedPdf: ByteArray?,
    stamp: String,
    pdfBuilder: (List<ByteArray>) -> ByteArray? = { ImageUtils.buildPdfFromJpegPages(it) }
): List<TrichterUploadItem> {
    if (pages.isEmpty()) return emptyList()
    return if (splitIntoSinglePdfs && pages.size > 1) {
        val pad = if (pages.size >= 100) 3 else 2
        pages.mapIndexedNotNull { idx, singlePageJpeg ->
            val singlePdf = pdfBuilder(listOf(singlePageJpeg))
            if (singlePdf != null && singlePdf.isNotEmpty()) {
                val num = (idx + 1).toString().padStart(pad, '0')
                TrichterUploadItem(
                    bytes = singlePdf,
                    fileName = "Scan_${stamp}_$num.pdf",
                    mimeType = "application/pdf"
                )
            } else null
        }
    } else {
        val mergedPdf = cachedMergedPdf ?: pdfBuilder(pages)
        if (mergedPdf != null && mergedPdf.isNotEmpty()) {
            listOf(
                TrichterUploadItem(
                    bytes = mergedPdf,
                    fileName = "Scan_$stamp.pdf",
                    mimeType = "application/pdf"
                )
            )
        } else emptyList()
    }
}

@Composable
fun BatchDocumentScannerSection(
    isUploading: Boolean,
    initialSharedUris: List<Uri> = emptyList(),
    autoLaunchScannerNonce: Int = 0,
    trichterFeedback: TrichterFeedback? = null,
    onClearTrichterFeedback: () -> Unit = {},
    onDismiss: () -> Unit,
    onSendMultipleToTrichter: (items: List<TrichterUploadItem>, customTitle: String?) -> Unit
) {
    val context = LocalContext.current
    val contentResolver = context.contentResolver
    val scope = rememberCoroutineScope()

    var splitIntoSinglePdfs by rememberSaveable { mutableStateOf(true) }
    var trichterTitle by rememberSaveable { mutableStateOf("") }
    var fallbackCameraUriString by rememberSaveable { mutableStateOf<String?>(null) }
    var scannedPages by remember { mutableStateOf<List<ByteArray>>(emptyList()) }
    var nativeCombinedPdfBytes by remember { mutableStateOf<ByteArray?>(null) }
    var queuedFiles by remember { mutableStateOf<List<TrichterUploadItem>>(emptyList()) }
    var statusNote by rememberSaveable { mutableStateOf<String?>(null) }
    var queueSyncSuccessNote by remember { mutableStateOf<String?>(null) }
    var syncingOfflineQueue by remember { mutableStateOf(false) }
    var localCompilingPdf by remember { mutableStateOf(false) }
    var localLoadingFiles by remember { mutableStateOf(false) }
    var sharedUrisConsumed by rememberSaveable { mutableStateOf(false) }
    var handledAutoLaunchNonce by rememberSaveable { mutableIntStateOf(0) }

    val offlineQueueCount by KdriveTrichterOfflineQueue.pendingCount.collectAsState()

    LaunchedEffect(Unit) {
        KdriveTrichterOfflineQueue.refreshCount(context)
    }

    LaunchedEffect(trichterFeedback?.id) {
        if (trichterFeedback?.isSuccess == true) {
            scannedPages = emptyList()
            nativeCombinedPdfBytes = null
            queuedFiles = emptyList()
            trichterTitle = ""
            statusNote = null
        }
    }

    val readUriRawBytesLimited: (Uri) -> Triple<ByteArray, String, String>? = { targetUri ->
        val mime = contentResolver.getType(targetUri) ?: "application/octet-stream"
        var resolvedName: String? = null
        contentResolver.query(targetUri, null, null, null, null)?.use { c ->
            val nameIdx = c.getColumnIndex(OpenableColumns.DISPLAY_NAME)
            if (nameIdx != -1 && c.moveToFirst()) {
                resolvedName = c.getString(nameIdx)
            }
        }
        if (resolvedName.isNullOrBlank()) {
            val ext = MimeTypeMap.getSingleton().getExtensionFromMimeType(mime) ?: "pdf"
            resolvedName = "Dokument_${System.currentTimeMillis()}.$ext"
        }
        val maxUploadBytes = 25 * 1024 * 1024
        val bytes = contentResolver.openInputStream(targetUri)?.use { input ->
            val out = ByteArrayOutputStream()
            val buf = ByteArray(8192)
            var total = 0
            while (true) {
                val read = input.read(buf)
                if (read == -1) break
                total += read
                if (total > maxUploadBytes) {
                    throw IllegalArgumentException("Datei ist größer als 25 MB.")
                }
                out.write(buf, 0, read)
            }
            out.toByteArray()
        }
        if (bytes != null && bytes.isNotEmpty()) Triple(bytes, resolvedName!!, mime) else null
    }

    val loadUriAsTrichterItem: (Uri, Int) -> TrichterUploadItem? = { targetUri, idx ->
        val mime = contentResolver.getType(targetUri) ?: "application/octet-stream"
        var resolvedName: String? = null
        contentResolver.query(targetUri, null, null, null, null)?.use { c ->
            val nameIdx = c.getColumnIndex(OpenableColumns.DISPLAY_NAME)
            if (nameIdx != -1 && c.moveToFirst()) {
                resolvedName = c.getString(nameIdx)
            }
        }
        if (mime.startsWith("image/")) {
            val compressed = ImageUtils.compressImageUri(
                context, targetUri, maxDimension = 1900, quality = 85
            )
            if (compressed != null && compressed.isNotEmpty()) {
                val baseName = resolvedName?.takeIf { it.isNotBlank() }
                    ?: "Bild_${System.currentTimeMillis()}_${idx + 1}.jpg"
                val jpgName = if (baseName.endsWith(".jpg", true) || baseName.endsWith(".jpeg", true)) {
                    baseName
                } else {
                    val dotIdx = baseName.lastIndexOf('.')
                    if (dotIdx != -1) baseName.substring(0, dotIdx) + ".jpg" else "$baseName.jpg"
                }
                TrichterUploadItem(
                    bytes = compressed,
                    fileName = jpgName,
                    mimeType = "image/jpeg"
                )
            } else {
                val fallback = readUriRawBytesLimited(targetUri)
                fallback?.let { TrichterUploadItem(bytes = it.first, fileName = it.second, mimeType = it.third) }
            }
        } else {
            val loaded = readUriRawBytesLimited(targetUri)
            loaded?.let { TrichterUploadItem(bytes = it.first, fileName = it.second, mimeType = it.third) }
        }
    }

    LaunchedEffect(initialSharedUris) {
        if (initialSharedUris.isNotEmpty() && !sharedUrisConsumed) {
            sharedUrisConsumed = true
            statusNote = null
            localLoadingFiles = true
            val loadedBatch = withContext(Dispatchers.IO) {
                val acc = mutableListOf<TrichterUploadItem>()
                initialSharedUris.take(30).forEachIndexed { idx, u ->
                    try {
                        loadUriAsTrichterItem(u, idx)?.let { acc.add(it) }
                    } catch (_: Exception) {}
                }
                acc
            }
            localLoadingFiles = false
            if (loadedBatch.isNotEmpty()) {
                queuedFiles = queuedFiles + loadedBatch
            } else {
                statusNote = "Datei konnte nicht gelesen werden."
            }
        }
    }

    val fallbackCameraLauncher = rememberLauncherForActivityResult(
        contract = ActivityResultContracts.TakePicture()
    ) { success ->
        val uri = fallbackCameraUriString?.let { Uri.parse(it) }
        if (success && uri != null) {
            statusNote = null
            onClearTrichterFeedback()
            scope.launch {
                try {
                    val pageBytes = withContext(Dispatchers.IO) {
                        val bytes = ImageUtils.compressImageUri(
                            context, uri, maxDimension = 1900, quality = 85
                        ) ?: context.contentResolver.openInputStream(uri)?.use { it.readBytes() }
                        try { context.contentResolver.delete(uri, null, null) } catch (_: Exception) {}
                        bytes
                    }
                    fallbackCameraUriString = null
                    if (pageBytes != null && pageBytes.isNotEmpty()) {
                        nativeCombinedPdfBytes = null
                        scannedPages = scannedPages + pageBytes
                    } else {
                        statusNote = "Aufnahme fehlgeschlagen."
                    }
                } catch (_: Exception) {
                    try { context.contentResolver.delete(uri, null, null) } catch (_: Exception) {}
                    fallbackCameraUriString = null
                    statusNote = "Fehler beim Verarbeiten der Seite."
                }
            }
        } else {
            if (uri != null) {
                try { context.contentResolver.delete(uri, null, null) } catch (_: Exception) {}
            }
            fallbackCameraUriString = null
        }
    }

    val launchFallbackCamera: () -> Unit = {
        try {
            val cameraDir = File(context.cacheDir, "camera_captures").apply { mkdirs() }
            val tempFile = File.createTempFile("trichter_page_", ".jpg", cameraDir).apply {
                deleteOnExit()
            }
            val uri = FileProvider.getUriForFile(
                context,
                "de.meinseelenfunke.app.fileprovider",
                tempFile
            )
            fallbackCameraUriString = uri.toString()
            fallbackCameraLauncher.launch(uri)
        } catch (_: Exception) {
            statusNote = "Kamera konnte nicht gestartet werden."
        }
    }

    val mlKitScannerLauncher = rememberLauncherForActivityResult(
        contract = ActivityResultContracts.StartIntentSenderForResult()
    ) { activityResult ->
        if (activityResult.resultCode == Activity.RESULT_OK) {
            val scanResult = GmsDocumentScanningResult.fromActivityResultIntent(activityResult.data)
            if (scanResult != null) {
                statusNote = null
                onClearTrichterFeedback()
                localLoadingFiles = true
                val hadPreviousPages = scannedPages.isNotEmpty()
                scope.launch {
                    try {
                        val extracted = withContext(Dispatchers.IO) {
                            val pageList = mutableListOf<ByteArray>()
                            scanResult.pages?.forEach { page ->
                                val imgBytes = ImageUtils.compressImageUri(
                                    context,
                                    page.imageUri,
                                    maxDimension = 1850,
                                    quality = 82
                                ) ?: contentResolver.openInputStream(page.imageUri)?.use { it.readBytes() }
                                if (imgBytes != null && imgBytes.isNotEmpty()) {
                                    pageList.add(imgBytes)
                                }
                            }
                            val pdfBytes = if (pageList.isEmpty() && !hadPreviousPages) {
                                scanResult.pdf?.uri?.let { pdfUri ->
                                    try {
                                        readUriRawBytesLimited(pdfUri)?.first
                                    } catch (_: Exception) {
                                        null
                                    }
                                }
                            } else null
                            pageList to pdfBytes
                        }
                        localLoadingFiles = false
                        val (newPages, nativePdf) = extracted
                        if (newPages.isNotEmpty()) {
                            nativeCombinedPdfBytes = null
                            scannedPages = scannedPages + newPages
                        } else if (nativePdf != null && nativePdf.isNotEmpty()) {
                            val stamp = SimpleDateFormat("yyyy-MM-dd_HHmmss", Locale.US).format(Date())
                            queuedFiles = queuedFiles + TrichterUploadItem(
                                bytes = nativePdf,
                                fileName = "Scan_$stamp.pdf",
                                mimeType = "application/pdf"
                            )
                        } else {
                            statusNote = "Keine Seiten vom Scanner empfangen."
                        }
                    } catch (e: Exception) {
                        localLoadingFiles = false
                        statusNote = e.localizedMessage ?: "Fehler beim Lesen des Scans."
                    }
                }
            }
        }
    }

    val startBatchScanner: () -> Unit = {
        statusNote = null
        onClearTrichterFeedback()
        val hostActivity = context.findHostActivity()
        if (hostActivity == null) {
            launchFallbackCamera()
        } else {
            try {
                val options = GmsDocumentScannerOptions.Builder()
                    .setGalleryImportAllowed(true)
                    .setPageLimit(50)
                    .setResultFormats(
                        GmsDocumentScannerOptions.RESULT_FORMAT_JPEG,
                        GmsDocumentScannerOptions.RESULT_FORMAT_PDF
                    )
                    .setScannerMode(GmsDocumentScannerOptions.SCANNER_MODE_FULL)
                    .build()
                GmsDocumentScanning.getClient(options)
                    .getStartScanIntent(hostActivity)
                    .addOnSuccessListener { intentSender ->
                        mlKitScannerLauncher.launch(IntentSenderRequest.Builder(intentSender).build())
                    }
                    .addOnFailureListener {
                        launchFallbackCamera()
                    }
            } catch (_: Throwable) {
                launchFallbackCamera()
            }
        }
    }

    LaunchedEffect(autoLaunchScannerNonce) {
        if (autoLaunchScannerNonce > 0 && autoLaunchScannerNonce != handledAutoLaunchNonce) {
            handledAutoLaunchNonce = autoLaunchScannerNonce
            startBatchScanner()
        }
    }

    val filePickerLauncher = rememberLauncherForActivityResult(
        contract = ActivityResultContracts.GetMultipleContents()
    ) { uris ->
        if (uris.isNotEmpty()) {
            statusNote = null
            onClearTrichterFeedback()
            localLoadingFiles = true
            scope.launch {
                try {
                    val loadedItems = withContext(Dispatchers.IO) {
                        val list = mutableListOf<TrichterUploadItem>()
                        uris.take(40).forEachIndexed { idx, targetUri ->
                            loadUriAsTrichterItem(targetUri, idx)?.let { list.add(it) }
                        }
                        list
                    }
                    localLoadingFiles = false
                    if (loadedItems.isNotEmpty()) {
                        queuedFiles = queuedFiles + loadedItems
                    } else {
                        statusNote = "Keine gültigen Dateien ausgewählt."
                    }
                } catch (e: Exception) {
                    localLoadingFiles = false
                    statusNote = e.localizedMessage ?: "Dateien konnten nicht gelesen werden."
                }
            }
        }
    }

    Column(
        modifier = Modifier.fillMaxWidth(),
        verticalArrangement = Arrangement.spacedBy(10.dp)
    ) {
        // 1-Tap Stapel-Modus Umschalter: [ Einzel-Belege ] | [ Mehrseitig (1 PDF) ]
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .clip(RoundedCornerShape(9.dp))
                .background(AppTheme.colors.glassSurface)
                .border(1.dp, AppTheme.colors.glassBorder, RoundedCornerShape(9.dp))
                .padding(3.dp),
            horizontalArrangement = Arrangement.spacedBy(4.dp)
        ) {
            Row(
                modifier = Modifier
                    .weight(1f)
                    .clip(RoundedCornerShape(7.dp))
                    .background(if (splitIntoSinglePdfs) Color(0x28C5A059) else Color.Transparent)
                    .border(
                        width = 1.dp,
                        color = if (splitIntoSinglePdfs) Gold else Color.Transparent,
                        shape = RoundedCornerShape(7.dp)
                    )
                    .clickable { splitIntoSinglePdfs = true }
                    .padding(vertical = 6.dp, horizontal = 6.dp),
                horizontalArrangement = Arrangement.Center,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Icon(
                    imageVector = Icons.Default.Description,
                    contentDescription = null,
                    tint = if (splitIntoSinglePdfs) Gold else AppTheme.colors.textMuted,
                    modifier = Modifier.size(14.dp)
                )
                Spacer(modifier = Modifier.width(5.dp))
                Text(
                    text = "Einzel-Belege",
                    color = if (splitIntoSinglePdfs) Gold else AppTheme.colors.textPrimary,
                    fontSize = 11.5.sp,
                    fontWeight = if (splitIntoSinglePdfs) FontWeight.Bold else FontWeight.Medium,
                    maxLines = 1
                )
            }

            Row(
                modifier = Modifier
                    .weight(1f)
                    .clip(RoundedCornerShape(7.dp))
                    .background(if (!splitIntoSinglePdfs) Color(0x28C5A059) else Color.Transparent)
                    .border(
                        width = 1.dp,
                        color = if (!splitIntoSinglePdfs) Gold else Color.Transparent,
                        shape = RoundedCornerShape(7.dp)
                    )
                    .clickable { splitIntoSinglePdfs = false }
                    .padding(vertical = 6.dp, horizontal = 6.dp),
                horizontalArrangement = Arrangement.Center,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Icon(
                    imageVector = Icons.Default.FilterNone,
                    contentDescription = null,
                    tint = if (!splitIntoSinglePdfs) Gold else AppTheme.colors.textMuted,
                    modifier = Modifier.size(14.dp)
                )
                Spacer(modifier = Modifier.width(5.dp))
                Text(
                    text = "Mehrseitig (1 PDF)",
                    color = if (!splitIntoSinglePdfs) Gold else AppTheme.colors.textPrimary,
                    fontSize = 11.5.sp,
                    fontWeight = if (!splitIntoSinglePdfs) FontWeight.Bold else FontWeight.Medium,
                    maxLines = 1
                )
            }
        }

        // Scanner + Datei-Auswahl
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.spacedBy(8.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            Row(
                modifier = Modifier
                    .weight(1f)
                    .clip(RoundedCornerShape(8.dp))
                    .background(Color(0x22C5A059))
                    .border(1.dp, Gold, RoundedCornerShape(8.dp))
                    .clickable { startBatchScanner() }
                    .padding(horizontal = 12.dp, vertical = 10.dp),
                horizontalArrangement = Arrangement.Center,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Icon(
                    imageVector = Icons.Default.DocumentScanner,
                    contentDescription = "Stapel scannen",
                    tint = Gold,
                    modifier = Modifier.size(17.dp)
                )
                Spacer(modifier = Modifier.width(6.dp))
                Text(
                    text = if (scannedPages.isEmpty()) "Stapel scannen" else "+ Weiter (${scannedPages.size})",
                    color = Gold,
                    fontSize = 12.5.sp,
                    fontWeight = FontWeight.Bold
                )
            }

            Row(
                modifier = Modifier
                    .clip(RoundedCornerShape(8.dp))
                    .background(AppTheme.colors.glassSurface)
                    .border(1.dp, AppTheme.colors.glassBorder, RoundedCornerShape(8.dp))
                    .clickable { filePickerLauncher.launch("*/*") }
                    .padding(horizontal = 12.dp, vertical = 10.dp),
                verticalAlignment = Alignment.CenterVertically
            ) {
                Icon(
                    imageVector = Icons.Default.AttachFile,
                    contentDescription = "Dateien wählen",
                    tint = if (queuedFiles.isNotEmpty()) Gold else AppTheme.colors.textMuted,
                    modifier = Modifier.size(16.dp)
                )
                Spacer(modifier = Modifier.width(4.dp))
                Text(
                    text = if (queuedFiles.isEmpty()) "Dateien" else "+ Dateien (${queuedFiles.size})",
                    color = if (queuedFiles.isNotEmpty()) Gold else AppTheme.colors.textPrimary,
                    fontSize = 12.sp,
                    fontWeight = if (queuedFiles.isNotEmpty()) FontWeight.Bold else FontWeight.Medium
                )
            }
        }

        if (scannedPages.isNotEmpty()) {
            val modeSummary = if (splitIntoSinglePdfs) {
                if (scannedPages.size == 1) "1 Seite -> 1 PDF" else "${scannedPages.size} Seiten -> ${scannedPages.size} Einzel-PDFs"
            } else {
                if (scannedPages.size == 1) "1 Seite -> 1 PDF" else "${scannedPages.size} Seiten -> 1 Sammel-PDF"
            }
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .background(Color(0x14C5A059), RoundedCornerShape(8.dp))
                    .border(1.dp, Gold.copy(alpha = 0.4f), RoundedCornerShape(8.dp))
                    .padding(horizontal = 10.dp, vertical = 8.dp),
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.SpaceBetween
            ) {
                Text(
                    text = modeSummary,
                    color = Gold,
                    fontSize = 12.sp,
                    fontWeight = FontWeight.Bold,
                    modifier = Modifier.weight(1f)
                )
                Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                    if (scannedPages.size > 1) {
                        Text(
                            text = "-1 Seite",
                            color = AppTheme.colors.textMuted,
                            fontSize = 11.sp,
                            fontWeight = FontWeight.SemiBold,
                            modifier = Modifier.clickable {
                                nativeCombinedPdfBytes = null
                                scannedPages = scannedPages.dropLast(1)
                            }
                        )
                    }
                    Text(
                        text = "Leeren",
                        color = Rose500,
                        fontSize = 11.sp,
                        fontWeight = FontWeight.Bold,
                        modifier = Modifier.clickable {
                            nativeCombinedPdfBytes = null
                            scannedPages = emptyList()
                        }
                    )
                }
            }
        }

        if (queuedFiles.isNotEmpty()) {
            val queuedImageItems = queuedFiles.filter { it.mimeType.startsWith("image/") }
            Column(
                modifier = Modifier
                    .fillMaxWidth()
                    .background(Color(0x14C5A059), RoundedCornerShape(8.dp))
                    .border(1.dp, Gold.copy(alpha = 0.4f), RoundedCornerShape(8.dp))
                    .padding(horizontal = 10.dp, vertical = 7.dp),
                verticalArrangement = Arrangement.spacedBy(5.dp)
            ) {
                if (queuedFiles.size > 1) {
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Text(
                            text = "${queuedFiles.size} Dateien ausgewählt",
                            color = Gold,
                            fontSize = 12.sp,
                            fontWeight = FontWeight.Bold
                        )
                        Row(
                            horizontalArrangement = Arrangement.spacedBy(10.dp),
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            if (queuedImageItems.size >= 2) {
                                Text(
                                    text = "Als 1 PDF",
                                    color = Gold,
                                    fontSize = 11.sp,
                                    fontWeight = FontWeight.Bold,
                                    modifier = Modifier.clickable {
                                        localCompilingPdf = true
                                        val imgBytesList = queuedImageItems.map { it.bytes }
                                        val remainingNonImages = queuedFiles.filterNot { it.mimeType.startsWith("image/") }
                                        scope.launch {
                                            val mergedPdf = withContext(Dispatchers.Default) {
                                                ImageUtils.buildPdfFromJpegPages(imgBytesList)
                                            }
                                            localCompilingPdf = false
                                            if (mergedPdf != null && mergedPdf.isNotEmpty()) {
                                                val stamp = SimpleDateFormat("yyyy-MM-dd_HHmmss", Locale.US).format(Date())
                                                queuedFiles = listOf(
                                                    TrichterUploadItem(
                                                        bytes = mergedPdf,
                                                        fileName = "Scan_$stamp.pdf",
                                                        mimeType = "application/pdf"
                                                    )
                                                ) + remainingNonImages
                                            } else {
                                                statusNote = "PDF-Bündelung fehlgeschlagen."
                                            }
                                        }
                                    }
                                )
                            }
                            Text(
                                text = "Alle leeren",
                                color = Rose500,
                                fontSize = 11.sp,
                                fontWeight = FontWeight.Bold,
                                modifier = Modifier.clickable {
                                    queuedFiles = emptyList()
                                }
                            )
                        }
                    }
                }

                queuedFiles.forEach { fileItem ->
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.SpaceBetween
                    ) {
                        Text(
                            text = fileItem.fileName,
                            color = if (queuedFiles.size == 1) Gold else AppTheme.colors.textPrimary,
                            fontSize = 12.sp,
                            maxLines = 1,
                            overflow = TextOverflow.Ellipsis,
                            modifier = Modifier.weight(1f)
                        )
                        Icon(
                            imageVector = Icons.Default.Close,
                            contentDescription = "Entfernen",
                            tint = Rose500,
                            modifier = Modifier
                                .size(16.dp)
                                .clickable {
                                    queuedFiles = queuedFiles.filterNot { it.id == fileItem.id }
                                }
                        )
                    }
                }
            }
        }

        if (offlineQueueCount > 0) {
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .background(AppTheme.colors.glassSurface, RoundedCornerShape(8.dp))
                    .border(1.dp, Gold.copy(alpha = 0.45f), RoundedCornerShape(8.dp))
                    .padding(horizontal = 10.dp, vertical = 6.dp),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Text(
                    text = "Offline-Warteschlange: $offlineQueueCount",
                    color = Gold,
                    fontSize = 11.5.sp,
                    fontWeight = FontWeight.SemiBold
                )
                Text(
                    text = if (syncingOfflineQueue) "Synchronisiere..." else "Jetzt synchronisieren",
                    color = if (syncingOfflineQueue) Gold else AppTheme.colors.textPrimary,
                    fontSize = 11.sp,
                    fontWeight = FontWeight.Bold,
                    modifier = Modifier.clickable(enabled = !syncingOfflineQueue) {
                        scope.launch {
                            syncingOfflineQueue = true
                            statusNote = null
                            queueSyncSuccessNote = null
                            val res = ServiceLocator.financeRepository.flushOfflineTrichterQueueDetailed()
                            syncingOfflineQueue = false
                            when {
                                res.uploaded > 0 && res.remaining == 0 -> {
                                    statusNote = null
                                    queueSyncSuccessNote = if (res.uploaded == 1) {
                                        "1 Dokument erfolgreich an kDrive übertragen."
                                    } else {
                                        "${res.uploaded} Dokumente erfolgreich an kDrive übertragen."
                                    }
                                }
                                res.uploaded > 0 && res.remaining > 0 -> {
                                    statusNote = "${res.uploaded} übertragen, ${res.remaining} wartend: ${res.lastError ?: "Verbindung prüfen."}"
                                }
                                res.remaining > 0 -> {
                                    statusNote = res.lastError ?: "kDrive-Übertragung aktuell nicht möglich (${res.remaining} wartend)."
                                }
                            }
                        }
                    }
                )
            }
        }

        OutlinedTextField(
            value = trichterTitle,
            onValueChange = { trichterTitle = it },
            label = { Text("Titel / Stichwort (optional)") },
            singleLine = true,
            modifier = Modifier.fillMaxWidth(),
            colors = OutlinedTextFieldDefaults.colors(
                focusedBorderColor = Gold,
                unfocusedBorderColor = AppTheme.colors.inputBorder,
                focusedTextColor = AppTheme.colors.textPrimary,
                unfocusedTextColor = AppTheme.colors.textPrimary
            )
        )

        val bannerIsError = statusNote != null || (trichterFeedback != null && !trichterFeedback.isSuccess)
        val bannerText = when {
            statusNote != null -> statusNote
            queueSyncSuccessNote != null -> queueSyncSuccessNote
            trichterFeedback != null -> trichterFeedback.message
            else -> null
        }

        if (bannerText != null) {
            Card(
                colors = CardDefaults.cardColors(
                    containerColor = if (bannerIsError) Color(0x24EF4444) else Color(0x2410B981)
                ),
                border = BorderStroke(
                    1.dp,
                    if (bannerIsError) Color(0xFFEF4444) else Emerald500
                ),
                modifier = Modifier.fillMaxWidth()
            ) {
                Text(
                    text = bannerText,
                    color = AppTheme.colors.textPrimary,
                    fontSize = 12.sp,
                    fontWeight = FontWeight.SemiBold,
                    modifier = Modifier.padding(10.dp)
                )
            }
        }

        val scannedDocCount = when {
            scannedPages.isEmpty() -> 0
            splitIntoSinglePdfs -> scannedPages.size
            else -> 1
        }
        val totalTrichterDocuments = scannedDocCount + queuedFiles.size
        val hasTrichterPayload = totalTrichterDocuments > 0
        val busyTrichter = isUploading || localCompilingPdf || localLoadingFiles

        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(top = 4.dp),
            horizontalArrangement = Arrangement.End,
            verticalAlignment = Alignment.CenterVertically
        ) {
            TextButton(
                onClick = { onDismiss() },
                enabled = !busyTrichter
            ) {
                Text("Schließen", color = AppTheme.colors.textMuted, fontSize = 13.sp)
            }
            Spacer(modifier = Modifier.width(6.dp))
            Button(
                onClick = {
                    statusNote = null
                    queueSyncSuccessNote = null
                    onClearTrichterFeedback()
                    val cleanHint = trichterTitle.trim().ifBlank { null }
                    val queuedSnapshot = queuedFiles
                    if (scannedPages.isNotEmpty()) {
                        localCompilingPdf = true
                        val pagesSnapshot = scannedPages
                        val cachedMerged = nativeCombinedPdfBytes
                        val splitSnapshot = splitIntoSinglePdfs
                        scope.launch {
                            val stamp = SimpleDateFormat("yyyy-MM-dd_HHmmss", Locale.US).format(Date())
                            val builtItems = withContext(Dispatchers.Default) {
                                compileScannedPagesForTrichter(
                                    pages = pagesSnapshot,
                                    splitIntoSinglePdfs = splitSnapshot,
                                    cachedMergedPdf = cachedMerged,
                                    stamp = stamp
                                )
                            }
                            localCompilingPdf = false
                            if (builtItems.isNotEmpty()) {
                                onSendMultipleToTrichter(builtItems + queuedSnapshot, cleanHint)
                            } else {
                                statusNote = "PDF konnte nicht erzeugt werden."
                            }
                        }
                    } else if (queuedSnapshot.isNotEmpty()) {
                        onSendMultipleToTrichter(queuedSnapshot, cleanHint)
                    } else {
                        statusNote = "Bitte zuerst scannen oder Dateien auswählen."
                    }
                },
                enabled = !busyTrichter && hasTrichterPayload,
                colors = ButtonDefaults.buttonColors(containerColor = Gold, contentColor = SpaceBlack),
                shape = RoundedCornerShape(10.dp)
            ) {
                if (busyTrichter) {
                    CircularProgressIndicator(color = SpaceBlack, modifier = Modifier.size(15.dp), strokeWidth = 2.dp)
                    Spacer(modifier = Modifier.width(6.dp))
                    Text("Wird übertragen...", fontWeight = FontWeight.Bold, fontSize = 12.sp)
                } else {
                    Icon(
                        imageVector = Icons.Default.CloudUpload,
                        contentDescription = null,
                        tint = SpaceBlack,
                        modifier = Modifier.size(16.dp)
                    )
                    Spacer(modifier = Modifier.width(6.dp))
                    val btnLabel = when {
                        totalTrichterDocuments > 1 -> "$totalTrichterDocuments PDFs senden"
                        !splitIntoSinglePdfs && scannedPages.size > 1 -> "1 PDF (${scannedPages.size} S.) senden"
                        else -> "In kDrive senden"
                    }
                    Text(btnLabel, fontWeight = FontWeight.Bold, fontSize = 13.sp)
                }
            }
        }
    }
}
