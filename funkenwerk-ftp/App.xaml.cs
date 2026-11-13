using System;
using System.Linq;
using System.Windows;

namespace FunkenwerkFtp
{
    public partial class App : Application
    {
        protected override void OnStartup(StartupEventArgs e)
        {
            base.OnStartup(e);

            if (e.Args.Any(a => string.Equals(a, "--self-test", StringComparison.OrdinalIgnoreCase)))
            {
                try
                {
                    var profiles = FunkenwerkFtpSecurity.LoadAndImportProfiles();
                    bool securityOk = profiles.Count > 0 && FunkenwerkFtpSecurity.RunSelfTest();
                    var testWin = new MainWindow();
                    var sampleDoc = FtpTextBufferCodec.Decode(System.Text.Encoding.UTF8.GetBytes("<?php\necho 'ok';\n"), false);
                    var testEd = new FtpFileEditorWindow(testWin, true, true, "test.php", "/html/test.php", sampleDoc, _ => System.Threading.Tasks.Task.CompletedTask);
                    bool uiOk = testWin.Title.Contains("Funkenwerk FTP") && testEd.Title.Contains("test.php");
                    Environment.Exit((securityOk && uiOk) ? 0 : 1);
                    return;
                }
                catch (Exception ex)
                {
                    try
                    {
                        System.IO.File.WriteAllText(
                            System.IO.Path.Combine(FunkenwerkFtpSecurity.VaultDir, "selftest_error.txt"),
                            ex.ToString());
                    }
                    catch { }
                    Environment.Exit(2);
                    return;
                }
            }

            var win = new MainWindow();
            MainWindow = win;
            win.Show();
        }
    }
}
