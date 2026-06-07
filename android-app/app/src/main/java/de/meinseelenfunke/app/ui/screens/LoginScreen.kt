package de.meinseelenfunke.app.ui.screens

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.animation.AnimatedVisibility
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Visibility
import androidx.compose.material.icons.filled.VisibilityOff
import androidx.compose.material3.AlertDialog
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
import androidx.compose.ui.focus.onFocusChanged
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.layout.boundsInWindow
import androidx.compose.ui.layout.onGloballyPositioned
import androidx.compose.ui.platform.LocalAutofill
import androidx.compose.ui.platform.LocalAutofillTree
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.text.input.VisualTransformation
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.viewmodel.compose.viewModel
import de.meinseelenfunke.app.ui.theme.Gold
import de.meinseelenfunke.app.ui.theme.Copper
import de.meinseelenfunke.app.ui.theme.SpaceBlue
import de.meinseelenfunke.app.ui.theme.SpaceBlack
import de.meinseelenfunke.app.ui.theme.GlassWhite10
import de.meinseelenfunke.app.ui.theme.Rose500
import de.meinseelenfunke.app.ui.theme.Slate400
import de.meinseelenfunke.app.ui.theme.Slate50
import de.meinseelenfunke.app.ui.theme.Slate900
import androidx.compose.foundation.Image
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.layout.ContentScale

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

    // Pre-populate email if remember me is active
    LaunchedEffect(Unit) {
        if (viewModel.isRememberMeEnabled()) {
            email = viewModel.getSavedEmail()
        }
    }

    LaunchedEffect(loginState) {
        if (loginState is LoginState.Success) {
            onLoginSuccess()
        }
    }

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
            .background(
                Brush.verticalGradient(
                    colors = listOf(SpaceBlack, Slate900)
                )
            ),
        contentAlignment = Alignment.Center
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(24.dp),
            horizontalAlignment = Alignment.CenterHorizontally
        ) {
            // Cosmic Branding Header - Large Logo
            Image(
                painter = painterResource(id = de.meinseelenfunke.app.R.drawable.mein_seelenfunke_logo),
                contentDescription = "Seelenfunke Logo",
                modifier = Modifier.height(140.dp),
                contentScale = ContentScale.Fit
            )

            Spacer(modifier = Modifier.height(32.dp))

            // Glassmorphic Card Container
            Card(
                modifier = Modifier.fillMaxWidth(),
                colors = CardDefaults.cardColors(
                    containerColor = GlassWhite10
                ),
                shape = RoundedCornerShape(16.dp)
            ) {
                Column(
                    modifier = Modifier.padding(24.dp),
                    verticalArrangement = Arrangement.spacedBy(16.dp)
                ) {
                    Text(
                        text = "Anmelden",
                        fontSize = 20.sp,
                        fontWeight = FontWeight.Bold,
                        color = Slate50
                    )

                    // Email input with autofill trigger
                    OutlinedTextField(
                        value = email,
                        onValueChange = { email = it },
                        label = { Text("E-Mail-Adresse") },
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
                        colors = OutlinedTextFieldDefaults.colors(
                            focusedBorderColor = Gold,
                            unfocusedBorderColor = Color.Gray,
                            focusedLabelColor = Gold,
                            unfocusedLabelColor = Color.Gray,
                            focusedTextColor = Slate50,
                            unfocusedTextColor = Slate50
                        )
                    )

                    // Password input with visibility toggle & autofill
                    OutlinedTextField(
                        value = password,
                        onValueChange = { password = it },
                        label = { Text("Passwort") },
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
                                    tint = Slate400
                                )
                            }
                        },
                        colors = OutlinedTextFieldDefaults.colors(
                            focusedBorderColor = Gold,
                            unfocusedBorderColor = Color.Gray,
                            focusedLabelColor = Gold,
                            unfocusedLabelColor = Color.Gray,
                            focusedTextColor = Slate50,
                            unfocusedTextColor = Slate50
                        )
                    )

                    // Remember Me and Forgot Password Action Rows
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.SpaceBetween
                    ) {
                        Row(
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Checkbox(
                                checked = rememberMe,
                                onCheckedChange = { rememberMe = it },
                                colors = CheckboxDefaults.colors(
                                    checkedColor = Gold,
                                    uncheckedColor = Color.Gray,
                                    checkmarkColor = SpaceBlack
                                )
                            )
                            Text(
                                text = "Merken",
                                color = Slate50,
                                fontSize = 14.sp
                            )
                        }

                        TextButton(onClick = {
                            forgotPasswordEmail = email
                            showForgotPasswordDialog = true
                        }) {
                            Text("Passwort vergessen?", color = Gold, fontSize = 13.sp)
                        }
                    }

                    if (loginState is LoginState.Error) {
                        Text(
                            text = (loginState as LoginState.Error).message,
                            color = Rose500,
                            fontSize = 14.sp,
                            modifier = Modifier.fillMaxWidth(),
                            textAlign = TextAlign.Start
                        )
                    }

                    Button(
                        onClick = { viewModel.login(email, password, rememberMe) },
                        modifier = Modifier.fillMaxWidth(),
                        enabled = loginState !is LoginState.Loading,
                        shape = RoundedCornerShape(8.dp),
                        colors = ButtonDefaults.buttonColors(
                            containerColor = Gold,
                            contentColor = SpaceBlack
                        )
                    ) {
                        if (loginState is LoginState.Loading) {
                            CircularProgressIndicator(
                                color = SpaceBlack,
                                modifier = Modifier.size(20.dp)
                            )
                        } else {
                            Text("Einloggen", fontWeight = FontWeight.Bold)
                        }
                    }

                    AnimatedVisibility(visible = showForgotPasswordDialog) {
                        Column(
                            modifier = Modifier
                                .fillMaxWidth()
                                .padding(top = 8.dp)
                                .background(Color(0x0AFFFFFF), RoundedCornerShape(8.dp))
                                .padding(12.dp),
                            verticalArrangement = Arrangement.spacedBy(8.dp)
                        ) {
                            Text(
                                text = "Passwort zurücksetzen",
                                color = Slate50,
                                fontWeight = FontWeight.Bold,
                                fontSize = 14.sp
                            )
                            Text(
                                text = "Gib deine E-Mail-Adresse ein. Wir senden dir einen Link, mit dem du dein Passwort zurücksetzen kannst.",
                                color = Slate400,
                                fontSize = 12.sp
                            )
                            OutlinedTextField(
                                value = forgotPasswordEmail,
                                onValueChange = { forgotPasswordEmail = it },
                                label = { Text("E-Mail-Adresse") },
                                modifier = Modifier.fillMaxWidth(),
                                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Email),
                                colors = OutlinedTextFieldDefaults.colors(
                                    focusedBorderColor = Gold,
                                    unfocusedBorderColor = Color.Gray,
                                    focusedLabelColor = Gold,
                                    unfocusedTextColor = Slate50
                                )
                            )

                            // Display feedback state
                            passwordResetState?.onSuccess {
                                Text(
                                    text = "Zurücksetzung-Link wurde an deine Mail gesendet!",
                                    color = Gold,
                                    fontSize = 12.sp,
                                    modifier = Modifier.padding(top = 4.dp)
                                )
                            }?.onFailure { error ->
                                Text(
                                    text = error.localizedMessage ?: "Fehler beim Senden.",
                                    color = Rose500,
                                    fontSize = 12.sp,
                                    modifier = Modifier.padding(top = 4.dp)
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
                                    Text("Abbrechen", color = Slate400, fontSize = 12.sp)
                                }
                                Spacer(modifier = Modifier.width(8.dp))
                                Button(
                                    onClick = { viewModel.sendPasswordResetEmail(forgotPasswordEmail) },
                                    colors = ButtonDefaults.buttonColors(containerColor = Gold, contentColor = SpaceBlack),
                                    shape = RoundedCornerShape(4.dp)
                                ) {
                                    Text("Link anfordern", fontSize = 12.sp)
                                }
                            }
                        }
                    }
                }
            }

            Spacer(modifier = Modifier.height(24.dp))

            // Custom Base URL Setting
            if (isEditingUrl) {
                Column(
                    modifier = Modifier.fillMaxWidth(0.9f),
                    horizontalAlignment = Alignment.CenterHorizontally,
                    verticalArrangement = Arrangement.spacedBy(8.dp)
                ) {
                    Text(
                        text = "API-Server auswählen",
                        color = Slate50,
                        fontSize = 14.sp,
                        fontWeight = FontWeight.Bold
                    )
                    
                    val devUrl = de.meinseelenfunke.app.di.ServiceLocator.getDynamicDefaultBaseUrl()
                    val stageUrl = "https://stage.mein-seelenfunke.de/api/"
                    
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.spacedBy(8.dp)
                    ) {
                        // Local Server Button
                        val isLocal = baseUrl == devUrl
                        Button(
                            onClick = {
                                viewModel.updateBaseUrl(devUrl)
                                inputUrl = devUrl
                            },
                            modifier = Modifier.weight(1f),
                            shape = RoundedCornerShape(8.dp),
                            colors = ButtonDefaults.buttonColors(
                                containerColor = if (isLocal) Gold else GlassWhite10,
                                contentColor = if (isLocal) SpaceBlack else Slate50
                            )
                        ) {
                            Text("Lokal", fontSize = 12.sp, fontWeight = FontWeight.Bold)
                        }
                        
                        // Stage Server Button
                        val isStage = baseUrl == stageUrl
                        Button(
                            onClick = {
                                viewModel.updateBaseUrl(stageUrl)
                                inputUrl = stageUrl
                            },
                            modifier = Modifier.weight(1f),
                            shape = RoundedCornerShape(8.dp),
                            colors = ButtonDefaults.buttonColors(
                                containerColor = if (isStage) Gold else GlassWhite10,
                                contentColor = if (isStage) SpaceBlack else Slate50
                            )
                        ) {
                            Text("Stage", fontSize = 12.sp, fontWeight = FontWeight.Bold)
                        }
                    }
                    
                    Spacer(modifier = Modifier.height(4.dp))
                    
                    // Custom URL Field
                    OutlinedTextField(
                        value = inputUrl,
                        onValueChange = { inputUrl = it },
                        label = { Text("Benutzerdefinierte URL") },
                        modifier = Modifier.fillMaxWidth(),
                        colors = OutlinedTextFieldDefaults.colors(
                            focusedBorderColor = Gold,
                            unfocusedBorderColor = Color.Gray,
                            focusedLabelColor = Gold,
                            unfocusedTextColor = Slate50
                        )
                    )
                    
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.End
                    ) {
                        TextButton(onClick = { isEditingUrl = false }) {
                            Text("Fertig", color = Gold, fontWeight = FontWeight.Bold)
                        }
                        if (inputUrl != baseUrl) {
                            Spacer(modifier = Modifier.width(8.dp))
                            Button(
                                onClick = {
                                    viewModel.updateBaseUrl(inputUrl)
                                    isEditingUrl = false
                                },
                                colors = ButtonDefaults.buttonColors(containerColor = SpaceBlue)
                            ) {
                                Text("Speichern")
                            }
                        }
                    }
                }
            } else {
                Text(
                    text = "Server: $baseUrl",
                    fontSize = 11.sp,
                    color = Slate400,
                    textAlign = TextAlign.Center
                )
                TextButton(
                    onClick = { 
                        inputUrl = baseUrl
                        isEditingUrl = true 
                    },
                    modifier = Modifier.height(30.dp)
                ) {
                    Text("API Server ändern", fontSize = 11.sp, color = SpaceBlue)
                }
            }
        }
    }


}
