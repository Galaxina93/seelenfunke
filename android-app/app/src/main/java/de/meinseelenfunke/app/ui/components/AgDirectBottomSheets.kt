package de.meinseelenfunke.app.ui.components

import android.Manifest
import android.app.Activity
import android.content.ActivityNotFoundException
import android.content.Intent
import android.content.pm.PackageManager
import android.graphics.BitmapFactory
import android.net.Uri
import android.speech.RecognizerIntent
import android.widget.Toast
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.core.content.ContextCompat
import androidx.core.content.FileProvider
import androidx.compose.animation.AnimatedVisibility
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.gestures.detectTapGestures
import androidx.compose.foundation.gestures.detectTransformGestures
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.imePadding
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.BasicTextField
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Add
import androidx.compose.material.icons.filled.Bolt
import androidx.compose.material.icons.filled.Close
import androidx.compose.material.icons.filled.DeleteOutline
import androidx.compose.material.icons.filled.GraphicEq
import androidx.compose.material.icons.filled.Image
import androidx.compose.material.icons.filled.Inventory2
import androidx.compose.material.icons.filled.Mic
import androidx.compose.material.icons.filled.PhotoCamera
import androidx.compose.material.icons.filled.Psychology
import androidx.compose.material.icons.filled.Refresh
import androidx.compose.material.icons.filled.Send
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.ModalBottomSheet
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.OutlinedTextFieldDefaults
import androidx.compose.material3.Switch
import androidx.compose.material3.SwitchDefaults
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.rememberModalBottomSheetState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableFloatStateOf
import androidx.compose.runtime.mutableStateListOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.ImageBitmap
import androidx.compose.ui.graphics.SolidColor
import androidx.compose.ui.graphics.asImageBitmap
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.input.pointer.pointerInput
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalConfiguration
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.ui.window.Dialog
import androidx.compose.ui.window.DialogProperties
import de.meinseelenfunke.app.R
import de.meinseelenfunke.app.ui.theme.AppTheme
import de.meinseelenfunke.app.ui.theme.Copper
import de.meinseelenfunke.app.ui.theme.Emerald500
import de.meinseelenfunke.app.ui.theme.Gold
import de.meinseelenfunke.app.ui.theme.Rose500
import de.meinseelenfunke.app.ui.theme.SpaceBlack
import de.meinseelenfunke.app.util.AgDirectChannel
import de.meinseelenfunke.app.util.AgDirectDisplayItem
import de.meinseelenfunke.app.util.AgDirectInboxManager
import de.meinseelenfunke.app.util.AgDirectStatus
import de.meinseelenfunke.app.util.FunkiraDraftPackage
import de.meinseelenfunke.app.util.FunkiraLocalMemoryStore
import de.meinseelenfunke.app.util.FunkiraPackageStore
import de.meinseelenfunke.app.util.PreparedAgAttachment
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import java.io.File
import java.time.OffsetDateTime
import java.time.format.DateTimeFormatter
import java.util.Base64
import java.util.Locale

private sealed class LightboxTarget {
    data class DirectBitmap(val bitmap: ImageBitmap, val title: String) : LightboxTarget()
    data class Base64Image(val base64Jpeg: String, val title: String) : LightboxTarget()
    data class InboxItem(val item: AgDirectDisplayItem) : LightboxTarget()
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun AgDirectInboxSheet(
    onDismiss: () -> Unit,
    initialAttachment: PreparedAgAttachment? = null,
    initialExpandedItemId: String? = null,
    onAttachmentSubmitted: () -> Unit = {}
) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    val sheetState = rememberModalBottomSheetState(skipPartiallyExpanded = true)
    val maxSheetHeight = (LocalConfiguration.current.screenHeightDp * 0.90f).dp

    val items by AgDirectInboxManager.itemsFlow.collectAsState()
    val isSyncing by AgDirectInboxManager.isSyncingFlow.collectAsState()
    val voiceDirectEnabled by AgDirectInboxManager.voiceDirectExecutionFlow.collectAsState()
    val draftPackages by FunkiraPackageStore.packagesFlow.collectAsState()
    val memorySnap by FunkiraLocalMemoryStore.snapshotFlow.collectAsState()

    var messageText by remember { mutableStateOf("") }
    val checklistItems = remember { mutableStateListOf<String>() }
    var executionMode by remember { mutableStateOf("concept") }
    var selectedChannel by remember { mutableStateOf(AgDirectChannel.LEITUNG.code) }
    var selectedAttachment by remember {
        mutableStateOf(initialAttachment ?: AgDirectInboxManager.getActiveStagedAttachment())
    }
    var isPreparingImage by remember { mutableStateOf(false) }
    var isPreparingAudio by remember { mutableStateOf(false) }
    var expandedItemId by remember(initialExpandedItemId) { mutableStateOf(initialExpandedItemId) }
    var lightboxTarget by remember { mutableStateOf<LightboxTarget?>(null) }
    var pendingCameraUri by remember { mutableStateOf<Uri?>(null) }
    var pendingPackageUuidForPhoto by remember { mutableStateOf<String?>(null) }
    var confirmDeletePackage by remember { mutableStateOf<FunkiraDraftPackage?>(null) }
    var showMemoryDrawer by remember { mutableStateOf(false) }
    var confirmClearMemory by remember { mutableStateOf(false) }

    LaunchedEffect(Unit) {
        AgDirectInboxManager.hydrateLocalState(context)
        FunkiraPackageStore.hydrate(context)
        FunkiraLocalMemoryStore.hydrate(context)
        AgDirectInboxManager.refreshAsync(context)
    }

    val speechLauncher = rememberLauncherForActivityResult(
        contract = ActivityResultContracts.StartActivityForResult()
    ) { result ->
        if (result.resultCode == Activity.RESULT_OK) {
            val spoken = result.data
                ?.getStringArrayListExtra(RecognizerIntent.EXTRA_RESULTS)
                ?.firstOrNull()
                ?.trim()
            if (!spoken.isNullOrEmpty()) {
                messageText = if (messageText.isBlank()) {
                    spoken
                } else {
                    "${messageText.trimEnd()} $spoken"
                }
            }
        }
    }

    val imagePickerLauncher = rememberLauncherForActivityResult(
        contract = ActivityResultContracts.GetContent()
    ) { uri: Uri? ->
        val targetPkgUuid = pendingPackageUuidForPhoto
        pendingPackageUuidForPhoto = null
        if (uri != null) {
            isPreparingImage = true
            scope.launch {
                val prepared = AgDirectInboxManager.prepareAttachmentFromUri(context, uri)
                isPreparingImage = false
                if (prepared != null) {
                    if (targetPkgUuid != null) {
                        FunkiraPackageStore.attachMediaToPackage(
                            context = context,
                            attachment = prepared,
                            caption = prepared.fileName,
                            packageUuid = targetPkgUuid
                        )
                        Toast.makeText(context, "✓ Bild ins Sammelpaket gelegt", Toast.LENGTH_SHORT).show()
                    } else {
                        selectedAttachment = prepared
                        de.meinseelenfunke.app.util.FalconCameraController.syncFromPreparedAttachment(prepared)
                    }
                } else {
                    Toast.makeText(context, "Bild konnte nicht geladen werden.", Toast.LENGTH_SHORT).show()
                }
            }
        }
    }

    val takePictureLauncher = rememberLauncherForActivityResult(
        contract = ActivityResultContracts.TakePicture()
    ) { success: Boolean ->
        val uri = pendingCameraUri
        val targetPkgUuid = pendingPackageUuidForPhoto
        pendingCameraUri = null
        pendingPackageUuidForPhoto = null
        if (success && uri != null) {
            isPreparingImage = true
            scope.launch {
                val prepared = AgDirectInboxManager.prepareAttachmentFromUri(context, uri)
                isPreparingImage = false
                if (prepared != null) {
                    if (targetPkgUuid != null) {
                        FunkiraPackageStore.attachMediaToPackage(
                            context = context,
                            attachment = prepared,
                            caption = "Kamera-Foto",
                            packageUuid = targetPkgUuid
                        )
                        Toast.makeText(context, "✓ Foto ins Sammelpaket gelegt", Toast.LENGTH_SHORT).show()
                    } else {
                        selectedAttachment = prepared
                        de.meinseelenfunke.app.util.FalconCameraController.syncFromPreparedAttachment(prepared)
                    }
                } else {
                    Toast.makeText(context, "Foto konnte nicht verarbeitet werden.", Toast.LENGTH_SHORT).show()
                }
            }
        }
    }

    val launchCameraCapture: (String?) -> Unit = { targetPkgUuid ->
        try {
            pendingPackageUuidForPhoto = targetPkgUuid
            val dir = File(context.cacheDir, "camera_captures").apply { mkdirs() }
            val outFile = File(dir, "ag_cam_${System.currentTimeMillis()}.jpg")
            val uri = FileProvider.getUriForFile(context, "${context.packageName}.fileprovider", outFile)
            pendingCameraUri = uri
            takePictureLauncher.launch(uri)
        } catch (e: Throwable) {
            pendingPackageUuidForPhoto = null
            Toast.makeText(context, "Kamera nicht verfügbar.", Toast.LENGTH_SHORT).show()
        }
    }

    val cameraPermissionLauncher = rememberLauncherForActivityResult(
        contract = ActivityResultContracts.RequestPermission()
    ) { granted: Boolean ->
        val targetPkgUuid = pendingPackageUuidForPhoto
        if (granted) {
            launchCameraCapture(targetPkgUuid)
        } else {
            pendingPackageUuidForPhoto = null
            Toast.makeText(context, "Kamera-Berechtigung erforderlich.", Toast.LENGTH_SHORT).show()
        }
    }

    val audioPickerLauncher = rememberLauncherForActivityResult(
        contract = ActivityResultContracts.GetContent()
    ) { uri: Uri? ->
        if (uri != null) {
            isPreparingAudio = true
            scope.launch {
                val prepared = AgDirectInboxManager.prepareAttachmentFromUri(context, uri)
                isPreparingAudio = false
                if (prepared != null) {
                    selectedAttachment = prepared
                    de.meinseelenfunke.app.util.FalconCameraController.syncFromPreparedAttachment(prepared)
                } else {
                    Toast.makeText(context, "Audiodatei konnte nicht geladen werden.", Toast.LENGTH_SHORT).show()
                }
            }
        }
    }

    lightboxTarget?.let { target ->
        AgImageLightboxDialog(
            target = target,
            onDismiss = { lightboxTarget = null }
        )
    }

    confirmDeletePackage?.let { pkg ->
        AlertDialog(
            onDismissRequest = { confirmDeletePackage = null },
            containerColor = AppTheme.colors.cardBackground,
            title = {
                Text(
                    text = "Sammelpaket löschen?",
                    color = AppTheme.colors.textPrimary,
                    fontWeight = FontWeight.Bold
                )
            },
            text = {
                Text(
                    text = pkg.title,
                    color = AppTheme.colors.textSecondary,
                    fontSize = 13.sp
                )
            },
            confirmButton = {
                TextButton(onClick = {
                    FunkiraPackageStore.deletePackage(context, pkg.uuid)
                    confirmDeletePackage = null
                }) {
                    Text("Löschen", color = Rose500, fontWeight = FontWeight.Bold)
                }
            },
            dismissButton = {
                TextButton(onClick = { confirmDeletePackage = null }) {
                    Text("Abbrechen", color = AppTheme.colors.textPrimary)
                }
            }
        )
    }

    if (confirmClearMemory) {
        AlertDialog(
            onDismissRequest = { confirmClearMemory = false },
            containerColor = AppTheme.colors.cardBackground,
            title = {
                Text(
                    text = "Gedächtnis zurücksetzen?",
                    color = AppTheme.colors.textPrimary,
                    fontWeight = FontWeight.Bold
                )
            },
            text = {
                Text(
                    text = "Lokale Gesprächsverläufe und gemerkte Fakten werden gelöscht.",
                    color = AppTheme.colors.textSecondary,
                    fontSize = 13.sp
                )
            },
            confirmButton = {
                TextButton(onClick = {
                    FunkiraLocalMemoryStore.clearMemory(context)
                    confirmClearMemory = false
                    Toast.makeText(context, "Gedächtnis geleert", Toast.LENGTH_SHORT).show()
                }) {
                    Text("Zurücksetzen", color = Rose500, fontWeight = FontWeight.Bold)
                }
            },
            dismissButton = {
                TextButton(onClick = { confirmClearMemory = false }) {
                    Text("Abbrechen", color = AppTheme.colors.textPrimary)
                }
            }
        )
    }

    ModalBottomSheet(
        onDismissRequest = onDismiss,
        sheetState = sheetState,
        containerColor = AppTheme.colors.cardBackground,
        contentColor = AppTheme.colors.textPrimary,
        dragHandle = null,
        shape = RoundedCornerShape(topStart = 22.dp, topEnd = 22.dp)
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .heightIn(max = maxSheetHeight)
                .navigationBarsPadding()
                .imePadding()
                .verticalScroll(rememberScrollState())
                .padding(horizontal = 16.dp, vertical = 14.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp)
        ) {
            // Kopfzeile
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(10.dp)
                ) {
                    Box(
                        modifier = Modifier
                            .size(34.dp)
                            .clip(CircleShape)
                            .background(Gold.copy(alpha = 0.16f))
                            .border(1.dp, Gold.copy(alpha = 0.65f), CircleShape),
                        contentAlignment = Alignment.Center
                    ) {
                        Image(
                            painter = painterResource(id = R.drawable.funkira_avatar),
                            contentDescription = "Funkira",
                            contentScale = ContentScale.Crop,
                            modifier = Modifier
                                .fillMaxSize()
                                .clip(CircleShape)
                        )
                    }
                    Text(
                        text = "Direktnachricht · Antigravity",
                        fontSize = 16.sp,
                        fontWeight = FontWeight.Bold,
                        color = AppTheme.colors.textPrimary
                    )
                }

                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(6.dp)
                ) {
                    Box(
                        modifier = Modifier
                            .size(32.dp)
                            .clip(CircleShape)
                            .background(AppTheme.colors.glassSurface)
                            .border(1.dp, AppTheme.colors.glassBorder, CircleShape)
                            .clickable(enabled = !isSyncing) {
                                AgDirectInboxManager.refreshAsync(context)
                            },
                        contentAlignment = Alignment.Center
                    ) {
                        if (isSyncing) {
                            CircularProgressIndicator(
                                color = Gold,
                                strokeWidth = 2.dp,
                                modifier = Modifier.size(15.dp)
                            )
                        } else {
                            Icon(
                                imageVector = Icons.Default.Refresh,
                                contentDescription = "Aktualisieren",
                                tint = Gold,
                                modifier = Modifier.size(16.dp)
                            )
                        }
                    }

                    Box(
                        modifier = Modifier
                            .size(32.dp)
                            .clip(CircleShape)
                            .background(AppTheme.colors.glassSurface)
                            .border(1.dp, AppTheme.colors.glassBorder, CircleShape)
                            .clickable(onClick = onDismiss),
                        contentAlignment = Alignment.Center
                    ) {
                        Icon(
                            imageVector = Icons.Default.Close,
                            contentDescription = "Schließen",
                            tint = AppTheme.colors.textSecondary,
                            modifier = Modifier.size(16.dp)
                        )
                    }
                }
            }

            // 1-zeilige Abteilungs-Kanal-Leiste (ohne Subtext)
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .horizontalScroll(rememberScrollState()),
                horizontalArrangement = Arrangement.spacedBy(6.dp),
                verticalAlignment = Alignment.CenterVertically
            ) {
                AgDirectChannel.entries.forEach { channelEntry ->
                    AgChannelChip(
                        label = channelEntry.chipLabel,
                        selected = selectedChannel == channelEntry.code,
                        onClick = { selectedChannel = channelEntry.code }
                    )
                }
            }

            // Hauptfeld + Spracheingabe
            Row(
                modifier = Modifier.fillMaxWidth(),
                verticalAlignment = Alignment.Top,
                horizontalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                OutlinedTextField(
                    value = messageText,
                    onValueChange = { messageText = it },
                    modifier = Modifier.weight(1f),
                    placeholder = {
                        Text(
                            text = "Nachricht / Idee",
                            color = AppTheme.colors.textMuted,
                            fontSize = 14.sp
                        )
                    },
                    trailingIcon = if (messageText.isNotEmpty()) {
                        {
                            Box(
                                modifier = Modifier
                                    .size(28.dp)
                                    .clip(CircleShape)
                                    .background(AppTheme.colors.glassSurface)
                                    .border(1.dp, AppTheme.colors.glassBorder, CircleShape)
                                    .clickable { messageText = "" },
                                contentAlignment = Alignment.Center
                            ) {
                                Icon(
                                    imageVector = Icons.Default.Close,
                                    contentDescription = "Text löschen",
                                    tint = AppTheme.colors.textSecondary,
                                    modifier = Modifier.size(15.dp)
                                )
                            }
                        }
                    } else {
                        null
                    },
                    minLines = 2,
                    maxLines = 6,
                    shape = RoundedCornerShape(12.dp),
                    textStyle = TextStyle(
                        fontSize = 14.sp,
                        color = AppTheme.colors.textPrimary
                    ),
                    colors = OutlinedTextFieldDefaults.colors(
                        focusedBorderColor = Gold,
                        unfocusedBorderColor = AppTheme.colors.inputBorder,
                        cursorColor = Gold,
                        focusedContainerColor = AppTheme.colors.inputBackground,
                        unfocusedContainerColor = AppTheme.colors.inputBackground,
                        focusedTextColor = AppTheme.colors.textPrimary,
                        unfocusedTextColor = AppTheme.colors.textPrimary
                    )
                )

                Box(
                    modifier = Modifier
                        .size(48.dp)
                        .clip(RoundedCornerShape(12.dp))
                        .background(Gold)
                        .clickable {
                            val speechIntent = Intent(RecognizerIntent.ACTION_RECOGNIZE_SPEECH).apply {
                                putExtra(
                                    RecognizerIntent.EXTRA_LANGUAGE_MODEL,
                                    RecognizerIntent.LANGUAGE_MODEL_FREE_FORM
                                )
                                putExtra(RecognizerIntent.EXTRA_LANGUAGE, "de-DE")
                                putExtra(RecognizerIntent.EXTRA_PROMPT, "Nachricht sprechen")
                            }
                            try {
                                speechLauncher.launch(speechIntent)
                            } catch (_: ActivityNotFoundException) {
                                Toast.makeText(
                                    context,
                                    "Spracheingabe auf diesem Gerät nicht verfügbar.",
                                    Toast.LENGTH_SHORT
                                ).show()
                            } catch (_: Exception) {
                                Toast.makeText(
                                    context,
                                    "Spracheingabe konnte nicht gestartet werden.",
                                    Toast.LENGTH_SHORT
                                ).show()
                            }
                        },
                    contentAlignment = Alignment.Center
                ) {
                    Icon(
                        imageVector = Icons.Default.Mic,
                        contentDescription = "Diktieren",
                        tint = SpaceBlack,
                        modifier = Modifier.size(21.dp)
                    )
                }
            }

            // Dynamische Unterpunkte
            if (checklistItems.isNotEmpty()) {
                Column(
                    modifier = Modifier.fillMaxWidth(),
                    verticalArrangement = Arrangement.spacedBy(6.dp)
                ) {
                    checklistItems.forEachIndexed { index, pointText ->
                        Row(
                            modifier = Modifier
                                .fillMaxWidth()
                                .clip(RoundedCornerShape(10.dp))
                                .background(AppTheme.colors.inputBackground)
                                .border(1.dp, AppTheme.colors.inputBorder, RoundedCornerShape(10.dp))
                                .padding(horizontal = 10.dp, vertical = 6.dp),
                            verticalAlignment = Alignment.CenterVertically,
                            horizontalArrangement = Arrangement.spacedBy(8.dp)
                        ) {
                            Box(
                                modifier = Modifier
                                    .size(6.dp)
                                    .clip(CircleShape)
                                    .background(Gold)
                            )
                            Box(modifier = Modifier.weight(1f)) {
                                if (pointText.isEmpty()) {
                                    Text(
                                        text = "Punkt ${index + 1}",
                                        fontSize = 13.sp,
                                        color = AppTheme.colors.textMuted
                                    )
                                }
                                BasicTextField(
                                    value = pointText,
                                    onValueChange = { checklistItems[index] = it },
                                    modifier = Modifier.fillMaxWidth(),
                                    textStyle = TextStyle(
                                        fontSize = 13.sp,
                                        color = AppTheme.colors.textPrimary
                                    ),
                                    cursorBrush = SolidColor(Gold),
                                    maxLines = 3
                                )
                            }
                            Box(
                                modifier = Modifier
                                    .size(24.dp)
                                    .clip(CircleShape)
                                    .clickable { checklistItems.removeAt(index) },
                                contentAlignment = Alignment.Center
                            ) {
                                Icon(
                                    imageVector = Icons.Default.Close,
                                    contentDescription = "Entfernen",
                                    tint = AppTheme.colors.textMuted,
                                    modifier = Modifier.size(15.dp)
                                )
                            }
                        }
                    }
                }
            }

            // Anhang-Vorschau (Bild oder Audio)
            selectedAttachment?.let { att ->
                if (att.isAudio) {
                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .clip(RoundedCornerShape(12.dp))
                            .background(AppTheme.colors.glassSurface)
                            .border(1.dp, Gold.copy(alpha = 0.5f), RoundedCornerShape(12.dp))
                            .padding(horizontal = 10.dp, vertical = 8.dp),
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.spacedBy(10.dp)
                    ) {
                        Box(
                            modifier = Modifier
                                .size(36.dp)
                                .clip(RoundedCornerShape(8.dp))
                                .background(Gold.copy(alpha = 0.16f))
                                .border(1.dp, Gold.copy(alpha = 0.65f), RoundedCornerShape(8.dp)),
                            contentAlignment = Alignment.Center
                        ) {
                            Icon(
                                imageVector = Icons.Default.GraphicEq,
                                contentDescription = "Audio",
                                tint = Gold,
                                modifier = Modifier.size(20.dp)
                            )
                        }
                        Text(
                            text = att.fileName,
                            fontSize = 12.5.sp,
                            fontWeight = FontWeight.Medium,
                            color = AppTheme.colors.textPrimary,
                            maxLines = 1,
                            overflow = TextOverflow.Ellipsis,
                            modifier = Modifier.weight(1f)
                        )
                        Box(
                            modifier = Modifier
                                .size(28.dp)
                                .clip(CircleShape)
                                .background(AppTheme.colors.cardBackground)
                                .clickable {
                                    selectedAttachment = null
                                    de.meinseelenfunke.app.util.FalconCameraController.clearLastCapturedPhoto()
                                },
                            contentAlignment = Alignment.Center
                        ) {
                            Icon(
                                imageVector = Icons.Default.Close,
                                contentDescription = "Audio entfernen",
                                tint = Rose500,
                                modifier = Modifier.size(15.dp)
                            )
                        }
                    }
                } else if (att.previewBitmap != null) {
                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .clip(RoundedCornerShape(12.dp))
                            .background(AppTheme.colors.glassSurface)
                            .border(1.dp, Gold.copy(alpha = 0.5f), RoundedCornerShape(12.dp))
                            .padding(8.dp),
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.spacedBy(10.dp)
                    ) {
                        Image(
                            bitmap = att.previewBitmap,
                            contentDescription = att.fileName,
                            contentScale = ContentScale.Crop,
                            modifier = Modifier
                                .size(46.dp)
                                .clip(RoundedCornerShape(8.dp))
                                .border(1.dp, AppTheme.colors.cardBorder, RoundedCornerShape(8.dp))
                                .clickable {
                                    lightboxTarget = LightboxTarget.DirectBitmap(
                                        bitmap = att.previewBitmap,
                                        title = att.fileName
                                    )
                                }
                        )
                        Text(
                            text = att.fileName,
                            fontSize = 12.5.sp,
                            fontWeight = FontWeight.Medium,
                            color = AppTheme.colors.textPrimary,
                            maxLines = 1,
                            overflow = TextOverflow.Ellipsis,
                            modifier = Modifier
                                .weight(1f)
                                .clickable {
                                    lightboxTarget = LightboxTarget.DirectBitmap(
                                        bitmap = att.previewBitmap,
                                        title = att.fileName
                                    )
                                }
                        )
                        Box(
                            modifier = Modifier
                                .size(28.dp)
                                .clip(CircleShape)
                                .background(AppTheme.colors.cardBackground)
                                .clickable {
                                    selectedAttachment = null
                                    de.meinseelenfunke.app.util.FalconCameraController.clearLastCapturedPhoto()
                                },
                            contentAlignment = Alignment.Center
                        ) {
                            Icon(
                                imageVector = Icons.Default.Close,
                                contentDescription = "Bild entfernen",
                                tint = Rose500,
                                modifier = Modifier.size(15.dp)
                            )
                        }
                    }
                }
            }

            // Aktionszeile: + Punkt & + Foto & + Bild & + Audio
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .horizontalScroll(rememberScrollState()),
                horizontalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                Row(
                    modifier = Modifier
                        .clip(RoundedCornerShape(10.dp))
                        .background(AppTheme.colors.glassSurface)
                        .border(1.dp, AppTheme.colors.glassBorder, RoundedCornerShape(10.dp))
                        .clickable { checklistItems.add("") }
                        .padding(horizontal = 11.dp, vertical = 8.dp),
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(5.dp)
                ) {
                    Icon(
                        imageVector = Icons.Default.Add,
                        contentDescription = null,
                        tint = Gold,
                        modifier = Modifier.size(15.dp)
                    )
                    Text(
                        text = "Punkt",
                        fontSize = 12.5.sp,
                        fontWeight = FontWeight.SemiBold,
                        color = AppTheme.colors.textPrimary
                    )
                }

                Row(
                    modifier = Modifier
                        .clip(RoundedCornerShape(10.dp))
                        .background(AppTheme.colors.glassSurface)
                        .border(1.dp, Gold.copy(alpha = 0.55f), RoundedCornerShape(10.dp))
                        .clickable(enabled = !isPreparingImage) {
                            pendingPackageUuidForPhoto = null
                            val hasCam = ContextCompat.checkSelfPermission(
                                context,
                                Manifest.permission.CAMERA
                            ) == PackageManager.PERMISSION_GRANTED
                            if (hasCam) {
                                launchCameraCapture(null)
                            } else {
                                cameraPermissionLauncher.launch(Manifest.permission.CAMERA)
                            }
                        }
                        .padding(horizontal = 11.dp, vertical = 8.dp),
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(5.dp)
                ) {
                    Icon(
                        imageVector = Icons.Default.PhotoCamera,
                        contentDescription = null,
                        tint = Gold,
                        modifier = Modifier.size(15.dp)
                    )
                    Text(
                        text = "+ Foto",
                        fontSize = 12.5.sp,
                        fontWeight = FontWeight.SemiBold,
                        color = AppTheme.colors.textPrimary
                    )
                }

                Row(
                    modifier = Modifier
                        .clip(RoundedCornerShape(10.dp))
                        .background(AppTheme.colors.glassSurface)
                        .border(1.dp, AppTheme.colors.glassBorder, RoundedCornerShape(10.dp))
                        .clickable(enabled = !isPreparingImage) {
                            pendingPackageUuidForPhoto = null
                            imagePickerLauncher.launch("image/*")
                        }
                        .padding(horizontal = 11.dp, vertical = 8.dp),
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(5.dp)
                ) {
                    if (isPreparingImage) {
                        CircularProgressIndicator(
                            color = Gold,
                            strokeWidth = 2.dp,
                            modifier = Modifier.size(14.dp)
                        )
                    } else {
                        Icon(
                            imageVector = Icons.Default.Image,
                            contentDescription = null,
                            tint = Gold,
                            modifier = Modifier.size(15.dp)
                        )
                    }
                    Text(
                        text = "+ Bild",
                        fontSize = 12.5.sp,
                        fontWeight = FontWeight.SemiBold,
                        color = AppTheme.colors.textPrimary
                    )
                }

                Row(
                    modifier = Modifier
                        .clip(RoundedCornerShape(10.dp))
                        .background(AppTheme.colors.glassSurface)
                        .border(1.dp, AppTheme.colors.glassBorder, RoundedCornerShape(10.dp))
                        .clickable(enabled = !isPreparingAudio) {
                            audioPickerLauncher.launch("audio/*")
                        }
                        .padding(horizontal = 11.dp, vertical = 8.dp),
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(5.dp)
                ) {
                    if (isPreparingAudio) {
                        CircularProgressIndicator(
                            color = Gold,
                            strokeWidth = 2.dp,
                            modifier = Modifier.size(14.dp)
                        )
                    } else {
                        Icon(
                            imageVector = Icons.Default.GraphicEq,
                            contentDescription = null,
                            tint = Gold,
                            modifier = Modifier.size(15.dp)
                        )
                    }
                    Text(
                        text = "+ Audio",
                        fontSize = 12.5.sp,
                        fontWeight = FontWeight.SemiBold,
                        color = AppTheme.colors.textPrimary
                    )
                }
            }

            // Modus-Wahl (2 cleane Chips nebeneinander, ohne Subtext)
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                AgModeChip(
                    modifier = Modifier.weight(1f),
                    label = if (executionMode == "concept") "✓ Erst Konzept" else "Erst Konzept",
                    selected = executionMode == "concept",
                    onClick = { executionMode = "concept" }
                )
                AgModeChip(
                    modifier = Modifier.weight(1f),
                    label = if (executionMode == "direct") "✓ Direkt umsetzen" else "Direkt umsetzen",
                    selected = executionMode == "direct",
                    onClick = { executionMode = "direct" }
                )
            }

            // Senden & Ins Sammelpaket schnüren
            val canSend = (messageText.isNotBlank() ||
                checklistItems.any { it.isNotBlank() } ||
                selectedAttachment != null) && !isPreparingImage && !isPreparingAudio

            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                OutlinedButton(
                    onClick = {
                        val rawSnapshot = messageText.trim()
                        val checklistSnapshot = checklistItems.map { it.trim() }.filter { it.isNotEmpty() }
                        val modeSnapshot = executionMode
                        val channelSnapshot = selectedChannel
                        val attachmentSnapshot = selectedAttachment

                        val lines = rawSnapshot.lines().map { it.trim() }.filter { it.isNotEmpty() }
                        val stagedItems = if (checklistSnapshot.isNotEmpty()) {
                            (lines + checklistSnapshot).distinct()
                        } else {
                            lines
                        }
                        val pkg = FunkiraPackageStore.stageRequirement(
                            context = context,
                            item = stagedItems.firstOrNull(),
                            items = stagedItems.drop(1),
                            packageTitle = if (lines.size == 1 && checklistSnapshot.isNotEmpty()) lines.first() else null,
                            channel = channelSnapshot,
                            executionMode = modeSnapshot,
                            attachment = attachmentSnapshot,
                            attachmentCaption = rawSnapshot.take(60),
                            source = "sheet"
                        )
                        if (pkg != null) {
                            messageText = ""
                            checklistItems.clear()
                            selectedAttachment = null
                            if (attachmentSnapshot != null) {
                                de.meinseelenfunke.app.util.FalconCameraController.clearLastCapturedPhoto()
                                onAttachmentSubmitted()
                            }
                            Toast.makeText(
                                context,
                                "✓ Ins Sammelpaket '${pkg.title.take(28)}' gelegt",
                                Toast.LENGTH_SHORT
                            ).show()
                        }
                    },
                    enabled = canSend,
                    modifier = Modifier
                        .weight(0.95f)
                        .height(46.dp),
                    shape = RoundedCornerShape(12.dp),
                    border = BorderStroke(1.dp, if (canSend) Gold else Gold.copy(alpha = 0.35f)),
                    colors = ButtonDefaults.outlinedButtonColors(
                        contentColor = Gold,
                        disabledContentColor = Gold.copy(alpha = 0.4f)
                    ),
                    contentPadding = PaddingValues(horizontal = 10.dp)
                ) {
                    Icon(
                        imageVector = Icons.Default.Inventory2,
                        contentDescription = null,
                        modifier = Modifier.size(16.dp)
                    )
                    Spacer(Modifier.width(5.dp))
                    Text(
                        text = "Ins Paket",
                        fontSize = 13.sp,
                        fontWeight = FontWeight.Bold,
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis
                    )
                }

                Button(
                    onClick = {
                        val rawSnapshot = messageText
                        val checklistSnapshot = checklistItems.map { it.trim() }.filter { it.isNotEmpty() }
                        val modeSnapshot = executionMode
                        val channelSnapshot = selectedChannel
                        val attachmentSnapshot = selectedAttachment

                        // Formular sofort leeren für den nächsten Gedanken
                        messageText = ""
                        checklistItems.clear()
                        selectedAttachment = null
                        executionMode = "concept"
                        selectedChannel = AgDirectChannel.LEITUNG.code
                        if (attachmentSnapshot != null) {
                            de.meinseelenfunke.app.util.FalconCameraController.clearLastCapturedPhoto()
                            onAttachmentSubmitted()
                        }

                        scope.launch {
                            val sentLive = AgDirectInboxManager.submitMessage(
                                context = context,
                                rawText = rawSnapshot,
                                checklist = checklistSnapshot,
                                executionMode = modeSnapshot,
                                attachment = attachmentSnapshot,
                                channel = channelSnapshot
                            )
                            if (!sentLive) {
                                Toast.makeText(
                                    context,
                                    "Offline gespeichert (Wartet)",
                                    Toast.LENGTH_SHORT
                                ).show()
                            }
                        }
                    },
                    enabled = canSend,
                    modifier = Modifier
                        .weight(1.25f)
                        .height(46.dp),
                    shape = RoundedCornerShape(12.dp),
                    colors = ButtonDefaults.buttonColors(
                        containerColor = Gold,
                        contentColor = SpaceBlack,
                        disabledContainerColor = Gold.copy(alpha = 0.35f),
                        disabledContentColor = SpaceBlack.copy(alpha = 0.6f)
                    ),
                    contentPadding = PaddingValues(horizontal = 12.dp)
                ) {
                    Text(
                        text = "An Antigravity",
                        fontSize = 13.5.sp,
                        fontWeight = FontWeight.Bold,
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis
                    )
                }
            }

            HorizontalDivider(color = AppTheme.colors.cardBorder, thickness = 1.dp)

            // Sprachbefehle Direct-Go Switch (Clean Design)
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(vertical = 2.dp),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Text(
                    text = "Sprachbefehle direkt als Go senden",
                    fontSize = 12.5.sp,
                    fontWeight = FontWeight.Medium,
                    color = AppTheme.colors.textPrimary
                )
                Switch(
                    checked = voiceDirectEnabled,
                    onCheckedChange = { checked ->
                        AgDirectInboxManager.setVoiceDirectExecutionEnabled(context, checked)
                    },
                    colors = SwitchDefaults.colors(
                        checkedThumbColor = SpaceBlack,
                        checkedTrackColor = Gold,
                        checkedBorderColor = Gold,
                        uncheckedThumbColor = AppTheme.colors.textMuted,
                        uncheckedTrackColor = AppTheme.colors.cardBackground,
                        uncheckedBorderColor = AppTheme.colors.cardBorder
                    )
                )
            }

            HorizontalDivider(color = AppTheme.colors.cardBorder, thickness = 1.dp)

            // Offene Sammelpakete (Pakete schnüren vor dem Versand)
            if (draftPackages.isNotEmpty()) {
                Text(
                    text = "Sammelpakete (${draftPackages.size})",
                    fontSize = 13.sp,
                    fontWeight = FontWeight.Bold,
                    color = Gold
                )
                Column(
                    modifier = Modifier.fillMaxWidth(),
                    verticalArrangement = Arrangement.spacedBy(10.dp)
                ) {
                    draftPackages.forEach { pkg ->
                        FunkiraDraftPackageCard(
                            pkg = pkg,
                            onAddPoint = { text ->
                                FunkiraPackageStore.stageRequirement(
                                    context = context,
                                    item = text,
                                    packageUuid = pkg.uuid,
                                    channel = pkg.channel,
                                    executionMode = pkg.executionMode,
                                    source = "sheet"
                                )
                            },
                            onRemovePoint = { idx ->
                                FunkiraPackageStore.removeItem(context, pkg.uuid, idx)
                            },
                            onAddCameraPhoto = {
                                val hasCam = ContextCompat.checkSelfPermission(
                                    context,
                                    Manifest.permission.CAMERA
                                ) == PackageManager.PERMISSION_GRANTED
                                if (hasCam) {
                                    launchCameraCapture(pkg.uuid)
                                } else {
                                    pendingPackageUuidForPhoto = pkg.uuid
                                    cameraPermissionLauncher.launch(Manifest.permission.CAMERA)
                                }
                            },
                            onAddGalleryImage = {
                                pendingPackageUuidForPhoto = pkg.uuid
                                imagePickerLauncher.launch("image/*")
                            },
                            onOpenAttachment = { att ->
                                lightboxTarget = LightboxTarget.Base64Image(
                                    base64Jpeg = att.base64Data,
                                    title = att.caption.ifBlank { att.fileName }
                                )
                            },
                            onRemoveAttachment = { attId ->
                                FunkiraPackageStore.removeAttachment(context, pkg.uuid, attId)
                            },
                            onModeChange = { nextMode ->
                                FunkiraPackageStore.updateMode(context, pkg.uuid, nextMode)
                            },
                            onRelease = { mode ->
                                scope.launch {
                                    val res = FunkiraPackageStore.releasePackage(
                                        context = context,
                                        packageUuid = pkg.uuid,
                                        executionModeOverride = mode
                                    )
                                    Toast.makeText(
                                        context,
                                        if (res.sentLive) "✓ Sammelpaket an Antigravity gesendet" else "Offline gespeichert (Wartet)",
                                        Toast.LENGTH_SHORT
                                    ).show()
                                }
                            },
                            onDelete = { confirmDeletePackage = pkg }
                        )
                    }
                }
                HorizontalDivider(color = AppTheme.colors.cardBorder, thickness = 1.dp)
            }

            // Einklappbares lokales Kurzzeitgedächtnis ("Lokales Gehirn")
            if (memorySnap.turns.isNotEmpty() || memorySnap.rollingDigest.isNotBlank()) {
                Column(
                    modifier = Modifier
                        .fillMaxWidth()
                        .clip(RoundedCornerShape(12.dp))
                        .background(AppTheme.colors.glassSurface)
                        .border(1.dp, AppTheme.colors.cardBorder, RoundedCornerShape(12.dp))
                        .clickable { showMemoryDrawer = !showMemoryDrawer }
                        .padding(horizontal = 12.dp, vertical = 9.dp),
                    verticalArrangement = Arrangement.spacedBy(6.dp)
                ) {
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Row(
                            verticalAlignment = Alignment.CenterVertically,
                            horizontalArrangement = Arrangement.spacedBy(7.dp)
                        ) {
                            Icon(
                                imageVector = Icons.Default.Psychology,
                                contentDescription = null,
                                tint = Gold,
                                modifier = Modifier.size(16.dp)
                            )
                            Text(
                                text = "Gedächtnis (${memorySnap.memoryCount})",
                                fontSize = 12.5.sp,
                                fontWeight = FontWeight.Bold,
                                color = AppTheme.colors.textPrimary
                            )
                        }
                        Row(
                            verticalAlignment = Alignment.CenterVertically,
                            horizontalArrangement = Arrangement.spacedBy(10.dp)
                        ) {
                            if (showMemoryDrawer) {
                                Text(
                                    text = "Leeren",
                                    fontSize = 11.5.sp,
                                    fontWeight = FontWeight.Bold,
                                    color = Rose500,
                                    modifier = Modifier.clickable { confirmClearMemory = true }
                                )
                            }
                            Text(
                                text = if (showMemoryDrawer) "Ausblenden" else "Verlauf",
                                fontSize = 11.5.sp,
                                fontWeight = FontWeight.Bold,
                                color = Gold
                            )
                        }
                    }
                    if (showMemoryDrawer) {
                        memorySnap.turns.takeLast(6).forEach { turn ->
                            val who = if (turn.role == "user") "Du" else "Funkira"
                            Text(
                                text = "${turn.timeShort} $who: ${turn.body}".trim(),
                                fontSize = 11.5.sp,
                                lineHeight = 15.sp,
                                color = if (turn.role == "user") AppTheme.colors.textSecondary else AppTheme.colors.textPrimary,
                                maxLines = 2,
                                overflow = TextOverflow.Ellipsis
                            )
                        }
                    }
                }
                HorizontalDivider(color = AppTheme.colors.cardBorder, thickness = 1.dp)
            }

            // Verlauf
            Text(
                text = "Verlauf",
                fontSize = 13.sp,
                fontWeight = FontWeight.Bold,
                color = AppTheme.colors.textSecondary
            )

            if (items.isEmpty()) {
                Text(
                    text = "Keine Einträge",
                    fontSize = 12.sp,
                    color = AppTheme.colors.textMuted,
                    modifier = Modifier.padding(vertical = 8.dp)
                )
            } else {
                Column(
                    modifier = Modifier.fillMaxWidth(),
                    verticalArrangement = Arrangement.spacedBy(8.dp)
                ) {
                    items.forEach { item ->
                        val isExpanded = expandedItemId == item.id
                        AgHistoryItemCard(
                            item = item,
                            expanded = isExpanded,
                            onToggleExpand = {
                                expandedItemId = if (isExpanded) null else item.id
                            },
                            onOpenAttachment = {
                                lightboxTarget = LightboxTarget.InboxItem(item)
                            },
                            onRetry = {
                                AgDirectInboxManager.refreshAsync(context)
                            },
                            onDelete = {
                                scope.launch {
                                    val ok = AgDirectInboxManager.deleteEntry(context, item)
                                    if (ok && expandedItemId == item.id) {
                                        expandedItemId = null
                                    }
                                }
                            },
                            onGrantGo = {
                                scope.launch {
                                    val ok = AgDirectInboxManager.resolveBlockade(
                                        context = context,
                                        itemId = item.id,
                                        decision = "approved",
                                        answerText = "Go erteilt"
                                    )
                                    Toast.makeText(
                                        context,
                                        if (ok) "✓ Go an Antigravity übermittelt!" else "Offline als Direktauftrag gespeichert",
                                        Toast.LENGTH_SHORT
                                    ).show()
                                    AgDirectInboxManager.refreshFromServer(context)
                                }
                            },
                            onReplyAndApprove = { replyText ->
                                scope.launch {
                                    val ok = AgDirectInboxManager.resolveBlockade(
                                        context = context,
                                        itemId = item.id,
                                        decision = "info_provided",
                                        answerText = replyText
                                    )
                                    Toast.makeText(
                                        context,
                                        if (ok) "✓ Antwort & Freigabe übermittelt!" else "Offline als Antwort gespeichert",
                                        Toast.LENGTH_SHORT
                                    ).show()
                                    AgDirectInboxManager.refreshFromServer(context)
                                }
                            }
                        )
                    }
                }
            }
        }
    }
}

@Composable
private fun FunkiraDraftPackageCard(
    pkg: FunkiraDraftPackage,
    onAddPoint: (String) -> Unit,
    onRemovePoint: (Int) -> Unit,
    onAddCameraPhoto: () -> Unit,
    onAddGalleryImage: () -> Unit,
    onOpenAttachment: (de.meinseelenfunke.app.util.FunkiraPackageAttachment) -> Unit,
    onRemoveAttachment: (String) -> Unit,
    onModeChange: (String) -> Unit,
    onRelease: (String) -> Unit,
    onDelete: () -> Unit
) {
    var newBullet by remember(pkg.uuid) { mutableStateOf("") }
    val channelBadgeLabel = AgDirectInboxManager.resolveChannelEnum(pkg.channel).chipLabel

    Card(
        modifier = Modifier.fillMaxWidth(),
        colors = CardDefaults.cardColors(containerColor = AppTheme.colors.glassSurface),
        border = BorderStroke(1.2.dp, Gold.copy(alpha = 0.78f)),
        shape = RoundedCornerShape(14.dp)
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(12.dp),
            verticalArrangement = Arrangement.spacedBy(8.dp)
        ) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(6.dp)
                ) {
                    Box(
                        modifier = Modifier
                            .clip(RoundedCornerShape(6.dp))
                            .background(Gold)
                            .padding(horizontal = 7.dp, vertical = 2.dp)
                    ) {
                        Text(
                            text = "SAMMELPAKET",
                            fontSize = 10.sp,
                            fontWeight = FontWeight.ExtraBold,
                            color = SpaceBlack
                        )
                    }
                    Box(
                        modifier = Modifier
                            .clip(RoundedCornerShape(6.dp))
                            .background(AppTheme.colors.cardBackground)
                            .border(1.dp, Gold.copy(alpha = 0.45f), RoundedCornerShape(6.dp))
                            .padding(horizontal = 7.dp, vertical = 2.dp)
                    ) {
                        Text(
                            text = channelBadgeLabel,
                            fontSize = 10.5.sp,
                            fontWeight = FontWeight.SemiBold,
                            color = AppTheme.colors.textPrimary
                        )
                    }
                }
                IconButton(onClick = onDelete, modifier = Modifier.size(28.dp)) {
                    Icon(
                        imageVector = Icons.Default.DeleteOutline,
                        contentDescription = "Paket löschen",
                        tint = Rose500,
                        modifier = Modifier.size(17.dp)
                    )
                }
            }

            Text(
                text = pkg.title,
                fontSize = 14.sp,
                fontWeight = FontWeight.Bold,
                color = AppTheme.colors.textPrimary
            )

            if (pkg.checklist.isNotEmpty()) {
                Column(verticalArrangement = Arrangement.spacedBy(5.dp)) {
                    pkg.checklist.forEachIndexed { idx, bullet ->
                        Row(
                            modifier = Modifier
                                .fillMaxWidth()
                                .clip(RoundedCornerShape(9.dp))
                                .background(AppTheme.colors.cardBackground)
                                .border(1.dp, AppTheme.colors.cardBorder, RoundedCornerShape(9.dp))
                                .padding(horizontal = 10.dp, vertical = 7.dp),
                            verticalAlignment = Alignment.CenterVertically,
                            horizontalArrangement = Arrangement.SpaceBetween
                        ) {
                            Text(
                                text = "${idx + 1}. $bullet",
                                fontSize = 12.sp,
                                lineHeight = 16.sp,
                                color = AppTheme.colors.textPrimary,
                                modifier = Modifier.weight(1f)
                            )
                            IconButton(
                                onClick = { onRemovePoint(idx) },
                                modifier = Modifier.size(22.dp)
                            ) {
                                Icon(
                                    imageVector = Icons.Default.Close,
                                    contentDescription = "Punkt entfernen",
                                    tint = AppTheme.colors.textMuted,
                                    modifier = Modifier.size(14.dp)
                                )
                            }
                        }
                    }
                }
            }

            // Angehängte Fotos + Kamera/Galerie-Schnellbuttons
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .horizontalScroll(rememberScrollState()),
                horizontalArrangement = Arrangement.spacedBy(6.dp),
                verticalAlignment = Alignment.CenterVertically
            ) {
                Row(
                    modifier = Modifier
                        .clip(RoundedCornerShape(8.dp))
                        .background(AppTheme.colors.cardBackground)
                        .border(1.dp, Gold.copy(alpha = 0.55f), RoundedCornerShape(8.dp))
                        .clickable(onClick = onAddCameraPhoto)
                        .padding(horizontal = 8.dp, vertical = 5.dp),
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(4.dp)
                ) {
                    Icon(Icons.Default.PhotoCamera, contentDescription = "Foto hinzufügen", tint = Gold, modifier = Modifier.size(13.dp))
                    Text("+ Foto", fontSize = 11.sp, fontWeight = FontWeight.Bold, color = AppTheme.colors.textPrimary)
                }
                Row(
                    modifier = Modifier
                        .clip(RoundedCornerShape(8.dp))
                        .background(AppTheme.colors.cardBackground)
                        .border(1.dp, AppTheme.colors.cardBorder, RoundedCornerShape(8.dp))
                        .clickable(onClick = onAddGalleryImage)
                        .padding(horizontal = 8.dp, vertical = 5.dp),
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(4.dp)
                ) {
                    Icon(Icons.Default.Image, contentDescription = "Bild hinzufügen", tint = Gold, modifier = Modifier.size(13.dp))
                    Text("+ Bild", fontSize = 11.sp, fontWeight = FontWeight.Bold, color = AppTheme.colors.textPrimary)
                }
                pkg.attachments.forEach { att ->
                    Row(
                        verticalAlignment = Alignment.CenterVertically,
                        modifier = Modifier
                            .clip(RoundedCornerShape(8.dp))
                            .background(Gold.copy(alpha = 0.14f))
                            .border(1.dp, Gold.copy(alpha = 0.6f), RoundedCornerShape(8.dp))
                            .clickable { onOpenAttachment(att) }
                            .padding(horizontal = 8.dp, vertical = 5.dp)
                    ) {
                        Icon(Icons.Default.Image, contentDescription = null, tint = Gold, modifier = Modifier.size(13.dp))
                        Spacer(Modifier.width(4.dp))
                        Text(
                            text = "${att.caption.take(18)} (${att.sizeKb} KB)",
                            fontSize = 11.sp,
                            fontWeight = FontWeight.Bold,
                            color = AppTheme.colors.textPrimary
                        )
                        Spacer(Modifier.width(5.dp))
                        Icon(
                            imageVector = Icons.Default.Close,
                            contentDescription = "Foto entfernen",
                            tint = Rose500,
                            modifier = Modifier
                                .size(13.dp)
                                .clickable { onRemoveAttachment(att.id) }
                        )
                    }
                }
            }

            // Punkt schnell ergänzen
            Row(
                modifier = Modifier.fillMaxWidth(),
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(6.dp)
            ) {
                OutlinedTextField(
                    value = newBullet,
                    onValueChange = { newBullet = it },
                    placeholder = { Text("+ Punkt ergänzen...", fontSize = 12.sp, color = AppTheme.colors.textMuted) },
                    singleLine = true,
                    shape = RoundedCornerShape(10.dp),
                    textStyle = TextStyle(fontSize = 12.5.sp, color = AppTheme.colors.textPrimary),
                    colors = OutlinedTextFieldDefaults.colors(
                        focusedBorderColor = Gold,
                        unfocusedBorderColor = AppTheme.colors.inputBorder,
                        cursorColor = Gold,
                        focusedContainerColor = AppTheme.colors.inputBackground,
                        unfocusedContainerColor = AppTheme.colors.inputBackground
                    ),
                    modifier = Modifier.weight(1f)
                )
                Box(
                    modifier = Modifier
                        .size(42.dp)
                        .clip(RoundedCornerShape(10.dp))
                        .background(Gold.copy(alpha = 0.16f))
                        .border(1.dp, Gold, RoundedCornerShape(10.dp))
                        .clickable {
                            val t = newBullet.trim()
                            if (t.isNotEmpty()) {
                                newBullet = ""
                                onAddPoint(t)
                            }
                        },
                    contentAlignment = Alignment.Center
                ) {
                    Icon(Icons.Default.Add, contentDescription = "Punkt hinzufügen", tint = Gold, modifier = Modifier.size(18.dp))
                }
            }

            // Modus & Absenden
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Row(horizontalArrangement = Arrangement.spacedBy(6.dp), modifier = Modifier.weight(1f)) {
                    AgModeChip(
                        modifier = Modifier.weight(1f),
                        label = "Konzept",
                        selected = pkg.executionMode != "direct",
                        onClick = { onModeChange("concept") }
                    )
                    AgModeChip(
                        modifier = Modifier.weight(1f),
                        label = "Direkt",
                        selected = pkg.executionMode == "direct",
                        onClick = { onModeChange("direct") }
                    )
                }
                Spacer(Modifier.width(8.dp))
                Button(
                    onClick = { onRelease(pkg.executionMode) },
                    shape = RoundedCornerShape(10.dp),
                    colors = ButtonDefaults.buttonColors(containerColor = Gold, contentColor = SpaceBlack),
                    contentPadding = PaddingValues(horizontal = 12.dp, vertical = 8.dp),
                    modifier = Modifier.height(38.dp)
                ) {
                    Icon(Icons.Default.Send, contentDescription = null, modifier = Modifier.size(14.dp))
                    Spacer(Modifier.width(5.dp))
                    Text("Absenden", fontSize = 12.sp, fontWeight = FontWeight.Bold)
                }
            }
        }
    }
}

@Composable
private fun AgChannelChip(
    label: String,
    selected: Boolean,
    onClick: () -> Unit
) {
    val bgColor = if (selected) Gold.copy(alpha = 0.16f) else AppTheme.colors.glassSurface
    val borderColor = if (selected) Gold else AppTheme.colors.cardBorder
    val textColor = if (selected) Gold else AppTheme.colors.textSecondary

    Box(
        modifier = Modifier
            .height(34.dp)
            .clip(RoundedCornerShape(10.dp))
            .background(bgColor)
            .border(1.dp, borderColor, RoundedCornerShape(10.dp))
            .clickable(onClick = onClick)
            .padding(horizontal = 11.dp),
        contentAlignment = Alignment.Center
    ) {
        Text(
            text = label,
            fontSize = 12.sp,
            fontWeight = if (selected) FontWeight.Bold else FontWeight.Medium,
            color = textColor,
            maxLines = 1
        )
    }
}

@Composable
private fun AgModeChip(
    modifier: Modifier = Modifier,
    label: String,
    selected: Boolean,
    onClick: () -> Unit
) {
    val bgColor = if (selected) Gold.copy(alpha = 0.16f) else AppTheme.colors.glassSurface
    val borderColor = if (selected) Gold else AppTheme.colors.cardBorder
    val textColor = if (selected) Gold else AppTheme.colors.textSecondary

    Box(
        modifier = modifier
            .height(38.dp)
            .clip(RoundedCornerShape(10.dp))
            .background(bgColor)
            .border(1.dp, borderColor, RoundedCornerShape(10.dp))
            .clickable(onClick = onClick)
            .padding(horizontal = 10.dp),
        contentAlignment = Alignment.Center
    ) {
        Text(
            text = label,
            fontSize = 12.5.sp,
            fontWeight = if (selected) FontWeight.Bold else FontWeight.Medium,
            color = textColor,
            maxLines = 1,
            overflow = TextOverflow.Ellipsis
        )
    }
}

@Composable
private fun AgHistoryItemCard(
    item: AgDirectDisplayItem,
    expanded: Boolean,
    onToggleExpand: () -> Unit,
    onOpenAttachment: () -> Unit,
    onRetry: () -> Unit,
    onDelete: () -> Unit,
    onGrantGo: () -> Unit,
    onReplyAndApprove: (String) -> Unit = {}
) {
    val needsGo = item.status == AgDirectStatus.AWAITING_GO ||
        (item.executionMode == "concept" && item.status != AgDirectStatus.COMPLETED)
    var quickReplyText by remember(item.id) { mutableStateOf("") }
    val questionOrSummary = item.pendingQuestion?.takeIf { it.isNotBlank() }
        ?: item.agResponseSummary?.takeIf { it.isNotBlank() }

    val (badgeBg, badgeBorder, badgeText) = when (item.status) {
        AgDirectStatus.QUEUED -> Triple(
            Copper.copy(alpha = 0.15f),
            Copper.copy(alpha = 0.55f),
            Copper
        )
        AgDirectStatus.PENDING -> Triple(
            AppTheme.colors.glassSurface,
            AppTheme.colors.cardBorder,
            AppTheme.colors.textSecondary
        )
        AgDirectStatus.DELIVERED -> Triple(
            Gold.copy(alpha = 0.16f),
            Gold.copy(alpha = 0.55f),
            Gold
        )
        AgDirectStatus.AWAITING_GO -> Triple(
            Gold.copy(alpha = 0.24f),
            Gold,
            Gold
        )
        AgDirectStatus.COMPLETED -> Triple(
            Emerald500.copy(alpha = 0.15f),
            Emerald500.copy(alpha = 0.55f),
            Emerald500
        )
    }

    Card(
        modifier = Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(12.dp))
            .clickable(onClick = onToggleExpand),
        colors = CardDefaults.cardColors(containerColor = AppTheme.colors.glassSurface),
        border = BorderStroke(
            width = 1.dp,
            color = when {
                expanded -> Gold.copy(alpha = 0.75f)
                needsGo -> Gold.copy(alpha = 0.65f)
                else -> AppTheme.colors.cardBorder
            }
        ),
        shape = RoundedCornerShape(12.dp)
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = 12.dp, vertical = 10.dp),
            verticalArrangement = Arrangement.spacedBy(6.dp)
        ) {
            val channelBadgeLabel = AgDirectInboxManager.resolveChannelEnum(item.channel).chipLabel

            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(6.dp)
                ) {
                    Box(
                        modifier = Modifier
                            .clip(RoundedCornerShape(6.dp))
                            .background(AppTheme.colors.cardBackground)
                            .border(1.dp, Gold.copy(alpha = 0.42f), RoundedCornerShape(6.dp))
                            .padding(horizontal = 7.dp, vertical = 2.dp)
                    ) {
                        Text(
                            text = channelBadgeLabel,
                            fontSize = 10.5.sp,
                            fontWeight = FontWeight.SemiBold,
                            color = AppTheme.colors.textPrimary,
                            maxLines = 1
                        )
                    }

                    if (needsGo) {
                        Box(
                            modifier = Modifier
                                .clip(RoundedCornerShape(6.dp))
                                .background(Gold.copy(alpha = 0.16f))
                                .border(1.dp, Gold.copy(alpha = 0.65f), RoundedCornerShape(6.dp))
                                .padding(horizontal = 7.dp, vertical = 2.dp)
                        ) {
                            Text(
                                text = "⚡ Go benötigt",
                                fontSize = 10.5.sp,
                                fontWeight = FontWeight.SemiBold,
                                color = Gold,
                                maxLines = 1
                            )
                        }
                    } else if (item.executionMode == "concept" && item.status == AgDirectStatus.COMPLETED) {
                        Box(
                            modifier = Modifier
                                .clip(RoundedCornerShape(6.dp))
                                .background(AppTheme.colors.cardBackground)
                                .border(1.dp, AppTheme.colors.cardBorder, RoundedCornerShape(6.dp))
                                .padding(horizontal = 7.dp, vertical = 2.dp)
                        ) {
                            Text(
                                text = "Konzept",
                                fontSize = 10.5.sp,
                                fontWeight = FontWeight.Normal,
                                color = AppTheme.colors.textMuted,
                                maxLines = 1
                            )
                        }
                    }
                }

                Box(
                    modifier = Modifier
                        .clip(RoundedCornerShape(50))
                        .background(badgeBg)
                        .border(1.dp, badgeBorder, RoundedCornerShape(50))
                        .padding(horizontal = 8.dp, vertical = 3.dp)
                ) {
                    Text(
                        text = item.status.badgeLabel,
                        fontSize = 10.5.sp,
                        fontWeight = FontWeight.Bold,
                        color = badgeText
                    )
                }
            }

            Row(
                modifier = Modifier.fillMaxWidth(),
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(6.dp)
            ) {
                Text(
                    text = item.title,
                    fontSize = 13.5.sp,
                    fontWeight = FontWeight.SemiBold,
                    color = AppTheme.colors.textPrimary,
                    maxLines = if (expanded) 4 else 1,
                    overflow = TextOverflow.Ellipsis,
                    modifier = Modifier.weight(1f, fill = false)
                )
                if (item.hasAttachment) {
                    val isAudioAttachment = item.attachmentMime?.startsWith("audio/") == true ||
                        item.attachmentName?.let { name ->
                            name.endsWith(".m4a", true) || name.endsWith(".mp3", true) ||
                            name.endsWith(".wav", true) || name.endsWith(".ogg", true) ||
                            name.endsWith(".aac", true) || name.endsWith(".opus", true) ||
                            name.endsWith(".webm", true)
                        } == true

                    Icon(
                        imageVector = if (isAudioAttachment) Icons.Default.GraphicEq else Icons.Default.Image,
                        contentDescription = if (isAudioAttachment) "Audio vorhanden" else "Bild vorhanden",
                        tint = Gold,
                        modifier = Modifier.size(14.dp)
                    )
                }
            }

            if (needsGo && !expanded) {
                Button(
                    onClick = onGrantGo,
                    modifier = Modifier
                        .fillMaxWidth()
                        .height(34.dp),
                    shape = RoundedCornerShape(8.dp),
                    colors = ButtonDefaults.buttonColors(
                        containerColor = Gold,
                        contentColor = SpaceBlack
                    ),
                    contentPadding = PaddingValues(horizontal = 12.dp)
                ) {
                    Icon(
                        imageVector = Icons.Default.Bolt,
                        contentDescription = null,
                        modifier = Modifier.size(15.dp),
                        tint = SpaceBlack
                    )
                    Spacer(modifier = Modifier.width(5.dp))
                    Text(
                        text = "⚡ Go erteilen",
                        fontSize = 12.sp,
                        fontWeight = FontWeight.Bold
                    )
                }
            }

            AnimatedVisibility(visible = expanded) {
                Column(
                    modifier = Modifier.fillMaxWidth(),
                    verticalArrangement = Arrangement.spacedBy(8.dp)
                ) {
                    Row(
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.spacedBy(8.dp)
                    ) {
                        val modeLabel = if (item.executionMode == "direct") "Direkt umsetzen" else "Erst Konzept"
                        Text(
                            text = modeLabel,
                            fontSize = 11.sp,
                            fontWeight = FontWeight.SemiBold,
                            color = Gold
                        )
                        formatAgTimestamp(item.createdAt)?.let { ts ->
                            Text(
                                text = "· $ts",
                                fontSize = 11.sp,
                                color = AppTheme.colors.textMuted
                            )
                        }
                    }

                    if (item.body.isNotBlank() && item.body != item.title) {
                        Text(
                            text = item.body,
                            fontSize = 12.5.sp,
                            color = AppTheme.colors.textSecondary,
                            lineHeight = 17.sp
                        )
                    }

                    if (item.checklist.isNotEmpty()) {
                        Column(verticalArrangement = Arrangement.spacedBy(4.dp)) {
                            item.checklist.forEach { point ->
                                Row(
                                    verticalAlignment = Alignment.Top,
                                    horizontalArrangement = Arrangement.spacedBy(6.dp)
                                ) {
                                    Text(
                                        text = "✓",
                                        fontSize = 11.5.sp,
                                        fontWeight = FontWeight.Bold,
                                        color = Gold
                                    )
                                    Text(
                                        text = point,
                                        fontSize = 12.5.sp,
                                        color = AppTheme.colors.textPrimary,
                                        lineHeight = 16.sp
                                    )
                                }
                            }
                        }
                    }

                    if (!questionOrSummary.isNullOrBlank()) {
                        Box(
                            modifier = Modifier
                                .fillMaxWidth()
                                .clip(RoundedCornerShape(10.dp))
                                .background(Gold.copy(alpha = 0.10f))
                                .border(1.dp, Gold.copy(alpha = 0.45f), RoundedCornerShape(10.dp))
                                .padding(10.dp)
                        ) {
                            Text(
                                text = questionOrSummary,
                                fontSize = 12.sp,
                                color = AppTheme.colors.textPrimary,
                                lineHeight = 16.sp
                            )
                        }
                    }

                    if (needsGo) {
                        OutlinedTextField(
                            value = quickReplyText,
                            onValueChange = { quickReplyText = it },
                            modifier = Modifier.fillMaxWidth(),
                            placeholder = {
                                Text(
                                    text = "Antwort / Entscheidung eingeben",
                                    fontSize = 12.5.sp,
                                    color = AppTheme.colors.textMuted
                                )
                            },
                            minLines = 1,
                            maxLines = 3,
                            shape = RoundedCornerShape(10.dp),
                            textStyle = TextStyle(
                                fontSize = 13.sp,
                                color = AppTheme.colors.textPrimary
                            ),
                            colors = OutlinedTextFieldDefaults.colors(
                                focusedBorderColor = Gold,
                                unfocusedBorderColor = AppTheme.colors.inputBorder,
                                cursorColor = Gold,
                                focusedContainerColor = AppTheme.colors.inputBackground,
                                unfocusedContainerColor = AppTheme.colors.inputBackground
                            )
                        )

                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.spacedBy(8.dp)
                        ) {
                            Button(
                                onClick = onGrantGo,
                                modifier = Modifier
                                    .weight(1f)
                                    .height(40.dp),
                                shape = RoundedCornerShape(10.dp),
                                colors = ButtonDefaults.buttonColors(
                                    containerColor = Gold,
                                    contentColor = SpaceBlack
                                ),
                                contentPadding = PaddingValues(horizontal = 10.dp)
                            ) {
                                Text(
                                    text = "⚡ Go",
                                    fontSize = 12.5.sp,
                                    fontWeight = FontWeight.Bold
                                )
                            }

                            Button(
                                onClick = {
                                    val answer = quickReplyText.trim()
                                    if (answer.isNotEmpty()) {
                                        quickReplyText = ""
                                        onReplyAndApprove(answer)
                                    } else {
                                        onGrantGo()
                                    }
                                },
                                modifier = Modifier
                                    .weight(1.45f)
                                    .height(40.dp),
                                shape = RoundedCornerShape(10.dp),
                                colors = ButtonDefaults.buttonColors(
                                    containerColor = Emerald500,
                                    contentColor = Color.White
                                ),
                                contentPadding = PaddingValues(horizontal = 10.dp)
                            ) {
                                Text(
                                    text = "✓ Absenden & Freigeben",
                                    fontSize = 12.sp,
                                    fontWeight = FontWeight.Bold,
                                    maxLines = 1,
                                    overflow = TextOverflow.Ellipsis
                                )
                            }
                        }
                    }

                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                            if (item.hasAttachment) {
                                val isAudioAttachment = item.attachmentMime?.startsWith("audio/") == true ||
                                    item.attachmentName?.let { name ->
                                        name.endsWith(".m4a", true) || name.endsWith(".mp3", true) ||
                                        name.endsWith(".wav", true) || name.endsWith(".ogg", true) ||
                                        name.endsWith(".aac", true) || name.endsWith(".opus", true) ||
                                        name.endsWith(".webm", true)
                                    } == true

                                Row(
                                    modifier = Modifier
                                        .clip(RoundedCornerShape(8.dp))
                                        .background(AppTheme.colors.cardBackground)
                                        .border(1.dp, Gold.copy(alpha = 0.5f), RoundedCornerShape(8.dp))
                                        .clickable(onClick = onOpenAttachment)
                                        .padding(horizontal = 10.dp, vertical = 6.dp),
                                    verticalAlignment = Alignment.CenterVertically,
                                    horizontalArrangement = Arrangement.spacedBy(5.dp)
                                ) {
                                    Icon(
                                        imageVector = if (isAudioAttachment) Icons.Default.GraphicEq else Icons.Default.Image,
                                        contentDescription = null,
                                        tint = Gold,
                                        modifier = Modifier.size(14.dp)
                                    )
                                    Text(
                                        text = if (isAudioAttachment) "Audio" else "Bild",
                                        fontSize = 11.5.sp,
                                        fontWeight = FontWeight.SemiBold,
                                        color = AppTheme.colors.textPrimary
                                    )
                                }
                            }

                            if (item.isLocalOutbox) {
                                Row(
                                    modifier = Modifier
                                        .clip(RoundedCornerShape(8.dp))
                                        .background(AppTheme.colors.cardBackground)
                                        .border(1.dp, Gold.copy(alpha = 0.5f), RoundedCornerShape(8.dp))
                                        .clickable(onClick = onRetry)
                                        .padding(horizontal = 10.dp, vertical = 6.dp),
                                    verticalAlignment = Alignment.CenterVertically,
                                    horizontalArrangement = Arrangement.spacedBy(5.dp)
                                ) {
                                    Icon(
                                        imageVector = Icons.Default.Refresh,
                                        contentDescription = null,
                                        tint = Gold,
                                        modifier = Modifier.size(14.dp)
                                    )
                                    Text(
                                        text = "Senden",
                                        fontSize = 11.5.sp,
                                        fontWeight = FontWeight.SemiBold,
                                        color = AppTheme.colors.textPrimary
                                    )
                                }
                            }
                        }

                        IconButton(
                            onClick = onDelete,
                            modifier = Modifier.size(30.dp)
                        ) {
                            Icon(
                                imageVector = Icons.Default.DeleteOutline,
                                contentDescription = "Löschen",
                                tint = Rose500,
                                modifier = Modifier.size(18.dp)
                            )
                        }
                    }
                }
            }
        }
    }
}

@Composable
fun AgImageLightboxDialog(
    bitmap: ImageBitmap,
    title: String,
    onDismiss: () -> Unit,
    onSendToAntigravity: (() -> Unit)? = null
) {
    AgImageLightboxDialog(
        target = LightboxTarget.DirectBitmap(bitmap = bitmap, title = title),
        onDismiss = onDismiss,
        onSendToAntigravity = onSendToAntigravity
    )
}

@Composable
private fun AgImageLightboxDialog(
    target: LightboxTarget,
    onDismiss: () -> Unit,
    onSendToAntigravity: (() -> Unit)? = null
) {
    var bitmap by remember(target) {
        mutableStateOf(
            when (target) {
                is LightboxTarget.DirectBitmap -> target.bitmap
                is LightboxTarget.Base64Image -> null
                is LightboxTarget.InboxItem -> null
            }
        )
    }
    var isLoading by remember(target) {
        mutableStateOf(target is LightboxTarget.InboxItem || target is LightboxTarget.Base64Image)
    }
    var loadError by remember(target) { mutableStateOf(false) }

    val isAudioTarget = (target as? LightboxTarget.InboxItem)?.let {
        it.item.attachmentMime?.startsWith("audio/") == true ||
            it.item.attachmentName?.let { name ->
                name.endsWith(".m4a", true) || name.endsWith(".mp3", true) ||
                name.endsWith(".wav", true) || name.endsWith(".ogg", true) ||
                name.endsWith(".aac", true) || name.endsWith(".opus", true) ||
                name.endsWith(".webm", true)
            } == true
    } == true

    var scale by remember(target) { mutableFloatStateOf(1f) }
    var offset by remember(target) { mutableStateOf(Offset.Zero) }

    LaunchedEffect(target) {
        when (target) {
            is LightboxTarget.DirectBitmap -> {
                isLoading = false
                loadError = false
            }
            is LightboxTarget.Base64Image -> {
                isLoading = true
                loadError = false
                val loaded = withContext(Dispatchers.IO) {
                    try {
                        val clean = target.base64Jpeg.substringAfter("base64,", target.base64Jpeg)
                        val bytes = Base64.getMimeDecoder().decode(clean)
                        BitmapFactory.decodeByteArray(bytes, 0, bytes.size)?.asImageBitmap()
                    } catch (_: Throwable) {
                        null
                    }
                }
                bitmap = loaded
                loadError = loaded == null
                isLoading = false
            }
            is LightboxTarget.InboxItem -> {
                if (isAudioTarget) {
                    isLoading = false
                    loadError = false
                } else {
                    isLoading = true
                    loadError = false
                    val loaded = AgDirectInboxManager.loadAttachmentBitmap(target.item)
                    bitmap = loaded
                    loadError = loaded == null
                    isLoading = false
                }
            }
        }
    }

    val headerTitle = when (target) {
        is LightboxTarget.DirectBitmap -> target.title
        is LightboxTarget.Base64Image -> target.title
        is LightboxTarget.InboxItem -> target.item.attachmentName ?: target.item.title
    }

    Dialog(
        onDismissRequest = onDismiss,
        properties = DialogProperties(
            usePlatformDefaultWidth = false,
            decorFitsSystemWindows = false
        )
    ) {
        Box(
            modifier = Modifier
                .fillMaxSize()
                .background(SpaceBlack.copy(alpha = 0.95f))
                .statusBarsPadding()
                .navigationBarsPadding()
        ) {
            when {
                isLoading -> {
                    CircularProgressIndicator(
                        color = Gold,
                        modifier = Modifier.align(Alignment.Center)
                    )
                }
                isAudioTarget -> {
                    Column(
                        modifier = Modifier.align(Alignment.Center),
                        horizontalAlignment = Alignment.CenterHorizontally,
                        verticalArrangement = Arrangement.spacedBy(14.dp)
                    ) {
                        Box(
                            modifier = Modifier
                                .size(72.dp)
                                .clip(CircleShape)
                                .background(Gold.copy(alpha = 0.16f))
                                .border(1.dp, Gold, CircleShape),
                            contentAlignment = Alignment.Center
                        ) {
                            Icon(
                                imageVector = Icons.Default.GraphicEq,
                                contentDescription = "Audio",
                                tint = Gold,
                                modifier = Modifier.size(36.dp)
                            )
                        }
                        Text(
                            text = headerTitle,
                            color = Color.White,
                            fontSize = 14.sp,
                            fontWeight = FontWeight.Medium
                        )
                    }
                }
                bitmap != null -> {
                    Image(
                        bitmap = bitmap!!,
                        contentDescription = headerTitle,
                        contentScale = ContentScale.Fit,
                        modifier = Modifier
                            .fillMaxSize()
                            .pointerInput(target) {
                                detectTapGestures(
                                    onDoubleTap = {
                                        if (scale > 1.05f) {
                                            scale = 1f
                                            offset = Offset.Zero
                                        } else {
                                            scale = 2.4f
                                        }
                                    }
                                )
                            }
                            .pointerInput(target) {
                                detectTransformGestures { _, pan, zoom, _ ->
                                    val newScale = (scale * zoom).coerceIn(1f, 5f)
                                    scale = newScale
                                    offset = if (newScale > 1f) {
                                        offset + pan
                                    } else {
                                        Offset.Zero
                                    }
                                }
                            }
                            .graphicsLayer(
                                scaleX = scale,
                                scaleY = scale,
                                translationX = offset.x,
                                translationY = offset.y
                            )
                    )
                }
                loadError -> {
                    Text(
                        text = "Bild nicht verfügbar",
                        color = Color.White,
                        fontSize = 14.sp,
                        modifier = Modifier.align(Alignment.Center)
                    )
                }
            }

            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .align(Alignment.TopCenter)
                    .padding(horizontal = 16.dp, vertical = 12.dp),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Text(
                    text = headerTitle,
                    color = Color.White,
                    fontSize = 14.sp,
                    fontWeight = FontWeight.SemiBold,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis,
                    modifier = Modifier
                        .weight(1f)
                        .padding(end = 12.dp)
                )

                Box(
                    modifier = Modifier
                        .size(36.dp)
                        .clip(CircleShape)
                        .background(Color.White.copy(alpha = 0.14f))
                        .border(1.dp, Gold.copy(alpha = 0.6f), CircleShape)
                        .clickable(onClick = onDismiss),
                    contentAlignment = Alignment.Center
                ) {
                    Icon(
                        imageVector = Icons.Default.Close,
                        contentDescription = "Schließen",
                        tint = Gold,
                        modifier = Modifier.size(18.dp)
                    )
                }
            }

            if (onSendToAntigravity != null) {
                Box(
                    modifier = Modifier
                        .fillMaxWidth()
                        .align(Alignment.BottomCenter)
                        .padding(horizontal = 20.dp, vertical = 20.dp),
                    contentAlignment = Alignment.Center
                ) {
                    Row(
                        modifier = Modifier
                            .clip(RoundedCornerShape(14.dp))
                            .background(Gold)
                            .clickable {
                                onDismiss()
                                onSendToAntigravity()
                            }
                            .padding(horizontal = 22.dp, vertical = 12.dp),
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.spacedBy(8.dp)
                    ) {
                        Icon(
                            imageVector = Icons.Default.Bolt,
                            contentDescription = null,
                            tint = SpaceBlack,
                            modifier = Modifier.size(18.dp)
                        )
                        Text(
                            text = "⚡ An Antigravity senden",
                            color = SpaceBlack,
                            fontSize = 14.sp,
                            fontWeight = FontWeight.Bold
                        )
                    }
                }
            }
        }
    }
}

internal fun formatAgTimestamp(rawIso: String?): String? {
    if (rawIso.isNullOrBlank()) return null
    return try {
        val parsed = OffsetDateTime.parse(rawIso)
        parsed.format(DateTimeFormatter.ofPattern("dd.MM. HH:mm", Locale.GERMANY))
    } catch (_: Throwable) {
        if (rawIso.length >= 16) rawIso.substring(0, 16).replace('T', ' ') else rawIso
    }
}
