using System;
using System.Collections.Generic;
using System.Globalization;
using System.IO;
using System.Linq;
using System.Text;
using System.Text.RegularExpressions;
using PdfSharp.Pdf;
using PdfSharp.Pdf.Advanced;
using PdfSharp.Pdf.IO;

namespace FunkenwerkPdf
{
    public enum PdfAcroFieldKind
    {
        Text,
        CheckBox,
        RadioButton,
        ComboBox,
        SignatureTrigger
    }

    public sealed class PdfFormFieldItem
    {
        public int PageIndex { get; set; }
        public int AnnotIndex { get; set; }
        public string FullName { get; set; } = "";
        public string Tooltip { get; set; } = "";
        public string GroupId { get; set; } = "";
        public PdfAcroFieldKind Kind { get; set; } = PdfAcroFieldKind.Text;

        // Top-down page coordinates in PDF points (1/72 inch)
        public double XPt { get; set; }
        public double YPt { get; set; }
        public double WidthPt { get; set; }
        public double HeightPt { get; set; }

        // Field constraints & styling
        public bool IsReadOnly { get; set; }
        public bool IsMultiline { get; set; }
        public bool IsPassword { get; set; }
        public int MaxLength { get; set; }
        public int Alignment { get; set; } // 0=Left, 1=Center, 2=Right
        public double FontSizePt { get; set; } = 10.0;

        // Current interactive state
        public string TextValue { get; set; } = "";
        public bool IsChecked { get; set; }
        public string OnStateName { get; set; } = "/Yes";
        public List<string> Options { get; set; } = new();

        public bool IsTinyBoxText =>
            Kind == PdfAcroFieldKind.Text && !IsMultiline && WidthPt <= 25.0 && HeightPt <= 21.0;

        public bool HasValue =>
            Kind switch
            {
                PdfAcroFieldKind.CheckBox or PdfAcroFieldKind.RadioButton => IsChecked,
                PdfAcroFieldKind.SignatureTrigger => false,
                _ => !string.IsNullOrWhiteSpace(TextValue)
            };
    }

    public static class PdfFormFieldEngine
    {
        private static readonly Encoding WinAnsi;

        static PdfFormFieldEngine()
        {
            Encoding.RegisterProvider(CodePagesEncodingProvider.Instance);
            WinAnsi = Encoding.GetEncoding(1252);
        }

        private static PdfDictionary? DerefDict(PdfItem? item) =>
            item is PdfReference r ? r.Value as PdfDictionary : item as PdfDictionary;

        private static PdfArray? DerefArr(PdfItem? item) =>
            item is PdfReference r ? r.Value as PdfArray : item as PdfArray;

        private static string ReadPdfItemAsString(PdfItem? item)
        {
            if (item is PdfReference r) item = r.Value;
            return item switch
            {
                PdfString s => s.Value ?? "",
                PdfName n => n.Value?.TrimStart('/') ?? "",
                _ => ""
            };
        }

        private static double ParseDaFontSize(string? da)
        {
            if (string.IsNullOrWhiteSpace(da)) return 0;
            var m = Regex.Match(da, @"([0-9]+(?:\.[0-9]+)?)\s+Tf", RegexOptions.CultureInvariant);
            if (m.Success && double.TryParse(m.Groups[1].Value, NumberStyles.Float, CultureInfo.InvariantCulture, out double sz))
                return sz;
            return 0;
        }

        public static List<PdfFormFieldItem> ExtractFields(byte[] pdfBytes)
        {
            var result = new List<PdfFormFieldItem>();
            if (pdfBytes == null || pdfBytes.Length == 0) return result;

            try
            {
                using var ms = new MemoryStream(pdfBytes);
                using var doc = PdfReader.Open(ms, PdfDocumentOpenMode.Import);

                var acro = DerefDict(doc.Internals.Catalog.Elements["/AcroForm"]);
                string docDa = acro?.Elements.GetString("/DA") ?? "";

                for (int pageIdx = 0; pageIdx < doc.PageCount; pageIdx++)
                {
                    var page = doc.Pages[pageIdx];
                    var annots = DerefArr(page.Elements["/Annots"]);
                    if (annots == null) continue;

                    var crop = page.CropBox;
                    var media = page.MediaBox;
                    bool hasCrop = crop.Width > 0 && crop.Height > 0;
                    double boxX1 = hasCrop ? crop.X1 : media.X1;
                    double boxY1 = hasCrop ? crop.Y1 : media.Y1;
                    double boxW = hasCrop ? crop.Width : (media.Width > 0 ? media.Width : page.Width.Point);
                    double boxH = hasCrop ? crop.Height : (media.Height > 0 ? media.Height : page.Height.Point);
                    int rotate = ((page.Rotate % 360) + 360) % 360;

                    for (int annotIdx = 0; annotIdx < annots.Elements.Count; annotIdx++)
                    {
                        var d = DerefDict(annots.Elements[annotIdx]);
                        if (d == null) continue;
                        if (!string.Equals(d.Elements.GetString("/Subtype"), "/Widget", StringComparison.Ordinal))
                            continue;

                        int annotFlags = d.Elements.GetInteger("/F");
                        // Skip Hidden (bit 2 = 2) or NoView (bit 6 = 32)
                        if ((annotFlags & 2) != 0 || (annotFlags & 32) != 0)
                            continue;

                        string ft = d.Elements.GetString("/FT");
                        string localT = d.Elements.GetString("/T");
                        string tu = d.Elements.GetString("/TU");
                        int ff = d.Elements.GetInteger("/Ff");
                        int maxLen = d.Elements.GetInteger("/MaxLen");
                        int quadding = d.Elements.GetInteger("/Q");
                        string da = d.Elements.GetString("/DA");
                        PdfItem? vItem = d.Elements["/V"];
                        PdfArray? optArr = DerefArr(d.Elements["/Opt"]);

                        string fullT = localT;
                        string groupId = fullT;
                        var parent = DerefDict(d.Elements["/Parent"]);
                        if (parent != null && parent.Reference != null)
                            groupId = $"obj:{parent.Reference.ObjectID.ObjectNumber}";

                        var curParent = parent;
                        while (curParent != null)
                        {
                            if (string.IsNullOrEmpty(ft)) ft = curParent.Elements.GetString("/FT");
                            if (ff == 0) ff = curParent.Elements.GetInteger("/Ff");
                            if (maxLen == 0) maxLen = curParent.Elements.GetInteger("/MaxLen");
                            if (quadding == 0) quadding = curParent.Elements.GetInteger("/Q");
                            if (string.IsNullOrEmpty(da)) da = curParent.Elements.GetString("/DA");
                            if (string.IsNullOrEmpty(tu)) tu = curParent.Elements.GetString("/TU");
                            if (vItem == null) vItem = curParent.Elements["/V"];
                            if (optArr == null) optArr = DerefArr(curParent.Elements["/Opt"]);

                            string pt = curParent.Elements.GetString("/T");
                            if (!string.IsNullOrEmpty(pt))
                                fullT = string.IsNullOrEmpty(fullT) ? pt : $"{pt}.{fullT}";

                            curParent = DerefDict(curParent.Elements["/Parent"]);
                        }

                        if (string.IsNullOrEmpty(ft)) continue;

                        bool isReadOnly = (ff & 1) != 0;
                        bool isMultiline = (ff & (1 << 12)) != 0;
                        bool isPassword = (ff & (1 << 13)) != 0;
                        bool isRadio = (ff & (1 << 15)) != 0;
                        bool isPushbutton = (ff & (1 << 16)) != 0;

                        if (ft == "/Btn" && isPushbutton) continue;

                        var rect = d.Elements.GetRectangle("/Rect");
                        double rx1 = Math.Min(rect.X1, rect.X2) - boxX1;
                        double rx2 = Math.Max(rect.X1, rect.X2) - boxX1;
                        double ry1 = Math.Min(rect.Y1, rect.Y2) - boxY1;
                        double ry2 = Math.Max(rect.Y1, rect.Y2) - boxY1;
                        double rw = rx2 - rx1;
                        double rh = ry2 - ry1;
                        if (rw < 2 || rh < 2) continue;

                        double xPt, yPt, wPt, hPt;
                        switch (rotate)
                        {
                            case 90:
                                xPt = ry1;
                                yPt = rx1;
                                wPt = rh;
                                hPt = rw;
                                break;
                            case 180:
                                xPt = boxW - rx2;
                                yPt = ry1;
                                wPt = rw;
                                hPt = rh;
                                break;
                            case 270:
                                xPt = boxH - ry2;
                                yPt = boxW - rx2;
                                wPt = rh;
                                hPt = rw;
                                break;
                            default:
                                xPt = rx1;
                                yPt = boxH - ry2;
                                wPt = rw;
                                hPt = rh;
                                break;
                        }

                        double daFont = ParseDaFontSize(!string.IsNullOrEmpty(da) ? da : docDa);
                        if (daFont <= 0)
                        {
                            daFont = isMultiline ? 10.0 : Math.Clamp(hPt * 0.72, 7.5, 12.0);
                        }

                        var field = new PdfFormFieldItem
                        {
                            PageIndex = pageIdx,
                            AnnotIndex = annotIdx,
                            FullName = fullT,
                            Tooltip = !string.IsNullOrWhiteSpace(tu) ? tu : fullT,
                            GroupId = string.IsNullOrEmpty(groupId) ? fullT : groupId,
                            XPt = xPt,
                            YPt = yPt,
                            WidthPt = wPt,
                            HeightPt = hPt,
                            IsReadOnly = isReadOnly,
                            IsMultiline = isMultiline,
                            IsPassword = isPassword,
                            MaxLength = maxLen,
                            Alignment = Math.Clamp(quadding, 0, 2),
                            FontSizePt = daFont
                        };

                        if (ft == "/Tx")
                        {
                            field.Kind = PdfAcroFieldKind.Text;
                            field.TextValue = ReadPdfItemAsString(vItem);
                            if (field.IsTinyBoxText)
                                field.Alignment = 1; // Center 'X' in small Ja/Nein boxes
                        }
                        else if (ft == "/Btn")
                        {
                            field.Kind = isRadio ? PdfAcroFieldKind.RadioButton : PdfAcroFieldKind.CheckBox;
                            string onState = "/Yes";

                            var ap = DerefDict(d.Elements["/AP"]);
                            var apN = ap != null ? DerefDict(ap.Elements["/N"]) : null;
                            if (apN != null && apN.Stream == null)
                            {
                                var nonOff = apN.Elements.Keys.FirstOrDefault(k => !string.Equals(k, "/Off", StringComparison.OrdinalIgnoreCase));
                                if (!string.IsNullOrEmpty(nonOff))
                                    onState = nonOff;
                            }
                            field.OnStateName = onState;

                            string asVal = d.Elements.GetString("/AS");
                            string vName = vItem is PdfName pn ? pn.Value : (vItem is PdfString ps ? "/" + ps.Value : "");

                            if (!string.IsNullOrEmpty(asVal))
                                field.IsChecked = !string.Equals(asVal, "/Off", StringComparison.OrdinalIgnoreCase);
                            else if (!string.IsNullOrEmpty(vName))
                                field.IsChecked = isRadio
                                    ? string.Equals(vName, onState, StringComparison.Ordinal)
                                    : !string.Equals(vName, "/Off", StringComparison.OrdinalIgnoreCase);
                        }
                        else if (ft == "/Ch")
                        {
                            field.Kind = PdfAcroFieldKind.ComboBox;
                            field.TextValue = ReadPdfItemAsString(vItem);
                            if (optArr != null)
                            {
                                foreach (var optEl in optArr.Elements)
                                {
                                    var derefOpt = optEl is PdfReference or ? or.Value : optEl;
                                    if (derefOpt is PdfArray pair && pair.Elements.Count >= 2)
                                        field.Options.Add(ReadPdfItemAsString(pair.Elements[1]));
                                    else
                                    {
                                        string s = ReadPdfItemAsString(derefOpt);
                                        if (!string.IsNullOrEmpty(s)) field.Options.Add(s);
                                    }
                                }
                            }
                        }
                        else if (ft == "/Sig")
                        {
                            field.Kind = PdfAcroFieldKind.SignatureTrigger;
                        }
                        else
                        {
                            continue;
                        }

                        result.Add(field);
                    }
                }
            }
            catch
            {
                // Non-AcroForm or malformed widget dictionary — safely ignore
            }

            return result;
        }

        /// <summary>
        /// Produces a fast in-memory copy of the PDF where editable /Widget appearance streams (/AP)
        /// are suppressed so Windows.Data.Pdf renders the clean underlying page lines/boxes without
        /// ghosting stale form text underneath our live interactive WPF controls.
        /// </summary>
        public static byte[] CreateCleanRenderPdfBytes(byte[] pdfBytes, bool hasExtractedFields)
        {
            if (!hasExtractedFields || pdfBytes == null || pdfBytes.Length == 0)
                return pdfBytes ?? Array.Empty<byte>();

            try
            {
                using var inMs = new MemoryStream(pdfBytes);
                using var doc = PdfReader.Open(inMs, PdfDocumentOpenMode.Modify);

                var acro = DerefDict(doc.Internals.Catalog.Elements["/AcroForm"]);
                if (acro != null)
                    acro.Elements.SetBoolean("/NeedAppearances", false);

                for (int p = 0; p < doc.PageCount; p++)
                {
                    var annots = DerefArr(doc.Pages[p].Elements["/Annots"]);
                    if (annots == null) continue;
                    for (int i = 0; i < annots.Elements.Count; i++)
                    {
                        var d = DerefDict(annots.Elements[i]);
                        if (d == null) continue;
                        if (string.Equals(d.Elements.GetString("/Subtype"), "/Widget", StringComparison.Ordinal))
                        {
                            // Bit 2 (Hidden = 2) hides the static widget appearance only in this temporary render buffer
                            int f = d.Elements.GetInteger("/F");
                            d.Elements.SetInteger("/F", f | 2);
                        }
                    }
                }

                using var outMs = new MemoryStream();
                doc.Save(outMs, false);
                return outMs.ToArray();
            }
            catch
            {
                return pdfBytes;
            }
        }

        /// <summary>
        /// Writes all interactive AcroForm values (/V, /AS) AND ISO-32000 Form XObject Appearance Streams (/AP -> /N)
        /// back into the PDF so the saved file stays interactively editable in Adobe Acrobat & Funkenwerk PDF
        /// AND displays every filled value identically in all browsers, mobile devices, and PDF viewers.
        /// </summary>
        public static byte[] ApplyFormFieldsToPdfBytes(byte[] sourcePdfBytes, IReadOnlyList<PdfFormFieldItem> fields)
        {
            if (fields == null || fields.Count == 0)
                return sourcePdfBytes;

            using var inMs = new MemoryStream(sourcePdfBytes);
            using var doc = PdfReader.Open(inMs, PdfDocumentOpenMode.Modify);

            var acro = DerefDict(doc.Internals.Catalog.Elements["/AcroForm"]);
            if (acro != null)
            {
                // Keep /NeedAppearances = false because we generate exact /AP Form XObjects for every field
                acro.Elements.SetBoolean("/NeedAppearances", false);
            }

            foreach (var pageGroup in fields.GroupBy(f => f.PageIndex))
            {
                if (pageGroup.Key < 0 || pageGroup.Key >= doc.PageCount) continue;
                var page = doc.Pages[pageGroup.Key];
                var annots = DerefArr(page.Elements["/Annots"]);
                if (annots == null) continue;

                foreach (var field in pageGroup)
                {
                    if (field.IsReadOnly || field.Kind == PdfAcroFieldKind.SignatureTrigger) continue;
                    if (field.AnnotIndex < 0 || field.AnnotIndex >= annots.Elements.Count) continue;

                    var widget = DerefDict(annots.Elements[field.AnnotIndex]);
                    if (widget == null) continue;

                    var parent = DerefDict(widget.Elements["/Parent"]);
                    var valueHolder = (parent != null && !widget.Elements.ContainsKey("/T")) ? parent : widget;

                    var rect = widget.Elements.GetRectangle("/Rect");
                    double w = Math.Max(2, Math.Abs(rect.X2 - rect.X1));
                    double h = Math.Max(2, Math.Abs(rect.Y2 - rect.Y1));

                    if (field.Kind == PdfAcroFieldKind.Text || field.Kind == PdfAcroFieldKind.ComboBox)
                    {
                        string val = field.TextValue ?? "";
                        valueHolder.Elements.SetString("/V", val);
                        if (valueHolder != widget && widget.Elements.ContainsKey("/V"))
                            widget.Elements.SetString("/V", val);

                        var apStream = BuildTextAppearanceXObject(doc, val, w, h, field.FontSizePt, field.IsMultiline, field.Alignment);
                        var apDict = new PdfDictionary(doc);
                        apDict.Elements["/N"] = apStream.Reference!;
                        widget.Elements["/AP"] = apDict;
                    }
                    else if (field.Kind == PdfAcroFieldKind.CheckBox || field.Kind == PdfAcroFieldKind.RadioButton)
                    {
                        string stateName = field.IsChecked ? field.OnStateName : "/Off";
                        if (!stateName.StartsWith("/")) stateName = "/" + stateName;

                        widget.Elements.SetName("/AS", stateName);
                        if (field.Kind == PdfAcroFieldKind.CheckBox)
                        {
                            valueHolder.Elements.SetName("/V", stateName);
                        }
                        else if (field.IsChecked)
                        {
                            // Radio group parent holds the selected option's name
                            valueHolder.Elements.SetName("/V", stateName);
                        }
                        else if (parent == null)
                        {
                            valueHolder.Elements.SetName("/V", "/Off");
                        }

                        // Ensure /AP -> /N has a visible state stream even if the source PDF lacked one
                        var existingAp = DerefDict(widget.Elements["/AP"]);
                        var existingN = existingAp != null ? DerefDict(existingAp.Elements["/N"]) : null;
                        bool hasValidStateDict = existingN != null && existingN.Stream == null && existingN.Elements.ContainsKey(stateName);

                        if (!hasValidStateDict)
                        {
                            var stateDict = new PdfDictionary(doc);
                            var offObj = BuildCheckAppearanceXObject(doc, false, field.Kind == PdfAcroFieldKind.RadioButton, w, h);
                            var onObj = BuildCheckAppearanceXObject(doc, true, field.Kind == PdfAcroFieldKind.RadioButton, w, h);
                            string onKey = field.OnStateName.StartsWith("/") ? field.OnStateName : "/" + field.OnStateName;
                            stateDict.Elements["/Off"] = offObj.Reference!;
                            stateDict.Elements[onKey] = onObj.Reference!;

                            var apDict = new PdfDictionary(doc);
                            apDict.Elements["/N"] = stateDict;
                            widget.Elements["/AP"] = apDict;
                        }
                    }
                }
            }

            using var outMs = new MemoryStream();
            doc.Save(outMs, false);
            return outMs.ToArray();
        }

        private static PdfDictionary BuildTextAppearanceXObject(
            PdfDocument doc,
            string text,
            double w,
            double h,
            double requestedFontSize,
            bool isMultiline,
            int alignment)
        {
            var fontDict = new PdfDictionary(doc);
            fontDict.Elements.SetName("/Type", "/Font");
            fontDict.Elements.SetName("/Subtype", "/Type1");
            fontDict.Elements.SetName("/BaseFont", "/Helvetica");
            fontDict.Elements.SetName("/Encoding", "/WinAnsiEncoding");

            var fontsDict = new PdfDictionary(doc);
            fontsDict.Elements["/Helv"] = fontDict;

            var resDict = new PdfDictionary(doc);
            resDict.Elements["/Font"] = fontsDict;

            var formXObj = new PdfDictionary(doc);
            formXObj.Elements.SetName("/Type", "/XObject");
            formXObj.Elements.SetName("/Subtype", "/Form");
            formXObj.Elements["/BBox"] = new PdfArray(doc, new PdfReal(0), new PdfReal(0), new PdfReal(w), new PdfReal(h));
            formXObj.Elements["/Resources"] = resDict;

            using var ms = new MemoryStream();
            void WriteAscii(string s)
            {
                byte[] b = Encoding.ASCII.GetBytes(s);
                ms.Write(b, 0, b.Length);
            }

            if (string.IsNullOrEmpty(text))
            {
                WriteAscii("/Tx BMC q Q EMC");
            }
            else
            {
                double fontSize = isMultiline
                    ? Math.Clamp(requestedFontSize, 7.0, 14.0)
                    : Math.Min(requestedFontSize, Math.Max(6.5, h * 0.78));

                WriteAscii(string.Format(
                    CultureInfo.InvariantCulture,
                    "/Tx BMC q 0 0 {0:0.##} {1:0.##} re W n BT /Helv {2:0.##} Tf 0 g ",
                    w, h, fontSize));

                if (!isMultiline)
                {
                    string single = text.Replace("\r", "").Replace("\n", " ");
                    double approxTextW = single.Length * fontSize * 0.52;
                    double tx = alignment switch
                    {
                        1 => Math.Max(1.0, (w - approxTextW) * 0.5),
                        2 => Math.Max(1.5, w - approxTextW - 2.0),
                        _ => 2.0
                    };
                    double ty = Math.Max(1.6, (h - fontSize) * 0.5 + fontSize * 0.16);
                    WriteAscii(string.Format(CultureInfo.InvariantCulture, "{0:0.##} {1:0.##} Td (", tx, ty));
                    WriteEscapedWinAnsi(ms, single);
                    WriteAscii(") Tj ");
                }
                else
                {
                    var lines = WrapMultilineText(text, w - 4.0, fontSize);
                    double lineHeight = fontSize * 1.22;
                    double curY = h - fontSize - 2.0;

                    for (int i = 0; i < lines.Count; i++)
                    {
                        if (curY < -fontSize) break;
                        if (i == 0)
                            WriteAscii(string.Format(CultureInfo.InvariantCulture, "2 {0:0.##} Td (", curY));
                        else
                            WriteAscii(string.Format(CultureInfo.InvariantCulture, "0 {0:0.##} Td (", -lineHeight));

                        WriteEscapedWinAnsi(ms, lines[i]);
                        WriteAscii(") Tj ");
                        curY -= lineHeight;
                    }
                }

                WriteAscii("ET Q EMC");
            }

            formXObj.CreateStream(ms.ToArray());
            doc.Internals.AddObject(formXObj);
            return formXObj;
        }

        private static PdfDictionary BuildCheckAppearanceXObject(PdfDocument doc, bool isChecked, bool isRadio, double w, double h)
        {
            var formXObj = new PdfDictionary(doc);
            formXObj.Elements.SetName("/Type", "/XObject");
            formXObj.Elements.SetName("/Subtype", "/Form");
            formXObj.Elements["/BBox"] = new PdfArray(doc, new PdfReal(0), new PdfReal(0), new PdfReal(w), new PdfReal(h));

            string content;
            if (!isChecked)
            {
                content = "q Q";
            }
            else if (isRadio)
            {
                double cx = w * 0.5;
                double cy = h * 0.5;
                double r = Math.Min(w, h) * 0.28;
                double k = r * 0.55228;
                content = string.Format(
                    CultureInfo.InvariantCulture,
                    "q 0.08 0.08 0.08 rg {0:0.##} {1:0.##} m {2:0.##} {1:0.##} {3:0.##} {4:0.##} {3:0.##} {5:0.##} c {3:0.##} {6:0.##} {2:0.##} {7:0.##} {0:0.##} {7:0.##} c {8:0.##} {7:0.##} {9:0.##} {6:0.##} {9:0.##} {5:0.##} c {9:0.##} {4:0.##} {8:0.##} {1:0.##} {0:0.##} {1:0.##} c f Q",
                    cx, cy + r, cx + k, cx + r, cy + k, cy, cy - k, cy - r, cx - k, cx - r);
            }
            else
            {
                double sw = Math.Max(1.2, Math.Min(w, h) * 0.14);
                double x1 = w * 0.18, y1 = h * 0.46;
                double x2 = w * 0.42, y2 = h * 0.20;
                double x3 = w * 0.84, y3 = h * 0.78;
                content = string.Format(
                    CultureInfo.InvariantCulture,
                    "q 0.08 0.08 0.08 RG {0:0.##} w 1 J 1 j {1:0.##} {2:0.##} m {3:0.##} {4:0.##} l {5:0.##} {6:0.##} l S Q",
                    sw, x1, y1, x2, y2, x3, y3);
            }

            formXObj.CreateStream(Encoding.ASCII.GetBytes(content));
            doc.Internals.AddObject(formXObj);
            return formXObj;
        }

        private static List<string> WrapMultilineText(string text, double maxWidthPt, double fontSize)
        {
            var result = new List<string>();
            int maxCharsPerLine = Math.Max(8, (int)Math.Floor(maxWidthPt / (fontSize * 0.50)));
            string[] paragraphs = text.Replace("\r\n", "\n").Replace('\r', '\n').Split('\n');

            foreach (string para in paragraphs)
            {
                if (para.Length <= maxCharsPerLine)
                {
                    result.Add(para);
                    continue;
                }

                string[] words = para.Split(' ');
                var cur = new StringBuilder();
                foreach (string word in words)
                {
                    if (cur.Length == 0)
                    {
                        cur.Append(word);
                    }
                    else if (cur.Length + 1 + word.Length <= maxCharsPerLine)
                    {
                        cur.Append(' ').Append(word);
                    }
                    else
                    {
                        result.Add(cur.ToString());
                        cur.Clear().Append(word);
                    }
                }
                if (cur.Length > 0) result.Add(cur.ToString());
            }

            return result;
        }

        private static void WriteEscapedWinAnsi(Stream ms, string s)
        {
            byte[] bytes = WinAnsi.GetBytes(s);
            foreach (byte b in bytes)
            {
                if (b == (byte)'(' || b == (byte)')' || b == (byte)'\\')
                    ms.WriteByte((byte)'\\');
                ms.WriteByte(b);
            }
        }
    }
}
