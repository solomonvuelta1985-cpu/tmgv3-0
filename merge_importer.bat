@echo off
echo ========================================
echo Merging ExcelImporter Files
echo ========================================

cd /d c:\xampp\htdocs\tmg\services

if not exist ExcelImporter.php (
    echo ERROR: ExcelImporter.php not found!
    pause
    exit /b 1
)

if not exist ExcelImporterPart2.php (
    echo ERROR: ExcelImporterPart2.php not found!
    pause
    exit /b 1
)

echo Creating backup...
copy ExcelImporter.php ExcelImporter.php.backup

echo Merging files...
type ExcelImporter.php > ExcelImporterTemp.txt
echo. >> ExcelImporterTemp.txt
type ExcelImporterPart2.php >> ExcelImporterTemp.txt

move /y ExcelImporterTemp.txt ExcelImporter.php

echo.
echo ========================================
echo Merge Complete!
echo ========================================
echo.
echo Backup saved as: ExcelImporter.php.backup
echo.
pause
