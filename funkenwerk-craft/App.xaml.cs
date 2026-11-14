using System;
using System.IO;
using System.Linq;
using System.Runtime.InteropServices;
using System.Text;
using System.Threading.Tasks;
using System.Windows;

namespace FunkenwerkCraft
{
    public partial class App : System.Windows.Application
    {
        [DllImport("kernel32.dll", SetLastError = true)]
        private static extern bool AttachConsole(int dwProcessId);

        private const int AttachParentProcess = -1;

        private static void EnsureConsoleAttached()
        {
            try
            {
                if (!Console.IsOutputRedirected)
                {
                    AttachConsole(AttachParentProcess);
                }
                Console.OutputEncoding = new UTF8Encoding(false);
            }
            catch { }
        }

        private static void AppendAppErrorLog(string detail)
        {
            try
            {
                string logPath = Path.Combine(CraftEngine.LocalDataDir, "funkenwerk_craft_error.log");
                File.AppendAllText(logPath, $"[{DateTime.Now:O}] {detail}{Environment.NewLine}", new UTF8Encoding(false));
            }
            catch { }
        }

        protected override async void OnStartup(StartupEventArgs e)
        {
            base.OnStartup(e);

            AppDomain.CurrentDomain.UnhandledException += (_, args) =>
            {
                AppendAppErrorLog($"{args.ExceptionObject}");
            };

            DispatcherUnhandledException += (_, args) =>
            {
                AppendAppErrorLog($"{args.Exception}");
                args.Handled = true;
            };

            if (e.Args != null && e.Args.Length > 0)
            {
                if (e.Args.Any(a => string.Equals(a, "--self-test", StringComparison.OrdinalIgnoreCase)))
                {
                    EnsureConsoleAttached();
                    int code = await CraftEngine.RunSelfTestAsync();
                    Environment.Exit(code);
                    return;
                }

                int cliIdx = Array.FindIndex(e.Args, a => string.Equals(a, "--cli", StringComparison.OrdinalIgnoreCase));
                if (cliIdx >= 0)
                {
                    EnsureConsoleAttached();
                    if (cliIdx + 1 >= e.Args.Length)
                    {
                        string missingErr = "{\"ok\":false,\"error\":\"CLI JSON-Parameter fehlt.\"}";
                        Console.Out.WriteLine(missingErr);
                        Console.Out.Flush();
                        Environment.Exit(1);
                        return;
                    }

                    string reqArg = e.Args[cliIdx + 1];
                    string resultJson;
                    try
                    {
                        string jsonPayload;
                        string trimmed = reqArg.TrimStart();
                        if (trimmed.StartsWith("{", StringComparison.Ordinal))
                        {
                            jsonPayload = reqArg;
                        }
                        else if (File.Exists(reqArg))
                        {
                            var fi = new FileInfo(reqArg);
                            if (!string.Equals(fi.Extension, ".json", StringComparison.OrdinalIgnoreCase))
                                throw new UnauthorizedAccessException("Nur .json-Dateien sind fuer --cli erlaubt.");
                            if (fi.Attributes.HasFlag(FileAttributes.ReparsePoint) || fi.LinkTarget != null)
                                throw new UnauthorizedAccessException("Symlinks / Reparse-Points sind fuer --cli blockiert.");
                            if (fi.Length > 262144)
                                throw new InvalidDataException("CLI JSON-Datei ueberschreitet 256 KB.");
                            jsonPayload = await File.ReadAllTextAsync(fi.FullName, Encoding.UTF8);
                        }
                        else
                        {
                            jsonPayload = reqArg;
                        }

                        resultJson = await CraftEngine.ExecuteCliCommandJsonAsync(jsonPayload);
                    }
                    catch (Exception ex)
                    {
                        resultJson = System.Text.Json.JsonSerializer.Serialize(new { ok = false, error = ex.Message });
                    }

                    Console.Out.WriteLine(resultJson);
                    Console.Out.Flush();
                    int exitCode = CraftEngine.GetCliExitCode(resultJson);
                    Environment.Exit(exitCode);
                    return;
                }
            }

            string? initialProject = (e.Args != null && e.Args.Length > 0 && !e.Args[0].StartsWith("--", StringComparison.Ordinal))
                ? e.Args[0]
                : null;

            var win = new MainWindow(initialProject);
            MainWindow = win;
            win.Show();
        }
    }
}
