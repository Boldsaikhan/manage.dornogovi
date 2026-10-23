# Manage Dornogovi — Windows суулгац
#
# Администраторын эрх шаардахгүй. Дараах зүйлийг хийнэ:
#   1. Өргөтгөлийг байнгын хавтас руу хуулна.
#   2. Chrome / Edge-д өргөтгөлийг бүртгэнэ (боломжтой бол автоматаар).
#   3. Ширээний компьютер дээр «Manage Dornogovi» товчлол үүсгэнэ —
#      дарахад систем програм хэлбэрээр нээгдэнэ.

$ErrorActionPreference = 'Stop'

$AppName  = 'Manage Dornogovi'
$SiteUrl  = 'https://manage.dornogovi.gov.mn'
$Root     = Join-Path $env:LOCALAPPDATA 'ManageDornogovi'
$ExtDir   = Join-Path $Root 'extension'
$Source   = Join-Path $PSScriptRoot 'extension'

function Write-Step($text) {
    Write-Host ''
    Write-Host "  $text" -ForegroundColor Cyan
}

Write-Host ''
Write-Host '  ===========================================' -ForegroundColor DarkCyan
Write-Host "   $AppName — суулгаж байна" -ForegroundColor White
Write-Host '  ===========================================' -ForegroundColor DarkCyan

# 1. Өргөтгөлийг хуулна -------------------------------------------------
Write-Step '1/3 Өргөтгөлийн файлуудыг хуулж байна…'

if (-not (Test-Path $Source)) {
    Write-Host '  ! extension хавтас олдсонгүй. ZIP-ээ бүтнээр нь задалсан эсэхээ шалгана уу.' -ForegroundColor Red
    Read-Host '  Гарахын тулд Enter дарна уу'
    exit 1
}

New-Item -ItemType Directory -Force -Path $Root | Out-Null

if (Test-Path $ExtDir) {
    Remove-Item -Recurse -Force $ExtDir
}

Copy-Item -Recurse -Force $Source $ExtDir
Write-Host "    Хуулсан: $ExtDir" -ForegroundColor DarkGray

# 2. Хөтөч дээр бүртгэнэ -----------------------------------------------
Write-Step '2/3 Хөтөч дээр бүртгэж байна…'

# Өргөтгөлийн ID нь manifest.json доторх «key»-ээс тогтмол гарна.
$ExtensionId = 'hbdkjcnamgbnbdcoaloepnbhcbnjbepe'

$policyPaths = @(
    'HKCU:\Software\Policies\Google\Chrome\ExtensionInstallAllowlist',
    'HKCU:\Software\Policies\Microsoft\Edge\ExtensionInstallAllowlist'
)

foreach ($path in $policyPaths) {
    try {
        New-Item -Path $path -Force | Out-Null
        New-ItemProperty -Path $path -Name '1' -Value $ExtensionId -PropertyType String -Force | Out-Null
    } catch {
        # Бодлогын түлхүүр бичигдэхгүй бол гараар ачаална — доор зааварчилна.
    }
}

Write-Host '    Зөвшөөрөгдсөн өргөтгөлийн жагсаалтад нэмэв.' -ForegroundColor DarkGray

# 3. Товчлол үүсгэнэ ----------------------------------------------------
Write-Step '3/3 Ширээний товчлол үүсгэж байна…'

function Find-Browser {
    $candidates = @(
        "$env:ProgramFiles\Google\Chrome\Application\chrome.exe",
        "${env:ProgramFiles(x86)}\Google\Chrome\Application\chrome.exe",
        "$env:LOCALAPPDATA\Google\Chrome\Application\chrome.exe",
        "$env:ProgramFiles\Microsoft\Edge\Application\msedge.exe",
        "${env:ProgramFiles(x86)}\Microsoft\Edge\Application\msedge.exe"
    )

    foreach ($path in $candidates) {
        if (Test-Path $path) { return $path }
    }

    return $null
}

$browser = Find-Browser

if ($null -eq $browser) {
    Write-Host '  ! Chrome эсвэл Edge олдсонгүй. Эхлээд хөтчөө суулгана уу.' -ForegroundColor Red
    Read-Host '  Гарахын тулд Enter дарна уу'
    exit 1
}

$shell = New-Object -ComObject WScript.Shell
$iconPath = Join-Path $ExtDir 'icons\icon-128.png'

foreach ($dir in @([Environment]::GetFolderPath('Desktop'),
                   (Join-Path $env:APPDATA 'Microsoft\Windows\Start Menu\Programs'))) {
    $link = $shell.CreateShortcut((Join-Path $dir "$AppName.lnk"))
    $link.TargetPath = $browser
    # Програм хэлбэрээр нээнэ — хаягийн мөр, табгүй.
    $link.Arguments = "--app=$SiteUrl"
    $link.WorkingDirectory = Split-Path $browser
    $link.Description = 'Дорноговь аймгийн дотоод систем'
    $link.IconLocation = "$browser,0"
    $link.Save()
}

Write-Host '    Ширээний компьютер болон Эхлүүлэх цэсэнд нэмэв.' -ForegroundColor DarkGray

# Дуусав ----------------------------------------------------------------
Write-Host ''
Write-Host '  ===========================================' -ForegroundColor DarkCyan
Write-Host '   Суулгалт дууслаа' -ForegroundColor Green
Write-Host '  ===========================================' -ForegroundColor DarkCyan
Write-Host ''
Write-Host '  Өргөтгөлийг нэг удаа идэвхжүүлнэ:' -ForegroundColor White
Write-Host '    1. Нээгдэх хуудсан дээр «Developer mode» асаана.'
Write-Host '    2. «Load unpacked» дарна.'
Write-Host '    3. Дараах хавтсыг сонгоно:'
Write-Host "       $ExtDir" -ForegroundColor Yellow
Write-Host ''
Write-Host '  Үүний дараа ширээний «Manage Dornogovi» товчлолоор ороход'
Write-Host '  холбосон системүүд рүү автоматаар нэвтэрнэ.' -ForegroundColor White
Write-Host ''

$extPage = if ($browser -like '*msedge.exe') { 'edge://extensions' } else { 'chrome://extensions' }
Start-Process $browser $extPage

Read-Host '  Хаахын тулд Enter дарна уу'
