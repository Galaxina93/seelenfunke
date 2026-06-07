package de.meinseelenfunke.app

import android.app.Application
import de.meinseelenfunke.app.di.ServiceLocator
import de.meinseelenfunke.app.util.SoundManager

class SeelenfunkeApp : Application() {
    override fun onCreate() {
        super.onCreate()
        // Initialize the dependency injection service locator
        ServiceLocator.init(this)
        // Initialize the sound manager helper
        SoundManager.init(this)
        // Initialize the real-time Email WebSocket Client
        de.meinseelenfunke.app.data.EmailWebSocketClient.init(this)

        // Auto-start background wake-word listening service if enabled
        if (ServiceLocator.isWakeWordEnabled()) {
            val intent = android.content.Intent(this, de.meinseelenfunke.app.services.WakeWordService::class.java)
            try {
                if (android.os.Build.VERSION.SDK_INT >= android.os.Build.VERSION_CODES.O) {
                    startForegroundService(intent)
                } else {
                    startService(intent)
                }
            } catch (e: Exception) {
                e.printStackTrace()
            }
        }
    }
}
