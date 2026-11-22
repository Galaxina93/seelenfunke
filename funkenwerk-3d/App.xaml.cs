using System;
using System.Diagnostics;
using System.IO;
using System.Linq;
using System.Runtime.InteropServices;
using System.Windows;
using System.Windows.Threading;

namespace Funkenwerk3D
{
    public partial class App : Application
    {
        [DllImport("kernel32.dll", SetLastError = true)]
        private static extern bool AttachConsole(int dwProcessId);

        [DllImport("kernel32.dll", SetLastError = true)]
        private static extern bool TerminateProcess(IntPtr hProcess, uint uExitCode);

        protected override void OnStartup(StartupEventArgs e)
        {
            base.OnStartup(e);

            if (e.Args.Any(a => string.Equals(a, "--self-test", StringComparison.OrdinalIgnoreCase)))
            {
                try
                {
                    if (!Console.IsOutputRedirected)
                        AttachConsole(-1);
                }
                catch { }

                ShutdownMode = ShutdownMode.OnExplicitShutdown;
                uint code = 1u;
                try
                {
                    bool ok = AwaitOnSta(ThreeDSelfTestRunner.RunAllAsync());
                    code = ok ? 0u : 1u;
                }
                catch (Exception ex)
                {
                    Console.Error.WriteLine("SELF-TEST ERROR: " + ex);
                    code = 2u;
                }
                Console.Out.Flush();
                Console.Error.Flush();
                TerminateProcess(Process.GetCurrentProcess().Handle, code);
                Shutdown((int)code);
                return;
            }

            if (e.Args.Any(a => string.Equals(a, "--register-default", StringComparison.OrdinalIgnoreCase)))
            {
                bool includeAll = e.Args.Any(a => string.Equals(a, "--all-formats", StringComparison.OrdinalIgnoreCase));
                bool ok = WindowsDefault3DRegistrar.RegisterAsWindows3DViewer(
                    out string msg,
                    setDefaultForPrimary: true,
                    setDefaultForSlicerFormats: includeAll);
                try { Console.WriteLine(msg); } catch { }
                Shutdown(ok ? 0 : 1);
                return;
            }

            int prevIdx = Array.FindIndex(e.Args, a => string.Equals(a, "--render-preview", StringComparison.OrdinalIgnoreCase));
            if (prevIdx >= 0 && prevIdx + 2 < e.Args.Length)
            {
                ShutdownMode = ShutdownMode.OnExplicitShutdown;
                uint code = 1u;
                try
                {
                    code = AwaitOnSta(RunHeadlessPreviewCliAsync(Path.GetFullPath(e.Args[prevIdx + 1]), Path.GetFullPath(e.Args[prevIdx + 2]))) ? 0u : 1u;
                }
                catch { code = 2u; }
                TerminateProcess(Process.GetCurrentProcess().Handle, code);
                Shutdown((int)code);
                return;
            }

            int rigIdx = Array.FindIndex(e.Args, a => string.Equals(a, "--rig-glb", StringComparison.OrdinalIgnoreCase));
            if (rigIdx >= 0)
            {
                try
                {
                    if (!Console.IsOutputRedirected)
                        AttachConsole(-1);
                }
                catch { }

                ShutdownMode = ShutdownMode.OnExplicitShutdown;
                uint code = 1u;
                try
                {
                    code = AwaitOnSta(RunHeadlessRigCliAsync(e.Args, rigIdx)) ? 0u : 1u;
                }
                catch (Exception ex)
                {
                    Console.Error.WriteLine("RIG-GLB ERROR: " + ex.Message);
                    code = 2u;
                }
                Console.Out.Flush();
                Console.Error.Flush();
                TerminateProcess(Process.GetCurrentProcess().Handle, code);
                Shutdown((int)code);
                return;
            }

            // Register Capabilities, OpenWithProgids & primary 3D (.glb, .gltf, .obj, .ply, .fbx) file associations
            WindowsDefault3DRegistrar.RegisterAsWindows3DViewer(out _, setDefaultForPrimary: true, setDefaultForSlicerFormats: false);

            string? initialModelPath = null;
            foreach (string arg in e.Args)
            {
                if (!string.IsNullOrWhiteSpace(arg) &&
                    !arg.StartsWith("-", StringComparison.Ordinal) &&
                    !arg.StartsWith("/", StringComparison.Ordinal) &&
                    File.Exists(arg))
                {
                    initialModelPath = arg;
                    break;
                }
            }

            var win = new MainWindow(initialModelPath);
            MainWindow = win;
            win.Show();
        }

        internal static T AwaitOnSta<T>(System.Threading.Tasks.Task<T> task)
        {
            if (task.IsCompleted)
                return task.GetAwaiter().GetResult();

            var frame = new DispatcherFrame();
            task.ContinueWith(_ => frame.Continue = false, System.Threading.Tasks.TaskScheduler.Default);
            Dispatcher.PushFrame(frame);
            return task.GetAwaiter().GetResult();
        }

        private static async System.Threading.Tasks.Task<bool> RunHeadlessRigCliAsync(string[] args, int rigFlagIndex)
        {
            if (rigFlagIndex + 2 >= args.Length)
            {
                Console.Error.WriteLine("Syntax: Funkenwerk3D.exe --rig-glb <inputModel> <output.glb> [--profile auto|funki_chibi|humanoid] [--variant original|funki_male|funkira_female]");
                return false;
            }

            string inputPath = Path.GetFullPath(args[rigFlagIndex + 1]);
            string outputPath = Path.GetFullPath(args[rigFlagIndex + 2]);
            string profile = "auto";
            string variant = "original";
            string? previewPngPath = null;
            bool stripPedestal = true;

            for (int i = rigFlagIndex + 3; i < args.Length; i++)
            {
                if (string.Equals(args[i], "--profile", StringComparison.OrdinalIgnoreCase) && i + 1 < args.Length)
                    profile = args[++i];
                else if (string.Equals(args[i], "--variant", StringComparison.OrdinalIgnoreCase) && i + 1 < args.Length)
                    variant = args[++i];
                else if (string.Equals(args[i], "--preview", StringComparison.OrdinalIgnoreCase) && i + 1 < args.Length)
                    previewPngPath = Path.GetFullPath(args[++i]);
                else if (string.Equals(args[i], "--keep-pedestal", StringComparison.OrdinalIgnoreCase))
                    stripPedestal = false;
            }

            MainWindow? win = null;
            try
            {
                win = new MainWindow
                {
                    WindowStyle = WindowStyle.None,
                    ShowInTaskbar = false,
                    ShowActivated = false,
                    Width = 640,
                    Height = 480,
                    Left = -20000,
                    Top = -20000
                };
                win.Show();

                var inStats = await win.LoadModelFileAsync(inputPath);
                var rigRes = await win.GenerateExoskeletonAsync(profile, variant, stripPedestal, showBones: !string.IsNullOrEmpty(previewPngPath), autoPlay: false);
                byte[] glbBytes = await win.ExportRiggedGlbBytesAsync(keepCurrentPose: false);

                if (!string.IsNullOrEmpty(previewPngPath))
                {
                    byte[] pngBytes = await win.CaptureViewportPngBytesAsync(transparentBackground: false);
                    await File.WriteAllBytesAsync(previewPngPath, pngBytes);
                }

                string? outDir = Path.GetDirectoryName(outputPath);
                if (!string.IsNullOrEmpty(outDir))
                    Directory.CreateDirectory(outDir);

                string tmpOut = outputPath + ".tmp";
                await File.WriteAllBytesAsync(tmpOut, glbBytes);
                File.Move(tmpOut, outputPath, overwrite: true);

                Console.WriteLine($"OK: {outputPath} ({glbBytes.Length:N0} Bytes, {inStats.Triangles:N0} Tris, {rigRes.BoneCount} Knochen, {rigRes.ClipCount} Animationen, Profil={rigRes.ProfileResolved}, Stil={variant})");
                return true;
            }
            finally
            {
                uint browserPid = 0;
                try { browserPid = win?.ViewportWebView?.CoreWebView2?.BrowserProcessId ?? 0; } catch { }
                try { win?.ViewportWebView?.Dispose(); } catch { }
                try { win?.Close(); } catch { }
                if (browserPid > 0)
                {
                    try { Process.GetProcessById((int)browserPid).WaitForExit(2000); } catch { }
                }
            }
        }

        private static async System.Threading.Tasks.Task<bool> RunHeadlessPreviewCliAsync(string inputPath, string outputPngPath)
        {
            MainWindow? win = null;
            try
            {
                win = new MainWindow
                {
                    WindowStyle = WindowStyle.None,
                    ShowInTaskbar = false,
                    ShowActivated = false,
                    Width = 820,
                    Height = 680,
                    Left = -20000,
                    Top = -20000
                };
                win.Show();
                await win.LoadModelFileAsync(inputPath);
                await System.Threading.Tasks.Task.Delay(180);
                byte[] pngBytes = await win.CaptureViewportPngBytesAsync(transparentBackground: false);
                string? dir = Path.GetDirectoryName(outputPngPath);
                if (!string.IsNullOrEmpty(dir)) Directory.CreateDirectory(dir);
                await File.WriteAllBytesAsync(outputPngPath, pngBytes);
                return pngBytes.Length > 200;
            }
            finally
            {
                uint browserPid = 0;
                try { browserPid = win?.ViewportWebView?.CoreWebView2?.BrowserProcessId ?? 0; } catch { }
                try { win?.ViewportWebView?.Dispose(); } catch { }
                try { win?.Close(); } catch { }
                if (browserPid > 0)
                {
                    try { Process.GetProcessById((int)browserPid).WaitForExit(2000); } catch { }
                }
            }
        }
    }
}
