-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Dec 03, 2025 at 09:40 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `traffic_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `apprehending_officers`
--

CREATE TABLE `apprehending_officers` (
  `officer_id` int(11) NOT NULL,
  `officer_name` varchar(255) NOT NULL,
  `badge_number` varchar(50) DEFAULT NULL,
  `position` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `apprehending_officers`
--

INSERT INTO `apprehending_officers` (`officer_id`, `officer_name`, `badge_number`, `position`, `is_active`, `created_at`) VALUES
(1, 'RICHMOND', NULL, 'ENFORCER', 1, '2025-11-17 10:19:12'),
(2, 'PNP TALLANG', NULL, 'SGT', 1, '2025-11-24 01:25:13');

-- --------------------------------------------------------

--
-- Table structure for table `audit_log`
--

CREATE TABLE `audit_log` (
  `audit_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `table_name` varchar(50) NOT NULL,
  `record_id` int(11) DEFAULT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `audit_log`
--

INSERT INTO `audit_log` (`audit_id`, `user_id`, `action`, `table_name`, `record_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `created_at`) VALUES
(69, 1, 'finalized', 'payments', 46, '{\"status\":\"pending_print\",\"citation_status\":\"pending\"}', '{\"status\":\"completed\",\"citation_status\":\"paid\",\"finalized_by\":1}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-12-01 08:35:11'),
(70, 1, 'soft_delete', 'citations', 46, '{\"ticket_number\":\"06102\",\"status\":\"pending\",\"deleted_at\":null}', '{\"deleted_at\":\"2025-12-01 03:29:01\",\"deleted_by\":1,\"deletion_reason\":\"Deleted by admin\"}', '::1', NULL, '2025-12-01 10:29:01'),
(71, 1, 'finalized', 'payments', 47, '{\"status\":\"pending_print\",\"citation_status\":\"pending\"}', '{\"status\":\"completed\",\"citation_status\":\"paid\",\"finalized_by\":1}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-12-02 11:28:28');

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `log_id` int(11) NOT NULL,
  `action_type` varchar(50) NOT NULL COMMENT 'Type of action: payment_created, payment_cancelled, payment_voided, or_number_changed, payment_finalized',
  `entity_type` varchar(50) NOT NULL COMMENT 'Type of entity: payment, receipt, citation',
  `entity_id` int(11) NOT NULL COMMENT 'ID of the entity (payment_id, receipt_id, citation_id)',
  `or_number_old` varchar(50) DEFAULT NULL COMMENT 'Previous OR number (for changes)',
  `or_number_new` varchar(50) DEFAULT NULL COMMENT 'New OR number',
  `ticket_number` varchar(50) DEFAULT NULL COMMENT 'Citation ticket number',
  `amount` decimal(10,2) DEFAULT NULL COMMENT 'Payment amount',
  `payment_status_old` varchar(20) DEFAULT NULL COMMENT 'Previous payment status',
  `payment_status_new` varchar(20) DEFAULT NULL COMMENT 'New payment status',
  `user_id` int(11) NOT NULL COMMENT 'User who performed the action',
  `username` varchar(100) NOT NULL COMMENT 'Username (for quick reference)',
  `action_datetime` datetime NOT NULL DEFAULT current_timestamp() COMMENT 'When the action occurred',
  `reason` text DEFAULT NULL COMMENT 'Reason for the action',
  `ip_address` varchar(45) DEFAULT NULL COMMENT 'IP address of user',
  `user_agent` text DEFAULT NULL COMMENT 'Browser/device information',
  `additional_data` text DEFAULT NULL COMMENT 'JSON formatted additional data'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Audit trail for payments and OR numbers';

-- --------------------------------------------------------

--
-- Table structure for table `citations`
--

CREATE TABLE `citations` (
  `citation_id` int(11) NOT NULL,
  `ticket_number` varchar(50) NOT NULL,
  `driver_id` int(11) DEFAULT NULL,
  `last_name` varchar(100) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_initial` varchar(10) DEFAULT NULL,
  `suffix` varchar(20) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `age` int(11) DEFAULT NULL,
  `zone` varchar(50) DEFAULT NULL,
  `barangay` varchar(100) NOT NULL,
  `municipality` varchar(100) NOT NULL DEFAULT 'Baggao',
  `province` varchar(100) NOT NULL DEFAULT 'Cagayan',
  `license_number` varchar(50) DEFAULT NULL,
  `license_type` varchar(50) DEFAULT NULL,
  `plate_mv_engine_chassis_no` varchar(100) NOT NULL,
  `vehicle_description` text DEFAULT NULL,
  `apprehension_datetime` datetime NOT NULL,
  `place_of_apprehension` varchar(255) NOT NULL,
  `apprehension_officer` varchar(255) NOT NULL,
  `remarks` text DEFAULT NULL,
  `status` enum('pending','paid','contested','dismissed','void') NOT NULL DEFAULT 'pending',
  `payment_date` datetime DEFAULT NULL,
  `total_fine` decimal(10,2) DEFAULT 0.00,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL COMMENT 'Soft delete timestamp',
  `deleted_by` int(11) DEFAULT NULL COMMENT 'User who deleted the citation',
  `deletion_reason` text DEFAULT NULL COMMENT 'Reason for deletion'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `citations`
--

INSERT INTO `citations` (`citation_id`, `ticket_number`, `driver_id`, `last_name`, `first_name`, `middle_initial`, `suffix`, `date_of_birth`, `age`, `zone`, `barangay`, `municipality`, `province`, `license_number`, `license_type`, `plate_mv_engine_chassis_no`, `vehicle_description`, `apprehension_datetime`, `place_of_apprehension`, `apprehension_officer`, `remarks`, `status`, `payment_date`, `total_fine`, `created_at`, `updated_at`, `created_by`, `deleted_at`, `deleted_by`, `deletion_reason`) VALUES
(45, '06101', 10, 'LAURETA', 'VLADIMIR', NULL, NULL, '1999-12-12', 25, NULL, 'San Jose', 'Baggao', 'Cagayan', NULL, 'nonProf', 'SAMPLE', 'ADV', '2025-12-01 08:22:00', 'SAN JOSE', 'PNP TALLANG', NULL, 'paid', '2025-12-01 01:35:01', 150.00, '2025-12-01 08:22:13', '2025-12-01 08:35:11', NULL, NULL, NULL, NULL),
(46, '06102', 11, 'Rosete', 'Richmond', NULL, NULL, '1999-10-17', 26, NULL, 'Bitag Grande', 'Baggao', 'Cagayan', NULL, 'nonProf', '4564564', 'KJHGFDSA', '2025-12-01 10:21:00', 'SAN JOSE', 'PNP TALLANG', NULL, 'paid', '2025-12-02 04:28:12', 150.00, '2025-12-01 10:21:56', '2025-12-02 11:28:28', NULL, '2025-12-01 10:29:01', 1, 'Deleted by admin'),
(47, '06103', 12, 'GUZMAN', 'BONG', 'S', '', '1995-02-10', 30, '05', 'Canagatan', 'Baggao', 'Cagayan', '', '', 'EMAXCJ3DONA144811', 'XRM', '2025-09-17 08:25:00', 'POBLACION', 'PNP TALLANG', '', 'contested', NULL, 150.00, '2025-12-01 13:33:16', '2025-12-02 13:19:44', NULL, NULL, NULL, NULL),
(48, '06104', 13, 'ROSETE', 'RICHMOND', '', '', '1999-10-17', 26, '1', 'Asassi', 'Baggao', 'Cagayan', '', '', '5JK567', 'RIDER 150', '2025-12-03 11:04:00', 'POBLACION', 'PNP TALLANG', '', 'pending', NULL, 150.00, '2025-12-03 11:04:58', '2025-12-03 14:16:31', NULL, NULL, NULL, NULL),
(49, '06105', 14, 'test', 'test123', 'G', NULL, '1998-10-17', 27, '1', 'Asassi', 'Baggao', 'Cagayan', NULL, 'nonProf', '5JK567', 'RIDER 150', '2025-12-03 14:53:00', 'POBLACION', 'RICHMOND', NULL, 'pending', NULL, 150.00, '2025-12-03 14:54:35', '2025-12-03 14:54:35', NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `citation_vehicles`
--

CREATE TABLE `citation_vehicles` (
  `vehicle_id` int(11) NOT NULL,
  `citation_id` int(11) NOT NULL,
  `vehicle_type` varchar(100) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `citation_vehicles`
--

INSERT INTO `citation_vehicles` (`vehicle_id`, `citation_id`, `vehicle_type`, `created_at`) VALUES
(54, 45, 'Motorcycle', '2025-12-01 08:22:13'),
(55, 46, 'Motorcycle', '2025-12-01 10:21:56'),
(57, 47, 'Motorcycle', '2025-12-02 13:19:44'),
(59, 48, 'Motorcycle', '2025-12-03 14:16:31'),
(60, 49, 'Motorcycle', '2025-12-03 14:54:35');

-- --------------------------------------------------------

--
-- Table structure for table `drivers`
--

CREATE TABLE `drivers` (
  `driver_id` int(11) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_initial` varchar(10) DEFAULT NULL,
  `suffix` varchar(20) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `age` int(11) DEFAULT NULL,
  `zone` varchar(50) DEFAULT NULL,
  `barangay` varchar(100) NOT NULL,
  `municipality` varchar(100) NOT NULL DEFAULT 'Baggao',
  `province` varchar(100) NOT NULL DEFAULT 'Cagayan',
  `license_number` varchar(50) DEFAULT NULL,
  `license_type` varchar(50) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `drivers`
--

INSERT INTO `drivers` (`driver_id`, `last_name`, `first_name`, `middle_initial`, `suffix`, `date_of_birth`, `age`, `zone`, `barangay`, `municipality`, `province`, `license_number`, `license_type`, `created_at`, `updated_at`) VALUES
(10, 'LAURETA', 'VLADIMIR', NULL, NULL, '1999-12-12', 25, NULL, 'San Jose', 'Baggao', 'Cagayan', NULL, 'nonProf', '2025-12-01 08:22:13', NULL),
(11, 'Rosete', 'Richmond', NULL, NULL, '1999-10-17', 26, NULL, 'Bitag Grande', 'Baggao', 'Cagayan', NULL, 'nonProf', '2025-12-01 10:21:56', NULL),
(12, 'GUZMAN', 'BONG', 'S', NULL, '1995-02-10', 30, '05', 'Canagatan', 'Baggao', 'Cagayan', NULL, 'nonProf', '2025-12-01 13:33:16', NULL),
(13, 'Mercado', 'Rogelio', 'G', NULL, '1999-10-17', 26, '1', 'Agaman', 'Baggao', 'Cagayan', NULL, 'nonProf', '2025-12-03 11:04:58', NULL),
(14, 'test', 'test123', 'G', NULL, '1998-10-17', 27, '1', 'Asassi', 'Baggao', 'Cagayan', NULL, 'nonProf', '2025-12-03 14:54:35', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `payment_id` int(11) NOT NULL,
  `citation_id` int(11) NOT NULL,
  `amount_paid` decimal(10,2) NOT NULL,
  `payment_method` enum('cash','check','online','gcash','paymaya','bank_transfer','money_order') NOT NULL DEFAULT 'cash',
  `payment_date` datetime NOT NULL,
  `reference_number` varchar(100) DEFAULT NULL COMMENT 'Check number, transaction ID, etc.',
  `receipt_number` varchar(50) NOT NULL COMMENT 'Official Receipt number',
  `collected_by` int(11) NOT NULL COMMENT 'User ID of cashier/collector',
  `check_number` varchar(50) DEFAULT NULL,
  `check_bank` varchar(100) DEFAULT NULL,
  `check_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('completed','pending','pending_print','failed','refunded','cancelled','voided') DEFAULT 'completed' COMMENT 'Payment status',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores payment transactions for traffic citations';

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`payment_id`, `citation_id`, `amount_paid`, `payment_method`, `payment_date`, `reference_number`, `receipt_number`, `collected_by`, `check_number`, `check_bank`, `check_date`, `notes`, `status`, `created_at`, `updated_at`) VALUES
(46, 45, 150.00, 'cash', '2025-12-01 01:35:01', NULL, 'CGVM12334568', 1, NULL, NULL, NULL, '', 'completed', '2025-12-01 08:35:01', '2025-12-01 08:35:11'),
(47, 46, 150.00, 'cash', '2025-12-02 04:28:12', NULL, 'CGVM12334893', 1, NULL, NULL, NULL, '', 'completed', '2025-12-02 11:28:12', '2025-12-02 11:28:28'),
(48, 48, 150.00, 'cash', '2025-12-03 14:17:02', NULL, 'CGVM12334580', 1, NULL, NULL, NULL, '', 'pending_print', '2025-12-03 14:17:02', '2025-12-03 14:17:02'),
(49, 49, 150.00, 'cash', '2025-12-03 14:54:51', NULL, 'CGVM12334581', 1, NULL, NULL, NULL, '', 'pending_print', '2025-12-03 14:54:51', '2025-12-03 14:54:51');

--
-- Triggers `payments`
--
DELIMITER $$
CREATE TRIGGER `after_payment_insert` AFTER INSERT ON `payments` FOR EACH ROW BEGIN
    DECLARE current_citation_status VARCHAR(20);
    DECLARE update_failed BOOLEAN DEFAULT FALSE;
    IF NEW.status = 'completed' THEN
        SELECT status INTO current_citation_status
        FROM citations
        WHERE citation_id = NEW.citation_id;
        UPDATE citations
        SET
            status = 'paid',
            payment_date = NEW.payment_date,
            updated_at = CURRENT_TIMESTAMP
        WHERE citation_id = NEW.citation_id;
        SELECT status INTO current_citation_status
        FROM citations
        WHERE citation_id = NEW.citation_id;
        IF current_citation_status != 'paid' THEN
            INSERT INTO trigger_error_log (
                trigger_name,
                error_message,
                error_details,
                citation_id,
                payment_id
            ) VALUES (
                'after_payment_insert',
                'CRITICAL: Failed to update citation status to paid',
                JSON_OBJECT(
                    'citation_id', NEW.citation_id,
                    'citation_status_before', current_citation_status,
                    'citation_status_after', current_citation_status,
                    'payment_id', NEW.payment_id,
                    'payment_status', NEW.status,
                    'receipt_number', NEW.receipt_number
                ),
                NEW.citation_id,
                NEW.payment_id
            );
        END IF;
    END IF;
    INSERT INTO payment_audit (
        payment_id,
        action,
        new_values,
        performed_by,
        ip_address,
        notes
    ) VALUES (
        NEW.payment_id,
        'created',
        JSON_OBJECT(
            'amount_paid', NEW.amount_paid,
            'payment_method', NEW.payment_method,
            'receipt_number', NEW.receipt_number,
            'status', NEW.status
        ),
        NEW.collected_by,
        @user_ip,
        CONCAT('Payment recorded for citation ID: ', NEW.citation_id)
    );
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `after_payment_update` AFTER UPDATE ON `payments` FOR EACH ROW BEGIN
    DECLARE current_citation_status VARCHAR(20);
    INSERT INTO payment_audit (
        payment_id,
        action,
        old_values,
        new_values,
        performed_by,
        ip_address,
        notes
    ) VALUES (
        NEW.payment_id,
        'updated',
        JSON_OBJECT(
            'status', OLD.status,
            'amount_paid', OLD.amount_paid,
            'payment_method', OLD.payment_method,
            'notes', OLD.notes
        ),
        JSON_OBJECT(
            'status', NEW.status,
            'amount_paid', NEW.amount_paid,
            'payment_method', NEW.payment_method,
            'notes', NEW.notes
        ),
        NEW.collected_by,
        @user_ip,
        'Payment updated'
    );
    IF NEW.status IN ('refunded', 'cancelled', 'voided') AND OLD.status = 'completed' THEN
        UPDATE citations
        SET
            status = 'pending',
            payment_date = NULL,
            updated_at = CURRENT_TIMESTAMP
        WHERE citation_id = NEW.citation_id;
        SELECT status INTO current_citation_status
        FROM citations WHERE citation_id = NEW.citation_id;
        IF current_citation_status != 'pending' THEN
            INSERT INTO trigger_error_log (
                trigger_name,
                error_message,
                error_details,
                citation_id,
                payment_id
            ) VALUES (
                'after_payment_update',
                'CRITICAL: Failed to revert citation to pending after payment cancellation',
                JSON_OBJECT(
                    'citation_id', NEW.citation_id,
                    'payment_old_status', OLD.status,
                    'payment_new_status', NEW.status,
                    'citation_status', current_citation_status
                ),
                NEW.citation_id,
                NEW.payment_id
            );
        END IF;
    END IF;
    IF NEW.status = 'completed' AND OLD.status != 'completed' THEN
        UPDATE citations
        SET
            status = 'paid',
            payment_date = NEW.payment_date,
            updated_at = CURRENT_TIMESTAMP
        WHERE citation_id = NEW.citation_id;
        SELECT status INTO current_citation_status
        FROM citations WHERE citation_id = NEW.citation_id;
        IF current_citation_status != 'paid' THEN
            INSERT INTO trigger_error_log (
                trigger_name,
                error_message,
                error_details,
                citation_id,
                payment_id
            ) VALUES (
                'after_payment_update',
                'CRITICAL: Failed to update citation to paid after payment completion',
                JSON_OBJECT(
                    'citation_id', NEW.citation_id,
                    'payment_old_status', OLD.status,
                    'payment_new_status', NEW.status,
                    'citation_status', current_citation_status
                ),
                NEW.citation_id,
                NEW.payment_id
            );
        END IF;
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `before_payment_status_change` BEFORE INSERT ON `payments` FOR EACH ROW BEGIN
    DECLARE citation_status VARCHAR(20);
    DECLARE error_msg TEXT;
    SELECT status INTO citation_status
    FROM citations
    WHERE citation_id = NEW.citation_id;
    IF NEW.status = 'completed' AND citation_status IN ('void', 'dismissed') THEN
        SET error_msg = CONCAT('Cannot create completed payment: Citation #', NEW.citation_id, ' is ', citation_status);
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = error_msg;
    END IF;
    IF NEW.status = 'completed' AND citation_status = 'pending' THEN
        INSERT IGNORE INTO trigger_error_log (
            trigger_name,
            error_message,
            error_details,
            citation_id,
            payment_id
        ) VALUES (
            'before_payment_status_change',
            'WARNING: Creating completed payment on pending citation',
            JSON_OBJECT(
                'citation_id', NEW.citation_id,
                'citation_status', citation_status,
                'payment_status', NEW.status,
                'receipt_number', NEW.receipt_number
            ),
            NEW.citation_id,
            NULL
        );
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `payment_audit`
--

CREATE TABLE `payment_audit` (
  `audit_id` int(11) NOT NULL,
  `payment_id` int(11) NOT NULL,
  `action` enum('created','updated','refunded','cancelled','voided') NOT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `performed_by` int(11) NOT NULL,
  `performed_at` datetime DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Audit trail for payment transactions';

--
-- Dumping data for table `payment_audit`
--

INSERT INTO `payment_audit` (`audit_id`, `payment_id`, `action`, `old_values`, `new_values`, `performed_by`, `performed_at`, `ip_address`, `user_agent`, `notes`) VALUES
(1, 46, 'created', NULL, '{\"amount_paid\": 150.00, \"payment_method\": \"cash\", \"receipt_number\": \"CGVM12334568\", \"status\": \"pending_print\"}', 1, '2025-12-01 08:35:01', NULL, NULL, 'Payment recorded for citation ID: 45'),
(2, 46, 'updated', '{\"status\": \"pending_print\", \"amount_paid\": 150.00, \"payment_method\": \"cash\", \"notes\": \"\"}', '{\"status\": \"completed\", \"amount_paid\": 150.00, \"payment_method\": \"cash\", \"notes\": \"\"}', 1, '2025-12-01 08:35:11', NULL, NULL, 'Payment updated'),
(3, 47, 'created', NULL, '{\"amount_paid\": 150.00, \"payment_method\": \"cash\", \"receipt_number\": \"CGVM12334893\", \"status\": \"pending_print\"}', 1, '2025-12-02 11:28:12', NULL, NULL, 'Payment recorded for citation ID: 46'),
(4, 47, 'updated', '{\"status\": \"pending_print\", \"amount_paid\": 150.00, \"payment_method\": \"cash\", \"notes\": \"\"}', '{\"status\": \"completed\", \"amount_paid\": 150.00, \"payment_method\": \"cash\", \"notes\": \"\"}', 1, '2025-12-02 11:28:28', NULL, NULL, 'Payment updated'),
(5, 48, 'created', NULL, '{\"amount_paid\": 150.00, \"payment_method\": \"cash\", \"receipt_number\": \"CGVM12334580\", \"status\": \"pending_print\"}', 1, '2025-12-03 14:17:02', NULL, NULL, 'Payment recorded for citation ID: 48'),
(6, 49, 'created', NULL, '{\"amount_paid\": 150.00, \"payment_method\": \"cash\", \"receipt_number\": \"CGVM12334581\", \"status\": \"pending_print\"}', 1, '2025-12-03 14:54:51', NULL, NULL, 'Payment recorded for citation ID: 49');

-- --------------------------------------------------------

--
-- Table structure for table `receipts`
--

CREATE TABLE `receipts` (
  `receipt_id` int(11) NOT NULL,
  `payment_id` int(11) NOT NULL,
  `receipt_number` varchar(50) NOT NULL,
  `generated_at` datetime DEFAULT current_timestamp(),
  `generated_by` int(11) NOT NULL COMMENT 'User ID who generated the receipt',
  `printed_at` datetime DEFAULT NULL,
  `print_count` int(11) DEFAULT 0,
  `last_printed_by` int(11) DEFAULT NULL,
  `last_printed_at` datetime DEFAULT NULL,
  `status` enum('active','cancelled','void') DEFAULT 'active',
  `cancellation_reason` text DEFAULT NULL,
  `cancelled_by` int(11) DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tracks receipt generation, printing, and cancellation';

--
-- Dumping data for table `receipts`
--

INSERT INTO `receipts` (`receipt_id`, `payment_id`, `receipt_number`, `generated_at`, `generated_by`, `printed_at`, `print_count`, `last_printed_by`, `last_printed_at`, `status`, `cancellation_reason`, `cancelled_by`, `cancelled_at`) VALUES
(46, 46, 'CGVM12334568', '2025-12-01 08:35:01', 1, '2025-12-01 08:35:11', 2, 1, '2025-12-01 13:22:29', 'active', NULL, NULL, NULL),
(47, 47, 'CGVM12334893', '2025-12-02 11:28:12', 1, '2025-12-02 11:28:28', 2, 1, '2025-12-02 13:25:52', 'active', NULL, NULL, NULL),
(48, 48, 'CGVM12334580', '2025-12-03 14:17:02', 1, NULL, 0, NULL, NULL, 'active', NULL, NULL, NULL),
(49, 49, 'CGVM12334581', '2025-12-03 14:54:51', 1, NULL, 0, NULL, NULL, 'active', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `receipt_sequence`
--

CREATE TABLE `receipt_sequence` (
  `id` int(11) NOT NULL DEFAULT 1,
  `current_year` int(11) NOT NULL,
  `current_number` int(11) NOT NULL DEFAULT 0,
  `last_updated` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `receipt_sequence`
--

INSERT INTO `receipt_sequence` (`id`, `current_year`, `current_number`, `last_updated`) VALUES
(1, 2025, 7, '2025-11-26 08:37:32');

-- --------------------------------------------------------

--
-- Table structure for table `trigger_error_log`
--

CREATE TABLE `trigger_error_log` (
  `log_id` int(11) NOT NULL,
  `trigger_name` varchar(100) NOT NULL,
  `error_message` text NOT NULL,
  `error_details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`error_details`)),
  `citation_id` int(11) DEFAULT NULL,
  `payment_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Logs trigger execution errors for debugging';

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `role` enum('user','admin','enforcer','cashier') NOT NULL DEFAULT 'user' COMMENT 'User role: user=read-only, enforcer=field officer, cashier=payment processor, admin=full access',
  `status` enum('active','inactive','suspended') NOT NULL DEFAULT 'active',
  `last_login` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `password_hash`, `full_name`, `email`, `role`, `status`, `last_login`, `created_at`, `updated_at`, `created_by`) VALUES
(1, 'admin', '$2y$10$mmjBnDB0cU4krnO/uPuwF.Qs8Cja0Md.lHAcf2pGqFx3K0k/4nz8.', 'System Administrator', 'admin@traffic.gov', 'admin', 'active', '2025-12-03 14:49:10', '2025-11-17 13:23:47', '2025-12-03 14:49:10', NULL),
(2, 'rich', '$2y$10$t4YFwv7NpVvZcH7jlFNI5uYble6KlFP2Wx8vBw3wq7YcKMVe0q7Rq', 'richmond', 'richmondrosete19@gmail.com', 'cashier', 'active', '2025-11-28 10:51:20', '2025-11-25 14:12:51', '2025-11-28 10:51:20', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `violations`
--

CREATE TABLE `violations` (
  `violation_id` int(11) NOT NULL,
  `citation_id` int(11) NOT NULL,
  `violation_type_id` int(11) NOT NULL,
  `offense_count` int(11) NOT NULL DEFAULT 1,
  `fine_amount` decimal(10,2) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `violations`
--

INSERT INTO `violations` (`violation_id`, `citation_id`, `violation_type_id`, `offense_count`, `fine_amount`, `created_at`) VALUES
(74, 45, 3, 1, 150.00, '2025-12-01 08:22:13'),
(75, 46, 3, 1, 150.00, '2025-12-01 10:21:56'),
(77, 47, 8, 1, 150.00, '2025-12-02 13:19:44'),
(79, 48, 3, 1, 150.00, '2025-12-03 14:16:31'),
(80, 49, 3, 1, 150.00, '2025-12-03 14:54:35');

--
-- Triggers `violations`
--
DELIMITER $$
CREATE TRIGGER `after_violation_insert_update_total` AFTER INSERT ON `violations` FOR EACH ROW BEGIN
    -- Update total fine on citation (different table, so this is OK)
    UPDATE citations
    SET total_fine = (SELECT COALESCE(SUM(fine_amount), 0) FROM violations WHERE citation_id = NEW.citation_id)
    WHERE citation_id = NEW.citation_id;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `before_violation_insert` BEFORE INSERT ON `violations` FOR EACH ROW BEGIN
    DECLARE fine DECIMAL(10,2);

    -- Get the appropriate fine based on offense count
    SELECT
        CASE
            WHEN NEW.offense_count = 1 THEN fine_amount_1
            WHEN NEW.offense_count = 2 THEN fine_amount_2
            ELSE fine_amount_3
        END INTO fine
    FROM violation_types
    WHERE violation_type_id = NEW.violation_type_id;

    -- Set the fine amount before insert (no UPDATE needed)
    SET NEW.fine_amount = fine;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `violation_types`
--

CREATE TABLE `violation_types` (
  `violation_type_id` int(11) NOT NULL,
  `violation_type` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `fine_amount_1` decimal(10,2) NOT NULL DEFAULT 500.00,
  `fine_amount_2` decimal(10,2) NOT NULL DEFAULT 1000.00,
  `fine_amount_3` decimal(10,2) NOT NULL DEFAULT 1500.00,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `violation_types`
--

INSERT INTO `violation_types` (`violation_type_id`, `violation_type`, `description`, `fine_amount_1`, `fine_amount_2`, `fine_amount_3`, `is_active`, `created_at`) VALUES
(2, 'NO HELMET (DRIVER)', NULL, 150.00, 150.00, 150.00, 1, '2025-11-17 18:07:43'),
(3, 'NO HELMET (BACKRIDER)', NULL, 150.00, 150.00, 150.00, 1, '2025-11-17 18:07:43'),
(5, 'NO / EXPIRED VEHICLE REGISTRATION', NULL, 2500.00, 2500.00, 2500.00, 1, '2025-11-17 18:07:43'),
(6, 'NO / DEFECTIVE PARTS & ACCESSORIES', NULL, 500.00, 500.00, 500.00, 1, '2025-11-17 18:07:43'),
(7, 'RECKLESS / ARROGANT DRIVING', NULL, 500.00, 750.00, 1000.00, 1, '2025-11-17 18:07:43'),
(8, 'DISREGARDING TRAFFIC SIGN', NULL, 150.00, 150.00, 150.00, 1, '2025-11-17 18:07:43'),
(9, 'ILLEGAL MODIFICATION', NULL, 500.00, 500.00, 500.00, 1, '2025-11-17 18:07:43'),
(10, 'PASSENGER ON TOP OF THE VEHICLE', NULL, 150.00, 150.00, 150.00, 1, '2025-11-17 18:07:43'),
(11, 'NOISY MUFFLER (98DB ABOVE)', NULL, 2500.00, 2500.00, 2500.00, 1, '2025-11-17 18:07:43'),
(12, 'NO MUFFLER ATTACHED', NULL, 2500.00, 2500.00, 2500.00, 1, '2025-11-17 18:07:43'),
(13, 'ILLEGAL PARKING', NULL, 200.00, 500.00, 2500.00, 1, '2025-11-17 18:07:43'),
(14, 'ROAD OBSTRUCTION', NULL, 200.00, 500.00, 2500.00, 1, '2025-11-17 18:07:43'),
(15, 'BLOCKING PEDESTRIAN LANE', NULL, 200.00, 500.00, 2500.00, 1, '2025-11-17 18:07:43'),
(16, 'LOADING/UNLOADING IN PROHIBITED ZONE', NULL, 200.00, 500.00, 2500.00, 1, '2025-11-17 18:07:43'),
(17, 'DOUBLE PARKING', NULL, 200.00, 500.00, 2500.00, 1, '2025-11-17 18:07:43'),
(18, 'DRUNK DRIVING', NULL, 500.00, 1000.00, 1500.00, 1, '2025-11-17 18:07:43'),
(19, 'COLORUM OPERATION', NULL, 2500.00, 3000.00, 3000.00, 1, '2025-11-17 18:07:43'),
(20, 'NO TRASHBIN', NULL, 1000.00, 2000.00, 2500.00, 1, '2025-11-17 18:07:43'),
(21, 'DRIVING IN SHORT / SANDO', NULL, 200.00, 500.00, 1000.00, 1, '2025-11-17 18:07:43'),
(22, 'OVERLOADED PASSENGER', NULL, 500.00, 750.00, 1000.00, 1, '2025-11-17 18:07:43'),
(23, 'OVER CHARGING / UNDER CHARGING', NULL, 500.00, 750.00, 1000.00, 1, '2025-11-17 18:07:43'),
(24, 'REFUSAL TO CONVEY PASSENGER/S', NULL, 500.00, 750.00, 1000.00, 1, '2025-11-17 18:07:43'),
(25, 'DRAG RACING', NULL, 1000.00, 1500.00, 2500.00, 1, '2025-11-17 18:07:43'),
(26, 'NO ENHANCED OPLAN VISA STICKER', NULL, 300.00, 300.00, 300.00, 1, '2025-11-17 18:07:43'),
(27, 'FAILURE TO PRESENT E-OV MATCH CARD', NULL, 200.00, 200.00, 200.00, 1, '2025-11-17 18:07:43'),
(28, 'MINOR', '', 500.00, 500.00, 500.00, 1, '2025-11-18 08:22:51'),
(29, 'NO DRIVER\'S LICENSE', '', 500.00, 500.00, 500.00, 1, '2025-11-18 14:49:05');

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_active_citations`
-- (See below for the actual view)
--
CREATE TABLE `vw_active_citations` (
`citation_id` int(11)
,`ticket_number` varchar(50)
,`driver_id` int(11)
,`last_name` varchar(100)
,`first_name` varchar(100)
,`middle_initial` varchar(10)
,`suffix` varchar(20)
,`date_of_birth` date
,`age` int(11)
,`zone` varchar(50)
,`barangay` varchar(100)
,`municipality` varchar(100)
,`province` varchar(100)
,`license_number` varchar(50)
,`license_type` varchar(50)
,`plate_mv_engine_chassis_no` varchar(100)
,`vehicle_description` text
,`apprehension_datetime` datetime
,`place_of_apprehension` varchar(255)
,`apprehension_officer` varchar(255)
,`remarks` text
,`status` enum('pending','paid','contested','dismissed','void')
,`payment_date` datetime
,`total_fine` decimal(10,2)
,`created_at` datetime
,`updated_at` datetime
,`created_by` int(11)
,`deleted_at` datetime
,`deleted_by` int(11)
,`deletion_reason` text
,`violations` mediumtext
,`vehicle_type` varchar(100)
,`created_by_name` varchar(100)
,`deleted_by_name` varchar(100)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_citation_summary`
-- (See below for the actual view)
--
CREATE TABLE `vw_citation_summary` (
`citation_id` int(11)
,`ticket_number` varchar(50)
,`driver_name` varchar(213)
,`license_number` varchar(50)
,`plate_mv_engine_chassis_no` varchar(100)
,`apprehension_datetime` datetime
,`place_of_apprehension` varchar(255)
,`status` enum('pending','paid','contested','dismissed','void')
,`total_fine` decimal(10,2)
,`violation_count` bigint(21)
,`created_at` datetime
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_deleted_citations`
-- (See below for the actual view)
--
CREATE TABLE `vw_deleted_citations` (
`citation_id` int(11)
,`ticket_number` varchar(50)
,`driver_id` int(11)
,`last_name` varchar(100)
,`first_name` varchar(100)
,`middle_initial` varchar(10)
,`suffix` varchar(20)
,`date_of_birth` date
,`age` int(11)
,`zone` varchar(50)
,`barangay` varchar(100)
,`municipality` varchar(100)
,`province` varchar(100)
,`license_number` varchar(50)
,`license_type` varchar(50)
,`plate_mv_engine_chassis_no` varchar(100)
,`vehicle_description` text
,`apprehension_datetime` datetime
,`place_of_apprehension` varchar(255)
,`apprehension_officer` varchar(255)
,`remarks` text
,`status` enum('pending','paid','contested','dismissed','void')
,`payment_date` datetime
,`total_fine` decimal(10,2)
,`created_at` datetime
,`updated_at` datetime
,`created_by` int(11)
,`deleted_at` datetime
,`deleted_by` int(11)
,`deletion_reason` text
,`violations` mediumtext
,`vehicle_type` varchar(100)
,`created_by_name` varchar(100)
,`deleted_by_name` varchar(100)
,`days_in_trash` int(7)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_driver_offenses`
-- (See below for the actual view)
--
CREATE TABLE `vw_driver_offenses` (
`driver_id` int(11)
,`driver_name` varchar(202)
,`license_number` varchar(50)
,`total_citations` bigint(21)
,`total_violations` bigint(21)
,`total_fines` decimal(32,2)
);

-- --------------------------------------------------------

--
-- Structure for view `vw_active_citations`
--
DROP TABLE IF EXISTS `vw_active_citations`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_active_citations`  AS SELECT `c`.`citation_id` AS `citation_id`, `c`.`ticket_number` AS `ticket_number`, `c`.`driver_id` AS `driver_id`, `c`.`last_name` AS `last_name`, `c`.`first_name` AS `first_name`, `c`.`middle_initial` AS `middle_initial`, `c`.`suffix` AS `suffix`, `c`.`date_of_birth` AS `date_of_birth`, `c`.`age` AS `age`, `c`.`zone` AS `zone`, `c`.`barangay` AS `barangay`, `c`.`municipality` AS `municipality`, `c`.`province` AS `province`, `c`.`license_number` AS `license_number`, `c`.`license_type` AS `license_type`, `c`.`plate_mv_engine_chassis_no` AS `plate_mv_engine_chassis_no`, `c`.`vehicle_description` AS `vehicle_description`, `c`.`apprehension_datetime` AS `apprehension_datetime`, `c`.`place_of_apprehension` AS `place_of_apprehension`, `c`.`apprehension_officer` AS `apprehension_officer`, `c`.`remarks` AS `remarks`, `c`.`status` AS `status`, `c`.`payment_date` AS `payment_date`, `c`.`total_fine` AS `total_fine`, `c`.`created_at` AS `created_at`, `c`.`updated_at` AS `updated_at`, `c`.`created_by` AS `created_by`, `c`.`deleted_at` AS `deleted_at`, `c`.`deleted_by` AS `deleted_by`, `c`.`deletion_reason` AS `deletion_reason`, group_concat(distinct `vt`.`violation_type` separator ', ') AS `violations`, `cv`.`vehicle_type` AS `vehicle_type`, `u`.`full_name` AS `created_by_name`, `du`.`full_name` AS `deleted_by_name` FROM (((((`citations` `c` left join `violations` `v` on(`c`.`citation_id` = `v`.`citation_id`)) left join `violation_types` `vt` on(`v`.`violation_type_id` = `vt`.`violation_type_id`)) left join `citation_vehicles` `cv` on(`c`.`citation_id` = `cv`.`citation_id`)) left join `users` `u` on(`c`.`created_by` = `u`.`user_id`)) left join `users` `du` on(`c`.`deleted_by` = `du`.`user_id`)) WHERE `c`.`deleted_at` is null GROUP BY `c`.`citation_id` ORDER BY `c`.`created_at` DESC ;

-- --------------------------------------------------------

--
-- Structure for view `vw_citation_summary`
--
DROP TABLE IF EXISTS `vw_citation_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_citation_summary`  AS SELECT `c`.`citation_id` AS `citation_id`, `c`.`ticket_number` AS `ticket_number`, concat(`c`.`last_name`,', ',`c`.`first_name`,' ',coalesce(`c`.`middle_initial`,'')) AS `driver_name`, `c`.`license_number` AS `license_number`, `c`.`plate_mv_engine_chassis_no` AS `plate_mv_engine_chassis_no`, `c`.`apprehension_datetime` AS `apprehension_datetime`, `c`.`place_of_apprehension` AS `place_of_apprehension`, `c`.`status` AS `status`, `c`.`total_fine` AS `total_fine`, count(`v`.`violation_id`) AS `violation_count`, `c`.`created_at` AS `created_at` FROM (`citations` `c` left join `violations` `v` on(`c`.`citation_id` = `v`.`citation_id`)) GROUP BY `c`.`citation_id` ;

-- --------------------------------------------------------

--
-- Structure for view `vw_deleted_citations`
--
DROP TABLE IF EXISTS `vw_deleted_citations`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_deleted_citations`  AS SELECT `c`.`citation_id` AS `citation_id`, `c`.`ticket_number` AS `ticket_number`, `c`.`driver_id` AS `driver_id`, `c`.`last_name` AS `last_name`, `c`.`first_name` AS `first_name`, `c`.`middle_initial` AS `middle_initial`, `c`.`suffix` AS `suffix`, `c`.`date_of_birth` AS `date_of_birth`, `c`.`age` AS `age`, `c`.`zone` AS `zone`, `c`.`barangay` AS `barangay`, `c`.`municipality` AS `municipality`, `c`.`province` AS `province`, `c`.`license_number` AS `license_number`, `c`.`license_type` AS `license_type`, `c`.`plate_mv_engine_chassis_no` AS `plate_mv_engine_chassis_no`, `c`.`vehicle_description` AS `vehicle_description`, `c`.`apprehension_datetime` AS `apprehension_datetime`, `c`.`place_of_apprehension` AS `place_of_apprehension`, `c`.`apprehension_officer` AS `apprehension_officer`, `c`.`remarks` AS `remarks`, `c`.`status` AS `status`, `c`.`payment_date` AS `payment_date`, `c`.`total_fine` AS `total_fine`, `c`.`created_at` AS `created_at`, `c`.`updated_at` AS `updated_at`, `c`.`created_by` AS `created_by`, `c`.`deleted_at` AS `deleted_at`, `c`.`deleted_by` AS `deleted_by`, `c`.`deletion_reason` AS `deletion_reason`, group_concat(distinct `vt`.`violation_type` separator ', ') AS `violations`, `cv`.`vehicle_type` AS `vehicle_type`, `u`.`full_name` AS `created_by_name`, `du`.`full_name` AS `deleted_by_name`, to_days(current_timestamp()) - to_days(`c`.`deleted_at`) AS `days_in_trash` FROM (((((`citations` `c` left join `violations` `v` on(`c`.`citation_id` = `v`.`citation_id`)) left join `violation_types` `vt` on(`v`.`violation_type_id` = `vt`.`violation_type_id`)) left join `citation_vehicles` `cv` on(`c`.`citation_id` = `cv`.`citation_id`)) left join `users` `u` on(`c`.`created_by` = `u`.`user_id`)) left join `users` `du` on(`c`.`deleted_by` = `du`.`user_id`)) WHERE `c`.`deleted_at` is not null GROUP BY `c`.`citation_id` ORDER BY `c`.`deleted_at` DESC ;

-- --------------------------------------------------------

--
-- Structure for view `vw_driver_offenses`
--
DROP TABLE IF EXISTS `vw_driver_offenses`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_driver_offenses`  AS SELECT `d`.`driver_id` AS `driver_id`, concat(`d`.`last_name`,', ',`d`.`first_name`) AS `driver_name`, `d`.`license_number` AS `license_number`, count(distinct `c`.`citation_id`) AS `total_citations`, count(`v`.`violation_id`) AS `total_violations`, sum(`c`.`total_fine`) AS `total_fines` FROM ((`drivers` `d` left join `citations` `c` on(`d`.`driver_id` = `c`.`driver_id`)) left join `violations` `v` on(`c`.`citation_id` = `v`.`citation_id`)) GROUP BY `d`.`driver_id` ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `apprehending_officers`
--
ALTER TABLE `apprehending_officers`
  ADD PRIMARY KEY (`officer_id`);

--
-- Indexes for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD PRIMARY KEY (`audit_id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_table` (`table_name`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `idx_action_type` (`action_type`),
  ADD KEY `idx_entity_type_id` (`entity_type`,`entity_id`),
  ADD KEY `idx_or_numbers` (`or_number_old`,`or_number_new`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_action_datetime` (`action_datetime`),
  ADD KEY `idx_ticket_number` (`ticket_number`);

--
-- Indexes for table `citations`
--
ALTER TABLE `citations`
  ADD PRIMARY KEY (`citation_id`),
  ADD UNIQUE KEY `ticket_number` (`ticket_number`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_ticket` (`ticket_number`),
  ADD KEY `idx_driver` (`driver_id`),
  ADD KEY `idx_datetime` (`apprehension_datetime`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created` (`created_at`),
  ADD KEY `idx_date_status` (`apprehension_datetime`,`status`),
  ADD KEY `idx_status_date` (`status`,`apprehension_datetime`),
  ADD KEY `idx_driver_names` (`last_name`,`first_name`),
  ADD KEY `idx_plate` (`plate_mv_engine_chassis_no`),
  ADD KEY `idx_fine` (`total_fine`),
  ADD KEY `idx_deleted_at` (`deleted_at`),
  ADD KEY `idx_deleted_by` (`deleted_by`);

--
-- Indexes for table `citation_vehicles`
--
ALTER TABLE `citation_vehicles`
  ADD PRIMARY KEY (`vehicle_id`),
  ADD KEY `idx_citation` (`citation_id`);

--
-- Indexes for table `drivers`
--
ALTER TABLE `drivers`
  ADD PRIMARY KEY (`driver_id`),
  ADD UNIQUE KEY `license_number` (`license_number`),
  ADD KEY `idx_license` (`license_number`),
  ADD KEY `idx_name` (`last_name`,`first_name`),
  ADD KEY `idx_barangay` (`barangay`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`payment_id`),
  ADD UNIQUE KEY `receipt_number` (`receipt_number`),
  ADD KEY `idx_citation` (`citation_id`),
  ADD KEY `idx_payment_date` (`payment_date`),
  ADD KEY `idx_receipt_number` (`receipt_number`),
  ADD KEY `idx_collected_by` (`collected_by`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_payment_method` (`payment_method`),
  ADD KEY `idx_citation_payment_status` (`citation_id`,`status`);

--
-- Indexes for table `payment_audit`
--
ALTER TABLE `payment_audit`
  ADD PRIMARY KEY (`audit_id`),
  ADD KEY `idx_payment` (`payment_id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_performed_at` (`performed_at`),
  ADD KEY `idx_performed_by` (`performed_by`);

--
-- Indexes for table `receipts`
--
ALTER TABLE `receipts`
  ADD PRIMARY KEY (`receipt_id`),
  ADD UNIQUE KEY `receipt_number` (`receipt_number`),
  ADD KEY `generated_by` (`generated_by`),
  ADD KEY `last_printed_by` (`last_printed_by`),
  ADD KEY `cancelled_by` (`cancelled_by`),
  ADD KEY `idx_payment` (`payment_id`),
  ADD KEY `idx_receipt_number` (`receipt_number`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_generated_at` (`generated_at`);

--
-- Indexes for table `receipt_sequence`
--
ALTER TABLE `receipt_sequence`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `trigger_error_log`
--
ALTER TABLE `trigger_error_log`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `idx_trigger_name` (`trigger_name`),
  ADD KEY `idx_citation_id` (`citation_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_role` (`role`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `violations`
--
ALTER TABLE `violations`
  ADD PRIMARY KEY (`violation_id`),
  ADD UNIQUE KEY `unique_citation_violation` (`citation_id`,`violation_type_id`),
  ADD KEY `idx_citation` (`citation_id`),
  ADD KEY `idx_violation_type` (`violation_type_id`),
  ADD KEY `idx_offense_count` (`offense_count`);

--
-- Indexes for table `violation_types`
--
ALTER TABLE `violation_types`
  ADD PRIMARY KEY (`violation_type_id`),
  ADD UNIQUE KEY `violation_type` (`violation_type`),
  ADD KEY `idx_violation_type` (`violation_type`),
  ADD KEY `idx_active` (`is_active`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `apprehending_officers`
--
ALTER TABLE `apprehending_officers`
  MODIFY `officer_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `audit_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=72;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `citations`
--
ALTER TABLE `citations`
  MODIFY `citation_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=50;

--
-- AUTO_INCREMENT for table `citation_vehicles`
--
ALTER TABLE `citation_vehicles`
  MODIFY `vehicle_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=61;

--
-- AUTO_INCREMENT for table `drivers`
--
ALTER TABLE `drivers`
  MODIFY `driver_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=50;

--
-- AUTO_INCREMENT for table `payment_audit`
--
ALTER TABLE `payment_audit`
  MODIFY `audit_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `receipts`
--
ALTER TABLE `receipts`
  MODIFY `receipt_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=50;

--
-- AUTO_INCREMENT for table `trigger_error_log`
--
ALTER TABLE `trigger_error_log`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `violations`
--
ALTER TABLE `violations`
  MODIFY `violation_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=81;

--
-- AUTO_INCREMENT for table `violation_types`
--
ALTER TABLE `violation_types`
  MODIFY `violation_type_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD CONSTRAINT `audit_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `citations`
--
ALTER TABLE `citations`
  ADD CONSTRAINT `citations_ibfk_1` FOREIGN KEY (`driver_id`) REFERENCES `drivers` (`driver_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `citations_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_citations_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `citation_vehicles`
--
ALTER TABLE `citation_vehicles`
  ADD CONSTRAINT `citation_vehicles_ibfk_1` FOREIGN KEY (`citation_id`) REFERENCES `citations` (`citation_id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`citation_id`) REFERENCES `citations` (`citation_id`),
  ADD CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`collected_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `payment_audit`
--
ALTER TABLE `payment_audit`
  ADD CONSTRAINT `payment_audit_ibfk_1` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`payment_id`),
  ADD CONSTRAINT `payment_audit_ibfk_2` FOREIGN KEY (`performed_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `receipts`
--
ALTER TABLE `receipts`
  ADD CONSTRAINT `receipts_ibfk_1` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`payment_id`),
  ADD CONSTRAINT `receipts_ibfk_2` FOREIGN KEY (`generated_by`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `receipts_ibfk_3` FOREIGN KEY (`last_printed_by`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `receipts_ibfk_4` FOREIGN KEY (`cancelled_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `violations`
--
ALTER TABLE `violations`
  ADD CONSTRAINT `violations_ibfk_1` FOREIGN KEY (`citation_id`) REFERENCES `citations` (`citation_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `violations_ibfk_2` FOREIGN KEY (`violation_type_id`) REFERENCES `violation_types` (`violation_type_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
