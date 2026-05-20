package de.meinseelenfunke.app.ui.theme

import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.darkColorScheme
import androidx.compose.runtime.Composable
import androidx.compose.ui.graphics.Color

private val DarkColorScheme = darkColorScheme(
    primary = Cyan500,
    secondary = Emerald500,
    tertiary = Indigo500,
    background = Slate900,
    surface = Slate800,
    onPrimary = Slate900,
    onSecondary = Slate900,
    onTertiary = Slate50,
    onBackground = Slate50,
    onSurface = Slate50,
    error = Rose500,
    onError = Color.White
)

@Composable
fun SeelenfunkeTheme(
    darkTheme: Boolean = isSystemInDarkTheme(),
    content: @Composable () -> Unit
) {
    // We enforce Dark Theme as the default premium dashboard style
    val colorScheme = DarkColorScheme

    MaterialTheme(
        colorScheme = colorScheme,
        typography = MaterialTheme.typography, // standard defaults
        content = content
    )
}
