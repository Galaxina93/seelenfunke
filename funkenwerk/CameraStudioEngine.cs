using System;
using System.Collections.Concurrent;
using System.Collections.Generic;
using System.Diagnostics;
using System.Globalization;
using System.IO;
using System.IO.MemoryMappedFiles;
using System.Linq;
using System.Runtime.InteropServices;
using System.Runtime.InteropServices.ComTypes;
using System.Text;
using System.Text.Json;
using System.Text.Json.Serialization;
using System.Threading;
using System.Threading.Tasks;
using System.Windows;
using System.Windows.Media;
using System.Windows.Media.Imaging;
using Microsoft.Win32;

namespace Funkenwerk
{
    public class CameraMediaItem
    {
        public string Filename { get; set; } = "";
        public string FileName
        {
            get => Filename;
            set => Filename = value;
        }
        public string FullPath { get; set; } = "";
        public bool IsVideo { get; set; }
        public DateTime CreatedAt { get; set; }
        public string DateDisplay { get; set; } = "";
        public string DateLabel
        {
            get => DateDisplay;
            set => DateDisplay = value;
        }
        public double SizeMb { get; set; }
        public string SizeLabel { get; set; } = "";
        public string TypeBadge => IsVideo ? "🎬 VIDEO" : "📸 FOTO";
        public string SizeDisplay => !string.IsNullOrEmpty(SizeLabel) ? SizeLabel : (SizeMb >= 1.0 ? $"{SizeMb:0.0} MB" : $"{Math.Max(1, (int)(SizeMb * 1024))} KB");
    }

    public class WindowShareTarget
    {
        public IntPtr Handle { get; set; }
        public string Title { get; set; } = "";
        public string ProcessName { get; set; } = "";
        public bool IsDesktop { get; set; }
        public bool IsFullDesktop => IsDesktop;
        public string RegionMode { get; set; } = "fit"; // fit, left_half, center_half, right_half, window_fit
        public string CustomDisplayLabel { get; set; } = "";
        public string DisplayName => !string.IsNullOrEmpty(CustomDisplayLabel)
            ? CustomDisplayLabel
            : (IsDesktop ? "🖥 Gesamter Bildschirm (Letterbox Fit)" : $"🪟 {Title} ({ProcessName})");
        public string DisplayLabel => DisplayName;
    }

    public class VideoSignalClipItem
    {
        public string Title { get; set; } = "";
        public string FilePath { get; set; } = "";
        public string DurationLabel { get; set; } = "Video-Signal";
        // Per-Video Auto-Stop Timer ("kleiner Timer pro Abspielen optional aktivierbar")
        public bool AutoStopTimerEnabled { get; set; } = false;
        public int AutoStopDurationSec { get; set; } = 5; // e.g. 5, 10, 15 or custom seconds
        public string AutoStopPresetMode { get; set; } = "off"; // "off", "5", "10", "15", "custom"
    }

    public class MusicSignalClipItem
    {
        public string Title { get; set; } = "";
        public string FilePath { get; set; } = "";
        public string CategoryBadge { get; set; } = "Musik";
        public bool AutoStopTimerEnabled { get; set; } = false;
        public int AutoStopDurationSec { get; set; } = 10;
        public string AutoStopPresetMode { get; set; } = "off"; // "off", "5", "10", "15", "custom"
    }

    public class DeepFakeFacePreset
    {
        public string Name { get; set; } = "";
        public string FilePath { get; set; } = "";
        public string FullPath
        {
            get => FilePath;
            set => FilePath = value;
        }
        public bool IsBuiltIn { get; set; } = true;
    }

    public class CameraStudioConfig
    {
        public string PrimaryCameraName { get; set; } = "HD Pro Webcam C920";
        public string SecondaryCameraName { get; set; } = "Integrated Camera";
        public string SelectedMicrophoneName { get; set; } = "Mikrofon (HD Pro Webcam C920)";
        public string MediaSaveDirectory { get; set; } = "";

        [JsonIgnore]
        public string PrimaryCamera { get => PrimaryCameraName; set => PrimaryCameraName = value; }
        [JsonIgnore]
        public string SecondaryCamera { get => SecondaryCameraName; set => SecondaryCameraName = value; }
        [JsonIgnore]
        public string SelectedMicrophone { get => SelectedMicrophoneName; set => SelectedMicrophoneName = value; }
        [JsonIgnore]
        public string MediaDirectory { get => MediaSaveDirectory; set => MediaSaveDirectory = value; }

        // 1. FPS & Resolution & Recording Mode
        public int TargetFps { get; set; } = 30; // 15, 24, 30, 60
        public int ResolutionWidth { get; set; } = 1280;
        public int ResolutionHeight { get; set; } = 720;
        public string RecordingMode { get; set; } = "standard"; // standard, gaming_60, video_only, countdown_3s, clip_15s
        public string RecordingSpeedMode { get; set; } = "1x"; // "1x", "2x", "3x", "custom"
        public double RecordingSpeedMultiplier { get; set; } = 1.0; // Default 1.0x real-time speed (0.25x .. 10.0x)

        [JsonIgnore]
        public int Fps { get => TargetFps; set => TargetFps = value; }
        [JsonIgnore]
        public int Width { get => ResolutionWidth; set => ResolutionWidth = value; }
        [JsonIgnore]
        public int Height { get => ResolutionHeight; set => ResolutionHeight = value; }

        // 4. Screen Splitting, Interactive Mouse PiP & Super-Ultrawide Window Sharing
        public string SplitMode { get; set; } = "single"; // single, pip, sidebyside/split_lr, topbottom/split_tb, windowshare/window_share
        public string SecondarySourceType { get; set; } = "camera2"; // camera2, window, desktop, mirror_compare
        public string PipCorner { get; set; } = "custom"; // custom, bottom_right, bottom_left, top_right, top_left
        public int PipScalePercent { get; set; } = 30; // 15..75
        public double PipNormalizedX { get; set; } = 0.67; // 0.0 .. 0.92
        public double PipNormalizedY { get; set; } = 0.65; // 0.0 .. 0.92
        public double PipNormalizedW { get; set; } = 0.30; // 0.12 .. 0.85
        public double PipNormalizedH { get; set; } = 0.30; // 0.12 .. 0.85
        public bool ShowPipOverlay { get; set; } = true; // Toggle whether the 2nd PiP window is shown over screen share
        public string PipShape { get; set; } = "rounded"; // rect, rounded, circle, hexagon
        public bool PipChromaKeyEnabled { get; set; } = false;
        public double PipCropLeft { get; set; } = 0.0; // 0.0 .. 0.42
        public double PipCropTop { get; set; } = 0.0; // 0.0 .. 0.42
        public double PipCropRight { get; set; } = 0.0; // 0.0 .. 0.42
        public double PipCropBottom { get; set; } = 0.0; // 0.0 .. 0.42
        public bool SwapSources { get; set; } = false;
        public string SelectedWindowTitle { get; set; } = "";
        public string WindowRegionMode { get; set; } = "fit"; // fit, left_half, center_half, right_half, window_fit, custom_region, mouse_follow_1280, mouse_follow_960, mouse_follow_1600, mouse_follow_custom
        public bool MirrorSharedSource { get; set; } = false; // Horizontally mirrors shared window/monitor (fixes Discord mirror!)
        public bool TutorialClickHighlight { get; set; } = true; // Highlights cursor & clicks when recording tutorials
        public int CustomShareRegionX { get; set; } = 0;
        public int CustomShareRegionY { get; set; } = 0;
        public int CustomShareRegionW { get; set; } = 1920;
        public int CustomShareRegionH { get; set; } = 1080;

        [JsonIgnore]
        public double PipScale
        {
            get => PipScalePercent / 100.0;
            set
            {
                PipScalePercent = (int)Math.Round(value <= 1.0 ? value * 100.0 : value);
                double s = Math.Clamp(PipScalePercent / 100.0, 0.12, 0.85);
                PipNormalizedW = s;
                PipNormalizedH = s;
            }
        }
        [JsonIgnore]
        public double PipNormX { get => PipNormalizedX; set => PipNormalizedX = value; }
        [JsonIgnore]
        public double PipNormY { get => PipNormalizedY; set => PipNormalizedY = value; }
        [JsonIgnore]
        public double PipNormW { get => PipNormalizedW; set => PipNormalizedW = value; }
        [JsonIgnore]
        public double PipNormH { get => PipNormalizedH; set => PipNormalizedH = value; }
        [JsonIgnore]
        public bool SwapSplitSources { get => SwapSources; set => SwapSources = value; }

        // 6 & 7. Microphone & Sound Controls
        public int MicVolume { get; set; } = 85;
        public bool MicMuted { get; set; } = false;
        public int SoundVolume { get; set; } = 80;
        public bool SoundMuted { get; set; } = false;

        // 10, 11, 12, 13. Zoom, Pan/Tilt, Autofocus, AWB, Image Adjustments
        public double ZoomLevel { get; set; } = 1.0; // 1.0 .. 4.0
        public int PanX { get; set; } = 0; // -100 .. +100
        public int PanY { get; set; } = 0; // -100 .. +100
        public bool AutoFocus { get; set; } = true;
        public int ManualFocusValue { get; set; } = 35; // 0 .. 250
        public bool AutoWhiteBalance { get; set; } = true;
        public int Brightness { get; set; } = 0; // -100 .. +100
        public int Contrast { get; set; } = 0; // -100 .. +100
        public int Warmth { get; set; } = 0; // -100 .. +100
        public int Sharpness { get; set; } = 20; // 0 .. 100
        public bool MirrorHorizontal { get; set; } = false;
        public bool FlipVertical { get; set; } = false;

        [JsonIgnore]
        public double Zoom { get => ZoomLevel; set => ZoomLevel = value; }
        [JsonIgnore]
        public bool Autofocus { get => AutoFocus; set => AutoFocus = value; }
        [JsonIgnore]
        public int ManualFocus { get => ManualFocusValue; set => ManualFocusValue = value; }

        // TikTok & Beauty Filters (Skin Smoothing, Face Radiance, Soft Glow)
        public string BeautyFilter { get; set; } = "none"; // none, tiktok_glow, porcelain, golden_hour, cyber_glam, soft_dream
        public int BeautySkinSmooth { get; set; } = 0; // 0 .. 100
        public int BeautyFaceBright { get; set; } = 0; // 0 .. 100
        public int BeautySoftBloom { get; set; } = 0; // 0 .. 100
        [JsonIgnore]
        public int BeautyFaceGlow
        {
            get => BeautyFaceBright;
            set
            {
                BeautyFaceBright = value;
                BeautySoftBloom = value;
            }
        }

        // 9. Color Filters, Distortion Filters & 60FPS Fluid Special Effects
        public string ColorFilter { get; set; } = "none";
        public string DistortionFilter { get; set; } = "none";
        public int DistortionIntensity { get; set; } = 65; // 10 .. 100
        public bool ContinuousSpin { get; set; } = false;
        public int SpinSpeed { get; set; } = 45;
        public bool DiscoMode { get; set; } = false;
        public bool VhsMode { get; set; } = false;
        public bool MatrixRainMode { get; set; } = false;
        public bool DealWithItMode { get; set; } = false;
        public bool CinemaBarsMode { get; set; } = false;
        public bool NeonPulseMode { get; set; } = false;
        public bool RgbWaveMode { get; set; } = false;
        public bool HologramHudMode { get; set; } = false;
        public bool FireAuraMode { get; set; } = false;

        [JsonIgnore]
        public bool FxContinuousSpin { get => ContinuousSpin; set => ContinuousSpin = value; }
        [JsonIgnore]
        public bool FxDiscoParty { get => DiscoMode; set => DiscoMode = value; }
        [JsonIgnore]
        public bool FxVhsRetro { get => VhsMode; set => VhsMode = value; }
        [JsonIgnore]
        public bool FxMatrixRain { get => MatrixRainMode; set => MatrixRainMode = value; }
        [JsonIgnore]
        public bool FxDealWithIt { get => DealWithItMode; set => DealWithItMode = value; }
        [JsonIgnore]
        public bool FxCinemaBars { get => CinemaBarsMode; set => CinemaBarsMode = value; }
        [JsonIgnore]
        public bool FxNeonPulse { get => NeonPulseMode; set => NeonPulseMode = value; }
        [JsonIgnore]
        public bool FxNeonEdgePulse { get => NeonPulseMode; set => NeonPulseMode = value; }
        [JsonIgnore]
        public bool FxRgbWave { get => RgbWaveMode; set => RgbWaveMode = value; }
        [JsonIgnore]
        public bool FxRgbChromaticWave { get => RgbWaveMode; set => RgbWaveMode = value; }
        [JsonIgnore]
        public bool FxHologramHud { get => HologramHudMode; set => HologramHudMode = value; }
        [JsonIgnore]
        public bool FxFireAura { get => FireAuraMode; set => FireAuraMode = value; }

        // 14. Greenscreen / Chroma Key with Eyedropper Pipette
        public bool ChromaKeyEnabled { get; set; } = false;
        public byte ChromaKeyR { get; set; } = 0;
        public byte ChromaKeyG { get; set; } = 177;
        public byte ChromaKeyB { get; set; } = 64;
        public int ChromaTolerance { get; set; } = 38; // 5 .. 90
        public int ChromaSmoothness { get; set; } = 18; // 0 .. 50
        public string ChromaBgMode { get; set; } = "studio_cyber"; // studio_cyber, studio_gold, blur, secondary, image
        public string ChromaCustomImagePath { get; set; } = "";

        // 15. Deep Fake Studio (Deep-Live-Cam Integration)
        public bool DeepFakeEnabled { get; set; } = false;
        public string DeepFakeSourceFacePath { get; set; } = "";
        public bool DeepFakeSwapActive { get; set; } = true;
        public bool DeepFakeMouthMask { get; set; } = false;
        public bool DeepFakeColorCorrection { get; set; } = false;
        public bool DeepFakeEnhancer { get; set; } = true;
        public bool DeepFakeManyFaces { get; set; } = false;
        public int DeepFakeOpacity { get; set; } = 100; // 10 .. 100
        [JsonIgnore]
        public int DeepFakeBlendOpacity { get => DeepFakeOpacity; set => DeepFakeOpacity = value; }
        public string DeepFakeArMode { get; set; } = "none"; // none, cyber_visor, anime_eyes, alien_morph, pixel_anon, terminator
        [JsonIgnore]
        public int DeepFakeArModeCode => DeepFakeArMode switch
        {
            "cyber_visor" => 1,
            "anime_eyes" => 2,
            "alien_morph" => 3,
            "pixel_anon" => 4,
            "terminator" => 5,
            "cyborg_eye" => 5,
            _ => 0
        };

        // 16. Dynamic Video Signal Player, Tarn-Clip (10s Default) & Boomerang Deck (under Bild & Zoom)
        public List<VideoSignalClipItem> VideoSignalClips { get; set; } = new();
        public bool VideoSignalLoop { get; set; } = true;
        public bool VideoSignalBoomerang { get; set; } = false;
        public bool VideoSignalReturnToLive { get; set; } = false;
        public int TarnClipDurationSec { get; set; } = 10; // Default 10s (5s, 10s, 15s, custom)
        public string TarnClipDurationMode { get; set; } = "10"; // "5", "10", "15", "custom"
        public string ActiveVideoSignalPath { get; set; } = "";

        // 16B. Dynamic Music Signal Player & Studio Background Audio Deck (under Video-Einspieler)
        public List<MusicSignalClipItem> MusicSignalClips { get; set; } = new();
        public bool MusicSignalLoop { get; set; } = true;
        public int MusicSignalVolume { get; set; } = 75; // 0 .. 100
        public string ActiveMusicSignalPath { get; set; } = "";

        // 17. Real-Time Studio Voice Changer & DSP Engine ("Stimme" Tab)
        public bool VoiceChangerEnabled { get; set; } = false;
        public string VoicePreset { get; set; } = "none"; // none, anonymous, robot, demon, chipmunk, female, deep_male, alien, radio, autotune, monster, ghost
        public double VoicePitchSemitones { get; set; } = 0.0; // -12.0 .. +12.0
        public double VoiceFormantShift { get; set; } = 0.0; // -100 .. +100
        public int VoiceRobotModulation { get; set; } = 0; // 0 .. 100
        public int VoiceReverbMix { get; set; } = 0; // 0 .. 100
        public int VoiceSubBass { get; set; } = 0; // 0 .. 100
        public int VoiceNoiseGate { get; set; } = 15; // 0 .. 100
        public bool VoiceLiveMonitoring { get; set; } = false;
        public string VoiceOutputDeviceName { get; set; } = "Standard-Ausgabegerät";

        [JsonIgnore]
        public string VoiceChangerPreset { get => VoicePreset; set => VoicePreset = value; }
        [JsonIgnore]
        public int VoiceRobotMix { get => VoiceRobotModulation; set => VoiceRobotModulation = value; }
        [JsonIgnore]
        public int VoiceBassBoost { get => VoiceSubBass; set => VoiceSubBass = value; }
        [JsonIgnore]
        public bool VoiceLiveMonitor { get => VoiceLiveMonitoring; set => VoiceLiveMonitoring = value; }
        [JsonIgnore]
        public string VoiceOutputDevice { get => VoiceOutputDeviceName; set => VoiceOutputDeviceName = value; }

        // Virtual Camera state
        public bool VirtualCamEnabled { get; set; } = true;
    }

    // =========================================================================
    // 1. OS VIRTUAL CAMERA REGISTRATION & SHARED MEMORY BROADCASTER
    // =========================================================================
    public static class FunkenwerkVirtualCamDriver
    {
        public const string VirtualCamClsid = "{F5A8E912-3493-419F-958A-ABA1250EC20B}";
        public const string VideoInputCategoryClsid = "{860BB310-5D01-11d0-BD3B-00A0C911CE86}";
        public const string FriendlyName = "Funkenwerk Kamera";
        public const string SharedMemoryName = "FunkenwerkCamVideo";

        [DllImport("user32.dll", SetLastError = true, CharSet = CharSet.Auto)]
        private static extern IntPtr SendMessageTimeout(
            IntPtr hWnd,
            uint Msg,
            UIntPtr wParam,
            IntPtr lParam,
            uint fuFlags,
            uint uTimeout,
            out UIntPtr lpdwResult);

        private const uint WM_DEVICECHANGE = 0x0219;
        private const uint DBT_DEVNODES_CHANGED = 0x0007;
        private const uint SMTO_ABORTIFHUNG = 0x0002;

        // Standard DirectShow Video Capture Source Pin FilterData (NV12 / YUV / Video Output Pin)
        private static readonly byte[] _filterData = new byte[]
        {
            0x02, 0x00, 0x00, 0x00, 0x00, 0x00, 0x20, 0x00, 0x01, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00,
            0x30, 0x70, 0x69, 0x33, 0x08, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x01, 0x00, 0x00, 0x00,
            0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x30, 0x74, 0x79, 0x33, 0x00, 0x00, 0x00, 0x00,
            0x38, 0x00, 0x00, 0x00, 0x48, 0x00, 0x00, 0x00, 0x76, 0x69, 0x64, 0x73, 0x00, 0x00, 0x10, 0x00,
            0x80, 0x00, 0x00, 0xAA, 0x00, 0x38, 0x9B, 0x71, 0x4E, 0x56, 0x31, 0x32, 0x00, 0x00, 0x10, 0x00,
            0x80, 0x00, 0x00, 0xAA, 0x00, 0x38, 0x9B, 0x71
        };

        public static (bool Ok, string Message) EnsureRegistered(int width = 1280, int height = 720, int fps = 30)
        {
            try
            {
                string baseDir = AppDomain.CurrentDomain.BaseDirectory;
                string dll64 = Path.Combine(baseDir, "FunkenwerkCam64.dll");
                string dll32 = Path.Combine(baseDir, "FunkenwerkCam32.dll");

                if (!File.Exists(dll64))
                {
                    string desktopDll64 = @"C:\Users\konta\Desktop\Funkenwerk\FunkenwerkCam64.dll";
                    if (File.Exists(desktopDll64)) dll64 = desktopDll64;
                }
                if (!File.Exists(dll32))
                {
                    string desktopDll32 = @"C:\Users\konta\Desktop\Funkenwerk\FunkenwerkCam32.dll";
                    if (File.Exists(desktopDll32)) dll32 = desktopDll32;
                }

                // 1. Write default format hint file for DirectShow pin negotiation BEFORE registration
                UpdateFormatHintFile(width, height, fps);

                // 2. Register 64-bit DirectShow Filter in HKCU\Software\Classes (merges into HKCR without Admin!)
                RegisterInHive(Registry.CurrentUser, @"Software\Classes", dll64);

                // 3. Register 32-bit DirectShow Filter in HKCU\Software\Classes\WOW6432Node
                if (File.Exists(dll32))
                {
                    RegisterInHive(Registry.CurrentUser, @"Software\Classes\WOW6432Node", dll32);
                }

                // 4. Also attempt HKLM registration if running with administrative privileges
                try
                {
                    RegisterInHive(Registry.LocalMachine, @"Software\Classes", dll64);
                    if (File.Exists(dll32))
                        RegisterInHive(Registry.LocalMachine, @"Software\Classes\WOW6432Node", dll32);
                }
                catch { }

                // 5. Broadcast WM_DEVICECHANGE (DBT_DEVNODES_CHANGED) to all top-level windows
                // so Discord's VideoInputDevicePoller, OBS, Teams, and Zoom re-enumerate immediately!
                BroadcastDeviceNodesChanged();

                bool ok = File.Exists(dll64);
                return (ok, ok ? "DirectShow 64-Bit & 32-Bit Treiber 'Funkenwerk Kamera' aktiv (Discord-Sync gesendet)" : "DLL nicht gefunden");
            }
            catch (Exception ex)
            {
                return (false, ex.Message);
            }
        }

        public static void BroadcastDeviceNodesChanged()
        {
            try
            {
                SendMessageTimeout(
                    (IntPtr)0xFFFF,
                    WM_DEVICECHANGE,
                    (UIntPtr)DBT_DEVNODES_CHANGED,
                    IntPtr.Zero,
                    SMTO_ABORTIFHUNG,
                    250,
                    out _);
            }
            catch { }
        }

        private static void RegisterInHive(RegistryKey rootKey, string classesRootPrefix, string dllPath)
        {
            string catInstanceKey = $@"{classesRootPrefix}\CLSID\{VideoInputCategoryClsid}\Instance\{VirtualCamClsid}";
            string clsidKey = $@"{classesRootPrefix}\CLSID\{VirtualCamClsid}";
            string inprocKey = $@"{clsidKey}\InprocServer32";

            using (var kCat = rootKey.CreateSubKey(catInstanceKey, true))
            {
                kCat?.SetValue("CLSID", VirtualCamClsid, RegistryValueKind.String);
                kCat?.SetValue("FriendlyName", FriendlyName, RegistryValueKind.String);
                kCat?.SetValue("Description", FriendlyName, RegistryValueKind.String);
                kCat?.SetValue("FilterData", _filterData, RegistryValueKind.Binary);
            }

            using (var kCls = rootKey.CreateSubKey(clsidKey, true))
            {
                kCls?.SetValue(null, FriendlyName, RegistryValueKind.String);
            }

            using (var kInproc = rootKey.CreateSubKey(inprocKey, true))
            {
                kInproc?.SetValue(null, dllPath, RegistryValueKind.String);
                kInproc?.SetValue("ThreadingModel", "Both", RegistryValueKind.String);
            }
        }

        public static void UpdateFormatHintFile(int width, int height, int fps)
        {
            try
            {
                ulong interval = (ulong)(10000000 / Math.Clamp(fps, 5, 120));
                string appData = Environment.GetFolderPath(Environment.SpecialFolder.ApplicationData);
                string hintPath = Path.Combine(appData, "fkw-virtualcam.txt");
                File.WriteAllText(hintPath, $"{width}x{height}x{interval}", Encoding.ASCII);
            }
            catch { }
        }
    }

    public sealed class FunkenwerkVirtualCamBroadcaster : IDisposable
    {
        private MemoryMappedFile? _mmf;
        private MemoryMappedViewAccessor? _accessor;
        private int _cx;
        private int _cy;
        private int _frameSize;
        private int _slotSize;
        private const int HeaderSize = 96;
        private byte[] _nv12Buffer = Array.Empty<byte>();
        private uint _tick = 0;
        private readonly object _sync = new();
        private bool _disposed;

        public bool IsActive { get; private set; }

        public void Start(int width, int height, int fps)
        {
            lock (_sync)
            {
                try
                {
                    ulong interval = (ulong)(10000000 / Math.Clamp(fps, 5, 120));
                    EnsureSharedMemory(width, height, interval);
                    IsActive = true;
                }
                catch { }
            }
        }

        public void SendFrameBgra(byte[] bgra, int width, int height, int fps) => PushBgraFrame(bgra, width, height, fps);

        public void PushBgraFrame(byte[] bgra, int width, int height, int fps)
        {
            if (_disposed || bgra == null || bgra.Length < width * height * 4) return;

            lock (_sync)
            {
                try
                {
                    ulong interval = (ulong)(10000000 / Math.Clamp(fps, 5, 120));
                    EnsureSharedMemory(width, height, interval);
                    if (_accessor == null) return;

                    ConvertBgraToNv12(bgra, _nv12Buffer, width, height);

                    _tick++;
                    uint slotIdx = _tick % 3;
                    int slotOffset = HeaderSize + (int)slotIdx * _slotSize;

                    _accessor.Write(0x1C, (uint)width);
                    _accessor.Write(0x20, (uint)height);
                    _accessor.Write(0x28, interval);
                    _accessor.Write(slotOffset, (ulong)_tick * interval);
                    IntPtr basePtr = _accessor.SafeMemoryMappedViewHandle.DangerousGetHandle();
                    Marshal.Copy(_nv12Buffer, 0, IntPtr.Add(basePtr, (int)_accessor.PointerOffset + slotOffset + 32), _frameSize);

                    // Update write_idx, read_idx, and state=2 (SHARED_QUEUE_STATE_READY)
                    _accessor.Write(0x00, _tick);
                    _accessor.Write(0x04, _tick);
                    _accessor.Write(0x08, (uint)2);
                    IsActive = true;
                }
                catch { }
            }
        }

        private void EnsureSharedMemory(int width, int height, ulong interval)
        {
            if (_mmf != null && _cx == width && _cy == height) return;

            CloseQueue();
            _cx = width;
            _cy = height;
            _frameSize = width * height * 3 / 2;
            _slotSize = 32 + _frameSize;
            int totalSize = HeaderSize + 3 * _slotSize;
            _nv12Buffer = new byte[_frameSize];

            _mmf = MemoryMappedFile.CreateOrOpen(FunkenwerkVirtualCamDriver.SharedMemoryName, totalSize);
            _accessor = _mmf.CreateViewAccessor(0, totalSize);

            _accessor.Write(0x00, (uint)0); // write_idx
            _accessor.Write(0x04, (uint)0); // read_idx
            _accessor.Write(0x08, (uint)2); // state = READY
            _accessor.Write(0x0C, (uint)HeaderSize);
            _accessor.Write(0x10, (uint)(HeaderSize + _slotSize));
            _accessor.Write(0x14, (uint)(HeaderSize + 2 * _slotSize));
            _accessor.Write(0x18, (uint)0); // Video Queue type
            _accessor.Write(0x1C, (uint)width);
            _accessor.Write(0x20, (uint)height);
            _accessor.Write(0x28, interval);
        }

        private static void ConvertBgraToNv12(byte[] bgra, byte[] nv12, int width, int height)
        {
            int uvPlaneOffset = width * height;
            int halfHeight = height / 2;

            Parallel.For(0, halfHeight, py =>
            {
                int y0 = py * 2;
                int y1 = y0 + 1;
                int bgraRow0 = y0 * width * 4;
                int bgraRow1 = y1 * width * 4;
                int yRow0 = y0 * width;
                int yRow1 = y1 * width;
                int uvRow = uvPlaneOffset + py * width;

                for (int x = 0; x < width; x += 2)
                {
                    int p00 = bgraRow0 + x * 4;
                    int b00 = bgra[p00], g00 = bgra[p00 + 1], r00 = bgra[p00 + 2];

                    int p01 = p00 + 4;
                    int b01 = bgra[p01], g01 = bgra[p01 + 1], r01 = bgra[p01 + 2];

                    int p10 = bgraRow1 + x * 4;
                    int b10 = bgra[p10], g10 = bgra[p10 + 1], r10 = bgra[p10 + 2];

                    int p11 = p10 + 4;
                    int b11 = bgra[p11], g11 = bgra[p11 + 1], r11 = bgra[p11 + 2];

                    nv12[yRow0 + x] = (byte)(((66 * r00 + 129 * g00 + 25 * b00 + 128) >> 8) + 16);
                    nv12[yRow0 + x + 1] = (byte)(((66 * r01 + 129 * g01 + 25 * b01 + 128) >> 8) + 16);
                    nv12[yRow1 + x] = (byte)(((66 * r10 + 129 * g10 + 25 * b10 + 128) >> 8) + 16);
                    nv12[yRow1 + x + 1] = (byte)(((66 * r11 + 129 * g11 + 25 * b11 + 128) >> 8) + 16);

                    int rAvg = (r00 + r01 + r10 + r11) >> 2;
                    int gAvg = (g00 + g01 + g10 + g11) >> 2;
                    int bAvg = (b00 + b01 + b10 + b11) >> 2;

                    nv12[uvRow + x] = (byte)(((-38 * rAvg - 74 * gAvg + 112 * bAvg + 128) >> 8) + 128);
                    nv12[uvRow + x + 1] = (byte)(((112 * rAvg - 94 * gAvg - 18 * bAvg + 128) >> 8) + 128);
                }
            });
        }

        public void CloseQueue()
        {
            lock (_sync)
            {
                try
                {
                    if (_accessor != null)
                    {
                        _accessor.Write(0x08, (uint)3); // SHARED_QUEUE_STATE_STOPPING
                        _accessor.Dispose();
                        _accessor = null;
                    }
                    _mmf?.Dispose();
                    _mmf = null;
                    IsActive = false;
                }
                catch { }
            }
        }

        public void Dispose()
        {
            _disposed = true;
            CloseQueue();
        }
    }

    // =========================================================================
    // 2. DIRECTSHOW HARDWARE CAMERA & COREAUDIO CONTROLLER
    // =========================================================================
    public static class FunkenwerkHardwareController
    {
        [ComImport, Guid("62BE5D10-60EB-11d0-BD3B-00A0C911CE86")]
        private class CreateDevEnum { }

        [ComImport, Guid("29840822-5B84-11D0-BD3B-00A0C911CE86"), InterfaceType(ComInterfaceType.InterfaceIsIUnknown)]
        private interface ICreateDevEnum
        {
            [PreserveSig]
            int CreateClassEnumerator([In] ref Guid pType, [Out] out IEnumMoniker ppEnumMoniker, [In] int dwFlags);
        }

        [ComImport, Guid("55272A00-42CB-11CE-8135-00AA004BB851"), InterfaceType(ComInterfaceType.InterfaceIsIUnknown)]
        private interface IPropertyBag
        {
            [PreserveSig]
            int Read([In, MarshalAs(UnmanagedType.LPWStr)] string pszPropName, [Out, MarshalAs(UnmanagedType.Struct)] out object pVar, [In] IntPtr pErrorLog);
            [PreserveSig]
            int Write([In, MarshalAs(UnmanagedType.LPWStr)] string pszPropName, [In] ref object pVar);
        }

        [ComImport, Guid("C6E13370-30AC-11d0-A18C-00A0C9118956"), InterfaceType(ComInterfaceType.InterfaceIsIUnknown)]
        private interface IAMCameraControl
        {
            [PreserveSig] int GetRange(int Property, out int pMin, out int pMax, out int pSteppingDelta, out int pDefault, out int pCapsFlags);
            [PreserveSig] int Set(int Property, int lValue, int Flags);
            [PreserveSig] int Get(int Property, out int lValue, out int Flags);
        }

        [ComImport, Guid("C6E13360-30AC-11d0-A18C-00A0C9118956"), InterfaceType(ComInterfaceType.InterfaceIsIUnknown)]
        private interface IAMVideoProcAmp
        {
            [PreserveSig] int GetRange(int Property, out int pMin, out int pMax, out int pSteppingDelta, out int pDefault, out int pCapsFlags);
            [PreserveSig] int Set(int Property, int lValue, int Flags);
            [PreserveSig] int Get(int Property, out int lValue, out int Flags);
        }

        // Windows CoreAudio COM Interfaces for System Sound & Microphone Volume/Mute/Meter
        [ComImport, Guid("BCDE0395-E52F-467C-8E3D-C4579291692E")]
        private class MMDeviceEnumeratorComObject { }

        [ComImport, Guid("A95664D2-9614-4F35-A746-DE8DB63617E6"), InterfaceType(ComInterfaceType.InterfaceIsIUnknown)]
        private interface IMMDeviceEnumerator
        {
            [PreserveSig] int EnumAudioEndpoints(int dataFlow, int dwStateMask, out IntPtr ppDevices);
            [PreserveSig] int GetDefaultAudioEndpoint(int dataFlow, int role, out IMMDevice ppEndpoint);
        }

        [ComImport, Guid("D666063F-1587-4E43-81F1-B948E807363F"), InterfaceType(ComInterfaceType.InterfaceIsIUnknown)]
        private interface IMMDevice
        {
            [PreserveSig] int Activate(ref Guid iid, int dwClsCtx, IntPtr pActivationParams, [MarshalAs(UnmanagedType.IUnknown)] out object ppInterface);
        }

        [ComImport, Guid("5CDF2C82-841E-4546-9722-0CF74078229A"), InterfaceType(ComInterfaceType.InterfaceIsIUnknown)]
        private interface IAudioEndpointVolume
        {
            [PreserveSig] int RegisterControlChangeNotify(IntPtr pNotify);
            [PreserveSig] int UnregisterControlChangeNotify(IntPtr pNotify);
            [PreserveSig] int GetChannelCount(out int pnChannelCount);
            [PreserveSig] int SetMasterVolumeLevel(float fLevelDB, ref Guid pguidEventContext);
            [PreserveSig] int SetMasterVolumeLevelScalar(float fLevel, ref Guid pguidEventContext);
            [PreserveSig] int GetMasterVolumeLevel(out float pfLevelDB);
            [PreserveSig] int GetMasterVolumeLevelScalar(out float pfLevel);
            [PreserveSig] int SetChannelVolumeLevel(uint nChannel, float fLevelDB, ref Guid pguidEventContext);
            [PreserveSig] int SetChannelVolumeLevelScalar(uint nChannel, float fLevel, ref Guid pguidEventContext);
            [PreserveSig] int GetChannelVolumeLevel(uint nChannel, out float pfLevelDB);
            [PreserveSig] int GetChannelVolumeLevelScalar(uint nChannel, out float pfLevel);
            [PreserveSig] int SetMute([MarshalAs(UnmanagedType.Bool)] bool bMute, ref Guid pguidEventContext);
            [PreserveSig] int GetMute(out bool pbMute);
        }

        [ComImport, Guid("C02216F6-8C67-4B5B-9D00-D008E73E0064"), InterfaceType(ComInterfaceType.InterfaceIsIUnknown)]
        private interface IAudioMeterInformation
        {
            [PreserveSig] int GetPeakValue(out float pfPeak);
        }

        public static List<string> GetVideoDevices() => GetVideoInputDevices();
        public static List<string> GetAudioDevices() => GetAudioInputDevices();

        public static (double Volume, bool Muted) GetAudioVolumeAndMute(int dataFlow)
        {
            try
            {
                var enumerator = (IMMDeviceEnumerator)new MMDeviceEnumeratorComObject();
                Guid epVolGuid = typeof(IAudioEndpointVolume).GUID;
                if (enumerator.GetDefaultAudioEndpoint(dataFlow, 0, out var dev) == 0 && dev != null)
                {
                    if (dev.Activate(ref epVolGuid, 1, IntPtr.Zero, out object volObj) == 0 && volObj is IAudioEndpointVolume epVol)
                    {
                        epVol.GetMasterVolumeLevelScalar(out float lvl);
                        epVol.GetMute(out bool muted);
                        return (Math.Round(lvl * 100.0), muted);
                    }
                }
            }
            catch { }
            return (dataFlow == 1 ? 85.0 : 80.0, false);
        }

        public static void SetAudioVolumeAndMute(int dataFlow, double volume, bool muted)
        {
            Task.Run(() =>
            {
                try
                {
                    var enumerator = (IMMDeviceEnumerator)new MMDeviceEnumeratorComObject();
                    Guid epVolGuid = typeof(IAudioEndpointVolume).GUID;
                    Guid emptyCtx = Guid.Empty;
                    if (enumerator.GetDefaultAudioEndpoint(dataFlow, 0, out var dev) == 0 && dev != null)
                    {
                        if (dev.Activate(ref epVolGuid, 1, IntPtr.Zero, out object volObj) == 0 && volObj is IAudioEndpointVolume epVol)
                        {
                            epVol.SetMasterVolumeLevelScalar(Math.Clamp((float)(volume / 100.0), 0f, 1f), ref emptyCtx);
                            epVol.SetMute(muted, ref emptyCtx);
                        }
                    }
                }
                catch { }
            });
        }

        public static double GetAudioPeakLevel(int dataFlow)
        {
            try
            {
                var enumerator = (IMMDeviceEnumerator)new MMDeviceEnumeratorComObject();
                Guid meterGuid = typeof(IAudioMeterInformation).GUID;
                if (enumerator.GetDefaultAudioEndpoint(dataFlow, 0, out var dev) == 0 && dev != null)
                {
                    if (dev.Activate(ref meterGuid, 1, IntPtr.Zero, out object mObj) == 0 && mObj is IAudioMeterInformation meter)
                    {
                        meter.GetPeakValue(out float p);
                        return Math.Clamp(p, 0.0, 1.0);
                    }
                }
            }
            catch { }
            return 0.0;
        }

        public static void SetCameraControlProperty(string camName, int prop, int value, bool auto)
        {
            try
            {
                var devEnum = (ICreateDevEnum)new CreateDevEnum();
                Guid cat = new Guid("860BB310-5D01-11d0-BD3B-00A0C911CE86");
                if (devEnum.CreateClassEnumerator(ref cat, out var enumMon, 0) != 0 || enumMon == null) return;
                IMoniker[] mon = new IMoniker[1];
                while (enumMon.Next(1, mon, IntPtr.Zero) == 0)
                {
                    Guid bagId = typeof(IPropertyBag).GUID;
                    mon[0].BindToStorage(null!, null!, ref bagId, out object bagObj);
                    if (bagObj is not IPropertyBag bag || bag.Read("FriendlyName", out object nameObj, IntPtr.Zero) != 0) continue;
                    if (!string.Equals(nameObj as string, camName, StringComparison.OrdinalIgnoreCase)) continue;
                    Guid filterId = new Guid("56a86895-0ad4-11ce-b03a-0020af0ba770");
                    mon[0].BindToObject(null!, null!, ref filterId, out object filterObj);
                    if (filterObj is IAMCameraControl camCtrl && camCtrl.GetRange(prop, out int min, out int max, out int step, out int def, out _) == 0)
                    {
                        if (auto) camCtrl.Set(prop, def, 1);
                        else
                        {
                            int v = Math.Clamp(value, min, max);
                            if (step > 1) v = min + ((v - min) / step) * step;
                            camCtrl.Set(prop, v, 2);
                        }
                    }
                    break;
                }
            }
            catch { }
        }

        public static void SetVideoProcAmpProperty(string camName, int prop, int value, bool auto)
        {
            try
            {
                var devEnum = (ICreateDevEnum)new CreateDevEnum();
                Guid cat = new Guid("860BB310-5D01-11d0-BD3B-00A0C911CE86");
                if (devEnum.CreateClassEnumerator(ref cat, out var enumMon, 0) != 0 || enumMon == null) return;
                IMoniker[] mon = new IMoniker[1];
                while (enumMon.Next(1, mon, IntPtr.Zero) == 0)
                {
                    Guid bagId = typeof(IPropertyBag).GUID;
                    mon[0].BindToStorage(null!, null!, ref bagId, out object bagObj);
                    if (bagObj is not IPropertyBag bag || bag.Read("FriendlyName", out object nameObj, IntPtr.Zero) != 0) continue;
                    if (!string.Equals(nameObj as string, camName, StringComparison.OrdinalIgnoreCase)) continue;
                    Guid filterId = new Guid("56a86895-0ad4-11ce-b03a-0020af0ba770");
                    mon[0].BindToObject(null!, null!, ref filterId, out object filterObj);
                    if (filterObj is IAMVideoProcAmp procAmp && procAmp.GetRange(prop, out int min, out int max, out int step, out int def, out _) == 0)
                    {
                        if (auto) procAmp.Set(prop, def, 1);
                        else
                        {
                            double t = Math.Clamp(value / 255.0, 0.0, 1.0);
                            int v = min + (int)Math.Round(t * (max - min));
                            if (step > 1) v = min + ((v - min) / step) * step;
                            procAmp.Set(prop, Math.Clamp(v, min, max), 2);
                        }
                    }
                    break;
                }
            }
            catch { }
        }

        public static List<string> GetVideoInputDevices()
        {
            var list = new List<string>();
            try
            {
                var devEnum = (ICreateDevEnum)new CreateDevEnum();
                Guid cat = new Guid("860BB310-5D01-11d0-BD3B-00A0C911CE86");
                if (devEnum.CreateClassEnumerator(ref cat, out var enumMon, 0) == 0 && enumMon != null)
                {
                    IMoniker[] mon = new IMoniker[1];
                    while (enumMon.Next(1, mon, IntPtr.Zero) == 0)
                    {
                        Guid bagId = typeof(IPropertyBag).GUID;
                        mon[0].BindToStorage(null!, null!, ref bagId, out object bagObj);
                        if (bagObj is IPropertyBag bag && bag.Read("FriendlyName", out object nameObj, IntPtr.Zero) == 0)
                        {
                            string? name = nameObj as string;
                            if (!string.IsNullOrWhiteSpace(name) &&
                                !name.Equals("Funkenwerk Kamera", StringComparison.OrdinalIgnoreCase) &&
                                !name.Equals("Logi Capture", StringComparison.OrdinalIgnoreCase) &&
                                !name.Equals("OBS Virtual Camera", StringComparison.OrdinalIgnoreCase))
                            {
                                if (!list.Contains(name)) list.Add(name);
                            }
                        }
                    }
                }
            }
            catch { }

            if (list.Count == 0)
            {
                list.Add("HD Pro Webcam C920");
                list.Add("Integrated Camera");
            }
            return list;
        }

        public static List<string> GetAudioInputDevices()
        {
            var list = new List<string>();
            try
            {
                var devEnum = (ICreateDevEnum)new CreateDevEnum();
                Guid cat = new Guid("33D9A762-90C8-11D0-BD43-00A0C911CE86");
                if (devEnum.CreateClassEnumerator(ref cat, out var enumMon, 0) == 0 && enumMon != null)
                {
                    IMoniker[] mon = new IMoniker[1];
                    while (enumMon.Next(1, mon, IntPtr.Zero) == 0)
                    {
                        Guid bagId = typeof(IPropertyBag).GUID;
                        mon[0].BindToStorage(null!, null!, ref bagId, out object bagObj);
                        if (bagObj is IPropertyBag bag && bag.Read("FriendlyName", out object nameObj, IntPtr.Zero) == 0)
                        {
                            string? name = nameObj as string;
                            if (!string.IsNullOrWhiteSpace(name) && !list.Contains(name))
                            {
                                list.Add(name);
                            }
                        }
                    }
                }
            }
            catch { }

            if (list.Count == 0)
            {
                list.Add("Mikrofon (HD Pro Webcam C920)");
            }
            return list;
        }

        public static void ApplyCameraHardwareSettings(CameraStudioConfig cfg)
        {
            Task.Run(() =>
            {
                ICreateDevEnum? devEnum = null;
                IEnumMoniker? enumMon = null;
                try
                {
                    devEnum = (ICreateDevEnum)new CreateDevEnum();
                    Guid cat = new Guid("860BB310-5D01-11d0-BD3B-00A0C911CE86");
                    if (devEnum.CreateClassEnumerator(ref cat, out enumMon, 0) != 0 || enumMon == null) return;

                    IMoniker[] mon = new IMoniker[1];
                    while (enumMon.Next(1, mon, IntPtr.Zero) == 0 && mon[0] != null)
                    {
                        object? bagObj = null;
                        object? filterObj = null;
                        try
                        {
                            Guid bagId = typeof(IPropertyBag).GUID;
                            mon[0].BindToStorage(null!, null!, ref bagId, out bagObj);
                            if (bagObj is not IPropertyBag bag || bag.Read("FriendlyName", out object nameObj, IntPtr.Zero) != 0)
                                continue;

                            string? name = nameObj as string;
                            if (string.IsNullOrWhiteSpace(name) || !name.Equals(cfg.PrimaryCameraName, StringComparison.OrdinalIgnoreCase))
                                continue;

                            Guid filterId = new Guid("56a86895-0ad4-11ce-b03a-0020af0ba770");
                            try { mon[0].BindToObject(null!, null!, ref filterId, out filterObj); } catch { }
                            if (filterObj == null) break;

                            if (filterObj is IAMCameraControl camCtrl)
                            {
                                // 1. Zoom (Property = 3, Flags: 2 = Manual)
                                if (camCtrl.GetRange(3, out int zMin, out int zMax, out int zStep, out _, out _) == 0 && zMax > zMin)
                                {
                                    double normZoom = Math.Clamp((cfg.ZoomLevel - 1.0) / 3.0, 0.0, 1.0);
                                    int hwZoom = zMin + (int)Math.Round(normZoom * (zMax - zMin));
                                    if (zStep > 1) hwZoom = zMin + ((hwZoom - zMin) / zStep) * zStep;
                                    camCtrl.Set(3, Math.Clamp(hwZoom, zMin, zMax), 2);
                                }

                                // 2. Pan & Tilt (Property 0 & 1)
                                if (camCtrl.GetRange(0, out int pMin, out int pMax, out int pStep, out _, out _) == 0 && pMax > pMin)
                                {
                                    int hwPan = (int)Math.Round((cfg.PanX / 100.0) * pMax);
                                    if (pStep > 1) hwPan = (hwPan / pStep) * pStep;
                                    camCtrl.Set(0, Math.Clamp(hwPan, pMin, pMax), 2);
                                }
                                if (camCtrl.GetRange(1, out int tMin, out int tMax, out int tStep, out _, out _) == 0 && tMax > tMin)
                                {
                                    int hwTilt = (int)Math.Round((-cfg.PanY / 100.0) * tMax);
                                    if (tStep > 1) hwTilt = (hwTilt / tStep) * tStep;
                                    camCtrl.Set(1, Math.Clamp(hwTilt, tMin, tMax), 2);
                                }

                                // 3. Focus (Property = 6, Flags: 1 = Auto, 2 = Manual)
                                if (camCtrl.GetRange(6, out int fMin, out int fMax, out int fStep, out int fDef, out _) == 0)
                                {
                                    if (cfg.AutoFocus)
                                    {
                                        camCtrl.Set(6, fDef, 1); // 1 = Auto
                                    }
                                    else
                                    {
                                        int fVal = Math.Clamp(cfg.ManualFocusValue, fMin, fMax);
                                        if (fStep > 1) fVal = fMin + ((fVal - fMin) / fStep) * fStep;
                                        camCtrl.Set(6, fVal, 2); // 2 = Manual
                                    }
                                }
                            }

                            if (filterObj is IAMVideoProcAmp procAmp)
                            {
                                // Brightness (0)
                                SetMappedProcAmp(procAmp, 0, cfg.Brightness, -100, 100);
                                // Contrast (1)
                                SetMappedProcAmp(procAmp, 1, cfg.Contrast, -100, 100);
                                // Sharpness (4)
                                SetMappedProcAmp(procAmp, 4, cfg.Sharpness, 0, 100);

                                // White Balance (4 in VideoProcAmp, also try 7 for extended drivers)
                                foreach (int wbProp in new[] { 4, 7 })
                                {
                                    if (procAmp.GetRange(wbProp, out int wbMin, out int wbMax, out int wbStep, out int wbDef, out _) == 0 && wbMax > wbMin)
                                    {
                                        if (cfg.AutoWhiteBalance)
                                        {
                                            procAmp.Set(wbProp, wbDef, 1);
                                        }
                                        else
                                        {
                                            double normWarmth = (cfg.Warmth + 100.0) / 200.0;
                                            int wbVal = wbMin + (int)Math.Round(normWarmth * (wbMax - wbMin));
                                            if (wbStep > 1) wbVal = wbMin + ((wbVal - wbMin) / wbStep) * wbStep;
                                            procAmp.Set(wbProp, Math.Clamp(wbVal, wbMin, wbMax), 2);
                                        }
                                    }
                                }
                            }
                            break;
                        }
                        finally
                        {
                            if (filterObj != null && Marshal.IsComObject(filterObj)) try { Marshal.ReleaseComObject(filterObj); } catch { }
                            if (bagObj != null && Marshal.IsComObject(bagObj)) try { Marshal.ReleaseComObject(bagObj); } catch { }
                            try { Marshal.ReleaseComObject(mon[0]); } catch { }
                            mon[0] = null!;
                        }
                    }
                }
                catch { }
                finally
                {
                    if (enumMon != null && Marshal.IsComObject(enumMon)) try { Marshal.ReleaseComObject(enumMon); } catch { }
                    if (devEnum != null && Marshal.IsComObject(devEnum)) try { Marshal.ReleaseComObject(devEnum); } catch { }
                }
            });
        }

        private static void SetMappedProcAmp(IAMVideoProcAmp procAmp, int prop, int uiVal, int uiMin, int uiMax)
        {
            if (procAmp.GetRange(prop, out int min, out int max, out int step, out int def, out _) != 0 || max <= min)
                return;

            int hwVal;
            if (uiMin < 0 && uiVal == 0)
            {
                hwVal = def;
            }
            else
            {
                double t = (double)(uiVal - uiMin) / (uiMax - uiMin);
                hwVal = min + (int)Math.Round(t * (max - min));
            }
            if (step > 1) hwVal = min + ((hwVal - min) / step) * step;
            procAmp.Set(prop, Math.Clamp(hwVal, min, max), 2);
        }

        public static void ApplyAudioEndpointSettings(int micVolume, bool micMuted, int soundVolume, bool soundMuted)
        {
            Task.Run(() =>
            {
                IMMDeviceEnumerator? enumerator = null;
                IMMDevice? capDev = null;
                IMMDevice? renDev = null;
                object? capVolObj = null;
                object? renVolObj = null;
                try
                {
                    enumerator = (IMMDeviceEnumerator)new MMDeviceEnumeratorComObject();
                    Guid epVolGuid = typeof(IAudioEndpointVolume).GUID;
                    Guid emptyCtx = Guid.Empty;

                    // 1. Default Capture Endpoint (Microphone)
                    if (enumerator.GetDefaultAudioEndpoint(1 /*eCapture*/, 0 /*eConsole*/, out capDev) == 0 && capDev != null)
                    {
                        if (capDev.Activate(ref epVolGuid, 1, IntPtr.Zero, out capVolObj) == 0 && capVolObj is IAudioEndpointVolume capVol)
                        {
                            capVol.SetMasterVolumeLevelScalar(Math.Clamp(micVolume / 100f, 0f, 1f), ref emptyCtx);
                            capVol.SetMute(micMuted, ref emptyCtx);
                        }
                    }

                    // 2. Default Render Endpoint (System Sound)
                    if (enumerator.GetDefaultAudioEndpoint(0 /*eRender*/, 0 /*eConsole*/, out renDev) == 0 && renDev != null)
                    {
                        if (renDev.Activate(ref epVolGuid, 1, IntPtr.Zero, out renVolObj) == 0 && renVolObj is IAudioEndpointVolume renVol)
                        {
                            renVol.SetMasterVolumeLevelScalar(Math.Clamp(soundVolume / 100f, 0f, 1f), ref emptyCtx);
                            renVol.SetMute(soundMuted, ref emptyCtx);
                        }
                    }
                }
                catch { }
                finally
                {
                    if (capVolObj != null && Marshal.IsComObject(capVolObj)) try { Marshal.ReleaseComObject(capVolObj); } catch { }
                    if (renVolObj != null && Marshal.IsComObject(renVolObj)) try { Marshal.ReleaseComObject(renVolObj); } catch { }
                    if (capDev != null && Marshal.IsComObject(capDev)) try { Marshal.ReleaseComObject(capDev); } catch { }
                    if (renDev != null && Marshal.IsComObject(renDev)) try { Marshal.ReleaseComObject(renDev); } catch { }
                    if (enumerator != null && Marshal.IsComObject(enumerator)) try { Marshal.ReleaseComObject(enumerator); } catch { }
                }
            });
        }

        public static (double MicPeak, double SoundPeak) ReadAudioPeaks(bool micMuted, int micVolume, bool soundMuted, int soundVolume)
        {
            double micPeak = 0.0;
            double soundPeak = 0.0;
            IMMDeviceEnumerator? enumerator = null;
            IMMDevice? capDev = null;
            IMMDevice? renDev = null;
            object? capMeterObj = null;
            object? renMeterObj = null;
            try
            {
                enumerator = (IMMDeviceEnumerator)new MMDeviceEnumeratorComObject();
                Guid meterGuid = typeof(IAudioMeterInformation).GUID;

                if (!micMuted && enumerator.GetDefaultAudioEndpoint(1 /*eCapture*/, 0, out capDev) == 0 && capDev != null)
                {
                    if (capDev.Activate(ref meterGuid, 1, IntPtr.Zero, out capMeterObj) == 0 && capMeterObj is IAudioMeterInformation meter)
                    {
                        meter.GetPeakValue(out float p);
                        micPeak = Math.Clamp(p * (micVolume / 100.0) * 1.35, 0.0, 1.0);
                    }
                }

                if (!soundMuted && enumerator.GetDefaultAudioEndpoint(0 /*eRender*/, 0, out renDev) == 0 && renDev != null)
                {
                    if (renDev.Activate(ref meterGuid, 1, IntPtr.Zero, out renMeterObj) == 0 && renMeterObj is IAudioMeterInformation meter)
                    {
                        meter.GetPeakValue(out float p);
                        soundPeak = Math.Clamp(p * (soundVolume / 100.0) * 1.35, 0.0, 1.0);
                    }
                }
            }
            catch { }
            finally
            {
                if (capMeterObj != null && Marshal.IsComObject(capMeterObj)) try { Marshal.ReleaseComObject(capMeterObj); } catch { }
                if (renMeterObj != null && Marshal.IsComObject(renMeterObj)) try { Marshal.ReleaseComObject(renMeterObj); } catch { }
                if (capDev != null && Marshal.IsComObject(capDev)) try { Marshal.ReleaseComObject(capDev); } catch { }
                if (renDev != null && Marshal.IsComObject(renDev)) try { Marshal.ReleaseComObject(renDev); } catch { }
                if (enumerator != null && Marshal.IsComObject(enumerator)) try { Marshal.ReleaseComObject(enumerator); } catch { }
            }
            return (micPeak, soundPeak);
        }
    }

    // =========================================================================
    // 3. WIN32 WINDOW & DESKTOP SCREEN CAPTURE (32:9 ULTRAWIDE & OBS SOURCES)
    // =========================================================================
    public static class FunkenwerkWindowCapture
    {
        private delegate bool EnumWindowsProc(IntPtr hWnd, IntPtr lParam);

        [DllImport("user32.dll")]
        private static extern bool EnumWindows(EnumWindowsProc enumProc, IntPtr lParam);

        [DllImport("user32.dll")]
        private static extern bool IsWindowVisible(IntPtr hWnd);

        [DllImport("user32.dll")]
        private static extern bool IsIconic(IntPtr hWnd);

        [DllImport("user32.dll", CharSet = CharSet.Unicode)]
        private static extern int GetWindowText(IntPtr hWnd, StringBuilder lpString, int nMaxCount);

        [DllImport("user32.dll")]
        private static extern int GetWindowTextLength(IntPtr hWnd);

        [DllImport("user32.dll")]
        private static extern uint GetWindowThreadProcessId(IntPtr hWnd, out uint lpdwProcessId);

        [DllImport("user32.dll")]
        private static extern bool GetWindowRect(IntPtr hWnd, out RECT lpRect);

        [DllImport("user32.dll")]
        private static extern bool PrintWindow(IntPtr hWnd, IntPtr hdcBlt, uint nFlags);

        [DllImport("user32.dll")]
        private static extern IntPtr GetDesktopWindow();

        [DllImport("user32.dll")]
        private static extern IntPtr GetWindowDC(IntPtr hWnd);

        [DllImport("user32.dll")]
        private static extern int ReleaseDC(IntPtr hWnd, IntPtr hDC);

        [DllImport("user32.dll")]
        private static extern int GetSystemMetrics(int nIndex);

        [DllImport("user32.dll")]
        private static extern bool GetCursorInfo(ref CURSORINFO pci);

        [DllImport("user32.dll")]
        private static extern bool GetCursorPos(out POINT lpPoint);

        [DllImport("user32.dll")]
        private static extern short GetAsyncKeyState(int vKey);

        [DllImport("user32.dll")]
        private static extern bool DrawIconEx(IntPtr hdc, int xLeft, int yTop, IntPtr hIcon, int cxWidth, int cyHeight, uint istepIfAniCur, IntPtr hbrFlickerFreeDraw, uint diFlags);

        [DllImport("gdi32.dll")]
        private static extern int GetDeviceCaps(IntPtr hdc, int nIndex);

        [DllImport("gdi32.dll")]
        private static extern IntPtr CreateCompatibleDC(IntPtr hdc);

        [DllImport("gdi32.dll")]
        private static extern IntPtr CreateDIBSection(IntPtr hdc, ref BITMAPINFO pbmi, uint pila, out IntPtr ppvBits, IntPtr hSection, uint dwOffset);

        [DllImport("gdi32.dll")]
        private static extern IntPtr SelectObject(IntPtr hdc, IntPtr hgdiobj);

        [DllImport("gdi32.dll")]
        private static extern bool DeleteObject(IntPtr hObject);

        [DllImport("gdi32.dll")]
        private static extern bool DeleteDC(IntPtr hdc);

        [DllImport("gdi32.dll")]
        private static extern bool StretchBlt(IntPtr hdcDest, int nXOriginDest, int nYOriginDest, int nWidthDest, int nHeightDest,
            IntPtr hdcSrc, int nXOriginSrc, int nYOriginSrc, int nWidthSrc, int nHeightSrc, uint dwRop);

        [DllImport("gdi32.dll")]
        private static extern int SetStretchBltMode(IntPtr hdc, int iStretchMode);

        [DllImport("gdi32.dll")]
        private static extern bool SetBrushOrgEx(IntPtr hdc, int nXOrg, int nYOrg, IntPtr lppt);

        [StructLayout(LayoutKind.Sequential)]
        private struct RECT { public int Left, Top, Right, Bottom; }

        [StructLayout(LayoutKind.Sequential)]
        private struct POINT { public int X, Y; }

        [StructLayout(LayoutKind.Sequential)]
        private struct CURSORINFO
        {
            public int cbSize;
            public int flags;
            public IntPtr hCursor;
            public POINT ptScreenPos;
        }

        [StructLayout(LayoutKind.Sequential)]
        private struct BITMAPINFOHEADER
        {
            public uint biSize;
            public int biWidth;
            public int biHeight;
            public ushort biPlanes;
            public ushort biBitCount;
            public uint biCompression;
            public uint biSizeImage;
            public int biXPelsPerMeter;
            public int biYPelsPerMeter;
            public uint biClrUsed;
            public uint biClrImportant;
        }

        [StructLayout(LayoutKind.Sequential)]
        private struct BITMAPINFO
        {
            public BITMAPINFOHEADER bmiHeader;
            public uint bmiColors;
        }

        private const uint SRCCOPY = 0x00CC0020;
        private const uint PW_RENDERFULLCONTENT = 0x00000002;
        private const int DESKTOPHORZRES = 118;
        private const int DESKTOPVERTRES = 117;

        public static List<WindowShareTarget> GetCapturableWindows() => GetShareableWindows();

        public static (int PhysicalWidth, int PhysicalHeight) GetPrimaryMonitorPhysicalSize()
        {
            IntPtr hdc = GetWindowDC(GetDesktopWindow());
            if (hdc != IntPtr.Zero)
            {
                try
                {
                    int w = GetDeviceCaps(hdc, DESKTOPHORZRES);
                    int h = GetDeviceCaps(hdc, DESKTOPVERTRES);
                    if (w >= 640 && h >= 480) return (w, h);
                }
                finally
                {
                    ReleaseDC(GetDesktopWindow(), hdc);
                }
            }
            int smW = GetSystemMetrics(0);
            int smH = GetSystemMetrics(1);
            return (Math.Max(1280, smW), Math.Max(720, smH));
        }

        public static int CustomRegionX { get; set; } = 0;
        public static int CustomRegionY { get; set; } = 0;
        public static int CustomRegionW { get; set; } = 1920;
        public static int CustomRegionH { get; set; } = 1080;
        public static bool TutorialClickHighlightEnabled { get; set; } = true;

        private static double _smoothMouseX = -1;
        private static double _smoothMouseY = -1;
        private static long _lastLeftClickTick = 0;
        private static long _lastRightClickTick = 0;
        private static bool _wasLeftDown = false;
        private static bool _wasRightDown = false;

        public static void SetCustomRegion(int x, int y, int w, int h) => SetCustomCaptureRegion(x, y, w, h);

        public static void SetCustomCaptureRegion(int x, int y, int w, int h)
        {
            CustomRegionX = Math.Max(0, x);
            CustomRegionY = Math.Max(0, y);
            CustomRegionW = Math.Max(120, w);
            CustomRegionH = Math.Max(90, h);
        }

        public static bool CaptureWindowBgra(IntPtr targetHwnd, bool captureDesktop, int destW, int destH, byte[] destBgra, string regionMode = "fit", bool mirrorHorizontal = false)
            => CaptureWindowOrDesktopToBgra(targetHwnd, captureDesktop, destBgra, destW, destH, regionMode, mirrorHorizontal);

        public static byte[]? CaptureWindowBgra(IntPtr targetHwnd, bool captureDesktop, int destW, int destH, string regionMode = "fit", bool mirrorHorizontal = false)
        {
            byte[] buf = new byte[destW * destH * 4];
            if (CaptureWindowOrDesktopToBgra(targetHwnd, captureDesktop, buf, destW, destH, regionMode, mirrorHorizontal))
                return buf;
            return null;
        }

        public static List<WindowShareTarget> GetShareableWindows()
        {
            var (monW, monH) = GetPrimaryMonitorPhysicalSize();
            int halfW = monW / 2;

            var results = new List<WindowShareTarget>
            {
                new WindowShareTarget
                {
                    Handle = IntPtr.Zero,
                    Title = "Gesamter Monitor (Letterbox Fit)",
                    ProcessName = "Desktop",
                    IsDesktop = true,
                    RegionMode = "fit",
                    CustomDisplayLabel = $"🖥 Gesamter Monitor ({monW}×{monH} • Proportional Fit)"
                },
                new WindowShareTarget
                {
                    Handle = IntPtr.Zero,
                    Title = "Monitor: Zentrum / Mitte (16:9)",
                    ProcessName = "Desktop",
                    IsDesktop = true,
                    RegionMode = "center_half",
                    CustomDisplayLabel = $"🖥 Monitor: Zentrum / Fokus ({halfW}×{monH} • 16:9 Vollbild)"
                },
                new WindowShareTarget
                {
                    Handle = IntPtr.Zero,
                    Title = "Monitor: Linke Hälfte (16:9)",
                    ProcessName = "Desktop",
                    IsDesktop = true,
                    RegionMode = "left_half",
                    CustomDisplayLabel = $"🖥 Monitor: Linke Hälfte ({halfW}×{monH} • 16:9 Vollbild)"
                },
                new WindowShareTarget
                {
                    Handle = IntPtr.Zero,
                    Title = "Monitor: Rechte Hälfte (16:9)",
                    ProcessName = "Desktop",
                    IsDesktop = true,
                    RegionMode = "right_half",
                    CustomDisplayLabel = $"🖥 Monitor: Rechte Hälfte ({halfW}×{monH} • 16:9 Vollbild)"
                },
                new WindowShareTarget
                {
                    Handle = IntPtr.Zero,
                    Title = "Benutzerdefinierte Fläche (Monitor-Ausschnitt)",
                    ProcessName = "Desktop",
                    IsDesktop = true,
                    RegionMode = "custom_region",
                    CustomDisplayLabel = $"📐 Eigene Fläche ({CustomRegionW}×{CustomRegionH} bei X={CustomRegionX}, Y={CustomRegionY})"
                },
                new WindowShareTarget
                {
                    Handle = IntPtr.Zero,
                    Title = "Maus-Verfolgung: Standard-Tutorial (1280×720 • 2.0× Zoom)",
                    ProcessName = "Desktop",
                    IsDesktop = true,
                    RegionMode = "mouse_follow_1280",
                    CustomDisplayLabel = "🎯 Maus-Verfolgung: Standard-Tutorial (1280×720 • 2.0× Zoom folgt Maus)"
                },
                new WindowShareTarget
                {
                    Handle = IntPtr.Zero,
                    Title = "Maus-Verfolgung: Nah-Fokus (960×540 • 2.7× Detail-Zoom)",
                    ProcessName = "Desktop",
                    IsDesktop = true,
                    RegionMode = "mouse_follow_960",
                    CustomDisplayLabel = "🎯 Maus-Verfolgung: Nah-Fokus (960×540 • 2.7× Detail-Zoom folgt Maus)"
                },
                new WindowShareTarget
                {
                    Handle = IntPtr.Zero,
                    Title = "Maus-Verfolgung: Überblick (1600×900 • 1.6× Weitwinkel)",
                    ProcessName = "Desktop",
                    IsDesktop = true,
                    RegionMode = "mouse_follow_1600",
                    CustomDisplayLabel = "🎯 Maus-Verfolgung: Überblick (1600×900 • 1.6× Weitwinkel folgt Maus)"
                },
                new WindowShareTarget
                {
                    Handle = IntPtr.Zero,
                    Title = $"Maus-Verfolgung: Eigene Flächengröße ({CustomRegionW}×{CustomRegionH} px)",
                    ProcessName = "Desktop",
                    IsDesktop = true,
                    RegionMode = "mouse_follow_custom",
                    CustomDisplayLabel = $"🎯 Maus-Verfolgung: Eigene Größe ({CustomRegionW}×{CustomRegionH} px folgt Maus)"
                }
            };

            try
            {
                int currentPid = Environment.ProcessId;
                EnumWindows((hWnd, lParam) =>
                {
                    if (!IsWindowVisible(hWnd) || IsIconic(hWnd)) return true;
                    int len = GetWindowTextLength(hWnd);
                    if (len <= 1) return true;

                    GetWindowRect(hWnd, out RECT r);
                    int ww = r.Right - r.Left;
                    int wh = r.Bottom - r.Top;
                    if (ww < 180 || wh < 120) return true;

                    var sb = new StringBuilder(len + 2);
                    GetWindowText(hWnd, sb, sb.Capacity);
                    string title = sb.ToString().Trim();
                    if (string.IsNullOrWhiteSpace(title) ||
                        title.Equals("Program Manager", StringComparison.OrdinalIgnoreCase) ||
                        title.Equals("Windows-Eingabeerfahrung", StringComparison.OrdinalIgnoreCase) ||
                        title.Equals("Microsoft Text Input Application", StringComparison.OrdinalIgnoreCase) ||
                        title.Equals("Settings", StringComparison.OrdinalIgnoreCase))
                        return true;

                    GetWindowThreadProcessId(hWnd, out uint pid);
                    if (pid == (uint)currentPid) return true;

                    string procName = "App";
                    try { procName = Process.GetProcessById((int)pid).ProcessName; } catch { }

                    results.Add(new WindowShareTarget
                    {
                        Handle = hWnd,
                        Title = title,
                        ProcessName = procName,
                        IsDesktop = false,
                        RegionMode = "window_fit",
                        CustomDisplayLabel = $"🪟 {title} ({procName} • {ww}×{wh})"
                    });
                    return true;
                }, IntPtr.Zero);
            }
            catch { }

            return results;
        }

        public static bool CaptureWindowOrDesktopToBgra(IntPtr targetHwnd, bool captureDesktop, byte[] destBgra, int destW, int destH, string regionMode = "fit", bool mirrorHorizontal = false)
        {
            if (destBgra == null || destBgra.Length < destW * destH * 4) return false;

            IntPtr desktopHwnd = GetDesktopWindow();
            IntPtr hwnd = (!captureDesktop && targetHwnd != IntPtr.Zero && IsWindowVisible(targetHwnd))
                ? targetHwnd
                : desktopHwnd;

            IntPtr hdcScreen = GetWindowDC(desktopHwnd);
            if (hdcScreen == IntPtr.Zero) return false;

            IntPtr hdcDest = CreateCompatibleDC(hdcScreen);
            var bmi = new BITMAPINFO
            {
                bmiHeader = new BITMAPINFOHEADER
                {
                    biSize = (uint)Marshal.SizeOf<BITMAPINFOHEADER>(),
                    biWidth = destW,
                    biHeight = -destH, // Top-down DIB
                    biPlanes = 1,
                    biBitCount = 32,
                    biCompression = 0
                }
            };

            IntPtr hDib = CreateDIBSection(hdcDest, ref bmi, 0, out IntPtr ppvBits, IntPtr.Zero, 0);
            if (hDib == IntPtr.Zero || ppvBits == IntPtr.Zero)
            {
                DeleteDC(hdcDest);
                ReleaseDC(desktopHwnd, hdcScreen);
                return false;
            }

            IntPtr oldObj = SelectObject(hdcDest, hDib);
            try
            {
                // Use COLORONCOLOR (3) at Full-HD/HD for 15x faster GDI StretchBlt (1.5ms vs 25ms on 5120x1440!)
                int stretchMode = (destW >= 1280) ? 3 /*COLORONCOLOR*/ : 4 /*HALFTONE*/;
                SetStretchBltMode(hdcDest, stretchMode);
                SetBrushOrgEx(hdcDest, 0, 0, IntPtr.Zero);

                int dstX = 0, dstY = 0, dstW = destW, dstH = destH;
                int srcOriginX = 0, srcOriginY = 0, srcCaptureW = destW, srcCaptureH = destH;

                if (!captureDesktop && hwnd != desktopHwnd && GetWindowRect(hwnd, out RECT rect))
                {
                    int winW = Math.Max(64, rect.Right - rect.Left);
                    int winH = Math.Max(64, rect.Bottom - rect.Top);
                    srcOriginX = rect.Left;
                    srcOriginY = rect.Top;
                    srcCaptureW = winW;
                    srcCaptureH = winH;

                    // Preserve window aspect ratio inside destW x destH
                    double scale = Math.Min((double)destW / winW, (double)destH / winH);
                    dstW = Math.Clamp((int)Math.Round(winW * scale), 1, destW);
                    dstH = Math.Clamp((int)Math.Round(winH * scale), 1, destH);
                    dstX = (destW - dstW) / 2;
                    dstY = (destH - dstH) / 2;

                    IntPtr hdcWinTmp = CreateCompatibleDC(hdcScreen);
                    var winBmi = bmi;
                    winBmi.bmiHeader.biWidth = winW;
                    winBmi.bmiHeader.biHeight = -winH;
                    IntPtr hWinDib = CreateDIBSection(hdcWinTmp, ref winBmi, 0, out IntPtr _, IntPtr.Zero, 0);
                    if (hWinDib != IntPtr.Zero)
                    {
                        IntPtr oldWinObj = SelectObject(hdcWinTmp, hWinDib);
                        SetStretchBltMode(hdcWinTmp, stretchMode);
                        bool ok = PrintWindow(hwnd, hdcWinTmp, PW_RENDERFULLCONTENT);
                        if (!ok)
                        {
                            StretchBlt(hdcWinTmp, 0, 0, winW, winH, hdcScreen, rect.Left, rect.Top, winW, winH, SRCCOPY);
                        }

                        // Draw proportional un-squished window into center
                        StretchBlt(hdcDest, dstX, dstY, dstW, dstH, hdcWinTmp, 0, 0, winW, winH, SRCCOPY);

                        SelectObject(hdcWinTmp, oldWinObj);
                        DeleteObject(hWinDib);
                    }
                    DeleteDC(hdcWinTmp);
                }
                else
                {
                    int screenW = GetDeviceCaps(hdcScreen, DESKTOPHORZRES);
                    int screenH = GetDeviceCaps(hdcScreen, DESKTOPVERTRES);
                    if (screenW <= 0) screenW = Math.Max(1280, GetSystemMetrics(0));
                    if (screenH <= 0) screenH = Math.Max(720, GetSystemMetrics(1));

                    srcOriginX = 0;
                    srcOriginY = 0;
                    srcCaptureW = screenW;
                    srcCaptureH = screenH;

                    if (regionMode == "left_half")
                    {
                        srcOriginX = 0;
                        srcCaptureW = Math.Max(640, screenW / 2);
                    }
                    else if (regionMode == "center_half")
                    {
                        srcOriginX = screenW / 4;
                        srcCaptureW = Math.Max(640, screenW / 2);
                    }
                    else if (regionMode == "right_half")
                    {
                        srcOriginX = screenW / 2;
                        srcCaptureW = Math.Max(640, screenW - srcOriginX);
                    }
                    else if (regionMode == "custom_region")
                    {
                        srcOriginX = Math.Clamp(CustomRegionX, 0, Math.Max(0, screenW - 120));
                        srcOriginY = Math.Clamp(CustomRegionY, 0, Math.Max(0, screenH - 90));
                        srcCaptureW = Math.Clamp(CustomRegionW, 120, screenW - srcOriginX);
                        srcCaptureH = Math.Clamp(CustomRegionH, 90, screenH - srcOriginY);
                    }
                    else if (regionMode != null && regionMode.StartsWith("mouse_follow", StringComparison.OrdinalIgnoreCase))
                    {
                        int boxW = 1280;
                        int boxH = 720;
                        if (regionMode == "mouse_follow_960") { boxW = 960; boxH = 540; }
                        else if (regionMode == "mouse_follow_1600") { boxW = 1600; boxH = 900; }
                        else if (regionMode == "mouse_follow_custom")
                        {
                            boxW = Math.Clamp(CustomRegionW, 240, screenW);
                            boxH = Math.Clamp(CustomRegionH, 160, screenH);
                        }
                        boxW = Math.Clamp(boxW, 240, screenW);
                        boxH = Math.Clamp(boxH, 160, screenH);

                        int rawMouseX = screenW / 2;
                        int rawMouseY = screenH / 2;
                        var mci = new CURSORINFO { cbSize = Marshal.SizeOf<CURSORINFO>() };
                        if (GetCursorInfo(ref mci))
                        {
                            rawMouseX = mci.ptScreenPos.X;
                            rawMouseY = mci.ptScreenPos.Y;
                        }
                        else if (GetCursorPos(out POINT mpt))
                        {
                            rawMouseX = mpt.X;
                            rawMouseY = mpt.Y;
                        }

                        if (_smoothMouseX < 0 || _smoothMouseY < 0)
                        {
                            _smoothMouseX = rawMouseX;
                            _smoothMouseY = rawMouseY;
                        }
                        else
                        {
                            double dx = rawMouseX - _smoothMouseX;
                            double dy = rawMouseY - _smoothMouseY;
                            double dist = Math.Sqrt(dx * dx + dy * dy);
                            // Comfort deadzone (prevents hand micro-tremors from shaking tutorial recordings)
                            double alpha = dist < 14.0
                                ? 0.045
                                : Math.Clamp(0.17 + (dist / 850.0) * 0.22, 0.17, 0.38);
                            _smoothMouseX += dx * alpha;
                            _smoothMouseY += dy * alpha;
                        }

                        srcCaptureW = boxW;
                        srcCaptureH = boxH;
                        srcOriginX = Math.Clamp((int)Math.Round(_smoothMouseX - boxW * 0.5), 0, Math.Max(0, screenW - srcCaptureW));
                        srcOriginY = Math.Clamp((int)Math.Round(_smoothMouseY - boxH * 0.5), 0, Math.Max(0, screenH - srcCaptureH));
                    }

                    // Compute aspect-ratio-preserving destination rectangle (prevents 32:9 5120x1440 squishing!)
                    double scale = Math.Min((double)destW / srcCaptureW, (double)destH / srcCaptureH);
                    dstW = Math.Clamp((int)Math.Round(srcCaptureW * scale), 1, destW);
                    dstH = Math.Clamp((int)Math.Round(srcCaptureH * scale), 1, destH);
                    dstX = (destW - dstW) / 2;
                    dstY = (destH - dstH) / 2;

                    // Render crisp proportional screen region in a single fast GDI call
                    StretchBlt(hdcDest, dstX, dstY, dstW, dstH, hdcScreen, srcOriginX, srcOriginY, srcCaptureW, srcCaptureH, SRCCOPY);
                }

                int cursorDestX = -1;
                int cursorDestY = -1;
                bool isMouseFollowOrCustom = regionMode != null &&
                    (regionMode.StartsWith("mouse_follow", StringComparison.OrdinalIgnoreCase) || regionMode == "custom_region");

                // Draw live mouse cursor at proportional coordinates
                try
                {
                    var ci = new CURSORINFO { cbSize = Marshal.SizeOf<CURSORINFO>() };
                    if (GetCursorInfo(ref ci) && (ci.flags & 1) != 0 && ci.hCursor != IntPtr.Zero)
                    {
                        int relX = ci.ptScreenPos.X - srcOriginX;
                        int relY = ci.ptScreenPos.Y - srcOriginY;
                        if (relX >= 0 && relX < srcCaptureW && relY >= 0 && relY < srcCaptureH)
                        {
                            int curX = dstX + (int)((double)relX / srcCaptureW * dstW);
                            int curY = dstY + (int)((double)relY / srcCaptureH * dstH);
                            cursorDestX = curX + 4;
                            cursorDestY = curY + 4;
                            int iconSz = (regionMode != null && regionMode.StartsWith("mouse_follow", StringComparison.OrdinalIgnoreCase)) ? 28 : 24;
                            DrawIconEx(hdcDest, curX, curY, ci.hCursor, iconSz, iconSz, 0, IntPtr.Zero, 0x0003 /*DI_NORMAL*/);
                        }
                    }
                }
                catch { }

                Marshal.Copy(ppvBits, destBgra, 0, destW * destH * 4);

                // Optional Tutorial Cursor Spotlight Halo & Click-Ripple Ring
                if (TutorialClickHighlightEnabled && isMouseFollowOrCustom && cursorDestX >= 0 && cursorDestY >= 0)
                {
                    DrawTutorialCursorSpotlight(destBgra, destW, destH, cursorDestX, cursorDestY,
                        regionMode != null && regionMode.StartsWith("mouse_follow", StringComparison.OrdinalIgnoreCase));
                }

                // Style letterbox/pillarbox bars & optionally mirror horizontally (fixes Discord mirror!)
                PostProcessCapturedScreen(destBgra, destW, destH, dstX, dstY, dstW, dstH, mirrorHorizontal);
                return true;
            }
            catch
            {
                return false;
            }
            finally
            {
                SelectObject(hdcDest, oldObj);
                DeleteObject(hDib);
                DeleteDC(hdcDest);
                ReleaseDC(desktopHwnd, hdcScreen);
            }
        }

        private static void DrawTutorialCursorSpotlight(byte[] bgra, int w, int h, int cx, int cy, bool isMouseFollow)
        {
            bool leftDown = (GetAsyncKeyState(0x01) & 0x8000) != 0;
            bool rightDown = (GetAsyncKeyState(0x02) & 0x8000) != 0;
            long nowMs = Environment.TickCount64;

            if (leftDown && !_wasLeftDown) _lastLeftClickTick = nowMs;
            if (rightDown && !_wasRightDown) _lastRightClickTick = nowMs;
            _wasLeftDown = leftDown;
            _wasRightDown = rightDown;

            long leftAge = nowMs - _lastLeftClickTick;
            long rightAge = nowMs - _lastRightClickTick;
            bool showLeftRipple = leftAge >= 0 && leftAge < 340;
            bool showRightRipple = rightAge >= 0 && rightAge < 340;

            if (!isMouseFollow && !leftDown && !rightDown && !showLeftRipple && !showRightRipple)
                return;

            double rippleProg = showLeftRipple
                ? leftAge / 340.0
                : (showRightRipple ? rightAge / 340.0 : -1.0);
            double rippleRadius = rippleProg >= 0 ? (10.0 + rippleProg * 32.0) : -1.0;
            double rippleAlpha = rippleProg >= 0 ? (1.0 - rippleProg) * 0.85 : 0.0;

            // Gold (#FBBF24 / #C5A059) for left-click & halo, Emerald (#34D399) for right-click
            byte ringR = (showRightRipple || rightDown) ? (byte)52 : (byte)251;
            byte ringG = (showRightRipple || rightDown) ? (byte)211 : (byte)191;
            byte ringB = (showRightRipple || rightDown) ? (byte)153 : (byte)36;

            int maxR = 46;
            int minX = Math.Max(0, cx - maxR);
            int maxX = Math.Min(w - 1, cx + maxR);
            int minY = Math.Max(0, cy - maxR);
            int maxY = Math.Min(h - 1, cy + maxR);

            for (int y = minY; y <= maxY; y++)
            {
                int dy = y - cy;
                int row = y * w * 4;
                for (int x = minX; x <= maxX; x++)
                {
                    int dx = x - cx;
                    double d = Math.Sqrt(dx * dx + dy * dy);
                    if (d > maxR) continue;

                    double blend = 0.0;
                    byte tR = 197, tG = 160, tB = 89; // Seelenfunke Gold #C5A059

                    // 1. Subtle golden tutorial spotlight halo around cursor (outside the inner 7px pointer tip)
                    if (isMouseFollow && d >= 7.0 && d <= 22.0)
                    {
                        double edge = Math.Abs(d - 20.5);
                        if (edge < 1.6)
                        {
                            blend = (1.0 - edge / 1.6) * (leftDown || rightDown ? 0.72 : 0.42);
                        }
                        else if (d < 20.5)
                        {
                            blend = (1.0 - d / 22.0) * 0.16;
                        }
                    }

                    // 2. Expanding Click-Ripple Ring on Left / Right Mouse Click
                    if (rippleRadius > 0)
                    {
                        double distToRing = Math.Abs(d - rippleRadius);
                        if (distToRing < 2.6)
                        {
                            double rAlpha = (1.0 - distToRing / 2.6) * rippleAlpha;
                            if (rAlpha > blend)
                            {
                                blend = rAlpha;
                                tR = ringR;
                                tG = ringG;
                                tB = ringB;
                            }
                        }
                    }

                    if (blend > 0.01)
                    {
                        int idx = row + x * 4;
                        bgra[idx] = (byte)Math.Clamp(bgra[idx] * (1.0 - blend) + tB * blend, 0, 255);
                        bgra[idx + 1] = (byte)Math.Clamp(bgra[idx + 1] * (1.0 - blend) + tG * blend, 0, 255);
                        bgra[idx + 2] = (byte)Math.Clamp(bgra[idx + 2] * (1.0 - blend) + tR * blend, 0, 255);
                    }
                }
            }
        }

        private static void PostProcessCapturedScreen(byte[] bgra, int w, int h, int contentX, int contentY, int contentW, int contentH, bool mirrorHorizontal)
        {
            int rightEdge = contentX + contentW;
            int bottomEdge = contentY + contentH;
            bool hasBars = (contentW < w || contentH < h);

            Parallel.For(0, h, y =>
            {
                int row = y * w * 4;
                bool inVertBar = hasBars && (y < contentY || y >= bottomEdge);
                bool isBorderY = hasBars && (y == contentY - 1 || y == bottomEdge);

                if (hasBars)
                {
                    for (int x = 0; x < w; x++)
                    {
                        int idx = row + x * 4;
                        bool inHorzBar = (x < contentX || x >= rightEdge);
                        bool isBorderX = (x == contentX - 1 || x == rightEdge);

                        if (isBorderY || isBorderX)
                        {
                            bgra[idx] = 55;      // B
                            bgra[idx + 1] = 110; // G
                            bgra[idx + 2] = 145; // R
                            bgra[idx + 3] = 255;
                        }
                        else if (inVertBar || inHorzBar)
                        {
                            bgra[idx] = 20;
                            bgra[idx + 1] = 15;
                            bgra[idx + 2] = 13;
                            bgra[idx + 3] = 255;
                        }
                        else
                        {
                            bgra[idx + 3] = 255;
                        }
                    }
                }
                else
                {
                    for (int x = 0; x < w; x++)
                    {
                        bgra[row + x * 4 + 3] = 255;
                    }
                }

                if (mirrorHorizontal)
                {
                    int halfW = w / 2;
                    for (int x = 0; x < halfW; x++)
                    {
                        int leftIdx = row + x * 4;
                        int rightIdx = row + (w - 1 - x) * 4;
                        byte b0 = bgra[leftIdx], g0 = bgra[leftIdx + 1], r0 = bgra[leftIdx + 2];
                        bgra[leftIdx] = bgra[rightIdx];
                        bgra[leftIdx + 1] = bgra[rightIdx + 1];
                        bgra[leftIdx + 2] = bgra[rightIdx + 2];
                        bgra[rightIdx] = b0;
                        bgra[rightIdx + 1] = g0;
                        bgra[rightIdx + 2] = r0;
                    }
                }
            });
        }
    }

    // =========================================================================
    // 4. DIRECTSHOW FFMPEG RAW-BGRA CAMERA STREAM WORKER
    // =========================================================================
    public sealed class DirectShowCameraStreamWorker : IDisposable
    {
        private readonly string _ffmpegPath;
        private string _cameraName;
        private int _width;
        private int _height;
        private int _fps;
        private CancellationTokenSource? _cts;
        private Process? _proc;
        private byte[] _latestFrame = Array.Empty<byte>();
        private readonly object _frameLock = new();
        private bool _hasRealFrame = false;

        public bool IsOnline => _hasRealFrame;
        public bool HasFrame => _hasRealFrame;
        public string CameraName => _cameraName;
        public string DeviceName => _cameraName;
        public byte[] LatestFrameBuffer => _latestFrame;

        public DirectShowCameraStreamWorker(string cameraName, int width, int height, int fps)
            : this(ResolveDefaultFfmpegPath(), cameraName, width, height, fps)
        {
            Start();
        }

        private static string ResolveDefaultFfmpegPath()
        {
            string localTools = Path.Combine(AppDomain.CurrentDomain.BaseDirectory, "tools", "ffmpeg.exe");
            if (File.Exists(localTools)) return localTools;
            string desktopTools = @"C:\Users\konta\Desktop\Funkenwerk\tools\ffmpeg.exe";
            if (File.Exists(desktopTools)) return desktopTools;
            return "ffmpeg";
        }

        public (byte R, byte G, byte B) SampleColorAtNormalized(double normX, double normY)
        {
            int px = (int)Math.Clamp(normX * _width, 0, Math.Max(0, _width - 1));
            int py = (int)Math.Clamp(normY * _height, 0, Math.Max(0, _height - 1));
            lock (_frameLock)
            {
                if (!_hasRealFrame || _latestFrame.Length < _width * _height * 4)
                    return (0, 177, 64);

                int rSum = 0, gSum = 0, bSum = 0, count = 0;
                for (int dy = -2; dy <= 2; dy++)
                {
                    int y = Math.Clamp(py + dy, 0, _height - 1);
                    for (int dx = -2; dx <= 2; dx++)
                    {
                        int x = Math.Clamp(px + dx, 0, _width - 1);
                        int idx = (y * _width + x) * 4;
                        bSum += _latestFrame[idx];
                        gSum += _latestFrame[idx + 1];
                        rSum += _latestFrame[idx + 2];
                        count++;
                    }
                }
                return ((byte)(rSum / count), (byte)(gSum / count), (byte)(bSum / count));
            }
        }

        public DirectShowCameraStreamWorker(string ffmpegPath, string cameraName, int width, int height, int fps)
        {
            _ffmpegPath = ffmpegPath;
            _cameraName = cameraName;
            _width = width;
            _height = height;
            _fps = fps;
            _latestFrame = new byte[width * height * 4];
        }

        public void Start()
        {
            Stop();
            _cts = new CancellationTokenSource();
            var token = _cts.Token;
            Task.Run(() => CaptureLoopAsync(token), token);
        }

        public void RestartWith(string cameraName, int width, int height, int fps)
        {
            if (_cameraName == cameraName && _width == width && _height == height && _fps == fps && _cts != null)
                return;

            _cameraName = cameraName;
            _width = width;
            _height = height;
            _fps = fps;
            lock (_frameLock)
            {
                if (_latestFrame.Length != width * height * 4)
                    _latestFrame = new byte[width * height * 4];
            }
            Start();
        }

        public bool CopyLatestFrame(byte[] dest, int destW, int destH, out byte sampleR, out byte sampleG, out byte sampleB, int pickX = -1, int pickY = -1)
        {
            sampleR = 0; sampleG = 177; sampleB = 64;
            lock (_frameLock)
            {
                if (!_hasRealFrame || _latestFrame.Length != destW * destH * 4)
                    return false;

                Buffer.BlockCopy(_latestFrame, 0, dest, 0, dest.Length);

                if (pickX >= 0 && pickY >= 0 && pickX < destW && pickY < destH)
                {
                    int rSum = 0, gSum = 0, bSum = 0, count = 0;
                    for (int dy = -2; dy <= 2; dy++)
                    {
                        int py = Math.Clamp(pickY + dy, 0, destH - 1);
                        for (int dx = -2; dx <= 2; dx++)
                        {
                            int px = Math.Clamp(pickX + dx, 0, destW - 1);
                            int idx = (py * destW + px) * 4;
                            bSum += _latestFrame[idx];
                            gSum += _latestFrame[idx + 1];
                            rSum += _latestFrame[idx + 2];
                            count++;
                        }
                    }
                    sampleR = (byte)(rSum / count);
                    sampleG = (byte)(gSum / count);
                    sampleB = (byte)(bSum / count);
                }
                return true;
            }
        }

        private async Task CaptureLoopAsync(CancellationToken token)
        {
            int attemptMode = 0; // 0 = Native MJPEG, 1 = 720p MJPEG fast-scaled to target, 2 = Default raw DirectShow
            while (!token.IsCancellationRequested)
            {
                var sw = Stopwatch.StartNew();
                bool receivedFrameThisRun = false;
                try
                {
                    int w = _width;
                    int h = _height;
                    int fps = Math.Clamp(_fps, 15, 60);
                    int hwFps = Math.Min(fps, 30); // USB 2.0/3.0 webcams capture at 30fps MJPEG in hardware
                    int frameBytes = w * h * 4;
                    byte[] readBuf = new byte[frameBytes];

                    string safeCamName = FunkenwerkSecurityVault.SanitizeCliArgument(_cameraName);
                    string args;
                    if (attemptMode == 0)
                    {
                        args = $"-hide_banner -loglevel error -fflags nobuffer -flags low_delay -probesize 32 -analyzeduration 0 -f dshow -rtbufsize 128M -video_size {w}x{h} -framerate {hwFps} -vcodec mjpeg -i video=\"{safeCamName}\" -threads 4 -f rawvideo -pix_fmt bgra -s {w}x{h} -";
                    }
                    else if (attemptMode == 1)
                    {
                        args = $"-hide_banner -loglevel error -fflags nobuffer -flags low_delay -probesize 32 -analyzeduration 0 -f dshow -rtbufsize 128M -video_size 1280x720 -framerate 30 -vcodec mjpeg -i video=\"{safeCamName}\" -threads 4 -vf \"scale={w}:{h}:flags=fast_bilinear\" -f rawvideo -pix_fmt bgra -";
                    }
                    else
                    {
                        args = $"-hide_banner -loglevel error -fflags nobuffer -flags low_delay -f dshow -rtbufsize 128M -video_size {w}x{h} -framerate {hwFps} -i video=\"{safeCamName}\" -threads 4 -f rawvideo -pix_fmt bgra -s {w}x{h} -";
                    }

                    var psi = new ProcessStartInfo
                    {
                        FileName = _ffmpegPath,
                        Arguments = args,
                        UseShellExecute = false,
                        CreateNoWindow = true,
                        RedirectStandardOutput = true,
                        RedirectStandardError = true
                    };

                    using var proc = Process.Start(psi);
                    _proc = proc;
                    if (proc != null)
                    {
                        var stream = proc.StandardOutput.BaseStream;
                        while (!token.IsCancellationRequested && !proc.HasExited)
                        {
                            int offset = 0;
                            while (offset < frameBytes && !token.IsCancellationRequested)
                            {
                                int read = await stream.ReadAsync(readBuf.AsMemory(offset, frameBytes - offset), token);
                                if (read <= 0) break;
                                offset += read;
                            }

                            if (offset == frameBytes)
                            {
                                receivedFrameThisRun = true;
                                lock (_frameLock)
                                {
                                    if (_latestFrame.Length == frameBytes)
                                    {
                                        Buffer.BlockCopy(readBuf, 0, _latestFrame, 0, frameBytes);
                                        _hasRealFrame = true;
                                    }
                                }
                            }
                            else
                            {
                                break;
                            }
                        }
                    }
                }
                catch { }
                finally
                {
                    KillProcessSafe();
                }

                if (!token.IsCancellationRequested && !receivedFrameThisRun && sw.ElapsedMilliseconds < 2200)
                {
                    // Only cycle fallback mode if FFmpeg failed on its own (NEVER when cancelled by resolution switch!)
                    attemptMode = (attemptMode + 1) % 3;
                }

                if (!token.IsCancellationRequested)
                {
                    try { await Task.Delay(350, token); } catch { break; }
                }
            }
        }

        private void KillProcessSafe()
        {
            try
            {
                var p = _proc;
                _proc = null;
                if (p != null && !p.HasExited)
                {
                    p.Kill(true);
                }
            }
            catch { }
        }

        public void Stop()
        {
            try { _cts?.Cancel(); } catch { }
            KillProcessSafe();
            _cts = null;
        }

        public void Dispose() => Stop();
    }

    // =========================================================================
    // 5. MP4 VIDEO + AUDIO RECORDING ENGINE (WALL-CLOCK PACED RAW BGRA + DSHOW AUDIO)
    // =========================================================================
    public sealed class FunkenwerkVideoRecorder : IDisposable
    {
        private Process? _ffmpegProc;
        private Stream? _videoStdin;
        private Thread? _pacerThread;
        private readonly object _recLock = new();
        private readonly object _frameBufLock = new();
        private byte[] _latestFrame = Array.Empty<byte>();
        private bool _hasFirstFrame;
        private long _writtenFrames;
        private int _fps = 30;
        private double _speedMultiplier = 1.0;
        private volatile bool _isRecording;
        private volatile bool _isPaused;
        private DateTime _startTime;
        private TimeSpan _pausedAccumulated;
        private DateTime _pauseStart;
        private string _currentOutputPath = "";

        public bool IsRecording => _isRecording;
        public double SpeedMultiplier
        {
            get => _speedMultiplier;
            set => _speedMultiplier = Math.Clamp(value <= 0.05 ? 1.0 : value, 0.25, 10.0);
        }
        public bool IsPaused
        {
            get => _isPaused;
            set
            {
                if (_isPaused != value) TogglePause();
            }
        }
        public string CurrentOutputPath => _currentOutputPath;
        public string OutputPath => _currentOutputPath;

        public bool Start(string outputMp4Path, int width, int height, int fps, string micName, bool recordMic, double micVolume, bool highBitrate, CameraStudioConfig? fullCfg = null)
        {
            string ffmpegPath = Path.Combine(AppDomain.CurrentDomain.BaseDirectory, "tools", "ffmpeg.exe");
            if (!File.Exists(ffmpegPath))
            {
                string desktopTools = @"C:\Users\konta\Desktop\Funkenwerk\tools\ffmpeg.exe";
                ffmpegPath = File.Exists(desktopTools) ? desktopTools : "ffmpeg";
            }
            var cfg = new CameraStudioConfig
            {
                SelectedMicrophoneName = micName,
                MicMuted = !recordMic,
                MicVolume = (int)Math.Round(micVolume),
                RecordingMode = highBitrate ? "gaming_60" : (recordMic ? "standard" : "video_only"),
                RecordingSpeedMode = fullCfg?.RecordingSpeedMode ?? "1x",
                RecordingSpeedMultiplier = fullCfg?.RecordingSpeedMultiplier ?? 1.0,
                VoiceChangerEnabled = fullCfg?.VoiceChangerEnabled ?? false,
                VoicePreset = fullCfg?.VoicePreset ?? "none",
                VoicePitchSemitones = fullCfg?.VoicePitchSemitones ?? 0.0,
                VoiceFormantShift = fullCfg?.VoiceFormantShift ?? 0,
                VoiceRobotModulation = fullCfg?.VoiceRobotModulation ?? 0,
                VoiceReverbMix = fullCfg?.VoiceReverbMix ?? 0,
                VoiceSubBass = fullCfg?.VoiceSubBass ?? 0,
                VoiceNoiseGate = fullCfg?.VoiceNoiseGate ?? 15,
                ActiveMusicSignalPath = fullCfg?.ActiveMusicSignalPath ?? "",
                MusicSignalVolume = fullCfg?.MusicSignalVolume ?? 75,
                MusicSignalLoop = fullCfg?.MusicSignalLoop ?? true,
                SoundMuted = fullCfg?.SoundMuted ?? false
            };
            return StartRecording(ffmpegPath, outputMp4Path, width, height, fps, cfg);
        }

        public void WriteFrameBgra(byte[] bgra)
        {
            if (bgra != null) WriteFrame(bgra, bgra.Length);
        }

        public string Stop() => StopRecording();

        public TimeSpan Elapsed
        {
            get
            {
                if (!_isRecording) return TimeSpan.Zero;
                var now = _isPaused ? _pauseStart : DateTime.UtcNow;
                var span = (now - _startTime) - _pausedAccumulated;
                return span < TimeSpan.Zero ? TimeSpan.Zero : span;
            }
        }

        private static string AppendAudioTempoFilter(string baseAudioFilter, double speed)
        {
            double s = Math.Clamp(speed <= 0.05 ? 1.0 : speed, 0.25, 10.0);
            if (Math.Abs(s - 1.0) < 0.02) return baseAudioFilter;

            var parts = new List<string>();
            if (!string.IsNullOrWhiteSpace(baseAudioFilter))
                parts.Add(baseAudioFilter);

            double rem = s;
            while (rem > 2.0)
            {
                parts.Add("atempo=2.0");
                rem /= 2.0;
            }
            while (rem < 0.5)
            {
                parts.Add("atempo=0.5");
                rem /= 0.5;
            }
            parts.Add($"atempo={rem.ToString("0.000", CultureInfo.InvariantCulture)}");
            return string.Join(",", parts);
        }

        public bool StartRecording(string ffmpegPath, string outputMp4Path, int width, int height, int fps, CameraStudioConfig cfg)
        {
            lock (_recLock)
            {
                if (_isRecording) return false;

                try
                {
                    Directory.CreateDirectory(Path.GetDirectoryName(outputMp4Path)!);

                    _fps = Math.Clamp(fps, 15, 60);
                    _speedMultiplier = Math.Clamp(cfg.RecordingSpeedMultiplier <= 0.05 ? 1.0 : cfg.RecordingSpeedMultiplier, 0.25, 10.0);
                    lock (_frameBufLock)
                    {
                        _latestFrame = new byte[width * height * 4];
                        _hasFirstFrame = false;
                    }
                    _writtenFrames = 0;

                    bool includeMic = !cfg.MicMuted && cfg.RecordingMode != "video_only" && !string.IsNullOrWhiteSpace(cfg.SelectedMicrophoneName);
                    bool includeMusic = !cfg.SoundMuted && cfg.RecordingMode != "video_only" && !string.IsNullOrWhiteSpace(cfg.ActiveMusicSignalPath) && File.Exists(cfg.ActiveMusicSignalPath);
                    int crf = cfg.RecordingMode == "gaming_60" ? 17 : 21;
                    string volStr = Math.Clamp(cfg.MicVolume / 100.0, 0.0, 2.0).ToString("0.00", CultureInfo.InvariantCulture);
                    string musVolStr = Math.Clamp(cfg.MusicSignalVolume / 100.0, 0.0, 1.5).ToString("0.00", CultureInfo.InvariantCulture);

                    string safeMicName = FunkenwerkSecurityVault.SanitizeCliArgument(cfg.SelectedMicrophoneName);
                    string safeMusicPath = includeMusic ? FunkenwerkSecurityVault.SanitizeCliArgument(cfg.ActiveMusicSignalPath) : "";
                    string safeOutPath = FunkenwerkSecurityVault.SanitizeCliArgument(outputMp4Path);
                    string baseAudioFilter = FunkenwerkVoiceChangerEngine.BuildFfmpegAudioFilter(cfg, volStr);
                    string audioFilter = AppendAudioTempoFilter(baseAudioFilter, _speedMultiplier);
                    string musicFilter = AppendAudioTempoFilter($"volume={musVolStr}", _speedMultiplier);
                    string loopArg = cfg.MusicSignalLoop ? "-stream_loop -1 " : "";
                    string args;
                    if (includeMic && !string.IsNullOrEmpty(safeMicName) && includeMusic && !string.IsNullOrEmpty(safeMusicPath))
                    {
                        args = $"-y -hide_banner -loglevel error -f rawvideo -pix_fmt bgra -s {width}x{height} -r {_fps} -i - " +
                               $"-f dshow -rtbufsize 32M -i audio=\"{safeMicName}\" " +
                               $"{loopArg}-i \"{safeMusicPath}\" " +
                               $"-filter_complex \"[1:a]{audioFilter}[mic];[2:a]{musicFilter}[mus];[mic][mus]amix=inputs=2:duration=first:dropout_transition=0[aout]\" " +
                               $"-map 0:v -map \"[aout]\" -c:v libx264 -preset ultrafast -crf {crf} -pix_fmt yuv420p -c:a aac -b:a 160k -shortest -movflags +faststart \"{safeOutPath}\"";
                    }
                    else if (includeMic && !string.IsNullOrEmpty(safeMicName))
                    {
                        args = $"-y -hide_banner -loglevel error -f rawvideo -pix_fmt bgra -s {width}x{height} -r {_fps} -i - " +
                               $"-f dshow -rtbufsize 32M -i audio=\"{safeMicName}\" " +
                               $"-filter:a \"{audioFilter}\" -c:v libx264 -preset ultrafast -crf {crf} -pix_fmt yuv420p -c:a aac -b:a 160k -shortest -movflags +faststart \"{safeOutPath}\"";
                    }
                    else if (includeMusic && !string.IsNullOrEmpty(safeMusicPath))
                    {
                        args = $"-y -hide_banner -loglevel error -f rawvideo -pix_fmt bgra -s {width}x{height} -r {_fps} -i - " +
                               $"{loopArg}-i \"{safeMusicPath}\" " +
                               $"-map 0:v -map 1:a -filter:a \"{musicFilter}\" -c:v libx264 -preset ultrafast -crf {crf} -pix_fmt yuv420p -c:a aac -b:a 160k -shortest -movflags +faststart \"{safeOutPath}\"";
                    }
                    else
                    {
                        args = $"-y -hide_banner -loglevel error -f rawvideo -pix_fmt bgra -s {width}x{height} -r {_fps} -i - " +
                               $"-c:v libx264 -preset ultrafast -crf {crf} -pix_fmt yuv420p -movflags +faststart \"{safeOutPath}\"";
                    }

                    var psi = new ProcessStartInfo
                    {
                        FileName = ffmpegPath,
                        Arguments = args,
                        UseShellExecute = false,
                        CreateNoWindow = true,
                        RedirectStandardInput = true,
                        RedirectStandardError = true
                    };

                    _ffmpegProc = Process.Start(psi);
                    if (_ffmpegProc == null) return false;

                    // Check within 180ms if audio device caused an immediate exit; if so, fallback to video-only so recording ALWAYS works
                    Thread.Sleep(180);
                    if (_ffmpegProc.HasExited && (includeMic || includeMusic))
                    {
                        string fallbackArgs = $"-y -hide_banner -loglevel error -f rawvideo -pix_fmt bgra -s {width}x{height} -r {_fps} -i - " +
                                              $"-c:v libx264 -preset ultrafast -crf {crf} -pix_fmt yuv420p -movflags +faststart \"{outputMp4Path}\"";
                        psi.Arguments = fallbackArgs;
                        _ffmpegProc = Process.Start(psi);
                        if (_ffmpegProc == null) return false;
                    }

                    _videoStdin = _ffmpegProc.StandardInput.BaseStream;
                    _currentOutputPath = outputMp4Path;
                    _startTime = DateTime.UtcNow;
                    _pausedAccumulated = TimeSpan.Zero;
                    _isPaused = false;
                    _isRecording = true;

                    _pacerThread = new Thread(PacedWriteLoop)
                    {
                        IsBackground = true,
                        Name = "FunkenwerkVideoRecorderPacer",
                        Priority = ThreadPriority.AboveNormal
                    };
                    _pacerThread.Start();

                    return true;
                }
                catch
                {
                    StopRecording();
                    return false;
                }
            }
        }

        public void WriteFrame(byte[] bgra, int expectedLength)
        {
            if (!_isRecording || _isPaused || bgra == null || bgra.Length < expectedLength) return;
            lock (_frameBufLock)
            {
                if (_latestFrame.Length != expectedLength)
                    _latestFrame = new byte[expectedLength];
                Buffer.BlockCopy(bgra, 0, _latestFrame, 0, expectedLength);
                if (!_hasFirstFrame)
                {
                    _hasFirstFrame = true;
                    _startTime = DateTime.UtcNow;
                    _pausedAccumulated = TimeSpan.Zero;
                }
            }
        }

        private void PacedWriteLoop()
        {
            byte[] localFrame = Array.Empty<byte>();
            while (_isRecording)
            {
                if (_isPaused || !_hasFirstFrame)
                {
                    Thread.Sleep(6);
                    continue;
                }

                double elapsedSec = Elapsed.TotalSeconds;
                double speed = Math.Clamp(_speedMultiplier <= 0.05 ? 1.0 : _speedMultiplier, 0.25, 10.0);
                double effectiveVideoSec = elapsedSec / speed;
                long targetFrames = Math.Max(1, (long)Math.Floor(effectiveVideoSec * _fps) + 1);

                if (_writtenFrames >= targetFrames)
                {
                    Thread.Sleep(4);
                    continue;
                }

                int frameLen;
                lock (_frameBufLock)
                {
                    frameLen = _latestFrame.Length;
                    if (frameLen == 0)
                    {
                        Thread.Sleep(4);
                        continue;
                    }
                    if (localFrame.Length != frameLen)
                        localFrame = new byte[frameLen];
                    Buffer.BlockCopy(_latestFrame, 0, localFrame, 0, frameLen);
                }

                long framesToWrite = Math.Min(targetFrames - _writtenFrames, 4);
                for (long f = 0; f < framesToWrite && _isRecording && !_isPaused; f++)
                {
                    lock (_recLock)
                    {
                        if (!_isRecording || _videoStdin == null) break;
                        try
                        {
                            _videoStdin.Write(localFrame, 0, frameLen);
                            _writtenFrames++;
                        }
                        catch
                        {
                            break;
                        }
                    }
                }
            }
        }

        public void TogglePause()
        {
            lock (_recLock)
            {
                if (!_isRecording) return;
                if (!_isPaused)
                {
                    _isPaused = true;
                    _pauseStart = DateTime.UtcNow;
                }
                else
                {
                    _pausedAccumulated += (DateTime.UtcNow - _pauseStart);
                    _isPaused = false;
                }
            }
        }

        public string StopRecording()
        {
            string savedPath;
            Process? procToWait;
            Thread? pacerToJoin;
            lock (_recLock)
            {
                savedPath = _currentOutputPath;
                _isRecording = false;
                _isPaused = false;
                pacerToJoin = _pacerThread;
                _pacerThread = null;
            }

            if (pacerToJoin != null && pacerToJoin != Thread.CurrentThread)
            {
                try { pacerToJoin.Join(600); } catch { }
            }

            lock (_recLock)
            {
                try
                {
                    _videoStdin?.Flush();
                    _videoStdin?.Close();
                }
                catch { }
                _videoStdin = null;
                procToWait = _ffmpegProc;
                _ffmpegProc = null;
            }

            if (procToWait != null)
            {
                try
                {
                    if (!procToWait.WaitForExit(3500))
                    {
                        procToWait.Kill(true);
                    }
                }
                catch { }
                finally
                {
                    procToWait.Dispose();
                }
            }
            return savedPath;
        }

        public void Dispose() => StopRecording();
    }

    // =========================================================================
    // 6. REAL-TIME COMPOSITOR, CHROMA KEY, COLOR FILTERS & FUN EFFECTS ENGINE
    // =========================================================================
    public sealed class FunkenwerkFrameProcessor
    {
        private struct ConfettiParticle
        {
            public float X, Y, Vx, Vy, Size, Angle, VAngle;
            public byte R, G, B;
        }

        private readonly List<ConfettiParticle> _confetti = new();
        private readonly float[] _matrixDrops = new float[160];
        private readonly Random _rng = new();
        private readonly object _fxLock = new();

        // One-shot animation state
        private string _activeOneShot = "";
        private DateTime _oneShotStart = DateTime.MinValue;
        private double _oneShotDurationSec = 1.2;

        // Custom Chroma Background cache
        private string _loadedBgPath = "";
        private byte[]? _customBgBgra = null;
        private int _customBgW = 0, _customBgH = 0;

        public string ActiveOneShot => _activeOneShot;

        public FunkenwerkFrameProcessor()
        {
            for (int i = 0; i < _matrixDrops.Length; i++)
                _matrixDrops[i] = (float)(_rng.NextDouble() * 100.0);
        }

        public void TriggerOneShotEffect(string effectId)
        {
            lock (_fxLock)
            {
                _activeOneShot = effectId;
                _oneShotStart = DateTime.UtcNow;
                _oneShotDurationSec = effectId switch
                {
                    "spin_360" => 1.15,
                    "flip_3d" => 1.10,
                    "earthquake" => 1.60,
                    "glitch" => 1.20,
                    "confetti" => 3.20,
                    "paparazzi" => 1.80,
                    _ => 1.20
                };

                if (effectId == "confetti")
                {
                    _confetti.Clear();
                    byte[][] palette = new[]
                    {
                        new byte[] { 197, 160, 89 },  // Gold
                        new byte[] { 239, 68, 68 },   // Red
                        new byte[] { 52, 211, 153 },  // Emerald
                        new byte[] { 59, 130, 246 },  // Blue
                        new byte[] { 236, 72, 153 },  // Pink
                        new byte[] { 250, 204, 21 },  // Yellow
                        new byte[] { 168, 85, 247 }   // Purple
                    };
                    for (int i = 0; i < 130; i++)
                    {
                        var col = palette[_rng.Next(palette.Length)];
                        _confetti.Add(new ConfettiParticle
                        {
                            X = (float)_rng.NextDouble(),
                            Y = -0.05f - (float)(_rng.NextDouble() * 0.45),
                            Vx = (float)((_rng.NextDouble() - 0.5) * 0.008),
                            Vy = 0.009f + (float)(_rng.NextDouble() * 0.015),
                            Size = 6f + (float)(_rng.NextDouble() * 8f),
                            Angle = (float)(_rng.NextDouble() * 6.28),
                            VAngle = (float)((_rng.NextDouble() - 0.5) * 0.25),
                            R = col[0],
                            G = col[1],
                            B = col[2]
                        });
                    }
                }
            }
        }

        public void RenderProceduralStudioFeed(byte[] dest, int w, int h, double timeSec, string label)
        {
            Parallel.For(0, h, y =>
            {
                int row = y * w * 4;
                double ny = (double)y / h;
                for (int x = 0; x < w; x++)
                {
                    double nx = (double)x / w;
                    double dx = nx - 0.5;
                    double dy = ny - 0.5;
                    double dist = Math.Sqrt(dx * dx + dy * dy);
                    double wave = Math.Sin(dist * 18.0 - timeSec * 3.0) * 0.5 + 0.5;

                    byte r = (byte)Math.Clamp(18 + wave * 35 + (1.0 - dist) * 40, 0, 255);
                    byte g = (byte)Math.Clamp(22 + wave * 28 + (1.0 - dist) * 32, 0, 255);
                    byte b = (byte)Math.Clamp(34 + wave * 20 + (1.0 - dist) * 45, 0, 255);

                    int idx = row + x * 4;
                    dest[idx] = b;
                    dest[idx + 1] = g;
                    dest[idx + 2] = r;
                    dest[idx + 3] = 255;
                }
            });
        }

        private byte[]? _workBufA;
        private byte[]? _workBufB;

        public void ProcessAndComposite(byte[] primaryRaw, byte[]? secondaryRaw, byte[] outputBgra, int w, int h, CameraStudioConfig cfg)
        {
            int len = w * h * 4;
            if (_workBufA == null || _workBufA.Length != len) _workBufA = new byte[len];
            if (_workBufB == null || _workBufB.Length != len) _workBufB = new byte[len];
            bool hasSec = secondaryRaw != null && secondaryRaw.Length == len;
            double timeSec = (Environment.TickCount64 % 3600000) / 1000.0;
            ProcessAndCompositeFrame(primaryRaw, secondaryRaw ?? primaryRaw, hasSec, _workBufA, _workBufB, outputBgra, w, h, cfg, timeSec);
        }

        public void Trigger360Spin() => TriggerOneShotEffect("spin_360");
        public void Trigger3DFlipSpin() => TriggerOneShotEffect("flip_3d");
        public void TriggerEarthquake() => TriggerOneShotEffect("earthquake");
        public void TriggerGlitchBurst() => TriggerOneShotEffect("glitch");
        public void TriggerConfetti(int w = 1280, int h = 720) => TriggerOneShotEffect("confetti");
        public void TriggerPaparazzi() => TriggerOneShotEffect("paparazzi");

        public bool SetCustomBackgroundImage(string path, int w, int h)
        {
            _loadedBgPath = "";
            _customBgBgra = null;
            return File.Exists(path);
        }

        private byte[]? _workBufC;

        public void ProcessAndCompositeFrame(
            byte[] primaryRaw,
            byte[] secondaryRaw,
            bool hasSecondary,
            byte[] workBufA,
            byte[] workBufB,
            byte[] outputBgra,
            int w,
            int h,
            CameraStudioConfig cfg,
            double timeSec)
        {
            int len = w * h * 4;
            if (_workBufC == null || _workBufC.Length != len) _workBufC = new byte[len];

            // Step 1: Apply Zoom, Pan/Tilt (works even at 1.0x!), Mirror/Flip, Image Adjustments & YCbCr Studio Chroma Key -> workBufA
            ApplyZoomAndAdjustments(primaryRaw, secondaryRaw, hasSecondary, workBufA, w, h, cfg, timeSec);

            // Step 1b: Apply TikTok & Beauty Filter (Edge-Preserving Bilateral Skin Smoothing + Face Radiance + Beauty LUT)
            byte[] currentSrc = workBufA;
            if (cfg.BeautyFilter != "none" || cfg.BeautySkinSmooth > 0 || cfg.BeautyFaceBright > 0 || cfg.BeautySoftBloom > 0)
            {
                ApplyTikTokBeautyFilter(currentSrc, _workBufC, w, h, cfg);
                currentSrc = _workBufC;
            }

            // Step 2: Apply Sharpness only if increased above default (> 25)
            if (cfg.Sharpness > 25)
            {
                byte[] sharpTarget = ReferenceEquals(currentSrc, workBufA) ? workBufB : workBufA;
                ApplyFastSharpen(currentSrc, sharpTarget, w, h, (cfg.Sharpness - 20) / 80.0);
                currentSrc = sharpTarget;
            }

            // Step 3: Screen Splitting / Scene Composition (Single, Interactive Mouse PiP, Links/Rechts, Oben/Unten, Fenster Teilen)
            bool isSingleNoSplit = (cfg.SplitMode == null || cfg.SplitMode == "single") && !cfg.SwapSources;
            byte[] sceneBuf;
            if (isSingleNoSplit)
            {
                sceneBuf = currentSrc;
            }
            else
            {
                sceneBuf = ReferenceEquals(currentSrc, workBufA) ? workBufB : workBufA;
                ComposeSplitScene(currentSrc, secondaryRaw, hasSecondary, sceneBuf, w, h, cfg, timeSec);
            }

            // Step 4: Apply Fun Distortion Filter & Spin / One-Shot Geometric Transform -> outputBgra
            ApplyGeometricEffectsAndDistortions(sceneBuf, outputBgra, w, h, cfg, timeSec);

            // Step 5: Apply Color Filters & 60FPS Fluid Overlays (Neon Pulse, RGB Wave, Hologram HUD, Fire Aura, Disco, VHS, Matrix, etc.)
            ApplyColorFiltersAndOverlays(outputBgra, w, h, cfg, timeSec);
        }

        private void ApplyZoomAndAdjustments(
            byte[] src,
            byte[] secondaryRaw,
            bool hasSecondary,
            byte[] dst,
            int w,
            int h,
            CameraStudioConfig cfg,
            double timeSec)
        {
            // Automatic overscan when PanX or PanY is non-zero so Schwenken & Neigen ALWAYS work even at 1.0x zoom!
            double panMagnitude = Math.Max(Math.Abs(cfg.PanX), Math.Abs(cfg.PanY)) / 100.0;
            double minZoomForPan = 1.0 + panMagnitude * 0.42;
            double zoom = Math.Clamp(Math.Max(cfg.ZoomLevel, minZoomForPan), 1.0, 4.0);

            if (cfg.DiscoMode || cfg.RgbWaveMode)
            {
                zoom *= 1.0 + 0.035 * Math.Abs(Math.Sin(timeSec * 7.5));
            }

            double cropW = w / zoom;
            double cropH = h / zoom;
            double maxOffsetX = (w - cropW) * 0.5;
            double maxOffsetY = (h - cropH) * 0.5;
            double startX = maxOffsetX + (cfg.PanX / 100.0) * maxOffsetX;
            double startY = maxOffsetY + (cfg.PanY / 100.0) * maxOffsetY;

            // Precompute Brightness, Contrast & Warmth Look-Up Tables (256 bytes each)
            byte[] lutR = new byte[256];
            byte[] lutG = new byte[256];
            byte[] lutB = new byte[256];

            double brightOffset = cfg.Brightness * 1.6;
            double contrastFactor = Math.Pow((cfg.Contrast + 100.0) / 100.0, 2.0);
            double warmthR = cfg.Warmth * 0.65;
            double warmthG = cfg.Warmth * 0.15;
            double warmthB = -cfg.Warmth * 0.75;

            // Optional Gray-World Auto White Balance correction
            double awbR = 1.0, awbG = 1.0, awbB = 1.0;
            if (cfg.AutoWhiteBalance)
            {
                long sumR = 0, sumG = 0, sumB = 0;
                int step = Math.Max(4, (w * h) / 2000) * 4;
                int samples = 0;
                for (int i = 0; i < src.Length - 4; i += step)
                {
                    sumB += src[i];
                    sumG += src[i + 1];
                    sumR += src[i + 2];
                    samples++;
                }
                if (samples > 0)
                {
                    double avgR = Math.Max(20.0, (double)sumR / samples);
                    double avgG = Math.Max(20.0, (double)sumG / samples);
                    double avgB = Math.Max(20.0, (double)sumB / samples);
                    double avgGray = (avgR + avgG + avgB) / 3.0;
                    awbR = 1.0 + ((avgGray / avgR) - 1.0) * 0.35;
                    awbG = 1.0 + ((avgGray / avgG) - 1.0) * 0.35;
                    awbB = 1.0 + ((avgGray / avgB) - 1.0) * 0.35;
                }
            }

            for (int i = 0; i < 256; i++)
            {
                double v = ((i - 128.0) * contrastFactor) + 128.0 + brightOffset;
                lutR[i] = (byte)Math.Clamp((v + warmthR) * awbR, 0, 255);
                lutG[i] = (byte)Math.Clamp((v + warmthG) * awbG, 0, 255);
                lutB[i] = (byte)Math.Clamp((v + warmthB) * awbB, 0, 255);
            }

            bool mirrorX = cfg.MirrorHorizontal;
            bool flipY = cfg.FlipVertical;
            bool chromaOn = cfg.ChromaKeyEnabled;

            if (!chromaOn && zoom <= 1.001 && !mirrorX && !flipY &&
                cfg.Brightness == 0 && cfg.Contrast == 0 && cfg.Warmth == 0 &&
                Math.Abs(awbR - 1.0) < 0.03 && Math.Abs(awbG - 1.0) < 0.03 && Math.Abs(awbB - 1.0) < 0.03)
            {
                Buffer.BlockCopy(src, 0, dst, 0, w * h * 4);
                return;
            }

            // Precompute horizontal source byte offsets once per frame (eliminates 2M float divisions at 1080p!)
            int[] srcXByteOffset = new int[w];
            for (int x = 0; x < w; x++)
            {
                double sx = startX + ((mirrorX ? (w - 1 - x) : x) / (double)w) * cropW;
                srcXByteOffset[x] = Math.Clamp((int)sx, 0, w - 1) * 4;
            }

            int keyR = cfg.ChromaKeyR, keyG = cfg.ChromaKeyG, keyB = cfg.ChromaKeyB;

            // OBS-Style YCbCr Chrominance Keying Constants
            double keyY  = 0.299000 * keyR + 0.587000 * keyG + 0.114000 * keyB;
            double keyCb = 128.0 - 0.168736 * keyR - 0.331264 * keyG + 0.500000 * keyB;
            double keyCr = 128.0 + 0.500000 * keyR - 0.418688 * keyG - 0.081312 * keyB;
            double innerTol = Math.Max(4.0, cfg.ChromaTolerance * 1.12);
            double outerTol = innerTol + Math.Max(3.0, cfg.ChromaSmoothness * 1.35);
            bool isGreenKey = keyG > keyR && keyG > keyB;
            bool isBlueKey = keyB > keyR && keyG <= keyB;

            string chromaBgMode = cfg.ChromaBgMode;
            if (chromaOn && (chromaBgMode == "custom_image" || chromaBgMode == "image") && !string.IsNullOrWhiteSpace(cfg.ChromaCustomImagePath))
            {
                EnsureCustomChromaBgLoaded(cfg.ChromaCustomImagePath, w, h);
            }

            byte[]? customBg = _customBgBgra;

            Parallel.For(0, h, y =>
            {
                int dstRow = y * w * 4;
                double sy = startY + ((flipY ? (h - 1 - y) : y) / (double)h) * cropH;
                int iy = Math.Clamp((int)sy, 0, h - 1);
                int srcRow = iy * w * 4;

                if (!chromaOn)
                {
                    for (int x = 0; x < w; x++)
                    {
                        int sIdx = srcRow + srcXByteOffset[x];
                        int dIdx = dstRow + x * 4;
                        dst[dIdx]     = lutB[src[sIdx]];
                        dst[dIdx + 1] = lutG[src[sIdx + 1]];
                        dst[dIdx + 2] = lutR[src[sIdx + 2]];
                        dst[dIdx + 3] = 255;
                    }
                    return;
                }

                for (int x = 0; x < w; x++)
                {
                    int sIdx = srcRow + srcXByteOffset[x];

                    int rawB = src[sIdx];
                    int rawG = src[sIdx + 1];
                    int rawR = src[sIdx + 2];

                    byte r = lutR[rawR];
                    byte g = lutG[rawG];
                    byte b = lutB[rawB];

                    if (chromaOn)
                    {
                        double pY  = 0.299000 * rawR + 0.587000 * rawG + 0.114000 * rawB;
                        double pCb = 128.0 - 0.168736 * rawR - 0.331264 * rawG + 0.500000 * rawB;
                        double pCr = 128.0 + 0.500000 * rawR - 0.418688 * rawG - 0.081312 * rawB;

                        double dCb = pCb - keyCb;
                        double dCr = pCr - keyCr;
                        double dY  = (pY - keyY) * 0.20; // Low luma weight makes shadows/hotspots on greenscreen key cleanly!
                        double chromaDist = Math.Sqrt(dCb * dCb + dCr * dCr + dY * dY);

                        if (chromaDist < outerTol)
                        {
                            byte bgR, bgG, bgB;
                            GetChromaBackgroundPixel(chromaBgMode, src, secondaryRaw, hasSecondary, customBg, x, y, w, h, timeSec, out bgR, out bgG, out bgB);

                            if (chromaDist <= innerTol)
                            {
                                r = bgR;
                                g = bgG;
                                b = bgB;
                            }
                            else
                            {
                                double t = (chromaDist - innerTol) / (outerTol - innerTol);
                                double alpha = t * t * (3.0 - 2.0 * t); // Smoothstep feathering

                                // Color spill suppression on foreground edges
                                byte cleanR = r, cleanG = g, cleanB = b;
                                if (isGreenKey && cleanG > ((cleanR + cleanB) >> 1))
                                {
                                    cleanG = (byte)((cleanR + cleanB) >> 1);
                                }
                                else if (isBlueKey && cleanB > ((cleanR + cleanG) >> 1))
                                {
                                    cleanB = (byte)((cleanR + cleanG) >> 1);
                                }

                                r = (byte)(bgR * (1.0 - alpha) + cleanR * alpha);
                                g = (byte)(bgG * (1.0 - alpha) + cleanG * alpha);
                                b = (byte)(bgB * (1.0 - alpha) + cleanB * alpha);
                            }
                        }
                        else if (chromaDist < outerTol + 14.0)
                        {
                            // Subtle spill suppression on hair/shoulder pixels near the key boundary
                            if (isGreenKey && g > Math.Max(r, b) + 12)
                            {
                                g = (byte)((g + Math.Max(r, b)) >> 1);
                            }
                        }
                    }

                    int dIdx = dstRow + x * 4;
                    dst[dIdx] = b;
                    dst[dIdx + 1] = g;
                    dst[dIdx + 2] = r;
                    dst[dIdx + 3] = 255;
                }
            });
        }

        private static void ApplyTikTokBeautyFilter(byte[] src, byte[] dst, int w, int h, CameraStudioConfig cfg)
        {
            string preset = cfg.BeautyFilter ?? "none";
            double smooth = cfg.BeautySkinSmooth / 100.0;
            double bright = cfg.BeautyFaceBright / 100.0;
            double bloom = cfg.BeautySoftBloom / 100.0;

            // Apply preset signature boosts if a preset is active
            double tintR = 1.0, tintG = 1.0, tintB = 1.0;
            switch (preset)
            {
                case "tiktok_glow":
                    smooth = Math.Max(smooth, 0.65);
                    bright = Math.Max(bright, 0.38);
                    bloom = Math.Max(bloom, 0.35);
                    tintR = 1.05; tintG = 1.01; tintB = 0.98;
                    break;
                case "porcelain":
                    smooth = Math.Max(smooth, 0.78);
                    bright = Math.Max(bright, 0.48);
                    bloom = Math.Max(bloom, 0.25);
                    tintR = 1.02; tintG = 1.03; tintB = 1.06;
                    break;
                case "golden_hour":
                    smooth = Math.Max(smooth, 0.55);
                    bright = Math.Max(bright, 0.32);
                    bloom = Math.Max(bloom, 0.40);
                    tintR = 1.10; tintG = 1.03; tintB = 0.90;
                    break;
                case "soft_peach":
                case "soft_dream":
                    smooth = Math.Max(smooth, 0.68);
                    bright = Math.Max(bright, 0.40);
                    bloom = Math.Max(bloom, 0.42);
                    tintR = 1.08; tintG = 0.99; tintB = 1.02;
                    break;
                case "studio_hd":
                    smooth = Math.Max(smooth, 0.48);
                    bright = Math.Max(bright, 0.25);
                    bloom = Math.Max(bloom, 0.18);
                    tintR = 1.02; tintG = 1.02; tintB = 1.02;
                    break;
                case "cyber_doll":
                case "cyber_glam":
                    smooth = Math.Max(smooth, 0.85);
                    bright = Math.Max(bright, 0.42);
                    bloom = Math.Max(bloom, 0.48);
                    tintR = 1.07; tintG = 0.97; tintB = 1.09;
                    break;
            }

            int stride = w * 4;
            int edgeThresh = (int)(18 + smooth * 26);

            Parallel.For(0, h, y =>
            {
                int row = y * stride;
                int yUp1 = Math.Max(0, y - 2) * stride;
                int yDn1 = Math.Min(h - 1, y + 2) * stride;
                int yUp2 = Math.Max(0, y - 5) * stride;
                int yDn2 = Math.Min(h - 1, y + 5) * stride;

                for (int x = 0; x < w; x++)
                {
                    int idx = row + x * 4;
                    int b0 = src[idx];
                    int g0 = src[idx + 1];
                    int r0 = src[idx + 2];

                    // YCbCr skin-tone detection (human skin clusters around Cb in [77..128], Cr in [132..178])
                    int yVal = (r0 * 77 + g0 * 150 + b0 * 29) >> 8;
                    int cb = 128 + ((-r0 * 43 - g0 * 85 + b0 * 128) >> 8);
                    int cr = 128 + ((r0 * 128 - g0 * 107 - b0 * 21) >> 8);

                    bool isSkin = yVal > 35 && cb >= 74 && cb <= 132 && cr >= 128 && cr <= 182;

                    double rOut = r0;
                    double gOut = g0;
                    double bOut = b0;

                    if (isSkin && smooth > 0.02)
                    {
                        // Fast 9-tap bilateral surface blur: smooths skin pores & blemishes while preserving sharp eyes/lips/hair!
                        int xL1 = Math.Max(0, x - 2) * 4;
                        int xR1 = Math.Min(w - 1, x + 2) * 4;
                        int xL2 = Math.Max(0, x - 5) * 4;
                        int xR2 = Math.Min(w - 1, x + 5) * 4;
                        int xC = x * 4;

                        int sumR = r0 * 4, sumG = g0 * 4, sumB = b0 * 4, wSum = 4;

                        void AccumulateTap(int tapIdx, int weight)
                        {
                            int tb = src[tapIdx];
                            int tg = src[tapIdx + 1];
                            int tr = src[tapIdx + 2];
                            int diff = Math.Abs(tr - r0) + Math.Abs(tg - g0) + Math.Abs(tb - b0);
                            if (diff < edgeThresh * 3)
                            {
                                sumR += tr * weight;
                                sumG += tg * weight;
                                sumB += tb * weight;
                                wSum += weight;
                            }
                        }

                        AccumulateTap(row + xL1, 2);
                        AccumulateTap(row + xR1, 2);
                        AccumulateTap(yUp1 + xC, 2);
                        AccumulateTap(yDn1 + xC, 2);
                        AccumulateTap(yUp2 + xL2, 1);
                        AccumulateTap(yUp2 + xR2, 1);
                        AccumulateTap(yDn2 + xL2, 1);
                        AccumulateTap(yDn2 + xR2, 1);

                        double blurR = (double)sumR / wSum;
                        double blurG = (double)sumG / wSum;
                        double blurB = (double)sumB / wSum;

                        rOut = r0 * (1.0 - smooth) + blurR * smooth;
                        gOut = g0 * (1.0 - smooth) + blurG * smooth;
                        bOut = b0 * (1.0 - smooth) + blurB * smooth;

                        // Soft skin radiance lift (TikTok Face Brightening)
                        if (bright > 0.01)
                        {
                            double lift = bright * 28.0 * (1.0 - (yVal / 255.0) * 0.45);
                            rOut += lift * 1.08;
                            gOut += lift * 0.98;
                            bOut += lift * 0.95;
                        }
                    }
                    else if (bloom > 0.02 && yVal > 165)
                    {
                        // Soft glamour highlight bloom on bright areas
                        double glow = (yVal - 165) * bloom * 0.35;
                        rOut += glow;
                        gOut += glow;
                        bOut += glow;
                    }

                    dst[idx]     = (byte)Math.Clamp(bOut * tintB, 0, 255);
                    dst[idx + 1] = (byte)Math.Clamp(gOut * tintG, 0, 255);
                    dst[idx + 2] = (byte)Math.Clamp(rOut * tintR, 0, 255);
                    dst[idx + 3] = 255;
                }
            });
        }

        private void GetChromaBackgroundPixel(
            string mode,
            byte[] primarySrc,
            byte[] secondaryRaw,
            bool hasSecondary,
            byte[]? customBg,
            int x,
            int y,
            int w,
            int h,
            double timeSec,
            out byte r,
            out byte g,
            out byte b)
        {
            if ((mode == "secondary_source" || mode == "secondary") && hasSecondary && secondaryRaw.Length >= w * h * 4)
            {
                int idx = (y * w + x) * 4;
                b = secondaryRaw[idx];
                g = secondaryRaw[idx + 1];
                r = secondaryRaw[idx + 2];
                return;
            }
            if ((mode == "custom_image" || mode == "image") && customBg != null && customBg.Length >= w * h * 4)
            {
                int idx = (y * w + x) * 4;
                b = customBg[idx];
                g = customBg[idx + 1];
                r = customBg[idx + 2];
                return;
            }
            if (mode == "blur")
            {
                int rSum = 0, gSum = 0, bSum = 0;
                int[] offsets = { -18, -6, 6, 18 };
                for (int oy = 0; oy < 4; oy++)
                {
                    int py = Math.Clamp(y + offsets[oy], 0, h - 1) * w * 4;
                    for (int ox = 0; ox < 4; ox++)
                    {
                        int px = Math.Clamp(x + offsets[ox], 0, w - 1) * 4;
                        bSum += primarySrc[py + px];
                        gSum += primarySrc[py + px + 1];
                        rSum += primarySrc[py + px + 2];
                    }
                }
                r = (byte)(rSum >> 4);
                g = (byte)(gSum >> 4);
                b = (byte)(bSum >> 4);
                return;
            }
            if (mode == "gold_lounge" || mode == "studio_gold")
            {
                double nx = (double)x / w;
                double ny = (double)y / h;
                double glow = Math.Sin(nx * 6.0 + timeSec * 1.2) * Math.Cos(ny * 5.0 - timeSec * 0.9) * 0.5 + 0.5;
                r = (byte)Math.Clamp(28 + glow * 115 + (1.0 - ny) * 35, 0, 255);
                g = (byte)Math.Clamp(22 + glow * 85 + (1.0 - ny) * 25, 0, 255);
                b = (byte)Math.Clamp(16 + glow * 35, 0, 255);
                return;
            }

            // Default: "cyber_studio" / "studio_cyber" (Neon Cyber Studio Grid & Aurora)
            double cx = (double)x / w;
            double cy = (double)y / h;
            double aurora = Math.Sin(cx * 8.0 + timeSec * 1.8) * 0.5 + 0.5;
            r = (byte)Math.Clamp(16 + aurora * 65 + cy * 45, 0, 255);
            g = (byte)Math.Clamp(24 + (1.0 - aurora) * 80 + (1.0 - cy) * 30, 0, 255);
            b = (byte)Math.Clamp(55 + aurora * 110 + cy * 60, 0, 255);
        }

        private void EnsureCustomChromaBgLoaded(string path, int w, int h)
        {
            if (_loadedBgPath == path && _customBgW == w && _customBgH == h && _customBgBgra != null)
                return;

            try
            {
                if (!File.Exists(path)) return;
                var bmp = new BitmapImage();
                bmp.BeginInit();
                bmp.UriSource = new Uri(path, UriKind.Absolute);
                bmp.DecodePixelWidth = w;
                bmp.DecodePixelHeight = h;
                bmp.CacheOption = BitmapCacheOption.OnLoad;
                bmp.EndInit();

                var converted = new FormatConvertedBitmap(bmp, PixelFormats.Bgra32, null, 0);
                var scaled = new TransformedBitmap(converted, new ScaleTransform((double)w / converted.PixelWidth, (double)h / converted.PixelHeight));
                byte[] buf = new byte[w * h * 4];
                scaled.CopyPixels(buf, w * 4, 0);

                _customBgBgra = buf;
                _loadedBgPath = path;
                _customBgW = w;
                _customBgH = h;
            }
            catch { }
        }

        private static void ApplyFastSharpen(byte[] src, byte[] dst, int w, int h, double strength)
        {
            Buffer.BlockCopy(src, 0, dst, 0, w * 4);
            Buffer.BlockCopy(src, (h - 1) * w * 4, dst, (h - 1) * w * 4, w * 4);

            int sFixed = (int)(strength * 256);
            int stride = w * 4;

            Parallel.For(1, h - 1, y =>
            {
                int row = y * stride;
                for (int x = 1; x < w - 1; x++)
                {
                    int idx = row + x * 4;
                    for (int c = 0; c < 3; c++)
                    {
                        int center = src[idx + c];
                        int lap = (center << 2) - src[idx - 4 + c] - src[idx + 4 + c] - src[idx - stride + c] - src[idx + stride + c];
                        int val = center + ((lap * sFixed) >> 8);
                        dst[idx + c] = (byte)Math.Clamp(val, 0, 255);
                    }
                    dst[idx + 3] = 255;
                }
            });
        }

        private static void ComposeSplitScene(
            byte[] primary,
            byte[] secondary,
            bool hasSecondary,
            byte[] dst,
            int w,
            int h,
            CameraStudioConfig cfg,
            double timeSec)
        {
            string mode = cfg.SplitMode ?? "single";
            byte[] srcA = (cfg.SwapSources && hasSecondary) ? secondary : primary;
            byte[] srcB = (cfg.SwapSources && hasSecondary) ? primary : (hasSecondary ? secondary : primary);

            if (mode == "single")
            {
                Buffer.BlockCopy(srcA, 0, dst, 0, w * h * 4);
                return;
            }

            if (mode == "screenshare_only" || mode == "screen_only")
            {
                // Pure 1x Screen/Window Share WITHOUT any second PiP overlay window
                byte[] mainCanvas = (!cfg.SwapSources && hasSecondary) ? secondary : primary;
                Buffer.BlockCopy(mainCanvas, 0, dst, 0, w * h * 4);
                return;
            }

            if (mode == "window_share" || mode == "windowshare")
            {
                // Gaming / Window Share mode: Secondary (Window/Desktop) is the full 16:9 canvas, Primary (Webcam) is Interactive PiP over it!
                byte[] mainCanvas = (!cfg.SwapSources && hasSecondary) ? secondary : primary;
                byte[] camOverlay = (!cfg.SwapSources && hasSecondary) ? primary : secondary;
                Buffer.BlockCopy(mainCanvas, 0, dst, 0, w * h * 4);
                if (cfg.ShowPipOverlay)
                {
                    DrawInteractivePipOverlay(dst, camOverlay, w, h, cfg);
                }
                return;
            }

            if (mode == "pip")
            {
                Buffer.BlockCopy(srcA, 0, dst, 0, w * h * 4);
                if (cfg.ShowPipOverlay)
                {
                    DrawInteractivePipOverlay(dst, srcB, w, h, cfg);
                }
                return;
            }

            if (mode == "split_lr" || mode == "sidebyside")
            {
                int halfW = w / 2;
                int cropStartX = w / 4; // Center-crop camera feed into its half, or scale secondary window proportionally
                bool secIsWindow = cfg.SecondarySourceType == "window" || cfg.SecondarySourceType == "desktop";

                Parallel.For(0, h, y =>
                {
                    int row = y * w * 4;
                    for (int x = 0; x < halfW; x++)
                    {
                        int sIdxL = row + (cropStartX + x) * 4;
                        int dIdxL = row + x * 4;
                        dst[dIdxL] = srcA[sIdxL];
                        dst[dIdxL + 1] = srcA[sIdxL + 1];
                        dst[dIdxL + 2] = srcA[sIdxL + 2];
                        dst[dIdxL + 3] = 255;
                    }
                    for (int x = halfW; x < w; x++)
                    {
                        int sx;
                        if (secIsWindow && !cfg.SwapSources)
                        {
                            // Map full width of captured screen/window into right half without cutting edges
                            sx = ((x - halfW) * w) / halfW;
                        }
                        else
                        {
                            sx = cropStartX + (x - halfW);
                            if (cfg.SecondarySourceType == "mirror_compare")
                                sx = w - 1 - sx;
                        }
                        sx = Math.Clamp(sx, 0, w - 1);
                        int sIdxR = row + sx * 4;
                        int dIdxR = row + x * 4;
                        dst[dIdxR] = srcB[sIdxR];
                        dst[dIdxR + 1] = srcB[sIdxR + 1];
                        dst[dIdxR + 2] = srcB[sIdxR + 2];
                        dst[dIdxR + 3] = 255;
                    }
                    // Gold divider line (4px)
                    for (int dx = -2; dx <= 1; dx++)
                    {
                        int divX = Math.Clamp(halfW + dx, 0, w - 1);
                        int dIdx = row + divX * 4;
                        dst[dIdx] = 89;      // B
                        dst[dIdx + 1] = 160; // G
                        dst[dIdx + 2] = 197; // R (#C5A059)
                        dst[dIdx + 3] = 255;
                    }
                });
                return;
            }

            if (mode == "split_tb" || mode == "topbottom")
            {
                int halfH = h / 2;
                int cropStartY = h / 4;
                Parallel.For(0, h, y =>
                {
                    int dstRow = y * w * 4;
                    if (y < halfH)
                    {
                        int srcRow = (cropStartY + y) * w * 4;
                        Buffer.BlockCopy(srcA, srcRow, dst, dstRow, w * 4);
                    }
                    else
                    {
                        int srcRow = Math.Clamp(cropStartY + (y - halfH), 0, h - 1) * w * 4;
                        Buffer.BlockCopy(srcB, srcRow, dst, dstRow, w * 4);
                    }
                    if (Math.Abs(y - halfH) <= 1)
                    {
                        for (int x = 0; x < w; x++)
                        {
                            int idx = dstRow + x * 4;
                            dst[idx] = 89;
                            dst[idx + 1] = 160;
                            dst[idx + 2] = 197;
                            dst[idx + 3] = 255;
                        }
                    }
                });
                return;
            }

            Buffer.BlockCopy(srcA, 0, dst, 0, w * h * 4);
        }

        private static void DrawInteractivePipOverlay(byte[] canvas, byte[] pipSrc, int w, int h, CameraStudioConfig cfg)
        {
            double normW = Math.Clamp(cfg.PipNormalizedW, 0.12, 0.88);
            double normH = Math.Clamp(cfg.PipNormalizedH, 0.12, 0.88);
            double normX = Math.Clamp(cfg.PipNormalizedX, 0.0, 1.0 - normW);
            double normY = Math.Clamp(cfg.PipNormalizedY, 0.0, 1.0 - normH);

            int pipW = Math.Max(64, (int)Math.Round(normW * w));
            int pipH = Math.Max(48, (int)Math.Round(normH * h));
            int startX = Math.Clamp((int)Math.Round(normX * w), 0, w - pipW);
            int startY = Math.Clamp((int)Math.Round(normY * h), 0, h - pipH);

            // Scissors Crop bounds (0.0 .. 0.42 on each edge)
            double cropL = Math.Clamp(cfg.PipCropLeft, 0.0, 0.42);
            double cropR = Math.Clamp(cfg.PipCropRight, 0.0, 0.42);
            double cropT = Math.Clamp(cfg.PipCropTop, 0.0, 0.42);
            double cropB = Math.Clamp(cfg.PipCropBottom, 0.0, 0.42);
            double activeSrcW = Math.Max(0.15, 1.0 - cropL - cropR);
            double activeSrcH = Math.Max(0.15, 1.0 - cropT - cropB);

            string shape = cfg.PipShape ?? "rounded";
            bool pipChroma = cfg.PipChromaKeyEnabled;
            int keyR = cfg.ChromaKeyR, keyG = cfg.ChromaKeyG, keyB = cfg.ChromaKeyB;
            double keyCb = 128.0 - 0.168736 * keyR - 0.331264 * keyG + 0.500000 * keyB;
            double keyCr = 128.0 + 0.500000 * keyR - 0.418688 * keyG - 0.081312 * keyB;
            double innerTol = Math.Max(4.0, cfg.ChromaTolerance * 1.15);
            double outerTol = innerTol + Math.Max(3.0, cfg.ChromaSmoothness * 1.35);

            double halfW = pipW * 0.5;
            double halfH = pipH * 0.5;
            double cornerRad = Math.Min(halfW, halfH) * 0.22;
            double hexSlantLen = Math.Sqrt((0.5 * halfW) * (0.5 * halfW) + halfH * halfH);

            Parallel.For(0, pipH, py =>
            {
                int dstY = startY + py;
                if (dstY < 0 || dstY >= h) return;
                int dstRow = dstY * w * 4;

                for (int px = 0; px < pipW; px++)
                {
                    int dstX = startX + px;
                    if (dstX < 0 || dstX >= w) continue;

                    // Compute exact pixel distance to shape boundary for mask & uniform anti-aliased border
                    double distToEdge = 100.0;
                    if (shape == "circle")
                    {
                        double nx = (px - halfW) / halfW;
                        double ny = (py - halfH) / halfH;
                        double rad = Math.Sqrt(nx * nx + ny * ny);
                        if (rad > 1.0) continue;
                        double grad = Math.Sqrt((nx / halfW) * (nx / halfW) + (ny / halfH) * (ny / halfH));
                        distToEdge = grad > 1e-5 ? (1.0 - rad) / grad : Math.Min(halfW, halfH);
                    }
                    else if (shape == "hexagon")
                    {
                        // 6-cornered Hexagon with vertices at (0, 0.5H), (0.25W, 0), (0.75W, 0), (W, 0.5H), (0.75W, H), (0.25W, H)
                        double qx = Math.Abs(px - halfW);
                        double qy = Math.Abs(py - halfH);
                        double distTopBot = halfH - qy;
                        double distSlant = (halfW * halfH - (qx * halfH + qy * 0.5 * halfW)) / hexSlantLen;
                        if (distTopBot < 0.0 || distSlant < 0.0) continue;
                        distToEdge = Math.Min(distTopBot, distSlant);
                    }
                    else if (shape == "rounded")
                    {
                        double qx = Math.Abs(px - halfW) - (halfW - cornerRad);
                        double qy = Math.Abs(py - halfH) - (halfH - cornerRad);
                        double outDist = Math.Sqrt(Math.Max(qx, 0.0) * Math.Max(qx, 0.0) + Math.Max(qy, 0.0) * Math.Max(qy, 0.0))
                                         + Math.Min(Math.Max(qx, qy), 0.0) - cornerRad;
                        if (outDist > 0.0) continue;
                        distToEdge = -outDist;
                    }
                    else // "rect"
                    {
                        distToEdge = Math.Min(Math.Min(px, pipW - 1 - px), Math.Min(py, pipH - 1 - py));
                    }

                    // Map (px, py) through Scissors Crop rectangle into pipSrc
                    double u = cropL + ((double)px / pipW) * activeSrcW;
                    double v = cropT + ((double)py / pipH) * activeSrcH;
                    int srcX = Math.Clamp((int)(u * w), 0, w - 1);
                    int srcY = Math.Clamp((int)(v * h), 0, h - 1);
                    int sIdx = (srcY * w + srcX) * 4;

                    byte b = pipSrc[sIdx];
                    byte g = pipSrc[sIdx + 1];
                    byte r = pipSrc[sIdx + 2];

                    int dIdx = dstRow + dstX * 4;

                    if (pipChroma)
                    {
                        double pCb = 128.0 - 0.168736 * r - 0.331264 * g + 0.500000 * b;
                        double pCr = 128.0 + 0.500000 * r - 0.418688 * g - 0.081312 * b;
                        double cDist = Math.Sqrt((pCb - keyCb) * (pCb - keyCb) + (pCr - keyCr) * (pCr - keyCr));
                        if (cDist <= innerTol) continue; // Transparent background on PiP!
                        if (cDist < outerTol)
                        {
                            double alpha = (cDist - innerTol) / (outerTol - innerTol);
                            canvas[dIdx]     = (byte)(canvas[dIdx] * (1.0 - alpha) + b * alpha);
                            canvas[dIdx + 1] = (byte)(canvas[dIdx + 1] * (1.0 - alpha) + g * alpha);
                            canvas[dIdx + 2] = (byte)(canvas[dIdx + 2] * (1.0 - alpha) + r * alpha);
                            continue;
                        }
                    }

                    // Draw sleek 2.5px Gold Border (#C5A059) unless Chroma Key is active on PiP
                    if (!pipChroma && distToEdge < 2.8)
                    {
                        canvas[dIdx] = 89;      // B
                        canvas[dIdx + 1] = 160; // G
                        canvas[dIdx + 2] = 197; // R
                        canvas[dIdx + 3] = 255;
                    }
                    else
                    {
                        canvas[dIdx] = b;
                        canvas[dIdx + 1] = g;
                        canvas[dIdx + 2] = r;
                        canvas[dIdx + 3] = 255;
                    }
                }
            });
        }

        private void ApplyGeometricEffectsAndDistortions(
            byte[] src,
            byte[] dst,
            int w,
            int h,
            CameraStudioConfig cfg,
            double timeSec)
        {
            string distMode = cfg.DistortionFilter ?? "none";
            double intensity = cfg.DistortionIntensity / 100.0;

            string oneShot;
            double oneShotProgress = 0.0;
            lock (_fxLock)
            {
                oneShot = _activeOneShot;
                if (!string.IsNullOrEmpty(oneShot))
                {
                    double elapsed = (DateTime.UtcNow - _oneShotStart).TotalSeconds;
                    if (elapsed >= _oneShotDurationSec)
                    {
                        _activeOneShot = "";
                        oneShot = "";
                    }
                    else
                    {
                        oneShotProgress = elapsed / _oneShotDurationSec;
                    }
                }
            }

            bool hasSpin = cfg.ContinuousSpin || oneShot == "spin_360";
            bool hasFlip3D = oneShot == "flip_3d";
            bool hasEarthquake = oneShot == "earthquake";

            if (distMode == "none" && !hasSpin && !hasFlip3D && !hasEarthquake)
            {
                Buffer.BlockCopy(src, 0, dst, 0, w * h * 4);
                return;
            }

            double spinAngle = 0.0;
            double spinScale = 1.0;
            if (cfg.ContinuousSpin)
            {
                spinAngle += timeSec * (cfg.SpinSpeed * 0.065);
            }
            if (oneShot == "spin_360")
            {
                double t = oneShotProgress;
                double ease = t < 0.5 ? 4.0 * t * t * t : 1.0 - Math.Pow(-2.0 * t + 2.0, 3.0) / 2.0;
                spinAngle += ease * Math.PI * 2.0;
                spinScale = 1.0 - 0.16 * Math.Sin(t * Math.PI);
            }

            double cosA = Math.Cos(-spinAngle) / spinScale;
            double sinA = Math.Sin(-spinAngle) / spinScale;
            double flipCos = hasFlip3D ? Math.Cos(oneShotProgress * Math.PI * 2.0) : 1.0;
            if (Math.Abs(flipCos) < 0.02) flipCos = 0.02;

            double shakeX = hasEarthquake ? Math.Sin(timeSec * 95.0) * 0.04 * (1.0 - oneShotProgress) : 0.0;
            double shakeY = hasEarthquake ? Math.Cos(timeSec * 82.0) * 0.04 * (1.0 - oneShotProgress) : 0.0;

            double aspect = (double)w / h;

            Parallel.For(0, h, y =>
            {
                int dstRow = y * w * 4;
                double baseNy = ((double)y / h) * 2.0 - 1.0 + shakeY;

                for (int x = 0; x < w; x++)
                {
                    double nx = (((double)x / w) * 2.0 - 1.0 + shakeX) / flipCos;
                    double ny = baseNy;
                    if (hasFlip3D)
                    {
                        // 3D perspective keystone foreshortening during Y-axis flip!
                        double persp = 1.0 + nx * Math.Sin(oneShotProgress * Math.PI * 2.0) * 0.22;
                        ny *= persp;
                    }

                    // 1. Spin rotation
                    if (hasSpin)
                    {
                        double rx = (nx * aspect * cosA - ny * sinA) / aspect;
                        double ry = nx * aspect * sinA + ny * cosA;
                        nx = rx;
                        ny = ry;
                    }

                    // 2. Fun Distortion Filters (supports both XAML tags and engine tags!)
                    switch (distMode)
                    {
                        case "fisheye":
                        {
                            double r = Math.Sqrt(nx * nx + ny * ny);
                            if (r < 1.0 && r > 0.0001)
                            {
                                double nr = Math.Pow(r, 1.0 + intensity * 1.45);
                                nx *= nr / r;
                                ny *= nr / r;
                            }
                            break;
                        }
                        case "pinch":
                        {
                            double r = Math.Sqrt(nx * nx + ny * ny);
                            if (r < 1.0 && r > 0.0001)
                            {
                                double nr = Math.Pow(r, 1.0 / (1.0 + intensity * 1.35));
                                nx *= nr / r;
                                ny *= nr / r;
                            }
                            break;
                        }
                        case "twirl":
                        case "swirl":
                        {
                            double r = Math.Sqrt(nx * nx + ny * ny);
                            if (r < 1.0)
                            {
                                double dynamicPulse = 1.0 + 0.15 * Math.Sin(timeSec * 3.2);
                                double theta = Math.Atan2(ny, nx) + (1.0 - r) * (1.0 - r) * intensity * 5.2 * dynamicPulse;
                                nx = r * Math.Cos(theta);
                                ny = r * Math.Sin(theta);
                            }
                            break;
                        }
                        case "waves":
                        case "wave":
                        {
                            double origNx = nx;
                            nx += Math.Sin(ny * 10.0 + timeSec * 5.5) * 0.065 * intensity;
                            ny += Math.Cos(origNx * 10.0 + timeSec * 4.6) * 0.065 * intensity;
                            break;
                        }
                        case "kaleidoscope":
                        {
                            double r = Math.Sqrt(nx * nx + ny * ny);
                            double theta = Math.Atan2(ny, nx) + Math.PI + timeSec * 0.35;
                            double seg = Math.PI / 3.0; // 6 segments
                            double modTheta = theta % seg;
                            if (((int)(theta / seg) & 1) == 1) modTheta = seg - modTheta;
                            nx = r * Math.Cos(modTheta);
                            ny = r * Math.Sin(modTheta);
                            break;
                        }
                        case "mirror_left":
                        {
                            if (nx > 0) nx = -nx;
                            break;
                        }
                        case "mirror_right":
                        {
                            if (nx < 0) nx = -nx;
                            break;
                        }
                        case "pixelate":
                        {
                            int block = Math.Max(4, (int)(intensity * 26));
                            int px = (x / block) * block + block / 2;
                            int py = (y / block) * block + block / 2;
                            nx = ((double)px / w) * 2.0 - 1.0;
                            ny = ((double)py / h) * 2.0 - 1.0;
                            break;
                        }
                        case "quad_clone":
                        case "quad":
                        {
                            double u = ((nx + 1.0) * 0.5 * 2.0) % 1.0;
                            double v = ((ny + 1.0) * 0.5 * 2.0) % 1.0;
                            if (u < 0) u += 1.0;
                            if (v < 0) v += 1.0;
                            nx = u * 2.0 - 1.0;
                            ny = v * 2.0 - 1.0;
                            break;
                        }
                    }

                    int sx = (int)(((nx + 1.0) * 0.5) * w);
                    int sy = (int)(((ny + 1.0) * 0.5) * h);
                    int dIdx = dstRow + x * 4;

                    if (sx >= 0 && sx < w && sy >= 0 && sy < h)
                    {
                        int sIdx = (sy * w + sx) * 4;
                        dst[dIdx] = src[sIdx];
                        dst[dIdx + 1] = src[sIdx + 1];
                        dst[dIdx + 2] = src[sIdx + 2];
                        dst[dIdx + 3] = 255;
                    }
                    else
                    {
                        dst[dIdx] = 18;
                        dst[dIdx + 1] = 20;
                        dst[dIdx + 2] = 28;
                        dst[dIdx + 3] = 255;
                    }
                }
            });
        }

        private void ApplyColorFiltersAndOverlays(
            byte[] buf,
            int w,
            int h,
            CameraStudioConfig cfg,
            double timeSec)
        {
            string filter = cfg.ColorFilter ?? "none";
            bool disco = cfg.DiscoMode;
            bool vhs = cfg.VhsMode;
            bool cinema = cfg.CinemaBarsMode;
            bool neonPulse = cfg.NeonPulseMode;
            bool rgbWave = cfg.RgbWaveMode;
            bool holoHud = cfg.HologramHudMode;
            bool fireAura = cfg.FireAuraMode;
            string oneShot = _activeOneShot;

            bool needPixelLoop = filter != "none" || disco || vhs || cinema || neonPulse || rgbWave || holoHud || fireAura ||
                                 oneShot == "glitch" || oneShot == "paparazzi" || oneShot == "earthquake";

            if (needPixelLoop)
            {
                // Keep a read-only snapshot if RGB wave or Neon edge detection needs neighbor lookups
                byte[]? snap = null;
                if (neonPulse || rgbWave || oneShot == "glitch" || oneShot == "earthquake" || vhs)
                {
                    if (_workBufC == null || _workBufC.Length != w * h * 4) _workBufC = new byte[w * h * 4];
                    Buffer.BlockCopy(buf, 0, _workBufC, 0, w * h * 4);
                    snap = _workBufC;
                }

                int discoR = (int)((Math.Sin(timeSec * 6.5) * 0.5 + 0.5) * 90);
                int discoG = (int)((Math.Sin(timeSec * 6.5 + 2.09) * 0.5 + 0.5) * 90);
                int discoB = (int)((Math.Sin(timeSec * 6.5 + 4.18) * 0.5 + 0.5) * 90);
                int vhsBarY = (int)((timeSec * 150) % h);
                int holoLaserY = (int)((timeSec * 210) % h);
                bool flashNow = oneShot == "paparazzi" && (((int)(timeSec * 14) % 3) == 0);
                int barH = h / 9;
                int rgbShift = (int)(Math.Sin(timeSec * 6.0) * 12.0);
                int stride = w * 4;

                Parallel.For(0, h, y =>
                {
                    int row = y * stride;
                    if (cinema && (y < barH || y >= h - barH))
                    {
                        for (int x = 0; x < w; x++)
                        {
                            int idx = row + x * 4;
                            buf[idx] = 8;
                            buf[idx + 1] = 9;
                            buf[idx + 2] = 12;
                        }
                        return;
                    }

                    bool isGlitchSlice = (oneShot == "glitch" || vhs) && Math.Abs(y - vhsBarY) < 20;
                    int waveOffset = rgbWave ? (int)(rgbShift + Math.Sin(y * 0.04 + timeSec * 9.0) * 7.0) : 10;

                    for (int x = 0; x < w; x++)
                    {
                        int idx = row + x * 4;
                        int b = buf[idx];
                        int g = buf[idx + 1];
                        int r = buf[idx + 2];

                        if ((isGlitchSlice || oneShot == "earthquake" || rgbWave) && snap != null)
                        {
                            int shiftR = Math.Clamp(x + waveOffset, 0, w - 1);
                            int shiftB = Math.Clamp(x - waveOffset, 0, w - 1);
                            r = snap[row + shiftR * 4 + 2];
                            b = snap[row + shiftB * 4];
                        }

                        switch (filter)
                        {
                            case "noir":
                            case "grayscale":
                            {
                                int lum = (r * 77 + g * 150 + b * 29) >> 8;
                                lum = Math.Clamp(((lum - 128) * 5 >> 2) + 128, 0, 255);
                                r = g = b = lum;
                                break;
                            }
                            case "sepia":
                            {
                                int sr = (r * 101 + g * 197 + b * 48) >> 8;
                                int sg = (r * 89 + g * 176 + b * 43) >> 8;
                                int sb = (r * 69 + g * 137 + b * 33) >> 8;
                                r = Math.Min(255, sr);
                                g = Math.Min(255, sg);
                                b = Math.Min(255, sb);
                                break;
                            }
                            case "cyberpunk":
                            {
                                r = Math.Clamp((r * 5 >> 2) + 25, 0, 255);
                                g = (g * 3) >> 2;
                                b = Math.Clamp((b * 5 >> 2) + 45, 0, 255);
                                break;
                            }
                            case "matrix":
                            {
                                int lum = (r * 77 + g * 150 + b * 29) >> 8;
                                r = lum >> 2;
                                g = Math.Min(255, (lum * 5) >> 2);
                                b = lum >> 2;
                                break;
                            }
                            case "thermal":
                            {
                                int lum = (r * 77 + g * 150 + b * 29) >> 8;
                                if (lum < 85)
                                {
                                    r = lum * 2;
                                    g = 0;
                                    b = 140 + lum;
                                }
                                else if (lum < 170)
                                {
                                    int t = lum - 85;
                                    r = 170 + t;
                                    g = t * 2;
                                    b = Math.Max(0, 225 - t * 3);
                                }
                                else
                                {
                                    int t = lum - 170;
                                    r = 255;
                                    g = Math.Min(255, 170 + t);
                                    b = Math.Min(255, t * 3);
                                }
                                break;
                            }
                            case "invert":
                            {
                                r = 255 - r;
                                g = 255 - g;
                                b = 255 - b;
                                break;
                            }
                            case "gold":
                            case "vintage_gold":
                            {
                                int lum = (r * 77 + g * 150 + b * 29) >> 8;
                                r = Math.Min(255, (lum * 225) >> 8);
                                g = Math.Min(255, (lum * 182) >> 8);
                                b = Math.Min(255, (lum * 105) >> 8);
                                break;
                            }
                            case "arctic":
                            {
                                r = (r * 3) >> 2;
                                g = Math.Min(255, (g * 9) >> 3);
                                b = Math.Min(255, (b * 5 >> 2) + 35);
                                break;
                            }
                            case "synthwave":
                            {
                                double grad = (double)y / h;
                                r = Math.Clamp((int)(r * 1.15 + (1.0 - grad) * 55), 0, 255);
                                g = Math.Clamp((int)(g * 0.75), 0, 255);
                                b = Math.Clamp((int)(b * 1.15 + grad * 65), 0, 255);
                                break;
                            }
                            case "comic":
                            {
                                r = (r & 0xE0) + 16;
                                g = (g & 0xE0) + 16;
                                b = (b & 0xE0) + 16;
                                break;
                            }
                            case "nightvision":
                            {
                                int lum = (r * 77 + g * 150 + b * 29) >> 8;
                                int scan = (y & 1) == 0 ? 15 : -15;
                                r = Math.Clamp((lum >> 2) + scan, 0, 255);
                                g = Math.Clamp(lum + 35 + scan, 0, 255);
                                b = Math.Clamp((lum >> 2) + scan, 0, 255);
                                break;
                            }
                        }

                        // 60FPS Neon Edge Pulse Shader Effect
                        if (neonPulse && snap != null && x > 1 && x < w - 2 && y > 1 && y < h - 2)
                        {
                            int idxR = row + (x + 1) * 4;
                            int idxD = (y + 1) * stride + x * 4;
                            int gx = Math.Abs(snap[idxR + 2] - snap[idx + 2]) + Math.Abs(snap[idxR + 1] - snap[idx + 1]);
                            int gy = Math.Abs(snap[idxD + 2] - snap[idx + 2]) + Math.Abs(snap[idxD + 1] - snap[idx + 1]);
                            int edge = (gx + gy);
                            if (edge > 28)
                            {
                                double phase = (x * 0.008) + (y * 0.008) - timeSec * 4.5;
                                int nr = (int)((Math.Sin(phase) * 0.5 + 0.5) * 255);
                                int ng = (int)((Math.Sin(phase + 2.09) * 0.5 + 0.5) * 255);
                                int nb = (int)((Math.Sin(phase + 4.18) * 0.5 + 0.5) * 255);
                                double str = Math.Min(1.0, edge / 95.0);
                                r = (int)Math.Clamp(r * 0.55 + nr * str, 0, 255);
                                g = (int)Math.Clamp(g * 0.55 + ng * str, 0, 255);
                                b = (int)Math.Clamp(b * 0.55 + nb * str, 0, 255);
                            }
                        }

                        // 60FPS Sci-Fi Hologram HUD Effect
                        if (holoHud)
                        {
                            int scanLine = (y % 3 == 0) ? -22 : 10;
                            int lum = (r * 77 + g * 150 + b * 29) >> 8;
                            r = Math.Clamp((lum >> 2) + scanLine, 0, 255);
                            g = Math.Clamp(((lum * 4) >> 2) + 25 + scanLine, 0, 255);
                            b = Math.Clamp(((lum * 5) >> 2) + 55 + scanLine, 0, 255);

                            int distLaser = Math.Abs(y - holoLaserY);
                            if (distLaser < 6)
                            {
                                int boost = (6 - distLaser) * 32;
                                g = Math.Min(255, g + boost);
                                b = Math.Min(255, b + boost);
                                r = Math.Min(255, r + (boost >> 1));
                            }
                        }

                        // 60FPS Rising Fire / Plasma Aura Effect
                        if (fireAura)
                        {
                            double ny = (double)y / h;
                            if (ny > 0.52)
                            {
                                double flameWave = Math.Sin(x * 0.045 + timeSec * 8.0) * Math.Cos(x * 0.02 - timeSec * 5.5);
                                double heightFactor = Math.Max(0.0, (ny - (0.68 + flameWave * 0.14)) / 0.32);
                                if (heightFactor > 0.0)
                                {
                                    r = Math.Min(255, r + (int)(heightFactor * 240));
                                    g = Math.Min(255, g + (int)(heightFactor * heightFactor * 165));
                                    b = Math.Max(0, b - (int)(heightFactor * 80));
                                }
                            }
                        }

                        if (disco)
                        {
                            r = Math.Min(255, r + discoR);
                            g = Math.Min(255, g + discoG);
                            b = Math.Min(255, b + discoB);
                        }

                        if (vhs && (y % 4 == 0))
                        {
                            r = (r * 7) >> 3;
                            g = (g * 7) >> 3;
                            b = (b * 7) >> 3;
                        }

                        if (flashNow)
                        {
                            r = Math.Min(255, r + 150);
                            g = Math.Min(255, g + 150);
                            b = Math.Min(255, b + 150);
                        }

                        buf[idx] = (byte)b;
                        buf[idx + 1] = (byte)g;
                        buf[idx + 2] = (byte)r;
                    }
                });
            }

            // Hologram HUD Target Reticle Overlay
            if (holoHud)
            {
                DrawHologramHudReticle(buf, w, h, timeSec);
            }

            // Matrix Digital Rain Overlay
            if (cfg.MatrixRainMode)
            {
                int cols = Math.Min(_matrixDrops.Length, w / 12);
                for (int c = 0; c < cols; c++)
                {
                    _matrixDrops[c] += 0.85f;
                    if (_matrixDrops[c] * 10 > h + 80) _matrixDrops[c] = 0;
                    int headY = (int)(_matrixDrops[c] * 10);
                    int colX = c * 12 + 4;
                    for (int trail = 0; trail < 48; trail += 3)
                    {
                        int py = headY - trail;
                        if (py < 2 || py >= h - 2 || colX >= w - 4) continue;
                        byte bright = (byte)Math.Max(60, 255 - trail * 4);
                        for (int dy = 0; dy < 2; dy++)
                        {
                            for (int dx = 0; dx < 3; dx++)
                            {
                                int idx = ((py + dy) * w + (colX + dx)) * 4;
                                buf[idx] = (byte)(trail == 0 ? 180 : 20);
                                buf[idx + 1] = bright;
                                buf[idx + 2] = (byte)(trail == 0 ? 180 : 20);
                            }
                        }
                    }
                }
            }

            // "Deal With It" Pixel Sunglasses Overlay
            if (cfg.DealWithItMode)
            {
                DrawDealWithItGlasses(buf, w, h, timeSec);
            }

            // Confetti Cannon Particle Overlay
            if (oneShot == "confetti")
            {
                lock (_fxLock)
                {
                    for (int i = 0; i < _confetti.Count; i++)
                    {
                        var p = _confetti[i];
                        p.X += p.Vx + (float)(Math.Sin(timeSec * 5.0 + i) * 0.002);
                        p.Y += p.Vy;
                        p.Angle += p.VAngle;
                        _confetti[i] = p;

                        int cx = (int)(p.X * w);
                        int cy = (int)(p.Y * h);
                        int rad = (int)p.Size;
                        for (int dy = -rad; dy <= rad; dy++)
                        {
                            int py = cy + dy;
                            if (py < 0 || py >= h) continue;
                            for (int dx = -rad; dx <= rad; dx++)
                            {
                                int px = cx + dx;
                                if (px < 0 || px >= w) continue;
                                int idx = (py * w + px) * 4;
                                buf[idx] = p.B;
                                buf[idx + 1] = p.G;
                                buf[idx + 2] = p.R;
                            }
                        }
                    }
                }
            }
        }

        private static void DrawHologramHudReticle(byte[] buf, int w, int h, double timeSec)
        {
            int cx = w / 2;
            int cy = h / 2;
            int radius = Math.Min(w, h) / 4;

            for (int deg = 0; deg < 360; deg += 2)
            {
                if ((deg / 30) % 2 == 1) continue;
                double rad = (deg + timeSec * 45.0) * Math.PI / 180.0;
                int px = cx + (int)(Math.Cos(rad) * radius);
                int py = cy + (int)(Math.Sin(rad) * radius);
                if (px >= 1 && px < w - 1 && py >= 1 && py < h - 1)
                {
                    int idx = (py * w + px) * 4;
                    buf[idx] = 255;     // B
                    buf[idx + 1] = 230; // G
                    buf[idx + 2] = 60;  // R
                }
            }
        }

        private static void DrawDealWithItGlasses(byte[] buf, int w, int h, double timeSec)
        {
            int centerX = w / 2;
            int centerY = (int)(h * 0.38 + Math.Sin(timeSec * 3.0) * 6.0);
            int glassW = w / 4;
            int glassH = glassW / 4;
            int startX = centerX - glassW / 2;
            int startY = centerY - glassH / 2;

            for (int y = 0; y < glassH; y++)
            {
                int py = startY + y;
                if (py < 0 || py >= h) continue;
                for (int x = 0; x < glassW; x++)
                {
                    int px = startX + x;
                    if (px < 0 || px >= w) continue;

                    double nx = (double)x / glassW;
                    double ny = (double)y / glassH;

                    bool isTopBar = ny < 0.24;
                    bool isLeftLens = nx > 0.06 && nx < 0.44 && ny >= 0.24 && ny < (1.0 - Math.Abs(nx - 0.25) * 1.4);
                    bool isRightLens = nx > 0.56 && nx < 0.94 && ny >= 0.24 && ny < (1.0 - Math.Abs(nx - 0.75) * 1.4);

                    if (isTopBar || isLeftLens || isRightLens)
                    {
                        bool isGlint = ((x + y) / 8) % 5 == 0 && !isTopBar;
                        int idx = (py * w + px) * 4;
                        byte c = isGlint ? (byte)245 : (byte)14;
                        buf[idx] = c;
                        buf[idx + 1] = c;
                        buf[idx + 2] = c;
                    }
                }
            }
        }
    }

    // =========================================================================
    // 7. DYNAMIC VIDEO / MEDIA SIGNAL INJECTOR & INFINITE LOOP / BOOMERANG DECK
    // =========================================================================
    public sealed class FunkenwerkMediaInjector : IDisposable
    {
        private CancellationTokenSource? _cts;
        private Process? _proc;
        private byte[] _latestFrame = Array.Empty<byte>();
        private readonly object _lock = new();
        private bool _hasFrame;
        private bool _isPlaying;
        private string _activeFilePath = "";
        private int _width = 1280;
        private int _height = 720;
        private bool _loopEnabled = true;
        private bool _boomerangEnabled = false;
        private bool _returnToLiveAfterEnd = true;
        private volatile bool _didAutoFinishToLive = false;

        public bool IsPlaying => _isPlaying;
        public bool HasFrame => _hasFrame;
        public string ActiveFilePath => _activeFilePath;
        public string ActiveVideoPath => _activeFilePath;

        public bool LoopEnabled
        {
            get => _loopEnabled;
            set => _loopEnabled = value;
        }

        public bool BoomerangEnabled
        {
            get => _boomerangEnabled;
            set => _boomerangEnabled = value;
        }

        public bool ReturnToLiveAfterEnd
        {
            get => _returnToLiveAfterEnd;
            set => _returnToLiveAfterEnd = value;
        }

        public bool DidAutoFinishToLive => _didAutoFinishToLive;

        public bool ConsumeAutoFinishToLive()
        {
            if (!_didAutoFinishToLive) return false;
            _didAutoFinishToLive = false;
            return true;
        }

        public void StartVideo(string filePath, int width, int height, int fps, bool loop) => Start(filePath, width, height, fps, loop);

        public void Start(string filePath, int width, int height, int fps, bool loop)
        {
            if (string.IsNullOrWhiteSpace(filePath) || !File.Exists(filePath)) return;
            Stop();

            _activeFilePath = filePath;
            _width = width;
            _height = height;
            _loopEnabled = loop;
            _didAutoFinishToLive = false;
            _hasFrame = false;
            _isPlaying = true;
            lock (_lock)
            {
                if (_latestFrame.Length != width * height * 4)
                    _latestFrame = new byte[width * height * 4];
            }

            _cts = new CancellationTokenSource();
            var token = _cts.Token;
            Task.Run(() => DecodeLoopAsync(filePath, width, height, Math.Clamp(fps, 15, 60), token), token);
        }

        public void Stop()
        {
            _isPlaying = false;
            _hasFrame = false;
            _activeFilePath = "";
            try { _cts?.Cancel(); } catch { }
            try
            {
                if (_proc != null && !_proc.HasExited)
                    _proc.Kill(true);
            }
            catch { }
            _proc = null;
        }

        public bool CopyLatestFrame(int w, int h, ref byte[]? dest)
        {
            if (!_isPlaying || !_hasFrame) return false;
            if (dest == null || dest.Length != w * h * 4)
                dest = new byte[w * h * 4];
            return CopyLatestFrame(dest, w, h);
        }

        public bool CopyLatestFrame(byte[] dest, int w, int h)
        {
            if (!_isPlaying || !_hasFrame || dest == null || dest.Length < w * h * 4)
                return false;

            lock (_lock)
            {
                if (_latestFrame.Length != w * h * 4) return false;
                Buffer.BlockCopy(_latestFrame, 0, dest, 0, w * h * 4);
                return true;
            }
        }

        private async Task DecodeLoopAsync(string filePath, int w, int h, int fps, CancellationToken token)
        {
            string ffmpegPath = Path.Combine(AppDomain.CurrentDomain.BaseDirectory, "tools", "ffmpeg.exe");
            if (!File.Exists(ffmpegPath))
            {
                string desktopTools = @"C:\Users\konta\Desktop\Funkenwerk\tools\ffmpeg.exe";
                ffmpegPath = File.Exists(desktopTools) ? desktopTools : "ffmpeg";
            }

            int frameBytes = w * h * 4;
            byte[] readBuf = new byte[frameBytes];
            string ext = Path.GetExtension(filePath).ToLowerInvariant();
            bool isStaticImage = ext is ".png" or ".jpg" or ".jpeg" or ".bmp" or ".webp";
            int frameDelayMs = Math.Max(12, 1000 / Math.Max(15, fps));

            // Cache for Instagram-style Boomerang (forward <-> backward ping-pong)
            int maxCacheFrames = (w >= 1920) ? 65 : 120;
            var boomerangCache = new List<byte[]>(maxCacheFrames);

            while (!token.IsCancellationRequested && _isPlaying)
            {
                try
                {
                    // If Boomerang cache is already populated and BoomerangEnabled is active, ping-pong directly from RAM
                    if (_boomerangEnabled && !isStaticImage && boomerangCache.Count > 2)
                    {
                        // Forward pass
                        for (int i = 0; i < boomerangCache.Count && !token.IsCancellationRequested && _isPlaying && _boomerangEnabled; i++)
                        {
                            PublishFrame(boomerangCache[i], frameBytes);
                            await Task.Delay(frameDelayMs, token).ConfigureAwait(false);
                        }
                        // Backward pass (Instagram Boomerang reverse bounce)
                        for (int i = boomerangCache.Count - 2; i >= 1 && !token.IsCancellationRequested && _isPlaying && _boomerangEnabled; i--)
                        {
                            PublishFrame(boomerangCache[i], frameBytes);
                            await Task.Delay(frameDelayMs, token).ConfigureAwait(false);
                        }

                        if (!_loopEnabled)
                        {
                            _isPlaying = false;
                            _hasFrame = false;
                            if (_returnToLiveAfterEnd) _didAutoFinishToLive = true;
                            break;
                        }
                        continue;
                    }

                    bool useFfmpegInfiniteLoop = (_loopEnabled && !_boomerangEnabled) || isStaticImage;
                    string loopArg = useFfmpegInfiniteLoop ? "-stream_loop -1 " : "";
                    string inputPrefix = isStaticImage ? $"-loop 1 -framerate {fps} " : $"-re {loopArg}";
                    string vf = $"scale={w}:{h}:force_original_aspect_ratio=decrease,pad={w}:{h}:(ow-iw)/2:(oh-ih)/2:color=#101218";
                    string safeFilePath = FunkenwerkSecurityVault.SanitizeCliArgument(filePath);
                    string args = $"-hide_banner -loglevel error {inputPrefix}-i \"{safeFilePath}\" -vf \"{vf}\" -r {fps} -f rawvideo -pix_fmt bgra -";

                    var psi = new ProcessStartInfo
                    {
                        FileName = ffmpegPath,
                        Arguments = args,
                        UseShellExecute = false,
                        CreateNoWindow = true,
                        RedirectStandardOutput = true,
                        RedirectStandardError = true
                    };

                    using var proc = Process.Start(psi);
                    _proc = proc;
                    if (proc != null)
                    {
                        var stream = proc.StandardOutput.BaseStream;
                        while (!token.IsCancellationRequested && _isPlaying && !proc.HasExited)
                        {
                            int offset = 0;
                            while (offset < frameBytes && !token.IsCancellationRequested)
                            {
                                int read = await stream.ReadAsync(readBuf.AsMemory(offset, frameBytes - offset), token);
                                if (read <= 0) break;
                                offset += read;
                            }
                            if (offset == frameBytes)
                            {
                                PublishFrame(readBuf, frameBytes);

                                if (_boomerangEnabled && !isStaticImage && boomerangCache.Count < maxCacheFrames)
                                {
                                    byte[] copy = new byte[frameBytes];
                                    Buffer.BlockCopy(readBuf, 0, copy, 0, frameBytes);
                                    boomerangCache.Add(copy);
                                    if (boomerangCache.Count >= maxCacheFrames && useFfmpegInfiniteLoop)
                                    {
                                        try { proc.Kill(true); } catch { }
                                        break;
                                    }
                                }
                            }
                            else
                            {
                                break;
                            }
                        }
                    }

                    // If Boomerang was just populated during the first pass, immediately run the reverse pass
                    if (_boomerangEnabled && !isStaticImage && boomerangCache.Count > 2 && !token.IsCancellationRequested && _isPlaying)
                    {
                        for (int i = boomerangCache.Count - 2; i >= 1 && !token.IsCancellationRequested && _isPlaying && _boomerangEnabled; i--)
                        {
                            PublishFrame(boomerangCache[i], frameBytes);
                            await Task.Delay(frameDelayMs, token).ConfigureAwait(false);
                        }
                    }
                }
                catch { }

                if (!_loopEnabled && !isStaticImage)
                {
                    _isPlaying = false;
                    _hasFrame = false;
                    if (_returnToLiveAfterEnd) _didAutoFinishToLive = true;
                    break;
                }

                await Task.Delay(40, token).ConfigureAwait(false);
            }
        }

        private void PublishFrame(byte[] src, int frameBytes)
        {
            lock (_lock)
            {
                if (_latestFrame.Length != frameBytes)
                    _latestFrame = new byte[frameBytes];
                Buffer.BlockCopy(src, 0, _latestFrame, 0, frameBytes);
                _hasFrame = true;
            }
        }

        public void Dispose() => Stop();
    }

    // =========================================================================
    // 8. DEEP FAKE STUDIO ENGINE (DEEP-LIVE-CAM SHARED MEMORY v3 + AR LANDMARKS)
    // =========================================================================
    public sealed class FunkenwerkDeepFakeEngine : IDisposable
    {
        public const string DlcRootDir = @"C:\Users\konta\Documents\Deep-Live-Cam";
        public const string ShmName = "FunkenwerkDeepFakeShm_v3";
        private const int MaxW = 1920;
        private const int MaxH = 1080;
        private const int WorkW = 640;
        private const int WorkH = 360;
        private const int WorkBytes = WorkW * WorkH * 4;
        private const int FrameStride = MaxW * MaxH * 4;
        private const int HeaderSize = 576; // 64-byte control header + 512-byte UTF-8 source path
        private const int SourcePathOffset = 64;
        private const int SourcePathMaxBytes = 512;
        private const int OutOffset = HeaderSize + FrameStride;
        private const int TotalShmSize = HeaderSize + FrameStride * 2;

        private MemoryMappedFile? _mmf;
        private MemoryMappedViewAccessor? _accessor;
        private Process? _pythonProc;
        private readonly object _sync = new();
        private readonly byte[] _workInBgra = new byte[WorkBytes];
        private readonly byte[] _workOutOverlayBgra = new byte[WorkBytes];
        private int _overlayFx1, _overlayFy1, _overlayFx2, _overlayFy2;
        private string _lastSourcePath = "";
        private int _sourceVersion = 1;
        private bool _hasProcessedFrame = false;
        private int _pythonStatus = 0;
        private int _lastInferMs = 0;
        private int _detectedFaces = 0;

        public int PythonStatus => _pythonStatus;
        public int LastInferenceMs => _lastInferMs;
        public int DetectedFacesCount => _detectedFaces;
        public bool IsReady => _pythonStatus == 2 || _pythonStatus == 3;

        public string StatusText => StatusDescription;

        public string StatusDescription => _pythonStatus switch
        {
            0 => "Bereit zum Start",
            1 => "⏳ Lade InsightFace & DirectML GPU-Modell...",
            2 => $"🟢 KI Live aktiv ({_lastInferMs} ms • {_detectedFaces} Gesicht{(_detectedFaces == 1 ? "" : "er")})",
            3 => "🟡 Bereit (Bitte Quell-Gesicht wählen oder AR-Modus nutzen)",
            -1 => "⚠ KI-Worker Neustart...",
            _ => "Bereit"
        };

        public static List<DeepFakeFacePreset> GetAvailableFacePresets()
        {
            var list = new List<DeepFakeFacePreset>();
            string mediaDir = Path.Combine(DlcRootDir, "media");
            if (Directory.Exists(mediaDir))
            {
                var friendlyNames = new Dictionary<string, string>(StringComparer.OrdinalIgnoreCase)
                {
                    ["Elon_Musk_-_54820081119_(cropped).jpg.webp"] = "🚀 Elon Musk (HD)",
                    ["1.png"] = "🎭 Studio Gesicht #1",
                    ["images.jpg"] = "🎬 Celebrity Portrait",
                    ["3.png"] = "✨ Studio Gesicht #2",
                    ["2hm66rc.jpg"] = "😎 Meme / Charakter",
                    ["musk.jpg"] = "🚀 Elon Musk (Celebrity)",
                    ["zuck.jpg"] = "🤖 Mark Zuckerberg",
                    ["kirk.jpg"] = "🎙 Charlie Kirk",
                    ["show-1.jpg"] = "🎬 Studio Portrait #1",
                    ["show-2.jpg"] = "🎬 Studio Portrait #2",
                    ["show-3.jpg"] = "🎬 Studio Portrait #3",
                    ["test.jpg"] = "✨ Model Portrait"
                };

                var priorityOrder = new List<string>
                {
                    "Elon_Musk_-_54820081119_(cropped).jpg.webp",
                    "1.png",
                    "images.jpg",
                    "3.png",
                    "2hm66rc.jpg"
                };

                var allFiles = Directory.GetFiles(mediaDir)
                    .OrderBy(f =>
                    {
                        string name = Path.GetFileName(f);
                        int idx = priorityOrder.FindIndex(p => string.Equals(p, name, StringComparison.OrdinalIgnoreCase));
                        return idx >= 0 ? idx : 100;
                    })
                    .ThenBy(f => Path.GetFileName(f), StringComparer.OrdinalIgnoreCase);

                foreach (var file in allFiles)
                {
                    string ext = Path.GetExtension(file).ToLowerInvariant();
                    if (ext is not (".jpg" or ".jpeg" or ".png" or ".webp")) continue;
                    string fn = Path.GetFileName(file);

                    // Exclude UI screenshots, banners, and non-face graphics
                    if (fn.Contains("banner", StringComparison.OrdinalIgnoreCase) ||
                        fn.Contains("deep-live-cam", StringComparison.OrdinalIgnoreCase) ||
                        fn.Contains("Screenshot", StringComparison.OrdinalIgnoreCase) ||
                        string.Equals(fn, "Download.png", StringComparison.OrdinalIgnoreCase) ||
                        string.Equals(fn, "instruction.png", StringComparison.OrdinalIgnoreCase))
                        continue;

                    string label = friendlyNames.TryGetValue(fn, out var custom) ? custom : $"👤 {Path.GetFileNameWithoutExtension(fn)}";
                    list.Add(new DeepFakeFacePreset
                    {
                        Name = label,
                        FilePath = file,
                        IsBuiltIn = true
                    });
                }
            }
            return list;
        }

        public static BitmapImage? LoadPortraitBitmap(string filePath, int decodeWidth = 140)
        {
            if (string.IsNullOrWhiteSpace(filePath) || !File.Exists(filePath)) return null;
            string loadPath = filePath;

            // If file is .webp, create a cached .png fallback if WPF fails or for guaranteed thumbnail rendering
            if (filePath.EndsWith(".webp", StringComparison.OrdinalIgnoreCase))
            {
                try
                {
                    string cacheDir = Path.Combine(Path.GetTempPath(), "FunkenwerkDlcThumbCache");
                    Directory.CreateDirectory(cacheDir);
                    string cachedPng = Path.Combine(cacheDir, Path.GetFileName(filePath) + ".png");
                    if (!File.Exists(cachedPng))
                    {
                        string ffmpeg = Path.Combine(AppDomain.CurrentDomain.BaseDirectory, "tools", "ffmpeg.exe");
                        if (!File.Exists(ffmpeg))
                            ffmpeg = @"C:\Users\konta\Desktop\Funkenwerk\tools\ffmpeg.exe";
                        if (File.Exists(ffmpeg))
                        {
                            using var p = Process.Start(new ProcessStartInfo
                            {
                                FileName = ffmpeg,
                                Arguments = $"-y -i \"{filePath}\" -frames:v 1 \"{cachedPng}\"",
                                UseShellExecute = false,
                                CreateNoWindow = true
                            });
                            p?.WaitForExit(2500);
                        }
                    }
                    if (File.Exists(cachedPng)) loadPath = cachedPng;
                }
                catch { }
            }

            try
            {
                var bmp = new BitmapImage();
                bmp.BeginInit();
                bmp.CacheOption = BitmapCacheOption.OnLoad;
                bmp.UriSource = new Uri(loadPath, UriKind.Absolute);
                if (decodeWidth > 0) bmp.DecodePixelWidth = decodeWidth;
                bmp.EndInit();
                bmp.Freeze();
                return bmp;
            }
            catch
            {
                return null;
            }
        }

        private void WriteSourcePathToShm(string sourceFacePath)
        {
            if (_accessor == null || string.IsNullOrWhiteSpace(sourceFacePath)) return;
            try
            {
                byte[] pathBytes = Encoding.UTF8.GetBytes(sourceFacePath.Trim());
                byte[] buf = new byte[SourcePathMaxBytes];
                int copyLen = Math.Min(pathBytes.Length, SourcePathMaxBytes - 1);
                Buffer.BlockCopy(pathBytes, 0, buf, 0, copyLen);
                _accessor.WriteArray(SourcePathOffset, buf, 0, SourcePathMaxBytes);
                _accessor.Write(24, _sourceVersion);
            }
            catch { }
        }

        public void EnsureStarted(string sourceFacePath)
        {
            lock (_sync)
            {
                try
                {
                    if (_mmf == null || _accessor == null)
                    {
                        _mmf = MemoryMappedFile.CreateOrOpen(ShmName, TotalShmSize);
                        _accessor = _mmf.CreateViewAccessor(0, TotalShmSize);
                        _accessor.Write(0, 0); // cmd = 0 (idle)
                    }
                    else
                    {
                        int existingCmd = _accessor.ReadInt32(0);
                        if (existingCmd == 99)
                        {
                            _accessor.Write(0, 0);
                        }
                    }

                    UpdateSourceFace(sourceFacePath);
                    WriteSourcePathToShm(_lastSourcePath);

                    if (_pythonProc == null || _pythonProc.HasExited)
                    {
                        string pythonExe = Path.Combine(DlcRootDir, "venv", "Scripts", "python.exe");
                        if (!File.Exists(pythonExe)) return;

                        string bridgeScript = Path.Combine(AppDomain.CurrentDomain.BaseDirectory, "funkenwerk_dlc_bridge.py");
                        if (!File.Exists(bridgeScript))
                        {
                            string desktopBridge = @"C:\Users\konta\Desktop\Funkenwerk\funkenwerk_dlc_bridge.py";
                            if (File.Exists(desktopBridge)) bridgeScript = desktopBridge;
                        }

                        if (!File.Exists(bridgeScript)) return;

                        // Reset command and status before spawning worker
                        _accessor.Write(0, 0);
                        _accessor.Write(28, 1);
                        WriteSourcePathToShm(_lastSourcePath);

                        var psi = new ProcessStartInfo
                        {
                            FileName = pythonExe,
                            Arguments = $"\"{bridgeScript}\"",
                            WorkingDirectory = DlcRootDir,
                            UseShellExecute = false,
                            CreateNoWindow = true,
                            RedirectStandardError = true,
                            RedirectStandardOutput = true
                        };

                        _pythonStatus = 1;
                        _pythonProc = Process.Start(psi);
                    }
                }
                catch { }
            }
        }

        public void UpdateSourceFace(string sourceFacePath)
        {
            if (string.IsNullOrWhiteSpace(sourceFacePath) || !File.Exists(sourceFacePath)) return;
            if (string.Equals(_lastSourcePath, sourceFacePath, StringComparison.OrdinalIgnoreCase))
            {
                WriteSourcePathToShm(_lastSourcePath);
                return;
            }

            try
            {
                _lastSourcePath = sourceFacePath;
                _hasProcessedFrame = false;
                var utf8NoBom = new UTF8Encoding(false);
                string cfgFile = Path.Combine(AppDomain.CurrentDomain.BaseDirectory, "funkenwerk_dlc_source.txt");
                File.WriteAllText(cfgFile, sourceFacePath, utf8NoBom);
                try
                {
                    string desktopCfg = @"C:\Users\konta\Desktop\Funkenwerk\funkenwerk_dlc_source.txt";
                    File.WriteAllText(desktopCfg, sourceFacePath, utf8NoBom);
                }
                catch { }
                _sourceVersion++;
                WriteSourcePathToShm(_lastSourcePath);
            }
            catch { }
        }

        public void ProcessFrame(byte[] frameBgra, int w, int h, CameraStudioConfig cfg)
        {
            if (!cfg.DeepFakeEnabled || w <= 0 || h <= 0 || w > MaxW || h > MaxH) return;

            EnsureStarted(cfg.DeepFakeSourceFacePath);

            lock (_sync)
            {
                if (_accessor == null) return;

                try
                {
                    int cmd = _accessor.ReadInt32(0);
                    _pythonStatus = _accessor.ReadInt32(28);
                    _lastInferMs = _accessor.ReadInt32(32);

                    IntPtr basePtr = _accessor.SafeMemoryMappedViewHandle.DangerousGetHandle();

                    // If Python finished a frame (cmd == 2), read the RGBA face overlay & bbox
                    if (cmd == 2)
                    {
                        _detectedFaces = _accessor.ReadInt32(36);
                        int fx1 = _accessor.ReadInt32(40);
                        int fy1 = _accessor.ReadInt32(44);
                        int fx2 = _accessor.ReadInt32(48);
                        int fy2 = _accessor.ReadInt32(52);

                        if (fx2 > fx1 && fy2 > fy1 && fx1 >= 0 && fy1 >= 0 && fx2 <= WorkW && fy2 <= WorkH)
                        {
                            Marshal.Copy(IntPtr.Add(basePtr, OutOffset), _workOutOverlayBgra, 0, WorkBytes);
                            _overlayFx1 = fx1;
                            _overlayFy1 = fy1;
                            _overlayFx2 = fx2;
                            _overlayFy2 = fy2;
                            _hasProcessedFrame = true;
                        }
                        else
                        {
                            _hasProcessedFrame = false;
                        }
                        cmd = 0;
                        _accessor.Write(0, 0);
                    }

                    // If Python is idle (cmd == 0), downscale current frame to 640x360 and post via fast Marshal.Copy
                    if (cmd == 0)
                    {
                        DownscaleToWorkBuffer(frameBgra, w, h, _workInBgra);

                        int flags = 0;
                        if (cfg.DeepFakeSwapActive) flags |= 1;
                        if (cfg.DeepFakeMouthMask) flags |= 2;
                        if (cfg.DeepFakeColorCorrection) flags |= 4;
                        flags |= 8; // Seamless feather blend
                        if (cfg.DeepFakeEnhancer) flags |= 16;
                        if (cfg.DeepFakeManyFaces) flags |= 32;

                        Marshal.Copy(_workInBgra, 0, IntPtr.Add(basePtr, HeaderSize), WorkBytes);
                        _accessor.Write(4, WorkW);
                        _accessor.Write(8, WorkH);
                        _accessor.Write(12, flags);
                        _accessor.Write(16, Math.Clamp(cfg.DeepFakeOpacity, 10, 100));
                        _accessor.Write(20, cfg.DeepFakeArModeCode);
                        _accessor.Write(24, _sourceVersion);
                        _accessor.Write(0, 1); // Signal cmd = 1 (frame ready for Python)
                    }

                    // Composite ONLY the RGBA face/AR bounding box onto the live 60 FPS full-resolution camera frame!
                    if (_hasProcessedFrame && _overlayFx2 > _overlayFx1 && _overlayFy2 > _overlayFy1)
                    {
                        CompositeOverlayBbox(frameBgra, w, h, _workOutOverlayBgra, _overlayFx1, _overlayFy1, _overlayFx2, _overlayFy2);
                    }
                    else if (_pythonStatus == 1 && cfg.DeepFakeArModeCode > 0)
                    {
                        // Instant native C# AR fallback while Python models warm up
                        ApplyInstantNativeArFallback(frameBgra, w, h, cfg.DeepFakeArModeCode);
                    }
                }
                catch { }
            }
        }

        private static void DownscaleToWorkBuffer(byte[] srcBgra, int srcW, int srcH, byte[] dstWorkBgra)
        {
            if (srcW == WorkW && srcH == WorkH)
            {
                Buffer.BlockCopy(srcBgra, 0, dstWorkBgra, 0, WorkBytes);
                return;
            }

            Parallel.For(0, WorkH, wy =>
            {
                int sy = (wy * srcH) / WorkH;
                int srcRow = sy * srcW * 4;
                int dstRow = wy * WorkW * 4;
                for (int wx = 0; wx < WorkW; wx++)
                {
                    int sx = (wx * srcW) / WorkW;
                    int sIdx = srcRow + (sx << 2);
                    int dIdx = dstRow + (wx << 2);
                    dstWorkBgra[dIdx] = srcBgra[sIdx];
                    dstWorkBgra[dIdx + 1] = srcBgra[sIdx + 1];
                    dstWorkBgra[dIdx + 2] = srcBgra[sIdx + 2];
                    dstWorkBgra[dIdx + 3] = 255;
                }
            });
        }

        private static void CompositeOverlayBbox(byte[] dstBgra, int dstW, int dstH, byte[] overlayWork, int fx1, int fy1, int fx2, int fy2)
        {
            int dstX1 = Math.Clamp((fx1 * dstW) / WorkW, 0, dstW - 1);
            int dstY1 = Math.Clamp((fy1 * dstH) / WorkH, 0, dstH - 1);
            int dstX2 = Math.Clamp(((fx2 + 1) * dstW) / WorkW, 0, dstW);
            int dstY2 = Math.Clamp(((fy2 + 1) * dstH) / WorkH, 0, dstH);

            float scaleX = (float)WorkW / dstW;
            float scaleY = (float)WorkH / dstH;

            Parallel.For(dstY1, dstY2, dy =>
            {
                float wyF = dy * scaleY;
                int wy0 = (int)wyF;
                if (wy0 >= WorkH) wy0 = WorkH - 1;
                int wy1 = wy0 + 1 < WorkH ? wy0 + 1 : wy0;
                int fyFrac = (int)((wyF - wy0) * 256.0f);
                int fyInv = 256 - fyFrac;

                int row0 = wy0 * WorkW * 4;
                int row1 = wy1 * WorkW * 4;
                int dstRow = dy * dstW * 4;

                for (int dx = dstX1; dx < dstX2; dx++)
                {
                    float wxF = dx * scaleX;
                    int wx0 = (int)wxF;
                    if (wx0 >= WorkW) wx0 = WorkW - 1;
                    int wx1 = wx0 + 1 < WorkW ? wx0 + 1 : wx0;

                    int i00 = row0 + (wx0 << 2);
                    byte a00 = overlayWork[i00 + 3];
                    int i01 = row0 + (wx1 << 2);
                    byte a01 = overlayWork[i01 + 3];
                    int i10 = row1 + (wx0 << 2);
                    byte a10 = overlayWork[i10 + 3];
                    int i11 = row1 + (wx1 << 2);
                    byte a11 = overlayWork[i11 + 3];

                    if ((a00 | a01 | a10 | a11) == 0) continue;

                    int fxFrac = (int)((wxF - wx0) * 256.0f);
                    int fxInv = 256 - fxFrac;

                    int w00 = fxInv * fyInv;
                    int w01 = fxFrac * fyInv;
                    int w10 = fxInv * fyFrac;
                    int w11 = fxFrac * fyFrac;

                    int a = (a00 * w00 + a01 * w01 + a10 * w10 + a11 * w11) >> 16;
                    if (a <= 1) continue;

                    int b = (overlayWork[i00] * w00 + overlayWork[i01] * w01 + overlayWork[i10] * w10 + overlayWork[i11] * w11) >> 16;
                    int g = (overlayWork[i00 + 1] * w00 + overlayWork[i01 + 1] * w01 + overlayWork[i10 + 1] * w10 + overlayWork[i11 + 1] * w11) >> 16;
                    int r = (overlayWork[i00 + 2] * w00 + overlayWork[i01 + 2] * w01 + overlayWork[i10 + 2] * w10 + overlayWork[i11 + 2] * w11) >> 16;

                    int dIdx = dstRow + (dx << 2);
                    if (a >= 254)
                    {
                        dstBgra[dIdx] = (byte)b;
                        dstBgra[dIdx + 1] = (byte)g;
                        dstBgra[dIdx + 2] = (byte)r;
                    }
                    else
                    {
                        int invA = 255 - a;
                        dstBgra[dIdx] = (byte)((b * a + dstBgra[dIdx] * invA) >> 8);
                        dstBgra[dIdx + 1] = (byte)((g * a + dstBgra[dIdx + 1] * invA) >> 8);
                        dstBgra[dIdx + 2] = (byte)((r * a + dstBgra[dIdx + 2] * invA) >> 8);
                    }
                }
            });
        }

        private static void ApplyInstantNativeArFallback(byte[] bgra, int w, int h, int arMode)
        {
            int cx = w / 2;
            int cy = (int)(h * 0.40);
            int rx = w / 7;
            int ry = h / 14;

            if (arMode == 1) // Cyber Visor
            {
                for (int y = Math.Max(0, cy - ry); y < Math.Min(h, cy + ry); y++)
                {
                    for (int x = Math.Max(0, cx - rx); x < Math.Min(w, cx + rx); x++)
                    {
                        double dx = (double)(x - cx) / rx;
                        double dy = (double)(y - cy) / ry;
                        if (dx * dx + dy * dy <= 1.0)
                        {
                            int idx = (y * w + x) * 4;
                            bgra[idx] = 255;     // B
                            bgra[idx + 1] = 220; // G
                            bgra[idx + 2] = 20;  // R
                        }
                    }
                }
            }
            else if (arMode == 4) // Face Pixel Anonymizer
            {
                int box = 18;
                for (int y = Math.Max(0, cy - ry * 2); y < Math.Min(h, cy + ry * 3); y += box)
                {
                    for (int x = Math.Max(0, cx - rx); x < Math.Min(w, cx + rx); x += box)
                    {
                        int sIdx = (y * w + x) * 4;
                        byte b = bgra[sIdx], g = bgra[sIdx + 1], r = bgra[sIdx + 2];
                        for (int dy = 0; dy < box && y + dy < h; dy++)
                        {
                            for (int dx = 0; dx < box && x + dx < w; dx++)
                            {
                                int dIdx = ((y + dy) * w + (x + dx)) * 4;
                                bgra[dIdx] = b; bgra[dIdx + 1] = g; bgra[dIdx + 2] = r;
                            }
                        }
                    }
                }
            }
        }

        /// <summary>
        /// Pauses live overlay rendering without killing the warm Python/DirectML process.
        /// This guarantees instant 0ms resume when unchecking and immediately re-checking "Aktivieren".
        /// </summary>
        public void Pause()
        {
            lock (_sync)
            {
                _hasProcessedFrame = false;
                try
                {
                    if (_accessor != null)
                    {
                        int cmd = _accessor.ReadInt32(0);
                        if (cmd == 2) _accessor.Write(0, 0);
                    }
                }
                catch { }
            }
        }

        public void Stop() => StopWorker();

        public void StopWorker()
        {
            lock (_sync)
            {
                try { _accessor?.Write(0, 99); } catch { }
                try
                {
                    if (_pythonProc != null && !_pythonProc.HasExited)
                        _pythonProc.Kill(true);
                }
                catch { }
                _pythonProc = null;
                _hasProcessedFrame = false;
                _pythonStatus = 0;
                try { _accessor?.Write(0, 0); } catch { }
            }
        }

        public void Dispose()
        {
            StopWorker();
            try { _accessor?.Dispose(); } catch { }
            try { _mmf?.Dispose(); } catch { }
            _accessor = null;
            _mmf = null;
        }
    }

    // =========================================================================
    // 9. REAL-TIME STUDIO VOICE CHANGER ENGINE (DSP PITCH/FORMANT/MODULATION)
    // =========================================================================
    public sealed class FunkenwerkVoiceChangerEngine : IDisposable
    {
        private const int SampleRate = 48000;
        private const int BufferSamples = 960; // 20 ms ultra-low-latency frame
        private const int BufferBytes = BufferSamples * 2;
        private const int NumBuffers = 6;
        private const int GrainSize = 1024;
        private const int RingCap = 16384;
        private const int RingMask = RingCap - 1;

        [StructLayout(LayoutKind.Sequential)]
        private struct WAVEFORMATEX
        {
            public ushort wFormatTag;
            public ushort nChannels;
            public uint nSamplesPerSec;
            public uint nAvgBytesPerSec;
            public ushort nBlockAlign;
            public ushort wBitsPerSample;
            public ushort cbSize;
        }

        [StructLayout(LayoutKind.Sequential)]
        private struct WAVEHDR
        {
            public IntPtr lpData;
            public uint dwBufferLength;
            public uint dwBytesRecorded;
            public IntPtr dwUser;
            public uint dwFlags;
            public uint dwLoops;
            public IntPtr lpNext;
            public IntPtr reserved;
        }

        [StructLayout(LayoutKind.Sequential, CharSet = CharSet.Unicode)]
        private struct WAVEOUTCAPS
        {
            public ushort wMid;
            public ushort wPid;
            public uint vDriverVersion;
            [MarshalAs(UnmanagedType.ByValTStr, SizeConst = 32)]
            public string szPname;
            public uint dwFormats;
            public ushort wChannels;
            public ushort wReserved1;
            public uint dwSupport;
        }

        private delegate void WaveDelegate(IntPtr hWave, uint uMsg, IntPtr dwInstance, IntPtr dwParam1, IntPtr dwParam2);

        private const int CALLBACK_FUNCTION = 0x00030000;
        private const uint WIM_DATA = 0x3C0;
        private const int WAVE_MAPPER = -1;

        [DllImport("winmm.dll")]
        private static extern int waveInOpen(out IntPtr hWaveIn, int uDeviceID, ref WAVEFORMATEX lpFormat, WaveDelegate dwCallback, IntPtr dwInstance, int dwFlags);
        [DllImport("winmm.dll")]
        private static extern int waveInPrepareHeader(IntPtr hWaveIn, IntPtr lpWaveInHdr, int uSize);
        [DllImport("winmm.dll")]
        private static extern int waveInUnprepareHeader(IntPtr hWaveIn, IntPtr lpWaveInHdr, int uSize);
        [DllImport("winmm.dll")]
        private static extern int waveInAddBuffer(IntPtr hWaveIn, IntPtr lpWaveInHdr, int uSize);
        [DllImport("winmm.dll")]
        private static extern int waveInStart(IntPtr hWaveIn);
        [DllImport("winmm.dll")]
        private static extern int waveInReset(IntPtr hWaveIn);
        [DllImport("winmm.dll")]
        private static extern int waveInClose(IntPtr hWaveIn);

        [DllImport("winmm.dll")]
        private static extern int waveOutOpen(out IntPtr hWaveOut, int uDeviceID, ref WAVEFORMATEX lpFormat, IntPtr dwCallback, IntPtr dwInstance, int dwFlags);
        [DllImport("winmm.dll")]
        private static extern int waveOutPrepareHeader(IntPtr hWaveOut, IntPtr lpWaveOutHdr, int uSize);
        [DllImport("winmm.dll")]
        private static extern int waveOutUnprepareHeader(IntPtr hWaveOut, IntPtr lpWaveOutHdr, int uSize);
        [DllImport("winmm.dll")]
        private static extern int waveOutWrite(IntPtr hWaveOut, IntPtr lpWaveOutHdr, int uSize);
        [DllImport("winmm.dll")]
        private static extern int waveOutReset(IntPtr hWaveOut);
        [DllImport("winmm.dll")]
        private static extern int waveOutClose(IntPtr hWaveOut);
        [DllImport("winmm.dll")]
        private static extern uint waveOutGetNumDevs();
        [DllImport("winmm.dll", CharSet = CharSet.Unicode)]
        private static extern int waveOutGetDevCaps(IntPtr uDeviceID, out WAVEOUTCAPS pwoc, int cbwoc);

        private IntPtr _hWaveIn = IntPtr.Zero;
        private IntPtr _hWaveOut = IntPtr.Zero;
        private WaveDelegate? _waveInProc;
        private readonly IntPtr[] _inHdrPtrs = new IntPtr[NumBuffers];
        private readonly IntPtr[] _inDataPtrs = new IntPtr[NumBuffers];
        private readonly IntPtr[] _outHdrPtrs = new IntPtr[NumBuffers];
        private readonly IntPtr[] _outDataPtrs = new IntPtr[NumBuffers];
        private int _outBufIdx = 0;
        private volatile bool _isRunning = false;
        private readonly object _sync = new();

        // DSP State
        private readonly float[] _ringBuffer = new float[RingCap];
        private int _ringWritePos = 0;
        private double _grainPhase = 0.0;
        private double _ringModPhase = 0.0;
        private double _subOctavePhase = 1.0;
        private float _prevSample = 0f;
        private float _formantState1 = 0f;
        private float _formantState2 = 0f;
        private float _radioBpState1 = 0f;
        private float _radioBpState2 = 0f;
        private readonly float[] _reverbComb1 = new float[1687];
        private readonly float[] _reverbComb2 = new float[2281];
        private readonly float[] _reverbComb3 = new float[3119];
        private int _revIdx1 = 0, _revIdx2 = 0, _revIdx3 = 0;
        private volatile float _currentOutputLevel = 0f;

        // 4-second Test Sample Recording / Playback
        private readonly short[] _testSampleBuffer = new short[SampleRate * 4];
        private volatile int _testRecordPos = 0;
        private volatile bool _isRecordingSample = false;
        private volatile bool _hasRecordedSample = false;

        // Live DSP Parameters
        private volatile bool _enabled = false;
        private volatile bool _liveMonitor = false;
        private volatile float _pitchSemitones = 0f;
        private volatile float _formantShift = 0f;
        private volatile int _robotMix = 0;
        private volatile int _reverbMix = 0;
        private volatile int _bassBoost = 0;
        private volatile int _noiseGate = 10;
        private volatile string _preset = "none";
        private int _activeInDev = -1;
        private int _activeOutDev = -1;

        public float CurrentOutputLevel => _currentOutputLevel;
        public bool IsRunning => _isRunning;
        public bool IsRecordingSample => _isRecordingSample;
        public bool HasRecordedSample => _hasRecordedSample;

        public static List<string> GetAudioOutputDevices()
        {
            var list = new List<string> { "Standard-Ausgabegerät (Kopfhörer / Lautsprecher)" };
            try
            {
                uint count = waveOutGetNumDevs();
                for (uint i = 0; i < count; i++)
                {
                    if (waveOutGetDevCaps((IntPtr)i, out var caps, Marshal.SizeOf<WAVEOUTCAPS>()) == 0)
                    {
                        if (!string.IsNullOrWhiteSpace(caps.szPname))
                            list.Add(caps.szPname.Trim());
                    }
                }
            }
            catch { }
            return list;
        }

        public static void ApplyPresetToConfig(CameraStudioConfig cfg, string presetKey)
        {
            cfg.VoiceChangerPreset = presetKey;
            switch (presetKey)
            {
                case "none":
                    cfg.VoicePitchSemitones = 0;
                    cfg.VoiceFormantShift = 0;
                    cfg.VoiceRobotMix = 0;
                    cfg.VoiceReverbMix = 0;
                    cfg.VoiceBassBoost = 0;
                    break;
                case "anonymous": // Anonymous / Hacker
                    cfg.VoicePitchSemitones = -5.5;
                    cfg.VoiceFormantShift = -35;
                    cfg.VoiceRobotMix = 18;
                    cfg.VoiceReverbMix = 15;
                    cfg.VoiceBassBoost = 55;
                    break;
                case "robot": // Cyborg / Roboter
                    cfg.VoicePitchSemitones = -1.5;
                    cfg.VoiceFormantShift = -15;
                    cfg.VoiceRobotMix = 82;
                    cfg.VoiceReverbMix = 22;
                    cfg.VoiceBassBoost = 30;
                    break;
                case "demon": // Dämon / Dunkler Lord
                    cfg.VoicePitchSemitones = -8.5;
                    cfg.VoiceFormantShift = -60;
                    cfg.VoiceRobotMix = 32;
                    cfg.VoiceReverbMix = 68;
                    cfg.VoiceBassBoost = 85;
                    break;
                case "chipmunk": // Chipmunk / Helium
                    cfg.VoicePitchSemitones = +8.5;
                    cfg.VoiceFormantShift = +65;
                    cfg.VoiceRobotMix = 0;
                    cfg.VoiceReverbMix = 8;
                    cfg.VoiceBassBoost = 0;
                    break;
                case "female": // Weibliche Stimme (KI-Formant)
                    cfg.VoicePitchSemitones = +4.2;
                    cfg.VoiceFormantShift = +48;
                    cfg.VoiceRobotMix = 0;
                    cfg.VoiceReverbMix = 12;
                    cfg.VoiceBassBoost = 0;
                    break;
                case "deep_male": // Tiefe Männerstimme (Radio-Bass)
                    cfg.VoicePitchSemitones = -3.8;
                    cfg.VoiceFormantShift = -32;
                    cfg.VoiceRobotMix = 0;
                    cfg.VoiceReverbMix = 10;
                    cfg.VoiceBassBoost = 65;
                    break;
                case "alien": // Alien / Extraterrestrisch
                    cfg.VoicePitchSemitones = +3.5;
                    cfg.VoiceFormantShift = -50;
                    cfg.VoiceRobotMix = 75;
                    cfg.VoiceReverbMix = 48;
                    cfg.VoiceBassBoost = 15;
                    break;
                case "radio": // Funkgerät / Walkie-Talkie
                    cfg.VoicePitchSemitones = 0.0;
                    cfg.VoiceFormantShift = +25;
                    cfg.VoiceRobotMix = 28;
                    cfg.VoiceReverbMix = 5;
                    cfg.VoiceBassBoost = 0;
                    break;
                case "autotune": // Cyber-Tune / Synth-Star
                    cfg.VoicePitchSemitones = +2.0;
                    cfg.VoiceFormantShift = +20;
                    cfg.VoiceRobotMix = 42;
                    cfg.VoiceReverbMix = 55;
                    cfg.VoiceBassBoost = 35;
                    break;
                case "monster": // Titan / Riesen-Monster
                    cfg.VoicePitchSemitones = -10.5;
                    cfg.VoiceFormantShift = -75;
                    cfg.VoiceRobotMix = 25;
                    cfg.VoiceReverbMix = 50;
                    cfg.VoiceBassBoost = 95;
                    break;
                case "ghost": // Geist / Echo-Sphäre
                    cfg.VoicePitchSemitones = +1.5;
                    cfg.VoiceFormantShift = -25;
                    cfg.VoiceRobotMix = 22;
                    cfg.VoiceReverbMix = 92;
                    cfg.VoiceBassBoost = 10;
                    break;
            }
        }

        public static string GetPresetDisplayName(string presetKey) => presetKey switch
        {
            "anonymous" => "🕵️ Anonymous / Hacker",
            "robot" => "🤖 Cyborg / Roboter",
            "demon" => "👹 Dämon / Dunkler Lord",
            "chipmunk" => "🐿️ Chipmunk / Helium",
            "female" => "👩 Weibliche Stimme (Formant)",
            "deep_male" => "🎙️ Tiefe Studio-Stimme",
            "alien" => "👽 Alien / Extraterrestrisch",
            "radio" => "📻 Funkgerät / Walkie-Talkie",
            "autotune" => "⚡ Cyber-Tune / Synth-Voice",
            "monster" => "🦖 Titan / Monster",
            "ghost" => "👻 Geist / Echo-Sphäre",
            _ => "🟢 Natürlich (Original)"
        };

        /// <summary>
        /// Builds an FFmpeg audio filter chain matching the current Voice Changer settings for MP4 video recording.
        /// </summary>
        public static string BuildFfmpegAudioFilter(CameraStudioConfig cfg, string baseVolStr)
        {
            if (!cfg.VoiceChangerEnabled)
                return $"volume={baseVolStr}";

            var filters = new List<string> { $"volume={baseVolStr}" };

            double semi = Math.Clamp(cfg.VoicePitchSemitones, -12.0, 12.0);
            double formant = Math.Clamp(cfg.VoiceFormantShift, -100.0, 100.0);
            double combinedSemi = semi + (formant * 0.035);
            if (Math.Abs(combinedSemi) > 0.15)
            {
                double pitchFactor = Math.Pow(2.0, combinedSemi / 12.0);
                double tempoFactor = Math.Clamp(1.0 / pitchFactor, 0.5, 2.0);
                string pfStr = pitchFactor.ToString("0.000", CultureInfo.InvariantCulture);
                string tfStr = tempoFactor.ToString("0.000", CultureInfo.InvariantCulture);
                filters.Add($"asetrate=48000*{pfStr},aresample=48000,atempo={tfStr}");
            }

            if (string.Equals(cfg.VoiceChangerPreset, "radio", StringComparison.OrdinalIgnoreCase))
            {
                filters.Add("highpass=f=420,lowpass=f=2900,acrusher=bits=8:mix=0.45");
            }
            else if (cfg.VoiceRobotMix > 10)
            {
                double depth = Math.Clamp(cfg.VoiceRobotMix / 100.0, 0.1, 0.95);
                filters.Add($"tremolo=f=65:d={depth.ToString("0.00", CultureInfo.InvariantCulture)}");
            }

            if (cfg.VoiceBassBoost > 8)
            {
                int gainDb = Math.Clamp(cfg.VoiceBassBoost / 5, 2, 18);
                filters.Add($"bass=g={gainDb}:f=110");
            }

            if (cfg.VoiceReverbMix > 8)
            {
                double decay = Math.Clamp(cfg.VoiceReverbMix / 120.0, 0.15, 0.85);
                string decStr = decay.ToString("0.00", CultureInfo.InvariantCulture);
                filters.Add($"aecho=0.8:0.88:55|115:{decStr}|{decStr}");
            }

            return string.Join(",", filters);
        }

        public void SyncFromConfig(CameraStudioConfig cfg, int micDeviceIndex = -1, int outDeviceIndex = -1)
        {
            _enabled = cfg.VoiceChangerEnabled;
            _liveMonitor = cfg.VoiceLiveMonitor;
            _pitchSemitones = (float)Math.Clamp(cfg.VoicePitchSemitones, -12.0, 12.0);
            _formantShift = (float)Math.Clamp(cfg.VoiceFormantShift, -100.0, 100.0);
            _robotMix = Math.Clamp(cfg.VoiceRobotMix, 0, 100);
            _reverbMix = Math.Clamp(cfg.VoiceReverbMix, 0, 100);
            _bassBoost = Math.Clamp(cfg.VoiceBassBoost, 0, 100);
            _noiseGate = Math.Clamp(cfg.VoiceNoiseGate, 0, 100);
            _preset = cfg.VoiceChangerPreset ?? "none";

            bool shouldRun = _enabled && (_liveMonitor || _isRecordingSample);
            if (shouldRun)
            {
                if (!_isRunning || _activeInDev != micDeviceIndex || _activeOutDev != outDeviceIndex)
                {
                    Start(micDeviceIndex, outDeviceIndex);
                }
            }
            else if (_isRunning && !_isRecordingSample)
            {
                Stop();
            }
        }

        public void StartSampleRecording(CameraStudioConfig cfg, int micDeviceIndex = -1)
        {
            _testRecordPos = 0;
            _hasRecordedSample = false;
            _isRecordingSample = true;
            SyncFromConfig(cfg, micDeviceIndex, _activeOutDev);
            if (!_isRunning)
            {
                Start(micDeviceIndex, _activeOutDev);
            }
        }

        public void PlayRecordedSampleWithCurrentDsp(CameraStudioConfig cfg, int outDeviceIndex = -1)
        {
            if (!_hasRecordedSample) return;
            SyncFromConfig(cfg, _activeInDev, outDeviceIndex);

            Task.Run(() =>
            {
                IntPtr hOut = IntPtr.Zero;
                IntPtr hdrPtr = IntPtr.Zero;
                IntPtr dataPtr = IntPtr.Zero;
                try
                {
                    int totalSamples = _testSampleBuffer.Length;
                    short[] processed = new short[totalSamples];
                    short[] chunk = new short[BufferSamples];
                    for (int offset = 0; offset + BufferSamples <= totalSamples; offset += BufferSamples)
                    {
                        Array.Copy(_testSampleBuffer, offset, chunk, 0, BufferSamples);
                        ProcessDspBlockInPlace(chunk, BufferSamples);
                        Array.Copy(chunk, 0, processed, offset, BufferSamples);
                    }

                    var fmt = new WAVEFORMATEX
                    {
                        wFormatTag = 1,
                        nChannels = 1,
                        nSamplesPerSec = SampleRate,
                        wBitsPerSample = 16,
                        nBlockAlign = 2,
                        nAvgBytesPerSec = SampleRate * 2,
                        cbSize = 0
                    };

                    int devId = outDeviceIndex < 0 ? WAVE_MAPPER : outDeviceIndex;
                    if (waveOutOpen(out hOut, devId, ref fmt, IntPtr.Zero, IntPtr.Zero, 0) != 0)
                        return;

                    int byteLen = totalSamples * 2;
                    dataPtr = Marshal.AllocHGlobal(byteLen);
                    Marshal.Copy(processed, 0, dataPtr, totalSamples);

                    var hdr = new WAVEHDR
                    {
                        lpData = dataPtr,
                        dwBufferLength = (uint)byteLen,
                        dwFlags = 0
                    };
                    hdrPtr = Marshal.AllocHGlobal(Marshal.SizeOf<WAVEHDR>());
                    Marshal.StructureToPtr(hdr, hdrPtr, false);

                    waveOutPrepareHeader(hOut, hdrPtr, Marshal.SizeOf<WAVEHDR>());
                    waveOutWrite(hOut, hdrPtr, Marshal.SizeOf<WAVEHDR>());
                    Thread.Sleep(4150);
                    waveOutReset(hOut);
                    waveOutUnprepareHeader(hOut, hdrPtr, Marshal.SizeOf<WAVEHDR>());
                }
                catch { }
                finally
                {
                    if (hOut != IntPtr.Zero) try { waveOutClose(hOut); } catch { }
                    if (hdrPtr != IntPtr.Zero) Marshal.FreeHGlobal(hdrPtr);
                    if (dataPtr != IntPtr.Zero) Marshal.FreeHGlobal(dataPtr);
                }
            });
        }

        public void Start(int inDeviceId = -1, int outDeviceId = -1)
        {
            lock (_sync)
            {
                StopInternal();
                try
                {
                    _activeInDev = inDeviceId;
                    _activeOutDev = outDeviceId;

                    var fmt = new WAVEFORMATEX
                    {
                        wFormatTag = 1, // PCM
                        nChannels = 1,
                        nSamplesPerSec = SampleRate,
                        wBitsPerSample = 16,
                        nBlockAlign = 2,
                        nAvgBytesPerSec = SampleRate * 2,
                        cbSize = 0
                    };

                    int outDev = outDeviceId < 0 ? WAVE_MAPPER : outDeviceId;
                    if (waveOutOpen(out _hWaveOut, outDev, ref fmt, IntPtr.Zero, IntPtr.Zero, 0) != 0)
                    {
                        _hWaveOut = IntPtr.Zero;
                    }
                    else
                    {
                        for (int i = 0; i < NumBuffers; i++)
                        {
                            _outDataPtrs[i] = Marshal.AllocHGlobal(BufferBytes);
                            var hdr = new WAVEHDR
                            {
                                lpData = _outDataPtrs[i],
                                dwBufferLength = BufferBytes,
                                dwFlags = 0
                            };
                            _outHdrPtrs[i] = Marshal.AllocHGlobal(Marshal.SizeOf<WAVEHDR>());
                            Marshal.StructureToPtr(hdr, _outHdrPtrs[i], false);
                            waveOutPrepareHeader(_hWaveOut, _outHdrPtrs[i], Marshal.SizeOf<WAVEHDR>());
                        }
                    }

                    _waveInProc = OnWaveInCallback;
                    int inDev = inDeviceId < 0 ? WAVE_MAPPER : inDeviceId;
                    if (waveInOpen(out _hWaveIn, inDev, ref fmt, _waveInProc, IntPtr.Zero, CALLBACK_FUNCTION) != 0)
                    {
                        _hWaveIn = IntPtr.Zero;
                        StopInternal();
                        return;
                    }

                    for (int i = 0; i < NumBuffers; i++)
                    {
                        _inDataPtrs[i] = Marshal.AllocHGlobal(BufferBytes);
                        var hdr = new WAVEHDR
                        {
                            lpData = _inDataPtrs[i],
                            dwBufferLength = BufferBytes,
                            dwFlags = 0
                        };
                        _inHdrPtrs[i] = Marshal.AllocHGlobal(Marshal.SizeOf<WAVEHDR>());
                        Marshal.StructureToPtr(hdr, _inHdrPtrs[i], false);
                        waveInPrepareHeader(_hWaveIn, _inHdrPtrs[i], Marshal.SizeOf<WAVEHDR>());
                        waveInAddBuffer(_hWaveIn, _inHdrPtrs[i], Marshal.SizeOf<WAVEHDR>());
                    }

                    _isRunning = true;
                    waveInStart(_hWaveIn);
                }
                catch
                {
                    StopInternal();
                }
            }
        }

        private void OnWaveInCallback(IntPtr hWaveIn, uint uMsg, IntPtr dwInstance, IntPtr dwParam1, IntPtr dwParam2)
        {
            if (uMsg != WIM_DATA || !_isRunning || dwParam1 == IntPtr.Zero) return;

            try
            {
                var inHdr = Marshal.PtrToStructure<WAVEHDR>(dwParam1);
                int recordedSamples = (int)(inHdr.dwBytesRecorded / 2);
                if (recordedSamples > 0 && recordedSamples <= BufferSamples)
                {
                    short[] pcm = new short[recordedSamples];
                    Marshal.Copy(inHdr.lpData, pcm, 0, recordedSamples);

                    // Record raw voice snippet if 4s sample recorder is active
                    if (_isRecordingSample)
                    {
                        int pos = _testRecordPos;
                        int copyCount = Math.Min(recordedSamples, _testSampleBuffer.Length - pos);
                        if (copyCount > 0)
                        {
                            Array.Copy(pcm, 0, _testSampleBuffer, pos, copyCount);
                            _testRecordPos = pos + copyCount;
                        }
                        if (_testRecordPos >= _testSampleBuffer.Length)
                        {
                            _isRecordingSample = false;
                            _hasRecordedSample = true;
                        }
                    }

                    // Run real-time DSP Voice Changer pipeline
                    ProcessDspBlockInPlace(pcm, recordedSamples);

                    // Output to headphones/speakers if Live Monitoring is enabled
                    if (_liveMonitor && _hWaveOut != IntPtr.Zero)
                    {
                        int outIdx = Interlocked.Increment(ref _outBufIdx) % NumBuffers;
                        IntPtr outHdrPtr = _outHdrPtrs[outIdx];
                        IntPtr outDataPtr = _outDataPtrs[outIdx];
                        if (outHdrPtr != IntPtr.Zero && outDataPtr != IntPtr.Zero)
                        {
                            Marshal.Copy(pcm, 0, outDataPtr, recordedSamples);
                            waveOutWrite(_hWaveOut, outHdrPtr, Marshal.SizeOf<WAVEHDR>());
                        }
                    }
                }

                if (_isRunning && _hWaveIn != IntPtr.Zero)
                {
                    waveInAddBuffer(_hWaveIn, dwParam1, Marshal.SizeOf<WAVEHDR>());
                }
            }
            catch { }
        }

        /// <summary>
        /// Processes a 16-bit PCM block in-place through the real-time DSP Voice Changer pipeline:
        /// 1. Noise Gate
        /// 2. Dual-Tap Overlap-Add Hann-Windowed Pitch Shifter (-12..+12 semitones)
        /// 3. Formant Envelope Warping Filter (-100..+100%)
        /// 4. Sub-Octave Bass Synthesizer (for Demon / Anonymous / Monster)
        /// 5. Ring Modulator / Vocoder Carrier (for Robot / Cyborg / Alien)
        /// 6. Radio Bandpass + Bitcrusher (for Walkie-Talkie)
        /// 7. Multi-Tap Schroeder Comb Reverb (for Ghost / Demon / Studio Room)
        /// 8. Soft-Knee Studio Limiter
        /// </summary>
        public void ProcessDspBlockInPlace(short[] pcm, int count)
        {
            if (!_enabled || count <= 0) return;

            // Measure RMS for Noise Gate
            double sumSq = 0;
            for (int i = 0; i < count; i++)
            {
                double norm = pcm[i] / 32768.0;
                sumSq += norm * norm;
            }
            double rms = Math.Sqrt(sumSq / count);
            double gateThresh = (_noiseGate / 100.0) * 0.045;
            float gateGain = rms < gateThresh ? (float)(rms / Math.Max(0.0001, gateThresh)) : 1.0f;

            double semi = _pitchSemitones + (_formantShift * 0.025);
            double pitchRatio = Math.Pow(2.0, semi / 12.0);
            double phaseStep = (1.0 - pitchRatio) / GrainSize;

            float formantAmt = _formantShift / 100.0f;
            float robotAmt = _robotMix / 100.0f;
            float reverbAmt = _reverbMix / 100.0f;
            float bassAmt = _bassBoost / 100.0f;
            bool isRadio = string.Equals(_preset, "radio", StringComparison.OrdinalIgnoreCase);

            float peak = 0f;

            for (int i = 0; i < count; i++)
            {
                float x = (pcm[i] / 32768.0f) * gateGain;

                // Write to circular buffer
                _ringBuffer[_ringWritePos] = x;

                // Dual-tap Hann-windowed pitch shift
                float yPitch;
                if (Math.Abs(semi) < 0.08)
                {
                    yPitch = x;
                }
                else
                {
                    _grainPhase += phaseStep;
                    if (_grainPhase >= 1.0) _grainPhase -= 1.0;
                    else if (_grainPhase < 0.0) _grainPhase += 1.0;

                    double p1 = _grainPhase;
                    double p2 = p1 + 0.5;
                    if (p2 >= 1.0) p2 -= 1.0;

                    double delay1 = p1 * GrainSize;
                    double delay2 = p2 * GrainSize;

                    float s1 = ReadFractional(_ringWritePos - delay1);
                    float s2 = ReadFractional(_ringWritePos - delay2);

                    // Hann window weights: w1 + w2 == 1.0
                    float w1 = (float)(0.5 * (1.0 - Math.Cos(2.0 * Math.PI * p1)));
                    float w2 = 1.0f - w1;
                    yPitch = s1 * w1 + s2 * w2;
                }

                _ringWritePos = (_ringWritePos + 1) & RingMask;

                // Formant spectral tilt / resonant filter
                float yFormant = yPitch;
                if (Math.Abs(formantAmt) > 0.02f)
                {
                    _formantState1 += 0.28f * (yPitch - _formantState1);
                    _formantState2 += 0.08f * (yPitch - _formantState2);
                    float highFormant = yPitch - _formantState1;
                    float midFormant = _formantState1 - _formantState2;
                    if (formantAmt > 0)
                        yFormant = yPitch + highFormant * (formantAmt * 1.35f) + midFormant * (formantAmt * 0.45f);
                    else
                        yFormant = _formantState1 * (-formantAmt * 0.85f) + yPitch * (1.0f + formantAmt * 0.55f);
                }

                // Sub-Octave Bass Enhancer (zero-crossing flip divider + lowpass)
                float yBass = yFormant;
                if (bassAmt > 0.02f)
                {
                    if ((_prevSample < 0f && yFormant >= 0f) || (_prevSample >= 0f && yFormant < 0f))
                    {
                        _subOctavePhase = -_subOctavePhase;
                    }
                    _prevSample = yFormant;
                    float subSignal = _formantState2 * (float)_subOctavePhase;
                    yBass = yFormant + subSignal * (bassAmt * 0.95f);
                }

                // Robot Ring Modulator / Cyber Vocoder
                float yRobot = yBass;
                if (robotAmt > 0.02f)
                {
                    double modFreq = string.Equals(_preset, "alien", StringComparison.OrdinalIgnoreCase) ? 115.0 : 58.0;
                    _ringModPhase += (2.0 * Math.PI * modFreq) / SampleRate;
                    if (_ringModPhase > 2.0 * Math.PI) _ringModPhase -= 2.0 * Math.PI;
                    float carrier = (float)Math.Sin(_ringModPhase);
                    yRobot = yBass * (1.0f - robotAmt * 0.80f) + (yBass * carrier) * (robotAmt * 1.15f);
                }

                // Walkie-Talkie / Funkgerät Bandpass + Overdrive
                if (isRadio)
                {
                    _radioBpState1 += 0.32f * (yRobot - _radioBpState1);
                    _radioBpState2 += 0.06f * (yRobot - _radioBpState2);
                    float bp = _radioBpState1 - _radioBpState2;
                    yRobot = (float)Math.Tanh(bp * 3.2);
                }

                // Multi-Tap Comb Reverb
                float yOut = yRobot;
                if (reverbAmt > 0.02f)
                {
                    float fb = 0.42f + reverbAmt * 0.36f;
                    float c1 = _reverbComb1[_revIdx1];
                    float c2 = _reverbComb2[_revIdx2];
                    float c3 = _reverbComb3[_revIdx3];

                    _reverbComb1[_revIdx1] = yRobot + c1 * fb;
                    _reverbComb2[_revIdx2] = yRobot + c2 * (fb * 0.94f);
                    _reverbComb3[_revIdx3] = yRobot + c3 * (fb * 0.88f);

                    if (++_revIdx1 >= _reverbComb1.Length) _revIdx1 = 0;
                    if (++_revIdx2 >= _reverbComb2.Length) _revIdx2 = 0;
                    if (++_revIdx3 >= _reverbComb3.Length) _revIdx3 = 0;

                    float wet = (c1 + c2 + c3) * 0.35f;
                    yOut = yRobot * (1.0f - reverbAmt * 0.35f) + wet * (reverbAmt * 0.75f);
                }

                // Soft-knee limiter
                if (yOut > 0.95f) yOut = 0.95f + 0.05f * (float)Math.Tanh((yOut - 0.95f) * 8.0f);
                else if (yOut < -0.95f) yOut = -0.95f + 0.05f * (float)Math.Tanh((yOut + 0.95f) * 8.0f);

                float absVal = Math.Abs(yOut);
                if (absVal > peak) peak = absVal;

                pcm[i] = (short)Math.Clamp((int)(yOut * 32767.0f), short.MinValue, short.MaxValue);
            }

            _currentOutputLevel = peak;
        }

        private float ReadFractional(double pos)
        {
            while (pos < 0) pos += RingCap;
            int idx0 = ((int)pos) & RingMask;
            int idx1 = (idx0 + 1) & RingMask;
            float frac = (float)(pos - Math.Floor(pos));
            return _ringBuffer[idx0] * (1.0f - frac) + _ringBuffer[idx1] * frac;
        }

        public void Stop()
        {
            lock (_sync)
            {
                StopInternal();
            }
        }

        private void StopInternal()
        {
            _isRunning = false;
            _currentOutputLevel = 0f;

            if (_hWaveIn != IntPtr.Zero)
            {
                try { waveInReset(_hWaveIn); } catch { }
                for (int i = 0; i < NumBuffers; i++)
                {
                    if (_inHdrPtrs[i] != IntPtr.Zero)
                    {
                        try { waveInUnprepareHeader(_hWaveIn, _inHdrPtrs[i], Marshal.SizeOf<WAVEHDR>()); } catch { }
                        Marshal.FreeHGlobal(_inHdrPtrs[i]);
                        _inHdrPtrs[i] = IntPtr.Zero;
                    }
                    if (_inDataPtrs[i] != IntPtr.Zero)
                    {
                        Marshal.FreeHGlobal(_inDataPtrs[i]);
                        _inDataPtrs[i] = IntPtr.Zero;
                    }
                }
                try { waveInClose(_hWaveIn); } catch { }
                _hWaveIn = IntPtr.Zero;
            }

            if (_hWaveOut != IntPtr.Zero)
            {
                try { waveOutReset(_hWaveOut); } catch { }
                for (int i = 0; i < NumBuffers; i++)
                {
                    if (_outHdrPtrs[i] != IntPtr.Zero)
                    {
                        try { waveOutUnprepareHeader(_hWaveOut, _outHdrPtrs[i], Marshal.SizeOf<WAVEHDR>()); } catch { }
                        Marshal.FreeHGlobal(_outHdrPtrs[i]);
                        _outHdrPtrs[i] = IntPtr.Zero;
                    }
                    if (_outDataPtrs[i] != IntPtr.Zero)
                    {
                        Marshal.FreeHGlobal(_outDataPtrs[i]);
                        _outDataPtrs[i] = IntPtr.Zero;
                    }
                }
                try { waveOutClose(_hWaveOut); } catch { }
                _hWaveOut = IntPtr.Zero;
            }
        }

        public void Dispose() => Stop();
    }
}
