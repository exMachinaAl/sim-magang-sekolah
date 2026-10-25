$ErrorActionPreference = 'Stop'
$runtime = Join-Path $env:LOCALAPPDATA 'SIMMAG\https'
$settings = Get-Content -LiteralPath "$runtime/settings.json" -Raw | ConvertFrom-Json
if (!(Get-NetIPAddress -AddressFamily IPv4 | Where-Object { $_.IPAddress -eq $settings.address })) { throw 'Wi-Fi IP changed. Run setup-local-https.ps1 again with the new private IP.' }
function Test-OwnListener([int]$Port,[string]$Marker) {
    $listener = Get-NetTCPConnection -LocalPort $Port -State Listen -ErrorAction SilentlyContinue | Select-Object -First 1
    if (!$listener) { return $false }
    $process = Get-CimInstance Win32_Process -Filter "ProcessId = $($listener.OwningProcess)"
    if (!$process.CommandLine -or !$process.CommandLine.Contains($Marker)) { throw "Port $Port is used by another process; it was not stopped." }
    return $true
}
$router = Join-Path $settings.workspace 'scripts\local-https-router.php'
if (!(Test-OwnListener 18080 $router)) {
    Start-Process -FilePath $settings.php -WindowStyle Hidden -WorkingDirectory (Join-Path $settings.workspace 'public') -ArgumentList @('-S','127.0.0.1:18080',('"'+$router+'"')) -RedirectStandardOutput "$runtime/php-out.log" -RedirectStandardError "$runtime/php-error.log" | Out-Null
}
$configPath = Join-Path $runtime 'httpd.conf'
if (!(Test-OwnListener 8443 $configPath)) {
    Start-Process -FilePath $settings.apache -WindowStyle Hidden -ArgumentList @('-f',('"'+$configPath+'"')) -RedirectStandardOutput "$runtime/apache-out.log" -RedirectStandardError "$runtime/apache-start-error.log" | Out-Null
}
Write-Output "Open on PC and phone: https://$($settings.address):8443"
