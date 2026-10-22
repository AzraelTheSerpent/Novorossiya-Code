@echo off
cd /d "%~dp0"
set PORT=%~1
if "%PORT%"=="" set PORT=8000
echo Starting Kod Novorossii on http://127.0.0.1:%PORT%
echo Press Ctrl+C to stop.
echo.
.\runtime\php\php.exe artisan serve --no-reload --port=%PORT%
pause
