package de.meinseelenfunke.app.widget

import android.os.Bundle
import android.widget.Toast
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardActions
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.OutlinedTextFieldDefaults
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.focus.FocusRequester
import androidx.compose.ui.focus.focusRequester
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import de.meinseelenfunke.app.di.ServiceLocator
import de.meinseelenfunke.app.ui.theme.Gold
import de.meinseelenfunke.app.ui.theme.Rose500
import de.meinseelenfunke.app.ui.theme.Slate400
import de.meinseelenfunke.app.ui.theme.Slate50
import de.meinseelenfunke.app.ui.theme.Slate900
import de.meinseelenfunke.app.ui.theme.SpaceBlack
import kotlinx.coroutines.launch

class AddShoppingItemActivity : ComponentActivity() {

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)

        setContent {
            AddShoppingItemScreen(
                onDismiss = { finish() },
                onAdd = { itemName ->
                    // Done inside the screen coroutine scope to allow toast/loading
                }
            )
        }
    }

    @Composable
    fun AddShoppingItemScreen(onDismiss: () -> Unit, onAdd: (String) -> Unit) {
        var itemName by remember { mutableStateOf("") }
        var isLoading by remember { mutableStateOf(false) }
        var errorMessage by remember { mutableStateOf<String?>(null) }
        
        val coroutineScope = rememberCoroutineScope()
        val focusRequester = remember { FocusRequester() }

        // Request keyboard focus immediately on load
        LaunchedEffect(Unit) {
            focusRequester.requestFocus()
        }

        fun performAdd() {
            if (itemName.isBlank()) return
            isLoading = true
            errorMessage = null
            coroutineScope.launch {
                ServiceLocator.organizerRepository.addShoppingItem(itemName.trim())
                    .onSuccess {
                        isLoading = false
                        Toast.makeText(this@AddShoppingItemActivity, "Artikel hinzugefügt: ${it.name}", Toast.LENGTH_SHORT).show()
                        onDismiss()
                    }
                    .onFailure { error ->
                        isLoading = false
                        errorMessage = error.localizedMessage ?: "Verbindungsfehler"
                    }
            }
        }

        Box(
            modifier = Modifier
                .fillMaxSize()
                .background(Color(0x99030712)) // Dark translucent overlay
                .clickable { if (!isLoading) onDismiss() },
            contentAlignment = Alignment.Center
        ) {
            Card(
                modifier = Modifier
                    .width(320.dp)
                    .padding(16.dp)
                    .clickable {}, // Consume clicks on card
                shape = RoundedCornerShape(16.dp),
                border = BorderStroke(1.5.dp, Gold),
                colors = CardDefaults.cardColors(
                    containerColor = Slate900
                ),
                elevation = CardDefaults.cardElevation(defaultElevation = 8.dp)
            ) {
                Column(
                    modifier = Modifier.padding(20.dp),
                    horizontalAlignment = Alignment.CenterHorizontally
                ) {
                    Text(
                        text = "NEUER ARTIKEL",
                        color = Gold,
                        fontSize = 16.sp,
                        fontWeight = FontWeight.Bold,
                        letterSpacing = 0.1.sp
                    )

                    Spacer(modifier = Modifier.height(16.dp))

                    OutlinedTextField(
                        value = itemName,
                        onValueChange = { itemName = it },
                        placeholder = { Text("z.B. Milch, Äpfel...") },
                        singleLine = true,
                        enabled = !isLoading,
                        keyboardOptions = KeyboardOptions(
                            imeAction = ImeAction.Done
                        ),
                        keyboardActions = KeyboardActions(
                            onDone = { performAdd() }
                        ),
                        modifier = Modifier
                            .fillMaxWidth()
                            .focusRequester(focusRequester),
                        colors = OutlinedTextFieldDefaults.colors(
                            focusedBorderColor = Gold,
                            unfocusedBorderColor = Color(0x33FFFFFF),
                            focusedTextColor = Slate50,
                            unfocusedTextColor = Slate50,
                            focusedPlaceholderColor = Slate400,
                            unfocusedPlaceholderColor = Slate400
                        ),
                        shape = RoundedCornerShape(12.dp)
                    )

                    errorMessage?.let { msg ->
                        Spacer(modifier = Modifier.height(8.dp))
                        Text(
                            text = msg,
                            color = Rose500,
                            fontSize = 12.sp,
                            fontWeight = FontWeight.SemiBold
                        )
                    }

                    Spacer(modifier = Modifier.height(20.dp))

                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        OutlinedButton(
                            onClick = onDismiss,
                            enabled = !isLoading,
                            shape = RoundedCornerShape(20.dp),
                            border = BorderStroke(1.dp, Color(0x33FFFFFF)),
                            colors = ButtonDefaults.outlinedButtonColors(
                                contentColor = Slate400
                            ),
                            modifier = Modifier.weight(1f)
                        ) {
                            Text("Abbrechen", fontSize = 13.sp)
                        }

                        Spacer(modifier = Modifier.width(12.dp))

                        Button(
                            onClick = { performAdd() },
                            enabled = !isLoading && itemName.isNotBlank(),
                            shape = RoundedCornerShape(20.dp),
                            colors = ButtonDefaults.buttonColors(
                                containerColor = Gold,
                                contentColor = SpaceBlack,
                                disabledContainerColor = Color(0x33C5A059),
                                disabledContentColor = Slate400
                            ),
                            modifier = Modifier.weight(1f)
                        ) {
                            if (isLoading) {
                                CircularProgressIndicator(
                                    modifier = Modifier.height(18.dp).width(18.dp),
                                    color = SpaceBlack,
                                    strokeWidth = 2.dp
                                )
                            } else {
                                Text("Hinzufügen", fontSize = 13.sp, fontWeight = FontWeight.Bold)
                            }
                        }
                    }
                }
            }
        }
    }
}
