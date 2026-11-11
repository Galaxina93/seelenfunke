using System;
using System.Diagnostics;
using System.Windows;
using System.Windows.Media;

namespace Funkenwerk
{
    /// <summary>
    /// Zero-allocation, VSync-locked (60-240 FPS) GPU DrawingContext renderer for the
    /// Seelenfunke Music Focus Mode ("Celestial Rhythm").
    /// All Pens, Brushes, and Geometries are pre-allocated and Frozen at startup so
    /// OnRender() triggers zero GC pressure and zero WPF layout passes per frame.
    /// </summary>
    public sealed class CelestialFocusRenderer : FrameworkElement
    {
        private const int AlphaLevels = 32;
        private const int ParticleCount = 96;
        private const int SpectrumBarCount = 96;
        private const int MaxShockwaves = 10;

        private struct FocusParticle
        {
            public double Angle;
            public double RadiusNorm;
            public double AngularSpeed;
            public double RadialDrift;
            public double Size;
            public double PhaseOffset;
            public byte Layer; // 0 = foreground warm gold, 1 = deep parallax champagne
        }

        private struct ShockwaveSlot
        {
            public bool Active;
            public double Radius;
            public double MaxRadius;
            public double Opacity;
            public double SpeedPerSec;
        }

        private static readonly SolidColorBrush[] s_goldBrushes = new SolidColorBrush[AlphaLevels];
        private static readonly SolidColorBrush[] s_champagneBrushes = new SolidColorBrush[AlphaLevels];
        private static readonly Pen[] s_goldBarPens = new Pen[AlphaLevels];
        private static readonly Pen[] s_champagneBarPens = new Pen[AlphaLevels];
        private static readonly Pen[] s_shockwavePens = new Pen[AlphaLevels];
        private static readonly Pen[] s_innerRingPens = new Pen[AlphaLevels];
        private static readonly Pen s_outerAstrolabePen;
        private static readonly Pen s_midAstrolabePen;
        private static readonly Pen s_medallionBorderPen;
        private static readonly RadialGradientBrush s_nebulaAuraBrush;
        private static readonly RadialGradientBrush s_coreHaloBrush;
        private static readonly LinearGradientBrush s_medallionFillBrush;
        private static readonly SolidColorBrush s_obsidianStarBrush;
        private static readonly StreamGeometry s_fourPointStarGeometry;

        static CelestialFocusRenderer()
        {
            for (int i = 0; i < AlphaLevels; i++)
            {
                byte alpha = (byte)Math.Clamp((int)Math.Round((i / (double)(AlphaLevels - 1)) * 255.0), 0, 255);

                var goldBrush = new SolidColorBrush(Color.FromArgb(alpha, 197, 160, 89)); // #C5A059
                goldBrush.Freeze();
                s_goldBrushes[i] = goldBrush;

                var champBrush = new SolidColorBrush(Color.FromArgb(alpha, 253, 230, 138)); // #FDE68A
                champBrush.Freeze();
                s_champagneBrushes[i] = champBrush;

                var goldBarPen = new Pen(goldBrush, 2.35)
                {
                    StartLineCap = PenLineCap.Round,
                    EndLineCap = PenLineCap.Round
                };
                goldBarPen.Freeze();
                s_goldBarPens[i] = goldBarPen;

                var champBarPen = new Pen(champBrush, 2.35)
                {
                    StartLineCap = PenLineCap.Round,
                    EndLineCap = PenLineCap.Round
                };
                champBarPen.Freeze();
                s_champagneBarPens[i] = champBarPen;

                var swPen = new Pen(champBrush, 1.8);
                swPen.Freeze();
                s_shockwavePens[i] = swPen;

                var innerPen = new Pen(goldBrush, 1.25);
                innerPen.Freeze();
                s_innerRingPens[i] = innerPen;
            }

            var outerStroke = new SolidColorBrush(Color.FromArgb(145, 197, 160, 89));
            outerStroke.Freeze();
            s_outerAstrolabePen = new Pen(outerStroke, 1.2)
            {
                DashStyle = new DashStyle(new double[] { 4.0, 8.0 }, 0.0)
            };
            s_outerAstrolabePen.Freeze();

            var midStroke = new SolidColorBrush(Color.FromArgb(185, 253, 230, 138));
            midStroke.Freeze();
            s_midAstrolabePen = new Pen(midStroke, 1.5)
            {
                DashStyle = new DashStyle(new double[] { 16.0, 8.0, 3.0, 8.0 }, 0.0)
            };
            s_midAstrolabePen.Freeze();

            var medBorderBrush = new SolidColorBrush(Color.FromArgb(235, 253, 230, 138));
            medBorderBrush.Freeze();
            s_medallionBorderPen = new Pen(medBorderBrush, 2.2);
            s_medallionBorderPen.Freeze();

            s_nebulaAuraBrush = new RadialGradientBrush
            {
                Center = new Point(0.5, 0.5),
                GradientOrigin = new Point(0.5, 0.5),
                RadiusX = 0.5,
                RadiusY = 0.5,
                GradientStops = new GradientStopCollection
                {
                    new GradientStop(Color.FromArgb(165, 197, 160, 89), 0.0),
                    new GradientStop(Color.FromArgb(72, 154, 114, 41), 0.44),
                    new GradientStop(Color.FromArgb(18, 92, 66, 20), 0.75),
                    new GradientStop(Color.FromArgb(0, 0, 0, 0), 1.0)
                }
            };
            s_nebulaAuraBrush.Freeze();

            s_coreHaloBrush = new RadialGradientBrush
            {
                Center = new Point(0.5, 0.5),
                GradientOrigin = new Point(0.5, 0.5),
                RadiusX = 0.5,
                RadiusY = 0.5,
                GradientStops = new GradientStopCollection
                {
                    new GradientStop(Color.FromArgb(150, 253, 230, 138), 0.0),
                    new GradientStop(Color.FromArgb(55, 197, 160, 89), 0.55),
                    new GradientStop(Color.FromArgb(0, 0, 0, 0), 1.0)
                }
            };
            s_coreHaloBrush.Freeze();

            s_medallionFillBrush = new LinearGradientBrush
            {
                StartPoint = new Point(0, 0),
                EndPoint = new Point(1, 1),
                GradientStops = new GradientStopCollection
                {
                    new GradientStop(Color.FromRgb(253, 230, 138), 0.0), // #FDE68A
                    new GradientStop(Color.FromRgb(197, 160, 89), 0.55), // #C5A059
                    new GradientStop(Color.FromRgb(154, 114, 41), 1.0)   // #9A7229
                }
            };
            s_medallionFillBrush.Freeze();

            s_obsidianStarBrush = new SolidColorBrush(Color.FromRgb(17, 24, 39));
            s_obsidianStarBrush.Freeze();

            // Unit-centered 4-pointed Seelenfunke star in [-1..+1] coordinate space
            var starGeo = new StreamGeometry();
            using (var ctx = starGeo.Open())
            {
                ctx.BeginFigure(new Point(0.0, -1.0), isFilled: true, isClosed: true);
                ctx.LineTo(new Point(0.26, -0.26), isStroked: false, isSmoothJoin: false);
                ctx.LineTo(new Point(1.0, 0.0), isStroked: false, isSmoothJoin: false);
                ctx.LineTo(new Point(0.26, 0.26), isStroked: false, isSmoothJoin: false);
                ctx.LineTo(new Point(0.0, 1.0), isStroked: false, isSmoothJoin: false);
                ctx.LineTo(new Point(-0.26, 0.26), isStroked: false, isSmoothJoin: false);
                ctx.LineTo(new Point(-1.0, 0.0), isStroked: false, isSmoothJoin: false);
                ctx.LineTo(new Point(-0.26, -0.26), isStroked: false, isSmoothJoin: false);
            }
            starGeo.Freeze();
            s_fourPointStarGeometry = starGeo;
        }

        public delegate (double Bass, double Kick, double Mid, double High, bool IsPlaying) AudioBandsSampleProvider();

        private readonly FocusParticle[] _particles = new FocusParticle[ParticleCount];
        private readonly ShockwaveSlot[] _shockwaves = new ShockwaveSlot[MaxShockwaves];
        private readonly double[] _barSmoothed = new double[SpectrumBarCount];
        private readonly double[] _barPeaks = new double[SpectrumBarCount];
        private readonly double[] _cosTable = new double[SpectrumBarCount];
        private readonly double[] _sinTable = new double[SpectrumBarCount];

        private readonly RotateTransform _outerRotTransform = new RotateTransform();
        private readonly RotateTransform _midRotTransform = new RotateTransform();
        private readonly MatrixTransform _starMatrixTransform = new MatrixTransform();

        private readonly Stopwatch _clock = new Stopwatch();
        private double _lastFrameSec;
        private bool _isRunning;

        private double _phase;
        private double _smoothBass = 0.08;
        private double _smoothKick;
        private double _smoothMid = 0.06;
        private double _smoothHigh = 0.04;
        private double _angleOuter;
        private double _angleMid;
        private double _angleInner;
        private double _lastShockwaveSec = -10.0;

        public AudioBandsSampleProvider? SampleProvider { get; set; }

        public CelestialFocusRenderer()
        {
            IsHitTestVisible = false;
            SnapsToDevicePixels = false;

            var rng = new Random(20261109);
            for (int i = 0; i < ParticleCount; i++)
            {
                byte layer = (byte)(i % 3 == 0 ? 1 : 0);
                _particles[i] = new FocusParticle
                {
                    Angle = rng.NextDouble() * Math.PI * 2.0,
                    RadiusNorm = 0.12 + rng.NextDouble() * 0.86,
                    AngularSpeed = (0.14 + rng.NextDouble() * 0.34) * (i % 2 == 0 ? 1.0 : -1.0) * (layer == 1 ? 0.65 : 1.0),
                    RadialDrift = (0.025 + rng.NextDouble() * 0.065) * (layer == 1 ? 0.6 : 1.0),
                    Size = layer == 1 ? (1.1 + rng.NextDouble() * 1.4) : (1.5 + rng.NextDouble() * 2.3),
                    PhaseOffset = rng.NextDouble() * Math.PI * 2.0,
                    Layer = layer
                };
            }

            for (int b = 0; b < SpectrumBarCount; b++)
            {
                double angle = (b * Math.PI * 2.0 / SpectrumBarCount) - Math.PI * 0.5;
                _cosTable[b] = Math.Cos(angle);
                _sinTable[b] = Math.Sin(angle);
            }
        }

        public void StartRendering()
        {
            if (_isRunning) return;
            _isRunning = true;
            _clock.Restart();
            _lastFrameSec = 0.0;
            CompositionTarget.Rendering += OnCompositionTargetRendering;
            InvalidateVisual();
        }

        public void StopRendering()
        {
            if (!_isRunning) return;
            _isRunning = false;
            CompositionTarget.Rendering -= OnCompositionTargetRendering;
            _clock.Stop();
            for (int i = 0; i < MaxShockwaves; i++)
            {
                _shockwaves[i].Active = false;
            }
        }

        private void OnCompositionTargetRendering(object? sender, EventArgs e)
        {
            if (!_isRunning || Visibility != Visibility.Visible) return;

            double nowSec = _clock.Elapsed.TotalSeconds;
            double dt = nowSec - _lastFrameSec;
            if (dt <= 0.0005) return; // Guard duplicate CompositionTarget ticks in same VSync
            _lastFrameSec = nowSec;
            if (dt > 0.05) dt = 0.016; // Smooth clamp across window drag/alt-tab

            StepSimulation(dt, nowSec);
            InvalidateVisual();
        }

        private void StepSimulation(double dt, double nowSec)
        {
            double rawBass = 0.08;
            double rawKick = 0.0;
            double rawMid = 0.06;
            double rawHigh = 0.04;
            bool isPlaying = false;

            if (SampleProvider != null)
            {
                var s = SampleProvider();
                rawBass = s.Bass;
                rawKick = s.Kick;
                rawMid = s.Mid;
                rawHigh = s.High;
                isPlaying = s.IsPlaying;
            }

            if (!isPlaying)
            {
                rawBass = 0.08 + 0.025 * Math.Sin(_phase * 1.1);
                rawKick = 0.0;
                rawMid = 0.06 + 0.02 * Math.Cos(_phase * 1.35);
                rawHigh = 0.04 + 0.015 * Math.Sin(_phase * 1.9);
            }

            // Frame-rate independent attack / musical release envelope followers
            double atkFast = 1.0 - Math.Exp(-28.0 * dt);
            double atkKick = 1.0 - Math.Exp(-42.0 * dt);
            double relSmooth = 1.0 - Math.Exp(-7.5 * dt);
            double relKick = 1.0 - Math.Exp(-11.0 * dt);

            _smoothBass += (rawBass - _smoothBass) * (rawBass > _smoothBass ? atkFast : relSmooth);
            _smoothKick += (rawKick - _smoothKick) * (rawKick > _smoothKick ? atkKick : relKick);
            _smoothMid += (rawMid - _smoothMid) * (rawMid > _smoothMid ? atkFast : relSmooth);
            _smoothHigh += (rawHigh - _smoothHigh) * (rawHigh > _smoothHigh ? atkFast : relSmooth);

            _phase += dt * (1.05 + _smoothMid * 2.1 + _smoothKick * 1.45);

            // Silky astrolabe rotations (degrees/sec)
            _angleOuter = (_angleOuter + dt * (10.5 + _smoothMid * 42.0 + _smoothKick * 26.0)) % 360.0;
            _angleMid = (_angleMid - dt * (14.0 + _smoothBass * 48.0)) % 360.0;
            _angleInner = (_angleInner + dt * (19.0 + _smoothHigh * 62.0)) % 360.0;

            double w = ActualWidth;
            double h = ActualHeight;
            double minDim = Math.Max(320.0, Math.Min(w, h));
            double baseMedallionR = minDim * 0.072;

            // Spawn expanding Golden Supernova Shockwave on Kick Drum hits
            if (isPlaying && _smoothKick > 0.36 && (nowSec - _lastShockwaveSec) > 0.165)
            {
                _lastShockwaveSec = nowSec;
                for (int i = 0; i < MaxShockwaves; i++)
                {
                    if (!_shockwaves[i].Active)
                    {
                        _shockwaves[i] = new ShockwaveSlot
                        {
                            Active = true,
                            Radius = baseMedallionR * 1.32,
                            MaxRadius = minDim * (0.45 + Math.Min(0.16, _smoothKick * 0.16)),
                            Opacity = Math.Clamp(0.46 + _smoothKick * 0.44, 0.35, 0.92),
                            SpeedPerSec = minDim * (0.48 + _smoothKick * 0.42)
                        };
                        break;
                    }
                }
            }

            // Update shockwaves
            for (int i = 0; i < MaxShockwaves; i++)
            {
                if (!_shockwaves[i].Active) continue;
                _shockwaves[i].Radius += _shockwaves[i].SpeedPerSec * dt;
                if (_shockwaves[i].Radius >= _shockwaves[i].MaxRadius)
                {
                    _shockwaves[i].Active = false;
                }
            }

            // Update particles
            double angularBoost = 1.0 + _smoothMid * 2.1 + _smoothKick * 2.8;
            double radialBoost = 0.45 + _smoothBass * 1.65 + _smoothKick * 2.1;
            for (int i = 0; i < ParticleCount; i++)
            {
                ref var p = ref _particles[i];
                p.Angle += p.AngularSpeed * angularBoost * dt;
                p.RadiusNorm += p.RadialDrift * radialBoost * dt;
                if (p.RadiusNorm > 1.0)
                {
                    p.RadiusNorm = 0.11 + (p.RadiusNorm - 1.0) * 0.25;
                }
            }

            // Update 96-bar harmonic spectrum with individual spring-release & floating peak dots
            double barAttack = 1.0 - Math.Exp(-32.0 * dt);
            double barRelease = 1.0 - Math.Exp(-10.5 * dt);
            double peakDecay = dt * 0.42;
            int halfBars = SpectrumBarCount / 2;

            for (int b = 0; b < SpectrumBarCount; b++)
            {
                int mirrorIdx = b <= halfBars ? b : (SpectrumBarCount - b);
                double normFreq = mirrorIdx / (double)halfBars;

                double bandVal;
                if (normFreq < 0.34)
                {
                    double t = normFreq / 0.34;
                    double smoothT = t * t * (3.0 - 2.0 * t);
                    bandVal = _smoothBass * (1.0 - smoothT) + _smoothMid * smoothT + _smoothKick * 0.46 * (1.0 - smoothT);
                }
                else if (normFreq < 0.72)
                {
                    double t = (normFreq - 0.34) / 0.38;
                    double smoothT = t * t * (3.0 - 2.0 * t);
                    bandVal = _smoothMid * (1.0 - smoothT) + _smoothHigh * smoothT;
                }
                else
                {
                    double t = (normFreq - 0.72) / 0.28;
                    bandVal = _smoothHigh * (0.90 - 0.20 * t) + _smoothMid * 0.22;
                }

                // Organic harmonic shimmer wave so adjacent bars flow like liquid gold
                double wave1 = Math.Sin(_phase * 3.8 + mirrorIdx * 0.34);
                double wave2 = Math.Cos(_phase * 2.1 - mirrorIdx * 0.19);
                double harmonicDetail = 0.74 + 0.18 * wave1 + 0.10 * wave2;
                double target = Math.Clamp(bandVal * harmonicDetail, 0.025, 1.25);

                double cur = _barSmoothed[b];
                cur += (target - cur) * (target > cur ? barAttack : barRelease);
                _barSmoothed[b] = cur;

                if (cur >= _barPeaks[b])
                    _barPeaks[b] = cur;
                else
                    _barPeaks[b] = Math.Max(cur, _barPeaks[b] - peakDecay);
            }
        }

        private static int AlphaIdx(double normalizedAlpha)
        {
            return Math.Clamp((int)(normalizedAlpha * (AlphaLevels - 1)), 0, AlphaLevels - 1);
        }

        protected override void OnRender(DrawingContext dc)
        {
            double w = ActualWidth;
            double h = ActualHeight;
            if (w <= 10.0 || h <= 10.0) return;

            double cx = w * 0.5;
            double cy = h * 0.5 - 22.0;
            var center = new Point(cx, cy);
            double minDim = Math.Min(w, h);
            double baseMedallionR = minDim * 0.072;
            double beatScale = 1.0 + _smoothKick * 0.21 + _smoothBass * 0.12;

            // 1. Deep Atelier Golden Nebula Radial Aura (Zero brush allocation via PushOpacity)
            double nebulaR = minDim * (0.40 + _smoothBass * 0.19 + _smoothKick * 0.075);
            double nebulaOpacity = Math.Clamp(0.32 + _smoothBass * 0.52 + _smoothKick * 0.22, 0.22, 1.0);
            dc.PushOpacity(nebulaOpacity);
            dc.DrawEllipse(s_nebulaAuraBrush, null, center, nebulaR, nebulaR);
            dc.Pop();

            // 2. Parallax Golden & Champagne Seelenfunken Vortex Particles
            double maxOrbitR = minDim * 0.47;
            for (int i = 0; i < ParticleCount; i++)
            {
                ref readonly var p = ref _particles[i];
                double r = p.RadiusNorm * maxOrbitR;
                double px = cx + Math.Cos(p.Angle) * r;
                double py = cy + Math.Sin(p.Angle) * r;

                double fadeEdge = Math.Sin(p.RadiusNorm * Math.PI);
                double shimmer = 0.64 + 0.36 * Math.Sin(_phase * 3.1 + p.PhaseOffset);
                double alphaNorm = Math.Clamp(fadeEdge * shimmer * (0.52 + _smoothHigh * 0.48), 0.06, 0.96);
                double radius = p.Size * (0.86 + _smoothKick * 0.48);

                var brush = p.Layer == 1
                    ? s_champagneBrushes[AlphaIdx(alphaNorm)]
                    : s_goldBrushes[AlphaIdx(alphaNorm)];
                dc.DrawEllipse(brush, null, new Point(px, py), radius, radius);
            }

            // 3. Expanding Supernova Shockwave Rings on Kick Onsets
            double startSwR = baseMedallionR * 1.32;
            for (int i = 0; i < MaxShockwaves; i++)
            {
                ref readonly var sw = ref _shockwaves[i];
                if (!sw.Active) continue;

                double progress = Math.Clamp((sw.Radius - startSwR) / Math.Max(1.0, sw.MaxRadius - startSwR), 0.0, 1.0);
                double curAlpha = sw.Opacity * (1.0 - progress) * (1.0 - progress * 0.35);
                if (curAlpha <= 0.02) continue;

                dc.DrawEllipse(null, s_shockwavePens[AlphaIdx(curAlpha)], center, sw.Radius, sw.Radius);
            }

            // 4. Precision Counter-Rotating Celestial Astrolabe Orbit Rings
            double outerRingR = baseMedallionR * (2.68 + _smoothBass * 0.17);
            double midRingR = baseMedallionR * (2.16 + _smoothMid * 0.14);
            double innerRingR = baseMedallionR * (1.42 + _smoothKick * 0.12);

            _outerRotTransform.CenterX = cx;
            _outerRotTransform.CenterY = cy;
            _outerRotTransform.Angle = _angleOuter;
            dc.PushTransform(_outerRotTransform);
            dc.DrawEllipse(null, s_outerAstrolabePen, center, outerRingR, outerRingR);
            dc.Pop();

            _midRotTransform.CenterX = cx;
            _midRotTransform.CenterY = cy;
            _midRotTransform.Angle = _angleMid;
            dc.PushTransform(_midRotTransform);
            dc.DrawEllipse(null, s_midAstrolabePen, center, midRingR, midRingR);
            dc.Pop();

            double innerAlpha = Math.Clamp(0.45 + _smoothHigh * 0.48, 0.35, 0.95);
            dc.DrawEllipse(null, s_innerRingPens[AlphaIdx(innerAlpha)], center, innerRingR, innerRingR);

            // 5. 96-Bar Symmetrical Radial Harmonic Equalizer + Floating Champagne Peak Gems
            double barStartR = innerRingR + 6.5;
            double maxBarLen = minDim * 0.142;

            for (int b = 0; b < SpectrumBarCount; b++)
            {
                double val = _barSmoothed[b];
                double barLen = Math.Max(3.2, val * maxBarLen);
                double cosA = _cosTable[b];
                double sinA = _sinTable[b];

                double x1 = cx + cosA * barStartR;
                double y1 = cy + sinA * barStartR;
                double endR = barStartR + barLen;
                double x2 = cx + cosA * endR;
                double y2 = cy + sinA * endR;

                int aIdx = AlphaIdx(Math.Clamp(0.44 + val * 0.56, 0.28, 0.98));
                var pen = (b & 1) == 0 ? s_champagneBarPens[aIdx] : s_goldBarPens[aIdx];
                dc.DrawLine(pen, new Point(x1, y1), new Point(x2, y2));

                // Subtle floating champagne peak gem on strong transients
                double peakVal = _barPeaks[b];
                if (peakVal > val + 0.035 && (b & 1) == 0)
                {
                    double peakR = barStartR + peakVal * maxBarLen + 4.0;
                    int pIdx = AlphaIdx(Math.Clamp(0.30 + peakVal * 0.55, 0.15, 0.88));
                    dc.DrawEllipse(s_champagneBrushes[pIdx], null, new Point(cx + cosA * peakR, cy + sinA * peakR), 1.45, 1.45);
                }
            }

            // 6. Central Core Warm Halo + Noble Seelenfunke Gold Medallion + Rotating 4-Pointed Star
            double medallionR = baseMedallionR * beatScale;
            double haloR = medallionR * 1.65;
            dc.PushOpacity(Math.Clamp(0.45 + _smoothKick * 0.45, 0.35, 0.95));
            dc.DrawEllipse(s_coreHaloBrush, null, center, haloR, haloR);
            dc.Pop();

            dc.DrawEllipse(s_medallionFillBrush, s_medallionBorderPen, center, medallionR, medallionR);

            double starScale = medallionR * 0.50;
            double starRad = (_angleInner * 0.35) * (Math.PI / 180.0);
            double cosS = Math.Cos(starRad) * starScale;
            double sinS = Math.Sin(starRad) * starScale;
            _starMatrixTransform.Matrix = new Matrix(cosS, sinS, -sinS, cosS, cx, cy);

            dc.PushTransform(_starMatrixTransform);
            dc.DrawGeometry(s_obsidianStarBrush, null, s_fourPointStarGeometry);
            dc.Pop();
        }
    }
}
