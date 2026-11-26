package de.meinseelenfunke.app.ui.components

import androidx.compose.animation.AnimatedVisibility
import androidx.compose.animation.expandVertically
import androidx.compose.animation.fadeIn
import androidx.compose.animation.fadeOut
import androidx.compose.animation.shrinkVertically
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.AutoAwesome
import androidx.compose.material.icons.filled.Check
import androidx.compose.material.icons.filled.ExpandLess
import androidx.compose.material.icons.filled.ExpandMore
import androidx.compose.material.icons.filled.ThumbDown
import androidx.compose.material.icons.filled.ThumbUp
import androidx.compose.material.icons.outlined.ThumbDown
import androidx.compose.material.icons.outlined.ThumbUp
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import de.meinseelenfunke.app.data.api.BehaviorProposalDto
import de.meinseelenfunke.app.data.api.BehaviorQuickChipDto
import de.meinseelenfunke.app.ui.screens.MessageReactionState
import de.meinseelenfunke.app.ui.theme.AppTheme
import de.meinseelenfunke.app.ui.theme.Emerald500
import de.meinseelenfunke.app.ui.theme.Gold
import de.meinseelenfunke.app.ui.theme.Rose500
import de.meinseelenfunke.app.ui.theme.SpaceBlack

/**
 * Dezente Clean-Design Daumen-hoch / Daumen-runter Reaktions-Icons fuer Funkiras Antworten.
 */
@Composable
fun BehaviorThumbActions(
    reaction: MessageReactionState?,
    mutedTint: Color = AppTheme.colors.textMuted,
    onPraise: () -> Unit,
    onCriticismToggle: () -> Unit,
    modifier: Modifier = Modifier
) {
    val isPraise = reaction?.polarity == "praise"
    val isNegative = reaction?.polarity == "criticism" || reaction?.polarity == "correction"

    Row(
        modifier = modifier,
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(6.dp)
    ) {
        Box(
            modifier = Modifier
                .size(24.dp)
                .clip(CircleShape)
                .background(if (isPraise) Emerald500.copy(alpha = 0.14f) else Color.Transparent)
                .clickable(onClick = onPraise),
            contentAlignment = Alignment.Center
        ) {
            Icon(
                imageVector = if (isPraise) Icons.Filled.ThumbUp else Icons.Outlined.ThumbUp,
                contentDescription = "Lob",
                tint = if (isPraise) Emerald500 else mutedTint.copy(alpha = 0.75f),
                modifier = Modifier.size(13.dp)
            )
        }

        Box(
            modifier = Modifier
                .size(24.dp)
                .clip(CircleShape)
                .background(if (isNegative) Rose500.copy(alpha = 0.14f) else Color.Transparent)
                .clickable(onClick = onCriticismToggle),
            contentAlignment = Alignment.Center
        ) {
            Icon(
                imageVector = if (isNegative) Icons.Filled.ThumbDown else Icons.Outlined.ThumbDown,
                contentDescription = "Kritik",
                tint = if (isNegative) Rose500 else mutedTint.copy(alpha = 0.75f),
                modifier = Modifier.size(13.dp)
            )
        }
    }
}

/**
 * Kompakte 4x 1-Tap-Schnell-Chips nach Tippen auf Daumen-runter.
 */
@Composable
fun BehaviorQuickReasonChipsRow(
    visible: Boolean,
    chips: List<BehaviorQuickChipDto>,
    selectedReasonCode: String?,
    darkHudSurface: Boolean = false,
    onSelectChip: (BehaviorQuickChipDto) -> Unit,
    modifier: Modifier = Modifier
) {
    AnimatedVisibility(
        visible = visible,
        enter = fadeIn() + expandVertically(),
        exit = fadeOut() + shrinkVertically(),
        modifier = modifier
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(top = 6.dp)
                .horizontalScroll(rememberScrollState()),
            horizontalArrangement = Arrangement.spacedBy(6.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            chips.forEach { chip ->
                val isSelected = selectedReasonCode == chip.code
                val chipBg = when {
                    isSelected -> Gold.copy(alpha = 0.20f)
                    darkHudSurface -> Color(0xFF151822)
                    else -> AppTheme.colors.glassSurface
                }
                val chipBorder = if (isSelected) Gold else AppTheme.colors.cardBorder
                val chipText = when {
                    isSelected -> Gold
                    darkHudSurface -> Color(0xFFE5E7EB)
                    else -> AppTheme.colors.textPrimary
                }
                val label = chip.short_label?.takeIf { it.isNotBlank() } ?: chip.label

                Row(
                    modifier = Modifier
                        .clip(RoundedCornerShape(50))
                        .background(chipBg)
                        .border(1.dp, chipBorder, RoundedCornerShape(50))
                        .clickable { onSelectChip(chip) }
                        .padding(horizontal = 10.dp, vertical = 5.dp),
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(4.dp)
                ) {
                    if (isSelected) {
                        Icon(
                            imageVector = Icons.Default.Check,
                            contentDescription = null,
                            tint = Gold,
                            modifier = Modifier.size(11.dp)
                        )
                    }
                    Text(
                        text = label,
                        fontSize = 11.sp,
                        fontWeight = if (isSelected) FontWeight.Bold else FontWeight.Medium,
                        color = chipText,
                        maxLines = 1,
                        softWrap = false
                    )
                }
            }
        }
    }
}

/**
 * Schlanke, einklappbare 1-Klick-Freigabe-Leiste fuer offene Verhaltensregel-Vorschlaege.
 */
@Composable
fun FunkiraBehaviorProposalBar(
    proposals: List<BehaviorProposalDto>,
    onApprove: (BehaviorProposalDto) -> Unit,
    onDismiss: (BehaviorProposalDto) -> Unit,
    modifier: Modifier = Modifier
) {
    if (proposals.isEmpty()) return

    var expanded by remember { mutableStateOf(false) }

    Column(
        modifier = modifier
            .fillMaxWidth()
            .padding(horizontal = 16.dp, vertical = 4.dp)
            .clip(RoundedCornerShape(12.dp))
            .background(AppTheme.colors.cardBackground)
            .border(1.dp, Gold.copy(alpha = 0.55f), RoundedCornerShape(12.dp))
            .padding(horizontal = 12.dp, vertical = 8.dp),
        verticalArrangement = Arrangement.spacedBy(8.dp)
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .clickable { expanded = !expanded },
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.SpaceBetween
        ) {
            Row(
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(6.dp)
            ) {
                Icon(
                    imageVector = Icons.Default.AutoAwesome,
                    contentDescription = null,
                    tint = Gold,
                    modifier = Modifier.size(14.dp)
                )
                Text(
                    text = if (proposals.size == 1) "1 Lern-Regel offen" else "${proposals.size} Lern-Regeln offen",
                    fontSize = 11.5.sp,
                    fontWeight = FontWeight.Bold,
                    color = Gold
                )
            }
            Icon(
                imageVector = if (expanded) Icons.Default.ExpandLess else Icons.Default.ExpandMore,
                contentDescription = if (expanded) "Schließen" else "Öffnen",
                tint = Gold,
                modifier = Modifier.size(18.dp)
            )
        }

        AnimatedVisibility(visible = expanded) {
            Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                proposals.take(3).forEach { proposal ->
                    Card(
                        modifier = Modifier.fillMaxWidth(),
                        colors = CardDefaults.cardColors(containerColor = AppTheme.colors.glassSurface),
                        border = BorderStroke(1.dp, Gold.copy(alpha = 0.35f)),
                        shape = RoundedCornerShape(10.dp)
                    ) {
                        Column(
                            modifier = Modifier
                                .fillMaxWidth()
                                .padding(10.dp),
                            verticalArrangement = Arrangement.spacedBy(6.dp)
                        ) {
                            Text(
                                text = proposal.suggested_title?.takeIf { it.isNotBlank() }
                                    ?: "${proposal.channel_label ?: proposal.channel}: ${proposal.category_label ?: proposal.category}",
                                fontSize = 11.sp,
                                fontWeight = FontWeight.Bold,
                                color = Gold,
                                maxLines = 1,
                                overflow = TextOverflow.Ellipsis
                            )
                            Text(
                                text = proposal.suggested_directive,
                                fontSize = 12.sp,
                                lineHeight = 16.sp,
                                color = AppTheme.colors.textPrimary,
                                maxLines = 3,
                                overflow = TextOverflow.Ellipsis
                            )
                            Row(
                                modifier = Modifier.fillMaxWidth(),
                                horizontalArrangement = Arrangement.spacedBy(8.dp)
                            ) {
                                Button(
                                    onClick = { onApprove(proposal) },
                                    modifier = Modifier
                                        .weight(1f)
                                        .height(32.dp),
                                    shape = RoundedCornerShape(8.dp),
                                    colors = ButtonDefaults.buttonColors(
                                        containerColor = Gold,
                                        contentColor = SpaceBlack
                                    ),
                                    contentPadding = PaddingValues(horizontal = 10.dp, vertical = 2.dp)
                                ) {
                                    Text(
                                        text = "Freigeben",
                                        fontSize = 11.5.sp,
                                        fontWeight = FontWeight.Bold,
                                        color = SpaceBlack
                                    )
                                }
                                Button(
                                    onClick = { onDismiss(proposal) },
                                    modifier = Modifier
                                        .weight(0.8f)
                                        .height(32.dp),
                                    shape = RoundedCornerShape(8.dp),
                                    colors = ButtonDefaults.buttonColors(
                                        containerColor = AppTheme.colors.cardBackground,
                                        contentColor = AppTheme.colors.textMuted
                                    ),
                                    border = BorderStroke(1.dp, AppTheme.colors.cardBorder),
                                    contentPadding = PaddingValues(horizontal = 10.dp, vertical = 2.dp)
                                ) {
                                    Text(
                                        text = "Verwerfen",
                                        fontSize = 11.5.sp,
                                        fontWeight = FontWeight.Medium,
                                        color = AppTheme.colors.textMuted
                                    )
                                }
                            }
                        }
                    }
                }
                Spacer(modifier = Modifier.height(2.dp))
            }
        }
    }
}
