& {
    $ErrorActionPreference = 'Stop'
    Set-StrictMode -Version Latest

    # DIST-002: reproducible offline ZIP, with closed allowlist.
    $root = Split-Path -Parent $PSScriptRoot
    $targetDir = Join-Path $root 'dist'
    $zipName = 'simplehtmldom-modernized-development.zip'
    $targetZip = Join-Path $targetDir $zipName
    $targetHash = "$targetZip.sha256"
    $scratch = Join-Path ([System.IO.Path]::GetTempPath()) (
        'simplehtmldom_dist002_' + [guid]::NewGuid().ToString('N')
    )
    $createdTarget = $false
    $success = $false
    $errorText = $null
    $lf = [string][char]10

    function Assert-Check([bool]$condition, [string]$message) {
        if (-not $condition) { throw $message }
    }

    try {
        Assert-Check (-not (Test-Path -LiteralPath $targetDir)) (
            "La carpeta dist ya existe. No se sobrescribira: $targetDir"
        )

        $phpExe = (Get-Command php -ErrorAction Stop).Source
        $strictUtf8 = [System.Text.UTF8Encoding]::new($false, $true)
        $baselinePath = Join-Path $root 'HASHES_LIBRERIAS_BASE.json'
        $baseline = Get-Content -LiteralPath $baselinePath -Raw -Encoding UTF8 |
            ConvertFrom-Json

        $original = Join-Path $root 'librerias/original/simple_html_dom.php'
        $maintained = Join-Path $root 'librerias/fase1/simple_html_dom.php'
        $standalone = Join-Path $root 'simple_html_dom.php'
        $originalExpected = 'd3fcca27ce5d1c6ddb06402d8ea22ee1b3989f62c44e2025154c9d4eef501cdd'
        $maintainedExpected = 'bba1d651b7c58eab6e70a031a6d8ca52b86c68bc746188440652f877b9110d25'

        $origHash = (Get-FileHash -LiteralPath $original -Algorithm SHA256).Hash.ToLowerInvariant()
        $sourceHash = (Get-FileHash -LiteralPath $maintained -Algorithm SHA256).Hash.ToLowerInvariant()
        $publicHash = (Get-FileHash -LiteralPath $standalone -Algorithm SHA256).Hash.ToLowerInvariant()
        Assert-Check ($origHash -eq $originalExpected -and $origHash -eq $baseline.original.'simple_html_dom.php') 'La biblioteca original no coincide con el checkpoint.'
        Assert-Check ($sourceHash -eq $maintainedExpected -and $sourceHash -eq $baseline.fase1.'simple_html_dom.php') 'La biblioteca mantenida no coincide con el checkpoint.'
        Assert-Check ($publicHash -eq $sourceHash) 'El archivo unico difiere de la biblioteca mantenida.'

        $licensePath = Join-Path $root 'LICENSE'
        $licenseHash = (Get-FileHash -LiteralPath $licensePath -Algorithm SHA256).Hash.ToLowerInvariant()

        $inputs = [ordered]@{
            'LICENSE' = 'LICENSE'
            'README.md' = 'distribucion/README_PAQUETE.md'
            'ejemplos/ejemplo_basico.php' = 'distribucion/ejemplos/ejemplo_basico.php'
            'simple_html_dom.php' = 'simple_html_dom.php'
        }
        $entries = [System.Collections.Generic.List[object]]::new()
        foreach ($name in @($inputs.Keys | Sort-Object -CaseSensitive)) {
            Assert-Check ($name -notmatch '(^/|\\|(^|/)\.\.?(/|$))') "Ruta insegura en el paquete: $name"
            $file = Join-Path $root $inputs[$name]
            Assert-Check (Test-Path -LiteralPath $file -PathType Leaf) "Falta archivo permitido: $name"
            $bytes = [System.IO.File]::ReadAllBytes($file)
            Assert-Check ($bytes.Length -gt 0) "Archivo vacio: $name"
            Assert-Check (-not ($bytes.Length -ge 3 -and $bytes[0] -eq 239 -and $bytes[1] -eq 187 -and $bytes[2] -eq 191)) "BOM UTF-8 no permitido: $name"
            $decoded = $strictUtf8.GetString($bytes)
            Assert-Check (-not $decoded.Contains([char]0xFFFD)) "Caracter de sustitucion: $name"
            $hash = (Get-FileHash -LiteralPath $file -Algorithm SHA256).Hash.ToLowerInvariant()
            $entries.Add([PSCustomObject]@{
                Name = $name
                Bytes = $bytes
                Hash = $hash
            })
        }
        Assert-Check ($entries.Count -eq 4) 'Inventario de distribucion inesperado.'

        $manifestText = (@($entries | ForEach-Object { "$($_.Hash)  $($_.Name)" }) -join $lf) + $lf
        $manifestBytes = $strictUtf8.GetBytes($manifestText)
        $entries.Add([PSCustomObject]@{
            Name = 'SHA256SUMS.txt'
            Bytes = $manifestBytes
            Hash = $null
        })

        New-Item -ItemType Directory -Path $scratch -Force | Out-Null
        $temporaryZip = Join-Path $scratch $zipName
        $fixedDate = [DateTimeOffset]::new(2000,1,1,0,0,0,[TimeSpan]::Zero)

        Add-Type -AssemblyName System.IO.Compression
        Add-Type -AssemblyName System.IO.Compression.FileSystem

        $fileStream = [System.IO.File]::Open(
            $temporaryZip, [System.IO.FileMode]::CreateNew
        )
        try {
            $archive = [System.IO.Compression.ZipArchive]::new(
                $fileStream,
                [System.IO.Compression.ZipArchiveMode]::Create,
                $true,
                [System.Text.Encoding]::UTF8
            )
            try {
                foreach ($item in @($entries | Sort-Object Name -CaseSensitive)) {
                    $zipEntry = $archive.CreateEntry(
                        $item.Name,
                        [System.IO.Compression.CompressionLevel]::NoCompression
                    )
                    $zipEntry.LastWriteTime = $fixedDate
                    $zipEntry.ExternalAttributes = (33188 -shl 16)
                    $stream = $zipEntry.Open()
                    try {
                        $stream.Write($item.Bytes, 0, $item.Bytes.Length)
                    } finally {
                        $stream.Dispose()
                    }
                }
            } finally {
                $archive.Dispose()
            }
        } finally {
            $fileStream.Dispose()
        }

        # .NET marca el creador ZIP como Unix (3) o DOS/Windows (0).
        # Estandarizamos el campo "version made by" de cada entrada central
        # a Unix (3), sin tocar datos, CRC, tamaños ni rutas.
        $zipBytes = [System.IO.File]::ReadAllBytes($temporaryZip)
        $eocd = $zipBytes.Length - 22
        Assert-Check ($eocd -ge 0) 'ZIP sin directorio central.'
        Assert-Check ([BitConverter]::ToUInt32($zipBytes, $eocd) -eq 0x06054b50) 'Final de directorio ZIP no esperado.'
        $entryCount = [int][BitConverter]::ToUInt16($zipBytes, $eocd + 10)
        $centralSize = [int][BitConverter]::ToUInt32($zipBytes, $eocd + 12)
        $centralStart = [int][BitConverter]::ToUInt32($zipBytes, $eocd + 16)
        Assert-Check ($entryCount -eq $entries.Count -and $entryCount -eq 5) 'Cantidad ZIP central inesperada.'
        Assert-Check ($centralStart -gt 0 -and $centralSize -gt 0 -and ($centralStart + $centralSize) -eq $eocd) 'Directorio central ZIP corrupto.'
        $cursor = $centralStart
        for ($i = 0; $i -lt $entryCount; $i++) {
            Assert-Check (($cursor + 46) -le $eocd) 'Encabezado central truncado.'
            Assert-Check ([BitConverter]::ToUInt32($zipBytes, $cursor) -eq 0x02014b50) 'Firma central ZIP inesperada.'
            $zipBytes[$cursor + 5] = [byte]3
            $nameLength = [int][BitConverter]::ToUInt16($zipBytes, $cursor + 28)
            $extraLength = [int][BitConverter]::ToUInt16($zipBytes, $cursor + 30)
            $commentLength = [int][BitConverter]::ToUInt16($zipBytes, $cursor + 32)
            $cursor += 46 + $nameLength + $extraLength + $commentLength
        }
        Assert-Check ($cursor -eq $eocd) 'Longitud del directorio central ZIP inesperada.'
        [System.IO.File]::WriteAllBytes($temporaryZip, $zipBytes)

        $unpack = Join-Path $scratch 'verificar'
        [System.IO.Compression.ZipFile]::ExtractToDirectory($temporaryZip, $unpack)
        $names = @(
            Get-ChildItem -LiteralPath $unpack -Recurse -File |
            ForEach-Object {
                $_.FullName.Substring($unpack.Length + 1).Replace('\','/')
            } | Sort-Object -CaseSensitive
        )
        $expectedNames = @($entries | ForEach-Object { $_.Name } | Sort-Object -CaseSensitive)
        Assert-Check ($names.Count -eq 5 -and @(
            Compare-Object $names $expectedNames -CaseSensitive
        ).Count -eq 0) 'Contenido extraido distinto del manifiesto.'

        foreach ($item in $entries) {
            $unpackedPath = Join-Path $unpack $item.Name
            $actualBytes = [System.IO.File]::ReadAllBytes($unpackedPath)
            Assert-Check ([Convert]::ToHexString($actualBytes) -ceq [Convert]::ToHexString($item.Bytes)) "Datos ZIP alterados: $($item.Name)"
            if ($null -ne $item.Hash) {
                $unpackedHash = (Get-FileHash -LiteralPath $unpackedPath -Algorithm SHA256).Hash.ToLowerInvariant()
                Assert-Check ($unpackedHash -eq $item.Hash) "Hash ZIP incorrecto: $($item.Name)"
            }
        }

        Assert-Check ((Get-FileHash -LiteralPath (Join-Path $unpack 'simple_html_dom.php') -Algorithm SHA256).Hash.ToLowerInvariant() -eq $maintainedExpected) 'La biblioteca dentro del ZIP difiere de la fuente.'

        foreach ($relative in @('simple_html_dom.php','ejemplos/ejemplo_basico.php')) {
            & $phpExe -l (Join-Path $unpack $relative) | Out-Null
            Assert-Check ($LASTEXITCODE -eq 0) "Lint del paquete fallido: $relative"
        }

        $probe = @(& $phpExe (Join-Path $unpack 'ejemplos/ejemplo_basico.php') 2>&1)
        Assert-Check ($LASTEXITCODE -eq 0 -and ($probe -join $lf).Trim() -ceq 'Hola mundo') (
            'El ejemplo del ZIP no funciona aislado: ' + ($probe -join ' ')
        )

        Assert-Check ((Get-FileHash -LiteralPath $original -Algorithm SHA256).Hash.ToLowerInvariant() -eq $origHash) 'Biblioteca original modificada.'
        Assert-Check ((Get-FileHash -LiteralPath $maintained -Algorithm SHA256).Hash.ToLowerInvariant() -eq $sourceHash) 'Biblioteca mantenida modificada.'
        Assert-Check ((Get-FileHash -LiteralPath $standalone -Algorithm SHA256).Hash.ToLowerInvariant() -eq $publicHash) 'Biblioteca distribuible modificada.'
        Assert-Check ((Get-FileHash -LiteralPath $licensePath -Algorithm SHA256).Hash.ToLowerInvariant() -eq $licenseHash) 'Licencia modificada.'

        New-Item -ItemType Directory -Path $targetDir -ErrorAction Stop | Out-Null
        $createdTarget = $true
        [System.IO.File]::Copy($temporaryZip, $targetZip)
        $zipHash = (Get-FileHash -LiteralPath $targetZip -Algorithm SHA256).Hash.ToLowerInvariant()
        $hashLine = "$zipHash  $zipName" + $lf
        [System.IO.File]::WriteAllText($targetHash, $hashLine, $strictUtf8)
        Assert-Check ($zipHash -eq (Get-FileHash -LiteralPath $temporaryZip -Algorithm SHA256).Hash.ToLowerInvariant()) 'ZIP final no coincide con el verificado.'
        Assert-Check ((Get-Content -LiteralPath $targetHash -Raw -Encoding UTF8) -ceq $hashLine) 'Hash externo no coincide.'

        $success = $true
        Write-Host "DIST-002: PASS - ZIP con 5 archivos, hashes verificados y PHP aislado." -ForegroundColor Green
        Write-Host "SHA-256 ZIP: $zipHash"
        Write-Host "ZIP: $targetZip"
    } catch {
        $errorText = $_.Exception.Message
    } finally {
        try {
            if (Test-Path -LiteralPath $scratch) {
                Remove-Item -LiteralPath $scratch -Recurse -Force -ErrorAction Stop
            }
        } catch {
            $success = $false
            $errorText = "No se pudo limpiar temporal: $($_.Exception.Message)"
        }
        if (-not $success -and $createdTarget -and (Test-Path -LiteralPath $targetDir)) {
            try {
                Remove-Item -LiteralPath $targetDir -Recurse -Force -ErrorAction Stop
            } catch {
                $errorText += " | Fallo rollback de dist: $($_.Exception.Message)"
            }
        }
    }
    if (-not $success) {
        throw "DIST-002 NO VALIDADA: $errorText"
    }
}
