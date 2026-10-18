$ErrorActionPreference = "Stop"
$Utf8 = New-Object System.Text.UTF8Encoding($false)
[Console]::InputEncoding = $Utf8
[Console]::OutputEncoding = $Utf8
$OutputEncoding = $Utf8

$Root = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $Root
$runtime = Join-Path $Root 'runtime'; New-Item -ItemType Directory -Force -Path $runtime | Out-Null
$httpPidFile = Join-Path $runtime 'http.pid'; $tlsPidFile = Join-Path $runtime 'tls.pid'
function Is-RunningPidFile($file) { if (!(Test-Path $file)) { return $false }; $id=[int](Get-Content $file); return $null -ne (Get-Process -Id $id -ErrorAction SilentlyContinue) }
if (!(Is-RunningPidFile $httpPidFile)) {
    $out=Join-Path $Root 'resultados/logs/http.stdout.log';$err=Join-Path $Root 'resultados/logs/http.stderr.log'
    $p=Start-Process php -ArgumentList @('-S','127.0.0.1:8080','-t',(Join-Path $Root 'servidor_objetivo/public'),(Join-Path $Root 'servidor_objetivo/public/router.php')) -PassThru -WindowStyle Hidden -RedirectStandardOutput $out -RedirectStandardError $err
    Set-Content $httpPidFile $p.Id
}
Start-Sleep -Milliseconds 500
try { $r=Invoke-WebRequest -Uri 'http://127.0.0.1:8080/basic' -UseBasicParsing -TimeoutSec 5; if($r.StatusCode-ne 200 -or $r.Headers['X-Test-Scenario'] -ne 'basic'){throw 'El puerto 8080 no pertenece al servidor objetivo'} } catch { throw "No se pudo iniciar HTTP: $_" }
if ((Test-Path (Join-Path $Root 'certificados/cert.pem')) -and !(Is-RunningPidFile $tlsPidFile)) {
    $out=Join-Path $Root 'resultados/logs/tls.stdout.log';$err=Join-Path $Root 'resultados/logs/tls.stderr.log'
    $p=Start-Process php -ArgumentList @((Join-Path $Root 'servidor_objetivo/tls_proxy.php'),'127.0.0.1:8443','127.0.0.1','8080') -PassThru -WindowStyle Hidden -RedirectStandardOutput $out -RedirectStandardError $err
    Set-Content $tlsPidFile $p.Id
}
Start-Sleep -Milliseconds 500
Write-Host 'HTTP:  http://127.0.0.1:8080'
Write-Host 'HTTPS: https://127.0.0.1:8443 (certificado autofirmado)'
