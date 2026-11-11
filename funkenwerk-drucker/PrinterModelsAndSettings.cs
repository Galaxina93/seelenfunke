using System;
using System.Collections.Generic;
using System.IO;
using System.Text.Json;
using System.Windows;
using System.Windows.Media;
using System.Windows.Media.Imaging;

namespace FunkenwerkDrucker
{
    public enum WorkspaceMode
    {
        Print,
        Scan,
        Copy,
        Queue
    }

    public enum PrintColorMode
    {
        Color,
        Grayscale
    }

    public enum PrintDuplexMode
    {
        OneSided,
        LongEdge,
        ShortEdge
    }

    public enum PrintPaperFormat
    {
        A4,
        A5,
        Label100x150,
        Photo10x15
    }

    public enum PrintOrientationMode
    {
        Auto,
        Portrait,
        Landscape
    }

    public enum PrintScaleMode
    {
        FitPrintableArea,
        ActualSize100
    }

    public enum PrintPageRangeMode
    {
        All,
        Current,
        Custom
    }

    public enum ScanSourceMode
    {
        Flatbed,
        AdfSimplex,
        AdfDuplex
    }

    public enum ScanColorMode
    {
        Color,
        Grayscale,
        ReceiptBw
    }

    public enum ScanProtocolMode
    {
        Auto,
        EsclLan,
        WiaLocal
    }

    public sealed class DiscoveredPrinterInfo
    {
        public string QueueName { get; set; } = "";
        public string DisplayName { get; set; } = "";
        public string DriverName { get; set; } = "";
        public string PortName { get; set; } = "";
        public bool IsWsdPort { get; set; }
        public bool IsVirtualPrinter { get; set; }
        public bool IsDefault { get; set; }
        public bool SpoolerReportsOffline { get; set; }
        public string SpoolerStatusText { get; set; } = "Bereit";
        public int ActiveJobCount { get; set; }

        public string ResolvedHostOrIp { get; set; } = "";
        public string ResolvedIpAddress { get; set; } = "";
        public bool LanHttpOnline { get; set; }
        public bool LanIppOnline { get; set; }
        public bool LanEsclOnline { get; set; }
        public bool LanRaw9100Online { get; set; }

        public int? BlackInkPercent { get; set; }
        public int? ColorInkPercent { get; set; }
        public string LiveDeviceState { get; set; } = "";

        public bool IsEffectivelyOnline =>
            IsVirtualPrinter || LanHttpOnline || LanIppOnline || (!SpoolerReportsOffline && !IsWsdPort);

        public string EffectiveStatusLabel
        {
            get
            {
                if (IsVirtualPrinter) return "Virtuell · Bereit";
                if (LanHttpOnline || LanIppOnline)
                {
                    string extra = !string.IsNullOrWhiteSpace(LiveDeviceState) ? $" · {LiveDeviceState}" : "";
                    return SpoolerReportsOffline ? $"LAN Direkt aktiv{extra}" : $"Online{extra}";
                }
                if (!SpoolerReportsOffline) return "Bereit";
                return string.IsNullOrWhiteSpace(ResolvedIpAddress)
                    ? "Ausgeschaltet / Schlafmodus"
                    : $"Ausgeschaltet · {ResolvedIpAddress}";
            }
        }

        public Brush StatusDotBrush
        {
            get
            {
                if (IsVirtualPrinter || LanHttpOnline || LanIppOnline || !SpoolerReportsOffline)
                {
                    var b = new SolidColorBrush(Color.FromRgb(16, 185, 129)); // #10B981
                    b.Freeze();
                    return b;
                }
                var off = new SolidColorBrush(Color.FromRgb(245, 158, 11)); // #F59E0B (Asleep/Standby)
                off.Freeze();
                return off;
            }
        }

        public string BadgeText =>
            IsVirtualPrinter ? "PDF/APP" : (LanIppOnline || LanHttpOnline ? "LAN" : (IsWsdPort ? "WLAN" : "USB"));
    }

    public sealed class PrintQueueJobItem
    {
        public int JobId { get; set; }
        public string DocumentName { get; set; } = "";
        public string StatusText { get; set; } = "";
        public string PagesText { get; set; } = "";
        public string SubmittedText { get; set; } = "";
    }

    public sealed class DocumentPageItem
    {
        public string Id { get; set; } = Guid.NewGuid().ToString("N");
        public int Index { get; set; }
        public string SourceKind { get; set; } = "PDF"; // PDF, Scan, Bild
        public int SourcePdfPageIndex { get; set; } = -1;
        public byte[]? SourcePdfBytes { get; set; }

        public required BitmapSource OriginalBitmap { get; set; }
        public required BitmapSource ProcessedBitmap { get; set; }

        public int RotationDegrees { get; set; } = 0; // 0, 90, 180, 270
        public bool EnhanceReceiptContrast { get; set; }
        public bool ForceBwText { get; set; }
        public Rect NormalizedCrop { get; set; } = new Rect(0, 0, 1, 1);

        public double WidthMm { get; set; } = 210.0;
        public double HeightMm { get; set; } = 297.0;

        public string Title => $"Seite {Index + 1}";
        public string Badge => EnhanceReceiptContrast ? $"{SourceKind} · Kontrast" : SourceKind;
    }

    public sealed class FunkenwerkDruckerSettings
    {
        public string PreferredPrinterName { get; set; } = "";
        public string CustomPrinterIp { get; set; } = "";
        public bool IsDarkMode { get; set; } = true;
        public int ScanDpi { get; set; } = 300;
        public ScanSourceMode LastScanSource { get; set; } = ScanSourceMode.Flatbed;
        public ScanColorMode LastScanColor { get; set; } = ScanColorMode.Color;
        public PrintColorMode LastPrintColor { get; set; } = PrintColorMode.Color;
        public PrintDuplexMode LastDuplex { get; set; } = PrintDuplexMode.OneSided;
        public PrintPaperFormat LastPaperFormat { get; set; } = PrintPaperFormat.A4;
        public PrintScaleMode LastScaleMode { get; set; } = PrintScaleMode.FitPrintableArea;

        public static string DataDirectory
        {
            get
            {
                string dir = Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData), "FunkenwerkDrucker");
                Directory.CreateDirectory(dir);
                return dir;
            }
        }

        private static string SettingsFilePath => Path.Combine(DataDirectory, "settings.json");

        public static FunkenwerkDruckerSettings Load()
        {
            try
            {
                if (File.Exists(SettingsFilePath))
                {
                    string json = File.ReadAllText(SettingsFilePath);
                    var loaded = JsonSerializer.Deserialize<FunkenwerkDruckerSettings>(json);
                    if (loaded != null) return loaded;
                }
            }
            catch { }
            return new FunkenwerkDruckerSettings();
        }

        public void Save()
        {
            try
            {
                string tmp = SettingsFilePath + "." + Guid.NewGuid().ToString("N") + ".tmp";
                string json = JsonSerializer.Serialize(this, new JsonSerializerOptions { WriteIndented = true });
                File.WriteAllText(tmp, json);
                File.Move(tmp, SettingsFilePath, overwrite: true);
            }
            catch { }
        }
    }
}
