package de.meinseelenfunke.app.util

import android.content.Context
import android.media.AudioAttributes
import android.media.MediaPlayer
import android.media.SoundPool
import android.util.Log

object SoundManager {
    private const val TAG = "SoundManager"
    private var soundPool: SoundPool? = null
    private val soundMap = HashMap<Int, Int>() // Maps resource ID to SoundPool sound ID
    private val loadedMap = HashMap<Int, Boolean>() // Track loaded status
    private var context: Context? = null

    fun init(appContext: Context) {
        context = appContext.applicationContext
        
        val audioAttributes = AudioAttributes.Builder()
            .setUsage(AudioAttributes.USAGE_ASSISTANCE_SONIFICATION)
            .setContentType(AudioAttributes.CONTENT_TYPE_SONIFICATION)
            .build()
            
        soundPool = SoundPool.Builder()
            .setMaxStreams(5)
            .setAudioAttributes(audioAttributes)
            .build()

        soundPool?.setOnLoadCompleteListener { _, sampleId, status ->
            if (status == 0) {
                loadedMap[sampleId] = true
            } else {
                Log.e(TAG, "Error loading sound sample $sampleId: status $status")
            }
        }

        // Preload sounds
        preloadSound(de.meinseelenfunke.app.R.raw.open_project_brain)
        preloadSound(de.meinseelenfunke.app.R.raw.click_file_in_project_brain)
        preloadSound(de.meinseelenfunke.app.R.raw.sent_message_1)
        preloadSound(de.meinseelenfunke.app.R.raw.top_secret_sound_4)
        preloadSound(de.meinseelenfunke.app.R.raw.order_ching)
    }

    private fun preloadSound(resId: Int) {
        val ctx = context ?: return
        val pool = soundPool ?: return
        try {
            val soundId = pool.load(ctx, resId, 1)
            soundMap[resId] = soundId
        } catch (e: Exception) {
            Log.e(TAG, "Error preloading sound resource $resId", e)
        }
    }

    fun playSound(soundResId: Int) {
        val pool = soundPool ?: run {
            Log.w(TAG, "SoundManager/SoundPool not initialized.")
            playFallback(soundResId)
            return
        }
        val soundId = soundMap[soundResId] ?: run {
            Log.w(TAG, "Sound resource $soundResId not preloaded. Preloading now.")
            preloadSound(soundResId)
            playFallback(soundResId)
            return
        }
        
        val isLoaded = loadedMap[soundId] ?: false
        if (isLoaded) {
            try {
                pool.play(soundId, 1.0f, 1.0f, 1, 0, 1.0f)
            } catch (e: Exception) {
                Log.e(TAG, "Error playing sound via SoundPool", e)
                playFallback(soundResId)
            }
        } else {
            // Fallback to MediaPlayer if not loaded yet
            playFallback(soundResId)
        }
    }

    private fun playFallback(soundResId: Int) {
        val ctx = context ?: return
        try {
            val mediaPlayer = MediaPlayer.create(ctx, soundResId) ?: return
            mediaPlayer.setOnCompletionListener { mp -> mp.release() }
            mediaPlayer.start()
        } catch (e: Exception) {
            Log.e(TAG, "Error in fallback player", e)
        }
    }

    fun playNotificationSound() {
        playSound(de.meinseelenfunke.app.R.raw.sent_message_1)
    }

    fun playOrderChingSound() {
        playSound(de.meinseelenfunke.app.R.raw.order_ching)
    }

    fun release() {
        soundPool?.release()
        soundPool = null
        soundMap.clear()
        loadedMap.clear()
    }
}
