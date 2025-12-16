<?php
/**
 * Excel File Analyzer for Citation Data Import
 * Analyzes structure and identifies data inconsistencies
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

$excelFile = __DIR__ . '/NEW 2023-2024 CITATION TICKET  (Responses) (24) (1).xlsx';

if (!file_exists($excelFile)) {
    die("ERROR: Excel file not found at: $excelFile\n");
}

echo "=======================================================\n";
echo "EXCEL FILE ANALYSIS - CITATION TICKET DATA\n";
echo "=======================================================\n";
echo "File: " . basename($excelFile) . "\n";
echo "Size: " . number_format(filesize($excelFile)) . " bytes\n";
echo "=======================================================\n\n";

// Try to use SimpleXLSX library (lightweight, no dependencies)
// First check if it exists in vendor
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// Try using SimpleXLSX if available
class ExcelAnalyzer {
    private $file;
    private $data = [];
    private $headers = [];
    private $issues = [];

    public function __construct($file) {
        $this->file = $file;
    }

    public function analyze() {
        // Try to extract using ZIP (xlsx is a zip file)
        $zip = new ZipArchive();
        if ($zip->open($this->file) === TRUE) {
            echo "✓ Successfully opened Excel file as ZIP\n\n";

            // List all files in the Excel package
            echo "Excel Package Contents:\n";
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $filename = $zip->getNameIndex($i);
                echo "  - $filename\n";
            }
            echo "\n";

            // Extract shared strings (text content)
            $sharedStrings = $zip->getFromName('xl/sharedStrings.xml');
            if ($sharedStrings) {
                $this->extractSharedStrings($sharedStrings);
            }

            // Extract worksheet data
            $worksheet = $zip->getFromName('xl/worksheets/sheet1.xml');
            if ($worksheet) {
                $this->extractWorksheetData($worksheet);
            }

            // Extract workbook info
            $workbook = $zip->getFromName('xl/workbook.xml');
            if ($workbook) {
                $this->extractWorkbookInfo($workbook);
            }

            $zip->close();

            // Analyze the extracted data
            $this->analyzeData();

        } else {
            echo "✗ Failed to open Excel file\n";
            return false;
        }

        return true;
    }

    private function extractSharedStrings($xml) {
        echo "Extracting shared strings...\n";
        $doc = new DOMDocument();
        @$doc->loadXML($xml);
        $strings = $doc->getElementsByTagName('t');

        echo "Found " . $strings->length . " shared strings\n";

        // Show first 50 strings as sample
        echo "\nSample Shared Strings (first 50):\n";
        echo str_repeat("-", 80) . "\n";
        for ($i = 0; $i < min(50, $strings->length); $i++) {
            $value = $strings->item($i)->nodeValue;
            echo sprintf("[%3d] %s\n", $i, $value);
        }
        echo str_repeat("-", 80) . "\n\n";
    }

    private function extractWorksheetData($xml) {
        echo "Extracting worksheet data...\n";
        $doc = new DOMDocument();
        @$doc->loadXML($xml);

        // Get dimension
        $dimension = $doc->getElementsByTagName('dimension');
        if ($dimension->length > 0) {
            $ref = $dimension->item(0)->getAttribute('ref');
            echo "Worksheet dimension: $ref\n";
        }

        // Count rows
        $rows = $doc->getElementsByTagName('row');
        echo "Total rows in worksheet: " . $rows->length . "\n\n";

        // Show row structure
        if ($rows->length > 0) {
            echo "First 5 rows structure:\n";
            echo str_repeat("-", 80) . "\n";
            for ($i = 0; $i < min(5, $rows->length); $i++) {
                $row = $rows->item($i);
                $rowNum = $row->getAttribute('r');
                $cells = $row->getElementsByTagName('c');
                echo "Row $rowNum: " . $cells->length . " cells\n";

                // Show cell references
                for ($j = 0; $j < $cells->length; $j++) {
                    $cell = $cells->item($j);
                    $cellRef = $cell->getAttribute('r');
                    $cellType = $cell->getAttribute('t');
                    $value = $cell->getElementsByTagName('v');
                    if ($value->length > 0) {
                        $val = $value->item(0)->nodeValue;
                        echo "  $cellRef (type: $cellType) = $val\n";
                    }
                }
            }
            echo str_repeat("-", 80) . "\n\n";
        }
    }

    private function extractWorkbookInfo($xml) {
        echo "Extracting workbook info...\n";
        $doc = new DOMDocument();
        @$doc->loadXML($xml);

        // Get sheet names
        $sheets = $doc->getElementsByTagName('sheet');
        echo "Sheets in workbook: " . $sheets->length . "\n";
        for ($i = 0; $i < $sheets->length; $i++) {
            $sheet = $sheets->item($i);
            $name = $sheet->getAttribute('name');
            $sheetId = $sheet->getAttribute('sheetId');
            echo "  Sheet $sheetId: $name\n";
        }
        echo "\n";
    }

    private function analyzeData() {
        echo "=======================================================\n";
        echo "DATA ANALYSIS SUMMARY\n";
        echo "=======================================================\n";
        echo "This is a preliminary analysis of the Excel file structure.\n";
        echo "For detailed data analysis, we need to parse the cell values.\n\n";
    }
}

// Run the analysis
$analyzer = new ExcelAnalyzer($excelFile);
$analyzer->analyze();

echo "\n=======================================================\n";
echo "NEXT STEPS\n";
echo "=======================================================\n";
echo "1. Install a PHP Excel library for detailed parsing\n";
echo "2. Extract all cell values with proper type conversion\n";
echo "3. Analyze data inconsistencies\n";
echo "4. Map columns to database schema\n";
echo "5. Generate import script\n";
echo "=======================================================\n";
