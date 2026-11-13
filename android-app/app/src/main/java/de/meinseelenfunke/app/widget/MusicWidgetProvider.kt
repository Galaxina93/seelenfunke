package de.meinseelenfunke.app.widget

import android.app.PendingIntent
import android.appwidget.AppWidgetManager
import android.appwidget.AppWidgetProvider
import android.content.ComponentName
import android.content.Context
import android.content.Intent
import android.widget.RemoteViews
import de.meinseelenfunke.app.MainActivity
import de.meinseelenfunke.app.R
import de.meinseelenfunke.app.util.MusicPlayerManager

class MusicWidgetProvider : AppWidgetProvider() {

    override fun onUpdate(context: Context, appWidgetManager: AppWidgetManager, appWidgetIds: IntArray) {
        if (MusicPlayerManager.allTracks.value.isEmpty()) {
            MusicPlayerManager.fetchPlaylists(context)
        }
        for (appWidgetId in appWidgetIds) {
            updateAppWidget(context, appWidgetManager, appWidgetId)
        }
        super.onUpdate(context, appWidgetManager, appWidgetIds)
    }

    override fun onReceive(context: Context, intent: Intent) {
        val action = intent.action
        if (action != null && action.startsWith("de.meinseelenfunke.app.widget.") &&
            !de.meinseelenfunke.app.util.SecurityIntentHelper.isTrustedInternalIntent(context, intent)
        ) {
            return
        }
        super.onReceive(context, intent)
        de.meinseelenfunke.app.di.ServiceLocator.ensureInitialized(context.applicationContext)
        when (action) {
            ACTION_PLAY_PAUSE -> {
                MusicPlayerManager.togglePlayPause(context)
            }
            ACTION_NEXT -> {
                MusicPlayerManager.playNext(context)
            }
            ACTION_PREV -> {
                MusicPlayerManager.playPrevious(context)
            }
            ACTION_SHUFFLE -> {
                MusicPlayerManager.toggleShuffle(context)
            }
            ACTION_REFRESH -> {
                MusicPlayerManager.fetchPlaylists(context)
            }
        }
        updateAllWidgets(context)
    }

    companion object {
        const val ACTION_PLAY_PAUSE = "de.meinseelenfunke.app.widget.ACTION_MUSIC_PLAY_PAUSE"
        const val ACTION_NEXT = "de.meinseelenfunke.app.widget.ACTION_MUSIC_NEXT"
        const val ACTION_PREV = "de.meinseelenfunke.app.widget.ACTION_MUSIC_PREV"
        const val ACTION_SHUFFLE = "de.meinseelenfunke.app.widget.ACTION_MUSIC_SHUFFLE"
        const val ACTION_REFRESH = "de.meinseelenfunke.app.widget.ACTION_MUSIC_REFRESH"

        fun updateAllWidgets(context: Context) {
            val appWidgetManager = AppWidgetManager.getInstance(context)
            val componentName = ComponentName(context, MusicWidgetProvider::class.java)
            val appWidgetIds = appWidgetManager.getAppWidgetIds(componentName)
            for (id in appWidgetIds) {
                updateAppWidget(context, appWidgetManager, id)
            }
            MasterWidgetProvider.updateAllWidgets(context)
        }

        fun updateAppWidget(context: Context, appWidgetManager: AppWidgetManager, appWidgetId: Int) {
            val views = RemoteViews(context.packageName, R.layout.widget_music_layout)

            val track = MusicPlayerManager.currentTrack.value
            val isPlaying = MusicPlayerManager.isPlaying.value
            val isShuffle = MusicPlayerManager.isShuffle.value
            val totalSongs = MusicPlayerManager.allTracks.value.size

            views.setTextViewText(
                R.id.music_widget_playlist_subtitle,
                if (totalSongs > 0) "Lieblingssongs · $totalSongs Titel" else "Lieblingssongs · Synchronisiert"
            )

            if (track != null) {
                views.setTextViewText(R.id.music_widget_title, track.title)
                views.setTextViewText(
                    R.id.music_widget_artist,
                    track.artist.ifBlank { "Funkenwerk Audio" }
                )
            } else {
                views.setTextViewText(R.id.music_widget_title, "Funkenwerk Soundplayer bereit")
                views.setTextViewText(R.id.music_widget_artist, "Tippe auf ▶ für Sofort-Wiedergabe")
            }

            views.setTextViewText(R.id.btn_music_widget_play_pause, if (isPlaying) "❚❚" else "▶\uFE0E")
            views.setInt(
                R.id.btn_music_widget_shuffle,
                "setColorFilter",
                if (isShuffle) 0xFFC5A059.toInt() else 0x99C5A059.toInt()
            )

            // Broadcast PendingIntents for Playback Controls
            views.setOnClickPendingIntent(
                R.id.btn_music_widget_play_pause,
                getBroadcastPendingIntent(context, ACTION_PLAY_PAUSE, appWidgetId * 10 + 1)
            )
            views.setOnClickPendingIntent(
                R.id.btn_music_widget_next,
                getBroadcastPendingIntent(context, ACTION_NEXT, appWidgetId * 10 + 2)
            )
            views.setOnClickPendingIntent(
                R.id.btn_music_widget_prev,
                getBroadcastPendingIntent(context, ACTION_PREV, appWidgetId * 10 + 3)
            )
            views.setOnClickPendingIntent(
                R.id.btn_music_widget_shuffle,
                getBroadcastPendingIntent(context, ACTION_SHUFFLE, appWidgetId * 10 + 4)
            )
            views.setOnClickPendingIntent(
                R.id.btn_music_widget_refresh,
                getBroadcastPendingIntent(context, ACTION_REFRESH, appWidgetId * 10 + 5)
            )

            // Open App into Music Tab (Organizer -> SubTab 5)
            val openAppIntent = Intent(context, MainActivity::class.java).apply {
                putExtra("open_tab", 2)
                putExtra("open_subtab", 5)
                flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP
                de.meinseelenfunke.app.util.SecurityIntentHelper.signIntent(context, this)
            }
            val openPending = PendingIntent.getActivity(
                context,
                appWidgetId * 10 + 6,
                openAppIntent,
                PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
            )
            views.setOnClickPendingIntent(R.id.music_widget_track_area, openPending)
            views.setOnClickPendingIntent(R.id.music_widget_header_click, openPending)
            views.setOnClickPendingIntent(R.id.btn_music_widget_open, openPending)

            appWidgetManager.updateAppWidget(appWidgetId, views)
        }

        private fun getBroadcastPendingIntent(context: Context, action: String, reqCode: Int): PendingIntent {
            val intent = Intent(context, MusicWidgetProvider::class.java).apply {
                this.action = action
                de.meinseelenfunke.app.util.SecurityIntentHelper.signIntent(context, this)
            }
            return PendingIntent.getBroadcast(
                context,
                reqCode,
                intent,
                PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
            )
        }
    }
}
