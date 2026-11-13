package de.meinseelenfunke.app.services

import android.app.Notification
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.app.Service
import android.content.Context
import android.content.Intent
import android.content.pm.ServiceInfo
import android.graphics.Bitmap
import android.graphics.Canvas
import android.graphics.LinearGradient
import android.graphics.Paint
import android.graphics.RectF
import android.graphics.Shader
import android.media.MediaMetadata
import android.media.session.MediaSession
import android.media.session.PlaybackState
import android.net.wifi.WifiManager
import android.os.Build
import android.os.Bundle
import android.os.IBinder
import android.os.PowerManager
import android.os.SystemClock
import android.util.Log
import de.meinseelenfunke.app.MainActivity
import de.meinseelenfunke.app.R
import de.meinseelenfunke.app.util.MusicPlayerManager
import de.meinseelenfunke.app.widget.MusicWidgetProvider

class MusicPlaybackService : Service() {

    private var wakeLock: PowerManager.WakeLock? = null
    private var wifiLock: WifiManager.WifiLock? = null
    private var mediaSession: MediaSession? = null
    private var studioCoverBitmap: Bitmap? = null

    companion object {
        private const val TAG = "MusicPlaybackService"
        private const val CHANNEL_ID = "funkenwerk_media_session_channel_v2"
        private const val NOTIFICATION_ID = 1008

        const val ACTION_START_OR_UPDATE = "de.meinseelenfunke.app.services.MUSIC_START_OR_UPDATE"
        const val ACTION_STOP = "de.meinseelenfunke.app.services.MUSIC_STOP"
        const val ACTION_PLAY_PAUSE = "de.meinseelenfunke.app.services.MUSIC_PLAY_PAUSE"
        const val ACTION_NEXT = "de.meinseelenfunke.app.services.MUSIC_NEXT"
        const val ACTION_PREV = "de.meinseelenfunke.app.services.MUSIC_PREV"
        const val ACTION_SHUFFLE = "de.meinseelenfunke.app.services.MUSIC_SHUFFLE"
        private const val ACTION_CUSTOM_SHUFFLE = "de.meinseelenfunke.app.services.CUSTOM_SHUFFLE"

        fun startOrUpdate(context: Context) {
            try {
                val appCtx = context.applicationContext
                val intent = Intent(appCtx, MusicPlaybackService::class.java).apply {
                    action = ACTION_START_OR_UPDATE
                }
                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                    appCtx.startForegroundService(intent)
                } else {
                    appCtx.startService(intent)
                }
            } catch (e: Exception) {
                Log.w(TAG, "Could not start MusicPlaybackService: ${e.message}")
            }
        }

        fun stop(context: Context) {
            try {
                val appCtx = context.applicationContext
                val intent = Intent(appCtx, MusicPlaybackService::class.java).apply {
                    action = ACTION_STOP
                }
                appCtx.stopService(intent)
            } catch (_: Exception) { }
        }
    }

    override fun onCreate() {
        super.onCreate()
        createChannel()
        acquireLocks()
        initMediaSession()
    }

    private fun initMediaSession() {
        if (mediaSession != null) return
        try {
            val session = MediaSession(this, "FunkenwerkMediaSession")
            session.setSessionActivity(createOpenMusicTabPendingIntent())
            session.setCallback(object : MediaSession.Callback() {
                override fun onPlay() {
                    if (!MusicPlayerManager.isPlaying.value) {
                        MusicPlayerManager.togglePlayPause(applicationContext)
                    } else {
                        updateSessionAndNotification()
                    }
                }

                override fun onPause() {
                    if (MusicPlayerManager.isPlaying.value) {
                        MusicPlayerManager.togglePlayPause(applicationContext)
                    } else {
                        updateSessionAndNotification()
                    }
                }

                override fun onSkipToNext() {
                    MusicPlayerManager.playNext(applicationContext)
                }

                override fun onSkipToPrevious() {
                    MusicPlayerManager.playPrevious(applicationContext)
                }

                override fun onSeekTo(pos: Long) {
                    val targetSec = (pos / 1000L).toInt().coerceAtLeast(0)
                    MusicPlayerManager.seekToSeconds(targetSec)
                    updateSessionAndNotification()
                }

                override fun onCustomAction(action: String, extras: Bundle?) {
                    if (action == ACTION_CUSTOM_SHUFFLE) {
                        MusicPlayerManager.toggleShuffle(applicationContext)
                        updateSessionAndNotification()
                    }
                }

                override fun onStop() {
                    if (MusicPlayerManager.isPlaying.value) {
                        MusicPlayerManager.togglePlayPause(applicationContext)
                    }
                    updateSessionAndNotification()
                }
            })
            session.isActive = true
            mediaSession = session
        } catch (e: Exception) {
            Log.e(TAG, "Failed to initialize MediaSession", e)
        }
    }

    override fun onStartCommand(intent: Intent?, flags: Int, startId: Int): Int {
        when (intent?.action) {
            ACTION_STOP -> {
                releaseLocks()
                try { mediaSession?.isActive = false } catch (_: Exception) { }
                stopForeground(STOP_FOREGROUND_REMOVE)
                stopSelf()
                return START_NOT_STICKY
            }
            ACTION_PLAY_PAUSE -> {
                MusicPlayerManager.togglePlayPause(applicationContext)
            }
            ACTION_NEXT -> {
                MusicPlayerManager.playNext(applicationContext)
            }
            ACTION_PREV -> {
                MusicPlayerManager.playPrevious(applicationContext)
            }
            ACTION_SHUFFLE -> {
                MusicPlayerManager.toggleShuffle(applicationContext)
            }
        }

        acquireLocks()
        initMediaSession()
        val notification = updateSessionAndNotification()

        try {
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {
                startForeground(
                    NOTIFICATION_ID,
                    notification,
                    ServiceInfo.FOREGROUND_SERVICE_TYPE_MEDIA_PLAYBACK
                )
            } else {
                startForeground(NOTIFICATION_ID, notification)
            }
        } catch (e: Exception) {
            Log.w(TAG, "startForeground failed, falling back to NotificationManager.notify: ${e.message}")
            try {
                val nm = getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
                nm.notify(NOTIFICATION_ID, notification)
            } catch (_: Exception) { }
        }

        return START_STICKY
    }

    private fun updateSessionAndNotification(): Notification {
        val track = MusicPlayerManager.currentTrack.value
        val isPlaying = MusicPlayerManager.isPlaying.value
        val isShuffle = MusicPlayerManager.isShuffle.value
        val title = track?.title ?: "Funkenwerk Soundplayer"
        val artist = track?.artist?.ifBlank { "Funkenwerk Audio" } ?: "Bereit für Sofort-Wiedergabe"
        val durationSec = MusicPlayerManager.durationSec.value.coerceAtLeast(track?.durationSeconds ?: 0)
        val durationMs = if (durationSec > 0) durationSec * 1000L else -1L
        val positionMs = (MusicPlayerManager.currentPositionSec.value.coerceAtLeast(0)) * 1000L
        val coverBmp = getOrCreateStudioCoverBitmap()

        mediaSession?.let { session ->
            try {
                session.isActive = true

                val metaBuilder = MediaMetadata.Builder()
                    .putString(MediaMetadata.METADATA_KEY_TITLE, title)
                    .putString(MediaMetadata.METADATA_KEY_ARTIST, artist)
                    .putString(MediaMetadata.METADATA_KEY_ALBUM, "Funkenwerk Studio")
                    .putBitmap(MediaMetadata.METADATA_KEY_ALBUM_ART, coverBmp)
                    .putBitmap(MediaMetadata.METADATA_KEY_DISPLAY_ICON, coverBmp)

                if (durationMs > 0) {
                    metaBuilder.putLong(MediaMetadata.METADATA_KEY_DURATION, durationMs)
                }
                session.setMetadata(metaBuilder.build())

                val stateActions = PlaybackState.ACTION_PLAY or
                        PlaybackState.ACTION_PAUSE or
                        PlaybackState.ACTION_PLAY_PAUSE or
                        PlaybackState.ACTION_SKIP_TO_NEXT or
                        PlaybackState.ACTION_SKIP_TO_PREVIOUS or
                        PlaybackState.ACTION_SEEK_TO or
                        PlaybackState.ACTION_STOP

                val stateBuilder = PlaybackState.Builder()
                    .setActions(stateActions)
                    .setState(
                        if (isPlaying) PlaybackState.STATE_PLAYING else PlaybackState.STATE_PAUSED,
                        positionMs,
                        if (isPlaying) 1.0f else 0f,
                        SystemClock.elapsedRealtime()
                    )
                    .addCustomAction(
                        PlaybackState.CustomAction.Builder(
                            ACTION_CUSTOM_SHUFFLE,
                            if (isShuffle) "Zufall AN" else "Zufall AUS",
                            android.R.drawable.ic_menu_rotate
                        ).build()
                    )

                session.setPlaybackState(stateBuilder.build())
            } catch (e: Exception) {
                Log.w(TAG, "Error updating MediaSession metadata/state: ${e.message}")
            }
        }

        val notification = buildMediaNotification(title, artist, isPlaying, coverBmp)
        try {
            val nm = getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
            nm.notify(NOTIFICATION_ID, notification)
        } catch (_: Exception) { }

        return notification
    }

    private fun createOpenMusicTabPendingIntent(): PendingIntent {
        val openIntent = Intent(this, MainActivity::class.java).apply {
            putExtra("open_tab", 2)
            putExtra("open_subtab", 5)
            flags = Intent.FLAG_ACTIVITY_SINGLE_TOP or Intent.FLAG_ACTIVITY_CLEAR_TOP
            de.meinseelenfunke.app.util.SecurityIntentHelper.signIntent(this@MusicPlaybackService, this)
        }
        return PendingIntent.getActivity(
            this,
            901,
            openIntent,
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
        )
    }

    private fun createServiceActionPendingIntent(action: String, reqCode: Int): PendingIntent {
        val intent = Intent(this, MusicPlaybackService::class.java).apply {
            this.action = action
        }
        return if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            PendingIntent.getForegroundService(
                this,
                reqCode,
                intent,
                PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
            )
        } else {
            PendingIntent.getService(
                this,
                reqCode,
                intent,
                PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
            )
        }
    }

    private fun buildMediaNotification(
        title: String,
        artist: String,
        isPlaying: Boolean,
        coverBmp: Bitmap
    ): Notification {
        val contentPi = createOpenMusicTabPendingIntent()
        val prevPi = createServiceActionPendingIntent(ACTION_PREV, 902)
        val playPausePi = createServiceActionPendingIntent(ACTION_PLAY_PAUSE, 903)
        val nextPi = createServiceActionPendingIntent(ACTION_NEXT, 904)

        val prevAction = Notification.Action.Builder(
            android.R.drawable.ic_media_previous,
            "Zurück",
            prevPi
        ).build()

        val playPauseAction = Notification.Action.Builder(
            if (isPlaying) android.R.drawable.ic_media_pause else android.R.drawable.ic_media_play,
            if (isPlaying) "Pause" else "Abspielen",
            playPausePi
        ).build()

        val nextAction = Notification.Action.Builder(
            android.R.drawable.ic_media_next,
            "Weiter",
            nextPi
        ).build()

        val mediaStyle = Notification.MediaStyle()
            .setShowActionsInCompactView(0, 1, 2)
        mediaSession?.sessionToken?.let { token ->
            mediaStyle.setMediaSession(token)
        }

        val builder = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            Notification.Builder(this, CHANNEL_ID)
        } else {
            @Suppress("DEPRECATION")
            Notification.Builder(this)
        }

        return builder
            .setStyle(mediaStyle)
            .setSmallIcon(R.mipmap.ic_launcher)
            .setLargeIcon(coverBmp)
            .setContentTitle(title)
            .setContentText(artist)
            .setSubText("Funkenwerk")
            .setContentIntent(contentPi)
            .setOngoing(isPlaying)
            .setOnlyAlertOnce(true)
            .setVisibility(Notification.VISIBILITY_PUBLIC)
            .setColor(0xFF141720.toInt())
            .setColorized(true)
            .addAction(prevAction)
            .addAction(playPauseAction)
            .addAction(nextAction)
            .build()
    }

    private fun getOrCreateStudioCoverBitmap(): Bitmap {
        studioCoverBitmap?.let { if (!it.isRecycled) return it }

        val size = 512
        val bmp = Bitmap.createBitmap(size, size, Bitmap.Config.ARGB_8888)
        val canvas = Canvas(bmp)
        val paint = Paint(Paint.ANTI_ALIAS_FLAG)

        // Deep Matte Obsidian-Gold Studio Background
        paint.shader = LinearGradient(
            0f, 0f, size.toFloat(), size.toFloat(),
            intArrayOf(0xFF12151E.toInt(), 0xFF1A1710.toInt(), 0xFF0B0D12.toInt()),
            floatArrayOf(0f, 0.55f, 1f),
            Shader.TileMode.CLAMP
        )
        canvas.drawRect(0f, 0f, size.toFloat(), size.toFloat(), paint)
        paint.shader = null

        val cx = size / 2f
        val cy = size / 2f

        // Subtle Vinyl Grooves
        paint.style = Paint.Style.STROKE
        paint.strokeWidth = 2.5f
        paint.color = 0x26C5A059
        canvas.drawCircle(cx, cy, 195f, paint)
        canvas.drawCircle(cx, cy, 160f, paint)
        canvas.drawCircle(cx, cy, 125f, paint)

        // Outer Gold Ring
        paint.strokeWidth = 5f
        paint.color = 0xFFC5A059.toInt()
        canvas.drawCircle(cx, cy, 88f, paint)

        // Inner Gold Disc
        paint.style = Paint.Style.FILL
        paint.color = 0x22C5A059
        canvas.drawCircle(cx, cy, 86f, paint)

        // Stylized Equalizer Bars inside the Gold Disc
        paint.color = 0xFFC5A059.toInt()
        val barWidth = 14f
        val gap = 10f
        val heights = floatArrayOf(42f, 76f, 96f, 64f, 46f)
        val totalW = heights.size * barWidth + (heights.size - 1) * gap
        var startX = cx - totalW / 2f
        for (h in heights) {
            val rect = RectF(startX, cy - h / 2f, startX + barWidth, cy + h / 2f)
            canvas.drawRoundRect(rect, 7f, 7f, paint)
            startX += barWidth + gap
        }

        studioCoverBitmap = bmp
        return bmp
    }

    private fun acquireLocks() {
        try {
            if (wakeLock == null) {
                val pm = getSystemService(Context.POWER_SERVICE) as PowerManager
                wakeLock = pm.newWakeLock(PowerManager.PARTIAL_WAKE_LOCK, "Funkenwerk::MusicWakeLock").apply {
                    setReferenceCounted(false)
                }
            }
            if (wakeLock?.isHeld == false) {
                wakeLock?.acquire(4 * 60 * 60 * 1000L)
            }
        } catch (_: Exception) { }

        try {
            if (wifiLock == null) {
                val wm = applicationContext.getSystemService(Context.WIFI_SERVICE) as WifiManager
                @Suppress("DEPRECATION")
                val lockMode = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {
                    WifiManager.WIFI_MODE_FULL_LOW_LATENCY
                } else {
                    WifiManager.WIFI_MODE_FULL_HIGH_PERF
                }
                wifiLock = wm.createWifiLock(lockMode, "Funkenwerk::MusicWifiLock").apply {
                    setReferenceCounted(false)
                }
            }
            if (wifiLock?.isHeld == false) {
                wifiLock?.acquire()
            }
        } catch (_: Exception) { }
    }

    private fun releaseLocks() {
        try {
            if (wakeLock?.isHeld == true) wakeLock?.release()
        } catch (_: Exception) { }
        try {
            if (wifiLock?.isHeld == true) wifiLock?.release()
        } catch (_: Exception) { }
    }

    private fun createChannel() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            val nm = getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
            val channel = NotificationChannel(
                CHANNEL_ID,
                "Funkenwerk Musikplayer",
                NotificationManager.IMPORTANCE_LOW
            ).apply {
                description = "Mediensteuerung in der Benachrichtigungsleiste & auf dem Sperrbildschirm"
                setShowBadge(false)
                setSound(null, null)
                lockscreenVisibility = Notification.VISIBILITY_PUBLIC
            }
            nm.createNotificationChannel(channel)
        }
    }

    override fun onTaskRemoved(rootIntent: Intent?) {
        // Keep music playing and keep the media player in the notification shade when app is minimized/swiped
        if (MusicPlayerManager.isPlaying.value) {
            updateSessionAndNotification()
        }
        super.onTaskRemoved(rootIntent)
    }

    override fun onDestroy() {
        releaseLocks()
        try {
            mediaSession?.isActive = false
            mediaSession?.release()
            mediaSession = null
        } catch (_: Exception) { }
        super.onDestroy()
    }

    override fun onBind(intent: Intent?): IBinder? = null
}
