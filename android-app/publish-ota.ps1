param(
    [string]$RemoteUrl = "",
    [string]$AdminToken = "",
    [string]$ReleaseNotes = "",
    [switch]$SkipBuild
)

$ErrorActionPreference = "Stop"

$wslProjectRoot = "\\wsl.localhost\Ubuntu\home\ubuntuxina\meine-projekte\seelenfunke"
$wslAndroidRoot = "$wslProjectRoot\android-app"
$winBuildMirror = "C:\Users\konta\AndroidStudioProjects\seelenfunke-android"
$pinnedCertSha256 = "e4c85cdd79648fa19430c91b47e1db036544d60d2fa6136a0c5106300c849064"

if (-not $env:JAVA_HOME -or -not (Test-Path "$env:JAVA_HOME\bin\java.exe")) {
    $jbr = "C:\Program Files\JetBrains\PhpStorm 2025.3.2\jbr"
    if (Test-Path "$jbr\bin\java.exe") {
        $env:JAVA_HOME = $jbr
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
robocopy "$wslAndroidRoot\app\src" "$winBuildMirror\app\src" /MIR /NFL /NDL /NJH /NJS | Out-Null
Copy-Item "$wslAndroidRoot\app\build.gradle.kts" "$winBuildMirror\app\build.gradle.kts" -Force
Copy-Item "$wslAndroidRoot\app\proguard-rules.pro" "$winBuildMirror\app\proguard-rules.pro" -Force
Copy-Item "$wslAndroidRoot\.gitignore" "$winBuildMirror\.gitignore" -Force

$apkPath = "$winBuildMirror\app\build\outputs\apk\release\app-release.apk"

if (-not $SkipBuild) {
    Write-Host "[2/4] Baue R8-minifiziertes & 4096-Bit-RSA-signiertes Release-APK..." -ForegroundColor Cyan
    Push-Location $winBuildMirror
    try {
        .\gradlew.bat assembleRelease --no-daemon
        if ($LASTEXITCODE -ne 0) {
            throw "Gradle assembleRelease fehlgeschlagen (Exit-Code $LASTEXITCODE)."
        }
    } finally {
        Pop-Location
    }
}

if (-not (Test-Path $apkPath)) {
    throw "Signiertes Release-APK wurde nicht gefunden: $apkPath"
}

Write-Host "[3/4] Prüfe X.509-Release-Signatur & SHA-256-Fingerprint..." -ForegroundColor Cyan
$certOut = & "$env:JAVA_HOME\bin\keytool.exe" -printcert -jarfile $apkPath | Out-String
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
