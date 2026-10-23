# Manage Dornogovi — устгах.
#
# Өргөтгөлийн хавтас, товчлол, бодлогын түлхүүрийг арилгана. Хөтчөөс
# өргөтгөлийг өөрийг нь chrome://extensions хуудаснаас хасна.

$ErrorActionPreference = 'SilentlyContinue'

$AppName = 'Manage Dornogovi'
$Root = Join-Path $env:LOCALAPPDATA 'ManageDornogovi'

Write-Host ''
Write-Host "  $AppName — устгаж байна…" -ForegroundColor Cyan

Remove-Item -Recurse -Force $Root

foreach ($dir in @([Environment]::GetFolderPath('Desktop'),
                   (Join-Path $env:APPDATA 'Microsoft\Windows\Start Menu\Programs'))) {
    Remove-Item -Force (Join-Path $dir "$AppName.lnk")
}

foreach ($path in @('HKCU:\Software\Policies\Google\Chrome\ExtensionInstallAllowlist',
                    'HKCU:\Software\Policies\Microsoft\Edge\ExtensionInstallAllowlist')) {
    Remove-ItemProperty -Path $path -Name '1'
}

Write-Host '  Дууслаа. Хөтчийн өргөтгөлийн хуудаснаас мөн хасна уу.' -ForegroundColor Green
Write-Host ''
Read-Host '  Хаахын тулд Enter дарна уу'
