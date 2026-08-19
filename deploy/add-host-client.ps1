# Дотоод сүлжээн доторх ӨӨР компьютер дээр ажиллуулна (админ эрхээр).
# manage.dornogovi.gov.mn домайныг серверийн LAN хаяг руу заана.
#
# Ажиллуулах: PowerShell-ийг "Run as administrator"-аар нээгээд
#   powershell -ExecutionPolicy Bypass -File add-host-client.ps1
#
# Сервер компьютерийн LAN хаяг өөрчлөгдвөл $ServerIp-г засна.

param(
    [string]$ServerIp = '192.168.0.123',
    [string]$HostName = 'manage.dornogovi.gov.mn'
)

$hostsFile = 'C:\Windows\System32\drivers\etc\hosts'

if (-not ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
    Write-Host "Админ эрхээр ажиллуулна уу (Run as administrator)." -ForegroundColor Red
    exit 1
}

$existing = Select-String -Path $hostsFile -Pattern ([regex]::Escape($HostName)) -ErrorAction SilentlyContinue

if ($existing) {
    Write-Host "Аль хэдийн бичигдсэн байна:" -ForegroundColor Yellow
    $existing | ForEach-Object { "  $($_.Line)" }
    Write-Host "Хаяг буруу бол hosts файлыг гараар засна уу: $hostsFile"
} else {
    Add-Content -Path $hostsFile -Value "$ServerIp $HostName"
    Write-Host "Нэмэгдлээ: $ServerIp $HostName" -ForegroundColor Green
}

ipconfig /flushdns | Out-Null

Write-Host ""
Write-Host "Шалгах:" -ForegroundColor Cyan
try {
    $result = Test-NetConnection -ComputerName $ServerIp -Port 443 -WarningAction SilentlyContinue
    Write-Host "  $ServerIp`:443 -> $(if ($result.TcpTestSucceeded) { 'нээлттэй' } else { 'ХААЛТТАЙ — серверийн firewall/сүлжээг шалга' })"
} catch {
    Write-Host "  холболт шалгаж чадсангүй: $($_.Exception.Message)"
}

Write-Host ""
Write-Host "Одоо хөтчөөр нээнэ: https://$HostName" -ForegroundColor Cyan
Write-Host "Гэрчилгээ нь өөрөө гарын үсэг зурсан тул 'Advanced -> Proceed' гэж үргэлжлүүлнэ."
