$ErrorActionPreference = "Stop"
$Utf8 = New-Object System.Text.UTF8Encoding($false)
[Console]::InputEncoding = $Utf8
[Console]::OutputEncoding = $Utf8
$OutputEncoding = $Utf8

$Root = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $Root
foreach($name in @('http','tls')){$file=Join-Path $Root "runtime/$name.pid";if(Test-Path $file){$id=[int](Get-Content $file);$p=Get-Process -Id $id -ErrorAction SilentlyContinue;if($p){Stop-Process -Id $id -Force;Write-Host "Detenido $name PID $id"};Remove-Item $file -Force}}
