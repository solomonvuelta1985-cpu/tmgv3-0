<?php
/**
 * Excel Citation Importer
 *
 * Comprehensive import system for Excel citation data
 * Implements smart grouping, deduplication, and data cleaning
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/config.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class ExcelImporter {
    private $db;
    private $batchId;
    private $excelFile;
    private $userId;

    // Statistics
    private $stats = [
        'total_rows' => 0,
        'processed_rows' => 0,
        'skipped_rows' => 0,
        'error_rows' => 0,
        'citations_created' => 0,
        'citations_paid' => 0,
        'drivers_created' => 0,
        'drivers_matched' => 0,
        'violations_created' => 0,
        'duplicates_removed' => 0,
        'tickets_generated' => 0
    ];

    // Configuration
    private $config = [
        'default_municipality' => 'Baggao',
        'default_province' => 'Cagayan',
        'fuzzy_match_threshold' => 3,
        'batch_size' => 500
    ];

    /**
     * Constructor
     */
    public function __construct($excelFile, $userId = null) {
        $this->db = getPDO();

        if ($this->db === null) {
            throw new Exception("Database connection failed. Check your database credentials in config.php");
        }

        $this->excelFile = $excelFile;
        $this->userId = $userId;
        $this->batchId = 'BATCH-' . date('YmdHis');
    }

    /**
     * Main import method
     */
    public function import($dryRun = false) {
        try {
            // Phase 1: Initialize batch (must be first - creates batch_id in database)
            $this->initializeBatch();

            $this->log('info', 'import_started', 'Starting Excel import process');

            // Phase 2: Extract and stage data
            $this->extractAndStageData();

            // Phase 3: Identify duplicates
            $this->identifyDuplicates();

            // Phase 4: Group multi-violations
            $this->groupMultiViolations();

            // Phase 5: Resolve ticket conflicts
            $this->resolveTicketConflicts();

            // Phase 6: Generate missing tickets
            $this->generateMissingTickets();

            if (!$dryRun) {
                // Phase 7: Import to database
                $this->db->beginTransaction();

                try {
                    $this->importDrivers();
                    $this->importCitations();
                    $this->importViolations();

                    $this->db->commit();
                    $this->completeBatch('completed');

                } catch (Exception $e) {
                    $this->db->rollBack();
                    $this->log('error', 'import_failed', 'Database import failed: ' . $e->getMessage());
                    $this->completeBatch('failed');
                    throw $e;
                }
            } else {
                $this->log('info', 'dry_run', 'Dry run completed - no data imported');
                $this->completeBatch('completed');
            }

            // Phase 8: Generate reports
            $report = $this->generateReport();

            return [
                'success' => true,
                'batch_id' => $this->batchId,
                'stats' => $this->stats,
                'report' => $report
            ];

        } catch (Exception $e) {
            $this->log('error', 'import_exception', 'Import failed with exception: ' . $e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'batch_id' => $this->batchId
            ];
        }
    }

    /**
     * Initialize import batch
     */
    private function initializeBatch() {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO import_batches (
                    batch_id, excel_file, started_at, imported_by, status
                ) VALUES (?, ?, NOW(), ?, 'running')
            ");

            $stmt->execute([
                $this->batchId,
                basename($this->excelFile),
                $this->userId
            ]);

            // Verify the batch was created before logging
            $verify = $this->db->prepare("SELECT batch_id FROM import_batches WHERE batch_id = ?");
            $verify->execute([$this->batchId]);

            if (!$verify->fetch()) {
                throw new Exception("Failed to create import batch record");
            }

            $this->log('info', 'batch_initialized', 'Import batch initialized');

        } catch (PDOException $e) {
            throw new Exception("Failed to initialize batch: " . $e->getMessage());
        }
    }

    /**
     * Extract data from Excel and load into staging
     */
    private function extractAndStageData() {
        $this->log('info', 'extraction_started', 'Starting Excel data extraction');

        $spreadsheet = IOFactory::load($this->excelFile);
        $worksheet = $spreadsheet->getActiveSheet();
        $highestRow = $worksheet->getHighestRow();

        $this->stats['total_rows'] = $highestRow - 1; // Exclude header

        // Prepare insert statement
        $stmt = $this->db->prepare("
            INSERT INTO import_staging (
                batch_id, excel_row, original_ticket, final_ticket,
                last_name, first_name, middle_initial, suffix,
                date_of_birth, age, zone, barangay, municipality, province,
                license_number, license_type,
                plate_mv_engine_chassis_no, vehicle_type, vehicle_description,
                apprehension_date, apprehension_time, apprehension_datetime,
                place_of_apprehension, apprehending_officer,
                violations_raw, remarks,
                driver_key, grouping_key, ticket_date_key
            ) VALUES (
                ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?,
                ?, ?,
                ?, ?, ?,
                ?, ?, ?,
                ?, ?,
                ?, ?,
                ?, ?, ?
            )
        ");

        $processedCount = 0;

        for ($row = 2; $row <= $highestRow; $row++) {
            try {
                $data = $this->extractRowData($worksheet, $row);

                if ($data) {
                    $stmt->execute([
                        $this->batchId,
                        $row,
                        $data['original_ticket'],
                        $data['final_ticket'],
                        $data['last_name'],
                        $data['first_name'],
                        $data['middle_initial'],
                        $data['suffix'],
                        $data['date_of_birth'],
                        $data['age'],
                        $data['zone'],
                        $data['barangay'],
                        $data['municipality'],
                        $data['province'],
                        $data['license_number'],
                        $data['license_type'],
                        $data['plate_mv_engine_chassis_no'],
                        $data['vehicle_type'],
                        $data['vehicle_description'],
                        $data['apprehension_date'],
                        $data['apprehension_time'],
                        $data['apprehension_datetime'],
                        $data['place_of_apprehension'],
                        $data['apprehending_officer'],
                        $data['violations_raw'],
                        $data['remarks'],
                        $data['driver_key'],
                        $data['grouping_key'],
                        $data['ticket_date_key']
                    ]);

                    $processedCount++;
                }

                if ($row % 1000 == 0) {
                    $this->log('debug', 'extraction_progress', "Processed $row of $highestRow rows");
                }

            } catch (Exception $e) {
                $this->log('error', 'row_extraction_failed', "Row $row failed: " . $e->getMessage(), $row);
                $this->stats['error_rows']++;
            }
        }

        $this->log('success', 'extraction_complete', "Extracted $processedCount rows into staging");
    }

    /**
     * Extract and normalize single row data
     */
    private function extractRowData($worksheet, $rowNumber) {
        // Extract raw values
        $ticketNumber = $this->getCellValue($worksheet, 'B', $rowNumber);
        $lastName = $this->getCellValue($worksheet, 'C', $rowNumber);
        $firstName = $this->getCellValue($worksheet, 'D', $rowNumber);
        $middleInitial = $this->getCellValue($worksheet, 'E', $rowNumber);
        $barangay = $this->getCellValue($worksheet, 'F', $rowNumber);
        $zone = $this->getCellValue($worksheet, 'G', $rowNumber);
        $licenseNumber = $this->getCellValue($worksheet, 'H', $rowNumber);
        $plate = $this->getCellValue($worksheet, 'I', $rowNumber);
        $vehicleType = $this->getCellValue($worksheet, 'J', $rowNumber);
        $vehicleDesc = $this->getCellValue($worksheet, 'K', $rowNumber);
        $dateRaw = $worksheet->getCell('L' . $rowNumber)->getValue();
        $timeRaw = $worksheet->getCell('M' . $rowNumber)->getValue();
        $place = $this->getCellValue($worksheet, 'N', $rowNumber);
        $violations = $this->getCellValue($worksheet, 'O', $rowNumber);
        $officer = $this->getCellValue($worksheet, 'P', $rowNumber);
        $remarks = $this->getCellValue($worksheet, 'Q', $rowNumber);

        // Normalize data
        $ticketNumber = $this->normalizeTicketNumber($ticketNumber);
        $lastName = $this->normalizeName($lastName);
        $firstName = $this->normalizeName($firstName);
        $middleInitial = $this->normalizeName($middleInitial);
        $licenseNumber = $this->normalizeLicense($licenseNumber);
        $plate = $this->normalizePlate($plate);

        // Parse dates
        $apprehensionDate = $this->parseExcelDate($dateRaw);
        $apprehensionTime = $this->parseExcelTime($timeRaw);
        $apprehensionDatetime = null;

        if ($apprehensionDate && $apprehensionTime) {
            $apprehensionDatetime = $apprehensionDate . ' ' . $apprehensionTime;
        } elseif ($apprehensionDate) {
            $apprehensionDatetime = $apprehensionDate . ' 00:00:00';
        }

        // Generate keys
        $driverKey = $this->generateDriverKey($lastName, $firstName, $licenseNumber);

        // Use placeholder for grouping if ticket is missing
        $groupingTicket = $ticketNumber ?: 'MISSING-' . $rowNumber;
        $groupingKey = $this->generateGroupingKey($groupingTicket, $lastName, $firstName, $apprehensionDate, $place);
        $ticketDateKey = $groupingTicket . '|' . $apprehensionDate;

        return [
            'original_ticket' => $ticketNumber,
            'final_ticket' => $ticketNumber ?: 'TEMP-' . $rowNumber, // Temporary placeholder for missing tickets
            'last_name' => $lastName,
            'first_name' => $firstName,
            'middle_initial' => $middleInitial,
            'suffix' => null,
            'date_of_birth' => null,
            'age' => null,
            'zone' => $zone,
            'barangay' => $barangay,
            'municipality' => $this->config['default_municipality'],
            'province' => $this->config['default_province'],
            'license_number' => $licenseNumber,
            'license_type' => null,
            'plate_mv_engine_chassis_no' => $plate,
            'vehicle_type' => $vehicleType,
            'vehicle_description' => $vehicleDesc,
            'apprehension_date' => $apprehensionDate,
            'apprehension_time' => $apprehensionTime,
            'apprehension_datetime' => $apprehensionDatetime,
            'place_of_apprehension' => $place,
            'apprehending_officer' => $officer,
            'violations_raw' => $violations,
            'remarks' => $remarks,
            'driver_key' => $driverKey,
            'grouping_key' => $groupingKey,
            'ticket_date_key' => $ticketDateKey
        ];
    }

    /**
     * Identify and mark exact duplicates
     */
    private function identifyDuplicates() {
        $this->log('info', 'duplicate_detection', 'Identifying exact duplicates');

        // Find exact duplicates (same grouping key + violations)
        $stmt = $this->db->query("
            SELECT
                grouping_key,
                violations_raw,
                MIN(staging_id) as keep_id,
                GROUP_CONCAT(staging_id ORDER BY staging_id) as all_ids,
                GROUP_CONCAT(excel_row ORDER BY excel_row) as all_rows,
                COUNT(*) as duplicate_count
            FROM import_staging
            WHERE batch_id = '{$this->batchId}'
            AND violations_raw IS NOT NULL
            GROUP BY grouping_key, violations_raw
            HAVING COUNT(*) > 1
        ");

        $duplicates = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($duplicates as $dup) {
            $ids = explode(',', $dup['all_ids']);
            $rows = explode(',', $dup['all_rows']);
            $keepId = $dup['keep_id'];

            $removedIds = array_diff($ids, [$keepId]);
            $removedRows = array_diff($rows, [$rows[0]]);

            // Mark as duplicates
            if (!empty($removedIds)) {
                $this->db->prepare("
                    UPDATE import_staging
                    SET is_exact_duplicate = 1,
                        process_status = 'skipped'
                    WHERE staging_id IN (" . implode(',', $removedIds) . ")
                ")->execute();

                // Log duplicate group
                $this->db->prepare("
                    INSERT INTO import_duplicates (
                        batch_id, grouping_key, excel_rows, kept_row, removed_rows, duplicate_type
                    ) VALUES (?, ?, ?, ?, ?, 'exact')
                ")->execute([
                    $this->batchId,
                    $dup['grouping_key'],
                    $dup['all_rows'],
                    $rows[0],
                    implode(',', $removedRows)
                ]);

                $this->stats['duplicates_removed'] += count($removedIds);
            }
        }

        $this->log('success', 'duplicates_identified', "Removed {$this->stats['duplicates_removed']} exact duplicates");
    }

    /**
     * Group multi-violation citations
     */
    private function groupMultiViolations() {
        $this->log('info', 'grouping_started', 'Grouping multi-violation citations');

        // Assign group IDs to records with same grouping key
        $stmt = $this->db->query("
            SELECT
                grouping_key,
                MIN(staging_id) as group_id,
                GROUP_CONCAT(staging_id ORDER BY staging_id) as member_ids,
                GROUP_CONCAT(excel_row ORDER BY excel_row) as excel_rows,
                GROUP_CONCAT(violations_raw SEPARATOR '|||') as all_violations,
                COUNT(*) as member_count
            FROM import_staging
            WHERE batch_id = '{$this->batchId}'
            AND is_exact_duplicate = 0
            GROUP BY grouping_key
        ");

        $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $multiViolationGroups = 0;

        foreach ($groups as $group) {
            $groupId = $group['group_id'];
            $memberIds = explode(',', $group['member_ids']);

            // Update all members with group ID
            $this->db->prepare("
                UPDATE import_staging
                SET citation_group_id = ?,
                    is_grouped = 1
                WHERE staging_id IN (" . implode(',', $memberIds) . ")
            ")->execute([$groupId]);

            // Log groups with multiple members
            if ($group['member_count'] > 1) {
                $this->db->prepare("
                    INSERT INTO import_citation_groups (
                        batch_id, grouping_key, final_ticket, excel_rows, row_count, violations_combined
                    ) VALUES (?, ?, (
                        SELECT final_ticket FROM import_staging WHERE staging_id = ? LIMIT 1
                    ), ?, ?, ?)
                ")->execute([
                    $this->batchId,
                    $group['grouping_key'],
                    $groupId,
                    $group['excel_rows'],
                    $group['member_count'],
                    $group['all_violations']
                ]);

                $multiViolationGroups++;
            }
        }

        $this->log('success', 'grouping_complete', "Created $multiViolationGroups multi-violation citation groups");
    }

    /**
     * Resolve ticket number conflicts
     */
    private function resolveTicketConflicts() {
        $this->log('info', 'conflict_resolution', 'Resolving ticket number conflicts');

        // Find tickets with different dates or people
        $stmt = $this->db->query("
            SELECT
                final_ticket,
                GROUP_CONCAT(DISTINCT apprehension_date) as dates,
                GROUP_CONCAT(DISTINCT driver_key) as driver_keys,
                GROUP_CONCAT(staging_id ORDER BY staging_id) as all_ids,
                GROUP_CONCAT(excel_row ORDER BY excel_row) as all_rows,
                COUNT(DISTINCT citation_group_id) as group_count
            FROM import_staging
            WHERE batch_id = '{$this->batchId}'
            AND is_exact_duplicate = 0
            AND final_ticket IS NOT NULL
            AND final_ticket != ''
            GROUP BY final_ticket
            HAVING COUNT(DISTINCT apprehension_date) > 1
               OR COUNT(DISTINCT driver_key) > 1
        ");

        $conflicts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($conflicts as $conflict) {
            $ids = explode(',', $conflict['all_ids']);
            $rows = explode(',', $conflict['all_rows']);
            $dates = explode(',', $conflict['dates']);
            $driverKeys = explode(',', $conflict['driver_keys']);

            $conflictType = 'both';
            if (count($dates) > 1 && count($driverKeys) == 1) {
                $conflictType = 'different_date';
            } elseif (count($dates) == 1 && count($driverKeys) > 1) {
                $conflictType = 'different_person';
            }

            // Keep first occurrence, generate new tickets for rest
            $generatedTickets = [];
            $suffix = 'A';

            for ($i = 1; $i < count($ids); $i++) {
                $newTicket = $conflict['final_ticket'] . '-' . $suffix;
                $generatedTickets[] = $newTicket;

                $this->db->prepare("
                    UPDATE import_staging
                    SET final_ticket = ?,
                        ticket_generated = 1,
                        generation_reason = ?,
                        is_conflict = 1
                    WHERE staging_id = ?
                ")->execute([
                    $newTicket,
                    "Conflict: $conflictType",
                    $ids[$i]
                ]);

                $suffix++;
                $this->stats['tickets_generated']++;
            }

            // Log conflict
            $this->db->prepare("
                INSERT INTO import_ticket_conflicts (
                    batch_id, original_ticket, conflicting_rows, conflict_type,
                    resolution, tickets_generated
                ) VALUES (?, ?, ?, ?, ?, ?)
            ")->execute([
                $this->batchId,
                $conflict['final_ticket'],
                implode(',', $rows),
                $conflictType,
                'Generated suffixed tickets',
                implode(',', $generatedTickets)
            ]);
        }

        $this->log('success', 'conflicts_resolved', "Resolved " . count($conflicts) . " ticket conflicts");
    }

    /**
     * Generate missing ticket numbers
     */
    private function generateMissingTickets() {
        $this->log('info', 'generating_tickets', 'Generating missing ticket numbers');

        $stmt = $this->db->query("
            SELECT staging_id, excel_row
            FROM import_staging
            WHERE batch_id = '{$this->batchId}'
            AND (final_ticket IS NULL OR final_ticket = '' OR final_ticket LIKE 'TEMP-%')
            AND is_exact_duplicate = 0
        ");

        $missing = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $sequence = 19001;

        foreach ($missing as $row) {
            $newTicket = 'AUT-' . str_pad($sequence, 6, '0', STR_PAD_LEFT);

            $this->db->prepare("
                UPDATE import_staging
                SET final_ticket = ?,
                    ticket_generated = 1,
                    generation_reason = 'Missing ticket number'
                WHERE staging_id = ?
            ")->execute([$newTicket, $row['staging_id']]);

            $this->log('warning', 'ticket_generated', "Generated ticket $newTicket for row {$row['excel_row']}", $row['excel_row'], $newTicket);

            $sequence++;
            $this->stats['tickets_generated']++;
        }

        $this->log('success', 'tickets_generated', "Generated " . count($missing) . " missing ticket numbers");
    }

    /**
     * Import/Match Drivers
     */
    private function importDrivers() {
        $this->log('info', 'driver_import', 'Importing drivers');

        // Get unique driver keys from staging
        $stmt = $this->db->query("
            SELECT DISTINCT
                driver_key,
                last_name,
                first_name,
                middle_initial,
                license_number,
                barangay,
                municipality,
                province
            FROM import_staging
            WHERE batch_id = '{$this->batchId}'
            AND is_exact_duplicate = 0
            AND last_name IS NOT NULL
            AND first_name IS NOT NULL
        ");

        $uniqueDrivers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($uniqueDrivers as $driver) {
            try {
                // Try to find existing driver
                $existingStmt = $this->db->prepare("
                    SELECT driver_id
                    FROM drivers
                    WHERE last_name = ?
                    AND first_name = ?
                    AND (
                        license_number = ?
                        OR (license_number IS NULL AND ? IS NULL)
                        OR license_number IS NULL
                        OR ? IS NULL
                    )
                    LIMIT 1
                ");

                $existingStmt->execute([
                    $driver['last_name'],
                    $driver['first_name'],
                    $driver['license_number'],
                    $driver['license_number'],
                    $driver['license_number']
                ]);

                $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);

                if ($existing) {
                    // Match existing driver
                    $driverId = $existing['driver_id'];
                    $this->stats['drivers_matched']++;

                    $this->log('debug', 'driver_matched', "Matched existing driver: {$driver['last_name']}, {$driver['first_name']}");

                } else {
                    // Create new driver
                    $insertStmt = $this->db->prepare("
                        INSERT INTO drivers (
                            last_name, first_name, middle_initial,
                            license_number,
                            barangay, municipality, province,
                            import_batch_id,
                            created_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ");

                    $insertStmt->execute([
                        $driver['last_name'],
                        $driver['first_name'],
                        $driver['middle_initial'],
                        $driver['license_number'],
                        $driver['barangay'],
                        $driver['municipality'],
                        $driver['province'],
                        $this->batchId
                    ]);

                    $driverId = $this->db->lastInsertId();
                    $this->stats['drivers_created']++;

                    $this->log('success', 'driver_created', "Created new driver: {$driver['last_name']}, {$driver['first_name']} (ID: $driverId)");
                }

                // Update staging with driver ID
                $this->db->prepare("
                    UPDATE import_staging
                    SET driver_id = ?
                    WHERE batch_id = ?
                    AND driver_key = ?
                ")->execute([$driverId, $this->batchId, $driver['driver_key']]);

            } catch (Exception $e) {
                $this->log('error', 'driver_import_failed', "Failed to import driver: " . $e->getMessage());
            }
        }

        $this->log('success', 'drivers_imported', "Imported {$this->stats['drivers_created']} drivers, matched {$this->stats['drivers_matched']} existing");
    }

    /**
     * Import Citations
     */
    private function importCitations() {
        $this->log('info', 'citation_import', 'Importing citations');

        // Get unique citation groups
        $stmt = $this->db->query("
            SELECT
                MIN(staging_id) as staging_id,
                citation_group_id,
                final_ticket,
                driver_id,
                last_name,
                first_name,
                middle_initial,
                zone,
                barangay,
                license_number,
                license_type,
                plate_mv_engine_chassis_no,
                vehicle_type,
                vehicle_description,
                apprehension_datetime,
                place_of_apprehension,
                apprehending_officer,
                remarks
            FROM import_staging
            WHERE batch_id = '{$this->batchId}'
            AND is_exact_duplicate = 0
            AND driver_id IS NOT NULL
            GROUP BY citation_group_id
        ");

        $citations = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($citations as $citation) {
            try {
                // Determine payment status from REMARKS column
                $status = 'pending'; // Default status
                if (!empty($citation['remarks'])) {
                    $remarksUpper = strtoupper(trim($citation['remarks']));
                    // Check if REMARKS contains 'PAID'
                    if (strpos($remarksUpper, 'PAID') !== false) {
                        $status = 'paid';
                    }
                }

                $insertStmt = $this->db->prepare("
                    INSERT INTO citations (
                        ticket_number,
                        driver_id,
                        last_name,
                        first_name,
                        middle_initial,
                        zone,
                        barangay,
                        municipality,
                        province,
                        license_number,
                        license_type,
                        plate_mv_engine_chassis_no,
                        vehicle_type,
                        vehicle_description,
                        apprehension_datetime,
                        place_of_apprehension,
                        apprehension_officer,
                        remarks,
                        status,
                        import_batch_id,
                        created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");

                $insertStmt->execute([
                    $citation['final_ticket'],
                    $citation['driver_id'],
                    $citation['last_name'],
                    $citation['first_name'],
                    $citation['middle_initial'],
                    $citation['zone'],
                    $citation['barangay'],
                    $this->config['default_municipality'],
                    $this->config['default_province'],
                    $citation['license_number'],
                    $citation['license_type'],
                    $citation['plate_mv_engine_chassis_no'],
                    $citation['vehicle_type'],
                    $citation['vehicle_description'],
                    $citation['apprehension_datetime'],
                    $citation['place_of_apprehension'],
                    $citation['apprehending_officer'],
                    $citation['remarks'],
                    $status,
                    $this->batchId
                ]);

                $citationId = $this->db->lastInsertId();
                $this->stats['citations_created']++;

                // Track paid citations
                if ($status === 'paid') {
                    $this->stats['citations_paid']++;
                }

                // Update staging with citation ID
                $this->db->prepare("
                    UPDATE import_staging
                    SET citation_id = ?,
                        process_status = 'processed'
                    WHERE citation_group_id = ?
                ")->execute([$citationId, $citation['citation_group_id']]);

                $statusLabel = $status === 'paid' ? ' [PAID]' : '';
                $this->log('debug', 'citation_created', "Created citation {$citation['final_ticket']} (ID: $citationId)$statusLabel");

            } catch (Exception $e) {
                $this->log('error', 'citation_import_failed', "Failed to import citation {$citation['final_ticket']}: " . $e->getMessage());
            }
        }

        $this->log('success', 'citations_imported', "Created {$this->stats['citations_created']} citations");
    }

    /**
     * Import Violations
     */
    private function importViolations() {
        $this->log('info', 'violation_import', 'Importing violations');

        // Get all non-duplicate staging records with citations
        $stmt = $this->db->query("
            SELECT
                staging_id,
                citation_id,
                driver_id,
                violations_raw
            FROM import_staging
            WHERE batch_id = '{$this->batchId}'
            AND is_exact_duplicate = 0
            AND citation_id IS NOT NULL
            AND violations_raw IS NOT NULL
        ");

        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($records as $record) {
            try {
                $violationsText = $record['violations_raw'];

                // Split by comma
                $violationsList = explode(',', $violationsText);

                foreach ($violationsList as $violationText) {
                    $violationText = trim($violationText);

                    if (empty($violationText)) continue;

                    // Match to violation type
                    $violationType = $this->matchViolationType($violationText);

                    if ($violationType) {
                        // Get offense count for this driver
                        $offenseCount = $this->getOffenseCount($record['driver_id'], $violationType['violation_type_id']);

                        // Get the correct fine amount based on offense count
                        $fineAmount = $this->getFineAmount($violationType['violation_type_id'], $offenseCount);

                        // Insert violation
                        $insertStmt = $this->db->prepare("
                            INSERT INTO violations (
                                citation_id,
                                violation_type_id,
                                offense_count,
                                fine_amount,
                                import_batch_id,
                                created_at
                            ) VALUES (?, ?, ?, ?, ?, NOW())
                        ");

                        $insertStmt->execute([
                            $record['citation_id'],
                            $violationType['violation_type_id'],
                            $offenseCount,
                            $fineAmount,
                            $this->batchId
                        ]);

                        $this->stats['violations_created']++;

                        $this->log('debug', 'violation_created', "Created violation: $violationText (Fine: $fineAmount)");
                    }
                }

            } catch (Exception $e) {
                $this->log('error', 'violation_import_failed', "Failed to import violations for citation {$record['citation_id']}: " . $e->getMessage());
            }
        }

        $this->log('success', 'violations_imported', "Created {$this->stats['violations_created']} violation records");
    }

    /**
     * Match violation text to violation type
     */
    private function matchViolationType($violationText) {
        // Original text for caching
        $originalText = $violationText;

        // Normalize the violation text
        $normalizedText = $this->normalizeViolationText($violationText);

        // Check cache first (using original text)
        $cacheStmt = $this->db->prepare("
            SELECT violation_type_id, violation_type_name, match_type
            FROM import_violation_mappings
            WHERE batch_id = ?
            AND excel_text = ?
        ");

        $cacheStmt->execute([$this->batchId, $originalText]);
        $cached = $cacheStmt->fetch(PDO::FETCH_ASSOC);

        if ($cached) {
            return [
                'violation_type_id' => $cached['violation_type_id'],
                'violation_type' => $cached['violation_type_name']
            ];
        }

        // Try exact match with original text
        $exactStmt = $this->db->prepare("
            SELECT violation_type_id, violation_type
            FROM violation_types
            WHERE violation_type = ?
            AND is_active = 1
        ");

        $exactStmt->execute([$originalText]);
        $exact = $exactStmt->fetch(PDO::FETCH_ASSOC);

        if ($exact) {
            $this->cacheViolationMapping($originalText, $exact['violation_type_id'], $exact['violation_type'], 'exact', 100);
            return $exact;
        }

        // Try normalized match
        $normalizedStmt = $this->db->prepare("
            SELECT violation_type_id, violation_type
            FROM violation_types
            WHERE REPLACE(REPLACE(UPPER(violation_type), ' ', ''), '  ', '') = ?
            AND is_active = 1
            LIMIT 1
        ");

        $normalizedStmt->execute([$normalizedText]);
        $normalized = $normalizedStmt->fetch(PDO::FETCH_ASSOC);

        if ($normalized) {
            $this->cacheViolationMapping($originalText, $normalized['violation_type_id'], $normalized['violation_type'], 'normalized', 90);
            return $normalized;
        }

        // Try case-insensitive match
        $caseStmt = $this->db->prepare("
            SELECT violation_type_id, violation_type
            FROM violation_types
            WHERE UPPER(violation_type) = UPPER(?)
            AND is_active = 1
        ");

        $caseStmt->execute([$originalText]);
        $caseMatch = $caseStmt->fetch(PDO::FETCH_ASSOC);

        if ($caseMatch) {
            $this->cacheViolationMapping($originalText, $caseMatch['violation_type_id'], $caseMatch['violation_type'], 'case_insensitive', 95);
            return $caseMatch;
        }

        // Try partial match
        $partialStmt = $this->db->prepare("
            SELECT violation_type_id, violation_type
            FROM violation_types
            WHERE violation_type LIKE ?
            OR ? LIKE CONCAT('%', violation_type, '%')
            AND is_active = 1
            LIMIT 1
        ");

        $partialStmt->execute(["%$originalText%", $originalText]);
        $partial = $partialStmt->fetch(PDO::FETCH_ASSOC);

        if ($partial) {
            $this->cacheViolationMapping($originalText, $partial['violation_type_id'], $partial['violation_type'], 'partial', 70);
            $this->log('warning', 'fuzzy_match', "Partial match: '$originalText' → '{$partial['violation_type']}'");
            return $partial;
        }

        // Create new violation type
        $this->log('warning', 'new_violation', "Creating new violation type: $originalText");

        $insertStmt = $this->db->prepare("
            INSERT INTO violation_types (
                violation_type, is_active, created_at
            ) VALUES (?, 1, NOW())
        ");

        $insertStmt->execute([$originalText]);
        $newId = $this->db->lastInsertId();

        $this->cacheViolationMapping($originalText, $newId, $originalText, 'new', 100);

        return [
            'violation_type_id' => $newId,
            'violation_type' => $originalText
        ];
    }

    /**
     * Normalize violation text for matching
     */
    private function normalizeViolationText($text) {
        // Convert to uppercase
        $text = strtoupper(trim($text));

        // Remove extra spaces (multiple spaces to single space)
        $text = preg_replace('/\s+/', ' ', $text);

        // Remove all spaces for comparison
        $text = str_replace(' ', '', $text);

        return $text;
    }

    /**
     * Cache violation mapping
     */
    private function cacheViolationMapping($excelText, $violationTypeId, $violationTypeName, $matchType, $confidence) {
        $this->db->prepare("
            INSERT INTO import_violation_mappings (
                batch_id, excel_text, violation_type_id, violation_type_name, match_type, match_confidence
            ) VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE times_used = times_used + 1
        ")->execute([
            $this->batchId,
            $excelText,
            $violationTypeId,
            $violationTypeName,
            $matchType,
            $confidence
        ]);
    }

    /**
     * Get offense count for driver + violation type
     */
    private function getOffenseCount($driverId, $violationTypeId) {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count
            FROM violations v
            JOIN citations c ON v.citation_id = c.citation_id
            WHERE c.driver_id = ?
            AND v.violation_type_id = ?
        ");

        $stmt->execute([$driverId, $violationTypeId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return ($result['count'] ?? 0) + 1;
    }

    /**
     * Get fine amount based on offense count
     */
    private function getFineAmount($violationTypeId, $offenseCount) {
        $stmt = $this->db->prepare("
            SELECT
                fine_amount_1,
                fine_amount_2,
                fine_amount_3
            FROM violation_types
            WHERE violation_type_id = ?
        ");

        $stmt->execute([$violationTypeId]);
        $fines = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$fines) {
            return 500.00; // Default fine if violation type not found
        }

        // Return appropriate fine based on offense count
        if ($offenseCount == 1) {
            return $fines['fine_amount_1'];
        } elseif ($offenseCount == 2) {
            return $fines['fine_amount_2'];
        } else {
            return $fines['fine_amount_3'];
        }
    }

    /**
     * Complete batch
     */
    private function completeBatch($status) {
        $this->db->prepare("
            UPDATE import_batches
            SET status = ?,
                completed_at = NOW(),
                total_rows = ?,
                processed_rows = ?,
                skipped_rows = ?,
                error_rows = ?,
                citations_created = ?,
                citations_paid = ?,
                drivers_created = ?,
                drivers_matched = ?,
                violations_created = ?,
                duplicates_removed = ?,
                tickets_generated = ?,
                summary = ?
            WHERE batch_id = ?
        ")->execute([
            $status,
            $this->stats['total_rows'],
            $this->stats['processed_rows'],
            $this->stats['skipped_rows'],
            $this->stats['error_rows'],
            $this->stats['citations_created'],
            $this->stats['citations_paid'],
            $this->stats['drivers_created'],
            $this->stats['drivers_matched'],
            $this->stats['violations_created'],
            $this->stats['duplicates_removed'],
            $this->stats['tickets_generated'],
            json_encode($this->stats),
            $this->batchId
        ]);
    }

    /**
     * Generate import report
     */
    private function generateReport() {
        $report = [];
        $report['batch_id'] = $this->batchId;
        $report['stats'] = $this->stats;

        // Get auto-generated tickets
        $stmt = $this->db->query("
            SELECT final_ticket, excel_row, generation_reason
            FROM import_staging
            WHERE batch_id = '{$this->batchId}'
            AND ticket_generated = 1
            ORDER BY final_ticket
        ");
        $report['auto_generated_tickets'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get conflicts
        $stmt = $this->db->query("
            SELECT * FROM import_ticket_conflicts
            WHERE batch_id = '{$this->batchId}'
            ORDER BY original_ticket
        ");
        $report['ticket_conflicts'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get multi-violation groups
        $stmt = $this->db->query("
            SELECT * FROM import_citation_groups
            WHERE batch_id = '{$this->batchId}'
            ORDER BY row_count DESC
        ");
        $report['multi_violation_groups'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get fuzzy matches
        $stmt = $this->db->query("
            SELECT * FROM import_violation_mappings
            WHERE batch_id = '{$this->batchId}'
            AND match_type != 'exact'
            ORDER BY match_type, excel_text
        ");
        $report['fuzzy_matches'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $report;
    }

    /**
     * Log message
     */
    private function log($level, $type, $message, $excelRow = null, $ticketNumber = null, $details = null) {
        $stmt = $this->db->prepare("
            INSERT INTO import_logs (
                batch_id, log_level, log_type, message, excel_row, ticket_number, details
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $this->batchId,
            $level,
            $type,
            $message,
            $excelRow,
            $ticketNumber,
            $details ? json_encode($details) : null
        ]);
    }

    // ==========================================
    // UTILITY METHODS
    // ==========================================

    private function getCellValue($worksheet, $column, $row) {
        $value = $worksheet->getCell($column . $row)->getValue();
        return $value !== null ? trim($value) : null;
    }

    private function normalizeTicketNumber($ticket) {
        if (empty($ticket)) return null;
        return strtoupper(trim($ticket));
    }

    private function normalizeName($name) {
        if (empty($name)) return null;
        return strtoupper(trim($name));
    }

    private function normalizeLicense($license) {
        if (empty($license) || strtoupper($license) == 'NONE' || strtoupper($license) == 'N/A') {
            return null;
        }
        return strtoupper(trim($license));
    }

    private function normalizePlate($plate) {
        if (empty($plate)) return null;
        return strtoupper(trim(preg_replace('/\s+/', '', $plate)));
    }

    private function parseExcelDate($value) {
        if (empty($value)) return null;

        if (is_numeric($value)) {
            try {
                return Date::excelToDateTimeObject($value)->format('Y-m-d');
            } catch (Exception $e) {
                return null;
            }
        }

        return null;
    }

    private function parseExcelTime($value) {
        if (empty($value)) return '00:00:00';

        if (is_numeric($value)) {
            try {
                return Date::excelToDateTimeObject($value)->format('H:i:s');
            } catch (Exception $e) {
                return '00:00:00';
            }
        }

        return '00:00:00';
    }

    private function generateDriverKey($lastName, $firstName, $license) {
        return sha1(strtoupper($lastName . '|' . $firstName . '|' . $license));
    }

    private function generateGroupingKey($ticket, $lastName, $firstName, $date, $place) {
        return md5(strtoupper($ticket . '|' . $lastName . '|' . $firstName . '|' . $date . '|' . $place));
    }
}
