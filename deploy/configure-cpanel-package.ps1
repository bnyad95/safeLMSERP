param(
    [Parameter(Mandatory = $true)]
    [string]$DatabaseName,

    [Parameter(Mandatory = $true)]
    [string]$DatabaseUsername,

    [Parameter(Mandatory = $true)]
    [string]$DatabasePassword,

    [string]$DatabaseHost = "localhost",
    [string]$AppUrl = "https://your-domain.example",
    [string]$PackageName = "SafeLMS-cPanel-configured.tar"
)

$ErrorActionPreference = "Stop"

$projectRoot = (Resolve-Path (Join-Path $PSScriptRoot "..")).Path
$stagingRoot = Join-Path $projectRoot "dist\SafeLMS"
$environmentPath = Join-Path $stagingRoot ".env"
$packagePath = Join-Path $projectRoot "dist\$PackageName"
$instructionsPath = Join-Path $projectRoot "dist\SafeLMS-INSTALLATION.txt"

if (! (Test-Path -LiteralPath (Join-Path $stagingRoot "artisan"))) {
    throw "Build the base package first with deploy/build-cpanel-package.ps1."
}

$AppUrl = $AppUrl.TrimEnd('/')

if ($AppUrl -notmatch '^https://') {
    throw "APP_URL must start with https://."
}

$deploymentFiles = @(
    ".env.cpanel.example",
    "DEPLOYMENT.md"
)

foreach ($relativePath in $deploymentFiles) {
    Copy-Item -LiteralPath (Join-Path $projectRoot $relativePath) -Destination (Join-Path $stagingRoot $relativePath) -Force
}

Copy-Item -Path (Join-Path $projectRoot "deploy\*") -Destination (Join-Path $stagingRoot "deploy") -Recurse -Force

$applicationKey = php -r "echo 'base64:'.base64_encode(random_bytes(32));"
if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace($applicationKey)) {
    throw "Could not generate the production application key."
}

$installerToken = php -r "echo bin2hex(random_bytes(24));"
if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace($installerToken)) {
    throw "Could not generate the one-time installation code."
}

$template = Get-Content -LiteralPath (Join-Path $projectRoot ".env.cpanel.example") -Raw
$configuredEnvironment = $template `
    -replace '(?m)^APP_KEY=.*$', "APP_KEY=$applicationKey" `
    -replace '(?m)^APP_URL=.*$', "APP_URL=$AppUrl" `
    -replace '(?m)^INSTALLER_TOKEN=.*$', "INSTALLER_TOKEN=$installerToken" `
    -replace '(?m)^DB_HOST=.*$', "DB_HOST=$DatabaseHost" `
    -replace '(?m)^DB_DATABASE=.*$', "DB_DATABASE=$DatabaseName" `
    -replace '(?m)^DB_USERNAME=.*$', "DB_USERNAME=$DatabaseUsername"

$escapedPassword = $DatabasePassword.Replace('\', '\\').Replace('"', '\"')
$configuredEnvironment = $configuredEnvironment -replace '(?m)^DB_PASSWORD=.*$', "DB_PASSWORD=`"$escapedPassword`""

try {
    $utf8WithoutBom = New-Object System.Text.UTF8Encoding($false)
    [System.IO.File]::WriteAllText($environmentPath, $configuredEnvironment, $utf8WithoutBom)

    tar.exe -cf $packagePath -C $stagingRoot .
    if ($LASTEXITCODE -ne 0) {
        throw "Configured TAR creation failed."
    }

    $instructions = @"
SafeLMS ERP browser installation

1. Upload and extract: $PackageName
2. Point the domain document root to the public directory.
3. Open: $AppUrl/setup
4. Enter this one-time installation code:

$installerToken

The setup page creates the database tables, roles, permissions, and first
Super Administrator. It disables itself after installation.
"@
    [System.IO.File]::WriteAllText($instructionsPath, $instructions, $utf8WithoutBom)
} finally {
    if (Test-Path -LiteralPath $environmentPath) {
        Remove-Item -LiteralPath $environmentPath -Force
    }
}

$sizeMb = [math]::Round((Get-Item -LiteralPath $packagePath).Length / 1MB, 1)
Write-Host "Configured package ready: $packagePath ($sizeMb MB)"
Write-Host "Browser installation instructions: $instructionsPath"
Write-Host "Database credentials were added only to the TAR archive and were not saved in the project."
