# 📥 EXCEL CITATION IMPORT SYSTEM - COMPLETE GUIDE

## 🚀 Quick Start

### **Step 1: Setup Database**
```bash
# Navigate to your TMG directory
cd c:\xampp\htdocs\tmg

# Run the staging tables migration
mysql -u root -p traffic_system < database/migrations/create_import_staging_tables.sql
```

### **Step 2: Merge ExcelImporter Class**
```bash
# Combine the two parts into one file
# Copy content from ExcelImporterPart2.php and append to ExcelImporter.php
# (Remove the closing ?> from Part 1 first, then append Part 2)
```

OR use this command:
```bash
# Windows PowerShell
Get-Content services/ExcelImporter.php, services/ExcelImporterPart2.php | Set-Content services/ExcelImporterComplete.php

# Then rename
mv services/ExcelImporterComplete.php services/ExcelImporter.php
```

### **Step 3: DRY RUN (Recommended First!)**
```bash
# Test without making database changes
php import_excel.php --dry-run
```

This will show you:
- ✅ How many citations will be created
- ✅ How many duplicates will be removed
- ✅ Which tickets will be auto-generated
- ✅ Which conflicts will be resolved
- ❌ NO actual database changes

### **Step 4: LIVE IMPORT**
```bash
# When you're ready, run the real import
php import_excel.php
```

---

## 📋 What The Import Does

### **Phase 1: Data Extraction** (2 min)
- ✅ Reads all 10,218 Excel rows
- ✅ Normalizes data (uppercase names, clean plates, parse dates)
- ✅ Generates unique keys for matching

### **Phase 2: Deduplication** (1 min)
- ✅ Identifies 65 exact duplicate rows
- ✅ Marks them for deletion
- ✅ Keeps first occurrence

### **Phase 3: Multi-Violation Grouping** (2 min)
- ✅ Groups citations with same (ticket + person + date + place)
- ✅ Combines violations into ONE citation
- ✅ Creates ~186 multi-violation groups

Example:
```
Excel Rows:
  #12345 | Juan Cruz | Jan 15 | NO HELMET
  #12345 | Juan Cruz | Jan 15 | NO LICENSE
  #12345 | Juan Cruz | Jan 15 | EXPIRED REG

Database Result:
  Citation #12345
    └─ 3 violations
```

### **Phase 4: Conflict Resolution** (1 min)
- ✅ Detects same ticket used on different dates or people
- ✅ Keeps first occurrence with original ticket
- ✅ Generates new tickets with suffixes (12345-A, 12345-B)
- ✅ Logs all changes

Example:
```
Excel Rows:
  #12345 | Juan Cruz  | Jan 15 | NO HELMET
  #12345 | Pedro Santos | Mar 20 | NO LICENSE  ← CONFLICT!

Database Result:
  Citation #12345 (Juan Cruz, Jan 15)
  Citation #12345-A (Pedro Santos, Mar 20)
```

### **Phase 5: Auto-Generate Missing Tickets** (1 min)
- ✅ For 5 rows with empty tickets
- ✅ Generates: AUT-019001, AUT-019002, etc.
- ✅ Clearly marked for manual review

### **Phase 6: Driver Import** (3 min)
- ✅ Deduplicates drivers by (name + license)
- ✅ Creates ONE driver record per person
- ✅ Links all their citations

Example:
```
Excel Rows:
  #12345 | Juan Cruz | B123 | Jan 15
  #67890 | Juan Cruz | B123 | Mar 20
  #11111 | Juan Cruz | B123 | Jun 10

Database Result:
  drivers table: 1 record (Juan Cruz)
  citations table: 3 records (all linked to same driver_id)
```

### **Phase 7: Citation Import** (3 min)
- ✅ Creates ONE citation per group
- ✅ ~10,000 total citations
- ✅ All linked to drivers

### **Phase 8: Violation Import** (5 min)
- ✅ Parses violation text (splits by comma)
- ✅ Matches to violation_types (fuzzy matching)
- ✅ Calculates offense count (1st, 2nd, 3rd)
- ✅ Creates ~12,000-15,000 violation records

---

## 📊 Expected Results

### **Input: 10,218 Excel Rows**

### **Output:**
```
✅ ~10,000 Citations
   ├─ 9,920 with original ticket numbers
   ├─ 104 with conflict suffixes (12345-A, 67890-B)
   └─ 5 with AUT- prefix (AUT-019001 to AUT-019005)

✅ ~9,000 Drivers (deduplicated)
   └─ One record per person, linked to all their citations

✅ ~12,000-15,000 Violations
   └─ Avg 1.2-1.5 violations per citation

❌ 65 Exact Duplicates (auto-removed)
```

---

## 🔍 Post-Import Review

### **1. Find Auto-Generated Tickets (Need Manual Edit)**
```sql
SELECT citation_id, ticket_number, last_name, first_name, apprehension_datetime
FROM citations
WHERE ticket_number LIKE 'AUT-%'
ORDER BY ticket_number;

-- Result: 5 records
-- Action: Update with real ticket numbers
```

### **2. Find Conflict-Resolved Tickets (Verify Accuracy)**
```sql
SELECT citation_id, ticket_number, last_name, first_name, apprehension_datetime
FROM citations
WHERE ticket_number LIKE '%-_'
ORDER BY ticket_number;

-- Result: ~104 records
-- Action: Verify suffixes are correct
```

### **3. View Import Logs**
```sql
-- View all logs
SELECT log_level, log_type, message, excel_row, created_at
FROM import_logs
WHERE batch_id = 'BATCH-20251216-XXXXXX'
ORDER BY created_at DESC;

-- View errors only
SELECT * FROM import_logs
WHERE batch_id = 'BATCH-20251216-XXXXXX'
AND log_level = 'error';

-- View warnings only
SELECT * FROM import_logs
WHERE batch_id = 'BATCH-20251216-XXXXXX'
AND log_level = 'warning';
```

### **4. View Multi-Violation Groups**
```sql
SELECT * FROM import_citation_groups
WHERE batch_id = 'BATCH-20251216-XXXXXX'
ORDER BY row_count DESC;

-- Shows which citations combined multiple Excel rows
```

### **5. View Violation Matches**
```sql
SELECT excel_text, violation_type_name, match_type, match_confidence
FROM import_violation_mappings
WHERE batch_id = 'BATCH-20251216-XXXXXX'
AND match_type != 'exact'
ORDER BY match_confidence ASC;

-- Review fuzzy matches with low confidence
```

---

## ✏️ Editing Auto-Generated Tickets

### **To update AUT- prefix tickets:**

```sql
-- Example: Update AUT-019001 to real ticket 025601
UPDATE citations
SET ticket_number = '025601'
WHERE ticket_number = 'AUT-019001';

-- System will automatically validate uniqueness
```

### **Bulk update all AUT- tickets:**
```sql
-- First, review what you're changing
SELECT citation_id, ticket_number, last_name, first_name, apprehension_datetime
FROM citations
WHERE ticket_number LIKE 'AUT-%'
ORDER BY citation_id;

-- Then update one by one (safer than bulk)
-- UPDATE citations SET ticket_number = 'REAL-TICKET' WHERE citation_id = X;
```

---

## 🔄 Rollback (If Needed)

### **To completely remove imported data:**

```sql
-- Step 1: Get your batch ID
SELECT batch_id, started_at, citations_created, drivers_created
FROM import_batches
ORDER BY started_at DESC
LIMIT 5;

-- Step 2: Delete violations
DELETE FROM violations
WHERE import_batch_id = 'BATCH-20251216-XXXXXX';

-- Step 3: Delete citations
DELETE FROM citations
WHERE import_batch_id = 'BATCH-20251216-XXXXXX';

-- Step 4: Delete drivers (only those created in this batch)
DELETE FROM drivers
WHERE import_batch_id = 'BATCH-20251216-XXXXXX';

-- Step 5: Clean up staging
DELETE FROM import_staging WHERE batch_id = 'BATCH-20251216-XXXXXX';
DELETE FROM import_logs WHERE batch_id = 'BATCH-20251216-XXXXXX';
DELETE FROM import_batches WHERE batch_id = 'BATCH-20251216-XXXXXX';
```

---

## 📈 Data Integrity Checks

### **After import, verify integrity:**

```sql
-- Check 1: Orphaned violations (should be 0)
SELECT COUNT(*) as orphaned_violations
FROM violations v
LEFT JOIN citations c ON v.citation_id = c.citation_id
WHERE c.citation_id IS NULL;

-- Check 2: Citations without violations (should be 0)
SELECT COUNT(*) as citations_without_violations
FROM citations c
LEFT JOIN violations v ON c.citation_id = v.citation_id
WHERE v.violation_id IS NULL;

-- Check 3: Citations without drivers (should be 0)
SELECT COUNT(*) as citations_without_drivers
FROM citations c
LEFT JOIN drivers d ON c.driver_id = d.driver_id
WHERE d.driver_id IS NULL;

-- Check 4: Duplicate ticket numbers (should be 0)
SELECT ticket_number, COUNT(*) as count
FROM citations
GROUP BY ticket_number
HAVING COUNT(*) > 1;

-- All these should return 0!
```

---

## ⚙️ Advanced Options

### **Import Specific File**
```bash
php import_excel.php --file="path/to/your/file.xlsx"
```

### **Dry Run with Specific File**
```bash
php import_excel.php --dry-run --file="path/to/your/file.xlsx"
```

### **View Progress in Real-Time**
```bash
# In another terminal, watch the logs
mysql -u root -p traffic_system -e "
  SELECT log_level, message, created_at
  FROM import_logs
  WHERE batch_id = (SELECT batch_id FROM import_batches ORDER BY started_at DESC LIMIT 1)
  ORDER BY created_at DESC
  LIMIT 20;
"
```

---

## 🎯 Success Criteria

After import, you should have:

✅ **~10,000 citations** (from 10,218 rows after deduplication)
✅ **~9,000 unique drivers**
✅ **~12,000-15,000 violations**
✅ **All ticket numbers unique**
✅ **All citations linked to drivers**
✅ **All citations have at least one violation**
✅ **Repeat offenders properly tracked**
✅ **Complete audit trail in logs**

---

## ❓ Troubleshooting

### **Problem: "File not found"**
```bash
# Check file path
ls -la "NEW 2023-2024 CITATION TICKET  (Responses) (24) (1).xlsx"

# Or specify full path
php import_excel.php --file="c:\xampp\htdocs\tmg\NEW 2023-2024 CITATION TICKET  (Responses) (24) (1).xlsx"
```

### **Problem: "Class ExcelImporter not found"**
```bash
# Make sure you merged the two parts
cat services/ExcelImporter.php services/ExcelImporterPart2.php > services/ExcelImporterMerged.php
mv services/ExcelImporterMerged.php services/ExcelImporter.php
```

### **Problem: "Database connection failed"**
```bash
# Check your config.php has correct credentials
cat includes/config.php | grep DB_
```

### **Problem: "Too many duplicates"**
This is expected! The system is designed to:
- Remove exact duplicates (65)
- Resolve conflicts (104)
- Group multi-violations (186)

---

## 📞 Support

**Files Generated:**
- `analysis_report.txt` - Initial data analysis
- `duplicate_report.txt` - Detailed duplicate analysis
- `IMPORT_STRATEGY_FINAL.md` - Complete import strategy

**Database Tables:**
- `import_batches` - Track all import sessions
- `import_logs` - Detailed logs of every action
- `import_staging` - Temporary staging data
- `import_citation_groups` - Multi-violation groupings
- `import_ticket_conflicts` - Conflict resolutions

**Review these if you have questions!**

---

## 🎉 You're Ready!

**Recommended Flow:**
1. ✅ Run `--dry-run` first
2. ✅ Review the output
3. ✅ Run actual import
4. ✅ Check data integrity
5. ✅ Update AUT- tickets
6. ✅ Verify fuzzy matches
7. ✅ Done!

**Good luck with your import!** 🚀
