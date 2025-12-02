# Payment Management & Receipt Printing System
## Implementation Plan & Analysis

**Project:** Traffic Citation Management System
**Database:** traffic_system
**Technology Stack:** PHP 8.2.12, MySQL/MariaDB, Bootstrap 5
**Date:** 2025-11-25

---

## Table of Contents
1. [Current System Analysis](#current-system-analysis)
2. [What's Missing](#whats-missing)
3. [Database Schema Design](#database-schema-design)
4. [Implementation Phases](#implementation-phases)
5. [File Structure](#file-structure)
6. [Receipt Design Specifications](#receipt-design-specifications)
7. [Security Considerations](#security-considerations)
8. [Implementation Checklist](#implementation-checklist)

---

## Current System Analysis

### ✅ Existing Features

#### Database Foundation
- **citations table** has payment tracking fields:
  - `status` ENUM('pending', 'paid', 'contested', 'dismissed', 'void')
  - `payment_date` DATETIME NULL
  - `total_fine` DECIMAL(10,2) DEFAULT 0.00

- **Automatic fine calculation** via database trigger (`after_violation_insert`)
- **Audit fields:** created_at, updated_at, created_by

#### Current Functionality
- ✅ Can mark citations as 'paid' via status update
- ✅ Financial reporting with revenue tracking
- ✅ CSV export for citations and reports
- ✅ Browser-based print functionality
- ✅ User authentication with role-based access (admin/enforcer/user)
- ✅ CSRF protection and security measures
- ✅ Outstanding fines with aging analysis (0-30, 31-60, 61-90, 90+ days)

#### Technology Stack
- **Backend:** PHP 8.2.12 with PDO
- **Database:** MySQL/MariaDB 10.4.32
- **Frontend:** Bootstrap 5.3.3, Chart.js 4.4.0
- **JavaScript:** Vanilla JS (no jQuery)
- **Security:** CSRF tokens, rate limiting, prepared statements

---

## What's Missing

### ❌ Critical Gaps for Payment Management

#### 1. Payment Processing
- ❌ No payment recording interface
- ❌ No payment method tracking (cash, check, online, GCash, etc.)
- ❌ No payment reference numbers
- ❌ No cashier/collector information tracking
- ❌ `payment_date` NOT auto-populated when status changed to 'paid'
- ❌ No payment history/audit trail
- ❌ No partial payment support

#### 2. Receipt Generation
- ❌ No official receipt (OR) numbering system
- ❌ No receipt template design
- ❌ No receipt printing module
- ❌ No PDF generation library (TCPDF, Dompdf, mPDF)
- ❌ No receipt table in database
- ❌ No receipt reprint functionality
- ❌ No receipt cancellation/void workflow

#### 3. Database Structure
- ❌ No `payments` table
- ❌ No `receipts` table
- ❌ No `payment_methods` lookup table
- ❌ No payment audit trail

#### 4. Business Logic
- ❌ No OR number sequence generator
- ❌ No payment validation rules
- ❌ No payment reversal/refund workflow
- ❌ No duplicate payment prevention

---

## Database Schema Design

### New Tables to Create

#### 1. payments Table
```sql
CREATE TABLE payments (
    payment_id INT AUTO_INCREMENT PRIMARY KEY,
    citation_id INT NOT NULL,
    amount_paid DECIMAL(10,2) NOT NULL,
    payment_method ENUM('cash', 'check', 'online', 'gcash', 'paymaya', 'bank_transfer', 'money_order') NOT NULL DEFAULT 'cash',
    payment_date DATETIME NOT NULL,
    reference_number VARCHAR(100) NULL COMMENT 'Check number, transaction ID, etc.',
    receipt_number VARCHAR(50) UNIQUE NOT NULL COMMENT 'Official Receipt number',
    collected_by INT NOT NULL COMMENT 'User ID of cashier/collector',
    check_number VARCHAR(50) NULL,
    check_bank VARCHAR(100) NULL,
    check_date DATE NULL,
    notes TEXT NULL,
    status ENUM('completed', 'pending', 'failed', 'refunded', 'cancelled') DEFAULT 'completed',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (citation_id) REFERENCES citations(citation_id) ON DELETE RESTRICT,
    FOREIGN KEY (collected_by) REFERENCES users(user_id) ON DELETE RESTRICT,

    INDEX idx_citation (citation_id),
    INDEX idx_payment_date (payment_date),
    INDEX idx_receipt_number (receipt_number),
    INDEX idx_collected_by (collected_by),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 2. receipts Table
```sql
CREATE TABLE receipts (
    receipt_id INT AUTO_INCREMENT PRIMARY KEY,
    payment_id INT NOT NULL,
    receipt_number VARCHAR(50) UNIQUE NOT NULL,
    generated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    generated_by INT NOT NULL COMMENT 'User ID who generated the receipt',
    printed_at DATETIME NULL,
    print_count INT DEFAULT 0,
    last_printed_by INT NULL,
    last_printed_at DATETIME NULL,
    status ENUM('active', 'cancelled', 'void') DEFAULT 'active',
    cancellation_reason TEXT NULL,
    cancelled_by INT NULL,
    cancelled_at DATETIME NULL,

    FOREIGN KEY (payment_id) REFERENCES payments(payment_id) ON DELETE RESTRICT,
    FOREIGN KEY (generated_by) REFERENCES users(user_id) ON DELETE RESTRICT,
    FOREIGN KEY (last_printed_by) REFERENCES users(user_id) ON DELETE RESTRICT,
    FOREIGN KEY (cancelled_by) REFERENCES users(user_id) ON DELETE RESTRICT,

    INDEX idx_payment (payment_id),
    INDEX idx_receipt_number (receipt_number),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 3. receipt_sequence Table (for OR number generation)
```sql
CREATE TABLE receipt_sequence (
    id INT PRIMARY KEY DEFAULT 1,
    current_year INT NOT NULL,
    current_number INT NOT NULL DEFAULT 0,
    last_updated DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CHECK (id = 1) -- Only one row allowed
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Initialize with current year
INSERT INTO receipt_sequence (id, current_year, current_number)
VALUES (1, YEAR(CURDATE()), 0);
```

#### 4. payment_audit Table (optional - for audit trail)
```sql
CREATE TABLE payment_audit (
    audit_id INT AUTO_INCREMENT PRIMARY KEY,
    payment_id INT NOT NULL,
    action ENUM('created', 'updated', 'refunded', 'cancelled', 'voided') NOT NULL,
    old_values JSON NULL,
    new_values JSON NULL,
    performed_by INT NOT NULL,
    performed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    notes TEXT NULL,

    FOREIGN KEY (payment_id) REFERENCES payments(payment_id) ON DELETE RESTRICT,
    FOREIGN KEY (performed_by) REFERENCES users(user_id) ON DELETE RESTRICT,

    INDEX idx_payment (payment_id),
    INDEX idx_action (action),
    INDEX idx_performed_at (performed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Database Triggers

#### Auto-update citation status on payment
```sql
DELIMITER //

CREATE TRIGGER after_payment_insert
AFTER INSERT ON payments
FOR EACH ROW
BEGIN
    -- Update citation status to 'paid' and set payment_date
    UPDATE citations
    SET
        status = 'paid',
        payment_date = NEW.payment_date,
        updated_at = CURRENT_TIMESTAMP
    WHERE citation_id = NEW.citation_id;
END//

DELIMITER ;
```

#### Payment audit trigger
```sql
DELIMITER //

CREATE TRIGGER after_payment_update
AFTER UPDATE ON payments
FOR EACH ROW
BEGIN
    INSERT INTO payment_audit (
        payment_id,
        action,
        old_values,
        new_values,
        performed_by
    )
    VALUES (
        NEW.payment_id,
        'updated',
        JSON_OBJECT(
            'status', OLD.status,
            'amount_paid', OLD.amount_paid,
            'payment_method', OLD.payment_method
        ),
        JSON_OBJECT(
            'status', NEW.status,
            'amount_paid', NEW.amount_paid,
            'payment_method', NEW.payment_method
        ),
        NEW.collected_by
    );
END//

DELIMITER ;
```

---

## Implementation Phases

### PHASE 1: Database Setup (Priority: HIGH)
**Estimated Time:** 30 minutes

**Tasks:**
1. Create `payments` table
2. Create `receipts` table
3. Create `receipt_sequence` table
4. Create `payment_audit` table (optional)
5. Create database triggers
6. Create database indexes for performance
7. Test database schema with sample data

**Files to Create:**
- `database/migrations/add_payment_tables.sql`
- `database/migrations/add_payment_triggers.sql`

---

### PHASE 2: Backend Services (Priority: HIGH)
**Estimated Time:** 1-2 hours

**Tasks:**
1. Install PDF generation library (Dompdf recommended)
2. Create PaymentService class
3. Create ReceiptService class
4. Implement OR number generator
5. Create payment processing API endpoints
6. Create receipt generation API endpoints

**Files to Create:**

#### Service Classes
- `services/PaymentService.php`
  - `recordPayment($citationId, $amount, $method, $collectedBy, $notes)`
  - `getPaymentHistory($citationId)`
  - `getAllPayments($filters, $dateRange)`
  - `refundPayment($paymentId, $reason, $userId)`
  - `cancelPayment($paymentId, $reason, $userId)`
  - `validatePayment($citationId, $amount)`

- `services/ReceiptService.php`
  - `generateReceiptNumber()` - Format: OR-YYYY-XXXXXX
  - `generateReceiptPDF($paymentId)`
  - `reprintReceipt($receiptId, $userId)`
  - `cancelReceipt($receiptId, $reason, $userId)`
  - `getReceiptData($paymentId)`

#### API Endpoints
- `api/payment_process.php` - Record new payment
- `api/payment_history.php` - Get payment history
- `api/payment_list.php` - List all payments with filters
- `api/receipt_generate.php` - Generate receipt PDF
- `api/receipt_print.php` - Print/reprint receipt
- `api/receipt_cancel.php` - Cancel/void receipt

---

### PHASE 3: PDF Library Setup (Priority: HIGH)
**Estimated Time:** 30 minutes

**Option A: Dompdf (Recommended - Easy to use)**
```bash
# Install via Composer
composer require dompdf/dompdf
```

**Option B: TCPDF (More features)**
```bash
composer require tecnickcom/tcpdf
```

**Option C: mPDF (Good balance)**
```bash
composer require mpdf/mpdf
```

**Recommendation:** Use **Dompdf** for simplicity and HTML-to-PDF conversion.

**Files to Create:**
- `includes/pdf_config.php` - PDF library configuration
- `templates/receipts/official-receipt.php` - HTML receipt template

---

### PHASE 4: Receipt Template Design (Priority: MEDIUM)
**Estimated Time:** 1 hour

**Receipt Components:**

#### Header Section
- LGU/Government agency name
- Address and contact information
- Logo (if available)
- "OFFICIAL RECEIPT" title

#### Receipt Information
- Receipt Number: **OR-2025-000001**
- Date & Time: **November 25, 2025 10:30 AM**
- Citation/Ticket Number: **TKT-2025-001**

#### Violator Information
- Name: **Juan Dela Cruz**
- License Number: **N01-12-345678**
- Plate Number: **ABC 1234**

#### Violation Details
- Violation Type
- Offense Count
- Fine Amount
- Total Fine

#### Payment Information
- Amount Paid: **₱500.00**
- Payment Method: **Cash**
- Payment Reference: **(for check/online payments)**

#### Footer Section
- Collected by: **Cashier Name**
- Signature line
- Official stamps
- Disclaimer text
- Copy designation (Original/Duplicate/Triplicate)

#### Optional Features
- QR Code with receipt verification URL
- Barcode for quick scanning
- Watermark for security

**Files to Create:**
- `templates/receipts/official-receipt.php` - Main receipt template
- `templates/receipts/receipt-header.php` - Reusable header
- `templates/receipts/receipt-footer.php` - Reusable footer
- `assets/css/receipt.css` - Receipt styling

---

### PHASE 5: Frontend UI Development (Priority: HIGH)
**Estimated Time:** 2-3 hours

**Components to Build:**

#### 1. Payment Recording Modal
**Location:** Add to citation details page

**Features:**
- Citation summary display
- Total fine amount
- Payment amount input with validation
- Payment method dropdown
- Reference number field (conditional based on method)
- Check details (if payment method = check)
- Notes/remarks textarea
- Submit and cancel buttons
- Real-time validation

**Files to Modify/Create:**
- Modify existing citation details page to add "Record Payment" button
- `assets/js/payment-modal.js` - Payment form handling
- `assets/css/payment-modal.css` - Modal styling

#### 2. Payment Management Page
**Location:** `public/payments.php`

**Sections:**
- **Dashboard Cards:**
  - Today's Collections
  - This Week's Collections
  - This Month's Collections
  - Pending Payments

- **Search & Filters:**
  - Date range picker
  - Ticket number search
  - OR number search
  - Payment method filter
  - Cashier filter
  - Status filter

- **Payment List Table:**
  - Columns: Date, Time, Ticket #, OR #, Violator, Amount, Method, Cashier, Actions
  - Pagination
  - Sort by column
  - Export to CSV/Excel

- **Actions:**
  - View Details
  - Print Receipt
  - Download PDF
  - Void Payment (admin only)

**Files to Create:**
- `public/payments.php` - Main payments page
- `assets/js/payments.js` - Payments page JavaScript
- `assets/css/payments.css` - Payments page styling

#### 3. Receipt Preview & Print
**Features:**
- Modal popup with receipt preview
- Print button (window.print)
- Download PDF button
- Email receipt (future feature)
- Reprint option with warning

**Files to Create:**
- `templates/payments/receipt-preview-modal.php`
- `assets/js/receipt-print.js`

---

### PHASE 6: Integration & Enhancement (Priority: MEDIUM)
**Estimated Time:** 1-2 hours

**Tasks:**

#### 1. Update Citation Workflow
- Add "Record Payment" button to citation details
- Show payment status indicator
- Display payment history
- Link to receipt from citation

**Files to Modify:**
- Citation details page (wherever it exists)
- Citation list page (add payment status column)

#### 2. Enhance Financial Reports
- Add payment breakdown by method
- Add cashier performance report
- Add daily collections report
- Add payment trends chart

**Files to Modify:**
- `services/ReportService.php`
- `templates/reports/financial-report.php`

#### 3. Update Navigation
- Add "Payments" menu item to sidebar
- Add quick access to today's collections

**Files to Modify:**
- Sidebar navigation template

---

## File Structure

```
c:/xampp/htdocs/tmg/
│
├── database/
│   ├── migrations/
│   │   ├── add_payment_tables.sql          [NEW]
│   │   ├── add_payment_triggers.sql        [NEW]
│   │   └── payment_schema_rollback.sql     [NEW]
│   │
│   └── seeds/
│       └── payment_test_data.sql           [NEW]
│
├── services/
│   ├── CitationService.php                 [EXISTING]
│   ├── ReportService.php                   [EXISTING]
│   ├── PaymentService.php                  [NEW]
│   └── ReceiptService.php                  [NEW]
│
├── api/
│   ├── payment_process.php                 [NEW]
│   ├── payment_history.php                 [NEW]
│   ├── payment_list.php                    [NEW]
│   ├── receipt_generate.php                [NEW]
│   ├── receipt_print.php                   [NEW]
│   └── receipt_cancel.php                  [NEW]
│
├── public/
│   ├── citations.php                       [EXISTING]
│   ├── reports.php                         [EXISTING]
│   └── payments.php                        [NEW]
│
├── templates/
│   ├── receipts/
│   │   ├── official-receipt.php            [NEW]
│   │   ├── receipt-header.php              [NEW]
│   │   ├── receipt-footer.php              [NEW]
│   │   └── receipt-preview-modal.php       [NEW]
│   │
│   └── payments/
│       ├── payment-modal.php               [NEW]
│       └── payment-history.php             [NEW]
│
├── assets/
│   ├── css/
│   │   ├── payments.css                    [NEW]
│   │   ├── payment-modal.css               [NEW]
│   │   └── receipt.css                     [NEW]
│   │
│   └── js/
│       ├── payments.js                     [NEW]
│       ├── payment-modal.js                [NEW]
│       └── receipt-print.js                [NEW]
│
├── includes/
│   ├── config.php                          [EXISTING]
│   ├── auth.php                            [EXISTING]
│   ├── functions.php                       [EXISTING]
│   └── pdf_config.php                      [NEW]
│
├── vendor/                                 [NEW - Composer dependencies]
│   └── dompdf/
│
├── composer.json                           [NEW]
├── composer.lock                           [NEW]
└── PAYMENT_RECEIPT_IMPLEMENTATION.md       [THIS FILE]
```

---

## Receipt Design Specifications

### Receipt Number Format
**Format:** `OR-YYYY-NNNNNN`

**Examples:**
- OR-2025-000001
- OR-2025-000002
- OR-2025-999999

**Generation Logic:**
1. Get current year
2. If year changed, reset counter to 1
3. Otherwise, increment counter
4. Format: OR-{YEAR}-{6-digit-zero-padded-number}

### Receipt Template Layout

```
┌────────────────────────────────────────────────────┐
│              [LOGO]                                │
│         Local Government Unit                      │
│         Municipality of Baggao                     │
│         Province of Cagayan                        │
│         Contact: (xxx) xxx-xxxx                    │
│                                                    │
│         ═══════════════════════════                │
│            OFFICIAL RECEIPT                        │
│         ═══════════════════════════                │
│                                                    │
│  Receipt No.:  OR-2025-000001                      │
│  Date & Time:  November 25, 2025 10:30 AM         │
│  Ticket No.:   TKT-2025-001                        │
│                                                    │
├────────────────────────────────────────────────────┤
│  RECEIVED FROM:                                    │
│  Name:         Juan Dela Cruz                      │
│  License No.:  N01-12-345678                       │
│  Plate No.:    ABC 1234                            │
│                                                    │
├────────────────────────────────────────────────────┤
│  VIOLATION DETAILS:                                │
│  ┌──────────────────────────┬───────┬──────────┐  │
│  │ Violation Type           │ Count │   Amount │  │
│  ├──────────────────────────┼───────┼──────────┤  │
│  │ No Valid License         │   1st │  ₱500.00 │  │
│  │ No Helmet                │   1st │  ₱500.00 │  │
│  └──────────────────────────┴───────┴──────────┘  │
│                                                    │
│  TOTAL FINE:                          ₱1,000.00   │
│  AMOUNT PAID:                         ₱1,000.00   │
│  PAYMENT METHOD: Cash                              │
│  CHANGE:                                  ₱0.00   │
│                                                    │
├────────────────────────────────────────────────────┤
│  Collected by: Maria Santos                        │
│  Signature: _____________________                  │
│                                                    │
│  [QR CODE]                                         │
│                                                    │
│  This is an official receipt. Keep for your        │
│  records. For verification, visit:                 │
│  http://yourdomain.com/verify?or=OR-2025-000001   │
│                                                    │
│  ─── ORIGINAL COPY ───                             │
└────────────────────────────────────────────────────┘
```

### Receipt Copies
- **Original:** White paper - For violator
- **Duplicate:** Yellow paper - For LGU accounting (optional)
- **Triplicate:** Pink paper - For cashier file (optional)

### Paper Size
- **Standard:** Letter (8.5" x 11") or A4
- **Thermal:** 80mm width (for thermal printers)

---

## Security Considerations

### Payment Security
1. **Duplicate Payment Prevention:**
   - Check if citation already has payment before allowing new payment
   - Validate citation is in 'pending' status before payment

2. **Amount Validation:**
   - Ensure payment amount matches total fine
   - Prevent overpayment or underpayment (unless partial payments allowed)

3. **Receipt Number Uniqueness:**
   - Use database transaction for OR number generation
   - Implement table lock to prevent duplicate OR numbers
   - Atomic increment operation

4. **Audit Trail:**
   - Log all payment transactions
   - Track who collected payment
   - Record IP address and timestamp
   - Keep payment history even if refunded

5. **Access Control:**
   - Only authenticated users can record payments
   - Only admin can void/refund payments
   - Cashier can only see their own collections (optional)

### Receipt Security
1. **Digital Signature:** Consider adding hash/signature to receipt
2. **Watermark:** Add security watermark to PDF
3. **QR Code:** Include verification QR code
4. **Tamper Prevention:** Make receipts read-only after generation
5. **Void/Cancel Tracking:** Log all receipt cancellations with reason

### Database Security
1. **Transactions:** Use database transactions for payment recording
2. **Foreign Keys:** Enforce referential integrity
3. **Constraints:** Add CHECK constraints for valid amounts
4. **Prepared Statements:** Always use PDO prepared statements
5. **Input Validation:** Sanitize all user inputs

---

## Implementation Checklist

### Database Setup
- [ ] Create `payments` table
- [ ] Create `receipts` table
- [ ] Create `receipt_sequence` table
- [ ] Create `payment_audit` table
- [ ] Create `after_payment_insert` trigger
- [ ] Create `after_payment_update` trigger
- [ ] Add indexes for performance
- [ ] Test schema with sample data
- [ ] Create database migration script
- [ ] Create rollback script

### Backend Development
- [ ] Install Composer (if not installed)
- [ ] Install Dompdf via Composer
- [ ] Create `includes/pdf_config.php`
- [ ] Create `services/PaymentService.php`
  - [ ] `recordPayment()` method
  - [ ] `getPaymentHistory()` method
  - [ ] `getAllPayments()` method
  - [ ] `refundPayment()` method
  - [ ] `validatePayment()` method
- [ ] Create `services/ReceiptService.php`
  - [ ] `generateReceiptNumber()` method
  - [ ] `generateReceiptPDF()` method
  - [ ] `reprintReceipt()` method
  - [ ] `cancelReceipt()` method
- [ ] Create `api/payment_process.php`
- [ ] Create `api/payment_history.php`
- [ ] Create `api/payment_list.php`
- [ ] Create `api/receipt_generate.php`
- [ ] Create `api/receipt_print.php`
- [ ] Test all API endpoints

### Receipt Template
- [ ] Design receipt HTML template
- [ ] Create `templates/receipts/official-receipt.php`
- [ ] Create `templates/receipts/receipt-header.php`
- [ ] Create `templates/receipts/receipt-footer.php`
- [ ] Create `assets/css/receipt.css`
- [ ] Add LGU logo/header
- [ ] Add QR code generation
- [ ] Test PDF generation
- [ ] Test print layout
- [ ] Optimize for thermal printers (optional)

### Frontend Development
- [ ] Create `public/payments.php` (payment management page)
- [ ] Create payment dashboard cards
- [ ] Create payment search/filter form
- [ ] Create payment list table
- [ ] Create `assets/js/payments.js`
- [ ] Create `assets/css/payments.css`
- [ ] Create payment recording modal
- [ ] Create `templates/payments/payment-modal.php`
- [ ] Create `assets/js/payment-modal.js`
- [ ] Create receipt preview modal
- [ ] Create `assets/js/receipt-print.js`
- [ ] Add form validation
- [ ] Add AJAX payment submission
- [ ] Test responsive design

### Integration
- [ ] Add "Record Payment" button to citation details page
- [ ] Display payment status on citation list
- [ ] Display payment history on citation details
- [ ] Add "Payments" to sidebar navigation
- [ ] Update financial reports with payment breakdown
- [ ] Add cashier performance report
- [ ] Test end-to-end payment workflow
- [ ] Test receipt generation workflow
- [ ] Test reprint functionality

### Testing
- [ ] Test payment recording with various methods (cash, check, online)
- [ ] Test OR number generation uniqueness
- [ ] Test receipt PDF generation
- [ ] Test receipt printing
- [ ] Test payment validation
- [ ] Test duplicate payment prevention
- [ ] Test access control (admin vs. enforcer vs. user)
- [ ] Test error handling
- [ ] Test database triggers
- [ ] Test with multiple concurrent payments
- [ ] Cross-browser testing
- [ ] Mobile responsive testing
- [ ] Print layout testing

### Documentation
- [x] Create implementation plan (this file)
- [ ] Document API endpoints
- [ ] Document database schema
- [ ] Create user guide for cashiers
- [ ] Create admin guide for payment management
- [ ] Update README.md

### Deployment
- [ ] Backup current database
- [ ] Run database migrations
- [ ] Install Composer dependencies
- [ ] Configure PDF settings
- [ ] Configure receipt header/footer (LGU name, etc.)
- [ ] Test in production environment
- [ ] Train users on new payment system
- [ ] Monitor for issues

---

## Configuration & Settings

### Environment Configuration

#### PDF Configuration (`includes/pdf_config.php`)
```php
// PDF settings
define('PDF_FONT_NAME', 'helvetica');
define('PDF_FONT_SIZE', 10);
define('PDF_MARGIN_TOP', 10);
define('PDF_MARGIN_BOTTOM', 10);
define('PDF_MARGIN_LEFT', 10);
define('PDF_MARGIN_RIGHT', 10);

// Receipt settings
define('RECEIPT_LOGO_PATH', __DIR__ . '/../assets/images/logo.png');
define('RECEIPT_LGU_NAME', 'Municipality of Baggao');
define('RECEIPT_LGU_ADDRESS', 'Baggao, Cagayan, Philippines');
define('RECEIPT_LGU_CONTACT', 'Tel: (078) 844-1234');
define('RECEIPT_COPIES', 3); // Original, Duplicate, Triplicate

// OR number format
define('OR_NUMBER_PREFIX', 'OR-');
define('OR_NUMBER_YEAR_FORMAT', 'Y');
define('OR_NUMBER_PADDING', 6);
```

#### Payment Settings
```php
// Payment methods
$PAYMENT_METHODS = [
    'cash' => 'Cash',
    'check' => 'Check',
    'online' => 'Online Transfer',
    'gcash' => 'GCash',
    'paymaya' => 'PayMaya',
    'bank_transfer' => 'Bank Transfer',
    'money_order' => 'Money Order'
];

// Payment validation
define('ALLOW_PARTIAL_PAYMENT', false);
define('ALLOW_OVERPAYMENT', false);
define('MINIMUM_PAYMENT_AMOUNT', 1.00);
define('MAXIMUM_PAYMENT_AMOUNT', 999999.99);
```

---

## Frequently Asked Questions (FAQ)

### Q1: What happens if payment is recorded but receipt generation fails?
**A:** Payment is still recorded in database with status. Receipt can be regenerated from payment record. Always log errors and notify admin.

### Q2: Can we reprint receipts?
**A:** Yes, receipts can be reprinted. The system tracks print count and last printed date. Reprints should be marked as "DUPLICATE COPY".

### Q3: How do we handle refunds?
**A:** Create a refund function that:
1. Updates payment status to 'refunded'
2. Does NOT delete payment record (audit trail)
3. Reverses citation status back to 'pending'
4. Logs refund reason and who approved it
5. Generates refund receipt

### Q4: Can cashiers delete or modify payments?
**A:** No. Only admins can void payments. All modifications are logged in audit trail.

### Q5: What if we need to change OR number format?
**A:** Update the `ReceiptService::generateReceiptNumber()` method and configuration constants. Existing receipts keep their numbers.

### Q6: How to handle bounced checks?
**A:**
1. Update payment status to 'failed'
2. Revert citation status to 'pending'
3. Add notes about bounced check
4. Generate new citation if needed

### Q7: Can we accept partial payments?
**A:** Currently not implemented. To add:
1. Set `ALLOW_PARTIAL_PAYMENT = true`
2. Add `balance_remaining` field to citations table
3. Update payment validation logic
4. Show remaining balance on citation

### Q8: How to backup payment data?
**A:** Regularly backup database. Export payments to CSV monthly. Keep physical copies of receipts if required by policy.

---

## Next Steps

### Immediate Actions (Do This First)
1. **Review this document** with your team/stakeholders
2. **Decide on receipt format** and branding (LGU name, logo, etc.)
3. **Confirm OR number format** preference
4. **Identify who will have payment recording access** (admin only, or cashiers too)
5. **Determine payment methods** to support

### Development Sequence
1. Start with **Phase 1 (Database)** - Foundation must be solid
2. Move to **Phase 2 (Backend Services)** - Business logic
3. Implement **Phase 3 (PDF Library)** - Receipt generation
4. Build **Phase 4 (Receipt Template)** - Visual design
5. Develop **Phase 5 (Frontend UI)** - User interface
6. Complete **Phase 6 (Integration)** - Tie everything together

### Estimated Total Implementation Time
- **Minimum (basic features):** 6-8 hours
- **Complete (all features):** 12-16 hours
- **With testing & refinement:** 20-24 hours

---

## Support & Maintenance

### Common Issues & Solutions

**Issue:** OR numbers not sequential
- **Solution:** Check `receipt_sequence` table, ensure transaction locking

**Issue:** PDF not generating
- **Solution:** Check Composer installation, verify Dompdf library, check file permissions

**Issue:** Receipt printing cuts off
- **Solution:** Adjust CSS margins, check page size settings, test with different browsers

**Issue:** Payment not updating citation status
- **Solution:** Check database trigger, verify trigger is enabled, check citation_id foreign key

---

## Version History

- **v1.0** (2025-11-25) - Initial implementation plan created
- **v1.1** (TBD) - After Phase 1-3 completion
- **v2.0** (TBD) - After full implementation
- **v2.1** (TBD) - Feature enhancements (partial payments, online payment gateway)

---

## Additional Resources

### PHP PDF Libraries
- **Dompdf:** https://github.com/dompdf/dompdf
- **TCPDF:** https://tcpdf.org/
- **mPDF:** https://mpdf.github.io/

### Payment Gateway Integration (Future)
- PayMongo (Philippines)
- PayPal
- Stripe
- GCash API
- PayMaya API

### Barcode/QR Code Libraries
- PHP QR Code: https://github.com/chillerlan/php-qrcode
- Barcode Generator: https://github.com/picqer/php-barcode-generator

---

## Contact & Feedback

For questions about this implementation:
- Review this document
- Check existing codebase in `c:/xampp/htdocs/tmg/`
- Test each phase before moving to next
- Document any deviations from this plan

**Good luck with the implementation! 🚀**
