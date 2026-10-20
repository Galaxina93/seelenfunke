package de.meinseelenfunke.app.ui.theme

import androidx.compose.animation.animateColorAsState
import androidx.compose.animation.core.tween
import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.darkColorScheme
import androidx.compose.material3.lightColorScheme
import androidx.compose.runtime.Composable
import androidx.compose.runtime.CompositionLocalProvider
import androidx.compose.runtime.ReadOnlyComposable
import androidx.compose.runtime.staticCompositionLocalOf
import androidx.compose.ui.graphics.Color

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

val LocalAppColors = staticCompositionLocalOf {
    AppThemeColors(
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
}

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

    val animatedCardBg = animateColorAsState(
        targetValue = if (darkTheme) Slate900 else PureWhite,
        animationSpec = tween(350), label = "cardBg"
    ).value

    val animatedCardBorder = animateColorAsState(
        targetValue = if (darkTheme) GlassWhite20 else Slate200,
        animationSpec = tween(350), label = "cardBorder"
    ).value

    val animatedTextPrimary = animateColorAsState(
        targetValue = if (darkTheme) Slate50 else Slate900,
        animationSpec = tween(350), label = "textPrimary"
    ).value

    val animatedTextSecondary = animateColorAsState(
        targetValue = if (darkTheme) Slate300 else Slate600,
        animationSpec = tween(350), label = "textSecondary"
    ).value

    val animatedTextMuted = animateColorAsState(
        targetValue = if (darkTheme) Slate400 else Slate500,
        animationSpec = tween(350), label = "textMuted"
    ).value

    val animatedGlassSurface = animateColorAsState(
        targetValue = if (darkTheme) GlassWhite10 else Color(0x0DC5A059),
        animationSpec = tween(350), label = "glassSurface"
    ).value

    val animatedGlassBorder = animateColorAsState(
        targetValue = if (darkTheme) GlassBorderDark else GlassBorderLight,
        animationSpec = tween(350), label = "glassBorder"
    ).value

    val animatedInputBg = animateColorAsState(
        targetValue = if (darkTheme) Color(0x221E293B) else Slate100,
        animationSpec = tween(350), label = "inputBg"
    ).value

    val animatedTopBarBg = animateColorAsState(
        targetValue = if (darkTheme) Slate900.copy(alpha = 0.95f) else PureWhite.copy(alpha = 0.95f),
        animationSpec = tween(350), label = "topBarBg"
    ).value

    val animatedBottomBarBg = animateColorAsState(
        targetValue = if (darkTheme) SpaceBlack.copy(alpha = 0.95f) else PureWhite.copy(alpha = 0.95f),
        animationSpec = tween(350), label = "bottomBarBg"
    ).value

    val appThemeColors = AppThemeColors(
        isDark = darkTheme,
        bgGradient = if (darkTheme) CosmicDarkGradient else CosmicLightGradient,
        cardBackground = animatedCardBg,
        cardBorder = animatedCardBorder,
        textPrimary = animatedTextPrimary,
        textSecondary = animatedTextSecondary,
        textMuted = animatedTextMuted,
        primaryGold = Gold,
        glassSurface = animatedGlassSurface,
        glassBorder = animatedGlassBorder,
        inputBackground = animatedInputBg,
        inputBorder = if (darkTheme) GlassWhite20 else Slate200,
        topBarBackground = animatedTopBarBg,
        bottomBarBackground = animatedBottomBarBg,
        glowColor = if (darkTheme) GoldGlow else Color(0x1AC5A059)
    )

    CompositionLocalProvider(LocalAppColors provides appThemeColors) {
        MaterialTheme(
            colorScheme = colorScheme,
            typography = MaterialTheme.typography,
            content = content
        )
    }
}
