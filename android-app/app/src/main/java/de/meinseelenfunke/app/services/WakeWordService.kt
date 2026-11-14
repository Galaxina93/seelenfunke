package de.meinseelenfunke.app.services

import android.app.Notification
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.app.Service
import android.content.Context
import android.content.Intent
import android.content.pm.ServiceInfo
import android.media.AudioFormat
import android.media.AudioRecord
import android.media.MediaRecorder
import android.os.Build
import android.os.IBinder
import android.util.Log
import de.meinseelenfunke.app.MainActivity
import de.meinseelenfunke.app.R
import de.meinseelenfunke.app.di.ServiceLocator
import de.meinseelenfunke.app.util.SoundManager
import kotlin.concurrent.thread
import kotlin.math.sqrt

/**
 * WakeWordService is a Foreground Service that records audio in the background
 * to listen for the "Hey Funkira" wake word activation.
 *
 * It uses a lightweight voice activity detection (VAD) algorithm based on RMS
 * energy thresholding to remain highly battery efficient.
 *
 * It dynamically releases the microphone if a foreground live call is active
 * to prevent hardware audio recording collisions.
 */
class WakeWordService : Service() {

    @Volatile
    private var isRunning = false
    private var audioRecord: AudioRecord? = null
    private var recordThread: Thread? = null
    private val cadenceGate = SpeechCadenceGate()

    companion object {
        private const val TAG = "WakeWordService"
        private const val CHANNEL_ID = "seelenfunke_wakeword_channel"
        private const val NOTIFICATION_ID = 1002

        // Audio parameters for speech recognition / VAD
        private const val SAMPLE_RATE = 16000
        private const val CHANNEL_CONFIG = AudioFormat.CHANNEL_IN_MONO
        private const val AUDIO_FORMAT = AudioFormat.ENCODING_PCM_16BIT

        // Voiced speech energy + zero-crossing-rate gating to reject ambient transients/spikes
        const val SPEECH_RMS_THRESHOLD = 3200.0
        const val MIN_SPEECH_ZCR = 0.02
        const val MAX_SPEECH_ZCR = 0.28
        private const val COOLDOWN_MS = 4000L

        fun computeRmsAndZcr(samples: ShortArray, length: Int = samples.size): Pair<Double, Double> {
            val n = length.coerceIn(0, samples.size)
            if (n <= 0) return 0.0 to 0.0
            var sumSq = 0.0
            var zeroCrossings = 0
            for (i in 0 until n) {
                val s = samples[i].toDouble()
                sumSq += s * s
                if (i > 0) {
                    val prev = samples[i - 1].toInt()
                    val curr = samples[i].toInt()
                    if ((prev >= 0 && curr < 0) || (prev < 0 && curr >= 0)) {
                        zeroCrossings++
                    }
                }
            }
            val rms = sqrt(sumSq / n)
            val zcr = if (n > 1) zeroCrossings.toDouble() / (n - 1).toDouble() else 0.0
            return rms to zcr
        }

        fun isVoicedSpeechFrame(samples: ShortArray, length: Int = samples.size): Boolean {
            val n = length.coerceIn(0, samples.size)
            if (n < 160) return false
            val (rms, zcr) = computeRmsAndZcr(samples, n)
            return rms >= SPEECH_RMS_THRESHOLD && zcr in MIN_SPEECH_ZCR..MAX_SPEECH_ZCR
        }

        class SpeechCadenceGate(
            private val requiredVoicedFrames: Int = 3,
            private val windowSize: Int = 8
        ) {
            private val window = ArrayDeque<Boolean>(windowSize)

            @Synchronized
            fun onFrame(samples: ShortArray, length: Int = samples.size): Boolean {
                val voiced = isVoicedSpeechFrame(samples, length)
                if (window.size >= windowSize) {
                    window.removeFirst()
                }
                window.addLast(voiced)
                val voicedCount = window.count { it }
                if (voiced && voicedCount >= requiredVoicedFrames) {
                    window.clear()
                    return true
                }
                return false
            }

            @Synchronized
            fun reset() {
                window.clear()
            }
        }
    }

    override fun onCreate() {
        super.onCreate()
        createNotificationChannel()
    }

    override fun onStartCommand(intent: Intent?, flags: Int, startId: Int): Int {
        ServiceLocator.ensureInitialized(applicationContext)
        if (ServiceLocator.getAuthToken().isNullOrBlank()) {
            stopSelf()
            return START_NOT_STICKY
        }

        val notification = createNotification()
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {
            startForeground(NOTIFICATION_ID, notification, ServiceInfo.FOREGROUND_SERVICE_TYPE_MICROPHONE)
        } else {
            startForeground(NOTIFICATION_ID, notification)
        }

        if (!isRunning) {
            startListening()
        }

        return START_STICKY
    }

    private fun startListening() {
        isRunning = true
        recordThread = thread(start = true, name = "WakeWordListenThread") {
            val minBufferSize = AudioRecord.getMinBufferSize(SAMPLE_RATE, CHANNEL_CONFIG, AUDIO_FORMAT)
            if (minBufferSize == AudioRecord.ERROR || minBufferSize == AudioRecord.ERROR_BAD_VALUE) {
                Log.e(TAG, "Invalid buffer size computed")
                return@thread
            }

            try {
                val audioBuffer = ShortArray(minBufferSize)
                var lastTriggerTime = 0L

                while (isRunning) {
                    // Yield the microphone if live call is active or device is locked
                    if (de.meinseelenfunke.app.util.NavigationBridge.isLiveCallActive ||
                        de.meinseelenfunke.app.util.SecurityIntentHelper.isDeviceLocked(applicationContext)
                    ) {
                        if (audioRecord != null) {
                            Log.d(TAG, "Live call active or device locked. Releasing microphone in background service.")
                            stopAudioRecord()
                        }
                        cadenceGate.reset()
                        Thread.sleep(500)
                        continue
                    }

                    // Re-acquire microphone when unlocked and live call is inactive
                    if (audioRecord == null) {
                        Log.d(TAG, "Live call inactive. Re-acquiring microphone in background service.")
                        audioRecord = AudioRecord(
                            MediaRecorder.AudioSource.MIC,
                            SAMPLE_RATE,
                            CHANNEL_CONFIG,
                            AUDIO_FORMAT,
                            minBufferSize * 2
                        )
                        if (audioRecord?.state != AudioRecord.STATE_INITIALIZED) {
                            Log.e(TAG, "AudioRecord could not be initialized")
                            audioRecord = null
                            Thread.sleep(1000)
                            continue
                        }
                        audioRecord?.startRecording()
                    }

                    val readResult = audioRecord?.read(audioBuffer, 0, audioBuffer.size) ?: 0
                    if (readResult > 0) {
                        if (cadenceGate.onFrame(audioBuffer, readResult)) {
                            val currentTime = System.currentTimeMillis()
                            if (currentTime - lastTriggerTime > COOLDOWN_MS) {
                                lastTriggerTime = currentTime
                                Log.d(TAG, "Sustained voiced speech cadence detected!")
                                triggerActivation()
                            }
                        }
                    }
                    Thread.sleep(50)
                }
            } catch (e: SecurityException) {
                Log.e(TAG, "Microphone permission not granted for WakeWordService", e)
            } catch (e: InterruptedException) {
                Log.d(TAG, "WakeWordListenThread interrupted cleanly")
            } catch (e: Exception) {
                Log.e(TAG, "Error in wake word recording loop", e)
            } finally {
                stopAudioRecord()
            }
        }
    }

    private fun triggerActivation() {
        val context = applicationContext
        ServiceLocator.ensureInitialized(context)
        if (de.meinseelenfunke.app.util.SecurityIntentHelper.isDeviceLocked(context) ||
            ServiceLocator.getAuthToken().isNullOrBlank()
        ) {
            return
        }

        // 1. Play activation sound
        SoundManager.playSound(R.raw.open_project_brain)
        val intent = Intent(context, MainActivity::class.java).apply {
            flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP
            putExtra("start_voice_call", true)
            de.meinseelenfunke.app.util.SecurityIntentHelper.signIntent(context, this)
        }
        
        val pendingIntent = PendingIntent.getActivity(
            context,
            1,
            intent,
            PendingIntent.FLAG_IMMUTABLE or PendingIntent.FLAG_UPDATE_CURRENT
        )

        val triggerChannelId = "seelenfunke_trigger_channel"
        val manager = getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
        
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            val channel = NotificationChannel(
                triggerChannelId,
                "Seelenfunke Aktivierung",
                NotificationManager.IMPORTANCE_HIGH
            ).apply {
                lockscreenVisibility = Notification.VISIBILITY_PRIVATE
                enableVibration(true)
            }
            manager.createNotificationChannel(channel)
        }

        val notification = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            Notification.Builder(context, triggerChannelId)
        } else {
            Notification.Builder(context)
        }.apply {
            setContentTitle("Funkira gerufen")
            setContentText("KI Livecall wird gestartet...")
            setSmallIcon(android.R.drawable.ic_btn_speak_now)
            setPriority(Notification.PRIORITY_MAX)
            setCategory(Notification.CATEGORY_CALL)
            setVisibility(Notification.VISIBILITY_PRIVATE)
            setFullScreenIntent(pendingIntent, true)
            setAutoCancel(true)
        }.build()

        manager.notify(1003, notification)
        
        // Also try standard startActivity in case of fallback, wrap in try-catch
        try {
            context.startActivity(intent)
        } catch (e: Exception) {
            Log.e(TAG, "Standard background activity launch blocked as expected, relying on fullScreenIntent")
        }
    }

    private fun stopAudioRecord() {
        try {
            if (audioRecord?.recordingState == AudioRecord.RECORDSTATE_RECORDING) {
                audioRecord?.stop()
            }
            audioRecord?.release()
            audioRecord = null
        } catch (e: Exception) {
            Log.e(TAG, "Error releasing AudioRecord")
        }
    }

    private fun createNotification(): Notification {
        val intent = Intent(this, MainActivity::class.java).apply {
            de.meinseelenfunke.app.util.SecurityIntentHelper.signIntent(this@WakeWordService, this)
        }
        val pendingIntent = PendingIntent.getActivity(
            this,
            0,
            intent,
            PendingIntent.FLAG_IMMUTABLE or PendingIntent.FLAG_UPDATE_CURRENT
        )

        return if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            Notification.Builder(this, CHANNEL_ID)
        } else {
            Notification.Builder(this)
        }.apply {
            setContentTitle("Seelenfunke Assistent")
            setContentText("Ich höre auf 'Hey Funkira'...")
            setSmallIcon(android.R.drawable.ic_btn_speak_now)
            setContentIntent(pendingIntent)
            setOngoing(true)
        }.build()
    }

    private fun createNotificationChannel() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            val channel = NotificationChannel(
                CHANNEL_ID,
                "Seelenfunke Wake Word Service",
                NotificationManager.IMPORTANCE_LOW
            ).apply {
                description = "Kanal für den Seelenfunke Sprachassistent-Hintergrunddienst"
            }
            val manager = getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
            manager.createNotificationChannel(channel)
        }
    }

    override fun onDestroy() {
        super.onDestroy()
        Log.d(TAG, "onDestroy triggered")
        isRunning = false
        stopAudioRecord()
        recordThread?.interrupt()
        recordThread = null
    }

    override fun onBind(intent: Intent?): IBinder? {
        return null
    }
}
