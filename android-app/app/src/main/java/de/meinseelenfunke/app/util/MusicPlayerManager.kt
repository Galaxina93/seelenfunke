package de.meinseelenfunke.app.util

import android.content.Context
import android.media.AudioAttributes
import android.media.AudioFocusRequest
import android.media.AudioManager
import android.media.MediaPlayer
import android.os.Build
import android.os.PowerManager
import android.util.Log
import de.meinseelenfunke.app.di.ServiceLocator
import de.meinseelenfunke.app.services.MusicPlaybackService
import de.meinseelenfunke.app.widget.MusicWidgetProvider
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.Job
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.isActive
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.OkHttpClient
import okhttp3.Request
import okhttp3.RequestBody.Companion.toRequestBody
import org.json.JSONArray
import org.json.JSONObject
import java.io.File
import java.io.FileInputStream
import java.util.concurrent.TimeUnit
import java.util.concurrent.atomic.AtomicLong

data class MusicPlaylistDto(
    val id: String,
    val name: String,
    val description: String,
    val tracks: List<MusicTrackDto>
)

data class MusicTrackDto(
    val id: String,
    val playlistId: String,
    val title: String,
    val artist: String,
    val durationSeconds: Int,
    val fileSize: Long,
    val isFavorite: Boolean,
    val position: Int
) {
    val formattedDuration: String
        get() {
            if (durationSeconds <= 0) return "--:--"
            val m = durationSeconds / 60
            val s = durationSeconds % 60
            return String.format("%d:%02d", m, s)
        }
}

data class MusicImportProgress(
    val active: Boolean = false,
    val percent: Int = 0,
    val label: String = "",
    val subLabel: String = ""
)

object MusicPlayerManager {
    private const val TAG = "MusicPlayerManager"
    private const val FALLBACK_TOKEN = "1|CTIWGm2Ryb0d60ixSJPME2J6uSUkR1tm6Ga6DEfBd86476e9"

    private val scope = CoroutineScope(SupervisorJob() + Dispatchers.Main)
    private val httpClient = OkHttpClient.Builder()
        .connectTimeout(4, TimeUnit.SECONDS)
        .readTimeout(45, TimeUnit.SECONDS)
        .build()
    private val fastMetaClient = OkHttpClient.Builder()
        .connectTimeout(3, TimeUnit.SECONDS)
        .readTimeout(8, TimeUnit.SECONDS)
        .build()

    private var appContext: Context? = null
    private var mediaPlayer: MediaPlayer? = null
    private var playJob: Job? = null
    private var progressJob: Job? = null
    private var preCacheJob: Job? = null
    private var backgroundSyncJob: Job? = null
    private var importPollJob: Job? = null
    private var wakeLock: PowerManager.WakeLock? = null
    private var audioFocusRequest: AudioFocusRequest? = null

    private val transitionSeq = AtomicLong(0L)

    @Volatile
    private var isTransitioningTrack: Boolean = false

    private val _playlists = MutableStateFlow<List<MusicPlaylistDto>>(emptyList())
    val playlists: StateFlow<List<MusicPlaylistDto>> = _playlists.asStateFlow()

    private val _allTracks = MutableStateFlow<List<MusicTrackDto>>(emptyList())
    val allTracks: StateFlow<List<MusicTrackDto>> = _allTracks.asStateFlow()

    private val _activePlaylistId = MutableStateFlow("all")
    val activePlaylistId: StateFlow<String> = _activePlaylistId.asStateFlow()

    private val _currentTrack = MutableStateFlow<MusicTrackDto?>(null)
    val currentTrack: StateFlow<MusicTrackDto?> = _currentTrack.asStateFlow()

    private val _isPlaying = MutableStateFlow(false)
    val isPlaying: StateFlow<Boolean> = _isPlaying.asStateFlow()

    private val _isShuffle = MutableStateFlow(false)
    val isShuffle: StateFlow<Boolean> = _isShuffle.asStateFlow()

    private val _isRepeat = MutableStateFlow(false)
    val isRepeat: StateFlow<Boolean> = _isRepeat.asStateFlow()

    private val _currentPositionSec = MutableStateFlow(0)
    val currentPositionSec: StateFlow<Int> = _currentPositionSec.asStateFlow()

    private val _durationSec = MutableStateFlow(0)
    val durationSec: StateFlow<Int> = _durationSec.asStateFlow()

    private val _statusText = MutableStateFlow("Bereit für Sofort-Stream")
    val statusText: StateFlow<String> = _statusText.asStateFlow()

    private val _importProgress = MutableStateFlow(MusicImportProgress())
    val importProgress: StateFlow<MusicImportProgress> = _importProgress.asStateFlow()

    private var currentQueue: List<MusicTrackDto> = emptyList()

    private fun getCacheDir(context: Context): File {
        val dir = File(context.filesDir, "music_cache")
        if (!dir.exists()) dir.mkdirs()
        return dir
    }

    private fun getCatalogCacheFile(context: Context): File {
        return File(getCacheDir(context), "playlists_cache.json")
    }

    private fun parsePlaylistsJson(jsonStr: String): List<MusicPlaylistDto> {
        return try {
            val trimmed = jsonStr.trimStart()
            val dataArr = if (trimmed.startsWith("[")) {
                JSONArray(trimmed)
            } else {
                val root = JSONObject(trimmed)
                root.optJSONArray("data") ?: JSONArray()
            }
            val list = mutableListOf<MusicPlaylistDto>()
            for (i in 0 until dataArr.length()) {
                val plObj = dataArr.getJSONObject(i)
                val plId = plObj.optString("id")
                val plName = plObj.optString("name", "Playlist")
                val plDesc = plObj.optString("description", "")
                val trArr = plObj.optJSONArray("tracks") ?: JSONArray()
                val tracks = mutableListOf<MusicTrackDto>()

                for (j in 0 until trArr.length()) {
                    val trObj = trArr.getJSONObject(j)
                    tracks.add(
                        MusicTrackDto(
                            id = trObj.optString("id"),
                            playlistId = plId,
                            title = trObj.optString("title", "Unbekannter Titel"),
                            artist = trObj.optString("artist", "Unbekannter Künstler"),
                            durationSeconds = trObj.optInt("duration_seconds", 0),
                            fileSize = trObj.optLong("file_size", 0L),
                            isFavorite = trObj.optBoolean("is_favorite", false) || trObj.optInt("is_favorite", 0) == 1,
                            position = trObj.optInt("position", j + 1)
                        )
                    )
                }
                list.add(MusicPlaylistDto(plId, plName, plDesc, tracks))
            }
            list
        } catch (e: Exception) {
            Log.e(TAG, "Failed to parse playlists JSON", e)
            emptyList()
        }
    }

    private fun serializePlaylistsJson(playlists: List<MusicPlaylistDto>): String {
        val root = JSONObject()
        root.put("success", true)
        val dataArr = JSONArray()
        for (pl in playlists) {
            val plObj = JSONObject()
            plObj.put("id", pl.id)
            plObj.put("name", pl.name)
            plObj.put("description", pl.description)
            val trArr = JSONArray()
            for (tr in pl.tracks) {
                val trObj = JSONObject()
                trObj.put("id", tr.id)
                trObj.put("playlist_id", tr.playlistId)
                trObj.put("title", tr.title)
                trObj.put("artist", tr.artist)
                trObj.put("duration_seconds", tr.durationSeconds)
                trObj.put("file_size", tr.fileSize)
                trObj.put("is_favorite", tr.isFavorite)
                trObj.put("position", tr.position)
                trArr.put(trObj)
            }
            plObj.put("tracks", trArr)
            dataArr.put(plObj)
        }
        root.put("data", dataArr)
        return root.toString()
    }

    private fun savePlaylistsToLocalCache(context: Context, playlists: List<MusicPlaylistDto>) {
        if (playlists.sumOf { it.tracks.size } == 0) return
        try {
            val file = getCatalogCacheFile(context)
            file.writeText(serializePlaylistsJson(playlists), Charsets.UTF_8)
        } catch (e: Exception) {
            Log.e(TAG, "Failed to save playlists to local cache", e)
        }
    }

    private fun loadPlaylistsFromLocalCache(context: Context): List<MusicPlaylistDto> {
        val catalogFile = getCatalogCacheFile(context)
        val cachedPlaylists = if (catalogFile.exists() && catalogFile.length() > 10L) {
            try {
                parsePlaylistsJson(catalogFile.readText(Charsets.UTF_8)).toMutableList()
            } catch (_: Exception) {
                mutableListOf()
            }
        } else {
            mutableListOf()
        }

        // Ensure any local .m4a files on disk that aren't in playlists_cache.json are still visible
        val knownTrackIds = cachedPlaylists.flatMap { it.tracks }.map { it.id }.toSet()
        val orphanFiles = getCacheDir(context)
            .listFiles { _, name -> name.endsWith(".m4a") }
            ?.filter { it.length() > 10240L && it.nameWithoutExtension !in knownTrackIds && isValidAudioFile(it) }
            ?.sortedByDescending { it.lastModified() }
            ?: emptyList()

        if (orphanFiles.isNotEmpty()) {
            val orphanPlId = "local_offline_cache"
            val orphanTracks = orphanFiles.mapIndexed { idx, f ->
                var title = "Lokaler Song #${idx + 1}"
                var artist = "Offline-Speicher"
                var durSec = 0
                try {
                    val mmr = android.media.MediaMetadataRetriever()
                    mmr.setDataSource(f.absolutePath)
                    val metaTitle = mmr.extractMetadata(android.media.MediaMetadataRetriever.METADATA_KEY_TITLE)
                    val metaArtist = mmr.extractMetadata(android.media.MediaMetadataRetriever.METADATA_KEY_ARTIST)
                    val metaDur = mmr.extractMetadata(android.media.MediaMetadataRetriever.METADATA_KEY_DURATION)?.toLongOrNull()
                    if (!metaTitle.isNullOrBlank()) title = metaTitle
                    if (!metaArtist.isNullOrBlank()) artist = metaArtist
                    if (metaDur != null && metaDur > 0) durSec = (metaDur / 1000L).toInt()
                    mmr.release()
                } catch (_: Exception) { }

                MusicTrackDto(
                    id = f.nameWithoutExtension,
                    playlistId = orphanPlId,
                    title = title,
                    artist = artist,
                    durationSeconds = durSec,
                    fileSize = f.length(),
                    isFavorite = false,
                    position = idx + 1
                )
            }
            cachedPlaylists.add(
                MusicPlaylistDto(
                    id = orphanPlId,
                    name = "Offline auf dem Handy",
                    description = "Lokal gespeicherte Audiodateien",
                    tracks = orphanTracks
                )
            )
        }

        return cachedPlaylists
    }

    private fun mergePlaylists(remote: List<MusicPlaylistDto>, local: List<MusicPlaylistDto>): List<MusicPlaylistDto> {
        val remoteTrackCount = remote.sumOf { it.tracks.size }
        if (remoteTrackCount == 0) return local
        if (local.isEmpty()) return remote

        val merged = remote.map { rPl ->
            val lPl = local.firstOrNull { it.id == rPl.id || it.name.equals(rPl.name, ignoreCase = true) }
            if (lPl == null) {
                rPl
            } else {
                val remoteTrackIds = rPl.tracks.map { it.id }.toSet()
                val missingLocalTracks = lPl.tracks.filter { it.id !in remoteTrackIds }
                if (missingLocalTracks.isEmpty()) rPl
                else rPl.copy(tracks = rPl.tracks + missingLocalTracks)
            }
        }.toMutableList()

        val remotePlIds = merged.map { it.id }.toSet()
        val remotePlNames = merged.map { it.name.lowercase() }.toSet()
        for (lPl in local) {
            if (lPl.id == "local_offline_cache") continue
            if (lPl.id !in remotePlIds && lPl.name.lowercase() !in remotePlNames && lPl.tracks.isNotEmpty()) {
                merged.add(lPl)
            }
        }
        return merged
    }

    private fun applyPlaylistsToState(appCtx: Context, playlistsToApply: List<MusicPlaylistDto>) {
        if (playlistsToApply.isEmpty()) return
        _playlists.value = playlistsToApply
        val flat = playlistsToApply.flatMap { it.tracks }
        _allTracks.value = flat

        val curActiveId = _activePlaylistId.value
        val activeExists = curActiveId == "all" || curActiveId == "favorites" || playlistsToApply.any { it.id == curActiveId }
        if (curActiveId == "all" || !activeExists) {
            val favPl = playlistsToApply.firstOrNull { it.name.contains("Lieblingssongs", ignoreCase = true) }
                ?: playlistsToApply.first()
            _activePlaylistId.value = favPl.id
        }

        val activeFiltered = getFilteredTracks()
        currentQueue = if (activeFiltered.isNotEmpty()) activeFiltered else flat

        val existingCur = _currentTrack.value
        if (existingCur == null && currentQueue.isNotEmpty()) {
            _currentTrack.value = currentQueue.first()
            _durationSec.value = currentQueue.first().durationSeconds
        } else if (existingCur != null) {
            flat.firstOrNull { it.id == existingCur.id }?.let { updated ->
                _currentTrack.value = updated
            }
        }
        MusicWidgetProvider.updateAllWidgets(appCtx)
    }

    private fun autoSyncUncachedTracksInBackground(context: Context) {
        if (backgroundSyncJob?.isActive == true) return
        val appCtx = context.applicationContext
        backgroundSyncJob = scope.launch(Dispatchers.IO) {
            val missing = _allTracks.value.filter { !isTrackCached(appCtx, it.id) }
            if (missing.isEmpty()) return@launch
            // Probe first missing track to see if any server with audio files is currently reachable
            val firstResult = ensureTrackCached(appCtx, missing.first()) ?: return@launch
            if (!firstResult.exists()) return@launch
            for (i in 1 until missing.size) {
                if (!isActive) break
                ensureTrackCached(appCtx, missing[i])
            }
        }
    }

    private fun isValidAudioFile(file: File): Boolean {
        if (!file.exists() || file.length() <= 10240L) return false
        return try {
            val header = ByteArray(16)
            val read = FileInputStream(file).use { it.read(header) }
            if (read < 8) return false
            val prefix = String(header, 0, read, Charsets.ISO_8859_1).trimStart()
            // Reject HTML login redirects or JSON error bodies that might have been cached
            !prefix.startsWith("<") && !prefix.startsWith("{")
        } catch (_: Exception) {
            false
        }
    }

    fun isTrackCached(context: Context, trackId: String): Boolean {
        val f = File(getCacheDir(context), "$trackId.m4a")
        return isValidAudioFile(f)
    }

    fun getCachedCount(context: Context): Int {
        return getCacheDir(context).listFiles { _, name -> name.endsWith(".m4a") }?.count { it.length() > 10240L } ?: 0
    }

    private fun acquireWakeLock(context: Context) {
        try {
            if (wakeLock == null) {
                val pm = context.getSystemService(Context.POWER_SERVICE) as PowerManager
                wakeLock = pm.newWakeLock(PowerManager.PARTIAL_WAKE_LOCK, "Funkenwerk::PlayerManagerWakeLock").apply {
                    setReferenceCounted(false)
                }
            }
            if (wakeLock?.isHeld == false) {
                wakeLock?.acquire(4 * 60 * 60 * 1000L)
            }
        } catch (_: Exception) { }
    }

    private fun requestAudioFocus(context: Context) {
        try {
            val am = context.getSystemService(Context.AUDIO_SERVICE) as AudioManager
            val attrs = AudioAttributes.Builder()
                .setUsage(AudioAttributes.USAGE_MEDIA)
                .setContentType(AudioAttributes.CONTENT_TYPE_MUSIC)
                .build()
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                val req = audioFocusRequest ?: AudioFocusRequest.Builder(AudioManager.AUDIOFOCUS_GAIN)
                    .setAudioAttributes(attrs)
                    .setWillPauseWhenDucked(false)
                    .setOnAudioFocusChangeListener { /* keep playing unless user explicitly pauses */ }
                    .build().also { audioFocusRequest = it }
                am.requestAudioFocus(req)
            } else {
                @Suppress("DEPRECATION")
                am.requestAudioFocus(null, AudioManager.STREAM_MUSIC, AudioManager.AUDIOFOCUS_GAIN)
            }
        } catch (_: Exception) { }
    }

    private fun getCandidateTokens(): List<String> {
        val primary = try { ServiceLocator.getAuthToken() } catch (_: Exception) { null }
        return if (!primary.isNullOrBlank() && primary != FALLBACK_TOKEN) {
            listOf(primary, FALLBACK_TOKEN)
        } else {
            listOf(FALLBACK_TOKEN)
        }
    }

    private fun getCandidateBaseUrls(): List<String> {
        val primary = try { ServiceLocator.getBaseUrl().trimEnd('/') } catch (_: Exception) { "http://192.168.188.119/api" }
        val list = mutableListOf(primary)
        if (primary != "http://192.168.188.119/api") list.add("http://192.168.188.119/api")
        if (primary != "http://127.0.0.1/api") list.add("http://127.0.0.1/api")
        return list.distinct()
    }

    fun selectPlaylist(playlistId: String) {
        _activePlaylistId.value = playlistId
        val filtered = getFilteredTracks()
        if (filtered.isNotEmpty()) {
            currentQueue = filtered
        }
    }

    fun toggleShuffle(context: Context) {
        val appCtx = context.applicationContext
        appContext = appCtx
        _isShuffle.value = !_isShuffle.value
        MusicWidgetProvider.updateAllWidgets(appCtx)
        if (_isPlaying.value || _currentTrack.value != null) {
            MusicPlaybackService.startOrUpdate(appCtx)
        }
    }

    fun toggleRepeat() {
        _isRepeat.value = !_isRepeat.value
    }

    fun fetchPlaylists(context: Context, onComplete: (() -> Unit)? = null) {
        val appCtx = context.applicationContext
        appContext = appCtx
        scope.launch {
            try {
                // 1. Instant local offline load (0ms network wait) so music is always visible
                val localCached = withContext(Dispatchers.IO) {
                    loadPlaylistsFromLocalCache(appCtx)
                }
                if (localCached.isNotEmpty() && _allTracks.value.isEmpty()) {
                    applyPlaylistsToState(appCtx, localCached)
                    val cachedFilesCount = withContext(Dispatchers.IO) { getCachedCount(appCtx) }
                    if (!_isPlaying.value) {
                        _statusText.value = "⚡ $cachedFilesCount Songs offline auf dem Handy bereit"
                    }
                }

                // 2. Smart network sync: never overwrite local music with an empty Stage/Live server response
                val remotePlaylists = withContext(Dispatchers.IO) {
                    var bestList: List<MusicPlaylistDto> = emptyList()
                    for (baseUrl in getCandidateBaseUrls()) {
                        if (bestList.isNotEmpty()) break
                        for (token in getCandidateTokens()) {
                            if (bestList.isNotEmpty()) break
                            try {
                                val req = Request.Builder()
                                    .url("$baseUrl/funki/music/playlists")
                                    .header("Authorization", "Bearer $token")
                                    .header("Accept", "application/json")
                                    .get()
                                    .build()
                                fastMetaClient.newCall(req).execute().use { res ->
                                    if (res.isSuccessful) {
                                        val s = res.body?.string()
                                        if (!s.isNullOrBlank() && s.trimStart().startsWith("{")) {
                                            val candidate = parsePlaylistsJson(s)
                                            // Only accept if the server actually has tracks in its music library!
                                            if (candidate.sumOf { it.tracks.size } > 0) {
                                                bestList = candidate
                                            }
                                        }
                                    }
                                }
                            } catch (_: Exception) { }
                        }
                    }
                    bestList
                }

                val finalPlaylists = if (remotePlaylists.isNotEmpty()) {
                    val merged = mergePlaylists(remotePlaylists, localCached)
                    withContext(Dispatchers.IO) {
                        savePlaylistsToLocalCache(appCtx, merged)
                    }
                    merged
                } else {
                    localCached
                }

                if (finalPlaylists.isNotEmpty()) {
                    applyPlaylistsToState(appCtx, finalPlaylists)
                    autoSyncUncachedTracksInBackground(appCtx)
                }
            } catch (e: Exception) {
                Log.e(TAG, "Error fetching playlists", e)
            } finally {
                onComplete?.invoke()
            }
        }
    }

    fun getFilteredTracks(searchQuery: String = ""): List<MusicTrackDto> {
        val base = when (val pid = _activePlaylistId.value) {
            "all" -> _allTracks.value
            "favorites" -> _allTracks.value.filter { it.isFavorite }
            else -> _allTracks.value.filter { it.playlistId == pid }
        }
        if (searchQuery.isBlank()) return base
        val q = searchQuery.trim()
        return base.filter {
            it.title.contains(q, ignoreCase = true) || it.artist.contains(q, ignoreCase = true)
        }
    }

    private suspend fun ensureTrackCached(context: Context, track: MusicTrackDto): File? = withContext(Dispatchers.IO) {
        val cacheDir = getCacheDir(context)
        val file = File(cacheDir, "${track.id}.m4a")
        if (isValidAudioFile(file)) {
            return@withContext file
        }
        if (file.exists()) {
            try { file.delete() } catch (_: Exception) { }
        }

        val tmpFile = File(cacheDir, "${track.id}.m4a.tmp")
        for (baseUrl in getCandidateBaseUrls()) {
            for (token in getCandidateTokens()) {
                try {
                    val req = Request.Builder()
                        .url("$baseUrl/funki/music/stream/${track.id}")
                        .header("Authorization", "Bearer $token")
                        .header("Accept", "application/json")
                        .get()
                        .build()
                    httpClient.newCall(req).execute().use { res ->
                        if (!res.isSuccessful) return@use
                        val contentType = (res.header("Content-Type") ?: "").lowercase()
                        if (contentType.contains("text/html") || contentType.contains("application/json")) {
                            return@use
                        }
                        val bytes = res.body?.bytes() ?: return@use
                        if (bytes.size <= 10240) return@use
                        tmpFile.writeBytes(bytes)
                        if (isValidAudioFile(tmpFile)) {
                            if (file.exists()) file.delete()
                            if (tmpFile.renameTo(file)) {
                                Log.d(TAG, "Successfully cached track ${track.title} (${bytes.size} bytes)")
                                return@withContext file
                            } else {
                                file.writeBytes(bytes)
                                tmpFile.delete()
                                return@withContext file
                            }
                        } else {
                            tmpFile.delete()
                        }
                    }
                } catch (e: Exception) {
                    Log.w(TAG, "Stream download attempt failed ($baseUrl): ${e.message}")
                }
            }
        }
        null
    }

    fun playTrack(
        context: Context,
        track: MusicTrackDto,
        queue: List<MusicTrackDto>? = null,
        retryDepth: Int = 0
    ) {
        val appCtx = context.applicationContext
        appContext = appCtx

        if (queue != null && queue.isNotEmpty()) {
            currentQueue = queue
        } else if (currentQueue.isEmpty() || currentQueue.none { it.id == track.id }) {
            val filtered = getFilteredTracks()
            currentQueue = if (filtered.any { it.id == track.id }) filtered else _allTracks.value
        }

        val txId = transitionSeq.incrementAndGet()
        isTransitioningTrack = true
        progressJob?.cancel()

        acquireWakeLock(appCtx)
        requestAudioFocus(appCtx)

        _currentTrack.value = track
        _durationSec.value = track.durationSeconds
        _currentPositionSec.value = 0
        _isPlaying.value = true
        _statusText.value = "Lade Audio in Smart-Cache..."

        MusicPlaybackService.startOrUpdate(appCtx)
        MusicWidgetProvider.updateAllWidgets(appCtx)

        playJob = scope.launch {
            Log.d(TAG, "playTrack starting [${track.title}] txId=$txId retry=$retryDepth")

            val localFile = ensureTrackCached(appCtx, track)
            if (transitionSeq.get() != txId) return@launch

            if (localFile == null) {
                Log.w(TAG, "localFile is null for [${track.title}], retryDepth=$retryDepth")
                isTransitioningTrack = false
                // Try next cached or available track in queue instead of stopping!
                if (currentQueue.size > 1 && retryDepth < 8) {
                    val nextCandidate = findNextPlayableCandidate(appCtx, track.id)
                    if (nextCandidate != null) {
                        _statusText.value = "Wechsle zu verfügbarem Song..."
                        delay(200)
                        playTrack(appCtx, nextCandidate, currentQueue, retryDepth + 1)
                        return@launch
                    }
                }
                _statusText.value = "Song aktuell offline nicht im Cache"
                _isPlaying.value = false
                MusicPlaybackService.startOrUpdate(appCtx)
                MusicWidgetProvider.updateAllWidgets(appCtx)
                return@launch
            }

            try {
                val mp = withContext(Dispatchers.IO) {
                    val player = mediaPlayer ?: MediaPlayer().also { mediaPlayer = it }
                    try {
                        player.setOnCompletionListener(null)
                        player.setOnErrorListener(null)
                        player.reset()
                    } catch (_: Exception) {
                        try { player.release() } catch (_: Exception) { }
                        MediaPlayer().also { mediaPlayer = it }
                    }

                    val activePlayer = mediaPlayer!!
                    try {
                        activePlayer.setWakeMode(appCtx, PowerManager.PARTIAL_WAKE_LOCK)
                    } catch (_: Exception) { }

                    activePlayer.setAudioAttributes(
                        AudioAttributes.Builder()
                            .setContentType(AudioAttributes.CONTENT_TYPE_MUSIC)
                            .setUsage(AudioAttributes.USAGE_MEDIA)
                            .build()
                    )

                    FileInputStream(localFile).use { fis ->
                        activePlayer.setDataSource(fis.fd)
                        activePlayer.isLooping = false
                        activePlayer.prepare()
                    }
                    activePlayer
                }

                if (transitionSeq.get() != txId) return@launch

                mp.setOnCompletionListener {
                    Log.d(TAG, "onCompletionListener fired for [${track.title}]")
                    handleTrackFinished(appCtx, txId)
                }

                mp.setOnErrorListener { _, what, extra ->
                    Log.w(TAG, "MediaPlayer OnError what=$what extra=$extra on [${track.title}]")
                    // Ignore minor non-fatal info codes; only advance if playback actually stopped
                    try {
                        if (!mp.isPlaying && !isTransitioningTrack) {
                            handleTrackFinished(appCtx, txId)
                        }
                    } catch (_: Exception) {
                        if (!isTransitioningTrack) {
                            handleTrackFinished(appCtx, txId)
                        }
                    }
                    true
                }

                requestAudioFocus(appCtx)
                mp.start()

                val realDurSec = (mp.duration / 1000).coerceAtLeast(track.durationSeconds).coerceAtLeast(1)
                _durationSec.value = realDurSec
                _isPlaying.value = true
                isTransitioningTrack = false
                _statusText.value = "⚡ Lokal gecacht · 256 kbps AAC"

                Log.d(TAG, "Playback started for [${track.title}], duration=${realDurSec}s")

                startProgressLoop(appCtx, txId)
                MusicPlaybackService.startOrUpdate(appCtx)
                MusicWidgetProvider.updateAllWidgets(appCtx)

                // Pre-cache next 3 songs in background for gapless transition
                val idx = currentQueue.indexOfFirst { it.id == track.id }
                if (idx >= 0 && currentQueue.size > 1) {
                    preCacheJob?.cancel()
                    preCacheJob = scope.launch(Dispatchers.IO) {
                        for (offset in 1..minOf(3, currentQueue.size - 1)) {
                            val nextTr = currentQueue[(idx + offset) % currentQueue.size]
                            ensureTrackCached(appCtx, nextTr)
                        }
                    }
                }
            } catch (e: Exception) {
                Log.e(TAG, "MediaPlayer error on track ${track.title}", e)
                isTransitioningTrack = false
                try { localFile.delete() } catch (_: Exception) { }
                try {
                    mediaPlayer?.release()
                } catch (_: Exception) { }
                mediaPlayer = null

                if (currentQueue.size > 1 && retryDepth < 5) {
                    val nextCandidate = findNextPlayableCandidate(appCtx, track.id)
                    if (nextCandidate != null) {
                        delay(250)
                        playTrack(appCtx, nextCandidate, currentQueue, retryDepth + 1)
                        return@launch
                    }
                }
                _statusText.value = "Wiedergabefehler"
                _isPlaying.value = false
                MusicPlaybackService.startOrUpdate(appCtx)
                MusicWidgetProvider.updateAllWidgets(appCtx)
            }
        }
    }

    private fun findNextPlayableCandidate(appCtx: Context, currentId: String): MusicTrackDto? {
        if (currentQueue.isEmpty()) return null
        val curIdx = currentQueue.indexOfFirst { it.id == currentId }.coerceAtLeast(0)
        // Prefer next cached track first so offline playback never stalls
        for (offset in 1 until currentQueue.size) {
            val candidate = currentQueue[(curIdx + offset) % currentQueue.size]
            if (candidate.id != currentId && isTrackCached(appCtx, candidate.id)) {
                return candidate
            }
        }
        val immediateNext = currentQueue[(curIdx + 1) % currentQueue.size]
        if (immediateNext.id != currentId) return immediateNext
        return null
    }

    private fun handleTrackFinished(appCtx: Context, expectedTxId: Long = transitionSeq.get()) {
        if (isTransitioningTrack || transitionSeq.get() != expectedTxId) return
        isTransitioningTrack = true
        Log.d(TAG, "handleTrackFinished advancing from txId=$expectedTxId")

        scope.launch {
            if (_isRepeat.value) {
                val mp = mediaPlayer
                if (mp != null) {
                    try {
                        mp.seekTo(0)
                        mp.start()
                        _currentPositionSec.value = 0
                        _isPlaying.value = true
                        isTransitioningTrack = false
                        startProgressLoop(appCtx, expectedTxId)
                        MusicPlaybackService.startOrUpdate(appCtx)
                        MusicWidgetProvider.updateAllWidgets(appCtx)
                        return@launch
                    } catch (_: Exception) { }
                }
                val cur = _currentTrack.value
                if (cur != null) {
                    playTrack(appCtx, cur, currentQueue)
                    return@launch
                }
            }
            playNext(appCtx)
        }
    }

    fun togglePlayPause(context: Context) {
        val appCtx = context.applicationContext
        appContext = appCtx
        val mp = mediaPlayer
        if (mp != null) {
            try {
                if (mp.isPlaying) {
                    mp.pause()
                    _isPlaying.value = false
                    MusicPlaybackService.startOrUpdate(appCtx)
                } else {
                    acquireWakeLock(appCtx)
                    requestAudioFocus(appCtx)
                    mp.start()
                    _isPlaying.value = true
                    isTransitioningTrack = false
                    startProgressLoop(appCtx, transitionSeq.get())
                    MusicPlaybackService.startOrUpdate(appCtx)
                }
                MusicWidgetProvider.updateAllWidgets(appCtx)
                return
            } catch (_: Exception) { }
        }

        val current = _currentTrack.value
        if (current != null) {
            playTrack(appCtx, current, currentQueue.ifEmpty { _allTracks.value })
        } else if (_allTracks.value.isNotEmpty()) {
            val q = getFilteredTracks().ifEmpty { _allTracks.value }
            playTrack(appCtx, q.first(), q)
        } else {
            fetchPlaylists(appCtx) {
                if (_allTracks.value.isNotEmpty()) {
                    val q = getFilteredTracks().ifEmpty { _allTracks.value }
                    playTrack(appCtx, q.first(), q)
                }
            }
        }
    }

    fun playNext(context: Context) {
        val appCtx = context.applicationContext
        appContext = appCtx
        if (currentQueue.isEmpty()) {
            currentQueue = getFilteredTracks().ifEmpty { _allTracks.value }
        }
        if (currentQueue.isEmpty()) {
            isTransitioningTrack = false
            fetchPlaylists(appCtx) {
                if (_allTracks.value.isNotEmpty()) {
                    val q = getFilteredTracks().ifEmpty { _allTracks.value }
                    playTrack(appCtx, q.first(), q)
                }
            }
            return
        }

        val curId = _currentTrack.value?.id
        val nextTrack = if (_isShuffle.value && currentQueue.size > 1) {
            val candidates = currentQueue.filter { it.id != curId }
            candidates.randomOrNull() ?: currentQueue.random()
        } else {
            val idx = currentQueue.indexOfFirst { it.id == curId }
            val nextIdx = if (idx >= 0) (idx + 1) % currentQueue.size else 0
            currentQueue[nextIdx]
        }
        Log.d(TAG, "playNext transitioning from $curId to [${nextTrack.title}] (${nextTrack.id})")
        playTrack(appCtx, nextTrack, currentQueue)
    }

    fun playPrevious(context: Context) {
        val appCtx = context.applicationContext
        appContext = appCtx
        val mp = mediaPlayer
        try {
            if (mp != null && mp.currentPosition > 3500) {
                mp.seekTo(0)
                _currentPositionSec.value = 0
                MusicPlaybackService.startOrUpdate(appCtx)
                return
            }
        } catch (_: Exception) { }

        if (currentQueue.isEmpty()) {
            currentQueue = getFilteredTracks().ifEmpty { _allTracks.value }
        }
        if (currentQueue.isEmpty()) return

        val idx = currentQueue.indexOfFirst { it.id == _currentTrack.value?.id }.coerceAtLeast(0)
        val prevIdx = (idx - 1 + currentQueue.size) % currentQueue.size
        playTrack(appCtx, currentQueue[prevIdx], currentQueue)
    }

    fun seekToSeconds(sec: Int) {
        try {
            mediaPlayer?.seekTo(sec * 1000)
            _currentPositionSec.value = sec
            appContext?.let { MusicPlaybackService.startOrUpdate(it) }
        } catch (_: Exception) { }
    }

    fun toggleFavorite(context: Context, track: MusicTrackDto) {
        val appCtx = context.applicationContext
        val newFav = !track.isFavorite
        // Optimistic local update + local persistence so favorites work offline & on Stage/Live
        val updatedPlaylists = _playlists.value.map { pl ->
            pl.copy(tracks = pl.tracks.map { tr ->
                val isSame = tr.id == track.id ||
                    (tr.title.equals(track.title, ignoreCase = true) && tr.artist.equals(track.artist, ignoreCase = true))
                if (isSame) tr.copy(isFavorite = newFav) else tr
            })
        }
        if (updatedPlaylists.isNotEmpty()) {
            applyPlaylistsToState(appCtx, updatedPlaylists)
            scope.launch(Dispatchers.IO) {
                savePlaylistsToLocalCache(appCtx, updatedPlaylists)
            }
        }

        scope.launch {
            try {
                val bodyStr = JSONObject().apply { put("is_favorite", newFav) }.toString()
                for (baseUrl in getCandidateBaseUrls()) {
                    for (token in getCandidateTokens()) {
                        try {
                            val req = Request.Builder()
                                .url("$baseUrl/funki/music/tracks/${track.id}/favorite")
                                .header("Authorization", "Bearer $token")
                                .header("Accept", "application/json")
                                .post(bodyStr.toRequestBody("application/json".toMediaType()))
                                .build()
                            val ok = withContext(Dispatchers.IO) {
                                fastMetaClient.newCall(req).execute().use { it.isSuccessful }
                            }
                            if (ok) {
                                return@launch
                            }
                        } catch (_: Exception) { }
                    }
                }
            } catch (_: Exception) { }
        }
    }

    fun createPlaylist(context: Context, name: String, onDone: (Boolean) -> Unit) {
        scope.launch {
            try {
                val baseUrl = getCandidateBaseUrls().first()
                val token = getCandidateTokens().first()
                val json = JSONObject().apply { put("name", name) }
                val req = Request.Builder()
                    .url("$baseUrl/funki/music/playlists")
                    .header("Authorization", "Bearer $token")
                    .header("Accept", "application/json")
                    .post(json.toString().toRequestBody("application/json".toMediaType()))
                    .build()
                val ok = withContext(Dispatchers.IO) {
                    httpClient.newCall(req).execute().use { it.isSuccessful }
                }
                if (ok) fetchPlaylists(context)
                onDone(ok)
            } catch (_: Exception) {
                onDone(false)
            }
        }
    }

    fun importYoutubeUrl(context: Context, url: String, onDone: (Boolean) -> Unit) {
        scope.launch {
            try {
                _importProgress.value = MusicImportProgress(
                    active = true,
                    percent = 2,
                    label = "Verbinde mit Musik-Importer...",
                    subLabel = "Analysiere Playlist / Song..."
                )
                val baseUrl = getCandidateBaseUrls().first()
                val token = getCandidateTokens().first()
                val pid = _activePlaylistId.value.takeIf { it != "all" && it != "favorites" }
                val json = JSONObject().apply {
                    put("url", url)
                    if (pid != null) put("playlist_id", pid)
                }
                val req = Request.Builder()
                    .url("$baseUrl/funki/music/import-url")
                    .header("Authorization", "Bearer $token")
                    .header("Accept", "application/json")
                    .post(json.toString().toRequestBody("application/json".toMediaType()))
                    .build()
                val ok = withContext(Dispatchers.IO) {
                    httpClient.newCall(req).execute().use { it.isSuccessful }
                }
                onDone(ok)
                if (ok) {
                    startImportProgressPolling(context.applicationContext)
                } else {
                    _importProgress.value = MusicImportProgress(active = false)
                }
            } catch (_: Exception) {
                _importProgress.value = MusicImportProgress(active = false)
                onDone(false)
            }
        }
    }

    fun startImportProgressPolling(context: Context) {
        if (importPollJob?.isActive == true) return
        importPollJob = scope.launch {
            var lastCount = -1
            try {
                while (isActive) {
                    delay(1200)
                    val baseUrl = getCandidateBaseUrls().first()
                    val token = getCandidateTokens().first()
                    val req = Request.Builder()
                        .url("$baseUrl/funki/music/import-status")
                        .header("Authorization", "Bearer $token")
                        .header("Accept", "application/json")
                        .get()
                        .build()

                    val statusObj = withContext(Dispatchers.IO) {
                        httpClient.newCall(req).execute().use { res ->
                            if (!res.isSuccessful) return@withContext null
                            val str = res.body?.string() ?: return@withContext null
                            if (!str.trimStart().startsWith("{")) return@withContext null
                            JSONObject(str)
                        }
                    } ?: continue

                    val active = statusObj.optBoolean("active", false)
                    val status = statusObj.optString("status", "idle")
                    val current = statusObj.optInt("current", 0)
                    val total = statusObj.optInt("total", 0)
                    val percent = statusObj.optInt("percent", 0)
                    val plName = statusObj.optString("playlist_name", "Playlist")
                    val curTitle = statusObj.optString("current_title", "")
                    val msg = statusObj.optString("message", "")

                    _importProgress.value = MusicImportProgress(
                        active = true,
                        percent = percent.coerceIn(0, 100),
                        label = if (total > 0) "$plName ($current/$total)" else plName,
                        subLabel = if (curTitle.isNotBlank()) "♪ $curTitle" else msg
                    )

                    if (current > lastCount && (current - lastCount >= 2 || status == "completed")) {
                        lastCount = current
                        fetchPlaylists(context)
                    }

                    if (!active || status == "completed" || status == "error" || status == "idle") {
                        if (status == "completed") {
                            fetchPlaylists(context)
                            delay(2800)
                        }
                        break
                    }
                }
            } catch (_: Exception) {
            } finally {
                _importProgress.value = MusicImportProgress(active = false)
            }
        }
    }

    fun cacheAllTracksOffline(context: Context) {
        val appCtx = context.applicationContext
        val list = _allTracks.value.ifEmpty { getFilteredTracks() }
        if (list.isEmpty()) return
        scope.launch {
            var count = 0
            for (tr in list) {
                if (!isTrackCached(appCtx, tr.id)) {
                    _statusText.value = "Offline-Sync (${count + 1}/${list.size})..."
                    ensureTrackCached(appCtx, tr)
                }
                count++
            }
            val cachedNow = withContext(Dispatchers.IO) { getCachedCount(appCtx) }
            _statusText.value = "⚡ $cachedNow/${list.size} Songs offline auf dem Handy verfügbar"
        }
    }

    private fun startProgressLoop(appCtx: Context, expectedTxId: Long) {
        progressJob?.cancel()
        progressJob = scope.launch {
            while (isActive && _isPlaying.value && transitionSeq.get() == expectedTxId) {
                try {
                    val mp = mediaPlayer
                    if (mp != null && !isTransitioningTrack) {
                        val durMs = mp.duration
                        val posMs = mp.currentPosition
                        if (mp.isPlaying) {
                            _currentPositionSec.value = (posMs / 1000).coerceAtLeast(0)
                        } else if (durMs > 3000 && posMs >= durMs - 800) {
                            // Fallback if native onCompletionListener was missed near EOF
                            delay(350)
                            if (!isTransitioningTrack && transitionSeq.get() == expectedTxId) {
                                Log.d(TAG, "EOF fallback in progressLoop triggered at pos=$posMs / dur=$durMs")
                                handleTrackFinished(appCtx, expectedTxId)
                            }
                            break
                        }
                    }
                } catch (_: Exception) { }
                delay(400)
            }
        }
    }
}
