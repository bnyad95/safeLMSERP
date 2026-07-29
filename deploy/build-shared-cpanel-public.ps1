param(
    [string]$PrivateAppFolder = "safelms_app",
    [string]$PackageName = "SafeLMS-public_html.zip"
)

$ErrorActionPreference = "Stop"

if ($PrivateAppFolder -notmatch '^[A-Za-z0-9_-]+$') {
    throw "The private application folder may contain only letters, numbers, hyphens, and underscores."
}

$projectRoot = (Resolve-Path (Join-Path $PSScriptRoot "..")).Path
$distRoot = Join-Path $projectRoot "dist"
$stagingRoot = Join-Path $distRoot "public_html"
$packagePath = Join-Path $distRoot $PackageName

if (Test-Path -LiteralPath $stagingRoot) {
    Remove-Item -LiteralPath $stagingRoot -Recurse -Force
}
New-Item -ItemType Directory -Path $stagingRoot -Force | Out-Null

& robocopy (Join-Path $projectRoot "public") $stagingRoot /E /XJ /XD "storage" /XF "hot" /NFL /NDL /NJH /NJS /NP
if ($LASTEXITCODE -gt 7) {
    throw "Could not copy the public web files."
}

$indexPath = Join-Path $stagingRoot "index.php"
$index = Get-Content -LiteralPath $indexPath -Raw
$index = $index.Replace("__DIR__.'/../storage/framework/maintenance.php'", "__DIR__.'/../$PrivateAppFolder/storage/framework/maintenance.php'")
$index = $index.Replace("__DIR__.'/../vendor/autoload.php'", "__DIR__.'/../$PrivateAppFolder/vendor/autoload.php'")
$index = $index.Replace("__DIR__.'/../bootstrap/app.php'", "__DIR__.'/../$PrivateAppFolder/bootstrap/app.php'")

if ($index -notmatch [regex]::Escape("__DIR__.'/../$PrivateAppFolder/vendor/autoload.php'")) {
    throw "Could not update the public index.php application paths."
}

$utf8WithoutBom = New-Object System.Text.UTF8Encoding($false)
[System.IO.File]::WriteAllText($indexPath, $index, $utf8WithoutBom)

php (Join-Path $projectRoot "deploy\create-zip.php") $stagingRoot $packagePath
if ($LASTEXITCODE -ne 0) {
    throw "Could not create the public_html ZIP archive."
}

$sizeMb = [math]::Round((Get-Item -LiteralPath $packagePath).Length / 1MB, 1)
Write-Host "Shared cPanel public package ready: $packagePath ($sizeMb MB)"
