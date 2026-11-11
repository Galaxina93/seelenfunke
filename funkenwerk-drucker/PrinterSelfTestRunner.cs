using System;
using System.Collections.Generic;
using System.IO;
using System.Threading.Tasks;
using System.Windows;

namespace FunkenwerkDrucker
{
    public static class PrinterSelfTestRunner
    {
        public static async Task<bool> RunAllAsync()
        {
            int passed = 0;

            void Check(string name, bool condition)
            {
                if (!condition)
                    throw new InvalidOperationException($"Self-Test fehlgeschlagen: {name}");
                Console.Error.WriteLine($"[PASS] {name}");
                passed++;
            }

            // 1. Private LAN Security Guard (Blocks public Internet IPs)
            Check("01. Private LAN Guard erlaubt 192.168.188.26 & blockt 8.8.8.8",
                PrinterHardwareEngine.IsPrivateLanIp(System.Net.IPAddress.Parse("192.168.188.26")) &&
                !PrinterHardwareEngine.IsPrivateLanIp(System.Net.IPAddress.Parse("8.8.8.8")));

            // 2. HP LEDM Consumable XML Parser (HP 303 K + CMY without Instant Ink)
            string sampleHpXml = """
                <?xml version="1.0" encoding="UTF-8"?>
                <ccdyn:ConsumableConfigDyn xmlns:dd="http://www.hp.com/schemas/imaging/con/dictionaries/1.0/" xmlns:ccdyn="http://www.hp.com/schemas/imaging/con/ledm/consumableconfigdyn/2007/11/19">
                  <ccdyn:ConsumableInfo>
                    <dd:ConsumableLabelCode>K</dd:ConsumableLabelCode>
                    <dd:ConsumableState>ok</dd:ConsumableState>
                    <dd:ConsumablePercentageLevelRemaining>74</dd:ConsumablePercentageLevelRemaining>
                  </ccdyn:ConsumableInfo>
                  <ccdyn:ConsumableInfo>
                    <dd:ConsumableLabelCode>CMY</dd:ConsumableLabelCode>
                    <dd:ConsumableState>ok</dd:ConsumableState>
                    <dd:ConsumablePercentageLevelRemaining>61</dd:ConsumablePercentageLevelRemaining>
                  </ccdyn:ConsumableInfo>
                </ccdyn:ConsumableConfigDyn>
                """;
            var (kPct, cmyPct) = PrinterHardwareEngine.ParseHpConsumableXml(sampleHpXml);
            Check("02. HP LEDM XML Tintenstand-Parser (K=74%, CMY=61%)", kPct == 74 && cmyPct == 61);

            // 3. Test Page Generation + Rotation + Crop + Receipt Contrast + Grayscale
            var page1 = DocumentAndPrintEngine.CreateCalibrationTestPage();
            Check("03. Kalibrierungs-Prüfblatt 300 DPI erzeugt", page1.ProcessedBitmap.PixelWidth >= 2400 && page1.ProcessedBitmap.PixelHeight >= 3400);

            page1.RotationDegrees = 90;
            page1.EnhanceReceiptContrast = true;
            page1.NormalizedCrop = new Rect(0.05, 0.05, 0.90, 0.90);
            DocumentAndPrintEngine.RebuildProcessedBitmap(page1);
            Check("04. Bild-Pipeline (90°-Rotation, Zuschnitt & Beleg-Kontrast)",
                page1.ProcessedBitmap.PixelWidth > page1.ProcessedBitmap.PixelHeight);

            // 4. Multi-Page PDF Export & Reload Roundtrip
            var page2 = DocumentAndPrintEngine.CreateCalibrationTestPage();
            string tempPdf = Path.Combine(Path.GetTempPath(), $"fw_drucker_selftest_{Guid.NewGuid():N}.pdf");
            try
            {
                await DocumentAndPrintEngine.ExportPagesToPdfAsync(new List<DocumentPageItem> { page1, page2 }, tempPdf, forceGrayscale: true);
                Check("05. Mehrseiten-PDF Export (2 Seiten, Graustufen)", File.Exists(tempPdf) && new FileInfo(tempPdf).Length > 4096);

                var reloaded = await DocumentAndPrintEngine.LoadFilesAsync(new[] { tempPdf });
                Check("06. Windows.Data.Pdf Reload Roundtrip (2 Seiten)", reloaded.Count == 2);
            }
            finally
            {
                try { if (File.Exists(tempPdf)) File.Delete(tempPdf); } catch { }
            }

            // 5. Page Range Parser
            var range = DocumentAndPrintEngine.ResolveSelectedPageIndices(6, 2, PrintPageRangeMode.Custom, "1-2, 5");
            Check("07. Seitenbereich-Parser ('1-2, 5' -> [0,1,4])", range.Count == 3 && range[0] == 0 && range[1] == 1 && range[2] == 4);

            // 6. Local Printer Discovery
            var printers = await PrinterHardwareEngine.DiscoverPrintersAsync();
            Check("08. Windows PrintServer Drucker-Erkennung", printers.Count >= 1);

            Console.Error.WriteLine($"FUNKENWERK DRUCKER SELF-TEST: {passed}/8 TESTS BESTANDEN.");
            return true;
        }
    }
}
