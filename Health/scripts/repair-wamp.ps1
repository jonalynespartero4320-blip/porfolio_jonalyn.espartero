#Requires -RunAsAdministrator
$ErrorActionPreference = 'Stop'
Start-Transcript -Path (Join-Path $PSScriptRoot '..\storage\wamp-repair.log') -Append | Out-Null
$wampRoot = 'C:\wamp64'
$projectPublic = 'C:\wamp\www\Health\public'
$apacheExe = Join-Path $wampRoot 'bin\apache\apache2.4.65\bin\httpd.exe'
$wampPhp = Join-Path $wampRoot 'bin\php\php8.4.15\php.exe'
$mysqlIni = Join-Path $wampRoot 'bin\mysql\mysql8.4.7\my.ini'
$managerConf = Join-Path $wampRoot 'wampmanager.conf'
$aliasPath = Join-Path $wampRoot 'alias\health.conf'
$stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
foreach ($requiredPath in @($apacheExe, $wampPhp, $mysqlIni, $managerConf, $projectPublic)) {
    if (-not (Test-Path -LiteralPath $requiredPath)) { throw "Required path not found: $requiredPath" }
}
$mysqlService = Get-Service -Name wampmysqld64
if ($mysqlService.Status -ne 'Running') {
    $probe = [System.Net.Sockets.TcpListener]::new([System.Net.IPAddress]::Loopback, 3308)
    try { $probe.Start() } finally { $probe.Stop() }
    Copy-Item -LiteralPath $mysqlIni -Destination "$mysqlIni.health-backup-$stamp"
    Copy-Item -LiteralPath $managerConf -Destination "$managerConf.health-backup-$stamp"
    Push-Location (Join-Path $wampRoot 'scripts')
    try {
        & $wampPhp 'switchMysqlPort.php' '3308'
        if ($LASTEXITCODE -ne 0) { throw 'WAMP port-change helper failed.' }
    } finally { Pop-Location }
    if (([regex]::Matches([System.IO.File]::ReadAllText($mysqlIni), '(?m)^port\s*=\s*3308\s*$')).Count -ne 3) {
        throw 'WAMP MySQL port configuration did not update as expected.'
    }
    Start-Service -Name wampmysqld64
    (Get-Service wampmysqld64).WaitForStatus('Running', [TimeSpan]::FromSeconds(30))
}
$alias = @'
# Health project: expose only the public directory, and only to local clients.
Alias "/Health/public" "C:/wamp/www/Health/public"
<Directory "C:/wamp/www/Health/public">
    Options -Indexes +FollowSymLinks
    AllowOverride All
    Require local
</Directory>
'@
if (Test-Path -LiteralPath $aliasPath) { Copy-Item -LiteralPath $aliasPath -Destination "$aliasPath.health-backup-$stamp" }
[System.IO.File]::WriteAllText($aliasPath, $alias, [System.Text.UTF8Encoding]::new($false))
& $apacheExe '-t'
if ($LASTEXITCODE -ne 0) { throw 'Apache configuration validation failed; Apache was not restarted.' }
Restart-Service -Name wampapache64
(Get-Service wampapache64).WaitForStatus('Running', [TimeSpan]::FromSeconds(30))
Write-Output 'WAMP MySQL and Apache are running. XAMPP services were not stopped.'
Write-Output 'Customer URL: http://localhost/Health/public/customer'
Write-Output 'WAMP MySQL uses port 3308; MariaDB remains on 3307.'
Stop-Transcript | Out-Null
