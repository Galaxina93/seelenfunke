package de.meinseelenfunke.app.util

import android.content.Context
import android.content.pm.ApplicationInfo
import android.util.Log
import java.io.File

object AppLogger {
    @Volatile
    private var legacyLogCleaned = false

    private fun isDebuggable(context: Context?): Boolean {
        val ctx = context ?: try { de.meinseelenfunke.app.di.ServiceLocator.context } catch (_: Exception) { null } ?: return false
        val debuggable = (ctx.applicationInfo.flags and ApplicationInfo.FLAG_DEBUGGABLE) != 0
        if (!debuggable && !legacyLogCleaned) {
            legacyLogCleaned = true
            try { File(ctx.filesDir, "seelenfunke_debug_logs.txt").delete() } catch (_: Exception) {}
            try { File(ctx.cacheDir, "seelenfunke_debug_logs.txt").delete() } catch (_: Exception) {}
        }
        return debuggable
    }

    fun info(context: Context?, tag: String, message: String) {
        if (isDebuggable(context)) {
            Log.i(tag, message)
        }
    }

    fun error(context: Context?, tag: String, message: String, throwable: Throwable? = null) {
        if (isDebuggable(context)) {
            if (throwable != null) {
                Log.e(tag, message, throwable)
            } else {
                Log.e(tag, message)
            }
        }
    }

    fun debug(context: Context?, tag: String, message: String) {
        if (isDebuggable(context)) {
            Log.d(tag, message)
        }
    }

    fun warn(context: Context?, tag: String, message: String) {
        if (isDebuggable(context)) {
            Log.w(tag, message)
        }
    }
}
