@echo off
setlocal EnableDelayedExpansion

echo ----------------------------------------------------
echo Smart Shop - Binary Cleaner
echo Reduces size of PHP and MySQL binaries for distribution
echo ----------------------------------------------------

REM Get the directory of this script (tools/)
set "SCRIPT_DIR=%~dp0"
REM Go up one level to project root
set "PROJECT_ROOT=%SCRIPT_DIR%.."
set "DIST_DIR=%PROJECT_ROOT%\dist"

echo Looking for dist folder at:
echo "%DIST_DIR%"
echo.

if not exist "%DIST_DIR%" (
    echo Error: 'dist' directory not found at:
    echo "%DIST_DIR%"
    echo.
    echo Please run 'build_app.bat' first to create the distribution folder.
    pause
    exit /b 1
)

if not exist "%DIST_DIR%\bin" (
    echo Error: 'bin' directory not found inside 'dist'.
    echo Expected path: "%DIST_DIR%\bin"
    echo.
    echo Please copy your 'php' and 'mysql' folders into the 'dist\bin' folder manually.
    echo Structure should be:
    echo   dist\
    echo     bin\
    echo       php\
    echo       mysql\
    pause
    exit /b 1
)

echo Cleaning PHP...
set "PHP_DIR=%DIST_DIR%\bin\php"
if exist "%PHP_DIR%" (
    pushd "%PHP_DIR%"
    if exist "docs" rmdir /s /q "docs"
    if exist "dev" rmdir /s /q "dev"
    if exist "tests" rmdir /s /q "tests"
    if exist "extras" rmdir /s /q "extras"
    if exist "phpdbg.exe" del /q "phpdbg.exe"
    if exist "php.ini-development" del /q "php.ini-development"
    if exist "php.ini-production" del /q "php.ini-production"
    popd
    echo [OK] PHP Cleaned.
) else (
    echo [Skip] PHP folder not found at: %PHP_DIR%
)

echo Cleaning MySQL...
set "MYSQL_DIR=%DIST_DIR%\bin\mysql"
if exist "%MYSQL_DIR%" (
    pushd "%MYSQL_DIR%"
    if exist "docs" rmdir /s /q "docs"
    if exist "include" rmdir /s /q "include"
    if exist "test" rmdir /s /q "test"
    if exist "lib" (
        del /q "lib\*.lib" 2>nul
        del /q "lib\*.pdb" 2>nul
    )
    if exist "bin" (
        pushd "bin"
        del /q "*.pdb" 2>nul
        del /q "*.map" 2>nul
        if exist "mysqld-debug.exe" del /q "mysqld-debug.exe"
        if exist "mysqltest.exe" del /q "mysqltest.exe"
        if exist "mysql_config_editor.exe" del /q "mysql_config_editor.exe"
        if exist "mysql_secure_installation.exe" del /q "mysql_secure_installation.exe"
        popd
    )
    popd
    echo [OK] MySQL Cleaned.
) else (
    echo [Skip] MySQL folder not found at: %MYSQL_DIR%
)

echo.
echo Cleaning complete!
echo You can now compress the 'dist' folder.
pause
