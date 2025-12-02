# 🚀 Payment System - Quick Reference Guide

## ⚡ 3-Step Installation

```bash
# Step 1: Open in browser
http://localhost/tmg/install_payment_system.php

# Step 2: Configure (optional)
Edit: includes/pdf_config.php

# Step 3: Start using
http://localhost/tmg/public/payments.php
```

---

## 📁 All Files Created

### Database (3 files)
```
database/migrations/
├── add_payment_tables.sql       → Creates 4 tables
├── add_payment_triggers.sql     → Creates 3 triggers
└── rollback_payment_tables.sql  → Rollback script
```

### Backend Services (3 files)
```
services/
├── PaymentService.php           → Payment logic
└── ReceiptService.php           → Receipt generation

includes/
└── pdf_config.php               → PDF configuration
```

### API Endpoints (5 files)
```
api/
├── payment_process.php          → Record payment
├── payment_history.php          → Get payment history
├── payment_list.php             → List all payments
├── receipt_generate.php         → Generate receipt PDF
└── receipt_print.php            → Print/reprint receipt
```

### User Interface (3 files)
```
public/
└── payments.php                 → Payment management page

templates/
├── payments/
│   └── payment-modal.php        → Payment recording modal
└── receipts/
    └── official-receipt.php     → Receipt PDF template
```

### Frontend Assets (4 files)
```
assets/
├── css/
│   ├── payments.css             → Payment page styles
│   └── receipt.css              → Receipt styles
└── js/
    ├── payments.js              → Payment list JS
    └── payment-modal.js         → Payment modal JS
```

### Documentation (4 files)
```
├── PAYMENT_RECEIPT_IMPLEMENTATION.md  → Technical docs
├── PAYMENT_SYSTEM_SETUP.md            → Setup guide
├── IMPLEMENTATION_COMPLETE.md         → Feature summary
├── QUICK_REFERENCE.md                 → This file
└── install_payment_system.php         → Auto installer
```

### Dependencies
```
├── composer.json                → Dependency config
├── composer.lock                → Locked versions
└── vendor/                      → Dompdf library
```

**Total:** 30+ files created

---

## 🗄️ Database Tables

| Table | Purpose | Key Fields |
|-------|---------|------------|
| `payments` | Payment records | payment_id, citation_id, receipt_number, amount_paid |
| `receipts` | Receipt tracking | receipt_id, payment_id, print_count, status |
| `receipt_sequence` | OR number generation | current_year, current_number |
| `payment_audit` | Audit trail | audit_id, payment_id, action, old_values, new_values |

---

## 🔌 API Endpoints

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/api/payment_process.php` | POST | Record new payment |
| `/api/payment_history.php?citation_id=X` | GET | Get payment history |
| `/api/payment_list.php` | GET | List payments with filters |
| `/api/receipt_generate.php?payment_id=X` | GET | Generate receipt PDF |
| `/api/receipt_print.php?receipt_id=X` | GET | Print/reprint receipt |

---

## 💳 Payment Methods Supported

- ✅ Cash
- ✅ Check (with check details)
- ✅ Online Transfer
- ✅ GCash
- ✅ PayMaya
- ✅ Bank Transfer
- ✅ Money Order

---

## 🧾 Receipt Number Format

```
OR-YYYY-NNNNNN

Examples:
OR-2025-000001
OR-2025-000002
OR-2025-999999
OR-2026-000001  (resets in new year)
```

---

## 🎯 Key Features

### Payment Recording
- ✅ Record payments for citations
- ✅ Multiple payment methods
- ✅ Payment validation
- ✅ Duplicate prevention
- ✅ Auto-update citation status
- ✅ Complete audit trail

### Receipt Generation
- ✅ Auto-generate OR numbers
- ✅ Professional PDF receipts
- ✅ QR code verification
- ✅ Multiple copies support
- ✅ LGU logo and header
- ✅ Security watermark

### Payment Management
- ✅ Payment dashboard
- ✅ Search and filters
- ✅ Date range filter
- ✅ Payment statistics
- ✅ Export to CSV
- ✅ Print functionality

### Receipt Management
- ✅ View receipts
- ✅ Print receipts
- ✅ Download PDF
- ✅ Reprint tracking
- ✅ Receipt verification

---

## ⚙️ Configuration Files

### Receipt Header Settings
**File:** `includes/pdf_config.php`
```php
define('RECEIPT_LGU_NAME', 'Municipality of Baggao');
define('RECEIPT_LGU_ADDRESS', 'Baggao, Cagayan, Philippines');
define('RECEIPT_LGU_CONTACT', 'Tel: (078) 844-1234');
```

### OR Number Format
**File:** `includes/pdf_config.php`
```php
define('OR_NUMBER_PREFIX', 'OR-');
define('OR_NUMBER_PADDING', 6);
```

### Payment Methods
**File:** `api/payment_process.php`
```php
$validMethods = ['cash', 'check', 'online', 'gcash', 'paymaya'];
```

---

## 🔗 Important URLs

```
Installation:     http://localhost/tmg/install_payment_system.php
Payment Page:     http://localhost/tmg/public/payments.php
Citations Page:   http://localhost/tmg/public/citations.php
```

---

## 🚨 Common Commands

### Install System
```bash
http://localhost/tmg/install_payment_system.php
```

### Rollback (if needed)
```sql
SOURCE database/migrations/rollback_payment_tables.sql
```

### Reinstall Dompdf
```bash
cd c:\xampp\htdocs\tmg
composer require dompdf/dompdf
```

### Check Tables
```sql
SHOW TABLES LIKE '%payment%';
SHOW TABLES LIKE '%receipt%';
```

### Check Triggers
```sql
SHOW TRIGGERS WHERE `Trigger` LIKE '%payment%';
```

### View Receipt Sequence
```sql
SELECT * FROM receipt_sequence;
```

### Reset OR Number (BE CAREFUL!)
```sql
UPDATE receipt_sequence SET current_number = 0 WHERE id = 1;
```

---

## 📊 Database Triggers

| Trigger | Event | Purpose |
|---------|-------|---------|
| `after_payment_insert` | After INSERT on payments | Update citation status to 'paid', create audit log |
| `after_payment_update` | After UPDATE on payments | Log changes, revert citation if refunded |
| `before_receipt_print` | Before UPDATE on receipts | Update print tracking and count |

---

## 🎨 Customization Quick Guide

### Change Logo
```
1. Save logo as: assets/images/logo.png
2. Size: 80x80 pixels
3. Format: PNG
```

### Change LGU Name
```
Edit: includes/pdf_config.php
Line: define('RECEIPT_LGU_NAME', 'Your Name Here');
```

### Change OR Format
```
Edit: includes/pdf_config.php
Lines:
  define('OR_NUMBER_PREFIX', 'OR-');
  define('OR_NUMBER_PADDING', 6);
```

### Add Payment Method
```
Edit: api/payment_process.php
Add to: $validMethods array
```

---

## 🧪 Testing Workflow

1. **Install Database**
   - Run: `install_payment_system.php`
   - Verify 4 tables created

2. **Record Payment**
   - Go to citations page
   - Click "Record Payment" on pending citation
   - Fill form, submit

3. **Verify Receipt**
   - Check PDF downloads
   - Verify OR number (OR-2025-000001)
   - Check all details appear

4. **Check Database**
   - Verify payment in `payments` table
   - Verify receipt in `receipts` table
   - Check citation status = 'paid'

5. **Test Reprint**
   - Click print icon
   - Verify print_count increments

---

## 📱 Integration Code

### Add Payment Button to Citation
```php
<button onclick="openPaymentModal({
    citation_id: <?= $citation['citation_id'] ?>,
    ticket_number: '<?= $citation['ticket_number'] ?>',
    driver_name: '<?= $citation['driver_name'] ?>',
    total_fine: <?= $citation['total_fine'] ?>,
    status: '<?= $citation['status'] ?>'
})">
    Record Payment
</button>
```

### Include Payment Modal
```php
<?php include '../templates/payments/payment-modal.php'; ?>
<script src="../assets/js/payment-modal.js"></script>
```

---

## 🔒 Security Checklist

- ✅ CSRF token protection
- ✅ SQL injection prevention (PDO)
- ✅ Input validation
- ✅ Duplicate payment prevention
- ✅ Amount validation
- ✅ User authentication required
- ✅ Audit trail enabled
- ✅ Receipt number uniqueness

---

## 📝 Quick Troubleshooting

| Problem | Solution |
|---------|----------|
| PDF not generating | Check Dompdf: `composer show dompdf/dompdf` |
| OR numbers not sequential | Check `receipt_sequence` table |
| Citation not updating | Check trigger: `SHOW TRIGGERS;` |
| Access denied | Check login and user role |
| Database error | Check `includes/config.php` |

---

## 📚 Documentation Files

| File | Purpose |
|------|---------|
| `PAYMENT_RECEIPT_IMPLEMENTATION.md` | Complete technical docs |
| `PAYMENT_SYSTEM_SETUP.md` | Installation guide |
| `IMPLEMENTATION_COMPLETE.md` | Feature summary |
| `QUICK_REFERENCE.md` | This file |

---

## ✅ Verification Checklist

After installation, verify:

- [ ] 4 tables created (payments, receipts, receipt_sequence, payment_audit)
- [ ] 3 triggers created (after_payment_insert, after_payment_update, before_receipt_print)
- [ ] Dompdf installed (`vendor/dompdf/` exists)
- [ ] Can access `public/payments.php`
- [ ] Can record a payment
- [ ] Receipt PDF generates
- [ ] OR number is sequential
- [ ] Citation status updates
- [ ] Can print receipt
- [ ] Print count increments

---

## 🎓 Key Classes & Methods

### PaymentService
```php
$paymentService->recordPayment($citationId, $amount, $method, $userId)
$paymentService->getPaymentHistory($citationId)
$paymentService->getAllPayments($filters)
$paymentService->validatePayment($citationId, $amount)
$paymentService->refundPayment($paymentId, $reason, $userId)
```

### ReceiptService
```php
$receiptService->generateReceiptPDF($paymentId, $copyNumber)
$receiptService->getReceiptData($paymentId)
$receiptService->reprintReceipt($receiptId, $userId)
$receiptService->verifyReceipt($receiptNumber)
$receiptService->cancelReceipt($receiptId, $reason, $userId)
```

---

## 🚀 Production Deployment

Before going live:

1. **Security**
   - [ ] Delete `install_payment_system.php`
   - [ ] Change default admin password
   - [ ] Enable HTTPS
   - [ ] Set proper file permissions

2. **Configuration**
   - [ ] Update LGU information
   - [ ] Add logo
   - [ ] Configure OR number format
   - [ ] Set payment methods

3. **Testing**
   - [ ] Test all payment methods
   - [ ] Test receipt generation
   - [ ] Test reprint functionality
   - [ ] Test on different browsers

4. **Backup**
   - [ ] Backup database
   - [ ] Backup files
   - [ ] Test restore procedure

5. **Training**
   - [ ] Train cashiers
   - [ ] Prepare user manual
   - [ ] Setup support system

---

## 💡 Pro Tips

1. **Regular Backups**
   - Export payments to CSV weekly
   - Backup database daily
   - Keep physical receipt copies if required

2. **Monitor OR Numbers**
   - Check sequence regularly
   - Verify no gaps
   - Plan for year rollover

3. **Audit Trail**
   - Review `payment_audit` table monthly
   - Monitor for unusual activity
   - Keep logs for compliance

4. **Performance**
   - Index frequently searched fields
   - Archive old payments annually
   - Optimize large queries

---

**Everything is ready! Install and start using! 🎉**

**Quick Start:** `http://localhost/tmg/install_payment_system.php`
