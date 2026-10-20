package de.meinseelenfunke.app.ui.screens

import android.content.ContextWrapper
import androidx.compose.animation.core.FastOutSlowInEasing
import androidx.compose.animation.core.RepeatMode
import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.infiniteRepeatable
import androidx.compose.animation.core.rememberInfiniteTransition
import androidx.compose.animation.core.tween
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.ui.layout.ContentScale
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Fingerprint
import androidx.compose.material.icons.filled.Lock
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.scale
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.fragment.app.FragmentActivity
import de.meinseelenfunke.app.R
import de.meinseelenfunke.app.ui.theme.AppTheme
import de.meinseelenfunke.app.ui.theme.Gold
import de.meinseelenfunke.app.ui.theme.SpaceBlack
import de.meinseelenfunke.app.util.BiometricHelper

@Composable
fun BiometricLockScreen(
    onUnlockSuccess: () -> Unit,
    onUsePassword: () -> Unit
) {
    val context = LocalContext.current
    val activity = remember(context) {
        var ctx = context
        while (ctx is ContextWrapper) {
            if (ctx is FragmentActivity) break
            ctx = ctx.baseContext
        }
        ctx as? FragmentActivity
    }

    val triggerBiometricPrompt = {
        if (activity != null && BiometricHelper.isBiometricAvailable(context)) {
            BiometricHelper.showBiometricPrompt(
                activity = activity,
                title = "Seelenfunke entsperren",
                subtitle = "Bestätige mit deinem Fingerabdruck",
                negativeButtonText = "Passwort nutzen",
                onSuccess = { onUnlockSuccess() },
                onError = { errorCode, _ ->
                    // 13 = ERROR_NEGATIVE_BUTTON (User clicked "Passwort nutzen")
                    if (errorCode == 13) {
                        onUsePassword()
                    }
                }
            )
        }
    }

    // Auto-prompt upon display
    LaunchedEffect(Unit) {
        triggerBiometricPrompt()
    }

    // Ambient pulsing animation
    val infiniteTransition = rememberInfiniteTransition(label = "haloTransition")
    val pulseScale by infiniteTransition.animateFloat(
        initialValue = 0.96f,
        targetValue = 1.05f,
        animationSpec = infiniteRepeatable(
            animation = tween(2200, easing = FastOutSlowInEasing),
            repeatMode = RepeatMode.Reverse
        ),
        label = "pulseScale"
    )

    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(androidx.compose.ui.graphics.Brush.verticalGradient(AppTheme.colors.bgGradient))
            .padding(24.dp),
        contentAlignment = Alignment.Center
    ) {
        Column(
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.Center
        ) {
            // App Logo
            Image(
                painter = painterResource(id = R.drawable.mein_seelenfunke_logo),
                contentDescription = "Seelenfunke Logo",
                modifier = Modifier
                    .width(180.dp)
                    .height(48.dp),
                contentScale = androidx.compose.ui.layout.ContentScale.Fit
            )

            Spacer(modifier = Modifier.height(20.dp))

            Text(
                text = "Willkommen zurück",
                fontSize = 24.sp,
                fontWeight = FontWeight.Bold,
                color = AppTheme.colors.textPrimary
            )

            Spacer(modifier = Modifier.height(6.dp))

            Text(
                text = "Bestätige deine Identität per Fingerabdruck,\num Seelenfunke zu entsperren.",
                fontSize = 13.sp,
                color = AppTheme.colors.textMuted,
                textAlign = TextAlign.Center
            )

            Spacer(modifier = Modifier.height(48.dp))

            // Large pulsing Fingerprint Button
            Box(
                modifier = Modifier
                    .size(96.dp)
                    .scale(pulseScale)
                    .clip(CircleShape)
                    .background(Color(0x22C5A059))
                    .border(2.dp, Gold, CircleShape)
                    .clickable { triggerBiometricPrompt() },
                contentAlignment = Alignment.Center
            ) {
                Icon(
                    imageVector = Icons.Default.Fingerprint,
                    contentDescription = "Fingerabdruck scannen",
                    tint = Gold,
                    modifier = Modifier.size(52.dp)
                )
            }

            Spacer(modifier = Modifier.height(32.dp))

            Button(
                onClick = { triggerBiometricPrompt() },
                colors = ButtonDefaults.buttonColors(
                    containerColor = Gold,
                    contentColor = SpaceBlack
                ),
                shape = RoundedCornerShape(12.dp)
            ) {
                Text(
                    text = "Mit Fingerabdruck entsperren",
                    fontWeight = FontWeight.Bold,
                    fontSize = 14.sp
                )
            }

            Spacer(modifier = Modifier.height(16.dp))

            TextButton(onClick = onUsePassword) {
                Text(
                    text = "Mit Passwort anmelden / Abmelden",
                    color = AppTheme.colors.textMuted,
                    fontSize = 13.sp
                )
            }
        }
    }
}
