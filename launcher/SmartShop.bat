@echo off
setlocal EnableDelayedExpansion

REM Get Current Directory
set "APP_DIR=%~dp0"
set "BIN_PHP=%APP_DIR%bin\php\php.exe"
set "BIN_MYSQL=%APP_DIR%bin\mysql\bin\mysqld.exe"
set "BIN_MYSQL_ADMIN=%APP_DIR%bin\mysql\bin\mysqladmin.exe"
set "DATA_DIR=%APPDATA%\SmartShop\data"

REM -------------------------------------------------------------------------
REM Directory Detection Logic (Using GOTO to avoid nested IF issues)
REM -------------------------------------------------------------------------

REM 1. Check for Production (dist/www)
set "WWW_DIR=%APP_DIR%www"
if exist "%WWW_DIR%\login.php" goto :FOUND_PROD

REM 2. Check for Development (Source Root)
if exist "%APP_DIR%..\login.php" goto :FOUND_DEV

REM 3. Not Found
goto :ERROR_NOT_FOUND

:FOUND_PROD
echo [Check] Found application in: %WWW_DIR% (Production)
goto :START_APP

:FOUND_DEV
set "WWW_DIR=%APP_DIR%.."
echo [Check] Found application in: %WWW_DIR% (Development)
goto :START_APP

:ERROR_NOT_FOUND
echo.
echo ====================================================
echo Error: Could not find application files!
echo ====================================================
echo.
echo Expected to find 'login.php' in:
echo 1. %APP_DIR%www (Production Build)
echo 2. %APP_DIR%.. (Source Code)
echo.
echo ----------------------------------------------------
echo Troubleshooting:
echo 1. Did you run 'build_app.bat' first?
echo 2. Did you select the files INSIDE the 'dist' folder?
echo    (Do NOT archive the 'launcher' folder directly!)
echo 3. Does the 'www' folder exist next to this file?
echo ----------------------------------------------------
echo.
echo Current Directory Contents:
dir /b "%APP_DIR%"
echo.
pause
exit /b 1

:START_APP
REM -------------------------------------------------------------------------
REM Application Startup
REM -------------------------------------------------------------------------

REM Start Launcher (C#) if available - Preferred Method
if exist "%APP_DIR%SmartShopLauncher.exe" (
    echo Starting GUI Launcher...
    start "" "%APP_DIR%SmartShopLauncher.exe"
    exit
)

set "DB_PORT=3307"
set "PHP_PORT=8000"

REM Set Environment Variable for PHP
set DB_PORT=%DB_PORT%

REM Check for binaries
if not exist "%BIN_PHP%" (
    echo Error: PHP binary not found at %BIN_PHP%
    echo Please make sure you have downloaded and placed PHP in bin\php\
    pause
    exit /b 1
)
if not exist "%BIN_MYSQL%" (
    echo Error: MySQL binary not found at %BIN_MYSQL%
    echo Please make sure you have downloaded and placed MySQL in bin\mysql\
    pause
    exit /b 1
)

REM Check if Data Directory Exists
if not exist "%DATA_DIR%" (
    echo Initializing Database in %DATA_DIR%...
    if not exist "%APPDATA%\SmartShop" mkdir "%APPDATA%\SmartShop"
    if not exist "%DATA_DIR%" mkdir "%DATA_DIR%"
    
    "%BIN_MYSQL%" --initialize-insecure --datadir="%DATA_DIR%" --console
    if errorlevel 1 (
        echo Error initializing database.
        pause
        exit /b 1
    )
)

REM -------------------------------------------------------------------------
REM Firewall Configuration (Optional)
REM Try to add firewall rules if running as Admin to prevent blocking
REM -------------------------------------------------------------------------
netsh advfirewall firewall show rule name="SmartShop MySQL" >nul
if errorlevel 1 (
    echo Configuring Firewall...
    netsh advfirewall firewall add rule name="SmartShop MySQL" dir=in action=allow program="%BIN_MYSQL%" enable=yes profile=private,domain >nul 2>&1
    netsh advfirewall firewall add rule name="SmartShop PHP" dir=in action=allow program="%BIN_PHP%" enable=yes profile=private,domain >nul 2>&1
    netsh advfirewall firewall add rule name="SmartShop Web" dir=in action=allow protocol=TCP localport=%PHP_PORT% >nul 2>&1
    netsh advfirewall firewall add rule name="SmartShop DB" dir=in action=allow protocol=TCP localport=%DB_PORT% >nul 2>&1
)

echo.
echo ====================================================
echo   Starting Smart Shop Portable...
echo ====================================================
echo.

REM Start MySQL Server
echo [1/3] Starting Database Server (Port %DB_PORT%)...

REM Clear previous logs
if exist "%APP_DIR%mysql_debug.log" del "%APP_DIR%mysql_debug.log"

REM Start MySQL (Localhost Only) and log output
start /B "" "%BIN_MYSQL%" --bind-address=127.0.0.1 --port=%DB_PORT% --datadir="%DATA_DIR%" --console > "%APP_DIR%mysql_debug.log" 2>&1

REM Wait for MySQL to be ready (Ping Loop)
echo Waiting for Database to initialize...
set "MYSQL_READY=0"

:PING_LOOP
for /L %%i in (1,1,30) do (
    "%BIN_MYSQL_ADMIN%" -u root --port=%DB_PORT% ping > nul 2>&1
    if not errorlevel 1 (
        set "MYSQL_READY=1"
        goto :MYSQL_IS_UP
    )
    timeout /t 1 /nobreak > nul
)

:MYSQL_IS_UP
if "%MYSQL_READY%"=="0" (
    echo.
    echo ====================================================
    echo ERROR: MySQL Server failed to start within 30 seconds!
    echo ====================================================
    echo.
    echo Most likely cause: Missing 'Visual C++ Redistributable'
    echo Please install 'VC_redist.x64.exe' from Microsoft website.
    echo.
    echo Error Log Details:
    echo ----------------------------------------------------
    if exist "%APP_DIR%mysql_debug.log" type "%APP_DIR%mysql_debug.log"
    echo ----------------------------------------------------
    echo.
    pause
    exit /b 1
)

echo Database is ready!

REM Create Portable Config for PHP (No closing tag to prevent whitespace output)
echo ^<?php $PORTABLE_DB_PORT = %DB_PORT%; > "%WWW_DIR%\portable_config.php"

REM Start PHP Server (Background)
echo [2/3] Starting Web Server (Port %PHP_PORT%)...

REM Configure PHP Runtime options (Extensions and Limits)
REM Using relative path for extension_dir to avoid syntax errors with spaces/tildes in absolute paths
if exist "%APP_DIR%bin\php\php.exe" (
    set "EXT_DIR=bin\php\ext"
) else (
    REM Fallback for source/dev environment
    set "EXT_DIR=..\bin\php\ext"
)

set "PHP_OPTS=-d extension_dir="%EXT_DIR%" -d extension=mysqli -d extension=mbstring -d extension=gd -d extension=curl -d upload_max_filesize=64M -d post_max_size=64M -d memory_limit=256M"

REM Start PHP on 127.0.0.1 explicitly to avoid IPv6 issues
start /B "" "%BIN_PHP%" %PHP_OPTS% -S 127.0.0.1:%PHP_PORT% -t "%WWW_DIR%"

REM Wait for PHP to start
timeout /t 2 /nobreak > nul

REM Open Browser
echo [3/3] Opening Application...
start "" "http://localhost:%PHP_PORT%"

echo.
echo ====================================================
echo   Smart Shop is Running!
echo   - Web URL: http://localhost:%PHP_PORT%
echo   - Database Port: %DB_PORT%
echo.
echo   ^>^> KEEP THIS WINDOW OPEN ^<^<
echo.
echo   Press any key to STOP the server and EXIT.
echo ====================================================
echo.

pause

REM Cleanup when user presses a key
echo.
echo Stopping services...
"%BIN_MYSQL_ADMIN%" -u root --port=%DB_PORT% shutdown > nul 2>&1
taskkill /F /IM php.exe > nul 2>&1
echo Done.

exit
