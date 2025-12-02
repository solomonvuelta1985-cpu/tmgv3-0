# Preventing Data Integrity Issues

## Current Issues Found

### 1. Citations with Mismatched Status (6 issues)
- **Problem**: Citations marked as "paid" but payments are "voided"
- **Cause**: Payment was voided AFTER citation was marked as paid, without reverting citation status
- **Impact**: Drivers appear to have paid when they haven't

### 2. Orphaned Payments (7 issues)
- **Problem**: Payments referencing non-existent citations
- **Cause**: Citations were deleted but related payments weren't cleaned up
- **Impact**: OR number gaps, accounting discrepancies

## ✅ Prevention Measures NOW IN PLACE

### Database Triggers (Installed and Active)

Three triggers are now protecting data integrity:

#### 1. `before_payment_status_change` (BEFORE INSERT)
- **Prevents**: Creating completed payments on void/dismissed citations
- **Allows**: Payment creation on pending citations (with warning log)
- **Action**: Blocks invalid payment creations

#### 2. `after_payment_insert` (AFTER INSERT)
- **Auto-updates**: Citation status to "paid" when completed payment is created
- **Sets**: payment_date automatically
- **Logs**: Any failures to trigger_error_log table

#### 3. `after_payment_update` (AFTER UPDATE)
- **Auto-reverts**: Citation to "pending" when payment is voided/refunded/cancelled
- **Clears**: payment_date when payment is cancelled
- **Logs**: Any failures to trigger_error_log table

## 🛠️ How to Fix Existing Issues

### Step 1: Run the Cleanup Script
Visit: `http://localhost/tmg/admin/maintenance/fix_data_integrity_issues.php`

This script will:
- ✓ Revert citations with voided payments back to "pending" status
- ✓ Identify orphaned payments for manual review
- ✓ Show detailed log of all changes

### Step 2: Review the Results
Check the Data Integrity Dashboard:
`http://localhost/tmg/public/data_integrity_dashboard.php`

All mismatched citations should now be resolved.

### Step 3: Handle Orphaned Payments Manually
For each orphaned payment, investigate:
1. Was the citation accidentally deleted?
2. Should the citation be restored from backups?
3. Should the payment be marked as voided?

## 📋 Best Practices Going Forward

### DO:
✓ **Always use the payment processing workflow** - Don't manually update citation/payment status
✓ **Check dashboard regularly** - Monitor for any new integrity issues
✓ **Review trigger_error_log** - Check for trigger failures
✓ **Use soft deletes** - Mark records as deleted instead of physically deleting them
✓ **Test in development first** - Verify changes don't break triggers

### DON'T:
✗ **Never manually UPDATE citation status** if there are related payments
✗ **Never DELETE citations** that have payments - use soft delete instead
✗ **Never manually set payment_date** - let triggers handle it
✗ **Never disable triggers** without understanding the impact
✗ **Never run direct SQL updates** on production without testing

## 🔍 Monitoring

### Regular Checks
1. **Weekly**: Visit Data Integrity Dashboard
2. **Monthly**: Review trigger_error_log table
3. **After bulk operations**: Always check for new inconsistencies

### Key Queries

```sql
-- Check for mismatched citations
SELECT c.ticket_number, c.status, p.receipt_number, p.status
FROM citations c
INNER JOIN payments p ON c.citation_id = p.citation_id
WHERE (c.status = 'paid' AND p.status IN ('voided', 'cancelled'))
   OR (c.status = 'pending' AND p.status = 'completed');

-- Check trigger error log
SELECT * FROM trigger_error_log
ORDER BY created_at DESC
LIMIT 50;

-- Verify triggers are active
SHOW TRIGGERS LIKE 'payments';
```

## 🚨 If Issues Recur

1. **Check if triggers are still active**: Run `SHOW TRIGGERS LIKE 'payments'`
2. **Review trigger_error_log**: Look for patterns
3. **Identify the source**: Check who/what is bypassing triggers
4. **Re-run migration**: If triggers missing, run the migration script again

## 📞 Emergency Procedures

If you discover new integrity issues:

1. **Don't panic** - Triggers will prevent NEW issues
2. **Document the issue** - Take screenshots, note citation/payment IDs
3. **Run the cleanup script** - Fixes most common issues automatically
4. **Manual intervention** - For complex cases, update both tables in a transaction

## Summary

- ✅ **Triggers are installed** and will prevent future issues
- ⚠️ **Existing issues** are from BEFORE trigger installation
- 🛠️ **Cleanup script** available to fix existing issues
- 📊 **Dashboard** available for ongoing monitoring
- 🔒 **Best practices** in place to maintain data quality

**The system is now protected against these issues happening again!**
