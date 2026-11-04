param([switch]$SinNavegador)

$ErrorActionPreference = 'Stop'
$projectDirectory = $PSScriptRoot
$frontendDirectory = Join-Path $projectDirectory 'frontend'
$backendDirectory = Join-Path $projectDirectory 'backend'
$logsDirectory = Join-Path $backendDirectory 'storage/logs'
$nodeExecutable = (Get-Command node -ErrorAction Stop).Source
$phpExecutable = 'C:/laragon/bin/php/php-8.3.33-Win32-vs16-x64/php.exe'

if (-not (Test-Path -LiteralPath $phpExecutable)) {
    throw 'No se encontro PHP 8.3 de Laragon. Revisa la ruta de PHP en iniciar-topico.ps1.'
}
if (-not (Test-Path -LiteralPath (Join-Path $frontendDirectory 'node_modules/@quasar/app-vite/bin/quasar.js'))) {
    throw 'Faltan las dependencias del frontend. Ejecuta npm install en frontend.'
}
if (-not (Test-Path -LiteralPath (Join-Path $backendDirectory 'vendor/autoload.php'))) {
    throw 'Faltan las dependencias de Laravel. Ejecuta composer install en backend con PHP 8.3.'
}

function Get-ListeningProcess([int]$Port) {
    Get-NetTCPConnection -State Listen -LocalPort $Port -ErrorAction SilentlyContinue |
        Select-Object -First 1 -ExpandProperty OwningProcess
}

function Wait-ForPage([string]$Url, [string]$ServiceName) {
    for ($attempt = 1; $attempt -le 12; $attempt++) {
        try {
            $response = Invoke-WebRequest -Uri $Url -UseBasicParsing -TimeoutSec 3
            if ($response.StatusCode -eq 200) {
                Write-Host "$ServiceName disponible: HTTP 200" -ForegroundColor Green
                return
            }
        } catch {
            if ($attempt -eq 12) {
                throw "$ServiceName no responde en $Url. Consulta los registros en $logsDirectory. Comprueba que MySQL este iniciado en Laragon."
            }
        }
        Start-Sleep -Milliseconds 600
    }
}

New-Item -ItemType Directory -Path $logsDirectory -Force | Out-Null
$runLabel = Get-Date -Format 'yyyyMMdd-HHmmss-fff'

if (-not (Get-ListeningProcess 3306)) {
    $mysqlExecutable = 'C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysqld.exe'
    $mysqlConfiguration = 'C:\laragon\bin\mysql\mysql-8.4.3-winx64\my.ini'
    if (-not (Test-Path -LiteralPath $mysqlExecutable) -or -not (Test-Path -LiteralPath $mysqlConfiguration)) {
        throw 'MySQL no esta disponible. Inicialo desde Laragon y vuelve a abrir Iniciar Topico.cmd.'
    }
    $existingDatabaseProcess = Get-Process -Name mysqld -ErrorAction SilentlyContinue | Where-Object { $_.Path -eq $mysqlExecutable }
    if (-not $existingDatabaseProcess) {
        $mysqlArguments = '--defaults-file="' + $mysqlConfiguration + '" --console'
        $databaseProcess = Start-Process -FilePath $mysqlExecutable -ArgumentList $mysqlArguments -WorkingDirectory (Split-Path (Split-Path $mysqlExecutable)) -WindowStyle Hidden -RedirectStandardOutput (Join-Path $logsDirectory "mysql-$runLabel.log") -RedirectStandardError (Join-Path $logsDirectory "mysql-$runLabel-error.log") -PassThru
        Write-Host "MySQL iniciado (PID $($databaseProcess.Id)). Esperando la base de datos..."
    }
    for ($databaseAttempt = 0; $databaseAttempt -lt 60; $databaseAttempt++) {
        if (Get-ListeningProcess 3306) { break }
        Start-Sleep -Milliseconds 500
    }
    if (-not (Get-ListeningProcess 3306)) {
        throw "MySQL no pudo iniciar. Consulta los registros en $logsDirectory o inicialo desde Laragon."
    }
}
Write-Host 'MySQL disponible en el puerto 3306.' -ForegroundColor Green

if (-not (Get-ListeningProcess 8001)) {
    $backendRouter = Join-Path $backendDirectory 'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php'
    $backendArguments = '-S 127.0.0.1:8001 "' + $backendRouter + '"'
    $backendProcess = Start-Process -FilePath $phpExecutable -ArgumentList $backendArguments -WorkingDirectory (Join-Path $backendDirectory 'public') -WindowStyle Hidden -RedirectStandardOutput (Join-Path $logsDirectory "php-$runLabel.log") -RedirectStandardError (Join-Path $logsDirectory "php-$runLabel-error.log") -PassThru
    Write-Host "Laravel iniciado (PID $($backendProcess.Id))."
} else {
    Write-Host 'El puerto 8001 ya esta en uso; se comprobara su respuesta.'
}

if (-not (Get-ListeningProcess 9000)) {
    $frontendProcess = Start-Process -FilePath $nodeExecutable -ArgumentList 'node_modules/@quasar/app-vite/bin/quasar.js dev --port 9000 --hostname 127.0.0.1' -WorkingDirectory $frontendDirectory -WindowStyle Hidden -RedirectStandardOutput (Join-Path $logsDirectory "quasar-$runLabel.log") -RedirectStandardError (Join-Path $logsDirectory "quasar-$runLabel-error.log") -PassThru
    Write-Host "Frontend iniciado (PID $($frontendProcess.Id))."
} else {
    Write-Host 'El puerto 9000 ya esta en uso; se comprobara su respuesta.'
}

Wait-ForPage 'http://127.0.0.1:8001/up' 'Laravel'
Wait-ForPage 'http://127.0.0.1:9000/' 'Topico'
Wait-ForPage 'http://127.0.0.1:9000/api/csrf' 'Conexion con la API'
Write-Host 'Sistema disponible en http://127.0.0.1:9000/' -ForegroundColor Green
if (-not $SinNavegador) {
    Start-Process 'http://127.0.0.1:9000/'
}
