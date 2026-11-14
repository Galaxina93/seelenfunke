package de.meinseelenfunke.app.ui.screens

import android.widget.Toast
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.lazy.itemsIndexed
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.geometry.CornerRadius
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.StrokeJoin
import androidx.compose.ui.graphics.drawscope.Fill
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import de.meinseelenfunke.app.R
import de.meinseelenfunke.app.ui.theme.AppTheme
import de.meinseelenfunke.app.ui.theme.Gold
import de.meinseelenfunke.app.util.MusicPlayerManager

@Composable
fun MusicTabContent() {
    val context = LocalContext.current
    val playlists by MusicPlayerManager.playlists.collectAsState()
    val allTracks by MusicPlayerManager.allTracks.collectAsState()
    val activePlaylistId by MusicPlayerManager.activePlaylistId.collectAsState()
    val currentTrack by MusicPlayerManager.currentTrack.collectAsState()
    val isPlaying by MusicPlayerManager.isPlaying.collectAsState()
    val isShuffle by MusicPlayerManager.isShuffle.collectAsState()
    val isRepeat by MusicPlayerManager.isRepeat.collectAsState()
    val currentPosSec by MusicPlayerManager.currentPositionSec.collectAsState()
    val durationSec by MusicPlayerManager.durationSec.collectAsState()
    val statusText by MusicPlayerManager.statusText.collectAsState()
    val importProgress by MusicPlayerManager.importProgress.collectAsState()

    var searchQuery by remember { mutableStateOf("") }
    var importOrPlaylistInput by remember { mutableStateOf("") }
    var isImporting by remember { mutableStateOf(false) }

    LaunchedEffect(Unit) {
        MusicPlayerManager.ensureCatalogLoadedFast(context)
        MusicPlayerManager.fetchPlaylists(context)
    }

    val filteredTracks = remember(allTracks, activePlaylistId, searchQuery) {
        MusicPlayerManager.getFilteredTracks(searchQuery)
    }

    Column(
        modifier = Modifier
            .fillMaxSize()
            .padding(bottom = 8.dp)
    ) {
        // 1. STUDIO NOW PLAYING DECK CARD
        Card(
            modifier = Modifier
                .fillMaxWidth()
                .padding(bottom = 12.dp),
            shape = RoundedCornerShape(18.dp),
            colors = CardDefaults.cardColors(containerColor = AppTheme.colors.cardBackground),
            border = BorderStroke(1.dp, Gold.copy(alpha = 0.45f))
        ) {
            Column(modifier = Modifier.padding(14.dp)) {
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    modifier = Modifier.fillMaxWidth()
                ) {
                    Box(
                        modifier = Modifier
                            .size(56.dp)
                            .clip(RoundedCornerShape(14.dp))
                            .background(Color(0xFF1A1E29))
                            .border(1.dp, Gold.copy(alpha = 0.5f), RoundedCornerShape(14.dp)),
                        contentAlignment = Alignment.Center
                    ) {
                        Image(
                            painter = painterResource(id = R.drawable.ic_launcher_foreground),
                            contentDescription = "Funkenwerk",
                            modifier = Modifier.size(48.dp)
                        )
                    }

                    Spacer(modifier = Modifier.width(12.dp))

                    Column(modifier = Modifier.weight(1f)) {
                        Text(
                            text = currentTrack?.title ?: "Kein Song ausgewählt",
                            color = AppTheme.colors.textPrimary,
                            fontSize = 15.sp,
                            fontWeight = FontWeight.Bold,
                            maxLines = 1,
                            overflow = TextOverflow.Ellipsis
                        )
                        Text(
                            text = currentTrack?.artist?.ifBlank { "Funkenwerk Audio" } ?: "Wähle unten einen Titel",
                            color = Gold,
                            fontSize = 12.5.sp,
                            fontWeight = FontWeight.SemiBold,
                            maxLines = 1,
                            overflow = TextOverflow.Ellipsis
                        )
                        Text(
                            text = statusText,
                            color = Color(0xFF34D399),
                            fontSize = 10.5.sp,
                            maxLines = 1,
                            overflow = TextOverflow.Ellipsis,
                            modifier = Modifier.padding(top = 2.dp)
                        )
                    }

                    if (currentTrack != null) {
                        Text(
                            text = if (currentTrack?.isFavorite == true) "♥" else "♡",
                            color = if (currentTrack?.isFavorite == true) Gold else AppTheme.colors.textMuted,
                            fontSize = 18.sp,
                            modifier = Modifier
                                .clickable { currentTrack?.let { MusicPlayerManager.toggleFavorite(context, it) } }
                                .padding(horizontal = 6.dp, vertical = 4.dp)
                        )
                    }

                    Text(
                        text = "⚡ Offline",
                        color = Gold,
                        fontSize = 12.sp,
                        fontWeight = FontWeight.Bold,
                        modifier = Modifier
                            .clickable { MusicPlayerManager.cacheAllTracksOffline(context) }
                            .padding(horizontal = 6.dp, vertical = 4.dp)
                    )
                }

                // Scrubber
                val maxDur = durationSec.coerceAtLeast(1).toFloat()
                val curVal = currentPosSec.coerceIn(0, durationSec.coerceAtLeast(1)).toFloat()
                Slider(
                    value = curVal,
                    onValueChange = { MusicPlayerManager.seekToSeconds(it.toInt()) },
                    valueRange = 0f..maxDur,
                    colors = SliderDefaults.colors(
                        thumbColor = Gold,
                        activeTrackColor = Gold,
                        inactiveTrackColor = Color(0xFF2B3140)
                    ),
                    modifier = Modifier
                        .fillMaxWidth()
                        .height(28.dp)
                        .padding(top = 6.dp)
                )

                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween
                ) {
                    Text(formatSec(currentPosSec), color = AppTheme.colors.textMuted, fontSize = 11.sp)
                    Text(formatSec(durationSec), color = AppTheme.colors.textMuted, fontSize = 11.sp)
                }

                // Playback Controls Row (Unified Modern Gold Vector Icons)
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(top = 6.dp),
                    horizontalArrangement = Arrangement.SpaceEvenly,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    // Shuffle Button (Zufällige Wiedergabe) in Gold
                    Box(
                        modifier = Modifier
                            .size(38.dp)
                            .clip(RoundedCornerShape(10.dp))
                            .background(if (isShuffle) Gold.copy(alpha = 0.18f) else Color.Transparent)
                            .border(
                                width = 1.dp,
                                color = if (isShuffle) Gold else Color.Transparent,
                                shape = RoundedCornerShape(10.dp)
                            )
                            .clickable { MusicPlayerManager.toggleShuffle(context) },
                        contentAlignment = Alignment.Center
                    ) {
                        GoldShuffleIcon(
                            color = if (isShuffle) Gold else Gold.copy(alpha = 0.65f),
                            modifier = Modifier.size(19.dp)
                        )
                    }

                    // Previous Track Button
                    Box(
                        modifier = Modifier
                            .size(38.dp)
                            .clip(CircleShape)
                            .clickable { MusicPlayerManager.playPrevious(context) },
                        contentAlignment = Alignment.Center
                    ) {
                        SkipPrevVectorIcon(
                            color = AppTheme.colors.textPrimary,
                            modifier = Modifier.size(18.dp)
                        )
                    }

                    // Play / Pause Gold Circle Button
                    Box(
                        modifier = Modifier
                            .size(48.dp)
                            .clip(CircleShape)
                            .background(Gold)
                            .clickable { MusicPlayerManager.togglePlayPause(context) },
                        contentAlignment = Alignment.Center
                    ) {
                        PlayPauseVectorIcon(
                            isPlaying = isPlaying,
                            color = Color(0xFF0F1117),
                            modifier = Modifier.size(20.dp)
                        )
                    }

                    // Next Track Button
                    Box(
                        modifier = Modifier
                            .size(38.dp)
                            .clip(CircleShape)
                            .clickable { MusicPlayerManager.playNext(context) },
                        contentAlignment = Alignment.Center
                    ) {
                        SkipNextVectorIcon(
                            color = AppTheme.colors.textPrimary,
                            modifier = Modifier.size(18.dp)
                        )
                    }

                    // Repeat Button (Dauerschleife) in Gold
                    Box(
                        modifier = Modifier
                            .size(38.dp)
                            .clip(RoundedCornerShape(10.dp))
                            .background(if (isRepeat) Gold.copy(alpha = 0.18f) else Color.Transparent)
                            .border(
                                width = 1.dp,
                                color = if (isRepeat) Gold else Color.Transparent,
                                shape = RoundedCornerShape(10.dp)
                            )
                            .clickable { MusicPlayerManager.toggleRepeat() },
                        contentAlignment = Alignment.Center
                    ) {
                        GoldRepeatIcon(
                            color = if (isRepeat) Gold else Gold.copy(alpha = 0.65f),
                            modifier = Modifier.size(19.dp)
                        )
                    }
                }
            }
        }

        // 2. PLAYLIST CHIPS ROW
        LazyRow(
            horizontalArrangement = Arrangement.spacedBy(8.dp),
            modifier = Modifier
                .fillMaxWidth()
                .padding(bottom = 10.dp)
        ) {
            item {
                PlaylistChip(
                    label = "♪ Alle (${allTracks.size})",
                    selected = activePlaylistId == "all",
                    onClick = { MusicPlayerManager.selectPlaylist("all", context, autoPlayRandom = isPlaying) }
                )
            }
            item {
                val favCount = allTracks.count { it.isFavorite }
                PlaylistChip(
                    label = "★ Favoriten ($favCount)",
                    selected = activePlaylistId == "favorites",
                    onClick = { MusicPlayerManager.selectPlaylist("favorites", context, autoPlayRandom = isPlaying) }
                )
            }
            items(playlists) { pl ->
                PlaylistChip(
                    label = "♪ ${pl.name} (${pl.tracks.size})",
                    selected = activePlaylistId == pl.id,
                    onClick = { MusicPlayerManager.selectPlaylist(pl.id, context, autoPlayRandom = isPlaying) }
                )
            }
        }

        // 3. SEARCH & PLAYLIST / YOUTUBE URL IMPORTER ROW
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(bottom = 8.dp),
            horizontalArrangement = Arrangement.spacedBy(8.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            OutlinedTextField(
                value = searchQuery,
                onValueChange = { searchQuery = it },
                placeholder = { Text("Songs suchen...", fontSize = 12.sp) },
                singleLine = true,
                modifier = Modifier.weight(0.95f),
                shape = RoundedCornerShape(12.dp),
                colors = OutlinedTextFieldDefaults.colors(
                    focusedBorderColor = Gold,
                    unfocusedBorderColor = AppTheme.colors.cardBorder
                )
            )

            OutlinedTextField(
                value = importOrPlaylistInput,
                onValueChange = { importOrPlaylistInput = it },
                placeholder = { Text("Neue Playlist / YT-Link...", fontSize = 11.5.sp) },
                singleLine = true,
                modifier = Modifier.weight(1.15f),
                shape = RoundedCornerShape(12.dp),
                colors = OutlinedTextFieldDefaults.colors(
                    focusedBorderColor = Gold,
                    unfocusedBorderColor = AppTheme.colors.cardBorder
                )
            )

            Button(
                onClick = {
                    val v = importOrPlaylistInput.trim()
                    if (v.isNotBlank() && !isImporting) {
                        if (v.startsWith("http://", ignoreCase = true) || v.startsWith("https://", ignoreCase = true)) {
                            isImporting = true
                            MusicPlayerManager.importYoutubeUrl(context, v) { ok ->
                                isImporting = false
                                if (ok) {
                                    importOrPlaylistInput = ""
                                    Toast.makeText(context, "Musik-Import gestartet!", Toast.LENGTH_SHORT).show()
                                } else {
                                    Toast.makeText(context, "Import fehlgeschlagen.", Toast.LENGTH_SHORT).show()
                                }
                            }
                        } else {
                            MusicPlayerManager.createPlaylist(context, v) { ok ->
                                if (ok) {
                                    importOrPlaylistInput = ""
                                    Toast.makeText(context, "Playlist angelegt!", Toast.LENGTH_SHORT).show()
                                }
                            }
                        }
                    }
                },
                colors = ButtonDefaults.buttonColors(containerColor = Gold, contentColor = Color(0xFF0F1117)),
                shape = RoundedCornerShape(12.dp),
                contentPadding = PaddingValues(horizontal = 10.dp, vertical = 12.dp)
            ) {
                Text(if (isImporting) "..." else "⬇ Import", fontSize = 11.5.sp, fontWeight = FontWeight.Bold)
            }
        }

        // LIVE PROGRESS BAR DURING YOUTUBE PLAYLIST / SONG IMPORT
        if (importProgress.active) {
            Card(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(bottom = 10.dp),
                shape = RoundedCornerShape(12.dp),
                colors = CardDefaults.cardColors(containerColor = AppTheme.colors.cardBackground),
                border = BorderStroke(1.5.dp, Gold)
            ) {
                Column(modifier = Modifier.padding(horizontal = 12.dp, vertical = 9.dp)) {
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Text(
                            text = importProgress.label.ifBlank { "Importiere..." },
                            color = AppTheme.colors.textPrimary,
                            fontSize = 12.sp,
                            fontWeight = FontWeight.Bold,
                            maxLines = 1,
                            overflow = TextOverflow.Ellipsis,
                            modifier = Modifier.weight(1f)
                        )
                        Text(
                            text = "${importProgress.percent} %",
                            color = Gold,
                            fontSize = 12.sp,
                            fontWeight = FontWeight.Bold
                        )
                    }
                    LinearProgressIndicator(
                        progress = { (importProgress.percent / 100f).coerceIn(0f, 1f) },
                        color = Gold,
                        trackColor = AppTheme.colors.cardBorder,
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(vertical = 6.dp)
                            .height(6.dp)
                            .clip(RoundedCornerShape(3.dp))
                    )
                    Text(
                        text = importProgress.subLabel,
                        color = AppTheme.colors.textMuted,
                        fontSize = 11.sp,
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis
                    )
                }
            }
        }

        // 4. TRACK LIST
        LazyColumn(
            modifier = Modifier.weight(1f),
            verticalArrangement = Arrangement.spacedBy(6.dp),
            contentPadding = PaddingValues(bottom = 80.dp)
        ) {
            itemsIndexed(filteredTracks, key = { _, tr -> tr.id }) { index, track ->
                val isCurrent = currentTrack?.id == track.id
                val isCached = MusicPlayerManager.isTrackCached(context, track.id)

                Card(
                    modifier = Modifier
                        .fillMaxWidth()
                        .clickable {
                            if (isCurrent) {
                                MusicPlayerManager.togglePlayPause(context)
                            } else {
                                MusicPlayerManager.playTrack(context, track, filteredTracks)
                            }
                        },
                    shape = RoundedCornerShape(12.dp),
                    colors = CardDefaults.cardColors(
                        containerColor = AppTheme.colors.cardBackground
                    ),
                    border = BorderStroke(
                        if (isCurrent) 2.dp else 1.dp,
                        if (isCurrent) Gold else AppTheme.colors.cardBorder
                    )
                ) {
                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(horizontal = 12.dp, vertical = 10.dp),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Text(
                            text = if (isCurrent) (if (isPlaying) "▶" else "❚❚") else "${index + 1}",
                            color = if (isCurrent) Gold else AppTheme.colors.textMuted,
                            fontWeight = FontWeight.Bold,
                            fontSize = 12.sp,
                            modifier = Modifier.width(26.dp)
                        )

                        Column(modifier = Modifier.weight(1f)) {
                            Text(
                                text = track.title,
                                color = if (isCurrent) Gold else AppTheme.colors.textPrimary,
                                fontWeight = FontWeight.Bold,
                                fontSize = 13.5.sp,
                                maxLines = 1,
                                overflow = TextOverflow.Ellipsis
                            )
                            Text(
                                text = track.artist.ifBlank { "Unbekannter Künstler" },
                                color = AppTheme.colors.textMuted,
                                fontSize = 11.5.sp,
                                maxLines = 1,
                                overflow = TextOverflow.Ellipsis
                            )
                        }

                        if (isCached) {
                            Text(
                                text = "⚡",
                                color = Color(0xFF34D399),
                                fontSize = 12.sp,
                                modifier = Modifier.padding(end = 6.dp)
                            )
                        }

                        Text(
                            text = track.formattedDuration,
                            color = AppTheme.colors.textMuted,
                            fontSize = 11.5.sp,
                            modifier = Modifier.padding(end = 8.dp)
                        )

                        Text(
                            text = if (track.isFavorite) "♥" else "♡",
                            color = if (track.isFavorite) Gold else AppTheme.colors.textMuted,
                            fontSize = 16.sp,
                            modifier = Modifier
                                .clickable { MusicPlayerManager.toggleFavorite(context, track) }
                                .padding(4.dp)
                        )
                    }
                }
            }
        }
    }
}

@Composable
private fun GoldShuffleIcon(color: Color, modifier: Modifier = Modifier) {
    Canvas(modifier = modifier) {
        val w = size.width
        val h = size.height
        val stroke = Stroke(width = w * 0.105f, cap = StrokeCap.Round, join = StrokeJoin.Round)

        val path = Path().apply {
            // Crossing line 1: top-left to bottom-right
            moveTo(w * 0.14f, h * 0.28f)
            lineTo(w * 0.36f, h * 0.28f)
            lineTo(w * 0.68f, h * 0.72f)
            lineTo(w * 0.86f, h * 0.72f)

            // Arrowhead bottom-right
            moveTo(w * 0.73f, h * 0.57f)
            lineTo(w * 0.88f, h * 0.72f)
            lineTo(w * 0.73f, h * 0.87f)

            // Crossing line 2: bottom-left to top-right
            moveTo(w * 0.14f, h * 0.72f)
            lineTo(w * 0.36f, h * 0.72f)
            lineTo(w * 0.68f, h * 0.28f)
            lineTo(w * 0.86f, h * 0.28f)

            // Arrowhead top-right
            moveTo(w * 0.73f, h * 0.13f)
            lineTo(w * 0.88f, h * 0.28f)
            lineTo(w * 0.73f, h * 0.43f)
        }
        drawPath(path = path, color = color, style = stroke)
    }
}

@Composable
private fun GoldRepeatIcon(color: Color, modifier: Modifier = Modifier) {
    Canvas(modifier = modifier) {
        val w = size.width
        val h = size.height
        val stroke = Stroke(width = w * 0.105f, cap = StrokeCap.Round, join = StrokeJoin.Round)

        val path = Path().apply {
            // Top loop: left-bottom up to top-right
            moveTo(w * 0.16f, h * 0.52f)
            lineTo(w * 0.16f, h * 0.32f)
            lineTo(w * 0.82f, h * 0.32f)

            // Top-right arrowhead
            moveTo(w * 0.67f, h * 0.16f)
            lineTo(w * 0.84f, h * 0.32f)
            lineTo(w * 0.67f, h * 0.48f)

            // Bottom loop: right-top down to bottom-left
            moveTo(w * 0.84f, h * 0.48f)
            lineTo(w * 0.84f, h * 0.68f)
            lineTo(w * 0.18f, h * 0.68f)

            // Bottom-left arrowhead
            moveTo(w * 0.33f, h * 0.52f)
            lineTo(w * 0.16f, h * 0.68f)
            lineTo(w * 0.33f, h * 0.84f)
        }
        drawPath(path = path, color = color, style = stroke)
    }
}

@Composable
private fun PlayPauseVectorIcon(isPlaying: Boolean, color: Color, modifier: Modifier = Modifier) {
    Canvas(modifier = modifier) {
        val w = size.width
        val h = size.height
        if (isPlaying) {
            val barW = w * 0.24f
            val barH = h * 0.68f
            val top = (h - barH) / 2f
            drawRoundRect(
                color = color,
                topLeft = Offset(w * 0.18f, top),
                size = Size(barW, barH),
                cornerRadius = CornerRadius(barW * 0.3f, barW * 0.3f)
            )
            drawRoundRect(
                color = color,
                topLeft = Offset(w * 0.58f, top),
                size = Size(barW, barH),
                cornerRadius = CornerRadius(barW * 0.3f, barW * 0.3f)
            )
        } else {
            val path = Path().apply {
                moveTo(w * 0.24f, h * 0.16f)
                lineTo(w * 0.84f, h * 0.50f)
                lineTo(w * 0.24f, h * 0.84f)
                close()
            }
            drawPath(path = path, color = color, style = Fill)
        }
    }
}

@Composable
private fun SkipPrevVectorIcon(color: Color, modifier: Modifier = Modifier) {
    Canvas(modifier = modifier) {
        val w = size.width
        val h = size.height
        drawRoundRect(
            color = color,
            topLeft = Offset(w * 0.16f, h * 0.20f),
            size = Size(w * 0.12f, h * 0.60f),
            cornerRadius = CornerRadius(2f, 2f)
        )
        val path = Path().apply {
            moveTo(w * 0.82f, h * 0.20f)
            lineTo(w * 0.34f, h * 0.50f)
            lineTo(w * 0.82f, h * 0.80f)
            close()
        }
        drawPath(path = path, color = color, style = Fill)
    }
}

@Composable
private fun SkipNextVectorIcon(color: Color, modifier: Modifier = Modifier) {
    Canvas(modifier = modifier) {
        val w = size.width
        val h = size.height
        val path = Path().apply {
            moveTo(w * 0.18f, h * 0.20f)
            lineTo(w * 0.66f, h * 0.50f)
            lineTo(w * 0.18f, h * 0.80f)
            close()
        }
        drawPath(path = path, color = color, style = Fill)
        drawRoundRect(
            color = color,
            topLeft = Offset(w * 0.72f, h * 0.20f),
            size = Size(w * 0.12f, h * 0.60f),
            cornerRadius = CornerRadius(2f, 2f)
        )
    }
}

@Composable
private fun PlaylistChip(
    label: String,
    selected: Boolean,
    onClick: () -> Unit
) {
    Surface(
        shape = RoundedCornerShape(10.dp),
        color = if (selected) Gold else AppTheme.colors.cardBackground,
        border = BorderStroke(1.dp, if (selected) Gold else AppTheme.colors.cardBorder),
        modifier = Modifier.clickable { onClick() }
    ) {
        Text(
            text = label,
            color = if (selected) Color(0xFF0F1117) else AppTheme.colors.textPrimary,
            fontSize = 12.sp,
            fontWeight = if (selected) FontWeight.Bold else FontWeight.Medium,
            modifier = Modifier.padding(horizontal = 12.dp, vertical = 7.dp)
        )
    }
}

private fun formatSec(sec: Int): String {
    if (sec <= 0) return "0:00"
    val m = sec / 60
    val s = sec % 60
    return String.format("%d:%02d", m, s)
}
