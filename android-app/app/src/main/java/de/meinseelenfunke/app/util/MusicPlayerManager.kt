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
import kotlinx.coroutines.sync.Mutex
import kotlinx.coroutines.sync.withLock
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
    private const val FAV_SYNC_PLAYLIST_NAME = "__FUNKENWERK_FAV_SYNC__"
    private const val MAX_AUDIO_TRACK_BYTES = 40 * 1024 * 1024 // 40 MB

    @Volatile
    private var lastFavSyncTimestamp: Long = 0L
    private var favPollJob: Job? = null

    private val scope = CoroutineScope(SupervisorJob() + Dispatchers.Main)
    private val httpClient = OkHttpClient.Builder()
        .connectTimeout(4, TimeUnit.SECONDS)
        .readTimeout(45, TimeUnit.SECONDS)
        .followRedirects(false)
        .followSslRedirects(false)
        .build()
    private val fastMetaClient = OkHttpClient.Builder()
        .connectTimeout(3, TimeUnit.SECONDS)
        .readTimeout(8, TimeUnit.SECONDS)
        .followRedirects(false)
        .followSslRedirects(false)
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
    private val playerMutex = Mutex()

    @Volatile
    private var localDiskScannedOnce: Boolean = false

    @Volatile
    private var isTransitioningTrack: Boolean = false

    @Volatile
    private var isDuckedForAlarm: Boolean = false

    fun setAlarmDucking(duck: Boolean) {
        isDuckedForAlarm = duck
        val targetVol = if (duck) 0.12f else 1.0f
        try {
            mediaPlayer?.setVolume(targetVol, targetVol)
        } catch (_: Exception) { }
    }

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

    private fun getOrphanMetaCacheFile(context: Context): File {
        return File(getCacheDir(context), "orphan_meta_cache.json")
    }

    private data class OrphanTrackMeta(
        val title: String,
        val artist: String,
        val durationSeconds: Int,
        val fileSize: Long
    )

    private fun loadOrphanMetaMap(context: Context): MutableMap<String, OrphanTrackMeta> {
        val map = mutableMapOf<String, OrphanTrackMeta>()
        val f = getOrphanMetaCacheFile(context)
        if (!f.exists() || f.length() <= 2L) return map
        try {
            val root = JSONObject(f.readText(Charsets.UTF_8))
            val keys = root.keys()
            while (keys.hasNext()) {
                val k = keys.next()
                val obj = root.optJSONObject(k) ?: continue
                map[k] = OrphanTrackMeta(
                    title = obj.optString("t", ""),
                    artist = obj.optString("a", ""),
                    durationSeconds = obj.optInt("d", 0),
                    fileSize = obj.optLong("s", 0L)
                )
            }
        } catch (_: Exception) { }
        return map
    }

    private fun saveOrphanMetaMap(context: Context, map: Map<String, OrphanTrackMeta>) {
        if (map.isEmpty()) return
        try {
            val root = JSONObject()
            for ((k, v) in map) {
                root.put(
                    k,
                    JSONObject().apply {
                        put("t", v.title)
                        put("a", v.artist)
                        put("d", v.durationSeconds)
                        put("s", v.fileSize)
                    }
                )
            }
            getOrphanMetaCacheFile(context).writeText(root.toString(), Charsets.UTF_8)
        } catch (_: Exception) { }
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
        val persistable = playlists.filter { it.id != "local_offline_cache" }
        if (persistable.sumOf { it.tracks.size } == 0) return
        try {
            val file = getCatalogCacheFile(context)
            file.writeText(serializePlaylistsJson(persistable), Charsets.UTF_8)
        } catch (e: Exception) {
            Log.e(TAG, "Failed to save playlists to local cache", e)
        }
    }

    /**
     * Ultra-fast (~2ms) synchronous catalog bootstrap for cold-start Widget / Notification / Tab opens.
     * Reads playlists_cache.json + orphan_meta_cache.json without blocking on MediaMetadataRetriever or HTTP.
     */
    @Synchronized
    fun ensureCatalogLoadedFast(context: Context) {
        val appCtx = context.applicationContext
        appContext = appCtx
        ServiceLocator.ensureInitialized(appCtx)
        if (_allTracks.value.isNotEmpty()) return

        val catalogFile = getCatalogCacheFile(appCtx)
        val cachedPlaylists = if (catalogFile.exists() && catalogFile.length() > 10L) {
            try {
                parsePlaylistsJson(catalogFile.readText(Charsets.UTF_8)).toMutableList()
            } catch (_: Exception) {
                mutableListOf()
            }
        } else {
            mutableListOf()
        }

        val knownTrackIds = cachedPlaylists.flatMap { it.tracks }.map { it.id }.toSet()
        val orphanMetaMap = loadOrphanMetaMap(appCtx)
        val orphanFiles = getCacheDir(appCtx)
            .listFiles { _, name -> name.endsWith(".m4a") }
            ?.filter { it.length() > 10240L && it.nameWithoutExtension !in knownTrackIds }
            ?.sortedByDescending { it.lastModified() }
            ?: emptyList()

        if (orphanFiles.isNotEmpty()) {
            val orphanPlId = "local_offline_cache"
            val orphanTracks = orphanFiles.mapIndexed { idx, f ->
                val id = f.nameWithoutExtension
                val cachedMeta = orphanMetaMap[id]?.takeIf { it.fileSize == f.length() }
                MusicTrackDto(
                    id = id,
                    playlistId = orphanPlId,
                    title = cachedMeta?.title?.takeIf { it.isNotBlank() } ?: "Lokaler Song #${idx + 1}",
                    artist = cachedMeta?.artist?.takeIf { it.isNotBlank() } ?: "Offline-Speicher",
                    durationSeconds = cachedMeta?.durationSeconds ?: 0,
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

        if (cachedPlaylists.isNotEmpty()) {
            applyPlaylistsToState(appCtx, cachedPlaylists)
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

        // Ensure any local .m4a files on disk that aren't in playlists_cache.json are still visible,
        // persisting extracted metadata in orphan_meta_cache.json so MediaMetadataRetriever only runs once per new file.
        val knownTrackIds = cachedPlaylists.flatMap { it.tracks }.map { it.id }.toSet()
        val orphanMetaMap = loadOrphanMetaMap(context)
        var orphanMapDirty = false

        val orphanFiles = getCacheDir(context)
            .listFiles { _, name -> name.endsWith(".m4a") }
            ?.filter { it.length() > 10240L && it.nameWithoutExtension !in knownTrackIds && isValidAudioFile(it) }
            ?.sortedByDescending { it.lastModified() }
            ?: emptyList()

        if (orphanFiles.isNotEmpty()) {
            val orphanPlId = "local_offline_cache"
            val orphanTracks = orphanFiles.mapIndexed { idx, f ->
                val id = f.nameWithoutExtension
                val existingMeta = orphanMetaMap[id]?.takeIf { it.fileSize == f.length() }
                val resolvedMeta = if (existingMeta != null) {
                    existingMeta
                } else {
                    var title = "Lokaler Song #${idx + 1}"
                    var artist = "Offline-Speicher"
                    var durSec = 0
                    try {
                        val mmr = android.media.MediaMetadataRetriever()
                        try {
                            mmr.setDataSource(f.absolutePath)
                            val metaTitle = mmr.extractMetadata(android.media.MediaMetadataRetriever.METADATA_KEY_TITLE)
                            val metaArtist = mmr.extractMetadata(android.media.MediaMetadataRetriever.METADATA_KEY_ARTIST)
                            val metaDur = mmr.extractMetadata(android.media.MediaMetadataRetriever.METADATA_KEY_DURATION)?.toLongOrNull()
                            if (!metaTitle.isNullOrBlank()) title = metaTitle
                            if (!metaArtist.isNullOrBlank()) artist = metaArtist
                            if (metaDur != null && metaDur > 0) durSec = (metaDur / 1000L).toInt()
                        } finally {
                            try { mmr.release() } catch (_: Exception) { }
                        }
                    } catch (_: Exception) { }

                    OrphanTrackMeta(title, artist, durSec, f.length()).also {
                        orphanMetaMap[id] = it
                        orphanMapDirty = true
                    }
                }

                MusicTrackDto(
                    id = id,
                    playlistId = orphanPlId,
                    title = resolvedMeta.title,
                    artist = resolvedMeta.artist,
                    durationSeconds = resolvedMeta.durationSeconds,
                    fileSize = f.length(),
                    isFavorite = false,
                    position = idx + 1
                )
            }
            if (orphanMapDirty) {
                saveOrphanMetaMap(context, orphanMetaMap)
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

        localDiskScannedOnce = true
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
        val mergedTrackIds = merged.flatMap { it.tracks }.map { it.id }.toSet()
        for (lPl in local) {
            if (lPl.id == "local_offline_cache") {
                val stillOrphan = lPl.tracks.filter { it.id !in mergedTrackIds }
                if (stillOrphan.isNotEmpty()) {
                    merged.add(lPl.copy(tracks = stillOrphan))
                }
                continue
            }
            if (lPl.id !in remotePlIds && lPl.name.lowercase() !in remotePlNames && lPl.tracks.isNotEmpty()) {
                merged.add(lPl)
            }
        }
        return merged
    }

    private fun parseFavSyncDescription(desc: String?): Pair<Long, Set<String>> {
        if (desc.isNullOrBlank() || !desc.startsWith("fav_sync:v1:", ignoreCase = true)) {
            return 0L to emptySet()
        }
        val parts = desc.split(":")
        val ts = parts.getOrNull(2)?.toLongOrNull() ?: 0L
        val prefixes = parts.getOrNull(3)
            ?.split(",")
            ?.map { it.trim().lowercase() }
            ?.filter { it.isNotEmpty() }
            ?.toSet() ?: emptySet()
        return ts to prefixes
    }

    private fun applyPlaylistsToState(appCtx: Context, playlistsToApply: List<MusicPlaylistDto>) {
        val visible = playlistsToApply.filter { !it.name.equals(FAV_SYNC_PLAYLIST_NAME, ignoreCase = true) }
        if (visible.isEmpty()) return
        _playlists.value = visible
        val flat = visible.flatMap { it.tracks }
        _allTracks.value = flat

        val curActiveId = _activePlaylistId.value
        val activeExists = curActiveId == "all" || curActiveId == "favorites" || visible.any { it.id == curActiveId }
        if (curActiveId == "all" || !activeExists) {
            val favPl = visible.firstOrNull { it.name.contains("Lieblingssongs", ignoreCase = true) }
                ?: visible.first()
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
        ensurePeriodicFavSyncStarted(appCtx)
    }

    private fun ensurePeriodicFavSyncStarted(appCtx: Context) {
        if (favPollJob?.isActive == true) return
        favPollJob = scope.launch {
            while (isActive) {
                delay(14_000)
                try {
                    fetchPlaylists(appCtx)
                } catch (_: Exception) { }
            }
        }
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

    private fun hasKnownAudioContainerMagic(header: ByteArray, read: Int): Boolean {
        if (read < 8) return false
        val boxType = String(header, 4, 4, Charsets.ISO_8859_1)
        if (boxType == "ftyp" || boxType == "moov" || boxType == "styp" || boxType == "free" || boxType == "wide") {
            return true
        }
        val magic4 = String(header, 0, 4, Charsets.ISO_8859_1)
        if (magic4.startsWith("ID3") || magic4 == "OggS" || magic4 == "RIFF" || magic4 == "fLaC") {
            return true
        }
        val b0 = header[0].toInt() and 0xFF
        val b1 = header[1].toInt() and 0xFF
        if (b0 == 0xFF && (b1 and 0xE0) == 0xE0) return true // ADTS AAC / MP3 frame sync
        if (b0 == 0x1A && b1 == 0x45) return true // WebM / Matroska EBML
        return false
    }

    private fun isValidAudioFile(file: File): Boolean {
        if (!file.exists() || file.length() <= 10240L) return false
        return try {
            val header = ByteArray(16)
            val read = FileInputStream(file).use { it.read(header) }
            if (read < 8) return false
            if (hasKnownAudioContainerMagic(header, read)) return true
            val prefix = String(header, 0, read, Charsets.ISO_8859_1).trimStart()
            // Reject HTML login redirects or JSON error bodies that might have been cached
            !prefix.startsWith("<") && !prefix.startsWith("{")
        } catch (_: Exception) {
            false
        }
    }

    private fun safeTrackFileId(rawId: String): String {
        return rawId.replace(Regex("[^A-Za-z0-9_-]"), "_").take(80).ifBlank { "track" }
    }

    fun isTrackCached(context: Context, trackId: String): Boolean {
        val f = File(getCacheDir(context), "${safeTrackFileId(trackId)}.m4a")
        return isValidAudioFile(f)
    }

    fun getCachedCount(context: Context): Int {
        return getCacheDir(context).listFiles { _, name -> name.endsWith(".m4a") }?.count { it.length() > 10240L } ?: 0
    }

    /**
     * Returns all available music library tracks for the Alarm Sound Picker.
     * Automatically loads from local disk cache if MusicPlayerManager hasn't fetched yet.
     */
    fun getAvailableTracksForAlarm(context: Context): List<MusicTrackDto> {
        val appCtx = context.applicationContext
        val current = _allTracks.value
        if (current.isNotEmpty()) return current
        val localPlaylists = loadPlaylistsFromLocalCache(appCtx)
        if (localPlaylists.isNotEmpty()) {
            applyPlaylistsToState(appCtx, localPlaylists)
        }
        return _allTracks.value
    }

    /**
     * Resolves a local .m4a file for an alarm's custom Wecksound:
     * - "random_music": picks a random valid cached .m4a file from music_cache
     * - "<trackId>": returns music_cache/<trackId>.m4a if cached (or triggers background download & falls back to random cached track)
     */
    fun resolveAlarmTrackFile(context: Context, trackId: String?): File? {
        if (trackId.isNullOrBlank() || trackId == "standard") return null
        val appCtx = context.applicationContext
        val cacheDir = getCacheDir(appCtx)

        if (trackId == "random_music") {
            val validFiles = cacheDir
                .listFiles { _, name -> name.endsWith(".m4a") }
                ?.filter { isValidAudioFile(it) }
                ?: emptyList()
            return if (validFiles.isNotEmpty()) validFiles.random() else null
        }

        val exactFile = File(cacheDir, "${safeTrackFileId(trackId)}.m4a")
        if (isValidAudioFile(exactFile)) {
            return exactFile
        }

        // If the selected track isn't cached yet, trigger background caching and fall back to any cached song if available
        cacheTrackForAlarmAsync(appCtx, trackId)
        val fallbackFiles = cacheDir
            .listFiles { _, name -> name.endsWith(".m4a") }
            ?.filter { isValidAudioFile(it) }
            ?: emptyList()
        return fallbackFiles.firstOrNull()
    }

    /**
     * Ensures that a track selected as custom alarm sound is downloaded into local offline cache immediately.
     */
    fun cacheTrackForAlarmAsync(context: Context, trackId: String) {
        if (trackId.isBlank() || trackId == "standard" || trackId == "random_music") return
        val appCtx = context.applicationContext
        if (isTrackCached(appCtx, trackId)) return
        scope.launch(Dispatchers.IO) {
            try {
                val tracks = getAvailableTracksForAlarm(appCtx)
                val dto = tracks.firstOrNull { it.id == trackId }
                    ?: MusicTrackDto(
                        id = trackId,
                        playlistId = "all",
                        title = "Wecksound",
                        artist = "Funkenwerk",
                        durationSeconds = 0,
                        fileSize = 0L,
                        isFavorite = false,
                        position = 1
                    )
                ensureTrackCached(appCtx, dto)
            } catch (_: Exception) {
            }
        }
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
                    .setOnAudioFocusChangeListener { focusChange ->
                        when (focusChange) {
                            AudioManager.AUDIOFOCUS_LOSS_TRANSIENT_CAN_DUCK -> {
                                try { mediaPlayer?.setVolume(0.12f, 0.12f) } catch (_: Exception) { }
                            }
                            AudioManager.AUDIOFOCUS_GAIN -> {
                                if (!isDuckedForAlarm) {
                                    try { mediaPlayer?.setVolume(1.0f, 1.0f) } catch (_: Exception) { }
                                }
                            }
                        }
                    }
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
        return if (!primary.isNullOrBlank()) listOf(primary) else emptyList()
    }

    private fun getCandidateBaseUrls(): List<String> {
        val primary = try {
            ServiceLocator.getBaseUrl().trimEnd('/')
        } catch (_: Exception) {
            "https://stage.mein-seelenfunke.de/api"
        }
        return if (ServiceLocator.shouldAttachBearerToken(primary)) {
            listOf(primary)
        } else {
            emptyList()
        }
    }

    fun selectPlaylist(playlistId: String, context: Context? = null, autoPlayRandom: Boolean = true) {
        _activePlaylistId.value = playlistId
        val filtered = getFilteredTracks()
        if (filtered.isNotEmpty()) {
            currentQueue = filtered
            val appCtx = context?.applicationContext ?: appContext
            if (autoPlayRandom) {
                if (appCtx != null) {
                    val cachedPool = filtered.filter { isTrackCached(appCtx, it.id) }
                    val pool = if (cachedPool.isNotEmpty()) cachedPool else filtered
                    val candidates = if (pool.size > 1 && _currentTrack.value != null) {
                        pool.filter { it.id != _currentTrack.value?.id }.ifEmpty { pool }
                    } else {
                        pool
                    }
                    val randomTrack = candidates.random()
                    playTrack(appCtx, randomTrack, filtered)
                }
            } else if (!_isPlaying.value) {
                val cur = _currentTrack.value
                if (cur == null || filtered.none { it.id == cur.id }) {
                    _currentTrack.value = filtered.first()
                    _durationSec.value = filtered.first().durationSeconds
                    _currentPositionSec.value = 0
                    if (appCtx != null) {
                        MusicWidgetProvider.updateAllWidgets(appCtx)
                    }
                }
            }
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
        ServiceLocator.ensureInitialized(appCtx)
        ensureCatalogLoadedFast(appCtx)
        scope.launch {
            try {
                // 1. Instant local offline load (skip redundant disk scan on periodic polls once scanned)
                val localCached = if (localDiskScannedOnce && _playlists.value.isNotEmpty()) {
                    _playlists.value
                } else {
                    withContext(Dispatchers.IO) {
                        loadPlaylistsFromLocalCache(appCtx)
                    }
                }
                if (localCached.isNotEmpty() && _allTracks.value.isEmpty()) {
                    applyPlaylistsToState(appCtx, localCached)
                    val cachedFilesCount = withContext(Dispatchers.IO) { getCachedCount(appCtx) }
                    if (!_isPlaying.value) {
                        _statusText.value = "⚡ $cachedFilesCount Songs offline auf dem Handy bereit"
                    }
                } else if (!_isPlaying.value && _statusText.value == "Bereit für Sofort-Stream" && _allTracks.value.isNotEmpty()) {
                    val cachedFilesCount = withContext(Dispatchers.IO) { getCachedCount(appCtx) }
                    if (!_isPlaying.value) {
                        _statusText.value = "⚡ $cachedFilesCount Songs offline auf dem Handy bereit"
                    }
                }

                // 2. Smart network sync across both Local LAN & Stage servers (syncing both tracks & Favorites/Likes)
                data class RemoteFetchResult(
                    val bestTracksPlaylists: List<MusicPlaylistDto>,
                    val bestFavSyncTs: Long,
                    val bestFavSyncPrefixes: Set<String>,
                    val dbFavoriteIds: Set<String>
                )

                val remoteData = withContext(Dispatchers.IO) {
                    var bestList: List<MusicPlaylistDto> = emptyList()
                    var bestFavTs = 0L
                    var bestFavPrefixes: Set<String> = emptySet()
                    val dbFavs = mutableSetOf<String>()

                    for (baseUrl in getCandidateBaseUrls()) {
                        for (token in getCandidateTokens()) {
                            var gotResponseForUrl = false
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
                                            gotResponseForUrl = true
                                            val candidate = parsePlaylistsJson(s)
                                            val syncPl = candidate.firstOrNull { it.name.equals(FAV_SYNC_PLAYLIST_NAME, ignoreCase = true) }
                                            if (syncPl != null) {
                                                val (ts, pfx) = parseFavSyncDescription(syncPl.description)
                                                if (ts > bestFavTs) {
                                                    bestFavTs = ts
                                                    bestFavPrefixes = pfx
                                                }
                                            }
                                            val visible = candidate.filter { !it.name.equals(FAV_SYNC_PLAYLIST_NAME, ignoreCase = true) }
                                            for (pl in visible) {
                                                for (tr in pl.tracks) {
                                                    if (tr.isFavorite) {
                                                        dbFavs.add(tr.id.lowercase())
                                                        if (tr.id.length >= 8) dbFavs.add(tr.id.substring(0, 8).lowercase())
                                                    }
                                                }
                                            }
                                            if (bestList.isEmpty() && visible.sumOf { it.tracks.size } > 0) {
                                                bestList = visible
                                            }
                                        }
                                    }
                                }
                            } catch (_: Exception) { }
                            if (gotResponseForUrl) break
                        }
                    }
                    RemoteFetchResult(bestList, bestFavTs, bestFavPrefixes, dbFavs)
                }

                var finalPlaylists = if (remoteData.bestTracksPlaylists.isNotEmpty()) {
                    mergePlaylists(remoteData.bestTracksPlaylists, localCached)
                } else {
                    localCached
                }

                // Apply synchronized favorites from Windows Funkenwerk / Server
                if (remoteData.bestFavSyncTs > 0L && remoteData.bestFavSyncTs >= lastFavSyncTimestamp) {
                    lastFavSyncTimestamp = remoteData.bestFavSyncTs
                    finalPlaylists = finalPlaylists.map { pl ->
                        pl.copy(tracks = pl.tracks.map { tr ->
                            val pfx = if (tr.id.length >= 8) tr.id.substring(0, 8).lowercase() else tr.id.lowercase()
                            val fav = pfx in remoteData.bestFavSyncPrefixes || tr.id.lowercase() in remoteData.bestFavSyncPrefixes
                            tr.copy(isFavorite = fav)
                        })
                    }
                } else if (remoteData.dbFavoriteIds.isNotEmpty()) {
                    finalPlaylists = finalPlaylists.map { pl ->
                        pl.copy(tracks = pl.tracks.map { tr ->
                            val pfx = if (tr.id.length >= 8) tr.id.substring(0, 8).lowercase() else tr.id.lowercase()
                            val fav = pfx in remoteData.dbFavoriteIds || tr.id.lowercase() in remoteData.dbFavoriteIds
                            tr.copy(isFavorite = fav)
                        })
                    }
                }

                if (finalPlaylists.isNotEmpty()) {
                    withContext(Dispatchers.IO) {
                        savePlaylistsToLocalCache(appCtx, finalPlaylists)
                    }
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
        val safeId = safeTrackFileId(track.id)
        val file = File(cacheDir, "$safeId.m4a")
        if (isValidAudioFile(file)) {
            return@withContext file
        }
        if (file.exists()) {
            try { file.delete() } catch (_: Exception) { }
        }

        val tokens = getCandidateTokens()
        if (tokens.isEmpty()) return@withContext null

        val tmpFile = File(cacheDir, "$safeId.m4a.tmp")
        for (baseUrl in getCandidateBaseUrls()) {
            for (token in tokens) {
                try {
                    val req = Request.Builder()
                        .url("$baseUrl/funki/music/stream/$safeId")
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
                        val body = res.body ?: return@use
                        if (body.contentLength() > MAX_AUDIO_TRACK_BYTES) return@use

                        var totalRead = 0
                        var exceeded = false
                        body.byteStream().use { input ->
                            tmpFile.outputStream().use { output ->
                                val buf = ByteArray(16 * 1024)
                                while (true) {
                                    val r = input.read(buf)
                                    if (r <= 0) break
                                    totalRead += r
                                    if (totalRead > MAX_AUDIO_TRACK_BYTES) {
                                        exceeded = true
                                        break
                                    }
                                    output.write(buf, 0, r)
                                }
                            }
                        }
                        if (exceeded || totalRead <= 10240) {
                            tmpFile.delete()
                            return@use
                        }
                        if (isValidAudioFile(tmpFile)) {
                            if (file.exists()) file.delete()
                            if (tmpFile.renameTo(file)) {
                                return@withContext file
                            } else {
                                tmpFile.copyTo(file, overwrite = true)
                                tmpFile.delete()
                                return@withContext file
                            }
                        } else {
                            tmpFile.delete()
                        }
                    }
                } catch (e: Exception) {
                    Log.w(TAG, "Stream download attempt failed: ${e.message}")
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
        ServiceLocator.ensureInitialized(appCtx)
        ensureCatalogLoadedFast(appCtx)

        if (queue != null && queue.isNotEmpty()) {
            currentQueue = queue
        } else if (currentQueue.isEmpty() || currentQueue.none { it.id == track.id }) {
            val filtered = getFilteredTracks()
            currentQueue = if (filtered.any { it.id == track.id }) filtered else _allTracks.value
        }

        val txId = transitionSeq.incrementAndGet()
        isTransitioningTrack = true
        progressJob?.cancel()
        playJob?.cancel()

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
                    playerMutex.withLock {
                        if (transitionSeq.get() != txId) return@withLock null
                        var player = mediaPlayer ?: MediaPlayer().also { mediaPlayer = it }
                        try {
                            player.setOnCompletionListener(null)
                            player.setOnErrorListener(null)
                            player.reset()
                        } catch (_: Exception) {
                            try { player.release() } catch (_: Exception) { }
                            player = MediaPlayer().also { mediaPlayer = it }
                        }

                        try {
                            player.setWakeMode(appCtx, PowerManager.PARTIAL_WAKE_LOCK)
                        } catch (_: Exception) { }

                        val audioAttrs = AudioAttributes.Builder()
                            .setContentType(AudioAttributes.CONTENT_TYPE_MUSIC)
                            .setUsage(AudioAttributes.USAGE_MEDIA)
                            .build()
                        player.setAudioAttributes(audioAttrs)

                        try {
                            FileInputStream(localFile).use { fis ->
                                player.setDataSource(fis.fd, 0L, localFile.length())
                                player.isLooping = false
                                player.prepare()
                            }
                        } catch (fdErr: Exception) {
                            Log.w(TAG, "FD setDataSource failed for [${track.title}], retrying with file path: ${fdErr.message}")
                            try { player.release() } catch (_: Exception) { }
                            player = MediaPlayer().also { mediaPlayer = it }
                            try { player.setWakeMode(appCtx, PowerManager.PARTIAL_WAKE_LOCK) } catch (_: Exception) { }
                            player.setAudioAttributes(audioAttrs)
                            player.setDataSource(localFile.absolutePath)
                            player.isLooping = false
                            player.prepare()
                        }
                        player
                    }
                } ?: return@launch

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
                val initialVol = if (isDuckedForAlarm) 0.12f else 1.0f
                mp.setVolume(initialVol, initialVol)
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
                // Only remove localFile if it is genuinely corrupted (not a valid audio container)
                if (!isValidAudioFile(localFile)) {
                    try { localFile.delete() } catch (_: Exception) { }
                }
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
        ServiceLocator.ensureInitialized(appCtx)
        ensureCatalogLoadedFast(appCtx)

        val mp = mediaPlayer
        if (mp != null && !isTransitioningTrack) {
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
            // Promote ForegroundService immediately while still inside user gesture / PendingIntent broadcast window
            MusicPlaybackService.startOrUpdate(appCtx)
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
        ServiceLocator.ensureInitialized(appCtx)
        ensureCatalogLoadedFast(appCtx)

        if (currentQueue.isEmpty()) {
            currentQueue = getFilteredTracks().ifEmpty { _allTracks.value }
        }
        if (currentQueue.isEmpty()) {
            isTransitioningTrack = false
            MusicPlaybackService.startOrUpdate(appCtx)
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
        ServiceLocator.ensureInitialized(appCtx)
        ensureCatalogLoadedFast(appCtx)

        val mp = mediaPlayer
        try {
            if (mp != null && !isTransitioningTrack && mp.currentPosition > 3500) {
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
        val ts = System.currentTimeMillis() / 1000L
        lastFavSyncTimestamp = ts

        // Optimistic local update + local persistence so favorites work offline & on Stage/Live
        val updatedPlaylists = _playlists.value.map { pl ->
            pl.copy(tracks = pl.tracks.map { tr ->
                val isSame = tr.id.equals(track.id, ignoreCase = true) ||
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

        val favPrefixes = _allTracks.value
            .filter { it.isFavorite && it.id.isNotBlank() }
            .map { if (it.id.length >= 8) it.id.substring(0, 8).lowercase() else it.id.lowercase() }
            .distinct()
            .sorted()
            .joinToString(",")
        val syncDesc = "fav_sync:v1:$ts:$favPrefixes"

        scope.launch(Dispatchers.IO) {
            try {
                val bodyStr = JSONObject().apply {
                    put("is_favorite", newFav)
                    put("title", track.title)
                    put("artist", track.artist)
                }.toString()

                for (baseUrl in getCandidateBaseUrls()) {
                    for (token in getCandidateTokens()) {
                        var serverReachable = false
                        try {
                            val req = Request.Builder()
                                .url("$baseUrl/funki/music/tracks/${track.id}/favorite")
                                .header("Authorization", "Bearer $token")
                                .header("Accept", "application/json")
                                .post(bodyStr.toRequestBody("application/json".toMediaType()))
                                .build()
                            fastMetaClient.newCall(req).execute().use { res ->
                                if (res.code != 401 && res.code != 403) {
                                    serverReachable = true
                                }
                            }
                        } catch (_: Exception) { }

                        try {
                            upsertFavSyncPlaylistOnServer(baseUrl, token, syncDesc)
                            serverReachable = true
                        } catch (_: Exception) { }

                        if (serverReachable) break
                    }
                }
            } catch (_: Exception) { }
        }
    }

    private fun upsertFavSyncPlaylistOnServer(baseUrl: String, token: String, syncDesc: String) {
        val listReq = Request.Builder()
            .url("$baseUrl/funki/music/playlists")
            .header("Authorization", "Bearer $token")
            .header("Accept", "application/json")
            .get()
            .build()
        var existingId: String? = null
        fastMetaClient.newCall(listReq).execute().use { res ->
            if (!res.isSuccessful) return
            val s = res.body?.string() ?: return
            if (!s.trimStart().startsWith("{")) return
            val parsed = parsePlaylistsJson(s)
            existingId = parsed.firstOrNull { it.name.equals(FAV_SYNC_PLAYLIST_NAME, ignoreCase = true) }?.id
        }

        val payload = JSONObject().apply {
            put("name", FAV_SYNC_PLAYLIST_NAME)
            put("description", syncDesc)
        }.toString().toRequestBody("application/json".toMediaType())

        val writeReq = if (!existingId.isNullOrBlank()) {
            Request.Builder()
                .url("$baseUrl/funki/music/playlists/$existingId")
                .header("Authorization", "Bearer $token")
                .header("Accept", "application/json")
                .put(payload)
                .build()
        } else {
            Request.Builder()
                .url("$baseUrl/funki/music/playlists")
                .header("Authorization", "Bearer $token")
                .header("Accept", "application/json")
                .post(payload)
                .build()
        }
        fastMetaClient.newCall(writeReq).execute().close()
    }

    fun createPlaylist(context: Context, name: String, onDone: (Boolean) -> Unit) {
        scope.launch {
            try {
                val baseUrl = getCandidateBaseUrls().firstOrNull() ?: run { onDone(false); return@launch }
                val token = getCandidateTokens().firstOrNull() ?: run { onDone(false); return@launch }
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
                val baseUrl = getCandidateBaseUrls().firstOrNull() ?: run {
                    _importProgress.value = MusicImportProgress(active = false)
                    onDone(false)
                    return@launch
                }
                val token = getCandidateTokens().firstOrNull() ?: run {
                    _importProgress.value = MusicImportProgress(active = false)
                    onDone(false)
                    return@launch
                }
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
                    val baseUrl = getCandidateBaseUrls().firstOrNull() ?: break
                    val token = getCandidateTokens().firstOrNull() ?: break
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

    fun stopAndClearSession(context: Context) {
        val appCtx = context.applicationContext
        favPollJob?.cancel()
        backgroundSyncJob?.cancel()
        importPollJob?.cancel()
        preCacheJob?.cancel()
        progressJob?.cancel()
        playJob?.cancel()
        try { mediaPlayer?.stop() } catch (_: Exception) {}
        try { mediaPlayer?.release() } catch (_: Exception) {}
        mediaPlayer = null
        _isPlaying.value = false
        _currentTrack.value = null
        _playlists.value = emptyList()
        _allTracks.value = emptyList()
        _currentPositionSec.value = 0
        _durationSec.value = 0
        _statusText.value = "Bereit für Sofort-Stream"
        currentQueue = emptyList()
        localDiskScannedOnce = false
        try { getCatalogCacheFile(appCtx).delete() } catch (_: Exception) {}
        try { getOrphanMetaCacheFile(appCtx).delete() } catch (_: Exception) {}
        try {
            appCtx.getSharedPreferences("music_widget_prefs", Context.MODE_PRIVATE).edit().clear().apply()
        } catch (_: Exception) {}
        try { if (wakeLock?.isHeld == true) wakeLock?.release() } catch (_: Exception) {}
        try {
            MusicPlaybackService.stop(appCtx)
        } catch (_: Exception) {}
        try {
            appCtx.stopService(android.content.Intent(appCtx, MusicPlaybackService::class.java))
        } catch (_: Exception) {}
    }
}
