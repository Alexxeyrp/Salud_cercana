param([string]$OutputDirectory = (Join-Path $env:TEMP 'salud-cercana-deploy'))
$ErrorActionPreference = 'Stop'
$root = Split-Path $PSScriptRoot -Parent
$stage = Join-Path $OutputDirectory ('public-' + [guid]::NewGuid().ToString('N'))
New-Item -ItemType Directory -Path $stage -Force | Out-Null
$pages = @('index','login','logout','registro','perfil','noticias','crear_noticia','citaciones','usuarios-administracion','noticias-administracion','citaciones-administracion')
foreach ($page in $pages) { Copy-Item -LiteralPath (Join-Path $root "$page.php") -Destination $stage }
Copy-Item -LiteralPath (Join-Path $root '.htaccess') -Destination $stage
foreach ($directory in @('config','includes','css','js','uploads')) {
    $destination = Join-Path $stage $directory
    New-Item -ItemType Directory -Path $destination -Force | Out-Null
    foreach ($entry in Get-ChildItem -LiteralPath (Join-Path $root $directory) -Force) {
        # Real hosting credentials are never part of the public archive.
        if ($directory -eq 'config' -and $entry.Name -ieq 'private.php') { continue }
        Copy-Item -LiteralPath $entry.FullName -Destination $destination -Recurse
    }
}
# Refuse accidental credentials or source data in otherwise allowed folders.
if (Test-Path -LiteralPath (Join-Path $stage 'config/private.php')) {
    throw 'Private configuration found in staging; public archive was not created.'
}
$forbidden = Get-ChildItem -LiteralPath $stage -Recurse -Force -File | Where-Object {
    $_.Name -match '^\.env|\.(sql|pem|key|crt|cer|p12|pfx|log|bak|zip)$'
}
if ($forbidden) { throw 'Forbidden private file found in package; inspect staging privately.' }
$archive = Join-Path $OutputDirectory ('salud-public-' + (Get-Date -Format 'yyyyMMdd-HHmmss') + '.zip')
Add-Type -AssemblyName System.IO.Compression.FileSystem
[IO.Compression.ZipFile]::CreateFromDirectory($stage, $archive)
Write-Output $archive
