using System;
using System.Collections.Generic;
using System.IO;
using System.Linq;
using System.Runtime.InteropServices;
using System.Security.AccessControl;
using System.Security.Cryptography;
using System.Security.Principal;
using System.Text;
using System.Windows;
using System.Windows.Interop;

namespace FunkenwerkTresor
{
    public static class TresorSelfTestRunner
    {
        [DllImport("user32.dll", SetLastError = true)]
        private static extern bool OpenClipboard(IntPtr hWndNewOwner);

        [DllImport("user32.dll", SetLastError = true)]
        private static extern bool EmptyClipboard();

        [DllImport("user32.dll", SetLastError = true)]
        private static extern bool CloseClipboard();
        private static bool VerifyStrictNtfsFileAcl(string filePath)
        {
            if (!File.Exists(filePath)) return false;
            var fi = new FileInfo(filePath);
            FileSecurity acl = fi.GetAccessControl();
            if (!acl.AreAccessRulesProtected)
                return false;

            var currentUserSid = WindowsIdentity.GetCurrent().User;
            if (currentUserSid == null) return false;

            var worldSid = new SecurityIdentifier(WellKnownSidType.WorldSid, null);
            var builtinUsersSid = new SecurityIdentifier(WellKnownSidType.BuiltinUsersSid, null);
            var authenticatedUsersSid = new SecurityIdentifier(WellKnownSidType.AuthenticatedUserSid, null);

            bool hasCurrentUserAllow = false;
            var rules = acl.GetAccessRules(includeExplicit: true, includeInherited: true, typeof(SecurityIdentifier));
            foreach (FileSystemAccessRule rule in rules)
            {
                if (rule.IdentityReference is SecurityIdentifier sid)
                {
                    if (sid.Equals(worldSid) || sid.Equals(builtinUsersSid) || sid.Equals(authenticatedUsersSid))
                        return false;
                    if (sid.Equals(currentUserSid) &&
                        rule.AccessControlType == AccessControlType.Allow &&
                        (rule.FileSystemRights & FileSystemRights.ReadData) != 0)
                    {
                        hasCurrentUserAllow = true;
                    }
                }
            }
            return hasCurrentUserAllow;
        }

        private static int ReadClipboardDwordFlag(IDataObject dataObj, string format)
        {
            if (!dataObj.GetDataPresent(format)) return -999;
            object? raw = dataObj.GetData(format);
            if (raw is MemoryStream ms)
            {
                byte[] bytes = ms.ToArray();
                if (bytes.Length >= 4)
                    return BitConverter.ToInt32(bytes, 0);
            }
            else if (raw is byte[] arr && arr.Length >= 4)
            {
                return BitConverter.ToInt32(arr, 0);
            }
            else if (raw is int i)
            {
                return i;
            }
            return -998;
        }

        public static bool RunEndToEndSelfTest()
        {
            try { Console.OutputEncoding = Encoding.UTF8; } catch { }
            var checks = new List<(string Name, Func<bool> Test)>();
            void AddCheck(string name, Func<bool> test) => checks.Add((name, test));

            string tempDir = Path.Combine(Path.GetTempPath(), "fw_tresor_selftest_" + Guid.NewGuid().ToString("N").Substring(0, 10));
            Directory.CreateDirectory(tempDir);
            TresorAppSettings.TestOverrideBaseDir = tempDir;

            int passed = 0;
            try
            {
                AddCheck("1. Marken-Icons (funkenwerk_tresor.ico & funkenwerk_tresor.png)", () =>
                {
                    var ico = Application.GetResourceStream(new Uri("pack://application:,,,/funkenwerk_tresor.ico"));
                    var png = Application.GetResourceStream(new Uri("pack://application:,,,/funkenwerk_tresor.png"));
                    return ico != null && ico.Stream.Length > 1000 && png != null && png.Stream.Length > 1000;
                });

                AddCheck("2. KDBX 4.0 (Argon2id + AES-256 + ChaCha20 Inner-Stream + Strukturbaum + Anhang) Roundtrip", () =>
                {
                    var db = KdbxDatabase.CreateNewDefault("DB");
                    db.Argon2MemoryBytes = 16UL * 1024UL * 1024UL;
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
                    entry.CustomStrings["IBAN"] = new KdbxCustomString { Value = "DE00100100100000", IsProtected = true };
                    entry.Attachments.Add(new KdbxAttachment
                    {
                        FileName = "../../unsafe/path/tan_brief.txt",
                        Data = Encoding.UTF8.GetBytes("Geheimer Anhang im KDBX 4 Inner-Header")
                    });
                    sfGroup.Entries.Add(entry);

                    string kdbxPath = Path.Combine(tempDir, "test_v4_argon2id.kdbx");
                    KdbxCryptoEngine.SaveKdbxFile(db, kdbxPath, "MasterPass#Seelenfunke2026!");

                    var loaded = KdbxCryptoEngine.OpenKdbxFile(kdbxPath, "MasterPass#Seelenfunke2026!");
                    var loadedEntry = loaded.GetAllEntriesFlat().FirstOrDefault(e => e.Title == "FYRST Geschäftskonto");

                    return loaded.FormatMajor == 4 &&
                           loaded.FormatMinor == 0 &&
                           loaded.KdfAlgorithm == "Argon2id" &&
                           loaded.CipherAlgorithm == "AES-256" &&
                           emailsGroup.SubGroups.Count == 2 &&
                           loaded.GetDisplayGroups().Count >= 30 &&
                           KeePassIconRenderer.GetSelectableIcons().Count >= 18 &&
                           loadedEntry != null &&
                           loadedEntry.Password == "Äöü!StrengGeheim#2026$Tresor" &&
                           loadedEntry.CustomStrings.TryGetValue("IBAN", out var iban) && iban.IsProtected && iban.Value == "DE00100100100000" &&
                           loadedEntry.IsFavorite &&
                           loadedEntry.HasTotp &&
                           loadedEntry.Attachments.Count == 1 &&
                           loadedEntry.Attachments[0].FileName == "tan_brief.txt" &&
                           Encoding.UTF8.GetString(loadedEntry.Attachments[0].Data) == "Geheimer Anhang im KDBX 4 Inner-Header";
                });

                AddCheck("3. KDBX 4.1 Minor-Version Erhalt beim Speichern (FormatMinor == 1)", () =>
                {
                    var db = KdbxDatabase.CreateNewDefault("KDBX41");
                    db.FormatMajor = 4;
                    db.FormatMinor = 1;
                    db.Argon2MemoryBytes = 16UL * 1024UL * 1024UL;
                    db.RootGroup.Entries.Add(new KdbxEntry { Title = "V4.1 Entry", Password = "V41Password!2026" });

                    byte[] bytes = KdbxCryptoEngine.WriteKdbx4Bytes(db, "V41Master!");
                    var loaded = KdbxCryptoEngine.OpenKdbxBytes(bytes, "V41Master!");
                    return loaded.FormatMajor == 4 &&
                           loaded.FormatMinor == 1 &&
                           loaded.GetAllEntriesFlat().Any(e => e.Password == "V41Password!2026");
                });

                AddCheck("4. KDBX 4.0 Outer-Cipher ChaCha20 (RFC 7539) Roundtrip", () =>
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

                AddCheck("5. Legacy KDBX 3.1 (AES-KDF + Salsa20 + Twofish Outer) Lesen & sauberes KDBX 4 AES-256 Upgrade", () =>
                {
                    var legacyDb = KdbxDatabase.CreateNewDefault("Alte Twofish DB");
                    legacyDb.RootGroup.SubGroups[0].Entries.Add(new KdbxEntry
                    {
                        Title = "Altes T-Online Postfach",
                        UserName = "alina.stone@t-online.de",
                        Password = "LegacyTwofishSalsa20Password!"
                    });

                    byte[] kdbx3Twofish = KdbxCryptoEngine.WriteLegacyKdbx3BytesForTest(legacyDb, "OldPass123", 4000, useTwofishOuter: true);
                    var openedLegacy = KdbxCryptoEngine.OpenKdbxBytes(kdbx3Twofish, "OldPass123");
                    bool wasTwofishV3 = openedLegacy.FormatMajor == 3 &&
                                        openedLegacy.KdfAlgorithm == "AES-KDF" &&
                                        openedLegacy.CipherAlgorithm == "Twofish" &&
                                        !openedLegacy.IsModernArgon2Kdbx4;

                    openedLegacy.Argon2MemoryBytes = 16UL * 1024UL * 1024UL;
                    byte[] upgradedBytes = KdbxCryptoEngine.WriteKdbx4Bytes(openedLegacy, "OldPass123");
                    var reopenedV4 = KdbxCryptoEngine.OpenKdbxBytes(upgradedBytes, "OldPass123");

                    return wasTwofishV3 &&
                           openedLegacy.CipherAlgorithm == "AES-256" &&
                           reopenedV4.IsModernArgon2Kdbx4 &&
                           reopenedV4.CipherAlgorithm == "AES-256" &&
                           reopenedV4.GetAllEntriesFlat().Any(e => e.Password == "LegacyTwofishSalsa20Password!");
                });

                AddCheck("6. Falsches Master-Passwort & HMAC-Block-Manipulationsschutz (Fail-Closed)", () =>
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

                AddCheck("7. 2-Faktor Composite-Key: Master-Passwort + .keyx XML v2.0 Schlüsseldatei", () =>
                {
                    string keyfilePath = Path.Combine(tempDir, "master_v2.keyx");
                    KdbxCryptoEngine.GenerateKeyfileXmlV2(keyfilePath);

                    var db = KdbxDatabase.CreateNewDefault("Keyfile v2 Tresor");
                    db.Argon2MemoryBytes = 16UL * 1024UL * 1024UL;
                    db.RootGroup.SubGroups[0].Entries.Add(new KdbxEntry
                    {
                        Title = "ELSTER Zertifikat",
                        Password = "Elster#Keyfile#Protected"
                    });

                    string kdbxPath = Path.Combine(tempDir, "with_keyfile_v2.kdbx");
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

                AddCheck("8. XML KeyFile v1.0 (Base64 32-Byte) Kompatibilität", () =>
                {
                    byte[] rawKey = RandomNumberGenerator.GetBytes(32);
                    string b64 = Convert.ToBase64String(rawKey);
                    string v1Xml = $"<?xml version=\"1.0\" encoding=\"utf-8\"?><KeyFile><Meta><Version>1.00</Version></Meta><Key><Data>{b64}</Data></Key></KeyFile>";
                    string kfPath = Path.Combine(tempDir, "legacy_v1.keyx");
                    File.WriteAllText(kfPath, v1Xml, Encoding.UTF8);

                    var db = KdbxDatabase.CreateNewDefault("Keyfile v1");
                    db.Argon2MemoryBytes = 16UL * 1024UL * 1024UL;
                    db.RootGroup.Entries.Add(new KdbxEntry { Title = "V1 Key", Password = "V1SecretPassword!" });
                    byte[] kdbxBytes = KdbxCryptoEngine.WriteKdbx4Bytes(db, "PassV1", kfPath);
                    var loaded = KdbxCryptoEngine.OpenKdbxBytes(kdbxBytes, "PassV1", kfPath);
                    return loaded.GetAllEntriesFlat().Any(e => e.Password == "V1SecretPassword!");
                });

                AddCheck("9. Binäre 32-Byte & 64-Hex-ASCII Schlüsseldateien (.key)", () =>
                {
                    byte[] raw32 = RandomNumberGenerator.GetBytes(32);
                    raw32[0] = (byte)'<'; // Even if byte 0 is '<', 32-byte raw .key must still work as raw 32-byte key!
                    string binKeyPath = Path.Combine(tempDir, "raw32.key");
                    File.WriteAllBytes(binKeyPath, raw32);

                    string hexKeyPath = Path.Combine(tempDir, "hex64.key");
                    File.WriteAllText(hexKeyPath, Convert.ToHexString(raw32), Encoding.ASCII);

                    // Both represent the exact same 32-byte key component!
                    byte[] ck1 = KdbxCryptoEngine.BuildCompositeKey("SamePass", binKeyPath);
                    byte[] ck2 = KdbxCryptoEngine.BuildCompositeKey("SamePass", hexKeyPath);
                    return ck1.SequenceEqual(ck2);
                });

                AddCheck("10. Beschädigte .keyx Schlüsseldateien (Fail-Closed InvalidDataException statt SHA-256 Fallback)", () =>
                {
                    string[] badKeyxPayloads =
                    {
                        "<KeyFile><BrokenXml",
                        "<?xml version=\"1.0\"?><!DOCTYPE KeyFile [<!ENTITY xxe \"test\">]><KeyFile><Meta><Version>2.0</Version></Meta><Key><Data>00</Data></Key></KeyFile>",
                        "<?xml version=\"1.0\"?><KeyFile><Meta><Version>2.0</Version></Meta><Key><Data Hash=\"1234\">" + new string('A', 64) + "</Data></Key></KeyFile>",
                        "<?xml version=\"1.0\"?><KeyFile><Meta><Version>2.0</Version></Meta><Key><Data Hash=\"DEADBEEF\">" + new string('A', 64) + "</Data></Key></KeyFile>",
                        "<?xml version=\"1.0\"?><KeyFile><Meta><Version>2.0</Version></Meta><Key><Data Hash=\"00000000\">NOT_HEX_DATA</Data></Key></KeyFile>",
                        "<?xml version=\"1.0\"?><KeyFile><Meta><Version>1.00</Version></Meta><Key><Data>" + Convert.ToBase64String(new byte[16]) + "</Data></Key></KeyFile>",
                        "not xml at all inside a .keyx file"
                    };

                    for (int i = 0; i < badKeyxPayloads.Length; i++)
                    {
                        string badPath = Path.Combine(tempDir, $"corrupt_{i}.keyx");
                        File.WriteAllText(badPath, badKeyxPayloads[i], Encoding.UTF8);
                        bool rejected = false;
                        try
                        {
                            KdbxCryptoEngine.BuildCompositeKey("TestPass", badPath);
                        }
                        catch (InvalidDataException)
                        {
                            rejected = true;
                        }
                        if (!rejected)
                            return false;
                    }
                    return true;
                });

                AddCheck("11. Keyfile-Only Tresor (Leeres Passwort \"\" + .keyx) & DPAPI OpenedVaultSession Speichern/Laden", () =>
                {
                    string kfPath = Path.Combine(tempDir, "keyfile_only.keyx");
                    KdbxCryptoEngine.GenerateKeyfileXmlV2(kfPath);

                    string dbPath = Path.Combine(tempDir, "keyfile_only_vault.kdbx");
                    var db = KdbxDatabase.CreateNewDefault("KeyfileOnlyDB");
                    db.Argon2MemoryBytes = 16UL * 1024UL * 1024UL;
                    db.RootGroup.Entries.Add(new KdbxEntry { Title = "Initial", Password = "KeyfileOnlySecret#1" });
                    KdbxCryptoEngine.SaveKdbxFile(db, dbPath, "", kfPath);

                    // Open with OpenKdbxFileAndDeriveCompositeKey ("", kfPath), store in DPAPI OpenedVaultSession, mutate & SaveDatabaseFile()
                    var opened = KdbxCryptoEngine.OpenKdbxFileAndDeriveCompositeKey(dbPath, "", kfPath, out byte[] ck);
                    var session = new OpenedVaultSession { Database = opened, KeyfilePath = kfPath };
                    session.SetCompositeKey(ck);
                    CryptographicOperations.ZeroMemory(ck);

                    if (!session.HasValidCredentials)
                        return false;

                    opened.RootGroup.Entries.Add(new KdbxEntry { Title = "AddedInKeyfileOnlySession", Password = "KeyfileOnlySecret#2" });
                    opened.IsDirty = true;
                    session.SaveDatabaseFile();
                    session.WipeSession();

                    var reloaded = KdbxCryptoEngine.OpenKdbxFile(dbPath, "", kfPath);
                    return !session.HasValidCredentials &&
                           reloaded.GetAllEntriesFlat().Count == 2 &&
                           reloaded.GetAllEntriesFlat().Any(e => e.Title == "AddedInKeyfileOnlySession" && e.Password == "KeyfileOnlySecret#2");
                });

                AddCheck("12. Verlustfreier KDBX-Roundtrip für <History> (inkl. Protected Passwords), <DeletedObjects> & <CustomData>", () =>
                {
                    // Build a KDBX 4 database whose XML contains <History> with Protected="True" password, <DeletedObjects>, and <CustomData> on Meta/Group/Entry
                    var db = KdbxDatabase.CreateNewDefault("NonDestructiveDB");
                    db.Argon2MemoryBytes = 16UL * 1024UL * 1024UL;
                    db.RawCustomDataXml = "<CustomData><Item><Key>PluginMetaKey</Key><Value>MetaVal42</Value></Item></CustomData>";
                    db.RawDeletedObjectsXml = "<DeletedObjects><DeletedObject><UUID>AAAAAAAAAAAAAAAAAAAAAA==</UUID><DeletionTime>2026-01-01T00:00:00Z</DeletionTime></DeletedObject></DeletedObjects>";

                    var grp = db.RootGroup.SubGroups[0];
                    grp.RawCustomDataXml = "<CustomData><Item><Key>GroupPluginKey</Key><Value>GroupVal99</Value></Item></CustomData>";

                    var entry = new KdbxEntry
                    {
                        Title = "SSH Key Prod",
                        UserName = "root",
                        Password = "CurrentPassword#2026",
                        RawCustomDataXml = "<CustomData><Item><Key>KeeAgent</Key><Value>Enabled</Value></Item></CustomData>",
                        RawHistoryXml = "<History><Entry><UUID>BBBBBBBBBBBBBBBBBBBBBB==</UUID><IconID>0</IconID><String><Key>Title</Key><Value>SSH Key Prod</Value></String><String><Key>Password</Key><Value Protected=\"True\">HistoricalOldPassword#2024!</Value></String></Entry></History>"
                    };
                    grp.Entries.Add(entry);

                    // Save #1 (encrypts History password with random ChaCha20 innerStreamKey #1)
                    byte[] pass1 = KdbxCryptoEngine.WriteKdbx4Bytes(db, "RoundtripMaster!");
                    var loaded1 = KdbxCryptoEngine.OpenKdbxBytes(pass1, "RoundtripMaster!");

                    // Save #2 (re-encrypts History password with brand new ChaCha20 innerStreamKey #2!)
                    byte[] pass2 = KdbxCryptoEngine.WriteKdbx4Bytes(loaded1, "RoundtripMaster!");
                    var loaded2 = KdbxCryptoEngine.OpenKdbxBytes(pass2, "RoundtripMaster!");

                    var loadedEntry = loaded2.GetAllEntriesFlat().First(e => e.Title == "SSH Key Prod");
                    var loadedGrp = loaded2.RootGroup.SubGroups[0];

                    bool historyPreservedAndDecrypted = loadedEntry.RawHistoryXml != null &&
                        loadedEntry.RawHistoryXml.Contains("HistoricalOldPassword#2024!", StringComparison.Ordinal) &&
                        loadedEntry.Password == "CurrentPassword#2026";

                    bool customDataPreserved =
                        (loaded2.RawCustomDataXml?.Contains("MetaVal42", StringComparison.Ordinal) ?? false) &&
                        (loadedGrp.RawCustomDataXml?.Contains("GroupVal99", StringComparison.Ordinal) ?? false) &&
                        (loadedEntry.RawCustomDataXml?.Contains("KeeAgent", StringComparison.Ordinal) ?? false);

                    bool deletedObjectsPreserved =
                        loaded2.RawDeletedObjectsXml?.Contains("DeletedObject", StringComparison.Ordinal) ?? false;

                    return historyPreservedAndDecrypted && customDataPreserved && deletedObjectsPreserved;
                });

                AddCheck("13. KDF-Bounds-Prüfung gegen DoS & Integer-Truncation (Argon2 & AES-KDF Grenzwerte)", () =>
                {
                    byte[] key = new byte[32];
                    byte[] salt = new byte[32];
                    bool badMemHigh = false, badMemLow = false, badIter = false, badPar = false, badAesRounds = false;

                    try { KdbxCryptoEngine.TransformKeyArgon2(key, salt, 2, 2, 2 * 1024 * 1024, 2); } catch (InvalidDataException) { badMemHigh = true; }
                    try { KdbxCryptoEngine.TransformKeyArgon2(key, salt, 2, 2, 4, 2); } catch (InvalidDataException) { badMemLow = true; }
                    try { KdbxCryptoEngine.TransformKeyArgon2(key, salt, 2, 5000, 16384, 2); } catch (InvalidDataException) { badIter = true; }
                    try { KdbxCryptoEngine.TransformKeyArgon2(key, salt, 2, 2, 16384, 128); } catch (InvalidDataException) { badPar = true; }
                    try { KdbxCryptoEngine.TransformKeyAesKdf(key, salt, 500_000_000UL); } catch (InvalidDataException) { badAesRounds = true; }

                    return badMemHigh && badMemLow && badIter && badPar && badAesRounds;
                });

                AddCheck("14. MergeFrom: CustomIcons, CustomIconUuidHex, Ablaufdatum & Dubletten-Schutz", () =>
                {
                    string iconUuid = KdbxEntry.NewUuidHex();
                    byte[] fakePng = { 0x89, 0x50, 0x4E, 0x47, 0x0D, 0x0A, 0x1A, 0x0A, 1, 2, 3, 4 };

                    var oldDb = KdbxDatabase.CreateNewDefault("Alte DB");
                    oldDb.CustomIcons[iconUuid] = fakePng;
                    var customFolder = new KdbxGroup { Name = "Alt-Verträge", CustomIconUuidHex = iconUuid };
                    DateTime expUtc = new DateTime(2028, 12, 31, 0, 0, 0, DateTimeKind.Utc);
                    customFolder.Entries.Add(new KdbxEntry
                    {
                        Title = "Barmenia Versicherung",
                        UserName = "VN-99182",
                        Password = "Barmenia#OldVaultPassword",
                        CustomIconUuidHex = iconUuid,
                        Expires = true,
                        ExpiresUtc = expUtc
                    });
                    oldDb.RootGroup.SubGroups.Add(customFolder);

                    var newDb = KdbxDatabase.CreateNewDefault("Neue DB");
                    var (groups, imported, _, _) = newDb.MergeFrom(oldDb);
                    var (_, imported2, _, skipped2) = newDb.MergeFrom(oldDb);

                    var mergedEntry = newDb.GetAllEntriesFlat().FirstOrDefault(e => e.Title == "Barmenia Versicherung");
                    var mergedGroup = newDb.GetDisplayGroups().FirstOrDefault(g => g.Name == "Alt-Verträge");

                    return imported == 1 &&
                           groups >= 1 &&
                           imported2 == 0 &&
                           skipped2 == 1 &&
                           newDb.CustomIcons.ContainsKey(iconUuid) &&
                           mergedGroup != null && mergedGroup.CustomIconUuidHex == iconUuid &&
                           mergedEntry != null && mergedEntry.CustomIconUuidHex == iconUuid &&
                           mergedEntry.Expires && mergedEntry.ExpiresUtc == expUtc;
                });

                AddCheck("15. RFC 6238 TOTP Vektoren (SHA1 / SHA256 / SHA512) & strikte Base32-Validierung", () =>
                {
                    var t59 = DateTimeOffset.FromUnixTimeSeconds(59);
                    // RFC 6238 Appendix B test keys:
                    // SHA1   20-byte ASCII "12345678901234567890" -> Base32 GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ -> 8-digit at T=59: 94287082 (6-digit: 287082)
                    // SHA256 32-byte ASCII "12345678901234567890123456789012" -> Base32 GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQGEZA==== -> 8-digit at T=59: 46119246
                    // SHA512 64-byte ASCII "1234567890..." -> 8-digit at T=59: 90693936
                    string b32Sha1 = "GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ";
                    string b32Sha256 = "GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQGEZA====";
                    string b32Sha512 = "GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQGEZDGNA=";

                    bool ok1 = TotpEngine.TryGenerateTotp(b32Sha1, t59, out string c1, out int rem) && c1.Replace(" ", "") == "287082" && rem == 1;
                    bool ok256 = TotpEngine.TryGenerateTotp($"otpauth://totp/Test?secret={Uri.EscapeDataString(b32Sha256)}&digits=8&algorithm=SHA256", t59, out string c256, out _) && c256.Replace(" ", "") == "46119246";
                    bool ok512 = TotpEngine.TryGenerateTotp($"otpauth://totp/Test?secret={Uri.EscapeDataString(b32Sha512)}&digits=8&algorithm=SHA512", t59, out string c512, out _) && c512.Replace(" ", "") == "90693936";

                    bool validAccepted = TotpEngine.IsValidSecretOrUri("JBSW Y3DP EHPK 3PXP") &&
                                         TotpEngine.IsValidSecretOrUri("otpauth://totp/SF:Alina?secret=JBSWY3DPEHPK3PXP&digits=6&algorithm=SHA1");
                    bool invalidRejected = !TotpEngine.IsValidSecretOrUri("1111888899990000") &&
                                           !TotpEngine.IsValidSecretOrUri("ABC") &&
                                           !TotpEngine.IsValidSecretOrUri("otpauth://totp/SF:Alina?secret=INVALID!@#");

                    return ok1 && ok256 && ok512 && validAccepted && invalidRejected;
                });

                AddCheck("16. AvatarInitial Surrogate-Pair Sicherheit (StringInfo) & Passwort-Generator Entropie", () =>
                {
                    var emojiEntry = new KdbxEntry { Title = "🔐 Tresor" };
                    string initial = emojiEntry.AvatarInitial;
                    bool validUtf16 = !initial.Any(char.IsSurrogate) || (initial.Length >= 2 && char.IsSurrogatePair(initial, 0));

                    string pwd = PasswordGeneratorEngine.GeneratePassword(24, true, true, true, true, true);
                    string phrase = PasswordGeneratorEngine.GeneratePassphrase(4, "-");
                    double bits = PasswordGeneratorEngine.CalculateEntropyBits(pwd);

                    return validUtf16 && initial == "🔐" && pwd.Length == 24 && bits >= 85 && phrase.Split('-').Length == 5;
                });

                AddCheck("17. Offline Sicherheits-Audit (Schwach, Mehrfach verwendet & Abgelaufen)", () =>
                {
                    var entries = new[]
                    {
                        new KdbxEntry { Title = "Schwach", Password = "short" },
                        new KdbxEntry { Title = "Dienst A", Password = "DoppeltesStarkesPasswort!2026" },
                        new KdbxEntry { Title = "Dienst B", Password = "DoppeltesStarkesPasswort!2026", Expires = true, ExpiresUtc = DateTime.UtcNow.AddDays(-2) }
                    };
                    var issues = PasswordGeneratorEngine.AuditEntries(entries);
                    return issues.Count == 4 &&
                           issues.Count(i => i.Category == "Weak" && i.Entry.Title == "Schwach") == 1 &&
                           issues.Count(i => i.Category == "Reused") == 2 &&
                           issues.Count(i => i.Category == "Expired" && i.Entry.Title == "Dienst B") == 1;
                });

                AddCheck("18. ZeroMemory RAM-Bereinigung beim Sperren (WipeAllInMemory & KeePassIconRenderer)", () =>
                {
                    var db = KdbxDatabase.CreateNewDefault("RAM Test");
                    byte[] iconBytes = { 10, 20, 30, 40 };
                    db.CustomIcons[KdbxEntry.NewUuidHex()] = iconBytes;
                    var e = new KdbxEntry
                    {
                        Title = "Test",
                        Password = "SuperSecretInRam!",
                        RawHistoryXml = "<History/>",
                        RawCustomDataXml = "<CustomData/>",
                        Attachments = { new KdbxAttachment { FileName = "sec.bin", Data = new byte[] { 1, 2, 3, 4 } } }
                    };
                    var rawAttachmentRef = e.Attachments[0].Data;
                    db.RootGroup.SubGroups[0].Entries.Add(e);
                    db.WipeAllInMemory();
                    KeePassIconRenderer.ClearCustomCache();

                    return string.IsNullOrEmpty(e.Password) &&
                           e.RawHistoryXml == null &&
                           e.RawCustomDataXml == null &&
                           rawAttachmentRef.All(b => b == 0) &&
                           iconBytes.All(b => b == 0) &&
                           db.GetAllEntriesFlat().Count == 0;
                });

                AddCheck("19. NTFS-ACL Härtung (.kdbx / .keyx / settings.json) & Standard-Einstellungen (AutoLock=false)", () =>
                {
                    var defaults = new TresorAppSettings();
                    if (defaults.AutoLockEnabled != false ||
                        defaults.ScreenCaptureProtection != true ||
                        defaults.LockOnWindowsLock != true)
                    {
                        return false;
                    }

                    var s = TresorAppSettings.Load();
                    s.Save();

                    string aclKdbx = Path.Combine(tempDir, "test_v4_argon2id.kdbx");
                    string aclKeyx = Path.Combine(tempDir, "master_v2.keyx");

                    return VerifyStrictNtfsFileAcl(TresorAppSettings.SettingsFilePath) &&
                           VerifyStrictNtfsFileAcl(aclKdbx) &&
                           VerifyStrictNtfsFileAcl(aclKeyx);
                });

                AddCheck("20. STA Clipboard-Guard (Win+V & Cloud-Clipboard Ausschluss-Flags + Fail-Closed Auto-Clear)", () =>
                {
                    string testSecret = "FwSelfTestClipSecret#" + Guid.NewGuid().ToString("N").Substring(0, 8);
                    var builtObj = TresorSecurityGuard.BuildProtectedClipboardDataObject(testSecret);
                    bool formatsOk =
                        string.Equals(builtObj.GetData(DataFormats.UnicodeText) as string, testSecret, StringComparison.Ordinal) &&
                        ReadClipboardDwordFlag(builtObj, "ExcludeClipboardContentFromMonitorProcessing") == 1 &&
                        ReadClipboardDwordFlag(builtObj, "CanIncludeInClipboardHistory") == 0 &&
                        ReadClipboardDwordFlag(builtObj, "CanUploadToCloudClipboard") == 0;
                    if (!formatsOk)
                    {
                        throw new InvalidOperationException(
                            $"DataObject format check failed: exMon={ReadClipboardDwordFlag(builtObj, "ExcludeClipboardContentFromMonitorProcessing")}, " +
                            $"hist={ReadClipboardDwordFlag(builtObj, "CanIncludeInClipboardHistory")}, " +
                            $"cloud={ReadClipboardDwordFlag(builtObj, "CanUploadToCloudClipboard")}");
                    }

                    bool copied = TresorSecurityGuard.CopySensitiveToClipboard(testSecret, clearSeconds: 12);
                    if (!copied)
                    {
                        // Verify whether OS WindowStation / JobObject (e.g. JOB_OBJECT_UILIMIT_WRITECLIPBOARD) blocks clipboard writes
                        IntPtr ownerHwnd = TresorSecurityGuard.EnsureClipboardOwnerHwnd();
                        bool osAllowsClipboardWrite = false;
                        int lastErr = 0;
                        if (OpenClipboard(ownerHwnd))
                        {
                            try
                            {
                                osAllowsClipboardWrite = EmptyClipboard();
                                if (!osAllowsClipboardWrite)
                                    lastErr = Marshal.GetLastWin32Error();
                            }
                            finally
                            {
                                CloseClipboard();
                            }
                        }
                        else
                        {
                            lastErr = Marshal.GetLastWin32Error();
                        }

                        if (osAllowsClipboardWrite)
                        {
                            throw new InvalidOperationException($"CopySensitiveToClipboard returned false despite writable OS clipboard (lastErr={lastErr}).");
                        }

                        // Restricted JobObject / locked WindowStation: must fail closed without arming stale secret or countdown
                        return !TresorSecurityGuard.HasActiveClipboardSecret &&
                               !TresorSecurityGuard.TryGetClipboardRemainingSeconds(out _, out _);
                    }

                    int excludeMonitor = -999, allowHistory = -999, allowCloud = -999;
                    bool textMatched = false;

                    try
                    {
                        var dataObj = Clipboard.GetDataObject();
                        if (dataObj != null)
                        {
                            excludeMonitor = ReadClipboardDwordFlag(dataObj, "ExcludeClipboardContentFromMonitorProcessing");
                            allowHistory = ReadClipboardDwordFlag(dataObj, "CanIncludeInClipboardHistory");
                            allowCloud = ReadClipboardDwordFlag(dataObj, "CanUploadToCloudClipboard");
                            textMatched = string.Equals(dataObj.GetData(DataFormats.UnicodeText) as string, testSecret, StringComparison.Ordinal);
                        }
                    }
                    catch
                    {
                        // OLE GetDataObject unavailable in non-OLE session; verify directly via Win32 clipboard below
                    }

                    if (excludeMonitor != 1 || allowHistory != 0 || allowCloud != 0 || !textMatched)
                    {
                        TresorSecurityGuard.TryReadWin32ClipboardDword("ExcludeClipboardContentFromMonitorProcessing", out excludeMonitor);
                        TresorSecurityGuard.TryReadWin32ClipboardDword("CanIncludeInClipboardHistory", out allowHistory);
                        TresorSecurityGuard.TryReadWin32ClipboardDword("CanUploadToCloudClipboard", out allowCloud);
                        if (TresorSecurityGuard.TryReadWin32ClipboardUnicodeText(out string win32Text))
                            textMatched = string.Equals(win32Text, testSecret, StringComparison.Ordinal);
                    }

                    bool countdownArmed = TresorSecurityGuard.TryGetClipboardRemainingSeconds(out double rem, out _) && rem > 0;

                    TresorSecurityGuard.ClearClipboardNow();
                    bool cleared = !TresorSecurityGuard.HasActiveClipboardSecret &&
                                   TresorSecurityGuard.TryReadWin32ClipboardUnicodeText(out string afterClear) &&
                                   !string.Equals(afterClear, testSecret, StringComparison.Ordinal);

                    if (!(textMatched && excludeMonitor == 1 && allowHistory == 0 && allowCloud == 0 && countdownArmed && cleared))
                    {
                        throw new InvalidOperationException(
                            $"textMatched={textMatched}, excludeMonitor={excludeMonitor}, allowHistory={allowHistory}, allowCloud={allowCloud}, countdownArmed={countdownArmed}, cleared={cleared}");
                    }

                    return true;
                });

                AddCheck("21. Realisiertes HWND SetWindowDisplayAffinity (WDA_EXCLUDEFROMCAPTURE == 0x11)", () =>
                {
                    var hiddenWin = new Window
                    {
                        Width = 10,
                        Height = 10,
                        WindowStyle = WindowStyle.None,
                        ShowInTaskbar = false,
                        Visibility = Visibility.Hidden
                    };
                    try
                    {
                        IntPtr hwnd = new WindowInteropHelper(hiddenWin).EnsureHandle();
                        if (hwnd == IntPtr.Zero) return false;

                        bool appliedOn = TresorSecurityGuard.ApplyScreenCaptureProtection(hiddenWin, true);
                        bool queriedOn = TresorSecurityGuard.TryQueryWindowDisplayAffinity(hiddenWin, out uint affOn);

                        bool appliedOff = TresorSecurityGuard.ApplyScreenCaptureProtection(hiddenWin, false);
                        bool queriedOff = TresorSecurityGuard.TryQueryWindowDisplayAffinity(hiddenWin, out uint affOff);

                        return appliedOn && queriedOn && affOn == TresorSecurityGuard.WDA_EXCLUDEFROMCAPTURE &&
                               appliedOff && queriedOff && affOff == TresorSecurityGuard.WDA_NONE;
                    }
                    finally
                    {
                        hiddenWin.Close();
                    }
                });

                AddCheck("22. MainWindow XAML-Instanziierung, Boot-Log in DataDir & sauberes Schließen", () =>
                {
                    App.LogBoot("SelfTest Boot-Log Check");
                    string bootLogPath = Path.Combine(TresorAppSettings.DataDir, "funkenwerk_tresor_boot.log");
                    var win = new MainWindow();
                    try
                    {
                        IntPtr hwnd = new WindowInteropHelper(win).EnsureHandle();
                        bool titleOk = win.Title.Contains("Tresor", StringComparison.Ordinal);
                        bool logAclOk = File.Exists(bootLogPath) && VerifyStrictNtfsFileAcl(bootLogPath);
                        return hwnd != IntPtr.Zero && titleOk && logAclOk;
                    }
                    finally
                    {
                        win.Close();
                    }
                });

                foreach (var (name, test) in checks)
                {
                    try
                    {
                        bool ok = test();
                        Console.WriteLine(ok ? $"[PASS] {name}" : $"[FAIL] {name}");
                        if (ok) passed++;
                    }
                    catch (Exception ex)
                    {
                        Console.WriteLine($"[FAIL] {name}: {ex.GetType().Name}: {ex.Message}");
                    }
                }
            }
            finally
            {
                TresorAppSettings.TestOverrideBaseDir = null;
                try { Directory.Delete(tempDir, recursive: true); } catch { }
            }

            Console.WriteLine($"FUNKENWERK TRESOR SELF-TEST: {passed}/{checks.Count} TESTS BESTANDEN.");
            return passed == checks.Count;
        }
    }
}
