@echo off
setlocal EnableDelayedExpansion

REM ---------------------------------------------------------------------------
REM Smart Shop - C# Launcher Compiler Script
REM ---------------------------------------------------------------------------

echo Searching for C# Compiler (csc.exe)...

REM 1. Check Framework64 (x64)
set "CSC=%WINDIR%\Microsoft.NET\Framework64\v4.0.30319\csc.exe"
if exist "%CSC%" goto :FOUND_CSC

REM 2. Check Framework (x86)
set "CSC=%WINDIR%\Microsoft.NET\Framework\v4.0.30319\csc.exe"
if exist "%CSC%" goto :FOUND_CSC

REM 3. Not Found
goto :ERROR_CSC

:FOUND_CSC
echo Found compiler: %CSC%
echo.
echo Compiling SmartShopLauncher...
echo --------------------------------------------------------

REM Check if source file exists
if not exist "SmartShopLauncher.cs" (
    echo Error: Source file 'SmartShopLauncher.cs' not found in current directory.
    pause
    exit /b 1
)

REM Check Icon Path
set "ICON_PATH=..\src\img\favicon.ico"
if exist "www\src\img\favicon.ico" set "ICON_PATH=www\src\img\favicon.ico"
if exist "favicon.ico" set "ICON_PATH=favicon.ico"

echo using icon: %ICON_PATH%

REM Build Command
if exist "%ICON_PATH%" (
    "%CSC%" /target:winexe /out:SmartShopLauncher.exe /win32icon:"%ICON_PATH%" SmartShopLauncher.cs
) else (
    echo Warning: Icon not found, building without icon.
    "%CSC%" /target:winexe /out:SmartShopLauncher.exe SmartShopLauncher.cs
)

if errorlevel 1 (
    echo.
    echo ====================================================
    echo ERROR: Compilation Failed!
    echo ====================================================
    pause
    exit /b 1
)

echo.
echo ====================================================
echo SUCCESS: SmartShopLauncher.exe created!
echo ====================================================
echo.

REM Copy to dist root if we are in dist/launcher? No, script is in dist root.
REM If script is run from inside 'launcher' folder (dev):
if exist "..\dist" (
    copy SmartShopLauncher.exe ..\dist\SmartShopLauncher.exe > nul
    echo Copied to dist directory.
)

pause
exit /b 0

:ERROR_CSC
echo.
echo ====================================================
echo ERROR: C# Compiler not found!
echo ====================================================
echo.
echo Could not find 'csc.exe' in Microsoft.NET Framework folders.
echo Please ensure .NET Framework 4.5 or later is installed.
echo.
pause
exit /b 1
