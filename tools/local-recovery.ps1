param([ValidateSet('start','stop','status','once','queue','enable','disable')][string]$Action = 'status')
$ErrorActionPreference = 'Stop'
$pythonPath = (Get-Command python -CommandType Application -ErrorAction Stop).Source
& $pythonPath (Join-Path $PSScriptRoot 'local-recovery.py') $Action
exit $LASTEXITCODE
