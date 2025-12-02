# Payment & Receipt System - Setup Guide

## Quick Start Installation

### Step 1: Run Database Migrations

Open phpMyAdmin or MySQL command line and run these SQL files in order:

```bash
# 1. Create payment tables
mysql -u root traffic_system < database/migrations/add_payment_tables.sql

# 2. Create payment triggers
mysql -u root traffic_system < database/migrations/add_payment_triggers.sql
```

**OR** use the provided installation script:

```bash
cd c:\xampp\htdocs\tmg
php install_payment_system.php
```

### Step 2: Verify Installation

1. Check if tables were created:
```sql
USE traffic_system;
SHOW TABLES LIKE '%payment%';
SHOW TABLES LIKE '%receipt%';
```

You should see:
- `payments`
- `receipts`
- `receipt_sequence`
- `payment_audit`

2. Check if triggers were created:
```sql
SHOW TRIGGERS WHERE `Trigger` LIKE '%payment%';
```

### Step 3: Configure Receipt Settings

Edit `includes/pdf_config.php` to customize:

```php
// Update these with your LGU information
define('RECEIPT_LGU_NAME', 'Municipality of Baggao');
define('RECEIPT_LGU_PROVINCE', 'Province of Cagayan');
define('RECEIPT_LGU_ADDRESS', 'Baggao, Cagayan, Philippines');
define('RECEIPT_LGU_CONTACT', 'Tel: (078) 844-1234');
define('RECEIPT_LGU_EMAIL', 'info@baggao.gov.ph');
```

### Step 4: Add Logo (Optional)

1. Place your LGU logo at: `assets/images/logo.png`
2. Recommended size: 80x80 pixels, PNG format
3. The system will auto-detect and display it on receipts

### Step 5: Test the System

1. **Access Payment Management:**
   - URL: `http://localhost/tmg/public/payments.php`
   - Login with your admin account

2. **Test Payment Recording:**
   - Go to a citation with pending status
   - Click "Record Payment" button
   - Fill in payment details
   - Submit and verify receipt generation

3. **Verify Receipt PDF:**
   - Check if PDF downloads correctly
   - Verify all information is displayed
   - Test print functionality

## File Structure

```
tmg/
├── database/
│   └── migrations/
│       ├── add_payment_tables.sql          ✓ Created
│       ├── add_payment_triggers.sql        ✓ Created
│       └── rollback_payment_tables.sql     ✓ Created
│
├── services/
│   ├── PaymentService.php                  ✓ Created
│   └── ReceiptService.php                  ✓ Created
│
├── api/
│   ├── payment_process.php                 ✓ Created
│   ├── payment_history.php                 ✓ Created
│   ├── payment_list.php                    ✓ Created
│   ├── receipt_generate.php                ✓ Created
│   └── receipt_print.php                   ✓ Created
│
├── public/
│   └── payments.php                        ✓ Created
│
├── templates/
│   ├── receipts/
│   │   └── official-receipt.php            ✓ Created
│   └── payments/
│       └── payment-modal.php               ✓ Created
│
├── assets/
│   ├── css/
│   │   ├── payments.css                    ✓ Created
│   │   └── receipt.css                     ✓ Created
│   └── js/
│       ├── payments.js                     ✓ Created
│       └── payment-modal.js                ✓ Created
│
├── includes/
│   └── pdf_config.php                      ✓ Created
│
└── vendor/                                  ✓ Dompdf installed
```

## Integration with Existing Pages

### Add Payment Button to Citation List

In your citation list template, add this button for pending citations:

```php
<?php if ($citation['status'] === 'pending'): ?>
    <button class="btn btn-success btn-sm"
            onclick="openPaymentModal({
                citation_id: <?= $citation['citation_id'] ?>,
                ticket_number: '<?= $citation['ticket_number'] ?>',
                driver_name: '<?= $citation['driver_name'] ?>',
                license_number: '<?= $citation['license_number'] ?>',
                plate_number: '<?= $citation['plate_number'] ?>',
                citation_date: '<?= $citation['citation_date'] ?>',
                total_fine: <?= $citation['total_fine'] ?>,
                status: '<?= $citation['status'] ?>'
            })">
        <i class="fas fa-money-bill-wave"></i> Record Payment
    </button>
<?php endif; ?>
```

### Include Payment Modal in Pages

At the bottom of your citation pages, include:

```php
<?php include '../templates/payments/payment-modal.php'; ?>
<script src="../assets/js/payment-modal.js"></script>
```

### Add to Navigation Menu

Add to your sidebar navigation:

```php
<li class="nav-item">
    <a class="nav-link" href="payments.php">
        <i class="fas fa-money-bill-wave"></i>
        Payments
    </a>
</li>
```

## Features Included

### ✅ Payment Management
- Record payments for citations
- Multiple payment methods (Cash, Check, Online, GCash, etc.)
- Payment validation (prevents duplicate payments)
- Auto-update citation status to "paid"
- Payment audit trail

### ✅ Receipt Generation
- Auto-generate OR (Official Receipt) numbers
- Format: OR-YYYY-NNNNNN (e.g., OR-2025-000001)
- Professional PDF receipts with Dompdf
- QR code for verification
- Multiple copies (Original, Duplicate, Triplicate)

### ✅ Payment Reports
- Today's collections dashboard
- Weekly and monthly statistics
- Filter by date range, payment method, cashier
- Search by receipt number or ticket number
- Export to CSV

### ✅ Receipt Management
- View receipts
- Print receipts
- Download PDF
- Reprint receipts (tracked with count)
- Cancel/void receipts

## Database Tables

### 1. payments
Stores all payment transactions
- `payment_id` - Primary key
- `citation_id` - Foreign key to citations
- `amount_paid` - Payment amount
- `payment_method` - Cash, Check, Online, etc.
- `receipt_number` - OR number
- `collected_by` - User who collected payment
- `status` - completed, pending, refunded, cancelled

### 2. receipts
Tracks receipt generation and printing
- `receipt_id` - Primary key
- `payment_id` - Foreign key to payments
- `print_count` - Number of times printed
- `status` - active, cancelled, void

### 3. receipt_sequence
Manages OR number generation
- `current_year` - Current year
- `current_number` - Current sequence number
- Auto-resets to 1 every new year

### 4. payment_audit
Audit trail for all payment changes
- `audit_id` - Primary key
- `payment_id` - Foreign key to payments
- `action` - created, updated, refunded, cancelled
- `old_values` - JSON of previous values
- `new_values` - JSON of new values

## API Endpoints

### Payment APIs
- `POST /api/payment_process.php` - Record new payment
- `GET /api/payment_history.php?citation_id=X` - Get payment history
- `GET /api/payment_list.php` - List all payments with filters

### Receipt APIs
- `GET /api/receipt_generate.php?payment_id=X` - Generate receipt PDF
- `GET /api/receipt_print.php?receipt_id=X` - Print/reprint receipt

## Security Features

- ✅ CSRF token protection
- ✅ User authentication required
- ✅ SQL injection prevention (PDO prepared statements)
- ✅ Input validation and sanitization
- ✅ Duplicate payment prevention
- ✅ Payment amount validation
- ✅ Audit trail for all transactions
- ✅ Receipt number uniqueness enforcement

## Configuration Options

### Payment Methods
Edit in `api/payment_process.php`:
```php
$validMethods = ['cash', 'check', 'online', 'gcash', 'paymaya', 'bank_transfer', 'money_order'];
```

### OR Number Format
Edit in `includes/pdf_config.php`:
```php
define('OR_NUMBER_PREFIX', 'OR-');        // Prefix
define('OR_NUMBER_YEAR_FORMAT', 'Y');     // Year format
define('OR_NUMBER_PADDING', 6);           // Digit count
```

### Receipt Copies
```php
define('RECEIPT_COPIES', 3);              // Number of copies
define('RECEIPT_COPY_NAMES', [
    1 => 'ORIGINAL COPY',
    2 => 'DUPLICATE COPY',
    3 => 'TRIPLICATE COPY'
]);
```

## Troubleshooting

### Issue: PDF not generating
**Solution:**
1. Check if Dompdf is installed: `composer show dompdf/dompdf`
2. Verify vendor/autoload.php exists
3. Check PHP error log: `php_errors.log`

### Issue: OR numbers not sequential
**Solution:**
1. Check `receipt_sequence` table exists
2. Run: `SELECT * FROM receipt_sequence;`
3. If empty, run: `INSERT INTO receipt_sequence VALUES (1, 2025, 0, NOW());`

### Issue: Payment not updating citation status
**Solution:**
1. Check if triggers exist: `SHOW TRIGGERS;`
2. Re-run `add_payment_triggers.sql`
3. Verify payment status is 'completed'

### Issue: "Access Denied" errors
**Solution:**
1. Ensure you're logged in
2. Check user role (must be admin or enforcer)
3. Verify session is active

## Testing Checklist

- [ ] Database tables created successfully
- [ ] Database triggers working
- [ ] Can access payments page
- [ ] Can view payment statistics
- [ ] Can filter/search payments
- [ ] Can record a cash payment
- [ ] Can record a check payment
- [ ] Receipt PDF generates correctly
- [ ] Receipt number is sequential
- [ ] Citation status updates to "paid"
- [ ] Can print receipt
- [ ] Can download receipt
- [ ] Can reprint receipt
- [ ] Print count increments
- [ ] QR code displays on receipt
- [ ] All citation details show on receipt
- [ ] All violation details show on receipt

## Rollback Instructions

If you need to remove the payment system:

```sql
-- Run this to remove all payment tables and triggers
source database/migrations/rollback_payment_tables.sql
```

**WARNING:** This will permanently delete all payment data!

## Support

For issues or questions:
1. Check this setup guide
2. Review `PAYMENT_RECEIPT_IMPLEMENTATION.md`
3. Check PHP error log: `c:\xampp\htdocs\tmg\php_errors.log`
4. Verify database connection in `includes/config.php`

## Next Steps

After successful installation:

1. **Configure Receipt Header**
   - Update LGU name and address in `includes/pdf_config.php`
   - Add logo to `assets/images/logo.png`

2. **Test All Features**
   - Record test payment
   - Generate test receipt
   - Verify data in database

3. **Train Users**
   - Show cashiers how to record payments
   - Demonstrate receipt printing
   - Explain payment methods

4. **Monitor Performance**
   - Check payment statistics daily
   - Review audit trail
   - Verify receipt sequence

## Version Information

- **Created:** 2025-11-25
- **PHP Version:** 8.2.12
- **Database:** MySQL/MariaDB
- **PDF Library:** Dompdf 3.1.4
- **Bootstrap:** 5.3.3

---

**Installation Complete! 🎉**

You now have a fully functional payment and receipt management system.
