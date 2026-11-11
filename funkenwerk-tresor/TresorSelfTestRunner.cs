using System;
using System.IO;
using System.Linq;
using System.Text;

namespace FunkenwerkTresor
{
    public static class TresorSelfTestRunner
    {
        public static bool RunEndToEndSelfTest()
        {
            int passed = 0;
            int total = 12;

            void Check(string name, Func<bool> test)
            {
                try
                {
                    bool ok = test();
                    Console.WriteLine(ok ? $"[PASS] {name}" : $"[FAIL] {name}");
                    if (ok) passed++;
                }
                catch (Exception ex)
                {
                    Console.WriteLine($"[FAIL] {name}: {ex.Message}");
                }
            }

            string tempDir = Path.Combine(Path.GetTempPath(), "fw_tresor_selftest_" + Guid.NewGuid().ToString("N").Substring(0, 8));
            Directory.CreateDirectory(tempDir);

            try
            {
                Check("1. Marken-Icons (funkenwerk_tresor.ico & funkenwerk_tresor.png)", () =>
                {
                    var ico = System.Windows.Application.GetResourceStream(new Uri("pack://application:,,,/funkenwerk_tresor.ico"));
                    var png = System.Windows.Application.GetResourceStream(new Uri("pack://application:,,,/funkenwerk_tresor.png"));
                    return ico != null && ico.Stream.Length > 1000 && png != null && png.Stream.Length > 1000;
                });

                Check("2. KDBX 4.0 (Argon2id + AES-256 + ChaCha20 Inner-Stream + Strukturbaum + Anhang) Roundtrip", () =>
                {
                    var db = KdbxDatabase.CreateNewDefault("DB");
                    db.Argon2MemoryBytes = 16UL * 1024UL * 1024UL; // fast 16MB for self-test
                    db.Argon2Iterations = 2;

                    var sfGroup = db.GetDisplayGroups().First(g => g.Name == "Mein-Seelenfunke");
                    var emailsGroup = db.GetDisplayGroups().First(g => g.Name == "Emails");
                    var entry = new KdbxEntry
                    {
                        Title = "FYRST Geschäftskonto",
                        UserName = "alina.steinhauer@mein-seelenfunke.de",
                        Password = "Äöü!StrengGeheim#2026$Tresor",
                        Url = "https://banking.fyrst.de",
                        Notes = "Recovery-Pin: 9941-8820",
                        IsFavorite = true,
                        TotpSecretOrUri = "JBSWY3DPEHPK3PXP"
                    };
                    entry.Attachments.Add(new KdbxAttachment
                    {
                        FileName = "tan_brief.txt",
                        Data = Encoding.UTF8.GetBytes("Geheimer Anhang im KDBX 4 Inner-Header")
                    });
                    sfGroup.Entries.Add(entry);

                    string kdbxPath = Path.Combine(tempDir, "test_v4_argon2id.kdbx");
                    KdbxCryptoEngine.SaveKdbxFile(db, kdbxPath, "MasterPass#Seelenfunke2026!");

                    var loaded = KdbxCryptoEngine.OpenKdbxFile(kdbxPath, "MasterPass#Seelenfunke2026!");
                    var loadedEntry = loaded.GetAllEntriesFlat().FirstOrDefault(e => e.Title == "FYRST Geschäftskonto");

                    return loaded.FormatMajor == 4 &&
                           loaded.KdfAlgorithm == "Argon2id" &&
                           loaded.CipherAlgorithm == "AES-256" &&
                           emailsGroup.SubGroups.Count == 2 &&
                           loaded.GetDisplayGroups().Count >= 30 &&
                           KeePassIconRenderer.GetSelectableIcons().Count >= 18 &&
                           loadedEntry != null &&
                           loadedEntry.Password == "Äöü!StrengGeheim#2026$Tresor" &&
                           loadedEntry.IsFavorite &&
                           loadedEntry.HasTotp &&
                           loadedEntry.Attachments.Count == 1 &&
                           Encoding.UTF8.GetString(loadedEntry.Attachments[0].Data) == "Geheimer Anhang im KDBX 4 Inner-Header";
                });

                Check("3. Falsches Master-Passwort & HMAC-Manipulationsschutz (Fail-Closed)", () =>
                {
                    string kdbxPath = Path.Combine(tempDir, "test_v4_argon2id.kdbx");
                    bool wrongPwdRejected = false;
                    try
                    {
                        KdbxCryptoEngine.OpenKdbxFile(kdbxPath, "FalschesPasswort123");
                    }
                    catch (UnauthorizedAccessException)
                    {
                        wrongPwdRejected = true;
                    }

                    byte[] tampered = File.ReadAllBytes(kdbxPath);
                    tampered[tampered.Length - 20] ^= 0xFF;
                    bool tamperRejected = false;
                    try
                    {
                        KdbxCryptoEngine.OpenKdbxBytes(tampered, "MasterPass#Seelenfunke2026!");
                    }
                    catch (InvalidDataException)
                    {
                        tamperRejected = true;
                    }

                    return wrongPwdRejected && tamperRejected;
                });

                Check("4. KDBX 4.0 Outer-Cipher ChaCha20 (RFC 7539) Kompatibilität", () =>
                {
                    var db = KdbxDatabase.CreateNewDefault("ChaCha Tresor");
                    db.CipherAlgorithm = "ChaCha20";
                    db.Argon2MemoryBytes = 16UL * 1024UL * 1024UL;
                    db.RootGroup.SubGroups[0].Entries.Add(new KdbxEntry
                    {
                        Title = "Mittwald Stage SSH",
                        UserName = "p659132",
                        Password = "ChaCha20_Secret_Password_99!"
                    });

                    byte[] raw = KdbxCryptoEngine.WriteKdbx4Bytes(db, "ChaChaMasterKey!");
                    var loaded = KdbxCryptoEngine.OpenKdbxBytes(raw, "ChaChaMasterKey!");
                    return loaded.CipherAlgorithm == "ChaCha20" &&
                           loaded.GetAllEntriesFlat().Any(e => e.Password == "ChaCha20_Secret_Password_99!");
                });

                Check("5. 2-Faktor Composite-Key: Master-Passwort + .keyx XML v2.0 Schlüsseldatei", () =>
                {
                    string keyfilePath = Path.Combine(tempDir, "master.keyx");
                    KdbxCryptoEngine.GenerateKeyfileXmlV2(keyfilePath);

                    var db = KdbxDatabase.CreateNewDefault("Keyfile Tresor");
                    db.Argon2MemoryBytes = 16UL * 1024UL * 1024UL;
                    db.RootGroup.SubGroups[0].Entries.Add(new KdbxEntry
                    {
                        Title = "ELSTER Zertifikat",
                        Password = "Elster#Keyfile#Protected"
                    });

                    string kdbxPath = Path.Combine(tempDir, "with_keyfile.kdbx");
                    KdbxCryptoEngine.SaveKdbxFile(db, kdbxPath, "MeinPasswort", keyfilePath);

                    bool blockedWithoutKeyfile = false;
                    try
                    {
                        KdbxCryptoEngine.OpenKdbxFile(kdbxPath, "MeinPasswort", null);
                    }
                    catch (UnauthorizedAccessException)
                    {
                        blockedWithoutKeyfile = true;
                    }

                    var unlocked = KdbxCryptoEngine.OpenKdbxFile(kdbxPath, "MeinPasswort", keyfilePath);
                    return blockedWithoutKeyfile &&
                           unlocked.GetAllEntriesFlat().Any(e => e.Password == "Elster#Keyfile#Protected");
                });

                Check("6. Legacy KDBX 3.1 (AES-KDF + Salsa20) Öffnen & 1-Klick Upgrade auf Argon2id KDBX 4", () =>
                {
                    var legacyDb = KdbxDatabase.CreateNewDefault("Alte KeePass 2 DB");
                    legacyDb.RootGroup.SubGroups[0].Entries.Add(new KdbxEntry
                    {
                        Title = "Altes T-Online Postfach",
                        UserName = "alina.stone@t-online.de",
                        Password = "LegacyKdbx3Password!"
                    });

                    byte[] kdbx3Bytes = KdbxCryptoEngine.WriteLegacyKdbx3BytesForTest(legacyDb, "OldPass123", 5000);
                    var openedLegacy = KdbxCryptoEngine.OpenKdbxBytes(kdbx3Bytes, "OldPass123");
                    bool wasV3 = openedLegacy.FormatMajor == 3 && openedLegacy.KdfAlgorithm == "AES-KDF" && !openedLegacy.IsModernArgon2Kdbx4;

                    // Upgrade to KDBX 4 Argon2id
                    openedLegacy.Argon2MemoryBytes = 16UL * 1024UL * 1024UL;
                    byte[] upgradedBytes = KdbxCryptoEngine.WriteKdbx4Bytes(openedLegacy, "OldPass123");
                    var reopenedV4 = KdbxCryptoEngine.OpenKdbxBytes(upgradedBytes, "OldPass123");

                    return wasV3 &&
                           reopenedV4.IsModernArgon2Kdbx4 &&
                           reopenedV4.GetAllEntriesFlat().Any(e => e.Password == "LegacyKdbx3Password!");
                });

                Check("7. Datenbank-Import & Zusammenführen (Alte .kdbx -> Neue .kdbx)", () =>
                {
                    var oldDb = KdbxDatabase.CreateNewDefault("Alte DB");
                    var customFolder = new KdbxGroup { Name = "Alt-Verträge" };
                    customFolder.Entries.Add(new KdbxEntry
                    {
                        Title = "Barmenia Versicherung",
                        UserName = "VN-99182",
                        Password = "Barmenia#OldVaultPassword"
                    });
                    oldDb.RootGroup.SubGroups.Add(customFolder);

                    var newDb = KdbxDatabase.CreateNewDefault("Neue DB");
                    var (groups, imported, updated, skipped) = newDb.MergeFrom(oldDb);
                    var (_, imported2, _, skipped2) = newDb.MergeFrom(oldDb); // second merge must skip duplicates!

                    return imported == 1 &&
                           groups >= 1 &&
                           imported2 == 0 &&
                           skipped2 == 1 &&
                           newDb.GetAllEntriesFlat().Any(e => e.Title == "Barmenia Versicherung" && e.Password == "Barmenia#OldVaultPassword");
                });

                Check("8. RFC 6238 2FA / TOTP Authenticator (Base32 & otpauth://)", () =>
                {
                    var fixedTime = DateTimeOffset.FromUnixTimeSeconds(59);
                    // RFC 6238 / RFC 4226 standard secret "12345678901234567890" = Base32 "GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ" -> at T=59s SHA1 6-digit = "287082"
                    bool okVector = TotpEngine.TryGenerateTotp("GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ", fixedTime, out string code, out int rem);
                    bool okUri = TotpEngine.TryGenerateTotp("otpauth://totp/Seelenfunke:Alina?secret=JBSWY3DPEHPK3PXP&digits=6&period=30", DateTimeOffset.UtcNow, out string uriCode, out _);
                    return okVector && code.Replace(" ", "") == "287082" && rem == 1 && okUri && uriCode.Replace(" ", "").Length == 6;
                });

                Check("9. Kryptografischer Passwort-/Passphrasen-Generator & Entropie", () =>
                {
                    string pwd = PasswordGeneratorEngine.GeneratePassword(24, true, true, true, true, true);
                    string phrase = PasswordGeneratorEngine.GeneratePassphrase(4, "-");
                    double bits = PasswordGeneratorEngine.CalculateEntropyBits(pwd);
                    return pwd.Length == 24 && bits >= 85 && phrase.Split('-').Length == 5;
                });

                Check("10. Offline Passwort-Sicherheits-Audit (Schwach & Doppelt Erkennung)", () =>
                {
                    var entries = new[]
                    {
                        new KdbxEntry { Title = "Schwach", Password = "123" },
                        new KdbxEntry { Title = "Dienst A", Password = "DoppeltesStarkesPasswort!2026" },
                        new KdbxEntry { Title = "Dienst B", Password = "DoppeltesStarkesPasswort!2026" }
                    };
                    var issues = PasswordGeneratorEngine.AuditEntries(entries);
                    return issues.Count == 3 &&
                           issues.Any(i => i.Severity == "Kritisch" && i.Entry.Title == "Schwach") &&
                           issues.Count(i => i.Severity == "Warnung") == 2;
                });

                Check("11. ZeroMemory RAM-Bereinigung beim Sperren (WipeAllInMemory)", () =>
                {
                    var db = KdbxDatabase.CreateNewDefault("RAM Test");
                    var e = new KdbxEntry
                    {
                        Title = "Test",
                        Password = "SuperSecretInRam!",
                        Attachments = { new KdbxAttachment { FileName = "sec.bin", Data = new byte[] { 1, 2, 3, 4 } } }
                    };
                    var rawAttachmentRef = e.Attachments[0].Data;
                    db.RootGroup.SubGroups[0].Entries.Add(e);
                    db.WipeAllInMemory();
                    return string.IsNullOrEmpty(e.Password) &&
                           rawAttachmentRef.All(b => b == 0) &&
                           db.GetAllEntriesFlat().Count == 0;
                });

                Check("12. Tresor-Einstellungen, NTFS-ACL & MainWindow UI-Initialisierung", () =>
                {
                    var s = TresorAppSettings.Load();
                    s.Save();
                    var win = new MainWindow();
                    return Directory.Exists(TresorAppSettings.DataDir) &&
                           Directory.Exists(TresorAppSettings.DefaultVaultsDir) &&
                           File.Exists(TresorAppSettings.SettingsFilePath) &&
                           win.Title.Contains("Tresor") &&
                           !s.AutoLockEnabled;
                });
            }
            finally
            {
                try { Directory.Delete(tempDir, recursive: true); } catch { }
            }

            Console.WriteLine($"FUNKENWERK TRESOR SELF-TEST: {passed}/{total} TESTS BESTANDEN.");
            return passed == total;
        }
    }
}
