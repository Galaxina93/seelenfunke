using System;
using System.Collections.Generic;
using System.Diagnostics;
using System.Globalization;
using System.IO;
using System.Linq;
using System.Media;
using System.Threading.Tasks;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Input;
using System.Windows.Media;
using System.Windows.Media.Imaging;
using System.Windows.Threading;
using Brush = System.Windows.Media.Brush;
using Button = System.Windows.Controls.Button;
using Color = System.Windows.Media.Color;
using Cursors = System.Windows.Input.Cursors;
using FontFamily = System.Windows.Media.FontFamily;
using HorizontalAlignment = System.Windows.HorizontalAlignment;
using KeyEventArgs = System.Windows.Input.KeyEventArgs;
using Orientation = System.Windows.Controls.Orientation;
using Pen = System.Windows.Media.Pen;
using Point = System.Windows.Point;
using Size = System.Windows.Size;
using VerticalAlignment = System.Windows.VerticalAlignment;

namespace FunkenwerkOS
{
    /// <summary>
    /// Ultra-luxury native DirectX GPU-Sprite 60-FPS 3D Crystal-Glass, Silk-Ribbon & Diamond-Flare Stage Renderer.
    /// Pre-bakes all radial gradients, dashed Haute-Horlogerie filigree rings, flowing silk light ribbons,
    /// 4-point anamorphic diamond stars, bokeh orbs, and pre-clipped sapphire-glass sheen frames ONCE at startup
    /// into frozen DirectX GPU textures (RenderTargetBitmap).
    /// OnRender performs ZERO geometry tessellation, ZERO dash-breaking, ZERO software clip masks, and ZERO allocations.
    /// </summary>
    public sealed class GewerbeMagicPortalRenderer : FrameworkElement
    {
        private const int PaletteCount = 2; // 0 = 24K Gold, 1 = Smaragd Emerald
        private const int SparkAlphaLevels = 12;
        private const int BokehAlphaLevels = 8;
        private const int ShockwaveAlphaLevels = 10;
        private const double SheenCycleIntervalSeconds = 10.0;
        private const double SheenSweepDurationSeconds = 1.45;
        private const int SparkCount = 88;
        private const int BokehCount = 18;

        private struct VortexSpark
        {
            public double Angle;
            public double BaseRadiusNorm;
            public double RadiusNorm;
            public double AngularSpeed;
            public double RadialPhase;
            public double Size;
            public double TwinklePhase;
            public bool IsDiamondStar;
        }

        private struct BokehOrb
        {
            public double Angle;
            public double RadiusNorm;
            public double Speed;
            public double Size;
            public double Phase;
        }

        private struct ShockwaveRing
        {
            public double RadiusNorm;
            public double Alpha;
            public bool Active;
        }

        // Pre-baked frozen DirectX GPU Textures (0 = Gold, 1 = Emerald)
        private static readonly BitmapSource[] s_backdropAuraTex = new BitmapSource[PaletteCount];
        private static readonly BitmapSource[] s_outerFiligreeRingTex = new BitmapSource[PaletteCount];
        private static readonly BitmapSource[] s_midSilkRibbonTexA = new BitmapSource[PaletteCount];
        private static readonly BitmapSource[] s_midSilkRibbonTexB = new BitmapSource[PaletteCount];
        private static readonly BitmapSource[] s_crystalSphereTex = new BitmapSource[PaletteCount];
        private static readonly BitmapSource[,] s_sparkGlowTex = new BitmapSource[PaletteCount, SparkAlphaLevels];
        private static readonly BitmapSource[,] s_diamondStarTex = new BitmapSource[PaletteCount, SparkAlphaLevels];
        private static readonly BitmapSource[,] s_bokehOrbTex = new BitmapSource[PaletteCount, BokehAlphaLevels];
        private static readonly BitmapSource[,] s_shockwaveRingTex = new BitmapSource[PaletteCount, ShockwaveAlphaLevels];

        static GewerbeMagicPortalRenderer()
        {
            for (int p = 0; p < PaletteCount; p++)
            {
                bool isEmerald = (p == 1);
                Color primary = isEmerald ? Color.FromRgb(16, 185, 129) : Color.FromRgb(197, 160, 89);
                Color bright = isEmerald ? Color.FromRgb(167, 243, 208) : Color.FromRgb(253, 230, 138);
                Color deep = isEmerald ? Color.FromRgb(5, 110, 75) : Color.FromRgb(120, 82, 24);

                s_backdropAuraTex[p] = BakeBackdropAuraTexture(primary, bright, deep, isEmerald);
                s_outerFiligreeRingTex[p] = BakeOuterFiligreeRingTexture(primary, bright);
                s_midSilkRibbonTexA[p] = BakeMidSilkRibbonTextureA(primary, bright, isEmerald);
                s_midSilkRibbonTexB[p] = BakeMidSilkRibbonTextureB(primary, bright, isEmerald);
                s_crystalSphereTex[p] = BakeCrystalSphereTexture(primary, bright);

                for (int a = 0; a < SparkAlphaLevels; a++)
                {
                    double normA = (a + 1) / (double)SparkAlphaLevels;
                    s_sparkGlowTex[p, a] = BakeSparkGlowSprite(primary, bright, normA);
                    s_diamondStarTex[p, a] = BakeDiamondStarSprite(primary, bright, normA);
                }

                for (int a = 0; a < BokehAlphaLevels; a++)
                {
                    double normA = (a + 1) / (double)BokehAlphaLevels;
                    s_bokehOrbTex[p, a] = BakeBokehOrbSprite(primary, bright, normA);
                }

                for (int a = 0; a < ShockwaveAlphaLevels; a++)
                {
                    double normA = (a + 1) / (double)ShockwaveAlphaLevels;
                    s_shockwaveRingTex[p, a] = BakeShockwaveRingSprite(bright, normA);
                }
            }
        }

        private static BitmapSource RenderVisualToFrozenBitmap(DrawingVisual dv, int pixelSize)
        {
            var rtb = new RenderTargetBitmap(pixelSize, pixelSize, 96, 96, PixelFormats.Pbgra32);
            rtb.Render(dv);
            rtb.Freeze();
            return rtb;
        }

        private static BitmapSource BakeBackdropAuraTexture(Color primary, Color bright, Color deep, bool isEmerald)
        {
            const int sz = 512;
            double c = sz * 0.5;
            double outerR = c * 0.96;
            double innerR = c * 0.52;

            var outerAura = new RadialGradientBrush();
            outerAura.GradientStops.Add(new GradientStop(Color.FromArgb((byte)(isEmerald ? 140 : 108), primary.R, primary.G, primary.B), 0.0));
            outerAura.GradientStops.Add(new GradientStop(Color.FromArgb((byte)(isEmerald ? 68 : 50), deep.R, deep.G, deep.B), 0.45));
            outerAura.GradientStops.Add(new GradientStop(Color.FromArgb(0, primary.R, primary.G, primary.B), 1.0));
            outerAura.Freeze();

            var innerCorona = new RadialGradientBrush();
            innerCorona.GradientStops.Add(new GradientStop(Color.FromArgb((byte)(isEmerald ? 195 : 158), bright.R, bright.G, bright.B), 0.0));
            innerCorona.GradientStops.Add(new GradientStop(Color.FromArgb((byte)(isEmerald ? 112 : 86), primary.R, primary.G, primary.B), 0.42));
            innerCorona.GradientStops.Add(new GradientStop(Color.FromArgb(0, primary.R, primary.G, primary.B), 1.0));
            innerCorona.Freeze();

            var dv = new DrawingVisual();
            using (var dc = dv.RenderOpen())
            {
                Point center = new(c, c);
                dc.DrawEllipse(outerAura, null, center, outerR, outerR);
                dc.DrawEllipse(innerCorona, null, center, innerR, innerR);
            }
            return RenderVisualToFrozenBitmap(dv, sz);
        }

        private static BitmapSource BakeOuterFiligreeRingTexture(Color primary, Color bright)
        {
            const int sz = 640;
            double c = sz * 0.5;
            double ringR = 264.0;
            Point center = new(c, c);

            var softGlowPen = new Pen(new SolidColorBrush(Color.FromArgb(46, primary.R, primary.G, primary.B)), 5.0);
            softGlowPen.Freeze();

            var outerRingPen = new Pen(new SolidColorBrush(Color.FromArgb(165, bright.R, bright.G, bright.B)), 1.5)
            {
                DashStyle = new DashStyle(new double[] { 5, 11 }, 0)
            };
            outerRingPen.Freeze();

            var fineInnerPen = new Pen(new SolidColorBrush(Color.FromArgb(75, bright.R, bright.G, bright.B)), 0.9);
            fineInnerPen.Freeze();

            var dv = new DrawingVisual();
            using (var dc = dv.RenderOpen())
            {
                dc.DrawEllipse(null, softGlowPen, center, ringR, ringR);
                dc.DrawEllipse(null, outerRingPen, center, ringR, ringR);
                dc.DrawEllipse(null, fineInnerPen, center, ringR - 11.0, ringR - 11.0);

                for (int n = 0; n < 4; n++)
                {
                    double na = n * Math.PI * 0.5;
                    Point np = new(center.X + Math.Cos(na) * ringR, center.Y + Math.Sin(na) * ringR);
                    DrawVectorDiamondStar(dc, np, 15.0, primary, bright, 1.0);
                }
            }
            return RenderVisualToFrozenBitmap(dv, sz);
        }

        private static BitmapSource BakeMidSilkRibbonTextureA(Color primary, Color bright, bool isEmerald)
        {
            const int sz = 640;
            double c = sz * 0.5;
            double orbR = 171.5; // Scaled relative to outerR = 264 (264 / 1.54)
            double midR = orbR * 1.31;
            Point center = new(c, c);

            var midRingPen = new Pen(new SolidColorBrush(Color.FromArgb(190, primary.R, primary.G, primary.B)), 2.1)
            {
                DashStyle = new DashStyle(new double[] { 28, 10, 4, 10 }, 0)
            };
            midRingPen.Freeze();

            var dv = new DrawingVisual();
            using (var dc = dv.RenderOpen())
            {
                dc.DrawEllipse(null, midRingPen, center, midR, midR);

                // Silk Ribbons 0 & 2 (Clockwise flowing silk light waves + diamond star tips)
                int[] ribbons = { 0, 2 };
                foreach (int rib in ribbons)
                {
                    double baseOff = rib * (Math.PI * 2.0 / 3.0);
                    double baseR = orbR * (1.20 + rib * 0.16);
                    byte ra = (byte)(162 - rib * 28);

                    var glowPen = new Pen(new SolidColorBrush(Color.FromArgb((byte)(ra * 0.35), primary.R, primary.G, primary.B)), 6.2 - rib * 0.8)
                    {
                        StartLineCap = PenLineCap.Round,
                        EndLineCap = PenLineCap.Round
                    };
                    glowPen.Freeze();

                    var corePen = new Pen(new SolidColorBrush(Color.FromArgb(ra, bright.R, bright.G, bright.B)), 2.7 - rib * 0.42)
                    {
                        StartLineCap = PenLineCap.Round,
                        EndLineCap = PenLineCap.Round
                    };
                    corePen.Freeze();

                    var geom = new StreamGeometry();
                    Point tipPt = center;
                    using (var ctx = geom.Open())
                    {
                        const int segs = 48;
                        for (int s = 0; s <= segs; s++)
                        {
                            double frac = s / (double)segs;
                            double a = baseOff + frac * Math.PI * 1.32;
                            double wave = Math.Sin(frac * Math.PI * 3.0 + rib) * (10.5 + (isEmerald ? 6.0 : 0.0));
                            double r = baseR + wave;
                            Point p = new(center.X + Math.Cos(a) * r, center.Y + Math.Sin(a) * r);
                            if (s == 0) ctx.BeginFigure(p, false, false);
                            else ctx.LineTo(p, true, true);
                            if (s == segs) tipPt = p;
                        }
                    }
                    geom.Freeze();
                    dc.DrawGeometry(null, glowPen, geom);
                    dc.DrawGeometry(null, corePen, geom);
                    DrawVectorDiamondStar(dc, tipPt, 15.5, primary, bright, 1.0);
                }
            }
            return RenderVisualToFrozenBitmap(dv, sz);
        }

        private static BitmapSource BakeMidSilkRibbonTextureB(Color primary, Color bright, bool isEmerald)
        {
            const int sz = 640;
            double c = sz * 0.5;
            double orbR = 171.5;
            Point center = new(c, c);

            var dv = new DrawingVisual();
            using (var dc = dv.RenderOpen())
            {
                // Silk Ribbon 1 + Counter-Flowing Companion Silk Wave
                for (int pass = 0; pass < 2; pass++)
                {
                    double baseOff = (Math.PI * 2.0 / 3.0) + pass * Math.PI;
                    double baseR = orbR * (1.36 - pass * 0.08);
                    byte ra = (byte)(pass == 0 ? 142 : 108);

                    var glowPen = new Pen(new SolidColorBrush(Color.FromArgb((byte)(ra * 0.34), primary.R, primary.G, primary.B)), 5.2 - pass * 1.0)
                    {
                        StartLineCap = PenLineCap.Round,
                        EndLineCap = PenLineCap.Round
                    };
                    glowPen.Freeze();

                    var corePen = new Pen(new SolidColorBrush(Color.FromArgb(ra, bright.R, bright.G, bright.B)), 2.3 - pass * 0.4)
                    {
                        StartLineCap = PenLineCap.Round,
                        EndLineCap = PenLineCap.Round
                    };
                    corePen.Freeze();

                    var geom = new StreamGeometry();
                    Point tipPt = center;
                    using (var ctx = geom.Open())
                    {
                        const int segs = 48;
                        for (int s = 0; s <= segs; s++)
                        {
                            double frac = s / (double)segs;
                            double a = baseOff + frac * Math.PI * 1.28;
                            double wave = Math.Sin(frac * Math.PI * 3.0 + 1.2 + pass) * (10.0 + (isEmerald ? 5.5 : 0.0));
                            double r = baseR + wave;
                            Point p = new(center.X + Math.Cos(a) * r, center.Y + Math.Sin(a) * r);
                            if (s == 0) ctx.BeginFigure(p, false, false);
                            else ctx.LineTo(p, true, true);
                            if (s == segs) tipPt = p;
                        }
                    }
                    geom.Freeze();
                    dc.DrawGeometry(null, glowPen, geom);
                    dc.DrawGeometry(null, corePen, geom);
                    DrawVectorDiamondStar(dc, tipPt, pass == 0 ? 15.0 : 12.5, primary, bright, 1.0);
                }
            }
            return RenderVisualToFrozenBitmap(dv, sz);
        }

        private static BitmapSource BakeCrystalSphereTexture(Color primary, Color bright)
        {
            const int sz = 512;
            double c = sz * 0.5;
            double orbR = 236.0;
            Point center = new(c, c);

            var crystal = new RadialGradientBrush
            {
                Center = new Point(0.5, 0.5),
                GradientOrigin = new Point(0.32, 0.26),
                RadiusX = 0.52,
                RadiusY = 0.52
            };
            crystal.GradientStops.Add(new GradientStop(Color.FromArgb(245, 30, 36, 52), 0.0));
            crystal.GradientStops.Add(new GradientStop(Color.FromArgb(250, 11, 14, 22), 0.62));
            crystal.GradientStops.Add(new GradientStop(Color.FromArgb(252, 5, 7, 12), 0.88));
            crystal.GradientStops.Add(new GradientStop(Color.FromArgb(230, primary.R, primary.G, primary.B), 1.0));
            crystal.Freeze();

            var rimCaustic = new RadialGradientBrush();
            rimCaustic.GradientStops.Add(new GradientStop(Color.FromArgb(0, primary.R, primary.G, primary.B), 0.68));
            rimCaustic.GradientStops.Add(new GradientStop(Color.FromArgb(65, primary.R, primary.G, primary.B), 0.90));
            rimCaustic.GradientStops.Add(new GradientStop(Color.FromArgb(150, bright.R, bright.G, bright.B), 1.0));
            rimCaustic.Freeze();

            var bezelBrush = new LinearGradientBrush(bright, primary, new Point(0.0, 0.0), new Point(1.0, 1.0));
            bezelBrush.Freeze();
            var innerBezelPen = new Pen(bezelBrush, 3.2);
            innerBezelPen.Freeze();

            var fineInnerPen = new Pen(new SolidColorBrush(Color.FromArgb(85, bright.R, bright.G, bright.B)), 1.0);
            fineInnerPen.Freeze();

            var specBrush = new LinearGradientBrush(
                Color.FromArgb(215, 255, 255, 255),
                Color.FromArgb(0, 255, 255, 255),
                new Point(0.2, 0.0),
                new Point(0.8, 1.0));
            specBrush.Freeze();
            var specPen = new Pen(specBrush, 2.8)
            {
                StartLineCap = PenLineCap.Round,
                EndLineCap = PenLineCap.Round
            };
            specPen.Freeze();

            var arcGeom = new StreamGeometry();
            using (var ctx = arcGeom.Open())
            {
                double rArc = orbR * 0.91;
                Point pStart = new(center.X + Math.Cos(-2.45) * rArc, center.Y + Math.Sin(-2.45) * rArc);
                Point pEnd = new(center.X + Math.Cos(-0.68) * rArc, center.Y + Math.Sin(-0.68) * rArc);
                ctx.BeginFigure(pStart, false, false);
                ctx.ArcTo(pEnd, new Size(rArc, rArc), 0, false, SweepDirection.Clockwise, true, false);
            }
            arcGeom.Freeze();

            var dv = new DrawingVisual();
            using (var dc = dv.RenderOpen())
            {
                dc.DrawEllipse(crystal, null, center, orbR, orbR);
                dc.DrawEllipse(rimCaustic, innerBezelPen, center, orbR, orbR);
                dc.DrawEllipse(null, fineInnerPen, center, orbR * 0.94, orbR * 0.94);
                dc.DrawGeometry(null, specPen, arcGeom);
            }
            return RenderVisualToFrozenBitmap(dv, sz);
        }

        private static LinearGradientBrush CreateLiveSheenBrush(Color bright, Transform relativeTransform)
        {
            var sheen = new LinearGradientBrush
            {
                StartPoint = new Point(0.0, 0.22),
                EndPoint = new Point(1.0, 0.78),
                SpreadMethod = GradientSpreadMethod.Pad,
                RelativeTransform = relativeTransform
            };
            sheen.GradientStops.Add(new GradientStop(Color.FromArgb(0, 255, 255, 255), 0.00));
            sheen.GradientStops.Add(new GradientStop(Color.FromArgb(0, bright.R, bright.G, bright.B), 0.40));
            sheen.GradientStops.Add(new GradientStop(Color.FromArgb(34, bright.R, bright.G, bright.B), 0.45));
            sheen.GradientStops.Add(new GradientStop(Color.FromArgb(98, 255, 255, 255), 0.49));
            sheen.GradientStops.Add(new GradientStop(Color.FromArgb(28, bright.R, bright.G, bright.B), 0.52));
            sheen.GradientStops.Add(new GradientStop(Color.FromArgb(0, bright.R, bright.G, bright.B), 0.54));
            sheen.GradientStops.Add(new GradientStop(Color.FromArgb(48, 255, 255, 255), 0.565));
            sheen.GradientStops.Add(new GradientStop(Color.FromArgb(0, 255, 255, 255), 0.595));
            sheen.GradientStops.Add(new GradientStop(Color.FromArgb(0, 255, 255, 255), 1.00));
            return sheen;
        }

        private static BitmapSource BakeSparkGlowSprite(Color primary, Color bright, double normAlpha)
        {
            const int sz = 64;
            double c = sz * 0.5;
            byte al = (byte)Math.Clamp((int)Math.Round(normAlpha * 255.0), 0, 255);

            var sp = new RadialGradientBrush();
            sp.GradientStops.Add(new GradientStop(Color.FromArgb(al, 255, 255, 255), 0.0));
            sp.GradientStops.Add(new GradientStop(Color.FromArgb((byte)(al * 0.85), bright.R, bright.G, bright.B), 0.25));
            sp.GradientStops.Add(new GradientStop(Color.FromArgb((byte)(al * 0.38), primary.R, primary.G, primary.B), 0.62));
            sp.GradientStops.Add(new GradientStop(Color.FromArgb(0, primary.R, primary.G, primary.B), 1.0));
            sp.Freeze();

            var dv = new DrawingVisual();
            using (var dc = dv.RenderOpen())
            {
                dc.DrawEllipse(sp, null, new Point(c, c), c * 0.92, c * 0.92);
            }
            return RenderVisualToFrozenBitmap(dv, sz);
        }

        private static BitmapSource BakeDiamondStarSprite(Color primary, Color bright, double normAlpha)
        {
            const int sz = 80;
            double c = sz * 0.5;
            var dv = new DrawingVisual();
            using (var dc = dv.RenderOpen())
            {
                DrawVectorDiamondStar(dc, new Point(c, c), c * 0.78, primary, bright, normAlpha);
            }
            return RenderVisualToFrozenBitmap(dv, sz);
        }

        private static BitmapSource BakeBokehOrbSprite(Color primary, Color bright, double normAlpha)
        {
            const int sz = 96;
            double c = sz * 0.5;
            byte al = (byte)Math.Clamp((int)Math.Round(normAlpha * 255.0), 0, 255);

            var bk = new RadialGradientBrush();
            bk.GradientStops.Add(new GradientStop(Color.FromArgb((byte)(al * 0.34), bright.R, bright.G, bright.B), 0.0));
            bk.GradientStops.Add(new GradientStop(Color.FromArgb((byte)(al * 0.14), primary.R, primary.G, primary.B), 0.55));
            bk.GradientStops.Add(new GradientStop(Color.FromArgb(0, primary.R, primary.G, primary.B), 1.0));
            bk.Freeze();

            var dv = new DrawingVisual();
            using (var dc = dv.RenderOpen())
            {
                dc.DrawEllipse(bk, null, new Point(c, c), c * 0.94, c * 0.94);
            }
            return RenderVisualToFrozenBitmap(dv, sz);
        }

        private static BitmapSource BakeShockwaveRingSprite(Color bright, double normAlpha)
        {
            const int sz = 512;
            double c = sz * 0.5;
            byte al = (byte)Math.Clamp((int)Math.Round(normAlpha * 255.0), 0, 255);

            var swBrush = new SolidColorBrush(Color.FromArgb(al, bright.R, bright.G, bright.B));
            swBrush.Freeze();
            var swPen = new Pen(swBrush, 3.2);
            swPen.Freeze();

            var dv = new DrawingVisual();
            using (var dc = dv.RenderOpen())
            {
                dc.DrawEllipse(null, swPen, new Point(c, c), c * 0.94, c * 0.94);
            }
            return RenderVisualToFrozenBitmap(dv, sz);
        }

        private static void DrawVectorDiamondStar(DrawingContext dc, Point pt, double radius, Color primary, Color bright, double normAlpha)
        {
            byte al = (byte)Math.Clamp((int)Math.Round(normAlpha * 255.0), 0, 255);
            var glow = new RadialGradientBrush();
            glow.GradientStops.Add(new GradientStop(Color.FromArgb(al, 255, 255, 255), 0.0));
            glow.GradientStops.Add(new GradientStop(Color.FromArgb((byte)(al * 0.85), bright.R, bright.G, bright.B), 0.25));
            glow.GradientStops.Add(new GradientStop(Color.FromArgb((byte)(al * 0.38), primary.R, primary.G, primary.B), 0.62));
            glow.GradientStops.Add(new GradientStop(Color.FromArgb(0, primary.R, primary.G, primary.B), 1.0));
            glow.Freeze();

            dc.DrawEllipse(glow, null, pt, radius, radius);

            var coreBrush = new SolidColorBrush(Color.FromArgb(al, 255, 255, 255));
            coreBrush.Freeze();

            var starGeom = new StreamGeometry();
            using (var ctx = starGeom.Open())
            {
                double arm = radius * 1.15;
                double waist = radius * 0.16;
                ctx.BeginFigure(new Point(pt.X, pt.Y - arm), true, true);
                ctx.LineTo(new Point(pt.X + waist, pt.Y - waist), false, false);
                ctx.LineTo(new Point(pt.X + arm, pt.Y), false, false);
                ctx.LineTo(new Point(pt.X + waist, pt.Y + waist), false, false);
                ctx.LineTo(new Point(pt.X, pt.Y + arm), false, false);
                ctx.LineTo(new Point(pt.X - waist, pt.Y + waist), false, false);
                ctx.LineTo(new Point(pt.X - arm, pt.Y), false, false);
                ctx.LineTo(new Point(pt.X - waist, pt.Y - waist), false, false);
            }
            starGeom.Freeze();
            dc.DrawGeometry(coreBrush, null, starGeom);
        }

        private readonly VortexSpark[] _sparks = new VortexSpark[SparkCount];
        private readonly BokehOrb[] _bokeh = new BokehOrb[BokehCount];
        private readonly ShockwaveRing[] _shockwaves = new ShockwaveRing[4];
        private readonly Stopwatch _clock = new();
        private readonly DispatcherTimer _frameHeartbeatTimer;
        private double _lastSec;
        private TimeSpan _lastRenderingTime = TimeSpan.MinValue;
        private bool _running;
        private DateTime _surgeUntilUtc = DateTime.MinValue;
        private double _currentGreenBlend;
        private double _currentSpeedMultiplier = 1.0;
        private double _outerRingDeg;
        private double _midRingDegA;
        private double _midRingDegB;
        private double _ribbonPhase;
        private double _sheenCycleSec;
        private double _portalScale = 1.0;
        private bool _focusModeActive;

        // Reusable Transforms & Live 60-FPS Sapphire Mirror Sheen Brushes so OnRender allocates zero objects
        private readonly RotateTransform _outerRotTransform = new();
        private readonly RotateTransform _midRotTransformA = new();
        private readonly RotateTransform _midRotTransformB = new();
        private readonly TranslateTransform _sheenTranslateTransform = new();
        private readonly LinearGradientBrush[] _liveSheenBrush = new LinearGradientBrush[PaletteCount];

        // Live FPS & CPU Telemetry
        private int _fpsFrameCounter;
        private double _fpsWindowStartSec;
        private double _renderMsAccum;
        private double _renderMsMax;
        private TimeSpan _lastProcCpuTime;
        private double _measuredFps = 60.0;
        private double _measuredRenderAvgMs = 0.2;
        private double _measuredRenderMaxMs = 0.4;
        private double _measuredCpuPercent = 1.0;

        public double CurrentGreenBlend => _currentGreenBlend;
        public bool IsSurgeActive => DateTime.UtcNow < _surgeUntilUtc;
        public double MeasuredFps => _measuredFps;
        public double MeasuredRenderAvgMs => _measuredRenderAvgMs;
        public double MeasuredCpuPercent => _measuredCpuPercent;
        public event Action<double, bool>? BlendStateUpdated;
        public event Action<double, double, double>? FpsMetricsUpdated;

        public GewerbeMagicPortalRenderer()
        {
            IsHitTestVisible = false;
            RenderOptions.SetBitmapScalingMode(this, BitmapScalingMode.Linear);

            _liveSheenBrush[0] = CreateLiveSheenBrush(Color.FromRgb(253, 230, 138), _sheenTranslateTransform);
            _liveSheenBrush[1] = CreateLiveSheenBrush(Color.FromRgb(167, 243, 208), _sheenTranslateTransform);

            _frameHeartbeatTimer = new DispatcherTimer(DispatcherPriority.Render)
            {
                Interval = TimeSpan.FromMilliseconds(45)
            };
            _frameHeartbeatTimer.Tick += (_, _) =>
            {
                if (_running && (_clock.Elapsed.TotalSeconds - _lastSec) > 0.042)
                    InvalidateVisual();
            };

            var rng = new Random(20261110);
            for (int i = 0; i < SparkCount; i++)
            {
                double rNorm = 0.21 + rng.NextDouble() * 0.67;
                _sparks[i] = new VortexSpark
                {
                    Angle = rng.NextDouble() * Math.PI * 2.0,
                    BaseRadiusNorm = rNorm,
                    RadiusNorm = rNorm,
                    AngularSpeed = (0.18 + rng.NextDouble() * 0.30) * (i % 4 == 0 ? -1.0 : 1.0),
                    RadialPhase = rng.NextDouble() * Math.PI * 2.0,
                    Size = 5.5 + rng.NextDouble() * 9.5,
                    TwinklePhase = rng.NextDouble() * Math.PI * 2.0,
                    IsDiamondStar = (i % 4 == 0)
                };
            }

            for (int i = 0; i < BokehCount; i++)
            {
                _bokeh[i] = new BokehOrb
                {
                    Angle = rng.NextDouble() * Math.PI * 2.0,
                    RadiusNorm = 0.26 + rng.NextDouble() * 0.64,
                    Speed = (0.04 + rng.NextDouble() * 0.08) * (i % 2 == 0 ? 1.0 : -1.0),
                    Size = 26.0 + rng.NextDouble() * 42.0,
                    Phase = rng.NextDouble() * Math.PI * 2.0
                };
            }
        }

        public void SetRunning(bool active)
        {
            if (active && !_running)
            {
                _running = true;
                _clock.Restart();
                _lastSec = 0;
                _lastRenderingTime = TimeSpan.MinValue;
                _fpsWindowStartSec = 0;
                _fpsFrameCounter = 0;
                _renderMsAccum = 0;
                _renderMsMax = 0;
                try { _lastProcCpuTime = Environment.CpuUsage.TotalTime; } catch { }

                CompositionTarget.Rendering += OnVSyncFrame;
                _frameHeartbeatTimer.Start();
                InvalidateVisual();
            }
            else if (!active && _running)
            {
                _running = false;
                _frameHeartbeatTimer.Stop();
                CompositionTarget.Rendering -= OnVSyncFrame;
                _clock.Stop();
            }
        }

        public void SetFocusMode(bool active)
        {
            _focusModeActive = active;
            _portalScale = active ? 0.92 : 1.0;
            InvalidateVisual();
        }

        public double GetPortalCenterOffsetY(double height) => height * (_focusModeActive ? 0.065 : 0.04);

        public void TriggerIncomingOrderSurge(double durationSeconds = 5.0)
        {
            _surgeUntilUtc = DateTime.UtcNow.AddSeconds(durationSeconds);
            _sheenCycleSec = 0.0; // Trigger immediate smooth sapphire mirror reflection on order arrival
            for (int i = 0; i < _shockwaves.Length; i++)
            {
                if (!_shockwaves[i].Active)
                {
                    _shockwaves[i] = new ShockwaveRing { RadiusNorm = 0.19, Alpha = 0.95, Active = true };
                    break;
                }
            }
        }

        private void OnVSyncFrame(object? sender, EventArgs e)
        {
            if (e is RenderingEventArgs rea)
            {
                if (rea.RenderingTime == _lastRenderingTime) return;
                _lastRenderingTime = rea.RenderingTime;
            }

            double now = _clock.Elapsed.TotalSeconds;
            double rawDt = now - _lastSec;
            if (rawDt < 0.004) return;
            double dt = rawDt > 0.05 ? 0.016667 : rawDt;
            _lastSec = now;

            bool surging = DateTime.UtcNow < _surgeUntilUtc;
            if (surging)
            {
                double atk = 1.0 - Math.Exp(-7.5 * dt);
                _currentGreenBlend = Math.Clamp(_currentGreenBlend + (1.0 - _currentGreenBlend) * atk, 0.0, 1.0);
                _currentSpeedMultiplier = Math.Clamp(_currentSpeedMultiplier + (4.4 - _currentSpeedMultiplier) * atk, 1.0, 4.4);
            }
            else
            {
                double rel = 1.0 - Math.Exp(-0.88 * dt);
                _currentGreenBlend = Math.Clamp(_currentGreenBlend + (0.0 - _currentGreenBlend) * rel, 0.0, 1.0);
                _currentSpeedMultiplier = Math.Clamp(_currentSpeedMultiplier + (1.0 - _currentSpeedMultiplier) * rel, 1.0, 4.4);
                if (_currentGreenBlend < 0.004) _currentGreenBlend = 0.0;
            }

            _outerRingDeg = (_outerRingDeg + 8.5 * _currentSpeedMultiplier * dt) % 360.0;
            _midRingDegA = (_midRingDegA + 18.0 * _currentSpeedMultiplier * dt) % 360.0;
            _midRingDegB = (_midRingDegB - 14.5 * _currentSpeedMultiplier * dt) % 360.0;
            _ribbonPhase += dt * 0.95 * _currentSpeedMultiplier;
            _sheenCycleSec = (_sheenCycleSec + dt) % SheenCycleIntervalSeconds;

            for (int i = 0; i < BokehCount; i++)
            {
                _bokeh[i].Angle += _bokeh[i].Speed * _currentSpeedMultiplier * dt;
                _bokeh[i].Phase += dt * 1.3;
            }

            for (int i = 0; i < SparkCount; i++)
            {
                ref var s = ref _sparks[i];
                s.Angle += s.AngularSpeed * _currentSpeedMultiplier * dt;
                s.TwinklePhase += dt * (2.5 + _currentSpeedMultiplier * 0.7);
                if (surging)
                {
                    s.RadiusNorm -= dt * 0.16 * _currentSpeedMultiplier;
                    if (s.RadiusNorm < 0.19) s.RadiusNorm = 0.86;
                }
                else
                {
                    double targetR = s.BaseRadiusNorm + Math.Sin(_ribbonPhase * 0.9 + s.RadialPhase) * 0.024;
                    s.RadiusNorm += (targetR - s.RadiusNorm) * Math.Min(1.0, dt * 2.2);
                }
            }

            for (int i = 0; i < _shockwaves.Length; i++)
            {
                if (!_shockwaves[i].Active) continue;
                _shockwaves[i].RadiusNorm += dt * 0.48;
                _shockwaves[i].Alpha = Math.Max(0.0, 1.0 - (_shockwaves[i].RadiusNorm - 0.19) / 0.72);
                if (_shockwaves[i].RadiusNorm >= 0.90 || _shockwaves[i].Alpha <= 0.02)
                    _shockwaves[i].Active = false;
            }

            BlendStateUpdated?.Invoke(_currentGreenBlend, surging);
            InvalidateVisual();

            // Live FPS & CPU Telemetry Window (1.0s)
            _fpsFrameCounter++;
            double winElapsed = now - _fpsWindowStartSec;
            if (winElapsed >= 1.0)
            {
                _measuredFps = _fpsFrameCounter / winElapsed;
                _measuredRenderAvgMs = _fpsFrameCounter > 0 ? (_renderMsAccum / _fpsFrameCounter) : 0.0;
                _measuredRenderMaxMs = _renderMsMax;

                try
                {
                    TimeSpan curCpu = Environment.CpuUsage.TotalTime;
                    double cpuDeltaSec = (curCpu - _lastProcCpuTime).TotalSeconds;
                    _lastProcCpuTime = curCpu;
                    _measuredCpuPercent = Math.Clamp((cpuDeltaSec / (winElapsed * Math.Max(1, Environment.ProcessorCount))) * 100.0, 0.0, 100.0);
                }
                catch { }

                _fpsFrameCounter = 0;
                _fpsWindowStartSec = now;
                _renderMsAccum = 0;
                _renderMsMax = 0;

                FpsMetricsUpdated?.Invoke(_measuredFps, _measuredRenderAvgMs, _measuredCpuPercent);
            }
        }

        protected override void OnRender(DrawingContext dc)
        {
            long t0 = Stopwatch.GetTimestamp();

            double w = ActualWidth;
            double h = ActualHeight;
            if (w <= 20 || h <= 20) return;

            double cx = w * 0.5;
            double cy = h * 0.5 + GetPortalCenterOffsetY(h);
            double minDim = Math.Min(w, h) * _portalScale;
            double maxOrbitR = minDim * 0.45;
            double orbR = minDim * 0.168;

            int pal = _currentGreenBlend >= 0.5 ? 1 : 0;
            bool isBlending = _currentGreenBlend > 0.02 && _currentGreenBlend < 0.98;

            // 1. Deep Atmospheric Outer Aura & Inner Corona (1 GPU Sprite DrawImage!)
            double breath = 1.0 + 0.035 * Math.Sin(_ribbonPhase * 1.4) + _currentGreenBlend * 0.08;
            double auraRadius = maxOrbitR * 1.20 * breath;
            Rect auraRect = new(cx - auraRadius, cy - auraRadius, auraRadius * 2.0, auraRadius * 2.0);
            if (!isBlending)
            {
                dc.DrawImage(s_backdropAuraTex[pal], auraRect);
            }
            else
            {
                dc.DrawImage(s_backdropAuraTex[0], auraRect);
                dc.PushOpacity(_currentGreenBlend);
                dc.DrawImage(s_backdropAuraTex[1], auraRect);
                dc.Pop();
            }

            // 2. 18 Deep Bokeh Orbs (Pure bare DrawImage calls, 0 PushOpacity!)
            for (int i = 0; i < BokehCount; i++)
            {
                ref readonly var bk = ref _bokeh[i];
                double r = bk.RadiusNorm * maxOrbitR;
                double bx = cx + Math.Cos(bk.Angle) * r;
                double by = cy + Math.Sin(bk.Angle) * r;
                double tw = 0.45 + 0.55 * (0.5 + 0.5 * Math.Sin(bk.Phase));
                int aIdx = Math.Clamp((int)(tw * (BokehAlphaLevels - 1)), 0, BokehAlphaLevels - 1);
                double sz = bk.Size * _portalScale;
                dc.DrawImage(s_bokehOrbTex[pal, aIdx], new Rect(bx - sz, by - sz, sz * 2.0, sz * 2.0));
            }

            // 3. Three Flowing Liquid-Gold / Emerald Silk Light Ribbons & Counter-Rotating Filigree Rings (3 GPU Sprite DrawImage calls!)
            double ringQuadRadius = orbR * 1.87; // Matches 264 / 320 ratio in 640x640 texture so ring sits at orbR * 1.54
            Rect ringRect = new(cx - ringQuadRadius, cy - ringQuadRadius, ringQuadRadius * 2.0, ringQuadRadius * 2.0);

            _midRotTransformA.Angle = _midRingDegA;
            _midRotTransformA.CenterX = cx;
            _midRotTransformA.CenterY = cy;
            dc.PushTransform(_midRotTransformA);
            dc.DrawImage(s_midSilkRibbonTexA[pal], ringRect);
            dc.Pop();

            _midRotTransformB.Angle = _midRingDegB;
            _midRotTransformB.CenterX = cx;
            _midRotTransformB.CenterY = cy;
            dc.PushTransform(_midRotTransformB);
            dc.DrawImage(s_midSilkRibbonTexB[pal], ringRect);
            dc.Pop();

            _outerRotTransform.Angle = _outerRingDeg;
            _outerRotTransform.CenterX = cx;
            _outerRotTransform.CenterY = cy;
            dc.PushTransform(_outerRotTransform);
            dc.DrawImage(s_outerFiligreeRingTex[pal], ringRect);
            dc.Pop();

            // 4. Shockwave Rings on Order Arrival
            for (int i = 0; i < _shockwaves.Length; i++)
            {
                if (!_shockwaves[i].Active) continue;
                int aIdx = Math.Clamp((int)(_shockwaves[i].Alpha * (ShockwaveAlphaLevels - 1)), 0, ShockwaveAlphaLevels - 1);
                double swR = _shockwaves[i].RadiusNorm * maxOrbitR * 1.06;
                dc.DrawImage(s_shockwaveRingTex[pal, aIdx], new Rect(cx - swR, cy - swR, swR * 2.0, swR * 2.0));
            }

            // 5. 88 Swirling "Magie aktivieren" Seelenfunken with Optical Silk Trails & 4-Point Diamond Stars (Pure bare DrawImage calls!)
            double trailLag = 0.065 + (_currentSpeedMultiplier - 1.0) * 0.035;
            for (int i = 0; i < SparkCount; i++)
            {
                ref readonly var s = ref _sparks[i];
                double r = s.RadiusNorm * maxOrbitR;
                double px = cx + Math.Cos(s.Angle) * r;
                double py = cy + Math.Sin(s.Angle) * r;

                double edgeFade = Math.Sin(Math.Clamp((s.RadiusNorm - 0.16) / 0.74, 0.0, 1.0) * Math.PI);
                double tw = edgeFade * (0.42 + 0.58 * (0.5 + 0.5 * Math.Sin(s.TwinklePhase)));
                int aIdx = Math.Clamp((int)(tw * (SparkAlphaLevels - 1)), 0, SparkAlphaLevels - 1);
                if (aIdx == 0) continue;

                double sz = s.Size * _portalScale * (1.0 + _currentGreenBlend * 0.25);

                // Soft optical silk afterglow trail sprite (0 vector line tessellation!)
                int trailAIdx = aIdx >> 1;
                if (trailAIdx > 0)
                {
                    double tailAng = s.Angle - s.AngularSpeed * trailLag;
                    double tx = cx + Math.Cos(tailAng) * r;
                    double ty = cy + Math.Sin(tailAng) * r;
                    double tSz = sz * 0.72;
                    dc.DrawImage(s_sparkGlowTex[pal, trailAIdx], new Rect(tx - tSz, ty - tSz, tSz * 2.0, tSz * 2.0));
                }

                if (s.IsDiamondStar)
                {
                    double starSz = sz * 1.65;
                    dc.DrawImage(s_diamondStarTex[pal, aIdx], new Rect(px - starSz, py - starSz, starSz * 2.0, starSz * 2.0));
                }
                else
                {
                    dc.DrawImage(s_sparkGlowTex[pal, aIdx], new Rect(px - sz, py - sz, sz * 2.0, sz * 2.0));
                }
            }

            // 6. 3D K9 Smoked Sapphire-Crystal Sphere, Rim Caustics, 24K Gold Bezel & 60-FPS Mirror Reflection Sweep (every 10s)
            double sphereQuadR = orbR * 1.084; // Matches 236 / 256 in 512x512 texture
            Rect sphereRect = new(cx - sphereQuadR, cy - sphereQuadR, sphereQuadR * 2.0, sphereQuadR * 2.0);
            if (!isBlending)
            {
                dc.DrawImage(s_crystalSphereTex[pal], sphereRect);
            }
            else
            {
                dc.DrawImage(s_crystalSphereTex[0], sphereRect);
                dc.PushOpacity(_currentGreenBlend);
                dc.DrawImage(s_crystalSphereTex[1], sphereRect);
                dc.Pop();
            }

            if (_sheenCycleSec < SheenSweepDurationSeconds)
            {
                double t = _sheenCycleSec / SheenSweepDurationSeconds;
                double ease = 0.5 - 0.5 * Math.Cos(t * Math.PI);
                double prog = t * 0.32 + ease * 0.68;
                _sheenTranslateTransform.X = (prog - 0.5) * 1.48;
                _sheenTranslateTransform.Y = (prog - 0.5) * 0.82;

                var sheenBrush = _liveSheenBrush[pal];
                sheenBrush.Opacity = Math.Sin(t * Math.PI);
                double sheenRadius = orbR * 0.94;
                dc.DrawEllipse(sheenBrush, null, new Point(cx, cy), sheenRadius, sheenRadius);
            }

            long t1 = Stopwatch.GetTimestamp();
            double elapsedMs = (t1 - t0) * 1000.0 / Stopwatch.Frequency;
            _renderMsAccum += elapsedMs;
            if (elapsedMs > _renderMsMax) _renderMsMax = elapsedMs;
        }
    }

    /// <summary>
    /// Dedicated Fullscreen-Capable Funkenwerk OS Flagship App: "Monitor Gewerbebereich"
    /// Renders an ultra-luxury, hardware-accelerated DirectX GPU-Sprite 3D Crystal-Glass, Diamond-Caustics
    /// & 24K Liquid-Gold Showcase at a locked 60.0 FPS with < 3% CPU load on Intel Alder Lake-N.
    /// </summary>
    public sealed class GewerbeMonitorWindow : Window
    {
        private static readonly Brush s_goldTitleBrush;
        private static readonly Brush s_emeraldTitleBrush;
        private static readonly Brush s_goldSubBrush;
        private static readonly Brush s_emeraldSubBrush;
        private static readonly Brush s_goldBorderBrush;
        private static readonly Brush s_emeraldBorderBrush;

        static GewerbeMonitorWindow()
        {
            var goldGrad = new LinearGradientBrush { StartPoint = new Point(0, 0), EndPoint = new Point(1, 0.3) };
            goldGrad.GradientStops.Add(new GradientStop(Color.FromRgb(255, 248, 224), 0.0));
            goldGrad.GradientStops.Add(new GradientStop(Color.FromRgb(253, 230, 138), 0.28));
            goldGrad.GradientStops.Add(new GradientStop(Color.FromRgb(197, 160, 89), 0.55));
            goldGrad.GradientStops.Add(new GradientStop(Color.FromRgb(255, 250, 230), 0.80));
            goldGrad.GradientStops.Add(new GradientStop(Color.FromRgb(197, 160, 89), 1.0));
            goldGrad.Freeze();
            s_goldTitleBrush = goldGrad;

            var emGrad = new LinearGradientBrush { StartPoint = new Point(0, 0), EndPoint = new Point(1, 0.3) };
            emGrad.GradientStops.Add(new GradientStop(Color.FromRgb(255, 255, 255), 0.0));
            emGrad.GradientStops.Add(new GradientStop(Color.FromRgb(209, 250, 229), 0.28));
            emGrad.GradientStops.Add(new GradientStop(Color.FromRgb(52, 211, 153), 0.55));
            emGrad.GradientStops.Add(new GradientStop(Color.FromRgb(167, 243, 208), 0.80));
            emGrad.GradientStops.Add(new GradientStop(Color.FromRgb(16, 185, 129), 1.0));
            emGrad.Freeze();
            s_emeraldTitleBrush = emGrad;

            var gSub = new SolidColorBrush(Color.FromRgb(253, 230, 138));
            gSub.Freeze();
            s_goldSubBrush = gSub;

            var eSub = new SolidColorBrush(Color.FromRgb(167, 243, 208));
            eSub.Freeze();
            s_emeraldSubBrush = eSub;

            var gBorder = new SolidColorBrush(Color.FromArgb(120, 197, 160, 89));
            gBorder.Freeze();
            s_goldBorderBrush = gBorder;

            var eBorder = new SolidColorBrush(Color.FromArgb(185, 16, 185, 129));
            eBorder.Freeze();
            s_emeraldBorderBrush = eBorder;
        }

        private readonly OsSettings _settings;
        private readonly Grid _stageGrid;
        private readonly GewerbeMagicPortalRenderer _portalRenderer;
        private readonly StackPanel _headerStack;
        private readonly System.Windows.Controls.Image? _headerLogoImg;
        private readonly TextBlock _headerOverlineTxt;
        private readonly TextBlock _brandTitleTxt;
        private readonly TextBlock _headerSubtitleTxt;
        private readonly StackPanel _centerStack;
        private readonly ScaleTransform _centerScaleTransform;
        private readonly TranslateTransform _centerTranslateTransform;
        private readonly TextBlock _centerStarTxt;
        private readonly TextBlock _orderCountTxt;
        private readonly TextBlock _orderSubLabelTxt;
        private readonly TextBlock _openCartsTxt;
        private readonly Border _topCustomersBorder;
        private readonly TextBlock _topCustomersTxt;
        private readonly Border _statusBorder;
        private readonly TextBlock _statusTxt;
        private readonly TextBlock _clockAndFpsTxt;
        private readonly StackPanel _topBar;
        private sealed class EnvOrderTrackState
        {
            public bool Initialized;
            public int LastOpenCount = -1;
            public int LastTotalCount = -1;
            public string LastLatestOrderKey = "";
            public HashSet<int> KnownOrderIds = new();
            public HashSet<string> KnownOrderKeys = new(StringComparer.OrdinalIgnoreCase);
        }

        private readonly StackPanel _envSwitchPanel;
        private readonly Button _envLocalBtn;
        private readonly Button _envStageBtn;
        private readonly Button _envLiveBtn;
        private readonly Button _btPairBtn;
        private readonly Button _testBtn;
        private readonly Button _focusBtn;
        private readonly DispatcherTimer _fastControlTimer;
        private System.Threading.CancellationTokenSource? _bgWorkerCts;
        private readonly Dictionary<string, EnvOrderTrackState> _envTrackStates = new(StringComparer.OrdinalIgnoreCase);
        private readonly object _trackLock = new();
        private string _activeDisplayEnv = "live";

        private bool _isFocusMode;
        private DateTime _lastMouseMoveUtc = DateTime.UtcNow;
        private bool _lastSurgingState;
        private int _currentOrderCount = -1;
        private int _currentCartCount = -1;
        private string _lastTopCustomersFormatted = "";
        private double _latestFps = 60.0;
        private double _latestRenderMs = 0.2;
        private double _latestCpuPct = 1.0;

        public GewerbeMonitorWindow(OsSettings settings)
        {
            _settings = settings;
            Title = "Mein Seelenfunke · Monitor Gewerbebereich";
            WindowStyle = WindowStyle.None;
            ResizeMode = ResizeMode.NoResize;
            Topmost = false;
            WindowState = WindowState.Normal;
            Left = 0;
            Top = 0;
            Width = SystemParameters.PrimaryScreenWidth;
            Height = SystemParameters.PrimaryScreenHeight;

            var bgBrush = new SolidColorBrush(Color.FromRgb(3, 4, 7));
            bgBrush.Freeze();
            Background = bgBrush;

            OsHardwareAndAudioEngine.KeepDisplayAndSystemAwake();
            PrewarmOrderArrivalChimeInBackground();

            _stageGrid = new Grid
            {
                ClipToBounds = true,
                Background = bgBrush
            };

            _portalRenderer = new GewerbeMagicPortalRenderer
            {
                HorizontalAlignment = HorizontalAlignment.Stretch,
                VerticalAlignment = VerticalAlignment.Stretch
            };
            _stageGrid.Children.Add(_portalRenderer);

            // Monumental Brand Header
            _headerStack = new StackPanel
            {
                HorizontalAlignment = HorizontalAlignment.Center,
                VerticalAlignment = VerticalAlignment.Top,
                Margin = new Thickness(0, 26, 0, 0),
                IsHitTestVisible = false
            };

            try
            {
                string logoFile = Path.Combine(AppContext.BaseDirectory, "mein-seelenfunke-logo.png");
                if (File.Exists(logoFile))
                {
                    var bi = new BitmapImage();
                    bi.BeginInit();
                    bi.CacheOption = BitmapCacheOption.OnLoad;
                    bi.DecodePixelWidth = 112;
                    bi.UriSource = new Uri(logoFile, UriKind.Absolute);
                    bi.EndInit();
                    bi.Freeze();
                    _headerLogoImg = new System.Windows.Controls.Image
                    {
                        Source = bi,
                        Width = 54,
                        Height = 54,
                        HorizontalAlignment = HorizontalAlignment.Center,
                        Margin = new Thickness(0, 0, 0, 6)
                    };
                    _headerStack.Children.Add(_headerLogoImg);
                }
            }
            catch { }

            var overlineBrush = new SolidColorBrush(Color.FromArgb(215, 253, 230, 138));
            overlineBrush.Freeze();
            _headerOverlineTxt = new TextBlock
            {
                Text = "✦   EDELMANUFAKTUR · GIFHORN   ✦",
                FontSize = 12,
                FontWeight = FontWeights.SemiBold,
                Foreground = overlineBrush,
                HorizontalAlignment = HorizontalAlignment.Center,
                Margin = new Thickness(0, 0, 0, 4)
            };
            _headerStack.Children.Add(_headerOverlineTxt);

            _brandTitleTxt = new TextBlock
            {
                Text = "MEIN SEELENFUNKE",
                FontFamily = new FontFamily("Georgia"),
                FontSize = 60,
                FontWeight = FontWeights.Bold,
                Foreground = s_goldTitleBrush,
                HorizontalAlignment = HorizontalAlignment.Center
            };
            var titleViewbox = new Viewbox
            {
                Stretch = Stretch.Uniform,
                StretchDirection = StretchDirection.DownOnly,
                HorizontalAlignment = HorizontalAlignment.Center,
                Margin = new Thickness(32, 0, 32, 0),
                Child = _brandTitleTxt
            };
            _headerStack.Children.Add(titleViewbox);

            var subTagBrush = new SolidColorBrush(Color.FromArgb(165, 243, 232, 210));
            subTagBrush.Freeze();
            _headerSubtitleTxt = new TextBlock
            {
                Text = "KRISTALLGLAS   ·   DIAMANT   ·   EDELGOLD",
                FontSize = 12,
                FontWeight = FontWeights.SemiBold,
                Foreground = subTagBrush,
                HorizontalAlignment = HorizontalAlignment.Center,
                Margin = new Thickness(0, 6, 0, 0)
            };
            _headerStack.Children.Add(_headerSubtitleTxt);
            _stageGrid.Children.Add(_headerStack);

            // Center Order Counter inside Crystal Sphere (mathematically & optically locked to sphere center cx, cy)
            _centerScaleTransform = new ScaleTransform(1.0, 1.0);
            _centerTranslateTransform = new TranslateTransform(0.0, 0.0);
            var centerTransformGroup = new TransformGroup();
            centerTransformGroup.Children.Add(_centerScaleTransform);
            centerTransformGroup.Children.Add(_centerTranslateTransform);

            _centerStack = new StackPanel
            {
                HorizontalAlignment = HorizontalAlignment.Center,
                VerticalAlignment = VerticalAlignment.Center,
                Margin = new Thickness(0),
                RenderTransformOrigin = new Point(0.5, 0.5),
                RenderTransform = centerTransformGroup,
                IsHitTestVisible = false
            };

            _centerStarTxt = new TextBlock
            {
                Text = "✦",
                FontSize = 20,
                Foreground = s_goldTitleBrush,
                HorizontalAlignment = HorizontalAlignment.Center,
                Visibility = Visibility.Collapsed
            };
            _centerStack.Children.Add(_centerStarTxt);

            _orderCountTxt = new TextBlock
            {
                Text = "0",
                FontFamily = new FontFamily("Times New Roman"),
                FontSize = 114,
                FontWeight = FontWeights.Bold,
                Foreground = s_goldTitleBrush,
                HorizontalAlignment = HorizontalAlignment.Center,
                TextAlignment = TextAlignment.Center,
                Margin = new Thickness(0, -25, 0, -22)
            };
            _centerStack.Children.Add(_orderCountTxt);

            _orderSubLabelTxt = new TextBlock
            {
                Text = "BESTELLUNGEN",
                FontSize = 20,
                FontWeight = FontWeights.Bold,
                Foreground = s_goldSubBrush,
                HorizontalAlignment = HorizontalAlignment.Center,
                TextAlignment = TextAlignment.Center,
                Margin = new Thickness(0, 8, 0, 0)
            };
            _centerStack.Children.Add(_orderSubLabelTxt);

            _openCartsTxt = new TextBlock
            {
                Text = "WARENKÖRBE: 0",
                FontSize = 14,
                FontWeight = FontWeights.Bold,
                Foreground = s_goldTitleBrush,
                Opacity = 0.88,
                HorizontalAlignment = HorizontalAlignment.Center,
                TextAlignment = TextAlignment.Center,
                Margin = new Thickness(0, 6, 0, -6)
            };
            _centerStack.Children.Add(_openCartsTxt);
            _stageGrid.Children.Add(_centerStack);
            _stageGrid.SizeChanged += (_, _) => UpdateCenterStackExactAlignment();

            // Bottom Status Pill & Top 3 Customers (DSGVO-anonymized)
            var pillBgBrush = new SolidColorBrush(Color.FromArgb(215, 8, 11, 18));
            pillBgBrush.Freeze();

            _topCustomersBorder = new Border
            {
                HorizontalAlignment = HorizontalAlignment.Center,
                VerticalAlignment = VerticalAlignment.Bottom,
                Margin = new Thickness(0, 0, 0, 76),
                Padding = new Thickness(22, 7, 22, 7),
                CornerRadius = new CornerRadius(999),
                Background = pillBgBrush,
                BorderBrush = s_goldBorderBrush,
                BorderThickness = new Thickness(1),
                IsHitTestVisible = false
            };
            _topCustomersTxt = new TextBlock
            {
                Text = "♛   TOP KUNDEN   ·   Warte auf Bestellungen",
                FontSize = 12,
                FontWeight = FontWeights.Bold,
                Foreground = s_goldTitleBrush
            };
            _topCustomersBorder.Child = _topCustomersTxt;
            _stageGrid.Children.Add(_topCustomersBorder);

            _statusBorder = new Border
            {
                HorizontalAlignment = HorizontalAlignment.Center,
                VerticalAlignment = VerticalAlignment.Bottom,
                Margin = new Thickness(0, 0, 0, 26),
                Padding = new Thickness(24, 9, 24, 9),
                CornerRadius = new CornerRadius(999),
                Background = pillBgBrush,
                BorderBrush = s_goldBorderBrush,
                BorderThickness = new Thickness(1),
                IsHitTestVisible = false
            };
            _statusTxt = new TextBlock
            {
                Text = "✦   MAGIE AKTIV · LIVE-MANUFAKTUR",
                FontSize = 12,
                FontWeight = FontWeights.Bold,
                Foreground = s_goldSubBrush
            };
            _statusBorder.Child = _statusTxt;
            _stageGrid.Children.Add(_statusBorder);

            // Corner Clock & Live 60-FPS Telemetry
            var clockFg = new SolidColorBrush(Color.FromArgb(145, 229, 231, 235));
            clockFg.Freeze();
            _clockAndFpsTxt = new TextBlock
            {
                Text = $"{DateTime.Now:HH:mm}  ·  60 FPS",
                FontSize = 12.5,
                FontWeight = FontWeights.SemiBold,
                Foreground = clockFg,
                HorizontalAlignment = HorizontalAlignment.Left,
                VerticalAlignment = VerticalAlignment.Bottom,
                Margin = new Thickness(32, 0, 0, 28),
                IsHitTestVisible = false
            };
            _stageGrid.Children.Add(_clockAndFpsTxt);

            _topBar = new StackPanel
            {
                Orientation = Orientation.Horizontal,
                HorizontalAlignment = HorizontalAlignment.Right,
                VerticalAlignment = VerticalAlignment.Top,
                Margin = new Thickness(0, 24, 28, 0)
            };
            _btPairBtn = CreateStageButton("🔊 Teufel");
            _btPairBtn.Margin = new Thickness(0, 0, 8, 0);
            _btPairBtn.Click += async (_, _) => await PairOrConnectTeufelSpeakerAsync(userInitiated: true);
            var sleepBtn = CreateStageButton("☾ Nachtruhe (08:00)");
            sleepBtn.Margin = new Thickness(0, 0, 8, 0);
            sleepBtn.Click += (_, _) => OsHardwareAndAudioEngine.TriggerNightSleepUntilMorning0800();
            var fullBtn = CreateStageButton("⛶ Vollbild");
            fullBtn.Margin = new Thickness(0, 0, 8, 0);
            fullBtn.Click += (_, _) => ToggleFullscreenMode();
            var minBtn = CreateStageButton("—");
            minBtn.Margin = new Thickness(0, 0, 8, 0);
            minBtn.Click += (_, _) => WindowState = WindowState.Minimized;
            var closeBtn = CreateStageButton("✕");
            closeBtn.Click += (_, _) => Close();
            _topBar.Children.Add(_btPairBtn);
            _topBar.Children.Add(sleepBtn);
            _topBar.Children.Add(fullBtn);
            _topBar.Children.Add(minBtn);
            _topBar.Children.Add(closeBtn);
            _stageGrid.Children.Add(_topBar);

            var bottomRightBar = new StackPanel
            {
                Orientation = Orientation.Horizontal,
                HorizontalAlignment = HorizontalAlignment.Right,
                VerticalAlignment = VerticalAlignment.Bottom,
                Margin = new Thickness(0, 0, 28, 26)
            };

            _envSwitchPanel = new StackPanel
            {
                Orientation = Orientation.Horizontal,
                Margin = new Thickness(0, 0, 10, 0)
            };
            _envLocalBtn = CreateStageButton("LOKAL");
            _envLocalBtn.Margin = new Thickness(0, 0, 4, 0);
            _envLocalBtn.Click += (_, _) => SwitchGewerbeEnvironment("local");

            _envStageBtn = CreateStageButton("STAGE");
            _envStageBtn.Margin = new Thickness(0, 0, 4, 0);
            _envStageBtn.Click += (_, _) => SwitchGewerbeEnvironment("stage");

            _envLiveBtn = CreateStageButton("LIVE");
            _envLiveBtn.Click += (_, _) => SwitchGewerbeEnvironment("live");

            _envSwitchPanel.Children.Add(_envLocalBtn);
            _envSwitchPanel.Children.Add(_envStageBtn);
            _envSwitchPanel.Children.Add(_envLiveBtn);
            UpdateEnvSwitchButtonsUi();

            _testBtn = CreateStageButton("✦ Eingang testen");
            _testBtn.Margin = new Thickness(0, 0, 8, 0);
            _testBtn.Click += (_, _) => SimulateOrderArrivalForDemo();

            _focusBtn = CreateStageButton("◎ Fokus");
            _focusBtn.Click += (_, _) => ToggleFocusMode();

            bottomRightBar.Children.Add(_envSwitchPanel);
            bottomRightBar.Children.Add(_testBtn);
            bottomRightBar.Children.Add(_focusBtn);
            _stageGrid.Children.Add(bottomRightBar);

            MouseMove += (_, _) =>
            {
                _lastMouseMoveUtc = DateTime.UtcNow;
                if (_isFocusMode)
                    _focusBtn.Opacity = 0.85;
            };

            // ZERO-ALLOCATION state change handler: only updates TextBlocks when surging state transitions!
            _portalRenderer.BlendStateUpdated += (_, surging) =>
            {
                if (surging == _lastSurgingState) return;
                _lastSurgingState = surging;

                if (surging)
                {
                    _brandTitleTxt.Foreground = s_emeraldTitleBrush;
                    _centerStarTxt.Foreground = s_emeraldTitleBrush;
                    _orderCountTxt.Foreground = s_emeraldTitleBrush;
                    _orderSubLabelTxt.Text = "NEUE BESTELLUNG";
                    _orderSubLabelTxt.Foreground = s_emeraldSubBrush;
                    _statusTxt.Text = "✦   NEUE BESTELLUNG EINGEGANGEN · MANUFAKTUR AKTIV   ✦";
                    _statusTxt.Foreground = s_emeraldSubBrush;
                    _statusBorder.BorderBrush = s_emeraldBorderBrush;
                }
                else
                {
                    _brandTitleTxt.Foreground = s_goldTitleBrush;
                    _centerStarTxt.Foreground = s_goldTitleBrush;
                    _orderCountTxt.Foreground = s_goldTitleBrush;
                    _orderSubLabelTxt.Text = "BESTELLUNGEN";
                    _orderSubLabelTxt.Foreground = s_goldSubBrush;
                    _statusTxt.Text = "✦   MAGIE AKTIV · LIVE-MANUFAKTUR";
                    _statusTxt.Foreground = s_goldSubBrush;
                    _statusBorder.BorderBrush = s_goldBorderBrush;
                }
            };

            _portalRenderer.FpsMetricsUpdated += (fps, renderMs, cpuPct) =>
            {
                _latestFps = fps;
                _latestRenderMs = renderMs;
                _latestCpuPct = cpuPct;
                if (!_isFocusMode)
                {
                    int dispFps = Math.Clamp((int)Math.Round(fps), 1, 240);
                    string nextClockTxt = $"{DateTime.Now:HH:mm}  ·  {dispFps} FPS";
                    if (!string.Equals(_clockAndFpsTxt.Text, nextClockTxt, StringComparison.Ordinal))
                        _clockAndFpsTxt.Text = nextClockTxt;
                }
            };

            Content = _stageGrid;

            KeyDown += OnWindowKeyDown;
            MouseDoubleClick += (_, e) =>
            {
                if (e.OriginalSource is not Button)
                    ToggleFullscreenMode();
            };

            _fastControlTimer = new DispatcherTimer { Interval = TimeSpan.FromSeconds(1) };
            _fastControlTimer.Tick += (_, _) =>
            {
                if (_isFocusMode && !_focusBtn.IsMouseOver && (DateTime.UtcNow - _lastMouseMoveUtc).TotalSeconds >= 2.5)
                {
                    _focusBtn.Opacity = 0.0;
                }
            };

            Loaded += (_, _) =>
            {
                WindowStyle = WindowStyle.None;
                ResizeMode = ResizeMode.NoResize;
                Topmost = false;
                WindowState = WindowState.Maximized;
                Activate();
                Focus();

                _activeDisplayEnv = (_settings.ApiEnvironment ?? "live").Trim().ToLowerInvariant();
                UpdateCenterStackExactAlignment();
                OsHardwareAndAudioEngine.KeepDisplayAndSystemAwake();
                _portalRenderer.SetRunning(true);
                _fastControlTimer.Start();
                StartBackgroundOrderAndTelemetryWorker();
            };

            Closed += (_, _) =>
            {
                _fastControlTimer.Stop();
                StopBackgroundOrderAndTelemetryWorker();
                _portalRenderer.SetRunning(false);
            };
        }

        private void StartBackgroundOrderAndTelemetryWorker()
        {
            StopBackgroundOrderAndTelemetryWorker();
            var cts = new System.Threading.CancellationTokenSource();
            _bgWorkerCts = cts;
            _ = Task.Run(() => RunBackgroundWorkerLoopAsync(cts.Token));
        }

        private void StopBackgroundOrderAndTelemetryWorker()
        {
            try
            {
                _bgWorkerCts?.Cancel();
                _bgWorkerCts?.Dispose();
            }
            catch { }
            _bgWorkerCts = null;
        }

        private async Task RunBackgroundWorkerLoopAsync(System.Threading.CancellationToken ct)
        {
            int tickSec = 0;
            while (!ct.IsCancellationRequested)
            {
                try
                {
                    if (CheckExternalTestTriggerFlagOnBgThread())
                    {
                        _ = Dispatcher.BeginInvoke(DispatcherPriority.Normal, new Action(SimulateOrderArrivalForDemo));
                    }

                    if (CheckExternalBtPairTriggerFlagOnBgThread())
                    {
                        await PairOrConnectTeufelSpeakerAsync(userInitiated: true).ConfigureAwait(false);
                    }
                    else if (tickSec % 4 == 2)
                    {
                        var curAudio = OsHardwareAndAudioEngine.ReadAudioState();
                        _ = Dispatcher.BeginInvoke(DispatcherPriority.Background, new Action(() =>
                        {
                            _btPairBtn.Content = curAudio.IsTeufelConnected ? "🔊 Teufel ✓" : "🔊 Teufel";
                        }));
                    }

                    // Poll orders every 4 seconds (and immediately on tick 0) completely off the WPF UI thread
                    if (tickSec % 4 == 0)
                    {
                        OsHardwareAndAudioEngine.KeepDisplayAndSystemAwake();
                        string activeEnv = (_settings.ApiEnvironment ?? "live").Trim().ToLowerInvariant();
                        var snap = await SeelenfunkeLiveClient.FetchActiveEnvOrdersPulseAsync(_settings).ConfigureAwait(false);
                        EvaluateSingleEnvPulseOffUiThread(activeEnv, snap);
                    }

                    // Write FPS benchmark file once every 15 seconds on the background thread (zero UI-thread I/O)
                    if (tickSec % 15 == 2)
                    {
                        WriteFpsBenchmarkReportSync(_latestFps, _latestRenderMs, _latestCpuPct, _lastSurgingState);
                    }
                }
                catch { }

                tickSec++;
                try
                {
                    await Task.Delay(1000, ct).ConfigureAwait(false);
                }
                catch
                {
                    break;
                }
            }
        }

        private static bool CheckExternalTestTriggerFlagOnBgThread()
        {
            try
            {
                string dir = OsVaultAndStorage.OsDataDir;
                string flagFile = Path.Combine(dir, "trigger_test_order.flag");
                if (File.Exists(flagFile))
                {
                    File.Delete(flagFile);
                    return true;
                }
            }
            catch { }
            return false;
        }

        private static bool CheckExternalBtPairTriggerFlagOnBgThread()
        {
            try
            {
                string dir = OsVaultAndStorage.OsDataDir;
                string flagFile = Path.Combine(dir, "trigger_bt_pair.flag");
                if (File.Exists(flagFile))
                {
                    File.Delete(flagFile);
                    return true;
                }
            }
            catch { }
            return false;
        }

        private async Task PairOrConnectTeufelSpeakerAsync(bool userInitiated)
        {
            try
            {
                if (!userInitiated)
                    return;

                var audio = OsHardwareAndAudioEngine.ReadAudioState();
                var list = await OsHardwareAndAudioEngine.ScanBluetoothDevicesAsync(issueHardwareInquiry: false).ConfigureAwait(false);
                var target = list.FirstOrDefault(b => b.IsTeufel)
                          ?? new BluetoothDeviceDto { Name = "Teufel KB 43 CR 19", BluetoothAddress = 0xFC58FAC7F41DUL, IsPaired = true };

                if (audio.IsTeufelConnected)
                {
                    _ = Dispatcher.BeginInvoke(DispatcherPriority.Background, new Action(() =>
                    {
                        _btPairBtn.Content = "🔊 Trenne...";
                    }));

                    await OsHardwareAndAudioEngine.DisconnectBluetoothDeviceAsync(target).ConfigureAwait(false);
                    var postDiscAudio = OsHardwareAndAudioEngine.ReadAudioState();

                    _ = Dispatcher.BeginInvoke(DispatcherPriority.Background, new Action(() =>
                    {
                        _btPairBtn.Content = postDiscAudio.IsTeufelConnected ? "🔊 Teufel ✓" : "🔊 Teufel";
                    }));
                    return;
                }

                _ = Dispatcher.BeginInvoke(DispatcherPriority.Background, new Action(() =>
                {
                    _btPairBtn.Content = "🔊 Verbinde...";
                }));

                var (ok, msg) = await OsHardwareAndAudioEngine.ConnectOrPairBluetoothDeviceAsync(target).ConfigureAwait(false);
                var postAudio = OsHardwareAndAudioEngine.ReadAudioState();

                try
                {
                    string dir = OsVaultAndStorage.OsDataDir;
                    File.WriteAllText(Path.Combine(dir, "bt_pair_status.log"),
                        $"TIME={DateTime.Now:HH:mm:ss} | OK={ok} | MSG={msg} | ACTIVE_OUT={postAudio.DeviceName} | TEUFEL_CONN={postAudio.IsTeufelConnected}");
                }
                catch { }

                _ = Dispatcher.BeginInvoke(DispatcherPriority.Background, new Action(() =>
                {
                    _btPairBtn.Content = postAudio.IsTeufelConnected ? "🔊 Teufel ✓" : "🔊 Teufel";
                    if (userInitiated && postAudio.IsTeufelConnected)
                    {
                        PlayMatteOrderArrivalSound();
                    }
                }));
            }
            catch { }
        }

        private static void WriteFpsBenchmarkReportSync(double fps, double renderMs, double cpuPct, bool isSurging)
        {
            try
            {
                string dir = OsVaultAndStorage.OsDataDir;
                Directory.CreateDirectory(dir);
                string path = Path.Combine(dir, "gewerbe_fps_benchmark.txt");
                string line = string.Format(
                    CultureInfo.InvariantCulture,
                    "TIMESTAMP={0:yyyy-MM-dd HH:mm:ss} | FPS={1:F1} | RENDER_CPU_MS={2:F3} | PROCESS_CPU_PCT={3:F1}% | MODE={4}",
                    DateTime.Now,
                    fps,
                    renderMs,
                    cpuPct,
                    isSurging ? "EMERALD_SURGE_5S" : "GOLD_SHOWCASE");
                File.WriteAllText(path, line + Environment.NewLine);
            }
            catch { }
        }

        private static Button CreateStageButton(string text)
        {
            var fg = new SolidColorBrush(Color.FromRgb(253, 230, 138));
            fg.Freeze();
            var bg = new SolidColorBrush(Color.FromArgb(200, 10, 13, 22));
            bg.Freeze();
            return new Button
            {
                Content = text,
                Cursor = Cursors.Hand,
                Padding = new Thickness(12, 6, 12, 6),
                FontSize = 11.5,
                FontWeight = FontWeights.SemiBold,
                Foreground = fg,
                Background = bg,
                BorderBrush = s_goldBorderBrush,
                BorderThickness = new Thickness(1)
            };
        }

        private static readonly Brush s_envActiveBgBrush = CreateFrozenSolidBrush(235, 197, 160, 89);
        private static readonly Brush s_envActiveFgBrush = CreateFrozenSolidBrush(255, 10, 13, 22);
        private static readonly Brush s_envInactiveBgBrush = CreateFrozenSolidBrush(200, 10, 13, 22);
        private static readonly Brush s_envInactiveFgBrush = CreateFrozenSolidBrush(185, 253, 230, 138);

        private static SolidColorBrush CreateFrozenSolidBrush(byte a, byte r, byte g, byte b)
        {
            var br = new SolidColorBrush(Color.FromArgb(a, r, g, b));
            br.Freeze();
            return br;
        }

        private void SwitchGewerbeEnvironment(string env)
        {
            string normEnv = (env ?? "live").Trim().ToLowerInvariant();
            _settings.ApiEnvironment = normEnv;
            _activeDisplayEnv = normEnv;
            UpdateEnvSwitchButtonsUi();

            _currentOrderCount = 0;
            _currentCartCount = 0;
            _lastTopCustomersFormatted = "";
            _orderCountTxt.Text = "0";
            _openCartsTxt.Text = "WARENKÖRBE: 0";
            _topCustomersTxt.Text = "♛   TOP KUNDEN   ·   Lade...";

            _ = Task.Run(() =>
            {
                try { OsVaultAndStorage.SaveSettings(_settings); } catch { }
            });
            _ = PollLiveOrdersAsync();
        }

        private void UpdateEnvSwitchButtonsUi()
        {
            string active = (_settings.ApiEnvironment ?? "live").Trim().ToLowerInvariant();
            ApplyEnvBtnVisual(_envLocalBtn, "LOKAL", active == "local");
            ApplyEnvBtnVisual(_envStageBtn, "STAGE", active == "stage");
            ApplyEnvBtnVisual(_envLiveBtn, "LIVE", active == "live");
        }

        private static void ApplyEnvBtnVisual(Button btn, string label, bool isActive)
        {
            if (btn == null) return;
            btn.Content = isActive ? $"● {label}" : label;
            btn.Background = isActive ? s_envActiveBgBrush : s_envInactiveBgBrush;
            btn.Foreground = isActive ? s_envActiveFgBrush : s_envInactiveFgBrush;
            btn.FontWeight = isActive ? FontWeights.Bold : FontWeights.SemiBold;
        }

        private void ToggleFocusMode() => SetFocusModeState(!_isFocusMode);

        public void SetFocusModeState(bool active)
        {
            _isFocusMode = active;
            _portalRenderer.SetFocusMode(active);

            Visibility extraVis = active ? Visibility.Collapsed : Visibility.Visible;
            if (_headerLogoImg != null)
                _headerLogoImg.Visibility = extraVis;
            _headerOverlineTxt.Visibility = extraVis;
            _headerSubtitleTxt.Visibility = extraVis;
            _statusBorder.Visibility = extraVis;
            _clockAndFpsTxt.Visibility = extraVis;
            _topBar.Visibility = extraVis;
            _envSwitchPanel.Visibility = extraVis;
            _testBtn.Visibility = extraVis;

            _topCustomersBorder.Margin = active ? new Thickness(0, 0, 0, 26) : new Thickness(0, 0, 0, 76);

            // In Focus Mode: monumental "MEIN SEELENFUNKE" title (132 pt) and large centered animation below it
            _brandTitleTxt.FontSize = active ? 132 : 60;
            _headerStack.Margin = active ? new Thickness(0, 22, 0, 0) : new Thickness(0, 26, 0, 0);

            UpdateCenterStackExactAlignment();

            _focusBtn.Content = active ? "◎ Fokus aktiv" : "◎ Fokus";
            _focusBtn.Opacity = active ? 0.0 : 1.0;
        }

        private void UpdateCenterStackExactAlignment()
        {
            double scale = _isFocusMode ? 0.92 : 1.0;
            _centerScaleTransform.ScaleX = scale;
            _centerScaleTransform.ScaleY = scale;

            double h = _stageGrid.ActualHeight;
            if (h <= 20) h = ActualHeight;
            if (h <= 20) h = SystemParameters.PrimaryScreenHeight;
            _centerTranslateTransform.Y = _portalRenderer.GetPortalCenterOffsetY(h);
        }

        private void ToggleFullscreenMode()
        {
            if (WindowStyle == WindowStyle.None && WindowState == WindowState.Maximized)
            {
                Topmost = false;
                WindowState = WindowState.Normal;
                WindowStyle = WindowStyle.SingleBorderWindow;
                ResizeMode = ResizeMode.CanResize;
                Width = 1520;
                Height = 900;
                WindowStartupLocation = WindowStartupLocation.CenterScreen;
            }
            else
            {
                WindowState = WindowState.Normal;
                WindowStyle = WindowStyle.None;
                ResizeMode = ResizeMode.NoResize;
                Topmost = true;
                WindowState = WindowState.Maximized;
                Activate();
                Focus();
            }
        }

        private void OnWindowKeyDown(object sender, KeyEventArgs e)
        {
            if (e.Key == Key.F)
            {
                e.Handled = true;
                ToggleFocusMode();
            }
            else if (e.Key == Key.F11)
            {
                e.Handled = true;
                ToggleFullscreenMode();
            }
            else if (e.Key is Key.T or Key.Space)
            {
                e.Handled = true;
                SimulateOrderArrivalForDemo();
            }
            else if (e.Key == Key.Escape)
            {
                e.Handled = true;
                if (_isFocusMode)
                {
                    SetFocusModeState(false);
                }
                else if (WindowStyle == WindowStyle.None)
                {
                    Close();
                }
            }
        }

        public async Task PollLiveOrdersAsync()
        {
            try
            {
                string activeEnv = (_settings.ApiEnvironment ?? "live").Trim().ToLowerInvariant();
                var snap = await SeelenfunkeLiveClient.FetchActiveEnvOrdersPulseAsync(_settings).ConfigureAwait(false);
                EvaluateSingleEnvPulseOffUiThread(activeEnv, snap);
            }
            catch { }
        }

        private void EvaluateSingleEnvPulseOffUiThread(string env, OsDashboardSnapshot snap)
        {
            if (snap == null || !snap.IsConnected) return;

            bool envHasNew = false;
            int finalCount = snap.OpenOrdersCount;

            lock (_trackLock)
            {
                string normEnv = (env ?? "live").Trim().ToLowerInvariant();
                _activeDisplayEnv = normEnv;

                if (!_envTrackStates.TryGetValue(normEnv, out var st))
                {
                    st = new EnvOrderTrackState();
                    _envTrackStates[normEnv] = st;
                }

                var incomingIds = new HashSet<int>();
                var incomingKeys = new HashSet<string>(StringComparer.OrdinalIgnoreCase);
                if (snap.AllPageOrderIds != null)
                {
                    foreach (int id in snap.AllPageOrderIds)
                        if (id > 0) incomingIds.Add(id);
                }
                if (snap.AllPageOrderKeys != null)
                {
                    foreach (string k in snap.AllPageOrderKeys)
                        if (!string.IsNullOrWhiteSpace(k)) incomingKeys.Add(k);
                }
                if (snap.ActiveOrders != null)
                {
                    foreach (var o in snap.ActiveOrders)
                    {
                        if (o.Id > 0) incomingIds.Add(o.Id);
                        if (!string.IsNullOrWhiteSpace(o.OrderUuid)) incomingKeys.Add(o.OrderUuid);
                    }
                }

                if (st.Initialized)
                {
                    if (snap.TotalOrdersCount > st.LastTotalCount && st.LastTotalCount >= 0)
                        envHasNew = true;
                    else if (snap.OpenOrdersCount > st.LastOpenCount && st.LastOpenCount >= 0)
                        envHasNew = true;
                    else if (!string.IsNullOrWhiteSpace(snap.LatestOrderKey) && !string.IsNullOrWhiteSpace(st.LastLatestOrderKey)
                             && string.CompareOrdinal(snap.LatestOrderKey, st.LastLatestOrderKey) > 0)
                        envHasNew = true;
                    else if (incomingKeys.Any(k => !st.KnownOrderKeys.Contains(k)))
                        envHasNew = true;
                    else if (incomingIds.Any(id => !st.KnownOrderIds.Contains(id)))
                        envHasNew = true;
                }

                st.LastOpenCount = snap.OpenOrdersCount;
                st.LastTotalCount = snap.TotalOrdersCount;
                if (!string.IsNullOrWhiteSpace(snap.LatestOrderKey)
                    && (string.IsNullOrWhiteSpace(st.LastLatestOrderKey) || string.CompareOrdinal(snap.LatestOrderKey, st.LastLatestOrderKey) > 0))
                {
                    st.LastLatestOrderKey = snap.LatestOrderKey;
                }
                foreach (int id in incomingIds)
                    st.KnownOrderIds.Add(id);
                foreach (string k in incomingKeys)
                    st.KnownOrderKeys.Add(k);
                st.Initialized = true;
            }

            int finalCarts = Math.Max(0, snap.OpenCartsCount);
            bool visitorMode = _settings.VisitorPrivacyMode;
            string topFormatted = FormatTopCustomersLine(snap.TopCustomersGdpr, visitorMode);

            bool countChanged = finalCount != _currentOrderCount;
            bool cartsChanged = finalCarts != _currentCartCount;
            bool topChanged = !string.Equals(topFormatted, _lastTopCustomersFormatted, StringComparison.Ordinal);

            if (countChanged || cartsChanged || topChanged || envHasNew)
            {
                void applyUi()
                {
                    _currentOrderCount = finalCount;
                    _currentCartCount = finalCarts;
                    _lastTopCustomersFormatted = topFormatted;

                    string nextText = finalCount.ToString(CultureInfo.InvariantCulture);
                    if (!string.Equals(_orderCountTxt.Text, nextText, StringComparison.Ordinal))
                        _orderCountTxt.Text = nextText;

                    string nextCartsText = $"WARENKÖRBE: {finalCarts}";
                    if (!string.Equals(_openCartsTxt.Text, nextCartsText, StringComparison.Ordinal))
                        _openCartsTxt.Text = nextCartsText;

                    if (!string.Equals(_topCustomersTxt.Text, topFormatted, StringComparison.Ordinal))
                        _topCustomersTxt.Text = topFormatted;

                    if (envHasNew)
                    {
                        PlayMatteOrderArrivalSound();
                        _portalRenderer.TriggerIncomingOrderSurge(5.0);
                    }
                }

                if (Dispatcher.CheckAccess())
                    applyUi();
                else
                    _ = Dispatcher.BeginInvoke(DispatcherPriority.Background, new Action(applyUi));
            }
        }

        public string DisplayedTopCustomersText => _topCustomersTxt.Text;

        public static string AnonymizeCustomerNameGdpr(string? raw)
        {
            if (string.IsNullOrWhiteSpace(raw)) return "";
            string cleaned = raw.Trim();
            int atIdx = cleaned.IndexOf('@');
            if (atIdx > 0)
                cleaned = cleaned.Substring(0, atIdx).Replace('.', ' ').Replace('_', ' ').Replace('-', ' ');

            string[] parts = cleaned.Split(new[] { ' ', '\t', '\r', '\n' }, StringSplitOptions.RemoveEmptyEntries);
            if (parts.Length == 0) return "";

            string first = parts[0].Trim('.');
            if (string.IsNullOrEmpty(first)) return "";
            if (first.Length > 18) first = first.Substring(0, 18);
            if (char.IsLower(first[0]))
                first = char.ToUpperInvariant(first[0]) + (first.Length > 1 ? first.Substring(1) : "");

            if (parts.Length == 1) return first;

            string last = parts[parts.Length - 1].Trim('.');
            if (string.IsNullOrEmpty(last)) return first;
            return $"{first} {char.ToUpperInvariant(last[0])}.";
        }

        public static string FormatTopCustomersLine(IReadOnlyList<string>? rawCustomers, bool visitorPrivacyMode)
        {
            if (visitorPrivacyMode)
                return "♛   TOP KUNDEN   ·   Gesperrt";

            if (rawCustomers != null && rawCustomers.Count > 0)
            {
                var parts = new List<string>(3);
                for (int i = 0; i < rawCustomers.Count && parts.Count < 3; i++)
                {
                    string safe = AnonymizeCustomerNameGdpr(rawCustomers[i]);
                    if (!string.IsNullOrWhiteSpace(safe))
                        parts.Add($"{parts.Count + 1}. {safe}");
                }

                if (parts.Count > 0)
                    return "♛   TOP KUNDEN   ·   " + string.Join("   ·   ", parts);
            }

            return "♛   TOP KUNDEN   ·   Noch keine Bestellungen";
        }

        public void UpdateFromSnapshot(OsDashboardSnapshot snap, bool? visitorPrivacyOverride = null)
        {
            if (snap == null || !snap.IsConnected) return;
            if (visitorPrivacyOverride.HasValue)
                _settings.VisitorPrivacyMode = visitorPrivacyOverride.Value;
            string env = (_settings.ApiEnvironment ?? "live").Trim().ToLowerInvariant();
            EvaluateSingleEnvPulseOffUiThread(env, snap);
        }

        public void SimulateOrderArrivalForDemo()
        {
            int nextCount = Math.Max(0, _currentOrderCount) + 1;
            _currentOrderCount = nextCount;
            _orderCountTxt.Text = nextCount.ToString(CultureInfo.InvariantCulture);
            PlayMatteOrderArrivalSound();
            _portalRenderer.TriggerIncomingOrderSurge(5.0);
        }

        public void TriggerOrderArrivalCelebration()
        {
            PlayMatteOrderArrivalSound();
            _portalRenderer.TriggerIncomingOrderSurge(5.0);
        }

        private static byte[]? s_cachedStereoChimeWavBytes;
        private static SoundPlayer? s_persistentChimePlayer;
        private static MemoryStream? s_persistentChimeStream;
        private static readonly object s_chimeLock = new();

        private static void EnsureChimeSynthesized()
        {
            lock (s_chimeLock)
            {
                if (s_cachedStereoChimeWavBytes != null) return;

                try
                {
                    string wavAssetPath = Path.Combine(AppContext.BaseDirectory, "order_ching.wav");
                    if (File.Exists(wavAssetPath))
                    {
                        byte[] assetBytes = File.ReadAllBytes(wavAssetPath);
                        if (assetBytes.Length > 1024)
                        {
                            s_cachedStereoChimeWavBytes = assetBytes;
                            s_persistentChimeStream = new MemoryStream(s_cachedStereoChimeWavBytes, writable: false);
                            s_persistentChimePlayer = new SoundPlayer(s_persistentChimeStream);
                            s_persistentChimePlayer.Load();
                            return;
                        }
                    }
                }
                catch { }

                const int sampleRate = 48000;
                const short channels = 2;
                const double durationSec = 3.20;
                int totalFrames = (int)(sampleRate * durationSec);
                int dataBytes = totalFrames * channels * 2;
                byte[] pcmBytes = new byte[44 + dataBytes];

                using (var ms = new MemoryStream(pcmBytes))
                using (var bw = new BinaryWriter(ms))
                {
                    bw.Write(new[] { 'R', 'I', 'F', 'F' });
                    bw.Write(36 + dataBytes);
                    bw.Write(new[] { 'W', 'A', 'V', 'E' });
                    bw.Write(new[] { 'f', 'm', 't', ' ' });
                    bw.Write(16);
                    bw.Write((short)1); // PCM
                    bw.Write(channels);
                    bw.Write(sampleRate);
                    bw.Write(sampleRate * channels * 2);
                    bw.Write((short)(channels * 2));
                    bw.Write((short)16);
                    bw.Write(new[] { 'd', 'a', 't', 'a' });
                    bw.Write(dataBytes);

                    // Authentic 48kHz Stereo Cash Register "Katsching!" (Cha-Ching!) Synthesizer:
                    // - 0.00s - 0.28s: Silent Bluetooth A2DP wake-up pre-roll so Teufel never clips the beginning
                    // - 0.28s - 0.40s: "KA-" (Mechanical cash-drawer spring latch pop + metallic coin-rail slide)
                    // - 0.41s - 2.65s: "-TSCHING!" (Crisp metallic clapper double-strike + bright spun-brass register bell E7/B7/E8)
                    // - 2.65s - 3.20s: Trailing silence buffer so Bluetooth A2DP never cuts off the bell tail
                    var noiseRng = new Random(7772026);
                    double lpNoise = 0.0;
                    double hpPrev = 0.0;

                    for (int i = 0; i < totalFrames; i++)
                    {
                        double t = i / (double)sampleRate;
                        double white = noiseRng.NextDouble() * 2.0 - 1.0;
                        lpNoise += 0.38 * (white - lpNoise);
                        double metallicNoise = white - hpPrev;
                        hpPrev = white * 0.72;

                        double mixL = 0.0;
                        double mixR = 0.0;

                        // 1. "KA-" (t = 0.280s .. 0.400s): Mechanical cash drawer latch click + sliding coin tray
                        if (t >= 0.280 && t < 0.410)
                        {
                            double kt = t - 0.280;
                            // Spring latch pop + wooden/metal drawer body thump
                            double latchEnv = (kt < 0.0015 ? (kt / 0.0015) : 1.0) * Math.Exp(-kt * 68.0);
                            double latchClick = (0.45 * Math.Sin(2.0 * Math.PI * 195.0 * kt)
                                               + 0.35 * Math.Sin(2.0 * Math.PI * 1180.0 * kt)
                                               + 0.28 * Math.Sin(2.0 * Math.PI * 2460.0 * kt)
                                               + 0.42 * lpNoise) * latchEnv;
                            mixL += latchClick * 0.55;
                            mixR += latchClick * 0.50;

                            // Sliding metal rail & coin clinks ("ka-ch-")
                            if (kt >= 0.028)
                            {
                                double st = kt - 0.028;
                                double slideEnv = Math.Sin(Math.Min(1.0, st / 0.085) * Math.PI) * Math.Exp(-st * 22.0);
                                double coinSlide = (0.35 * metallicNoise
                                                  + 0.28 * Math.Sin(2.0 * Math.PI * 1740.0 * st)
                                                  + 0.24 * Math.Sin(2.0 * Math.PI * 3120.0 * st)
                                                  + 0.20 * Math.Sin(2.0 * Math.PI * 4680.0 * st)) * slideEnv;
                                mixL += coinSlide * 0.38;
                                mixR += coinSlide * 0.44;
                            }
                        }

                        // 2. "-TSCH-" Clapper Grace Strike (t = 0.408s): Rapid brass clapper pre-bounce before main bell ring
                        if (t >= 0.408 && t < 0.480)
                        {
                            double gt = t - 0.408;
                            double graceAttack = gt < 0.0008 ? (gt / 0.0008) : 1.0;
                            double graceEnv = graceAttack * Math.Exp(-gt * 55.0);
                            double graceStrike = (0.48 * Math.Sin(2.0 * Math.PI * 2637.0 * gt)
                                                + 0.34 * Math.Sin(2.0 * Math.PI * 5274.0 * gt)
                                                + 0.26 * Math.Sin(2.0 * Math.PI * 7450.0 * gt)
                                                + 0.38 * metallicNoise * Math.Exp(-gt * 110.0)) * graceEnv;
                            mixL += graceStrike * 0.52;
                            mixR += graceStrike * 0.48;
                        }

                        // 3. "-ING!" Main Spun-Brass Cash Register Bell Ring (t = 0.446s .. 2.680s)
                        if (t >= 0.446 && t < 2.680)
                        {
                            double bt = t - 0.446;
                            double bellAttack = bt < 0.0007 ? (bt / 0.0007) : 1.0;
                            double endFade = t > 2.35 ? Math.Max(0.0, (2.68 - t) / 0.33) : 1.0;

                            // Crisp metallic hammer-on-brass impact ping
                            double hammerEnv = bellAttack * Math.Exp(-bt * 95.0);
                            double hammerPing = (0.35 * metallicNoise + 0.40 * Math.Sin(2.0 * Math.PI * 4186.0 * bt)) * hammerEnv;

                            // Authentic brass dome bell modes (E6 hum, E7 prime, B7 fifth, E8 octave, + inharmonic brass dome overtones)
                            double m1 = 0.14 * Math.Sin(2.0 * Math.PI * 1318.5 * bt) * Math.Exp(-bt * 2.50);
                            double m2L = 0.48 * Math.Sin(2.0 * Math.PI * 2637.0 * bt) * Math.Exp(-bt * 1.85);
                            double m2R = 0.48 * Math.Sin(2.0 * Math.PI * 2638.4 * bt) * Math.Exp(-bt * 1.85);
                            double m3L = 0.28 * Math.Sin(2.0 * Math.PI * 3951.1 * bt) * Math.Exp(-bt * 2.35);
                            double m3R = 0.28 * Math.Sin(2.0 * Math.PI * 3949.5 * bt) * Math.Exp(-bt * 2.35);
                            double m4 = 0.22 * Math.Sin(2.0 * Math.PI * 5274.0 * bt) * Math.Exp(-bt * 3.10);
                            double m5 = 0.14 * Math.Sin(2.0 * Math.PI * 6428.0 * bt) * Math.Exp(-bt * 4.60);
                            double m6 = 0.09 * Math.Sin(2.0 * Math.PI * 8195.0 * bt) * Math.Exp(-bt * 7.80);

                            // Joyful major-third upper bell sparkle (G#7 = 3322.4 Hz) entering 38ms after main strike
                            double sparkle = 0.0;
                            if (bt >= 0.038)
                            {
                                double st2 = bt - 0.038;
                                sparkle = 0.16 * Math.Sin(2.0 * Math.PI * 3322.4 * st2) * Math.Exp(-st2 * 2.20);
                            }

                            mixL += (hammerPing + m1 + m2L + m3L + m4 + m5 + m6 + sparkle) * bellAttack * endFade;
                            mixR += (hammerPing + m1 + m2R + m3R + m4 + m5 + m6 + sparkle) * bellAttack * endFade;
                        }

                        // Soft saturation limiter for loud, crystal-clear brass ring without digital clipping
                        double outL = Math.Tanh(mixL * 1.15);
                        double outR = Math.Tanh(mixR * 1.15);

                        short pcmL = (short)Math.Clamp(outL * 26500.0, short.MinValue, short.MaxValue);
                        short pcmR = (short)Math.Clamp(outR * 26500.0, short.MinValue, short.MaxValue);
                        bw.Write(pcmL); // Left
                        bw.Write(pcmR); // Right
                    }
                }

                s_cachedStereoChimeWavBytes = pcmBytes;
                s_persistentChimeStream = new MemoryStream(s_cachedStereoChimeWavBytes, writable: false);
                s_persistentChimePlayer = new SoundPlayer(s_persistentChimeStream);
                s_persistentChimePlayer.Load();
            }
        }

        private static void PrewarmOrderArrivalChimeInBackground()
        {
            _ = Task.Run(() =>
            {
                try { EnsureChimeSynthesized(); } catch { }
            });
        }

        /// <summary>
        /// Plays a warm, matte, noble felt-mallet 48kHz Stereo acoustic chime in memory.
        /// Includes a 280ms Bluetooth A2DP wake-up pre-roll and an 850ms trailing silence buffer,
        /// and uses a persistent SoundPlayer so the Bluetooth stream is NEVER cut off mid-tail!
        /// </summary>
        public static void PlayMatteOrderArrivalSound()
        {
            _ = Task.Run(() =>
            {
                try
                {
                    EnsureChimeSynthesized();
                    lock (s_chimeLock)
                    {
                        s_persistentChimePlayer?.Play();
                    }
                }
                catch { }
            });
        }
    }
}
