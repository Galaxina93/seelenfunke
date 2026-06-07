package de.meinseelenfunke.app.receiver

import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.util.Log
import de.meinseelenfunke.app.util.CalendarAlarmScheduler

class BootReceiver : BroadcastReceiver() {
    override fun onReceive(context: Context, intent: Intent) {
        if (intent.action == Intent.ACTION_BOOT_COMPLETED) {
            Log.d("BootReceiver", "Boot completed. Rescheduling all calendar alarms from cache...")
            CalendarAlarmScheduler.rescheduleAlarmsFromCache(context)
        }
    }
}
