package de.meinseelenfunke.app.util

import android.content.Context
import android.util.Log

object AppLogger {
    fun info(context: Context?, tag: String, message: String) {
        Log.i(tag, message)
    }

    fun error(context: Context?, tag: String, message: String, throwable: Throwable? = null) {
        if (throwable != null) {
            Log.e(tag, message, throwable)
        } else {
            Log.e(tag, message)
        }
    }

    fun debug(context: Context?, tag: String, message: String) {
        Log.d(tag, message)
    }

    fun warn(context: Context?, tag: String, message: String) {
        Log.w(tag, message)
    }
}
