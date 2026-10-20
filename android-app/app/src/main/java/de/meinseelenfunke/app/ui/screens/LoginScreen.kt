package de.meinseelenfunke.app.ui.screens

import androidx.compose.animation.AnimatedVisibility
import androidx.compose.animation.core.FastOutSlowInEasing
import androidx.compose.animation.core.RepeatMode
import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.infiniteRepeatable
import androidx.compose.animation.core.rememberInfiniteTransition
import androidx.compose.animation.core.tween
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.interaction.collectIsPressedAsState
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Computer
import androidx.compose.material.icons.filled.DarkMode
import androidx.compose.material.icons.filled.ErrorOutline
import androidx.compose.material.icons.filled.Fingerprint
import androidx.compose.material.icons.filled.LightMode
import androidx.compose.material.icons.filled.Public
import androidx.compose.material.icons.filled.Refresh
import androidx.compose.material.icons.filled.Settings
import androidx.compose.material.icons.filled.Usb
import androidx.compose.material.icons.filled.Visibility
import androidx.compose.material.icons.filled.VisibilityOff
import androidx.compose.material.icons.filled.Wifi
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.Checkbox
import androidx.compose.material3.CheckboxDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.OutlinedTextFieldDefaults
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.ExperimentalComposeUiApi
import androidx.compose.ui.Modifier
import androidx.compose.ui.autofill.AutofillNode
import androidx.compose.ui.autofill.AutofillType
import androidx.compose.ui.draw.clip
import androidx.compose.ui.focus.onFocusChanged
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.layout.boundsInWindow
import androidx.compose.ui.layout.onGloballyPositioned
import androidx.compose.ui.platform.LocalAutofill
import androidx.compose.ui.platform.LocalAutofillTree
import androidx.compose.ui.platform.LocalContext
import android.widget.Toast
import android.content.ContextWrapper
import androidx.fragment.app.FragmentActivity
import de.meinseelenfunke.app.util.BiometricHelper
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.text.input.VisualTransformation
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.viewmodel.compose.viewModel
import de.meinseelenfunke.app.R
import de.meinseelenfunke.app.di.ServiceLocator
import de.meinseelenfunke.app.ui.theme.AppTheme
import de.meinseelenfunke.app.ui.theme.Gold
import de.meinseelenfunke.app.ui.theme.GoldGradient
import de.meinseelenfunke.app.ui.theme.Rose500
import de.meinseelenfunke.app.ui.theme.SpaceBlack

@OptIn(ExperimentalComposeUiApi::class)
@Composable
fun LoginScreen(
    onLoginSuccess: () -> Unit,
    viewModel: LoginViewModel = viewModel()
) {
    val loginState by viewModel.loginState.collectAsState()
    val baseUrl by viewModel.baseUrl.collectAsState()
    val passwordResetState by viewModel.passwordResetState.collectAsState()

    var email by remember { mutableStateOf("") }
    var password by remember { mutableStateOf("") }
    var isPasswordVisible by remember { mutableStateOf(false) }
    var rememberMe by remember { mutableStateOf(viewModel.isRememberMeEnabled()) }
    var isEditingUrl by remember { mutableStateOf(false) }
    var inputUrl by remember { mutableStateOf(baseUrl) }
    var showForgotPasswordDialog by remember { mutableStateOf(false) }
    var forgotPasswordEmail by remember { mutableStateOf("") }

    val context = LocalContext.current
    val activity = remember(context) {
        var ctx = context
        while (ctx is ContextWrapper) {
            if (ctx is FragmentActivity) break
            ctx = ctx.baseContext
        }
        ctx as? FragmentActivity
    }
    var hasAutoPromptedBiometric by remember { mutableStateOf(false) }

    // Pre-populate credentials & auto-prompt biometric if available
    LaunchedEffect(Unit) {
        if (viewModel.isRememberMeEnabled()) {
            email = viewModel.getSavedEmail()
            val savedPass = viewModel.getSavedPassword()
            if (savedPass.isNotBlank()) {
                password = savedPass
            }

            if (!hasAutoPromptedBiometric
                && activity != null
                && viewModel.isBiometricEnabled()
                && BiometricHelper.isBiometricAvailable(context)
                && email.isNotBlank()
                && savedPass.isNotBlank()
            ) {
                hasAutoPromptedBiometric = true
                BiometricHelper.showBiometricPrompt(
                    activity = activity,
                    title = "Biometrische Anmeldung",
                    subtitle = "Bestätige mit deinem Fingerabdruck",
                    negativeButtonText = "Passwort nutzen",
                    onSuccess = {
                        viewModel.login(email, savedPass, rememberMe = true)
                    },
                    onError = { _, _ -> /* Stay on screen */ },
                    onFailed = { /* Failed */ }
                )
            }
        }
    }

    LaunchedEffect(loginState) {
        if (loginState is LoginState.Success) {
            onLoginSuccess()
        }
    }

    // Keep inputUrl in sync with baseUrl updates
    LaunchedEffect(baseUrl) {
        inputUrl = baseUrl
    }

    // Ambient halo breathing animation
    val infiniteTransition = rememberInfiniteTransition(label = "cosmicAmbience")
    val haloAlpha by infiniteTransition.animateFloat(
        initialValue = if (AppTheme.colors.isDark) 0.12f else 0.08f,
        targetValue = if (AppTheme.colors.isDark) 0.32f else 0.20f,
        animationSpec = infiniteRepeatable(
            animation = tween(3500, easing = FastOutSlowInEasing),
            repeatMode = RepeatMode.Reverse
        ),
        label = "haloAlpha"
    )
    val floatOffset by infiniteTransition.animateFloat(
        initialValue = -3f,
        targetValue = 3f,
        animationSpec = infiniteRepeatable(
            animation = tween(2800, easing = FastOutSlowInEasing),
            repeatMode = RepeatMode.Reverse
        ),
        label = "floatOffset"
    )

    // Button press scale interaction
    val buttonInteraction = remember { MutableInteractionSource() }
    val isButtonPressed by buttonInteraction.collectIsPressedAsState()
    val buttonScale by animateFloatAsState(
        targetValue = if (isButtonPressed) 0.97f else 1f,
        animationSpec = tween(120),
        label = "buttonScale"
    )

    // Autofill setups
    val autofill = LocalAutofill.current
    val emailAutofillNode = remember {
        AutofillNode(
            autofillTypes = listOf(AutofillType.EmailAddress),
            onFill = { email = it }
        )
    }
    val passwordAutofillNode = remember {
        AutofillNode(
            autofillTypes = listOf(AutofillType.Password),
            onFill = { password = it }
        )
    }
    LocalAutofillTree.current += emailAutofillNode
    LocalAutofillTree.current += passwordAutofillNode

    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(Brush.verticalGradient(AppTheme.colors.bgGradient))
    ) {
        // Ambient Golden Halo background glow
        Box(
            modifier = Modifier
                .size(340.dp)
                .align(Alignment.TopCenter)
                .padding(top = 60.dp)
                .graphicsLayer { alpha = haloAlpha }
                .background(
                    Brush.radialGradient(
                        colors = listOf(Gold.copy(alpha = 0.6f), Color.Transparent)
                    ),
                    shape = CircleShape
                )
        )

        Column(
            modifier = Modifier
                .fillMaxSize()
                .statusBarsPadding()
                .padding(horizontal = 24.dp)
                .verticalScroll(rememberScrollState()),
            horizontalAlignment = Alignment.CenterHorizontally
        ) {
            // Top Action Bar: Theme Switch & Server Status Pill
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(top = 12.dp, bottom = 16.dp),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                // Server Quick Badge
                val serverLabel = when {
                    baseUrl.contains("192.168.188.119") -> "WLAN PC (80)"
                    baseUrl.contains("127.0.0.1") -> "USB ADB (80)"
                    baseUrl.contains("10.0.2.2") -> "Emulator (80)"
                    baseUrl.contains("stage") -> "Stage Online"
                    else -> "Custom Server"
                }

                Row(
                    modifier = Modifier
                        .clip(RoundedCornerShape(20.dp))
                        .background(AppTheme.colors.glassSurface)
                        .border(1.dp, AppTheme.colors.glassBorder, RoundedCornerShape(20.dp))
                        .clickable { isEditingUrl = !isEditingUrl }
                        .padding(horizontal = 10.dp, vertical = 6.dp),
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(6.dp)
                ) {
                    Box(
                        modifier = Modifier
                            .size(7.dp)
                            .background(Gold, CircleShape)
                    )
                    Text(
                        text = serverLabel,
                        color = AppTheme.colors.textMuted,
                        fontSize = 11.sp,
                        fontWeight = FontWeight.Medium
                    )
                }

                // Sleek Theme Toggle Pill (Dark / Light)
                Row(
                    modifier = Modifier
                        .clip(RoundedCornerShape(20.dp))
                        .background(AppTheme.colors.glassSurface)
                        .border(1.dp, AppTheme.colors.glassBorder, RoundedCornerShape(20.dp))
                        .clickable { ServiceLocator.toggleTheme() }
                        .padding(horizontal = 10.dp, vertical = 5.dp),
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(6.dp)
                ) {
                    Icon(
                        imageVector = if (AppTheme.colors.isDark) Icons.Default.LightMode else Icons.Default.DarkMode,
                        contentDescription = "Theme umschalten",
                        tint = Gold,
                        modifier = Modifier.size(16.dp)
                    )
                    Text(
                        text = if (AppTheme.colors.isDark) "Hell" else "Dunkel",
                        color = AppTheme.colors.textPrimary,
                        fontSize = 12.sp,
                        fontWeight = FontWeight.SemiBold
                    )
                }
            }

            Spacer(modifier = Modifier.height(16.dp))

            // Cosmic Logo with subtle floating animation
            Image(
                painter = painterResource(id = R.drawable.mein_seelenfunke_logo),
                contentDescription = "Seelenfunke Logo",
                modifier = Modifier
                    .height(125.dp)
                    .graphicsLayer { translationY = floatOffset },
                contentScale = ContentScale.Fit
            )

            Spacer(modifier = Modifier.height(28.dp))

            // Glassmorphic Card Container
            Card(
                modifier = Modifier.fillMaxWidth(),
                colors = CardDefaults.cardColors(
                    containerColor = AppTheme.colors.cardBackground.copy(
                        alpha = if (AppTheme.colors.isDark) 0.75f else 0.92f
                    )
                ),
                border = BorderStroke(1.dp, AppTheme.colors.cardBorder),
                shape = RoundedCornerShape(24.dp)
            ) {
                Column(
                    modifier = Modifier.padding(24.dp),
                    verticalArrangement = Arrangement.spacedBy(16.dp)
                ) {
                    Column(verticalArrangement = Arrangement.spacedBy(4.dp)) {
                        Text(
                            text = "Willkommen zurück",
                            fontSize = 22.sp,
                            fontWeight = FontWeight.Bold,
                            color = AppTheme.colors.textPrimary
                        )
                        Text(
                            text = "Melde dich an, um deinen Funken zu entfachen",
                            fontSize = 12.sp,
                            color = AppTheme.colors.textMuted
                        )
                    }

                    // Email input
                    OutlinedTextField(
                        value = email,
                        onValueChange = { email = it },
                        label = { Text("E-Mail-Adresse") },
                        singleLine = true,
                        modifier = Modifier
                            .fillMaxWidth()
                            .onGloballyPositioned { emailAutofillNode.boundingBox = it.boundsInWindow() }
                            .onFocusChanged { focusState ->
                                autofill?.run {
                                    if (focusState.isFocused) {
                                        requestAutofillForNode(emailAutofillNode)
                                    } else {
                                        cancelAutofillForNode(emailAutofillNode)
                                    }
                                }
                            },
                        keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Email),
                        shape = RoundedCornerShape(12.dp),
                        colors = OutlinedTextFieldDefaults.colors(
                            focusedBorderColor = Gold,
                            unfocusedBorderColor = AppTheme.colors.inputBorder,
                            focusedLabelColor = Gold,
                            unfocusedLabelColor = AppTheme.colors.textMuted,
                            focusedTextColor = AppTheme.colors.textPrimary,
                            unfocusedTextColor = AppTheme.colors.textPrimary,
                            focusedContainerColor = AppTheme.colors.inputBackground,
                            unfocusedContainerColor = AppTheme.colors.inputBackground
                        )
                    )

                    // Password input with visibility toggle
                    OutlinedTextField(
                        value = password,
                        onValueChange = { password = it },
                        label = { Text("Passwort") },
                        singleLine = true,
                        modifier = Modifier
                            .fillMaxWidth()
                            .onGloballyPositioned { passwordAutofillNode.boundingBox = it.boundsInWindow() }
                            .onFocusChanged { focusState ->
                                autofill?.run {
                                    if (focusState.isFocused) {
                                        requestAutofillForNode(passwordAutofillNode)
                                    } else {
                                        cancelAutofillForNode(passwordAutofillNode)
                                    }
                                }
                            },
                        visualTransformation = if (isPasswordVisible) VisualTransformation.None else PasswordVisualTransformation(),
                        keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Password),
                        trailingIcon = {
                            IconButton(onClick = { isPasswordVisible = !isPasswordVisible }) {
                                Icon(
                                    imageVector = if (isPasswordVisible) Icons.Filled.Visibility else Icons.Filled.VisibilityOff,
                                    contentDescription = if (isPasswordVisible) "Passwort verbergen" else "Passwort anzeigen",
                                    tint = AppTheme.colors.textMuted
                                )
                            }
                        },
                        shape = RoundedCornerShape(12.dp),
                        colors = OutlinedTextFieldDefaults.colors(
                            focusedBorderColor = Gold,
                            unfocusedBorderColor = AppTheme.colors.inputBorder,
                            focusedLabelColor = Gold,
                            unfocusedLabelColor = AppTheme.colors.textMuted,
                            focusedTextColor = AppTheme.colors.textPrimary,
                            unfocusedTextColor = AppTheme.colors.textPrimary,
                            focusedContainerColor = AppTheme.colors.inputBackground,
                            unfocusedContainerColor = AppTheme.colors.inputBackground
                        )
                    )

                    // Remember Me & Forgot Password Action Row
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.SpaceBetween
                    ) {
                        Row(
                            verticalAlignment = Alignment.CenterVertically,
                            modifier = Modifier.clickable { rememberMe = !rememberMe }
                        ) {
                            Checkbox(
                                checked = rememberMe,
                                onCheckedChange = { rememberMe = it },
                                colors = CheckboxDefaults.colors(
                                    checkedColor = Gold,
                                    uncheckedColor = AppTheme.colors.textMuted,
                                    checkmarkColor = SpaceBlack
                                )
                            )
                            Text(
                                text = "Angemeldet bleiben",
                                color = AppTheme.colors.textSecondary,
                                fontSize = 13.sp
                            )
                        }

                        TextButton(onClick = {
                            forgotPasswordEmail = email
                            showForgotPasswordDialog = true
                        }) {
                            Text("Passwort vergessen?", color = Gold, fontSize = 12.sp, fontWeight = FontWeight.SemiBold)
                        }
                    }

                    // Error feedback Banner with auto-fix suggestion
                    if (loginState is LoginState.Error) {
                        val errorMsg = (loginState as LoginState.Error).message
                        Card(
                            colors = CardDefaults.cardColors(containerColor = Rose500.copy(alpha = 0.12f)),
                            border = BorderStroke(1.dp, Rose500.copy(alpha = 0.35f)),
                            shape = RoundedCornerShape(12.dp),
                            modifier = Modifier.fillMaxWidth()
                        ) {
                            Column(modifier = Modifier.padding(12.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
                                Row(
                                    verticalAlignment = Alignment.CenterVertically,
                                    horizontalArrangement = Arrangement.spacedBy(8.dp)
                                ) {
                                    Icon(Icons.Default.ErrorOutline, contentDescription = null, tint = Rose500, modifier = Modifier.size(18.dp))
                                    Text(
                                        text = errorMsg,
                                        color = Rose500,
                                        fontSize = 12.sp,
                                        fontWeight = FontWeight.Medium
                                    )
                                }

                                if (errorMsg.contains("127.0.0.1") || errorMsg.contains("connect") || errorMsg.contains("8081")) {
                                    Button(
                                        onClick = {
                                            viewModel.updateBaseUrl("http://192.168.188.119/api/")
                                            viewModel.login(email, password, rememberMe)
                                        },
                                        colors = ButtonDefaults.buttonColors(containerColor = Gold, contentColor = SpaceBlack),
                                        shape = RoundedCornerShape(8.dp),
                                        modifier = Modifier.fillMaxWidth().padding(top = 4.dp)
                                    ) {
                                        Text("👉 Zu WLAN PC Server (192.168.188.119) wechseln", fontSize = 11.sp, fontWeight = FontWeight.Bold)
                                    }
                                }
                            }
                        }
                    }

                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.spacedBy(10.dp),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        // Modern Golden Gradient Login Button
                        Button(
                            onClick = { viewModel.login(email, password, rememberMe) },
                            modifier = Modifier
                                .weight(1f)
                                .height(50.dp)
                                .graphicsLayer {
                                    scaleX = buttonScale
                                    scaleY = buttonScale
                                },
                            enabled = loginState !is LoginState.Loading && email.isNotBlank() && password.isNotBlank(),
                            shape = RoundedCornerShape(14.dp),
                            colors = ButtonDefaults.buttonColors(
                                containerColor = Color.Transparent,
                                disabledContainerColor = Color.Gray.copy(alpha = 0.2f)
                            ),
                            interactionSource = buttonInteraction
                        ) {
                            Box(
                                modifier = Modifier
                                    .fillMaxSize()
                                    .background(
                                        if (email.isNotBlank() && password.isNotBlank()) {
                                            Brush.horizontalGradient(GoldGradient)
                                        } else {
                                            Brush.horizontalGradient(listOf(Color(0xFF555555), Color(0xFF444444)))
                                        },
                                        RoundedCornerShape(14.dp)
                                    ),
                                contentAlignment = Alignment.Center
                            ) {
                                if (loginState is LoginState.Loading) {
                                    CircularProgressIndicator(
                                        color = SpaceBlack,
                                        modifier = Modifier.size(20.dp),
                                        strokeWidth = 2.5.dp
                                    )
                                } else {
                                    Text(
                                        text = "Einloggen",
                                        color = if (email.isNotBlank() && password.isNotBlank()) SpaceBlack else Color.LightGray,
                                        fontWeight = FontWeight.Bold,
                                        fontSize = 15.sp
                                    )
                                }
                            }
                        }

                        // Dedicated Biometric Button
                        if (activity != null && BiometricHelper.isBiometricAvailable(context)) {
                            Box(
                                modifier = Modifier
                                    .size(50.dp)
                                    .clip(RoundedCornerShape(14.dp))
                                    .background(AppTheme.colors.glassSurface)
                                    .border(1.5.dp, Gold, RoundedCornerShape(14.dp))
                                    .clickable {
                                        val promptEmail = email.ifBlank { viewModel.getSavedEmail() }
                                        val promptPassword = password.ifBlank { viewModel.getSavedPassword() }
                                        if (promptEmail.isNotBlank() && promptPassword.isNotBlank()) {
                                            BiometricHelper.showBiometricPrompt(
                                                activity = activity,
                                                title = "Biometrische Anmeldung",
                                                subtitle = "Bestätige mit deinem Fingerabdruck",
                                                negativeButtonText = "Abbrechen",
                                                onSuccess = {
                                                    viewModel.login(promptEmail, promptPassword, rememberMe = true)
                                                }
                                            )
                                        } else {
                                            Toast.makeText(context, "Bitte erst einmal mit E-Mail & Passwort anmelden", Toast.LENGTH_SHORT).show()
                                        }
                                    },
                                contentAlignment = Alignment.Center
                            ) {
                                Icon(
                                    imageVector = Icons.Default.Fingerprint,
                                    contentDescription = "Biometrischer Login",
                                    tint = Gold,
                                    modifier = Modifier.size(28.dp)
                                )
                            }
                        }
                    }

                    // Password Reset expandable
                    AnimatedVisibility(visible = showForgotPasswordDialog) {
                        Column(
                            modifier = Modifier
                                .fillMaxWidth()
                                .padding(top = 4.dp)
                                .background(AppTheme.colors.glassSurface, RoundedCornerShape(12.dp))
                                .border(1.dp, AppTheme.colors.glassBorder, RoundedCornerShape(12.dp))
                                .padding(12.dp),
                            verticalArrangement = Arrangement.spacedBy(8.dp)
                        ) {
                            Text(
                                text = "Passwort zurücksetzen",
                                color = AppTheme.colors.textPrimary,
                                fontWeight = FontWeight.Bold,
                                fontSize = 13.sp
                            )
                            Text(
                                text = "Gib deine E-Mail-Adresse ein, um einen Zurücksetzungs-Link zu erhalten.",
                                color = AppTheme.colors.textMuted,
                                fontSize = 11.sp
                            )
                            OutlinedTextField(
                                value = forgotPasswordEmail,
                                onValueChange = { forgotPasswordEmail = it },
                                label = { Text("E-Mail-Adresse") },
                                singleLine = true,
                                modifier = Modifier.fillMaxWidth(),
                                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Email),
                                colors = OutlinedTextFieldDefaults.colors(
                                    focusedBorderColor = Gold,
                                    unfocusedBorderColor = AppTheme.colors.inputBorder,
                                    focusedTextColor = AppTheme.colors.textPrimary,
                                    unfocusedTextColor = AppTheme.colors.textPrimary
                                )
                            )

                            passwordResetState?.onSuccess {
                                Text(
                                    text = "Zurücksetzungs-Link wurde versendet!",
                                    color = Gold,
                                    fontSize = 12.sp,
                                    fontWeight = FontWeight.Medium
                                )
                            }?.onFailure { error ->
                                Text(
                                    text = error.localizedMessage ?: "Fehler beim Senden.",
                                    color = Rose500,
                                    fontSize = 12.sp
                                )
                            }

                            Row(
                                modifier = Modifier.fillMaxWidth(),
                                horizontalArrangement = Arrangement.End
                            ) {
                                TextButton(onClick = {
                                    showForgotPasswordDialog = false
                                    viewModel.clearPasswordResetState()
                                }) {
                                    Text("Abbrechen", color = AppTheme.colors.textMuted, fontSize = 12.sp)
                                }
                                Spacer(modifier = Modifier.width(8.dp))
                                Button(
                                    onClick = { viewModel.sendPasswordResetEmail(forgotPasswordEmail) },
                                    colors = ButtonDefaults.buttonColors(containerColor = Gold, contentColor = SpaceBlack),
                                    shape = RoundedCornerShape(8.dp)
                                ) {
                                    Text("Link anfordern", fontSize = 12.sp, fontWeight = FontWeight.Bold)
                                }
                            }
                        }
                    }
                }
            }

            Spacer(modifier = Modifier.height(24.dp))

            // Expandable Modern API Server Selector
            AnimatedVisibility(visible = isEditingUrl) {
                Card(
                    modifier = Modifier.fillMaxWidth(),
                    colors = CardDefaults.cardColors(containerColor = AppTheme.colors.cardBackground),
                    border = BorderStroke(1.dp, AppTheme.colors.cardBorder),
                    shape = RoundedCornerShape(16.dp)
                ) {
                    Column(
                        modifier = Modifier.padding(16.dp),
                        verticalArrangement = Arrangement.spacedBy(10.dp)
                    ) {
                        Text(
                            text = "Serververbindung wählen",
                            color = AppTheme.colors.textPrimary,
                            fontSize = 14.sp,
                            fontWeight = FontWeight.Bold
                        )
                        Text(
                            text = "Wähle deinen Server oder trage deine IP-Adresse ein:",
                            color = AppTheme.colors.textMuted,
                            fontSize = 11.sp
                        )

                        // Presets Grid
                        val presets = listOf(
                            Triple("WLAN PC", "http://192.168.188.119/api/", Icons.Default.Wifi),
                            Triple("USB ADB", "http://127.0.0.1/api/", Icons.Default.Usb),
                            Triple("Emulator", "http://10.0.2.2/api/", Icons.Default.Computer),
                            Triple("Stage Online", "https://stage.mein-seelenfunke.de/api/", Icons.Default.Public)
                        )

                        Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
                            presets.forEach { (name, url, icon) ->
                                val isSelected = baseUrl == url
                                Row(
                                    modifier = Modifier
                                        .fillMaxWidth()
                                        .clip(RoundedCornerShape(8.dp))
                                        .background(if (isSelected) Gold.copy(alpha = 0.15f) else AppTheme.colors.glassSurface)
                                        .border(
                                            1.dp,
                                            if (isSelected) Gold else AppTheme.colors.glassBorder,
                                            RoundedCornerShape(8.dp)
                                        )
                                        .clickable {
                                            viewModel.updateBaseUrl(url)
                                            inputUrl = url
                                        }
                                        .padding(horizontal = 12.dp, vertical = 8.dp),
                                    verticalAlignment = Alignment.CenterVertically,
                                    horizontalArrangement = Arrangement.SpaceBetween
                                ) {
                                    Row(
                                        verticalAlignment = Alignment.CenterVertically,
                                        horizontalArrangement = Arrangement.spacedBy(8.dp)
                                    ) {
                                        Icon(icon, contentDescription = null, tint = if (isSelected) Gold else AppTheme.colors.textMuted, modifier = Modifier.size(16.dp))
                                        Column {
                                            Text(name, color = if (isSelected) Gold else AppTheme.colors.textPrimary, fontSize = 12.sp, fontWeight = FontWeight.Bold)
                                            Text(url, color = AppTheme.colors.textMuted, fontSize = 10.sp)
                                        }
                                    }
                                    if (isSelected) {
                                        Text("Aktiv", color = Gold, fontSize = 11.sp, fontWeight = FontWeight.Bold)
                                    }
                                }
                            }
                        }

                        // Custom URL Field
                        OutlinedTextField(
                            value = inputUrl,
                            onValueChange = { inputUrl = it },
                            label = { Text("Eigene URL") },
                            singleLine = true,
                            modifier = Modifier.fillMaxWidth(),
                            colors = OutlinedTextFieldDefaults.colors(
                                focusedBorderColor = Gold,
                                unfocusedBorderColor = AppTheme.colors.inputBorder,
                                focusedTextColor = AppTheme.colors.textPrimary,
                                unfocusedTextColor = AppTheme.colors.textPrimary
                            )
                        )

                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.End,
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            TextButton(onClick = { isEditingUrl = false }) {
                                Text("Schließen", color = AppTheme.colors.textMuted)
                            }
                            if (inputUrl != baseUrl) {
                                Spacer(modifier = Modifier.width(8.dp))
                                Button(
                                    onClick = {
                                        viewModel.updateBaseUrl(inputUrl)
                                        isEditingUrl = false
                                    },
                                    colors = ButtonDefaults.buttonColors(containerColor = Gold, contentColor = SpaceBlack)
                                ) {
                                    Text("Übernehmen")
                                }
                            }
                        }
                    }
                }
            }

            if (!isEditingUrl) {
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(6.dp),
                    modifier = Modifier
                        .clickable { isEditingUrl = true }
                        .padding(8.dp)
                ) {
                    Icon(
                        imageVector = Icons.Default.Settings,
                        contentDescription = null,
                        tint = AppTheme.colors.textMuted,
                        modifier = Modifier.size(13.dp)
                    )
                    Text(
                        text = "Server: $baseUrl",
                        fontSize = 11.sp,
                        color = AppTheme.colors.textMuted,
                        textAlign = TextAlign.Center
                    )
                }
            }

            Spacer(modifier = Modifier.height(32.dp))
        }
    }
}
