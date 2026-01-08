<?php
/**
 * PDF Configuration for Payment Receipts
 *
 * This file contains all configuration settings for PDF generation
 * using Dompdf library for official receipts
 *
 * @package TrafficCitationSystem
 * @subpackage Payment
 */

// Load Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// ============================================================================
// PDF GENERATION SETTINGS
// ============================================================================

// Paper size and orientation
define('PDF_PAPER_SIZE', 'letter'); // letter, A4, legal
define('PDF_ORIENTATION', 'portrait'); // portrait, landscape

// Font settings
define('PDF_FONT_NAME', 'helvetica');
define('PDF_FONT_SIZE', 10);
define('PDF_FONT_MONOSPACE', 'courier');

// Margins (in points)
define('PDF_MARGIN_TOP', 10);
define('PDF_MARGIN_BOTTOM', 10);
define('PDF_MARGIN_LEFT', 15);
define('PDF_MARGIN_RIGHT', 15);

// DPI settings
define('PDF_DPI', 96);
define('PDF_IMAGE_DPI', 150);

// ============================================================================
// RECEIPT SETTINGS
// ============================================================================

// Organization/LGU Information
define('RECEIPT_LGU_NAME', 'Municipality of Baggao');
define('RECEIPT_LGU_PROVINCE', 'Province of Cagayan');
define('RECEIPT_LGU_ADDRESS', 'Baggao, Cagayan, Philippines');
define('RECEIPT_LGU_CONTACT', 'Tel: (078) 844-1234');
define('RECEIPT_LGU_EMAIL', 'info@baggao.gov.ph');
define('RECEIPT_LGU_WEBSITE', 'www.baggao.gov.ph');

// Logo settings
define('RECEIPT_LOGO_PATH', __DIR__ . '/../assets/images/logo.png');
define('RECEIPT_LOGO_WIDTH', 80); // pixels
define('RECEIPT_LOGO_HEIGHT', 80); // pixels
define('RECEIPT_SHOW_LOGO', file_exists(RECEIPT_LOGO_PATH)); // Auto-detect if logo exists

// Receipt copies
define('RECEIPT_COPIES', 3); // Original, Duplicate, Triplicate
define('RECEIPT_COPY_NAMES', [
    1 => 'ORIGINAL COPY',
    2 => 'DUPLICATE COPY',
    3 => 'TRIPLICATE COPY'
]);

// Copy colors (for visual distinction)
define('RECEIPT_COPY_COLORS', [
    1 => '#000000', // Black for original
    2 => '#FFA500', // Orange for duplicate
    3 => '#FF1493'  // Pink for triplicate
]);

// ============================================================================
// OR NUMBER SETTINGS
// ============================================================================

// OR number format: OR-YYYY-NNNNNN
define('OR_NUMBER_PREFIX', 'OR-');
define('OR_NUMBER_YEAR_FORMAT', 'Y'); // Y for 4-digit year, y for 2-digit
define('OR_NUMBER_PADDING', 6); // Number of digits (zero-padded)
define('OR_NUMBER_SEPARATOR', '-');

// Example: OR-2025-000001

// ============================================================================
// RECEIPT APPEARANCE
// ============================================================================

// Colors
define('RECEIPT_PRIMARY_COLOR', '#0d6efd'); // Bootstrap primary blue
define('RECEIPT_BORDER_COLOR', '#dee2e6');
define('RECEIPT_HEADER_BG', '#f8f9fa');
define('RECEIPT_TEXT_COLOR', '#212529');

// Watermark settings
define('RECEIPT_SHOW_WATERMARK', true);
define('RECEIPT_WATERMARK_TEXT', 'OFFICIAL RECEIPT');
define('RECEIPT_WATERMARK_OPACITY', 0.1);

// Footer disclaimer
define('RECEIPT_DISCLAIMER', 'This is an official receipt. Keep for your records. For verification, contact the municipality.');

// ============================================================================
// QR CODE SETTINGS
// ============================================================================

define('RECEIPT_SHOW_QR_CODE', true);
define('RECEIPT_QR_CODE_SIZE', 100); // pixels
define('RECEIPT_QR_CODE_VERIFY_URL', 'https://btracs.online/tmg/public/verify_receipt.php?or=');

// ============================================================================
// SECURITY SETTINGS
// ============================================================================

// Enable security features
define('PDF_ENABLE_REMOTE', false); // Disable remote file access for security
define('PDF_ENABLE_JAVASCRIPT', false);
define('PDF_ENABLE_HTML5_PARSER', true);

// ============================================================================
// PERFORMANCE SETTINGS
// ============================================================================

// Caching
define('PDF_ENABLE_CACHE', true);
define('PDF_CACHE_DIR', __DIR__ . '/../cache/pdf/');
define('PDF_FONT_CACHE', __DIR__ . '/../cache/fonts/');

// Create cache directories if they don't exist
if (PDF_ENABLE_CACHE) {
    if (!is_dir(PDF_CACHE_DIR)) {
        mkdir(PDF_CACHE_DIR, 0755, true);
    }
    if (!is_dir(PDF_FONT_CACHE)) {
        mkdir(PDF_FONT_CACHE, 0755, true);
    }
}

// ============================================================================
// DOMPDF OPTIONS
// ============================================================================

/**
 * Get Dompdf options array
 *
 * @return array Dompdf configuration options
 */
function getDompdfOptions() {
    return [
        'isRemoteEnabled' => PDF_ENABLE_REMOTE,
        'isJavascriptEnabled' => PDF_ENABLE_JAVASCRIPT,
        'isHtml5ParserEnabled' => PDF_ENABLE_HTML5_PARSER,
        'isFontSubsettingEnabled' => true,
        'defaultFont' => PDF_FONT_NAME,
        'defaultPaperSize' => PDF_PAPER_SIZE,
        'defaultPaperOrientation' => PDF_ORIENTATION,
        'dpi' => PDF_DPI,
        'fontCache' => PDF_FONT_CACHE,
        'tempDir' => PDF_CACHE_DIR,
        'chroot' => dirname(__DIR__), // Restrict file access to project root
        'logOutputFile' => __DIR__ . '/../logs/dompdf.log',
        'debugKeepTemp' => false,
        'debugCss' => false,
        'debugLayout' => false,
        'debugLayoutLines' => false,
        'debugLayoutBlocks' => false,
        'debugLayoutInline' => false,
        'debugLayoutPaddingBox' => false,
    ];
}

/**
 * Initialize Dompdf instance with configured options
 *
 * @return \Dompdf\Dompdf Configured Dompdf instance
 */
function initDompdf() {
    $options = new \Dompdf\Options(getDompdfOptions());
    $dompdf = new \Dompdf\Dompdf($options);

    return $dompdf;
}

/**
 * Format money amount for display
 *
 * @param float $amount Amount to format
 * @param string $currency Currency symbol
 * @return string Formatted amount
 */
function formatMoney($amount, $currency = '₱') {
    return $currency . number_format($amount, 2);
}

/**
 * Format date for receipt display
 *
 * @param string $date Date string
 * @param string $format Date format
 * @return string Formatted date
 */
function formatReceiptDate($date, $format = 'F d, Y h:i A') {
    return date($format, strtotime($date));
}
