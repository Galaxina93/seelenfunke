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
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.ChevronRight
import androidx.compose.material.icons.filled.PhoneInTalk
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import de.meinseelenfunke.app.data.api.PhoneSecretaryCallSummaryDto
import de.meinseelenfunke.app.ui.theme.AppTheme
import de.meinseelenfunke.app.ui.theme.Gold

@Composable
fun PhoneSecretarySummaryCard(
    calls: List<PhoneSecretaryCallSummaryDto>,
    onSelectCall: (PhoneSecretaryCallSummaryDto) -> Unit,
    onOpenTasks: (() -> Unit)? = null
) {
    if (calls.isEmpty()) return

    Card(
        modifier = Modifier
            .fillMaxWidth()
            .border(1.dp, Gold.copy(alpha = 0.55f), RoundedCornerShape(16.dp)),
        colors = CardDefaults.cardColors(containerColor = AppTheme.colors.cardBackground),
        shape = RoundedCornerShape(16.dp)
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(14.dp),
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
                        text = "Telefon-Sekretärin",
                        fontSize = 14.sp,
                        fontWeight = FontWeight.Bold,
                        color = AppTheme.colors.textPrimary
                    )
                }
                if (onOpenTasks != null) {
                    Text(
                        text = "Aufgaben",
                        fontSize = 12.sp,
                        fontWeight = FontWeight.SemiBold,
                        color = Gold,
                        modifier = Modifier
                            .clip(RoundedCornerShape(8.dp))
                            .clickable { onOpenTasks() }
                            .padding(horizontal = 6.dp, vertical = 2.dp)
                    )
                }
            }

            calls.take(3).forEachIndexed { index, call ->
                if (index > 0) {
                    HorizontalDivider(color = AppTheme.colors.cardBorder)
                }
                PhoneSecretaryCallRow(
                    call = call,
                    onClick = { onSelectCall(call) }
                )
            }
        }
    }
}

@Composable
fun PhoneSecretaryCallRow(
    call: PhoneSecretaryCallSummaryDto,
    onClick: () -> Unit
) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(10.dp))
            .background(AppTheme.colors.glassSurface)
            .border(1.dp, AppTheme.colors.cardBorder, RoundedCornerShape(10.dp))
            .clickable { onClick() }
            .padding(horizontal = 12.dp, vertical = 10.dp),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.SpaceBetween
    ) {
        Column(
            modifier = Modifier.weight(1f),
            verticalArrangement = Arrangement.spacedBy(3.dp)
        ) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Text(
                    text = call.caller_name.ifBlank { "Kontakt" },
                    fontSize = 13.sp,
                    fontWeight = FontWeight.Bold,
                    color = AppTheme.colors.textPrimary,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis,
                    modifier = Modifier.weight(1f)
                )
                if (call.created_at_label.isNotBlank()) {
                    Spacer(modifier = Modifier.width(6.dp))
                    Text(
                        text = call.created_at_label,
                        fontSize = 11.sp,
                        color = AppTheme.colors.textMuted
                    )
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
        Spacer(modifier = Modifier.width(6.dp))
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
    onOpenTasks: (() -> Unit)? = null
) {
    var showTranscript by remember(call.id) { mutableStateOf(false) }
    val rahmenList = call.rahmenbedingungen.orEmpty().filter { it.isNotBlank() }
    val transcriptList = call.transcript.orEmpty().filter { it.isNotBlank() }

    AlertDialog(
        onDismissRequest = onDismiss,
        containerColor = AppTheme.colors.cardBackground,
        title = {
            Column(verticalArrangement = Arrangement.spacedBy(2.dp)) {
                Text(
                    text = call.caller_name.ifBlank { "Telefonat" },
                    fontSize = 16.sp,
                    fontWeight = FontWeight.Bold,
                    color = Gold
                )
                val metaParts = buildList {
                    if (call.created_at_label.isNotBlank()) add(call.created_at_label)
                    if (call.duration_seconds > 0) add("${call.duration_seconds} Sek.")
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
                    .heightIn(max = 430.dp)
                    .verticalScroll(rememberScrollState()),
                verticalArrangement = Arrangement.spacedBy(12.dp)
            ) {
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
