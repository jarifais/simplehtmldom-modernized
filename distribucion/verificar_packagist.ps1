& {
    $ErrorActionPreference = 'Stop'
    Set-StrictMode -Version Latest

    # PKG-002: verificar la publicacion real desde Packagist.org.
    # No agrega repositorios VCS ni usa credenciales de Packagist.
    $package = 'jarifais/simplehtmldom-modernized'
    $version = '0.1.0-beta.1'
    $tagCommit = '1047dd573014913a42f58aad343ce453d8d66f3a'
    $phpHash = 'bba1d651b7c58eab6e70a031a6d8ca52b86c68bc746188440652f877b9110d25'
    $licenseHash = '27c365807abed901376feec1dc2ce8bbee52c9e140548e18924c3ce32a345efb'
    $scratch = Join-Path ([System.IO.Path]::GetTempPath()) ('simplehtmldom_pkg002_' + [guid]::NewGuid().ToString('N'))
    $originalLocation = (Get-Location).Path
    $originalComposerHome = [Environment]::GetEnvironmentVariable('COMPOSER_HOME','Process')
    $originalComposerCache = [Environment]::GetEnvironmentVariable('COMPOSER_CACHE_DIR','Process')
    $success = $false
    $problems = [System.Collections.Generic.List[string]]::new()

    function Assert-Check([bool]$condition, [string]$message) {
        if (-not $condition) { throw $message }
    }

    try {
        $php = (Get-Command php -ErrorAction Stop).Source
        $null = Get-Command composer -ErrorAction Stop
        $phpId = [int](& $php -r 'echo PHP_VERSION_ID;')
        Assert-Check ($LASTEXITCODE -eq 0 -and $phpId -ge 80400 -and $phpId -lt 80500) 'PKG-002 verifica PHP 8.4, no otras versiones.'

        $modules = @(& $php -m)
        Assert-Check ($LASTEXITCODE -eq 0 -and $modules -contains 'iconv') 'Falta ext-iconv.'

        # Consultar el repositorio publico REAL, sin resolucion VCS local.
        $metadataUrl = "https://repo.packagist.org/p2/$package.json"
        $metadata = Invoke-RestMethod -Uri $metadataUrl -Method Get -TimeoutSec 30 -ErrorAction Stop
        # La clave JSON de Packagist contiene una barra: acceso literal.
        $versions = @($metadata.packages.'jarifais/simplehtmldom-modernized')
        Assert-Check ($versions.Count -gt 0) 'El paquete no esta registrado en Packagist.'
        $found = @($versions | Where-Object {
            $_.version -match '^v?0\.1\.0-beta\.1$' -and
            $_.source.reference -ceq $tagCommit
        })
        Assert-Check ($found.Count -eq 1) 'Packagist no indexa la beta exacta o apunta a otro commit.'
        Write-Host 'PKG-002: METADATOS PUBLICOS PACKAGIST PASS' -ForegroundColor Green

        New-Item -ItemType Directory -Path $scratch -Force -ErrorAction Stop | Out-Null
        $consumer = Join-Path $scratch 'consumer'
        $composerHome = Join-Path $scratch 'composer-home'
        $composerCache = Join-Path $scratch 'composer-cache'
        New-Item -ItemType Directory -Path $consumer,$composerHome,$composerCache -Force | Out-Null
        $env:COMPOSER_HOME = $composerHome
        $env:COMPOSER_CACHE_DIR = $composerCache

        # No repositories property: Composer must use default Packagist.org.
        $manifestPath = Join-Path $consumer 'composer.json'
        $consumerManifest = @'
{
    "name": "verificacion/simplehtmldom-packagist",
    "type": "project",
    "description": "Prueba temporal de instalacion directa desde Packagist",
    "license": "MIT",
    "require": {
        "jarifais/simplehtmldom-modernized": "0.1.0-beta.1"
    },
    "config": {
        "allow-plugins": false
    }
}
'@
        [System.IO.File]::WriteAllText(
            $manifestPath, $consumerManifest,
            [System.Text.UTF8Encoding]::new($false)
        )

        Set-Location $consumer
        & composer validate --strict --no-check-publish
        Assert-Check ($LASTEXITCODE -eq 0) 'Manifiesto consumidor no valido.'
        & composer install --no-interaction --no-progress --no-scripts --no-plugins --prefer-dist
        Assert-Check ($LASTEXITCODE -eq 0) 'Instalacion Packagist fallida.'

        $lock = Get-Content -LiteralPath (Join-Path $consumer 'composer.lock') -Raw -Encoding UTF8 | ConvertFrom-Json
        $locked = @($lock.packages | Where-Object { $_.name -ceq $package })
        Assert-Check ($locked.Count -eq 1) 'Composer no instalo exactamente un paquete esperado.'
        Assert-Check ($locked[0].version -match '^v?0\.1\.0-beta\.1$') 'Version Composer inesperada.'
        Assert-Check ($locked[0].source.reference -ceq $tagCommit) 'Commit instalado distinto de la beta publicada.'
        Assert-Check (-not $locked[0].replace -and -not $locked[0].provide) 'Metadatos de reemplazo no esperados.'

        $library = Join-Path $consumer 'vendor/jarifais/simplehtmldom-modernized'
        $autoload = Join-Path $consumer 'vendor/autoload.php'
        $maintained = Join-Path $library 'librerias/fase1/simple_html_dom.php'
        $standalone = Join-Path $library 'simple_html_dom.php'
        $license = Join-Path $library 'LICENSE'
        foreach ($path in @($maintained,$standalone)) {
            Assert-Check (Test-Path -LiteralPath $path -PathType Leaf) "Falta biblioteca: $path"
            $digest = (Get-FileHash -LiteralPath $path -Algorithm SHA256).Hash.ToLowerInvariant()
            Assert-Check ($digest -ceq $phpHash) "Biblioteca instalada alterada: $path"
            & $php -l $path | Out-Null
            Assert-Check ($LASTEXITCODE -eq 0) "Lint fallido: $path"
        }
        Assert-Check (Test-Path -LiteralPath $autoload -PathType Leaf) 'No se genero vendor/autoload.php.'
        Assert-Check (
            (Get-FileHash -LiteralPath $license -Algorithm SHA256).Hash.ToLowerInvariant() -ceq $licenseHash
        ) 'Licencia MIT alterada.'

        # A second fresh process uses only vendor/autoload.php.
        $probe = @'
<?php
declare(strict_types=1);
require_once $argv[1];
if (!function_exists('str_get_html') || !function_exists('file_get_html')
    || !class_exists('simple_html_dom') || !class_exists('simple_html_dom_node')) {
    exit(2);
}
$fn = new ReflectionFunction('str_get_html');
if (realpath((string)$fn->getFileName()) !== realpath($argv[2])) {
    exit(3);
}
$html = str_get_html('<section><p id="check" data-pkg="1">Packagist OK</p></section>');
if ($html === false) { exit(4); }
$node = $html->find('#check', 0);
if ($node === null || $node->plaintext !== 'Packagist OK' || !$node->hasAttribute('data-pkg')) {
    exit(5);
}
$node->removeAttribute('data-pkg');
if ($node->hasAttribute('data-pkg')) { exit(6); }
$html->clear();
echo "PKG-002-PROBE-PASS\n";
'@
        $probePath = Join-Path $scratch 'probe.php'
        [System.IO.File]::WriteAllText($probePath,$probe,[System.Text.UTF8Encoding]::new($false))
        & $php -l $probePath | Out-Null
        Assert-Check ($LASTEXITCODE -eq 0) 'Sintaxis del probe invalida.'
        $result = @(& $php $probePath $autoload $maintained 2>&1)
        Assert-Check (
            $LASTEXITCODE -eq 0 -and ($result -join '').Trim() -ceq 'PKG-002-PROBE-PASS'
        ) ('API clasica no disponible desde Packagist: ' + ($result -join ' | '))

        $success = $true
        Write-Host 'PKG-002: PASS - Packagist indexado, version/commit/hash/API/licencia verificados.' -ForegroundColor Green
    }
    catch {
        $problems.Add($_.Exception.Message)
    }
    finally {
        Set-Location $originalLocation
        [Environment]::SetEnvironmentVariable('COMPOSER_HOME',$originalComposerHome,'Process')
        [Environment]::SetEnvironmentVariable('COMPOSER_CACHE_DIR',$originalComposerCache,'Process')
        if (Test-Path -LiteralPath $scratch) {
            try {
                Remove-Item -LiteralPath $scratch -Recurse -Force -ErrorAction Stop
            }
            catch {
                $problems.Add("No se limpio el entorno temporal: $($_.Exception.Message)")
            }
        }
    }
    if (-not $success -or $problems.Count -gt 0) {
        throw ('PKG-002 NO VALIDADA: ' + ($problems -join ' | '))
    }
}
