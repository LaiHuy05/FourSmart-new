param(
    [int]$DatabasePort = 3306,
    [string]$DatabaseName = 'foursmart_demo',
    [string]$DatabaseUser = 'foursmart_app',
    [Security.SecureString]$DatabasePassword,
    [string]$PrivateDirectory,
    [string]$DocumentRoot,
    [string]$BaseUrl
)
$ErrorActionPreference = 'Stop'
$repository = [IO.Path]::GetFullPath((Split-Path -Parent $PSScriptRoot))
if (!$DocumentRoot) { $DocumentRoot = Split-Path -Parent $repository }
if (!$PrivateDirectory) {
    $PrivateDirectory = Join-Path ([Environment]::GetFolderPath('LocalApplicationData')) ('FourSmart\' + (Split-Path -Leaf $repository))
}
if (![IO.Path]::IsPathRooted($PrivateDirectory) -or ![IO.Path]::IsPathRooted($DocumentRoot)) { throw 'Use absolute private and document-root paths.' }
$privatePath = [IO.Path]::GetFullPath($PrivateDirectory).TrimEnd('\','/')
$publicPath = [IO.Path]::GetFullPath($DocumentRoot).TrimEnd('\','/')
$isWithin = { param($path, $parent) $path.Equals($parent, [StringComparison]::OrdinalIgnoreCase) -or ($path + '\').StartsWith($parent + '\', [StringComparison]::OrdinalIgnoreCase) }
if ((& $isWithin $privatePath $publicPath) -or (& $isWithin $privatePath $repository)) { throw 'Private configuration must stay outside every web root and the repository.' }
if (!(& $isWithin $repository $publicPath)) { throw 'DocumentRoot must contain this checkout.' }
if ($DatabasePort -lt 1 -or $DatabasePort -gt 65535 -or $DatabaseName -cnotmatch '^[A-Za-z_][A-Za-z0-9_]{0,63}$' -or $DatabaseUser -cnotmatch '^[A-Za-z_][A-Za-z0-9_]{0,79}$' -or $DatabaseUser -ieq 'root') { throw 'Invalid port, database name or runtime user.' }
if (!$BaseUrl) {
    $relative = $repository.Substring($publicPath.Length).Trim('\','/')
    $urlPath = (($relative -split '[\\/]') | ForEach-Object { [Uri]::EscapeDataString($_) }) -join '/'
    $BaseUrl = 'http://localhost/' + $urlPath + '/mvc/index.php'
}
$url = $null
if (![Uri]::TryCreate($BaseUrl, [UriKind]::Absolute, [ref]$url) -or $url.Host -ne 'localhost' -or $url.Scheme -notin @('http','https') -or $url.UserInfo -or $url.Query -or $url.Fragment -or !$url.AbsolutePath.EndsWith('/index.php') -or $BaseUrl -match '[\r\n"]') { throw 'BaseUrl must be a localhost index.php URL without credentials, query or fragment.' }
$BaseUrl = $url.AbsoluteUri
$deployment = Join-Path $repository 'mvc\.htaccess'
$example = Join-Path $repository 'mvc\.htaccess.example'
if ((Test-Path -LiteralPath $privatePath) -or (Test-Path -LiteralPath $deployment)) { throw 'Existing configuration was preserved. Edit its private JSON/deployment explicitly instead of overwriting it.' }
$template = [IO.File]::ReadAllText($example)
if ($template -notmatch '(?m)^SetEnv FOURSMART_PASSWORD_RECOVERY_ENABLED 0\s*$' -or $template -notmatch '(?m)^SetEnv FOURSMART_SESSION_VERSION_MODE required\s*$') { throw 'Safe OFF/required template is missing.' }
if (!$DatabasePassword) { $DatabasePassword = Read-Host 'Password of the database runtime user (not the demo login)' -AsSecureString }
$pointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($DatabasePassword)
try {
    $plain = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($pointer)
    if (!$plain -or $plain.Contains([char]0)) { throw 'Database password must be nonempty and contain no NUL.' }
    New-Item -ItemType Directory -Path $privatePath | Out-Null
    $acl = Get-Acl -LiteralPath $privatePath
    $acl.SetAccessRuleProtection($true, $false)
    $currentSid = [Security.Principal.WindowsIdentity]::GetCurrent().User
    foreach ($sid in @($currentSid, [Security.Principal.SecurityIdentifier]'S-1-5-18', [Security.Principal.SecurityIdentifier]'S-1-5-32-544')) {
        $rule = New-Object Security.AccessControl.FileSystemAccessRule($sid, 'FullControl', 'ContainerInherit,ObjectInherit', 'None', 'Allow')
        $acl.AddAccessRule($rule)
    }
    Set-Acl -LiteralPath $privatePath -AclObject $acl
    $configFile = Join-Path $privatePath 'database.local.json'
    $utf8 = New-Object Text.UTF8Encoding($false)
    $config = [ordered]@{host='127.0.0.1'; port=$DatabasePort; database=$DatabaseName; user=$DatabaseUser; password=$plain}
    [IO.File]::WriteAllText($configFile, ($config | ConvertTo-Json), $utf8)
    $config = $null
    $text = $template.TrimEnd() + "`r`n`r`nSetEnv FOURSMART_DATABASE_CONFIG `"" + $configFile.Replace('\','/') + "`"`r`nSetEnv FOURSMART_APP_URL " + $BaseUrl + "`r`n"
    [IO.File]::WriteAllText($deployment, $text, $utf8)
    Write-Output 'Created private database JSON and ignored Apache deployment. Recovery OFF; sessions required.'
    Write-Output ('Private configuration: ' + $configFile)
    Write-Output ('Website: ' + $BaseUrl)
} finally {
    $plain = $null
    [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($pointer)
}
