using System;
using System.Collections.Concurrent;
using System.Collections.Generic;
using System.IO;
using System.Windows;
using System.Windows.Media;
using System.Windows.Media.Imaging;

namespace FunkenwerkTresor
{
    public sealed class KeePassIconOption
    {
        public int IconId { get; init; }
        public string Name { get; init; } = "";
        public ImageSource Image { get; init; } = null!;
    }

    /// <summary>
    /// Zeichnet gestochen scharfe 16x16 Vektor-Icons kompatibel mit den originalen KeePass 2 IconIDs (0..68)
    /// sowie benutzerdefinierten PNG-Icons (<CustomIcons>) aus .kdbx-Datenbanken.
    /// </summary>
    public static class KeePassIconRenderer
    {
        private static readonly ConcurrentDictionary<int, ImageSource> _iconCache = new();
        private static readonly ConcurrentDictionary<string, ImageSource> _customCache = new(StringComparer.OrdinalIgnoreCase);

        public static void ClearCustomCache()
        {
            _customCache.Clear();
        }

        public static ImageSource GetIcon(int iconId, string? customIconUuidHex = null, Dictionary<string, byte[]>? customIcons = null, bool isRootDbFolder = false)
        {
            if (!string.IsNullOrWhiteSpace(customIconUuidHex) && customIcons != null &&
                customIcons.TryGetValue(customIconUuidHex, out byte[]? pngBytes) && pngBytes != null && pngBytes.Length > 0)
            {
                return _customCache.GetOrAdd(customIconUuidHex, _ =>
                {
                    try
                    {
                        using var ms = new MemoryStream(pngBytes, writable: false);
                        var bmp = new BitmapImage();
                        bmp.BeginInit();
                        bmp.CacheOption = BitmapCacheOption.OnLoad;
                        bmp.StreamSource = ms;
                        bmp.EndInit();
                        bmp.Freeze();
                        return bmp;
                    }
                    catch
                    {
                        return GetStandardIcon(iconId);
                    }
                });
            }

            if (isRootDbFolder && (iconId == 48 || iconId == 49))
                return GetStandardIcon(1048); // Spezieller blauer DB-Ordner wie im KeePass-Screenshot

            return GetStandardIcon(iconId);
        }

        public static ImageSource GetStandardIcon(int iconId)
        {
            return _iconCache.GetOrAdd(iconId, BuildStandardIcon);
        }

        public static List<KeePassIconOption> GetSelectableIcons()
        {
            var defs = new (int Id, string Label)[]
            {
                (0,  "Schlüssel (Standard)"),
                (48, "Ordner (Gelb)"),
                (1048, "Datenbank-Ordner (Blau)"),
                (61, "Stern / Favorit"),
                (1,  "Internet / Weltkugel"),
                (40, "E-Mail mit Lupe"),
                (19, "E-Mail Brief"),
                (3,  "Netzwerk / PC / Server"),
                (66, "Finanzen ($)"),
                (53, "Smarthome (Grün)"),
                (7,  "Einkauf / Notizblock"),
                (32, "Betriebssysteme"),
                (58, "Karten / Identität"),
                (55, "Person / Benutzer"),
                (12, "WLAN / Funk"),
                (11, "Kamera / Cams"),
                (27, "NAS / Festplatte"),
                (60, "Haus / Privat"),
                (24, "Sicherheit / Schild"),
                (43, "Papierkorb")
            };

            var list = new List<KeePassIconOption>(defs.Length);
            foreach (var d in defs)
            {
                list.Add(new KeePassIconOption
                {
                    IconId = d.Id,
                    Name = d.Label,
                    Image = GetStandardIcon(d.Id)
                });
            }
            return list;
        }

        private static Brush Solid(string hex)
        {
            var b = (SolidColorBrush)new BrushConverter().ConvertFromString(hex)!;
            b.Freeze();
            return b;
        }

        private static Pen Stroke(string hex, double thickness = 1.0)
        {
            var p = new Pen(Solid(hex), thickness)
            {
                StartLineCap = PenLineCap.Round,
                EndLineCap = PenLineCap.Round,
                LineJoin = PenLineJoin.Round
            };
            p.Freeze();
            return p;
        }

        private static ImageSource BuildStandardIcon(int iconId)
        {
            var group = new DrawingGroup();
            // Transparent 16x16 canvas bounds so every icon aligns pixel-perfectly in TreeView & DataGrid
            group.Children.Add(new GeometryDrawing(Brushes.Transparent, null, new RectangleGeometry(new Rect(0, 0, 16, 16))));

            switch (iconId)
            {
                case 1048: // Blauer DB-Wurzelordner ("DB" im Screenshot)
                    DrawFolder(group, "#3B82F6", "#1D4ED8", "#93C5FD");
                    break;

                case 48: // Gelber Ordner ("Hosting" etc.)
                case 49:
                case 50:
                case 4:
                    DrawFolder(group, "#FBBF24", "#B45309", "#FDE68A");
                    break;

                case 61: // Goldener Stern ("Privat", "Mein-Seelenfunke", "Social Media")
                case 28:
                    DrawStar(group);
                    break;

                case 40: // E-Mail mit Lupe ("Emails", "Fakes", "Original")
                    DrawEmail(group, withMagnifier: true);
                    break;

                case 19: // E-Mail Brief ("E-Mails")
                case 25:
                    DrawEmail(group, withMagnifier: false);
                    break;

                case 1: // Weltkugel ("Internet", "Online Shops", "Postdienste", "Portale", "Games", "Programme", "Webseiten", "Accounts")
                case 8:
                case 16:
                    DrawGlobe(group);
                    break;

                case 53: // Smarthome (Weißes Quadrat mit grünem Button wie im Screenshot)
                case 39:
                    DrawSmarthome(group);
                    break;

                case 66: // Grünes Dollar-Zeichen ("Financial")
                case 37:
                    DrawDollar(group);
                    break;

                case 3:  // Blauer Monitor + PC ("Netzwerk", "Router", "NAS", "APs", "Cams", "Shops", "Geräte", "Lenovo IdeaPad Pro 5")
                case 18:
                case 23:
                case 29:
                case 30:
                case 35:
                    DrawComputer(group);
                    break;

                case 32: // Betriebssysteme (Fenster mit bunten Mini-Icons)
                case 47:
                    DrawOsWindow(group);
                    break;

                case 58: // Karten (Gelber Benutzer mit Schlüssel)
                case 13:
                    DrawUserCardKey(group);
                    break;

                case 5:  // Grüner Benutzer ("Alina")
                case 9:
                case 55:
                    DrawGreenUser(group);
                    break;

                case 7:  // Notizblock mit Stift ("Einkauf")
                case 10:
                case 21:
                case 22:
                case 44:
                case 54:
                    DrawNotepadWithPen(group);
                    break;

                case 43: // Papierkorb
                    DrawTrashBin(group);
                    break;

                case 11: // Kamera
                    DrawCamera(group);
                    break;

                case 12: // WLAN / Antenne
                    DrawWifi(group);
                    break;

                case 27: // Festplatte / NAS
                case 26:
                case 38:
                    DrawDrive(group);
                    break;

                case 60: // Haus
                    DrawHome(group);
                    break;

                case 24: // Schild
                case 51:
                case 52:
                    DrawShield(group);
                    break;

                case 0:  // Goldener Schlüssel mit grünem Anhänger ("Felix-Machts", "Seelenfunke", "Finance-Manager", "Steinhauer-Invest", "Fim", "Entwicklung")
                default:
                    DrawKeyWithTag(group);
                    break;
            }

            group.Freeze();
            var img = new DrawingImage(group);
            img.Freeze();
            return img;
        }

        private static void DrawFolder(DrawingGroup g, string fillHex, string borderHex, string tabHex)
        {
            // Back tab
            var tabGeo = Geometry.Parse("M 1.5,3.5 L 6.5,3.5 L 8,5.2 L 14.5,5.2 L 14.5,13.2 L 1.5,13.2 Z");
            g.Children.Add(new GeometryDrawing(Solid(tabHex), Stroke(borderHex, 0.9), tabGeo));

            // Front flap
            var frontGeo = new RectangleGeometry(new Rect(1.5, 5.5, 13.0, 7.8), 1.0, 1.0);
            g.Children.Add(new GeometryDrawing(Solid(fillHex), Stroke(borderHex, 0.9), frontGeo));
        }

        private static void DrawStar(DrawingGroup g)
        {
            var starGeo = Geometry.Parse("M 8,1.2 L 9.9,5.4 L 14.5,5.9 L 11.0,9.0 L 12.0,13.6 L 8,11.2 L 4.0,13.6 L 5.0,9.0 L 1.5,5.9 L 6.1,5.4 Z");
            g.Children.Add(new GeometryDrawing(Solid("#FACC15"), Stroke("#B45309", 1.0), starGeo));
        }

        private static void DrawEmail(DrawingGroup g, bool withMagnifier)
        {
            var envRect = new RectangleGeometry(new Rect(1.2, 3.2, 12.6, 8.8), 0.8, 0.8);
            g.Children.Add(new GeometryDrawing(Solid("#F8FAFC"), Stroke("#475569", 0.95), envRect));

            var flap = Geometry.Parse("M 1.5,3.6 L 7.5,8.2 L 13.5,3.6");
            g.Children.Add(new GeometryDrawing(null, Stroke("#3B82F6", 1.05), flap));

            if (withMagnifier)
            {
                var lens = new EllipseGeometry(new Point(11.3, 10.6), 2.5, 2.5);
                g.Children.Add(new GeometryDrawing(Solid("#DBEAFE"), Stroke("#1D4ED8", 1.1), lens));
                var handle = new LineGeometry(new Point(13.1, 12.4), new Point(15.0, 14.4));
                g.Children.Add(new GeometryDrawing(null, Stroke("#B45309", 1.5), handle));
            }
        }

        private static void DrawGlobe(DrawingGroup g)
        {
            var circle = new EllipseGeometry(new Point(8, 8), 6.2, 6.2);
            g.Children.Add(new GeometryDrawing(Solid("#0EA5E9"), Stroke("#0369A1", 1.0), circle));

            // Continents
            var land1 = Geometry.Parse("M 5.2,3.5 C 7.0,4.0 7.8,5.8 6.5,7.4 C 5.5,8.5 6.8,10.5 5.8,12.2 C 4.2,11.2 3.2,8.8 3.6,6.0 Z");
            var land2 = Geometry.Parse("M 9.5,4.2 C 11.5,4.8 12.6,7.0 11.8,9.8 C 10.8,11.2 9.4,10.5 9.2,8.5 C 9.0,6.8 8.5,5.2 9.5,4.2 Z");
            g.Children.Add(new GeometryDrawing(Solid("#22C55E"), null, land1));
            g.Children.Add(new GeometryDrawing(Solid("#16A34A"), null, land2));

            // Gloss highlight
            var gloss = new EllipseGeometry(new Point(6.0, 5.2), 2.2, 1.3);
            g.Children.Add(new GeometryDrawing(Solid("#55FFFFFF"), null, gloss));
        }

        private static void DrawSmarthome(DrawingGroup g)
        {
            var box = new RectangleGeometry(new Rect(2.0, 2.0, 12.0, 12.0), 1.8, 1.8);
            g.Children.Add(new GeometryDrawing(Solid("#F8FAFC"), Stroke("#64748B", 0.95), box));

            var dot = new EllipseGeometry(new Point(8, 8), 3.9, 3.9);
            g.Children.Add(new GeometryDrawing(Solid("#22C55E"), Stroke("#15803D", 0.9), dot));
        }

        private static void DrawDollar(DrawingGroup g)
        {
            var sCurve = Geometry.Parse("M 10.8,4.6 C 10.2,3.2 6.0,3.0 5.5,5.2 C 5.0,7.2 11.0,7.6 10.6,10.4 C 10.2,12.6 5.8,12.5 5.0,10.8");
            g.Children.Add(new GeometryDrawing(null, Stroke("#16A34A", 2.0), sCurve));

            var vLine = new LineGeometry(new Point(8.0, 1.6), new Point(8.0, 14.4));
            g.Children.Add(new GeometryDrawing(null, Stroke("#15803D", 1.4), vLine));
        }

        private static void DrawComputer(DrawingGroup g)
        {
            // PC Tower on right
            var tower = new RectangleGeometry(new Rect(10.5, 2.2, 4.0, 10.5), 0.5, 0.5);
            g.Children.Add(new GeometryDrawing(Solid("#CBD5E1"), Stroke("#475569", 0.85), tower));

            // Monitor on left
            var screenFrame = new RectangleGeometry(new Rect(1.4, 3.4, 9.6, 7.4), 0.7, 0.7);
            g.Children.Add(new GeometryDrawing(Solid("#1E293B"), Stroke("#334155", 0.85), screenFrame));

            var lcd = new RectangleGeometry(new Rect(2.3, 4.3, 7.8, 5.6));
            g.Children.Add(new GeometryDrawing(Solid("#38BDF8"), null, lcd));

            // Monitor Stand
            var stand = Geometry.Parse("M 4.5,10.8 L 7.9,10.8 L 8.9,13.2 L 3.5,13.2 Z");
            g.Children.Add(new GeometryDrawing(Solid("#94A3B8"), Stroke("#475569", 0.8), stand));
        }

        private static void DrawOsWindow(DrawingGroup g)
        {
            var win = new RectangleGeometry(new Rect(1.5, 2.2, 13.0, 11.6), 1.0, 1.0);
            g.Children.Add(new GeometryDrawing(Solid("#F8FAFC"), Stroke("#475569", 0.95), win));

            var titleBar = new RectangleGeometry(new Rect(1.5, 2.2, 13.0, 3.0));
            g.Children.Add(new GeometryDrawing(Solid("#3B82F6"), null, titleBar));

            // 3 small app icons inside window
            g.Children.Add(new GeometryDrawing(Solid("#EF4444"), null, new RectangleGeometry(new Rect(3.2, 6.8, 2.6, 2.6))));
            g.Children.Add(new GeometryDrawing(Solid("#22C55E"), null, new RectangleGeometry(new Rect(6.8, 6.8, 2.6, 2.6))));
            g.Children.Add(new GeometryDrawing(Solid("#F59E0B"), null, new RectangleGeometry(new Rect(10.4, 6.8, 2.6, 2.6))));
        }

        private static void DrawUserCardKey(DrawingGroup g)
        {
            // Head & Shoulders in warm gold
            g.Children.Add(new GeometryDrawing(Solid("#FBBF24"), Stroke("#B45309", 0.9), new EllipseGeometry(new Point(6.2, 5.0), 2.5, 2.5)));
            var body = Geometry.Parse("M 2.0,13.5 C 2.0,9.8 10.4,9.8 10.4,13.5 Z");
            g.Children.Add(new GeometryDrawing(Solid("#F59E0B"), Stroke("#B45309", 0.9), body));

            // Small key on right
            g.Children.Add(new GeometryDrawing(Solid("#FEF08A"), Stroke("#A16207", 0.9), new EllipseGeometry(new Point(12.5, 6.2), 2.0, 2.0)));
            g.Children.Add(new GeometryDrawing(null, Stroke("#A16207", 1.3), new LineGeometry(new Point(12.5, 8.2), new Point(12.5, 13.5))));
        }

        private static void DrawGreenUser(DrawingGroup g)
        {
            g.Children.Add(new GeometryDrawing(Solid("#FDE68A"), Stroke("#92400E", 0.9), new EllipseGeometry(new Point(8.0, 4.8), 2.7, 2.7)));
            var body = Geometry.Parse("M 3.0,13.8 C 3.0,9.5 13.0,9.5 13.0,13.8 Z");
            g.Children.Add(new GeometryDrawing(Solid("#22C55E"), Stroke("#15803D", 0.95), body));
        }

        private static void DrawNotepadWithPen(DrawingGroup g)
        {
            var page = new RectangleGeometry(new Rect(2.0, 1.8, 9.8, 12.4), 0.7, 0.7);
            g.Children.Add(new GeometryDrawing(Solid("#FFFFFF"), Stroke("#3B82F6", 0.95), page));

            g.Children.Add(new GeometryDrawing(null, Stroke("#94A3B8", 0.9), new LineGeometry(new Point(3.8, 5.0), new Point(9.6, 5.0))));
            g.Children.Add(new GeometryDrawing(null, Stroke("#94A3B8", 0.9), new LineGeometry(new Point(3.8, 7.6), new Point(9.6, 7.6))));
            g.Children.Add(new GeometryDrawing(null, Stroke("#94A3B8", 0.9), new LineGeometry(new Point(3.8, 10.2), new Point(8.2, 10.2))));

            // Pencil diagonal
            var pen = Geometry.Parse("M 13.4,2.8 L 14.8,4.2 L 9.2,9.8 L 7.4,10.2 L 7.8,8.4 Z");
            g.Children.Add(new GeometryDrawing(Solid("#FACC15"), Stroke("#B45309", 0.85), pen));
        }

        private static void DrawKeyWithTag(DrawingGroup g)
        {
            // Green tag on bottom-right (classic KeePass key entry icon)
            var tag = Geometry.Parse("M 6.5,8.2 L 13.8,8.2 L 13.8,13.5 L 6.5,13.5 Z");
            g.Children.Add(new GeometryDrawing(Solid("#22C55E"), Stroke("#15803D", 0.85), tag));

            // Golden Key
            var head = new EllipseGeometry(new Point(5.0, 5.2), 3.0, 3.0);
            g.Children.Add(new GeometryDrawing(Solid("#FACC15"), Stroke("#B45309", 0.95), head));

            var shaft = Geometry.Parse("M 7.2,7.2 L 13.5,13.2 M 10.8,10.6 L 9.4,12.0 M 12.6,12.3 L 11.2,13.7");
            g.Children.Add(new GeometryDrawing(null, Stroke("#B45309", 1.4), shaft));
        }

        private static void DrawTrashBin(DrawingGroup g)
        {
            var bin = new RectangleGeometry(new Rect(3.5, 4.5, 9.0, 9.5), 0.8, 0.8);
            g.Children.Add(new GeometryDrawing(Solid("#E2E8F0"), Stroke("#475569", 0.95), bin));
            g.Children.Add(new GeometryDrawing(null, Stroke("#475569", 1.2), new LineGeometry(new Point(2.5, 4.2), new Point(13.5, 4.2))));
            g.Children.Add(new GeometryDrawing(null, Stroke("#64748B", 0.9), new LineGeometry(new Point(6.2, 6.5), new Point(6.2, 12.0))));
            g.Children.Add(new GeometryDrawing(null, Stroke("#64748B", 0.9), new LineGeometry(new Point(9.8, 6.5), new Point(9.8, 12.0))));
        }

        private static void DrawCamera(DrawingGroup g)
        {
            var body = new RectangleGeometry(new Rect(1.8, 4.2, 12.4, 9.0), 1.4, 1.4);
            g.Children.Add(new GeometryDrawing(Solid("#475569"), Stroke("#1E293B", 0.9), body));
            g.Children.Add(new GeometryDrawing(Solid("#38BDF8"), Stroke("#FFFFFF", 0.9), new EllipseGeometry(new Point(8.0, 8.7), 2.8, 2.8)));
        }

        private static void DrawWifi(DrawingGroup g)
        {
            g.Children.Add(new GeometryDrawing(null, Stroke("#0284C7", 1.4), Geometry.Parse("M 2.0,6.2 A 8.5,8.5 0 0 1 14.0,6.2")));
            g.Children.Add(new GeometryDrawing(null, Stroke("#0284C7", 1.4), Geometry.Parse("M 4.4,8.8 A 5.2,5.2 0 0 1 11.6,8.8")));
            g.Children.Add(new GeometryDrawing(Solid("#0369A1"), null, new EllipseGeometry(new Point(8.0, 12.2), 1.4, 1.4)));
        }

        private static void DrawDrive(DrawingGroup g)
        {
            var box = new RectangleGeometry(new Rect(2.0, 4.5, 12.0, 7.0), 1.2, 1.2);
            g.Children.Add(new GeometryDrawing(Solid("#CBD5E1"), Stroke("#475569", 0.95), box));
            g.Children.Add(new GeometryDrawing(Solid("#22C55E"), null, new EllipseGeometry(new Point(11.5, 8.0), 1.1, 1.1)));
        }

        private static void DrawHome(DrawingGroup g)
        {
            var roof = Geometry.Parse("M 1.5,8.2 L 8.0,2.2 L 14.5,8.2");
            var walls = new RectangleGeometry(new Rect(3.5, 7.8, 9.0, 6.0));
            g.Children.Add(new GeometryDrawing(Solid("#FEF3C7"), Stroke("#92400E", 0.95), walls));
            g.Children.Add(new GeometryDrawing(null, Stroke("#DC2626", 1.6), roof));
        }

        private static void DrawShield(DrawingGroup g)
        {
            var shield = Geometry.Parse("M 8.0,1.6 L 13.5,3.8 L 13.5,8.2 C 13.5,11.5 10.8,13.6 8.0,14.6 C 5.2,13.6 2.5,11.5 2.5,8.2 L 2.5,3.8 Z");
            g.Children.Add(new GeometryDrawing(Solid("#FACC15"), Stroke("#B45309", 1.0), shield));
        }
    }
}
