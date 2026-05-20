package de.meinseelenfunke.app

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import de.meinseelenfunke.app.ui.navigation.AppNavigation
import de.meinseelenfunke.app.ui.theme.SeelenfunkeTheme

class MainActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        // Enable edge-to-edge system bar layout
        enableEdgeToEdge()
        setContent {
            SeelenfunkeTheme {
                AppNavigation()
            }
        }
    }
}
