param(
    [string]$Version = '2026.07.21-rc1'
)

$ErrorActionPreference = 'Stop'
$workspace = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$buildRoot = Join-Path $workspace ('_release_build_' + [guid]::NewGuid().ToString('N'))
$packageRoot = Join-Path $buildRoot 'mororide'
$releaseDir = Join-Path $workspace 'releases'
$zipPath = Join-Path $releaseDir "MoroRide-server-$Version.zip"

if (-not $buildRoot.StartsWith($workspace, [System.StringComparison]::OrdinalIgnoreCase)) {
    throw 'Release staging path escaped the workspace.'
}

New-Item -ItemType Directory -Path $packageRoot -Force | Out-Null
New-Item -ItemType Directory -Path $releaseDir -Force | Out-Null

$backendTarget = Join-Path $packageRoot 'backend'
$backendSource = Join-Path $workspace 'backend'
robocopy $backendSource $backendTarget /E /XJ /R:1 /W:1 /XD node_modules .git (Join-Path $backendSource 'public\storage') (Join-Path $backendSource 'storage\framework\cache') (Join-Path $backendSource 'storage\framework\sessions') (Join-Path $backendSource 'storage\framework\views') /XF .env .env.* *.log .phpunit.result.cache | Out-Null
if ($LASTEXITCODE -gt 7) { throw "Backend copy failed with robocopy code $LASTEXITCODE" }
foreach ($runtimeDirectory in @('storage\app\public', 'storage\framework\cache', 'storage\framework\sessions', 'storage\framework\views', 'storage\logs')) {
    New-Item -ItemType Directory -Path (Join-Path $backendTarget $runtimeDirectory) -Force | Out-Null
}

$mobileTarget = Join-Path $packageRoot 'mobile'
robocopy (Join-Path $workspace 'mobile') $mobileTarget /E /XJ /R:1 /W:1 /XD node_modules .expo dist dist-check build .gradle /XF .env .env.* *.log | Out-Null
if ($LASTEXITCODE -gt 7) { throw "Mobile copy failed with robocopy code $LASTEXITCODE" }

foreach ($directory in @('deployment', 'docs')) {
    robocopy (Join-Path $workspace $directory) (Join-Path $packageRoot $directory) /E /XJ /R:1 /W:1 | Out-Null
    if ($LASTEXITCODE -gt 7) { throw "$directory copy failed with robocopy code $LASTEXITCODE" }
}

foreach ($file in @('README.md', 'TESTING.md', 'INSTALL-MORORIDE.md', 'PROJECT-STATUS.md', 'RELEASE-MANIFEST.md', 'docker-compose.yml')) {
    Copy-Item -LiteralPath (Join-Path $workspace $file) -Destination (Join-Path $packageRoot $file)
}

# Restore the safe production template excluded by the .env.* rule.
Copy-Item -LiteralPath (Join-Path $workspace 'backend\.env.production.example') -Destination (Join-Path $backendTarget '.env.production.example')
Copy-Item -LiteralPath (Join-Path $workspace 'backend\.env.example') -Destination (Join-Path $backendTarget '.env.example')

Get-ChildItem -LiteralPath $packageRoot -Recurse -File | Where-Object {
    $_.Name -eq '.env' -or $_.Name -eq '.env.local' -or $_.FullName -match '\\storage\\logs\\.*\.log$'
} | ForEach-Object { throw "Sensitive/runtime file reached the package: $($_.FullName)" }

if (Test-Path -LiteralPath $zipPath) {
    Remove-Item -LiteralPath $zipPath -Force
}
Push-Location $buildRoot
try {
    & 7z.exe a -tzip -mx=5 $zipPath 'mororide\*' | Out-Null
    if ($LASTEXITCODE -ne 0) { throw "Archive creation failed with 7-Zip code $LASTEXITCODE" }
} finally {
    Pop-Location
}

$hash = (Get-FileHash -LiteralPath $zipPath -Algorithm SHA256).Hash.ToLowerInvariant()
$checksumPath = "$zipPath.sha256.txt"
Set-Content -LiteralPath $checksumPath -Value "$hash  $(Split-Path $zipPath -Leaf)" -Encoding ascii

try {
    Remove-Item -LiteralPath $buildRoot -Recurse -Force
} catch {
    Write-Warning "Release created, but temporary staging cleanup must be retried: $buildRoot"
}

[pscustomobject]@{
    Release = $zipPath
    SizeMB = [math]::Round((Get-Item -LiteralPath $zipPath).Length / 1MB, 2)
    SHA256 = $hash
}
