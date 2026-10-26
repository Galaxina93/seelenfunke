package de.meinseelenfunke.app.ui.theme

import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.darkColorScheme
import androidx.compose.material3.lightColorScheme
import androidx.compose.runtime.Composable
import androidx.compose.runtime.CompositionLocalProvider
import androidx.compose.runtime.Immutable
import androidx.compose.runtime.ReadOnlyComposable
import androidx.compose.runtime.remember
import androidx.compose.runtime.staticCompositionLocalOf
import androidx.compose.ui.graphics.Color

@Immutable
data class AppThemeColors(
    val isDark: Boolean,
    val bgGradient: List<Color>,
    val cardBackground: Color,
    val cardBorder: Color,
    val textPrimary: Color,
    val textSecondary: Color,
    val textMuted: Color,
    val primaryGold: Color,
    val glassSurface: Color,
    val glassBorder: Color,
    val inputBackground: Color,
    val inputBorder: Color,
    val topBarBackground: Color,
    val bottomBarBackground: Color,
    val glowColor: Color
)

private val DarkAppThemeColors = AppThemeColors(
    isDark = true,
    bgGradient = CosmicDarkGradient,
    cardBackground = Slate900,
    cardBorder = GlassWhite20,
    textPrimary = Slate50,
    textSecondary = Slate300,
    textMuted = Slate400,
    primaryGold = Gold,
    glassSurface = GlassWhite10,
    glassBorder = GlassBorderDark,
    inputBackground = Color(0x221E293B),
    inputBorder = GlassWhite20,
    topBarBackground = Slate900.copy(alpha = 0.95f),
    bottomBarBackground = SpaceBlack.copy(alpha = 0.95f),
    glowColor = GoldGlow
)

private val LightAppThemeColors = AppThemeColors(
    isDark = false,
    bgGradient = CosmicLightGradient,
    cardBackground = PureWhite,
    cardBorder = Slate200,
    textPrimary = Slate900,
    textSecondary = Slate600,
    textMuted = Slate500,
    primaryGold = Gold,
    glassSurface = Color(0x0DC5A059),
    glassBorder = GlassBorderLight,
    inputBackground = Slate100,
    inputBorder = Slate200,
    topBarBackground = PureWhite.copy(alpha = 0.95f),
    bottomBarBackground = PureWhite.copy(alpha = 0.95f),
    glowColor = Color(0x1AC5A059)
)

val LocalAppColors = staticCompositionLocalOf { DarkAppThemeColors }

object AppTheme {
    val colors: AppThemeColors
        @Composable
        @ReadOnlyComposable
        get() = LocalAppColors.current
}

private val DarkColorScheme = darkColorScheme(
    primary = Gold,
    secondary = Copper,
    tertiary = SpaceBlue,
    background = SpaceBlack,
    surface = Slate900,
    surfaceVariant = Slate800,
    onPrimary = SpaceBlack,
    onSecondary = SpaceBlack,
    onTertiary = Slate50,
    onBackground = Slate50,
    onSurface = Slate50,
    onSurfaceVariant = Slate400,
    outline = GlassWhite20,
    error = Rose500,
    onError = Color.White
)

private val LightColorScheme = lightColorScheme(
    primary = Gold,
    secondary = Copper,
    tertiary = SpaceBlue,
    background = PearlBg,
    surface = PureWhite,
    surfaceVariant = Slate100,
    onPrimary = SpaceBlack,
    onSecondary = PureWhite,
    onTertiary = PureWhite,
    onBackground = Slate900,
    onSurface = Slate900,
    onSurfaceVariant = Slate600,
    outline = Slate200,
    error = Rose500,
    onError = PureWhite
)

@Composable
fun SeelenfunkeTheme(
    darkTheme: Boolean = isSystemInDarkTheme(),
    content: @Composable () -> Unit
) {
    val colorScheme = if (darkTheme) DarkColorScheme else LightColorScheme
    val appThemeColors = remember(darkTheme) {
        if (darkTheme) DarkAppThemeColors else LightAppThemeColors
    }

    CompositionLocalProvider(LocalAppColors provides appThemeColors) {
        MaterialTheme(
            colorScheme = colorScheme,
            typography = MaterialTheme.typography,
            content = content
        )
    }
}
