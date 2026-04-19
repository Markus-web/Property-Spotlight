# Property Spotlight - Build Production ZIP
# Usage: .\build-zip.ps1

$ErrorActionPreference = "Stop"

# Configuration
$pluginName = "property-spotlight"
$version = "1.2.1"
$outputFile = "$pluginName.zip"

# Get script directory
$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $scriptDir

Write-Host "Building $pluginName v$version..." -ForegroundColor Cyan

# Files and folders to include
$includeItems = @(
    "assets",
    "blocks", 
    "includes",
    "languages",
    "index.php",
    "property-spotlight.php",
    "uninstall.php",
    "LICENSE",
    "readme.txt"
)

# Files to exclude (within included folders)
$excludePatterns = @(
    "*.zip",
    "docker-compose.yml",
    "build-zip.ps1",
    "build-zip.sh",
    "README.md",
    "compile-mo.php"
)

# Clean up old ZIP
if (Test-Path $outputFile) {
    Remove-Item $outputFile -Force
    Write-Host "  Removed old $outputFile" -ForegroundColor Yellow
}

# Create temp directory
$tempDir = Join-Path $env:TEMP "property-spotlight-build"
$tempPluginDir = Join-Path $tempDir $pluginName

if (Test-Path $tempDir) {
    Remove-Item $tempDir -Recurse -Force
}
New-Item -ItemType Directory -Path $tempPluginDir -Force | Out-Null

# Copy files
Write-Host "  Copying files..." -ForegroundColor Gray
foreach ($item in $includeItems) {
    $sourcePath = Join-Path $scriptDir $item
    $destPath = Join-Path $tempPluginDir $item
    
    if (Test-Path $sourcePath) {
        if ((Get-Item $sourcePath).PSIsContainer) {
            Copy-Item -Path $sourcePath -Destination $destPath -Recurse -Force
        } else {
            Copy-Item -Path $sourcePath -Destination $destPath -Force
        }
        Write-Host "    + $item" -ForegroundColor Green
    } else {
        Write-Host "    ! $item not found" -ForegroundColor Yellow
    }
}

# Remove excluded files from temp directory
foreach ($pattern in $excludePatterns) {
    Get-ChildItem -Path $tempPluginDir -Filter $pattern -Recurse | Remove-Item -Force -ErrorAction SilentlyContinue
}

# Create ZIP using .NET with forward slashes (Linux/WordPress compatible)
Write-Host "  Creating ZIP archive..." -ForegroundColor Gray

$outputPath = Join-Path $scriptDir $outputFile
Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

# Create ZIP manually to ensure forward slashes in entry paths
$zipStream = [System.IO.File]::Create($outputPath)
$zip = New-Object System.IO.Compression.ZipArchive($zipStream, [System.IO.Compression.ZipArchiveMode]::Create)

# Add all files from temp directory with forward slashes
$tempDirLength = $tempDir.Length + 1
Get-ChildItem -Path $tempDir -Recurse -File | ForEach-Object {
    $relativePath = $_.FullName.Substring($tempDirLength)
    # Convert backslashes to forward slashes for Linux compatibility
    $entryPath = $relativePath -replace '\\', '/'
    
    $entry = $zip.CreateEntry($entryPath, [System.IO.Compression.CompressionLevel]::Optimal)
    $entryStream = $entry.Open()
    $fileStream = [System.IO.File]::OpenRead($_.FullName)
    $fileStream.CopyTo($entryStream)
    $fileStream.Close()
    $entryStream.Close()
}

$zip.Dispose()
$zipStream.Close()

# Clean up temp directory
Remove-Item $tempDir -Recurse -Force

# Verify ZIP structure
Write-Host "  Verifying ZIP structure..." -ForegroundColor Gray
$zipValid = $true
$mainFileFound = $false
$entryCount = 0

try {
    $zip = [System.IO.Compression.ZipFile]::OpenRead($outputPath)
    foreach ($entry in $zip.Entries) {
        $entryCount++
        # Normalize path separators (Windows uses backslash, Linux uses forward slash)
        $normalizedPath = $entry.FullName -replace '\\', '/'
        
        # Check that all entries start with plugin folder name
        if ($normalizedPath -notlike "$pluginName/*" -and $normalizedPath -ne "$pluginName/") {
            Write-Host "    ! Invalid path: $normalizedPath" -ForegroundColor Red
            $zipValid = $false
        }
        # Check for main plugin file
        if ($normalizedPath -eq "$pluginName/$pluginName.php") {
            $mainFileFound = $true
        }
        # Check for double-nesting (e.g., property-spotlight/property-spotlight/)
        if ($normalizedPath -like "$pluginName/$pluginName/*") {
            Write-Host "    ! Double-nested: $normalizedPath" -ForegroundColor Red
            $zipValid = $false
        }
    }
    $zip.Dispose()
} catch {
    Write-Host "ERROR: Failed to verify ZIP - $_" -ForegroundColor Red
    exit 1
}

if (-not $mainFileFound) {
    Write-Host "ERROR: Main plugin file ($pluginName/$pluginName.php) not found in ZIP" -ForegroundColor Red
    exit 1
}

if (-not $zipValid) {
    Write-Host "ERROR: ZIP structure is invalid" -ForegroundColor Red
    exit 1
}

# Success output
if (Test-Path $outputFile) {
    $zipSize = (Get-Item $outputFile).Length
    $zipSizeKB = [math]::Round($zipSize / 1KB, 1)
    Write-Host ""
    Write-Host "SUCCESS: Created $outputFile ($zipSizeKB KB, $entryCount files)" -ForegroundColor Green
    Write-Host "  Structure: $pluginName/$pluginName.php verified" -ForegroundColor Green
    Write-Host ""
    Write-Host "Install via: WordPress Admin > Plugins > Add New > Upload Plugin" -ForegroundColor Cyan
} else {
    Write-Host "ERROR: Failed to create ZIP file" -ForegroundColor Red
    exit 1
}
