package de.meinseelenfunke.app.ui

import android.app.KeyguardManager
import android.content.Context
import android.content.Intent
import android.os.Build
import android.os.Bundle
import android.view.WindowManager
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.CallEnd
import androidx.compose.material.icons.filled.GraphicEq
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import de.meinseelenfunke.app.services.FunkiraPhoneConciergeService
import kotlinx.coroutines.flow.MutableStateFlow
import java.lang.ref.WeakReference

/**
 * Lightweight, lockscreen-capable Call Activity for Funkira Smartphone Secretary:
 * Brings the app cleanly into Android's official TOP/RESUMED while-in-use state when a
 * Whitelist/Customer call is answered from the background (Android 14-17 / API 34-37),
 * enabling `FOREGROUND_SERVICE_TYPE_MICROPHONE` without SecurityException.
 */
class PhoneSecretaryCallActivity : ComponentActivity() {

    companion object {
        const val EXTRA_CALL_ID = "extra_secretary_call_id"
        const val EXTRA_DISPLAY_LABEL = "extra_secretary_display_label"

        private val activeRef = AtomicRefHolder()
        private val callerLabelFlow = MutableStateFlow("Anrufer")
        private val micElevatedFlow = MutableStateFlow(false)

        fun buildIntent(context: Context, callId: String, displayLabel: String): Intent {
            return Intent(context.applicationContext, PhoneSecretaryCallActivity::class.java).apply {
                flags = Intent.FLAG_ACTIVITY_NEW_TASK or
                    Intent.FLAG_ACTIVITY_SINGLE_TOP or
                    Intent.FLAG_ACTIVITY_CLEAR_TOP or
                    Intent.FLAG_ACTIVITY_NO_USER_ACTION
                putExtra(EXTRA_CALL_ID, callId)
                putExtra(EXTRA_DISPLAY_LABEL, displayLabel)
            }
        }

        fun updateMicElevatedState(elevated: Boolean) {
            micElevatedFlow.value = elevated
        }

        fun dismissActive() {
            val act = activeRef.get() ?: return
            act.runOnUiThread {
                try {
                    act.finishAndRemoveTask()
                } catch (_: Exception) {
                    try { act.finish() } catch (_: Exception) { }
                }
            }
        }

        private class AtomicRefHolder {
            @Volatile
            private var ref: WeakReference<PhoneSecretaryCallActivity>? = null
            fun set(activity: PhoneSecretaryCallActivity?) {
                ref = if (activity != null) WeakReference(activity) else null
            }
            fun get(): PhoneSecretaryCallActivity? = ref?.get()
        }
    }

    private var currentCallId: String = ""
    private var currentDisplayLabel: String = "Anrufer"

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O_MR1) {
            setShowWhenLocked(true)
            setTurnScreenOn(true)
        } else {
            @Suppress("DEPRECATION")
            window.addFlags(
                WindowManager.LayoutParams.FLAG_SHOW_WHEN_LOCKED or
                    WindowManager.LayoutParams.FLAG_TURN_SCREEN_ON
            )
        }
        window.addFlags(WindowManager.LayoutParams.FLAG_KEEP_SCREEN_ON)
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            setRecentsScreenshotEnabled(false)
        }

        try {
            val km = getSystemService(KeyguardManager::class.java)
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O && km?.isKeyguardLocked == true) {
                // Keep lockscreen safe (no unlock needed, activity renders cleanly above lockscreen)
            }
        } catch (_: Exception) { }

        activeRef.set(this)
        applyIntentExtras(intent)

        setContent {
            val label by callerLabelFlow.collectAsState()
            val micActive by micElevatedFlow.collectAsState()

            MaterialTheme {
                Box(
                    modifier = Modifier
                        .fillMaxSize()
                        .background(Color(0xFF0D0D10))
                        .padding(24.dp),
                    contentAlignment = Alignment.Center
                ) {
                    Column(
                        modifier = Modifier
                            .fillMaxWidth()
                            .clip(RoundedCornerShape(24.dp))
                            .background(Color(0xFF15151A))
                            .border(1.dp, Color(0x40C5A059), RoundedCornerShape(24.dp))
                            .padding(28.dp),
                        horizontalAlignment = Alignment.CenterHorizontally,
                        verticalArrangement = Arrangement.Center
                    ) {
                        Box(
                            modifier = Modifier
                                .size(72.dp)
                                .clip(CircleShape)
                                .background(Color(0x22C5A059))
                                .border(1.5.dp, Color(0xFFC5A059), CircleShape),
                            contentAlignment = Alignment.Center
                        ) {
                            Icon(
                                imageVector = Icons.Filled.GraphicEq,
                                contentDescription = "Funkira Aktiv",
                                tint = Color(0xFFC5A059),
                                modifier = Modifier.size(34.dp)
                            )
                        }

                        Spacer(modifier = Modifier.height(20.dp))

                        Text(
                            text = "FUNKIRA SEKRETÄRIN",
                            color = Color(0xFFC5A059),
                            fontSize = 12.sp,
                            fontWeight = FontWeight.Bold,
                            letterSpacing = 1.6.sp
                        )

                        Spacer(modifier = Modifier.height(8.dp))

                        Text(
                            text = label,
                            color = Color.White,
                            fontSize = 24.sp,
                            fontWeight = FontWeight.SemiBold,
                            textAlign = TextAlign.Center
                        )

                        Spacer(modifier = Modifier.height(8.dp))

                        Text(
                            text = if (micActive) "Live-Aufnahme aktiv" else "Audio-Kanal wird verbunden",
                            color = if (micActive) Color(0xFF6EE7B7) else Color(0xFFA1A1AA),
                            fontSize = 14.sp,
                            fontWeight = FontWeight.Medium
                        )

                        Spacer(modifier = Modifier.height(28.dp))

                        Button(
                            onClick = {
                                val stopIntent = Intent(this@PhoneSecretaryCallActivity, FunkiraPhoneConciergeService::class.java).apply {
                                    action = FunkiraPhoneConciergeService.ACTION_MANUAL_STOP
                                }
                                try {
                                    startService(stopIntent)
                                } catch (_: Exception) { }
                                finishAndRemoveTask()
                            },
                            colors = ButtonDefaults.buttonColors(
                                containerColor = Color(0xFFDC2626),
                                contentColor = Color.White
                            ),
                            shape = RoundedCornerShape(14.dp),
                            modifier = Modifier
                                .fillMaxWidth()
                                .height(52.dp)
                        ) {
                            Icon(
                                imageVector = Icons.Filled.CallEnd,
                                contentDescription = "Auflegen",
                                modifier = Modifier.size(20.dp)
                            )
                            Spacer(modifier = Modifier.size(10.dp))
                            Text(
                                text = "Auflegen",
                                fontSize = 16.sp,
                                fontWeight = FontWeight.SemiBold
                            )
                        }
                    }
                }
            }
        }
    }

    override fun onNewIntent(intent: Intent) {
        super.onNewIntent(intent)
        setIntent(intent)
        applyIntentExtras(intent)
    }

    override fun onResume() {
        super.onResume()
        // Activity is now in Android's official TOP/RESUMED state -> immediately elevate FGS to MICROPHONE!
        FunkiraPhoneConciergeService.requestMicFgsUpgradeFromForegroundActivity(
            context = this,
            callId = currentCallId,
            displayLabel = currentDisplayLabel
        )
    }

    override fun onDestroy() {
        if (activeRef.get() === this) {
            activeRef.set(null)
        }
        micElevatedFlow.value = false
        super.onDestroy()
    }

    private fun applyIntentExtras(intent: Intent?) {
        currentCallId = intent?.getStringExtra(EXTRA_CALL_ID).orEmpty()
        currentDisplayLabel = intent?.getStringExtra(EXTRA_DISPLAY_LABEL)?.takeIf { it.isNotBlank() } ?: "Anrufer"
        callerLabelFlow.value = currentDisplayLabel
    }
}
