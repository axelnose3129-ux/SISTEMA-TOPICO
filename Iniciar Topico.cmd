@echo off
cd /d "%~dp0"
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0iniciar-topico.ps1"
if errorlevel 1 (
    echo.
    echo No se pudo iniciar Topico. Revisa el mensaje anterior.
    pause
)
