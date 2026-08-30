# Компьютерийн DNS-ийг Google/Cloudflare руу шилжүүлж, кэшийг цэвэрлэнэ.
#
# ХЭЗЭЭ ХЭРЭГТЭЙ ВЭ:
#   manage.dornogovi.gov.mn нээхэд «DNS_PROBE_FINISHED_NXDOMAIN» гарч,
#   nslookup manage.dornogovi.gov.mn 8.8.8.8 → 202.37.109.67 зөв өгч байвал.
#   Энэ нь интернэт үйлчилгээ эрхлэгчийн (Univision/Skytel/MobiCom) DNS
#   «байхгүй» гэсэн хариуг кэшэлсэн гэсэн үг — сервер, сертификат буруу биш.
#
# АЖИЛЛУУЛАХ (админ эрхээр):
#   powershell -ExecutionPolicy Bypass -File set-dns-client.ps1
#
# БУЦААХ (ISP-ийн DNS руу, DHCP автомат):
#   powershell -ExecutionPolicy Bypass -File set-dns-client.ps1 -Reset

param(
    [string[]]$Dns4 = @('8.8.8.8', '1.1.1.1'),
    [string[]]$Dns6 = @('2001:4860:4860::8888', '2606:4700:4700::1111'),
    [string]$TestHost = 'manage.dornogovi.gov.mn',
    [string]$ExpectIp = '202.37.109.67',
    [switch]$Reset
)

if (-not ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
    Write-Host "Админ эрхээр ажиллуулна уу (Run as administrator)." -ForegroundColor Red
    exit 1
}

# Идэвхтэй, интернэтэд холбогдсон адаптеруудыг л сонгоно
$adapters = Get-NetAdapter -Physical | Where-Object { $_.Status -eq 'Up' }
if (-not $adapters) {
    Write-Host "Идэвхтэй сүлжээний адаптер олдсонгүй." -ForegroundColor Red
    exit 1
}

foreach ($a in $adapters) {
    if ($Reset) {
        Set-DnsClientServerAddress -InterfaceIndex $a.ifIndex -ResetServerAddresses
        Write-Host "$($a.Name): DNS автомат (DHCP) болголоо." -ForegroundColor Green
    }
    else {
        Set-DnsClientServerAddress -InterfaceIndex $a.ifIndex -ServerAddresses ($Dns4 + $Dns6)
        Write-Host "$($a.Name): DNS -> $($Dns4 -join ', '), $($Dns6 -join ', ')" -ForegroundColor Green
    }
}

ipconfig /flushdns | Out-Null
Write-Host "DNS кэш цэвэрлэлээ." -ForegroundColor Cyan
Write-Host ""

if ($Reset) {
    Write-Host "Буцаалаа. ISP-ийн DNS хэвийн болсон эсэхийг шалгана уу." -ForegroundColor Yellow
    exit 0
}

Write-Host "Шалгаж байна: $TestHost" -ForegroundColor Cyan
try {
    $rec = Resolve-DnsName -Name $TestHost -Type A -ErrorAction Stop
    $ips = ($rec | Where-Object { $_.IPAddress }).IPAddress
    if ($ips -contains $ExpectIp) {
        Write-Host "  $TestHost -> $($ips -join ', ')  ЗӨВ" -ForegroundColor Green
        Write-Host ""
        Write-Host "Chrome-д хуучин кэш үлдсэн бол:" -ForegroundColor Yellow
        Write-Host "  chrome://net-internals/#dns -> Clear host cache"
        Write-Host "  эсвэл хөтчийг бүрэн хааж дахин нээнэ."
    }
    else {
        Write-Host "  $TestHost -> $($ips -join ', ')  (хүлээгдэж буй $ExpectIp биш)" -ForegroundColor Red
    }
}
catch {
    Write-Host "  Шийдэгдсэнгүй. 1-2 минут хүлээгээд дахин оролдоно уу." -ForegroundColor Red
    Write-Host "  Ажиллахгүй бол: deploy/add-host-client.ps1 (hosts бичлэг)" -ForegroundColor Yellow
}
