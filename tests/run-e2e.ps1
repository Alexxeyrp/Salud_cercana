param(
    [string]$Php = 'php',
    [string]$Fixture = '',
    [int]$PortA = 18081,
    [int]$PortB = 18082
)
$ErrorActionPreference = 'Stop'
$repoPath = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
if ($Fixture -ne '') {
    if (-not (Test-Path -LiteralPath $Fixture)) { throw 'The requested local fixture environment script was not found.' }
    . $Fixture
}
$phpExecutable = (Get-Command $Php -ErrorAction Stop).Source
if ($env:SALUD_TEST_DB -ne '1' -or $env:APP_ENV -ne 'local' -or
    $env:DB_HOST -notin @('localhost', '127.0.0.1', '::1') -or
    $env:DB_PORT -in @('3306', '3307') -or
    -not $env:DB_NAME.StartsWith('salud_test_')) {
    throw 'Only explicitly enabled local salud_test_ databases are allowed.'
}
if ($PortA -eq $PortB) { throw 'Two distinct loopback ports are required.' }
foreach ($port in @($PortA, $PortB)) {
    $listener = [System.Net.Sockets.TcpListener]::new([System.Net.IPAddress]::Loopback, $port)
    try { $listener.Start() } finally { $listener.Stop() }
}
$logRoot = Join-Path ([System.IO.Path]::GetTempPath()) ('salud-e2e-' + [guid]::NewGuid().ToString('N'))
New-Item -ItemType Directory -Path $logRoot | Out-Null
$servers = @()
try {
    & $phpExecutable (Join-Path $PSScriptRoot 'e2e_setup.php')
    if ($LASTEXITCODE -ne 0) { throw 'Fixture setup failed.' }
    foreach ($port in @($PortA, $PortB)) {
        $arguments = @('-d', 'display_errors=0', '-d', 'post_max_size=4M', '-d', 'upload_max_filesize=3M',
            '-S', ('127.0.0.1:' + $port), '-t', ('"' + $repoPath + '"'), ('"' + (Join-Path $repoPath 'api\index.php') + '"'))
        $servers += Start-Process -FilePath $phpExecutable -ArgumentList $arguments -WorkingDirectory $repoPath -WindowStyle Hidden -PassThru `
            -RedirectStandardOutput (Join-Path $logRoot ('server-' + $port + '.out.log')) `
            -RedirectStandardError (Join-Path $logRoot ('server-' + $port + '.err.log'))
    }
    foreach ($port in @($PortA, $PortB)) {
        $ready = $false
        for ($attempt = 0; $attempt -lt 100; $attempt++) {
            $client = [System.Net.Sockets.TcpClient]::new()
            try { $client.Connect('127.0.0.1', $port); $ready = $true; break }
            catch { Start-Sleep -Milliseconds 50 }
            finally { $client.Dispose() }
        }
        if (-not $ready) { throw 'A fixture HTTP server did not start.' }
    }
    & $phpExecutable (Join-Path $PSScriptRoot 'e2e.php') ('http://127.0.0.1:' + $PortA) ('http://127.0.0.1:' + $PortB)
    if ($LASTEXITCODE -ne 0) { throw 'E2E checks failed; no production service was used.' }
} finally {
    foreach ($server in $servers) {
        if (-not $server.HasExited) { Stop-Process -InputObject $server }
        $server.WaitForExit(5000) | Out-Null
    }
    # Exact generated files only; preserve synthetic DB rows for inspection.
    foreach ($port in @($PortA, $PortB)) {
        foreach ($suffix in @('out', 'err')) {
            $logFile = Join-Path $logRoot ('server-' + $port + '.' + $suffix + '.log')
            if (Test-Path -LiteralPath $logFile) { Remove-Item -LiteralPath $logFile }
        }
    }
    Remove-Item -LiteralPath $logRoot
}
