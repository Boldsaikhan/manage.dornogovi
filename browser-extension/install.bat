@echo off
chcp 65001 >nul
title Manage Dornogovi — өргөтгөл суулгах
cd /d "%~dp0"

echo.
echo  ========================================
echo   Manage Dornogovi өргөтгөл суулгах
echo  ========================================
echo.
echo  1) Chrome эсвэл Edge нээгдэнэ (extensions хуудас).
echo  2) Developer mode / Хөгжүүлэгчийн горим асаана.
echo  3) Load unpacked / Задгай ачаалах дарна.
echo  4) ОДООГИЙН хавтсыг сонгоно:
echo.
echo     %CD%
echo.
echo  ========================================
echo.

REM Chrome
where chrome >nul 2>&1
if %ERRORLEVEL%==0 (
  start "" chrome "chrome://extensions"
  goto :done
)

if exist "%ProgramFiles%\Google\Chrome\Application\chrome.exe" (
  start "" "%ProgramFiles%\Google\Chrome\Application\chrome.exe" "chrome://extensions"
  goto :done
)

if exist "%ProgramFiles(x86)%\Google\Chrome\Application\chrome.exe" (
  start "" "%ProgramFiles(x86)%\Google\Chrome\Application\chrome.exe" "chrome://extensions"
  goto :done
)

REM Edge
if exist "%ProgramFiles(x86)%\Microsoft\Edge\Application\msedge.exe" (
  start "" "%ProgramFiles(x86)%\Microsoft\Edge\Application\msedge.exe" "edge://extensions"
  goto :done
)

if exist "%ProgramFiles%\Microsoft\Edge\Application\msedge.exe" (
  start "" "%ProgramFiles%\Microsoft\Edge\Application\msedge.exe" "edge://extensions"
  goto :done
)

echo  Chrome/Edge олдсонгүй. Гараар chrome://extensions нээнэ үү.
echo.

:done
echo  СУУЛГАХ.txt файлыг мөн уншина уу.
echo.
pause
