package de.meinseelenfunke.app

import android.app.NotificationManager
import android.content.Context
import android.content.Intent
import android.content.pm.PackageManager
import android.os.Build
import android.os.Bundle
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.fragment.app.FragmentActivity
import com.google.firebase.messaging.FirebaseMessaging
import de.meinseelenfunke.app.di.ServiceLocator
import de.meinseelenfunke.app.di.ThemeMode
import de.meinseelenfunke.app.services.MusicPlaybackService
import de.meinseelenfunke.app.ui.navigation.AppNavigation
import de.meinseelenfunke.app.ui.theme.SeelenfunkeTheme
import de.meinseelenfunke.app.util.FunkenwerkAlarmManager
import de.meinseelenfunke.app.util.MusicPlayerManager
import de.meinseelenfunke.app.util.NavigationBridge
import de.meinseelenfunke.app.util.SecurityIntentHelper
import de.meinseelenfunke.app.util.SoundManager
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch

class MainActivity : FragmentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        enableEdgeToEdge()

        // Prevent Android Recents task switcher from writing unencrypted screen snapshots to disk
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            setRecentsScreenshotEnabled(false)
            if (checkSelfPermission(android.Manifest.permission.POST_NOTIFICATIONS) != PackageManager.PERMISSION_GRANTED) {
                requestPermissions(arrayOf(android.Manifest.permission.POST_NOTIFICATIONS), 108)
            }
        }

        ServiceLocator.ensureInitialized(this)
        de.meinseelenfunke.app.util.AppUpdateManager.cleanupCacheAsync(this)
        de.meinseelenfunke.app.util.ErrorSentinelReporter.install(this)
        FunkenwerkAlarmManager.ensureInitialized(this)

        SoundManager.playSound(R.raw.open_project_brain)

        setContent {
            val themeMode by ServiceLocator.themeModeState.collectAsState()
            val isSystemDark = isSystemInDarkTheme()
            val isDark = when (themeMode) {
                ThemeMode.DARK -> true
                ThemeMode.LIGHT -> false
                else -> isSystemDark
            }
            SeelenfunkeTheme(darkTheme = isDark) {
                AppNavigation()
            }
        }

        // Fetch and register FCM token
        FirebaseMessaging.getInstance().token.addOnCompleteListener { task ->
            if (task.isSuccessful) {
                val token = task.result
                ServiceLocator.saveFcmToken(token)
                if (ServiceLocator.authRepository.isLoggedIn()) {
                    CoroutineScope(Dispatchers.IO).launch {
                        ServiceLocator.authRepository.updateFcmToken(token)
                    }
                }
            }
        }

        handleIntent(intent)
    }

    override fun onStop() {
        super.onStop()
        if (MusicPlayerManager.isPlaying.value) {
            MusicPlaybackService.startOrUpdate(this)
        }
    }

    override fun onNewIntent(intent: Intent) {
        super.onNewIntent(intent)
        setIntent(intent)
        handleIntent(intent)
    }

    private fun handleIntent(intent: Intent?) {
        if (intent == null) return

        val hasInternalActionExtras = try {
            intent.getBooleanExtra("start_voice_call", false)
                || intent.hasExtra("navigate_to")
                || intent.hasExtra("open_tab")
        } catch (_: Exception) {
            return
        }

        if (!hasInternalActionExtras) return

        // Block untrusted third-party apps from spoofing deep navigation or auto-starting live microphone voice calls
        if (!SecurityIntentHelper.isTrustedInternalIntent(this, intent)) {
            return
        }

        if (intent.getBooleanExtra("start_voice_call", false)) {
            val manager = getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
            manager.cancel(1003)
            NavigationBridge.triggerWakeWord()
        } else if (!intent.getStringExtra("navigate_to").isNullOrBlank()) {
            val navigateTo = intent.getStringExtra("navigate_to")?.trim().orEmpty()
            if (intent.hasExtra("email_id")) {
                NavigationBridge.pendingEmailId = intent.getStringExtra("email_id")
            }
            if (navigateTo.equals("email", ignoreCase = true) || navigateTo.equals("mail", ignoreCase = true)) {
                NavigationBridge.triggerNavigation(NavigationBridge.NavTarget.ORGANIZER, subTab = 4)
            } else {
                val parsedTarget = NavigationBridge.parseTarget(navigateTo) ?: NavigationBridge.NavTarget.ZENTRUM
                val subTab = intent.getIntExtra("open_subtab", 0)
                NavigationBridge.triggerNavigation(parsedTarget, subTab = subTab)
            }
        } else if (intent.hasExtra("open_tab")) {
            val tab = intent.getIntExtra("open_tab", 0)
            val subTab = intent.getIntExtra("open_subtab", 0)
            if (intent.hasExtra("alarm_inner_tab")) {
                FunkenwerkAlarmManager.setActiveInnerSubTab(
                    intent.getIntExtra("alarm_inner_tab", 0)
                )
            }
            if (intent.hasExtra("selected_date")) {
                NavigationBridge.pendingSelectedDate = intent.getStringExtra("selected_date")
            }
            if (intent.hasExtra("email_id")) {
                NavigationBridge.pendingEmailId = intent.getStringExtra("email_id")
            }
            NavigationBridge.pendingCreateEvent = intent.getBooleanExtra("create_event", false)
            NavigationBridge.triggerNavigation(tab, subTab)
        }
    }
}
