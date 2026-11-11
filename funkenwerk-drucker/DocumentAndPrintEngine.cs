using System;
using System.Collections.Generic;
using System.Globalization;
using System.IO;
using System.Linq;
using System.Net.Http;
using System.Printing;
using System.Text;
using System.Threading;
using System.Threading.Tasks;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Documents;
using System.Windows.Markup;
using System.Windows.Media;
using System.Windows.Media.Imaging;
using PdfSharp.Drawing;
using PdfSharp.Pdf;
using Windows.Data.Pdf;
using Windows.Storage.Streams;
using IOPath = System.IO.Path;

namespace FunkenwerkDrucker
{
    public static class DocumentAndPrintEngine
    {
        public static readonly string[] SupportedExtensions =
        {
            ".pdf", ".png", ".jpg", ".jpeg", ".webp", ".bmp", ".tif", ".tiff"
        };

        public static async Task<List<DocumentPageItem>> LoadFilesAsync(IEnumerable<string> filePaths)
        {
            var result = new List<DocumentPageItem>();

            foreach (string path in filePaths)
            {
                if (string.IsNullOrWhiteSpace(path) || !File.Exists(path)) continue;
                string ext = IOPath.GetExtension(path).ToLowerInvariant();

                if (ext == ".pdf")
                {
                    byte[] pdfBytes = await File.ReadAllBytesAsync(path);
                    if (pdfBytes.Length < 8) continue;

                    using var ras = new InMemoryRandomAccessStream();
                    using (var writer = new DataWriter(ras.GetOutputStreamAt(0)))
                    {
                        writer.WriteBytes(pdfBytes);
                        await writer.StoreAsync();
                    }

                    var winPdf = await Windows.Data.Pdf.PdfDocument.LoadFromStreamAsync(ras);
                    uint count = winPdf.PageCount;

                    for (uint i = 0; i < count; i++)
                    {
                        using var page = winPdf.GetPage(i);
                        double wDip = page.Size.Width;   // 1/96 inch
                        double hDip = page.Size.Height;
                        double wMm = wDip / 96.0 * 25.4;
                        double hMm = hDip / 96.0 * 25.4;

                        // Render crisp 180 DPI preview bitmap
                        uint renderW = (uint)Math.Clamp(Math.Round(wDip * (180.0 / 96.0)), 400, 3200);
                        uint renderH = (uint)Math.Clamp(Math.Round(hDip * (180.0 / 96.0)), 400, 4400);

                        using var pageStream = new InMemoryRandomAccessStream();
                        var opts = new PdfPageRenderOptions
                        {
                            DestinationWidth = renderW,
                            DestinationHeight = renderH,
                            BackgroundColor = Windows.UI.Color.FromArgb(255, 255, 255, 255)
                        };
                        await page.RenderToStreamAsync(pageStream, opts);

                        pageStream.Seek(0);
                        using var netStream = pageStream.AsStreamForRead();
                        using var ms = new MemoryStream();
                        await netStream.CopyToAsync(ms);
                        var bmp = PrinterHardwareEngine.DecodeBytesToFrozenBitmap(ms.ToArray());

                        result.Add(new DocumentPageItem
                        {
                            SourceKind = "PDF",
                            SourcePdfPageIndex = (int)i,
                            SourcePdfBytes = pdfBytes,
                            OriginalBitmap = bmp,
                            ProcessedBitmap = bmp,
                            WidthMm = wMm,
                            HeightMm = hMm
                        });
                    }
                }
                else if (SupportedExtensions.Contains(ext))
                {
                    byte[] bytes = await File.ReadAllBytesAsync(path);
                    using var ms = new MemoryStream(bytes);
                    var decoder = BitmapDecoder.Create(ms, BitmapCreateOptions.PreservePixelFormat, BitmapCacheOption.OnLoad);

                    foreach (var frame in decoder.Frames)
                    {
                        BitmapSource frozen = frame;
                        if (!frozen.IsFrozen)
                        {
                            var clone = new WriteableBitmap(frame);
                            clone.Freeze();
                            frozen = clone;
                        }

                        double dpiX = frozen.DpiX > 10 ? frozen.DpiX : 300.0;
                        double dpiY = frozen.DpiY > 10 ? frozen.DpiY : 300.0;
                        double wMm = frozen.PixelWidth / dpiX * 25.4;
                        double hMm = frozen.PixelHeight / dpiY * 25.4;

                        result.Add(new DocumentPageItem
                        {
                            SourceKind = "Bild",
                            OriginalBitmap = frozen,
                            ProcessedBitmap = frozen,
                            WidthMm = wMm,
                            HeightMm = hMm
                        });
                    }
                }
            }

            return result;
        }

        public static DocumentPageItem CreateScannedPageItem(BitmapSource rawScan, int dpi, bool receiptContrast, bool forceBw)
        {
            double effDpi = dpi > 50 ? dpi : (rawScan.DpiX > 50 ? rawScan.DpiX : 300.0);
            var item = new DocumentPageItem
            {
                SourceKind = "Scan",
                OriginalBitmap = rawScan,
                ProcessedBitmap = rawScan,
                EnhanceReceiptContrast = receiptContrast,
                ForceBwText = forceBw,
                WidthMm = rawScan.PixelWidth / effDpi * 25.4,
                HeightMm = rawScan.PixelHeight / effDpi * 25.4
            };
            RebuildProcessedBitmap(item);
            return item;
        }

        public static void RebuildProcessedBitmap(DocumentPageItem item)
        {
            BitmapSource current = item.OriginalBitmap;

            // 1. Rotation
            int rot = ((item.RotationDegrees % 360) + 360) % 360;
            if (rot == 90 || rot == 180 || rot == 270)
            {
                var tb = new TransformedBitmap();
                tb.BeginInit();
                tb.Source = current;
                tb.Transform = new RotateTransform(rot);
                tb.EndInit();
                tb.Freeze();
                current = tb;
            }

            // 2. Normalized Crop
            Rect c = item.NormalizedCrop;
            if (c.X > 0.002 || c.Y > 0.002 || c.Width < 0.996 || c.Height < 0.996)
            {
                int px = Math.Clamp((int)Math.Round(c.X * current.PixelWidth), 0, Math.Max(0, current.PixelWidth - 16));
                int py = Math.Clamp((int)Math.Round(c.Y * current.PixelHeight), 0, Math.Max(0, current.PixelHeight - 16));
                int pw = Math.Clamp((int)Math.Round(c.Width * current.PixelWidth), 16, current.PixelWidth - px);
                int ph = Math.Clamp((int)Math.Round(c.Height * current.PixelHeight), 16, current.PixelHeight - py);

                var cropped = new CroppedBitmap(current, new Int32Rect(px, py, pw, ph));
                cropped.Freeze();
                current = cropped;
            }

            // 3. Receipt Contrast / Clean White Paper / B&W Enhancement
            if (item.EnhanceReceiptContrast || item.ForceBwText)
            {
                current = ApplyDocumentEnhancement(current, item.EnhanceReceiptContrast, item.ForceBwText);
            }

            item.ProcessedBitmap = current;
        }

        public static BitmapSource ConvertToGrayscaleBitmap(BitmapSource src)
        {
            return ApplyDocumentEnhancement(src, whitenPaper: false, pureGrayscale: true);
        }

        private static BitmapSource ApplyDocumentEnhancement(BitmapSource src, bool whitenPaper, bool pureGrayscale)
        {
            var bgra = src.Format == PixelFormats.Bgra32
                ? src
                : new FormatConvertedBitmap(src, PixelFormats.Bgra32, null, 0);

            int w = bgra.PixelWidth;
            int h = bgra.PixelHeight;
            int stride = w * 4;
            byte[] pixels = new byte[h * stride];
            bgra.CopyPixels(pixels, stride, 0);

            for (int i = 0; i < pixels.Length; i += 4)
            {
                byte b = pixels[i];
                byte g = pixels[i + 1];
                byte r = pixels[i + 2];
                byte a = pixels[i + 3];

                if (a < 16)
                {
                    pixels[i] = 255;
                    pixels[i + 1] = 255;
                    pixels[i + 2] = 255;
                    pixels[i + 3] = 255;
                    continue;
                }

                // ITU-R BT.709 integer luminance (0..255)
                int lum = (r * 54 + g * 183 + b * 19) >> 8;

                if (whitenPaper)
                {
                    // Stretch shadow paper (>202 -> pure 255 white, saves ink!), deepen dark ink (<95 -> crisp black)
                    int stretched;
                    if (lum >= 202) stretched = 255;
                    else if (lum <= 68) stretched = 0;
                    else stretched = ((lum - 68) * 255) / (202 - 68);

                    if (pureGrayscale)
                    {
                        byte v = (byte)stretched;
                        pixels[i] = v;
                        pixels[i + 1] = v;
                        pixels[i + 2] = v;
                    }
                    else
                    {
                        if (lum >= 202)
                        {
                            pixels[i] = 255;
                            pixels[i + 1] = 255;
                            pixels[i + 2] = 255;
                        }
                        else
                        {
                            double factor = lum > 0 ? (double)stretched / lum : 0.0;
                            pixels[i] = (byte)Math.Clamp((int)(b * factor), 0, 255);
                            pixels[i + 1] = (byte)Math.Clamp((int)(g * factor), 0, 255);
                            pixels[i + 2] = (byte)Math.Clamp((int)(r * factor), 0, 255);
                        }
                    }
                }
                else if (pureGrayscale)
                {
                    byte v = (byte)lum;
                    pixels[i] = v;
                    pixels[i + 1] = v;
                    pixels[i + 2] = v;
                }
            }

            double dpiX = src.DpiX > 10 ? src.DpiX : 300;
            double dpiY = src.DpiY > 10 ? src.DpiY : 300;
            var outBmp = BitmapSource.Create(w, h, dpiX, dpiY, PixelFormats.Bgra32, null, pixels, stride);
            outBmp.Freeze();
            return outBmp;
        }

        // =====================================================================
        // MEHRSEITIGER PDF- & BILD-EXPORT (100 % OHNE HP-KONTO)
        // =====================================================================
        public static async Task ExportPagesToPdfAsync(IReadOnlyList<DocumentPageItem> pages, string outputPath, bool forceGrayscale = false)
        {
            if (pages.Count == 0)
                throw new InvalidOperationException("Keine Seiten zum Speichern vorhanden.");

            byte[] pdfBytes = await Task.Run(() => BuildPdfBytesFromPages(pages, forceGrayscale));

            string dir = IOPath.GetDirectoryName(outputPath) ?? Environment.CurrentDirectory;
            Directory.CreateDirectory(dir);
            string tmp = IOPath.Combine(dir, $".fw_print_{Guid.NewGuid():N}.tmp");
            await File.WriteAllBytesAsync(tmp, pdfBytes);
            File.Move(tmp, outputPath, overwrite: true);
        }

        public static byte[] BuildPdfBytesFromPages(IReadOnlyList<DocumentPageItem> pages, bool forceGrayscale = false)
        {
            using var doc = new PdfSharp.Pdf.PdfDocument();
            doc.Info.Title = "Funkenwerk Dokument";
            doc.Info.Creator = "Funkenwerk Drucker";

            foreach (var p in pages)
            {
                BitmapSource bmp = forceGrayscale
                    ? ConvertToGrayscaleBitmap(p.ProcessedBitmap)
                    : p.ProcessedBitmap;

                bool isLandscape = bmp.PixelWidth > bmp.PixelHeight * 1.05;
                var pdfPage = doc.AddPage();

                // Standard A4 points (595 x 842 pt) matching orientation
                pdfPage.Width = XUnit.FromPoint(isLandscape ? 841.89 : 595.28);
                pdfPage.Height = XUnit.FromPoint(isLandscape ? 595.28 : 841.89);

                byte[] jpgBytes = EncodeBitmapAsJpeg(bmp, quality: 92);
                using var imgStream = new MemoryStream(jpgBytes);
                using var xImg = XImage.FromStream(imgStream);
                using var gfx = XGraphics.FromPdfPage(pdfPage);

                double pw = pdfPage.Width.Point;
                double ph = pdfPage.Height.Point;
                double scale = Math.Min(pw / bmp.PixelWidth, ph / bmp.PixelHeight);
                double drawW = bmp.PixelWidth * scale;
                double drawH = bmp.PixelHeight * scale;
                double dx = (pw - drawW) / 2.0;
                double dy = (ph - drawH) / 2.0;

                gfx.DrawImage(xImg, dx, dy, drawW, drawH);
            }

            using var outStream = new MemoryStream();
            doc.Save(outStream, closeStream: false);
            return outStream.ToArray();
        }

        public static byte[] EncodeBitmapAsPng(BitmapSource bmp)
        {
            var enc = new PngBitmapEncoder();
            enc.Frames.Add(BitmapFrame.Create(bmp));
            using var ms = new MemoryStream();
            enc.Save(ms);
            return ms.ToArray();
        }

        public static byte[] EncodeBitmapAsJpeg(BitmapSource bmp, int quality = 92)
        {
            var enc = new JpegBitmapEncoder { QualityLevel = Math.Clamp(quality, 40, 100) };
            enc.Frames.Add(BitmapFrame.Create(bmp));
            using var ms = new MemoryStream();
            enc.Save(ms);
            return ms.ToArray();
        }

        // =====================================================================
        // SEITENBEREICH PARSER ("1-3, 5")
        // =====================================================================
        public static List<int> ResolveSelectedPageIndices(
            int totalPages,
            int currentIndex,
            PrintPageRangeMode mode,
            string? customRangeText)
        {
            var indices = new List<int>();
            if (totalPages <= 0) return indices;

            if (mode == PrintPageRangeMode.All)
            {
                for (int i = 0; i < totalPages; i++) indices.Add(i);
                return indices;
            }

            if (mode == PrintPageRangeMode.Current)
            {
                indices.Add(Math.Clamp(currentIndex, 0, totalPages - 1));
                return indices;
            }

            var set = new SortedSet<int>();
            string raw = customRangeText ?? "";
            foreach (string token in raw.Split(new[] { ',', ';' }, StringSplitOptions.RemoveEmptyEntries))
            {
                string part = token.Trim();
                int dash = part.IndexOf('-');
                if (dash > 0)
                {
                    if (int.TryParse(part.Substring(0, dash).Trim(), out int a) &&
                        int.TryParse(part.Substring(dash + 1).Trim(), out int b))
                    {
                        int start = Math.Max(1, Math.Min(a, b));
                        int end = Math.Min(totalPages, Math.Max(a, b));
                        for (int p = start; p <= end; p++) set.Add(p - 1);
                    }
                }
                else if (int.TryParse(part, out int single) && single >= 1 && single <= totalPages)
                {
                    set.Add(single - 1);
                }
            }

            if (set.Count == 0)
            {
                for (int i = 0; i < totalPages; i++) indices.Add(i);
                return indices;
            }

            return set.ToList();
        }

        // =====================================================================
        // HIGH-DPI DRUCK-AUSFÜHRUNG (WINDOWS SPOOLER + DIREKT-IPP FALLBACK)
        // =====================================================================
        public static (double WidthMm, double HeightMm, PageMediaSizeName MediaName) GetPaperDimensionsMm(PrintPaperFormat fmt)
        {
            return fmt switch
            {
                PrintPaperFormat.A5 => (148.0, 210.0, PageMediaSizeName.ISOA5),
                PrintPaperFormat.Label100x150 => (100.0, 150.0, PageMediaSizeName.NorthAmerica4x6),
                PrintPaperFormat.Photo10x15 => (100.0, 150.0, PageMediaSizeName.NorthAmerica4x6),
                _ => (210.0, 297.0, PageMediaSizeName.ISOA4)
            };
        }

        private static async Task<BitmapSource> GetHighResPageBitmapForPrintAsync(DocumentPageItem page, bool grayscale)
        {
            BitmapSource bmp = page.ProcessedBitmap;

            // Re-render vector PDF page at crisp 300 DPI if uncropped
            if (page.SourceKind == "PDF" &&
                page.SourcePdfBytes != null &&
                page.SourcePdfPageIndex >= 0 &&
                page.NormalizedCrop.X <= 0.002 &&
                page.NormalizedCrop.Y <= 0.002 &&
                page.NormalizedCrop.Width >= 0.996 &&
                page.NormalizedCrop.Height >= 0.996)
            {
                try
                {
                    using var ras = new InMemoryRandomAccessStream();
                    using (var writer = new DataWriter(ras.GetOutputStreamAt(0)))
                    {
                        writer.WriteBytes(page.SourcePdfBytes);
                        await writer.StoreAsync();
                    }
                    var winPdf = await Windows.Data.Pdf.PdfDocument.LoadFromStreamAsync(ras);
                    if (page.SourcePdfPageIndex < winPdf.PageCount)
                    {
                        using var p = winPdf.GetPage((uint)page.SourcePdfPageIndex);
                        uint rw = (uint)Math.Clamp(Math.Round(p.Size.Width * (300.0 / 96.0)), 600, 4960);
                        uint rh = (uint)Math.Clamp(Math.Round(p.Size.Height * (300.0 / 96.0)), 600, 7016);
                        using var ps = new InMemoryRandomAccessStream();
                        await p.RenderToStreamAsync(ps, new PdfPageRenderOptions
                        {
                            DestinationWidth = rw,
                            DestinationHeight = rh,
                            BackgroundColor = Windows.UI.Color.FromArgb(255, 255, 255, 255)
                        });
                        ps.Seek(0);
                        using var ns = ps.AsStreamForRead();
                        using var ms = new MemoryStream();
                        await ns.CopyToAsync(ms);
                        bmp = PrinterHardwareEngine.DecodeBytesToFrozenBitmap(ms.ToArray());

                        int rot = ((page.RotationDegrees % 360) + 360) % 360;
                        if (rot != 0)
                        {
                            var tb = new TransformedBitmap(bmp, new RotateTransform(rot));
                            tb.Freeze();
                            bmp = tb;
                        }
                        if (page.EnhanceReceiptContrast)
                        {
                            bmp = ApplyDocumentEnhancement(bmp, whitenPaper: true, pureGrayscale: grayscale);
                        }
                    }
                }
                catch { }
            }

            if (grayscale)
                bmp = ConvertToGrayscaleBitmap(bmp);

            return bmp;
        }

        public static async Task<string> ExecutePrintJobAsync(
            DiscoveredPrinterInfo printer,
            IReadOnlyList<DocumentPageItem> allPages,
            IReadOnlyList<int> pageIndices,
            int copies,
            PrintColorMode colorMode,
            PrintDuplexMode duplexMode,
            PrintPaperFormat paperFormat,
            PrintOrientationMode orientationMode,
            PrintScaleMode scaleMode,
            string jobTitle = "Funkenwerk Druckauftrag")
        {
            if (pageIndices.Count == 0)
                throw new InvalidOperationException("Keine Seiten für den Druck ausgewählt.");

            bool useGray = colorMode == PrintColorMode.Grayscale;
            var selectedItems = pageIndices.Select(i => allPages[i]).ToList();

            // Bypass Windows WSD "Offline" bug automatically if printer is awake via LAN IPP :631!
            if (!printer.IsVirtualPrinter &&
                printer.SpoolerReportsOffline &&
                printer.LanIppOnline &&
                !string.IsNullOrWhiteSpace(printer.ResolvedIpAddress))
            {
                bool ippSent = await TrySendDirectIppPdfJobAsync(
                    printer.ResolvedIpAddress,
                    selectedItems,
                    copies,
                    useGray,
                    duplexMode,
                    jobTitle);

                if (ippSent)
                    return $"Direkt per LAN-IPP an {printer.ResolvedIpAddress} gesendet ({selectedItems.Count} S. × {copies})";
            }

            // Prepare high-res bitmaps on background/async first
            var printBitmaps = new List<(BitmapSource Bitmap, DocumentPageItem Meta)>();
            foreach (var item in selectedItems)
            {
                var hiBmp = await GetHighResPageBitmapForPrintAsync(item, useGray);
                printBitmaps.Add((hiBmp, item));
            }

            var (paperWmm, paperHmm, mediaName) = GetPaperDimensionsMm(paperFormat);

            // Dispatch FixedDocument printing on WPF UI thread (required by XpsDocumentWriter)
            await Application.Current.Dispatcher.InvokeAsync(() =>
            {
                using var server = new PrintServer();
                using var queue = server.GetPrintQueue(printer.QueueName);

                var ticket = queue.DefaultPrintTicket?.Clone() ?? new PrintTicket();
                ticket.CopyCount = Math.Clamp(copies, 1, 99);
                ticket.OutputColor = useGray ? OutputColor.Monochrome : OutputColor.Color;
                ticket.Duplexing = duplexMode switch
                {
                    PrintDuplexMode.LongEdge => Duplexing.TwoSidedLongEdge,
                    PrintDuplexMode.ShortEdge => Duplexing.TwoSidedShortEdge,
                    _ => Duplexing.OneSided
                };

                bool firstIsLandscape = orientationMode == PrintOrientationMode.Landscape ||
                    (orientationMode == PrintOrientationMode.Auto && printBitmaps[0].Bitmap.PixelWidth > printBitmaps[0].Bitmap.PixelHeight * 1.05);

                double baseWdip = paperWmm / 25.4 * 96.0;
                double baseHdip = paperHmm / 25.4 * 96.0;
                ticket.PageMediaSize = new PageMediaSize(mediaName, baseWdip, baseHdip);
                ticket.PageOrientation = firstIsLandscape ? PageOrientation.Landscape : PageOrientation.Portrait;

                var fixedDoc = new FixedDocument();
                fixedDoc.DocumentPaginator.PageSize = firstIsLandscape
                    ? new Size(baseHdip, baseWdip)
                    : new Size(baseWdip, baseHdip);

                foreach (var (bmp, meta) in printBitmaps)
                {
                    bool pageLandscape = orientationMode == PrintOrientationMode.Landscape ||
                        (orientationMode == PrintOrientationMode.Auto && bmp.PixelWidth > bmp.PixelHeight * 1.05);

                    double pageW = pageLandscape ? baseHdip : baseWdip;
                    double pageH = pageLandscape ? baseWdip : baseHdip;

                    var fixedPage = new FixedPage
                    {
                        Width = pageW,
                        Height = pageH,
                        Background = Brushes.White
                    };

                    double drawW, drawH, offsetX, offsetY;
                    if (scaleMode == PrintScaleMode.ActualSize100)
                    {
                        bool rotSwapped = (meta.RotationDegrees % 180) != 0;
                        double mmW = rotSwapped ? meta.HeightMm : meta.WidthMm;
                        double mmH = rotSwapped ? meta.WidthMm : meta.HeightMm;
                        drawW = mmW / 25.4 * 96.0;
                        drawH = mmH / 25.4 * 96.0;
                        offsetX = (pageW - drawW) / 2.0;
                        offsetY = (pageH - drawH) / 2.0;
                    }
                    else
                    {
                        // Safe hardware printable margin (4.2 mm = ~16 DIPs, 0 margin for labels/photos)
                        double marginDip = (paperFormat == PrintPaperFormat.Label100x150 || paperFormat == PrintPaperFormat.Photo10x15)
                            ? 4.0
                            : 16.0;
                        double availW = Math.Max(40, pageW - marginDip * 2);
                        double availH = Math.Max(40, pageH - marginDip * 2);
                        double scale = Math.Min(availW / bmp.PixelWidth, availH / bmp.PixelHeight);
                        drawW = bmp.PixelWidth * scale;
                        drawH = bmp.PixelHeight * scale;
                        offsetX = (pageW - drawW) / 2.0;
                        offsetY = (pageH - drawH) / 2.0;
                    }

                    var imgControl = new Image
                    {
                        Source = bmp,
                        Width = drawW,
                        Height = drawH,
                        Stretch = Stretch.Fill
                    };
                    RenderOptions.SetBitmapScalingMode(imgControl, BitmapScalingMode.HighQuality);

                    FixedPage.SetLeft(imgControl, offsetX);
                    FixedPage.SetTop(imgControl, offsetY);
                    fixedPage.Children.Add(imgControl);

                    var pageContent = new PageContent();
                    ((IAddChild)pageContent).AddChild(fixedPage);
                    fixedDoc.Pages.Add(pageContent);
                }

                var writer = PrintQueue.CreateXpsDocumentWriter(queue);
                writer.Write(fixedDoc, ticket);
            });

            return $"Druckauftrag gesendet an {printer.DisplayName} ({selectedItems.Count} S. × {copies})";
        }

        private static async Task<bool> TrySendDirectIppPdfJobAsync(
            string ip,
            IReadOnlyList<DocumentPageItem> pages,
            int copies,
            bool grayscale,
            PrintDuplexMode duplex,
            string jobTitle)
        {
            try
            {
                byte[] pdfBytes = await Task.Run(() => BuildPdfBytesFromPages(pages, grayscale));

                using var ms = new MemoryStream();
                using (var bw = new BinaryWriter(ms, Encoding.UTF8, leaveOpen: true))
                {
                    bw.Write((byte)0x02); // IPP 2.0
                    bw.Write((byte)0x00);
                    bw.Write((byte)0x00); // Operation 0x0002 = Print-Job
                    bw.Write((byte)0x02);
                    bw.Write(new byte[] { 0x00, 0x00, 0x00, 0x02 }); // request-id

                    bw.Write((byte)0x01); // operation-attributes-tag
                    WriteIppStr(bw, 0x47, "attributes-charset", "utf-8");
                    WriteIppStr(bw, 0x48, "attributes-natural-language", "de");
                    WriteIppStr(bw, 0x45, "printer-uri", $"ipp://{ip}:631/ipp/print");
                    WriteIppStr(bw, 0x42, "requesting-user-name", "Funkenwerk");
                    WriteIppStr(bw, 0x42, "job-name", jobTitle);
                    WriteIppStr(bw, 0x49, "document-format", "application/pdf");

                    bw.Write((byte)0x02); // job-attributes-tag
                    WriteIppInt(bw, 0x21, "copies", Math.Clamp(copies, 1, 99));
                    WriteIppStr(bw, 0x44, "print-color-mode", grayscale ? "monochrome" : "color");
                    string sides = duplex switch
                    {
                        PrintDuplexMode.LongEdge => "two-sided-long-edge",
                        PrintDuplexMode.ShortEdge => "two-sided-short-edge",
                        _ => "one-sided"
                    };
                    WriteIppStr(bw, 0x44, "sides", sides);

                    bw.Write((byte)0x03); // end-of-attributes-tag
                    bw.Write(pdfBytes);
                }

                var handler = new SocketsHttpHandler { UseProxy = false, ConnectTimeout = TimeSpan.FromSeconds(3) };
                using var http = new HttpClient(handler) { Timeout = TimeSpan.FromSeconds(25) };
                using var content = new ByteArrayContent(ms.ToArray());
                content.Headers.Remove("Content-Type");
                content.Headers.TryAddWithoutValidation("Content-Type", "application/ipp");

                var resp = await http.PostAsync($"http://{ip}:631/ipp/print", content);
                if (!resp.IsSuccessStatusCode) return false;
                byte[] rb = await resp.Content.ReadAsByteArrayAsync();
                // IPP status-code in bytes [2..3]: 0x0000..0x00FF = successful-*
                return rb.Length >= 4 && rb[2] == 0x00;
            }
            catch
            {
                return false;
            }
        }

        private static void WriteIppStr(BinaryWriter bw, byte tag, string name, string val)
        {
            bw.Write(tag);
            byte[] nb = Encoding.UTF8.GetBytes(name);
            bw.Write((byte)((nb.Length >> 8) & 0xFF));
            bw.Write((byte)(nb.Length & 0xFF));
            bw.Write(nb);
            byte[] vb = Encoding.UTF8.GetBytes(val);
            bw.Write((byte)((vb.Length >> 8) & 0xFF));
            bw.Write((byte)(vb.Length & 0xFF));
            bw.Write(vb);
        }

        private static void WriteIppInt(BinaryWriter bw, byte tag, string name, int val)
        {
            bw.Write(tag);
            byte[] nb = Encoding.UTF8.GetBytes(name);
            bw.Write((byte)((nb.Length >> 8) & 0xFF));
            bw.Write((byte)(nb.Length & 0xFF));
            bw.Write(nb);
            bw.Write((byte)0x00);
            bw.Write((byte)0x04);
            bw.Write((byte)((val >> 24) & 0xFF));
            bw.Write((byte)((val >> 16) & 0xFF));
            bw.Write((byte)((val >> 8) & 0xFF));
            bw.Write((byte)(val & 0xFF));
        }

        // =====================================================================
        // SPARSAME DÜSEN- & 100-MM-MASSTESTSEITE GENERIEREN
        // =====================================================================
        public static DocumentPageItem CreateCalibrationTestPage()
        {
            if (Thread.CurrentThread.GetApartmentState() != ApartmentState.STA)
            {
                DocumentPageItem? staResult = null;
                Exception? staErr = null;
                var t = new Thread(() =>
                {
                    try { staResult = CreateCalibrationTestPageCore(); }
                    catch (Exception ex) { staErr = ex; }
                });
                t.SetApartmentState(ApartmentState.STA);
                t.Start();
                t.Join();
                if (staErr != null) throw staErr;
                return staResult!;
            }
            return CreateCalibrationTestPageCore();
        }

        private static DocumentPageItem CreateCalibrationTestPageCore()
        {
            const double dpi = 300.0;
            int pxW = (int)Math.Round(210.0 / 25.4 * dpi); // 2480 px
            int pxH = (int)Math.Round(297.0 / 25.4 * dpi); // 3508 px

            var dv = new DrawingVisual();
            using (var dc = dv.RenderOpen())
            {
                // Render in 96 DIP coordinate space, then rasterize at 300 DPI
                double dipW = 210.0 / 25.4 * 96.0;
                double dipH = 297.0 / 25.4 * 96.0;

                dc.DrawRectangle(Brushes.White, null, new Rect(0, 0, dipW, dipH));

                var goldBrush = new SolidColorBrush(Color.FromRgb(197, 160, 89));
                var darkBrush = new SolidColorBrush(Color.FromRgb(17, 24, 39));
                var grayPen = new Pen(new SolidColorBrush(Color.FromRgb(156, 163, 175)), 1.0);
                var darkPen = new Pen(darkBrush, 1.4);

                var tfBold = new Typeface(new FontFamily("Segoe UI"), FontStyles.Normal, FontWeights.Bold, FontStretches.Normal);
                var tfReg = new Typeface(new FontFamily("Segoe UI"), FontStyles.Normal, FontWeights.Normal, FontStretches.Normal);

                dc.DrawText(
                    new FormattedText("FUNKENWERK DRUCKER · PRÜFBLATT", CultureInfo.GetCultureInfo("de-DE"), FlowDirection.LeftToRight, tfBold, 20, darkBrush, 1.0),
                    new Point(56, 56));

                dc.DrawText(
                    new FormattedText($"Mein-Seelenfunke Manufaktur · {DateTime.Now:dd.MM.yyyy HH:mm} · 100 % Lokal & Abofrei", CultureInfo.GetCultureInfo("de-DE"), FlowDirection.LeftToRight, tfReg, 11.5, Brushes.DimGray, 1.0),
                    new Point(56, 86));

                dc.DrawLine(new Pen(goldBrush, 2.0), new Point(56, 114), new Point(dipW - 56, 114));

                // Ink-saving CMYK nozzle check bars
                (string Label, Color Col)[] swatches =
                {
                    ("K · Schwarz", Color.FromRgb(15, 15, 15)),
                    ("C · Cyan", Color.FromRgb(0, 168, 232)),
                    ("M · Magenta", Color.FromRgb(225, 0, 122)),
                    ("Y · Gelb", Color.FromRgb(245, 208, 0)),
                    ("SF · Gold", Color.FromRgb(197, 160, 89))
                };

                double sx = 56;
                double sy = 148;
                foreach (var sw in swatches)
                {
                    var b = new SolidColorBrush(sw.Col);
                    dc.DrawRoundedRectangle(b, null, new Rect(sx, sy, 118, 22), 4, 4);
                    dc.DrawText(
                        new FormattedText(sw.Label, CultureInfo.GetCultureInfo("de-DE"), FlowDirection.LeftToRight, tfBold, 10, darkBrush, 1.0),
                        new Point(sx, sy + 28));
                    sx += 134;
                }

                // Exact 100 mm Calibration Ruler for Laser Templates (100 mm = 377.952756 DIPs)
                double rulerY = 250;
                double mmDip = 96.0 / 25.4;
                double rulerLen = 100.0 * mmDip;

                dc.DrawText(
                    new FormattedText("MASSTABSKONTROLLE (EXAKT 100 MM BEI SKALIERUNG '100 % ORIGINALGRÖSSE')", CultureInfo.GetCultureInfo("de-DE"), FlowDirection.LeftToRight, tfBold, 10.5, darkBrush, 1.0),
                    new Point(56, rulerY - 24));

                dc.DrawLine(darkPen, new Point(56, rulerY), new Point(56 + rulerLen, rulerY));
                for (int mm = 0; mm <= 100; mm += 5)
                {
                    double x = 56 + mm * mmDip;
                    double tickH = (mm % 10 == 0) ? 14 : 8;
                    dc.DrawLine(mm % 10 == 0 ? darkPen : grayPen, new Point(x, rulerY), new Point(x, rulerY + tickH));
                    if (mm % 10 == 0)
                    {
                        dc.DrawText(
                            new FormattedText($"{mm}", CultureInfo.GetCultureInfo("de-DE"), FlowDirection.LeftToRight, tfReg, 9.5, darkBrush, 1.0),
                            new Point(x - 6, rulerY + 16));
                    }
                }
            }

            var rtb = new RenderTargetBitmap(pxW, pxH, dpi, dpi, PixelFormats.Pbgra32);
            rtb.Render(dv);
            rtb.Freeze();

            return new DocumentPageItem
            {
                SourceKind = "Prüfblatt",
                OriginalBitmap = rtb,
                ProcessedBitmap = rtb,
                WidthMm = 210.0,
                HeightMm = 297.0
            };
        }
    }
}
