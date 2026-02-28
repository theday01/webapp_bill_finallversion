@echo off
setlocal

REM Check if portable PHP exists
if exist "bin\php\php.exe" (
    echo Using portable PHP from bin\php\php.exe
    "bin\php\php.exe" tools/build.php
) else (
    echo Portable PHP not found in bin\php.
    echo Trying global 'php' command...
    php tools/build.php
)

if errorlevel 1 (
    echo.
    echo Build failed! Please make sure PHP is installed or placed in bin\php\
)

pause
