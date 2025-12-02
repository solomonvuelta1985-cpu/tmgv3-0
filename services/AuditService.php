<?php
/**
 * AuditService Class
 *
 * Centralized service for logging audit trail entries
 * Tracks all critical actions and changes across the system
 *
 * @package TrafficCitationSystem
 * @subpackage Services
 */

class AuditService {
    private $pdo;
    private $tableName = 'audit_log';
    private $orAuditTable = 'audit_logs'; // New OR-specific audit table

    /**
     * Constructor
     *
     * @param PDO $pdo Database connection
     */
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Log an audit entry
     *
     * @param string $action Action performed (e.g., 'create', 'update', 'delete', 'status_change')
     * @param string $tableName Table name affected
     * @param int|null $recordId Record ID affected
     * @param array|null $oldValues Old values (for updates)
     * @param array|null $newValues New values (for creates/updates)
     * @param int|null $userId User ID who performed the action
     * @return bool Success status
     */
    public function log($action, $tableName, $recordId = null, $oldValues = null, $newValues = null, $userId = null) {
        try {
            // Get user ID from session if not provided
            if ($userId === null && isset($_SESSION['user_id'])) {
                $userId = $_SESSION['user_id'];
            }

            // Get IP address
            $ipAddress = $this->getClientIp();

            // Get user agent
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

            // Prepare SQL
            $sql = "INSERT INTO {$this->tableName} (
                user_id, action, table_name, record_id,
                old_values, new_values, ip_address, user_agent
            ) VALUES (
                :user_id, :action, :table_name, :record_id,
                :old_values, :new_values, :ip_address, :user_agent
            )";

            $stmt = $this->pdo->prepare($sql);

            return $stmt->execute([
                ':user_id' => $userId,
                ':action' => $action,
                ':table_name' => $tableName,
                ':record_id' => $recordId,
                ':old_values' => $oldValues ? json_encode($oldValues) : null,
                ':new_values' => $newValues ? json_encode($newValues) : null,
                ':ip_address' => $ipAddress,
                ':user_agent' => $userAgent
            ]);

        } catch (Exception $e) {
            // Log error but don't fail the main operation
            error_log("Audit logging failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Log citation status change specifically
     *
     * @param int $citationId Citation ID
     * @param string $oldStatus Old status
     * @param string $newStatus New status
     * @param int|null $userId User ID who made the change
     * @param string|null $reason Reason for status change
     * @return bool Success status
     */
    public function logCitationStatusChange($citationId, $oldStatus, $newStatus, $userId = null, $reason = null) {
        $oldValues = [
            'status' => $oldStatus
        ];

        $newValues = [
            'status' => $newStatus
        ];

        if ($reason) {
            $newValues['reason'] = $reason;
        }

        return $this->log(
            'status_change',
            'citations',
            $citationId,
            $oldValues,
            $newValues,
            $userId
        );
    }

    /**
     * Log payment action
     *
     * @param int $paymentId Payment ID
     * @param string $action Action performed
     * @param array|null $oldValues Old values
     * @param array|null $newValues New values
     * @param int|null $userId User ID
     * @return bool Success status
     */
    public function logPaymentAction($paymentId, $action, $oldValues = null, $newValues = null, $userId = null) {
        return $this->log(
            $action,
            'payments',
            $paymentId,
            $oldValues,
            $newValues,
            $userId
        );
    }

    /**
     * Get audit history for a specific record
     *
     * @param string $tableName Table name
     * @param int $recordId Record ID
     * @param int $limit Results limit
     * @return array Audit history
     */
    public function getAuditHistory($tableName, $recordId, $limit = 50) {
        $sql = "SELECT
                    a.*,
                    u.username,
                    u.full_name as user_name
                FROM {$this->tableName} a
                LEFT JOIN users u ON a.user_id = u.user_id
                WHERE a.table_name = :table_name
                AND a.record_id = :record_id
                ORDER BY a.created_at DESC
                LIMIT :limit";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':table_name', $tableName, PDO::PARAM_STR);
        $stmt->bindValue(':record_id', $recordId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get citation status change history
     *
     * @param int $citationId Citation ID
     * @return array Status change history
     */
    public function getCitationStatusHistory($citationId) {
        $sql = "SELECT
                    a.*,
                    u.username,
                    u.full_name as user_name
                FROM {$this->tableName} a
                LEFT JOIN users u ON a.user_id = u.user_id
                WHERE a.table_name = 'citations'
                AND a.record_id = :citation_id
                AND a.action = 'status_change'
                ORDER BY a.created_at DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':citation_id' => $citationId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get recent audit activity
     *
     * @param int $limit Results limit
     * @param array $filters Optional filters (action, table_name, user_id)
     * @return array Recent activity
     */
    public function getRecentActivity($limit = 100, $filters = []) {
        $where = [];
        $params = [];

        if (!empty($filters['action'])) {
            $where[] = "a.action = :action";
            $params[':action'] = $filters['action'];
        }

        if (!empty($filters['table_name'])) {
            $where[] = "a.table_name = :table_name";
            $params[':table_name'] = $filters['table_name'];
        }

        if (!empty($filters['user_id'])) {
            $where[] = "a.user_id = :user_id";
            $params[':user_id'] = $filters['user_id'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = "a.created_at >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = "a.created_at <= :date_to";
            $params[':date_to'] = $filters['date_to'];
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "SELECT
                    a.*,
                    u.username,
                    u.full_name as user_name
                FROM {$this->tableName} a
                LEFT JOIN users u ON a.user_id = u.user_id
                {$whereClause}
                ORDER BY a.created_at DESC
                LIMIT :limit";

        $stmt = $this->pdo->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get client IP address
     *
     * @return string|null IP address
     */
    private function getClientIp() {
        $ipKeys = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];

        foreach ($ipKeys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);

                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? null;
    }

    /**
     * Export audit logs to CSV format
     *
     * @param array $filters Optional filters
     * @param int $limit Results limit
     * @return string CSV content
     */
    public function exportToCSV($filters = [], $limit = 1000) {
        $logs = $this->getRecentActivity($limit, $filters);

        $csv = [];

        // CSV Headers
        $csv[] = [
            'Date/Time',
            'User',
            'Action',
            'Table',
            'Record ID',
            'Old Values',
            'New Values',
            'IP Address',
            'User Agent'
        ];

        // CSV Rows
        foreach ($logs as $log) {
            $csv[] = [
                $log['created_at'],
                $log['user_name'] ?: $log['username'] ?: 'System',
                $log['action'],
                $log['table_name'],
                $log['record_id'],
                $log['old_values'] ?: '',
                $log['new_values'] ?: '',
                $log['ip_address'] ?: '',
                $log['user_agent'] ?: ''
            ];
        }

        // Generate CSV string
        $output = fopen('php://temp', 'r+');
        foreach ($csv as $row) {
            fputcsv($output, $row);
        }
        rewind($output);
        $csvString = stream_get_contents($output);
        fclose($output);

        return $csvString;
    }

    /**
     * Get audit statistics
     *
     * @param array $filters Optional filters
     * @return array Statistics
     */
    public function getStatistics($filters = []) {
        $where = [];
        $params = [];

        if (!empty($filters['action'])) {
            $where[] = "action = :action";
            $params[':action'] = $filters['action'];
        }

        if (!empty($filters['table_name'])) {
            $where[] = "table_name = :table_name";
            $params[':table_name'] = $filters['table_name'];
        }

        if (!empty($filters['user_id'])) {
            $where[] = "user_id = :user_id";
            $params[':user_id'] = $filters['user_id'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = "created_at >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = "created_at <= :date_to";
            $params[':date_to'] = $filters['date_to'];
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "SELECT
                    COUNT(*) as total_actions,
                    COUNT(DISTINCT user_id) as unique_users,
                    COUNT(DISTINCT table_name) as tables_affected,
                    MIN(created_at) as first_action,
                    MAX(created_at) as last_action
                FROM {$this->tableName}
                {$whereClause}";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get activity breakdown by action type
     *
     * @param array $filters Optional filters
     * @return array Action breakdown
     */
    public function getActionBreakdown($filters = []) {
        $where = [];
        $params = [];

        if (!empty($filters['table_name'])) {
            $where[] = "table_name = :table_name";
            $params[':table_name'] = $filters['table_name'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = "created_at >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = "created_at <= :date_to";
            $params[':date_to'] = $filters['date_to'];
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "SELECT
                    action,
                    COUNT(*) as count
                FROM {$this->tableName}
                {$whereClause}
                GROUP BY action
                ORDER BY count DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get most active users
     *
     * @param int $limit Number of users to return
     * @param array $filters Optional filters
     * @return array Active users
     */
    public function getMostActiveUsers($limit = 10, $filters = []) {
        $where = [];
        $params = [];

        if (!empty($filters['date_from'])) {
            $where[] = "a.created_at >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = "a.created_at <= :date_to";
            $params[':date_to'] = $filters['date_to'];
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "SELECT
                    u.user_id,
                    u.username,
                    u.full_name,
                    COUNT(*) as action_count
                FROM {$this->tableName} a
                LEFT JOIN users u ON a.user_id = u.user_id
                {$whereClause}
                GROUP BY u.user_id
                ORDER BY action_count DESC
                LIMIT :limit";

        $stmt = $this->pdo->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Log OR/Payment audit entry to the specialized audit_logs table
     * Specifically designed for OR number tracking and BIR/COA compliance
     *
     * @param string $actionType Action type (payment_created, payment_cancelled, payment_voided, or_number_changed, payment_finalized)
     * @param string $entityType Entity type (payment, receipt, citation)
     * @param int $entityId Entity ID
     * @param array $data Audit data containing OR numbers, ticket info, amounts, etc.
     * @param int|null $userId User ID
     * @param string|null $reason Reason for the action
     * @return bool Success status
     */
    public function logOrAction($actionType, $entityType, $entityId, $data = [], $userId = null, $reason = null) {
        try {
            // Get user ID from session if not provided
            if ($userId === null && isset($_SESSION['user_id'])) {
                $userId = $_SESSION['user_id'];
            }

            // Get username
            $username = 'System';
            if ($userId && isset($_SESSION['username'])) {
                $username = $_SESSION['username'];
            }

            // Get IP address and user agent
            $ipAddress = $this->getClientIp();
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

            // Prepare SQL
            $sql = "INSERT INTO {$this->orAuditTable} (
                action_type,
                entity_type,
                entity_id,
                or_number_old,
                or_number_new,
                ticket_number,
                amount,
                payment_status_old,
                payment_status_new,
                user_id,
                username,
                reason,
                ip_address,
                user_agent,
                additional_data
            ) VALUES (
                :action_type,
                :entity_type,
                :entity_id,
                :or_number_old,
                :or_number_new,
                :ticket_number,
                :amount,
                :payment_status_old,
                :payment_status_new,
                :user_id,
                :username,
                :reason,
                :ip_address,
                :user_agent,
                :additional_data
            )";

            $stmt = $this->pdo->prepare($sql);

            return $stmt->execute([
                ':action_type' => $actionType,
                ':entity_type' => $entityType,
                ':entity_id' => $entityId,
                ':or_number_old' => $data['or_number_old'] ?? null,
                ':or_number_new' => $data['or_number_new'] ?? null,
                ':ticket_number' => $data['ticket_number'] ?? null,
                ':amount' => $data['amount'] ?? null,
                ':payment_status_old' => $data['payment_status_old'] ?? null,
                ':payment_status_new' => $data['payment_status_new'] ?? null,
                ':user_id' => $userId,
                ':username' => $username,
                ':reason' => $reason,
                ':ip_address' => $ipAddress,
                ':user_agent' => $userAgent,
                ':additional_data' => isset($data['additional']) ? json_encode($data['additional']) : null
            ]);

        } catch (Exception $e) {
            // Log error but don't fail the main operation
            error_log("OR audit logging failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get OR audit history for a specific payment or OR number
     *
     * @param string|null $orNumber OR number to search for
     * @param int|null $paymentId Payment ID to search for
     * @param int $limit Results limit
     * @return array Audit history
     */
    public function getOrAuditHistory($orNumber = null, $paymentId = null, $limit = 50) {
        $where = [];
        $params = [];

        if ($orNumber) {
            $where[] = "(or_number_old = :or_number OR or_number_new = :or_number)";
            $params[':or_number'] = $orNumber;
        }

        if ($paymentId) {
            $where[] = "entity_id = :entity_id AND entity_type = 'payment'";
            $params[':entity_id'] = $paymentId;
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "SELECT *
                FROM {$this->orAuditTable}
                {$whereClause}
                ORDER BY action_datetime DESC
                LIMIT :limit";

        $stmt = $this->pdo->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
