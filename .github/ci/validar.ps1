& {
    $ErrorActionPreference = 'Stop'
    Set-StrictMode -Version Latest

    $root = Split-Path -Parent (Split-Path -Parent $PSScriptRoot)
    $httpProcess = $null
    $tlsProcess = $null
    $issues = [System.Collections.Generic.List[string]]::new()
    $complete = $false
    $certDir = Join-Path $root 'certificados'
    $resultsDir = Join-Path $root 'resultados'

    function Assert-Check([bool]$condition, [string]$message) {
        if (-not $condition) { throw $message }
    }

    function Test-FreePort([int]$port) {
        $listener = [System.Net.Sockets.TcpListener]::new(
            [System.Net.IPAddress]::Loopback, $port
        )
        try {
            $listener.Start()
            return $true
        } catch {
            return $false
        } finally {
            $listener.Stop()
        }
    }

    try {
        Set-Location $root
        $phpExe = (Get-Command php -ErrorAction Stop).Source
        $gitExe = (Get-Command git -ErrorAction Stop).Source
        $null = Get-Command composer -ErrorAction Stop

        $phpVersion = [int](& $phpExe -r 'echo PHP_VERSION_ID;')
        Assert-Check ($LASTEXITCODE -eq 0 -and $phpVersion -ge 80400 -and $phpVersion -lt 80500) 'Se requiere PHP 8.4 en CI-001.'

        $modules = @(& $phpExe -m)
        Assert-Check ($LASTEXITCODE -eq 0) 'No se pudieron consultar extensiones PHP.'
        foreach ($module in @('iconv','openssl','json','libxml','zlib','dom','mbstring','xml','xmlwriter')) {
            Assert-Check ($modules -contains $module) "Falta extension: $module"
        }

        $paths = @(& $gitExe -C $root ls-files)
        Assert-Check ($LASTEXITCODE -eq 0 -and $paths.Count -ge 53) 'Inventario Git incorrecto.'
        $strictUtf8 = [System.Text.UTF8Encoding]::new($false,$true)
        foreach ($relative in $paths) {
            $path = Join-Path $root $relative
            $null = $strictUtf8.GetString([System.IO.File]::ReadAllBytes($path))
        }

        $phpFiles = @($paths | Where-Object { $_ -match '\.php$' })
        Assert-Check ($phpFiles.Count -ge 28) 'Faltan fuentes o pruebas PHP.'
        foreach ($relative in $phpFiles) {
            & $phpExe -l (Join-Path $root $relative) | Out-Null
            Assert-Check ($LASTEXITCODE -eq 0) "Fallo de sintaxis PHP: $relative"
        }
        Write-Host "LINT/UTF-8: PASS ($($phpFiles.Count) PHP)" -ForegroundColor Green

        $baseline = Get-Content (Join-Path $root 'HASHES_LIBRERIAS_BASE.json') -Raw -Encoding UTF8 | ConvertFrom-Json
        Assert-Check ($baseline.original.'simple_html_dom.php' -eq 'd3fcca27ce5d1c6ddb06402d8ea22ee1b3989f62c44e2025154c9d4eef501cdd') 'Referencia original modificada.'
        foreach ($version in @('original','fase1')) {
            foreach ($name in @('simple_html_dom.php','LICENSE')) {
                $expected = $baseline.$version.$name
                $actual = (Get-FileHash (Join-Path $root "librerias/$version/$name") -Algorithm SHA256).Hash
                Assert-Check ($expected -eq $actual) "Checkpoint invalido: $version/$name"
            }
        }
        Write-Host 'HASHES PREVIOS: PASS' -ForegroundColor Green

        Assert-Check (-not (Test-Path $certDir)) 'Existen certificados previos no gestionados por CI.'
        foreach ($folder in @('resultados/original','resultados/fase1','resultados/comparacion','resultados/benchmarks','resultados/logs','servidor_objetivo/logs','certificados')) {
            New-Item -ItemType Directory -Path (Join-Path $root $folder) -Force | Out-Null
        }

        foreach ($port in @(8080,8443)) {
            Assert-Check (Test-FreePort $port) "Puerto $port ocupado; no se detendran procesos ajenos."
        }

        $certScript = Join-Path $certDir 'generar.php'
        $certPhp = @'
<?php
$key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
if ($key === false) exit(1);
$csr = openssl_csr_new(['commonName' => '127.0.0.1'], $key, ['digest_alg' => 'sha256']);
if ($csr === false) exit(2);
$cert = openssl_csr_sign($csr, null, $key, 1, ['digest_alg' => 'sha256']);
if ($cert === false) exit(3);
if (!openssl_x509_export_to_file($cert, $argv[1])) exit(4);
if (!openssl_pkey_export_to_file($key, $argv[2])) exit(5);
'@
        [System.IO.File]::WriteAllText($certScript,$certPhp,[System.Text.UTF8Encoding]::new($false))
        & $phpExe $certScript (Join-Path $certDir 'cert.pem') (Join-Path $certDir 'key.pem')
        Assert-Check ($LASTEXITCODE -eq 0) 'No fue posible generar certificado TLS temporal.'

        $public = Join-Path $root 'servidor_objetivo/public'
        $router = Join-Path $public 'router.php'
        $httpArgs = '-S 127.0.0.1:8080 -t "' + $public + '" "' + $router + '"'
        $httpProcess = Start-Process -FilePath $phpExe -ArgumentList $httpArgs -PassThru -WorkingDirectory $root -RedirectStandardOutput (Join-Path $resultsDir 'logs/http.out.log') -RedirectStandardError (Join-Path $resultsDir 'logs/http.err.log')

        $ready = $false
        for ($attempt=0; $attempt -lt 25; $attempt++) {
            Start-Sleep -Milliseconds 400
            if ($httpProcess.HasExited) { break }
            try {
                $response = Invoke-WebRequest -Uri 'http://127.0.0.1:8080/basic' -TimeoutSec 2 -ErrorAction Stop
                if ($response.StatusCode -eq 200 -and $response.Headers['X-Test-Scenario'] -eq 'basic') {
                    $ready = $true
                    break
                }
            } catch {}
        }
        Assert-Check $ready 'Servidor HTTP local no disponible.'

        $tlsScript = Join-Path $root 'servidor_objetivo/tls_proxy.php'
        $tlsArgs = '"' + $tlsScript + '" 127.0.0.1:8443 127.0.0.1 8080'
        $tlsProcess = Start-Process -FilePath $phpExe -ArgumentList $tlsArgs -PassThru -WorkingDirectory $root -RedirectStandardOutput (Join-Path $resultsDir 'logs/tls.out.log') -RedirectStandardError (Join-Path $resultsDir 'logs/tls.err.log')

        $ready = $false
        for ($attempt=0; $attempt -lt 25; $attempt++) {
            Start-Sleep -Milliseconds 400
            if ($tlsProcess.HasExited) { break }
            try {
                $response = Invoke-WebRequest -Uri 'https://127.0.0.1:8443/https-test' -SkipCertificateCheck -TimeoutSec 2 -ErrorAction Stop
                if ($response.StatusCode -eq 200 -and $response.Content -match 'TRANSPORT_OK') {
                    $ready = $true
                    break
                }
            } catch {}
        }
        Assert-Check $ready 'Servidor HTTPS local no disponible.'
        Write-Host 'HTTP/HTTPS: PASS' -ForegroundColor Green

        & $phpExe (Join-Path $root 'pruebas/ejecutar_todo.php')
        Assert-Check ($LASTEXITCODE -eq 0) 'Suite funcional o comparador ha fallado.'

        $phase = Get-Content (Join-Path $resultsDir 'fase1/resultados.json') -Raw -Encoding UTF8 | ConvertFrom-Json
        $original = Get-Content (Join-Path $resultsDir 'original/resultados.json') -Raw -Encoding UTF8 | ConvertFrom-Json
        $comparison = Get-Content (Join-Path $resultsDir 'comparacion/resumen.json') -Raw -Encoding UTF8 | ConvertFrom-Json
        Assert-Check ($phase.summary.total -eq 22 -and $phase.summary.pass -eq 22 -and $phase.summary.fail -eq 0 -and $phase.summary.known_failure -eq 0) 'Fase1: resultado distinto de 22 PASS.'
        Assert-Check ($original.summary.total -eq 22) 'Faltan casos de referencia original.'
        Assert-Check ($comparison.validated -and $comparison.api_equal -and $comparison.hashes_unchanged -and $comparison.mandatory_failures.Count -eq 0) 'Comparacion de API/hashes/pruebas fallida.'

        $expectedIds = @('F1-001','F1-002','F1-003A','F1-003B','F1-004','F1-005','F1-006','F1-007A','F1-007B','F1-008','F1-009','F1-010','F1-011','F1-012','R-001','R-002','R-003','K-001','K-002','K-003','K-004','K-005')
        $definitions = Get-Content (Join-Path $root 'pruebas/casos/todos.php') -Raw -Encoding UTF8
        $definedIds = @([regex]::Matches($definitions,"'id'\s*=>\s*'([A-Z0-9-]+)'") | ForEach-Object { $_.Groups[1].Value })
        foreach ($actualIds in @(@($definedIds),@($phase.results | ForEach-Object { $_.test }),@($original.results | ForEach-Object { $_.test }))) {
            Assert-Check ($actualIds.Count -eq 22) 'Cantidad de IDs inesperada.'
            Assert-Check (@($actualIds | Sort-Object -Unique).Count -eq 22) 'IDs duplicados.'
            Assert-Check (@(Compare-Object ($expectedIds | Sort-Object) ($actualIds | Sort-Object)).Count -eq 0) 'IDs F1/K/R distintos al contrato.'
        }
        Write-Host 'SUITE FUNCIONAL 22/22 Y API: PASS' -ForegroundColor Green

        $benchFile = Join-Path $resultsDir 'benchmarks/f3_001_smoke.json'
        & $phpExe (Join-Path $root 'benchmarks/run.php') '--mode=smoke' '--seed=3001' ("--output=" + $benchFile)
        Assert-Check ($LASTEXITCODE -eq 0) 'Smoke F3-001 fallido.'
        $bench = Get-Content $benchFile -Raw -Encoding UTF8 | ConvertFrom-Json
        Assert-Check ($bench.validation.success -and $bench.validation.expected_samples -eq 126 -and $bench.validation.actual_samples -eq 126 -and $bench.validation.status_counts.PASS -eq 126 -and $bench.validation.status_counts.ERROR -eq 0 -and $bench.validation.status_counts.TIMEOUT -eq 0 -and $bench.validation.status_counts.SEMANTIC_MISMATCH -eq 0) 'Smoke F3-001: resultado distinto de 126 PASS.'
        Write-Host 'BENCHMARK SMOKE 126/126: PASS' -ForegroundColor Green

        & composer install --no-interaction --prefer-dist --no-progress --no-plugins --no-scripts
        Assert-Check ($LASTEXITCODE -eq 0) 'Instalacion Composer fallida.'
        $phpunit = Join-Path $root 'vendor/bin/phpunit'
        Assert-Check (Test-Path $phpunit -PathType Leaf) 'PHPUnit no instalado.'

        & $phpExe $phpunit -c (Join-Path $root 'phpunit.xml') --filter F2007PublicInputContractTest
        Assert-Check ($LASTEXITCODE -eq 0) 'PHPUnit especifico fallido.'

        $junit = Join-Path $resultsDir 'phpunit.xml'
        & $phpExe $phpunit -c (Join-Path $root 'phpunit.xml') --log-junit $junit
        Assert-Check ($LASTEXITCODE -eq 0 -and (Test-Path $junit)) 'PHPUnit completo fallido o sin informe.'
        [xml]$report = Get-Content $junit -Raw -Encoding UTF8
        $suite = $report.DocumentElement
        Assert-Check ([int]$suite.GetAttribute('tests') -ge 62 -and [int]$suite.GetAttribute('failures') -eq 0 -and [int]$suite.GetAttribute('errors') -eq 0 -and [int]$suite.GetAttribute('skipped') -eq 0) 'JUnit no supera el contrato de la suite.'
        Write-Host 'PHPUNIT ESPECIFICO Y COMPLETO: PASS' -ForegroundColor Green

        foreach ($version in @('original','fase1')) {
            foreach ($name in @('simple_html_dom.php','LICENSE')) {
                $expected = $baseline.$version.$name
                $actual = (Get-FileHash (Join-Path $root "librerias/$version/$name") -Algorithm SHA256).Hash
                Assert-Check ($expected -eq $actual) "Hash final incorrecto: $version/$name"
            }
        }

        $trackedChanges = @(& $gitExe -C $root diff --name-only)
        Assert-Check ($LASTEXITCODE -eq 0) 'No se pudo revisar el arbol Git.'
        Assert-Check (@($trackedChanges | Where-Object { $_ -ne 'MATRIZ_COMPATIBILIDAD.md' }).Count -eq 0) 'El test modifico otros archivos versionados.'
        if ($trackedChanges -contains 'MATRIZ_COMPATIBILIDAD.md') {
            & $gitExe -C $root restore -- 'MATRIZ_COMPATIBILIDAD.md'
            Assert-Check ($LASTEXITCODE -eq 0) 'No se pudo restaurar la matriz generada.'
        }
        $dirty = @(& $gitExe -C $root status --porcelain --untracked-files=no)
        Assert-Check ($LASTEXITCODE -eq 0 -and $dirty.Count -eq 0) 'Archivos versionados modificados despues de las pruebas.'

        $complete = $true
    } catch {
        $issues.Add($_.Exception.Message)
        Write-Host "FALLO DE CI: $($_.Exception.Message)" -ForegroundColor Red
    } finally {
        foreach ($process in @($tlsProcess,$httpProcess)) {
            if ($null -ne $process) {
                try {
                    $live = Get-Process -Id $process.Id -ErrorAction SilentlyContinue
                    if ($null -ne $live) {
                        Stop-Process -Id $process.Id -Force -ErrorAction Stop
                        $process.WaitForExit(5000) | Out-Null
                    }
                    if (Get-Process -Id $process.Id -ErrorAction SilentlyContinue) {
                        $issues.Add("Servidor PID $($process.Id) sigue activo.")
                    }
                } catch {
                    $issues.Add("No se pudo detener PID $($process.Id): $($_.Exception.Message)")
                }
            }
        }
        if (Test-Path $certDir) {
            try { Remove-Item -LiteralPath $certDir -Recurse -Force -ErrorAction Stop }
            catch { $issues.Add("No se eliminaron certificados temporales: $($_.Exception.Message)") }
        }
        foreach ($port in @(8080,8443)) {
            if (-not (Test-FreePort $port)) {
                $issues.Add("El puerto $port sigue ocupado tras las pruebas.")
            }
        }
    }

    if ($issues.Count -ne 0 -or -not $complete) {
        throw ('CI-001 NO VALIDADA: ' + ($issues -join ' | '))
    }
    Write-Host 'CI-001: PASS; PHP/API/hashes intactos, servidores detenidos y certificados eliminados.' -ForegroundColor Green
}
