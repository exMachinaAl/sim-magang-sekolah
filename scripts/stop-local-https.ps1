$ErrorActionPreference = 'Stop'
$runtime = Join-Path $env:LOCALAPPDATA 'SIMMAG\https'
$settings = Get-Content -LiteralPath "$runtime/settings.json" -Raw | ConvertFrom-Json
$configPath = Join-Path $runtime 'httpd.conf'
$router = Join-Path $settings.workspace 'scripts\local-https-router.php'
$owned = @(Get-CimInstance Win32_Process | Where-Object {
    ($_.Name -eq 'httpd.exe' -and $_.CommandLine -and $_.CommandLine.Contains($configPath)) -or
    ($_.Name -eq 'php.exe' -and $_.CommandLine -and $_.CommandLine.Contains($router))
})
# Stop parents first so Apache does not respawn the test child. Other Laragon services are untouched.
foreach ($process in ($owned | Sort-Object { if ($_.ParentProcessId -in $owned.ProcessId) { 1 } else { 0 } })) {
    Stop-Process -Id $process.ProcessId -ErrorAction SilentlyContinue
}
Write-Output 'SIMMAG local HTTPS processes stopped.'
