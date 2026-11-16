package de.meinseelenfunke.app.ui.components

import android.app.Activity
import android.content.ActivityNotFoundException
import android.content.Intent
import android.net.Uri
import android.speech.RecognizerIntent
import android.widget.Toast
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.animation.AnimatedVisibility
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.gestures.detectTapGestures
import androidx.compose.foundation.gestures.detectTransformGestures
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
import androidx.compose.material.icons.filled.Close
import androidx.compose.material.icons.filled.DeleteOutline
import androidx.compose.material.icons.filled.Image
import androidx.compose.material.icons.filled.Mic
import androidx.compose.material.icons.filled.Refresh
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
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.OutlinedTextFieldDefaults
import androidx.compose.material3.Text
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
import de.meinseelenfunke.app.util.AgDirectDisplayItem
import de.meinseelenfunke.app.util.AgDirectInboxManager
import de.meinseelenfunke.app.util.AgDirectStatus
import de.meinseelenfunke.app.util.PreparedAgAttachment
import kotlinx.coroutines.launch
import java.time.OffsetDateTime
import java.time.format.DateTimeFormatter
import java.util.Locale

private sealed class LightboxTarget {
    data class DirectBitmap(val bitmap: ImageBitmap, val title: String) : LightboxTarget()
    data class InboxItem(val item: AgDirectDisplayItem) : LightboxTarget()
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun AgDirectInboxSheet(
    onDismiss: () -> Unit,
    initialAttachment: PreparedAgAttachment? = null,
    onAttachmentSubmitted: () -> Unit = {}
) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    val sheetState = rememberModalBottomSheetState(skipPartiallyExpanded = true)
    val maxSheetHeight = (LocalConfiguration.current.screenHeightDp * 0.90f).dp

    val items by AgDirectInboxManager.itemsFlow.collectAsState()
    val isSyncing by AgDirectInboxManager.isSyncingFlow.collectAsState()

    var messageText by remember { mutableStateOf("") }
    val checklistItems = remember { mutableStateListOf<String>() }
    var executionMode by remember { mutableStateOf("concept") }
    var selectedAttachment by remember { mutableStateOf(initialAttachment) }
    var isPreparingImage by remember { mutableStateOf(false) }
    var expandedItemId by remember { mutableStateOf<String?>(null) }
    var lightboxTarget by remember { mutableStateOf<LightboxTarget?>(null) }

    LaunchedEffect(Unit) {
        AgDirectInboxManager.hydrateLocalState(context)
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
        if (uri != null) {
            isPreparingImage = true
            scope.launch {
                val prepared = AgDirectInboxManager.prepareAttachmentFromUri(context, uri)
                isPreparingImage = false
                if (prepared != null) {
                    selectedAttachment = prepared
                } else {
                    Toast.makeText(context, "Bild konnte nicht geladen werden.", Toast.LENGTH_SHORT).show()
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
                            .border(1.dp, Gold.copy(alpha = 0.65f), CircleShape)
                            .padding(2.dp),
                        contentAlignment = Alignment.Center
                    ) {
                        Image(
                            painter = painterResource(id = R.drawable.falcon_avatar),
                            contentDescription = "Falcon",
                            contentScale = ContentScale.Fit,
                            modifier = Modifier.fillMaxSize()
                        )
                    }
                    Text(
                        text = "Direktnachricht · Falcon",
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

            // Bild-Vorschau
            selectedAttachment?.let { att ->
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
                            .clickable { selectedAttachment = null },
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

            // Aktionszeile: + Punkt & + Bild
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                Row(
                    modifier = Modifier
                        .clip(RoundedCornerShape(10.dp))
                        .background(AppTheme.colors.glassSurface)
                        .border(1.dp, AppTheme.colors.glassBorder, RoundedCornerShape(10.dp))
                        .clickable { checklistItems.add("") }
                        .padding(horizontal = 12.dp, vertical = 8.dp),
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
                        .border(1.dp, AppTheme.colors.glassBorder, RoundedCornerShape(10.dp))
                        .clickable(enabled = !isPreparingImage) {
                            imagePickerLauncher.launch("image/*")
                        }
                        .padding(horizontal = 12.dp, vertical = 8.dp),
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

            // Senden-Button
            val canSend = (messageText.isNotBlank() ||
                checklistItems.any { it.isNotBlank() } ||
                selectedAttachment != null) && !isPreparingImage

            Button(
                onClick = {
                    val rawSnapshot = messageText
                    val checklistSnapshot = checklistItems.map { it.trim() }.filter { it.isNotEmpty() }
                    val modeSnapshot = executionMode
                    val attachmentSnapshot = selectedAttachment

                    // Formular sofort leeren für den nächsten Gedanken
                    messageText = ""
                    checklistItems.clear()
                    selectedAttachment = null
                    executionMode = "concept"
                    if (attachmentSnapshot != null) {
                        onAttachmentSubmitted()
                    }

                    scope.launch {
                        val sentLive = AgDirectInboxManager.submitNewEntry(
                            context = context,
                            rawText = rawSnapshot,
                            checklist = checklistSnapshot,
                            executionMode = modeSnapshot,
                            attachment = attachmentSnapshot
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
                    .fillMaxWidth()
                    .height(46.dp),
                shape = RoundedCornerShape(12.dp),
                colors = ButtonDefaults.buttonColors(
                    containerColor = Gold,
                    contentColor = SpaceBlack,
                    disabledContainerColor = Gold.copy(alpha = 0.35f),
                    disabledContentColor = SpaceBlack.copy(alpha = 0.6f)
                ),
                contentPadding = PaddingValues(horizontal = 16.dp)
            ) {
                Text(
                    text = "An Falcon senden",
                    fontSize = 14.sp,
                    fontWeight = FontWeight.Bold
                )
            }

            HorizontalDivider(color = AppTheme.colors.cardBorder, thickness = 1.dp)

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
                            }
                        )
                    }
                }
            }
        }
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
    onDelete: () -> Unit
) {
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
            color = if (expanded) Gold.copy(alpha = 0.55f) else AppTheme.colors.cardBorder
        ),
        shape = RoundedCornerShape(12.dp)
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = 12.dp, vertical = 10.dp),
            verticalArrangement = Arrangement.spacedBy(8.dp)
        ) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(6.dp),
                    modifier = Modifier.weight(1f)
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
                        Icon(
                            imageVector = Icons.Default.Image,
                            contentDescription = "Bild vorhanden",
                            tint = Gold,
                            modifier = Modifier.size(14.dp)
                        )
                    }
                }

                Spacer(modifier = Modifier.width(8.dp))

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

                    if (!item.agResponseSummary.isNullOrBlank()) {
                        Box(
                            modifier = Modifier
                                .fillMaxWidth()
                                .clip(RoundedCornerShape(10.dp))
                                .background(Gold.copy(alpha = 0.10f))
                                .border(1.dp, Gold.copy(alpha = 0.45f), RoundedCornerShape(10.dp))
                                .padding(10.dp)
                        ) {
                            Text(
                                text = item.agResponseSummary,
                                fontSize = 12.sp,
                                color = AppTheme.colors.textPrimary,
                                lineHeight = 16.sp
                            )
                        }
                    }

                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                            if (item.hasAttachment) {
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
                                        imageVector = Icons.Default.Image,
                                        contentDescription = null,
                                        tint = Gold,
                                        modifier = Modifier.size(14.dp)
                                    )
                                    Text(
                                        text = "Bild",
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
    onDismiss: () -> Unit
) {
    AgImageLightboxDialog(
        target = LightboxTarget.DirectBitmap(bitmap = bitmap, title = title),
        onDismiss = onDismiss
    )
}

@Composable
private fun AgImageLightboxDialog(
    target: LightboxTarget,
    onDismiss: () -> Unit
) {
    var bitmap by remember(target) {
        mutableStateOf(
            when (target) {
                is LightboxTarget.DirectBitmap -> target.bitmap
                is LightboxTarget.InboxItem -> null
            }
        )
    }
    var isLoading by remember(target) {
        mutableStateOf(target is LightboxTarget.InboxItem)
    }
    var loadError by remember(target) { mutableStateOf(false) }

    var scale by remember(target) { mutableFloatStateOf(1f) }
    var offset by remember(target) { mutableStateOf(Offset.Zero) }

    LaunchedEffect(target) {
        if (target is LightboxTarget.InboxItem) {
            isLoading = true
            loadError = false
            val loaded = AgDirectInboxManager.loadAttachmentBitmap(target.item)
            bitmap = loaded
            loadError = loaded == null
            isLoading = false
        }
    }

    val headerTitle = when (target) {
        is LightboxTarget.DirectBitmap -> target.title
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
        }
    }
}

private fun formatAgTimestamp(rawIso: String?): String? {
    if (rawIso.isNullOrBlank()) return null
    return try {
        val parsed = OffsetDateTime.parse(rawIso)
        parsed.format(DateTimeFormatter.ofPattern("dd.MM. HH:mm", Locale.GERMANY))
    } catch (_: Throwable) {
        if (rawIso.length >= 16) rawIso.substring(0, 16).replace('T', ' ') else rawIso
    }
}
