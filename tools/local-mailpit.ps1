param(
    [ValidateSet('start', 'stop', 'status')][string]$Action = 'status',
    [string]$Binary,
    [string]$RuntimeDirectory
)
$ErrorActionPreference = 'Stop'
$localToolsPath = Join-Path $PSScriptRoot 'local-tools.local.json'
$localTools = if (Test-Path -LiteralPath $localToolsPath) { Get-Content -LiteralPath $localToolsPath -Raw | ConvertFrom-Json } else { $null }
$mailpitBinary = if ($Binary) { $Binary } elseif ($env:FOURSMART_MAILPIT_BINARY) { $env:FOURSMART_MAILPIT_BINARY } elseif ($localTools.mailpit_binary) { $localTools.mailpit_binary } else { (Get-Command mailpit.exe -CommandType Application -ErrorAction Stop).Source }
$defaultRuntime = Join-Path ([Environment]::GetFolderPath('LocalApplicationData')) 'FourSmart\mailpit-local'
$mailpitRuntime = if ($RuntimeDirectory) { $RuntimeDirectory } elseif ($env:FOURSMART_MAILPIT_RUNTIME) { $env:FOURSMART_MAILPIT_RUNTIME } elseif ($localTools.mailpit_runtime) { $localTools.mailpit_runtime } else { $defaultRuntime }
$mailpitBinary = [IO.Path]::GetFullPath($mailpitBinary)
$mailpitRuntime = [IO.Path]::GetFullPath($mailpitRuntime)
$repositoryRoot = Split-Path -Parent $PSScriptRoot
$documentRoot = if ($localTools.document_root) { $localTools.document_root } else { Split-Path -Parent $repositoryRoot }
$normalizedWebRoot = [IO.Path]::GetFullPath($documentRoot).TrimEnd('\','/') + [IO.Path]::DirectorySeparatorChar
if (($mailpitRuntime + [IO.Path]::DirectorySeparatorChar).StartsWith($normalizedWebRoot, [StringComparison]::OrdinalIgnoreCase)) { throw 'Mailpit runtime must stay outside web roots.' }
$mailpitState = Join-Path $mailpitRuntime 'process.json'

function Get-ManagedMailpit {
    if (!(Test-Path -LiteralPath $mailpitState)) { return $null }
    $state = Get-Content -LiteralPath $mailpitState -Raw | ConvertFrom-Json
    $process = Get-Process -Id $state.processId -ErrorAction SilentlyContinue
    if (!$process) { return $null }
    if ($process.Path -ne $mailpitBinary -or $process.StartTime.ToUniversalTime().Ticks -ne $state.startTicks) {
        throw 'Mailpit process identity mismatch; refusing to operate on this PID.'
    }
    return $process
}

$process = Get-ManagedMailpit
if ($Action -eq 'stop') {
    if ($process) {
        Stop-Process -Id $process.Id
        $process.WaitForExit(5000) | Out-Null
        if (!$process.HasExited) { throw 'Mailpit did not stop.' }
    }
    if (Test-Path -LiteralPath $mailpitState) { Remove-Item -LiteralPath $mailpitState }
    Write-Output 'Mailpit stopped. Inbox retained.'
    exit
}

if ($Action -eq 'start' -and !$process) {
    if (!(Test-Path -LiteralPath $mailpitBinary)) { throw 'Verified Windows Mailpit binary is missing.' }
    $listeners = @(Get-NetTCPConnection -State Listen | Where-Object {$_.LocalPort -in 1025,8025})
    if ($listeners.Count -ne 0) { throw 'Port 1025 or 8025 is occupied; no process was stopped.' }
    New-Item -ItemType Directory -Path $mailpitRuntime -Force | Out-Null
    # Do not inherit relay, forwarding, webhook, POP3, authentication or TLS settings.
    $savedMailpitEnvironment = @{}
    foreach ($variable in @(Get-ChildItem Env: | Where-Object {$_.Name -like 'MP_*'})) {
        $savedMailpitEnvironment[$variable.Name] = $variable.Value
        [Environment]::SetEnvironmentVariable($variable.Name, $null, 'Process')
    }
    try {
        $arguments = @('--smtp','127.0.0.1:1025','--listen','127.0.0.1:8025',
            '--database',('"' + (Join-Path $mailpitRuntime 'mailpit.sqlite') + '"'),
            '--log-file',('"' + (Join-Path $mailpitRuntime 'mailpit.log') + '"'),
            '--quiet','--disable-version-check','--smtp-disable-rdns',
            '--block-remote-css-and-fonts','--allowed-hosts','127.0.0.1,localhost')
        $process = Start-Process -FilePath $mailpitBinary -ArgumentList $arguments -WorkingDirectory $mailpitRuntime -WindowStyle Hidden -PassThru
    } finally {
        foreach ($name in $savedMailpitEnvironment.Keys) {
            [Environment]::SetEnvironmentVariable($name, $savedMailpitEnvironment[$name], 'Process')
        }
    }
    @{processId=$process.Id; startTicks=$process.StartTime.ToUniversalTime().Ticks} |
        ConvertTo-Json | Set-Content -LiteralPath $mailpitState -Encoding UTF8
    $ready = $false
    for ($attempt=0; $attempt -lt 30; $attempt++) {
        if ($process.HasExited) { throw 'Mailpit exited during startup.' }
        try {
            $response = Invoke-WebRequest -UseBasicParsing -Uri 'http://127.0.0.1:8025/readyz' -TimeoutSec 1
            if ($response.StatusCode -eq 200) { $ready=$true; break }
        } catch { Start-Sleep -Milliseconds 100 }
    }
    if (!$ready) { Stop-Process -Id $process.Id; throw 'Mailpit readiness check failed.' }
}

if (!$process) { Write-Output 'Mailpit is stopped.'; exit }
$listeners = @(Get-NetTCPConnection -State Listen -OwningProcess $process.Id)
if ($listeners.Count -ne 2 -or @($listeners | Where-Object {$_.LocalAddress -ne '127.0.0.1' -or $_.LocalPort -notin 1025,8025}).Count -ne 0) {
    throw 'Unexpected Mailpit listener configuration.'
}
$response = Invoke-WebRequest -UseBasicParsing -Uri 'http://127.0.0.1:8025/readyz' -TimeoutSec 2
[pscustomobject]@{processId=$process.Id; smtp='127.0.0.1:1025'; webUI='http://127.0.0.1:8025'; ready=$response.StatusCode -eq 200; runtime=$mailpitRuntime} | ConvertTo-Json
