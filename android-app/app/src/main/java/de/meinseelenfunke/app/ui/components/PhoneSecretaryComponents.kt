package de.meinseelenfunke.app.ui.components

import android.content.Intent
import android.net.Uri
import android.widget.Toast
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.horizontalScroll
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
import androidx.compose.material.icons.automirrored.filled.Send
import androidx.compose.material.icons.filled.Call
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
import androidx.compose.material3.Checkbox
import androidx.compose.material3.CheckboxDefaults
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
import androidx.compose.ui.platform.LocalContext
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

private fun isCustomerServiceCall(call: PhoneSecretaryCallSummaryDto): Boolean {
    return call.call_mode == "inbound_customer_service" ||
        call.handled_by.contains("customer_service", ignoreCase = true) ||
        !call.support_ticket_number.isNullOrBlank()
}

/**
 * Eigener Organizer-Tab (Telefon-Icon) für alle von Funkira aufgenommenen Gespräche,
 * Kunden-Tickets, Rahmenbedingungen, Aufgaben und die KI-Gegenprüfung.
 */
@Composable
fun PhoneSecretaryTabContent(
    calls: List<PhoneSecretaryCallSummaryDto>,
    onRefresh: () -> Unit,
    onOpenTasks: () -> Unit,
    onVerifyCall: (suspend (PhoneSecretaryCallSummaryDto) -> PhoneSecretaryCallSummaryDto)? = null,
    onConfirmCall: ((String) -> Unit)? = null,
    onUpdateCallNote: ((String, String, String, List<String>) -> Unit)? = null,
    onReplyCall: (suspend (String, String, Boolean) -> Result<String>)? = null,
    onResolveCall: (suspend (String) -> Result<String>)? = null
) {
    var selectedCallId by remember { mutableStateOf<String?>(null) }
    var activeFilter by remember { mutableStateOf("all") } // all, customer, private, uncertain

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
            onUpdateCallNote = onUpdateCallNote,
            onReplyCall = onReplyCall,
            onResolveCall = onResolveCall
        )
    }

    val customerCount = remember(calls) { calls.count { isCustomerServiceCall(it) } }
    val privateCount = remember(calls) { calls.count { !isCustomerServiceCall(it) } }
    val uncertainCount = remember(calls) {
        calls.count { it.verification?.status == CallNoteVerificationEngine.STATUS_UNCERTAIN }
    }
    val visibleCalls = remember(calls, activeFilter) {
        when (activeFilter) {
            "customer" -> calls.filter { isCustomerServiceCall(it) }
            "private" -> calls.filter { !isCustomerServiceCall(it) }
            "uncertain" -> calls.filter { it.verification?.status == CallNoteVerificationEngine.STATUS_UNCERTAIN }
            else -> calls
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

        if (calls.isNotEmpty()) {
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .horizontalScroll(rememberScrollState()),
                horizontalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                val filterChips = buildList {
                    add("all" to "Alle (${calls.size})")
                    add("customer" to "Kunden ($customerCount)")
                    add("private" to "Privat ($privateCount)")
                    if (uncertainCount > 0) {
                        add("uncertain" to "Prüfen ($uncertainCount)")
                    }
                }
                filterChips.forEach { (key, label) ->
                    val active = activeFilter == key
                    val chipColor = if (key == "uncertain") Rose500 else Gold
                    Box(
                        modifier = Modifier
                            .clip(RoundedCornerShape(10.dp))
                            .background(if (active) chipColor.copy(alpha = 0.16f) else AppTheme.colors.glassSurface)
                            .border(
                                1.dp,
                                if (active) chipColor else AppTheme.colors.cardBorder,
                                RoundedCornerShape(10.dp)
                            )
                            .clickable { activeFilter = key }
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
    val isCustomer = isCustomerServiceCall(call)
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
                    if (call.urgency.equals("hoch", ignoreCase = true) && !call.resolved) {
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
                    if (call.resolved) {
                        Text(
                            text = "✓ Erledigt",
                            fontSize = 10.sp,
                            fontWeight = FontWeight.Bold,
                            color = Emerald500
                        )
                    } else if (badge != null) {
                        Text(
                            text = badge.label,
                            fontSize = 10.sp,
                            fontWeight = FontWeight.Bold,
                            color = badge.color
                        )
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
            val metaLine = buildList {
                add(if (isCustomer) "Kundenservice" else "Privat")
                call.support_ticket_number?.takeIf { it.isNotBlank() }?.let { add(it) }
                    ?: call.ticket_number?.takeIf { it.isNotBlank() }?.let { add(it) }
                call.order_number?.takeIf { it.isNotBlank() }?.let { add("Best. $it") }
            }.joinToString(" · ")
            if (metaLine.isNotBlank()) {
                Text(
                    text = metaLine,
                    fontSize = 11.sp,
                    fontWeight = FontWeight.SemiBold,
                    color = if (isCustomer) Gold else AppTheme.colors.textMuted,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis
                )
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
            if (call.action_required.isNotBlank() && !call.resolved) {
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
    onUpdateCallNote: ((String, String, String, List<String>) -> Unit)? = null,
    onReplyCall: (suspend (String, String, Boolean) -> Result<String>)? = null,
    onResolveCall: (suspend (String) -> Result<String>)? = null
) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    val verification = call.verification
    val badge = resolveVerificationBadgeMeta(verification)
    val isCustomer = isCustomerServiceCall(call)
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
    var showReplyComposer by remember(call.id) { mutableStateOf(false) }
    var replyDraft by remember(call.id, call.suggested_reply) { mutableStateOf(call.suggested_reply.orEmpty()) }
    var replySendEmail by remember(call.id, call.caller_email) { mutableStateOf(!call.caller_email.isNullOrBlank()) }
    var isSendingReply by remember(call.id) { mutableStateOf(false) }
    var isResolving by remember(call.id) { mutableStateOf(false) }
    var actionMessage by remember(call.id) { mutableStateOf<String?>(null) }

    val rahmenList = call.rahmenbedingungen.orEmpty().filter { it.isNotBlank() }
    val transcriptList = call.transcript.orEmpty().filter { it.isNotBlank() }
    val dialablePhone = call.caller_phone?.trim()?.takeIf {
        it.isNotBlank() && !it.equals("Unterdrückt", ignoreCase = true) && it.any { ch -> ch.isDigit() }
    }

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
                    if (call.resolved) {
                        Text(
                            text = "✓ Erledigt",
                            fontSize = 11.sp,
                            fontWeight = FontWeight.Bold,
                            color = Emerald500
                        )
                    } else if (badge != null) {
                        val scoreTxt = if ((verification?.confidence_score ?: 0) > 0) {
                            "${badge.label} · ${verification?.confidence_score}%"
                        } else {
                            badge.label
                        }
                        Text(
                            text = scoreTxt,
                            fontSize = 11.sp,
                            fontWeight = FontWeight.Bold,
                            color = badge.color
                        )
                    }
                }
                val metaParts = buildList {
                    add(if (isCustomer) "Kundenservice" else "Privat")
                    if (call.created_at_label.isNotBlank()) add(call.created_at_label)
                    if (call.duration_seconds > 0) add("${call.duration_seconds} Sek.")
                    if (call.handled_by == "android_secretary" || call.handled_by == "android_customer_service") add("Smartphone")
                }
                if (metaParts.isNotEmpty()) {
                    Text(
                        text = metaParts.joinToString(" · "),
                        fontSize = 12.sp,
                        color = AppTheme.colors.textMuted
                    )
                }
                val refParts = buildList {
                    call.support_ticket_number?.takeIf { it.isNotBlank() }?.let { add("Ticket: $it") }
                    call.ticket_number?.takeIf { it.isNotBlank() }?.let { add("Vorgang: $it") }
                    call.order_number?.takeIf { it.isNotBlank() }?.let { add("Best.: $it") }
                }
                if (refParts.isNotEmpty()) {
                    Text(
                        text = refParts.joinToString(" · "),
                        fontSize = 11.sp,
                        fontWeight = FontWeight.SemiBold,
                        color = Gold
                    )
                }
                if (!call.caller_phone.isNullOrBlank() || !call.caller_email.isNullOrBlank()) {
                    val contactInfo = listOfNotNull(
                        call.caller_phone?.takeIf { it.isNotBlank() },
                        call.caller_email?.takeIf { it.isNotBlank() }
                    ).joinToString(" · ")
                    Text(
                        text = contactInfo,
                        fontSize = 11.sp,
                        color = AppTheme.colors.textSecondary
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

                    // 1-Tap Schnellaktionen: Zurückrufen, Antworten, Erledigt
                    if (dialablePhone != null || onReplyCall != null || (onResolveCall != null && !call.resolved)) {
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.spacedBy(6.dp)
                        ) {
                            if (dialablePhone != null) {
                                OutlinedButton(
                                    onClick = {
                                        try {
                                            val intent = Intent(Intent.ACTION_DIAL, Uri.fromParts("tel", dialablePhone, null)).apply {
                                                addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
                                            }
                                            context.startActivity(intent)
                                        } catch (_: Exception) {
                                            Toast.makeText(context, "Nummer: $dialablePhone", Toast.LENGTH_SHORT).show()
                                        }
                                    },
                                    modifier = Modifier.weight(1f),
                                    border = BorderStroke(1.dp, Gold.copy(alpha = 0.6f)),
                                    shape = RoundedCornerShape(10.dp)
                                ) {
                                    Icon(Icons.Default.Call, contentDescription = null, tint = Gold, modifier = Modifier.size(14.dp))
                                    Spacer(modifier = Modifier.width(4.dp))
                                    Text("Anrufen", fontSize = 11.sp, fontWeight = FontWeight.Bold, color = Gold)
                                }
                            }
                            if (onReplyCall != null) {
                                OutlinedButton(
                                    onClick = { showReplyComposer = !showReplyComposer },
                                    modifier = Modifier.weight(1f),
                                    border = BorderStroke(1.dp, Gold.copy(alpha = 0.6f)),
                                    shape = RoundedCornerShape(10.dp)
                                ) {
                                    Icon(Icons.AutoMirrored.Filled.Send, contentDescription = null, tint = Gold, modifier = Modifier.size(14.dp))
                                    Spacer(modifier = Modifier.width(4.dp))
                                    Text("Antwort", fontSize = 11.sp, fontWeight = FontWeight.Bold, color = Gold)
                                }
                            }
                            if (onResolveCall != null && !call.resolved) {
                                Button(
                                    onClick = {
                                        if (!isResolving) {
                                            isResolving = true
                                            scope.launch {
                                                val res = onResolveCall(call.id)
                                                isResolving = false
                                                actionMessage = res.getOrElse { it.message ?: "Fehler" }
                                            }
                                        }
                                    },
                                    modifier = Modifier.weight(1f),
                                    colors = ButtonDefaults.buttonColors(containerColor = Emerald500, contentColor = SpaceBlack),
                                    shape = RoundedCornerShape(10.dp)
                                ) {
                                    if (isResolving) {
                                        CircularProgressIndicator(modifier = Modifier.size(13.dp), strokeWidth = 2.dp, color = SpaceBlack)
                                    } else {
                                        Icon(Icons.Default.Check, contentDescription = null, modifier = Modifier.size(14.dp))
                                        Spacer(modifier = Modifier.width(3.dp))
                                        Text("Erledigt", fontSize = 11.sp, fontWeight = FontWeight.Bold)
                                    }
                                }
                            }
                        }
                    }

                    if (!call.last_reply.isNullOrBlank()) {
                        Text(
                            text = "Letzte Antwort: ${call.last_reply}",
                            fontSize = 11.sp,
                            color = Emerald500,
                            fontWeight = FontWeight.Medium
                        )
                    }

                    if (!actionMessage.isNullOrBlank()) {
                        Text(
                            text = actionMessage!!,
                            fontSize = 11.sp,
                            fontWeight = FontWeight.SemiBold,
                            color = Emerald500
                        )
                    }

                    if (showReplyComposer && onReplyCall != null) {
                        Column(
                            modifier = Modifier
                                .fillMaxWidth()
                                .clip(RoundedCornerShape(12.dp))
                                .background(AppTheme.colors.glassSurface)
                                .border(1.dp, Gold.copy(alpha = 0.45f), RoundedCornerShape(12.dp))
                                .padding(10.dp),
                            verticalArrangement = Arrangement.spacedBy(8.dp)
                        ) {
                            OutlinedTextField(
                                value = replyDraft,
                                onValueChange = { replyDraft = it },
                                label = { Text("Antwort an Kunden / Ticket", fontSize = 11.sp) },
                                modifier = Modifier.fillMaxWidth(),
                                minLines = 2,
                                maxLines = 5,
                                colors = OutlinedTextFieldDefaults.colors(
                                    focusedBorderColor = Gold,
                                    unfocusedBorderColor = AppTheme.colors.cardBorder,
                                    focusedTextColor = AppTheme.colors.textPrimary,
                                    unfocusedTextColor = AppTheme.colors.textPrimary
                                )
                            )
                            if (!call.caller_email.isNullOrBlank()) {
                                Row(
                                    verticalAlignment = Alignment.CenterVertically,
                                    modifier = Modifier.clickable { replySendEmail = !replySendEmail }
                                ) {
                                    Checkbox(
                                        checked = replySendEmail,
                                        onCheckedChange = { replySendEmail = it },
                                        colors = CheckboxDefaults.colors(checkedColor = Gold, checkmarkColor = SpaceBlack)
                                    )
                                    Text(
                                        text = "Auch per E-Mail (${call.caller_email})",
                                        fontSize = 11.sp,
                                        color = AppTheme.colors.textSecondary
                                    )
                                }
                            }
                            Button(
                                onClick = {
                                    if (!isSendingReply && replyDraft.isNotBlank()) {
                                        isSendingReply = true
                                        scope.launch {
                                            val res = onReplyCall(call.id, replyDraft, replySendEmail)
                                            isSendingReply = false
                                            actionMessage = res.getOrElse { it.message ?: "Senden fehlgeschlagen" }
                                            if (res.isSuccess) {
                                                showReplyComposer = false
                                            }
                                        }
                                    }
                                },
                                enabled = !isSendingReply && replyDraft.isNotBlank(),
                                modifier = Modifier.fillMaxWidth(),
                                colors = ButtonDefaults.buttonColors(containerColor = Gold, contentColor = SpaceBlack),
                                shape = RoundedCornerShape(10.dp)
                            ) {
                                if (isSendingReply) {
                                    CircularProgressIndicator(modifier = Modifier.size(14.dp), strokeWidth = 2.dp, color = SpaceBlack)
                                } else {
                                    Text("Antwort senden", fontWeight = FontWeight.Bold, fontSize = 12.sp)
                                }
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
