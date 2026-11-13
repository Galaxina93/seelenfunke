using System;
using System.Collections.Generic;
using System.IO;
using System.Runtime.InteropServices.ComTypes;
using System.Windows;
using ComIDataObject = System.Runtime.InteropServices.ComTypes.IDataObject;
using WpfIDataObject = System.Windows.IDataObject;

namespace FunkenwerkFtp
{
    /// <summary>
    /// Provides both internal zero-latency drag metadata (DRAG_FORMAT_REMOTE) for drops inside Funkenwerk FTP
    /// and on-demand CF_HDROP (DataFormats.FileDrop) staging when the user drags remote server files out
    /// onto the Windows Desktop, Windows Explorer, PhpStorm, Browser, or Funkenwerk OS.
    /// </summary>
    public sealed class DelayedRemoteFileDropDataObject : WpfIDataObject, ComIDataObject
    {
        private const short CF_HDROP = 15;

        private readonly DataObject _inner = new();
        private readonly Func<string[]?> _stageRemoteFilesCallback;
        private bool _hasAttemptedStaging;
        private string[]? _stagedFiles;

        public bool WasStagedExternally => _hasAttemptedStaging;

        public DelayedRemoteFileDropDataObject(string internalFormat, object internalPayload, Func<string[]?> stageRemoteFilesCallback)
        {
            _stageRemoteFilesCallback = stageRemoteFilesCallback ?? throw new ArgumentNullException(nameof(stageRemoteFilesCallback));
            _inner.SetData(internalFormat, internalPayload);
            // Seed a placeholder format registration on _inner so EnumFormatEtc & QueryGetData advertise CF_HDROP
            // without touching disk until an external drop target actually requests GetData(CF_HDROP).
            string placeholderPath = Path.Combine(DragStagingManager.RootStagingDir, "_drag_ready_placeholder");
            _inner.SetData(DataFormats.FileDrop, new[] { placeholderPath });
        }

        private void EnsureStagedForExternalDrop()
        {
            if (_hasAttemptedStaging) return;
            _hasAttemptedStaging = true;
            try
            {
                _stagedFiles = _stageRemoteFilesCallback();
                if (_stagedFiles != null && _stagedFiles.Length > 0)
                {
                    _inner.SetData(DataFormats.FileDrop, _stagedFiles);
                }
            }
            catch
            {
                _stagedFiles = null;
            }
        }

        // =====================================================================
        // System.Windows.IDataObject (Used inside WPF / Funkenwerk FTP)
        // =====================================================================
        public object? GetData(string format) => GetData(format, true);

        public object? GetData(Type format) => format != null ? GetData(format.FullName ?? "", true) : null;

        public object? GetData(string format, bool autoConvert)
        {
            if (string.Equals(format, DataFormats.FileDrop, StringComparison.OrdinalIgnoreCase) ||
                string.Equals(format, "FileNameW", StringComparison.OrdinalIgnoreCase) ||
                string.Equals(format, "FileName", StringComparison.OrdinalIgnoreCase))
            {
                EnsureStagedForExternalDrop();
                if (_stagedFiles == null || _stagedFiles.Length == 0)
                    return null;
                return _stagedFiles;
            }
            return _inner.GetData(format, autoConvert);
        }

        public bool GetDataPresent(string format) => _inner.GetDataPresent(format);
        public bool GetDataPresent(Type format) => _inner.GetDataPresent(format);
        public bool GetDataPresent(string format, bool autoConvert) => _inner.GetDataPresent(format, autoConvert);

        public string[] GetFormats() => _inner.GetFormats();
        public string[] GetFormats(bool autoConvert) => _inner.GetFormats(autoConvert);

        public void SetData(object data) => _inner.SetData(data);
        public void SetData(string format, object data) => _inner.SetData(format, data);
        public void SetData(Type format, object data) => _inner.SetData(format, data);
        public void SetData(string format, object data, bool autoConvert) => _inner.SetData(format, data, autoConvert);

        // =====================================================================
        // System.Runtime.InteropServices.ComTypes.IDataObject (Win32 / OLE Shell)
        // =====================================================================
        void ComIDataObject.GetData(ref FORMATETC format, out STGMEDIUM medium)
        {
            if (IsShellFileFormat(format.cfFormat))
            {
                EnsureStagedForExternalDrop();
                if (_stagedFiles == null || _stagedFiles.Length == 0)
                {
                    // DV_E_FORMATETC (0x80040064) cancels external drop cleanly if unstageable
                    throw new System.Runtime.InteropServices.COMException("Keine externen Drag-Dateien verfügbar.", unchecked((int)0x80040064));
                }
            }
            ((ComIDataObject)_inner).GetData(ref format, out medium);
        }

        void ComIDataObject.GetDataHere(ref FORMATETC format, ref STGMEDIUM medium)
        {
            if (IsShellFileFormat(format.cfFormat))
            {
                EnsureStagedForExternalDrop();
            }
            ((ComIDataObject)_inner).GetDataHere(ref format, ref medium);
        }

        int ComIDataObject.QueryGetData(ref FORMATETC format)
        {
            // Never stage during QueryGetData! Just advertise format availability in 0ms.
            return ((ComIDataObject)_inner).QueryGetData(ref format);
        }

        int ComIDataObject.GetCanonicalFormatEtc(ref FORMATETC formatIn, out FORMATETC formatOut)
        {
            return ((ComIDataObject)_inner).GetCanonicalFormatEtc(ref formatIn, out formatOut);
        }

        void ComIDataObject.SetData(ref FORMATETC formatIn, ref STGMEDIUM medium, bool release)
        {
            ((ComIDataObject)_inner).SetData(ref formatIn, ref medium, release);
        }

        IEnumFORMATETC ComIDataObject.EnumFormatEtc(DATADIR direction)
        {
            return ((ComIDataObject)_inner).EnumFormatEtc(direction);
        }

        int ComIDataObject.DAdvise(ref FORMATETC pFormatetc, ADVF advf, IAdviseSink adviseSink, out int connection)
        {
            return ((ComIDataObject)_inner).DAdvise(ref pFormatetc, advf, adviseSink, out connection);
        }

        void ComIDataObject.DUnadvise(int connection)
        {
            ((ComIDataObject)_inner).DUnadvise(connection);
        }

        int ComIDataObject.EnumDAdvise(out IEnumSTATDATA? enumAdvise)
        {
            return ((ComIDataObject)_inner).EnumDAdvise(out enumAdvise);
        }

        private static bool IsShellFileFormat(short cfFormat)
        {
            if (cfFormat == CF_HDROP) return true;
            short fileNameW = (short)(DataFormats.GetDataFormat("FileNameW").Id & 0xFFFF);
            short fileNameA = (short)(DataFormats.GetDataFormat("FileName").Id & 0xFFFF);
            return cfFormat == fileNameW || cfFormat == fileNameA;
        }
    }

    public static class DragStagingManager
    {
        public static string RootStagingDir
        {
            get
            {
                string dir = Path.Combine(FunkenwerkFtpSecurity.VaultDir, "DragStage");
                Directory.CreateDirectory(dir);
                return dir;
            }
        }

        public static string CreateIsolatedBatchDir()
        {
            PruneOldBatches(TimeSpan.FromMinutes(30));
            string batch = Path.Combine(RootStagingDir, DateTime.UtcNow.ToString("yyyyMMdd_HHmmss_") + Guid.NewGuid().ToString("N")[..8]);
            Directory.CreateDirectory(batch);
            return batch;
        }

        public static void PruneOldBatches(TimeSpan maxAge)
        {
            try
            {
                string root = Path.Combine(FunkenwerkFtpSecurity.VaultDir, "DragStage");
                if (!Directory.Exists(root)) return;
                DateTime cutoff = DateTime.UtcNow - maxAge;
                foreach (string sub in Directory.GetDirectories(root))
                {
                    try
                    {
                        if (Directory.GetCreationTimeUtc(sub) < cutoff)
                            Directory.Delete(sub, recursive: true);
                    }
                    catch { }
                }
            }
            catch { }
        }

        public static void PurgeAllStagingDirs()
        {
            try
            {
                string root = Path.Combine(FunkenwerkFtpSecurity.VaultDir, "DragStage");
                if (Directory.Exists(root))
                    Directory.Delete(root, recursive: true);
            }
            catch { }
        }
    }
}
