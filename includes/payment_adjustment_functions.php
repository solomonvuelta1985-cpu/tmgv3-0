<?php
/**
 * Payment Adjustment Functions
 *
 * Helper functions for admin payment adjustments system
 * Maintains audit trail and financial integrity
 *
 * @package TrafficCitationSystem
 * @subpackage PaymentAdjustments
 */

/**
 * Get valid adjustment types
 *
 * @return array Adjustment types with metadata
 */
function get_adjustment_types() {
    return [
        'external_payment' => [
            'label' => 'Paid at External Office',
            'description' => 'Payment made at LTO office, bank, or other authorized location',
            'requires_or' => true,
            'allows_amount_edit' => false,
            'target_status' => 'paid',
            'requires_password' => true
        ],
        'manual_correction' => [
            'label' => 'Data Correction',
            'description' => 'Payment was processed but status did not update correctly',
            'requires_or' => true,
            'allows_amount_edit' => false,
            'target_status' => 'paid',
            'requires_password' => false
        ],
        'waived' => [
            'label' => 'Fine Waived',
            'description' => 'Citation fine waived by authorized official',
            'requires_or' => false,
            'allows_amount_edit' => false,
            'target_status' => 'waived',
            'requires_password' => true
        ],
        'backlog_entry' => [
            'label' => 'Lost Paperwork Recovery',
            'description' => 'Retroactive entry for payment with lost or missing paperwork',
            'requires_or' => true,
            'allows_amount_edit' => false,
            'target_status' => 'paid',
            'requires_password' => true
        ],
        'court_settlement' => [
            'label' => 'Court Settlement',
            'description' => 'Payment through court order or legal settlement',
            'requires_or' => true,
            'allows_amount_edit' => true,
            'target_status' => 'paid',
            'requires_password' => true
        ]
    ];
}

/**
 * Validate citation eligibility for adjustment
 *
 * @param int $citation_id Citation ID
 * @param PDO $pdo Database connection
 * @return array ['valid' => bool, 'message' => string, 'citation' => array|null]
 */
function validate_citation_for_adjustment($citation_id, $pdo) {
    try {
        // Get citation details
        $stmt = $pdo->prepare("
            SELECT
                c.*,
                CONCAT(c.first_name, ' ', c.last_name) AS driver_name,
                GROUP_CONCAT(vt.violation_type SEPARATOR ', ') AS violations
            FROM citations c
            LEFT JOIN violations v ON c.citation_id = v.citation_id
            LEFT JOIN violation_types vt ON v.violation_type_id = vt.violation_type_id
            WHERE c.citation_id = ? AND c.deleted_at IS NULL
            GROUP BY c.citation_id
        ");
        $stmt->execute([$citation_id]);
        $citation = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$citation) {
            return [
                'valid' => false,
                'message' => 'Citation not found or has been deleted.',
                'citation' => null
            ];
        }

        // Check if already paid or waived
        if (in_array($citation['status'], ['paid', 'waived'])) {
            return [
                'valid' => false,
                'message' => 'Citation is already marked as ' . $citation['status'] . '. Cannot adjust.',
                'citation' => $citation
            ];
        }

        // Check if there's an existing payment
        $stmt = $pdo->prepare("
            SELECT payment_id, receipt_number, status
            FROM payments
            WHERE citation_id = ? AND status IN ('pending_print', 'completed')
            LIMIT 1
        ");
        $stmt->execute([$citation_id]);
        $existing_payment = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing_payment) {
            return [
                'valid' => false,
                'message' => 'Citation already has an active payment record (OR: ' . $existing_payment['receipt_number'] . '). Cannot adjust.',
                'citation' => $citation
            ];
        }

        return [
            'valid' => true,
            'message' => 'Citation is eligible for adjustment.',
            'citation' => $citation
        ];

    } catch (PDOException $e) {
        error_log("Error validating citation for adjustment: " . $e->getMessage());
        return [
            'valid' => false,
            'message' => 'Database error occurred while validating citation.',
            'citation' => null
        ];
    }
}

/**
 * Check if OR number is unique
 *
 * @param string $receipt_number OR number to check
 * @param PDO $pdo Database connection
 * @return bool True if unique, false if already exists
 */
function is_receipt_number_unique($receipt_number, $pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM payments WHERE receipt_number = ?
        ");
        $stmt->execute([$receipt_number]);
        return $stmt->fetchColumn() == 0;
    } catch (PDOException $e) {
        error_log("Error checking OR number uniqueness: " . $e->getMessage());
        return false;
    }
}

/**
 * Verify admin password
 *
 * @param int $user_id Admin user ID
 * @param string $password Password to verify
 * @param PDO $pdo Database connection
 * @return bool True if password is correct
 */
function verify_admin_password($user_id, $password, $pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT password_hash FROM users WHERE user_id = ? AND role = 'admin'
        ");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return false;
        }

        return password_verify($password, $user['password_hash']);
    } catch (PDOException $e) {
        error_log("Error verifying admin password: " . $e->getMessage());
        return false;
    }
}

/**
 * Create payment adjustment
 *
 * @param array $data Adjustment data
 * @param PDO $pdo Database connection
 * @return array ['success' => bool, 'message' => string, 'payment_id' => int|null]
 */
function create_payment_adjustment($data, $pdo) {
    try {
        // Start transaction
        $pdo->beginTransaction();

        // Validate required fields (except amount which can be 0)
        $required = ['citation_id', 'adjustment_type', 'reason', 'admin_user_id'];
        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Missing required field: $field");
            }
        }

        // Validate amount separately (allow 0 for waived fines)
        if (!isset($data['amount']) || $data['amount'] === '' || $data['amount'] === null) {
            throw new Exception("Missing required field: amount");
        }

        // Get adjustment type config
        $types = get_adjustment_types();
        if (!isset($types[$data['adjustment_type']])) {
            throw new Exception("Invalid adjustment type");
        }
        $type_config = $types[$data['adjustment_type']];

        // Handle OR number - API sends 'or_number', but DB uses 'receipt_number' for payments table
        // and 'or_number' for audit log table
        if (isset($data['or_number'])) {
            $data['receipt_number'] = $data['or_number']; // For payments table
            // $data['or_number'] stays for audit log table
        }

        // Validate OR number if required
        if ($type_config['requires_or']) {
            if (empty($data['receipt_number'])) {
                throw new Exception("OR number is required for this adjustment type");
            }
            if (!is_receipt_number_unique($data['receipt_number'], $pdo)) {
                throw new Exception("OR number already exists in the system");
            }
        }

        // Get citation details for old status
        $stmt = $pdo->prepare("SELECT status FROM citations WHERE citation_id = ?");
        $stmt->execute([$data['citation_id']]);
        $old_status = $stmt->fetchColumn();

        // Insert payment record
        $stmt = $pdo->prepare("
            INSERT INTO payments (
                citation_id,
                receipt_number,
                amount_paid,
                payment_method,
                payment_date,
                collected_by,
                status,
                adjustment_type,
                adjustment_reason,
                adjusted_by,
                original_payment_date,
                is_adjustment,
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
        ");

        // Format payment_date as datetime (YYYY-MM-DD HH:MM:SS)
        $payment_date = $data['payment_date'] ?? date('Y-m-d');
        $payment_datetime = $payment_date . ' ' . date('H:i:s');
        $payment_method = 'adjustment'; // All adjustments use 'adjustment' as payment method

        $stmt->execute([
            $data['citation_id'],
            $data['receipt_number'] ?? null,
            $data['amount'],
            $payment_method,
            $payment_datetime,
            $data['admin_user_id'], // Use admin as collected_by for adjustments
            'completed', // Mark as completed immediately
            $data['adjustment_type'],
            $data['reason'],
            $data['admin_user_id'],
            $data['original_payment_date'] ?? $payment_date
        ]);

        $payment_id = $pdo->lastInsertId();

        // Update citation status
        $stmt = $pdo->prepare("
            UPDATE citations
            SET status = ?, updated_at = NOW()
            WHERE citation_id = ?
        ");
        $stmt->execute([$type_config['target_status'], $data['citation_id']]);

        // Log the adjustment
        $stmt = $pdo->prepare("
            INSERT INTO payment_adjustments_log (
                payment_id,
                citation_id,
                admin_user_id,
                adjustment_type,
                reason,
                old_status,
                new_status,
                amount,
                or_number,
                original_payment_date,
                ip_address,
                user_agent
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $payment_id,
            $data['citation_id'],
            $data['admin_user_id'],
            $data['adjustment_type'],
            $data['reason'],
            $old_status,
            $type_config['target_status'],
            $data['amount'],
            $data['or_number'] ?? null,
            $data['original_payment_date'] ?? $payment_date,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);

        // Commit transaction
        $pdo->commit();

        return [
            'success' => true,
            'message' => 'Payment adjustment created successfully',
            'payment_id' => $payment_id
        ];

    } catch (Exception $e) {
        // Rollback on error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log("Error creating payment adjustment: " . $e->getMessage());
        return [
            'success' => false,
            'message' => $e->getMessage(),
            'payment_id' => null
        ];
    }
}

/**
 * Get recent adjustments for display
 *
 * @param PDO $pdo Database connection
 * @param int $limit Number of records to retrieve (default: 10)
 * @return array Array of adjustment records
 */
function get_recent_adjustments($pdo, $limit = 10) {
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM vw_payment_adjustments
            ORDER BY created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching recent adjustments: " . $e->getMessage());
        return [];
    }
}

/**
 * Send email notification for adjustment
 *
 * @param int $payment_id Payment ID
 * @param PDO $pdo Database connection
 * @return bool True if email sent successfully
 */
function send_adjustment_notification($payment_id, $pdo) {
    try {
        // Get adjustment details
        $stmt = $pdo->prepare("
            SELECT * FROM vw_payment_adjustments
            WHERE payment_id = ?
            LIMIT 1
        ");
        $stmt->execute([$payment_id]);
        $adjustment = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$adjustment) {
            return false;
        }

        // Get admin email
        $stmt = $pdo->prepare("
            SELECT email FROM users WHERE role = 'admin'
        ");
        $stmt->execute();
        $admin_emails = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($admin_emails)) {
            return false;
        }

        // Prepare email
        $subject = "Payment Adjustment Made - Ticket #{$adjustment['ticket_number']}";

        $message = "
Dear Admin,

A payment adjustment has been recorded:

Ticket Number: {$adjustment['ticket_number']}
Driver: {$adjustment['driver_name']}
License: {$adjustment['license_number']}

Adjustment Details:
- Type: {$adjustment['adjustment_type']}
- Amount: ₱" . number_format($adjustment['amount'], 2) . "
- OR Number: " . ($adjustment['or_number'] ?? 'N/A') . "
- Payment Date: {$adjustment['original_payment_date']}
- Status Change: {$adjustment['old_status']} → {$adjustment['new_status']}

Adjusted By: {$adjustment['admin_full_name']} ({$adjustment['admin_username']})
Reason: {$adjustment['reason']}

IP Address: {$adjustment['ip_address']}
Timestamp: {$adjustment['created_at']}

View full details in the B-TRACS system.

---
This is an automated notification from B-TRACS (Baggao Traffic Citation System)
        ";

        $headers = "From: no-reply@tmg-btracs.local\r\n";
        $headers .= "Reply-To: no-reply@tmg-btracs.local\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();

        // Send to all admins
        foreach ($admin_emails as $email) {
            mail($email, $subject, $message, $headers);
        }

        return true;

    } catch (Exception $e) {
        error_log("Error sending adjustment notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Check rate limit for adjustments
 *
 * @param int $admin_user_id Admin user ID
 * @param PDO $pdo Database connection
 * @param int $max_per_hour Maximum adjustments per hour (default: 10)
 * @return array ['allowed' => bool, 'message' => string, 'count' => int]
 */
function check_adjustment_rate_limit($admin_user_id, $pdo, $max_per_hour = 10) {
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM payment_adjustments_log
            WHERE admin_user_id = ?
            AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");
        $stmt->execute([$admin_user_id]);
        $count = $stmt->fetchColumn();

        if ($count >= $max_per_hour) {
            return [
                'allowed' => false,
                'message' => "Rate limit exceeded. Maximum $max_per_hour adjustments per hour.",
                'count' => $count
            ];
        }

        return [
            'allowed' => true,
            'message' => 'Within rate limit',
            'count' => $count
        ];

    } catch (PDOException $e) {
        error_log("Error checking rate limit: " . $e->getMessage());
        return [
            'allowed' => false,
            'message' => 'Error checking rate limit',
            'count' => 0
        ];
    }
}
