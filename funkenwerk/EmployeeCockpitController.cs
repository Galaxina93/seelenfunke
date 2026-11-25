using System;
using System.Collections.Generic;
using System.Diagnostics;
using System.Globalization;
using System.IO;
using System.Linq;
using System.Net.Http;
using System.Text;
using System.Text.Json;
using System.Threading.Tasks;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Input;
using System.Windows.Media;

namespace Funkenwerk
{
    public sealed class EmployeeCockpitState
    {
        public bool HasContract { get; set; }
        public int SelectedYear { get; set; } = DateTime.Now.Year;
        public int SelectedMonth { get; set; } = DateTime.Now.Month;
        public string FullName { get; set; } = "";
        public string Email { get; set; } = "";
        public string Initials { get; set; } = "SF";
        public string RankClass { get; set; } = "KLASSE C";
        public int EpTotal { get; set; }
        public int EfficiencyScore { get; set; } = 100;
        public string JobTitle { get; set; } = "";
        public string Department { get; set; } = "";
        public double WeeklyHours { get; set; }
        public bool CanAccessTasks { get; set; } = true;
        public bool CanAccessProduction { get; set; }
        public bool CanAccessSupport { get; set; }
        public bool CanAccessInventory { get; set; }
        public bool IsClockedIn { get; set; }
        public string ClockedInSinceTime { get; set; } = "";
        public string ClockedInNote { get; set; } = "";
        public string WorkedHoursFormatted { get; set; } = "0:00 Std.";
        public int RemainingVacationDays { get; set; }
        public int VacationDaysPerYear { get; set; }
        public int AssignedOpenTasksCount { get; set; }
        public int OpenOrdersCount { get; set; }
        public int ExpressOrdersCount { get; set; }
        public List<EmployeeTimeEntryItem> TimeEntries { get; set; } = new();
        public List<EmployeePayslipItem> Payslips { get; set; } = new();
        public List<EmployeeProductItem> PhysicalProducts { get; set; } = new();
    }

    public sealed class EmployeeTimeEntryItem
    {
        public string Id { get; set; } = "";
        public string WorkDateFormatted { get; set; } = "";
        public string EntryType { get; set; } = "work";
        public string StartedAtTime { get; set; } = "";
        public string EndedAtTime { get; set; } = "";
        public int BreakMinutes { get; set; }
        public string FormattedHours { get; set; } = "";
        public string ActivityNote { get; set; } = "";
        public string Status { get; set; } = "";
        public bool CanDelete { get; set; }
    }

    public sealed class EmployeePayslipItem
    {
        public string Id { get; set; } = "";
        public string PayslipNumber { get; set; } = "";
        public string PeriodLabel { get; set; } = "";
        public string NetPayoutFormatted { get; set; } = "";
        public string Status { get; set; } = "";
    }

    public sealed class EmployeeProductItem
    {
        public string Id { get; set; } = "";
        public string Name { get; set; } = "";
        public int Quantity { get; set; }
    }

    public partial class MainWindow
    {
        private EmployeeCockpitState? _employeeCockpitState;
        private string _employeeCockpitSubTab = "overview"; // "overview", "times", "payslips", "loss"
        private string _employeeCockpitFeedback = "";
        private bool _employeeCockpitFeedbackOk = true;
        private bool _isLoadingEmployeeCockpit = false;
        private int _empSelectedYear = DateTime.Now.Year;
        private int _empSelectedMonth = DateTime.Now.Month;

        private void ClearEmployeeCockpitMemory()
        {
            _employeeCockpitState = null;
            _employeeCockpitFeedback = "";
            _employeeCockpitSubTab = "overview";
            if (EmployeeCockpitHostBorder != null)
            {
                EmployeeCockpitHostBorder.Child = null;
                EmployeeCockpitHostBorder.Visibility = Visibility.Collapsed;
            }
        }

        private async Task LoadEmployeeCockpitAsync(bool silent = false)
        {
            if (FunkenwerkSharedSession.IsAdminSession())
            {
                ClearEmployeeCockpitMemory();
                return;
            }

            if (_isLoadingEmployeeCockpit) return;
            _isLoadingEmployeeCockpit = true;

            try
            {
                using var cts = new System.Threading.CancellationTokenSource(TimeSpan.FromSeconds(10));
                string url = $"{_apiBaseUrl}/employee/cockpit?year={_empSelectedYear}&month={_empSelectedMonth}";
                using var res = await _http.GetAsync(url, cts.Token);
                if (!res.IsSuccessStatusCode)
                {
                    if (!silent)
                    {
                        _employeeCockpitFeedback = $"Status {(int)res.StatusCode}";
                        _employeeCockpitFeedbackOk = false;
                    }
                    RenderEmployeeCockpitUI();
                    return;
                }

                string json = await res.Content.ReadAsStringAsync(cts.Token);
                using var doc = JsonDocument.Parse(json);
                if (doc.RootElement.TryGetProperty("data", out var dataEl) && dataEl.ValueKind == JsonValueKind.Object)
                {
                    ParseEmployeeCockpitPayload(dataEl);
                }
            }
            catch
            {
                if (!silent && _employeeCockpitState == null)
                {
                    _employeeCockpitFeedback = "Keine Verbindung zum Portal.";
                    _employeeCockpitFeedbackOk = false;
                }
            }
            finally
            {
                _isLoadingEmployeeCockpit = false;
                RenderEmployeeCockpitUI();
            }
        }

        private void ParseEmployeeCockpitPayload(JsonElement data)
        {
            var st = new EmployeeCockpitState
            {
                HasContract = data.TryGetProperty("has_contract", out var hc) && hc.ValueKind == JsonValueKind.True,
                SelectedYear = data.TryGetProperty("selected_year", out var sy) && sy.ValueKind == JsonValueKind.Number ? sy.GetInt32() : _empSelectedYear,
                SelectedMonth = data.TryGetProperty("selected_month", out var sm) && sm.ValueKind == JsonValueKind.Number ? sm.GetInt32() : _empSelectedMonth
            };

            if (data.TryGetProperty("employee", out var emp) && emp.ValueKind == JsonValueKind.Object)
            {
                st.FullName = emp.TryGetProperty("full_name", out var fn) ? (fn.GetString() ?? "") : "";
                st.Email = emp.TryGetProperty("email", out var em) ? (em.GetString() ?? "") : "";
            }

            if (data.TryGetProperty("operator_profile", out var op) && op.ValueKind == JsonValueKind.Object)
            {
                st.Initials = op.TryGetProperty("initials", out var ini) ? (ini.GetString() ?? "SF") : "SF";
                st.RankClass = op.TryGetProperty("rank_class", out var rc) ? (rc.GetString() ?? "KLASSE C") : "KLASSE C";
                st.EpTotal = op.TryGetProperty("ep_total", out var ep) && ep.ValueKind == JsonValueKind.Number ? ep.GetInt32() : 0;
                st.EfficiencyScore = op.TryGetProperty("efficiency_score", out var es) && es.ValueKind == JsonValueKind.Number ? es.GetInt32() : 100;
            }

            if (data.TryGetProperty("contract", out var ctr) && ctr.ValueKind == JsonValueKind.Object)
            {
                st.JobTitle = ctr.TryGetProperty("job_title", out var jt) ? (jt.GetString() ?? "") : "";
                st.Department = ctr.TryGetProperty("department", out var dp) ? (dp.GetString() ?? "") : "";
                st.WeeklyHours = ctr.TryGetProperty("weekly_hours", out var wh) && wh.ValueKind == JsonValueKind.Number ? wh.GetDouble() : 0;
                st.CanAccessTasks = !ctr.TryGetProperty("can_access_tasks", out var ct) || ct.ValueKind == JsonValueKind.True;
                st.CanAccessProduction = ctr.TryGetProperty("can_access_production", out var cp) && cp.ValueKind == JsonValueKind.True;
                st.CanAccessSupport = ctr.TryGetProperty("can_access_support", out var cs) && cs.ValueKind == JsonValueKind.True;
                st.CanAccessInventory = ctr.TryGetProperty("can_access_inventory", out var ci) && ci.ValueKind == JsonValueKind.True;
            }

            if (data.TryGetProperty("running_entry", out var run) && run.ValueKind == JsonValueKind.Object)
            {
                st.IsClockedIn = true;
                st.ClockedInSinceTime = run.TryGetProperty("started_at_time", out var sat) ? (sat.GetString() ?? "") : "";
                st.ClockedInNote = run.TryGetProperty("activity_note", out var an) ? (an.GetString() ?? "") : "";
            }

            if (data.TryGetProperty("kpis", out var kpis) && kpis.ValueKind == JsonValueKind.Object)
            {
                st.WorkedHoursFormatted = kpis.TryGetProperty("worked_hours_formatted", out var whf) ? (whf.GetString() ?? "0:00 Std.") : "0:00 Std.";
                st.RemainingVacationDays = kpis.TryGetProperty("remaining_vacation_days", out var rvd) && rvd.ValueKind == JsonValueKind.Number ? rvd.GetInt32() : 0;
                st.VacationDaysPerYear = kpis.TryGetProperty("vacation_days_per_year", out var vpy) && vpy.ValueKind == JsonValueKind.Number ? vpy.GetInt32() : 0;
                st.AssignedOpenTasksCount = kpis.TryGetProperty("assigned_open_tasks_count", out var aot) && aot.ValueKind == JsonValueKind.Number ? aot.GetInt32() : 0;
                st.OpenOrdersCount = kpis.TryGetProperty("open_orders_count", out var ooc) && ooc.ValueKind == JsonValueKind.Number ? ooc.GetInt32() : 0;
                st.ExpressOrdersCount = kpis.TryGetProperty("express_orders_count", out var eoc) && eoc.ValueKind == JsonValueKind.Number ? eoc.GetInt32() : 0;
            }

            if (data.TryGetProperty("time_entries", out var teArr) && teArr.ValueKind == JsonValueKind.Array)
            {
                foreach (var el in teArr.EnumerateArray())
                {
                    st.TimeEntries.Add(new EmployeeTimeEntryItem
                    {
                        Id = el.TryGetProperty("id", out var idEl) ? (idEl.GetString() ?? "") : "",
                        WorkDateFormatted = el.TryGetProperty("work_date_formatted", out var wdf) ? (wdf.GetString() ?? "") : "",
                        EntryType = el.TryGetProperty("entry_type", out var et) ? (et.GetString() ?? "work") : "work",
                        StartedAtTime = el.TryGetProperty("started_at_time", out var stt) && stt.ValueKind == JsonValueKind.String ? (stt.GetString() ?? "") : "",
                        EndedAtTime = el.TryGetProperty("ended_at_time", out var ett) && ett.ValueKind == JsonValueKind.String ? (ett.GetString() ?? "") : "",
                        BreakMinutes = el.TryGetProperty("break_minutes", out var bm) && bm.ValueKind == JsonValueKind.Number ? bm.GetInt32() : 0,
                        FormattedHours = el.TryGetProperty("formatted_hours", out var fh) ? (fh.GetString() ?? "") : "",
                        ActivityNote = el.TryGetProperty("activity_note", out var an) ? (an.GetString() ?? "") : "",
                        Status = el.TryGetProperty("status", out var stEl) ? (stEl.GetString() ?? "") : "",
                        CanDelete = el.TryGetProperty("can_delete", out var cd) && cd.ValueKind == JsonValueKind.True
                    });
                }
            }

            if (data.TryGetProperty("payslips", out var psArr) && psArr.ValueKind == JsonValueKind.Array)
            {
                foreach (var el in psArr.EnumerateArray())
                {
                    st.Payslips.Add(new EmployeePayslipItem
                    {
                        Id = el.TryGetProperty("id", out var idEl) ? (idEl.GetString() ?? "") : "",
                        PayslipNumber = el.TryGetProperty("payslip_number", out var pn) ? (pn.GetString() ?? "") : "",
                        PeriodLabel = el.TryGetProperty("period_label", out var pl) ? (pl.GetString() ?? "") : "",
                        NetPayoutFormatted = el.TryGetProperty("net_payout_formatted", out var npf) ? (npf.GetString() ?? "") : "",
                        Status = el.TryGetProperty("status", out var stEl) ? (stEl.GetString() ?? "") : ""
                    });
                }
            }

            if (data.TryGetProperty("physical_products", out var ppArr) && ppArr.ValueKind == JsonValueKind.Array)
            {
                foreach (var el in ppArr.EnumerateArray())
                {
                    st.PhysicalProducts.Add(new EmployeeProductItem
                    {
                        Id = el.TryGetProperty("id", out var idEl) ? (idEl.GetString() ?? "") : "",
                        Name = el.TryGetProperty("name", out var nm) ? (nm.GetString() ?? "") : "",
                        Quantity = el.TryGetProperty("quantity", out var qt) && qt.ValueKind == JsonValueKind.Number ? qt.GetInt32() : 0
                    });
                }
            }

            if (data.TryGetProperty("permissions", out var permsEl) && permsEl.ValueKind == JsonValueKind.Object)
            {
                ApplyAuthPermissionsFromJsonElement(data);
            }

            _employeeCockpitState = st;
        }

        private async Task PostEmployeeActionAsync(HttpMethod method, string relativeEndpoint, object? payload = null)
        {
            try
            {
                using var cts = new System.Threading.CancellationTokenSource(TimeSpan.FromSeconds(12));
                using var req = new HttpRequestMessage(method, $"{_apiBaseUrl}{relativeEndpoint}");
                if (payload != null)
                {
                    string body = JsonSerializer.Serialize(payload);
                    req.Content = new StringContent(body, Encoding.UTF8, "application/json");
                }

                using var res = await _http.SendAsync(req, cts.Token);
                string raw = await res.Content.ReadAsStringAsync(cts.Token);

                if (!string.IsNullOrWhiteSpace(raw))
                {
                    using var doc = JsonDocument.Parse(raw);
                    string msg = doc.RootElement.TryGetProperty("message", out var mEl) && mEl.ValueKind == JsonValueKind.String
                        ? (mEl.GetString() ?? "")
                        : "";
                    _employeeCockpitFeedback = !string.IsNullOrWhiteSpace(msg)
                        ? msg
                        : (res.IsSuccessStatusCode ? "Gespeichert." : $"Fehler ({(int)res.StatusCode})");
                    _employeeCockpitFeedbackOk = res.IsSuccessStatusCode;

                    if (doc.RootElement.TryGetProperty("data", out var dataEl) && dataEl.ValueKind == JsonValueKind.Object)
                    {
                        ParseEmployeeCockpitPayload(dataEl);
                    }
                    else if (res.IsSuccessStatusCode)
                    {
                        await LoadEmployeeCockpitAsync(silent: true);
                        return;
                    }
                }

                if (res.IsSuccessStatusCode) PlaySoundTaskDone();
            }
            catch (Exception ex)
            {
                _employeeCockpitFeedback = $"Fehler: {ex.Message}";
                _employeeCockpitFeedbackOk = false;
            }

            RenderEmployeeCockpitUI();
        }

        private async Task DownloadAndOpenEmployeePdfAsync(string relativeEndpoint, string fallbackFileName)
        {
            try
            {
                using var cts = new System.Threading.CancellationTokenSource(TimeSpan.FromSeconds(20));
                using var res = await _http.GetAsync($"{_apiBaseUrl}{relativeEndpoint}", cts.Token);
                if (!res.IsSuccessStatusCode)
                {
                    _employeeCockpitFeedback = "Dokument konnte nicht geladen werden.";
                    _employeeCockpitFeedbackOk = false;
                    RenderEmployeeCockpitUI();
                    return;
                }

                byte[] bytes = await res.Content.ReadAsByteArrayAsync(cts.Token);
                if (bytes.Length == 0)
                {
                    _employeeCockpitFeedback = "Dokument ist leer.";
                    _employeeCockpitFeedbackOk = false;
                    RenderEmployeeCockpitUI();
                    return;
                }

                string tempDir = Path.Combine(FunkenwerkSharedSession.GetSharedDataDirectory(), "EmployeeDocs");
                Directory.CreateDirectory(tempDir);
                string safeFile = Path.GetFileName(fallbackFileName);
                string fullPath = Path.Combine(tempDir, safeFile);
                await File.WriteAllBytesAsync(fullPath, bytes, cts.Token);

                PlaySoundActionPop();

                var pdfSpec = CollectionModuleRegistry.Modules.FirstOrDefault(m => string.Equals(m.Key, "pdf", StringComparison.OrdinalIgnoreCase));
                string pdfExe = pdfSpec != null ? CollectionModuleRegistry.ResolveModuleExePath(pdfSpec) : "";
                if (!string.IsNullOrEmpty(pdfExe) && File.Exists(pdfExe))
                {
                    Process.Start(new ProcessStartInfo
                    {
                        FileName = pdfExe,
                        Arguments = $"\"{fullPath}\"",
                        UseShellExecute = false
                    });
                }
                else
                {
                    Process.Start(new ProcessStartInfo
                    {
                        FileName = fullPath,
                        UseShellExecute = true
                    });
                }
            }
            catch (Exception ex)
            {
                _employeeCockpitFeedback = $"PDF-Fehler: {ex.Message}";
                _employeeCockpitFeedbackOk = false;
                RenderEmployeeCockpitUI();
            }
        }

        private void RenderEmployeeCockpitUI()
        {
            if (EmployeeCockpitHostBorder == null) return;

            bool isAdmin = FunkenwerkSharedSession.IsAdminSession();
            if (isAdmin)
            {
                EmployeeCockpitHostBorder.Child = null;
                EmployeeCockpitHostBorder.Visibility = Visibility.Collapsed;
                if (LauncherSearchBorder != null) LauncherSearchBorder.Visibility = Visibility.Visible;
                return;
            }

            if (LauncherSearchBorder != null)
            {
                var curSession = FunkenwerkSharedSession.Load();
                int allowedTiles = CollectionModuleRegistry.Modules.Count(m => FunkenwerkSharedSession.IsModuleAllowed(m.Key, curSession));
                LauncherSearchBorder.Visibility = allowedTiles > 4 ? Visibility.Visible : Visibility.Collapsed;
            }

            EmployeeCockpitHostBorder.Visibility = Visibility.Visible;

            Brush cardBg = BrushFromHex(_isDarkMode ? "#141720" : "#FFFFFF");
            Brush subCardBg = BrushFromHex(_isDarkMode ? "#0F1117" : "#F8FAFC");
            Brush cardBorder = BrushFromHex(_isDarkMode ? "#262B3C" : "#E2E8F0");
            Brush goldBrush = BrushFromHex("#C5A059");
            Brush goldSoftBg = BrushFromHex(_isDarkMode ? "#26C5A059" : "#FDF8ED");
            Brush textPrimary = BrushFromHex(_isDarkMode ? "#F9FAFB" : "#111827");
            Brush textMuted = BrushFromHex(_isDarkMode ? "#9CA3AF" : "#6B7280");
            Brush emeraldBrush = BrushFromHex("#10B981");
            Brush roseBrush = BrushFromHex("#EF4444");

            var rootStack = new StackPanel();

            var st = _employeeCockpitState;
            if (st == null)
            {
                var loadingCard = new Border
                {
                    Background = cardBg,
                    BorderBrush = cardBorder,
                    BorderThickness = new Thickness(1),
                    CornerRadius = new CornerRadius(16),
                    Padding = new Thickness(24, 20, 24, 20)
                };
                var row = new StackPanel { Orientation = Orientation.Horizontal, HorizontalAlignment = HorizontalAlignment.Center };
                row.Children.Add(new TextBlock
                {
                    Text = _isLoadingEmployeeCockpit ? "Mitarbeiter-Cockpit wird geladen..." : (!string.IsNullOrEmpty(_employeeCockpitFeedback) ? _employeeCockpitFeedback : "Mitarbeiter-Cockpit bereit"),
                    Foreground = textPrimary,
                    FontSize = 14,
                    FontWeight = FontWeights.SemiBold,
                    VerticalAlignment = VerticalAlignment.Center
                });
                var retryBtn = CreateCockpitActionButton("Aktualisieren", goldSoftBg, goldBrush, goldBrush);
                retryBtn.Margin = new Thickness(14, 0, 0, 0);
                retryBtn.Click += async (_, _) => await LoadEmployeeCockpitAsync(silent: false);
                row.Children.Add(retryBtn);
                loadingCard.Child = row;
                rootStack.Children.Add(loadingCard);
                EmployeeCockpitHostBorder.Child = rootStack;
                return;
            }

            // 1. HEADER CARD (Profile + Rank/EP + PDF Buttons + Live Clock-In/Out)
            var headerCard = new Border
            {
                Background = cardBg,
                BorderBrush = cardBorder,
                BorderThickness = new Thickness(1),
                CornerRadius = new CornerRadius(18),
                Padding = new Thickness(22, 18, 22, 18),
                Margin = new Thickness(0, 12, 0, 14)
            };

            var headerOuter = new StackPanel();
            var topGrid = new Grid();
            topGrid.ColumnDefinitions.Add(new ColumnDefinition { Width = GridLength.Auto });
            topGrid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
            topGrid.ColumnDefinitions.Add(new ColumnDefinition { Width = GridLength.Auto });

            var avatarCircle = new Border
            {
                Width = 48,
                Height = 48,
                CornerRadius = new CornerRadius(24),
                Background = goldSoftBg,
                BorderBrush = goldBrush,
                BorderThickness = new Thickness(1.5),
                Margin = new Thickness(0, 0, 14, 0),
                VerticalAlignment = VerticalAlignment.Center
            };
            avatarCircle.Child = new TextBlock
            {
                Text = string.IsNullOrWhiteSpace(st.Initials) ? "SF" : st.Initials,
                Foreground = goldBrush,
                FontSize = 16,
                FontWeight = FontWeights.Bold,
                HorizontalAlignment = HorizontalAlignment.Center,
                VerticalAlignment = VerticalAlignment.Center
            };
            Grid.SetColumn(avatarCircle, 0);
            topGrid.Children.Add(avatarCircle);

            var titleStack = new StackPanel { VerticalAlignment = VerticalAlignment.Center };
            var nameRow = new StackPanel { Orientation = Orientation.Horizontal, VerticalAlignment = VerticalAlignment.Center };
            nameRow.Children.Add(new TextBlock
            {
                Text = string.IsNullOrWhiteSpace(st.FullName) ? "Team" : st.FullName,
                Foreground = textPrimary,
                FontSize = 18,
                FontWeight = FontWeights.Bold,
                VerticalAlignment = VerticalAlignment.Center
            });

            var rankBadge = new Border
            {
                Background = goldSoftBg,
                BorderBrush = goldBrush,
                BorderThickness = new Thickness(1),
                CornerRadius = new CornerRadius(7),
                Padding = new Thickness(8, 2, 8, 2),
                Margin = new Thickness(10, 0, 0, 0),
                VerticalAlignment = VerticalAlignment.Center
            };
            rankBadge.Child = new TextBlock
            {
                Text = $"{st.RankClass} · {st.EpTotal} EP",
                Foreground = goldBrush,
                FontSize = 11,
                FontWeight = FontWeights.Bold
            };
            nameRow.Children.Add(rankBadge);
            titleStack.Children.Add(nameRow);

            string jobSub = st.HasContract
                ? $"{(string.IsNullOrWhiteSpace(st.JobTitle) ? "Team" : st.JobTitle)}{(string.IsNullOrWhiteSpace(st.Department) ? "" : " · " + st.Department)}"
                : st.Email;
            titleStack.Children.Add(new TextBlock
            {
                Text = jobSub,
                Foreground = textMuted,
                FontSize = 12.5,
                Margin = new Thickness(0, 3, 0, 0)
            });
            Grid.SetColumn(titleStack, 1);
            topGrid.Children.Add(titleStack);

            // Right Action Bar: Vertrag PDF | Stundenzettel PDF | Live Clock Button
            var actionsRow = new StackPanel { Orientation = Orientation.Horizontal, VerticalAlignment = VerticalAlignment.Center };
            if (st.HasContract)
            {
                if (st.IsClockedIn)
                {
                    var activeBadge = new Border
                    {
                        Background = BrushFromHex("#2210B981"),
                        BorderBrush = emeraldBrush,
                        BorderThickness = new Thickness(1),
                        CornerRadius = new CornerRadius(8),
                        Padding = new Thickness(10, 6, 10, 6),
                        Margin = new Thickness(0, 0, 8, 0),
                        VerticalAlignment = VerticalAlignment.Center
                    };
                    activeBadge.Child = new TextBlock
                    {
                        Text = $"● Aktiv seit {st.ClockedInSinceTime} Uhr",
                        Foreground = emeraldBrush,
                        FontSize = 12,
                        FontWeight = FontWeights.Bold
                    };
                    actionsRow.Children.Add(activeBadge);
                }

                var contractBtn = CreateCockpitActionButton("Vertrag PDF", subCardBg, cardBorder, textPrimary);
                contractBtn.Margin = new Thickness(0, 0, 8, 0);
                contractBtn.Click += async (_, _) => await DownloadAndOpenEmployeePdfAsync("/employee/documents/contract", "Arbeitsvertrag.pdf");
                actionsRow.Children.Add(contractBtn);

                var timesheetBtn = CreateCockpitActionButton("Stundenzettel PDF", subCardBg, cardBorder, textPrimary);
                timesheetBtn.Margin = new Thickness(0, 0, 10, 0);
                timesheetBtn.Click += async (_, _) => await DownloadAndOpenEmployeePdfAsync($"/employee/documents/timesheet/{_empSelectedYear}/{_empSelectedMonth}", $"Stundenzettel_{_empSelectedYear}_{_empSelectedMonth:D2}.pdf");
                actionsRow.Children.Add(timesheetBtn);

                var clockBtn = st.IsClockedIn
                    ? CreateCockpitActionButton("⏹ Ausstempeln", roseBrush, roseBrush, Brushes.White)
                    : CreateCockpitActionButton("▶ Einstempeln", emeraldBrush, emeraldBrush, Brushes.White);
                clockBtn.Click += async (_, _) =>
                {
                    if (st.IsClockedIn)
                        await PostEmployeeActionAsync(HttpMethod.Post, "/employee/clock-out", new { activity_note = st.ClockedInNote });
                    else
                        await PostEmployeeActionAsync(HttpMethod.Post, "/employee/clock-in", new { activity_note = "Manufaktur & Versand" });
                };
                actionsRow.Children.Add(clockBtn);
            }

            Grid.SetColumn(actionsRow, 2);
            topGrid.Children.Add(actionsRow);
            headerOuter.Children.Add(topGrid);

            if (!string.IsNullOrWhiteSpace(_employeeCockpitFeedback))
            {
                var fbBorder = new Border
                {
                    Background = BrushFromHex(_employeeCockpitFeedbackOk ? "#1F10B981" : "#1FEF4444"),
                    BorderBrush = _employeeCockpitFeedbackOk ? emeraldBrush : roseBrush,
                    BorderThickness = new Thickness(1),
                    CornerRadius = new CornerRadius(10),
                    Padding = new Thickness(12, 7, 12, 7),
                    Margin = new Thickness(0, 12, 0, 0)
                };
                var fbGrid = new Grid();
                fbGrid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
                fbGrid.ColumnDefinitions.Add(new ColumnDefinition { Width = GridLength.Auto });
                fbGrid.Children.Add(new TextBlock
                {
                    Text = _employeeCockpitFeedback,
                    Foreground = _employeeCockpitFeedbackOk ? emeraldBrush : roseBrush,
                    FontSize = 12.5,
                    FontWeight = FontWeights.SemiBold,
                    VerticalAlignment = VerticalAlignment.Center
                });
                var closeFb = new Button
                {
                    Content = "✕",
                    Background = Brushes.Transparent,
                    BorderThickness = new Thickness(0),
                    Foreground = textMuted,
                    Cursor = Cursors.Hand,
                    Padding = new Thickness(6, 0, 2, 0)
                };
                closeFb.Click += (_, _) => { _employeeCockpitFeedback = ""; RenderEmployeeCockpitUI(); };
                Grid.SetColumn(closeFb, 1);
                fbGrid.Children.Add(closeFb);
                fbBorder.Child = fbGrid;
                headerOuter.Children.Add(fbBorder);
            }

            // 4 KPI PILLS (1:1 Web EmployeeDashboard)
            if (st.HasContract)
            {
                var kpiGrid = new System.Windows.Controls.Primitives.UniformGrid
                {
                    Columns = 4,
                    Rows = 1,
                    Margin = new Thickness(0, 16, 0, 0)
                };

                kpiGrid.Children.Add(CreateKpiPill("MONATSSTUNDEN", st.WorkedHoursFormatted, $"{st.WeeklyHours:0.#} Std./Woche", subCardBg, cardBorder, textPrimary, textMuted, goldBrush, new Thickness(0, 0, 6, 0), () =>
                {
                    _employeeCockpitSubTab = "times";
                    RenderEmployeeCockpitUI();
                }));

                kpiGrid.Children.Add(CreateKpiPill("RESTURLAUB", $"{st.RemainingVacationDays} Tage", $"von {st.VacationDaysPerYear} Tagen", subCardBg, cardBorder, textPrimary, textMuted, emeraldBrush, new Thickness(6, 0, 6, 0), () =>
                {
                    _employeeCockpitSubTab = "times";
                    RenderEmployeeCockpitUI();
                }));

                kpiGrid.Children.Add(CreateKpiPill("MEINE AUFGABEN", $"{st.AssignedOpenTasksCount} offen", st.CanAccessTasks ? "Aufgaben öffnen" : "Gesperrt", subCardBg, cardBorder, textPrimary, textMuted, goldBrush, new Thickness(6, 0, 6, 0), () =>
                {
                    if (st.CanAccessTasks && FunkenwerkSharedSession.IsSpaceAllowed("todo"))
                    {
                        PlaySoundNav();
                        SwitchSpaceUI("todo");
                    }
                }));

                kpiGrid.Children.Add(CreateKpiPill("SCORE", $"{st.EfficiencyScore} %", $"{st.EpTotal} EP", subCardBg, cardBorder, textPrimary, textMuted, goldBrush, new Thickness(6, 0, 0, 0), null));

                headerOuter.Children.Add(kpiGrid);
            }

            headerCard.Child = headerOuter;
            rootStack.Children.Add(headerCard);

            if (!st.HasContract)
            {
                EmployeeCockpitHostBorder.Child = rootStack;
                return;
            }

            // 2. COCKPIT SUB-NAVIGATION BAR (Übersicht | Zeiten & Urlaub | Lohnzettel | optional Bruch melden)
            var tabBarBorder = new Border
            {
                Background = cardBg,
                BorderBrush = cardBorder,
                BorderThickness = new Thickness(1),
                CornerRadius = new CornerRadius(12),
                Padding = new Thickness(6),
                Margin = new Thickness(0, 0, 0, 14)
            };
            var tabRow = new StackPanel { Orientation = Orientation.Horizontal };

            void AddSubTabButton(string key, string title)
            {
                bool active = _employeeCockpitSubTab == key;
                var b = new Border
                {
                    Background = active ? goldBrush : Brushes.Transparent,
                    CornerRadius = new CornerRadius(8),
                    Padding = new Thickness(14, 7, 14, 7),
                    Margin = new Thickness(0, 0, 6, 0),
                    Cursor = Cursors.Hand
                };
                b.Child = new TextBlock
                {
                    Text = title,
                    Foreground = active ? BrushFromHex("#111827") : textPrimary,
                    FontSize = 12.5,
                    FontWeight = active ? FontWeights.Bold : FontWeights.SemiBold
                };
                b.MouseLeftButtonUp += (_, _) =>
                {
                    PlaySoundNav();
                    _employeeCockpitSubTab = key;
                    RenderEmployeeCockpitUI();
                };
                tabRow.Children.Add(b);
            }

            AddSubTabButton("overview", "Übersicht");
            AddSubTabButton("times", "Zeiten & Urlaub");
            AddSubTabButton("payslips", $"Lohnzettel ({st.Payslips.Count})");
            if (st.CanAccessInventory)
            {
                AddSubTabButton("loss", "Bruch melden");
            }

            tabBarBorder.Child = tabRow;
            rootStack.Children.Add(tabBarBorder);

            // 3. ACTIVE SUB-TAB CONTENT
            if (_employeeCockpitSubTab == "overview")
            {
                var quickGrid = new System.Windows.Controls.Primitives.UniformGrid
                {
                    Columns = st.CanAccessProduction ? 3 : 2,
                    Rows = 1
                };

                if (st.CanAccessTasks)
                {
                    quickGrid.Children.Add(CreateQuickNavCard(
                        "Aufgaben",
                        $"{st.AssignedOpenTasksCount} zugewiesene Aufgaben",
                        "Öffnen →",
                        cardBg, cardBorder, textPrimary, textMuted, goldBrush,
                        new Thickness(0, 0, 8, 0),
                        () => { PlaySoundNav(); SwitchSpaceUI("todo"); }));
                }

                if (st.CanAccessProduction)
                {
                    string orderHint = st.ExpressOrdersCount > 0
                        ? $"{st.OpenOrdersCount} offen · {st.ExpressOrdersCount} Express"
                        : $"{st.OpenOrdersCount} offene Bestellungen";
                    quickGrid.Children.Add(CreateQuickNavCard(
                        "Packtisch & Versand",
                        orderHint,
                        "Öffnen →",
                        cardBg, cardBorder, textPrimary, textMuted, goldBrush,
                        new Thickness(4, 0, 8, 0),
                        () => { PlaySoundNav(); SwitchSpaceUI("orders"); }));
                }

                quickGrid.Children.Add(CreateQuickNavCard(
                    "Arbeitszeit & Lohnzettel",
                    $"{st.TimeEntries.Count} Einträge · {st.Payslips.Count} Abrechnungen",
                    "Öffnen →",
                    cardBg, cardBorder, textPrimary, textMuted, goldBrush,
                    new Thickness(4, 0, 0, 0),
                    () => { PlaySoundNav(); _employeeCockpitSubTab = "times"; RenderEmployeeCockpitUI(); }));

                rootStack.Children.Add(quickGrid);
            }
            else if (_employeeCockpitSubTab == "times")
            {
                rootStack.Children.Add(BuildEmployeeTimesPanel(st, cardBg, subCardBg, cardBorder, textPrimary, textMuted, goldBrush, emeraldBrush, roseBrush));
            }
            else if (_employeeCockpitSubTab == "payslips")
            {
                rootStack.Children.Add(BuildEmployeePayslipsPanel(st, cardBg, subCardBg, cardBorder, textPrimary, textMuted, goldBrush, emeraldBrush));
            }
            else if (_employeeCockpitSubTab == "loss" && st.CanAccessInventory)
            {
                rootStack.Children.Add(BuildEmployeeInventoryLossPanel(st, cardBg, subCardBg, cardBorder, textPrimary, textMuted, goldBrush));
            }

            EmployeeCockpitHostBorder.Child = rootStack;
        }

        private UIElement BuildEmployeeTimesPanel(
            EmployeeCockpitState st,
            Brush cardBg,
            Brush subCardBg,
            Brush cardBorder,
            Brush textPrimary,
            Brush textMuted,
            Brush goldBrush,
            Brush emeraldBrush,
            Brush roseBrush)
        {
            var container = new StackPanel();

            // Forms Grid: Left = Arbeitszeit nachtragen | Right = Urlaub / Krank melden
            var formsGrid = new Grid { Margin = new Thickness(0, 0, 0, 14) };
            formsGrid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
            formsGrid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(14) });
            formsGrid.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });

            // Left Card: Arbeitszeit nachtragen
            var workCard = new Border
            {
                Background = cardBg,
                BorderBrush = cardBorder,
                BorderThickness = new Thickness(1),
                CornerRadius = new CornerRadius(14),
                Padding = new Thickness(16)
            };
            var workStack = new StackPanel();
            workStack.Children.Add(new TextBlock
            {
                Text = "Arbeitszeit nachtragen",
                Foreground = textPrimary,
                FontSize = 14,
                FontWeight = FontWeights.Bold,
                Margin = new Thickness(0, 0, 0, 10)
            });

            var dateBox = CreateStyledCockpitInput(DateTime.Now.ToString("yyyy-MM-dd"), "Datum (JJJJ-MM-TT)", subCardBg, cardBorder, textPrimary);
            var timeRow = new Grid { Margin = new Thickness(0, 8, 0, 8) };
            timeRow.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
            timeRow.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(8) });
            timeRow.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
            timeRow.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(8) });
            timeRow.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(90) });

            var startBox = CreateStyledCockpitInput("09:00", "Start (HH:MM)", subCardBg, cardBorder, textPrimary);
            var endBox = CreateStyledCockpitInput("15:00", "Ende (HH:MM)", subCardBg, cardBorder, textPrimary);
            var breakBox = CreateStyledCockpitInput("30", "Pause (Min.)", subCardBg, cardBorder, textPrimary);
            Grid.SetColumn(startBox, 0);
            Grid.SetColumn(endBox, 2);
            Grid.SetColumn(breakBox, 4);
            timeRow.Children.Add(startBox);
            timeRow.Children.Add(endBox);
            timeRow.Children.Add(breakBox);

            var noteBox = CreateStyledCockpitInput("Manufaktur & Versand", "Tätigkeit", subCardBg, cardBorder, textPrimary);
            var saveWorkBtn = CreateCockpitActionButton("+ Arbeitszeit speichern", goldBrush, goldBrush, BrushFromHex("#111827"));
            saveWorkBtn.Margin = new Thickness(0, 10, 0, 0);
            saveWorkBtn.HorizontalAlignment = HorizontalAlignment.Right;
            saveWorkBtn.Click += async (_, _) =>
            {
                int.TryParse(breakBox.Text.Trim(), out int brk);
                await PostEmployeeActionAsync(HttpMethod.Post, "/employee/time-entries", new
                {
                    work_date = dateBox.Text.Trim(),
                    start_time = startBox.Text.Trim(),
                    end_time = endBox.Text.Trim(),
                    break_minutes = Math.Clamp(brk, 0, 240),
                    activity_note = noteBox.Text.Trim()
                });
            };

            workStack.Children.Add(dateBox);
            workStack.Children.Add(timeRow);
            workStack.Children.Add(noteBox);
            workStack.Children.Add(saveWorkBtn);
            workCard.Child = workStack;
            Grid.SetColumn(workCard, 0);
            formsGrid.Children.Add(workCard);

            // Right Card: Urlaub / Krank melden
            var leaveCard = new Border
            {
                Background = cardBg,
                BorderBrush = cardBorder,
                BorderThickness = new Thickness(1),
                CornerRadius = new CornerRadius(14),
                Padding = new Thickness(16)
            };
            var leaveStack = new StackPanel();
            leaveStack.Children.Add(new TextBlock
            {
                Text = "Urlaub / Krank melden",
                Foreground = textPrimary,
                FontSize = 14,
                FontWeight = FontWeights.Bold,
                Margin = new Thickness(0, 0, 0, 10)
            });

            var typeCombo = new ComboBox
            {
                Padding = new Thickness(10, 6, 10, 6),
                FontSize = 12.5,
                Margin = new Thickness(0, 0, 0, 8)
            };
            typeCombo.Items.Add(new ComboBoxItem { Content = "Urlaub", Tag = "vacation" });
            typeCombo.Items.Add(new ComboBoxItem { Content = "Krankmeldung", Tag = "sick" });
            typeCombo.SelectedIndex = 0;

            var rangeRow = new Grid { Margin = new Thickness(0, 0, 0, 8) };
            rangeRow.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
            rangeRow.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(8) });
            rangeRow.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });

            string todayIso = DateTime.Now.ToString("yyyy-MM-dd");
            var leaveStartBox = CreateStyledCockpitInput(todayIso, "Von (JJJJ-MM-TT)", subCardBg, cardBorder, textPrimary);
            var leaveEndBox = CreateStyledCockpitInput(todayIso, "Bis (JJJJ-MM-TT)", subCardBg, cardBorder, textPrimary);
            Grid.SetColumn(leaveStartBox, 0);
            Grid.SetColumn(leaveEndBox, 2);
            rangeRow.Children.Add(leaveStartBox);
            rangeRow.Children.Add(leaveEndBox);

            var leaveNoteBox = CreateStyledCockpitInput("", "Notiz (optional)", subCardBg, cardBorder, textPrimary);
            var saveLeaveBtn = CreateCockpitActionButton("+ Eintragen", goldBrush, goldBrush, BrushFromHex("#111827"));
            saveLeaveBtn.Margin = new Thickness(0, 10, 0, 0);
            saveLeaveBtn.HorizontalAlignment = HorizontalAlignment.Right;
            saveLeaveBtn.Click += async (_, _) =>
            {
                string lType = (typeCombo.SelectedItem as ComboBoxItem)?.Tag as string ?? "vacation";
                await PostEmployeeActionAsync(HttpMethod.Post, "/employee/leave-entries", new
                {
                    entry_type = lType,
                    start_date = leaveStartBox.Text.Trim(),
                    end_date = leaveEndBox.Text.Trim(),
                    activity_note = leaveNoteBox.Text.Trim()
                });
            };

            leaveStack.Children.Add(typeCombo);
            leaveStack.Children.Add(rangeRow);
            leaveStack.Children.Add(leaveNoteBox);
            leaveStack.Children.Add(saveLeaveBtn);
            leaveCard.Child = leaveStack;
            Grid.SetColumn(leaveCard, 2);
            formsGrid.Children.Add(leaveCard);

            container.Children.Add(formsGrid);

            // Month Entries List Card
            var listCard = new Border
            {
                Background = cardBg,
                BorderBrush = cardBorder,
                BorderThickness = new Thickness(1),
                CornerRadius = new CornerRadius(14),
                Padding = new Thickness(16)
            };
            var listStack = new StackPanel();

            var monthHeader = new Grid { Margin = new Thickness(0, 0, 0, 10) };
            monthHeader.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
            monthHeader.ColumnDefinitions.Add(new ColumnDefinition { Width = GridLength.Auto });

            string monthTitle = new DateTime(_empSelectedYear, Math.Clamp(_empSelectedMonth, 1, 12), 1)
                .ToString("MMMM yyyy", new CultureInfo("de-DE"));
            monthHeader.Children.Add(new TextBlock
            {
                Text = $"Zeiteinträge · {monthTitle}",
                Foreground = textPrimary,
                FontSize = 14,
                FontWeight = FontWeights.Bold,
                VerticalAlignment = VerticalAlignment.Center
            });

            var monthNav = new StackPanel { Orientation = Orientation.Horizontal };
            var prevBtn = CreateCockpitActionButton("◀", subCardBg, cardBorder, textPrimary);
            prevBtn.Margin = new Thickness(0, 0, 6, 0);
            prevBtn.Click += async (_, _) =>
            {
                var dt = new DateTime(_empSelectedYear, Math.Clamp(_empSelectedMonth, 1, 12), 1).AddMonths(-1);
                _empSelectedYear = dt.Year;
                _empSelectedMonth = dt.Month;
                await LoadEmployeeCockpitAsync(silent: false);
            };
            var nextBtn = CreateCockpitActionButton("▶", subCardBg, cardBorder, textPrimary);
            nextBtn.Click += async (_, _) =>
            {
                var dt = new DateTime(_empSelectedYear, Math.Clamp(_empSelectedMonth, 1, 12), 1).AddMonths(1);
                _empSelectedYear = dt.Year;
                _empSelectedMonth = dt.Month;
                await LoadEmployeeCockpitAsync(silent: false);
            };
            monthNav.Children.Add(prevBtn);
            monthNav.Children.Add(nextBtn);
            Grid.SetColumn(monthNav, 1);
            monthHeader.Children.Add(monthNav);
            listStack.Children.Add(monthHeader);

            if (st.TimeEntries.Count == 0)
            {
                listStack.Children.Add(new TextBlock
                {
                    Text = "Keine Einträge in diesem Monat.",
                    Foreground = textMuted,
                    FontSize = 12.5,
                    Margin = new Thickness(0, 6, 0, 4)
                });
            }
            else
            {
                foreach (var entry in st.TimeEntries)
                {
                    var rowBorder = new Border
                    {
                        Background = subCardBg,
                        BorderBrush = cardBorder,
                        BorderThickness = new Thickness(1),
                        CornerRadius = new CornerRadius(10),
                        Padding = new Thickness(12, 9, 12, 9),
                        Margin = new Thickness(0, 0, 0, 6)
                    };
                    var rg = new Grid();
                    rg.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(95) });
                    rg.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(120) });
                    rg.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
                    rg.ColumnDefinitions.Add(new ColumnDefinition { Width = GridLength.Auto });
                    rg.ColumnDefinitions.Add(new ColumnDefinition { Width = GridLength.Auto });

                    rg.Children.Add(new TextBlock
                    {
                        Text = entry.WorkDateFormatted,
                        Foreground = textPrimary,
                        FontSize = 12.5,
                        FontWeight = FontWeights.SemiBold,
                        VerticalAlignment = VerticalAlignment.Center
                    });

                    string spanLabel = entry.EntryType switch
                    {
                        "vacation" => "Urlaub",
                        "sick" => "Krank",
                        _ => (!string.IsNullOrEmpty(entry.StartedAtTime) ? $"{entry.StartedAtTime} - {entry.EndedAtTime}" : "Schicht")
                    };
                    var spanTxt = new TextBlock
                    {
                        Text = spanLabel,
                        Foreground = textMuted,
                        FontSize = 12,
                        VerticalAlignment = VerticalAlignment.Center
                    };
                    Grid.SetColumn(spanTxt, 1);
                    rg.Children.Add(spanTxt);

                    var noteTxt = new TextBlock
                    {
                        Text = entry.ActivityNote,
                        Foreground = textPrimary,
                        FontSize = 12,
                        TextTrimming = TextTrimming.CharacterEllipsis,
                        VerticalAlignment = VerticalAlignment.Center,
                        Margin = new Thickness(0, 0, 10, 0)
                    };
                    Grid.SetColumn(noteTxt, 2);
                    rg.Children.Add(noteTxt);

                    var hrsTxt = new TextBlock
                    {
                        Text = entry.FormattedHours,
                        Foreground = entry.Status == "approved" ? emeraldBrush : goldBrush,
                        FontSize = 12.5,
                        FontWeight = FontWeights.Bold,
                        VerticalAlignment = VerticalAlignment.Center,
                        Margin = new Thickness(0, 0, 10, 0)
                    };
                    Grid.SetColumn(hrsTxt, 3);
                    rg.Children.Add(hrsTxt);

                    if (entry.CanDelete)
                    {
                        string entryId = entry.Id;
                        var delBtn = new Button
                        {
                            Content = "✕",
                            Background = Brushes.Transparent,
                            BorderThickness = new Thickness(0),
                            Foreground = roseBrush,
                            FontWeight = FontWeights.Bold,
                            Cursor = Cursors.Hand,
                            ToolTip = "Eintrag löschen",
                            Padding = new Thickness(6, 2, 6, 2)
                        };
                        delBtn.Click += async (_, _) => await PostEmployeeActionAsync(HttpMethod.Delete, $"/employee/time-entries/{entryId}");
                        Grid.SetColumn(delBtn, 4);
                        rg.Children.Add(delBtn);
                    }

                    rowBorder.Child = rg;
                    listStack.Children.Add(rowBorder);
                }
            }

            listCard.Child = listStack;
            container.Children.Add(listCard);
            return container;
        }

        private UIElement BuildEmployeePayslipsPanel(
            EmployeeCockpitState st,
            Brush cardBg,
            Brush subCardBg,
            Brush cardBorder,
            Brush textPrimary,
            Brush textMuted,
            Brush goldBrush,
            Brush emeraldBrush)
        {
            var card = new Border
            {
                Background = cardBg,
                BorderBrush = cardBorder,
                BorderThickness = new Thickness(1),
                CornerRadius = new CornerRadius(14),
                Padding = new Thickness(16)
            };
            var stack = new StackPanel();
            stack.Children.Add(new TextBlock
            {
                Text = "Meine Lohnabrechnungen",
                Foreground = textPrimary,
                FontSize = 14,
                FontWeight = FontWeights.Bold,
                Margin = new Thickness(0, 0, 0, 10)
            });

            if (st.Payslips.Count == 0)
            {
                stack.Children.Add(new TextBlock
                {
                    Text = "Noch keine Lohnabrechnungen hinterlegt.",
                    Foreground = textMuted,
                    FontSize = 12.5
                });
            }
            else
            {
                foreach (var slip in st.Payslips)
                {
                    var row = new Border
                    {
                        Background = subCardBg,
                        BorderBrush = cardBorder,
                        BorderThickness = new Thickness(1),
                        CornerRadius = new CornerRadius(10),
                        Padding = new Thickness(14, 10, 14, 10),
                        Margin = new Thickness(0, 0, 0, 8)
                    };
                    var g = new Grid();
                    g.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
                    g.ColumnDefinitions.Add(new ColumnDefinition { Width = GridLength.Auto });
                    g.ColumnDefinitions.Add(new ColumnDefinition { Width = GridLength.Auto });

                    var info = new StackPanel { VerticalAlignment = VerticalAlignment.Center };
                    info.Children.Add(new TextBlock
                    {
                        Text = slip.PeriodLabel,
                        Foreground = textPrimary,
                        FontSize = 13,
                        FontWeight = FontWeights.Bold
                    });
                    info.Children.Add(new TextBlock
                    {
                        Text = slip.PayslipNumber,
                        Foreground = textMuted,
                        FontSize = 11.5
                    });
                    g.Children.Add(info);

                    var netTxt = new TextBlock
                    {
                        Text = slip.NetPayoutFormatted,
                        Foreground = emeraldBrush,
                        FontSize = 13.5,
                        FontWeight = FontWeights.Bold,
                        VerticalAlignment = VerticalAlignment.Center,
                        Margin = new Thickness(0, 0, 14, 0)
                    };
                    Grid.SetColumn(netTxt, 1);
                    g.Children.Add(netTxt);

                    string slipId = slip.Id;
                    string slipNo = string.IsNullOrWhiteSpace(slip.PayslipNumber) ? "Lohnabrechnung" : slip.PayslipNumber;
                    var pdfBtn = CreateCockpitActionButton("⬇ PDF öffnen", goldBrush, goldBrush, BrushFromHex("#111827"));
                    pdfBtn.Click += async (_, _) => await DownloadAndOpenEmployeePdfAsync($"/employee/documents/payslip/{slipId}", $"{slipNo}.pdf");
                    Grid.SetColumn(pdfBtn, 2);
                    g.Children.Add(pdfBtn);

                    row.Child = g;
                    stack.Children.Add(row);
                }
            }

            card.Child = stack;
            return card;
        }

        private UIElement BuildEmployeeInventoryLossPanel(
            EmployeeCockpitState st,
            Brush cardBg,
            Brush subCardBg,
            Brush cardBorder,
            Brush textPrimary,
            Brush textMuted,
            Brush goldBrush)
        {
            var card = new Border
            {
                Background = cardBg,
                BorderBrush = cardBorder,
                BorderThickness = new Thickness(1),
                CornerRadius = new CornerRadius(14),
                Padding = new Thickness(16)
            };
            var stack = new StackPanel();
            stack.Children.Add(new TextBlock
            {
                Text = "Bruch / Ausschuss melden",
                Foreground = textPrimary,
                FontSize = 14,
                FontWeight = FontWeights.Bold,
                Margin = new Thickness(0, 0, 0, 10)
            });

            var prodCombo = new ComboBox
            {
                Padding = new Thickness(10, 6, 10, 6),
                FontSize = 12.5,
                Margin = new Thickness(0, 0, 0, 8)
            };
            foreach (var p in st.PhysicalProducts)
            {
                prodCombo.Items.Add(new ComboBoxItem { Content = $"{p.Name} (Bestand: {p.Quantity})", Tag = p.Id });
            }
            if (prodCombo.Items.Count > 0) prodCombo.SelectedIndex = 0;

            var qtyBox = CreateStyledCockpitInput("1", "Menge", subCardBg, cardBorder, textPrimary);
            qtyBox.Margin = new Thickness(0, 0, 0, 8);
            var reasonBox = CreateStyledCockpitInput("", "Grund (z. B. Laser-Fehlgravur)", subCardBg, cardBorder, textPrimary);

            var submitBtn = CreateCockpitActionButton("Bruch buchen", goldBrush, goldBrush, BrushFromHex("#111827"));
            submitBtn.HorizontalAlignment = HorizontalAlignment.Right;
            submitBtn.Margin = new Thickness(0, 10, 0, 0);
            submitBtn.Click += async (_, _) =>
            {
                string? pid = (prodCombo.SelectedItem as ComboBoxItem)?.Tag as string;
                if (string.IsNullOrWhiteSpace(pid)) return;
                int.TryParse(qtyBox.Text.Trim(), out int qty);
                await PostEmployeeActionAsync(HttpMethod.Post, "/employee/inventory/loss", new
                {
                    product_id = pid,
                    quantity = Math.Max(1, qty),
                    reason = reasonBox.Text.Trim()
                });
            };

            stack.Children.Add(prodCombo);
            stack.Children.Add(qtyBox);
            stack.Children.Add(reasonBox);
            stack.Children.Add(submitBtn);
            card.Child = stack;
            return card;
        }

        private static Button CreateCockpitActionButton(string label, Brush bg, Brush border, Brush fg)
        {
            var btn = new Button
            {
                Content = label,
                Background = bg,
                BorderBrush = border,
                BorderThickness = new Thickness(1),
                Foreground = fg,
                Padding = new Thickness(12, 6, 12, 6),
                FontSize = 12,
                FontWeight = FontWeights.Bold,
                Cursor = Cursors.Hand
            };
            var style = new Style(typeof(Border));
            style.Setters.Add(new Setter(Border.CornerRadiusProperty, new CornerRadius(9)));
            btn.Resources.Add(typeof(Border), style);
            return btn;
        }

        private static TextBox CreateStyledCockpitInput(string initialText, string tooltip, Brush bg, Brush border, Brush fg)
        {
            return new TextBox
            {
                Text = initialText,
                ToolTip = tooltip,
                Background = bg,
                BorderBrush = border,
                BorderThickness = new Thickness(1),
                Foreground = fg,
                CaretBrush = new SolidColorBrush((Color)ColorConverter.ConvertFromString("#C5A059")),
                Padding = new Thickness(10, 6, 10, 6),
                FontSize = 12.5,
                VerticalContentAlignment = VerticalAlignment.Center
            };
        }

        private static Border CreateKpiPill(
            string label,
            string mainVal,
            string subVal,
            Brush bg,
            Brush border,
            Brush textPrimary,
            Brush textMuted,
            Brush accent,
            Thickness margin,
            Action? onClick)
        {
            var pill = new Border
            {
                Background = bg,
                BorderBrush = border,
                BorderThickness = new Thickness(1),
                CornerRadius = new CornerRadius(12),
                Padding = new Thickness(14, 11, 14, 11),
                Margin = margin,
                Cursor = onClick != null ? Cursors.Hand : Cursors.Arrow
            };
            var st = new StackPanel();
            st.Children.Add(new TextBlock
            {
                Text = label,
                Foreground = textMuted,
                FontSize = 10.5,
                FontWeight = FontWeights.Bold
            });
            st.Children.Add(new TextBlock
            {
                Text = mainVal,
                Foreground = accent,
                FontSize = 17,
                FontWeight = FontWeights.Bold,
                Margin = new Thickness(0, 3, 0, 2)
            });
            st.Children.Add(new TextBlock
            {
                Text = subVal,
                Foreground = textMuted,
                FontSize = 11
            });
            pill.Child = st;
            if (onClick != null)
            {
                pill.MouseLeftButtonUp += (_, _) => onClick();
            }
            return pill;
        }

        private static Border CreateQuickNavCard(
            string title,
            string metric,
            string cta,
            Brush bg,
            Brush border,
            Brush textPrimary,
            Brush textMuted,
            Brush gold,
            Thickness margin,
            Action onClick)
        {
            var card = new Border
            {
                Background = bg,
                BorderBrush = border,
                BorderThickness = new Thickness(1),
                CornerRadius = new CornerRadius(14),
                Padding = new Thickness(16, 14, 16, 14),
                Margin = margin,
                Cursor = Cursors.Hand
            };
            var g = new Grid();
            g.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
            g.ColumnDefinitions.Add(new ColumnDefinition { Width = GridLength.Auto });

            var st = new StackPanel { VerticalAlignment = VerticalAlignment.Center };
            st.Children.Add(new TextBlock
            {
                Text = title,
                Foreground = textPrimary,
                FontSize = 14,
                FontWeight = FontWeights.Bold
            });
            st.Children.Add(new TextBlock
            {
                Text = metric,
                Foreground = textMuted,
                FontSize = 12,
                Margin = new Thickness(0, 3, 0, 0)
            });
            g.Children.Add(st);

            var ctaTxt = new TextBlock
            {
                Text = cta,
                Foreground = gold,
                FontSize = 12.5,
                FontWeight = FontWeights.Bold,
                VerticalAlignment = VerticalAlignment.Center
            };
            Grid.SetColumn(ctaTxt, 1);
            g.Children.Add(ctaTxt);

            card.Child = g;
            card.MouseLeftButtonUp += (_, _) => onClick();
            return card;
        }
    }
}
