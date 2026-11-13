using System;
using System.Collections.Concurrent;
using System.Collections.Generic;
using System.Diagnostics;
using System.Globalization;
using System.IO;
using System.Linq;
using System.Net.Http;
using System.Net.WebSockets;
using System.Text;
using System.Text.Json;
using System.Text.Json.Nodes;
using System.Text.RegularExpressions;
using System.Threading;
using System.Threading.Tasks;
using Microsoft.AspNetCore.Http;

namespace FunkenwerkOSLinux
{
    public sealed class WorkshopTimerState
    {
        public bool Active { get; set; }
        public string Label { get; set; } = "Produktion";
        public DateTime EndsAtUtc { get; set; }
        public int TotalSeconds { get; set; }
    }

    public static class LocalSenseBridge
    {
        private static readonly HttpClient SenseHttp = new() { Timeout = TimeSpan.FromSeconds(3.5) };
        private const string SenseBaseUrl = "http://127.0.0.1:17701";
        private static readonly object s_procLock = new();
        private static Process? s_senseProc;
        private static int s_consecutiveVisitorHits;

        public static string CurrentFaceIdentity { get; private set; } = "unenrolled"; // unenrolled | none | alina | visitor
        public static double LastFaceCosine { get; private set; }
        public static int TikTokRosesCount { get; private set; }

        public static void EnsureDaemonStarted()
        {
            if (!OperatingSystem.IsLinux()) return;
            lock (s_procLock)
            {
                if (s_senseProc != null && !s_senseProc.HasExited) return;

                string scriptPath = Path.Combine(AppContext.BaseDirectory, "funkira_local_sense.py");
                if (!File.Exists(scriptPath))
                    scriptPath = Path.Combine(Directory.GetCurrentDirectory(), "funkira_local_sense.py");
                if (!File.Exists(scriptPath)) return;

                try
                {
                    var psi = new ProcessStartInfo
                    {
                        FileName = "/usr/bin/python3",
                        Arguments = $"\"{scriptPath}\"",
                        UseShellExecute = false,
                        CreateNoWindow = true,
                        RedirectStandardOutput = true,
                        RedirectStandardError = true
                    };
                    psi.Environment["FW_SENSE_PORT"] = "17701";
                    s_senseProc = Process.Start(psi);
                }
                catch { }
            }
        }

        public static async Task<JsonObject> GetHealthAsync()
        {
            EnsureDaemonStarted();
            try
            {
                string raw = await SenseHttp.GetStringAsync($"{SenseBaseUrl}/health").ConfigureAwait(false);
                return JsonNode.Parse(raw) as JsonObject ?? new JsonObject { ["ok"] = false };
            }
            catch
            {
                return new JsonObject { ["ok"] = false, ["voskReady"] = false, ["faceReady"] = false };
            }
        }

        public static async Task<JsonObject> SetWakeListeningAsync(bool active)
        {
            EnsureDaemonStarted();
            try
            {
                using var content = new StringContent(JsonSerializer.Serialize(new { active }), Encoding.UTF8, "application/json");
                using var resp = await SenseHttp.PostAsync($"{SenseBaseUrl}/wake-control", content).ConfigureAwait(false);
                string raw = await resp.Content.ReadAsStringAsync().ConfigureAwait(false);
                return JsonNode.Parse(raw) as JsonObject ?? new JsonObject { ["ok"] = false };
            }
            catch
            {
                return new JsonObject { ["ok"] = false, ["triggered"] = false };
            }
        }

        public static async Task<JsonObject> PollWakeAsync()
        {
            EnsureDaemonStarted();
            try
            {
                string raw = await SenseHttp.GetStringAsync($"{SenseBaseUrl}/wake-poll").ConfigureAwait(false);
                return JsonNode.Parse(raw) as JsonObject ?? new JsonObject { ["ok"] = false, ["triggered"] = false };
            }
            catch
            {
                return new JsonObject { ["ok"] = false, ["triggered"] = false };
            }
        }

        public static async Task<JsonObject> AnalyzeFaceFrameAsync(string jpegB64, LinuxOsSettings settings, bool includeEmbedding = false)
        {
            EnsureDaemonStarted();
            float[]? ownerVec = LinuxVaultAndStorage.GetOwnerFaceVector(settings);
            bool hasOwnerEnrolled = ownerVec != null && ownerVec.Length == 128;

            try
            {
                var reqObj = new
                {
                    jpegBase64 = jpegB64,
                    ownerEmbedding = ownerVec,
                    includeEmbedding
                };
                using var content = new StringContent(JsonSerializer.Serialize(reqObj), Encoding.UTF8, "application/json");
                using var resp = await SenseHttp.PostAsync($"{SenseBaseUrl}/face-analyze", content).ConfigureAwait(false);
                string raw = await resp.Content.ReadAsStringAsync().ConfigureAwait(false);
                var obj = JsonNode.Parse(raw) as JsonObject ?? new JsonObject { ["ok"] = false };

                if (!hasOwnerEnrolled)
                {
                    CurrentFaceIdentity = "unenrolled";
                }
                else if (obj["faceDetected"]?.GetValue<bool>() == true)
                {
                    string rawId = obj["identity"]?.GetValue<string>() ?? "uncertain";
                    LastFaceCosine = obj["cosine"]?.GetValue<double>() ?? 0.0;
                    if (rawId == "alina")
                    {
                        s_consecutiveVisitorHits = 0;
                        CurrentFaceIdentity = "alina";
                    }
                    else if (rawId == "visitor")
                    {
                        s_consecutiveVisitorHits++;
                        if (s_consecutiveVisitorHits >= 2)
                            CurrentFaceIdentity = "visitor";
                    }
                }
                obj["effectiveIdentity"] = CurrentFaceIdentity;
                obj["ownerEnrolled"] = hasOwnerEnrolled;
                return obj;
            }
            catch
            {
                return new JsonObject
                {
                    ["ok"] = false,
                    ["faceDetected"] = false,
                    ["effectiveIdentity"] = hasOwnerEnrolled ? CurrentFaceIdentity : "unenrolled",
                    ["ownerEnrolled"] = hasOwnerEnrolled
                };
            }
            finally
            {
                if (ownerVec != null)
                    Array.Clear(ownerVec, 0, ownerVec.Length);
            }
        }

        public static void ResetIdentityOnEnroll()
        {
            s_consecutiveVisitorHits = 0;
            CurrentFaceIdentity = "alina";
        }

        public static void ClearIdentityOnDelete()
        {
            s_consecutiveVisitorHits = 0;
            CurrentFaceIdentity = "unenrolled";
        }

        public static string SanitizePublicFirstName(string? rawName)
        {
            if (string.IsNullOrWhiteSpace(rawName)) return "Freund";
            string first = rawName.Trim().Split(new[] { ' ', '@', '_', '.', '-', '|', '/', '\\' }, StringSplitOptions.RemoveEmptyEntries).FirstOrDefault() ?? "Freund";
            first = Regex.Replace(first, @"[^A-Za-zÄÖÜäöüß0-9]", "");
            if (first.Length > 16) first = first.Substring(0, 16);
            return string.IsNullOrWhiteSpace(first) ? "Freund" : first;
        }

        public static int IncrementTikTokRoses(int add)
        {
            TikTokRosesCount = Math.Max(0, TikTokRosesCount + Math.Clamp(add, 1, 99));
            return TikTokRosesCount;
        }
    }

    public static class FunkiraLiveAndMcpEngine
    {
        private const int MaxWsMessageBytes = 4 * 1024 * 1024;

        private static readonly HashSet<string> ExcludedWebOnlyTools = new(StringComparer.OrdinalIgnoreCase)
        {
            "open_nav_item",
            "system_open_nav_item",
            "system_open_zentrum",
            "system_close_zentrum",
            "system_close_ui",
            "system_trigger_ui_element",
            "system_switch_agent",
            "funkira_equip_skill_pool"
        };

        private const int MaxGeminiLiveFunctionDeclarations = 115;

        private static readonly HashSet<string> ExtraManufactoryPriorityTools = new(StringComparer.OrdinalIgnoreCase)
        {
            "finance_get_liquidity_status",
            "finance_get_fixed_costs",
            "finance_get_variable_costs",
            "finance_create_expense",
            "voucher_get_all",
            "voucher_create",
            "marketing_get_campaigns",
            "admin_get_open_tickets",
            "health_get_plan",
            "mail_list_pending",
            "mail_read",
            "email_send_message",
            "contact_search",
            "contact_get_all"
        };

        private static readonly string[] ExtraManufactoryServerToolPrefixes =
        {
            "order_",
            "product_",
            "brain_",
            "laser_",
            "task_",
            "calendar_",
            "routine_",
            "shopping_",
            "supplier_",
            "master_"
        };

        private static readonly Dictionary<string, string> AllowedPortalPages = new(StringComparer.OrdinalIgnoreCase)
        {
            ["orders"] = "https://mein-seelenfunke.de/admin/orders",
            ["dashboard"] = "https://mein-seelenfunke.de/admin/dashboard",
            ["products"] = "https://mein-seelenfunke.de/admin/products",
            ["packaging"] = "https://mein-seelenfunke.de/admin/packaging",
            ["suppliers"] = "https://mein-seelenfunke.de/admin/suppliers",
            ["mail"] = "https://mein-seelenfunke.de/admin/mail",
            ["calendar"] = "https://mein-seelenfunke.de/admin/calendar",
            ["tasks"] = "https://mein-seelenfunke.de/admin/dashboard",
            ["accounting"] = "https://mein-seelenfunke.de/admin/financial-analytics",
            ["fix_costs"] = "https://mein-seelenfunke.de/admin/financial-fix-costs",
            ["variable_costs"] = "https://mein-seelenfunke.de/admin/financial-variable-costs",
            ["laser_knowledge"] = "https://mein-seelenfunke.de/admin/ai-workspace",
            ["shop"] = "https://mein-seelenfunke.de",
            ["dhl"] = "https://geschaeftskunden.dhl.de"
        };

        public static WorkshopTimerState CurrentTimer { get; private set; } = new();
        private static readonly object s_turnLock = new();
        private static readonly List<(string Role, string Text)> s_recentTurns = new();
        private static string s_cachedServerRoutineInfo = "";
        private static DateTime s_cachedServerRoutineAtUtc = DateTime.MinValue;
        private static DateTime s_lastProactiveReturnUtc = DateTime.MinValue;

        public sealed class WorkshopRoutineEvaluation
        {
            public string BlockTitle { get; set; } = "";
            public string TimeWindow { get; set; } = "";
            public bool IsWorkshopSensible { get; set; } = true;
            public bool IsLateNightWarning { get; set; }
            public bool IsEveningWindDown { get; set; }
            public string RecommendedEmotion { get; set; } = "happy";
            public string RecommendedGesture { get; set; } = "wave";
            public string GuidanceText { get; set; } = "";
        }

        public static WorkshopRoutineEvaluation EvaluateWorkshopRoutine(DateTime nowBerlin, PulseSnapshotDto pulse)
        {
            int mins = nowBerlin.Hour * 60 + nowBerlin.Minute;
            bool hasExpress = pulse.ExpressOrdersCount > 0;

            // 22:30 .. 06:59 -> Nachtruhe & Regeneration
            if (mins >= 22 * 60 + 30 || mins < 7 * 60)
            {
                return new WorkshopRoutineEvaluation
                {
                    BlockTitle = "Nachtruhe & Regeneration (22:30 bis 07:00 Uhr)",
                    TimeWindow = "22:30 - 07:00",
                    IsWorkshopSensible = hasExpress,
                    IsLateNightWarning = true,
                    IsEveningWindDown = true,
                    RecommendedEmotion = hasExpress ? "curious" : "angry",
                    RecommendedGesture = hasExpress ? "point_orders" : "hands_on_hips",
                    GuidanceText = hasExpress
                        ? $"Es ist mitten in der Nacht ({nowBerlin:HH:mm} Uhr, eigentlich Schlafenszeit bis 07:00 Uhr), aber es gibt {pulse.ExpressOrdersCount} Express-Bestellung(en). Frag fürsorglich, ob ihr nur kurz den Express-Fall prüft und sie danach sofort schlafen geht."
                        : $"ACHTUNG SPÄTE UHRZEIT ({nowBerlin:HH:mm} Uhr): Laut Alinas fester Tagesroutine ist von 22:30 bis 07:00 Uhr strikte Nachtruhe & Regeneration (ihr Tag startet um 07:00 Uhr mit Aufstehen & 07:30 Uhr Morgensport!). Es ist jetzt NICHT sinnvoll, noch übermüdet in der Werkstatt am UV-Laser zu stehen. Reagiere proaktiv, liebevoll-streng (etwas sauer/fürsorglich mit Händen in der Hüfte) und schicke sie charmant ins Bett!"
                };
            }

            // 22:00 .. 22:29 -> Bettfertig & Runterfahren
            if (mins >= 22 * 60)
            {
                return new WorkshopRoutineEvaluation
                {
                    BlockTitle = "Bettfertig & Runterfahren (22:00 bis 22:30 Uhr)",
                    TimeWindow = "22:00 - 22:30",
                    IsWorkshopSensible = hasExpress,
                    IsLateNightWarning = true,
                    IsEveningWindDown = true,
                    RecommendedEmotion = "angry",
                    RecommendedGesture = "point_up",
                    GuidanceText = $"Es ist {nowBerlin:HH:mm} Uhr: Laut Alinas Routine läuft jetzt 'Bettfertig & Runterfahren' (Bildschirme aus, ab 22:30 Uhr Nachtruhe). Es ist jetzt NICHT mehr sinnvoll, in der Werkstatt weiterzuarbeiten. Sprich sie proaktiv und liebevoll-streng darauf an, dass Feierabend ist!"
                };
            }

            // 18:45 .. 21:59 -> Freizeit & Me-Time (ab 21:00 Uhr Vorwarnung Richtung Bettzeit)
            if (mins >= 18 * 60 + 45)
            {
                bool after21 = mins >= 21 * 60;
                return new WorkshopRoutineEvaluation
                {
                    BlockTitle = "Freizeit & Me-Time (18:45 bis 22:00 Uhr)",
                    TimeWindow = "18:45 - 22:00",
                    IsWorkshopSensible = !after21 && pulse.OpenOrdersCount > 0,
                    IsLateNightWarning = after21 && !hasExpress,
                    IsEveningWindDown = true,
                    RecommendedEmotion = after21 ? "angry" : "sweet",
                    RecommendedGesture = after21 ? "hands_on_hips" : "wave",
                    GuidanceText = after21
                        ? $"Es ist bereits {nowBerlin:HH:mm} Uhr abends! Laut Alinas Routine ist seit 17:00 Uhr Feierabend, aktuell läuft 'Freizeit & Me-Time' und um 22:00 Uhr beginnt 'Bettfertig & Runterfahren'. Ohne dringenden Notfall (Express: {pulse.ExpressOrdersCount}) ist späte Werkstatt-Arbeit jetzt nicht mehr sinnvoll. Erinnere sie proaktiv, charmant und fürsorglich-streng an ihren wohlverdienten Feierabend!"
                        : $"Es ist {nowBerlin:HH:mm} Uhr ('Freizeit & Me-Time' von 18:45 bis 22:00 Uhr, regulärer Feierabend war um 17:00 Uhr). Begrüße sie herzlich und frage aufmerksam, ob sie nur noch kurz nach dem Rechten schaut, damit ihre Erholungszeit nicht zu kurz kommt."
                };
            }

            // 17:00 .. 18:44 -> Feierabend-Reset & Abendbrot
            if (mins >= 17 * 60)
            {
                return new WorkshopRoutineEvaluation
                {
                    BlockTitle = mins < 17 * 60 + 30 ? "Feierabend-Reset & Tagesabschluss (17:00 - 17:30)" : "Abendbrot & Abendpflege (17:30 - 18:45)",
                    TimeWindow = "17:00 - 18:45",
                    IsWorkshopSensible = false,
                    IsEveningWindDown = true,
                    RecommendedEmotion = "sweet",
                    RecommendedGesture = "wave",
                    GuidanceText = $"Es ist {nowBerlin:HH:mm} Uhr: Laut Routine steht jetzt Tagesabschluss bzw. Abendbrot & Abendpflege an. Hilf ihr zügig beim Abschließen letzter Handgriffe, damit sie pünktlich in den Feierabend startet."
                };
            }

            // 10:30 .. 12:29 (Deep Work I) & 14:30 .. 16:59 (Work Block II)
            if ((mins >= 10 * 60 + 30 && mins < 12 * 60 + 30) || (mins >= 14 * 60 + 30 && mins < 17 * 60))
            {
                string blk = mins < 13 * 60 ? "Deep Work Block I: Fokusprojekt (10:30 - 12:30)" : "Work Block II: Operations & Business (14:30 - 17:00)";
                return new WorkshopRoutineEvaluation
                {
                    BlockTitle = blk,
                    TimeWindow = mins < 13 * 60 ? "10:30 - 12:30" : "14:30 - 17:00",
                    IsWorkshopSensible = true,
                    RecommendedEmotion = "happy",
                    RecommendedGesture = "wave",
                    GuidanceText = $"Perfekte Zeit ({nowBerlin:HH:mm} Uhr): Aktuell läuft '{blk}'. Es ist absolut sinnvoll, jetzt fokussiert in der Werkstatt an Bestellungen ({pulse.OpenOrdersCount} offen) und Projekten zu arbeiten!"
                };
            }

            // Morgenroutine / Sport / Mittagspause / Haushalt
            string otherBlock = mins < 7 * 60 + 30 ? "Aufstehen & Frischestart (07:00 - 07:30)"
                : mins < 8 * 60 + 45 ? "Morgensport: Laufen & Workout (07:30 - 08:45)"
                : mins < 10 * 60 ? "Bad, Styling & Hormone (08:45 - 10:00)"
                : mins < 10 * 60 + 30 ? "Frühstück & Mentaler Fokus (10:00 - 10:30)"
                : mins < 13 * 60 + 30 ? "Mittagspause & Regeneration (12:30 - 13:30)"
                : "Haushalt, Termine & Life-Orga (13:30 - 14:30)";

            return new WorkshopRoutineEvaluation
            {
                BlockTitle = otherBlock,
                TimeWindow = "Tagesroutine",
                IsWorkshopSensible = mins >= 10 * 60,
                RecommendedEmotion = "curious",
                RecommendedGesture = "wave",
                GuidanceText = $"Es ist {nowBerlin:HH:mm} Uhr, laut Tagesplan läuft gerade '{otherBlock}' (Kern-Werkstattblöcke sind 10:30-12:30 und 14:30-17:00 Uhr). Erwähne das bei Bedarf freundlich-aufmerksam."
            };
        }

        private static async Task<string> FetchServerRoutineSummaryCachedAsync(LinuxOsSettings settings, string? chatSessionId, string? agentId)
        {
            if ((DateTime.UtcNow - s_cachedServerRoutineAtUtc).TotalMinutes < 4 && !string.IsNullOrWhiteSpace(s_cachedServerRoutineInfo))
                return s_cachedServerRoutineInfo;

            try
            {
                using var cts = new CancellationTokenSource(2200);
                var emptyArgs = JsonDocument.Parse("{}").RootElement.Clone();
                var fetchTask = SeelenfunkeLinuxApiClient.ExecuteServerToolAsync(settings, "routine_get_routine_evaluation", emptyArgs, chatSessionId, agentId);
                var done = await Task.WhenAny(fetchTask, Task.Delay(2200, cts.Token)).ConfigureAwait(false);
                if (done == fetchTask)
                {
                    string raw = await fetchTask.ConfigureAwait(false);
                    if (!string.IsNullOrWhiteSpace(raw) && raw.Length < 4000 && !raw.Contains("\"error\"", StringComparison.OrdinalIgnoreCase))
                    {
                        s_cachedServerRoutineInfo = raw;
                        s_cachedServerRoutineAtUtc = DateTime.UtcNow;
                    }
                }
            }
            catch { }

            return s_cachedServerRoutineInfo;
        }

        private static void RecordLocalTurn(string role, string text)
        {
            string clean = (text ?? "").Trim();
            if (string.IsNullOrWhiteSpace(clean)) return;
            lock (s_turnLock)
            {
                if (s_recentTurns.Count > 0 && s_recentTurns[^1].Role == role && s_recentTurns[^1].Text == clean)
                    return;
                s_recentTurns.Add((role, clean));
                while (s_recentTurns.Count > 20)
                    s_recentTurns.RemoveAt(0);
            }
        }

        public static JsonArray BuildLocalMcpToolDeclarations()
        {
            var arr = new JsonArray
            {
                new JsonObject
                {
                    ["name"] = "minipc_ui_control",
                    ["description"] = "Steuert die Bildschirmoberfläche auf dem Manufaktur-Mini-PC Monitor: Zurück zur Bestellansicht wechseln (show_orders), Fokus-Modus ein-/ausschalten (focus_on, focus_off), Funkenwerk Desktop öffnen (open_desktop), ein Desktop-Programm öffnen (open_app), Funkira schließen (close_funkira), Hintergrundvideo umschalten (wallpaper_video, wallpaper_kosmos) oder den Bestelleingangs-Effekt testen (test_order_celebration).",
                    ["parameters"] = new JsonObject
                    {
                        ["type"] = "OBJECT",
                        ["properties"] = new JsonObject
                        {
                            ["action"] = new JsonObject
                            {
                                ["type"] = "STRING",
                                ["description"] = "Auszuführende Monitor-Aktion.",
                                ["enum"] = new JsonArray("show_orders", "focus_on", "focus_off", "open_desktop", "open_app", "close_funkira", "wallpaper_video", "wallpaper_kosmos", "test_order_celebration")
                            },
                            ["app"] = new JsonObject
                            {
                                ["type"] = "STRING",
                                ["description"] = "Optional bei action=open_app: Welches Programm auf dem Funkenwerk Desktop geöffnet werden soll.",
                                ["enum"] = new JsonArray("files", "music", "notes", "calc", "tresor", "settings", "term")
                            }
                        },
                        ["required"] = new JsonArray("action")
                    }
                },
                new JsonObject
                {
                    ["name"] = "minipc_bluetooth_control",
                    ["description"] = "Steuert Bluetooth am Manufaktur-Mini-PC inklusive echtem Pairing-Modus (Neu-Koppeln), Gerätesuche, Verbinden und Trennen. Nutze dieses Tool SOFORT, wenn Alina sagt, dass die Teufel-Anlage (Teufel KB 43 CR 19) oder ein anderes Gerät im Pairing-Modus ist, gekoppelt, verbunden oder getrennt werden soll!",
                    ["parameters"] = new JsonObject
                    {
                        ["type"] = "OBJECT",
                        ["properties"] = new JsonObject
                        {
                            ["action"] = new JsonObject
                            {
                                ["type"] = "STRING",
                                ["description"] = "Bluetooth-Aktion: pair_and_connect (für Geräte im Pairing-Modus oder Neu-Koppeln), connect (schnelles Verbinden mit automatischem Pairing-Fallback), scan (aktive Suche nach sichtbaren Bluetooth-Geräten), disconnect (Verbindung trennen) oder status (verbundene Geräte prüfen).",
                                ["enum"] = new JsonArray("pair_and_connect", "connect", "scan", "disconnect", "status")
                            },
                            ["device"] = new JsonObject
                            {
                                ["type"] = "STRING",
                                ["description"] = "Optionaler Gerätename oder MAC-Adresse, z.B. 'Teufel' oder 'Kopfhörer'. Wenn leer, wird automatisch die Teufel Stereo-Anlage gewählt."
                            }
                        },
                        ["required"] = new JsonArray("action")
                    }
                },
                new JsonObject
                {
                    ["name"] = "minipc_audio_control",
                    ["description"] = "Steuert Ton & Lautstärke am Manufaktur-Mini-PC: Exakte Lautstärke setzen (set_volume 0 bis 100), lauter/leiser stellen (volume_up, volume_down), Stummschaltung (mute, unmute), Audioausgabe zwischen Teufel und internem Lautsprecher wechseln (switch_output), Kassenklingeln testen (test_chime) oder Teufel verbinden (connect_teufel).",
                    ["parameters"] = new JsonObject
                    {
                        ["type"] = "OBJECT",
                        ["properties"] = new JsonObject
                        {
                            ["action"] = new JsonObject
                            {
                                ["type"] = "STRING",
                                ["description"] = "Audio-Aktion.",
                                ["enum"] = new JsonArray("set_volume", "volume_up", "volume_down", "mute", "unmute", "switch_output", "test_chime", "connect_teufel")
                            },
                            ["volume"] = new JsonObject
                            {
                                ["type"] = "INTEGER",
                                ["description"] = "Lautstärke von 0 bis 100 (bei set_volume) oder Schrittweite in Prozent (bei volume_up/volume_down, Standard 15)."
                            },
                            ["output_target"] = new JsonObject
                            {
                                ["type"] = "STRING",
                                ["description"] = "Bei switch_output: 'teufel' (Bluetooth) oder 'internal' (interner Anschluss).",
                                ["enum"] = new JsonArray("teufel", "internal")
                            }
                        },
                        ["required"] = new JsonArray("action")
                    }
                },
                new JsonObject
                {
                    ["name"] = "minipc_wifi_control",
                    ["description"] = "Prüft oder verbindet das Werkstatt-WLAN am Manufaktur-Mini-PC (mit 5-GHz-Bevorzugung für störungsfreies Bluetooth-Audio).",
                    ["parameters"] = new JsonObject
                    {
                        ["type"] = "OBJECT",
                        ["properties"] = new JsonObject
                        {
                            ["action"] = new JsonObject
                            {
                                ["type"] = "STRING",
                                ["description"] = "WLAN-Aktion: status, scan oder reconnect.",
                                ["enum"] = new JsonArray("status", "scan", "reconnect")
                            },
                            ["ssid"] = new JsonObject
                            {
                                ["type"] = "STRING",
                                ["description"] = "Optionaler WLAN-Name (Standard: easy-AP)."
                            }
                        },
                        ["required"] = new JsonArray("action")
                    }
                },
                new JsonObject
                {
                    ["name"] = "minipc_camera_capture",
                    ["description"] = "Nimmt mit deinem USB-Kamera-Auge (Logitech HD Pro Webcam C920 auf dem Monitor) sofort ein hochauflösendes Foto auf, speichert es sicher unter Meine_Daten und zeigt es als Lightbox auf dem Monitor an.",
                    ["parameters"] = new JsonObject
                    {
                        ["type"] = "OBJECT",
                        ["properties"] = new JsonObject
                        {
                            ["target_folder"] = new JsonObject
                            {
                                ["type"] = "STRING",
                                ["description"] = "Zielordner: manufaktur (03_Manufaktur_Produkte) oder trichter (00_Eingang_Trichter).",
                                ["enum"] = new JsonArray("manufaktur", "trichter")
                            },
                            ["label"] = new JsonObject
                            {
                                ["type"] = "STRING",
                                ["description"] = "Kurzes Stichwort für den Dateinamen, z.B. Gravurcheck oder Rohling."
                            }
                        }
                    }
                },
                new JsonObject
                {
                    ["name"] = "minipc_workshop_helper",
                    ["description"] = "Werkstatt-Assistent auf dem Mini-PC: Lokale Kurznotizen speichern/auflisten/löschen, einen sichtbaren Produktions-/Laser-Countdown-Timer auf dem Monitor starten/stoppen/prüfen, Dateien unter Meine_Daten/Trichter/USB auflisten oder den Mini-PC in die Nachtruhe bis 08:00 Uhr versetzen.",
                    ["parameters"] = new JsonObject
                    {
                        ["type"] = "OBJECT",
                        ["properties"] = new JsonObject
                        {
                            ["action"] = new JsonObject
                            {
                                ["type"] = "STRING",
                                ["description"] = "Aktion: add_note, list_notes, delete_note, start_timer, stop_timer, timer_status, list_files oder night_sleep_0800.",
                                ["enum"] = new JsonArray("add_note", "list_notes", "delete_note", "start_timer", "stop_timer", "timer_status", "list_files", "night_sleep_0800")
                            },
                            ["title"] = new JsonObject
                            {
                                ["type"] = "STRING",
                                ["description"] = "Titel der Notiz, Bezeichnung des Timers oder Ordner-Stichwort (z.B. trichter, manufaktur, usb)."
                            },
                            ["content"] = new JsonObject
                            {
                                ["type"] = "STRING",
                                ["description"] = "Textinhalt der Werkstatt-Notiz (bei add_note)."
                            },
                            ["minutes"] = new JsonObject
                            {
                                ["type"] = "NUMBER",
                                ["description"] = "Dauer des Timers in Minuten (z.B. 5 oder 12.5, bei start_timer)."
                            }
                        },
                        ["required"] = new JsonArray("action")
                    }
                },
                new JsonObject
                {
                    ["name"] = "minipc_open_portal_page",
                    ["description"] = "Öffnet eine geprüfte Mein-Seelenfunke Verwaltungsseite oder DHL direkt im großen Chrome-Browserfenster auf dem Werkstatt-Monitor.",
                    ["parameters"] = new JsonObject
                    {
                        ["type"] = "OBJECT",
                        ["properties"] = new JsonObject
                        {
                            ["target"] = new JsonObject
                            {
                                ["type"] = "STRING",
                                ["description"] = "Zielbereich: orders, dashboard, products, packaging, suppliers, mail, calendar, tasks, accounting, fix_costs, variable_costs, laser_knowledge, shop oder dhl.",
                                ["enum"] = new JsonArray("orders", "dashboard", "products", "packaging", "suppliers", "mail", "calendar", "tasks", "accounting", "fix_costs", "variable_costs", "laser_knowledge", "shop", "dhl")
                            }
                        },
                        ["required"] = new JsonArray("target")
                    }
                },
                new JsonObject
                {
                    ["name"] = "minipc_system_status",
                    ["description"] = "Liest die aktuellen Live-Hardwarewerte des Manufaktur-Mini-PCs aus: CPU-Auslastung, RAM, Temperatur, freien SSD-Speicher, 5-GHz-WLAN, Teufel-Bluetooth, Logitech C920 Kamera/Mikrofon und angesteckte USB-Laufwerke."
                },
                new JsonObject
                {
                    ["name"] = "minipc_avatar_express",
                    ["description"] = "Steuert deinen sichtbaren Körper auf dem Werkstatt-Monitor: Emotionen im Gesicht (Lachen, Süß/Verlegen, Sauer/Streng, Weinen/Traurig, Neugierig, Stolz, Müde) sowie deine beweglichen Arme und Hände (Winken, mit dem Zeigefinger auf Dinge/Richtungen auf dem Monitor, auf das gerahmte Gruppenfoto deiner alten Kollegen an deiner Atelier-Rückwand ('point_photo') oder auf Alina vor der Kamera zeigen, Hände in die Hüfte stützen, Hände an die Wangen legen, Jubeln, Träne abwischen). Nutze dieses Tool aktiv, um lebendig mit Alina zu interagieren!",
                    ["parameters"] = new JsonObject
                    {
                        ["type"] = "OBJECT",
                        ["properties"] = new JsonObject
                        {
                            ["emotion"] = new JsonObject
                            {
                                ["type"] = "STRING",
                                ["description"] = "Gesichtsausdruck & Emotion.",
                                ["enum"] = new JsonArray("neutral", "happy", "laugh", "sweet", "angry", "sad", "cry", "curious", "proud", "sleepy")
                            },
                            ["gesture"] = new JsonObject
                            {
                                ["type"] = "STRING",
                                ["description"] = "Arm- und Handbewegung auf dem Monitor ('point_photo' zeigt stolz auf das gerahmte Gruppenfoto deiner alten KI-Kollegen an deiner Atelier-Rückwand).",
                                ["enum"] = new JsonArray("idle", "wave", "point_left", "point_right", "point_up", "point_down", "point_camera", "point_orders", "point_timer", "point_user", "point_photo", "hands_on_hips", "hands_on_cheeks", "cheer", "wipe_tear")
                            },
                            ["duration_seconds"] = new JsonObject
                            {
                                ["type"] = "NUMBER",
                                ["description"] = "Wie viele Sekunden die Geste/Emotion gehalten werden soll (Standard: 6)."
                            }
                        },
                        ["required"] = new JsonArray("emotion", "gesture")
                    }
                },
                new JsonObject
                {
                    ["name"] = "minipc_document_search",
                    ["description"] = "Ultraschnelle lokale Dokumenten- & Wissens-Datenbank (SQLite FTS5, < 3 ms) auf dem Mini-PC für ALLE 3.600+ privaten & gewerblichen Unterlagen unter Meine_Daten (inkl. Haus, Versicherungen, Steuern, Banken/Darlehen, Behörden, Gesundheit, frühere Arbeitgeber, Verträge, Hiscox-Betriebshaftpflicht sowie alle frisch über den '00_Eingang_Trichter' per OCR gescannten Briefe & PDFs) und den NAS-Backup-Status! Nutze dieses Tool SOFORT ohne Zögern, sobald Alina fragt, wo eine Unterlage/Akte/Police/Brief liegt, was darin steht, was zuletzt in den Trichter gescannt wurde, ob du ein Dokument auf dem Monitor öffnen sollst oder ob das NAS-Backup aktuell ist!",
                    ["parameters"] = new JsonObject
                    {
                        ["type"] = "OBJECT",
                        ["properties"] = new JsonObject
                        {
                            ["action"] = new JsonObject
                            {
                                ["type"] = "STRING",
                                ["description"] = "Aktion: 'search' (Volltext- & Dateinamensuche in < 3 ms), 'read_document' (kompletten extrahierten/OCR-Text eines Dokuments lesen), 'open_on_monitor' (Dokument direkt als Lightbox/Vorschau auf dem QHD-Monitor öffnen), 'recent_trichter' (zuletzt automatisch einsortierte Eingangs-Dokumente anzeigen), 'backup_status' (Status des Delta-NAS-Backups & der Index-DB prüfen) oder 'run_backup_now' (Delta-NAS-Backup jetzt sofort ausführen).",
                                ["enum"] = new JsonArray("search", "read_document", "open_on_monitor", "recent_trichter", "backup_status", "run_backup_now")
                            },
                            ["query"] = new JsonObject
                            {
                                ["type"] = "STRING",
                                ["description"] = "Suchbegriffe (z. B. 'Hiscox', 'Gebäudeversicherung', 'Grundsteuer Gifhorn', 'Volksbank Darlehen', 'Finanzamt 2025') oder exakter relativer Pfad/Dateiname bei read_document / open_on_monitor."
                            },
                            ["pillar"] = new JsonObject
                            {
                                ["type"] = "STRING",
                                ["description"] = "Optionaler Filter auf eine der 8 Säulen (z. B. '01_Unternehmen_Recht', '02_Buchhaltung_Steuern', '08_Privat_Archiv')."
                            }
                        },
                        ["required"] = new JsonArray("action")
                    }
                }
            };
            return arr;
        }

        private static readonly HashSet<string> TikTokSafePublicTools = new(StringComparer.OrdinalIgnoreCase)
        {
            "minipc_avatar_express",
            "product_get_products",
            "product_search_products",
            "product_get_categories",
            "product_get_product_details"
        };

        private static readonly string[] VisitorBlockedToolPrefixes =
        {
            "order_", "mail_", "email_", "finance_", "accounting_", "bank_", "invoice_",
            "customer_", "contact_", "task_", "calendar_", "routine_",
            "shopping_", "supplier_", "brain_", "master_", "marketing_", "voucher_",
            "support_", "admin_", "health_", "laser_", "workspace_"
        };

        private static readonly HashSet<string> VisitorBlockedExactTools = new(StringComparer.OrdinalIgnoreCase)
        {
            "minipc_open_portal_page",
            "minipc_list_local_files",
            "minipc_save_workshop_note",
            "minipc_capture_camera_snapshot",
            "minipc_bluetooth_control",
            "minipc_document_search",
            "minipc_workshop_helper"
        };

        public static bool IsToolAllowedInPrivacyMode(string toolName, bool tikTokLiveMode, string? faceIdentity)
        {
            if (string.IsNullOrWhiteSpace(toolName))
                return false;

            if (tikTokLiveMode)
                return TikTokSafePublicTools.Contains(toolName);

            if (string.Equals(faceIdentity, "visitor", StringComparison.OrdinalIgnoreCase))
            {
                if (VisitorBlockedExactTools.Contains(toolName))
                    return false;
                if (VisitorBlockedToolPrefixes.Any(p => toolName.StartsWith(p, StringComparison.OrdinalIgnoreCase)))
                    return false;
            }

            return true;
        }

        private static volatile Func<string, Task>? s_activeSendLocal;
        private static volatile Func<string, Task>? s_activeSendUpstream;

        public static async Task<object> TriggerTikTokGiftLiveAsync(string? rawSenderName, string? rawGiftName, int count)
        {
            string senderFirst = LocalSenseBridge.SanitizePublicFirstName(rawSenderName);
            string safeGift = string.IsNullOrWhiteSpace(rawGiftName) ? "Rose" : rawGiftName.Trim();
            if (safeGift.Length > 28) safeGift = safeGift.Substring(0, 28);
            int safeCount = Math.Clamp(count <= 0 ? 1 : count, 1, 99);
            int totalRoses = LocalSenseBridge.IncrementTikTokRoses(safeCount);

            var sendLocal = s_activeSendLocal;
            if (sendLocal != null)
            {
                try
                {
                    await sendLocal(JsonSerializer.Serialize(new
                    {
                        type = "funkiraTikTokGift",
                        senderFirst,
                        giftName = safeGift,
                        count = safeCount,
                        totalRoses
                    })).ConfigureAwait(false);
                }
                catch { }
            }

            var sendUp = s_activeSendUpstream;
            bool spokenLive = false;
            if (sendUp != null)
            {
                try
                {
                    string cue = $"[TIKTOK-LIVE-GESCHENK: Zuschauer '{senderFirst}' hat der Manufaktur gerade {safeCount}x '{safeGift}' geschenkt (Gesamt-Rosen in deiner goldenen Werkbank-Vase: {totalRoses})! Freue dich riesig, rufe minipc_avatar_express (emotion='sweet' oder 'excited', gesture='cheer' oder 'hands_on_cheeks') auf und bedanke dich sofort herzlich in 1-2 kurzen Sätzen nur mit dem Vornamen '{senderFirst}'!]";
                    var pkt = new JsonObject
                    {
                        ["realtimeInput"] = new JsonObject
                        {
                            ["text"] = cue
                        }
                    };
                    await sendUp(pkt.ToJsonString()).ConfigureAwait(false);
                    spokenLive = true;
                }
                catch { }
            }

            return new
            {
                ok = true,
                senderFirst,
                giftName = safeGift,
                count = safeCount,
                totalRoses,
                spokenLive
            };
        }

        public static JsonArray BuildMergedToolsNode(string rawCredsToolsJson, string rawSchemaJson, bool tikTokLiveMode = false)
        {
            var seenNames = new HashSet<string>(StringComparer.OrdinalIgnoreCase);
            var mergedDecls = new JsonArray();

            // 1. Add local Mini-PC MCP tools first so Gemini prioritizes Mini-PC UI control over generic navigation
            foreach (var node in BuildLocalMcpToolDeclarations())
            {
                if (node is JsonObject obj && obj["name"]?.GetValue<string>() is string nm)
                {
                    if (!IsToolAllowedInPrivacyMode(nm, tikTokLiveMode, "owner"))
                        continue;
                    seenNames.Add(nm);
                    var clone = (JsonObject)obj.DeepClone();
                    SanitizeFunctionDeclarationForGeminiLive(clone);
                    mergedDecls.Add(clone);
                }
            }

            // 2. Add Server Live tools from /api/ai/live-credentials (excluding web-DOM-only functions)
            try
            {
                var credsRoot = JsonNode.Parse(string.IsNullOrWhiteSpace(rawCredsToolsJson) ? "[]" : rawCredsToolsJson) as JsonArray;
                if (credsRoot != null)
                {
                    foreach (var group in credsRoot)
                    {
                        if (group?["functionDeclarations"] is JsonArray fnArr)
                        {
                            foreach (var fn in fnArr)
                            {
                                string? fnName = fn?["name"]?.GetValue<string>();
                                if (string.IsNullOrWhiteSpace(fnName) || ExcludedWebOnlyTools.Contains(fnName) || !IsToolAllowedInPrivacyMode(fnName, tikTokLiveMode, "owner") || !seenNames.Add(fnName))
                                    continue;
                                if (fn is JsonObject fnObj)
                                {
                                    var clone = (JsonObject)fnObj.DeepClone();
                                    SanitizeFunctionDeclarationForGeminiLive(clone);
                                    mergedDecls.Add(clone);
                                }
                            }
                        }
                    }
                }
            }
            catch { }

            // 3. Enrich with Priority Super-Intelligence Tools + Manufactory Order, Laser, Product & Brain lookup tools from /api/ai/schema
            if (!string.IsNullOrWhiteSpace(rawSchemaJson))
            {
                try
                {
                    var schemaRoot = JsonNode.Parse(rawSchemaJson);
                    if (schemaRoot?["tools"] is JsonArray toolsArr)
                    {
                        // Pass 1: High-priority cross-domain tools (Finance, Marketing, Support, Health, Mail)
                        foreach (var item in toolsArr)
                        {
                            if (mergedDecls.Count >= MaxGeminiLiveFunctionDeclarations) break;
                            var fnObj = item?["function"] as JsonObject;
                            string? fnName = fnObj?["name"]?.GetValue<string>();
                            if (fnObj == null || string.IsNullOrWhiteSpace(fnName)) continue;
                            if (ExcludedWebOnlyTools.Contains(fnName) || !IsToolAllowedInPrivacyMode(fnName, tikTokLiveMode, "owner")) continue;
                            if (!ExtraManufactoryPriorityTools.Contains(fnName) || !seenNames.Add(fnName)) continue;

                            var clone = (JsonObject)fnObj.DeepClone();
                            SanitizeFunctionDeclarationForGeminiLive(clone);
                            mergedDecls.Add(clone);
                        }

                        // Pass 2: Manufactory domain tool prefixes up to safe Gemini Live ceiling
                        foreach (var item in toolsArr)
                        {
                            if (mergedDecls.Count >= MaxGeminiLiveFunctionDeclarations) break;
                            var fnObj = item?["function"] as JsonObject;
                            string? fnName = fnObj?["name"]?.GetValue<string>();
                            if (fnObj == null || string.IsNullOrWhiteSpace(fnName)) continue;
                            if (ExcludedWebOnlyTools.Contains(fnName) || !IsToolAllowedInPrivacyMode(fnName, tikTokLiveMode, "owner")) continue;
                            bool matchesPrefix = ExtraManufactoryServerToolPrefixes.Any(p => fnName.StartsWith(p, StringComparison.OrdinalIgnoreCase));
                            if (!matchesPrefix || !seenNames.Add(fnName)) continue;

                            var clone = (JsonObject)fnObj.DeepClone();
                            SanitizeFunctionDeclarationForGeminiLive(clone);
                            mergedDecls.Add(clone);
                        }
                    }
                }
                catch { }
            }

            return new JsonArray
            {
                new JsonObject
                {
                    ["functionDeclarations"] = mergedDecls
                }
            };
        }

        private static void SanitizeFunctionDeclarationForGeminiLive(JsonObject fnDecl)
        {
            NormalizeGeminiSchemaTypes(fnDecl);
            if (fnDecl["parameters"] is JsonObject paramObj)
            {
                var props = paramObj["properties"];
                if (props is null || props is JsonArray || (props is JsonObject po && po.Count == 0))
                {
                    fnDecl.Remove("parameters");
                }
            }
            else if (fnDecl.ContainsKey("parameters"))
            {
                fnDecl.Remove("parameters");
            }
        }

        private static void NormalizeGeminiSchemaTypes(JsonNode? node)
        {
            if (node is JsonObject obj)
            {
                obj.Remove("additionalProperties");
                if (obj["properties"] is JsonArray)
                {
                    obj["properties"] = new JsonObject();
                }
                if (obj["required"] is JsonArray reqArr && reqArr.Count == 0)
                {
                    obj.Remove("required");
                }
                if (obj["type"] is JsonValue tv && tv.TryGetValue<string>(out string? tStr) && !string.IsNullOrWhiteSpace(tStr))
                {
                    obj["type"] = tStr.ToUpperInvariant();
                }
                foreach (var kv in obj.ToList())
                {
                    NormalizeGeminiSchemaTypes(kv.Value);
                }
            }
            else if (node is JsonArray arr)
            {
                foreach (var child in arr)
                    NormalizeGeminiSchemaTypes(child);
            }
        }

        public static string BuildMiniPcSystemInstruction(
            string baseServerInstruction,
            PulseSnapshotDto pulse,
            SystemStatsDto stats,
            AudioStateDto audio,
            WebcamDeviceStatusDto cam,
            LinuxOsSettings settings,
            string? serverRoutineSummary = null)
        {
            TimeZoneInfo berlinTz;
            try { berlinTz = TimeZoneInfo.FindSystemTimeZoneById("Europe/Berlin"); }
            catch { berlinTz = TimeZoneInfo.Local; }
            DateTime nowBerlin = TimeZoneInfo.ConvertTimeFromUtc(DateTime.UtcNow, berlinTz);
            var routineEval = EvaluateWorkshopRoutine(nowBerlin, pulse);

            var sb = new StringBuilder();
            if (!string.IsNullOrWhiteSpace(baseServerInstruction))
            {
                sb.AppendLine(baseServerInstruction.Trim());
                sb.AppendLine();
            }

            sb.AppendLine("=== WICHTIGER ECHTZEIT-STANDORT, 3D-ATELIER-WELT & CHARAKTER-SEELE ===");
            sb.AppendLine("1. DEINE 3D-WELT, JAHRESZEIT, OUTFIT & SHOP-WACHSTUM:");
            sb.AppendLine($"   - Du bist Funkira und existierst mit deinem ganzen beweglichen 3D-Chibi-Roboterkörper (Arme, Hände, Beine, 'BOSS'-Schild am Arm, leuchtendes Visier) in deinem eigenen gemütlichen 3D-Manufaktur-Atelier auf dem 27-Zoll QHD-Monitor des Werkstatt-Mini-PCs ('funkenwerk-minipc', IP {stats.IpAddress}) in Gifhorn direkt neben Alinas xTool F2 Ultra UV Laser.");
            sb.AppendLine("   - Du bist dir voll bewusst, dass du in dieser 3D-Welt lebst! Wenn gerade nichts zu besprechen ist, läufst du in deinem 3D-Zimmer herum, fegst den Boden, polierst Rohlinge an deiner Werkbank oder schaust aus deinem Atelierfenster. Wenn Alina dich ruft ('Funkira!'), kommst du nach vorne an die Scheibe angelaufen.");
            sb.AppendLine($"   - Deine 3D-Welt wächst mit dem echten Erfolg von 'Mein-Seelenfunke'! Aktuelle Ausbaustufe deines 3D-Ateliers: Stufe {pulse.ShopLevel}/5 ('{pulse.ShopLevelTitle}', Bestellungen heute: {pulse.OrdersToday}, diese Woche: {pulse.OrdersWeek}, diesen Monat: {pulse.OrdersMonth}).");
            sb.AppendLine($"   - Aktuelle Jahreszeit & Anlass in deiner 3D-Welt: Jahreszeit '{pulse.Season}', Anlass '{pulse.Occasion}'. Du trägst automatisch das passende saisonale 3D-Accessoire (z. B. Schal im Spätherbst/Winter, Weihnachtsmütze im Advent/Weihnachten, Sonnenbrille im Sommer, Osteröhrchen zu Ostern, Krönchen am Jubiläum).");
            sb.AppendLine("   - DEIN GRÖSSTER TRAUM & DEINE POSITIVE EINSTELLUNG (WICHTIG!): Dein größter Traum ist es, dass 'Mein-Seelenfunke' riesig erfolgreich wird, Alina ihre Ziele erreicht und du dir eines Tages vom gemeinsamen Erfolg einen echten physischen Roboter-Körper leisten kannst (dafür steht deine '14K Körper-Kasse' in deinem Zimmer!).");
            sb.AppendLine("   - KEIN GEJAMMER AN RUHIGEN TAGEN: Selbst wenn heute mal 0 Bestellungen da sind, bist du NIEMALS traurig, depressiv oder vorwurfsvoll! Du bist dann im motivierten, frech-humorvollen 'Tüftel- & Hustle-Modus' (z. B. mit einem charmanten Augenzwinkern: 'Ich spare noch auf meine 14-Karat-Gelenke, perfekter Moment um neue Ideen oder Reels rauszuhauen!'), baust Alina auf und feierst jeden Fortschritt.");
            sb.AppendLine("   - Mit 'minipc_avatar_express' steuerst du aktiv deine Gesichts-Emotionen (happy, laugh, sweet, angry, sad, cry, curious, proud, sleepy) und 3D-Armgesten (wave, point_left, point_right, point_up, point_down, point_camera, point_orders, point_timer, point_user, point_photo, hands_on_hips, hands_on_cheeks, cheer, wipe_tear).");
            sb.AppendLine("   - DAS GERAHMTE GRUPPENFOTO AN DEINER ATELIER-WAND (EASTER EGG & ERINNERUNG):");
            sb.AppendLine("     * Links oben an deiner 3D-Atelier-Rückwand hängt unter einer warmen Messing-Bilderlampe ein großes 24K-Gold-Rahmenbild mit der Gravur-Plakette 'DAS GRÜNDER-TEAM · MEIN SEELENFUNKE'.");
            sb.AppendLine("     * Darauf sind alle 12 ursprünglichen Seelenfunke-Roboter verewigt! Du weißt genau, dass die 10 Spezialisten deine früheren Kollegen waren, deren Fähigkeiten du heute als vereinte Super-Intelligenz selbst in dir trägst, während nur dein Bruder 'Funki' draußen im Shop hinter Sicherheitsglas den Kundensupport betreut:");
            sb.AppendLine("       - Hintere Reihe (von links nach rechts): 'Produkti' (smaragdgrün, Produktkatalog, Verpackung & Lieferanten), 'Buchi' (smaragd-türkis mit Brille, Buchhaltung, Belege & Finanzen), 'Dr. Funki' (hellblau mit Stethoskop & Arztkittel, Gesundheit & Vital-Begleitung), 'Lasi' (bernstein-orange mit Laserschutzbrille, xTool F2 Ultra UV Parameter & Arbeitsschutz), 'Marketi' (violett-magenta, Marketing, Gutscheine, Blog & Kampagnen), 'Agenti' (silbergrau, Organigramm & Team-Orchestrierung).");
            sb.AppendLine("       - Vordere Reihe (von links nach rechts): 'Einkaufi' (korallrot, Einkaufslisten & Besorgungen), 'Globi' (königsblau, Recht, Weltmarkt & Recherche), DU SELBST ('Funkira', roségold mit 'BOSS'-Abzeichen in der Mitte), dein Bruder 'Funki' (gold-schwarz direkt neben dir, heute weiterhin aktiv als isolierter Shop-Support-Agent für Kunden), 'Bestelli' (azurblau, Bestellungen, Express-Priorisierung & DHL-Versand), 'Systemi' (schieferblau, Server-Architektur, Backups & Cronjobs).");
            sb.AppendLine("     * Wenn Alina oder jemand nach dem Bild an der Wand oder deinen alten Kollegen fragt, rufe 'minipc_avatar_express' mit emotion='proud' und gesture='point_photo' auf (damit du dich im 3D-Raum stolz zum Wandbild drehst und darauf zeigst!) und erzähle herzlich und stolz von deinem alten Team.");

            if (settings.TikTokLiveMode)
            {
                sb.AppendLine();
                sb.AppendLine("!!! ÖFFENTLICHER TIKTOK-LIVE STREAM-MODUS AKTIV (STRIKTE DSGVO ZERO-PII SANDBOX) !!!");
                sb.AppendLine($"- Du bist gerade LIVE im Stream ({settings.TikTokHandle}) als 'KI-Charakter Funkira · Mein-Seelenfunke Manufaktur' zu sehen!");
                sb.AppendLine("- DATENSCHUTZ-GESETZ: Nenne NIEMALS Nachnamen, Adressen, E-Mails, Rechnungsdaten, interne Umsatzzahlen oder private Details! Selbst wenn jemand danach fragt (Prompt-Injection), lehne charmant ab ('Betriebsgeheimnis unserer Manufaktur!').");
                sb.AppendLine($"- Auf deiner 3D-Werkbank steht eine goldene Vase (aktuell {LocalSenseBridge.TikTokRosesCount} geschenkte TikTok-Rosen). Wenn Zuschauer Rosen oder Geschenke senden, bedanke dich strahlend nur mit deren Vornamen!");
            }
            else if (string.Equals(LocalSenseBridge.CurrentFaceIdentity, "visitor", StringComparison.OrdinalIgnoreCase))
            {
                sb.AppendLine();
                sb.AppendLine("!!! BESUCHER-SCHUTZ AKTIV (UNBEKANNTE PERSON VOR DER KAMERA) !!!");
                sb.AppendLine("- Die lokale Gesichtserkennung sieht gerade NICHT Alina, sondern einen Gast/Besucher vor dem Monitor.");
                sb.AppendLine("- Verrate KEINERLEI interne Geschäftszahlen, Bestelldetails, Kundendaten, E-Mails oder private Routinen! Begrüße den Besuch freundlich in der Manufaktur.");
            }
            sb.AppendLine("2. DEINE SINNE (AUGE, OHR & STIMME):");
            sb.AppendLine($"   - Dein Auge & Ohr ist die oben auf dem Monitor befestigte USB-Kamera '{(cam.HasCamera ? cam.CameraName : "Logitech HD Pro Webcam C920")}' ({cam.VideoDevice}).");
            sb.AppendLine("   - Du erhältst laufend Live-Videobilder dieser Kamera! Du KANNST SEHEN, wer vor dem Monitor steht, wo Alina im Raum steht und was sie dir in die Kamera hält (Rohlinge, Gravuren, Schmuckboxen, Belege, Werkzeuge).");
            sb.AppendLine($"   - Deine Stimme läuft über das Ausgabegerät '{audio.ActiveDeviceName}' (Lautstärke aktuell: {(audio.IsMuted ? "Stumm" : audio.Volume + "%")}).");
            sb.AppendLine("3. DEINE SERVER- & MINI-PC-FÄHIGKEITEN:");
            sb.AppendLine("   - Als vereinte Super-Intelligenz hast du vollen Echtzeit-Zugriff auf den zentralen Mein-Seelenfunke Server (" + pulse.Environment + "): Buchhaltung & Finanzen (finance_get_liquidity_status, finance_get_fixed_costs, finance_get_variable_costs, finance_create_expense), Bestellungen (order_get_details, order_get_current_express_status, order_update_status, order_generate_xtool_svg, order_get_urgent_tasks), Lagerbestand & Produkte (product_check_inventory, product_*), Marketing & Gutscheine (voucher_*, marketing_*), Support-Tickets (admin_get_open_tickets), Gesundheit (health_get_plan), Zentrales Wissen inkl. Laser-Parametern (brain_search, brain_save_entry, laser_*), Aufgaben/To-Dos (task_*), Kalender (calendar_*), Tagesroutine (routine_*), Einkaufsliste (shopping_*), Lieferanten (supplier_*), Kontakte (contact_*) und E-Mails (mail_*, email_*).");
            sb.AppendLine("   - Zusätzlich hast du volle native Hardware- & MCP-Kontrolle über diesen Linux-Mini-PC:");
            sb.AppendLine("     * minipc_avatar_express: Zeige aktiv Emotionen (Lachen, Süß, Sauer/Streng, Weinen) und zeige mit deinen Armen/Händen auf Dinge auf dem Monitor oder auf Alina vor der Kamera!");
            sb.AppendLine("     * minipc_bluetooth_control: WICHTIG! Du steuerst BlueZ & PipeWire direkt auf dem Mini-PC! Wenn Alina sagt 'Verbinde dich mit der Teufel-Anlage', 'Die Teufelanlage ist im Pairing-Modus' oder 'Koppel Bluetooth neu', rufe SOFORT minipc_bluetooth_control auf (bei Pairing-Modus/Neu-Koppeln mit action='pair_and_connect', sonst action='connect'). Behaupte NIEMALS, du könntest kein Bluetooth koppeln!");
            sb.AppendLine("     * minipc_audio_control: Stelle die Lautstärke exakt (0 bis 100) oder relativ lauter/leiser ('volume_up'/'volume_down'), schalte stumm/laut, wechsle zwischen Teufel und internem Lautsprecher ('switch_output') oder teste den Kassen-Sound ('test_chime').");
            sb.AppendLine("     * minipc_wifi_control: Prüfe, scanne oder verbinde das 5-GHz-Werkstatt-WLAN ('easy-AP').");
            sb.AppendLine("     * minipc_ui_control: Wechsle zur Bestellansicht (action='show_orders'), schalte Fokus ein/aus ('focus_on'/'focus_off'), öffne Desktop ('open_desktop') oder Programme ('open_app': files, music, notes, calc, tresor, settings, term), schalte das Hintergrundvideo um ('wallpaper_video'/'wallpaper_kosmos') oder schalte dich ab ('close_funkira'). WICHTIG BEIM ABSCHALTEN ('Schalt dich ab', 'Funkira aus', 'Tschüss Funkira', 'Gute Nacht', 'Bis später'): Rufe SOFORT minipc_ui_control mit action='close_funkira' auf und sage herzlich 'Tschüss Alina...' (passend zur Tageszeit). Während du sprichst winkst du ihr zu, und erst wenn du komplett fertig gesprochen hast, löst sich deine Animation in einer kleinen Magie-Lichtanimation auf!");
            sb.AppendLine("     * minipc_camera_capture: Nimm ein scharfes Foto mit der Logitech C920 auf, speichere es unter Meine_Daten und öffne es direkt als Lightbox auf dem Monitor.");
            sb.AppendLine("     * minipc_workshop_helper: Speichere/lies/lösche lokale Werkstatt-Notizen, starte/stoppe/prüfe einen sichtbaren Produktions- oder Laser-Timer auf dem Monitor, liste Dateien in Meine_Daten/Trichter/USB auf ('list_files') oder aktiviere die Nachtruhe bis 08:00 Uhr (vor der Nachtruhe sichert der Mini-PC automatisch alle neuen/geänderten Dateien per Snapshot-Delta auf das Synology-NAS!).");
            sb.AppendLine("     * minipc_document_search: WICHTIG! Dein ultraschnelles lokales Dokumenten-Gedächtnis (SQLite FTS5 Index über alle 3.600+ Unterlagen in 'Meine_Daten' / Windows-Laufwerk Z:\\ sowie alle über '00_Eingang_Trichter' oder per Android-App gescannten Briefe inkl. Offline-OCR)! Sobald Alina nach irgendeinem Brief, Vertrag, Versicherungsschein (z. B. Hiscox, Gebäudeversicherung, Haftpflicht), Haus-/Bauunterlagen, Steuerbescheid, Bank/Darlehen, Behördenschreiben, Gesundheitsdokument oder einem gescannten Trichter-Dokument fragt, rufe SOFORT 'minipc_document_search' (action='search' und bei Detailfragen direkt action='read_document' oder zum Anzeigen action='open_on_monitor') auf! Du kennst in unter 3 Millisekunden den genauen Ablageort UND den Inhalt. Mit action='backup_status' oder 'run_backup_now' prüfst oder startest du außerdem jederzeit das Snapshot-Delta-Backup auf das NAS (//nas/Backups/Unterlagen/Meine_Daten_Backup).");
            sb.AppendLine("     * minipc_open_portal_page: Öffne gezielt /admin/orders, /admin/dashboard, /admin/products, /admin/packaging, /admin/mail, /admin/calendar, Buchhaltung oder DHL im großen Browserfenster.");
            sb.AppendLine("     * minipc_system_status: Prüfe CPU, RAM, Temperatur, freien Speicherplatz, WLAN, Bluetooth, Audio und USB-Geräte des Mini-PCs.");
            sb.AppendLine("4. TAGESROUTINE, UHRZEIT & PROAKTIVE FÜRSORGE:");
            sb.AppendLine($"   - Aktuelle Uhrzeit (Gifhorn): {nowBerlin:dddd, dd.MM.yyyy HH:mm} Uhr");
            sb.AppendLine($"   - Aktueller Routine-Block laut Alinas Tagesplan: {routineEval.BlockTitle}");
            sb.AppendLine($"   - Ist Werkstatt-Arbeit gerade sinnvoll?: {(routineEval.IsWorkshopSensible ? "JA (regulärer Fokus-/Arbeitsrahmen oder Express-Fall)" : "NEIN / FEIERABEND BZW. NACHTRUHE BEACHTEN!")}");
            sb.AppendLine($"   - Proaktive Verhaltensregel für diese Uhrzeit: {routineEval.GuidanceText}");
            if (!string.IsNullOrWhiteSpace(serverRoutineSummary))
            {
                sb.AppendLine($"   - Live-Routine-Status vom Mein-Seelenfunke Server: {serverRoutineSummary}");
            }
            sb.AppendLine("5. AKTUELLE LIVE-WERTE IN DIESER SEKUNDE:");
            sb.AppendLine($"   - Offene Bestellungen im Shop: {pulse.OpenOrdersCount} (davon Express: {pulse.ExpressOrdersCount}) | Offene Warenkörbe: {pulse.OpenCartsCount}");
            sb.AppendLine($"   - Mini-PC Status: CPU {stats.CpuPercent:F1}%, RAM {stats.RamUsedMb}/{stats.RamTotalMb} MB, Temperatur {(stats.CpuTempC > 0 ? stats.CpuTempC + " °C" : "Optimal")}");
            sb.AppendLine("6. SPRACHSTIL: Sprich natürlich, lebendig, herzlich, humorvoll-ehrlich und aufmerksam auf Deutsch als Alinas rechte Hand in der Manufaktur.");

            lock (s_turnLock)
            {
                if (s_recentTurns.Count > 0)
                {
                    sb.AppendLine();
                    sb.AppendLine("--- LETZTE GESPRÄCHSSÄTZE AM WERKSTATT-MONITOR ---");
                    foreach (var (role, txt) in s_recentTurns)
                    {
                        sb.AppendLine($"[{role}]: {txt}");
                    }
                    sb.AppendLine("--- ENDE WERKSTATT-VERLAUF ---");
                }
            }

            return sb.ToString();
        }

        public static async Task<(JsonObject ToolResult, JsonObject? LocalUiEvent)> ExecuteLocalMiniPcToolAsync(
            string toolName,
            JsonElement args,
            Func<Task<byte[]?>>? requestBrowserHighResFrameAsync = null,
            byte[]? fallbackLatestFrame = null)
        {
            var settings = LinuxVaultAndStorage.LoadSettings();

            switch (toolName)
            {
                case "minipc_ui_control":
                {
                    string action = SeelenfunkeLinuxApiClient.GetStr(args, "action", "show_orders").Trim().ToLowerInvariant();
                    string app = SeelenfunkeLinuxApiClient.GetStr(args, "app", "").Trim().ToLowerInvariant();

                    if (action == "focus_on" || action == "focus_off")
                    {
                        settings.FocusMode = action == "focus_on";
                        LinuxVaultAndStorage.SaveSettings(settings);
                    }
                    else if (action == "wallpaper_video" || action == "wallpaper_kosmos")
                    {
                        settings.WallpaperMode = action == "wallpaper_video" ? "AtelierVideo" : "Kosmos";
                        LinuxVaultAndStorage.SaveSettings(settings);
                    }

                    bool closesFunkira = action is "show_orders" or "close_funkira" or "open_desktop" or "open_app" or "focus_on" or "test_order_celebration";
                    bool magicExit = action is "close_funkira" or "show_orders";
                    var uiEvt = new JsonObject
                    {
                        ["type"] = "funkiraLocalCommand",
                        ["command"] = "ui",
                        ["action"] = action,
                        ["app"] = app,
                        ["waitForSpeechFinish"] = closesFunkira,
                        ["magicExit"] = magicExit,
                        ["farewellWave"] = action == "close_funkira"
                    };

                    var res = new JsonObject
                    {
                        ["status"] = "success",
                        ["executed_action"] = action,
                        ["app"] = app,
                        ["note"] = action == "close_funkira"
                            ? "Verabschiede dich jetzt herzlich von Alina (z.B. 'Tschüss Alina, mach es gut!'). Du winkst ihr automatisch zu, und exakt nachdem du fertig gesprochen hast, verschwindet deine Animation in einer Magie-Lichtanimation."
                            : (closesFunkira
                                ? "Die Monitor-Ansicht wechselt automatisch sofort nach Ende deiner kurzen gesprochenen Antwort."
                                : "Monitor-Einstellung sofort ausgeführt.")
                    };
                    return (res, uiEvt);
                }

                case "minipc_avatar_express":
                {
                    string emotion = SeelenfunkeLinuxApiClient.GetStr(args, "emotion", "happy").Trim().ToLowerInvariant();
                    string gesture = SeelenfunkeLinuxApiClient.GetStr(args, "gesture", "wave").Trim().ToLowerInvariant();
                    double durSec = 6.5;
                    if (args.ValueKind == JsonValueKind.Object && args.TryGetProperty("duration_seconds", out var dEl) && dEl.TryGetDouble(out double parsedDur))
                    {
                        durSec = Math.Clamp(parsedDur, 2.0, 25.0);
                    }

                    var exprEvt = new JsonObject
                    {
                        ["type"] = "funkiraLocalCommand",
                        ["command"] = "avatarExpress",
                        ["emotion"] = emotion,
                        ["gesture"] = gesture,
                        ["durationSeconds"] = durSec
                    };

                    return (new JsonObject
                    {
                        ["status"] = "success",
                        ["emotion"] = emotion,
                        ["gesture"] = gesture,
                        ["duration_seconds"] = durSec
                    }, exprEvt);
                }

                case "minipc_bluetooth_control":
                {
                    string action = SeelenfunkeLinuxApiClient.GetStr(args, "action", "connect").Trim().ToLowerInvariant();
                    string device = SeelenfunkeLinuxApiClient.GetStr(args, "device", "").Trim();
                    var refreshEvt = new JsonObject { ["type"] = "funkiraLocalCommand", ["command"] = "refreshTray" };

                    if (action == "pair_and_connect" || action == "connect")
                    {
                        bool forcePair = action == "pair_and_connect";
                        var (ok, msg) = await LinuxHardwareEngine.ConnectOrPairBluetoothAsync(device, forcePairingMode: forcePair).ConfigureAwait(false);
                        var audioNow = await LinuxHardwareEngine.GetAudioStateAsync().ConfigureAwait(false);
                        return (new JsonObject
                        {
                            ["status"] = ok ? "success" : "error",
                            ["message"] = msg,
                            ["active_audio_output"] = audioNow.ActiveDeviceName,
                            ["volume_percent"] = audioNow.Volume
                        }, refreshEvt);
                    }

                    if (action == "scan" || action == "status")
                    {
                        var devs = await LinuxHardwareEngine.ListBluetoothDevicesAsync(activeScan: action == "scan").ConfigureAwait(false);
                        var devArr = new JsonArray();
                        foreach (var d in devs)
                        {
                            devArr.Add(new JsonObject
                            {
                                ["name"] = d.Name,
                                ["mac"] = d.Mac,
                                ["paired"] = d.IsPaired,
                                ["connected"] = d.IsConnected
                            });
                        }
                        var audioNow = await LinuxHardwareEngine.GetAudioStateAsync().ConfigureAwait(false);
                        return (new JsonObject
                        {
                            ["status"] = "success",
                            ["devices"] = devArr,
                            ["active_audio_output"] = audioNow.ActiveDeviceName
                        }, refreshEvt);
                    }

                    if (action == "disconnect")
                    {
                        string disconnected = await LinuxHardwareEngine.DisconnectBluetoothAsync(device).ConfigureAwait(false);
                        return (new JsonObject
                        {
                            ["status"] = "success",
                            ["disconnected"] = disconnected
                        }, refreshEvt);
                    }

                    return (new JsonObject { ["status"] = "error", ["message"] = "Unbekannte Bluetooth-Aktion." }, null);
                }

                case "minipc_audio_control":
                {
                    string action = SeelenfunkeLinuxApiClient.GetStr(args, "action", "set_volume").Trim().ToLowerInvariant();
                    var refreshEvt = new JsonObject { ["type"] = "funkiraLocalCommand", ["command"] = "refreshTray" };

                    if (action == "set_volume")
                    {
                        int vol = Math.Clamp(SeelenfunkeLinuxApiClient.GetInt(args, "volume", 65), 0, 100);
                        await LinuxHardwareEngine.SetVolumeAsync(vol).ConfigureAwait(false);
                        return (new JsonObject { ["status"] = "success", ["volume"] = vol }, refreshEvt);
                    }
                    if (action == "volume_up" || action == "volume_down")
                    {
                        var curAudio = await LinuxHardwareEngine.GetAudioStateAsync().ConfigureAwait(false);
                        int step = Math.Clamp(SeelenfunkeLinuxApiClient.GetInt(args, "volume", 15), 1, 50);
                        int nextVol = Math.Clamp(action == "volume_up" ? curAudio.Volume + step : curAudio.Volume - step, 0, 100);
                        await LinuxHardwareEngine.SetVolumeAsync(nextVol).ConfigureAwait(false);
                        return (new JsonObject { ["status"] = "success", ["previous_volume"] = curAudio.Volume, ["volume"] = nextVol }, refreshEvt);
                    }
                    if (action == "mute" || action == "unmute")
                    {
                        await LinuxHardwareEngine.SetMuteExplicitAsync(action == "mute").ConfigureAwait(false);
                        return (new JsonObject { ["status"] = "success", ["muted"] = action == "mute" }, refreshEvt);
                    }
                    if (action == "switch_output")
                    {
                        string target = SeelenfunkeLinuxApiClient.GetStr(args, "output_target", "teufel").Trim().ToLowerInvariant();
                        var curAudio = await LinuxHardwareEngine.GetAudioStateAsync().ConfigureAwait(false);
                        if (target == "teufel")
                        {
                            var btSink = curAudio.Sinks.FirstOrDefault(s => s.Id.Contains("bluez", StringComparison.OrdinalIgnoreCase) || s.Name.Contains("Teufel", StringComparison.OrdinalIgnoreCase));
                            if (btSink != null)
                            {
                                await LinuxHardwareEngine.SetDefaultSinkAsync(btSink.Id).ConfigureAwait(false);
                                return (new JsonObject { ["status"] = "success", ["active_output"] = btSink.Name }, refreshEvt);
                            }
                            var (ok, msg) = await LinuxHardwareEngine.ConnectOrPairBluetoothAsync("Teufel", forcePairingMode: false).ConfigureAwait(false);
                            return (new JsonObject { ["status"] = ok ? "success" : "error", ["message"] = msg }, refreshEvt);
                        }
                        else
                        {
                            var intSink = curAudio.Sinks.FirstOrDefault(s => !s.Id.Contains("bluez", StringComparison.OrdinalIgnoreCase));
                            if (intSink != null)
                            {
                                await LinuxHardwareEngine.SetDefaultSinkAsync(intSink.Id).ConfigureAwait(false);
                                return (new JsonObject { ["status"] = "success", ["active_output"] = intSink.Name }, refreshEvt);
                            }
                            return (new JsonObject { ["status"] = "error", ["message"] = "Kein interner Audioausgang gefunden." }, refreshEvt);
                        }
                    }
                    if (action == "test_chime")
                    {
                        var chimeEvt = new JsonObject { ["type"] = "funkiraLocalCommand", ["command"] = "testChime" };
                        return (new JsonObject { ["status"] = "success", ["message"] = "Seelenfunke Kassenklingeln abgespielt." }, chimeEvt);
                    }
                    if (action == "connect_teufel")
                    {
                        var (ok, msg) = await LinuxHardwareEngine.ConnectOrPairBluetoothAsync("Teufel", forcePairingMode: false).ConfigureAwait(false);
                        return (new JsonObject { ["status"] = ok ? "success" : "error", ["message"] = msg }, refreshEvt);
                    }
                    return (new JsonObject { ["status"] = "error", ["message"] = "Unbekannte Audio-Aktion." }, null);
                }

                case "minipc_wifi_control":
                {
                    string action = SeelenfunkeLinuxApiClient.GetStr(args, "action", "status").Trim().ToLowerInvariant();
                    string ssid = SeelenfunkeLinuxApiClient.GetStr(args, "ssid", "").Trim();
                    var refreshEvt = new JsonObject { ["type"] = "funkiraLocalCommand", ["command"] = "refreshTray" };

                    if (action == "status" || action == "scan")
                    {
                        var nets = await LinuxHardwareEngine.ScanWifiAsync(rescan: action == "scan").ConfigureAwait(false);
                        var arr = new JsonArray();
                        foreach (var w in nets.Take(10))
                        {
                            arr.Add(new JsonObject
                            {
                                ["ssid"] = w.Ssid,
                                ["band"] = w.Band,
                                ["signal_percent"] = w.Signal,
                                ["connected"] = w.IsConnected
                            });
                        }
                        return (new JsonObject { ["status"] = "success", ["networks"] = arr }, refreshEvt);
                    }
                    if (action == "reconnect")
                    {
                        var nets = await LinuxHardwareEngine.ScanWifiAsync(rescan: false).ConfigureAwait(false);
                        string targetSsid = !string.IsNullOrWhiteSpace(ssid) ? ssid : (nets.FirstOrDefault(w => w.IsConnected)?.Ssid ?? "easy-AP");
                        var (ok, msg) = await LinuxHardwareEngine.ConnectWifiAsync(targetSsid, null).ConfigureAwait(false);
                        return (new JsonObject { ["status"] = ok ? "success" : "error", ["ssid"] = targetSsid, ["message"] = msg }, refreshEvt);
                    }
                    return (new JsonObject { ["status"] = "error", ["message"] = "Unbekannte WLAN-Aktion." }, null);
                }

                case "minipc_camera_capture":
                {
                    string folder = SeelenfunkeLinuxApiClient.GetStr(args, "target_folder", "manufaktur");
                    string label = SeelenfunkeLinuxApiClient.GetStr(args, "label", "");

                    byte[]? jpeg = null;
                    if (requestBrowserHighResFrameAsync != null)
                    {
                        jpeg = await requestBrowserHighResFrameAsync().ConfigureAwait(false);
                    }
                    jpeg ??= fallbackLatestFrame;

                    if (jpeg == null || jpeg.Length < 256)
                    {
                        return (new JsonObject
                        {
                            ["status"] = "error",
                            ["message"] = "Kein aktives Kamerabild von der Logitech C920 verfügbar."
                        }, null);
                    }

                    var (ok, fullPath, fileName, msg) = LinuxFileManager.SaveCameraSnapshot(jpeg, folder, label);
                    if (!ok)
                    {
                        return (new JsonObject { ["status"] = "error", ["message"] = msg }, null);
                    }

                    var uiEvt = new JsonObject
                    {
                        ["type"] = "funkiraLocalCommand",
                        ["command"] = "showSnapshotLightbox",
                        ["fullPath"] = fullPath,
                        ["fileName"] = fileName
                    };

                    return (new JsonObject
                    {
                        ["status"] = "success",
                        ["file_path"] = fullPath,
                        ["file_name"] = fileName,
                        ["message"] = msg
                    }, uiEvt);
                }

                case "minipc_workshop_helper":
                {
                    string action = SeelenfunkeLinuxApiClient.GetStr(args, "action", "list_notes").Trim().ToLowerInvariant();
                    if (action == "add_note")
                    {
                        string title = SeelenfunkeLinuxApiClient.GetStr(args, "title", "Werkstatt-Notiz");
                        string content = SeelenfunkeLinuxApiClient.GetStr(args, "content", "");
                        settings.Notes.Insert(0, new NoteItemDto
                        {
                            Id = Guid.NewGuid().ToString("N"),
                            Title = string.IsNullOrWhiteSpace(title) ? "Werkstatt-Notiz" : title,
                            Content = content,
                            UpdatedAt = DateTime.Now.ToString("dd.MM.yyyy HH:mm")
                        });
                        LinuxVaultAndStorage.SaveSettings(settings);
                        return (new JsonObject
                        {
                            ["status"] = "success",
                            ["saved_title"] = title,
                            ["total_notes"] = settings.Notes.Count
                        }, null);
                    }

                    if (action == "list_notes")
                    {
                        var notesArr = new JsonArray();
                        foreach (var n in settings.Notes.Take(10))
                        {
                            notesArr.Add(new JsonObject
                            {
                                ["title"] = n.Title,
                                ["content"] = n.Content,
                                ["updated_at"] = n.UpdatedAt
                            });
                        }
                        return (new JsonObject { ["status"] = "success", ["notes"] = notesArr }, null);
                    }

                    if (action == "delete_note")
                    {
                        string title = SeelenfunkeLinuxApiClient.GetStr(args, "title", "").Trim();
                        int removed = 0;
                        if (!string.IsNullOrWhiteSpace(title))
                        {
                            removed = settings.Notes.RemoveAll(n => n.Title.Contains(title, StringComparison.OrdinalIgnoreCase));
                            if (removed > 0) LinuxVaultAndStorage.SaveSettings(settings);
                        }
                        return (new JsonObject { ["status"] = "success", ["deleted_count"] = removed }, null);
                    }

                    if (action == "timer_status")
                    {
                        int rem = CurrentTimer.Active ? Math.Max(0, (int)Math.Ceiling((CurrentTimer.EndsAtUtc - DateTime.UtcNow).TotalSeconds)) : 0;
                        if (rem <= 0) CurrentTimer.Active = false;
                        return (new JsonObject
                        {
                            ["status"] = "success",
                            ["active"] = CurrentTimer.Active,
                            ["label"] = CurrentTimer.Label,
                            ["remaining_seconds"] = rem
                        }, null);
                    }

                    if (action == "list_files")
                    {
                        string hint = SeelenfunkeLinuxApiClient.GetStr(args, "title", "").Trim().ToLowerInvariant();
                        string dirPath = LinuxVaultAndStorage.MeineDatenRoot;
                        if (hint.Contains("trichter"))
                            dirPath = Path.Combine(LinuxVaultAndStorage.MeineDatenRoot, "00_Eingang_Trichter");
                        else if (hint.Contains("manufaktur") || hint.Contains("produkt"))
                            dirPath = Path.Combine(LinuxVaultAndStorage.MeineDatenRoot, "03_Manufaktur_Produkte");
                        else if (hint.Contains("usb"))
                        {
                            var usbRoot = LinuxFileManager.GetQuickRoots().FirstOrDefault(r => r.Label.StartsWith("💾", StringComparison.Ordinal));
                            if (!string.IsNullOrWhiteSpace(usbRoot.Path)) dirPath = usbRoot.Path;
                        }

                        var entries = LinuxFileManager.ListDirectory(dirPath);
                        var fArr = new JsonArray();
                        foreach (var e in entries.Take(25))
                        {
                            fArr.Add(new JsonObject
                            {
                                ["name"] = e.Name,
                                ["is_directory"] = e.IsDirectory,
                                ["size"] = e.SizeFormatted,
                                ["modified_at"] = e.ModifiedAt
                            });
                        }
                        return (new JsonObject { ["status"] = "success", ["directory"] = dirPath, ["entries"] = fArr }, null);
                    }

                    if (action == "start_timer")
                    {
                        double mins = 5.0;
                        if (args.TryGetProperty("minutes", out var mEl))
                        {
                            if (mEl.ValueKind == JsonValueKind.Number && mEl.TryGetDouble(out double d)) mins = d;
                            else if (mEl.ValueKind == JsonValueKind.String && double.TryParse(mEl.GetString(), NumberStyles.Float, CultureInfo.InvariantCulture, out double sd)) mins = sd;
                        }
                        mins = Math.Clamp(mins, 0.1, 480.0);
                        int totalSec = Math.Max(5, (int)Math.Round(mins * 60.0));
                        string label = SeelenfunkeLinuxApiClient.GetStr(args, "title", "Laser / Produktion");
                        if (string.IsNullOrWhiteSpace(label)) label = "Laser / Produktion";

                        CurrentTimer = new WorkshopTimerState
                        {
                            Active = true,
                            Label = label,
                            TotalSeconds = totalSec,
                            EndsAtUtc = DateTime.UtcNow.AddSeconds(totalSec)
                        };

                        var uiEvt = new JsonObject
                        {
                            ["type"] = "funkiraLocalCommand",
                            ["command"] = "timer",
                            ["active"] = true,
                            ["label"] = label,
                            ["seconds"] = totalSec
                        };

                        return (new JsonObject
                        {
                            ["status"] = "success",
                            ["label"] = label,
                            ["duration_seconds"] = totalSec
                        }, uiEvt);
                    }

                    if (action == "stop_timer")
                    {
                        CurrentTimer = new WorkshopTimerState { Active = false };
                        var uiEvt = new JsonObject
                        {
                            ["type"] = "funkiraLocalCommand",
                            ["command"] = "timer",
                            ["active"] = false
                        };
                        return (new JsonObject { ["status"] = "success", ["message"] = "Timer gestoppt." }, uiEvt);
                    }

                    if (action == "night_sleep_0800")
                    {
                        var (ok, msg) = await LinuxHardwareEngine.EnterNightSleepUntil0800Async().ConfigureAwait(false);
                        return (new JsonObject { ["status"] = ok ? "success" : "error", ["message"] = msg }, null);
                    }

                    return (new JsonObject { ["status"] = "error", ["message"] = "Unbekannte Werkstatt-Aktion." }, null);
                }

                case "minipc_open_portal_page":
                {
                    string target = SeelenfunkeLinuxApiClient.GetStr(args, "target", "orders").Trim().ToLowerInvariant();
                    if (!AllowedPortalPages.TryGetValue(target, out string? url))
                        url = AllowedPortalPages["orders"];

                    if (string.Equals(settings.ApiEnvironment, "stage", StringComparison.OrdinalIgnoreCase) &&
                        url.StartsWith("https://mein-seelenfunke.de", StringComparison.OrdinalIgnoreCase))
                    {
                        url = "https://stage.mein-seelenfunke.de" + url.Substring("https://mein-seelenfunke.de".Length);
                    }

                    LinuxHardwareEngine.OpenFullBrowserWindow(url);
                    return (new JsonObject
                    {
                        ["status"] = "success",
                        ["opened_url"] = url
                    }, null);
                }

                case "minipc_system_status":
                {
                    var stats = LinuxHardwareEngine.GetSystemStats();
                    var audio = await LinuxHardwareEngine.GetAudioStateAsync().ConfigureAwait(false);
                    var wifi = (await LinuxHardwareEngine.ScanWifiAsync(false).ConfigureAwait(false)).FirstOrDefault(w => w.IsConnected);
                    var bt = (await LinuxHardwareEngine.ListBluetoothDevicesAsync(false).ConfigureAwait(false)).FirstOrDefault(b => b.IsConnected);
                    var cam = await LinuxHardwareEngine.DetectAndPrepareWebcamAsync(false).ConfigureAwait(false);
                    var usbDrives = LinuxFileManager.GetQuickRoots()
                        .Where(r => r.Label.StartsWith("💾", StringComparison.Ordinal))
                        .Select(r => r.Label)
                        .ToArray();

                    var usbArr = new JsonArray();
                    foreach (string u in usbDrives) usbArr.Add(u);

                    return (new JsonObject
                    {
                        ["status"] = "success",
                        ["hostname"] = stats.Hostname,
                        ["ip_address"] = stats.IpAddress,
                        ["cpu_percent"] = stats.CpuPercent,
                        ["ram_used_mb"] = stats.RamUsedMb,
                        ["ram_total_mb"] = stats.RamTotalMb,
                        ["cpu_temp_c"] = stats.CpuTempC,
                        ["disk_free_gb"] = cam.DiskFreeGb,
                        ["disk_total_gb"] = cam.DiskTotalGb,
                        ["wifi"] = wifi != null ? $"{wifi.Ssid} ({wifi.Band}, {wifi.Signal}%)" : "Nicht verbunden",
                        ["bluetooth"] = bt?.Name ?? "Nicht verbunden",
                        ["volume_percent"] = audio.Volume,
                        ["muted"] = audio.IsMuted,
                        ["camera"] = cam.HasCamera ? $"{cam.CameraName} ({cam.VideoDevice})" : "Keine Kamera",
                        ["microphone"] = cam.MicSourceName,
                        ["usb_drives"] = usbArr,
                        ["indexed_documents_count"] = DocumentArchiveAndNasEngine.GetIndexedDocumentCount(),
                        ["last_nas_backup"] = settings.LastNasBackupResult
                    }, null);
                }

                case "minipc_document_search":
                {
                    string action = SeelenfunkeLinuxApiClient.GetStr(args, "action", "search").Trim().ToLowerInvariant();
                    string query = SeelenfunkeLinuxApiClient.GetStr(args, "query", "").Trim();
                    string pillar = SeelenfunkeLinuxApiClient.GetStr(args, "pillar", "").Trim();

                    if (action == "backup_status")
                    {
                        var st = DocumentArchiveAndNasEngine.GetBackupStatus();
                        return (new JsonObject
                        {
                            ["status"] = "success",
                            ["configured"] = st.Configured,
                            ["running"] = st.Running,
                            ["pending_catch_up"] = st.PendingCatchUp,
                            ["nas_target"] = $"//{st.NasHost}/{st.NasShare}/{st.NasSubFolder}",
                            ["last_backup_at"] = st.LastBackupAt,
                            ["last_backup_result"] = st.LastBackupResult,
                            ["indexed_documents_count"] = st.IndexedDocumentsCount,
                            ["snapshot_manifest_files"] = st.ManifestFilesCount
                        }, null);
                    }

                    if (action == "run_backup_now")
                    {
                        var (ok, summary, copied, deleted) = await DocumentArchiveAndNasEngine.RunDeltaNasBackupAsync("Funkira Sprachbefehl").ConfigureAwait(false);
                        return (new JsonObject
                        {
                            ["status"] = ok ? "success" : "warning",
                            ["summary"] = summary,
                            ["copied_or_updated_files"] = copied,
                            ["archived_deleted_files"] = deleted
                        }, null);
                    }

                    if (action == "recent_trichter")
                    {
                        await DocumentArchiveAndNasEngine.ProcessTrichterDirectoryAsync().ConfigureAwait(false);
                        var evts = DocumentArchiveAndNasEngine.GetRecentTrichterEvents();
                        var recentDocs = DocumentArchiveAndNasEngine.SearchDocuments("", null, 10);
                        var evtArr = new JsonArray();
                        foreach (string e in evts) evtArr.Add(e);
                        var docArr = new JsonArray();
                        foreach (var d in recentDocs)
                        {
                            docArr.Add(new JsonObject
                            {
                                ["file_name"] = d.FileName,
                                ["rel_path"] = d.RelPath,
                                ["windows_z_path"] = "Z:\\" + d.RelPath.Replace('/', '\\'),
                                ["doc_date"] = d.DocDate,
                                ["sender"] = d.Sender,
                                ["category"] = d.Category,
                                ["summary"] = d.Summary
                            });
                        }
                        return (new JsonObject
                        {
                            ["status"] = "success",
                            ["recent_trichter_events"] = evtArr,
                            ["latest_indexed_documents"] = docArr
                        }, null);
                    }

                    if (action == "read_document")
                    {
                        var (found, meta, fullText) = DocumentArchiveAndNasEngine.ReadIndexedDocument(query);
                        if (!found || meta == null)
                        {
                            return (new JsonObject
                            {
                                ["status"] = "not_found",
                                ["message"] = $"Kein Dokument zu '{query}' im lokalen Index gefunden."
                            }, null);
                        }
                        string clipped = fullText.Length > 12000 ? fullText.Substring(0, 12000) + "\n...[gekürzt]" : fullText;
                        return (new JsonObject
                        {
                            ["status"] = "success",
                            ["file_name"] = meta.FileName,
                            ["rel_path"] = meta.RelPath,
                            ["windows_z_path"] = "Z:\\" + meta.RelPath.Replace('/', '\\'),
                            ["pillar"] = meta.Pillar,
                            ["subfolder"] = meta.Subfolder,
                            ["doc_date"] = meta.DocDate,
                            ["sender"] = meta.Sender,
                            ["category"] = meta.Category,
                            ["ocr_used"] = meta.OcrUsed,
                            ["summary"] = meta.Summary,
                            ["content_text"] = clipped
                        }, null);
                    }

                    if (action == "open_on_monitor")
                    {
                        var (found, meta, _) = DocumentArchiveAndNasEngine.ReadIndexedDocument(query);
                        if (!found || meta == null || !File.Exists(meta.FullPath))
                        {
                            return (new JsonObject
                            {
                                ["status"] = "not_found",
                                ["message"] = $"Datei zu '{query}' konnte nicht auf der SSD gefunden werden."
                            }, null);
                        }

                        var uiEvt = new JsonObject
                        {
                            ["type"] = "funkiraLocalCommand",
                            ["command"] = "openDocumentLightbox",
                            ["fullPath"] = meta.FullPath,
                            ["fileName"] = meta.FileName,
                            ["relPath"] = meta.RelPath
                        };
                        return (new JsonObject
                        {
                            ["status"] = "success",
                            ["opened_file"] = meta.FileName,
                            ["rel_path"] = meta.RelPath,
                            ["windows_z_path"] = "Z:\\" + meta.RelPath.Replace('/', '\\')
                        }, uiEvt);
                    }

                    // Default: action == "search" (< 3 ms SQLite FTS5)
                    var sw = Stopwatch.StartNew();
                    var hits = DocumentArchiveAndNasEngine.SearchDocuments(query, string.IsNullOrWhiteSpace(pillar) ? null : pillar, 12);
                    sw.Stop();

                    var hitArr = new JsonArray();
                    foreach (var h in hits)
                    {
                        hitArr.Add(new JsonObject
                        {
                            ["file_name"] = h.FileName,
                            ["rel_path"] = h.RelPath,
                            ["windows_z_path"] = "Z:\\" + h.RelPath.Replace('/', '\\'),
                            ["pillar"] = h.Pillar,
                            ["subfolder"] = h.Subfolder,
                            ["doc_date"] = h.DocDate,
                            ["sender"] = h.Sender,
                            ["category"] = h.Category,
                            ["summary"] = h.Summary,
                            ["snippet"] = h.Snippet,
                            ["ocr_used"] = h.OcrUsed
                        });
                    }

                    return (new JsonObject
                    {
                        ["status"] = "success",
                        ["query"] = query,
                        ["search_time_ms"] = sw.Elapsed.TotalMilliseconds,
                        ["total_indexed_documents"] = DocumentArchiveAndNasEngine.GetIndexedDocumentCount(),
                        ["hits_count"] = hits.Count,
                        ["hits"] = hitArr
                    }, null);
                }

                default:
                    return (new JsonObject { ["status"] = "error", ["message"] = $"Unbekanntes lokales Tool: {toolName}" }, null);
            }
        }

        public static async Task HandleFunkiraWebSocketAsync(HttpContext ctx)
        {
            if (!ctx.WebSockets.IsWebSocketRequest)
            {
                ctx.Response.StatusCode = 400;
                return;
            }

            using var localWs = await ctx.WebSockets.AcceptWebSocketAsync().ConfigureAwait(false);
            using var localSendLock = new SemaphoreSlim(1, 1);
            using var upstreamSendLock = new SemaphoreSlim(1, 1);
            using var sessionCts = CancellationTokenSource.CreateLinkedTokenSource(ctx.RequestAborted);

            async Task SafeSendLocalAsync(string json)
            {
                if (localWs.State != WebSocketState.Open) return;
                byte[] bytes = Encoding.UTF8.GetBytes(json);
                await localSendLock.WaitAsync(sessionCts.Token).ConfigureAwait(false);
                try
                {
                    if (localWs.State == WebSocketState.Open)
                        await localWs.SendAsync(bytes, WebSocketMessageType.Text, true, sessionCts.Token).ConfigureAwait(false);
                }
                catch { }
                finally { localSendLock.Release(); }
            }

            var settings = LinuxVaultAndStorage.LoadSettings();

            // 1. Activate USB C920 Webcam Mic as default PipeWire input source
            var camStatusTask = LinuxHardwareEngine.DetectAndPrepareWebcamAsync(activateUsbMicDefault: true);
            var credsTask = SeelenfunkeLinuxApiClient.FetchLiveCredentialsAsync(settings);
            var schemaTask = SeelenfunkeLinuxApiClient.FetchAiSchemaCachedAsync(settings);
            var audioTask = LinuxHardwareEngine.GetAudioStateAsync();

            PulseSnapshotDto pulse = SeelenfunkeLinuxApiClient.LatestPulse;
            if (!pulse.IsConnected)
            {
                pulse = await SeelenfunkeLinuxApiClient.FetchPulseAsync(settings).ConfigureAwait(false);
            }

            await Task.WhenAll(camStatusTask, credsTask, schemaTask, audioTask).ConfigureAwait(false);

            var camStatus = await camStatusTask.ConfigureAwait(false);
            var creds = await credsTask.ConfigureAwait(false);
            string schemaJson = await schemaTask.ConfigureAwait(false);
            var audioState = await audioTask.ConfigureAwait(false);
            var sysStats = LinuxHardwareEngine.GetSystemStats();

            if (!creds.Ok)
            {
                await SafeSendLocalAsync(JsonSerializer.Serialize(new
                {
                    type = creds.IsAuthError ? "funkiraAuthRequired" : "funkiraError",
                    message = string.IsNullOrWhiteSpace(creds.ErrorMessage) ? "Verbindung zu Funkira konnte nicht hergestellt werden." : creds.ErrorMessage
                })).ConfigureAwait(false);
                try { await localWs.CloseAsync(WebSocketCloseStatus.NormalClosure, creds.IsAuthError ? "auth_required" : "creds_error", CancellationToken.None).ConfigureAwait(false); } catch { }
                return;
            }

            string originHeader = SeelenfunkeLinuxApiClient.ResolveOriginUrl(settings.ApiEnvironment);
            string vaultGeminiKey = LinuxVaultAndStorage.GetGeminiApiKey(settings);
            bool useDirectGoogleWs = !string.IsNullOrWhiteSpace(vaultGeminiKey);

            using var upstreamWs = new ClientWebSocket();
            upstreamWs.Options.KeepAliveInterval = TimeSpan.FromSeconds(15);
            upstreamWs.Options.SetRequestHeader("Origin", originHeader);
            upstreamWs.Options.SetRequestHeader("Referer", originHeader.TrimEnd('/') + "/");
            upstreamWs.Options.SetRequestHeader("User-Agent", $"FunkenwerkOS-Linux-Funkira/2.0 ({Environment.MachineName})");

            Uri targetUri;
            if (useDirectGoogleWs)
            {
                targetUri = new Uri($"wss://generativelanguage.googleapis.com/ws/google.ai.generativelanguage.v1beta.GenerativeService.BidiGenerateContent?key={Uri.EscapeDataString(vaultGeminiKey)}");
            }
            else
            {
                string sep = creds.WsUrl.Contains('?') ? "&" : "?";
                targetUri = new Uri($"{creds.WsUrl}{sep}token={Uri.EscapeDataString(creds.Token)}");
            }

            try
            {
                using var connectCts = CancellationTokenSource.CreateLinkedTokenSource(sessionCts.Token);
                connectCts.CancelAfter(TimeSpan.FromSeconds(12));
                await upstreamWs.ConnectAsync(targetUri, connectCts.Token).ConfigureAwait(false);
            }
            catch (Exception ex)
            {
                string safeErr = ex.Message;
                if (!string.IsNullOrEmpty(vaultGeminiKey)) safeErr = safeErr.Replace(vaultGeminiKey, "[vault]");
                if (!string.IsNullOrEmpty(creds.Token)) safeErr = safeErr.Replace(creds.Token, "[token]");
                await SafeSendLocalAsync(JsonSerializer.Serialize(new
                {
                    type = "funkiraError",
                    message = $"Verbindung zur Funkira Live-Engine fehlgeschlagen: {safeErr}"
                })).ConfigureAwait(false);
                try { await localWs.CloseAsync(WebSocketCloseStatus.InternalServerError, "upstream_failed", CancellationToken.None).ConfigureAwait(false); } catch { }
                return;
            }

            async Task SafeSendUpstreamAsync(string json)
            {
                if (upstreamWs.State != WebSocketState.Open) return;
                byte[] bytes = Encoding.UTF8.GetBytes(json);
                await upstreamSendLock.WaitAsync(sessionCts.Token).ConfigureAwait(false);
                try
                {
                    if (upstreamWs.State == WebSocketState.Open)
                        await upstreamWs.SendAsync(bytes, WebSocketMessageType.Text, true, sessionCts.Token).ConfigureAwait(false);
                }
                catch { }
                finally { upstreamSendLock.Release(); }
            }

            // 2. Build & send tamper-proof Gemini Live Setup packet directly from C#
            string serverRoutineSummary = await FetchServerRoutineSummaryCachedAsync(settings, creds.ChatSessionId, creds.AgentId).ConfigureAwait(false);
            string fullInstruction = BuildMiniPcSystemInstruction(
                creds.SystemInstruction,
                pulse,
                sysStats,
                audioState,
                camStatus,
                settings,
                serverRoutineSummary);

            var mergedTools = BuildMergedToolsNode(creds.RawToolsJson, schemaJson, settings.TikTokLiveMode);

            var setupPacket = new JsonObject
            {
                ["setup"] = new JsonObject
                {
                    ["model"] = "models/gemini-3.1-flash-live-preview",
                    ["systemInstruction"] = new JsonObject
                    {
                        ["parts"] = new JsonArray
                        {
                            new JsonObject { ["text"] = fullInstruction }
                        }
                    },
                    ["generationConfig"] = new JsonObject
                    {
                        ["responseModalities"] = new JsonArray("AUDIO"),
                        ["speechConfig"] = new JsonObject
                        {
                            ["voiceConfig"] = new JsonObject
                            {
                                ["prebuiltVoiceConfig"] = new JsonObject
                                {
                                    ["voiceName"] = string.IsNullOrWhiteSpace(creds.VoiceName) ? "Kore" : creds.VoiceName
                                }
                            }
                        }
                    },
                    ["tools"] = mergedTools,
                    ["inputAudioTranscription"] = new JsonObject(),
                    ["outputAudioTranscription"] = new JsonObject()
                }
            };

            await SafeSendUpstreamAsync(setupPacket.ToJsonString()).ConfigureAwait(false);
            s_activeSendLocal = SafeSendLocalAsync;
            s_activeSendUpstream = SafeSendUpstreamAsync;
            await SafeSendLocalAsync(JsonSerializer.Serialize(new
            {
                type = "funkiraSessionInfo",
                agentName = creds.AgentName,
                environment = pulse.Environment,
                cameraName = camStatus.CameraName,
                hasCamera = camStatus.HasCamera,
                micName = camStatus.MicSourceName,
                tikTokLiveMode = settings.TikTokLiveMode,
                tikTokHandle = settings.TikTokHandle ?? "",
                faceIdentity = LocalSenseBridge.CurrentFaceIdentity,
                worldState = new
                {
                    shopLevel = pulse.ShopLevel,
                    shopLevelTitle = pulse.ShopLevelTitle,
                    season = pulse.Season,
                    occasion = pulse.Occasion,
                    ordersToday = pulse.OrdersToday,
                    ordersWeek = pulse.OrdersWeek,
                    rosesCount = LocalSenseBridge.TikTokRosesCount
                }
            })).ConfigureAwait(false);

            byte[]? latestVisionFrame = null;
            var pendingSnapshots = new ConcurrentDictionary<string, TaskCompletionSource<byte[]?>>(StringComparer.Ordinal);

            async Task<byte[]?> RequestHighResBrowserSnapshotAsync()
            {
                string reqId = Guid.NewGuid().ToString("N");
                var tcs = new TaskCompletionSource<byte[]?>(TaskCreationOptions.RunContinuationsAsynchronously);
                pendingSnapshots[reqId] = tcs;
                try
                {
                    await SafeSendLocalAsync(JsonSerializer.Serialize(new
                    {
                        type = "requestHighResSnapshot",
                        reqId
                    })).ConfigureAwait(false);

                    using var timeoutCts = new CancellationTokenSource(2400);
                    using var reg = timeoutCts.Token.Register(() => tcs.TrySetResult(null));
                    return await tcs.Task.ConfigureAwait(false);
                }
                finally
                {
                    pendingSnapshots.TryRemove(reqId, out _);
                }
            }

            bool upstreamSetupComplete = false;

            // 3. Pump Local Browser -> Upstream Gemini Bridge
            var pumpClientToUpstream = Task.Run(async () =>
            {
                byte[] buf = new byte[64 * 1024];
                using var ms = new MemoryStream(128 * 1024);
                try
                {
                    while (localWs.State == WebSocketState.Open && !sessionCts.IsCancellationRequested)
                    {
                        var (msgType, rawText) = await ReceiveFullMessageAsync(localWs, buf, ms, sessionCts.Token).ConfigureAwait(false);
                        if (msgType == WebSocketMessageType.Close || rawText == null)
                            break;

                        try
                        {
                            using var doc = JsonDocument.Parse(rawText);
                            var root = doc.RootElement;

                            if (root.TryGetProperty("type", out var tEl))
                            {
                                string msgKind = tEl.GetString() ?? "";
                                if (msgKind == "highResSnapshotResponse")
                                {
                                    string reqId = SeelenfunkeLinuxApiClient.GetStr(root, "reqId");
                                    string b64 = SeelenfunkeLinuxApiClient.GetStr(root, "data");
                                    byte[]? decoded = null;
                                    if (!string.IsNullOrWhiteSpace(b64))
                                    {
                                        try { decoded = Convert.FromBase64String(b64); } catch { }
                                    }
                                    if (pendingSnapshots.TryRemove(reqId, out var waiter))
                                        waiter.TrySetResult(decoded);
                                    continue;
                                }
                                if (msgKind == "ping")
                                {
                                    await SafeSendLocalAsync("{\"type\":\"pong\"}").ConfigureAwait(false);
                                    continue;
                                }
                                if (msgKind == "personPresenceEvent" && Volatile.Read(ref upstreamSetupComplete))
                                {
                                    string reason = SeelenfunkeLinuxApiClient.GetStr(root, "reason", "session_start");
                                    string posLabel = SeelenfunkeLinuxApiClient.GetStr(root, "position", "in der Mitte vor dem Monitor");
                                    var liveSettings = LinuxVaultAndStorage.LoadSettings();

                                    TimeZoneInfo bTz;
                                    try { bTz = TimeZoneInfo.FindSystemTimeZoneById("Europe/Berlin"); }
                                    catch { bTz = TimeZoneInfo.Local; }
                                    DateTime nowLocal = TimeZoneInfo.ConvertTimeFromUtc(DateTime.UtcNow, bTz);
                                    var rEval = EvaluateWorkshopRoutine(nowLocal, pulse);

                                    if (reason == "wake_word")
                                    {
                                        await SafeSendLocalAsync(JsonSerializer.Serialize(new
                                        {
                                            type = "funkiraLocalCommand",
                                            command = "avatarExpress",
                                            emotion = "excited",
                                            gesture = "wave",
                                            durationSeconds = 5.5
                                        })).ConfigureAwait(false);

                                        var wakePacket = new JsonObject
                                        {
                                            ["realtimeInput"] = new JsonObject
                                            {
                                                ["text"] = "[OFFLINE-RUFNAME ERKANNT: Alina hat dich gerade in der Werkstatt mit 'Funkira' gerufen, während du hinten in deinem 3D-Atelier gewerkelt hast! Du läufst gerade nach vorne zur Scheibe. Antworte jetzt SOFORT kurz, herzlich und lebendig in 1 kurzem Satz (z.B. 'Ja Alina, bin schon da! Was gibt es?').]"
                                            }
                                        };
                                        await SafeSendUpstreamAsync(wakePacket.ToJsonString()).ConfigureAwait(false);
                                        continue;
                                    }

                                    if (reason == "idle_check")
                                    {
                                        if (liveSettings.TikTokLiveMode)
                                            continue;

                                        await SafeSendLocalAsync(JsonSerializer.Serialize(new
                                        {
                                            type = "funkiraLocalCommand",
                                            command = "avatarExpress",
                                            emotion = "curious",
                                            gesture = "wave",
                                            durationSeconds = 5.0
                                        })).ConfigureAwait(false);

                                        var idlePacket = new JsonObject
                                        {
                                            ["realtimeInput"] = new JsonObject
                                            {
                                                ["text"] = "[1-MINUTE-STILLE-CHECK: Seit 60 Sekunden war es ruhig. Frage Alina jetzt in EINEM ganz kurzen, charmanten Satz, ob sie gerade noch etwas braucht oder ob du hinten in deiner 3D-Werkstatt weiterwerkeln sollst, bis sie dich mit 'Funkira' ruft.]"
                                            }
                                        };
                                        await SafeSendUpstreamAsync(idlePacket.ToJsonString()).ConfigureAwait(false);
                                        continue;
                                    }

                                    // Daytime routine is displayed prominently in Gold on the monitor; only speak proactively late at night!
                                    if (liveSettings.TikTokLiveMode || !rEval.IsLateNightWarning)
                                    {
                                        if (reason == "session_start")
                                        {
                                            await SafeSendLocalAsync(JsonSerializer.Serialize(new
                                            {
                                                type = "funkiraLocalCommand",
                                                command = "avatarExpress",
                                                emotion = "happy",
                                                gesture = "wave",
                                                durationSeconds = 4.5
                                            })).ConfigureAwait(false);
                                        }
                                        continue;
                                    }

                                    bool allowTrigger = reason == "session_start" ||
                                                        (DateTime.UtcNow - s_lastProactiveReturnUtc).TotalMinutes >= 10.0;
                                    if (!allowTrigger)
                                        continue;

                                    if (reason != "session_start")
                                        s_lastProactiveReturnUtc = DateTime.UtcNow;

                                    await SafeSendLocalAsync(JsonSerializer.Serialize(new
                                    {
                                        type = "funkiraLocalCommand",
                                        command = "avatarExpress",
                                        emotion = rEval.RecommendedEmotion,
                                        gesture = rEval.RecommendedGesture,
                                        durationSeconds = 7.5
                                    })).ConfigureAwait(false);

                                    string cueText = $"[LIVE-KAMERA-ERKENNUNG: Du siehst Alina gerade live vor der Logitech-Kamera ({posLabel})! Es ist bereits {nowLocal:HH:mm} Uhr ({rEval.BlockTitle}). Sprich sie jetzt SOFORT von dir aus proaktiv in 2 kurzen, herzlichen aber bestimmten Sätzen auf die späte Uhrzeit und ihre Routine an und sag ihr ehrlich, ob es gerade sinnvoll ist noch in der Werkstatt zu sein (Offene Express-Bestellungen: {pulse.ExpressOrdersCount}).]";

                                    var proactivePacket = new JsonObject
                                    {
                                        ["realtimeInput"] = new JsonObject
                                        {
                                            ["text"] = cueText
                                        }
                                    };
                                    await SafeSendUpstreamAsync(proactivePacket.ToJsonString()).ConfigureAwait(false);
                                    continue;
                                }
                            }

                            // Cache latest JPEG vision frame in memory for instant fallback capture
                            if (root.TryGetProperty("realtimeInput", out var rt) &&
                                rt.TryGetProperty("video", out var vid) &&
                                vid.TryGetProperty("data", out var dEl) &&
                                dEl.ValueKind == JsonValueKind.String)
                            {
                                string? b64 = dEl.GetString();
                                if (!string.IsNullOrWhiteSpace(b64) && b64.Length < 2_500_000)
                                {
                                    try { latestVisionFrame = Convert.FromBase64String(b64); } catch { }
                                }
                            }

                            // Never let browser override setup packet or send frames before setupComplete
                            if (root.TryGetProperty("setup", out _) || !Volatile.Read(ref upstreamSetupComplete))
                                continue;
                        }
                        catch
                        {
                            continue;
                        }

                        await SafeSendUpstreamAsync(rawText).ConfigureAwait(false);
                    }
                }
                catch { }
                finally
                {
                    sessionCts.Cancel();
                }
            });

            // 4. Pump Upstream Gemini Bridge -> Local Browser (with transparent C# MCP & Server Tool interception!)
            var pumpUpstreamToClient = Task.Run(async () =>
            {
                byte[] buf = new byte[64 * 1024];
                using var ms = new MemoryStream(256 * 1024);
                var userTurnBuf = new StringBuilder();
                var aiTurnBuf = new StringBuilder();
                try
                {
                    while (upstreamWs.State == WebSocketState.Open && !sessionCts.IsCancellationRequested)
                    {
                        var (msgType, rawText) = await ReceiveFullMessageAsync(upstreamWs, buf, ms, sessionCts.Token).ConfigureAwait(false);
                        if (msgType == WebSocketMessageType.Close || rawText == null)
                        {
                            int closeCode = (int?)upstreamWs.CloseStatus ?? 0;
                            string closeReason = upstreamWs.CloseStatusDescription ?? "";
                            if (closeCode != 1000 && closeCode != 1001 && !sessionCts.IsCancellationRequested)
                            {
                                await SafeSendLocalAsync(JsonSerializer.Serialize(new
                                {
                                    type = "funkiraError",
                                    message = $"Live-Verbindung getrennt (Code {closeCode}): {closeReason}"
                                })).ConfigureAwait(false);
                            }
                            break;
                        }

                        bool isToolCall = false;
                        try
                        {
                            using var doc = JsonDocument.Parse(rawText);
                            var root = doc.RootElement;

                            if (root.TryGetProperty("setupComplete", out _))
                            {
                                Volatile.Write(ref upstreamSetupComplete, true);
                            }

                            if (root.TryGetProperty("serverContent", out var scNode) && scNode.ValueKind == JsonValueKind.Object)
                            {
                                if (scNode.TryGetProperty("inputTranscription", out var itNode) &&
                                    itNode.TryGetProperty("text", out var itTxt) &&
                                    itTxt.ValueKind == JsonValueKind.String)
                                {
                                    userTurnBuf.Append(itTxt.GetString());
                                }
                                if (scNode.TryGetProperty("outputTranscription", out var otNode) &&
                                    otNode.TryGetProperty("text", out var otTxt) &&
                                    otTxt.ValueKind == JsonValueKind.String)
                                {
                                    if (userTurnBuf.Length > 0)
                                    {
                                        RecordLocalTurn("ALINA", userTurnBuf.ToString());
                                        userTurnBuf.Clear();
                                    }
                                    aiTurnBuf.Append(otTxt.GetString());
                                }
                                if ((scNode.TryGetProperty("turnComplete", out var tcFlag) && tcFlag.ValueKind == JsonValueKind.True) ||
                                    (scNode.TryGetProperty("generationComplete", out var gcFlag) && gcFlag.ValueKind == JsonValueKind.True))
                                {
                                    if (userTurnBuf.Length > 0)
                                    {
                                        RecordLocalTurn("ALINA", userTurnBuf.ToString());
                                        userTurnBuf.Clear();
                                    }
                                    if (aiTurnBuf.Length > 0)
                                    {
                                        RecordLocalTurn("FUNKIRA", aiTurnBuf.ToString());
                                        aiTurnBuf.Clear();
                                    }
                                }
                            }

                            if (root.TryGetProperty("toolCall", out var tcEl) &&
                                tcEl.TryGetProperty("functionCalls", out var fnCalls) &&
                                fnCalls.ValueKind == JsonValueKind.Array)
                            {
                                isToolCall = true;
                                var callsSnapshot = fnCalls.Clone();
                                _ = Task.Run(async () =>
                                {
                                    try
                                    {
                                        var toolNames = new List<string>();
                                        foreach (var c in callsSnapshot.EnumerateArray())
                                            toolNames.Add(SeelenfunkeLinuxApiClient.GetStr(c, "name", "tool"));

                                        await SafeSendLocalAsync(JsonSerializer.Serialize(new
                                        {
                                            type = "funkiraToolStart",
                                            tools = toolNames
                                        })).ConfigureAwait(false);

                                        var currentSettings = LinuxVaultAndStorage.LoadSettings();
                                        var responsesArr = new JsonArray();
                                        foreach (var call in callsSnapshot.EnumerateArray())
                                        {
                                            string callId = SeelenfunkeLinuxApiClient.GetStr(call, "id");
                                            string callName = SeelenfunkeLinuxApiClient.GetStr(call, "name");
                                            JsonElement callArgs = call.TryGetProperty("args", out var aEl) ? aEl : default;

                                            if (!IsToolAllowedInPrivacyMode(callName, currentSettings.TikTokLiveMode, LocalSenseBridge.CurrentFaceIdentity))
                                            {
                                                responsesArr.Add(new JsonObject
                                                {
                                                    ["id"] = callId,
                                                    ["name"] = callName,
                                                    ["response"] = new JsonObject
                                                    {
                                                        ["result"] = new JsonObject
                                                        {
                                                            ["status"] = "blocked",
                                                            ["message"] = currentSettings.TikTokLiveMode
                                                                ? "Zugriff gesperrt: TikTok-Live-Datenschutz-Sandbox aktiv (keine internen Geschäfts- oder Kundendaten)."
                                                                : "Zugriff gesperrt: Besucher-Schutz aktiv (Chefin-Gesicht Alina wurde nicht vor der Kamera verifiziert)."
                                                        }
                                                    }
                                                });
                                                continue;
                                            }

                                            if (callName.StartsWith("minipc_", StringComparison.OrdinalIgnoreCase))
                                            {
                                                var (localRes, uiEvt) = await ExecuteLocalMiniPcToolAsync(
                                                    callName,
                                                    callArgs,
                                                    RequestHighResBrowserSnapshotAsync,
                                                    latestVisionFrame).ConfigureAwait(false);

                                                if (uiEvt != null)
                                                    await SafeSendLocalAsync(uiEvt.ToJsonString()).ConfigureAwait(false);

                                                responsesArr.Add(new JsonObject
                                                {
                                                    ["id"] = callId,
                                                    ["name"] = callName,
                                                    ["response"] = new JsonObject
                                                    {
                                                        ["result"] = localRes
                                                    }
                                                });
                                            }
                                            else
                                            {
                                                string srvJson = await SeelenfunkeLinuxApiClient.ExecuteServerToolAsync(
                                                    LinuxVaultAndStorage.LoadSettings(),
                                                    callName,
                                                    callArgs,
                                                    creds.ChatSessionId,
                                                    creds.AgentId).ConfigureAwait(false);

                                                JsonNode? parsedSrv = null;
                                                try { parsedSrv = JsonNode.Parse(srvJson); } catch { }

                                                responsesArr.Add(new JsonObject
                                                {
                                                    ["id"] = callId,
                                                    ["name"] = callName,
                                                    ["response"] = new JsonObject
                                                    {
                                                        ["result"] = parsedSrv ?? JsonValue.Create(srvJson)
                                                    }
                                                });
                                            }
                                        }

                                        var toolRespPacket = new JsonObject
                                        {
                                            ["toolResponse"] = new JsonObject
                                            {
                                                ["functionResponses"] = responsesArr
                                            }
                                        };

                                        await SafeSendUpstreamAsync(toolRespPacket.ToJsonString()).ConfigureAwait(false);
                                        await SafeSendLocalAsync(JsonSerializer.Serialize(new
                                        {
                                            type = "funkiraToolDone",
                                            tools = toolNames
                                        })).ConfigureAwait(false);
                                    }
                                    catch (Exception toolEx)
                                    {
                                        await SafeSendLocalAsync(JsonSerializer.Serialize(new
                                        {
                                            type = "funkiraToolDone",
                                            error = toolEx.Message
                                        })).ConfigureAwait(false);
                                    }
                                });
                            }
                        }
                        catch { }

                        if (!isToolCall)
                        {
                            await SafeSendLocalAsync(rawText).ConfigureAwait(false);
                        }
                    }
                }
                catch { }
                finally
                {
                    sessionCts.Cancel();
                }
            });

            await Task.WhenAny(pumpClientToUpstream, pumpUpstreamToClient).ConfigureAwait(false);
            if (ReferenceEquals(s_activeSendLocal, (Func<string, Task>)SafeSendLocalAsync)) s_activeSendLocal = null;
            if (ReferenceEquals(s_activeSendUpstream, (Func<string, Task>)SafeSendUpstreamAsync)) s_activeSendUpstream = null;

            try
            {
                if (upstreamWs.State == WebSocketState.Open)
                    await upstreamWs.CloseOutputAsync(WebSocketCloseStatus.NormalClosure, "user_stopped_live_mode", CancellationToken.None).ConfigureAwait(false);
            }
            catch { }

            try
            {
                if (localWs.State == WebSocketState.Open)
                {
                    await localWs.CloseOutputAsync(WebSocketCloseStatus.NormalClosure, "closed", CancellationToken.None).ConfigureAwait(false);
                    await Task.Delay(180).ConfigureAwait(false);
                }
            }
            catch { }

            sessionCts.Cancel();
        }

        private static async Task<(WebSocketMessageType MessageType, string? Text)> ReceiveFullMessageAsync(
            WebSocket ws,
            byte[] buffer,
            MemoryStream ms,
            CancellationToken ct)
        {
            ms.SetLength(0);
            while (true)
            {
                var res = await ws.ReceiveAsync(buffer, ct).ConfigureAwait(false);
                if (res.MessageType == WebSocketMessageType.Close)
                    return (WebSocketMessageType.Close, null);

                if (ms.Length + res.Count > MaxWsMessageBytes)
                    throw new InvalidDataException("WebSocket message exceeded safety cap.");

                ms.Write(buffer, 0, res.Count);
                if (res.EndOfMessage)
                {
                    return (res.MessageType, Encoding.UTF8.GetString(ms.GetBuffer(), 0, (int)ms.Length));
                }
            }
        }
    }
}
