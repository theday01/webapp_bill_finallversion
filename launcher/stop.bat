@echo off
echo Stopping SmartShop...
taskkill /F /IM php.exe > nul 2>&1
taskkill /F /IM mysqld.exe > nul 2>&1
echo Done.
pause
