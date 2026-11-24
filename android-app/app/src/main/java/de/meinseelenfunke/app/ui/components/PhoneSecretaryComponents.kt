package de.meinseelenfunke.app.ui.components

import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Check
import androidx.compose.material.icons.filled.ChevronRight
import androidx.compose.material.icons.filled.Edit
import androidx.compose.material.icons.filled.PhoneInTalk
import androidx.compose.material.icons.filled.Refresh
import androidx.compose.material.icons.filled.VerifiedUser
import androidx.compose.material.icons.filled.WarningAmber
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.OutlinedTextFieldDefaults
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import de.meinseelenfunke.app.data.api.CallVerificationReportDto
import de.meinseelenfunke.app.data.api.PhoneSecretaryCallSummaryDto
import de.meinseelenfunke.app.services.CallNoteVerificationEngine
import de.meinseelenfunke.app.ui.theme.AppTheme
import de.meinseelenfunke.app.ui.theme.Emerald500
import de.meinseelenfunke.app.ui.theme.Gold
import de.meinseelenfunke.app.ui.theme.Rose500
import de.meinseelenfunke.app.ui.theme.SpaceBlack
import kotlinx.coroutines.launch

/**
 * Kurzer, knackiger 1-Zeilen-Hinweis auf der Startseite (Zentrum),
 * ausschließlich sichtbar, wenn mindestens ein ungesehener Anrufbeantworter-Eintrag vorliegt.
 */
@Composable
fun NewPhoneEntryHintBanner(
    unseenCount: Int,
    latestCallerLabel: String? = null,
    uncertainCount: Int = 0,
    onClick: () -> Unit
) {
    if (unseenCount <= 0) return

    val label = when {
        uncertainCount > 0 -> "$unseenCount neue Anrufe · $uncertainCount prüfen"
        unseenCount == 1 && !latestCallerLabel.isNullOrBlank() -> "Neuer Anruf · ${latestCallerLabel.trim()}"
        unseenCount == 1 -> "Neuer Anrufbeantworter-Eintrag"
        else -> "$unseenCount neue Anrufbeantworter-Einträge"
    }
    val accent = if (uncertainCount > 0) Rose500 else Gold

    Card(
        modifier = Modifier
            .fillMaxWidth()
            .border(1.dp, accent.copy(alpha = 0.65f), RoundedCornerShape(14.dp))
            .clip(RoundedCornerShape(14.dp))
            .clickable { onClick() },
        colors = CardDefaults.cardColors(containerColor = AppTheme.colors.cardBackground),
        shape = RoundedCornerShape(14.dp)
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = 14.dp, vertical = 11.dp),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically
        ) {
            Row(
                verticalAlignment = Alignment.CenterVertically,
                modifier = Modifier.weight(1f)
            ) {
                Box(
                    modifier = Modifier
                        .size(7.dp)
                        .clip(CircleShape)
                        .background(accent)
                )
                Spacer(modifier = Modifier.width(8.dp))
                Icon(
                    imageVector = Icons.Default.PhoneInTalk,
                    contentDescription = null,
                    tint = accent,
                    modifier = Modifier.size(17.dp)
                )
                Spacer(modifier = Modifier.width(8.dp))
                Text(
                    text = label,
                    fontSize = 13.sp,
                    fontWeight = FontWeight.Bold,
                    color = AppTheme.colors.textPrimary,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis
                )
            }
            Icon(
                imageVector = Icons.Default.ChevronRight,
                contentDescription = null,
                tint = accent,
                modifier = Modifier.size(18.dp)
            )
        }
    }
}

private data class VerificationBadgeMeta(
    val label: String,
    val color: Color,
    val isWarning: Boolean
)

private fun resolveVerificationBadgeMeta(verification: CallVerificationReportDto?): VerificationBadgeMeta? {
    if (verification == null) return null
    return when (verification.status) {
        CallNoteVerificationEngine.STATUS_VERIFIED -> VerificationBadgeMeta("Geprüft", Emerald500, false)
        CallNoteVerificationEngine.STATUS_USER_CONFIRMED -> VerificationBadgeMeta("Bestätigt", Emerald500, false)
        CallNoteVerificationEngine.STATUS_CORRECTED -> VerificationBadgeMeta("Korrigiert", Gold, false)
        CallNoteVerificationEngine.STATUS_UNCERTAIN -> VerificationBadgeMeta("Prüfen", Rose500, true)
        CallNoteVerificationEngine.STATUS_PENDING -> VerificationBadgeMeta("Offen", Color(0xFF94A3B8), false)
        else -> null
    }
}

/**
 * Eigener Organizer-Tab (Telefon-Icon) für alle von Funkira aufgenommenen Gespräche,
 * Rahmenbedingungen, Aufgaben und die KI-Gegenprüfung (Verstandenes vs. Gesagtes).
 */
@Composable
fun PhoneSecretaryTabContent(
    calls: List<PhoneSecretaryCallSummaryDto>,
    onRefresh: () -> Unit,
    onOpenTasks: () -> Unit,
    onVerifyCall: (suspend (PhoneSecretaryCallSummaryDto) -> PhoneSecretaryCallSummaryDto)? = null,
    onConfirmCall: ((String) -> Unit)? = null,
    onUpdateCallNote: ((String, String, String, List<String>) -> Unit)? = null
) {
    var selectedCallId by remember { mutableStateOf<String?>(null) }
    var onlyUncertainFilter by remember { mutableStateOf(false) }

    val selectedCall = remember(selectedCallId, calls) {
        selectedCallId?.let { id -> calls.firstOrNull { it.id == id } }
    }

    selectedCall?.let { detail ->
        PhoneSecretaryCallDetailDialog(
            call = detail,
            onDismiss = { selectedCallId = null },
            onOpenTasks = onOpenTasks,
            onVerifyCall = onVerifyCall,
            onConfirmCall = onConfirmCall,
            onUpdateCallNote = onUpdateCallNote
        )
    }

    val uncertainCount = remember(calls) {
        calls.count { it.verification?.status == CallNoteVerificationEngine.STATUS_UNCERTAIN }
    }
    val visibleCalls = remember(calls, onlyUncertainFilter) {
        if (onlyUncertainFilter && uncertainCount > 0) {
            calls.filter { it.verification?.status == CallNoteVerificationEngine.STATUS_UNCERTAIN }
        } else {
            calls
        }
    }

    Column(
        modifier = Modifier
            .fillMaxSize()
            .verticalScroll(rememberScrollState()),
        verticalArrangement = Arrangement.spacedBy(10.dp)
    ) {
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically
        ) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Icon(
                    imageVector = Icons.Default.PhoneInTalk,
                    contentDescription = null,
                    tint = Gold,
                    modifier = Modifier.size(19.dp)
                )
                Spacer(modifier = Modifier.width(8.dp))
                Text(
                    text = "Anrufbeantworter",
                    fontSize = 15.sp,
                    fontWeight = FontWeight.Bold,
                    color = AppTheme.colors.textPrimary
                )
            }
            IconButton(
                onClick = onRefresh,
                modifier = Modifier.size(32.dp)
            ) {
                Icon(
                    imageVector = Icons.Default.Refresh,
                    contentDescription = "Aktualisieren",
                    tint = Gold,
                    modifier = Modifier.size(18.dp)
                )
            }
        }

        if (uncertainCount > 0) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                listOf(
                    false to "Alle (${calls.size})",
                    true to "Prüfen ($uncertainCount)"
                ).forEach { (filterVal, label) ->
                    val active = onlyUncertainFilter == filterVal
                    val chipColor = if (filterVal) Rose500 else Gold
                    Box(
                        modifier = Modifier
                            .clip(RoundedCornerShape(10.dp))
                            .background(if (active) chipColor.copy(alpha = 0.16f) else AppTheme.colors.glassSurface)
                            .border(
                                1.dp,
                                if (active) chipColor else AppTheme.colors.cardBorder,
                                RoundedCornerShape(10.dp)
                            )
                            .clickable { onlyUncertainFilter = filterVal }
                            .padding(horizontal = 12.dp, vertical = 6.dp)
                    ) {
                        Text(
                            text = label,
                            fontSize = 12.sp,
                            fontWeight = if (active) FontWeight.Bold else FontWeight.Medium,
                            color = if (active) chipColor else AppTheme.colors.textPrimary
                        )
                    }
                }
            }
        }

        if (visibleCalls.isEmpty()) {
            Card(
                modifier = Modifier.fillMaxWidth(),
                colors = CardDefaults.cardColors(containerColor = AppTheme.colors.cardBackground),
                border = BorderStroke(1.dp, AppTheme.colors.cardBorder),
                shape = RoundedCornerShape(16.dp)
            ) {
                Column(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(vertical = 36.dp, horizontal = 16.dp),
                    horizontalAlignment = Alignment.CenterHorizontally,
                    verticalArrangement = Arrangement.spacedBy(8.dp)
                ) {
                    Icon(
                        imageVector = Icons.Default.PhoneInTalk,
                        contentDescription = null,
                        tint = AppTheme.colors.textMuted,
                        modifier = Modifier.size(28.dp)
                    )
                    Text(
                        text = "Keine Einträge",
                        fontSize = 13.sp,
                        fontWeight = FontWeight.Medium,
                        color = AppTheme.colors.textMuted
                    )
                }
            }
        } else {
            visibleCalls.forEach { call ->
                PhoneSecretaryCallRow(
                    call = call,
                    onClick = { selectedCallId = call.id }
                )
            }
        }

        Spacer(modifier = Modifier.height(80.dp))
    }
}

@Composable
fun PhoneSecretaryCallRow(
    call: PhoneSecretaryCallSummaryDto,
    onClick: () -> Unit
) {
    val badge = resolveVerificationBadgeMeta(call.verification)
    val borderColor = if (badge?.isWarning == true) Rose500.copy(alpha = 0.55f) else AppTheme.colors.cardBorder

    Row(
        modifier = Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(12.dp))
            .background(AppTheme.colors.cardBackground)
            .border(1.dp, borderColor, RoundedCornerShape(12.dp))
            .clickable { onClick() }
            .padding(horizontal = 14.dp, vertical = 12.dp),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.SpaceBetween
    ) {
        Column(
            modifier = Modifier.weight(1f),
            verticalArrangement = Arrangement.spacedBy(4.dp)
        ) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    modifier = Modifier.weight(1f)
                ) {
                    if (call.urgency.equals("hoch", ignoreCase = true)) {
                        Box(
                            modifier = Modifier
                                .size(7.dp)
                                .clip(CircleShape)
                                .background(Rose500)
                        )
                        Spacer(modifier = Modifier.width(6.dp))
                    }
                    Text(
                        text = call.caller_name.ifBlank { "Kontakt" },
                        fontSize = 13.sp,
                        fontWeight = FontWeight.Bold,
                        color = AppTheme.colors.textPrimary,
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis
                    )
                }
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(6.dp)
                ) {
                    if (badge != null) {
                        Box(
                            modifier = Modifier
                                .clip(RoundedCornerShape(6.dp))
                                .background(badge.color.copy(alpha = 0.14f))
                                .border(1.dp, badge.color.copy(alpha = 0.45f), RoundedCornerShape(6.dp))
                                .padding(horizontal = 6.dp, vertical = 2.dp)
                        ) {
                            Text(
                                text = badge.label,
                                fontSize = 10.sp,
                                fontWeight = FontWeight.Bold,
                                color = badge.color
                            )
                        }
                    }
                    if (call.created_at_label.isNotBlank()) {
                        Text(
                            text = call.created_at_label,
                            fontSize = 11.sp,
                            color = AppTheme.colors.textMuted
                        )
                    }
                }
            }
            if (call.summary.isNotBlank()) {
                Text(
                    text = call.summary,
                    fontSize = 12.sp,
                    color = AppTheme.colors.textSecondary,
                    maxLines = 2,
                    overflow = TextOverflow.Ellipsis
                )
            }
            if (call.action_required.isNotBlank()) {
                Text(
                    text = "Zu tun: ${call.action_required}",
                    fontSize = 12.sp,
                    fontWeight = FontWeight.SemiBold,
                    color = Gold,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis
                )
            }
        }
        Spacer(modifier = Modifier.width(8.dp))
        Icon(
            imageVector = Icons.Default.ChevronRight,
            contentDescription = null,
            tint = Gold,
            modifier = Modifier.size(18.dp)
        )
    }
}

@Composable
fun PhoneSecretaryCallDetailDialog(
    call: PhoneSecretaryCallSummaryDto,
    onDismiss: () -> Unit,
    onOpenTasks: (() -> Unit)? = null,
    onVerifyCall: (suspend (PhoneSecretaryCallSummaryDto) -> PhoneSecretaryCallSummaryDto)? = null,
    onConfirmCall: ((String) -> Unit)? = null,
    onUpdateCallNote: ((String, String, String, List<String>) -> Unit)? = null
) {
    val scope = rememberCoroutineScope()
    val verification = call.verification
    val badge = resolveVerificationBadgeMeta(verification)
    val shouldAutoExpandVerification = verification?.status == CallNoteVerificationEngine.STATUS_UNCERTAIN ||
        verification?.status == CallNoteVerificationEngine.STATUS_CORRECTED ||
        !verification?.discrepancies.isNullOrEmpty()

    var showTranscript by remember(call.id) { mutableStateOf(false) }
    var showVerificationDetails by remember(call.id, verification?.status) {
        mutableStateOf(shouldAutoExpandVerification)
    }
    var isVerifying by remember(call.id) { mutableStateOf(false) }
    var isEditing by remember(call.id) { mutableStateOf(false) }
    var editSummary by remember(call.id, call.summary) { mutableStateOf(call.summary) }
    var editAction by remember(call.id, call.action_required) { mutableStateOf(call.action_required) }

    val rahmenList = call.rahmenbedingungen.orEmpty().filter { it.isNotBlank() }
    val transcriptList = call.transcript.orEmpty().filter { it.isNotBlank() }

    AlertDialog(
        onDismissRequest = onDismiss,
        containerColor = AppTheme.colors.cardBackground,
        title = {
            Column(verticalArrangement = Arrangement.spacedBy(4.dp)) {
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Text(
                        text = call.caller_name.ifBlank { "Telefonat" },
                        fontSize = 16.sp,
                        fontWeight = FontWeight.Bold,
                        color = Gold,
                        modifier = Modifier.weight(1f)
                    )
                    if (badge != null) {
                        val scoreTxt = if ((verification?.confidence_score ?: 0) > 0) {
                            "${badge.label} · ${verification?.confidence_score}%"
                        } else {
                            badge.label
                        }
                        Box(
                            modifier = Modifier
                                .clip(RoundedCornerShape(8.dp))
                                .background(badge.color.copy(alpha = 0.14f))
                                .border(1.dp, badge.color.copy(alpha = 0.5f), RoundedCornerShape(8.dp))
                                .padding(horizontal = 8.dp, vertical = 3.dp)
                        ) {
                            Text(
                                text = scoreTxt,
                                fontSize = 11.sp,
                                fontWeight = FontWeight.Bold,
                                color = badge.color
                            )
                        }
                    }
                }
                val metaParts = buildList {
                    if (call.created_at_label.isNotBlank()) add(call.created_at_label)
                    if (call.duration_seconds > 0) add("${call.duration_seconds} Sek.")
                    if (call.handled_by == "android_secretary") add("Smartphone")
                }
                if (metaParts.isNotEmpty()) {
                    Text(
                        text = metaParts.joinToString(" · "),
                        fontSize = 12.sp,
                        color = AppTheme.colors.textMuted
                    )
                }
            }
        },
        text = {
            Column(
                modifier = Modifier
                    .fillMaxWidth()
                    .heightIn(max = 460.dp)
                    .verticalScroll(rememberScrollState()),
                verticalArrangement = Arrangement.spacedBy(12.dp)
            ) {
                if (isEditing) {
                    Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                        OutlinedTextField(
                            value = editSummary,
                            onValueChange = { editSummary = it },
                            label = { Text("Besprochen", fontSize = 12.sp) },
                            modifier = Modifier.fillMaxWidth(),
                            colors = OutlinedTextFieldDefaults.colors(
                                focusedBorderColor = Gold,
                                unfocusedBorderColor = AppTheme.colors.cardBorder,
                                focusedTextColor = AppTheme.colors.textPrimary,
                                unfocusedTextColor = AppTheme.colors.textPrimary
                            )
                        )
                        OutlinedTextField(
                            value = editAction,
                            onValueChange = { editAction = it },
                            label = { Text("Für dich zu tun", fontSize = 12.sp) },
                            modifier = Modifier.fillMaxWidth(),
                            colors = OutlinedTextFieldDefaults.colors(
                                focusedBorderColor = Gold,
                                unfocusedBorderColor = AppTheme.colors.cardBorder,
                                focusedTextColor = AppTheme.colors.textPrimary,
                                unfocusedTextColor = AppTheme.colors.textPrimary
                            )
                        )
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.spacedBy(8.dp)
                        ) {
                            OutlinedButton(
                                onClick = { isEditing = false },
                                modifier = Modifier.weight(1f),
                                border = BorderStroke(1.dp, AppTheme.colors.cardBorder),
                                shape = RoundedCornerShape(10.dp)
                            ) {
                                Text("Abbrechen", color = AppTheme.colors.textSecondary, fontSize = 12.sp)
                            }
                            Button(
                                onClick = {
                                    onUpdateCallNote?.invoke(call.id, editSummary, editAction, rahmenList)
                                    isEditing = false
                                },
                                modifier = Modifier.weight(1f),
                                colors = ButtonDefaults.buttonColors(containerColor = Gold, contentColor = SpaceBlack),
                                shape = RoundedCornerShape(10.dp)
                            ) {
                                Text("Speichern", fontWeight = FontWeight.Bold, fontSize = 12.sp)
                            }
                        }
                    }
                } else {
                    Column(verticalArrangement = Arrangement.spacedBy(4.dp)) {
                        Text(
                            text = "Besprochen",
                            fontSize = 12.sp,
                            fontWeight = FontWeight.Bold,
                            color = AppTheme.colors.textSecondary
                        )
                        Text(
                            text = call.summary.ifBlank { "Gespräch abgeschlossen." },
                            fontSize = 13.sp,
                            color = AppTheme.colors.textPrimary
                        )
                    }

                    if (rahmenList.isNotEmpty()) {
                        Column(verticalArrangement = Arrangement.spacedBy(5.dp)) {
                            Text(
                                text = "Rahmenbedingungen",
                                fontSize = 12.sp,
                                fontWeight = FontWeight.Bold,
                                color = AppTheme.colors.textSecondary
                            )
                            rahmenList.forEach { item ->
                                Row(
                                    modifier = Modifier.fillMaxWidth(),
                                    verticalAlignment = Alignment.Top
                                ) {
                                    Text(
                                        text = "✓",
                                        fontSize = 13.sp,
                                        fontWeight = FontWeight.Bold,
                                        color = Gold
                                    )
                                    Spacer(modifier = Modifier.width(7.dp))
                                    Text(
                                        text = item,
                                        fontSize = 13.sp,
                                        color = AppTheme.colors.textPrimary,
                                        modifier = Modifier.weight(1f)
                                    )
                                }
                            }
                        }
                    }

                    if (call.action_required.isNotBlank()) {
                        Box(
                            modifier = Modifier
                                .fillMaxWidth()
                                .clip(RoundedCornerShape(12.dp))
                                .background(Gold.copy(alpha = 0.12f))
                                .border(BorderStroke(1.dp, Gold.copy(alpha = 0.55f)), RoundedCornerShape(12.dp))
                                .padding(12.dp)
                        ) {
                            Column(verticalArrangement = Arrangement.spacedBy(4.dp)) {
                                Text(
                                    text = "Für dich zu tun",
                                    fontSize = 12.sp,
                                    fontWeight = FontWeight.Bold,
                                    color = Gold
                                )
                                Text(
                                    text = call.action_required,
                                    fontSize = 13.sp,
                                    fontWeight = FontWeight.SemiBold,
                                    color = AppTheme.colors.textPrimary
                                )
                            }
                        }
                    }
                }

                // KI-Gegenprüfung: Verstandenes vs. Gesagtes
                if (transcriptList.isNotEmpty() || verification != null) {
                    HorizontalDivider(color = AppTheme.colors.cardBorder)
                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .clip(RoundedCornerShape(8.dp))
                            .clickable { showVerificationDetails = !showVerificationDetails }
                            .padding(vertical = 2.dp),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Icon(
                                imageVector = if (badge?.isWarning == true) Icons.Default.WarningAmber else Icons.Default.VerifiedUser,
                                contentDescription = null,
                                tint = badge?.color ?: Gold,
                                modifier = Modifier.size(16.dp)
                            )
                            Spacer(modifier = Modifier.width(6.dp))
                            Text(
                                text = "Abgleich: Verstandenes vs. Gesagtes",
                                fontSize = 12.sp,
                                fontWeight = FontWeight.Bold,
                                color = badge?.color ?: Gold
                            )
                        }
                        Text(
                            text = if (showVerificationDetails) "Weniger" else "Details",
                            fontSize = 11.sp,
                            fontWeight = FontWeight.SemiBold,
                            color = AppTheme.colors.textMuted
                        )
                    }

                    if (showVerificationDetails) {
                        Column(
                            modifier = Modifier
                                .fillMaxWidth()
                                .clip(RoundedCornerShape(12.dp))
                                .background(AppTheme.colors.glassSurface)
                                .border(1.dp, (badge?.color ?: AppTheme.colors.cardBorder).copy(alpha = 0.4f), RoundedCornerShape(12.dp))
                                .padding(10.dp),
                            verticalArrangement = Arrangement.spacedBy(8.dp)
                        ) {
                            if (verification != null && verification.verification_summary.isNotBlank()) {
                                Text(
                                    text = verification.verification_summary,
                                    fontSize = 12.sp,
                                    color = AppTheme.colors.textPrimary
                                )
                            }

                            val discrepancies = verification?.discrepancies.orEmpty()
                            if (discrepancies.isNotEmpty()) {
                                Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
                                    discrepancies.forEach { diff ->
                                        val diffColor = if (diff.resolved_by_correction) Gold else Rose500
                                        Column(
                                            modifier = Modifier
                                                .fillMaxWidth()
                                                .clip(RoundedCornerShape(8.dp))
                                                .background(diffColor.copy(alpha = 0.08f))
                                                .border(1.dp, diffColor.copy(alpha = 0.35f), RoundedCornerShape(8.dp))
                                                .padding(8.dp),
                                            verticalArrangement = Arrangement.spacedBy(2.dp)
                                        ) {
                                            Text(
                                                text = if (diff.resolved_by_correction) "${diff.category} (korrigiert)" else diff.category,
                                                fontSize = 11.sp,
                                                fontWeight = FontWeight.Bold,
                                                color = diffColor
                                            )
                                            if (diff.understood.isNotBlank()) {
                                                Text(
                                                    text = "Verstanden: ${diff.understood}",
                                                    fontSize = 11.sp,
                                                    color = AppTheme.colors.textSecondary
                                                )
                                            }
                                            if (diff.spoken.isNotBlank()) {
                                                Text(
                                                    text = "Gesagt: ${diff.spoken}",
                                                    fontSize = 11.sp,
                                                    fontWeight = FontWeight.Medium,
                                                    color = AppTheme.colors.textPrimary
                                                )
                                            }
                                        }
                                    }
                                }
                            }

                            val verifiedFacts = verification?.verified_facts.orEmpty()
                            if (verifiedFacts.isNotEmpty()) {
                                Column(verticalArrangement = Arrangement.spacedBy(4.dp)) {
                                    verifiedFacts.forEach { fact ->
                                        Row(verticalAlignment = Alignment.Top) {
                                            Text(
                                                text = if (fact.matched) "✓" else "!",
                                                fontSize = 11.sp,
                                                fontWeight = FontWeight.Bold,
                                                color = if (fact.matched) Emerald500 else Rose500
                                            )
                                            Spacer(modifier = Modifier.width(6.dp))
                                            Column {
                                                Text(
                                                    text = fact.fact,
                                                    fontSize = 11.sp,
                                                    fontWeight = FontWeight.SemiBold,
                                                    color = AppTheme.colors.textPrimary
                                                )
                                                if (fact.evidence_quote.isNotBlank()) {
                                                    Text(
                                                        text = "„${fact.evidence_quote}“",
                                                        fontSize = 11.sp,
                                                        color = AppTheme.colors.textMuted
                                                    )
                                                }
                                            }
                                        }
                                    }
                                }
                            }

                            Row(
                                modifier = Modifier.fillMaxWidth(),
                                horizontalArrangement = Arrangement.spacedBy(6.dp)
                            ) {
                                if (onVerifyCall != null && transcriptList.isNotEmpty()) {
                                    OutlinedButton(
                                        onClick = {
                                            if (!isVerifying) {
                                                isVerifying = true
                                                scope.launch {
                                                    try {
                                                        onVerifyCall(call)
                                                    } finally {
                                                        isVerifying = false
                                                    }
                                                }
                                            }
                                        },
                                        modifier = Modifier.weight(1f),
                                        border = BorderStroke(1.dp, Gold.copy(alpha = 0.55f)),
                                        shape = RoundedCornerShape(9.dp)
                                    ) {
                                        if (isVerifying) {
                                            CircularProgressIndicator(
                                                modifier = Modifier.size(14.dp),
                                                strokeWidth = 2.dp,
                                                color = Gold
                                            )
                                        } else {
                                            Text("KI-Prüfung", fontSize = 11.sp, fontWeight = FontWeight.Bold, color = Gold)
                                        }
                                    }
                                }
                                if (onUpdateCallNote != null && !isEditing) {
                                    OutlinedButton(
                                        onClick = { isEditing = true },
                                        modifier = Modifier.weight(1f),
                                        border = BorderStroke(1.dp, AppTheme.colors.cardBorder),
                                        shape = RoundedCornerShape(9.dp)
                                    ) {
                                        Icon(
                                            imageVector = Icons.Default.Edit,
                                            contentDescription = null,
                                            tint = AppTheme.colors.textPrimary,
                                            modifier = Modifier.size(13.dp)
                                        )
                                        Spacer(modifier = Modifier.width(4.dp))
                                        Text("Bearbeiten", fontSize = 11.sp, color = AppTheme.colors.textPrimary)
                                    }
                                }
                                if (onConfirmCall != null &&
                                    verification?.status != CallNoteVerificationEngine.STATUS_USER_CONFIRMED &&
                                    verification?.status != CallNoteVerificationEngine.STATUS_VERIFIED
                                ) {
                                    Button(
                                        onClick = { onConfirmCall(call.id) },
                                        modifier = Modifier.weight(1f),
                                        colors = ButtonDefaults.buttonColors(containerColor = Emerald500, contentColor = SpaceBlack),
                                        shape = RoundedCornerShape(9.dp)
                                    ) {
                                        Icon(
                                            imageVector = Icons.Default.Check,
                                            contentDescription = null,
                                            modifier = Modifier.size(13.dp)
                                        )
                                        Spacer(modifier = Modifier.width(3.dp))
                                        Text("Bestätigen", fontSize = 11.sp, fontWeight = FontWeight.Bold)
                                    }
                                }
                            }
                        }
                    }
                }

                if (transcriptList.isNotEmpty()) {
                    Text(
                        text = if (showTranscript) "Gesprächsverlauf ausblenden" else "Gesprächsverlauf anzeigen",
                        fontSize = 12.sp,
                        fontWeight = FontWeight.SemiBold,
                        color = Gold,
                        modifier = Modifier
                            .clip(RoundedCornerShape(8.dp))
                            .clickable { showTranscript = !showTranscript }
                            .padding(vertical = 4.dp)
                    )
                    if (showTranscript) {
                        Column(
                            modifier = Modifier
                                .fillMaxWidth()
                                .clip(RoundedCornerShape(10.dp))
                                .background(AppTheme.colors.glassSurface)
                                .border(1.dp, AppTheme.colors.cardBorder, RoundedCornerShape(10.dp))
                                .padding(10.dp),
                            verticalArrangement = Arrangement.spacedBy(6.dp)
                        ) {
                            transcriptList.forEach { line ->
                                val isFunkira = line.startsWith("Funkira:") || line.startsWith("KI:")
                                Text(
                                    text = line,
                                    fontSize = 12.sp,
                                    color = if (isFunkira) Gold else AppTheme.colors.textPrimary
                                )
                            }
                        }
                    }
                }
            }
        },
        dismissButton = if (onOpenTasks != null) {
            {
                TextButton(
                    onClick = {
                        onDismiss()
                        onOpenTasks()
                    }
                ) {
                    Text("Zu Aufgaben", color = AppTheme.colors.textSecondary, fontWeight = FontWeight.SemiBold)
                }
            }
        } else null,
        confirmButton = {
            TextButton(onClick = onDismiss) {
                Text("Schließen", color = Gold, fontWeight = FontWeight.Bold)
            }
        }
    )
}
