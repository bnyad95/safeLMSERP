param(
    [string]$PackageName = "SafeLMS-cPanel.zip"
)

$ErrorActionPreference = "Stop"

$projectRoot = (Resolve-Path (Join-Path $PSScriptRoot "..")).Path
$distRoot = Join-Path $projectRoot "dist"
$stagingRoot = Join-Path $distRoot "SafeLMS"
$packagePath = Join-Path $distRoot $PackageName

if ((Split-Path $stagingRoot -Parent) -ne $distRoot) {
    throw "Invalid staging directory."
}

Push-Location $projectRoot
try {
    if (! (Test-Path -LiteralPath (Join-Path $projectRoot "vendor\autoload.php"))) {
        throw "Run composer install in the project before building the cPanel package."
    }

    Write-Host "Building frontend assets..."
    npm run build
    if ($LASTEXITCODE -ne 0) {
        throw "Frontend build failed."
    }

    if (Test-Path -LiteralPath $distRoot) {
        Remove-Item -LiteralPath $distRoot -Recurse -Force
    }
    New-Item -ItemType Directory -Path $stagingRoot -Force | Out-Null

    Write-Host "Copying production files..."
    $excludedDirectories = @(
        ".git",
        ".agents",
        ".codex",
        ".fleet",
        ".idea",
        ".nova",
        ".vscode",
        ".zed",
        "dist",
        "node_modules",
        "tests"
    )
    $excludedFiles = @(
        ".env",
        ".env.backup",
        ".env.production",
        ".phpunit.result.cache",
        "auth.json",
        "database.sqlite",
        "Homestead.json",
        "Homestead.yaml",
        "phpunit.xml"
    )

    & robocopy $projectRoot $stagingRoot /E /XJ /XD $excludedDirectories /XF $excludedFiles /NFL /NDL /NJH /NJS /NP
    if ($LASTEXITCODE -gt 7) {
        throw "Project copy failed with robocopy exit code $LASTEXITCODE."
    }

    $runtimeDirectories = @(
        "storage\app\private",
        "storage\app\public",
        "storage\framework\cache\data",
        "storage\framework\sessions",
        "storage\framework\views",
        "storage\logs",
        "bootstrap\cache"
    )

    foreach ($relativeDirectory in $runtimeDirectories) {
        $directory = Join-Path $stagingRoot $relativeDirectory
        if (Test-Path -LiteralPath $directory) {
            Get-ChildItem -LiteralPath $directory -Force |
                Where-Object { $_.Name -ne ".gitignore" } |
                Remove-Item -Recurse -Force
        } else {
            New-Item -ItemType Directory -Path $directory -Force | Out-Null
        }
    }

    Write-Host "Installing production PHP dependencies..."
    Push-Location $stagingRoot
    try {
        composer config optimize-autoloader false
        if ($LASTEXITCODE -ne 0) {
            throw "Could not prepare Composer for packaging."
        }

        composer install --no-dev --prefer-dist --no-interaction --no-scripts
        if ($LASTEXITCODE -ne 0) {
            throw "Composer production install failed."
        }
    } finally {
        Pop-Location
    }
    Copy-Item -LiteralPath (Join-Path $projectRoot "composer.json") -Destination (Join-Path $stagingRoot "composer.json") -Force

    Write-Host "Creating $PackageName..."
    php (Join-Path $projectRoot "deploy\create-zip.php") $stagingRoot $packagePath
    if ($LASTEXITCODE -ne 0) {
        throw "ZIP creation failed."
    }

    $sizeMb = [math]::Round((Get-Item -LiteralPath $packagePath).Length / 1MB, 1)
    Write-Host "Package ready: $packagePath ($sizeMb MB)"
} finally {
    Pop-Location
}
