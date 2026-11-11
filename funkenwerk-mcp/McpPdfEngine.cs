using System;
using System.Collections.Generic;
using System.Globalization;
using System.IO;
using System.Linq;
using System.Text;
using System.Text.RegularExpressions;
using PdfSharp.Drawing;
using PdfSharp.Pdf;
using PdfSharp.Pdf.Advanced;
using PdfSharp.Pdf.IO;

namespace FunkenwerkMcp
{
    public sealed class McpPdfFieldDto
    {
        public int PageIndex { get; set; }
        public int PageNumber => PageIndex + 1;
        public int AnnotIndex { get; set; }
        public string Name { get; set; } = "";
        public string Tooltip { get; set; } = "";
        public string GroupId { get; set; } = "";
        public string Kind { get; set; } = "Text"; // Text, CheckBox, RadioButton, ComboBox, SignatureTrigger
        public double XPt { get; set; }
        public double YPt { get; set; }
        public double WidthPt { get; set; }
        public double HeightPt { get; set; }
        public bool IsReadOnly { get; set; }
        public bool IsMultiline { get; set; }
        public bool IsTinyBoxText { get; set; }
        public int MaxLength { get; set; }
        public int Alignment { get; set; }
        public double FontSizePt { get; set; } = 10.0;
        public string Value { get; set; } = "";
        public bool IsChecked { get; set; }
        public string OnStateName { get; set; } = "/Yes";
        public List<string> Options { get; set; } = new();
    }

    public sealed class McpPdfOverlayInput
    {
        public int PageNumber { get; set; } = 1;
        public string Type { get; set; } = "text"; // text, check, cross, whiteout
        public double XPt { get; set; }
        public double YPt { get; set; }
        public double WidthPt { get; set; } = 160;
        public double HeightPt { get; set; } = 20;
        public string Text { get; set; } = "";
        public double FontSizePt { get; set; } = 11;
    }

    public sealed class McpPdfInspectResult
    {
        public string FilePath { get; set; } = "";
        public int PageCount { get; set; }
        public int FormFieldCount { get; set; }
        public List<object> Pages { get; set; } = new();
        public List<McpPdfFieldDto> Fields { get; set; } = new();
    }

    public static class McpPdfEngine
    {
        private static readonly Encoding WinAnsi;

        static McpPdfEngine()
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

        public static McpPdfInspectResult InspectPdf(string safeFilePath)
        {
            byte[] pdfBytes = File.ReadAllBytes(safeFilePath);
            using var ms = new MemoryStream(pdfBytes);
            using var doc = PdfReader.Open(ms, PdfDocumentOpenMode.Import);

            var pages = new List<object>();
            for (int i = 0; i < doc.PageCount; i++)
            {
                var p = doc.Pages[i];
                pages.Add(new
                {
                    pageNumber = i + 1,
                    widthPt = Math.Round(p.Width.Point, 2),
                    heightPt = Math.Round(p.Height.Point, 2),
                    rotate = p.Rotate
                });
            }

            var fields = ExtractFieldsFromBytes(pdfBytes);
            return new McpPdfInspectResult
            {
                FilePath = safeFilePath,
                PageCount = doc.PageCount,
                FormFieldCount = fields.Count,
                Pages = pages,
                Fields = fields
            };
        }

        public static List<McpPdfFieldDto> ExtractFieldsFromBytes(byte[] pdfBytes)
        {
            var result = new List<McpPdfFieldDto>();
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

                    double xPt = rotate switch { 90 => ry1, 180 => boxW - rx2, 270 => boxH - ry2, _ => rx1 };
                    double yPt = rotate switch { 90 => rx1, 180 => ry1, 270 => boxW - rx2, _ => boxH - ry2 };
                    double wPt = (rotate == 90 || rotate == 270) ? rh : rw;
                    double hPt = (rotate == 90 || rotate == 270) ? rw : rh;

                    double daFont = ParseDaFontSize(!string.IsNullOrEmpty(da) ? da : docDa);
                    if (daFont <= 0)
                        daFont = isMultiline ? 10.0 : Math.Clamp(hPt * 0.72, 7.5, 12.0);

                    var dto = new McpPdfFieldDto
                    {
                        PageIndex = pageIdx,
                        AnnotIndex = annotIdx,
                        Name = McpSecurityGuard.SanitizeUntrustedPdfString(fullT, 200),
                        Tooltip = McpSecurityGuard.SanitizeUntrustedPdfString(!string.IsNullOrWhiteSpace(tu) ? tu : fullT, 300),
                        GroupId = groupId,
                        XPt = Math.Round(xPt, 2),
                        YPt = Math.Round(yPt, 2),
                        WidthPt = Math.Round(wPt, 2),
                        HeightPt = Math.Round(hPt, 2),
                        IsReadOnly = isReadOnly,
                        IsMultiline = isMultiline,
                        MaxLength = maxLen,
                        Alignment = Math.Clamp(quadding, 0, 2),
                        FontSizePt = Math.Round(daFont, 2)
                    };

                    if (ft == "/Tx")
                    {
                        dto.Kind = "Text";
                        dto.IsTinyBoxText = !isMultiline && wPt <= 25.0 && hPt <= 21.0;
                        if (dto.IsTinyBoxText) dto.Alignment = 1;
                        dto.Value = McpSecurityGuard.SanitizeUntrustedPdfString(ReadPdfItemAsString(vItem), 1000);
                    }
                    else if (ft == "/Btn")
                    {
                        dto.Kind = isRadio ? "RadioButton" : "CheckBox";
                        string onState = "/Yes";
                        var ap = DerefDict(d.Elements["/AP"]);
                        var apN = ap != null ? DerefDict(ap.Elements["/N"]) : null;
                        if (apN != null && apN.Stream == null)
                        {
                            var nonOff = apN.Elements.Keys.FirstOrDefault(k => !string.Equals(k, "/Off", StringComparison.OrdinalIgnoreCase));
                            if (!string.IsNullOrEmpty(nonOff)) onState = nonOff;
                        }
                        dto.OnStateName = onState;

                        string asVal = d.Elements.GetString("/AS");
                        string vName = vItem is PdfName pn ? pn.Value : (vItem is PdfString ps ? "/" + ps.Value : "");
                        if (!string.IsNullOrEmpty(asVal))
                            dto.IsChecked = !string.Equals(asVal, "/Off", StringComparison.OrdinalIgnoreCase);
                        else if (!string.IsNullOrEmpty(vName))
                            dto.IsChecked = isRadio
                                ? string.Equals(vName, onState, StringComparison.Ordinal)
                                : !string.Equals(vName, "/Off", StringComparison.OrdinalIgnoreCase);
                        dto.Value = dto.IsChecked ? "true" : "false";
                    }
                    else if (ft == "/Ch")
                    {
                        dto.Kind = "ComboBox";
                        dto.Value = McpSecurityGuard.SanitizeUntrustedPdfString(ReadPdfItemAsString(vItem), 500);
                        if (optArr != null)
                        {
                            foreach (var optEl in optArr.Elements)
                            {
                                var derefOpt = optEl is PdfReference or ? or.Value : optEl;
                                string optStr = derefOpt is PdfArray pair && pair.Elements.Count >= 2
                                    ? ReadPdfItemAsString(pair.Elements[1])
                                    : ReadPdfItemAsString(derefOpt);
                                if (!string.IsNullOrEmpty(optStr))
                                    dto.Options.Add(McpSecurityGuard.SanitizeUntrustedPdfString(optStr, 200));
                            }
                        }
                    }
                    else if (ft == "/Sig")
                    {
                        dto.Kind = "SignatureTrigger";
                    }
                    else
                    {
                        continue;
                    }

                    result.Add(dto);
                }
            }

            return result;
        }

        private static string CleanPdfInputText(string? raw, int maxLen = 4000)
        {
            if (string.IsNullOrEmpty(raw)) return "";
            var sb = new StringBuilder(Math.Min(raw.Length, maxLen));
            foreach (char c in raw)
            {
                if (sb.Length >= maxLen) break;
                if (c == '\n' || c == '\r' || c == '\t' || !char.IsControl(c))
                    sb.Append(c);
            }
            return sb.ToString();
        }

        private static double ClampFinite(double val, double min, double max, double fallback) =>
            double.IsFinite(val) ? Math.Clamp(val, min, max) : fallback;

        public static (byte[] SavedBytes, List<string> UpdatedFields, List<string> UnmatchedKeys) ApplyFormValuesAndOverlays(
            byte[] sourcePdfBytes,
            Dictionary<string, string>? fieldValues,
            List<McpPdfOverlayInput>? overlays)
        {
            var fields = ExtractFieldsFromBytes(sourcePdfBytes);
            var updatedFields = new List<string>();
            var unmatchedKeys = new List<string>();

            if (fieldValues != null && fieldValues.Count > 0)
            {
                if (fieldValues.Count > 300)
                    throw new InvalidOperationException("Sicherheitslimit überschritten: Maximal 300 Formularfeld-Zuweisungen pro Aufruf erlaubt.");

                foreach (var kv in fieldValues)
                {
                    string requestedKey = kv.Key.Trim();
                    string newVal = CleanPdfInputText(kv.Value, 4000);

                    var matches = fields.Where(f =>
                        string.Equals(f.Name, kv.Key, StringComparison.Ordinal) ||
                        string.Equals(f.Name.Trim(), requestedKey, StringComparison.OrdinalIgnoreCase)).ToList();

                    if (matches.Count == 0)
                    {
                        unmatchedKeys.Add(kv.Key);
                        continue;
                    }

                    foreach (var field in matches)
                    {
                        if (field.IsReadOnly || field.Kind == "SignatureTrigger") continue;

                        if (field.Kind == "CheckBox" || field.Kind == "RadioButton")
                        {
                            bool check = newVal.Equals("true", StringComparison.OrdinalIgnoreCase)
                                      || newVal.Equals("1", StringComparison.Ordinal)
                                      || newVal.Equals("x", StringComparison.OrdinalIgnoreCase)
                                      || newVal.Equals("ja", StringComparison.OrdinalIgnoreCase)
                                      || newVal.Equals("yes", StringComparison.OrdinalIgnoreCase);

                            if (field.Kind == "RadioButton" && check)
                            {
                                foreach (var sib in fields.Where(s => s.Kind == "RadioButton" && s.GroupId == field.GroupId))
                                    sib.IsChecked = (sib == field);
                            }
                            else
                            {
                                field.IsChecked = check;
                            }
                        }
                        else if (field.IsTinyBoxText)
                        {
                            bool clear = string.IsNullOrWhiteSpace(newVal)
                                      || newVal.Equals("false", StringComparison.OrdinalIgnoreCase)
                                      || newVal.Equals("0", StringComparison.Ordinal)
                                      || newVal.Equals("nein", StringComparison.OrdinalIgnoreCase);
                            field.Value = clear ? "" : (newVal.Equals("true", StringComparison.OrdinalIgnoreCase) ? "X" : newVal);
                        }
                        else
                        {
                            field.Value = field.MaxLength > 0 && newVal.Length > field.MaxLength
                                ? newVal.Substring(0, field.MaxLength)
                                : newVal;
                        }

                        updatedFields.Add(field.Name);
                    }
                }
            }

            using var inMs = new MemoryStream(sourcePdfBytes);
            using var doc = PdfReader.Open(inMs, PdfDocumentOpenMode.Modify);

            if (fields.Count > 0)
            {
                var acro = DerefDict(doc.Internals.Catalog.Elements["/AcroForm"]);
                if (acro != null)
                    acro.Elements.SetBoolean("/NeedAppearances", false);

                foreach (var pageGroup in fields.GroupBy(f => f.PageIndex))
                {
                    if (pageGroup.Key < 0 || pageGroup.Key >= doc.PageCount) continue;
                    var page = doc.Pages[pageGroup.Key];
                    var annots = DerefArr(page.Elements["/Annots"]);
                    if (annots == null) continue;

                    foreach (var field in pageGroup)
                    {
                        if (field.IsReadOnly || field.Kind == "SignatureTrigger") continue;
                        if (field.AnnotIndex < 0 || field.AnnotIndex >= annots.Elements.Count) continue;

                        var widget = DerefDict(annots.Elements[field.AnnotIndex]);
                        if (widget == null) continue;

                        var parent = DerefDict(widget.Elements["/Parent"]);
                        var valueHolder = (parent != null && !widget.Elements.ContainsKey("/T")) ? parent : widget;
                        var rect = widget.Elements.GetRectangle("/Rect");
                        double w = Math.Max(2, Math.Abs(rect.X2 - rect.X1));
                        double h = Math.Max(2, Math.Abs(rect.Y2 - rect.Y1));

                        if (field.Kind == "Text" || field.Kind == "ComboBox")
                        {
                            string val = field.Value ?? "";
                            valueHolder.Elements.SetString("/V", val);
                            if (valueHolder != widget && widget.Elements.ContainsKey("/V"))
                                widget.Elements.SetString("/V", val);

                            var apStream = BuildTextAppearanceXObject(doc, val, w, h, field.FontSizePt, field.IsMultiline, field.Alignment);
                            var apDict = new PdfDictionary(doc);
                            apDict.Elements["/N"] = apStream.Reference!;
                            widget.Elements["/AP"] = apDict;
                        }
                        else if (field.Kind == "CheckBox" || field.Kind == "RadioButton")
                        {
                            string stateName = field.IsChecked ? field.OnStateName : "/Off";
                            if (!stateName.StartsWith("/")) stateName = "/" + stateName;

                            widget.Elements.SetName("/AS", stateName);
                            if (field.Kind == "CheckBox" || field.IsChecked || parent == null)
                                valueHolder.Elements.SetName("/V", stateName);

                            var existingAp = DerefDict(widget.Elements["/AP"]);
                            var existingN = existingAp != null ? DerefDict(existingAp.Elements["/N"]) : null;
                            bool hasValidStateDict = existingN != null && existingN.Stream == null && existingN.Elements.ContainsKey(stateName);
                            if (!hasValidStateDict)
                            {
                                var stateDict = new PdfDictionary(doc);
                                var offObj = BuildCheckAppearanceXObject(doc, false, field.Kind == "RadioButton", w, h);
                                var onObj = BuildCheckAppearanceXObject(doc, true, field.Kind == "RadioButton", w, h);
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
            }

            if (overlays != null && overlays.Count > 0)
            {
                if (overlays.Count > 150)
                    throw new InvalidOperationException("Sicherheitslimit überschritten: Maximal 150 Vektor-Overlays pro Aufruf erlaubt.");

                foreach (var pageOverlays in overlays.GroupBy(o => Math.Max(0, o.PageNumber - 1)))
                {
                    if (pageOverlays.Key < 0 || pageOverlays.Key >= doc.PageCount) continue;
                    var page = doc.Pages[pageOverlays.Key];
                    using var gfx = XGraphics.FromPdfPage(page, XGraphicsPdfPageOptions.Append);

                    foreach (var ov in pageOverlays)
                    {
                        double safeX = ClampFinite(ov.XPt, -500, 5000, 0);
                        double safeY = ClampFinite(ov.YPt, -500, 5000, 0);
                        double safeW = ClampFinite(ov.WidthPt, 2, 3000, 160);
                        double safeH = ClampFinite(ov.HeightPt, 2, 3000, 20);
                        double safeFont = ClampFinite(ov.FontSizePt, 6, 48, 11);
                        string safeText = CleanPdfInputText(ov.Text, 2000);

                        string t = (ov.Type ?? "text").Trim().ToLowerInvariant();
                        if (t == "whiteout")
                        {
                            gfx.DrawRectangle(XBrushes.White, safeX, safeY, safeW, safeH);
                            if (!string.IsNullOrWhiteSpace(safeText))
                            {
                                var font = new XFont("Arial", safeFont, XFontStyleEx.Regular);
                                gfx.DrawString(safeText, font, XBrushes.Black, new XPoint(safeX + 2, safeY + safeFont + 1));
                            }
                        }
                        else if (t == "check" || t == "cross")
                        {
                            double w = Math.Max(8, safeW);
                            double h = Math.Max(8, safeH);
                            var pen = new XPen(XColors.Black, Math.Max(1.4, Math.Min(w, h) * 0.13))
                            {
                                LineCap = XLineCap.Round,
                                LineJoin = XLineJoin.Round
                            };
                            if (t == "check")
                            {
                                gfx.DrawLines(pen, new[]
                                {
                                    new XPoint(safeX + w * (3.5 / 24.0), safeY + h * (13.2 / 24.0)),
                                    new XPoint(safeX + w * (9.6 / 24.0), safeY + h * (19.5 / 24.0)),
                                    new XPoint(safeX + w * (20.8 / 24.0), safeY + h * (4.8 / 24.0))
                                });
                            }
                            else
                            {
                                double px = w * (4.5 / 24.0), py = h * (4.5 / 24.0);
                                gfx.DrawLine(pen, safeX + px, safeY + py, safeX + w - px, safeY + h - py);
                                gfx.DrawLine(pen, safeX + w - px, safeY + py, safeX + px, safeY + h - py);
                            }
                        }
                        else if (!string.IsNullOrWhiteSpace(safeText))
                        {
                            var font = new XFont("Arial", safeFont, XFontStyleEx.Regular);
                            gfx.DrawString(safeText, font, XBrushes.Black, new XPoint(safeX, safeY + safeFont));
                        }
                    }
                }
            }

            using var outMs = new MemoryStream();
            doc.Save(outMs, false);
            return (outMs.ToArray(), updatedFields.Distinct().ToList(), unmatchedKeys);
        }

        public static Dictionary<string, string> BuildSeelenfunkeStammdatenMapping(IReadOnlyList<McpPdfFieldDto> fields)
        {
            var autoMap = new Dictionary<string, string>(StringComparer.Ordinal);
            string today = DateTime.Now.ToString("dd.MM.yyyy");

            foreach (var f in fields)
            {
                if (f.IsReadOnly || f.Kind != "Text" || f.IsTinyBoxText) continue;
                string n = f.Name.Trim().ToLowerInvariant();

                if (n.Contains("bank-name") || n.Contains("bankname") || n == "kreditinstitut" || n == "bank")
                    autoMap[f.Name] = "FYRST (Deutsche Bank AG)";
                else if (n.Contains("bank-adresse") || n.Contains("bankadresse"))
                    continue; // Leave specific bank branch address empty unless explicitly overridden
                else if (n.Contains("weitere firmen"))
                    continue;
                else if (n.Contains("firmenname") || n.Contains("unternehmen") || n.Contains("firma") || n.Contains("company"))
                    autoMap[f.Name] = "Mein-Seelenfunke";
                else if (n.Contains("inhaber") || n.Contains("geschäftsführer") || n.Contains("geschaeftsfuehrer") || n.Contains("wirtschaftlich berechtigt") || n.Contains("wirstschaftlich berechtigt") || n == "name" || n == "vor- und nachname")
                    autoMap[f.Name] = "Alina Steinhauer";
                else if (n.Contains("titel / funktion") || n.Contains("funktion") || n.Contains("position"))
                    autoMap[f.Name] = "Inhaberin";
                else if (n.Contains("straße") || n.Contains("strasse") || n.Contains("anschrift") || n == "adresse")
                    autoMap[f.Name] = "Carl-Goerdeler-Ring 26";
                else if (n.Contains("postleitzahl") || n == "plz" || n == "zip")
                    autoMap[f.Name] = "38518";
                else if (n == "stadt" || n == "ort" || n == "wohnort" || n == "firmensitz" || n == "city")
                    autoMap[f.Name] = "Gifhorn";
                else if (n.Contains("bundesland") || n == "state")
                    autoMap[f.Name] = "Niedersachsen";
                else if (n == "land" || n == "staat" || n == "country")
                    autoMap[f.Name] = "Deutschland";
                else if (n.Contains("telefon") || n.Contains("mobil") || n.Contains("phone") || n == "tel" || n == "tel.")
                    autoMap[f.Name] = "+49 1590 1966864";
                else if (n.Contains("mail"))
                    autoMap[f.Name] = "kontakt@mein-seelenfunke.de";
                else if (n.Contains("internetseite") || n.Contains("webseite") || n.Contains("website") || n.Contains("homepage") || n == "url")
                    autoMap[f.Name] = "https://mein-seelenfunke.de";
                else if (n.Contains("haupttätigkeit") || n.Contains("haupttaetigkeit") || n.Contains("geschäftszweck") || n.Contains("branche"))
                    autoMap[f.Name] = "Personalisierte Geschenkartikel & Schmuck-Veredelung (UV-Laser-Manufaktur)";
                else if (n == "datum" || n == "date")
                    autoMap[f.Name] = $"Gifhorn, {today}";
                else if (n == "ort, datum" || n == "ort und datum")
                    autoMap[f.Name] = $"Gifhorn, {today}";
            }

            return autoMap;
        }

        private static PdfDictionary BuildTextAppearanceXObject(
            PdfDocument doc, string text, double w, double h, double requestedFontSize, bool isMultiline, int alignment)
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
            void WriteAscii(string s) { var b = Encoding.ASCII.GetBytes(s); ms.Write(b, 0, b.Length); }

            if (string.IsNullOrEmpty(text))
            {
                WriteAscii("/Tx BMC q Q EMC");
            }
            else
            {
                double fontSize = isMultiline
                    ? Math.Clamp(requestedFontSize, 7.0, 14.0)
                    : Math.Min(requestedFontSize, Math.Max(6.5, h * 0.78));

                WriteAscii(string.Format(CultureInfo.InvariantCulture,
                    "/Tx BMC q 0 0 {0:0.##} {1:0.##} re W n BT /Helv {2:0.##} Tf 0 g ", w, h, fontSize));

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
                    int maxChars = Math.Max(8, (int)Math.Floor((w - 4.0) / (fontSize * 0.50)));
                    var lines = new List<string>();
                    foreach (string para in text.Replace("\r\n", "\n").Replace('\r', '\n').Split('\n'))
                    {
                        if (para.Length <= maxChars) { lines.Add(para); continue; }
                        var cur = new StringBuilder();
                        foreach (string word in para.Split(' '))
                        {
                            if (cur.Length == 0) cur.Append(word);
                            else if (cur.Length + 1 + word.Length <= maxChars) cur.Append(' ').Append(word);
                            else { lines.Add(cur.ToString()); cur.Clear().Append(word); }
                        }
                        if (cur.Length > 0) lines.Add(cur.ToString());
                    }

                    double lineHeight = fontSize * 1.22;
                    double curY = h - fontSize - 2.0;
                    for (int i = 0; i < lines.Count; i++)
                    {
                        if (curY < -fontSize) break;
                        WriteAscii(i == 0
                            ? string.Format(CultureInfo.InvariantCulture, "2 {0:0.##} Td (", curY)
                            : string.Format(CultureInfo.InvariantCulture, "0 {0:0.##} Td (", -lineHeight));
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
                double cx = w * 0.5, cy = h * 0.5, r = Math.Min(w, h) * 0.28, k = r * 0.55228;
                content = string.Format(CultureInfo.InvariantCulture,
                    "q 0.08 0.08 0.08 rg {0:0.##} {1:0.##} m {2:0.##} {1:0.##} {3:0.##} {4:0.##} {3:0.##} {5:0.##} c {3:0.##} {6:0.##} {2:0.##} {7:0.##} {0:0.##} {7:0.##} c {8:0.##} {7:0.##} {9:0.##} {6:0.##} {9:0.##} {5:0.##} c {9:0.##} {4:0.##} {8:0.##} {1:0.##} {0:0.##} {1:0.##} c f Q",
                    cx, cy + r, cx + k, cx + r, cy + k, cy, cy - k, cy - r, cx - k, cx - r);
            }
            else
            {
                double sw = Math.Max(1.2, Math.Min(w, h) * 0.14);
                content = string.Format(CultureInfo.InvariantCulture,
                    "q 0.08 0.08 0.08 RG {0:0.##} w 1 J 1 j {1:0.##} {2:0.##} m {3:0.##} {4:0.##} l {5:0.##} {6:0.##} l S Q",
                    sw, w * 0.18, h * 0.46, w * 0.42, h * 0.20, w * 0.84, h * 0.78);
            }

            formXObj.CreateStream(Encoding.ASCII.GetBytes(content));
            doc.Internals.AddObject(formXObj);
            return formXObj;
        }

        private static void WriteEscapedWinAnsi(Stream ms, string s)
        {
            foreach (byte b in WinAnsi.GetBytes(s))
            {
                if (b == (byte)'(' || b == (byte)')' || b == (byte)'\\')
                    ms.WriteByte((byte)'\\');
                ms.WriteByte(b);
            }
        }
    }
}
