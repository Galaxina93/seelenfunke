package de.meinseelenfunke.app.receiver

import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.util.Log
import de.meinseelenfunke.app.util.CalendarAlarmScheduler
import de.meinseelenfunke.app.util.FunkenwerkAlarmManager

class BootReceiver : BroadcastReceiver() {
    override fun onReceive(context: Context, intent: Intent) {
        if (intent.action == Intent.ACTION_BOOT_COMPLETED) {
            de.meinseelenfunke.app.di.ServiceLocator.ensureInitialized(context.applicationContext)
            CalendarAlarmScheduler.rescheduleAlarmsFromCache(context)
            FunkenwerkAlarmManager.ensureInitialized(context)
            FunkenwerkAlarmManager.scheduleAllActiveAlarms(context)
        }
    }
}
