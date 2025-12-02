# Voided Payments Fix - Documentation

## Issue Summary

**Problem:** When a payment was voided (cancelled from pending_print status), the citation status was not being reverted back to 'pending'. This caused:

1. The voided payment shows as "voided" in [payments.php](public/payments.php)
2. BUT the citation still shows as "paid" or stays in an incorrect status in [citations.php](public/citations.php)
3. The citation does NOT appear in [process_payment.php](public/process_payment.php) for re-processing

**Example Case:** Ticket #06122 had a voided payment but the citation status was stuck, preventing re-payment.

---

## Root Cause

The `voidPayment()` method in [services/payment/PaymentProcessor.php](services/payment/PaymentProcessor.php) was:
- ✅ Updating payment status to 'voided'
- ✅ Updating receipt status to 'void'
- ✅ Logging to audit trail
- ❌ **NOT updating citation status back to 'pending'**

Compare with the `refundPayment()` method in [services/payment/RefundHandler.php](services/payment/RefundHandler.php) which correctly reverts the citation status.

---

## Solution Applied

### 1. Code Fix (COMPLETED)

**File:** [services/payment/PaymentProcessor.php](services/payment/PaymentProcessor.php)

**Change:** Added citation status update in the `voidPayment()` method at line 472-478:

```php
// Revert citation status back to pending
$this->updateCitationStatus(
    $payment['citation_id'],
    'pending',
    $userId,
    'Payment voided: ' . $reason
);
```

This ensures that when a payment is voided:
- Payment status → 'voided'
- Receipt status → 'void'
- **Citation status → 'pending'** ✅ (NEW)
- Payment date → NULL
- Audit trail is logged

### 2. Database Fix (TO BE RUN)

**File:** [fix_voided_payments.sql](fix_voided_payments.sql)

**Purpose:** Fix existing citations that have voided payments but incorrect status

**Instructions:**

1. Open phpMyAdmin (http://localhost/phpmyadmin/)
2. Select the `traffic_system` database
3. Go to the "SQL" tab
4. Copy and paste the contents of `fix_voided_payments.sql`
5. Click "Go" to execute

**What it does:**
- Finds all citations with voided payments
- Updates their status to 'pending'
- Clears their payment_date
- Shows before/after verification
- Provides a summary report

---

## Verification Steps

After running the SQL fix, verify the following:

### 1. Check Ticket 06122

```sql
SELECT
    c.citation_id,
    c.ticket_number,
    c.status AS citation_status,
    p.payment_id,
    p.receipt_number,
    p.status AS payment_status
FROM citations c
LEFT JOIN payments p ON c.citation_id = p.citation_id
WHERE c.ticket_number = '06122'
ORDER BY p.payment_id DESC;
```

**Expected Result:**
- Citation status: `pending`
- Payment status: `voided` (or multiple rows if multiple payment attempts)
- Payment date: `NULL`

### 2. Check Process Payment Page

1. Login as cashier/admin
2. Navigate to [Process Payments](public/process_payment.php)
3. Search for ticket "06122"
4. **Expected:** Ticket should now appear in the list of pending citations

### 3. Check Payments Page

1. Navigate to [Payment Management](public/payments.php)
2. Filter by status "Voided"
3. **Expected:** All voided payments should be visible
4. The citation numbers should now show as "pending" if you check them

### 4. Check Citations Page

1. Navigate to [View Citations](public/citations.php)
2. Filter by status "Pending"
3. **Expected:** Ticket 06122 should appear in the pending list

---

## Files Modified

### PHP Files
1. ✅ [services/payment/PaymentProcessor.php](services/payment/PaymentProcessor.php) - Lines 472-478 added

### New Files Created
1. ✅ [fix_voided_payments.sql](fix_voided_payments.sql) - Database fix script
2. ✅ [VOIDED_PAYMENTS_FIX_README.md](VOIDED_PAYMENTS_FIX_README.md) - This documentation

---

## How It Works (Technical Details)

### Frontend Query Logic

Both [process_payment.php](public/process_payment.php) and the API endpoint [api/pending_citations.php](api/pending_citations.php) use this query pattern:

```sql
SELECT c.*, ...
FROM citations c
LEFT JOIN payments p ON c.citation_id = p.citation_id
    AND p.status IN ('pending_print', 'completed')
WHERE c.status = 'pending'
  AND p.payment_id IS NULL
```

**Key Points:**
- Only joins payments with status `pending_print` or `completed`
- Voided payments won't match the join condition
- Result: `p.payment_id IS NULL` for citations with voided payments
- These citations appear in the pending list ✅

### Status Flow

```
Citation Created
    ↓
Status: 'pending'
    ↓
Payment Processed → Status: 'pending_print'
    ↓
    ├─ Receipt Printed → Payment: 'completed' → Citation: 'paid' ✅
    │
    └─ Print Failed → Payment: 'voided' → Citation: 'pending' ✅ (FIXED)
```

---

## Testing the Fix

### Test Scenario 1: New Voided Payment

1. Create a new citation
2. Process payment (status becomes `pending_print`)
3. Void the payment from [pending_print_payments.php](public/pending_print_payments.php)
4. **Verify:** Citation status automatically reverts to 'pending'
5. **Verify:** Citation reappears in [process_payment.php](public/process_payment.php)

### Test Scenario 2: Re-process Previously Voided Citation

1. Find a citation with a voided payment (e.g., ticket 06122)
2. After running SQL fix, it should appear in [process_payment.php](public/process_payment.php)
3. Process a new payment for the same citation
4. **Verify:** New payment is created successfully
5. **Verify:** Citation can be paid normally

---

## Related Files Reference

### Service Layer
- [services/PaymentService.php](services/PaymentService.php) - Main payment service facade
- [services/payment/PaymentProcessor.php](services/payment/PaymentProcessor.php) - **MODIFIED** - Core payment processing
- [services/payment/RefundHandler.php](services/payment/RefundHandler.php) - Reference for correct status update pattern

### API Endpoints
- [api/payments/void_payment.php](api/payments/void_payment.php) - Void payment endpoint
- [api/payments/finalize_payment.php](api/payments/finalize_payment.php) - Complete pending_print payments
- [api/pending_citations.php](api/pending_citations.php) - Get pending citations (AJAX)

### Frontend Pages
- [public/process_payment.php](public/process_payment.php) - Process new payments
- [public/payments.php](public/payments.php) - View all payments
- [public/citations.php](public/citations.php) - View all citations
- [public/pending_print_payments.php](public/pending_print_payments.php) - Manage stuck payments

---

## Important Notes

⚠️ **BEFORE YOU START:**
- Backup your database before running the SQL fix
- Test in a development environment first if possible
- The SQL script is safe and only updates citations with voided payments

✅ **AFTER RUNNING SQL FIX:**
- All future voided payments will automatically revert citation status (code fix)
- All existing voided payments will have correct citation status (SQL fix)
- Citations can be re-processed for payment normally

🔒 **SECURITY:**
- Only cashiers and admins can void payments (enforced by `can_process_payment()`)
- All voids are logged in the audit trail
- CSRF tokens protect the void payment endpoint

---

## Summary

### What Was Fixed
1. ✅ Code bug in `PaymentProcessor::voidPayment()` - now updates citation status
2. ✅ Existing data inconsistencies - SQL script fixes all historical voided payments

### What To Do Next
1. Run the SQL fix script in phpMyAdmin
2. Verify ticket 06122 now appears in process_payment.php
3. Test voiding a new payment to confirm automatic status reversion

### Expected Outcome
- ✅ Voided payments now correctly revert citations to 'pending' status
- ✅ Pending citations with voided payments appear in process_payment.php
- ✅ Citations can be re-processed for payment after voiding
- ✅ All three pages (payments.php, process_payment.php, citations.php) show consistent status

---

**Date Fixed:** November 29, 2025
**Developer:** Claude Code
**Issue Reported By:** System Administrator
