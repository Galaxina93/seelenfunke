using System;
using System.Collections.Generic;
using System.IO;
using System.Linq;
using System.Text;
using System.Threading.Tasks;
using System.Windows;
using UglyToad.PdfPig;
using UglyToad.PdfPig.Content;
using Windows.Graphics.Imaging;
using Windows.Media.Ocr;
using Windows.Storage.Streams;

namespace FunkenwerkPdf
{
    public sealed class PdfTextGlyph
    {
        public int GlobalIndex { get; set; }
        public int PageIndex { get; init; }
        public int LineIndex { get; init; }
        public int WordIndex { get; init; }
        public string Text { get; init; } = "";
        public Rect BoundsPt { get; set; }
    }

    public sealed class PdfTextWordInfo
    {
        public int PageIndex { get; init; }
        public int LineIndex { get; set; }
        public int WordIndex { get; set; }
        public string Text { get; init; } = "";
        public Rect BoundsPt { get; set; }
        public int FirstGlyphGlobalIndex { get; set; } = -1;
        public int LastGlyphGlobalIndex { get; set; } = -1;
        public List<PdfTextGlyph> Glyphs { get; } = new();
    }

    public sealed class PdfTextLineInfo
    {
        public int PageIndex { get; init; }
        public int LineIndex { get; set; }
        public double TopPt { get; set; }
        public double BottomPt { get; set; }
        public double CenterYPt => (TopPt + BottomPt) * 0.5;
        public double HeightPt => Math.Max(4.0, BottomPt - TopPt);
        public List<PdfTextWordInfo> Words { get; } = new();
        public int FirstGlyphGlobalIndex { get; set; } = -1;
        public int LastGlyphGlobalIndex { get; set; } = -1;
    }

    public sealed class PdfPageTextLayout
    {
        public int PageIndex { get; init; }
        public double WidthPt { get; set; } = 595.0;
        public double HeightPt { get; set; } = 842.0;
        public bool HasEmbeddedText { get; set; }
        public bool OcrAttempted { get; set; }
        public List<PdfTextLineInfo> Lines { get; } = new();
    }

    public sealed class PdfSelectionComputation
    {
        public string Text { get; init; } = "";
        public Dictionary<int, List<Rect>> HighlightRectsByPage { get; init; } = new();
        public int EndPageIndex { get; init; }
        public Rect LastHighlightRectPt { get; init; }
    }

    /// <summary>
    /// Extracts reading-order text lines, words, and character bounding boxes (in top-left PDF points)
    /// using PdfPig, with automatic offline Windows OCR fallback for scanned image-only pages.
    /// </summary>
    public sealed class PdfTextSelectionEngine
    {
        private readonly object _sync = new();
        private readonly List<PdfPageTextLayout> _pages = new();
        private readonly List<PdfTextGlyph> _allGlyphs = new();

        public int PageCount
        {
            get { lock (_sync) return _pages.Count; }
        }

        public static PdfTextSelectionEngine CreateFromPdfBytes(byte[]? pdfBytes)
        {
            var engine = new PdfTextSelectionEngine();
            if (pdfBytes == null || pdfBytes.Length == 0)
                return engine;

            try
            {
                using var doc = PdfDocument.Open(pdfBytes);
                for (int p = 1; p <= doc.NumberOfPages; p++)
                {
                    int pageIdx = p - 1;
                    var page = doc.GetPage(p);
                    double pageW = Math.Max(10.0, page.Width);
                    double pageH = Math.Max(10.0, page.Height);

                    var pageLayout = new PdfPageTextLayout
                    {
                        PageIndex = pageIdx,
                        WidthPt = pageW,
                        HeightPt = pageH
                    };

                    var rawWords = page.GetWords()?.ToList() ?? new List<Word>();
                    var wordInfos = new List<PdfTextWordInfo>();

                    foreach (var w in rawWords)
                    {
                        if (string.IsNullOrWhiteSpace(w.Text))
                            continue;

                        var bb = w.BoundingBox;
                        double wLeft = Math.Clamp(bb.Left, 0, pageW);
                        double wTop = Math.Clamp(pageH - bb.Top, 0, pageH);
                        double wWidth = Math.Max(1.2, bb.Width);
                        double wHeight = Math.Max(3.5, bb.Height);

                        var wordInfo = new PdfTextWordInfo
                        {
                            PageIndex = pageIdx,
                            Text = w.Text,
                            BoundsPt = new Rect(wLeft, wTop, wWidth, wHeight)
                        };

                        var letters = w.Letters?.OrderBy(l => l.BoundingBox.Left).ToList();
                        if (letters != null && letters.Count > 0)
                        {
                            foreach (var letter in letters)
                            {
                                if (string.IsNullOrEmpty(letter.Value))
                                    continue;
                                var lb = letter.BoundingBox;
                                double lx = Math.Clamp(lb.Left, 0, pageW);
                                double ly = Math.Clamp(pageH - lb.Top, 0, pageH);
                                double lw = Math.Max(0.8, lb.Width);
                                double lh = Math.Max(3.0, lb.Height);

                                wordInfo.Glyphs.Add(new PdfTextGlyph
                                {
                                    PageIndex = pageIdx,
                                    Text = letter.Value,
                                    BoundsPt = new Rect(lx, ly, lw, lh)
                                });
                            }
                        }

                        if (wordInfo.Glyphs.Count == 0)
                        {
                            SynthesizeEvenGlyphs(wordInfo);
                        }

                        wordInfos.Add(wordInfo);
                    }

                    BuildLinesForPage(pageLayout, wordInfos);
                    pageLayout.HasEmbeddedText = pageLayout.Lines.Count > 0;
                    engine._pages.Add(pageLayout);
                }

                engine.RebuildGlobalGlyphIndexNoLock();
            }
            catch
            {
                // Keep empty layout if malformed PDF text stream encounters an issue
            }

            return engine;
        }

        public bool NeedsScannedPageOcr(int pageIndex)
        {
            lock (_sync)
            {
                if (pageIndex < 0 || pageIndex >= _pages.Count) return false;
                var p = _pages[pageIndex];
                return !p.HasEmbeddedText && !p.OcrAttempted;
            }
        }

        public async Task TryPopulateOcrFromRenderedStreamAsync(int pageIndex, IRandomAccessStream renderedPngStream, double pageWidthPt, double pageHeightPt)
        {
            lock (_sync)
            {
                if (pageIndex < 0 || pageIndex >= _pages.Count) return;
                if (_pages[pageIndex].HasEmbeddedText || _pages[pageIndex].OcrAttempted) return;
                _pages[pageIndex].OcrAttempted = true;
            }

            try
            {
                var ocrEngine = OcrEngine.TryCreateFromUserProfileLanguages();
                if (ocrEngine == null) return;

                renderedPngStream.Seek(0);
                var decoder = await BitmapDecoder.CreateAsync(renderedPngStream);
                using var softwareBitmap = await decoder.GetSoftwareBitmapAsync(BitmapPixelFormat.Bgra8, BitmapAlphaMode.Premultiplied);

                double bmpW = Math.Max(1.0, softwareBitmap.PixelWidth);
                double bmpH = Math.Max(1.0, softwareBitmap.PixelHeight);
                if (bmpW > OcrEngine.MaxImageDimension || bmpH > OcrEngine.MaxImageDimension)
                    return;

                var ocrResult = await ocrEngine.RecognizeAsync(softwareBitmap);
                if (ocrResult?.Lines == null || ocrResult.Lines.Count == 0)
                    return;

                double scaleX = pageWidthPt / bmpW;
                double scaleY = pageHeightPt / bmpH;

                var wordInfos = new List<PdfTextWordInfo>();
                foreach (var ocrLine in ocrResult.Lines)
                {
                    foreach (var ocrWord in ocrLine.Words)
                    {
                        if (string.IsNullOrWhiteSpace(ocrWord.Text)) continue;
                        var r = ocrWord.BoundingRect;
                        var rectPt = new Rect(
                            r.X * scaleX,
                            r.Y * scaleY,
                            Math.Max(1.5, r.Width * scaleX),
                            Math.Max(4.0, r.Height * scaleY));

                        var wi = new PdfTextWordInfo
                        {
                            PageIndex = pageIndex,
                            Text = ocrWord.Text,
                            BoundsPt = rectPt
                        };
                        SynthesizeEvenGlyphs(wi);
                        wordInfos.Add(wi);
                    }
                }

                if (wordInfos.Count == 0) return;

                lock (_sync)
                {
                    if (pageIndex < 0 || pageIndex >= _pages.Count) return;
                    var layout = _pages[pageIndex];
                    layout.WidthPt = pageWidthPt;
                    layout.HeightPt = pageHeightPt;
                    layout.Lines.Clear();
                    BuildLinesForPage(layout, wordInfos);
                    layout.HasEmbeddedText = layout.Lines.Count > 0;
                    RebuildGlobalGlyphIndexNoLock();
                }
            }
            catch
            {
                // Silent fallback if Windows OCR pack is unavailable
            }
        }

        private static void SynthesizeEvenGlyphs(PdfTextWordInfo wordInfo)
        {
            string t = wordInfo.Text;
            if (string.IsNullOrEmpty(t)) return;
            double stepW = Math.Max(0.8, wordInfo.BoundsPt.Width / t.Length);
            for (int i = 0; i < t.Length; i++)
            {
                wordInfo.Glyphs.Add(new PdfTextGlyph
                {
                    PageIndex = wordInfo.PageIndex,
                    Text = t[i].ToString(),
                    BoundsPt = new Rect(
                        wordInfo.BoundsPt.Left + i * stepW,
                        wordInfo.BoundsPt.Top,
                        stepW,
                        wordInfo.BoundsPt.Height)
                });
            }
        }

        private static void BuildLinesForPage(PdfPageTextLayout pageLayout, List<PdfTextWordInfo> words)
        {
            if (words.Count == 0) return;

            var sorted = words
                .OrderBy(w => w.BoundsPt.Top + w.BoundsPt.Height * 0.5)
                .ThenBy(w => w.BoundsPt.Left)
                .ToList();

            var lines = new List<PdfTextLineInfo>();
            foreach (var word in sorted)
            {
                double wordCenterY = word.BoundsPt.Top + word.BoundsPt.Height * 0.5;
                PdfTextLineInfo? bestLine = null;
                double bestDy = double.MaxValue;

                foreach (var candidate in lines)
                {
                    double tol = Math.Max(candidate.HeightPt, word.BoundsPt.Height) * 0.55;
                    double dy = Math.Abs(wordCenterY - candidate.CenterYPt);
                    if (dy <= tol && dy < bestDy)
                    {
                        bestDy = dy;
                        bestLine = candidate;
                    }
                }

                if (bestLine == null)
                {
                    bestLine = new PdfTextLineInfo
                    {
                        PageIndex = pageLayout.PageIndex,
                        TopPt = word.BoundsPt.Top,
                        BottomPt = word.BoundsPt.Bottom
                    };
                    bestLine.Words.Add(word);
                    lines.Add(bestLine);
                }
                else
                {
                    bestLine.Words.Add(word);
                    bestLine.TopPt = Math.Min(bestLine.TopPt, word.BoundsPt.Top);
                    bestLine.BottomPt = Math.Max(bestLine.BottomPt, word.BoundsPt.Bottom);
                }
            }

            lines = lines.OrderBy(l => l.CenterYPt).ToList();

            for (int lineIdx = 0; lineIdx < lines.Count; lineIdx++)
            {
                var ln = lines[lineIdx];
                ln.LineIndex = lineIdx;

                double padY = Math.Clamp(ln.HeightPt * 0.12, 0.8, 2.2);
                double normTop = Math.Max(0, ln.TopPt - padY);
                double normBottom = Math.Min(pageLayout.HeightPt, ln.BottomPt + padY);
                ln.TopPt = normTop;
                ln.BottomPt = normBottom;

                var orderedWords = ln.Words.OrderBy(w => w.BoundsPt.Left).ToList();
                ln.Words.Clear();

                for (int wIdx = 0; wIdx < orderedWords.Count; wIdx++)
                {
                    var w = orderedWords[wIdx];
                    w.LineIndex = lineIdx;
                    w.WordIndex = wIdx;
                    w.BoundsPt = new Rect(w.BoundsPt.Left, normTop, w.BoundsPt.Width, Math.Max(4.0, normBottom - normTop));

                    var rebuiltGlyphs = new List<PdfTextGlyph>(w.Glyphs.Count);
                    foreach (var g in w.Glyphs)
                    {
                        rebuiltGlyphs.Add(new PdfTextGlyph
                        {
                            PageIndex = pageLayout.PageIndex,
                            LineIndex = lineIdx,
                            WordIndex = wIdx,
                            Text = g.Text,
                            BoundsPt = new Rect(g.BoundsPt.Left, normTop, g.BoundsPt.Width, Math.Max(4.0, normBottom - normTop))
                        });
                    }
                    w.Glyphs.Clear();
                    w.Glyphs.AddRange(rebuiltGlyphs);
                    ln.Words.Add(w);
                }

                pageLayout.Lines.Add(ln);
            }
        }

        private void RebuildGlobalGlyphIndexNoLock()
        {
            _allGlyphs.Clear();
            int globalIdx = 0;

            foreach (var page in _pages)
            {
                foreach (var line in page.Lines)
                {
                    line.FirstGlyphGlobalIndex = globalIdx;
                    foreach (var word in line.Words)
                    {
                        word.FirstGlyphGlobalIndex = globalIdx;
                        foreach (var g in word.Glyphs)
                        {
                            g.GlobalIndex = globalIdx++;
                            _allGlyphs.Add(g);
                        }
                        word.LastGlyphGlobalIndex = globalIdx - 1;
                    }
                    line.LastGlyphGlobalIndex = globalIdx - 1;
                }
            }
        }

        public bool IsPointOverText(int pageIndex, Point ptPt)
        {
            lock (_sync)
            {
                if (pageIndex < 0 || pageIndex >= _pages.Count) return false;
                var page = _pages[pageIndex];
                foreach (var line in page.Lines)
                {
                    if (ptPt.Y < line.TopPt - 3.0 || ptPt.Y > line.BottomPt + 3.0)
                        continue;
                    foreach (var word in line.Words)
                    {
                        Rect inflated = word.BoundsPt;
                        inflated.Inflate(3.5, 2.5);
                        if (inflated.Contains(ptPt))
                            return true;
                    }
                }
                return false;
            }
        }

        public PdfSelectionComputation? SelectWordAt(int pageIndex, Point ptPt)
        {
            lock (_sync)
            {
                if (pageIndex < 0 || pageIndex >= _pages.Count) return null;
                var page = _pages[pageIndex];
                PdfTextWordInfo? bestWord = null;
                double bestDist = double.MaxValue;

                foreach (var line in page.Lines)
                {
                    if (ptPt.Y < line.TopPt - 6.0 || ptPt.Y > line.BottomPt + 6.0)
                        continue;
                    foreach (var word in line.Words)
                    {
                        double dx = ptPt.X < word.BoundsPt.Left ? word.BoundsPt.Left - ptPt.X
                                  : (ptPt.X > word.BoundsPt.Right ? ptPt.X - word.BoundsPt.Right : 0.0);
                        double dy = ptPt.Y < word.BoundsPt.Top ? word.BoundsPt.Top - ptPt.Y
                                  : (ptPt.Y > word.BoundsPt.Bottom ? ptPt.Y - word.BoundsPt.Bottom : 0.0);
                        double dist = dx + dy * 2.0;
                        if (dist <= 12.0 && dist < bestDist)
                        {
                            bestDist = dist;
                            bestWord = word;
                        }
                    }
                }

                if (bestWord == null || bestWord.FirstGlyphGlobalIndex < 0)
                    return null;

                return BuildSelectionFromGlyphSpanNoLock(bestWord.FirstGlyphGlobalIndex, bestWord.LastGlyphGlobalIndex);
            }
        }

        public PdfSelectionComputation? SelectLineAt(int pageIndex, Point ptPt)
        {
            lock (_sync)
            {
                if (pageIndex < 0 || pageIndex >= _pages.Count) return null;
                var page = _pages[pageIndex];
                var line = page.Lines
                    .Where(l => ptPt.Y >= l.TopPt - 8.0 && ptPt.Y <= l.BottomPt + 8.0)
                    .OrderBy(l => Math.Abs(ptPt.Y - l.CenterYPt))
                    .FirstOrDefault();

                if (line == null || line.FirstGlyphGlobalIndex < 0)
                    return null;

                return BuildSelectionFromGlyphSpanNoLock(line.FirstGlyphGlobalIndex, line.LastGlyphGlobalIndex);
            }
        }

        public PdfSelectionComputation? SelectAllOnPage(int pageIndex)
        {
            lock (_sync)
            {
                if (pageIndex < 0 || pageIndex >= _pages.Count) return null;
                var page = _pages[pageIndex];
                if (page.Lines.Count == 0) return null;
                int start = page.Lines.First().FirstGlyphGlobalIndex;
                int end = page.Lines.Last().LastGlyphGlobalIndex;
                if (start < 0 || end < start) return null;
                return BuildSelectionFromGlyphSpanNoLock(start, end);
            }
        }

        public PdfSelectionComputation? SelectRange(int startPageIndex, Point startPt, int endPageIndex, Point endPt)
        {
            lock (_sync)
            {
                if (_allGlyphs.Count == 0) return null;

                int g1 = MapPointToNearestGlyphIndexNoLock(startPageIndex, startPt);
                int g2 = MapPointToNearestGlyphIndexNoLock(endPageIndex, endPt);
                if (g1 < 0 || g2 < 0) return null;

                int minIdx = Math.Min(g1, g2);
                int maxIdx = Math.Max(g1, g2);
                return BuildSelectionFromGlyphSpanNoLock(minIdx, maxIdx);
            }
        }

        private int MapPointToNearestGlyphIndexNoLock(int pageIndex, Point ptPt)
        {
            if (_pages.Count == 0 || _allGlyphs.Count == 0) return -1;
            int clampedPage = Math.Clamp(pageIndex, 0, _pages.Count - 1);
            var page = _pages[clampedPage];

            if (page.Lines.Count == 0)
            {
                for (int offset = 1; offset < _pages.Count; offset++)
                {
                    if (clampedPage - offset >= 0 && _pages[clampedPage - offset].Lines.Count > 0)
                        return _pages[clampedPage - offset].Lines.Last().LastGlyphGlobalIndex;
                    if (clampedPage + offset < _pages.Count && _pages[clampedPage + offset].Lines.Count > 0)
                        return _pages[clampedPage + offset].Lines.First().FirstGlyphGlobalIndex;
                }
                return -1;
            }

            var firstLine = page.Lines[0];
            var lastLine = page.Lines[page.Lines.Count - 1];

            if (ptPt.Y < firstLine.TopPt - 10.0)
                return firstLine.FirstGlyphGlobalIndex;
            if (ptPt.Y > lastLine.BottomPt + 10.0)
                return lastLine.LastGlyphGlobalIndex;

            PdfTextLineInfo chosenLine = firstLine;
            double bestLineDist = double.MaxValue;
            foreach (var ln in page.Lines)
            {
                double vDist = ptPt.Y < ln.TopPt ? ln.TopPt - ptPt.Y
                             : (ptPt.Y > ln.BottomPt ? ptPt.Y - ln.BottomPt : 0.0);
                if (vDist < bestLineDist)
                {
                    bestLineDist = vDist;
                    chosenLine = ln;
                }
            }

            int bestGlyph = chosenLine.FirstGlyphGlobalIndex;
            double bestXDist = double.MaxValue;

            for (int gi = chosenLine.FirstGlyphGlobalIndex; gi <= chosenLine.LastGlyphGlobalIndex; gi++)
            {
                var g = _allGlyphs[gi];
                double centerX = g.BoundsPt.Left + g.BoundsPt.Width * 0.5;
                double dx = Math.Abs(ptPt.X - centerX);
                if (dx < bestXDist)
                {
                    bestXDist = dx;
                    bestGlyph = gi;
                }
            }

            return bestGlyph;
        }

        private PdfSelectionComputation? BuildSelectionFromGlyphSpanNoLock(int minIdx, int maxIdx)
        {
            if (minIdx < 0 || maxIdx < minIdx || maxIdx >= _allGlyphs.Count)
                return null;

            var sb = new StringBuilder(Math.Max(32, maxIdx - minIdx + 16));
            var rectsByPage = new Dictionary<int, List<Rect>>();

            int curRectPage = -1;
            int curRectLine = -1;
            double curLeft = 0, curRight = 0, curTop = 0, curBottom = 0;

            void FlushCurrentLineRect()
            {
                if (curRectPage < 0) return;
                if (!rectsByPage.TryGetValue(curRectPage, out var list))
                {
                    list = new List<Rect>();
                    rectsByPage[curRectPage] = list;
                }
                list.Add(new Rect(curLeft, curTop, Math.Max(2.0, curRight - curLeft), Math.Max(4.0, curBottom - curTop)));
                curRectPage = -1;
            }

            for (int i = minIdx; i <= maxIdx; i++)
            {
                var g = _allGlyphs[i];
                sb.Append(g.Text);

                if (g.PageIndex != curRectPage || g.LineIndex != curRectLine)
                {
                    FlushCurrentLineRect();
                    curRectPage = g.PageIndex;
                    curRectLine = g.LineIndex;
                    curLeft = g.BoundsPt.Left;
                    curRight = g.BoundsPt.Right;
                    curTop = g.BoundsPt.Top;
                    curBottom = g.BoundsPt.Bottom;
                }
                else
                {
                    curLeft = Math.Min(curLeft, g.BoundsPt.Left);
                    curRight = Math.Max(curRight, g.BoundsPt.Right);
                    curTop = Math.Min(curTop, g.BoundsPt.Top);
                    curBottom = Math.Max(curBottom, g.BoundsPt.Bottom);
                }

                if (i < maxIdx)
                {
                    var next = _allGlyphs[i + 1];
                    if (next.PageIndex != g.PageIndex)
                    {
                        sb.Append("\r\n\r\n");
                    }
                    else if (next.LineIndex != g.LineIndex)
                    {
                        sb.Append("\r\n");
                    }
                    else if (next.WordIndex != g.WordIndex)
                    {
                        sb.Append(' ');
                    }
                }
            }

            FlushCurrentLineRect();

            string finalText = sb.ToString();
            if (string.IsNullOrEmpty(finalText))
                return null;

            int endPage = _allGlyphs[maxIdx].PageIndex;
            Rect lastRect = rectsByPage.TryGetValue(endPage, out var endRects) && endRects.Count > 0
                ? endRects[endRects.Count - 1]
                : _allGlyphs[maxIdx].BoundsPt;

            return new PdfSelectionComputation
            {
                Text = finalText,
                HighlightRectsByPage = rectsByPage,
                EndPageIndex = endPage,
                LastHighlightRectPt = lastRect
            };
        }
    }
}
