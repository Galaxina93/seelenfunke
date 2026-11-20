param(
    [string]$RemoteUrl = "",
    [string]$AdminToken = "",
    [string]$ReleaseNotes = "",
    [switch]$SkipBuild,
    [switch]$SkipStage
)

$ErrorActionPreference = "Stop"

$wslProjectRoot = "\\wsl.localhost\Ubuntu\home\ubuntuxina\meine-projekte\seelenfunke"
$wslAndroidRoot = "$wslProjectRoot\android-app"
$wslHomeRoot = Split-Path -Parent (Split-Path -Parent $wslProjectRoot)
$winBuildMirror = Join-Path $env:USERPROFILE "AndroidStudioProjects\seelenfunke-android"
$pinnedCertSha256 = "e4c85cdd79648fa19430c91b47e1db036544d60d2fa6136a0c5106300c849064"

$hasWindowsJava = [bool]($env:JAVA_HOME -and (Test-Path "$env:JAVA_HOME\bin\java.exe"))
if (-not $hasWindowsJava) {
    $jbrCandidates = @(
        "C:\Program Files\JetBrains\PhpStorm 2025.3.2\jbr",
        "C:\Program Files\Android\Android Studio\jbr"
    )
    foreach ($cand in $jbrCandidates) {
        if (Test-Path "$cand\bin\java.exe") {
            $env:JAVA_HOME = $cand
            $hasWindowsJava = $true
            break
        }
    }
}

$gradleKts = Get-Content "$wslAndroidRoot\app\build.gradle.kts" -Raw
$verCodeMatch = [regex]::Match($gradleKts, 'versionCode\s*=\s*(\d+)')
$verNameMatch = [regex]::Match($gradleKts, 'versionName\s*=\s*"([^"]+)"')
if (-not $verCodeMatch.Success -or -not $verNameMatch.Success) {
    throw "versionCode oder versionName konnte nicht aus app/build.gradle.kts gelesen werden."
}
$versionCode = [int]$verCodeMatch.Groups[1].Value
$versionName = $verNameMatch.Groups[1].Value

Write-Host "[1/4] Synchronisiere Funkenwerk Android Quellcode (v$versionName, Build $versionCode)..." -ForegroundColor Cyan
New-Item -ItemType Directory -Path "$winBuildMirror\app\src" -Force | Out-Null
robocopy "$wslAndroidRoot\app\src" "$winBuildMirror\app\src" /MIR /NFL /NDL /NJH /NJS | Out-Null
Copy-Item "$wslAndroidRoot\app\build.gradle.kts" "$winBuildMirror\app\build.gradle.kts" -Force
Copy-Item "$wslAndroidRoot\app\proguard-rules.pro" "$winBuildMirror\app\proguard-rules.pro" -Force
Copy-Item "$wslAndroidRoot\.gitignore" "$winBuildMirror\.gitignore" -Force

$apkPath = "$winBuildMirror\app\build\outputs\apk\release\app-release.apk"

if (-not $SkipBuild) {
    Write-Host "[2/4] Baue R8-minifiziertes & 4096-Bit-RSA-signiertes Release-APK..." -ForegroundColor Cyan
    if ($hasWindowsJava -and (Test-Path "$winBuildMirror\gradlew.bat")) {
        Push-Location $winBuildMirror
        try {
            .\gradlew.bat assembleRelease --no-daemon
            if ($LASTEXITCODE -ne 0) {
                throw "Gradle assembleRelease fehlgeschlagen (Exit-Code $LASTEXITCODE)."
            }
        } finally {
            Pop-Location
        }
    } else {
        Write-Host "      Nutze WSL OpenJDK 21 + Android SDK fuer Release-Build..." -ForegroundColor DarkCyan
        $ksCandidates = @(
            (Join-Path $env:USERPROFILE ".android\signing\keystore.properties"),
            "$wslHomeRoot\.android\signing\keystore.properties",
            "$wslAndroidRoot\keystore.properties"
        )
        $ksPropsPath = $ksCandidates | Where-Object { Test-Path $_ -ErrorAction SilentlyContinue } | Select-Object -First 1
        if (-not $ksPropsPath) {
            throw "Release-Keystore nicht gefunden (geprueft: $($ksCandidates -join ', ')). Bitte funkenwerk-release.jks + keystore.properties unter ~/.android/signing/ ablegen."
        }
        $ksMap = @{}
        foreach ($line in (Get-Content $ksPropsPath)) {
            if ($line -match '^\s*([^#=]+?)\s*=\s*(.+?)\s*$') {
                $ksMap[$matches[1]] = $matches[2]
            }
        }
        $rawStore = $ksMap["storeFile"] -replace '\\', '/'
        if ($rawStore -match '^([A-Za-z]):/(.*)$') {
            $wslStore = "/mnt/" + $matches[1].ToLowerInvariant() + "/" + $matches[2]
        } else {
            $wslStore = $rawStore
        }
        $prevWslEnv = $env:WSLENV
        $env:SIGNING_STORE_FILE = $wslStore
        $env:SIGNING_STORE_PASSWORD = $ksMap["storePassword"]
        $env:SIGNING_KEY_ALIAS = $ksMap["keyAlias"]
        $env:SIGNING_KEY_PASSWORD = $ksMap["keyPassword"]
        $env:WSLENV = "SIGNING_STORE_FILE/u:SIGNING_STORE_PASSWORD/u:SIGNING_KEY_ALIAS/u:SIGNING_KEY_PASSWORD/u"
        try {
            wsl -d Ubuntu bash -c "cd /home/ubuntuxina/meine-projekte/seelenfunke/android-app && ./gradlew assembleRelease --no-daemon"
            if ($LASTEXITCODE -ne 0) {
                throw "WSL Gradle assembleRelease fehlgeschlagen (Exit-Code $LASTEXITCODE)."
            }
        } finally {
            Remove-Item Env:\SIGNING_STORE_FILE -ErrorAction SilentlyContinue
            Remove-Item Env:\SIGNING_STORE_PASSWORD -ErrorAction SilentlyContinue
            Remove-Item Env:\SIGNING_KEY_ALIAS -ErrorAction SilentlyContinue
            Remove-Item Env:\SIGNING_KEY_PASSWORD -ErrorAction SilentlyContinue
            $env:WSLENV = $prevWslEnv
        }
        $wslApkSource = "$wslAndroidRoot\app\build\outputs\apk\release\app-release.apk"
        $apkDir = Split-Path -Parent $apkPath
        if (-not (Test-Path $apkDir)) {
            New-Item -ItemType Directory -Path $apkDir -Force | Out-Null
        }
        Copy-Item $wslApkSource $apkPath -Force
    }
}

if (-not (Test-Path $apkPath)) {
    throw "Signiertes Release-APK wurde nicht gefunden: $apkPath"
}

Write-Host "[3/4] Prüfe X.509-Release-Signatur & SHA-256-Fingerprint..." -ForegroundColor Cyan
if ($hasWindowsJava) {
    $certOut = & "$env:JAVA_HOME\bin\keytool.exe" -printcert -jarfile $apkPath | Out-String
} else {
    $wslVerifyApk = "/mnt/c" + ($apkPath.Substring(2) -replace '\\', '/')
    $certOut = wsl -d Ubuntu -- keytool -printcert -jarfile $wslVerifyApk | Out-String
}
$certShaMatch = [regex]::Match($certOut, 'SHA256:\s*([0-9A-Fa-f:]{95})')
if (-not $certShaMatch.Success) {
    throw "X.509-Zertifikat im APK konnte nicht verifiziert werden."
}
$actualCertHex = ($certShaMatch.Groups[1].Value -replace ':', '').ToLowerInvariant()
if ($actualCertHex -ne $pinnedCertSha256) {
    throw "Sicherheitsabbruch: APK-Zertifikat ($actualCertHex) stimmt NICHT mit dem gepinnten Funkenwerk-Release-Key ($pinnedCertSha256) überein!"
}

$apkSha256 = (Get-FileHash -Path $apkPath -Algorithm SHA256).Hash.ToLowerInvariant()
$apkSizeMb = [Math]::Round((Get-Item $apkPath).Length / 1MB, 2)
Write-Host "      Zertifikat verifiziert! APK-Größe: $apkSizeMb MB | SHA-256: $apkSha256" -ForegroundColor Green

Write-Host "[4/4] Aktiviere Release im privaten Laravel-OTA-Tresor (storage/app/private/releases/android/)..." -ForegroundColor Cyan
$winMountApk = "/mnt/c" + ($apkPath.Substring(2) -replace '\\', '/')
wsl -d Ubuntu bash -c "docker exec mein_php_server mkdir -p /var/www/html/storage/app/private/releases/android && docker exec mein_php_server chown -R www-data:www-data /var/www/html/storage/app/private/releases && cat '$winMountApk' | docker exec -u www-data -i mein_php_server sh -c 'cat > /tmp/_ota_stage_upload.apk'"
if ($LASTEXITCODE -ne 0) {
    throw "Temporärer Container-Transfer der APK fehlgeschlagen."
}

$payloadObj = @{
    version_code  = $versionCode
    version_name  = $versionName
    sha256        = $apkSha256
    release_notes = $ReleaseNotes
}
$payloadB64 = [Convert]::ToBase64String([System.Text.Encoding]::UTF8.GetBytes(($payloadObj | ConvertTo-Json -Compress)))

$phpScript = @'
<?php
require '/var/www/html/vendor/autoload.php';
$app = require_once '/var/www/html/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$tmp = '/tmp/_ota_stage_upload.apk';
try {
    $in = json_decode(base64_decode($argv[1]), true, 512, JSON_THROW_ON_ERROR);
    $svc = app(\App\Services\System\AndroidReleaseService::class);
    $m = $svc->storeVerifiedRelease(
        $tmp,
        (int) $in['version_code'],
        (string) $in['version_name'],
        (string) $in['sha256'],
        (string) ($in['release_notes'] ?? ''),
        true
    );
    @unlink($tmp);
    echo json_encode($m, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (\Throwable $e) {
    @unlink($tmp);
    fwrite(STDERR, $e->getMessage());
    exit(1);
}
'@

$manifestJson = $phpScript | wsl -d Ubuntu -- docker exec -u www-data -i mein_php_server php -- $payloadB64
if ($LASTEXITCODE -ne 0) {
    throw "Lokale Tresor-Aktivierung fehlgeschlagen: $manifestJson"
}
Write-Host "      Lokal veröffentlicht: $manifestJson" -ForegroundColor Green

if (-not $SkipStage) {
    $mcpCandidates = @(
        (Join-Path $env:LOCALAPPDATA "FunkenwerkOS\McpBuild\FunkenwerkMcp.exe"),
        (Join-Path $env:USERPROFILE "Desktop\Funkenwerk Collection\Funkenwerk MCP\FunkenwerkMcp.exe"),
        "C:\Users\konta\AppData\Local\FunkenwerkOS\McpBuild\FunkenwerkMcp.exe",
        "C:\Users\konta\Desktop\Funkenwerk Collection\Funkenwerk MCP\FunkenwerkMcp.exe"
    )
    $mcpExe = $mcpCandidates | Where-Object { Test-Path $_ -ErrorAction SilentlyContinue } | Select-Object -First 1

    Write-Host "[5/5] Veröffentliche signiertes Release per Ed25519-Key-Only auf Stage (stage.mein-seelenfunke.de)..." -ForegroundColor Cyan
    if ($mcpExe) {
        $stageArgs = @(
            "--stage-publish-apk",
            "--apk", $apkPath,
            "--version-code", "$versionCode",
            "--version-name", $versionName,
            "--notes", $ReleaseNotes
        )
        & $mcpExe @stageArgs
        if ($LASTEXITCODE -ne 0) {
            throw "Stage-APK-Publish fehlgeschlagen (Exit-Code $LASTEXITCODE)."
        }
    } else {
        $wslStageApk = "/mnt/" + $apkPath.Substring(0, 1).ToLowerInvariant() + ($apkPath.Substring(2) -replace '\\', '/')
        $stageSshCmd = "ssh -i /home/ubuntuxina/.ssh/id_ed25519 -o BatchMode=yes -o ConnectTimeout=15 kontakt@mein-seelenfunke.de@a-lc6tkm@ssh.altgemeinde.project.host"
        wsl -d Ubuntu bash -c "cat '$wslStageApk' | $stageSshCmd 'cat > /tmp/_ota_stage_upload.apk'"
        if ($LASTEXITCODE -ne 0) {
            throw "Stage-SSH-Transfer der signierten APK fehlgeschlagen."
        }
        $stagePhpScript = $phpScript -replace '/var/www/html', '/home/p-g27wim/html/seelenfunke-stage'
        $stageManifestOut = $stagePhpScript | wsl -d Ubuntu bash -c "$stageSshCmd 'php -- $payloadB64'"
        if ($LASTEXITCODE -ne 0) {
            throw "Stage-Tresor-Aktivierung fehlgeschlagen: $stageManifestOut"
        }
        Write-Host "      Stage veröffentlicht (Ed25519 SSH): $stageManifestOut" -ForegroundColor Green
    }
}

if ($RemoteUrl -and $AdminToken) {
    $cleanBase = $RemoteUrl.TrimEnd('/')
    $publishEndpoint = "$cleanBase/api/funki/system/app-update/publish"
    Write-Host "[Optional] Übertrage signiertes Release verschlüsselt an $publishEndpoint ..." -ForegroundColor Cyan
    $curlArgs = @(
        "-sS", "-f", "-X", "POST", $publishEndpoint,
        "-H", "Authorization: Bearer $AdminToken",
        "-H", "Accept: application/json",
        "-F", "apk=@$apkPath;type=application/vnd.android.package-archive",
        "-F", "version_code=$versionCode",
        "-F", "version_name=$versionName",
        "-F", "sha256=$apkSha256",
        "-F", "release_notes=$ReleaseNotes"
    )
    $remoteResp = & curl.exe @curlArgs
    if ($LASTEXITCODE -ne 0) {
        throw "Remote-OTA-Upload fehlgeschlagen."
    }
    Write-Host "      Remote veröffentlicht: $remoteResp" -ForegroundColor Green
}
