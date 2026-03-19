Add-Type -AssemblyName System.IO.Compression.FileSystem
$version = "3.11.1"
$zipName = "broodle-whmcs-tools-v$version.zip"
$srcDir  = "modules\addons\broodle_whmcs_tools"
$zipPath = Join-Path $PSScriptRoot $zipName

if (Test-Path $zipPath) { Remove-Item $zipPath -Force }

$zip = [System.IO.Compression.ZipFile]::Open($zipPath, 'Create')
$files = Get-ChildItem -Path $srcDir -Recurse -File
foreach ($f in $files) {
    $rel = $f.FullName.Substring((Resolve-Path $PSScriptRoot).Path.Length + 1)
    $entry = $rel.Replace('\', '/')
    [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $f.FullName, $entry) | Out-Null
}
$zip.Dispose()
Write-Host "Created $zipName with $($files.Count) files"
