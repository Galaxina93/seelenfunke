package de.meinseelenfunke.app.ui.screens

import android.widget.Toast
import androidx.compose.animation.AnimatedVisibility
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.border
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
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Add
import androidx.compose.material.icons.filled.Bolt
import androidx.compose.material.icons.filled.CheckCircle
import androidx.compose.material.icons.filled.DarkMode
import androidx.compose.material.icons.filled.Delete
import androidx.compose.material.icons.filled.Description
import androidx.compose.material.icons.filled.ExitToApp
import androidx.compose.material.icons.filled.LightMode
import androidx.compose.material.icons.filled.PlayArrow
import androidx.compose.material.icons.filled.Refresh
import androidx.compose.material.icons.filled.Schedule
import androidx.compose.material.icons.filled.ShoppingBag
import androidx.compose.material.icons.filled.Stop
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.DropdownMenuItem
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.ExposedDropdownMenuBox
import androidx.compose.material3.ExposedDropdownMenuDefaults
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.OutlinedTextFieldDefaults
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import androidx.lifecycle.viewmodel.compose.viewModel
import de.meinseelenfunke.app.R
import de.meinseelenfunke.app.data.api.EmployeeClockNoteRequest
import de.meinseelenfunke.app.data.api.EmployeeCockpitData
import de.meinseelenfunke.app.data.api.EmployeeInventoryLossRequest
import de.meinseelenfunke.app.data.api.EmployeeLeaveRequest
import de.meinseelenfunke.app.data.api.EmployeeManualTimeRequest
import de.meinseelenfunke.app.data.api.EmployeePayslipDto
import de.meinseelenfunke.app.data.api.EmployeeTimeEntryDto
import de.meinseelenfunke.app.di.ServiceLocator
import de.meinseelenfunke.app.ui.theme.AppTheme
import de.meinseelenfunke.app.ui.theme.Emerald500
import de.meinseelenfunke.app.ui.theme.Gold
import de.meinseelenfunke.app.ui.theme.Rose500
import de.meinseelenfunke.app.ui.theme.SpaceBlack
import de.meinseelenfunke.app.util.SecureFileOpener
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import org.json.JSONObject
import retrofit2.HttpException
import java.time.LocalDate

sealed class EmployeeCockpitUiState {
    object Loading : EmployeeCockpitUiState()
    data class Success(val data: EmployeeCockpitData) : EmployeeCockpitUiState()
    data class Error(val message: String) : EmployeeCockpitUiState()
}

class EmployeeCockpitViewModel : ViewModel() {

    private val _uiState = MutableStateFlow<EmployeeCockpitUiState>(EmployeeCockpitUiState.Loading)
    val uiState: StateFlow<EmployeeCockpitUiState> = _uiState.asStateFlow()

    private val _isBusy = MutableStateFlow(false)
    val isBusy: StateFlow<Boolean> = _isBusy.asStateFlow()

    private val _toastMessage = MutableStateFlow<String?>(null)
    val toastMessage: StateFlow<String?> = _toastMessage.asStateFlow()

    init {
        loadCockpit(showLoading = true)
    }

    fun consumeToast() {
        _toastMessage.value = null
    }

    private fun extractErrorMessage(e: Throwable, fallback: String): String {
        if (e is HttpException) {
            val raw = try { e.response()?.errorBody()?.string() } catch (_: Exception) { null }
            if (!raw.isNullOrBlank()) {
                try {
                    val json = JSONObject(raw)
                    val msg = json.optString("message")
                    if (msg.isNotBlank()) return msg
                } catch (_: Exception) {}
            }
        }
        return e.localizedMessage ?: fallback
    }

    fun loadCockpit(showLoading: Boolean = false) {
        viewModelScope.launch {
            if (showLoading) {
                _uiState.value = EmployeeCockpitUiState.Loading
            }
            try {
                val res = ServiceLocator.getEmployeeApi().getCockpit()
                val payload = res.data
                if (res.success && payload != null) {
                    payload.permissions?.let { p ->
                        ServiceLocator.saveEmployeePermissions(p.can_access_tasks, p.can_access_production)
                    }
                    _uiState.value = EmployeeCockpitUiState.Success(payload)
                } else {
                    _uiState.value = EmployeeCockpitUiState.Error(res.message ?: "Mitarbeiter-Cockpit konnte nicht geladen werden.")
                }
            } catch (e: Exception) {
                _uiState.value = EmployeeCockpitUiState.Error(extractErrorMessage(e, "Verbindungsfehler."))
            }
        }
    }

    fun clockIn() {
        viewModelScope.launch {
            _isBusy.value = true
            try {
                val res = ServiceLocator.getEmployeeApi().clockIn(EmployeeClockNoteRequest())
                res.data?.let { _uiState.value = EmployeeCockpitUiState.Success(it) }
                _toastMessage.value = res.message ?: "Eingestempelt."
            } catch (e: Exception) {
                _toastMessage.value = extractErrorMessage(e, "Einstempeln fehlgeschlagen.")
            } finally {
                _isBusy.value = false
            }
        }
    }

    fun clockOut() {
        viewModelScope.launch {
            _isBusy.value = true
            try {
                val res = ServiceLocator.getEmployeeApi().clockOut(EmployeeClockNoteRequest())
                res.data?.let { _uiState.value = EmployeeCockpitUiState.Success(it) }
                _toastMessage.value = res.message ?: "Ausgestempelt."
            } catch (e: Exception) {
                _toastMessage.value = extractErrorMessage(e, "Ausstempeln fehlgeschlagen.")
            } finally {
                _isBusy.value = false
            }
        }
    }

    fun saveManualTime(workDate: String, startTime: String, endTime: String, note: String, onDone: () -> Unit) {
        viewModelScope.launch {
            _isBusy.value = true
            try {
                val res = ServiceLocator.getEmployeeApi().saveTimeEntry(
                    EmployeeManualTimeRequest(
                        work_date = workDate.trim(),
                        start_time = startTime.trim(),
                        end_time = endTime.trim(),
                        activity_note = note.trim().ifBlank { "Manufaktur & Versand" }
                    )
                )
                res.data?.let { _uiState.value = EmployeeCockpitUiState.Success(it) }
                _toastMessage.value = res.message ?: "Arbeitszeit gespeichert."
                onDone()
            } catch (e: Exception) {
                _toastMessage.value = extractErrorMessage(e, "Arbeitszeit konnte nicht gespeichert werden.")
            } finally {
                _isBusy.value = false
            }
        }
    }

    fun saveLeave(entryType: String, startDate: String, endDate: String, note: String, onDone: () -> Unit) {
        viewModelScope.launch {
            _isBusy.value = true
            try {
                val res = ServiceLocator.getEmployeeApi().saveLeaveEntry(
                    EmployeeLeaveRequest(
                        entry_type = entryType,
                        start_date = startDate.trim(),
                        end_date = endDate.trim().ifBlank { startDate.trim() },
                        activity_note = note.trim()
                    )
                )
                res.data?.let { _uiState.value = EmployeeCockpitUiState.Success(it) }
                _toastMessage.value = res.message ?: "Eintrag gespeichert."
                onDone()
            } catch (e: Exception) {
                _toastMessage.value = extractErrorMessage(e, "Eintrag konnte nicht gespeichert werden.")
            } finally {
                _isBusy.value = false
            }
        }
    }

    fun deleteTimeEntry(entryId: String) {
        viewModelScope.launch {
            _isBusy.value = true
            try {
                val res = ServiceLocator.getEmployeeApi().deleteTimeEntry(entryId)
                res.data?.let { _uiState.value = EmployeeCockpitUiState.Success(it) }
                _toastMessage.value = res.message ?: "Eintrag gelöscht."
            } catch (e: Exception) {
                _toastMessage.value = extractErrorMessage(e, "Löschen fehlgeschlagen.")
            } finally {
                _isBusy.value = false
            }
        }
    }

    fun reportInventoryLoss(productId: String, qty: Int, reason: String, onDone: () -> Unit) {
        viewModelScope.launch {
            _isBusy.value = true
            try {
                val res = ServiceLocator.getEmployeeApi().reportInventoryLoss(
                    EmployeeInventoryLossRequest(product_id = productId, quantity = qty, reason = reason.trim())
                )
                res.data?.let { _uiState.value = EmployeeCockpitUiState.Success(it) }
                _toastMessage.value = res.message ?: "Bruch gespeichert."
                onDone()
            } catch (e: Exception) {
                _toastMessage.value = extractErrorMessage(e, "Bruchmeldung fehlgeschlagen.")
            } finally {
                _isBusy.value = false
            }
        }
    }
}

@Composable
fun EmployeeOverviewScreen(
    isPageVisible: Boolean,
    onNavigateToTasks: () -> Unit,
    onNavigateToProduction: () -> Unit,
    onNavigateToTimeAndPay: () -> Unit,
    onLogout: () -> Unit,
    viewModel: EmployeeCockpitViewModel = viewModel()
) {
    val context = LocalContext.current
    val uiState by viewModel.uiState.collectAsState()
    val isBusy by viewModel.isBusy.collectAsState()
    val toastMsg by viewModel.toastMessage.collectAsState()

    LaunchedEffect(isPageVisible) {
        if (isPageVisible) {
            viewModel.loadCockpit(showLoading = false)
        }
    }

    LaunchedEffect(toastMsg) {
        toastMsg?.let { msg ->
            Toast.makeText(context, msg, Toast.LENGTH_SHORT).show()
            viewModel.consumeToast()
        }
    }

    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(Brush.verticalGradient(AppTheme.colors.bgGradient))
    ) {
        Column(
            modifier = Modifier
                .fillMaxSize()
                .verticalScroll(rememberScrollState())
                .padding(horizontal = 16.dp, vertical = 12.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp)
        ) {
            // Clean Top Bar (Logo + Theme + Refresh + Logout)
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Image(
                    painter = painterResource(id = R.drawable.mein_seelenfunke_logo),
                    contentDescription = "Mein-Seelenfunke",
                    modifier = Modifier.height(40.dp),
                    contentScale = ContentScale.Fit
                )
                Row(
                    horizontalArrangement = Arrangement.spacedBy(8.dp),
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Box(
                        modifier = Modifier
                            .size(34.dp)
                            .clip(CircleShape)
                            .background(AppTheme.colors.glassSurface)
                            .border(1.dp, AppTheme.colors.glassBorder, CircleShape)
                            .clickable { ServiceLocator.toggleTheme() },
                        contentAlignment = Alignment.Center
                    ) {
                        Icon(
                            imageVector = if (AppTheme.colors.isDark) Icons.Default.LightMode else Icons.Default.DarkMode,
                            contentDescription = "Design wechseln",
                            tint = Gold,
                            modifier = Modifier.size(17.dp)
                        )
                    }
                    Box(
                        modifier = Modifier
                            .size(34.dp)
                            .clip(CircleShape)
                            .background(AppTheme.colors.glassSurface)
                            .border(1.dp, AppTheme.colors.glassBorder, CircleShape)
                            .clickable { viewModel.loadCockpit(showLoading = true) },
                        contentAlignment = Alignment.Center
                    ) {
                        Icon(
                            imageVector = Icons.Default.Refresh,
                            contentDescription = "Aktualisieren",
                            tint = Gold,
                            modifier = Modifier.size(17.dp)
                        )
                    }
                    Box(
                        modifier = Modifier
                            .size(34.dp)
                            .clip(CircleShape)
                            .background(AppTheme.colors.glassSurface)
                            .border(1.dp, AppTheme.colors.glassBorder, CircleShape)
                            .clickable { onLogout() },
                        contentAlignment = Alignment.Center
                    ) {
                        Icon(
                            imageVector = Icons.Default.ExitToApp,
                            contentDescription = "Abmelden",
                            tint = Rose500,
                            modifier = Modifier.size(17.dp)
                        )
                    }
                }
            }

            when (val st = uiState) {
                is EmployeeCockpitUiState.Loading -> {
                    Box(
                        modifier = Modifier
                            .fillMaxWidth()
                            .height(220.dp),
                        contentAlignment = Alignment.Center
                    ) {
                        CircularProgressIndicator(color = Gold)
                    }
                }

                is EmployeeCockpitUiState.Error -> {
                    Card(
                        modifier = Modifier.fillMaxWidth(),
                        colors = CardDefaults.cardColors(containerColor = AppTheme.colors.cardBackground),
                        border = BorderStroke(1.dp, Rose500.copy(alpha = 0.5f)),
                        shape = RoundedCornerShape(16.dp)
                    ) {
                        Column(
                            modifier = Modifier.padding(16.dp),
                            horizontalAlignment = Alignment.CenterHorizontally,
                            verticalArrangement = Arrangement.spacedBy(8.dp)
                        ) {
                            Text(text = st.message, fontSize = 13.sp, color = AppTheme.colors.textPrimary)
                            Button(
                                onClick = { viewModel.loadCockpit(showLoading = true) },
                                colors = ButtonDefaults.buttonColors(containerColor = Gold, contentColor = SpaceBlack)
                            ) {
                                Text("Erneut laden", fontWeight = FontWeight.Bold)
                            }
                        }
                    }
                }

                is EmployeeCockpitUiState.Success -> {
                    val data = st.data
                    if (!data.has_contract || data.contract == null) {
                        Card(
                            modifier = Modifier.fillMaxWidth(),
                            colors = CardDefaults.cardColors(containerColor = AppTheme.colors.cardBackground),
                            border = BorderStroke(1.dp, AppTheme.colors.cardBorder),
                            shape = RoundedCornerShape(16.dp)
                        ) {
                            Box(modifier = Modifier.padding(24.dp), contentAlignment = Alignment.Center) {
                                Text(
                                    text = "Noch kein aktiver Arbeitsvertrag hinterlegt.",
                                    fontSize = 13.sp,
                                    fontWeight = FontWeight.SemiBold,
                                    color = AppTheme.colors.textSecondary
                                )
                            }
                        }
                    } else {
                        EmployeeMainProfileAndClockCard(
                            data = data,
                            isBusy = isBusy,
                            onClockIn = { viewModel.clockIn() },
                            onClockOut = { viewModel.clockOut() },
                            onOpenContractPdf = {
                                SecureFileOpener.openAuthenticatedApiFile(
                                    context = context,
                                    relativeApiEndpoint = "employee/documents/contract",
                                    fallbackFileName = "Arbeitsvertrag.pdf"
                                )
                            },
                            onOpenTimesheetPdf = {
                                SecureFileOpener.openAuthenticatedApiFile(
                                    context = context,
                                    relativeApiEndpoint = "employee/documents/timesheet/${data.selected_year}/${data.selected_month}",
                                    fallbackFileName = "Stundenzettel_${data.selected_year}_${data.selected_month}.pdf"
                                )
                            }
                        )

                        // Quick Action Tiles matching exact Web EmployeeDashboard tabs
                        val kpis = data.kpis
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.spacedBy(10.dp)
                        ) {
                            if (data.contract.can_access_tasks) {
                                CompactDashboardTile(
                                    modifier = Modifier.weight(1f),
                                    icon = Icons.Default.CheckCircle,
                                    title = "Meine Aufgaben",
                                    value = "${kpis?.assigned_open_tasks_count ?: 0} offen",
                                    subtitle = "Aufgaben öffnen",
                                    isAlert = false,
                                    onClick = onNavigateToTasks
                                )
                            }
                            CompactDashboardTile(
                                modifier = Modifier.weight(1f),
                                icon = Icons.Default.Schedule,
                                title = "Zeiten & Lohn",
                                value = kpis?.worked_hours_formatted ?: "0:00 Std.",
                                subtitle = "${data.payslips.size} Lohnzettel",
                                isAlert = false,
                                onClick = onNavigateToTimeAndPay
                            )
                        }

                        if (data.contract.can_access_production) {
                            val openOrders = kpis?.open_orders_count ?: 0
                            val expressOrders = kpis?.express_orders_count ?: 0
                            CompactDashboardTile(
                                modifier = Modifier.fillMaxWidth(),
                                icon = if (expressOrders > 0) Icons.Default.Bolt else Icons.Default.ShoppingBag,
                                title = "Packtisch & Laser",
                                value = "$openOrders offen",
                                subtitle = if (expressOrders > 0) "$expressOrders Express-Aufträge" else "Fertigung öffnen",
                                isAlert = expressOrders > 0,
                                onClick = onNavigateToProduction
                            )
                        }
                    }
                }
            }
        }
    }
}

@Composable
private fun EmployeeMainProfileAndClockCard(
    data: EmployeeCockpitData,
    isBusy: Boolean,
    onClockIn: () -> Unit,
    onClockOut: () -> Unit,
    onOpenContractPdf: () -> Unit,
    onOpenTimesheetPdf: () -> Unit
) {
    val emp = data.employee
    val contract = data.contract
    val op = data.operator_profile
    val running = data.running_entry
    val kpis = data.kpis

    Card(
        modifier = Modifier.fillMaxWidth(),
        colors = CardDefaults.cardColors(containerColor = AppTheme.colors.cardBackground),
        border = BorderStroke(1.dp, AppTheme.colors.cardBorder),
        shape = RoundedCornerShape(16.dp)
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp)
        ) {
            // Identity + Rank Badge (1:1 Web EmployeeDashboard)
            Row(
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(12.dp)
            ) {
                Box(
                    modifier = Modifier
                        .size(46.dp)
                        .clip(RoundedCornerShape(14.dp))
                        .background(Gold.copy(alpha = 0.15f))
                        .border(1.5.dp, Gold, RoundedCornerShape(14.dp)),
                    contentAlignment = Alignment.Center
                ) {
                    Text(
                        text = op?.initials ?: "SF",
                        fontSize = 14.sp,
                        fontWeight = FontWeight.Black,
                        color = Gold
                    )
                }

                Column(modifier = Modifier.weight(1f)) {
                    Text(
                        text = emp?.full_name?.ifBlank { emp.email } ?: "Mitarbeiter",
                        fontSize = 16.sp,
                        fontWeight = FontWeight.Bold,
                        color = AppTheme.colors.textPrimary,
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis
                    )
                    if (op != null) {
                        Box(
                            modifier = Modifier
                                .padding(top = 2.dp)
                                .clip(RoundedCornerShape(6.dp))
                                .background(Gold.copy(alpha = 0.14f))
                                .border(1.dp, Gold.copy(alpha = 0.4f), RoundedCornerShape(6.dp))
                                .padding(horizontal = 7.dp, vertical = 2.dp)
                        ) {
                            Text(
                                text = "${op.rank_class} · ${op.title_label} (${op.ep_total} EP)",
                                fontSize = 10.sp,
                                fontWeight = FontWeight.Bold,
                                color = Gold
                            )
                        }
                    }
                    if (!contract?.job_title.isNullOrBlank()) {
                        Text(
                            text = "${contract?.job_title} · ${contract?.department}",
                            fontSize = 11.5.sp,
                            color = AppTheme.colors.textMuted,
                            maxLines = 1,
                            overflow = TextOverflow.Ellipsis
                        )
                    }
                }
            }

            // Live Stempeluhr (Clock-In / Clock-Out)
            if (running != null) {
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Box(
                        modifier = Modifier
                            .clip(RoundedCornerShape(10.dp))
                            .background(Emerald500.copy(alpha = 0.14f))
                            .border(1.dp, Emerald500.copy(alpha = 0.4f), RoundedCornerShape(10.dp))
                            .padding(horizontal = 10.dp, vertical = 7.dp)
                    ) {
                        Text(
                            text = "● Aktiv seit ${running.started_at_time} Uhr",
                            fontSize = 12.sp,
                            fontWeight = FontWeight.Bold,
                            color = Emerald500
                        )
                    }
                    Button(
                        onClick = onClockOut,
                        enabled = !isBusy,
                        shape = RoundedCornerShape(10.dp),
                        colors = ButtonDefaults.buttonColors(containerColor = Rose500, contentColor = Color.White)
                    ) {
                        Icon(Icons.Default.Stop, contentDescription = null, modifier = Modifier.size(16.dp))
                        Spacer(modifier = Modifier.width(6.dp))
                        Text("Ausstempeln", fontWeight = FontWeight.Bold, fontSize = 12.5.sp)
                    }
                }
            } else {
                Button(
                    onClick = onClockIn,
                    enabled = !isBusy,
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(11.dp),
                    colors = ButtonDefaults.buttonColors(containerColor = Emerald500, contentColor = Color.White)
                ) {
                    Icon(Icons.Default.PlayArrow, contentDescription = null, modifier = Modifier.size(18.dp))
                    Spacer(modifier = Modifier.width(6.dp))
                    Text("Einstempeln", fontWeight = FontWeight.Bold, fontSize = 13.5.sp)
                }
            }

            // Document Quick Buttons (Vertrag PDF + Stundenzettel PDF)
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                OutlinedButton(
                    onClick = onOpenContractPdf,
                    modifier = Modifier.weight(1f),
                    shape = RoundedCornerShape(10.dp),
                    border = BorderStroke(1.dp, AppTheme.colors.cardBorder)
                ) {
                    Icon(Icons.Default.Description, contentDescription = null, tint = Gold, modifier = Modifier.size(15.dp))
                    Spacer(modifier = Modifier.width(6.dp))
                    Text("Vertrag PDF", fontSize = 11.5.sp, fontWeight = FontWeight.Bold, color = AppTheme.colors.textPrimary)
                }
                OutlinedButton(
                    onClick = onOpenTimesheetPdf,
                    modifier = Modifier.weight(1f),
                    shape = RoundedCornerShape(10.dp),
                    border = BorderStroke(1.dp, AppTheme.colors.cardBorder)
                ) {
                    Icon(Icons.Default.Description, contentDescription = null, tint = Gold, modifier = Modifier.size(15.dp))
                    Spacer(modifier = Modifier.width(6.dp))
                    Text("Stundenzettel PDF", fontSize = 11.5.sp, fontWeight = FontWeight.Bold, color = AppTheme.colors.textPrimary)
                }
            }

            HorizontalDivider(color = AppTheme.colors.cardBorder)

            // 4 KPI Mini Tiles (Monatsstunden, Resturlaub, Meine Aufgaben, Score)
            Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                EmployeeKpiPill(
                    modifier = Modifier.weight(1f),
                    label = "Monatsstunden",
                    value = kpis?.worked_hours_formatted ?: "0:00 Std.",
                    valueColor = AppTheme.colors.textPrimary
                )
                EmployeeKpiPill(
                    modifier = Modifier.weight(1f),
                    label = "Resturlaub",
                    value = "${kpis?.remaining_vacation_days ?: 0} / ${kpis?.vacation_days_per_year ?: 0} Tg.",
                    valueColor = AppTheme.colors.textPrimary
                )
            }
            Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                EmployeeKpiPill(
                    modifier = Modifier.weight(1f),
                    label = "Meine Aufgaben",
                    value = "${kpis?.assigned_open_tasks_count ?: 0} offen",
                    valueColor = Gold
                )
                EmployeeKpiPill(
                    modifier = Modifier.weight(1f),
                    label = "Score",
                    value = "${kpis?.efficiency_score ?: 100} %",
                    valueColor = Emerald500
                )
            }
        }
    }
}

@Composable
private fun EmployeeKpiPill(
    modifier: Modifier = Modifier,
    label: String,
    value: String,
    valueColor: Color
) {
    Row(
        modifier = modifier
            .clip(RoundedCornerShape(10.dp))
            .background(AppTheme.colors.glassSurface)
            .border(1.dp, AppTheme.colors.cardBorder, RoundedCornerShape(10.dp))
            .padding(horizontal = 10.dp, vertical = 9.dp),
        horizontalArrangement = Arrangement.SpaceBetween,
        verticalAlignment = Alignment.CenterVertically
    ) {
        Text(text = label, fontSize = 11.sp, fontWeight = FontWeight.SemiBold, color = AppTheme.colors.textMuted)
        Text(text = value, fontSize = 12.5.sp, fontWeight = FontWeight.Black, color = valueColor)
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun EmployeeTimeAndPayslipsScreen(
    isPageVisible: Boolean,
    viewModel: EmployeeCockpitViewModel = viewModel()
) {
    val context = LocalContext.current
    val uiState by viewModel.uiState.collectAsState()
    val isBusy by viewModel.isBusy.collectAsState()
    val toastMsg by viewModel.toastMessage.collectAsState()

    var activeSection by remember { mutableStateOf("time") } // "time", "payslips", "inventory"
    var showManualTimeForm by remember { mutableStateOf(false) }
    var showLeaveForm by remember { mutableStateOf(false) }

    val todayIso = remember {
        try { LocalDate.now().toString() } catch (_: Exception) { "2026-11-25" }
    }
    var manualDate by remember { mutableStateOf(todayIso) }
    var manualStart by remember { mutableStateOf("09:00") }
    var manualEnd by remember { mutableStateOf("13:00") }
    var manualNote by remember { mutableStateOf("Manufaktur & Versand") }

    var leaveType by remember { mutableStateOf("vacation") }
    var leaveStart by remember { mutableStateOf(todayIso) }
    var leaveEnd by remember { mutableStateOf(todayIso) }
    var leaveNote by remember { mutableStateOf("") }

    var selectedProductId by remember { mutableStateOf("") }
    var productMenuExpanded by remember { mutableStateOf(false) }
    var lossQtyText by remember { mutableStateOf("1") }
    var lossReason by remember { mutableStateOf("") }

    LaunchedEffect(isPageVisible) {
        if (isPageVisible) {
            viewModel.loadCockpit(showLoading = false)
        }
    }

    LaunchedEffect(toastMsg) {
        toastMsg?.let { msg ->
            Toast.makeText(context, msg, Toast.LENGTH_SHORT).show()
            viewModel.consumeToast()
        }
    }

    Column(
        modifier = Modifier
            .fillMaxSize()
            .background(Brush.verticalGradient(AppTheme.colors.bgGradient))
            .verticalScroll(rememberScrollState())
            .padding(16.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp)
    ) {
        Text(
            text = "ZEITEN & LOHN",
            fontSize = 20.sp,
            fontWeight = FontWeight.Bold,
            color = Gold,
            letterSpacing = 1.5.sp
        )

        val data = (uiState as? EmployeeCockpitUiState.Success)?.data
        val canInventory = data?.contract?.can_access_inventory == true

        // Clean Segment Switcher: Zeiten & Urlaub | Lohnzettel | optional Bruch melden
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.spacedBy(8.dp)
        ) {
            EmployeeSegmentChip(
                modifier = Modifier.weight(1f),
                title = "Zeiten & Urlaub",
                selected = activeSection == "time",
                onClick = { activeSection = "time" }
            )
            EmployeeSegmentChip(
                modifier = Modifier.weight(1f),
                title = "Lohnzettel (${data?.payslips?.size ?: 0})",
                selected = activeSection == "payslips",
                onClick = { activeSection = "payslips" }
            )
            if (canInventory) {
                EmployeeSegmentChip(
                    modifier = Modifier.weight(1f),
                    title = "Bruch",
                    selected = activeSection == "inventory",
                    onClick = { activeSection = "inventory" }
                )
            }
        }

        if (data == null) {
            Box(modifier = Modifier.fillMaxWidth().height(180.dp), contentAlignment = Alignment.Center) {
                CircularProgressIndicator(color = Gold)
            }
            return
        }

        when (activeSection) {
            "time" -> {
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.spacedBy(8.dp)
                ) {
                    Button(
                        onClick = {
                            showManualTimeForm = !showManualTimeForm
                            if (showManualTimeForm) showLeaveForm = false
                        },
                        modifier = Modifier.weight(1f),
                        shape = RoundedCornerShape(10.dp),
                        colors = ButtonDefaults.buttonColors(containerColor = Gold, contentColor = SpaceBlack)
                    ) {
                        Icon(Icons.Default.Add, contentDescription = null, modifier = Modifier.size(16.dp))
                        Spacer(modifier = Modifier.width(4.dp))
                        Text("Arbeitszeit", fontWeight = FontWeight.Bold, fontSize = 12.sp)
                    }
                    OutlinedButton(
                        onClick = {
                            showLeaveForm = !showLeaveForm
                            if (showLeaveForm) showManualTimeForm = false
                        },
                        modifier = Modifier.weight(1f),
                        shape = RoundedCornerShape(10.dp),
                        border = BorderStroke(1.dp, Gold.copy(alpha = 0.6f))
                    ) {
                        Icon(Icons.Default.Add, contentDescription = null, tint = Gold, modifier = Modifier.size(16.dp))
                        Spacer(modifier = Modifier.width(4.dp))
                        Text("Urlaub / Krank", fontWeight = FontWeight.Bold, fontSize = 12.sp, color = Gold)
                    }
                }

                AnimatedVisibility(visible = showManualTimeForm) {
                    Card(
                        modifier = Modifier.fillMaxWidth(),
                        colors = CardDefaults.cardColors(containerColor = AppTheme.colors.cardBackground),
                        border = BorderStroke(1.dp, Gold.copy(alpha = 0.5f)),
                        shape = RoundedCornerShape(14.dp)
                    ) {
                        Column(modifier = Modifier.padding(14.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                            OutlinedTextField(
                                value = manualDate,
                                onValueChange = { manualDate = it },
                                label = { Text("Datum (JJJJ-MM-TT)") },
                                singleLine = true,
                                modifier = Modifier.fillMaxWidth()
                            )
                            Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                                OutlinedTextField(
                                    value = manualStart,
                                    onValueChange = { manualStart = it },
                                    label = { Text("Von (HH:MM)") },
                                    singleLine = true,
                                    modifier = Modifier.weight(1f)
                                )
                                OutlinedTextField(
                                    value = manualEnd,
                                    onValueChange = { manualEnd = it },
                                    label = { Text("Bis (HH:MM)") },
                                    singleLine = true,
                                    modifier = Modifier.weight(1f)
                                )
                            }
                            OutlinedTextField(
                                value = manualNote,
                                onValueChange = { manualNote = it },
                                label = { Text("Tätigkeit") },
                                singleLine = true,
                                modifier = Modifier.fillMaxWidth()
                            )
                            Button(
                                onClick = {
                                    viewModel.saveManualTime(manualDate, manualStart, manualEnd, manualNote) {
                                        showManualTimeForm = false
                                    }
                                },
                                enabled = !isBusy,
                                modifier = Modifier.fillMaxWidth(),
                                colors = ButtonDefaults.buttonColors(containerColor = Gold, contentColor = SpaceBlack)
                            ) {
                                Text("Speichern", fontWeight = FontWeight.Bold)
                            }
                        }
                    }
                }

                AnimatedVisibility(visible = showLeaveForm) {
                    Card(
                        modifier = Modifier.fillMaxWidth(),
                        colors = CardDefaults.cardColors(containerColor = AppTheme.colors.cardBackground),
                        border = BorderStroke(1.dp, Gold.copy(alpha = 0.5f)),
                        shape = RoundedCornerShape(14.dp)
                    ) {
                        Column(modifier = Modifier.padding(14.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                            Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                                EmployeeSegmentChip(
                                    modifier = Modifier.weight(1f),
                                    title = "Urlaub",
                                    selected = leaveType == "vacation",
                                    onClick = { leaveType = "vacation" }
                                )
                                EmployeeSegmentChip(
                                    modifier = Modifier.weight(1f),
                                    title = "Krank",
                                    selected = leaveType == "sick",
                                    onClick = { leaveType = "sick" }
                                )
                            }
                            Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                                OutlinedTextField(
                                    value = leaveStart,
                                    onValueChange = { leaveStart = it },
                                    label = { Text("Von (JJJJ-MM-TT)") },
                                    singleLine = true,
                                    modifier = Modifier.weight(1f)
                                )
                                OutlinedTextField(
                                    value = leaveEnd,
                                    onValueChange = { leaveEnd = it },
                                    label = { Text("Bis (JJJJ-MM-TT)") },
                                    singleLine = true,
                                    modifier = Modifier.weight(1f)
                                )
                            }
                            OutlinedTextField(
                                value = leaveNote,
                                onValueChange = { leaveNote = it },
                                label = { Text("Notiz (optional)") },
                                singleLine = true,
                                modifier = Modifier.fillMaxWidth()
                            )
                            Button(
                                onClick = {
                                    viewModel.saveLeave(leaveType, leaveStart, leaveEnd, leaveNote) {
                                        showLeaveForm = false
                                        leaveNote = ""
                                    }
                                },
                                enabled = !isBusy,
                                modifier = Modifier.fillMaxWidth(),
                                colors = ButtonDefaults.buttonColors(containerColor = Gold, contentColor = SpaceBlack)
                            ) {
                                Text("Eintragen", fontWeight = FontWeight.Bold)
                            }
                        }
                    }
                }

                Card(
                    modifier = Modifier.fillMaxWidth(),
                    colors = CardDefaults.cardColors(containerColor = AppTheme.colors.cardBackground),
                    border = BorderStroke(1.dp, AppTheme.colors.cardBorder),
                    shape = RoundedCornerShape(16.dp)
                ) {
                    Column(modifier = Modifier.padding(14.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                        if (data.time_entries.isEmpty()) {
                            Text(
                                text = "Noch keine Einträge in diesem Monat.",
                                fontSize = 12.5.sp,
                                color = AppTheme.colors.textMuted
                            )
                        } else {
                            data.time_entries.forEach { entry ->
                                EmployeeTimeEntryRow(
                                    entry = entry,
                                    onDelete = { viewModel.deleteTimeEntry(entry.id) }
                                )
                            }
                        }
                    }
                }
            }

            "payslips" -> {
                Card(
                    modifier = Modifier.fillMaxWidth(),
                    colors = CardDefaults.cardColors(containerColor = AppTheme.colors.cardBackground),
                    border = BorderStroke(1.dp, AppTheme.colors.cardBorder),
                    shape = RoundedCornerShape(16.dp)
                ) {
                    Column(modifier = Modifier.padding(14.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                        if (data.payslips.isEmpty()) {
                            Text(
                                text = "Noch keine Lohnabrechnungen vorhanden.",
                                fontSize = 12.5.sp,
                                color = AppTheme.colors.textMuted
                            )
                        } else {
                            data.payslips.forEach { slip ->
                                EmployeePayslipRow(
                                    slip = slip,
                                    onDownload = {
                                        SecureFileOpener.openAuthenticatedApiFile(
                                            context = context,
                                            relativeApiEndpoint = "employee/documents/payslip/${slip.id}",
                                            fallbackFileName = "Lohnabrechnung_${slip.period_year}_${slip.period_month}.pdf"
                                        )
                                    }
                                )
                            }
                        }
                    }
                }
            }

            "inventory" -> {
                Card(
                    modifier = Modifier.fillMaxWidth(),
                    colors = CardDefaults.cardColors(containerColor = AppTheme.colors.cardBackground),
                    border = BorderStroke(1.dp, AppTheme.colors.cardBorder),
                    shape = RoundedCornerShape(16.dp)
                ) {
                    Column(modifier = Modifier.padding(14.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                        val selectedProduct = data.physical_products.firstOrNull { it.id == selectedProductId }
                        ExposedDropdownMenuBox(
                            expanded = productMenuExpanded,
                            onExpandedChange = { productMenuExpanded = !productMenuExpanded }
                        ) {
                            OutlinedTextField(
                                value = selectedProduct?.let { "${it.name} (Bestand: ${it.quantity})" } ?: "Produkt auswählen",
                                onValueChange = {},
                                readOnly = true,
                                trailingIcon = { ExposedDropdownMenuDefaults.TrailingIcon(expanded = productMenuExpanded) },
                                modifier = Modifier.menuAnchor().fillMaxWidth()
                            )
                            ExposedDropdownMenu(
                                expanded = productMenuExpanded,
                                onDismissRequest = { productMenuExpanded = false }
                            ) {
                                data.physical_products.forEach { prod ->
                                    DropdownMenuItem(
                                        text = { Text("${prod.name} (Bestand: ${prod.quantity})") },
                                        onClick = {
                                            selectedProductId = prod.id
                                            productMenuExpanded = false
                                        }
                                    )
                                }
                            }
                        }

                        OutlinedTextField(
                            value = lossQtyText,
                            onValueChange = { lossQtyText = it.filter { ch -> ch.isDigit() } },
                            label = { Text("Menge") },
                            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                            singleLine = true,
                            modifier = Modifier.fillMaxWidth()
                        )
                        OutlinedTextField(
                            value = lossReason,
                            onValueChange = { lossReason = it },
                            label = { Text("Grund") },
                            singleLine = true,
                            modifier = Modifier.fillMaxWidth()
                        )
                        Button(
                            onClick = {
                                val qty = lossQtyText.toIntOrNull() ?: 1
                                viewModel.reportInventoryLoss(selectedProductId, qty, lossReason) {
                                    lossQtyText = "1"
                                    lossReason = ""
                                }
                            },
                            enabled = !isBusy && selectedProductId.isNotBlank() && lossReason.trim().length >= 2,
                            modifier = Modifier.fillMaxWidth(),
                            colors = ButtonDefaults.buttonColors(containerColor = Gold, contentColor = SpaceBlack)
                        ) {
                            Text("Bruch speichern", fontWeight = FontWeight.Bold)
                        }
                    }
                }
            }
        }
    }
}

@Composable
private fun EmployeeSegmentChip(
    modifier: Modifier = Modifier,
    title: String,
    selected: Boolean,
    onClick: () -> Unit
) {
    Box(
        modifier = modifier
            .clip(RoundedCornerShape(10.dp))
            .background(if (selected) Gold else AppTheme.colors.cardBackground)
            .border(1.dp, if (selected) Gold else AppTheme.colors.cardBorder, RoundedCornerShape(10.dp))
            .clickable(onClick = onClick)
            .padding(vertical = 9.dp, horizontal = 8.dp),
        contentAlignment = Alignment.Center
    ) {
        Text(
            text = title,
            fontSize = 11.5.sp,
            fontWeight = FontWeight.Bold,
            color = if (selected) SpaceBlack else AppTheme.colors.textPrimary,
            maxLines = 1,
            overflow = TextOverflow.Ellipsis
        )
    }
}

@Composable
private fun EmployeeTimeEntryRow(
    entry: EmployeeTimeEntryDto,
    onDelete: () -> Unit
) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(10.dp))
            .background(AppTheme.colors.glassSurface)
            .padding(horizontal = 10.dp, vertical = 8.dp),
        horizontalArrangement = Arrangement.SpaceBetween,
        verticalAlignment = Alignment.CenterVertically
    ) {
        Column(modifier = Modifier.weight(1f)) {
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalAlignment = Alignment.CenterVertically) {
                Text(
                    text = entry.work_date_formatted,
                    fontSize = 12.sp,
                    fontWeight = FontWeight.Bold,
                    color = AppTheme.colors.textPrimary
                )
                val detail = when (entry.entry_type) {
                    "vacation" -> "Urlaub"
                    "sick" -> "Krank"
                    else -> "${entry.started_at_time ?: ""} bis ${entry.ended_at_time ?: ""} (${entry.formatted_hours})"
                }
                Text(text = detail, fontSize = 11.5.sp, color = Gold, fontWeight = FontWeight.SemiBold)
            }
            if (entry.activity_note.isNotBlank()) {
                Text(text = entry.activity_note, fontSize = 11.sp, color = AppTheme.colors.textMuted)
            }
        }
        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(4.dp)) {
            Text(
                text = if (entry.status == "approved") "✓" else "Offen",
                fontSize = 11.sp,
                fontWeight = FontWeight.Bold,
                color = if (entry.status == "approved") Emerald500 else Gold
            )
            if (entry.can_delete) {
                IconButton(onClick = onDelete, modifier = Modifier.size(26.dp)) {
                    Icon(Icons.Default.Delete, contentDescription = "Löschen", tint = Rose500, modifier = Modifier.size(16.dp))
                }
            }
        }
    }
}

@Composable
private fun EmployeePayslipRow(
    slip: EmployeePayslipDto,
    onDownload: () -> Unit
) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(10.dp))
            .background(AppTheme.colors.glassSurface)
            .padding(horizontal = 12.dp, vertical = 10.dp),
        horizontalArrangement = Arrangement.SpaceBetween,
        verticalAlignment = Alignment.CenterVertically
    ) {
        Column(modifier = Modifier.weight(1f)) {
            Text(text = slip.period_label, fontSize = 13.sp, fontWeight = FontWeight.Bold, color = AppTheme.colors.textPrimary)
            Text(text = "Auszahlung: ${slip.net_payout_formatted}", fontSize = 11.5.sp, color = AppTheme.colors.textMuted)
        }
        Button(
            onClick = onDownload,
            shape = RoundedCornerShape(9.dp),
            colors = ButtonDefaults.buttonColors(containerColor = Gold, contentColor = SpaceBlack)
        ) {
            Text("PDF", fontWeight = FontWeight.Bold, fontSize = 12.sp)
        }
    }
}
