param(
    [Parameter(Mandatory=$true)][string]$Address,
    [string]$Apache = 'C:\laragon\bin\apache\httpd-2.4.54-win64-VS16\bin\httpd.exe'
)
$ErrorActionPreference = 'Stop'
$workspace = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$interface = Get-NetIPAddress -AddressFamily IPv4 | Where-Object { $_.IPAddress -eq $Address } | Select-Object -First 1
if (!$interface -or $Address -notmatch '^(10\.|192\.168\.|172\.(1[6-9]|2[0-9]|3[01])\.)') { throw 'Use the private IPv4 address currently assigned to this computer.' }
$php = (Get-Command php -ErrorAction Stop).Source
$openssl = (Get-Command openssl -ErrorAction Stop).Source
if (!(Test-Path -LiteralPath $Apache)) { throw 'Apache executable not found; supply -Apache.' }
$runtime = Join-Path $env:LOCALAPPDATA 'SIMMAG\https'
New-Item -ItemType Directory -Path $runtime -Force | Out-Null
$identity = [Security.Principal.WindowsIdentity]::GetCurrent().Name
& icacls.exe $runtime /inheritance:r /grant:r "${identity}:(OI)(CI)F" 'SYSTEM:(OI)(CI)F' | Out-Null
if ($LASTEXITCODE -ne 0) { throw 'Cannot restrict private-key directory permissions.' }
if (Test-Path -LiteralPath "$runtime/settings.json") { & (Join-Path $PSScriptRoot 'stop-local-https.ps1') }
function Invoke-OpenSsl([string[]]$Arguments) {
    $oldPreference = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'
    & $openssl @Arguments 2> $null
    $code = $LASTEXITCODE
    $ErrorActionPreference = $oldPreference
    if ($code -ne 0) { throw 'OpenSSL failed; no server was started.' }
}
$ca = Join-Path $runtime 'ca.pem'
$caKey = Join-Path $runtime 'ca-private.key'
[IO.File]::WriteAllText("$runtime/openssl.cnf","[req]`ndistinguished_name=dn`n[dn]`n")
if (!(Test-Path -LiteralPath $ca)) {
    if (Test-Path -LiteralPath $caKey) { throw 'Incomplete CA exists; inspect it before retrying.' }
    Invoke-OpenSsl @('req','-config',"$runtime/openssl.cnf",'-x509','-newkey','rsa:2048','-nodes','-keyout',$caKey,'-out',$ca,'-days','365',
        '-subj','/CN=SIMMAG Local Kiosk Test CA','-addext','basicConstraints=critical,CA:TRUE,pathlen:0',
        '-addext','keyUsage=critical,keyCertSign,cRLSign')
}
if (!(Test-Path -LiteralPath $caKey)) { throw 'CA private key missing.' }
# Issue a short-lived server certificate. The CA stays the same on reruns.
$extensions = "subjectAltName=IP:$Address,IP:127.0.0.1,DNS:localhost`nbasicConstraints=critical,CA:FALSE`nkeyUsage=critical,digitalSignature,keyEncipherment`nextendedKeyUsage=serverAuth`n"
[IO.File]::WriteAllText((Join-Path $runtime 'server.ext'),$extensions)
Invoke-OpenSsl @('req','-config',"$runtime/openssl.cnf",'-new','-newkey','rsa:2048','-nodes','-keyout',"$runtime/server-private.key",'-out',"$runtime/server.csr",'-subj',"/CN=$Address")
Invoke-OpenSsl @('x509','-req','-in',"$runtime/server.csr",'-CA',$ca,'-CAkey',$caKey,'-CAcreateserial','-out',"$runtime/server.pem",'-days','30','-sha256','-extfile',"$runtime/server.ext")
Invoke-OpenSsl @('x509','-in',$ca,'-outform','DER','-out',"$runtime/SIMMAG-Kiosk-CA.crt")
# Trust-store import is manual: Windows can show an interactive root-CA prompt.
$apacheRoot = Split-Path (Split-Path $Apache -Parent) -Parent
$configuration = @'
ServerRoot "__APACHE_ROOT__"
ServerName __IP__:8443
Listen __IP__:8443 https
PidFile "__RUNTIME__/httpd.pid"
LoadModule authz_core_module modules/mod_authz_core.so
LoadModule authz_host_module modules/mod_authz_host.so
LoadModule log_config_module modules/mod_log_config.so
LoadModule headers_module modules/mod_headers.so
LoadModule ssl_module modules/mod_ssl.so
LoadModule socache_shmcb_module modules/mod_socache_shmcb.so
LoadModule proxy_module modules/mod_proxy.so
LoadModule proxy_http_module modules/mod_proxy_http.so
ErrorLog "__RUNTIME__/apache-error.log"
LogLevel warn
ServerTokens Prod
ServerSignature Off
SSLSessionCache "shmcb:__RUNTIME__/ssl-cache(512000)"
<VirtualHost __IP__:8443>
    ServerName __IP__:8443
    DocumentRoot "__WORKSPACE__/public"
    SSLEngine on
    SSLProtocol -all +TLSv1.2 +TLSv1.3
    SSLCertificateFile "__RUNTIME__/server.pem"
    SSLCertificateKeyFile "__RUNTIME__/server-private.key"
    ProxyRequests Off
    ProxyPreserveHost On
    RequestHeader unset X-Forwarded-For
    RequestHeader unset Forwarded
    RequestHeader set X-Forwarded-Proto "https"
    RequestHeader set X-Forwarded-Port "8443"
    ProxyPass / http://127.0.0.1:18080/ connectiontimeout=5 timeout=120
    ProxyPassReverse / http://127.0.0.1:18080/
    <Location />
        Require ip __IP__/__PREFIX__
    </Location>
    # Deliberately omit request paths/query strings containing kiosk QR tokens.
    CustomLog "__RUNTIME__/apache-access.log" "%a %t %m %>s %b"
</VirtualHost>
'@
$configuration = $configuration.Replace('__APACHE_ROOT__',$apacheRoot.Replace('\','/')).Replace('__RUNTIME__',$runtime.Replace('\','/')).Replace('__WORKSPACE__',$workspace.Replace('\','/')).Replace('__IP__',$Address).Replace('__PREFIX__',[string]$interface.PrefixLength)
[IO.File]::WriteAllText("$runtime/httpd.conf",$configuration)
& $Apache -f "$runtime/httpd.conf" -t
if ($LASTEXITCODE -ne 0) { throw 'Apache configuration invalid; .env was not changed.' }
$envPath = Join-Path $workspace '.env'
Copy-Item -LiteralPath $envPath -Destination (Join-Path $runtime ('env-before-https-'+(Get-Date -Format 'yyyyMMdd-HHmmss')+'.backup'))
$envText = [IO.File]::ReadAllText($envPath)
$settings = [ordered]@{ APP_URL="https://${Address}:8443"; APP_DEBUG='false'; SESSION_SECURE_COOKIE='true'; SESSION_COOKIE='simmag_https_session'; TRUSTED_PROXIES='127.0.0.1,::1' }
foreach ($entry in $settings.GetEnumerator()) {
    $line = $entry.Key+'='+$entry.Value
    $pattern = '(?m)^'+[regex]::Escape($entry.Key)+'=.*$'
    if ([regex]::IsMatch($envText,$pattern)) { $envText = [regex]::Replace($envText,$pattern,[System.Text.RegularExpressions.MatchEvaluator]{param($match) $line}) }
    else { $envText += "`r`n$line" }
}
[IO.File]::WriteAllText($envPath,$envText,(New-Object System.Text.UTF8Encoding($false)))
Push-Location $workspace
try { & $php artisan config:clear; if ($LASTEXITCODE -ne 0) { throw 'Failed clearing config cache.' } }
finally { Pop-Location }
$runtimeSettings = @{ address=$Address; php=$php; apache=$Apache; workspace=$workspace }
[IO.File]::WriteAllText("$runtime/settings.json",($runtimeSettings | ConvertTo-Json))
& (Join-Path $PSScriptRoot 'start-local-https.ps1')
Write-Output "Phone CA certificate: $runtime\SIMMAG-Kiosk-CA.crt"
Write-Output 'Private keys remain on this computer. If the phone cannot connect, follow the firewall instructions in docs/HTTPS_KIOSK_LOKAL.md.'
