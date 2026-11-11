using System;
using System.Collections.Generic;
using System.Globalization;
using System.IO;
using System.IO.Compression;
using System.Linq;
using System.Security.Cryptography;
using System.Text;
using System.Xml.Linq;
using Org.BouncyCastle.Crypto;
using Org.BouncyCastle.Crypto.Engines;
using Org.BouncyCastle.Crypto.Generators;
using Org.BouncyCastle.Crypto.Modes;
using Org.BouncyCastle.Crypto.Paddings;
using Org.BouncyCastle.Crypto.Parameters;

namespace FunkenwerkTresor
{
    public static class KdbxCryptoEngine
    {
        public const uint KdbxSig1 = 0x9AA2D903;
        public const uint KdbxSig2 = 0xB54BFB67;

        // Cipher UUIDs (16 bytes)
        public static readonly byte[] CipherAes256Uuid = Convert.FromHexString("31C1F2E6BF714350BE5805216AFC5AFF");
        public static readonly byte[] CipherChaCha20Uuid = Convert.FromHexString("D6038A2B8B6F4CB5A524339A31DBB59A");
        public static readonly byte[] CipherTwofishUuid = Convert.FromHexString("AD68F29F576F4BB9A36AD47AF965346C");

        // KDF UUIDs (16 bytes)
        public static readonly byte[] KdfArgon2dUuid = Convert.FromHexString("EF636DDF8C29444B91F7A9A403E30A0C");
        public static readonly byte[] KdfArgon2idUuid = Convert.FromHexString("9E298B1956DB4773B23DFC3EC6F0A1E6");
        public static readonly byte[] KdfAesUuid = Convert.FromHexString("C9D9F39A628A4460BF740D08C18A4FEA");
        public static readonly byte[] KdfAesLegacyUuid = Convert.FromHexString("7C02BB8279A74AC0927D114A00648238");

        private static readonly byte[] Salsa20FixedIv = { 0xE8, 0x30, 0x09, 0x4B, 0x97, 0x20, 0x5D, 0x2A };
        private static readonly DateTime KdbxEpochUtc = new(1, 1, 1, 0, 0, 0, DateTimeKind.Utc);

        // =====================================================================
        // PUBLIC API: OPEN, SAVE (KDBX 4 ARGON2ID), CREATE KEYFILE
        // =====================================================================
        public static KdbxDatabase OpenKdbxFile(string filePath, string? masterPassword, string? keyfilePath = null)
        {
            if (string.IsNullOrWhiteSpace(filePath) || !File.Exists(filePath))
                throw new FileNotFoundException("Die angegebene .kdbx-Tresordatei wurde nicht gefunden.", filePath);

            byte[] rawFile = File.ReadAllBytes(filePath);
            var db = OpenKdbxBytes(rawFile, masterPassword, keyfilePath);
            db.FilePath = Path.GetFullPath(filePath);
            db.IsDirty = false;
            return db;
        }

        public static void SaveKdbxFile(KdbxDatabase db, string filePath, string? masterPassword, string? keyfilePath = null)
        {
            if (db == null) throw new ArgumentNullException(nameof(db));
            if (string.IsNullOrWhiteSpace(filePath)) throw new ArgumentException("Kein Dateipfad für den Tresor angegeben.");

            byte[] bytes = WriteKdbx4Bytes(db, masterPassword, keyfilePath);
            string fullPath = Path.GetFullPath(filePath);
            string? dir = Path.GetDirectoryName(fullPath);
            if (!string.IsNullOrEmpty(dir) && !Directory.Exists(dir))
                Directory.CreateDirectory(dir);

            string tmpPath = fullPath + ".tmp_" + Guid.NewGuid().ToString("N").Substring(0, 6);
            File.WriteAllBytes(tmpPath, bytes);
            File.Move(tmpPath, fullPath, overwrite: true);
            db.FilePath = fullPath;
            db.IsDirty = false;
        }

        public static void GenerateKeyfileXmlV2(string outputFilePath)
        {
            byte[] keyBytes = RandomNumberGenerator.GetBytes(32);
            try
            {
                byte[] hash = SHA256.HashData(keyBytes);
                string hashHex = Convert.ToHexString(hash, 0, 4);
                string hexRaw = Convert.ToHexString(keyBytes);
                var formattedHex = new StringBuilder();
                formattedHex.AppendLine();
                for (int i = 0; i < hexRaw.Length; i += 8)
                {
                    if (i % 32 == 0) formattedHex.Append("            ");
                    formattedHex.Append(hexRaw.Substring(i, Math.Min(8, hexRaw.Length - i)));
                    if (i + 8 < hexRaw.Length)
                        formattedHex.Append((i + 8) % 32 == 0 ? Environment.NewLine : " ");
                }
                formattedHex.AppendLine();
                formattedHex.Append("        ");

                var doc = new XDocument(
                    new XDeclaration("1.0", "utf-8", null),
                    new XElement("KeyFile",
                        new XElement("Meta",
                            new XElement("Version", "2.0")),
                        new XElement("Key",
                            new XElement("Data",
                                new XAttribute("Hash", hashHex),
                                formattedHex.ToString()))));

                string tmp = outputFilePath + ".tmp";
                doc.Save(tmp);
                File.Move(tmp, outputFilePath, overwrite: true);
                TresorAppSettings.LockDownFileAcl(outputFilePath);
            }
            finally
            {
                CryptographicOperations.ZeroMemory(keyBytes);
            }
        }

        // =====================================================================
        // READ KDBX 3.1 & KDBX 4.0/4.1
        // =====================================================================
        public static KdbxDatabase OpenKdbxBytes(byte[] fileBytes, string? masterPassword, string? keyfilePath = null)
        {
            if (fileBytes == null || fileBytes.Length < 64)
                throw new InvalidDataException("Ungültige oder beschädigte .kdbx-Datei (zu kurz).");

            using var ms = new MemoryStream(fileBytes, writable: false);
            using var br = new BinaryReader(ms, Encoding.UTF8, leaveOpen: true);

            uint sig1 = br.ReadUInt32();
            uint sig2 = br.ReadUInt32();
            if (sig1 != KdbxSig1 || sig2 != KdbxSig2)
                throw new InvalidDataException("Dies ist keine gültige KeePass .kdbx-Datenbank (Signatur stimmt nicht überein).");

            ushort verMinor = br.ReadUInt16();
            ushort verMajor = br.ReadUInt16();

            if (verMajor != 3 && verMajor != 4)
                throw new NotSupportedException($"Nicht unterstützte KDBX-Formatversion: {verMajor}.{verMinor} (unterstützt: KDBX 3.1 & KDBX 4.x).");

            byte[]? compositeKey = null;
            byte[]? transformedKey = null;
            byte[]? masterKey = null;
            byte[]? hmacKey64 = null;
            byte[]? decryptedPayload = null;
            byte[]? xmlBytes = null;

            try
            {
                compositeKey = BuildCompositeKey(masterPassword, keyfilePath);

                if (verMajor == 4)
                {
                    return ReadKdbx4Internal(fileBytes, ms, br, verMajor, verMinor, compositeKey, out transformedKey, out masterKey, out hmacKey64, out decryptedPayload, out xmlBytes);
                }
                else
                {
                    return ReadKdbx3Internal(ms, br, verMajor, verMinor, compositeKey, out transformedKey, out masterKey, out decryptedPayload, out xmlBytes);
                }
            }
            finally
            {
                if (compositeKey != null) CryptographicOperations.ZeroMemory(compositeKey);
                if (transformedKey != null) CryptographicOperations.ZeroMemory(transformedKey);
                if (masterKey != null) CryptographicOperations.ZeroMemory(masterKey);
                if (hmacKey64 != null) CryptographicOperations.ZeroMemory(hmacKey64);
                if (decryptedPayload != null) CryptographicOperations.ZeroMemory(decryptedPayload);
                if (xmlBytes != null) CryptographicOperations.ZeroMemory(xmlBytes);
            }
        }

        private static KdbxDatabase ReadKdbx4Internal(
            byte[] fileBytes,
            MemoryStream ms,
            BinaryReader br,
            ushort verMajor,
            ushort verMinor,
            byte[] compositeKey,
            out byte[]? transformedKey,
            out byte[]? masterKey,
            out byte[]? hmacKey64,
            out byte[]? decryptedPayload,
            out byte[]? xmlBytes)
        {
            transformedKey = null;
            masterKey = null;
            hmacKey64 = null;
            decryptedPayload = null;
            xmlBytes = null;

            byte[] cipherUuid = CipherAes256Uuid;
            uint compressionFlags = 1;
            byte[] masterSeed = Array.Empty<byte>();
            byte[] encryptionIv = Array.Empty<byte>();
            Dictionary<string, object> kdfParams = new(StringComparer.Ordinal);

            while (true)
            {
                byte fieldId = br.ReadByte();
                int fieldLen = br.ReadInt32();
                if (fieldLen < 0 || ms.Position + fieldLen > ms.Length)
                    throw new InvalidDataException("KDBX 4 Header beschädigt.");

                byte[] data = br.ReadBytes(fieldLen);
                if (fieldId == 0) break; // EndOfHeader
                switch (fieldId)
                {
                    case 2: cipherUuid = data; break;
                    case 3: if (data.Length >= 4) compressionFlags = BitConverter.ToUInt32(data, 0); break;
                    case 4: masterSeed = data; break;
                    case 7: encryptionIv = data; break;
                    case 11: kdfParams = ParseVariantDictionary(data); break;
                }
            }

            int headerEndOffset = (int)ms.Position;
            byte[] headerBytes = new byte[headerEndOffset];
            Buffer.BlockCopy(fileBytes, 0, headerBytes, 0, headerEndOffset);

            byte[] storedHeaderSha256 = br.ReadBytes(32);
            byte[] storedHeaderHmac = br.ReadBytes(32);

            byte[] actualHeaderSha256 = SHA256.HashData(headerBytes);
            if (!CryptographicOperations.FixedTimeEquals(storedHeaderSha256, actualHeaderSha256))
                throw new InvalidDataException("Die KDBX 4 Header-Prüfsumme (SHA-256) ist ungültig. Datei ist möglicherweise beschädigt.");

            transformedKey = DeriveKdbx4TransformedKey(compositeKey, kdfParams, out string kdfName, out ulong argonMem, out ulong argonIter, out uint argonPar, out ulong aesRounds);
            masterKey = ComputeMasterKey(masterSeed, transformedKey);
            hmacKey64 = ComputeKdbx4HmacKey64(masterSeed, transformedKey);

            byte[] headerHmacKey = ComputeKdbx4BlockHmacKey(ulong.MaxValue, hmacKey64);
            byte[] expectedHeaderHmac = HMACSHA256.HashData(headerHmacKey, headerBytes);
            CryptographicOperations.ZeroMemory(headerHmacKey);

            if (!CryptographicOperations.FixedTimeEquals(storedHeaderHmac, expectedHeaderHmac))
                throw new UnauthorizedAccessException("Das eingegebene Master-Passwort (oder die Schlüsseldatei) ist nicht korrekt.");

            byte[] encryptedStream = ReadHmacBlockStream(br, hmacKey64);
            decryptedPayload = DecryptOuterCipher(cipherUuid, masterKey, encryptionIv, encryptedStream, out string cipherName);
            byte[] innerRaw = compressionFlags == 1 ? DecompressGzip(decryptedPayload) : decryptedPayload;

            // Parse KDBX 4 Inner Header (RandomStreamID, RandomStreamKey, Binary Attachments)
            using var innerMs = new MemoryStream(innerRaw, writable: false);
            using var innerBr = new BinaryReader(innerMs, Encoding.UTF8, leaveOpen: true);

            uint innerStreamId = 3;
            byte[] innerStreamKey = Array.Empty<byte>();
            var binaryPool = new List<byte[]>();

            while (innerMs.Position < innerMs.Length)
            {
                byte fId = innerBr.ReadByte();
                int fLen = innerBr.ReadInt32();
                if (fLen < 0 || innerMs.Position + fLen > innerMs.Length)
                    throw new InvalidDataException("KDBX 4 Inner-Header beschädigt.");

                byte[] fData = innerBr.ReadBytes(fLen);
                if (fId == 0) break;
                switch (fId)
                {
                    case 1:
                        if (fData.Length >= 4) innerStreamId = BitConverter.ToUInt32(fData, 0);
                        break;
                    case 2:
                        innerStreamKey = fData;
                        break;
                    case 3:
                        if (fData.Length >= 1)
                        {
                            byte[] attBytes = new byte[fData.Length - 1];
                            Buffer.BlockCopy(fData, 1, attBytes, 0, attBytes.Length);
                            binaryPool.Add(attBytes);
                        }
                        break;
                }
            }

            int xmlLen = (int)(innerMs.Length - innerMs.Position);
            xmlBytes = innerBr.ReadBytes(xmlLen);

            var db = ParseKdbxXmlDocument(xmlBytes, innerStreamId, innerStreamKey, binaryPool);
            db.FormatMajor = verMajor;
            db.FormatMinor = verMinor;
            db.KdfAlgorithm = kdfName;
            db.CipherAlgorithm = cipherName;
            db.Argon2MemoryBytes = argonMem;
            db.Argon2Iterations = argonIter;
            db.Argon2Parallelism = argonPar;
            db.AesKdfRounds = aesRounds;
            return db;
        }

        private static KdbxDatabase ReadKdbx3Internal(
            MemoryStream ms,
            BinaryReader br,
            ushort verMajor,
            ushort verMinor,
            byte[] compositeKey,
            out byte[]? transformedKey,
            out byte[]? masterKey,
            out byte[]? decryptedPayload,
            out byte[]? xmlBytes)
        {
            transformedKey = null;
            masterKey = null;
            decryptedPayload = null;
            xmlBytes = null;

            byte[] cipherUuid = CipherAes256Uuid;
            uint compressionFlags = 1;
            byte[] masterSeed = Array.Empty<byte>();
            byte[] transformSeed = Array.Empty<byte>();
            ulong transformRounds = 60000;
            byte[] encryptionIv = Array.Empty<byte>();
            byte[] protectedStreamKey = Array.Empty<byte>();
            byte[] streamStartBytes = Array.Empty<byte>();
            uint innerRandomStreamId = 2;

            while (true)
            {
                byte fieldId = br.ReadByte();
                ushort fieldLen = br.ReadUInt16();
                byte[] data = br.ReadBytes(fieldLen);
                if (fieldId == 0) break;
                switch (fieldId)
                {
                    case 2: cipherUuid = data; break;
                    case 3: if (data.Length >= 4) compressionFlags = BitConverter.ToUInt32(data, 0); break;
                    case 4: masterSeed = data; break;
                    case 5: transformSeed = data; break;
                    case 6: if (data.Length >= 8) transformRounds = BitConverter.ToUInt64(data, 0); break;
                    case 7: encryptionIv = data; break;
                    case 8: protectedStreamKey = data; break;
                    case 9: streamStartBytes = data; break;
                    case 10: if (data.Length >= 4) innerRandomStreamId = BitConverter.ToUInt32(data, 0); break;
                }
            }

            transformedKey = TransformKeyAesKdf(compositeKey, transformSeed, transformRounds);
            masterKey = ComputeMasterKey(masterSeed, transformedKey);

            int remaining = (int)(ms.Length - ms.Position);
            byte[] encryptedBytes = br.ReadBytes(remaining);

            try
            {
                decryptedPayload = DecryptOuterCipher(cipherUuid, masterKey, encryptionIv, encryptedBytes, out string cipherName);
            }
            catch (CryptographicException)
            {
                throw new UnauthorizedAccessException("Das eingegebene Master-Passwort (oder die Schlüsseldatei) ist nicht korrekt.");
            }

            if (decryptedPayload.Length < 32 ||
                !CryptographicOperations.FixedTimeEquals(decryptedPayload.AsSpan(0, 32), streamStartBytes))
            {
                throw new UnauthorizedAccessException("Das eingegebene Master-Passwort (oder die Schlüsseldatei) ist nicht korrekt.");
            }

            using var blockMs = new MemoryStream(decryptedPayload, 32, decryptedPayload.Length - 32, writable: false);
            using var blockBr = new BinaryReader(blockMs, Encoding.UTF8, leaveOpen: true);
            byte[] unblocked = ReadHashedBlockStreamKdbx3(blockBr);
            xmlBytes = compressionFlags == 1 ? DecompressGzip(unblocked) : unblocked;

            DecryptOverallCipherName(cipherUuid, out string cName);
            var db = ParseKdbxXmlDocument(xmlBytes, innerRandomStreamId, protectedStreamKey, new List<byte[]>());
            db.FormatMajor = verMajor;
            db.FormatMinor = verMinor;
            db.KdfAlgorithm = "AES-KDF";
            db.CipherAlgorithm = cName;
            db.AesKdfRounds = transformRounds;
            return db;
        }

        // =====================================================================
        // WRITE KDBX 4.0 (ARGON2ID + AES-256 / CHACHA20 + HMAC BLOCK STREAM)
        // =====================================================================
        public static byte[] WriteKdbx4Bytes(KdbxDatabase db, string? masterPassword, string? keyfilePath = null)
        {
            byte[]? compositeKey = null;
            byte[]? transformedKey = null;
            byte[]? masterKey = null;
            byte[]? hmacKey64 = null;
            byte[]? innerStreamKey = null;
            byte[]? xmlBytes = null;
            byte[]? innerPlainBytes = null;

            try
            {
                compositeKey = BuildCompositeKey(masterPassword, keyfilePath);

                byte[] masterSeed = RandomNumberGenerator.GetBytes(32);
                bool useChaChaOuter = string.Equals(db.CipherAlgorithm, "ChaCha20", StringComparison.OrdinalIgnoreCase);
                byte[] cipherUuid = useChaChaOuter ? CipherChaCha20Uuid : CipherAes256Uuid;
                byte[] encryptionIv = RandomNumberGenerator.GetBytes(useChaChaOuter ? 12 : 16);

                // Always write KDBX 4.0 with Argon2id (or Argon2d if explicitly chosen)
                db.FormatMajor = 4;
                db.FormatMinor = 0;
                if (!string.Equals(db.KdfAlgorithm, "Argon2d", StringComparison.OrdinalIgnoreCase))
                    db.KdfAlgorithm = "Argon2id";

                byte[] argonSalt = RandomNumberGenerator.GetBytes(32);
                ulong memBytes = Math.Max(16UL * 1024UL * 1024UL, db.Argon2MemoryBytes);
                ulong iterations = Math.Max(2UL, db.Argon2Iterations);
                uint parallelism = Math.Max(1U, db.Argon2Parallelism);

                byte[] kdfUuid = string.Equals(db.KdfAlgorithm, "Argon2d", StringComparison.OrdinalIgnoreCase)
                    ? KdfArgon2dUuid
                    : KdfArgon2idUuid;

                var kdfDict = new Dictionary<string, (byte TypeCode, object Value)>(StringComparer.Ordinal)
                {
                    ["$UUID"] = (0x42, kdfUuid),
                    ["S"] = (0x42, argonSalt),
                    ["P"] = (0x04, parallelism),
                    ["M"] = (0x05, memBytes),
                    ["I"] = (0x05, iterations),
                    ["V"] = (0x04, (uint)0x13)
                };
                byte[] kdfDictBytes = WriteVariantDictionary(kdfDict);

                int argonType = kdfUuid.SequenceEqual(KdfArgon2dUuid) ? Argon2Parameters.Argon2d : Argon2Parameters.Argon2id;
                transformedKey = TransformKeyArgon2(compositeKey, argonSalt, argonType, (int)iterations, (int)(memBytes / 1024UL), (int)parallelism);
                masterKey = ComputeMasterKey(masterSeed, transformedKey);
                hmacKey64 = ComputeKdbx4HmacKey64(masterSeed, transformedKey);

                // Build Outer Header
                using var headerMs = new MemoryStream();
                using (var hbw = new BinaryWriter(headerMs, Encoding.UTF8, leaveOpen: true))
                {
                    hbw.Write(KdbxSig1);
                    hbw.Write(KdbxSig2);
                    hbw.Write((ushort)0); // Minor = 0
                    hbw.Write((ushort)4); // Major = 4

                    WriteKdbx4HeaderField(hbw, 2, cipherUuid);
                    WriteKdbx4HeaderField(hbw, 3, BitConverter.GetBytes((uint)1)); // GZip = 1
                    WriteKdbx4HeaderField(hbw, 4, masterSeed);
                    WriteKdbx4HeaderField(hbw, 7, encryptionIv);
                    WriteKdbx4HeaderField(hbw, 11, kdfDictBytes);
                    WriteKdbx4HeaderField(hbw, 0, new byte[] { 0x0D, 0x0A, 0x0D, 0x0A });
                }
                byte[] headerBytes = headerMs.ToArray();
                byte[] headerSha256 = SHA256.HashData(headerBytes);
                byte[] headerHmacKey = ComputeKdbx4BlockHmacKey(ulong.MaxValue, hmacKey64);
                byte[] headerHmac = HMACSHA256.HashData(headerHmacKey, headerBytes);
                CryptographicOperations.ZeroMemory(headerHmacKey);

                // Build Inner Header + XML Document
                innerStreamKey = RandomNumberGenerator.GetBytes(64);
                var binaryPool = new List<byte[]>();
                xmlBytes = BuildKdbx4XmlBytes(db, 3, innerStreamKey, binaryPool);

                using var innerMs = new MemoryStream();
                using (var ibw = new BinaryWriter(innerMs, Encoding.UTF8, leaveOpen: true))
                {
                    WriteKdbx4HeaderField(ibw, 1, BitConverter.GetBytes((uint)3)); // ChaCha20 Inner Stream = 3
                    WriteKdbx4HeaderField(ibw, 2, innerStreamKey);
                    foreach (byte[] bin in binaryPool)
                    {
                        byte[] flagged = new byte[bin.Length + 1];
                        flagged[0] = 0; // non-protected flag byte
                        Buffer.BlockCopy(bin, 0, flagged, 1, bin.Length);
                        WriteKdbx4HeaderField(ibw, 3, flagged);
                    }
                    WriteKdbx4HeaderField(ibw, 0, Array.Empty<byte>());
                    ibw.Write(xmlBytes);
                }

                innerPlainBytes = innerMs.ToArray();
                byte[] compressedPayload = CompressGzip(innerPlainBytes);
                byte[] encryptedPayload = EncryptOuterCipher(cipherUuid, masterKey, encryptionIv, compressedPayload);

                // Assemble Final KDBX 4 File (Header + SHA256 + HMAC + HMAC Block Stream)
                using var outMs = new MemoryStream();
                using (var obw = new BinaryWriter(outMs, Encoding.UTF8, leaveOpen: true))
                {
                    obw.Write(headerBytes);
                    obw.Write(headerSha256);
                    obw.Write(headerHmac);
                    WriteHmacBlockStream(obw, encryptedPayload, hmacKey64);
                }
                return outMs.ToArray();
            }
            finally
            {
                if (compositeKey != null) CryptographicOperations.ZeroMemory(compositeKey);
                if (transformedKey != null) CryptographicOperations.ZeroMemory(transformedKey);
                if (masterKey != null) CryptographicOperations.ZeroMemory(masterKey);
                if (hmacKey64 != null) CryptographicOperations.ZeroMemory(hmacKey64);
                if (innerStreamKey != null) CryptographicOperations.ZeroMemory(innerStreamKey);
                if (xmlBytes != null) CryptographicOperations.ZeroMemory(xmlBytes);
                if (innerPlainBytes != null) CryptographicOperations.ZeroMemory(innerPlainBytes);
            }
        }

        // =====================================================================
        // LEGACY KDBX 3.1 WRITER (USED BY SELF-TEST FOR LEGACY COMPATIBILITY)
        // =====================================================================
        public static byte[] WriteLegacyKdbx3BytesForTest(KdbxDatabase db, string masterPassword, ulong rounds = 10000)
        {
            byte[] compositeKey = BuildCompositeKey(masterPassword, null);
            byte[] masterSeed = RandomNumberGenerator.GetBytes(32);
            byte[] transformSeed = RandomNumberGenerator.GetBytes(32);
            byte[] encryptionIv = RandomNumberGenerator.GetBytes(16);
            byte[] protectedStreamKey = RandomNumberGenerator.GetBytes(32);
            byte[] streamStartBytes = RandomNumberGenerator.GetBytes(32);

            byte[] transformedKey = TransformKeyAesKdf(compositeKey, transformSeed, rounds);
            byte[] masterKey = ComputeMasterKey(masterSeed, transformedKey);

            var binaryPool = new List<byte[]>();
            byte[] xmlBytes = BuildKdbx4XmlBytes(db, 2, protectedStreamKey, binaryPool, useIsoDatesForKdbx3: true);
            byte[] compressed = CompressGzip(xmlBytes);

            using var payloadMs = new MemoryStream();
            using (var pbw = new BinaryWriter(payloadMs, Encoding.UTF8, leaveOpen: true))
            {
                pbw.Write(streamStartBytes);
                pbw.Write((uint)0);
                pbw.Write(SHA256.HashData(compressed));
                pbw.Write(compressed.Length);
                pbw.Write(compressed);
                // EOF block
                pbw.Write((uint)1);
                pbw.Write(new byte[32]);
                pbw.Write(0);
            }

            byte[] encryptedPayload = EncryptOuterCipher(CipherAes256Uuid, masterKey, encryptionIv, payloadMs.ToArray());

            using var outMs = new MemoryStream();
            using (var bw = new BinaryWriter(outMs, Encoding.UTF8, leaveOpen: true))
            {
                bw.Write(KdbxSig1);
                bw.Write(KdbxSig2);
                bw.Write((ushort)1); // Minor = 1
                bw.Write((ushort)3); // Major = 3

                void WriteF3(byte id, byte[] d)
                {
                    bw.Write(id);
                    bw.Write((ushort)d.Length);
                    bw.Write(d);
                }

                WriteF3(2, CipherAes256Uuid);
                WriteF3(3, BitConverter.GetBytes((uint)1));
                WriteF3(4, masterSeed);
                WriteF3(5, transformSeed);
                WriteF3(6, BitConverter.GetBytes(rounds));
                WriteF3(7, encryptionIv);
                WriteF3(8, protectedStreamKey);
                WriteF3(9, streamStartBytes);
                WriteF3(10, BitConverter.GetBytes((uint)2)); // Salsa20
                WriteF3(0, new byte[] { 0x0D, 0x0A, 0x0D, 0x0A });
                bw.Write(encryptedPayload);
            }
            return outMs.ToArray();
        }

        // =====================================================================
        // COMPOSITE KEY, ARGON2ID/D, AES-KDF & STREAM CIPHERS
        // =====================================================================
        public static byte[] BuildCompositeKey(string? masterPassword, string? keyfilePath)
        {
            var components = new List<byte[]>(2);
            try
            {
                if (!string.IsNullOrEmpty(masterPassword))
                {
                    byte[] utf8 = Encoding.UTF8.GetBytes(masterPassword);
                    try
                    {
                        components.Add(SHA256.HashData(utf8));
                    }
                    finally
                    {
                        CryptographicOperations.ZeroMemory(utf8);
                    }
                }

                if (!string.IsNullOrWhiteSpace(keyfilePath))
                {
                    if (!File.Exists(keyfilePath))
                        throw new FileNotFoundException("Die ausgewählte Schlüsseldatei (Keyfile) wurde nicht gefunden.", keyfilePath);

                    components.Add(ReadKeyfileComponent32(keyfilePath));
                }

                if (components.Count == 0)
                {
                    // Empty password fallback if user explicitly set blank password in a test DB
                    components.Add(SHA256.HashData(Array.Empty<byte>()));
                }

                byte[] concat = new byte[components.Count * 32];
                for (int i = 0; i < components.Count; i++)
                    Buffer.BlockCopy(components[i], 0, concat, i * 32, 32);

                try
                {
                    return SHA256.HashData(concat);
                }
                finally
                {
                    CryptographicOperations.ZeroMemory(concat);
                }
            }
            finally
            {
                foreach (var c in components)
                    CryptographicOperations.ZeroMemory(c);
            }
        }

        private static byte[] ReadKeyfileComponent32(string keyfilePath)
        {
            byte[] raw = File.ReadAllBytes(keyfilePath);
            try
            {
                if (raw.Length == 32)
                {
                    byte[] copy = new byte[32];
                    Buffer.BlockCopy(raw, 0, copy, 0, 32);
                    return copy;
                }

                if (raw.Length == 64)
                {
                    string ascii = Encoding.ASCII.GetString(raw).Trim();
                    if (ascii.Length == 64 && ascii.All(Uri.IsHexDigit))
                        return Convert.FromHexString(ascii);
                }

                // Try XML KeyFile v1.0 or v2.0
                string text = Encoding.UTF8.GetString(raw).TrimStart('\uFEFF', ' ', '\t', '\r', '\n');
                if (text.StartsWith("<", StringComparison.Ordinal))
                {
                    try
                    {
                        var doc = XDocument.Parse(text);
                        var ver = doc.Root?.Element("Meta")?.Element("Version")?.Value?.Trim() ?? "";
                        var dataEl = doc.Root?.Element("Key")?.Element("Data");
                        if (dataEl != null)
                        {
                            string dataVal = new string((dataEl.Value ?? "").Where(c => !char.IsWhiteSpace(c)).ToArray());
                            if (ver.StartsWith("2.", StringComparison.Ordinal))
                            {
                                byte[] keyBytes = Convert.FromHexString(dataVal);
                                string? expectedHashHex = dataEl.Attribute("Hash")?.Value?.Trim();
                                if (!string.IsNullOrEmpty(expectedHashHex))
                                {
                                    byte[] actualHash = SHA256.HashData(keyBytes);
                                    string actualPrefix = Convert.ToHexString(actualHash, 0, expectedHashHex.Length / 2);
                                    if (!string.Equals(expectedHashHex, actualPrefix, StringComparison.OrdinalIgnoreCase))
                                        throw new InvalidDataException("Die Prüfsumme der Schlüsseldatei (.keyx) ist beschädigt.");
                                }
                                return keyBytes.Length == 32 ? keyBytes : SHA256.HashData(keyBytes);
                            }
                            else
                            {
                                byte[] keyBytes = Convert.FromBase64String(dataVal);
                                return keyBytes.Length == 32 ? keyBytes : SHA256.HashData(keyBytes);
                            }
                        }
                    }
                    catch (InvalidDataException) { throw; }
                    catch { }
                }

                return SHA256.HashData(raw);
            }
            finally
            {
                CryptographicOperations.ZeroMemory(raw);
            }
        }

        private static byte[] DeriveKdbx4TransformedKey(
            byte[] compositeKey,
            Dictionary<string, object> kdfParams,
            out string kdfName,
            out ulong argonMemBytes,
            out ulong argonIterations,
            out uint argonParallelism,
            out ulong aesRounds)
        {
            argonMemBytes = 64UL * 1024UL * 1024UL;
            argonIterations = 3;
            argonParallelism = 2;
            aesRounds = 600_000;

            if (!kdfParams.TryGetValue("$UUID", out object? uuidObj) || uuidObj is not byte[] kdfUuid)
                throw new InvalidDataException("KDF-Parameter ($UUID) fehlen im KDBX 4 Header.");

            if (kdfUuid.SequenceEqual(KdfArgon2idUuid) || kdfUuid.SequenceEqual(KdfArgon2dUuid))
            {
                bool isId = kdfUuid.SequenceEqual(KdfArgon2idUuid);
                kdfName = isId ? "Argon2id" : "Argon2d";

                byte[] salt = kdfParams.TryGetValue("S", out var sObj) && sObj is byte[] sb ? sb : Array.Empty<byte>();
                argonParallelism = kdfParams.TryGetValue("P", out var pObj) ? Convert.ToUInt32(pObj) : 2U;
                argonMemBytes = kdfParams.TryGetValue("M", out var mObj) ? Convert.ToUInt64(mObj) : (64UL * 1024UL * 1024UL);
                argonIterations = kdfParams.TryGetValue("I", out var iObj) ? Convert.ToUInt64(iObj) : 2UL;
                int version = kdfParams.TryGetValue("V", out var vObj) ? Convert.ToInt32(vObj) : 0x13;

                int argonType = isId ? Argon2Parameters.Argon2id : Argon2Parameters.Argon2d;
                int memKiB = (int)Math.Clamp(argonMemBytes / 1024UL, 8UL, 2UL * 1024UL * 1024UL);
                return TransformKeyArgon2(compositeKey, salt, argonType, (int)argonIterations, memKiB, (int)argonParallelism, version);
            }

            if (kdfUuid.SequenceEqual(KdfAesUuid) || kdfUuid.SequenceEqual(KdfAesLegacyUuid))
            {
                kdfName = "AES-KDF";
                byte[] seed = kdfParams.TryGetValue("S", out var sObj) && sObj is byte[] sb ? sb : Array.Empty<byte>();
                aesRounds = kdfParams.TryGetValue("R", out var rObj) ? Convert.ToUInt64(rObj) : 60000UL;
                return TransformKeyAesKdf(compositeKey, seed, aesRounds);
            }

            throw new NotSupportedException($"Nicht unterstützte KDF-UUID in .kdbx: {Convert.ToHexString(kdfUuid)}");
        }

        public static byte[] TransformKeyArgon2(byte[] compositeKey, byte[] salt, int argonType, int iterations, int memoryKiB, int parallelism, int version = 0x13)
        {
            var builder = new Argon2Parameters.Builder(argonType)
                .WithVersion(version)
                .WithIterations(Math.Max(1, iterations))
                .WithMemoryAsKB(Math.Max(8, memoryKiB))
                .WithParallelism(Math.Max(1, parallelism))
                .WithSalt(salt);

            var gen = new Argon2BytesGenerator();
            gen.Init(builder.Build());
            byte[] outKey = new byte[32];
            gen.GenerateBytes(compositeKey, outKey, 0, 32);
            return outKey;
        }

        public static byte[] TransformKeyAesKdf(byte[] compositeKey, byte[] transformSeed, ulong rounds)
        {
            if (transformSeed.Length != 32)
                throw new InvalidDataException("Ungültiger AES-KDF TransformSeed.");

            byte[] work = new byte[32];
            Buffer.BlockCopy(compositeKey, 0, work, 0, 32);

            using var aes = Aes.Create();
            aes.KeySize = 256;
            aes.Key = transformSeed;
            aes.Mode = CipherMode.ECB;
            aes.Padding = PaddingMode.None;

            using var encryptor = aes.CreateEncryptor();
            for (ulong r = 0; r < rounds; r++)
            {
                encryptor.TransformBlock(work, 0, 32, work, 0);
            }

            byte[] result = SHA256.HashData(work);
            CryptographicOperations.ZeroMemory(work);
            return result;
        }

        private static byte[] ComputeMasterKey(byte[] masterSeed, byte[] transformedKey)
        {
            byte[] buf = new byte[masterSeed.Length + transformedKey.Length];
            Buffer.BlockCopy(masterSeed, 0, buf, 0, masterSeed.Length);
            Buffer.BlockCopy(transformedKey, 0, buf, masterSeed.Length, transformedKey.Length);
            try
            {
                return SHA256.HashData(buf);
            }
            finally
            {
                CryptographicOperations.ZeroMemory(buf);
            }
        }

        private static byte[] ComputeKdbx4HmacKey64(byte[] masterSeed, byte[] transformedKey)
        {
            byte[] buf = new byte[masterSeed.Length + transformedKey.Length + 1];
            Buffer.BlockCopy(masterSeed, 0, buf, 0, masterSeed.Length);
            Buffer.BlockCopy(transformedKey, 0, buf, masterSeed.Length, transformedKey.Length);
            buf[buf.Length - 1] = 0x01;
            try
            {
                return SHA512.HashData(buf);
            }
            finally
            {
                CryptographicOperations.ZeroMemory(buf);
            }
        }

        private static byte[] ComputeKdbx4BlockHmacKey(ulong blockIndex, byte[] hmacKey64)
        {
            byte[] buf = new byte[8 + hmacKey64.Length];
            byte[] idxBytes = BitConverter.GetBytes(blockIndex);
            Buffer.BlockCopy(idxBytes, 0, buf, 0, 8);
            Buffer.BlockCopy(hmacKey64, 0, buf, 8, hmacKey64.Length);
            try
            {
                return SHA512.HashData(buf);
            }
            finally
            {
                CryptographicOperations.ZeroMemory(buf);
            }
        }

        private static void DecryptOverallCipherName(byte[] cipherUuid, out string cipherName)
        {
            if (cipherUuid.SequenceEqual(CipherAes256Uuid)) cipherName = "AES-256";
            else if (cipherUuid.SequenceEqual(CipherChaCha20Uuid)) cipherName = "ChaCha20";
            else if (cipherUuid.SequenceEqual(CipherTwofishUuid)) cipherName = "Twofish";
            else cipherName = "AES-256";
        }

        private static byte[] DecryptOuterCipher(byte[] cipherUuid, byte[] masterKey, byte[] iv, byte[] cipherBytes, out string cipherName)
        {
            if (cipherUuid.SequenceEqual(CipherAes256Uuid))
            {
                cipherName = "AES-256";
                using var aes = Aes.Create();
                aes.KeySize = 256;
                aes.Key = masterKey;
                aes.IV = iv;
                aes.Mode = CipherMode.CBC;
                aes.Padding = PaddingMode.PKCS7;
                using var dec = aes.CreateDecryptor();
                return dec.TransformFinalBlock(cipherBytes, 0, cipherBytes.Length);
            }

            if (cipherUuid.SequenceEqual(CipherChaCha20Uuid))
            {
                cipherName = "ChaCha20";
                var engine = new ChaCha7539Engine();
                engine.Init(false, new ParametersWithIV(new KeyParameter(masterKey), iv));
                byte[] output = new byte[cipherBytes.Length];
                engine.ProcessBytes(cipherBytes, 0, cipherBytes.Length, output, 0);
                return output;
            }

            if (cipherUuid.SequenceEqual(CipherTwofishUuid))
            {
                cipherName = "Twofish";
                var cipher = new PaddedBufferedBlockCipher(new CbcBlockCipher(new TwofishEngine()), new Pkcs7Padding());
                cipher.Init(false, new ParametersWithIV(new KeyParameter(masterKey), iv));
                return cipher.DoFinal(cipherBytes);
            }

            throw new NotSupportedException($"Nicht unterstützter Outer-Cipher in .kdbx: {Convert.ToHexString(cipherUuid)}");
        }

        private static byte[] EncryptOuterCipher(byte[] cipherUuid, byte[] masterKey, byte[] iv, byte[] plainBytes)
        {
            if (cipherUuid.SequenceEqual(CipherChaCha20Uuid))
            {
                var engine = new ChaCha7539Engine();
                engine.Init(true, new ParametersWithIV(new KeyParameter(masterKey), iv));
                byte[] output = new byte[plainBytes.Length];
                engine.ProcessBytes(plainBytes, 0, plainBytes.Length, output, 0);
                return output;
            }

            using var aes = Aes.Create();
            aes.KeySize = 256;
            aes.Key = masterKey;
            aes.IV = iv;
            aes.Mode = CipherMode.CBC;
            aes.Padding = PaddingMode.PKCS7;
            using var enc = aes.CreateEncryptor();
            return enc.TransformFinalBlock(plainBytes, 0, plainBytes.Length);
        }

        // =====================================================================
        // HMAC-BLOCK-STREAM (KDBX 4) & HASHED-BLOCK-STREAM (KDBX 3.1)
        // =====================================================================
        private static byte[] ReadHmacBlockStream(BinaryReader br, byte[] hmacKey64)
        {
            using var outMs = new MemoryStream();
            ulong blockIndex = 0;

            while (true)
            {
                byte[] storedHmac = br.ReadBytes(32);
                int blockSize = br.ReadInt32();
                if (blockSize < 0)
                    throw new InvalidDataException("Ungültige Blockgröße im KDBX 4 HMAC-Stream.");

                byte[] blockData = blockSize > 0 ? br.ReadBytes(blockSize) : Array.Empty<byte>();
                byte[] blockKey = ComputeKdbx4BlockHmacKey(blockIndex, hmacKey64);

                using var hmac = new HMACSHA256(blockKey);
                byte[] idxBytes = BitConverter.GetBytes(blockIndex);
                byte[] sizeBytes = BitConverter.GetBytes(blockSize);
                hmac.TransformBlock(idxBytes, 0, 8, null, 0);
                hmac.TransformBlock(sizeBytes, 0, 4, null, 0);
                hmac.TransformFinalBlock(blockData, 0, blockData.Length);
                byte[] expectedHmac = hmac.Hash!;
                CryptographicOperations.ZeroMemory(blockKey);

                if (!CryptographicOperations.FixedTimeEquals(storedHmac, expectedHmac))
                    throw new InvalidDataException($"KDBX 4 Datenblock #{blockIndex} wurde manipuliert oder beschädigt (HMAC-Fehler).");

                if (blockSize == 0)
                    break;

                outMs.Write(blockData, 0, blockData.Length);
                blockIndex++;
            }

            return outMs.ToArray();
        }

        private static void WriteHmacBlockStream(BinaryWriter bw, byte[] payload, byte[] hmacKey64)
        {
            const int chunkSize = 1024 * 1024; // 1 MB blocks
            ulong blockIndex = 0;
            int offset = 0;

            while (offset < payload.Length)
            {
                int len = Math.Min(chunkSize, payload.Length - offset);
                WriteSingleHmacBlock(bw, blockIndex, payload, offset, len, hmacKey64);
                offset += len;
                blockIndex++;
            }

            // Final 0-length EOF block
            WriteSingleHmacBlock(bw, blockIndex, Array.Empty<byte>(), 0, 0, hmacKey64);
        }

        private static void WriteSingleHmacBlock(BinaryWriter bw, ulong blockIndex, byte[] buffer, int offset, int count, byte[] hmacKey64)
        {
            byte[] blockKey = ComputeKdbx4BlockHmacKey(blockIndex, hmacKey64);
            using var hmac = new HMACSHA256(blockKey);
            byte[] idxBytes = BitConverter.GetBytes(blockIndex);
            byte[] sizeBytes = BitConverter.GetBytes(count);
            hmac.TransformBlock(idxBytes, 0, 8, null, 0);
            hmac.TransformBlock(sizeBytes, 0, 4, null, 0);
            hmac.TransformFinalBlock(buffer, offset, count);
            byte[] mac = hmac.Hash!;
            CryptographicOperations.ZeroMemory(blockKey);

            bw.Write(mac);
            bw.Write(count);
            if (count > 0)
                bw.Write(buffer, offset, count);
        }

        private static byte[] ReadHashedBlockStreamKdbx3(BinaryReader br)
        {
            using var outMs = new MemoryStream();
            uint expectedIndex = 0;
            while (true)
            {
                uint idx = br.ReadUInt32();
                byte[] hash = br.ReadBytes(32);
                int size = br.ReadInt32();
                if (idx != expectedIndex || size < 0)
                    throw new InvalidDataException("KDBX 3.1 Block-Stream beschädigt.");

                if (size == 0)
                    break;

                byte[] data = br.ReadBytes(size);
                byte[] actualHash = SHA256.HashData(data);
                if (!CryptographicOperations.FixedTimeEquals(hash, actualHash))
                    throw new InvalidDataException("KDBX 3.1 Block-Prüfsumme ungültig.");

                outMs.Write(data, 0, data.Length);
                expectedIndex++;
            }
            return outMs.ToArray();
        }

        // =====================================================================
        // VARIANT DICTIONARY (KDBX 4 KDF PARAMETERS)
        // =====================================================================
        private static Dictionary<string, object> ParseVariantDictionary(byte[] data)
        {
            var dict = new Dictionary<string, object>(StringComparer.Ordinal);
            if (data == null || data.Length < 2) return dict;

            using var ms = new MemoryStream(data, writable: false);
            using var br = new BinaryReader(ms, Encoding.UTF8, leaveOpen: true);

            ushort version = br.ReadUInt16();
            if ((version & 0xFF00) > 0x0100)
                throw new NotSupportedException($"Nicht unterstützte VariantDictionary-Version: 0x{version:X4}");

            while (ms.Position < ms.Length)
            {
                byte valueType = br.ReadByte();
                if (valueType == 0) break;

                int nameLen = br.ReadInt32();
                string name = Encoding.UTF8.GetString(br.ReadBytes(nameLen));
                int valLen = br.ReadInt32();
                byte[] valBytes = br.ReadBytes(valLen);

                object parsed = valueType switch
                {
                    0x04 => BitConverter.ToUInt32(valBytes, 0),
                    0x05 => BitConverter.ToUInt64(valBytes, 0),
                    0x08 => valBytes.Length > 0 && valBytes[0] != 0,
                    0x0C => BitConverter.ToInt32(valBytes, 0),
                    0x0D => BitConverter.ToInt64(valBytes, 0),
                    0x18 => Encoding.UTF8.GetString(valBytes),
                    0x42 => valBytes,
                    _ => valBytes
                };
                dict[name] = parsed;
            }
            return dict;
        }

        private static byte[] WriteVariantDictionary(Dictionary<string, (byte TypeCode, object Value)> entries)
        {
            using var ms = new MemoryStream();
            using var bw = new BinaryWriter(ms, Encoding.UTF8, leaveOpen: true);
            bw.Write((ushort)0x0100);

            foreach (var kv in entries)
            {
                bw.Write(kv.Value.TypeCode);
                byte[] nameBytes = Encoding.UTF8.GetBytes(kv.Key);
                bw.Write(nameBytes.Length);
                bw.Write(nameBytes);

                byte[] valBytes = kv.Value.TypeCode switch
                {
                    0x04 => BitConverter.GetBytes((uint)kv.Value.Value),
                    0x05 => BitConverter.GetBytes((ulong)kv.Value.Value),
                    0x08 => new byte[] { (bool)kv.Value.Value ? (byte)1 : (byte)0 },
                    0x0C => BitConverter.GetBytes((int)kv.Value.Value),
                    0x0D => BitConverter.GetBytes((long)kv.Value.Value),
                    0x18 => Encoding.UTF8.GetBytes((string)kv.Value.Value),
                    0x42 => (byte[])kv.Value.Value,
                    _ => (byte[])kv.Value.Value
                };
                bw.Write(valBytes.Length);
                bw.Write(valBytes);
            }

            bw.Write((byte)0);
            return ms.ToArray();
        }

        private static void WriteKdbx4HeaderField(BinaryWriter bw, byte fieldId, byte[] data)
        {
            bw.Write(fieldId);
            bw.Write(data.Length);
            if (data.Length > 0)
                bw.Write(data);
        }

        // =====================================================================
        // INNER RANDOM STREAM CIPHER (CHACHA20 / SALSA20) & XML PARSER/WRITER
        // =====================================================================
        private static IStreamCipher? CreateInnerRandomStreamCipher(uint streamId, byte[] keyMaterial)
        {
            if (streamId == 3 && keyMaterial.Length > 0) // ChaCha20 (RFC 7539)
            {
                byte[] hash512 = SHA512.HashData(keyMaterial);
                byte[] key = new byte[32];
                byte[] nonce = new byte[12];
                Buffer.BlockCopy(hash512, 0, key, 0, 32);
                Buffer.BlockCopy(hash512, 32, nonce, 0, 12);
                CryptographicOperations.ZeroMemory(hash512);

                var chacha = new ChaCha7539Engine();
                chacha.Init(true, new ParametersWithIV(new KeyParameter(key), nonce));
                return chacha;
            }

            if (streamId == 2 && keyMaterial.Length > 0) // Salsa20
            {
                byte[] key = SHA256.HashData(keyMaterial);
                var salsa = new Salsa20Engine();
                salsa.Init(true, new ParametersWithIV(new KeyParameter(key), Salsa20FixedIv));
                return salsa;
            }

            return null;
        }

        private static string UnprotectXmlValue(XElement valueEl, IStreamCipher? streamCipher)
        {
            bool isProtected = string.Equals(valueEl.Attribute("Protected")?.Value, "True", StringComparison.OrdinalIgnoreCase);
            string rawText = valueEl.Value ?? "";
            if (!isProtected || streamCipher == null || string.IsNullOrEmpty(rawText))
                return rawText;

            byte[] cipherBytes = Convert.FromBase64String(rawText);
            byte[] plainBytes = new byte[cipherBytes.Length];
            streamCipher.ProcessBytes(cipherBytes, 0, cipherBytes.Length, plainBytes, 0);
            try
            {
                return Encoding.UTF8.GetString(plainBytes);
            }
            finally
            {
                CryptographicOperations.ZeroMemory(plainBytes);
            }
        }

        private static string ProtectXmlValue(string plainText, IStreamCipher? streamCipher)
        {
            if (streamCipher == null || string.IsNullOrEmpty(plainText))
                return "";

            byte[] plainBytes = Encoding.UTF8.GetBytes(plainText);
            byte[] cipherBytes = new byte[plainBytes.Length];
            try
            {
                streamCipher.ProcessBytes(plainBytes, 0, plainBytes.Length, cipherBytes, 0);
                return Convert.ToBase64String(cipherBytes);
            }
            finally
            {
                CryptographicOperations.ZeroMemory(plainBytes);
            }
        }

        private static KdbxDatabase ParseKdbxXmlDocument(byte[] xmlBytes, uint innerStreamId, byte[] innerStreamKey, List<byte[]> kdbx4BinaryPool)
        {
            using var ms = new MemoryStream(xmlBytes, writable: false);
            var doc = XDocument.Load(ms);
            var streamCipher = CreateInnerRandomStreamCipher(innerStreamId, innerStreamKey);

            var db = new KdbxDatabase();
            var root = doc.Root ?? throw new InvalidDataException("KeePass XML-Wurzelknoten fehlt.");
            var meta = root.Element("Meta");

            // Parse KDBX 3.1 Meta/Binaries pool if present
            var kdbx3Binaries = new Dictionary<string, byte[]>(StringComparer.OrdinalIgnoreCase);
            if (meta != null)
            {
                db.GeneratorName = meta.Element("Generator")?.Value ?? "KeePass";
                db.DatabaseName = meta.Element("DatabaseName")?.Value ?? "Tresor";
                db.DatabaseDescription = meta.Element("DatabaseDescription")?.Value ?? "";
                db.RecycleBinEnabled = !string.Equals(meta.Element("RecycleBinEnabled")?.Value, "False", StringComparison.OrdinalIgnoreCase);
                db.RecycleBinUuidHex = Base64UuidToHex(meta.Element("RecycleBinUUID")?.Value);

                var binariesEl = meta.Element("Binaries");
                if (binariesEl != null)
                {
                    foreach (var binEl in binariesEl.Elements("Binary"))
                    {
                        string? id = binEl.Attribute("ID")?.Value;
                        if (string.IsNullOrEmpty(id)) continue;
                        bool compressed = string.Equals(binEl.Attribute("Compressed")?.Value, "True", StringComparison.OrdinalIgnoreCase);
                        byte[] raw = Convert.FromBase64String(binEl.Value ?? "");
                        kdbx3Binaries[id] = compressed ? DecompressGzip(raw) : raw;
                    }
                }

                var customIconsEl = meta.Element("CustomIcons");
                if (customIconsEl != null)
                {
                    foreach (var iconEl in customIconsEl.Elements("Icon"))
                    {
                        string uuidHex = Base64UuidToHex(iconEl.Element("UUID")?.Value);
                        string? b64Data = iconEl.Element("Data")?.Value;
                        if (!string.IsNullOrWhiteSpace(uuidHex) && !string.IsNullOrWhiteSpace(b64Data))
                        {
                            try
                            {
                                db.CustomIcons[uuidHex] = Convert.FromBase64String(b64Data.Trim());
                            }
                            catch { }
                        }
                    }
                }
            }

            // IMPORTANT: In KeePass XML, the InnerRandomStream MUST advance in exact document order across ALL <Value Protected="True"> nodes (including History)!
            var protectedValueMap = new Dictionary<XElement, string>();
            foreach (var valEl in root.Descendants("Value"))
            {
                if (string.Equals(valEl.Attribute("Protected")?.Value, "True", StringComparison.OrdinalIgnoreCase))
                {
                    protectedValueMap[valEl] = UnprotectXmlValue(valEl, streamCipher);
                }
            }

            string ReadValue(XElement? valEl)
            {
                if (valEl == null) return "";
                if (protectedValueMap.TryGetValue(valEl, out string? dec)) return dec;
                return valEl.Value ?? "";
            }

            KdbxEntry ParseEntry(XElement entryEl, KdbxGroup parentGroup)
            {
                string customIconUuid = "";
                string? rawCustomIcon = entryEl.Element("CustomIconUUID")?.Value;
                if (!string.IsNullOrWhiteSpace(rawCustomIcon))
                    customIconUuid = Base64UuidToHex(rawCustomIcon);

                var entry = new KdbxEntry
                {
                    UuidHex = Base64UuidToHex(entryEl.Element("UUID")?.Value),
                    GroupUuidHex = parentGroup.UuidHex,
                    GroupName = parentGroup.Name,
                    IconId = int.TryParse(entryEl.Element("IconID")?.Value, out int ico) ? ico : 0,
                    CustomIconUuidHex = customIconUuid,
                    CustomIconsRef = db.CustomIcons,
                    Tags = entryEl.Element("Tags")?.Value ?? ""
                };

                var timesEl = entryEl.Element("Times");
                if (timesEl != null)
                {
                    entry.CreatedUtc = ParseKdbxTime(timesEl.Element("CreationTime")?.Value);
                    entry.ModifiedUtc = ParseKdbxTime(timesEl.Element("LastModificationTime")?.Value);
                    entry.Expires = string.Equals(timesEl.Element("Expires")?.Value, "True", StringComparison.OrdinalIgnoreCase);
                    if (entry.Expires)
                        entry.ExpiresUtc = ParseKdbxTime(timesEl.Element("ExpiryTime")?.Value);
                }

                foreach (var strEl in entryEl.Elements("String"))
                {
                    string key = strEl.Element("Key")?.Value ?? "";
                    var valEl = strEl.Element("Value");
                    string val = ReadValue(valEl);
                    bool isProt = string.Equals(valEl?.Attribute("Protected")?.Value, "True", StringComparison.OrdinalIgnoreCase);

                    switch (key)
                    {
                        case "Title": entry.Title = val; break;
                        case "UserName": entry.UserName = val; break;
                        case "Password": entry.Password = val; break;
                        case "URL": entry.Url = val; break;
                        case "Notes": entry.Notes = val; break;
                        default:
                            if (!string.IsNullOrEmpty(key))
                                entry.CustomStrings[key] = new KdbxCustomString { Value = val, IsProtected = isProt };
                            break;
                    }
                }

                foreach (var binEl in entryEl.Elements("Binary"))
                {
                    string fName = binEl.Element("Key")?.Value ?? "anhang.bin";
                    var valEl = binEl.Element("Value");
                    string? refId = valEl?.Attribute("Ref")?.Value;
                    if (!string.IsNullOrEmpty(refId))
                    {
                        if (int.TryParse(refId, out int idx) && idx >= 0 && idx < kdbx4BinaryPool.Count)
                        {
                            entry.Attachments.Add(new KdbxAttachment { FileName = fName, Data = kdbx4BinaryPool[idx] });
                        }
                        else if (kdbx3Binaries.TryGetValue(refId, out byte[]? b3))
                        {
                            entry.Attachments.Add(new KdbxAttachment { FileName = fName, Data = b3 });
                        }
                    }
                }

                return entry;
            }

            KdbxGroup ParseGroup(XElement groupEl)
            {
                string customIconUuid = "";
                string? rawCustomIcon = groupEl.Element("CustomIconUUID")?.Value;
                if (!string.IsNullOrWhiteSpace(rawCustomIcon))
                    customIconUuid = Base64UuidToHex(rawCustomIcon);

                var g = new KdbxGroup
                {
                    UuidHex = Base64UuidToHex(groupEl.Element("UUID")?.Value),
                    Name = groupEl.Element("Name")?.Value ?? "Gruppe",
                    Notes = groupEl.Element("Notes")?.Value ?? "",
                    IconId = int.TryParse(groupEl.Element("IconID")?.Value, out int ico) ? ico : 48,
                    CustomIconUuidHex = customIconUuid,
                    CustomIconsRef = db.CustomIcons,
                    IsExpanded = !string.Equals(groupEl.Element("IsExpanded")?.Value, "False", StringComparison.OrdinalIgnoreCase)
                };

                var timesEl = groupEl.Element("Times");
                if (timesEl != null)
                {
                    g.CreatedUtc = ParseKdbxTime(timesEl.Element("CreationTime")?.Value);
                    g.ModifiedUtc = ParseKdbxTime(timesEl.Element("LastModificationTime")?.Value);
                }

                foreach (var entryEl in groupEl.Elements("Entry"))
                    g.Entries.Add(ParseEntry(entryEl, g));

                foreach (var subEl in groupEl.Elements("Group"))
                    g.SubGroups.Add(ParseGroup(subEl));

                return g;
            }

            var rootGroupEl = root.Element("Root")?.Element("Group");
            if (rootGroupEl != null)
                db.RootGroup = ParseGroup(rootGroupEl);

            db.RefreshHierarchyReferences();
            return db;
        }

        private static byte[] BuildKdbx4XmlBytes(KdbxDatabase db, uint innerStreamId, byte[] innerStreamKey, List<byte[]> binaryPool, bool useIsoDatesForKdbx3 = false)
        {
            var streamCipher = CreateInnerRandomStreamCipher(innerStreamId, innerStreamKey);

            string FormatTime(DateTime dt)
            {
                var utc = dt.Kind == DateTimeKind.Utc ? dt : dt.ToUniversalTime();
                if (useIsoDatesForKdbx3)
                    return utc.ToString("yyyy-MM-ddTHH:mm:ssZ", CultureInfo.InvariantCulture);

                long secs = (long)Math.Round((utc - KdbxEpochUtc).TotalSeconds);
                return Convert.ToBase64String(BitConverter.GetBytes(secs));
            }

            XElement BuildStringEl(string key, string value, bool protect)
            {
                if (protect)
                {
                    string enc = ProtectXmlValue(value ?? "", streamCipher);
                    return new XElement("String",
                        new XElement("Key", key),
                        new XElement("Value", new XAttribute("Protected", "True"), enc));
                }
                return new XElement("String",
                    new XElement("Key", key),
                    new XElement("Value", value ?? ""));
            }

            XElement BuildTimesEl(DateTime created, DateTime modified, bool expires = false, DateTime? expiry = null)
            {
                return new XElement("Times",
                    new XElement("CreationTime", FormatTime(created)),
                    new XElement("LastModificationTime", FormatTime(modified)),
                    new XElement("LastAccessTime", FormatTime(DateTime.UtcNow)),
                    new XElement("ExpiryTime", FormatTime(expiry ?? DateTime.UtcNow)),
                    new XElement("Expires", expires ? "True" : "False"),
                    new XElement("UsageCount", "1"),
                    new XElement("LocationChanged", FormatTime(modified)));
            }

            XElement BuildEntryEl(KdbxEntry e)
            {
                var el = new XElement("Entry",
                    new XElement("UUID", HexUuidToBase64(e.UuidHex)),
                    new XElement("IconID", (e.IconId == 1048 ? 48 : e.IconId).ToString(CultureInfo.InvariantCulture)));

                if (!string.IsNullOrWhiteSpace(e.CustomIconUuidHex))
                    el.Add(new XElement("CustomIconUUID", HexUuidToBase64(e.CustomIconUuidHex)));

                el.Add(
                    new XElement("Tags", e.Tags ?? ""),
                    BuildTimesEl(e.CreatedUtc, e.ModifiedUtc, e.Expires, e.ExpiresUtc),
                    BuildStringEl("Title", e.Title, false),
                    BuildStringEl("UserName", e.UserName, false),
                    BuildStringEl("Password", e.Password, true),
                    BuildStringEl("URL", e.Url, false),
                    BuildStringEl("Notes", e.Notes, false));

                foreach (var kv in e.CustomStrings)
                {
                    el.Add(BuildStringEl(kv.Key, kv.Value.Value, kv.Value.IsProtected));
                }

                foreach (var att in e.Attachments)
                {
                    int refIdx = binaryPool.Count;
                    binaryPool.Add(att.Data);
                    el.Add(new XElement("Binary",
                        new XElement("Key", att.FileName),
                        new XElement("Value", new XAttribute("Ref", refIdx.ToString(CultureInfo.InvariantCulture)))));
                }

                el.Add(new XElement("AutoType",
                    new XElement("Enabled", "True"),
                    new XElement("DataTransferObfuscation", "0"),
                    new XElement("DefaultSequence", "{USERNAME}{TAB}{PASSWORD}{ENTER}")));

                return el;
            }

            XElement BuildGroupEl(KdbxGroup g)
            {
                var el = new XElement("Group",
                    new XElement("UUID", HexUuidToBase64(g.UuidHex)),
                    new XElement("Name", g.Name ?? "Gruppe"),
                    new XElement("Notes", g.Notes ?? ""),
                    new XElement("IconID", (g.IconId == 1048 ? 48 : g.IconId).ToString(CultureInfo.InvariantCulture)));

                if (!string.IsNullOrWhiteSpace(g.CustomIconUuidHex))
                    el.Add(new XElement("CustomIconUUID", HexUuidToBase64(g.CustomIconUuidHex)));

                el.Add(
                    BuildTimesEl(g.CreatedUtc, g.ModifiedUtc),
                    new XElement("IsExpanded", g.IsExpanded ? "True" : "False"));

                foreach (var entry in g.Entries)
                    el.Add(BuildEntryEl(entry));

                foreach (var sub in g.SubGroups)
                    el.Add(BuildGroupEl(sub));

                return el;
            }

            string nowStr = FormatTime(DateTime.UtcNow);
            var metaEl = new XElement("Meta",
                new XElement("Generator", "Funkenwerk Tresor"),
                new XElement("DatabaseName", db.DatabaseName ?? "DB"),
                new XElement("DatabaseNameChanged", nowStr),
                new XElement("DatabaseDescription", db.DatabaseDescription ?? ""),
                new XElement("DatabaseDescriptionChanged", nowStr),
                new XElement("MaintenanceHistoryDays", "365"),
                new XElement("RecycleBinEnabled", db.RecycleBinEnabled ? "True" : "False"),
                new XElement("RecycleBinUUID", HexUuidToBase64(string.IsNullOrEmpty(db.RecycleBinUuidHex) ? KdbxEntry.NewUuidHex() : db.RecycleBinUuidHex)),
                new XElement("RecycleBinChanged", nowStr),
                new XElement("HistoryMaxItems", "10"),
                new XElement("HistoryMaxSize", "6291456"),
                new XElement("MemoryProtection",
                    new XElement("ProtectTitle", "False"),
                    new XElement("ProtectUserName", "False"),
                    new XElement("ProtectPassword", "True"),
                    new XElement("ProtectURL", "False"),
                    new XElement("ProtectNotes", "False")));

            if (db.CustomIcons.Count > 0)
            {
                var ciEl = new XElement("CustomIcons");
                foreach (var kv in db.CustomIcons)
                {
                    ciEl.Add(new XElement("Icon",
                        new XElement("UUID", HexUuidToBase64(kv.Key)),
                        new XElement("Data", Convert.ToBase64String(kv.Value))));
                }
                metaEl.Add(ciEl);
            }

            var doc = new XDocument(
                new XDeclaration("1.0", "utf-8", "yes"),
                new XElement("KeePassFile",
                    metaEl,
                    new XElement("Root",
                        BuildGroupEl(db.RootGroup),
                        new XElement("DeletedObjects"))));

            using var outMs = new MemoryStream();
            using (var sw = new StreamWriter(outMs, new UTF8Encoding(false), 4096, leaveOpen: true))
            {
                doc.Save(sw);
            }
            return outMs.ToArray();
        }

        private static string Base64UuidToHex(string? base64)
        {
            if (string.IsNullOrWhiteSpace(base64)) return KdbxEntry.NewUuidHex();
            try
            {
                byte[] bytes = Convert.FromBase64String(base64.Trim());
                if (bytes.Length == 16) return Convert.ToHexString(bytes);
            }
            catch { }
            return KdbxEntry.NewUuidHex();
        }

        private static string HexUuidToBase64(string? hex)
        {
            if (!string.IsNullOrWhiteSpace(hex) && hex.Length == 32)
            {
                try
                {
                    return Convert.ToBase64String(Convert.FromHexString(hex));
                }
                catch { }
            }
            return Convert.ToBase64String(RandomNumberGenerator.GetBytes(16));
        }

        private static DateTime ParseKdbxTime(string? raw)
        {
            if (string.IsNullOrWhiteSpace(raw)) return DateTime.UtcNow;
            string s = raw.Trim();

            if (s.Contains('-') && s.Contains(':'))
            {
                if (DateTime.TryParse(s, CultureInfo.InvariantCulture, DateTimeStyles.AdjustToUniversal | DateTimeStyles.AssumeUniversal, out var dt))
                    return dt;
            }

            try
            {
                byte[] b = Convert.FromBase64String(s);
                if (b.Length >= 8)
                {
                    long secs = BitConverter.ToInt64(b, 0);
                    if (secs > 0 && secs < 315537897599L)
                        return KdbxEpochUtc.AddSeconds(secs);
                }
            }
            catch { }

            return DateTime.UtcNow;
        }

        private static byte[] CompressGzip(byte[] plain)
        {
            using var outMs = new MemoryStream();
            using (var gz = new GZipStream(outMs, CompressionLevel.Optimal, leaveOpen: true))
            {
                gz.Write(plain, 0, plain.Length);
            }
            return outMs.ToArray();
        }

        private static byte[] DecompressGzip(byte[] compressed)
        {
            using var inMs = new MemoryStream(compressed, writable: false);
            using var gz = new GZipStream(inMs, CompressionMode.Decompress);
            using var outMs = new MemoryStream();
            gz.CopyTo(outMs);
            return outMs.ToArray();
        }
    }
}
