using System;
using System.Collections.Concurrent;
using System.Collections.Generic;
using System.Diagnostics;
using System.Globalization;
using System.IO;
using System.IO.Compression;
using System.Linq;
using System.Security.Cryptography;
using System.Text;
using System.Text.RegularExpressions;
using System.Threading;
using System.Threading.Tasks;
using System.Xml.Linq;
using PdfSharp.Pdf;
using PdfSharp.Pdf.Content;
using PdfSharp.Pdf.Content.Objects;
using PdfSharp.Pdf.IO;
using Windows.Globalization;
using Windows.Graphics.Imaging;
using Windows.Media.Ocr;
using Windows.Storage;
using Windows.Storage.Streams;
using WinPdfDocument = Windows.Data.Pdf.PdfDocument;
using WinPdfPage = Windows.Data.Pdf.PdfPage;
using WinPdfPageRenderOptions = Windows.Data.Pdf.PdfPageRenderOptions;

namespace Funkenwerk
{
    public sealed class ArchiveSearchHit
    {
        public FunkenwerkFileDbRecord Record { get; init; } = new();
        public int Score { get; init; }
        public string Snippet { get; init; } = "";
        public double QueryLatencyMs { get; init; }
    }

    public sealed class ZeroTokenClassificationResult
    {
        public string MainFolder { get; init; } = "02_Finanzen_Steuern_Banken";
        public string SubFolder { get; init; } = "Rechnungen";
        public string SuggestedFilename { get; init; } = "";
        public string ExtractedDate { get; init; } = DateTime.Today.ToString("yyyy-MM-dd");
        public string Title { get; init; } = "";
        public string Category { get; init; } = "";
        public List<string> Tags { get; init; } = new();
        public string Purpose { get; init; } = "";
        public Dictionary<string, string> KeyFacts { get; init; } = new();
        public string Summary { get; init; } = "";
        public string SearchText { get; init; } = "";
    }

    /// <summary>
    /// Sub-millisecond 0-Token In-Memory Knowledge Index over all documents in C:\Users\konta\kDrive\Meine Daten.
    /// Uses inverted token posting lists, German umlaut normalization, domain synonym expansion,
    /// temporal ranking ("letzte / neueste / aktuellste"), and year matching.
    /// </summary>
    public sealed class FunkenwerkArchiveKnowledgeIndex
    {
        private readonly object _gate = new();
        private List<FunkenwerkFileDbRecord> _records = new();
        private List<string> _normFnTitle = new();
        private List<string> _normPath = new();
        private List<string> _normMeta = new();
        private List<string> _normPrimary = new();
        private List<string> _normFull = new();
        private List<DateTime> _parsedDates = new();
        private List<DateTime> _scanTimestamps = new();
        private Dictionary<string, HashSet<int>> _inverted = new(StringComparer.Ordinal);
        public DateTime LastRebuiltUtc { get; private set; } = DateTime.MinValue;

        private static readonly HashSet<string> StopWords = new(StringComparer.Ordinal)
        {
            "gib", "mir", "bitte", "mal", "den", "die", "das", "der", "dem", "des", "ein", "eine", "einen", "einem",
            "und", "oder", "von", "vom", "zu", "zum", "zur", "mit", "fuer", "fur", "ueber", "uber", "aus", "bei",
            "wo", "liegt", "liegen", "sind", "ist", "war", "habe", "ich", "wir", "uns", "mein", "meine", "meinen",
            "meiner", "zeig", "zeige", "such", "suche", "finde", "oeffne", "offne", "brauche", "dokument", "dokumente",
            "datei", "dateien", "unterlage", "unterlagen", "info", "infos", "was", "steht", "drin", "drauf", "alles",
            "alle", "noch", "schon", "hier", "dort", "nach", "in", "im", "am", "an", "auf", "als", "wie", "kannst", "du",
            "ueber", "app", "trichter", "eingang"
        };

        private static readonly HashSet<string> TemporalTokens = new(StringComparer.Ordinal)
        {
            "letzte", "letzter", "letztes", "letzten", "letztem",
            "neueste", "neuester", "neuestes", "neuesten", "aktuell",
            "aktuelle", "aktueller", "aktuelles", "aktuellste", "aktuellsten",
            "juengste", "juengster", "juengsten", "neue", "neuer", "neues",
            "eingescannt", "eingescannte", "eingescannten", "eingescanntes",
            "gescannt", "gescannte", "gescannten", "gescanntes",
            "scan", "scans", "hochgeladen", "hochgeladene", "hochgeladenes",
            "gerade", "eben", "soeben", "zuletzt", "heute", "heutige", "heutiges"
        };

        private static readonly Dictionary<string, string[]> DomainSynonyms = new(StringComparer.Ordinal)
        {
            ["rentenbescheid"] = new[] { "renteninformation", "renteninformationen", "renteninfo", "deutsche rentenversicherung", "drv", "rente", "feststellungsbescheid", "versorgungsausgleich", "kontenklaerung", "v0510", "v0800" },
            ["rente"] = new[] { "renteninformation", "renteninformationen", "renteninfo", "rentenversicherung", "deutsche rentenversicherung", "drv", "feststellungsbescheid", "versorgungsausgleich", "altersrente" },
            ["renteninfo"] = new[] { "renteninformation", "renteninformationen", "rentenversicherung", "drv" },
            ["renteninformation"] = new[] { "renteninformationen", "renteninfo", "rentenbescheid", "rentenversicherung", "drv" },
            ["drv"] = new[] { "deutsche rentenversicherung", "renteninformation", "renteninformationen", "rentenversicherung" },
            ["sozialversicherungsausweis"] = new[] { "rentenversicherung", "versicherungsnummer", "nachweis" },
            ["steuerbescheid"] = new[] { "einkommenssteuerbescheid", "einkommensteuerbescheid", "bescheid", "steuererklaerung", "finanzamt", "elster" },
            ["steuer"] = new[] { "steuern", "steuererklaerung", "steuerbescheid", "einkommensteuerbescheid", "einkommenssteuerbescheid", "finanzamt", "elster", "gewerbesteuer", "umsatzsteuer", "lohnsteuerbescheinigung" },
            ["finanzamt"] = new[] { "steuern", "steuerbescheid", "steuererklaerung", "elster", "gewerbesteuer", "umsatzsteuer" },
            ["arbeitsamt"] = new[] { "agentur fuer arbeit", "bundesagentur", "arbeitslosengeld", "alg", "gruendungszuschuss", "bewilligungsbescheid", "aufhebungsbescheid", "aenderungsbescheid", "schinke" },
            ["alg"] = new[] { "arbeitslosengeld", "bewilligungsbescheid", "aufhebungsbescheid", "agentur fuer arbeit" },
            ["gruendungszuschuss"] = new[] { "gruenderzuschuss", "tragfaehigkeit", "tragfaehigkeitsbescheinigung", "businessplan", "liquiditaetsplanung", "241d129534" },
            ["krankenkasse"] = new[] { "bkk firmus", "bkk", "firmus", "krankengeld", "barmer", "hek", "salus", "versicherungsverhaeltnis" },
            ["bkk"] = new[] { "bkk firmus", "firmus", "krankenkasse", "krankengeld", "sozialgericht", "eilantrag" },
            ["krankengeld"] = new[] { "bkk firmus", "sozialgericht", "eilantrag", "widerspruch", "aussteuerung", "s26kr337" },
            ["krankschreibung"] = new[] { "krankschreibungen", "krankmeldungen", "arbeitsunfaehigkeit", "au", "eau", "folgebescheinigung" },
            ["au"] = new[] { "krankschreibung", "krankschreibungen", "arbeitsunfaehigkeit", "eau", "krankmeldungen" },
            ["kaufvertrag"] = new[] { "notar", "esser", "haus", "immobilie", "grundbuch", "carl goerdeler ring" },
            ["notar"] = new[] { "esser", "kaufvertrag", "grundbuch", "haus", "immobilie" },
            ["hauskredit"] = new[] { "kredit", "volksbank", "darlehen", "lastenzuschuss", "grundschuld" },
            ["kredit"] = new[] { "darlehen", "hauskredit", "volksbank", "norisbank" },
            ["zaehlerstand"] = new[] { "zaehlerstaende", "wasserzaehler", "drehstromzaehler", "stromzaehler", "gaszaehler", "wasserwerk", "ebz" },
            ["zaehler"] = new[] { "zaehlerstaende", "zaehlerstand", "wasserwerk", "drehstromzaehler", "ebz", "lsw" },
            ["strom"] = new[] { "lsw", "vattenfall", "gruenewelt", "zaehlerstaende", "drehstromzaehler", "photovoltaik" },
            ["gas"] = new[] { "lsw", "komfortgas", "gruenewelt", "zaehlerstaende" },
            ["wasser"] = new[] { "wasserwerk", "lsw", "zwischenzaehler", "zaehlerstaende" },
            ["personalausweis"] = new[] { "ausweis", "vorderseite", "rueckseite", "beidseitig", "bundid" },
            ["ausweis"] = new[] { "personalausweis", "fuehrerschein", "sozialversicherungsausweis" },
            ["fuehrerschein"] = new[] { "ausweis", "fuehrerschein" },
            ["urkunde"] = new[] { "geburtsurkunde", "eheurkunde", "namensaenderung", "standesamt", "vaterschaft" },
            ["testament"] = new[] { "erbe", "todesfallcheckliste", "vorsorgevollmacht", "papa" },
            ["vollmacht"] = new[] { "vorsorgevollmacht", "testament", "papa", "erbe" },
            ["keepass"] = new[] { "kdbx", "db privat", "passwort", "tresor" },
            ["passwort"] = new[] { "keepass", "kdbx", "pw", "tresor", "1password" },
            ["gehaltsabrechnung"] = new[] { "gehalt", "lohnsteuerbescheinigung", "biceps", "skon", "high office", "computacenter" },
            ["gehalt"] = new[] { "gehaltsabrechnung", "lohnsteuerbescheinigung", "biceps", "skon", "high office", "computacenter" },
            ["arbeitszeugnis"] = new[] { "zeugnis", "high office", "biceps", "computacenter", "dis ag", "qualifikation" },
            ["seelenfunke"] = new[] { "mein seelenfunke", "gewerbeanmeldung", "businessplan", "xtool", "hiscox", "laserschutzbeauftragte" },
            ["laser"] = new[] { "xtool", "f2 ultra", "ra3", "laserschutzbeauftragte", "anleitung", "barthaarentfernung" },
            ["versicherung"] = new[] { "hiscox", "arag", "barmenia", "allianz", "axa", "ergo", "devk", "neodigital", "allsafe", "kravag", "verti" },
            ["vertrag"] = new[] { "telekom", "vodafone", "o2", "glasfaser", "handyvertrag", "simplytel", "all inkl", "rundfunk" },
            ["kontoauszug"] = new[] { "volksbank", "umsaetze", "kontoauszuege", "8583196000", "bank" },
            ["schufa"] = new[] { "schufaauszug", "bonitaet" }
        };

        public int Count
        {
            get { lock (_gate) return _records.Count; }
        }

        public Dictionary<string, int> GetFolderCounts()
        {
            lock (_gate)
            {
                return _records
                    .GroupBy(r => string.IsNullOrWhiteSpace(r.MainFolder) ? "Sonstiges" : r.MainFolder)
                    .OrderBy(g => g.Key)
                    .ToDictionary(g => g.Key, g => g.Count());
            }
        }

        public void Rebuild(IEnumerable<FunkenwerkFileDbRecord> records)
        {
            var list = records.ToList();
            var normFn = new List<string>(list.Count);
            var normPa = new List<string>(list.Count);
            var normMe = new List<string>(list.Count);
            var normP = new List<string>(list.Count);
            var normF = new List<string>(list.Count);
            var dates = new List<DateTime>(list.Count);
            var scanTimes = new List<DateTime>(list.Count);
            var inv = new Dictionary<string, HashSet<int>>(StringComparer.Ordinal);

            for (int i = 0; i < list.Count; i++)
            {
                var r = list[i];
                string tagsJoined = r.Tags != null ? string.Join(" ", r.Tags) : "";
                string factsJoined = r.KeyFacts != null ? string.Join(" ", r.KeyFacts.Select(kv => $"{kv.Key} {kv.Value}")) : "";

                string fnRaw = $"{r.Filename} {r.Title}";
                string pathRaw = $"{r.MainFolder} {r.SubFolder} {r.Category}";
                string metaRaw = $"{tagsJoined} {r.Purpose} {factsJoined}";
                string primaryRaw = $"{fnRaw} {pathRaw} {metaRaw} {r.ExtractedDate}";
                string fullRaw = $"{primaryRaw} {r.Summary} {r.SearchText}";

                string nfFn = Normalize(fnRaw);
                string nfPa = Normalize(pathRaw);
                string nfMe = Normalize(metaRaw);
                string np = Normalize(primaryRaw);
                string nf = Normalize(fullRaw);

                normFn.Add(nfFn);
                normPa.Add(nfPa);
                normMe.Add(nfMe);
                normP.Add(np);
                normF.Add(nf);
                var parsedDt = ParseRecordDate(r);
                dates.Add(parsedDt);
                scanTimes.Add(ParseScanTimestamp(r, parsedDt));

                foreach (var tok in Tokenize(nf))
                {
                    if (!inv.TryGetValue(tok, out var set))
                    {
                        set = new HashSet<int>();
                        inv[tok] = set;
                    }
                    set.Add(i);
                }
            }

            lock (_gate)
            {
                _records = list;
                _normFnTitle = normFn;
                _normPath = normPa;
                _normMeta = normMe;
                _normPrimary = normP;
                _normFull = normF;
                _parsedDates = dates;
                _scanTimestamps = scanTimes;
                _inverted = inv;
                LastRebuiltUtc = DateTime.UtcNow;
            }
        }

        public List<ArchiveSearchHit> GetRecentFiles(int maxResults = 6, string? filterHint = null)
        {
            var sw = Stopwatch.StartNew();
            var filterTokens = Tokenize(Normalize(filterHint))
                .Where(t => !StopWords.Contains(t) && !TemporalTokens.Contains(t))
                .ToList();

            lock (_gate)
            {
                if (_records.Count == 0)
                    return new List<ArchiveSearchHit>();

                IEnumerable<int> indices = Enumerable.Range(0, _records.Count);
                if (filterTokens.Count > 0)
                {
                    indices = indices.Where(i => filterTokens.All(tok => _normFull[i].Contains(tok, StringComparison.Ordinal)));
                }

                var picked = indices
                    .OrderByDescending(i => _scanTimestamps[i])
                    .ThenByDescending(i => _parsedDates[i])
                    .Take(maxResults)
                    .ToList();

                sw.Stop();
                double ms = Math.Round(sw.Elapsed.TotalMilliseconds, 2);

                return picked.Select(i =>
                {
                    var r = _records[i];
                    return new ArchiveSearchHit
                    {
                        Record = r,
                        Score = 500,
                        Snippet = ExtractBestSnippet(r, filterTokens),
                        QueryLatencyMs = ms
                    };
                }).ToList();
            }
        }

        public List<ArchiveSearchHit> Search(string rawQuery, int maxResults = 8)
        {
            var sw = Stopwatch.StartNew();
            string normQ = Normalize(rawQuery);
            if (string.IsNullOrWhiteSpace(normQ))
                return new List<ArchiveSearchHit>();

            var allTokens = Tokenize(normQ).ToList();
            bool wantsLatest = allTokens.Any(t => TemporalTokens.Contains(t));
            int? explicitYear = null;
            foreach (var t in allTokens)
            {
                if (t.Length == 4 && int.TryParse(t, out int yr) && yr >= 1990 && yr <= 2035)
                {
                    explicitYear = yr;
                    break;
                }
            }

            var queryTokens = allTokens
                .Where(t => !StopWords.Contains(t) && !TemporalTokens.Contains(t))
                .Distinct(StringComparer.Ordinal)
                .ToList();

            if (queryTokens.Count == 0 && wantsLatest)
            {
                return GetRecentFiles(maxResults, null);
            }

            if (queryTokens.Count == 0)
                return new List<ArchiveSearchHit>();

            var expandedByToken = new List<(string Original, List<string> Variants)>();
            foreach (var qt in queryTokens)
            {
                var variants = new HashSet<string>(StringComparer.Ordinal) { qt };
                if (qt.EndsWith("es", StringComparison.Ordinal) && qt.Length > 5) variants.Add(qt[..^2]);
                if (qt.EndsWith("en", StringComparison.Ordinal) && qt.Length > 5) variants.Add(qt[..^2]);
                if (qt.EndsWith("er", StringComparison.Ordinal) && qt.Length > 5) variants.Add(qt[..^2]);
                if (qt.EndsWith("e", StringComparison.Ordinal) && qt.Length > 4) variants.Add(qt[..^1]);
                if (qt.EndsWith("s", StringComparison.Ordinal) && qt.Length > 4) variants.Add(qt[..^1]);

                foreach (var kv in DomainSynonyms)
                {
                    if (string.Equals(kv.Key, qt, StringComparison.Ordinal) ||
                        (qt.Length >= 4 && (kv.Key.StartsWith(qt, StringComparison.Ordinal) || qt.StartsWith(kv.Key, StringComparison.Ordinal))))
                    {
                        foreach (var syn in kv.Value)
                        {
                            string ns = Normalize(syn);
                            if (!string.IsNullOrWhiteSpace(ns))
                                variants.Add(ns);
                        }
                    }
                }
                expandedByToken.Add((qt, variants.ToList()));
            }

            List<(int Index, int Score)> scored = new();
            lock (_gate)
            {
                if (_records.Count == 0) return new List<ArchiveSearchHit>();

                // 1. O(1) Inverted-Index direct lookup across all expanded tokens
                var candidates = new HashSet<int>();
                var prefixFallbacks = new List<string>();
                foreach (var (_, variants) in expandedByToken)
                {
                    foreach (var v in variants)
                    {
                        foreach (var subTok in v.Split(' ', StringSplitOptions.RemoveEmptyEntries))
                        {
                            if (_inverted.TryGetValue(subTok, out var set))
                            {
                                foreach (int idx in set) candidates.Add(idx);
                            }
                            else if (subTok.Length >= 5)
                            {
                                prefixFallbacks.Add(subTok);
                            }
                        }
                    }
                }

                // 2. Single-pass prefix scan only when direct hits returned nothing OR for explicit short query stems
                if ((candidates.Count == 0 && prefixFallbacks.Count > 0) ||
                    (candidates.Count < 8 && queryTokens.Any(qt => qt.Length >= 5 && !_inverted.ContainsKey(qt))))
                {
                    var stems = queryTokens.Where(qt => qt.Length >= 5).Concat(prefixFallbacks).Distinct(StringComparer.Ordinal).ToArray();
                    if (stems.Length > 0)
                    {
                        foreach (var kv in _inverted)
                        {
                            string ik = kv.Key;
                            for (int sIdx = 0; sIdx < stems.Length; sIdx++)
                            {
                                string st = stems[sIdx];
                                if (ik.StartsWith(st, StringComparison.Ordinal) ||
                                    (ik.Length >= 5 && st.StartsWith(ik, StringComparison.Ordinal)))
                                {
                                    foreach (int idx in kv.Value) candidates.Add(idx);
                                    break;
                                }
                            }
                        }
                    }
                }

                // Fallback full scan only if inverted candidate pool is empty (e.g. heavy typo)
                IEnumerable<int> scanIndices = candidates.Count > 0
                    ? candidates
                    : Enumerable.Range(0, _records.Count);

                foreach (int i in scanIndices)
                {
                    var rec = _records[i];
                    string fnNorm = _normFnTitle[i];
                    string pathNorm = _normPath[i];
                    string metaNorm = _normMeta[i];
                    string np = _normPrimary[i];
                    string nf = _normFull[i];
                    int total = 0;
                    int matchedQueryTokens = 0;

                    if (fnNorm.Contains(normQ, StringComparison.Ordinal)) total += 260;
                    else if (np.Contains(normQ, StringComparison.Ordinal)) total += 150;
                    else if (nf.Contains(normQ, StringComparison.Ordinal)) total += 65;

                    foreach (var (orig, variants) in expandedByToken)
                    {
                        int bestTok = 0;
                        foreach (var v in variants)
                        {
                            bool isExactOrig = string.Equals(v, orig, StringComparison.Ordinal);
                            bool isStrongDocSyn = isExactOrig || v.Length >= 9;

                            if (fnNorm.Contains(v, StringComparison.Ordinal))
                            {
                                bestTok = Math.Max(bestTok, isExactOrig ? 280 : (isStrongDocSyn ? 220 : 120));
                            }
                            else if (pathNorm.Contains(v, StringComparison.Ordinal))
                            {
                                bestTok = Math.Max(bestTok, isExactOrig ? 170 : (isStrongDocSyn ? 130 : 75));
                            }
                            else if (metaNorm.Contains(v, StringComparison.Ordinal))
                            {
                                bestTok = Math.Max(bestTok, isExactOrig ? 68 : 38);
                            }
                            else if (nf.Contains(v, StringComparison.Ordinal))
                            {
                                bestTok = Math.Max(bestTok, isExactOrig ? 28 : 12);
                            }
                        }

                        if (bestTok > 0)
                        {
                            matchedQueryTokens++;
                            total += bestTok;
                        }
                    }

                    // Require all meaningful tokens (or allow partial if >=2 tokens matched on multi-word query)
                    if (matchedQueryTokens == 0) continue;
                    if (queryTokens.Count >= 2 && matchedQueryTokens < queryTokens.Count)
                    {
                        // Penalize partial matches so full matches always dominate
                        total = (int)(total * ((double)matchedQueryTokens / queryTokens.Count) * 0.55);
                        if (total < 15) continue;
                    }

                    // Document format quality tie-breaker (PDF/DOCX before raw image scan when same topic/date)
                    string extLow = (rec.Ext ?? "").ToLowerInvariant();
                    if (extLow == "pdf") total += 18;
                    else if (extLow is "docx" or "xlsx" or "md" or "txt") total += 12;
                    else if (extLow is "png" or "jpg" or "jpeg") total += 4;

                    // Explicit year boost
                    if (explicitYear.HasValue)
                    {
                        if (_parsedDates[i].Year == explicitYear.Value ||
                            rec.Filename.Contains(explicitYear.Value.ToString(), StringComparison.Ordinal) ||
                            rec.RelPath.Contains(explicitYear.Value.ToString(), StringComparison.Ordinal))
                        {
                            total += 95;
                        }
                    }

                    // Balanced Recency Boost ("gib mir den letzten ..."):
                    // Decisively ranks newer editions above older editions of the SAME topic without letting unrelated 2026 letters beat 2025 primary docs.
                    if (wantsLatest)
                    {
                        DateTime effDate = _scanTimestamps[i] > _parsedDates[i] ? _scanTimestamps[i] : _parsedDates[i];
                        int daysFrom2015 = Math.Max(0, (int)(effDate - new DateTime(2015, 1, 1)).TotalDays);
                        total += Math.Min(92, daysFrom2015 / 45);
                    }

                    scored.Add((i, total));
                }

                var ordered = scored
                    .OrderByDescending(x => x.Score)
                    .ThenByDescending(x => _parsedDates[x.Index])
                    .ThenByDescending(x => _scanTimestamps[x.Index])
                    .Take(maxResults)
                    .ToList();

                sw.Stop();
                double ms = Math.Round(sw.Elapsed.TotalMilliseconds, 2);

                return ordered.Select(x =>
                {
                    var r = _records[x.Index];
                    return new ArchiveSearchHit
                    {
                        Record = r,
                        Score = x.Score,
                        Snippet = ExtractBestSnippet(r, queryTokens),
                        QueryLatencyMs = ms
                    };
                }).ToList();
            }
        }

        private static string ExtractBestSnippet(FunkenwerkFileDbRecord rec, List<string> queryTokens)
        {
            string source = !string.IsNullOrWhiteSpace(rec.SearchText) ? rec.SearchText : rec.Summary;
            if (string.IsNullOrWhiteSpace(source)) return "";
            string clean = Regex.Replace(source, @"\s+", " ").Trim();
            string lower = Normalize(clean);

            foreach (var tok in queryTokens)
            {
                int idx = lower.IndexOf(tok, StringComparison.Ordinal);
                if (idx >= 0)
                {
                    int start = Math.Max(0, idx - 60);
                    int len = Math.Min(260, clean.Length - start);
                    string sub = clean.Substring(start, len).Trim();
                    if (start > 0) sub = "..." + sub;
                    if (start + len < clean.Length) sub += "...";
                    return sub;
                }
            }

            return clean.Length > 240 ? clean.Substring(0, 237) + "..." : clean;
        }

        private static DateTime ParseRecordDate(FunkenwerkFileDbRecord r)
        {
            if (!string.IsNullOrWhiteSpace(r.ExtractedDate) && DateTime.TryParse(r.ExtractedDate, out var dt))
                return dt;
            var m = Regex.Match(r.Filename + " " + r.RelPath, @"\b(20[12]\d)\b");
            if (m.Success && int.TryParse(m.Groups[1].Value, out int yr))
                return new DateTime(yr, 6, 15);
            return new DateTime(2018, 1, 1);
        }

        private static DateTime ParseScanTimestamp(FunkenwerkFileDbRecord r, DateTime fallbackDate)
        {
            DateTime best = fallbackDate;
            if (!string.IsNullOrWhiteSpace(r.FileMtime) && DateTime.TryParse(r.FileMtime, out var mtime) && mtime > best)
                best = mtime;
            if (!string.IsNullOrWhiteSpace(r.IndexedAt) && DateTime.TryParse(r.IndexedAt, out var idxAt) && idxAt > best)
                best = idxAt;

            var prefixDate = Regex.Match(r.Filename ?? "", @"\b(20[12]\d)-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])\b");
            if (prefixDate.Success && DateTime.TryParse(prefixDate.Value, out var fDt))
            {
                // If filename starts with YYYY-MM-DD (like 2026-11-22_...) and no finer timestamp exists yet, preserve at least that date
                if (best.Date < fDt.Date)
                    best = fDt;
            }
            return best;
        }

        public static string Normalize(string? raw)
        {
            if (string.IsNullOrWhiteSpace(raw)) return "";
            var sb = new StringBuilder(raw.Length + 8);
            foreach (char c in raw.ToLowerInvariant())
            {
                switch (c)
                {
                    case 'ä': sb.Append("ae"); break;
                    case 'ö': sb.Append("oe"); break;
                    case 'ü': sb.Append("ue"); break;
                    case 'ß': sb.Append("ss"); break;
                    default:
                        sb.Append(char.IsLetterOrDigit(c) ? c : ' ');
                        break;
                }
            }
            return Regex.Replace(sb.ToString(), @"\s+", " ").Trim();
        }

        private static IEnumerable<string> Tokenize(string normalizedText)
        {
            return normalizedText
                .Split(' ', StringSplitOptions.RemoveEmptyEntries)
                .Where(t => t.Length >= 2)
                .Distinct(StringComparer.Ordinal);
        }
    }

    /// <summary>
    /// 100 % Local 0-Token Document Reader (PDF Text + Windows.Data.Pdf + Windows.Media.Ocr de-DE, DOCX, XLSX, EML, MSG, TXT/CSV)
    /// and Rule-Based Classifier for C:\Users\konta\kDrive\Meine Daten\00_Eingang_Trichter.
    /// </summary>
    public static class FunkenwerkZeroTokenDocumentPipeline
    {
        private static readonly Dictionary<string, int> GermanMonths = new(StringComparer.OrdinalIgnoreCase)
        {
            ["januar"] = 1, ["jan"] = 1, ["februar"] = 2, ["feb"] = 2, ["märz"] = 3, ["maerz"] = 3, ["mrz"] = 3,
            ["april"] = 4, ["apr"] = 4, ["mai"] = 5, ["juni"] = 6, ["jun"] = 6, ["juli"] = 7, ["jul"] = 7,
            ["august"] = 8, ["aug"] = 8, ["september"] = 9, ["sep"] = 9, ["sept"] = 9,
            ["oktober"] = 10, ["okt"] = 10, ["november"] = 11, ["nov"] = 11, ["dezember"] = 12, ["dez"] = 12
        };

        static FunkenwerkZeroTokenDocumentPipeline()
        {
            Encoding.RegisterProvider(CodePagesEncodingProvider.Instance);
        }

        public static async Task<string> ExtractTextZeroTokenAsync(string fullPath)
        {
            if (string.IsNullOrWhiteSpace(fullPath) || !File.Exists(fullPath))
                return "";

            string ext = Path.GetExtension(fullPath).TrimStart('.').ToLowerInvariant();
            try
            {
                if (ext is "txt" or "text" or "md" or "csv" or "json" or "xml" or "ics" or "log")
                {
                    string raw = await File.ReadAllTextAsync(fullPath, Encoding.UTF8).ConfigureAwait(false);
                    return raw.Length > 25000 ? raw[..25000] : raw;
                }
                if (ext is "html" or "htm")
                {
                    string html = await File.ReadAllTextAsync(fullPath, Encoding.UTF8).ConfigureAwait(false);
                    string stripped = Regex.Replace(html, @"<[^>]+>", " ");
                    stripped = Regex.Replace(stripped, @"\s+", " ").Trim();
                    return stripped.Length > 25000 ? stripped[..25000] : stripped;
                }
                if (ext is "docx" or "xlsx")
                {
                    return ExtractDocxOrXlsxText(fullPath, ext);
                }
                if (ext == "eml")
                {
                    return ExtractEmlText(fullPath);
                }
                if (ext == "msg")
                {
                    return ExtractMsgText(fullPath);
                }
                if (ext == "pdf")
                {
                    string native = ExtractPdfSharpNativeText(fullPath);
                    if (native.Length >= 50)
                        return native.Length > 25000 ? native[..25000] : native;

                    string ocr = await OcrPdfWithWindowsAsync(fullPath, maxPages: 4).ConfigureAwait(false);
                    string merged = string.IsNullOrWhiteSpace(ocr) ? native : ocr;
                    return merged.Length > 25000 ? merged[..25000] : merged;
                }
                if (ext is "jpg" or "jpeg" or "png" or "webp" or "bmp")
                {
                    string ocr = await OcrImageWithWindowsAsync(fullPath).ConfigureAwait(false);
                    return ocr.Length > 25000 ? ocr[..25000] : ocr;
                }
            }
            catch { }
            return "";
        }

        private static string ExtractDocxOrXlsxText(string path, string ext)
        {
            try
            {
                using var zf = ZipFile.OpenRead(path);
                var sb = new StringBuilder();
                foreach (var entry in zf.Entries)
                {
                    bool take = ext == "docx"
                        ? entry.FullName.Equals("word/document.xml", StringComparison.OrdinalIgnoreCase) ||
                          entry.FullName.Equals("word/header1.xml", StringComparison.OrdinalIgnoreCase)
                        : entry.FullName.Equals("xl/sharedStrings.xml", StringComparison.OrdinalIgnoreCase) ||
                          entry.FullName.StartsWith("xl/worksheets/sheet", StringComparison.OrdinalIgnoreCase);

                    if (!take) continue;
                    using var stream = entry.Open();
                    var xdoc = XDocument.Load(stream);
                    foreach (var el in xdoc.Descendants())
                    {
                        if (el.Name.LocalName == "t" && !string.IsNullOrWhiteSpace(el.Value))
                        {
                            sb.Append(el.Value).Append(' ');
                            if (sb.Length > 25000) break;
                        }
                    }
                    if (sb.Length > 25000) break;
                }
                return sb.ToString().Trim();
            }
            catch { return ""; }
        }

        private static string ExtractEmlText(string path)
        {
            try
            {
                string raw = File.ReadAllText(path, Encoding.UTF8);
                var sb = new StringBuilder();
                foreach (string hdr in new[] { "Subject", "From", "To", "Date" })
                {
                    var m = Regex.Match(raw, $@"^{hdr}:\s*(.+)$", RegexOptions.Multiline | RegexOptions.IgnoreCase);
                    if (m.Success) sb.AppendLine($"{hdr}: {m.Groups[1].Value.Trim()}");
                }
                int sep = raw.IndexOf("\r\n\r\n", StringComparison.Ordinal);
                if (sep < 0) sep = raw.IndexOf("\n\n", StringComparison.Ordinal);
                if (sep >= 0 && sep + 2 < raw.Length)
                {
                    string body = Regex.Replace(raw[(sep + 2)..], @"<[^>]+>", " ");
                    body = Regex.Replace(body, @"\s+", " ").Trim();
                    sb.Append(body);
                }
                string res = sb.ToString();
                return res.Length > 25000 ? res[..25000] : res;
            }
            catch { return ""; }
        }

        private static string ExtractMsgText(string path)
        {
            try
            {
                // Clean Unicode + ASCII scan fallback if full CFB isn't needed
                byte[] bytes = File.ReadAllBytes(path);
                string uni = Encoding.Unicode.GetString(bytes);
                var chunks = Regex.Matches(uni, @"[A-Za-zÄÖÜäöüß0-9\.,;:\-\(\)/€@\s]{12,}")
                    .Select(m => Regex.Replace(m.Value, @"\s+", " ").Trim())
                    .Where(s => s.Length >= 12 && !s.StartsWith("__substg", StringComparison.OrdinalIgnoreCase))
                    .Distinct();
                string joined = string.Join(" ", chunks);
                return joined.Length > 25000 ? joined[..25000] : joined;
            }
            catch { return ""; }
        }

        private static string ExtractPdfSharpNativeText(string path)
        {
            try
            {
                using var doc = PdfReader.Open(path, PdfDocumentOpenMode.Import);
                var sb = new StringBuilder();
                int pages = Math.Min(doc.PageCount, 12);
                for (int i = 0; i < pages; i++)
                {
                    var content = ContentReader.ReadContent(doc.Pages[i]);
                    ExtractTextFromCObject(content, sb);
                    sb.AppendLine();
                    if (sb.Length > 25000) break;
                }
                return Regex.Replace(sb.ToString(), @"[ \t]+", " ").Trim();
            }
            catch
            {
                return "";
            }
        }

        private static void ExtractTextFromCObject(CObject obj, StringBuilder sb)
        {
            if (obj is CSequence seq)
            {
                foreach (var item in seq) ExtractTextFromCObject(item, sb);
            }
            else if (obj is CArray arr)
            {
                foreach (var item in arr) ExtractTextFromCObject(item, sb);
            }
            else if (obj is COperator op)
            {
                string name = op.OpCode.Name;
                if (name is "Tj" or "TJ" or "'" or "\"")
                {
                    foreach (var operand in op.Operands) ExtractTextFromCObject(operand, sb);
                    sb.Append(' ');
                }
            }
            else if (obj is CString str)
            {
                sb.Append(str.Value);
            }
        }

        private static OcrEngine? TryCreateGermanOcr()
        {
            try
            {
                var de = new Language("de-DE");
                if (OcrEngine.IsLanguageSupported(de))
                {
                    var eng = OcrEngine.TryCreateFromLanguage(de);
                    if (eng != null) return eng;
                }
            }
            catch { }
            return OcrEngine.TryCreateFromUserProfileLanguages();
        }

        private static async Task<string> OcrImageWithWindowsAsync(string imagePath)
        {
            var ocr = TryCreateGermanOcr();
            if (ocr == null) return "";

            var file = await StorageFile.GetFileFromPathAsync(imagePath);
            using IRandomAccessStream stream = await file.OpenAsync(FileAccessMode.Read);
            BitmapDecoder decoder = await BitmapDecoder.CreateAsync(stream);
            using SoftwareBitmap bmp = await decoder.GetSoftwareBitmapAsync(BitmapPixelFormat.Bgra8, BitmapAlphaMode.Premultiplied);
            if (bmp.PixelWidth < 30 || bmp.PixelHeight < 30) return "";
            OcrResult res = await ocr.RecognizeAsync(bmp);
            return (res?.Text ?? "").Trim();
        }

        private static async Task<string> OcrPdfWithWindowsAsync(string pdfPath, int maxPages = 4)
        {
            var ocr = TryCreateGermanOcr();
            if (ocr == null) return "";

            var file = await StorageFile.GetFileFromPathAsync(pdfPath);
            WinPdfDocument pdf = await WinPdfDocument.LoadFromFileAsync(file);
            uint count = Math.Min(pdf.PageCount, (uint)maxPages);
            var sb = new StringBuilder();

            for (uint i = 0; i < count; i++)
            {
                using WinPdfPage page = pdf.GetPage(i);
                using var mem = new InMemoryRandomAccessStream();
                var opts = new WinPdfPageRenderOptions();
                double scale = page.Size.Width > 0 ? Math.Min(2.2, 1750.0 / page.Size.Width) : 1.8;
                if (scale < 1.2) scale = 1.2;
                opts.DestinationWidth = (uint)Math.Clamp(Math.Round(page.Size.Width * scale), 600, 2600);
                await page.RenderToStreamAsync(mem, opts);
                mem.Seek(0);

                BitmapDecoder decoder = await BitmapDecoder.CreateAsync(mem);
                using SoftwareBitmap bmp = await decoder.GetSoftwareBitmapAsync(BitmapPixelFormat.Bgra8, BitmapAlphaMode.Premultiplied);
                OcrResult res = await ocr.RecognizeAsync(bmp);
                if (!string.IsNullOrWhiteSpace(res?.Text))
                {
                    if (sb.Length > 0) sb.AppendLine();
                    sb.Append(res.Text.Trim());
                }
            }

            return sb.ToString();
        }

        public static ZeroTokenClassificationResult ClassifyDocument(string filePath, string extractedText, string rootDir)
        {
            var fi = new FileInfo(filePath);
            string origName = fi.Name;
            string ext = fi.Extension.TrimStart('.').ToLowerInvariant();
            string cleanText = Regex.Replace(extractedText ?? "", @"[ \t]+", " ").Trim();
            string combined = $"{origName} {cleanText}".ToLowerInvariant();

            string exDate = ExtractDate(origName, cleanText, fi.LastWriteTime);
            string year = exDate.Length >= 4 ? exDate[..4] : DateTime.Now.Year.ToString();
            var facts = ExtractKeyFacts(cleanText);

            var (mainF, subF, cat, tags, purpose, senderLabel, topicLabel) = ResolveFolderAndMeta(combined, year, ext, rootDir);

            // Rename generic scans cleanly: YYYY-MM-DD_Sender_Topic.ext
            string finalName = origName;
            bool isGeneric = Regex.IsMatch(origName, @"^(scan|beleg|bild|dokument|trichter|belegpaket|pxl_|img_|dsc|\d{10,})", RegexOptions.IgnoreCase);
            if (isGeneric && !string.IsNullOrWhiteSpace(senderLabel))
            {
                string safeStem = $"{exDate}_{senderLabel}_{topicLabel}".Trim('_');
                safeStem = Regex.Replace(safeStem, @"[^A-Za-z0-9ÄÖÜäöüß_\-]", "_");
                safeStem = Regex.Replace(safeStem, @"_+", "_").Trim('_');
                if (safeStem.Length > 85) safeStem = safeStem[..85];
                if (!string.IsNullOrWhiteSpace(safeStem))
                    finalName = $"{safeStem}.{ext}";
            }

            string title = Path.GetFileNameWithoutExtension(finalName).Replace('_', ' ').Replace('-', ' ').Trim();
            string summary = !string.IsNullOrWhiteSpace(cleanText)
                ? TruncateClean(Regex.Replace(cleanText, @"\s+", " "), 320).Replace(" – ", " · ").Replace(" — ", " · ")
                : $"{title} ({cat})";

            string factStr = facts.Count > 0 ? string.Join(" | ", facts.Select(kv => $"{kv.Key}: {kv.Value}")) : "";
            string searchText = $"{title} {subF.Replace('/', ' ')} {factStr} {(cleanText.Length > 12000 ? cleanText[..12000] : cleanText)}".Trim();

            return new ZeroTokenClassificationResult
            {
                MainFolder = mainF,
                SubFolder = subF,
                SuggestedFilename = finalName,
                ExtractedDate = exDate,
                Title = title,
                Category = cat,
                Tags = tags,
                Purpose = purpose,
                KeyFacts = facts,
                Summary = summary,
                SearchText = searchText
            };
        }

        private static (string MainFolder, string SubFolder, string Category, List<string> Tags, string Purpose, string Sender, string Topic)
            ResolveFolderAndMeta(string low, string year, string ext, string rootDir)
        {
            var tags = new List<string>();
            void AddTag(string t) { if (!string.IsNullOrWhiteSpace(t) && !tags.Contains(t)) tags.Add(t); }

            // 1. Mein-Seelenfunke (Business)
            if (low.Contains("seelenfunke") || low.Contains("xtool") || low.Contains("westpack") ||
                low.Contains("printequipment") || low.Contains("glücksfieber") || low.Contains("gluecksfieber") ||
                low.Contains("kartonfritze") || low.Contains("karton.eu") || low.Contains("fyrst") ||
                low.Contains("laserschutzbeauftragte") || low.Contains("betriebshaftpflicht") || low.Contains("hiscox") ||
                low.Contains("gewerbeanmeldung") || low.Contains("gewerbesteuer") || low.Contains("gründungszuschuss") || low.Contains("gruendungszuschuss"))
            {
                string sub = "Eingangsrechnungen_Belege";
                string topic = "Beleg";
                string sender = "Mein-Seelenfunke";
                if (low.Contains("xtool") || low.Contains("laser")) { sub = "Laser_Manufaktur"; topic = "Laser"; AddTag("Laser"); }
                else if (low.Contains("fyrst")) { sub = "Bank_FYRST"; topic = "Konto"; sender = "FYRST"; AddTag("FYRST"); }
                else if (low.Contains("gewerbesteuer") || low.Contains("umsatzsteuer") || low.Contains("gewerbeanmeldung")) { sub = "Steuern_Finanzamt_Gewerbe"; topic = "Gewerbe"; AddTag("Gewerbe"); }
                else if (low.Contains("hiscox") || low.Contains("betriebshaftpflicht")) { sub = "Vertraege_Versicherungen"; sender = "Hiscox"; topic = "Betriebshaftpflicht"; AddTag("Hiscox"); }
                else if (low.Contains("gründungszuschuss") || low.Contains("gruendungszuschuss") || low.Contains("businessplan") || low.Contains("tragfähigkeit")) { sub = "Gruendung_Businessplan"; topic = "Gruendungszuschuss"; AddTag("Gruendungszuschuss"); }
                AddTag("Mein-Seelenfunke"); AddTag(year); AddTag(ext.ToUpperInvariant());
                return ("01_Mein-Seelenfunke", sub, $"Mein-Seelenfunke (Gewerbe) › {sub.Replace('_', ' ')}", tags, "Geschäftsunterlage für Mein-Seelenfunke.", sender, topic);
            }

            // 2. Rente & Vorsorge / Ausweise / Urkunden
            if (low.Contains("renteninformation") || low.Contains("rentenbescheid") || low.Contains("deutsche rentenversicherung") ||
                low.Contains("versorgungsausgleich") || low.Contains("kontenklärung") || low.Contains("kontenklaerung") || low.Contains("sozialversicherungsausweis"))
            {
                AddTag("Rente"); AddTag("Renteninformation"); AddTag("Rentenbescheid"); AddTag("Deutsche Rentenversicherung"); AddTag(year); AddTag(ext.ToUpperInvariant());
                return ("05_Familie_Ausweise_Vorsorge", $"Rente_Vorsorge/{year}", "Familie, Ausweise, Rente & Vorsorge › Rente Vorsorge", tags, "Renteninformation bzw. Bescheid der Deutschen Rentenversicherung.", "DRV", "Renteninformation");
            }
            if (low.Contains("personalausweis") || low.Contains("führerschein") || low.Contains("fuehrerschein"))
            {
                AddTag("Ausweis"); AddTag(year); AddTag(ext.ToUpperInvariant());
                return ("05_Familie_Ausweise_Vorsorge", "Ausweise_Fuehrerschein", "Familie, Ausweise, Rente & Vorsorge › Ausweise", tags, "Persönliches Ausweisdokument.", "Ausweis", "Dokument");
            }
            if (low.Contains("geburtsurkunde") || low.Contains("eheurkunde") || low.Contains("standesamt") || low.Contains("namensänderung") || low.Contains("namensaenderung"))
            {
                AddTag("Urkunde"); AddTag(year); AddTag(ext.ToUpperInvariant());
                return ("05_Familie_Ausweise_Vorsorge", "Urkunden", "Familie, Ausweise, Rente & Vorsorge › Urkunden", tags, "Personenstandsurkunde.", "Standesamt", "Urkunde");
            }
            if (low.Contains("testament") || low.Contains("vorsorgevollmacht") || low.Contains("todesfallcheckliste"))
            {
                AddTag("Vorsorge"); AddTag("Vollmacht"); AddTag(year); AddTag(ext.ToUpperInvariant());
                return ("05_Familie_Ausweise_Vorsorge", "Erbe_Vorsorge", "Familie, Ausweise, Rente & Vorsorge › Erbe & Vorsorge", tags, "Vorsorge- oder Erbdokument.", "Vorsorge", "Vollmacht");
            }

            // 3. Gesundheit & Krankenkasse
            if (low.Contains("arbeitsunfähigkeit") || low.Contains("arbeitsunfaehigkeit") || low.Contains("krankschreibung") || low.Contains("folgebescheinigung") || low.Contains("eau"))
            {
                AddTag("Krankschreibung"); AddTag("AU"); AddTag(year); AddTag(ext.ToUpperInvariant());
                return ("06_Gesundheit", $"Krankmeldungen/{year}", "Gesundheit & Krankenkasse › Krankmeldungen", tags, "Arbeitsunfähigkeitsbescheinigung (AU/eAU).", "Arzt", "AU_Bescheinigung");
            }
            if (low.Contains("bkk") || low.Contains("firmus") || low.Contains("krankengeld") || low.Contains("sozialgericht") ||
                low.Contains("lubos") || low.Contains("endokrinolog") || low.Contains("arztbrief") || low.Contains("befund") || low.Contains("rezept") || low.Contains("attest"))
            {
                string sub = (low.Contains("bkk") || low.Contains("firmus") || low.Contains("krankengeld") || low.Contains("sozialgericht"))
                    ? "Krankenkasse/4_BKK_firmus"
                    : "Arztbriefe_Befunde";
                AddTag("Gesundheit"); AddTag(low.Contains("bkk") ? "BKK firmus" : "Arzt"); AddTag(year); AddTag(ext.ToUpperInvariant());
                return ("06_Gesundheit", sub, $"Gesundheit & Krankenkasse › {sub.Split('/')[0]}", tags, "Medizinisches Dokument bzw. Krankenkassen-Korrespondenz.", low.Contains("bkk") ? "BKK-firmus" : "Arzt", "Unterlage");
            }

            // 4. Haus & Immobilie (inkl. Zählerstände)
            if (low.Contains("zähler") || low.Contains("zaehler") || low.Contains("drehstromzähler") || low.Contains("wasserwerk") ||
                low.Contains("lsw") || low.Contains("vattenfall") || low.Contains("grünewelt") || low.Contains("gruenewelt"))
            {
                bool isMeter = low.Contains("zähler") || low.Contains("zaehler") || low.Contains("drehstromzähler") || low.Contains("wasserwerk");
                string sub = isMeter ? $"Gas_Wasser_Strom/Zaehlerstaende/{year}" : "Gas_Wasser_Strom";
                AddTag("Haus"); AddTag(isMeter ? "Zaehlerstand" : "Energie"); AddTag(year); AddTag(ext.ToUpperInvariant());
                return ("03_Haus_Immobilie", sub, "Haus & Immobilie › Gas, Wasser & Strom", tags, "Zählerstand oder Versorgerunterlage fürs Eigenheim.", isMeter ? "Zaehler" : "Versorger", "Stand");
            }
            if (low.Contains("gebäudeversicherung") || low.Contains("gebaeudeversicherung") || low.Contains("barmenia") || low.Contains("devk") || low.Contains("neodigital"))
            {
                AddTag("Gebaeudeversicherung"); AddTag("Haus"); AddTag(year); AddTag(ext.ToUpperInvariant());
                return ("03_Haus_Immobilie", "Gebaeudeversicherung", "Haus & Immobilie › Gebäudeversicherung", tags, "Gebäudeversicherungs-Dokument.", "Versicherung", "Gebaeude");
            }
            if (low.Contains("photovoltaik") || low.Contains("wärmepumpe") || low.Contains("waermepumpe") || low.Contains("grundsteuer") || low.Contains("kaufvertrag") || low.Contains("notar") || low.Contains("miete") || low.Contains("vermietung"))
            {
                string sub = (low.Contains("photovoltaik") || low.Contains("wärmepumpe") || low.Contains("waermepumpe")) ? "Photovoltaik_Waermepumpe" :
                             (low.Contains("kaufvertrag") || low.Contains("notar")) ? "Kaufvertrag_Notar" :
                             (low.Contains("miete") || low.Contains("vermietung")) ? "Vermietung" : "Bauakten_Grundriss";
                AddTag("Haus"); AddTag(year); AddTag(ext.ToUpperInvariant());
                return ("03_Haus_Immobilie", sub, $"Haus & Immobilie › {sub.Replace('_', ' ')}", tags, "Immobilien-Unterlage.", "Haus", "Dokument");
            }

            // 5. Behörden, Arbeit, Recht
            if (low.Contains("agentur für arbeit") || low.Contains("agentur fuer arbeit") || low.Contains("bundesagentur") || low.Contains("arbeitsamt") || low.Contains("arbeitslosengeld") || low.Contains("alg 1"))
            {
                AddTag("Arbeitsamt"); AddTag("Agentur für Arbeit"); AddTag("ALG 1"); AddTag(year); AddTag(ext.ToUpperInvariant());
                return ("07_Behoerden_Recht_Berufshistorie", $"Agentur_fuer_Arbeit/{year}", "Behörden, Recht & Beruf › Agentur für Arbeit", tags, "Bescheid oder Mitteilung der Agentur für Arbeit.", "Bundesagentur", "Bescheid");
            }
            if (low.Contains("polizei") || low.Contains("amtsgericht") || low.Contains("stadt gifhorn") || low.Contains("landkreis gifhorn"))
            {
                AddTag("Gifhorn"); AddTag("Behoerde"); AddTag(year); AddTag(ext.ToUpperInvariant());
                return ("07_Behoerden_Recht_Berufshistorie", "Gifhorn_Behoerden", "Behörden, Recht & Beruf › Gifhorn Behörden", tags, "Behördliches Schreiben.", "Behoerde", "Schreiben");
            }

            // 6. Versicherungen & Alltag
            if (low.Contains("telekom") || low.Contains("vodafone") || low.Contains("handyvertrag") || low.Contains("glasfaser") || low.Contains("rundfunk"))
            {
                string sub = low.Contains("rundfunk") ? "Rundfunk" : "Handy_Internet";
                AddTag("Vertrag"); AddTag(year); AddTag(ext.ToUpperInvariant());
                return ("04_Versicherungen_Vertraege_Mobilitaet", sub, $"Versicherungen, Verträge & Mobilität › {sub.Replace('_', ' ')}", tags, "Telekommunikations- oder Rundfunk-Vertragsdokument.", "Vertrag", "Unterlage");
            }
            if (low.Contains("versicherung") || low.Contains("arag") || low.Contains("axa") || low.Contains("allianz") || low.Contains("kfz") || low.Contains("roller"))
            {
                string sub = (low.Contains("kfz") || low.Contains("auto") || low.Contains("roller")) ? "Mobilitaet_Roller_Kfz" : "Versicherungen_Privat";
                AddTag("Versicherung"); AddTag(year); AddTag(ext.ToUpperInvariant());
                return ("04_Versicherungen_Vertraege_Mobilitaet", sub, $"Versicherungen, Verträge & Mobilität › {sub.Replace('_', ' ')}", tags, "Private Versicherungs- oder Mobilitätsunterlage.", "Versicherung", "Police");
            }

            // 7. Tresor / Backups
            if (low.Contains("keepass") || low.Contains("kdbx") || low.Contains("recovery") || low.Contains("passwort"))
            {
                AddTag("Tresor"); AddTag("Backup"); AddTag(year); AddTag(ext.ToUpperInvariant());
                return ("08_Tresor_Backups", "Keepass_Backups", "Tresor & Backups › KeePass Backups", tags, "Sicherheits-Backup oder Zugangsdaten.", "Tresor", "Backup");
            }

            // 8. Finanzen, Steuern & Banken (Default)
            if (low.Contains("steuer") || low.Contains("finanzamt") || low.Contains("elster") || low.Contains("lohnsteuer"))
            {
                AddTag("Steuern"); AddTag("Finanzamt"); AddTag(year); AddTag(ext.ToUpperInvariant());
                return ("02_Finanzen_Steuern_Banken", $"Steuern_Elster/{year}", "Finanzen, Steuern & Banken › Steuern Elster", tags, "Steuerbescheid oder Finanzamt-Unterlage.", "Finanzamt", "Steuern");
            }
            if (low.Contains("volksbank") || low.Contains("kontoauszug") || low.Contains("umsätze") || low.Contains("umsaetze") || low.Contains("iban") || low.Contains("schufa"))
            {
                string sub = low.Contains("schufa") ? "Schufa" : "Banken/Volksbank_BraWo";
                AddTag("Bank"); AddTag("Volksbank BraWo"); AddTag(year); AddTag(ext.ToUpperInvariant());
                return ("02_Finanzen_Steuern_Banken", sub, $"Finanzen, Steuern & Banken › {sub.Split('/')[0]}", tags, "Bank-, Kontoauszugs- oder Bonitätsdokument.", "Volksbank", "Konto");
            }

            AddTag("Rechnung"); AddTag(year); AddTag(ext.ToUpperInvariant());
            return ("02_Finanzen_Steuern_Banken", $"Rechnungen/{year}", "Finanzen, Steuern & Banken › Rechnungen", tags, "Rechnung, Zahlungsbeleg oder Finanzunterlage.", "Beleg", "Rechnung");
        }

        private static string ExtractDate(string filename, string text, DateTime fallback)
        {
            var m = Regex.Match(filename, @"(19\d\d|20[0-2]\d)[-_](0[1-9]|1[0-2])[-_](0[1-9]|[12]\d|3[01])");
            if (m.Success) return $"{m.Groups[1].Value}-{m.Groups[2].Value}-{m.Groups[3].Value}";

            m = Regex.Match(filename, @"(0[1-9]|[12]\d|3[01])\.(0[1-9]|1[0-2])\.(19\d\d|20[0-2]\d)");
            if (m.Success) return $"{m.Groups[3].Value}-{m.Groups[2].Value}-{m.Groups[1].Value}";

            if (!string.IsNullOrWhiteSpace(text))
            {
                string head = text.Length > 2500 ? text[..2500] : text;
                m = Regex.Match(head, @"\b(\d{1,2})\.?\s+(januar|jan|februar|feb|märz|maerz|mrz|april|apr|mai|juni|jun|juli|jul|august|aug|september|sep|sept|oktober|okt|november|nov|dezember|dez)\s+(19\d\d|20[0-2]\d)\b", RegexOptions.IgnoreCase);
                if (m.Success && int.TryParse(m.Groups[1].Value, out int day) && day >= 1 && day <= 31 &&
                    GermanMonths.TryGetValue(m.Groups[2].Value, out int mon) && int.TryParse(m.Groups[3].Value, out int yr))
                {
                    return $"{yr:D4}-{mon:D2}-{day:D2}";
                }

                m = Regex.Match(head, @"\b(0[1-9]|[12]\d|3[01])\.(0[1-9]|1[0-2])\.(20[12]\d)\b");
                if (m.Success) return $"{m.Groups[3].Value}-{m.Groups[2].Value}-{m.Groups[1].Value}";
            }

            return fallback.ToString("yyyy-MM-dd", CultureInfo.InvariantCulture);
        }

        private static Dictionary<string, string> ExtractKeyFacts(string text)
        {
            var facts = new Dictionary<string, string>();
            if (string.IsNullOrWhiteSpace(text)) return facts;
            string head = text.Length > 8000 ? text[..8000] : text;

            var m = Regex.Match(head, @"Versicherungsnummer[:\s]*([0-9]{2}\s*[0-9]{6}\s*[A-Za-z]\s*[0-9]{3})", RegexOptions.IgnoreCase);
            if (m.Success) facts["versichertennummer"] = Regex.Replace(m.Groups[1].Value.Trim(), @"\s+", " ");

            m = Regex.Match(head, @"\bDE\d{2}\s*(?:\d{4}\s*){4}\d{2}\b");
            if (m.Success) facts["iban"] = Regex.Replace(m.Value.Trim(), @"\s+", " ");

            m = Regex.Match(head, @"(?:Aktenzeichen|Az\.?|Geschäftszeichen)[:\s]+([A-Za-z0-9\-/\. ]{4,30})", RegexOptions.IgnoreCase);
            if (m.Success) facts["aktenzeichen"] = m.Groups[1].Value.Trim();

            var amts = Regex.Matches(head, @"(?:\d{1,3}(?:\.\d{3})*|\d+),\d{2}\s*(?:€|EUR)")
                .Select(x => x.Value.Trim())
                .Distinct()
                .Take(4)
                .ToList();
            if (amts.Count > 0) facts["betraege"] = string.Join(", ", amts);

            return facts;
        }

        private static string TruncateClean(string s, int maxLen)
        {
            if (string.IsNullOrWhiteSpace(s) || s.Length <= maxLen) return s.Trim();
            return s[..(maxLen - 3)].TrimEnd() + "...";
        }
    }
}
