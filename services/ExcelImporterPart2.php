<?php
/**
 * ExcelImporter - Part 2
 * (Append this to ExcelImporter.php after the generateMissingTickets() method)
 */

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
                plate_mv_engine_chassis_no,
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
                $insertStmt = $this->db->prepare("
                    INSERT INTO citations (
                        ticket_number,
                        driver_id,
                        last_name,
                        first_name,
                        middle_initial,
                        plate_mv_engine_chassis_no,
                        apprehension_datetime,
                        place_of_apprehension,
                        remarks,
                        status,
                        municipality,
                        province,
                        import_batch_id,
                        created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, NOW())
                ");

                $insertStmt->execute([
                    $citation['final_ticket'],
                    $citation['driver_id'],
                    $citation['last_name'],
                    $citation['first_name'],
                    $citation['middle_initial'],
                    $citation['plate_mv_engine_chassis_no'],
                    $citation['apprehension_datetime'],
                    $citation['place_of_apprehension'],
                    $citation['remarks'],
                    $this->config['default_municipality'],
                    $this->config['default_province'],
                    $this->batchId
                ]);

                $citationId = $this->db->lastInsertId();
                $this->stats['citations_created']++;

                // Update staging with citation ID
                $this->db->prepare("
                    UPDATE import_staging
                    SET citation_id = ?,
                        process_status = 'processed'
                    WHERE citation_group_id = ?
                ")->execute([$citationId, $citation['citation_group_id']]);

                $this->log('debug', 'citation_created', "Created citation {$citation['final_ticket']} (ID: $citationId)");

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

                        // Insert violation
                        $insertStmt = $this->db->prepare("
                            INSERT INTO violations (
                                citation_id,
                                violation_type_id,
                                offense_count,
                                import_batch_id,
                                created_at
                            ) VALUES (?, ?, ?, ?, NOW())
                        ");

                        $insertStmt->execute([
                            $record['citation_id'],
                            $violationType['violation_type_id'],
                            $offenseCount,
                            $this->batchId
                        ]);

                        $this->stats['violations_created']++;

                        $this->log('debug', 'violation_created', "Created violation: $violationText");
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
        // Check cache first
        $cacheStmt = $this->db->prepare("
            SELECT violation_type_id, violation_type_name, match_type
            FROM import_violation_mappings
            WHERE batch_id = ?
            AND excel_text = ?
        ");

        $cacheStmt->execute([$this->batchId, $violationText]);
        $cached = $cacheStmt->fetch(PDO::FETCH_ASSOC);

        if ($cached) {
            return [
                'violation_type_id' => $cached['violation_type_id'],
                'violation_type' => $cached['violation_type_name']
            ];
        }

        // Try exact match
        $exactStmt = $this->db->prepare("
            SELECT violation_type_id, violation_type
            FROM violation_types
            WHERE violation_type = ?
            AND is_active = 1
        ");

        $exactStmt->execute([$violationText]);
        $exact = $exactStmt->fetch(PDO::FETCH_ASSOC);

        if ($exact) {
            $this->cacheViolationMapping($violationText, $exact['violation_type_id'], $exact['violation_type'], 'exact', 100);
            return $exact;
        }

        // Try case-insensitive match
        $caseStmt = $this->db->prepare("
            SELECT violation_type_id, violation_type
            FROM violation_types
            WHERE UPPER(violation_type) = UPPER(?)
            AND is_active = 1
        ");

        $caseStmt->execute([$violationText]);
        $caseMatch = $caseStmt->fetch(PDO::FETCH_ASSOC);

        if ($caseMatch) {
            $this->cacheViolationMapping($violationText, $caseMatch['violation_type_id'], $caseMatch['violation_type'], 'case_insensitive', 95);
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

        $partialStmt->execute(["%$violationText%", $violationText]);
        $partial = $partialStmt->fetch(PDO::FETCH_ASSOC);

        if ($partial) {
            $this->cacheViolationMapping($violationText, $partial['violation_type_id'], $partial['violation_type'], 'partial', 70);
            $this->log('warning', 'fuzzy_match', "Partial match: '$violationText' → '{$partial['violation_type']}'");
            return $partial;
        }

        // Create new violation type
        $this->log('warning', 'new_violation', "Creating new violation type: $violationText");

        $insertStmt = $this->db->prepare("
            INSERT INTO violation_types (
                violation_type, is_active, created_at
            ) VALUES (?, 1, NOW())
        ");

        $insertStmt->execute([$violationText]);
        $newId = $this->db->lastInsertId();

        $this->cacheViolationMapping($violationText, $newId, $violationText, 'new', 100);

        return [
            'violation_type_id' => $newId,
            'violation_type' => $violationText
        ];
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
