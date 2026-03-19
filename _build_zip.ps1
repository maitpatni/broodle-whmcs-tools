Add-Type -AssemblyName System.IO.Compression.FileSystem
Add-Type -AssemblyName System.IO.Compression

$version = "3.10.95"
$zipPath = "broodle-whmcs-tools-v$version.zip"
$sourceDir = "modules"

if (Test-Path $zipPath) { Remove-Item $zipPath -Force }

$zip = [System.IO.Compression.ZipFile]::Open($zipPath, [System.IO.Compression.ZipArchiveMode]::Create)

$basePath = (Get-Location).Path
$files = Get-ChildItem -Path $sourceDir -Recurse -File

foreach ($file in $files) {
    $relativePath = $file.FullName.Substring($basePath.Length + 1)
    $entryName = $relativePath.Replace('\', '/')
    [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $file.FullName, $entryName, [System.IO.Compression.CompressionLevel]::Optimal) | Out-Null
}

$zip.Dispose()
Write-Host "Created $zipPath with $($files.Count) files"
