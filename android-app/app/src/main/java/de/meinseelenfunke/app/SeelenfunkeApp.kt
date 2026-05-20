package de.meinseelenfunke.app

import android.app.Application
import de.meinseelenfunke.app.di.ServiceLocator

class SeelenfunkeApp : Application() {
    override fun onCreate() {
        super.onCreate()
        // Initialize the dependency injection service locator
        ServiceLocator.init(this)
    }
}
