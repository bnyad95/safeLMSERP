$ErrorActionPreference = 'Stop'

$projectRoot = Split-Path -Parent $PSScriptRoot
$mysqlRoot = 'C:\xampp\mysql'
$dataDir = Join-Path $projectRoot '.runtime\mysql\data'
$configPath = Join-Path $projectRoot '.runtime\mysql\my.ini'
$mysql = Join-Path $mysqlRoot 'bin\mysql.exe'
$mysqlAdmin = Join-Path $mysqlRoot 'bin\mysqladmin.exe'
$mysqlInstall = Join-Path $mysqlRoot 'bin\mysql_install_db.exe'
$mysqld = Join-Path $mysqlRoot 'bin\mysqld.exe'

foreach ($binary in @($mysql, $mysqlAdmin, $mysqlInstall, $mysqld)) {
    if (-not (Test-Path $binary)) {
        throw "XAMPP MariaDB is missing: $binary"
    }
}

if (-not (Test-Path (Join-Path $dataDir 'mysql'))) {
    New-Item -ItemType Directory -Force -Path $dataDir | Out-Null
    & $mysqlInstall "--datadir=$dataDir" | Out-Null
    if ($LASTEXITCODE -ne 0) {
        throw 'Could not initialize the SafeLMS local MariaDB data directory.'
    }
}

New-Item -ItemType Directory -Force -Path (Split-Path $configPath) | Out-Null
$config = @"
[mysqld]
basedir=$($mysqlRoot.Replace('\', '/'))
datadir=$($dataDir.Replace('\', '/'))
port=3307
bind-address=127.0.0.1
skip-name-resolve

[client]
port=3307
host=127.0.0.1
"@
Set-Content -Path $configPath -Value $config -Encoding Ascii

& $mysqlAdmin --defaults-file=$configPath ping 2>$null | Out-Null
if ($LASTEXITCODE -ne 0) {
    Start-Process -FilePath $mysqld -ArgumentList "--defaults-file=$configPath" -WindowStyle Hidden
    $ready = $false
    foreach ($attempt in 1..30) {
        Start-Sleep -Milliseconds 500
        & $mysqlAdmin --defaults-file=$configPath ping 2>$null | Out-Null
        if ($LASTEXITCODE -eq 0) {
            $ready = $true
            break
        }
    }

    if (-not $ready) {
        throw 'SafeLMS MariaDB did not start on 127.0.0.1:3307.'
    }
}

& $mysql --defaults-file=$configPath -uroot -e 'CREATE DATABASE IF NOT EXISTS safelms_local CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE DATABASE IF NOT EXISTS safelms_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;'
if ($LASTEXITCODE -ne 0) {
    throw 'MariaDB started, but the SafeLMS databases could not be created.'
}

Write-Output 'SafeLMS MariaDB is ready at 127.0.0.1:3307.'
