package de.meinseelenfunke.app

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import de.meinseelenfunke.app.ui.navigation.AppNavigation
import de.meinseelenfunke.app.ui.theme.SeelenfunkeTheme
import de.meinseelenfunke.app.util.SoundManager

class MainActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        enableEdgeToEdge()
        
        SoundManager.playSound(R.raw.open_project_brain)

        setContent {
            SeelenfunkeTheme {
                AppNavigation()
            }
        }

        // Fetch and register FCM token
        com.google.firebase.messaging.FirebaseMessaging.getInstance().token.addOnCompleteListener { task ->
            if (task.isSuccessful) {
                val token = task.result
                de.meinseelenfunke.app.di.ServiceLocator.saveFcmToken(token)
                if (de.meinseelenfunke.app.di.ServiceLocator.authRepository.isLoggedIn()) {
                    kotlinx.coroutines.CoroutineScope(kotlinx.coroutines.Dispatchers.IO).launch {
                        de.meinseelenfunke.app.di.ServiceLocator.authRepository.updateFcmToken(token)
                    }
                }
            }
        }

        handleIntent(intent)
    }


    override fun onNewIntent(intent: android.content.Intent) {
        super.onNewIntent(intent)
        setIntent(intent)
        handleIntent(intent)
    }

    private fun handleIntent(intent: android.content.Intent?) {
        if (intent?.getBooleanExtra("start_voice_call", false) == true) {
            val manager = getSystemService(android.content.Context.NOTIFICATION_SERVICE) as android.app.NotificationManager
            manager.cancel(1003)
            de.meinseelenfunke.app.util.NavigationBridge.triggerWakeWord()
        } else if (intent?.hasExtra("open_tab") == true) {
            val tab = intent.getIntExtra("open_tab", 0)
            val subTab = intent.getIntExtra("open_subtab", 0)
            if (intent.hasExtra("selected_date")) {
                de.meinseelenfunke.app.util.NavigationBridge.pendingSelectedDate = intent.getStringExtra("selected_date")
            }
            if (intent.hasExtra("email_id")) {
                de.meinseelenfunke.app.util.NavigationBridge.pendingEmailId = intent.getStringExtra("email_id")
            }
            de.meinseelenfunke.app.util.NavigationBridge.pendingCreateEvent = intent.getBooleanExtra("create_event", false)
            de.meinseelenfunke.app.util.NavigationBridge.triggerNavigation(tab, subTab)
        }
    }
}
