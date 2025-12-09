@echo off
REM =====================================================
REM TMG - Backup Task Scheduler Setup Script
REM =====================================================
REM This script creates a Windows Task Scheduler task
REM to run automatic database backups.
REM
REM REQUIREMENTS:
REM - Run as Administrator
REM - XAMPP installed at C:\xampp
REM =====================================================

echo.
echo ========================================
echo TMG Backup Task Scheduler Setup
echo ========================================
echo.

REM Check if running as administrator
net session >nul 2>&1
if %errorLevel% neq 0 (
    echo ERROR: This script must be run as Administrator!
    echo Right-click and select "Run as administrator"
    echo.
    pause
    exit /b 1
)

echo [1/5] Checking PHP installation...
if not exist "C:\xampp\php\php.exe" (
    echo ERROR: PHP not found at C:\xampp\php\php.exe
    echo Please install XAMPP or update the path in this script.
    echo.
    pause
    exit /b 1
)
echo      PHP found: C:\xampp\php\php.exe

echo.
echo [2/5] Checking backup scheduler script...
if not exist "%~dp0backup_scheduler.php" (
    echo ERROR: backup_scheduler.php not found!
    echo Expected location: %~dp0backup_scheduler.php
    echo.
    pause
    exit /b 1
)
echo      Script found: %~dp0backup_scheduler.php

echo.
echo [3/5] Testing backup scheduler...
cd /d "%~dp0"
"C:\xampp\php\php.exe" backup_scheduler.php
if %errorLevel% neq 0 (
    echo ERROR: Backup scheduler test failed!
    echo Please check the error messages above.
    echo.
    pause
    exit /b 1
)

echo.
echo [4/5] Creating Windows scheduled task...
schtasks /create /tn "TMG Database Backup" /tr "\"C:\xampp\php\php.exe\" \"%~dp0backup_scheduler.php\"" /sc DAILY /st 02:00 /ru SYSTEM /rl HIGHEST /f

if %errorLevel% neq 0 (
    echo ERROR: Failed to create scheduled task!
    echo Please create the task manually using Task Scheduler.
    echo.
    pause
    exit /b 1
)

echo.
echo [5/5] Verifying task creation...
schtasks /query /tn "TMG Database Backup" >nul 2>&1
if %errorLevel% neq 0 (
    echo ERROR: Task was not created successfully!
    echo.
    pause
    exit /b 1
)

echo.
echo ========================================
echo SUCCESS! Backup task created.
echo ========================================
echo.
echo Task Name: TMG Database Backup
echo Schedule:  Daily at 2:00 AM
echo Status:    Enabled
echo.
echo NOTE: The scheduler will check if backup is due based
echo       on your configured frequency in the web interface.
echo.
echo NEXT STEPS:
echo 1. Configure backup settings at:
echo    http://localhost/tmg/admin/backup_settings.php
echo.
echo 2. Enable automatic backups
echo.
echo 3. Set your desired frequency (daily/weekly/monthly)
echo.
echo To test the task manually:
echo   schtasks /run /tn "TMG Database Backup"
echo.
echo To view task details:
echo   Task Scheduler -^> TMG Database Backup
echo.
pause
