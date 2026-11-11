using System;
using System.Diagnostics;
using System.Windows;
using System.Windows.Media;
using Pen = System.Windows.Media.Pen;

namespace FunkenwerkOS
{
    /// <summary>
    /// Zero-allocation VSync-locked (60-240 FPS) GPU DrawingContext renderer for subtle
    /// floating Seelenfunke golden dust motes and interactive cursor wake ripples.
    /// All Brushes and Pens are pre-frozen at startup — zero managed heap allocations per frame.
    /// </summary>
    public sealed class AmbientSparksRenderer : FrameworkElement
    {
        private const int AlphaLevels = 32;
        private const int SparkCount = 32;
        private const int MaxRipples = 16;

        private struct FloatingSpark
        {
            public double XNorm;
            public double YNorm;
            public double VxNorm;
            public double VyNorm;
            public double Radius;
            public double Phase;
            public double PulseSpeed;
            public bool IsStar;
        }

        private struct CursorRipple
        {
            public bool Active;
            public double X;
            public double Y;
            public double Radius;
            public double MaxRadius;
            public double Speed;
            public double Alpha;
            public double FadeRate;
            public bool IsClick;
        }

        private static readonly SolidColorBrush[] s_goldBrushes = new SolidColorBrush[AlphaLevels];
        private static readonly SolidColorBrush[] s_champagneBrushes = new SolidColorBrush[AlphaLevels];
        private static readonly Pen[] s_starRayPens = new Pen[AlphaLevels];
        private static readonly Pen[] s_ripplePens = new Pen[AlphaLevels];
        private static readonly Pen[] s_clickRipplePens = new Pen[AlphaLevels];

        static AmbientSparksRenderer()
        {
            for (int i = 0; i < AlphaLevels; i++)
            {
                byte a = (byte)Math.Clamp((int)Math.Round((i / (double)(AlphaLevels - 1)) * 185.0), 0, 255);
                var g = new SolidColorBrush(System.Windows.Media.Color.FromArgb(a, 197, 160, 89));
                g.Freeze();
                s_goldBrushes[i] = g;

                var c = new SolidColorBrush(System.Windows.Media.Color.FromArgb(a, 253, 230, 138));
                c.Freeze();
                s_champagneBrushes[i] = c;

                var rayPen = new Pen(c, 1.05)
                {
                    StartLineCap = PenLineCap.Round,
                    EndLineCap = PenLineCap.Round
                };
                rayPen.Freeze();
                s_starRayPens[i] = rayPen;

                var p = new Pen(c, 1.15);
                p.Freeze();
                s_ripplePens[i] = p;

                var cp = new Pen(g, 1.65);
                cp.Freeze();
                s_clickRipplePens[i] = cp;
            }
        }

        private readonly FloatingSpark[] _sparks = new FloatingSpark[SparkCount];
        private readonly CursorRipple[] _ripples = new CursorRipple[MaxRipples];
        private readonly Stopwatch _clock = new();
        private double _lastSec;
        private TimeSpan _lastRenderingTime = TimeSpan.MinValue;
        private bool _running;
        private int _nextRippleIdx;

        public AmbientSparksRenderer()
        {
            IsHitTestVisible = false;
            var rng = new Random(20261109);
            for (int i = 0; i < SparkCount; i++)
            {
                _sparks[i] = new FloatingSpark
                {
                    XNorm = rng.NextDouble(),
                    YNorm = rng.NextDouble(),
                    VxNorm = (rng.NextDouble() - 0.5) * 0.011,
                    VyNorm = -0.004 - rng.NextDouble() * 0.013,
                    Radius = 1.1 + rng.NextDouble() * 2.3,
                    Phase = rng.NextDouble() * Math.PI * 2.0,
                    PulseSpeed = 0.8 + rng.NextDouble() * 1.6,
                    IsStar = (i % 7 == 0)
                };
            }

            Loaded += (_, _) => SetRunning(Visibility == Visibility.Visible);
            Unloaded += (_, _) => SetRunning(false);
        }

        public void SetRunning(bool active)
        {
            if (active && !_running)
            {
                _running = true;
                _clock.Restart();
                _lastSec = 0;
                CompositionTarget.Rendering += OnVSyncFrame;
            }
            else if (!active && _running)
            {
                _running = false;
                CompositionTarget.Rendering -= OnVSyncFrame;
                _clock.Stop();
            }
        }

        public void SpawnRipple(double x, double y, bool isClick = false)
        {
            if (!_running) return;
            _ripples[_nextRippleIdx] = new CursorRipple
            {
                Active = true,
                X = x,
                Y = y,
                Radius = isClick ? 5.0 : 3.5,
                MaxRadius = isClick ? 58.0 : 40.0,
                Speed = isClick ? 98.0 : 74.0,
                Alpha = isClick ? 0.72 : 0.45,
                FadeRate = isClick ? 1.10 : 0.92,
                IsClick = isClick
            };
            _nextRippleIdx = (_nextRippleIdx + 1) % MaxRipples;
        }

        private void OnVSyncFrame(object? sender, EventArgs e)
        {
            if (Visibility != Visibility.Visible)
            {
                SetRunning(false);
                return;
            }
            if (e is RenderingEventArgs rea)
            {
                if (rea.RenderingTime == _lastRenderingTime) return;
                _lastRenderingTime = rea.RenderingTime;
            }

            double now = _clock.Elapsed.TotalSeconds;
            double dt = now - _lastSec;
            if (dt < 0.008) return;
            if (dt > 0.05) dt = 0.0166;
            _lastSec = now;

            for (int i = 0; i < SparkCount; i++)
            {
                ref var s = ref _sparks[i];
                s.XNorm += s.VxNorm * dt + Math.Sin(now * 0.45 + s.Phase) * 0.0022 * dt;
                s.YNorm += s.VyNorm * dt;

                if (s.YNorm < -0.03) s.YNorm = 1.03;
                if (s.XNorm < -0.03) s.XNorm = 1.03;
                else if (s.XNorm > 1.03) s.XNorm = -0.03;
            }

            for (int i = 0; i < MaxRipples; i++)
            {
                ref var r = ref _ripples[i];
                if (!r.Active) continue;
                r.Radius += r.Speed * dt;
                r.Alpha -= r.FadeRate * dt;
                if (r.Alpha <= 0.01 || r.Radius >= r.MaxRadius)
                    r.Active = false;
            }

            InvalidateVisual();
        }

        protected override void OnRender(DrawingContext dc)
        {
            double w = ActualWidth;
            double h = ActualHeight;
            if (w <= 10 || h <= 10) return;

            double t = _clock.Elapsed.TotalSeconds;

            for (int i = 0; i < SparkCount; i++)
            {
                ref readonly var s = ref _sparks[i];
                double pulse = 0.30 + 0.70 * (0.5 + 0.5 * Math.Sin(t * s.PulseSpeed + s.Phase));
                int idx = Math.Clamp((int)(pulse * (AlphaLevels - 1)), 2, AlphaLevels - 1);
                var brush = (i % 3 == 0) ? s_champagneBrushes[idx] : s_goldBrushes[idx];

                double px = s.XNorm * w;
                double py = s.YNorm * h;

                if (s.IsStar)
                {
                    // Zero-allocation 4-point star (no MatrixTransform allocation)
                    double arm = s.Radius * 2.4;
                    var pen = s_starRayPens[idx];
                    dc.DrawLine(pen, new System.Windows.Point(px - arm, py), new System.Windows.Point(px + arm, py));
                    dc.DrawLine(pen, new System.Windows.Point(px, py - arm), new System.Windows.Point(px, py + arm));
                    dc.DrawEllipse(brush, null, new System.Windows.Point(px, py), s.Radius * 0.85, s.Radius * 0.85);
                }
                else
                {
                    dc.DrawEllipse(brush, null, new System.Windows.Point(px, py), s.Radius, s.Radius);
                }
            }

            for (int i = 0; i < MaxRipples; i++)
            {
                ref readonly var r = ref _ripples[i];
                if (!r.Active) continue;
                int idx = Math.Clamp((int)(r.Alpha * (AlphaLevels - 1)), 1, AlphaLevels - 1);
                var pen = r.IsClick ? s_clickRipplePens[idx] : s_ripplePens[idx];
                dc.DrawEllipse(null, pen, new System.Windows.Point(r.X, r.Y), r.Radius, r.Radius);
            }
        }
    }
}
