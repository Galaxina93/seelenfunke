package de.meinseelenfunke.app.ui.screens

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.OutlinedTextFieldDefaults
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.material3.TopAppBar
import androidx.compose.material3.TopAppBarDefaults
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.viewmodel.compose.viewModel
import de.meinseelenfunke.app.ui.theme.Cyan500
import de.meinseelenfunke.app.ui.theme.Emerald500
import de.meinseelenfunke.app.ui.theme.GlassWhite10
import de.meinseelenfunke.app.ui.theme.Indigo500
import de.meinseelenfunke.app.ui.theme.Rose500
import de.meinseelenfunke.app.ui.theme.Slate400
import de.meinseelenfunke.app.ui.theme.Slate50
import de.meinseelenfunke.app.ui.theme.Slate800
import de.meinseelenfunke.app.ui.theme.Slate900

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun LocalAiScreen(
    viewModel: LocalAiViewModel = viewModel()
) {
    val apiKey by viewModel.apiKey.collectAsState()
    val responseText by viewModel.responseText.collectAsState()
    val isLoading by viewModel.isLoading.collectAsState()
    val error by viewModel.error.collectAsState()

    var inputPrompt by remember { mutableStateOf("") }
    var inputKey by remember { mutableStateOf(apiKey) }
    var showSettings by remember { mutableStateOf(apiKey.isBlank()) }

    Scaffold(
        topBar = {
            TopAppBar(
                title = {
                    Column {
                        Text(
                            text = "Local Gemini AI",
                            fontSize = 18.sp,
                            fontWeight = FontWeight.Bold,
                            color = Emerald500
                        )
                        Text(
                            text = "On-Device GenAI Core",
                            fontSize = 12.sp,
                            color = Slate400
                        )
                    }
                },
                actions = {
                    Button(
                        onClick = { showSettings = !showSettings },
                        colors = ButtonDefaults.buttonColors(
                            containerColor = if (showSettings) Slate900 else Indigo500,
                            contentColor = Slate50
                        ),
                        shape = RoundedCornerShape(8.dp),
                        modifier = Modifier.padding(end = 8.dp)
                    ) {
                        Text(if (showSettings) "Schließen" else "API Key")
                    }
                },
                colors = TopAppBarDefaults.topAppBarColors(
                    containerColor = Slate800
                )
            )
        }
    ) { innerPadding ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(innerPadding)
                .background(
                    Brush.verticalGradient(
                        colors = listOf(Slate900, Color(0xFF020617))
                    )
                )
                .padding(16.dp)
                .verticalScroll(rememberScrollState())
        ) {
            // API Key Settings Box
            if (showSettings) {
                Card(
                    modifier = Modifier.fillMaxWidth(),
                    colors = CardDefaults.cardColors(containerColor = GlassWhite10),
                    shape = RoundedCornerShape(12.dp)
                ) {
                    Column(modifier = Modifier.padding(16.dp)) {
                        Text(
                            text = "Google AI Studio API Key",
                            fontSize = 14.sp,
                            fontWeight = FontWeight.Bold,
                            color = Slate50
                        )
                        Text(
                            text = "Erforderlich für die lokale Generierung via SDK.",
                            fontSize = 11.sp,
                            color = Slate400,
                            modifier = Modifier.padding(vertical = 4.dp)
                        )
                        OutlinedTextField(
                            value = inputKey,
                            onValueChange = { inputKey = it },
                            placeholder = { Text("AIzaSy...") },
                            modifier = Modifier.fillMaxWidth(),
                            colors = OutlinedTextFieldDefaults.colors(
                                focusedBorderColor = Indigo500,
                                unfocusedBorderColor = Color.Gray,
                                focusedTextColor = Slate50,
                                unfocusedTextColor = Slate50
                            )
                        )
                        Spacer(modifier = Modifier.height(8.dp))
                        Button(
                            onClick = {
                                viewModel.saveApiKey(inputKey)
                                showSettings = false
                            },
                            colors = ButtonDefaults.buttonColors(containerColor = Indigo500),
                            modifier = Modifier.align(Alignment.End)
                        ) {
                            Text("Speichern")
                        }
                    }
                }
                Spacer(modifier = Modifier.height(16.dp))
            }

            // Prompt Input Card
            Card(
                modifier = Modifier.fillMaxWidth(),
                colors = CardDefaults.cardColors(containerColor = GlassWhite10),
                shape = RoundedCornerShape(12.dp)
            ) {
                Column(modifier = Modifier.padding(16.dp)) {
                    Text(
                        text = "Lokaler Prompt",
                        fontSize = 15.sp,
                        fontWeight = FontWeight.Bold,
                        color = Slate50
                    )
                    Spacer(modifier = Modifier.height(8.dp))
                    OutlinedTextField(
                        value = inputPrompt,
                        onValueChange = { inputPrompt = it },
                        placeholder = { Text("Z.B.: Schreibe ein Gedicht über einen Roboter, der meditieren lernt...") },
                        modifier = Modifier.fillMaxWidth(),
                        minLines = 3,
                        maxLines = 6,
                        colors = OutlinedTextFieldDefaults.colors(
                            focusedBorderColor = Emerald500,
                            unfocusedBorderColor = Color.Gray,
                            focusedTextColor = Slate50,
                            unfocusedTextColor = Slate50,
                            focusedPlaceholderColor = Slate400,
                            unfocusedPlaceholderColor = Slate400
                        )
                    )
                    Spacer(modifier = Modifier.height(12.dp))
                    Button(
                        onClick = { viewModel.generateLocalResponse(inputPrompt) },
                        modifier = Modifier.fillMaxWidth(),
                        enabled = !isLoading,
                        colors = ButtonDefaults.buttonColors(
                            containerColor = Emerald500,
                            contentColor = Slate900
                        )
                    ) {
                        if (isLoading) {
                            CircularProgressIndicator(
                                color = Slate900,
                                modifier = Modifier.height(20.dp)
                            )
                        } else {
                            Text("Lokal Generieren", fontWeight = FontWeight.Bold)
                        }
                    }
                }
            }

            Spacer(modifier = Modifier.height(16.dp))

            // Output Card
            if (responseText.isNotBlank() || error != null) {
                Card(
                    modifier = Modifier.fillMaxWidth(),
                    colors = CardDefaults.cardColors(containerColor = Slate800),
                    shape = RoundedCornerShape(12.dp)
                ) {
                    Column(modifier = Modifier.padding(16.dp)) {
                        Text(
                            text = "Ausgabe",
                            fontSize = 14.sp,
                            fontWeight = FontWeight.Bold,
                            color = Slate400
                        )
                        Spacer(modifier = Modifier.height(8.dp))
                        if (error != null) {
                            Text(
                                text = error ?: "",
                                color = Rose500,
                                fontSize = 14.sp
                            )
                        } else {
                            Text(
                                text = responseText,
                                color = Slate50,
                                fontSize = 15.sp,
                                lineHeight = 22.sp
                            )
                        }
                    }
                }
            }
        }
    }
}
