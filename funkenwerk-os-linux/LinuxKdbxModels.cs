using System;
using System.Collections.Generic;
using System.Linq;
using System.Security.Cryptography;

namespace FunkenwerkTresor
{
    public static class TresorAppSettings
    {
        public static void LockDownFileAcl(string filePath)
        {
            if (OperatingSystem.IsWindows()) return;
            try
            {
                if (System.IO.File.Exists(filePath))
                    System.IO.File.SetUnixFileMode(filePath, System.IO.UnixFileMode.UserRead | System.IO.UnixFileMode.UserWrite);
            }
            catch { }
        }
    }

    public sealed class KdbxCustomString
    {
        public string Value { get; set; } = "";
        public bool IsProtected { get; set; }
    }

    public sealed class KdbxAttachment
    {
        public string FileName { get; set; } = "";
        public byte[] Data { get; set; } = Array.Empty<byte>();
    }

    public sealed class KdbxEntry
    {
        public string UuidHex { get; set; } = NewUuidHex();
        public string GroupUuidHex { get; set; } = "";
        public string GroupName { get; set; } = "";
        public int IconId { get; set; } = 0;
        public string CustomIconUuidHex { get; set; } = "";
        public Dictionary<string, byte[]>? CustomIconsRef { get; set; }

        public string Title { get; set; } = "";
        public string UserName { get; set; } = "";
        public string Password { get; set; } = "";
        public string Url { get; set; } = "";
        public string Notes { get; set; } = "";
        public string Tags { get; set; } = "";

        public Dictionary<string, KdbxCustomString> CustomStrings { get; set; } = new(StringComparer.Ordinal);
        public List<KdbxAttachment> Attachments { get; set; } = new();

        public DateTime CreatedUtc { get; set; } = DateTime.UtcNow;
        public DateTime ModifiedUtc { get; set; } = DateTime.UtcNow;
        public bool Expires { get; set; }
        public DateTime? ExpiresUtc { get; set; }
        public string? RawHistoryXml { get; set; }
        public string? RawCustomDataXml { get; set; }

        public void WipeSensitiveMemory()
        {
            Password = new string('\0', Password.Length);
            Password = "";
            Notes = "";
            RawHistoryXml = null;
            RawCustomDataXml = null;
            foreach (var kv in CustomStrings)
                kv.Value.Value = "";
            CustomStrings.Clear();
            foreach (var att in Attachments)
                CryptographicOperations.ZeroMemory(att.Data);
            Attachments.Clear();
        }

        public static string NewUuidHex() => Convert.ToHexString(RandomNumberGenerator.GetBytes(16));
    }

    public sealed class KdbxGroup
    {
        public string UuidHex { get; set; } = KdbxEntry.NewUuidHex();
        public string Name { get; set; } = "Allgemein";
        public int IconId { get; set; } = 48;
        public string CustomIconUuidHex { get; set; } = "";
        public Dictionary<string, byte[]>? CustomIconsRef { get; set; }
        public bool IsRootGroup { get; set; }
        public KdbxGroup? Parent { get; set; }
        public string Notes { get; set; } = "";
        public bool IsExpanded { get; set; } = true;
        public DateTime CreatedUtc { get; set; } = DateTime.UtcNow;
        public DateTime ModifiedUtc { get; set; } = DateTime.UtcNow;
        public string? RawCustomDataXml { get; set; }
        public List<KdbxGroup> SubGroups { get; set; } = new();
        public List<KdbxEntry> Entries { get; set; } = new();

        public bool IsRecycleBin(string? recycleBinUuidHex) =>
            (!string.IsNullOrEmpty(recycleBinUuidHex) && string.Equals(UuidHex, recycleBinUuidHex, StringComparison.OrdinalIgnoreCase))
            || string.Equals(Name, "Papierkorb", StringComparison.OrdinalIgnoreCase)
            || string.Equals(Name, "Recycle Bin", StringComparison.OrdinalIgnoreCase);

        public KdbxGroup AddChild(string name, int iconId, bool expanded = true)
        {
            var child = new KdbxGroup
            {
                Name = name,
                IconId = iconId,
                IsExpanded = expanded,
                Parent = this,
                CustomIconsRef = CustomIconsRef
            };
            SubGroups.Add(child);
            return child;
        }
    }

    public sealed class KdbxDatabase
    {
        public string FilePath { get; set; } = "";
        public string DatabaseName { get; set; } = "DB";
        public string DatabaseDescription { get; set; } = "Verschlüsselter KDBX 4 Passwort-Tresor";
        public string GeneratorName { get; set; } = "Funkenwerk Tresor";

        public ushort FormatMajor { get; set; } = 4;
        public ushort FormatMinor { get; set; } = 0;
        public string KdfAlgorithm { get; set; } = "Argon2id";
        public string CipherAlgorithm { get; set; } = "AES-256";

        public ulong Argon2MemoryBytes { get; set; } = 64UL * 1024UL * 1024UL;
        public ulong Argon2Iterations { get; set; } = 3;
        public uint Argon2Parallelism { get; set; } = 2;
        public ulong AesKdfRounds { get; set; } = 600_000;

        public string RecycleBinUuidHex { get; set; } = "";
        public bool RecycleBinEnabled { get; set; } = true;
        public string? RawDeletedObjectsXml { get; set; }
        public string? RawCustomDataXml { get; set; }
        public Dictionary<string, byte[]> CustomIcons { get; set; } = new(StringComparer.OrdinalIgnoreCase);
        public KdbxGroup RootGroup { get; set; } = new() { Name = "DB", IconId = 48, IsRootGroup = true };
        public bool IsDirty { get; set; }

        public string SecurityBadgeText => $"KDBX {FormatMajor}.{FormatMinor} · {KdfAlgorithm} · {CipherAlgorithm}";

        public void WipeAllInMemory()
        {
            void Walk(KdbxGroup g)
            {
                g.RawCustomDataXml = null;
                foreach (var e in g.Entries)
                    e.WipeSensitiveMemory();
                g.Entries.Clear();
                foreach (var sub in g.SubGroups)
                    Walk(sub);
                g.SubGroups.Clear();
            }
            Walk(RootGroup);
            RawDeletedObjectsXml = null;
            RawCustomDataXml = null;
            foreach (var kv in CustomIcons)
            {
                if (kv.Value != null)
                    CryptographicOperations.ZeroMemory(kv.Value);
            }
            CustomIcons.Clear();
        }

        public void RefreshHierarchyReferences()
        {
            RootGroup.IsRootGroup = true;
            RootGroup.Parent = null;
            RootGroup.CustomIconsRef = CustomIcons;

            void Walk(KdbxGroup g)
            {
                g.CustomIconsRef = CustomIcons;
                foreach (var e in g.Entries)
                {
                    e.GroupUuidHex = g.UuidHex;
                    e.GroupName = g.Name;
                    e.CustomIconsRef = CustomIcons;
                }
                foreach (var sub in g.SubGroups)
                {
                    sub.IsRootGroup = false;
                    sub.Parent = g;
                    Walk(sub);
                }
            }
            Walk(RootGroup);
        }

        public static KdbxDatabase CreateNewDefault(string databaseName)
        {
            string rootName = string.IsNullOrWhiteSpace(databaseName) ? "DB" : databaseName.Trim();
            var db = new KdbxDatabase
            {
                DatabaseName = rootName,
                RootGroup = new KdbxGroup { Name = rootName, IconId = 48, IsRootGroup = true, IsExpanded = true }
            };
            var alina = db.RootGroup.AddChild("Alina", 55, expanded: true);
            alina.AddChild("Privat", 61, expanded: true);
            alina.AddChild("Mein-Seelenfunke", 61, expanded: true);
            db.RefreshHierarchyReferences();
            return db;
        }

        public List<KdbxEntry> GetAllEntriesFlat(bool includeRecycleBin = false)
        {
            var list = new List<KdbxEntry>();
            void Walk(KdbxGroup g)
            {
                if (!includeRecycleBin && g.IsRecycleBin(RecycleBinUuidHex)) return;
                foreach (var e in g.Entries)
                {
                    e.GroupUuidHex = g.UuidHex;
                    e.GroupName = g.Name;
                    list.Add(e);
                }
                foreach (var sub in g.SubGroups)
                    Walk(sub);
            }
            Walk(RootGroup);
            return list;
        }
    }
}
