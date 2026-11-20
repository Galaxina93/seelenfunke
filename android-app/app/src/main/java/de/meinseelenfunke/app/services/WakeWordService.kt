package de.meinseelenfunke.app.services

import android.Manifest
import android.app.Notification
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.app.Service
import android.content.Context
import android.content.Intent
import android.content.pm.PackageManager
import android.content.pm.ServiceInfo
import android.media.AudioFormat
import android.media.AudioRecord
import android.media.MediaRecorder
import android.os.Build
import android.os.Bundle
import android.os.Handler
import android.os.IBinder
import android.os.Looper
import android.os.ParcelFileDescriptor
import android.speech.RecognitionListener
import android.speech.RecognizerIntent
import android.speech.SpeechRecognizer
import android.util.Log
import androidx.core.app.NotificationCompat
import androidx.core.content.ContextCompat
import de.meinseelenfunke.app.MainActivity
import de.meinseelenfunke.app.R
import de.meinseelenfunke.app.di.ServiceLocator
import de.meinseelenfunke.app.util.FunkenwerkAlarmManager
import de.meinseelenfunke.app.util.NavigationBridge
import de.meinseelenfunke.app.util.SecurityIntentHelper
import de.meinseelenfunke.app.util.SoundManager
import java.util.Locale
import java.util.concurrent.CountDownLatch
import java.util.concurrent.TimeUnit
import java.util.concurrent.atomic.AtomicBoolean
import java.util.concurrent.atomic.AtomicInteger
import kotlin.concurrent.thread
import kotlin.math.sqrt

/**
 * WakeWordService listens in the background for "Hey Falcone" (and phonetic "Hey Falcon" variants).
 *
 * Stage 1 uses a zero-network, low-power PCM voice-activity cadence gate with a 1-second circular
 * pre-roll buffer. When human speech is detected, Stage 2 verifies the phrase via Android's
 * SpeechRecognizer (using zero-gap ParcelFileDescriptor audio piping on Android 13+ so no syllable
 * is lost and no system mic beep is played, with clean fallback on older Android versions).
 *
 * Releases all microphone resources strictly BEFORE starting the Falcone Live Call so zero hardware
 * microphone contention can occur.
 */
class WakeWordService : Service() {

    @Volatile
    private var isRunning = false
    private var audioRecord: AudioRecord? = null
    private var recordThread: Thread? = null
    private val cadenceGate = SpeechCadenceGate()
    private val mainHandler = Handler(Looper.getMainLooper())
    private var activeSpeechRecognizer: SpeechRecognizer? = null

    @Volatile
    private var pipeSttSupported: Boolean = Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU

    companion object {
        private const val TAG = "WakeWordService"
        private const val CHANNEL_ID = "seelenfunke_wakeword_channel"
        private const val NOTIFICATION_ID = 1002

        private const val SAMPLE_RATE = 16000
        private const val CHANNEL_CONFIG = AudioFormat.CHANNEL_IN_MONO
        private const val AUDIO_FORMAT = AudioFormat.ENCODING_PCM_16BIT

        // Voiced speech energy + zero-crossing-rate gating to reject ambient transients/spikes
        const val SPEECH_RMS_THRESHOLD = 3200.0
        const val MIN_SPEECH_ZCR = 0.02
        const val MAX_SPEECH_ZCR = 0.28
        private const val COOLDOWN_MS = 4000L
        private const val PRE_ROLL_SAMPLES = SAMPLE_RATE // 1.0s @ 16 kHz

        private val WAKE_GREETINGS = setOf(
            "hey", "hi", "hallo", "ok", "okay", "ey", "hei", "hej"
        )

        private val FALCONE_VARIANTS = setOf(
            "falcone", "falkone", "falcon", "falkon", "falco", "falko",
            "falken", "falke", "falconi", "falkoni", "falcones", "falkons",
            "velcon", "felcon", "valcon"
        )

        private val SPLIT_FIRST_PART = setOf("fal", "falk", "fall", "fahl")
        private val SPLIT_SECOND_PART = setOf("cone", "kone", "kon", "con", "kohne", "ohne")

        fun matchesHeyFalconePhrase(
            rawTranscript: String?,
            allowStandaloneAfterWake: Boolean = false
        ): Boolean {
            if (rawTranscript.isNullOrBlank()) return false
            val normalized = rawTranscript
                .lowercase(Locale.GERMAN)
                .replace("ß", "ss")
                .replace("ä", "ae")
                .replace("ö", "oe")
                .replace("ü", "ue")
                .replace(Regex("[^a-z0-9]+"), " ")
                .trim()
            if (normalized.isEmpty()) return false

            val tokens = normalized.split(Regex("\\s+")).filter { it.isNotEmpty() }
            if (tokens.isEmpty()) return false

            for (i in tokens.indices) {
                val t = tokens[i]
                // 1. Joined STT token like "heyfalcone", "heyfalcon", "okfalcone"
                for (g in WAKE_GREETINGS) {
                    if (t.startsWith(g) && t.length > g.length + 3) {
                        val rest = t.removePrefix(g)
                        if (rest in FALCONE_VARIANTS) return true
                    }
                }

                // 2. Greeting followed by Falcone/Falcon variant (allowing 1 optional filler like "du" / "mein")
                if (t in WAKE_GREETINGS) {
                    val next1 = tokens.getOrNull(i + 1)
                    val next2 = tokens.getOrNull(i + 2)
                    if (next1 != null && next1 in FALCONE_VARIANTS) return true
                    if (next2 != null && next2 in FALCONE_VARIANTS) return true
                    if (next1 != null && next2 != null && next1 in SPLIT_FIRST_PART && next2 in SPLIT_SECOND_PART) {
                        return true
                    }
                }
            }

            if (allowStandaloneAfterWake && tokens.size <= 3) {
                if (tokens.first() in setOf("falcone", "falkone", "falcon", "falkon")) {
                    return true
                }
                if (tokens.size >= 2 && tokens[0] in SPLIT_FIRST_PART && tokens[1] in SPLIT_SECOND_PART) {
                    return true
                }
            }

            return false
        }

        fun syncServiceState(context: Context) {
            val appCtx = context.applicationContext
            ServiceLocator.ensureInitialized(appCtx)
            val hasMicPermission = ContextCompat.checkSelfPermission(
                appCtx,
                Manifest.permission.RECORD_AUDIO
            ) == PackageManager.PERMISSION_GRANTED
            val shouldRun = ServiceLocator.isWakeWordEnabled() &&
                !ServiceLocator.getAuthToken().isNullOrBlank() &&
                hasMicPermission

            val intent = Intent(appCtx, WakeWordService::class.java)
            try {
                if (shouldRun) {
                    if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                        appCtx.startForegroundService(intent)
                    } else {
                        appCtx.startService(intent)
                    }
                } else {
                    appCtx.stopService(intent)
                }
            } catch (e: Exception) {
                Log.w(TAG, "WakeWordService state sync deferred (${e.javaClass.simpleName})")
            }
        }

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
        val hasMic = ContextCompat.checkSelfPermission(
            applicationContext,
            Manifest.permission.RECORD_AUDIO
        ) == PackageManager.PERMISSION_GRANTED
        if (ServiceLocator.getAuthToken().isNullOrBlank() || !ServiceLocator.isWakeWordEnabled() || !hasMic) {
            stopSelf()
            return START_NOT_STICKY
        }

        val notification = createNotification()
        try {
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {
                startForeground(NOTIFICATION_ID, notification, ServiceInfo.FOREGROUND_SERVICE_TYPE_MICROPHONE)
            } else {
                startForeground(NOTIFICATION_ID, notification)
            }
        } catch (e: Exception) {
            Log.w(TAG, "Unable to enter foreground microphone mode right now: ${e.message}")
            stopSelf()
            return START_NOT_STICKY
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
                Log.e(TAG, "Invalid AudioRecord buffer size")
                return@thread
            }

            val frameSamples = 800 // 50ms @ 16 kHz
            val audioBuffer = ShortArray(frameSamples)
            val preRollRing = ShortArray(PRE_ROLL_SAMPLES)
            var preRollWritePos = 0
            var preRollFilled = 0
            var lastTriggerTime = 0L
            var lastCheckEndMs = 0L

            try {
                while (isRunning) {
                    if (!ServiceLocator.isWakeWordEnabled() || ServiceLocator.getAuthToken().isNullOrBlank()) {
                        stopSelf()
                        break
                    }

                    // Pause mic & SpeechRecognizer while music plays so Pixel never applies speaker-mic coupling attenuation or audio-focus ducking
                    if (NavigationBridge.isLiveCallActive ||
                        FunkenwerkAlarmManager.isAlarmRingingFlow.value ||
                        de.meinseelenfunke.app.util.MusicPlayerManager.isPlaying.value ||
                        SecurityIntentHelper.isDeviceLocked(applicationContext)
                    ) {
                        if (audioRecord != null) {
                            stopAudioRecord()
                        }
                        cancelSpeechRecognizerBlocking()
                        cadenceGate.reset()
                        preRollWritePos = 0
                        preRollFilled = 0
                        Thread.sleep(450L)
                        continue
                    }

                    if (audioRecord == null) {
                        val rec = AudioRecord(
                            MediaRecorder.AudioSource.MIC,
                            SAMPLE_RATE,
                            CHANNEL_CONFIG,
                            AUDIO_FORMAT,
                            maxOf(minBufferSize * 2, SAMPLE_RATE * 2)
                        )
                        if (rec.state != AudioRecord.STATE_INITIALIZED) {
                            try { rec.release() } catch (_: Exception) {}
                            Thread.sleep(1000L)
                            continue
                        }
                        rec.startRecording()
                        audioRecord = rec
                    }

                    val readResult = audioRecord?.read(audioBuffer, 0, audioBuffer.size) ?: 0
                    if (readResult <= 0) {
                        Thread.sleep(40L)
                        continue
                    }

                    // Push into circular 1-second pre-roll ring buffer
                    for (i in 0 until readResult) {
                        preRollRing[preRollWritePos] = audioBuffer[i]
                        preRollWritePos = (preRollWritePos + 1) % PRE_ROLL_SAMPLES
                        if (preRollFilled < PRE_ROLL_SAMPLES) preRollFilled++
                    }

                    if (cadenceGate.onFrame(audioBuffer, readResult)) {
                        val now = System.currentTimeMillis()
                        val checkGapMs = if (de.meinseelenfunke.app.util.MusicPlayerManager.isPlaying.value) 1800L else 600L
                        if (now - lastTriggerTime > COOLDOWN_MS && now - lastCheckEndMs > checkGapMs) {
                            val preRollBytes = snapshotPreRollPcmBytes(preRollRing, preRollWritePos, preRollFilled)
                            val phraseMatched = verifyWakePhrase(preRollBytes, audioBuffer)
                            lastCheckEndMs = System.currentTimeMillis()
                            preRollWritePos = 0
                            preRollFilled = 0
                            cadenceGate.reset()

                            if (phraseMatched && isRunning && !NavigationBridge.isLiveCallActive) {
                                lastTriggerTime = System.currentTimeMillis()
                                // Release ALL mic handles BEFORE signalling live call start!
                                stopAudioRecord()
                                cancelSpeechRecognizerBlocking()
                                NavigationBridge.isLiveCallActive = true
                                triggerActivation()
                            }
                        }
                    }
                }
            } catch (_: InterruptedException) {
                Log.d(TAG, "WakeWordListenThread interrupted cleanly")
            } catch (e: SecurityException) {
                Log.e(TAG, "Microphone permission missing for WakeWordService", e)
            } catch (e: Exception) {
                Log.e(TAG, "Error in WakeWordService loop", e)
            } finally {
                stopAudioRecord()
                cancelSpeechRecognizerBlocking()
            }
        }
    }

    private fun snapshotPreRollPcmBytes(ring: ShortArray, writePos: Int, filled: Int): ByteArray {
        if (filled <= 0) return ByteArray(0)
        val out = ByteArray(filled * 2)
        val start = if (filled < ring.size) 0 else writePos
        for (i in 0 until filled) {
            val sample = ring[(start + i) % ring.size].toInt()
            out[i * 2] = (sample and 0xFF).toByte()
            out[i * 2 + 1] = ((sample ushr 8) and 0xFF).toByte()
        }
        return out
    }

    private fun verifyWakePhrase(preRollBytes: ByteArray, tempBuffer: ShortArray): Boolean {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU && pipeSttSupported) {
            val pipeResult = verifyViaAudioPipeApi33(preRollBytes, tempBuffer)
            if (pipeResult != null) {
                return pipeResult
            }
        }
        if (de.meinseelenfunke.app.util.MusicPlayerManager.isPlaying.value) {
            return false
        }
        return verifyViaDirectSpeechRecognizerFallback()
    }

    private fun verifyViaAudioPipeApi33(preRollBytes: ByteArray, tempBuffer: ShortArray): Boolean? {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.TIRAMISU) return null
        var readPfd: ParcelFileDescriptor? = null
        var writePfd: ParcelFileDescriptor? = null
        val matched = AtomicBoolean(false)
        val readyObserved = AtomicBoolean(false)
        val fatalPipeError = AtomicInteger(0)
        val doneLatch = CountDownLatch(1)

        return try {
            val pipe = ParcelFileDescriptor.createPipe()
            readPfd = pipe[0]
            writePfd = pipe[1]
            val recognizerPfd = readPfd

            val startedLatch = CountDownLatch(1)
            val startedOk = AtomicBoolean(false)

            mainHandler.post {
                try {
                    if (!SpeechRecognizer.isRecognitionAvailable(applicationContext)) {
                        startedLatch.countDown()
                        doneLatch.countDown()
                        return@post
                    }
                    destroyRecognizerOnMain()
                    val sr = SpeechRecognizer.createSpeechRecognizer(applicationContext)
                    activeSpeechRecognizer = sr
                    sr.setRecognitionListener(object : RecognitionListener {
                        override fun onReadyForSpeech(params: Bundle?) {
                            readyObserved.set(true)
                        }
                        override fun onBeginningOfSpeech() {}
                        override fun onRmsChanged(rmsdB: Float) {}
                        override fun onBufferReceived(buffer: ByteArray?) {}
                        override fun onEndOfSpeech() {}
                        override fun onError(error: Int) {
                            if (!readyObserved.get() && (error == SpeechRecognizer.ERROR_CLIENT || error == SpeechRecognizer.ERROR_AUDIO)) {
                                fatalPipeError.set(error)
                            }
                            doneLatch.countDown()
                        }
                        override fun onResults(results: Bundle?) {
                            val list = results?.getStringArrayList(SpeechRecognizer.RESULTS_RECOGNITION).orEmpty()
                            if (list.any { matchesHeyFalconePhrase(it, allowStandaloneAfterWake = false) }) {
                                matched.set(true)
                            }
                            doneLatch.countDown()
                        }
                        override fun onPartialResults(partialResults: Bundle?) {
                            val list = partialResults?.getStringArrayList(SpeechRecognizer.RESULTS_RECOGNITION).orEmpty()
                            if (list.any { matchesHeyFalconePhrase(it, allowStandaloneAfterWake = false) }) {
                                matched.set(true)
                                doneLatch.countDown()
                            }
                        }
                        override fun onEvent(eventType: Int, params: Bundle?) {}
                    })

                    val recIntent = Intent(RecognizerIntent.ACTION_RECOGNIZE_SPEECH).apply {
                        putExtra(RecognizerIntent.EXTRA_LANGUAGE_MODEL, RecognizerIntent.LANGUAGE_MODEL_FREE_FORM)
                        putExtra(RecognizerIntent.EXTRA_LANGUAGE, "de-DE")
                        putExtra(RecognizerIntent.EXTRA_PARTIAL_RESULTS, true)
                        putExtra(RecognizerIntent.EXTRA_MAX_RESULTS, 4)
                        putExtra(RecognizerIntent.EXTRA_AUDIO_SOURCE, recognizerPfd)
                        putExtra(RecognizerIntent.EXTRA_AUDIO_SOURCE_CHANNEL_COUNT, 1)
                        putExtra(RecognizerIntent.EXTRA_AUDIO_SOURCE_ENCODING, AudioFormat.ENCODING_PCM_16BIT)
                        putExtra(RecognizerIntent.EXTRA_AUDIO_SOURCE_SAMPLING_RATE, SAMPLE_RATE)
                    }
                    sr.startListening(recIntent)
                    startedOk.set(true)
                } catch (e: Exception) {
                    Log.w(TAG, "Pipe SpeechRecognizer start failed: ${e.message}")
                    doneLatch.countDown()
                } finally {
                    startedLatch.countDown()
                }
            }

            startedLatch.await(800L, TimeUnit.MILLISECONDS)
            if (!startedOk.get()) {
                return null
            }

            ParcelFileDescriptor.AutoCloseOutputStream(writePfd).use { out ->
                writePfd = null
                if (preRollBytes.isNotEmpty()) {
                    out.write(preRollBytes)
                    out.flush()
                }
                val streamDeadline = System.currentTimeMillis() + 2200L
                val chunkBytes = ByteArray(tempBuffer.size * 2)
                while (isRunning &&
                    !matched.get() &&
                    doneLatch.count > 0 &&
                    !NavigationBridge.isLiveCallActive &&
                    System.currentTimeMillis() < streamDeadline
                ) {
                    val read = audioRecord?.read(tempBuffer, 0, tempBuffer.size) ?: -1
                    if (read <= 0) break
                    for (i in 0 until read) {
                        val s = tempBuffer[i].toInt()
                        chunkBytes[i * 2] = (s and 0xFF).toByte()
                        chunkBytes[i * 2 + 1] = ((s ushr 8) and 0xFF).toByte()
                    }
                    out.write(chunkBytes, 0, read * 2)
                }
                out.flush()
            }

            if (!matched.get()) {
                doneLatch.await(900L, TimeUnit.MILLISECONDS)
            }

            if (fatalPipeError.get() != 0 && !matched.get()) {
                pipeSttSupported = false
                return null
            }
            matched.get()
        } catch (_: Exception) {
            matched.get()
        } finally {
            try { writePfd?.close() } catch (_: Exception) {}
            try { readPfd?.close() } catch (_: Exception) {}
            cancelSpeechRecognizerBlocking()
        }
    }

    private fun verifyViaDirectSpeechRecognizerFallback(): Boolean {
        // Release AudioRecord first so SpeechRecognizer owns the mic cleanly
        stopAudioRecord()
        val matched = AtomicBoolean(false)
        val doneLatch = CountDownLatch(1)

        mainHandler.post {
            try {
                if (!SpeechRecognizer.isRecognitionAvailable(applicationContext)) {
                    doneLatch.countDown()
                    return@post
                }
                destroyRecognizerOnMain()
                val sr = SpeechRecognizer.createSpeechRecognizer(applicationContext)
                activeSpeechRecognizer = sr
                sr.setRecognitionListener(object : RecognitionListener {
                    override fun onReadyForSpeech(params: Bundle?) {}
                    override fun onBeginningOfSpeech() {}
                    override fun onRmsChanged(rmsdB: Float) {}
                    override fun onBufferReceived(buffer: ByteArray?) {}
                    override fun onEndOfSpeech() {}
                    override fun onError(error: Int) {
                        doneLatch.countDown()
                    }
                    override fun onResults(results: Bundle?) {
                        val list = results?.getStringArrayList(SpeechRecognizer.RESULTS_RECOGNITION).orEmpty()
                        if (list.any { matchesHeyFalconePhrase(it, allowStandaloneAfterWake = true) }) {
                            matched.set(true)
                        }
                        doneLatch.countDown()
                    }
                    override fun onPartialResults(partialResults: Bundle?) {
                        val list = partialResults?.getStringArrayList(SpeechRecognizer.RESULTS_RECOGNITION).orEmpty()
                        if (list.any { matchesHeyFalconePhrase(it, allowStandaloneAfterWake = true) }) {
                            matched.set(true)
                            doneLatch.countDown()
                        }
                    }
                    override fun onEvent(eventType: Int, params: Bundle?) {}
                })

                val recIntent = Intent(RecognizerIntent.ACTION_RECOGNIZE_SPEECH).apply {
                    putExtra(RecognizerIntent.EXTRA_LANGUAGE_MODEL, RecognizerIntent.LANGUAGE_MODEL_FREE_FORM)
                    putExtra(RecognizerIntent.EXTRA_LANGUAGE, "de-DE")
                    putExtra(RecognizerIntent.EXTRA_PARTIAL_RESULTS, true)
                    putExtra(RecognizerIntent.EXTRA_MAX_RESULTS, 4)
                }
                sr.startListening(recIntent)
            } catch (_: Exception) {
                doneLatch.countDown()
            }
        }

        doneLatch.await(2800L, TimeUnit.MILLISECONDS)
        cancelSpeechRecognizerBlocking()
        return matched.get()
    }

    private fun destroyRecognizerOnMain() {
        val sr = activeSpeechRecognizer ?: return
        activeSpeechRecognizer = null
        try {
            sr.cancel()
        } catch (_: Exception) {
        }
        try {
            sr.destroy()
        } catch (_: Exception) {
        }
    }

    private fun cancelSpeechRecognizerBlocking() {
        if (Looper.myLooper() == Looper.getMainLooper()) {
            destroyRecognizerOnMain()
            return
        }
        val latch = CountDownLatch(1)
        mainHandler.post {
            try {
                destroyRecognizerOnMain()
            } finally {
                latch.countDown()
            }
        }
        try {
            latch.await(500L, TimeUnit.MILLISECONDS)
        } catch (_: InterruptedException) {
        }
    }

    private fun triggerActivation() {
        val context = applicationContext
        ServiceLocator.ensureInitialized(context)
        if (SecurityIntentHelper.isDeviceLocked(context) ||
            ServiceLocator.getAuthToken().isNullOrBlank()
        ) {
            NavigationBridge.isLiveCallActive = false
            return
        }

        SoundManager.playSound(R.raw.open_project_brain)

        // 1. Trigger instant in-process navigation to FalconFocusScreen + autoStartVoice if foregrounded
        NavigationBridge.triggerWakeWord()

        // 2. Bring MainActivity to foreground if app was minimized to home screen
        val intent = Intent(context, MainActivity::class.java).apply {
            flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP or Intent.FLAG_ACTIVITY_SINGLE_TOP
            putExtra("start_voice_call", true)
            SecurityIntentHelper.signIntent(context, this)
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
                "Falcone Aktivierung",
                NotificationManager.IMPORTANCE_HIGH
            ).apply {
                lockscreenVisibility = Notification.VISIBILITY_PRIVATE
                enableVibration(true)
            }
            manager.createNotificationChannel(channel)
        }

        val notification = NotificationCompat.Builder(context, triggerChannelId)
            .setContentTitle("Falcone gerufen")
            .setContentText("Live-Gespräch startet")
            .setSmallIcon(R.drawable.ic_stat_funkenwerk)
            .setColor(0xFFC5A059.toInt())
            .setPriority(NotificationCompat.PRIORITY_MAX)
            .setCategory(NotificationCompat.CATEGORY_CALL)
            .setVisibility(NotificationCompat.VISIBILITY_PRIVATE)
            .setFullScreenIntent(pendingIntent, true)
            .setAutoCancel(true)
            .build()

        manager.notify(1003, notification)

        try {
            context.startActivity(intent)
        } catch (_: Exception) {
            Log.d(TAG, "Background startActivity deferred to fullScreenIntent")
        }
    }

    private fun stopAudioRecord() {
        val rec = audioRecord ?: return
        audioRecord = null
        try {
            if (rec.recordingState == AudioRecord.RECORDSTATE_RECORDING) {
                rec.stop()
            }
        } catch (_: Exception) {
        }
        try {
            rec.release()
        } catch (_: Exception) {
        }
    }

    private fun createNotification(): Notification {
        val intent = Intent(this, MainActivity::class.java).apply {
            putExtra(SecurityIntentHelper.EXTRA_FROM_WIDGET, true)
            SecurityIntentHelper.signIntent(this@WakeWordService, this)
        }
        val pendingIntent = PendingIntent.getActivity(
            this,
            0,
            intent,
            PendingIntent.FLAG_IMMUTABLE or PendingIntent.FLAG_UPDATE_CURRENT
        )

        return NotificationCompat.Builder(this, CHANNEL_ID)
            .setContentTitle("Falcone Assistent")
            .setContentText("Hört auf 'Hey Falcone'")
            .setSmallIcon(R.drawable.ic_stat_funkenwerk)
            .setColor(0xFFC5A059.toInt())
            .setContentIntent(pendingIntent)
            .setOngoing(true)
            .build()
    }

    private fun createNotificationChannel() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            val channel = NotificationChannel(
                CHANNEL_ID,
                "Hey Falcone Sprachaktivierung",
                NotificationManager.IMPORTANCE_LOW
            ).apply {
                description = "Hintergrunddienst für Hey Falcone"
            }
            val manager = getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
            manager.createNotificationChannel(channel)
        }
    }

    override fun onDestroy() {
        super.onDestroy()
        isRunning = false
        stopAudioRecord()
        cancelSpeechRecognizerBlocking()
        recordThread?.interrupt()
        recordThread = null
    }

    override fun onBind(intent: Intent?): IBinder? {
        return null
    }
}
