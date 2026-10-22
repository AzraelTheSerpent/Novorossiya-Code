@echo off
cd /d "%~dp0"
set PORT=%~1
if "%PORT%"=="" set PORT=8000
echo.
echo Keep start.cmd running in another window.
echo Waiting for the phone address...
echo.
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0phone.ps1" -Port %PORT%
pause
