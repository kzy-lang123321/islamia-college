# Build and Packaging Automation Script for ICT Management System
# Generates production-ready standalone Windows installer .exe

param (
    [switch]$SkipTests = $false
)

$ErrorActionPreference = "Stop"
$ProjectRoot = $PSScriptRoot
$UiDir = Join-Path $ProjectRoot "src\ICTManagementSystem.UI"
$AppDir = Join-Path $ProjectRoot "src\ICTManagementSystem.App"
$AppWwwroot = Join-Path $AppDir "wwwroot"
$AppProj = Join-Path $AppDir "ICTManagementSystem.App.csproj"
$TestsProj = Join-Path $ProjectRoot "tests\ICTManagementSystem.Tests\ICTManagementSystem.Tests.csproj"
$PublishDir = Join-Path $ProjectRoot "output\publish"
$OutputDir = Join-Path $ProjectRoot "output"
$InstallerScript = Join-Path $ProjectRoot "installer\installer.iss"

function Assert-BuildOutputPath([string]$PathToCheck) {
    $rootPath = [IO.Path]::GetFullPath($ProjectRoot).TrimEnd('\') + '\'
    $resolvedPath = [IO.Path]::GetFullPath($PathToCheck)
    if (-not $resolvedPath.StartsWith($rootPath, [StringComparison]::OrdinalIgnoreCase)) {
        throw "Build output path is outside the desktop project: $resolvedPath"
    }
}

Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host "   ICT Management System - Build & Packaging Automation   " -ForegroundColor Cyan
Write-Host "==========================================================" -ForegroundColor Cyan

# 1. Check tools
Write-Host "`n[1/5] Checking build dependencies..." -ForegroundColor Yellow
if (-not (Get-Command dotnet -ErrorAction SilentlyContinue)) {
    throw ".NET SDK is required on PATH."
}
if (-not (Get-Command npm -ErrorAction SilentlyContinue)) {
    throw "Node.js / npm is required on PATH."
}

# Locate Inno Setup compiler
$InnoCompiler = $null
$PossibleInnoPaths = @(
    "$env:LOCALAPPDATA\Programs\Inno Setup 6\ISCC.exe",
    "$env:ProgramFiles\Inno Setup 6\ISCC.exe",
    "${env:ProgramFiles(x86)}\Inno Setup 6\ISCC.exe"
)
foreach ($p in $PossibleInnoPaths) {
    if (Test-Path $p) {
        $InnoCompiler = $p
        break
    }
}
if (-not $InnoCompiler) {
    $cmd = Get-Command iscc -ErrorAction SilentlyContinue
    if ($cmd) { $InnoCompiler = $cmd.Source }
}
if (-not $InnoCompiler) {
    throw "Inno Setup compiler (ISCC.exe) was not found in ProgramFiles or LocalAppData."
}
Write-Host " -> Inno Setup Compiler found: $InnoCompiler" -ForegroundColor Green

# 2. Build React Frontend
Write-Host "`n[2/5] Building React + Tailwind frontend (npm ci && npm run build)..." -ForegroundColor Yellow
Push-Location $UiDir
try {
    npm ci
    if ($LASTEXITCODE -ne 0) { throw "npm ci failed." }
    npm run build
    if ($LASTEXITCODE -ne 0) { throw "UI build failed." }
} finally {
    Pop-Location
}

$UiDist = Join-Path $UiDir "dist"
$UiDistIndex = Join-Path $UiDist "index.html"
if (-not (Test-Path $UiDistIndex)) {
    throw "CRITICAL ERROR: ui/dist/index.html is missing after frontend build! Aborting packaging."
}

# Delete any existing/placeholder index.html in App/wwwroot
if (Test-Path $AppWwwroot) {
    Write-Host " -> Cleaning existing App/wwwroot directory..." -ForegroundColor DarkGray
    Assert-BuildOutputPath $AppWwwroot
    Remove-Item -LiteralPath $AppWwwroot -Recurse -Force
}
New-Item -ItemType Directory -Force -Path $AppWwwroot | Out-Null

# Copy ui/dist/* into App/wwwroot AFTER frontend build and BEFORE dotnet publish
Copy-Item -Path "$UiDist\*" -Destination $AppWwwroot -Recurse -Force

# Verify that App/wwwroot/index.html exists and is not a placeholder
$AppIndex = Join-Path $AppWwwroot "index.html"
if (-not (Test-Path $AppIndex)) {
    throw "CRITICAL ERROR: Failed to copy frontend assets to '$AppWwwroot'!"
}
$indexContent = Get-Content $AppIndex -Raw
if ($indexContent -match "Building frontend assets") {
    throw "CRITICAL ERROR: App/wwwroot/index.html contains placeholder text instead of real React bundle!"
}
Write-Host " -> Frontend build verified. Copied to $AppWwwroot successfully." -ForegroundColor Green

# 3. Run Automated Tests
if (-not $SkipTests) {
    Write-Host "`n[3/5] Executing automated tests (C# domain, SQLite WAL, sync, fee rules)..." -ForegroundColor Yellow
    dotnet test $TestsProj -c Release --no-restore
    if ($LASTEXITCODE -ne 0) { throw "Automated tests failed." }
    Write-Host " -> All automated tests passed." -ForegroundColor Green
} else {
    Write-Host "`n[3/5] Skipping tests as requested." -ForegroundColor DarkGray
}

# 4. Publish .NET 8 Win-x64 Host
Write-Host "`n[4/5] Publishing self-contained win-x64 single-file executable..." -ForegroundColor Yellow
if (Test-Path $PublishDir) {
    Assert-BuildOutputPath $PublishDir
    Remove-Item -LiteralPath $PublishDir -Recurse -Force
}
dotnet publish $AppProj -c Release -r win-x64 --self-contained true -p:PublishSingleFile=true -o $PublishDir
if ($LASTEXITCODE -ne 0) { throw "Desktop publish failed." }
Copy-Item (Join-Path $AppDir "app.ico") -Destination $PublishDir -Force

# Ensure published output contains wwwroot with verified index.html
$PublishWwwroot = Join-Path $PublishDir "wwwroot"
if (-not (Test-Path (Join-Path $PublishWwwroot "index.html"))) {
    Write-Host " -> Copying wwwroot to publish directory..." -ForegroundColor Yellow
    Copy-Item -Path $AppWwwroot -Destination $PublishDir -Recurse -Force
}

if (-not (Test-Path (Join-Path $PublishWwwroot "index.html"))) {
    throw "CRITICAL ERROR: wwwroot/index.html is missing in publish directory: $PublishWwwroot"
}
Write-Host " -> Binaries and frontend wwwroot published to $PublishDir" -ForegroundColor Green

# 5. Compile Inno Setup Installer
Write-Host "`n[5/5] Compiling Inno Setup installer package..." -ForegroundColor Yellow
& $InnoCompiler $InstallerScript
if ($LASTEXITCODE -ne 0) { throw "Installer compilation failed." }

$FinalInstaller = Join-Path $OutputDir "Setup_ICTManagementSystem_v1.1.0.exe"
if (Test-Path $FinalInstaller) {
    $item = Get-Item $FinalInstaller
    $hash = (Get-FileHash $FinalInstaller -Algorithm SHA256).Hash
    Write-Host "`n==========================================================" -ForegroundColor Green
    Write-Host " SUCCESS: Installer generated successfully!" -ForegroundColor Green
    Write-Host " Location: $($item.FullName)" -ForegroundColor White
    Write-Host " Size:     $([math]::Round($item.Length / 1MB, 2)) MB" -ForegroundColor White
    Write-Host " SHA256:   $hash" -ForegroundColor Gray
    Write-Host "==========================================================" -ForegroundColor Green
} else {
    throw "Installer binary was not found at $FinalInstaller"
}
