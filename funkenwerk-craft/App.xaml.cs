using System;
using System.IO;
using System.Linq;
using System.Text;
using System.Threading.Tasks;
using System.Windows;

namespace FunkenwerkCraft
{
    public partial class App : System.Windows.Application
    {
        protected override async void OnStartup(StartupEventArgs e)
        {
            base.OnStartup(e);

            AppDomain.CurrentDomain.UnhandledException += (_, args) =>
            {
                try
                {
                    string logPath = Path.Combine(CraftEngine.WorkspaceRoot, "funkenwerk_craft_error.log");
                    File.AppendAllText(logPath, $"[{DateTime.Now:O}] {args.ExceptionObject}\n");
                }
                catch { }
            };

            DispatcherUnhandledException += (_, args) =>
            {
                try
                {
                    string logPath = Path.Combine(CraftEngine.WorkspaceRoot, "funkenwerk_craft_error.log");
                    File.AppendAllText(logPath, $"[{DateTime.Now:O}] {args.Exception}\n");
                }
                catch { }
                args.Handled = true;
            };

            if (e.Args != null && e.Args.Length > 0)
            {
                if (e.Args.Any(a => string.Equals(a, "--self-test", StringComparison.OrdinalIgnoreCase)))
                {
                    int code = await CraftEngine.RunSelfTestAsync();
                    Environment.Exit(code);
                    return;
                }

                int cliIdx = Array.FindIndex(e.Args, a => string.Equals(a, "--cli", StringComparison.OrdinalIgnoreCase));
                if (cliIdx >= 0 && cliIdx + 1 < e.Args.Length)
                {
                    Console.OutputEncoding = new UTF8Encoding(false);
                    string reqArg = e.Args[cliIdx + 1];
                    string jsonPayload = File.Exists(reqArg) ? await File.ReadAllTextAsync(reqArg, Encoding.UTF8) : reqArg;
                    string resultJson = await CraftEngine.ExecuteCliCommandJsonAsync(jsonPayload);
                    Console.Out.WriteLine(resultJson);
                    Console.Out.Flush();
                    Environment.Exit(0);
                    return;
                }
            }

            string? initialProject = (e.Args != null && e.Args.Length > 0 && !e.Args[0].StartsWith("--"))
                ? e.Args[0]
                : null;

            var win = new MainWindow(initialProject);
            MainWindow = win;
            win.Show();
        }
    }
}
