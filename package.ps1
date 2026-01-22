# Configuration
$moduleDirName = "cdretention"
$moduleXmlPath = ".\$moduleDirName\module.xml"

# Try to find 7z.exe
$7zPaths = @(
    "C:\Program Files\7-Zip\7z.exe",
    "C:\Program Files (x86)\7-Zip\7z.exe"
)

$7zPath = $null
foreach ($path in $7zPaths) {
    if (Test-Path $path) {
        $7zPath = $path
        break
    }
}

if (-not $7zPath) {
    Write-Error "7-Zip executable (7z.exe) not found in standard locations."
    Write-Host "Please install 7-Zip or edit this script to specify the path to 7z.exe."
    exit 1
}

Write-Host "Using 7-Zip at: $7zPath"

# Read version and rawname from module.xml
if (Test-Path $moduleXmlPath) {
    [xml]$xml = Get-Content $moduleXmlPath
    $version = $xml.module.version
    $rawname = $xml.module.rawname
    Write-Host "Detected rawname: $rawname"
    Write-Host "Detected version: $version"
} else {
    Write-Error "module.xml not found at $moduleXmlPath!"
    exit 1
}

$tarFile = "$rawname-$version.tar"
$tgzFile = "$rawname-$version.tar.gz"

# Remove old files if they exist
if (Test-Path $tarFile) { Remove-Item $tarFile }
if (Test-Path $tgzFile) { Remove-Item $tgzFile }

# Step 1: Create TAR
# We want the tar to contain the folder 'cdretention', so we include the folder name in the source
Write-Host "Creating TAR archive: $tarFile"
$proc = Start-Process -FilePath $7zPath -ArgumentList "a", "-ttar", "$tarFile", ".\$moduleDirName" -Wait -PassThru -NoNewWindow

if ($proc.ExitCode -ne 0) {
    Write-Error "Failed to create TAR archive."
    exit $proc.ExitCode
}

# Step 2: Compress TAR to GZIP
Write-Host "Compressing to GZIP: $tgzFile"
$proc = Start-Process -FilePath $7zPath -ArgumentList "a", "-tgzip", "$tgzFile", "$tarFile" -Wait -PassThru -NoNewWindow

if ($proc.ExitCode -ne 0) {
    Write-Error "Failed to create GZIP archive."
    exit $proc.ExitCode
}

# Step 3: Cleanup intermediate TAR file
if (Test-Path $tarFile) { 
    Remove-Item $tarFile 
    Write-Host "Cleaned up temporary TAR file."
}

Write-Host "----------------------------------------"
Write-Host "Success! Module packaged at:"
Write-Host (Resolve-Path $tgzFile)
Write-Host "----------------------------------------"
