# manage.dornogovi.gov.mn домайныг сервэрийн нийтийн IP руу зааж өгнө.
#
# ХЭЗЭЭ ХЭРЭГТЭЙ ВЭ:
#   DNS-ийн A бичлэг хараахан засагдаагүй байгаа тул домайн дотоод хаяг
#   (10.52.1.67) руу заасан хэвээр байна. Энэ скрипт тухайн компьютер дээр
#   тэр заалтыг түр давж, домайнаар шууд ажиллах боломж олгоно.
#
#   DNS засагдсаны дараа энэ скрипт ХЭРЭГГҮЙ болно —
#   `-Remove` параметрээр бичлэгийг устгана.
#
# АЖИЛЛУУЛАХ (админ эрхээр):
#   powershell -ExecutionPolicy Bypass -File add-host-client.ps1
#
# УСТГАХ (DNS зассаны дараа):
#   powershell -ExecutionPolicy Bypass -File add-host-client.ps1 -Remove

param(
    [string]$ServerIp = '202.37.109.67',
    [string]$HostName = 'manage.dornogovi.gov.mn',
    [switch]$Remove
)

$hostsFile = "$env:SystemRoot\System32\drivers\etc\hosts"

if (-not ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
    Write-Host "Админ эрхээр ажиллуулна уу (Run as administrator)." -ForegroundColor Red
    exit 1
}

$lines = Get-Content $hostsFile
$pattern = "\s$([regex]::Escape($HostName))\s*$"

if ($Remove) {
    $kept = $lines | Where-Object { $_ -notmatch $pattern }
    Set-Content -Path $hostsFile -Value $kept -Encoding ASCII
    ipconfig /flushdns | Out-Null
    Write-Host "$HostName -ийн бичлэгийг устгалаа. Одоо DNS-ээр шийдэгдэнэ." -ForegroundColor Green
    exit 0
}

# Хуучин бичлэгүүдийг (127.0.0.1, 10.52.1.67, хуучин LAN хаяг гэх мэт) цэвэрлэнэ
$existing = $lines | Where-Object { $_ -match $pattern }
if ($existing) {
    Write-Host "Одоо байгаа бичлэгүүдийг сольж байна:" -ForegroundColor Yellow
    $existing | ForEach-Object { Write-Host "  $_" }
}

$kept = $lines | Where-Object { $_ -notmatch $pattern }
$kept += "$ServerIp $HostName"
Set-Content -Path $hostsFile -Value $kept -Encoding ASCII
ipconfig /flushdns | Out-Null

Write-Host "Тохирууллаа: $ServerIp $HostName" -ForegroundColor Green
Write-Host ""

Write-Host "Холболт шалгаж байна..." -ForegroundColor Cyan
$result = Test-NetConnection -ComputerName $ServerIp -Port 443 -WarningAction SilentlyContinue
if ($result.TcpTestSucceeded) {
    Write-Host "  $ServerIp`:443 -> нээлттэй" -ForegroundColor Green
    Write-Host ""
    Write-Host "Хөтчөөр нээнэ: https://$HostName/" -ForegroundColor Cyan
    Write-Host "Гэрчилгээ өөрөө гарын үсэг зурсан тул 'Advanced -> Proceed' гэж үргэлжлүүлнэ."
    Write-Host "(DNS зассаны дараа Let's Encrypt-ийн жинхэнэ гэрчилгээ суух тул анхааруулга алга болно.)"
} else {
    Write-Host "  $ServerIp`:443 -> ХААЛТТАЙ" -ForegroundColor Red
    Write-Host "  Интернэт холболтоо шалгана уу, эсвэл сервер унтарсан байж болно."
}
