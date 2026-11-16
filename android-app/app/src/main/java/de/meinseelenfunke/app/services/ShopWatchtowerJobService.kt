package de.meinseelenfunke.app.services

import android.app.job.JobParameters
import android.app.job.JobService
import de.meinseelenfunke.app.util.AgDirectInboxManager
import de.meinseelenfunke.app.util.ErrorSentinelReporter
import de.meinseelenfunke.app.util.ShopWatchtowerManager
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.Job
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.launch

class ShopWatchtowerJobService : JobService() {
    private val serviceScope = CoroutineScope(SupervisorJob() + Dispatchers.IO)
    private var activeJob: Job? = null

    override fun onStartJob(params: JobParameters?): Boolean {
        activeJob = serviceScope.launch {
            try {
                ErrorSentinelReporter.triggerAsyncFlush(applicationContext)
                try {
                    AgDirectInboxManager.flushOutbox(applicationContext)
                } catch (_: Throwable) {
                }
                ShopWatchtowerManager.runCheckNow(applicationContext)
            } catch (_: Throwable) {
            } finally {
                if (params != null) {
                    jobFinished(params, false)
                }
            }
        }
        return true
    }

    override fun onStopJob(params: JobParameters?): Boolean {
        activeJob?.cancel()
        return true
    }
}
