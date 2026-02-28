@echo off
setlocal EnableDelayedExpansion

REM Get Current Directory
set "APP_DIR=%~dp0"
set "BIN_MYSQL=%APP_DIR%bin\mysql\bin\mysql.exe"
set "DB_PORT=3307"

REM Check for mysql client
if not exist "%BIN_MYSQL%" (
    echo Error: MySQL client binary not found at %BIN_MYSQL%
    echo Please make sure you have downloaded and placed MySQL in bin\mysql\
    pause
    exit /b 1
)

echo.
echo ====================================================
echo   Smart Shop - Database Console
echo ====================================================
echo.
echo   Connecting to Smart Shop database...
echo   Host: localhost
echo   Port: %DB_PORT%
echo   User: root
echo.

"%BIN_MYSQL%" -h 127.0.0.1 -P %DB_PORT% -u root smart_shop

if errorlevel 1 (
    echo.
    echo Error: Could not connect to the database.
    echo Make sure SmartShop.bat is running!
    pause
)
