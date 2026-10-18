$ErrorActionPreference = "Stop"

$Utf8 = New-Object System.Text.UTF8Encoding -ArgumentList $false
[Console]::InputEncoding = $Utf8
[Console]::OutputEncoding = $Utf8
$OutputEncoding = $Utf8

$Root = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $Root

Write-Host "Directorio: $Root"

$phpCommand = Get-Command php -ErrorAction Stop
$phpVersionText = (& php -r "echo PHP_VERSION;").Trim()

Write-Host "PHP: $phpVersionText"

try {
    $normalizedVersion = ($phpVersionText -split "-")[0]
    $phpVersion = [version]$normalizedVersion
}
catch {
    throw "No se pudo interpretar la versión de PHP: $phpVersionText"
}

if ($phpVersion -lt [version]"8.2.0") {
    throw "El entorno requiere PHP 8.2 o posterior. Detectado: $phpVersionText"
}

if ($phpVersion -lt [version]"8.4.0") {
    Write-Warning "PHP $phpVersionText permite ejecutar las pruebas, pero PHP 8.4 sigue siendo el objetivo principal."
}

$allowUrlFopen = (& php -r "echo ini_get('allow_url_fopen');").Trim()

if ($allowUrlFopen -ne "1") {
    throw "allow_url_fopen debe estar activado."
}

$requiredExtensions = @(
    "iconv",
    "openssl",
    "json",
    "libxml",
    "zlib"
)

$phpModules = @(& php -m)

foreach ($extension in $requiredExtensions) {
    if ($phpModules -notcontains $extension) {
        throw "Falta la extensión PHP obligatoria: $extension"
    }
}

$directories = @(
    "resultados/original",
    "resultados/fase1",
    "resultados/comparacion",
    "resultados/logs",
    "runtime",
    "certificados"
)

foreach ($directory in $directories) {
    New-Item `
        -ItemType Directory `
        -Force `
        -Path (Join-Path $Root $directory) | Out-Null
}

$certPath = Join-Path $Root "certificados/cert.pem"
$keyPath = Join-Path $Root "certificados/key.pem"

if ((Test-Path $certPath) -and (Test-Path $keyPath)) {
    Write-Host "Certificado TLS local existente y preparado."
}
else {
    $opensslCommand = Get-Command openssl -ErrorAction SilentlyContinue

    if ($opensslCommand) {
        & openssl req `
            -x509 `
            -newkey rsa:2048 `
            -sha256 `
            -nodes `
            -keyout $keyPath `
            -out $certPath `
            -days 3650 `
            -subj "/CN=127.0.0.1" `
            -addext "subjectAltName=IP:127.0.0.1,DNS:localhost"

        if ($LASTEXITCODE -ne 0) {
            throw "No se pudo generar el certificado TLS local."
        }

        Write-Host "Certificado TLS local generado."
    }
    else {
        Write-Warning "No existe un certificado TLS y openssl.exe no está instalado. Solo las pruebas HTTPS quedarán sin ejecutar."
    }
}

$HashScript = Join-Path $Root "pruebas/generar_hashes.php"

if (!(Test-Path $HashScript)) {
    throw "No se encuentra pruebas/generar_hashes.php"
}

& php $HashScript

if ($LASTEXITCODE -ne 0) {
    throw "Las librerías no coinciden con sus hashes originales."
}

$composerCommand = Get-Command composer -ErrorAction SilentlyContinue

if ($composerCommand) {
    $composerLockPath = Join-Path $Root "composer.lock"

    if (Test-Path $composerLockPath) {
        Write-Host "Composer disponible. Instalando dependencias desde composer.lock..."

        & composer install `
            --no-interaction `
            --prefer-dist
    }
    else {
        Write-Host "Composer disponible. Generando composer.lock e instalando dependencias..."

        & composer update `
            --no-interaction `
            --prefer-dist
    }

    if ($LASTEXITCODE -ne 0) {
        Write-Warning "Composer no pudo instalar PHPUnit. La configuración continuará utilizando el runner autónomo."
    }
    else {
        $phpunitUnix = Join-Path $Root "vendor/bin/phpunit"
        $phpunitWindows = Join-Path $Root "vendor/bin/phpunit.bat"

        if ((Test-Path $phpunitUnix) -or (Test-Path $phpunitWindows)) {
            Write-Host "PHPUnit instalado correctamente."
        }
        else {
            Write-Warning "Composer terminó correctamente, pero no se encontró PHPUnit. Se utilizará el runner autónomo."
        }
    }
}
else {
    Write-Warning "Composer no está disponible. Se utilizará el runner autónomo."
}
Write-Host "Configuración terminada correctamente."