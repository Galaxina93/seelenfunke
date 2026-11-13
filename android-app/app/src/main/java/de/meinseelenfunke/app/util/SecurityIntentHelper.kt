package de.meinseelenfunke.app.util

import android.app.KeyguardManager
import android.content.Context
import android.content.Intent
import android.os.Build
import android.widget.Toast
import androidx.activity.ComponentActivity
import de.meinseelenfunke.app.di.ServiceLocator
import java.security.MessageDigest

object SecurityIntentHelper {
    const val EXTRA_SECURITY_TOKEN = "_fw_sec_token"

    /**
     * Attaches the app-private 256-bit security token (stored in AndroidKeyStore)
     * to internal Intents / PendingIntents so exported receivers & MainActivity
     * reject spoofed Intents from third-party apps.
     */
    fun signIntent(context: Context, intent: Intent): Intent {
        ServiceLocator.ensureInitialized(context)
        val token = ServiceLocator.getOrCreateInternalIntentToken()
        intent.putExtra(EXTRA_SECURITY_TOKEN, token)
        if (intent.`package` == null && intent.component == null) {
            intent.setPackage(context.packageName)
        }
        return intent
    }

    /**
     * Constant-time verification that an incoming Intent originated from a PendingIntent
     * or internal broadcast signed by this app installation.
     */
    fun isTrustedInternalIntent(context: Context, intent: Intent?): Boolean {
        if (intent == null) return false
        ServiceLocator.ensureInitialized(context)
        val provided = try {
            intent.getStringExtra(EXTRA_SECURITY_TOKEN)
        } catch (_: Exception) {
            // Defend against malformed / unparcelable third-party Serializable/Parcelable payloads
            null
        } ?: return false

        val expected = ServiceLocator.getOrCreateInternalIntentToken()
        if (expected.isEmpty() || provided.isEmpty()) return false

        return MessageDigest.isEqual(
            provided.toByteArray(Charsets.UTF_8),
            expected.toByteArray(Charsets.UTF_8)
        )
    }

    /**
     * Protects widget overlay dialog activities:
     * - Blocks access when the device is locked (Keyguard)
     * - Blocks access when logged out (no Sanctum auth token)
     * - Disables Recents task snapshots on Android 13+
     * Returns true if execution may proceed, false if the activity was finished.
     */
    fun guardWidgetDialogActivity(activity: ComponentActivity, showToastOnLocked: Boolean = true): Boolean {
        ServiceLocator.ensureInitialized(activity)
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            activity.setRecentsScreenshotEnabled(false)
        }

        val keyguardManager = activity.getSystemService(Context.KEYGUARD_SERVICE) as? KeyguardManager
        if (keyguardManager?.isDeviceLocked == true) {
            if (showToastOnLocked) {
                Toast.makeText(activity, "Bitte zuerst das Gerät entsperren.", Toast.LENGTH_SHORT).show()
            }
            activity.finish()
            return false
        }

        if (ServiceLocator.getAuthToken().isNullOrBlank()) {
            if (showToastOnLocked) {
                Toast.makeText(activity, "Bitte zuerst in Funkenwerk anmelden.", Toast.LENGTH_SHORT).show()
            }
            activity.finish()
            return false
        }

        return true
    }
}
