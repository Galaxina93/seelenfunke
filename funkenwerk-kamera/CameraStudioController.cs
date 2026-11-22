using System;
using System.Collections.Generic;
using System.Diagnostics;
using System.Globalization;
using System.IO;
using System.Linq;
using System.Text;
using System.Text.Json;
using System.Threading;
using System.Threading.Tasks;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Controls.Primitives;
using System.Windows.Input;
using System.Windows.Media;
using System.Windows.Media.Imaging;
using Microsoft.Win32;

namespace Funkenwerk
{
    public partial class MainWindow : Window
    {
        // =========================================================================
        // MODULE 7: KAMERA & STREAMING STUDIO (FUNKENWERK VIRTUELLE KAMERA)
        // =========================================================================
        private bool _isCameraStudioInitialized = false;
        private bool _suppressCameraUiEvents = false;
        private CameraStudioConfig _camConfig = new CameraStudioConfig();
        private string _camConfigPath = "";

        private DirectShowCameraStreamWorker? _camPrimaryWorker;
        private DirectShowCameraStreamWorker? _camSecondaryWorker;
        private FunkenwerkVirtualCamBroadcaster? _virtualCamBroadcaster;
        private FunkenwerkVideoRecorder? _camVideoRecorder;
        private readonly FunkenwerkFrameProcessor _camFrameProcessor = new FunkenwerkFrameProcessor();
        private readonly FunkenwerkMediaInjector _camMediaInjector = new FunkenwerkMediaInjector();
        private readonly FunkenwerkDeepFakeEngine _camDeepFakeEngine = new FunkenwerkDeepFakeEngine();
        private readonly FunkenwerkVoiceChangerEngine _camVoiceChanger = new FunkenwerkVoiceChangerEngine();
        private DateTime? _videoSignalAutoStopDeadlineUtc = null;
        private int _videoSignalAutoStopLastDisplayedSec = -1;
        private readonly MediaPlayer _camMusicSignalPlayer = new MediaPlayer();
        private bool _isCamMusicSignalPlaying = false;
        private bool _camMusicSignalEventsWired = false;
        private DateTime? _musicSignalAutoStopDeadlineUtc = null;
        private int _musicSignalAutoStopLastDisplayedSec = -1;

        private System.Windows.Threading.DispatcherTimer? _camRenderTimer;
        private System.Windows.Threading.DispatcherTimer? _camAudioMeterTimer;
        private System.Windows.Media.Imaging.WriteableBitmap? _camWriteableBitmap;

        private byte[]? _camCompositedBuffer;
        private byte[]? _camInjectedVideoBuffer;
        private byte[]? _camSecondaryFrameCache;
        private long _lastWindowCaptureTick = 0;
        private IntPtr _selectedShareWindowHandle = IntPtr.Zero;
        private bool _isSelectedShareFullDesktop = true;
        private string _selectedShareRegionMode = "fit";

        // Interactive Mouse PiP Drag, Resize & Scissors Crop State
        private bool _isPipDragging = false;
        private bool _isPipResizing = false;
        private Point _pipMouseStartPt;
        private double _pipStartX = 0.67, _pipStartY = 0.63, _pipStartW = 0.30, _pipStartH = 0.32;
        private bool _isRecordingQuickLoop = false;

        private bool _isEyedropperActive = false;
        private bool _isCountdownRunning = false;
        private CameraMediaItem? _activeCamPreviewItem = null;
        private bool _isCamPreviewPlaying = false;
        private string _activeCamInspectorTab = "image";

        private string GetDefaultCameraMediaDir()
        {
            if (!FunkenwerkSecurityVault.IsUsingTemporaryAppData)
            {
                string existingBaseMedia = System.IO.Path.Combine(AppDomain.CurrentDomain.BaseDirectory, "KameraMedien");
                if (Directory.Exists(existingBaseMedia)) return existingBaseMedia;
            }
            string dir = System.IO.Path.Combine(FunkenwerkSecurityVault.AppDataDirectory, "KameraMedien");
            try { Directory.CreateDirectory(dir); } catch { }
            return dir;
        }

        private string GetEffectiveCameraMediaDir()
        {
            string defaultDir = GetDefaultCameraMediaDir();
            if (FunkenwerkSecurityVault.IsUsingTemporaryAppData)
            {
                return defaultDir;
            }

            string configured = !string.IsNullOrWhiteSpace(_layoutSettings.CameraMediaDirectory)
                ? _layoutSettings.CameraMediaDirectory.Trim()
                : _camConfig.MediaDirectory;

            if (string.IsNullOrWhiteSpace(configured) ||
                configured.Contains(@"\Desktop\Funkenwerk\", StringComparison.OrdinalIgnoreCase))
            {
                configured = defaultDir;
                _layoutSettings.CameraMediaDirectory = defaultDir;
                _camConfig.MediaDirectory = defaultDir;
                _camConfig.MediaSaveDirectory = defaultDir;
            }

            try
            {
                Directory.CreateDirectory(configured);
                return configured;
            }
            catch
            {
                return defaultDir;
            }
        }

        private void LoadCameraStudioConfig()
        {
            _camConfigPath = FunkenwerkSecurityVault.GetAppDataFilePath("camera_studio.json", migrateFromBaseDir: true);
            try
            {
                if (File.Exists(_camConfigPath))
                {
                    var json = File.ReadAllText(_camConfigPath, Encoding.UTF8);
                    var loaded = JsonSerializer.Deserialize<CameraStudioConfig>(json);
                    if (loaded != null) _camConfig = loaded;
                    FunkenwerkSecurityVault.LockDownFileAcl(_camConfigPath);
                }
            }
            catch { }

            if (string.IsNullOrWhiteSpace(_camConfig.MediaDirectory))
                _camConfig.MediaDirectory = GetDefaultCameraMediaDir();

            if (!string.IsNullOrWhiteSpace(_layoutSettings.CameraMediaDirectory))
                _camConfig.MediaDirectory = _layoutSettings.CameraMediaDirectory;
            else
                _layoutSettings.CameraMediaDirectory = _camConfig.MediaDirectory;

            if (!string.IsNullOrWhiteSpace(_layoutSettings.SelectedMicrophoneName))
                _camConfig.SelectedMicrophone = _layoutSettings.SelectedMicrophoneName;

            if (!string.IsNullOrWhiteSpace(_layoutSettings.PrimaryCameraName))
                _camConfig.PrimaryCamera = _layoutSettings.PrimaryCameraName;

            // Ensure Deep-Live-Cam default face preset is populated and points to a valid portrait (not UI icons)
            var presets = FunkenwerkDeepFakeEngine.GetAvailableFacePresets();
            bool needResetFace = string.IsNullOrWhiteSpace(_camConfig.DeepFakeSourceFacePath) ||
                                 !File.Exists(_camConfig.DeepFakeSourceFacePath) ||
                                 _camConfig.DeepFakeSourceFacePath.EndsWith("Download.png", StringComparison.OrdinalIgnoreCase) ||
                                 _camConfig.DeepFakeSourceFacePath.EndsWith("instruction.png", StringComparison.OrdinalIgnoreCase) ||
                                 _camConfig.DeepFakeSourceFacePath.Contains("Screenshot", StringComparison.OrdinalIgnoreCase);
            if (needResetFace && presets.Count > 0)
            {
                _camConfig.DeepFakeSourceFacePath = presets[0].FullPath;
            }

            // Ensure leftover test AR modes ("anime_eyes" + MouthMask) from previous automated test runs do not obscure swapped faces
            if (_camConfig.DeepFakeArMode == "anime_eyes" && _camConfig.DeepFakeMouthMask)
            {
                _camConfig.DeepFakeArMode = "none";
                _camConfig.DeepFakeMouthMask = false;
                _camConfig.DeepFakeColorCorrection = false;
            }
            _camConfig.DeepFakeSwapActive = true;

            // Ensure VideoSignalClips list is clean and auto-discover existing MP4 clips in KameraMedien if empty
            _camConfig.VideoSignalClips ??= new List<VideoSignalClipItem>();
            _camConfig.VideoSignalClips.RemoveAll(c => string.IsNullOrWhiteSpace(c.FilePath) || !File.Exists(c.FilePath));
            if (_camConfig.VideoSignalClips.Count == 0)
            {
                try
                {
                    string mediaDir = GetEffectiveCameraMediaDir();
                    if (Directory.Exists(mediaDir))
                    {
                        foreach (var mp4 in Directory.GetFiles(mediaDir, "*.mp4").OrderByDescending(File.GetLastWriteTime).Take(3))
                        {
                            _camConfig.VideoSignalClips.Add(new VideoSignalClipItem
                            {
                                Title = System.IO.Path.GetFileNameWithoutExtension(mp4),
                                FilePath = mp4
                            });
                        }
                    }
                }
                catch { }
            }

            // Ensure MusicSignalClips list is clean and auto-populate with Seelenfunke Studio Tracks if empty
            _camConfig.MusicSignalClips ??= new List<MusicSignalClipItem>();
            _camConfig.MusicSignalClips.RemoveAll(c => string.IsNullOrWhiteSpace(c.FilePath) || !File.Exists(c.FilePath));
            if (_camConfig.MusicSignalClips.Count == 0)
            {
                PopulateDefaultSfMusicSignalClips(silent: true);
            }
        }

        private void SaveCameraStudioConfig()
        {
            if (!_isCameraStudioInitialized) return;
            try
            {
                _layoutSettings.CameraMediaDirectory = _camConfig.MediaDirectory;
                _layoutSettings.SelectedMicrophoneName = _camConfig.SelectedMicrophone;
                _layoutSettings.PrimaryCameraName = _camConfig.PrimaryCamera;
                var json = JsonSerializer.Serialize(_camConfig, new JsonSerializerOptions { WriteIndented = true });
                FunkenwerkSecurityVault.WriteSecureFileText(_camConfigPath, json);
            }
            catch { }
        }

        private bool _isHardwareCameraStarted = false;

        private void EnsureCameraStudioInitialized()
        {
            if (_isCameraStudioInitialized)
            {
                RefreshCameraWindowShareTargets();
                RenderCameraMediaGallery();
                RenderVideoSignalDeckList();
                RenderMusicSignalDeckList();
                RenderDeepFakePresetsUI();
                SwitchCameraInspectorTab(_activeCamInspectorTab);
                UpdateHardwareCameraStartStopButtonUI();
                SyncInteractivePipGizmoBounds();
                return;
            }

            _isCameraStudioInitialized = true;
            LoadCameraStudioConfig();

            if (!_camMusicSignalEventsWired)
            {
                _camMusicSignalEventsWired = true;
                _camMusicSignalPlayer.MediaEnded += (s, e) =>
                {
                    if (_camConfig.MusicSignalLoop || _musicSignalAutoStopDeadlineUtc.HasValue)
                    {
                        try
                        {
                            _camMusicSignalPlayer.Position = TimeSpan.Zero;
                            _camMusicSignalPlayer.Play();
                        }
                        catch { }
                    }
                    else
                    {
                        StopMusicSignalPlayback(showToast: true);
                    }
                };
            }

            // 1. Register "Funkenwerk Kamera" in Windows DirectShow (HKCU & HKLM 64-bit & 32-bit + WM_DEVICECHANGE for Discord)
            var (regOk, regMsg) = FunkenwerkVirtualCamDriver.EnsureRegistered(_camConfig.Width, _camConfig.Height, _camConfig.Fps);
            if (CamVirtualStatusSubTxt != null)
            {
                CamVirtualStatusSubTxt.Text = regOk
                    ? "Virtuelle Kamera: 'Funkenwerk Kamera' (Aktiv in Discord & Windows)"
                    : $"Virtuelle Kamera Hinweis: {regMsg}";
                CamVirtualStatusSubTxt.Foreground = regOk ? BrushFromHex("#34D399") : BrushFromHex("#F59E0B");
            }

            // 2. Initialize Shared Memory Virtual Camera Broadcaster
            _virtualCamBroadcaster = new FunkenwerkVirtualCamBroadcaster();
            _virtualCamBroadcaster.Start(_camConfig.Width, _camConfig.Height, _camConfig.Fps);

            // 3. Populate Hardware Cameras, Microphones, Window Targets, Video Deck & Deep Fake Presets
            _suppressCameraUiEvents = true;
            try
            {
                RefreshCameraHardwareDevicesUI();
                RefreshCameraWindowShareTargets();
                SyncCameraUiFromConfig();
                RenderVideoSignalDeckList();
                RenderMusicSignalDeckList();
                RenderDeepFakePresetsUI();
            }
            finally
            {
                _suppressCameraUiEvents = false;
            }

            // 4. PC Hardware Camera stays OFF by default until the user clicks "▶ Kamera starten"!
            SwitchCameraInspectorTab(_activeCamInspectorTab);
            UpdateHardwareCameraStartStopButtonUI();

            // 5. Start Compositor Render Loop Timer
            _camRenderTimer = new System.Windows.Threading.DispatcherTimer(System.Windows.Threading.DispatcherPriority.Render)
            {
                Interval = TimeSpan.FromMilliseconds(Math.Max(15, 1000.0 / Math.Max(15, _camConfig.Fps)))
            };
            _camRenderTimer.Tick += CamRenderTimer_Tick;
            _camRenderTimer.Start();

            // 6. Start Audio VU Meter & Recording Clock Timer
            _camAudioMeterTimer = new System.Windows.Threading.DispatcherTimer
            {
                Interval = TimeSpan.FromMilliseconds(100)
            };
            _camAudioMeterTimer.Tick += CamAudioMeterTimer_Tick;
            _camAudioMeterTimer.Start();

            RenderCameraMediaGallery();
            UpdateCameraLiveStatusBadges();
            SyncInteractivePipGizmoBounds();
        }

        private void ShutdownCameraStudioEngine()
        {
            try { _camRenderTimer?.Stop(); } catch { }
            try { _camAudioMeterTimer?.Stop(); } catch { }
            try { _camMusicSignalPlayer.Stop(); _camMusicSignalPlayer.Close(); } catch { }
            try { _camVoiceChanger.Dispose(); } catch { }
            try { _camMediaInjector.Dispose(); } catch { }
            try { _camDeepFakeEngine.Dispose(); } catch { }
            try { _camVideoRecorder?.Dispose(); _camVideoRecorder = null; } catch { }
            try { _camPrimaryWorker?.Dispose(); _camPrimaryWorker = null; } catch { }
            try { _camSecondaryWorker?.Dispose(); _camSecondaryWorker = null; } catch { }
            try { _virtualCamBroadcaster?.Dispose(); _virtualCamBroadcaster = null; } catch { }
        }

        private void RefreshCameraHardwareDevicesUI()
        {
            var videoDevs = FunkenwerkHardwareController.GetVideoDevices();
            if (videoDevs.Count == 0)
            {
                videoDevs.Add("HD Pro Webcam C920");
                videoDevs.Add("Integrated Camera");
            }

            var audioDevs = FunkenwerkHardwareController.GetAudioDevices();
            if (audioDevs.Count == 0)
            {
                audioDevs.Add("Standard-Mikrofon");
            }

            CamPrimaryDeviceCombo.ItemsSource = videoDevs;
            if (!string.IsNullOrWhiteSpace(_camConfig.PrimaryCamera) && videoDevs.Contains(_camConfig.PrimaryCamera))
                CamPrimaryDeviceCombo.SelectedItem = _camConfig.PrimaryCamera;
            else
            {
                _camConfig.PrimaryCamera = videoDevs[0];
                CamPrimaryDeviceCombo.SelectedIndex = 0;
            }

            var secList = new List<string> { "Keine 2. Hardware-Kamera (Fenster/Desktop nutzen)" };
            secList.AddRange(videoDevs);
            CamSecondaryDeviceCombo.ItemsSource = secList;
            if (!string.IsNullOrWhiteSpace(_camConfig.SecondaryCamera) && secList.Contains(_camConfig.SecondaryCamera))
                CamSecondaryDeviceCombo.SelectedItem = _camConfig.SecondaryCamera;
            else
                CamSecondaryDeviceCombo.SelectedIndex = 0;

            if (string.IsNullOrWhiteSpace(_camConfig.SelectedMicrophone) || !audioDevs.Contains(_camConfig.SelectedMicrophone))
            {
                _camConfig.SelectedMicrophone = audioDevs[0];
            }

            if (CamActiveMicLabel != null)
                CamActiveMicLabel.Text = _camConfig.SelectedMicrophone;

            if (CamBottomMicCombo != null)
            {
                _suppressCameraUiEvents = true;
                CamBottomMicCombo.ItemsSource = audioDevs;
                CamBottomMicCombo.SelectedItem = _camConfig.SelectedMicrophone;
                _suppressCameraUiEvents = false;
            }

            if (CamVoiceOutputDeviceCombo != null)
            {
                var outDevs = FunkenwerkVoiceChangerEngine.GetAudioOutputDevices();
                _suppressCameraUiEvents = true;
                CamVoiceOutputDeviceCombo.ItemsSource = outDevs;
                if (!string.IsNullOrWhiteSpace(_camConfig.VoiceOutputDevice) && outDevs.Contains(_camConfig.VoiceOutputDevice))
                    CamVoiceOutputDeviceCombo.SelectedItem = _camConfig.VoiceOutputDevice;
                else
                    CamVoiceOutputDeviceCombo.SelectedIndex = 0;
                _suppressCameraUiEvents = false;
            }
        }

        private void RefreshCameraWindowShareTargets()
        {
            if (CamWindowTargetCombo == null) return;
            FunkenwerkWindowCapture.TutorialClickHighlightEnabled = _camConfig.TutorialClickHighlight;
            if (_camConfig.CustomShareRegionW > 40 && _camConfig.CustomShareRegionH > 40)
            {
                FunkenwerkWindowCapture.SetCustomRegion(
                    _camConfig.CustomShareRegionX,
                    _camConfig.CustomShareRegionY,
                    _camConfig.CustomShareRegionW,
                    _camConfig.CustomShareRegionH);
            }

            var targets = FunkenwerkWindowCapture.GetCapturableWindows();
            CamWindowTargetCombo.ItemsSource = targets;
            if (targets.Count > 0)
            {
                var preferred = targets.FirstOrDefault(t => t.RegionMode == _camConfig.WindowRegionMode && t.IsFullDesktop)
                                ?? targets.FirstOrDefault(t => t.RegionMode == "center_half")
                                ?? targets.FirstOrDefault(t => t.IsFullDesktop)
                                ?? targets[0];
                CamWindowTargetCombo.SelectedItem = preferred;
                _selectedShareWindowHandle = preferred.Handle;
                _isSelectedShareFullDesktop = preferred.IsFullDesktop;
                _selectedShareRegionMode = preferred.RegionMode;
                _camConfig.WindowRegionMode = preferred.RegionMode;
            }
        }

        private void SyncCameraUiFromConfig()
        {
            // FPS Combo
            foreach (ComboBoxItem item in CamFpsCombo.Items)
            {
                if (item.Tag?.ToString() == _camConfig.Fps.ToString())
                {
                    CamFpsCombo.SelectedItem = item;
                    break;
                }
            }

            // Resolution Combo
            string resTag = $"{_camConfig.Width}x{_camConfig.Height}";
            foreach (ComboBoxItem item in CamResolutionCombo.Items)
            {
                if (item.Tag?.ToString() == resTag)
                {
                    CamResolutionCombo.SelectedItem = item;
                    break;
                }
            }

            // Record Mode Combo
            foreach (ComboBoxItem item in CamRecordModeCombo.Items)
            {
                if (item.Tag?.ToString() == _camConfig.RecordingMode)
                {
                    CamRecordModeCombo.SelectedItem = item;
                    break;
                }
            }

            // Recording Speed / Tempo (Default 1x Standard)
            if (_camConfig.RecordingSpeedMultiplier <= 0.05) _camConfig.RecordingSpeedMultiplier = 1.0;
            if (string.IsNullOrWhiteSpace(_camConfig.RecordingSpeedMode)) _camConfig.RecordingSpeedMode = "1x";
            if (CamRecSpeedCustomBox != null)
                CamRecSpeedCustomBox.Text = _camConfig.RecordingSpeedMultiplier.ToString("0.##", CultureInfo.InvariantCulture);
            UpdateRecordingSpeedButtonsUI();

            // Zoom, Pan, Tilt, Mirror, Flip, Mirror Shared Source
            CamZoomSlider.Value = _camConfig.Zoom;
            CamZoomValueTxt.Text = $"{_camConfig.Zoom:0.0}x";
            CamPanXSlider.Value = _camConfig.PanX;
            CamPanYSlider.Value = _camConfig.PanY;
            if (CamPanXTxt != null) CamPanXTxt.Text = _camConfig.PanX.ToString();
            if (CamPanYTxt != null) CamPanYTxt.Text = _camConfig.PanY.ToString();
            CamMirrorHCheck.IsChecked = _camConfig.MirrorHorizontal;
            CamFlipVCheck.IsChecked = _camConfig.FlipVertical;
            if (CamMirrorSharedCheck != null) CamMirrorSharedCheck.IsChecked = _camConfig.MirrorSharedSource;

            // Autofocus & AWB
            CamAutofocusCheck.IsChecked = _camConfig.Autofocus;
            CamManualFocusSlider.Value = _camConfig.ManualFocus;
            CamManualFocusTxt.Text = _camConfig.ManualFocus.ToString();
            CamManualFocusRow.IsEnabled = !_camConfig.Autofocus;
            CamManualFocusRow.Opacity = _camConfig.Autofocus ? 0.45 : 1.0;
            CamAutoWbCheck.IsChecked = _camConfig.AutoWhiteBalance;

            // Image Settings
            CamBrightnessSlider.Value = _camConfig.Brightness;
            CamBrightnessTxt.Text = _camConfig.Brightness.ToString();
            CamContrastSlider.Value = _camConfig.Contrast;
            CamContrastTxt.Text = _camConfig.Contrast.ToString();
            CamWarmthSlider.Value = _camConfig.Warmth;
            CamWarmthTxt.Text = _camConfig.Warmth.ToString();
            CamSharpnessSlider.Value = _camConfig.Sharpness;
            CamSharpnessTxt.Text = _camConfig.Sharpness.ToString();

            // TikTok & Beauty Filter
            if (CamBeautySkinSlider != null) CamBeautySkinSlider.Value = _camConfig.BeautySkinSmooth;
            if (CamBeautySkinTxt != null) CamBeautySkinTxt.Text = $"{_camConfig.BeautySkinSmooth} %";
            if (CamBeautyGlowSlider != null) CamBeautyGlowSlider.Value = _camConfig.BeautyFaceGlow;
            if (CamBeautyGlowTxt != null) CamBeautyGlowTxt.Text = $"{_camConfig.BeautyFaceGlow} %";
            UpdateBeautyFilterButtonsUI();

            // 60FPS Shader Checkboxes
            if (CamFxNeonPulseCheck != null) CamFxNeonPulseCheck.IsChecked = _camConfig.FxNeonEdgePulse;
            if (CamFxRgbWaveCheck != null) CamFxRgbWaveCheck.IsChecked = _camConfig.FxRgbChromaticWave;
            if (CamFxHoloHudCheck != null) CamFxHoloHudCheck.IsChecked = _camConfig.FxHologramHud;
            if (CamFxFireAuraCheck != null) CamFxFireAuraCheck.IsChecked = _camConfig.FxFireAura;
            if (CamFxContinuousSpinCheck != null) CamFxContinuousSpinCheck.IsChecked = _camConfig.FxContinuousSpin;
            if (CamFxDiscoCheck != null) CamFxDiscoCheck.IsChecked = _camConfig.FxDiscoParty;
            if (CamFxVhsCheck != null) CamFxVhsCheck.IsChecked = _camConfig.FxVhsRetro;
            if (CamFxMatrixRainCheck != null) CamFxMatrixRainCheck.IsChecked = _camConfig.FxMatrixRain;
            if (CamFxDealWithItCheck != null) CamFxDealWithItCheck.IsChecked = _camConfig.FxDealWithIt;
            if (CamFxCinemaBarsCheck != null) CamFxCinemaBarsCheck.IsChecked = _camConfig.FxCinemaBars;

            // Distortion Intensity
            CamDistortionIntensitySlider.Value = _camConfig.DistortionIntensity;
            CamDistortionIntensityTxt.Text = $"{_camConfig.DistortionIntensity} %";

            // Video Signal Loop, Return-to-Live, Boomerang & Tarn-Clip Duration (Default 10s)
            if (_camConfig.TarnClipDurationSec <= 0) _camConfig.TarnClipDurationSec = 10;
            if (string.IsNullOrWhiteSpace(_camConfig.TarnClipDurationMode)) _camConfig.TarnClipDurationMode = "10";
            if (CamVideoLoopCheck != null) CamVideoLoopCheck.IsChecked = _camConfig.VideoSignalLoop;
            if (CamVideoSignalReturnLiveCheck != null) CamVideoSignalReturnLiveCheck.IsChecked = _camConfig.VideoSignalReturnToLive;
            if (CamVideoSignalBoomerangCheck != null) CamVideoSignalBoomerangCheck.IsChecked = _camConfig.VideoSignalBoomerang;
            if (CamTarnCustomSecBox != null) CamTarnCustomSecBox.Text = _camConfig.TarnClipDurationSec.ToString();
            _camMediaInjector.LoopEnabled = _camConfig.VideoSignalLoop && !_camConfig.VideoSignalReturnToLive;
            _camMediaInjector.ReturnToLiveAfterEnd = _camConfig.VideoSignalReturnToLive;
            _camMediaInjector.BoomerangEnabled = _camConfig.VideoSignalBoomerang;
            UpdateTarnDurationButtonsUI();

            // Music Signal Injector Controls
            if (CamMusicLoopCheck != null) CamMusicLoopCheck.IsChecked = _camConfig.MusicSignalLoop;
            int musVol = Math.Clamp(_camConfig.MusicSignalVolume <= 0 ? 75 : _camConfig.MusicSignalVolume, 0, 100);
            _camConfig.MusicSignalVolume = musVol;
            if (CamMusicSignalVolumeSlider != null) CamMusicSignalVolumeSlider.Value = musVol;
            if (CamMusicSignalVolumeTxt != null) CamMusicSignalVolumeTxt.Text = $"{musVol} %";
            try { _camMusicSignalPlayer.Volume = _camConfig.SoundMuted ? 0.0 : Math.Clamp(musVol / 100.0, 0.0, 1.0); } catch { }

            // Voice Changer ("Stimme" Tab) Controls
            if (CamVoiceEnableCheck != null) CamVoiceEnableCheck.IsChecked = _camConfig.VoiceChangerEnabled;
            if (CamVoiceMonitorCheck != null) CamVoiceMonitorCheck.IsChecked = _camConfig.VoiceLiveMonitor;
            if (CamVoicePitchSlider != null) CamVoicePitchSlider.Value = _camConfig.VoicePitchSemitones;
            if (CamVoicePitchTxt != null) CamVoicePitchTxt.Text = $"{_camConfig.VoicePitchSemitones:+0.0;-0.0;0.0} st";
            if (CamVoiceFormantSlider != null) CamVoiceFormantSlider.Value = _camConfig.VoiceFormantShift;
            if (CamVoiceFormantTxt != null) CamVoiceFormantTxt.Text = $"{(int)Math.Round(_camConfig.VoiceFormantShift)}%";
            if (CamVoiceRobotSlider != null) CamVoiceRobotSlider.Value = _camConfig.VoiceRobotMix;
            if (CamVoiceRobotTxt != null) CamVoiceRobotTxt.Text = $"{_camConfig.VoiceRobotMix}%";
            if (CamVoiceReverbSlider != null) CamVoiceReverbSlider.Value = _camConfig.VoiceReverbMix;
            if (CamVoiceReverbTxt != null) CamVoiceReverbTxt.Text = $"{_camConfig.VoiceReverbMix}%";
            if (CamVoiceBassSlider != null) CamVoiceBassSlider.Value = _camConfig.VoiceBassBoost;
            if (CamVoiceBassTxt != null) CamVoiceBassTxt.Text = $"{_camConfig.VoiceBassBoost}%";
            if (CamVoiceGateSlider != null) CamVoiceGateSlider.Value = _camConfig.VoiceNoiseGate;
            if (CamVoiceGateTxt != null) CamVoiceGateTxt.Text = $"{_camConfig.VoiceNoiseGate}%";
            UpdateVoicePresetButtonsUI();
            SyncVoiceChangerEngineState();

            // Deep Fake Studio Controls
            if (CamDeepFakeEnableCheck != null) CamDeepFakeEnableCheck.IsChecked = _camConfig.DeepFakeEnabled;
            if (CamDeepFakeMouthMaskCheck != null) CamDeepFakeMouthMaskCheck.IsChecked = _camConfig.DeepFakeMouthMask;
            if (CamDeepFakeColorCorrCheck != null) CamDeepFakeColorCorrCheck.IsChecked = _camConfig.DeepFakeColorCorrection;
            if (CamDeepFakeEnhanceCheck != null) CamDeepFakeEnhanceCheck.IsChecked = _camConfig.DeepFakeEnhancer;
            if (CamDeepFakeOpacitySlider != null) CamDeepFakeOpacitySlider.Value = _camConfig.DeepFakeBlendOpacity;
            if (CamDeepFakeOpacityTxt != null) CamDeepFakeOpacityTxt.Text = $"{_camConfig.DeepFakeBlendOpacity} %";
            UpdateDeepFakeArButtonsUI();
            UpdateDeepFakeActiveFaceHeaderUI();

            // Greenscreen / Chroma Key
            CamChromaEnableCheck.IsChecked = _camConfig.ChromaKeyEnabled;
            CamChromaToleranceSlider.Value = _camConfig.ChromaTolerance;
            CamChromaToleranceTxt.Text = $"{_camConfig.ChromaTolerance} %";
            CamChromaSmoothSlider.Value = _camConfig.ChromaSmoothness;
            CamChromaSmoothTxt.Text = $"{_camConfig.ChromaSmoothness} %";
            UpdateChromaColorSwatchUI(_camConfig.ChromaKeyR, _camConfig.ChromaKeyG, _camConfig.ChromaKeyB);

            foreach (ComboBoxItem item in CamChromaBgModeCombo.Items)
            {
                if (item.Tag?.ToString() == _camConfig.ChromaBgMode)
                {
                    CamChromaBgModeCombo.SelectedItem = item;
                    break;
                }
            }

            // PiP Scale & Interactive PiP
            if (CamPipScaleSlider != null) CamPipScaleSlider.Value = _camConfig.PipScale;
            UpdatePipQuickUiState();

            // Audio Volume & Mute (Read current Windows CoreAudio state + sync)
            var (micVol, micMute) = FunkenwerkHardwareController.GetAudioVolumeAndMute(1);
            if (micVol > 0) _camConfig.MicVolume = (int)Math.Round(micVol);
            CamMicVolumeSlider.Value = _camConfig.MicVolume;
            CamMicVolumeTxt.Text = $"{_camConfig.MicVolume} %";
            UpdateMicMuteButtonUI(_camConfig.MicMuted);

            var (sndVol, sndMute) = FunkenwerkHardwareController.GetAudioVolumeAndMute(0);
            if (sndVol > 0) _camConfig.SoundVolume = (int)Math.Round(sndVol);
            CamSoundVolumeSlider.Value = _camConfig.SoundVolume;
            CamSoundVolumeTxt.Text = $"{_camConfig.SoundVolume} %";
            UpdateSoundMuteButtonUI(_camConfig.SoundMuted);

            UpdateSplitModeButtonsUI();
            UpdateColorFilterButtonsUI();
            UpdateDistortionFilterButtonsUI();
            UpdateVirtualCamButtonUI();
            UpdateHardwareCameraStartStopButtonUI();
            SwitchCameraInspectorTab(_activeCamInspectorTab);
        }

        private void RestartPrimaryCameraWorker()
        {
            try { _camPrimaryWorker?.Dispose(); } catch { }
            _camPrimaryWorker = null;

            if (!_isHardwareCameraStarted || string.IsNullOrWhiteSpace(_camConfig.PrimaryCamera)) return;

            int captureFps = Math.Clamp(_camConfig.Fps, 15, 60);
            _camPrimaryWorker = new DirectShowCameraStreamWorker(
                _camConfig.PrimaryCamera,
                _camConfig.Width,
                _camConfig.Height,
                captureFps);
        }

        private void RestartSecondaryCameraWorkerIfNeeded()
        {
            bool needsSecCam = _isHardwareCameraStarted &&
                               _camConfig.SplitMode != "single" &&
                               !string.IsNullOrWhiteSpace(_camConfig.SecondaryCamera) &&
                               !_camConfig.SecondaryCamera.StartsWith("Keine", StringComparison.OrdinalIgnoreCase) &&
                               !string.Equals(_camConfig.SecondaryCamera, _camConfig.PrimaryCamera, StringComparison.OrdinalIgnoreCase);

            if (!needsSecCam)
            {
                try { _camSecondaryWorker?.Dispose(); } catch { }
                _camSecondaryWorker = null;
                return;
            }

            if (_camSecondaryWorker != null &&
                string.Equals(_camSecondaryWorker.DeviceName, _camConfig.SecondaryCamera, StringComparison.OrdinalIgnoreCase))
            {
                return;
            }

            try { _camSecondaryWorker?.Dispose(); } catch { }
            _camSecondaryWorker = new DirectShowCameraStreamWorker(
                _camConfig.SecondaryCamera,
                _camConfig.Width,
                _camConfig.Height,
                Math.Clamp(_camConfig.Fps, 15, 60));
        }

        private byte[]? _camSecondaryCaptureBackBuffer;
        private volatile bool _isWindowCaptureInFlight;

        private void CamRenderTimer_Tick(object? sender, EventArgs e)
        {
            if (!_isCameraStudioInitialized) return;

            int w = _camConfig.Width;
            int h = _camConfig.Height;
            int frameBytes = w * h * 4;

            if (_camCompositedBuffer == null || _camCompositedBuffer.Length != frameBytes)
                _camCompositedBuffer = new byte[frameBytes];

            // 1. Acquire Primary Camera Frame OR Injected Dynamic Video Signal (with optional Per-Video Auto-Stop Timer)
            byte[]? primaryRaw = null;
            if (_camMediaInjector.IsPlaying)
            {
                if (_videoSignalAutoStopDeadlineUtc.HasValue)
                {
                    double remSec = (_videoSignalAutoStopDeadlineUtc.Value - DateTime.UtcNow).TotalSeconds;
                    if (remSec <= 0)
                    {
                        _videoSignalAutoStopDeadlineUtc = null;
                        _videoSignalAutoStopLastDisplayedSec = -1;
                        _camMediaInjector.Stop();
                        _camConfig.ActiveVideoSignalPath = "";
                        if (CamVideoSignalActiveBar != null) CamVideoSignalActiveBar.Visibility = Visibility.Collapsed;
                        RenderVideoSignalDeckList();
                        UpdateCameraLiveStatusBadges();
                        ShowCameraToast("⏱ Video-Timer abgelaufen → Automatisch zurück auf Live-Kamera!");
                    }
                    else
                    {
                        int ceilSec = Math.Max(1, (int)Math.Ceiling(remSec));
                        if (ceilSec != _videoSignalAutoStopLastDisplayedSec)
                        {
                            _videoSignalAutoStopLastDisplayedSec = ceilSec;
                            UpdateVideoSignalStatusBannerText();
                        }
                    }
                }

                if (_camMediaInjector.IsPlaying && _camMediaInjector.CopyLatestFrame(w, h, ref _camInjectedVideoBuffer) && _camInjectedVideoBuffer != null)
                {
                    primaryRaw = _camInjectedVideoBuffer;
                }
            }
            else if (_camMediaInjector.ConsumeAutoFinishToLive() ||
                     (CamVideoSignalActiveBar != null && CamVideoSignalActiveBar.Visibility == Visibility.Visible))
            {
                // Clip finished playing -> automatically jump back to original live camera recording!
                _videoSignalAutoStopDeadlineUtc = null;
                _videoSignalAutoStopLastDisplayedSec = -1;
                _camConfig.ActiveVideoSignalPath = "";
                if (CamVideoSignalActiveBar != null) CamVideoSignalActiveBar.Visibility = Visibility.Collapsed;
                RenderVideoSignalDeckList();
                UpdateCameraLiveStatusBadges();
                ShowCameraToast("🎥 Tarn-Clip abgelaufen → Automatisch zurück auf Original-Aufnahme!");
            }

            // Optional Per-Track Auto-Stop Timer for Music Signal Player
            if (_isCamMusicSignalPlaying && _musicSignalAutoStopDeadlineUtc.HasValue)
            {
                double remMusSec = (_musicSignalAutoStopDeadlineUtc.Value - DateTime.UtcNow).TotalSeconds;
                if (remMusSec <= 0)
                {
                    StopMusicSignalPlayback(showToast: false);
                    ShowCameraToast("⏱ Musik-Timer abgelaufen → Musik-Einspieler gestoppt!");
                }
                else
                {
                    int ceilMusSec = Math.Max(1, (int)Math.Ceiling(remMusSec));
                    if (ceilMusSec != _musicSignalAutoStopLastDisplayedSec)
                    {
                        _musicSignalAutoStopLastDisplayedSec = ceilMusSec;
                        UpdateMusicSignalStatusBannerText();
                    }
                }
            }

            if (primaryRaw == null)
            {
                primaryRaw = (_camPrimaryWorker != null && _camPrimaryWorker.HasFrame)
                    ? _camPrimaryWorker.LatestFrameBuffer
                    : null;
            }

            if (primaryRaw == null || primaryRaw.Length != frameBytes)
            {
                primaryRaw = GenerateAnimatedStandbyFrame(w, h);
            }

            // 2. Acquire Secondary Frame if Split-Screen or Secondary Chroma Background is active
            byte[]? secondaryRaw = null;
            bool needSecondary = _camConfig.SplitMode != "single" ||
                                 (_camConfig.ChromaKeyEnabled && _camConfig.ChromaBgMode == "secondary");

            if (needSecondary)
            {
                if (_camSecondaryWorker != null && _camSecondaryWorker.HasFrame)
                {
                    secondaryRaw = _camSecondaryWorker.LatestFrameBuffer;
                }
                else
                {
                    long nowTick = Environment.TickCount64;
                    if (_camSecondaryFrameCache == null || _camSecondaryFrameCache.Length != frameBytes)
                    {
                        // First frame synchronous bootstrap
                        _lastWindowCaptureTick = nowTick;
                        _camSecondaryFrameCache = new byte[frameBytes];
                        FunkenwerkWindowCapture.CaptureWindowBgra(
                            _selectedShareWindowHandle,
                            _isSelectedShareFullDesktop,
                            w,
                            h,
                            _camSecondaryFrameCache,
                            _selectedShareRegionMode,
                            _camConfig.MirrorSharedSource);
                    }
                    else if (!_isWindowCaptureInFlight && (nowTick - _lastWindowCaptureTick) >= 28)
                    {
                        // Zero-blocking background capture for butter-smooth Full HD 1080p
                        _lastWindowCaptureTick = nowTick;
                        _isWindowCaptureInFlight = true;
                        IntPtr capHwnd = _selectedShareWindowHandle;
                        bool capDesktop = _isSelectedShareFullDesktop;
                        string capRegion = _selectedShareRegionMode;
                        bool capMirror = _camConfig.MirrorSharedSource;

                        Task.Run(() =>
                        {
                            try
                            {
                                if (_camSecondaryCaptureBackBuffer == null || _camSecondaryCaptureBackBuffer.Length != frameBytes)
                                    _camSecondaryCaptureBackBuffer = new byte[frameBytes];

                                if (FunkenwerkWindowCapture.CaptureWindowBgra(capHwnd, capDesktop, w, h, _camSecondaryCaptureBackBuffer, capRegion, capMirror))
                                {
                                    var swapped = _camSecondaryFrameCache;
                                    _camSecondaryFrameCache = _camSecondaryCaptureBackBuffer;
                                    _camSecondaryCaptureBackBuffer = swapped;
                                }
                            }
                            catch { }
                            finally
                            {
                                _isWindowCaptureInFlight = false;
                            }
                        });
                    }
                    secondaryRaw = _camSecondaryFrameCache;
                }
            }

            // 3. Process & Composite Frame (Zoom/Pan/Tilt Overscan, YCbCr Chroma Key, TikTok Beauty, Split-Screen, Interactive PiP, 60FPS Shaders)
            _camFrameProcessor.ProcessAndComposite(
                primaryRaw,
                secondaryRaw,
                _camCompositedBuffer,
                w,
                h,
                _camConfig);

            // 3b. Process Deep Fake / Deep-Live-Cam AI Face Swap & 106-Landmark AR if enabled
            if (_camConfig.DeepFakeEnabled)
            {
                _camDeepFakeEngine.ProcessFrame(_camCompositedBuffer, w, h, _camConfig);
                if (CamDeepFakeStatusTxt != null && CamDeepFakeStatusTxt.Text != _camDeepFakeEngine.StatusText)
                {
                    CamDeepFakeStatusTxt.Text = _camDeepFakeEngine.StatusText;
                }
            }

            // 4. Broadcast to Windows DirectShow Virtual Camera ("Funkenwerk Kamera") via zero-copy Marshal.Copy
            if (_camConfig.VirtualCamEnabled && _virtualCamBroadcaster != null)
            {
                _virtualCamBroadcaster.SendFrameBgra(_camCompositedBuffer, w, h, _camConfig.Fps);
            }

            // 5. Feed active MP4 Video Recorder if recording
            if (_camVideoRecorder != null && _camVideoRecorder.IsRecording)
            {
                _camVideoRecorder.WriteFrameBgra(_camCompositedBuffer);
            }

            // 6. Update WPF Live Preview Image if Camera Studio view is visible
            if (_activeSpace == "camera" && CamLivePreviewImage != null)
            {
                if (_camWriteableBitmap == null || _camWriteableBitmap.PixelWidth != w || _camWriteableBitmap.PixelHeight != h)
                {
                    _camWriteableBitmap = new System.Windows.Media.Imaging.WriteableBitmap(w, h, 96, 96, PixelFormats.Bgr32, null);
                    CamLivePreviewImage.Source = _camWriteableBitmap;
                }

                _camWriteableBitmap.WritePixels(new Int32Rect(0, 0, w, h), _camCompositedBuffer, w * 4, 0);
            }
        }

        private byte[]? _standbyBuffer;
        private int _standbyTick = 0;
        private byte[] GenerateAnimatedStandbyFrame(int w, int h)
        {
            int len = w * h * 4;
            if (_standbyBuffer == null || _standbyBuffer.Length != len)
                _standbyBuffer = new byte[len];

            _standbyTick++;
            double phase = _standbyTick * 0.06;
            int cx = w / 2;
            int cy = h / 2;
            bool isDark = _isDarkMode;

            Parallel.For(0, h, y =>
            {
                int row = y * w * 4;
                for (int x = 0; x < w; x++)
                {
                    int idx = row + x * 4;
                    double dx = (x - cx) / (double)w;
                    double dy = (y - cy) / (double)h;
                    double dist = Math.Sqrt(dx * dx + dy * dy);
                    double ring = Math.Sin(dist * 28.0 - phase) * 0.5 + 0.5;

                    byte r, g, b;
                    if (isDark)
                    {
                        b = (byte)Math.Clamp(22 + dist * 40 + ring * 18, 0, 255);
                        g = (byte)Math.Clamp(18 + ring * 28, 0, 255);
                        r = (byte)Math.Clamp(16 + ring * 45, 0, 255);

                        if ((x % 64 == 0) || (y % 64 == 0))
                        {
                            r = (byte)Math.Min(255, r + 14);
                            g = (byte)Math.Min(255, g + 12);
                            b = (byte)Math.Min(255, b + 8);
                        }
                    }
                    else
                    {
                        b = (byte)Math.Clamp(246 - dist * 20 - ring * 8, 0, 255);
                        g = (byte)Math.Clamp(242 - dist * 22 - ring * 10, 0, 255);
                        r = (byte)Math.Clamp(238 - dist * 16 - ring * 6, 0, 255);

                        if ((x % 64 == 0) || (y % 64 == 0))
                        {
                            r = (byte)Math.Max(0, r - 10);
                            g = (byte)Math.Max(0, g - 10);
                            b = (byte)Math.Max(0, b - 8);
                        }
                    }

                    _standbyBuffer[idx] = b;
                    _standbyBuffer[idx + 1] = g;
                    _standbyBuffer[idx + 2] = r;
                    _standbyBuffer[idx + 3] = 255;
                }
            });

            return _standbyBuffer;
        }

        private void CamAudioMeterTimer_Tick(object? sender, EventArgs e)
        {
            if (!_isCameraStudioInitialized || _activeSpace != "camera") return;

            // Live Microphone Peak Meter
            if (_camConfig.MicMuted)
            {
                CamMicVuBar.Value = 0;
            }
            else
            {
                double micPeak = FunkenwerkHardwareController.GetAudioPeakLevel(1);
                double scaledMic = Math.Clamp(micPeak * (_camConfig.MicVolume / 100.0) * 135.0, 2.0, 100.0);
                CamMicVuBar.Value = scaledMic;
            }

            // Live Voice Changer DSP Level Meter
            if (CamVoiceMeterFillBar != null)
            {
                if (_camConfig.VoiceChangerEnabled && !_camConfig.MicMuted)
                {
                    double dspPeak = Math.Max(_camVoiceChanger.CurrentOutputLevel * 100.0, CamMicVuBar.Value * 0.85);
                    CamVoiceMeterFillBar.Width = Math.Clamp(dspPeak * 2.2, 4.0, 225.0);
                }
                else
                {
                    CamVoiceMeterFillBar.Width = 0;
                }
            }

            // Live System Sound Peak Meter
            if (_camConfig.SoundMuted)
            {
                CamSoundVuBar.Value = 0;
            }
            else
            {
                double sndPeak = FunkenwerkHardwareController.GetAudioPeakLevel(0);
                double scaledSnd = Math.Clamp(sndPeak * (_camConfig.SoundVolume / 100.0) * 135.0, 1.0, 100.0);
                CamSoundVuBar.Value = scaledSnd;
            }

            // Active Recording Timer & 15s Short-Clip Auto-Stop
            if (_camVideoRecorder != null && _camVideoRecorder.IsRecording && !_isRecordingQuickLoop)
            {
                var el = _camVideoRecorder.Elapsed;
                double recSpd = Math.Clamp(_camConfig.RecordingSpeedMultiplier <= 0.05 ? 1.0 : _camConfig.RecordingSpeedMultiplier, 0.25, 10.0);
                string spdLabel = Math.Abs(recSpd - 1.0) < 0.02 ? "1x" : $"{recSpd.ToString("0.##", CultureInfo.InvariantCulture)}x";
                CamRecordingBadge.Visibility = Visibility.Visible;
                CamRecordingTimerTxt.Text = _camVideoRecorder.IsPaused
                    ? $"PAUSIERT {el.Minutes:D2}:{el.Seconds:D2} ({spdLabel})"
                    : $"REC {el.Minutes:D2}:{el.Seconds:D2} • {spdLabel}";

                if (_camConfig.RecordingMode == "clip15" && el.TotalSeconds >= 15.0)
                {
                    StopCameraVideoRecording();
                }
            }
            else if (!_isRecordingQuickLoop)
            {
                if (CamRecordingBadge != null) CamRecordingBadge.Visibility = Visibility.Collapsed;
            }
        }

        private void ApplyHardwareCameraSettingsAsync()
        {
            if (!_isHardwareCameraStarted) return;
            string camName = _camConfig.PrimaryCamera;
            if (string.IsNullOrWhiteSpace(camName)) return;

            Task.Run(() =>
            {
                try
                {
                    FunkenwerkHardwareController.ApplyCameraHardwareSettings(_camConfig);
                }
                catch { }
            });
        }

        private void ShowCameraToast(string message)
        {
            if (CamToastBanner == null || CamToastBannerTxt == null) return;
            CamToastBannerTxt.Text = message;
            CamToastBanner.Visibility = Visibility.Visible;

            Task.Run(async () =>
            {
                await Task.Delay(2600);
                Dispatcher.Invoke(() =>
                {
                    if (CamToastBannerTxt.Text == message)
                        CamToastBanner.Visibility = Visibility.Collapsed;
                });
            });
        }

        private void UpdateCameraLiveStatusBadges()
        {
            if (CamLiveStatsBadgeTxt != null)
            {
                string modeLabel = _camConfig.SplitMode switch
                {
                    "pip" => "2-Fach PiP",
                    "sidebyside" => "Links/Rechts",
                    "topbottom" => "Oben/Unten",
                    "screenshare_only" => "Nur Teilen (Pur)",
                    "windowshare" => _camConfig.ShowPipOverlay ? "Gaming/Fenster + Cam" : "Nur Teilen (Pur)",
                    _ => "1x Vollbild"
                };
                string camState = _isHardwareCameraStarted ? "LIVE" : "STANDBY (Kamera aus)";
                CamLiveStatsBadgeTxt.Text = $"{camState} • {_camConfig.Width}x{_camConfig.Height} • {_camConfig.Fps} FPS • {modeLabel}";
            }

            if (CamActiveEffectsBadge != null && CamActiveEffectsBadgeTxt != null)
            {
                var activeFx = new List<string>();
                if (_camConfig.DeepFakeEnabled) activeFx.Add("DEEP-FAKE KI");
                if (_camConfig.VoiceChangerEnabled) activeFx.Add("STIMME: " + (_camConfig.VoiceChangerPreset ?? "DSP").ToUpperInvariant());
                if (_camMediaInjector.IsPlaying) activeFx.Add("VIDEO-SIGNAL");
                if (_isCamMusicSignalPlaying) activeFx.Add("MUSIK-SIGNAL");
                if (_camConfig.BeautyFilter != "none") activeFx.Add("TIKTOK-BEAUTY");
                if (_camConfig.ColorFilter != "none") activeFx.Add(_camConfig.ColorFilter.ToUpperInvariant());
                if (_camConfig.DistortionFilter != "none") activeFx.Add(_camConfig.DistortionFilter.ToUpperInvariant());
                if (_camConfig.ChromaKeyEnabled) activeFx.Add("GREENSCREEN");
                if (_camConfig.FxNeonEdgePulse) activeFx.Add("NEON");
                if (_camConfig.FxRgbChromaticWave) activeFx.Add("RGB-WAVE");
                if (_camConfig.FxHologramHud) activeFx.Add("HOLO-HUD");
                if (_camConfig.FxFireAura) activeFx.Add("FIRE");
                if (_camConfig.FxContinuousSpin) activeFx.Add("SPIN");
                if (_camConfig.FxDiscoParty) activeFx.Add("DISCO");
                if (_camConfig.FxVhsRetro) activeFx.Add("VHS");
                if (_camConfig.FxMatrixRain) activeFx.Add("MATRIX");
                if (_camConfig.FxDealWithIt) activeFx.Add("😎");

                if (activeFx.Count > 0)
                {
                    CamActiveEffectsBadge.Visibility = Visibility.Visible;
                    CamActiveEffectsBadgeTxt.Text = "✨ " + string.Join(" + ", activeFx);
                }
                else
                {
                    CamActiveEffectsBadge.Visibility = Visibility.Collapsed;
                }
            }

            if (SpaceCameraBtn != null)
            {
                SpaceCameraBtn.ApplyTemplate();
                if (SpaceCameraBtn.Template?.FindName("SpaceCameraLiveDot", SpaceCameraBtn) is Border liveDot)
                {
                    liveDot.Visibility = (_camVideoRecorder != null && _camVideoRecorder.IsRecording)
                        ? Visibility.Visible
                        : Visibility.Collapsed;
                }
            }
        }

        // =========================================================================
        // TOP BAR & SCENE / SCREENSPLITTING HANDLERS (Requirements 1, 2, 4)
        // =========================================================================
        private void CamPrimaryDeviceCombo_SelectionChanged(object sender, SelectionChangedEventArgs e)
        {
            if (_suppressCameraUiEvents || !_isCameraStudioInitialized) return;
            if (CamPrimaryDeviceCombo.SelectedItem is string dev && !string.IsNullOrWhiteSpace(dev))
            {
                _camConfig.PrimaryCamera = dev;
                SaveCameraStudioConfig();
                RestartPrimaryCameraWorker();
                ApplyHardwareCameraSettingsAsync();
                ShowCameraToast($"🎥 Hauptkamera gewechselt: {dev}");
            }
        }

        private void CamFpsCombo_SelectionChanged(object sender, SelectionChangedEventArgs e)
        {
            if (_suppressCameraUiEvents || !_isCameraStudioInitialized) return;
            if (CamFpsCombo.SelectedItem is ComboBoxItem item && int.TryParse(item.Tag?.ToString(), out int fps))
            {
                _camConfig.Fps = fps;
                SaveCameraStudioConfig();
                if (_camRenderTimer != null)
                    _camRenderTimer.Interval = TimeSpan.FromMilliseconds(Math.Max(15, 1000.0 / fps));
                UpdateCameraLiveStatusBadges();
                ShowCameraToast($"⚡ Bildrate auf {fps} FPS gesetzt");
            }
        }

        private void CamResolutionCombo_SelectionChanged(object sender, SelectionChangedEventArgs e)
        {
            if (_suppressCameraUiEvents || !_isCameraStudioInitialized) return;
            if (CamResolutionCombo.SelectedItem is ComboBoxItem item && item.Tag is string res)
            {
                var parts = res.Split('x');
                if (parts.Length == 2 && int.TryParse(parts[0], out int w) && int.TryParse(parts[1], out int h))
                {
                    _camConfig.Width = w;
                    _camConfig.Height = h;
                    SaveCameraStudioConfig();
                    RestartPrimaryCameraWorker();
                    RestartSecondaryCameraWorkerIfNeeded();
                    UpdateCameraLiveStatusBadges();
                    SyncInteractivePipGizmoBounds();
                    ShowCameraToast($"📐 Format auf {w}x{h} umgestellt");
                }
            }
        }

        private void CamRecordModeCombo_SelectionChanged(object sender, SelectionChangedEventArgs e)
        {
            if (_suppressCameraUiEvents || !_isCameraStudioInitialized) return;
            if (CamRecordModeCombo.SelectedItem is ComboBoxItem item && item.Tag is string mode)
            {
                _camConfig.RecordingMode = mode;
                if (mode == "gaming" && _camConfig.Fps < 60)
                {
                    _camConfig.Fps = 60;
                    _suppressCameraUiEvents = true;
                    CamFpsCombo.SelectedIndex = 3; // 60 FPS
                    _suppressCameraUiEvents = false;
                    if (_camRenderTimer != null)
                        _camRenderTimer.Interval = TimeSpan.FromMilliseconds(16.6);
                }
                SaveCameraStudioConfig();
                UpdateCameraLiveStatusBadges();
                ShowCameraToast($"🎬 Aufnahmemodus: {item.Content}");
            }
        }

        private void CamHardwareStartStop_Click(object sender, RoutedEventArgs e)
        {
            PlaySoundActionPop();
            _isHardwareCameraStarted = !_isHardwareCameraStarted;
            if (_isHardwareCameraStarted)
            {
                RestartPrimaryCameraWorker();
                RestartSecondaryCameraWorkerIfNeeded();
                ApplyHardwareCameraSettingsAsync();
                ShowCameraToast($"🎥 PC-Kamera '{_camConfig.PrimaryCamera}' gestartet!");
            }
            else
            {
                try { _camPrimaryWorker?.Dispose(); } catch { }
                _camPrimaryWorker = null;
                try { _camSecondaryWorker?.Dispose(); } catch { }
                _camSecondaryWorker = null;
                ShowCameraToast("⏹ PC-Kamera ausgeschaltet (Standby)");
            }

            UpdateHardwareCameraStartStopButtonUI();
            UpdateCameraLiveStatusBadges();
        }

        private void UpdateHardwareCameraStartStopButtonUI()
        {
            if (CamHardwareStartStopBtn == null) return;
            if (_isHardwareCameraStarted)
            {
                CamHardwareStartStopBtn.Content = "⏹ Kamera stoppen";
                CamHardwareStartStopBtn.Background = DirectHexBrush(_isDarkMode ? "#2D161B" : "#FEF2F2");
                CamHardwareStartStopBtn.BorderBrush = DirectHexBrush("#EF4444");
                CamHardwareStartStopBtn.Foreground = DirectHexBrush(_isDarkMode ? "#F87171" : "#DC2626");
            }
            else
            {
                CamHardwareStartStopBtn.Content = "▶ Kamera starten";
                CamHardwareStartStopBtn.Background = DirectHexBrush(_isDarkMode ? "#26231F" : "#FEF9EC");
                CamHardwareStartStopBtn.BorderBrush = DirectHexBrush("#C5A059");
                CamHardwareStartStopBtn.Foreground = DirectHexBrush(_isDarkMode ? "#C5A059" : "#8A6522");
            }
        }

        private void CamVirtualCamToggle_Click(object sender, RoutedEventArgs e)
        {
            PlaySoundActionPop();
            _camConfig.VirtualCamEnabled = !_camConfig.VirtualCamEnabled;
            if (_camConfig.VirtualCamEnabled)
            {
                FunkenwerkVirtualCamDriver.EnsureRegistered(_camConfig.Width, _camConfig.Height, _camConfig.Fps);
            }
            SaveCameraStudioConfig();
            UpdateVirtualCamButtonUI();
            ShowCameraToast(_camConfig.VirtualCamEnabled
                ? "📡 Virtuelle 'Funkenwerk Kamera' sendet jetzt live an Discord & Windows!"
                : "⏸ Virtuelle 'Funkenwerk Kamera' pausiert");
        }

        private void UpdateVirtualCamButtonUI()
        {
            if (CamVirtualCamToggleBtn == null) return;
            if (_camConfig.VirtualCamEnabled)
            {
                CamVirtualCamToggleBtn.Content = "🎥 Funkenwerk Kamera";
                CamVirtualCamToggleBtn.Background = DirectHexBrush("#23A55A");
                CamVirtualCamToggleBtn.Foreground = DirectHexBrush("#FFFFFF");
                CamVirtualCamToggleBtn.BorderBrush = DirectHexBrush("#10B981");
            }
            else
            {
                CamVirtualCamToggleBtn.Content = "🚫 Kamera AUS";
                CamVirtualCamToggleBtn.Background = DirectHexBrush(_isDarkMode ? "#2B2D31" : "#E2E8F0");
                CamVirtualCamToggleBtn.Foreground = DirectHexBrush(_isDarkMode ? "#B5BAC1" : "#374151");
                CamVirtualCamToggleBtn.BorderBrush = DirectHexBrush(_isDarkMode ? "#3F4147" : "#CBD5E1");
            }
        }

        private void CamOpenDiscordDevicePopup_Click(object sender, RoutedEventArgs e)
        {
            PlaySoundNav();
            if (sender is not FrameworkElement anchor) return;

            var menu = new ContextMenu
            {
                Background = BrushFromHex("#161922"),
                BorderBrush = BrushFromHex("#2E3446"),
                BorderThickness = new Thickness(1),
                Padding = new Thickness(6)
            };

            var headerItem = new MenuItem
            {
                Header = "KAMERA-QUELLE & DISCORD TREIBER",
                IsEnabled = false,
                Foreground = BrushFromHex("#949BA4"),
                FontWeight = FontWeights.Bold,
                FontSize = 10.5
            };
            menu.Items.Add(headerItem);

            var cams = FunkenwerkHardwareController.GetVideoDevices();
            foreach (var cam in cams)
            {
                bool isCurrent = string.Equals(cam, _camConfig.PrimaryCamera, StringComparison.OrdinalIgnoreCase);
                var mi = new MenuItem
                {
                    Header = (isCurrent ? "●  📷 " : "○  📷 ") + cam,
                    Foreground = isCurrent ? BrushFromHex("#34D399") : BrushFromHex("#F2F3F5"),
                    FontWeight = isCurrent ? FontWeights.Bold : FontWeights.Normal,
                    Padding = new Thickness(8, 6, 8, 6)
                };
                string capturedCam = cam;
                mi.Click += (s, ev) =>
                {
                    _camConfig.PrimaryCamera = capturedCam;
                    _suppressCameraUiEvents = true;
                    CamPrimaryDeviceCombo.SelectedItem = capturedCam;
                    _suppressCameraUiEvents = false;
                    SaveCameraStudioConfig();
                    RestartPrimaryCameraWorker();
                    ApplyHardwareCameraSettingsAsync();
                    ShowCameraToast($"📷 Kamera gewählt: {capturedCam}");
                };
                menu.Items.Add(mi);
            }

            menu.Items.Add(new Separator { Background = BrushFromHex("#2E3446") });

            var regItem = new MenuItem
            {
                Header = "📡 'Funkenwerk Kamera' für Discord neu synchronisieren",
                Foreground = BrushFromHex("#C5A059"),
                FontWeight = FontWeights.Bold,
                Padding = new Thickness(8, 6, 8, 6)
            };
            regItem.Click += (s, ev) =>
            {
                var (ok, msg) = FunkenwerkVirtualCamDriver.EnsureRegistered(_camConfig.Width, _camConfig.Height, _camConfig.Fps);
                ShowCameraToast(ok ? "📡 'Funkenwerk Kamera' in Discord & Windows aktualisiert!" : msg);
            };
            menu.Items.Add(regItem);

            var settingsItem = new MenuItem
            {
                Header = "⚙ Video- & Audioeinstellungen öffnen",
                Foreground = BrushFromHex("#D1D5DB"),
                Padding = new Thickness(8, 6, 8, 6)
            };
            settingsItem.Click += (s, ev) => CamOpenCameraSettings_Click(anchor, new RoutedEventArgs());
            menu.Items.Add(settingsItem);

            menu.PlacementTarget = anchor;
            menu.Placement = System.Windows.Controls.Primitives.PlacementMode.Top;
            menu.IsOpen = true;
        }

        private void CamQuickScreenShare_Click(object sender, RoutedEventArgs e)
        {
            PlaySoundNav();
            if (sender is not FrameworkElement anchor) return;

            FunkenwerkWindowCapture.TutorialClickHighlightEnabled = _camConfig.TutorialClickHighlight;
            if (_camConfig.CustomShareRegionW > 40 && _camConfig.CustomShareRegionH > 40)
            {
                FunkenwerkWindowCapture.SetCustomRegion(
                    _camConfig.CustomShareRegionX,
                    _camConfig.CustomShareRegionY,
                    _camConfig.CustomShareRegionW,
                    _camConfig.CustomShareRegionH);
            }

            var allTargets = FunkenwerkWindowCapture.GetCapturableWindows();
            if (CamWindowTargetCombo != null)
            {
                CamWindowTargetCombo.ItemsSource = allTargets;
            }

            var menu = new ContextMenu
            {
                Background = BrushFromHex("#161922"),
                BorderBrush = BrushFromHex("#2E3446"),
                BorderThickness = new Thickness(1),
                Padding = new Thickness(6)
            };

            // If currently sharing, offer instant stop at the top
            if (_camConfig.SplitMode != "single")
            {
                var stopItem = new MenuItem
                {
                    Header = "🛑 Bildschirm-Teilen beenden (Zurück zu 1x Kamera)",
                    Foreground = BrushFromHex("#F87171"),
                    FontWeight = FontWeights.Bold,
                    Padding = new Thickness(8, 6, 8, 6)
                };
                stopItem.Click += (s, ev) =>
                {
                    PlaySoundActionPop();
                    _camConfig.SplitMode = "single";
                    SaveCameraStudioConfig();
                    RestartSecondaryCameraWorkerIfNeeded();
                    UpdateSplitModeButtonsUI();
                    UpdateCameraLiveStatusBadges();
                    SyncInteractivePipGizmoBounds();
                    ShowCameraToast("🎥 Teilen beendet → 1x Vollbild-Kamera");
                };
                menu.Items.Add(stopItem);
                menu.Items.Add(new Separator { Background = BrushFromHex("#2E3446") });
            }

            // Mirror Shared Area Toggle (Fixes mirrored screen share in Discord!)
            var mirrorItem = new MenuItem
            {
                Header = (_camConfig.MirrorSharedSource ? "☑  " : "☐  ") + "↔ Geteilten Bereich spiegeln (Discord-Fix gegen Spiegelschrift)",
                Foreground = BrushFromHex("#34D399"),
                FontWeight = FontWeights.Bold,
                Padding = new Thickness(8, 6, 8, 6)
            };
            mirrorItem.Click += (s, ev) =>
            {
                PlaySoundSmartPtzTick();
                _camConfig.MirrorSharedSource = !_camConfig.MirrorSharedSource;
                _suppressCameraUiEvents = true;
                if (CamMirrorSharedCheck != null) CamMirrorSharedCheck.IsChecked = _camConfig.MirrorSharedSource;
                _suppressCameraUiEvents = false;
                _camSecondaryFrameCache = null;
                SaveCameraStudioConfig();
                ShowCameraToast(_camConfig.MirrorSharedSource
                    ? "↔ Geteilter Bereich wird gespiegelt (In Discord jetzt richtig herum lesbar!)"
                    : "↔ Spiegelung des geteilten Bereichs deaktiviert");
            };
            menu.Items.Add(mirrorItem);

            // Toggle 2nd PiP Window On/Off while sharing screen
            bool pipCurrentlyShown = _camConfig.SplitMode != "screenshare_only" && _camConfig.ShowPipOverlay;
            var pipToggleItem = new MenuItem
            {
                Header = (pipCurrentlyShown ? "☑  " : "☐  ") + "🖼 Zweitfenster (Kamera-PiP) über geteiltem Bild anzeigen",
                Foreground = BrushFromHex("#38BDF8"),
                FontWeight = FontWeights.Bold,
                Padding = new Thickness(8, 6, 8, 6)
            };
            pipToggleItem.Click += (s, ev) =>
            {
                PlaySoundActionPop();
                bool nextShow = !pipCurrentlyShown;
                _camConfig.ShowPipOverlay = nextShow;
                if (!nextShow && _camConfig.SplitMode == "windowshare")
                {
                    _camConfig.SplitMode = "screenshare_only";
                }
                else if (nextShow && _camConfig.SplitMode == "screenshare_only")
                {
                    _camConfig.SplitMode = "windowshare";
                }
                SaveCameraStudioConfig();
                UpdateSplitModeButtonsUI();
                UpdateCameraLiveStatusBadges();
                SyncInteractivePipGizmoBounds();
                ShowCameraToast(nextShow
                    ? "🖼 Zweitfenster (Kamera-PiP) eingeblendet"
                    : "🖥 Zweitfenster ausgeblendet → Nur geteiltes Bild einzeln sichtbar");
            };
            menu.Items.Add(pipToggleItem);
            menu.Items.Add(new Separator { Background = BrushFromHex("#2E3446") });

            // 1. GANZEN MONITOR TEILEN
            menu.Items.Add(new MenuItem
            {
                Header = "🖥 GANZEN MONITOR / BILDSCHIRMBEREICH TEILEN",
                IsEnabled = false,
                Foreground = BrushFromHex("#C5A059"),
                FontWeight = FontWeights.Bold,
                FontSize = 10.5
            });

            foreach (var mon in allTargets.Where(t => t.IsFullDesktop && t.RegionMode != "custom_region" && !t.RegionMode.StartsWith("mouse_follow")))
            {
                bool active = _camConfig.SplitMode != "single" && _isSelectedShareFullDesktop && _selectedShareRegionMode == mon.RegionMode;
                var mi = new MenuItem
                {
                    Header = (active ? "●  " : "○  ") + mon.Title,
                    Foreground = active ? BrushFromHex("#34D399") : BrushFromHex("#F2F3F5"),
                    FontWeight = active ? FontWeights.Bold : FontWeights.Normal,
                    Padding = new Thickness(8, 5, 8, 5)
                };
                var capturedMon = mon;
                mi.Click += (s, ev) => ActivateScreenShareTarget(capturedMon);
                menu.Items.Add(mi);
            }

            menu.Items.Add(new Separator { Background = BrushFromHex("#2E3446") });

            // 2. BENUTZERDEFINIERTE FLÄCHE & TUTORIAL MAUS-VERFOLGUNG
            menu.Items.Add(new MenuItem
            {
                Header = "📐 BENUTZERDEFINIERTE FLÄCHE & 🎯 TUTORIAL MAUS-FOLLOW",
                IsEnabled = false,
                Foreground = BrushFromHex("#C5A059"),
                FontWeight = FontWeights.Bold,
                FontSize = 10.5
            });

            var customPickItem = new MenuItem
            {
                Header = "📐 Feste Fläche per Maus auf dem Bildschirm markieren...",
                Foreground = BrushFromHex("#FBBF24"),
                FontWeight = FontWeights.Bold,
                Padding = new Thickness(8, 6, 8, 6)
            };
            customPickItem.Click += (s, ev) => OpenInteractiveScreenRegionSelector(false);
            menu.Items.Add(customPickItem);

            var existingCustom = allTargets.FirstOrDefault(t => t.RegionMode == "custom_region");
            if (existingCustom != null)
            {
                bool activeCustom = _camConfig.SplitMode != "single" && _selectedShareRegionMode == "custom_region";
                var useCustomItem = new MenuItem
                {
                    Header = (activeCustom ? "●  " : "○  ") + existingCustom.Title,
                    Foreground = activeCustom ? BrushFromHex("#34D399") : BrushFromHex("#F2F3F5"),
                    FontWeight = activeCustom ? FontWeights.Bold : FontWeights.Normal,
                    Padding = new Thickness(8, 5, 8, 5)
                };
                useCustomItem.Click += (s, ev) => ActivateScreenShareTarget(existingCustom);
                menu.Items.Add(useCustomItem);
            }

            foreach (var mf in allTargets.Where(t => t.RegionMode.StartsWith("mouse_follow")))
            {
                bool activeMf = _camConfig.SplitMode != "single" && _selectedShareRegionMode == mf.RegionMode;
                var mfItem = new MenuItem
                {
                    Header = (activeMf ? "●  " : "○  ") + mf.Title,
                    Foreground = activeMf ? BrushFromHex("#34D399") : BrushFromHex("#F2F3F5"),
                    FontWeight = activeMf ? FontWeights.Bold : FontWeights.Normal,
                    Padding = new Thickness(8, 5, 8, 5)
                };
                var capturedMf = mf;
                mfItem.Click += (s, ev) => ActivateScreenShareTarget(capturedMf);
                menu.Items.Add(mfItem);
            }

            var customFollowPickItem = new MenuItem
            {
                Header = "🎯 Eigene Maus-Follow Größe per Maus aufziehen (Bereich um Maus)...",
                Foreground = BrushFromHex("#38BDF8"),
                FontWeight = FontWeights.Bold,
                Padding = new Thickness(8, 6, 8, 6)
            };
            customFollowPickItem.Click += (s, ev) => OpenInteractiveScreenRegionSelector(true);
            menu.Items.Add(customFollowPickItem);

            var clickHighlightItem = new MenuItem
            {
                Header = (_camConfig.TutorialClickHighlight ? "☑  " : "☐  ") + "✨ Tutorial Maus-Spotlight & Klick-Wellen im Video anzeigen",
                Foreground = BrushFromHex("#C5A059"),
                FontWeight = FontWeights.SemiBold,
                Padding = new Thickness(8, 5, 8, 5)
            };
            clickHighlightItem.Click += (s, ev) =>
            {
                PlaySoundSmartPtzTick();
                _camConfig.TutorialClickHighlight = !_camConfig.TutorialClickHighlight;
                FunkenwerkWindowCapture.TutorialClickHighlightEnabled = _camConfig.TutorialClickHighlight;
                SaveCameraStudioConfig();
                ShowCameraToast(_camConfig.TutorialClickHighlight
                    ? "✨ Tutorial Maus-Spotlight & Klick-Ringe aktiviert"
                    : "✨ Tutorial Maus-Spotlight ausgeblendet");
            };
            menu.Items.Add(clickHighlightItem);

            menu.Items.Add(new Separator { Background = BrushFromHex("#2E3446") });

            // 3. FENSTERAUSWAHL (OFFENE PROGRAMME / GAMES)
            menu.Items.Add(new MenuItem
            {
                Header = "🪟 FENSTERAUSWAHL (PROGRAMM / GAME TEILEN)",
                IsEnabled = false,
                Foreground = BrushFromHex("#C5A059"),
                FontWeight = FontWeights.Bold,
                FontSize = 10.5
            });

            var winTargets = allTargets.Where(t => !t.IsFullDesktop).Take(16).ToList();
            if (winTargets.Count == 0)
            {
                menu.Items.Add(new MenuItem
                {
                    Header = "Keine weiteren offenen Fenster gefunden",
                    IsEnabled = false,
                    Foreground = BrushFromHex("#9CA3AF")
                });
            }
            else
            {
                foreach (var win in winTargets)
                {
                    bool activeWin = _camConfig.SplitMode != "single" && !_isSelectedShareFullDesktop && _selectedShareWindowHandle == win.Handle;
                    var mi = new MenuItem
                    {
                        Header = (activeWin ? "●  🪟 " : "○  🪟 ") + win.Title,
                        Foreground = activeWin ? BrushFromHex("#34D399") : BrushFromHex("#F2F3F5"),
                        FontWeight = activeWin ? FontWeights.Bold : FontWeights.Normal,
                        Padding = new Thickness(8, 5, 8, 5)
                    };
                    var capturedWin = win;
                    mi.Click += (s, ev) => ActivateScreenShareTarget(capturedWin);
                    menu.Items.Add(mi);
                }
            }

            menu.PlacementTarget = anchor;
            menu.Placement = System.Windows.Controls.Primitives.PlacementMode.Top;
            menu.IsOpen = true;
        }

        private void ActivateScreenShareTarget(WindowShareTarget target)
        {
            PlaySoundActionPop();
            _selectedShareWindowHandle = target.Handle;
            _isSelectedShareFullDesktop = target.IsFullDesktop;
            _selectedShareRegionMode = target.RegionMode;
            _camConfig.WindowRegionMode = target.RegionMode;
            _camSecondaryFrameCache = null;

            if (_camConfig.SplitMode == "single")
            {
                _camConfig.SplitMode = _camConfig.ShowPipOverlay ? "windowshare" : "screenshare_only";
            }

            if (CamWindowTargetCombo != null && CamWindowTargetCombo.ItemsSource is IEnumerable<WindowShareTarget> list)
            {
                var match = list.FirstOrDefault(x => x.Handle == target.Handle && x.RegionMode == target.RegionMode);
                if (match != null) CamWindowTargetCombo.SelectedItem = match;
            }

            SaveCameraStudioConfig();
            RestartSecondaryCameraWorkerIfNeeded();
            UpdateSplitModeButtonsUI();
            UpdateCameraLiveStatusBadges();
            SyncInteractivePipGizmoBounds();
            ShowCameraToast($"🖥 Teile jetzt: {target.Title}");
        }

        private void CamMirrorSharedCheck_Changed(object sender, RoutedEventArgs e)
        {
            if (_suppressCameraUiEvents || !_isCameraStudioInitialized) return;
            PlaySoundSmartPtzTick();
            _camConfig.MirrorSharedSource = CamMirrorSharedCheck?.IsChecked == true;
            _camSecondaryFrameCache = null;
            SaveCameraStudioConfig();
            ShowCameraToast(_camConfig.MirrorSharedSource
                ? "↔ Geteilter Bereich gespiegelt (Text ist in Discord jetzt richtig herum lesbar!)"
                : "↔ Spiegelung des geteilten Bereichs ausgeschaltet");
        }

        private void CamDefineCustomScreenRegion_Click(object sender, RoutedEventArgs e)
        {
            OpenInteractiveScreenRegionSelector(false);
        }

        private void CamQuickMouseFollow_Click(object sender, RoutedEventArgs e)
        {
            PlaySoundNav();
            FunkenwerkWindowCapture.TutorialClickHighlightEnabled = _camConfig.TutorialClickHighlight;

            // If not yet in mouse-follow mode, activate standard 1280x720 mouse-follow right away (or custom if defined)
            bool alreadyFollowing = _camConfig.SplitMode != "single" &&
                                    !string.IsNullOrEmpty(_selectedShareRegionMode) &&
                                    _selectedShareRegionMode.StartsWith("mouse_follow");

            if (!alreadyFollowing)
            {
                RefreshCameraWindowShareTargets();
                var allTargets = CamWindowTargetCombo?.ItemsSource as IEnumerable<WindowShareTarget>
                                 ?? FunkenwerkWindowCapture.GetCapturableWindows();
                var preferredFollow = allTargets.FirstOrDefault(t => t.RegionMode == "mouse_follow_1280")
                                      ?? allTargets.FirstOrDefault(t => t.RegionMode.StartsWith("mouse_follow"));
                if (preferredFollow != null)
                {
                    ActivateScreenShareTarget(preferredFollow);
                    return;
                }
            }

            // If already in Mouse-Follow mode, open the quick preset & options menu
            if (sender is FrameworkElement anchor)
            {
                CamQuickScreenShare_Click(anchor, e);
            }
        }

        private void OpenInteractiveScreenRegionSelector(bool followMouse = false)
        {
            PlaySoundActionPop();

            double vLeft = SystemParameters.VirtualScreenLeft;
            double vTop = SystemParameters.VirtualScreenTop;
            double vWidth = SystemParameters.VirtualScreenWidth;
            double vHeight = SystemParameters.VirtualScreenHeight;

            bool isFollowMode = followMouse;

            var overlay = new Window
            {
                Title = "Funkenwerk Bildschirmbereich wählen",
                WindowStyle = WindowStyle.None,
                AllowsTransparency = true,
                Background = new SolidColorBrush(Color.FromArgb(110, 8, 10, 16)),
                Topmost = true,
                ShowInTaskbar = false,
                Left = vLeft,
                Top = vTop,
                Width = vWidth,
                Height = vHeight,
                Cursor = Cursors.Cross
            };

            var canvas = new Canvas { Width = vWidth, Height = vHeight };

            var hintText = new TextBlock
            {
                Foreground = BrushFromHex("#F9FAFB"),
                FontSize = 13.5,
                FontWeight = FontWeights.Bold
            };

            void UpdateHintText()
            {
                hintText.Text = isFollowMode
                    ? "🎯 MAUS-FOLLOW GRÖSSE: Ziehe den gewünschten Sichtbereich auf, der deiner Maus folgen soll  |  [M] = Zu fester Fläche wechseln  |  [ESC] = Abbrechen"
                    : "📐 FESTE FLÄCHE: Ziehe mit der linken Maustaste den gewünschten Bildschirmbereich auf  |  [M] = Zu Maus-Follow wechseln  |  [ESC] = Abbrechen";
            }
            UpdateHintText();

            var hintBanner = new Border
            {
                Background = BrushFromHex("#161922"),
                BorderBrush = BrushFromHex("#C5A059"),
                BorderThickness = new Thickness(1.5),
                CornerRadius = new CornerRadius(12),
                Padding = new Thickness(18, 10, 18, 10),
                Child = hintText
            };
            Canvas.SetLeft(hintBanner, Math.Max(40, (vWidth - 880) * 0.5));
            Canvas.SetTop(hintBanner, 36);
            canvas.Children.Add(hintBanner);

            var selRect = new Border
            {
                BorderBrush = BrushFromHex("#C5A059"),
                BorderThickness = new Thickness(2.5),
                Background = new SolidColorBrush(Color.FromArgb(45, 197, 160, 89)),
                CornerRadius = new CornerRadius(4),
                Visibility = Visibility.Collapsed
            };
            var sizeLabel = new TextBlock
            {
                Foreground = BrushFromHex("#FBBF24"),
                Background = BrushFromHex("#161922"),
                FontSize = 12,
                FontWeight = FontWeights.Bold,
                Padding = new Thickness(6, 2, 6, 2),
                HorizontalAlignment = HorizontalAlignment.Left,
                VerticalAlignment = VerticalAlignment.Top
            };
            selRect.Child = sizeLabel;
            canvas.Children.Add(selRect);
            overlay.Content = canvas;

            bool dragging = false;
            Point startPt = default;

            overlay.KeyDown += (s, ev) =>
            {
                if (ev.Key == Key.Escape)
                {
                    overlay.Close();
                }
                else if (ev.Key == Key.M)
                {
                    isFollowMode = !isFollowMode;
                    UpdateHintText();
                }
            };

            overlay.MouseLeftButtonDown += (s, ev) =>
            {
                dragging = true;
                startPt = ev.GetPosition(canvas);
                Canvas.SetLeft(selRect, startPt.X);
                Canvas.SetTop(selRect, startPt.Y);
                selRect.Width = 2;
                selRect.Height = 2;
                selRect.Visibility = Visibility.Visible;
                overlay.CaptureMouse();
            };

            overlay.MouseMove += (s, ev) =>
            {
                if (!dragging) return;
                Point cur = ev.GetPosition(canvas);
                double x = Math.Min(startPt.X, cur.X);
                double y = Math.Min(startPt.Y, cur.Y);
                double w = Math.Max(4, Math.Abs(cur.X - startPt.X));
                double h = Math.Max(4, Math.Abs(cur.Y - startPt.Y));

                Canvas.SetLeft(selRect, x);
                Canvas.SetTop(selRect, y);
                selRect.Width = w;
                selRect.Height = h;

                var dpi = VisualTreeHelper.GetDpi(overlay);
                int pxW = (int)Math.Round(w * dpi.DpiScaleX);
                int pxH = (int)Math.Round(h * dpi.DpiScaleY);
                sizeLabel.Text = isFollowMode
                    ? $"🎯 Maus-Follow Bereich: {pxW} × {pxH} px"
                    : $"📐 Feste Fläche: {pxW} × {pxH} px";
            };

            overlay.MouseLeftButtonUp += (s, ev) =>
            {
                if (!dragging) return;
                dragging = false;
                overlay.ReleaseMouseCapture();

                Point endPt = ev.GetPosition(canvas);
                var dpi = VisualTreeHelper.GetDpi(overlay);

                double logicalX = vLeft + Math.Min(startPt.X, endPt.X);
                double logicalY = vTop + Math.Min(startPt.Y, endPt.Y);
                double logicalW = Math.Abs(endPt.X - startPt.X);
                double logicalH = Math.Abs(endPt.Y - startPt.Y);

                int physX = (int)Math.Round(logicalX * dpi.DpiScaleX);
                int physY = (int)Math.Round(logicalY * dpi.DpiScaleY);
                int physW = (int)Math.Round(logicalW * dpi.DpiScaleX);
                int physH = (int)Math.Round(logicalH * dpi.DpiScaleY);

                overlay.Close();

                if (physW < 48 || physH < 48)
                {
                    ShowCameraToast("⚠ Auswahl zu klein (mindestens 48×48 Pixel)");
                    return;
                }

                string targetMode = isFollowMode ? "mouse_follow_custom" : "custom_region";

                _camConfig.CustomShareRegionX = physX;
                _camConfig.CustomShareRegionY = physY;
                _camConfig.CustomShareRegionW = physW;
                _camConfig.CustomShareRegionH = physH;
                _camConfig.WindowRegionMode = targetMode;

                FunkenwerkWindowCapture.SetCustomRegion(physX, physY, physW, physH);
                RefreshCameraWindowShareTargets();

                var customTarget = (CamWindowTargetCombo?.ItemsSource as IEnumerable<WindowShareTarget>)
                    ?.FirstOrDefault(t => t.RegionMode == targetMode);

                if (customTarget != null)
                {
                    ActivateScreenShareTarget(customTarget);
                }
                else
                {
                    _selectedShareWindowHandle = IntPtr.Zero;
                    _isSelectedShareFullDesktop = true;
                    _selectedShareRegionMode = targetMode;
                    _camSecondaryFrameCache = null;
                    if (_camConfig.SplitMode == "single") _camConfig.SplitMode = "windowshare";
                    SaveCameraStudioConfig();
                    UpdateSplitModeButtonsUI();
                    UpdateCameraLiveStatusBadges();
                    SyncInteractivePipGizmoBounds();
                }

                ShowCameraToast(isFollowMode
                    ? $"🎯 Maus-Follow aktiv ({physW}×{physH} px um Mauszeiger)"
                    : $"📐 Benutzerdefinierte Fläche aktiv: {physW}×{physH} px");
            };

            overlay.ShowDialog();
        }

        private void CamSplitMode_Click(object sender, RoutedEventArgs e)
        {
            if (sender is Button btn && btn.Tag is string mode)
            {
                PlaySoundNav();
                if (mode == "screenshare_only")
                {
                    _camConfig.ShowPipOverlay = false;
                }
                else if (mode == "windowshare" || mode == "pip")
                {
                    _camConfig.ShowPipOverlay = true;
                }
                _camConfig.SplitMode = mode;
                SaveCameraStudioConfig();
                if (mode != "single") RefreshCameraWindowShareTargets();
                RestartSecondaryCameraWorkerIfNeeded();
                UpdateSplitModeButtonsUI();
                UpdateCameraLiveStatusBadges();
                SyncInteractivePipGizmoBounds();
            }
        }

        private void UpdateSplitModeButtonsUI()
        {
            if (CamSplitSingleBtn == null) return;
            void StyleBtn(Button? b, bool active)
            {
                if (b == null) return;
                b.Background = active
                    ? DirectHexBrush(_isDarkMode ? "#26231F" : "#C5A059")
                    : DirectHexBrush(_isDarkMode ? "#13161F" : "#F1F5F9");
                b.Foreground = active
                    ? DirectHexBrush(_isDarkMode ? "#C5A059" : "#111827")
                    : DirectHexBrush(_isDarkMode ? "#D1D5DB" : "#1F2937");
                b.BorderBrush = active
                    ? DirectHexBrush("#C5A059")
                    : DirectHexBrush(_isDarkMode ? "#2E3446" : "#CBD5E1");
                b.BorderThickness = new Thickness(1);
                b.FontWeight = active ? FontWeights.Bold : FontWeights.SemiBold;
            }

            string mode = _camConfig.SplitMode ?? "single";
            bool isScreenOnly = mode == "screenshare_only" || (mode == "windowshare" && !_camConfig.ShowPipOverlay);
            bool isWindowWithPip = mode == "windowshare" && _camConfig.ShowPipOverlay;

            StyleBtn(CamSplitSingleBtn, mode == "single");
            StyleBtn(CamSplitPipBtn, mode == "pip");
            StyleBtn(CamSplitSideBySideBtn, mode == "sidebyside");
            StyleBtn(CamSplitTopBottomBtn, mode == "topbottom");
            StyleBtn(CamSplitScreenOnlyBtn, isScreenOnly);
            StyleBtn(CamSplitWindowShareBtn, isWindowWithPip);

            if (CamQuickMouseFollowBtn != null)
            {
                bool isMouseFollow = mode != "single" &&
                                     !string.IsNullOrEmpty(_selectedShareRegionMode) &&
                                     _selectedShareRegionMode.StartsWith("mouse_follow");
                CamQuickMouseFollowBtn.Background = isMouseFollow
                    ? DirectHexBrush(_isDarkMode ? "#26231F" : "#C5A059")
                    : DirectHexBrush(_isDarkMode ? "#1E1F22" : "#F8FAFC");
                CamQuickMouseFollowBtn.Foreground = isMouseFollow
                    ? DirectHexBrush(_isDarkMode ? "#C5A059" : "#111827")
                    : DirectHexBrush(_isDarkMode ? "#38BDF8" : "#0369A1");
                CamQuickMouseFollowBtn.BorderBrush = isMouseFollow
                    ? DirectHexBrush("#C5A059")
                    : DirectHexBrush(_isDarkMode ? "#2E3446" : "#CBD5E1");
            }

            if (CamDiscordScreenShareBtn != null)
            {
                bool sharing = mode == "windowshare" || mode == "screenshare_only" || mode == "pip";
                CamDiscordScreenShareBtn.Background = sharing
                    ? DirectHexBrush(_isDarkMode ? "#163026" : "#23A55A")
                    : DirectHexBrush(_isDarkMode ? "#2B2D31" : "#E2E8F0");
                CamDiscordScreenShareBtn.Foreground = sharing
                    ? DirectHexBrush(_isDarkMode ? "#34D399" : "#FFFFFF")
                    : DirectHexBrush(_isDarkMode ? "#F2F3F5" : "#111827");
                CamDiscordScreenShareBtn.BorderBrush = sharing
                    ? DirectHexBrush("#10B981")
                    : DirectHexBrush(_isDarkMode ? "#3F4147" : "#CBD5E1");
                CamDiscordScreenShareBtn.BorderThickness = new Thickness(1);
            }
        }

        private void CamSwapSources_Click(object sender, RoutedEventArgs e)
        {
            PlaySoundActionPop();
            _camConfig.SwapSplitSources = !_camConfig.SwapSplitSources;
            SaveCameraStudioConfig();
            ShowCameraToast("⇄ Haupt- und Zweitquelle getauscht");
        }

        private void CamRefreshWindows_Click(object sender, RoutedEventArgs e)
        {
            PlaySoundNav();
            RefreshCameraWindowShareTargets();
            ShowCameraToast("↻ Fenster-, Gaming- & 32:9 Monitor-Quellen aktualisiert");
        }

        private void CamWindowTargetCombo_SelectionChanged(object sender, SelectionChangedEventArgs e)
        {
            if (CamWindowTargetCombo.SelectedItem is WindowShareTarget target)
            {
                _selectedShareWindowHandle = target.Handle;
                _isSelectedShareFullDesktop = target.IsFullDesktop;
                _selectedShareRegionMode = target.RegionMode;
                _camConfig.WindowRegionMode = target.RegionMode;
                _camSecondaryFrameCache = null;
                UpdateSplitModeButtonsUI();
            }
        }

        private void CamSecondaryDeviceCombo_SelectionChanged(object sender, SelectionChangedEventArgs e)
        {
            if (_suppressCameraUiEvents || !_isCameraStudioInitialized) return;
            if (CamSecondaryDeviceCombo.SelectedItem is string sec)
            {
                _camConfig.SecondaryCamera = sec.StartsWith("Keine", StringComparison.OrdinalIgnoreCase) ? "" : sec;
                SaveCameraStudioConfig();
                RestartSecondaryCameraWorkerIfNeeded();
            }
        }

        private void CamPipScaleSlider_ValueChanged(object sender, RoutedPropertyChangedEventArgs<double> e)
        {
            if (_suppressCameraUiEvents || !_isCameraStudioInitialized) return;
            double scale = Math.Round(e.NewValue, 2);
            _camConfig.PipScale = scale;
            _camConfig.PipNormW = Math.Clamp(scale, 0.12, 0.85);
            _camConfig.PipNormH = Math.Clamp(scale * 1.05, 0.12, 0.85);
            if (_camConfig.PipNormX + _camConfig.PipNormW > 0.98)
                _camConfig.PipNormX = Math.Max(0.02, 0.98 - _camConfig.PipNormW);
            if (_camConfig.PipNormY + _camConfig.PipNormH > 0.98)
                _camConfig.PipNormY = Math.Max(0.02, 0.98 - _camConfig.PipNormH);
            SyncInteractivePipGizmoBounds();
        }

        // =========================================================================
        // INTERACTIVE MOUSE PIP OVERLAY (DRAG, RESIZE, HOVER TOOLBAR, RIGHT-CLICK, SCISSORS)
        // =========================================================================
        private void CamViewport_SizeChanged(object sender, SizeChangedEventArgs e)
        {
            SyncInteractivePipGizmoBounds();
        }

        private (double OffsetX, double OffsetY, double RenderW, double RenderH) GetLiveVideoRenderedRect()
        {
            if (CamViewportRootGrid == null) return (0, 0, 0, 0);
            double containerW = CamViewportRootGrid.ActualWidth;
            double containerH = CamViewportRootGrid.ActualHeight;
            if (containerW <= 10 || containerH <= 10) return (0, 0, 0, 0);

            double videoAspect = (double)Math.Max(1, _camConfig.Width) / Math.Max(1, _camConfig.Height);
            double containerAspect = containerW / containerH;

            double renderW, renderH, offsetX, offsetY;
            if (containerAspect > videoAspect)
            {
                renderH = containerH;
                renderW = renderH * videoAspect;
                offsetX = (containerW - renderW) * 0.5;
                offsetY = 0;
            }
            else
            {
                renderW = containerW;
                renderH = renderW / videoAspect;
                offsetX = 0;
                offsetY = (containerH - renderH) * 0.5;
            }

            return (offsetX, offsetY, renderW, renderH);
        }

        private void SyncInteractivePipGizmoBounds()
        {
            UpdatePipQuickUiState();
            if (CamPipInteractiveCanvas == null || CamPipInteractiveBox == null) return;

            bool pipActive = (_camConfig.SplitMode == "pip" || _camConfig.SplitMode == "windowshare") && _camConfig.ShowPipOverlay;
            CamPipInteractiveCanvas.Visibility = pipActive ? Visibility.Visible : Visibility.Collapsed;
            if (!pipActive) return;

            var (offX, offY, rw, rh) = GetLiveVideoRenderedRect();
            if (rw <= 10 || rh <= 10) return;

            double boxW = Math.Max(64, Math.Clamp(_camConfig.PipNormW, 0.12, 0.85) * rw);
            double boxH = Math.Max(48, Math.Clamp(_camConfig.PipNormH, 0.12, 0.85) * rh);
            double boxX = offX + Math.Clamp(_camConfig.PipNormX, 0.0, Math.Max(0.0, 1.0 - _camConfig.PipNormW)) * rw;
            double boxY = offY + Math.Clamp(_camConfig.PipNormY, 0.0, Math.Max(0.0, 1.0 - _camConfig.PipNormH)) * rh;

            CamPipInteractiveBox.Width = boxW;
            CamPipInteractiveBox.Height = boxH;
            Canvas.SetLeft(CamPipInteractiveBox, boxX);
            Canvas.SetTop(CamPipInteractiveBox, boxY);

            // Keep outer Border transparent so CamPipShapeHoverOutline renders the exact 1:1 shape (including 6-Eck Hexagon & Circle!)
            CamPipInteractiveBox.BorderBrush = Brushes.Transparent;
            CamPipInteractiveBox.Background = new SolidColorBrush(Color.FromArgb(2, 255, 255, 255));

            if (CamPipShapeHoverOutline != null)
            {
                string shape = _camConfig.PipShape ?? "rounded";
                Geometry geom;
                if (shape == "hexagon")
                {
                    var fig = new PathFigure { StartPoint = new Point(0, boxH * 0.5), IsClosed = true, IsFilled = true };
                    fig.Segments.Add(new LineSegment(new Point(boxW * 0.25, 0), true));
                    fig.Segments.Add(new LineSegment(new Point(boxW * 0.75, 0), true));
                    fig.Segments.Add(new LineSegment(new Point(boxW, boxH * 0.5), true));
                    fig.Segments.Add(new LineSegment(new Point(boxW * 0.75, boxH), true));
                    fig.Segments.Add(new LineSegment(new Point(boxW * 0.25, boxH), true));
                    var pg = new PathGeometry();
                    pg.Figures.Add(fig);
                    geom = pg;
                }
                else if (shape == "circle")
                {
                    geom = new EllipseGeometry(new Point(boxW * 0.5, boxH * 0.5), boxW * 0.5, boxH * 0.5);
                }
                else if (shape == "rect")
                {
                    geom = new RectangleGeometry(new Rect(0, 0, boxW, boxH), 2, 2);
                }
                else // "rounded"
                {
                    double rad = Math.Min(boxW, boxH) * 0.11;
                    geom = new RectangleGeometry(new Rect(0, 0, boxW, boxH), rad, rad);
                }
                CamPipShapeHoverOutline.Data = geom;
                CamPipShapeHoverOutline.Stroke = BrushFromHex("#C5A059");
            }
        }

        private void UpdatePipQuickUiState()
        {
            if (CamPipToggleVisibilityBtn != null)
            {
                bool pipOn = _camConfig.SplitMode != "screenshare_only" && _camConfig.ShowPipOverlay;
                CamPipToggleVisibilityBtn.Content = pipOn ? "👁 An" : "🚫 Aus";
                CamPipToggleVisibilityBtn.Foreground = pipOn
                    ? DirectHexBrush(_isDarkMode ? "#34D399" : "#047857")
                    : DirectHexBrush(_isDarkMode ? "#F87171" : "#DC2626");
                CamPipToggleVisibilityBtn.Background = pipOn
                    ? DirectHexBrush(_isDarkMode ? "#064E3B" : "#ECFDF5")
                    : DirectHexBrush(_isDarkMode ? "#2B2D31" : "#FEF2F2");
                CamPipToggleVisibilityBtn.BorderBrush = pipOn
                    ? DirectHexBrush("#10B981")
                    : DirectHexBrush(_isDarkMode ? "#2E3446" : "#FCA5A5");
            }

            string shapeIcon = _camConfig.PipShape switch
            {
                "circle" => "⭕ Rund",
                "hexagon" => "⬢ 6-Eck",
                "rect" => "▭ Eckig",
                _ => "▢ Abger."
            };
            if (CamPipQuickShapeBtn != null)
            {
                CamPipQuickShapeBtn.Content = shapeIcon;
                CamPipQuickShapeBtn.Background = DirectHexBrush(_isDarkMode ? "#1E1F22" : "#FFFFFF");
                CamPipQuickShapeBtn.Foreground = DirectHexBrush(_isDarkMode ? "#F2F3F5" : "#111827");
                CamPipQuickShapeBtn.BorderBrush = DirectHexBrush(_isDarkMode ? "#2B2D31" : "#CBD5E1");
            }
            if (CamPipQuickCropBtn != null)
            {
                bool cropOpen = CamPipScissorsBanner != null && CamPipScissorsBanner.Visibility == Visibility.Visible;
                CamPipQuickCropBtn.Background = cropOpen
                    ? DirectHexBrush(_isDarkMode ? "#26231F" : "#C5A059")
                    : DirectHexBrush(_isDarkMode ? "#1E1F22" : "#FFFFFF");
                CamPipQuickCropBtn.Foreground = cropOpen
                    ? DirectHexBrush(_isDarkMode ? "#C5A059" : "#111827")
                    : DirectHexBrush(_isDarkMode ? "#F2F3F5" : "#111827");
                CamPipQuickCropBtn.BorderBrush = cropOpen
                    ? DirectHexBrush("#C5A059")
                    : DirectHexBrush(_isDarkMode ? "#2B2D31" : "#CBD5E1");
                CamPipQuickCropBtn.BorderThickness = new Thickness(1);
            }
            if (CamPipQuickChromaBtn != null)
            {
                CamPipQuickChromaBtn.Background = _camConfig.PipChromaKeyEnabled
                    ? DirectHexBrush(_isDarkMode ? "#064E3B" : "#ECFDF5")
                    : DirectHexBrush(_isDarkMode ? "#1E1F22" : "#FFFFFF");
                CamPipQuickChromaBtn.Foreground = _camConfig.PipChromaKeyEnabled
                    ? DirectHexBrush(_isDarkMode ? "#34D399" : "#047857")
                    : DirectHexBrush(_isDarkMode ? "#F2F3F5" : "#111827");
                CamPipQuickChromaBtn.BorderBrush = _camConfig.PipChromaKeyEnabled
                    ? DirectHexBrush("#10B981")
                    : DirectHexBrush(_isDarkMode ? "#2B2D31" : "#CBD5E1");
            }
            if (CamPipChromaBtn != null)
            {
                CamPipChromaBtn.Background = DirectHexBrush(_camConfig.PipChromaKeyEnabled ? "#064E3B" : "#2B2D31");
            }
            if (CamPipContextChromaItem != null)
            {
                CamPipContextChromaItem.IsChecked = _camConfig.PipChromaKeyEnabled;
            }
        }

        private void CamPipToggleVisibility_Click(object sender, RoutedEventArgs e)
        {
            PlaySoundActionPop();
            bool currentlyShown = _camConfig.SplitMode != "screenshare_only" && _camConfig.ShowPipOverlay;
            bool nextShow = !currentlyShown;
            _camConfig.ShowPipOverlay = nextShow;

            if (!nextShow && _camConfig.SplitMode == "windowshare")
            {
                _camConfig.SplitMode = "screenshare_only";
            }
            else if (nextShow && _camConfig.SplitMode == "screenshare_only")
            {
                _camConfig.SplitMode = "windowshare";
            }

            SaveCameraStudioConfig();
            UpdateSplitModeButtonsUI();
            UpdateCameraLiveStatusBadges();
            SyncInteractivePipGizmoBounds();
            ShowCameraToast(nextShow
                ? "👁 Zweitfenster (PiP) eingeblendet"
                : "🖥 Zweitfenster ausgeblendet → Nur geteiltes Bild einzeln sichtbar");
        }

        private void CamPipHideFromHover_Click(object sender, RoutedEventArgs e)
        {
            PlaySoundActionPop();
            _camConfig.ShowPipOverlay = false;
            if (_camConfig.SplitMode == "windowshare")
            {
                _camConfig.SplitMode = "screenshare_only";
            }
            SaveCameraStudioConfig();
            UpdateSplitModeButtonsUI();
            UpdateCameraLiveStatusBadges();
            SyncInteractivePipGizmoBounds();
            ShowCameraToast("🖥 Zweitfenster ausgeblendet (Wieder einblenden über '👁 An' oder '🎮 Teilen + Cam')");
        }

        private void CamPipBox_MouseEnter(object sender, MouseEventArgs e)
        {
            if (CamPipInteractiveBox == null) return;
            if (CamPipShapeHoverOutline != null) CamPipShapeHoverOutline.Visibility = Visibility.Visible;
            if (CamPipHoverToolbar != null) CamPipHoverToolbar.Visibility = Visibility.Visible;
            if (CamPipResizeGrip != null) CamPipResizeGrip.Visibility = Visibility.Visible;
        }

        private void CamPipBox_MouseLeave(object sender, MouseEventArgs e)
        {
            if (_isPipDragging || _isPipResizing) return;
            if (CamPipInteractiveBox == null) return;
            if (CamPipShapeHoverOutline != null) CamPipShapeHoverOutline.Visibility = Visibility.Collapsed;
            if (CamPipHoverToolbar != null) CamPipHoverToolbar.Visibility = Visibility.Collapsed;
            if (CamPipResizeGrip != null && (CamPipScissorsBanner == null || CamPipScissorsBanner.Visibility != Visibility.Visible))
                CamPipResizeGrip.Visibility = Visibility.Collapsed;
        }

        private void CamPipBox_MouseLeftButtonDown(object sender, MouseButtonEventArgs e)
        {
            if (_isPipResizing) return;
            if (e.OriginalSource is DependencyObject d && (IsDescendantOf(d, CamPipHoverToolbar) || IsDescendantOf(d, CamPipScissorsBanner) || IsDescendantOf(d, CamPipResizeGrip)))
                return;

            _isPipDragging = true;
            _pipMouseStartPt = e.GetPosition(CamViewportRootGrid);
            _pipStartX = _camConfig.PipNormX;
            _pipStartY = _camConfig.PipNormY;
            CamPipInteractiveBox.CaptureMouse();
            e.Handled = true;
        }

        private void CamPipBox_MouseMove(object sender, MouseEventArgs e)
        {
            if (!_isPipDragging) return;
            var (_, _, rw, rh) = GetLiveVideoRenderedRect();
            if (rw <= 10 || rh <= 10) return;

            Point cur = e.GetPosition(CamViewportRootGrid);
            double dxNorm = (cur.X - _pipMouseStartPt.X) / rw;
            double dyNorm = (cur.Y - _pipMouseStartPt.Y) / rh;

            _camConfig.PipNormX = Math.Clamp(_pipStartX + dxNorm, 0.01, Math.Max(0.02, 0.99 - _camConfig.PipNormW));
            _camConfig.PipNormY = Math.Clamp(_pipStartY + dyNorm, 0.01, Math.Max(0.02, 0.99 - _camConfig.PipNormH));
            SyncInteractivePipGizmoBounds();
        }

        private void CamPipBox_MouseLeftButtonUp(object sender, MouseButtonEventArgs e)
        {
            if (!_isPipDragging) return;
            _isPipDragging = false;
            CamPipInteractiveBox.ReleaseMouseCapture();
            SaveCameraStudioConfig();
            e.Handled = true;
        }

        private void CamPipResizeGrip_MouseLeftButtonDown(object sender, MouseButtonEventArgs e)
        {
            _isPipResizing = true;
            _pipMouseStartPt = e.GetPosition(CamViewportRootGrid);
            _pipStartW = _camConfig.PipNormW;
            _pipStartH = _camConfig.PipNormH;
            CamPipResizeGrip.CaptureMouse();
            e.Handled = true;
        }

        private void CamPipResizeGrip_MouseMove(object sender, MouseEventArgs e)
        {
            if (!_isPipResizing) return;
            var (_, _, rw, rh) = GetLiveVideoRenderedRect();
            if (rw <= 10 || rh <= 10) return;

            Point cur = e.GetPosition(CamViewportRootGrid);
            double dwNorm = (cur.X - _pipMouseStartPt.X) / rw;
            double dhNorm = (cur.Y - _pipMouseStartPt.Y) / rh;

            _camConfig.PipNormW = Math.Clamp(_pipStartW + dwNorm, 0.14, 0.95 - _camConfig.PipNormX);
            _camConfig.PipNormH = Math.Clamp(_pipStartH + dhNorm, 0.14, 0.95 - _camConfig.PipNormY);
            _camConfig.PipScale = Math.Round(_camConfig.PipNormW, 2);

            _suppressCameraUiEvents = true;
            if (CamPipScaleSlider != null) CamPipScaleSlider.Value = _camConfig.PipScale;
            _suppressCameraUiEvents = false;

            SyncInteractivePipGizmoBounds();
        }

        private void CamPipResizeGrip_MouseLeftButtonUp(object sender, MouseButtonEventArgs e)
        {
            if (!_isPipResizing) return;
            _isPipResizing = false;
            CamPipResizeGrip.ReleaseMouseCapture();
            SaveCameraStudioConfig();
            e.Handled = true;
        }

        private void CamPipBox_MouseWheel(object sender, MouseWheelEventArgs e)
        {
            double step = e.Delta > 0 ? 0.025 : -0.025;
            _camConfig.PipNormW = Math.Clamp(_camConfig.PipNormW + step, 0.14, 0.85);
            _camConfig.PipNormH = Math.Clamp(_camConfig.PipNormH + step, 0.14, 0.85);
            if (_camConfig.PipNormX + _camConfig.PipNormW > 0.98)
                _camConfig.PipNormX = Math.Max(0.01, 0.98 - _camConfig.PipNormW);
            if (_camConfig.PipNormY + _camConfig.PipNormH > 0.98)
                _camConfig.PipNormY = Math.Max(0.01, 0.98 - _camConfig.PipNormH);
            _camConfig.PipScale = Math.Round(_camConfig.PipNormW, 2);

            _suppressCameraUiEvents = true;
            if (CamPipScaleSlider != null) CamPipScaleSlider.Value = _camConfig.PipScale;
            _suppressCameraUiEvents = false;

            SyncInteractivePipGizmoBounds();
            SaveCameraStudioConfig();
            e.Handled = true;
        }

        private bool IsDescendantOf(DependencyObject? node, DependencyObject? ancestor)
        {
            if (node == null || ancestor == null) return false;
            DependencyObject? cur = node;
            while (cur != null)
            {
                if (ReferenceEquals(cur, ancestor)) return true;
                cur = VisualTreeHelper.GetParent(cur);
            }
            return false;
        }

        private void CamPipCycleShape_Click(object sender, RoutedEventArgs e)
        {
            PlaySoundSmartPtzTick();
            _camConfig.PipShape = _camConfig.PipShape switch
            {
                "rounded" => "circle",
                "circle" => "hexagon",
                "hexagon" => "rect",
                _ => "rounded"
            };
            SaveCameraStudioConfig();
            SyncInteractivePipGizmoBounds();
            ShowCameraToast($"🎭 PiP-Form geändert: {_camConfig.PipShape.ToUpperInvariant()}");
        }

        private void CamPipSetShapeMenu_Click(object sender, RoutedEventArgs e)
        {
            if (sender is MenuItem mi && mi.Tag is string shape)
            {
                PlaySoundSmartPtzTick();
                _camConfig.PipShape = shape;
                SaveCameraStudioConfig();
                SyncInteractivePipGizmoBounds();
                ShowCameraToast($"🎭 PiP-Form gesetzt: {mi.Header}");
            }
        }

        private void CamPipToggleChroma_Click(object sender, RoutedEventArgs e)
        {
            PlaySoundActionPop();
            _camConfig.PipChromaKeyEnabled = !_camConfig.PipChromaKeyEnabled;
            SaveCameraStudioConfig();
            UpdatePipQuickUiState();
            ShowCameraToast(_camConfig.PipChromaKeyEnabled
                ? "🟩 Chroma Key auf Zweitfenster (PiP) aktiviert!"
                : "🟩 Chroma Key auf Zweitfenster deaktiviert");
        }

        private void CamPipToggleScissors_Click(object sender, RoutedEventArgs e)
        {
            PlaySoundNav();
            if (CamPipScissorsBanner == null) return;
            bool show = CamPipScissorsBanner.Visibility != Visibility.Visible;
            CamPipScissorsBanner.Visibility = show ? Visibility.Visible : Visibility.Collapsed;
            if (show)
            {
                ShowCameraToast("✂ Scheren-Werkzeug aktiv: Schneide die Ränder (L/R/O/U) des Zweitfensters zu");
            }
        }

        private void CamPipCropSideStep_Click(object sender, RoutedEventArgs e)
        {
            if (sender is Button btn && btn.Tag is string side)
            {
                PlaySoundSmartPtzTick();
                switch (side)
                {
                    case "left":
                        _camConfig.PipCropLeft = (_camConfig.PipCropLeft >= 0.35) ? 0.0 : Math.Round(_camConfig.PipCropLeft + 0.07, 2);
                        break;
                    case "right":
                        _camConfig.PipCropRight = (_camConfig.PipCropRight >= 0.35) ? 0.0 : Math.Round(_camConfig.PipCropRight + 0.07, 2);
                        break;
                    case "top":
                        _camConfig.PipCropTop = (_camConfig.PipCropTop >= 0.35) ? 0.0 : Math.Round(_camConfig.PipCropTop + 0.07, 2);
                        break;
                    case "bottom":
                        _camConfig.PipCropBottom = (_camConfig.PipCropBottom >= 0.35) ? 0.0 : Math.Round(_camConfig.PipCropBottom + 0.07, 2);
                        break;
                    case "reset":
                        _camConfig.PipCropLeft = 0;
                        _camConfig.PipCropRight = 0;
                        _camConfig.PipCropTop = 0;
                        _camConfig.PipCropBottom = 0;
                        break;
                }
                SaveCameraStudioConfig();
                ShowCameraToast($"✂ Zuschnitt: L={(int)(_camConfig.PipCropLeft * 100)}% R={(int)(_camConfig.PipCropRight * 100)}% O={(int)(_camConfig.PipCropTop * 100)}% U={(int)(_camConfig.PipCropBottom * 100)}%");
            }
        }

        private void CamPipResetLayout_Click(object sender, RoutedEventArgs e)
        {
            PlaySoundActionPop();
            _camConfig.PipNormX = 0.67;
            _camConfig.PipNormY = 0.63;
            _camConfig.PipNormW = 0.30;
            _camConfig.PipNormH = 0.32;
            _camConfig.PipScale = 0.30;
            _camConfig.PipShape = "rounded";
            _camConfig.PipCropLeft = 0;
            _camConfig.PipCropRight = 0;
            _camConfig.PipCropTop = 0;
            _camConfig.PipCropBottom = 0;
            if (CamPipScissorsBanner != null) CamPipScissorsBanner.Visibility = Visibility.Collapsed;

            _suppressCameraUiEvents = true;
            if (CamPipScaleSlider != null) CamPipScaleSlider.Value = 0.30;
            _suppressCameraUiEvents = false;

            SaveCameraStudioConfig();
            SyncInteractivePipGizmoBounds();
            ShowCameraToast("↺ PiP-Fenster Position, Größe & Zuschnitt zurückgesetzt");
        }

        // =========================================================================
        // AUDIO MIXER: MICROPHONE & SYSTEM SOUND VOLUME + MUTE (Requirements 6, 7)
        // =========================================================================
        private void CamMicMute_Click(object sender, RoutedEventArgs e)
        {
            PlaySoundSmartPtzTick();
            _camConfig.MicMuted = !_camConfig.MicMuted;
            FunkenwerkHardwareController.SetAudioVolumeAndMute(1, _camConfig.MicVolume, _camConfig.MicMuted);
            UpdateMicMuteButtonUI(_camConfig.MicMuted);
            SaveCameraStudioConfig();
            ShowCameraToast(_camConfig.MicMuted ? "🔇 Mikrofon stummgeschaltet" : "🎙 Mikrofon aktiviert");
        }

        private void CamMicVolumeSlider_ValueChanged(object sender, RoutedPropertyChangedEventArgs<double> e)
        {
            if (_suppressCameraUiEvents || !_isCameraStudioInitialized || CamMicVolumeTxt == null) return;
            _camConfig.MicVolume = (int)Math.Round(e.NewValue);
            CamMicVolumeTxt.Text = $"{(int)_camConfig.MicVolume} %";
            FunkenwerkHardwareController.SetAudioVolumeAndMute(1, _camConfig.MicVolume, _camConfig.MicMuted);
        }

        private void UpdateMicMuteButtonUI(bool muted)
        {
            if (CamMicMuteBtn == null) return;
            CamMicMuteBtn.Content = muted ? "🔇 Stumm" : "🎙 Mikro";
            CamMicMuteBtn.Background = muted
                ? DirectHexBrush(_isDarkMode ? "#2D161B" : "#FEE2E2")
                : DirectHexBrush(_isDarkMode ? "#2B2D31" : "#ECFDF5");
            CamMicMuteBtn.Foreground = muted
                ? DirectHexBrush(_isDarkMode ? "#F87171" : "#DC2626")
                : DirectHexBrush(_isDarkMode ? "#34D399" : "#047857");
        }

        private void CamBottomMicCombo_SelectionChanged(object sender, SelectionChangedEventArgs e)
        {
            if (_suppressCameraUiEvents || !_isCameraStudioInitialized) return;
            if (CamBottomMicCombo?.SelectedItem is string mic && !string.IsNullOrWhiteSpace(mic))
            {
                PlaySoundSmartPtzTick();
                _camConfig.SelectedMicrophone = mic;
                _layoutSettings.SelectedMicrophoneName = mic;
                if (CamActiveMicLabel != null) CamActiveMicLabel.Text = mic;
                if (SettingsMicrophoneCombo != null && SettingsMicrophoneCombo.Items.Contains(mic))
                    SettingsMicrophoneCombo.SelectedItem = mic;
                SaveCameraStudioConfig();
                ShowCameraToast($"🎙 Mikrofon gewechselt: {mic}");
            }
        }

        private void CamSoundMute_Click(object sender, RoutedEventArgs e)
        {
            PlaySoundSmartPtzTick();
            _camConfig.SoundMuted = !_camConfig.SoundMuted;
            FunkenwerkHardwareController.SetAudioVolumeAndMute(0, _camConfig.SoundVolume, _camConfig.SoundMuted);
            UpdateSoundMuteButtonUI(_camConfig.SoundMuted);
            SaveCameraStudioConfig();
            ShowCameraToast(_camConfig.SoundMuted ? "🔇 System-Sound stummgeschaltet" : "🔊 System-Sound aktiviert");
        }

        private void CamSoundVolumeSlider_ValueChanged(object sender, RoutedPropertyChangedEventArgs<double> e)
        {
            if (_suppressCameraUiEvents || !_isCameraStudioInitialized || CamSoundVolumeTxt == null) return;
            _camConfig.SoundVolume = (int)Math.Round(e.NewValue);
            CamSoundVolumeTxt.Text = $"{(int)_camConfig.SoundVolume} %";
            FunkenwerkHardwareController.SetAudioVolumeAndMute(0, _camConfig.SoundVolume, _camConfig.SoundMuted);
        }

        private void UpdateSoundMuteButtonUI(bool muted)
        {
            if (CamSoundMuteBtn == null) return;
            CamSoundMuteBtn.Content = muted ? "🔇 Stumm" : "🔊 Sound";
            CamSoundMuteBtn.Background = muted
                ? DirectHexBrush(_isDarkMode ? "#2D161B" : "#FEE2E2")
                : DirectHexBrush(_isDarkMode ? "#2B2D31" : "#EFF6FF");
            CamSoundMuteBtn.Foreground = muted
                ? DirectHexBrush(_isDarkMode ? "#F87171" : "#DC2626")
                : DirectHexBrush(_isDarkMode ? "#60A5FA" : "#1D4ED8");
            try
            {
                _camMusicSignalPlayer.Volume = muted ? 0.0 : Math.Clamp(_camConfig.MusicSignalVolume / 100.0, 0.0, 1.0);
            }
            catch { }
        }

        // =========================================================================
        // PHOTO SNAPSHOT & MP4 VIDEO RECORDING (Requirements 2, 3, 5)
        // =========================================================================
        private async void CamTakePhoto_Click(object sender, RoutedEventArgs e)
        {
            if (_camConfig.RecordingMode == "countdown3")
            {
                await RunCameraCountdownAsync(3);
            }
            CaptureCameraPhotoNow();
        }

        private async void CamTakePhotoTimer_Click(object sender, RoutedEventArgs e)
        {
            await RunCameraCountdownAsync(3);
            CaptureCameraPhotoNow();
        }

        private async Task RunCameraCountdownAsync(int seconds)
        {
            if (_isCountdownRunning) return;
            _isCountdownRunning = true;
            try
            {
                CamCountdownOverlay.Visibility = Visibility.Visible;
                for (int i = seconds; i >= 1; i--)
                {
                    CamCountdownNumberTxt.Text = i.ToString();
                    FunkenwerkSoundEngine.PlayCountdownBeep(0.65);
                    await Task.Delay(950);
                }
            }
            finally
            {
                CamCountdownOverlay.Visibility = Visibility.Collapsed;
                _isCountdownRunning = false;
            }
        }

        private void CaptureCameraPhotoNow()
        {
            if (_camCompositedBuffer == null) return;

            int w = _camConfig.Width;
            int h = _camConfig.Height;
            string dir = GetEffectiveCameraMediaDir();
            string fileName = $"Funkenwerk_Foto_{DateTime.Now:yyyy-MM-dd_HH-mm-ss-fff}.png";
            string fullPath = System.IO.Path.Combine(dir, fileName);

            try
            {
                var bmp = System.Windows.Media.Imaging.BitmapSource.Create(
                    w, h, 96, 96, PixelFormats.Bgr32, null, _camCompositedBuffer, w * 4);
                var encoder = new System.Windows.Media.Imaging.PngBitmapEncoder();
                encoder.Frames.Add(System.Windows.Media.Imaging.BitmapFrame.Create(bmp));
                using (var fs = new FileStream(fullPath, FileMode.Create, FileAccess.Write))
                {
                    encoder.Save(fs);
                }

                FunkenwerkSoundEngine.PlayCameraShutter(0.8);
                TriggerViewportShutterFlash();
                ShowCameraToast($"📸 Foto gespeichert: {fileName}");
                RenderCameraMediaGallery();
            }
            catch (Exception ex)
            {
                MessageBox.Show("Fehler beim Speichern des Fotos: " + ex.Message, "Funkenwerk Kamera", MessageBoxButton.OK, MessageBoxImage.Warning);
            }
        }

        private void TriggerViewportShutterFlash()
        {
            if (CamShutterFlashBorder == null) return;
            CamShutterFlashBorder.Visibility = Visibility.Visible;
            CamShutterFlashBorder.Opacity = 0.85;
            Task.Run(async () =>
            {
                for (int i = 0; i < 6; i++)
                {
                    await Task.Delay(35);
                    Dispatcher.Invoke(() => CamShutterFlashBorder.Opacity = Math.Max(0, CamShutterFlashBorder.Opacity - 0.16));
                }
                Dispatcher.Invoke(() => CamShutterFlashBorder.Visibility = Visibility.Collapsed);
            });
        }

        private async void CamRecordVideo_Click(object sender, RoutedEventArgs e)
        {
            if (_camVideoRecorder != null && _camVideoRecorder.IsRecording)
            {
                StopCameraVideoRecording();
                return;
            }

            if (_camConfig.RecordingMode == "countdown3")
            {
                await RunCameraCountdownAsync(3);
            }

            StartCameraVideoRecording();
        }

        private void StartCameraVideoRecording()
        {
            string dir = GetEffectiveCameraMediaDir();
            string modePrefix = _camConfig.RecordingMode switch
            {
                "gaming" => "GamingStream",
                "clip15" => "ShortClip",
                "silent" => "SilentVideo",
                _ => "Video"
            };
            string fileName = $"Funkenwerk_{modePrefix}_{DateTime.Now:yyyy-MM-dd_HH-mm-ss}.mp4";
            string fullPath = System.IO.Path.Combine(dir, fileName);

            bool recordMic = _camConfig.RecordingMode != "silent" && !_camConfig.MicMuted;
            bool highBitrate = _camConfig.RecordingMode == "gaming";

            _camVideoRecorder = new FunkenwerkVideoRecorder();
            bool started = _camVideoRecorder.Start(
                fullPath,
                _camConfig.Width,
                _camConfig.Height,
                _camConfig.Fps,
                _camConfig.SelectedMicrophone,
                recordMic,
                _camConfig.MicVolume,
                highBitrate,
                _camConfig);

            if (started)
            {
                PlaySoundTaskDone();
                CamRecordVideoBtn.Content = "⏹ Stopp";
                CamRecordVideoBtn.Background = DirectHexBrush("#991B1B");
                CamRecordVideoBtn.Foreground = DirectHexBrush("#FFFFFF");
                CamPauseRecordBtn.Visibility = Visibility.Visible;
                CamPauseRecordBtn.Content = "⏸";
                UpdateCameraLiveStatusBadges();
                double spd = Math.Clamp(_camConfig.RecordingSpeedMultiplier <= 0.05 ? 1.0 : _camConfig.RecordingSpeedMultiplier, 0.25, 10.0);
                string spdTxt = Math.Abs(spd - 1.0) < 0.02 ? "1x Echtzeit" : $"{spd.ToString("0.##", CultureInfo.InvariantCulture)}x Tempo";
                ShowCameraToast($"⏺ Videoaufnahme gestartet ({spdTxt} • {fileName})");
            }
            else
            {
                _camVideoRecorder.Dispose();
                _camVideoRecorder = null;
                MessageBox.Show("Videoaufnahme konnte nicht gestartet werden.", "Funkenwerk Kamera", MessageBoxButton.OK, MessageBoxImage.Warning);
            }
        }

        private void CamRecordSpeedPreset_Click(object sender, RoutedEventArgs e)
        {
            if (sender is not Button btn || btn.Tag is not string mode) return;
            PlaySoundSmartPtzTick();

            _camConfig.RecordingSpeedMode = mode;
            if (mode == "1x")
            {
                _camConfig.RecordingSpeedMultiplier = 1.0;
            }
            else if (mode == "2x")
            {
                _camConfig.RecordingSpeedMultiplier = 2.0;
            }
            else if (mode == "3x")
            {
                _camConfig.RecordingSpeedMultiplier = 3.0;
            }
            else if (mode == "custom")
            {
                if (!TryParseSpeedMultiplier(CamRecSpeedCustomBox?.Text, out double customVal))
                {
                    customVal = (_camConfig.RecordingSpeedMultiplier > 0.05 && Math.Abs(_camConfig.RecordingSpeedMultiplier - 1.0) > 0.02)
                        ? _camConfig.RecordingSpeedMultiplier
                        : 1.5;
                }
                _camConfig.RecordingSpeedMultiplier = customVal;
                if (CamRecSpeedCustomBox != null)
                    CamRecSpeedCustomBox.Text = customVal.ToString("0.##", CultureInfo.InvariantCulture);
            }

            if (_camVideoRecorder != null && _camVideoRecorder.IsRecording)
            {
                _camVideoRecorder.SpeedMultiplier = _camConfig.RecordingSpeedMultiplier;
            }

            SaveCameraStudioConfig();
            UpdateRecordingSpeedButtonsUI();
            if (mode == "custom" && CamRecSpeedCustomBox != null)
            {
                CamRecSpeedCustomBox.Focus();
                CamRecSpeedCustomBox.SelectAll();
            }

            string label = Math.Abs(_camConfig.RecordingSpeedMultiplier - 1.0) < 0.02
                ? "1x (Standard Echtzeit)"
                : $"{_camConfig.RecordingSpeedMultiplier.ToString("0.##", CultureInfo.InvariantCulture)}x";
            ShowCameraToast($"⏩ Aufnahme-Tempo gesetzt: {label}");
        }

        private void CamRecSpeedCustomBox_LostFocus(object sender, RoutedEventArgs e)
        {
            ApplyCustomRecordingSpeedFromBox();
        }

        private void CamRecSpeedCustomBox_KeyDown(object sender, KeyEventArgs e)
        {
            if (e.Key == Key.Enter)
            {
                ApplyCustomRecordingSpeedFromBox();
                e.Handled = true;
            }
        }

        private void ApplyCustomRecordingSpeedFromBox()
        {
            if (_suppressCameraUiEvents || CamRecSpeedCustomBox == null) return;
            if (TryParseSpeedMultiplier(CamRecSpeedCustomBox.Text, out double val))
            {
                _camConfig.RecordingSpeedMode = "custom";
                _camConfig.RecordingSpeedMultiplier = val;
                CamRecSpeedCustomBox.Text = val.ToString("0.##", CultureInfo.InvariantCulture);
                if (_camVideoRecorder != null && _camVideoRecorder.IsRecording)
                {
                    _camVideoRecorder.SpeedMultiplier = val;
                }
                SaveCameraStudioConfig();
                UpdateRecordingSpeedButtonsUI();
                ShowCameraToast($"⏩ Individuelles Aufnahme-Tempo: {val.ToString("0.##", CultureInfo.InvariantCulture)}x");
            }
            else
            {
                CamRecSpeedCustomBox.Text = _camConfig.RecordingSpeedMultiplier.ToString("0.##", CultureInfo.InvariantCulture);
            }
        }

        private static bool TryParseSpeedMultiplier(string? raw, out double result)
        {
            result = 1.0;
            if (string.IsNullOrWhiteSpace(raw)) return false;
            string cleaned = raw.Trim().TrimEnd('x', 'X').Replace(',', '.');
            if (double.TryParse(cleaned, NumberStyles.Float, CultureInfo.InvariantCulture, out double parsed) && parsed >= 0.25 && parsed <= 10.0)
            {
                result = Math.Round(parsed, 2);
                return true;
            }
            return false;
        }

        private void UpdateRecordingSpeedButtonsUI()
        {
            if (CamRecSpeed1xBtn == null || CamRecSpeed2xBtn == null || CamRecSpeed3xBtn == null || CamRecSpeedCustomBtn == null)
                return;

            bool dark = _isDarkMode;
            string mode = string.IsNullOrWhiteSpace(_camConfig.RecordingSpeedMode) ? "1x" : _camConfig.RecordingSpeedMode;

            if (CamRecordSpeedPillBorder != null)
            {
                CamRecordSpeedPillBorder.Background = DirectHexBrush(dark ? "#1E1F22" : "#F8FAFC");
                CamRecordSpeedPillBorder.BorderBrush = DirectHexBrush(dark ? "#2B2D31" : "#CBD5E1");
            }

            void StyleBtn(Button b, bool active)
            {
                b.Background = active
                    ? DirectHexBrush(dark ? "#26231F" : "#C5A059")
                    : Brushes.Transparent;
                b.Foreground = active
                    ? DirectHexBrush(dark ? "#C5A059" : "#111827")
                    : DirectHexBrush(dark ? "#B5BAC1" : "#334155");
                b.BorderBrush = active ? DirectHexBrush("#C5A059") : Brushes.Transparent;
                b.BorderThickness = new Thickness(active ? 1 : 0);
                b.FontWeight = active ? FontWeights.Bold : FontWeights.SemiBold;
            }

            StyleBtn(CamRecSpeed1xBtn, mode == "1x");
            StyleBtn(CamRecSpeed2xBtn, mode == "2x");
            StyleBtn(CamRecSpeed3xBtn, mode == "3x");
            StyleBtn(CamRecSpeedCustomBtn, mode == "custom");

            if (CamRecSpeedCustomWrap != null)
            {
                CamRecSpeedCustomWrap.Visibility = mode == "custom" ? Visibility.Visible : Visibility.Collapsed;
            }
            if (CamRecSpeedCustomBox != null)
            {
                CamRecSpeedCustomBox.Background = DirectHexBrush(dark ? "#111214" : "#FFFFFF");
                CamRecSpeedCustomBox.Foreground = DirectHexBrush(dark ? "#F2F3F5" : "#0F172A");
            }
        }

        private void StopCameraVideoRecording()
        {
            if (_camVideoRecorder == null) return;
            var recorderToStop = _camVideoRecorder;
            _camVideoRecorder = null;

            PlaySoundMailChime();
            CamRecordVideoBtn.Content = "⏺ Aufnahme";
            CamRecordVideoBtn.Background = DirectHexBrush("#DA373C");
            CamRecordVideoBtn.Foreground = DirectHexBrush("#FFFFFF");
            CamPauseRecordBtn.Visibility = Visibility.Collapsed;
            CamRecordingBadge.Visibility = Visibility.Collapsed;
            UpdateCameraLiveStatusBadges();

            Task.Run(() =>
            {
                string finalizedPath = "";
                try
                {
                    finalizedPath = recorderToStop.Stop();
                    recorderToStop.Dispose();
                }
                catch { }

                Dispatcher.Invoke(() =>
                {
                    if (!string.IsNullOrWhiteSpace(finalizedPath) && File.Exists(finalizedPath))
                    {
                        ShowCameraToast($"🎬 Video gespeichert: {System.IO.Path.GetFileName(finalizedPath)}");
                    }
                    else
                    {
                        ShowCameraToast("⚠ Videoaufnahme war zu kurz oder konnte nicht finalisiert werden.");
                    }
                    RenderCameraMediaGallery();
                });
            });
        }

        private void CamPauseRecord_Click(object sender, RoutedEventArgs e)
        {
            if (_camVideoRecorder == null || !_camVideoRecorder.IsRecording) return;
            PlaySoundSmartPtzTick();
            _camVideoRecorder.IsPaused = !_camVideoRecorder.IsPaused;
            CamPauseRecordBtn.Content = _camVideoRecorder.IsPaused ? "▶" : "⏸";
            ShowCameraToast(_camVideoRecorder.IsPaused ? "⏸ Videoaufnahme pausiert" : "▶ Videoaufnahme läuft weiter");
        }

        // =========================================================================
        // INSPECTOR TABS (5 TABS: BILD & ZOOM | STIMME | EFFEKTE | DEEP FAKE | CHROMA)
        // =========================================================================
        private void CamInspectorTab_Click(object sender, MouseButtonEventArgs e)
        {
            if (sender is FrameworkElement el && el.Tag is string tab)
            {
                PlaySoundNav();
                SwitchCameraInspectorTab(tab);
            }
        }

        private void SwitchCameraInspectorTab(string tab)
        {
            _activeCamInspectorTab = tab;
            if (CamInspectorImagePane != null) CamInspectorImagePane.Visibility = tab == "image" ? Visibility.Visible : Visibility.Collapsed;
            if (CamInspectorVoicePane != null) CamInspectorVoicePane.Visibility = tab == "voice" ? Visibility.Visible : Visibility.Collapsed;
            if (CamInspectorEffectsPane != null) CamInspectorEffectsPane.Visibility = tab == "effects" ? Visibility.Visible : Visibility.Collapsed;
            if (CamInspectorDeepFakePane != null) CamInspectorDeepFakePane.Visibility = tab == "deepfake" ? Visibility.Visible : Visibility.Collapsed;
            if (CamInspectorChromaPane != null) CamInspectorChromaPane.Visibility = tab == "chroma" ? Visibility.Visible : Visibility.Collapsed;

            void StyleTab(Border? b, TextBlock? t, bool active)
            {
                if (b == null || t == null) return;
                b.Background = active
                    ? DirectHexBrush(_isDarkMode ? "#2E291F" : "#C5A059")
                    : Brushes.Transparent;
                b.BorderBrush = active
                    ? DirectHexBrush(_isDarkMode ? "#C5A059" : "#B38B40")
                    : Brushes.Transparent;
                b.BorderThickness = new Thickness(1);
                t.Foreground = active
                    ? DirectHexBrush(_isDarkMode ? "#F9FAFB" : "#111827")
                    : DirectHexBrush(_isDarkMode ? "#D1D5DB" : "#334155");
                t.FontWeight = active ? FontWeights.Bold : FontWeights.SemiBold;
            }

            StyleTab(CamTabImageBtn, CamTabImageTxt, tab == "image");
            StyleTab(CamTabVoiceBtn, CamTabVoiceTxt, tab == "voice");
            StyleTab(CamTabEffectsBtn, CamTabEffectsTxt, tab == "effects");
            StyleTab(CamTabDeepFakeBtn, CamTabDeepFakeTxt, tab == "deepfake");
            StyleTab(CamTabChromaBtn, CamTabChromaTxt, tab == "chroma");
        }

        // =========================================================================
        // NEW TAB "STIMME": REAL-TIME DSP VOICE CHANGER (Phase 5 Requirement 1)
        // =========================================================================
        private int GetSelectedMicWaveInIndex()
        {
            if (CamBottomMicCombo == null || CamBottomMicCombo.SelectedIndex < 0) return -1;
            return CamBottomMicCombo.SelectedIndex;
        }

        private int GetSelectedVoiceOutWaveIndex()
        {
            if (CamVoiceOutputDeviceCombo == null || CamVoiceOutputDeviceCombo.SelectedIndex <= 0) return -1;
            return CamVoiceOutputDeviceCombo.SelectedIndex - 1;
        }

        private void SyncVoiceChangerEngineState()
        {
            _camVoiceChanger.SyncFromConfig(_camConfig, GetSelectedMicWaveInIndex(), GetSelectedVoiceOutWaveIndex());
            if (CamVoiceActivePresetTxt != null)
            {
                CamVoiceActivePresetTxt.Text = _camConfig.VoiceChangerEnabled
                    ? $"Aktiv: {FunkenwerkVoiceChangerEngine.GetPresetDisplayName(_camConfig.VoiceChangerPreset)}"
                    : $"Bereit: {FunkenwerkVoiceChangerEngine.GetPresetDisplayName(_camConfig.VoiceChangerPreset)}";
            }
        }

        private void UpdateVoicePresetButtonsUI()
        {
            if (CamVoicePresetsGrid == null) return;
            bool dark = _isDarkMode;
            foreach (var child in CamVoicePresetsGrid.Children)
            {
                if (child is Button b && b.Tag is string tag)
                {
                    bool active = string.Equals(_camConfig.VoiceChangerPreset, tag, StringComparison.OrdinalIgnoreCase);
                    b.Background = DirectHexBrush(active ? (dark ? "#26231F" : "#C5A059") : (dark ? "#13161F" : "#F1F5F9"));
                    b.Foreground = DirectHexBrush(active ? (dark ? "#C5A059" : "#111827") : (dark ? "#F9FAFB" : "#111827"));
                    b.BorderBrush = DirectHexBrush(active ? "#C5A059" : (dark ? "#262A36" : "#CBD5E1"));
                    b.BorderThickness = new Thickness(1);
                    b.FontWeight = active ? FontWeights.Bold : FontWeights.SemiBold;
                }
            }
        }

        private void CamVoiceEnableCheck_Changed(object sender, RoutedEventArgs e)
        {
            if (_suppressCameraUiEvents || !_isCameraStudioInitialized) return;
            PlaySoundActionPop();
            _camConfig.VoiceChangerEnabled = CamVoiceEnableCheck?.IsChecked == true;
            if (_camConfig.VoiceChangerEnabled && string.Equals(_camConfig.VoiceChangerPreset, "none", StringComparison.OrdinalIgnoreCase) &&
                Math.Abs(_camConfig.VoicePitchSemitones) < 0.1 && _camConfig.VoiceRobotMix == 0)
            {
                FunkenwerkVoiceChangerEngine.ApplyPresetToConfig(_camConfig, "anonymous");
                _suppressCameraUiEvents = true;
                SyncCameraUiFromConfig();
                _suppressCameraUiEvents = false;
            }
            SyncVoiceChangerEngineState();
            SaveCameraStudioConfig();
            UpdateCameraLiveStatusBadges();
            ShowCameraToast(_camConfig.VoiceChangerEnabled
                ? $"🎙 Stimmenverzerrer aktiv: {FunkenwerkVoiceChangerEngine.GetPresetDisplayName(_camConfig.VoiceChangerPreset)}"
                : "🎙 Stimmenverzerrer deaktiviert (Original-Stimme)");
        }

        private void CamVoicePreset_Click(object sender, RoutedEventArgs e)
        {
            if (sender is not Button btn || btn.Tag is not string presetKey) return;
            PlaySoundActionPop();

            FunkenwerkVoiceChangerEngine.ApplyPresetToConfig(_camConfig, presetKey);
            _camConfig.VoiceChangerEnabled = presetKey != "none";

            _suppressCameraUiEvents = true;
            if (CamVoiceEnableCheck != null) CamVoiceEnableCheck.IsChecked = _camConfig.VoiceChangerEnabled;
            if (CamVoicePitchSlider != null) CamVoicePitchSlider.Value = _camConfig.VoicePitchSemitones;
            if (CamVoicePitchTxt != null) CamVoicePitchTxt.Text = $"{_camConfig.VoicePitchSemitones:+0.0;-0.0;0.0} st";
            if (CamVoiceFormantSlider != null) CamVoiceFormantSlider.Value = _camConfig.VoiceFormantShift;
            if (CamVoiceFormantTxt != null) CamVoiceFormantTxt.Text = $"{(int)Math.Round(_camConfig.VoiceFormantShift)}%";
            if (CamVoiceRobotSlider != null) CamVoiceRobotSlider.Value = _camConfig.VoiceRobotMix;
            if (CamVoiceRobotTxt != null) CamVoiceRobotTxt.Text = $"{_camConfig.VoiceRobotMix}%";
            if (CamVoiceReverbSlider != null) CamVoiceReverbSlider.Value = _camConfig.VoiceReverbMix;
            if (CamVoiceReverbTxt != null) CamVoiceReverbTxt.Text = $"{_camConfig.VoiceReverbMix}%";
            if (CamVoiceBassSlider != null) CamVoiceBassSlider.Value = _camConfig.VoiceBassBoost;
            if (CamVoiceBassTxt != null) CamVoiceBassTxt.Text = $"{_camConfig.VoiceBassBoost}%";
            _suppressCameraUiEvents = false;

            UpdateVoicePresetButtonsUI();
            SyncVoiceChangerEngineState();
            SaveCameraStudioConfig();
            UpdateCameraLiveStatusBadges();

            ShowCameraToast(presetKey == "none"
                ? "🟢 Stimmen-Profil: Natürlich (Original)"
                : $"🎙 Stimmen-Profil geladen: {FunkenwerkVoiceChangerEngine.GetPresetDisplayName(presetKey)}");
        }

        private void CamVoiceSlider_ValueChanged(object sender, RoutedPropertyChangedEventArgs<double> e)
        {
            if (_suppressCameraUiEvents || !_isCameraStudioInitialized) return;
            if (CamVoicePitchSlider != null) _camConfig.VoicePitchSemitones = Math.Round(CamVoicePitchSlider.Value, 1);
            if (CamVoiceFormantSlider != null) _camConfig.VoiceFormantShift = Math.Round(CamVoiceFormantSlider.Value, 0);
            if (CamVoiceRobotSlider != null) _camConfig.VoiceRobotMix = (int)Math.Round(CamVoiceRobotSlider.Value);
            if (CamVoiceReverbSlider != null) _camConfig.VoiceReverbMix = (int)Math.Round(CamVoiceReverbSlider.Value);
            if (CamVoiceBassSlider != null) _camConfig.VoiceBassBoost = (int)Math.Round(CamVoiceBassSlider.Value);
            if (CamVoiceGateSlider != null) _camConfig.VoiceNoiseGate = (int)Math.Round(CamVoiceGateSlider.Value);

            if (CamVoicePitchTxt != null) CamVoicePitchTxt.Text = $"{_camConfig.VoicePitchSemitones:+0.0;-0.0;0.0} st";
            if (CamVoiceFormantTxt != null) CamVoiceFormantTxt.Text = $"{(int)_camConfig.VoiceFormantShift}%";
            if (CamVoiceRobotTxt != null) CamVoiceRobotTxt.Text = $"{_camConfig.VoiceRobotMix}%";
            if (CamVoiceReverbTxt != null) CamVoiceReverbTxt.Text = $"{_camConfig.VoiceReverbMix}%";
            if (CamVoiceBassTxt != null) CamVoiceBassTxt.Text = $"{_camConfig.VoiceBassBoost}%";
            if (CamVoiceGateTxt != null) CamVoiceGateTxt.Text = $"{_camConfig.VoiceNoiseGate}%";

            if (!_camConfig.VoiceChangerEnabled &&
                (Math.Abs(_camConfig.VoicePitchSemitones) > 0.2 || Math.Abs(_camConfig.VoiceFormantShift) > 2 || _camConfig.VoiceRobotMix > 0))
            {
                _camConfig.VoiceChangerEnabled = true;
                _suppressCameraUiEvents = true;
                if (CamVoiceEnableCheck != null) CamVoiceEnableCheck.IsChecked = true;
                _suppressCameraUiEvents = false;
                UpdateCameraLiveStatusBadges();
            }

            SyncVoiceChangerEngineState();
            SaveCameraStudioConfig();
        }

        private void CamVoiceResetSliders_Click(object sender, RoutedEventArgs e)
        {
            PlaySoundActionPop();
            FunkenwerkVoiceChangerEngine.ApplyPresetToConfig(_camConfig, "none");
            _camConfig.VoiceNoiseGate = 10;
            _camConfig.VoiceChangerEnabled = false;

            _suppressCameraUiEvents = true;
            SyncCameraUiFromConfig();
            _suppressCameraUiEvents = false;

            SaveCameraStudioConfig();
            UpdateCameraLiveStatusBadges();
            ShowCameraToast("↺ Stimmenverzerrer Regler auf Standard zurückgesetzt");
        }

        private void CamVoiceMonitorCheck_Changed(object sender, RoutedEventArgs e)
        {
            if (_suppressCameraUiEvents || !_isCameraStudioInitialized) return;
            PlaySoundSmartPtzTick();
            _camConfig.VoiceLiveMonitor = CamVoiceMonitorCheck?.IsChecked == true;
            if (_camConfig.VoiceLiveMonitor && !_camConfig.VoiceChangerEnabled)
            {
                _camConfig.VoiceChangerEnabled = true;
                _suppressCameraUiEvents = true;
                if (CamVoiceEnableCheck != null) CamVoiceEnableCheck.IsChecked = true;
                _suppressCameraUiEvents = false;
            }
            SyncVoiceChangerEngineState();
            SaveCameraStudioConfig();
            UpdateCameraLiveStatusBadges();
            ShowCameraToast(_camConfig.VoiceLiveMonitor
                ? "🎧 Live-Monitoring aktiv: Du hörst deine verzerrte Stimme jetzt in Echtzeit!"
                : "🎧 Live-Monitoring beendet");
        }

        private void CamVoiceOutputDeviceCombo_SelectionChanged(object sender, SelectionChangedEventArgs e)
        {
            if (_suppressCameraUiEvents || !_isCameraStudioInitialized) return;
            if (CamVoiceOutputDeviceCombo?.SelectedItem is string dev)
            {
                _camConfig.VoiceOutputDevice = dev;
                SyncVoiceChangerEngineState();
                SaveCameraStudioConfig();
            }
        }

        private async void CamVoiceRecordSample_Click(object sender, RoutedEventArgs e)
        {
            if (_camVoiceChanger.IsRecordingSample) return;
            PlaySoundTaskDone();

            if (!_camConfig.VoiceChangerEnabled)
            {
                _camConfig.VoiceChangerEnabled = true;
                if (_camConfig.VoiceChangerPreset == "none")
                    FunkenwerkVoiceChangerEngine.ApplyPresetToConfig(_camConfig, "anonymous");
                _suppressCameraUiEvents = true;
                SyncCameraUiFromConfig();
                _suppressCameraUiEvents = false;
            }

            _camVoiceChanger.StartSampleRecording(_camConfig, GetSelectedMicWaveInIndex());
            if (CamVoiceRecordSampleBtn != null)
            {
                CamVoiceRecordSampleBtn.IsEnabled = false;
                CamVoiceRecordSampleBtn.Content = "⏺ Sprich (4s)...";
            }
            if (CamVoiceSampleStatusTxt != null)
            {
                CamVoiceSampleStatusTxt.Visibility = Visibility.Visible;
                CamVoiceSampleStatusTxt.Text = "🎙 Aufnahme läuft (4 Sekunden) · Sprich jetzt in dein Mikrofon...";
            }

            for (int s = 4; s >= 1; s--)
            {
                if (CamVoiceRecordSampleBtn != null) CamVoiceRecordSampleBtn.Content = $"⏺ Sprich ({s}s)...";
                await Task.Delay(1000);
            }

            if (CamVoiceRecordSampleBtn != null)
            {
                CamVoiceRecordSampleBtn.IsEnabled = true;
                CamVoiceRecordSampleBtn.Content = "⏺ 4s Stimm-Probe";
            }
            if (CamVoiceSampleStatusTxt != null)
            {
                CamVoiceSampleStatusTxt.Visibility = Visibility.Visible;
                CamVoiceSampleStatusTxt.Text = "✅ 4s Stimm-Probe bereit · Klicke auf '▶ Probe anhören'.";
            }

            SyncVoiceChangerEngineState();
            ShowCameraToast("🎙 4s Stimm-Probe aufgenommen · Spiele sie jetzt mit jedem Effekt ab!");
        }

        private void CamVoicePlaySample_Click(object sender, RoutedEventArgs e)
        {
            if (!_camVoiceChanger.HasRecordedSample)
            {
                ShowCameraToast("ℹ Bitte nimm zuerst mit '⏺ 4s Stimm-Probe' eine kurze Sprachprobe auf!");
                return;
            }
            PlaySoundActionPop();
            if (!_camConfig.VoiceChangerEnabled)
            {
                _camConfig.VoiceChangerEnabled = true;
                _suppressCameraUiEvents = true;
                if (CamVoiceEnableCheck != null) CamVoiceEnableCheck.IsChecked = true;
                _suppressCameraUiEvents = false;
            }
            _camVoiceChanger.PlayRecordedSampleWithCurrentDsp(_camConfig, GetSelectedVoiceOutWaveIndex());
            ShowCameraToast($"🔊 Spiele Stimm-Probe mit Profil '{FunkenwerkVoiceChangerEngine.GetPresetDisplayName(_camConfig.VoiceChangerPreset)}' ab...");
        }

        // 10. ZOOM & PAN / TILT / FLIP (Works even at 1.0x via Overscan + Hardware PTZ)
        private void CamZoomSlider_ValueChanged(object sender, RoutedPropertyChangedEventArgs<double> e)
        {
            if (_suppressCameraUiEvents || !_isCameraStudioInitialized || CamZoomValueTxt == null) return;
            _camConfig.Zoom = Math.Round(e.NewValue, 2);
            CamZoomValueTxt.Text = $"{_camConfig.Zoom:0.0}x";
            ApplyHardwareCameraSettingsAsync();
        }

        private void CamZoomPreset_Click(object sender, RoutedEventArgs e)
        {
            if (sender is Button btn && double.TryParse(btn.Tag?.ToString(), NumberStyles.Any, CultureInfo.InvariantCulture, out double z))
            {
                PlaySoundSmartPtzTick();
                CamZoomSlider.Value = z;
                if (z <= 1.01)
                {
                    CamPanXSlider.Value = 0;
                    CamPanYSlider.Value = 0;
                }
                SaveCameraStudioConfig();
            }
        }

        private void CamPanTilt_ValueChanged(object sender, RoutedPropertyChangedEventArgs<double> e)
        {
            if (_suppressCameraUiEvents || !_isCameraStudioInitialized) return;
            _camConfig.PanX = (int)Math.Round(CamPanXSlider.Value);
            _camConfig.PanY = (int)Math.Round(CamPanYSlider.Value);
            if (CamPanXTxt != null) CamPanXTxt.Text = _camConfig.PanX.ToString();
            if (CamPanYTxt != null) CamPanYTxt.Text = _camConfig.PanY.ToString();
            ApplyHardwareCameraSettingsAsync();
        }

        private void CamTransformCheck_Changed(object sender, RoutedEventArgs e)
        {
            if (_suppressCameraUiEvents || !_isCameraStudioInitialized) return;
            PlaySoundSmartPtzTick();
            _camConfig.MirrorHorizontal = CamMirrorHCheck.IsChecked == true;
            _camConfig.FlipVertical = CamFlipVCheck.IsChecked == true;
            SaveCameraStudioConfig();
        }

        // 11 & 12. AUTOFOCUS & AUTO WHITE BALANCE
        private void CamAutofocusCheck_Changed(object sender, RoutedEventArgs e)
        {
            if (_suppressCameraUiEvents || !_isCameraStudioInitialized) return;
            PlaySoundSmartPtzTick();
            _camConfig.Autofocus = CamAutofocusCheck.IsChecked == true;
            CamManualFocusRow.IsEnabled = !_camConfig.Autofocus;
            CamManualFocusRow.Opacity = _camConfig.Autofocus ? 0.45 : 1.0;
            SaveCameraStudioConfig();
            ApplyHardwareCameraSettingsAsync();
            ShowCameraToast(_camConfig.Autofocus ? "🎯 Autofokus aktiviert" : "🎯 Manueller Fokus aktiv");
        }

        private void CamManualFocusSlider_ValueChanged(object sender, RoutedPropertyChangedEventArgs<double> e)
        {
            if (_suppressCameraUiEvents || !_isCameraStudioInitialized || CamManualFocusTxt == null) return;
            _camConfig.ManualFocus = (int)Math.Round(e.NewValue);
            CamManualFocusTxt.Text = _camConfig.ManualFocus.ToString();
            ApplyHardwareCameraSettingsAsync();
        }

        private void CamAutoWbCheck_Changed(object sender, RoutedEventArgs e)
        {
            if (_suppressCameraUiEvents || !_isCameraStudioInitialized) return;
            PlaySoundSmartPtzTick();
            _camConfig.AutoWhiteBalance = CamAutoWbCheck.IsChecked == true;
            SaveCameraStudioConfig();
            ApplyHardwareCameraSettingsAsync();
            ShowCameraToast(_camConfig.AutoWhiteBalance ? "🌡 Automatischer Weißabgleich (AWB) aktiv" : "🌡 Weißabgleich manuell");
        }

        // 13. BILDEINSTELLUNGEN (Helligkeit, Kontrast, Wärme, Schärfe)
        private void CamImageSetting_ValueChanged(object sender, RoutedPropertyChangedEventArgs<double> e)
        {
            if (_suppressCameraUiEvents || !_isCameraStudioInitialized) return;
            _camConfig.Brightness = (int)Math.Round(CamBrightnessSlider.Value);
            _camConfig.Contrast = (int)Math.Round(CamContrastSlider.Value);
            _camConfig.Warmth = (int)Math.Round(CamWarmthSlider.Value);
            _camConfig.Sharpness = (int)Math.Round(CamSharpnessSlider.Value);

            if (CamBrightnessTxt != null) CamBrightnessTxt.Text = _camConfig.Brightness.ToString();
            if (CamContrastTxt != null) CamContrastTxt.Text = _camConfig.Contrast.ToString();
            if (CamWarmthTxt != null) CamWarmthTxt.Text = _camConfig.Warmth.ToString();
            if (CamSharpnessTxt != null) CamSharpnessTxt.Text = _camConfig.Sharpness.ToString();

            ApplyHardwareCameraSettingsAsync();
        }

        private void CamResetImageAdjustments_Click(object sender, RoutedEventArgs e)
        {
            PlaySoundActionPop();
            _suppressCameraUiEvents = true;
            _camConfig.Brightness = 0;
            _camConfig.Contrast = 0;
            _camConfig.Warmth = 0;
            _camConfig.Sharpness = 0;
            _camConfig.Zoom = 1.0;
            _camConfig.PanX = 0;
            _camConfig.PanY = 0;
            SyncCameraUiFromConfig();
            _suppressCameraUiEvents = false;
            SaveCameraStudioConfig();
            ApplyHardwareCameraSettingsAsync();
            ShowCameraToast("↺ Bildeinstellungen, Schwenken/Neigen & Zoom zurückgesetzt");
        }

        // =========================================================================
        // DYNAMIC VIDEO SIGNAL INJECTOR, TARN-CLIP (10S DEFAULT) & INSTAGRAM BOOMERANG
        // =========================================================================
        private void CamVideoLoopCheck_Changed(object sender, RoutedEventArgs e)
        {
            if (_suppressCameraUiEvents || !_isCameraStudioInitialized) return;
            PlaySoundSmartPtzTick();
            _camConfig.VideoSignalLoop = CamVideoLoopCheck.IsChecked == true;
            if (_camConfig.VideoSignalLoop && _camConfig.VideoSignalReturnToLive)
            {
                _camConfig.VideoSignalReturnToLive = false;
                _suppressCameraUiEvents = true;
                if (CamVideoSignalReturnLiveCheck != null) CamVideoSignalReturnLiveCheck.IsChecked = false;
                _suppressCameraUiEvents = false;
            }
            _camMediaInjector.LoopEnabled = _camConfig.VideoSignalLoop && !_camConfig.VideoSignalReturnToLive;
            _camMediaInjector.ReturnToLiveAfterEnd = _camConfig.VideoSignalReturnToLive;
            SaveCameraStudioConfig();
            UpdateVideoSignalStatusBannerText();
        }

        private void CamVideoSignalReturnLiveCheck_Changed(object sender, RoutedEventArgs e)
        {
            if (_suppressCameraUiEvents || !_isCameraStudioInitialized) return;
            PlaySoundSmartPtzTick();
            _camConfig.VideoSignalReturnToLive = CamVideoSignalReturnLiveCheck?.IsChecked == true;
            if (_camConfig.VideoSignalReturnToLive)
            {
                _camConfig.VideoSignalLoop = false;
                _suppressCameraUiEvents = true;
                if (CamVideoLoopCheck != null) CamVideoLoopCheck.IsChecked = false;
                _suppressCameraUiEvents = false;
                ShowCameraToast("↩ Aktiviert: Nach Ablauf des Clips springt das Bild automatisch auf die Original-Aufnahme!");
            }
            _camMediaInjector.LoopEnabled = _camConfig.VideoSignalLoop && !_camConfig.VideoSignalReturnToLive;
            _camMediaInjector.ReturnToLiveAfterEnd = _camConfig.VideoSignalReturnToLive;
            SaveCameraStudioConfig();
            UpdateVideoSignalStatusBannerText();
        }

        private void CamVideoSignalBoomerangCheck_Changed(object sender, RoutedEventArgs e)
        {
            if (_suppressCameraUiEvents || !_isCameraStudioInitialized) return;
            PlaySoundSmartPtzTick();
            _camConfig.VideoSignalBoomerang = CamVideoSignalBoomerangCheck?.IsChecked == true;
            _camMediaInjector.BoomerangEnabled = _camConfig.VideoSignalBoomerang;
            SaveCameraStudioConfig();
            UpdateVideoSignalStatusBannerText();
            ShowCameraToast(_camConfig.VideoSignalBoomerang
                ? "🪃 Instagram Boomerang-Modus aktiv (Vorwärts ⇄ Rückwärts)!"
                : "🪃 Boomerang-Modus deaktiviert");
        }

        private void UpdateVideoSignalStatusBannerText()
        {
            if (!_camMediaInjector.IsPlaying || CamVideoSignalStatusTxt == null) return;
            string clipName = System.IO.Path.GetFileNameWithoutExtension(_camMediaInjector.ActiveVideoPath);
            string modeDesc = _camConfig.VideoSignalReturnToLive
                ? "1x → Zurück zu Live"
                : (_camConfig.VideoSignalLoop ? "Dauerschleife" : "1x Abspielen");
            if (_camConfig.VideoSignalBoomerang)
                modeDesc = "🪃 Boomerang • " + modeDesc;
            if (_videoSignalAutoStopDeadlineUtc.HasValue)
            {
                int rem = Math.Max(1, (int)Math.Ceiling((_videoSignalAutoStopDeadlineUtc.Value - DateTime.UtcNow).TotalSeconds));
                modeDesc = $"⏱ Stopp in {rem}s • " + modeDesc;
            }
            CamVideoSignalStatusTxt.Text = $"▶ Sendet: {clipName} ({modeDesc})";
        }

        private void CamTarnDurationPreset_Click(object sender, RoutedEventArgs e)
        {
            if (sender is not Button btn || btn.Tag is not string tag) return;
            PlaySoundSmartPtzTick();
            _camConfig.TarnClipDurationMode = tag;

            if (tag == "5") _camConfig.TarnClipDurationSec = 5;
            else if (tag == "10") _camConfig.TarnClipDurationSec = 10;
            else if (tag == "15") _camConfig.TarnClipDurationSec = 15;
            else
            {
                if (CamTarnCustomSecBox != null && int.TryParse(CamTarnCustomSecBox.Text.Trim(), out int customSec))
                    _camConfig.TarnClipDurationSec = Math.Clamp(customSec, 1, 120);
                else
                    _camConfig.TarnClipDurationSec = 10;
                CamTarnCustomSecBox?.Focus();
                CamTarnCustomSecBox?.SelectAll();
            }

            _suppressCameraUiEvents = true;
            if (CamTarnCustomSecBox != null) CamTarnCustomSecBox.Text = _camConfig.TarnClipDurationSec.ToString();
            _suppressCameraUiEvents = false;

            UpdateTarnDurationButtonsUI();
            SaveCameraStudioConfig();
            ShowCameraToast($"⏱ Tarn-Clip Dauer auf {_camConfig.TarnClipDurationSec} Sekunden eingestellt");
        }

        private void CamTarnCustomSecBox_TextChanged(object sender, TextChangedEventArgs e)
        {
            if (_suppressCameraUiEvents || !_isCameraStudioInitialized || CamTarnCustomSecBox == null) return;
            if (int.TryParse(CamTarnCustomSecBox.Text.Trim(), out int sec))
            {
                _camConfig.TarnClipDurationSec = Math.Clamp(sec, 1, 120);
                _camConfig.TarnClipDurationMode = _camConfig.TarnClipDurationSec switch
                {
                    5 => "5",
                    10 => "10",
                    15 => "15",
                    _ => "custom"
                };
                UpdateTarnDurationButtonsUI();
                SaveCameraStudioConfig();
            }
        }

        private void UpdateTarnDurationButtonsUI()
        {
            int sec = Math.Clamp(_camConfig.TarnClipDurationSec <= 0 ? 10 : _camConfig.TarnClipDurationSec, 1, 120);
            string mode = string.IsNullOrWhiteSpace(_camConfig.TarnClipDurationMode) ? "10" : _camConfig.TarnClipDurationMode;

            void StyleBtn(Button? b, bool active)
            {
                if (b == null) return;
                b.Background = active
                    ? DirectHexBrush(_isDarkMode ? "#26231F" : "#C5A059")
                    : DirectHexBrush(_isDarkMode ? "#1E1F22" : "#FFFFFF");
                b.Foreground = active
                    ? DirectHexBrush(_isDarkMode ? "#C5A059" : "#111827")
                    : DirectHexBrush(_isDarkMode ? "#D1D5DB" : "#1F2937");
                b.BorderBrush = active
                    ? DirectHexBrush("#C5A059")
                    : DirectHexBrush(_isDarkMode ? "#2B2D31" : "#CBD5E1");
                b.BorderThickness = new Thickness(1);
            }

            StyleBtn(CamTarnLen5Btn, mode == "5");
            StyleBtn(CamTarnLen10Btn, mode == "10");
            StyleBtn(CamTarnLen15Btn, mode == "15");
            StyleBtn(CamTarnLenCustomBtn, mode == "custom");

            if (CamRecordQuickLoopBtn != null && !_isRecordingQuickLoop)
            {
                CamRecordQuickLoopBtn.Content = $"⏺ {sec}s Tarn-Clip";
            }
        }

        private void CamAddVideoSignal_Click(object sender, RoutedEventArgs e)
        {
            var dlg = new Microsoft.Win32.OpenFileDialog
            {
                Title = "Video oder Clip für Kamera-Einspieler hinzufügen",
                Filter = "Videodateien (*.mp4;*.mov;*.webm;*.avi;*.mkv;*.wmv)|*.mp4;*.mov;*.webm;*.avi;*.mkv;*.wmv",
                Multiselect = true
            };

            if (dlg.ShowDialog() == true && dlg.FileNames.Length > 0)
            {
                PlaySoundActionPop();
                foreach (var f in dlg.FileNames)
                {
                    if (!_camConfig.VideoSignalClips.Any(c => string.Equals(c.FilePath, f, StringComparison.OrdinalIgnoreCase)))
                    {
                        _camConfig.VideoSignalClips.Add(new VideoSignalClipItem
                        {
                            Title = System.IO.Path.GetFileNameWithoutExtension(f),
                            FilePath = f
                        });
                    }
                }
                SaveCameraStudioConfig();
                RenderVideoSignalDeckList();
                ShowCameraToast($"🎬 {dlg.FileNames.Length} Video(s) zum Einspieler hinzugefügt!");
            }
        }

        private async void CamRecordQuickLoop_Click(object sender, RoutedEventArgs e)
        {
            if (_isRecordingQuickLoop || (_camVideoRecorder != null && _camVideoRecorder.IsRecording))
            {
                ShowCameraToast("⏳ Es läuft bereits eine Videoaufnahme!");
                return;
            }

            int durationSec = Math.Clamp(_camConfig.TarnClipDurationSec <= 0 ? 10 : _camConfig.TarnClipDurationSec, 1, 120);
            _isRecordingQuickLoop = true;
            string dir = GetEffectiveCameraMediaDir();
            string clipTitle = $"TarnClip_{durationSec}s_{DateTime.Now:HH-mm-ss}";
            string fullPath = System.IO.Path.Combine(dir, $"Funkenwerk_{clipTitle}.mp4");

            if (CamRecordQuickLoopBtn != null)
            {
                CamRecordQuickLoopBtn.IsEnabled = false;
                CamRecordQuickLoopBtn.Content = $"⏺ {durationSec}s läuft...";
            }

            try
            {
                _camVideoRecorder = new FunkenwerkVideoRecorder();
                bool started = _camVideoRecorder.Start(
                    fullPath,
                    _camConfig.Width,
                    _camConfig.Height,
                    _camConfig.Fps,
                    _camConfig.SelectedMicrophone,
                    false,
                    _camConfig.MicVolume,
                    false,
                    _camConfig);

                if (!started)
                {
                    _camVideoRecorder.Dispose();
                    _camVideoRecorder = null;
                    ShowCameraToast("⚠ Tarn-Clip Aufnahme konnte nicht gestartet werden");
                    return;
                }

                PlaySoundTaskDone();
                for (int sec = durationSec; sec >= 1; sec--)
                {
                    if (CamRecordQuickLoopBtn != null) CamRecordQuickLoopBtn.Content = $"⏺ {sec}s...";
                    if (CamRecordingBadge != null && CamRecordingTimerTxt != null)
                    {
                        CamRecordingBadge.Visibility = Visibility.Visible;
                        CamRecordingTimerTxt.Text = $"TARN-CLIP {sec}s";
                    }
                    await Task.Delay(1000);
                }

                _camVideoRecorder.Stop();
                _camVideoRecorder.Dispose();
                _camVideoRecorder = null;

                if (CamRecordingBadge != null) CamRecordingBadge.Visibility = Visibility.Collapsed;

                await Task.Delay(350);
                if (File.Exists(fullPath))
                {
                    var newClip = new VideoSignalClipItem
                    {
                        Title = clipTitle,
                        FilePath = fullPath
                    };
                    _camConfig.VideoSignalClips.Insert(0, newClip);
                    SaveCameraStudioConfig();
                    RenderVideoSignalDeckList();
                    RenderCameraMediaGallery();
                    PlaySoundMailChime();
                    ShowCameraToast($"🎬 {durationSec}s Tarn-Clip '{clipTitle}' bereit zum Einspielen!");
                }
            }
            finally
            {
                _isRecordingQuickLoop = false;
                if (CamRecordQuickLoopBtn != null)
                {
                    CamRecordQuickLoopBtn.IsEnabled = true;
                    CamRecordQuickLoopBtn.Content = $"⏺ {durationSec}s Tarn-Clip";
                }
            }
        }

        private async void CamRecordBoomerangClip_Click(object sender, RoutedEventArgs e)
        {
            if (_isRecordingQuickLoop || (_camVideoRecorder != null && _camVideoRecorder.IsRecording))
            {
                ShowCameraToast("⏳ Es läuft bereits eine Aufnahme!");
                return;
            }

            _isRecordingQuickLoop = true;
            string dir = GetEffectiveCameraMediaDir();
            string stamp = DateTime.Now.ToString("HH-mm-ss");
            string rawPath = System.IO.Path.Combine(dir, $"_tmp_boom_{stamp}.mp4");
            string clipTitle = $"🪃 Boomerang_{stamp}";
            string finalPath = System.IO.Path.Combine(dir, $"Funkenwerk_Boomerang_{stamp}.mp4");

            if (CamRecordBoomerangBtn != null)
            {
                CamRecordBoomerangBtn.IsEnabled = false;
                CamRecordBoomerangBtn.Content = "🪃 3s Burst...";
            }

            try
            {
                _camVideoRecorder = new FunkenwerkVideoRecorder();
                bool started = _camVideoRecorder.Start(
                    rawPath,
                    _camConfig.Width,
                    _camConfig.Height,
                    Math.Max(30, _camConfig.Fps),
                    _camConfig.SelectedMicrophone,
                    false,
                    _camConfig.MicVolume,
                    false);

                if (!started)
                {
                    _camVideoRecorder.Dispose();
                    _camVideoRecorder = null;
                    ShowCameraToast("⚠ Boomerang-Aufnahme konnte nicht gestartet werden");
                    return;
                }

                PlaySoundTaskDone();
                for (int step = 3; step >= 1; step--)
                {
                    if (CamRecordBoomerangBtn != null) CamRecordBoomerangBtn.Content = $"🪃 {step}s...";
                    if (CamRecordingBadge != null && CamRecordingTimerTxt != null)
                    {
                        CamRecordingBadge.Visibility = Visibility.Visible;
                        CamRecordingTimerTxt.Text = $"BOOMERANG {step}s";
                    }
                    await Task.Delay(850);
                }

                _camVideoRecorder.Stop();
                _camVideoRecorder.Dispose();
                _camVideoRecorder = null;

                if (CamRecordingBadge != null) CamRecordingBadge.Visibility = Visibility.Collapsed;
                if (CamRecordBoomerangBtn != null) CamRecordBoomerangBtn.Content = "🪃 Erzeuge...";

                await Task.Delay(300);

                // Generate true forward + reverse Instagram Boomerang MP4 via FFmpeg
                await Task.Run(() =>
                {
                    try
                    {
                        string ffmpegPath = ResolveFfmpegBinaryPath();
                        var psi = new ProcessStartInfo
                        {
                            FileName = ffmpegPath,
                            UseShellExecute = false,
                            RedirectStandardError = true,
                            CreateNoWindow = true
                        };
                        psi.ArgumentList.Add("-y");
                        psi.ArgumentList.Add("-hide_banner");
                        psi.ArgumentList.Add("-loglevel");
                        psi.ArgumentList.Add("error");
                        psi.ArgumentList.Add("-i");
                        psi.ArgumentList.Add(rawPath);
                        psi.ArgumentList.Add("-filter_complex");
                        psi.ArgumentList.Add("[0:v]setpts=1.0*PTS,split[f][r];[r]reverse[rev];[f][rev]concat=n=2:v=1:a=0");
                        psi.ArgumentList.Add("-c:v");
                        psi.ArgumentList.Add("libx264");
                        psi.ArgumentList.Add("-preset");
                        psi.ArgumentList.Add("ultrafast");
                        psi.ArgumentList.Add("-crf");
                        psi.ArgumentList.Add("20");
                        psi.ArgumentList.Add("-pix_fmt");
                        psi.ArgumentList.Add("yuv420p");
                        psi.ArgumentList.Add(finalPath);

                        using var p = Process.Start(psi);
                        if (p != null)
                        {
                            var stderrTask = p.StandardError.ReadToEndAsync();
                            if (!p.WaitForExit(8000))
                            {
                                try { p.Kill(entireProcessTree: true); } catch { }
                            }
                            try { stderrTask.Wait(500); } catch { }
                        }
                    }
                    catch { }
                });

                if (!File.Exists(finalPath) && File.Exists(rawPath))
                {
                    File.Move(rawPath, finalPath, true);
                }
                else
                {
                    try { if (File.Exists(rawPath)) File.Delete(rawPath); } catch { }
                }

                if (File.Exists(finalPath))
                {
                    var boomClip = new VideoSignalClipItem
                    {
                        Title = clipTitle,
                        FilePath = finalPath
                    };
                    _camConfig.VideoSignalClips.Insert(0, boomClip);
                    _camConfig.VideoSignalBoomerang = true;
                    _camMediaInjector.BoomerangEnabled = true;

                    _suppressCameraUiEvents = true;
                    if (CamVideoSignalBoomerangCheck != null) CamVideoSignalBoomerangCheck.IsChecked = true;
                    _suppressCameraUiEvents = false;

                    SaveCameraStudioConfig();
                    RenderVideoSignalDeckList();
                    RenderCameraMediaGallery();
                    StartVideoSignalClipPlayback(boomClip);
                    PlaySoundMailChime();
                    ShowCameraToast("🪃 Instagram Boomerang-Clip erstellt & läuft vorwärts ⇄ rückwärts!");
                }
            }
            finally
            {
                _isRecordingQuickLoop = false;
                if (CamRecordBoomerangBtn != null)
                {
                    CamRecordBoomerangBtn.IsEnabled = true;
                    CamRecordBoomerangBtn.Content = "🪃 Boomerang";
                }
            }
        }

        private void StartVideoSignalClipPlayback(VideoSignalClipItem clip)
        {
            if (!File.Exists(clip.FilePath))
            {
                _camConfig.VideoSignalClips.Remove(clip);
                SaveCameraStudioConfig();
                RenderVideoSignalDeckList();
                return;
            }

            PlaySoundActionPop();
            _camConfig.ActiveVideoSignalPath = clip.FilePath;

            // Configure optional Per-Video Auto-Stop Timer (Phase 5 Requirement 3)
            if (clip.AutoStopTimerEnabled && clip.AutoStopDurationSec > 0)
            {
                int sec = Math.Clamp(clip.AutoStopDurationSec, 1, 600);
                _videoSignalAutoStopDeadlineUtc = DateTime.UtcNow.AddSeconds(sec);
                _videoSignalAutoStopLastDisplayedSec = sec;
            }
            else
            {
                _videoSignalAutoStopDeadlineUtc = null;
                _videoSignalAutoStopLastDisplayedSec = -1;
            }

            bool effectiveLoop = (_camConfig.VideoSignalLoop && !_camConfig.VideoSignalReturnToLive) || clip.AutoStopTimerEnabled;
            _camMediaInjector.BoomerangEnabled = _camConfig.VideoSignalBoomerang;
            _camMediaInjector.ReturnToLiveAfterEnd = _camConfig.VideoSignalReturnToLive && !clip.AutoStopTimerEnabled;
            _camMediaInjector.StartVideo(clip.FilePath, _camConfig.Width, _camConfig.Height, _camConfig.Fps, effectiveLoop);

            if (CamVideoSignalActiveBar != null && CamVideoSignalStatusTxt != null)
            {
                CamVideoSignalActiveBar.Visibility = Visibility.Visible;
                UpdateVideoSignalStatusBannerText();
            }

            RenderVideoSignalDeckList();
            UpdateCameraLiveStatusBadges();
            ShowCameraToast(clip.AutoStopTimerEnabled
                ? $"📡 Sende '{clip.Title}' für {clip.AutoStopDurationSec}s → danach automatisch Live-Kamera!"
                : $"📡 Sende Video-Signal '{clip.Title}' als Live-Kamera!");
        }

        private void CamStopVideoSignal_Click(object sender, RoutedEventArgs e)
        {
            PlaySoundNav();
            _videoSignalAutoStopDeadlineUtc = null;
            _videoSignalAutoStopLastDisplayedSec = -1;
            _camMediaInjector.Stop();
            _camConfig.ActiveVideoSignalPath = "";
            if (CamVideoSignalActiveBar != null) CamVideoSignalActiveBar.Visibility = Visibility.Collapsed;
            RenderVideoSignalDeckList();
            UpdateCameraLiveStatusBadges();
            ShowCameraToast("🎥 Zurück auf echtes Live-Kamerabild geschaltet");
        }

        private void RenderVideoSignalDeckList()
        {
            if (CamVideoSignalListPanel == null) return;
            CamVideoSignalListPanel.Children.Clear();

            if (_camConfig.VideoSignalClips == null || _camConfig.VideoSignalClips.Count == 0)
            {
                CamVideoSignalListPanel.Children.Add(new TextBlock
                {
                    Text = "Noch keine Einspieler-Videos hinterlegt. Klicke auf '➕ Video...', '⏺ 10s Tarn-Clip' oder '🪃 Boomerang'!",
                    Foreground = BrushFromHex("#9CA3AF"),
                    FontSize = 10.5,
                    TextWrapping = TextWrapping.Wrap,
                    Margin = new Thickness(2, 2, 2, 0)
                });
                return;
            }

            bool dark = _isDarkMode;

            foreach (var clip in _camConfig.VideoSignalClips.ToList())
            {
                if (clip.AutoStopDurationSec <= 0) clip.AutoStopDurationSec = 5;
                if (string.IsNullOrWhiteSpace(clip.AutoStopPresetMode))
                    clip.AutoStopPresetMode = clip.AutoStopTimerEnabled ? clip.AutoStopDurationSec.ToString() : "off";

                bool isPlayingThis = _camMediaInjector.IsPlaying &&
                                     string.Equals(_camMediaInjector.ActiveVideoPath, clip.FilePath, StringComparison.OrdinalIgnoreCase);

                var card = new Border
                {
                    Background = isPlayingThis ? DirectHexBrush(dark ? "#064E3B" : "#ECFDF5") : DirectHexBrush(dark ? "#11131A" : "#F8FAFC"),
                    BorderBrush = isPlayingThis ? DirectHexBrush("#10B981") : DirectHexBrush(dark ? "#262A36" : "#CBD5E1"),
                    BorderThickness = new Thickness(1),
                    CornerRadius = new CornerRadius(8),
                    Padding = new Thickness(8, 6, 8, 6),
                    Margin = new Thickness(0, 0, 0, 6)
                };

                var cardStack = new StackPanel();

                // Row 1: Title + Play/Stop Button + Delete Button
                var g = new Grid { Margin = new Thickness(0, 0, 0, 5) };
                g.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
                g.ColumnDefinitions.Add(new ColumnDefinition { Width = GridLength.Auto });
                g.ColumnDefinitions.Add(new ColumnDefinition { Width = GridLength.Auto });

                var titleTxt = new TextBlock
                {
                    Text = (isPlayingThis ? "🟢 " : "🎞 ") + clip.Title,
                    Foreground = isPlayingThis ? DirectHexBrush(dark ? "#ECFDF5" : "#065F46") : DirectHexBrush(dark ? "#E5E7EB" : "#0F172A"),
                    FontSize = 11.5,
                    FontWeight = isPlayingThis ? FontWeights.Bold : FontWeights.SemiBold,
                    VerticalAlignment = VerticalAlignment.Center,
                    TextTrimming = TextTrimming.CharacterEllipsis,
                    Margin = new Thickness(0, 0, 6, 0)
                };
                g.Children.Add(titleTxt);

                string playLabel = isPlayingThis
                    ? "⏹ Stopp"
                    : (clip.AutoStopTimerEnabled ? $"▶ Abspielen ({clip.AutoStopDurationSec}s)" : "▶ Abspielen");

                var playBtn = new Button
                {
                    Content = playLabel,
                    Background = isPlayingThis ? DirectHexBrush("#DC2626") : DirectHexBrush("#23A55A"),
                    Foreground = DirectHexBrush("#FFFFFF"),
                    FontWeight = FontWeights.Bold,
                    FontSize = 10.5,
                    BorderThickness = new Thickness(0),
                    Padding = new Thickness(9, 4, 9, 4),
                    Margin = new Thickness(0, 0, 4, 0),
                    Cursor = Cursors.Hand
                };
                var capturedClip = clip;
                playBtn.Click += (s, ev) =>
                {
                    if (isPlayingThis)
                        CamStopVideoSignal_Click(playBtn, ev);
                    else
                        StartVideoSignalClipPlayback(capturedClip);
                };
                Grid.SetColumn(playBtn, 1);
                g.Children.Add(playBtn);

                var delBtn = new Button
                {
                    Content = "🗑",
                    Background = DirectHexBrush(dark ? "#1E1F22" : "#E2E8F0"),
                    Foreground = DirectHexBrush(dark ? "#9CA3AF" : "#475569"),
                    BorderThickness = new Thickness(0),
                    Padding = new Thickness(6, 4, 6, 4),
                    Cursor = Cursors.Hand,
                    ToolTip = "Aus Einspieler-Liste entfernen"
                };
                delBtn.Click += (s, ev) =>
                {
                    if (isPlayingThis)
                    {
                        _videoSignalAutoStopDeadlineUtc = null;
                        _camMediaInjector.Stop();
                    }
                    _camConfig.VideoSignalClips.Remove(capturedClip);
                    SaveCameraStudioConfig();
                    RenderVideoSignalDeckList();
                };
                Grid.SetColumn(delBtn, 2);
                g.Children.Add(delBtn);

                cardStack.Children.Add(g);

                // Row 2: Per-Video Auto-Stop Timer Selection (Aus | 5s | 10s | 15s | Manuell Sek.)
                var timerRow = new Grid();
                timerRow.ColumnDefinitions.Add(new ColumnDefinition { Width = GridLength.Auto });
                timerRow.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
                timerRow.ColumnDefinitions.Add(new ColumnDefinition { Width = GridLength.Auto });

                var timerLbl = new TextBlock
                {
                    Text = "⏱ Timer:",
                    Foreground = isPlayingThis ? DirectHexBrush(dark ? "#A7F3D0" : "#047857") : DirectHexBrush(dark ? "#9CA3AF" : "#475569"),
                    FontSize = 10,
                    FontWeight = FontWeights.Bold,
                    VerticalAlignment = VerticalAlignment.Center,
                    Margin = new Thickness(0, 0, 5, 0)
                };
                timerRow.Children.Add(timerLbl);

                var btnWrap = new StackPanel { Orientation = Orientation.Horizontal, VerticalAlignment = VerticalAlignment.Center };

                Button MakeTimerChip(string label, string modeKey, int secVal)
                {
                    bool activeChip = modeKey == "off"
                        ? !capturedClip.AutoStopTimerEnabled
                        : (capturedClip.AutoStopTimerEnabled && capturedClip.AutoStopDurationSec == secVal);

                    var chip = new Button
                    {
                        Content = label,
                        Background = activeChip ? DirectHexBrush(dark ? "#26231F" : "#C5A059") : DirectHexBrush(dark ? "#1E1F22" : "#E2E8F0"),
                        Foreground = activeChip ? DirectHexBrush(dark ? "#C5A059" : "#111827") : DirectHexBrush(dark ? "#E5E7EB" : "#1E293B"),
                        BorderBrush = activeChip ? DirectHexBrush("#C5A059") : DirectHexBrush(dark ? "#2B2D31" : "#CBD5E1"),
                        BorderThickness = new Thickness(1),
                        FontSize = 9.5,
                        FontWeight = activeChip ? FontWeights.Bold : FontWeights.SemiBold,
                        Padding = new Thickness(6, 2, 6, 2),
                        Margin = new Thickness(0, 0, 3, 0),
                        Cursor = Cursors.Hand,
                        ToolTip = modeKey == "off"
                            ? "Kein Auto-Stopp Timer für dieses Video"
                            : $"Video stoppt nach {secVal} Sekunden automatisch und zeigt wieder die Live-Kamera"
                    };
                    chip.Click += (s, ev) =>
                    {
                        PlaySoundSmartPtzTick();
                        if (modeKey == "off")
                        {
                            capturedClip.AutoStopTimerEnabled = false;
                            capturedClip.AutoStopPresetMode = "off";
                            if (isPlayingThis)
                            {
                                _videoSignalAutoStopDeadlineUtc = null;
                                UpdateVideoSignalStatusBannerText();
                            }
                        }
                        else
                        {
                            capturedClip.AutoStopTimerEnabled = true;
                            capturedClip.AutoStopDurationSec = secVal;
                            capturedClip.AutoStopPresetMode = modeKey;
                            if (isPlayingThis)
                            {
                                _videoSignalAutoStopDeadlineUtc = DateTime.UtcNow.AddSeconds(secVal);
                                UpdateVideoSignalStatusBannerText();
                            }
                        }
                        SaveCameraStudioConfig();
                        RenderVideoSignalDeckList();
                    };
                    return chip;
                }

                btnWrap.Children.Add(MakeTimerChip("Aus", "off", 0));
                btnWrap.Children.Add(MakeTimerChip("5s", "5", 5));
                btnWrap.Children.Add(MakeTimerChip("10s", "10", 10));
                btnWrap.Children.Add(MakeTimerChip("15s", "15", 15));

                Grid.SetColumn(btnWrap, 1);
                timerRow.Children.Add(btnWrap);

                // Custom duration input right on the video card
                var customSp = new StackPanel { Orientation = Orientation.Horizontal, VerticalAlignment = VerticalAlignment.Center };
                bool isCustomActive = capturedClip.AutoStopTimerEnabled &&
                                      capturedClip.AutoStopDurationSec != 5 &&
                                      capturedClip.AutoStopDurationSec != 10 &&
                                      capturedClip.AutoStopDurationSec != 15;
                var customBox = new TextBox
                {
                    Text = capturedClip.AutoStopDurationSec.ToString(),
                    Width = 34,
                    Background = DirectHexBrush(dark ? "#1E1F22" : "#FFFFFF"),
                    Foreground = DirectHexBrush(dark ? "#F2F3F5" : "#0F172A"),
                    BorderBrush = isCustomActive ? DirectHexBrush("#C5A059") : DirectHexBrush(dark ? "#2B2D31" : "#CBD5E1"),
                    BorderThickness = new Thickness(1),
                    FontSize = 10,
                    FontWeight = FontWeights.Bold,
                    Padding = new Thickness(3, 1, 3, 1),
                    HorizontalContentAlignment = HorizontalAlignment.Center,
                    ToolTip = "Eigene Dauer in Sekunden eingeben (Enter oder Zahl ändern aktiviert den Timer)"
                };
                customBox.LostFocus += (s, ev) =>
                {
                    if (int.TryParse(customBox.Text.Trim(), out int val) && val > 0)
                    {
                        int clamped = Math.Clamp(val, 1, 600);
                        if (capturedClip.AutoStopDurationSec != clamped)
                        {
                            capturedClip.AutoStopDurationSec = clamped;
                            capturedClip.AutoStopTimerEnabled = true;
                            capturedClip.AutoStopPresetMode = "custom";
                            if (isPlayingThis)
                            {
                                _videoSignalAutoStopDeadlineUtc = DateTime.UtcNow.AddSeconds(clamped);
                                UpdateVideoSignalStatusBannerText();
                            }
                            SaveCameraStudioConfig();
                            RenderVideoSignalDeckList();
                        }
                    }
                };
                customBox.KeyDown += (s, ev) =>
                {
                    if (ev.Key == Key.Enter && int.TryParse(customBox.Text.Trim(), out int val) && val > 0)
                    {
                        int clamped = Math.Clamp(val, 1, 600);
                        capturedClip.AutoStopDurationSec = clamped;
                        capturedClip.AutoStopTimerEnabled = true;
                        capturedClip.AutoStopPresetMode = "custom";
                        if (isPlayingThis)
                        {
                            _videoSignalAutoStopDeadlineUtc = DateTime.UtcNow.AddSeconds(clamped);
                            UpdateVideoSignalStatusBannerText();
                        }
                        SaveCameraStudioConfig();
                        RenderVideoSignalDeckList();
                        ShowCameraToast($"⏱ Video-Timer für '{capturedClip.Title}' auf {clamped}s gesetzt");
                    }
                };
                customSp.Children.Add(customBox);
                customSp.Children.Add(new TextBlock
                {
                    Text = "s",
                    Foreground = isPlayingThis ? DirectHexBrush(dark ? "#A7F3D0" : "#047857") : DirectHexBrush(dark ? "#9CA3AF" : "#475569"),
                    FontSize = 10,
                    FontWeight = FontWeights.Bold,
                    VerticalAlignment = VerticalAlignment.Center,
                    Margin = new Thickness(2, 0, 0, 0)
                });
                Grid.SetColumn(customSp, 2);
                timerRow.Children.Add(customSp);

                cardStack.Children.Add(timerRow);
                card.Child = cardStack;
                CamVideoSignalListPanel.Children.Add(card);
            }
        }

        // =========================================================================
        // DYNAMIC MUSIC SIGNAL PLAYER & STUDIO BACKGROUND SOUND (Under Video-Einspieler)
        // =========================================================================
        private void PopulateDefaultSfMusicSignalClips(bool silent = false)
        {
            try
            {
                _camConfig.MusicSignalClips ??= new List<MusicSignalClipItem>();
                int added = 0;
                var candidateDirs = new List<string>();
                string mktAssets = System.IO.Path.Combine(AppDomain.CurrentDomain.BaseDirectory, "Assets", "MarketingMusic");
                if (Directory.Exists(mktAssets)) candidateDirs.Add(mktAssets);
                string siblingAssets = System.IO.Path.Combine(CollectionModuleRegistry.ResolveModuleDirectory("Funkenwerk Marketing"), "Assets", "MarketingMusic");
                if (Directory.Exists(siblingAssets)) candidateDirs.Add(siblingAssets);
                string musicCache = System.IO.Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData), "Funkenwerk", "MusicCache");
                if (Directory.Exists(musicCache)) candidateDirs.Add(musicCache);

                foreach (var dir in candidateDirs)
                {
                    foreach (var file in Directory.GetFiles(dir).Where(f => f.EndsWith(".mp3", StringComparison.OrdinalIgnoreCase) || f.EndsWith(".m4a", StringComparison.OrdinalIgnoreCase) || f.EndsWith(".wav", StringComparison.OrdinalIgnoreCase)).Take(25))
                    {
                        if (!_camConfig.MusicSignalClips.Any(c => string.Equals(c.FilePath, file, StringComparison.OrdinalIgnoreCase)))
                        {
                            _camConfig.MusicSignalClips.Add(new MusicSignalClipItem
                            {
                                Title = System.IO.Path.GetFileNameWithoutExtension(file),
                                FilePath = file,
                                CategoryBadge = "Studio"
                            });
                            added++;
                        }
                    }
                }
                if (!silent && added > 0)
                {
                    SaveCameraStudioConfig();
                    RenderMusicSignalDeckList();
                    ShowCameraToast($"🎵 {added} Seelenfunke Studio-Songs in den Musik-Einspieler geladen!");
                }
            }
            catch { }
        }

        private void CamMusicLoopCheck_Changed(object sender, RoutedEventArgs e)
        {
            if (_suppressCameraUiEvents || !_isCameraStudioInitialized) return;
            PlaySoundSmartPtzTick();
            _camConfig.MusicSignalLoop = CamMusicLoopCheck?.IsChecked == true;
            SaveCameraStudioConfig();
            UpdateMusicSignalStatusBannerText();
        }

        private void CamMusicSignalVolumeSlider_ValueChanged(object sender, RoutedPropertyChangedEventArgs<double> e)
        {
            if (_suppressCameraUiEvents || !_isCameraStudioInitialized) return;
            int vol = Math.Clamp((int)Math.Round(e.NewValue), 0, 100);
            _camConfig.MusicSignalVolume = vol;
            if (CamMusicSignalVolumeTxt != null)
                CamMusicSignalVolumeTxt.Text = $"{vol} %";
            try
            {
                _camMusicSignalPlayer.Volume = _camConfig.SoundMuted ? 0.0 : Math.Clamp(vol / 100.0, 0.0, 1.0);
            }
            catch { }
            SaveCameraStudioConfig();
        }

        private void CamAddMusicSignal_Click(object sender, RoutedEventArgs e)
        {
            var dlg = new Microsoft.Win32.OpenFileDialog
            {
                Title = "Musik oder Sound für Musik-Einspieler hinzufügen",
                Filter = "Audiodateien (*.mp3;*.m4a;*.wav;*.flac;*.ogg;*.wma)|*.mp3;*.m4a;*.wav;*.flac;*.ogg;*.wma",
                Multiselect = true
            };

            if (dlg.ShowDialog() == true && dlg.FileNames.Length > 0)
            {
                PlaySoundActionPop();
                _camConfig.MusicSignalClips ??= new List<MusicSignalClipItem>();
                foreach (var f in dlg.FileNames)
                {
                    if (!_camConfig.MusicSignalClips.Any(c => string.Equals(c.FilePath, f, StringComparison.OrdinalIgnoreCase)))
                    {
                        _camConfig.MusicSignalClips.Insert(0, new MusicSignalClipItem
                        {
                            Title = System.IO.Path.GetFileNameWithoutExtension(f),
                            FilePath = f,
                            CategoryBadge = "Eigene Datei"
                        });
                    }
                }
                SaveCameraStudioConfig();
                RenderMusicSignalDeckList();
                ShowCameraToast($"🎵 {dlg.FileNames.Length} Musikdatei(en) zum Einspieler hinzugefügt!");
            }
        }

        private void CamLoadSfMusicCatalog_Click(object sender, RoutedEventArgs e)
        {
            PlaySoundActionPop();
            int before = _camConfig.MusicSignalClips?.Count ?? 0;
            PopulateDefaultSfMusicSignalClips(silent: true);
            SaveCameraStudioConfig();
            RenderMusicSignalDeckList();
            int after = _camConfig.MusicSignalClips?.Count ?? 0;
            ShowCameraToast(after > before
                ? $"✨ {after - before} Seelenfunke Songs zum Musik-Einspieler hinzugefügt!"
                : "✨ Alle 12 Seelenfunke Studio-Songs sind bereits im Musik-Einspieler bereit!");
        }

        private void StartMusicSignalClipPlayback(MusicSignalClipItem clip)
        {
            if (!File.Exists(clip.FilePath))
            {
                _camConfig.MusicSignalClips?.Remove(clip);
                SaveCameraStudioConfig();
                RenderMusicSignalDeckList();
                return;
            }

            PlaySoundActionPop();
            _camConfig.ActiveMusicSignalPath = clip.FilePath;

            if (clip.AutoStopTimerEnabled && clip.AutoStopDurationSec > 0)
            {
                int sec = Math.Clamp(clip.AutoStopDurationSec, 1, 600);
                _musicSignalAutoStopDeadlineUtc = DateTime.UtcNow.AddSeconds(sec);
                _musicSignalAutoStopLastDisplayedSec = sec;
            }
            else
            {
                _musicSignalAutoStopDeadlineUtc = null;
                _musicSignalAutoStopLastDisplayedSec = -1;
            }

            try
            {
                _camMusicSignalPlayer.Stop();
                _camMusicSignalPlayer.Open(new Uri(clip.FilePath, UriKind.Absolute));
                _camMusicSignalPlayer.Volume = _camConfig.SoundMuted
                    ? 0.0
                    : Math.Clamp(_camConfig.MusicSignalVolume / 100.0, 0.0, 1.0);
                _camMusicSignalPlayer.Play();
                _isCamMusicSignalPlaying = true;
            }
            catch
            {
                _isCamMusicSignalPlaying = false;
            }

            if (CamMusicSignalActiveBar != null && CamMusicSignalStatusTxt != null)
            {
                CamMusicSignalActiveBar.Visibility = _isCamMusicSignalPlaying ? Visibility.Visible : Visibility.Collapsed;
                UpdateMusicSignalStatusBannerText();
            }

            RenderMusicSignalDeckList();
            UpdateCameraLiveStatusBadges();
            ShowCameraToast(clip.AutoStopTimerEnabled
                ? $"🎵 Spiele '{clip.Title}' für {clip.AutoStopDurationSec}s ein!"
                : $"🎵 Musik-Einspieler aktiv: '{clip.Title}'");
        }

        private void CamStopMusicSignal_Click(object sender, RoutedEventArgs e)
        {
            PlaySoundNav();
            StopMusicSignalPlayback(showToast: true);
        }

        private void StopMusicSignalPlayback(bool showToast)
        {
            _musicSignalAutoStopDeadlineUtc = null;
            _musicSignalAutoStopLastDisplayedSec = -1;
            _isCamMusicSignalPlaying = false;
            _camConfig.ActiveMusicSignalPath = "";
            try { _camMusicSignalPlayer.Stop(); } catch { }
            if (CamMusicSignalActiveBar != null) CamMusicSignalActiveBar.Visibility = Visibility.Collapsed;
            RenderMusicSignalDeckList();
            UpdateCameraLiveStatusBadges();
            if (showToast)
                ShowCameraToast("⏹ Musik-Einspieler gestoppt");
        }

        private void UpdateMusicSignalStatusBannerText()
        {
            if (!_isCamMusicSignalPlaying || CamMusicSignalStatusTxt == null) return;
            var activeClip = _camConfig.MusicSignalClips?.FirstOrDefault(c =>
                string.Equals(c.FilePath, _camConfig.ActiveMusicSignalPath, StringComparison.OrdinalIgnoreCase));
            string title = activeClip?.Title ?? System.IO.Path.GetFileNameWithoutExtension(_camConfig.ActiveMusicSignalPath);
            string modeDesc = _camConfig.MusicSignalLoop ? "Dauerschleife" : "1x Abspielen";
            if (_musicSignalAutoStopDeadlineUtc.HasValue)
            {
                int rem = Math.Max(1, (int)Math.Ceiling((_musicSignalAutoStopDeadlineUtc.Value - DateTime.UtcNow).TotalSeconds));
                modeDesc = $"⏱ Stopp in {rem}s • " + modeDesc;
            }
            CamMusicSignalStatusTxt.Text = $"▶ Spielt: {title} ({modeDesc})";
        }

        private void RenderMusicSignalDeckList()
        {
            if (CamMusicSignalListPanel == null) return;
            CamMusicSignalListPanel.Children.Clear();

            if (_camConfig.MusicSignalClips == null || _camConfig.MusicSignalClips.Count == 0)
            {
                CamMusicSignalListPanel.Children.Add(new TextBlock
                {
                    Text = "Keine Songs hinterlegt. Klicke auf '➕ Musik...' oder '✨ SF-Album laden'!",
                    Foreground = BrushFromHex("#9CA3AF"),
                    FontSize = 10.5,
                    TextWrapping = TextWrapping.Wrap,
                    Margin = new Thickness(2, 2, 2, 0)
                });
                return;
            }

            bool dark = _isDarkMode;

            foreach (var clip in _camConfig.MusicSignalClips.ToList())
            {
                if (clip.AutoStopDurationSec <= 0) clip.AutoStopDurationSec = 10;
                if (string.IsNullOrWhiteSpace(clip.AutoStopPresetMode))
                    clip.AutoStopPresetMode = clip.AutoStopTimerEnabled ? clip.AutoStopDurationSec.ToString() : "off";

                bool isPlayingThis = _isCamMusicSignalPlaying &&
                                     string.Equals(_camConfig.ActiveMusicSignalPath, clip.FilePath, StringComparison.OrdinalIgnoreCase);

                var card = new Border
                {
                    Background = isPlayingThis ? DirectHexBrush(dark ? "#064E3B" : "#ECFDF5") : DirectHexBrush(dark ? "#11131A" : "#F8FAFC"),
                    BorderBrush = isPlayingThis ? DirectHexBrush("#10B981") : DirectHexBrush(dark ? "#262A36" : "#CBD5E1"),
                    BorderThickness = new Thickness(1),
                    CornerRadius = new CornerRadius(8),
                    Padding = new Thickness(8, 6, 8, 6),
                    Margin = new Thickness(0, 0, 0, 6)
                };

                var cardStack = new StackPanel();

                // Row 1: Title + Play/Stop Button + Delete Button
                var g = new Grid { Margin = new Thickness(0, 0, 0, 5) };
                g.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
                g.ColumnDefinitions.Add(new ColumnDefinition { Width = GridLength.Auto });
                g.ColumnDefinitions.Add(new ColumnDefinition { Width = GridLength.Auto });

                var titleTxt = new TextBlock
                {
                    Text = (isPlayingThis ? "🟢 " : "🎵 ") + clip.Title,
                    Foreground = isPlayingThis ? DirectHexBrush(dark ? "#ECFDF5" : "#065F46") : DirectHexBrush(dark ? "#F9FAFB" : "#0F172A"),
                    FontSize = 11.5,
                    FontWeight = isPlayingThis ? FontWeights.Bold : FontWeights.SemiBold,
                    VerticalAlignment = VerticalAlignment.Center,
                    TextTrimming = TextTrimming.CharacterEllipsis,
                    Margin = new Thickness(0, 0, 6, 0),
                    ToolTip = clip.Title
                };
                g.Children.Add(titleTxt);

                string playLabel = isPlayingThis
                    ? "⏹ Stopp"
                    : (clip.AutoStopTimerEnabled ? $"▶ Einspielen ({clip.AutoStopDurationSec}s)" : "▶ Einspielen");

                var playBtn = new Button
                {
                    Content = playLabel,
                    Background = isPlayingThis ? DirectHexBrush("#DC2626") : DirectHexBrush("#C5A059"),
                    Foreground = isPlayingThis ? DirectHexBrush("#FFFFFF") : DirectHexBrush("#111827"),
                    BorderBrush = isPlayingThis ? DirectHexBrush("#DC2626") : DirectHexBrush("#C5A059"),
                    BorderThickness = new Thickness(1),
                    FontWeight = FontWeights.Bold,
                    FontSize = 10.5,
                    Padding = new Thickness(9, 4, 9, 4),
                    Margin = new Thickness(0, 0, 4, 0),
                    Cursor = Cursors.Hand
                };
                var capturedClip = clip;
                playBtn.Click += (s, ev) =>
                {
                    if (isPlayingThis)
                        CamStopMusicSignal_Click(playBtn, ev);
                    else
                        StartMusicSignalClipPlayback(capturedClip);
                };
                Grid.SetColumn(playBtn, 1);
                g.Children.Add(playBtn);

                var delBtn = new Button
                {
                    Content = "🗑",
                    Background = DirectHexBrush(dark ? "#1E1F22" : "#E2E8F0"),
                    Foreground = DirectHexBrush(dark ? "#9CA3AF" : "#475569"),
                    BorderThickness = new Thickness(0),
                    Padding = new Thickness(6, 4, 6, 4),
                    Cursor = Cursors.Hand,
                    ToolTip = "Aus Musik-Einspieler entfernen"
                };
                delBtn.Click += (s, ev) =>
                {
                    if (isPlayingThis)
                        StopMusicSignalPlayback(showToast: false);
                    _camConfig.MusicSignalClips.Remove(capturedClip);
                    SaveCameraStudioConfig();
                    RenderMusicSignalDeckList();
                };
                Grid.SetColumn(delBtn, 2);
                g.Children.Add(delBtn);

                cardStack.Children.Add(g);

                // Row 2: Per-Music Auto-Stop Timer Selection (Aus | 5s | 10s | 15s | Manuell Sek.)
                var timerRow = new Grid();
                timerRow.ColumnDefinitions.Add(new ColumnDefinition { Width = GridLength.Auto });
                timerRow.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
                timerRow.ColumnDefinitions.Add(new ColumnDefinition { Width = GridLength.Auto });

                var timerLbl = new TextBlock
                {
                    Text = "⏱ Timer:",
                    Foreground = isPlayingThis ? DirectHexBrush(dark ? "#A7F3D0" : "#047857") : DirectHexBrush(dark ? "#9CA3AF" : "#475569"),
                    FontSize = 10,
                    FontWeight = FontWeights.Bold,
                    VerticalAlignment = VerticalAlignment.Center,
                    Margin = new Thickness(0, 0, 5, 0)
                };
                timerRow.Children.Add(timerLbl);

                var btnWrap = new StackPanel { Orientation = Orientation.Horizontal, VerticalAlignment = VerticalAlignment.Center };

                Button MakeMusicTimerChip(string label, string modeKey, int secVal)
                {
                    bool activeChip = modeKey == "off"
                        ? !capturedClip.AutoStopTimerEnabled
                        : (capturedClip.AutoStopTimerEnabled && capturedClip.AutoStopDurationSec == secVal);

                    var chip = new Button
                    {
                        Content = label,
                        Background = activeChip ? DirectHexBrush(dark ? "#26231F" : "#C5A059") : DirectHexBrush(dark ? "#1E1F22" : "#E2E8F0"),
                        Foreground = activeChip ? DirectHexBrush(dark ? "#C5A059" : "#111827") : DirectHexBrush(dark ? "#E5E7EB" : "#1E293B"),
                        BorderBrush = activeChip ? DirectHexBrush("#C5A059") : DirectHexBrush(dark ? "#2B2D31" : "#CBD5E1"),
                        BorderThickness = new Thickness(1),
                        FontSize = 9.5,
                        FontWeight = activeChip ? FontWeights.Bold : FontWeights.SemiBold,
                        Padding = new Thickness(6, 2, 6, 2),
                        Margin = new Thickness(0, 0, 3, 0),
                        Cursor = Cursors.Hand,
                        ToolTip = modeKey == "off"
                            ? "Kein Auto-Stopp Timer für diesen Song"
                            : $"Song stoppt nach {secVal} Sekunden automatisch"
                    };
                    chip.Click += (s, ev) =>
                    {
                        PlaySoundSmartPtzTick();
                        if (modeKey == "off")
                        {
                            capturedClip.AutoStopTimerEnabled = false;
                            capturedClip.AutoStopPresetMode = "off";
                            if (isPlayingThis)
                            {
                                _musicSignalAutoStopDeadlineUtc = null;
                                UpdateMusicSignalStatusBannerText();
                            }
                        }
                        else
                        {
                            capturedClip.AutoStopTimerEnabled = true;
                            capturedClip.AutoStopDurationSec = secVal;
                            capturedClip.AutoStopPresetMode = modeKey;
                            if (isPlayingThis)
                            {
                                _musicSignalAutoStopDeadlineUtc = DateTime.UtcNow.AddSeconds(secVal);
                                UpdateMusicSignalStatusBannerText();
                            }
                        }
                        SaveCameraStudioConfig();
                        RenderMusicSignalDeckList();
                    };
                    return chip;
                }

                btnWrap.Children.Add(MakeMusicTimerChip("Aus", "off", 0));
                btnWrap.Children.Add(MakeMusicTimerChip("5s", "5", 5));
                btnWrap.Children.Add(MakeMusicTimerChip("10s", "10", 10));
                btnWrap.Children.Add(MakeMusicTimerChip("15s", "15", 15));

                Grid.SetColumn(btnWrap, 1);
                timerRow.Children.Add(btnWrap);

                var customSp = new StackPanel { Orientation = Orientation.Horizontal, VerticalAlignment = VerticalAlignment.Center };
                bool isCustomActive = capturedClip.AutoStopTimerEnabled &&
                                      capturedClip.AutoStopDurationSec != 5 &&
                                      capturedClip.AutoStopDurationSec != 10 &&
                                      capturedClip.AutoStopDurationSec != 15;
                var customBox = new TextBox
                {
                    Text = capturedClip.AutoStopDurationSec.ToString(),
                    Width = 34,
                    Background = DirectHexBrush(dark ? "#1E1F22" : "#FFFFFF"),
                    Foreground = DirectHexBrush(dark ? "#F9FAFB" : "#0F172A"),
                    BorderBrush = isCustomActive ? DirectHexBrush("#C5A059") : DirectHexBrush(dark ? "#2B2D31" : "#CBD5E1"),
                    BorderThickness = new Thickness(1),
                    FontSize = 10,
                    FontWeight = FontWeights.Bold,
                    Padding = new Thickness(3, 1, 3, 1),
                    HorizontalContentAlignment = HorizontalAlignment.Center,
                    ToolTip = "Eigene Dauer in Sekunden eingeben"
                };
                customBox.LostFocus += (s, ev) =>
                {
                    if (int.TryParse(customBox.Text.Trim(), out int val) && val > 0)
                    {
                        int clamped = Math.Clamp(val, 1, 600);
                        if (capturedClip.AutoStopDurationSec != clamped)
                        {
                            capturedClip.AutoStopDurationSec = clamped;
                            capturedClip.AutoStopTimerEnabled = true;
                            capturedClip.AutoStopPresetMode = "custom";
                            if (isPlayingThis)
                            {
                                _musicSignalAutoStopDeadlineUtc = DateTime.UtcNow.AddSeconds(clamped);
                                UpdateMusicSignalStatusBannerText();
                            }
                            SaveCameraStudioConfig();
                            RenderMusicSignalDeckList();
                        }
                    }
                };
                customBox.KeyDown += (s, ev) =>
                {
                    if (ev.Key == Key.Enter && int.TryParse(customBox.Text.Trim(), out int val) && val > 0)
                    {
                        int clamped = Math.Clamp(val, 1, 600);
                        capturedClip.AutoStopDurationSec = clamped;
                        capturedClip.AutoStopTimerEnabled = true;
                        capturedClip.AutoStopPresetMode = "custom";
                        if (isPlayingThis)
                        {
                            _musicSignalAutoStopDeadlineUtc = DateTime.UtcNow.AddSeconds(clamped);
                            UpdateMusicSignalStatusBannerText();
                        }
                        SaveCameraStudioConfig();
                        RenderMusicSignalDeckList();
                        ShowCameraToast($"⏱ Musik-Timer für '{capturedClip.Title}' auf {clamped}s gesetzt");
                    }
                };
                customSp.Children.Add(customBox);
                customSp.Children.Add(new TextBlock
                {
                    Text = "s",
                    Foreground = isPlayingThis ? DirectHexBrush(dark ? "#A7F3D0" : "#047857") : DirectHexBrush(dark ? "#9CA3AF" : "#475569"),
                    FontSize = 10,
                    FontWeight = FontWeights.Bold,
                    VerticalAlignment = VerticalAlignment.Center,
                    Margin = new Thickness(2, 0, 0, 0)
                });
                Grid.SetColumn(customSp, 2);
                timerRow.Children.Add(customSp);

                cardStack.Children.Add(timerRow);
                card.Child = cardStack;
                CamMusicSignalListPanel.Children.Add(card);
            }
        }

        // =========================================================================
        // TIKTOK & BEAUTY FACE FILTERS + 60FPS FLUID SHADERS (Requirement 9+)
        // =========================================================================
        private void CamBeautyFilter_Click(object sender, RoutedEventArgs e)
        {
            if (sender is Button btn && btn.Tag is string filter)
            {
                PlaySoundNav();
                _camConfig.BeautyFilter = filter;
                _suppressCameraUiEvents = true;
                if (filter == "none")
                {
                    _camConfig.BeautySkinSmooth = 0;
                    _camConfig.BeautyFaceGlow = 0;
                }
                else
                {
                    if (_camConfig.BeautySkinSmooth < 25) _camConfig.BeautySkinSmooth = 68;
                    if (_camConfig.BeautyFaceGlow < 15) _camConfig.BeautyFaceGlow = 48;
                }
                if (CamBeautySkinSlider != null) CamBeautySkinSlider.Value = _camConfig.BeautySkinSmooth;
                if (CamBeautySkinTxt != null) CamBeautySkinTxt.Text = $"{_camConfig.BeautySkinSmooth} %";
                if (CamBeautyGlowSlider != null) CamBeautyGlowSlider.Value = _camConfig.BeautyFaceGlow;
                if (CamBeautyGlowTxt != null) CamBeautyGlowTxt.Text = $"{_camConfig.BeautyFaceGlow} %";
                _suppressCameraUiEvents = false;

                SaveCameraStudioConfig();
                UpdateBeautyFilterButtonsUI();
                UpdateCameraLiveStatusBadges();
                ShowCameraToast(filter == "none"
                    ? "🙂 TikTok Beauty-Filter deaktiviert"
                    : $"✨ TikTok Beauty-Filter aktiv: {btn.Content}");
            }
        }

        private void StyleStandardInspectorFilterButton(Button b, bool active)
        {
            b.Background = DirectHexBrush(active ? (_isDarkMode ? "#26231F" : "#C5A059") : (_isDarkMode ? "#13161F" : "#F1F5F9"));
            b.Foreground = DirectHexBrush(active ? (_isDarkMode ? "#C5A059" : "#111827") : (_isDarkMode ? "#D1D5DB" : "#1F2937"));
            b.BorderBrush = DirectHexBrush(active ? "#C5A059" : (_isDarkMode ? "#2E3446" : "#CBD5E1"));
            b.BorderThickness = new Thickness(1);
            b.FontWeight = active ? FontWeights.Bold : FontWeights.SemiBold;
        }

        private void UpdateBeautyFilterButtonsUI()
        {
            if (CamBeautyFilterGrid == null) return;
            foreach (var child in CamBeautyFilterGrid.Children)
            {
                if (child is Button b && b.Tag is string tag)
                {
                    StyleStandardInspectorFilterButton(b, string.Equals(_camConfig.BeautyFilter, tag, StringComparison.OrdinalIgnoreCase));
                }
            }
        }

        private void CamBeautySlider_ValueChanged(object sender, RoutedPropertyChangedEventArgs<double> e)
        {
            if (_suppressCameraUiEvents || !_isCameraStudioInitialized) return;
            if (CamBeautySkinSlider != null) _camConfig.BeautySkinSmooth = (int)Math.Round(CamBeautySkinSlider.Value);
            if (CamBeautyGlowSlider != null) _camConfig.BeautyFaceGlow = (int)Math.Round(CamBeautyGlowSlider.Value);
            if (CamBeautySkinTxt != null) CamBeautySkinTxt.Text = $"{_camConfig.BeautySkinSmooth} %";
            if (CamBeautyGlowTxt != null) CamBeautyGlowTxt.Text = $"{_camConfig.BeautyFaceGlow} %";

            if (_camConfig.BeautyFilter == "none" && (_camConfig.BeautySkinSmooth > 0 || _camConfig.BeautyFaceGlow > 0))
            {
                _camConfig.BeautyFilter = "tiktok_glow";
                UpdateBeautyFilterButtonsUI();
                UpdateCameraLiveStatusBadges();
            }
        }

        // 9. EFFEKTE: SPEZIALEFFEKTE (SPIN, FLIP, SHAKE, GLITCH, CONFETTI, PAPARAZZI), 60FPS SHADERS, FARBFILTER & VERZERRFILTER
        private void CamTriggerSpin_Click(object sender, RoutedEventArgs e)
        {
            PlaySoundActionPop();
            _camFrameProcessor.Trigger360Spin();
            ShowCameraToast("🔄 360° Barrel-Roll Spin!");
        }

        private void CamTriggerFlipSpin_Click(object sender, RoutedEventArgs e)
        {
            PlaySoundActionPop();
            _camFrameProcessor.Trigger3DFlipSpin();
            ShowCameraToast("🪙 3D Münzwurf-Flip!");
        }

        private void CamTriggerEarthquake_Click(object sender, RoutedEventArgs e)
        {
            PlaySoundActionPop();
            _camFrameProcessor.TriggerEarthquake();
            ShowCameraToast("🌋 Erdbeben-Shake ausgelöst!");
        }

        private void CamTriggerGlitch_Click(object sender, RoutedEventArgs e)
        {
            PlaySoundActionPop();
            _camFrameProcessor.TriggerGlitchBurst();
            ShowCameraToast("⚡ Cyber-Glitch aktiv!");
        }

        private void CamTriggerConfetti_Click(object sender, RoutedEventArgs e)
        {
            PlaySoundTaskDone();
            _camFrameProcessor.TriggerConfetti(_camConfig.Width, _camConfig.Height);
            ShowCameraToast("🎉 Konfetti-Kanone abgefeuert!");
        }

        private void CamTriggerPaparazzi_Click(object sender, RoutedEventArgs e)
        {
            FunkenwerkSoundEngine.PlayCameraShutter(0.75);
            _camFrameProcessor.TriggerPaparazzi();
            ShowCameraToast("📸 Paparazzi-Blitzgewitter!");
        }

        private void CamSpecialFxCheck_Changed(object sender, RoutedEventArgs e)
        {
            if (_suppressCameraUiEvents || !_isCameraStudioInitialized) return;
            PlaySoundSmartPtzTick();
            if (CamFxNeonPulseCheck != null) _camConfig.FxNeonEdgePulse = CamFxNeonPulseCheck.IsChecked == true;
            if (CamFxRgbWaveCheck != null) _camConfig.FxRgbChromaticWave = CamFxRgbWaveCheck.IsChecked == true;
            if (CamFxHoloHudCheck != null) _camConfig.FxHologramHud = CamFxHoloHudCheck.IsChecked == true;
            if (CamFxFireAuraCheck != null) _camConfig.FxFireAura = CamFxFireAuraCheck.IsChecked == true;
            _camConfig.FxContinuousSpin = CamFxContinuousSpinCheck.IsChecked == true;
            _camConfig.FxDiscoParty = CamFxDiscoCheck.IsChecked == true;
            _camConfig.FxVhsRetro = CamFxVhsCheck.IsChecked == true;
            _camConfig.FxMatrixRain = CamFxMatrixRainCheck.IsChecked == true;
            _camConfig.FxDealWithIt = CamFxDealWithItCheck.IsChecked == true;
            _camConfig.FxCinemaBars = CamFxCinemaBarsCheck.IsChecked == true;
            SaveCameraStudioConfig();
            UpdateCameraLiveStatusBadges();
        }

        private void CamColorFilter_Click(object sender, RoutedEventArgs e)
        {
            if (sender is Button btn && btn.Tag is string filter)
            {
                PlaySoundNav();
                _camConfig.ColorFilter = filter;
                SaveCameraStudioConfig();
                UpdateColorFilterButtonsUI();
                UpdateCameraLiveStatusBadges();
            }
        }

        private void UpdateColorFilterButtonsUI()
        {
            if (CamColorFilterGrid == null) return;
            foreach (var child in CamColorFilterGrid.Children)
            {
                if (child is Button b && b.Tag is string tag)
                {
                    StyleStandardInspectorFilterButton(b, string.Equals(_camConfig.ColorFilter, tag, StringComparison.OrdinalIgnoreCase));
                }
            }
        }

        private void CamDistortionFilter_Click(object sender, RoutedEventArgs e)
        {
            if (sender is Button btn && btn.Tag is string dist)
            {
                PlaySoundNav();
                _camConfig.DistortionFilter = dist;
                SaveCameraStudioConfig();
                UpdateDistortionFilterButtonsUI();
                UpdateCameraLiveStatusBadges();
            }
        }

        private void UpdateDistortionFilterButtonsUI()
        {
            if (CamDistortionFilterGrid == null) return;
            foreach (var child in CamDistortionFilterGrid.Children)
            {
                if (child is Button b && b.Tag is string tag)
                {
                    StyleStandardInspectorFilterButton(b, string.Equals(_camConfig.DistortionFilter, tag, StringComparison.OrdinalIgnoreCase));
                }
            }
        }

        private void CamDistortionIntensity_ValueChanged(object sender, RoutedPropertyChangedEventArgs<double> e)
        {
            if (_suppressCameraUiEvents || !_isCameraStudioInitialized || CamDistortionIntensityTxt == null) return;
            _camConfig.DistortionIntensity = (int)Math.Round(e.NewValue);
            CamDistortionIntensityTxt.Text = $"{_camConfig.DistortionIntensity} %";
        }

        private void CamResetAllEffects_Click(object sender, RoutedEventArgs e)
        {
            PlaySoundActionPop();
            _suppressCameraUiEvents = true;
            _camConfig.BeautyFilter = "none";
            _camConfig.BeautySkinSmooth = 0;
            _camConfig.BeautyFaceGlow = 0;
            _camConfig.ColorFilter = "none";
            _camConfig.DistortionFilter = "none";
            _camConfig.FxNeonEdgePulse = false;
            _camConfig.FxRgbChromaticWave = false;
            _camConfig.FxHologramHud = false;
            _camConfig.FxFireAura = false;
            _camConfig.FxContinuousSpin = false;
            _camConfig.FxDiscoParty = false;
            _camConfig.FxVhsRetro = false;
            _camConfig.FxMatrixRain = false;
            _camConfig.FxDealWithIt = false;
            _camConfig.FxCinemaBars = false;

            if (CamBeautySkinSlider != null) CamBeautySkinSlider.Value = 0;
            if (CamBeautySkinTxt != null) CamBeautySkinTxt.Text = "0 %";
            if (CamBeautyGlowSlider != null) CamBeautyGlowSlider.Value = 0;
            if (CamBeautyGlowTxt != null) CamBeautyGlowTxt.Text = "0 %";
            if (CamFxNeonPulseCheck != null) CamFxNeonPulseCheck.IsChecked = false;
            if (CamFxRgbWaveCheck != null) CamFxRgbWaveCheck.IsChecked = false;
            if (CamFxHoloHudCheck != null) CamFxHoloHudCheck.IsChecked = false;
            if (CamFxFireAuraCheck != null) CamFxFireAuraCheck.IsChecked = false;
            CamFxContinuousSpinCheck.IsChecked = false;
            CamFxDiscoCheck.IsChecked = false;
            CamFxVhsCheck.IsChecked = false;
            CamFxMatrixRainCheck.IsChecked = false;
            CamFxDealWithItCheck.IsChecked = false;
            CamFxCinemaBarsCheck.IsChecked = false;
            UpdateBeautyFilterButtonsUI();
            UpdateColorFilterButtonsUI();
            UpdateDistortionFilterButtonsUI();
            _suppressCameraUiEvents = false;

            SaveCameraStudioConfig();
            UpdateCameraLiveStatusBadges();
            ShowCameraToast("✨ Alle Beauty-, Farb-, Verzerr- & 60FPS-Shader-Effekte deaktiviert");
        }

        // =========================================================================
        // NEW TAB 3: DEEP FAKE KI STUDIO (Integrates C:\Users\konta\Documents\Deep-Live-Cam)
        // =========================================================================
        private void CamDeepFakeEnableCheck_Changed(object sender, RoutedEventArgs e)
        {
            if (_suppressCameraUiEvents || !_isCameraStudioInitialized) return;
            PlaySoundActionPop();
            _camConfig.DeepFakeEnabled = CamDeepFakeEnableCheck.IsChecked == true;
            SaveCameraStudioConfig();
            UpdateCameraLiveStatusBadges();

            if (_camConfig.DeepFakeEnabled)
            {
                _camDeepFakeEngine.EnsureStarted(_camConfig.DeepFakeSourceFacePath);
                if (CamDeepFakeStatusTxt != null)
                {
                    CamDeepFakeStatusTxt.Text = _camDeepFakeEngine.IsReady
                        ? _camDeepFakeEngine.StatusText
                        : "⏳ Lade InsightFace & DirectML GPU-Modell...";
                }
                ShowCameraToast(_camDeepFakeEngine.IsReady
                    ? "🎭 Deep-Live-Cam KI sofort wieder aktiv!"
                    : "🎭 Deep-Live-Cam KI aktiviert! Initialisiere Face-Swapper...");
            }
            else
            {
                // Pause overlay rendering while keeping Python/DirectML warm in the background for 0ms instant re-enable
                _camDeepFakeEngine.Pause();
                if (CamDeepFakeStatusTxt != null)
                    CamDeepFakeStatusTxt.Text = "⏸ Deep-Live-Cam KI bereit im Standby (Haken setzen für sofortigen Start)";
                ShowCameraToast("🎭 Deep Fake Live-KI pausiert (Modell bleibt warm geladen)");
            }
        }

        private void CamDeepFakeBrowseFace_Click(object sender, RoutedEventArgs e)
        {
            var dlg = new Microsoft.Win32.OpenFileDialog
            {
                Title = "Quell-Gesicht für Deep-Live-Cam Face-Swap wählen",
                Filter = "Porträt-Bilder (*.jpg;*.jpeg;*.png;*.webp;*.bmp)|*.jpg;*.jpeg;*.png;*.webp;*.bmp"
            };
            if (dlg.ShowDialog() == true && File.Exists(dlg.FileName))
            {
                PlaySoundActionPop();
                _camConfig.DeepFakeSourceFacePath = dlg.FileName;
                _camConfig.DeepFakeSwapActive = true;
                _camConfig.DeepFakeEnabled = true;
                if (_camConfig.DeepFakeArMode is "anime_eyes" or "alien_morph" or "pixel_anon")
                {
                    _camConfig.DeepFakeArMode = "none";
                    UpdateDeepFakeArButtonsUI();
                }

                _suppressCameraUiEvents = true;
                if (CamDeepFakeEnableCheck != null) CamDeepFakeEnableCheck.IsChecked = true;
                _suppressCameraUiEvents = false;

                _camDeepFakeEngine.UpdateSourceFace(dlg.FileName);
                SaveCameraStudioConfig();
                UpdateDeepFakeActiveFaceHeaderUI();
                RenderDeepFakePresetsUI();
                UpdateCameraLiveStatusBadges();
                ShowCameraToast($"🎭 Neues Ziel-Gesicht geladen: {System.IO.Path.GetFileName(dlg.FileName)}");
            }
        }

        private void CamDeepFakeOption_Changed(object sender, RoutedEventArgs e)
        {
            if (_suppressCameraUiEvents || !_isCameraStudioInitialized) return;
            PlaySoundSmartPtzTick();
            if (CamDeepFakeMouthMaskCheck != null) _camConfig.DeepFakeMouthMask = CamDeepFakeMouthMaskCheck.IsChecked == true;
            if (CamDeepFakeColorCorrCheck != null) _camConfig.DeepFakeColorCorrection = CamDeepFakeColorCorrCheck.IsChecked == true;
            if (CamDeepFakeEnhanceCheck != null) _camConfig.DeepFakeEnhancer = CamDeepFakeEnhanceCheck.IsChecked == true;
            SaveCameraStudioConfig();
        }

        private void CamDeepFakeOpacity_ValueChanged(object sender, RoutedPropertyChangedEventArgs<double> e)
        {
            if (_suppressCameraUiEvents || !_isCameraStudioInitialized) return;
            _camConfig.DeepFakeBlendOpacity = (int)Math.Round(e.NewValue);
            if (CamDeepFakeOpacityTxt != null) CamDeepFakeOpacityTxt.Text = $"{_camConfig.DeepFakeBlendOpacity} %";
        }

        private void CamDeepFakeArMode_Click(object sender, RoutedEventArgs e)
        {
            if (sender is Button btn && btn.Tag is string arMode)
            {
                PlaySoundNav();
                _camConfig.DeepFakeArMode = arMode;
                if (arMode != "none" && !_camConfig.DeepFakeEnabled)
                {
                    _camConfig.DeepFakeEnabled = true;
                    _suppressCameraUiEvents = true;
                    if (CamDeepFakeEnableCheck != null) CamDeepFakeEnableCheck.IsChecked = true;
                    _suppressCameraUiEvents = false;
                }
                SaveCameraStudioConfig();
                UpdateDeepFakeArButtonsUI();
                UpdateCameraLiveStatusBadges();
                ShowCameraToast(arMode == "none"
                    ? "🙂 AR Gesichts-Overlay deaktiviert"
                    : $"🤖 106-Landmark AR aktiv: {btn.Content}");
            }
        }

        private void UpdateDeepFakeArButtonsUI()
        {
            if (CamDeepFakeArGrid == null) return;
            foreach (var child in CamDeepFakeArGrid.Children)
            {
                if (child is Button b && b.Tag is string tag)
                {
                    StyleStandardInspectorFilterButton(b, string.Equals(_camConfig.DeepFakeArMode, tag, StringComparison.OrdinalIgnoreCase));
                }
            }
        }

        private void UpdateDeepFakeActiveFaceHeaderUI()
        {
            if (CamDeepFakeActiveFaceNameTxt == null || CamDeepFakeActiveFaceImg == null) return;
            string path = _camConfig.DeepFakeSourceFacePath;
            if (string.IsNullOrWhiteSpace(path) || !File.Exists(path))
            {
                CamDeepFakeActiveFaceNameTxt.Text = "Kein Gesicht gewählt (nur AR)";
                CamDeepFakeActiveFaceImg.Source = null;
                return;
            }

            var matchPreset = FunkenwerkDeepFakeEngine.GetAvailableFacePresets()
                .FirstOrDefault(p => string.Equals(p.FullPath, path, StringComparison.OrdinalIgnoreCase));
            CamDeepFakeActiveFaceNameTxt.Text = matchPreset != null
                ? matchPreset.Name
                : System.IO.Path.GetFileNameWithoutExtension(path);

            CamDeepFakeActiveFaceImg.Source = FunkenwerkDeepFakeEngine.LoadPortraitBitmap(path, 120);
        }

        private void RenderDeepFakePresetsUI()
        {
            if (CamDeepFakePresetsPanel == null) return;
            CamDeepFakePresetsPanel.Children.Clear();

            var presets = FunkenwerkDeepFakeEngine.GetAvailableFacePresets();
            foreach (var preset in presets)
            {
                bool isSelected = string.Equals(_camConfig.DeepFakeSourceFacePath, preset.FullPath, StringComparison.OrdinalIgnoreCase);

                var card = new Border
                {
                    Width = 92,
                    Background = isSelected ? BrushFromHex("#26231F") : BrushFromHex("#11131A"),
                    BorderBrush = isSelected ? BrushFromHex("#C5A059") : BrushFromHex("#262A36"),
                    BorderThickness = new Thickness(isSelected ? 1.5 : 1),
                    CornerRadius = new CornerRadius(9),
                    Padding = new Thickness(6),
                    Margin = new Thickness(0, 0, 6, 6),
                    Cursor = Cursors.Hand
                };

                var st = new StackPanel();
                var imgBorder = new Border
                {
                    Width = 78,
                    Height = 62,
                    CornerRadius = new CornerRadius(6),
                    Background = BrushFromHex("#1E1F22"),
                    ClipToBounds = true,
                    Margin = new Thickness(0, 0, 0, 5)
                };

                var bi = FunkenwerkDeepFakeEngine.LoadPortraitBitmap(preset.FullPath, 140);
                if (bi != null)
                {
                    imgBorder.Child = new Image { Source = bi, Stretch = Stretch.UniformToFill };
                }

                st.Children.Add(imgBorder);
                st.Children.Add(new TextBlock
                {
                    Text = preset.Name,
                    Foreground = isSelected ? DirectHexBrush(_isDarkMode ? "#C5A059" : "#8A6522") : BrushFromHex("#E5E7EB"),
                    FontSize = 10.5,
                    FontWeight = isSelected ? FontWeights.Bold : FontWeights.SemiBold,
                    HorizontalAlignment = HorizontalAlignment.Center,
                    TextTrimming = TextTrimming.CharacterEllipsis
                });

                card.Child = st;
                var capturedPreset = preset;
                card.MouseLeftButtonUp += (s, ev) =>
                {
                    PlaySoundActionPop();
                    _camConfig.DeepFakeSourceFacePath = capturedPreset.FullPath;
                    _camConfig.DeepFakeSwapActive = true;
                    _camConfig.DeepFakeEnabled = true;

                    // Ensure face-distorting AR modes don't hide the newly chosen Face-Swap portrait
                    if (_camConfig.DeepFakeArMode is "anime_eyes" or "alien_morph" or "pixel_anon")
                    {
                        _camConfig.DeepFakeArMode = "none";
                        UpdateDeepFakeArButtonsUI();
                    }

                    _suppressCameraUiEvents = true;
                    if (CamDeepFakeEnableCheck != null) CamDeepFakeEnableCheck.IsChecked = true;
                    _suppressCameraUiEvents = false;

                    _camDeepFakeEngine.UpdateSourceFace(capturedPreset.FullPath);
                    SaveCameraStudioConfig();
                    UpdateDeepFakeActiveFaceHeaderUI();
                    RenderDeepFakePresetsUI();
                    UpdateCameraLiveStatusBadges();
                    ShowCameraToast($"🎭 Deep Fake Gesicht aktiv: {capturedPreset.Name}");
                };

                CamDeepFakePresetsPanel.Children.Add(card);
            }

            UpdateDeepFakeActiveFaceHeaderUI();
        }

        // =========================================================================
        // 14. GREENSCREEN / CHROMA KEY + FARBAUSWAHL-PIPETTE
        // =========================================================================
        private void CamChromaEnableCheck_Changed(object sender, RoutedEventArgs e)
        {
            if (_suppressCameraUiEvents || !_isCameraStudioInitialized) return;
            PlaySoundSmartPtzTick();
            _camConfig.ChromaKeyEnabled = CamChromaEnableCheck.IsChecked == true;
            SaveCameraStudioConfig();
            UpdateCameraLiveStatusBadges();
            ShowCameraToast(_camConfig.ChromaKeyEnabled
                ? "🟩 Studio Chroma Key (YCbCr + Despill) aktiviert"
                : "Greenscreen deaktiviert");
        }

        private void CamStartEyedropper_Click(object sender, RoutedEventArgs e)
        {
            PlaySoundNav();
            _isEyedropperActive = true;
            CamEyedropperBanner.Visibility = Visibility.Visible;
            CamLivePreviewImage.Cursor = Cursors.Cross;
            ShowCameraToast("💧 Pipette bereit: Klicke im Live-Bild auf deinen Hintergrund!");
        }

        private void CamCancelEyedropper_Click(object sender, RoutedEventArgs e)
        {
            _isEyedropperActive = false;
            CamEyedropperBanner.Visibility = Visibility.Collapsed;
            CamLivePreviewImage.Cursor = Cursors.Arrow;
        }

        private void CamLivePreviewImage_MouseLeftButtonDown(object sender, MouseButtonEventArgs e)
        {
            if (!_isEyedropperActive) return;

            Point clickPt = e.GetPosition(CamLivePreviewImage);
            double actualW = CamLivePreviewImage.ActualWidth;
            double actualH = CamLivePreviewImage.ActualHeight;
            if (actualW <= 1 || actualH <= 1) return;

            double normX = Math.Clamp(clickPt.X / actualW, 0.0, 1.0);
            double normY = Math.Clamp(clickPt.Y / actualH, 0.0, 1.0);

            byte r = 0, g = 180, b = 60;
            if (_camPrimaryWorker != null && _camPrimaryWorker.HasFrame)
            {
                (r, g, b) = _camPrimaryWorker.SampleColorAtNormalized(normX, normY);
            }
            else if (_camCompositedBuffer != null)
            {
                int px = Math.Clamp((int)(normX * _camConfig.Width), 0, _camConfig.Width - 1);
                int py = Math.Clamp((int)(normY * _camConfig.Height), 0, _camConfig.Height - 1);
                int idx = (py * _camConfig.Width + px) * 4;
                b = _camCompositedBuffer[idx];
                g = _camCompositedBuffer[idx + 1];
                r = _camCompositedBuffer[idx + 2];
            }

            _camConfig.ChromaKeyR = r;
            _camConfig.ChromaKeyG = g;
            _camConfig.ChromaKeyB = b;
            _camConfig.ChromaKeyEnabled = true;

            _suppressCameraUiEvents = true;
            CamChromaEnableCheck.IsChecked = true;
            _suppressCameraUiEvents = false;

            UpdateChromaColorSwatchUI(r, g, b);
            SaveCameraStudioConfig();
            UpdateCameraLiveStatusBadges();

            _isEyedropperActive = false;
            CamEyedropperBanner.Visibility = Visibility.Collapsed;
            CamLivePreviewImage.Cursor = Cursors.Arrow;

            PlaySoundActionPop();
            ShowCameraToast($"💧 Hintergrundfarbe übernommen: #{r:X2}{g:X2}{b:X2}");
        }

        private void CamChromaPresetColor_Click(object sender, RoutedEventArgs e)
        {
            if (sender is Button btn && btn.Tag is string rgbStr)
            {
                var p = rgbStr.Split(',');
                if (p.Length == 3 && byte.TryParse(p[0], out byte r) && byte.TryParse(p[1], out byte g) && byte.TryParse(p[2], out byte b))
                {
                    PlaySoundSmartPtzTick();
                    _camConfig.ChromaKeyR = r;
                    _camConfig.ChromaKeyG = g;
                    _camConfig.ChromaKeyB = b;
                    _camConfig.ChromaKeyEnabled = true;
                    _suppressCameraUiEvents = true;
                    CamChromaEnableCheck.IsChecked = true;
                    _suppressCameraUiEvents = false;
                    UpdateChromaColorSwatchUI(r, g, b);
                    SaveCameraStudioConfig();
                    UpdateCameraLiveStatusBadges();
                }
            }
        }

        private void UpdateChromaColorSwatchUI(byte r, byte g, byte b)
        {
            if (CamChromaColorSwatch == null) return;
            CamChromaColorSwatch.Background = new SolidColorBrush(Color.FromRgb(r, g, b));
            CamChromaHexTxt.Text = $"#{r:X2}{g:X2}{b:X2}";
            CamChromaRgbTxt.Text = $"R: {r}  G: {g}  B: {b}";
        }

        private void CamChromaSlider_ValueChanged(object sender, RoutedPropertyChangedEventArgs<double> e)
        {
            if (_suppressCameraUiEvents || !_isCameraStudioInitialized) return;
            _camConfig.ChromaTolerance = (int)Math.Round(CamChromaToleranceSlider.Value);
            _camConfig.ChromaSmoothness = (int)Math.Round(CamChromaSmoothSlider.Value);
            if (CamChromaToleranceTxt != null) CamChromaToleranceTxt.Text = $"{_camConfig.ChromaTolerance} %";
            if (CamChromaSmoothTxt != null) CamChromaSmoothTxt.Text = $"{_camConfig.ChromaSmoothness} %";
        }

        private void CamChromaBgModeCombo_SelectionChanged(object sender, SelectionChangedEventArgs e)
        {
            if (_suppressCameraUiEvents || !_isCameraStudioInitialized) return;
            if (CamChromaBgModeCombo.SelectedItem is ComboBoxItem item && item.Tag is string bgMode)
            {
                _camConfig.ChromaBgMode = bgMode;
                SaveCameraStudioConfig();
            }
        }

        private void CamChooseChromaImage_Click(object sender, RoutedEventArgs e)
        {
            var dlg = new Microsoft.Win32.OpenFileDialog
            {
                Title = "Hintergrundbild für Greenscreen auswählen",
                Filter = "Bilder (*.png;*.jpg;*.jpeg;*.bmp)|*.png;*.jpg;*.jpeg;*.bmp"
            };
            if (dlg.ShowDialog() == true)
            {
                _camConfig.ChromaCustomImagePath = dlg.FileName;
                _camConfig.ChromaBgMode = "image";
                _camConfig.ChromaKeyEnabled = true;
                _camFrameProcessor.SetCustomBackgroundImage(dlg.FileName, _camConfig.Width, _camConfig.Height);

                _suppressCameraUiEvents = true;
                CamChromaEnableCheck.IsChecked = true;
                CamChromaBgModeCombo.SelectedIndex = 4; // Eigenes Bild
                _suppressCameraUiEvents = false;

                SaveCameraStudioConfig();
                UpdateCameraLiveStatusBadges();
                ShowCameraToast($"🖼 Hintergrundbild geladen: {System.IO.Path.GetFileName(dlg.FileName)}");
            }
        }

        // =========================================================================
        // MEDIA GALLERY & BUILT-IN MEDIA PREVIEW PLAYER (Requirement 5)
        // =========================================================================
        private List<CameraMediaItem> LoadCameraMediaItems()
        {
            var list = new List<CameraMediaItem>();
            string dir = GetEffectiveCameraMediaDir();
            if (!Directory.Exists(dir)) return list;

            try
            {
                foreach (var file in Directory.GetFiles(dir))
                {
                    string ext = System.IO.Path.GetExtension(file).ToLowerInvariant();
                    if (ext != ".png" && ext != ".jpg" && ext != ".mp4") continue;
                    var fi = new FileInfo(file);
                    bool isVid = ext == ".mp4";
                    if (isVid && !FunkenwerkVideoRecorder.IsValidMp4File(fi.FullName))
                    {
                        // Quarantine / remove broken unfinalized MP4 files so Explorer and WhatsApp never crash
                        if ((DateTime.UtcNow - fi.LastWriteTimeUtc).TotalSeconds > 15)
                        {
                            try { File.Delete(fi.FullName); } catch { }
                        }
                        continue;
                    }
                    string kbOrMb = fi.Length >= 1024 * 1024
                        ? $"{fi.Length / (1024.0 * 1024.0):0.0} MB"
                        : $"{Math.Max(1, fi.Length / 1024)} KB";

                    list.Add(new CameraMediaItem
                    {
                        FileName = fi.Name,
                        FullPath = fi.FullName,
                        CreatedAt = fi.LastWriteTime,
                        IsVideo = isVid,
                        DateLabel = fi.LastWriteTime.ToString("dd.MM.yyyy HH:mm:ss"),
                        SizeLabel = kbOrMb
                    });
                }
            }
            catch { }

            return list.OrderByDescending(x => x.CreatedAt).Take(40).ToList();
        }

        private void RenderCameraMediaGallery()
        {
            if (CamMediaGalleryPanel == null) return;
            CamMediaGalleryPanel.Children.Clear();

            var items = LoadCameraMediaItems();
            if (CamMediaCountBadge != null)
                CamMediaCountBadge.Text = items.Count.ToString();

            if (items.Count == 0)
            {
                CamMediaGalleryPanel.Children.Add(new TextBlock
                {
                    Text = "Noch keine Fotos oder Videos im Ordner 'KameraMedien'.",
                    Foreground = BrushFromHex("#6B7280"),
                    FontSize = 11.5,
                    TextWrapping = TextWrapping.Wrap,
                    Margin = new Thickness(4, 8, 4, 0)
                });
                return;
            }

            foreach (var item in items)
            {
                bool isSelected = _activeCamPreviewItem != null &&
                                  string.Equals(_activeCamPreviewItem.FullPath, item.FullPath, StringComparison.OrdinalIgnoreCase);

                var card = new Border
                {
                    Background = isSelected ? BrushFromHex("#26231F") : BrushFromHex("#181B24"),
                    BorderBrush = isSelected ? BrushFromHex("#C5A059") : BrushFromHex("#262A36"),
                    BorderThickness = new Thickness(1),
                    CornerRadius = new CornerRadius(9),
                    Padding = new Thickness(10, 7, 10, 7),
                    Margin = new Thickness(0, 0, 0, 5),
                    Cursor = Cursors.Hand
                };

                var g = new Grid();
                g.ColumnDefinitions.Add(new ColumnDefinition { Width = GridLength.Auto });
                g.ColumnDefinitions.Add(new ColumnDefinition { Width = new GridLength(1, GridUnitType.Star) });
                g.ColumnDefinitions.Add(new ColumnDefinition { Width = GridLength.Auto });

                g.Children.Add(new TextBlock
                {
                    Text = item.IsVideo ? "🎬" : "📸",
                    FontSize = 14,
                    VerticalAlignment = VerticalAlignment.Center,
                    Margin = new Thickness(0, 0, 8, 0)
                });

                var st = new StackPanel { VerticalAlignment = VerticalAlignment.Center };
                st.Children.Add(new TextBlock
                {
                    Text = item.FileName,
                    Foreground = isSelected ? DirectHexBrush(_isDarkMode ? "#C5A059" : "#8A6522") : BrushFromHex("#F9FAFB"),
                    FontSize = 11.5,
                    FontWeight = FontWeights.SemiBold,
                    TextTrimming = TextTrimming.CharacterEllipsis
                });
                st.Children.Add(new TextBlock
                {
                    Text = $"{item.DateLabel} • {item.SizeLabel}",
                    Foreground = BrushFromHex("#9CA3AF"),
                    FontSize = 10
                });
                Grid.SetColumn(st, 1);
                g.Children.Add(st);

                var delBtn = new Button
                {
                    Content = "🗑",
                    Background = Brushes.Transparent,
                    Foreground = BrushFromHex("#9CA3AF"),
                    BorderThickness = new Thickness(0),
                    Padding = new Thickness(5, 2, 5, 2),
                    Cursor = Cursors.Hand,
                    ToolTip = "Aufnahme löschen"
                };
                var captured = item;
                delBtn.Click += (s, ev) =>
                {
                    ev.Handled = true;
                    DeleteCameraMediaItem(captured);
                };
                Grid.SetColumn(delBtn, 2);
                g.Children.Add(delBtn);

                card.Child = g;
                card.MouseLeftButtonUp += (s, ev) => OpenCameraMediaPreview(captured);
                CamMediaGalleryPanel.Children.Add(card);
            }
        }

        private string _previewPlaybackSpeedMode = "1x";
        private double _previewPlaybackSpeedRatio = 1.0;

        private void OpenCameraMediaPreview(CameraMediaItem item)
        {
            if (!File.Exists(item.FullPath))
            {
                RenderCameraMediaGallery();
                return;
            }

            PlaySoundNav();
            _activeCamPreviewItem = item;
            CamMediaPreviewOverlay.Visibility = Visibility.Visible;
            CamPreviewTitleTxt.Text = item.FileName;
            CamPreviewSubTxt.Text = $"{(item.IsVideo ? "MP4 Videoaufnahme" : "PNG Foto-Snapshot")} • {item.DateLabel} • {item.SizeLabel}";

            if (item.IsVideo)
            {
                try
                {
                    CamPreviewMediaElement.Stop();
                    CamPreviewMediaElement.Close();
                    CamPreviewMediaElement.Source = null;
                }
                catch { }

                CamPreviewSnapshotImage.Visibility = Visibility.Collapsed;
                CamPreviewMediaElement.Visibility = Visibility.Visible;
                CamPreviewPlayPauseBtn.Visibility = Visibility.Visible;
                if (CamPreviewSpeedBarBorder != null) CamPreviewSpeedBarBorder.Visibility = Visibility.Visible;
                _previewPlaybackSpeedMode = "1x";
                _previewPlaybackSpeedRatio = 1.0;
                UpdatePreviewSpeedButtonsUI();
                CamPreviewMediaElement.Source = new Uri(item.FullPath, UriKind.Absolute);
                CamPreviewMediaElement.Play();
                _isCamPreviewPlaying = true;
            }
            else
            {
                try { CamPreviewMediaElement.Stop(); CamPreviewMediaElement.Close(); CamPreviewMediaElement.Source = null; } catch { }
                CamPreviewMediaElement.Visibility = Visibility.Collapsed;
                CamPreviewPlayPauseBtn.Visibility = Visibility.Collapsed;
                if (CamPreviewSpeedBarBorder != null) CamPreviewSpeedBarBorder.Visibility = Visibility.Collapsed;

                var bi = new System.Windows.Media.Imaging.BitmapImage();
                using (var fs = new FileStream(item.FullPath, FileMode.Open, FileAccess.Read, FileShare.ReadWrite))
                {
                    bi.BeginInit();
                    bi.CacheOption = System.Windows.Media.Imaging.BitmapCacheOption.OnLoad;
                    bi.StreamSource = fs;
                    bi.EndInit();
                }
                bi.Freeze();
                CamPreviewSnapshotImage.Source = bi;
                CamPreviewSnapshotImage.Visibility = Visibility.Visible;
            }

            RenderCameraMediaGallery();
        }

        private void CamPreviewSpeedPreset_Click(object sender, RoutedEventArgs e)
        {
            if (sender is not Button btn || btn.Tag is not string mode) return;
            PlaySoundSmartPtzTick();

            _previewPlaybackSpeedMode = mode;
            if (mode == "1x") _previewPlaybackSpeedRatio = 1.0;
            else if (mode == "2x") _previewPlaybackSpeedRatio = 2.0;
            else if (mode == "3x") _previewPlaybackSpeedRatio = 3.0;
            else if (mode == "custom")
            {
                if (!TryParseSpeedMultiplier(CamPrevSpeedCustomBox?.Text, out double customVal))
                    customVal = 1.5;
                _previewPlaybackSpeedRatio = customVal;
                if (CamPrevSpeedCustomBox != null)
                    CamPrevSpeedCustomBox.Text = customVal.ToString("0.##", CultureInfo.InvariantCulture);
            }

            try
            {
                if (CamPreviewMediaElement != null)
                    CamPreviewMediaElement.SpeedRatio = Math.Clamp(_previewPlaybackSpeedRatio, 0.25, 8.0);
            }
            catch { }

            UpdatePreviewSpeedButtonsUI();
            if (mode == "custom" && CamPrevSpeedCustomBox != null)
            {
                CamPrevSpeedCustomBox.Focus();
                CamPrevSpeedCustomBox.SelectAll();
            }
        }

        private void CamPrevSpeedCustomBox_LostFocus(object sender, RoutedEventArgs e)
        {
            ApplyCustomPreviewSpeedFromBox();
        }

        private void CamPrevSpeedCustomBox_KeyDown(object sender, KeyEventArgs e)
        {
            if (e.Key == Key.Enter)
            {
                ApplyCustomPreviewSpeedFromBox();
                e.Handled = true;
            }
        }

        private void ApplyCustomPreviewSpeedFromBox()
        {
            if (CamPrevSpeedCustomBox == null) return;
            if (TryParseSpeedMultiplier(CamPrevSpeedCustomBox.Text, out double val))
            {
                _previewPlaybackSpeedMode = "custom";
                _previewPlaybackSpeedRatio = Math.Clamp(val, 0.25, 8.0);
                CamPrevSpeedCustomBox.Text = _previewPlaybackSpeedRatio.ToString("0.##", CultureInfo.InvariantCulture);
                try
                {
                    if (CamPreviewMediaElement != null)
                        CamPreviewMediaElement.SpeedRatio = _previewPlaybackSpeedRatio;
                }
                catch { }
                UpdatePreviewSpeedButtonsUI();
            }
            else
            {
                CamPrevSpeedCustomBox.Text = _previewPlaybackSpeedRatio.ToString("0.##", CultureInfo.InvariantCulture);
            }
        }

        private void UpdatePreviewSpeedButtonsUI()
        {
            if (CamPrevSpeed1xBtn == null || CamPrevSpeed2xBtn == null || CamPrevSpeed3xBtn == null || CamPrevSpeedCustomBtn == null)
                return;

            // CamMediaPreviewOverlay sits inside the dark video stage (FixedDarkOverlay), so keep crisp gold/dark-theater contrast!
            void StyleBtn(Button b, bool active)
            {
                b.Background = active ? DirectHexBrush("#C5A059") : Brushes.Transparent;
                b.Foreground = active ? DirectHexBrush("#111827") : DirectHexBrush("#E5E7EB");
                b.BorderBrush = active ? DirectHexBrush("#C5A059") : Brushes.Transparent;
                b.BorderThickness = new Thickness(active ? 1 : 0);
                b.FontWeight = active ? FontWeights.Bold : FontWeights.SemiBold;
            }

            StyleBtn(CamPrevSpeed1xBtn, _previewPlaybackSpeedMode == "1x");
            StyleBtn(CamPrevSpeed2xBtn, _previewPlaybackSpeedMode == "2x");
            StyleBtn(CamPrevSpeed3xBtn, _previewPlaybackSpeedMode == "3x");
            StyleBtn(CamPrevSpeedCustomBtn, _previewPlaybackSpeedMode == "custom");

            if (CamPrevSpeedCustomWrap != null)
                CamPrevSpeedCustomWrap.Visibility = _previewPlaybackSpeedMode == "custom" ? Visibility.Visible : Visibility.Collapsed;
        }

        private void CamPreviewPlayPause_Click(object sender, RoutedEventArgs e)
        {
            if (_activeCamPreviewItem == null || !_activeCamPreviewItem.IsVideo) return;
            if (_isCamPreviewPlaying)
            {
                CamPreviewMediaElement.Pause();
                _isCamPreviewPlaying = false;
            }
            else
            {
                CamPreviewMediaElement.Play();
                _isCamPreviewPlaying = true;
            }
        }

        private void CamPreviewMedia_MediaOpened(object sender, RoutedEventArgs e)
        {
            try
            {
                if (CamPreviewMediaElement != null)
                    CamPreviewMediaElement.SpeedRatio = Math.Clamp(_previewPlaybackSpeedRatio, 0.25, 8.0);
            }
            catch { }
        }

        private void CamPreviewMedia_MediaFailed(object sender, ExceptionRoutedEventArgs e)
        {
            try
            {
                CamPreviewMediaElement.Stop();
                CamPreviewMediaElement.Close();
                CamPreviewMediaElement.Source = null;
            }
            catch { }
            ShowCameraToast("⚠ Video konnte im Vorschau-Player nicht geladen werden.");
        }

        private void CamPreviewMedia_MediaEnded(object sender, RoutedEventArgs e)
        {
            CamPreviewMediaElement.Position = TimeSpan.Zero;
            CamPreviewMediaElement.Play();
        }

        private void CamPreviewOpenExplorer_Click(object sender, RoutedEventArgs e)
        {
            if (_activeCamPreviewItem == null) return;
            RevealFileInExplorerSafely(_activeCamPreviewItem.FullPath);
        }

        private void CamCloseMediaPreview_Click(object sender, RoutedEventArgs e)
        {
            PlaySoundNav();
            try
            {
                CamPreviewMediaElement.Stop();
                CamPreviewMediaElement.Close();
                CamPreviewMediaElement.Source = null;
            }
            catch { }
            _activeCamPreviewItem = null;
            CamMediaPreviewOverlay.Visibility = Visibility.Collapsed;
            RenderCameraMediaGallery();
        }

        private void DeleteCameraMediaItem(CameraMediaItem item)
        {
            if (_activeCamPreviewItem != null && string.Equals(_activeCamPreviewItem.FullPath, item.FullPath, StringComparison.OrdinalIgnoreCase))
            {
                CamCloseMediaPreview_Click(this, new RoutedEventArgs());
            }
            try
            {
                if (File.Exists(item.FullPath)) File.Delete(item.FullPath);
                PlaySoundActionPop();
                RenderCameraMediaGallery();
            }
            catch { }
        }

        private void CamOpenMediaFolder_Click(object sender, RoutedEventArgs e)
        {
            PlaySoundActionPop();
            OpenDirectoryInExplorerSafely(GetEffectiveCameraMediaDir());
        }

        private void CamRefreshGallery_Click(object sender, RoutedEventArgs e)
        {
            PlaySoundNav();
            RenderCameraMediaGallery();
        }

    }
}
