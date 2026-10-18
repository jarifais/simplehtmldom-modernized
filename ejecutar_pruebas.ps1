$ErrorActionPreference = "Stop"
$Utf8 = New-Object System.Text.UTF8Encoding($false)
[Console]::InputEncoding = $Utf8
[Console]::OutputEncoding = $Utf8
$OutputEncoding = $Utf8

$Root = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $Root
try { Invoke-WebRequest -Uri 'http://127.0.0.1:8080/basic' -UseBasicParsing -TimeoutSec 3 | Out-Null } catch { throw "El servidor no está activo. Ejecute .\iniciar_entorno.ps1" }
& php (Join-Path $Root 'pruebas/ejecutar_todo.php'); $runnerCode=$LASTEXITCODE
$composer=Get-Command composer -ErrorAction SilentlyContinue
if($composer -and (Test-Path (Join-Path $Root 'vendor/bin/phpunit'))){& php (Join-Path $Root 'vendor/bin/phpunit') -c (Join-Path $Root 'phpunit.xml') --log-junit (Join-Path $Root 'resultados/phpunit.xml');if($LASTEXITCODE-ne 0){$runnerCode=$LASTEXITCODE};& php (Join-Path $Root 'pruebas/comparar_resultados.php') | Out-Null}else{Write-Warning 'PHPUnit no está instalado; el runner autónomo sí se ha ejecutado.'}
Write-Host "Informe: $(Join-Path $Root 'INFORME_PRUEBAS_FASE_1.md')"
exit $runnerCode
