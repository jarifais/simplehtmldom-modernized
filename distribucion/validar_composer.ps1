& {
    $ErrorActionPreference = 'Stop'
    Set-StrictMode -Version Latest

    # PKG-001: isolated package installation, with no changes to tracked source.
    $root = Split-Path -Parent $PSScriptRoot
    $scratch = Join-Path ([System.IO.Path]::GetTempPath()) (
        'simplehtmldom_pkg001_' + [guid]::NewGuid().ToString('N')
    )
    $savedLocation = (Get-Location).Path
    $issues = [System.Collections.Generic.List[string]]::new()
    $completed = $false

    function Assert-Check([bool]$condition, [string]$message) {
        if (-not $condition) {
            throw $message
        }
    }

    try {
        $php = (Get-Command php -ErrorAction Stop).Source
        $git = (Get-Command git -ErrorAction Stop).Source
        $null = Get-Command composer -ErrorAction Stop
        $manifestPath = Join-Path $root 'composer.json'
        $manifest = Get-Content -LiteralPath $manifestPath -Raw -Encoding UTF8 |
            ConvertFrom-Json

        Assert-Check ($manifest.name -ceq 'jarifais/simplehtmldom-modernized') 'Nombre de paquete incorrecto.'
        Assert-Check ($manifest.type -ceq 'library' -and $manifest.license -ceq 'MIT') 'Tipo o licencia del paquete incorrectos.'
        Assert-Check ($manifest.require.php -ceq '>=8.2') 'Restriccion PHP inesperada.'
        Assert-Check ($manifest.require.'ext-iconv' -ceq '*') 'La extension iconv debe declararse.'
        Assert-Check (
            $manifest.autoload.files.Count -eq 1 -and
            $manifest.autoload.files[0] -ceq 'librerias/fase1/simple_html_dom.php'
        ) 'autoload.files no apunta a la fuente mantenida.'
        Assert-Check (-not (Test-Path -LiteralPath (Join-Path $root 'composer.lock'))) 'composer.lock heredado aun versionado.'
        $strictUtf8 = [System.Text.UTF8Encoding]::new($false,$true)
        $bytes = [System.IO.File]::ReadAllBytes($manifestPath)
        $null = $strictUtf8.GetString($bytes)
        Assert-Check (-not ($bytes.Length -ge 3 -and $bytes[0] -eq 239 -and $bytes[1] -eq 187 -and $bytes[2] -eq 191)) 'composer.json tiene BOM.'

        $before = @(& $git -C $root status --porcelain --untracked-files=no)
        Assert-Check ($LASTEXITCODE -eq 0 -and $before.Count -eq 0) 'Repositorio modificado antes del test.'

        $baseline = Get-Content -LiteralPath (Join-Path $root 'HASHES_LIBRERIAS_BASE.json') -Raw -Encoding UTF8 |
            ConvertFrom-Json
        $expectedOriginal = 'd3fcca27ce5d1c6ddb06402d8ea22ee1b3989f62c44e2025154c9d4eef501cdd'
        $expectedPhase = 'bba1d651b7c58eab6e70a031a6d8ca52b86c68bc746188440652f877b9110d25'
        $original = Join-Path $root 'librerias/original/simple_html_dom.php'
        $phase = Join-Path $root 'librerias/fase1/simple_html_dom.php'
        $public = Join-Path $root 'simple_html_dom.php'
        $phaseHash = (Get-FileHash -LiteralPath $phase -Algorithm SHA256).Hash.ToLowerInvariant()
        $originalHash = (Get-FileHash -LiteralPath $original -Algorithm SHA256).Hash.ToLowerInvariant()
        $publicHash = (Get-FileHash -LiteralPath $public -Algorithm SHA256).Hash.ToLowerInvariant()
        Assert-Check (
            $originalHash -ceq $expectedOriginal -and
            $originalHash -ceq $baseline.original.'simple_html_dom.php'
        ) 'Original distinto al checkpoint.'
        Assert-Check (
            $phaseHash -ceq $expectedPhase -and
            $phaseHash -ceq $baseline.fase1.'simple_html_dom.php' -and
            $publicHash -ceq $phaseHash
        ) 'Biblioteca mantenida o descargable modificada.'

        Push-Location $root
        try {
            & composer validate --strict --no-check-publish
            Assert-Check ($LASTEXITCODE -eq 0) 'composer validate --strict fallido.'
        } finally {
            Pop-Location
        }
        Write-Host 'PKG-001: MANIFIESTO COMPOSER PASS' -ForegroundColor Green

        $consumer = Join-Path $scratch 'consumidor'
        New-Item -ItemType Directory -Path $consumer -Force -ErrorAction Stop | Out-Null

        # Install a copied package through Composer's path repository.
        # The checkout may be a detached pull-request merge; pin a synthetic
        # *development* branch alias ONLY inside this temporary consumer.
        $consumerManifest = @{
            name = 'verificacion/consumidor-temporal'
            description = 'Isolated PKG-001 consumer test'
            type = 'project'
            repositories = @(
                @{
                    type = 'path'
                    url = $root.Replace('\','/')
                    options = @{
                        symlink = $false
                        versions = @{
                            'jarifais/simplehtmldom-modernized' = 'dev-main'
                        }
                    }
                }
            )
            require = @{
                'jarifais/simplehtmldom-modernized' = 'dev-main'
            }
            config = @{
                'allow-plugins' = $false
            }
        }
        $consumerJson = (ConvertTo-Json $consumerManifest -Depth 12) + [string][char]10
        [System.IO.File]::WriteAllText(
            (Join-Path $consumer 'composer.json'), $consumerJson, $strictUtf8
        )

        Push-Location $consumer
        try {
            & composer install --no-interaction --no-plugins --no-scripts --prefer-dist --no-progress
            Assert-Check ($LASTEXITCODE -eq 0) 'Composer no pudo instalar el paquete en carpeta limpia.'
        } finally {
            Pop-Location
        }

        $autoload = Join-Path $consumer 'vendor/autoload.php'
        $installed = Join-Path $consumer 'vendor/jarifais/simplehtmldom-modernized'
        $loadedSource = Join-Path $installed 'librerias/fase1/simple_html_dom.php'
        $copiedSingle = Join-Path $installed 'simple_html_dom.php'
        Assert-Check (Test-Path -LiteralPath $autoload -PathType Leaf) 'Falta vendor/autoload.php.'
        Assert-Check (Test-Path -LiteralPath $loadedSource -PathType Leaf) 'Falta biblioteca cargada por Composer.'
        Assert-Check (Test-Path -LiteralPath $copiedSingle -PathType Leaf) 'Falta descarga de archivo unico.'
        foreach ($file in @($loadedSource,$copiedSingle)) {
            $digest = (Get-FileHash -LiteralPath $file -Algorithm SHA256).Hash.ToLowerInvariant()
            Assert-Check ($digest -ceq $expectedPhase) "El paquete instalado cambio la biblioteca: $file"
        }
        $licenseSource = Join-Path $root 'LICENSE'
        $licenseInstalled = Join-Path $installed 'LICENSE'
        Assert-Check (
            (Get-FileHash -LiteralPath $licenseSource -Algorithm SHA256).Hash -eq
            (Get-FileHash -LiteralPath $licenseInstalled -Algorithm SHA256).Hash
        ) 'Licencia del consumidor distinta de la original.'

        $probeSource = @'
<?php
declare(strict_types=1);
require_once $argv[1];
if (!function_exists('str_get_html') || !function_exists('file_get_html')
    || !class_exists('simple_html_dom') || !class_exists('simple_html_dom_node')) {
    fwrite(STDERR, "API incompleta\n");
    exit(2);
}
$function = new ReflectionFunction('str_get_html');
if (realpath((string) $function->getFileName()) !== realpath($argv[2])) {
    fwrite(STDERR, "autoload apunta a otra biblioteca\n");
    exit(3);
}
$html = str_get_html('<main><p id="dato" data-test="1">Hola Composer</p></main>');
if ($html === false) {
    exit(4);
}
$node = $html->find('#dato', 0);
if ($node === null || $node->plaintext !== 'Hola Composer'
    || !$node->hasAttribute('data-test')) {
    exit(5);
}
$node->removeAttribute('data-test');
if ($node->hasAttribute('data-test')) {
    exit(6);
}
$html->clear();
echo "PKG-001-CONSUMIDOR-PASS\n";
'@
        $probePath = Join-Path $scratch 'probe.php'
        [System.IO.File]::WriteAllText($probePath, $probeSource, $strictUtf8)
        & $php -l $probePath | Out-Null
        Assert-Check ($LASTEXITCODE -eq 0) 'Lint PHP del probe fallido.'
        $output = @(& $php $probePath $autoload $loadedSource 2>&1)
        Assert-Check (
            $LASTEXITCODE -eq 0 -and
            ($output -join '').Trim() -ceq 'PKG-001-CONSUMIDOR-PASS'
        ) ('Falló consumo de la API Composer: ' + ($output -join ' | '))

        Assert-Check (
            (Get-FileHash -LiteralPath $original -Algorithm SHA256).Hash.ToLowerInvariant() -ceq $originalHash -and
            (Get-FileHash -LiteralPath $phase -Algorithm SHA256).Hash.ToLowerInvariant() -ceq $phaseHash -and
            (Get-FileHash -LiteralPath $public -Algorithm SHA256).Hash.ToLowerInvariant() -ceq $publicHash
        ) 'Hashes posteriores de biblioteca incorrectos.'

        $after = @(& $git -C $root status --porcelain --untracked-files=no)
        Assert-Check ($LASTEXITCODE -eq 0 -and $after.Count -eq 0) 'Archivos versionados modificados por prueba Composer.'
        # Second integration: resolve the actual public GitHub VCS branch,
        # not the source checkout or a local Composer path repository.
        $vcsVersion = [string]$env:PKG001_VCS_VERSION
        Assert-Check (
            $vcsVersion -match '^dev-[A-Za-z0-9_.\/-]+$'
        ) 'Version VCS no suministrada o invalida.'
        $vcsConsumer = Join-Path $scratch 'consumidor-vcs'
        New-Item -ItemType Directory -Path $vcsConsumer -Force -ErrorAction Stop | Out-Null
        $vcsManifest = @{
            name = 'verificacion/consumidor-github'
            description = 'Isolated public GitHub VCS consumer'
            type = 'project'
            repositories = @(@{
                type = 'vcs'
                url = 'https://github.com/jarifais/simplehtmldom-modernized.git'
            })
            require = @{
                'jarifais/simplehtmldom-modernized' = $vcsVersion
            }
            config = @{ 'allow-plugins' = $false }
        }
        [System.IO.File]::WriteAllText(
            (Join-Path $vcsConsumer 'composer.json'),
            (ConvertTo-Json $vcsManifest -Depth 12) + [string][char]10,
            $strictUtf8
        )
        Push-Location $vcsConsumer
        try {
            & composer install --no-interaction --no-plugins --no-scripts --prefer-dist --no-progress
            Assert-Check ($LASTEXITCODE -eq 0) 'Composer no pudo instalar desde GitHub VCS.'
        } finally {
            Pop-Location
        }
        $vcsInstalled = Join-Path $vcsConsumer 'vendor/jarifais/simplehtmldom-modernized'
        $vcsAutoload = Join-Path $vcsConsumer 'vendor/autoload.php'
        $vcsSource = Join-Path $vcsInstalled 'librerias/fase1/simple_html_dom.php'
        foreach ($file in @($vcsSource,(Join-Path $vcsInstalled 'simple_html_dom.php'))) {
            Assert-Check (Test-Path -LiteralPath $file -PathType Leaf) "Fuente VCS ausente: $file"
            Assert-Check (
                (Get-FileHash -LiteralPath $file -Algorithm SHA256).Hash.ToLowerInvariant() -ceq $expectedPhase
            ) "Biblioteca GitHub VCS distinta al checkpoint: $file"
        }
        Assert-Check (Test-Path -LiteralPath $vcsAutoload -PathType Leaf) 'autoload del consumidor GitHub ausente.'
        $vcsProbe = @(& $php $probePath $vcsAutoload $vcsSource 2>&1)
        Assert-Check (
            $LASTEXITCODE -eq 0 -and
            ($vcsProbe -join '').Trim() -ceq 'PKG-001-CONSUMIDOR-PASS'
        ) ('La API de GitHub VCS ha fallado: ' + ($vcsProbe -join ' | '))
        Write-Host "PKG-001: GITHUB VCS PASS ($vcsVersion)" -ForegroundColor Green

        $completed = $true
        Write-Host 'PKG-001: PASS - Composer instala y carga la API tradicional sin modificar el parser.' -ForegroundColor Green
    } catch {
        $issues.Add($_.Exception.Message)
    } finally {
        Set-Location $savedLocation
        if (Test-Path -LiteralPath $scratch) {
            try {
                Remove-Item -LiteralPath $scratch -Recurse -Force -ErrorAction Stop
            } catch {
                $issues.Add("No se pudo borrar carpeta temporal: $($_.Exception.Message)")
            }
        }
    }

    if ($issues.Count -gt 0 -or -not $completed) {
        throw ('PKG-001 NO VALIDADA: ' + ($issues -join ' | '))
    }
}
